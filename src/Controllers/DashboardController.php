<?php

namespace App\Controllers;

/** Contrôleur de pages/dashboard.php — tableau de bord, différent selon le rôle. */
class DashboardController extends Controller
{
    public function index(): void
    {
        $entreprise_id = $_SESSION['entreprise_id'];

        if (aRole(ROLE_LIVREUR)) {
            if (!FEATURE_LOGISTIQUE_ACTIVE) {
                $this->showLogistiqueEnPause();
                return;
            }
            $this->showLivreur($entreprise_id);
            return;
        }

        if (estAdminPlateforme()) {
            $this->showAdmin();
            return;
        }

        if (aRole(ROLE_VENDEUR)) {
            $this->showVendeur($entreprise_id);
            return;
        }

        $this->showProprio($entreprise_id);
    }

    private function salutation(): string
    {
        return ((int) date('H') >= 18) ? 'Bonsoir' : 'Bonjour';
    }

    private function showLivreur(int $entreprise_id): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Logistique WHERE Id_Entreprise = ? AND Statut_Livraison IN ('traitement','en_attente')");
        $stmt->execute([$entreprise_id]);
        $a_expedier = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Logistique WHERE Id_Entreprise = ? AND Statut_Livraison = 'expediee'");
        $stmt->execute([$entreprise_id]);
        $en_route = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Logistique WHERE Id_Entreprise = ? AND Statut_Livraison = 'livree' AND DATE(Date_Livraison_Effectuee) = CURDATE()");
        $stmt->execute([$entreprise_id]);
        $livrees_jour = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT l.Id_Logistique, l.Statut_Livraison, l.Date_Livraison_Prevue,
                   l.Transporteur, l.Numero_Suivi, l.Adresse_Livraison,
                   v.Nom_Client, v.Numero_Vente,
                   e.Nom_Entreprise AS Nom_Acheteur, c.Numero_Commande
            FROM Logistique l
            LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
            LEFT JOIN Commande_B2B c ON l.Id_Commande_B2B = c.Id_Commande_B2B
            LEFT JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
            WHERE l.Id_Entreprise = ? AND l.Statut_Livraison NOT IN ('livree','annulee')
            ORDER BY (l.Date_Livraison_Prevue IS NULL) ASC, l.Date_Livraison_Prevue ASC, l.Id_Logistique ASC
            LIMIT 12
        ");
        $stmt->execute([$entreprise_id]);
        $livraisons_actives = $stmt->fetchAll();

