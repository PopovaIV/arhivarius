<?php

declare(strict_types=1);

namespace App\Enum;

enum LinkCategory: string
{
    case Archive = 'archive';            // Архивные сайты: РГИА, ЦГИА и т.д.
    case Genealogy = 'genealogy';        // Familio, MyHeritage, FamilySearch, Geni
    case Library = 'library';            // Цифровые библиотеки, репозитории книг
    case Database = 'database';          // Базы данных предков, мемориалы
    case Forum = 'forum';                // Форумы и сообщества
    case Map = 'map';                    // Исторические карты, гео-сервисы
    case Press = 'press';                // Архивы прессы
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Archive   => 'Архивные сайты',
            self::Genealogy => 'Генеалогические сервисы',
            self::Library   => 'Библиотеки',
            self::Database  => 'Базы данных и мемориалы',
            self::Forum     => 'Форумы и сообщества',
            self::Map       => 'Карты и геоданные',
            self::Press     => 'Архивы прессы',
            self::Other     => 'Прочее',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Archive   => 'bi-archive',
            self::Genealogy => 'bi-diagram-3',
            self::Library   => 'bi-book',
            self::Database  => 'bi-database',
            self::Forum     => 'bi-chat-square-text',
            self::Map       => 'bi-map',
            self::Press     => 'bi-newspaper',
            self::Other     => 'bi-link-45deg',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
