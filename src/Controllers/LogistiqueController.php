<?php

namespace App\Controllers;

/** Contrôleur de pages/logistique.php — liste des expéditions/livraisons avec filtres. */
class LogistiqueController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutVoirExpeditions());

        $stmt = $this->pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $entreprise_id = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT l.*,
                   v.Nom_Client,
                   v.Numero_Vente,
                   c.Numero_Commande,
                   e.Nom_Entreprise AS Nom_Acheteur
            FROM Logistique l
            LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
            LEFT JOIN Commande_B2B c ON l.Id_Commande_B2B = c.Id_Commande_B2B
            LEFT JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
            WHERE l.Id_Entreprise = ?
            ORDER BY l.Id_Logistique DESC
        ");
        $stmt->execute([$entreprise_id]);
        $logistique_brut = $stmt->fetchAll();

        $recherche = trim($_GET['q'] ?? '');
        $statut_filtre = $_GET['statut'] ?? '';
        $statuts_valides = ['traitement', 'en_attente', 'expediee', 'livree', 'annulee'];
        if (!in_array($statut_filtre, $statuts_valides, true)) {
            $statut_filtre = '';
        }

        // Filtrage
        $logistique = $logistique_brut;
        if ($recherche !== '' || $statut_filtre !== '') {
            $logistique = array_values(array_filter($logistique, function (array $ligne) use ($recherche, $statut_filtre): bool {
                $texte = implode(' ', [
                    $ligne['Numero_Commande'] ?? '',
                    $ligne['Numero_Vente'] ?? '',
                    $ligne['Nom_Acheteur'] ?? '',
                    $ligne['Nom_Client'] ?? '',
                    $ligne['Transporteur'] ?? '',
                    $ligne['Numero_Suivi'] ?? '',
                ]);

                $correspond_recherche = $recherche === ''
                    || stripos($texte, $recherche) !== false;
                $correspond_statut = $statut_filtre === ''
                    || ($ligne['Statut_Livraison'] ?? '') === $statut_filtre;

                return $correspond_recherche && $correspond_statut;
            }));
        }

        // Métriques pour les KPI cards
        $nb_total = count($logistique_brut);
        $nb_attente = count(array_filter($logistique_brut, fn($l) => in_array($l['Statut_Livraison'], ['traitement', 'en_attente'])));
        $nb_route = count(array_filter($logistique_brut, fn($l) => $l['Statut_Livraison'] === 'expediee'));
        $nb_livrees = count(array_filter($logistique_brut, fn($l) => $l['Statut_Livraison'] === 'livree'));
        $nb_retard = 0;
        foreach ($logistique_brut as $l) {
            $dp = $l['Date_Livraison_Prevue'];
            if ($dp && strtotime($dp) < time() && $l['Statut_Livraison'] !== 'livree' && $l['Statut_Livraison'] !== 'annulee') {
                $nb_retard++;
            }
        }

        $this->render('logistique/index', [
            'logistique'     => $logistique,
            'recherche'      => $recherche,
            'statut_filtre'  => $statut_filtre,
            'nb_total'       => $nb_total,
            'nb_attente'     => $nb_attente,
            'nb_route'       => $nb_route,
            'nb_livrees'     => $nb_livrees,
            'nb_retard'      => $nb_retard,
        ], 'Suivi Logistique');
    }
}
