<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Map filter - Transform array items
 * 
 * Usage: {{ items|map('double') }} to apply transformation
 * Usage: {{ users|map('name') }} to extract attribute 'name' from each item
 */
class MapFilter implements FilterInterface
{
    public function getName(): string
    {
        return 'map';
    }

    public function apply(mixed $value, array $args = []): mixed
    {
        if (!is_array($value) && !($value instanceof \Traversable)) {
            return $value;
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value, false);
        }

        $callback = $args[0] ?? null;
        if (!$callback) {
            return $value;
        }

        // Extract additional args for the callback
        $mapArgs = array_slice($args, 1);

        // For attribute extraction like map('name'), extract from each array item
        // For transformation callbacks like map('double'), apply the transformation
        return array_map(function ($item) use ($callback, $mapArgs) {
            return $this->applyCallback($item, $callback, $mapArgs);
        }, $value);
    }

    private function applyCallback(mixed $item, string $callback, array $args): mixed
    {
        // Try to extract attribute if callback looks like an attribute name (lowercase)
        if (is_array($item) && isset($item[$callback])) {
            return $item[$callback];
        }

        // Try object property access
        if (is_object($item) && property_exists($item, $callback)) {
            return $item->$callback;
        }

        // Apply built-in transformations
        return match ($callback) {
            'double' => $item * 2,
            'triple' => $item * 3,
            'square' => $item * $item,
            'sqrt' => sqrt((float)$item),
            'abs' => abs((float)$item),
            'upper' => strtoupper((string)$item),
            'lower' => strtolower((string)$item),
            'trim' => trim((string)$item),
            'strlen' => strlen((string)$item),
            'reverse' => strrev((string)$item),
            'json' => json_encode($item),
            default => $item,
        };
    }
}
