<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\ChatRepository;
use InvalidArgumentException;

final class ChatService
{
    private const VALID_MESSAGE_TYPES = [
        'texte',
        'negociation_qte',
        'negociation_delai',
        'confirmation_dispo',
        'fichier',
    ];

    public function __construct(private ChatRepository $repository)
    {
    }

    public function canAccessCommand(int $commandId, int $enterpriseId): bool
    {
        return $this->repository->canAccessCommand($commandId, $enterpriseId);
    }

    public function getMessages(int $commandId, int $enterpriseId, int $sinceId = 0): array
    {
        $messages = $this->repository->findMessages($commandId, $enterpriseId, $sinceId);

        foreach ($messages as &$message) {
            $message['Date_Affichage'] = date('H:i', strtotime($message['Date_Envoi']));
            $message['Date_Tooltip'] = date('d/m/Y H:i', strtotime($message['Date_Envoi']));
        }
        unset($message);

        $this->repository->markAsRead($commandId, $enterpriseId);

        return $messages;
    }

    public function sendMessage(
        int $commandId,
        int $enterpriseId,
        ?string $message,
        string $type,
        ?string $filePath,
        ?string $fileName
    ): int {
        if (!in_array($type, self::VALID_MESSAGE_TYPES, true)) {
            throw new InvalidArgumentException('Type de message invalide.');
        }

        if (($message ?? '') === '' && $type !== 'fichier') {
            throw new InvalidArgumentException('Le message ne peut pas être vide.');
        }

        if (!$this->repository->canAccessCommand($commandId, $enterpriseId)) {
            throw new InvalidArgumentException('Accès non autorisé à cette commande.');
        }

        return $this->repository->createMessage(
            $commandId,
            $enterpriseId,
            $message !== '' ? $message : null,
            $type,
            $filePath,
            $fileName
        );
    }

    public function markAsRead(int $commandId, int $enterpriseId): void
    {
        if (!$this->repository->canAccessCommand($commandId, $enterpriseId)) {
            throw new InvalidArgumentException('Accès non autorisé à cette commande.');
        }

        $this->repository->markAsRead($commandId, $enterpriseId);
    }

    public function countUnread(int $enterpriseId): int
    {
        return $this->repository->countUnread($enterpriseId);
    }

    public function findCommand(int $commandId): ?array
    {
        return $this->repository->findCommand($commandId);
    }

    public function findEnterpriseName(int $enterpriseId): string
    {
        return $this->repository->findEnterpriseName($enterpriseId);
    }
}
