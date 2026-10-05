<?php

namespace App\Controllers;

/** Contrôleur de pages/sales.php — historique des ventes (paginé). */
class SalesController extends Controller
{
    private const PER_PAGE = 5;

    public function index(): void
    {
        exigerPermission(peutVoirVentes());

        $entreprise_id = $_SESSION['entreprise_id'];

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Vente WHERE Id_Entreprise = ?");
        $stmt->execute([$entreprise_id]);
        $total_ventes = (int) $stmt->fetchColumn();
        $total_pages  = (int) ceil($total_ventes / self::PER_PAGE);

        // Stat globales
        $stmt = $this->pdo->prepare("SELECT SUM(Montant_Total), COUNT(*) FROM Vente WHERE Id_Entreprise = ? AND " . \App\Application\Billing\InvoiceCancellationService::notCancelledSql() . "");
        $stmt->execute([$entreprise_id]);
        [$total_ca, $total_count] = $stmt->fetch(\PDO::FETCH_NUM);

        $stmt = $this->pdo->prepare("SELECT SUM(Montant_Total) FROM Vente WHERE Id_Entreprise = ? AND DATE(Date_Vente) = CURDATE() AND " . \App\Application\Billing\InvoiceCancellationService::notCancelledSql() . "");
        $stmt->execute([$entreprise_id]);
        $ca_jour = (float) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT v.Id_Vente, v.Numero_Vente, v.Nom_Client, v.Nom_Vendeur, v.Date_Vente,
                   " . ReceiptController::ARTICLES_SQL . " AS Articles_JSON, v.Montant_Total, v.Type_Vente,
                   NOT " . \App\Application\Billing\InvoiceCancellationService::notCancelledSql('v') . " AS Est_Annulee
            FROM Vente v
            WHERE v.Id_Entreprise = ?
            ORDER BY v.Date_Vente DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$entreprise_id, self::PER_PAGE, $offset]);
        $ventes = $stmt->fetchAll();

        $this->render('sales/index', [
            'page'          => $page,
            'total_pages'   => $total_pages,
            'total_ventes'  => $total_ventes,
            'total_ca'      => $total_ca,
            'ca_jour'       => $ca_jour,
            'ventes'        => $ventes,
        ], 'Historique des Ventes');
    }
}
