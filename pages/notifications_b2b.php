<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(ROLE_PROPRIO);

$page_title = "Notifications B2B";
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$mon_entreprise_id = (int) $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all') {
    requireCsrf();
    $pdo->prepare("UPDATE Notification_B2B SET Est_Lue = TRUE WHERE Id_Entreprise_Destinataire = ?")
        ->execute([$mon_entreprise_id]);
    header('Location: notifications_b2b.php');
    exit;
}

include '../includes/header.php';

// Paginer les notifications
$page  = max(1, intval($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM Notification_B2B WHERE Id_Entreprise_Destinataire = ?");
$total_stmt->execute([$mon_entreprise_id]);
$total = (int)$total_stmt->fetchColumn();
$total_pages = ceil($total / $limit);

$stmt = $pdo->prepare("
    SELECT n.*, c.Numero_Commande
    FROM Notification_B2B n
    LEFT JOIN Commande_B2B c ON n.Id_Commande_B2B = c.Id_Commande_B2B
    WHERE n.Id_Entreprise_Destinataire = ?
    ORDER BY n.Date_Creation DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$mon_entreprise_id, $limit, $offset]);
$notifications = $stmt->fetchAll();

$non_lues = $pdo->prepare("SELECT COUNT(*) FROM Notification_B2B WHERE Id_Entreprise_Destinataire = ? AND Est_Lue = FALSE");
$non_lues->execute([$mon_entreprise_id]);
$nb_non_lues = (int)$non_lues->fetchColumn();

$icones = [
    'nouvelle_commande'  => ['fa-shopping-cart', '#3498db'],
    'commande_urgente'   => ['fa-bolt',           '#e74c3c'],
    'nouveau_message'    => ['fa-comment',        '#9b59b6'],
    'validation'         => ['fa-check-circle',   '#27ae60'],
    'refus'              => ['fa-times-circle',   '#e74c3c'],
    'livraison'          => ['fa-truck',          '#27ae60'],
    'expedition'         => ['fa-shipping-fast',  '#2980b9'],
    'preparation'        => ['fa-box-open',        '#8b5cf6'],
    'prete'              => ['fa-check-double',   '#0d9488'],
    'reception'          => ['fa-trophy',         '#eab308'],
];
?>

<div class="container fade-in">
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
        <div>
            <h1><i class="fas fa-bell"></i> Notifications B2B</h1>
            <p>
                <?php if ($nb_non_lues > 0): ?>
                    <span class="badge badge-primary"><?= $nb_non_lues ?> non lue(s)</span>
                <?php else: ?>
                    Tout est à jour ✓
                <?php endif; ?>
            </p>
        </div>
        <?php if ($nb_non_lues > 0): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="mark_all">
                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="fas fa-check-double"></i> Tout marquer comme lu
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Navigation B2B -->
    <div class="b2b-nav-links" style="display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap;">
        <a href="reseau_b2b.php" class="btn btn-secondary btn-sm"><i class="fas fa-building"></i> Annuaire</a>
        <a href="annonces.php" class="btn btn-secondary btn-sm"><i class="fas fa-bullhorn"></i> Annonces</a>
        <a href="commandes_b2b.php" class="btn btn-secondary btn-sm"><i class="fas fa-shipping-fast"></i> Commandes</a>
    </div>

    <div class="notifs-list">
        <?php if (empty($notifications)): ?>
            <div class="card" style="text-align:center; padding:60px 20px; color:var(--text-muted);">
                <i class="fas fa-bell-slash" style="font-size:3rem; opacity:0.3; margin-bottom:15px;"></i>
                <p>Aucune notification pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <?php
                $ic = $icones[$n['Type_Notif']] ?? ['fa-bell', '#6b7076'];
                $ts = strtotime($n['Date_Creation']);
                $diff = time() - $ts;
                if ($diff < 60)        $temps = 'À l\'instant';
                elseif ($diff < 3600)  $temps = 'Il y a ' . floor($diff / 60) . ' min';
                elseif ($diff < 86400) $temps = 'Il y a ' . floor($diff / 3600) . 'h';
                else                   $temps = date('d/m/Y à H:i', $ts);
                ?>
                <div class="notif-item <?= !$n['Est_Lue'] ? 'notif-unread' : '' ?>"
                    onclick="marquerLue(<?= $n['Id_Notification'] ?>, this)"
                    style="cursor:pointer;">
                    <div class="notif-icon" style="background:<?= $ic[1] ?>20; color:<?= $ic[1] ?>;">
                        <i class="fas <?= $ic[0] ?>"></i>
                    </div>
                    <div class="notif-content">
                        <div class="notif-titre">
                            <?= htmlspecialchars($n['Titre']) ?>
                            <?php if (!$n['Est_Lue']): ?>
                                <span class="notif-dot"></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($n['Message']): ?>
                            <div class="notif-message"><?= htmlspecialchars($n['Message']) ?></div>
                        <?php endif; ?>
                        <div class="notif-meta">
                            <span><i class="fas fa-clock"></i> <?= $temps ?></span>
                            <?php if ($n['Numero_Commande']): ?>
                                <a href="commandes_b2b.php" class="notif-link" onclick="event.stopPropagation();">
                                    <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($n['Numero_Commande']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="display:flex; gap:6px; justify-content:center; margin-top:20px;">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?p=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    async function marquerLue(id, el) {
        if (el.classList.contains('notif-unread')) {
            el.classList.remove('notif-unread');
            const dot = el.querySelector('.notif-dot');
            if (dot) dot.remove();

            await fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=mark_read&id=${id}&csrf_token=${encodeURIComponent('<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>')}`
            });
        }
    }
</script>

<style>
    .notifs-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .notif-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 18px;
        background: white;
        border-radius: 12px;
        border: 1px solid var(--zinc-200);
        transition: background 0.2s, box-shadow 0.2s;
    }

    .notif-item:hover {
        background: var(--zinc-50);
        box-shadow: var(--shadow-sm);
    }

    .notif-unread {
        border-left: 3px solid var(--primary);
        background: #f8f9ff;
    }

    .notif-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .notif-content {
        flex: 1;
        min-width: 0;
    }

    .notif-titre {
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .notif-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--primary);
        flex-shrink: 0;
    }

    .notif-message {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-top: 3px;
        line-height: 1.4;
    }

    .notif-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 5px;
        font-size: 0.76rem;
        color: var(--text-muted);
    }

    .notif-link {
        color: var(--primary);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .notif-link:hover {
        text-decoration: underline;
    }
</style>
</body>

</html>