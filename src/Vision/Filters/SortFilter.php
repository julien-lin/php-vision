<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Trie les éléments d'un array
 * 
 * Exemples:
 * - {{ items | sort }}       => Tri croissant (SORT_REGULAR)
 * - {{ items | sort(1) }}    => Tri SORT_NUMERIC
 * - {{ items | sort(2) }}    => Tri SORT_STRING
 */
class SortFilter implements FilterInterface
{
    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $array = $value;
        $sortFlags = SORT_REGULAR;

        if (!empty($params)) {
            $flag = (int)$params[0];
            if (in_array($flag, [SORT_NUMERIC, SORT_STRING, SORT_NATURAL, SORT_LOCALE_STRING], true)) {
                $sortFlags = $flag;
            }
        }

        sort($array, $sortFlags);
        return $array;
    }

    public function getName(): string
    {
        return 'sort';
    }
}
