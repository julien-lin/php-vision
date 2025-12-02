<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour fournir une valeur par défaut
 */
class DefaultFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'default';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return $params[0] ?? '';
        }
        return $value;
    }
}
