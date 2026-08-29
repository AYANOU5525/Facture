<?php

namespace App\Controllers;

/** Contrôleur de pages/annonces.php — annonces B2B (appels d'offre, partenariats). */
class AnnonceController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutGererB2B());

        $stmt = $this->pdo->prepare("SELECT Id_Entreprise, Latitude, Longitude FROM Entreprise WHERE Id_Entreprise = (SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?)");
        $stmt->execute([$_SESSION['user_id']]);
        $mon_ent = $stmt->fetch();
        $mon_entreprise_id = $mon_ent['Id_Entreprise'];
        $j_ai_coords = !empty($mon_ent['Latitude']) && !empty($mon_ent['Longitude']);

        $success = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
            [$success, $error] = $this->handleAjouter($mon_entreprise_id);
        }

        // Filtrage
        $entreprise_filtre = $_GET['entreprise'] ?? '';
        $type_filtre = $_GET['type'] ?? '';

        $sql = "SELECT a.*, e.Nom_Entreprise, e.Tel_Entreprise, e.Email_Entreprise, e.Latitude, e.Longitude
                FROM Annonce a
                JOIN Entreprise e ON a.Id_Entreprise = e.Id_Entreprise
                WHERE a.Statut = 'active'";

        $params = [];
        if ($entreprise_filtre) {
            $sql .= " AND a.Id_Entreprise = ?";
            $params[] = $entreprise_filtre;
        }
        if ($type_filtre) {
            $sql .= " AND a.Type_Annonce = ?";
            $params[] = $type_filtre;
        }

        $sql .= " ORDER BY a.Date_Publication DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $annonces = $stmt->fetchAll();

        $this->render('annonces/index', [
            'success'            => $success,
            'error'              => $error,
            'annonces'           => $annonces,
            'type_filtre'        => $type_filtre,
            'j_ai_coords'        => $j_ai_coords,
            'mon_ent'            => $mon_ent,
            'mon_entreprise_id'  => $mon_entreprise_id,
        ], 'Annonces B2B');
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleAjouter(int $mon_entreprise_id): array
    {
        exigerCsrf();
        try {
            $type = $_POST['type_annonce'];
            $titre = $_POST['titre'];
            $description = $_POST['description'];

            // Insertion simplifiée selon facturation.sql (pas de produit, pas de date expiration)
            $stmt = $this->pdo->prepare("
                INSERT INTO Annonce (Id_Entreprise, Type_Annonce, Titre, Description, Date_Publication, Statut)
                VALUES (?, ?, ?, ?, NOW(), 'active')
            ");
            $stmt->execute([
                $mon_entreprise_id,
                $type,
                $titre,
                $description
            ]);

            return ['Annonce publiée avec succès !', ''];
        } catch (\PDOException $e) {
            return ['', 'Erreur lors de la publication : ' . $e->getMessage()];
        }
    }
}
