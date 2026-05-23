<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DocumentFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentFile>
 */
class DocumentFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentFile::class);
    }

    public function save(DocumentFile $file, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($file);
        if ($flush) {
            $em->flush();
        }
    }
}
