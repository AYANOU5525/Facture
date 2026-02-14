<?php
require_once '../includes/auth.php'; // Pour vérifier la connexion
require_once '../config/db.php';

if (!isset($_GET['ref'])) {
    die("Référence Facture manquante.");
}

$ref_vente = $_GET['ref'];
$user_id = $_SESSION['user_id'];
$my_entreprise_id = $_SESSION['entreprise_id'] ?? null;

// Si session entreprise_id pas dispo, on le cherche
if (!$my_entreprise_id) {
    $stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
    $stmt->execute([$user_id]);
    $my_entreprise_id = $stmt->fetchColumn();
}

// 1. Récupérer la vente par Numero_Vente
$stmt = $pdo->prepare("
    SELECT v.*, e.Nom_Entreprise, e.Adresse_Entreprise, e.Tel_Entreprise, e.Email_Entreprise, e.NIF_Entreprise
    FROM Vente v
    JOIN Entreprise e ON v.Id_Entreprise = e.Id_Entreprise
    WHERE v.Numero_Vente = ? AND v.Id_Entreprise = ?
");
$stmt->execute([$ref_vente, $my_entreprise_id]);
$vente = $stmt->fetch();

if (!$vente) {
    die("Facture introuvable ou accès refusé.");
}

$articles = json_decode($vente['Articles_JSON'], true);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Facture <?= htmlspecialchars($vente['Numero_Vente']) ?></title>
    <!-- Utilisation de la même police pour cohérence, mais style print spécifique -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
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

        .btn-print {
            background: #333;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            text-decoration: none;
            border-radius: 5px;
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

    <div class="no-print">
        <button onclick="window.print()" class="btn-print">Imprimer / PDF</button>
        <button onclick="window.close()" class="btn-print" style="background: #ccc; color: #000;">Fermer</button>
    </div>

    <div class="invoice-box">
        <div class="header">
            <div class="company-info">
                <h1><?= htmlspecialchars($vente['Nom_Entreprise']) ?></h1>
                <p><?= nl2br(htmlspecialchars($vente['Adresse_Entreprise'])) ?></p>
                <p>Tel: <?= htmlspecialchars($vente['Tel_Entreprise']) ?></p>
                <p>Email: <?= htmlspecialchars($vente['Email_Entreprise']) ?></p>
                <?php if ($vente['NIF_Entreprise']): ?>
                    <p>NIF: <?= htmlspecialchars($vente['NIF_Entreprise']) ?></p>
                <?php endif; ?>
            </div>

            <div class="invoice-details">
                <h2>FACTURE</h2>
                <p>N° <?= htmlspecialchars($vente['Numero_Vente']) ?></p>
                <p>Date : <?= date('d/m/Y', strtotime($vente['Date_Vente'])) ?></p>
            </div>
        </div>

        <div class="client-info">
            <h3>Facturé à :</h3>
            <div class="client-name"><?= htmlspecialchars($vente['Nom_Client']) ?></div>
            <?php if (!empty($vente['Nom_Vendeur'])): ?>
                <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                    <strong>Vendeur:</strong> <?= htmlspecialchars($vente['Nom_Vendeur']) ?>
                </div>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th style="text-align: center;">Qté</th>
                    <th style="text-align: right;">P.U.</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articles as $art): ?>
                    <tr>
                        <td><?= htmlspecialchars($art['nom']) ?></td>
                        <td style="text-align: center;"><?= $art['quantite'] ?></td>
                        <td style="text-align: right;"><?= number_format($art['prix'], 0, ',', ' ') ?></td>
                        <td style="text-align: right;"><?= number_format($art['total'] ?? ($art['quantite'] * $art['prix']), 0, ',', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">TOTAL NET À PAYER</td>
                    <td style="text-align: right;"><?= number_format($vente['Montant_Total'], 0, ',', ' ') ?> FCFA</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p>Merci de votre confiance.</p>
            <p>Facture générée numériquement via FactuPro le <?= date('d/m/Y à H:i') ?></p>
        </div>
    </div>

</body>

</html>
