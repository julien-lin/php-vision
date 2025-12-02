<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour encoder en JSON
 */
class JsonFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'json';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (isset($params[0]) && is_numeric($params[0])) {
            $flags = (int)$params[0];
        }
        $json = json_encode($value, $flags);
        return $json !== false ? $json : json_encode(['error' => 'encoding failed']);
    }
}
