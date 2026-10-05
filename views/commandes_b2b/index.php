<!-- ============================================================
     VUE — Interface Commandes B2B v2
     ============================================================ -->
<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<script src="../assets/vendor/leaflet/leaflet.js"></script>
<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Gérez vos achats et ventes inter-entreprises</p>
    </div>

    <!-- Alertes -->
    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-start gap-2">
            <i class="fas fa-check-circle mt-1"></i> <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2" style="white-space: pre-line;">
            <i class="fas fa-exclamation-triangle mt-1"></i> <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Navigation B2B -->
    <?php require __DIR__ . '/../partials/b2b_nav.php'; ?>

    <!-- Onglets -->
    <ul class="nav nav-pills b2b-tabs mb-4">
        <li class="nav-item flex-fill">
            <a href="?onglet=recues" class="nav-link b2b-tab <?= $onglet === 'recues' ? 'active' : '' ?>">
                <i class="fas fa-inbox"></i> Reçues
                <span class="tab-subtitle d-block">Je suis vendeur</span>
            </a>
        </li>
        <li class="nav-item flex-fill">
            <a href="?onglet=passees" class="nav-link b2b-tab <?= $onglet === 'passees' ? 'active' : '' ?>">
                <i class="fas fa-shopping-bag"></i> Passées
                <span class="tab-subtitle d-block">Je suis acheteur</span>
            </a>
        </li>
    </ul>

    <div class="row g-4 align-items-start">

        <!-- ────────────────────────────────
             PANNEAU NOUVELLE COMMANDE (Acheteur)
             ──────────────────────────────── -->
        <?php if ($onglet === 'passees'): ?>
            <!-- Côte à côte seulement sur très grand écran : sinon le tableau, à droite, coupait la colonne Actions -->
            <div class="col-xxl-4">
                <div class="panel-soft b2b-form-card">
                    <div class="panel-soft-head">
                        <div>
                            <h2 class="panel-soft-title">Nouvelle commande</h2>
                            <div class="panel-soft-sub">Commandez auprès d'un fournisseur du réseau</div>
                        </div>
                    </div>

                    <!-- Sélecteur fournisseur -->
                    <form method="POST" action="commandes_b2b.php" class="vendeur-selector">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="choisir_vendeur">
                        <label class="form-label mb-2" for="vendeur_id">Fournisseur</label>
                        <div class="d-flex gap-2">
                            <select name="vendeur_id" id="vendeur_id" class="form-control" required>
                                <option value="">— Choisir —</option>
                                <?php foreach ($fournisseurs as $f): ?>
                                    <option value="<?= $f['Id_Entreprise'] ?>"
                                        <?= $selected_vendeur == $f['Id_Entreprise'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['Nom_Entreprise']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">OK</button>
                            <?php if ($selected_vendeur): ?>
                                <form method="POST" action="commandes_b2b.php" class="mb-0">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="reset_vendeur">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Réinitialiser">✕</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Formulaire commande (si fournisseur sélectionné) -->
                    <?php if ($selected_vendeur && !empty($produits_b2b)): ?>
                        <form method="POST" action="commandes_b2b.php?onglet=passees" class="order-form" id="newOrderForm" onsubmit="return validerAvantEnvoi()">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="creer_commande">
                            <input type="hidden" name="id_vendeur" value="<?= $selected_vendeur ?>">

                            <!-- Sélecteur de produit -->
                            <div class="product-picker">
                                <div class="form-group" style="margin-bottom:10px;">
                                    <label for="product-select">Choisir un produit</label>
                                    <select id="product-select" class="form-control form-control-sm" onchange="onProductSelect()">
                                        <option value="">— Sélectionner un produit —</option>
                                        <?php foreach ($produits_b2b as $p): ?>
                                            <option value="<?= $p['Id_Produit'] ?>"
                                                data-nom="<?= htmlspecialchars($p['Nom_Produit'], ENT_QUOTES) ?>"
                                                data-prix="<?= $p['Prix_B2B'] ?>"
                                                data-min="<?= max(1, (int)$p['Quantite_Min_B2B']) ?>">
                                                <?= htmlspecialchars($p['Nom_Produit']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="product-details" class="product-details" style="display:none;">
                                    <div class="detail-row">
                                        <span><i class="fas fa-layer-group"></i> Quantité minimale</span>
                                        <strong id="detail-min">-</strong>
                                    </div>
                                    <div class="detail-row">
                                        <span><i class="fas fa-tag"></i> Prix unitaire</span>
                                        <strong id="detail-prix">-</strong>
                                    </div>
                                    <div class="form-group" style="margin:10px 0;">
                                        <label for="product-qty">Quantité</label>
                                        <input type="number" id="product-qty" class="form-control form-control-sm" min="1" value="1" oninput="updateApercu()">
                                    </div>
                                    <div class="detail-row">
                                        <span>Sous-total</span>
                                        <strong id="detail-total">-</strong>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm btn-block" style="margin-top:10px;" onclick="ajouterAuPanier()">
                                        <i class="fas fa-cart-plus"></i> Ajouter à la commande
                                    </button>
                                </div>
                            </div>

                            <!-- Panier de la commande -->
                            <div id="cart-section" class="cart-section" style="display:none;">
                                <div class="cart-header"><i class="fas fa-shopping-basket"></i> Articles de la commande</div>
                                <ul class="cart-list" id="cart-list"></ul>
                            </div>
                            <div id="cart-hidden-inputs"></div>

                            <!-- Total dynamique -->
                            <div class="order-total-box">
                                <span>Total estimé</span>
                                <strong id="order-total">0 F</strong>
                            </div>

                            <!-- Options commande urgente (Point 1) -->
                            <div class="urgence-section">
                                <label class="toggle-label" for="est_urgente">
                                    <input type="checkbox" name="est_urgente" id="est_urgente"
                                        onchange="toggleUrgence(this)">
                                    <span class="toggle-slider"></span>
                                    <span><i class="fas fa-bolt"></i> Commande urgente</span>
                                </label>
                                <div id="urgence-options" class="urgence-options" style="display:none;">
                                    <label for="delai_minutes" style="font-size:0.85rem; color:var(--text-muted);">Délai de réponse souhaité</label>
                                    <select name="delai_minutes" id="delai_minutes" class="form-control form-control-sm">
                                        <option value="30">30 minutes</option>
                                        <option value="60">1 heure</option>
                                        <option value="120" selected>2 heures (défaut)</option>
                                        <option value="240">4 heures</option>
                                        <option value="480">8 heures</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Mode de retrait (Point 2) -->
                            <div class="retrait-section">
                                <span style="font-weight:600; font-size:0.9rem; display:block; margin-bottom:5px;">Mode de livraison</span>
                                <div class="retrait-options">
                                    <label class="retrait-option" for="mode_retrait_livraison">
                                        <input type="radio" name="mode_retrait" id="mode_retrait_livraison" value="livraison" checked
                                            onchange="toggleAdresseRetrait(false)">
                                        <div class="retrait-card">
                                            <i class="fas fa-truck"></i>
                                            <span>Livraison</span>
                                        </div>
                                    </label>
                                    <label class="retrait-option" for="mode_retrait_retrait_place">
                                        <input type="radio" name="mode_retrait" id="mode_retrait_retrait_place" value="retrait_place"
                                            onchange="toggleAdresseRetrait(true)">
                                        <div class="retrait-card">
                                            <i class="fas fa-store"></i>
                                            <span>Retrait sur place</span>
                                        </div>
                                    </label>
                                </div>
                                <div id="adresse-retrait-group" style="display:none; margin-top:10px;">
                                    <label for="adresse_retrait" class="sr-only">Adresse de retrait</label>
                                    <input type="text" name="adresse_retrait" id="adresse_retrait" class="form-control form-control-sm"
                                        placeholder="Adresse de retrait...">
                                </div>

                                <!-- Point de livraison (mode Livraison uniquement) : l'acheteur précise où le
                                     livreur devra apporter la marchandise, indépendamment de l'adresse de
                                     l'entreprise. Optionnel — à défaut, le livreur utilisera l'adresse de
                                     l'entreprise (voir LogisticsRepository::findForEnterprise). -->
                                <div id="livraison-map-group" style="margin-top:10px;">
                                    <label class="form-label small text-body-secondary mb-1">
                                        <i class="fas fa-map-marker-alt text-danger"></i> Lieu de livraison <span class="text-body-tertiary">(optionnel)</span>
                                    </label>
                                    <div class="input-group input-group-sm mb-2">
                                        <input type="text" id="cmd-address-search" class="form-control" placeholder="Rechercher une adresse...">
                                        <button type="button" class="btn btn-primary" onclick="rechercherLivraisonCmd()"><i class="fas fa-search"></i></button>
                                    </div>
                                    <div id="cmd-livraison-map" style="height:220px; border-radius:8px; border:1px solid var(--bs-border-color);"></div>
                                    <p class="text-body-secondary small mt-1 mb-0">Cliquez sur la carte pour placer le point de livraison.</p>
                                    <input type="hidden" name="adresse_livraison" id="cmd_adresse_livraison">
                                    <input type="hidden" name="lat_livraison" id="cmd_lat_livraison">
                                    <input type="hidden" name="lng_livraison" id="cmd_lng_livraison">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-block" style="margin-top:15px;">
                                <i class="fas fa-paper-plane"></i> Envoyer la commande
                            </button>
                        </form>
                    <?php elseif ($selected_vendeur): ?>
                        <div class="alert alert-info" style="margin-top:15px; font-size:0.9rem;">
                            <i class="fas fa-info-circle"></i>
                            Ce fournisseur n'a aucun produit disponible en B2B actuellement.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ────────────────────────────────
             LISTE DES COMMANDES
             ──────────────────────────────── -->
        <div class="<?= $onglet === 'passees' ? 'col-xxl-8' : 'col-12' ?> min-w-0">
            <?php if (empty($commandes)): ?>
                <div class="panel-soft">
                    <div class="notif-empty py-5">
                        <span class="notif-empty-icon"><i class="fas fa-inbox"></i></span>
                        <span class="fw-semibold"><?= $onglet === 'recues' ? 'Aucune commande reçue' : 'Aucune commande passée' ?></span>
                        <span><?= $onglet === 'recues' ? 'Les commandes de vos clients B2B apparaîtront ici.' : 'Choisissez un fournisseur pour passer votre première commande.' ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="panel-soft">
                    <div class="panel-soft-head">
                        <div>
                            <h2 class="panel-soft-title"><?= $onglet === 'recues' ? 'Commandes reçues' : 'Mes commandes' ?></h2>
                            <div class="panel-soft-sub"><?= count($commandes) ?> commande<?= count($commandes) > 1 ? 's' : '' ?> · <?= $onglet === 'recues' ? 'vous êtes le fournisseur' : 'vous êtes l\'acheteur' ?></div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Commande</th>
                                    <th>Entreprise</th>
                                    <th class="text-end">Montant</th>
                                    <th>Date</th>
                                    <th>Urgence</th>
                                    <th>Statut</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody data-paginate="5">
                                <?php foreach ($commandes as $c):
                                    $est_urgente  = (bool)$c['Est_Urgente'];
                                    $urgent_actif = $est_urgente && in_array($c['Statut'], ['en_attente', 'validee']);
                                    $sec_restants = $est_urgente ? getSecondesRestantes($c['Date_Limite_Reponse'] ?? null) : null;
                                    $mode_retrait = $c['Mode_Retrait'] ?? 'livraison';
                                    $lignes_cmd   = getLignesCommande($pdo, (int)$c['Id_Commande_B2B']);
                                    $historique   = getHistoriqueCommande($pdo, (int)$c['Id_Commande_B2B']);
                                    $detail_id    = 'detail-' . $c['Id_Commande_B2B'];
                                ?>
                                <tr>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-secondary cmd-toggle" data-bs-toggle="collapse"
                                            data-bs-target="#<?= $detail_id ?>" aria-expanded="false" aria-controls="<?= $detail_id ?>"
                                            title="Voir le détail">
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    </td>
                                    <td class="text-nowrap">
                                        <strong><?= htmlspecialchars($c['Numero_Commande']) ?></strong>
                                        <?php if (!empty($c['Numero_Origine'])): ?>
                                            <div class="small text-body-secondary"><i class="fas fa-clock-rotate-left"></i> Reliquat de <?= htmlspecialchars($c['Numero_Origine']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($c['Autre_Partie']) ?>
                                        <?php if ($c['Tel_Entreprise']): ?>
                                            <a href="tel:<?= htmlspecialchars($c['Tel_Entreprise']) ?>" class="ms-1 text-body-secondary" title="Appeler">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-nowrap"><?= number_format((float)$c['Montant_Total'], 0, ',', ' ') ?> F</td>
                                    <td class="small text-body-secondary"><?= date('d/m/y H:i', strtotime($c['Date_Commande'])) ?></td>
                                    <td>
                                        <?php if ($urgent_actif): ?>
                                            <span class="text-danger fw-semibold"><i class="fas fa-bolt"></i> Urgent</span>
                                        <?php else: ?>
                                            <span class="text-body-secondary">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo badgeStatutCommande($c['Statut']); ?></td>
                                    <td class="text-center">
                                        <div class="d-flex flex-wrap justify-content-center gap-1">
                                            <!-- Actions VENDEUR (onglet reçues) -->
                                            <?php if ($onglet === 'recues'): ?>
                                                <?php if ($c['Statut'] === 'en_attente'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Valider cette commande ? Le stock sera automatiquement déduit.')">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="valider">
                                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Valider">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-outline-warning btn-sm" title="Stock insuffisant : proposer une livraison partielle"
                                                        data-bs-toggle="collapse" data-bs-target="#<?= $detail_id ?>">
                                                        <i class="fas fa-balance-scale"></i> Partiel
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" title="Refuser"
                                                        onclick="ouvrirModalRefus(<?= $c['Id_Commande_B2B'] ?>, '<?= htmlspecialchars($c['Numero_Commande']) ?>')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php elseif ($c['Statut'] === 'validee'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="en_preparation">
                                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                        <button type="submit" class="btn btn-purple btn-sm">
                                                            <i class="fas fa-box-open"></i> Préparation
                                                        </button>
                                                    </form>
                                                <?php elseif ($c['Statut'] === 'en_preparation'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="marquer_prete">
                                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                        <button type="submit" class="btn btn-teal btn-sm">
                                                            <i class="fas fa-check-double"></i> Prête
                                                        </button>
                                                    </form>
                                                <?php elseif ($c['Statut'] === 'prete'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Expédier cette commande ? <?= FEATURE_LOGISTIQUE_ACTIVE ? 'Une facture et une expédition logistique seront créées.' : 'Une facture sera créée.' ?>')">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="expedier">
                                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-shipping-fast"></i> Expédier
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <!-- Actions ACHETEUR (onglet passées) -->
                                            <?php else: ?>
                                                <?php if ($c['Statut'] === 'a_confirmer'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Accepter la proposition partielle ? La commande sera validée avec ces quantités.')">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="accepter_proposition">
                                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Prendre les quantités proposées, sans le reste">
                                                            <i class="fas fa-check"></i> Accepter
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Accepter les quantités proposées et demander au fournisseur de compléter le reste plus tard ? Le reste sera enregistré comme une nouvelle commande (reliquat).')">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="accepter_proposition">
                                                        <input type="hidden" name="completer" value="1">
                                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Prendre ce qui est disponible et se faire compléter le reste plus tard">
                                                            <i class="fas fa-clock-rotate-left"></i> Accepter + compléter plus tard
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (in_array($c['Statut'], ['en_attente', 'a_confirmer'], true)): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Annuler cette commande ?')">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="annuler_commande">
                                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Annuler la commande">
                                                            <i class="fas fa-ban"></i> Annuler
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($c['Statut'] === 'expediee'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Confirmer la réception correcte de cette commande ?')">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="livree">
                                                        <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-box-open"></i> Reçue
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (!empty($c['Numero_Facture'])): ?>
                                                <a href="invoice_view.php?ref=<?= urlencode($c['Numero_Facture']) ?>" target="_blank"
                                                   class="btn btn-outline-secondary btn-sm" title="Voir / imprimer la facture <?= htmlspecialchars($c['Numero_Facture']) ?>">
                                                    <i class="fas fa-file-invoice"></i> Facture
                                                </a>
                                            <?php endif; ?>

                                            <button class="btn btn-chat btn-sm" title="Chat"
                                                onclick="ouvrirChat(<?= $c['Id_Commande_B2B'] ?>, '<?= htmlspecialchars($c['Numero_Commande']) ?>')">
                                                <i class="fas fa-comment-dots"></i>
                                                <span class="chat-unread-count" id="chat-count-<?= $c['Id_Commande_B2B'] ?>" style="display:none;"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr data-pg-follow>
                                    <td colspan="8" class="p-0 border-top-0">
                                        <div class="collapse" id="<?= $detail_id ?>">
                                            <div class="commande-detail-panel p-3">
                                                <!-- Compte à rebours urgence (côté vendeur + commandes en attente) -->
                                                <?php if ($est_urgente && $onglet === 'recues' && $c['Statut'] === 'en_attente' && $sec_restants !== null): ?>
                                                    <div class="countdown-bar <?= $sec_restants < 1800 ? 'countdown-critical' : '' ?>"
                                                        data-seconds="<?= max(0, $sec_restants) ?>"
                                                        id="countdown-<?= $c['Id_Commande_B2B'] ?>">
                                                        <i class="fas fa-hourglass-half"></i>
                                                        <span class="countdown-text">Calcul...</span>
                                                        <div class="countdown-progress-wrap">
                                                            <div class="countdown-progress-bar" style="width:100%"></div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <!-- Articles (depuis lignes normalisées) -->
                                                        <div class="commande-articles" data-paginate="5">
                                                            <?php $total_propose = 0; ?>
                                                            <?php foreach ($lignes_cmd as $ligne):
                                                                $proposee = $c['Statut'] === 'a_confirmer' && isset($ligne['quantite_proposee']) ? (int) $ligne['quantite_proposee'] : null;
                                                                $total_propose += $proposee !== null ? $proposee * (float) $ligne['prix'] : 0;
                                                            ?>
                                                                <div class="article-ligne">
                                                                    <?php if ($proposee !== null && $proposee !== (int) $ligne['quantite']): ?>
                                                                        <span class="article-qte"><s class="text-body-secondary"><?= $ligne['quantite'] ?></s> → <?= $proposee ?>×</span>
                                                                    <?php else: ?>
                                                                        <span class="article-qte"><?= $ligne['quantite'] ?>×</span>
                                                                    <?php endif; ?>
                                                                    <span class="article-nom"><?= htmlspecialchars($ligne['nom']) ?></span>
                                                                    <span class="article-prix"><?= number_format($proposee !== null ? $proposee * (float) $ligne['prix'] : (float) $ligne['sous_total'], 0, ',', ' ') ?> F</span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if ($c['Statut'] === 'a_confirmer'): ?>
                                                                <div class="alert alert-warning small mt-2 mb-0" data-pg-ignore>
                                                                    <i class="fas fa-balance-scale"></i>
                                                                    Proposition partielle du vendeur — nouveau total :
                                                                    <strong><?= number_format($total_propose, 0, ',', ' ') ?> F</strong>
                                                                    (au lieu de <?= number_format((float) $c['Montant_Total'], 0, ',', ' ') ?> F).
                                                                    <?= $onglet === 'recues' ? "En attente de la réponse de l'acheteur." : 'Acceptez ou annulez la commande.' ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if ($onglet === 'recues' && $c['Statut'] === 'en_attente'): ?>
                                                            <!-- Livraison partielle : le vendeur propose les quantités disponibles -->
                                                            <form method="POST" class="partiel-form border rounded p-2 mb-2"
                                                                  onsubmit="return confirm('Envoyer cette proposition à l\'acheteur ? Le stock proposé sera réservé en attendant sa réponse.')">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="action" value="proposer_partiel">
                                                                <input type="hidden" name="id_commande" value="<?= $c['Id_Commande_B2B'] ?>">
                                                                <div class="small fw-semibold mb-1"><i class="fas fa-balance-scale"></i> Stock insuffisant ? Proposer une livraison partielle</div>
                                                                <?php foreach ($lignes_cmd as $ligne):
                                                                    if (empty($ligne['Id_Ligne'])) { continue; }
                                                                    $dispo = max(0, (int) ($ligne['stock_vendeur'] ?? 0));
                                                                ?>
                                                                    <div class="d-flex align-items-center gap-2 small mb-1">
                                                                        <span class="flex-grow-1"><?= htmlspecialchars($ligne['nom']) ?>
                                                                            <span class="text-body-secondary">— commandé <?= (int) $ligne['quantite'] ?>, en stock <?= $dispo ?></span></span>
                                                                        <input type="number" name="quantites[<?= (int) $ligne['Id_Ligne'] ?>]" class="form-control form-control-sm" style="width:80px"
                                                                               min="0" max="<?= min((int) $ligne['quantite'], $dispo) ?>" value="<?= min((int) $ligne['quantite'], $dispo) ?>"
                                                                               aria-label="Quantité proposée pour <?= htmlspecialchars($ligne['nom']) ?>">
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <input type="text" name="message" class="form-control form-control-sm mb-1" maxlength="255" placeholder="Message pour l'acheteur (facultatif)">
                                                                <button type="submit" class="btn btn-outline-warning btn-sm w-100"><i class="fas fa-paper-plane"></i> Envoyer la proposition</button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <span class="mode-retrait-chip">
                                                            <?php if ($mode_retrait === 'retrait_place'): ?>
                                                                <i class="fas fa-store"></i> Retrait sur place
                                                            <?php else: ?>
                                                                <i class="fas fa-truck"></i> Livraison
                                                            <?php endif; ?>
                                                        </span>

                                                        <?php if ($mode_retrait === 'retrait_place' && !empty($c['Adresse_Retrait'])): ?>
                                                            <div class="retrait-info mt-2">
                                                                <i class="fas fa-map-marker-alt"></i>
                                                                Retrait : <?= htmlspecialchars($c['Adresse_Retrait']) ?>
                                                            </div>
                                                        <?php elseif ($mode_retrait === 'livraison' && !empty($c['Adresse_Livraison'])): ?>
                                                            <div class="retrait-info mt-2">
                                                                <i class="fas fa-map-marker-alt text-danger"></i>
                                                                Livraison : <?= htmlspecialchars($c['Adresse_Livraison']) ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($c['Message_Validation'])): ?>
                                                            <div class="validation-message mt-2">
                                                                <i class="fas fa-comment-alt"></i>
                                                                <?= htmlspecialchars($c['Message_Validation']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <!-- Timeline de la commande (v3) -->
                                                        <div class="commande-timeline">
                                                            <?php
                                                            $etapes_timeline = getTimelineSteps($c['Statut']);
                                                            foreach ($etapes_timeline as $etape):
                                                                $classe = ($etape['etat'] === 'done') ? 'done' : (($etape['etat'] === 'active') ? 'current' : (($etape['etat'] === 'error') ? 'error' : 'pending'));
                                                            ?>
                                                                <div class="timeline-step <?= $classe ?>" title="<?= htmlspecialchars($etape['desc'] ?? '') ?>">
                                                                    <div class="timeline-dot">
                                                                        <i class="fas <?= htmlspecialchars($etape['icon']) ?>"></i>
                                                                    </div>
                                                                    <span><?= htmlspecialchars($etape['label']) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>

                                                        <!-- Historique de la commande (v3) -->
                                                        <?php if (!empty($historique)): ?>
                                                            <div class="commande-history">
                                                                <button type="button" class="history-title" onclick="toggleHistory(<?= $c['Id_Commande_B2B'] ?>)" aria-expanded="false">
                                                                    <i class="fas fa-history"></i> Historique des statuts
                                                                    <i class="fas fa-chevron-down history-chevron" id="history-chevron-<?= $c['Id_Commande_B2B'] ?>"></i>
                                                                </button>
                                                                <!-- Conteneur replié/déplié (et non la liste elle-même) : la pagination se replie avec l'historique -->
                                                                <div id="history-list-<?= $c['Id_Commande_B2B'] ?>" style="display:none;">
                                                                <ul class="history-list" data-paginate="5">
                                                                    <?php foreach ($historique as $h): ?>
                                                                        <li>
                                                                            <span class="history-date"><?= date('d/m H:i', strtotime($h['Date_Changement'])) ?></span>
                                                                            <span class="history-badge badge-small"><?= htmlspecialchars(getLabelStatut($h['Nouveau_Statut'])) ?></span>
                                                                            <?php if ($h['Nom_Entreprise']): ?>
                                                                                <span class="history-actor">par <strong><?= htmlspecialchars($h['Nom_Entreprise']) ?></strong></span>
                                                                            <?php endif; ?>
                                                                            <?php if ($h['Note']): ?>
                                                                                <div class="history-note">« <?= htmlspecialchars($h['Note']) ?> »</div>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL DE REFUS
     ============================================================ -->
<div id="modal-refus" class="modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title fs-5"><i class="fas fa-times-circle text-danger"></i> Refuser la commande</h3>
            <button type="button" class="btn-close" onclick="fermerModalRefus()" aria-label="Fermer"></button>
        </div>
        <form method="POST" id="form-refus">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="refuser">
            <input type="hidden" name="id_commande" id="refus-id-commande">
            <div class="modal-body">
                <p id="refus-num-label" class="text-body-secondary mb-3"></p>
                <label class="form-label">Motif du refus *</label>
                <textarea name="motif_refus" class="form-control" rows="3"
                    placeholder="Ex : Rupture de stock temporaire, quantité non disponible..."
                    required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fermerModalRefus()">Annuler</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> Confirmer le refus
                </button>
            </div>
        </form>
    </div>
    </div>
</div>

<!-- ============================================================
     PANNEAU CHAT B2B (Point 7)
     ============================================================ -->
<div id="chat-panel" class="chat-panel" style="display:none;">
    <div class="chat-header">
        <div class="chat-title">
            <div class="chat-avatar"><i class="fas fa-comments"></i></div>
            <div>
                <strong>Discussion commande</strong>
                <span class="chat-command-ref">N° <span id="chat-commande-label">-</span></span>
            </div>
        </div>
        <div class="chat-header-actions">
            <span class="chat-status" id="chat-status"><span class="chat-status-dot"></span> En ligne</span>
            <button type="button" onclick="fermerChat()" class="chat-close-btn" aria-label="Fermer le chat" title="Fermer le chat">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="chat-messages" id="chat-messages">
        <div class="chat-loading">
            <i class="fas fa-spinner fa-spin"></i> Chargement...
        </div>
    </div>

    <!-- Barre de saisie -->
    <div class="chat-input-area">
        <div class="chat-compose-label">Votre message</div>
        <!-- Sélecteur de type de message -->
        <div class="chat-type-selector">
            <button type="button" class="chat-type-btn active" data-type="texte" aria-pressed="true" onclick="setTypeMessage(this, 'texte')" title="Message texte">
                <span>Message</span>
            </button>
            <button type="button" class="chat-type-btn chat-advanced-type" data-type="negociation_qte" aria-pressed="false" onclick="setTypeMessage(this, 'negociation_qte')" title="Négocier quantité">
                <span>Quantité</span>
            </button>
            <button type="button" class="chat-type-btn chat-advanced-type" data-type="negociation_delai" aria-pressed="false" onclick="setTypeMessage(this, 'negociation_delai')" title="Négocier délai">
                <span>Délai</span>
            </button>
            <button type="button" class="chat-type-btn chat-advanced-type" data-type="confirmation_dispo" aria-pressed="false" onclick="setTypeMessage(this, 'confirmation_dispo')" title="Confirmer disponibilité">
                <span>Disponibilité</span>
            </button>
            <button type="button" class="chat-more-btn" onclick="toggleChatOptions(this)">
                <span>Plus</span>
            </button>
            <label class="chat-type-btn" data-type="fichier" aria-pressed="false" onclick="setTypeMessage(this, 'fichier')" title="Joindre un fichier" style="cursor:pointer; margin:0;">
                <span>Fichier</span>
                <input type="file" id="chat-file-input" style="display:none;"
                    accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.docx,.doc"
                    onchange="previewFile(this)">
            </label>
        </div>

        <!-- Prévisualisation fichier -->
        <div id="file-preview" class="file-preview" style="display:none;">
            <i class="fas fa-file"></i>
            <span id="file-preview-name"></span>
            <button type="button" onclick="clearFile()" style="background:none; border:none; cursor:pointer; color:var(--danger);">✕</button>
        </div>

        <div class="chat-input-row">
            <textarea id="chat-input" class="chat-textarea"
                placeholder="Votre message..."
                aria-label="Votre message"
                onkeydown="handleChatKeydown(event)"
                rows="1"></textarea>
            <button type="button" onclick="envoyerMessage()" class="btn btn-primary chat-send-btn">
                <i class="fas fa-paper-plane"></i><span>Envoyer</span>
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
    // ── Variables globales ──
    let chatCommandeId = null;
    let chatLastMsgId = 0;
    let chatPollingTimer = null;
    let chatTypeMessage = 'texte';
    const MON_ENTREPRISE_ID = <?= $mon_entreprise_id ?>;

    // ── Sélecteur de produit + panier de la commande ──
    let panier = {}; // { id: { nom, qte, prix } }

    function formaterF(montant) {
        return new Intl.NumberFormat('fr-FR').format(montant) + ' F';
    }

    function onProductSelect() {
        const select = document.getElementById('product-select');
        const details = document.getElementById('product-details');
        const opt = select.options[select.selectedIndex];

        if (!select.value) {
            details.style.display = 'none';
            return;
        }

        const min = parseInt(opt.dataset.min) || 1;
        const prix = parseFloat(opt.dataset.prix) || 0;

        document.getElementById('detail-min').textContent = min;
        document.getElementById('detail-prix').textContent = formaterF(prix);

        // Pas de plafond : le stock du vendeur n'est pas visible et l'acheteur commande
        // librement ; le vendeur signalera un éventuel manque (proposition partielle).
        const qtyInput = document.getElementById('product-qty');
        qtyInput.min = min;
        qtyInput.removeAttribute('max');
        qtyInput.value = min;

        details.style.display = 'block';
        updateApercu();
    }

    function updateApercu() {
        const select = document.getElementById('product-select');
        const opt = select.options[select.selectedIndex];
        if (!select.value) return;

        const prix = parseFloat(opt.dataset.prix) || 0;
        const qte = parseInt(document.getElementById('product-qty').value) || 0;
        document.getElementById('detail-total').textContent = formaterF(qte * prix);
    }

    function ajouterAuPanier() {
        const select = document.getElementById('product-select');
        const opt = select.options[select.selectedIndex];
        if (!select.value) return;

        const id = select.value;
        const min = parseInt(opt.dataset.min) || 1;
        const prix = parseFloat(opt.dataset.prix) || 0;
        const qte = parseInt(document.getElementById('product-qty').value) || 0;

        if (qte < min) {
            alert(`Quantité minimale requise : ${min}`);
            return;
        }

        panier[id] = { nom: opt.dataset.nom, qte, prix };
        renderPanier();

        // Réinitialiser le sélecteur pour le prochain produit
        select.value = '';
        document.getElementById('product-details').style.display = 'none';
    }

    function retirerDuPanier(id) {
        delete panier[id];
        renderPanier();
    }

    function renderPanier() {
        const ids = Object.keys(panier);
        const cartSection = document.getElementById('cart-section');
        const list = document.getElementById('cart-list');
        const hiddenWrap = document.getElementById('cart-hidden-inputs');

        cartSection.style.display = ids.length ? 'block' : 'none';
        list.innerHTML = '';
        hiddenWrap.innerHTML = '';

        let total = 0;
        ids.forEach(id => {
            const item = panier[id];
            const sousTotal = item.qte * item.prix;
            total += sousTotal;

            const li = document.createElement('li');
            li.className = 'cart-item';
            li.innerHTML = `
                <span class="cart-item-name">${item.qte} × ${escapeHtml(item.nom)}</span>
                <span class="cart-item-total">${formaterF(sousTotal)}</span>
                <button type="button" class="cart-item-remove" onclick="retirerDuPanier('${id}')" aria-label="Retirer">✕</button>
            `;
            list.appendChild(li);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `items[${id}]`;
            input.value = item.qte;
            hiddenWrap.appendChild(input);
        });

        const el = document.getElementById('order-total');
        if (el) el.textContent = formaterF(total);
    }

    function validerAvantEnvoi() {
        if (Object.keys(panier).length === 0) {
            alert('Ajoutez au moins un produit à la commande.');
            return false;
        }
        return true;
    }

    // ── Urgence ──
    function toggleUrgence(checkbox) {
        const opts = document.getElementById('urgence-options');
        if (opts) opts.style.display = checkbox.checked ? 'block' : 'none';
    }

    // ── Mode retrait ──
    function toggleAdresseRetrait(show) {
        const group = document.getElementById('adresse-retrait-group');
        if (group) group.style.display = show ? 'block' : 'none';

        const mapGroup = document.getElementById('livraison-map-group');
        if (mapGroup) mapGroup.style.display = show ? 'none' : 'block';
        if (!show) {
            setTimeout(initCmdLivraisonMap, 0); // le conteneur doit être visible avant Leaflet.map()
        }
    }

    // ── Carte de sélection du point de livraison (formulaire "Nouvelle commande") ──
    let cmdLivraisonMap = null;
    let cmdLivraisonMarker = null;

    function initCmdLivraisonMap() {
        if (cmdLivraisonMap) {
            cmdLivraisonMap.invalidateSize();
            return;
        }
        const defaultLat = 6.1372, defaultLng = 1.2125; // Lomé, même valeur par défaut que le suivi logistique
        cmdLivraisonMap = L.map('cmd-livraison-map').setView([defaultLat, defaultLng], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(cmdLivraisonMap);

        cmdLivraisonMap.on('click', function(e) {
            placerPointLivraisonCmd(e.latlng.lat, e.latlng.lng);
            reverseGeocodeLivraisonCmd(e.latlng.lat, e.latlng.lng);
        });
    }

    function placerPointLivraisonCmd(lat, lng) {
        if (cmdLivraisonMarker) cmdLivraisonMap.removeLayer(cmdLivraisonMarker);
        cmdLivraisonMarker = L.marker([lat, lng], { draggable: true }).addTo(cmdLivraisonMap);
        document.getElementById('cmd_lat_livraison').value = lat;
        document.getElementById('cmd_lng_livraison').value = lng;

        cmdLivraisonMarker.on('dragend', function() {
            const pos = cmdLivraisonMarker.getLatLng();
            document.getElementById('cmd_lat_livraison').value = pos.lat;
            document.getElementById('cmd_lng_livraison').value = pos.lng;
            reverseGeocodeLivraisonCmd(pos.lat, pos.lng);
        });
    }

    async function reverseGeocodeLivraisonCmd(lat, lng) {
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`);
            const data = await response.json();
            if (data && data.display_name) {
                document.getElementById('cmd_adresse_livraison').value = data.display_name;
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function rechercherLivraisonCmd() {
        const query = document.getElementById('cmd-address-search').value.trim();
        if (!query) return;
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`);
            const results = await response.json();
            if (results && results.length > 0) {
                const res = results[0];
                const lat = parseFloat(res.lat), lng = parseFloat(res.lon);
                document.getElementById('cmd_adresse_livraison').value = res.display_name;
                cmdLivraisonMap.setView([lat, lng], 15);
                placerPointLivraisonCmd(lat, lng);
            } else {
                alert('Adresse non trouvée.');
            }
        } catch (e) {
            console.error(e);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('cmd-livraison-map')) {
            initCmdLivraisonMap();
        }
    });

    // ── Compte à rebours urgence ──
    function initCountdowns() {
        document.querySelectorAll('.countdown-bar[data-seconds]').forEach(bar => {
            const initialSecs = parseInt(bar.dataset.seconds) || 0;
            const totalMinutes = <?= max(30, 120) ?>;
            const totalSecs = totalMinutes * 60;
            let secsLeft = initialSecs;

            function updateDisplay() {
                const textEl = bar.querySelector('.countdown-text');
                const progEl = bar.querySelector('.countdown-progress-bar');

                if (secsLeft <= 0) {
                    if (textEl) textEl.textContent = 'Délai dépassé !';
                    if (progEl) progEl.style.width = '0%';
                    bar.classList.add('countdown-critical');
                    return;
                }

                const h = Math.floor(secsLeft / 3600);
                const m = Math.floor((secsLeft % 3600) / 60);
                const s = secsLeft % 60;
                const label = h > 0 ?
                    `${h}h ${m.toString().padStart(2,'0')}min restant` :
                    `${m}min ${s.toString().padStart(2,'0')}s restant`;

                if (textEl) textEl.textContent = label;
                if (progEl) progEl.style.width = Math.max(0, (secsLeft / totalSecs) * 100) + '%';

                if (secsLeft < 1800) bar.classList.add('countdown-critical');
            }

            updateDisplay();
            setInterval(() => {
                secsLeft--;
                updateDisplay();
            }, 1000);
        });
    }

    // ── Modal de refus ──
    function ouvrirModalRefus(idCommande, numCommande) {
        document.getElementById('refus-id-commande').value = idCommande;
        document.getElementById('refus-num-label').textContent = `Commande : ${numCommande}`;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-refus')).show();
    }

    function fermerModalRefus() {
        const instance = bootstrap.Modal.getInstance(document.getElementById('modal-refus'));
        if (instance) instance.hide();
    }

    // ── CHAT ──
    function ouvrirChat(commandeId, commandeNum) {
        chatCommandeId = commandeId;
        chatLastMsgId = 0;
        document.getElementById('chat-commande-label').textContent = commandeNum;
        document.getElementById('chat-panel').style.display = 'flex';
        document.getElementById('chat-messages').innerHTML = '<div class="chat-loading"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
        chargerMessages();
        if (chatPollingTimer) clearInterval(chatPollingTimer);
        chatPollingTimer = setInterval(chargerMessages, 5000); // polling 5s
    }

    function fermerChat() {
        document.getElementById('chat-panel').style.display = 'none';
        if (chatPollingTimer) clearInterval(chatPollingTimer);
        chatPollingTimer = null;
        chatCommandeId = null;
    }

    async function chargerMessages() {
        if (!chatCommandeId) return;
        try {
            const url = `../api/chat_b2b.php?action=get_messages&commande_id=${chatCommandeId}&since_id=${chatLastMsgId}`;
            const resp = await fetch(url);
            const data = await resp.json();

            if (data.success && data.messages.length > 0) {
                const container = document.getElementById('chat-messages');
                // Vider si premier chargement
                if (chatLastMsgId === 0) container.innerHTML = '';

                data.messages.forEach(msg => {
                    container.appendChild(creerBulleMessage(msg));
                    chatLastMsgId = Math.max(chatLastMsgId, msg.Id_Message);
                });
                container.scrollTop = container.scrollHeight;
            } else if (chatLastMsgId === 0) {
                document.getElementById('chat-messages').innerHTML =
                    '<div class="chat-empty"><i class="fas fa-comment-slash"></i><br>Aucun message. Démarrez la discussion !</div>';
            }
            document.getElementById('chat-status').textContent = 'En ligne';
        } catch (e) {
            document.getElementById('chat-status').textContent = 'Hors ligne';
        }
    }

    function creerBulleMessage(msg) {
        const div = document.createElement('div');
        const estMoi = parseInt(msg.Est_Moi) === 1;
        div.className = 'chat-bubble ' + (estMoi ? 'bubble-moi' : 'bubble-autre');

        const typeLabels = {
            'negociation_qte': '<i class="fas fa-scale-balanced me-1"></i> Négociation quantité',
            'negociation_delai': '<i class="far fa-calendar me-1"></i> Négociation délai',
            'confirmation_dispo': '<i class="fas fa-circle-check me-1"></i> Confirmation disponibilité',
            'fichier': '<i class="fas fa-paperclip me-1"></i> Fichier joint',
            'texte': null,
        };
        const typeLabel = typeLabels[msg.Type_Message];

        let contenu = '';
        if (typeLabel) {
            contenu += `<div class="chat-msg-type">${typeLabel}</div>`;
        }
        if (msg.Message) {
            contenu += `<div class="chat-msg-text">${escapeHtml(msg.Message)}</div>`;
        }
        if (msg.Fichier_Path && msg.Fichier_Nom) {
            contenu += `<a href="../${escapeHtml(msg.Fichier_Path)}" target="_blank" class="chat-file-link">
                        <i class="fas fa-file-download"></i> ${escapeHtml(msg.Fichier_Nom)}
                    </a>`;
        }

        const lu = estMoi ?
            (msg.Est_Lu_Vendeur && msg.Est_Lu_Acheteur ? '✓✓' : '✓') :
            '';

        div.innerHTML = `
        ${!estMoi ? `<div class="bubble-auteur">${escapeHtml(msg.Nom_Emetteur)}</div>` : ''}
        <div class="bubble-content">${contenu}</div>
        <div class="bubble-meta">
            <span title="${escapeHtml(msg.Date_Tooltip)}">${escapeHtml(msg.Date_Affichage)}</span>
            ${lu ? `<span class="bubble-lu">${lu}</span>` : ''}
        </div>
    `;
        return div;
    }

    async function envoyerMessage() {
        if (!chatCommandeId) return;
        const input = document.getElementById('chat-input');
        const texte = input.value.trim();
        const fichierInput = document.getElementById('chat-file-input');

        if (!texte && chatTypeMessage !== 'fichier') return;
        if (chatTypeMessage === 'fichier' && !fichierInput.files.length) {
            alert('Veuillez sélectionner un fichier.');
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', '<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>');
        formData.append('action', 'send');
        formData.append('commande_id', chatCommandeId);
        formData.append('message', texte);
        formData.append('type_message', chatTypeMessage);
        if (fichierInput.files.length) {
            formData.append('fichier', fichierInput.files[0]);
        }

        try {
            const resp = await fetch('../api/chat_b2b.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();
            if (data.success) {
                input.value = '';
                clearFile();
                setTypeMessage(document.querySelector('.chat-type-btn[data-type="texte"]'), 'texte');
                await chargerMessages();
            } else {
                alert('Erreur : ' + (data.error || 'Inconnu'));
            }
        } catch (e) {
            alert('Erreur réseau. Réessayez.');
        }
    }

    function handleChatKeydown(e) {
        // Ctrl+Entrée ou Entrée seul (pas Shift+Entrée) pour envoyer
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            envoyerMessage();
        }
    }

    function setTypeMessage(btn, type) {
        chatTypeMessage = type;
        document.querySelectorAll('.chat-type-btn').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        if (btn) {
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
        }

        const typeLabels = {
            texte: 'Message texte',
            negociation_qte: 'Négociation de quantité',
            negociation_delai: 'Négociation de délai',
            confirmation_dispo: 'Confirmation de disponibilité',
            fichier: 'Fichier joint'
        };
        const composeLabel = document.querySelector('.chat-compose-label');
        if (composeLabel) composeLabel.textContent = typeLabels[type] || 'Votre message';

        // Si fichier, déclencher le sélecteur
        if (type === 'fichier') {
            document.getElementById('chat-file-input').click();
        }
    }

    function toggleChatOptions(btn) {
        const selector = btn.closest('.chat-type-selector');
        if (!selector) return;
        const expanded = selector.classList.toggle('show-advanced');
        btn.classList.toggle('active', expanded);
        btn.querySelector('span').textContent = expanded ? 'Moins' : 'Plus';
    }

    function previewFile(input) {
        if (input.files.length) {
            const name = input.files[0].name;
            document.getElementById('file-preview-name').textContent = name;
            document.getElementById('file-preview').style.display = 'flex';
            setTypeMessage(document.querySelector('.chat-type-btn[data-type="fichier"]'), 'fichier');
        }
    }

    function clearFile() {
        document.getElementById('chat-file-input').value = '';
        document.getElementById('file-preview').style.display = 'none';
        setTypeMessage(document.querySelector('.chat-type-btn[data-type="texte"]'), 'texte');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Toggle Historique (v3) ──
    function toggleHistory(id) {
        const list = document.getElementById('history-list-' + id);
        const chevron = document.getElementById('history-chevron-' + id);
        if (list && chevron) {
            const isHidden = list.style.display === 'none';
            list.style.display = isHidden ? 'block' : 'none';
            chevron.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    // ── Ouverture directe du chat depuis une notification (?open_chat=ID&num=NUM) ──
    function ouvrirChatDepuisNotification() {
        const params = new URLSearchParams(window.location.search);
        const idCommande = parseInt(params.get('open_chat'));
        if (!idCommande) return;

        ouvrirChat(idCommande, params.get('num') || '');

        // Nettoie l'URL pour ne pas rouvrir le chat à chaque rafraîchissement de la page.
        params.delete('open_chat');
        params.delete('num');
        const query = params.toString();
        window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));
    }

    // ── Ouverture directe d'une commande depuis une notification (?ouvrir=ID) ──
    function ouvrirCommandeDepuisNotification() {
        const params = new URLSearchParams(window.location.search);
        const id = parseInt(params.get('ouvrir'));
        if (!id) return;
        const panel = document.getElementById('detail-' + id);
        if (panel) {
            if (window.Paginator) Paginator.showItem(panel);
            bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).show();
            panel.closest('tr').previousElementSibling?.classList.add('table-active');
            setTimeout(() => panel.closest('tr').previousElementSibling?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 150);
        }
        params.delete('ouvrir');
        const query = params.toString();
        window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));
    }

    // ── Init ──
    document.addEventListener('DOMContentLoaded', function() {
        initCountdowns();
        ouvrirChatDepuisNotification();
        ouvrirCommandeDepuisNotification();
    });
</script>

<?php
// ============================================================
// STYLES INTÉGRÉS (spécifiques à cette page)
// ============================================================
?>
<style>
    .btn-purple {
        background-color: #7c3aed !important; /* #8b5cf6 : contraste insuffisant avec le texte blanc */
        color: white !important;
    }

    .btn-purple:hover {
        background-color: #6d28d9 !important;
    }

    .btn-teal {
        background-color: #0f766e !important; /* #0d9488 : contraste insuffisant avec le texte blanc */
        color: white !important;
    }

    .btn-teal:hover {
        background-color: #115e59 !important;
    }

    /* ── Historique B2B v3 ── */
    .commande-history {
        background: var(--zinc-50);
        border-top: 1px solid var(--zinc-100);
        padding: 10px 18px;
        font-size: 0.82rem;
    }

    .history-title {
        color: var(--text-muted);
        font-weight: 600;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        user-select: none;
        padding: 4px 0;
    }

    .history-title:hover {
        color: var(--primary);
    }

    .history-chevron {
        transition: transform 0.2s ease;
    }

    .history-list {
        list-style: none;
        padding: 8px 0 0 0;
        margin: 0;
        border-top: 1px dashed var(--zinc-200);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .history-list li {
        position: relative;
        padding-left: 15px;
        line-height: 1.4;
        color: var(--text-main);
    }

    .history-list li::before {
        content: '•';
        position: absolute;
        left: 2px;
        color: var(--primary);
        font-weight: bold;
    }

    .history-date {
        color: var(--text-muted);
        font-size: 0.75rem;
        margin-right: 6px;
    }

    .history-badge {
        font-weight: 600;
        color: var(--text-main);
    }

    .history-actor {
        color: var(--text-muted);
        font-size: 0.78rem;
    }

    .history-note {
        margin-top: 2px;
        font-style: italic;
        color: var(--text-muted);
        padding-left: 8px;
        border-left: 2px solid var(--zinc-200);
    }

    .badge-small {
        font-size: 0.75rem;
        padding: 1px 6px;
        border-radius: 4px;
        background: var(--zinc-200);
        color: var(--text-main);
    }

    /* ── Onglets B2B ── */
    .b2b-tabs {
        display: flex;
        gap: 4px;
        background: var(--bs-secondary-bg);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 5px;
        margin-bottom: 25px;
    }

    .b2b-tab {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12px 20px;
        border-radius: 9px;
        text-decoration: none;
        color: var(--text-muted);
        font-weight: 500;
        transition: all 0.2s ease;
        font-size: 0.95rem;
    }

    .b2b-tab .tab-subtitle {
        font-size: 0.75rem;
        opacity: 0.7;
        font-weight: 400;
        margin-top: 2px;
    }

    .b2b-tabs .b2b-tab.active {
        background: var(--bg-card);
        color: var(--primary);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
    }
    [data-bs-theme="dark"] .b2b-tabs .b2b-tab.active { background: #1e2236; color: #b4c3ff; }

    .b2b-tab:hover:not(.active) {
        background: var(--zinc-200);
    }

    /* ── Panneau de détail (ligne dépliable) ── */
    .commande-detail-panel {
        background: var(--zinc-50);
        border-top: 1px solid var(--zinc-100);
    }

    .cmd-toggle i {
        transition: transform 0.15s ease;
    }

    .cmd-toggle[aria-expanded="true"] i {
        transform: rotate(180deg);
    }

    /* ── Articles ── */
    .commande-articles {
        margin-bottom: 10px;
    }

    .article-ligne {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 3px 0;
        font-size: 0.875rem;
        border-bottom: 1px solid var(--zinc-100);
    }

    .article-ligne:last-child {
        border-bottom: none;
    }

    .article-qte {
        font-weight: 700;
        color: var(--primary);
        min-width: 30px;
    }

    .article-nom {
        flex: 1;
        color: var(--text-muted);
    }

    .article-prix {
        font-weight: 600;
        color: var(--text-main);
    }

    .retrait-info {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-top: 6px;
        padding: 5px 10px;
        background: var(--zinc-50);
        border-radius: 6px;
    }

    .validation-message {
        font-size: 0.82rem;
        color: var(--text-muted);
        font-style: italic;
        margin-top: 8px;
        padding: 6px 10px;
        background: var(--info-bg);
        border-radius: 6px;
        border-left: 3px solid var(--info);
    }

    /* ── Mode retrait chip ── */
    .mode-retrait-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.78rem;
        padding: 2px 8px;
        background: var(--zinc-100);
        border-radius: 20px;
        color: var(--text-muted);
    }

    /* ── Timeline ── */
    .commande-timeline {
        display: flex;
        justify-content: space-between;
        padding: 12px 18px;
        background: var(--zinc-50);
        border-top: 1px solid var(--zinc-100);
        border-bottom: 1px solid var(--zinc-100);
        position: relative;
    }

    .commande-timeline::before {
        content: '';
        position: absolute;
        top: 24px;
        left: 10%;
        right: 10%;
        height: 2px;
        background: var(--zinc-200);
        z-index: 0;
    }

    .timeline-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        z-index: 1;
        position: relative;
        font-size: 0.7rem;
        color: var(--text-muted);
    }

    .timeline-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        background: var(--bg-card);
        border: 2px solid var(--zinc-200);
        transition: all 0.3s ease;
    }

    .timeline-step.done .timeline-dot {
        background: var(--success);
        border-color: var(--success);
        color: white;
    }

    .timeline-step.current .timeline-dot {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
        box-shadow: 0 0 0 3px rgba(0, 70, 255, 0.15);
    }

    .timeline-step.done {
        color: var(--success);
    }

    .timeline-step.current {
        color: var(--primary);
        font-weight: 600;
    }

    .btn-chat {
        background: #4338ca !important;
        color: white !important;
        border: none !important;
        position: relative;
    }

    .btn-chat:hover {
        opacity: 0.9;
    }

    .chat-unread-count {
        position: absolute;
        top: -6px;
        right: -6px;
        background: var(--danger);
        color: white;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        font-size: 0.65rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* ── Compte à rebours ── */
    .countdown-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 18px;
        background: var(--bs-warning-bg-subtle);
        border-bottom: 1px solid var(--bs-warning-border-subtle);
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--bs-warning-text-emphasis);
    }

    .countdown-bar.countdown-critical {
        background: var(--bs-danger-bg-subtle);
        border-color: var(--bs-danger-border-subtle);
        color: var(--bs-danger-text-emphasis);
        animation: pulse-danger 1s ease-in-out infinite;
    }

    .countdown-progress-wrap {
        flex: 1;
        height: 6px;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 3px;
        overflow: hidden;
    }

    .countdown-progress-bar {
        height: 100%;
        background: #ffc107;
        border-radius: 3px;
        transition: width 1s linear;
    }

    .countdown-critical .countdown-progress-bar {
        background: #e74c3c;
    }

    /* ── Formulaire commande ── */
    .b2b-form-card { padding: 0; }

    .vendeur-selector {
        padding: 15px 18px;
        border-bottom: 1px solid var(--zinc-100);
    }

    .order-form {
        padding: 15px 18px;
    }

    /* ── Sélecteur de produit (nouvelle UX) ── */
    .product-picker {
        margin-bottom: 12px;
    }

    .product-details {
        padding: 12px;
        background: var(--zinc-50);
        border: 1px solid var(--zinc-200);
        border-radius: 8px;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        padding: 4px 0;
        color: var(--text-muted);
    }

    .detail-row strong {
        color: var(--text-main);
    }

    #detail-total {
        color: var(--primary);
    }

    /* ── Panier de la commande ── */
    .cart-section {
        margin-bottom: 12px;
        border: 1px solid var(--zinc-200);
        border-radius: 8px;
        overflow: hidden;
    }

    .cart-header {
        padding: 8px 12px;
        background: var(--zinc-50);
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-muted);
        border-bottom: 1px solid var(--zinc-100);
    }

    .cart-list {
        list-style: none;
        margin: 0;
        padding: 0;
        max-height: 220px;
        overflow-y: auto;
    }

    .cart-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        font-size: 0.85rem;
        border-bottom: 1px solid var(--zinc-100);
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    .cart-item-name {
        flex: 1;
        min-width: 0;
    }

    .cart-item-total {
        font-weight: 600;
        color: var(--primary);
        white-space: nowrap;
    }

    .cart-item-remove {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--danger);
        font-size: 0.85rem;
        padding: 2px 4px;
    }

    .order-total-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        background: var(--primary-light);
        border-radius: 8px;
        margin-bottom: 12px;
        font-size: 0.9rem;
    }

    .order-total-box strong {
        font-size: 1.1rem;
        color: var(--primary);
    }

    /* ── Urgence section ── */
    .urgence-section {
        padding: 12px;
        background: color-mix(in srgb, #f97316 8%, var(--bg-card));
        border: 1px solid color-mix(in srgb, #f97316 30%, var(--zinc-200));
        border-radius: 8px;
        margin-bottom: 12px;
    }

    .urgence-options {
        margin-top: 10px;
    }

    .toggle-label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .toggle-label input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    /* ── Mode retrait ── */
    .retrait-section {
        margin-bottom: 12px;
    }

    .retrait-options {
        display: flex;
        gap: 10px;
        margin-top: 8px;
    }

    .retrait-option {
        flex: 1;
        cursor: pointer;
    }

    .retrait-option input {
        display: none;
    }

    .retrait-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        padding: 10px;
        border: 2px solid var(--zinc-200);
        border-radius: 8px;
        font-size: 0.82rem;
        transition: all 0.2s ease;
        background: var(--bg-card);
    }

    .retrait-card i {
        font-size: 1.2rem;
        color: var(--text-muted);
    }

    .retrait-option input:checked+.retrait-card {
        border-color: var(--primary);
        background: var(--primary-light);
        color: var(--primary);
    }

    .retrait-option input:checked+.retrait-card i {
        color: var(--primary);
    }

    /* ── Chat panel ── */
    .chat-panel {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: min(430px, calc(100vw - 48px));
        height: min(680px, calc(100vh - 48px));
        background: var(--bg-card);
        color: var(--text-main);
        border-radius: 18px;
        box-shadow: 0 18px 55px rgba(15, 23, 42, 0.22);
        z-index: 1500;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--zinc-200);
    }

    .chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 18px;
        background: #172554;
        color: white;
    }

    .chat-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
    }

    .chat-avatar {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: #2563eb;
        color: white;
        flex-shrink: 0;
    }

    .chat-title strong,
    .chat-command-ref {
        display: block;
    }

    .chat-command-ref {
        margin-top: 2px;
        color: #bfdbfe;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .chat-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .chat-close-btn {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 8px;
        background: transparent;
        color: white;
        cursor: pointer;
    }

    .chat-close-btn:hover {
        background: rgba(255, 255, 255, 0.12);
    }

    .chat-status {
        font-size: 0.75rem;
        color: #bbf7d0;
        white-space: nowrap;
    }

    .chat-status-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        margin-right: 4px;
        border-radius: 50%;
        background: #4ade80;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 18px 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: var(--zinc-50);
    }

    .chat-loading,
    .chat-empty {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.875rem;
        padding: 30px;
        margin: auto;
    }

    /* Bulles */
    .chat-bubble {
        max-width: 84%;
        display: flex;
        flex-direction: column;
    }

    .bubble-moi {
        align-self: flex-end;
        align-items: flex-end;
    }

    .bubble-autre {
        align-self: flex-start;
        align-items: flex-start;
    }

    .bubble-auteur {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-bottom: 2px;
    }

    .bubble-content {
        padding: 10px 13px;
        border-radius: 14px;
        font-size: 0.875rem;
        line-height: 1.4;
        word-break: break-word;
    }

    .bubble-moi .bubble-content {
        background: #2563eb;
        color: white;
        border-bottom-right-radius: 4px;
    }

    .bubble-autre .bubble-content {
        background: var(--zinc-100);
        color: var(--text-main);
        border: 1px solid var(--zinc-200);
        border-bottom-left-radius: 4px;
    }

    .bubble-meta {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .bubble-lu {
        color: #667eea;
    }

    .chat-msg-type {
        font-size: 0.72rem;
        font-weight: 700;
        margin-bottom: 4px;
        opacity: 0.8;
    }

    .chat-file-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.82rem;
        padding: 4px 8px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.2);
        color: inherit;
        margin-top: 4px;
        text-decoration: none;
    }

    .chat-file-link:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Zone de saisie */
    .chat-input-area {
        border-top: 1px solid var(--zinc-100);
        padding: 12px 14px 14px;
        background: var(--bg-card);
    }

    .chat-compose-label {
        margin-bottom: 8px;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .chat-type-selector {
        display: flex;
        gap: 6px;
        margin-bottom: 10px;
    }

    .chat-type-btn {
        flex: 1 1 70px;
        min-width: 0;
        min-height: 34px;
        padding: 6px 5px;
        background: var(--zinc-100);
        border: none;
        border-radius: 6px;
        cursor: pointer;
        color: var(--text-muted);
        font-size: 0.8rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .chat-more-btn {
        display: none;
        flex: 0 0 auto;
        min-height: 34px;
        padding: 6px 10px;
        border: 0;
        border-radius: 6px;
        background: var(--zinc-100);
        color: var(--text-muted);
        cursor: pointer;
        font: inherit;
        font-size: 0.8rem;
    }

    .chat-type-btn.active,
    .chat-type-btn:hover {
        background: var(--primary-light);
        color: var(--primary);
    }

    .file-preview {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 5px 8px;
        background: var(--info-bg);
        border-radius: 6px;
        font-size: 0.8rem;
        margin-bottom: 6px;
        color: var(--info);
    }

    .chat-input-row {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    .chat-textarea {
        flex: 1;
        resize: none;
        border: 1px solid var(--zinc-200);
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 0.875rem;
        font-family: inherit;
        outline: none;
        max-height: 80px;
        min-height: 42px;
        overflow-y: auto;
    }

    .chat-textarea:focus {
        border-color: var(--primary);
    }

    .chat-send-btn {
        min-height: 42px;
        padding: 0 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }

    .chat-send-btn span {
        display: inline;
    }

    @keyframes pulse-danger {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4);
        }

        50% {
            box-shadow: 0 0 0 6px rgba(231, 76, 60, 0);
        }
    }

    /* ── Alertes améliorées ── */
    .b2b-alert {
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .chat-panel {
            inset: 10px;
            width: auto;
            height: calc(100dvh - 20px);
            max-height: none;
            border-radius: 14px;
        }

        .chat-type-selector {
            overflow-x: visible;
            padding-bottom: 2px;
        }

        .chat-type-btn {
            flex: 0 0 auto;
            min-width: 74px;
        }

        .chat-type-selector .chat-advanced-type {
            display: none;
        }

        .chat-type-selector .chat-more-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .chat-type-selector.show-advanced .chat-advanced-type {
            display: inline-flex;
        }

        .chat-type-selector .chat-type-btn,
        .chat-type-selector .chat-more-btn {
            min-width: 0;
            flex: 1 1 0;
        }

        .chat-type-selector .chat-type-btn span {
            display: none;
        }

        .chat-type-selector .chat-more-btn span,
        .chat-type-selector.show-advanced .chat-more-btn span {
            display: inline;
        }

        .chat-input-row {
            gap: 6px;
        }

        .chat-send-btn {
            min-width: 46px;
            padding: 0 12px;
        }

        .chat-send-btn span {
            display: none;
        }

        .commande-timeline {
            display: none;
        }

        .b2b-tabs {
            flex-direction: column;
        }
    }
</style>

</body>

</html>
