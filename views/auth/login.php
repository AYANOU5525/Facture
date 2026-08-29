<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - FactuPro</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="login-body fade-in">

    <div class="authentication-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-cube"></i>
            </div>
            <h1 class="fs-3 mb-1">Bienvenue sur FactuPro</h1>
            <p class="text-body-secondary mb-0">Gérez votre facturation et votre réseau B2B</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label">Nom d'utilisateur ou Email</label>
                <input type="text"
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
                            class="password-toggle"
                            onclick="togglePassword('login-password', this)"
                            title="Afficher / masquer le mot de passe"
                            aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
</script>

</html>
