<?php
require_once 'auth.php';
require_once 'bdd.php';

$error = '';
$success = '';

// Fetch all products for the dropdown
$products = [];
$stmt = $pdo->prepare('SELECT id, nom, prix_unitaire, quantite_en_stock FROM produits WHERE entreprise_id = :ent_id ORDER BY nom ASC');
$stmt->execute(['ent_id' => $_SESSION['entreprise_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $items = json_decode($_POST['items'] ?? '[]', true);

    if (empty($client_name) || empty($items)) {
        $error = 'Le nom du client et au moins un article sont obligatoires.';
    } else {
        $total_amount = 0;
        $invoice_articles = [];
        $stock_updates = [];

        foreach ($items as $item) {
            $product_id = $item['id_produit'];
            $quantity = $item['quantity'];

            $stmt = $pdo->prepare('SELECT nom, prix_unitaire, quantite_en_stock FROM produits WHERE id = :id_produit AND entreprise_id = :ent_id');
            $stmt->execute(['id_produit' => $product_id, 'ent_id' => $_SESSION['entreprise_id']]);
            $product_db = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product_db || $product_db['quantite_en_stock'] < $quantity) {
                $error = 'Stock insuffisant pour le produit ' . htmlspecialchars($product_db['nom'] ?? 'inconnu') . '.';
                break;
            }

            $item_price = $product_db['prix_unitaire'];
            $item_total = $quantity * $item_price;
            $total_amount += $item_total;

            $invoice_articles[] = [
                'id_produit' => $product_id,
                'description' => $product_db['nom'],
                'quantity' => $quantity,
                'price' => $item_price,
                'total' => $item_total
            ];

            $stock_updates[] = [
                'id_produit' => $product_id,
                'new_stock' => $product_db['quantite_en_stock'] - $quantity
            ];
        }

        if (empty($error)) {
            $invoice_id = uniqid('INV-');
            $stmt = $pdo->prepare('INSERT INTO factures (id_facture, nom_client, email_client, date, articles, montant_total, cree_par, entreprise_id) VALUES (:id_facture, :nom_client, :email_client, :date, :articles, :montant_total, :cree_par, :entreprise_id)');

            if ($stmt->execute([
                'id_facture' => $invoice_id,
                'nom_client' => $client_name,
                'email_client' => $client_email,
                'date' => date('Y-m-d H:i:s'),
                'articles' => json_encode($invoice_articles),
                'montant_total' => $total_amount,
                'cree_par' => $_SESSION['username'],
                'entreprise_id' => $_SESSION['entreprise_id']
            ])) {
                foreach ($stock_updates as $update) {
                    $stmt = $pdo->prepare('UPDATE produits SET quantite_en_stock = :new_stock WHERE id = :id_produit AND entreprise_id = :ent_id');
                    $stmt->execute([
                        'new_stock' => $update['new_stock'],
                        'id_produit' => $update['id_produit'],
                        'ent_id' => $_SESSION['entreprise_id']
                    ]);
                }
                $success = 'Facture <strong style="color: var(--primary);">' . $invoice_id . '</strong> créée avec succès ! <a href="afficher_facture.php?id=' . urlencode($invoice_id) . '" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; margin-left: 1rem;"><i class="fas fa-eye"></i> Voir la facture</a>';
                $_POST = array();
            } else {
                $error = 'Erreur lors de la création de la facture.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer Facture | FactuPro</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .invoice-builder {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            align-items: start;
        }

        .item-row {
            display: grid;
            grid-template-columns: 2fr 100px 150px 50px;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: 0.75rem;
            margin-bottom: 0.75rem;
            transition: var(--transition);
        }

        .item-row:hover {
            background: var(--gray-200);
        }

        .remove-item-btn {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .remove-item-btn:hover {
            background: #ef4444;
            color: white;
        }

        .total-panel {
            position: sticky;
            top: 2rem;
            background: var(--white);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--primary);
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .grand-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            border-top: 2px solid var(--gray-200);
            padding-top: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: 700;">Créer une Facture</h1>
                <p style="color: var(--gray-600);">Remplissez les détails pour générer une nouvelle facture</p>
            </header>

            <?php if ($error): ?>
                <div class="card bg-danger-light" style="border: none; color: #dc2626; padding: 1rem;">
                    <i class="fas fa-circle-exclamation"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="card bg-success-light" style="border: none; color: #059669; padding: 1rem;">
                    <i class="fas fa-circle-check"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <form id="invoiceForm" action="creer_facture.php" method="POST" class="invoice-builder">
                <div class="builder-columns">
                    <div class="card">
                        <div class="card-title"><i class="fas fa-user-tie text-primary"></i> Informations Client</div>
                        <div class="stats-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 0;">
                            <div class="form-group">
                                <label for="client_name">Nom du Client</label>
                                <input type="text" id="client_name" name="client_name" required placeholder="Ex: Jean Dupont">
                            </div>
                            <div class="form-group">
                                <label for="client_email">Email (optionnel)</label>
                                <input type="email" id="client_email" name="client_email" placeholder="client@exemple.com">
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-title" style="justify-content: space-between;">
                            <span><i class="fas fa-list-ul text-primary"></i> Articles de la facture</span>
                            <button type="button" class="btn btn-primary btn-sm add-item-btn" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                                <i class="fas fa-plus"></i> Ajouter un produit
                            </button>
                        </div>
                        <div id="items-container">
                            <!-- Items appear here -->
                        </div>
                    </div>
                </div>

                <div class="total-panel">
                    <div class="card-title"><i class="fas fa-receipt text-primary"></i> Récapitulatif</div>
                    <div id="summary-items">
                        <!-- Summary here -->
                    </div>
                    <div class="grand-total">
                        <div style="font-size: 0.875rem; color: var(--gray-600); font-weight: 500;">Total à Payer</div>
                        <div id="grand-total-value">0 FCFA</div>
                    </div>

                    <input type="hidden" name="items" id="hidden-items-input">
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem; padding: 1rem;">
                        <i class="fas fa-file-invoice"></i> Générer Facture
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const itemsContainer = document.getElementById('items-container');
            const summaryItems = document.getElementById('summary-items');
            const grandTotalValue = document.getElementById('grand-total-value');
            const addItemBtn = document.querySelector('.add-item-btn');
            const invoiceForm = document.getElementById('invoiceForm');
            const hiddenItemsInput = document.getElementById('hidden-items-input');
            const productsData = <?= json_encode($products) ?>;

            function updateTotals() {
                let grandTotal = 0;
                summaryItems.innerHTML = '';

                itemsContainer.querySelectorAll('.item-row').forEach(row => {
                    const select = row.querySelector('.product-select');
                    const qtyInput = row.querySelector('.quantity-input');
                    const option = select.options[select.selectedIndex];

                    if (option && option.value) {
                        const price = parseFloat(option.dataset.price);
                        const qty = parseInt(qtyInput.value) || 0;
                        const subtotal = price * qty;
                        grandTotal += subtotal;

                        summaryItems.innerHTML += `
                            <div class="total-line">
                                <span style="font-size: 0.875rem;">${option.text.split('(')[0]} x ${qty}</span>
                                <span>${subtotal.toLocaleString()} FCFA</span>
                            </div>
                        `;
                    }
                });

                grandTotalValue.textContent = grandTotal.toLocaleString() + ' FCFA';
            }

            function addItemRow(selectedId = '', quantity = 1) {
                const row = document.createElement('div');
                row.className = 'item-row';

                let options = '<option value="">Produit...</option>';
                productsData.forEach(p => {
                    options += `<option value="${p.id}" data-price="${p.prix_unitaire}" data-stock="${p.quantite_en_stock}" ${p.id == selectedId ? 'selected' : ''}>${p.nom} (${p.prix_unitaire} F)</option>`;
                });

                row.innerHTML = `
                    <select class="product-select" required>${options}</select>
                    <input type="number" class="quantity-input" value="${quantity}" min="1" required>
                    <div style="font-weight: 600; color: var(--primary);" class="row-subtotal">0 F</div>
                    <button type="button" class="remove-item-btn"><i class="fas fa-trash"></i></button>
                `;

                itemsContainer.appendChild(row);

                const select = row.querySelector('.product-select');
                const qtyInput = row.querySelector('.quantity-input');
                const subtotalDiv = row.querySelector('.row-subtotal');

                const updateRowTotal = () => {
                    const opt = select.options[select.selectedIndex];
                    const p = opt.dataset.price ? parseFloat(opt.dataset.price) : 0;
                    const q = parseInt(qtyInput.value) || 0;
                    subtotalDiv.textContent = (p * q).toLocaleString() + ' F';
                    updateTotals();
                };

                select.addEventListener('change', updateRowTotal);
                qtyInput.addEventListener('input', updateRowTotal);
                row.querySelector('.remove-item-btn').addEventListener('click', () => {
                    row.remove();
                    updateTotals();
                });

                updateRowTotal();
            }

            addItemBtn.addEventListener('click', () => addItemRow());

            invoiceForm.addEventListener('submit', function(e) {
                const items = [];
                itemsContainer.querySelectorAll('.item-row').forEach(row => {
                    const sel = row.querySelector('.product-select');
                    const qty = parseInt(row.querySelector('.quantity-input').value);
                    if (sel.value) {
                        items.push({
                            id_produit: sel.value,
                            quantity: qty
                        });
                    }
                });

                if (items.length === 0) {
                    e.preventDefault();
                    alert("Ajoutez au moins un produit");
                    return;
                }
                hiddenItemsInput.value = JSON.stringify(items);
            });

            addItemRow();
        });
    </script>
</body>

</html>