<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Person;
use App\Entity\Relation;
use App\Entity\User;
use App\Enum\RelationType;
use App\Repository\PersonRepository;
use App\Repository\RelationRepository;
use App\Service\ActivityLogger;
use App\Service\Gedcom\GedcomExporter;
use App\Service\Gedcom\GedcomImporter;
use App\Service\TreeBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/genealogy', name: 'genealogy_')]
#[IsGranted('ROLE_USER')]
final class GenealogyController extends AbstractController
{
    public function __construct(
        private readonly PersonRepository $persons,
        private readonly RelationRepository $relations,
        private readonly EntityManagerInterface $em,
        private readonly TreeBuilder $treeBuilder,
        private readonly GedcomExporter $gedcomExporter,
        private readonly GedcomImporter $gedcomImporter,
        private readonly ActivityLogger $logger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('genealogy/index.html.twig', [
            'persons_total' => $this->persons->countAll(),
            'relations_total' => $this->em->getRepository(Relation::class)->count([]),
        ]);
    }

    /**
     * JSON-эндпоинт с полным графом для рендера древа.
     */
    #[Route('/tree.json', name: 'tree_json', methods: ['GET'])]
    public function treeJson(Request $request): JsonResponse
    {
        $centerId = $request->query->get('person');
        if ($centerId !== null) {
            $center = $this->persons->find((int) $centerId);
            if ($center === null) {
                throw $this->createNotFoundException();
            }
            $depth = max(1, min(8, (int) $request->query->get('depth', 3)));
            return new JsonResponse($this->treeBuilder->buildAround($center, $depth));
        }
        return new JsonResponse($this->treeBuilder->buildFullTree());
    }

    // === Управление связями (вызывается с карточки Person) ===

    /**
     * Создать связь. Тело: { type: 'parent'|'spouse', other_person_id?: int, new_person_name?: string,
     *                        direction: 'incoming'|'outgoing', start_date?, end_date?, notes? }
     *
     * direction:
     *   - incoming: other → current (other родитель, current ребёнок ИЛИ other супруг)
     *   - outgoing: current → other (current родитель, other ребёнок ИЛИ current супруг)
     * Для spouse direction не имеет значения.
     */
    #[Route('/persons/{id}/relations', name: 'relation_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function createRelation(Person $current, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'invalid_json'], 400);
        }

        $type = RelationType::tryFrom($data['type'] ?? '');
        if ($type === null) {
            return new JsonResponse(['error' => 'invalid_type'], 400);
        }
        $direction = $data['direction'] ?? 'outgoing';

        // Достаём или создаём «другого» человека
        $other = null;
        if (!empty($data['other_person_id'])) {
            $other = $this->persons->find((int) $data['other_person_id']);
        } elseif (!empty($data['new_person_name'])) {
            $name = trim((string) $data['new_person_name']);
            if ($name === '') {
                return new JsonResponse(['error' => 'empty_name'], 400);
            }
            $other = new Person($name, $user);
            $this->persons->save($other);
        }
        if ($other === null) {
            return new JsonResponse(['error' => 'no_other_person'], 400);
        }
        if ($other->getId() === $current->getId()) {
            return new JsonResponse(['error' => 'self_link'], 400);
        }

        // Определяем направление
        if ($type === RelationType::Parent) {
            // incoming = other родитель current; outgoing = current родитель other
            $from = $direction === 'incoming' ? $other : $current;
            $to   = $direction === 'incoming' ? $current : $other;
        } else {
            // spouse — направление произвольное
            $from = $current;
            $to = $other;
        }

        if ($this->relations->existsBetween($from, $to, $type)) {
            return new JsonResponse(['error' => 'already_exists'], 409);
        }

        $relation = new Relation($from, $to, $type, $user);
        if (!empty($data['start_date'])) {
            $relation->setStartDate((string) $data['start_date']);
        }
        if (!empty($data['end_date'])) {
            $relation->setEndDate((string) $data['end_date']);
        }
        if (!empty($data['notes'])) {
            $relation->setNotes((string) $data['notes']);
        }
        $this->relations->save($relation);

        $this->logger->log(
            'relation_create',
            entityType: 'person',
            entityId: $current->getId(),
            metadata: [
                'type' => $type->value,
                'other_person_id' => $other->getId(),
                'other_person_name' => $other->getFullName(),
            ],
        );

        return new JsonResponse([
            'id' => $relation->getId(),
            'other_person' => [
                'id' => $other->getId(),
                'name' => $other->getFullName(),
                'lifespan' => $other->getLifespanLabel(),
            ],
        ], 201);
    }

    #[Route('/relations/{id}/delete', name: 'relation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function deleteRelation(int $id, Request $request): Response
    {
        $relation = $this->em->getRepository(Relation::class)->find($id);
        if ($relation === null) {
            throw $this->createNotFoundException();
        }
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isAdmin() && $relation->getCreatedBy()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('delete-rel-' . $relation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $personId = $request->request->get('return_to');
        $this->em->remove($relation);
        $this->em->flush();

        $this->addFlash('success', 'Связь удалена');
        if ($personId !== null) {
            return $this->redirectToRoute('persons_show', ['id' => $personId]);
        }
        return $this->redirectToRoute('genealogy_index');
    }

    // === GEDCOM ===

    #[Route('/export.ged', name: 'gedcom_export', methods: ['GET'])]
    public function gedcomExport(): Response
    {
        $content = $this->gedcomExporter->export();
        $this->logger->log('gedcom_export', metadata: ['size' => strlen($content)]);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/x-gedcom; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'genarchive_' . date('Y-m-d') . '.ged',
            ),
        );
        return $response;
    }

    #[Route('/import', name: 'gedcom_import', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function gedcomImport(Request $request): Response
    {
        $stats = null;
        if ($request->isMethod('POST')) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $file */
            $file = $request->files->get('gedcom_file');
            if (!$this->isCsrfTokenValid('gedcom-import', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            if ($file === null) {
                $this->addFlash('error', 'Файл не загружен');
            } else {
                $size = $file->getSize();
                if ($size !== null && $size > 50 * 1024 * 1024) {
                    $this->addFlash('error', 'Файл слишком большой (макс. 50 МБ)');
                } else {
                    $content = file_get_contents($file->getPathname());
                    if ($content === false) {
                        $this->addFlash('error', 'Не удалось прочитать файл');
                    } else {
                        /** @var User $user */
                        $user = $this->getUser();
                        $stats = $this->gedcomImporter->import($content, $user);
                        $this->logger->log(
                            'gedcom_import',
                            metadata: [
                                'persons_created' => $stats['persons_created'],
                                'relations_created' => $stats['relations_created'],
                            ],
                        );
                        $this->addFlash('success', "Импорт завершён: добавлено {$stats['persons_created']} людей и {$stats['relations_created']} связей");
                    }
                }
            }
        }

        return $this->render('genealogy/import.html.twig', ['stats' => $stats]);
    }
}
