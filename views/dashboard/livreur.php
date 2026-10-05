<div class="container fade-in py-4">
    <div class="mb-3">
        <h1 class="fs-4 fw-bold mb-1"><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
        <p class="text-body-secondary small mb-0">Vos livraisons — <?= date('d/m/Y') ?></p>
    </div>

    <div class="dash-kpi-row">
        <div class="dash-kpi">
            <div class="dash-kpi-label">À prendre en charge</div>
            <div class="dash-kpi-value"><?= $a_expedier ?></div>
        </div>
        <div class="dash-kpi is-primary">
            <div class="dash-kpi-label">En route</div>
            <div class="dash-kpi-value"><?= $en_route ?></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Livrées aujourd'hui</div>
            <div class="dash-kpi-value"><?= $livrees_jour ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="fas fa-route text-primary me-2"></i> Livraisons à effectuer</h2>
            <a href="logistique.php" class="link-primary text-decoration-none fw-medium small">Tout voir</a>
        </div>
        <div class="card-body">
        <?php if ($livraisons_actives): ?>
            <div class="row g-3" data-paginate="5">
                <?php foreach ($livraisons_actives as $l):
                    $is_b2b   = !empty($l['Id_Commande_B2B']);
                    $nom      = $is_b2b ? ($l['Nom_Acheteur'] ?? '-') : ($l['Nom_Client'] ?? '-');
                    $ref      = $is_b2b ? ($l['Numero_Commande'] ?? '-') : ($l['Numero_Vente'] ?? '-');
                    $is_route = $l['Statut_Livraison'] === 'expediee';
                    $retard   = $l['Date_Livraison_Prevue'] && strtotime($l['Date_Livraison_Prevue']) < time();
                    $etat     = $retard ? 'is-late' : ($is_route ? 'is-route' : '');
                ?>
                    <div class="col-md-6 col-xl-4">
                    <div class="card dash-delivery <?= $etat ?> h-100">
                        <div class="card-body d-flex flex-column gap-2">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="min-w-0">
                                    <strong class="d-block text-truncate"><?= htmlspecialchars($nom) ?></strong>
                                    <span class="text-body-secondary small font-monospace">Commande <?= htmlspecialchars($ref) ?></span>
                                </div>
                                <?php if ($is_route): ?>
                                    <span class="badge text-bg-info text-nowrap">En route</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary text-nowrap"><?= ucfirst(str_replace('_', ' ', $l['Statut_Livraison'])) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($l['Adresse_Livraison'])): ?>
                                <div class="small text-body-secondary">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?= htmlspecialchars(mb_strimwidth($l['Adresse_Livraison'], 0, 52, '…')) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($l['Numero_Suivi'])): ?>
                                <div class="small text-body-secondary">
                                    <i class="fas fa-barcode me-1"></i> Suivi : <?= htmlspecialchars($l['Numero_Suivi']) ?>
                                    <?php if (!empty($l['Transporteur'])): ?>
                                        (<?= htmlspecialchars($l['Transporteur']) ?>)
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="small <?= $retard ? 'text-danger fw-semibold' : 'text-body-secondary' ?>">
                                <i class="fas fa-<?= $retard ? 'exclamation-triangle' : 'calendar-alt' ?> me-1"></i>
                                <?php if ($l['Date_Livraison_Prevue']): ?>
                                    <?= $retard ? 'En retard — prévu le ' : 'Prévu le ' ?><?= date('d/m/Y', strtotime($l['Date_Livraison_Prevue'])) ?>
                                <?php else: ?>
                                    Pas de date prévue
                                <?php endif; ?>
                            </div>

                            <?php if ($is_route): ?>
                                <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-success mt-1">
                                    <i class="fas fa-check-circle"></i> Marquer livrée
                                </a>
                            <?php else: ?>
                                <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-primary mt-1">
                                    <i class="fas fa-shipping-fast"></i> Voir les détails
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-body-secondary py-5">
                <i class="fas fa-check-double fs-1 text-success d-block mb-3"></i>
                <p class="mb-0">Aucune livraison en cours. Bien joué !</p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

</body>

</html>
