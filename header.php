<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Compteur messages non lus
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    // Connexion DB si pas déjà faite (parfois header est inclus avant db)
    if (!isset($pdo)) {
        require_once 'db.php';
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

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php if (isset($_SESSION['user_id'])): ?>
        <nav class="navbar">
            <div class="container navbar-content">
                <a href="dashboard.php" class="nav-brand">
                    <div class="brand-icon"><i class="fas fa-cube"></i></div>
                    <span class="brand-text">FactuPro<span class="brand-highlight">.B2B</span></span>
                </a>

                <ul class="nav-links">
                    <li><a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Dash</a></li>
                    <li><a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>"><i class="fas fa-box"></i> Prod</a></li>
                    <li><a href="sales.php" class="<?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>"><i class="fas fa-receipt"></i> Ventes</a></li>

                    <li><a href="reseau_b2b.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reseau_b2b.php' ? 'active' : '' ?>"><i class="fas fa-globe"></i> B2B</a></li>
                    <li>
                        <a href="messages.php" class="<?= basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : '' ?>" style="position: relative;">
                            <i class="fas fa-comment-dots"></i> Msg
                            <?php if ($unread_count > 0): ?>
                                <span class="badge-notif"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li><a href="team.php" title="Équipe"><i class="fas fa-users"></i></a></li>
                        <li><a href="settings.php" title="Réglages"><i class="fas fa-cog"></i></a></li>
                    <?php endif; ?>

                    <li class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i></a></li>
                </ul>
            </div>
        </nav>
        <style>
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

            .brand-text {
                display: inline-block;
            }

            @media (max-width: 768px) {
                .brand-text {
                    display: none;
                }
            }
        </style>
    <?php endif; ?>

    <main class="container fade-in">