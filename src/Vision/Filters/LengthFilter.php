<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour obtenir la longueur d'une chaîne ou d'un tableau
 */
class LengthFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'length';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8');
        }
        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }
        return 0;
    }
}
