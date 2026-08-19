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
        <div id="items-container">
        </div>

        <button type="button" onclick="addItem()" class="btn btn-secondary btn-sm" style="margin: 20px 0;">
            <i class="fas fa-plus"></i> Ajouter un article
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
    };
</script>

</body>

</html>