<?php
if (ob_get_level() === 0) {
    ob_start();
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'FactuPro' ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php if (isset($_SESSION['user_id'])): ?>
        <nav class="navbar">
            <div class="container navbar-content"
                style="display: flex; justify-content: space-between; align-items: center; width: 100%;">

                <!-- LEFT: BRAND -->
                <div class="nav-left">
                    <a href="dashboard.php" class="nav-brand" style="text-decoration: none;">
                        <div class="brand-icon"><i class="fas fa-cube"></i></div>
                        <span class="brand-text"
                            style="font-weight: 800; font-size: 1.2rem; letter-spacing: -0.5px;">
                            FactuPro<span class="brand-highlight" style="color: var(--primary);">.B2B</span>
                        </span>
                    </a>
                </div>

                <!-- CENTER: MAIN NAVIGATION -->
                <div class="nav-center" style="flex: 1; display: flex; justify-content: center;">
                    <ul class="nav-links main-nav"
                        style="display: flex; gap: 5px; list-style: none; margin: 0; padding: 0;">
                        <li>
                            <a href="dashboard.php"
                                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"
                                title="Tableau de bord"><i class="fas fa-chart-pie"></i> Dash</a>
                        </li>
                        <?php if (canViewStock()): ?>
                        <li>
                            <a href="products.php"
                                class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>"
                                title="Inventaire"><i class="fas fa-box"></i> Stocks</a>
                        </li>
                        <?php endif; ?>
                        <?php if (canManageStock()): ?>
                        <li>
                            <a href="approvisionnement.php"
                                class="<?= basename($_SERVER['PHP_SELF']) == 'approvisionnement.php' ? 'active' : '' ?>"
                                title="Entrée en stock"><i class="fas fa-truck-loading"></i> Réception</a>
                        </li>
                        <?php endif; ?>
                        <?php if (canSell()): ?>
                        <li>
                            <a href="sales.php"
                                class="<?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>"
                                title="Historique"><i class="fas fa-receipt"></i> Ventes</a>
                        </li>
                        <li>
                            <a href="invoices.php"
                                class="<?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>"
                                title="Facturation"><i class="fas fa-file-invoice"></i> Factures</a>
                        </li>
                        <li>
                            <a href="clients.php"
                                class="<?= basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : '' ?>"
                                title="Base clients"><i class="fas fa-users"></i> Clients</a>
                        </li>
                        <?php endif; ?>
                        <?php if (canDeliver()): ?>
                        <li>
                            <a href="logistique.php"
                                class="<?= basename($_SERVER['PHP_SELF']) == 'logistique.php' ? 'active' : '' ?>"
                                title="Livraisons"><i class="fas fa-truck"></i> Logistique</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- RIGHT: BURGER MENU -->
                <div class="nav-right" style="display:flex; align-items:center; gap:8px;">

                    <!-- Bouton mode sombre -->
                    <button id="dark-toggle" title="Mode sombre / clair" aria-label="Basculer le thème">
                        <i class="fas fa-moon" id="dark-icon"></i>
                    </button>

                    <!-- Notification Bell (B2B — proprio uniquement) -->
                    <?php if (canAccessB2B()): ?>
                    <a href="notifications_b2b.php" class="btn btn-secondary" style="position:relative; padding:0 12px; height:40px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; color:var(--text-main);">
                        <i class="fas fa-bell"></i>
                        <span id="nav-notif-badge" class="badge" style="position:absolute; top:-6px; right:-6px; display:none; background:var(--danger); color:white; border-radius:50%; width:18px; height:18px; font-size:0.65rem; align-items:center; justify-content:center; padding:0;">0</span>
                    </a>
                    <?php endif; ?>

                    <div class="user-dropdown" style="position: relative;">
                        <button id="burgerBtn"
                            class="btn btn-secondary"
                            style="padding: 0 12px; height: 40px; border-radius: 8px;">
                            <i class="fas fa-bars"></i>
                        </button>

                        <div id="userMenu"
                            class="dropdown-content m-card"
                            style="display: none; position: absolute; right: 0; top: 55px; width: 240px;
                                    z-index: 1001; padding: 12px; border-radius: 12px;
                                    box-shadow: var(--shadow-lg); background: var(--bg-card);
                                    border: 1px solid var(--zinc-200);">
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li class="mobile-nav-links">
                                    <a href="dashboard.php" class="dropdown-item">
                                        <i class="fas fa-chart-pie" style="width: 20px;"></i>
                                        <span>Tableau de bord</span>
                                    </a>
                                </li>
                                <?php if (canViewStock()): ?>
                                <li class="mobile-nav-links">
                                    <a href="products.php" class="dropdown-item">
                                        <i class="fas fa-box" style="width: 20px;"></i>
                                        <span>Stocks</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (canManageStock()): ?>
                                <li class="mobile-nav-links">
                                    <a href="approvisionnement.php" class="dropdown-item">
                                        <i class="fas fa-truck-loading" style="width: 20px;"></i>
                                        <span>Réception</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (canSell()): ?>
                                <li class="mobile-nav-links">
                                    <a href="sales.php" class="dropdown-item">
                                        <i class="fas fa-receipt" style="width: 20px;"></i>
                                        <span>Ventes</span>
                                    </a>
                                </li>
                                <li class="mobile-nav-links">
                                    <a href="invoices.php" class="dropdown-item">
                                        <i class="fas fa-file-invoice" style="width: 20px;"></i>
                                        <span>Factures</span>
                                    </a>
                                </li>
                                <li class="mobile-nav-links">
                                    <a href="clients.php" class="dropdown-item">
                                        <i class="fas fa-users" style="width: 20px;"></i>
                                        <span>Clients</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (canDeliver()): ?>
                                <li class="mobile-nav-links">
                                    <a href="logistique.php" class="dropdown-item">
                                        <i class="fas fa-truck" style="width: 20px;"></i>
                                        <span>Logistique</span>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php if (canManageTeam()): ?>
                                    <div class="dropdown-divider"></div>
                                    <li>
                                        <a href="team.php" class="dropdown-item">
                                            <i class="fas fa-user-shield"
                                                style="width: 20px; color: var(--primary);"></i>
                                            <span>Gestion d'équipe</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="settings.php" class="dropdown-item">
                                            <i class="fas fa-cog" style="width: 20px; color: var(--zinc-500);"></i>
                                            <span>Paramètres</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if (canAccessB2B()): ?>
                                    <div class="dropdown-divider"></div>
                                    <li>
                                        <a href="reseau_b2b.php" class="dropdown-item">
                                            <i class="fas fa-globe" style="width: 20px; color: var(--primary);"></i>
                                            <span>Réseau B2B</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="commandes_b2b.php" class="dropdown-item">
                                            <i class="fas fa-comments" style="width: 20px; color: var(--primary);"></i>
                                            <span>Commandes &amp; chat B2B</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                        <div class="dropdown-divider"></div>
                                <li style="padding:8px 12px;">
                                    <small style="color:var(--text-muted);">Connecté en tant que</small><br>
                                    <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong>
                                    <span class="badge <?= roleBadgeClass($_SESSION['role'] ?? '') ?>" style="font-size:0.7rem; margin-left:6px;">
                                        <?= roleName($_SESSION['role'] ?? '') ?>
                                    </span>
                                </li>
                                <div class="dropdown-divider"></div>
                                <li>
                                    <a href="../includes/logout.php" class="dropdown-item logout-item">
                                        <i class="fas fa-sign-out-alt" style="width: 20px;"></i>
                                        <span>Déconnexion</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <style>
            .navbar-content {
                max-width: 1400px;
                margin: 0 auto;
            }

            .dropdown-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                border-radius: 8px;
                color: var(--text-main);
                text-decoration: none;
                font-size: 0.9rem;
                transition: all 0.2s ease;
                background: transparent;
            }

            .dropdown-item:hover {
                background: var(--zinc-50) !important;
                color: var(--primary);
                transform: translateX(4px);
            }

            .logout-item {
                color: var(--danger-text);
                background: var(--danger-bg);
            }

            .logout-item:hover {
                background: #fee2e2 !important;
                color: #b91c1c !important;
            }

            .dropdown-divider {
                height: 1px;
                background: var(--zinc-100);
                margin: 6px 0;
            }



            @media (max-width: 992px) {
                .nav-center {
                    display: none !important;
                    /* Hide center menu on tablets/mobile */
                }

                /* You might want to move center links to the burger menu if hidden here */
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const burgerBtn = document.getElementById('burgerBtn');
                const userMenu = document.getElementById('userMenu');

                if (burgerBtn && userMenu) {
                    burgerBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        userMenu.style.display = userMenu.style.display === 'none' ? 'block' : 'none';
                    });

                    document.addEventListener('click', function(e) {
                        if (!userMenu.contains(e.target) && e.target !== burgerBtn) {
                            userMenu.style.display = 'none';
                        }
                    });
                }

                // Polling Notifications B2B (proprio uniquement)
                <?php if (canAccessB2B()): ?>
                function updateNotifBadge() {
                    fetch('../api/notifications.php?action=count')
                        .then(res => res.json())
                        .then(data => {
                            const badge = document.getElementById('nav-notif-badge');
                            if (badge) {
                                if (data.success && data.count > 0) {
                                    badge.textContent = data.count > 99 ? '99+' : data.count;
                                    badge.style.display = 'flex';
                                } else {
                                    badge.style.display = 'none';
                                }
                            }
                        })
                        .catch(err => console.error('Erreur check notifications:', err));
                }
                updateNotifBadge();
                setInterval(updateNotifBadge, 30000);
                <?php endif; ?>

                // === Mode sombre ===
                const root     = document.documentElement;
                const toggle   = document.getElementById('dark-toggle');
                const icon     = document.getElementById('dark-icon');

                function applyTheme(dark) {
                    root.setAttribute('data-theme', dark ? 'dark' : 'light');
                    icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
                }

                const saved = localStorage.getItem('factupro_theme');
                applyTheme(saved === 'dark');

                if (toggle) {
                    toggle.addEventListener('click', function () {
                        const isDark = root.getAttribute('data-theme') === 'dark';
                        applyTheme(!isDark);
                        localStorage.setItem('factupro_theme', !isDark ? 'dark' : 'light');
                    });
                }
            });
        </script>
    <?php endif; ?>

    <main class="container fade-in">