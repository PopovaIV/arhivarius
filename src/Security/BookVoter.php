<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Book;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class BookVoter extends Voter
{
    public const VIEW = 'book_view';
    public const READ = 'book_read';
    public const EDIT = 'book_edit';
    public const DELETE = 'book_delete';
    public const RESTORE = 'book_restore';
    public const PERMA_DELETE = 'book_perma_delete';

    private const SUPPORTED = [
        self::VIEW, self::READ, self::EDIT, self::DELETE, self::RESTORE, self::PERMA_DELETE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED, true) && $subject instanceof Book;
    }

    /** @param Book $subject */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        $isAdmin = $user->isAdmin();
        $isOwner = $subject->getCreatedBy()->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW, self::READ => true,
            self::EDIT, self::DELETE => $isAdmin || $isOwner,
            self::RESTORE, self::PERMA_DELETE => $isAdmin,
            default => false,
        };
    }
}
