<div class="container fade-in py-4">
    <div class="mb-3">
        <h1 class="fs-4 fw-bold mb-1"><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
        <p class="text-body-secondary small mb-0">Votre activité du jour — <?= date('d/m/Y') ?></p>
    </div>

    <div class="dash-kpi-row">
        <div class="dash-kpi is-primary">
            <div class="dash-kpi-label">CA du jour</div>
            <div class="dash-kpi-value"><?= number_format($ca_jour, 0, ',', ' ') ?> <small>FCFA</small></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Ventes aujourd'hui</div>
            <div class="dash-kpi-value"><?= $ventes_jour ?></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Clients servis</div>
            <div class="dash-kpi-value"><?= $nb_clients ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="fas fa-receipt text-primary me-2"></i> Ventes récentes</h2>
            <a href="sales.php" class="link-primary text-decoration-none fw-medium small">Tout voir</a>
        </div>
        <?php if ($ventes_recentes): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Référence</th>
                            <th>Date</th>
                            <th class="text-end">Montant</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-paginate="5">
                        <?php foreach ($ventes_recentes as $v): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($v['Nom_Client']) ?></strong></td>
                                <td class="text-body-secondary small"><?= htmlspecialchars($v['Numero_Vente']) ?></td>
                                <td class="text-body-secondary small"><?= date('d/m/Y H:i', strtotime($v['Date_Vente'])) ?></td>
                                <td class="text-end fw-bold"><?= number_format($v['Montant_Total'], 0, ',', ' ') ?> F</td>
                                <td>
                                    <a href="invoice_view.php?ref=<?= urlencode($v['Numero_Vente']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Voir facture">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card-body text-center text-body-secondary py-4">
                <p class="mb-2 small">Aucune vente enregistrée.</p>
                <a href="invoice_add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Créer une vente</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>

</html>
