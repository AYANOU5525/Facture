<style>
code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
}
.delay-alert {
    font-weight: 700;
    font-size: 0.8rem;
}
</style>

<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Expéditions, livraisons et suivi des transporteurs</p>
    </div>

    <!-- KPI STRIP -->
    <div class="dash-kpi-row">
        <div class="dash-kpi">
            <div class="dash-kpi-label">À planifier</div>
            <div class="dash-kpi-value"><?= $nb_attente ?></div>
        </div>
        <div class="dash-kpi is-primary">
            <div class="dash-kpi-label">En transit</div>
            <div class="dash-kpi-value"><?= $nb_route ?></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Livrées</div>
            <div class="dash-kpi-value"><?= $nb_livrees ?></div>
        </div>
        <div class="dash-kpi <?= $nb_retard > 0 ? 'is-danger' : '' ?>">
            <div class="dash-kpi-label">En retard</div>
            <div class="dash-kpi-value"><?= $nb_retard ?></div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="card">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="fs-6 mb-0">Expéditions &amp; Livraisons</h2>
                <p class="text-body-secondary small mb-0"><?= $nb_resultats ?> expédition<?= $nb_resultats > 1 ? 's' : '' ?></p>
            </div>

            <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="width:220px;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="search" name="q" class="form-control" value="<?= htmlspecialchars($recherche) ?>" placeholder="Commande, client, transporteur...">
                </div>
                <select name="statut" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="traitement" <?= $statut_filtre === 'traitement' ? 'selected' : '' ?>>À planifier</option>
                    <option value="expediee" <?= $statut_filtre === 'expediee' ? 'selected' : '' ?>>En route</option>
                    <option value="livree" <?= $statut_filtre === 'livree' ? 'selected' : '' ?>>Livrée</option>
                    <option value="annulee" <?= $statut_filtre === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i></button>
                <?php if ($recherche !== '' || $statut_filtre !== ''): ?>
                    <a href="logistique.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($logistique) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Réf. Document</th>
                            <th>Client / Acheteur</th>
                            <th>Transporteur</th>
                            <th>N° Suivi</th>
                            <th>Statut</th>
                            <th>Date Prévue</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logistique as $i => $l):
                            $dp = $l['Date_Livraison_Prevue'];
                            $retard = $dp && strtotime($dp) < strtotime('today') && $l['Statut_Livraison'] !== 'livree' && $l['Statut_Livraison'] !== 'annulee';

                            $sl = libelleStatutLivraison($l);
                            $a_confirmer = $l['Statut_Livraison'] === 'expediee' && empty($l['Date_Confirmation_Livreur']);
                        ?>
                            <tr>
                                <td>
                                    <?php if ($l['Id_Commande_B2B']): ?>
                                        <span class="badge text-bg-primary mb-1"><i class="fas fa-handshake"></i> B2B</span><br>
                                        <code><?= htmlspecialchars($l['Numero_Commande'] ?? '-') ?></code>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary mb-1"><i class="fas fa-store"></i> Comptoir</span><br>
                                        <code><?= htmlspecialchars($l['Numero_Vente'] ?? '-') ?></code>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold">
                                    <?php if ($l['Id_Commande_B2B']): ?>
                                        <?= htmlspecialchars($l['Nom_Acheteur'] ?? '-') ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($l['Nom_Client'] ?? '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($l['Transporteur'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($l['Numero_Suivi'] ?? '-') ?></code></td>
                                <td>
                                    <span class="badge text-bg-<?= $sl['badge'] ?>"><?= htmlspecialchars($sl['label']) ?></span>
                                </td>
                                <td>
                                    <?php if ($retard): ?>
                                        <span class="delay-alert text-danger" title="Livraison en retard ! Date limite dépassée.">
                                            <i class="fas fa-exclamation-triangle"></i> <?= date('d/m/Y', strtotime($dp)) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-body-secondary small">
                                            <?= $dp ? date('d/m/Y', strtotime($dp)) : '-' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($l['Statut_Livraison'] === 'traitement' && aRole(ROLE_PROPRIO)): ?>
                                        <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-sm btn-primary" title="Renseigner le transporteur et valider l'expédition">
                                            <i class="fas fa-shipping-fast"></i> Expédier
                                        </a>
                                    <?php elseif ($a_confirmer): ?>
                                        <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-sm btn-success" title="Confirmer la remise du colis">
                                            <i class="fas fa-check-circle"></i> Livré
                                        </a>
                                    <?php else: ?>
                                        <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-sm btn-outline-primary" title="Suivi &amp; Carte">
                                            <i class="fas fa-map-marked-alt"></i> Carte
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state py-5">
                <img src="../assets/img/illustrations/empty-inbox.svg" alt="" class="empty-state-img">
                <p class="empty-state-title">Aucune expédition</p>
                <p class="empty-state-text">Aucun colis ne correspond aux critères de recherche actuels.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1):
        $qs = array_filter(['q' => $recherche, 'statut' => $statut_filtre]);
    ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query($qs + ['p' => $i]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

</body>
</html>
