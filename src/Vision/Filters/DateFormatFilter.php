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

        $format = $params[0] ?? 'Y-m-d H:i:s';

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
