<?php

namespace App\Controllers;

use App\Application\B2B\OrderService;
use App\Application\B2B\B2BOrderService;
use App\Application\B2B\ShipmentService;
use App\Infrastructure\Persistence\OrderRepository;

/**
 * Contrôleur de pages/commandes_b2b.php — gestion complète des commandes B2B
 * (sélection fournisseur, création, validation/refus, préparation, expédition,
 * réception, chat par commande, timeline/historique).
 */
class CommandeB2BController extends Controller
{
    private OrderService $orderService;
    private B2BOrderService $b2bOrderService;
    private ShipmentService $shipmentService;

    /** Nom d'entreprise, mis en cache par requête (portée sur l'instance du contrôleur). */
    private array $nomEntrepriseCache = [];

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->orderService = new OrderService($pdo, new OrderRepository($pdo));
        $this->b2bOrderService = new B2BOrderService($pdo, new OrderRepository($pdo));
        $this->shipmentService = new ShipmentService($pdo, new OrderRepository($pdo));
    }

    public function index(): void
    {
        exigerPermission(peutGererB2B());

        $mon_entreprise_id = (int) $_SESSION['entreprise_id'];

        $success = '';
        $error   = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            [$success, $error] = $this->handlePost($mon_entreprise_id);
        }

        // ============================================================
        // DONNÉES D'AFFICHAGE
        // ============================================================

        // Sélection directe du vendeur via le bouton "Commander" du réseau B2B
        // (reseau_b2b.php?...  ->  commandes_b2b.php?onglet=passees&vendeur=ID)
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['vendeur'])) {
            $v = filter_input(INPUT_GET, 'vendeur', FILTER_VALIDATE_INT);
            if ($v && $v !== $mon_entreprise_id) {
                $stmt_v = $this->pdo->prepare("SELECT 1 FROM Entreprise WHERE Id_Entreprise = ?");
                $stmt_v->execute([$v]);
                if ($stmt_v->fetchColumn()) {
                    $_SESSION['selected_vendeur'] = $v;
                }
            }
        }

        // Produits du vendeur sélectionné (pour le formulaire)
        $produits_b2b     = [];
        $selected_vendeur = $_SESSION['selected_vendeur'] ?? null;
        if ($selected_vendeur) {
            $stmt = $this->pdo->prepare("
                SELECT Id_Produit, Nom_Produit, Prix_B2B, Quantite_En_Stock, Quantite_Min_B2B
                FROM Produit
                WHERE Id_Entreprise = ? AND En_Destockage_B2B = 1 AND Quantite_En_Stock > 0
                ORDER BY Nom_Produit
            ");
            $stmt->execute([$selected_vendeur]);
            $produits_b2b = $stmt->fetchAll();
        }

        // Liste des fournisseurs disponibles
        $stmt_f = $this->pdo->prepare("SELECT Id_Entreprise, Nom_Entreprise FROM Entreprise WHERE Id_Entreprise != ? ORDER BY Nom_Entreprise");
        $stmt_f->execute([$mon_entreprise_id]);
        $fournisseurs = $stmt_f->fetchAll();

        // Onglet actif
        $onglet = $_GET['onglet'] ?? 'recues';

        // Requête des commandes selon l'onglet
        if ($onglet === 'recues') {
            // Je suis le VENDEUR
            $sql = "
                SELECT c.*,
                       e.Nom_Entreprise AS Autre_Partie,
                       e.Tel_Entreprise,
                       e.Email_Entreprise
                FROM Commande_B2B c
                JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
                WHERE c.Id_Entreprise_Vendeuse = ?
                ORDER BY c.Est_Urgente DESC, c.Date_Commande DESC
            ";
        } else {
            // Je suis l'ACHETEUR
            $sql = "
                SELECT c.*,
                       e.Nom_Entreprise AS Autre_Partie,
                       e.Tel_Entreprise,
                       e.Email_Entreprise
                FROM Commande_B2B c
                JOIN Entreprise e ON c.Id_Entreprise_Vendeuse = e.Id_Entreprise
                WHERE c.Id_Entreprise_Acheteuse = ?
                ORDER BY c.Est_Urgente DESC, c.Date_Commande DESC
            ";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mon_entreprise_id]);
        $commandes = $stmt->fetchAll();

        $non_lues = $this->pdo->prepare("SELECT COUNT(*) FROM Notification_B2B WHERE Id_Entreprise_Destinataire = ? AND Est_Lue = FALSE");
        $non_lues->execute([$mon_entreprise_id]);
        $nb_non_lues = (int) $non_lues->fetchColumn();

        $this->render('commandes_b2b/index', [
            'pdo'               => $this->pdo,
            'mon_entreprise_id' => $mon_entreprise_id,
            'success'           => $success,
            'error'             => $error,
            'produits_b2b'      => $produits_b2b,
            'selected_vendeur'  => $selected_vendeur,
            'fournisseurs'      => $fournisseurs,
            'onglet'            => $onglet,
            'commandes'         => $commandes,
            'nb_non_lues'       => $nb_non_lues,
        ], 'Commandes B2B');
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handlePost(int $mon_entreprise_id): array
    {
        $action = $_POST['action'] ?? '';

        if ($action === 'choisir_vendeur') {
            $v = filter_input(INPUT_POST, 'vendeur_id', FILTER_VALIDATE_INT);
            if ($v && $v !== $mon_entreprise_id) {
                $_SESSION['selected_vendeur'] = $v;
            }
            $this->redirect('commandes_b2b.php?onglet=passees');
        }

        if ($action === 'reset_vendeur') {
            unset($_SESSION['selected_vendeur']);
            $this->redirect('commandes_b2b.php?onglet=passees');
        }

        if ($action === 'creer_commande') {
            return $this->handleCreerCommande($mon_entreprise_id);
        }

        if ($action === 'valider') {
            return $this->handleValider($mon_entreprise_id);
        }

        if ($action === 'en_preparation') {
            return $this->handleEnPreparation($mon_entreprise_id);
        }

        if ($action === 'marquer_prete') {
            return $this->handleMarquerPrete($mon_entreprise_id);
        }

        if ($action === 'expedier') {
            return $this->handleExpedier($mon_entreprise_id);
        }

        if ($action === 'livree') {
            return $this->handleLivree($mon_entreprise_id);
        }

        if ($action === 'refuser') {
            return $this->handleRefuser($mon_entreprise_id);
        }

        return ['', ''];
    }

    // ──────────────────────────────────────────────
    // 2. CRÉATION D'UNE COMMANDE B2B (Point 1, 2, 5)
    // ──────────────────────────────────────────────
    private function handleCreerCommande(int $mon_entreprise_id): array
    {
        try {
            $id_vendeur = (int) ($_POST['id_vendeur'] ?? 0);
            $commande = $this->b2bOrderService->create([
                'seller_id' => $id_vendeur,
                'items' => $_POST['items'] ?? [],
                'urgent' => isset($_POST['est_urgente']),
                'deadline_minutes' => (int) ($_POST['delai_minutes'] ?? 120),
                'mode' => $_POST['mode_retrait'] ?? 'livraison',
                'pickup_address' => $_POST['adresse_retrait'] ?? '',
                'delivery_address' => $_POST['adresse_livraison'] ?? '',
                'delivery_lat' => $_POST['lat_livraison'] ?? null,
                'delivery_lng' => $_POST['lng_livraison'] ?? null,
            ], $mon_entreprise_id);
            $numero = $commande['number'];
            $id_commande = $commande['id'];
            $est_urgente = $commande['urgent'];
            $delai_minutes = $commande['deadline_minutes'];
            $total_commande = $commande['total'];

            $mon_nom = $this->getNomEntrepriseLocal($mon_entreprise_id);
            $type_notif = $est_urgente ? 'commande_urgente' : 'nouvelle_commande';
            $titre_notif = $est_urgente
                ? "⚡ COMMANDE URGENTE de $mon_nom"
                : "Nouvelle commande de $mon_nom";
            $msg_notif = "Commande $numero — Total : " . number_format($total_commande, 0, ',', ' ') . " F.";
            if ($est_urgente) {
                $msg_notif .= " Délai de réponse requis : $delai_minutes minutes.";
            }
            creerNotificationB2b($this->pdo, $id_vendeur, $type_notif, $titre_notif, $msg_notif, $id_commande);

            return ["Commande $numero envoyée avec succès !" . ($est_urgente ? " 🔴 Marquée comme URGENTE." : ""), ''];
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['', $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────
    // 3. VALIDATION par le vendeur
    // ──────────────────────────────────────────────
    private function handleValider(int $mon_entreprise_id): array
    {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);
            $msg_vendeur = trim($_POST['message'] ?? '');

            $this->pdo->beginTransaction();

            // Vérifier accès (je suis le vendeur)
            $stmt = $this->pdo->prepare("
                SELECT * FROM Commande_B2B
                WHERE Id_Commande_B2B = ? AND Id_Entreprise_Vendeuse = ? AND Statut = 'en_attente'
                FOR UPDATE
            ");
            $stmt->execute([$id_commande, $mon_entreprise_id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                throw new \RuntimeException("Commande introuvable ou déjà traitée.");
            }

            // ⚠️ Contrôle de stock avant validation
            $erreurs_stock = verifierStockAvantValidation($this->pdo, $id_commande);
            if (!empty($erreurs_stock)) {
                $messages_erreur = array_map(fn($e) => $e['message'], $erreurs_stock);
                throw new \RuntimeException(
                    "Validation impossible — stock insuffisant :\n• "
                        . implode("\n• ", $messages_erreur)
                );
            }

            decrementerStockCommande($this->pdo, $id_commande);

            $this->pdo->prepare("
                UPDATE Commande_B2B
                SET Statut = 'validee', Message_Validation = ?, Date_Validation = NOW()
                WHERE Id_Commande_B2B = ?
            ")->execute([$msg_vendeur, $id_commande]);

            enregistrerHistoriqueCommande($this->pdo, $id_commande, 'en_attente', 'validee', $msg_vendeur, $mon_entreprise_id);

            $this->pdo->commit();

            $num = $cmd['Numero_Commande'];
            $nom_vendeur = $this->getNomEntrepriseLocal($mon_entreprise_id);
            creerNotificationB2b(
                $this->pdo,
                (int) $cmd['Id_Entreprise_Acheteuse'],
                'validation',
                "✅ Commande $num validée par $nom_vendeur",
                "Votre commande $num a été validée par $nom_vendeur. Elle va être mise en préparation." . ($msg_vendeur ? "\nMessage du vendeur : $msg_vendeur" : ''),
                $id_commande
            );

            return ["Commande validée et stock mis à jour. L'acheteur a été notifié.", ''];
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['', $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────
    // 3b. MISE EN PRÉPARATION par le vendeur (v3)
    // ──────────────────────────────────────────────
    private function handleEnPreparation(int $mon_entreprise_id): array
    {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);

            $cmd = $this->orderService->transitionForSeller(
                $id_commande,
                $mon_entreprise_id,
                'validee',
                'en_preparation'
            );

            $nom_vendeur = $this->getNomEntrepriseLocal($mon_entreprise_id);
            creerNotificationB2b(
                $this->pdo,
                (int) $cmd['Id_Entreprise_Acheteuse'],
                'preparation',
                "📦 Commande {$cmd['Numero_Commande']} en préparation",
                "Votre commande {$cmd['Numero_Commande']} est actuellement en cours de préparation par $nom_vendeur.",
                $id_commande
            );

            return ["Commande passée en préparation. L'acheteur a été notifié.", ''];
        } catch (\Exception $e) {
            return ['', $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────
    // 3c. COMMANDE PRÊTE par le vendeur (v3)
    // ──────────────────────────────────────────────
    private function handleMarquerPrete(int $mon_entreprise_id): array
    {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);

            $cmd = $this->orderService->transitionForSeller(
                $id_commande,
                $mon_entreprise_id,
                'en_preparation',
                'prete'
            );

            $nom_vendeur = $this->getNomEntrepriseLocal($mon_entreprise_id);
            creerNotificationB2b(
                $this->pdo,
                (int) $cmd['Id_Entreprise_Acheteuse'],
                'prete',
                "✅ Commande {$cmd['Numero_Commande']} prête à expédier",
                "Votre commande {$cmd['Numero_Commande']} est prête. Elle sera expédiée très prochainement par $nom_vendeur.",
                $id_commande
            );

            return ["Commande marquée comme prête. L'acheteur a été notifié.", ''];
        } catch (\Exception $e) {
            return ['', $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────
    // 4. EXPÉDITION + FACTURATION AUTOMATIQUE
    // ──────────────────────────────────────────────
    private function handleExpedier(int $mon_entreprise_id): array
    {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);
            $shipment = $this->shipmentService->ship($id_commande, $mon_entreprise_id, (string) ($_SESSION['username'] ?? ''), (int) ($_SESSION['user_id'] ?? 0));
            $cmd = $shipment['order'];
            $ref_facture = $shipment['number'];

            $nom_vendeur = $this->getNomEntrepriseLocal($mon_entreprise_id);
            creerNotificationB2b(
                $this->pdo,
                (int) $cmd['Id_Entreprise_Acheteuse'],
                'expedition',
                "🚚 Commande {$cmd['Numero_Commande']} expédiée",
                "Votre commande {$cmd['Numero_Commande']} a été expédiée par $nom_vendeur et est en cours de livraison. Facture N° $ref_facture générée.",
                $id_commande
            );

            return ["Commande expédiée. Facture N° $ref_facture et suivi logistique créés. L'acheteur a été notifié.", ''];
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['', $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────
    // 5. LIVRAISON CONFIRMÉE par l'acheteur
    // ──────────────────────────────────────────────
    private function handleLivree(int $mon_entreprise_id): array
    {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);

            $this->pdo->beginTransaction();

            // Verrouiller la ligne avant de statuer, comme handleValider() : sans ce FOR UPDATE
            // à l'intérieur de la transaction, deux confirmations concurrentes (double clic,
            // deux onglets) passeraient toutes les deux le contrôle de statut et dupliqueraient
            // l'incrément du score de fiabilité et les notifications.
            $stmt = $this->pdo->prepare("
                SELECT * FROM Commande_B2B
                WHERE Id_Commande_B2B = ? AND Id_Entreprise_Acheteuse = ? AND Statut = 'expediee'
                FOR UPDATE
            ");
            $stmt->execute([$id_commande, $mon_entreprise_id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                throw new \RuntimeException("Commande introuvable.");
            }

            $this->pdo->prepare("
                UPDATE Commande_B2B SET Statut = 'livree' WHERE Id_Commande_B2B = ?
            ")->execute([$id_commande]);

            // La logistique appartient au vendeur (Id_Entreprise = vendeur)
            $this->pdo->prepare("
                UPDATE Logistique
                SET Statut_Livraison = 'livree', Date_Livraison_Effectuee = NOW()
                WHERE Id_Commande_B2B = ? AND Id_Entreprise = ?
            ")->execute([$id_commande, $cmd['Id_Entreprise_Vendeuse']]);

            enregistrerHistoriqueCommande($this->pdo, $id_commande, 'expediee', 'livree', 'Réception confirmée par l\'acheteur', $mon_entreprise_id);

            // Score fiabilité vendeur +1
            $this->pdo->prepare("
                UPDATE Entreprise
                SET Score_Fiabilite = LEAST(100, Score_Fiabilite + 1),
                    Nombre_Commandes_Completees = Nombre_Commandes_Completees + 1
                WHERE Id_Entreprise = ?
            ")->execute([$cmd['Id_Entreprise_Vendeuse']]);

            $this->pdo->commit();

            $nom_acheteur = $this->getNomEntrepriseLocal($mon_entreprise_id);
            creerNotificationB2b(
                $this->pdo,
                (int) $cmd['Id_Entreprise_Vendeuse'],
                'reception',
                "🏆 Commande {$cmd['Numero_Commande']} livrée",
                "$nom_acheteur a confirmé l'arrivée de la commande {$cmd['Numero_Commande']}. Les produits sont maintenant disponibles dans sa réception d'approvisionnement.",
                $id_commande
            );

            creerNotificationB2b(
                $this->pdo,
                $mon_entreprise_id,
                'livraison',
                "✅ Réception de {$cmd['Numero_Commande']} confirmée",
                "La commande {$cmd['Numero_Commande']} est arrivée. Ouvrez Approvisionnement pour choisir les quantités à ajouter à votre stock.",
                $id_commande
            );

            return ["Arrivée confirmée ! Choisissez maintenant les quantités à ajouter dans Approvisionnement.", ''];
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['', $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────
    // 6. REFUS par le vendeur
    // ──────────────────────────────────────────────
    private function handleRefuser(int $mon_entreprise_id): array
    {
        try {
            $id_commande  = intval($_POST['id_commande'] ?? 0);
            $motif_refus  = trim($_POST['motif_refus'] ?? '');

            $cmd = $this->orderService->refuse($id_commande, $mon_entreprise_id, $motif_refus);

            $nom_vendeur = $this->getNomEntrepriseLocal($mon_entreprise_id);
            creerNotificationB2b(
                $this->pdo,
                (int) $cmd['Id_Entreprise_Acheteuse'],
                'refus',
                "❌ Commande {$cmd['Numero_Commande']} refusée par $nom_vendeur",
                "Votre commande {$cmd['Numero_Commande']} a été refusée par $nom_vendeur.\nMotif : $motif_refus",
                $id_commande
            );

            return ["Commande refusée. L'acheteur a été notifié avec le motif.", ''];
        } catch (\Exception $e) {
            return ['', $e->getMessage()];
        }
    }

    private function getNomEntrepriseLocal(int $id): string
    {
        if (!isset($this->nomEntrepriseCache[$id])) {
            $s = $this->pdo->prepare("SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
            $s->execute([$id]);
            $this->nomEntrepriseCache[$id] = $s->fetchColumn() ?: 'Inconnu';
        }
        return $this->nomEntrepriseCache[$id];
    }
}
