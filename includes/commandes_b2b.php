<?php

/**
 * commandes_b2b.php — Gestion complète des commandes B2B
 *
 * Fonctionnalités v2 :
 *  - Commandes urgentes avec compte à rebours (Point 1)
 *  - Mode retrait (livraison / retrait sur place) (Point 2)
 *  - Lignes relationnelles (Ligne_Commande_B2B) (Point 5)
 *  - Contrôle de stock avant validation (Point 6)
 *  - Notifications automatiques (Point 8)
 *  - Chat intégré par commande (Point 7)
 *  - Interface modernisée avec timeline (Point 9)
 *
 * @package FactuPro B2B v2
 */

require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/b2b_helpers.php';

$page_title = "Commandes B2B";
include_once '../includes/header.php';

// Récupérer l'entreprise de l'utilisateur connecté
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$mon_entreprise_id = (int) $stmt->fetchColumn();

$success = '';
$error   = '';
$warnings = []; // Alertes non bloquantes (ex: stock faible)

// TRAITEMENT DES FORMULAIRES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    // 1. SÉLECTION DU VENDEUR (via SESSION)
    if ($action === 'choisir_vendeur') {
        $v = filter_input(INPUT_POST, 'vendeur_id', FILTER_VALIDATE_INT);
        if ($v && $v !== $mon_entreprise_id) {
            $_SESSION['selected_vendeur'] = $v;
        }
        header('Location: commandes_b2b.php?onglet=passees');
        exit;
    }

    if ($action === 'reset_vendeur') {
        unset($_SESSION['selected_vendeur']);
        header('Location: commandes_b2b.php?onglet=passees');
        exit;
    }

    // ──────────────────────────────────────────────
    // 2. CRÉATION D'UNE COMMANDE B2B (Point 1, 2, 5)
    // ──────────────────────────────────────────────
    if ($action === 'creer_commande') {
        try {
            $id_vendeur    = intval($_POST['id_vendeur'] ?? 0);
            $items         = $_POST['items'] ?? [];
            $est_urgente   = isset($_POST['est_urgente']) ? 1 : 0;
            $delai_minutes = intval($_POST['delai_minutes'] ?? 120);
            $mode_retrait  = in_array($_POST['mode_retrait'] ?? '', ['livraison', 'retrait_place'])
                ? $_POST['mode_retrait']
                : 'livraison';
            $adresse_retrait = ($mode_retrait === 'retrait_place')
                ? trim($_POST['adresse_retrait'] ?? '')
                : null;

            if (!$id_vendeur || $id_vendeur === $mon_entreprise_id) {
                throw new InvalidArgumentException("Fournisseur invalide.");
            }
            if (empty($items)) {
                throw new InvalidArgumentException("Veuillez sélectionner au moins un produit.");
            }
            if ($delai_minutes < 30 || $delai_minutes > 10080) { // 30 min → 1 semaine
                $delai_minutes = 120;
            }

            $pdo->beginTransaction();

            // Calculer le total et préparer les lignes
            $total_commande = 0;
            $lignes_a_inserer = [];

            foreach ($items as $id_produit => $quantite) {
                $quantite = intval($quantite);
                if ($quantite <= 0) {
                    continue;
                }

                // Vérifier le produit (avec verrou pour éviter les race conditions)
                $stmt = $pdo->prepare("
                    SELECT Id_Produit, Nom_Produit, Prix_B2B, Quantite_En_Stock, En_Destockage_B2B
                    FROM Produit
                    WHERE Id_Produit = ? AND Id_Entreprise = ? AND En_Destockage_B2B = 1
                    FOR UPDATE
                ");
                $stmt->execute([$id_produit, $id_vendeur]);
                $prod = $stmt->fetch();

                if (!$prod) {
                    throw new RuntimeException("Produit ID $id_produit indisponible ou non proposé en B2B.");
                }
                if ($prod['Quantite_En_Stock'] < $quantite) {
                    throw new RuntimeException(
                        "Stock insuffisant pour \"{$prod['Nom_Produit']}\" : "
                            . "disponible {$prod['Quantite_En_Stock']}, demandé $quantite."
                    );
                }

                $sous_total = $prod['Prix_B2B'] * $quantite;
                $total_commande += $sous_total;
                $lignes_a_inserer[] = [
                    'id_produit'   => $id_produit,
                    'nom'          => $prod['Nom_Produit'],
                    'quantite'     => $quantite,
                    'prix'         => $prod['Prix_B2B'],
                    'sous_total'   => $sous_total,
                ];
            }

            if (empty($lignes_a_inserer)) {
                throw new InvalidArgumentException("Aucune quantité saisie.");
            }

            // Calculer la date limite si urgente
            $date_limite = null;
            if ($est_urgente) {
                $date_limite = date('Y-m-d H:i:s', strtotime("+{$delai_minutes} minutes"));
            }

            // Insérer la commande principale
            $numero = 'CMD-' . date('Ymd') . '-' . random_int(1000, 9999);
            $stmt = $pdo->prepare("
                INSERT INTO Commande_B2B 
                    (Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse,
                     Montant_Total, Statut, Est_Urgente, Delai_Reponse_Minutes,
                     Date_Limite_Reponse, Mode_Retrait, Adresse_Retrait, Date_Commande)
                VALUES (?, ?, ?, ?, 'en_attente', ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $numero,
                $mon_entreprise_id,
                $id_vendeur,
                $total_commande,
                $est_urgente,
                $delai_minutes,
                $date_limite,
                $mode_retrait,
                $adresse_retrait
            ]);
            $id_commande = (int) $pdo->lastInsertId();

            // Insérer les lignes dans la table normalisée
            $ins_ligne = $pdo->prepare("
                INSERT INTO Ligne_Commande_B2B 
                    (Id_Commande_B2B, Id_Produit, Nom_Produit, Quantite, Prix_Unitaire, Sous_Total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($lignes_a_inserer as $ligne) {
                $ins_ligne->execute([
                    $id_commande,
                    $ligne['id_produit'],
                    $ligne['nom'],
                    $ligne['quantite'],
                    $ligne['prix'],
                    $ligne['sous_total']
                ]);
            }

            $pdo->commit();

            // Notifications au vendeur
            $mon_nom = getNomEntrepriseLocal($pdo, $mon_entreprise_id);
            $type_notif = $est_urgente ? 'commande_urgente' : 'nouvelle_commande';
            $titre_notif = $est_urgente
                ? "⚡ COMMANDE URGENTE de $mon_nom"
                : "Nouvelle commande de $mon_nom";
            $msg_notif = "Commande $numero — Total : " . number_format($total_commande, 0, ',', ' ') . " F.";
            if ($est_urgente) {
                $msg_notif .= " Délai de réponse requis : $delai_minutes minutes.";
            }
            creerNotificationB2b($pdo, $id_vendeur, $type_notif, $titre_notif, $msg_notif, $id_commande);

            $success = "Commande $numero envoyée avec succès !" . ($est_urgente ? " 🔴 Marquée comme URGENTE." : "");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }

    // ──────────────────────────────────────────────
    // 3. VALIDATION par le vendeur
    // ──────────────────────────────────────────────
    elseif ($action === 'valider') {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);
            $msg_vendeur = trim($_POST['message'] ?? '');

            $pdo->beginTransaction();

            // Vérifier accès (je suis le vendeur)
            $stmt = $pdo->prepare("
                SELECT * FROM Commande_B2B
                WHERE Id_Commande_B2B = ? AND Id_Entreprise_Vendeuse = ? AND Statut = 'en_attente'
                FOR UPDATE
            ");
            $stmt->execute([$id_commande, $mon_entreprise_id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                throw new RuntimeException("Commande introuvable ou déjà traitée.");
            }

            // ⚠️ Contrôle de stock avant validation
            $erreurs_stock = verifierStockAvantValidation($pdo, $id_commande);
            if (!empty($erreurs_stock)) {
                $messages_erreur = array_map(fn($e) => $e['message'], $erreurs_stock);
                throw new RuntimeException(
                    "Validation impossible — stock insuffisant :\n• "
                        . implode("\n• ", $messages_erreur)
                );
            }

            // Décrémenter le stock
            decrementerStockCommande($pdo, $id_commande);

            // Mettre à jour la commande
            $pdo->prepare("
                UPDATE Commande_B2B
                SET Statut = 'validee', Message_Validation = ?, Date_Validation = NOW()
                WHERE Id_Commande_B2B = ?
            ")->execute([$msg_vendeur, $id_commande]);

            // Historique
            enregistrerHistoriqueCommande($pdo, $id_commande, 'en_attente', 'validee', $msg_vendeur, $mon_entreprise_id);

            $pdo->commit();

            // Notifier l'acheteur
            $num = $cmd['Numero_Commande'];
            $nom_vendeur = getNomEntrepriseLocal($pdo, $mon_entreprise_id);
            creerNotificationB2b(
                $pdo,
                (int)$cmd['Id_Entreprise_Acheteuse'],
                'validation',
                "✅ Commande $num validée par $nom_vendeur",
                "Votre commande $num a été validée par $nom_vendeur. Elle va être mise en préparation." . ($msg_vendeur ? "\nMessage du vendeur : $msg_vendeur" : ''),
                $id_commande
            );

            $success = "Commande validée et stock mis à jour. L'acheteur a été notifié.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }

    // ──────────────────────────────────────────────
    // 3b. MISE EN PRÉPARATION par le vendeur (v3)
    // ──────────────────────────────────────────────
    elseif ($action === 'en_preparation') {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT * FROM Commande_B2B
                WHERE Id_Commande_B2B = ? AND Id_Entreprise_Vendeuse = ? AND Statut = 'validee'
            ");
            $stmt->execute([$id_commande, $mon_entreprise_id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                throw new RuntimeException("Commande introuvable ou statut incorrect (attendu : validée).");
            }

            $pdo->prepare("
                UPDATE Commande_B2B SET Statut = 'en_preparation' WHERE Id_Commande_B2B = ?
            ")->execute([$id_commande]);

            enregistrerHistoriqueCommande($pdo, $id_commande, 'validee', 'en_preparation', '', $mon_entreprise_id);

            $nom_vendeur = getNomEntrepriseLocal($pdo, $mon_entreprise_id);
            creerNotificationB2b(
                $pdo,
                (int)$cmd['Id_Entreprise_Acheteuse'],
                'preparation',
                "📦 Commande {$cmd['Numero_Commande']} en préparation",
                "Votre commande {$cmd['Numero_Commande']} est actuellement en cours de préparation par $nom_vendeur.",
                $id_commande
            );

            $success = "Commande passée en préparation. L'acheteur a été notifié.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // ──────────────────────────────────────────────
    // 3c. COMMANDE PRÊTE par le vendeur (v3)
    // ──────────────────────────────────────────────
    elseif ($action === 'marquer_prete') {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT * FROM Commande_B2B
                WHERE Id_Commande_B2B = ? AND Id_Entreprise_Vendeuse = ? AND Statut = 'en_preparation'
            ");
            $stmt->execute([$id_commande, $mon_entreprise_id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                throw new RuntimeException("Commande introuvable ou statut incorrect (attendu : en_preparation).");
            }

            $pdo->prepare("
                UPDATE Commande_B2B SET Statut = 'prete' WHERE Id_Commande_B2B = ?
            ")->execute([$id_commande]);

            enregistrerHistoriqueCommande($pdo, $id_commande, 'en_preparation', 'prete', '', $mon_entreprise_id);

            $nom_vendeur = getNomEntrepriseLocal($pdo, $mon_entreprise_id);
            creerNotificationB2b(
                $pdo,
                (int)$cmd['Id_Entreprise_Acheteuse'],
                'prete',
                "✅ Commande {$cmd['Numero_Commande']} prête à expédier",
                "Votre commande {$cmd['Numero_Commande']} est prête. Elle sera expédiée très prochainement par $nom_vendeur.",
                $id_commande
            );

            $success = "Commande marquée comme prête. L'acheteur a été notifié.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // ──────────────────────────────────────────────
    // 4. EXPÉDITION + FACTURATION AUTOMATIQUE
    // ──────────────────────────────────────────────
    elseif ($action === 'expedier') {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);

            $pdo->beginTransaction();

            // Vérifier accès — doit être 'prete' (ou 'validee' pour compatibilité ascendante)
            $stmt = $pdo->prepare("
                SELECT c.*, e.Nom_Entreprise AS Nom_Acheteur
                FROM Commande_B2B c
                JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
                WHERE c.Id_Commande_B2B = ? AND c.Id_Entreprise_Vendeuse = ?
                  AND c.Statut IN ('prete', 'validee', 'en_preparation')
                FOR UPDATE
            ");
            $stmt->execute([$id_commande, $mon_entreprise_id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                throw new RuntimeException("Commande introuvable ou statut incorrect.");
            }

            $ancien_statut = $cmd['Statut'];

            // Générer la référence facture
            $ref_facture = 'FAC-B2B-' . date('Ymd') . '-' . random_int(100, 999);

            // Créer la Vente (pour l'historique et les stats du dashboard)
            $lignes = getLignesCommande($pdo, $id_commande);
            $articles_json = json_encode($lignes, JSON_UNESCAPED_UNICODE);

            $ins_vente = $pdo->prepare("
                INSERT INTO Vente 
                    (Numero_Vente, Id_Entreprise, Nom_Client, Date_Vente, Montant_Total, Type_Vente, Articles_JSON)
                VALUES (?, ?, ?, NOW(), ?, 'b2b', ?)
            ");
            $ins_vente->execute([
                $ref_facture,
                $mon_entreprise_id,
                $cmd['Nom_Acheteur'],
                $cmd['Montant_Total'],
                $articles_json
            ]);
            $id_vente = (int) $pdo->lastInsertId();

            // Créer la Facture
            $pdo->prepare("
                INSERT INTO Facture
                    (Id_Vente, Id_Commande_B2B, Numero_Facture, Date_Echeance, Montant_HT, Montant_TTC, Id_Entreprise)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, ?)
            ")->execute([
                $id_vente,
                $id_commande,
                $ref_facture,
                $cmd['Montant_Total'] * 0.8,
                $cmd['Montant_Total'],
                $mon_entreprise_id
            ]);
            $id_facture = (int) $pdo->lastInsertId();

            // Créer le suivi Logistique
            $pdo->prepare("
                INSERT INTO Logistique 
                    (Id_Vente, Id_Commande_B2B, Id_Facture, Statut_Livraison, Id_Entreprise)
                VALUES (?, ?, ?, 'traitement', ?)
            ")->execute([$id_vente, $id_commande, $id_facture, $mon_entreprise_id]);

            // Mettre à jour la commande
            $pdo->prepare("
                UPDATE Commande_B2B
                SET Statut = 'expediee', Date_Expedition_Reelle = NOW()
                WHERE Id_Commande_B2B = ?
            ")->execute([$id_commande]);

            // Historique
            enregistrerHistoriqueCommande($pdo, $id_commande, $ancien_statut, 'expediee', "Facture $ref_facture générée", $mon_entreprise_id);

            $pdo->commit();

            // Notifier l'acheteur
            $nom_vendeur = getNomEntrepriseLocal($pdo, $mon_entreprise_id);
            creerNotificationB2b(
                $pdo,
                (int)$cmd['Id_Entreprise_Acheteuse'],
                'expedition',
                "🚚 Commande {$cmd['Numero_Commande']} expédiée",
                "Votre commande {$cmd['Numero_Commande']} a été expédiée par $nom_vendeur et est en cours de livraison. Facture N° $ref_facture générée.",
                $id_commande
            );

            $success = "Commande expédiée. Facture N° $ref_facture et suivi logistique créés. L'acheteur a été notifié.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }

    // ──────────────────────────────────────────────
    // 5. LIVRAISON CONFIRMÉE par l'acheteur
    // ──────────────────────────────────────────────
    elseif ($action === 'livree') {
        try {
            $id_commande = intval($_POST['id_commande'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT * FROM Commande_B2B
                WHERE Id_Commande_B2B = ? AND Id_Entreprise_Acheteuse = ? AND Statut = 'expediee'
            ");
            $stmt->execute([$id_commande, $mon_entreprise_id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                throw new RuntimeException("Commande introuvable.");
            }

            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE Commande_B2B SET Statut = 'livree' WHERE Id_Commande_B2B = ?
            ")->execute([$id_commande]);

            $pdo->prepare("
                UPDATE Logistique
                SET Statut_Livraison = 'livree', Date_Livraison_Effectuee = NOW()
                WHERE Id_Commande_B2B = ? AND Id_Entreprise = ?
            ")->execute([$id_commande, $mon_entreprise_id]);

            // Historique
            enregistrerHistoriqueCommande($pdo, $id_commande, 'expediee', 'livree', 'Réception confirmée par l\'acheteur', $mon_entreprise_id);

            // Score fiabilité vendeur +1
            $pdo->prepare("
                UPDATE Entreprise
                SET Score_Fiabilite = LEAST(100, Score_Fiabilite + 1),
                    Nombre_Commandes_Completees = Nombre_Commandes_Completees + 1
                WHERE Id_Entreprise = ?
            ")->execute([$cmd['Id_Entreprise_Vendeuse']]);

            $pdo->commit();

            // Notifier le vendeur que la livraison est confirmée
            $nom_acheteur = getNomEntrepriseLocal($pdo, $mon_entreprise_id);
            creerNotificationB2b(
                $pdo,
                (int)$cmd['Id_Entreprise_Vendeuse'],
                'reception',
                "🏆 Commande {$cmd['Numero_Commande']} livrée",
                "$nom_acheteur a confirmé l'arrivée de la commande {$cmd['Numero_Commande']}. Les produits sont maintenant disponibles dans sa réception d'approvisionnement.",
                $id_commande
            );

            // Notifier aussi l'acheteur (confirmation)
            creerNotificationB2b(
                $pdo,
                $mon_entreprise_id,
                'livraison',
                "✅ Réception de {$cmd['Numero_Commande']} confirmée",
                "La commande {$cmd['Numero_Commande']} est arrivée. Ouvrez Approvisionnement pour choisir les quantités à ajouter à votre stock.",
                $id_commande
            );

            $success = "Arrivée confirmée ! Choisissez maintenant les quantités à ajouter dans Approvisionnement.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }

    // ──────────────────────────────────────────────
    // 6. REFUS par le vendeur
    // ──────────────────────────────────────────────
    elseif ($action === 'refuser') {
        try {
            $id_commande  = intval($_POST['id_commande'] ?? 0);
            $motif_refus  = trim($_POST['motif_refus'] ?? '');

            if (empty($motif_refus)) {
                throw new InvalidArgumentException("Veuillez indiquer un motif de refus.");
            }

            $stmt = $pdo->prepare("
                SELECT * FROM Commande_B2B
                WHERE Id_Commande_B2B = ? AND Id_Entreprise_Vendeuse = ? AND Statut = 'en_attente'
            ");
            $stmt->execute([$id_commande, $mon_entreprise_id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                throw new RuntimeException("Commande introuvable.");
            }

            $pdo->prepare("
                UPDATE Commande_B2B
                SET Statut = 'refusee', Message_Validation = ?, Date_Validation = NOW()
                WHERE Id_Commande_B2B = ?
            ")->execute([$motif_refus, $id_commande]);

            enregistrerHistoriqueCommande($pdo, $id_commande, 'en_attente', 'refusee', $motif_refus, $mon_entreprise_id);

            $nom_vendeur = getNomEntrepriseLocal($pdo, $mon_entreprise_id);
            creerNotificationB2b(
                $pdo,
                (int)$cmd['Id_Entreprise_Acheteuse'],
                'refus',
                "❌ Commande {$cmd['Numero_Commande']} refusée par $nom_vendeur",
                "Votre commande {$cmd['Numero_Commande']} a été refusée par $nom_vendeur.\nMotif : $motif_refus",
                $id_commande
            );

            $success = "Commande refusée. L'acheteur a été notifié avec le motif.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// ============================================================
// DONNÉES D'AFFICHAGE
// ============================================================

// Produits du vendeur sélectionné (pour le formulaire)
$produits_b2b     = [];
$selected_vendeur = $_SESSION['selected_vendeur'] ?? null;
if ($selected_vendeur) {
    $stmt = $pdo->prepare("
        SELECT Id_Produit, Nom_Produit, Prix_B2B, Quantite_En_Stock, Quantite_Min_B2B
        FROM Produit
        WHERE Id_Entreprise = ? AND En_Destockage_B2B = 1 AND Quantite_En_Stock > 0
        ORDER BY Nom_Produit
    ");
    $stmt->execute([$selected_vendeur]);
    $produits_b2b = $stmt->fetchAll();
}

// Liste des fournisseurs disponibles
$stmt_f = $pdo->prepare("SELECT Id_Entreprise, Nom_Entreprise FROM Entreprise WHERE Id_Entreprise != ? ORDER BY Nom_Entreprise");
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
$stmt = $pdo->prepare($sql);
$stmt->execute([$mon_entreprise_id]);
$commandes = $stmt->fetchAll();

// Fonction locale : nom entreprise
function getNomEntrepriseLocal(PDO $pdo, int $id): string
{
    static $cache = [];
    if (!isset($cache[$id])) {
        $s = $pdo->prepare("SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
        $s->execute([$id]);
        $cache[$id] = $s->fetchColumn() ?: 'Inconnu';
    }
    return $cache[$id];
}
?>

<!-- ============================================================
     VUE — Interface Commandes B2B v2
     ============================================================ -->
<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-shipping-fast"></i> Gestion des Commandes B2B</h1>
        <p>Gérez vos achats et ventes inter-entreprises</p>
    </div>

    <!-- Alertes -->
    <?php if ($success): ?>
        <div class="alert alert-success b2b-alert">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger b2b-alert" style="white-space: pre-line;">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Onglets -->
    <div class="b2b-tabs">
        <a href="?onglet=recues"
            class="b2b-tab <?= $onglet === 'recues' ? 'active' : '' ?>">
            <i class="fas fa-inbox"></i> Reçues
            <span class="tab-subtitle">Je suis vendeur</span>
        </a>
        <a href="?onglet=passees"
            class="b2b-tab <?= $onglet === 'passees' ? 'active' : '' ?>">
            <i class="fas fa-shopping-bag"></i> Passées
            <span class="tab-subtitle">Je suis acheteur</span>
        </a>
    </div>

    <div class="row" style="display:flex; gap:25px; align-items:flex-start;">

        <!-- ────────────────────────────────
             PANNEAU NOUVELLE COMMANDE (Acheteur)
             ──────────────────────────────── -->
        <?php if ($onglet === 'passees'): ?>
            <div style="width: 360px; flex-shrink: 0;">
                <div class="card b2b-form-card">
                    <div class="b2b-form-header">
                        <i class="fas fa-plus-circle"></i>
                        <span>Nouvelle commande</span>
                    </div>

                    <!-- Sélecteur fournisseur -->
                    <form method="POST" action="commandes_b2b.php" class="vendeur-selector">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="choisir_vendeur">
                        <div class="form-group" style="margin:0;">
                            <label for="vendeur_id">Fournisseur</label>
                            <div style="display:flex; gap:8px;">
                                <select name="vendeur_id" id="vendeur_id" class="form-control" required>
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($fournisseurs as $f): ?>
                                        <option value="<?= $f['Id_Entreprise'] ?>"
                                            <?= $selected_vendeur == $f['Id_Entreprise'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($f['Nom_Entreprise']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">OK</button>
                                <?php if ($selected_vendeur): ?>
                                    <form method="POST" action="commandes_b2b.php" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="reset_vendeur">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Réinitialiser">✕</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>

                    <!-- Formulaire commande (si fournisseur sélectionné) -->
                    <?php if ($selected_vendeur && !empty($produits_b2b)): ?>
                        <form method="POST" action="commandes_b2b.php?onglet=passees" class="order-form" id="newOrderForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="creer_commande">
                            <input type="hidden" name="id_vendeur" value="<?= $selected_vendeur ?>">

                            <!-- Articles -->
                            <div class="products-table-wrap">
                                <table class="table table-sm b2b-products-table">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th class="text-center">Stock</th>
                                            <th style="width:70px">Qté</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($produits_b2b as $p): ?>
                                            <tr>
                                                <td>
                                                    <span class="product-name"><?= htmlspecialchars($p['Nom_Produit']) ?></span>
                                                    <span class="product-price"><?= number_format((float)$p['Prix_B2B'], 0, ',', ' ') ?> F</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="stock-badge <?= $p['Quantite_En_Stock'] < 10 ? 'stock-low' : 'stock-ok' ?>">
                                                        <?= $p['Quantite_En_Stock'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <input type="number"
                                                        name="items[<?= $p['Id_Produit'] ?>]"
                                                        id="qty-field-<?= $p['Id_Produit'] ?>"
                                                        aria-label="Quantité pour <?= htmlspecialchars($p['Nom_Produit']) ?>"
                                                        min="0"
                                                        max="<?= $p['Quantite_En_Stock'] ?>"
                                                        value="0"
                                                        class="form-control form-control-sm qty-field"
                                                        data-prix="<?= $p['Prix_B2B'] ?>"
                                                        oninput="calculerTotal()">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Total dynamique -->
                            <div class="order-total-box">
                                <span>Total estimé</span>
                                <strong id="order-total">0 F</strong>
                            </div>

                            <!-- Options commande urgente (Point 1) -->
                            <div class="urgence-section">
                                <label class="toggle-label" for="est_urgente">
                                    <input type="checkbox" name="est_urgente" id="est_urgente"
                                        onchange="toggleUrgence(this)">
                                    <span class="toggle-slider"></span>
                                    <span>⚡ Commande urgente</span>
                                </label>
                                <div id="urgence-options" class="urgence-options" style="display:none;">
                                    <label for="delai_minutes" style="font-size:0.85rem; color:var(--text-muted);">Délai de réponse souhaité</label>
                                    <select name="delai_minutes" id="delai_minutes" class="form-control form-control-sm">
                                        <option value="30">30 minutes</option>
                                        <option value="60">1 heure</option>
                                        <option value="120" selected>2 heures (défaut)</option>
                                        <option value="240">4 heures</option>
                                        <option value="480">8 heures</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Mode de retrait (Point 2) -->
                            <div class="retrait-section">
                                <span style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Mode de livraison</span>
                                <div class="retrait-options">
                                    <label class="retrait-option" for="mode_retrait_livraison">
                                        <input type="radio" name="mode_retrait" id="mode_retrait_livraison" value="livraison" checked
                                            onchange="toggleAdresseRetrait(false)">
                                        <div class="retrait-card">
                                            <i class="fas fa-truck"></i>
                                            <span>Livraison</span>
                                        </div>
                                    </label>
                                    <label class="retrait-option" for="mode_retrait_retrait_place">
                                        <input type="radio" name="mode_retrait" id="mode_retrait_retrait_place" value="retrait_place"
                                            onchange="toggleAdresseRetrait(true)">
                                        <div class="retrait-card">
                                            <i class="fas fa-store"></i>
                                            <span>Retrait sur place</span>
                                        </div>
                                    </label>
                                </div>
                                <div id="adresse-retrait-group" style="display:none; margin-top:10px;">
                                    <label for="adresse_retrait" class="sr-only">Adresse de retrait</label>
                                    <input type="text" name="adresse_retrait" id="adresse_retrait" class="form-control form-control-sm"
                                        placeholder="Adresse de retrait...">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-block" style="margin-top:15px;">
                                <i class="fas fa-paper-plane"></i> Envoyer la commande
                            </button>
                        </form>
                    <?php elseif ($selected_vendeur): ?>
                        <div class="alert alert-info" style="margin-top:15px; font-size:0.9rem;">
                            <i class="fas fa-info-circle"></i>
                            Ce fournisseur n'a aucun produit disponible en B2B actuellement.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ────────────────────────────────
             LISTE DES COMMANDES
             ──────────────────────────────── -->
        <div style="flex:1; min-width:0;">
            <?php if (empty($commandes)): ?>
                <div class="card" style="text-align:center; padding:60px 20px; color:var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size:3rem; opacity:0.3; margin-bottom:15px;"></i>
                    <p>Aucune transaction B2B pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($commandes as $c): ?>
                    <?php
                    $est_urgente  = (bool)$c['Est_Urgente'];
                    $sec_restants = $est_urgente ? getSecondesRestantes($c['Date_Limite_Reponse'] ?? null) : null;
                    $mode_retrait = $c['Mode_Retrait'] ?? 'livraison';
                    $lignes_cmd   = getLignesCommande($pdo, (int)$c['Id_Commande_B2B']);
                    ?>
                    <div class="commande-card <?= $est_urgente && in_array($c['Statut'], ['en_attente', 'validee']) ? 'commande-urgente' : '' ?>"
                        data-id="<?= $c['Id_Commande_B2B'] ?>">

                        <!-- En-tête de la commande -->
                        <div class="commande-header">
                            <div class="commande-ref">
                                <strong><?= htmlspecialchars($c['Numero_Commande']) ?></strong>
                                <?php if ($est_urgente && in_array($c['Statut'], ['en_attente', 'validee'])): ?>
                                    <span class="badge badge-urgent badge-pulse">⚡ URGENT</span>
                                <?php endif; ?>
                            </div>

                            <div class="commande-meta">
                                <?php echo badgeStatutCommande($c['Statut']); ?>
                                <span class="mode-retrait-chip">
                                    <?php if ($mode_retrait === 'retrait_place'): ?>
                                        <i class="fas fa-store"></i> Retrait
                                    <?php else: ?>
                                        <i class="fas fa-truck"></i> Livraison
                                    <?php endif; ?>
                                </span>
                                <span class="commande-date">
                                    <i class="fas fa-clock"></i>
                                    <?= date('d/m/y H:i', strtotime($c['Date_Commande'])) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Compte à rebours urgence (côté vendeur + commandes en attente) -->
                        <?php if ($est_urgente && $onglet === 'recues' && $c['Statut'] === 'en_attente' && $sec_restants !== null): ?>
                            <div class="countdown-bar <?= $sec_restants < 1800 ? 'countdown-critical' : '' ?>"
                                data-seconds="<?= max(0, $sec_restants) ?>"
                                id="countdown-<?= $c['Id_Commande_B2B'] ?>">
                                <i class="fas fa-hourglass-half"></i>
                                <span class="countdown-text">Calcul...</span>
                                <div class="countdown-progress-wrap">
                                    <div class="countdown-progress-bar" style="width:100%"></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Corps de la commande -->
                        <div class="commande-body">
                            <div class="commande-partenaire">
                                <i class="fas fa-building"></i>
                                <strong><?= htmlspecialchars($c['Autre_Partie']) ?></strong>
                                <?php if ($c['Tel_Entreprise']): ?>
                                    <a href="tel:<?= htmlspecialchars($c['Tel_Entreprise']) ?>" class="btn-icon" title="Appeler">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Articles (depuis lignes normalisées) -->
                            <div class="commande-articles">
                                <?php foreach ($lignes_cmd as $ligne): ?>
                                    <div class="article-ligne">
                                        <span class="article-qte"><?= $ligne['quantite'] ?>×</span>
                                        <span class="article-nom"><?= htmlspecialchars($ligne['nom']) ?></span>
                                        <span class="article-prix"><?= number_format((float)$ligne['sous_total'], 0, ',', ' ') ?> F</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="commande-total">
                                Total : <strong><?= number_format((float)$c['Montant_Total'], 0, ',', ' ') ?> F</strong>
                            </div>

                            <?php if ($mode_retrait === 'retrait_place' && !empty($c['Adresse_Retrait'])): ?>
                                <div class="retrait-info">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Retrait : <?= htmlspecialchars($c['Adresse_Retrait']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($c['Message_Validation'])): ?>
                                <div class="validation-message">
                                    <i class="fas fa-comment-alt"></i>
                                    <?= htmlspecialchars($c['Message_Validation']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Timeline de la commande (v3) -->
                        <div class="commande-timeline">
                            <?php
                            $etapes_timeline = getTimelineSteps($c['Statut']);
                            foreach ($etapes_timeline as $etape):
                                $classe = ($etape['etat'] === 'done') ? 'done' : (($etape['etat'] === 'active') ? 'current' : (($etape['etat'] === 'error') ? 'error' : 'pending'));
                            ?>
                                <div class="timeline-step <?= $classe ?>" title="<?= htmlspecialchars($etape['desc'] ?? '') ?>">
                                    <div class="timeline-dot">
                                        <i class="fas <?= htmlspecialchars($etape['icon']) ?>"></i>
                                    </div>
                                    <span><?= htmlspecialchars($etape['label']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Historique de la commande (v3) -->
                        <?php
                        $historique = getHistoriqueCommande($pdo, (int)$c['Id_Commande_B2B']);
                        if (!empty($historique)):
                        ?>
                            <div class="commande-history">
                                <button type="button" class="history-title" onclick="toggleHistory(<?= $c['Id_Commande_B2B'] ?>)" aria-expanded="false">
                                    <i class="fas fa-history"></i> Historique des statuts
                                    <i class="fas fa-chevron-down history-chevron" id="history-chevron-<?= $c['Id_Commande_B2B'] ?>"></i>
                                </button>
                                <ul class="history-list" id="history-list-<?= $c['Id_Commande_B2B'] ?>" style="display:none;">
                                    <?php foreach ($historique as $h): ?>
                                        <li>
                                            <span class="history-date"><?= date('d/m H:i', strtotime($h['Date_Changement'])) ?></span>
                                            <span class="history-badge badge-small"><?= htmlspecialchars(getLabelStatut($h['Nouveau_Statut'])) ?></span>
                                            <?php if ($h['Nom_Entreprise']): ?>
                                                <span class="history-actor">par <strong><?= htmlspecialchars($h['Nom_Entreprise']) ?></strong></span>
                                            <?php endif; ?>
                                            <?php if ($h['Note']): ?>
                                                <div class="history-note">« <?= htmlspecialchars($h['Note']) ?> »</div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="commande-actions">
                            <!-- Actions VENDEUR (onglet reçues) -->
                            <?php if ($onglet === 'recues'): ?>
                                <?php if ($c['Statut'] === 'en_attente'): ?>
                                    <!-- Valider -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Valider cette commande ? Le stock sera automatiquement déduit.')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="valider">
                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Valider
                                        </button>
                                    </form>
                                    <!-- Refuser -->
                                    <button class="btn btn-danger btn-sm"
                                        onclick="ouvrirModalRefus(<?= $c['Id_Commande_B2B'] ?>, '<?= htmlspecialchars($c['Numero_Commande']) ?>')">
                                        <i class="fas fa-times"></i> Refuser
                                    </button>
                                <?php elseif ($c['Statut'] === 'validee'): ?>
                                    <!-- Mettre en préparation -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="en_preparation">
                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                        <button type="submit" class="btn btn-purple btn-sm">
                                            <i class="fas fa-box-open"></i> Commencer préparation
                                        </button>
                                    </form>
                                <?php elseif ($c['Statut'] === 'en_preparation'): ?>
                                    <!-- Marquer prête -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="marquer_prete">
                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                        <button type="submit" class="btn btn-teal btn-sm">
                                            <i class="fas fa-check-double"></i> Marquer prête à expédier
                                        </button>
                                    </form>
                                <?php elseif ($c['Statut'] === 'prete'): ?>
                                    <!-- Expédier -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Expédier cette commande ? Une facture et une expédition logistique seront créées.')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="expedier">
                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-shipping-fast"></i> Lancer la livraison
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Actions ACHETEUR (onglet passées) -->
                            <?php else: ?>
                                <?php if ($c['Statut'] === 'expediee'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Confirmer la réception correcte de cette commande ?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="livree">
                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-box-open"></i> Confirmer réception (Livré)
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Bouton Chat (toujours visible) -->
                            <button class="btn btn-chat btn-sm"
                                onclick="ouvrirChat(<?= $c['Id_Commande_B2B'] ?>, '<?= htmlspecialchars($c['Numero_Commande']) ?>')">
                                <i class="fas fa-comment-dots"></i> Chat
                                <span class="chat-unread-count" id="chat-count-<?= $c['Id_Commande_B2B'] ?>" style="display:none;"></span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL DE REFUS
     ============================================================ -->
<div id="modal-refus" class="b2b-modal-overlay" style="display:none;" onclick="fermerModalRefus(event)">
    <div class="b2b-modal">
        <div class="b2b-modal-header">
            <h3><i class="fas fa-times-circle" style="color:var(--danger);"></i> Refuser la commande</h3>
            <button onclick="fermerModalRefus()" class="modal-close">✕</button>
        </div>
        <form method="POST" id="form-refus">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="refuser">
            <input type="hidden" name="id_commande" id="refus-id-commande">
            <div class="b2b-modal-body">
                <p id="refus-num-label" style="margin-bottom:15px; color:var(--text-muted);"></p>
                <div class="form-group">
                    <label>Motif du refus *</label>
                    <textarea name="motif_refus" class="form-control" rows="3"
                        placeholder="Ex : Rupture de stock temporaire, quantité non disponible..."
                        required></textarea>
                </div>
            </div>
            <div class="b2b-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModalRefus()">Annuler</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> Confirmer le refus
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     PANNEAU CHAT B2B (Point 7)
     ============================================================ -->
<div id="chat-panel" class="chat-panel" style="display:none;">
    <div class="chat-header">
        <div class="chat-title">
            <div class="chat-avatar"><i class="fas fa-comments"></i></div>
            <div>
                <strong>Discussion commande</strong>
                <span class="chat-command-ref">N° <span id="chat-commande-label">-</span></span>
            </div>
        </div>
        <div class="chat-header-actions">
            <span class="chat-status" id="chat-status"><span class="chat-status-dot"></span> En ligne</span>
            <button type="button" onclick="fermerChat()" class="chat-close-btn" aria-label="Fermer le chat" title="Fermer le chat">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="chat-messages" id="chat-messages">
        <div class="chat-loading">
            <i class="fas fa-spinner fa-spin"></i> Chargement...
        </div>
    </div>

    <!-- Barre de saisie -->
    <div class="chat-input-area">
        <div class="chat-compose-label">Votre message</div>
        <!-- Sélecteur de type de message -->
        <div class="chat-type-selector">
            <button type="button" class="chat-type-btn active" data-type="texte" aria-pressed="true" onclick="setTypeMessage(this, 'texte')" title="Message texte">
                <span>Message</span>
            </button>
            <button type="button" class="chat-type-btn chat-advanced-type" data-type="negociation_qte" aria-pressed="false" onclick="setTypeMessage(this, 'negociation_qte')" title="Négocier quantité">
                <span>Quantité</span>
            </button>
            <button type="button" class="chat-type-btn chat-advanced-type" data-type="negociation_delai" aria-pressed="false" onclick="setTypeMessage(this, 'negociation_delai')" title="Négocier délai">
                <span>Délai</span>
            </button>
            <button type="button" class="chat-type-btn chat-advanced-type" data-type="confirmation_dispo" aria-pressed="false" onclick="setTypeMessage(this, 'confirmation_dispo')" title="Confirmer disponibilité">
                <span>Disponibilité</span>
            </button>
            <button type="button" class="chat-more-btn" onclick="toggleChatOptions(this)">
                <span>Plus</span>
            </button>
            <label class="chat-type-btn" data-type="fichier" aria-pressed="false" onclick="setTypeMessage(this, 'fichier')" title="Joindre un fichier" style="cursor:pointer; margin:0;">
                <span>Fichier</span>
                <input type="file" id="chat-file-input" style="display:none;"
                    accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.docx,.doc"
                    onchange="previewFile(this)">
            </label>
        </div>

        <!-- Prévisualisation fichier -->
        <div id="file-preview" class="file-preview" style="display:none;">
            <i class="fas fa-file"></i>
            <span id="file-preview-name"></span>
            <button type="button" onclick="clearFile()" style="background:none; border:none; cursor:pointer; color:var(--danger);">✕</button>
        </div>

        <div class="chat-input-row">
            <textarea id="chat-input" class="chat-textarea"
                placeholder="Votre message..."
                aria-label="Votre message"
                onkeydown="handleChatKeydown(event)"
                rows="1"></textarea>
            <button type="button" onclick="envoyerMessage()" class="btn btn-primary chat-send-btn">
                <i class="fas fa-paper-plane"></i><span>Envoyer</span>
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
    // ── Variables globales ──
    let chatCommandeId = null;
    let chatLastMsgId = 0;
    let chatPollingTimer = null;
    let chatTypeMessage = 'texte';
    const MON_ENTREPRISE_ID = <?= $mon_entreprise_id ?>;

    // ── Calcul total de la commande ──
    function calculerTotal() {
        let total = 0;
        document.querySelectorAll('.qty-field').forEach(input => {
            const qte = parseFloat(input.value) || 0;
            const prix = parseFloat(input.dataset.prix) || 0;
            total += qte * prix;
        });
        const el = document.getElementById('order-total');
        if (el) el.textContent = new Intl.NumberFormat('fr-FR').format(total) + ' F';
    }

    // ── Urgence ──
    function toggleUrgence(checkbox) {
        const opts = document.getElementById('urgence-options');
        if (opts) opts.style.display = checkbox.checked ? 'block' : 'none';
    }

    // ── Mode retrait ──
    function toggleAdresseRetrait(show) {
        const group = document.getElementById('adresse-retrait-group');
        if (group) group.style.display = show ? 'block' : 'none';
    }

    // ── Compte à rebours urgence ──
    function initCountdowns() {
        document.querySelectorAll('.countdown-bar[data-seconds]').forEach(bar => {
            const initialSecs = parseInt(bar.dataset.seconds) || 0;
            const totalMinutes = <?= max(30, 120) ?>;
            const totalSecs = totalMinutes * 60;
            let secsLeft = initialSecs;

            function updateDisplay() {
                const textEl = bar.querySelector('.countdown-text');
                const progEl = bar.querySelector('.countdown-progress-bar');

                if (secsLeft <= 0) {
                    if (textEl) textEl.textContent = 'Délai dépassé !';
                    if (progEl) progEl.style.width = '0%';
                    bar.classList.add('countdown-critical');
                    return;
                }

                const h = Math.floor(secsLeft / 3600);
                const m = Math.floor((secsLeft % 3600) / 60);
                const s = secsLeft % 60;
                const label = h > 0 ?
                    `${h}h ${m.toString().padStart(2,'0')}min restant` :
                    `${m}min ${s.toString().padStart(2,'0')}s restant`;

                if (textEl) textEl.textContent = label;
                if (progEl) progEl.style.width = Math.max(0, (secsLeft / totalSecs) * 100) + '%';

                if (secsLeft < 1800) bar.classList.add('countdown-critical');
            }

            updateDisplay();
            setInterval(() => {
                secsLeft--;
                updateDisplay();
            }, 1000);
        });
    }

    // ── Modal de refus ──
    function ouvrirModalRefus(idCommande, numCommande) {
        document.getElementById('refus-id-commande').value = idCommande;
        document.getElementById('refus-num-label').textContent = `Commande : ${numCommande}`;
        document.getElementById('modal-refus').style.display = 'flex';
    }

    function fermerModalRefus(e) {
        if (!e || e.target.classList.contains('b2b-modal-overlay')) {
            document.getElementById('modal-refus').style.display = 'none';
        }
    }

    // ── CHAT ──
    function ouvrirChat(commandeId, commandeNum) {
        chatCommandeId = commandeId;
        chatLastMsgId = 0;
        document.getElementById('chat-commande-label').textContent = commandeNum;
        document.getElementById('chat-panel').style.display = 'flex';
        document.getElementById('chat-messages').innerHTML = '<div class="chat-loading"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
        chargerMessages();
        if (chatPollingTimer) clearInterval(chatPollingTimer);
        chatPollingTimer = setInterval(chargerMessages, 5000); // polling 5s
    }

    function fermerChat() {
        document.getElementById('chat-panel').style.display = 'none';
        if (chatPollingTimer) clearInterval(chatPollingTimer);
        chatPollingTimer = null;
        chatCommandeId = null;
    }

    async function chargerMessages() {
        if (!chatCommandeId) return;
        try {
            const url = `../api/chat_b2b.php?action=get_messages&commande_id=${chatCommandeId}&since_id=${chatLastMsgId}`;
            const resp = await fetch(url);
            const data = await resp.json();

            if (data.success && data.messages.length > 0) {
                const container = document.getElementById('chat-messages');
                // Vider si premier chargement
                if (chatLastMsgId === 0) container.innerHTML = '';

                data.messages.forEach(msg => {
                    container.appendChild(creerBulleMessage(msg));
                    chatLastMsgId = Math.max(chatLastMsgId, msg.Id_Message);
                });
                container.scrollTop = container.scrollHeight;
            } else if (chatLastMsgId === 0) {
                document.getElementById('chat-messages').innerHTML =
                    '<div class="chat-empty"><i class="fas fa-comment-slash"></i><br>Aucun message. Démarrez la discussion !</div>';
            }
            document.getElementById('chat-status').textContent = 'En ligne';
        } catch (e) {
            document.getElementById('chat-status').textContent = 'Hors ligne';
        }
    }

    function creerBulleMessage(msg) {
        const div = document.createElement('div');
        const estMoi = parseInt(msg.Est_Moi) === 1;
        div.className = 'chat-bubble ' + (estMoi ? 'bubble-moi' : 'bubble-autre');

        const typeLabels = {
            'negociation_qte': '⚖️ Négociation quantité',
            'negociation_delai': '📅 Négociation délai',
            'confirmation_dispo': '✅ Confirmation disponibilité',
            'fichier': '📎 Fichier joint',
            'texte': null,
        };
        const typeLabel = typeLabels[msg.Type_Message];

        let contenu = '';
        if (typeLabel) {
            contenu += `<div class="chat-msg-type">${typeLabel}</div>`;
        }
        if (msg.Message) {
            contenu += `<div class="chat-msg-text">${escapeHtml(msg.Message)}</div>`;
        }
        if (msg.Fichier_Path && msg.Fichier_Nom) {
            contenu += `<a href="../${escapeHtml(msg.Fichier_Path)}" target="_blank" class="chat-file-link">
                        <i class="fas fa-file-download"></i> ${escapeHtml(msg.Fichier_Nom)}
                    </a>`;
        }

        const lu = estMoi ?
            (msg.Est_Lu_Vendeur && msg.Est_Lu_Acheteur ? '✓✓' : '✓') :
            '';

        div.innerHTML = `
        ${!estMoi ? `<div class="bubble-auteur">${escapeHtml(msg.Nom_Emetteur)}</div>` : ''}
        <div class="bubble-content">${contenu}</div>
        <div class="bubble-meta">
            <span title="${escapeHtml(msg.Date_Tooltip)}">${escapeHtml(msg.Date_Affichage)}</span>
            ${lu ? `<span class="bubble-lu">${lu}</span>` : ''}
        </div>
    `;
        return div;
    }

    async function envoyerMessage() {
        if (!chatCommandeId) return;
        const input = document.getElementById('chat-input');
        const texte = input.value.trim();
        const fichierInput = document.getElementById('chat-file-input');

        if (!texte && chatTypeMessage !== 'fichier') return;
        if (chatTypeMessage === 'fichier' && !fichierInput.files.length) {
            alert('Veuillez sélectionner un fichier.');
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', '<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>');
        formData.append('action', 'send');
        formData.append('commande_id', chatCommandeId);
        formData.append('message', texte);
        formData.append('type_message', chatTypeMessage);
        if (fichierInput.files.length) {
            formData.append('fichier', fichierInput.files[0]);
        }

        try {
            const resp = await fetch('../api/chat_b2b.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();
            if (data.success) {
                input.value = '';
                clearFile();
                setTypeMessage(document.querySelector('.chat-type-btn[data-type="texte"]'), 'texte');
                await chargerMessages();
            } else {
                alert('Erreur : ' + (data.error || 'Inconnu'));
            }
        } catch (e) {
            alert('Erreur réseau. Réessayez.');
        }
    }

    function handleChatKeydown(e) {
        // Ctrl+Entrée ou Entrée seul (pas Shift+Entrée) pour envoyer
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            envoyerMessage();
        }
    }

    function setTypeMessage(btn, type) {
        chatTypeMessage = type;
        document.querySelectorAll('.chat-type-btn').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        if (btn) {
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
        }

        const typeLabels = {
            texte: 'Message texte',
            negociation_qte: 'Négociation de quantité',
            negociation_delai: 'Négociation de délai',
            confirmation_dispo: 'Confirmation de disponibilité',
            fichier: 'Fichier joint'
        };
        const composeLabel = document.querySelector('.chat-compose-label');
        if (composeLabel) composeLabel.textContent = typeLabels[type] || 'Votre message';

        // Si fichier, déclencher le sélecteur
        if (type === 'fichier') {
            document.getElementById('chat-file-input').click();
        }
    }

    function toggleChatOptions(btn) {
        const selector = btn.closest('.chat-type-selector');
        if (!selector) return;
        const expanded = selector.classList.toggle('show-advanced');
        btn.classList.toggle('active', expanded);
        btn.querySelector('span').textContent = expanded ? 'Moins' : 'Plus';
    }

    function previewFile(input) {
        if (input.files.length) {
            const name = input.files[0].name;
            document.getElementById('file-preview-name').textContent = name;
            document.getElementById('file-preview').style.display = 'flex';
            setTypeMessage(document.querySelector('.chat-type-btn[data-type="fichier"]'), 'fichier');
        }
    }

    function clearFile() {
        document.getElementById('chat-file-input').value = '';
        document.getElementById('file-preview').style.display = 'none';
        setTypeMessage(document.querySelector('.chat-type-btn[data-type="texte"]'), 'texte');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Toggle Historique (v3) ──
    function toggleHistory(id) {
        const list = document.getElementById('history-list-' + id);
        const chevron = document.getElementById('history-chevron-' + id);
        if (list && chevron) {
            const isHidden = list.style.display === 'none';
            list.style.display = isHidden ? 'block' : 'none';
            chevron.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    // ── Init ──
    document.addEventListener('DOMContentLoaded', function() {
        initCountdowns();
        calculerTotal();
    });
</script>

<?php
// ============================================================
// STYLES INTÉGRÉS (spécifiques à cette page)
// ============================================================
?>
<style>
    .btn-purple {
        background-color: #8b5cf6 !important;
        color: white !important;
    }

    .btn-purple:hover {
        background-color: #7c3aed !important;
    }

    .btn-teal {
        background-color: #0d9488 !important;
        color: white !important;
    }

    .btn-teal:hover {
        background-color: #0f766e !important;
    }

    /* ── Historique B2B v3 ── */
    .commande-history {
        background: #f8fafc;
        border-top: 1px solid var(--zinc-100);
        padding: 10px 18px;
        font-size: 0.82rem;
    }

    .history-title {
        color: var(--text-muted);
        font-weight: 600;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        user-select: none;
        padding: 4px 0;
    }

    .history-title:hover {
        color: var(--primary);
    }

    .history-chevron {
        transition: transform 0.2s ease;
    }

    .history-list {
        list-style: none;
        padding: 8px 0 0 0;
        margin: 0;
        border-top: 1px dashed var(--zinc-200);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .history-list li {
        position: relative;
        padding-left: 15px;
        line-height: 1.4;
        color: var(--text-main);
    }

    .history-list li::before {
        content: '•';
        position: absolute;
        left: 2px;
        color: var(--primary);
        font-weight: bold;
    }

    .history-date {
        color: var(--text-muted);
        font-size: 0.75rem;
        margin-right: 6px;
    }

    .history-badge {
        font-weight: 600;
        color: var(--text-main);
    }

    .history-actor {
        color: var(--text-muted);
        font-size: 0.78rem;
    }

    .history-note {
        margin-top: 2px;
        font-style: italic;
        color: #475569;
        padding-left: 8px;
        border-left: 2px solid var(--zinc-200);
    }

    .badge-small {
        font-size: 0.75rem;
        padding: 1px 6px;
        border-radius: 4px;
        background: #e2e8f0;
    }

    /* ── Onglets B2B ── */
    .b2b-tabs {
        display: flex;
        gap: 4px;
        background: var(--zinc-100);
        border-radius: 12px;
        padding: 5px;
        margin-bottom: 25px;
    }

    .b2b-tab {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12px 20px;
        border-radius: 9px;
        text-decoration: none;
        color: var(--text-muted);
        font-weight: 500;
        transition: all 0.2s ease;
        font-size: 0.95rem;
    }

    .b2b-tab .tab-subtitle {
        font-size: 0.75rem;
        opacity: 0.7;
        font-weight: 400;
        margin-top: 2px;
    }

    .b2b-tab.active {
        background: white;
        color: var(--primary);
        box-shadow: var(--shadow-md);
    }

    .b2b-tab:hover:not(.active) {
        background: var(--zinc-200);
    }

    /* ── Carte commande ── */
    .commande-card {
        background: white;
        border-radius: 12px;
        border: 1px solid var(--zinc-200);
        margin-bottom: 16px;
        overflow: hidden;
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }

    .commande-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-1px);
    }

    .commande-urgente {
        border-left: 4px solid #e74c3c !important;
        border-color: #fce4e4 !important;
        background: #fffafa !important;
    }

    .commande-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 18px;
        border-bottom: 1px solid var(--zinc-100);
        background: var(--zinc-50);
    }

    .commande-ref {
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .commande-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .commande-date {
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    .commande-body {
        padding: 14px 18px;
    }

    .commande-partenaire {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: var(--text-main);
    }

    .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--primary-light);
        color: var(--primary);
        font-size: 0.75rem;
        text-decoration: none;
        transition: background 0.2s;
    }

    .btn-icon:hover {
        background: var(--primary);
        color: white;
    }

    /* ── Articles ── */
    .commande-articles {
        margin-bottom: 10px;
    }

    .article-ligne {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 3px 0;
        font-size: 0.875rem;
        border-bottom: 1px solid var(--zinc-100);
    }

    .article-ligne:last-child {
        border-bottom: none;
    }

    .article-qte {
        font-weight: 700;
        color: var(--primary);
        min-width: 30px;
    }

    .article-nom {
        flex: 1;
        color: var(--text-muted);
    }

    .article-prix {
        font-weight: 600;
        color: var(--text-main);
    }

    .commande-total {
        text-align: right;
        font-size: 1rem;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid var(--zinc-200);
    }

    .retrait-info {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-top: 6px;
        padding: 5px 10px;
        background: var(--zinc-50);
        border-radius: 6px;
    }

    .validation-message {
        font-size: 0.82rem;
        color: var(--text-muted);
        font-style: italic;
        margin-top: 8px;
        padding: 6px 10px;
        background: var(--info-bg);
        border-radius: 6px;
        border-left: 3px solid var(--info);
    }

    /* ── Mode retrait chip ── */
    .mode-retrait-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.78rem;
        padding: 2px 8px;
        background: var(--zinc-100);
        border-radius: 20px;
        color: var(--text-muted);
    }

    /* ── Timeline ── */
    .commande-timeline {
        display: flex;
        justify-content: space-between;
        padding: 12px 18px;
        background: var(--zinc-50);
        border-top: 1px solid var(--zinc-100);
        border-bottom: 1px solid var(--zinc-100);
        position: relative;
    }

    .commande-timeline::before {
        content: '';
        position: absolute;
        top: 24px;
        left: 10%;
        right: 10%;
        height: 2px;
        background: var(--zinc-200);
        z-index: 0;
    }

    .timeline-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        z-index: 1;
        position: relative;
        font-size: 0.7rem;
        color: var(--text-muted);
    }

    .timeline-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        background: white;
        border: 2px solid var(--zinc-200);
        transition: all 0.3s ease;
    }

    .timeline-step.done .timeline-dot {
        background: var(--success);
        border-color: var(--success);
        color: white;
    }

    .timeline-step.current .timeline-dot {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
        box-shadow: 0 0 0 3px rgba(0, 70, 255, 0.15);
    }

    .timeline-step.done {
        color: var(--success);
    }

    .timeline-step.current {
        color: var(--primary);
        font-weight: 600;
    }

    /* ── Actions commande ── */
    .commande-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 18px;
        flex-wrap: wrap;
    }

    .btn-chat {
        background: linear-gradient(135deg, #667eea, #764ba2) !important;
        color: white !important;
        border: none !important;
        position: relative;
    }

    .btn-chat:hover {
        opacity: 0.9;
    }

    .chat-unread-count {
        position: absolute;
        top: -6px;
        right: -6px;
        background: var(--danger);
        color: white;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        font-size: 0.65rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* ── Compte à rebours ── */
    .countdown-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 18px;
        background: linear-gradient(135deg, #fff3cd, #ffeeba);
        border-bottom: 1px solid #ffc107;
        font-size: 0.875rem;
        font-weight: 600;
        color: #856404;
    }

    .countdown-bar.countdown-critical {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        border-color: #e74c3c;
        color: #721c24;
        animation: pulse-danger 1s ease-in-out infinite;
    }

    .countdown-progress-wrap {
        flex: 1;
        height: 6px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 3px;
        overflow: hidden;
    }

    .countdown-progress-bar {
        height: 100%;
        background: #ffc107;
        border-radius: 3px;
        transition: width 1s linear;
    }

    .countdown-critical .countdown-progress-bar {
        background: #e74c3c;
    }

    /* ── Formulaire commande ── */
    .b2b-form-card {
        padding: 0;
        overflow: hidden;
        border: 1px solid var(--zinc-200);
    }

    .b2b-form-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px 20px;
        background: linear-gradient(135deg, var(--primary), #3b6fd4);
        color: white;
        font-weight: 600;
        font-size: 1rem;
    }

    .vendeur-selector {
        padding: 15px 18px;
        border-bottom: 1px solid var(--zinc-100);
    }

    .order-form {
        padding: 15px 18px;
    }

    .products-table-wrap {
        max-height: 300px;
        overflow-y: auto;
        margin-bottom: 12px;
    }

    .b2b-products-table {
        font-size: 0.85rem;
    }

    .b2b-products-table th {
        background: var(--zinc-50);
        font-size: 0.78rem;
    }

    .product-name {
        display: block;
        font-size: 0.82rem;
    }

    .product-price {
        display: block;
        font-size: 0.78rem;
        color: var(--primary);
        font-weight: 600;
    }

    .stock-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .stock-ok {
        background: var(--success-bg);
        color: var(--success);
    }

    .stock-low {
        background: var(--warning-bg);
        color: var(--warning);
    }

    .qty-field {
        width: 55px;
        text-align: center;
    }

    .order-total-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        background: var(--primary-light);
        border-radius: 8px;
        margin-bottom: 12px;
        font-size: 0.9rem;
    }

    .order-total-box strong {
        font-size: 1.1rem;
        color: var(--primary);
    }

    /* ── Urgence section ── */
    .urgence-section {
        padding: 12px;
        background: #fff8f0;
        border: 1px solid #ffe0b2;
        border-radius: 8px;
        margin-bottom: 12px;
    }

    .urgence-options {
        margin-top: 10px;
    }

    .toggle-label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .toggle-label input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    /* ── Mode retrait ── */
    .retrait-section {
        margin-bottom: 12px;
    }

    .retrait-options {
        display: flex;
        gap: 10px;
        margin-top: 8px;
    }

    .retrait-option {
        flex: 1;
        cursor: pointer;
    }

    .retrait-option input {
        display: none;
    }

    .retrait-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        padding: 10px;
        border: 2px solid var(--zinc-200);
        border-radius: 8px;
        font-size: 0.82rem;
        transition: all 0.2s ease;
        background: white;
    }

    .retrait-card i {
        font-size: 1.2rem;
        color: var(--text-muted);
    }

    .retrait-option input:checked+.retrait-card {
        border-color: var(--primary);
        background: var(--primary-light);
        color: var(--primary);
    }

    .retrait-option input:checked+.retrait-card i {
        color: var(--primary);
    }

    /* ── Modal ── */
    .b2b-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .b2b-modal {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 480px;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    .b2b-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid var(--zinc-100);
    }

    .b2b-modal-header h3 {
        margin: 0;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .b2b-modal-body {
        padding: 20px;
    }

    .b2b-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px 20px;
        border-top: 1px solid var(--zinc-100);
    }

    .modal-close {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.1rem;
        color: var(--text-muted);
    }

    .modal-close:hover {
        color: var(--danger);
    }

    /* ── Chat panel ── */
    .chat-panel {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: min(430px, calc(100vw - 48px));
        height: min(680px, calc(100vh - 48px));
        background: white;
        border-radius: 18px;
        box-shadow: 0 18px 55px rgba(15, 23, 42, 0.22);
        z-index: 1500;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--zinc-200);
    }

    .chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 18px;
        background: #172554;
        color: white;
    }

    .chat-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
    }

    .chat-avatar {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: #2563eb;
        color: white;
        flex-shrink: 0;
    }

    .chat-title strong,
    .chat-command-ref {
        display: block;
    }

    .chat-command-ref {
        margin-top: 2px;
        color: #bfdbfe;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .chat-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .chat-close-btn {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 8px;
        background: transparent;
        color: white;
        cursor: pointer;
    }

    .chat-close-btn:hover {
        background: rgba(255, 255, 255, 0.12);
    }

    .chat-status {
        font-size: 0.75rem;
        color: #bbf7d0;
        white-space: nowrap;
    }

    .chat-status-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        margin-right: 4px;
        border-radius: 50%;
        background: #4ade80;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 18px 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: #f8fafc;
    }

    .chat-loading,
    .chat-empty {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.875rem;
        padding: 30px;
        margin: auto;
    }

    /* Bulles */
    .chat-bubble {
        max-width: 84%;
        display: flex;
        flex-direction: column;
    }

    .bubble-moi {
        align-self: flex-end;
        align-items: flex-end;
    }

    .bubble-autre {
        align-self: flex-start;
        align-items: flex-start;
    }

    .bubble-auteur {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-bottom: 2px;
    }

    .bubble-content {
        padding: 10px 13px;
        border-radius: 14px;
        font-size: 0.875rem;
        line-height: 1.4;
        word-break: break-word;
    }

    .bubble-moi .bubble-content {
        background: #2563eb;
        color: white;
        border-bottom-right-radius: 4px;
    }

    .bubble-autre .bubble-content {
        background: white;
        color: var(--text-main);
        border: 1px solid var(--zinc-200);
        border-bottom-left-radius: 4px;
    }

    .bubble-meta {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .bubble-lu {
        color: #667eea;
    }

    .chat-msg-type {
        font-size: 0.72rem;
        font-weight: 700;
        margin-bottom: 4px;
        opacity: 0.8;
    }

    .chat-file-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.82rem;
        padding: 4px 8px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.2);
        color: inherit;
        margin-top: 4px;
        text-decoration: none;
    }

    .chat-file-link:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Zone de saisie */
    .chat-input-area {
        border-top: 1px solid var(--zinc-100);
        padding: 12px 14px 14px;
        background: white;
    }

    .chat-compose-label {
        margin-bottom: 8px;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .chat-type-selector {
        display: flex;
        gap: 6px;
        margin-bottom: 10px;
    }

    .chat-type-btn {
        flex: 1 1 70px;
        min-width: 0;
        min-height: 34px;
        padding: 6px 5px;
        background: var(--zinc-100);
        border: none;
        border-radius: 6px;
        cursor: pointer;
        color: var(--text-muted);
        font-size: 0.8rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .chat-more-btn {
        display: none;
        flex: 0 0 auto;
        min-height: 34px;
        padding: 6px 10px;
        border: 0;
        border-radius: 6px;
        background: var(--zinc-100);
        color: var(--text-muted);
        cursor: pointer;
        font: inherit;
        font-size: 0.8rem;
    }

    .chat-type-btn.active,
    .chat-type-btn:hover {
        background: var(--primary-light);
        color: var(--primary);
    }

    .file-preview {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 5px 8px;
        background: var(--info-bg);
        border-radius: 6px;
        font-size: 0.8rem;
        margin-bottom: 6px;
        color: var(--info);
    }

    .chat-input-row {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    .chat-textarea {
        flex: 1;
        resize: none;
        border: 1px solid var(--zinc-200);
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 0.875rem;
        font-family: inherit;
        outline: none;
        max-height: 80px;
        min-height: 42px;
        overflow-y: auto;
    }

    .chat-textarea:focus {
        border-color: var(--primary);
    }

    .chat-send-btn {
        min-height: 42px;
        padding: 0 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }

    .chat-send-btn span {
        display: inline;
    }

    /* ── Badges ── */
    .badge-urgent {
        background: #e74c3c;
        color: white;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .badge-pulse {
        animation: pulse-danger 1.5s ease-in-out infinite;
    }

    @keyframes pulse-danger {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4);
        }

        50% {
            box-shadow: 0 0 0 6px rgba(231, 76, 60, 0);
        }
    }

    /* ── Alertes améliorées ── */
    .b2b-alert {
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .chat-panel {
            inset: 10px;
            width: auto;
            height: calc(100dvh - 20px);
            max-height: none;
            border-radius: 14px;
        }

        .chat-type-selector {
            overflow-x: visible;
            padding-bottom: 2px;
        }

        .chat-type-btn {
            flex: 0 0 auto;
            min-width: 74px;
        }

        .chat-type-selector .chat-advanced-type {
            display: none;
        }

        .chat-type-selector .chat-more-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .chat-type-selector.show-advanced .chat-advanced-type {
            display: inline-flex;
        }

        .chat-type-selector .chat-type-btn,
        .chat-type-selector .chat-more-btn {
            min-width: 0;
            flex: 1 1 0;
        }

        .chat-type-selector .chat-type-btn span {
            display: none;
        }

        .chat-type-selector .chat-more-btn span,
        .chat-type-selector.show-advanced .chat-more-btn span {
            display: inline;
        }

        .chat-input-row {
            gap: 6px;
        }

        .chat-send-btn {
            min-width: 46px;
            padding: 0 12px;
        }

        .chat-send-btn span {
            display: none;
        }

        .commande-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .commande-timeline {
            display: none;
        }

        .b2b-tabs {
            flex-direction: column;
        }
    }
</style>

</body>

</html>