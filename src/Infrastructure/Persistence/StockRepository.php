<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class StockRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findProductForUpdate(int $productId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit, Nom_Produit, Quantite_En_Stock
             FROM Produit
             WHERE Id_Produit = ? AND Id_Entreprise = ?
             FOR UPDATE'
        );
        $statement->execute([$productId, $enterpriseId]);
        $product = $statement->fetch();

        return $product ?: null;
    }

    public function increase(int $productId, int $enterpriseId, int $quantity): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Produit
             SET Quantite_En_Stock = Quantite_En_Stock + ?
             WHERE Id_Produit = ? AND Id_Entreprise = ?'
        );
        $statement->execute([$quantity, $productId, $enterpriseId]);
    }

    public function createReceivedProduct(array $product, int $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO Produit
                (Nom_Produit, Description_Produit, Prix_Unitaire_Produit, Quantite_En_Stock,
                 En_Destockage_B2B, Prix_B2B, Quantite_Min_B2B, Id_Entreprise)
             VALUES (?, ?, ?, 0, 0, NULL, 1, ?)'
        );
        $statement->execute([
            $product['Nom_Produit'],
            $product['Description_Produit'] ?? null,
            $product['Prix_B2B'] ?? $product['Prix_Unitaire_Produit'] ?? 0,
            $enterpriseId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByNameForUpdate(string $name, int $enterpriseId): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit FROM Produit
             WHERE Id_Entreprise = ? AND Nom_Produit = ?
             ORDER BY Id_Produit ASC LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([$enterpriseId, $name]);
        $productId = $statement->fetchColumn();

        return $productId === false ? null : (int) $productId;
    }
}
