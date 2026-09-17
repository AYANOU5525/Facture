<style>
    /* .item-row/.item-row-legend/.qte-carton-input/.qte-unite-input/.product-select/.item-subtotal-inline
       are generated dynamically by JS below (addItem()) and read back via querySelector — class names
       are load-bearing, kept exactly as-is. Only their visual styling is refreshed here. */
    .scanner-box {
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        border-left: 4px solid var(--bs-primary);
        padding: 14px;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .scanner-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    #barcode_feedback:not(:empty) {
        padding: 6px 10px;
        border-radius: 6px;
        background: var(--bs-tertiary-bg);
        font-weight: 600;
    }
    .item-row {
        display: grid;
        grid-template-columns: 1fr 80px 80px 130px 36px;
        gap: 8px;
        align-items: center;
        margin-bottom: 8px;
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 8px;
        padding: 10px 12px;
        transition: border-color 0.2s;
    }
    .item-row:hover { border-color: var(--bs-primary); }
    .sale-summary-card {
        background: var(--bs-card-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        padding: 24px;
        position: sticky;
        top: 80px;
    }
    .sale-summary-rows { margin-bottom: 16px; }
    .sale-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        font-size: 0.88rem;
        color: var(--bs-secondary-color);
        border-bottom: 1px dashed var(--bs-border-color);
    }
    .sale-summary-row:last-child { border-bottom: none; }
    .sale-total-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 2px solid var(--bs-border-color);
        padding-top: 14px;
        font-weight: 700;
        font-size: 1.2rem;
    }
    #items-count {
        display: inline-block;
        background: var(--bs-primary);
        color: #fff;
        border-radius: 12px;
        font-size: 0.75rem;
        padding: 1px 8px;
        font-weight: 600;
    }
    .mode-remise-option { cursor: pointer; }
    .mode-remise-option input[type="radio"] { display: none; }
    .mode-remise-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 12px 8px;
        border: 2px solid var(--bs-border-color);
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--bs-secondary-color);
        background: var(--bs-tertiary-bg);
        transition: all 0.2s;
    }
    .mode-remise-card i { font-size: 1.3rem; }
    .mode-remise-option input:checked + .mode-remise-card {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary);
    }
    @media (max-width: 768px) {
        .sale-summary-card { position: static; }
        .item-row { grid-template-columns: 1fr 70px 70px 36px; }
        .item-subtotal-inline { display: none; }
    }
</style>

