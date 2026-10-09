<div class="container fade-in py-4">
    <div class="dash-hero">
        <div>
            <span class="dash-hero-date"><i class="far fa-calendar"></i> <?= date('d/m/Y') ?></span>
            <h1 class="dash-hero-title"><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
            <p class="dash-hero-text">Vos livraisons du jour</p>
            <div class="dash-hero-actions">
                <a href="logistique.php" class="btn btn-hero btn-sm"><i class="fas fa-route me-1"></i> Toutes mes livraisons</a>
            </div>
        </div>
        <img src="../assets/img/illustrations/banner-delivery.svg" alt="" class="dash-hero-art">
    </div>

    <div class="dash-kpi-row">
        <div class="dash-kpi kpi-warning">
            <span class="dash-kpi-icon"><i class="fas fa-truck-fast"></i></span>
            <div>
                <div class="dash-kpi-label">À livrer</div>
                <div class="dash-kpi-value"><?= $a_livrer ?></div>
            </div>
        </div>
        <div class="dash-kpi is-primary">
            <span class="dash-kpi-icon"><i class="fas fa-hourglass-half"></i></span>
            <div>
                <div class="dash-kpi-label">Remises, attente acheteur</div>
                <div class="dash-kpi-value"><?= $attente_acheteur ?></div>
            </div>
        </div>
        <div class="dash-kpi kpi-success">
            <span class="dash-kpi-icon"><i class="fas fa-circle-check"></i></span>
            <div>
                <div class="dash-kpi-label">Livrées aujourd'hui</div>
                <div class="dash-kpi-value"><?= $livrees_jour ?></div>
            </div>
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
                    $a_livrer_ici = empty($l['Date_Confirmation_Livreur']);
                    $retard   = $a_livrer_ici && $l['Date_Livraison_Prevue'] && strtotime($l['Date_Livraison_Prevue']) < strtotime('today');
                    $etat     = $retard ? 'is-late' : ($a_livrer_ici ? 'is-route' : '');
                    $sl       = libelleStatutLivraison($l);
                ?>
                    <div class="col-md-6 col-xl-4">
                    <div class="card dash-delivery <?= $etat ?> h-100">
                        <div class="card-body d-flex flex-column gap-2">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="min-w-0">
                                    <strong class="d-block text-truncate"><?= htmlspecialchars($nom) ?></strong>
                                    <span class="text-body-secondary small font-monospace">Commande <?= htmlspecialchars($ref) ?></span>
                                </div>
                                <span class="badge text-bg-<?= $sl['badge'] ?> text-nowrap"><?= htmlspecialchars($sl['label']) ?></span>
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
                                    <?= $retard ? 'En retard, prévu le ' : 'Prévu le ' ?><?= date('d/m/Y', strtotime($l['Date_Livraison_Prevue'])) ?>
                                <?php else: ?>
                                    Pas de date prévue
                                <?php endif; ?>
                            </div>

                            <?php if ($a_livrer_ici): ?>
                                <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-success mt-1">
                                    <i class="fas fa-check-circle"></i> Confirmer la remise
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
            <div class="empty-state">
                <img src="../assets/img/illustrations/empty-done.svg" alt="" class="empty-state-img">
                <p class="empty-state-title">Aucune livraison en cours</p>
                <p class="empty-state-text">Bien joué, tout est livré !</p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

</body>

</html>
