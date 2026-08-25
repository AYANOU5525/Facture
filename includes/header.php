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
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Préchauffe les connexions vers les services de carte (Leaflet, tuiles OSM, géocodage,
         itinéraire) dès qu'une page est chargée après connexion, pour que la carte parte plus
         vite quand l'utilisateur atteint réellement une page qui l'affiche (Logistique, Paramètres). -->
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
    <link rel="dns-prefetch" href="https://a.tile.openstreetmap.org">
    <link rel="dns-prefetch" href="https://b.tile.openstreetmap.org">
    <link rel="dns-prefetch" href="https://c.tile.openstreetmap.org">
    <link rel="preconnect" href="https://nominatim.openstreetmap.org" crossorigin>
    <link rel="preconnect" href="https://router.project-osrm.org" crossorigin>
    <link rel="dns-prefetch" href="https://raw.githubusercontent.com">

    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <?php if (isset($_SESSION['user_id'])): ?>
        <nav class="navbar" id="mainNavbar">
            <div class="navbar-content">

                <!-- LEFT: BRAND -->
                <div class="nav-left">
                    <a href="dashboard.php" class="nav-brand">
                        <div class="brand-icon">
                            <i class="fas fa-cube"></i>
                        </div>
                        <span class="brand-text">
                            FactuPro<span class="brand-highlight">.B2B</span>
                        </span>
                    </a>
                </div>

                <!-- CENTER: MAIN NAVIGATION -->
                <div class="nav-center" style="flex:1; display:flex; justify-content:center;">
                    <ul class="nav-links main-nav">
                        <li>
                            <a href="dashboard.php"
                                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"
                                title="Tableau de bord">
                                <i class="fas fa-chart-pie"></i> Dash
                            </a>
                        </li>
                        <?php if (peutVoirStock()): ?>
                            <li>
                                <a href="products.php"
                                    class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>"
                                    title="Inventaire">
                                    <i class="fas fa-box"></i> Stocks
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (peutGererStock()): ?>
                            <li>
                                <a href="approvisionnement.php"
                                    class="<?= basename($_SERVER['PHP_SELF']) == 'approvisionnement.php' ? 'active' : '' ?>"
                                    title="Entrée en stock">
                                    <i class="fas fa-truck-loading"></i> Réception
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (peutVendre()): ?>
                            <li>
                                <a href="sales.php"
                                    class="<?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>"
                                    title="Historique">
                                    <i class="fas fa-receipt"></i> Ventes
                                </a>
                            </li>
                            <li>
                                <a href="invoices.php"
                                    class="<?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>"
                                    title="Facturation">
                                    <i class="fas fa-file-invoice"></i> Factures
                                </a>
                            </li>
                            <li>
                                <a href="clients.php"
                                    class="<?= basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : '' ?>"
                                    title="Base clients">
                                    <i class="fas fa-users"></i> Clients
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (peutLivrer()): ?>
                            <li>
                                <a href="logistique.php"
                                    class="<?= basename($_SERVER['PHP_SELF']) == 'logistique.php' ? 'active' : '' ?>"
                                    title="Livraisons">
                                    <i class="fas fa-truck"></i> Logistique
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- RIGHT: ACTIONS -->
                <div class="nav-right" style="display:flex; align-items:center; gap:8px;">

                    <!-- Dark mode pill toggle — Uiverse.io style -->
                    <button id="dark-toggle" title="Mode sombre / clair" aria-label="Basculer le thème">
                        <i class="fas fa-moon" id="dark-icon"></i>
                    </button>

                    <!-- Notification Bell — ReactBits pulse ring -->
                    <?php if (peutAccederB2B()): ?>
                        <a href="notifications_b2b.php" class="notif-bell" id="notifBell" title="Notifications B2B">
                            <i class="fas fa-bell"></i>
                            <span id="nav-notif-badge">0</span>
                        </a>
                    <?php endif; ?>

                    <!-- Burger / User menu -->
                    <div class="user-dropdown">
                        <button id="burgerBtn" aria-label="Menu utilisateur" aria-expanded="false">
                            <i class="fas fa-bars" id="burgerIcon"></i>
                        </button>

                        <div id="userMenu" role="menu" aria-hidden="true">
                            <!-- User info header -->
                            <div style="display:flex; align-items:center; gap:10px; padding:10px 12px 8px;">
                                <div style="width:36px; height:36px; border-radius:10px; background:var(--primary); display:flex; align-items:center; justify-content:center; color:white; font-size:0.9rem; font-weight:700; flex-shrink:0;">
                                    <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div style="min-width:0;">
                                    <div style="font-weight:700; font-size:0.875rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
                                    </div>
                                    <span class="badge badge-primary" style="font-size:0.65rem; margin-top:2px; display:inline-flex;">
                                        <?= nomRole($_SESSION['role'] ?? '') ?>
                                    </span>
                                </div>
                            </div>

                            <div class="dropdown-divider"></div>

                            <!-- Mobile nav links -->
                            <ul style="list-style:none; padding:0; margin:0;">
                                <li class="mobile-nav-links">
                                    <a href="dashboard.php" class="dropdown-item" role="menuitem">
                                        <i class="fas fa-chart-pie" style="color:var(--primary);"></i>
                                        <span>Tableau de bord</span>
                                    </a>
                                </li>
                                <?php if (peutVoirStock()): ?>
                                    <li class="mobile-nav-links">
                                        <a href="products.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-box" style="color:var(--warning);"></i>
                                            <span>Stocks</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if (peutGererStock()): ?>
                                    <li class="mobile-nav-links">
                                        <a href="approvisionnement.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-truck-loading" style="color:var(--info);"></i>
                                            <span>Réception</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if (peutVendre()): ?>
                                    <li class="mobile-nav-links">
                                        <a href="sales.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-receipt" style="color:var(--success);"></i>
                                            <span>Ventes</span>
                                        </a>
                                    </li>
                                    <li class="mobile-nav-links">
                                        <a href="invoices.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-file-invoice" style="color:var(--primary);"></i>
                                            <span>Factures</span>
                                        </a>
                                    </li>
                                    <li class="mobile-nav-links">
                                        <a href="clients.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-users" style="color:var(--info);"></i>
                                            <span>Clients</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if (peutLivrer()): ?>
                                    <li class="mobile-nav-links">
                                        <a href="logistique.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-truck" style="color:var(--warning);"></i>
                                            <span>Logistique</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if (peutGererEquipe()): ?>
                                    <div class="dropdown-divider"></div>
                                    <li>
                                        <a href="team.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-user-shield" style="color:var(--primary);"></i>
                                            <span>Gestion d'équipe</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="settings.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-cog" style="color:var(--text-muted);"></i>
                                            <span>Paramètres</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if (peutAccederB2B()): ?>
                                    <div class="dropdown-divider"></div>
                                    <li>
                                        <a href="reseau_b2b.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-globe" style="color:var(--primary);"></i>
                                            <span>Réseau B2B</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="commandes_b2b.php" class="dropdown-item" role="menuitem">
                                            <i class="fas fa-comments" style="color:var(--success);"></i>
                                            <span>Commandes &amp; chat B2B</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <div class="dropdown-divider"></div>
                                <li>
                                    <a href="../includes/logout.php" class="dropdown-item logout-item" role="menuitem">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Déconnexion</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <script>
            (function() {
                /* ── Dark Mode Pill Toggle ── */
                const root = document.documentElement;
                const toggle = document.getElementById('dark-toggle');
                const icon = document.getElementById('dark-icon');

                function applyTheme(dark) {
                    root.setAttribute('data-theme', dark ? 'dark' : 'light');
                    if (icon) {
                        icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
                    }
                }

                const saved = localStorage.getItem('factupro_theme');
                applyTheme(saved === 'dark');

                if (toggle) {
                    toggle.addEventListener('click', function() {
                        const isDark = root.getAttribute('data-theme') === 'dark';
                        applyTheme(!isDark);
                        localStorage.setItem('factupro_theme', !isDark ? 'dark' : 'light');
                    });
                }

                /* ── Burger / Dropdown — Animated ── */
                const burgerBtn = document.getElementById('burgerBtn');
                const burgerIcon = document.getElementById('burgerIcon');
                const userMenu = document.getElementById('userMenu');

                function toggleMenu(open) {
                    if (!userMenu) return;
                    if (open) {
                        userMenu.classList.add('open');
                        userMenu.style.display = 'block';
                        userMenu.setAttribute('aria-hidden', 'false');
                        if (burgerBtn) burgerBtn.setAttribute('aria-expanded', 'true');
                        if (burgerIcon) {
                            burgerIcon.className = 'fas fa-times';
                        }
                    } else {
                        userMenu.classList.remove('open');
                        userMenu.setAttribute('aria-hidden', 'true');
                        if (burgerBtn) burgerBtn.setAttribute('aria-expanded', 'false');
                        if (burgerIcon) {
                            burgerIcon.className = 'fas fa-bars';
                        }
                        // hide after animation
                        setTimeout(() => {
                            if (!userMenu.classList.contains('open')) {
                                userMenu.style.display = 'none';
                            }
                        }, 200);
                    }
                }

                if (userMenu) userMenu.style.display = 'none';

                if (burgerBtn && userMenu) {
                    burgerBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const isOpen = userMenu.classList.contains('open');
                        toggleMenu(!isOpen);
                    });

                    document.addEventListener('click', function(e) {
                        if (userMenu.classList.contains('open') &&
                            !userMenu.contains(e.target) &&
                            e.target !== burgerBtn &&
                            !burgerBtn.contains(e.target)) {
                            toggleMenu(false);
                        }
                    });

                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && userMenu.classList.contains('open')) {
                            toggleMenu(false);
                            burgerBtn.focus();
                        }
                    });
                }

                /* ── Notification Badge — ReactBits pulse ── */
                <?php if (peutAccederB2B()): ?>

                    function updateNotifBadge() {
                        fetch('../api/notifications.php?action=count')
                            .then(res => res.json())
                            .then(data => {
                                const badge = document.getElementById('nav-notif-badge');
                                if (badge) {
                                    if (data.success && data.count > 0) {
                                        badge.textContent = data.count > 99 ? '99+' : data.count;
                                        badge.style.display = 'flex';
                                        badge.classList.add('has-notif');
                                    } else {
                                        badge.style.display = 'none';
                                        badge.classList.remove('has-notif');
                                    }
                                }
                            })
                            .catch(err => console.error('Notifications:', err));
                    }
                    updateNotifBadge();
                    setInterval(updateNotifBadge, 30000);
                <?php endif; ?>

                /* ── Navbar scroll shadow ── */
                const navbar = document.getElementById('mainNavbar');
                if (navbar) {
                    window.addEventListener('scroll', function() {
                        if (window.scrollY > 10) {
                            navbar.style.boxShadow = 'var(--shadow-md)';
                        } else {
                            navbar.style.boxShadow = 'var(--shadow-sm)';
                        }
                    }, {
                        passive: true
                    });
                }
            })();
        </script>
    <?php endif; ?>

    <main class="container fade-in">