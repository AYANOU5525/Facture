<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class OrderRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findForSeller(int $orderId, int $enterpriseId, string $status): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM Commande_B2B
             WHERE Id_Commande_B2B = ? AND Id_Entreprise_Vendeuse = ? AND Statut = ?
             FOR UPDATE'
        );
        $statement->execute([$orderId, $enterpriseId, $status]);
        $order = $statement->fetch();

        return $order ?: null;
    }

    public function transition(int $orderId, string $newStatus, ?string $message = null): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Commande_B2B
             SET Statut = ?, Message_Validation = COALESCE(?, Message_Validation)
             WHERE Id_Commande_B2B = ?'
        );
        $statement->execute([$newStatus, $message, $orderId]);
    }

    public function recordHistory(
        int $orderId,
        string $oldStatus,
        string $newStatus,
        ?string $note,
        int $enterpriseId
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO Historique_Commande_B2B
                (Id_Commande_B2B, Ancien_Statut, Nouveau_Statut, Note, Id_Entreprise_Action)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$orderId, $oldStatus, $newStatus, $note, $enterpriseId]);
    }

    public function findB2BProductForUpdate(int $productId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit, Nom_Produit, Prix_B2B, Quantite_En_Stock
             FROM Produit
             WHERE Id_Produit = ? AND Id_Entreprise = ? AND En_Destockage_B2B = 1
             FOR UPDATE'
        );
        $statement->execute([$productId, $enterpriseId]);
        $product = $statement->fetch();

        return $product ?: null;
    }

    public function create(array $order): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO Commande_B2B
                (Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse,
                 Montant_Total, Statut, Est_Urgente, Delai_Reponse_Minutes,
                 Date_Limite_Reponse, Mode_Retrait, Adresse_Retrait, Date_Commande)
             VALUES (?, ?, ?, ?, \'en_attente\', ?, ?, ?, ?, ?, NOW())'
        );
        $statement->execute([
            $order['number'], $order['buyer_id'], $order['seller_id'], $order['total'],
            $order['urgent'], $order['deadline_minutes'], $order['deadline'],
            $order['mode'], $order['pickup_address'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createLine(int $orderId, array $line): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO Ligne_Commande_B2B
                (Id_Commande_B2B, Id_Produit, Nom_Produit, Quantite, Prix_Unitaire, Sous_Total)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $orderId, $line['product_id'], $line['name'], $line['quantity'],
            $line['unit_price'], $line['subtotal'],
        ]);
    }

    public function findReadyForShipment(int $orderId, int $sellerId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT c.*, e.Nom_Entreprise AS Nom_Acheteur
             FROM Commande_B2B c
             JOIN Entreprise e ON c.Id_Entreprise_Acheteuse = e.Id_Entreprise
             WHERE c.Id_Commande_B2B = ? AND c.Id_Entreprise_Vendeuse = ?
               AND c.Statut IN ('prete', 'validee', 'en_preparation')
             FOR UPDATE"
        );
        $statement->execute([$orderId, $sellerId]);
        $order = $statement->fetch();

        return $order ?: null;
    }

    public function findLines(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit AS id_produit, Nom_Produit AS nom,
                    Quantite AS quantite, Prix_Unitaire AS prix, Sous_Total AS sous_total
             FROM Ligne_Commande_B2B WHERE Id_Commande_B2B = ? ORDER BY Id_Ligne ASC'
        );
        $statement->execute([$orderId]);

        return $statement->fetchAll();
    }

    public function createB2BSale(array $sale): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO Vente
                (Numero_Vente, Id_Entreprise, Nom_Client, Date_Vente, Montant_Total, Type_Vente, Articles_JSON)
             VALUES (?, ?, ?, NOW(), ?, 'b2b', ?)"
        );
        $statement->execute([
            $sale['number'], $sale['enterprise_id'], $sale['client'], $sale['total'], $sale['articles'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createB2BInvoice(int $saleId, int $orderId, string $number, float $total, int $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO Facture
                (Id_Vente, Id_Commande_B2B, Numero_Facture, Date_Echeance, Montant_HT, Montant_TTC, Id_Entreprise)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, ?)'
        );
        $statement->execute([$saleId, $orderId, $number, $total * 0.8, $total, $enterpriseId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createB2BLogistics(int $saleId, int $orderId, int $invoiceId, int $enterpriseId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO Logistique
                (Id_Vente, Id_Commande_B2B, Id_Facture, Statut_Livraison, Id_Entreprise)
             VALUES (?, ?, ?, 'traitement', ?)"
        );
        $statement->execute([$saleId, $orderId, $invoiceId, $enterpriseId]);
    }

    public function markShipped(int $orderId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE Commande_B2B SET Statut = 'expediee', Date_Expedition_Reelle = NOW()
             WHERE Id_Commande_B2B = ?"
        );
        $statement->execute([$orderId]);
    }
}
