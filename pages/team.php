
<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/b2b_helpers.php';

// Sécurité : Admin seulement
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$page_title = "Gestion de l'équipe";
include '../includes/header.php';

// ID Entreprise
$stmt = $pdo->prepare("SELECT Id_Entreprise, Mot_De_Passe_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_admin = $stmt->fetch();
$entreprise_id = $current_admin['Id_Entreprise'];

$success = '';
$error = '';

// === TRAITEMENT DU FORMULAIRE (AJOUT) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();

    // 1. AJOUT
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $email = trim($_POST['email']);

        if (empty($username) || empty($password) || empty($email)) {
            $error = "Tous les champs sont requis.";
        } else {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM Utilisateur WHERE Nom_Utilisateur = ? OR Email_Utilisateur = ?"
            );
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Ce nom d'utilisateur ou cet email est déjà pris.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO Utilisateur "
                    . "(Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise) "
                    . "VALUES (?, ?, ?, ?, ?)"
                );
                if ($stmt->execute([$username, $email, $hash, $role, $entreprise_id])) {
                    $success = "Utilisateur ajouté avec succès ! Un email de bienvenue a été envoyé à $email.";
                    $role_label  = $role === 'admin' ? 'Administrateur' : 'Employé';
                    $app_url     = rtrim($_ENV['APP_URL'] ?? 'http://localhost/facturation', '/');
                    $login_url   = $app_url . '/pages/login.php';
                    $from_name   = htmlspecialchars($_ENV['MAIL_FROM_NAME'] ?? 'FactuPro', ENT_QUOTES);

                    // Récupérer le nom de l'entreprise pour personnaliser l'email
                    $stmt_ent = $pdo->prepare("SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
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
                } else {
                    $error = "Erreur lors de l'ajout.";
                }
            }
        }
    }

    // 2. CHANGEMENT DE RÔLE (AVEC SÉCURITÉ)
    elseif ($_POST['action'] === 'switch') {
        $target_id = $_POST['target_id'];
        $admin_password = $_POST['admin_password'];

        // A. Vérifier le mot de passe de l'admin
        if (!password_verify($admin_password, $current_admin['Mot_De_Passe_Utilisateur'])) {
            $error = "Mot de passe administrateur incorrect. Action annulée.";
        } else {
            // B. Vérifier que la cible est bien dans mon entreprise
            $stmt = $pdo->prepare(
                "SELECT Role_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = ? AND Id_Entreprise = ?"
            );
            $stmt->execute([$target_id, $entreprise_id]);
            $target_user_role = $stmt->fetchColumn();

            if ($target_user_role) {
                if ($target_id == $_SESSION['user_id']) {
                    $error = "Vous ne pouvez pas modifier votre propre rôle ici.";
                } else {
                    $new_role = ($target_user_role === 'admin') ? 'utilisateur' : 'admin';
                    $upd = $pdo->prepare("UPDATE Utilisateur SET Role_Utilisateur = ? WHERE Id_Utilisateur = ?");
                    $upd->execute([$new_role, $target_id]);
                    $success = "Rôle modifié avec succès (Maintenant : " . ucfirst($new_role) . ").";
                }
            } else {
                $error = "Utilisateur introuvable.";
            }
        }
    }
}

// LISTE DES MEMBRES
$stmt = $pdo->prepare(
    "SELECT Id_Utilisateur, Nom_Utilisateur, Email_Utilisateur, Role_Utilisateur "
    . "FROM Utilisateur WHERE Id_Entreprise = ? ORDER BY Nom_Utilisateur"
);
$stmt->execute([$entreprise_id]);
$membres = $stmt->fetchAll();
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Gestion de l'équipe</h1>
    </div>

    <?php if ($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>

    <div class="row" style="display:flex; gap:30px; flex-wrap:wrap;">

        <!-- GAUCHE : AJOUT -->
        <div style="flex: 1; min-width: 300px;">
            <div class="card">
                <h3><i class="fas fa-user-plus"></i> Nouveau Membre</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Nom d'utilisateur</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Mot de passe provisoire</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Rôle</label>
                        <select name="role" class="form-control">
                            <option value="utilisateur">Employé (Accès limité)</option>
                            <option value="admin">Administrateur (Accès total)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;">Ajouter</button>
                </form>
            </div>
        </div>

        <!-- DROITE : LISTE -->
        <div style="flex: 2; min-width: 300px;">
            <div class="card">
                <h3><i class="fas fa-list"></i> Membres existants</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membres as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($u['Nom_Utilisateur']) ?></strong>
                                    <?php if ($u['Id_Utilisateur'] == $_SESSION['user_id']): ?>
                                        <small>(Vous)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['Email_Utilisateur']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $u['Role_Utilisateur'] === 'admin'
                                        ? 'primary'
                                        : 'secondary' ?>">
                                        <?= ucfirst($u['Role_Utilisateur']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['Id_Utilisateur'] != $_SESSION['user_id']): ?>
                                        <button onclick="openModal(<?= $u['Id_Utilisateur'] ?>, '<?= htmlspecialchars($u['Nom_Utilisateur'], ENT_QUOTES) ?>')"
                                                class="btn btn-sm btn-info"
                                                style="padding: 5px 10px; font-size: 0.8em;">
                                            <i class="fas fa-sync-alt"></i> Changer rôle
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODALE DE SÉCURITÉ -->
<div id="securityModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3><i class="fas fa-lock"></i> Confirmation de sécurité</h3>
        <p>Vous êtes sur le point de changer le rôle de <strong id="modalUserName"></strong>.</p>
        <p>Veuillez confirmer votre mot de passe administrateur pour continuer :</p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="switch">
            <input type="hidden" name="target_id" id="modalTargetId">

            <div class="form-group">
                <input type="password" 
                       name="admin_password" 
                       class="form-control" 
                       placeholder="Votre mot de passe actuel" 
                       required 
                       autofocus>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Style simple pour la modale */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        animation: slideDown 0.3s;
    }

    @keyframes slideDown {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<script>
    function openModal(id, name) {
        document.getElementById('modalTargetId').value = id;
        document.getElementById('modalUserName').innerText = name;
        document.getElementById('securityModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('securityModal').style.display = 'none';
    }
    // Fermer si clic dehors
    window.onclick = function(event) {
        if (event.target == document.getElementById('securityModal')) {
            closeModal();
        }
    }
</script>

</body>

</html>
