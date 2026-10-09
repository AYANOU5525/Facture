<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Annuaire des entreprises partenaires du réseau B2B</p>
    </div>

    <!-- Navigation B2B -->
    <?php require __DIR__ . '/../partials/b2b_nav.php'; ?>

    <div class="panel-soft mb-4">
        <div class="panel-soft-head">
            <div>
                <h2 class="panel-soft-title">Filtrer les entreprises</h2>
                <div class="panel-soft-sub">Par secteur, ville, région ou distance</div>
            </div>
        </div>
        <div class="panel-soft-body">
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
            <div class="d-flex align-items-center gap-2 flex-wrap mt-3 pt-3 border-top small" aria-label="Filtres actifs">
                <i class="fas fa-filter text-body-secondary"></i>
                <span class="fw-semibold"><?= count($entreprises) ?> résultat(s)</span>
                <?php if ($secteur_filtre): ?>
                    <span class="chip-soft"><?= htmlspecialchars($secteur_filtre) ?></span>
                <?php endif; ?>
                <?php if ($ville_filtre): ?>
                    <span class="chip-soft"><?= htmlspecialchars($ville_filtre) ?></span>
                <?php endif; ?>
                <?php if ($region_filtre): ?>
                    <span class="chip-soft"><?= htmlspecialchars($region_filtre) ?></span>
                <?php endif; ?>
                <?php if ($distance_max): ?>
                    <span class="chip-soft">≤ <?= number_format($distance_max, 0, ',', ' ') ?> km</span>
                <?php endif; ?>
                <?php if ($tri_distance): ?>
                    <span class="chip-soft"><i class="fas fa-location-dot"></i> Trié par distance</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Annuaire des entreprises -->
    <div class="panel-soft">
        <div class="panel-soft-head">
            <div>
                <h2 class="panel-soft-title">Entreprises du réseau</h2>
                <div class="panel-soft-sub"><?= count($entreprises) ?> entreprise<?= count($entreprises) > 1 ? 's' : '' ?> partenaire<?= count($entreprises) > 1 ? 's' : '' ?></div>
            </div>
            <label class="search-soft mb-0">
                <i class="fas fa-search"></i>
                <input type="search" id="searchEntreprise" class="form-control form-control-sm" placeholder="Rechercher une entreprise…" oninput="filterEntreprises()" aria-label="Rechercher une entreprise">
            </label>
        </div>

        <?php if (empty($entreprises)): ?>
            <div class="empty-state py-5">
                <img src="../assets/img/illustrations/empty-users.svg" alt="" class="empty-state-img">
                <p class="empty-state-title">Aucune entreprise trouvée avec ces critères.</p>
                <a href="reseau_b2b.php" class="btn btn-outline-secondary btn-sm">Voir toutes les entreprises</a>
            </div>
        <?php else: ?>
            <div class="annuaire-list" id="listeEntreprises" data-paginate="5">
                <?php foreach ($entreprises as $e):
                    $score = (int) $e['Score_Fiabilite'];
                    $score_color = $score >= 90 ? 'success' : ($score >= 70 ? 'info' : 'warning');
                    $react = $e['reactivite'];
                    $react_color = ['reaction-excellent' => 'success', 'reaction-bon' => 'info', 'reaction-moyen' => 'warning', 'reaction-lent' => 'danger'][$react['classe']] ?? 'secondary';
                    $livrees = (int) $e['Nombre_Commandes_Completees'];
                    // Couleur d'avatar stable pour chaque entreprise (dérivée de son nom)
                    $teinte = crc32($e['Nom_Entreprise']) % 360;
                    $recherche = mb_strtolower(implode(' ', array_filter([
                        $e['Nom_Entreprise'], $e['Description_Entreprise'] ?? '', $e['Secteur_Activite'] ?? '', $e['Ville'] ?? '',
                    ])));
                ?>
                    <div class="annuaire-row" data-search="<?= htmlspecialchars($recherche) ?>" style="--ent-hue: <?= $teinte ?>">
                        <span class="ent-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($e['Nom_Entreprise'], 0, 1))) ?></span>

                        <div class="annuaire-body">
                            <div class="annuaire-name"><?= htmlspecialchars($e['Nom_Entreprise']) ?></div>
                            <?php if (!empty($e['Description_Entreprise'])): ?>
                                <div class="annuaire-desc"><?= htmlspecialchars($e['Description_Entreprise']) ?></div>
                            <?php endif; ?>
                            <div class="annuaire-meta">
                                <span><i class="fas fa-industry"></i> <?= !empty($e['Secteur_Activite']) ? htmlspecialchars($e['Secteur_Activite']) : 'Secteur non renseigné' ?></span>
                                <span>
                                    <i class="fas fa-location-dot"></i> <?= !empty($e['Ville']) ? htmlspecialchars($e['Ville']) : 'Ville non renseignée' ?>
                                    <?php if ($e['distance_label'] && $j_ai_coords): ?>
                                        <span class="annuaire-distance">· <?= htmlspecialchars($e['distance_label']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="annuaire-stats">
                            <div class="annuaire-score" title="Score de fiabilité : part des commandes menées à terme">
                                <span class="annuaire-score-value text-<?= $score_color ?>"><?= $score ?></span>
                                <div class="flex-grow-1">
                                    <div class="progress" style="height:4px;">
                                        <div class="progress-bar bg-<?= $score_color ?>" style="width:<?= $score ?>%"></div>
                                    </div>
                                    <div class="annuaire-score-label">
                                        <?= $livrees > 0 ? number_format($livrees, 0, ',', ' ') . ' commande' . ($livrees > 1 ? 's livrées' : ' livrée') : 'Aucune commande livrée' ?>
                                    </div>
                                </div>
                            </div>
                            <span class="annuaire-chip bg-<?= $react_color ?>-subtle text-<?= $react_color ?>-emphasis border border-<?= $react_color ?>-subtle">
                                <i class="far fa-clock"></i> <?= htmlspecialchars($react['label']) ?>
                            </span>
                        </div>

                        <div class="annuaire-actions">
                            <a href="commandes_b2b.php?onglet=passees&vendeur=<?= $e['Id_Entreprise'] ?>" class="btn btn-sm btn-primary" title="Commander">
                                <i class="fas fa-shopping-cart"></i> <span class="d-none d-xl-inline">Commander</span>
                            </a>
                            <?php if (!empty($e['Tel_Entreprise'])): ?>
                                <a href="tel:<?= htmlspecialchars($e['Tel_Entreprise']) ?>" class="btn btn-sm btn-outline-secondary" title="Appeler : <?= htmlspecialchars($e['Tel_Entreprise']) ?>" aria-label="Appeler">
                                    <i class="fas fa-phone"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($e['Email_Entreprise'])): ?>
                                <a href="mailto:<?= htmlspecialchars($e['Email_Entreprise']) ?>" class="btn btn-sm btn-outline-secondary" title="Écrire : <?= htmlspecialchars($e['Email_Entreprise']) ?>" aria-label="Envoyer un e-mail">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="annuaire-empty-search text-center text-body-secondary py-4" data-pg-ignore hidden>
                    <i class="fas fa-search opacity-50 me-1"></i> Aucune entreprise ne correspond à votre recherche.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .annuaire-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 12px;
    }
    .annuaire-row {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 16px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--bg-card);
        transition: border-color 0.12s ease, box-shadow 0.12s ease;
    }
    .annuaire-row:hover {
        border-color: hsl(var(--ent-hue) 60% 55% / 0.45);
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    }
    .ent-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        font-weight: 700;
        color: hsl(var(--ent-hue) 65% 42%);
        background: hsl(var(--ent-hue) 70% 50% / 0.14);
    }
    [data-bs-theme="dark"] .ent-avatar { color: hsl(var(--ent-hue) 80% 72%); background: hsl(var(--ent-hue) 70% 55% / 0.18); }
    .annuaire-body { flex: 1; min-width: 0; }
    .annuaire-name { font-size: 0.92rem; font-weight: 700; color: var(--text-main); }
    .annuaire-desc {
        margin-top: 1px;
        font-size: 0.8rem;
        color: var(--text-muted);
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .annuaire-meta { display: flex; flex-wrap: wrap; gap: 2px 16px; margin-top: 6px; font-size: 0.75rem; color: var(--text-muted); }
    .annuaire-meta i { font-size: 0.68rem; margin-right: 3px; opacity: 0.8; }
    .annuaire-distance { opacity: 0.8; }
    .annuaire-stats { width: 210px; flex-shrink: 0; display: flex; flex-direction: column; gap: 8px; }
    .annuaire-score { display: flex; align-items: center; gap: 10px; }
    .annuaire-score-value { font-size: 1.15rem; font-weight: 800; line-height: 1; min-width: 34px; }
    .annuaire-score-label { margin-top: 4px; font-size: 0.72rem; color: var(--text-muted); }
    .annuaire-chip {
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
    }
    /* Largeur fixe : la colonne fiabilité reste alignée même sans bouton téléphone / e-mail */
    .annuaire-actions { flex-shrink: 0; display: flex; gap: 6px; justify-content: flex-end; width: 212px; }
    @media (max-width: 1199.98px) { .annuaire-actions { width: 130px; } }

    @media (max-width: 767.98px) {
        .annuaire-row { flex-wrap: wrap; align-items: flex-start; }
        .annuaire-body { flex-basis: calc(100% - 58px); }
        .annuaire-stats { width: 100%; padding-left: 58px; }
        .annuaire-actions { width: 100%; padding-left: 58px; justify-content: flex-start; }
    }
</style>

<script>
function filterEntreprises() {
    const filter = document.getElementById('searchEntreprise').value.trim().toLowerCase();
    let visibles = 0;
    document.querySelectorAll('#listeEntreprises .annuaire-row').forEach(function(row) {
        const ok = (row.dataset.search || '').includes(filter);
        row.hidden = !ok;
        if (ok) visibles++;
    });
    const vide = document.querySelector('#listeEntreprises .annuaire-empty-search');
    if (vide) vide.hidden = visibles > 0;
}
</script>
