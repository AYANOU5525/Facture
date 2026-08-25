<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

exigerPermission(peutGererStock());

use App\Application\Inventory\StockService;
use App\Infrastructure\Persistence\StockRepository;

$stockService = new StockService($pdo, new StockRepository($pdo));

$page_title = 'Approvisionnement';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// Fetch all products for the JS array
$stmt = $pdo->prepare("SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock, Quantite_Par_Carton FROM Produit WHERE Id_Entreprise = ? ORDER BY Nom_Produit");
$stmt->execute([$entreprise_id]);
$produits = $stmt->fetchAll();

$error = '';
$success = '';

$stmt = $pdo->prepare("\n    SELECT\n        l.Id_Ligne,\n        l.Id_Produit,\n        l.Nom_Produit,\n        l.Quantite,\n        l.Quantite_Receptionnee,\n        (l.Quantite - l.Quantite_Receptionnee) AS Quantite_Restante,\n        c.Numero_Commande,\n        e.Nom_Entreprise AS Nom_Vendeur\n    FROM Ligne_Commande_B2B l\n    JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B\n    JOIN Entreprise e ON e.Id_Entreprise = c.Id_Entreprise_Vendeuse\n    WHERE c.Id_Entreprise_Acheteuse = ?\n      AND c.Statut = 'livree'\n      AND l.Quantite_Receptionnee < l.Quantite\n    ORDER BY c.Date_Commande DESC, l.Id_Ligne ASC\n");
$stmt->execute([$entreprise_id]);
$receptions_b2b = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerCsrf();
    $action = $_POST['action'] ?? 'approvisionnement';

    if ($action === 'recevoir_b2b') {
        $receptions = $_POST['receptions'] ?? [];
        $quantites_recues = 0;

        try {
            $pdo->beginTransaction();

            foreach ($receptions as $id_ligne => $quantite) {
                $quantite = max(0, (int) $quantite);
                if ($quantite === 0) {
                    continue;
                }

                $stmt = $pdo->prepare("\n                    SELECT l.Id_Ligne, l.Id_Produit, l.Nom_Produit, l.Quantite, l.Quantite_Receptionnee,\n                           p.Description_Produit, p.Prix_Unitaire_Produit, p.Prix_B2B,\n                           p.Code_Barre_Unite, p.Code_Barre_Carton, p.Quantite_Par_Carton\n                    FROM Ligne_Commande_B2B l\n                    JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B\n                    JOIN Produit p ON p.Id_Produit = l.Id_Produit\n                    WHERE l.Id_Ligne = ?\n                      AND c.Id_Entreprise_Acheteuse = ?\n                      AND c.Statut = 'livree'\n                    FOR UPDATE\n                ");
                $stmt->execute([(int) $id_ligne, $entreprise_id]);
                $ligne = $stmt->fetch();

                if (!$ligne) {
                    throw new RuntimeException('Ligne de réception introuvable ou non autorisée.');
                }

                    $stockService->receiveB2BLine($ligne, $quantite, (int) $entreprise_id);
                $pdo->prepare("UPDATE Ligne_Commande_B2B SET Quantite_Receptionnee = Quantite_Receptionnee + ? WHERE Id_Ligne = ?")
                    ->execute([$quantite, $ligne['Id_Ligne']]);
                $quantites_recues += $quantite;
            }

            $pdo->commit();
            $success = $quantites_recues > 0
                ? "$quantites_recues unité(s) B2B ajoutée(s) au stock."
                : 'Aucune quantité B2B sélectionnée.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Erreur lors de la réception B2B : " . $e->getMessage();
        }

        $stmt = $pdo->prepare("\n            SELECT l.Id_Ligne, l.Id_Produit, l.Nom_Produit, l.Quantite, l.Quantite_Receptionnee,\n                   (l.Quantite - l.Quantite_Receptionnee) AS Quantite_Restante,\n                   c.Numero_Commande, e.Nom_Entreprise AS Nom_Vendeur\n            FROM Ligne_Commande_B2B l\n            JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B\n            JOIN Entreprise e ON e.Id_Entreprise = c.Id_Entreprise_Vendeuse\n            WHERE c.Id_Entreprise_Acheteuse = ? AND c.Statut = 'livree'\n              AND l.Quantite_Receptionnee < l.Quantite\n            ORDER BY c.Date_Commande DESC, l.Id_Ligne ASC\n        ");
        $stmt->execute([$entreprise_id]);
        $receptions_b2b = $stmt->fetchAll();
    } else {
        $items = $_POST['items'] ?? [];

        if (empty($items)) {
            $error = 'Veuillez ajouter au moins un produit à approvisionner.';
        } else {
            try {
                $received = $stockService->receiveManual($items, (int) $entreprise_id);
                $success = $received > 0
                    ? 'Approvisionnement enregistré avec succès. Les stocks ont été mis à jour.'
                    : 'Aucune quantité valide à ajouter.';
                $stmt = $pdo->prepare("SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock, Quantite_Par_Carton FROM Produit WHERE Id_Entreprise = ? ORDER BY Nom_Produit");
                $stmt->execute([$entreprise_id]);
                $produits = $stmt->fetchAll();
            } catch (Throwable $e) {
                $error = "Erreur lors de l'approvisionnement : " . $e->getMessage();
            }
        }
    }
}
?>

