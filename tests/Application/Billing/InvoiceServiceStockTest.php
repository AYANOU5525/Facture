<?php

declare(strict_types=1);

namespace Tests\Application\Billing;

use App\Application\Billing\InvoiceService;
use App\Infrastructure\Persistence\InvoiceRepository;
use RuntimeException;
use Tests\DatabaseTestCase;

/**
 * Vérifie l'invariant le plus critique de l'application : impossible de vendre plus
 * d'unités qu'il n'y en a en stock — et, à l'inverse, qu'une vente valide décrémente le
 * stock, enregistre la TVA et reçoit un numéro séquentiel.
 */
final class InvoiceServiceStockTest extends DatabaseTestCase
{
    public function testOversellIsRejectedAndStockIsUnchanged(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $enterprise = $f->enterprise();
        $product = $f->product($enterprise, ['Quantite_En_Stock' => 5]);

        try {
            (new InvoiceService($pdo, new InvoiceRepository($pdo)))->createDirectSale(
                'Client test', 'phpunit', [['produit' => $product, 'qte_unite' => 6]], $enterprise
            );
            $this->fail('La vente aurait dû être refusée.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Stock insuffisant', $e->getMessage());
        }

        $this->assertSame(5, $f->stock($product), 'Le stock ne doit pas bouger quand la vente échoue.');
    }

    public function testUnknownProductIsRejected(): void
    {
        $pdo = $this->getPdo();
        $enterprise = $this->fixtures($pdo)->enterprise();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/introuvable/');

        (new InvoiceService($pdo, new InvoiceRepository($pdo)))->createDirectSale(
            'Client test', 'phpunit', [['produit' => 999999, 'qte_unite' => 1]], $enterprise
        );
    }

    public function testProductOfAnotherEnterpriseIsRejected(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $other = $f->product($f->enterprise('Autre'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/introuvable/');

        (new InvoiceService($pdo, new InvoiceRepository($pdo)))->createDirectSale(
            'Client test', 'phpunit', [['produit' => $other, 'qte_unite' => 1]], $f->enterprise()
        );
    }

    public function testEmptyClientNameIsRejectedBeforeTouchingTheDatabase(): void
    {
        $pdo = $this->getPdo();
        $this->expectException(\InvalidArgumentException::class);

        (new InvoiceService($pdo, new InvoiceRepository($pdo)))->createDirectSale(
            '   ', 'phpunit', [['produit' => 1, 'qte_unite' => 1]], 1
        );
    }

    public function testValidSaleDecrementsStockAndRecordsVatAndSequentialNumbers(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $enterprise = $f->enterprise();
        $product = $f->product($enterprise, ['Prix_Unitaire_Produit' => 1180, 'Quantite_En_Stock' => 10, 'Quantite_Par_Carton' => 4]);
        $service = new InvoiceService($pdo, new InvoiceRepository($pdo));

        // 1 carton de 4 + 1 unité = 5 unités ; nom falsifié envoyé par le navigateur ignoré.
        $first = $service->createDirectSale('Client test', 'phpunit',
            [['produit' => $product, 'qte_carton' => 1, 'qte_unite' => 1, 'label' => 'Nom falsifié']], $enterprise, 0, false);
        $second = $service->createDirectSale('Client test', 'phpunit', [['produit' => $product, 'qte_unite' => 1]], $enterprise);

        $this->assertSame(4, $f->stock($product));
        $this->assertMatchesRegularExpression('/^FAC-\d{8}-\d{4,}$/', $first);
        $this->assertSame((int) substr($first, -4) + 1, (int) substr($second, -4), 'Numéros consécutifs attendus.');

        $invoice = $pdo->query("SELECT f.Montant_HT, f.TVA, f.Montant_TTC, lv.Nom_Produit,
                (SELECT COUNT(*) FROM Logistique l WHERE l.Id_Vente = v.Id_Vente) AS livraisons
            FROM Vente v JOIN Facture f USING (Id_Vente) JOIN Ligne_Vente lv USING (Id_Vente)
            WHERE v.Numero_Vente = '$first'")->fetch();
        $this->assertEqualsWithDelta(5900.0, (float) $invoice['Montant_TTC'], 0.001);
        $this->assertEqualsWithDelta(5000.0, (float) $invoice['Montant_HT'], 0.001);
        $this->assertEqualsWithDelta(900.0, (float) $invoice['TVA'], 0.001);
        $this->assertNotSame('Nom falsifié', $invoice['Nom_Produit']);
        $this->assertSame(0, (int) $invoice['livraisons'], 'Pas de livraison pour un retrait sur place.');
    }
}
