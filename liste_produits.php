<?php
require_once 'auth.php';

$produits = [];
$stmt = $pdo->prepare('SELECT id, nom, description, prix_unitaire, quantite_en_stock FROM produits WHERE entreprise_id = :ent_id ORDER BY nom ASC');
$stmt->execute(['ent_id' => $entreprise_id]);
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits | FactuPro</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success {
            background: #ecfdf5;
            color: #059669;
        }

        .badge-warning {
            background: #fffbeb;
            color: #d97706;
        }

        .badge-danger {
            background: #fef2f2;
            color: #dc2626;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 700;">Gestion du Stock</h1>
                    <p style="color: var(--gray-600);">Visualisez et gérez votre inventaire de produits</p>
                </div>
                <a href="ajouter_produit.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouveau Produit
                </a>
            </header>

            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Description</th>
                                <th>Prix Unitaire</th>
                                <th>Stock Actuel</th>
                                <th>Statut</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($produits)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 3rem; color: var(--gray-500);">Aucun produit trouvé.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($produits as $p): ?>
                                    <?php
                                    $stock = $p['quantite_en_stock'];
                                    $status_class = ($stock > 10) ? 'badge-success' : (($stock > 0) ? 'badge-warning' : 'badge-danger');
                                    $status_label = ($stock > 10) ? 'En Stock' : (($stock > 0) ? 'Faible' : 'Rupture');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($p['nom']) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);">ID: #<?= $p['id'] ?></div>
                                        </td>
                                        <td style="color: var(--gray-600); font-size: 0.875rem;"><?= htmlspecialchars($p['description']) ?: '-' ?></td>
                                        <td style="font-weight: 600;"><?= number_format($p['prix_unitaire'], 0, ',', ' ') ?> FCFA</td>
                                        <td>
                                            <span style="font-weight: 700;"><?= $stock ?></span> unités
                                        </td>
                                        <td><span class="badge <?= $status_class ?>"><?= $status_label ?></span></td>
                                        <td style="text-align: right;">
                                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                                <a href="modifier_produit.php?id=<?= $p['id'] ?>" class="btn" style="padding: 0.5rem; background: var(--gray-100); color: var(--primary);">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="supprimer_produit.php?id=<?= $p['id'] ?>" class="btn" style="padding: 0.5rem; background: #fef2f2; color: #ef4444;" onclick="return confirm('Confirmer la suppression ?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>