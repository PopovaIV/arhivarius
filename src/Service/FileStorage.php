<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Хранилище загруженных файлов. Структура:
 *   var/uploads/{subdir}/{YYYY}/{MM}/{uuid}_{slug}.{ext}
 *
 * В будущем легко заменить на Flysystem-адаптер (S3/MinIO) — точка входа одна.
 */
final readonly class FileStorage
{
    /**
     * @param array<string> $allowedMimeTypes
     * @param array<string> $allowedExtensions
     */
    public function __construct(
        private string $uploadsDir,
        private SluggerInterface $slugger,
        private array $allowedMimeTypes = [
            'application/pdf',
            'application/epub+zip',
            'application/zip',
            'application/octet-stream',
            'application/x-mobipocket-ebook',
            'application/x-fictionbook+xml',
            'image/jpeg',
            'image/png',
            'image/tiff',
            'image/webp',
            'image/vnd.djvu',
            'image/x.djvu',
            'text/plain',
            'text/xml',
            'application/xml',
        ],
        private array $allowedExtensions = [
            'pdf', 'epub', 'djvu', 'djv', 'fb2', 'mobi', 'txt',
            'jpg', 'jpeg', 'png', 'tiff', 'tif', 'webp',
        ],
        private int $maxFileSize = 524288000, // 500 MB
    ) {
    }

    /**
     * @return array{path: string, mime: string, size: int, originalName: string}
     */
    public function store(UploadedFile $file, string $subdir): array
    {
        if ($file->getSize() > $this->maxFileSize) {
            throw new FileException(sprintf(
                'Файл слишком большой. Максимум %s МБ',
                $this->maxFileSize / 1024 / 1024,
            ));
        }

        $mime = (string) $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');

        // Принимаем файл если либо mime в allowlist, либо расширение известное —
        // это покрывает кейсы когда PHP определяет EPUB/MOBI как application/octet-stream
        $mimeOk = in_array($mime, $this->allowedMimeTypes, true);
        $extOk = in_array($extension, $this->allowedExtensions, true);
        if (!$mimeOk && !$extOk) {
            throw new FileException("Тип файла не поддерживается (mime: {$mime}, ext: {$extension})");
        }

        $originalName = $file->getClientOriginalName();
        $safeName = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME))->toString();
        if ($extension === '') {
            $extension = 'bin';
        }

        $now = new \DateTimeImmutable();
        $relativeDir = sprintf('%s/%s/%s', $subdir, $now->format('Y'), $now->format('m'));
        $absoluteDir = $this->uploadsDir . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new FileException("Не удалось создать директорию {$absoluteDir}");
        }

        $filename = bin2hex(random_bytes(8)) . '_' . $safeName . '.' . $extension;
        $file->move($absoluteDir, $filename);

        return [
            'path' => $relativeDir . '/' . $filename,
            'mime' => $mime,
            'size' => $file->getSize() ?: filesize($absoluteDir . '/' . $filename),
            'originalName' => $originalName,
        ];
    }

    public function absolutePath(string $storedPath): string
    {
        return $this->uploadsDir . '/' . $storedPath;
    }

    public function delete(string $storedPath): void
    {
        $abs = $this->absolutePath($storedPath);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    public function exists(string $storedPath): bool
    {
        return is_file($this->absolutePath($storedPath));
    }
}
