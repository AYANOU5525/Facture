<?php

namespace App\Controllers;

/** Contrôleur de pages/sales.php — historique des ventes (paginé). */
class SalesController extends Controller
{
    private const PER_PAGE = 25;

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
        $stmt = $this->pdo->prepare("SELECT SUM(Montant_Total), COUNT(*) FROM Vente WHERE Id_Entreprise = ?");
        $stmt->execute([$entreprise_id]);
        [$total_ca, $total_count] = $stmt->fetch(\PDO::FETCH_NUM);

        $stmt = $this->pdo->prepare("SELECT SUM(Montant_Total) FROM Vente WHERE Id_Entreprise = ? AND DATE(Date_Vente) = CURDATE()");
        $stmt->execute([$entreprise_id]);
        $ca_jour = (float) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT Id_Vente, Numero_Vente, Nom_Client, Nom_Vendeur, Date_Vente, Articles_JSON, Montant_Total, Type_Vente
            FROM Vente
            WHERE Id_Entreprise = ?
            ORDER BY Date_Vente DESC
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
