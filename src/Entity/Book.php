<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\Table(name: 'books')]
#[ORM\Index(name: 'idx_book_deleted', columns: ['deleted_at'])]
#[ORM\Index(name: 'idx_book_year', columns: ['year'])]
#[ORM\Index(name: 'idx_book_language', columns: ['language'])]
#[ORM\HasLifecycleCallbacks]
class Book implements SoftDeletableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authors = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1400, max: 2100)]
    private ?int $year = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $isbn = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Путь к JPEG-обложке относительно uploads_dir. Генерируется автоматически из первой страницы PDF.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverPath = null;

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
     * @var Collection<int, BookFile>
     */
    #[ORM\OneToMany(targetEntity: BookFile::class, mappedBy: 'book', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $files;

    public function __construct(User $createdBy)
    {
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->files = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): self { $this->title = $t; return $this; }
    public function getAuthors(): ?string { return $this->authors; }
    public function setAuthors(?string $a): self { $this->authors = $a; return $this; }
    public function getYear(): ?int { return $this->year; }
    public function setYear(?int $y): self { $this->year = $y; return $this; }
    public function getPublisher(): ?string { return $this->publisher; }
    public function setPublisher(?string $p): self { $this->publisher = $p; return $this; }
    public function getLanguage(): ?string { return $this->language; }
    public function setLanguage(?string $l): self { $this->language = $l; return $this; }
    public function getGenre(): ?string { return $this->genre; }
    public function setGenre(?string $g): self { $this->genre = $g; return $this; }
    public function getIsbn(): ?string { return $this->isbn; }
    public function setIsbn(?string $i): self { $this->isbn = $i; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }
    public function getCoverPath(): ?string { return $this->coverPath; }
    public function setCoverPath(?string $p): self { $this->coverPath = $p; return $this; }
    public function hasCover(): bool { return $this->coverPath !== null; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $at): self { $this->deletedAt = $at; return $this; }
    public function getDeletedBy(): ?User { return $this->deletedBy; }
    public function setDeletedBy(?User $u): self { $this->deletedBy = $u; return $this; }
    public function isDeleted(): bool { return $this->deletedAt !== null; }

    /**
     * @return Collection<int, BookFile>
     */
    public function getFiles(): Collection { return $this->files; }

    public function addFile(BookFile $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setBook($this);
        }
        return $this;
    }

    public function removeFile(BookFile $file): self
    {
        $this->files->removeElement($file);
        return $this;
    }

    /**
     * Файл подходящего формата для чтения в браузере (если есть).
     */
    public function findReadableFile(): ?BookFile
    {
        // Сначала PDF — самый универсальный
        foreach ($this->files as $f) {
            if ($f->getFormat()->value === 'pdf') {
                return $f;
            }
        }
        // Потом EPUB
        foreach ($this->files as $f) {
            if ($f->getFormat()->value === 'epub') {
                return $f;
            }
        }
        return null;
    }
}
