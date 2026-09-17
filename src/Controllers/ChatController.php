<?php

namespace App\Controllers;

use App\Application\B2B\ChatService;
use App\Infrastructure\Persistence\ChatRepository;

/** Contrôleur de api/chat_b2b.php — messagerie instantanée par commande B2B (polling). */
class ChatController extends Controller
{
    private ChatRepository $chatRepository;
    private ChatService $chatService;
    private int $mon_entreprise_id;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);

        if (!peutAccederB2B()) {
            $this->jsonResponse(['success' => false, 'error' => 'Accès refusé.'], 403);
        }

        $this->chatRepository = new ChatRepository($pdo);
        $this->chatService = new ChatService($this->chatRepository);

        $this->mon_entreprise_id = (int) ($_SESSION['entreprise_id'] ?? 0);

        if (!$this->mon_entreprise_id) {
            $this->jsonResponse(['error' => 'Entreprise introuvable pour cet utilisateur.'], 403);
        }
    }

    public function handle(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        if ($action === 'get_messages' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->getMessages();
        }

        if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->send();
        }

        if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->markRead();
        }

        if ($action === 'get_unread_count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->getUnreadCount();
        }

        $this->jsonResponse(['error' => "Action '$action' inconnue."], 400);
    }

    private function getMessages(): void
    {
        $commande_id = intval($_GET['commande_id'] ?? 0);
        $since_id    = intval($_GET['since_id'] ?? 0); // Pour ne récupérer que les nouveaux

        if (!$commande_id) {
            $this->jsonResponse(['error' => 'commande_id requis.']);
        }

        if (!$this->chatService->canAccessCommand($commande_id, $this->mon_entreprise_id)) {
            $this->jsonResponse(['error' => 'Accès non autorisé à cette commande.'], 403);
        }

        $messages = $this->chatService->getMessages($commande_id, $this->mon_entreprise_id, $since_id);

        $this->jsonResponse([
            'success'  => true,
            'messages' => $messages,
            'count'    => count($messages),
        ]);
    }

    private function send(): void
    {
        exigerCsrf();

        $commande_id   = intval($_POST['commande_id'] ?? 0);
        $message_texte = trim($_POST['message'] ?? '');
        $type_message  = $_POST['type_message'] ?? 'texte';

        // Valider le type de message
        $types_valides = ['texte', 'negociation_qte', 'negociation_delai', 'confirmation_dispo', 'fichier'];
        if (!in_array($type_message, $types_valides)) {
            $type_message = 'texte';
        }

        if (!$commande_id) {
            $this->jsonResponse(['error' => 'commande_id requis.']);
        }

        if (!$this->chatService->canAccessCommand($commande_id, $this->mon_entreprise_id)) {
            $this->jsonResponse(['error' => 'Accès non autorisé à cette commande.'], 403);
        }

        if (empty($message_texte) && $type_message !== 'fichier') {
            $this->jsonResponse(['error' => 'Le message ne peut pas être vide.']);
        }

        // Gérer le fichier joint
        $fichier_path = null;
        $fichier_nom  = null;
        if ($type_message === 'fichier' && isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/chat_b2b/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
            $exts_autorisees = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'docx', 'doc'];

            if (!in_array($ext, $exts_autorisees)) {
                $this->jsonResponse(['error' => 'Type de fichier non autorisé. Formats : PDF, images, Excel, Word.']);
            }

            if ($_FILES['fichier']['size'] > 10 * 1024 * 1024) { // 10 MB max
                $this->jsonResponse(['error' => 'Fichier trop volumineux (max 10 Mo).']);
            }

            $fichier_nom  = basename($_FILES['fichier']['name']);
            $unique_name  = 'cmd' . $commande_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $fichier_path = 'uploads/chat_b2b/' . $unique_name;

            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], '../' . $fichier_path)) {
                $this->jsonResponse(['error' => "Erreur lors de l'upload du fichier."]);
            }
        }

        try {
            $id_message = $this->chatService->sendMessage(
                $commande_id,
                $this->mon_entreprise_id,
                $message_texte ?: null,
                $type_message,
                $fichier_path,
                $fichier_nom
            );

            // Notifier l'autre partie
            $commande = $this->chatRepository->findCommand($commande_id);
            if ($commande) {
                $destinataire_id = ($this->mon_entreprise_id === (int) $commande['Id_Entreprise_Acheteuse'])
                    ? (int) $commande['Id_Entreprise_Vendeuse']
                    : (int) $commande['Id_Entreprise_Acheteuse'];

                $mon_nom = $this->chatRepository->findEnterpriseName($this->mon_entreprise_id);
                creerNotificationB2b(
                    $this->pdo,
                    $destinataire_id,
                    'nouveau_message',
                    "Nouveau message sur {$commande['Numero_Commande']}",
                    "$mon_nom vous a envoyé un message concernant la commande {$commande['Numero_Commande']}.",
                    $commande_id
                );
            }

            $this->jsonResponse([
                'success'    => true,
                'id_message' => $id_message,
                'message'    => 'Message envoyé.',
            ]);
        } catch (\Exception $e) {
            error_log('[FactuPro] Chat send failed: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Erreur serveur lors de l’envoi du message.'], 500);
        }
    }

    private function markRead(): void
    {
        exigerCsrf();

        $commande_id = intval($_POST['commande_id'] ?? $_GET['commande_id'] ?? 0);

        if (!$commande_id) {
            $this->jsonResponse(['error' => 'commande_id requis.']);
        }

        $this->chatService->markAsRead($commande_id, $this->mon_entreprise_id);
        $this->jsonResponse(['success' => true]);
    }

    private function getUnreadCount(): void
    {
        $count = $this->chatService->countUnread($this->mon_entreprise_id);
        $this->jsonResponse(['success' => true, 'count' => $count]);
    }
}
