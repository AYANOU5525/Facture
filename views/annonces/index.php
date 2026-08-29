<div class="container fade-in">
    <div class="mb-4">
        <h1><i class="fas fa-bullhorn"></i> Annonces & Opportunités</h1>
        <p class="text-body-secondary mb-0">Publiez vos appels d'offres ou propositions de partenariat</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Menu de navigation B2B -->
    <div class="btn-group w-100 mb-3" role="group">
        <a href="reseau_b2b.php" class="btn btn-outline-primary">
            <i class="fas fa-building"></i> Annuaire
        </a>
        <a href="annonces.php" class="btn btn-primary">
            <i class="fas fa-bullhorn"></i> Annonces
        </a>
        <a href="commandes_b2b.php" class="btn btn-outline-primary">
            <i class="fas fa-shopping-cart"></i> Commandes B2B
        </a>
    </div>

    <!-- Note d'information -->
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="fas fa-info-circle mt-1"></i>
        <div><strong>Note :</strong> Pour mettre des produits en vente B2B, utilisez l'option "Déstockage B2B" directement dans la gestion des produits.</div>
    </div>

    <div class="row g-4">
        <!-- Formulaire de publication -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-transparent">
                    <h2 class="h6 mb-0"><i class="fas fa-plus-circle"></i> Publier une annonce</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="ajouter">

                        <div class="mb-3">
                            <label for="type_annonce" class="form-label">Type d'annonce *</label>
                            <select name="type_annonce" id="type_annonce" class="form-select" required>
                                <option value="">-- Sélectionnez --</option>
                                <option value="appel_offre">Appel d'offre (Recherche fournisseur)</option>
                                <option value="partenariat">Recherche de partenariat</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="titre" class="form-label">Titre de l'annonce *</label>
                            <input type="text" name="titre" id="titre" class="form-control"
                                placeholder="Ex: Recherche fournisseur papier A4" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0"><i class="fas fa-list"></i> Annonces actives</h2>
                <form method="GET" style="min-width:200px;">
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tous les types</option>
                        <option value="appel_offre" <?= $type_filtre === 'appel_offre' ? 'selected' : '' ?>>Appel d'offre</option>
                        <option value="partenariat" <?= $type_filtre === 'partenariat' ? 'selected' : '' ?>>Partenariat</option>
                    </select>
                </form>
            </div>

            <?php if (empty($annonces)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Aucune annonce active pour le moment.
                </div>
            <?php else: ?>
                <?php foreach ($annonces as $annonce): ?>
                    <?php $borderClass = $annonce['Type_Annonce'] === 'appel_offre' ? 'border-danger' : 'border-primary'; ?>
                    <div class="card mb-3 border-start border-4 <?= $borderClass ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between text-body-secondary small mb-2">
                                <span class="fw-semibold text-uppercase">
                                    <?php if ($annonce['Type_Annonce'] == 'appel_offre'): ?>
                                        <i class="fas fa-search"></i> Appel d'offre
                                    <?php else: ?>
                                        <i class="fas fa-handshake"></i> Partenariat
                                    <?php endif; ?>
                                </span>
                                <span>
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($annonce['Date_Publication'])) ?>
                                </span>
                            </div>

                            <h3 class="h5"><?= htmlspecialchars($annonce['Titre']) ?></h3>
                            <p class="mb-3"><?= nl2br(htmlspecialchars($annonce['Description'])) ?></p>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <div>
                                    <i class="fas fa-building"></i>
                                    <strong><?= htmlspecialchars($annonce['Nom_Entreprise']) ?></strong>
                                    <?php if ($j_ai_coords && !empty($annonce['Latitude']) && !empty($annonce['Longitude'])): ?>
                                        <?php $dist = calculDistanceHaversine((float)$mon_ent['Latitude'], (float)$mon_ent['Longitude'], (float)$annonce['Latitude'], (float)$annonce['Longitude']); ?>
                                        <span class="badge text-bg-secondary ms-2"><i class="fas fa-route"></i> <?= formaterDistance($dist) ?></span>
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
                                            <a href="tel:<?= htmlspecialchars($annonce['Tel_Entreprise']) ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-phone"></i> Tél
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge text-bg-info">Votre annonce</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>

</html>
