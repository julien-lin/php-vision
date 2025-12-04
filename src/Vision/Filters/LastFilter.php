<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Retourne le dernier élément d'un array ou la dernière lettre d'une string
 * 
 * Exemples:
 * - {{ items | last }}        => Dernier élément
 * - {{ items | last(2) }}     => Derniers 2 éléments (array)
 * - {{ text | last(3) }}      => Derniers 3 caractères
 */
class LastFilter implements FilterInterface
{
    public function apply(mixed $value, array $params = []): mixed
    {
        $count = 1;
        if (!empty($params)) {
            $count = (int)$params[0];
        }

        if (is_array($value)) {
            return array_slice($value, -$count ?: null);
        }

        if (is_string($value)) {
            return mb_substr($value, -$count ?: null);
        }

        return $value;
    }

    public function getName(): string
    {
        return 'last';
    }
}
