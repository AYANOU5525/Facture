<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use App\Application\Inventory\ProductService;
use App\Infrastructure\Persistence\ProductRepository;

$productService = new ProductService(new ProductRepository($pdo));

// Récupérer l'ID de l'entreprise de l'utilisateur connecté
$entreprise_id = $_SESSION['entreprise_id'] ?? null;

if (!$entreprise_id) {
    $stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ../includes/logout.php');
        exit();
    }
    $entreprise_id = $user['Id_Entreprise'];
}

$success = '';
$error = '';
$edit_mode = false;

$page_title = 'Gestion des Produits';
include '../includes/header.php';
// === TRAITEMENT DU FORMULAIRE (AJOUT / MODIFICATION / SUPPRESSION) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // CAS 1 : SUPPRESSION
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id_to_delete = $_POST['id_produit'];
        try {
            $productService->delete((int) $id_to_delete, (int) $entreprise_id);
            $success = "Produit supprimé avec succès.";
        } catch (Throwable $e) {
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
    // CAS 2 : AJOUT OU MODIFICATION
    else {
        $nom = $_POST['nom'];
        $description = $_POST['description'];
        $prix = $_POST['prix'];
        $stock = $_POST['stock'];
        $en_destockage = isset($_POST['en_destockage_b2b']) ? 1 : 0;
        $prix_b2b = !empty($_POST['prix_b2b']) ? $_POST['prix_b2b'] : null;
        $qte_min_b2b = !empty($_POST['quantite_min_b2b']) ? $_POST['quantite_min_b2b'] : 1;
        $id_produit = $_POST['id_produit'] ?? null;

        if ($id_produit) {
            try {
                    $productService->save($_POST, (int) $entreprise_id, (int) $id_produit);
                $success = "Produit modifié avec succès.";
            } catch (Throwable $e) {
                $error = "Erreur lors de la modification : " . $e->getMessage();
            }
        } else {
            try {
                $productService->save($_POST, (int) $entreprise_id);
                $success = "Produit ajouté avec succès.";
            } catch (Throwable $e) {
                $error = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    }
}

// === GESTION DE L'AFFICHAGE POUR ÉDITION ===
if (isset($_GET['edit'])) {
    $product_data = $productService->find((int) $_GET['edit'], (int) $entreprise_id);
    if ($product_data) {
        $edit_mode = true;
    }
}

// === RÉCUPÉRATION DE LA LISTE DES PRODUITS ===
$produits = $productService->list((int) $entreprise_id);
?>

<h1><i class="fas fa-box"></i> Gestion des Produits</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- FORMULAIRE -->
<div class="card" style="margin-bottom: 30px;">
    <h2><?= $edit_mode ? 'Modifier le produit' : 'Nouveau produit' ?></h2>
    <form method="POST" action="products.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($edit_mode): ?>
            <input type="hidden" name="id_produit" value="<?= $product_data['Id_Produit'] ?>">
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Nom du produit *</label>
                    <input type="text" name="nom" required class="form-control" value="<?= $edit_mode ? htmlspecialchars($product_data['Nom_Produit'] ?? '') : '' ?>">
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    <label>Prix Unitaire (FCFA) *</label>
                    <input type="number" step="0.01" name="prix" required class="form-control" value="<?= $edit_mode ? $product_data['Prix_Unitaire_Produit'] : '' ?>">
                </div>
            </div>

            <div class="col-md-2">
                <div class="form-group">
                    <label>Stock *</label>
                    <input type="number" name="stock" required class="form-control" value="<?= $edit_mode ? $product_data['Quantite_En_Stock'] : '0' ?>">
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" value="<?= $edit_mode ? htmlspecialchars($product_data['Description_Produit'] ?? '') : '' ?>">
                </div>
            </div>
        </div>

        <!-- SECTION B2B -->
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label style="font-weight: bold; color: #2c3e50;">
                        <input type="checkbox" name="en_destockage_b2b" value="1" id="check_b2b"
                            <?= ($edit_mode && $product_data['En_Destockage_B2B']) ? 'checked' : '' ?>
                            onchange="toggleB2B()">
                        Mettre en Déstockage B2B
                    </label>
                </div>
            </div>
        </div>

        <div id="b2b_fields" style="display: <?= ($edit_mode && $product_data['En_Destockage_B2B']) ? 'block' : 'none' ?>; background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Prix Spécial B2B</label>
                        <input type="number" step="0.01" name="prix_b2b" class="form-control" value="<?= $edit_mode ? $product_data['Prix_B2B'] : '' ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Quantité Min. Achat</label>
                        <input type="number" name="quantite_min_b2b" class="form-control" value="<?= $edit_mode ? $product_data['Quantite_Min_B2B'] : '1' ?>">
                    </div>
                </div>
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $edit_mode ? 'Enregistrer les modifications' : 'Ajouter le produit' ?>
            </button>
            <?php if ($edit_mode): ?>
                <a href="products.php" class="btn btn-secondary">Annuler</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- LISTE DES PRODUITS -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">Liste des produits (<?= count($produits) ?>)</h2>
        <input type="text" id="searchProduct" class="form-control" style="width: 250px;" placeholder="🔍 Rechercher un produit..." onkeyup="filterProducts()">
    </div>
    <div class="scrollable-list">
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
                            <strong><?= htmlspecialchars($p['Nom_Produit'] ?? '') ?></strong><br>
                            <small style="color: #666;"><?= htmlspecialchars($p['Description_Produit'] ?? '') ?></small>
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
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
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

    function filterProducts() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchProduct");
        filter = input.value.toUpperCase();
        table = document.querySelector(".table");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            td = tr[i].getElementsByTagName("td")[0]; // Colonne Produit
            if (td) {
                txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
</script>

<!-- Enlever l'include footer car intégré dans header ou inutile -->
</body>

</html>