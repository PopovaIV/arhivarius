<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Photo;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PhotoVoter extends Voter
{
    public const VIEW = 'photo_view';
    public const EDIT = 'photo_edit';
    public const DELETE = 'photo_delete';
    public const TAG = 'photo_tag';
    public const RESTORE = 'photo_restore';
    public const PERMA_DELETE = 'photo_perma_delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::TAG, self::RESTORE, self::PERMA_DELETE], true)
            && $subject instanceof Photo;
    }

    /** @param Photo $subject */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }
        $isAdmin = $user->isAdmin();
        $isOwner = $subject->getCreatedBy()->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW, self::TAG => true, // тегировать может любой авторизованный
            self::EDIT, self::DELETE => $isAdmin || $isOwner,
            self::RESTORE, self::PERMA_DELETE => $isAdmin,
            default => false,
        };
    }
}
