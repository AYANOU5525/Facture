<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Redirection si paramètres manquants
if (!isset($_GET['type']) || (!isset($_GET['id']) && !isset($_GET['name']))) {
    header('Location: clients.php');
    exit();
}

$type = $_GET['type'];
$entreprise_id = $_SESSION['entreprise_id'];
$client_name = '';
$history = [];

if ($type === 'b2b' && isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    
    // Récupérer le nom de l'entreprise cliente
    $stmt = $pdo->prepare("SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
    $stmt->execute([$target_id]);
    $client_name = $stmt->fetchColumn() ?: 'Entreprise inconnue';

    // Historique B2B
    $stmt = $pdo->prepare("
        SELECT 
            'Commande B2B' as Type_Doc,
            Numero_Commande as Reference,
            Date_Commande as Date_Doc,
            Montant_Total,
            Statut as Etat
        FROM Commande_B2B 
        WHERE Id_Entreprise_Vendeuse = ? AND Id_Entreprise_Acheteuse = ?
        ORDER BY Date_Commande DESC
    ");
    $stmt->execute([$entreprise_id, $target_id]);
    $history = $stmt->fetchAll();

} elseif ($type === 'direct' && isset($_GET['name'])) {
    $client_name = $_GET['name'];

    // Historique Direct
    $stmt = $pdo->prepare("
        SELECT 
            'Vente Directe' as Type_Doc,
            Numero_Vente as Reference,
            Date_Vente as Date_Doc,
            Montant_Total,
            'Validée' as Etat
        FROM Vente 
        WHERE Id_Entreprise = ? AND Nom_Client = ?
        ORDER BY Date_Vente DESC
    ");
    $stmt->execute([$entreprise_id, $client_name]);
    $history = $stmt->fetchAll();
}

$page_title = "Historique Client : " . htmlspecialchars($client_name ?? '');
include '../includes/header.php';
?>

<div class="container fade-in">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1><i class="fas fa-history"></i> Historique d'achat</h1>
            <p>Détails des transactions pour <strong><?= htmlspecialchars($client_name ?? '') ?></strong></p>
        </div>
        <a href="clients.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour aux clients</a>
    </div>

    <div class="card">
        <?php if (empty($history)): ?>
            <div class="alert alert-info">Aucune transaction trouvée pour ce client.</div>
        <?php else: ?>
            <div class="scrollable-list">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Référence</th>
                            <th>Type</th>
                            <th class="text-right">Montant</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($item['Date_Doc'])) ?></td>
                                <td><strong><?= htmlspecialchars($item['Reference'] ?? '') ?></strong></td>
                                <td><span class="text-muted small"><?= $item['Type_Doc'] ?></span></td>
                                <td class="text-right font-weight-bold"><?= number_format($item['Montant_Total'], 0, ',', ' ') ?> F</td>
                                <td class="text-center">
                                    <?php 
                                        $etat = $item['Etat'] ?? 'N/A';
                                        $badge = 'secondary';
                                        if (in_array($etat, ['livree', 'Validée'])) $badge = 'success';
                                        if ($etat === 'annulee') $badge = 'danger';
                                        if ($etat === 'expediee') $badge = 'info';
                                    ?>
                                    <span class="badge badge-<?= $badge ?>"><?= strtoupper($etat) ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="invoice_view.php?ref=<?= urlencode($item['Reference']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Voir Facture">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
