<?php

declare(strict_types=1);

namespace App\Service\Gedcom;

use App\Entity\Person;
use App\Entity\Relation;
use App\Enum\Gender;
use App\Enum\RelationType;
use App\Repository\PersonRepository;
use App\Repository\RelationRepository;

/**
 * Экспорт реестра людей и связей в формате GEDCOM 5.5.1.
 *
 * Структура:
 *   HEAD                — заголовок
 *   INDI (Individual)   — на каждого Person, со ссылками на FAMS (семьи, где супруг) и FAMC (семья, где ребёнок)
 *   FAM (Family)        — на каждый брак / семейную единицу, с HUSB, WIFE, CHIL
 *   TRLR                — конец файла
 *
 * Семьи реконструируются из связей parent/spouse:
 *   - каждый брак = одна FAM
 *   - дети с общими родителями группируются в общую FAM
 *   - одинокий родитель тоже формирует FAM с одним супругом
 */
final readonly class GedcomExporter
{
    public function __construct(
        private PersonRepository $persons,
        private RelationRepository $relations,
    ) {
    }

    public function export(): string
    {
        $persons = $this->persons->findAllOrdered(10000);
        $relations = $this->relations->findAll();

        $personById = [];
        foreach ($persons as $p) {
            $personById[(string) $p->getId()] = $p;
        }

        // 1. Собираем семьи: ключ — отсортированная пара супругов (или один родитель),
        //    значение — { husb, wife, children[] }
        $families = $this->buildFamilies($persons, $relations, $personById);

        // 2. Для каждого Person — список FAMS (где супруг) и FAMC (где ребёнок)
        $personFamilies = [];
        foreach ($families as $famId => $fam) {
            foreach (['husb', 'wife'] as $role) {
                if ($fam[$role] !== null) {
                    $personFamilies[$fam[$role]]['fams'][] = $famId;
                }
            }
            foreach ($fam['children'] as $childId) {
                $personFamilies[$childId]['famc'][] = $famId;
            }
        }

        $now = (new \DateTimeImmutable())->format('d M Y');

        $out = "0 HEAD\n";
        $out .= "1 SOUR Genarchive\n";
        $out .= "2 NAME Family Archive Platform\n";
        $out .= "2 VERS 1.0\n";
        $out .= "1 DATE {$now}\n";
        $out .= "1 GEDC\n";
        $out .= "2 VERS 5.5.1\n";
        $out .= "2 FORM LINEAGE-LINKED\n";
        $out .= "1 CHAR UTF-8\n";

        // INDI
        foreach ($persons as $p) {
            $out .= $this->serializeIndi($p, $personFamilies[(string) $p->getId()] ?? []);
        }

        // FAM
        foreach ($families as $famId => $fam) {
            $out .= $this->serializeFam($famId, $fam);
        }

        $out .= "0 TRLR\n";
        return $out;
    }

    /**
     * @param list<Person> $persons
     * @param list<Relation> $relations
     * @param array<string, Person> $personById
     * @return array<string, array{husb: ?string, wife: ?string, children: list<string>}>
     */
    private function buildFamilies(array $persons, array $relations, array $personById): array
    {
        // Группируем родительские связи по ребёнку — каждый ребёнок принадлежит одной семье
        // (паре своих родителей, если оба известны, иначе одинокому)
        $parentsByChild = [];
        foreach ($relations as $r) {
            if ($r->getType() !== RelationType::Parent) {
                continue;
            }
            $childId = (string) $r->getToPerson()->getId();
            $parentId = (string) $r->getFromPerson()->getId();
            $parentsByChild[$childId][] = $parentId;
        }

        $families = [];
        $famKey = function (?string $a, ?string $b): string {
            $ids = array_filter([$a, $b]);
            sort($ids);
            return 'F_' . implode('_', $ids);
        };

        // Семьи из родительских связей
        foreach ($parentsByChild as $childId => $parentIds) {
            $parentIds = array_values(array_unique($parentIds));
            $a = $parentIds[0] ?? null;
            $b = $parentIds[1] ?? null;
            $key = $famKey($a, $b);
            if (!isset($families[$key])) {
                $families[$key] = $this->initFamily($a, $b, $personById);
            }
            $families[$key]['children'][] = $childId;
        }

        // Семьи из браков, не порождённые родительскими связями (бездетные браки)
        foreach ($relations as $r) {
            if ($r->getType() !== RelationType::Spouse) {
                continue;
            }
            $a = (string) $r->getFromPerson()->getId();
            $b = (string) $r->getToPerson()->getId();
            $key = $famKey($a, $b);
            if (!isset($families[$key])) {
                $families[$key] = $this->initFamily($a, $b, $personById);
            }
        }

        return $families;
    }

    /**
     * @param array<string, Person> $personById
     * @return array{husb: ?string, wife: ?string, children: list<string>}
     */
    private function initFamily(?string $a, ?string $b, array $personById): array
    {
        $husb = null;
        $wife = null;
        foreach (array_filter([$a, $b]) as $pid) {
            $person = $personById[$pid] ?? null;
            if ($person === null) {
                continue;
            }
            if ($person->getGender() === Gender::Male && $husb === null) {
                $husb = $pid;
            } elseif ($person->getGender() === Gender::Female && $wife === null) {
                $wife = $pid;
            } elseif ($husb === null) {
                $husb = $pid;
            } else {
                $wife = $pid;
            }
        }
        return ['husb' => $husb, 'wife' => $wife, 'children' => []];
    }

    /**
     * @param array{fams?: list<string>, famc?: list<string>} $links
     */
    private function serializeIndi(Person $p, array $links): string
    {
        $id = $p->getId();
        $out = "0 @I{$id}@ INDI\n";
        $out .= "1 NAME " . $this->gedcomName($p) . "\n";
        $out .= "1 SEX " . match ($p->getGender()) {
            Gender::Male => 'M',
            Gender::Female => 'F',
            Gender::Unknown => 'U',
        } . "\n";

        if ($p->getBirthDate() !== null || $p->getBirthYear() !== null || $p->getBirthPlace() !== null) {
            $out .= "1 BIRT\n";
            $date = $p->getBirthDate() ?? ($p->getBirthYear() !== null ? (string) $p->getBirthYear() : null);
            if ($date !== null) {
                $out .= "2 DATE " . $this->escapeValue($date) . "\n";
            }
            if ($p->getBirthPlace() !== null) {
                $out .= "2 PLAC " . $this->escapeValue($p->getBirthPlace()) . "\n";
            }
        }

        if ($p->getDeathDate() !== null || $p->getDeathYear() !== null || $p->getDeathPlace() !== null) {
            $out .= "1 DEAT\n";
            $date = $p->getDeathDate() ?? ($p->getDeathYear() !== null ? (string) $p->getDeathYear() : null);
            if ($date !== null) {
                $out .= "2 DATE " . $this->escapeValue($date) . "\n";
            }
            if ($p->getDeathPlace() !== null) {
                $out .= "2 PLAC " . $this->escapeValue($p->getDeathPlace()) . "\n";
            }
        }

        foreach (array_unique($links['famc'] ?? []) as $famId) {
            $out .= "1 FAMC @{$famId}@\n";
        }
        foreach (array_unique($links['fams'] ?? []) as $famId) {
            $out .= "1 FAMS @{$famId}@\n";
        }

        if ($p->getNotes() !== null && $p->getNotes() !== '') {
            $out .= $this->serializeNote($p->getNotes());
        }

        return $out;
    }

    private function gedcomName(Person $p): string
    {
        // В GEDCOM имя пишется как "Имя /Фамилия/". Мы храним FullName одной строкой,
        // потому экспортируем её как есть в имени, без слэшей — это корректный фолбэк.
        return $this->escapeValue($p->getFullName());
    }

    /**
     * @param array{husb: ?string, wife: ?string, children: list<string>} $fam
     */
    private function serializeFam(string $famId, array $fam): string
    {
        $out = "0 @{$famId}@ FAM\n";
        if ($fam['husb'] !== null) {
            $out .= "1 HUSB @I{$fam['husb']}@\n";
        }
        if ($fam['wife'] !== null) {
            $out .= "1 WIFE @I{$fam['wife']}@\n";
        }
        foreach (array_unique($fam['children']) as $childId) {
            $out .= "1 CHIL @I{$childId}@\n";
        }
        return $out;
    }

    private function serializeNote(string $text): string
    {
        // Разбиваем длинные заметки на CONT (продолжение), GEDCOM ограничивает строку 255 символами
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $first = array_shift($lines) ?? '';
        $out = "1 NOTE " . $this->escapeValue($first) . "\n";
        foreach ($lines as $line) {
            $out .= "2 CONT " . $this->escapeValue($line) . "\n";
        }
        return $out;
    }

    private function escapeValue(string $value): string
    {
        // GEDCOM не любит управляющие символы и @-без-XREF
        $value = preg_replace('/[\x00-\x1F]/u', ' ', $value) ?? $value;
        return $value;
    }
}
