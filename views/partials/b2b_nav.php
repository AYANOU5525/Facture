<?php
/**
 * Onglets du module B2B (Annuaire / Annonces / Commandes / Notifications), partagés par
 * les 4 pages du module. Style « contrôle segmenté » : .b2b-nav dans assets/css/theme.css.
 * Variables attendues : $current (page courante, défini par includes/header.php),
 * $nb_non_lues facultatif (compteur de notifications non lues).
 */
$onglets_b2b = [
    'reseau_b2b.php'        => ['fa-building', 'Annuaire'],
    'annonces.php'          => ['fa-bullhorn', 'Annonces'],
    'commandes_b2b.php'     => ['fa-shipping-fast', 'Commandes'],
    'notifications_b2b.php' => ['fa-bell', 'Notifications'],
];
?>
<nav class="b2b-nav mb-3" aria-label="Module B2B">
    <?php foreach ($onglets_b2b as $page_b2b => [$icone_b2b, $libelle_b2b]): ?>
        <a href="<?= $page_b2b ?>" class="<?= ($current ?? '') === $page_b2b ? 'active' : '' ?>"<?= ($current ?? '') === $page_b2b ? ' aria-current="page"' : '' ?>>
            <i class="fas <?= $icone_b2b ?>"></i> <?= $libelle_b2b ?>
            <?php if ($page_b2b === 'notifications_b2b.php' && !empty($nb_non_lues)): ?>
                <span class="badge rounded-pill text-bg-danger"><?= (int) $nb_non_lues ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
