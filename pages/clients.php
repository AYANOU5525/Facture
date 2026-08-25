<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

/** @var PDO $pdo */

exigerPermission(peutVoirClients());

$page_title = 'Clients Uniques';
include '../includes/header.php';

$entreprise_id = $_SESSION['entreprise_id'];

$per_page    = 20;
$page_direct = max(1, (int) ($_GET['page'] ?? 1));
$offset_d    = ($page_direct - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT Nom_Client) FROM Vente WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$total_directs = (int) $stmt->fetchColumn();
$pages_directs = (int) ceil($total_directs / $per_page);

$stmt = $pdo->prepare("
    SELECT
        Nom_Client,
        COUNT(*) as Nb_Commandes,
        SUM(Montant_Total) as Total_Depense,
        MAX(Date_Vente) as Derniere_Commande
    FROM Vente
    WHERE Id_Entreprise = ?
    GROUP BY Nom_Client
    ORDER BY Total_Depense DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$entreprise_id, $per_page, $offset_d]);
$clients_directs = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        e.Id_Entreprise,
        e.Nom_Entreprise as Nom_Client,
        COUNT(*) as Nb_Commandes,
        SUM(c.Montant_Total) as Total_Depense,
        MAX(c.Date_Commande) as Derniere_Commande
    FROM Commande_B2B c
    JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
    WHERE c.Id_Entreprise_Vendeuse = ?
    GROUP BY c.Id_Entreprise_Acheteuse
    ORDER BY Total_Depense DESC
");
$stmt->execute([$entreprise_id]);
$clients_b2b = $stmt->fetchAll();

// Global KPIs
$total_b2b_volume  = array_sum(array_column($clients_b2b, 'Total_Depense'));
$total_dir_volume  = array_sum(array_column($clients_directs, 'Total_Depense'));
$nb_b2b   = count($clients_b2b);
$nb_directs = $total_directs;
?>

<style>
/* ── Clients Premium ── */
.cl-stat-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}

.cl-stat {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all var(--duration-lg) var(--ease);
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-xs);
}

.cl-stat:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

.cl-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.cl-stat-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted); margin-bottom: 4px; }
.cl-stat-value { font-size: 1.45rem; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); line-height: 1; }

/* Client card row */
.cl-row-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 800;
    color: white;
    flex-shrink: 0;
}

.cl-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    gap: 16px;
    flex-wrap: wrap;
}

.cl-search {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--zinc-100);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0 14px;
    transition: border-color var(--duration) var(--ease);
}

