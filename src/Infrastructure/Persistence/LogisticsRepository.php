<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class LogisticsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findForEnterprise(int $logisticsId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT l.*, v.Nom_Client, v.Numero_Vente,
                    c.Id_Entreprise_Acheteuse, c.Id_Entreprise_Vendeuse, c.Numero_Commande,
                    c.Statut AS Statut_Commande,
                    u.Nom_Utilisateur AS Nom_Livreur,
                    e_vend.Nom_Entreprise AS Nom_Vendeur, e_vend.Latitude AS Lat_Vendeur,
                    e_vend.Longitude AS Lng_Vendeur, e_vend.Adresse_Entreprise AS Adresse_Vendeur,
                    e_ach.Nom_Entreprise AS Nom_Acheteur, e_ach.Latitude AS Lat_Acheteur,
                    e_ach.Longitude AS Lng_Acheteur, e_ach.Adresse_Entreprise AS Adresse_Acheteur,
                    e_me.Nom_Entreprise AS Mon_Nom_Entreprise, e_me.Latitude AS Ma_Latitude,
                    e_me.Longitude AS Ma_Longitude, e_me.Adresse_Entreprise AS Mon_Adresse
             FROM Logistique l
             LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
             LEFT JOIN Commande_B2B c ON l.Id_Commande_B2B = c.Id_Commande_B2B
             LEFT JOIN Utilisateur u ON l.Id_Livreur = u.Id_Utilisateur
             LEFT JOIN Entreprise e_vend ON c.Id_Entreprise_Vendeuse = e_vend.Id_Entreprise
             LEFT JOIN Entreprise e_ach ON c.Id_Entreprise_Acheteuse = e_ach.Id_Entreprise
             LEFT JOIN Entreprise e_me ON l.Id_Entreprise = e_me.Id_Entreprise
             WHERE l.Id_Logistique = ? AND l.Id_Entreprise = ?'
        );
        $statement->execute([$logisticsId, $enterpriseId]);
        $logistics = $statement->fetch();

        return $logistics ?: null;
    }

    /** Fiche de l'entreprise, verrouillée pour une transition de statut. */
    public function findForUpdate(int $logisticsId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM Logistique WHERE Id_Logistique = ? AND Id_Entreprise = ? FOR UPDATE'
        );
        $statement->execute([$logisticsId, $enterpriseId]);
        $logistics = $statement->fetch();

        return $logistics ?: null;
    }

    /** Fiche en cours (non annulée) d'une commande B2B, verrouillée. */
    public function findActiveForOrder(int $orderId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM Logistique
             WHERE Id_Commande_B2B = ? AND Statut_Livraison <> 'annulee'
             ORDER BY Id_Logistique DESC LIMIT 1
             FOR UPDATE"
        );
        $statement->execute([$orderId]);
        $logistics = $statement->fetch();

        return $logistics ?: null;
    }

    public function createForOrder(
        int $orderId,
        int $sellerId,
        string $trackingNumber,
        ?string $address,
        ?float $latitude,
        ?float $longitude
    ): int {
        $statement = $this->pdo->prepare(
            "INSERT INTO Logistique
                (Id_Commande_B2B, Numero_Suivi, Statut_Livraison, Id_Entreprise,
                 Adresse_Livraison, Adresse_Livraison_Lat, Adresse_Livraison_Lng)
             VALUES (?, ?, 'traitement', ?, ?, ?, ?)"
        );
        $statement->execute([$orderId, $trackingNumber, $sellerId, $address, $latitude, $longitude]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setTrackingNumber(int $logisticsId, string $trackingNumber): void
    {
        $this->pdo->prepare('UPDATE Logistique SET Numero_Suivi = ? WHERE Id_Logistique = ?')
            ->execute([$trackingNumber, $logisticsId]);
    }

    /** Membres de l'entreprise pouvant être assignés à une livraison (livreurs et propriétaires). */
    public function findCarriersForEnterprise(int $enterpriseId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT Id_Utilisateur, Nom_Utilisateur, Role_Utilisateur
             FROM Utilisateur
             WHERE Id_Entreprise = ? AND Role_Utilisateur IN ('livreur', 'proprio')
             ORDER BY Role_Utilisateur = 'livreur' DESC, Nom_Utilisateur"
        );
        $statement->execute([$enterpriseId]);

        return $statement->fetchAll();
    }

    public function findCarrier(int $userId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT Id_Utilisateur, Nom_Utilisateur, Role_Utilisateur
             FROM Utilisateur
             WHERE Id_Utilisateur = ? AND Id_Entreprise = ? AND Role_Utilisateur IN ('livreur', 'proprio')"
        );
        $statement->execute([$userId, $enterpriseId]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function markShipped(int $logisticsId, array $data): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE Logistique SET
                Statut_Livraison = 'expediee', Date_Expedition = NOW(),
                Transporteur = ?, Id_Livreur = ?, Date_Livraison_Prevue = ?, Notes_Logistique = ?,
                Adresse_Livraison = ?, Adresse_Livraison_Lat = ?, Adresse_Livraison_Lng = ?,
                Id_Vente = COALESCE(?, Id_Vente), Id_Facture = COALESCE(?, Id_Facture)
             WHERE Id_Logistique = ?"
        );
        $statement->execute([
            $data['carrier'], $data['carrier_id'], $data['date_prevue'], $data['notes'],
            $data['address'], $data['latitude'], $data['longitude'],
            $data['sale_id'] ?? null, $data['invoice_id'] ?? null, $logisticsId,
        ]);
    }

    /** Remise confirmée par le livreur ; la note éventuelle complète les notes existantes. */
    public function confirmByCarrier(int $logisticsId, string $note): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE Logistique
             SET Date_Confirmation_Livreur = NOW(),
                 Notes_Logistique = CONCAT_WS('\n', NULLIF(Notes_Logistique, ''), NULLIF(?, ''))
             WHERE Id_Logistique = ?"
        );
        $statement->execute([$note, $logisticsId]);
    }

    public function confirmByBuyer(int $logisticsId): void
    {
        $this->pdo->prepare('UPDATE Logistique SET Date_Confirmation_Acheteur = NOW() WHERE Id_Logistique = ?')
            ->execute([$logisticsId]);
    }

    public function markDelivered(int $logisticsId): void
    {
        $this->pdo->prepare(
            "UPDATE Logistique SET Statut_Livraison = 'livree', Date_Livraison_Effectuee = NOW() WHERE Id_Logistique = ?"
        )->execute([$logisticsId]);
    }

    public function cancel(int $logisticsId): void
    {
        $this->pdo->prepare("UPDATE Logistique SET Statut_Livraison = 'annulee' WHERE Id_Logistique = ?")
            ->execute([$logisticsId]);
    }
}
