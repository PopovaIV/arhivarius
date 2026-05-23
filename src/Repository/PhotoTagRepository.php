<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PhotoTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PhotoTag>
 */
class PhotoTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PhotoTag::class);
    }

    public function save(PhotoTag $t, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($t);
        if ($flush) {
            $em->flush();
        }
    }
}
