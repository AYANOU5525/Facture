<?php
session_start();
require_once 'bdd.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nom_entreprise = $_POST['nom_entreprise'] ?? '';

    if ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Start transaction
        $pdo->beginTransaction();
        try {
            // Create enterprise
            $stmt = $pdo->prepare('INSERT INTO entreprises (nom) VALUES (:nom)');
            $stmt->execute(['nom' => $nom_entreprise]);
            $entreprise_id = $pdo->lastInsertId();

            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO utilisateurs (nom_utilisateur, email, mot_de_passe, entreprise_id, role) VALUES (:username, :email, :password, :ent_id, "admin")');
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $hashed_password,
                'ent_id' => $entreprise_id
            ]);

            $pdo->commit();
            $success = "Compte créé avec succès !";
            header("refresh:2;url=connexion.php");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription | FactuPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 15px -3px rgba(67, 97, 238, 0.4);
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }

        .error-box {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .success-box {
            background: #ecfdf5;
            color: #059669;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-input-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
        }

        .form-input-wrapper input {
            padding-left: 2.75rem;
        }

        .btn-login {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.875rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(67, 97, 238, 0.3);
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <h1 class="login-title">Rejoignez FactuPro</h1>
            <p style="color: var(--gray-600); font-size: 0.875rem;">Commencez à gérer votre business aujourd'hui</p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-box">
                <i class="fas fa-circle-check"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form action="inscription.php" method="POST">
            <div class="form-input-wrapper">
                <i class="fas fa-building"></i>
                <input type="text" name="nom_entreprise" placeholder="Nom de votre Entreprise" required value="<?= htmlspecialchars($_POST['nom_entreprise'] ?? '') ?>">
            </div>
            <div class="form-input-wrapper">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Nom d'utilisateur" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-input-wrapper">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Adresse Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Mot de passe" required>
            </div>
            <div class="form-input-wrapper">
                <i class="fas fa-lock-open"></i>
                <input type="password" name="confirm_password" placeholder="Confirmer le mot de passe" required>
            </div>
            <button type="submit" class="btn-login">
                Créer mon compte <i class="fas fa-user-plus" style="margin-left: 0.5rem;"></i>
            </button>
        </form>

        <div class="register-link">
            Déjà un compte ? <a href="connexion.php">Se connecter</a>
        </div>
    </div>
</body>

</html>