<div class="container fade-in py-4">
    <div class="mb-3">
        <h1 class="fs-4 fw-bold mb-1"><?= $salutation ?>, <?= htmlspecialchars($_SESSION['username']) ?></h1>
        <p class="text-body-secondary small mb-0">Livraisons — <?= date('d/m/Y') ?></p>
    </div>

    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-truck-loading fs-1 d-block mb-3 opacity-50"></i>
            <p class="mb-1 fw-semibold">La gestion des livraisons est temporairement désactivée.</p>
            <p class="text-body-secondary small mb-0">Contactez le propriétaire de l'entreprise si vous pensez qu'il s'agit d'une erreur.</p>
        </div>
    </div>
</div>
