<div class="container fade-in py-4">
    <div class="mb-3">
        <h1 class="fs-4 fw-bold mb-1"><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
        <p class="text-body-secondary small mb-0">
            <i class="fas fa-lock me-1 opacity-50"></i>
            Accès lecture seule — données agrégées de la plateforme FactuPro.
        </p>
    </div>

    <div class="dash-kpi-row">
        <div class="dash-kpi is-primary">
            <div class="dash-kpi-label">Entreprises</div>
            <div class="dash-kpi-value"><?= $nb_entreprises ?></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Utilisateurs</div>
            <div class="dash-kpi-value"><?= $nb_utilisateurs ?></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Ventes totales</div>
            <div class="dash-kpi-value"><?= number_format($nb_ventes_total, 0, ',', ' ') ?></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">CA plateforme</div>
            <div class="dash-kpi-value"><?= number_format($ca_total_plateforme, 0, ',', ' ') ?> <small>FCFA</small></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="fas fa-building text-primary me-2"></i> Entreprises enregistrées</h2>
            <span class="badge text-bg-light"><?= $nb_entreprises ?> au total</span>
        </div>
        <?php if ($entreprises_recentes): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($entreprises_recentes as $e):
                    $initials = mb_strtoupper(mb_substr($e['Nom_Entreprise'], 0, 2));
                ?>
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <div class="company-avatar rounded-3 bg-primary text-white d-inline-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;flex-shrink:0;font-size:.8rem;"><?= htmlspecialchars($initials) ?></div>
                        <div class="flex-grow-1 min-w-0">
                            <strong class="d-block text-truncate"><?= htmlspecialchars($e['Nom_Entreprise']) ?></strong>
                            <small class="text-body-secondary"><?= htmlspecialchars($e['Secteur_Activite'] ?? $e['Ville'] ?? 'Entreprise enregistrée') ?></small>
                        </div>
                        <span class="badge text-bg-light text-nowrap"><i class="fas fa-user small"></i> <?= (int) $e['nb_membres'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card-body text-center text-body-secondary py-4">
                <p class="mb-0 small">Aucune entreprise enregistrée.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>

</html>
