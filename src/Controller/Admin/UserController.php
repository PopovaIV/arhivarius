<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Form\UserCreateType;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted(User::ROLE_ADMIN)]
final class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $users->findAllOrderedByCreatedAt(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserPasswordHasherInterface $hasher,
        UserRepository $users,
        ActivityLogger $logger,
    ): Response {
        $user = new User();
        $form = $this->createForm(UserCreateType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $role = (string) $form->get('role')->getData();

            $user->setPassword($hasher->hashPassword($user, $plainPassword));
            $user->setRoles([$role]);

            /** @var User $admin */
            $admin = $this->getUser();
            $user->setCreatedBy($admin);

            $users->save($user);

            $logger->log(
                ActivityLog::ACTION_USER_CREATE,
                entityType: 'user',
                entityId: $user->getId(),
                metadata: ['username' => $user->getUsername(), 'role' => $role],
            );

            $this->addFlash('success', 'Пользователь создан: ' . $user->getUsername());

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'toggle_active', methods: ['POST'])]
    public function toggleActive(
        User $user,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        Request $request,
    ): Response {
        if (!$this->isCsrfTokenValid('toggle-' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Не позволяем админу заблокировать самого себя
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Нельзя деактивировать самого себя');
            return $this->redirectToRoute('admin_users_index');
        }

        $user->setActive(!$user->isActive());
        $em->flush();

        if (!$user->isActive()) {
            $logger->log(
                ActivityLog::ACTION_USER_DEACTIVATE,
                entityType: 'user',
                entityId: $user->getId(),
            );
        }

        $this->addFlash('success', $user->isActive() ? 'Пользователь активирован' : 'Пользователь деактивирован');
        return $this->redirectToRoute('admin_users_index');
    }
}
