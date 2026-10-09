<div class="container fade-in py-4">

    <!-- EN-TÊTE -->
    <div class="dash-hero">
        <div>
            <span class="dash-hero-date"><i class="far fa-calendar"></i> <?= date('d/m/Y') ?></span>
            <h1 class="dash-hero-title"><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
            <p class="dash-hero-text">Aperçu de l'activité de votre entreprise</p>
            <div class="dash-hero-actions">
                <?php if (peutCreerVente()): ?>
                    <a href="invoice_add.php" class="btn btn-hero btn-sm"><i class="fas fa-cash-register me-1"></i> Nouvelle vente</a>
                <?php endif; ?>
                <?php if (peutGererB2B()): ?>
                    <a href="commandes_b2b.php" class="btn btn-hero-ghost btn-sm"><i class="fas fa-comments me-1"></i> Commandes B2B</a>
                <?php endif; ?>
            </div>
        </div>
        <img src="../assets/img/illustrations/banner-growth.svg" alt="" class="dash-hero-art">
    </div>

    <!-- ZONE SUPÉRIEURE : CA / VENTES / COMMANDES EN COURS -->
    <div class="dash-kpi-row">
        <div class="dash-kpi is-primary">
            <span class="dash-kpi-icon"><i class="fas fa-coins"></i></span>
            <div>
                <div class="dash-kpi-label">Chiffre d'affaires</div>
                <div class="dash-kpi-value"><?= number_format($total_ca, 0, ',', ' ') ?> <small>FCFA</small></div>
            </div>
        </div>
        <div class="dash-kpi kpi-success">
            <span class="dash-kpi-icon"><i class="fas fa-receipt"></i></span>
            <div>
                <div class="dash-kpi-label">Ventes</div>
                <div class="dash-kpi-value"><?= $nb_ventes ?></div>
            </div>
        </div>
        <div class="dash-kpi <?= count($b2b) > 0 ? 'is-danger' : 'kpi-warning' ?>">
            <span class="dash-kpi-icon"><i class="fas fa-hourglass-half"></i></span>
            <div>
                <div class="dash-kpi-label">Commandes B2B en attente</div>
                <div class="dash-kpi-value"><?= count($b2b) ?></div>
            </div>
        </div>
    </div>

    <!-- ALERTES STOCK -->
    <?php if (!empty($produits_alerte)): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i> Alertes stock</h2>
                <a href="products.php" class="link-primary text-decoration-none fw-medium small">Voir les produits</a>
            </div>
            <div class="list-group list-group-flush" data-paginate="5">
                <?php foreach ($produits_alerte as $pa): ?>
                    <a href="products.php?edit=<?= (int) $pa['Id_Produit'] ?>&focus=stock"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                       title="Cliquer pour réapprovisionner ce produit">
                        <span><?= htmlspecialchars($pa['Nom_Produit']) ?></span>
                        <span>
                            <span class="badge text-bg-warning"><?= (int) $pa['Quantite_En_Stock'] ?> restant(s)</span>
                            <i class="fas fa-plus-circle text-primary ms-2" title="Réapprovisionner"></i>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- COMMANDES B2B EN ATTENTE -->
    <?php if (estProprietaire()): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0"><i class="fas fa-comments text-primary me-2"></i> Commandes B2B à valider</h2>
                <a href="commandes_b2b.php?onglet=recues" class="link-primary text-decoration-none fw-medium small">Gérer</a>
            </div>
            <?php if ($b2b): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Entreprise</th>
                                <th>Commande</th>
                                <th class="text-end">Montant</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-paginate="5">
                            <?php foreach ($b2b as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['Nom_Entreprise']) ?></td>
                                    <td class="text-body-secondary small"><?= htmlspecialchars($c['Numero_Commande']) ?></td>
                                    <td class="text-end fw-bold"><?= number_format($c['Montant_Total'], 0, ',', ' ') ?> F</td>
                                    <td class="text-end"><a href="commandes_b2b.php?onglet=recues" class="btn btn-sm btn-outline-secondary">Voir</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <img src="../assets/img/illustrations/empty-done.svg" alt="" class="empty-state-img">
                    <p class="empty-state-text">Aucune commande en attente de validation.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ACTIVITÉ RÉCENTE -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="fas fa-clock-rotate-left text-primary me-2"></i> Activité récente</h2>
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
                        </tr>
                    </thead>
                    <tbody data-paginate="5">
                        <?php foreach ($ventes_recentes as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['Nom_Client']) ?></td>
                                <td class="text-body-secondary small"><?= htmlspecialchars($v['Numero_Vente']) ?></td>
                                <td class="text-body-secondary small"><?= date('d/m/Y H:i', strtotime($v['Date_Vente'])) ?></td>
                                <td class="text-end fw-bold"><?= number_format($v['Montant_Total'], 0, ',', ' ') ?> F</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <img src="../assets/img/illustrations/empty-receipt.svg" alt="" class="empty-state-img">
                <p class="empty-state-title">Aucune vente enregistrée</p>
                <p class="empty-state-text">Vos dernières ventes apparaîtront ici.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>

</html>
