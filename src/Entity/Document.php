<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DatePrecision;
use App\Enum\DocumentCategory;
use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\Index(name: 'idx_doc_category', columns: ['category'])]
#[ORM\Index(name: 'idx_doc_deleted', columns: ['deleted_at'])]
#[ORM\Index(name: 'idx_doc_created_by', columns: ['created_by_id'])]
#[ORM\HasLifecycleCallbacks]
class Document implements SoftDeletableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(length: 32, enumType: DocumentCategory::class)]
    private DocumentCategory $category;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Текстовое представление даты. Архивные документы часто датируются
     * нечётко («1840-е», «около 1810», «1850—1855»), потому строкой.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $documentDate = null;

    /**
     * Числовой год для сортировки и фильтрации.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1500, max: 2100)]
    private ?int $documentYear = null;

    #[ORM\Column(length: 16, enumType: DatePrecision::class)]
    private DatePrecision $datePrecision = DatePrecision::Year;

    /**
     * Место/локация (приход, уезд, губерния, страна — свободный текст).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $place = null;

    /**
     * Архивный шифр: ф. 19, оп. 124, д. 567, л. 12 об.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $archiveReference = null;

    /**
     * Категория-специфичные поля. Структура зависит от category.
     * Например для метрик: {record_type: 'birth', person_name: '...', parents: '...'}
     *
     * @var array<string,mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'deleted_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $deletedBy = null;

    /**
     * @var Collection<int, DocumentFile>
     */
    #[ORM\OneToMany(targetEntity: DocumentFile::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $files;

    public function __construct(DocumentCategory $category, User $createdBy)
    {
        $this->category = $category;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->files = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCategory(): DocumentCategory
    {
        return $this->category;
    }

    public function setCategory(DocumentCategory $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDocumentDate(): ?string
    {
        return $this->documentDate;
    }

    public function setDocumentDate(?string $documentDate): self
    {
        $this->documentDate = $documentDate;
        return $this;
    }

    public function getDocumentYear(): ?int
    {
        return $this->documentYear;
    }

    public function setDocumentYear(?int $year): self
    {
        $this->documentYear = $year;
        return $this;
    }

    public function getDatePrecision(): DatePrecision
    {
        return $this->datePrecision;
    }

    public function setDatePrecision(DatePrecision $precision): self
    {
        $this->datePrecision = $precision;
        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setPlace(?string $place): self
    {
        $this->place = $place;
        return $this;
    }

    public function getArchiveReference(): ?string
    {
        return $this->archiveReference;
    }

    public function setArchiveReference(?string $ref): self
    {
        $this->archiveReference = $ref;
        return $this;
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

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $at): self
    {
        $this->deletedAt = $at;
        return $this;
    }

    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?User $user): self
    {
        $this->deletedBy = $user;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @return Collection<int, DocumentFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(DocumentFile $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setDocument($this);
        }
        return $this;
    }

    public function removeFile(DocumentFile $file): self
    {
        $this->files->removeElement($file);
        return $this;
    }
}
