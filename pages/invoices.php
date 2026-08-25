<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

exigerPermission(peutVoirFactures());

$page_title = 'Suivi des Factures';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    exigerCsrf();
    $id_facture = $_POST['id_facture'];
    $nouveau_statut = $_POST['nouveau_statut'];

    $stmt = $pdo->prepare("SELECT Id_Facture, Date_Facture FROM Facture WHERE Id_Facture = ? AND Id_Entreprise = ?");
    $stmt->execute([$id_facture, $entreprise_id]);
    $facture_a_modifier = $stmt->fetch();

    if ($facture_a_modifier) {
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

// KPIs
$total_payees    = count(array_filter($factures, fn($f) => $f['Statut_Paiement'] === 'payee'));
$total_non_payees = count(array_filter($factures, fn($f) => $f['Statut_Paiement'] === 'non_payee'));
$total_annulees  = count(array_filter($factures, fn($f) => $f['Statut_Paiement'] === 'annulee'));
$ca_paye         = array_sum(array_map(fn($f) => $f['Statut_Paiement'] === 'payee' ? $f['Montant_TTC'] : 0, $factures));
$ca_impaye       = array_sum(array_map(fn($f) => $f['Statut_Paiement'] === 'non_payee' ? $f['Montant_TTC'] : 0, $factures));
?>

<style>
code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
    background: var(--zinc-100);
    padding: 2px 7px;
    border-radius: 5px;
    color: var(--primary);
}

.inv-status-select {
    appearance: none;
    -webkit-appearance: none;
    background: var(--zinc-100);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 5px 10px;
    font-size: 0.8rem;
    font-family: inherit;
    color: var(--text-main);
    cursor: pointer;
    transition: border-color var(--duration) var(--ease);
}

.inv-status-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.inv-filter-group {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--zinc-100);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0 12px;
    height: 38px;
    transition: border-color var(--duration) var(--ease);
}

.inv-filter-group:focus-within {
    border-color: var(--primary);
    background: var(--bg-card);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.inv-filter-group i { color: var(--text-muted); font-size: 0.8rem; }
.inv-filter-group input {
    background: transparent; border: none; outline: none;
    font-size: 0.85rem; color: var(--text-main);
    font-family: inherit; width: 160px;
}

.retention-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    padding: 3px 8px;
    border-radius: 6px;
}

.retention-active { background: rgba(245,158,11,0.1); color: var(--warning); }
.retention-expired { background: var(--success-bg); color: var(--success); }
</style>

