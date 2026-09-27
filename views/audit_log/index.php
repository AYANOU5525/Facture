<div class="container fade-in py-4">
    <div class="mb-4">
        <h1 class="fs-4 fw-bold mb-1"><i class="fas fa-shield-halved text-primary me-2"></i> Journal d'audit</h1>
        <p class="text-body-secondary mb-0">Traçabilité des évènements de sécurité de votre entreprise : connexions, verrouillages, scans, associations de codes-barres.</p>
    </div>

    <div class="card">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h6 mb-0">
                <i class="fas fa-list text-primary me-2"></i>
                <?= $total ?> évènement<?= $total > 1 ? 's' : '' ?>
            </h2>
            <form method="GET" class="d-flex align-items-center gap-2">
                <select name="action" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Toutes les actions</option>
                    <?php foreach ($actions_disponibles as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $action_filtree === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($entries)): ?>
            <div class="card-body text-center text-body-secondary py-5">
                <i class="fas fa-shield-halved fs-1 d-block mb-3 opacity-50"></i>
                <p class="mb-0">Aucun évènement<?= $action_filtree !== '' ? ' pour ce filtre' : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Cible</th>
                            <th>Détails</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $e): ?>
                            <tr>
                                <td class="text-nowrap text-body-secondary"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($e['Created_At']))) ?></td>
                                <td><?= htmlspecialchars($e['Nom_Utilisateur'] ?? ('#' . $e['Id_Utilisateur'])) ?></td>
                                <td>
                                    <?php
                                        $badge = str_contains($e['Action'], 'failed') || str_contains($e['Action'], 'lockout')
                                            ? 'text-bg-danger'
                                            : (str_contains($e['Action'], 'success') ? 'text-bg-success' : 'text-bg-secondary');
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($e['Action']) ?></span>
                                </td>
                                <td class="text-body-secondary">
                                    <?= $e['Table_Cible'] ? htmlspecialchars($e['Table_Cible']) . ($e['Id_Cible'] ? ' #' . (int) $e['Id_Cible'] : '') : '—' ?>
                                </td>
                                <td class="text-body-secondary"><?= $e['Details'] ? htmlspecialchars($e['Details']) : '—' ?></td>
                                <td class="text-body-secondary font-monospace"><?= htmlspecialchars($e['IP_Address'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?p=<?= $i ?><?= $action_filtree !== '' ? '&action=' . urlencode($action_filtree) : '' ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
