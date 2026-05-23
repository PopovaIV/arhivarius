<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Book;
use App\Entity\BookAnnotation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookAnnotation>
 */
class BookAnnotationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookAnnotation::class);
    }

    /**
     * @return list<BookAnnotation>
     */
    public function findFor(User $user, Book $book): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :u AND a.book = :b')
            ->setParameter('u', $user)
            ->setParameter('b', $book)
            ->orderBy('a.pdfPage', 'ASC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(BookAnnotation $a, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($a);
        if ($flush) {
            $em->flush();
        }
    }
}
