<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BookAnnotationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Закладки и заметки в книгах — одна таблица, разные типы.
 * - bookmark: краткая метка позиции (label)
 * - note: текстовая заметка с произвольным содержанием (text)
 * Позиция: pdf_page для PDF, epub_cfi для EPUB.
 */
#[ORM\Entity(repositoryClass: BookAnnotationRepository::class)]
#[ORM\Table(name: 'book_annotations')]
#[ORM\Index(name: 'idx_ann_user_book', columns: ['user_id', 'book_id'])]
class BookAnnotation
{
    public const TYPE_BOOKMARK = 'bookmark';
    public const TYPE_NOTE = 'note';

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

    #[ORM\Column(length: 16)]
    private string $type;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pdfPage = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $epubCfi = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, Book $book, string $type, string $content)
    {
        $this->user = $user;
        $this->book = $book;
        $this->type = $type;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getBook(): Book { return $this->book; }
    public function getType(): string { return $this->type; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $c): self { $this->content = $c; return $this; }
    public function getPdfPage(): ?int { return $this->pdfPage; }
    public function setPdfPage(?int $p): self { $this->pdfPage = $p; return $this; }
    public function getEpubCfi(): ?string { return $this->epubCfi; }
    public function setEpubCfi(?string $c): self { $this->epubCfi = $c; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isBookmark(): bool { return $this->type === self::TYPE_BOOKMARK; }
    public function isNote(): bool { return $this->type === self::TYPE_NOTE; }
}
