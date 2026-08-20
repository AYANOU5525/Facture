<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'Clients Uniques';
include '../includes/header.php';

$entreprise_id = $_SESSION['entreprise_id'];

// Pagination des clients directs
$per_page    = 20;
$page_direct = max(1, (int) ($_GET['page'] ?? 1));
$offset_d    = ($page_direct - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT Nom_Client) FROM Vente WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$total_directs   = (int) $stmt->fetchColumn();
$pages_directs   = (int) ceil($total_directs / $per_page);

// 1. Récupérer les clients comptoirs (Vente)
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

// 2. Récupérer les clients B2B (Entreprises acheteuses)
$stmt = $pdo->prepare("
    SELECT 
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

// Fusionner pour l'affichage si désiré, ou afficher deux tables
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Clients Uniques</h1>
        <p>Retrouvez ici la liste de vos clients et leur volume d'achat.</p>
    </div>

    <!-- CLIENTS B2B -->
    <?php if (!empty($clients_b2b)): ?>
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;"><i class="fas fa-building text-primary"></i> Clients B2B (Entreprises)</h2>
                <input type="text" 
                       id="searchB2B" 
                       class="form-control" 
                       style="width: 250px;" 
                       placeholder="🔍 Rechercher..." 
                       onkeyup="filterB2B()">
            </div>
            <div class="scrollable-list">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Entreprise</th>
                            <th class="text-center">Commandes</th>
                            <th class="text-right">Volume Achat</th>
                            <th class="text-right">Dernière commande</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients_b2b as $c): ?>
                            <tr>
                                <td>
                                    <div class="font-weight-bold"><?= htmlspecialchars($c['Nom_Client'] ?? '') ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?= $c['Nb_Commandes'] ?></span>
                                </td>
                                <td class="text-right font-weight-bold text-success">
                                    <?= number_format($c['Total_Depense'], 0, ',', ' ') ?> F
                                </td>
                                <td class="text-right text-muted">
                                    <?= !empty($c['Derniere_Commande']) ? date('d/m/Y', strtotime($c['Derniere_Commande'])) : '-' ?>
                                </td>
                                <td class="text-center">
                                    <a href="client_history.php?type=b2b&id=<?= $c['Id_Entreprise'] ?? 0 ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Voir l'historique d'achat">
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
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;"><i class="fas fa-user-friends text-info"></i> Clients Directs</h2>
            <input type="text" 
                   id="searchDirect" 
                   class="form-control" 
                   style="width: 250px;" 
                   placeholder="🔍 Rechercher..." 
                   onkeyup="filterDirect()">
        </div>
        <?php if (!empty($clients_directs)): ?>
            <div class="scrollable-list">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nom Client</th>
                            <th class="text-center">Ventes</th>
                            <th class="text-right">Volume Achat</th>
                            <th class="text-right">Dernier Achat</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients_directs as $c): ?>
                            <tr>
                                <td>
                                    <div class="font-weight-bold"><?= htmlspecialchars($c['Nom_Client'] ?? '') ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary"><?= $c['Nb_Commandes'] ?></span>
                                </td>
                                <td class="text-right font-weight-bold text-success">
                                    <?= number_format($c['Total_Depense'], 0, ',', ' ') ?> F
                                </td>
                                <td class="text-right text-muted">
                                    <?= !empty($c['Derniere_Commande']) ? date('d/m/Y', strtotime($c['Derniere_Commande'])) : '-' ?>
                                </td>
                                <td class="text-center">
                                    <a href="client_history.php?type=direct&name=<?= urlencode($c['Nom_Client'] ?? '') ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Voir l'historique d'achat">
                                        <i class="fas fa-history"></i> Historique
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">Aucun client direct enregistré.</p>
        <?php endif; ?>

        <?php if ($pages_directs > 1): ?>
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
        <p style="text-align:center; color:var(--text-muted); font-size:0.875rem; margin-top:8px;">
            <?= $total_directs ?> clients directs au total
        </p>
        <?php endif; ?>
    </div>
</div>

<script>
    function filterTable(inputId, tableSelectorOrIndex) {
        var input = document.getElementById(inputId);
        var filter = input.value.toUpperCase();
        var table;
        if (typeof tableSelectorOrIndex === 'string') {
            table = document.querySelector(tableSelectorOrIndex);
        } else {
            var tables = document.querySelectorAll(".card .table");
            table = tables[tableSelectorOrIndex];
        }
        if (!table) return;
        var tr = table.getElementsByTagName("tr");

        for (var i = 1; i < tr.length; i++) {
            var td = tr[i].getElementsByTagName("td")[0];
            if (td) {
                var txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    function filterB2B() {
        filterTable("searchB2B", ".card:first-of-type .table");
    }

    function filterDirect() {
        var tables = document.querySelectorAll(".card .table");
        filterTable("searchDirect", tables.length - 1);
    }
</script>

</body>

</html>
