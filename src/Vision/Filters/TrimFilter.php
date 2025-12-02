<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour supprimer les espaces en début et fin
 */
class TrimFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'trim';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        $chars = $params[0] ?? null;
        return $chars ? trim($value, $chars) : trim($value);
    }
}