<div class="container fade-in py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <p class="text-body-secondary small mb-0">
            Vendeur : <strong><?= htmlspecialchars($nom_vendeur) ?></strong>
        </p>
        <a href="sales.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list"></i> Historique des ventes</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="invoiceForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="row g-3">
            <!-- Colonne gauche : client + articles -->
            <div class="col-lg-8">
                <!-- Client -->
                <div class="card mb-3">
                    <div class="card-body">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-user text-primary"></i> Nom du client
                        </label>
                        <input type="text" name="client" class="form-control"
                               placeholder="Ex : Client Comptant, Dupont Marie..."
                               required autofocus>
                    </div>
                </div>

                <!-- Articles -->
                <div class="card">
                    <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-shopping-basket text-primary"></i>
                            Articles
                            <span id="items-count">0</span>
                        </h3>
                        <button type="button" onclick="addItem()" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </div>

                    <!-- SCANNER CODE BARRE -->
                    <div class="scanner-box">
                        <div class="scanner-row">
                            <i class="fas fa-barcode text-primary flex-shrink-0" style="font-size:1.3rem;"></i>
                            <input type="text"
                                   id="barcode_input"
                                   class="form-control"
                                   placeholder="Scanner ou saisir un code barre..."
                                   style="max-width:260px; flex:1;"
                                   autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="lookupBarcode()">
                                <i class="fas fa-search"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openScanChooser()" id="btn-scan-chooser" title="Scanner un code-barre">
                                <i class="fas fa-camera"></i> Scanner
                            </button>
                        </div>
                        <span id="barcode_feedback" class="d-block small mt-2"></span>
                        <div id="phone-status-row" class="d-none align-items-center gap-2 mt-2 small">
                            <span id="phone-status-badge">⚪ Téléphone déconnecté</span>
                            <span id="phone-countdown" class="text-body-secondary"></span>
                            <button type="button" class="btn btn-danger btn-sm py-0" id="btn-phone-disconnect" onclick="revokePhoneScanner()">
                                Déconnecter le téléphone
                            </button>
                        </div>
                        <p class="small text-body-secondary mt-2 mb-0">
                            <i class="fas fa-circle-info"></i> Un produit scanné s'ajoute automatiquement au panier.
                        </p>
                    </div>

                    <div class="item-row item-row-legend" style="background:none; border:none; padding:0 12px; margin-bottom:4px; font-size:0.72rem; font-weight:700; color:var(--bs-secondary-color); text-transform:uppercase;">
                        <span>Produit</span>
                        <span>Cartons</span>
                        <span>Unités</span>
                        <span></span>
                        <span></span>
                    </div>
                    <div id="items-container"></div>

                    <div id="empty-items" class="text-center text-body-secondary py-4">
                        <i class="fas fa-shopping-cart fs-2 opacity-25 d-block mb-2"></i>
                        Aucun article — scannez un produit ou cliquez sur Ajouter
                    </div>
                    </div>
                </div>
            </div>

            <!-- Colonne droite : récapitulatif -->
            <div class="col-lg-4">
                <div class="sale-summary-card">
                <div class="d-flex align-items-center gap-2 fw-bold mb-3">
                    <i class="fas fa-basket-shopping text-primary"></i>
                    Panier
                </div>
                <div class="sale-summary-rows" id="summary-rows">
                    <div class="text-center text-body-secondary small py-2">
                        Aucun article
                    </div>
                </div>
                <div class="sale-total-line">
                    <span>Total</span>
                    <span class="text-success" id="grand-total">0 F</span>
                </div>
                <?php if (FEATURE_LOGISTIQUE_ACTIVE): ?>
                <!-- Mode de remise -->
                <div class="mb-3 mt-3">
                    <div class="fw-semibold small text-body-secondary text-uppercase mb-2" style="letter-spacing:0.04em;">
                        Mode de remise
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                        <label class="mode-remise-option d-block">
                            <input type="radio" name="mode_remise" value="livraison" checked onchange="updateModeRemise()">
                            <div class="mode-remise-card" id="card-livraison">
                                <i class="fas fa-truck"></i>
                                <span>Livraison</span>
                            </div>
                        </label>
                        </div>
                        <div class="col-6">
                        <label class="mode-remise-option d-block">
                            <input type="radio" name="mode_remise" value="retrait" onchange="updateModeRemise()">
                            <div class="mode-remise-card" id="card-retrait">
                                <i class="fas fa-store"></i>
                                <span>Retrait</span>
                            </div>
                        </label>
                        </div>
                    </div>
                    <p id="mode-remise-hint" class="small text-body-secondary mt-2 mb-0">
                        <i class="fas fa-info-circle"></i> L'étape logistique sera incluse dans la finalisation.
                    </p>
                </div>
                <?php else: ?>
                <input type="hidden" name="mode_remise" value="retrait">
                <?php endif; ?>

                <div class="d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-success" id="btn-submit" disabled>
                        <i class="fas fa-check-circle"></i> Valider la vente
                    </button>
                    <a href="sales.php" class="btn btn-outline-secondary text-center">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let itemCount = 0;

    const products = <?php echo json_encode($produits) ?>;
    const productMap = {};
    products.forEach(p => { productMap[p.Id_Produit] = p; });

    function renderOptions(selectedValue) {
        let opts = '<option value="">— Sélectionner un produit —</option>';
        products.forEach(p => {
            const stock = parseInt(p.Quantite_En_Stock);
            const label = `${p.Nom_Produit} — ${parseInt(p.Prix_Unitaire_Produit).toLocaleString('fr-FR')} F (stock: ${stock})`;
            opts += `<option value="${p.Id_Produit}" ${p.Id_Produit == selectedValue ? 'selected' : ''}
                        data-prix="${p.Prix_Unitaire_Produit}" ${stock <= 0 ? 'disabled' : ''}>${label}</option>`;
        });
        return opts;
    }

    function addItem(selectedId = null, qteCarton = 0, qteUnite = 1) {
        const container = document.getElementById('items-container');
        const empty     = document.getElementById('empty-items');
        if (empty) empty.style.display = 'none';

        const div = document.createElement('div');
        div.className = 'item-row';
        div.setAttribute('data-id', itemCount);

        div.innerHTML = `
            <select name="items[${itemCount}][produit]" class="form-control product-select" required
                    onchange="onProductChange(this)">
                ${renderOptions(selectedId)}
            </select>
            <input type="number" name="items[${itemCount}][qte_carton]" class="form-control qte-carton-input"
                   min="0" value="${qteCarton}" placeholder="Cartons" title="Nombre de cartons" oninput="recalculate()">
            <input type="number" name="items[${itemCount}][qte_unite]" class="form-control qte-unite-input"
                   min="0" value="${qteUnite}" placeholder="Unités" title="Nombre d'unités" oninput="recalculate()">
            <span class="item-subtotal-inline" style="font-size:0.78rem; color:var(--text-muted); white-space:nowrap; text-align:right; line-height:1.3;"></span>
            <button type="button" onclick="removeItem(this)" class="btn btn-danger btn-sm" title="Supprimer">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(div);
        itemCount++;
        if (selectedId) updateCartonAvailability(div);
        updateOptions();
        recalculate();
        return div;
    }

    // Active/désactive le champ "Cartons" selon que le produit sélectionné a un conditionnement carton.
    // Le coefficient réel (Quantite_Par_Carton) n'est utilisé ici que pour l'affichage — le serveur
    // recalcule tout indépendamment à partir du produit en base au moment de la validation.
    function updateCartonAvailability(row) {
        const select = row.querySelector('.product-select');
        const cartonInput = row.querySelector('.qte-carton-input');
        const product = productMap[select.value];
        const canCarton = product && parseInt(product.Quantite_Par_Carton) > 1;

        cartonInput.disabled = !canCarton;
        cartonInput.title = canCarton
            ? `Nombre de cartons (1 carton = ${product.Quantite_Par_Carton} unités)`
            : `Ce produit n'a pas de conditionnement carton`;
        if (!canCarton) cartonInput.value = 0;
    }

    function removeItem(btn) {
        btn.closest('.item-row').remove();
        updateOptions();
        recalculate();
        const rows = document.querySelectorAll('.item-row:not(.item-row-legend)');
        if (rows.length === 0) {
            const empty = document.getElementById('empty-items');
            if (empty) empty.style.display = '';
        }
    }

    function onProductChange(select) {
        updateCartonAvailability(select.closest('.item-row'));
        updateOptions();
        recalculate();
    }

    function updateOptions() {
        const allSelects = document.querySelectorAll('.product-select');
        const selectedValues = [];
        allSelects.forEach(s => { if (s.value) selectedValues.push(s.value); });

        allSelects.forEach(select => {
            const myValue = select.value;
            Array.from(select.options).forEach(opt => {
                if (!opt.value) return;
                const alreadyTaken = selectedValues.includes(opt.value) && opt.value !== myValue;
                opt.disabled = alreadyTaken || parseInt(productMap[opt.value]?.Quantite_En_Stock ?? 1) <= 0;
            });
        });

        document.getElementById('items-count').textContent = allSelects.length;
    }

    function recalculate() {
        const rows = document.querySelectorAll('.item-row:not(.item-row-legend)');
        let total = 0;
        const summaryEl = document.getElementById('summary-rows');
        let summaryHtml = '';

        rows.forEach(row => {
            const select = row.querySelector('.product-select');
            const cartonInput = row.querySelector('.qte-carton-input');
            const uniteInput = row.querySelector('.qte-unite-input');
            const subtotalEl = row.querySelector('.item-subtotal-inline');
            const selectedOpt = select.options[select.selectedIndex];
            const prix = parseFloat(selectedOpt?.dataset?.prix ?? 0);
            const product = productMap[select.value];

            const perCarton = product ? (parseInt(product.Quantite_Par_Carton) || 1) : 1;
            const qteCarton = parseInt(cartonInput?.value) || 0;
            const qteUnite = parseInt(uniteInput?.value) || 0;
            const realQty = (qteCarton * perCarton) + qteUnite;
            const sub = prix * realQty;
            total += sub;

            if (subtotalEl) {
                subtotalEl.textContent = realQty > 0 ? `${realQty} u. = ${sub.toLocaleString('fr-FR')} F` : '';
            }

            if (select.value && prix > 0 && realQty > 0) {
                const parts = [];
                if (qteCarton > 0) parts.push(`${qteCarton} carton(s)`);
                if (qteUnite > 0) parts.push(`${qteUnite} u.`);
                summaryHtml += `<div class="sale-summary-row">
                    <span>${escHtml(selectedOpt.text.split('—')[0].trim())} (${parts.join(' + ')})</span>
                    <span>${sub.toLocaleString('fr-FR')} F</span>
                </div>`;
            }
        });

        summaryEl.innerHTML = summaryHtml || '<div style="text-align:center;color:var(--text-muted);font-size:0.85rem;padding:10px 0;">Aucun article</div>';
        document.getElementById('grand-total').textContent = total.toLocaleString('fr-FR') + ' F';
        document.getElementById('btn-submit').disabled = (rows.length === 0 || total === 0);
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function updateModeRemise() {
        const mode = document.querySelector('input[name="mode_remise"]:checked')?.value ?? 'livraison';
        const hint = document.getElementById('mode-remise-hint');
        if (hint) {
            hint.innerHTML = mode === 'retrait'
                ? '<i class="fas fa-info-circle"></i> Retrait sur place — l\'étape logistique sera ignorée.'
                : '<i class="fas fa-info-circle"></i> L\'étape logistique sera incluse dans la finalisation.';
        }
    }

    // Initialiser avec une ligne vide
    window.addEventListener('DOMContentLoaded', function() {
        addItem();
        document.getElementById('barcode_input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupBarcode();
            }
        });
    });

    /* ── CHOIX DU MODE DE SCAN ── */
    function openScanChooser() {
        document.getElementById('scanChooserModal').style.display = 'flex';
    }

    function closeScanChooser() {
        document.getElementById('scanChooserModal').style.display = 'none';
    }

    function chooseWebcamScan() {
        closeScanChooser();
        openCamera();
    }

    function choosePhoneScan() {
        closeScanChooser();
        openPhoneScanner();
    }

    /* ── CAMÉRA ── */
    let qrScanner = null;

    function openCamera() {
        document.getElementById('cameraModal').style.display = 'flex';
        document.getElementById('cam-status').textContent    = 'Pointez la caméra vers un code barre…';
        document.getElementById('cam-status').style.color   = '#aaa';
        document.getElementById('cam-fallback-phone').style.display = 'none';

        qrScanner = new Html5Qrcode('camera-reader');
        qrScanner.start(
            { facingMode: 'environment' },
            { fps: 12, qrbox: { width: 260, height: 120 }, aspectRatio: 1.333334 },
            (decodedText) => {
                document.getElementById('barcode_input').value = decodedText;
                document.getElementById('cam-status').textContent = '✔ Code détecté : ' + decodedText;
                document.getElementById('cam-status').style.color = '#28a745';
                setTimeout(() => {
                    closeCamera();
                    lookupBarcode();
                }, 600);
            },
            () => { /* frame sans détection — ignoré */ }
        ).catch((err) => {
            const noCamera = /NotFoundError|NotAllowedError|NotReadableError/i.test(String(err));
            document.getElementById('cam-status').textContent = noCamera
                ? "⚠ Aucune caméra utilisable sur cet ordinateur."
                : '⚠ Caméra inaccessible : ' + err;
            document.getElementById('cam-status').style.color = '#dc3545';
            document.getElementById('cam-fallback-phone').style.display = 'block';
        });
    }

    function closeCamera() {
        // Si la caméra n'a jamais réussi à démarrer (ex: NotFoundError), .stop() peut lever
        // une exception SYNCHRONE plutôt qu'une promesse rejetée — Promise.resolve().then(...)
        // capture les deux cas, et le try/finally garantit que la modale se ferme dans tous les cas.
        try {
            const scanner = qrScanner;
            qrScanner = null;
            if (scanner) {
                Promise.resolve()
                    .then(() => scanner.stop())
                    .catch(() => {})
                    .finally(() => {
                        try { scanner.clear(); } catch (e) {}
                    });
            }
        } finally {
            document.getElementById('cameraModal').style.display = 'none';
        }
    }

    function lookupBarcode() {
        const input    = document.getElementById('barcode_input');
        const feedback = document.getElementById('barcode_feedback');
        const barcode  = input.value.trim();

        if (!barcode) return;

        feedback.textContent = 'Recherche...';
        feedback.style.color = '#666';

        fetch('../api/lookup_product.php?barcode=' + encodeURIComponent(barcode))
            .then(r => r.json())
            .then(data => {
                if (!data.found) {
                    feedback.textContent = '⚠ ' + (data.message || 'Produit introuvable');
                    feedback.style.color = 'var(--danger)';
                    return;
                }
                applyScannedProduct(data, false);
                input.value = '';
                input.focus();
            })
            .catch(() => {
                feedback.textContent = 'Erreur de connexion';
                feedback.style.color = 'var(--danger)';
            });
    }

    // Fonction commune d'ajout au panier — appelée aussi bien par le scanner caméra du PC
    // (lookupBarcode ci-dessus) que par les scans reçus du téléphone distant (polling scan_session).
    // Centralise toute la logique métier : un même produit scanné plusieurs fois incrémente
    // la quantité existante au lieu de créer une nouvelle ligne.
    function applyScannedProduct(data, fromPhone) {
        const feedback = document.getElementById('barcode_feedback');
        const isCarton = data.type_conditionnement === 'carton';
        const prefix = fromPhone ? '📱 ' : '';

        const selects = document.querySelectorAll('.product-select');
        let existingRow = null;
        selects.forEach(sel => {
            if (sel.value == data.id_produit) existingRow = sel.closest('.item-row');
        });

        if (existingRow) {
            const targetInput = existingRow.querySelector(isCarton ? '.qte-carton-input' : '.qte-unite-input');
            targetInput.value = (parseInt(targetInput.value) || 0) + 1;
            recalculate();
            feedback.textContent = prefix + '✔ Quantité mise à jour : ' + data.nom_produit;
        } else {
            addItem(data.id_produit, isCarton ? 1 : 0, isCarton ? 0 : 1);
            feedback.textContent = prefix + '✔ Ajouté : ' + data.nom_produit + (isCarton
                ? ` — conditionnement Carton (1 = ${data.coefficient} unités, ${Math.round(data.prix_conditionnement).toLocaleString('fr-FR')} F)`
                : ' — conditionnement Unité');
        }
        feedback.style.color = 'var(--success)';
    }
