<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class SalesWorkflowRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findSale(string $number, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT v.*, f.Id_Facture, f.Statut_Paiement, f.Montant_TTC
             FROM Vente v
             LEFT JOIN Facture f ON v.Id_Vente = f.Id_Vente
             WHERE v.Numero_Vente = ? AND v.Id_Entreprise = ?'
        );
        $statement->execute([$number, $enterpriseId]);
        $sale = $statement->fetch();

        return $sale ?: null;
    }

    public function hasLogistics(int $saleId): ?array
    {
        $statement = $this->pdo->prepare('SELECT Id_Logistique FROM Logistique WHERE Id_Vente = ?');
        $statement->execute([$saleId]);
        $logistics = $statement->fetch();

        return $logistics ?: null;
    }

    public function markPaid(int $invoiceId): void
    {
        $statement = $this->pdo->prepare("UPDATE Facture SET Statut_Paiement = 'payee' WHERE Id_Facture = ?");
        $statement->execute([$invoiceId]);
    }

    public function createLogistics(
        int $saleId,
        int $enterpriseId,
        string $carrier,
        string $trackingNumber,
        ?string $deliveryDate
    ): void {
        $statement = $this->pdo->prepare(
            "INSERT INTO Logistique
                (Id_Vente, Id_Entreprise, Transporteur, Numero_Suivi, Date_Livraison_Prevue, Statut_Livraison)
             VALUES (?, ?, ?, ?, ?, 'traitement')"
        );
        $statement->execute([$saleId, $enterpriseId, $carrier, $trackingNumber, $deliveryDate]);
    }
}
