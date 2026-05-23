<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\DocumentCategory;
use App\Form\CategoryMetadata\ConfessionListMetadataType;
use App\Form\CategoryMetadata\EmigrationMetadataType;
use App\Form\CategoryMetadata\MetricBookMetadataType;
use App\Form\CategoryMetadata\PressMetadataType;
use App\Form\CategoryMetadata\RevisionTaleMetadataType;
use App\Form\CategoryMetadata\ShipLogMetadataType;

/**
 * Мостик: категория документа → класс формы для metadata + partial-шаблон отображения.
 * Один и тот же резолвер используется и в форме, и в шаблонах.
 */
final class MetadataFormResolver
{
    /**
     * @return class-string|null
     */
    public function formClassFor(DocumentCategory $category): ?string
    {
        return match ($category) {
            DocumentCategory::MetricBook     => MetricBookMetadataType::class,
            DocumentCategory::RevisionTale   => RevisionTaleMetadataType::class,
            DocumentCategory::ConfessionList => ConfessionListMetadataType::class,
            DocumentCategory::Emigration     => EmigrationMetadataType::class,
            DocumentCategory::ShipLog        => ShipLogMetadataType::class,
            DocumentCategory::Press          => PressMetadataType::class,
        };
    }

    public function viewPartialFor(DocumentCategory $category): string
    {
        return 'documents/_metadata/' . $category->value . '.html.twig';
    }
}
