<?php

namespace App\Controllers;

/** Contrôleur de pages/notifications_b2b.php — liste des notifications B2B reçues. */
class NotificationController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutGererB2B());

        $mon_entreprise_id = (int) $_SESSION['entreprise_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all') {
            exigerCsrf();
            $this->pdo->prepare("UPDATE Notification_B2B SET Est_Lue = TRUE WHERE Id_Entreprise_Destinataire = ?")
                ->execute([$mon_entreprise_id]);
            $this->redirect('notifications_b2b.php');
        }

        // Paginer les notifications
        $page  = max(1, intval($_GET['p'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $total_stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Notification_B2B WHERE Id_Entreprise_Destinataire = ?");
        $total_stmt->execute([$mon_entreprise_id]);
        $total = (int) $total_stmt->fetchColumn();
        $total_pages = ceil($total / $limit);

        $stmt = $this->pdo->prepare("
            SELECT n.*, c.Numero_Commande
            FROM Notification_B2B n
            LEFT JOIN Commande_B2B c ON n.Id_Commande_B2B = c.Id_Commande_B2B
            WHERE n.Id_Entreprise_Destinataire = ?
            ORDER BY n.Date_Creation DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$mon_entreprise_id, $limit, $offset]);
        $notifications = $stmt->fetchAll();

        $non_lues = $this->pdo->prepare("SELECT COUNT(*) FROM Notification_B2B WHERE Id_Entreprise_Destinataire = ? AND Est_Lue = FALSE");
        $non_lues->execute([$mon_entreprise_id]);
        $nb_non_lues = (int) $non_lues->fetchColumn();

        $this->render('notifications_b2b/index', [
            'notifications'  => $notifications,
            'nb_non_lues'    => $nb_non_lues,
            'page'           => $page,
            'total_pages'    => $total_pages,
        ], 'Notifications B2B');
    }
}
