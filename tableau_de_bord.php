<?php
require_once 'auth.php';
require_once 'bdd.php';

// Fetch enterprise info
$stmt = $pdo->prepare('SELECT nom FROM entreprises WHERE id = :id');
$stmt->execute(['id' => $entreprise_id]);
$entreprise = $stmt->fetch(PDO::FETCH_ASSOC);
$nom_entreprise = $entreprise['nom'] ?? 'Mon Entreprise';

// Fetch sales from database
$stmt = $pdo->prepare('SELECT v.id, p.nom as nom_produit, v.quantite, v.montant, v.date FROM ventes v JOIN produits p ON v.id_produit = p.id WHERE v.entreprise_id = :ent_id ORDER BY v.date DESC LIMIT 5');
$stmt->execute(['ent_id' => $entreprise_id]);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rapport de ventes par produit
$stmt_sales_by_product = $pdo->prepare('SELECT p.nom as nom_produit, SUM(v.quantite) as total_quantite_vendue, SUM(v.montant) as total_montant_vendu FROM ventes v JOIN produits p ON v.id_produit = p.id WHERE v.entreprise_id = :ent_id GROUP BY p.nom ORDER BY total_montant_vendu DESC');
$stmt_sales_by_product->execute(['ent_id' => $entreprise_id]);
$sales_by_product = $stmt_sales_by_product->fetchAll(PDO::FETCH_ASSOC);

// Fetch total stats
$stmt_stats = $pdo->prepare("SELECT 
    (SELECT SUM(montant) FROM ventes WHERE entreprise_id = :ent_id) as total_sales,
    (SELECT SUM(montant) FROM achats WHERE entreprise_id = :ent_id) as total_purchases
");
$stmt_stats->execute(['ent_id' => $entreprise_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_sales = $stats['total_sales'] ?? 0;
$total_purchases = $stats['total_purchases'] ?? 0;
$net_profit = $total_sales - $total_purchases;

// Fetch low stock products
$stmt = $pdo->prepare('SELECT nom, quantite_en_stock FROM produits WHERE entreprise_id = :ent_id AND quantite_en_stock <= 5 ORDER BY quantite_en_stock ASC');
$stmt->execute(['ent_id' => $entreprise_id]);
$low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($nom_entreprise) ?></title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--dark);"><?= htmlspecialchars($nom_entreprise) ?></h1>
                    <p style="color: var(--gray-600);">Bienvenue, <strong><?= htmlspecialchars($username) ?></strong> 👋</p>
                </div>
                <div class="header-actions">
                    <a href="creer_facture.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nouvelle Facture
                    </a>
                </div>
            </header>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-primary-light">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <div class="label">Ventes Totales</div>
                        <div class="value"><?= number_format($total_sales, 0, ',', ' ') ?> FCFA</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-danger-light">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <div class="label">Achats Totaux</div>
                        <div class="value"><?= number_format($total_purchases, 0, ',', ' ') ?> FCFA</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">
                        <i class="fas fa-hand-holding-dollar"></i>
                    </div>
                    <div class="stat-info">
                        <div class="label">Bénéfice Net</div>
                        <div class="value"><?= number_format($net_profit, 0, ',', ' ') ?> FCFA</div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                <!-- Main Report -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-chart-pie text-primary"></i> Rapport des Ventes par Produit
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Chiffre d'Affaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sales_by_product)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: var(--gray-500);">Aucune donnée disponible</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sales_by_product as $item): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($item['nom_produit']) ?></td>
                                            <td><?= htmlspecialchars($item['total_quantite_vendue']) ?></td>
                                            <td style="font-weight: 600; color: var(--primary);"><?= number_format($item['total_montant_vendu'], 0, ',', ' ') ?> FCFA</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="card" style="border-left: 4px solid var(--warning);">
                    <div class="card-title">
                        <i class="fas fa-triangle-exclamation text-warning"></i> Alertes Stock
                    </div>
                    <?php if (empty($low_stock_products)): ?>
                        <div style="text-align: center; padding: 1rem; color: var(--gray-500);">
                            <i class="fas fa-check-circle text-success" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                            Tout est en stock !
                        </div>
                    <?php else: ?>
                        <ul style="list-style: none;">
                            <?php foreach ($low_stock_products as $product): ?>
                                <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-100);">
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($product['nom']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">Critique à &le; 5</div>
                                    </div>
                                    <span class="bg-danger-light" style="padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem; font-weight: 600;">
                                        <?= htmlspecialchars($product['quantite_en_stock']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="liste_produits.php" style="display: block; text-align: center; margin-top: 1rem; font-size: 0.875rem; text-decoration: none; color: var(--primary); font-weight: 600;">
                            Gérer le stock <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity if needed -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-history text-info"></i> Ventes Récentes
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_sales)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--gray-500);">Aucune vente récente</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td style="color: var(--gray-600);"><?= date('d/m/Y', strtotime($sale['date'])) ?></td>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($sale['nom_produit']) ?></td>
                                        <td><?= htmlspecialchars($sale['quantite']) ?></td>
                                        <td style="font-weight: 600;"><?= number_format($sale['montant'], 0, ',', ' ') ?> FCFA</td>
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