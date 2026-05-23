<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Person;
use App\Entity\Photo;
use App\Entity\PhotoTag;
use App\Entity\User;
use App\Form\PhotoType;
use App\Repository\PersonRepository;
use App\Repository\PhotoRepository;
use App\Repository\PhotoTagRepository;
use App\Security\PhotoVoter;
use App\Service\ActivityLogger;
use App\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/photos', name: 'photos_')]
#[IsGranted('ROLE_USER')]
final class PhotoController extends AbstractController
{
    public function __construct(
        private readonly PhotoRepository $photos,
        private readonly PersonRepository $persons,
        private readonly PhotoTagRepository $photoTags,
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogger $logger,
        private readonly FileStorage $storage,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 30;
        return $this->render('photos/index.html.twig', [
            'photos' => $this->photos->findAllOrdered($perPage, ($page - 1) * $perPage),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $this->photos->countAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createFormBuilder()
            ->add('imageFile', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Файл фотографии',
                'required' => true,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\File(
                        maxSize: '50M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'JPG, PNG или WEBP',
                    ),
                ],
            ])
            ->add('title', \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['label' => 'Название'])
            ->add('description', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'label' => 'Описание', 'required' => false, 'attr' => ['rows' => 3],
            ])
            ->add('takenDate', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'label' => 'Дата съёмки', 'required' => false,
            ])
            ->add('takenYear', \Symfony\Component\Form\Extension\Core\Type\IntegerType::class, [
                'label' => 'Год (для сортировки)', 'required' => false,
                'attr' => ['min' => 1800, 'max' => 2100],
            ])
            ->add('place', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'label' => 'Место съёмки', 'required' => false,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('imageFile')->getData();
            $info = $this->storage->store($file, 'photos');

            $photo = new Photo(
                $form->get('title')->getData(),
                $info['path'],
                $info['mime'],
                $info['size'],
                $user,
            );
            $photo->setDescription($form->get('description')->getData());
            $photo->setTakenDate($form->get('takenDate')->getData());
            $photo->setTakenYear($form->get('takenYear')->getData());
            $photo->setPlace($form->get('place')->getData());

            // Определяем размеры изображения
            $absPath = $this->storage->absolutePath($info['path']);
            $sizes = @getimagesize($absPath);
            if (is_array($sizes)) {
                $photo->setImageWidth($sizes[0]);
                $photo->setImageHeight($sizes[1]);
            }

            $this->em->persist($photo);
            $this->em->flush();

            $this->logger->log(
                ActivityLog::ACTION_PHOTO_UPLOAD,
                entityType: 'photo',
                entityId: $photo->getId(),
                metadata: ['title' => $photo->getTitle()],
            );

            $this->addFlash('success', 'Фотография добавлена');
            return $this->redirectToRoute('photos_show', ['id' => $photo->getId()]);
        }

        return $this->render('photos/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Photo $photo): Response
    {
        $this->denyAccessUnlessGranted(PhotoVoter::VIEW, $photo);
        return $this->render('photos/show.html.twig', ['photo' => $photo]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Photo $photo, Request $request): Response
    {
        $this->denyAccessUnlessGranted(PhotoVoter::EDIT, $photo);

        $form = $this->createForm(PhotoType::class, $photo, ['require_file' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Изменения сохранены');
            return $this->redirectToRoute('photos_show', ['id' => $photo->getId()]);
        }

        return $this->render('photos/edit.html.twig', ['photo' => $photo, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Photo $photo, Request $request): Response
    {
        $this->denyAccessUnlessGranted(PhotoVoter::DELETE, $photo);
        if (!$this->isCsrfTokenValid('delete-photo-' . $photo->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        /** @var User $user */
        $user = $this->getUser();
        $photo->setDeletedAt(new \DateTimeImmutable());
        $photo->setDeletedBy($user);
        $this->em->flush();

        $this->addFlash('success', 'Фото перемещено в корзину');
        return $this->redirectToRoute('photos_index');
    }

    /**
     * Отдача файла фото — приватная, через контроллер с проверкой прав.
     */
    #[Route('/{id}/image', name: 'image', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function image(Photo $photo): Response
    {
        $this->denyAccessUnlessGranted(PhotoVoter::VIEW, $photo);
        $abs = $this->storage->absolutePath($photo->getImagePath());
        if (!is_file($abs)) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($abs);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('Content-Type', $photo->getMimeType());
        $response->headers->set('Cache-Control', 'private, max-age=86400');
        return $response;
    }

    // === Теги ===

    /**
     * @return JsonResponse список тегов фотографии
     */
    #[Route('/{id}/tags', name: 'tags_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function tagsList(Photo $photo): JsonResponse
    {
        $this->denyAccessUnlessGranted(PhotoVoter::VIEW, $photo);

        $items = array_map(fn (PhotoTag $t) => [
            'id' => $t->getId(),
            'person_id' => $t->getPerson()->getId(),
            'person_name' => $t->getPerson()->getFullName(),
            'person_lifespan' => $t->getPerson()->getLifespanLabel(),
            'x' => $t->getX(),
            'y' => $t->getY(),
            'width' => $t->getWidth(),
            'height' => $t->getHeight(),
        ], $photo->getTags()->toArray());

        return new JsonResponse(['items' => $items]);
    }

    /**
     * Создать тег. Тело: { person_id?: int, new_person_name?: string, x, y, width, height }
     */
    #[Route('/{id}/tags', name: 'tags_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function tagsCreate(Photo $photo, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PhotoVoter::TAG, $photo);
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'invalid_json'], 400);
        }

        $person = null;
        if (!empty($data['person_id'])) {
            $person = $this->persons->find((int) $data['person_id']);
        } elseif (!empty($data['new_person_name'])) {
            $name = trim((string) $data['new_person_name']);
            if ($name === '') {
                return new JsonResponse(['error' => 'empty_name'], 400);
            }
            $person = new Person($name, $user);
            $this->persons->save($person);
        }

        if ($person === null) {
            return new JsonResponse(['error' => 'no_person'], 400);
        }

        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (!isset($data[$key]) || !is_numeric($data[$key])) {
                return new JsonResponse(['error' => "missing_$key"], 400);
            }
        }

        $tag = new PhotoTag(
            $photo, $person,
            (float) $data['x'], (float) $data['y'],
            (float) $data['width'], (float) $data['height'],
            $user,
        );
        $this->photoTags->save($tag);

        return new JsonResponse([
            'id' => $tag->getId(),
            'person_id' => $person->getId(),
            'person_name' => $person->getFullName(),
            'person_lifespan' => $person->getLifespanLabel(),
            'x' => $tag->getX(),
            'y' => $tag->getY(),
            'width' => $tag->getWidth(),
            'height' => $tag->getHeight(),
        ], 201);
    }

    #[Route('/{photoId}/tags/{tagId}', name: 'tags_delete', methods: ['DELETE'], requirements: ['photoId' => '\d+', 'tagId' => '\d+'])]
    public function tagsDelete(int $photoId, int $tagId, Request $request): JsonResponse
    {
        $photo = $this->photos->find($photoId);
        if ($photo === null) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }
        $this->denyAccessUnlessGranted(PhotoVoter::TAG, $photo);

        $tag = $this->photoTags->find($tagId);
        if ($tag === null || $tag->getPhoto()->getId() !== $photo->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        // Удалять может автор тега или админ
        if (!$user->isAdmin() && $tag->getCreatedBy()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $this->em->remove($tag);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
