<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BookFormat;
use App\Repository\BookFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookFileRepository::class)]
#[ORM\Table(name: 'book_files')]
class BookFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Book $book;

    #[ORM\Column(length: 16, enumType: BookFormat::class)]
    private BookFormat $format;

    #[ORM\Column(length: 255)]
    private string $originalName;

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

    /**
     * Извлечённый текст для полнотекстового поиска. Хранится отдельным TEXT-полем,
     * tsvector для GIN-индекса обновляется тем же UPDATE-запросом в репозитории.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $extractedText = null;

    /**
     * Статус извлечения текста: pending / processed / failed / skipped.
     */
    #[ORM\Column(length: 16)]
    private string $textStatus = 'pending';

    public function __construct(
        Book $book,
        BookFormat $format,
        string $originalName,
        string $storedPath,
        string $mimeType,
        int $sizeBytes,
        User $uploadedBy,
    ) {
        $this->book = $book;
        $this->format = $format;
        $this->originalName = $originalName;
        $this->storedPath = $storedPath;
        $this->mimeType = $mimeType;
        $this->sizeBytes = (string) $sizeBytes;
        $this->uploadedBy = $uploadedBy;
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getBook(): Book { return $this->book; }
    public function setBook(Book $b): self { $this->book = $b; return $this; }
    public function getFormat(): BookFormat { return $this->format; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getStoredPath(): string { return $this->storedPath; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSizeBytes(): int { return (int) $this->sizeBytes; }
    public function getUploadedBy(): User { return $this->uploadedBy; }
    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
    public function getExtractedText(): ?string { return $this->extractedText; }
    public function setExtractedText(?string $t): self { $this->extractedText = $t; return $this; }
    public function getTextStatus(): string { return $this->textStatus; }
    public function setTextStatus(string $s): self { $this->textStatus = $s; return $this; }
}
