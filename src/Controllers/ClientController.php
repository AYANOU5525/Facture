<?php

namespace App\Controllers;

use App\Infrastructure\Persistence\ClientRepository;

/** Contrôleur de pages/clients.php — liste des clients B2B et directs. */
class ClientController extends Controller
{
    private ClientRepository $clients;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->clients = new ClientRepository($pdo);
    }

    public function index(): void
    {
        exigerPermission(peutVoirClients());

        $entreprise_id = $_SESSION['entreprise_id'];
        $success = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_contact') {
            exigerCsrf();
            [$success, $error] = $this->handleUpdateContact((int) $entreprise_id);
        }

        $per_page    = 5;
        $page_direct = max(1, (int) ($_GET['page'] ?? 1));
        $offset_d    = ($page_direct - 1) * $per_page;

        // Clients directs = ventes au comptoir uniquement : les ventes B2B (Type_Vente 'b2b')
        // sont déjà comptées dans la section partenaires B2B plus bas.
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT Nom_Client), COALESCE(SUM(Montant_Total), 0)
            FROM Vente WHERE Id_Entreprise = ? AND Type_Vente = 'directe' AND " . \App\Application\Billing\InvoiceCancellationService::notCancelledSql() . "
        ");
        $stmt->execute([$entreprise_id]);
        [$total_directs, $total_dir_volume] = $stmt->fetch(\PDO::FETCH_NUM);
        $total_directs = (int) $total_directs;
        $pages_directs = (int) ceil($total_directs / $per_page);

        $stmt = $this->pdo->prepare("
            SELECT
                Nom_Client,
                COUNT(*) as Nb_Commandes,
                SUM(Montant_Total) as Total_Depense,
                MAX(Date_Vente) as Derniere_Commande
            FROM Vente
            WHERE Id_Entreprise = ? AND Type_Vente = 'directe' AND " . \App\Application\Billing\InvoiceCancellationService::notCancelledSql() . "
            GROUP BY Nom_Client
            ORDER BY Total_Depense DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$entreprise_id, $per_page, $offset_d]);
        $clients_directs = $stmt->fetchAll();

        $fiches = $this->clients->byNameForEnterprise((int) $entreprise_id);
        foreach ($clients_directs as &$c) {
            $c['fiche'] = $fiches[$c['Nom_Client']] ?? null;
        }
        unset($c);

        $stmt = $this->pdo->prepare("
            SELECT
                e.Id_Entreprise,
                e.Nom_Entreprise as Nom_Client,
                e.Tel_Entreprise,
                e.Email_Entreprise,
                COUNT(*) as Nb_Commandes,
                -- Montant engagé : commandes acceptées par le vendeur (hors attente et refus)
                SUM(CASE WHEN c.Statut NOT IN ('en_attente', 'a_confirmer') THEN c.Montant_Total ELSE 0 END) as Total_Depense,
                MAX(c.Date_Commande) as Derniere_Commande
            FROM Commande_B2B c
            JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
            WHERE c.Id_Entreprise_Vendeuse = ? AND c.Statut NOT IN ('refusee', 'annulee')
            GROUP BY c.Id_Entreprise_Acheteuse
            ORDER BY Total_Depense DESC
        ");
        $stmt->execute([$entreprise_id]);
        $clients_b2b = $stmt->fetchAll();

        // Global KPIs
        $total_b2b_volume = array_sum(array_column($clients_b2b, 'Total_Depense'));
        // $total_dir_volume : calculé plus haut sur toutes les ventes directes (pas seulement la page affichée)
        $nb_b2b     = count($clients_b2b);
        $nb_directs = $total_directs;

        $this->render('clients/index', [
            'clients_directs'   => $clients_directs,
            'clients_b2b'       => $clients_b2b,
            'total_b2b_volume'  => $total_b2b_volume,
            'total_dir_volume'  => $total_dir_volume,
            'nb_b2b'            => $nb_b2b,
            'nb_directs'        => $nb_directs,
            'total_directs'     => $total_directs,
            'pages_directs'     => $pages_directs,
            'page_direct'       => $page_direct,
            'success'           => $success,
            'error'             => $error,
        ], 'Clients Uniques');
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleUpdateContact(int $entreprise_id): array
    {
        $clientId = (int) ($_POST['id_client'] ?? 0);

        try {
            $this->clients->updateContact($clientId, $entreprise_id, [
                'telephone' => trim((string) ($_POST['telephone'] ?? '')),
                'email'     => trim((string) ($_POST['email'] ?? '')),
                'adresse'   => trim((string) ($_POST['adresse'] ?? '')),
                'nif'       => trim((string) ($_POST['nif'] ?? '')),
                'statut'    => in_array($_POST['statut'] ?? '', ['actif', 'inactif'], true) ? $_POST['statut'] : 'actif',
            ]);

            return ['Fiche client mise à jour.', ''];
        } catch (\Throwable $e) {
            return ['', 'Erreur lors de la mise à jour : ' . $e->getMessage()];
        }
    }
}
