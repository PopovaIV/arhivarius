<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Document;
use App\Entity\DocumentFile;
use App\Entity\User;
use App\Enum\DocumentCategory;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Security\DocumentVoter;
use App\Service\ActivityLogger;
use App\Service\FileStorage;
use App\Util\ListTextarea;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents', name: 'documents_')]
#[IsGranted('ROLE_USER')]
final class DocumentController extends AbstractController
{
    /**
     * Поля metadata, которые приходят с textarea и должны быть массивом строк.
     */
    private const LIST_FIELDS = [
        'godparents', 'witnesses', 'household_members',
        'family_members', 'mentioned_persons', 'passengers',
    ];

    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogger $logger,
        private readonly FileStorage $storage,
    ) {
    }

    #[Route('', name: 'overview', methods: ['GET'])]
    public function overview(): Response
    {
        $stats = [];
        foreach (DocumentCategory::all() as $cat) {
            $stats[] = [
                'category' => $cat,
                'count' => $this->documents->countByCategory($cat),
            ];
        }
        return $this->render('documents/overview.html.twig', ['stats' => $stats]);
    }

    #[Route('/{slug}', name: 'index', methods: ['GET'], requirements: ['slug' => '[a-z-]+'])]
    public function index(string $slug, Request $request): Response
    {
        $category = $this->safeCategory($slug);
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 30;
        $items = $this->documents->findByCategory($category, $perPage, ($page - 1) * $perPage);
        $total = $this->documents->countByCategory($category);

        return $this->render('documents/index.html.twig', [
            'category' => $category,
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    #[Route('/{slug}/new', name: 'new', methods: ['GET', 'POST'], requirements: ['slug' => '[a-z-]+'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function new(string $slug, Request $request): Response
    {
        $category = $this->safeCategory($slug);
        /** @var User $user */
        $user = $this->getUser();
        $doc = new Document($category, $user);

        $form = $this->createForm(DocumentType::class, $doc, [
            'category' => $category,
            'metadata_data' => $this->metadataForForm([]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyMetadata($doc, $form->has('metadata') ? (array) $form->get('metadata')->getData() : []);
            $this->em->persist($doc);
            $this->em->flush();
            $this->storeUploads($doc, $form->get('uploadFiles')->getData() ?? [], $user);

            $this->logger->log(
                ActivityLog::ACTION_DOC_CREATE,
                entityType: 'document',
                entityId: $doc->getId(),
                metadata: ['category' => $category->value, 'title' => $doc->getTitle()],
            );
            $this->addFlash('success', 'Документ добавлен');
            return $this->redirectToRoute('documents_show', ['id' => $doc->getId()]);
        }

        return $this->render('documents/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/view/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        $this->logger->log(
            ActivityLog::ACTION_DOC_VIEW,
            entityType: 'document',
            entityId: $document->getId(),
            metadata: ['title' => $document->getTitle()],
        );

        return $this->render('documents/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/view/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Document $document, Request $request): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::EDIT, $document);
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(DocumentType::class, $document, [
            'category' => $document->getCategory(),
            'metadata_data' => $this->metadataForForm($document->getMetadata() ?? []),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyMetadata($document, $form->has('metadata') ? (array) $form->get('metadata')->getData() : []);
            $this->em->flush();
            $this->storeUploads($document, $form->get('uploadFiles')->getData() ?? [], $user);

            $this->addFlash('success', 'Изменения сохранены');
            return $this->redirectToRoute('documents_show', ['id' => $document->getId()]);
        }

        return $this->render('documents/edit.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/view/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Document $document, Request $request): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::DELETE, $document);

        if (!$this->isCsrfTokenValid('delete-doc-' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $document->setDeletedAt(new \DateTimeImmutable());
        $document->setDeletedBy($user);
        $this->em->flush();

        $this->logger->log(
            ActivityLog::ACTION_DOC_DELETE,
            entityType: 'document',
            entityId: $document->getId(),
            metadata: ['title' => $document->getTitle(), 'category' => $document->getCategory()->value],
        );

        $this->addFlash('success', 'Документ перемещён в корзину');
        return $this->redirectToRoute('documents_index', ['slug' => $document->getCategory()->slug()]);
    }

    #[Route('/view/{docId}/files/{fileId}/download', name: 'file_download', methods: ['GET'], requirements: ['docId' => '\d+', 'fileId' => '\d+'])]
    public function downloadFile(int $docId, int $fileId): Response
    {
        [$document, $file] = $this->loadDocAndFile($docId, $fileId);
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        $absPath = $this->storage->absolutePath($file->getStoredPath());
        if (!is_file($absPath)) {
            throw $this->createNotFoundException('Файл отсутствует на диске');
        }

        $this->logger->log(
            ActivityLog::ACTION_DOC_DOWNLOAD,
            entityType: 'document',
            entityId: $document->getId(),
            metadata: ['file_name' => $file->getOriginalName(), 'file_size' => $file->getSizeBytes()],
        );

        $response = new BinaryFileResponse($absPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getOriginalName());
        $response->headers->set('Content-Type', $file->getMimeType());
        return $response;
    }

    #[Route('/view/{docId}/files/{fileId}/preview', name: 'file_preview', methods: ['GET'], requirements: ['docId' => '\d+', 'fileId' => '\d+'])]
    public function previewFile(int $docId, int $fileId): Response
    {
        [$document, $file] = $this->loadDocAndFile($docId, $fileId);
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        $absPath = $this->storage->absolutePath($file->getStoredPath());
        if (!is_file($absPath)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($absPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file->getOriginalName());
        $response->headers->set('Content-Type', $file->getMimeType());
        $response->headers->set('Accept-Ranges', 'bytes');
        return $response;
    }

    #[Route('/view/{docId}/files/{fileId}/delete', name: 'file_delete', methods: ['POST'], requirements: ['docId' => '\d+', 'fileId' => '\d+'])]
    public function deleteFile(int $docId, int $fileId, Request $request): Response
    {
        [$document, $file] = $this->loadDocAndFile($docId, $fileId);
        $this->denyAccessUnlessGranted(DocumentVoter::EDIT, $document);

        if (!$this->isCsrfTokenValid('delete-file-' . $file->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $filename = $file->getOriginalName();
        $this->storage->delete($file->getStoredPath());
        $this->em->remove($file);
        $this->em->flush();

        $this->logger->log(
            'document_file_delete',
            entityType: 'document',
            entityId: $document->getId(),
            metadata: ['file_name' => $filename],
        );

        $this->addFlash('success', "Файл удалён: {$filename}");
        return $this->redirectToRoute('documents_show', ['id' => $document->getId()]);
    }

    /**
     * @return array{0: Document, 1: DocumentFile}
     */
    private function loadDocAndFile(int $docId, int $fileId): array
    {
        $document = $this->documents->find($docId);
        if ($document === null) {
            throw $this->createNotFoundException();
        }
        foreach ($document->getFiles() as $f) {
            if ((int) $f->getId() === $fileId) {
                return [$document, $f];
            }
        }
        throw $this->createNotFoundException();
    }

    /**
     * @param iterable<int, UploadedFile|null> $uploads
     */
    private function storeUploads(Document $doc, iterable $uploads, User $user): void
    {
        $any = false;
        foreach ($uploads as $upload) {
            if (!$upload instanceof UploadedFile) {
                continue;
            }
            $info = $this->storage->store($upload, 'documents');
            $file = new DocumentFile(
                $doc, $info['originalName'], $info['path'],
                $info['mime'], $info['size'], $user,
            );
            $this->em->persist($file);
            $any = true;
        }
        if ($any) {
            $this->em->flush();
        }
    }

    /**
     * Преобразование textarea-полей (массив строк) в текст для формы.
     *
     * @param array<string,mixed> $stored
     * @return array<string,mixed>
     */
    private function metadataForForm(array $stored): array
    {
        foreach (self::LIST_FIELDS as $field) {
            if (isset($stored[$field]) && is_array($stored[$field])) {
                $stored[$field] = ListTextarea::toText($stored[$field]);
            }
        }
        // record_type у метрических записей — EnumType, при редактировании нужно вернуть enum-кейс из строки
        if (isset($stored['record_type']) && is_string($stored['record_type'])) {
            $stored['record_type'] = \App\Enum\MetricRecordType::tryFrom($stored['record_type']);
        }
        return $stored;
    }

    /**
     * Преобразование данных формы → metadata: парсим textarea-поля в массивы строк,
     * убираем пустые значения, сохраняем enum-кейсы как строки.
     *
     * @param array<string,mixed> $raw
     */
    private function applyMetadata(Document $doc, array $raw): void
    {
        $cleaned = [];
        foreach ($raw as $key => $value) {
            // textarea-списки → массив строк
            if (in_array($key, self::LIST_FIELDS, true)) {
                $list = ListTextarea::fromText(is_string($value) ? $value : null);
                if ($list !== []) {
                    $cleaned[$key] = $list;
                }
                continue;
            }
            // enum → строка
            if ($value instanceof \BackedEnum) {
                $cleaned[$key] = $value->value;
                continue;
            }
            // отсеиваем пустоту
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $cleaned[$key] = $value;
        }
        $doc->setMetadata($cleaned !== [] ? $cleaned : null);
    }

    private function safeCategory(string $slug): DocumentCategory
    {
        try {
            return DocumentCategory::fromSlug($slug);
        } catch (\ValueError) {
            throw $this->createNotFoundException("Категория документов «{$slug}» не найдена");
        }
    }
}
