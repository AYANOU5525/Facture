<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - FactuPro</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/theme.css') ?>">
    <script src="../assets/js/page-loader.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/page-loader.js') ?>"></script>
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
</head>

<body class="login-body fade-in">
    <div class="authentication-card" style="max-width: 500px;">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-rocket"></i>
            </div>
            <h1 class="fs-3 mb-1">Créer un compte</h1>
            <p class="text-body-secondary mb-0">Rejoignez FactuPro et gérez votre entreprise</p>
        </div>

        <?php
        $error = $error ?? '';
        $success = $success ?? '';
        ?>

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

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(jetonCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-building text-primary me-2"></i>Nom de l'entreprise</label>
                <input type="text" name="company_name" class="form-control" placeholder="Ma Super Entreprise" required>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user text-primary me-2"></i>Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" placeholder="admin_user" required>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-envelope text-primary me-2"></i>Email</label>
                <input type="email" name="email" class="form-control" placeholder="contact@entreprise.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-lock text-primary me-2"></i>Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password"
                           id="reg-password"
                           name="password"
                          class="form-control"
                           placeholder="Minimum 8 caractères"
                           required
                           oninput="checkRegStrength(this.value); checkRegMatch()">
                    <button type="button"
                            class="password-toggle"
                            onclick="togglePassword('reg-password', this)"
                            title="Afficher / masquer"
                            aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <!-- Barre de force -->
                <div class="mt-2">
                    <div class="rounded" style="height:5px; background:var(--bs-secondary-bg);">
                        <div id="reg-strength-fill" class="rounded" style="height:100%; width:0%; transition:all .3s;"></div>
                    </div>
                    <small id="reg-strength-label" class="text-body-secondary"></small>
                </div>
                <!-- Critères -->
                <ul id="reg-criteria" class="list-unstyled small text-body-secondary mt-2 mb-0">
                    <li id="c-len"><i class="fas fa-circle" style="font-size:.5rem;"></i> Au moins 6 caractères</li>
                    <li id="c-upp"><i class="fas fa-circle" style="font-size:.5rem;"></i> Une lettre majuscule</li>
                    <li id="c-num"><i class="fas fa-circle" style="font-size:.5rem;"></i> Un chiffre</li>
                </ul>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="fas fa-lock text-primary me-2"></i>Confirmer le mot de passe</label>
                <div class="password-wrapper">
                    <input type="password"
                           id="reg-confirm-password"
                           name="confirm_password"
                           class="form-control"
                           placeholder="Confirmez votre mot de passe"
                           required
                           oninput="checkRegMatch()">
                    <button type="button"
                            class="password-toggle"
                            onclick="togglePassword('reg-confirm-password', this)"
                            title="Afficher / masquer"
                            aria-label="Afficher le mot de passe">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small id="reg-match-label" class="d-block mt-1"></small>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fas fa-user-plus"></i> Créer mon compte
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <p class="text-body-secondary mb-0">
                Déjà un compte ? <a href="login.php" class="fw-semibold">Se connecter</a>
            </p>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>

<script>
function togglePassword(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon  = btn.querySelector('i');
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

function checkRegStrength(val) {
    const fill  = document.getElementById('reg-strength-fill');
    const label = document.getElementById('reg-strength-label');
    let score = 0;
    if (val.length >= 6)           score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;

    const levels = [
        { pct:'0%',   color:'#e9ecef', text:'' },
        { pct:'25%',  color:'#dc3545', text:'Très faible' },
        { pct:'50%',  color:'#fd7e14', text:'Faible' },
        { pct:'75%',  color:'#ffc107', text:'Moyen' },
        { pct:'100%', color:'#28a745', text:'Fort' },
    ];
    const l = levels[Math.min(score, 4)];
    fill.style.width      = l.pct;
    fill.style.background = l.color;
    label.textContent     = l.text;
    label.style.color     = l.color;

    // Critères visuels
    const setOk = (id, ok) => {
        const el = document.getElementById(id);
        el.style.color = ok ? '#28a745' : '#999';
        el.querySelector('i').className = ok ? 'fas fa-check-circle' : 'fas fa-circle';
        el.querySelector('i').style.fontSize = ok ? '0.7rem' : '0.5rem';
    };
    setOk('c-len', val.length >= 6);
    setOk('c-upp', /[A-Z]/.test(val));
    setOk('c-num', /[0-9]/.test(val));
}

function checkRegMatch() {
    const p1    = document.getElementById('reg-password').value;
    const p2    = document.getElementById('reg-confirm-password').value;
    const label = document.getElementById('reg-match-label');
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

</html>
