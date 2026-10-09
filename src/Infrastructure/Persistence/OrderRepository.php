<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Billing\Vat;
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

    /** Commande quelconque, verrouillée (l'appelant a déjà vérifié l'entreprise par ailleurs). */
    public function findForUpdate(int $orderId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM Commande_B2B WHERE Id_Commande_B2B = ? FOR UPDATE');
        $statement->execute([$orderId]);
        $order = $statement->fetch();

        return $order ?: null;
    }

    /** Commande de l'ACHETEUR dans l'un des statuts donnés (verrouillée). */
    public function findForBuyer(int $orderId, int $buyerId, array $statuses): ?array
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $statement = $this->pdo->prepare(
            "SELECT * FROM Commande_B2B
             WHERE Id_Commande_B2B = ? AND Id_Entreprise_Acheteuse = ? AND Statut IN ($placeholders)
             FOR UPDATE"
        );
        $statement->execute([$orderId, $buyerId, ...$statuses]);
        $order = $statement->fetch();

        return $order ?: null;
    }

    /** Lignes d'une commande avec le stock actuel du produit vendeur (lignes et produits verrouillés). */
    public function findLinesWithStockForUpdate(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT l.Id_Ligne, l.Id_Produit, l.Nom_Produit, l.Quantite, l.Quantite_Proposee,
                    l.Prix_Unitaire, p.Quantite_En_Stock
             FROM Ligne_Commande_B2B l
             LEFT JOIN Produit p ON p.Id_Produit = l.Id_Produit
             WHERE l.Id_Commande_B2B = ?
             ORDER BY l.Id_Ligne
             FOR UPDATE'
        );
        $statement->execute([$orderId]);

        return $statement->fetchAll();
    }

    public function setProposedQuantity(int $lineId, ?int $quantity): void
    {
        $this->pdo->prepare('UPDATE Ligne_Commande_B2B SET Quantite_Proposee = ? WHERE Id_Ligne = ?')
            ->execute([$quantity, $lineId]);
    }

    /** Remplace les quantités commandées par la proposition acceptée (lignes à 0 supprimées). */
    public function applyProposal(int $orderId): void
    {
        $this->pdo->prepare('DELETE FROM Ligne_Commande_B2B WHERE Id_Commande_B2B = ? AND Quantite_Proposee = 0')
            ->execute([$orderId]);
        // Les triggers trg_ligne_cmd_b2b_* recalculent Commande_B2B.Montant_Total.
        $this->pdo->prepare(
            'UPDATE Ligne_Commande_B2B
             SET Quantite = Quantite_Proposee, Sous_Total = Quantite_Proposee * Prix_Unitaire, Quantite_Proposee = NULL
             WHERE Id_Commande_B2B = ? AND Quantite_Proposee IS NOT NULL'
        )->execute([$orderId]);
    }

    public function adjustStock(int $productId, int $delta): void
    {
        $this->pdo->prepare('UPDATE Produit SET Quantite_En_Stock = Quantite_En_Stock + ? WHERE Id_Produit = ?')
            ->execute([$delta, $productId]);
    }

    public function markValidated(int $orderId): void
    {
        $this->pdo->prepare("UPDATE Commande_B2B SET Statut = 'validee', Date_Validation = NOW() WHERE Id_Commande_B2B = ?")
            ->execute([$orderId]);
    }

    /** Commandes urgentes restées « en_attente » au-delà de leur délai de réponse (verrouillées). */
    public function findExpiredUrgentForUpdate(): array
    {
        return $this->pdo->query(
            "SELECT Id_Commande_B2B, Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse
             FROM Commande_B2B
             WHERE Est_Urgente = 1 AND Statut = 'en_attente'
               AND Date_Limite_Reponse IS NOT NULL AND Date_Limite_Reponse < NOW()
             FOR UPDATE"
        )->fetchAll();
    }

    /** Crédit de confiance initial, en « commandes réussies » fictives (voir recalculateSellerReliability). */
    public const RELIABILITY_PRIOR = 5;

    /**
     * Score de fiabilité du vendeur = part des commandes menées à terme parmi celles qu'il a
     * tranchées (refus et délais d'urgence dépassés compris), lissée par un crédit de départ :
     * (livrées + 5) / (livrées + refusées + 5). Sans ce crédit, un seul refus ferait tomber une
     * nouvelle entreprise à 0 ; avec, il la place à 83, et 10 refus sur 10 à 33.
     * Recalculé à chaque issue de commande, au lieu de l'ancien « +1 plafonné à 100 » qui
     * partait de 100 et ne bougeait donc jamais.
     */
    public function recalculateSellerReliability(int $sellerId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE Entreprise e
             JOIN (
                 SELECT SUM(Statut = 'livree') AS livrees, SUM(Statut IN ('livree', 'refusee')) AS tranchees
                 FROM Commande_B2B WHERE Id_Entreprise_Vendeuse = :seller1
             ) s
             SET e.Nombre_Commandes_Completees = COALESCE(s.livrees, 0),
                 e.Score_Fiabilite = ROUND(100 * (COALESCE(s.livrees, 0) + :prior1) / (COALESCE(s.tranchees, 0) + :prior2))
             WHERE e.Id_Entreprise = :seller2"
        );
        $statement->execute([
            'seller1' => $sellerId, 'seller2' => $sellerId,
            'prior1' => self::RELIABILITY_PRIOR, 'prior2' => self::RELIABILITY_PRIOR,
        ]);
    }

    public function findB2BProductForUpdate(int $productId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Produit, Nom_Produit, Prix_B2B, Quantite_En_Stock, Quantite_Min_B2B
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
                 Date_Limite_Reponse, Mode_Retrait, Adresse_Retrait,
                 Adresse_Livraison, Latitude_Livraison, Longitude_Livraison, Date_Commande, Id_Commande_Origine)
             VALUES (?, ?, ?, ?, \'en_attente\', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
        );
        $statement->execute([
            $order['number'], $order['buyer_id'], $order['seller_id'], $order['total'],
            $order['urgent'], $order['deadline_minutes'], $order['deadline'],
            $order['mode'], $order['pickup_address'],
            $order['delivery_address'] ?? null, $order['delivery_lat'] ?? null, $order['delivery_lng'] ?? null,
            $order['origin_id'] ?? null,
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
                (Numero_Vente, Id_Entreprise, Nom_Client, Nom_Vendeur, Id_Vendeur, Date_Vente, Montant_Total, Type_Vente, Articles_JSON)
             VALUES (?, ?, ?, ?, ?, NOW(), ?, 'b2b', ?)"
        );
        $statement->execute([
            $sale['number'], $sale['enterprise_id'], $sale['client'], $sale['seller'] ?? null,
            $sale['seller_id'] ?: null, $sale['total'], $sale['articles'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Ligne relationnelle de vente B2B — miroir de createLine() pour Ligne_Commande_B2B. */
    public function createSaleLine(int $saleId, array $line): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO Ligne_Vente (Id_Vente, Id_Produit, Nom_Produit, Quantite, Prix_Unitaire)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $saleId, $line['id_produit'], $line['nom'], $line['quantite'], $line['prix'],
        ]);
    }

    public function createB2BInvoice(int $saleId, int $orderId, string $number, float $total, int $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO Facture
                (Id_Vente, Id_Commande_B2B, Numero_Facture, Date_Echeance, Montant_HT, TVA, Montant_TTC, Date_Archivage, Id_Entreprise)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 YEAR), ?)'
        );
        $vat = Vat::fromTtc($total);
        $statement->execute([$saleId, $orderId, $number, $vat['ht'], $vat['tva'], $total, $enterpriseId]);

        return (int) $this->pdo->lastInsertId();
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
