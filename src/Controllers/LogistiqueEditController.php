<?php

namespace App\Controllers;

use App\Application\Logistics\LogisticsService;
use App\Infrastructure\Persistence\LogisticsRepository;

/** Contrôleur de pages/logistique_edit.php — mise à jour du suivi d'une livraison (vue livreur simplifiée + vue proprio complète avec carte). */
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

        if (!isset($_GET['id'])) {
            $this->redirect('logistique.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            exigerCsrf();
        }

        $id_logistique = (int) $_GET['id'];
        $stmt = $this->pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $entreprise_id = $stmt->fetchColumn();

        // Récupérer l'entrée logistique
        $log = $this->logistics->find($id_logistique, (int) $entreprise_id);

        if (!$log) {
            die("Entrée logistique non trouvée ou accès refusé.");
        }

        $error = '';
        $success = '';

        // Important : ne traiter/écrire qu'en POST. Un simple GET (ouvrir la page pour
        // consulter ou cliquer "Carte"/"Traiter") ne doit jamais modifier l'entrée — sans
        // cette garde, $_POST est vide sur GET et statut retombe sur 'traitement' par
        // défaut, écrasant silencieusement le transporteur/suivi déjà enregistrés.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $transporteur = trim($_POST['transporteur'] ?? '');
            $numero_suivi = trim($_POST['numero_suivi'] ?? '');
            $statut = $_POST['statut'] ?? 'traitement';
            $statuts_valides = ['traitement', 'en_attente', 'expediee', 'livree', 'annulee'];
            // Les champs date laissés vides arrivent en '' (pas absents) : les normaliser en
            // null ici, sinon MySQL rejette '' comme valeur DATETIME (SQLSTATE 22007).
            $date_exp = trim($_POST['date_expedition'] ?? '') ?: null;
            $date_prevue = trim($_POST['date_prevue'] ?? '') ?: null;
            $date_livree = trim($_POST['date_livraison'] ?? '') ?: null;
            $notes = $_POST['notes'] ?? '';
            $adresse_livraison = $_POST['adresse_livraison'] ?? '';
            $lat_livraison = !empty($_POST['lat_livraison']) ? floatval($_POST['lat_livraison']) : null;
            $lng_livraison = !empty($_POST['lng_livraison']) ? floatval($_POST['lng_livraison']) : null;

            try {
                if (!in_array($statut, $statuts_valides, true)) {
                    throw new \InvalidArgumentException('Statut de livraison invalide.');
                }

                if ($statut === 'expediee') {
                    if ($transporteur === '' || $numero_suivi === '') {
                        throw new \InvalidArgumentException('Le transporteur et le numéro de suivi sont requis pour une expédition.');
                    }
                    $date_exp = $date_exp ?: date('Y-m-d H:i:s');
                }

                if ($statut === 'livree') {
                    $date_livree = $date_livree ?: date('Y-m-d H:i:s');
                }

                $event = $this->logistics->update($id_logistique, (int) $entreprise_id, [
                    'carrier' => $transporteur,
                    'tracking' => $numero_suivi,
                    'status' => $statut,
                    'date_expedition' => $date_exp,
                    'date_prevue' => $date_prevue,
                    'date_livraison' => $date_livree,
                    'notes' => $notes,
                    'address' => $adresse_livraison,
                    'latitude' => $lat_livraison,
                    'longitude' => $lng_livraison,
                    'command_id' => (int) ($log['Id_Commande_B2B'] ?? 0),
                ]);

                if ($event) {
                    require_once __DIR__ . '/../../includes/b2b_helpers.php';
                    $cmd = $event['command'];
                    $id_cmd = (int) $log['Id_Commande_B2B'];
                    $note = $event['new_status'] === 'expediee'
                        ? "Mis en livraison (N° Suivi: $numero_suivi)"
                        : 'Livrée par le transporteur';
                    enregistrerHistoriqueCommande($this->pdo, $id_cmd, $event['old_status'], $event['new_status'], $note, $entreprise_id);
                    if ($event['new_status'] === 'expediee') {
                        creerNotificationB2b($this->pdo, (int) $cmd['Id_Entreprise_Acheteuse'], 'expedition', "🚚 Commande {$cmd['Numero_Commande']} en livraison", "Votre commande {$cmd['Numero_Commande']} a été expédiée via $transporteur (N° de suivi : $numero_suivi).", $id_cmd);
                    } else {
                        creerNotificationB2b($this->pdo, (int) $cmd['Id_Entreprise_Acheteuse'], 'livraison', "✅ Commande {$cmd['Numero_Commande']} livrée", "La livraison de votre commande {$cmd['Numero_Commande']} est arrivée.", $id_cmd);
                        creerNotificationB2b($this->pdo, (int) $cmd['Id_Entreprise_Vendeuse'], 'reception', "🏆 Commande {$cmd['Numero_Commande']} livrée", "La livraison de votre commande {$cmd['Numero_Commande']} a été complétée.", $id_cmd);
                    }
                }

                $success = "Le suivi logistique a été mis à jour.";
                // Rafraîchir les données
                $log = $this->logistics->find($id_logistique, (int) $entreprise_id);
            } catch (\Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $error = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
        }

        if (aRole(ROLE_LIVREUR)) {
            $this->showLivreur($log, $success, $error);
            return;
        }

        $this->showProprio($log, $success, $error);
    }

    private function showLivreur(array $log, string $success, string $error): void
    {
        $dest = $log['Id_Commande_B2B'] ? ($log['Nom_Acheteur'] ?? '-') : ($log['Nom_Client'] ?? '-');
        $ref  = $log['Id_Commande_B2B'] ? ($log['Numero_Commande'] ?? '-') : ($log['Numero_Vente'] ?? '-');
        $statut_actuel = $log['Statut_Livraison'];
        $statut_labels = [
            'traitement'  => ['label' => 'En préparation', 'badge' => 'secondary'],
            'en_attente'  => ['label' => 'En attente d\'enlèvement', 'badge' => 'warning'],
            'expediee'    => ['label' => 'En route', 'badge' => 'info'],
            'livree'      => ['label' => 'Livrée', 'badge' => 'success'],
            'annulee'     => ['label' => 'Annulée', 'badge' => 'danger'],
        ];
        $sl = $statut_labels[$statut_actuel] ?? ['label' => $statut_actuel, 'badge' => 'secondary'];

        $lat_dst = !empty($log['Adresse_Livraison_Lat']) ? floatval($log['Adresse_Livraison_Lat']) : (!empty($log['Lat_Acheteur']) ? floatval($log['Lat_Acheteur']) : null);
        $lng_dst = !empty($log['Adresse_Livraison_Lng']) ? floatval($log['Adresse_Livraison_Lng']) : (!empty($log['Lng_Acheteur']) ? floatval($log['Lng_Acheteur']) : null);
        $step_prepare = in_array($statut_actuel, ['traitement', 'en_attente', 'expediee', 'livree']);
        $step_expedie = in_array($statut_actuel, ['expediee', 'livree']);
        $step_livre   = $statut_actuel === 'livree';

        $this->render('logistique_edit/livreur', [
            'log' => $log,
            'success' => $success,
            'error' => $error,
            'dest' => $dest,
            'ref' => $ref,
            'statut_actuel' => $statut_actuel,
            'sl' => $sl,
            'lat_dst' => $lat_dst,
            'lng_dst' => $lng_dst,
            'step_prepare' => $step_prepare,
            'step_expedie' => $step_expedie,
            'step_livre' => $step_livre,
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
            'lat_dep' => $lat_dep,
            'lng_dep' => $lng_dep,
            'label_dep' => $label_dep,
            'lat_dst' => $lat_dst,
            'lng_dst' => $lng_dst,
            'label_dst' => $label_dst,
            'is_b2b' => $is_b2b,
        ], 'Suivi Livraison');
    }
}
