<?php

declare(strict_types=1);

namespace App\Enum;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Мужской',
            self::Female => 'Женский',
            self::Unknown => 'Не указан',
        };
    }
}
