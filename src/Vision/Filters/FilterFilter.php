<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filter filter - Keep only truthy values or apply custom filter
 * 
 * Usage: {{ items|filter }} to remove falsy values
 * Usage: {{ items|filter('callback', arg1, arg2) }} to apply custom filter
 */
class FilterFilter implements FilterInterface
{
    public function getName(): string
    {
        return 'filter';
    }

    public function apply(mixed $value, array $args = []): mixed
    {
        if (!is_array($value) && !($value instanceof \Traversable)) {
            return $value;
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value, false);
        }

        // If no callback provided, filter by truthy values
        if (empty($args)) {
            return array_filter($value);  // PHP's array_filter removes falsy values by default
        }

        // If callback provided
        $callback = $args[0] ?? null;
        if (!$callback) {
            return $value;
        }

        // Extract additional args for the callback
        $filterArgs = array_slice($args, 1);

        // Support for built-in filter functions like 'greaterThan', 'lessThan', etc.
        return array_filter($value, function ($item) use ($callback, $filterArgs) {
            return $this->applyCallback($item, $callback, $filterArgs);
        });
    }

    private function applyCallback(mixed $item, string $callback, array $args): bool
    {
        return match ($callback) {
            'greaterThan' => $item > ($args[0] ?? 0),
            'lessThan' => $item < ($args[0] ?? 0),
            'greaterThanOrEqual' => $item >= ($args[0] ?? 0),
            'lessThanOrEqual' => $item <= ($args[0] ?? 0),
            'equals' => $item == ($args[0] ?? null),
            'notEquals' => $item != ($args[0] ?? null),
            'contains' => is_string($item) && strpos($item, (string)($args[0] ?? '')) !== false,
            'startsWith' => is_string($item) && str_starts_with($item, (string)($args[0] ?? '')),
            'endsWith' => is_string($item) && str_ends_with($item, (string)($args[0] ?? '')),
            default => false,
        };
    }
}
