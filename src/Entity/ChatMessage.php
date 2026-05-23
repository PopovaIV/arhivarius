<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_messages')]
#[ORM\Index(name: 'idx_msg_channel_id', columns: ['channel_id', 'id'])]
#[ORM\Index(name: 'idx_msg_deleted', columns: ['deleted_at'])]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ChatChannel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ChatChannel $channel;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    private string $body;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(ChatChannel $channel, User $author, string $body)
    {
        $this->channel = $channel;
        $this->author = $author;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getChannel(): ChatChannel { return $this->channel; }
    public function getAuthor(): User { return $this->author; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $b): self { $this->body = $b; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $at): self { $this->deletedAt = $at; return $this; }
    public function isDeleted(): bool { return $this->deletedAt !== null; }
}
