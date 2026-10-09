<div class="container fade-in py-4">
    <div class="dash-hero">
        <div>
            <span class="dash-hero-date"><i class="far fa-calendar"></i> <?= date('d/m/Y') ?></span>
            <h1 class="dash-hero-title"><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
            <p class="dash-hero-text">Livraisons</p>
        </div>
        <img src="../assets/img/illustrations/banner-delivery.svg" alt="" class="dash-hero-art">
    </div>

    <div class="card">
        <div class="empty-state py-5">
            <img src="../assets/img/illustrations/empty-box.svg" alt="" class="empty-state-img">
            <p class="empty-state-title">La gestion des livraisons est temporairement désactivée.</p>
            <p class="empty-state-text">Contactez le propriétaire de l'entreprise si vous pensez qu'il s'agit d'une erreur.</p>
        </div>
    </div>
</div>
