<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'Tableau de bord';
include '../includes/header.php';

// Récupération des données entreprise (déjà dans la session via auth.php)
$entreprise_id = $_SESSION['entreprise_id'];

// ── Livreur → redirection directe vers logistique ──
if (hasRole(ROLE_LIVREUR)) {
    header('Location: logistique.php');
    exit();
}

// ── Admin plateforme → tableau de bord plateforme uniquement ──
if (isAppAdmin()) {
    $nb_entreprises = (int) $pdo->query("SELECT COUNT(*) FROM Entreprise")->fetchColumn();
    $nb_utilisateurs = (int) $pdo->query("SELECT COUNT(*) FROM Utilisateur")->fetchColumn();
    $nb_ventes_total = (int) $pdo->query("SELECT COUNT(*) FROM Vente")->fetchColumn();
    $ca_total_plateforme = (float) ($pdo->query("SELECT COALESCE(SUM(Montant_Total),0) FROM Vente")->fetchColumn());

    $entreprises_recentes = $pdo->query("
        SELECT e.Nom_Entreprise, e.Date_Creation,
               COUNT(u.Id_Utilisateur) AS nb_membres
        FROM Entreprise e
        LEFT JOIN Utilisateur u ON u.Id_Entreprise = e.Id_Entreprise
        GROUP BY e.Id_Entreprise
        ORDER BY e.Date_Creation DESC
        LIMIT 8
    ")->fetchAll();

    $heure = date('H');
    $salutation = ($heure >= 18) ? 'Bonsoir' : 'Bonjour';
    ?>
    <div class="container fade-in">
        <div class="dashboard-header">
            <div>
                <h1><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?> !</h1>
                <p style="color:var(--text-muted);">Vue administrateur plateforme — accès en lecture seule sur les données globales.</p>
            </div>
            <span class="badge badge-danger" style="font-size:0.9rem; padding:8px 14px;">
                <i class="fas fa-shield-alt"></i> Admin Plateforme
            </span>
        </div>

        <!-- Stats globales -->
        <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="stat-card gradient-blue">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-info">
                    <h3>Entreprises</h3>
                    <div class="stat-value"><?= $nb_entreprises ?></div>
                </div>
            </div>
            <div class="stat-card gradient-orange">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3>Utilisateurs</h3>
                    <div class="stat-value"><?= $nb_utilisateurs ?></div>
                </div>
            </div>
            <div class="stat-card" style="background:var(--bg-card); border:1px solid var(--zinc-200);">
                <div class="stat-icon text-success"><i class="fas fa-receipt"></i></div>
                <div class="stat-info">
                    <h3>Ventes totales</h3>
                    <div class="stat-value text-dark"><?= number_format($nb_ventes_total, 0, ',', ' ') ?></div>
                </div>
            </div>
            <div class="stat-card" style="background:var(--bg-card); border:1px solid var(--zinc-200);">
                <div class="stat-icon text-primary"><i class="fas fa-wallet"></i></div>
                <div class="stat-info">
                    <h3>CA Plateforme</h3>
                    <div class="stat-value text-dark"><?= number_format($ca_total_plateforme, 0, ',', ' ') ?> <small>F</small></div>
                </div>
            </div>
        </div>

        <!-- Liste des entreprises -->
        <div class="card">
            <div class="card-header-flex">
                <h3><i class="fas fa-building text-primary"></i> Entreprises enregistrées</h3>
                <span style="color:var(--text-muted); font-size:0.85rem;"><?= $nb_entreprises ?> au total</span>
            </div>
            <?php if ($entreprises_recentes): ?>
                <div class="scrollable-list">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Entreprise</th>
                                <th>Membres</th>
                                <th>Inscription</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entreprises_recentes as $e): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($e['Nom_Entreprise']) ?></strong></td>
                                    <td><?= (int)$e['nb_membres'] ?></td>
                                    <td style="color:var(--text-muted); font-size:0.85rem;">
                                        <?= $e['Date_Creation'] ? date('d/m/Y', strtotime($e['Date_Creation'])) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-building"></i>
                    <p>Aucune entreprise enregistrée.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="alert alert-info" style="margin-top:20px;">
            <i class="fas fa-info-circle"></i>
            En tant qu'administrateur plateforme, vous n'avez pas accès aux données internes des entreprises (stocks, ventes, B2B, équipe).
        </div>
    </div>
    </body></html>
    <?php
    exit();
}

