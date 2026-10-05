<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\NotificationRepository;

final class NotificationService
{
    public const ICONS = [
        'nouvelle_commande'  => 'fa-shopping-cart',
        'commande_urgente'   => 'fa-bolt',
        'nouveau_message'    => 'fa-comment',
        'validation'         => 'fa-check-circle',
        'refus'              => 'fa-times-circle',
        'livraison'          => 'fa-truck',
        'expedition'         => 'fa-shipping-fast',
        'preparation'        => 'fa-box-open',
        'prete'              => 'fa-check-double',
        'reception'          => 'fa-trophy',
    ];

    public const COLORS = [
        'nouvelle_commande'  => '#3498db',
        'commande_urgente'   => '#e74c3c',
        'nouveau_message'    => '#9b59b6',
        'validation'         => '#27ae60',
        'refus'              => '#e74c3c',
        'livraison'          => '#27ae60',
        'expedition'         => '#2980b9',
        'preparation'        => '#8b5cf6',
        'prete'              => '#0d9488',
        'reception'          => '#eab308',
    ];

    /**
     * Titre affiché sans émoji : les anciens titres commençaient par 🚚, ✅, ⚡… alors que
     * l'icône du type de notification remplit déjà ce rôle (les nouveaux n'en ont plus).
     */
    public static function cleanTitle(string $title): string
    {
        $clean = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{231A}-\x{23FF}\x{FE0F}\x{200D}]/u', '', $title);
        $clean = trim(preg_replace('/\s{2,}/u', ' ', (string) $clean));
        $clean = str_replace('COMMANDE URGENTE', 'Commande urgente', $clean); // anciens titres en majuscules

        return $clean !== '' ? $clean : $title;
    }

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

    /**
     * Page à ouvrir pour une notification : la commande concernée, dans le bon onglet
     * (« Mes commandes » si l'entreprise est l'acheteuse, « Reçues » sinon), détail déplié
     * — ou le chat pour un message. Sans commande : la liste des notifications.
     */
    public static function targetUrl(array $notification, int $enterpriseId): string
    {
        $orderId = (int) ($notification['Id_Commande_B2B'] ?? 0);
        if ($orderId <= 0 || empty($notification['Numero_Commande'])) {
            return 'notifications_b2b.php';
        }

        $tab = (int) ($notification['Id_Entreprise_Acheteuse'] ?? 0) === $enterpriseId ? 'passees' : 'recues';
        if (($notification['Type_Notif'] ?? '') === 'nouveau_message') {
            return "commandes_b2b.php?onglet=$tab&open_chat=$orderId&num=" . urlencode($notification['Numero_Commande']);
        }

        return "commandes_b2b.php?onglet=$tab&ouvrir=$orderId#detail-$orderId";
    }

    /** Marque la notification comme lue et renvoie la page à ouvrir (null si elle n'appartient pas à l'entreprise). */
    public function open(int $notificationId, int $enterpriseId): ?string
    {
        $notification = $this->repository->findForEnterprise($notificationId, $enterpriseId);
        if ($notification === null) {
            return null;
        }
        $this->repository->markRead($notificationId, $enterpriseId);

        return self::targetUrl($notification, $enterpriseId);
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
