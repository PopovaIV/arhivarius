<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Маркер для сущностей, поддерживающих «мягкое» удаление.
 * SoftDeleteFilter автоматически прячет такие записи у не-админов.
 */
interface SoftDeletableInterface
{
    public function getDeletedAt(): ?\DateTimeImmutable;

    public function setDeletedAt(?\DateTimeImmutable $at): self;

    public function getDeletedBy(): ?User;

    public function setDeletedBy(?User $user): self;

    public function isDeleted(): bool;
}
