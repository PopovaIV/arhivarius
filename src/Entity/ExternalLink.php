<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LinkCategory;
use App\Repository\ExternalLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExternalLinkRepository::class)]
#[ORM\Table(name: 'external_links')]
#[ORM\Index(name: 'idx_link_category', columns: ['category'])]
#[ORM\Index(name: 'idx_link_deleted', columns: ['deleted_at'])]
#[ORM\HasLifecycleCallbacks]
class ExternalLink implements SoftDeletableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(length: 32, enumType: LinkCategory::class)]
    private LinkCategory $category;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $title;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Url]
    private string $url;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Свободные теги через запятую — для группировки по тематике.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tags = null;

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

    public function __construct(LinkCategory $category, string $title, string $url, User $createdBy)
    {
        $this->category = $category;
        $this->title = $title;
        $this->url = $url;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): ?string { return $this->id; }
    public function getCategory(): LinkCategory { return $this->category; }
    public function setCategory(LinkCategory $c): self { $this->category = $c; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): self { $this->title = $t; return $this; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $u): self { $this->url = $u; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }
    public function getTags(): ?string { return $this->tags; }
    public function setTags(?string $t): self { $this->tags = $t; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $at): self { $this->deletedAt = $at; return $this; }
    public function getDeletedBy(): ?User { return $this->deletedBy; }
    public function setDeletedBy(?User $u): self { $this->deletedBy = $u; return $this; }
    public function isDeleted(): bool { return $this->deletedAt !== null; }

    public function getHostname(): string
    {
        $parsed = parse_url($this->url);
        return $parsed['host'] ?? '';
    }
}
