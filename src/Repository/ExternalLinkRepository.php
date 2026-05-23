<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExternalLink;
use App\Enum\LinkCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalLink>
 */
class ExternalLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalLink::class);
    }

    public function save(ExternalLink $l, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($l);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Группировка по категориям для главной страницы раздела.
     *
     * @return array<string, list<ExternalLink>>
     */
    public function groupedByCategory(?string $query = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->addSelect('u')
            ->leftJoin('l.createdBy', 'u')
            ->orderBy('l.category', 'ASC')
            ->addOrderBy('l.title', 'ASC');

        if ($query !== null && trim($query) !== '') {
            $qb->andWhere('LOWER(l.title) LIKE :q OR LOWER(l.description) LIKE :q OR LOWER(l.tags) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower(trim($query)) . '%');
        }

        $links = $qb->getQuery()->getResult();

        $groups = [];
        foreach (LinkCategory::all() as $cat) {
            $groups[$cat->value] = [];
        }
        foreach ($links as $link) {
            $groups[$link->getCategory()->value][] = $link;
        }
        return $groups;
    }

    /**
     * @return list<ExternalLink>
     */
    public function findTrashed(int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.deletedAt IS NOT NULL')
            ->orderBy('l.deletedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
