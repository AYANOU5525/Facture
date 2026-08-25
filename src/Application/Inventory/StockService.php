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
                $qteUnite = max(0, (int) ($item['qte_unite'] ?? 0));
                $qteCarton = max(0, (int) ($item['qte_carton'] ?? 0));

                if ($productId <= 0 || ($qteUnite <= 0 && $qteCarton <= 0)) {
                    continue;
                }

                $product = $this->repository->findProductForUpdate($productId, $enterpriseId);
                if (!$product) {
                    throw new RuntimeException('Produit introuvable ou accès non autorisé.');
                }

                // Coefficient carton lu uniquement depuis le produit verrouillé en base — jamais depuis le client.
                $realQuantity = PackagingConverter::combinedUnits($product, $qteCarton, $qteUnite);
                if ($realQuantity <= 0) {
                    continue;
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

        // Le code-barre du vendeur est plus fiable qu'un nom pour reconnaître le même article
        // dans le catalogue de l'acheteur (noms parfois formulés différemment d'une entreprise à l'autre).
        $productId = $this->repository->findByBarcodeForUpdate(
            (string) ($line['Code_Barre_Unite'] ?? ''),
            $line['Code_Barre_Carton'] ?? null,
            $enterpriseId
        );

        if ($productId === null) {
            $productId = $this->repository->findByNameForUpdate((string) $line['Nom_Produit'], $enterpriseId);
        }

        if ($productId === null) {
            $productId = $this->repository->createReceivedProduct($line, $enterpriseId);
        }

        $this->repository->increase($productId, $enterpriseId, $quantity);
    }
}
