<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChatChannel;
use App\Entity\ChatMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    public function save(ChatMessage $m, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($m);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * История канала — последние N сообщений.
     * Возвращает в хронологическом порядке (старые сверху).
     *
     * @return list<ChatMessage>
     */
    public function findRecent(ChatChannel $channel, int $limit = 100): array
    {
        $msgs = $this->createQueryBuilder('m')
            ->where('m.channel = :c AND m.deletedAt IS NULL')
            ->setParameter('c', $channel)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        return array_reverse($msgs);
    }

    /**
     * Сообщения после given ID — для long polling.
     *
     * @return list<ChatMessage>
     */
    public function findSince(ChatChannel $channel, int $sinceId, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.channel = :c AND m.id > :since AND m.deletedAt IS NULL')
            ->setParameter('c', $channel)
            ->setParameter('since', $sinceId)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLatestId(ChatChannel $channel): ?int
    {
        $res = $this->createQueryBuilder('m')
            ->select('MAX(m.id) AS max_id')
            ->where('m.channel = :c AND m.deletedAt IS NULL')
            ->setParameter('c', $channel)
            ->getQuery()
            ->getSingleScalarResult();
        return $res === null ? null : (int) $res;
    }

    /**
     * Количество сообщений в канале после lastReadId (для бейджа непрочитанного).
     */
    public function countSince(ChatChannel $channel, ?int $lastReadId): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.channel = :c AND m.deletedAt IS NULL')
            ->setParameter('c', $channel);

        if ($lastReadId !== null) {
            $qb->andWhere('m.id > :last')->setParameter('last', $lastReadId);
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
