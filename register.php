<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            $success = 'Compte créé avec succès ! Vous pouvez vous connecter.';
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
    <link rel="stylesheet" href="style.css">
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
                <input type="password" name="password" class="form-control" placeholder="Minimum 6 caractères" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock" style="margin-right: 8px; color: var(--primary);"></i>Confirmer le mot de passe</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirmez votre mot de passe" required>
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

</html>