<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - FactuPro</title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
    <!-- Fonts (auto-hébergées, hors ligne) -->
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
                <i class="fas fa-cube"></i>
            </div>
            <h1 class="fs-3 mb-1">Bienvenue sur FactuPro</h1>
            <p class="text-body-secondary mb-0">Gérez votre facturation et votre réseau B2B</p>
        </div>

        <?php if ($unverified_email): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <?= htmlspecialchars($error) ?>
                    <a href="confirm_email.php?email=<?= urlencode($unverified_email) ?>" class="fw-semibold d-block mt-1">
                        <i class="fas fa-envelope-open-text"></i> Confirmer mon email
                    </a>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?><?php if ($locked_seconds > 0): ?> <span id="lock-countdown"></span><?php endif; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label">Nom d'utilisateur ou Email</label>
                <input type="text"
                       id="login-username"
                       name="username"
                       class="form-control"
                       placeholder="Ex: admin_fourni ou contact@email.com"
                       required
                       autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password"
                           id="login-password"
                           name="password"
                           class="form-control"
                           placeholder="Votre mot de passe"
                           required>
                    <button type="button"
                            id="login-password-toggle"
                            class="password-toggle"
                            onclick="togglePassword('login-password', this)"
                            title="Afficher / masquer le mot de passe"
                            aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" id="login-submit-btn" class="btn btn-primary w-100 py-2">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="forgot_password.php" class="text-body-secondary small">
                <i class="fas fa-lock"></i> Mot de passe oublié ?
            </a>
        </div>

        <div class="text-center mt-3 mb-2">
            <p class="text-body-secondary mb-0">
                Pas encore de compte ?
                <a href="register.php" class="fw-semibold">S'inscrire gratuitement</a>
            </p>
        </div>
    </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>

<script>
function togglePassword(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        btn.setAttribute('aria-label', 'Masquer le mot de passe');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        btn.setAttribute('aria-label', 'Afficher le mot de passe');
    }
}

<?php $lockedSeconds = (int) ($locked_seconds ?? 0); ?>
<?php if ($lockedSeconds > 0): ?>
(function () {
    var remaining = <?= $lockedSeconds ?>;
    var fields = [
        document.getElementById('login-username'),
        document.getElementById('login-password'),
        document.getElementById('login-password-toggle'),
        document.getElementById('login-submit-btn'),
    ];
    var countdownEl = document.getElementById('lock-countdown');

    function formatDuration(s) {
        if (s >= 60) {
            var m = Math.floor(s / 60), r = s % 60;
            return r > 0 ? (m + ' min ' + r + 's') : (m + ' min');
        }
        return s + 's';
    }

    function tick() {
        fields.forEach(function (el) { if (el) { el.disabled = true; } });
        if (countdownEl) {
            countdownEl.textContent = '(' + formatDuration(remaining) + ')';
        }
        if (remaining <= 0) {
            fields.forEach(function (el) { if (el) { el.disabled = false; } });
            if (countdownEl) { countdownEl.textContent = ''; }
            return;
        }
        remaining--;
        setTimeout(tick, 1000);
    }

    tick();
})();
<?php endif; ?>
</script>

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

</html>
