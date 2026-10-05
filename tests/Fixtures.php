<?php

declare(strict_types=1);

namespace Tests;

use PDO;

/**
 * Fabriques de données pour la base de test isolée (cf. tests/bootstrap.php). Chaque
 * appel crée des lignes neuves aux noms uniques : les tests ne dépendent d'aucun jeu de
 * démonstration et ne se gênent pas entre eux.
 */
final class Fixtures
{
    public function __construct(private PDO $pdo)
    {
    }

    public function enterprise(string $name = 'Entreprise test'): int
    {
        $this->pdo->prepare('INSERT INTO Entreprise (Nom_Entreprise) VALUES (?)')->execute([$name . ' ' . uniqid()]);

        return (int) $this->pdo->lastInsertId();
    }

    public function product(int $enterpriseId, array $values = []): int
    {
        $row = array_merge([
            'Nom_Produit' => 'Produit ' . uniqid(),
            'Prix_Unitaire_Produit' => 1000,
            'Quantite_En_Stock' => 50,
            'Code_Barre_Unite' => null,
            'Code_Barre_Carton' => null,
            'Quantite_Par_Carton' => 1,
            'En_Destockage_B2B' => 0,
            'Prix_B2B' => null,
            'Quantite_Min_B2B' => 1,
        ], $values, ['Id_Entreprise' => $enterpriseId]);

        $columns = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $this->pdo->prepare("INSERT INTO Produit ($columns) VALUES ($placeholders)")->execute(array_values($row));

        return (int) $this->pdo->lastInsertId();
    }

    public function stock(int $productId): int
    {
        return (int) $this->pdo->query('SELECT Quantite_En_Stock FROM Produit WHERE Id_Produit = ' . $productId)->fetchColumn();
    }

    public function logistics(int $enterpriseId, ?int $saleId = null, string $status = 'traitement'): int
    {
        $this->pdo->prepare('INSERT INTO Logistique (Id_Vente, Statut_Livraison, Id_Entreprise) VALUES (?, ?, ?)')
            ->execute([$saleId, $status, $enterpriseId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Commande B2B minimale (une ligne), directement au statut voulu. */
    public function order(int $buyerId, int $sellerId, string $status = 'en_attente', array $values = []): int
    {
        $row = array_merge([
            'Numero_Commande' => 'CMD-TEST-' . uniqid(),
            'Id_Entreprise_Acheteuse' => $buyerId,
            'Id_Entreprise_Vendeuse' => $sellerId,
            'Montant_Total' => 0,
            'Statut' => $status,
            'Est_Urgente' => 0,
            'Date_Limite_Reponse' => null,
        ], $values);

        $columns = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $this->pdo->prepare("INSERT INTO Commande_B2B ($columns) VALUES ($placeholders)")->execute(array_values($row));

        return (int) $this->pdo->lastInsertId();
    }

    /** Ligne de commande B2B (le trigger recalcule Commande_B2B.Montant_Total). */
    public function orderLine(int $orderId, int $productId, int $quantity, float $unitPrice): int
    {
        $name = (string) $this->pdo->query('SELECT Nom_Produit FROM Produit WHERE Id_Produit = ' . $productId)->fetchColumn();
        $this->pdo->prepare(
            'INSERT INTO Ligne_Commande_B2B (Id_Commande_B2B, Id_Produit, Nom_Produit, Quantite, Prix_Unitaire, Sous_Total)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$orderId, $productId, $name, $quantity, $unitPrice, $quantity * $unitPrice]);

        return (int) $this->pdo->lastInsertId();
    }

    public function orderStatus(int $orderId): string
    {
        return (string) $this->pdo->query('SELECT Statut FROM Commande_B2B WHERE Id_Commande_B2B = ' . $orderId)->fetchColumn();
    }
}
