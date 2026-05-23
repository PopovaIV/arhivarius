<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ChatChannel;
use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\ChatChannelRepository;
use App\Repository\ChatMessageRepository;
use App\Repository\ChatReadStateRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat', name: 'chat_')]
#[IsGranted('ROLE_USER')]
final class ChatController extends AbstractController
{
    public function __construct(
        private readonly ChatChannelRepository $channels,
        private readonly ChatMessageRepository $messages,
        private readonly ChatReadStateRepository $readStates,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Главный экран чата. По умолчанию открывает публичный канал.
     * Сайдбар показывает public + все direct-каналы пользователя.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('chat_public');
    }

    #[Route('/public', name: 'public', methods: ['GET'])]
    public function publicChannel(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $channel = $this->channels->getOrCreatePublic();
        return $this->renderChannel($channel, $user);
    }

    #[Route('/direct/{otherId}', name: 'direct', methods: ['GET'], requirements: ['otherId' => '\d+'])]
    public function directChannel(int $otherId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $other = $this->users->find($otherId);
        if ($other === null || !$other->isActive() || $other->getId() === $user->getId()) {
            throw $this->createNotFoundException();
        }
        $channel = $this->channels->getOrCreateDirect($user, $other);
        return $this->renderChannel($channel, $user);
    }

    /**
     * Список direct-каналов + список других пользователей для нового диалога.
     */
    #[Route('/contacts', name: 'contacts', methods: ['GET'])]
    public function contacts(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $directs = $this->channels->findDirectChannelsForUser($user);

        // Соберём «других» участников вместе с непрочитанными
        $items = [];
        foreach ($directs as $ch) {
            $other = $ch->getOtherParticipant($user);
            if ($other === null) {
                continue;
            }
            $state = $this->readStates->findOneBy(['user' => $user, 'channel' => $ch]);
            $lastReadId = $state?->getLastReadMessageId();
            $unread = $this->messages->countSince($ch, $lastReadId !== null ? (int) $lastReadId : null);
            $items[] = [
                'channel' => $ch,
                'other' => $other,
                'unread' => $unread,
            ];
        }

        // Все остальные пользователи (с кем ещё нет диалога)
        $existingIds = [$user->getId()];
        foreach ($items as $it) {
            $existingIds[] = $it['other']->getId();
        }
        $allUsers = $this->users->findAllOrderedByCreatedAt();
        $available = array_filter($allUsers, fn (User $u) => !in_array($u->getId(), $existingIds, true) && $u->isActive());

        return $this->render('chat/contacts.html.twig', [
            'directs' => $items,
            'available' => $available,
        ]);
    }

    /**
     * Long polling: возвращает все сообщения после sinceId.
     * Браузер вызывает с timeout ~25 сек, ждёт новых сообщений, потом перезапрашивает.
     */
    #[Route('/channel/{channelId}/poll', name: 'poll', methods: ['GET'], requirements: ['channelId' => '\d+'])]
    public function poll(int $channelId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $channel = $this->channels->find($channelId);
        if ($channel === null || !$this->canAccess($channel, $user)) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $since = (int) $request->query->get('since', 0);
        $maxWait = (int) $request->query->get('wait', 0);  // секунд ожидания; 0 — без ожидания
        $maxWait = max(0, min(25, $maxWait));

        // Освобождаем сессионный лок ДО polling-цикла.
        // Иначе PHP держит файл сессии заблокированным всё время ожидания,
        // и параллельные запросы этого же пользователя виснут на session_start().
        $request->getSession()->save();

        $deadline = microtime(true) + $maxWait;
        $messages = $this->messages->findSince($channel, $since);

        // Long polling: если новых сообщений нет, ждём с poll-интервалом 2 сек
        // (этого хватает для семейного архива; для серьёзной нагрузки лучше Mercure)
        while ($messages === [] && microtime(true) < $deadline) {
            usleep(2_000_000); // 2 сек
            $this->em->clear(); // обновляем сессию
            $channel = $this->channels->find($channelId);
            if ($channel === null) {
                break;
            }
            $messages = $this->messages->findSince($channel, $since);
        }

        return new JsonResponse([
            'items' => array_map(fn (ChatMessage $m) => $this->serializeMessage($m, $user), $messages),
            'last_id' => $messages !== [] ? (int) end($messages)->getId() : $since,
        ]);
    }

