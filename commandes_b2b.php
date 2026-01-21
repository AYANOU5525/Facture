<?php
require_once 'auth.php';
require_once 'db.php';

$page_title = "Commandes B2B";
include 'header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$mon_entreprise_id = $stmt->fetchColumn();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CRÉATION D'UNE COMMANDE
    if (isset($_POST['action']) && $_POST['action'] === 'creer_commande') {
        try {
            $id_vendeur = $_POST['id_vendeur'];
            $items = $_POST['items'] ?? [];

            if (empty($id_vendeur) || empty($items)) {
                throw new Exception("Veuillez sélectionner un fournisseur et au moins un produit.");
            }

            $total_commande = 0;
            $articles_json = [];
            $has_items = false;

            foreach ($items as $id_produit => $quantite) {
                if ($quantite > 0) {
                    $stmt = $pdo->prepare("SELECT Nom_Produit, Prix_B2B FROM Produit WHERE Id_Produit = ?");
                    $stmt->execute([$id_produit]);
                    $prod = $stmt->fetch();

                    if ($prod) {
                        $prix = $prod['Prix_B2B'];
                        $total_commande += ($prix * $quantite);
                        $articles_json[] = [
                            'id_produit' => $id_produit,
                            'nom' => $prod['Nom_Produit'],
                            'quantite' => $quantite,
                            'prix' => $prix
                        ];
                        $has_items = true;
                    }
                }
            }

            if (!$has_items) throw new Exception("Aucune quantité saisie.");

            $numero = 'CMD-' . date('Ymd') . '-' . rand(1000, 9999);
            $stmt = $pdo->prepare("
                INSERT INTO Commande_B2B (Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse, 
                                        Articles_JSON, Montant_Total, Statut, Date_Commande)
                VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())
            ");
            $stmt->execute([$numero, $mon_entreprise_id, $id_vendeur, json_encode($articles_json), $total_commande]);

            $success = "Commande envoyée avec succès !";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // 2. CHANGEMENT DE STATUT
    elseif (isset($_POST['action'])) {
        try {
            $id_commande = $_POST['id_commande'];

            // VALIDATION
            if ($_POST['action'] === 'valider') {
                $msg = $_POST['message'] ?? '';
                $stmt = $pdo->prepare("UPDATE Commande_B2B SET Statut = 'validee', Message_Validation = ?, Date_Validation = NOW() WHERE Id_Commande_B2B = ?");
                $stmt->execute([$msg, $id_commande]);

                $stmt = $pdo->prepare("SELECT Articles_JSON FROM Commande_B2B WHERE Id_Commande_B2B = ?");
                $stmt->execute([$id_commande]);
                $articles = json_decode($stmt->fetchColumn(), true);

                foreach ($articles as $art) {
                    $upd = $pdo->prepare("UPDATE Produit SET Quantite_En_Stock = Quantite_En_Stock - ? WHERE Id_Produit = ?");
                    $upd->execute([$art['quantite'], $art['id_produit']]);
                }
                $success = "Commande validée et stock décrémenté.";
            }

            // EXPÉDITION (ET FACTURATION AUTOMATIQUE)
            elseif ($_POST['action'] === 'expedier') {

                // A. Marquer comme expédiée
                $stmt = $pdo->prepare("UPDATE Commande_B2B SET Statut = 'expediee' WHERE Id_Commande_B2B = ?");
                $stmt->execute([$id_commande]);

                // B. Générer la Facture (Vente)
                // 1. Infos commande
                $stmt = $pdo->prepare("
                    SELECT c.*, e.Nom_Entreprise as Nom_Acheteur 
                    FROM Commande_B2B c
                    JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
                    WHERE c.Id_Commande_B2B = ?
                ");
                $stmt->execute([$id_commande]);
                $cmd = $stmt->fetch();

                if ($cmd) {
                    // 2. Créer Facture
                    $ref_facture = 'FAC-B2B-' . date('Ymd') . '-' . rand(100, 999);
                    $ins = $pdo->prepare("
                        INSERT INTO Vente (Numero_Vente, Id_Entreprise, Nom_Client, Date_Vente, Montant_Total, Type_Vente, Articles_JSON)
                        VALUES (?, ?, ?, NOW(), ?, 'b2b', ?)
                    ");
                    $ins->execute([
                        $ref_facture,
                        $mon_entreprise_id, // Moi (Vendeur)
                        $cmd['Nom_Acheteur'],
                        $cmd['Montant_Total'],
                        $cmd['Articles_JSON']
                    ]);

                    $success = "Commande expédiée et Facture N°$ref_facture générée automatiquement !";
                } else {
                    $success = "Commande expédiée (Erreur génération facture).";
                }
            }

            // LIVRAISON
            elseif ($_POST['action'] === 'livree') {
                $stmt = $pdo->prepare("UPDATE Commande_B2B SET Statut = 'livree' WHERE Id_Commande_B2B = ?");
                $stmt->execute([$id_commande]);

                $stmt = $pdo->prepare("SELECT Id_Entreprise_Vendeuse FROM Commande_B2B WHERE Id_Commande_B2B = ?");
                $stmt->execute([$id_commande]);
                $id_vendeur = $stmt->fetchColumn();

                $upd = $pdo->prepare("UPDATE Entreprise SET Score_Fiabilite = LEAST(100, Score_Fiabilite + 1), Nombre_Commandes_Completees = Nombre_Commandes_Completees + 1 WHERE Id_Entreprise = ?");
                $upd->execute([$id_vendeur]);

                $success = "Réception confirmée ! Stock et Score mis à jour.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// === VIEW DATA ===
$produits_b2b = [];
$selected_vendeur = $_GET['vendeur'] ?? null;
if ($selected_vendeur) {
    $stmt = $pdo->prepare("SELECT * FROM Produit WHERE Id_Entreprise = ? AND En_Destockage_B2B = 1 AND Quantite_En_Stock > 0");
    $stmt->execute([$selected_vendeur]);
    $produits_b2b = $stmt->fetchAll();
}

$fournisseurs = $pdo->query("SELECT Id_Entreprise, Nom_Entreprise FROM Entreprise WHERE Id_Entreprise != $mon_entreprise_id")->fetchAll();

$onglet = $_GET['onglet'] ?? 'recues';
if ($onglet === 'recues') {
    $sql = "SELECT c.*, e.Nom_Entreprise as Autre_Partie, e.Tel_Entreprise, e.Email_Entreprise
            FROM Commande_B2B c JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
            WHERE c.Id_Entreprise_Vendeuse = ? ORDER BY c.Date_Commande DESC";
} else {
    $sql = "SELECT c.*, e.Nom_Entreprise as Autre_Partie, e.Tel_Entreprise, e.Email_Entreprise
            FROM Commande_B2B c JOIN Entreprise e ON c.Id_Entreprise_Vendeuse = e.Id_Entreprise
            WHERE c.Id_Entreprise_Acheteuse = ? ORDER BY c.Date_Commande DESC";
}
$stmt = $pdo->prepare($sql);
$stmt->execute([$mon_entreprise_id]);
$commandes = $stmt->fetchAll();
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-shipping-fast"></i> Gestion des Commandes B2B</h1>
    </div>

    <?php if ($success): ?> <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div> <?php endif; ?>

    <!-- NOUVELLE COMMANDE -->
    <?php if ($onglet === 'passees'): ?>
        <div class="card" style="border-left: 5px solid #3498db;">
            <h3><i class="fas fa-plus-circle"></i> Nouvelle commande</h3>
            <form method="GET" action="commandes_b2b.php" style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
                <input type="hidden" name="onglet" value="passees">
                <select name="vendeur" class="form-control" required style="max-width: 300px;">
                    <option value="">-- Choisir un fournisseur --</option>
                    <?php foreach ($fournisseurs as $f): ?>
                        <option value="<?= $f['Id_Entreprise'] ?>" <?= $selected_vendeur == $f['Id_Entreprise'] ? 'selected' : '' ?>><?= htmlspecialchars($f['Nom_Entreprise']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Voir produits</button>
            </form>

            <?php if ($selected_vendeur && !empty($produits_b2b)): ?>
                <form method="POST" action="commandes_b2b.php?onglet=passees">
                    <input type="hidden" name="action" value="creer_commande">
                    <input type="hidden" name="id_vendeur" value="<?= $selected_vendeur ?>">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Quantité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produits_b2b as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['Nom_Produit']) ?></td>
                                    <td><?= number_format($p['Prix_B2B'], 0, ',', ' ') ?> F</td>
                                    <td><?= $p['Quantite_En_Stock'] ?></td>
                                    <td><input type="number" name="items[<?= $p['Id_Produit'] ?>]" min="0" max="<?= $p['Quantite_En_Stock'] ?>" class="form-control" style="width: 80px;"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-success">Envoyer la commande</button>
                </form>
            <?php elseif ($selected_vendeur): ?><p>Aucun produit dispo.</p><?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ONGLETS -->
    <div style="margin: 20px 0;">
        <a href="?onglet=recues" class="btn <?= $onglet === 'recues' ? 'btn-primary' : 'btn-secondary' ?>">Reçues (Je suis vendeur)</a>
        <a href="?onglet=passees" class="btn <?= $onglet === 'passees' ? 'btn-primary' : 'btn-secondary' ?>">Passées (Je suis acheteur)</a>
    </div>

    <!-- TABLEAU -->
    <div class="card">
        <?php if (empty($commandes)): ?>
            <p>Aucune transaction.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Date</th>
                        <th>Partenaire</th>
                        <th>Détails</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $c): ?>
                        <tr>
                            <td><strong><?= $c['Numero_Commande'] ?></strong></td>
                            <td><?= date('d/m/y H:i', strtotime($c['Date_Commande'])) ?></td>
                            <td><?= htmlspecialchars($c['Autre_Partie']) ?></td>
                            <td>
                                <?php $arts = json_decode($c['Articles_JSON'], true); ?>
                                <small>
                                    <?php if ($arts) foreach ($arts as $a) echo $a['quantite'] . "x " . $a['nom'] . "<br>"; ?>
                                    <strong>Total: <?= number_format($c['Montant_Total'], 0, ',', ' ') ?> F</strong>
                                </small>
                            </td>
                            <td>
                                <span class="badge badge-<?= $c['Statut'] === 'validee' ? 'success' : ($c['Statut'] === 'livree' ? 'primary' : 'warning') ?>"><?= strtoupper($c['Statut']) ?></span>
                            </td>
                            <td>
                                <?php if ($onglet === 'recues'): ?>
                                    <?php if ($c['Statut'] === 'en_attente'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="valider">
                                            <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                            <button class="btn btn-success btn-sm">Valider</button>
                                        </form>
                                    <?php elseif ($c['Statut'] === 'validee'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="expedier">
                                            <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                            <button class="btn btn-info btn-sm">Exprédier + Facture</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($c['Statut'] === 'expediee'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="livree">
                                            <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                            <button class="btn btn-success btn-sm">Confirmer Réception</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>

</html>