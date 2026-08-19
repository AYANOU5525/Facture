<?php
/**
 * api/chat_b2b.php — Endpoint AJAX pour la messagerie instantanée par commande B2B
 * 
 * Méthodes :
 *   GET  ?action=get_messages&commande_id=X[&since_id=Y]  → Messages (polling)
 *   POST ?action=send                                       → Envoyer un message
 *   POST ?action=mark_read&commande_id=X                   → Marquer comme lu
 *   GET  ?action=get_unread_count&commande_id=X            → Nombre de messages non lus
 * 
 * @package FactuPro B2B v2
 */

require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/b2b_helpers.php';
require_once '../vendor/autoload.php';

use App\Infrastructure\Persistence\ChatRepository;
use App\Application\B2B\ChatService;

// Toujours répondre en JSON
header('Content-Type: application/json; charset=UTF-8');

// Récupérer l'entreprise de l'utilisateur connecté
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$mon_entreprise_id = (int) $stmt->fetchColumn();
$chatRepository = new ChatRepository($pdo);
$chatService = new ChatService($chatRepository);

if (!$mon_entreprise_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Entreprise introuvable pour cet utilisateur.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================================
// ACTION : Récupérer les messages (polling)
// ============================================================
if ($action === 'get_messages' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $commande_id = intval($_GET['commande_id'] ?? 0);
    $since_id    = intval($_GET['since_id'] ?? 0); // Pour ne récupérer que les nouveaux
    
    if (!$commande_id) {
        echo json_encode(['error' => 'commande_id requis.']);
        exit;
    }

    // Vérifier que l'utilisateur a accès à cette commande
    if (!$chatService->canAccessCommand($commande_id, $mon_entreprise_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé à cette commande.']);
        exit;
    }

    $messages = $chatService->getMessages($commande_id, $mon_entreprise_id, $since_id);

    echo json_encode([
        'success'  => true,
        'messages' => $messages,
        'count'    => count($messages),
    ]);
    exit;
}

// ============================================================
// ACTION : Envoyer un message
// ============================================================
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $commande_id   = intval($_POST['commande_id'] ?? 0);
    $message_texte = trim($_POST['message'] ?? '');
    $type_message  = $_POST['type_message'] ?? 'texte';
    
    // Valider le type de message
    $types_valides = ['texte', 'negociation_qte', 'negociation_delai', 'confirmation_dispo', 'fichier'];
    if (!in_array($type_message, $types_valides)) {
        $type_message = 'texte';
    }

    if (!$commande_id) {
        echo json_encode(['error' => 'commande_id requis.']);
        exit;
    }

    if (!$chatService->canAccessCommand($commande_id, $mon_entreprise_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé à cette commande.']);
        exit;
    }

    if (empty($message_texte) && $type_message !== 'fichier') {
        echo json_encode(['error' => 'Le message ne peut pas être vide.']);
        exit;
    }

    // Gérer le fichier joint
    $fichier_path = null;
    $fichier_nom  = null;
    if ($type_message === 'fichier' && isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/chat_b2b/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $ext  = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        $exts_autorisees = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'docx', 'doc'];
        
        if (!in_array($ext, $exts_autorisees)) {
            echo json_encode(['error' => 'Type de fichier non autorisé. Formats : PDF, images, Excel, Word.']);
            exit;
        }
        
        if ($_FILES['fichier']['size'] > 10 * 1024 * 1024) { // 10 MB max
            echo json_encode(['error' => 'Fichier trop volumineux (max 10 Mo).']);
            exit;
        }

        $fichier_nom  = basename($_FILES['fichier']['name']);
        $unique_name  = 'cmd' . $commande_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $fichier_path = 'uploads/chat_b2b/' . $unique_name;
        
        if (!move_uploaded_file($_FILES['fichier']['tmp_name'], '../' . $fichier_path)) {
            echo json_encode(['error' => 'Erreur lors de l\'upload du fichier.']);
            exit;
        }
    }

    try {
        $id_message = $chatService->sendMessage(
            $commande_id,
            $mon_entreprise_id,
            $message_texte ?: null,
            $type_message,
            $fichier_path,
            $fichier_nom
        );

        // Notifier l'autre partie
        $commande = $chatRepository->findCommand($commande_id);
        if ($commande) {
            $destinataire_id = ($mon_entreprise_id === (int)$commande['Id_Entreprise_Acheteuse'])
                ? (int)$commande['Id_Entreprise_Vendeuse']
                : (int)$commande['Id_Entreprise_Acheteuse'];

            $mon_nom = $chatRepository->findEnterpriseName($mon_entreprise_id);
            creerNotificationB2b(
                $pdo,
                $destinataire_id,
                'nouveau_message',
                "Nouveau message sur {$commande['Numero_Commande']}",
                "$mon_nom vous a envoyé un message concernant la commande {$commande['Numero_Commande']}.",
                $commande_id
            );
        }

        echo json_encode([
            'success'    => true,
            'id_message' => $id_message,
            'message'    => 'Message envoyé.',
        ]);

    } catch (Exception $e) {
        error_log('[FactuPro] Chat send failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erreur serveur lors de l’envoi du message.']);
    }
    exit;
}

// ============================================================
// ACTION : Marquer les messages comme lus
// ============================================================
if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $commande_id = intval($_POST['commande_id'] ?? $_GET['commande_id'] ?? 0);
    
    if (!$commande_id) {
        echo json_encode(['error' => 'commande_id requis.']);
        exit;
    }

    $chatService->markAsRead($commande_id, $mon_entreprise_id);
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// ACTION : Nombre de messages non lus (badge cloche)
// ============================================================
if ($action === 'get_unread_count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $count = $chatService->countUnread($mon_entreprise_id);

    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

// ============================================================
// Action inconnue
// ============================================================
http_response_code(400);
echo json_encode(['error' => "Action '$action' inconnue."]);
exit;

