<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

requireRole(ROLE_ADMIN, ROLE_PROPRIO, ROLE_VENDEUR);

use App\Application\Billing\InvoiceService;
use App\Infrastructure\Persistence\InvoiceRepository;

$invoiceService = new InvoiceService($pdo, new InvoiceRepository($pdo));

$stmt = $pdo->prepare("SELECT Nom_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$nom_vendeur = trim($user['Nom_Utilisateur'] ?? '');

$produits = $invoiceService->availableProducts((int) $entreprise_id);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $client = trim($_POST['client'] ?? '');
    $items = $_POST['items'] ?? [];

    if (empty($items)) {
        $error = 'Veuillez ajouter au moins un produit.';
    } else {
        try {
            $numero = $invoiceService->createDirectSale($client, $nom_vendeur, $items, (int) $entreprise_id);
            header('Location: vente_workflow.php?ref=' . urlencode($numero) . '&etape=1');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$page_title = 'Nouvelle Vente / Facture';
include '../includes/header.php';
?>

<style>
    .sale-form-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 20px;
        align-items: start;
    }
    .scanner-box {
        background: var(--zinc-50);
        border: 1px solid var(--zinc-200);
        border-left: 4px solid var(--primary);
        padding: 14px;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .scanner-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .item-row {
        display: grid;
        grid-template-columns: 1fr 80px 110px 36px;
        gap: 8px;
        align-items: center;
        margin-bottom: 8px;
        background: var(--zinc-50);
        border: 1px solid var(--zinc-200);
        border-radius: 8px;
        padding: 10px 12px;
        transition: border-color 0.2s;
    }
    .item-row:hover { border-color: var(--primary); }
    .item-subtotal {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-top: 3px;
        text-align: right;
        grid-column: 1 / -1;
    }
    .sale-summary-card {
        background: var(--bg-card);
        border: 1px solid var(--zinc-200);
        border-radius: 12px;
        padding: 24px;
        position: sticky;
        top: 80px;
    }
    .sale-summary-title {
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-main);
    }
    .sale-summary-rows { margin-bottom: 16px; }
    .sale-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        font-size: 0.88rem;
        color: var(--text-muted);
        border-bottom: 1px dashed var(--zinc-100);
    }
    .sale-summary-row:last-child { border-bottom: none; }
    .sale-total-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 2px solid var(--zinc-200);
        padding-top: 14px;
        font-weight: 700;
        font-size: 1.2rem;
    }
    .sale-total-amount { color: var(--success); }
    #items-count {
        display: inline-block;
        background: var(--primary);
        color: white;
        border-radius: 12px;
        font-size: 0.75rem;
        padding: 1px 8px;
        font-weight: 600;
    }
    @media (max-width: 768px) {
        .sale-form-grid { grid-template-columns: 1fr; }
        .sale-summary-card { position: static; }
        .item-row { grid-template-columns: 1fr 70px 36px; }
        .item-prix-display { display: none; }
    }
</style>

