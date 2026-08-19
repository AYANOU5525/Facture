<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\OrderRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class B2BOrderService
{
    public function __construct(
        private PDO $pdo,
        private OrderRepository $repository
    ) {
    }

    public function create(array $input, int $buyerId): array
    {
        $sellerId = (int) ($input['seller_id'] ?? 0);
        $items = $input['items'] ?? [];
        if ($sellerId <= 0 || $sellerId === $buyerId || !is_array($items) || $items === []) {
            throw new InvalidArgumentException('Fournisseur et produits obligatoires.');
        }

        $urgent = isset($input['urgent']) ? 1 : 0;
        $deadlineMinutes = (int) ($input['deadline_minutes'] ?? 120);
        if ($deadlineMinutes < 30 || $deadlineMinutes > 10080) {
            $deadlineMinutes = 120;
        }
        $mode = in_array($input['mode'] ?? '', ['livraison', 'retrait_place'], true)
            ? $input['mode'] : 'livraison';
        $pickupAddress = $mode === 'retrait_place' ? trim((string) ($input['pickup_address'] ?? '')) : null;
        $deadline = $urgent ? date('Y-m-d H:i:s', strtotime("+{$deadlineMinutes} minutes")) : null;

        $lines = [];
        $total = 0.0;
        $this->pdo->beginTransaction();
        try {
            foreach ($items as $productId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity <= 0) {
                    continue;
                }
                $product = $this->repository->findB2BProductForUpdate((int) $productId, $sellerId);
                if (!$product) {
                    throw new RuntimeException("Produit ID {$productId} indisponible.");
                }
                if ((int) $product['Quantite_En_Stock'] < $quantity) {
                    throw new RuntimeException("Stock insuffisant pour {$product['Nom_Produit']}.");
                }
                $subtotal = (float) $product['Prix_B2B'] * $quantity;
                $total += $subtotal;
                $lines[] = [
                    'product_id' => (int) $product['Id_Produit'],
                    'name' => $product['Nom_Produit'],
                    'quantity' => $quantity,
                    'unit_price' => $product['Prix_B2B'],
                    'subtotal' => $subtotal,
                ];
            }
            if ($lines === []) {
                throw new InvalidArgumentException('Aucune quantité saisie.');
            }

            $number = 'CMD-' . date('Ymd') . '-' . random_int(1000, 9999);
            $orderId = $this->repository->create([
                'number' => $number, 'buyer_id' => $buyerId, 'seller_id' => $sellerId,
                'total' => $total, 'urgent' => $urgent, 'deadline_minutes' => $deadlineMinutes,
                'deadline' => $deadline, 'mode' => $mode, 'pickup_address' => $pickupAddress,
            ]);
            foreach ($lines as $line) {
                $this->repository->createLine($orderId, $line);
            }
            $this->pdo->commit();

            return ['id' => $orderId, 'number' => $number, 'seller_id' => $sellerId, 'total' => $total, 'urgent' => $urgent, 'deadline_minutes' => $deadlineMinutes];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
