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

<div class="container fade-in">
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h1><i class="fas fa-box"></i> Gestion des Produits</h1>
        <button class="btn btn-primary" onclick="openProductModal()">
            <i class="fas fa-plus"></i> Ajouter un produit
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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
                                <small style="color: #666;"><?= htmlspecialchars($p['Description_Produit'] ?? '-') ?></small>
                            </td>
                            <td><?= number_format($p['Prix_Unitaire_Produit'], 0, ',', ' ') ?> FCFA</td>
                            <td>
                                <?php
                                $seuil = (int) ($p['Seuil_Alerte_Stock'] ?? 5);
                                $qte   = (int) $p['Quantite_En_Stock'];
                                ?>
                                <?php if ($qte <= $seuil): ?>
                                    <span style="color: #ea580c; font-weight: bold;" title="Stock sous le seuil d'alerte (<?= $seuil ?>)">
                                        ⚠ <?= $qte ?>
                                    </span>
                                <?php else: ?>
                                    <?= $qte ?>
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
                                <button onclick="openProductModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </button>
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
</div>

<!-- MODALE AJOUT / MODIFICATION PRODUIT -->
<div id="productModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px; width:95%;">
        <h3 id="modalTitle"><i class="fas fa-box"></i> Nouveau produit</h3>

        <form method="POST" action="products.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_produit" id="modal_id_produit" value="">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nom du produit *</label>
                        <input type="text" name="nom" id="modal_nom" required class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" id="modal_description" class="form-control">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Prix Unitaire (FCFA) *</label>
                        <input type="number" step="0.01" name="prix" id="modal_prix" required class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Stock *</label>
                        <input type="number" name="stock" id="modal_stock" required class="form-control" value="0">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Seuil d'alerte stock</label>
                <input type="number" name="seuil_alerte" id="modal_seuil" class="form-control" value="5" min="0"
                       title="Une alerte apparaît quand le stock est inférieur ou égal à ce seuil">
            </div>

            <div class="form-group">
                <label style="font-weight:bold;">
                    <input type="checkbox" name="en_destockage_b2b" value="1" id="modal_check_b2b" onchange="toggleModalB2B()">
                    Mettre en Déstockage B2B
                </label>
            </div>

            <div id="modal_b2b_fields" style="display:none; background:#f8f9fa; padding:15px; border-radius:5px; margin-bottom:15px;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Prix Spécial B2B (FCFA)</label>
                            <input type="number" step="0.01" name="prix_b2b" id="modal_prix_b2b" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Quantité Min. Achat</label>
                            <input type="number" name="quantite_min_b2b" id="modal_qte_min" class="form-control" value="1">
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Annuler</button>
                <button type="submit" class="btn btn-primary" id="modal_submit_btn">
                    <i class="fas fa-save"></i> Ajouter le produit
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 2000;
        display: flex; align-items: center; justify-content: center;
    }
    .modal-content {
        background: white; padding: 30px; border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        animation: slideDown 0.3s;
        max-height: 90vh; overflow-y: auto;
    }
    @keyframes slideDown {
        from { transform: translateY(-40px); opacity: 0; }
        to   { transform: translateY(0); opacity: 1; }
    }
</style>

<script>
    function openProductModal(product) {
        const modal = document.getElementById('productModal');
        const title = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('modal_submit_btn');

        if (product) {
            title.innerHTML = '<i class="fas fa-edit"></i> Modifier le produit';
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer les modifications';
            document.getElementById('modal_id_produit').value   = product.Id_Produit;
            document.getElementById('modal_nom').value          = product.Nom_Produit || '';
            document.getElementById('modal_description').value  = product.Description_Produit || '';
            document.getElementById('modal_prix').value         = product.Prix_Unitaire_Produit || '';
            document.getElementById('modal_stock').value        = product.Quantite_En_Stock || 0;
            document.getElementById('modal_seuil').value        = product.Seuil_Alerte_Stock ?? 5;
            document.getElementById('modal_prix_b2b').value     = product.Prix_B2B || '';
            document.getElementById('modal_qte_min').value      = product.Quantite_Min_B2B || 1;
            const chk = document.getElementById('modal_check_b2b');
            chk.checked = !!parseInt(product.En_Destockage_B2B);
            document.getElementById('modal_b2b_fields').style.display = chk.checked ? 'block' : 'none';
        } else {
            title.innerHTML = '<i class="fas fa-plus"></i> Nouveau produit';
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Ajouter le produit';
            document.getElementById('modal_id_produit').value   = '';
            document.getElementById('modal_nom').value          = '';
            document.getElementById('modal_description').value  = '';
            document.getElementById('modal_prix').value         = '';
            document.getElementById('modal_stock').value        = '0';
            document.getElementById('modal_seuil').value        = '5';
            document.getElementById('modal_prix_b2b').value     = '';
            document.getElementById('modal_qte_min').value      = '1';
            document.getElementById('modal_check_b2b').checked  = false;
            document.getElementById('modal_b2b_fields').style.display = 'none';
        }

        modal.style.display = 'flex';
    }

    function closeProductModal() {
        document.getElementById('productModal').style.display = 'none';
    }

    function toggleModalB2B() {
        const chk = document.getElementById('modal_check_b2b');
        document.getElementById('modal_b2b_fields').style.display = chk.checked ? 'block' : 'none';
    }

    window.onclick = function(e) {
        const modal = document.getElementById('productModal');
        if (e.target === modal) closeProductModal();
    };

    function filterProducts() {
        const filter = document.getElementById('searchProduct').value.toUpperCase();
        const rows   = document.querySelectorAll('.table tbody tr');
        rows.forEach(row => {
            const td = row.getElementsByTagName('td')[0];
            if (td) {
                row.style.display = td.textContent.toUpperCase().includes(filter) ? '' : 'none';
            }
        });
    }

    <?php if ($edit_mode): ?>
    window.addEventListener('DOMContentLoaded', function() {
        openProductModal(<?= json_encode($product_data) ?>);
    });
    <?php endif; ?>
</script>

</body>
</html>