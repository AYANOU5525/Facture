<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'Suivi Logistique';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// On récupère les entrées logistiques
$stmt = $pdo->prepare("
    SELECT l.*, v.Nom_Client, v.Numero_Vente
    FROM Logistique l
    LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
    WHERE l.Id_Entreprise = ? 
    ORDER BY l.Id_Logistique DESC
");
$stmt->execute([$entreprise_id]);
$logistique = $stmt->fetchAll();
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-truck-loading"></i> Suivi Logistique</h1>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px; font-size: 1.5rem;">Expéditions & Livraisons</h2>

        <?php if (count($logistique) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Réf. Vente</th>
                        <th>Client</th>
                        <th>Transporteur</th>
                        <th>N° Suivi</th>
                        <th>Statut</th>
                        <th>Livraison Prévue</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($logistique as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l['Numero_Vente'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($l['Nom_Client'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($l['Transporteur'] ?? 'Non assigné') ?></td>
                            <td><code><?= htmlspecialchars($l['Numero_Suivi'] ?? '-') ?></code></td>
                            <td>
                                <span class="badge badge-<?= $l['Statut_Livraison'] === 'livree' ? 'success' : ($l['Statut_Livraison'] === 'expediee' ? 'info' : 'secondary') ?>">
                                    <?= strtoupper(str_replace('_', ' ', $l['Statut_Livraison'])) ?>
                                </span>
                            </td>
                            <td><?= $l['Date_Livraison_Prevue'] ? date('d/m/Y', strtotime($l['Date_Livraison_Prevue'])) : 'TBD' ?></td>
                            <td>
                                <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                Aucune expédition en cours.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>

</html>
