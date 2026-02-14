<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Récupérer le numéro de vente depuis l'URL
$numero_vente = $_GET['ref'] ?? null;
if (!$numero_vente) {
    header('Location: dashboard.php');
    exit();
}

$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise_id = $stmt->fetchColumn();

// Récupérer les informations de la vente
$stmt = $pdo->prepare("
    SELECT v.*, f.Id_Facture, f.Statut_Paiement, f.Montant_TTC
    FROM Vente v
    LEFT JOIN Facture f ON v.Id_Vente = f.Id_Vente
    WHERE v.Numero_Vente = ? AND v.Id_Entreprise = ?
");
$stmt->execute([$numero_vente, $entreprise_id]);
$vente = $stmt->fetch();

if (!$vente) {
    header('Location: dashboard.php');
    exit();
}

// Vérifier si logistique existe déjà
$stmt = $pdo->prepare("SELECT Id_Logistique FROM Logistique WHERE Id_Vente = ?");
$stmt->execute([$vente['Id_Vente']]);
$logistique_existe = $stmt->fetch();

$success = '';
$error = '';
$etape = $_GET['etape'] ?? '1';

// === TRAITEMENT VALIDATION PAIEMENT ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_paiement'])) {
    $stmt = $pdo->prepare("UPDATE Facture SET Statut_Paiement = 'payee' WHERE Id_Facture = ?");
    $stmt->execute([$vente['Id_Facture']]);
    $success = "Paiement validé avec succès !";
    $etape = '2'; // Passer à l'étape logistique

    // Recharger les données
    $stmt = $pdo->prepare("
        SELECT v.*, f.Id_Facture, f.Statut_Paiement, f.Montant_TTC
        FROM Vente v
        LEFT JOIN Facture f ON v.Id_Vente = f.Id_Vente
        WHERE v.Numero_Vente = ? AND v.Id_Entreprise = ?
    ");
    $stmt->execute([$numero_vente, $entreprise_id]);
    $vente = $stmt->fetch();
}

// === TRAITEMENT CRÉATION LOGISTIQUE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_logistique'])) {
    $transporteur = $_POST['transporteur'] ?? '';
    $numero_suivi = $_POST['numero_suivi'] ?? '';
    $date_livraison = $_POST['date_livraison'] ?? null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO Logistique 
            (Id_Vente, Id_Entreprise, Transporteur, Numero_Suivi, Date_Livraison_Prevue, Statut_Livraison)
            VALUES (?, ?, ?, ?, ?, 'en_preparation')
        ");
        $stmt->execute([
            $vente['Id_Vente'],
            $entreprise_id,
            $transporteur,
            $numero_suivi,
            $date_livraison
        ]);
        $success = "Logistique créée avec succès !";
        $etape = '3'; // Terminé
        $logistique_existe = true;
    } catch (PDOException $e) {
        $error = "Erreur lors de la création : " . $e->getMessage();
    }
}

$page_title = 'Workflow Vente';
include '../includes/header.php';
?>

