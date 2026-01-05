<?php
require_once 'auth.php';
require_once 'bdd.php';

$username = $_SESSION['username'];

if (isset($_POST['add_product'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $prix_unitaire = floatval($_POST['prix_unitaire']);
    $quantite_en_stock = intval($_POST['quantite_en_stock']);

    if (!empty($nom) && $prix_unitaire > 0 && $quantite_en_stock >= 0) {
        $stmt = $pdo->prepare('INSERT INTO produits (nom, description, prix_unitaire, quantite_en_stock, cree_par, entreprise_id) VALUES (:nom, :description, :prix_unitaire, :quantite_en_stock, :cree_par, :entreprise_id)');
        $stmt->execute([
            'nom' => $nom,
            'description' => $description,
            'prix_unitaire' => $prix_unitaire,
            'quantite_en_stock' => $quantite_en_stock,
            'cree_par' => $_SESSION['username'],
            'entreprise_id' => $_SESSION['entreprise_id']
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
    <title>Ajouter Produit | FactuPro</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: 700;">Nouveau Produit</h1>
                <p style="color: var(--gray-600);">Ajoutez un nouvel article à votre catalogue de vente</p>
            </header>

            <div class="card" style="max-width: 700px;">
                <div class="card-title"><i class="fas fa-box-open text-primary"></i> Détails du Produit</div>

                <?php if (isset($error_message)): ?>
                    <div class="card bg-danger-light" style="border: none; color: #dc2626; padding: 1rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-circle-exclamation"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <form action="ajouter_produit.php" method="POST">
                    <div class="form-group">
                        <label for="nom">Nom du Produit</label>
                        <input type="text" id="nom" name="nom" required placeholder="Ex: Pack de 6 bouteilles d'eau">
                    </div>

                    <div class="form-group">
                        <label for="description">Description (optionnelle)</label>
                        <textarea id="description" name="description" rows="3" placeholder="Description courte du produit..."></textarea>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label for="prix_unitaire">Prix de Vente (FCFA)</label>
                            <input type="number" id="prix_unitaire" name="prix_unitaire" step="1" required placeholder="0 F">
                        </div>
                        <div class="form-group">
                            <label for="quantite_en_stock">Stock Initial</label>
                            <input type="number" id="quantite_en_stock" name="quantite_en_stock" required placeholder="0">
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" name="add_product" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-save"></i> Enregistrer le Produit
                        </button>
                        <a href="liste_produits.php" class="btn" style="background: var(--gray-200); color: var(--gray-700);">Annuler</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>