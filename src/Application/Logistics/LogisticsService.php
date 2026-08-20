<?php

declare(strict_types=1);

namespace App\Application\Logistics;

use App\Infrastructure\Persistence\LogisticsRepository;
use InvalidArgumentException;
use PDO;

final class LogisticsService
{
    private const VALID_STATUSES = ['traitement', 'en_attente', 'expediee', 'livree', 'annulee'];

    public function __construct(
        private PDO $pdo,
        private LogisticsRepository $repository
    ) {
    }

    public function find(int $logisticsId, int $enterpriseId): ?array
    {
        return $this->repository->findForEnterprise($logisticsId, $enterpriseId);
    }

    public function update(int $logisticsId, int $enterpriseId, array $data): ?array
    {
        if (!in_array($data['status'], self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Statut de livraison invalide.');
        }
        if ($data['status'] === 'expediee' && ($data['carrier'] === '' || $data['tracking'] === '')) {
            throw new InvalidArgumentException('Le transporteur et le numéro de suivi sont requis pour une expédition.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->repository->updateTracking($logisticsId, $data);
            $event = null;
            $commandId = (int) ($data['command_id'] ?? 0);

            if ($commandId > 0) {
                $command = $this->repository->findB2BCommandForUpdate($commandId);
                if ($command) {
                    $oldStatus = $command['Statut'];
                    if ($data['status'] === 'expediee' && !in_array($oldStatus, ['validee', 'en_preparation', 'prete', 'expediee'], true)) {
                        throw new InvalidArgumentException('Cette commande doit être préparée avant son expédition.');
                    }
                    if ($data['status'] === 'livree' && !in_array($oldStatus, ['expediee', 'livree'], true)) {
                        throw new InvalidArgumentException('Cette commande doit être expédiée avant sa livraison.');
                    }

                    // Synchronise le statut B2B pour l'expédition uniquement.
                    // La transition vers 'livree' (confirmation finale) appartient à l'acheteur
                    // via commandes_b2b.php action=livree.
                    if ($data['status'] === 'expediee' && $oldStatus !== 'expediee') {
                        $this->repository->updateCommandStatus($commandId, 'expediee');
                    }

                    // Retourner l'event pour toutes les transitions notifiables
                    if ($data['status'] !== $oldStatus || $data['status'] === 'livree') {
                        $event = ['command' => $command, 'old_status' => $oldStatus, 'new_status' => $data['status']];
                    }
                }
            }

            $this->pdo->commit();
            return $event;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
