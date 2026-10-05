<?php

declare(strict_types=1);

namespace Tests\Application\Inventory;

use App\Application\Inventory\ProductService;
use App\Infrastructure\Persistence\ProductRepository;
use InvalidArgumentException;
use Tests\DatabaseTestCase;

/** Un nom de produit est unique par entreprise (la réception B2B s'appuie dessus). */
final class ProductServiceUniqueNameTest extends DatabaseTestCase
{
    public function testDuplicateNameInTheSameEnterpriseIsRejected(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $enterprise = $f->enterprise();
        $f->product($enterprise, ['Nom_Produit' => 'Riz 25kg']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/existe déjà/');

        (new ProductService(new ProductRepository($pdo)))->save(['nom' => '  riz 25KG ', 'prix' => '100', 'stock' => '1'], $enterprise);
    }

    public function testSameNameInAnotherEnterpriseAndRenamingItselfAreAllowed(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $enterprise = $f->enterprise();
        $existing = $f->product($enterprise, ['Nom_Produit' => 'Huile 1L']);
        $service = new ProductService(new ProductRepository($pdo));

        $this->assertGreaterThan(0, $service->save(['nom' => 'Huile 1L', 'prix' => '100', 'stock' => '1'], $f->enterprise('Autre')));
        // Réenregistrer le produit sous son propre nom n'est pas un doublon.
        $this->assertSame($existing, $service->save(['nom' => 'Huile 1L', 'prix' => '150', 'stock' => '2'], $enterprise, $existing));
    }
}
