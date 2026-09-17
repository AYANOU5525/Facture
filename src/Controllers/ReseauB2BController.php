<?php

namespace App\Controllers;

/** Contrôleur de pages/reseau_b2b.php — annuaire des entreprises partenaires B2B. */
class ReseauB2BController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutAccederB2B());

        if (!isset($_SESSION['entreprise_id'])) {
            $this->redirect('dashboard.php');
        }

        $entreprise_id = (int) $_SESSION['entreprise_id'];

        $stmt = $this->pdo->prepare("
            SELECT
                Id_Entreprise,
                Nom_Entreprise,
                Latitude,
                Longitude,
                Ville,
                Region
            FROM Entreprise
            WHERE Id_Entreprise = ?
        ");
        $stmt->execute([$entreprise_id]);
        $mon_entreprise = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$mon_entreprise) {
            die("Entreprise introuvable.");
        }

        $mon_latitude = $mon_entreprise['Latitude'];
        $mon_longitude = $mon_entreprise['Longitude'];

        $j_ai_coords =
            $mon_latitude !== null &&
            $mon_longitude !== null &&
            $mon_latitude !== '' &&
            $mon_longitude !== '';

        $j_lat = $j_ai_coords ? (float) $mon_latitude : null;
        $j_lon = $j_ai_coords ? (float) $mon_longitude : null;

        $secteur_filtre = trim($_GET['secteur'] ?? '');
        $ville_filtre   = trim($_GET['ville'] ?? '');
        $region_filtre  = trim($_GET['region'] ?? '');

        $distance_max = isset($_GET['distance'])
            ? max(0, (int) $_GET['distance'])
            : 0;

        $tri_distance =
            isset($_GET['autour_de_moi']) &&
            $_GET['autour_de_moi'] === '1' &&
            $j_ai_coords;

        // ============================================================
        // RÉCUPÉRATION DES ENTREPRISES
        // ============================================================

        $sql = "
            SELECT
                Id_Entreprise,
                Nom_Entreprise,
                Secteur_Activite,
                Description_Entreprise,
                Adresse_Entreprise,
                Tel_Entreprise,
                Email_Entreprise,
                Score_Fiabilite,
                Nombre_Commandes_Completees,
                Latitude,
                Longitude,
                Ville,
                Region
            FROM Entreprise
            WHERE Id_Entreprise != ?
        ";

        $params = [$entreprise_id];

        if ($secteur_filtre !== '') {
            $sql .= " AND Secteur_Activite = ?";
            $params[] = $secteur_filtre;
        }

        if ($ville_filtre !== '') {
            $sql .= " AND Ville = ?";
            $params[] = $ville_filtre;
        }

        if ($region_filtre !== '') {
            $sql .= " AND Region = ?";
            $params[] = $region_filtre;
        }

        $sql .= "
            ORDER BY
                Score_Fiabilite DESC,
                Nombre_Commandes_Completees DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $entreprises = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($entreprises as &$entreprise) {
            $entreprise['distance_km'] = null;
            $entreprise['distance_label'] = 'Distance inconnue';
            $a_des_coords =
                isset($entreprise['Latitude']) &&
                isset($entreprise['Longitude']) &&
                $entreprise['Latitude'] !== null &&
                $entreprise['Longitude'] !== null &&
                $entreprise['Latitude'] !== '' &&
                $entreprise['Longitude'] !== '';
            if ($j_ai_coords && $a_des_coords) {
                $distance = calculDistanceHaversine(
                    $j_lat,
                    $j_lon,
                    (float) $entreprise['Latitude'],
                    (float) $entreprise['Longitude']
                );

                $entreprise['distance_km'] = $distance;
                $entreprise['distance_label'] = formaterDistance($distance);
            }
            $entreprise['reactivite'] = getTempsReponseMoyen(
                $this->pdo,
                (int) $entreprise['Id_Entreprise']
            );
        }
        unset($entreprise);

        if ($distance_max > 0 && $j_ai_coords) {
            $entreprises = array_filter(
                $entreprises,
                function ($entreprise) use ($distance_max) {
                    return
                        $entreprise['distance_km'] !== null &&
                        $entreprise['distance_km'] <= $distance_max;
                }
            );

            $entreprises = array_values($entreprises);
        }

        if ($tri_distance) {
            usort(
                $entreprises,
                function ($a, $b) {
                    $distance_a = $a['distance_km'] ?? PHP_INT_MAX;
                    $distance_b = $b['distance_km'] ?? PHP_INT_MAX;
                    return $distance_a <=> $distance_b;
                }
            );
        }

        $secteurs = $this->pdo->query("
            SELECT DISTINCT Secteur_Activite
            FROM Entreprise
            WHERE Secteur_Activite IS NOT NULL
              AND Secteur_Activite <> ''
            ORDER BY Secteur_Activite
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $villes = $this->pdo->query("
            SELECT DISTINCT Ville
            FROM Entreprise
            WHERE Ville IS NOT NULL
              AND Ville <> ''
            ORDER BY Ville
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $regions = $this->pdo->query("
            SELECT DISTINCT Region
            FROM Entreprise
            WHERE Region IS NOT NULL
              AND Region <> ''
            ORDER BY Region
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $non_lues = $this->pdo->prepare("SELECT COUNT(*) FROM Notification_B2B WHERE Id_Entreprise_Destinataire = ? AND Est_Lue = FALSE");
        $non_lues->execute([$entreprise_id]);
        $nb_non_lues = (int) $non_lues->fetchColumn();

        $this->render('reseau_b2b/index', [
            'entreprises'      => $entreprises,
            'secteurs'         => $secteurs,
            'villes'           => $villes,
            'regions'          => $regions,
            'secteur_filtre'   => $secteur_filtre,
            'ville_filtre'     => $ville_filtre,
            'region_filtre'    => $region_filtre,
            'distance_max'     => $distance_max,
            'tri_distance'     => $tri_distance,
            'j_ai_coords'      => $j_ai_coords,
            'nb_non_lues'      => $nb_non_lues,
        ], 'Réseau B2B');
    }
}
