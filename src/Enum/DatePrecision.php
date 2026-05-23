<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Точность датировки архивного документа.
 * В архивных документах дата часто неточная — год, десятилетие, диапазон.
 */
enum DatePrecision: string
{
    case Day = 'day';        // 12.04.1850
    case Month = 'month';    // апрель 1850
    case Year = 'year';      // 1850
    case Decade = 'decade';  // 1850-е
    case Range = 'range';    // 1850-1855
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Day     => 'Точная дата',
            self::Month   => 'Месяц и год',
            self::Year    => 'Год',
            self::Decade  => 'Десятилетие',
            self::Range   => 'Диапазон',
            self::Unknown => 'Дата неизвестна',
        };
    }
}
