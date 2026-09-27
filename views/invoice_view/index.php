<?php
$vente = $vente ?? [];
$articles = $articles ?? [];
$label_conservation = $label_conservation ?? '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Facture <?= htmlspecialchars($vente['Numero_Vente'] ?? '') ?></title>
    <!-- Utilisation de la même police pour cohérence, mais style print spécifique -->
    <link rel="stylesheet" href="../assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #000;
            background: #fff;
            font-size: 14px;
            margin: 0;
            padding: 20px;
        }

        .invoice-box {
            max-width: 800px;
            margin: auto;
            border: 1px solid #eee;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
        }

        .company-info h1 {
            margin: 0;
            font-size: 24px;
            text-transform: uppercase;
            color: #333;
        }

        .company-info p {
            margin: 5px 0;
            color: #555;
        }

        .invoice-details {
            text-align: right;
        }

        .invoice-details h2 {
            margin: 0;
            color: #333;
        }

        .invoice-details p {
            margin: 5px 0;
        }

        .client-info {
            margin-bottom: 40px;
            border-top: 2px solid #333;
            padding-top: 20px;
        }

        .client-info h3 {
            margin: 0 0 10px 0;
            text-transform: uppercase;
            font-size: 12px;
            color: #777;
        }

        .client-name {
            font-size: 18px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th {
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #000;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .total-row td {
            border-top: 2px solid #000;
            border-bottom: none;
            font-weight: bold;
            font-size: 16px;
        }

        .footer {
            text-align: center;
            margin-top: 50px;
            font-size: 10px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .no-print {
            margin-bottom: 20px;
            text-align: right;
        }

        @media print {
            .no-print {
                display: none;
            }

            .invoice-box {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>

<body>

    <div class="no-print d-flex justify-content-end gap-2">
        <button onclick="window.print()" class="btn btn-dark">Imprimer / PDF</button>
        <button onclick="window.close()" class="btn btn-outline-secondary">Fermer</button>
    </div>

    <div class="invoice-box">
        <div class="header">
            <div class="company-info">
                <h1><?= htmlspecialchars($vente['Nom_Entreprise'] ?? '') ?></h1>
                <p><?= nl2br(htmlspecialchars($vente['Adresse_Entreprise'] ?? '')) ?></p>
                <p>Tel: <?= htmlspecialchars($vente['Tel_Entreprise'] ?? '') ?></p>
                <p>Email: <?= htmlspecialchars($vente['Email_Entreprise'] ?? '') ?></p>
                <?php if (!empty($vente['NIF_Entreprise'] ?? null)): ?>
                    <p>NIF: <?= htmlspecialchars($vente['NIF_Entreprise'] ?? '') ?></p>
                <?php endif; ?>
            </div>

            <div class="invoice-details">
                <h2>FACTURE</h2>
                <p>N° <?= htmlspecialchars($vente['Numero_Vente'] ?? '') ?></p>
                <p>Date : <?= date('d/m/Y', strtotime($vente['Date_Vente'] ?? 'now')) ?></p>
            </div>
        </div>

        <div class="client-info">
            <h3>Facturé à :</h3>
            <div class="client-name"><?= htmlspecialchars($vente['Nom_Client'] ?? '') ?></div>
            <?php if (!empty($vente['Nom_Vendeur'] ?? null)): ?>
                <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                    <strong>Vendeur:</strong> <?= htmlspecialchars($vente['Nom_Vendeur'] ?? '') ?>
                </div>
            <?php endif; ?>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th style="text-align: center;">Qté</th>
                    <th style="text-align: right;">P.U.</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($articles ?? []) as $art):
                    // Articles_JSON a eu plusieurs formats historiques (seed, ancien facteur_conversion,
                    // nouveau format carton+unité) — on reste tolérant aux trois pour l'affichage.
                    $qte_carton = (int) ($art['quantite_carton'] ?? 0);
                    $qte_unite  = $art['quantite_unite'] ?? null;
                    $qte_totale = $art['quantite_unites'] ?? $art['quantite'] ?? 1;
                    $prix_u     = $art['prix_unitaire'] ?? $art['prix'] ?? 0;
                    $total_l    = $art['total'] ?? $art['sous_total'] ?? ($qte_totale * $prix_u);

                    $qte_label = $qte_totale . ($qte_totale > 1 ? ' unités' : ' unité');
                    if ($qte_carton > 0) {
                        $detail = [$qte_carton . ' carton(s)'];
                        if (!empty($qte_unite)) {
                            $detail[] = $qte_unite . ' unité(s)';
                        }
                        $qte_label = implode(' + ', $detail) . ' = ' . $qte_totale . ' u.';
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($art['nom']) ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars((string) $qte_label) ?></td>
                        <td style="text-align: right;"><?= number_format((float) $prix_u, 0, ',', ' ') ?></td>
                        <td style="text-align: right;"><?= number_format((float) $total_l, 0, ',', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">TOTAL NET À PAYER</td>
                    <td style="text-align: right;"><?= number_format((float) ($vente['Montant_Total'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p>Merci de votre confiance.</p>
            <p>Facture générée numériquement via FactuPro le <?= date('d/m/Y à H:i') ?></p>
            <p style="margin-top: 10px; border-top: 1px solid #ddd; padding-top: 10px; font-style: italic;">
                🔒 Ce document comptable est conservé conformément aux obligations légales —
                durée minimale : <strong>10 ans</strong> — jusqu'au <strong><?= $label_conservation ?></strong>.
            </p>
        </div>

        <div class="no-print text-center mt-4">
            <button onclick="window.print()" class="btn btn-primary btn-lg">
                🖨 Imprimer / Exporter PDF
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary btn-lg ms-2">
                Fermer
            </button>
        </div>
    </div>

<?php if (!empty($_GET['autoprint'])): ?>
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 500);
    });
</script>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
}
</style>

</body>

</html>
