<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

// livreur et admin plateforme : pas d'accès aux produits
exigerPermission(peutVoirProduits());

$readonly = !peutGererStock(); // vendeur = lecture seule

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
    exigerCsrf();
    if ($readonly) {
        $error = "Accès refusé : vous n'avez pas la permission de modifier les produits.";
        goto skip_product_form;
    }

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
skip_product_form:

// === GESTION DE L'AFFICHAGE POUR ÉDITION ===
if (isset($_GET['edit'])) {
    $product_data = $productService->find((int) $_GET['edit'], (int) $entreprise_id);
    if ($product_data) {
        $edit_mode = true;
    }
}

// === RÉCUPÉRATION DE LA LISTE DES PRODUITS ===
$produits = $productService->list((int) $entreprise_id);

// Statistiques sur le stock
$total_produits = count($produits);
$total_stock_val = 0;
$produits_alerte = 0;
foreach ($produits as $p) {
    $total_stock_val += $p['Quantite_En_Stock'] * $p['Prix_Unitaire_Produit'];
    if ((int)$p['Quantite_En_Stock'] <= (int)($p['Seuil_Alerte_Stock'] ?? 5)) {
        $produits_alerte++;
    }
}
?>

<style>
.pr-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.pr-search {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--zinc-100);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0 14px;
    transition: border-color var(--duration) var(--ease);
}

.pr-search:focus-within {
    border-color: var(--primary);
    background: var(--bg-card);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.pr-search i { color: var(--text-muted); font-size: 0.85rem; }
.pr-search input {
    background: transparent;
    border: none;
    outline: none;
    padding: 9px 0;
    font-size: 0.875rem;
    color: var(--text-main);
    font-family: inherit;
    width: 240px;
}

/* Le card de la liste a padding:0 (pour que l'en-tête et le tableau touchent le bord) —
   on recrée un vrai espace intérieur sur la première/dernière colonne, aligné sur les
   20px/24px déjà utilisés par l'en-tête au-dessus du tableau. */
.pr-table th:first-child,
.pr-table td:first-child { padding-left: 24px; }

.pr-table th:last-child,
.pr-table td:last-child { padding-right: 24px; }

.pr-table tr:first-child td { padding-top: 18px; }
.pr-table tr:last-child td { padding-bottom: 18px; }

.pr-stock-alert {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.85rem;
}

.b2b-active {
    background: var(--primary-light);
    color: var(--primary);
    box-shadow: 0 0 0 1px rgba(79, 110, 247, 0.15);
}

.b2b-inactive {
    background: var(--zinc-100);
    color: var(--text-muted);
}

/* Section groupée dans le formulaire produit (codes-barres, B2B...) */
.pr-section-box {
    background: var(--zinc-100);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 22px;
    margin-bottom: 20px;
}

.pr-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Champ + bouton scanner soudés en un seul groupe visuel */
.pr-input-group {
    display: flex;
}

.pr-input-group .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: none;
    height: 42px;
}

.pr-input-group .btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border: 1.5px solid var(--zinc-200);
    height: 42px;
    flex-shrink: 0;
}

.pr-input-group:focus-within .form-control,
.pr-input-group:focus-within .btn {
    border-color: var(--primary);
}
</style>

