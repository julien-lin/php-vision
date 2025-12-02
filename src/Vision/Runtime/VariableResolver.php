<?php

namespace JulienLinard\Vision\Runtime;

class VariableResolver
{
    public function resolve(array $variables, string $path): mixed
    {
        $parts = explode('.', $path);
        $value = $variables;

        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } elseif (is_object($value)) {
                $getter = 'get' . ucfirst($part);
                if (method_exists($value, $getter)) {
                    $value = $value->$getter();
                } elseif (method_exists($value, 'is' . ucfirst($part))) {
                    $getter = 'is' . ucfirst($part);
                    $value = $value->$getter();
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
}
