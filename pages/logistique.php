<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(ROLE_PROPRIO, ROLE_LIVREUR);

$page_title = 'Suivi Logistique';
include '../includes/header.php';

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// On récupère les entrées logistiques
$stmt = $pdo->prepare("
    SELECT l.*, 
           v.Nom_Client, 
           v.Numero_Vente,
           c.Numero_Commande,
           e.Nom_Entreprise AS Nom_Acheteur
    FROM Logistique l
    LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
    LEFT JOIN Commande_B2B c ON l.Id_Commande_B2B = c.Id_Commande_B2B
    LEFT JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
    WHERE l.Id_Entreprise = ? 
    ORDER BY l.Id_Logistique DESC
");
$stmt->execute([$entreprise_id]);
$logistique = $stmt->fetchAll();

$recherche = trim($_GET['q'] ?? '');
$statut_filtre = $_GET['statut'] ?? '';
$statuts_valides = ['traitement', 'en_attente', 'expediee', 'livree', 'annulee'];
if (!in_array($statut_filtre, $statuts_valides, true)) {
    $statut_filtre = '';
}

if ($recherche !== '' || $statut_filtre !== '') {
    $logistique = array_values(array_filter($logistique, function (array $ligne) use ($recherche, $statut_filtre): bool {
        $texte = implode(' ', [
            $ligne['Numero_Commande'] ?? '',
            $ligne['Numero_Vente'] ?? '',
            $ligne['Nom_Acheteur'] ?? '',
            $ligne['Nom_Client'] ?? '',
            $ligne['Transporteur'] ?? '',
            $ligne['Numero_Suivi'] ?? '',
        ]);

        $correspond_recherche = $recherche === ''
            || stripos($texte, $recherche) !== false;
        $correspond_statut = $statut_filtre === ''
            || ($ligne['Statut_Livraison'] ?? '') === $statut_filtre;

        return $correspond_recherche && $correspond_statut;
    }));
}
?>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-truck-loading"></i> Suivi Logistique</h1>
    </div>

    <div class="card">
        <div class="logistique-toolbar">
            <h2>Expéditions & Livraisons</h2>
            <form method="GET" class="logistique-filters">
                <input type="search" name="q" class="form-control" value="<?= htmlspecialchars($recherche) ?>" placeholder="Commande, client, suivi...">
                <select name="statut" class="form-control" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="traitement" <?= $statut_filtre === 'traitement' ? 'selected' : '' ?>>Traitement</option>
                    <option value="en_attente" <?= $statut_filtre === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                    <option value="expediee" <?= $statut_filtre === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                    <option value="livree" <?= $statut_filtre === 'livree' ? 'selected' : '' ?>>Livrée</option>
                    <option value="annulee" <?= $statut_filtre === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrer</button>
                <?php if ($recherche !== '' || $statut_filtre !== ''): ?>
                    <a href="logistique.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($logistique) > 0): ?>
            <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Réf. Vente</th>
                        <th>Client</th>
                        <th>Transporteur</th>
                        <th>N° Suivi</th>
                        <th>Statut</th>
                        <th>Livraison Prévue</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($logistique as $l): ?>
                        <tr>
                            <td>
                                <?php if ($l['Id_Commande_B2B']): ?>
                                    <span class="badge badge-info" style="font-size:0.7rem; padding: 2px 6px;"><i class="fas fa-handshake"></i> B2B</span><br>
                                    <code><?= htmlspecialchars($l['Numero_Commande'] ?? '-') ?></code>
                                <?php else: ?>
                                    <code><?= htmlspecialchars($l['Numero_Vente'] ?? '-') ?></code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($l['Id_Commande_B2B']): ?>
                                    <strong><?= htmlspecialchars($l['Nom_Acheteur'] ?? '-') ?></strong>
                                <?php else: ?>
                                    <?= htmlspecialchars($l['Nom_Client'] ?? '-') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($l['Transporteur'] ?? '-') ?></td>
                            <td><code><?= htmlspecialchars($l['Numero_Suivi'] ?? '-') ?></code></td>
                            <td>
                                <span class="badge badge-<?= $l['Statut_Livraison'] === 'livree' ? 'success' : ($l['Statut_Livraison'] === 'expediee' ? 'info' : 'secondary') ?>">
                                    <?= strtoupper(str_replace('_', ' ', $l['Statut_Livraison'])) ?>
                                </span>
                            </td>
                            <td><?= $l['Date_Livraison_Prevue'] ? date('d/m/Y', strtotime($l['Date_Livraison_Prevue'])) : '-' ?></td>
                            <td>
                                <a href="logistique_edit.php?id=<?= $l['Id_Logistique'] ?>" class="btn btn-sm btn-primary" title="Suivi Logistique & Carte">
                                    <i class="fas fa-map-marked-alt"></i> Carte
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Aucune expédition en cours.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>

</html>
