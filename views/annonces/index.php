<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Publiez vos appels d'offres ou propositions de partenariat, et répondez à ceux du réseau</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-check-circle"></i> <div><?= $success ?></div></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-circle"></i> <div><?= $error ?></div></div>
    <?php endif; ?>

    <!-- Navigation B2B -->
    <?php require __DIR__ . '/../partials/b2b_nav.php'; ?>

    <!-- Note d'information -->
    <div class="note-soft mb-3">
        <span class="note-soft-icon"><i class="fas fa-info"></i></span>
        <div>Pour mettre des produits en vente B2B, utilisez l'option « Déstockage B2B » directement dans la <a href="products.php">gestion des produits</a>.</div>
    </div>

    <div class="row g-4">
        <!-- Formulaire de publication -->
        <div class="col-lg-4">
            <div class="panel-soft h-100">
                <div class="panel-soft-head">
                    <div>
                        <h2 class="panel-soft-title">Publier une annonce</h2>
                        <div class="panel-soft-sub">Appel d'offres ou recherche de partenaire</div>
                    </div>
                </div>
                <div class="panel-soft-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="ajouter">

                        <div class="mb-3">
                            <label for="type_annonce" class="form-label small fw-semibold text-body-secondary">Type d'annonce *</label>
                            <select name="type_annonce" id="type_annonce" class="form-select" required>
                                <option value="">-- Sélectionnez --</option>
                                <option value="appel_offre">Appel d'offre (Recherche fournisseur)</option>
                                <option value="partenariat">Recherche de partenariat</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="titre" class="form-label small fw-semibold text-body-secondary">Titre de l'annonce *</label>
                            <input type="text" name="titre" id="titre" class="form-control"
                                placeholder="Ex: Recherche fournisseur papier A4" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label small fw-semibold text-body-secondary">Description *</label>
                            <textarea name="description" id="description" class="form-control" rows="6"
                                placeholder="Décrivez votre besoin en détail..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-paper-plane"></i> Publier l'annonce
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Liste des annonces -->
        <div class="col-lg-8">
            <div class="panel-soft">
                <div class="panel-soft-head">
                    <div>
                        <h2 class="panel-soft-title">Annonces actives</h2>
                        <div class="panel-soft-sub"><?= count($annonces) ?> annonce<?= count($annonces) > 1 ? 's' : '' ?> publiée<?= count($annonces) > 1 ? 's' : '' ?> sur le réseau</div>
                    </div>
                    <form method="GET" style="min-width:190px;">
                        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tous les types</option>
                            <option value="appel_offre" <?= $type_filtre === 'appel_offre' ? 'selected' : '' ?>>Appel d'offre</option>
                            <option value="partenariat" <?= $type_filtre === 'partenariat' ? 'selected' : '' ?>>Partenariat</option>
                        </select>
                    </form>
                </div>

                <?php if (empty($annonces)): ?>
                    <div class="text-center text-body-secondary py-5">
                        <i class="fas fa-bullhorn fs-1 opacity-25 d-block mb-3"></i>
                        <p class="mb-0">Aucune annonce active pour le moment.</p>
                    </div>
                <?php else: ?>
                    <div class="offres-list" data-paginate="5" data-paginate-always>
                        <?php foreach ($annonces as $annonce): ?>
                            <?php
                                $estAppelOffre = $annonce['Type_Annonce'] === 'appel_offre';
                                // Couleur du type (pastille + étiquette) : rouge appel d'offres, bleu partenariat
                                $couleurType = $estAppelOffre ? 'var(--danger)' : 'var(--primary)';
                            ?>
                            <div class="offre-row" style="--offre-color: <?= $couleurType ?>">
                                <span class="offre-icon"><i class="fas <?= $estAppelOffre ? 'fa-magnifying-glass' : 'fa-handshake' ?>"></i></span>
                                <div class="offre-body">
                                    <div class="offre-top">
                                        <span class="offre-type"><?= $estAppelOffre ? "Appel d'offres" : 'Partenariat' ?></span>
                                        <span class="offre-date"><i class="far fa-clock"></i> <?= date('d/m/Y', strtotime($annonce['Date_Publication'])) ?></span>
                                    </div>
                                    <h3 class="offre-title"><?= htmlspecialchars($annonce['Titre']) ?></h3>
                                    <p class="offre-desc"><?= nl2br(htmlspecialchars($annonce['Description'])) ?></p>

                                <div class="offre-foot">
                                    <div class="offre-ent">
                                        <i class="fas fa-building"></i>
                                        <strong><?= htmlspecialchars($annonce['Nom_Entreprise']) ?></strong>
                                        <?php if ($j_ai_coords && $annonce['Id_Entreprise'] !== $mon_entreprise_id && !empty($annonce['Latitude']) && !empty($annonce['Longitude'])): ?>
                                            <?php $dist = calculDistanceHaversine((float)$mon_ent['Latitude'], (float)$mon_ent['Longitude'], (float)$annonce['Latitude'], (float)$annonce['Longitude']); ?>
                                            <span class="offre-distance">· <?= formaterDistance($dist) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($annonce['Id_Entreprise'] !== $mon_entreprise_id): ?>
                                        <div class="d-flex gap-1">
                                            <?php if ($annonce['Email_Entreprise']): ?>
                                                <a href="mailto:<?= htmlspecialchars($annonce['Email_Entreprise']) ?>?subject=Réponse annonce: <?= urlencode($annonce['Titre']) ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-envelope"></i> Email
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($annonce['Tel_Entreprise']): ?>
                                                <a href="tel:<?= htmlspecialchars($annonce['Tel_Entreprise']) ?>" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-phone"></i> Tél
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="chip-soft"><i class="fas fa-user"></i> Votre annonce</span>
                                    <?php endif; ?>
                                </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>

</html>
