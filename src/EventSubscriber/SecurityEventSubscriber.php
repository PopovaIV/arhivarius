<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->logger->log(ActivityLog::ACTION_LOGIN, userOverride: $user);
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $this->logger->log(
            ActivityLog::ACTION_LOGIN_FAILED,
            metadata: [
                'attempted_username' => $request->request->get('_username', ''),
                'reason' => $event->getException()->getMessage(),
            ],
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token === null) {
            return;
        }
        $user = $token->getUser();
        if ($user instanceof User) {
            $this->logger->log(ActivityLog::ACTION_LOGOUT, userOverride: $user);
        }
    }
}