<div class="container fade-in">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-box"></i> Gestion des Produits</h1>
            <?php if ($readonly): ?>
                <p style="color:var(--text-muted); margin:4px 0 0; font-size:0.875rem;">
                    <i class="fas fa-lock"></i> Consultation uniquement — droits restreints
                </p>
            <?php else: ?>
                <p>Gérez votre catalogue de produits, alertes de stock et déstockage B2B</p>
            <?php endif; ?>
        </div>
        <?php if (!$readonly): ?>
        <button class="btn btn-primary" onclick="openProductModal()">
            <i class="fas fa-plus"></i> Ajouter un produit
        </button>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- KPI STRIP -->
    <div class="grid-3 stagger-children" style="margin-bottom: 28px;">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--primary-light); color:var(--primary);">
                <i class="fas fa-cubes"></i>
            </div>
            <div class="stat-info">
                <h3>Total catalogue</h3>
                <div class="stat-value"><?= $total_produits ?></div>
            </div>
        </div>
        <div class="stat-card gradient-blue">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <h3>Valeur du stock</h3>
                <div class="stat-value" style="font-size:1.45rem;"><?= number_format($total_stock_val, 0, ',', ' ') ?> F</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:<?= $produits_alerte > 0 ? 'var(--danger-bg)' : 'var(--success-bg)' ?>; color:<?= $produits_alerte > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h3>En rupture / Seuil</h3>
                <div class="stat-value" style="color:<?= $produits_alerte > 0 ? 'var(--danger)' : 'var(--text-main)' ?>;"><?= $produits_alerte ?></div>
            </div>
        </div>
    </div>

    <!-- LISTE DES PRODUITS -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:12px;">
            <h2 style="margin:0; font-size:1.05rem;">Liste des produits (<?= count($produits) ?>)</h2>
            <div class="pr-toolbar">
                <div class="pr-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchProduct" placeholder="Rechercher par nom..." onkeyup="filterProducts()">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table pr-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th class="text-right">Prix Unitaire</th>
                        <th class="text-center">Stock Actuel</th>
                        <th class="text-center">Déstockage B2B</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $i => $p):
                        $seuil = (int) ($p['Seuil_Alerte_Stock'] ?? 5);
                        $qte   = (int) $p['Quantite_En_Stock'];
                        $is_low = ($qte <= $seuil);
                    ?>
                        <tr style="animation: fadeInUp 0.3s <?= $i * 20 ?>ms both;">
                            <td>
                                <div style="font-weight:700;"><?= htmlspecialchars($p['Nom_Produit'] ?? '') ?></div>
                                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                    <?= htmlspecialchars($p['Description_Produit'] ?? '-') ?>
                                </div>
                            </td>
                            <td class="text-right font-weight-bold" style="font-family:'Plus Jakarta Sans',sans-serif;">
                                <?= number_format($p['Prix_Unitaire_Produit'], 0, ',', ' ') ?> F
                            </td>
                            <td class="text-center">
                                <?php if ($is_low): ?>
                                    <span class="pr-stock-alert" title="Seuil d'alerte configuré à <?= $seuil ?>">
                                        <i class="fas fa-exclamation-triangle"></i> <?= $qte ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="font-weight:600;"><?= $qte ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($p['En_Destockage_B2B']): ?>
                                    <span class="badge b2b-active">Oui</span>
                                    <div style="font-size:0.75rem; font-weight:700; color:var(--primary); margin-top:4px; font-family:'Plus Jakarta Sans',sans-serif;">
                                        <?= number_format($p['Prix_B2B'], 0, ',', ' ') ?> F
                                    </div>
                                <?php else: ?>
                                    <span class="badge b2b-inactive">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" style="white-space:nowrap;">
                                <?php if (!$readonly): ?>
                                <button onclick="openProductModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" class="btn btn-sm btn-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="products.php" style="display:inline-block; margin-left:4px;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_produit" value="<?= $p['Id_Produit'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.85rem;"><i class="fas fa-lock"></i> Consult</span>
                                <?php endif; ?>
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
    <div class="modal-content" style="max-width:650px; width:95%; padding:28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0;"><i class="fas fa-box"></i> Nouveau produit</h3>
            <button onclick="closeProductModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.2rem;"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" action="products.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_produit" id="modal_id_produit" value="">

            <div class="form-row">
                <div class="form-group">
                    <label>Nom du produit *</label>
                    <input type="text" name="nom" id="modal_nom" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="modal_description" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Prix Unitaire (FCFA) *</label>
                    <input type="number" step="0.01" name="prix" id="modal_prix" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Stock initial *</label>
                    <input type="number" name="stock" id="modal_stock" required class="form-control" value="0">
                </div>
            </div>

            <div class="form-group">
                <label>Seuil d'alerte stock</label>
                <input type="number" name="seuil_alerte" id="modal_seuil" class="form-control" value="5" min="0"
                       title="Une alerte apparaît quand le stock est inférieur ou égal à ce seuil">
            </div>

            <div class="pr-section-box">
                <div class="pr-section-title"><i class="fas fa-barcode"></i> Codes-barres</div>

                <div class="form-row">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Code-barre (unité)</label>
                        <div class="pr-input-group">
                            <input type="text" name="code_barre_unite" id="modal_code_barre_unite" class="form-control" placeholder="Scanner ou saisir..." autocomplete="off">
                            <button type="button" class="btn btn-dark" onclick="openProductCamera('modal_code_barre_unite')" title="Scanner avec la caméra">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Code-barre (carton)</label>
                        <div class="pr-input-group">
                            <input type="text" name="code_barre_carton" id="modal_code_barre_carton" class="form-control" placeholder="Optionnel" autocomplete="off">
                            <button type="button" class="btn btn-dark" onclick="openProductCamera('modal_code_barre_carton')" title="Scanner avec la caméra">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:8px;">
                    <label>Unités par carton</label>
                    <input type="number" name="quantite_par_carton" id="modal_qte_carton" class="form-control" value="1" min="1">
                </div>

                <p style="font-size:0.76rem; color:var(--text-muted); margin:0;">
                    <i class="fas fa-circle-info"></i>
                    Pour scanner : cliquez sur <i class="fas fa-camera"></i>, autorisez la caméra, puis pointez vers le code-barre — il se remplit automatiquement.
                </p>
            </div>

            <div class="form-group" style="margin-bottom:15px; margin-top:20px;">
                <label style="font-weight:700; display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="en_destockage_b2b" value="1" id="modal_check_b2b" onchange="toggleModalB2B()" style="width:16px; height:16px;">
                    Mettre en Déstockage B2B
                </label>
            </div>

            <div id="modal_b2b_fields" class="pr-section-box" style="display:none;">
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Prix Spécial B2B (FCFA)</label>
                        <input type="number" step="0.01" name="prix_b2b" id="modal_prix_b2b" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Quantité Min. Achat B2B</label>
                        <input type="number" name="quantite_min_b2b" id="modal_qte_min" class="form-control" value="1">
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:25px; border-top:1px solid var(--border); padding-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Annuler</button>
                <button type="submit" class="btn btn-primary" id="modal_submit_btn">
                    <i class="fas fa-save"></i> Ajouter le produit
                </button>
            </div>
        </form>
    </div>
</div>

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
            document.getElementById('modal_code_barre_unite').value  = product.Code_Barre_Unite || '';
            document.getElementById('modal_code_barre_carton').value = product.Code_Barre_Carton || '';
            document.getElementById('modal_qte_carton').value        = product.Quantite_Par_Carton || 1;
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
            document.getElementById('modal_code_barre_unite').value  = '';
            document.getElementById('modal_code_barre_carton').value = '';
            document.getElementById('modal_qte_carton').value        = '1';
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

    /* ── CAMÉRA (scan code-barre produit) ── */
    let productQrScanner = null;
    let productScanTargetField = 'modal_code_barre_unite';

    function openProductCamera(targetFieldId) {
        productScanTargetField = targetFieldId;
        document.getElementById('productCameraModal').style.display = 'flex';
        document.getElementById('product-cam-status').textContent = 'Pointez la caméra vers un code barre…';
        document.getElementById('product-cam-status').style.color = '#aaa';

        productQrScanner = new Html5Qrcode('product-camera-reader');
        productQrScanner.start(
            { facingMode: 'environment' },
            { fps: 12, qrbox: { width: 260, height: 120 }, aspectRatio: 1.333334 },
            (decodedText) => {
                document.getElementById(productScanTargetField).value = decodedText;
                document.getElementById('product-cam-status').textContent = '✔ Code détecté : ' + decodedText;
                document.getElementById('product-cam-status').style.color = '#28a745';
                setTimeout(closeProductCamera, 600);
            },
            () => {}
        ).catch((err) => {
            document.getElementById('product-cam-status').textContent = '⚠ Caméra inaccessible : ' + err;
            document.getElementById('product-cam-status').style.color = '#dc3545';
        });
    }

    function closeProductCamera() {
        // Si la caméra n'a jamais réussi à démarrer (ex: NotFoundError), .stop() peut lever
        // une exception SYNCHRONE plutôt qu'une promesse rejetée — Promise.resolve().then(...)
        // capture les deux cas, et le try/finally garantit que la modale se ferme dans tous les cas.
        try {
            const scanner = productQrScanner;
            productQrScanner = null;
            if (scanner) {
                Promise.resolve()
                    .then(() => scanner.stop())
                    .catch(() => {})
                    .finally(() => {
                        try { scanner.clear(); } catch (e) {}
                    });
            }
        } finally {
            document.getElementById('productCameraModal').style.display = 'none';
        }
    }
</script>

<!-- MODAL CAMÉRA (produit) -->
<div id="productCameraModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
     z-index:9500; flex-direction:column; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
    <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:420px; max-height:90vh; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,.6); margin:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44; position:sticky; top:0; background:#1a1a2e; z-index:1;">
            <span style="color:#fff; font-weight:600; font-size:1rem;">
                <i class="fas fa-camera" style="color:#28a745; margin-right:8px;"></i>Scanner un code barre
            </span>
            <button onclick="closeProductCamera()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="position:relative; background:#000; max-height:280px; overflow:hidden;">
            <div id="product-camera-reader" style="width:100%;"></div>
        </div>
        <p id="product-cam-status" style="margin:0; padding:14px 20px; color:#aaa; font-size:0.9rem; text-align:center;">Initialisation…</p>
        <div style="padding:0 20px 20px;">
            <button type="button" onclick="closeProductCamera()" class="btn btn-secondary" style="width:100%;">
                <i class="fas fa-times-circle"></i> Annuler
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

</body>
</html>