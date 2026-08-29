<?php

namespace App\Controllers;

/** Contrôleur de pages/clients.php — liste des clients B2B et directs. */
class ClientController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutVoirClients());

        $entreprise_id = $_SESSION['entreprise_id'];

        $per_page    = 20;
        $page_direct = max(1, (int) ($_GET['page'] ?? 1));
        $offset_d    = ($page_direct - 1) * $per_page;

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT Nom_Client) FROM Vente WHERE Id_Entreprise = ?");
        $stmt->execute([$entreprise_id]);
        $total_directs = (int) $stmt->fetchColumn();
        $pages_directs = (int) ceil($total_directs / $per_page);

        $stmt = $this->pdo->prepare("
            SELECT
                Nom_Client,
                COUNT(*) as Nb_Commandes,
                SUM(Montant_Total) as Total_Depense,
                MAX(Date_Vente) as Derniere_Commande
            FROM Vente
            WHERE Id_Entreprise = ?
            GROUP BY Nom_Client
            ORDER BY Total_Depense DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$entreprise_id, $per_page, $offset_d]);
        $clients_directs = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("
            SELECT
                e.Id_Entreprise,
                e.Nom_Entreprise as Nom_Client,
                e.Tel_Entreprise,
                e.Email_Entreprise,
                COUNT(*) as Nb_Commandes,
                SUM(c.Montant_Total) as Total_Depense,
                MAX(c.Date_Commande) as Derniere_Commande
            FROM Commande_B2B c
            JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
            WHERE c.Id_Entreprise_Vendeuse = ?
            GROUP BY c.Id_Entreprise_Acheteuse
            ORDER BY Total_Depense DESC
        ");
        $stmt->execute([$entreprise_id]);
        $clients_b2b = $stmt->fetchAll();

        // Global KPIs
        $total_b2b_volume = array_sum(array_column($clients_b2b, 'Total_Depense'));
        $total_dir_volume = array_sum(array_column($clients_directs, 'Total_Depense'));
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
        ], 'Clients Uniques');
    }
}
