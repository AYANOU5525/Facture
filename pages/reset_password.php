<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once '../includes/csrf.php';
require_once '../config/db.php';

$token      = trim($_GET['token'] ?? '');
$user       = null;
$reset_row  = null;
$message    = '';
$message_type = '';
$token_valid  = false;

// Valider le token
if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT r.Id_Reset, r.Id_Utilisateur, r.Expire_At, u.Nom_Utilisateur, u.Email_Utilisateur
        FROM Password_Reset r
        JOIN Utilisateur u ON u.Id_Utilisateur = r.Id_Utilisateur
        WHERE r.Token = ? AND r.Utilise = 0 AND r.Expire_At > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset_row = $stmt->fetch();

    if ($reset_row) {
        $token_valid = true;
        $user        = $reset_row;
    } else {
        $message      = "Ce lien est invalide ou a expiré. Faites une nouvelle demande.";
        $message_type = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    requireCsrf();
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 8) {
        $message      = "Le mot de passe doit contenir au moins 8 caractères.";
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message      = "Les mots de passe ne correspondent pas.";
        $message_type = 'danger';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE Utilisateur SET Mot_De_Passe_Utilisateur = ? WHERE Id_Utilisateur = ?")
            ->execute([$hash, $reset_row['Id_Utilisateur']]);
        $pdo->prepare("UPDATE Password_Reset SET Utilise = 1 WHERE Id_Reset = ?")
            ->execute([$reset_row['Id_Reset']]);

        $message      = "Mot de passe mis à jour avec succès ! Vous pouvez vous connecter.";
        $message_type = 'success';
        $token_valid  = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - FactuPro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-body fade-in">

    <div class="authentication-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1 style="font-size: 1.8rem; margin-bottom: 5px;">Nouveau mot de passe</h1>
            <?php if ($token_valid): ?>
                <p style="color: var(--text-muted);">Bonjour <strong><?= htmlspecialchars($user['Nom_Utilisateur']) ?></strong>, choisissez un nouveau mot de passe.</p>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($token_valid): ?>
            <form method="POST" id="resetForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label>Nouveau mot de passe</label>
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
                    <div style="margin-top:6px;">
                        <div id="strength-bar" style="height:5px; border-radius:3px; background:#e9ecef; transition:all 0.3s;">
                            <div id="strength-fill" style="height:100%; width:0%; border-radius:3px; transition:all 0.3s;"></div>
                        </div>
                        <small id="strength-label" style="color:#999;"></small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirmer le mot de passe</label>
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
                    <small id="match-label" style="margin-top:4px; display:block;"></small>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:1rem;">
                    <i class="fas fa-save"></i> Enregistrer le mot de passe
                </button>
            </form>
        <?php elseif ($message_type === 'success'): ?>
            <div style="text-align:center; margin-top:10px;">
                <a href="login.php" class="btn btn-primary" style="width:100%; padding:14px;">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
            </div>
        <?php else: ?>
            <div style="text-align:center; margin-top:10px;">
                <a href="forgot_password.php" class="btn btn-primary" style="width:100%; padding:14px;">
                    <i class="fas fa-redo"></i> Faire une nouvelle demande
                </a>
            </div>
        <?php endif; ?>

        <div style="text-align:center; margin-top:20px;">
            <a href="login.php" style="color:var(--primary); font-weight:600;">
                <i class="fas fa-arrow-left"></i> Retour à la connexion
            </a>
        </div>
    </div>

<style>
.password-wrapper { position:relative; display:flex; align-items:center; }
.password-wrapper .form-control { padding-right:46px; }
.password-toggle {
    position:absolute; right:12px; background:none; border:none;
    cursor:pointer; color:var(--text-muted); font-size:1rem; padding:0; transition:color .2s;
}
.password-toggle:hover { color:var(--primary); }
</style>

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
        label.textContent = '✔ Les mots de passe correspondent';
        label.style.color = '#28a745';
    } else {
        label.textContent = '✖ Les mots de passe ne correspondent pas';
        label.style.color = '#dc3545';
    }
}
</script>

</body>
</html>
