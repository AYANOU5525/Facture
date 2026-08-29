<div class="container fade-in py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <p class="text-body-secondary mb-0">
            <?php if ($nb_non_lues > 0): ?>
                <span class="badge text-bg-primary"><?= $nb_non_lues ?> non lue(s)</span>
            <?php else: ?>
                Tout est à jour
            <?php endif; ?>
        </p>
        <?php if ($nb_non_lues > 0): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="mark_all">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-check-double"></i> Tout marquer comme lu
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Navigation B2B -->
    <div class="d-flex gap-2 flex-wrap mb-3">
        <a href="reseau_b2b.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-building"></i> Annuaire</a>
        <a href="annonces.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-bullhorn"></i> Annonces</a>
        <a href="commandes_b2b.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-shipping-fast"></i> Commandes</a>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="card">
            <div class="text-center text-body-secondary py-5">
                <i class="fas fa-bell-slash fs-1 opacity-25 d-block mb-3"></i>
                <p class="mb-0">Aucune notification pour le moment.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header bg-transparent">
                <h2 class="fs-6 mb-0"><?= count($notifications) ?> notification<?= count($notifications) > 1 ? 's' : '' ?></h2>
            </div>
            <div class="list-group list-group-flush notifs-list">
            <?php foreach ($notifications as $n): ?>
                <?php
                $ts = strtotime($n['Date_Creation']);
                $diff = time() - $ts;
                if ($diff < 60)        $temps = 'À l\'instant';
                elseif ($diff < 3600)  $temps = 'Il y a ' . floor($diff / 60) . ' min';
                elseif ($diff < 86400) $temps = 'Il y a ' . floor($diff / 3600) . 'h';
                else                   $temps = date('d/m/Y à H:i', $ts);
                ?>
                <?php
                    $id_cmd = (int) ($n['Id_Commande_B2B'] ?? 0);
                    $hasCommande = $id_cmd > 0 && !empty($n['Numero_Commande']);
                    $tag = $hasCommande ? 'a' : 'div';
                ?>
                <<?= $tag ?> <?php if ($hasCommande): ?>
                        href="commandes_b2b.php?open_chat=<?= $id_cmd ?>&amp;num=<?= urlencode($n['Numero_Commande']) ?>"
                        onclick="beaconMarquerLue(<?= $n['Id_Notification'] ?>)"
                    <?php else: ?>
                        onclick="marquerLue(<?= $n['Id_Notification'] ?>, this)"
                    <?php endif; ?>
                    class="list-group-item list-group-item-action notif-item <?= !$n['Est_Lue'] ? 'notif-unread border-start border-4 border-primary' : '' ?>"
                    style="cursor:pointer;">
                    <div class="notif-content">
                        <div class="fw-semibold">
                            <?= htmlspecialchars($n['Titre']) ?>
                        </div>
                        <?php if ($n['Message']): ?>
                            <div class="text-body-secondary small mt-1"><?= htmlspecialchars($n['Message']) ?></div>
                        <?php endif; ?>
                        <div class="d-flex align-items-center gap-3 text-body-secondary mt-1" style="font-size:0.76rem;">
                            <span><i class="fas fa-clock"></i> <?= $temps ?></span>
                            <?php if ($n['Numero_Commande']): ?>
                                <span class="text-primary">
                                    <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($n['Numero_Commande']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </<?= $tag ?>>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?p=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
    function envoyerMarquageLue(id) {
        return fetch('../api/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=mark_read&id=${id}&csrf_token=${encodeURIComponent('<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>')}`,
            keepalive: true // la requête doit survivre à la navigation vers commandes_b2b.php
        }).catch(() => {});
    }

    // Notification liée à une commande : la navigation par défaut du <a> ouvre directement
    // commandes_b2b.php (qui rouvre le chat) — on se contente de marquer comme lue en tâche de fond.
    function beaconMarquerLue(id) {
        envoyerMarquageLue(id);
    }

    // Notification sans commande associée : pas de destination à ouvrir, on reste sur la page.
    async function marquerLue(id, el) {
        if (el.classList.contains('notif-unread')) {
            el.classList.remove('notif-unread');
            await envoyerMarquageLue(id);
        }
    }
</script>

<style>
    .notif-unread {
        background: var(--bs-primary-bg-subtle);
    }
</style>
</body>

</html>
