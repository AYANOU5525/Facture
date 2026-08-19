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
                    e_vend.Nom_Entreprise AS Nom_Vendeur, e_vend.Latitude AS Lat_Vendeur,
                    e_vend.Longitude AS Lng_Vendeur, e_vend.Adresse_Entreprise AS Adresse_Vendeur,
                    e_ach.Nom_Entreprise AS Nom_Acheteur, e_ach.Latitude AS Lat_Acheteur,
                    e_ach.Longitude AS Lng_Acheteur, e_ach.Adresse_Entreprise AS Adresse_Acheteur,
                    e_me.Nom_Entreprise AS Mon_Nom_Entreprise, e_me.Latitude AS Ma_Latitude,
                    e_me.Longitude AS Ma_Longitude, e_me.Adresse_Entreprise AS Mon_Adresse
             FROM Logistique l
             LEFT JOIN Vente v ON l.Id_Vente = v.Id_Vente
             LEFT JOIN Commande_B2B c ON l.Id_Commande_B2B = c.Id_Commande_B2B
             LEFT JOIN Entreprise e_vend ON c.Id_Entreprise_Vendeuse = e_vend.Id_Entreprise
             LEFT JOIN Entreprise e_ach ON c.Id_Entreprise_Acheteuse = e_ach.Id_Entreprise
             LEFT JOIN Entreprise e_me ON l.Id_Entreprise = e_me.Id_Entreprise
             WHERE l.Id_Logistique = ? AND l.Id_Entreprise = ?'
        );
        $statement->execute([$logisticsId, $enterpriseId]);
        $logistics = $statement->fetch();

        return $logistics ?: null;
    }

    public function updateTracking(int $logisticsId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Logistique SET
                Transporteur = ?, Numero_Suivi = ?, Statut_Livraison = ?,
                Date_Expedition = ?, Date_Livraison_Prevue = ?, Date_Livraison_Effectuee = ?,
                Notes_Logistique = ?, Adresse_Livraison = ?,
                Adresse_Livraison_Lat = ?, Adresse_Livraison_Lng = ?
             WHERE Id_Logistique = ?'
        );
        $statement->execute([
            $data['carrier'], $data['tracking'], $data['status'], $data['date_expedition'],
            $data['date_prevue'], $data['date_livraison'], $data['notes'], $data['address'],
            $data['latitude'], $data['longitude'], $logisticsId,
        ]);
    }

    public function findB2BCommandForUpdate(int $commandId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT Statut, Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse
             FROM Commande_B2B WHERE Id_Commande_B2B = ? FOR UPDATE'
        );
        $statement->execute([$commandId]);
        $command = $statement->fetch();

        return $command ?: null;
    }

    public function updateCommandStatus(int $commandId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE Commande_B2B SET Statut = ? WHERE Id_Commande_B2B = ?');
        $statement->execute([$status, $commandId]);
    }

    public function markDelivered(int $commandId, int $enterpriseId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE Logistique SET Statut_Livraison = 'livree', Date_Livraison_Effectuee = NOW()
             WHERE Id_Commande_B2B = ? AND Id_Entreprise = ?"
        );
        $statement->execute([$commandId, $enterpriseId]);
    }

    public function incrementSellerScore(int $enterpriseId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Entreprise SET Score_Fiabilite = LEAST(100, Score_Fiabilite + 1),
             Nombre_Commandes_Completees = Nombre_Commandes_Completees + 1
             WHERE Id_Entreprise = ?'
        );
        $statement->execute([$enterpriseId]);
    }
}
