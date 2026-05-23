<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChatChannel;
use App\Entity\User;
use App\Enum\ChatChannelType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatChannel>
 */
class ChatChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatChannel::class);
    }

    /**
     * Общий канал. Создаётся при первом обращении.
     */
    public function getOrCreatePublic(): ChatChannel
    {
        $channel = $this->findOneBy(['type' => ChatChannelType::Public->value]);
        if ($channel === null) {
            $channel = new ChatChannel(ChatChannelType::Public);
            $this->getEntityManager()->persist($channel);
            $this->getEntityManager()->flush();
        }
        return $channel;
    }

    /**
     * Личный канал между двумя пользователями. Лукап по directKey.
     */
    public function getOrCreateDirect(User $a, User $b): ChatChannel
    {
        if ($a->getId() === $b->getId()) {
            throw new \InvalidArgumentException('Нельзя создать диалог с самим собой');
        }

        $key = ChatChannel::buildDirectKey($a, $b);
        $channel = $this->findOneBy(['directKey' => $key]);
        if ($channel === null) {
            $channel = new ChatChannel(ChatChannelType::Direct, $key);
            $channel->addParticipant($a);
            $channel->addParticipant($b);
            $em = $this->getEntityManager();
            $em->persist($channel);
            $em->flush();
        }
        return $channel;
    }

    /**
     * Все direct-каналы пользователя, в которых он участник.
     *
     * @return list<ChatChannel>
     */
    public function findDirectChannelsForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->where('c.type = :t AND p = :u')
            ->setParameter('t', ChatChannelType::Direct->value)
            ->setParameter('u', $user)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
