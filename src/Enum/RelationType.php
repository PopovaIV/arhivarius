<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Тип родственной связи между двумя людьми.
 * Все остальные связи (брат, сестра, дед, внук, тётя) выводятся обходом графа.
 * Это минимальный набор для полной реконструкции древа.
 */
enum RelationType: string
{
    case Parent = 'parent';   // from → родитель → to (ребёнок)
    case Spouse = 'spouse';   // from ↔ супруг ↔ to (симметрично)

    public function label(): string
    {
        return match ($this) {
            self::Parent => 'Родитель',
            self::Spouse => 'Супруг(а)',
        };
    }
}
