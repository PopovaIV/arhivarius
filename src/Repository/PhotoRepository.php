<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Person;
use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    public function save(Photo $p, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($p);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * @return list<Photo>
     */
    public function findAllOrdered(int $limit = 60, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.takenYear', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Все фотографии, на которых отмечен данный человек.
     *
     * @return list<Photo>
     */
    public function findByPerson(Person $person): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.tags', 't')
            ->where('t.person = :person')
            ->setParameter('person', $person)
            ->orderBy('p.takenYear', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Photo>
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
