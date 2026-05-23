<?php

declare(strict_types=1);

namespace App\Service\Gedcom;

use App\Entity\Person;
use App\Entity\Relation;
use App\Entity\User;
use App\Enum\Gender;
use App\Enum\RelationType;
use App\Repository\PersonRepository;
use App\Repository\RelationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Импорт GEDCOM 5.5.1 / 7.0.
 * Парсит INDI и FAM записи, создаёт Person с биографией и Relation для родства.
 *
 * Реализация прагматичная: понимает основные конструкции, игнорирует то, чего не знает.
 * Возвращает счётчики и список ошибок — пусть пользователь решает, что делать дальше.
 */
final class GedcomImporter
{
    public function __construct(
        private readonly PersonRepository $persons,
        private readonly RelationRepository $relations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{persons_created: int, relations_created: int, errors: list<string>}
     */
    public function import(string $content, User $importedBy): array
    {
        $stats = ['persons_created' => 0, 'relations_created' => 0, 'errors' => []];

        try {
            $records = $this->parseRecords($content);
        } catch (\Throwable $e) {
            $stats['errors'][] = 'Не удалось разобрать GEDCOM: ' . $e->getMessage();
            return $stats;
        }

        // Сначала создаём всех Person из INDI, запоминаем маппинг XREF → Person
        /** @var array<string, Person> $byXref */
        $byXref = [];
        foreach ($records as $rec) {
            if ($rec['tag'] !== 'INDI') {
                continue;
            }
            try {
                $person = $this->createPersonFromIndi($rec, $importedBy);
                $this->em->persist($person);
                $byXref[$rec['xref']] = $person;
                $stats['persons_created']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = 'INDI ' . $rec['xref'] . ': ' . $e->getMessage();
            }
        }
        $this->em->flush();

        // Затем разбираем FAM и создаём Relation
        foreach ($records as $rec) {
            if ($rec['tag'] !== 'FAM') {
                continue;
            }
            try {
                $created = $this->createRelationsFromFam($rec, $byXref, $importedBy);
                $stats['relations_created'] += $created;
            } catch (\Throwable $e) {
                $stats['errors'][] = 'FAM ' . $rec['xref'] . ': ' . $e->getMessage();
            }
        }
        $this->em->flush();

        return $stats;
    }

    /**
     * Превращаем плоский GEDCOM в массив записей:
     *   [ ['xref' => 'I1', 'tag' => 'INDI', 'lines' => [{level, tag, value}, ...]], ... ]
     *
     * @return list<array{xref: string, tag: string, lines: list<array{level: int, tag: string, value: string}>}>
     */
    private function parseRecords(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        $records = [];
        $current = null;
        $continuationBuffer = null;

        foreach ($lines as $rawLine) {
            $rawLine = rtrim($rawLine);
            if ($rawLine === '') {
                continue;
            }
            if (!preg_match('/^(\d+)\s+(@[^@]+@\s+)?(\S+)(?:\s+(.*))?$/u', $rawLine, $m)) {
                continue; // битая строка
            }
            $level = (int) $m[1];
            $xrefRaw = trim($m[2] ?? '');
            $tag = $m[3];
            $value = $m[4] ?? '';

            // Уровень 0 — начало новой записи
            if ($level === 0) {
                if ($current !== null) {
                    $records[] = $current;
                }
                $xref = '';
                if ($xrefRaw !== '') {
                    $xref = trim($xrefRaw, '@ ');
                }
                $current = ['xref' => $xref, 'tag' => $tag, 'lines' => []];
                continue;
            }

            if ($current === null) {
                continue;
            }

            // CONT / CONC — продолжение предыдущей строки
            if ($tag === 'CONT' || $tag === 'CONC') {
                $lastIdx = count($current['lines']) - 1;
                if ($lastIdx >= 0) {
                    $sep = $tag === 'CONT' ? "\n" : '';
                    $current['lines'][$lastIdx]['value'] .= $sep . $value;
                }
                continue;
            }

            $current['lines'][] = ['level' => $level, 'tag' => $tag, 'value' => $value];
        }
        if ($current !== null) {
            $records[] = $current;
        }
        return $records;
    }

    /**
     * @param array{xref: string, tag: string, lines: list<array{level: int, tag: string, value: string}>} $rec
     */
    private function createPersonFromIndi(array $rec, User $importedBy): Person
    {
        $name = 'Без имени';
        $gender = Gender::Unknown;
        $birthDate = null;
        $birthPlace = null;
        $deathDate = null;
        $deathPlace = null;
        $notes = [];

        $section = null; // BIRT, DEAT, MARR — куда относить вложенные DATE/PLAC
        foreach ($rec['lines'] as $line) {
            $level = $line['level'];
            $tag = $line['tag'];
            $value = trim($line['value']);

            if ($level === 1) {
                $section = null;
                switch ($tag) {
                    case 'NAME':
                        // "Иван /Петров/" → "Иван Петров"
                        $name = trim(str_replace('/', '', $value));
                        if ($name === '') { $name = 'Без имени'; }
                        break;
                    case 'SEX':
                        $gender = match (strtoupper($value)) {
                            'M' => Gender::Male,
                            'F' => Gender::Female,
                            default => Gender::Unknown,
                        };
                        break;
                    case 'BIRT': $section = 'BIRT'; break;
                    case 'DEAT': $section = 'DEAT'; break;
                    case 'NOTE':
                        if ($value !== '') { $notes[] = $value; }
                        break;
                }
            } elseif ($level === 2 && $section !== null) {
                if ($tag === 'DATE') {
                    if ($section === 'BIRT') { $birthDate = $value; }
                    if ($section === 'DEAT') { $deathDate = $value; }
                }
                if ($tag === 'PLAC') {
                    if ($section === 'BIRT') { $birthPlace = $value; }
                    if ($section === 'DEAT') { $deathPlace = $value; }
                }
            }
        }

        $person = new Person($name, $importedBy);
        $person->setGender($gender);
        if ($birthDate !== null) {
            $person->setBirthDate($birthDate);
            // Пробуем выдернуть год из строки даты
            if (preg_match('/\b(\d{4})\b/', $birthDate, $ym)) {
                $person->setBirthYear((int) $ym[1]);
            }
        }
        if ($birthPlace !== null) { $person->setBirthPlace($birthPlace); }
        if ($deathDate !== null) {
            $person->setDeathDate($deathDate);
            if (preg_match('/\b(\d{4})\b/', $deathDate, $ym)) {
                $person->setDeathYear((int) $ym[1]);
            }
        }
        if ($deathPlace !== null) { $person->setDeathPlace($deathPlace); }
        if ($notes !== []) { $person->setNotes(implode("\n\n", $notes)); }

        return $person;
    }

    /**
     * @param array{xref: string, tag: string, lines: list<array{level: int, tag: string, value: string}>} $rec
     * @param array<string, Person> $byXref
     */
    private function createRelationsFromFam(array $rec, array $byXref, User $importedBy): int
    {
        $husb = null;
        $wife = null;
        $children = [];
        $marrDate = null;
        $section = null;

        foreach ($rec['lines'] as $line) {
            $level = $line['level'];
            $tag = $line['tag'];
            $value = trim($line['value']);

            if ($level === 1) {
                $section = null;
                switch ($tag) {
                    case 'HUSB': $husb = $this->resolveXref($value, $byXref); break;
                    case 'WIFE': $wife = $this->resolveXref($value, $byXref); break;
                    case 'CHIL':
                        $child = $this->resolveXref($value, $byXref);
                        if ($child !== null) { $children[] = $child; }
                        break;
                    case 'MARR': $section = 'MARR'; break;
                }
            } elseif ($level === 2 && $section === 'MARR' && $tag === 'DATE') {
                $marrDate = $value;
            }
        }

        $created = 0;

        if ($husb !== null && $wife !== null) {
            if (!$this->relations->existsBetween($husb, $wife, RelationType::Spouse)) {
                $r = new Relation($husb, $wife, RelationType::Spouse, $importedBy);
                if ($marrDate !== null) { $r->setStartDate($marrDate); }
                $this->em->persist($r);
                $created++;
            }
        }

        foreach ($children as $child) {
            foreach (array_filter([$husb, $wife]) as $parent) {
                if (!$this->relations->existsBetween($parent, $child, RelationType::Parent)) {
                    $r = new Relation($parent, $child, RelationType::Parent, $importedBy);
                    $this->em->persist($r);
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * @param array<string, Person> $byXref
     */
    private function resolveXref(string $value, array $byXref): ?Person
    {
        $value = trim($value, '@ ');
        return $byXref[$value] ?? null;
    }
}
