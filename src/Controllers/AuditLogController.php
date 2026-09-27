<?php

namespace App\Controllers;

/** Contrôleur de pages/audit_log.php — consultation du journal d'audit de sécurité (proprio uniquement). */
class AuditLogController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutGererParametres());

        $entreprise_id = (int) $_SESSION['entreprise_id'];

        $action_filtree = trim((string) ($_GET['action'] ?? ''));

        $page   = max(1, (int) ($_GET['p'] ?? 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;

        $where  = 'WHERE l.Id_Entreprise = ?';
        $params = [$entreprise_id];
        if ($action_filtree !== '') {
            $where .= ' AND l.Action = ?';
            $params[] = $action_filtree;
        }

        $total_stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Audit_Log l $where");
        $total_stmt->execute($params);
        $total = (int) $total_stmt->fetchColumn();
        $total_pages = (int) ceil($total / $limit);

        $stmt = $this->pdo->prepare("
            SELECT l.*, u.Nom_Utilisateur
            FROM Audit_Log l
            LEFT JOIN Utilisateur u ON u.Id_Utilisateur = l.Id_Utilisateur
            $where
            ORDER BY l.Created_At DESC, l.Id_Log DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([...$params, $limit, $offset]);
        $entries = $stmt->fetchAll();

        $actions_stmt = $this->pdo->prepare("SELECT DISTINCT Action FROM Audit_Log WHERE Id_Entreprise = ? ORDER BY Action");
        $actions_stmt->execute([$entreprise_id]);
        $actions_disponibles = $actions_stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->render('audit_log/index', [
            'entries'              => $entries,
            'page'                 => $page,
            'total_pages'          => $total_pages,
            'total'                => $total,
            'action_filtree'       => $action_filtree,
            'actions_disponibles'  => $actions_disponibles,
        ], 'Journal d\'audit');
    }
}
