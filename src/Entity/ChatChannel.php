<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ChatChannelType;
use App\Repository\ChatChannelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Чат-канал. Один публичный + по одному direct на каждую пару пользователей.
 *
 * Для direct-канала directKey = '{userId1}_{userId2}' где id1 < id2 — это даёт
 * детерминированный лукап без рассмотрения порядка участников.
 */
#[ORM\Entity(repositoryClass: ChatChannelRepository::class)]
#[ORM\Table(name: 'chat_channels')]
#[ORM\UniqueConstraint(name: 'uq_channel_direct_key', columns: ['direct_key'])]
class ChatChannel
{
    public const PUBLIC_NAME = 'public';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(length: 16, enumType: ChatChannelType::class)]
    private ChatChannelType $type;

    /**
     * Ключ для direct-каналов: '{minId}_{maxId}'. Null для публичного.
     */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $directKey = null;

    /**
     * Участники direct-канала. Для public — пустая коллекция, доступ открыт всем.
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'chat_channel_participants')]
    private Collection $participants;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(ChatChannelType $type, ?string $directKey = null)
    {
        $this->type = $type;
        $this->directKey = $directKey;
        $this->participants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getType(): ChatChannelType { return $this->type; }
    public function getDirectKey(): ?string { return $this->directKey; }
    public function getParticipants(): Collection { return $this->participants; }
    public function addParticipant(User $u): self { if (!$this->participants->contains($u)) { $this->participants->add($u); } return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isPublic(): bool { return $this->type === ChatChannelType::Public; }
    public function isDirect(): bool { return $this->type === ChatChannelType::Direct; }

    /**
     * Получить «другого» участника direct-канала относительно given user.
     */
    public function getOtherParticipant(User $self): ?User
    {
        foreach ($this->participants as $p) {
            if ($p->getId() !== $self->getId()) {
                return $p;
            }
        }
        return null;
    }

    public static function buildDirectKey(User $a, User $b): string
    {
        $ida = (int) $a->getId();
        $idb = (int) $b->getId();
        $min = min($ida, $idb);
        $max = max($ida, $idb);
        return $min . '_' . $max;
    }
}
