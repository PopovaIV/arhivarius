<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ExternalLink;
use App\Entity\User;
use App\Enum\LinkCategory;
use App\Form\ExternalLinkType;
use App\Repository\ExternalLinkRepository;
use App\Security\ExternalLinkVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/links', name: 'links_')]
#[IsGranted('ROLE_USER')]
final class ExternalLinkController extends AbstractController
{
    public function __construct(
        private readonly ExternalLinkRepository $links,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q = $request->query->get('q');
        return $this->render('links/index.html.twig', [
            'groups' => $this->links->groupedByCategory($q),
            'q' => $q,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $link = new ExternalLink(LinkCategory::Other, '', 'https://', $user);

        $form = $this->createForm(ExternalLinkType::class, $link);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->links->save($link);
            $this->addFlash('success', 'Ссылка добавлена');
            return $this->redirectToRoute('links_index');
        }

        return $this->render('links/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(ExternalLink $link, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ExternalLinkVoter::EDIT, $link);

        $form = $this->createForm(ExternalLinkType::class, $link);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Изменения сохранены');
            return $this->redirectToRoute('links_index');
        }

        return $this->render('links/edit.html.twig', ['link' => $link, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(ExternalLink $link, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ExternalLinkVoter::DELETE, $link);
        if (!$this->isCsrfTokenValid('delete-link-' . $link->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        /** @var User $user */
        $user = $this->getUser();
        $link->setDeletedAt(new \DateTimeImmutable());
        $link->setDeletedBy($user);
        $this->em->flush();

        $this->addFlash('success', 'Ссылка перемещена в корзину');
        return $this->redirectToRoute('links_index');
    }
}
