<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Центральная точка записи действий пользователя.
 * Виден журнал только админу (см. AdminActivityController).
 */
final readonly class ActivityLogger
{
    public function __construct(
        private ActivityLogRepository $logs,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string,mixed>|null $metadata
     */
    public function log(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?array $metadata = null,
        ?User $userOverride = null,
        ?int $durationSeconds = null,
    ): void {
        /** @var User|null $user */
        $user = $userOverride ?? $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $log = new ActivityLog($user, $action);
        $log->setEntity($entityType, $entityId);
        $log->setMetadata($metadata);
        $log->setDurationSeconds($durationSeconds);

        if ($request !== null) {
            $log->setIp($request->getClientIp());
            $log->setUserAgent($request->headers->get('User-Agent'));
            if ($request->hasSession()) {
                $log->setSessionId($request->getSession()->getId());
            }
        }

        $this->logs->save($log);
    }
}