// 1. Chiffre d'Affaires (Ventes + B2B Vendu)
$stmt = $pdo->prepare("SELECT SUM(Montant_Total) FROM Vente WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$ca_direct = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(Montant_Total) FROM Commande_B2B WHERE Id_Entreprise_Vendeuse = ? AND Statut = 'livree'");
$stmt->execute([$entreprise_id]);
$ca_b2b = $stmt->fetchColumn() ?? 0;
$total_ca = $ca_direct + $ca_b2b;

// 2. Produits
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Produit WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$total_produits = $stmt->fetchColumn();

// 3. Clients Uniques
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT Nom_Client) FROM Vente WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$total_clients = $stmt->fetchColumn();

// 4. Dépenses B2B (Achats) — managers uniquement
$total_achats = 0;
if (isManager()) {
    $stmt = $pdo->prepare("SELECT SUM(Montant_Total) FROM Commande_B2B WHERE Id_Entreprise_Acheteuse = ? AND Statut != 'en_attente'");
    $stmt->execute([$entreprise_id]);
    $total_achats = $stmt->fetchColumn() ?? 0;
}

// 5. Logistique - Expéditions à traiter
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Logistique WHERE Id_Entreprise = ? AND Statut_Livraison = 'traitement'");
$stmt->execute([$entreprise_id]);
$expeditions_urgent = $stmt->fetchColumn();

// 6. CA par mois sur les 6 derniers mois
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(Date_Vente, '%Y-%m') AS mois, SUM(Montant_Total) AS ca
    FROM Vente
    WHERE Id_Entreprise = ?
      AND Date_Vente >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mois
    ORDER BY mois ASC
");
$stmt->execute([$entreprise_id]);
$ca_mensuel_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Construire un tableau des 6 derniers mois (même si aucune vente ce mois-là)
$mois_labels = [];
$ca_data     = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i month"));
    $mois_labels[] = strftime('%b %Y', strtotime($key . '-01')) ?: date('M Y', strtotime($key . '-01'));
    $ca_data[]     = (float) ($ca_mensuel_raw[$key] ?? 0);
}

