<?php

namespace App\Controllers;

/** Contrôleur de pages/invoices.php — suivi des factures (paiement, conservation légale). */
class InvoicesController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutVoirFactures());

        $entreprise_id = $_SESSION['entreprise_id'];

        $success = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
            [$success, $error] = $this->handleUpdateStatus($entreprise_id);
        }

        $stmt = $this->pdo->prepare("
            SELECT f.*, v.Nom_Client, v.Numero_Vente,
                   DATE_ADD(f.Date_Facture, INTERVAL 10 YEAR) AS Date_Conservation,
                   CASE WHEN DATE_ADD(f.Date_Facture, INTERVAL 10 YEAR) > NOW() THEN 1 ELSE 0 END AS En_Retention
            FROM Facture f
            LEFT JOIN Vente v ON f.Id_Vente = v.Id_Vente
            WHERE f.Id_Entreprise = ?
            ORDER BY f.Date_Facture DESC
        ");
        $stmt->execute([$entreprise_id]);
        $factures = $stmt->fetchAll();

        // KPIs calculés en une seule requête d'agrégation (plutôt que 5 array_filter/array_map
        // PHP sur la liste complète) — profite de l'index idx_facture_ent_statut.
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(CASE WHEN Statut_Paiement = 'payee' THEN 1 ELSE 0 END) AS total_payees,
                SUM(CASE WHEN Statut_Paiement = 'non_payee' THEN 1 ELSE 0 END) AS total_non_payees,
                SUM(CASE WHEN Statut_Paiement = 'annulee' THEN 1 ELSE 0 END) AS total_annulees,
                SUM(CASE WHEN Statut_Paiement = 'payee' THEN Montant_TTC ELSE 0 END) AS ca_paye,
                SUM(CASE WHEN Statut_Paiement = 'non_payee' THEN Montant_TTC ELSE 0 END) AS ca_impaye
            FROM Facture
            WHERE Id_Entreprise = ?
        ");
        $stmt->execute([$entreprise_id]);
        $kpi = $stmt->fetch();
        $total_payees     = (int) $kpi['total_payees'];
        $total_non_payees = (int) $kpi['total_non_payees'];
        $total_annulees   = (int) $kpi['total_annulees'];
        $ca_paye          = (float) $kpi['ca_paye'];
        $ca_impaye        = (float) $kpi['ca_impaye'];

        // Factures d'achat : factures B2B émises par nos fournisseurs pour nos commandes.
        // Lecture seule (le statut de paiement est tenu par le fournisseur qui a émis la facture).
        $factures_achat = [];
        if (peutGererB2B()) {
            $stmt = $this->pdo->prepare("
                SELECT f.Numero_Facture, f.Date_Facture, f.Date_Echeance, f.Montant_HT, f.TVA, f.Montant_TTC,
                       f.Statut_Paiement, c.Numero_Commande, c.Statut AS Statut_Commande,
                       e.Nom_Entreprise AS Fournisseur
                FROM Facture f
                JOIN Commande_B2B c ON c.Id_Commande_B2B = f.Id_Commande_B2B
                JOIN Entreprise e ON e.Id_Entreprise = f.Id_Entreprise
                WHERE c.Id_Entreprise_Acheteuse = ?
                ORDER BY f.Date_Facture DESC
            ");
            $stmt->execute([$entreprise_id]);
            $factures_achat = $stmt->fetchAll();
        }

        $this->render('invoices/index', [
            'factures_achat'    => $factures_achat,
            'success'           => $success,
            'error'             => $error,
            'factures'          => $factures,
            'total_payees'      => $total_payees,
            'total_non_payees'  => $total_non_payees,
            'total_annulees'    => $total_annulees,
            'ca_paye'           => $ca_paye,
            'ca_impaye'         => $ca_impaye,
        ], 'Suivi des Factures');
    }

    /** @return array{0:string,1:string} [$success, $error] */
    private function handleUpdateStatus($entreprise_id): array
    {
        exigerCsrf();
        $id_facture = (int) ($_POST['id_facture'] ?? 0);
        $nouveau_statut = $_POST['nouveau_statut'] ?? '';
        if (!in_array($nouveau_statut, ['payee', 'non_payee', 'annulee'], true)) {
            return ['', 'Statut de facture invalide.'];
        }

        // Annulation : facture conservée (obligation légale de 10 ans), stock réintégré.
        if ($nouveau_statut === 'annulee') {
            try {
                $unites = (new \App\Application\Billing\InvoiceCancellationService($this->pdo))->cancel($id_facture, (int) $entreprise_id);
                $this->audit((int) ($_SESSION['user_id'] ?? 0), (int) $entreprise_id, 'invoice_cancelled', 'Facture', $id_facture, "$unites unité(s) remise(s) en stock");
                return ["Facture annulée : elle reste archivée 10 ans, et $unites unité(s) ont été remises en stock.", ''];
            } catch (\InvalidArgumentException | \RuntimeException $e) {
                return ['', $e->getMessage()];
            }
        }

        $stmt = $this->pdo->prepare("SELECT Statut_Paiement FROM Facture WHERE Id_Facture = ? AND Id_Entreprise = ?");
        $stmt->execute([$id_facture, $entreprise_id]);
        $statut_actuel = $stmt->fetchColumn();

        if ($statut_actuel === false) {
            return ['', 'Facture introuvable.'];
        }
        if ($statut_actuel === 'annulee') {
            return ['', "Cette facture est annulée : son statut ne peut plus être modifié."];
        }

        $stmt = $this->pdo->prepare("UPDATE Facture SET Statut_Paiement = ? WHERE Id_Facture = ? AND Id_Entreprise = ?");
        $stmt->execute([$nouveau_statut, $id_facture, $entreprise_id]);

        return ['Statut mis à jour avec succès.', ''];
    }
}
