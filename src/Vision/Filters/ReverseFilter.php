<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Inverse l'ordre des éléments d'un array ou les caractères d'une string
 * 
 * Exemples:
 * - {{ items | reverse }}     => Array inversé
 * - {{ text | reverse }}      => Texte inversé (palindrome?)
 */
class ReverseFilter implements FilterInterface
{
    public function apply(mixed $value, array $params = []): mixed
    {
        if (is_array($value)) {
            return array_reverse($value);
        }

        if (is_string($value)) {
            $chars = mb_str_split($value);
            return implode('', array_reverse($chars));
        }

        return $value;
    }

    public function getName(): string
    {
        return 'reverse';
    }
}
