<?php
require_once 'auth.php';
require_once 'bdd.php';

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$produit = null;

if ($product_id > 0) {
    $stmt = $pdo->prepare('SELECT id, nom, description, prix_unitaire, quantite_en_stock FROM produits WHERE id = :id AND entreprise_id = :ent_id');
    $stmt->execute(['id' => $product_id, 'ent_id' => $_SESSION['entreprise_id']]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$produit) {
    header('Location: liste_produits.php');
    exit();
}

if (isset($_POST['update_product'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix_unitaire = floatval($_POST['prix_unitaire']);
    $quantite_en_stock = intval($_POST['quantite_en_stock']);

    if (!empty($nom) && $prix_unitaire > 0 && $quantite_en_stock >= 0) {
        $stmt = $pdo->prepare('UPDATE produits SET nom = :nom, description = :description, prix_unitaire = :prix_unitaire, quantite_en_stock = :quantite_en_stock WHERE id = :id AND entreprise_id = :ent_id');
        $stmt->execute([
            'nom' => $nom,
            'description' => $description,
            'prix_unitaire' => $prix_unitaire,
            'quantite_en_stock' => $quantite_en_stock,
            'id' => $product_id,
            'ent_id' => $_SESSION['entreprise_id']
        ]);
        header('Location: liste_produits.php');
        exit();
    } else {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Produit | FactuPro</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: 700;">Modifier : <?= htmlspecialchars($produit['nom']) ?></h1>
                <p style="color: var(--gray-600);">Mettez à jour les informations de votre produit</p>
            </header>

            <div class="card" style="max-width: 700px;">
                <div class="card-title"><i class="fas fa-edit text-primary"></i> Modifier le Produit</div>

                <?php if (isset($error_message)): ?>
                    <div class="card bg-danger-light" style="border: none; color: #dc2626; padding: 1rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-circle-exclamation"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <form action="modifier_produit.php?id=<?= $product_id ?>" method="POST">
                    <div class="form-group">
                        <label for="nom">Nom du Produit</label>
                        <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($produit['nom']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"><?= htmlspecialchars($produit['description']) ?></textarea>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label for="prix_unitaire">Prix Unitaire (FCFA)</label>
                            <input type="number" id="prix_unitaire" name="prix_unitaire" step="1" value="<?= intval($produit['prix_unitaire']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="quantite_en_stock">Quantité en Stock</label>
                            <input type="number" id="quantite_en_stock" name="quantite_en_stock" value="<?= $produit['quantite_en_stock'] ?>" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" name="update_product" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="liste_produits.php" class="btn" style="background: var(--gray-200); color: var(--gray-700);">Annuler</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>