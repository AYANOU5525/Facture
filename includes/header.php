<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Compteur messages non lus
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    // Connexion DB si pas déjà faite (parfois header est inclus avant db)
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/db.php';
    }
    // ID Entreprise
    if (!isset($entreprise_id_header)) {
        $stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $entreprise_id_header = $stmt->fetchColumn();
    }

    // Compter
    if ($entreprise_id_header) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Message WHERE Id_Destinataire = ? AND Lu = 0");
        $stmt->execute([$entreprise_id_header]);
        $unread_count = $stmt->fetchColumn();
    }
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
            <div class="container navbar-content" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                
                <!-- LEFT: BRAND -->
                <div class="nav-left">
                    <a href="dashboard.php" class="nav-brand" style="text-decoration: none;">
                        <div class="brand-icon"><i class="fas fa-cube"></i></div>
                        <span class="brand-text" style="font-weight: 800; font-size: 1.2rem; letter-spacing: -0.5px;">FactuPro<span class="brand-highlight" style="color: var(--primary);">.B2B</span></span>
                    </a>
                </div>

                <!-- CENTER: MAIN NAVIGATION -->
                <div class="nav-center" style="flex: 1; display: flex; justify-content: center;">
                    <ul class="nav-links main-nav" style="display: flex; gap: 5px; list-style: none; margin: 0; padding: 0;">
                        <li><a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" title="Tableau de bord"><i class="fas fa-chart-pie"></i> Dash</a></li>
                        <li><a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>" title="Inventaire"><i class="fas fa-box"></i> Stocks</a></li>
                        <li><a href="sales.php" class="<?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>" title="Historique"><i class="fas fa-receipt"></i> Ventes</a></li>
                        <li><a href="invoices.php" class="<?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>" title="Facturation"><i class="fas fa-file-invoice"></i> Factures</a></li>
                        <li><a href="clients.php" class="<?= basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : '' ?>" title="Base clients"><i class="fas fa-users"></i> Clients</a></li>
                        <li><a href="logistique.php" class="<?= basename($_SERVER['PHP_SELF']) == 'logistique.php' ? 'active' : '' ?>" title="Livraisons"><i class="fas fa-truck"></i> Logistique</a></li>
                    </ul>
                </div>

                <!-- RIGHT: BURGER MENU -->
                <div class="nav-right">
                    <div class="user-dropdown" style="position: relative;">
                        <button id="burgerBtn" class="btn btn-secondary" style="padding: 0 12px; height: 40px; border-radius: 8px;">
                            <i class="fas fa-bars"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge-notif" style="top: 5px; right: 5px;"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <div id="userMenu" class="dropdown-content m-card" style="display: none; position: absolute; right: 0; top: 55px; width: 240px; z-index: 1001; padding: 12px; border-radius: 12px; box-shadow: var(--shadow-lg); background: #ffffff !important; border: 1px solid var(--zinc-200);">
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <li>
                                    <a href="reseau_b2b.php" class="dropdown-item">
                                        <i class="fas fa-globe" style="width: 20px; color: var(--primary);"></i> 
                                        <span>Réseau B2B</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="messages.php" class="dropdown-item">
                                        <i class="fas fa-comment-dots" style="width: 20px; color: var(--accent);"></i> 
                                        <span>Messagerie</span>
                                        <?php if ($unread_count > 0): ?>
                                            <span class="badge-count"><?= $unread_count ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <div class="dropdown-divider"></div>
                                    <li>
                                        <a href="team.php" class="dropdown-item">
                                            <i class="fas fa-user-shield" style="width: 20px; color: var(--primary);"></i> 
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

            .badge-count {
                background: var(--danger-bg);
                color: var(--danger-text);
                font-size: 0.7rem;
                padding: 2px 8px;
                border-radius: 12px;
                margin-left: auto;
                font-weight: 600;
            }

            .badge-notif {
                position: absolute;
                top: 0;
                right: 0;
                background: #e74c3c;
                color: white;
                font-size: 0.6em;
                padding: 2px 5px;
                border-radius: 50%;
                transform: translate(25%, -25%);
            }

            @media (max-width: 992px) {
                .nav-center {
                    display: none !important; /* Hide center menu on tablets/mobile */
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
            });
        </script>
    <?php endif; ?>

    <main class="container fade-in">