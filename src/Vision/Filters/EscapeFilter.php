<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour échapper les caractères HTML
 */
class EscapeFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'escape';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_string($value) && !is_numeric($value)) {
            return $value;
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
