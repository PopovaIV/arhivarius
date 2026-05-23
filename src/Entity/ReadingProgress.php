<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReadingProgressRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Прогресс чтения книги конкретным пользователем.
 * Уникальная пара (user, book). Хранит позицию (страница для PDF, CFI для EPUB)
 * и суммарное время чтения.
 */
#[ORM\Entity(repositoryClass: ReadingProgressRepository::class)]
#[ORM\Table(name: 'reading_progress')]
#[ORM\UniqueConstraint(name: 'uq_progress_user_book', columns: ['user_id', 'book_id'])]
class ReadingProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Book $book;

    /**
     * Текущая страница для PDF.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pdfPage = null;

    /**
     * Общее количество страниц (известно после первого открытия).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pdfTotalPages = null;

    /**
     * EPUB Canonical Fragment Identifier — позиция в книге.
     */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $epubCfi = null;

    /**
     * Процент прочитанного (0–100), считается на основе позиции.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $percent = null;

    #[ORM\Column(type: 'integer')]
    private int $totalSeconds = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $lastReadAt;

    public function __construct(User $user, Book $book)
    {
        $this->user = $user;
        $this->book = $book;
        $this->lastReadAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getBook(): Book { return $this->book; }
    public function getPdfPage(): ?int { return $this->pdfPage; }
    public function setPdfPage(?int $p): self { $this->pdfPage = $p; return $this; }
    public function getPdfTotalPages(): ?int { return $this->pdfTotalPages; }
    public function setPdfTotalPages(?int $p): self { $this->pdfTotalPages = $p; return $this; }
    public function getEpubCfi(): ?string { return $this->epubCfi; }
    public function setEpubCfi(?string $c): self { $this->epubCfi = $c; return $this; }
    public function getPercent(): ?int { return $this->percent; }
    public function setPercent(?int $p): self { $this->percent = $p; return $this; }
    public function getTotalSeconds(): int { return $this->totalSeconds; }
    public function addSeconds(int $s): self { $this->totalSeconds += max(0, $s); return $this; }
    public function getLastReadAt(): \DateTimeImmutable { return $this->lastReadAt; }
    public function touch(): self { $this->lastReadAt = new \DateTimeImmutable(); return $this; }
}