        $this->render('dashboard/livreur', [
            'salutation'          => $this->salutation(),
            'a_expedier'          => $a_expedier,
            'en_route'            => $en_route,
            'livrees_jour'        => $livrees_jour,
            'livraisons_actives'  => $livraisons_actives,
        ], 'Tableau de bord');
    }

    private function showLogistiqueEnPause(): void
    {
        $this->render('dashboard/en_pause', [
            'salutation' => $this->salutation(),
        ], 'Tableau de bord');
    }

    private function showAdmin(): void
    {
        $nb_entreprises = (int) $this->pdo->query("SELECT COUNT(*) FROM Entreprise")->fetchColumn();
        $nb_utilisateurs = (int) $this->pdo->query("SELECT COUNT(*) FROM Utilisateur")->fetchColumn();
        $nb_ventes_total = (int) $this->pdo->query("SELECT COUNT(*) FROM Vente")->fetchColumn();
        $ca_total_plateforme = (float) ($this->pdo->query("SELECT COALESCE(SUM(Montant_Total),0) FROM Vente")->fetchColumn());

        $entreprises_recentes = $this->pdo->query("
            SELECT e.Id_Entreprise, e.Nom_Entreprise, e.Secteur_Activite, e.Ville,
                   COUNT(u.Id_Utilisateur) AS nb_membres
            FROM Entreprise e
            LEFT JOIN Utilisateur u ON u.Id_Entreprise = e.Id_Entreprise
            GROUP BY e.Id_Entreprise
            ORDER BY e.Id_Entreprise DESC
            LIMIT 8
        ")->fetchAll();

        $this->render('dashboard/admin', [
            'salutation'            => $this->salutation(),
            'nb_entreprises'        => $nb_entreprises,
            'nb_utilisateurs'       => $nb_utilisateurs,
            'nb_ventes_total'       => $nb_ventes_total,
            'ca_total_plateforme'   => $ca_total_plateforme,
            'entreprises_recentes'  => $entreprises_recentes,
        ], 'Tableau de bord');
    }

    private function showVendeur(int $entreprise_id): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Vente WHERE Id_Entreprise = ? AND DATE(Date_Vente) = CURDATE()");
        $stmt->execute([$entreprise_id]);
        $ventes_jour = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(Montant_Total), 0) FROM Vente WHERE Id_Entreprise = ? AND DATE(Date_Vente) = CURDATE()");
        $stmt->execute([$entreprise_id]);
        $ca_jour = (float) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT Nom_Client) FROM Vente WHERE Id_Entreprise = ?");
        $stmt->execute([$entreprise_id]);
        $nb_clients = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT Id_Vente, Numero_Vente, Nom_Client, Date_Vente, Montant_Total FROM Vente WHERE Id_Entreprise = ? ORDER BY Date_Vente DESC LIMIT 8");
        $stmt->execute([$entreprise_id]);
        $ventes_recentes = $stmt->fetchAll();

        $this->render('dashboard/vendeur', [
            'salutation'       => $this->salutation(),
            'ventes_jour'      => $ventes_jour,
            'ca_jour'          => $ca_jour,
            'nb_clients'       => $nb_clients,
            'ventes_recentes'  => $ventes_recentes,
        ], 'Tableau de bord');
    }

    private function showProprio(int $entreprise_id): void
    {
        // 1. Chiffre d'Affaires (Ventes + B2B Vendu)
        $stmt = $this->pdo->prepare("SELECT SUM(Montant_Total) FROM Vente WHERE Id_Entreprise = ?");
        $stmt->execute([$entreprise_id]);
        $ca_direct = $stmt->fetchColumn() ?? 0;

        $stmt = $this->pdo->prepare("SELECT SUM(Montant_Total) FROM Commande_B2B WHERE Id_Entreprise_Vendeuse = ? AND Statut = 'livree'");
        $stmt->execute([$entreprise_id]);
        $ca_b2b = $stmt->fetchColumn() ?? 0;
        $total_ca = $ca_direct + $ca_b2b;

        // 2. Nombre de ventes
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Vente WHERE Id_Entreprise = ?");
        $stmt->execute([$entreprise_id]);
        $nb_ventes = (int) $stmt->fetchColumn();

        // 3. Produits en alerte stock (stock <= seuil)
        $stmt = $this->pdo->prepare("
            SELECT Nom_Produit, Quantite_En_Stock,
                   COALESCE(Seuil_Alerte_Stock, 5) AS Seuil_Alerte_Stock
            FROM Produit
            WHERE Id_Entreprise = ?
              AND Quantite_En_Stock <= COALESCE(Seuil_Alerte_Stock, 5)
            ORDER BY Quantite_En_Stock ASC
            LIMIT 8
        ");
        $stmt->execute([$entreprise_id]);
        $produits_alerte = $stmt->fetchAll();

        // 4. Commandes B2B en attente (managers seulement)
        $b2b = [];
        if (estProprietaire()) {
            $stmt = $this->pdo->prepare("SELECT c.*, e.Nom_Entreprise FROM Commande_B2B c JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise WHERE c.Id_Entreprise_Vendeuse = ? AND c.Statut = 'en_attente' ORDER BY c.Date_Commande DESC LIMIT 5");
            $stmt->execute([$entreprise_id]);
            $b2b = $stmt->fetchAll();
        }

        // 5. Activité récente — dernières ventes
        $stmt = $this->pdo->prepare("SELECT Id_Vente, Numero_Vente, Nom_Client, Date_Vente, Montant_Total FROM Vente WHERE Id_Entreprise = ? ORDER BY Date_Vente DESC LIMIT 5");
        $stmt->execute([$entreprise_id]);
        $ventes_recentes = $stmt->fetchAll();

        $this->render('dashboard/proprio', [
            'salutation'          => $this->salutation(),
            'total_ca'            => $total_ca,
            'nb_ventes'           => $nb_ventes,
            'produits_alerte'     => $produits_alerte,
            'b2b'                 => $b2b,
            'ventes_recentes'     => $ventes_recentes,
        ], 'Tableau de bord');
    }
}
