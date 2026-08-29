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
            'SELECT Id_Produit, Nom_Produit, Quantite_En_Stock, Quantite_Par_Carton
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
                 En_Destockage_B2B, Prix_B2B, Quantite_Min_B2B,
                 Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton, Id_Entreprise)
             VALUES (?, ?, ?, 0, 0, NULL, 1, ?, ?, ?, ?)'
        );
        $statement->execute([
            $product['Nom_Produit'],
            $product['Description_Produit'] ?? null,
            $product['Prix_B2B'] ?? $product['Prix_Unitaire_Produit'] ?? 0,
            $product['Code_Barre_Unite'] ?? null,
            $product['Code_Barre_Carton'] ?? null,
            max(1, (int) ($product['Quantite_Par_Carton'] ?? 1)),
            $enterpriseId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Bascule le déstockage B2B sans toucher aux autres champs du produit.
     * $prixB2B / $qteMinB2B, si fournis, priment sur les valeurs déjà en base (saisie
     * explicite de l'entreprise). Sinon, si on active et qu'aucun prix B2B n'a jamais
     * été défini, on reprend le prix unitaire courant comme valeur de départ — le
     * produit n'apparaît jamais à 0 F sur le réseau B2B.
     */
    public function toggleDestockage(
        int $productId,
        int $enterpriseId,
        bool $enabled,
        ?float $prixB2B = null,
        ?int $qteMinB2B = null
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE Produit
                SET En_Destockage_B2B = ?,
                    Prix_B2B = CASE
                        WHEN ? = 1 AND ? IS NOT NULL THEN ?
                        WHEN ? = 1 AND Prix_B2B IS NULL THEN Prix_Unitaire_Produit
                        ELSE Prix_B2B
                    END,
                    Quantite_Min_B2B = CASE WHEN ? = 1 AND ? IS NOT NULL THEN ? ELSE Quantite_Min_B2B END
              WHERE Id_Produit = ? AND Id_Entreprise = ?'
        );
        $enabledFlag = $enabled ? 1 : 0;
        $statement->execute([
            $enabledFlag,
            $enabledFlag, $prixB2B, $prixB2B,
            $enabledFlag,
            $enabledFlag, $qteMinB2B, $qteMinB2B,
            $productId, $enterpriseId,
        ]);
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

    /** Retrouve un produit du même code-barre déjà présent chez l'acheteur (même article, catalogue différent). */
    public function findByBarcodeForUpdate(string $barcodeUnite, ?string $barcodeCarton, int $enterpriseId): ?int
    {
        if ($barcodeUnite === '' && ($barcodeCarton === null || $barcodeCarton === '')) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT Id_Produit FROM Produit
             WHERE Id_Entreprise = ?
               AND ((Code_Barre_Unite IS NOT NULL AND Code_Barre_Unite IN (?, ?))
                 OR (Code_Barre_Carton IS NOT NULL AND Code_Barre_Carton IN (?, ?)))
             ORDER BY Id_Produit ASC LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            $enterpriseId,
            $barcodeUnite, $barcodeCarton,
            $barcodeUnite, $barcodeCarton,
        ]);
        $productId = $statement->fetchColumn();

        return $productId === false ? null : (int) $productId;
    }
}
