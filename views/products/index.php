<style>
.pr-stock-alert {
    color: var(--bs-danger);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.85rem;
}

.pr-barcode { font-family: 'Plus Jakarta Sans', monospace; font-size: 0.8rem; }

.pr-b2b-toggle-form { display: inline-block; }

.pr-b2b-toggle {
    display: inline-flex;
    cursor: pointer;
}

.pr-b2b-toggle input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--bs-primary);
}

/* Champ + bouton scanner soudés en un seul groupe visuel */
.pr-input-group { display: flex; }

.pr-input-group .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: none;
}

.pr-input-group .btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    flex-shrink: 0;
}
</style>

<div class="container fade-in py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <p class="text-body-secondary mb-0">
            <?php if ($readonly): ?>
                <i class="fas fa-lock"></i> Consultation uniquement — droits restreints
            <?php else: ?>
                Catalogue, alertes de stock et déstockage B2B
            <?php endif; ?>
        </p>
        <?php if (!$readonly): ?>
        <div class="d-flex gap-2">
            <a href="approvisionnement.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-truck-loading"></i> Approvisionner
            </a>
            <button class="btn btn-outline-secondary btn-sm" onclick="openProductModal(); openScanChooser('modal_code_barre_unite');">
                <i class="fas fa-camera"></i> Scanner
            </button>
            <button class="btn btn-primary btn-sm" onclick="openProductModal()">
                <i class="fas fa-plus"></i> Ajouter un produit
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- KPI STRIP -->
    <div class="dash-kpi-row">
        <div class="dash-kpi">
            <div class="dash-kpi-label">Total catalogue</div>
            <div class="dash-kpi-value"><?= $total_produits ?></div>
        </div>
        <div class="dash-kpi is-primary">
            <div class="dash-kpi-label">Valeur du stock</div>
            <div class="dash-kpi-value"><?= number_format($total_stock_val, 0, ',', ' ') ?> <small>FCFA</small></div>
        </div>
        <div class="dash-kpi <?= $produits_alerte > 0 ? 'is-danger' : '' ?>">
            <div class="dash-kpi-label">En alerte de stock</div>
            <div class="dash-kpi-value"><?= $produits_alerte ?></div>
        </div>
    </div>

    <!-- LISTE DES PRODUITS -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h6 mb-0">Liste des produits (<?= count($produits) ?>)</h2>
            <div class="d-flex gap-2">
                <select id="filterProduct" class="form-select form-select-sm" style="width:auto;" onchange="filterProducts()">
                    <option value="">Tous les produits</option>
                    <option value="alerte">En alerte de stock</option>
                    <option value="b2b">En déstockage B2B</option>
                </select>
                <div class="input-group input-group-sm" style="max-width:220px;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchProduct" class="form-control" placeholder="Rechercher par nom..." onkeyup="filterProducts()">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover pr-table mb-0">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Code-barres</th>
                        <th class="text-end">Prix</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Seuil</th>
                        <th class="text-center">B2B</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $i => $p):
                        $seuil = (int) ($p['Seuil_Alerte_Stock'] ?? 5);
                        $qte   = (int) $p['Quantite_En_Stock'];
                        $is_low = ($qte <= $seuil);
                    ?>
                        <tr style="animation: fadeInUp 0.3s <?= $i * 20 ?>ms both;" data-alert="<?= $is_low ? '1' : '0' ?>" data-b2b="<?= $p['En_Destockage_B2B'] ? '1' : '0' ?>">
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($p['Nom_Produit'] ?? '') ?></div>
                                <?php if (!empty($p['Description_Produit'])): ?>
                                    <div class="small text-body-secondary"><?= htmlspecialchars($p['Description_Produit']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="pr-barcode text-body-secondary">
                                <?= !empty($p['Code_Barre_Unite']) ? htmlspecialchars($p['Code_Barre_Unite']) : '—' ?>
                                <?php if (!empty($p['Code_Barre_Carton'])): ?>
                                    <div class="text-body-tertiary" style="font-size:.72rem;">Carton : <?= htmlspecialchars($p['Code_Barre_Carton']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold" style="font-family:'Plus Jakarta Sans',sans-serif;">
                                <?= number_format($p['Prix_Unitaire_Produit'], 0, ',', ' ') ?> F
                            </td>
                            <td class="text-center">
                                <?php if ($is_low): ?>
                                    <span class="pr-stock-alert" title="Seuil d'alerte configuré à <?= $seuil ?>">
                                        <i class="fas fa-triangle-exclamation"></i> <?= $qte ?>
                                    </span>
                                <?php else: ?>
                                    <?= $qte ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-body-secondary"><?= $seuil ?></td>
                            <td class="text-center">
                                <?php if (!$readonly): ?>
                                    <form method="POST" action="products.php" class="pr-b2b-toggle-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="toggle_b2b">
                                        <input type="hidden" name="id_produit" value="<?= $p['Id_Produit'] ?>">
                                        <label class="pr-b2b-toggle" title="<?= $p['En_Destockage_B2B'] ? 'Retirer du déstockage B2B' : 'Ajouter au déstockage B2B' ?>">
                                            <input type="checkbox" name="enabled" value="1"
                                                <?= $p['En_Destockage_B2B'] ? 'checked' : '' ?>
                                                onchange="this.form.submit()">
                                        </label>
                                    </form>
                                <?php else: ?>
                                    <span class="badge <?= $p['En_Destockage_B2B'] ? 'text-bg-primary' : 'text-bg-light' ?>"><?= $p['En_Destockage_B2B'] ? 'Oui' : 'Non' ?></span>
                                <?php endif; ?>
                                <?php if ($p['En_Destockage_B2B']): ?>
                                    <div class="small fw-bold text-primary mt-1" style="font-family:'Plus Jakarta Sans',sans-serif;">
                                        <?= number_format($p['Prix_B2B'], 0, ',', ' ') ?> F
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <?php if (!$readonly): ?>
                                <button onclick="openProductModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="products.php" class="d-inline-block ms-1" id="delete-form-<?= $p['Id_Produit'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_produit" value="<?= $p['Id_Produit'] ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Supprimer" onclick="confirmDeleteProduct('delete-form-<?= $p['Id_Produit'] ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-body-secondary small"><i class="fas fa-lock"></i> Consult</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($produits)): ?>
                <div class="text-center text-body-secondary py-5">
                    <i class="fas fa-box-open fs-2 opacity-50 d-block mb-2"></i>
                    <p class="mb-0 small">Aucun produit dans votre catalogue.</p>
                </div>
            <?php endif; ?>
            <div id="noResultsRow" class="text-center text-body-secondary py-4 d-none">
                <p class="mb-0 small">Aucun produit ne correspond à cette recherche.</p>
            </div>
        </div>
    </div>
</div>

<!-- MODALE DE CONFIRMATION — SUPPRESSION PRODUIT -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fas fa-triangle-exclamation text-danger fs-2 mb-3"></i>
                <p class="fw-semibold mb-1">Supprimer ce produit ?</p>
                <p class="text-body-secondary small mb-0">Cette action est irréversible.</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="deleteProductConfirmBtn">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- MODALE AJOUT / MODIFICATION PRODUIT -->
<div id="productModal" class="modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" class="modal-title fs-5"><i class="fas fa-box"></i> Nouveau produit</h3>
            <button type="button" class="btn-close" onclick="closeProductModal()" aria-label="Fermer"></button>
        </div>

        <form method="POST" action="products.php">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_produit" id="modal_id_produit" value="">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nom du produit *</label>
                    <input type="text" name="nom" id="modal_nom" required class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" id="modal_description" class="form-control">
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <label class="form-label">Prix Unitaire (FCFA) *</label>
                    <input type="number" step="0.01" name="prix" id="modal_prix" required class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Stock initial *</label>
                    <input type="number" name="stock" id="modal_stock" required class="form-control" value="0">
                </div>
            </div>

            <div class="mb-0 mt-3">
                <label class="form-label">Seuil d'alerte stock</label>
                <input type="number" name="seuil_alerte" id="modal_seuil" class="form-control" value="5" min="0"
                       title="Une alerte apparaît quand le stock est inférieur ou égal à ce seuil">
            </div>

            <div class="card bg-body-tertiary border-0 mt-3">
                <div class="card-body">
                <div class="text-uppercase small fw-bold text-body-secondary mb-3"><i class="fas fa-barcode"></i> Codes-barres</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Code-barre (unité)</label>
                        <div class="pr-input-group">
                            <input type="text" name="code_barre_unite" id="modal_code_barre_unite" class="form-control" placeholder="Scanner ou saisir..." autocomplete="off">
                            <button type="button" class="btn btn-dark" onclick="openScanChooser('modal_code_barre_unite')" title="Scanner un code-barre">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Code-barre (carton)</label>
                        <div class="pr-input-group">
                            <input type="text" name="code_barre_carton" id="modal_code_barre_carton" class="form-control" placeholder="Optionnel" autocomplete="off">
                            <button type="button" class="btn btn-dark" onclick="openScanChooser('modal_code_barre_carton')" title="Scanner un code-barre">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-2 mt-3">
                    <label class="form-label">Unités par carton</label>
                    <input type="number" name="quantite_par_carton" id="modal_qte_carton" class="form-control" value="1" min="1">
                </div>

                <span id="scan-feedback" class="d-block small text-body-secondary mb-1"></span>
                <div id="phone-status-row" class="d-none align-items-center gap-2 mb-1 small">
                    <span id="phone-status-badge">⚪ Téléphone déconnecté</span>
                    <span id="phone-countdown" class="text-body-secondary"></span>
                    <button type="button" class="btn btn-danger btn-sm py-0" id="btn-phone-disconnect" onclick="revokePhoneScanner()">
                        Déconnecter le téléphone
                    </button>
                </div>
                <p class="small text-body-secondary mb-0">
                    <i class="fas fa-circle-info"></i>
                    Cliquez sur <i class="fas fa-camera"></i>, choisissez la webcam de ce PC ou votre téléphone, puis pointez vers le code-barre — il se remplit automatiquement.
                </p>
                </div>
            </div>

            <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" name="en_destockage_b2b" value="1" id="modal_check_b2b" onchange="toggleModalB2B()">
                <label class="form-check-label fw-bold" for="modal_check_b2b">Mettre en Déstockage B2B</label>
            </div>

            <div id="modal_b2b_fields" class="card bg-body-tertiary border-0 mt-3 d-none">
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Prix Spécial B2B (FCFA)</label>
                        <input type="number" step="0.01" name="prix_b2b" id="modal_prix_b2b" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Quantité Min. Achat B2B</label>
                        <input type="number" name="quantite_min_b2b" id="modal_qte_min" class="form-control" value="1">
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Annuler</button>
            <button type="submit" class="btn btn-primary" id="modal_submit_btn">
                <i class="fas fa-save"></i> Ajouter le produit
            </button>
        </div>
        </form>
    </div>
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

        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function closeProductModal() {
        const modal = document.getElementById('productModal');
        const instance = bootstrap.Modal.getInstance(modal);
        if (instance) instance.hide();
    }

    function toggleModalB2B() {
        const chk = document.getElementById('modal_check_b2b');
        document.getElementById('modal_b2b_fields').style.display = chk.checked ? 'block' : 'none';
    }

    function filterProducts() {
        const search = document.getElementById('searchProduct').value.toUpperCase();
        const filterSelect = document.getElementById('filterProduct');
        const filterVal = filterSelect ? filterSelect.value : '';
        const rows = document.querySelectorAll('.pr-table tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const td = row.getElementsByTagName('td')[0];
            const matchesSearch = !td || td.textContent.toUpperCase().includes(search);
            let matchesFilter = true;
            if (filterVal === 'alerte') matchesFilter = row.dataset.alert === '1';
            else if (filterVal === 'b2b') matchesFilter = row.dataset.b2b === '1';

            const visible = matchesSearch && matchesFilter;
            row.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });

        const noResults = document.getElementById('noResultsRow');
        if (noResults) noResults.classList.toggle('d-none', rows.length === 0 || visibleCount > 0);
    }

    /* ── CONFIRMATION DE SUPPRESSION ── */
    let productFormToDelete = null;

    function confirmDeleteProduct(formId) {
        productFormToDelete = document.getElementById(formId);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteProductModal')).show();
    }

    document.getElementById('deleteProductConfirmBtn').addEventListener('click', function() {
        if (productFormToDelete) productFormToDelete.submit();
    });

    <?php if ($edit_mode): ?>
    window.addEventListener('DOMContentLoaded', function() {
        openProductModal(<?= json_encode($product_data) ?>);
    });
    <?php endif; ?>

    /* ── CHOIX DU MODE DE SCAN ── */
    function openScanChooser(targetFieldId) {
        productScanTargetField = targetFieldId;
        document.getElementById('scanChooserModal').style.display = 'flex';
    }

    function closeScanChooser() {
        document.getElementById('scanChooserModal').style.display = 'none';
    }

    function chooseWebcamScan() {
        closeScanChooser();
        openProductCamera(productScanTargetField);
    }

    function choosePhoneScan() {
        closeScanChooser();
        openPhoneScanner();
    }

    function applyScannedBarcode(barcode, fromPhone) {
        document.getElementById(productScanTargetField).value = barcode;
        const feedback = document.getElementById('scan-feedback');
        feedback.textContent = (fromPhone ? '📱 ' : '') + '✔ Code capturé : ' + barcode;
        feedback.style.color = 'var(--success)';
    }

    /* ── CAMÉRA (scan code-barre produit) ── */
    let productQrScanner = null;
    let productScanTargetField = 'modal_code_barre_unite';

    function openProductCamera(targetFieldId) {
        productScanTargetField = targetFieldId;
        document.getElementById('productCameraModal').style.display = 'flex';
        document.getElementById('product-cam-status').textContent = 'Pointez la caméra vers un code barre…';
        document.getElementById('product-cam-status').style.color = '#aaa';
        document.getElementById('product-cam-fallback-phone').style.display = 'none';

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
            const noCamera = /NotFoundError|NotAllowedError|NotReadableError/i.test(String(err));
            document.getElementById('product-cam-status').textContent = noCamera
                ? "⚠ Aucune caméra utilisable sur cet ordinateur."
                : '⚠ Caméra inaccessible : ' + err;
            document.getElementById('product-cam-status').style.color = '#dc3545';
            document.getElementById('product-cam-fallback-phone').style.display = 'block';
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
        <div id="product-cam-fallback-phone" style="display:none; padding:0 20px 14px;">
            <button type="button" class="btn btn-primary" style="width:100%;" onclick="closeProductCamera(); openPhoneScanner();">
                <i class="fas fa-mobile-alt"></i> Utiliser mon téléphone à la place
            </button>
        </div>
        <div style="padding:0 20px 20px;">
            <button type="button" onclick="closeProductCamera()" class="btn btn-secondary" style="width:100%;">
                <i class="fas fa-times-circle"></i> Annuler
            </button>
        </div>
    </div>
</div>

<!-- MODAL CHOIX DU MODE DE SCAN -->
<div id="scanChooserModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
     z-index:9600; flex-direction:column; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
    <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:380px; box-shadow:0 8px 32px rgba(0,0,0,.6); margin:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44;">
            <span style="color:#fff; font-weight:600; font-size:1rem;">
                <i class="fas fa-barcode" style="color:#28a745; margin-right:8px;"></i>Comment voulez-vous scanner ?
            </span>
            <button onclick="closeScanChooser()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding:20px; display:flex; flex-direction:column; gap:12px;">
            <button type="button" class="btn btn-secondary" style="width:100%; padding:16px; text-align:left; display:flex; align-items:center; gap:12px;" onclick="chooseWebcamScan()">
                <i class="fas fa-camera" style="font-size:1.4rem; color:var(--primary);"></i>
                <span>
                    <strong style="display:block;">Webcam de cet ordinateur</strong>
                    <small style="color:var(--text-muted);">Nécessite une caméra branchée sur ce PC</small>
                </span>
            </button>
            <button type="button" class="btn btn-secondary" style="width:100%; padding:16px; text-align:left; display:flex; align-items:center; gap:12px;" onclick="choosePhoneScan()">
                <i class="fas fa-mobile-alt" style="font-size:1.4rem; color:var(--primary);"></i>
                <span>
                    <strong style="display:block;">Mon téléphone</strong>
                    <small style="color:var(--text-muted);">Génère un QR Code à scanner avec votre téléphone</small>
                </span>
            </button>
        </div>
    </div>
</div>

<!-- MODAL SCANNER TÉLÉPHONE (QR CODE) -->
<div id="phoneModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
     z-index:9600; flex-direction:column; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
    <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:380px; max-height:90vh; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,.6); margin:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44;">
            <span style="color:#fff; font-weight:600; font-size:1rem;">
                <i class="fas fa-mobile-alt" style="color:#28a745; margin-right:8px;"></i>Scanner avec mon téléphone
            </span>
            <button onclick="closePhoneModal()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding:24px; text-align:center;">
            <div id="phone-qr-container" style="background:#fff; border-radius:12px; padding:16px; display:inline-block; min-height:220px; min-width:220px; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-circle-notch fa-spin" style="color:#333; font-size:1.6rem;"></i>
            </div>
            <p id="phone-modal-status" style="color:#ddd; font-size:0.9rem; margin:16px 0 4px;">Génération du QR Code…</p>
            <p style="color:#888; font-size:0.78rem; margin:0;">
                Scannez ce QR Code avec l'appareil photo de votre téléphone (même réseau Wi-Fi que ce PC), puis scannez le code-barre — il remplit automatiquement le champ.
            </p>
        </div>
        <div style="padding:0 20px 20px;">
            <button onclick="closePhoneModal()" class="btn btn-secondary" style="width:100%;">
                <i class="fas fa-times-circle"></i> Fermer (le téléphone reste connecté)
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
/* ── SCANNER TÉLÉPHONE DISTANT ── */
let scannerCsrfTokens = <?= json_encode([jetonCsrf(), jetonCsrf(), jetonCsrf()]) ?>;
let scanSessionId = null;
let scanSessionSinceId = 0;
let scanSessionPollTimer = null;

function nextScannerCsrfToken() {
    return scannerCsrfTokens.length ? scannerCsrfTokens.shift() : '';
}

function openPhoneScanner() {
    document.getElementById('phoneModal').style.display = 'flex';

    if (scanSessionId) {
        return;
    }

    document.getElementById('phone-qr-container').innerHTML = '<i class="fas fa-circle-notch fa-spin" style="color:#333; font-size:1.6rem;"></i>';
    document.getElementById('phone-modal-status').textContent = 'Génération du QR Code…';

    const body = new URLSearchParams({ action: 'create', mode: 'texte', csrf_token: nextScannerCsrfToken() });
    fetch('../api/scan_session.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('phone-modal-status').textContent = 'Erreur : ' + (data.error || 'impossible de créer la session.');
                return;
            }
            scanSessionId = data.id_scan_session;
            scanSessionSinceId = 0;

            document.getElementById('phone-qr-container').innerHTML = '';
            new QRCode(document.getElementById('phone-qr-container'), {
                text: data.join_url,
                width: 220,
                height: 220,
            });
            document.getElementById('phone-modal-status').textContent = 'En attente du téléphone…';

            document.getElementById('phone-status-row').style.display = 'flex';
            setPhoneStatus('en_attente');
            startScanSessionPolling();
        })
        .catch(() => {
            document.getElementById('phone-modal-status').textContent = 'Impossible de communiquer avec FactuPro.';
        });
}

function closePhoneModal() {
    document.getElementById('phoneModal').style.display = 'none';
}

function setPhoneStatus(statut) {
    const badge = document.getElementById('phone-status-badge');
    if (statut === 'connecte') {
        badge.textContent = '🟢 Téléphone connecté';
    } else if (statut === 'en_attente') {
        badge.textContent = '⚪ En attente de connexion…';
    } else {
        badge.textContent = '⚪ Téléphone déconnecté';
    }
}

function startScanSessionPolling() {
    stopScanSessionPolling();
    scanSessionPollTimer = setInterval(() => {
        if (!scanSessionId) return;
        fetch(`../api/scan_session.php?action=poll&id=${scanSessionId}&since_id=${scanSessionSinceId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                setPhoneStatus(data.statut);
                if (data.statut === 'connecte') {
                    document.getElementById('phone-modal-status').textContent = '🟢 Téléphone connecté — continuez à scanner sur votre téléphone.';
                }

                (data.scans || []).forEach(scan => {
                    scanSessionSinceId = Math.max(scanSessionSinceId, scan.id_scan);
                    applyScannedBarcode(scan.barcode, true);
                });

                if (['expire', 'revoque', 'introuvable'].includes(data.statut)) {
                    resetPhoneScanner();
                }
            })
            .catch(() => { /* réseau temporairement indisponible — on retentera au prochain tick */ });
    }, 1200);
}

function stopScanSessionPolling() {
    if (scanSessionPollTimer) {
        clearInterval(scanSessionPollTimer);
        scanSessionPollTimer = null;
    }
}

function revokePhoneScanner() {
    if (!scanSessionId) return;
    const body = new URLSearchParams({ action: 'revoke', id_scan_session: scanSessionId, csrf_token: nextScannerCsrfToken() });
    fetch('../api/scan_session.php', { method: 'POST', body })
        .finally(() => resetPhoneScanner());
}

function resetPhoneScanner() {
    stopScanSessionPolling();
    scanSessionId = null;
    scanSessionSinceId = 0;
    document.getElementById('phone-status-row').style.display = 'none';
    document.getElementById('phone-qr-container').innerHTML = '<i class="fas fa-circle-notch fa-spin" style="color:#333; font-size:1.6rem;"></i>';
}
</script>

</body>
</html>
