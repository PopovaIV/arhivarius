<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\SoftDeletableInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Прячет soft-deleted записи у всех, кроме админа.
 * Включается/выключается в DoctrineFilterSubscriber по роли текущего пользователя.
 */
final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->reflClass?->implementsInterface(SoftDeletableInterface::class)) {
            return '';
        }
        return $targetTableAlias . '.deleted_at IS NULL';
    }
}
