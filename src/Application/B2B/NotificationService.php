<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\NotificationRepository;

final class NotificationService
{
    public function __construct(private NotificationRepository $repository)
    {
    }

    public function unreadCount(int $enterpriseId): int
    {
        return $this->repository->countUnread($enterpriseId);
    }

    public function latest(int $enterpriseId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $notifications = $this->repository->findLatest($enterpriseId, $limit);

        foreach ($notifications as &$notification) {
            $timestamp = strtotime($notification['Date_Creation']);
            $difference = time() - $timestamp;
            $notification['Temps_Relatif'] = $difference < 60
                ? 'À l\'instant'
                : ($difference < 3600
                    ? 'Il y a ' . floor($difference / 60) . ' min'
                    : ($difference < 86400
                        ? 'Il y a ' . floor($difference / 3600) . 'h'
                        : date('d/m/Y', $timestamp)));
        }
        unset($notification);

        return $notifications;
    }

    public function markRead(int $notificationId, int $enterpriseId): void
    {
        $this->repository->markRead($notificationId, $enterpriseId);
    }

    public function markAllRead(int $enterpriseId): int
    {
        return $this->repository->markAllRead($enterpriseId);
    }
}
