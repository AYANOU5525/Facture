<?php

namespace App\Controllers;

/** Contrôleur de pages/client_history.php — historique de transactions d'un client (B2B ou direct). */
class ClientHistoryController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutVoirClients());

        // Redirection si paramètres manquants
        if (!isset($_GET['type']) || (!isset($_GET['id']) && !isset($_GET['name']))) {
            $this->redirect('clients.php');
        }

        $type = $_GET['type'];
        $entreprise_id = $_SESSION['entreprise_id'];
        $client_name = '';
        $history = [];
        $client_id = null;

        if ($type === 'b2b' && isset($_GET['id'])) {
            $target_id = (int) $_GET['id'];
            $client_id = $target_id;

            $check = $this->pdo->prepare("
                SELECT 1 FROM Commande_B2B
                WHERE (Id_Entreprise_Vendeuse = ? AND Id_Entreprise_Acheteuse = ?)
                   OR (Id_Entreprise_Vendeuse = ? AND Id_Entreprise_Acheteuse = ?)
                LIMIT 1
            ");
            $check->execute([$entreprise_id, $target_id, $target_id, $entreprise_id]);
            if (!$check->fetchColumn()) {
                $this->redirect('clients.php');
            }

            $stmt = $this->pdo->prepare("SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
            $stmt->execute([$target_id]);
            $client_name = $stmt->fetchColumn() ?: 'Entreprise inconnue';

            $stmt = $this->pdo->prepare("
                SELECT
                    'Commande B2B' as Type_Doc,
                    Numero_Commande as Reference,
                    Date_Commande as Date_Doc,
                    Montant_Total,
                    Statut as Etat
                FROM Commande_B2B
                WHERE Id_Entreprise_Vendeuse = ? AND Id_Entreprise_Acheteuse = ?
                ORDER BY Date_Commande DESC
            ");
            $stmt->execute([$entreprise_id, $target_id]);
            $history = $stmt->fetchAll();
        } elseif ($type === 'direct' && isset($_GET['name'])) {
            $client_name = $_GET['name'];

            $stmt = $this->pdo->prepare("
                SELECT
                    'Vente Directe' as Type_Doc,
                    Numero_Vente as Reference,
                    Date_Vente as Date_Doc,
                    Montant_Total,
                    'Validée' as Etat
                FROM Vente
                WHERE Id_Entreprise = ? AND Nom_Client = ?
                ORDER BY Date_Vente DESC
            ");
            $stmt->execute([$entreprise_id, $client_name]);
            $history = $stmt->fetchAll();
        }

        // Statistiques globales
        $total_montant = array_sum(array_column($history, 'Montant_Total'));
        $nb_transactions = count($history);
        $derniere_transaction = !empty($history) ? $history[0]['Date_Doc'] : null;
        $montant_moyen = $nb_transactions > 0 ? $total_montant / $nb_transactions : 0;

        // Initiales pour l'avatar
        $initiales = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $client_name), 0, 2)));

        $this->render('client_history/index', [
            'type'                   => $type,
            'client_name'            => $client_name,
            'client_id'              => $client_id,
            'history'                => $history,
            'total_montant'          => $total_montant,
            'nb_transactions'        => $nb_transactions,
            'derniere_transaction'   => $derniere_transaction,
            'montant_moyen'          => $montant_moyen,
            'initiales'              => $initiales,
        ], "Historique Client : " . htmlspecialchars($client_name ?? ''));
    }
}
