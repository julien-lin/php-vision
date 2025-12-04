<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Extrait une portion d'un array ou d'une string
 * 
 * Exemples:
 * - {{ items | slice(1, 3) }}  => Éléments de l'index 1, longueur 3
 * - {{ text | slice(0, 5) }}   => Premiers 5 caractères
 * - {{ items | slice(2) }}     => À partir de l'index 2
 */
class SliceFilter implements FilterInterface
{
    public function apply(mixed $value, array $params = []): mixed
    {
        if (empty($params)) {
            return $value;
        }

        $offset = (int)($params[0] ?? 0);
        $length = isset($params[1]) ? (int)$params[1] : null;

        if (is_array($value)) {
            return array_slice($value, $offset, $length);
        }

        if (is_string($value)) {
            return mb_substr($value, $offset, $length);
        }

        return $value;
    }

    public function getName(): string
    {
        return 'slice';
    }
}
