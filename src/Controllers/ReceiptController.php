<?php

namespace App\Controllers;

/** Contrôleur de pages/invoice_view.php — reçu/facture imprimable, page autonome (pas de nav). */
class ReceiptController extends Controller
{
    /**
     * Articles d'une vente (alias v) : Articles_JSON, ou à défaut reconstruit depuis les lignes
     * relationnelles Ligne_Vente (ventes insérées sans JSON, ex. database/seed_test_data.php).
     */
    public const ARTICLES_SQL = "COALESCE(v.Articles_JSON, (
            SELECT JSON_ARRAYAGG(JSON_OBJECT(
                'id_produit', lv.Id_Produit, 'nom', lv.Nom_Produit, 'quantite', lv.Quantite,
                'prix', lv.Prix_Unitaire, 'sous_total', lv.Quantite * lv.Prix_Unitaire))
            FROM Ligne_Vente lv WHERE lv.Id_Vente = v.Id_Vente))";

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
            SELECT v.*, e.Nom_Entreprise, e.Adresse_Entreprise, e.Tel_Entreprise, e.Email_Entreprise, e.NIF_Entreprise,
                   " . self::ARTICLES_SQL . " AS Articles_JSON,
                   f.Montant_HT, f.TVA, f.Montant_TTC, f.Statut_Paiement
            FROM Vente v
            JOIN Entreprise e ON v.Id_Entreprise = e.Id_Entreprise
            LEFT JOIN Facture f ON f.Id_Vente = v.Id_Vente
            WHERE v.Numero_Vente = ?
              AND (v.Id_Entreprise = ?
                   -- Facture B2B : aussi consultable par l'entreprise ACHETEUSE (pour sa comptabilité)
                   OR EXISTS (SELECT 1 FROM Commande_B2B c
                              WHERE c.Id_Commande_B2B = f.Id_Commande_B2B AND c.Id_Entreprise_Acheteuse = ?))
        ");
        $stmt->execute([$ref_vente, $my_entreprise_id, $my_entreprise_id]);
        $vente = $stmt->fetch();

        if (!$vente) {
            die("Facture introuvable ou accès refusé.");
        }

        $articles = json_decode($vente['Articles_JSON'], true);

        // Calcul de la date de conservation légale (10 ans à compter de la date de facture)
        $date_conservation = new \DateTime($vente['Date_Vente']);
        $date_conservation->modify('+10 years');
        $label_conservation = $date_conservation->format('d/m/Y');

        // Ventilation HT / TVA : valeurs enregistrées sur la facture, recalculées depuis le TTC
        // pour une vente sans facture (ne devrait pas arriver, filet de sécurité).
        $ttc = (float) ($vente['Montant_TTC'] ?? $vente['Montant_Total']);
        $vat = $vente['Montant_HT'] !== null
            ? ['ht' => (float) $vente['Montant_HT'], 'tva' => (float) $vente['TVA']]
            : \App\Application\Billing\Vat::fromTtc($ttc);

        $this->renderStandalone('invoice_view/index', [
            'vente'               => $vente,
            'montant_ht'          => $vat['ht'],
            'montant_tva'         => $vat['tva'],
            'montant_ttc'         => $ttc,
            'taux_tva'            => \App\Application\Billing\Vat::label(),
            'est_annulee'         => ($vente['Statut_Paiement'] ?? '') === 'annulee',
            'articles'            => $articles,
            'label_conservation'  => $label_conservation,
        ]);
    }
}