<style>
    .workflow-progress {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        position: relative;
    }

    .workflow-progress::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 2px;
        background: #e2e8f0;
        z-index: 0;
    }

    .workflow-step {
        flex: 1;
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .workflow-step-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-weight: bold;
        transition: all 0.3s;
    }

    .workflow-step.active .workflow-step-circle {
        background: var(--primary);
        color: white;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
    }

    .workflow-step.completed .workflow-step-circle {
        background: var(--success);
        color: white;
    }

    .workflow-step-label {
        font-size: 0.9em;
        color: #64748b;
    }

    .workflow-step.active .workflow-step-label {
        color: var(--primary);
        font-weight: 600;
    }

    .workflow-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-check"></i> Finalisation de la Vente</h1>
        <p>Vente <strong><?= htmlspecialchars($numero_vente) ?></strong> - <?= htmlspecialchars($vente['Nom_Client']) ?></p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- PROGRESSION -->
    <div class="workflow-progress">
        <div class="workflow-step <?= $etape >= '1' ? 'completed' : '' ?>">
            <div class="workflow-step-circle">✓</div>
            <div class="workflow-step-label">Vente créée</div>
        </div>
        <div class="workflow-step <?= $etape == '1' ? 'active' : ($etape > '1' ? 'completed' : '') ?>">
            <div class="workflow-step-circle"><?= $etape > '1' ? '✓' : '2' ?></div>
            <div class="workflow-step-label">Validation paiement</div>
        </div>
        <div class="workflow-step <?= $etape == '2' ? 'active' : ($etape > '2' ? 'completed' : '') ?>">
            <div class="workflow-step-circle"><?= $etape > '2' ? '✓' : '3' ?></div>
            <div class="workflow-step-label">Logistique</div>
        </div>
        <div class="workflow-step <?= $etape == '3' ? 'active' : '' ?>">
            <div class="workflow-step-circle"><?= $etape == '3' ? '✓' : '4' ?></div>
            <div class="workflow-step-label">Terminé</div>
        </div>
    </div>

    <!-- CONTENU DE L'ÉTAPE -->
    <?php if ($etape == '1'): ?>
        <!-- ÉTAPE 1: VALIDATION PAIEMENT -->
        <div class="workflow-content">
            <h2><i class="fas fa-money-bill-wave"></i> Valider le Paiement</h2>
            <p>La facture a été générée. Confirmez la réception du paiement.</p>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span><strong>Montant Total:</strong></span>
                    <span style="font-size: 1.3em; color: var(--success); font-weight: bold;">
                        <?= number_format($vente['Montant_TTC'], 0, ',', ' ') ?> F
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span><strong>Statut actuel:</strong></span>
                    <span class="badge badge-<?= $vente['Statut_Paiement'] === 'payee' ? 'success' : 'warning' ?>">
                        <?= strtoupper(str_replace('_', ' ', $vente['Statut_Paiement'])) ?>
                    </span>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <?php if ($vente['Statut_Paiement'] !== 'payee'): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="valider_paiement" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Marquer comme Payé
                        </button>
                    </form>
                <?php else: ?>
                    <a href="?ref=<?= urlencode($numero_vente) ?>&etape=2" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Passer à la logistique
                    </a>
                <?php endif; ?>
                <a href="invoice_view.php?ref=<?= urlencode($numero_vente) ?>" target="_blank" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Voir la Facture
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Terminer plus tard
                </a>
            </div>
        </div>

    <?php elseif ($etape == '2'): ?>
        <!-- ÉTAPE 2: LOGISTIQUE -->
        <div class="workflow-content">
            <h2><i class="fas fa-truck"></i> Créer l'Entrée Logistique</h2>
            <p>Enregistrez les informations de transport et de livraison.</p>

            <?php if ($logistique_existe): ?>
                <div class="alert alert-info">
                    Une entrée logistique existe déjà pour cette vente.
                </div>
                <a href="?ref=<?= urlencode($numero_vente) ?>&etape=3" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Continuer
                </a>
            <?php else: ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Transporteur</label>
                                <input type="text" name="transporteur" class="form-control" placeholder="Ex: DHL, Fedex, UPS...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Numéro de Suivi</label>
                                <input type="text" name="numero_suivi" class="form-control" placeholder="Code de suivi...">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date de Livraison Prévue</label>
                                <input type="date" name="date_livraison" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="submit" name="creer_logistique" class="btn btn-primary">
                            <i class="fas fa-save"></i> Créer la Logistique
                        </button>
                        <a href="?ref=<?= urlencode($numero_vente) ?>&etape=3" class="btn btn-secondary">
                            <i class="fas fa-forward"></i> Passer cette étape
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- ÉTAPE 3: TERMINÉ -->
        <div class="workflow-content" style="text-align: center;">
            <div style="font-size: 4em; color: var(--success); margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Vente Finalisée !</h2>
            <p>Toutes les étapes ont été complétées avec succès.</p>

            <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: center;">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour au Dashboard
                </a>
                <a href="sales.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Voir toutes les ventes
                </a>
                <a href="invoice_add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Nouvelle Vente
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>

</html>
