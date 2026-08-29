<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - FactuPro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-body fade-in">

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
