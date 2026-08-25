<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

exigerPermission(peutVoirExpeditions());

$page_title = 'Suivi Logistique';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// On récupère les entrées logistiques
$stmt = $pdo->prepare("
    SELECT l.*, 
           v.Nom_Client, 
           v.Numero_Vente,
           c.Numero_Commande,
           e.Nom_Entreprise AS Nom_Acheteur
    FROM Logistique l
    LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
    LEFT JOIN Commande_B2B c ON l.Id_Commande_B2B = c.Id_Commande_B2B
    LEFT JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
    WHERE l.Id_Entreprise = ? 
    ORDER BY l.Id_Logistique DESC
");
$stmt->execute([$entreprise_id]);
$logistique_brut = $stmt->fetchAll();

$recherche = trim($_GET['q'] ?? '');
$statut_filtre = $_GET['statut'] ?? '';
$statuts_valides = ['traitement', 'en_attente', 'expediee', 'livree', 'annulee'];
if (!in_array($statut_filtre, $statuts_valides, true)) {
    $statut_filtre = '';
}

// Filtrage
$logistique = $logistique_brut;
if ($recherche !== '' || $statut_filtre !== '') {
    $logistique = array_values(array_filter($logistique, function (array $ligne) use ($recherche, $statut_filtre): bool {
        $texte = implode(' ', [
            $ligne['Numero_Commande'] ?? '',
            $ligne['Numero_Vente'] ?? '',
            $ligne['Nom_Acheteur'] ?? '',
            $ligne['Nom_Client'] ?? '',
            $ligne['Transporteur'] ?? '',
            $ligne['Numero_Suivi'] ?? '',
        ]);

        $correspond_recherche = $recherche === ''
            || stripos($texte, $recherche) !== false;
        $correspond_statut = $statut_filtre === ''
            || ($ligne['Statut_Livraison'] ?? '') === $statut_filtre;

        return $correspond_recherche && $correspond_statut;
    }));
}

// Métriques pour les KPI cards
$nb_total = count($logistique_brut);
$nb_attente = count(array_filter($logistique_brut, fn($l) => in_array($l['Statut_Livraison'], ['traitement', 'en_attente'])));
$nb_route = count(array_filter($logistique_brut, fn($l) => $l['Statut_Livraison'] === 'expediee'));
$nb_livrees = count(array_filter($logistique_brut, fn($l) => $l['Statut_Livraison'] === 'livree'));
$nb_retard = 0;
foreach ($logistique_brut as $l) {
    $dp = $l['Date_Livraison_Prevue'];
    if ($dp && strtotime($dp) < time() && $l['Statut_Livraison'] !== 'livree' && $l['Statut_Livraison'] !== 'annulee') {
        $nb_retard++;
    }
}
?>

<style>
.lg-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.lg-filter-group {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--zinc-100);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0 12px;
    transition: border-color var(--duration) var(--ease);
    height: 40px;
}

.lg-filter-group:focus-within {
    border-color: var(--primary);
    background: var(--bg-card);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.lg-filter-group i { color: var(--text-muted); font-size: 0.85rem; }
.lg-filter-group input, .lg-filter-group select {
    background: transparent;
    border: none;
    outline: none;
    font-size: 0.875rem;
    color: var(--text-main);
    font-family: inherit;
    height: 100%;
}

.lg-filter-group select {
    padding-right: 12px;
}

code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
    background: var(--zinc-100);
    padding: 2px 7px;
    border-radius: 5px;
    color: var(--primary);
}

.delay-alert {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
}
</style>

