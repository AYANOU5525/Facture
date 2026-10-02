<?php

namespace App\Controllers;

/** Contrôleur de pages/team.php — gestion des membres de l'équipe (ajout, changement de rôle). */
class TeamController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutGererEquipe());

        $entreprise_id = (int) $_SESSION['entreprise_id'];

        $success = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            exigerCsrf();

            if ($_POST['action'] === 'add') {
                [$success, $error] = $this->handleAdd($entreprise_id);
            } elseif ($_POST['action'] === 'switch') {
                [$success, $error] = $this->handleSwitch($entreprise_id);
            }
        }

        $stmt = $this->pdo->prepare(
            "SELECT Id_Utilisateur, Nom_Utilisateur, Email_Utilisateur, Role_Utilisateur "
            . "FROM Utilisateur WHERE Id_Entreprise = ? ORDER BY Nom_Utilisateur"
        );
        $stmt->execute([$entreprise_id]);
        $membres = $stmt->fetchAll();

        $this->render('team/index', [
            'success'  => $success,
            'error'    => $error,
            'membres'  => $membres,
        ], "Gestion de l'équipe");
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleAdd(int $entreprise_id): array
    {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'] ?? '';
        $email = trim($_POST['email']);
        $assignable_roles = FEATURE_LOGISTIQUE_ACTIVE ? ['proprio', 'vendeur', 'livreur'] : ['proprio', 'vendeur'];

        if (empty($username) || empty($password) || empty($email)) {
            return ['', "Tous les champs sont requis."];
        }
        if (!in_array($role, $assignable_roles, true)) {
            return ['', "Rôle invalide."];
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM Utilisateur WHERE Nom_Utilisateur = ? OR Email_Utilisateur = ?"
        );
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            return ['', "Ce nom d'utilisateur ou cet email est déjà pris."];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            "INSERT INTO Utilisateur "
            . "(Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise) "
            . "VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt->execute([$username, $email, $hash, $role, $entreprise_id])) {
            return ['', "Erreur lors de l'ajout."];
        }

        $success = "Utilisateur ajouté avec succès ! Un email de bienvenue a été envoyé à $email.";
        $role_label  = $role === 'admin' ? 'Administrateur' : 'Employé';
        $app_url     = $this->appBaseUrl();
        $login_url   = $app_url . '/pages/login.php';
        $from_name   = htmlspecialchars($_ENV['MAIL_FROM_NAME'] ?? 'FactuPro', ENT_QUOTES);

        // Récupérer le nom de l'entreprise pour personnaliser l'email
        $stmt_ent = $this->pdo->prepare("SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
        $stmt_ent->execute([$entreprise_id]);
        $nom_entreprise = htmlspecialchars($stmt_ent->fetchColumn() ?: 'votre entreprise', ENT_QUOTES);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">

        <!-- EN-TÊTE -->
        <tr>
          <td style="background:#3b5bdb;padding:32px 40px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:24px;">🎉 Bienvenue sur FactuPro</h1>
            <p style="margin:8px 0 0;color:#bfcfff;font-size:14px;">Votre compte a été créé</p>
          </td>
        </tr>

        <!-- CORPS -->
        <tr>
          <td style="padding:36px 40px;">
            <p style="margin:0 0 16px;color:#333;font-size:16px;">
              Bonjour <strong>{$username}</strong>,
            </p>
            <p style="margin:0 0 24px;color:#555;font-size:14px;line-height:1.6;">
              Un compte a été créé pour vous sur <strong>FactuPro</strong> par l'administrateur de <strong>{$nom_entreprise}</strong>.
              Voici vos identifiants de connexion :
            </p>

            <!-- CARTE IDENTIFIANTS -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f0f4ff;border-left:4px solid #3b5bdb;border-radius:6px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 24px;">
                  <table cellpadding="4" cellspacing="0">
                    <tr>
                      <td style="color:#666;font-size:13px;width:160px;">Nom d'utilisateur</td>
                      <td style="color:#1a1a2e;font-weight:bold;font-size:14px;">{$username}</td>
                    </tr>
                    <tr>
                      <td style="color:#666;font-size:13px;">Mot de passe provisoire</td>
                      <td style="color:#1a1a2e;font-weight:bold;font-size:14px;font-family:monospace;">{$password}</td>
                    </tr>
                    <tr>
                      <td style="color:#666;font-size:13px;">Rôle</td>
                      <td style="color:#1a1a2e;font-weight:bold;font-size:14px;">{$role_label}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <!-- BOUTON -->
            <p style="text-align:center;margin:0 0 24px;">
              <a href="{$login_url}"
                 style="display:inline-block;background:#3b5bdb;color:#ffffff;text-decoration:none;
                        padding:14px 32px;border-radius:8px;font-size:15px;font-weight:bold;">
                Se connecter maintenant →
              </a>
            </p>

            <!-- AVERTISSEMENT -->
            <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:14px 18px;margin-bottom:16px;">
              <p style="margin:0;color:#7a5800;font-size:13px;">
                ⚠️ <strong>Important :</strong> Ce mot de passe est provisoire.
                Veuillez le changer dès votre première connexion via <em>Paramètres → Sécurité</em>.
              </p>
            </div>

            <p style="margin:0;color:#888;font-size:12px;line-height:1.5;">
              Si vous n'êtes pas à l'origine de cette demande ou si vous pensez avoir reçu cet email par erreur,
              vous pouvez l'ignorer en toute sécurité.
            </p>
          </td>
        </tr>

        <!-- PIED DE PAGE -->
        <tr>
          <td style="background:#f8f9fa;padding:20px 40px;text-align:center;border-top:1px solid #e9ecef;">
            <p style="margin:0;color:#aaa;font-size:12px;">
              Cet email a été envoyé automatiquement par <strong>FactuPro</strong>. Ne pas répondre.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        $altText = "Bonjour $username,\n\n"
                 . "Votre compte FactuPro a été créé sur $nom_entreprise.\n\n"
                 . "Identifiants :\n"
                 . "  Nom d'utilisateur : $username\n"
                 . "  Mot de passe provisoire : $password\n"
                 . "  Rôle : $role_label\n\n"
                 . "Connectez-vous ici : $login_url\n\n"
                 . "Veuillez changer votre mot de passe à la première connexion.\n\n"
                 . "Cordialement,\nFactuPro";

        envoyerEmailB2b(
            $email,
            "🎉 Bienvenue sur FactuPro — Vos identifiants de connexion",
            $html,
            $altText
        );

        return [$success, ''];
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleSwitch(int $entreprise_id): array
    {
        $target_id = $_POST['target_id'];
        $admin_password = $_POST['admin_password'];

        // A. Vérifier le mot de passe de l'admin (hash récupéré ici, pas à chaque
        // affichage de la page : seule l'action "switch" en a besoin).
        $stmt = $this->pdo->prepare("SELECT Mot_De_Passe_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_hash = $stmt->fetchColumn();

        if (!password_verify($admin_password, $current_hash)) {
            return ['', "Mot de passe administrateur incorrect. Action annulée."];
        }

        // B. Vérifier que la cible est bien dans mon entreprise
        $stmt = $this->pdo->prepare(
            "SELECT Role_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = ? AND Id_Entreprise = ?"
        );
        $stmt->execute([$target_id, $entreprise_id]);
        $target_user_role = $stmt->fetchColumn();

        if (!$target_user_role) {
            return ['', "Utilisateur introuvable."];
        }

        if ($target_id == $_SESSION['user_id']) {
            return ['', "Vous ne pouvez pas modifier votre propre rôle ici."];
        }

        $new_role = $_POST['new_role'] ?? null;
        $allowed_roles = FEATURE_LOGISTIQUE_ACTIVE ? ['proprio', 'vendeur', 'livreur'] : ['proprio', 'vendeur'];
        if (!in_array($new_role, $allowed_roles, true)) {
            return ['', "Rôle invalide."];
        }

        $upd = $this->pdo->prepare("UPDATE Utilisateur SET Role_Utilisateur = ? WHERE Id_Utilisateur = ?");
        $upd->execute([$new_role, $target_id]);

        return ["Rôle modifié avec succès (Maintenant : " . nomRole($new_role) . ").", ''];
    }
}
