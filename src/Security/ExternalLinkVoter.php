<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ExternalLink;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ExternalLinkVoter extends Voter
{
    public const EDIT = 'link_edit';
    public const DELETE = 'link_delete';
    public const RESTORE = 'link_restore';
    public const PERMA_DELETE = 'link_perma_delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::RESTORE, self::PERMA_DELETE], true)
            && $subject instanceof ExternalLink;
    }

    /** @param ExternalLink $subject */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }
        $isAdmin = $user->isAdmin();
        $isOwner = $subject->getCreatedBy()->getId() === $user->getId();

        return match ($attribute) {
            self::EDIT, self::DELETE => $isAdmin || $isOwner,
            self::RESTORE, self::PERMA_DELETE => $isAdmin,
            default => false,
        };
    }
}
