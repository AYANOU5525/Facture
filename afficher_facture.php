<?php
require_once 'auth.php';
require_once 'bdd.php';

$invoice = null;
$error = '';
$invoice_id_param = $_GET['id'] ?? '';

if (empty($invoice_id_param)) {
    $error = 'ID de facture manquant.';
} else {
    $stmt = $pdo->prepare('SELECT f.*, e.nom as nom_entreprise, e.adresse, e.telephone, e.email_contact FROM factures f JOIN entreprises e ON f.entreprise_id = e.id WHERE f.id_facture = :id_facture AND f.entreprise_id = :ent_id');
    $stmt->execute([
        'id_facture' => $invoice_id_param,
        'ent_id' => $_SESSION['entreprise_id']
    ]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {
        $invoice['articles'] = json_decode($invoice['articles'], true) ?: [];
    } else {
        $error = 'Facture introuvable.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?= htmlspecialchars($invoice_id_param) ?> | FactuPro</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .invoice-paper {
            background: white;
            padding: 3rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            max-width: 850px;
            margin: 0 auto;
            border: 1px solid var(--gray-200);
        }

        .invoice-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3rem;
            border-bottom: 2px solid var(--gray-100);
            padding-bottom: 2rem;
        }

        .company-info h2 {
            margin: 0;
            color: var(--primary);
            font-size: 2rem;
        }

        .company-info p {
            margin: 0.25rem 0;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-meta h1 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--dark);
            text-transform: uppercase;
        }

        .invoice-meta p {
            margin: 0.25rem 0;
            color: var(--gray-600);
        }

        .client-info {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            width: fit-content;
            min-width: 300px;
        }

        @media print {
            .app-container {
                display: block;
            }

            .sidebar,
            .print-hide,
            .main-content header {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .invoice-paper {
                box-shadow: none;
                border: none;
                padding: 0;
            }

            body {
                background: white;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header class="print-hide" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 700;">Détails de la Facture</h1>
                    <p style="color: var(--gray-600);">Visualisez ou imprimez cette facture</p>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <a href="liste_factures.php" class="btn" style="background: var(--gray-200); color: var(--gray-700);">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="card bg-danger-light" style="border: none; color: #dc2626; padding: 1rem;">
                    <i class="fas fa-circle-exclamation"></i> <?= $error ?>
                </div>
            <?php elseif ($invoice): ?>
                <div class="invoice-paper">
                    <div class="invoice-top">
                        <div class="company-info">
                            <h2><?= htmlspecialchars($invoice['nom_entreprise']) ?></h2>
                            <p><i class="fas fa-location-dot"></i> <?= htmlspecialchars($invoice['adresse'] ?: 'Adresse non renseignée') ?></p>
                            <p><i class="fas fa-phone"></i> <?= htmlspecialchars($invoice['telephone'] ?: 'Téléphone non renseigné') ?></p>
                            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($invoice['email_contact'] ?: 'Email non renseigné') ?></p>
                        </div>
                        <div class="invoice-meta">
                            <h1>Facture</h1>
                            <p><strong>N° :</strong> <?= htmlspecialchars($invoice['id_facture']) ?></p>
                            <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($invoice['date'])) ?></p>
                        </div>
                    </div>

                    <div class="client-info">
                        <div style="text-transform: uppercase; font-size: 0.75rem; font-weight: 700; color: var(--gray-500); margin-bottom: 0.5rem;">Facturé à :</div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--dark);"><?= htmlspecialchars($invoice['nom_client']) ?></div>
                        <div style="color: var(--gray-600);"><?= htmlspecialchars($invoice['email_client'] ?: '') ?></div>
                    </div>

                    <table style="margin-bottom: 3rem;">
                        <thead>
                            <tr>
                                <th style="background: var(--dark); color: white;">Description</th>
                                <th style="background: var(--dark); color: white; text-align: center;">Qté</th>
                                <th style="background: var(--dark); color: white; text-align: right;">Prix Unitaire</th>
                                <th style="background: var(--dark); color: white; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice['articles'] as $item): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($item['description']) ?></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td style="text-align: right;"><?= number_format($item['price'], 0, ',', ' ') ?> F</td>
                                    <td style="text-align: right; font-weight: 600;"><?= number_format($item['quantity'] * $item['price'], 0, ',', ' ') ?> F</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="display: flex; justify-content: flex-end;">
                        <div style="width: 250px;">
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-600);">Sous-total</span>
                                <span style="font-weight: 600;"><?= number_format($invoice['montant_total'], 0, ',', ' ') ?> FCFA</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 1rem 0; font-size: 1.25rem; font-weight: 800; color: var(--primary);">
                                <span>TOTAL</span>
                                <span><?= number_format($invoice['montant_total'], 0, ',', ' ') ?> FCFA</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 4rem; border-top: 1px solid var(--gray-200); padding-top: 1rem; text-align: center; color: var(--gray-500); font-size: 0.75rem;">
                        Merci de votre confiance ! Cette facture est générée par FactuPro.
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>