// 7. Produits en alerte stock (stock <= seuil)
$stmt = $pdo->prepare("
    SELECT Nom_Produit, Quantite_En_Stock,
           COALESCE(Seuil_Alerte_Stock, 5) AS Seuil_Alerte_Stock
    FROM Produit
    WHERE Id_Entreprise = ?
      AND Quantite_En_Stock <= COALESCE(Seuil_Alerte_Stock, 5)
    ORDER BY Quantite_En_Stock ASC
    LIMIT 8
");
$stmt->execute([$entreprise_id]);
$produits_alerte = $stmt->fetchAll();

// Message d'accueil selon l'heure
$heure = date('H');
$salutation = ($heure >= 18) ? 'Bonsoir' : 'Bonjour';
?>

<div class="container fade-in">

    <!-- EN-TETE ACCUEIL -->
    <div class="dashboard-header">
        <div>
            <h1><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?> !</h1>
            <p>Voici l'état de votre activité en temps réel.</p>
        </div>
        <div class="date-badge">
            <i class="far fa-calendar-alt"></i> <?= date('d/m/Y') ?>
        </div>
    </div>

    <?php if (!empty($produits_alerte)): ?>
    <div class="stock-alert-banner">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong><?= count($produits_alerte) ?> produit(s) en alerte de stock</strong>
            <ul class="alert-list">
                <?php foreach ($produits_alerte as $pa): ?>
                    <li class="alert-chip"><?= htmlspecialchars($pa['Nom_Produit']) ?> — <?= $pa['Quantite_En_Stock'] ?> restant(s)</li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- QUICK ACTION CARDS -->
    <div class="dashboard-featured-actions" style="background: var(--bg-card); padding: 25px; border-radius: 16px; margin-bottom: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--zinc-200);">
        <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.4rem; color: var(--text-main);"><i class="fas fa-bolt"></i> Actions Rapides Caisse & Stock</h2>
        <div class="dashboard-featured-grid" style="display: grid; grid-template-columns: <?= canManageStock() ? '1fr 1fr' : '1fr' ?>; gap: 20px;">

            <?php if (canManageStock()): ?>
            <a href="approvisionnement.php" style="background: linear-gradient(135deg, #0ea5e9, #0284c7); color: white; border-radius: 16px; padding: 30px; text-decoration: none; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3);" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <h3 style="margin: 0; font-size: 1.5rem; text-transform: uppercase; letter-spacing: 1px;">Entrée Stock</h3>
            </a>
            <?php endif; ?>

            <?php if (canSell()): ?>
            <a href="invoice_add.php" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 16px; padding: 30px; text-decoration: none; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-cash-register" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <h3 style="margin: 0; font-size: 1.5rem; text-transform: uppercase; letter-spacing: 1px;">Vente Produit</h3>
            </a>
            <?php endif; ?>

        </div>
    </div>

    <div class="quick-actions">
        <?php if (canViewStock()): ?>
        <a href="products.php" class="action-btn">
            <div class="icon-box blue"><i class="fas fa-plus"></i></div>
            <span>Nouveau Produit</span>
        </a>
        <?php endif; ?>
        <?php if (canAccessB2B()): ?>
        <a href="reseau_b2b.php" class="action-btn">
            <div class="icon-box purple"><i class="fas fa-search"></i></div>
            <span>Chercher Fournisseur</span>
        </a>
        <?php endif; ?>
        <?php if (canDeliver()): ?>
        <a href="logistique.php" class="action-btn">
            <div class="icon-box orange"><i class="fas fa-truck"></i></div>
            <span>Logistique (<?= $expeditions_urgent ?>)</span>
        </a>
        <?php endif; ?>
    </div>


    <!-- STATS CARDS (GRID 4 COLONNES) -->
    <div class="stats-grid">
        <!-- CA -->
        <a href="sales.php" target="_blank" rel="noopener noreferrer">
            <div class="stat-card gradient-blue">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div class="stat-info">
                    <h3>Chiffre d'Affaires</h3>
                    <div class="stat-value"><?= number_format($total_ca, 0, ',', ' ') ?> <small>F</small></div>
                </div>
                <div class="stat-wave"></div>
            </div>
        </a>

        <?php if (isManager()): ?>
        <!-- Dépenses -->
        <div class="stat-card gradient-orange">
            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-info">
                <h3>Achats B2B (Dépenses)</h3>
                <div class="stat-value"><?= number_format($total_achats, 0, ',', ' ') ?> <small>F</small></div>
            </div>
        </div>
        <?php endif; ?>

        <a href="clients.php" class="stat-card bg-white" style="text-decoration: none; display: flex; color: inherit;">
            <div class="stat-icon text-purple"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3>Clients Uniques</h3>
                <div class="stat-value text-dark"><?= $total_clients ?></div>
            </div>
        </a>

        <div class="stat-card bg-white">
            <div class="stat-icon text-primary"><i class="fas fa-box"></i></div>
            <div class="stat-info">
                <h3>Produits Stock</h3>
                <div class="stat-value text-dark"><?= $total_produits ?></div>
            </div>
        </div>
    </div>

    <!-- GRAPHIQUE CA MENSUEL -->
    <div class="card" style="margin-bottom:25px;">
        <div class="card-header-flex">
            <h3><i class="fas fa-chart-bar text-primary"></i> Évolution du CA — 6 derniers mois</h3>
        </div>
        <canvas id="caChart" height="80"></canvas>
    </div>

    <div class="dashboard-grid">

        <div class="card">
            <div class="card-header-flex">
                <h3><i class="fas fa-receipt text-primary"></i> Ventes Récentes</h3>
                <a href="sales.php" class="btn-link">Tout voir</a>
            </div>
            <?php
            $stmt = $pdo->prepare("SELECT Id_Vente, Numero_Vente, Nom_Client, Date_Vente, Montant_Total FROM Vente WHERE Id_Entreprise = ? ORDER BY Date_Vente DESC LIMIT 5");
            $stmt->execute([$entreprise_id]);
            $ventes = $stmt->fetchAll();
            ?>
            <?php if ($ventes): ?>
                <div class="scrollable-list">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th class="text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventes as $v): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="icon-circle bg-light-blue"><i class="fas fa-user"></i></div>
                                            <div>
                                                <div class="font-weight-bold"><?= htmlspecialchars($v['Nom_Client']) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($v['Date_Vente'])) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-right font-weight-bold text-success">
                                        +<?= number_format($v['Montant_Total'], 0, ',', ' ') ?> F
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>Aucune vente pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- COMMANDES B2B EN ATTENTE — managers seulement -->
        <?php if (isManager()): ?>
        <div class="card">
            <div class="card-header-flex">
                <h3><i class="fas fa-bell text-warning"></i> Commandes à Valider</h3>
                <a href="commandes_b2b.php?onglet=recues" class="btn-link">Gérer</a>
            </div>
            <?php
            $stmt = $pdo->prepare("SELECT c.*, e.Nom_Entreprise FROM Commande_B2B c JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise WHERE c.Id_Entreprise_Vendeuse = ? AND c.Statut = 'en_attente' ORDER BY c.Date_Commande DESC LIMIT 5");
            $stmt->execute([$entreprise_id]);
            $b2b = $stmt->fetchAll();
            ?>
            <?php if ($b2b): ?>
                <div class="scrollable-list">
                    <div class="list-group">
                        <?php foreach ($b2b as $c): ?>
                            <div class="list-item">
                                <div class="d-flex align-items-center">
                                    <div class="icon-circle bg-light-warning"><i class="fas fa-exclamation"></i></div>
                                    <div>
                                        <div class="font-weight-bold"><?= htmlspecialchars($c['Nom_Entreprise']) ?></div>
                                        <small class="text-muted">Commande N°<?= $c['Numero_Commande'] ?></small>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-weight-bold"><?= number_format($c['Montant_Total'], 0, ',', ' ') ?> F</div>
                                    <a href="commandes_b2b.php?onglet=recues" class="btn btn-sm btn-success mt-1">Valider</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle text-success"></i>
                    <p>Tout est à jour ! Aucune commande en attente.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; // isManager ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels = <?= json_encode($mois_labels) ?>;
    const data   = <?= json_encode($ca_data) ?>;

    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor  = dark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
    const textColor  = dark ? '#8a8f9a' : '#6b7076';

    const ctx = document.getElementById('caChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Chiffre d\'Affaires (F)',
                data,
                backgroundColor: 'rgba(0, 70, 255, 0.15)',
                borderColor:     '#0046ff',
                borderWidth:     2,
                borderRadius:    6,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => new Intl.NumberFormat('fr-FR').format(ctx.parsed.y) + ' F'
                    }
                }
            },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor } },
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        callback: v => new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v)
                    }
                }
            }
        }
    });
})();
</script>

