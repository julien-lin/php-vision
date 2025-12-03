<?php

namespace JulienLinard\Vision\Runtime;

class VariableResolver
{
    /**
     * Cache des réflexions et méthodes pour éviter les coûts répétitifs
     * Limité à 500 entrées pour éviter les fuites mémoire
     */
    private array $reflectionCache = [];
    
    private const MAX_REFLECTION_CACHE_SIZE = 500;

    public function resolve(array $variables, string $path): mixed
    {
        $parts = explode('.', $path);
        $value = $variables;

        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } elseif (is_object($value)) {
                $className = get_class($value);
                $cacheKey = $className . '::' . $part;
                
                // Cache hit pour reflection
                if (isset($this->reflectionCache[$cacheKey])) {
                    $cached = $this->reflectionCache[$cacheKey];
                    if ($cached['type'] === 'method') {
                        $value = $value->{$cached['name']}();
                    } elseif ($cached['type'] === 'property') {
                        $reflection = $cached['reflection'];
                        $property = $reflection->getProperty($part);
                        if (!$property->isPublic() && PHP_VERSION_ID < 80100) {
                            $property->setAccessible(true);
                        }
                        $value = $property->getValue($value);
                    }
                    continue;
                }
                
                // Nouvelle résolution
                $getter = 'get' . ucfirst($part);
                if (method_exists($value, $getter)) {
                    $value = $value->$getter();
                    // Cache
                    if (count($this->reflectionCache) < self::MAX_REFLECTION_CACHE_SIZE) {
                        $this->reflectionCache[$cacheKey] = ['type' => 'method', 'name' => $getter];
                    }
                } elseif (method_exists($value, 'is' . ucfirst($part))) {
                    $getter = 'is' . ucfirst($part);
                    $value = $value->$getter();
                    if (count($this->reflectionCache) < self::MAX_REFLECTION_CACHE_SIZE) {
                        $this->reflectionCache[$cacheKey] = ['type' => 'method', 'name' => $getter];
                    }
                } elseif (method_exists($value, '__get')) {
                    $value = $value->$part;
                } elseif (property_exists($value, $part)) {
                    try {
                        $reflection = new \ReflectionClass($value);
                        $property = $reflection->getProperty($part);
                        if ($property->isPublic()) {
                            $value = $property->getValue($value);
                        } else {
                            if (PHP_VERSION_ID < 80100) {
                                $property->setAccessible(true);
                            }
                            $value = $property->getValue($value);
                        }
                        // Cache reflection + property
                        if (count($this->reflectionCache) < self::MAX_REFLECTION_CACHE_SIZE) {
                            $this->reflectionCache[$cacheKey] = [
                                'type' => 'property',
                                'reflection' => $reflection,
                                'property' => $property
                            ];
                        }
                    } catch (\ReflectionException) {
                        return null;
                    }
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $value;
    }

    public function format(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if (is_array($value) || is_object($value)) {
            return '';
        }
        return (string)$value;
    }

    /**
     * Nettoie le cache des réflexions
     * Utile pour libérer la mémoire si nécessaire
     */
    public function clearReflectionCache(): void
    {
        $this->reflectionCache = [];
    }
}
