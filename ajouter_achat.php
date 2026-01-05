<?php
require_once 'auth.php';
require_once 'bdd.php';

$products = [];
$stmt = $pdo->prepare('SELECT id, nom, quantite_en_stock FROM produits WHERE entreprise_id = :ent_id ORDER BY nom ASC');
$stmt->execute(['ent_id' => $_SESSION['entreprise_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error_message = '';
if (isset($_POST['add_purchase'])) {
    $id_produit = intval($_POST['id_produit']);
    $quantite_achetee = intval($_POST['quantite_achetee']);
    $montant_achat = floatval($_POST['montant_achat']);

    if ($id_produit > 0 && $quantite_achetee > 0 && $montant_achat > 0) {
        $stmt = $pdo->prepare('INSERT INTO achats (id_produit, quantite, montant, date, cree_par, entreprise_id) VALUES (:id_produit, :quantite, :montant, :date, :cree_par, :entreprise_id)');
        $stmt->execute([
            'id_produit' => $id_produit,
            'quantite' => $quantite_achetee,
            'montant' => $montant_achat,
            'date' => date('Y-m-d H:i:s'),
            'cree_par' => $username,
            'entreprise_id' => $_SESSION['entreprise_id']
        ]);

        $stmt = $pdo->prepare('UPDATE produits SET quantite_en_stock = quantite_en_stock + :quantite_achetee WHERE id = :id_produit AND entreprise_id = :ent_id');
        $stmt->execute([
            'quantite_achetee' => $quantite_achetee,
            'id_produit' => $id_produit,
            'ent_id' => $_SESSION['entreprise_id']
        ]);

        header('Location: tableau_de_bord.php');
        exit();
    } else {
        $error_message = "Veuillez sélectionner un produit et entrer des quantités et montants valides.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrer Achat | FactuPro</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: 700;">Approvisionnement</h1>
                <p style="color: var(--gray-600);">Enregistrez vos achats de marchandises pour mettre à jour le stock</p>
            </header>

            <div class="card" style="max-width: 600px;">
                <div class="card-title" style="color: var(--danger);"><i class="fas fa-truck-loading"></i> Nouvel Achat</div>

                <?php if ($error_message): ?>
                    <div class="card bg-danger-light" style="border: none; color: #dc2626; padding: 1rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-circle-exclamation"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <form action="ajouter_achat.php" method="POST">
                    <div class="form-group">
                        <label for="id_produit">Sélectionner le Produit</label>
                        <select id="id_produit" name="id_produit" required>
                            <option value="">-- Choisir un produit --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>">
                                    <?= htmlspecialchars($product['nom']) ?> (Stock actuel: <?= $product['quantite_en_stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label for="quantite_achetee">Quantité Achetée</label>
                            <input type="number" id="quantite_achetee" name="quantite_achetee" min="1" required placeholder="0">
                        </div>
                        <div class="form-group">
                            <label for="montant_achat">Prix d'Achat Total</label>
                            <input type="number" id="montant_achat" name="montant_achat" step="1" min="1" required placeholder="0 FCFA">
                        </div>
                    </div>

                    <div style="background: var(--light); padding: 1.5rem; border-radius: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; color: var(--gray-600);">
                            <span>Coût unitaire estimé :</span>
                            <span id="unit_cost_display" style="font-weight: 700; color: var(--dark);">0 FCFA</span>
                        </div>
                    </div>

                    <button type="submit" name="add_purchase" class="btn btn-primary" style="width: 100%; background: var(--danger);">
                        <i class="fas fa-cart-arrow-down"></i> Enregistrer l'Achat
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        const qtyInput = document.getElementById('quantite_achetee');
        const costInput = document.getElementById('montant_achat');
        const unitCostDisplay = document.getElementById('unit_cost_display');

        function updateUnitCost() {
            const qty = parseInt(qtyInput.value) || 0;
            const cost = parseFloat(costInput.value) || 0;
            if (qty > 0) {
                unitCostDisplay.textContent = (cost / qty).toLocaleString(undefined, {
                    maximumFractionDigits: 1
                }) + ' FCFA';
            } else {
                unitCostDisplay.textContent = '0 FCFA';
            }
        }

        qtyInput.addEventListener('input', updateUnitCost);
        costInput.addEventListener('input', updateUnitCost);
    </script>
</body>

</html>