</script>

<!-- MODAL CAMÉRA -->
<div id="cameraModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
     z-index:9000; flex-direction:column; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">

    <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:420px; max-height:90vh; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,.6); margin:auto;">

        <!-- Titre -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44; position:sticky; top:0; background:#1a1a2e; z-index:1;">
            <span style="color:#fff; font-weight:600; font-size:1rem;">
                <i class="fas fa-camera" style="color:#17a2b8; margin-right:8px;"></i>Scanner un code barre
            </span>
            <button onclick="closeCamera()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Viseur caméra -->
        <div style="position:relative; background:#000; max-height:280px; overflow:hidden;">
            <div id="camera-reader" style="width:100%;"></div>
            <div style="position:absolute; left:10%; width:80%; height:2px; top:50%;
                 background:linear-gradient(90deg,transparent,#17a2b8,transparent);
                 animation:scanAnim 2s linear infinite; pointer-events:none;"></div>
        </div>

        <!-- Statut -->
        <p id="cam-status" style="margin:0; padding:14px 20px; color:#aaa; font-size:0.9rem; text-align:center;">Initialisation…</p>

        <!-- Repli vers le téléphone si la webcam est indisponible -->
        <div id="cam-fallback-phone" style="display:none; padding:0 20px 14px;">
            <button type="button" class="btn btn-primary" style="width:100%;" onclick="closeCamera(); openPhoneScanner();">
                <i class="fas fa-mobile-alt"></i> Utiliser mon téléphone à la place
            </button>
        </div>

        <!-- Bouton fermer -->
        <div style="padding:0 20px 20px;">
            <button onclick="closeCamera()" class="btn btn-secondary" style="width:100%;">
                <i class="fas fa-times-circle"></i> Annuler
            </button>
        </div>
    </div>
</div>

<!-- MODAL CHOIX DU MODE DE SCAN -->
<div id="scanChooserModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
     z-index:9000; flex-direction:column; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">

    <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:380px; box-shadow:0 8px 32px rgba(0,0,0,.6); margin:auto;">

        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44;">
            <span style="color:#fff; font-weight:600; font-size:1rem;">
                <i class="fas fa-barcode" style="color:#17a2b8; margin-right:8px;"></i>Comment voulez-vous scanner ?
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

<style>
@keyframes scanAnim {
    0%   { top: 20%; opacity: .7; }
    50%  { top: 80%; opacity: 1;  }
    100% { top: 20%; opacity: .7; }
}
</style>

<!-- MODAL SCANNER TÉLÉPHONE (QR CODE) -->
<div id="phoneModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
     z-index:9000; flex-direction:column; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">

    <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:380px; max-height:90vh; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,.6); margin:auto;">

        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44;">
            <span style="color:#fff; font-weight:600; font-size:1rem;">
                <i class="fas fa-mobile-alt" style="color:#17a2b8; margin-right:8px;"></i>Scanner avec mon téléphone
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
                Scannez ce QR Code avec l'appareil photo de votre téléphone (même réseau Wi-Fi que ce PC), puis scannez vos produits — ils s'ajoutent automatiquement à cette vente.
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
let scanSessionCountdownTimer = null;

function nextScannerCsrfToken() {
    return scannerCsrfTokens.length ? scannerCsrfTokens.shift() : '';
}

function openPhoneScanner() {
    document.getElementById('phoneModal').style.display = 'flex';

    if (scanSessionId) {
        // Session déjà active : on réaffiche juste le QR existant plutôt que d'en recréer un.
        return;
    }

    document.getElementById('phone-qr-container').innerHTML = '<i class="fas fa-circle-notch fa-spin" style="color:#333; font-size:1.6rem;"></i>';
    document.getElementById('phone-modal-status').textContent = 'Génération du QR Code…';

    const body = new URLSearchParams({ action: 'create', csrf_token: nextScannerCsrfToken() });
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
    // Le polling continue en arrière-plan : fermer la fenêtre du QR ne déconnecte pas le téléphone.
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
                    applyScannedProduct(scan, true);
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
