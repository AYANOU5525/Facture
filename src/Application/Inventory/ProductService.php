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

    public function toggleDestockage(int $productId, int $enterpriseId, bool $enabled): void
    {
        $this->repository->toggleDestockage($productId, $enterpriseId, $enabled);
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

        $codeBarreUnite = trim((string) ($input['code_barre_unite'] ?? ''));
        $codeBarreCarton = trim((string) ($input['code_barre_carton'] ?? ''));

        $quantiteParCarton = filter_var($input['quantite_par_carton'] ?? 1, FILTER_VALIDATE_INT);
        if ($quantiteParCarton === false || $quantiteParCarton < 1) {
            $quantiteParCarton = 1;
        }

        $codeUniteOrNull = $codeBarreUnite !== '' ? $codeBarreUnite : null;
        $codeCartonOrNull = $codeBarreCarton !== '' ? $codeBarreCarton : null;

        if ($codeUniteOrNull !== null && $codeCartonOrNull !== null && $codeUniteOrNull === $codeCartonOrNull) {
            throw new InvalidArgumentException('Le code-barre unité et le code-barre carton doivent être différents.');
        }

        $conflict = $this->repository->findConflictingProduct($codeUniteOrNull, $codeCartonOrNull, $enterpriseId, $productId);
        if ($conflict !== null) {
            throw new InvalidArgumentException(
                'Ce code-barres est déjà utilisé par le produit « ' . $conflict['Nom_Produit'] . ' ». Un même code-barres ne peut pas être partagé entre deux produits.'
            );
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
            'code_barre_unite' => $codeBarreUnite !== '' ? $codeBarreUnite : null,
            'code_barre_carton' => $codeBarreCarton !== '' ? $codeBarreCarton : null,
            'quantite_par_carton' => $quantiteParCarton,
        ], $enterpriseId, $productId);
    }
}
