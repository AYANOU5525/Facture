<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Infrastructure\Persistence\InvoiceRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class InvoiceService
{
    public function __construct(
        private PDO $pdo,
        private InvoiceRepository $repository
    ) {
    }

    public function availableProducts(int $enterpriseId): array
    {
        return $this->repository->availableProducts($enterpriseId);
    }

    public function createDirectSale(
        string $client,
        string $seller,
        array $items,
        int $enterpriseId
    ): string {
        if (trim($client) === '' || $items === []) {
            throw new InvalidArgumentException('Client et articles sont obligatoires.');
        }

        $total = 0.0;
        $articles = [];
        $seen = [];
        $this->pdo->beginTransaction();

        try {
            foreach ($items as $item) {
                $productId = (int) ($item['produit'] ?? 0);
                $quantity = (int) ($item['quantite'] ?? 0);
                $factor = max(1, (int) ($item['facteur_conversion'] ?? 1));
                $realQuantity = $quantity * $factor;

                if ($productId <= 0 || $quantity <= 0) {
                    continue;
                }
                if (isset($seen[$productId])) {
                    throw new InvalidArgumentException('Un produit ne peut apparaître qu’une seule fois.');
                }
                $seen[$productId] = true;

                $product = $this->repository->lockProduct($productId, $enterpriseId);
                if (!$product || (int) $product['Quantite_En_Stock'] < $realQuantity) {
                    throw new RuntimeException('Stock insuffisant pour ' . ($product['Nom_Produit'] ?? 'le produit') . '.');
                }

                $unitPrice = isset($item['prix']) ? (float) $item['prix'] : (float) $product['Prix_Unitaire_Produit'] * $factor;
                $lineTotal = isset($item['prix']) ? $unitPrice * $quantity : $unitPrice * $realQuantity;
                $total += $lineTotal;
                $articles[] = [
                    'id_produit' => $productId,
                    'nom' => $item['label'] ?? $product['Nom_Produit'],
                    'quantite' => $quantity,
                    'facteur_conversion' => $factor,
                    'quantite_unites' => $realQuantity,
                    'prix' => $unitPrice,
                    'total' => $lineTotal,
                ];
                $this->repository->decreaseStock($productId, $realQuantity);
            }

            if ($articles === []) {
                throw new InvalidArgumentException('Aucun article valide.');
            }

            $number = 'FAC-' . date('Ymd') . '-' . random_int(1000, 9999);
            $saleId = $this->repository->createSale([
                'number' => $number,
                'client' => trim($client),
                'seller' => $seller,
                'articles' => json_encode($articles, JSON_UNESCAPED_UNICODE),
                'total' => $total,
                'enterprise_id' => $enterpriseId,
            ]);
            $invoiceId = $this->repository->createInvoice($saleId, $number, $total, $enterpriseId);
            $this->repository->createLogistics($saleId, $invoiceId, $enterpriseId);
            $this->pdo->commit();

            return $number;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