<div class="container fade-in">
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1><i class="fas fa-cart-plus"></i> Nouvelle Vente</h1>
            <p style="color:var(--text-muted); margin:4px 0 0; font-size:0.875rem;">
                Vendeur : <strong><?= htmlspecialchars($nom_vendeur) ?></strong>
            </p>
        </div>
        <a href="sales.php" class="btn btn-secondary btn-sm"><i class="fas fa-list"></i> Historique</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="invoiceForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="sale-form-grid">
            <!-- Colonne gauche : client + articles -->
            <div>
                <!-- Client -->
                <div class="card" style="margin-bottom:16px; padding:20px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-weight:600; display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                            <i class="fas fa-user" style="color:var(--primary);"></i> Nom du client
                        </label>
                        <input type="text" name="client" class="form-control"
                               placeholder="Ex : Client Comptant, Dupont Marie..."
                               required autofocus>
                    </div>
                </div>

                <!-- Articles -->
                <div class="card" style="padding:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <h3 style="margin:0; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-shopping-basket" style="color:var(--primary);"></i>
                            Articles
                            <span id="items-count">0</span>
                        </h3>
                        <button type="button" onclick="addItem()" class="btn btn-secondary btn-sm">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </div>

                    <!-- SCANNER CODE BARRE -->
                    <div class="scanner-box">
                        <div class="scanner-row">
                            <i class="fas fa-barcode" style="font-size:1.3rem; color:var(--primary); flex-shrink:0;"></i>
                            <input type="text"
                                   id="barcode_input"
                                   class="form-control"
                                   placeholder="Scanner ou saisir un code barre..."
                                   style="max-width:260px; flex:1;"
                                   autocomplete="off">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="lookupBarcode()">
                                <i class="fas fa-search"></i>
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openCamera()" id="btn-camera">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        <span id="barcode_feedback" style="font-size:0.85em; color:var(--text-muted); display:block; margin-top:6px;"></span>
                    </div>

                    <div id="items-container"></div>

                    <div id="empty-items" style="text-align:center; padding:30px 0; color:var(--text-muted);">
                        <i class="fas fa-shopping-cart" style="font-size:2rem; opacity:0.3; display:block; margin-bottom:8px;"></i>
                        Aucun article ajouté — utilisez le scanner ou cliquez sur Ajouter
                    </div>
                </div>
            </div>

            <!-- Colonne droite : récapitulatif -->
            <div class="sale-summary-card">
                <div class="sale-summary-title">
                    <i class="fas fa-receipt" style="color:var(--primary);"></i>
                    Récapitulatif
                </div>
                <div class="sale-summary-rows" id="summary-rows">
                    <div style="text-align:center; color:var(--text-muted); font-size:0.85rem; padding:10px 0;">
                        Aucun article
                    </div>
                </div>
                <div class="sale-total-line">
                    <span>Total</span>
                    <span class="sale-total-amount" id="grand-total">0 F</span>
                </div>
                <div style="margin-top:20px; display:flex; flex-direction:column; gap:8px;">
                    <button type="submit" class="btn btn-success" id="btn-submit" disabled>
                        <i class="fas fa-check-circle"></i> Valider la vente
                    </button>
                    <a href="sales.php" class="btn btn-secondary" style="text-align:center;">
                        <i class="fas fa-times"></i> Annuler
                    </a>
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

    function addItem(selectedId = null, qty = 1) {
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
            <input type="hidden" name="items[${itemCount}][facteur_conversion]" class="facteur-input" value="1">
            <input type="number" name="items[${itemCount}][quantite]" class="form-control qty-input item-prix-display"
                   placeholder="Qté" min="1" value="${qty}" required
                   oninput="recalculate()">
            <span class="item-subtotal-inline" style="font-size:0.82rem; color:var(--text-muted); white-space:nowrap; text-align:right;"></span>
            <button type="button" onclick="removeItem(this)" class="btn btn-danger btn-sm" title="Supprimer">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(div);
        itemCount++;
        updateOptions();
        recalculate();
        return div;
    }

    function removeItem(btn) {
        btn.closest('.item-row').remove();
        updateOptions();
        recalculate();
        const rows = document.querySelectorAll('.item-row');
        if (rows.length === 0) {
            const empty = document.getElementById('empty-items');
            if (empty) empty.style.display = '';
        }
    }

    function onProductChange(select) {
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
        const rows = document.querySelectorAll('.item-row');
        let total = 0;
        const summaryEl = document.getElementById('summary-rows');
        let summaryHtml = '';

        rows.forEach(row => {
            const select = row.querySelector('.product-select');
            const qtyInput = row.querySelector('.qty-input');
            const subtotalEl = row.querySelector('.item-subtotal-inline');
            const selectedOpt = select.options[select.selectedIndex];
            const prix = parseFloat(selectedOpt?.dataset?.prix ?? 0);
            const qty = parseInt(qtyInput?.value ?? 1) || 1;
            const sub = prix * qty;
            total += sub;

            if (subtotalEl) {
                subtotalEl.textContent = sub > 0 ? `= ${sub.toLocaleString('fr-FR')} F` : '';
            }

            if (select.value && prix > 0) {
                summaryHtml += `<div class="sale-summary-row">
                    <span>${escHtml(selectedOpt.text.split('—')[0].trim())} ×${qty}</span>
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

    /* ── CAMÉRA ── */
    let qrScanner = null;

    function openCamera() {
        document.getElementById('cameraModal').style.display = 'flex';
        document.getElementById('cam-status').textContent    = 'Pointez la caméra vers un code barre…';
        document.getElementById('cam-status').style.color   = '#aaa';

        qrScanner = new Html5Qrcode('camera-reader');
        qrScanner.start(
            { facingMode: 'environment' },
            { fps: 12, qrbox: { width: 260, height: 120 } },
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
            document.getElementById('cam-status').textContent = '⚠ Caméra inaccessible : ' + err;
            document.getElementById('cam-status').style.color = '#dc3545';
        });
    }

    function closeCamera() {
        if (qrScanner) {
            qrScanner.stop().catch(() => {}).finally(() => {
                qrScanner.clear();
                qrScanner = null;
            });
        }
        document.getElementById('cameraModal').style.display = 'none';
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

                // Chercher si le produit est déjà dans la liste
                const selects = document.querySelectorAll('.product-select');
                let existingRow = null;
                selects.forEach(sel => {
                    if (sel.value == data.id) existingRow = sel.closest('.item-row');
                });

                const qty = data.is_carton ? data.quantite_par_carton : 1;

                if (existingRow) {
                    const qtyInput = existingRow.querySelector('.qty-input');
                    qtyInput.value = parseInt(qtyInput.value || 1) + qty;
                    recalculate();
                    feedback.textContent = '✔ Quantité mise à jour : ' + data.nom;
                } else {
                    addItem(data.id, qty);
                    feedback.textContent = '✔ Ajouté : ' + data.nom + (data.is_carton ? ' (carton ×' + qty + ')' : '');
                }

                feedback.style.color = 'var(--success)';
                input.value = '';
                input.focus();
            })
            .catch(() => {
                feedback.textContent = 'Erreur de connexion';
                feedback.style.color = 'var(--danger)';
            });
    }
</script>

<!-- MODAL CAMÉRA -->
<div id="cameraModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.85);
     z-index:9000; flex-direction:column; align-items:center; justify-content:center; padding:20px;">

    <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:420px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,.6);">

        <!-- Titre -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44;">
            <span style="color:#fff; font-weight:600; font-size:1rem;">
                <i class="fas fa-camera" style="color:#17a2b8; margin-right:8px;"></i>Scanner un code barre
            </span>
            <button onclick="closeCamera()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Viseur caméra -->
        <div style="position:relative; background:#000;">
            <div id="camera-reader" style="width:100%;"></div>
            <div style="position:absolute; left:10%; width:80%; height:2px; top:50%;
                 background:linear-gradient(90deg,transparent,#17a2b8,transparent);
                 animation:scanAnim 2s linear infinite; pointer-events:none;"></div>
        </div>

        <!-- Statut -->
        <p id="cam-status" style="margin:0; padding:14px 20px; color:#aaa; font-size:0.9rem; text-align:center;">Initialisation…</p>

        <!-- Bouton fermer -->
        <div style="padding:0 20px 20px;">
            <button onclick="closeCamera()" class="btn btn-secondary" style="width:100%;">
                <i class="fas fa-times-circle"></i> Annuler
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

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

</body>

</html>