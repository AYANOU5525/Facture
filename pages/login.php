<?php
session_start();
require_once '../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM Utilisateur WHERE Nom_Utilisateur = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['Mot_De_Passe_Utilisateur'])) {
            $_SESSION['user_id'] = $user['Id_Utilisateur'];
            $_SESSION['username'] = $user['Nom_Utilisateur'];
            $_SESSION['role'] = $user['Role_Utilisateur'];
            $_SESSION['entreprise_id'] = $user['Id_Entreprise'];
            header('Location: dashboard.php');
            exit();
        } else {
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
            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" placeholder="Ex: admin_fourni" required autofocus>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" class="form-control" placeholder="Votre mot de passe" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1rem;">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>

        <div style="text-align: center; margin-top: 20px; margin-bottom: 20px;">
            <p style="color: var(--text-muted);">Pas encore de compte ? <a href="register.php" style="color: var(--primary); font-weight: 600;">S'inscrire gratuitement</a></p>
        </div>
    </div>

</body>

</html>