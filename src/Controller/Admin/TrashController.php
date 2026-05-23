<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\DocumentRepository;
use App\Repository\ExternalLinkRepository;
use App\Repository\PersonRepository;
use App\Repository\PhotoRepository;
use App\Security\BookVoter;
use App\Security\DocumentVoter;
use App\Security\ExternalLinkVoter;
use App\Security\PhotoVoter;
use App\Service\ActivityLogger;
use App\Service\BookCoverGenerator;
use App\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/trash', name: 'admin_trash_')]
#[IsGranted(User::ROLE_ADMIN)]
final class TrashController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly BookRepository $books,
        private readonly PhotoRepository $photos,
        private readonly PersonRepository $persons,
        private readonly ExternalLinkRepository $links,
        private readonly EntityManagerInterface $em,
        private readonly FileStorage $storage,
        private readonly BookCoverGenerator $coverGenerator,
        private readonly ActivityLogger $logger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/trash/index.html.twig', [
            'documents' => $this->documents->findTrashed(),
            'books' => $this->books->findTrashed(),
            'photos' => $this->photos->findTrashed(),
            'persons' => $this->persons->findTrashed(),
            'links' => $this->links->findTrashed(),
        ]);
    }

    // ==== документы ====

    #[Route('/document/{id}/restore', name: 'restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restoreDocument(int $id, Request $request): Response
    {
        $doc = $this->documents->findIncludingDeleted($id);
        if ($doc === null) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(DocumentVoter::RESTORE, $doc);
        if (!$this->isCsrfTokenValid('restore-doc-' . $doc->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $doc->setDeletedAt(null);
        $doc->setDeletedBy(null);
        $this->em->flush();
        $this->logger->log('document_restore', entityType: 'document', entityId: $doc->getId(), metadata: ['title' => $doc->getTitle()]);
        $this->addFlash('success', 'Документ восстановлен');
        return $this->redirectToRoute('admin_trash_index');
    }

    #[Route('/document/{id}/purge', name: 'purge', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function purgeDocument(int $id, Request $request): Response
    {
        $doc = $this->documents->findIncludingDeleted($id);
        if ($doc === null) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(DocumentVoter::PERMA_DELETE, $doc);
        if (!$this->isCsrfTokenValid('purge-doc-' . $doc->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $title = $doc->getTitle();
        $docId = $doc->getId();
        foreach ($doc->getFiles() as $f) {
            $this->storage->delete($f->getStoredPath());
        }
        $this->em->remove($doc);
        $this->em->flush();
        $this->logger->log('document_purge', entityType: 'document', entityId: $docId, metadata: ['title' => $title, 'irreversible' => true]);
        $this->addFlash('success', 'Документ удалён окончательно');
        return $this->redirectToRoute('admin_trash_index');
    }

    // ==== книги ====

    #[Route('/book/{id}/restore', name: 'restore_book', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restoreBook(int $id, Request $request): Response
    {
        $book = $this->books->findIncludingDeleted($id);
        if ($book === null) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(BookVoter::RESTORE, $book);
        if (!$this->isCsrfTokenValid('restore-book-' . $book->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $book->setDeletedAt(null);
        $book->setDeletedBy(null);
        $this->em->flush();
        $this->addFlash('success', 'Книга восстановлена');
        return $this->redirectToRoute('admin_trash_index');
    }

    #[Route('/book/{id}/purge', name: 'purge_book', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function purgeBook(int $id, Request $request): Response
    {
        $book = $this->books->findIncludingDeleted($id);
        if ($book === null) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(BookVoter::PERMA_DELETE, $book);
        if (!$this->isCsrfTokenValid('purge-book-' . $book->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $title = $book->getTitle();
        $bookId = $book->getId();
        foreach ($book->getFiles() as $f) {
            $this->storage->delete($f->getStoredPath());
        }
        $this->coverGenerator->deleteCover($book->getCoverPath());
        $this->em->remove($book);
        $this->em->flush();
        $this->logger->log('book_purge', entityType: 'book', entityId: $bookId, metadata: ['title' => $title, 'irreversible' => true]);
        $this->addFlash('success', 'Книга удалена окончательно');
        return $this->redirectToRoute('admin_trash_index');
    }

    // ==== фото ====

    #[Route('/photo/{id}/restore', name: 'restore_photo', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restorePhoto(int $id, Request $request): Response
    {
        $photo = $this->photos->find($id);
        if ($photo === null) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(PhotoVoter::RESTORE, $photo);
        if (!$this->isCsrfTokenValid('restore-photo-' . $photo->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $photo->setDeletedAt(null);
        $photo->setDeletedBy(null);
        $this->em->flush();
        $this->addFlash('success', 'Фото восстановлено');
        return $this->redirectToRoute('admin_trash_index');
    }

    #[Route('/photo/{id}/purge', name: 'purge_photo', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function purgePhoto(int $id, Request $request): Response
    {
        $photo = $this->photos->find($id);
        if ($photo === null) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(PhotoVoter::PERMA_DELETE, $photo);
        if (!$this->isCsrfTokenValid('purge-photo-' . $photo->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $title = $photo->getTitle();
        $photoId = $photo->getId();
        $this->storage->delete($photo->getImagePath());
        $this->em->remove($photo);
        $this->em->flush();
        $this->logger->log('photo_purge', entityType: 'photo', entityId: $photoId, metadata: ['title' => $title, 'irreversible' => true]);
        $this->addFlash('success', 'Фото удалено окончательно');
        return $this->redirectToRoute('admin_trash_index');
    }

    // ==== люди ====

    #[Route('/person/{id}/restore', name: 'restore_person', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restorePerson(int $id, Request $request): Response
    {
        $person = $this->persons->find($id);
        if ($person === null) { throw $this->createNotFoundException(); }
        if (!$this->isCsrfTokenValid('restore-person-' . $person->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $person->setDeletedAt(null);
        $person->setDeletedBy(null);
        $this->em->flush();
        $this->addFlash('success', 'Запись восстановлена');
        return $this->redirectToRoute('admin_trash_index');
    }

    #[Route('/person/{id}/purge', name: 'purge_person', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function purgePerson(int $id, Request $request): Response
    {
        $person = $this->persons->find($id);
        if ($person === null) { throw $this->createNotFoundException(); }
        if (!$this->isCsrfTokenValid('purge-person-' . $person->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $name = $person->getFullName();
        $personId = $person->getId();
        $this->em->remove($person);
        $this->em->flush();
        $this->logger->log('person_purge', entityType: 'person', entityId: $personId, metadata: ['name' => $name, 'irreversible' => true]);
        $this->addFlash('success', 'Запись о человеке удалена окончательно');
        return $this->redirectToRoute('admin_trash_index');
    }

    // ==== ссылки ====

    #[Route('/link/{id}/restore', name: 'restore_link', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restoreLink(int $id, Request $request): Response
    {
        $link = $this->links->find($id);
        if ($link === null) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(ExternalLinkVoter::RESTORE, $link);
        if (!$this->isCsrfTokenValid('restore-link-' . $link->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $link->setDeletedAt(null);
        $link->setDeletedBy(null);
        $this->em->flush();
        $this->addFlash('success', 'Ссылка восстановлена');
        return $this->redirectToRoute('admin_trash_index');
    }

    #[Route('/link/{id}/purge', name: 'purge_link', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function purgeLink(int $id, Request $request): Response
    {
        $link = $this->links->find($id);
        if ($link === null) { throw $this->createNotFoundException(); }
        $this->denyAccessUnlessGranted(ExternalLinkVoter::PERMA_DELETE, $link);
        if (!$this->isCsrfTokenValid('purge-link-' . $link->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $title = $link->getTitle();
        $linkId = $link->getId();
        $this->em->remove($link);
        $this->em->flush();
        $this->logger->log('link_purge', entityType: 'link', entityId: $linkId, metadata: ['title' => $title]);
        $this->addFlash('success', 'Ссылка удалена окончательно');
        return $this->redirectToRoute('admin_trash_index');
    }
}
