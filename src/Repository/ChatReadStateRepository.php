<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChatChannel;
use App\Entity\ChatReadState;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatReadState>
 */
class ChatReadStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatReadState::class);
    }

    public function findOrCreate(User $user, ChatChannel $channel): ChatReadState
    {
        $state = $this->findOneBy(['user' => $user, 'channel' => $channel]);
        if ($state === null) {
            $state = new ChatReadState($user, $channel);
            $this->getEntityManager()->persist($state);
        }
        return $state;
    }

    public function markRead(User $user, ChatChannel $channel, ?int $lastMessageId): void
    {
        $state = $this->findOrCreate($user, $channel);
        $state->setLastReadMessageId($lastMessageId !== null ? (string) $lastMessageId : null);
        $this->getEntityManager()->flush();
    }
}
