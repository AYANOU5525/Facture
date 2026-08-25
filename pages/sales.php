<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

exigerPermission(peutVoirVentes());

$page_title = 'Historique des Ventes';
include '../includes/header.php';

$entreprise_id = $_SESSION['entreprise_id'];

$per_page = 25;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM Vente WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$total_ventes = (int) $stmt->fetchColumn();
$total_pages  = (int) ceil($total_ventes / $per_page);

// Stat globales
$stmt = $pdo->prepare("SELECT SUM(Montant_Total), COUNT(*) FROM Vente WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
[$total_ca, $total_count] = $stmt->fetch(\PDO::FETCH_NUM);

$stmt = $pdo->prepare("SELECT SUM(Montant_Total) FROM Vente WHERE Id_Entreprise = ? AND DATE(Date_Vente) = CURDATE()");
$stmt->execute([$entreprise_id]);
$ca_jour = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT Id_Vente, Numero_Vente, Nom_Client, Nom_Vendeur, Date_Vente, Articles_JSON, Montant_Total, Type_Vente
    FROM Vente
    WHERE Id_Entreprise = ?
    ORDER BY Date_Vente DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$entreprise_id, $per_page, $offset]);
$ventes = $stmt->fetchAll();
?>

<style>
.sv-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.sv-filter-group {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--zinc-100);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0 12px;
    transition: border-color var(--duration) var(--ease);
    height: 40px;
}

.sv-filter-group:focus-within {
    border-color: var(--primary);
    background: var(--bg-card);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.sv-filter-group i { color: var(--text-muted); font-size: 0.8rem; }
.sv-filter-group input, .sv-filter-group span {
    background: transparent;
    border: none;
    outline: none;
    font-size: 0.85rem;
    color: var(--text-main);
    font-family: inherit;
    padding: 0 4px;
}

.sv-amount { font-weight: 800; color: var(--success); font-family: 'Plus Jakarta Sans', sans-serif; }

.type-b2b   { background:var(--primary-light); color:var(--primary); }
.type-comptoir { background:var(--success-bg); color:var(--success); }

code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
    background: var(--zinc-100);
    padding: 2px 7px;
    border-radius: 5px;
    color: var(--primary);
}
</style>

