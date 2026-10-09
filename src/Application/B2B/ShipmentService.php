<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\DocumentNumberRepository;
use App\Infrastructure\Persistence\OrderRepository;
use RuntimeException;
use PDO;

/**
 * Sortie d'une commande B2B de chez le vendeur : facture générée et commande « expediee ».
 *
 * - Commande en livraison : appelé par LogisticsService::ship() à la VALIDATION de
 *   l'expédition (transporteur, N° de suivi et date prévue renseignés), pas au clic
 *   « Expédier » qui ne fait qu'ouvrir la fiche de livraison.
 * - Retrait sur place (ou logistique désactivée) : ship() directement, sans fiche de livraison.
 */
final class ShipmentService
{
    public function __construct(
        private PDO $pdo,
        private OrderRepository $repository
    ) {
    }

    public function ship(int $orderId, int $sellerId, string $userName = '', int $userId = 0): array
    {
        $this->pdo->beginTransaction();
        try {
            $order = $this->repository->findReadyForShipment($orderId, $sellerId);
            if (!$order) {
                throw new RuntimeException('Commande introuvable ou statut incorrect.');
            }
            $result = $this->invoiceAndMarkShipped($order, $sellerId, $userName, $userId);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Génère la vente + la facture B2B et passe la commande « expediee ». À appeler dans une
     * transaction ouverte, la commande ($order, issue de findReadyForShipment) étant verrouillée.
     *
     * @return array{order: array, number: string, sale_id: int, invoice_id: int}
     */
    public function invoiceAndMarkShipped(array $order, int $sellerId, string $userName = '', int $userId = 0, string $note = ''): array
    {
        $orderId = (int) $order['Id_Commande_B2B'];
        $number = (new DocumentNumberRepository($this->pdo))->next('Vente', 'FAC-B2B');
        $lines = $this->repository->findLines($orderId);
        $saleId = $this->repository->createB2BSale([
            'number' => $number,
            'enterprise_id' => $sellerId,
            'client' => $order['Nom_Acheteur'],
            'seller' => $userName,
            'seller_id' => $userId,
            'total' => $order['Montant_Total'],
            'articles' => json_encode($lines, JSON_UNESCAPED_UNICODE),
        ]);
        foreach ($lines as $line) {
            $this->repository->createSaleLine($saleId, $line);
        }
        $invoiceId = $this->repository->createB2BInvoice(
            $saleId,
            $orderId,
            $number,
            (float) $order['Montant_Total'],
            $sellerId
        );
        $this->repository->markShipped($orderId);
        $this->repository->recordHistory(
            $orderId,
            $order['Statut'],
            'expediee',
            trim("Facture {$number} générée. " . $note),
            $sellerId
        );

        return [
            'order' => $order,
            'number' => $number,
            'sale_id' => $saleId,
            'invoice_id' => $invoiceId,
        ];
    }
}
