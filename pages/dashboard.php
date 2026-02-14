<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'Tableau de bord';
include '../includes/header.php';

// Récupération des données entreprise
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// --- CALCUL DES STATISTIQUES ---

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

// 4. Dépenses B2B (Achats)
$stmt = $pdo->prepare("SELECT SUM(Montant_Total) FROM Commande_B2B WHERE Id_Entreprise_Acheteuse = ? AND Statut != 'en_attente'");
$stmt->execute([$entreprise_id]);
$total_achats = $stmt->fetchColumn() ?? 0;

// 5. Logistique - Expéditions à traiter
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Logistique WHERE Id_Entreprise = ? AND Statut_Livraison = 'traitement'");
$stmt->execute([$entreprise_id]);
$expeditions_urgent = $stmt->fetchColumn();

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

    <!-- ACCÈS RAPIDE -->
    <div class="quick-actions">
        <a href="products.php" class="action-btn">
            <div class="icon-box blue"><i class="fas fa-plus"></i></div>
            <span>Nouveau Produit</span>
        </a>
        <a href="invoice_add.php" class="action-btn">
            <div class="icon-box green"><i class="fas fa-file-invoice-dollar"></i></div>
            <span>Facturer Client</span>
        </a>
        <a href="reseau_b2b.php" class="action-btn">
            <div class="icon-box purple"><i class="fas fa-search"></i></div>
            <span>Chercher Fournisseur</span>
        </a>
        <a href="logistique.php" class="action-btn">
            <div class="icon-box orange"><i class="fas fa-truck"></i></div>
            <span>Logistique (<?= $expeditions_urgent ?>)</span>
        </a>
    </div>


    <!-- STATS CARDS (GRID 4 COLONNES) -->
    <div class="stats-grid">
        <!-- CA -->
        <div class="stat-card gradient-blue">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <h3>Chiffre d'Affaires</h3>
                <div class="stat-value"><?= number_format($total_ca, 0, ',', ' ') ?> <small>F</small></div>
            </div>
            <div class="stat-wave"></div>
        </div>

        <!-- Dépenses -->
        <div class="stat-card gradient-orange">
            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-info">
                <h3>Achats B2B (Dépenses)</h3>
                <div class="stat-value"><?= number_format($total_achats, 0, ',', ' ') ?> <small>F</small></div>
            </div>
        </div>

        <!-- Clients -->
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

        <!-- COMMANDES B2B EN ATTENTE -->
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

    </div>
</div>

<style>
    /* Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 25px;
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
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
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
</style>

</body>

</html>
