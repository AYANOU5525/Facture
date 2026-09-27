<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class ProductRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByIdAndEnterprise(int $productId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit, Nom_Produit, Description_Produit, Prix_Unitaire_Produit,
                    Quantite_En_Stock, En_Destockage_B2B, Prix_B2B,
                    Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton,
                    COALESCE(Seuil_Alerte_Stock, 5) AS Seuil_Alerte_Stock
             FROM Produit
             WHERE Id_Produit = ? AND Id_Entreprise = ?'
        );
        $statement->execute([$productId, $enterpriseId]);
        $product = $statement->fetch();

        return $product ?: null;
    }

    public function findAllByEnterprise(int $enterpriseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit, Nom_Produit, Description_Produit, Prix_Unitaire_Produit,
                    Quantite_En_Stock, En_Destockage_B2B, Prix_B2B,
                    Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton,
                    COALESCE(Seuil_Alerte_Stock, 5) AS Seuil_Alerte_Stock
             FROM Produit
             WHERE Id_Entreprise = ?
             ORDER BY Nom_Produit'
        );
        $statement->execute([$enterpriseId]);

        return $statement->fetchAll();
    }

    public function delete(int $productId, int $enterpriseId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM Produit WHERE Id_Produit = ? AND Id_Entreprise = ?');
        $statement->execute([$productId, $enterpriseId]);
    }

    /**
     * Cherche, dans la même entreprise, un AUTRE produit qui utilise déjà l'un des codes-barres
     * fournis — sur n'importe laquelle des deux colonnes (un code carton d'un produit ne doit
     * pas non plus coïncider avec le code unité d'un autre, sous peine de scan ambigu).
     */
    public function findConflictingProduct(
        ?string $codeBarreUnite,
        ?string $codeBarreCarton,
        int $enterpriseId,
        ?int $excludeProductId = null
    ): ?array {
        $codes = array_values(array_unique(array_filter([$codeBarreUnite, $codeBarreCarton], fn ($c) => $c !== null && $c !== '')));
        if ($codes === []) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $sql = "SELECT Id_Produit, Nom_Produit FROM Produit
                WHERE Id_Entreprise = ?
                  AND (Code_Barre_Unite IN ($placeholders) OR Code_Barre_Carton IN ($placeholders))";
        $params = array_merge([$enterpriseId], $codes, $codes);

        if ($excludeProductId !== null) {
            $sql .= ' AND Id_Produit != ?';
            $params[] = $excludeProductId;
        }

        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $conflict = $statement->fetch();

        return $conflict ?: null;
    }

    /**
     * Bascule le déstockage B2B sans toucher aux autres champs du produit.
     * Si on l'active et qu'aucun prix B2B n'a jamais été défini, on reprend le prix
     * unitaire courant comme valeur de départ — l'entreprise peut l'affiner ensuite
     * depuis la modale, mais le produit n'apparaît jamais à 0 F sur le réseau B2B.
     */
    public function toggleDestockage(int $productId, int $enterpriseId, bool $enabled): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Produit
                SET En_Destockage_B2B = ?,
                    Prix_B2B = CASE WHEN ? = 1 AND Prix_B2B IS NULL THEN Prix_Unitaire_Produit ELSE Prix_B2B END
              WHERE Id_Produit = ? AND Id_Entreprise = ?'
        );
        $statement->execute([$enabled ? 1 : 0, $enabled ? 1 : 0, $productId, $enterpriseId]);
    }

    /** Renseigne un seul des deux codes-barres (association a posteriori) sans toucher au reste de la fiche. */
    public function associateBarcode(int $productId, int $enterpriseId, string $column, string $code): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE Produit SET {$column} = ? WHERE Id_Produit = ? AND Id_Entreprise = ?"
        );
        $statement->execute([$code, $productId, $enterpriseId]);
    }

    public function save(array $data, int $enterpriseId, ?int $productId = null): int
    {
        if ($productId !== null) {
            $statement = $this->pdo->prepare(
                'UPDATE Produit SET
                    Nom_Produit = ?, Description_Produit = ?, Prix_Unitaire_Produit = ?,
                    Quantite_En_Stock = ?, Seuil_Alerte_Stock = ?, En_Destockage_B2B = ?,
                    Prix_B2B = ?, Quantite_Min_B2B = ?,
                    Code_Barre_Unite = ?, Code_Barre_Carton = ?, Quantite_Par_Carton = ?
                 WHERE Id_Produit = ? AND Id_Entreprise = ?'
            );
            $statement->execute([
                $data['nom'], $data['description'], $data['prix'], $data['stock'],
                $data['seuil_alerte'] ?? 5,
                $data['en_destockage'], $data['prix_b2b'], $data['qte_min_b2b'],
                $data['code_barre_unite'], $data['code_barre_carton'], $data['quantite_par_carton'],
                $productId, $enterpriseId,
            ]);

            return $productId;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO Produit
                (Nom_Produit, Description_Produit, Prix_Unitaire_Produit, Quantite_En_Stock,
                 Seuil_Alerte_Stock, En_Destockage_B2B, Prix_B2B, Quantite_Min_B2B,
                 Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton, Id_Entreprise)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $data['nom'], $data['description'], $data['prix'], $data['stock'],
            $data['seuil_alerte'] ?? 5,
            $data['en_destockage'], $data['prix_b2b'], $data['qte_min_b2b'],
            $data['code_barre_unite'], $data['code_barre_carton'], $data['quantite_par_carton'],
            $enterpriseId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
