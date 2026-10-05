<?php

declare(strict_types=1);

namespace Tests\Application\Inventory;

use App\Application\Inventory\ProductService;
use App\Infrastructure\Persistence\ProductRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductServiceValidationTest extends TestCase
{
    #[DataProvider('invalidMinimumValues')]
    public function testRejectsInvalidB2bMinimum(mixed $minimum): void
    {
        $service = new ProductService(new ProductRepository($this->createStub(\PDO::class)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('quantité minimale B2B');

        $service->save([
            'nom' => 'Produit test',
            'prix' => '100',
            'stock' => '0',
            'quantite_min_b2b' => $minimum,
        ], 1);
    }

    public static function invalidMinimumValues(): array
    {
        return [['0'], ['-1'], ['abc']];
    }
}