<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use App\Application\Inventory\StockService;
use App\Infrastructure\Persistence\StockRepository;

$stockService = new StockService($pdo, new StockRepository($pdo));

$page_title = 'Approvisionnement';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// Fetch all products for the JS array
$stmt = $pdo->prepare("SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock FROM Produit WHERE Id_Entreprise = ? ORDER BY Nom_Produit");
$stmt->execute([$entreprise_id]);
$produits = $stmt->fetchAll();

$error = '';
$success = '';

$stmt = $pdo->prepare("\n    SELECT\n        l.Id_Ligne,\n        l.Id_Produit,\n        l.Nom_Produit,\n        l.Quantite,\n        l.Quantite_Receptionnee,\n        (l.Quantite - l.Quantite_Receptionnee) AS Quantite_Restante,\n        c.Numero_Commande,\n        e.Nom_Entreprise AS Nom_Vendeur\n    FROM Ligne_Commande_B2B l\n    JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B\n    JOIN Entreprise e ON e.Id_Entreprise = c.Id_Entreprise_Vendeuse\n    WHERE c.Id_Entreprise_Acheteuse = ?\n      AND c.Statut = 'livree'\n      AND l.Quantite_Receptionnee < l.Quantite\n    ORDER BY c.Date_Commande DESC, l.Id_Ligne ASC\n");
$stmt->execute([$entreprise_id]);
$receptions_b2b = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
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

                $stmt = $pdo->prepare("\n                    SELECT l.Id_Ligne, l.Id_Produit, l.Nom_Produit, l.Quantite, l.Quantite_Receptionnee,\n                           p.Description_Produit, p.Prix_Unitaire_Produit, p.Prix_B2B\n                    FROM Ligne_Commande_B2B l\n                    JOIN Commande_B2B c ON c.Id_Commande_B2B = l.Id_Commande_B2B\n                    JOIN Produit p ON p.Id_Produit = l.Id_Produit\n                    WHERE l.Id_Ligne = ?\n                      AND c.Id_Entreprise_Acheteuse = ?\n                      AND c.Statut = 'livree'\n                    FOR UPDATE\n                ");
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
                $stmt = $pdo->prepare("SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock FROM Produit WHERE Id_Entreprise = ? ORDER BY Nom_Produit");
                $stmt->execute([$entreprise_id]);
                $produits = $stmt->fetchAll();
            } catch (Throwable $e) {
                $error = "Erreur lors de l'approvisionnement : " . $e->getMessage();
            }
        }
    }
}
?>

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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
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
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

        <h3>Brouillon d'Entrée en Stock</h3>

        <!-- SCANNER CODE BARRE (ENTRÉE) -->
        <div style="background:#f0fff4; padding:12px; border-radius:8px; border-left:4px solid #28a745; margin-bottom:15px;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <i class="fas fa-barcode" style="font-size:1.4rem; color:#28a745;"></i>
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
                <span id="barcode_feedback" style="font-size:0.9em; color:#666; width:100%;"></span>
            </div>
        </div>

        <div id="items-container">
            <!-- Items will be added here dynamically -->
        </div>

        <button type="button" onclick="addItem()" class="btn btn-secondary btn-sm" style="margin: 20px 0;">
            <i class="fas fa-plus"></i> Ajouter un article manuellement
        </button>

        <div style="border-top: 1px solid #eee; padding-top: 20px; display:flex; gap:10px;">
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

    function addItem(selectedId = null) {
        const container = document.getElementById('items-container');
        const div = document.createElement('div');
        div.className = 'item-row';
        div.setAttribute('data-id', itemCount);
        div.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 5px; border-left: 4px solid #17a2b8;';

        div.innerHTML = `
            <div style="flex: 2;">
                <label style="font-size: 0.8em; color: #666; margin-bottom: 2px;">Produit</label>
                <select name="items[${itemCount}][produit]" class="form-control product-select" required onchange="updateOptions()">
                    ${renderOptions(selectedId)}
                </select>
                <input type="hidden" name="items[${itemCount}][facteur_conversion]" class="facteur-input" value="1">
            </div>

            <div style="flex: 1;">
                <label style="font-size: 0.8em; color: #666; margin-bottom: 2px;">Quantité à ENTRER</label>
                <input type="number" name="items[${itemCount}][quantite_ajouter]" class="form-control qty-input" placeholder="Qté" min="1" value="1" required>
            </div>
            <div style="margin-top: 20px;">
                <button type="button" onclick="removeItem(this)" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
            </div>
        `;
        container.appendChild(div);
        itemCount++;
        updateOptions();
        checkSubmitButton();
        return div;
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
            () => {}
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

                // Chercher si le produit est déjà dans le brouillon
                const selects = document.querySelectorAll('.product-select');
                let existingRow = null;
                selects.forEach(sel => {
                    if (sel.value == data.id) existingRow = sel.closest('.item-row');
                });

                const qty = data.is_carton ? data.quantite_par_carton : 1;

                if (existingRow) {
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
                <i class="fas fa-camera" style="color:#28a745; margin-right:8px;"></i>Scanner un code barre
            </span>
            <button onclick="closeCamera()" style="background:none; border:none; color:#aaa; font-size:1.3rem; cursor:pointer; line-height:1;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Viseur caméra -->
        <div style="position:relative; background:#000;">
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
