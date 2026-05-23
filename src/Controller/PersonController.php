<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Person;
use App\Entity\User;
use App\Form\PersonType;
use App\Repository\PersonRepository;
use App\Repository\PhotoRepository;
use App\Repository\RelationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/persons', name: 'persons_')]
#[IsGranted('ROLE_USER')]
final class PersonController extends AbstractController
{
    public function __construct(
        private readonly PersonRepository $persons,
        private readonly PhotoRepository $photos,
        private readonly RelationRepository $relations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q = $request->query->get('q');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 50;

        return $this->render('persons/index.html.twig', [
            'persons' => $this->persons->findAllOrdered($perPage, ($page - 1) * $perPage, $q),
            'total' => $this->persons->countAll($q),
            'q' => $q,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Автокомплит для диалога тегирования на фотографии.
     */
    #[Route('/autocomplete', name: 'autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        $items = $this->persons->autocomplete($q);

        return new JsonResponse([
            'items' => array_map(fn (Person $p) => [
                'id' => $p->getId(),
                'name' => $p->getFullName(),
                'lifespan' => $p->getLifespanLabel(),
            ], $items),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $person = new Person('', $user);

        $form = $this->createForm(PersonType::class, $person);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->persons->save($person);
            $this->addFlash('success', 'Запись о человеке создана');
            return $this->redirectToRoute('persons_show', ['id' => $person->getId()]);
        }

        return $this->render('persons/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Person $person): Response
    {
        $photos = $this->photos->findByPerson($person);

        // Все «несимметричные» связи Person — список объектов Relation для возможности их удалять
        $rawRelations = $this->em->createQueryBuilder()
            ->select('r')
            ->from(\App\Entity\Relation::class, 'r')
            ->where('r.fromPerson = :p OR r.toPerson = :p')
            ->setParameter('p', $person)
            ->getQuery()
            ->getResult();

        return $this->render('persons/show.html.twig', [
            'person' => $person,
            'photos' => $photos,
            'parents' => $this->relations->parentsOf($person),
            'children' => $this->relations->childrenOf($person),
            'spouses' => $this->relations->spousesOf($person),
            'all_relations' => $rawRelations,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function edit(Person $person, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isAdmin() && $person->getCreatedBy()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(PersonType::class, $person);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Изменения сохранены');
            return $this->redirectToRoute('persons_show', ['id' => $person->getId()]);
        }

        return $this->render('persons/edit.html.twig', ['person' => $person, 'form' => $form]);
    }
}
