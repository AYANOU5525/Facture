<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Infrastructure\Persistence\SalesWorkflowRepository;

final class SalesWorkflowService
{
    public function __construct(private SalesWorkflowRepository $repository)
    {
    }

    public function findSale(string $number, int $enterpriseId): ?array
    {
        return $this->repository->findSale($number, $enterpriseId);
    }

    public function findLogistics(int $saleId): ?array
    {
        return $this->repository->findLogistics($saleId);
    }

    public function markPaid(int $invoiceId): void
    {
        $this->repository->markPaid($invoiceId);
    }
}
