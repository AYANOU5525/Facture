<?php
require_once 'auth.php';
require_once 'db.php';

$page_title = "Réseau B2B";
include 'header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$mon_entreprise_id = $stmt->fetchColumn();

$secteur_filtre = $_GET['secteur'] ?? '';

$sql = "SELECT * FROM Entreprise WHERE Id_Entreprise != ?";
$params = [$mon_entreprise_id];
if ($secteur_filtre) {
    $sql .= " AND Secteur_Activite = ?";
    $params[] = $secteur_filtre;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entreprises = $stmt->fetchAll();

$secteurs = $pdo->query("SELECT DISTINCT Secteur_Activite FROM Entreprise WHERE Secteur_Activite IS NOT NULL ORDER BY Secteur_Activite")->fetchAll();
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-network-wired"></i> Réseau B2B</h1>
        <p>Découvrez vos partenaires commerciaux</p>
    </div>

    <!-- Filtres -->
    <div class="card" style="padding: 15px; margin-bottom: 25px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: center;">
            <i class="fas fa-filter text-muted"></i>
            <select name="secteur" class="form-control" onchange="this.form.submit()" style="max-width: 300px;">
                <option value="">Tous les secteurs</option>
                <?php foreach ($secteurs as $sec): ?>
                    <option value="<?= htmlspecialchars($sec['Secteur_Activite']) ?>" <?= $secteur_filtre === $sec['Secteur_Activite'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sec['Secteur_Activite']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Liste des entreprises -->
    <div class="entreprises-grid">
        <?php if (empty($entreprises)): ?>
            <div class="alert alert-info">
                Aucune entreprise trouvée.
            </div>
        <?php else: ?>
            <?php foreach ($entreprises as $entreprise): ?>
                <div class="card entreprise-card">
                    <div class="entreprise-header">
                        <div class="avatar-circle" style="width: 60px; height: 60px; font-size: 1.5rem; margin: 0 auto 15px;">
                            <?= strtoupper(substr($entreprise['Nom_Entreprise'], 0, 1)) ?>
                        </div>
                        <h3><?= htmlspecialchars($entreprise['Nom_Entreprise']) ?></h3>
                        <p class="text-muted"><?= htmlspecialchars($entreprise['Secteur_Activite']) ?></p>
                    </div>

                    <div class="entreprise-body">
                        <?php if ($entreprise['Description_Entreprise']): ?>
                            <p class="desc"><i class="fas fa-quote-left"></i> <?= htmlspecialchars($entreprise['Description_Entreprise']) ?></p>
                        <?php endif; ?>

                        <p class="location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($entreprise['Adresse_Entreprise']) ?></p>
                    </div>

                    <div class="entreprise-footer">
                        <a href="messages.php?destinataire=<?= $entreprise['Id_Entreprise'] ?>" class="btn btn-primary btn-sm" style="flex: 2;">
                            <i class="fas fa-comment"></i> Discuter
                        </a>

                        <?php if ($entreprise['Tel_Entreprise']): ?>
                            <a href="tel:<?= htmlspecialchars($entreprise['Tel_Entreprise']) ?>" class="btn btn-secondary btn-sm" title="Appeler">
                                <i class="fas fa-phone"></i>
                            </a>
                        <?php endif; ?>

                        <a href="commandes_b2b.php?onglet=passees&vendeur=<?= $entreprise['Id_Entreprise'] ?>" class="btn btn-success btn-sm" title="Commander">
                            <i class="fas fa-shopping-cart"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    .entreprises-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
    }

    .entreprise-card {
        padding: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .entreprise-header {
        padding: 25px;
        text-align: center;
        background: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
    }

    .entreprise-header h3 {
        margin: 0;
        font-size: 1.2rem;
    }

    .entreprise-body {
        padding: 20px;
        flex: 1;
    }

    .desc {
        font-style: italic;
        color: var(--text-muted);
        font-size: 0.9em;
        margin-bottom: 15px;
    }

    .location {
        font-size: 0.85em;
        color: var(--text-muted);
    }

    .entreprise-footer {
        padding: 15px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        gap: 8px;
        background: #fff;
    }

    .avatar-circle {
        background: linear-gradient(135deg, var(--primary), var(--accent));
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
</style>

</body>

</html>