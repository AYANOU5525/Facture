<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(ROLE_PROPRIO, ROLE_VENDEUR);

$page_title = 'Historique des Ventes';
include '../includes/header.php';

$entreprise_id = $_SESSION['entreprise_id'];

// Pagination
$per_page = 25;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// Total pour la pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Vente WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$total_ventes = (int) $stmt->fetchColumn();
$total_pages  = (int) ceil($total_ventes / $per_page);

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

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Historique des Ventes</h1>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <h2 style="margin: 0; font-size: 1.5rem;">Transactions</h2>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="date" id="dateDebut" class="form-control" style="width: 150px;" onchange="filterSales()">
                <span style="color: #666;">au</span>
                <input type="date" id="dateFin" class="form-control" style="width: 150px;" onchange="filterSales()">
                <input type="text" id="searchClient" class="form-control" style="width: 180px;" placeholder="🔍 Client..." onkeyup="filterSales()">
                <a href="invoice_add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouvelle Vente
                </a>
            </div>
        </div>

        <?php if (count($ventes) > 0): ?>
            <div class="scrollable-list">
                <table class="table">
                    <thead>
                        <tr>
                            <th>N° Vente</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Détails</th>
                            <th>Type</th>
                            <th>Total</th>
                            <th>Facture</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventes as $v): ?>
                            <tr data-date="<?= $v['Date_Vente'] ?>" data-client="<?= htmlspecialchars($v['Nom_Client'] ?? '') ?>">
                                <td><strong><?= htmlspecialchars($v['Numero_Vente'] ?? '-') ?></strong></td>
                                <td><?= !empty($v['Date_Vente']) ? date('d/m/Y H:i', strtotime($v['Date_Vente'])) : '-' ?></td>
                                <td><?= htmlspecialchars($v['Nom_Client'] ?? '-') ?></td>
                                <td>
                                    <?php $articles = json_decode($v['Articles_JSON'], true); ?>
                                    <small style="display: block; color: var(--text-muted);">
                                        <?php if ($articles): ?>
                                            <?= count($articles) ?> article(s) :
                                            <?php foreach (array_slice($articles, 0, 2) as $art): ?>
                                                <?= htmlspecialchars($art['nom'] ?? '') ?>,
                                            <?php endforeach; ?>
                                            <?php if (count($articles) > 2) echo '...'; ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $v['Type_Vente'] === 'b2b' ? 'info' : 'secondary' ?>">
                                        <?= strtoupper($v['Type_Vente']) ?>
                                    </span>
                                </td>
                                <td style="font-weight: bold; color: var(--success);">
                                    <?= number_format((float)($v['Montant_Total'] ?? 0), 0, ',', ' ') ?> F
                                </td>
                                <td>
                                    <a href="invoice_view.php?ref=<?= htmlspecialchars($v['Numero_Vente'] ?? '') ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 5px 10px; font-size: 0.85em;">
                                        <i class="fas fa-print"></i> Voir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Aucune vente enregistrée. <a href="invoice_add.php">Commencez ici</a>.
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $window = 2;
            $start  = max(1, $page - $window);
            $end    = min($total_pages, $page + $window);
            if ($start > 1):  ?><a href="?page=1">1</a><?php if ($start > 2): ?><span>…</span><?php endif; endif;
            for ($p = $start; $p <= $end; $p++):
                if ($p === $page): ?><span class="active"><?= $p ?></span><?php
                else: ?><a href="?page=<?= $p ?>"><?= $p ?></a><?php
                endif;
            endfor;
            if ($end < $total_pages): if ($end < $total_pages - 1): ?><span>…</span><?php endif; ?><a href="?page=<?= $total_pages ?>"><?= $total_pages ?></a><?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <span class="disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </nav>
        <p style="text-align:center; color:var(--text-muted); font-size:0.875rem; margin-top:8px;">
            Page <?= $page ?> / <?= $total_pages ?> — <?= number_format($total_ventes) ?> ventes au total
        </p>
        <?php endif; ?>
    </div>
</div>

<script>
    function filterSales() {
        var dateDebut = document.getElementById("dateDebut").value;
        var dateFin = document.getElementById("dateFin").value;
        var searchClient = document.getElementById("searchClient").value.toUpperCase();
        var table = document.querySelector(".table");
        if (!table) return;
        var tr = table.getElementsByTagName("tr");

        for (var i = 1; i < tr.length; i++) {
            var dateVente = tr[i].getAttribute("data-date");
            var nomClient = tr[i].getAttribute("data-client").toUpperCase();

            var matchDate = true;
            var matchClient = true;

            // Filter by date range
            if (dateDebut && dateVente) {
                var venteDate = dateVente.split(' ')[0]; // Extract date only (YYYY-MM-DD)
                if (venteDate < dateDebut) matchDate = false;
            }
            if (dateFin && dateVente) {
                var venteDate = dateVente.split(' ')[0];
                if (venteDate > dateFin) matchDate = false;
            }

            // Filter by client name
            if (searchClient !== "") {
                if (nomClient.indexOf(searchClient) === -1) matchClient = false;
            }

            if (matchDate && matchClient) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
</script>

</body>

</html>
