<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity', name: 'admin_activity_')]
#[IsGranted(User::ROLE_ADMIN)]
final class ActivityController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ActivityLogRepository $logs,
        UserRepository $users,
    ): Response {
        $filters = [];

        if ($userId = $request->query->get('user')) {
            $filters['user'] = $users->find($userId);
        }
        if ($action = $request->query->get('action')) {
            $filters['action'] = $action;
        }
        if ($from = $request->query->get('from')) {
            $filters['from'] = new \DateTimeImmutable($from);
        }
        if ($to = $request->query->get('to')) {
            $filters['to'] = new \DateTimeImmutable($to);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 50;

        $items = $logs->search($filters, limit: $perPage, offset: ($page - 1) * $perPage);

        return $this->render('admin/dashboard/activity.html.twig', [
            'items' => $items,
            'users' => $users->findAllOrderedByCreatedAt(),
            'filters' => $filters,
            'page' => $page,
        ]);
    }

    #[Route('/user/{id}', name: 'user_profile', methods: ['GET'])]
    public function userProfile(User $user, ActivityLogRepository $logs): Response
    {
        $items = $logs->search(['user' => $user], limit: 200);
        $totalSeconds = $logs->getTotalTimeSeconds($user);

        return $this->render('admin/dashboard/user_profile.html.twig', [
            'subject' => $user,
            'items' => $items,
            'total_seconds' => $totalSeconds,
        ]);
    }
}
