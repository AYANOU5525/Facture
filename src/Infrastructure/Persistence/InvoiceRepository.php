<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class InvoiceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function availableProducts(int $enterpriseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock
             FROM Produit
             WHERE Id_Entreprise = ? AND Quantite_En_Stock > 0
             ORDER BY Nom_Produit'
        );
        $statement->execute([$enterpriseId]);

        return $statement->fetchAll();
    }

    public function lockProduct(int $productId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit, Nom_Produit, Prix_Unitaire_Produit, Quantite_En_Stock
             FROM Produit
             WHERE Id_Produit = ? AND Id_Entreprise = ?
             FOR UPDATE'
        );
        $statement->execute([$productId, $enterpriseId]);
        $product = $statement->fetch();

        return $product ?: null;
    }

    public function decreaseStock(int $productId, int $quantity): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Produit SET Quantite_En_Stock = Quantite_En_Stock - ? WHERE Id_Produit = ?'
        );
        $statement->execute([$quantity, $productId]);
    }

    public function createSale(array $sale): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO Vente
                (Numero_Vente, Nom_Client, Nom_Vendeur, Articles_JSON, Montant_Total, Type_Vente, Id_Entreprise, Date_Vente)
             VALUES (?, ?, ?, ?, ?, 'directe', ?, NOW())"
        );
        $statement->execute([
            $sale['number'], $sale['client'], $sale['seller'], $sale['articles'],
            $sale['total'], $sale['enterprise_id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createInvoice(int $saleId, string $number, float $total, int $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO Facture
                (Id_Vente, Numero_Facture, Date_Echeance, Montant_HT, Montant_TTC, Date_Archivage, Id_Entreprise)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, DATE_ADD(NOW(), INTERVAL 10 YEAR), ?)'
        );
        $statement->execute([$saleId, $number, $total * 0.8, $total, $enterpriseId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createLogistics(int $saleId, int $invoiceId, int $enterpriseId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO Logistique (Id_Vente, Id_Facture, Statut_Livraison, Id_Entreprise)
             VALUES (?, ?, 'traitement', ?)"
        );
        $statement->execute([$saleId, $invoiceId, $enterpriseId]);
    }
}
