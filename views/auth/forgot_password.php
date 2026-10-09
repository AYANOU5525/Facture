<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - FactuPro</title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
    <link rel="stylesheet" href="../assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/theme.css') ?>">
    <script src="../assets/js/page-loader.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/page-loader.js') ?>"></script>
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="login-body fade-in">

    <div class="auth-shell">
    <?php require __DIR__ . '/../partials/auth_showcase.php'; ?>

    <div class="authentication-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-lock-open"></i>
            </div>
            <h1 class="fs-3 mb-1">Mot de passe oublié</h1>
            <p class="text-body-secondary mb-0">Entrez votre email pour recevoir un lien de réinitialisation</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> d-flex align-items-center gap-2">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($message_type !== 'success'): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-3">
                    <label class="form-label">Adresse email de votre compte</label>
                    <input type="email"
                           name="email"
                           class="form-control"
                           placeholder="votre@email.com"
                           required
                           autofocus>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-paper-plane"></i> Envoyer le lien
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="login.php" class="fw-semibold">
                <i class="fas fa-arrow-left"></i> Retour à la connexion
            </a>
        </div>
    </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        /* Désactive le bouton submit + affiche un spinner à l'envoi, pour ne pas laisser le
           formulaire paraître figé pendant l'attente serveur (page standalone, pas de header.php). */
        document.addEventListener('submit', function(e) {
            if (e.defaultPrevented) return;
            var form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])').forEach(function(btn) {
                if (btn.disabled) return;
                btn.disabled = true;
                if (btn.tagName === 'BUTTON') {
                    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> ' + (btn.dataset.loadingText || 'Chargement…');
                }
            });
        });
    </script>
</body>
</html>
