<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Joint les éléments d'un array avec un séparateur
 * 
 * Exemples:
 * - {{ items | join }}          => Pas de séparateur
 * - {{ items | join(", ") }}   => Virgule espace
 * - {{ items | join(" - ") }}  => Tiret avec espaces
 */
class JoinFilter implements FilterInterface
{
    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_array($value)) {
            return (string)$value;
        }

        $separator = $params[0] ?? '';
        $separator = (string)$separator;

        return implode($separator, array_map('strval', $value));
    }

    public function getName(): string
    {
        return 'join';
    }
}
