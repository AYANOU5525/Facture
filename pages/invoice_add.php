<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

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

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-cart-plus"></i> Nouvelle Vente</h1>
    </div>

    <?php if ($error): ?> <div class="alert alert-danger" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div> <?php endif; ?>

    <form method="POST" id="invoiceForm" class="card">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: bold;">Nom du client</label>
            <input type="text" name="client" class="form-control" placeholder="Client Comptant" required>
        </div>



        <h3>Articles</h3>

        <!-- SCANNER CODE BARRE -->
        <div style="background:#f0f8ff; padding:12px; border-radius:8px; border-left:4px solid #17a2b8; margin-bottom:15px;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <i class="fas fa-barcode" style="font-size:1.4rem; color:#17a2b8;"></i>
                <input type="text"
                       id="barcode_input"
                       class="form-control"
                       placeholder="Scanner ou saisir un code barre..."
                       style="max-width:280px; flex:1;"
                       autocomplete="off"
                       autofocus>
                <button type="button" class="btn btn-info btn-sm" onclick="lookupBarcode()">
                    <i class="fas fa-search"></i> Chercher
                </button>
                <button type="button" class="btn btn-dark btn-sm" onclick="openCamera()" id="btn-camera">
                    <i class="fas fa-camera"></i> Caméra
                </button>
                <span id="barcode_feedback" style="font-size:0.9em; color:#666; width:100%;"></span>
            </div>
        </div>

        <div id="items-container">
        </div>

        <button type="button" onclick="addItem()" class="btn btn-secondary btn-sm" style="margin: 20px 0;">
            <i class="fas fa-plus"></i> Ajouter un article manuellement
        </button>

        <div style="border-top: 1px solid #eee; padding-top: 20px; display:flex; gap:10px;">
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Valider la vente</button>
            <a href="sales.php" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>

<script>
    let itemCount = 0;

    const products = <?php echo json_encode($produits) ?>;

    function renderOptions(selectedValue) {
        let opts = '<option value="">Sélectionner un produit</option>';
        products.forEach(p => {
            let isSelected = (p.Id_Produit == selectedValue);
            opts += `<option value="${p.Id_Produit}" ${isSelected ? 'selected' : ''}>
                        ${p.Nom_Produit} (${p.Prix_Unitaire_Produit} F - Stock: ${p.Quantite_En_Stock})
                     </option>`;
        });
        return opts;
    }

    function addItem(selectedId = null) {
        const container = document.getElementById('items-container');
        const div = document.createElement('div');
        div.className = 'item-row';
        div.setAttribute('data-id', itemCount);
        div.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';

        div.innerHTML = `
            <select name="items[${itemCount}][produit]" class="form-control product-select" required style="flex: 3;" onchange="updateOptions()">
                ${renderOptions(selectedId)}
            </select>
            <input type="hidden" name="items[${itemCount}][facteur_conversion]" class="facteur-input" value="1">
            
            <input type="number" name="items[${itemCount}][quantite]" class="form-control qty-input" placeholder="Qté" min="1" value="1" required style="flex: 1;">
            <button type="button" onclick="removeItem(this)" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
        `;
        container.appendChild(div);
        itemCount++;
        updateOptions();
        return div;
    }

    function removeItem(btn) {
        btn.parentElement.remove();
        updateOptions();
    }

    function updateOptions() {
        const allSelects = document.querySelectorAll('.product-select');
        const selectedValues = [];
        allSelects.forEach(select => {
            if (select.value) selectedValues.push(select.value);
        });

        allSelects.forEach(select => {
            const myValue = select.value;

            Array.from(select.options).forEach(option => {
                if (!option.value) return;

                if (selectedValues.includes(option.value)) {
                    if (option.value !== myValue) {
                        option.disabled = true;
                        option.text = option.text.replace(' (Déjà sélectionné)', '') + ' (Déjà sélectionné)';
                    } else {
                        option.disabled = false;
                        option.text = option.text.replace(' (Déjà sélectionné)', '');
                    }
                } else {
                    option.disabled = false;
                    option.text = option.text.replace(' (Déjà sélectionné)', '');
                }
            });
        });
    }

    // Initialiser avec une ligne vide
    window.onload = function() {
        addItem();
        document.getElementById('barcode_input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupBarcode();
            }
        });
    };

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
                    feedback.style.color = '#dc3545';
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
                    // Incrémenter la quantité
                    const qtyInput = existingRow.querySelector('.qty-input');
                    qtyInput.value = parseInt(qtyInput.value || 1) + qty;
                    feedback.textContent = '✔ Quantité mise à jour : ' + data.nom;
                } else {
                    const row = addItem(data.id);
                    row.querySelector('.qty-input').value = qty;
                    feedback.textContent = '✔ Ajouté : ' + data.nom + (data.is_carton ? ' (carton ×' + qty + ')' : '');
                }

                feedback.style.color = '#28a745';
                input.value = '';
                input.focus();
            })
            .catch(() => {
                feedback.textContent = 'Erreur de connexion';
                feedback.style.color = '#dc3545';
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