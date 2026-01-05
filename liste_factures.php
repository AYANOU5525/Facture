<?php
require_once 'auth.php';
require_once 'bdd.php';

$stmt = $pdo->prepare('SELECT id_facture, nom_client, email_client, date, montant_total FROM factures WHERE entreprise_id = :ent_id ORDER BY date DESC');
$stmt->execute(['ent_id' => $_SESSION['entreprise_id']]);
$all_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures | FactuPro</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 700;">Historique des Factures</h1>
                    <p style="color: var(--gray-600);">Suivez toutes les factures émises par votre entreprise</p>
                </div>
                <a href="creer_facture.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Créer une Facture
                </a>
            </header>

            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th># ID Facture</th>
                                <th>Client</th>
                                <th>Date d'émission</th>
                                <th>Montant Total</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_invoices)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 3rem; color: var(--gray-500);">Aucune facture générée pour le moment.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_invoices as $inv): ?>
                                    <tr>
                                        <td>
                                            <span style="font-family: monospace; font-weight: 600; color: var(--primary);"><?= htmlspecialchars($inv['id_facture']) ?></span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($inv['nom_client']) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($inv['email_client']) ?: 'Pas d\'email' ?></div>
                                        </td>
                                        <td style="color: var(--gray-600);">
                                            <i class="far fa-calendar-alt" style="margin-right: 0.5rem;"></i>
                                            <?= date('d M Y, H:i', strtotime($inv['date'])) ?>
                                        </td>
                                        <td style="font-weight: 700; font-size: 1.05rem;">
                                            <?= number_format($inv['montant_total'], 0, ',', ' ') ?> FCFA
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="afficher_facture.php?id=<?= urlencode($inv['id_facture']) ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                <i class="fas fa-eye"></i> Détails
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>