    /**
     * Отправка сообщения. JSON-эндпоинт.
     */
    #[Route('/channel/{channelId}/send', name: 'send', methods: ['POST'], requirements: ['channelId' => '\d+'])]
    public function send(int $channelId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $channel = $this->channels->find($channelId);
        if ($channel === null || !$this->canAccess($channel, $user)) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            return new JsonResponse(['error' => 'empty'], 400);
        }
        if (mb_strlen($body) > 5000) {
            $body = mb_substr($body, 0, 5000);
        }

        $msg = new ChatMessage($channel, $user, $body);
        $this->messages->save($msg);

        return new JsonResponse($this->serializeMessage($msg, $user), 201);
    }

    /**
     * Soft delete своего сообщения (или любого, если админ).
     */
    #[Route('/messages/{id}/delete', name: 'message_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteMessage(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $msg = $this->messages->find($id);
        if ($msg === null || $msg->isDeleted()) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }
        if (!$user->isAdmin() && $msg->getAuthor()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $msg->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Отметить канал прочитанным до сообщения lastMessageId.
     */
    #[Route('/channel/{channelId}/read', name: 'read', methods: ['POST'], requirements: ['channelId' => '\d+'])]
    public function markRead(int $channelId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $channel = $this->channels->find($channelId);
        if ($channel === null || !$this->canAccess($channel, $user)) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $lastId = isset($data['last_id']) ? (int) $data['last_id'] : null;
        $this->readStates->markRead($user, $channel, $lastId);

        return new JsonResponse(['ok' => true]);
    }

    private function canAccess(ChatChannel $channel, User $user): bool
    {
        if ($channel->isPublic()) {
            return true;
        }
        foreach ($channel->getParticipants() as $p) {
            if ($p->getId() === $user->getId()) {
                return true;
            }
        }
        return false;
    }

    private function renderChannel(ChatChannel $channel, User $user): Response
    {
        $history = $this->messages->findRecent($channel);

        // Считаем непрочитанные для всех direct-каналов пользователя — для бейджей в сайдбаре
        $directs = $this->channels->findDirectChannelsForUser($user);
        $directs = array_map(function (ChatChannel $ch) use ($user) {
            $other = $ch->getOtherParticipant($user);
            $state = $this->readStates->findOneBy(['user' => $user, 'channel' => $ch]);
            $lastReadId = $state?->getLastReadMessageId();
            $unread = $this->messages->countSince($ch, $lastReadId !== null ? (int) $lastReadId : null);
            return ['channel' => $ch, 'other' => $other, 'unread' => $unread];
        }, $directs);

        // Сразу пометим открытый канал прочитанным до последнего сообщения
        $lastId = $this->messages->getLatestId($channel);
        $this->readStates->markRead($user, $channel, $lastId);

        return $this->render('chat/channel.html.twig', [
            'channel' => $channel,
            'other' => $channel->isDirect() ? $channel->getOtherParticipant($user) : null,
            'messages' => array_map(fn (ChatMessage $m) => $this->serializeMessage($m, $user), $history),
            'last_id' => $lastId ?? 0,
            'directs' => $directs,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(ChatMessage $m, User $viewer): array
    {
        return [
            'id' => (int) $m->getId(),
            'body' => $m->getBody(),
            'author_id' => $m->getAuthor()->getId(),
            'author_name' => $m->getAuthor()->getDisplayName(),
            'is_mine' => $m->getAuthor()->getId() === $viewer->getId(),
            'can_delete' => $viewer->isAdmin() || $m->getAuthor()->getId() === $viewer->getId(),
            'created_at' => $m->getCreatedAt()->format('c'),
        ];
    }
}
