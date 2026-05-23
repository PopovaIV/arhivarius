<?php

declare(strict_types=1);

namespace App\Enum;

enum BookFormat: string
{
    case Pdf = 'pdf';
    case Epub = 'epub';
    case Djvu = 'djvu';
    case Fb2 = 'fb2';
    case Mobi = 'mobi';
    case Txt = 'txt';

    public function label(): string
    {
        return match ($this) {
            self::Pdf  => 'PDF',
            self::Epub => 'EPUB',
            self::Djvu => 'DJVU',
            self::Fb2  => 'FB2',
            self::Mobi => 'MOBI',
            self::Txt  => 'TXT',
        };
    }

    /**
     * Можно ли читать прямо в браузере (есть JS-читалка).
     */
    public function isReadable(): bool
    {
        return $this === self::Pdf || $this === self::Epub;
    }

    public function readerRoute(): ?string
    {
        return match ($this) {
            self::Pdf  => 'books_read_pdf',
            self::Epub => 'books_read_epub',
            default    => null,
        };
    }

    public static function fromMimeOrExtension(string $mime, string $extension): ?self
    {
        $ext = strtolower($extension);
        return match (true) {
            $mime === 'application/pdf' || $ext === 'pdf'   => self::Pdf,
            $mime === 'application/epub+zip' || $ext === 'epub' => self::Epub,
            str_contains($mime, 'djvu') || $ext === 'djvu' || $ext === 'djv' => self::Djvu,
            $ext === 'fb2' => self::Fb2,
            $ext === 'mobi' => self::Mobi,
            str_starts_with($mime, 'text/') || $ext === 'txt' => self::Txt,
            default => null,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pdf  => 'bi-file-pdf',
            self::Epub => 'bi-book',
            self::Djvu => 'bi-file-earmark-richtext',
            default    => 'bi-file-earmark',
        };
    }
}
