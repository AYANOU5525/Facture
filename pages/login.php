<?php
session_start();
require_once '../includes/csrf.php';
require_once '../config/db.php';

$login_max_attempts = 5;
$login_lock_seconds = 900;

function loginAttemptFile(string $username): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', strtolower($username) . '|' . $ip);
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factupro_login_' . $key . '.json';
}

function loginAttemptState(string $username): array
{
    $file = loginAttemptFile($username);
    if (!is_file($file)) {
        return ['attempts' => 0, 'locked_until' => 0];
    }

    $state = json_decode((string) file_get_contents($file), true);
    return is_array($state) ? $state : ['attempts' => 0, 'locked_until' => 0];
}

function saveLoginAttemptState(string $username, array $state): void
{
    file_put_contents(loginAttemptFile($username), json_encode($state), LOCK_EX);
}

function clearLoginAttemptState(string $username): void
{
    $file = loginAttemptFile($username);
    if (is_file($file)) {
        unlink($file);
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        $state = loginAttemptState($username);
        if (($state['locked_until'] ?? 0) > time()) {
            $minutes = max(1, (int) ceil(($state['locked_until'] - time()) / 60));
            $error = "Trop de tentatives. Réessayez dans {$minutes} minute(s).";
        } else {
            if (($state['locked_until'] ?? 0) > 0) {
                $state = ['attempts' => 0, 'locked_until' => 0];
            }

            $stmt = $pdo->prepare("SELECT Id_Utilisateur, Nom_Utilisateur, Role_Utilisateur, Id_Entreprise, Mot_De_Passe_Utilisateur FROM Utilisateur WHERE Nom_Utilisateur = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['Mot_De_Passe_Utilisateur'])) {
                clearLoginAttemptState($username);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['Id_Utilisateur'];
                $_SESSION['username'] = $user['Nom_Utilisateur'];
                $_SESSION['role'] = $user['Role_Utilisateur'];
                $_SESSION['entreprise_id'] = $user['Id_Entreprise'];
                header('Location: dashboard.php');
                exit();
            }

            $attempts = (int) ($state['attempts'] ?? 0) + 1;
            $new_state = ['attempts' => $attempts, 'locked_until' => 0];
            if ($attempts >= $login_max_attempts) {
                $new_state['locked_until'] = time() + $login_lock_seconds;
            }
            saveLoginAttemptState($username, $new_state);
            $error = 'Nom d\'utilisateur ou mot de passe incorrect';
        }
    }
}
?>
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

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="login-body fade-in">

    <div class="authentication-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-cube"></i>
            </div>
            <h1 style="font-size: 1.8rem; margin-bottom: 5px;">Bienvenue sur FactuPro</h1>
            <p style="color: var(--text-muted);">Gérez votre facturation et votre réseau B2B</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" 
                       name="username" 
                       class="form-control" 
                       placeholder="Ex: admin_fourni" 
                       required 
                       autofocus>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
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

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1rem;">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>

        <div style="text-align: center; margin-top: 20px; margin-bottom: 20px;">
            <p style="color: var(--text-muted);">
                Pas encore de compte ? 
                <a href="register.php" style="color: var(--primary); font-weight: 600;">S'inscrire gratuitement</a>
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