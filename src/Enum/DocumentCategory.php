<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentCategory: string
{
    case MetricBook = 'metric_book';
    case RevisionTale = 'revision_tale';
    case ConfessionList = 'confession_list';
    case Emigration = 'emigration';
    case ShipLog = 'ship_log';
    case Press = 'press';

    public function label(): string
    {
        return match ($this) {
            self::MetricBook     => 'Метрические книги',
            self::RevisionTale   => 'Ревизские сказки',
            self::ConfessionList => 'Исповедные росписи',
            self::Emigration     => 'Эмиграционные документы',
            self::ShipLog        => 'Судоходные журналы',
            self::Press          => 'Пресса и заметки',
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::MetricBook     => 'запись метрической книги',
            self::RevisionTale   => 'ревизская сказка',
            self::ConfessionList => 'исповедная роспись',
            self::Emigration     => 'эмиграционный документ',
            self::ShipLog        => 'судоходный журнал',
            self::Press          => 'газетная заметка',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MetricBook     => 'bi-journal-text',
            self::RevisionTale   => 'bi-people',
            self::ConfessionList => 'bi-book-half',
            self::Emigration     => 'bi-globe',
            self::ShipLog        => 'bi-water',
            self::Press          => 'bi-newspaper',
        };
    }

    public function slug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    public static function fromSlug(string $slug): self
    {
        return self::from(str_replace('-', '_', $slug));
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
