<?php
require_once 'auth.php';
require_once 'db.php';

$page_title = 'Historique des Ventes';
include 'header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM Vente WHERE Id_Entreprise = ? ORDER BY Date_Vente DESC");
$stmt->execute([$entreprise_id]);
$ventes = $stmt->fetchAll();
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Historique des Ventes</h1>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 1.5rem;">Transactions</h2>
            <a href="invoice_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouvelle Vente
            </a>
        </div>

        <?php if (count($ventes) > 0): ?>
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
                        <tr>
                            <td><strong><?= htmlspecialchars($v['Numero_Vente']) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($v['Date_Vente'])) ?></td>
                            <td><?= htmlspecialchars($v['Nom_Client']) ?></td>
                            <td>
                                <?php $articles = json_decode($v['Articles_JSON'], true); ?>
                                <small style="display: block; color: var(--text-muted);">
                                    <?php if ($articles): ?>
                                        <?= count($articles) ?> article(s) :
                                        <?php foreach (array_slice($articles, 0, 2) as $art): ?>
                                            <?= htmlspecialchars($art['nom']) ?>,
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
                                <?= number_format($v['Montant_Total'], 0, ',', ' ') ?> F
                            </td>
                            <td>
                                <a href="invoice_view.php?id=<?= $v['Id_Vente'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 5px 10px; font-size: 0.85em;">
                                    <i class="fas fa-print"></i> Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                Aucune vente enregistrée. <a href="invoice_add.php">Commencez ici</a>.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>

</html>