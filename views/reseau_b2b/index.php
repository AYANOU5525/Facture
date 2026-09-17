<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Annuaire des entreprises partenaires du réseau B2B</p>
    </div>

    <!-- Navigation B2B -->
    <div class="d-flex gap-2 flex-wrap mb-3">
        <a href="reseau_b2b.php" class="btn btn-sm <?= $current === 'reseau_b2b.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-building"></i> Annuaire
        </a>
        <a href="annonces.php" class="btn btn-sm <?= $current === 'annonces.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-bullhorn"></i> Annonces
        </a>
        <a href="commandes_b2b.php" class="btn btn-sm <?= $current === 'commandes_b2b.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-shipping-fast"></i> Commandes
        </a>
        <a href="notifications_b2b.php" class="btn btn-sm <?= $current === 'notifications_b2b.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-bell"></i> Notifications
            <?php if (!empty($nb_non_lues)): ?><span class="badge rounded-pill text-bg-danger ms-1"><?= $nb_non_lues ?></span><?php endif; ?>
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
        <form method="GET" id="filtres-form">
            <div class="row g-3 align-items-end">
                <!-- Secteur -->
                <div class="col-md-3">
                    <label for="filtre-secteur" class="form-label small fw-semibold text-body-secondary"><i class="fas fa-industry"></i> Secteur</label>
                    <select id="filtre-secteur" name="secteur" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous les secteurs</option>
                        <?php foreach ($secteurs as $sec): ?>
                            <option value="<?= htmlspecialchars($sec) ?>"
                                <?= $secteur_filtre === $sec ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sec) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ville -->
                <div class="col-md-3">
                    <label for="filtre-ville" class="form-label small fw-semibold text-body-secondary"><i class="fas fa-city"></i> Ville</label>
                    <select id="filtre-ville" name="ville" class="form-select" onchange="this.form.submit()">
                        <option value="">Toutes les villes</option>
                        <?php foreach ($villes as $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>"
                                <?= $ville_filtre === $v ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Région -->
                <div class="col-md-3">
                    <label for="filtre-region" class="form-label small fw-semibold text-body-secondary"><i class="fas fa-globe-africa"></i> Pays / Région</label>
                    <select id="filtre-region" name="region" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous les pays</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"
                                <?= $region_filtre === $r ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Distance (si coordonnées dispo) -->
                <?php if ($j_ai_coords): ?>
                    <div class="col-md-3">
                        <label for="filtre-distance" class="form-label small fw-semibold text-body-secondary"><i class="fas fa-route"></i> Distance max</label>
                        <select id="filtre-distance" name="distance" class="form-select" onchange="this.form.submit()">
                            <option value="0">Toutes distances</option>
                            <option value="500" <?= $distance_max == 500  ? 'selected' : '' ?>>500 km</option>
                            <option value="1000" <?= $distance_max == 1000 ? 'selected' : '' ?>>1 000 km</option>
                            <option value="2000" <?= $distance_max == 2000 ? 'selected' : '' ?>>2 000 km</option>
                            <option value="5000" <?= $distance_max == 5000 ? 'selected' : '' ?>>5 000 km</option>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Boutons -->
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <?php if ($j_ai_coords): ?>
                        <a href="?autour_de_moi=1<?= $secteur_filtre ? '&secteur=' . urlencode($secteur_filtre) : '' ?>"
                            class="btn btn-primary btn-sm <?= $tri_distance ? 'active' : '' ?>">
                            <i class="fas fa-location-arrow"></i> Autour de moi
                        </a>
                    <?php endif; ?>
                    <a href="reseau_b2b.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                </div>
            </div>
        </form>

        <!-- Résumé des filtres actifs -->
        <?php if ($secteur_filtre || $ville_filtre || $region_filtre || $distance_max || $tri_distance): ?>
            <div class="d-flex align-items-center gap-2 flex-wrap mt-3 pt-3 border-top small">
                <i class="fas fa-filter text-body-secondary"></i>
                <span class="fw-semibold"><?= count($entreprises) ?> résultat(s)</span>
                <?php if ($secteur_filtre): ?>
                    <span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars($secteur_filtre) ?></span>
                <?php endif; ?>
                <?php if ($ville_filtre): ?>
                    <span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars($ville_filtre) ?></span>
                <?php endif; ?>
                <?php if ($region_filtre): ?>
                    <span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars($region_filtre) ?></span>
                <?php endif; ?>
                <?php if ($distance_max): ?>
                    <span class="badge rounded-pill text-bg-primary">≤ <?= number_format($distance_max, 0, ',', ' ') ?> km</span>
                <?php endif; ?>
                <?php if ($tri_distance): ?>
                    <span class="badge rounded-pill text-bg-primary">📍 Trié par distance</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Annuaire des entreprises -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h6 mb-0"><?= count($entreprises) ?> entreprise<?= count($entreprises) > 1 ? 's' : '' ?></h2>
            <div class="input-group input-group-sm" style="max-width:240px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchEntreprise" class="form-control" placeholder="Rechercher..." onkeyup="filterEntreprises()">
            </div>
        </div>

        <?php if (empty($entreprises)): ?>
            <div class="text-center text-body-secondary py-5">
                <i class="fas fa-search fs-1 opacity-25 d-block mb-3"></i>
                <p class="mb-2">Aucune entreprise trouvée avec ces critères.</p>
                <a href="reseau_b2b.php" class="btn btn-outline-secondary btn-sm">Voir toutes les entreprises</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tableEntreprises">
                    <thead>
                        <tr>
                            <th>Entreprise</th>
                            <th>Secteur</th>
                            <th>Ville</th>
                            <th style="min-width:140px;">Fiabilité</th>
                            <th>Réactivité</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entreprises as $e):
                            $score = (int) $e['Score_Fiabilite'];
                            $score_color = $score >= 90 ? 'success' : ($score >= 70 ? 'info' : 'warning');
                            $react = $e['reactivite'];
                            $react_color = ['reaction-excellent' => 'success', 'reaction-bon' => 'info', 'reaction-moyen' => 'warning', 'reaction-lent' => 'danger'][$react['classe']] ?? 'secondary';
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="ent-avatar"><?= strtoupper(substr($e['Nom_Entreprise'], 0, 1)) ?></div>
                                        <div class="min-w-0">
                                            <div class="fw-bold text-truncate"><?= htmlspecialchars($e['Nom_Entreprise']) ?></div>
                                            <?php if (!empty($e['Description_Entreprise'])): ?>
                                                <div class="small text-body-secondary text-truncate" style="max-width:260px;"><?= htmlspecialchars($e['Description_Entreprise']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="small text-body-secondary"><?= !empty($e['Secteur_Activite']) ? htmlspecialchars($e['Secteur_Activite']) : '—' ?></td>
                                <td class="small text-body-secondary">
                                    <?= !empty($e['Ville']) ? htmlspecialchars($e['Ville']) : '—' ?>
                                    <?php if ($e['distance_label'] && $j_ai_coords): ?>
                                        <div class="text-body-tertiary" style="font-size:.72rem;"><?= htmlspecialchars($e['distance_label']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-<?= $score_color ?>"><?= $score ?></span>
                                        <div class="progress flex-grow-1" style="height:4px;">
                                            <div class="progress-bar bg-<?= $score_color ?>" style="width:<?= $score ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="small text-body-secondary"><?= number_format($e['Nombre_Commandes_Completees'], 0, ',', ' ') ?> commande<?= $e['Nombre_Commandes_Completees'] > 1 ? 's' : '' ?></div>
                                </td>
                                <td><span class="badge text-bg-<?= $react_color ?>"><?= htmlspecialchars($react['label']) ?></span></td>
                                <td class="text-center text-nowrap">
                                    <a href="commandes_b2b.php?onglet=passees&vendeur=<?= $e['Id_Entreprise'] ?>"
                                        class="btn btn-sm btn-primary" title="Commander">
                                        <i class="fas fa-shopping-cart"></i>
                                    </a>
                                    <?php if (!empty($e['Tel_Entreprise'])): ?>
                                        <a href="tel:<?= htmlspecialchars($e['Tel_Entreprise']) ?>"
                                            class="btn btn-sm btn-outline-secondary ms-1" title="<?= htmlspecialchars($e['Tel_Entreprise']) ?>">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($e['Email_Entreprise'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($e['Email_Entreprise']) ?>"
                                            class="btn btn-sm btn-outline-secondary ms-1" title="<?= htmlspecialchars($e['Email_Entreprise']) ?>">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .ent-avatar {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: var(--bs-secondary-bg);
        color: var(--bs-secondary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        font-weight: 700;
        flex-shrink: 0;
    }
</style>

<script>
function filterEntreprises() {
    const filter = document.getElementById('searchEntreprise').value.toUpperCase();
    document.querySelectorAll('#tableEntreprises tbody tr').forEach(function(tr) {
        const txt = tr.querySelector('td')?.textContent || '';
        tr.style.display = txt.toUpperCase().includes(filter) ? '' : 'none';
    });
}
</script>
