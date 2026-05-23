<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Document;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Правила доступа к документам:
 *   VIEW          — любой авторизованный
 *   EDIT          — автор или админ
 *   DELETE        — автор или админ (soft delete)
 *   RESTORE       — только админ (из корзины)
 *   PERMA_DELETE  — только админ (окончательное удаление из корзины)
 */
final class DocumentVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const RESTORE = 'restore';
    public const PERMA_DELETE = 'perma_delete';

    private const SUPPORTED = [
        self::VIEW, self::EDIT, self::DELETE, self::RESTORE, self::PERMA_DELETE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED, true) && $subject instanceof Document;
    }

    /**
     * @param Document $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        $isAdmin = $user->isAdmin();
        $isOwner = $subject->getCreatedBy()->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW => true, // авторизованные видят всё (фильтр на soft-delete уже отрезал лишнее)
            self::EDIT, self::DELETE => $isAdmin || $isOwner,
            self::RESTORE, self::PERMA_DELETE => $isAdmin,
            default => false,
        };
    }
}
