<?php
$current_user_role = $_SESSION['role'] ?? 'utilisateur';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<button class="mobile-nav-toggle" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="sidebar-title">FactuPro</div>
    </div>

    <ul class="nav-links">
        <li class="nav-item">
            <a href="tableau_de_bord.php" class="nav-link <?= $current_page == 'tableau_de_bord.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span>Tableau de Bord</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="ajouter_vente.php" class="nav-link <?= $current_page == 'ajouter_vente.php' ? 'active' : '' ?>">
                <i class="fas fa-cart-shopping"></i>
                <span>Nouvelle Vente</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="ajouter_achat.php" class="nav-link <?= $current_page == 'ajouter_achat.php' ? 'active' : '' ?>">
                <i class="fas fa-bag-shopping"></i>
                <span>Nouvel Achat</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="creer_facture.php" class="nav-link <?= $current_page == 'creer_facture.php' ? 'active' : '' ?>">
                <i class="fas fa-file-circle-plus"></i>
                <span>Créer Facture</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="liste_factures.php" class="nav-link <?= $current_page == 'liste_factures.php' ? 'active' : '' ?>">
                <i class="fas fa-folder-open"></i>
                <span>Historique Factures</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="liste_produits.php" class="nav-link <?= $current_page == 'liste_produits.php' ? 'active' : '' ?>">
                <i class="fas fa-boxes-stacked"></i>
                <span>Stock Produits</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="ajouter_produit.php" class="nav-link <?= $current_page == 'ajouter_produit.php' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Ajouter Produit</span>
            </a>
        </li>

        <?php if ($current_user_role === 'admin'): ?>
            <div style="padding: 1rem 0 0.5rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--gray-500); font-weight: 700;">Admin</div>
            <li class="nav-item">
                <a href="ajouter_retour.php" class="nav-link <?= $current_page == 'ajouter_retour.php' ? 'active' : '' ?>">
                    <i class="fas fa-arrow-rotate-left"></i>
                    <span>Retours</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="gerer_utilisateurs.php" class="nav-link <?= $current_page == 'gerer_utilisateurs.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-gear"></i>
                    <span>Utilisateurs</span>
                </a>
            </li>
        <?php endif; ?>

        <div class="sidebar-footer">
            <a href="deconnexion.php" class="nav-link text-danger">
                <i class="fas fa-right-from-bracket"></i>
                <span>Se déconnecter</span>
            </a>
        </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');

            // Toggle icon
            const icon = toggleBtn.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.replace('fa-bars', 'fa-times');
            } else {
                icon.classList.replace('fa-times', 'fa-bars');
            }
        }

        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Close sidebar when clicking a link on mobile
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    toggleSidebar();
                }
            });
        });
    });
</script>