<div class="container fade-in">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-invoice-dollar"></i> Gestion des Factures</h1>
            <p>Suivi des paiements et conservation légale</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <!-- KPI STRIP -->
    <div class="grid-4 stagger-children" style="margin-bottom:28px;">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--success-bg); color:var(--success);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Payées</h3>
                <div class="stat-value"><?= $total_payees ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>En attente</h3>
                <div class="stat-value"><?= $total_non_payees ?></div>
            </div>
        </div>
        <div class="stat-card gradient-blue">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <h3>CA encaissé</h3>
                <div class="stat-value" style="font-size:1.3rem;"><?= number_format($ca_paye, 0, ',', ' ') ?> F</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h3>Impayés</h3>
                <div class="stat-value" style="color:var(--danger);"><?= number_format($ca_impaye, 0, ',', ' ') ?> F</div>
            </div>
        </div>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <!-- Toolbar -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0; font-size:1.05rem;">Détails financiers</h2>
                <p style="margin:3px 0 0; font-size:0.8rem; color:var(--text-muted);"><?= count($factures) ?> facture<?= count($factures) > 1 ? 's' : '' ?></p>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <select id="filterStatut" class="inv-status-select" onchange="filterInvoices()">
                    <option value="">Tous les statuts</option>
                    <option value="payee">✅ Payée</option>
                    <option value="non_payee">⏳ Non Payée</option>
                    <option value="annulee">❌ Annulée</option>
                </select>
                <div class="inv-filter-group">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInvoice" placeholder="N° facture, client..." onkeyup="filterInvoices()">
                </div>
            </div>
        </div>

        <?php if (count($factures) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Client</th>
                        <th>Échéance</th>
                        <th>Statut</th>
                        <th class="text-right">Montant TTC</th>
                        <th title="Conservation légale 10 ans">🔒 Conservation</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($factures as $i => $f):
                        $date_conservation = new DateTime($f['Date_Facture']);
                        $date_conservation->modify('+10 years');
                        $en_retention = $date_conservation > new DateTime();
                        $label_conservation = $date_conservation->format('d/m/Y');
                        $diff = (new DateTime())->diff($date_conservation);
                        $annees_restantes = $en_retention ? $diff->y : 0;
                    ?>
                    <tr data-statut="<?= $f['Statut_Paiement'] ?>" style="animation: fadeInUp 0.3s <?= $i * 20 ?>ms both;">
                        <td><code><?= htmlspecialchars($f['Numero_Facture']) ?></code></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($f['Nom_Client'] ?? 'N/A') ?></td>
                        <td style="font-size:0.875rem; color:var(--text-muted);">
                            <?= $f['Date_Echeance'] ? date('d/m/Y', strtotime($f['Date_Echeance'])) : '—' ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $f['Statut_Paiement'] === 'payee' ? 'success' : ($f['Statut_Paiement'] === 'annulee' ? 'danger' : 'warning') ?>">
                                <?= strtoupper(str_replace('_', ' ', $f['Statut_Paiement'])) ?>
                            </span>
                        </td>
                        <td class="text-right" style="font-weight:800; color:var(--success); font-family:'Plus Jakarta Sans',sans-serif;">
                            <?= number_format($f['Montant_TTC'], 0, ',', ' ') ?> F
                        </td>
                        <td>
                            <?php if ($en_retention): ?>
                                <span class="retention-badge retention-active"
                                      title="Conservation légale jusqu'au <?= $label_conservation ?> (encore <?= $annees_restantes ?> an<?= $annees_restantes > 1 ? 's' : '' ?>)">
                                    🔒 <?= $label_conservation ?>
                                </span>
                            <?php else: ?>
                                <span class="retention-badge retention-expired">✅ Expirée</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a href="invoice_view.php?ref=<?= htmlspecialchars($f['Numero_Vente']) ?>"
                               target="_blank" class="btn btn-sm btn-secondary" title="Voir">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="invoice_view.php?ref=<?= htmlspecialchars($f['Numero_Vente']) ?>&autoprint=1"
                               target="_blank" class="btn btn-sm btn-secondary" title="PDF" style="margin-left:4px;">
                                <i class="fas fa-file-pdf" style="color:#ef4444;"></i>
                            </a>
                            <form method="POST" style="display:inline-block; margin-left:4px;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="id_facture" value="<?= $f['Id_Facture'] ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="nouveau_statut" class="inv-status-select" onchange="this.form.submit()">
                                    <option value="">Changer…</option>
                                    <option value="payee" <?= $f['Statut_Paiement'] === 'payee' ? 'selected' : '' ?>>✅ Payée</option>
                                    <option value="non_payee" <?= $f['Statut_Paiement'] === 'non_payee' ? 'selected' : '' ?>>⏳ Non Payée</option>
                                    <?php if ($en_retention): ?>
                                        <option value="annulee" disabled title="Bloqué : conservation légale">🔒 Annulée (bloqué)</option>
                                    <?php else: ?>
                                        <option value="annulee" <?= $f['Statut_Paiement'] === 'annulee' ? 'selected' : '' ?>>❌ Annulée</option>
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
            <div style="text-align:center; padding:56px 24px;">
                <div style="width:68px;height:68px;border-radius:20px;background:var(--zinc-100);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--zinc-400);margin:0 auto 18px;animation:float 3s ease-in-out infinite;">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h3 style="font-size:1.05rem; margin-bottom:8px;">Aucune facture</h3>
                <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Créez votre première vente pour générer une facture.</p>
                <a href="invoice_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle vente</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterInvoices() {
    const statutFilter = document.getElementById('filterStatut').value;
    const searchInput  = document.getElementById('searchInvoice').value.toUpperCase();

    document.querySelectorAll('table tbody tr').forEach(function(tr) {
        const statut = tr.dataset.statut || '';
        const numFacture = tr.cells[0]?.textContent || '';
        const client     = tr.cells[1]?.textContent || '';

        const matchStatut = !statutFilter || statut === statutFilter;
        const matchSearch = !searchInput  ||
            numFacture.toUpperCase().includes(searchInput) ||
            client.toUpperCase().includes(searchInput);

        tr.style.display = (matchStatut && matchSearch) ? '' : 'none';
    });
}
</script>

</body>
</html>
