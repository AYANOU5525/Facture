<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Infrastructure\Persistence\SalesWorkflowRepository;
use InvalidArgumentException;

final class SalesWorkflowService
{
    public function __construct(private SalesWorkflowRepository $repository)
    {
    }

    public function findSale(string $number, int $enterpriseId): ?array
    {
        return $this->repository->findSale($number, $enterpriseId);
    }

    public function hasLogistics(int $saleId): ?array
    {
        return $this->repository->hasLogistics($saleId);
    }

    public function markPaid(int $invoiceId): void
    {
        $this->repository->markPaid($invoiceId);
    }

    public function createLogistics(
        int $saleId,
        int $enterpriseId,
        string $carrier,
        string $trackingNumber,
        ?string $deliveryDate
    ): void {
        if (trim($carrier) === '' || trim($trackingNumber) === '') {
            throw new InvalidArgumentException('Le transporteur et le numéro de suivi sont obligatoires.');
        }

        $this->repository->createLogistics(
            $saleId,
            $enterpriseId,
            trim($carrier),
            trim($trackingNumber),
            $deliveryDate
        );
    }
}
