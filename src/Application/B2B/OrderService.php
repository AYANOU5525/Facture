<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\OrderRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class OrderService
{
    public function __construct(
        private PDO $pdo,
        private OrderRepository $repository
    ) {
    }

    public function transitionForSeller(
        int $orderId,
        int $enterpriseId,
        string $expectedStatus,
        string $newStatus,
        ?string $message = null
    ): array {
        $this->pdo->beginTransaction();

        try {
            $order = $this->repository->findForSeller($orderId, $enterpriseId, $expectedStatus);
            if (!$order) {
                throw new RuntimeException("Commande introuvable ou statut incorrect (attendu : {$expectedStatus}).");
            }

            $this->repository->transition($orderId, $newStatus, $message);
            $this->repository->recordHistory($orderId, $expectedStatus, $newStatus, $message, $enterpriseId);
            $this->pdo->commit();

            return $order;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function refuse(int $orderId, int $enterpriseId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Veuillez indiquer un motif de refus.');
        }

        return $this->transitionForSeller($orderId, $enterpriseId, 'en_attente', 'refusee', $reason);
    }
}