<style>
    /* Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 25px;
        gap: 16px;
    }

    .dashboard-header h1 {
        margin: 0;
        font-size: 1.8rem;
        background: linear-gradient(90deg, var(--text-main), var(--primary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .date-badge {
        background: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 500;
        color: var(--text-muted);
        box-shadow: var(--shadow-sm);
        border: 1px solid #f1f5f9;
    }

    /* Quick Actions */
    .quick-actions {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    .action-btn {
        flex: 1;
        background: white;
        padding: 15px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 15px;
        text-decoration: none;
        color: var(--text-main);
        box-shadow: var(--shadow-sm);
        transition: transform 0.2s, box-shadow 0.2s;
        border: 1px solid #f1f5f9;
    }

    .action-btn:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary);
    }

    .action-btn span {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .icon-box {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
    }

    .icon-box.blue {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
    }

    .icon-box.green {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .icon-box.purple {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .icon-box.orange {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }


    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        padding: 25px;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: var(--shadow-md);
    }

    .stat-card.bg-white {
        background: white;
    }

    .stat-card.gradient-blue {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .stat-card.gradient-orange {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }

    .stat-card .stat-value small {
        font-size: 0.6em;
        opacity: 0.8;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background: rgba(0, 0, 0, 0.05);
    }

    .gradient-blue .stat-icon,
    .gradient-orange .stat-icon {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .text-primary {
        color: var(--primary);
    }

    .text-purple {
        color: #8b5cf6;
    }

    .stat-info h3 {
        margin: 0 0 5px 0;
        font-size: 0.85rem;
        font-weight: 500;
        opacity: 0.8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.2;
    }

    /* Grid Layout */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 25px;
    }

    .dashboard-featured-grid > a,
    .quick-actions > .action-btn,
    .dashboard-grid > .card {
        min-width: 0;
    }

    .list-item > .d-flex {
        min-width: 0;
    }

    .list-item > .d-flex > div:last-child {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .card-header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 15px;
    }

    .card-header-flex h3 {
        margin: 0;
        font-size: 1.1rem;
    }

    .btn-link {
        color: var(--primary);
        font-weight: 500;
    }

    .btn-link:hover {
        text-decoration: underline;
    }

    /* List Items */
    .list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .list-item:last-child {
        border-bottom: none;
    }

    .icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
    }

    .bg-light-blue {
        background: #eff6ff;
        color: var(--primary);
    }

    .bg-light-warning {
        background: #fffbeb;
        color: var(--warning);
    }

    .empty-state {
        text-align: center;
        padding: 40px 0;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }

    @media (max-width: 768px) {
        .dashboard-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .dashboard-header h1 {
            font-size: 1.5rem;
        }

        .dashboard-featured-actions {
            padding: 18px !important;
        }

        .dashboard-featured-actions h2 {
            font-size: 1.15rem !important;
        }

        .dashboard-featured-grid {
            grid-template-columns: 1fr !important;
        }

        .dashboard-featured-grid > a {
            padding: 24px !important;
        }

        .quick-actions {
            flex-direction: column;
        }

        .action-btn {
            width: 100%;
        }

        .dashboard-grid {
            grid-template-columns: 1fr;
        }

        .card-header-flex {
            align-items: flex-start;
            gap: 10px;
        }

        .card-header-flex h3 {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .list-item {
            align-items: flex-start;
            gap: 12px;
        }

        .list-item > .text-right {
            flex-shrink: 0;
        }
    }

    @media (max-width: 480px) {
        .dashboard-featured-grid > a {
            min-height: 150px;
            padding: 20px !important;
        }

        .dashboard-featured-grid > a h3 {
            font-size: 1.15rem !important;
        }

        .stat-card {
            padding: 18px;
            gap: 14px;
        }

        .stat-value {
            font-size: 1.3rem;
            overflow-wrap: anywhere;
        }

        .list-item > .text-right {
            font-size: 0.85rem;
        }
    }
</style>

</body>

</html>