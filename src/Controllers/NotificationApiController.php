<?php

namespace App\Controllers;

use App\Application\B2B\NotificationService;
use App\Infrastructure\Persistence\NotificationRepository;

/** Contrôleur de api/notifications.php — polling AJAX des notifications B2B (badge cloche). */
class NotificationApiController extends Controller
{

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

        // Polling du bandeau (cloche) : compteur + dernières notifications, en lecture seule
        // (aucun jeton CSRF généré : le pool de jetons de la session servirait aux formulaires).
        if ($action === 'poll' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $notifs = $notificationService->latest($mon_entreprise_id, 5);
            foreach ($notifs as &$n) {
                $n['Icone']   = NotificationService::ICONS[$n['Type_Notif']] ?? 'fa-bell';
                $n['Couleur'] = NotificationService::COLORS[$n['Type_Notif']] ?? '#6b7076';
                $n['Lien']    = '../api/notifications.php?action=open&id=' . (int) $n['Id_Notification'];
                $n['Titre_Court'] = NotificationService::cleanTitle((string) $n['Titre']);
                unset($n['Id_Entreprise_Acheteuse']);
            }
            unset($n);

            $this->jsonResponse([
                'success'       => true,
                'count'         => $notificationService->unreadCount($mon_entreprise_id),
                'notifications' => $notifs,
            ]);
        }

        // Clic sur une notification : marquée comme lue puis redirection vers la commande.
        // GET sans CSRF assumé : effet limité à « marquer comme lue » une notification de sa
        // propre entreprise, idempotent.
        if ($action === 'open' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $cible = $notificationService->open(intval($_GET['id'] ?? 0), $mon_entreprise_id);
            header('Location: ../pages/' . ($cible ?? 'notifications_b2b.php'));
            exit();
        }

        // « Tout marquer comme lu » depuis le menu de la cloche : même logique que `open`
        // (GET sans jeton, limité aux notifications de sa propre entreprise, idempotent).
        if ($action === 'read_all' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $nb = $notificationService->markAllRead($mon_entreprise_id);
            $this->jsonResponse(['success' => true, 'marked' => $nb]);
        }

        if ($action === 'count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $count = $notificationService->unreadCount($mon_entreprise_id);
            $this->jsonResponse(['success' => true, 'count' => $count]);
        }

        if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $limit = intval($_GET['limit'] ?? 20);
            $notifs = $notificationService->latest($mon_entreprise_id, $limit);

            foreach ($notifs as &$n) {
                $n['Icone']   = NotificationService::ICONS[$n['Type_Notif']] ?? 'fa-bell';
                $n['Couleur'] = NotificationService::COLORS[$n['Type_Notif']] ?? '#6b7076';
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
