<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\DocumentCategory;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class AppGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'document_categories' => DocumentCategory::all(),
        ];
    }
}
