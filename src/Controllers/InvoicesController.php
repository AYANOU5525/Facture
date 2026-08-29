<?php

namespace App\Controllers;

/** Contrôleur de pages/invoices.php — suivi des factures (paiement, conservation légale). */
class InvoicesController extends Controller
{
    public function index(): void
    {
        exigerPermission(peutVoirFactures());

        $stmt = $this->pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $entreprise_id = $stmt->fetchColumn();

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

        // KPIs
        $total_payees     = count(array_filter($factures, fn($f) => $f['Statut_Paiement'] === 'payee'));
        $total_non_payees = count(array_filter($factures, fn($f) => $f['Statut_Paiement'] === 'non_payee'));
        $total_annulees   = count(array_filter($factures, fn($f) => $f['Statut_Paiement'] === 'annulee'));
        $ca_paye          = array_sum(array_map(fn($f) => $f['Statut_Paiement'] === 'payee' ? $f['Montant_TTC'] : 0, $factures));
        $ca_impaye        = array_sum(array_map(fn($f) => $f['Statut_Paiement'] === 'non_payee' ? $f['Montant_TTC'] : 0, $factures));

        $this->render('invoices/index', [
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
        $id_facture = $_POST['id_facture'];
        $nouveau_statut = $_POST['nouveau_statut'];

        $stmt = $this->pdo->prepare("SELECT Id_Facture, Date_Facture FROM Facture WHERE Id_Facture = ? AND Id_Entreprise = ?");
        $stmt->execute([$id_facture, $entreprise_id]);
        $facture_a_modifier = $stmt->fetch();

        if (!$facture_a_modifier) {
            return ['', 'Facture introuvable.'];
        }

        $date_conservation = new \DateTime($facture_a_modifier['Date_Facture']);
        $date_conservation->modify('+10 years');
        $en_retention = $date_conservation > new \DateTime();

        if ($nouveau_statut === 'annulee' && $en_retention) {
            $annee_archivage = $date_conservation->format('d/m/Y');
            return ['', "❌ Impossible d'annuler cette facture : elle doit être conservée jusqu'au <strong>$annee_archivage</strong> (obligation légale de 10 ans)."];
        }

        $stmt = $this->pdo->prepare("UPDATE Facture SET Statut_Paiement = ? WHERE Id_Facture = ?");
        $stmt->execute([$nouveau_statut, $id_facture]);

        return ['Statut mis à jour avec succès.', ''];
    }
}
