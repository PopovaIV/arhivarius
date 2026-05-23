<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Book;
use App\Entity\BookFile;
use App\Entity\User;
use App\Enum\BookFormat;
use App\Form\BookType;
use App\Repository\BookRepository;
use App\Repository\ReadingProgressRepository;
use App\Security\BookVoter;
use App\Service\ActivityLogger;
use App\Service\BookCoverGenerator;
use App\Service\FileStorage;
use App\Service\TextExtractor;
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

#[Route('/books', name: 'books_')]
#[IsGranted('ROLE_USER')]
final class BookController extends AbstractController
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly ReadingProgressRepository $progresses,
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogger $logger,
        private readonly FileStorage $storage,
        private readonly BookCoverGenerator $coverGenerator,
        private readonly TextExtractor $textExtractor,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $filters = [
            'q' => $request->query->get('q'),
            'language' => $request->query->get('language'),
            'year_from' => $request->query->get('year_from') ? (int) $request->query->get('year_from') : null,
            'year_to' => $request->query->get('year_to') ? (int) $request->query->get('year_to') : null,
        ];
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 24;

        $items = $this->books->search($filters, $perPage, ($page - 1) * $perPage);
        $total = $this->books->countSearch($filters);
        $progressMap = $this->progresses->mapForUserAndBooks($user, $items);

        return $this->render('books/index.html.twig', [
            'books' => $items,
            'progress_map' => $progressMap,
            'filters' => $filters,
            'languages' => $this->books->distinctLanguages(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    /**
     * Полнотекстовый поиск по содержимому книг.
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $q = trim((string) $request->query->get('q', ''));
        $results = [];
        if ($q !== '') {
            $results = $this->books->searchContent($q, $user->isAdmin());
        }
        return $this->render('books/search.html.twig', [
            'q' => $q,
            'results' => $results,
        ]);
    }

    /**
     * Отдача обложки книги. Аутентифицированный endpoint, без логирования (тяжёлая статика).
     */
    #[Route('/{id}/cover', name: 'cover', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function cover(Book $book): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::VIEW, $book);
        $path = $book->getCoverPath();
        if ($path === null) {
            throw $this->createNotFoundException();
        }
        $abs = $this->storage->absolutePath($path);
        if (!is_file($abs)) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($abs);
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('Cache-Control', 'private, max-age=86400');
        return $response;
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CONTRIBUTOR')]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $book = new Book($user);

        $form = $this->createForm(BookType::class, $book, ['files_required' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($book);
            $this->em->flush();
            $this->storeUploads($book, $form->get('uploadFiles')->getData() ?? [], $user);

            $this->addFlash('success', 'Книга добавлена');
            return $this->redirectToRoute('books_show', ['id' => $book->getId()]);
        }

        return $this->render('books/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Book $book): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::VIEW, $book);
        /** @var User $user */
        $user = $this->getUser();

        $progress = $this->progresses->findFor($user, $book);

        return $this->render('books/show.html.twig', [
            'book' => $book,
            'progress' => $progress,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Book $book, Request $request): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::EDIT, $book);
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->storeUploads($book, $form->get('uploadFiles')->getData() ?? [], $user);
            $this->addFlash('success', 'Изменения сохранены');
            return $this->redirectToRoute('books_show', ['id' => $book->getId()]);
        }

        return $this->render('books/edit.html.twig', ['book' => $book, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Book $book, Request $request): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::DELETE, $book);
        if (!$this->isCsrfTokenValid('delete-book-' . $book->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $book->setDeletedAt(new \DateTimeImmutable());
        $book->setDeletedBy($user);
        $this->em->flush();

        $this->logger->log('book_delete', entityType: 'book', entityId: $book->getId(), metadata: ['title' => $book->getTitle()]);
        $this->addFlash('success', 'Книга перемещена в корзину');
        return $this->redirectToRoute('books_index');
    }

    // ===== ЧТЕНИЕ =====

    #[Route('/{id}/read', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Book $book, Request $request): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::READ, $book);

        $fileId = $request->query->get('file');
        $file = $fileId !== null ? $this->findFile($book, (int) $fileId) : $book->findReadableFile();

        if ($file === null) {
            $this->addFlash('error', 'Для этой книги нет файла, который можно открыть в браузере. Скачайте файл и читайте локально.');
            return $this->redirectToRoute('books_show', ['id' => $book->getId()]);
        }

        $route = $file->getFormat()->readerRoute();
        if ($route === null) {
            return $this->redirectToRoute('books_file_download', ['bookId' => $book->getId(), 'fileId' => $file->getId()]);
        }

        $this->logger->log(
            ActivityLog::ACTION_BOOK_OPEN,
            entityType: 'book',
            entityId: $book->getId(),
            metadata: ['title' => $book->getTitle(), 'format' => $file->getFormat()->value],
        );

        return $this->redirectToRoute($route, ['bookId' => $book->getId(), 'fileId' => $file->getId()]);
    }

    #[Route('/{bookId}/read/pdf/{fileId}', name: 'read_pdf', methods: ['GET'], requirements: ['bookId' => '\d+', 'fileId' => '\d+'])]
    public function readPdf(int $bookId, int $fileId): Response
    {
        [$book, $file] = $this->loadBookAndFile($bookId, $fileId);
        $this->denyAccessUnlessGranted(BookVoter::READ, $book);
        /** @var User $user */
        $user = $this->getUser();
        $progress = $this->progresses->findFor($user, $book);

        return $this->render('books/read_pdf.html.twig', [
            'book' => $book,
            'file' => $file,
            'progress' => $progress,
            'stream_url' => $this->generateUrl('books_file_stream', ['bookId' => $book->getId(), 'fileId' => $file->getId()]),
        ]);
    }

    #[Route('/{bookId}/read/epub/{fileId}', name: 'read_epub', methods: ['GET'], requirements: ['bookId' => '\d+', 'fileId' => '\d+'])]
    public function readEpub(int $bookId, int $fileId): Response
    {
        [$book, $file] = $this->loadBookAndFile($bookId, $fileId);
        $this->denyAccessUnlessGranted(BookVoter::READ, $book);
        /** @var User $user */
        $user = $this->getUser();
        $progress = $this->progresses->findFor($user, $book);

        return $this->render('books/read_epub.html.twig', [
            'book' => $book,
            'file' => $file,
            'progress' => $progress,
            'stream_url' => $this->generateUrl('books_file_stream', ['bookId' => $book->getId(), 'fileId' => $file->getId()]),
        ]);
    }

    /**
     * Стриминг файла с поддержкой Range Requests.
     * Symfony BinaryFileResponse автоматически отвечает 206 Partial Content на Range-заголовок,
     * что позволяет PDF.js / epub.js подгружать книгу по кускам и не зависать на больших файлах.
     */
    #[Route('/{bookId}/file/{fileId}/stream', name: 'file_stream', methods: ['GET'], requirements: ['bookId' => '\d+', 'fileId' => '\d+'])]
    public function streamFile(int $bookId, int $fileId, Request $request): Response
    {
        [$book, $file] = $this->loadBookAndFile($bookId, $fileId);
        $this->denyAccessUnlessGranted(BookVoter::READ, $book);

        $abs = $this->storage->absolutePath($file->getStoredPath());
        if (!is_file($abs)) {
            throw $this->createNotFoundException('Файл отсутствует на диске');
        }

        $response = new BinaryFileResponse($abs);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file->getOriginalName());
        $response->headers->set('Content-Type', $file->getMimeType());
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        // BinaryFileResponse сам обрабатывает Range-заголовок и возвращает 206 Partial Content
        $response->prepare($request);
        return $response;
    }

    #[Route('/{bookId}/file/{fileId}/download', name: 'file_download', methods: ['GET'], requirements: ['bookId' => '\d+', 'fileId' => '\d+'])]
    public function downloadFile(int $bookId, int $fileId): Response
    {
        [$book, $file] = $this->loadBookAndFile($bookId, $fileId);
        $this->denyAccessUnlessGranted(BookVoter::VIEW, $book);

        $abs = $this->storage->absolutePath($file->getStoredPath());
        if (!is_file($abs)) {
            throw $this->createNotFoundException();
        }

        $this->logger->log(
            ActivityLog::ACTION_BOOK_DOWNLOAD,
            entityType: 'book',
            entityId: $book->getId(),
            metadata: [
                'title' => $book->getTitle(),
                'format' => $file->getFormat()->value,
                'file_name' => $file->getOriginalName(),
                'file_size' => $file->getSizeBytes(),
            ],
        );

        $response = new BinaryFileResponse($abs);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getOriginalName());
        $response->headers->set('Content-Type', $file->getMimeType());
        return $response;
    }

    #[Route('/{bookId}/file/{fileId}/delete', name: 'file_delete', methods: ['POST'], requirements: ['bookId' => '\d+', 'fileId' => '\d+'])]
    public function deleteFile(int $bookId, int $fileId, Request $request): Response
    {
        [$book, $file] = $this->loadBookAndFile($bookId, $fileId);
        $this->denyAccessUnlessGranted(BookVoter::EDIT, $book);

        if (!$this->isCsrfTokenValid('delete-bf-' . $file->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = $file->getOriginalName();
        $this->storage->delete($file->getStoredPath());
        $this->em->remove($file);
        $this->em->flush();

        $this->logger->log('book_file_delete', entityType: 'book', entityId: $book->getId(), metadata: ['file_name' => $name]);
        $this->addFlash('success', "Файл удалён: {$name}");
        return $this->redirectToRoute('books_show', ['id' => $book->getId()]);
    }

    /**
     * Сохранение прогресса чтения. JSON эндпоинт, вызывается читалкой.
     * Принимает: { pdf_page?, pdf_total?, epub_cfi?, percent?, delta_seconds? }
     */
    #[Route('/{id}/progress', name: 'progress', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function progress(Book $book, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(BookVoter::READ, $book);
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_json'], 400);
        }

        $progress = $this->progresses->findOrCreate($user, $book);

        if (isset($data['pdf_page'])) {
            $progress->setPdfPage(max(1, (int) $data['pdf_page']));
        }
        if (isset($data['pdf_total'])) {
            $progress->setPdfTotalPages(max(1, (int) $data['pdf_total']));
        }
        if (isset($data['epub_cfi']) && is_string($data['epub_cfi'])) {
            $progress->setEpubCfi(mb_substr($data['epub_cfi'], 0, 500));
        }
        if (isset($data['percent'])) {
            $progress->setPercent(max(0, min(100, (int) $data['percent'])));
        }
        $delta = isset($data['delta_seconds']) ? (int) $data['delta_seconds'] : 0;
        if ($delta > 0 && $delta < 120) {  // отбрасываем неадекватные значения (>2 минут — фон неактивен)
            $progress->addSeconds($delta);
        }
        $progress->touch();
        $this->em->flush();

        // Логируем периодически — не на каждый heartbeat, иначе журнал захламится.
        // Пишем только если есть значимое изменение позиции
        if (isset($data['pdf_page']) || isset($data['epub_cfi'])) {
            $this->logger->log(
                ActivityLog::ACTION_BOOK_READ_PROGRESS,
                entityType: 'book',
                entityId: $book->getId(),
                metadata: [
                    'title' => $book->getTitle(),
                    'page' => $data['pdf_page'] ?? null,
                    'percent' => $progress->getPercent(),
                ],
                durationSeconds: $delta > 0 ? $delta : null,
            );
        }

        return new JsonResponse(['ok' => true, 'percent' => $progress->getPercent(), 'total_seconds' => $progress->getTotalSeconds()]);
    }

    // ===== вспомогательное =====

    /**
     * @return array{0: Book, 1: BookFile}
     */
    private function loadBookAndFile(int $bookId, int $fileId): array
    {
        $book = $this->books->find($bookId);
        if ($book === null) {
            throw $this->createNotFoundException();
        }
        $file = $this->findFile($book, $fileId);
        if ($file === null) {
            throw $this->createNotFoundException();
        }
        return [$book, $file];
    }

    private function findFile(Book $book, int $fileId): ?BookFile
    {
        foreach ($book->getFiles() as $f) {
            if ((int) $f->getId() === $fileId) {
                return $f;
            }
        }
        return null;
    }

    /**
     * @param iterable<int, UploadedFile|null> $uploads
     */
    private function storeUploads(Book $book, iterable $uploads, User $user): void
    {
        $any = false;
        foreach ($uploads as $upload) {
            if (!$upload instanceof UploadedFile) {
                continue;
            }
            $info = $this->storage->store($upload, 'books');
            $extension = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
            $format = BookFormat::fromMimeOrExtension($info['mime'], $extension);
            if ($format === null) {
                $format = BookFormat::Txt;
            }
            $bf = new BookFile(
                $book, $format, $info['originalName'], $info['path'],
                $info['mime'], $info['size'], $user,
            );
            $this->em->persist($bf);
            $this->em->flush(); // нужен id для генерации обложки и tsvector

            // Обложка: только из PDF и только если у книги её ещё нет
            if ($format === BookFormat::Pdf && !$book->hasCover()) {
                $coverPath = $this->coverGenerator->generateFromPdf(
                    $this->storage->absolutePath($info['path']),
                    $book->getId(),
                );
                if ($coverPath !== null) {
                    $book->setCoverPath($coverPath);
                }
            }

            // Экстракция текста для FTS — безопасная: при ошибке статус failed, upload не ломаем
            try {
                $text = $this->textExtractor->extract($format, $info['path']);
                if ($text !== null && $text !== '') {
                    $bf->setExtractedText($text);
                    $bf->setTextStatus('processed');
                } else {
                    $bf->setTextStatus('skipped');
                }
            } catch (\Throwable) {
                $bf->setTextStatus('failed');
            }

            $this->em->flush();

            // search_vector — это GENERATED column, PostgreSQL обновляет её сам при UPDATE на extracted_text.
            // Никаких дополнительных вызовов не требуется.

            $any = true;
        }
        if ($any) {
            $this->em->flush();
        }
    }
}
