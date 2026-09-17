<style>
.sv-amount { font-weight: 800; color: var(--success); font-family: 'Plus Jakarta Sans', sans-serif; }
code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
}
</style>

<div class="container fade-in py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h1 class="fs-4 fw-bold mb-1"><i class="fas fa-receipt"></i> Historique des Ventes</h1>
            <p class="text-body-secondary mb-0">Toutes les transactions de votre entreprise</p>
        </div>
        <a href="invoice_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouvelle Vente
        </a>
    </div>

    <!-- KPI STRIP -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6 text-body-secondary mb-1">Chiffre d'affaires</h3>
                    <div class="fs-5 fw-bold"><?= number_format((float)$total_ca, 0, ',', ' ') ?> F</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6 text-body-secondary mb-1">Total ventes</h3>
                    <div class="fs-5 fw-bold"><?= number_format($total_ventes) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6 text-body-secondary mb-1">CA aujourd'hui</h3>
                    <div class="fs-5 fw-bold"><?= number_format($ca_jour, 0, ',', ' ') ?> F</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <!-- Toolbar -->
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="h6 mb-0">Transactions</h2>
                <p class="text-body-secondary small mb-0">
                    Page <?= $page ?>/<?= max(1,$total_pages) ?> — <?= number_format($total_ventes) ?> ventes
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="width:auto;">
                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                    <input type="date" id="dateDebut" class="form-control" onchange="filterSales()" title="Date début">
                    <span class="input-group-text">→</span>
                    <input type="date" id="dateFin" class="form-control" onchange="filterSales()" title="Date fin">
                </div>
                <div class="input-group input-group-sm" style="width:180px;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchClient" class="form-control" placeholder="Client..." onkeyup="filterSales()">
                </div>
            </div>
        </div>

        <?php if (count($ventes) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Articles</th>
                        <th>Type</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventes as $i => $v): ?>
                    <tr data-date="<?= $v['Date_Vente'] ?>" data-client="<?= htmlspecialchars($v['Nom_Client'] ?? '') ?>">
                        <td><code><?= htmlspecialchars($v['Numero_Vente'] ?? '—') ?></code></td>
                        <td class="text-nowrap text-body-secondary small">
                            <?= !empty($v['Date_Vente']) ? date('d/m/Y H:i', strtotime($v['Date_Vente'])) : '—' ?>
                        </td>
                        <td class="fw-semibold"><?= htmlspecialchars($v['Nom_Client'] ?? '—') ?></td>
                        <td>
                            <?php $articles = json_decode($v['Articles_JSON'], true); ?>
                            <span class="small text-body-secondary">
                                <?php if ($articles): ?>
                                    <span class="fw-semibold me-1"><?= count($articles) ?> art. —</span>
                                    <?php foreach (array_slice($articles, 0, 2) as $art): ?>
                                        <?= htmlspecialchars($art['nom'] ?? '') ?><?= count($articles) > 1 ? ', ' : '' ?>
                                    <?php endforeach; ?>
                                    <?php if (count($articles) > 2) echo '<span class="text-body-secondary">…</span>'; ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <span class="fw-semibold <?= $v['Type_Vente'] === 'b2b' ? 'text-primary' : 'text-success' ?>">
                                <?= strtoupper($v['Type_Vente']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="sv-amount"><?= number_format((float)($v['Montant_Total'] ?? 0), 0, ',', ' ') ?> F</span>
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="invoice_view.php?ref=<?= htmlspecialchars($v['Numero_Vente'] ?? '') ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary" title="Voir facture">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="invoice_view.php?ref=<?= htmlspecialchars($v['Numero_Vente'] ?? '') ?>&autoprint=1"
                               target="_blank" class="btn btn-sm btn-secondary ms-1" title="Imprimer PDF">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="btn-icon mx-auto mb-3 fs-3" style="width:68px;height:68px;">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3 class="h6 mb-2">Aucune vente enregistrée</h3>
                <p class="text-body-secondary mb-3">Commencez à enregistrer vos transactions.</p>
                <a href="invoice_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Première vente</a>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-transparent">
            <nav>
                <ul class="pagination justify-content-center mb-2">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php
                    $window = 2; $start = max(1, $page - $window); $end = min($total_pages, $page + $window);
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    for ($p = $start; $p <= $end; $p++):
                        if ($p === $page): ?>
                            <li class="page-item active"><span class="page-link"><?= $p ?></span></li>
                        <?php else: ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
                        <?php endif;
                    endfor;
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'">'.$total_pages.'</a></li>';
                    }
                    ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
            <p class="text-center text-body-secondary small mb-0">
                Page <?= $page ?>/<?= $total_pages ?> — <?= number_format($total_ventes) ?> ventes au total
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterSales() {
    const dateDebut    = document.getElementById('dateDebut').value;
    const dateFin      = document.getElementById('dateFin').value;
    const searchClient = document.getElementById('searchClient').value.toUpperCase();

    document.querySelectorAll('table tbody tr').forEach(function(tr) {
        const dateVente = (tr.dataset.date || '').split(' ')[0];
        const nomClient = (tr.dataset.client || '').toUpperCase();

        const matchDate = (!dateDebut || dateVente >= dateDebut) && (!dateFin || dateVente <= dateFin);
        const matchCli  = !searchClient || nomClient.includes(searchClient);

        tr.style.display = (matchDate && matchCli) ? '' : 'none';
    });
}
</script>

</body>
</html>
