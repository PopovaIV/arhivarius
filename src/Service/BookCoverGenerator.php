<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Генерация превью обложки книги из PDF через pdftoppm.
 * Берёт первую страницу PDF, превращает в JPEG, кладёт в covers/{book_id}.jpg.
 * Если pdftoppm недоступен или процесс упал — возвращает null и не ломает upload.
 */
final readonly class BookCoverGenerator
{
    public function __construct(
        private string $uploadsDir,
        private int $widthPx = 400,
        private int $timeoutSeconds = 30,
    ) {
    }

    /**
     * @return string|null путь к обложке относительно uploadsDir (для сохранения в Book.coverPath) или null при неудаче
     */
    public function generateFromPdf(string $pdfAbsPath, string|int $bookId): ?string
    {
        if (!is_file($pdfAbsPath)) {
            return null;
        }

        $relativeDir = 'books/covers';
        $absoluteDir = $this->uploadsDir . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return null;
        }

        // pdftoppm создаёт файлы вида prefix-1.jpg. Используем -singlefile чтобы было просто prefix.jpg
        $prefix = $absoluteDir . '/' . $bookId;

        $process = new Process([
            'pdftoppm',
            '-jpeg',
            '-singlefile',
            '-scale-to-x', (string) $this->widthPx,
            '-scale-to-y', '-1',  // пропорционально
            '-f', '1',            // первая страница
            '-l', '1',
            $pdfAbsPath,
            $prefix,
        ]);
        $process->setTimeout($this->timeoutSeconds);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return null;
        }

        $resultPath = $prefix . '.jpg';
        if (!$process->isSuccessful() || !is_file($resultPath)) {
            return null;
        }

        return $relativeDir . '/' . $bookId . '.jpg';
    }

    public function deleteCover(?string $relativePath): void
    {
        if ($relativePath === null) {
            return;
        }
        $abs = $this->uploadsDir . '/' . $relativePath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
