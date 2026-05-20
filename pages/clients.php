<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'Clients Uniques';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// 1. Récupérer les clients comptoirs (Vente)
// Group by Nom_Client to get unique clients and their stats
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
");
$stmt->execute([$entreprise_id]);
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
                <input type="text" id="searchB2B" class="form-control" style="width: 250px;" placeholder="🔍 Rechercher..." onkeyup="filterB2B()">
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
                                <td class="text-center"><span class="badge badge-info"><?= $c['Nb_Commandes'] ?></span></td>
                                <td class="text-right font-weight-bold text-success"><?= number_format($c['Total_Depense'], 0, ',', ' ') ?> F</td>
                                <td class="text-right text-muted"><?= date('d/m/Y', strtotime($c['Derniere_Commande'])) ?></td>
                                <td class="text-center">
                                    <a href="client_history.php?type=b2b&id=<?= $c['Id_Entreprise'] ?? 0 ?>" class="btn btn-sm btn-outline-primary" title="Voir l'historique d'achat">
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
            <input type="text" id="searchDirect" class="form-control" style="width: 250px;" placeholder="🔍 Rechercher..." onkeyup="filterDirect()">
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
                                <td class="text-center"><span class="badge badge-secondary"><?= $c['Nb_Commandes'] ?></span></td>
                                <td class="text-right font-weight-bold text-success"><?= number_format($c['Total_Depense'], 0, ',', ' ') ?> F</td>
                                <td class="text-right text-muted"><?= date('d/m/Y', strtotime($c['Derniere_Commande'])) ?></td>
                                <td class="text-center">
                                    <a href="client_history.php?type=direct&name=<?= urlencode($c['Nom_Client'] ?? '') ?>" class="btn btn-sm btn-outline-primary" title="Voir l'historique d'achat">
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
    </div>
</div>

<script>
    function filterB2B() {
        var input = document.getElementById("searchB2B");
        var filter = input.value.toUpperCase();
        var table = document.querySelector(".card:first-of-type .table");
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

    function filterDirect() {
        var input = document.getElementById("searchDirect");
        var filter = input.value.toUpperCase();
        var tables = document.querySelectorAll(".card .table");
        var table = tables[tables.length - 1]; // Last table = Direct clients
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
</script>

</body>

</html>
