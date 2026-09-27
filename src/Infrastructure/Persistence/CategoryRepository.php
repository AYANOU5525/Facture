<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

/** Catégories de produits (Ligne_Produit + Contenir) — cf. RG1/RG2 du mémoire de soutenance. */
final class CategoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listByEnterprise(int $enterpriseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Ligne_Produit, Libelle FROM Ligne_Produit WHERE Id_Entreprise = ? ORDER BY Libelle'
        );
        $statement->execute([$enterpriseId]);

        return $statement->fetchAll();
    }

    /** @return array<int, int[]> Id_Produit => liste des Id_Ligne_Produit associés */
    public function categoryIdsByProduct(int $enterpriseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT co.Id_Produit, co.Id_Ligne_Produit
             FROM Contenir co
             JOIN Produit p ON p.Id_Produit = co.Id_Produit
             WHERE p.Id_Entreprise = ?'
        );
        $statement->execute([$enterpriseId]);

        $byProduct = [];
        foreach ($statement->fetchAll() as $row) {
            $byProduct[(int) $row['Id_Produit']][] = (int) $row['Id_Ligne_Produit'];
        }

        return $byProduct;
    }

    public function create(string $libelle, int $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO Ligne_Produit (Id_Entreprise, Libelle) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE Id_Ligne_Produit = LAST_INSERT_ID(Id_Ligne_Produit)'
        );
        $statement->execute([$enterpriseId, $libelle]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $categoryId, int $enterpriseId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM Ligne_Produit WHERE Id_Ligne_Produit = ? AND Id_Entreprise = ?'
        );
        $statement->execute([$categoryId, $enterpriseId]);
    }

    /** Remplace entièrement les catégories d'un produit par la liste fournie (vide = aucune catégorie). */
    public function assignToProduct(int $productId, array $categoryIds, int $enterpriseId): void
    {
        $owned = $this->listByEnterprise($enterpriseId);
        $validIds = array_column($owned, 'Id_Ligne_Produit');
        $categoryIds = array_values(array_intersect(array_map('intval', $categoryIds), $validIds));

        $delete = $this->pdo->prepare('DELETE FROM Contenir WHERE Id_Produit = ?');
        $delete->execute([$productId]);

        if ($categoryIds === []) {
            return;
        }

        $insert = $this->pdo->prepare('INSERT INTO Contenir (Id_Produit, Id_Ligne_Produit) VALUES (?, ?)');
        foreach ($categoryIds as $categoryId) {
            $insert->execute([$productId, $categoryId]);
        }
    }
}
