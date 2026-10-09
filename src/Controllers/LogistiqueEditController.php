<?php

namespace App\Controllers;

use App\Application\Logistics\LogisticsService;
use App\Infrastructure\Persistence\LogisticsRepository;

/**
 * Contrôleur de pages/logistique_edit.php — fiche d'une livraison.
 *
 * Actions (POST, une par étape, cf. LogisticsService) :
 *  - expedier         : propriétaire ; transporteur + date prévue obligatoires, N° de suivi
 *                       déjà attribué. Commande B2B : facture générée, commande « expediee ».
 *  - confirmer_remise : livreur assigné (ou propriétaire au nom d'un transporteur externe).
 *  - annuler          : propriétaire, tant que la livraison n'est pas expédiée.
 * Vue livreur simplifiée (ses livraisons uniquement), vue propriétaire complète avec carte.
 */
class LogistiqueEditController extends Controller
{
    private LogisticsService $logistics;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->logistics = new LogisticsService($pdo, new LogisticsRepository($pdo));
    }

    public function edit(): void
    {
        exigerPermission(peutModifierStatutExpedition());

        $id_logistique = (int) ($_GET['id'] ?? 0);
        $entreprise_id = (int) $_SESSION['entreprise_id'];
        $user_id = (int) ($_SESSION['user_id'] ?? 0);
        $est_proprio = aRole(ROLE_PROPRIO);

        $log = $id_logistique > 0 ? $this->logistics->find($id_logistique, $entreprise_id) : null;
        if (!$log) {
            $this->redirect('logistique.php');
        }
        // Un livreur ne voit que les livraisons qui lui sont assignées.
        if (!$est_proprio && (int) $log['Id_Livreur'] !== $user_id) {
            exigerPermission(false);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
            $flash = $this->handlePost($log, $entreprise_id, $user_id, $est_proprio);
            // Post/Redirect/Get : un rafraîchissement ne renvoie pas le formulaire.
            $_SESSION['flash_logistique'] = $flash;
            $this->redirect('logistique_edit.php?id=' . $id_logistique);
        }

        [$success, $error] = $_SESSION['flash_logistique'] ?? ['', ''];
        unset($_SESSION['flash_logistique']);

        if (!$est_proprio) {
            $this->showLivreur($log, $success, $error);
            return;
        }

        $this->showProprio($log, $success, $error);
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handlePost(array $log, int $entreprise_id, int $user_id, bool $est_proprio): array
    {
        $action = $_POST['action'] ?? '';
        $id = (int) $log['Id_Logistique'];

        try {
            if ($action === 'expedier' && $est_proprio) {
                $equipe = ($_POST['type_transporteur'] ?? 'equipe') === 'equipe';
                $result = $this->logistics->ship($id, $entreprise_id, [
                    'carrier_id' => $equipe ? (int) ($_POST['livreur_id'] ?? 0) : null,
                    'carrier_name' => $equipe ? '' : (string) ($_POST['transporteur_externe'] ?? ''),
                    'date_prevue' => $_POST['date_prevue'] ?? null,
                    'notes' => (string) ($_POST['notes'] ?? ''),
                    'address' => (string) ($_POST['adresse_livraison'] ?? ''),
                    'latitude' => ($_POST['lat_livraison'] ?? '') !== '' ? (float) $_POST['lat_livraison'] : null,
                    'longitude' => ($_POST['lng_livraison'] ?? '') !== '' ? (float) $_POST['lng_livraison'] : null,
                ], (string) ($_SESSION['username'] ?? ''), $user_id);

                if ($result['order']) {
                    $this->notifierExpedition($log, $result);
                    return ["Expédition validée (N° de suivi {$result['tracking']}). Facture N° {$result['invoice_number']} générée, l'acheteur a été notifié.", ''];
                }
                return ["Expédition validée (N° de suivi {$result['tracking']}).", ''];
            }

            if ($action === 'confirmer_remise') {
                $result = $this->logistics->confirmByCarrier($id, $entreprise_id, $user_id, $est_proprio, (string) ($_POST['notes'] ?? ''));
                if ($result['order']) {
                    $this->notifierRemise($result['order'], $result['finalized']);
                    return [$result['finalized']
                        ? "Remise confirmée : l'acheteur ayant déjà confirmé la réception, la livraison est terminée."
                        : "Remise confirmée. La livraison sera close dès que l'acheteur aura confirmé la réception.", ''];
                }
                return ['Remise confirmée : la livraison est terminée.', ''];
            }

            if ($action === 'annuler' && $est_proprio) {
                $this->logistics->cancel($id, $entreprise_id);
                return [$log['Id_Commande_B2B']
                    ? "Livraison annulée. La commande reste prête : « Expédier » ouvrira une nouvelle fiche."
                    : 'Livraison annulée.', ''];
            }

            return ['', 'Action non autorisée.'];
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['', $e->getMessage()];
        }
    }

    private function notifierExpedition(array $log, array $result): void
    {
        require_once __DIR__ . '/../../includes/b2b_helpers.php';
        $cmd = $result['order'];
        $id_cmd = (int) $cmd['Id_Commande_B2B'];
        $vendeur = $log['Nom_Vendeur'] ?? 'votre fournisseur';
        $prevue = date('d/m/Y', strtotime($result['date_prevue']));
        creerNotificationB2b(
            $this->pdo,
            (int) $cmd['Id_Entreprise_Acheteuse'],
            'expedition',
            "Commande {$cmd['Numero_Commande']} expédiée",
            "Votre commande {$cmd['Numero_Commande']} a été expédiée par $vendeur via {$result['carrier']} (N° de suivi : {$result['tracking']}), livraison prévue le $prevue. "
                . "Facture N° {$result['invoice_number']} : consultable dans Factures › Factures d'achat, ou depuis la commande. Confirmez la réception à son arrivée.",
            $id_cmd
        );
    }

    private function notifierRemise(array $cmd, bool $finalized): void
    {
        require_once __DIR__ . '/../../includes/b2b_helpers.php';
        $id_cmd = (int) $cmd['Id_Commande_B2B'];
        if (!$finalized) {
            creerNotificationB2b(
                $this->pdo,
                (int) $cmd['Id_Entreprise_Acheteuse'],
                'livraison',
                "Commande {$cmd['Numero_Commande']} remise par le livreur",
                "Le livreur a confirmé la remise de votre commande {$cmd['Numero_Commande']}. Confirmez sa réception dans Commandes B2B pour clôturer la livraison.",
                $id_cmd
            );
            return;
        }
        creerNotificationB2b(
            $this->pdo,
            (int) $cmd['Id_Entreprise_Acheteuse'],
            'livraison',
            "Commande {$cmd['Numero_Commande']} livrée",
            "La livraison de votre commande {$cmd['Numero_Commande']} est terminée. Ouvrez Approvisionnement pour choisir les quantités à ajouter à votre stock.",
            $id_cmd
        );
        creerNotificationB2b(
            $this->pdo,
            (int) $cmd['Id_Entreprise_Vendeuse'],
            'reception',
            "Commande {$cmd['Numero_Commande']} livrée",
            "Le livreur et l'acheteur ont confirmé la livraison de la commande {$cmd['Numero_Commande']}.",
            $id_cmd
        );
    }

    private function showLivreur(array $log, string $success, string $error): void
    {
        $dest = $log['Id_Commande_B2B'] ? ($log['Nom_Acheteur'] ?? '-') : ($log['Nom_Client'] ?? '-');
        $ref  = $log['Id_Commande_B2B'] ? ($log['Numero_Commande'] ?? '-') : ($log['Numero_Vente'] ?? '-');

        $lat_dst = !empty($log['Adresse_Livraison_Lat']) ? floatval($log['Adresse_Livraison_Lat']) : (!empty($log['Lat_Acheteur']) ? floatval($log['Lat_Acheteur']) : null);
        $lng_dst = !empty($log['Adresse_Livraison_Lng']) ? floatval($log['Adresse_Livraison_Lng']) : (!empty($log['Lng_Acheteur']) ? floatval($log['Lng_Acheteur']) : null);

        $this->render('logistique_edit/livreur', [
            'log' => $log,
            'success' => $success,
            'error' => $error,
            'dest' => $dest,
            'ref' => $ref,
            'sl' => libelleStatutLivraison($log),
            'lat_dst' => $lat_dst,
            'lng_dst' => $lng_dst,
            'etapes' => $this->etapes($log),
        ], 'Suivi Livraison');
    }

    private function showProprio(array $log, string $success, string $error): void
    {
        // Déterminer coordonnées de départ (vendeur) et destination (acheteur)
        $lat_dep = !empty($log['Lat_Vendeur']) ? floatval($log['Lat_Vendeur']) : (!empty($log['Ma_Latitude']) ? floatval($log['Ma_Latitude']) : 6.1372);
        $lng_dep = !empty($log['Lng_Vendeur']) ? floatval($log['Lng_Vendeur']) : (!empty($log['Ma_Longitude']) ? floatval($log['Ma_Longitude']) : 1.2125);
        $label_dep = $log['Nom_Vendeur'] ?? $log['Mon_Nom_Entreprise'] ?? 'Départ';

        $lat_dst = !empty($log['Adresse_Livraison_Lat']) ? floatval($log['Adresse_Livraison_Lat']) : (!empty($log['Lat_Acheteur']) ? floatval($log['Lat_Acheteur']) : null);
        $lng_dst = !empty($log['Adresse_Livraison_Lng']) ? floatval($log['Adresse_Livraison_Lng']) : (!empty($log['Lng_Acheteur']) ? floatval($log['Lng_Acheteur']) : null);
        $label_dst = $log['Nom_Acheteur'] ?? $log['Nom_Client'] ?? 'Destination';
        $is_b2b = $log['Id_Commande_B2B'] ? 1 : 0;

        $this->render('logistique_edit/proprio', [
            'log' => $log,
            'success' => $success,
            'error' => $error,
            'sl' => libelleStatutLivraison($log),
            'etapes' => $this->etapes($log),
            'livreurs' => $log['Statut_Livraison'] === 'traitement' ? $this->logistics->carriers((int) $log['Id_Entreprise']) : [],
            'lat_dep' => $lat_dep,
            'lng_dep' => $lng_dep,
            'label_dep' => $label_dep,
            'lat_dst' => $lat_dst,
            'lng_dst' => $lng_dst,
            'label_dst' => $label_dst,
            'is_b2b' => $is_b2b,
        ], 'Suivi Livraison');
    }

    /**
     * Étapes affichées dans le stepper : à planifier → en route → remise → livrée.
     * « Remise » couvre les deux confirmations (livreur, acheteur) d'une commande B2B.
     *
     * @return list<array{label: string, icon: string, etat: string}>
     */
    private function etapes(array $log): array
    {
        $statut = $log['Statut_Livraison'];
        $expediee = in_array($statut, ['expediee', 'livree'], true);
        $remise = !empty($log['Date_Confirmation_Livreur']) || $statut === 'livree';
        $livree = $statut === 'livree';

        $etat = static fn (bool $fait, bool $encours): string => $fait ? 'done' : ($encours ? 'active' : 'pending');

        return [
            ['label' => 'À planifier', 'icon' => 'fa-clipboard-list', 'etat' => $etat($expediee, $statut === 'traitement')],
            ['label' => 'En route', 'icon' => 'fa-truck', 'etat' => $etat($remise, $expediee && !$remise)],
            ['label' => 'Remise', 'icon' => 'fa-hand-holding', 'etat' => $etat($livree, $remise && !$livree)],
            ['label' => 'Livrée', 'icon' => 'fa-check-circle', 'etat' => $etat($livree, false)],
        ];
    }
}
