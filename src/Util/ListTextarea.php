<?php

declare(strict_types=1);

namespace App\Util;

final class ListTextarea
{
    /**
     * "Иван Петров, мещанин\nАнна Сидорова, крестьянка" → ["Иван Петров, мещанин", "Анна Сидорова, крестьянка"]
     *
     * @return list<string>
     */
    public static function fromText(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        if ($lines === false) {
            return [];
        }
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }
        return $result;
    }

    /**
     * @param list<string>|null $items
     */
    public static function toText(?array $items): string
    {
        return is_array($items) ? implode("\n", $items) : '';
    }
}
