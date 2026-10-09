<style>
    .workflow-progress { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
    .workflow-progress::before {
        content: '';
        position: absolute;
        top: 20px; left: 5%; right: 5%;
        height: 2px;
        background: var(--bs-border-color);
        z-index: 0;
    }
    .workflow-step { flex: 1; text-align: center; position: relative; z-index: 1; }
    .workflow-step-circle {
        width: 42px; height: 42px;
        border-radius: 50%;
        background: var(--bs-secondary-bg);
        color: var(--bs-secondary-color);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 10px;
        font-weight: 700;
        border: 2px solid var(--bs-border-color);
        transition: all 0.3s;
    }
    .workflow-step.active .workflow-step-circle {
        background: var(--bs-primary); color: #fff; border-color: var(--bs-primary);
        box-shadow: 0 0 0 4px var(--bs-primary-bg-subtle);
    }
    .workflow-step.completed .workflow-step-circle { background: var(--bs-success); color: #fff; border-color: var(--bs-success); }
    .workflow-step-label { font-size: 0.82rem; color: var(--bs-secondary-color); font-weight: 500; }
    .workflow-step.active .workflow-step-label { color: var(--bs-primary); font-weight: 600; }
    .workflow-step.completed .workflow-step-label { color: var(--bs-success); }
    @media (max-width: 600px) { .workflow-step-label { display: none; } }
</style>

<div class="container fade-in py-4">
    <div class="mb-4">
        <h1 class="fs-3 fw-bold mb-1"><i class="fas fa-clipboard-check text-primary me-2"></i> Finalisation de la Vente</h1>
        <p class="text-body-secondary mb-0">
            Vente <strong><?= htmlspecialchars($numero_vente) ?></strong>
            · <?= htmlspecialchars($vente['Nom_Client']) ?>
        </p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- PROGRESSION -->
    <div class="workflow-progress">
        <div class="workflow-step completed">
            <div class="workflow-step-circle"><i class="fas fa-check"></i></div>
            <div class="workflow-step-label">Vente créée</div>
        </div>
        <div class="workflow-step <?= $etape == '1' ? 'active' : ($etape > '1' ? 'completed' : '') ?>">
            <div class="workflow-step-circle"><?= $etape > '1' ? '<i class="fas fa-check"></i>' : '2' ?></div>
            <div class="workflow-step-label">Paiement</div>
        </div>
        <?php if ($avec_livraison): ?>
        <div class="workflow-step <?= $etape == '2' ? 'active' : ($etape > '2' ? 'completed' : '') ?>">
            <div class="workflow-step-circle"><?= $etape > '2' ? '<i class="fas fa-check"></i>' : '3' ?></div>
            <div class="workflow-step-label">Logistique</div>
        </div>
        <div class="workflow-step <?= $etape == '3' ? 'active' : '' ?>">
            <div class="workflow-step-circle"><?= $etape == '3' ? '<i class="fas fa-check"></i>' : '4' ?></div>
            <div class="workflow-step-label">Terminé</div>
        </div>
        <?php else: ?>
        <div class="workflow-step <?= $etape == '3' ? 'active' : '' ?>">
            <div class="workflow-step-circle"><?= $etape == '3' ? '<i class="fas fa-check"></i>' : '3' ?></div>
            <div class="workflow-step-label">Terminé</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- CONTENU DE L'ÉTAPE -->
    <?php if ($etape == '1'): ?>
        <!-- ÉTAPE 1 : VALIDATION PAIEMENT -->
        <div class="card">
        <div class="card-body p-4">
            <h2 class="h5"><i class="fas fa-money-bill-wave text-success me-2"></i> Valider le Paiement</h2>
            <p class="text-body-secondary">La facture a été générée. Confirmez la réception du paiement avant de passer à la logistique.</p>

            <div class="card bg-body-tertiary border-0 my-3">
                <div class="card-body">
                <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                    <span class="text-body-secondary">Client</span>
                    <span class="fw-semibold"><?= htmlspecialchars($vente['Nom_Client']) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                    <span class="text-body-secondary">Référence</span>
                    <span class="fw-semibold"><?= htmlspecialchars($numero_vente) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                    <span class="text-body-secondary">Montant total</span>
                    <span class="fw-semibold fs-5 text-success"><?= number_format($vente['Montant_TTC'], 0, ',', ' ') ?> F</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-1">
                    <span class="text-body-secondary">Statut paiement</span>
                    <span class="badge text-bg-<?= $vente['Statut_Paiement'] === 'payee' ? 'success' : 'warning' ?>">
                        <?= $vente['Statut_Paiement'] === 'payee' ? 'Payée' : 'En attente' ?>
                    </span>
                </div>
                </div>
            </div>

            <?php if ($avec_livraison): ?>
            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <span class="text-body-secondary"><i class="fas fa-truck"></i> Mode de remise</span>
                <span class="badge text-bg-primary">Livraison</span>
            </div>
            <?php else: ?>
            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <span class="text-body-secondary"><i class="fas fa-store"></i> Mode de remise</span>
                <span class="badge text-bg-success">Retrait sur place</span>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 justify-content-end flex-wrap mt-4">
                <?php if ($vente['Statut_Paiement'] !== 'payee'): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" name="valider_paiement" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Confirmer le paiement
                        </button>
                    </form>
                <?php else: ?>
                    <?php if ($avec_livraison): ?>
                    <a href="?ref=<?= urlencode($numero_vente) ?>&etape=2&mode=livraison" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Passer à la logistique
                    </a>
                    <?php else: ?>
                    <a href="?ref=<?= urlencode($numero_vente) ?>&etape=3&mode=retrait" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Terminer la vente
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="invoice_view.php?ref=<?= urlencode($numero_vente) ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="fas fa-print"></i> Voir la facture
                </a>
                <a href="sales.php" class="btn btn-outline-secondary">
                    <i class="fas fa-clock"></i> Terminer plus tard
                </a>
            </div>
        </div>
        </div>

    <?php elseif ($etape == '2'): ?>
        <!-- ÉTAPE 2 : LOGISTIQUE -->
        <div class="card">
        <div class="card-body p-4">
            <h2 class="h5"><i class="fas fa-truck text-primary me-2"></i> Expédition & Logistique</h2>
            <?php if ($logistique): ?>
                <p class="text-body-secondary">
                    La fiche de livraison est créée. Le transporteur et la date prévue doivent être renseignés pour valider l'expédition.
                </p>
                <ul class="list-unstyled mb-0">
                    <li><i class="fas fa-barcode text-body-secondary me-2"></i> N° de suivi : <code><?= htmlspecialchars($logistique['Numero_Suivi'] ?? '-') ?></code></li>
                    <li><i class="fas fa-truck text-body-secondary me-2"></i> Statut : <?= htmlspecialchars(libelleStatutLivraison($logistique)['label']) ?></li>
                    <?php if (!empty($logistique['Transporteur'])): ?>
                        <li><i class="fas fa-user text-body-secondary me-2"></i> Transporteur : <?= htmlspecialchars($logistique['Transporteur']) ?></li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex gap-2 justify-content-end flex-wrap mt-4">
                    <?php if (peutGererExpeditions()): ?>
                        <a href="logistique_edit.php?id=<?= (int) $logistique['Id_Logistique'] ?>" class="btn btn-primary">
                            <i class="fas fa-shipping-fast"></i> <?= $logistique['Statut_Livraison'] === 'traitement' ? "Organiser l'expédition" : 'Suivre la livraison' ?>
                        </a>
                    <?php else: ?>
                        <span class="text-body-secondary small align-self-center">Le propriétaire organisera l'expédition depuis le suivi logistique.</span>
                    <?php endif; ?>
                    <a href="?ref=<?= urlencode($numero_vente) ?>&etape=3&mode=livraison" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right"></i> Terminer
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> Aucune livraison n'est associée à cette vente.
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <a href="?ref=<?= urlencode($numero_vente) ?>&etape=3&mode=livraison" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right"></i> Terminer
                    </a>
                </div>
            <?php endif; ?>
        </div>
        </div>

    <?php else: ?>
        <!-- ÉTAPE 3 : TERMINÉ -->
        <div class="card">
        <div class="card-body text-center py-5 px-4">
            <i class="fas fa-check-circle text-success mb-3" style="font-size:4rem;"></i>
            <h2 class="h4 mb-2">Vente finalisée !</h2>
            <p class="text-body-secondary mx-auto mb-4" style="max-width:420px;">
                La vente <strong><?= htmlspecialchars($numero_vente) ?></strong> a été enregistrée et traitée avec succès.
            </p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="invoice_add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Nouvelle vente
                </a>
                <a href="sales.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list"></i> Toutes les ventes
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
        </div>
    <?php endif; ?>
</div>

</body>

</html>
