<?php

declare(strict_types=1);

namespace App\Application\Inventory;

use App\Infrastructure\Persistence\StockRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class StockService
{
    public function __construct(
        private PDO $pdo,
        private StockRepository $repository
    ) {
    }

    public function receiveManual(array $items, int $enterpriseId): int
    {
        if ($items === []) {
            throw new InvalidArgumentException('Veuillez ajouter au moins un produit à approvisionner.');
        }

        $received = 0;
        $this->pdo->beginTransaction();

        try {
            foreach ($items as $item) {
                $productId = (int) ($item['produit'] ?? 0);
                $quantity = (int) ($item['quantite_ajouter'] ?? 0);
                $factor = max(1, (int) ($item['facteur_conversion'] ?? 1));
                $realQuantity = $quantity * $factor;

                if ($productId <= 0 || $realQuantity <= 0) {
                    continue;
                }

                if (!$this->repository->findProductForUpdate($productId, $enterpriseId)) {
                    throw new RuntimeException('Produit introuvable ou accès non autorisé.');
                }

                $this->repository->increase($productId, $enterpriseId, $realQuantity);
                $received += $realQuantity;
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $received;
    }

    public function receiveB2BLine(array $line, int $quantity, int $enterpriseId): void
    {
        $remaining = (int) $line['Quantite'] - (int) $line['Quantite_Receptionnee'];
        if ($quantity <= 0 || $quantity > $remaining) {
            throw new InvalidArgumentException('La quantité réceptionnée dépasse la quantité restante.');
        }

        $productId = $this->repository->findByNameForUpdate((string) $line['Nom_Produit'], $enterpriseId);
        if ($productId === null) {
            $productId = $this->repository->createReceivedProduct($line, $enterpriseId);
        }

        $this->repository->increase($productId, $enterpriseId, $quantity);
    }
}
