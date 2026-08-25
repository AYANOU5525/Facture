<?php

declare(strict_types=1);

namespace App\Application\Inventory;

/**
 * Point unique de conversion unité/carton, partagé par la vente, l'approvisionnement
 * et toute future intégration B2B. Quantite_En_Stock est toujours en unités — un carton
 * n'est jamais qu'un multiplicateur appliqué à la volée, jamais un stock séparé.
 *
 * Le coefficient vient TOUJOURS du produit chargé depuis la base (jamais d'une valeur
 * envoyée par le client) : impossible de falsifier la conversion depuis le navigateur.
 */
final class PackagingConverter
{
    public const UNITE = 'unite';
    public const CARTON = 'carton';

    /** Nombre d'unités contenues dans un carton pour ce produit (>= 1). */
    public static function coefficientCarton(array $product): int
    {
        return max(1, (int) ($product['Quantite_Par_Carton'] ?? 1));
    }

    /** Convertit une quantité saisie dans un conditionnement donné en nombre d'unités réelles. */
    public static function toUnits(array $product, string $typeConditionnement, int $quantite): int
    {
        $quantite = max(0, $quantite);

        return $typeConditionnement === self::CARTON
            ? $quantite * self::coefficientCarton($product)
            : $quantite;
    }

    /** Additionne une saisie mixte (cartons + unités) en un total d'unités réelles. */
    public static function combinedUnits(array $product, int $quantiteCarton, int $quantiteUnite): int
    {
        return self::toUnits($product, self::CARTON, $quantiteCarton)
             + self::toUnits($product, self::UNITE, $quantiteUnite);
    }

    /**
     * Détermine à quel conditionnement correspond le code-barres scanné.
     * @return array{type: string, coefficient: int}|null null si le code ne correspond à rien.
     */
    public static function detect(array $product, string $scannedBarcode): ?array
    {
        if (!empty($product['Code_Barre_Carton']) && $product['Code_Barre_Carton'] === $scannedBarcode) {
            return ['type' => self::CARTON, 'coefficient' => self::coefficientCarton($product)];
        }

        if (!empty($product['Code_Barre_Unite']) && $product['Code_Barre_Unite'] === $scannedBarcode) {
            return ['type' => self::UNITE, 'coefficient' => 1];
        }

        return null;
    }
}
