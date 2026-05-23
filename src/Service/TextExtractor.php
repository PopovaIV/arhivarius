<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\BookFormat;
use Symfony\Component\Process\Process;

/**
 * Извлечение текста из книг для полнотекстового поиска.
 * Все методы устойчивы к ошибкам: при провале возвращают null/'', upload не ломается.
 *
 * - PDF  → pdftotext
 * - EPUB → ZipArchive + DOMDocument
 * - FB2  → загружаем XML, выкидываем теги
 * - TXT  → читаем напрямую (с конверсией кодировки в UTF-8)
 * - DJVU → djvutxt
 */
final readonly class TextExtractor
{
    public function __construct(
        private string $uploadsDir,
        private int $timeoutSeconds = 60,
        private int $maxBytesOutput = 20_000_000, // 20 МБ извлечённого текста — пилим если больше
    ) {
    }

    public function extract(BookFormat $format, string $storedPath): ?string
    {
        $abs = $this->uploadsDir . '/' . $storedPath;
        if (!is_file($abs)) {
            return null;
        }

        try {
            $text = match ($format) {
                BookFormat::Pdf  => $this->extractPdf($abs),
                BookFormat::Epub => $this->extractEpub($abs),
                BookFormat::Fb2  => $this->extractFb2($abs),
                BookFormat::Txt  => $this->extractTxt($abs),
                BookFormat::Djvu => $this->extractDjvu($abs),
                default          => null,
            };
        } catch (\Throwable) {
            return null;
        }

        if ($text === null || trim($text) === '') {
            return null;
        }

        // Нормализуем: схлопываем повторяющиеся пробелы, ограничиваем длину
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if (strlen($text) > $this->maxBytesOutput) {
            $text = substr($text, 0, $this->maxBytesOutput);
        }
        return $text;
    }

    private function extractPdf(string $abs): ?string
    {
        $process = new Process(['pdftotext', '-layout', '-nopgbrk', $abs, '-']);
        $process->setTimeout($this->timeoutSeconds);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }
        return $process->getOutput();
    }

    private function extractEpub(string $abs): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($abs) !== true) {
            return null;
        }

        $buf = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            $lower = strtolower($name);
            // Берём содержательные части EPUB: xhtml/html файлы текста
            if (!str_ends_with($lower, '.xhtml') && !str_ends_with($lower, '.html') && !str_ends_with($lower, '.htm')) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }
            $buf .= $this->stripHtml($content) . "\n";
            if (strlen($buf) > $this->maxBytesOutput) {
                break;
            }
        }
        $zip->close();
        return $buf;
    }

    private function extractFb2(string $abs): ?string
    {
        $content = @file_get_contents($abs);
        if ($content === false) {
            return null;
        }
        return $this->stripHtml($content);
    }

    private function extractTxt(string $abs): ?string
    {
        $content = @file_get_contents($abs);
        if ($content === false) {
            return null;
        }
        // Пытаемся определить кодировку и привести к UTF-8
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'KOI8-R', 'ISO-8859-1'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        return $content;
    }

    private function extractDjvu(string $abs): ?string
    {
        // Проверяем доступность djvutxt — если нет, пропускаем
        $check = new Process(['which', 'djvutxt']);
        $check->run();
        if (!$check->isSuccessful()) {
            return null;
        }

        $process = new Process(['djvutxt', $abs]);
        $process->setTimeout($this->timeoutSeconds);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }
        return $process->getOutput();
    }

    private function stripHtml(string $html): string
    {
        // Убираем скрипты и стили целиком (вместе с содержимым)
        $html = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        // Декодируем сущности, убираем теги
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $text;
    }
}
