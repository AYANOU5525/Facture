<?php
// Page: Gestion des Annonces B2B
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/b2b_helpers.php';

requireRole(ROLE_PROPRIO);

$page_title = "Annonces B2B";
include '../includes/header.php';

// Récupérer l'ID de l'entreprise connectée et ses coordonnées
$stmt = $pdo->prepare("SELECT Id_Entreprise, Latitude, Longitude FROM Entreprise WHERE Id_Entreprise = (SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?)");
$stmt->execute([$_SESSION['user_id']]);
$mon_ent = $stmt->fetch();
$mon_entreprise_id = $mon_ent['Id_Entreprise'];
$j_ai_coords = !empty($mon_ent['Latitude']) && !empty($mon_ent['Longitude']);

// Traitement de l'ajout d'une annonce
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    requireCsrf();
    try {
        $type = $_POST['type_annonce'];
        $titre = $_POST['titre'];
        $description = $_POST['description'];

        // Insertion simplifiée selon facturation.sql (pas de produit, pas de date expiration)
        $stmt = $pdo->prepare("
            INSERT INTO Annonce (Id_Entreprise, Type_Annonce, Titre, Description, Date_Publication, Statut)
            VALUES (?, ?, ?, ?, NOW(), 'active')
        ");
        $stmt->execute([
            $mon_entreprise_id,
            $type,
            $titre,
            $description
        ]);

        $success = "Annonce publiée avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur lors de la publication : " . $e->getMessage();
    }
}

// Filtrage
$entreprise_filtre = $_GET['entreprise'] ?? '';
$type_filtre = $_GET['type'] ?? '';

// Récupérer les annonces
$sql = "SELECT a.*, e.Nom_Entreprise, e.Tel_Entreprise, e.Email_Entreprise, e.Latitude, e.Longitude
        FROM Annonce a
        JOIN Entreprise e ON a.Id_Entreprise = e.Id_Entreprise
        WHERE a.Statut = 'active'";

$params = [];
if ($entreprise_filtre) {
    $sql .= " AND a.Id_Entreprise = ?";
    $params[] = $entreprise_filtre;
}
if ($type_filtre) {
    $sql .= " AND a.Type_Annonce = ?";
    $params[] = $type_filtre;
}

