<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - FactuPro</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/theme.css') ?>">
    <script src="../assets/js/page-loader.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/page-loader.js') ?>"></script>
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="login-body fade-in">

    <div class="authentication-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="fs-3 mb-1">Nouveau mot de passe</h1>
            <?php if ($token_valid): ?>
                <p class="text-body-secondary mb-0">Bonjour <strong><?= htmlspecialchars($user['Nom_Utilisateur']) ?></strong>, choisissez un nouveau mot de passe.</p>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> d-flex align-items-center gap-2">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($token_valid): ?>
            <form method="POST" id="resetForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-3">
                    <label class="form-label">Nouveau mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password"
                               id="new_password"
                               name="new_password"
                               class="form-control"
                               placeholder="Minimum 8 caractères"
                               required
                               autofocus
                               oninput="checkStrength(this.value); checkMatch()">
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <!-- Barre de force -->
                    <div class="mt-2">
                        <div id="strength-bar" class="rounded" style="height:5px; background:var(--bs-secondary-bg); transition:all 0.3s;">
                            <div id="strength-fill" class="rounded" style="height:100%; width:0%; transition:all 0.3s;"></div>
                        </div>
                        <small id="strength-label" class="text-body-secondary"></small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirmer le mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password"
                               id="confirm_password"
                               name="confirm_password"
                               class="form-control"
                               placeholder="Répétez le mot de passe"
                               required
                               oninput="checkMatch()">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small id="match-label" class="d-block mt-1"></small>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-save"></i> Enregistrer le mot de passe
                </button>
            </form>
        <?php elseif ($message_type === 'success'): ?>
            <div class="text-center mt-2">
                <a href="login.php" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
            </div>
        <?php else: ?>
            <div class="text-center mt-2">
                <a href="forgot_password.php" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-redo"></i> Faire une nouvelle demande
                </a>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="login.php" class="fw-semibold">
                <i class="fas fa-arrow-left"></i> Retour à la connexion
            </a>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkStrength(val) {
    const fill  = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    let score = 0;
    if (val.length >= 8)                   score++;
    if (/[A-Z]/.test(val))                 score++;
    if (/[0-9]/.test(val))                 score++;
    if (/[^A-Za-z0-9]/.test(val))          score++;

    const levels = [
        { pct: '0%',   color: '#e9ecef', text: '' },
        { pct: '25%',  color: '#dc3545', text: 'Très faible' },
        { pct: '50%',  color: '#fd7e14', text: 'Faible' },
        { pct: '75%',  color: '#ffc107', text: 'Moyen' },
        { pct: '100%', color: '#28a745', text: 'Fort' },
    ];
    const l = levels[score];
    fill.style.width = l.pct;
    fill.style.background = l.color;
    label.textContent = l.text;
    label.style.color = l.color;
}

function checkMatch() {
    const p1    = document.getElementById('new_password').value;
    const p2    = document.getElementById('confirm_password').value;
    const label = document.getElementById('match-label');
    if (!p2) { label.textContent = ''; return; }
    if (p1 === p2) {
        label.innerHTML = '<i class="fas fa-circle-check me-1"></i>Les mots de passe correspondent';
        label.style.color = '#28a745';
    } else {
        label.innerHTML = '<i class="fas fa-circle-xmark me-1"></i>Les mots de passe ne correspondent pas';
        label.style.color = '#dc3545';
    }
}
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

</body>
</html>
