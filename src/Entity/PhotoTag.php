<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PhotoTagRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Тег человека на фотографии.
 * Координаты — доли (0–1) от размеров изображения, не пиксели.
 * Так рамка останется правильной при любом отображаемом размере фото.
 */
#[ORM\Entity(repositoryClass: PhotoTagRepository::class)]
#[ORM\Table(name: 'photo_tags')]
#[ORM\Index(name: 'idx_pt_photo', columns: ['photo_id'])]
#[ORM\Index(name: 'idx_pt_person', columns: ['person_id'])]
class PhotoTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Photo::class, inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Photo $photo;

    #[ORM\ManyToOne(targetEntity: Person::class, inversedBy: 'photoTags')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Person $person;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 5)]
    #[Assert\Range(min: 0, max: 1)]
    private string $x;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 5)]
    #[Assert\Range(min: 0, max: 1)]
    private string $y;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 5)]
    #[Assert\Range(min: 0, max: 1)]
    private string $width;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 5)]
    #[Assert\Range(min: 0, max: 1)]
    private string $height;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Photo $photo,
        Person $person,
        float $x,
        float $y,
        float $width,
        float $height,
        User $createdBy,
    ) {
        $this->photo = $photo;
        $this->person = $person;
        $this->x = (string) max(0, min(1, $x));
        $this->y = (string) max(0, min(1, $y));
        $this->width = (string) max(0.01, min(1, $width));
        $this->height = (string) max(0.01, min(1, $height));
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getPhoto(): Photo { return $this->photo; }
    public function getPerson(): Person { return $this->person; }
    public function getX(): float { return (float) $this->x; }
    public function getY(): float { return (float) $this->y; }
    public function getWidth(): float { return (float) $this->width; }
    public function getHeight(): float { return (float) $this->height; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
