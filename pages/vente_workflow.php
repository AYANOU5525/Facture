<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

requireRole(ROLE_ADMIN, ROLE_PROPRIO, ROLE_VENDEUR);

use App\Application\Billing\SalesWorkflowService;
use App\Infrastructure\Persistence\SalesWorkflowRepository;

$salesWorkflow = new SalesWorkflowService(new SalesWorkflowRepository($pdo));

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
$vente = $salesWorkflow->findSale($numero_vente, (int) $entreprise_id);

if (!$vente) {
    header('Location: dashboard.php');
    exit();
}

// Vérifier si logistique existe déjà
$logistique_existe = $salesWorkflow->hasLogistics((int) $vente['Id_Vente']);

$success = '';
$error = '';
$etape = $_GET['etape'] ?? '1';

// === TRAITEMENT VALIDATION PAIEMENT ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_paiement'])) {
    requireCsrf();
    $salesWorkflow->markPaid((int) $vente['Id_Facture']);
    $success = "Paiement validé avec succès !";
    $etape = '2'; // Passer à l'étape logistique

    // Recharger les données
    $vente['Statut_Paiement'] = 'payee';
}

// === TRAITEMENT CRÉATION LOGISTIQUE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_logistique'])) {
    requireCsrf();
    $transporteur = trim($_POST['transporteur'] ?? '');
    $numero_suivi = trim($_POST['numero_suivi'] ?? '');
    $date_livraison = $_POST['date_livraison'] ?? null;

    try {
        $salesWorkflow->createLogistics(
            $vente['Id_Vente'],
            $entreprise_id,
            $transporteur,
            $numero_suivi,
            $date_livraison
        );
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
        left: 5%;
        right: 5%;
        height: 2px;
        background: var(--zinc-200);
        z-index: 0;
    }

    .workflow-step {
        flex: 1;
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .workflow-step-circle {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--zinc-100);
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-weight: 700;
        font-size: 0.95rem;
        border: 2px solid var(--zinc-200);
        transition: all 0.3s;
    }

    .workflow-step.active .workflow-step-circle {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
    }

    .workflow-step.completed .workflow-step-circle {
        background: var(--success);
        color: white;
        border-color: var(--success);
    }

    .workflow-step-label {
        font-size: 0.82rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    .workflow-step.active .workflow-step-label {
        color: var(--primary);
        font-weight: 600;
    }

    .workflow-step.completed .workflow-step-label {
        color: var(--success);
    }

    .workflow-content {
        background: var(--bg-card);
        color: var(--text-main);
        padding: 30px;
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--zinc-200);
    }

    .workflow-info-box {
        background: var(--zinc-50);
        border: 1px solid var(--zinc-200);
        padding: 20px;
        border-radius: 10px;
        margin: 20px 0;
    }

    .workflow-info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid var(--zinc-100);
    }

    .workflow-info-row:last-child {
        border-bottom: none;
    }

    .workflow-info-label {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .workflow-info-value {
        font-weight: 600;
    }

    .workflow-amount {
        font-size: 1.4rem;
        color: var(--success);
    }

    .workflow-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        flex-wrap: wrap;
        margin-top: 24px;
    }

    .workflow-done-icon {
        font-size: 4rem;
        color: var(--success);
        margin-bottom: 20px;
    }

    @media (max-width: 600px) {
        .workflow-step-label { display: none; }
        .workflow-content { padding: 20px 16px; }
        .workflow-actions { flex-direction: column; }
        .workflow-actions .btn { width: 100%; justify-content: center; }
    }
</style>

