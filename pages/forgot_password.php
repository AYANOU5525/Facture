<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once '../includes/csrf.php';
require_once '../config/db.php';
require_once '../includes/b2b_helpers.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Veuillez saisir une adresse email valide.";
        $message_type = 'danger';
    } else {
        $stmt = $pdo->prepare("SELECT Id_Utilisateur, Nom_Utilisateur FROM Utilisateur WHERE Email_Utilisateur = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // On affiche toujours le même message pour ne pas révéler l'existence du compte
        $message = "Si un compte est associé à cet email, un lien de réinitialisation a été envoyé.";
        $message_type = 'success';

        if ($user) {
            // Invalider les anciens tokens de cet utilisateur
            $pdo->prepare("UPDATE Password_Reset SET Utilise = 1 WHERE Id_Utilisateur = ? AND Utilise = 0")
                ->execute([$user['Id_Utilisateur']]);

            // Générer un token sécurisé
            $token     = bin2hex(random_bytes(32));
            $expire_at = date('Y-m-d H:i:s', time() + 3600); // 1 heure

            $pdo->prepare("INSERT INTO Password_Reset (Id_Utilisateur, Token, Expire_At) VALUES (?, ?, ?)")
                ->execute([$user['Id_Utilisateur'], $token, $expire_at]);

            $app_url  = rtrim($_ENV['APP_URL'] ?? 'http://localhost/facturation', '/');
            $link     = $app_url . '/pages/reset_password.php?token=' . urlencode($token);

            $corps = "Bonjour {$user['Nom_Utilisateur']},\n\n"
                   . "Vous avez demandé la réinitialisation de votre mot de passe FactuPro.\n\n"
                   . "Cliquez sur le lien ci-dessous pour choisir un nouveau mot de passe :\n"
                   . $link . "\n\n"
                   . "Ce lien est valable 1 heure. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.\n\n"
                   . "Cordialement,\nFactuPro";

            envoyerEmailB2b($email, "[FactuPro] Réinitialisation de votre mot de passe", $corps);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - FactuPro</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-body fade-in">

    <div class="authentication-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-lock-open"></i>
            </div>
            <h1 style="font-size: 1.8rem; margin-bottom: 5px;">Mot de passe oublié</h1>
            <p style="color: var(--text-muted);">Entrez votre email pour recevoir un lien de réinitialisation</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($message_type !== 'success'): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label>Adresse email de votre compte</label>
                    <input type="email"
                           name="email"
                           class="form-control"
                           placeholder="votre@email.com"
                           required
                           autofocus>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:1rem;">
                    <i class="fas fa-paper-plane"></i> Envoyer le lien
                </button>
            </form>
        <?php endif; ?>

        <div style="text-align:center; margin-top:20px;">
            <a href="login.php" style="color:var(--primary); font-weight:600;">
                <i class="fas fa-arrow-left"></i> Retour à la connexion
            </a>
        </div>
    </div>

</body>
</html>
