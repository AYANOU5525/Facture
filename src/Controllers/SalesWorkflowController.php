<?php

namespace App\Controllers;

use App\Application\Billing\SalesWorkflowService;
use App\Infrastructure\Persistence\SalesWorkflowRepository;

/** Contrôleur de pages/vente_workflow.php — finalisation d'une vente directe (paiement + logistique). */
class SalesWorkflowController extends Controller
{
    private SalesWorkflowService $workflow;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->workflow = new SalesWorkflowService(new SalesWorkflowRepository($pdo));
    }

    public function index(): void
    {
        exigerPermission(peutCreerVente());

        $numero_vente = $_GET['ref'] ?? null;
        if (!$numero_vente) {
            $this->redirect('dashboard.php');
        }

        $entreprise_id = $_SESSION['entreprise_id'];

        $vente = $this->workflow->findSale($numero_vente, (int) $entreprise_id);
        if (!$vente) {
            $this->redirect('dashboard.php');
        }

        // Fiche de livraison créée à la vente (N° de suivi déjà attribué). Le transporteur et la
        // date prévue se renseignent sur logistique_edit.php, comme pour une commande B2B.
        $logistique = $this->workflow->findLogistics((int) $vente['Id_Vente']);

        $success = '';
        $error = '';
        $etape = $_GET['etape'] ?? '1';

        $mode = in_array($_GET['mode'] ?? '', ['livraison', 'retrait']) ? $_GET['mode'] : 'livraison';
        // Logistique en attente (cf. includes/roles.php) : on force le parcours "retrait sur
        // place" tant que la fonctionnalité est désactivée, pour ne pas proposer une étape qui
        // mène à des pages bloquées.
        $avec_livraison = FEATURE_LOGISTIQUE_ACTIVE && ($mode === 'livraison');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_paiement'])) {
            exigerCsrf();
            $this->workflow->markPaid((int) $vente['Id_Facture']);
            $vente['Statut_Paiement'] = 'payee';
            $success = "Paiement validé avec succès !";
            // Retrait sur place : pas de logistique, on passe directement à "Terminé"
            $etape = $avec_livraison ? '2' : '3';
        }

        $this->render('vente_workflow/index', [
            'numero_vente'        => $numero_vente,
            'vente'               => $vente,
            'logistique'          => $logistique,
            'success'             => $success,
            'error'               => $error,
            'etape'               => $etape,
            'avec_livraison'      => $avec_livraison,
        ], 'Workflow Vente');
    }
}
