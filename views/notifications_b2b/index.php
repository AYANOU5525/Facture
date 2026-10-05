<div class="container fade-in py-4">
    <div class="mb-3">
        <p class="text-body-secondary mb-0">Suivez les réponses, commandes et messages liés à votre réseau B2B</p>
    </div>

    <!-- Navigation B2B -->
    <?php require __DIR__ . '/../partials/b2b_nav.php'; ?>

    <div class="panel-soft">
        <div class="panel-soft-head">
            <div>
                <h2 class="panel-soft-title">Notifications</h2>
                <div class="panel-soft-sub">
                    <?= count($notifications) ?> notification<?= count($notifications) > 1 ? 's' : '' ?> ·
                    <?= $nb_non_lues > 0
                        ? $nb_non_lues . ' non lue' . ($nb_non_lues > 1 ? 's' : '')
                        : 'vous êtes à jour' ?>
                </div>
            </div>
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

        <?php if (empty($notifications)): ?>
            <div class="notif-empty py-5">
                <span class="notif-empty-icon"><i class="fas fa-bell-slash"></i></span>
                <span class="fw-semibold">Aucune notification</span>
                <span>Les commandes et messages B2B apparaîtront ici.</span>
            </div>
        <?php else: ?>
            <div class="notifs-page-list">
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
                    $estNonLue = !$n['Est_Lue'];
                ?>
                <<?= $tag ?> <?php if ($hasCommande): ?>
                        href="../api/notifications.php?action=open&amp;id=<?= (int) $n['Id_Notification'] ?>"
                    <?php else: ?>
                        onclick="marquerLue(<?= $n['Id_Notification'] ?>, this)"
                    <?php endif; ?>
                    class="notif-page-row <?= $estNonLue ? 'is-unread' : '' ?>"
                    style="--notif-color: <?= htmlspecialchars(\App\Application\B2B\NotificationService::COLORS[$n['Type_Notif']] ?? '#6b7076') ?>">
                    <span class="notif-row-icon">
                        <i class="fas <?= htmlspecialchars(\App\Application\B2B\NotificationService::ICONS[$n['Type_Notif']] ?? 'fa-bell') ?>"></i>
                    </span>
                    <div class="notif-page-body">
                        <div class="notif-page-title"><?= htmlspecialchars(\App\Application\B2B\NotificationService::cleanTitle((string) $n['Titre'])) ?></div>
                        <?php if ($n['Message']): ?>
                            <div class="notif-page-msg"><?= htmlspecialchars($n['Message']) ?></div>
                        <?php endif; ?>
                        <div class="notif-page-meta">
                            <span><i class="far fa-clock"></i> <?= $temps ?></span>
                            <?php if ($n['Numero_Commande']): ?>
                                <span class="notif-page-cmd"><i class="fas fa-receipt"></i> <?= htmlspecialchars($n['Numero_Commande']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($estNonLue): ?>
                        <span class="notif-voyant" title="Non lue" aria-label="Non lue"></span>
                    <?php endif; ?>
                </<?= $tag ?>>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

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

    // Notification liée à une commande : le lien passe par api/notifications.php?action=open,
    // qui la marque comme lue et redirige vers la commande (bon onglet, détail déplié).

    // Notification sans commande associée : pas de destination à ouvrir, on reste sur la page.
    async function marquerLue(id, el) {
        if (el.classList.contains('is-unread')) {
            el.classList.remove('is-unread');
            el.querySelector('.notif-voyant')?.remove();
            await envoyerMarquageLue(id);
        }
    }
</script>

<style>
    .notifs-page-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 12px;
    }
    .notif-page-row {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 44px 14px 16px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--bg-card);
        color: var(--text-main);
        text-decoration: none;
        cursor: pointer;
        transition: background 0.12s ease, border-color 0.12s ease, box-shadow 0.12s ease;
    }
    .notif-page-row:hover {
        color: var(--text-main);
        border-color: color-mix(in srgb, var(--notif-color) 35%, var(--border));
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    }
    .notif-page-row.is-unread { background: color-mix(in srgb, var(--primary) 4%, var(--bg-card)); }
    .notif-page-row .notif-row-icon { width: 38px; height: 38px; font-size: 0.95rem; }
    .notif-page-body { flex: 1; min-width: 0; }
    .notif-page-title { font-size: 0.9rem; font-weight: 500; color: var(--text-muted); }
    .notif-page-row.is-unread .notif-page-title { font-weight: 700; color: var(--text-main); }
    .notif-page-msg { margin-top: 2px; font-size: 0.82rem; line-height: 1.45; color: var(--text-muted); overflow-wrap: anywhere; }
    .notif-page-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 6px; font-size: 0.74rem; color: var(--text-muted); }
    .notif-page-meta i { font-size: 0.68rem; margin-right: 2px; }
    .notif-page-cmd { color: var(--primary); font-weight: 600; }
    /* Voyant « non lue » */
    .notif-voyant {
        position: absolute;
        top: 50%;
        right: 18px;
        width: 10px;
        height: 10px;
        margin-top: -5px;
        border-radius: 50%;
        background: var(--primary);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 18%, transparent);
    }
</style>
</body>

</html>
