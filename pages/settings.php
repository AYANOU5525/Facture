<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Vérifier si l'utilisateur est admin
if ($_SESSION['role'] !== 'admin') {
    die("Accès refusé. Seuls les administrateurs peuvent modifier les paramètres.");
}

$page_title = "Paramètres de l'entreprise";
include '../includes/header.php';

// ID Entreprise
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

$success = '';
$error = '';

// === TRAITEMENT DU FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'];
    $adresse = $_POST['adresse'];
    $tel = $_POST['tel'];
    $email = $_POST['email'];
    $nif = $_POST['nif'];
    $intro = $_POST['description'];

    try {
        $stmt = $pdo->prepare("
            UPDATE Entreprise 
            SET Nom_Entreprise = ?, Adresse_Entreprise = ?, Tel_Entreprise = ?, 
                Email_Entreprise = ?, NIF_Entreprise = ?, Description_Entreprise = ?
            WHERE Id_Entreprise = ?
        ");
        $stmt->execute([$nom, $adresse, $tel, $email, $nif, $intro, $entreprise_id]);
        $success = "Informations mises à jour avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les infos actuelles
$stmt = $pdo->prepare("SELECT * FROM Entreprise WHERE Id_Entreprise = ?");
$stmt->execute([$entreprise_id]);
$ent = $stmt->fetch();
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> Paramètres de l'Entreprise</h1>
        <p>Ces informations apparaîtront sur vos factures</p>
    </div>

    <?php if ($success): ?> <div class="alert alert-success"><i class="fas fa-check"></i> <?= $success ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div> <?php endif; ?>

    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <form method="POST">
            <div class="form-group">
                <label>Nom de l'entreprise *</label>
                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($ent['Nom_Entreprise'] ?? '') ?>" required>
            </div>

            <div class="row" style="display:flex; gap:20px;">
                <div class="form-group" style="flex:1;">
                    <label>Email de contact *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($ent['Email_Entreprise'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Téléphone *</label>
                    <input type="text" name="tel" class="form-control" value="<?= htmlspecialchars($ent['Tel_Entreprise'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Adresse complète (Rue, Ville, BP...) *</label>
                <textarea name="adresse" class="form-control" rows="2" required><?= htmlspecialchars($ent['Adresse_Entreprise'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Numéro NIF / RC (Identifiant fiscal)</label>
                <input type="text" name="nif" class="form-control" value="<?= htmlspecialchars($ent['NIF_Entreprise'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Description courte (Slogan)</label>
                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($ent['Description_Entreprise'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Enregistrer les modifications
            </button>
        </form>
    </div>
</div>

</body>

</html>
