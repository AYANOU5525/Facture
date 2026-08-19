<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class ChatRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function canAccessCommand(int $commandId, int $enterpriseId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Commande_B2B
             FROM Commande_B2B
             WHERE Id_Commande_B2B = ?
               AND (Id_Entreprise_Acheteuse = ? OR Id_Entreprise_Vendeuse = ?)'
        );
        $statement->execute([$commandId, $enterpriseId, $enterpriseId]);

        return (bool) $statement->fetchColumn();
    }

    public function findMessages(int $commandId, int $enterpriseId, int $sinceId = 0): array
    {
        $sql = "
            SELECT
                m.Id_Message,
                m.Id_Entreprise_Emetteur,
                e.Nom_Entreprise AS Nom_Emetteur,
                m.Message,
                m.Type_Message,
                m.Fichier_Path,
                m.Fichier_Nom,
                m.Est_Lu_Acheteur,
                m.Est_Lu_Vendeur,
                m.Date_Envoi,
                CASE WHEN m.Id_Entreprise_Emetteur = ? THEN 1 ELSE 0 END AS Est_Moi
            FROM Chat_B2B m
            JOIN Entreprise e ON m.Id_Entreprise_Emetteur = e.Id_Entreprise
            WHERE m.Id_Commande_B2B = ?";
        $parameters = [$enterpriseId, $commandId];

        if ($sinceId > 0) {
            $sql .= ' AND m.Id_Message > ?';
            $parameters[] = $sinceId;
        }

        $sql .= ' ORDER BY m.Date_Envoi ASC LIMIT 100';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function markAsRead(int $commandId, int $enterpriseId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse
             FROM Commande_B2B
             WHERE Id_Commande_B2B = ?'
        );
        $statement->execute([$commandId]);
        $command = $statement->fetch();

        if (!$command) {
            return;
        }

        if ((int) $command['Id_Entreprise_Acheteuse'] === $enterpriseId) {
            $statement = $this->pdo->prepare(
                'UPDATE Chat_B2B
                 SET Est_Lu_Acheteur = 1
                 WHERE Id_Commande_B2B = ? AND Id_Entreprise_Emetteur != ?'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE Chat_B2B
                 SET Est_Lu_Vendeur = 1
                 WHERE Id_Commande_B2B = ? AND Id_Entreprise_Emetteur != ?'
            );
        }

        $statement->execute([$commandId, $enterpriseId]);
    }

    public function countUnread(int $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
              'SELECT SUM(CASE
                 WHEN c.Id_Entreprise_Acheteuse = :buyer_id
                     AND m.Est_Lu_Acheteur = 0
                     AND m.Id_Entreprise_Emetteur != :buyer_sender_id
                    THEN 1
                 WHEN c.Id_Entreprise_Vendeuse = :seller_id
                     AND m.Est_Lu_Vendeur = 0
                     AND m.Id_Entreprise_Emetteur != :seller_sender_id
                    THEN 1
                ELSE 0
            END) AS nb_non_lus
            FROM Chat_B2B m
            JOIN Commande_B2B c ON m.Id_Commande_B2B = c.Id_Commande_B2B
            WHERE c.Id_Entreprise_Acheteuse = :buyer_filter_id
               OR c.Id_Entreprise_Vendeuse = :seller_filter_id'
        );
        $statement->execute([
            'buyer_id' => $enterpriseId,
            'buyer_sender_id' => $enterpriseId,
            'seller_id' => $enterpriseId,
            'seller_sender_id' => $enterpriseId,
            'buyer_filter_id' => $enterpriseId,
            'seller_filter_id' => $enterpriseId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function createMessage(
        int $commandId,
        int $enterpriseId,
        ?string $message,
        string $type,
        ?string $filePath,
        ?string $fileName
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO Chat_B2B
                (Id_Commande_B2B, Id_Entreprise_Emetteur, Message, Type_Message, Fichier_Path, Fichier_Nom)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$commandId, $enterpriseId, $message, $type, $filePath, $fileName]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findCommand(int $commandId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT Id_Commande_B2B, Numero_Commande, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse
             FROM Commande_B2B
             WHERE Id_Commande_B2B = ?'
        );
        $statement->execute([$commandId]);
        $command = $statement->fetch();

        return $command ?: null;
    }

    public function findEnterpriseName(int $enterpriseId): string
    {
        $statement = $this->pdo->prepare('SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?');
        $statement->execute([$enterpriseId]);

        return (string) ($statement->fetchColumn() ?: '');
    }
}
