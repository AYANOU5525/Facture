<?php

declare(strict_types=1);

namespace Tests\Application\Inventory;

use App\Application\Inventory\PackagingConverter;
use PHPUnit\Framework\TestCase;

final class PackagingConverterTest extends TestCase
{
    public function testCoefficientCartonReadsFromProduct(): void
    {
        $this->assertSame(20, PackagingConverter::coefficientCarton(['Quantite_Par_Carton' => 20]));
    }

    public function testCoefficientCartonDefaultsToOneWhenMissing(): void
    {
        $this->assertSame(1, PackagingConverter::coefficientCarton([]));
    }

    public function testCoefficientCartonNeverGoesBelowOne(): void
    {
        // Une valeur corrompue/nulle en base ne doit jamais produire un coefficient <= 0
        // (diviserait effectivement le stock si utilisé tel quel dans un calcul).
        $this->assertSame(1, PackagingConverter::coefficientCarton(['Quantite_Par_Carton' => 0]));
        $this->assertSame(1, PackagingConverter::coefficientCarton(['Quantite_Par_Carton' => -5]));
    }

    public function testToUnitsInCartonsMultipliesByCoefficient(): void
    {
        $product = ['Quantite_Par_Carton' => 4];
        $this->assertSame(12, PackagingConverter::toUnits($product, PackagingConverter::CARTON, 3));
    }

    public function testToUnitsInUnitsIgnoresCoefficient(): void
    {
        $product = ['Quantite_Par_Carton' => 4];
        $this->assertSame(3, PackagingConverter::toUnits($product, PackagingConverter::UNITE, 3));
    }

    public function testToUnitsClampsNegativeQuantityToZero(): void
    {
        $product = ['Quantite_Par_Carton' => 4];
        $this->assertSame(0, PackagingConverter::toUnits($product, PackagingConverter::CARTON, -3));
        $this->assertSame(0, PackagingConverter::toUnits($product, PackagingConverter::UNITE, -3));
    }

    public function testCombinedUnitsSumsCartonsAndUnits(): void
    {
        // Produit vendu par carton de 10, saisie : 2 cartons + 5 unités = 25 unités réelles.
        $product = ['Quantite_Par_Carton' => 10];
        $this->assertSame(25, PackagingConverter::combinedUnits($product, 2, 5));
    }

    public function testCombinedUnitsWithZeroBoth(): void
    {
        $product = ['Quantite_Par_Carton' => 10];
        $this->assertSame(0, PackagingConverter::combinedUnits($product, 0, 0));
    }

    public function testCombinedUnitsWhenCartonCoefficientIsOne(): void
    {
        // Produit vendu à l'unité seule (pas de conditionnement carton) : cartons et unités
        // s'additionnent 1-pour-1, aucune distinction de comportement.
        $product = ['Quantite_Par_Carton' => 1];
        $this->assertSame(7, PackagingConverter::combinedUnits($product, 3, 4));
    }

    public function testDetectMatchesCartonBarcode(): void
    {
        $product = [
            'Code_Barre_Unite' => '1111',
            'Code_Barre_Carton' => '2222',
            'Quantite_Par_Carton' => 6,
        ];
        $result = PackagingConverter::detect($product, '2222');
        $this->assertSame(['type' => PackagingConverter::CARTON, 'coefficient' => 6], $result);
    }

    public function testDetectMatchesUniteBarcode(): void
    {
        $product = [
            'Code_Barre_Unite' => '1111',
            'Code_Barre_Carton' => '2222',
            'Quantite_Par_Carton' => 6,
        ];
        $result = PackagingConverter::detect($product, '1111');
        $this->assertSame(['type' => PackagingConverter::UNITE, 'coefficient' => 1], $result);
    }

    public function testDetectReturnsNullForUnknownBarcode(): void
    {
        $product = ['Code_Barre_Unite' => '1111', 'Code_Barre_Carton' => '2222'];
        $this->assertNull(PackagingConverter::detect($product, '9999'));
    }

    public function testDetectHandlesMissingCartonBarcode(): void
    {
        // Produit sans code-barre carton (colonne NULL en base) : ne doit jamais matcher
        // une chaîne vide envoyée par erreur, ni lever d'erreur sur le champ manquant.
        $product = ['Code_Barre_Unite' => '1111', 'Code_Barre_Carton' => null];
        $this->assertNull(PackagingConverter::detect($product, ''));
        $this->assertSame(
            ['type' => PackagingConverter::UNITE, 'coefficient' => 1],
            PackagingConverter::detect($product, '1111')
        );
    }

    public function testDetectPrefersCartonWhenBothBarcodesAreIdenticalByMistake(): void
    {
        // Cas de données aberrant (les deux codes-barres identiques) : le carton est
        // vérifié en premier dans l'implémentation, donc c'est le résultat attendu ici,
        // pas un choix arbitraire du test.
        $product = ['Code_Barre_Unite' => 'SAME', 'Code_Barre_Carton' => 'SAME', 'Quantite_Par_Carton' => 3];
        $result = PackagingConverter::detect($product, 'SAME');
        $this->assertSame(PackagingConverter::CARTON, $result['type']);
    }
}
