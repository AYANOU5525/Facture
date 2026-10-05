<?php

namespace App\Controllers;

/** Contrôleur de pages/logistique.php — liste des expéditions/livraisons avec filtres. */
class LogistiqueController extends Controller
{
    private const PER_PAGE = 5;

    public function index(): void
    {
        exigerPermission(peutVoirExpeditions());

        $entreprise_id = (int) $_SESSION['entreprise_id'];

        $recherche = trim($_GET['q'] ?? '');
        $statut_filtre = $_GET['statut'] ?? '';
        $statuts_valides = ['traitement', 'en_attente', 'expediee', 'livree', 'annulee'];
        if (!in_array($statut_filtre, $statuts_valides, true)) {
            $statut_filtre = '';
        }

        // Filtrage au niveau SQL (plutôt que charger toute la table et filtrer en PHP) :
        // reste rapide même quand l'historique de livraisons grossit.
        $conditions = ['l.Id_Entreprise = ?'];
        $params = [$entreprise_id];

        if ($statut_filtre !== '') {
            $conditions[] = 'l.Statut_Livraison = ?';
            $params[] = $statut_filtre;
        }
        if ($recherche !== '') {
            $conditions[] = '(c.Numero_Commande LIKE ? OR v.Numero_Vente LIKE ? OR e.Nom_Entreprise LIKE ? OR v.Nom_Client LIKE ? OR l.Transporteur LIKE ? OR l.Numero_Suivi LIKE ?)';
            $like = '%' . $recherche . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        $where = implode(' AND ', $conditions);
        $joins = "
            FROM Logistique l
            LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
            LEFT JOIN Commande_B2B c ON l.Id_Commande_B2B = c.Id_Commande_B2B
            LEFT JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
        ";

        $stmt = $this->pdo->prepare("SELECT COUNT(*) $joins WHERE $where");
        $stmt->execute($params);
        $nb_resultats = (int) $stmt->fetchColumn();

        $total_pages = max(1, (int) ceil($nb_resultats / self::PER_PAGE));
        $page = max(1, min($total_pages, (int) ($_GET['p'] ?? 1)));
        $offset = ($page - 1) * self::PER_PAGE;

        $stmt = $this->pdo->prepare("
            SELECT l.*, v.Nom_Client, v.Numero_Vente, c.Numero_Commande, e.Nom_Entreprise AS Nom_Acheteur
            $joins
            WHERE $where
            ORDER BY l.Id_Logistique DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([...$params, self::PER_PAGE, $offset]);
        $logistique = $stmt->fetchAll();

        // Métriques des KPI cards : comptent l'ensemble de l'entreprise (indépendamment
        // des filtres de recherche/statut/pagination de la liste ci-dessus), en une seule
        // requête agrégée plutôt qu'en itérant sur toutes les lignes côté PHP.
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(CASE WHEN Statut_Livraison IN ('traitement', 'en_attente') THEN 1 ELSE 0 END) AS nb_attente,
                SUM(CASE WHEN Statut_Livraison = 'expediee' THEN 1 ELSE 0 END) AS nb_route,
                SUM(CASE WHEN Statut_Livraison = 'livree' THEN 1 ELSE 0 END) AS nb_livrees,
                SUM(CASE WHEN Date_Livraison_Prevue IS NOT NULL AND Date_Livraison_Prevue < NOW()
                          AND Statut_Livraison NOT IN ('livree', 'annulee') THEN 1 ELSE 0 END) AS nb_retard
            FROM Logistique
            WHERE Id_Entreprise = ?
        ");
        $stmt->execute([$entreprise_id]);
        $kpi = $stmt->fetch();

        $this->render('logistique/index', [
            'logistique'     => $logistique,
            'recherche'      => $recherche,
            'statut_filtre'  => $statut_filtre,
            'nb_resultats'   => $nb_resultats,
            'nb_attente'     => (int) ($kpi['nb_attente'] ?? 0),
            'nb_route'       => (int) ($kpi['nb_route'] ?? 0),
            'nb_livrees'     => (int) ($kpi['nb_livrees'] ?? 0),
            'nb_retard'      => (int) ($kpi['nb_retard'] ?? 0),
            'page'           => $page,
            'total_pages'    => $total_pages,
        ], 'Suivi Logistique');
    }
}