<style>
    .stock-scanner-box {
        background: var(--success-bg);
        border: 1px solid var(--border);
        border-left: 4px solid var(--success);
        border-radius: var(--radius);
        padding: 14px 16px;
        margin-bottom: 20px;
    }
    .stock-scanner-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .stock-scanner-icon { font-size: 1.3rem; color: var(--success); flex-shrink: 0; }
    .stock-scanner-hint {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin: 4px 0 0;
        width: 100%;
    }
    .stock-items-legend {
        display: grid;
        grid-template-columns: 2fr 100px 100px 150px 40px;
        gap: 10px;
        padding: 0 14px;
        margin-bottom: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
    .stock-item-row {
        display: grid;
        grid-template-columns: 2fr 100px 100px 150px 40px;
        gap: 10px;
        align-items: center;
        background: var(--bg-card);
        border: 1px solid var(--zinc-200);
        border-radius: var(--radius);
        padding: 10px 14px;
        margin-bottom: 10px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .stock-item-row:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-xs);
    }
    .stock-item-conversion {
        font-size: 0.78rem;
        color: var(--success);
        font-weight: 600;
    }
    .stock-item-remove-btn {
        width: 32px;
        height: 32px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stock-draft-footer {
        border-top: 1px solid var(--border);
        padding-top: 20px;
        display: flex;
        gap: 10px;
    }
    @media (max-width: 700px) {
        .stock-items-legend { display: none; }
        .stock-item-row { grid-template-columns: 1fr 70px 70px 36px; }
        .stock-item-conversion { display: none; }
    }
</style>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-truck-loading"></i> Réception d'Approvisionnement</h1>
        <p>Ajoutez les articles pour faire une entrée en stock.</p>
    </div>

    <?php if ($error): ?> <div class="alert alert-danger" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div> <?php endif; ?>
    <?php if ($success): ?> <div class="alert alert-success" style="margin-bottom:20px;"><?= htmlspecialchars($success) ?></div> <?php endif; ?>

    <?php if (!empty($receptions_b2b)): ?>
        <section class="card b2b-reception-card">
            <div class="page-section-heading">
                <div>
                    <h2><i class="fas fa-boxes-stacked"></i> Réceptions B2B à traiter</h2>
                    <p>Choisissez les quantités à ajouter à votre stock. La livraison ne modifie pas automatiquement votre inventaire.</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="recevoir_b2b">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Commande</th>
                                <th>Vendeur</th>
                                <th>Produit</th>
                                <th>Restant</th>
                                <th>À ajouter au stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receptions_b2b as $reception): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($reception['Numero_Commande']) ?></code></td>
                                    <td><?= htmlspecialchars($reception['Nom_Vendeur']) ?></td>
                                    <td><?= htmlspecialchars($reception['Nom_Produit']) ?></td>
                                    <td><span class="badge badge-info"><?= (int) $reception['Quantite_Restante'] ?></span></td>
                                    <td>
                                        <input type="number" name="receptions[<?= (int) $reception['Id_Ligne'] ?>]" class="form-control" min="0" max="<?= (int) $reception['Quantite_Restante'] ?>" value="0" aria-label="Quantité à ajouter pour <?= htmlspecialchars($reception['Nom_Produit']) ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-boxes-stacked"></i> Ajouter la sélection au stock</button>
            </form>
        </section>
    <?php endif; ?>

    <form method="POST" id="approForm" class="card">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">

        <h3>Brouillon d'Entrée en Stock</h3>

        <!-- SCANNER CODE BARRE (ENTRÉE) -->
        <div class="stock-scanner-box">
            <div class="stock-scanner-row">
                <i class="fas fa-barcode stock-scanner-icon"></i>
                <input type="text"
                       id="barcode_input"
                       class="form-control"
                       placeholder="Scanner ou saisir un code barre..."
                       style="max-width:280px; flex:1;"
                       autocomplete="off">
                <button type="button" class="btn btn-success btn-sm" onclick="lookupBarcode()">
                    <i class="fas fa-search"></i> Chercher
                </button>
                <button type="button" class="btn btn-dark btn-sm" onclick="openCamera()" id="btn-camera">
                    <i class="fas fa-camera"></i> Caméra
                </button>
                <span id="barcode_feedback" style="font-size:0.9em; color:var(--text-muted); width:100%;"></span>
                <p class="stock-scanner-hint">
                    <i class="fas fa-circle-info"></i>
                    1) Cliquez sur <i class="fas fa-camera"></i> — 2) Autorisez la caméra si le navigateur le demande — 3) Pointez vers le code-barre, il s'ajoute automatiquement au brouillon.
                </p>
            </div>
        </div>

        <div class="stock-items-legend">
            <span>Produit</span>
            <span>Cartons</span>
            <span>Unités</span>
            <span></span>
            <span></span>
        </div>
        <div id="items-container">
            <!-- Items will be added here dynamically -->
        </div>

        <button type="button" onclick="addItem()" class="btn btn-secondary btn-sm" style="margin: 20px 0;">
            <i class="fas fa-plus"></i> Ajouter un article manuellement
        </button>

        <div class="stock-draft-footer">
            <button type="submit" class="btn btn-success" id="btn-submit" disabled><i class="fas fa-check-double"></i> Valider l'entrée en stock</button>
            <a href="products.php" class="btn btn-secondary">Retour aux Stocks</a>
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
                        ${p.Nom_Produit} (Stock Actuel: ${p.Quantite_En_Stock})
                     </option>`;
        });
        return opts;
    }

    function addItem(selectedId = null, qteCarton = 0, qteUnite = 1) {
        const container = document.getElementById('items-container');
        const div = document.createElement('div');
        div.className = 'item-row stock-item-row';
        div.setAttribute('data-id', itemCount);

        div.innerHTML = `
            <select name="items[${itemCount}][produit]" class="form-control product-select" required onchange="onItemProductChange(this)">
                ${renderOptions(selectedId)}
            </select>
            <input type="number" name="items[${itemCount}][qte_carton]" class="form-control qte-carton-input"
                   min="0" value="${qteCarton}" placeholder="0" oninput="updateRowConversion(this.closest('.item-row'))">
            <input type="number" name="items[${itemCount}][qte_unite]" class="form-control qte-unite-input"
                   min="0" value="${qteUnite}" placeholder="0" oninput="updateRowConversion(this.closest('.item-row'))">
            <small class="conversion-display stock-item-conversion"></small>
            <button type="button" onclick="removeItem(this)" class="btn btn-danger btn-sm stock-item-remove-btn" title="Supprimer">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(div);
        itemCount++;
        if (selectedId) updateCartonAvailability(div);
        updateRowConversion(div);
        updateOptions();
        checkSubmitButton();
        return div;
    }

    // Active/désactive le champ "Cartons" selon que le produit a un conditionnement carton.
    // Le coefficient affiché ici est purement informatif — le serveur recalcule tout à partir
    // du produit en base au moment de la validation, jamais depuis ce que le client envoie.
    function updateCartonAvailability(row) {
        const select = row.querySelector('.product-select');
        const cartonInput = row.querySelector('.qte-carton-input');
        const product = products.find(p => p.Id_Produit == select.value);
        const canCarton = product && parseInt(product.Quantite_Par_Carton) > 1;

        cartonInput.disabled = !canCarton;
        cartonInput.title = canCarton
            ? `1 carton = ${product.Quantite_Par_Carton} unités`
            : `Ce produit n'a pas de conditionnement carton`;
        if (!canCarton) cartonInput.value = 0;
        updateRowConversion(row);
    }

    function onItemProductChange(select) {
        updateCartonAvailability(select.closest('.item-row'));
        updateOptions();
    }

    function updateRowConversion(row) {
        const select = row.querySelector('.product-select');
        const cartonInput = row.querySelector('.qte-carton-input');
        const uniteInput = row.querySelector('.qte-unite-input');
        const display = row.querySelector('.conversion-display');
        const product = products.find(p => p.Id_Produit == select.value);

        const perCarton = product ? (parseInt(product.Quantite_Par_Carton) || 1) : 1;
        const qteCarton = parseInt(cartonInput.value) || 0;
        const qteUnite = parseInt(uniteInput.value) || 0;
        const total = (qteCarton * perCarton) + qteUnite;

        display.textContent = total > 0 ? `= ${total} unité(s) au total` : '';
        checkSubmitButton();
    }

    function removeItem(btn) {
        btn.parentElement.parentElement.remove();
        updateOptions();
        checkSubmitButton();
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
                    } else {
                        option.disabled = false;
                    }
                } else {
                    option.disabled = false;
                }
            });
        });
        checkSubmitButton();
    }

    function checkSubmitButton() {
        const selects = document.querySelectorAll('.product-select');
        const submitBtn = document.getElementById('btn-submit');
        submitBtn.disabled = selects.length === 0;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const barcodeInput = document.getElementById('barcode_input');
        barcodeInput.addEventListener('keydown', function(e) {
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
        document.getElementById('cam-status').textContent  = 'Pointez la caméra vers un code barre…';
        document.getElementById('cam-status').style.color = '#aaa';

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
            () => {}
        ).catch((err) => {
            document.getElementById('cam-status').textContent = '⚠ Caméra inaccessible : ' + err;
            document.getElementById('cam-status').style.color = '#dc3545';
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
        feedback.style.color = 'var(--text-muted)';

        fetch('../api/lookup_product.php?barcode=' + encodeURIComponent(barcode))
            .then(r => r.json())
            .then(data => {
                if (!data.found) {
                    feedback.textContent = '⚠ ' + (data.message || 'Produit introuvable');
                    feedback.style.color = 'var(--danger)';
                    return;
                }

                const isCarton = data.type_conditionnement === 'carton';

                // Chercher si le produit est déjà dans le brouillon
                const selects = document.querySelectorAll('.product-select');
                let existingRow = null;
                selects.forEach(sel => {
                    if (sel.value == data.id_produit) existingRow = sel.closest('.item-row');
                });

                if (existingRow) {
                    // Un scan de plus = +1 dans le conditionnement scanné (carton ou unité)
                    const targetInput = existingRow.querySelector(isCarton ? '.qte-carton-input' : '.qte-unite-input');
                    targetInput.value = (parseInt(targetInput.value) || 0) + 1;
                    updateRowConversion(existingRow);
                    feedback.textContent = '✔ Quantité mise à jour : ' + data.nom_produit;
                } else {
                    addItem(data.id_produit, isCarton ? 1 : 0, isCarton ? 0 : 1);
                    feedback.textContent = '✔ Ajouté : ' + data.nom_produit + (isCarton
                        ? ` — conditionnement Carton (1 = ${data.coefficient} unités)`
                        : ' — conditionnement Unité');
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
     z-index:9000; flex-direction:column; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">

    <div style="background:#1a1a2e; border-radius:16px; width:100%; max-width:420px; max-height:90vh; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,.6); margin:auto;">

        <!-- Titre -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #2d2d44; position:sticky; top:0; background:#1a1a2e; z-index:1;">
            <span style="color:#fff; font-weight:600; font-size:1rem;">
                <i class="fas fa-camera" style="color:#28a745; margin-right:8px;"></i>Scanner un code barre
            </span>
            <button onclick="closeCamera()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Viseur caméra -->
        <div style="position:relative; background:#000; max-height:280px; overflow:hidden;">
            <div id="camera-reader" style="width:100%;"></div>
            <div style="position:absolute; left:10%; width:80%; height:2px; top:50%;
                 background:linear-gradient(90deg,transparent,#28a745,transparent);
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
