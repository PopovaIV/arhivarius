<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Document;
use App\Entity\User;
use App\Enum\DocumentCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function save(Document $doc, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($doc);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Документы категории, отсортированные по дате.
     * Через включённый SoftDeleteFilter автоматически отсекаются удалённые.
     *
     * @return list<Document>
     */
    public function findByCategory(DocumentCategory $category, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.category = :cat')
            ->setParameter('cat', $category->value)
            ->orderBy('d.documentYear', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByCategory(DocumentCategory $category): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.category = :cat')
            ->setParameter('cat', $category->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Удалённые документы — только админ должен попадать сюда.
     * Создаём свой QB, минуя фильтр (фильтр у админа и так выключен).
     *
     * @return list<Document>
     */
    public function findTrashed(int $limit = 100): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.deletedAt IS NOT NULL')
            ->orderBy('d.deletedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти даже удалённый. Нужно админу для восстановления/окончательного удаления.
     */
    public function findIncludingDeleted(string|int $id): ?Document
    {
        return $this->find($id);
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('d');
    }
}
