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
    private const ENTERPRISE_ID = 1;
    private const PRODUCT_A = 3; // Souris sans fil — Code_Barre_Unite déjà renseigné, Carton NULL
    private const PRODUCT_B = 5; // Hub USB 4 ports — Code_Barre_Unite déjà renseigné, Carton NULL

    private function service(\PDO $pdo): ProductService
    {
        return new ProductService(new ProductRepository($pdo));
    }

    public function testAssociatesAnUnknownCodeToTheEmptySlot(): void
    {
        $pdo = $this->getPdo();
        $code = 'PHPUNIT-TEST-CARTON-' . uniqid();

        try {
            $this->service($pdo)->associateBarcode(self::PRODUCT_A, self::ENTERPRISE_ID, 'carton', $code);

            $stored = $pdo->query("SELECT Code_Barre_Carton FROM Produit WHERE Id_Produit = " . self::PRODUCT_A)->fetchColumn();
            $this->assertSame($code, $stored);
        } finally {
            $pdo->exec("UPDATE Produit SET Code_Barre_Carton = NULL WHERE Id_Produit = " . self::PRODUCT_A);
        }
    }

    public function testRejectsWhenTheSlotIsAlreadyFilled(): void
    {
        $pdo = $this->getPdo();
        $existing = $pdo->query("SELECT Code_Barre_Unite FROM Produit WHERE Id_Produit = " . self::PRODUCT_A)->fetchColumn();
        $this->assertNotEmpty($existing, 'Précondition : le produit fixture doit déjà avoir un code unité.');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/déjà un code/');

        $this->service($pdo)->associateBarcode(self::PRODUCT_A, self::ENTERPRISE_ID, 'unite', 'PHPUNIT-TEST-' . uniqid());
    }

    public function testRejectsACodeAlreadyUsedByAnotherProduct(): void
    {
        $pdo = $this->getPdo();
        $codeDejaPris = $pdo->query("SELECT Code_Barre_Unite FROM Produit WHERE Id_Produit = " . self::PRODUCT_B)->fetchColumn();
        $this->assertNotEmpty($codeDejaPris, 'Précondition : le produit B fixture doit déjà avoir un code unité.');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/déjà utilisé/');

        // PRODUCT_A n'a pas de code carton : on tente de lui associer, en tant que carton,
        // le code déjà utilisé comme code UNITÉ par PRODUCT_B — collision inter-produits
        // et inter-champs, doit être rejetée.
        $this->service($pdo)->associateBarcode(self::PRODUCT_A, self::ENTERPRISE_ID, 'carton', (string) $codeDejaPris);
    }

    public function testRejectsAnEmptyCode(): void
    {
        $pdo = $this->getPdo();
        $this->expectException(InvalidArgumentException::class);
        $this->service($pdo)->associateBarcode(self::PRODUCT_A, self::ENTERPRISE_ID, 'carton', '   ');
    }

    public function testRejectsAnInvalidType(): void
    {
        $pdo = $this->getPdo();
        $this->expectException(InvalidArgumentException::class);
        $this->service($pdo)->associateBarcode(self::PRODUCT_A, self::ENTERPRISE_ID, 'palette', 'PHPUNIT-TEST-' . uniqid());
    }

    public function testRejectsAnUnknownProduct(): void
    {
        $pdo = $this->getPdo();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/introuvable/');
        $this->service($pdo)->associateBarcode(999999, self::ENTERPRISE_ID, 'unite', 'PHPUNIT-TEST-' . uniqid());
    }

    public function testRejectsAProductBelongingToAnotherEnterprise(): void
    {
        $pdo = $this->getPdo();
        // PRODUCT_A appartient à l'entreprise 1 : le demander sous l'entreprise 2 doit échouer
        // exactement comme un produit inexistant (isolation multi-tenant).
        $this->expectException(InvalidArgumentException::class);
        $this->service($pdo)->associateBarcode(self::PRODUCT_A, 2, 'unite', 'PHPUNIT-TEST-' . uniqid());
    }
}
