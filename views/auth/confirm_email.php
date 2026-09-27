<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation d'email - FactuPro</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="login-body fade-in">

    <div class="authentication-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h1 class="fs-3 mb-1">Confirmez votre email</h1>
            <p class="text-body-secondary mb-0">
                Un code à 6 chiffres a été envoyé
                <?php if (!empty($email)): ?>à <strong><?= htmlspecialchars($email) ?></strong><?php endif; ?>.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center gap-2">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (str_contains($success, 'confirmé avec succès') || str_contains($success, 'déjà confirmé')): ?>
            <div class="text-center mt-2">
                <a href="login.php" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="confirm">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Code de confirmation</label>
                    <input type="text" name="code" class="form-control text-center"
                           style="font-size:1.4rem; letter-spacing:0.5rem;"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           placeholder="000000" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-check"></i> Confirmer mon compte
                </button>
            </form>

            <form method="POST" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="resend">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <button type="submit" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-redo"></i> Renvoyer le code
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="login.php" class="fw-semibold">
                <i class="fas fa-arrow-left"></i> Retour à la connexion
            </a>
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
