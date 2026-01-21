<?php
require_once 'auth.php';
require_once 'db.php';

$page_title = 'Gestion des Produits';
include 'header.php';

// Récupérer l'ID de l'entreprise de l'utilisateur connecté
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$entreprise_id = $user['Id_Entreprise'];

$success = '';
$error = '';
$edit_mode = false;
$product_data = [];

// === TRAITEMENT DU FORMULAIRE (AJOUT / MODIFICATION / SUPPRESSION) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CAS 1 : SUPPRESSION
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id_to_delete = $_POST['id_produit'];
        try {
            $stmt = $pdo->prepare("DELETE FROM Produit WHERE Id_Produit = ? AND Id_Entreprise = ?");
            $stmt->execute([$id_to_delete, $entreprise_id]);
            $success = "Produit supprimé avec succès.";
        } catch (PDOException $e) {
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
    // CAS 2 : AJOUT OU MODIFICATION
    else {
        // Récupération des données
        $nom = $_POST['nom'];
        $description = $_POST['description'];
        $prix = $_POST['prix'];
        $stock = $_POST['stock'];
        $en_destockage = isset($_POST['en_destockage_b2b']) ? 1 : 0;
        $prix_b2b = !empty($_POST['prix_b2b']) ? $_POST['prix_b2b'] : null;
        $qte_min_b2b = !empty($_POST['quantite_min_b2b']) ? $_POST['quantite_min_b2b'] : 1;
        $id_produit = $_POST['id_produit'] ?? null;

        if ($id_produit) {
            // Mise à jour
            try {
                $stmt = $pdo->prepare("
                    UPDATE Produit SET 
                    Nom_Produit = ?, Description_Produit = ?, Prix_Unitaire_Produit = ?, 
                    Quantite_En_Stock = ?, En_Destockage_B2B = ?, Prix_B2B = ?, Quantite_Min_B2B = ?
                    WHERE Id_Produit = ? AND Id_Entreprise = ?
                ");
                $stmt->execute([$nom, $description, $prix, $stock, $en_destockage, $prix_b2b, $qte_min_b2b, $id_produit, $entreprise_id]);
                $success = "Produit modifié avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur lors de la modification : " . $e->getMessage();
            }
        } else {
            // Création
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO Produit 
                    (Nom_Produit, Description_Produit, Prix_Unitaire_Produit, Quantite_En_Stock, 
                     En_Destockage_B2B, Prix_B2B, Quantite_Min_B2B, Id_Entreprise)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nom, $description, $prix, $stock, $en_destockage, $prix_b2b, $qte_min_b2b, $entreprise_id]);
                $success = "Produit ajouté avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    }
}

// === GESTION DE L'AFFICHAGE POUR ÉDITION ===
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM Produit WHERE Id_Produit = ? AND Id_Entreprise = ?");
    $stmt->execute([$_GET['edit'], $entreprise_id]);
    $product_data = $stmt->fetch();
    if ($product_data) {
        $edit_mode = true;
    }
}

// === RÉCUPÉRATION DE LA LISTE DES PRODUITS ===
$stmt = $pdo->prepare("SELECT * FROM Produit WHERE Id_Entreprise = ? ORDER BY Nom_Produit");
$stmt->execute([$entreprise_id]);
$produits = $stmt->fetchAll();
?>

<h1><i class="fas fa-box"></i> Gestion des Produits</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <!-- COLONNE GAUCHE : FORMULAIRE -->
    <div class="col-md-4">
        <div class="card">
            <h2><?= $edit_mode ? 'Modifier le produit' : 'Nouveau produit' ?></h2>
            <form method="POST" action="products.php">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id_produit" value="<?= $product_data['Id_Produit'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Nom du produit *</label>
                    <input type="text" name="nom" required class="form-control" value="<?= $edit_mode ? htmlspecialchars($product_data['Nom_Produit']) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control"><?= $edit_mode ? htmlspecialchars($product_data['Description_Produit']) : '' ?></textarea>
                </div>

                <div class="form-group">
                    <label>Prix Unitaire (FCFA) *</label>
                    <input type="number" step="0.01" name="prix" required class="form-control" value="<?= $edit_mode ? $product_data['Prix_Unitaire_Produit'] : '' ?>">
                </div>

                <div class="form-group">
                    <label>Stock *</label>
                    <input type="number" name="stock" required class="form-control" value="<?= $edit_mode ? $product_data['Quantite_En_Stock'] : '0' ?>">
                </div>

                <hr>
                <!-- SECTION B2B -->
                <div class="form-group">
                    <label style="font-weight: bold; color: #2c3e50;">
                        <input type="checkbox" name="en_destockage_b2b" value="1" id="check_b2b"
                            <?= ($edit_mode && $product_data['En_Destockage_B2B']) ? 'checked' : '' ?>
                            onchange="toggleB2B()">
                        Mettre en Déstockage B2B
                    </label>
                </div>

                <div id="b2b_fields" style="display: <?= ($edit_mode && $product_data['En_Destockage_B2B']) ? 'block' : 'none' ?>; background: #f8f9fa; padding: 10px; border-radius: 5px;">
                    <div class="form-group">
                        <label>Prix Spécial B2B</label>
                        <input type="number" step="0.01" name="prix_b2b" class="form-control" value="<?= $edit_mode ? $product_data['Prix_B2B'] : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Quantité Min. Achat</label>
                        <input type="number" name="quantite_min_b2b" class="form-control" value="<?= $edit_mode ? $product_data['Quantite_Min_B2B'] : '1' ?>">
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_mode ? 'Enregistrer les modifications' : 'Ajouter le produit' ?>
                    </button>
                    <?php if ($edit_mode): ?>
                        <a href="products.php" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- COLONNE DROITE : LISTE -->
    <div class="col-md-8">
        <div class="card">
            <h2>Liste des produits (<?= count($produits) ?>)</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Prix</th>
                        <th>Stock</th>
                        <th>B2B</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $p): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p['Nom_Produit']) ?></strong><br>
                                <small style="color: #666;"><?= htmlspecialchars($p['Description_Produit']) ?></small>
                            </td>
                            <td><?= number_format($p['Prix_Unitaire_Produit'], 0, ',', ' ') ?> FCFA</td>
                            <td>
                                <?php if ($p['Quantite_En_Stock'] <= 5): ?>
                                    <span style="color: red; font-weight: bold;"><?= $p['Quantite_En_Stock'] ?></span>
                                <?php else: ?>
                                    <?= $p['Quantite_En_Stock'] ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['En_Destockage_B2B']): ?>
                                    <span class="badge badge-success">Oui</span><br>
                                    <small><?= number_format($p['Prix_B2B'], 0, ',', ' ') ?> FCFA</small>
                                <?php else: ?>
                                    <span class="badge" style="background:#ddd; color:#666;">Non</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="products.php?edit=<?= $p['Id_Produit'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="products.php" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_produit" value="<?= $p['Id_Produit'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function toggleB2B() {
        var checkBox = document.getElementById("check_b2b");
        var fields = document.getElementById("b2b_fields");
        if (checkBox.checked == true) {
            fields.style.display = "block";
        } else {
            fields.style.display = "none";
        }
    }
</script>

<!-- Enlever l'include footer car intégré dans header ou inutile -->
</body>

</html>