.cl-search:focus-within {
    border-color: var(--primary);
    background: var(--bg-card);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.cl-search i { color: var(--text-muted); font-size: 0.85rem; }
.cl-search input {
    background: transparent;
    border: none;
    outline: none;
    padding: 9px 0;
    font-size: 0.875rem;
    color: var(--text-main);
    font-family: inherit;
    width: 200px;
}

.cl-volume { font-weight: 800; color: var(--success); font-family: 'Plus Jakarta Sans', sans-serif; }

@media (max-width: 992px) { .cl-stat-strip { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .cl-stat-strip { grid-template-columns: 1fr 1fr; gap: 12px; } }
</style>

<div class="container fade-in">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> Clients Uniques</h1>
            <p>Base de clients B2B et directs — volumes d'achat</p>
        </div>
    </div>

    <!-- KPI STRIP -->
    <div class="cl-stat-strip stagger-children">
        <div class="cl-stat">
            <div class="cl-stat-icon" style="background:var(--primary-light); color:var(--primary);">
                <i class="fas fa-handshake"></i>
            </div>
            <div class="cl-stat-body">
                <div class="cl-stat-label">Clients B2B</div>
                <div class="cl-stat-value"><?= $nb_b2b ?></div>
            </div>
        </div>
        <div class="cl-stat">
            <div class="cl-stat-icon" style="background:var(--success-bg); color:var(--success);">
                <i class="fas fa-user-friends"></i>
            </div>
            <div class="cl-stat-body">
                <div class="cl-stat-label">Clients Directs</div>
                <div class="cl-stat-value"><?= $nb_directs ?></div>
            </div>
        </div>
        <div class="cl-stat">
            <div class="cl-stat-icon" style="background:var(--warning-bg); color:var(--warning);">
                <i class="fas fa-building"></i>
            </div>
            <div class="cl-stat-body">
                <div class="cl-stat-label">Volume B2B</div>
                <div class="cl-stat-value" style="font-size:1.1rem;"><?= number_format($total_b2b_volume, 0, ',', ' ') ?> F</div>
            </div>
        </div>
        <div class="cl-stat">
            <div class="cl-stat-icon" style="background:var(--info-bg); color:var(--info);">
                <i class="fas fa-store"></i>
            </div>
            <div class="cl-stat-body">
                <div class="cl-stat-label">Volume Direct</div>
                <div class="cl-stat-value" style="font-size:1.1rem;"><?= number_format($total_dir_volume, 0, ',', ' ') ?> F</div>
            </div>
        </div>
    </div>

    <!-- CLIENTS B2B -->
    <?php if (!empty($clients_b2b)): ?>
    <div class="card" style="padding:0; overflow:hidden; margin-bottom:24px;">
        <div class="cl-section-header">
            <div>
                <h2 style="margin:0; font-size:1.05rem; display:flex; align-items:center; gap:8px;">
                    <span style="width:28px; height:28px; border-radius:8px; background:var(--primary-light); color:var(--primary); display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;">
                        <i class="fas fa-handshake"></i>
                    </span>
                    Clients B2B — Entreprises
                </h2>
                <p style="margin:4px 0 0; font-size:0.8rem; color:var(--text-muted);"><?= $nb_b2b ?> partenaires</p>
            </div>
            <label class="cl-search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchB2B" placeholder="Rechercher une entreprise..." onkeyup="filterB2B()">
            </label>
        </div>
        <div class="table-responsive">
            <table class="table" id="tableB2B">
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th class="text-center">Commandes</th>
                        <th class="text-right">Volume d'achat</th>
                        <th class="text-right">Dernière commande</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients_b2b as $i => $c):
                        $flat_colors = ['#4f6ef7', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'];
                        $color = $flat_colors[$i % count($flat_colors)];
                        $initiales = strtoupper(substr($c['Nom_Client'], 0, 2));
                    ?>
                    <tr style="animation: fadeInUp 0.3s <?= $i * 30 ?>ms both;">
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div class="cl-row-avatar" style="background:<?= $color ?>;">
                                    <?= htmlspecialchars($initiales) ?>
                                </div>
                                <div>
                                    <div style="font-weight:700; font-size:0.9rem;"><?= htmlspecialchars($c['Nom_Client'] ?? '') ?></div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);">Client B2B</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-primary"><?= $c['Nb_Commandes'] ?> cmd</span>
                        </td>
                        <td class="text-right">
                            <span class="cl-volume"><?= number_format($c['Total_Depense'], 0, ',', ' ') ?> F</span>
                        </td>
                        <td class="text-right" style="color:var(--text-muted); font-size:0.875rem;">
                            <?= !empty($c['Derniere_Commande']) ? date('d/m/Y', strtotime($c['Derniere_Commande'])) : '—' ?>
                        </td>
                        <td class="text-center">
                            <a href="client_history.php?type=b2b&id=<?= $c['Id_Entreprise'] ?? 0 ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-history"></i> Historique
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- CLIENTS DIRECTS -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div class="cl-section-header">
            <div>
                <h2 style="margin:0; font-size:1.05rem; display:flex; align-items:center; gap:8px;">
                    <span style="width:28px; height:28px; border-radius:8px; background:var(--success-bg); color:var(--success); display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem;">
                        <i class="fas fa-user"></i>
                    </span>
                    Clients Directs
                </h2>
                <p style="margin:4px 0 0; font-size:0.8rem; color:var(--text-muted);"><?= $total_directs ?> clients au total</p>
            </div>
            <label class="cl-search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchDirect" placeholder="Rechercher un client..." onkeyup="filterDirect()">
            </label>
        </div>

        <?php if (!empty($clients_directs)): ?>
        <div class="table-responsive">
            <table class="table" id="tableDirect">
                <thead>
                    <tr>
                        <th>Nom Client</th>
                        <th class="text-center">Ventes</th>
                        <th class="text-right">Volume d'achat</th>
                        <th class="text-right">Dernier achat</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients_directs as $i => $c):
                        $initiales = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $c['Nom_Client']), 0, 2)));
                        $hue = crc32($c['Nom_Client']) % 360;
                    ?>
                    <tr style="animation: fadeInUp 0.3s <?= $i * 25 ?>ms both;">
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div class="cl-row-avatar" style="background: hsl(<?= abs($hue) ?>, 60%, 50%);">
                                    <?= htmlspecialchars($initiales ?: strtoupper(substr($c['Nom_Client'], 0, 2))) ?>
                                </div>
                                <div>
                                    <div style="font-weight:700; font-size:0.9rem;"><?= htmlspecialchars($c['Nom_Client'] ?? '') ?></div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);">Client direct</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-secondary"><?= $c['Nb_Commandes'] ?> vente<?= $c['Nb_Commandes'] > 1 ? 's' : '' ?></span>
                        </td>
                        <td class="text-right">
                            <span class="cl-volume"><?= number_format($c['Total_Depense'], 0, ',', ' ') ?> F</span>
                        </td>
                        <td class="text-right" style="color:var(--text-muted); font-size:0.875rem;">
                            <?= !empty($c['Derniere_Commande']) ? date('d/m/Y', strtotime($c['Derniere_Commande'])) : '—' ?>
                        </td>
                        <td class="text-center">
                            <a href="client_history.php?type=direct&name=<?= urlencode($c['Nom_Client'] ?? '') ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-history"></i> Historique
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages_directs > 1): ?>
        <div style="padding: 16px 24px; border-top: 1px solid var(--border);">
            <nav class="pagination">
                <?php if ($page_direct > 1): ?>
                    <a href="?page=<?= $page_direct - 1 ?>"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $pages_directs; $p++): ?>
                    <?php if ($p === $page_direct): ?>
                        <span class="active"><?= $p ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $p ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page_direct < $pages_directs): ?>
                    <a href="?page=<?= $page_direct + 1 ?>"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </nav>
            <p style="text-align:center; color:var(--text-muted); font-size:0.8rem; margin-top:10px;">
                <?= $total_directs ?> clients directs au total
            </p>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="text-align:center; padding:48px 24px; color:var(--text-muted);">
            <div style="width:60px;height:60px;border-radius:16px;background:var(--zinc-100);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:var(--zinc-400);margin:0 auto 16px;animation:float 3s ease-in-out infinite;">
                <i class="fas fa-users"></i>
            </div>
            <p style="font-size:0.9rem;">Aucun client direct enregistré.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterB2B() {
    var filter = document.getElementById('searchB2B').value.toUpperCase();
    document.querySelectorAll('#tableB2B tbody tr').forEach(function(tr) {
        var txt = tr.querySelector('td')?.textContent || '';
        tr.style.display = txt.toUpperCase().includes(filter) ? '' : 'none';
    });
}

function filterDirect() {
    var filter = document.getElementById('searchDirect').value.toUpperCase();
    document.querySelectorAll('#tableDirect tbody tr').forEach(function(tr) {
        var txt = tr.querySelector('td')?.textContent || '';
        tr.style.display = txt.toUpperCase().includes(filter) ? '' : 'none';
    });
}
</script>

</body>
</html>
