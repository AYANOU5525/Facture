<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

/** Client direct (comptoir) — cf. entité Client du MLD (mémoire de soutenance). */
final class ClientRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Retrouve ou crée le client d'une vente comptoir, scopé par entreprise.
     * Nom_Client reste la clé d'appariement (comme Nom_Vendeur pour Id_Vendeur).
     */
    public function findOrCreate(string $name, int $enterpriseId): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO Client (Id_Entreprise, Nom_Client, Type_Client)
             VALUES (?, ?, \'direct\')
             ON DUPLICATE KEY UPDATE Id_Client = LAST_INSERT_ID(Id_Client)'
        );
        $statement->execute([$enterpriseId, $name]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByIdAndEnterprise(int $clientId, int $enterpriseId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM Client WHERE Id_Client = ? AND Id_Entreprise = ?'
        );
        $statement->execute([$clientId, $enterpriseId]);
        $client = $statement->fetch();

        return $client ?: null;
    }

    /** Noms des clients directs déjà connus, pour l'autocomplétion à la saisie d'une vente. */
    public function namesByEnterprise(int $enterpriseId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT Nom_Client FROM Client WHERE Id_Entreprise = ? AND Type_Client = 'direct' ORDER BY Nom_Client"
        );
        $statement->execute([$enterpriseId]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Fiches clients directs indexées par nom, pour enrichir la liste agrégée par Vente. */
    public function byNameForEnterprise(int $enterpriseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Client, Nom_Client, Telephone_Client, Email_Client, Adresse_Client, NIF_Client, Statut_Client, Date_Creation
             FROM Client WHERE Id_Entreprise = ?'
        );
        $statement->execute([$enterpriseId]);

        $byName = [];
        foreach ($statement->fetchAll() as $row) {
            $byName[$row['Nom_Client']] = $row;
        }

        return $byName;
    }

    public function updateContact(int $clientId, int $enterpriseId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Client SET
                Telephone_Client = ?, Email_Client = ?, Adresse_Client = ?,
                NIF_Client = ?, Statut_Client = ?
             WHERE Id_Client = ? AND Id_Entreprise = ?'
        );
        $statement->execute([
            $data['telephone'] !== '' ? $data['telephone'] : null,
            $data['email'] !== '' ? $data['email'] : null,
            $data['adresse'] !== '' ? $data['adresse'] : null,
            $data['nif'] !== '' ? $data['nif'] : null,
            $data['statut'],
            $clientId,
            $enterpriseId,
        ]);
    }
}
