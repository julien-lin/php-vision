<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour formater une date
 */
class DateFormatFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'date';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (empty($value)) {
            return '';
        }

        // Extract named parameters if present
        $namedParams = [];
        if (!empty($params) && is_array($params[count($params) - 1])) {
            $lastParam = $params[count($params) - 1];
            if (array_is_list($params)) {
                // It's a sequential array, last element is not named params
                $format = $params[0] ?? 'Y-m-d H:i:s';
            } else {
                // Has associative keys, so it's named params
                $namedParams = array_pop($params);
                $format = $params[0] ?? $namedParams['format'] ?? 'Y-m-d H:i:s';
            }
        } else {
            $format = $params[0] ?? 'Y-m-d H:i:s';
        }

        if (is_numeric($value)) {
            return date($format, (int)$value);
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return $value;
            }
            return date($format, $timestamp);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        return $value;
    }
}
