<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function save(ActivityLog $log, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($log);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * @param array{user?: ?User, action?: ?string, from?: ?\DateTimeInterface, to?: ?\DateTimeInterface} $filters
     * @return list<ActivityLog>
     */
    public function search(array $filters, int $limit = 100, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (!empty($filters['user'])) {
            $qb->andWhere('a.user = :user')->setParameter('user', $filters['user']);
        }
        if (!empty($filters['action'])) {
            $qb->andWhere('a.action = :action')->setParameter('action', $filters['action']);
        }
        if (!empty($filters['from'])) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', $filters['to']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Сумма времени, проведённого пользователем (суммируем длительности сессий по sessionId).
     */
    public function getTotalTimeSeconds(User $user): int
    {
        $sql = <<<SQL
            SELECT COALESCE(SUM(session_duration), 0) AS total
            FROM (
                SELECT EXTRACT(EPOCH FROM (MAX(created_at) - MIN(created_at)))::int AS session_duration
                FROM activity_log
                WHERE user_id = :uid AND session_id IS NOT NULL
                GROUP BY session_id
            ) sessions
        SQL;

        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery($sql, ['uid' => $user->getId()])->fetchOne();

        return (int) $result;
    }
}
