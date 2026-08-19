<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class NotificationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countUnread(int $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM Notification_B2B
             WHERE Id_Entreprise_Destinataire = ? AND Est_Lue = FALSE'
        );
        $statement->execute([$enterpriseId]);

        return (int) $statement->fetchColumn();
    }

    public function findLatest(int $enterpriseId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT n.Id_Notification, n.Type_Notif, n.Titre, n.Message,
                    n.Id_Commande_B2B, n.Est_Lue, n.Date_Creation,
                    c.Numero_Commande
             FROM Notification_B2B n
             LEFT JOIN Commande_B2B c ON n.Id_Commande_B2B = c.Id_Commande_B2B
             WHERE n.Id_Entreprise_Destinataire = ?
             ORDER BY n.Date_Creation DESC
             LIMIT ?'
        );
        $statement->bindValue(1, $enterpriseId, PDO::PARAM_INT);
        $statement->bindValue(2, $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function markRead(int $notificationId, int $enterpriseId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE Notification_B2B SET Est_Lue = TRUE
             WHERE Id_Notification = ? AND Id_Entreprise_Destinataire = ?'
        );
        $statement->execute([$notificationId, $enterpriseId]);
    }

    public function markAllRead(int $enterpriseId): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE Notification_B2B SET Est_Lue = TRUE
             WHERE Id_Entreprise_Destinataire = ? AND Est_Lue = FALSE'
        );
        $statement->execute([$enterpriseId]);

        return $statement->rowCount();
    }
}
