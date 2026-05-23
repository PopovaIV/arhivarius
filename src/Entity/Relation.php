<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RelationType;
use App\Repository\RelationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Родственная связь между двумя людьми.
 *
 * Семантика по типу:
 *  - parent: from = родитель, to = ребёнок (ориентированная)
 *  - spouse: from ↔ to (симметричная; для удобства хранится одна запись,
 *    запросы учитывают оба направления)
 *
 * Уникальность (from, to, type) гарантирует отсутствие дублей.
 */
#[ORM\Entity(repositoryClass: RelationRepository::class)]
#[ORM\Table(name: 'relations')]
#[ORM\UniqueConstraint(name: 'uq_relation_triple', columns: ['from_person_id', 'to_person_id', 'type'])]
#[ORM\Index(name: 'idx_relation_from', columns: ['from_person_id'])]
#[ORM\Index(name: 'idx_relation_to', columns: ['to_person_id'])]
class Relation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(name: 'from_person_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Person $fromPerson;

    #[ORM\ManyToOne(targetEntity: Person::class)]
    #[ORM\JoinColumn(name: 'to_person_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Person $toPerson;

    #[ORM\Column(length: 16, enumType: RelationType::class)]
    private RelationType $type;

    /**
     * Дата начала отношения (для брака — дата венчания, для родительства часто не указывается).
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $startDate = null;

    /**
     * Дата окончания (развод, овдовение). Для родительства не используется.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $endDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Person $from, Person $to, RelationType $type, User $createdBy)
    {
        if ($from->getId() === $to->getId() && $from->getId() !== null) {
            throw new \InvalidArgumentException('Нельзя связать человека с самим собой');
        }
        $this->fromPerson = $from;
        $this->toPerson = $to;
        $this->type = $type;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getFromPerson(): Person { return $this->fromPerson; }
    public function getToPerson(): Person { return $this->toPerson; }
    public function getType(): RelationType { return $this->type; }
    public function getStartDate(): ?string { return $this->startDate; }
    public function setStartDate(?string $d): self { $this->startDate = $d; return $this; }
    public function getEndDate(): ?string { return $this->endDate; }
    public function setEndDate(?string $d): self { $this->endDate = $d; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes = $n; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
