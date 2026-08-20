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

    public function save(array $data, int $enterpriseId, ?int $productId = null): void
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

            return;
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
    }
}
