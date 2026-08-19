<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'Suivi des Factures';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

$success = '';
$error = '';

// === TRAITEMENT DU CHANGEMENT DE STATUT ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    requireCsrf();
    $id_facture = $_POST['id_facture'];
    $nouveau_statut = $_POST['nouveau_statut'];

    // Vérifier que la facture appartient à l'entreprise
    $stmt = $pdo->prepare("SELECT Id_Facture, Date_Facture FROM Facture WHERE Id_Facture = ? AND Id_Entreprise = ?");
    $stmt->execute([$id_facture, $entreprise_id]);
    $facture_a_modifier = $stmt->fetch();

    if ($facture_a_modifier) {
        // PROTECTION LÉGALE : impossible d'annuler une facture encore sous période de conservation (10 ans)
        $date_conservation = new DateTime($facture_a_modifier['Date_Facture']);
        $date_conservation->modify('+10 years');
        $en_retention = $date_conservation > new DateTime();

        if ($nouveau_statut === 'annulee' && $en_retention) {
            $annee_archivage = $date_conservation->format('d/m/Y');
            $error = "❌ Impossible d'annuler cette facture : elle doit être conservée jusqu'au <strong>$annee_archivage</strong> (obligation légale de 10 ans).";
        } else {
            $stmt = $pdo->prepare("UPDATE Facture SET Statut_Paiement = ? WHERE Id_Facture = ?");
            $stmt->execute([$nouveau_statut, $id_facture]);
            $success = "Statut mis à jour avec succès.";
        }
    } else {
        $error = "Facture introuvable.";
    }
}

// On récupère les factures liées à l'entreprise
// Date_Archivage = Date_Facture + 10 ans (obligation légale de conservation)
$stmt = $pdo->prepare("
    SELECT f.*, v.Nom_Client, v.Numero_Vente,
           DATE_ADD(f.Date_Facture, INTERVAL 10 YEAR) AS Date_Conservation,
           CASE WHEN DATE_ADD(f.Date_Facture, INTERVAL 10 YEAR) > NOW() THEN 1 ELSE 0 END AS En_Retention
    FROM Facture f
    LEFT JOIN Vente v ON f.Id_Vente = v.Id_Vente
    WHERE f.Id_Entreprise = ? 
    ORDER BY f.Date_Facture DESC
");
$stmt->execute([$entreprise_id]);
$factures = $stmt->fetchAll();
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Gestion des Factures</h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 1.5rem;">Détails financiers</h2>
            <div style="display: flex; gap: 10px;">
                <select id="filterStatut" class="form-control" style="width: 150px;" onchange="filterInvoices()">
                    <option value="">Tous les statuts</option>
                    <option value="payee">Payée</option>
                    <option value="non_payee">Non Payée</option>
                    <option value="annulee">Annulée</option>
                </select>
                <input type="text" id="searchInvoice" class="form-control" style="width: 200px;" placeholder="🔍 Rechercher..." onkeyup="filterInvoices()">
            </div>
        </div>

        <?php if (count($factures) > 0): ?>
            <div class="scrollable-list">
                <table class="table">
                    <thead>
                        <tr>
                            <th>N° Facture</th>
                            <th>Client</th>
                            <th>Échéance</th>
                            <th>Statut</th>
                            <th>Montant TTC</th>
                            <th title="Obligation légale de conservation : 10 ans minimum">🔒 Conservée jusqu'au</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($factures as $f): ?>
                            <?php
                                $date_conservation = new DateTime($f['Date_Facture']);
                                $date_conservation->modify('+10 years');
                                $en_retention = $date_conservation > new DateTime();
                                $label_conservation = $date_conservation->format('d/m/Y');
                                // Années restantes
                                $diff = (new DateTime())->diff($date_conservation);
                                $annees_restantes = $en_retention ? $diff->y : 0;
                            ?>
                            <tr data-statut="<?= $f['Statut_Paiement'] ?>">
                                <td><strong><?= htmlspecialchars($f['Numero_Facture']) ?></strong></td>
                                <td><?= htmlspecialchars($f['Nom_Client'] ?? 'N/A') ?></td>
                                <td><?= $f['Date_Echeance'] ? date('d/m/Y', strtotime($f['Date_Echeance'])) : '-' ?></td>
                                <td>
                                    <span class="badge badge-<?= $f['Statut_Paiement'] === 'payee' ? 'success' : ($f['Statut_Paiement'] === 'annulee' ? 'danger' : 'warning') ?>">
                                        <?= strtoupper(str_replace('_', ' ', $f['Statut_Paiement'])) ?>
                                    </span>
                                </td>
                                <td style="font-weight: bold;">
                                    <?= number_format($f['Montant_TTC'], 0, ',', ' ') ?> F
                                </td>
                                <td style="white-space: nowrap;">
                                    <?php if ($en_retention): ?>
                                        <span title="Conservation légale obligatoire jusqu'au <?= $label_conservation ?> (encore <?= $annees_restantes ?> an<?= $annees_restantes > 1 ? 's' : '' ?>)" style="color: #e67e22; font-weight: 600; font-size: 0.85em;">
                                            🔒 <?= $label_conservation ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #27ae60; font-size: 0.85em;">✅ Période expirée</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="invoice_view.php?ref=<?= htmlspecialchars($f['Numero_Vente']) ?>" target="_blank" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <form method="POST" style="display: inline-block; margin-left: 5px;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id_facture" value="<?= $f['Id_Facture'] ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="nouveau_statut" class="form-control" style="display: inline-block; width: auto; padding: 5px 8px; font-size: 0.85em;" onchange="this.form.submit()">
                                            <option value="">Changer statut...</option>
                                            <option value="payee" <?= $f['Statut_Paiement'] === 'payee' ? 'selected' : '' ?>>Payée</option>
                                            <option value="non_payee" <?= $f['Statut_Paiement'] === 'non_payee' ? 'selected' : '' ?>>Non Payée</option>
                                            <?php if ($en_retention): ?>
                                                <option value="annulee" disabled title="Annulation bloquée : conservation légale jusqu'au <?= $label_conservation ?>">🔒 Annulée (bloqué)</option>
                                            <?php else: ?>
                                                <option value="annulee" <?= $f['Statut_Paiement'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                                            <?php endif; ?>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Aucune facture disponible. <a href="invoice_add.php">Créer une vente</a> pour générer une facture.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function filterInvoices() {
        var statutFilter = document.getElementById("filterStatut").value.toLowerCase();
        var searchInput = document.getElementById("searchInvoice").value.toUpperCase();
        var table = document.querySelector(".table");
        if (!table) return;
        var tr = table.getElementsByTagName("tr");

        for (var i = 1; i < tr.length; i++) {
            var statut = tr[i].getAttribute("data-statut");
            var numFacture = tr[i].getElementsByTagName("td")[0];
            var client = tr[i].getElementsByTagName("td")[1];

            var matchStatut = (statutFilter === "" || statut === statutFilter);
            var matchSearch = true;

            if (searchInput !== "") {
                var numText = numFacture ? (numFacture.textContent || numFacture.innerText) : "";
                var clientText = client ? (client.textContent || client.innerText) : "";
                matchSearch = (numText.toUpperCase().indexOf(searchInput) > -1 || clientText.toUpperCase().indexOf(searchInput) > -1);
            }

            if (matchStatut && matchSearch) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
</script>

</body>

</html>
