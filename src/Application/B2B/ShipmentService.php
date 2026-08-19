<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\OrderRepository;
use RuntimeException;
use PDO;

final class ShipmentService
{
    public function __construct(
        private PDO $pdo,
        private OrderRepository $repository
    ) {
    }

    public function ship(int $orderId, int $sellerId): array
    {
        $this->pdo->beginTransaction();
        try {
            $order = $this->repository->findReadyForShipment($orderId, $sellerId);
            if (!$order) {
                throw new RuntimeException('Commande introuvable ou statut incorrect.');
            }

            $number = 'FAC-B2B-' . date('Ymd') . '-' . random_int(100, 999);
            $lines = $this->repository->findLines($orderId);
            $saleId = $this->repository->createB2BSale([
                'number' => $number,
                'enterprise_id' => $sellerId,
                'client' => $order['Nom_Acheteur'],
                'total' => $order['Montant_Total'],
                'articles' => json_encode($lines, JSON_UNESCAPED_UNICODE),
            ]);
            $invoiceId = $this->repository->createB2BInvoice(
                $saleId,
                $orderId,
                $number,
                (float) $order['Montant_Total'],
                $sellerId
            );
            $this->repository->createB2BLogistics($saleId, $orderId, $invoiceId, $sellerId);
            $this->repository->markShipped($orderId);
            $this->repository->recordHistory(
                $orderId,
                $order['Statut'],
                'expediee',
                "Facture {$number} générée",
                $sellerId
            );
            $this->pdo->commit();

            return [
                'order' => $order,
                'number' => $number,
                'sale_id' => $saleId,
                'invoice_id' => $invoiceId,
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
