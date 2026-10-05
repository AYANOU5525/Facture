<?php

declare(strict_types=1);

namespace Tests\Application\Inventory;

use App\Application\Inventory\ProductService;
use App\Infrastructure\Persistence\ProductRepository;
use InvalidArgumentException;
use Tests\DatabaseTestCase;

/**
 * Couvre ProductService::associateBarcode() — le flux d'association d'un code-barres
 * scanné mais inconnu à un produit existant (jamais de création de fiche, jamais de
 * correspondance automatique sans confirmation explicite en amont côté contrôleur).
 */
final class ProductServiceAssociateBarcodeTest extends DatabaseTestCase
{
    private int $enterprise;
    private int $productA; // code unité renseigné, carton vide
    private int $productB; // code unité renseigné, carton vide

    protected function setUp(): void
    {
        $f = $this->fixtures($this->getPdo());
        $this->enterprise = $f->enterprise();
        $this->productA = $f->product($this->enterprise, ['Code_Barre_Unite' => 'A-' . uniqid()]);
        $this->productB = $f->product($this->enterprise, ['Code_Barre_Unite' => 'B-' . uniqid()]);
    }

    private function service(\PDO $pdo): ProductService
    {
        return new ProductService(new ProductRepository($pdo));
    }

    public function testAssociatesAnUnknownCodeToTheEmptySlot(): void
    {
        $pdo = $this->getPdo();
        $code = 'CARTON-' . uniqid();

        $this->service($pdo)->associateBarcode($this->productA, $this->enterprise, 'carton', $code);

        $stored = $pdo->query('SELECT Code_Barre_Carton FROM Produit WHERE Id_Produit = ' . $this->productA)->fetchColumn();
        $this->assertSame($code, $stored);
    }

    public function testRejectsWhenTheSlotIsAlreadyFilled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/déjà un code/');

        $this->service($this->getPdo())->associateBarcode($this->productA, $this->enterprise, 'unite', 'X-' . uniqid());
    }

    public function testRejectsACodeAlreadyUsedByAnotherProduct(): void
    {
        $pdo = $this->getPdo();
        $codeDejaPris = (string) $pdo->query('SELECT Code_Barre_Unite FROM Produit WHERE Id_Produit = ' . $this->productB)->fetchColumn();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/déjà utilisé/');

        // Le code UNITÉ de B proposé comme code CARTON de A : collision inter-produits et inter-champs.
        $this->service($pdo)->associateBarcode($this->productA, $this->enterprise, 'carton', $codeDejaPris);
    }

    public function testRejectsAnEmptyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service($this->getPdo())->associateBarcode($this->productA, $this->enterprise, 'carton', '   ');
    }

    public function testRejectsAnInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service($this->getPdo())->associateBarcode($this->productA, $this->enterprise, 'palette', 'X-' . uniqid());
    }

    public function testRejectsAnUnknownProduct(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/introuvable/');
        $this->service($this->getPdo())->associateBarcode(999999, $this->enterprise, 'unite', 'X-' . uniqid());
    }

    public function testRejectsAProductBelongingToAnotherEnterprise(): void
    {
        $pdo = $this->getPdo();
        $other = $this->fixtures($pdo)->enterprise('Autre');

        // Isolation multi-tenant : même comportement qu'un produit inexistant.
        $this->expectException(InvalidArgumentException::class);
        $this->service($pdo)->associateBarcode($this->productA, $other, 'unite', 'X-' . uniqid());
    }
}
