<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Person;
use App\Enum\RelationType;
use App\Repository\PersonRepository;
use App\Repository\RelationRepository;

/**
 * Готовит JSON-граф людей и связей для рендера древа на фронте.
 * Возвращает узлы и рёбра в формате, удобном для d3.js или любой другой визуализации.
 */
final readonly class TreeBuilder
{
    public function __construct(
        private PersonRepository $persons,
        private RelationRepository $relations,
    ) {
    }

    /**
     * @return array{nodes: list<array<string,mixed>>, edges: list<array<string,string>>}
     */
    public function buildFullTree(): array
    {
        $persons = $this->persons->findAllOrdered(10000);
        $edges = $this->relations->exportAllForTree();

        $nodes = [];
        foreach ($persons as $p) {
            $nodes[] = $this->personToNode($p);
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Граф вокруг конкретного человека — предки и потомки до depth уровней.
     * Все связи грузятся одним запросом, BFS выполняется в памяти.
     *
     * @return array{nodes: list<array<string,mixed>>, edges: list<array<string,string>>, center_id: string}
     */
    public function buildAround(Person $center, int $depth = 3): array
    {
        $allRelations = $this->relations->findAllWithPersons();

        // Строим карту людей и adjacency-список из загруженных данных
        /** @var array<string, Person> $personMap */
        $personMap = [(string) $center->getId() => $center];
        /** @var array<string, list<array{0: string, 1: string, 2: string, 3: string}>> $adjacency */
        $adjacency = [];

        foreach ($allRelations as $r) {
            $fId = (string) $r->getFromPerson()->getId();
            $tId = (string) $r->getToPerson()->getId();
            $personMap[$fId] = $r->getFromPerson();
            $personMap[$tId] = $r->getToPerson();
            $type = $r->getType()->value;

            // adjacency[id] = [neighborId, edgeFrom, edgeTo, edgeType]
            if ($r->getType() === RelationType::Parent) {
                $adjacency[$tId][] = [$fId, $fId, $tId, $type]; // родитель
                $adjacency[$fId][] = [$tId, $fId, $tId, $type]; // ребёнок
            } else {
                $adjacency[$fId][] = [$tId, $fId, $tId, $type];
                $adjacency[$tId][] = [$fId, $fId, $tId, $type];
            }
        }

        // BFS полностью в памяти — нет SQL в цикле
        /** @var array<string, Person> $visited */
        $visited = [];
        $edges = [];
        $queue = [[(string) $center->getId(), 0]];

        while ($queue !== []) {
            [$id, $level] = array_shift($queue);
            if (isset($visited[$id]) || !isset($personMap[$id])) {
                continue;
            }
            $visited[$id] = $personMap[$id];

            if ($level < $depth) {
                foreach ($adjacency[$id] ?? [] as [$nId, $eFrom, $eTo, $eType]) {
                    $edges[] = ['from' => $eFrom, 'to' => $eTo, 'type' => $eType];
                    $queue[] = [$nId, $level + 1];
                }
            }
        }

        $nodes = array_values(array_map($this->personToNode(...), $visited));

        return ['nodes' => $nodes, 'edges' => $this->dedupEdges($edges), 'center_id' => (string) $center->getId()];
    }

    /**
     * @return array<string,mixed>
     */
    private function personToNode(Person $p): array
    {
        return [
            'id' => (string) $p->getId(),
            'name' => $p->getFullName(),
            'gender' => $p->getGender()->value,
            'birth_year' => $p->getBirthYear(),
            'death_year' => $p->getDeathYear(),
            'lifespan' => $p->getLifespanLabel(),
        ];
    }

    /**
     * @param list<array<string,string>> $edges
     * @return list<array<string,string>>
     */
    private function dedupEdges(array $edges): array
    {
        $seen = [];
        $out = [];
        foreach ($edges as $e) {
            if ($e['type'] === RelationType::Spouse->value) {
                // Симметрия: считаем рёбра одинаковыми если идентификаторы переставлены
                $key = 'spouse_' . min($e['from'], $e['to']) . '_' . max($e['from'], $e['to']);
            } else {
                $key = 'parent_' . $e['from'] . '_' . $e['to'];
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $e;
        }
        return $out;
    }
}
