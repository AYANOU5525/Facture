<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\DocumentNumberRepository;
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

        // !empty et non isset : le contrôleur transmet toujours la clé (booléen true/false).
        $urgent = !empty($input['urgent']) ? 1 : 0;
        $deadlineMinutes = (int) ($input['deadline_minutes'] ?? 120);
        if ($deadlineMinutes < 30 || $deadlineMinutes > 10080) {
            $deadlineMinutes = 120;
        }
        $mode = in_array($input['mode'] ?? '', ['livraison', 'retrait_place'], true)
            ? $input['mode'] : 'livraison';
        $pickupAddress = $mode === 'retrait_place' ? trim((string) ($input['pickup_address'] ?? '')) : null;

        // Point de livraison choisi par l'acheteur sur la carte (mode livraison uniquement).
        // Optionnel : si l'acheteur ne précise rien, le livreur retombera sur l'adresse de
        // l'entreprise (cf. LogisticsRepository::findForEnterprise).
        $deliveryAddress = null;
        $deliveryLat = null;
        $deliveryLng = null;
        if ($mode === 'livraison') {
            $addr = trim((string) ($input['delivery_address'] ?? ''));
            $lat = $input['delivery_lat'] ?? null;
            $lng = $input['delivery_lng'] ?? null;
            if ($addr !== '' && is_numeric($lat) && is_numeric($lng)) {
                $deliveryAddress = $addr;
                $deliveryLat = (float) $lat;
                $deliveryLng = (float) $lng;
            }
        }

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
                $minimum = max(1, (int) ($product['Quantite_Min_B2B'] ?? 1));
                if ($quantity < $minimum) {
                    throw new RuntimeException("Quantité minimale de commande pour {$product['Nom_Produit']} : {$minimum}.");
                }
                // Pas de contrôle du stock du vendeur ici : l'acheteur ne le voit pas et peut
                // commander librement. Si le vendeur n'a pas tout, c'est à lui de le signaler
                // (proposition partielle, OrderService::proposePartial), et à l'acheteur de
                // répondre : accepter, accepter en se faisant compléter plus tard, ou annuler.
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

            $number = (new DocumentNumberRepository($this->pdo))->next('Commande_B2B', 'CMD');
            $orderId = $this->repository->create([
                'number' => $number, 'buyer_id' => $buyerId, 'seller_id' => $sellerId,
                'total' => $total, 'urgent' => $urgent, 'deadline_minutes' => $deadlineMinutes,
                'deadline' => $deadline, 'mode' => $mode, 'pickup_address' => $pickupAddress,
                'delivery_address' => $deliveryAddress, 'delivery_lat' => $deliveryLat, 'delivery_lng' => $deliveryLng,
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