<div class="container fade-in">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-check"></i> Finalisation de la Vente</h1>
        <p>
            Vente <strong><?= htmlspecialchars($numero_vente) ?></strong> 
            - <?= htmlspecialchars($vente['Nom_Client']) ?>
        </p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- PROGRESSION -->
    <div class="workflow-progress">
        <div class="workflow-step completed">
            <div class="workflow-step-circle"><i class="fas fa-check"></i></div>
            <div class="workflow-step-label">Vente créée</div>
        </div>
        <div class="workflow-step <?= $etape == '1' ? 'active' : ($etape > '1' ? 'completed' : '') ?>">
            <div class="workflow-step-circle"><?= $etape > '1' ? '<i class="fas fa-check"></i>' : '2' ?></div>
            <div class="workflow-step-label">Paiement</div>
        </div>
        <div class="workflow-step <?= $etape == '2' ? 'active' : ($etape > '2' ? 'completed' : '') ?>">
            <div class="workflow-step-circle"><?= $etape > '2' ? '<i class="fas fa-check"></i>' : '3' ?></div>
            <div class="workflow-step-label">Logistique</div>
        </div>
        <div class="workflow-step <?= $etape == '3' ? 'active' : '' ?>">
            <div class="workflow-step-circle"><?= $etape == '3' ? '<i class="fas fa-check"></i>' : '4' ?></div>
            <div class="workflow-step-label">Terminé</div>
        </div>
    </div>

    <!-- CONTENU DE L'ÉTAPE -->
    <?php if ($etape == '1'): ?>
        <!-- ÉTAPE 1 : VALIDATION PAIEMENT -->
        <div class="workflow-content">
            <h2><i class="fas fa-money-bill-wave" style="color:var(--success);"></i> Valider le Paiement</h2>
            <p style="color:var(--text-muted);">La facture a été générée. Confirmez la réception du paiement avant de passer à la logistique.</p>

            <div class="workflow-info-box">
                <div class="workflow-info-row">
                    <span class="workflow-info-label">Client</span>
                    <span class="workflow-info-value"><?= htmlspecialchars($vente['Nom_Client']) ?></span>
                </div>
                <div class="workflow-info-row">
                    <span class="workflow-info-label">Référence</span>
                    <span class="workflow-info-value"><?= htmlspecialchars($numero_vente) ?></span>
                </div>
                <div class="workflow-info-row">
                    <span class="workflow-info-label">Montant total</span>
                    <span class="workflow-info-value workflow-amount"><?= number_format($vente['Montant_TTC'], 0, ',', ' ') ?> F</span>
                </div>
                <div class="workflow-info-row">
                    <span class="workflow-info-label">Statut paiement</span>
                    <span class="badge badge-<?= $vente['Statut_Paiement'] === 'payee' ? 'success' : 'warning' ?>">
                        <?= $vente['Statut_Paiement'] === 'payee' ? 'Payée' : 'En attente' ?>
                    </span>
                </div>
            </div>

            <div class="workflow-actions">
                <?php if ($vente['Statut_Paiement'] !== 'payee'): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" name="valider_paiement" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Confirmer le paiement
                        </button>
                    </form>
                <?php else: ?>
                    <a href="?ref=<?= urlencode($numero_vente) ?>&etape=2" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Passer à la logistique
                    </a>
                <?php endif; ?>
                <a href="invoice_view.php?ref=<?= urlencode($numero_vente) ?>" target="_blank" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Voir la facture
                </a>
                <a href="sales.php" class="btn btn-secondary">
                    <i class="fas fa-clock"></i> Terminer plus tard
                </a>
            </div>
        </div>

    <?php elseif ($etape == '2'): ?>
        <!-- ÉTAPE 2 : LOGISTIQUE -->
        <div class="workflow-content">
            <h2><i class="fas fa-truck" style="color:var(--primary);"></i> Expédition & Logistique</h2>
            <p style="color:var(--text-muted);">Renseignez les informations de transport pour cette livraison.</p>

            <?php if ($logistique_existe): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Une entrée logistique existe déjà pour cette vente.
                </div>
                <div class="workflow-actions">
                    <a href="?ref=<?= urlencode($numero_vente) ?>&etape=3" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Terminer
                    </a>
                    <a href="logistique.php" class="btn btn-secondary">
                        <i class="fas fa-truck"></i> Voir la logistique
                    </a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Transporteur</label>
                                <input type="text" name="transporteur" class="form-control" placeholder="Ex : DHL, Fedex, UPS...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Numéro de suivi</label>
                                <input type="text" name="numero_suivi" class="form-control" placeholder="Code de suivi...">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" style="padding:0;">
                        <div class="form-group">
                            <label>Date de livraison prévue</label>
                            <input type="date" name="date_livraison" class="form-control" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="workflow-actions">
                        <button type="submit" name="creer_logistique" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer la logistique
                        </button>
                        <a href="?ref=<?= urlencode($numero_vente) ?>&etape=3" class="btn btn-secondary">
                            <i class="fas fa-forward"></i> Passer cette étape
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- ÉTAPE 3 : TERMINÉ -->
        <div class="workflow-content" style="text-align:center; padding:48px 30px;">
            <div class="workflow-done-icon"><i class="fas fa-check-circle"></i></div>
            <h2 style="margin-bottom:8px;">Vente finalisée !</h2>
            <p style="color:var(--text-muted); max-width:420px; margin:0 auto 30px;">
                La vente <strong><?= htmlspecialchars($numero_vente) ?></strong> a été enregistrée et traitée avec succès.
            </p>
            <div class="workflow-actions" style="justify-content:center;">
                <a href="invoice_add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Nouvelle vente
                </a>
                <a href="sales.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Toutes les ventes
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>

</html>
