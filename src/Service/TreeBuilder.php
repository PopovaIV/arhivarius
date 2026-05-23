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
     *
     * @return array{nodes: list<array<string,mixed>>, edges: list<array<string,string>>, center_id: string}
     */
    public function buildAround(Person $center, int $depth = 3): array
    {
        $visited = [];
        $edges = [];
        $queue = [[(string) $center->getId(), 0]];

        while ($queue !== []) {
            [$id, $level] = array_shift($queue);
            if (isset($visited[$id])) {
                continue;
            }
            $person = $this->persons->find($id);
            if ($person === null) {
                continue;
            }
            $visited[$id] = $person;

            if ($level >= $depth) {
                continue;
            }

            // Родители, дети, супруги — всех добавляем в очередь
            foreach ($this->relations->parentsOf($person) as $parent) {
                $pid = (string) $parent->getId();
                $edges[] = ['from' => $pid, 'to' => $id, 'type' => RelationType::Parent->value];
                $queue[] = [$pid, $level + 1];
            }
            foreach ($this->relations->childrenOf($person) as $child) {
                $cid = (string) $child->getId();
                $edges[] = ['from' => $id, 'to' => $cid, 'type' => RelationType::Parent->value];
                $queue[] = [$cid, $level + 1];
            }
            foreach ($this->relations->spousesOf($person) as $spouse) {
                $sid = (string) $spouse->getId();
                $edges[] = ['from' => $id, 'to' => $sid, 'type' => RelationType::Spouse->value];
                $queue[] = [$sid, $level + 1];
            }
        }

        $nodes = [];
        foreach ($visited as $p) {
            $nodes[] = $this->personToNode($p);
        }

        // Дедуп рёбер — нужно потому что обходим в обе стороны
        $edges = $this->dedupEdges($edges);

        return ['nodes' => $nodes, 'edges' => $edges, 'center_id' => (string) $center->getId()];
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
