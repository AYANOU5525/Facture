<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

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

    // 1. AJOUT
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $email = trim($_POST['email']);

        if (empty($username) || empty($password) || empty($email)) {
            $error = "Tous les champs sont requis.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Utilisateur WHERE Nom_Utilisateur = ? OR Email_Utilisateur = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Ce nom d'utilisateur ou cet email est déjà pris.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO Utilisateur (Nom_Utilisateur, Email_Utilisateur, Mot_De_Passe_Utilisateur, Role_Utilisateur, Id_Entreprise) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $hash, $role, $entreprise_id])) {
                    $success = "Utilisateur ajouté avec succès !";
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
            $stmt = $pdo->prepare("SELECT Role_Utilisateur FROM Utilisateur WHERE Id_Utilisateur = ? AND Id_Entreprise = ?");
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
$stmt = $pdo->prepare("SELECT * FROM Utilisateur WHERE Id_Entreprise = ? ORDER BY Nom_Utilisateur");
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
                                    <?php if ($u['Id_Utilisateur'] == $_SESSION['user_id']): ?> <small>(Vous)</small> <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['Email_Utilisateur']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $u['Role_Utilisateur'] === 'admin' ? 'primary' : 'secondary' ?>">
                                        <?= ucfirst($u['Role_Utilisateur']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['Id_Utilisateur'] != $_SESSION['user_id']): ?>
                                        <button onclick="openModal(<?= $u['Id_Utilisateur'] ?>, '<?= $u['Nom_Utilisateur'] ?>')" class="btn btn-sm btn-info" style="padding: 5px 10px; font-size: 0.8em;">
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
            <input type="hidden" name="action" value="switch">
            <input type="hidden" name="target_id" id="modalTargetId">

            <div class="form-group">
                <input type="password" name="admin_password" class="form-control" placeholder="Votre mot de passe actuel" required autofocus>
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
