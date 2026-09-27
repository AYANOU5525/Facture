<style>
.cl-row-avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--bs-secondary-color);
    background: var(--bs-secondary-bg);
    flex-shrink: 0;
}
.cl-volume { font-weight: 700; }
</style>

<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Base de clients B2B et directs — volumes d'achat</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- KPI STRIP -->
    <div class="dash-kpi-row">
        <div class="dash-kpi">
            <div class="dash-kpi-label">Clients B2B</div>
            <div class="dash-kpi-value"><?= $nb_b2b ?></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Clients directs</div>
            <div class="dash-kpi-value"><?= $nb_directs ?></div>
        </div>
        <div class="dash-kpi is-primary">
            <div class="dash-kpi-label">Volume B2B</div>
            <div class="dash-kpi-value"><?= number_format($total_b2b_volume, 0, ',', ' ') ?> <small>FCFA</small></div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi-label">Volume direct</div>
            <div class="dash-kpi-value"><?= number_format($total_dir_volume, 0, ',', ' ') ?> <small>FCFA</small></div>
        </div>
    </div>

    <!-- CLIENTS B2B -->
    <?php if (!empty($clients_b2b)): ?>
    <div class="card mb-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="fs-6 mb-0 d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-2" style="width:28px;height:28px;background:var(--primary-light); color:var(--primary); font-size:0.85rem;">
                        <i class="fas fa-handshake"></i>
                    </span>
                    Clients B2B — Entreprises
                </h2>
                <p class="text-body-secondary small mb-0 mt-1"><?= $nb_b2b ?> partenaires</p>
            </div>
            <div class="input-group input-group-sm" style="width:auto;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchB2B" class="form-control" placeholder="Rechercher une entreprise..." onkeyup="filterB2B()">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableB2B">
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>Contact</th>
                        <th class="text-center">Commandes</th>
                        <th class="text-end">Volume d'achat</th>
                        <th class="text-end">Dernière commande</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients_b2b as $c):
                        $initiales = strtoupper(substr($c['Nom_Client'], 0, 2));
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="cl-row-avatar">
                                    <?= htmlspecialchars($initiales) ?>
                                </div>
                                <div class="fw-bold" style="font-size:0.9rem;"><?= htmlspecialchars($c['Nom_Client'] ?? '') ?></div>
                            </div>
                        </td>
                        <td class="small text-body-secondary">
                            <?= !empty($c['Tel_Entreprise']) ? htmlspecialchars($c['Tel_Entreprise']) : '—' ?>
                            <?php if (!empty($c['Email_Entreprise'])): ?>
                                <div><?= htmlspecialchars($c['Email_Entreprise']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge text-bg-secondary"><?= $c['Nb_Commandes'] ?> cmd</span>
                        </td>
                        <td class="text-end">
                            <span class="cl-volume"><?= number_format($c['Total_Depense'], 0, ',', ' ') ?> F</span>
                        </td>
                        <td class="text-end small text-body-secondary">
                            <?= !empty($c['Derniere_Commande']) ? date('d/m/Y', strtotime($c['Derniere_Commande'])) : '—' ?>
                        </td>
                        <td class="text-center">
                            <a href="client_history.php?type=b2b&id=<?= $c['Id_Entreprise'] ?? 0 ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-history"></i> Historique
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- CLIENTS DIRECTS -->
    <div class="card">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="fs-6 mb-0 d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-2" style="width:28px;height:28px;background:var(--success-bg); color:var(--success); font-size:0.85rem;">
                        <i class="fas fa-user"></i>
                    </span>
                    Clients Directs
                </h2>
                <p class="text-body-secondary small mb-0 mt-1"><?= $total_directs ?> clients au total</p>
            </div>
            <div class="input-group input-group-sm" style="width:auto;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchDirect" class="form-control" placeholder="Rechercher un client..." onkeyup="filterDirect()">
            </div>
        </div>

        <?php if (!empty($clients_directs)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableDirect">
                <thead>
                    <tr>
                        <th>Nom Client</th>
                        <th>Contact</th>
                        <th class="text-center">Statut</th>
                        <th class="text-center">Ventes</th>
                        <th class="text-end">Volume d'achat</th>
                        <th class="text-end">Dernier achat</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients_directs as $c):
                        $initiales = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $c['Nom_Client']), 0, 2)));
                        $fiche = $c['fiche'] ?? null;
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="cl-row-avatar">
                                    <?= htmlspecialchars($initiales ?: strtoupper(substr($c['Nom_Client'], 0, 2))) ?>
                                </div>
                                <div class="fw-bold" style="font-size:0.9rem;"><?= htmlspecialchars($c['Nom_Client'] ?? '') ?></div>
                            </div>
                        </td>
                        <td class="small text-body-secondary">
                            <?php if ($fiche && (!empty($fiche['Telephone_Client']) || !empty($fiche['Email_Client']))): ?>
                                <?= !empty($fiche['Telephone_Client']) ? htmlspecialchars($fiche['Telephone_Client']) : '—' ?>
                                <?php if (!empty($fiche['Email_Client'])): ?>
                                    <div><?= htmlspecialchars($fiche['Email_Client']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($fiche): ?>
                                <span class="badge <?= $fiche['Statut_Client'] === 'actif' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= $fiche['Statut_Client'] === 'actif' ? 'Actif' : 'Inactif' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-body-tertiary">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge text-bg-secondary"><?= $c['Nb_Commandes'] ?> vente<?= $c['Nb_Commandes'] > 1 ? 's' : '' ?></span>
                        </td>
                        <td class="text-end">
                            <span class="cl-volume"><?= number_format($c['Total_Depense'], 0, ',', ' ') ?> F</span>
                        </td>
                        <td class="text-end small text-body-secondary">
                            <?= !empty($c['Derniere_Commande']) ? date('d/m/Y', strtotime($c['Derniere_Commande'])) : '—' ?>
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="client_history.php?type=direct&name=<?= urlencode($c['Nom_Client'] ?? '') ?>"
                               class="btn btn-sm btn-outline-secondary" title="Historique">
                                <i class="fas fa-history"></i>
                            </a>
                            <?php if ($fiche): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Modifier la fiche"
                                    onclick='openClientModal(<?= json_encode($fiche, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages_directs > 1): ?>
        <div class="card-footer bg-transparent">
            <nav aria-label="Pagination clients directs">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page_direct <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= max(1, $page_direct - 1) ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for ($p = 1; $p <= $pages_directs; $p++): ?>
                        <li class="page-item <?= $p === $page_direct ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page_direct >= $pages_directs ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= min($pages_directs, $page_direct + 1) ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
            <p class="text-center text-body-secondary small mt-2 mb-0">
                <?= $total_directs ?> clients directs au total
            </p>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center py-5 text-body-secondary">
            <div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-body-secondary text-body-tertiary mb-3" style="width:60px;height:60px;font-size:1.5rem;">
                <i class="fas fa-users"></i>
            </div>
            <p class="small mb-0">Aucun client direct enregistré.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODALE FICHE CLIENT -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="clients.php">
                <div class="modal-header">
                    <h3 class="modal-title fs-5"><i class="fas fa-user-edit"></i> Fiche client</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="update_contact">
                    <input type="hidden" name="id_client" id="cl_id_client" value="">

                    <p class="fw-bold mb-3" id="cl_nom_client"></p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" id="cl_telephone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="cl_email" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Adresse</label>
                        <input type="text" name="adresse" id="cl_adresse" class="form-control">
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">NIF</label>
                            <input type="text" name="nif" id="cl_nif" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <select name="statut" id="cl_statut" class="form-select">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openClientModal(fiche) {
    document.getElementById('cl_id_client').value = fiche.Id_Client || '';
    document.getElementById('cl_nom_client').textContent = fiche.Nom_Client || '';
    document.getElementById('cl_telephone').value = fiche.Telephone_Client || '';
    document.getElementById('cl_email').value = fiche.Email_Client || '';
    document.getElementById('cl_adresse').value = fiche.Adresse_Client || '';
    document.getElementById('cl_nif').value = fiche.NIF_Client || '';
    document.getElementById('cl_statut').value = fiche.Statut_Client || 'actif';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('clientModal')).show();
}

function filterB2B() {
    var filter = document.getElementById('searchB2B').value.toUpperCase();
    document.querySelectorAll('#tableB2B tbody tr').forEach(function(tr) {
        var txt = tr.querySelector('td')?.textContent || '';
        tr.style.display = txt.toUpperCase().includes(filter) ? '' : 'none';
    });
}

function filterDirect() {
    var filter = document.getElementById('searchDirect').value.toUpperCase();
    document.querySelectorAll('#tableDirect tbody tr').forEach(function(tr) {
        var txt = tr.querySelector('td')?.textContent || '';
        tr.style.display = txt.toUpperCase().includes(filter) ? '' : 'none';
    });
}
</script>

</body>
</html>
