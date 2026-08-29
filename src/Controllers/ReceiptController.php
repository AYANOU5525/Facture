<?php

namespace App\Controllers;

/** Contrôleur de pages/invoice_view.php — reçu/facture imprimable, page autonome (pas de nav). */
class ReceiptController extends Controller
{
    public function show(): void
    {
        exigerPermission(peutVoirFactures());

        if (!isset($_GET['ref'])) {
            die("Référence Facture manquante.");
        }

        $ref_vente = $_GET['ref'];
        $user_id = $_SESSION['user_id'];
        $my_entreprise_id = $_SESSION['entreprise_id'] ?? null;

        // Si session entreprise_id pas dispo, on le cherche
        if (!$my_entreprise_id) {
            $stmt = $this->pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
            $stmt->execute([$user_id]);
            $my_entreprise_id = $stmt->fetchColumn();
        }

        // 1. Récupérer la vente par Numero_Vente
        $stmt = $this->pdo->prepare("
            SELECT v.*, e.Nom_Entreprise, e.Adresse_Entreprise, e.Tel_Entreprise, e.Email_Entreprise, e.NIF_Entreprise
            FROM Vente v
            JOIN Entreprise e ON v.Id_Entreprise = e.Id_Entreprise
            WHERE v.Numero_Vente = ? AND v.Id_Entreprise = ?
        ");
        $stmt->execute([$ref_vente, $my_entreprise_id]);
        $vente = $stmt->fetch();

        if (!$vente) {
            die("Facture introuvable ou accès refusé.");
        }

        $articles = json_decode($vente['Articles_JSON'], true);

        // Calcul de la date de conservation légale (10 ans à compter de la date de facture)
        $date_conservation = new \DateTime($vente['Date_Vente']);
        $date_conservation->modify('+10 years');
        $label_conservation = $date_conservation->format('d/m/Y');

        $this->renderStandalone('invoice_view/index', [
            'vente'               => $vente,
            'articles'            => $articles,
            'label_conservation'  => $label_conservation,
        ]);
    }
}