$sql .= " ORDER BY a.Date_Publication DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$annonces = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-bullhorn"></i> Annonces & Opportunités</h1>
        <p>Publiez vos appels d'offres ou propositions de partenariat</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Menu de navigation B2B -->
    <div class="b2b-menu">
        <a href="reseau_b2b.php" class="btn btn-primary">
            <i class="fas fa-building"></i> Annuaire
        </a>
        <a href="annonces.php" class="btn btn-primary active">
            <i class="fas fa-bullhorn"></i> Annonces
        </a>
        <a href="commandes_b2b.php" class="btn btn-primary">
            <i class="fas fa-shopping-cart"></i> Commandes B2B
        </a>
    </div>

    <!-- Note d'information -->
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <strong>Note :</strong> Pour mettre des produits en vente B2B, utilisez l'option "Déstockage B2B" directement dans la gestion des produits.
    </div>

    <div class="content-wrapper">
        <!-- Formulaire de publication -->
        <div class="form-section">
            <h2><i class="fas fa-plus-circle"></i> Publier une annonce</h2>
            <form method="POST" class="annonce-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="ajouter">

                <div class="form-group">
                    <label for="type_annonce">Type d'annonce *</label>
                    <select name="type_annonce" id="type_annonce" class="form-control" required>
                        <option value="">-- Sélectionnez --</option>
                        <option value="appel_offre">Appel d'offre (Recherche fournisseur)</option>
                        <option value="partenariat">Recherche de partenariat</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="titre">Titre de l'annonce *</label>
                    <input type="text" name="titre" id="titre" class="form-control"
                        placeholder="Ex: Recherche fournisseur papier A4" required>
                </div>

                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea name="description" id="description" class="form-control" rows="6"
                        placeholder="Décrivez votre besoin en détail..." required></textarea>
                </div>

                <button type="submit" class="btn btn-success" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> Publier l'annonce
                </button>
            </form>
        </div>

        <!-- Liste des annonces -->
        <div class="annonces-section">
            <h2><i class="fas fa-list"></i> Annonces actives</h2>

            <!-- Filtres -->
            <div class="filters">
                <form method="GET" class="filter-form">
                    <select name="type" class="form-control" onchange="this.form.submit()">
                        <option value="">Tous les types</option>
                        <option value="appel_offre" <?= $type_filtre === 'appel_offre' ? 'selected' : '' ?>>Appel d'offre</option>
                        <option value="partenariat" <?= $type_filtre === 'partenariat' ? 'selected' : '' ?>>Partenariat</option>
                    </select>
                </form>
            </div>

            <div class="annonces-list">
                <?php if (empty($annonces)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucune annonce active pour le moment.
                    </div>
                <?php else: ?>
                    <?php foreach ($annonces as $annonce): ?>
                        <div class="annonce-card <?= $annonce['Type_Annonce'] ?>">
                            <div class="annonce-header">
                                <span class="annonce-type" style="text-transform: uppercase;">
                                    <?php if ($annonce['Type_Annonce'] == 'appel_offre'): ?>
                                        <i class="fas fa-search"></i> Appel d'offre
                                    <?php else: ?>
                                        <i class="fas fa-handshake"></i> Partenariat
                                    <?php endif; ?>
                                </span>
                                <span class="annonce-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($annonce['Date_Publication'])) ?>
                                </span>
                            </div>

                            <h3><?= htmlspecialchars($annonce['Titre']) ?></h3>
                            <p class="annonce-description"><?= nl2br(htmlspecialchars($annonce['Description'])) ?></p>

                            <div class="annonce-footer">
                                <div class="entreprise-info">
                                    <i class="fas fa-building"></i>
                                    <strong><?= htmlspecialchars($annonce['Nom_Entreprise']) ?></strong>
                                    <?php if ($j_ai_coords && !empty($annonce['Latitude']) && !empty($annonce['Longitude'])): ?>
                                        <?php $dist = calculDistanceHaversine((float)$mon_ent['Latitude'], (float)$mon_ent['Longitude'], (float)$annonce['Latitude'], (float)$annonce['Longitude']); ?>
                                        <span class="badge badge-secondary" style="margin-left:8px;"><i class="fas fa-route"></i> <?= formaterDistance($dist) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($annonce['Id_Entreprise'] !== $mon_entreprise_id): ?>
                                    <div class="annonce-actions">
                                        <?php if ($annonce['Email_Entreprise']): ?>
                                            <a href="mailto:<?= htmlspecialchars($annonce['Email_Entreprise']) ?>?subject=Réponse annonce: <?= urlencode($annonce['Titre']) ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-envelope"></i> Email
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($annonce['Tel_Entreprise']): ?>
                                            <a href="tel:<?= htmlspecialchars($annonce['Tel_Entreprise']) ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-phone"></i> Tél
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-info">Votre annonce</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .b2b-menu {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .b2b-menu .btn {
        flex: 1;
    }

    .content-wrapper {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 30px;
    }

    .form-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        height: fit-content;
    }

    .annonces-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .annonce-card {
        border: 1px solid #ddd;
        border-left: 4px solid #333;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 4px;
    }

    .annonce-card.appel_offre {
        border-left-color: #e74c3c;
    }

    .annonce-card.partenariat {
        border-left-color: #9b59b6;
    }

    .annonce-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 0.9em;
        color: #666;
    }

    .annonce-type {
        font-weight: bold;
    }

    .annonce-footer {
        margin-top: 15px;
        padding-top: 10px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .annonce-actions {
        display: flex;
        gap: 5px;
    }

    .alert-info {
        background: #e3f2fd;
        color: #0c5460;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
        border: 1px solid #b8daff;
    }
</style>
</body>

</html>
