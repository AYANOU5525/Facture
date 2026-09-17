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
    <div class="d-flex gap-2 flex-wrap mb-3">
        <a href="reseau_b2b.php" class="btn btn-sm <?= ($current ?? '') === 'reseau_b2b.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-building"></i> Annuaire
        </a>
        <a href="annonces.php" class="btn btn-sm <?= ($current ?? '') === 'annonces.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-bullhorn"></i> Annonces
        </a>
        <a href="commandes_b2b.php" class="btn btn-sm <?= ($current ?? '') === 'commandes_b2b.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-shipping-fast"></i> Commandes
        </a>
        <a href="notifications_b2b.php" class="btn btn-sm <?= ($current ?? '') === 'notifications_b2b.php' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-bell"></i> Notifications
            <?php if (!empty($nb_non_lues)): ?><span class="badge rounded-pill text-bg-danger ms-1"><?= $nb_non_lues ?></span><?php endif; ?>
        </a>
    </div>

    <!-- Note d'information -->
    <div class="alert alert-info d-flex align-items-start gap-2 small">
        <i class="fas fa-info-circle mt-1"></i>
        <div><strong>Note :</strong> pour mettre des produits en vente B2B, utilisez l'option « Déstockage B2B » directement dans la gestion des produits.</div>
    </div>

    <div class="row g-4">
        <!-- Formulaire de publication -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-transparent d-flex align-items-center gap-2">
                    <h2 class="h6 mb-0"><i class="fas fa-plus text-success me-2"></i>Publier une annonce</h2>
                </div>
                <div class="card-body">
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
            <div class="card">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="h6 mb-0"><i class="fas fa-list text-primary me-2"></i>Annonces actives <span class="text-body-secondary fw-normal">(<?= count($annonces) ?>)</span></h2>
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
                    <div class="list-group list-group-flush">
                        <?php foreach ($annonces as $annonce): ?>
                            <?php
                                $estAppelOffre = $annonce['Type_Annonce'] === 'appel_offre';
                                $accentClass = $estAppelOffre ? 'border-danger' : 'border-primary';
                                $textClass = $estAppelOffre ? 'text-danger' : 'text-primary';
                            ?>
                            <div class="list-group-item border-start border-4 <?= $accentClass ?> p-3">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                    <span class="fw-semibold small <?= $textClass ?>">
                                        <?php if ($estAppelOffre): ?>
                                            <i class="fas fa-search"></i> Appel d'offre
                                        <?php else: ?>
                                            <i class="fas fa-handshake"></i> Partenariat
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-body-secondary small"><i class="fas fa-clock"></i> <?= date('d/m/Y', strtotime($annonce['Date_Publication'])) ?></span>
                                </div>

                                <h3 class="h6 fw-bold mb-1"><?= htmlspecialchars($annonce['Titre']) ?></h3>
                                <p class="text-body-secondary mb-3"><?= nl2br(htmlspecialchars($annonce['Description'])) ?></p>

                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                                    <div class="small">
                                        <i class="fas fa-building text-body-secondary"></i>
                                        <strong><?= htmlspecialchars($annonce['Nom_Entreprise']) ?></strong>
                                        <?php if ($j_ai_coords && !empty($annonce['Latitude']) && !empty($annonce['Longitude'])): ?>
                                            <?php $dist = calculDistanceHaversine((float)$mon_ent['Latitude'], (float)$mon_ent['Longitude'], (float)$annonce['Latitude'], (float)$annonce['Longitude']); ?>
                                            <span class="text-body-secondary ms-1"><i class="fas fa-route"></i> <?= formaterDistance($dist) ?></span>
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
                                        <span class="text-body-secondary small fst-italic">Votre annonce</span>
                                    <?php endif; ?>
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
