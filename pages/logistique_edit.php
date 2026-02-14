<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (!isset($_GET['id'])) {
    header('Location: logistique.php');
    exit;
}

$id_logistique = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// Récupérer l'entrée logistique
$stmt = $pdo->prepare("
    SELECT l.*, v.Nom_Client, v.Numero_Vente 
    FROM Logistique l
    LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
    WHERE l.Id_Logistique = ? AND l.Id_Entreprise = ?
");
$stmt->execute([$id_logistique, $entreprise_id]);
$log = $stmt->fetch();

if (!$log) {
    die("Entrée logistique non trouvée ou accès refusé.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transporteur = $_POST['transporteur'] ?? '';
    $numero_suivi = $_POST['numero_suivi'] ?? '';
    $statut = $_POST['statut'] ?? 'traitement';
    $date_exp = $_POST['date_expedition'] ?: null;
    $date_prevue = $_POST['date_prevue'] ?: null;
    $date_livree = $_POST['date_livraison'] ?: null;
    $notes = $_POST['notes'] ?? '';

    try {
        $stmt = $pdo->prepare("
            UPDATE Logistique SET 
                Transporteur = ?, 
                Numero_Suivi = ?, 
                Statut_Livraison = ?, 
                Date_Expedition = ?, 
                Date_Livraison_Prevue = ?, 
                Date_Livraison_Effectuee = ?, 
                Notes_Logistique = ?
            WHERE Id_Logistique = ?
        ");
        $stmt->execute([
            $transporteur,
            $numero_suivi,
            $statut,
            $date_exp,
            $date_prevue,
            $date_livree,
            $notes,
            $id_logistique
        ]);
        $success = "Le suivi logistique a été mis à jour.";
        // Rafraîchir les données
        $stmt = $pdo->prepare("SELECT l.*, v.Nom_Client, v.Numero_Vente FROM Logistique l LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente WHERE l.Id_Logistique = ?");
        $stmt->execute([$id_logistique]);
        $log = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

$page_title = 'Modifier Expédition';
include '../includes/header.php';
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-truck"></i> Mise à jour Expédition</h1>
        <a href="logistique.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>
    <?php if ($error): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>

    <div class="card">
        <h2 style="margin-bottom: 20px;">Vente <?= htmlspecialchars($log['Numero_Vente']) ?> - <?= htmlspecialchars($log['Nom_Client']) ?></h2>

        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Transporteur</label>
                    <input type="text" name="transporteur" class="form-control" value="<?= htmlspecialchars($log['Transporteur'] ?? '') ?>" placeholder="ex: DHL, Fedex, GP...">
                </div>
                <div class="form-group">
                    <label>Numéro de Suivi</label>
                    <input type="text" name="numero_suivi" class="form-control" value="<?= htmlspecialchars($log['Numero_Suivi'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Statut de Livraison</label>
                    <select name="statut" class="form-control" required>
                        <option value="traitement" <?= $log['Statut_Livraison'] == 'traitement' ? 'selected' : '' ?>>En préparation (Traitement)</option>
                        <option value="en_attente" <?= $log['Statut_Livraison'] == 'en_attente' ? 'selected' : '' ?>>En attente d'enlèvement</option>
                        <option value="expediee" <?= $log['Statut_Livraison'] == 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                        <option value="livree" <?= $log['Statut_Livraison'] == 'livree' ? 'selected' : '' ?>>Livrée</option>
                        <option value="annulee" <?= $log['Statut_Livraison'] == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date d'Expédition</label>
                    <input type="datetime-local" name="date_expedition" class="form-control" value="<?= $log['Date_Expedition'] ? date('Y-m-d\TH:i', strtotime($log['Date_Expedition'])) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Date de Livraison Prévue</label>
                    <input type="date" name="date_prevue" class="form-control" value="<?= $log['Date_Livraison_Prevue'] ? date('Y-m-d', strtotime($log['Date_Livraison_Prevue'])) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Date de Livraison Réelle</label>
                    <input type="datetime-local" name="date_livraison" class="form-control" value="<?= $log['Date_Livraison_Effectuee'] ? date('Y-m-d\TH:i', strtotime($log['Date_Livraison_Effectuee'])) : '' ?>">
                </div>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label>Notes & Observations</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($log['Notes_Logistique'] ?? '') ?></textarea>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

</body>

</html>
