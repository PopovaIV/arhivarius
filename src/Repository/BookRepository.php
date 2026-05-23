<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    public function save(Book $book, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($book);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * @param array{q?: ?string, language?: ?string, year_from?: ?int, year_to?: ?int} $filters
     * @return list<Book>
     */
    public function search(array $filters, int $limit = 30, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (!empty($filters['q'])) {
            $qb->andWhere('LOWER(b.title) LIKE :q OR LOWER(b.authors) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower($filters['q']) . '%');
        }
        if (!empty($filters['language'])) {
            $qb->andWhere('b.language = :lang')->setParameter('lang', $filters['language']);
        }
        if (!empty($filters['year_from'])) {
            $qb->andWhere('b.year >= :yf')->setParameter('yf', $filters['year_from']);
        }
        if (!empty($filters['year_to'])) {
            $qb->andWhere('b.year <= :yt')->setParameter('yt', $filters['year_to']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array{q?: ?string, language?: ?string, year_from?: ?int, year_to?: ?int} $filters
     */
    public function countSearch(array $filters): int
    {
        $qb = $this->createQueryBuilder('b')->select('COUNT(b.id)');

        if (!empty($filters['q'])) {
            $qb->andWhere('LOWER(b.title) LIKE :q OR LOWER(b.authors) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower($filters['q']) . '%');
        }
        if (!empty($filters['language'])) {
            $qb->andWhere('b.language = :lang')->setParameter('lang', $filters['language']);
        }
        if (!empty($filters['year_from'])) {
            $qb->andWhere('b.year >= :yf')->setParameter('yf', $filters['year_from']);
        }
        if (!empty($filters['year_to'])) {
            $qb->andWhere('b.year <= :yt')->setParameter('yt', $filters['year_to']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Book>
     */
    public function findTrashed(int $limit = 100): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.deletedAt IS NOT NULL')
            ->orderBy('b.deletedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findIncludingDeleted(string|int $id): ?Book
    {
        return $this->find($id);
    }

    /**
     * @return list<string>
     */
    public function distinctLanguages(): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('DISTINCT b.language')
            ->where('b.language IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(fn ($r) => $r['language'] ?? null, $rows)));
    }

    /**
     * Полнотекстовый поиск по содержимому файлов книг.
     * Использует PostgreSQL FTS с tsvector GIN-индексом + ts_headline для сниппетов.
     * Не-админы автоматически не видят soft-deleted книги — через JOIN с проверкой deleted_at.
     *
     * @return list<array{book: Book, snippet: string, rank: float}>
     */
    public function searchContent(string $query, bool $isAdmin, int $limit = 30): array
    {
        if (trim($query) === '') {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $deletedCondition = $isAdmin ? '' : 'AND b.deleted_at IS NULL';

        $sql = <<<SQL
            SELECT
                b.id AS book_id,
                ts_rank(bf.search_vector, q) AS rank,
                ts_headline(
                    'simple',
                    bf.extracted_text,
                    q,
                    'MaxFragments=2, MaxWords=20, MinWords=5, StartSel=<mark>, StopSel=</mark>'
                ) AS snippet
            FROM book_files bf
            JOIN books b ON b.id = bf.book_id
            CROSS JOIN plainto_tsquery('simple', :q) q
            WHERE bf.search_vector @@ q $deletedCondition
            ORDER BY rank DESC
            LIMIT :limit
        SQL;

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('q', $query);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $rows = $stmt->executeQuery()->fetchAllAssociative();

        // Сворачиваем по книгам (книга может матчиться через несколько файлов)
        $byBook = [];
        foreach ($rows as $row) {
            $bookId = $row['book_id'];
            if (isset($byBook[$bookId])) {
                continue; // оставляем самый ранкованный
            }
            $byBook[$bookId] = $row;
        }

        if ($byBook === []) {
            return [];
        }

        $books = $this->createQueryBuilder('b')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', array_keys($byBook))
            ->getQuery()
            ->getResult();

        /** @var array<string, Book> $bookMap */
        $bookMap = [];
        foreach ($books as $b) {
            $bookMap[(string) $b->getId()] = $b;
        }

        $result = [];
        foreach ($byBook as $bookId => $row) {
            if (!isset($bookMap[(string) $bookId])) {
                continue; // отфильтрована soft-delete фильтром или удалена
            }
            $result[] = [
                'book' => $bookMap[(string) $bookId],
                'snippet' => (string) $row['snippet'],
                'rank' => (float) $row['rank'],
            ];
        }
        return $result;
    }
}
