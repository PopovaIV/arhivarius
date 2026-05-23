<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Person>
 */
class PersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    public function save(Person $p, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($p);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * @return list<Person>
     */
    public function findAllOrdered(int $limit = 100, int $offset = 0, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.fullName', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($query !== null && trim($query) !== '') {
            $qb->andWhere('LOWER(p.fullName) LIKE :q OR LOWER(p.aliases) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower(trim($query)) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $query = null): int
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)');
        if ($query !== null && trim($query) !== '') {
            $qb->andWhere('LOWER(p.fullName) LIKE :q OR LOWER(p.aliases) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower(trim($query)) . '%');
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Автокомплит для диалога тегирования: максимум 10 совпадений.
     *
     * @return list<Person>
     */
    public function autocomplete(string $query, int $limit = 10): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        return $this->createQueryBuilder('p')
            ->where('LOWER(p.fullName) LIKE :q OR LOWER(p.aliases) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->orderBy('p.fullName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Person>
     */
    public function findTrashed(int $limit = 100): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.deletedAt IS NOT NULL')
            ->orderBy('p.deletedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