<div class="container fade-in">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-receipt"></i> Historique des Ventes</h1>
            <p>Toutes les transactions de votre entreprise</p>
        </div>
        <a href="invoice_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouvelle Vente
        </a>
    </div>

    <!-- KPI STRIP -->
    <div class="stats-grid stagger-children" style="grid-template-columns:repeat(3,1fr); margin-bottom:28px;">
        <div class="stat-card gradient-blue">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info">
                <h3>Chiffre d'affaires</h3>
                <div class="stat-value" style="font-size:1.4rem;"><?= number_format((float)$total_ca, 0, ',', ' ') ?> F</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--success-bg); color:var(--success);">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-info">
                <h3>Total ventes</h3>
                <div class="stat-value"><?= number_format($total_ventes) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning);">
                <i class="fas fa-sun"></i>
            </div>
            <div class="stat-info">
                <h3>CA aujourd'hui</h3>
                <div class="stat-value" style="font-size:1.4rem;"><?= number_format($ca_jour, 0, ',', ' ') ?> F</div>
            </div>
        </div>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <!-- Toolbar -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0; font-size:1.05rem;">Transactions</h2>
                <p style="margin:3px 0 0; font-size:0.8rem; color:var(--text-muted);">
                    Page <?= $page ?>/<?= max(1,$total_pages) ?> — <?= number_format($total_ventes) ?> ventes
                </p>
            </div>
            <div class="sv-toolbar">
                <div class="sv-filter-group">
                    <i class="fas fa-calendar-alt"></i>
                    <input type="date" id="dateDebut" onchange="filterSales()" title="Date début">
                    <span style="color:var(--text-muted);">→</span>
                    <input type="date" id="dateFin" onchange="filterSales()" title="Date fin">
                </div>
                <div class="sv-filter-group">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchClient" placeholder="Client..." onkeyup="filterSales()" style="width:140px;">
                </div>
            </div>
        </div>

        <?php if (count($ventes) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Articles</th>
                        <th>Type</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventes as $i => $v): ?>
                    <tr data-date="<?= $v['Date_Vente'] ?>" data-client="<?= htmlspecialchars($v['Nom_Client'] ?? '') ?>"
                        style="animation: fadeInUp 0.3s <?= $i * 20 ?>ms both;">
                        <td><code><?= htmlspecialchars($v['Numero_Vente'] ?? '—') ?></code></td>
                        <td style="white-space:nowrap; font-size:0.875rem; color:var(--text-muted);">
                            <?= !empty($v['Date_Vente']) ? date('d/m/Y H:i', strtotime($v['Date_Vente'])) : '—' ?>
                        </td>
                        <td style="font-weight:600;"><?= htmlspecialchars($v['Nom_Client'] ?? '—') ?></td>
                        <td>
                            <?php $articles = json_decode($v['Articles_JSON'], true); ?>
                            <span style="font-size:0.8rem; color:var(--text-muted);">
                                <?php if ($articles): ?>
                                    <span class="badge badge-secondary" style="margin-right:4px;"><?= count($articles) ?> art.</span>
                                    <?php foreach (array_slice($articles, 0, 2) as $art): ?>
                                        <?= htmlspecialchars($art['nom'] ?? '') ?><?= count($articles) > 1 ? ', ' : '' ?>
                                    <?php endforeach; ?>
                                    <?php if (count($articles) > 2) echo '<span style="color:var(--text-muted);">…</span>'; ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $v['Type_Vente'] === 'b2b' ? 'type-b2b' : 'type-comptoir' ?>">
                                <?= strtoupper($v['Type_Vente']) ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <span class="sv-amount"><?= number_format((float)($v['Montant_Total'] ?? 0), 0, ',', ' ') ?> F</span>
                        </td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a href="invoice_view.php?ref=<?= htmlspecialchars($v['Numero_Vente'] ?? '') ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary" title="Voir facture">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="invoice_view.php?ref=<?= htmlspecialchars($v['Numero_Vente'] ?? '') ?>&autoprint=1"
                               target="_blank" class="btn btn-sm btn-secondary" title="Imprimer PDF" style="margin-left:4px;">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div style="text-align:center; padding:56px 24px;">
                <div style="width:68px;height:68px;border-radius:20px;background:var(--zinc-100);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--zinc-400);margin:0 auto 18px;animation:float 3s ease-in-out infinite;">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3 style="font-size:1.05rem; margin-bottom:8px;">Aucune vente enregistrée</h3>
                <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Commencez à enregistrer vos transactions.</p>
                <a href="invoice_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Première vente</a>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
        <div style="padding:16px 24px; border-top:1px solid var(--border);">
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $window = 2; $start = max(1, $page - $window); $end = min($total_pages, $page + $window);
                if ($start > 1) { echo '<a href="?page=1">1</a>'; if ($start > 2) echo '<span>…</span>'; }
                for ($p = $start; $p <= $end; $p++):
                    if ($p === $page): ?><span class="active"><?= $p ?></span><?php
                    else: ?><a href="?page=<?= $p ?>"><?= $p ?></a><?php
                    endif;
                endfor;
                if ($end < $total_pages) { if ($end < $total_pages - 1) echo '<span>…</span>'; echo '<a href="?page='.$total_pages.'">'.$total_pages.'</a>'; }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </nav>
            <p style="text-align:center; color:var(--text-muted); font-size:0.8rem; margin-top:10px;">
                Page <?= $page ?>/<?= $total_pages ?> — <?= number_format($total_ventes) ?> ventes au total
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterSales() {
    const dateDebut    = document.getElementById('dateDebut').value;
    const dateFin      = document.getElementById('dateFin').value;
    const searchClient = document.getElementById('searchClient').value.toUpperCase();

    document.querySelectorAll('table tbody tr').forEach(function(tr) {
        const dateVente = (tr.dataset.date || '').split(' ')[0];
        const nomClient = (tr.dataset.client || '').toUpperCase();

        const matchDate = (!dateDebut || dateVente >= dateDebut) && (!dateFin || dateVente <= dateFin);
        const matchCli  = !searchClient || nomClient.includes(searchClient);

        tr.style.display = (matchDate && matchCli) ? '' : 'none';
    });
}
</script>

</body>
</html>
