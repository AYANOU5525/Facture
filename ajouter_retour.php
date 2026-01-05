<?php
require_once 'auth.php';
require_once 'bdd.php';

if ($user_role !== 'admin') {
    header('Location: tableau_de_bord.php?error=acces_refuse');
    exit();
}

$message = '';
$error_message = '';

if (isset($_POST['add_return'])) {
    $id_produit = intval($_POST['id_produit']);
    $quantite = intval($_POST['quantite']);
    $raison = trim($_POST['raison']);
    $date_retour = date('Y-m-d H:i:s');
    $cree_par = $_SESSION['username'];

    if ($id_produit > 0 && $quantite > 0 && !empty($raison)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO retours (id_produit, quantite, date_retour, raison, cree_par, entreprise_id) VALUES (:id_produit, :quantite, :date_retour, :raison, :cree_par, :entreprise_id)');
            $stmt->execute([
                'id_produit' => $id_produit,
                'quantite' => $quantite,
                'date_retour' => $date_retour,
                'raison' => $raison,
                'cree_par' => $cree_par,
                'entreprise_id' => $_SESSION['entreprise_id']
            ]);

            $stmt = $pdo->prepare('UPDATE produits SET quantite_en_stock = quantite_en_stock + :quantite WHERE id = :id_produit AND entreprise_id = :ent_id');
            $stmt->execute([
                'quantite' => $quantite,
                'id_produit' => $id_produit,
                'ent_id' => $_SESSION['entreprise_id']
            ]);

            $pdo->commit();
            $message = 'Retour enregistré avec succès.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Erreur lors de l'enregistrement.";
        }
    } else {
        $error_message = "Veuillez remplir tous les champs.";
    }
}

$stmt_produits = $pdo->prepare('SELECT id, nom, quantite_en_stock FROM produits WHERE entreprise_id = :ent_id');
$stmt_produits->execute(['ent_id' => $_SESSION['entreprise_id']]);
$liste_produits = $stmt_produits->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retours | FactuPro</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="app-container">
        <?php include 'navbar.php'; ?>

        <main class="main-content">
            <header style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: 700;">Retours Produits</h1>
                <p style="color: var(--gray-600);">Gérez les retours clients et réintégrez les articles au stock</p>
            </header>

            <div class="card" style="max-width: 600px;">
                <div class="card-title" style="color: var(--secondary);"><i class="fas fa-undo"></i> Nouveau Retour</div>

                <?php if ($message): ?>
                    <div class="card bg-success-light" style="border: none; color: #059669; padding: 1rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-check-circle"></i> <?= $message ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="card bg-danger-light" style="border: none; color: #dc2626; padding: 1rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-circle-exclamation"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <form action="ajouter_retour.php" method="POST">
                    <div class="form-group">
                        <label for="id_produit">Produit Concerné</label>
                        <select id="id_produit" name="id_produit" required>
                            <option value="">-- Choisir un produit --</option>
                            <?php foreach ($liste_produits as $produit): ?>
                                <option value="<?= htmlspecialchars($produit['id']) ?>">
                                    <?= htmlspecialchars($produit['nom']) ?> (Stock actuel: <?= htmlspecialchars($produit['quantite_en_stock']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="quantite">Quantité Réintégrée</label>
                        <input type="number" id="quantite" name="quantite" min="1" required placeholder="0">
                    </div>

                    <div class="form-group">
                        <label for="raison">Raison du retour</label>
                        <textarea id="raison" name="raison" rows="3" required placeholder="Ex: Défaut de fabrication, Erreur de commande..."></textarea>
                    </div>

                    <button type="submit" name="add_return" class="btn btn-primary" style="width: 100%; background: var(--secondary);">
                        <i class="fas fa-save"></i> Enregistrer le Retour
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>

</html>