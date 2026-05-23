<?php

declare(strict_types=1);

namespace App\Enum;

enum ChatChannelType: string
{
    case Public = 'public';   // Общий чат, один на всю систему
    case Direct = 'direct';   // Личный диалог между двумя пользователями

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Общий чат',
            self::Direct => 'Личные сообщения',
        };
    }
}
