<?php
require_once 'auth.php';
require_once 'bdd.php';

$products = [];
$stmt = $pdo->prepare('SELECT id, nom, prix_unitaire, quantite_en_stock FROM produits WHERE entreprise_id = :ent_id ORDER BY nom ASC');
$stmt->execute(['ent_id' => $_SESSION['entreprise_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error_message = '';
$success_invoice_id = '';

if (isset($_POST['add_sale'])) {
    $id_produit = intval($_POST['id_produit']);
    $quantite_vendue = intval($_POST['quantite_vendue']);
    $client_name = trim($_POST['client_name'] ?? 'Client Comptant');

    if ($id_produit > 0 && $quantite_vendue > 0) {
        $stmt = $pdo->prepare('SELECT nom, prix_unitaire, quantite_en_stock FROM produits WHERE id = :id_produit AND entreprise_id = :ent_id');
        $stmt->execute(['id_produit' => $id_produit, 'ent_id' => $_SESSION['entreprise_id']]);
        $product_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product_info && $product_info['quantite_en_stock'] >= $quantite_vendue) {
            $montant_total = $product_info['prix_unitaire'] * $quantite_vendue;
            $invoice_id = uniqid('INV-');

            $pdo->beginTransaction();
            try {
                // 1. Enregistrer la vente
                $stmt = $pdo->prepare('INSERT INTO ventes (id_produit, quantite, montant, date, cree_par, entreprise_id) VALUES (:id_produit, :quantite, :montant, :date, :cree_par, :entreprise_id)');
                $stmt->execute([
                    'id_produit' => $id_produit,
                    'quantite' => $quantite_vendue,
                    'montant' => $montant_total,
                    'date' => date('Y-m-d H:i:s'),
                    'cree_par' => $username,
                    'entreprise_id' => $_SESSION['entreprise_id']
                ]);

                // 2. Mettre à jour le stock
                $stmt = $pdo->prepare('UPDATE produits SET quantite_en_stock = quantite_en_stock - :quantite WHERE id = :id_produit AND entreprise_id = :ent_id');
                $stmt->execute([
                    'quantite' => $quantite_vendue,
                    'id_produit' => $id_produit,
                    'ent_id' => $_SESSION['entreprise_id']
                ]);

                // 3. Générer la facture correspondante
                $articles_json = json_encode([[
                    'id_produit' => $id_produit,
                    'description' => $product_info['nom'],
                    'quantity' => $quantite_vendue,
                    'price' => $product_info['prix_unitaire'],
                    'total' => $montant_total
                ]]);

                $stmt = $pdo->prepare('INSERT INTO factures (id_facture, nom_client, date, articles, montant_total, cree_par, entreprise_id) VALUES (:id_facture, :nom_client, :date, :articles, :montant_total, :cree_par, :entreprise_id)');
                $stmt->execute([
                    'id_facture' => $invoice_id,
                    'nom_client' => $client_name,
                    'date' => date('Y-m-d H:i:s'),
                    'articles' => $articles_json,
                    'montant_total' => $montant_total,
                    'cree_par' => $username,
                    'entreprise_id' => $_SESSION['entreprise_id']
                ]);

                $pdo->commit();
                $success_invoice_id = $invoice_id;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        } else {
            $error_message = "Quantité insuffisante en stock.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrer Vente | FactuPro</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: 700;">Enregistrer une Vente</h1>
                <p style="color: var(--gray-600);">Saisie rapide et génération de facture instantanée</p>
            </header>

            <?php if ($success_invoice_id): ?>
                <div class="card bg-success-light" style="border: none; color: #059669; padding: 1.5rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <i class="fas fa-check-circle"></i> Vente enregistrée et facture <strong><?= $success_invoice_id ?></strong> générée !
                    </div>
                    <a href="afficher_facture.php?id=<?= $success_invoice_id ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <i class="fas fa-print"></i> Voir la Facture
                    </a>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px;">
                <div class="card-title"><i class="fas fa-cart-plus text-primary"></i> Détails de la Vente</div>

                <?php if ($error_message): ?>
                    <div class="card bg-danger-light" style="border: none; color: #dc2626; padding: 1rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-circle-exclamation"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <form action="ajouter_vente.php" method="POST">
                    <div class="form-group">
                        <label for="client_name">Nom du Client</label>
                        <input type="text" id="client_name" name="client_name" placeholder="Ex: Client Comptant">
                    </div>

                    <div class="form-group">
                        <label for="id_produit">Produit à vendre</label>
                        <select id="id_produit" name="id_produit" required>
                            <option value="">-- Choisir un produit --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>" data-price="<?= $product['prix_unitaire'] ?>" data-stock="<?= $product['quantite_en_stock'] ?>">
                                    <?= htmlspecialchars($product['nom']) ?> (Stock: <?= $product['quantite_en_stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label for="quantite_vendue">Quantité</label>
                            <input type="number" id="quantite_vendue" name="quantite_vendue" min="1" required placeholder="0">
                        </div>
                        <div class="form-group">
                            <label>Prix Unitaire</label>
                            <div id="price_display" style="padding: 0.75rem; background: var(--gray-100); border-radius: 0.5rem; font-weight: 600;">0 FCFA</div>
                        </div>
                    </div>

                    <div style="background: var(--primary-light); padding: 1.5rem; border-radius: 1rem; margin-bottom: 1.5rem; text-align: center;">
                        <div style="font-size: 0.875rem; color: var(--gray-600); font-weight: 500;">Total de la vente</div>
                        <div id="total_display" style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">0 FCFA</div>
                    </div>

                    <button type="submit" name="add_sale" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-check"></i> Confirmer et Facturer
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        const productSelect = document.getElementById('id_produit');
        const quantityInput = document.getElementById('quantite_vendue');
        const priceDisplay = document.getElementById('price_display');
        const totalDisplay = document.getElementById('total_display');

        function updateDisplay() {
            const option = productSelect.options[productSelect.selectedIndex];
            const price = option.dataset.price ? parseFloat(option.dataset.price) : 0;
            const qty = parseInt(quantityInput.value) || 0;

            priceDisplay.textContent = price.toLocaleString() + ' FCFA';
            totalDisplay.textContent = (price * qty).toLocaleString() + ' FCFA';
        }

        productSelect.addEventListener('change', updateDisplay);
        quantityInput.addEventListener('input', updateDisplay);
    </script>
</body>

</html>