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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <?php if (isset($_SESSION['user_id'])): ?>
        <?php $current = basename($_SERVER['PHP_SELF']); ?>

        <!-- ============================================================
             SIDEBAR — offcanvas sur mobile/tablette, fixe en colonne à
             partir du breakpoint lg (voir theme.css : @media (min-width: 992px)).
             ============================================================ -->
        <div class="offcanvas offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
            <div class="offcanvas-header d-lg-none">
                <span class="offcanvas-title" id="appSidebarLabel">Menu</span>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
            </div>
            <div class="offcanvas-body app-sidebar-body">
                <a href="dashboard.php" class="sidebar-brand">
                    <span class="brand-icon d-inline-flex align-items-center justify-content-center">
                        <i class="fas fa-cube"></i>
                    </span>
                    <span>FactuPro<span class="text-primary">.B2B</span></span>
                </a>

                <nav class="sidebar-nav" aria-label="Navigation principale">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link <?= $current === 'dashboard.php' ? 'active' : '' ?>">
                                <i class="fas fa-chart-pie"></i> Tableau de bord
                            </a>
                        </li>
                    </ul>

                    <?php if (peutVoirVentes() || peutCreerVente() || peutVoirFactures() || peutVoirClients()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">Ventes</div>
                        <ul class="nav flex-column">
                            <?php if (peutCreerVente()): ?>
                                <li class="nav-item">
                                    <a href="invoice_add.php" class="nav-link <?= $current === 'invoice_add.php' ? 'active' : '' ?>">
                                        <i class="fas fa-cash-register"></i> Nouvelle vente
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutVoirVentes()): ?>
                                <li class="nav-item">
                                    <a href="sales.php" class="nav-link <?= $current === 'sales.php' ? 'active' : '' ?>">
                                        <i class="fas fa-receipt"></i> Ventes
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutVoirFactures()): ?>
                                <li class="nav-item">
                                    <a href="invoices.php" class="nav-link <?= $current === 'invoices.php' ? 'active' : '' ?>">
                                        <i class="fas fa-file-invoice"></i> Factures
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutVoirClients()): ?>
                                <li class="nav-item">
                                    <a href="clients.php" class="nav-link <?= $current === 'clients.php' ? 'active' : '' ?>">
                                        <i class="fas fa-users"></i> Clients
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (peutVoirProduits() || peutGererStock()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">Stocks</div>
                        <ul class="nav flex-column">
                            <?php if (peutVoirProduits()): ?>
                                <li class="nav-item">
                                    <a href="products.php" class="nav-link <?= $current === 'products.php' ? 'active' : '' ?>">
                                        <i class="fas fa-box"></i> Produits
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutGererStock()): ?>
                                <li class="nav-item">
                                    <a href="approvisionnement.php" class="nav-link <?= $current === 'approvisionnement.php' ? 'active' : '' ?>">
                                        <i class="fas fa-truck-loading"></i> Approvisionnement
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (peutAccederB2B() || peutGererB2B()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">B2B</div>
                        <ul class="nav flex-column">
                            <?php if (peutAccederB2B()): ?>
                                <li class="nav-item">
                                    <a href="reseau_b2b.php" class="nav-link <?= $current === 'reseau_b2b.php' ? 'active' : '' ?>">
                                        <i class="fas fa-globe"></i> Réseau
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutGererB2B()): ?>
                                <li class="nav-item">
                                    <a href="commandes_b2b.php" class="nav-link <?= $current === 'commandes_b2b.php' ? 'active' : '' ?>">
                                        <i class="fas fa-comments"></i> Commandes
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="annonces.php" class="nav-link <?= $current === 'annonces.php' ? 'active' : '' ?>">
                                        <i class="fas fa-bullhorn"></i> Annonces
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="notifications_b2b.php" class="nav-link <?= $current === 'notifications_b2b.php' ? 'active' : '' ?>">
                                        <i class="fas fa-bell"></i> Notifications
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (peutVoirExpeditions()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">Logistique</div>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a href="logistique.php" class="nav-link <?= $current === 'logistique.php' ? 'active' : '' ?>">
                                    <i class="fas fa-truck"></i> Livraisons
                                </a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (peutGererEquipe() || peutGererParametres()): ?>
                    <div class="nav-group">
                        <div class="nav-group-title">Administration</div>
                        <ul class="nav flex-column">
                            <?php if (peutGererEquipe()): ?>
                                <li class="nav-item">
                                    <a href="team.php" class="nav-link <?= $current === 'team.php' ? 'active' : '' ?>">
                                        <i class="fas fa-user-shield"></i> Équipe
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (peutGererParametres()): ?>
                                <li class="nav-item">
                                    <a href="settings.php" class="nav-link <?= $current === 'settings.php' ? 'active' : '' ?>">
                                        <i class="fas fa-cog"></i> Paramètres
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </nav>
            </div>
        </div>

        <!-- ============================================================
             HEADER SUPÉRIEUR COMPACT — titre de page, notifications, menu utilisateur
             ============================================================ -->
        <header class="app-topbar">
            <button class="btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Ouvrir le menu">
                <i class="fas fa-bars"></i>
            </button>

            <h1 class="topbar-title"><?= htmlspecialchars($page_title ?? 'FactuPro') ?></h1>

            <div class="topbar-actions">
                <button id="dark-toggle" class="btn btn-icon rounded-pill" title="Mode sombre / clair" aria-label="Basculer le thème">
                    <i class="fas fa-moon" id="dark-icon"></i>
                </button>

                <?php if (peutAccederB2B()): ?>
                    <a href="notifications_b2b.php" class="btn btn-icon" id="notifBell" title="Notifications B2B">
                        <i class="fas fa-bell"></i>
                    </a>
                <?php endif; ?>

                <div class="dropdown">
                    <a href="#" class="topbar-user d-flex align-items-center gap-2" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar-circle"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></span>
                        <span class="d-none d-md-inline topbar-username"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                        <i class="fas fa-chevron-down d-none d-md-inline" style="font-size:.7rem; opacity:.6;"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:240px;">
                        <li class="px-2 py-1">
                            <div class="fw-bold text-truncate"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></div>
                            <span class="badge text-bg-primary"><?= nomRole($_SESSION['role'] ?? '') ?></span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (peutGererEquipe()): ?>
                            <li><a href="team.php" class="dropdown-item"><i class="fas fa-user-shield text-primary me-2"></i>Gestion d'équipe</a></li>
                            <li><a href="settings.php" class="dropdown-item"><i class="fas fa-cog text-secondary me-2"></i>Paramètres</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a href="../includes/logout.php" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            (function() {
                /* ── Dark Mode Pill Toggle ── */
                const root = document.documentElement;
                const toggle = document.getElementById('dark-toggle');
                const icon = document.getElementById('dark-icon');

                function applyTheme(dark) {
                    root.setAttribute('data-theme', dark ? 'dark' : 'light');
                    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
                    if (icon) {
                        icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
                    }
                }

                const saved = localStorage.getItem('factupro_theme');
                applyTheme(saved === 'dark');

                if (toggle) {
                    toggle.addEventListener('click', function() {
                        const isDark = root.getAttribute('data-bs-theme') === 'dark';
                        applyTheme(!isDark);
                        localStorage.setItem('factupro_theme', !isDark ? 'dark' : 'light');
                    });
                }
            })();
        </script>
    <?php endif; ?>

    <main class="app-main fade-in">