<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour convertir en minuscules
 */
class LowerFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'lower';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        return mb_strtolower($value, 'UTF-8');
    }
}
