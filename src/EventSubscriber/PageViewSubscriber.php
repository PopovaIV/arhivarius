<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Service\ActivityLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Логирует переходы по страницам аутентифицированных пользователей.
 * Не пишет: профайлер, ассеты, AJAX-пинги.
 */
final class PageViewSubscriber implements EventSubscriberInterface
{
    private const SKIP_PREFIXES = ['/_wdt', '/_profiler', '/css', '/js', '/images', '/favicon', '/build'];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 0]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ($request->getMethod() !== 'GET' || $request->isXmlHttpRequest()) {
            return;
        }
        $path = $request->getPathInfo();
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->logger->log(
            ActivityLog::ACTION_PAGE_VIEW,
            metadata: [
                'path' => $path,
                'route' => $request->attributes->get('_route'),
            ],
        );
    }
}
