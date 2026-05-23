<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Журнал действий пользователей. Виден только админу.
 * Покрывает: вход/выход, просмотр документов и книг, скачивания,
 * добавление/удаление контента, чтение страниц книг, длительность сессии.
 */
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(name: 'idx_activity_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_activity_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_activity_action', columns: ['action'])]
class ActivityLog
{
    // Типы действий
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_PAGE_VIEW = 'page_view';
    public const ACTION_DOC_VIEW = 'document_view';
    public const ACTION_DOC_DOWNLOAD = 'document_download';
    public const ACTION_DOC_CREATE = 'document_create';
    public const ACTION_DOC_DELETE = 'document_delete';
    public const ACTION_BOOK_OPEN = 'book_open';
    public const ACTION_BOOK_DOWNLOAD = 'book_download';
    public const ACTION_BOOK_READ_PROGRESS = 'book_read_progress';
    public const ACTION_PHOTO_UPLOAD = 'photo_upload';
    public const ACTION_USER_CREATE = 'user_create';
    public const ACTION_USER_DEACTIVATE = 'user_deactivate';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'activityLogs')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(length: 64)]
    private string $action;

    /**
     * Тип сущности, с которой работали: document, book, photo, user и т.д.
     */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $entityId = null;

    /**
     * Дополнительные детали: URL, имя файла, прогресс чтения и т.п.
     *
     * @var array<string,mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sessionId = null;

    /**
     * Сколько секунд провёл на странице/в действии (если применимо).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(?User $user, string $action)
    {
        $this->user = $user;
        $this->action = $action;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntity(?string $type, int|string|null $id): self
    {
        $this->entityType = $type;
        $this->entityId = $id !== null ? (string) $id : null;
        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string,mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 1000) : null;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $seconds): self
    {
        $this->durationSeconds = $seconds;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
