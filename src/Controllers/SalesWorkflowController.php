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

        // « Existe » = transport déjà renseigné ; une entrée créée à la vente sans transporteur
        // laisse le formulaire de l'étape 2 accessible pour la compléter.
        $logistique = $this->workflow->hasLogistics((int) $vente['Id_Vente']);
        $logistique_existe = $logistique !== null && trim((string) $logistique['Transporteur']) !== '';

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

        if (FEATURE_LOGISTIQUE_ACTIVE && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_logistique'])) {
            exigerCsrf();
            $transporteur = trim($_POST['transporteur'] ?? '');
            $numero_suivi = trim($_POST['numero_suivi'] ?? '');
            // Un champ date laissé vide arrive en '' (pas absent) : le normaliser en null,
            // sinon MySQL rejette '' comme valeur DATETIME (SQLSTATE 22007).
            $date_livraison = trim($_POST['date_livraison'] ?? '') ?: null;

            try {
                $this->workflow->createLogistics(
                    $vente['Id_Vente'],
                    $entreprise_id,
                    $transporteur,
                    $numero_suivi,
                    $date_livraison
                );
                $success = "Logistique créée avec succès !";
                $etape = '3';
                $logistique_existe = true;
            } catch (\InvalidArgumentException | \PDOException $e) {
                $error = "Erreur lors de la création : " . $e->getMessage();
                $etape = '2'; // rester sur le formulaire logistique (l'URL porte encore etape=1)
            }
        }

        $this->render('vente_workflow/index', [
            'numero_vente'        => $numero_vente,
            'vente'               => $vente,
            'logistique_existe'   => $logistique_existe,
            'success'             => $success,
            'error'               => $error,
            'etape'               => $etape,
            'avec_livraison'      => $avec_livraison,
        ], 'Workflow Vente');
    }
}
