<?php

declare(strict_types=1);

namespace App\Application\Inventory;

use App\Infrastructure\Persistence\ProductRepository;
use InvalidArgumentException;

final class ProductService
{
    public function __construct(private ProductRepository $repository)
    {
    }

    public function list(int $enterpriseId): array
    {
        return $this->repository->findAllByEnterprise($enterpriseId);
    }

    public function find(int $productId, int $enterpriseId): ?array
    {
        return $this->repository->findByIdAndEnterprise($productId, $enterpriseId);
    }

    public function delete(int $productId, int $enterpriseId): void
    {
        $this->repository->delete($productId, $enterpriseId);
    }

    public function save(array $input, int $enterpriseId, ?int $productId = null): void
    {
        $name = trim((string) ($input['nom'] ?? ''));
        $price = filter_var($input['prix'] ?? null, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($input['stock'] ?? null, FILTER_VALIDATE_INT);

        if ($name === '' || $price === false || $price < 0 || $stock === false || $stock < 0) {
            throw new InvalidArgumentException('Nom, prix et stock doivent être valides.');
        }

        $seuil = filter_var($input['seuil_alerte'] ?? 5, FILTER_VALIDATE_INT);
        if ($seuil === false || $seuil < 0) {
            $seuil = 5;
        }

        $this->repository->save([
            'nom' => $name,
            'description' => trim((string) ($input['description'] ?? '')),
            'prix' => $price,
            'stock' => $stock,
            'seuil_alerte' => $seuil,
            'en_destockage' => isset($input['en_destockage_b2b']) ? 1 : 0,
            'prix_b2b' => ($input['prix_b2b'] ?? '') !== '' ? $input['prix_b2b'] : null,
            'qte_min_b2b' => ($input['quantite_min_b2b'] ?? '') !== '' ? $input['quantite_min_b2b'] : 1,
            'code_barre_unite' => null,
            'code_barre_carton' => null,
            'quantite_par_carton' => 1,
        ], $enterpriseId, $productId);
    }
}
