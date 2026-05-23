<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Gender;
use App\Repository\PersonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Реестр людей (предков). Один Person может быть отмечен на многих фото,
 * упомянут во многих документах, связан родственными отношениями (Фаза 6).
 */
#[ORM\Entity(repositoryClass: PersonRepository::class)]
#[ORM\Table(name: 'persons')]
#[ORM\Index(name: 'idx_person_name', columns: ['full_name'])]
#[ORM\Index(name: 'idx_person_deleted', columns: ['deleted_at'])]
#[ORM\HasLifecycleCallbacks]
class Person implements SoftDeletableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $fullName;

    /**
     * Девичья фамилия и/или прозвища — поиск идёт и по этому полю.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $aliases = null;

    #[ORM\Column(length: 32, enumType: Gender::class)]
    private Gender $gender = Gender::Unknown;

    /**
     * Свободный формат даты: «12 апреля 1850», «около 1810», «1850-е».
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $birthDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $birthYear = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $birthPlace = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $deathDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $deathYear = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deathPlace = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * Ссылка на профиль человека во внешнем сервисе (Familio, MyHeritage, FamilySearch и т.п.).
     * На карточке Person рендерится отдельной кнопкой.
     */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $externalTreeUrl = null;

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
    #[ORM\OneToMany(targetEntity: PhotoTag::class, mappedBy: 'person', cascade: ['remove'])]
    private Collection $photoTags;

    public function __construct(string $fullName, User $createdBy)
    {
        $this->fullName = $fullName;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->photoTags = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $n): self { $this->fullName = $n; return $this; }
    public function getAliases(): ?string { return $this->aliases; }
    public function setAliases(?string $a): self { $this->aliases = $a; return $this; }
    public function getGender(): Gender { return $this->gender; }
    public function setGender(Gender $g): self { $this->gender = $g; return $this; }
    public function getBirthDate(): ?string { return $this->birthDate; }
    public function setBirthDate(?string $d): self { $this->birthDate = $d; return $this; }
    public function getBirthYear(): ?int { return $this->birthYear; }
    public function setBirthYear(?int $y): self { $this->birthYear = $y; return $this; }
    public function getBirthPlace(): ?string { return $this->birthPlace; }
    public function setBirthPlace(?string $p): self { $this->birthPlace = $p; return $this; }
    public function getDeathDate(): ?string { return $this->deathDate; }
    public function setDeathDate(?string $d): self { $this->deathDate = $d; return $this; }
    public function getDeathYear(): ?int { return $this->deathYear; }
    public function setDeathYear(?int $y): self { $this->deathYear = $y; return $this; }
    public function getDeathPlace(): ?string { return $this->deathPlace; }
    public function setDeathPlace(?string $p): self { $this->deathPlace = $p; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
    public function getExternalTreeUrl(): ?string { return $this->externalTreeUrl; }
    public function setExternalTreeUrl(?string $u): self { $this->externalTreeUrl = $u; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $at): self { $this->deletedAt = $at; return $this; }
    public function getDeletedBy(): ?User { return $this->deletedBy; }
    public function setDeletedBy(?User $u): self { $this->deletedBy = $u; return $this; }
    public function isDeleted(): bool { return $this->deletedAt !== null; }
    public function getPhotoTags(): Collection { return $this->photoTags; }

    public function getLifespanLabel(): string
    {
        $b = $this->birthYear ?? ($this->birthDate ?? '?');
        $d = $this->deathYear ?? ($this->deathDate ?? '');
        if ($d === '' || $d === '?') {
            return (string) $b;
        }
        return $b . ' — ' . $d;
    }
}
