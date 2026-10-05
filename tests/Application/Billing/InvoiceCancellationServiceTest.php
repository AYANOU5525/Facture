<?php

declare(strict_types=1);

namespace Tests\Application\Billing;

use App\Application\Billing\InvoiceCancellationService;
use App\Application\Billing\InvoiceService;
use App\Infrastructure\Persistence\InvoiceRepository;
use InvalidArgumentException;
use Tests\DatabaseTestCase;

/** Annulation d'une facture : conservée, stock réintégré, définitive, encadrée. */
final class InvoiceCancellationServiceTest extends DatabaseTestCase
{
    /** @return array{0:\PDO,1:int,2:int,3:int} [pdo, entreprise, produit, facture] */
    private function sale(bool $withDelivery = false): array
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $enterprise = $f->enterprise();
        $product = $f->product($enterprise, ['Quantite_En_Stock' => 10]);
        $number = (new InvoiceService($pdo, new InvoiceRepository($pdo)))
            ->createDirectSale('Client test', 'phpunit', [['produit' => $product, 'qte_unite' => 3]], $enterprise, 0, $withDelivery);
        $invoice = (int) $pdo->query("SELECT Id_Facture FROM Facture WHERE Numero_Facture = '$number'")->fetchColumn();

        return [$pdo, $enterprise, $product, $invoice];
    }

    public function testCancellationKeepsTheInvoiceAndRestocks(): void
    {
        [$pdo, $enterprise, $product, $invoice] = $this->sale();
        $this->assertSame(7, $this->fixtures($pdo)->stock($product));

        $units = (new InvoiceCancellationService($pdo))->cancel($invoice, $enterprise);

        $this->assertSame(3, $units);
        $this->assertSame(10, $this->fixtures($pdo)->stock($product));
        $this->assertSame('annulee', $pdo->query("SELECT Statut_Paiement FROM Facture WHERE Id_Facture = $invoice")->fetchColumn());
    }

    public function testCancellationIsFinal(): void
    {
        [$pdo, $enterprise, , $invoice] = $this->sale();
        $service = new InvoiceCancellationService($pdo);
        $service->cancel($invoice, $enterprise);

        $this->expectException(InvalidArgumentException::class);
        $service->cancel($invoice, $enterprise);
    }

    public function testShippedGoodsCannotBeCancelled(): void
    {
        [$pdo, $enterprise, $product, $invoice] = $this->sale(true);
        $pdo->exec("UPDATE Logistique l JOIN Facture f ON f.Id_Vente = l.Id_Vente SET l.Statut_Livraison = 'expediee' WHERE f.Id_Facture = $invoice");

        try {
            (new InvoiceCancellationService($pdo))->cancel($invoice, $enterprise);
            $this->fail("L'annulation aurait dû être refusée.");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('expédiée', $e->getMessage());
        }
        $this->assertSame(7, $this->fixtures($pdo)->stock($product), 'Stock inchangé après un refus.');
    }

    public function testAnotherEnterpriseCannotCancel(): void
    {
        [$pdo, , , $invoice] = $this->sale();

        $this->expectException(\RuntimeException::class);
        (new InvoiceCancellationService($pdo))->cancel($invoice, $this->fixtures($pdo)->enterprise('Intrus'));
    }
}
