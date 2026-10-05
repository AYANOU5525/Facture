<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Membres de l'entreprise et gestion des rôles</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- GAUCHE : AJOUT -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-transparent">
                    <h3 class="fs-6 mb-0"><i class="fas fa-user-plus text-primary"></i> Nouveau Membre</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Nom d'utilisateur</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe provisoire</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rôle</label>
                            <select name="role" class="form-select">
                                <option value="vendeur">Vendeur — ventes, clients, factures, stocks (lecture)</option>
                                <?php if (FEATURE_LOGISTIQUE_ACTIVE): ?>
                                <option value="livreur">Livreur — logistique uniquement</option>
                                <?php endif; ?>
                                <option value="proprio">Propriétaire — accès complet à l'entreprise</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Ajouter</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- DROITE : LISTE -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-transparent">
                    <h3 class="fs-6 mb-0"><i class="fas fa-list text-primary"></i> Membres existants</h3>
                </div>
                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody data-paginate="5">
                        <?php foreach ($membres as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($u['Nom_Utilisateur']) ?></strong>
                                    <?php if ($u['Id_Utilisateur'] == $_SESSION['user_id']): ?>
                                        <small class="text-body-secondary">(Vous)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['Email_Utilisateur']) ?></td>
                                <td>
                                    <span class="badge <?= classeBadgeRole($u['Role_Utilisateur']) ?>">
                                        <?= nomRole($u['Role_Utilisateur']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['Id_Utilisateur'] != $_SESSION['user_id']): ?>
                                        <button onclick="openModal(<?= $u['Id_Utilisateur'] ?>, '<?= htmlspecialchars($u['Nom_Utilisateur'], ENT_QUOTES) ?>')"
                                                class="btn btn-sm btn-info text-white">
                                            <i class="fas fa-sync-alt"></i> Changer rôle
                                        </button>
                                    <?php else: ?>
                                        <span class="text-body-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALE DE CHANGEMENT DE RÔLE -->
<div id="securityModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-6"><i class="fas fa-user-edit"></i> Changer le rôle</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Membre : <strong id="modalUserName"></strong></p>

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="switch">
                    <input type="hidden" name="target_id" id="modalTargetId">

                    <div class="mb-3">
                        <label class="form-label">Nouveau rôle</label>
                        <select name="new_role" class="form-select">
                            <option value="vendeur">Vendeur — ventes, clients, factures, stocks (lecture)</option>
                            <?php if (FEATURE_LOGISTIQUE_ACTIVE): ?>
                            <option value="livreur">Livreur — logistique uniquement</option>
                            <?php endif; ?>
                            <option value="proprio">Propriétaire — accès complet à l'entreprise</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Votre mot de passe (confirmation)</label>
                        <input type="password"
                               name="admin_password"
                               class="form-control"
                               placeholder="Mot de passe actuel"
                               required>
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
    var securityModal = new bootstrap.Modal(document.getElementById('securityModal'));

    function openModal(id, name) {
        document.getElementById('modalTargetId').value = id;
        document.getElementById('modalUserName').innerText = name;
        securityModal.show();
    }
</script>

</body>

</html>
