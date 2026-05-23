<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Person;
use App\Entity\Relation;
use App\Enum\RelationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Relation>
 */
class RelationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Relation::class);
    }

    public function save(Relation $r, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($r);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Родители конкретного человека (parent → person).
     *
     * @return list<Person>
     */
    public function parentsOf(Person $person): array
    {
        $rels = $this->createQueryBuilder('r')
            ->where('r.toPerson = :p AND r.type = :t')
            ->setParameter('p', $person)
            ->setParameter('t', RelationType::Parent->value)
            ->getQuery()
            ->getResult();
        return array_map(fn (Relation $r) => $r->getFromPerson(), $rels);
    }

    /**
     * Дети конкретного человека (person → parent → children).
     *
     * @return list<Person>
     */
    public function childrenOf(Person $person): array
    {
        $rels = $this->createQueryBuilder('r')
            ->where('r.fromPerson = :p AND r.type = :t')
            ->setParameter('p', $person)
            ->setParameter('t', RelationType::Parent->value)
            ->getQuery()
            ->getResult();
        return array_map(fn (Relation $r) => $r->getToPerson(), $rels);
    }

    /**
     * Супруги. Связь хранится один раз, поэтому ищем в обе стороны.
     *
     * @return list<Person>
     */
    public function spousesOf(Person $person): array
    {
        $rels = $this->createQueryBuilder('r')
            ->where('(r.fromPerson = :p OR r.toPerson = :p) AND r.type = :t')
            ->setParameter('p', $person)
            ->setParameter('t', RelationType::Spouse->value)
            ->getQuery()
            ->getResult();

        $others = [];
        foreach ($rels as $r) {
            $other = $r->getFromPerson()->getId() === $person->getId()
                ? $r->getToPerson()
                : $r->getFromPerson();
            $others[] = $other;
        }
        return $others;
    }

    /**
     * Все связи с обоими людьми — для in-memory BFS в TreeBuilder.
     *
     * @return list<Relation>
     */
    public function findAllWithPersons(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('fp', 'tp')
            ->join('r.fromPerson', 'fp')
            ->join('r.toPerson', 'tp')
            ->getQuery()
            ->getResult();
    }

    /**
     * Все связи всех людей одним запросом — для рендера древа.
     * Возвращает массивы [from_id, to_id, type].
     *
     * @return list<array{from: string, to: string, type: string}>
     */
    public function exportAllForTree(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.fromPerson) AS from_id, IDENTITY(r.toPerson) AS to_id, r.type')
            ->getQuery()
            ->getArrayResult();

        return array_map(fn ($row) => [
            'from' => (string) $row['from_id'],
            'to' => (string) $row['to_id'],
            'type' => is_object($row['type']) ? $row['type']->value : (string) $row['type'],
        ], $rows);
    }

    /**
     * Существует ли уже такая связь (защита от дублей).
     */
    public function existsBetween(Person $from, Person $to, RelationType $type): bool
    {
        if ($type === RelationType::Spouse) {
            // Симметрично: и (a→b) и (b→a) — один и тот же брак
            $count = $this->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.type = :t AND ((r.fromPerson = :a AND r.toPerson = :b) OR (r.fromPerson = :b AND r.toPerson = :a))')
                ->setParameter('a', $from)
                ->setParameter('b', $to)
                ->setParameter('t', $type->value)
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            $count = $this->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.fromPerson = :a AND r.toPerson = :b AND r.type = :t')
                ->setParameter('a', $from)
                ->setParameter('b', $to)
                ->setParameter('t', $type->value)
                ->getQuery()
                ->getSingleScalarResult();
        }
        return (int) $count > 0;
    }

    public function findBetween(Person $a, Person $b): ?Relation
    {
        return $this->createQueryBuilder('r')
            ->where('(r.fromPerson = :a AND r.toPerson = :b) OR (r.fromPerson = :b AND r.toPerson = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
