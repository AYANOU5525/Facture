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
        <div id="items-container">
            <!-- Items will be added here dynamically -->
        </div>

        <button type="button" onclick="addItem()" class="btn btn-secondary btn-sm" style="margin: 20px 0;">
            <i class="fas fa-plus"></i> Ajouter un article
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

</script>

</body>

</html>