<div class="container fade-in">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-truck-loading"></i> Suivi Logistique</h1>
            <p>Expéditions, livraisons et suivi des transporteurs</p>
        </div>
    </div>

    <!-- KPI STRIP -->
    <div class="grid-4 stagger-children" style="margin-bottom: 28px;">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning);">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-info">
                <h3>En préparation</h3>
                <div class="stat-value"><?= $nb_attente ?></div>
            </div>
        </div>
        <div class="stat-card gradient-blue">
            <div class="stat-icon"><i class="fas fa-truck"></i></div>
            <div class="stat-info">
                <h3>En transit</h3>
                <div class="stat-value"><?= $nb_route ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--success-bg); color:var(--success);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Livrées</h3>
                <div class="stat-value"><?= $nb_livrees ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:<?= $nb_retard > 0 ? 'var(--danger-bg)' : 'var(--zinc-100)' ?>; color:<?= $nb_retard > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>En retard</h3>
                <div class="stat-value" style="color:<?= $nb_retard > 0 ? 'var(--danger)' : 'var(--text-main)' ?>;"><?= $nb_retard ?></div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="card" style="padding:0; overflow:hidden;">
        <!-- Header / Toolbar -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0; font-size:1.05rem;">Expéditions &amp; Livraisons</h2>
                <p style="margin:3px 0 0; font-size:0.8rem; color:var(--text-muted);"><?= count($logistique) ?> expédition<?= count($logistique) > 1 ? 's' : '' ?></p>
            </div>

            <form method="GET" class="lg-toolbar">
                <div class="lg-filter-group">
                    <i class="fas fa-search"></i>
                    <input type="search" name="q" value="<?= htmlspecialchars($recherche) ?>" placeholder="Commande, client, transporteur...">
                </div>
                <div class="lg-filter-group">
                    <i class="fas fa-filter"></i>
                    <select name="statut" onchange="this.form.submit()">
                        <option value="">Tous les statuts</option>
                        <option value="traitement" <?= $statut_filtre === 'traitement' ? 'selected' : '' ?>>En préparation</option>
                        <option value="en_attente" <?= $statut_filtre === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="expediee" <?= $statut_filtre === 'expediee' ? 'selected' : '' ?>>En route</option>
                        <option value="livree" <?= $statut_filtre === 'livree' ? 'selected' : '' ?>>Livrée</option>
                        <option value="annulee" <?= $statut_filtre === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="height:40px; padding:0 16px;"><i class="fas fa-sync"></i></button>
                <?php if ($recherche !== '' || $statut_filtre !== ''): ?>
                    <a href="logistique.php" class="btn btn-secondary" style="height:40px; display:inline-flex; align-items:center; justify-content:center;"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($logistique) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Réf. Document</th>
                            <th>Client / Acheteur</th>
                            <th>Transporteur</th>
                            <th>N° Suivi</th>
                            <th>Statut</th>
                            <th>Date Prévue</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logistique as $i => $l):
                            $dp = $l['Date_Livraison_Prevue'];
                            $retard = $dp && strtotime($dp) < time() && $l['Statut_Livraison'] !== 'livree' && $l['Statut_Livraison'] !== 'annulee';
                            
                            $sbadge = match($l['Statut_Livraison']) {
                                'livree'     => 'success',
                                'expediee'   => 'info',
                                'en_attente' => 'warning',
                                'traitement' => 'warning',
                                'annulee'    => 'danger',
                                default      => 'secondary',
                            };
                            $slabel = match($l['Statut_Livraison']) {
                                'livree'     => 'Livrée',
                                'expediee'   => 'En route',
                                'en_attente' => 'En attente',
                                'traitement' => 'Préparation',
                                'annulee'    => 'Annulée',
                                default      => ucfirst($l['Statut_Livraison']),
                            };
                        ?>
                            <tr style="animation: fadeInUp 0.3s <?= $i * 20 ?>ms both;">
                                <td>
                                    <?php if ($l['Id_Commande_B2B']): ?>
                                        <span class="badge badge-primary" style="font-size:0.65rem; margin-bottom:4px;"><i class="fas fa-handshake"></i> B2B</span><br>
                                        <code><?= htmlspecialchars($l['Numero_Commande'] ?? '-') ?></code>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" style="font-size:0.65rem; margin-bottom:4px;"><i class="fas fa-store"></i> Comptoir</span><br>
                                        <code><?= htmlspecialchars($l['Numero_Vente'] ?? '-') ?></code>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;">
                                    <?php if ($l['Id_Commande_B2B']): ?>
                                        <?= htmlspecialchars($l['Nom_Acheteur'] ?? '-') ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($l['Nom_Client'] ?? '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($l['Transporteur'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($l['Numero_Suivi'] ?? '-') ?></code></td>
                                <td>
                                    <span class="badge badge-<?= $sbadge ?>"><?= $slabel ?></span>
                                </td>
                                <td>
                                    <?php if ($retard): ?>
                                        <span class="delay-alert" title="Livraison en retard ! Date limite dépassée.">
                                            <i class="fas fa-exclamation-triangle"></i> <?= date('d/m/Y', strtotime($dp)) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:0.875rem; color:var(--text-muted); font-weight:500;">
                                            <?= $dp ? date('d/m/Y', strtotime($dp)) : '—' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (aRole(ROLE_LIVREUR) && $l['Statut_Livraison'] === 'expediee'): ?>
                                        <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-sm btn-success" title="Confirmer la livraison">
                                            <i class="fas fa-check-circle"></i> Livré
                                        </a>
                                    <?php elseif (aRole(ROLE_LIVREUR)): ?>
                                        <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-sm btn-primary" title="Traiter cette livraison">
                                            <i class="fas fa-arrow-right"></i> Traiter
                                        </a>
                                    <?php else: ?>
                                        <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-sm btn-outline-primary" title="Suivi &amp; Carte">
                                            <i class="fas fa-map-marked-alt"></i> Carte
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:56px 24px;">
                <div style="width:68px;height:68px;border-radius:20px;background:var(--zinc-100);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--zinc-400);margin:0 auto 18px;animation:float 3s ease-in-out infinite;">
                    <i class="fas fa-truck"></i>
                </div>
                <h3 style="font-size:1.05rem; margin-bottom:8px;">Aucune expédition</h3>
                <p style="color:var(--text-muted); font-size:0.9rem;">Aucun colis ne correspond aux critères de recherche actuels.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
