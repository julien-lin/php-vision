<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Filtre pour formater un nombre
 */
class NumberFormatFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'number';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }

        $decimals = isset($params[0]) ? (int)$params[0] : 0;
        $decimalSeparator = $params[1] ?? '.';
        $thousandsSeparator = $params[2] ?? ',';

        return number_format((float)$value, $decimals, $decimalSeparator, $thousandsSeparator);
    }
}
