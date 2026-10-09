<style>
code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
    background: var(--bs-secondary-bg);
    padding: 2px 7px;
    border-radius: 5px;
    color: var(--primary);
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

<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Suivi des paiements et conservation légale</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <!-- KPI STRIP -->
    <div class="dash-kpi-row">
        <div class="dash-kpi">
            <div class="dash-kpi-label">Payées</div>
            <div class="dash-kpi-value"><?= $total_payees ?></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">En attente</div>
            <div class="dash-kpi-value"><?= $total_non_payees ?></div>
        </div>
        <div class="dash-kpi is-primary">
            <div class="dash-kpi-label">CA encaissé</div>
            <div class="dash-kpi-value"><?= number_format($ca_paye, 0, ',', ' ') ?> <small>FCFA</small></div>
        </div>
        <div class="dash-kpi <?= $ca_impaye > 0 ? 'is-danger' : '' ?>">
            <div class="dash-kpi-label">Impayés</div>
            <div class="dash-kpi-value"><?= number_format($ca_impaye, 0, ',', ' ') ?> <small>FCFA</small></div>
        </div>
    </div>

    <div class="card">
        <!-- Toolbar -->
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="fs-6 mb-0">Détails financiers</h2>
                <p class="text-body-secondary small mb-0"><?= count($factures) ?> facture<?= count($factures) > 1 ? 's' : '' ?></p>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <select id="filterStatut" class="form-select form-select-sm" style="width:auto;" onchange="filterInvoices()">
                    <option value="">Tous les statuts</option>
                    <option value="payee">Payée</option>
                    <option value="non_payee">Non payée</option>
                    <option value="en_retard">En retard</option>
                    <option value="annulee">Annulée</option>
                </select>
                <div class="input-group input-group-sm" style="width:auto;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInvoice" class="form-control" placeholder="N° facture, client..." onkeyup="filterInvoices()">
                </div>
            </div>
        </div>

        <?php if (count($factures) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 invoices-sales">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Client</th>
                        <th>Échéance</th>
                        <th>Statut</th>
                        <th class="text-end">Montant TTC</th>
                        <th title="Conservation légale 10 ans">Conservation</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody data-paginate="5">
                    <?php foreach ($factures as $i => $f):
                        $date_conservation = new DateTime($f['Date_Facture']);
                        $date_conservation->modify('+10 years');
                        $en_retention = $date_conservation > new DateTime();
                        $label_conservation = $date_conservation->format('d/m/Y');
                        $diff = (new DateTime())->diff($date_conservation);
                        $annees_restantes = $en_retention ? $diff->y : 0;

                        // "En retard" est un état d'affichage dérivé (échéance dépassée + non payée),
                        // jamais écrit en base, Statut_Paiement reste sur ses 3 valeurs réelles.
                        $en_retard = $f['Statut_Paiement'] === 'non_payee'
                            && !empty($f['Date_Echeance'])
                            && strtotime($f['Date_Echeance']) < time();
                        $statut_affichage = $en_retard ? 'en_retard' : $f['Statut_Paiement'];
                        $statut_labels = ['payee' => 'Payée', 'non_payee' => 'Non payée', 'en_retard' => 'En retard', 'annulee' => 'Annulée'];
                        $statut_classes = ['payee' => 'success', 'non_payee' => 'warning', 'en_retard' => 'danger', 'annulee' => 'secondary'];
                    ?>
                    <tr data-statut="<?= $statut_affichage ?>">
                        <td><code><?= htmlspecialchars($f['Numero_Facture']) ?></code></td>
                        <td class="fw-semibold"><?= htmlspecialchars($f['Nom_Client'] ?? '-') ?></td>
                        <td class="small text-body-secondary">
                            <?= $f['Date_Echeance'] ? date('d/m/Y', strtotime($f['Date_Echeance'])) : '-' ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= $statut_classes[$statut_affichage] ?>">
                                <?= $statut_labels[$statut_affichage] ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold">
                            <?= number_format($f['Montant_TTC'], 0, ',', ' ') ?> F
                        </td>
                        <td>
                            <?php if ($en_retention): ?>
                                <span class="retention-badge retention-active"
                                      title="Conservation légale jusqu'au <?= $label_conservation ?> (encore <?= $annees_restantes ?> an<?= $annees_restantes > 1 ? 's' : '' ?>)">
                                    <i class="fas fa-lock"></i> <?= $label_conservation ?>
                                </span>
                            <?php else: ?>
                                <span class="retention-badge retention-expired"><i class="fas fa-check"></i> Expirée</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="invoice_view.php?ref=<?= htmlspecialchars($f['Numero_Vente']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-secondary" title="Voir la facture">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="invoice_view.php?ref=<?= htmlspecialchars($f['Numero_Vente']) ?>&autoprint=1"
                               target="_blank" class="btn btn-sm btn-outline-secondary ms-1" title="Imprimer / Exporter en PDF">
                                <i class="fas fa-print"></i>
                            </a>
                            <?php if ($f['Statut_Paiement'] !== 'annulee'): ?>
                            <form method="POST" class="d-inline-block ms-1"
                                  onsubmit="return this.nouveau_statut.value !== 'annulee' || confirm('Annuler définitivement cette facture ? Elle restera archivée et les produits seront remis en stock.')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="id_facture" value="<?= $f['Id_Facture'] ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="nouveau_statut" class="form-select form-select-sm d-inline-block" style="width:auto;" onchange="this.form.requestSubmit()" title="Changer le statut">
                                    <option value="">Changer…</option>
                                    <option value="payee" <?= $f['Statut_Paiement'] === 'payee' ? 'selected' : '' ?>>Payée</option>
                                    <option value="non_payee" <?= $f['Statut_Paiement'] === 'non_payee' ? 'selected' : '' ?>>Non payée</option>
                                    <?php if (empty($f['Id_Commande_B2B'])): ?>
                                        <option value="annulee">Annuler la facture</option>
                                    <?php endif; ?>
                                </select>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state py-5">
                <img src="../assets/img/illustrations/empty-receipt.svg" alt="" class="empty-state-img">
                <p class="empty-state-title">Aucune facture</p>
                <p class="empty-state-text">Créez votre première vente pour générer une facture.</p>
                <a href="invoice_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle vente</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (peutGererB2B()): ?>
    <!-- Factures d'achat : factures B2B reçues des fournisseurs -->
    <div class="card mt-4" id="factures-achat">
        <div class="card-header bg-transparent">
            <h2 class="h6 mb-0"><i class="fas fa-file-import text-primary me-2"></i> Factures d'achat (fournisseurs B2B)</h2>
            <p class="small text-body-secondary mb-0">Factures émises par vos fournisseurs pour vos commandes B2B. Le statut de paiement est mis à jour par le fournisseur.</p>
        </div>
        <?php if (!empty($factures_achat)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>N° facture</th>
                        <th>Fournisseur</th>
                        <th>Commande</th>
                        <th>Date</th>
                        <th class="text-end">HT</th>
                        <th class="text-end">TVA</th>
                        <th class="text-end">TTC</th>
                        <th>Paiement</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody data-paginate="5">
                    <?php
                    $labels_achat = ['payee' => ['Payée', 'success'], 'non_payee' => ['À payer', 'warning'], 'annulee' => ['Annulée', 'secondary']];
                    foreach ($factures_achat as $fa):
                        [$label_fa, $classe_fa] = $labels_achat[$fa['Statut_Paiement']] ?? [$fa['Statut_Paiement'], 'secondary'];
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($fa['Numero_Facture']) ?></td>
                        <td><?= htmlspecialchars($fa['Fournisseur']) ?></td>
                        <td><code><?= htmlspecialchars($fa['Numero_Commande']) ?></code></td>
                        <td class="small"><?= date('d/m/Y', strtotime($fa['Date_Facture'])) ?></td>
                        <td class="text-end"><?= number_format((float) $fa['Montant_HT'], 0, ',', ' ') ?> F</td>
                        <td class="text-end"><?= number_format((float) $fa['TVA'], 0, ',', ' ') ?> F</td>
                        <td class="text-end fw-semibold"><?= number_format((float) $fa['Montant_TTC'], 0, ',', ' ') ?> F</td>
                        <td><span class="badge text-bg-<?= $classe_fa ?>"><?= htmlspecialchars($label_fa) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a href="invoice_view.php?ref=<?= urlencode($fa['Numero_Facture']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Voir la facture">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="invoice_view.php?ref=<?= urlencode($fa['Numero_Facture']) ?>&autoprint=1" target="_blank" class="btn btn-sm btn-outline-secondary ms-1" title="Imprimer / Exporter en PDF">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-body-secondary small p-3 mb-0">Aucune facture d'achat : elles apparaîtront ici dès qu'un fournisseur expédiera l'une de vos commandes B2B.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function filterInvoices() {
    const statutFilter = document.getElementById('filterStatut').value;
    const searchInput  = document.getElementById('searchInvoice').value.toUpperCase();

    document.querySelectorAll('table.invoices-sales tbody tr').forEach(function(tr) {
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
