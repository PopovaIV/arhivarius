<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Entity\BookAnnotation;
use App\Entity\User;
use App\Repository\BookAnnotationRepository;
use App\Security\BookVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/books/{bookId}/annotations', name: 'book_annotations_', requirements: ['bookId' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class BookAnnotationController extends AbstractController
{
    public function __construct(
        private readonly BookAnnotationRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Список закладок и заметок текущего пользователя в книге.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Book $book): JsonResponse
    {
        $this->denyAccessUnlessGranted(BookVoter::VIEW, $book);
        /** @var User $user */
        $user = $this->getUser();

        $items = $this->repo->findFor($user, $book);
        $data = array_map(fn (BookAnnotation $a) => [
            'id' => $a->getId(),
            'type' => $a->getType(),
            'content' => $a->getContent(),
            'pdf_page' => $a->getPdfPage(),
            'epub_cfi' => $a->getEpubCfi(),
            'created_at' => $a->getCreatedAt()->format('c'),
        ], $items);

        return new JsonResponse(['items' => $data]);
    }

    /**
     * Создать закладку или заметку.
     * Тело: { type: 'bookmark'|'note', content: string, pdf_page?: int, epub_cfi?: string }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Book $book, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(BookVoter::VIEW, $book);
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'invalid_json'], 400);
        }

        $type = $data['type'] ?? '';
        if (!in_array($type, [BookAnnotation::TYPE_BOOKMARK, BookAnnotation::TYPE_NOTE], true)) {
            return new JsonResponse(['error' => 'invalid_type'], 400);
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') {
            return new JsonResponse(['error' => 'empty_content'], 400);
        }
        if (mb_strlen($content) > 5000) {
            $content = mb_substr($content, 0, 5000);
        }

        $a = new BookAnnotation($user, $book, $type, $content);
        if (isset($data['pdf_page'])) {
            $a->setPdfPage(max(1, (int) $data['pdf_page']));
        }
        if (isset($data['epub_cfi']) && is_string($data['epub_cfi'])) {
            $a->setEpubCfi(mb_substr($data['epub_cfi'], 0, 500));
        }

        $this->repo->save($a);

        return new JsonResponse([
            'id' => $a->getId(),
            'type' => $a->getType(),
            'content' => $a->getContent(),
            'pdf_page' => $a->getPdfPage(),
            'epub_cfi' => $a->getEpubCfi(),
            'created_at' => $a->getCreatedAt()->format('c'),
        ], 201);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Book $book, int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(BookVoter::VIEW, $book);
        /** @var User $user */
        $user = $this->getUser();

        $a = $this->repo->find($id);
        if ($a === null || $a->getUser()->getId() !== $user->getId() || $a->getBook()->getId() !== $book->getId()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        $this->em->remove($a);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
