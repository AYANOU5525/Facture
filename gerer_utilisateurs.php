<?php
require_once 'auth.php';
require_once 'bdd.php';

if ($user_role !== 'admin') {
    header('Location: tableau_de_bord.php?error=acces_refuse');
    exit();
}

$message = '';
$error = '';

// Gérer la création d'un utilisateur
if (isset($_POST['create_user'])) {
    $new_username = trim($_POST['new_username']);
    $new_email = trim($_POST['new_email']);
    $new_password = $_POST['new_password'];
    $admin_password = $_POST['admin_password'];

    // 1. Vérifier le mot de passe de l'admin
    $stmt = $pdo->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $current_admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($admin_password, $current_admin['mot_de_passe'])) {
        // 2. Vérifier si l'utilisateur existe déjà
        $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE nom_utilisateur = :user OR email = :email');
        $stmt->execute(['user' => $new_username, 'email' => $new_email]);
        if ($stmt->fetch()) {
            $error = 'Ce nom d\'utilisateur ou cet email est déjà utilisé.';
        } else {
            // 3. Créer l'utilisateur rattaché à la même entreprise
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO utilisateurs (nom_utilisateur, email, mot_de_passe, role, entreprise_id) VALUES (:user, :email, :pass, "utilisateur", :ent_id)');
            if ($stmt->execute([
                'user' => $new_username,
                'email' => $new_email,
                'pass' => $hashed_password,
                'ent_id' => $_SESSION['entreprise_id']
            ])) {
                $message = "Collaborateur '$new_username' créé avec succès.";
            } else {
                $error = 'Erreur lors de la création.';
            }
        }
    } else {
        $error = 'Mot de passe administrateur incorrect. Création annulée.';
    }
}

// Gérer la modification du rôle
if (isset($_POST['confirm_update_role'])) {
    $target_user_id = intval($_POST['user_id']);
    $new_role = trim($_POST['new_role']);
    $admin_password = $_POST['admin_password'] ?? '';

    // 1. Vérifier le mot de passe de l'admin actuel
    $stmt = $pdo->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $current_admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($admin_password, $current_admin['mot_de_passe'])) {
        // 2. Vérifier que le rôle est autorisé
        $allowed_roles = ['admin', 'utilisateur'];
        if (in_array($new_role, $allowed_roles) && $target_user_id > 0) {
            $stmt = $pdo->prepare('UPDATE utilisateurs SET role = :new_role WHERE id = :user_id AND entreprise_id = :ent_id');
            if ($stmt->execute(['new_role' => $new_role, 'user_id' => $target_user_id, 'ent_id' => $_SESSION['entreprise_id']])) {
                $message = 'Rôle mis à jour avec succès.';
            } else {
                $error = 'Erreur lors de la mise à jour.';
            }
        }
    } else {
        $error = 'Mot de passe administrateur incorrect. Action annulée.';
    }
}

$stmt = $pdo->prepare('SELECT id, nom_utilisateur, email, role FROM utilisateurs WHERE entreprise_id = :ent_id');
$stmt->execute(['ent_id' => $_SESSION['entreprise_id']]);
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs | FactuPro</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 1rem;
            width: 400px;
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 700;">Gestion de l'Équipe</h1>
                    <p style="color: var(--gray-600);">Gérez les accès de vos collaborateurs</p>
                </div>
                <button onclick="openCreateModal()" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Nouveau Collaborateur
                </button>
            </header>

            <?php if ($message): ?>
                <div class="card bg-success-light" style="border: none; color: #059669; padding: 1rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-check-circle"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="card bg-danger-light" style="border: none; color: #dc2626; padding: 1rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Email</th>
                                <th>Rôle Actuel</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateurs as $user): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($user['nom_utilisateur']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">ID: #<?= $user['id'] ?></div>
                                    </td>
                                    <td style="color: var(--gray-600);"><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge <?= $user['role'] == 'admin' ? 'bg-primary-light' : 'bg-gray-200' ?>" style="color: <?= $user['role'] == 'admin' ? 'var(--primary)' : 'var(--gray-600)' ?>;">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <button onclick="openRoleModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['nom_utilisateur']) ?>', '<?= $user['role'] ?>')" class="btn" style="padding: 0.5rem 1rem; font-size: 0.875rem; background: var(--gray-100); color: var(--primary);">
                                            <i class="fas fa-user-shield"></i> Modifier le rôle
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create User Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1rem;">Ajouter un collaborateur</h3>
            <p style="margin-bottom: 1.5rem; color: var(--gray-600); font-size: 0.875rem;">L'utilisateur sera automatiquement rattaché à votre boutique.</p>

            <form action="gerer_utilisateurs.php" method="POST">
                <div class="form-group">
                    <label>Nom d'utilisateur</label>
                    <input type="text" name="new_username" required placeholder="ex: vendeur_jean">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="new_email" required placeholder="jean@exemple.com">
                </div>
                <div class="form-group">
                    <label>Mot de passe provisoire</label>
                    <input type="password" name="new_password" required placeholder="••••••••">
                </div>
                <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid var(--gray-200);">
                <div class="form-group">
                    <label>Confirmez avec VOTRE mot de passe (Admin)</label>
                    <input type="password" name="admin_password" required placeholder="Votre mot de passe actuel">
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" name="create_user" class="btn btn-primary" style="flex: 1;">Créer le compte</button>
                    <button type="button" onclick="closeCreateModal()" class="btn" style="background: var(--gray-200); color: var(--gray-700);">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Role Update Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1rem;">Modifier le rôle</h3>
            <p id="modalUserText" style="margin-bottom: 1.5rem; color: var(--gray-600); font-size: 0.9rem;"></p>

            <form action="gerer_utilisateurs.php" method="POST">
                <input type="hidden" name="user_id" id="modalUserId">

                <div class="form-group">
                    <label>Nouveau Rôle</label>
                    <select name="new_role" id="modalNewRole" required>
                        <option value="utilisateur">Utilisateur (Standard)</option>
                        <option value="admin">Administrateur (Complet)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Confirmez avec VOTRE mot de passe (Admin)</label>
                    <input type="password" name="admin_password" required placeholder="Votre mot de passe actuel">
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" name="confirm_update_role" class="btn btn-primary" style="flex: 1;">Confirmer</button>
                    <button type="button" onclick="closeRoleModal()" class="btn" style="background: var(--gray-200); color: var(--gray-700);">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const roleModal = document.getElementById('roleModal');
        const createModal = document.getElementById('createModal');
        const modalUserId = document.getElementById('modalUserId');
        const modalUserText = document.getElementById('modalUserText');
        const modalNewRole = document.getElementById('modalNewRole');

        function openRoleModal(id, name, currentRole) {
            modalUserId.value = id;
            modalUserText.innerHTML = `Utilisateur : <strong>${name}</strong><br>Rôle actuel : ${currentRole}`;
            modalNewRole.value = currentRole;
            roleModal.style.display = 'block';
        }

        function closeRoleModal() {
            roleModal.style.display = 'none';
        }

        function openCreateModal() {
            createModal.style.display = 'block';
        }

        function closeCreateModal() {
            createModal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == roleModal) closeRoleModal();
            if (event.target == createModal) closeCreateModal();
        }
    </script>
</body>

</html>