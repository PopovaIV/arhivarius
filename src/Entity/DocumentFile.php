<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocumentFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentFileRepository::class)]
#[ORM\Table(name: 'document_files')]
class DocumentFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    /**
     * Имя файла, как загрузил пользователь.
     */
    #[ORM\Column(length: 255)]
    private string $originalName;

    /**
     * Путь относительно uploads_dir, например documents/2026/05/uuid_filename.pdf
     */
    #[ORM\Column(length: 512)]
    private string $storedPath;

    #[ORM\Column(length: 128)]
    private string $mimeType;

    #[ORM\Column(type: 'bigint')]
    private string $sizeBytes;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $uploadedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    public function __construct(
        Document $document,
        string $originalName,
        string $storedPath,
        string $mimeType,
        int $sizeBytes,
        User $uploadedBy,
    ) {
        $this->document = $document;
        $this->originalName = $originalName;
        $this->storedPath = $storedPath;
        $this->mimeType = $mimeType;
        $this->sizeBytes = (string) $sizeBytes;
        $this->uploadedBy = $uploadedBy;
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): self
    {
        $this->document = $document;
        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getStoredPath(): string
    {
        return $this->storedPath;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSizeBytes(): int
    {
        return (int) $this->sizeBytes;
    }

    public function getUploadedBy(): User
    {
        return $this->uploadedBy;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }
}
