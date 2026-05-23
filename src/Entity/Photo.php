<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PhotoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\Table(name: 'photos')]
#[ORM\Index(name: 'idx_photo_deleted', columns: ['deleted_at'])]
#[ORM\Index(name: 'idx_photo_year', columns: ['taken_year'])]
#[ORM\HasLifecycleCallbacks]
class Photo implements SoftDeletableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $takenDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $takenYear = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $place = null;

    /**
     * Путь к файлу относительно uploads_dir.
     */
    #[ORM\Column(length: 512)]
    private string $imagePath;

    #[ORM\Column(length: 128)]
    private string $mimeType;

    #[ORM\Column(type: 'bigint')]
    private string $sizeBytes;

    /**
     * Размеры оригинала в пикселях — нужны для точного позиционирования тегов.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $imageWidth = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $imageHeight = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $deletedBy = null;

    /**
     * @var Collection<int, PhotoTag>
     */
    #[ORM\OneToMany(targetEntity: PhotoTag::class, mappedBy: 'photo', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $tags;

    public function __construct(string $title, string $imagePath, string $mimeType, int $sizeBytes, User $createdBy)
    {
        $this->title = $title;
        $this->imagePath = $imagePath;
        $this->mimeType = $mimeType;
        $this->sizeBytes = (string) $sizeBytes;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->tags = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): self { $this->title = $t; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }
    public function getTakenDate(): ?string { return $this->takenDate; }
    public function setTakenDate(?string $d): self { $this->takenDate = $d; return $this; }
    public function getTakenYear(): ?int { return $this->takenYear; }
    public function setTakenYear(?int $y): self { $this->takenYear = $y; return $this; }
    public function getPlace(): ?string { return $this->place; }
    public function setPlace(?string $p): self { $this->place = $p; return $this; }
    public function getImagePath(): string { return $this->imagePath; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSizeBytes(): int { return (int) $this->sizeBytes; }
    public function getImageWidth(): ?int { return $this->imageWidth; }
    public function setImageWidth(?int $w): self { $this->imageWidth = $w; return $this; }
    public function getImageHeight(): ?int { return $this->imageHeight; }
    public function setImageHeight(?int $h): self { $this->imageHeight = $h; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $at): self { $this->deletedAt = $at; return $this; }
    public function getDeletedBy(): ?User { return $this->deletedBy; }
    public function setDeletedBy(?User $u): self { $this->deletedBy = $u; return $this; }
    public function isDeleted(): bool { return $this->deletedAt !== null; }
    public function getTags(): Collection { return $this->tags; }
}
