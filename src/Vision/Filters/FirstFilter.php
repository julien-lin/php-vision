<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Retourne le premier élément d'un array ou la première lettre d'une string
 * 
 * Exemples:
 * - {{ items | first }}        => Premier élément
 * - {{ items | first(2) }}     => Premiers 2 éléments (array)
 * - {{ text | first(3) }}      => Premiers 3 caractères
 */
class FirstFilter implements FilterInterface
{
    public function apply(mixed $value, array $params = []): mixed
    {
        $count = 1;
        if (!empty($params)) {
            $count = (int)$params[0];
        }

        if (is_array($value)) {
            return array_slice($value, 0, $count);
        }

        if (is_string($value)) {
            return mb_substr($value, 0, $count);
        }

        return $value;
    }

    public function getName(): string
    {
        return 'first';
    }
}
