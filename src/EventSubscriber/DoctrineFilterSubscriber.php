<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Включает SoftDeleteFilter для всех, кроме админов.
 * Админ видит и удалённое (и нужно — чтобы /admin/trash работал).
 */
final class DoctrineFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Приоритет высокий, чтобы фильтр включился до контроллеров
        return [KernelEvents::REQUEST => ['onRequest', 100]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        $isAdmin = $user instanceof User && $user->isAdmin();

        $filters = $this->em->getFilters();
        if (!$isAdmin) {
            if (!$filters->isEnabled('soft_delete')) {
                $filters->enable('soft_delete');
            }
        } else {
            if ($filters->isEnabled('soft_delete')) {
                $filters->disable('soft_delete');
            }
        }
    }
}
