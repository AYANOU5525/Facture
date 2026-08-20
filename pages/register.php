<?php
session_start();
require_once '../includes/csrf.php';
require_once '../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $company_name = trim($_POST['company_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($company_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'Tous les champs sont obligatoires';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } else {
        try {
            $pdo->beginTransaction();

            // Créer l'entreprise
            $stmt = $pdo->prepare("INSERT INTO Entreprise (Nom_Entreprise) VALUES (?)");
            $stmt->execute([$company_name]);
            $entreprise_id = $pdo->lastInsertId();

            // Créer l'utilisateur (premier utilisateur = admin)
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO Utilisateur (Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise) VALUES (?, ?, ?, 'admin', ?)");
            $stmt->execute([$username, $email, $password_hash, $entreprise_id]);

            $pdo->commit();

            $success = 'Compte créé avec succès ! Redirection vers la connexion...';
            header('Refresh: 2; url=login.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $error = 'Ce nom d\'utilisateur ou email existe déjà';
            } else {
                $error = 'Erreur lors de la création du compte';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - FactuPro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="login-body fade-in">
    <div class="authentication-card" style="max-width: 500px;">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-rocket"></i>
            </div>
            <h1 style="font-size: 1.8rem; margin-bottom: 5px;">Créer un compte</h1>
            <p style="color: var(--text-muted);">Rejoignez FactuPro et gérez votre entreprise</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label><i class="fas fa-building" style="margin-right: 8px; color: var(--primary);"></i>Nom de l'entreprise</label>
                <input type="text" name="company_name" class="form-control" placeholder="Ma Super Entreprise" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user" style="margin-right: 8px; color: var(--primary);"></i>Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" placeholder="admin_user" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-envelope" style="margin-right: 8px; color: var(--primary);"></i>Email</label>
                <input type="email" name="email" class="form-control" placeholder="contact@entreprise.com" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock" style="margin-right: 8px; color: var(--primary);"></i>Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password"
                           id="reg-password"
                           name="password"
                           class="form-control"
                           placeholder="Minimum 6 caractères"
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
                <div style="margin-top:6px;">
                    <div style="height:5px; border-radius:3px; background:#e9ecef;">
                        <div id="reg-strength-fill" style="height:100%; width:0%; border-radius:3px; transition:all .3s;"></div>
                    </div>
                    <small id="reg-strength-label" style="color:#999;"></small>
                </div>
                <!-- Critères -->
                <ul id="reg-criteria" style="list-style:none; padding:0; margin:8px 0 0; font-size:0.8rem; color:#999;">
                    <li id="c-len"><i class="fas fa-circle" style="font-size:.5rem;"></i> Au moins 6 caractères</li>
                    <li id="c-upp"><i class="fas fa-circle" style="font-size:.5rem;"></i> Une lettre majuscule</li>
                    <li id="c-num"><i class="fas fa-circle" style="font-size:.5rem;"></i> Un chiffre</li>
                </ul>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock" style="margin-right: 8px; color: var(--primary);"></i>Confirmer le mot de passe</label>
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
                <small id="reg-match-label" style="display:block; margin-top:4px;"></small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1rem;">
                <i class="fas fa-user-plus"></i> Créer mon compte
            </button>
        </form>

        <div style="text-align: center; margin-top: 25px; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
            <p style="color: var(--text-muted);">
                Déjà un compte ? <a href="login.php" style="color: var(--primary); font-weight: 600;">Se connecter</a>
            </p>
        </div>
    </div>
</body>

<style>
.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.password-wrapper .form-control {
    padding-right: 46px;
}
.password-toggle {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 1rem;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}
.password-toggle:hover {
    color: var(--primary);
}
</style>

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
        label.textContent = '✔ Les mots de passe correspondent';
        label.style.color = '#28a745';
    } else {
        label.textContent = '✖ Les mots de passe ne correspondent pas';
        label.style.color = '#dc3545';
    }
}
</script>

</html>