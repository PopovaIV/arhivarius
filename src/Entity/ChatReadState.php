<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChatReadStateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Состояние прочтения канала пользователем.
 * Уникальная пара (user, channel). Хранит id последнего прочитанного сообщения.
 * Используется для подсчёта непрочитанного в навигации.
 */
#[ORM\Entity(repositoryClass: ChatReadStateRepository::class)]
#[ORM\Table(name: 'chat_read_state')]
#[ORM\UniqueConstraint(name: 'uq_chat_read_state', columns: ['user_id', 'channel_id'])]
class ChatReadState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: ChatChannel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChatChannel $channel;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $lastReadMessageId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, ChatChannel $channel)
    {
        $this->user = $user;
        $this->channel = $channel;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getChannel(): ChatChannel { return $this->channel; }
    public function getLastReadMessageId(): ?string { return $this->lastReadMessageId; }
    public function setLastReadMessageId(?string $id): self { $this->lastReadMessageId = $id; $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
