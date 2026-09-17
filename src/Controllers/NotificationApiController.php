<?php

namespace App\Controllers;

use App\Application\B2B\NotificationService;
use App\Infrastructure\Persistence\NotificationRepository;

/** Contrôleur de api/notifications.php — polling AJAX des notifications B2B (badge cloche). */
class NotificationApiController extends Controller
{
    private const ICONES = [
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

    private const COULEURS = [
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

    public function handle(): void
    {
        // Retour silencieux pour les rôles sans accès B2B (evite de casser le polling du header)
        if (!peutAccederB2B()) {
            $this->jsonResponse(['success' => true, 'count' => 0, 'notifications' => []]);
        }

        $mon_entreprise_id = (int) ($_SESSION['entreprise_id'] ?? 0);
        $notificationService = new NotificationService(new NotificationRepository($this->pdo));

        if (!$mon_entreprise_id) {
            $this->jsonResponse(['error' => 'Entreprise introuvable.'], 403);
        }

        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        if ($action === 'count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $count = $notificationService->unreadCount($mon_entreprise_id);
            $this->jsonResponse(['success' => true, 'count' => $count]);
        }

        if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $limit = intval($_GET['limit'] ?? 20);
            $notifs = $notificationService->latest($mon_entreprise_id, $limit);

            foreach ($notifs as &$n) {
                $n['Icone']   = self::ICONES[$n['Type_Notif']] ?? 'fa-bell';
                $n['Couleur'] = self::COULEURS[$n['Type_Notif']] ?? '#6b7076';
            }
            unset($n);

            $this->jsonResponse(['success' => true, 'notifications' => $notifs]);
        }

        if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            $id_notif = intval($_POST['id'] ?? 0);

            if (!$id_notif) {
                $this->jsonResponse(['error' => 'ID notification requis.']);
            }

            $notificationService->markRead($id_notif, $mon_entreprise_id);
            $this->jsonResponse(['success' => true]);
        }

        if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            $nb = $notificationService->markAllRead($mon_entreprise_id);
            $this->jsonResponse(['success' => true, 'marked' => $nb]);
        }

        $this->jsonResponse(['error' => "Action '$action' inconnue."], 400);
    }
}
