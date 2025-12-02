<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour convertir en majuscules
 */
class UpperFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'upper';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        return mb_strtoupper($value, 'UTF-8');
    }
}
