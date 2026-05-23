<?php

declare(strict_types=1);

namespace App\Enum;

enum MetricRecordType: string
{
    case Birth = 'birth';
    case Marriage = 'marriage';
    case Death = 'death';

    public function label(): string
    {
        return match ($this) {
            self::Birth    => 'Рождение / крещение',
            self::Marriage => 'Бракосочетание',
            self::Death    => 'Смерть / погребение',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Birth    => 'bi-stars',
            self::Marriage => 'bi-suit-heart',
            self::Death    => 'bi-flower2',
        };
    }
}
