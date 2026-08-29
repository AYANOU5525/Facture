<?php

namespace App\Controllers;

/** Contrôleur des pages d'authentification (connexion, inscription, mot de passe). */
class AuthController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_LOCK_SECONDS = 900;

    public function login(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('dashboard.php');
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = 'Veuillez remplir tous les champs';
            } else {
                $state = $this->loginAttemptState($username);
                if (($state['locked_until'] ?? 0) > time()) {
                    $minutes = max(1, (int) ceil(($state['locked_until'] - time()) / 60));
                    $error = "Trop de tentatives. Réessayez dans {$minutes} minute(s).";
                } else {
                    if (($state['locked_until'] ?? 0) > 0) {
                        $state = ['attempts' => 0, 'locked_until' => 0];
                    }

                    $stmt = $this->pdo->prepare("SELECT Id_Utilisateur, Nom_Utilisateur, Role_Utilisateur, Id_Entreprise, Mot_De_Passe_Utilisateur FROM Utilisateur WHERE Nom_Utilisateur = ? OR Email_Utilisateur = ?");
                    $stmt->execute([$username, $username]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['Mot_De_Passe_Utilisateur'])) {
                        $this->clearLoginAttemptState($username);
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['Id_Utilisateur'];
                        $_SESSION['username'] = $user['Nom_Utilisateur'];
                        $_SESSION['role'] = $user['Role_Utilisateur'];
                        $_SESSION['entreprise_id'] = $user['Id_Entreprise'];
                        $this->redirect('dashboard.php');
                    }

                    $attempts = (int) ($state['attempts'] ?? 0) + 1;
                    $new_state = ['attempts' => $attempts, 'locked_until' => 0];
                    if ($attempts >= self::LOGIN_MAX_ATTEMPTS) {
                        $new_state['locked_until'] = time() + self::LOGIN_LOCK_SECONDS;
                    }
                    $this->saveLoginAttemptState($username, $new_state);
                    $error = 'Nom d\'utilisateur ou mot de passe incorrect';
                }
            }
        }

        $this->renderStandalone('auth/login', ['error' => $error]);
    }

    public function register(): void
    {
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            $company_name = trim($_POST['company_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($company_name) || empty($username) || empty($email) || empty($password)) {
                $error = 'Tous les champs sont obligatoires';
            } elseif ($password !== $confirm_password) {
                $error = 'Les mots de passe ne correspondent pas';
            } elseif (strlen($password) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caractères';
            } else {
                try {
                    $this->pdo->beginTransaction();

                    $stmt = $this->pdo->prepare("INSERT INTO Entreprise (Nom_Entreprise) VALUES (?)");
                    $stmt->execute([$company_name]);
                    $entreprise_id = $this->pdo->lastInsertId();

                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $this->pdo->prepare("INSERT INTO Utilisateur (Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise) VALUES (?, ?, ?, 'proprio', ?)");
                    $stmt->execute([$username, $email, $password_hash, $entreprise_id]);

                    $this->pdo->commit();

                    $success = 'Compte créé avec succès ! Redirection vers la connexion...';
                    header('Refresh: 2; url=login.php');
                } catch (\PDOException $e) {
                    $this->pdo->rollBack();
                    $error = $e->getCode() == 23000
                        ? 'Ce nom d\'utilisateur ou email existe déjà'
                        : 'Erreur lors de la création du compte';
                }
            }
        }

        $this->renderStandalone('auth/register', ['error' => $error, 'success' => $success]);
    }

    public function forgotPassword(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('dashboard.php');
        }

        $message = '';
        $message_type = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            $email = trim($_POST['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Veuillez saisir une adresse email valide.";
                $message_type = 'danger';
            } else {
                $stmt = $this->pdo->prepare("SELECT Id_Utilisateur, Nom_Utilisateur FROM Utilisateur WHERE Email_Utilisateur = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // On affiche toujours le même message pour ne pas révéler l'existence du compte
                $message = "Si un compte est associé à cet email, un lien de réinitialisation a été envoyé.";
                $message_type = 'success';

                if ($user) {
                    $this->pdo->prepare("UPDATE Password_Reset SET Utilise = 1 WHERE Id_Utilisateur = ? AND Utilise = 0")
                        ->execute([$user['Id_Utilisateur']]);

                    $token     = bin2hex(random_bytes(32));
                    $expire_at = date('Y-m-d H:i:s', time() + 3600); // 1 heure

                    $this->pdo->prepare("INSERT INTO Password_Reset (Id_Utilisateur, Token, Expire_At) VALUES (?, ?, ?)")
                        ->execute([$user['Id_Utilisateur'], $token, $expire_at]);

                    $app_url = rtrim($_ENV['APP_URL'] ?? 'http://localhost/facturation', '/');
                    $link    = $app_url . '/pages/reset_password.php?token=' . urlencode($token);

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

        $this->renderStandalone('auth/forgot_password', ['message' => $message, 'message_type' => $message_type]);
    }

    public function resetPassword(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('dashboard.php');
        }

        $token        = trim($_GET['token'] ?? '');
        $user         = null;
        $reset_row    = null;
        $message      = '';
        $message_type = '';
        $token_valid  = false;

        if (!empty($token)) {
            $stmt = $this->pdo->prepare("
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
            exigerCsrf();
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
                $this->pdo->prepare("UPDATE Utilisateur SET Mot_De_Passe_Utilisateur = ? WHERE Id_Utilisateur = ?")
                    ->execute([$hash, $reset_row['Id_Utilisateur']]);
                $this->pdo->prepare("UPDATE Password_Reset SET Utilise = 1 WHERE Id_Reset = ?")
                    ->execute([$reset_row['Id_Reset']]);

                $message      = "Mot de passe mis à jour avec succès ! Vous pouvez vous connecter.";
                $message_type = 'success';
                $token_valid  = false;
            }
        }

        $this->renderStandalone('auth/reset_password', [
            'user'         => $user,
            'message'      => $message,
            'message_type' => $message_type,
            'token_valid'  => $token_valid,
        ]);
    }

    private function loginAttemptFile(string $username): string
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = hash('sha256', strtolower($username) . '|' . $ip);
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'factupro_login_' . $key . '.json';
    }

    private function loginAttemptState(string $username): array
    {
        $file = $this->loginAttemptFile($username);
        if (!is_file($file)) {
            return ['attempts' => 0, 'locked_until' => 0];
        }

        $state = json_decode((string) file_get_contents($file), true);
        return is_array($state) ? $state : ['attempts' => 0, 'locked_until' => 0];
    }

    private function saveLoginAttemptState(string $username, array $state): void
    {
        file_put_contents($this->loginAttemptFile($username), json_encode($state), LOCK_EX);
    }

    private function clearLoginAttemptState(string $username): void
    {
        $file = $this->loginAttemptFile($username);
        if (is_file($file)) {
            unlink($file);
        }
    }
}
