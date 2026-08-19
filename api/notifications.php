<?php
/**
 * api/notifications.php — Endpoint AJAX pour les notifications B2B
 * 
 * Méthodes :
 *   GET  ?action=count          → Nombre de notifications non lues
 *   GET  ?action=list[&limit=N] → Liste des dernières notifications
 *   POST ?action=mark_read&id=X → Marquer une notification comme lue
 *   POST ?action=mark_all_read  → Tout marquer comme lu
 * 
 * @package FactuPro B2B v2
 */

require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use App\Application\B2B\NotificationService;
use App\Infrastructure\Persistence\NotificationRepository;

// Toujours répondre en JSON
header('Content-Type: application/json; charset=UTF-8');

// Récupérer l'entreprise de l'utilisateur connecté
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$mon_entreprise_id = (int) $stmt->fetchColumn();
$notificationService = new NotificationService(new NotificationRepository($pdo));

if (!$mon_entreprise_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Entreprise introuvable.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================================
// ACTION : Nombre de notifications non lues (pour badge navbar)
// ============================================================
if ($action === 'count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $count = $notificationService->unreadCount($mon_entreprise_id);
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

// ============================================================
// ACTION : Liste des notifications
// ============================================================
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = intval($_GET['limit'] ?? 20);
    $notifs = $notificationService->latest($mon_entreprise_id, $limit);

    // Formater les dates et ajouter les icônes
    $icones = [
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
    $couleurs = [
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

    foreach ($notifs as &$n) {
        $n['Icone']    = $icones[$n['Type_Notif']] ?? 'fa-bell';
        $n['Couleur']  = $couleurs[$n['Type_Notif']] ?? '#6b7076';
    }
    unset($n);

    echo json_encode(['success' => true, 'notifications' => $notifs]);
    exit;
}

// ============================================================
// ACTION : Marquer une notification comme lue
// ============================================================
if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id_notif = intval($_POST['id'] ?? 0);
    
    if (!$id_notif) {
        echo json_encode(['error' => 'ID notification requis.']);
        exit;
    }

    $notificationService->markRead($id_notif, $mon_entreprise_id);
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// ACTION : Tout marquer comme lu
// ============================================================
if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $nb = $notificationService->markAllRead($mon_entreprise_id);
    echo json_encode(['success' => true, 'marked' => $nb]);
    exit;
}

// ============================================================
// Action inconnue
// ============================================================
http_response_code(400);
echo json_encode(['error' => "Action '$action' inconnue."]);
exit;
