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

    /**
     * Associe un code-barres scanné mais encore inconnu à un produit EXISTANT — jamais de création
     * de fiche ni de modification d'un code déjà renseigné par cette voie (cf. AssociateBarcodeController-like
     * usage : édition complète du produit reste le seul moyen de remplacer un code déjà présent).
     * @param string $type 'unite' ou 'carton'
     */
    public function associateBarcode(int $productId, int $enterpriseId, string $type, string $code): void
    {
        $code = trim($code);
        if ($code === '') {
            throw new InvalidArgumentException('Code-barres vide.');
        }
        if (!in_array($type, ['unite', 'carton'], true)) {
            throw new InvalidArgumentException('Type de conditionnement invalide.');
        }

        $product = $this->repository->findByIdAndEnterprise($productId, $enterpriseId);
        if ($product === null) {
            throw new InvalidArgumentException('Produit introuvable.');
        }

        $column = $type === 'carton' ? 'Code_Barre_Carton' : 'Code_Barre_Unite';
        if (!empty($product[$column])) {
            throw new InvalidArgumentException(
                'Ce produit a déjà un code ' . ($type === 'carton' ? 'carton' : 'unité') . ' enregistré : modifiez-le depuis la fiche produit si besoin.'
            );
        }

        $conflict = $this->repository->findConflictingProduct(
            $type === 'unite' ? $code : null,
            $type === 'carton' ? $code : null,
            $enterpriseId
        );
        if ($conflict !== null) {
            throw new InvalidArgumentException('Ce code-barres est déjà utilisé par « ' . $conflict['Nom_Produit'] . ' ».');
        }

        $this->repository->associateBarcode($productId, $enterpriseId, $column, $code);
    }

    public function save(array $input, int $enterpriseId, ?int $productId = null): int
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

        $quantiteMinB2bInput = $input['quantite_min_b2b'] ?? '';
        $quantiteMinB2b = $quantiteMinB2bInput === '' ? 1 : filter_var($quantiteMinB2bInput, FILTER_VALIDATE_INT);
        if ($quantiteMinB2b === false || $quantiteMinB2b < 1) {
            throw new InvalidArgumentException('La quantité minimale B2B doit être un nombre entier supérieur ou égal à 1.');
        }

        $prixB2bInput = $input['prix_b2b'] ?? '';
        $prixB2b = $prixB2bInput !== '' ? filter_var($prixB2bInput, FILTER_VALIDATE_FLOAT) : null;
        if ($prixB2b === false || ($prixB2b !== null && $prixB2b < 0)) {
            throw new InvalidArgumentException('Le prix B2B doit être un montant valide et positif ou nul.');
        }

        $enDestockage = isset($input['en_destockage_b2b']);
        if ($enDestockage && $prixB2b === null) {
            $prixB2b = $price;
        }

        $codeUniteOrNull = $codeBarreUnite !== '' ? $codeBarreUnite : null;
        $codeCartonOrNull = $codeBarreCarton !== '' ? $codeBarreCarton : null;

        if ($codeUniteOrNull !== null && $codeCartonOrNull !== null && $codeUniteOrNull === $codeCartonOrNull) {
            throw new InvalidArgumentException('Le code-barre unité et le code-barre carton doivent être différents.');
        }

        // Nom unique par entreprise : la réception B2B (StockService::receiveB2BLine) retrouve le
        // produit de l'acheteur par son nom à défaut de code-barres, un doublon la rendrait ambiguë.
        if ($this->repository->findByName($name, $enterpriseId, $productId) !== null) {
            throw new InvalidArgumentException('Un produit nommé « ' . $name . ' » existe déjà.');
        }

        $conflict = $this->repository->findConflictingProduct($codeUniteOrNull, $codeCartonOrNull, $enterpriseId, $productId);
        if ($conflict !== null) {
            throw new InvalidArgumentException(
                'Ce code-barres est déjà utilisé par le produit « ' . $conflict['Nom_Produit'] . ' ». Un même code-barres ne peut pas être partagé entre deux produits.'
            );
        }

        return $this->repository->save([
            'nom' => $name,
            'description' => trim((string) ($input['description'] ?? '')),
            'prix' => $price,
            'stock' => $stock,
            'seuil_alerte' => $seuil,
            'en_destockage' => $enDestockage ? 1 : 0,
            'prix_b2b' => $prixB2b,
            'qte_min_b2b' => $quantiteMinB2b,
            'code_barre_unite' => $codeBarreUnite !== '' ? $codeBarreUnite : null,
            'code_barre_carton' => $codeBarreCarton !== '' ? $codeBarreCarton : null,
            'quantite_par_carton' => $quantiteParCarton,
        ], $enterpriseId, $productId);
    }
}
