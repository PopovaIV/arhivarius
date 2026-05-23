<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Book;
use App\Entity\ReadingProgress;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadingProgress>
 */
class ReadingProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingProgress::class);
    }

    public function findOrCreate(User $user, Book $book): ReadingProgress
    {
        $progress = $this->findOneBy(['user' => $user, 'book' => $book]);
        if ($progress === null) {
            $progress = new ReadingProgress($user, $book);
            $this->getEntityManager()->persist($progress);
        }
        return $progress;
    }

    public function findFor(User $user, Book $book): ?ReadingProgress
    {
        return $this->findOneBy(['user' => $user, 'book' => $book]);
    }

    /**
     * Прогрессы по нескольким книгам для одного пользователя — для списка книг.
     *
     * @param list<Book> $books
     * @return array<string, ReadingProgress> ключ — id книги в виде строки
     */
    public function mapForUserAndBooks(User $user, array $books): array
    {
        if ($books === []) {
            return [];
        }
        $progresses = $this->createQueryBuilder('p')
            ->where('p.user = :u AND p.book IN (:books)')
            ->setParameter('u', $user)
            ->setParameter('books', $books)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($progresses as $p) {
            $result[(string) $p->getBook()->getId()] = $p;
        }
        return $result;
    }
}
