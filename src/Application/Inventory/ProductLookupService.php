<?php

declare(strict_types=1);

namespace App\Application\Inventory;

use PDO;

/**
 * Point unique de résolution "code-barre -> produit", scopé systématiquement à une
 * entreprise. Utilisé par le scanner caméra PC (api/lookup_product.php) et par le
 * scanner mobile distant (api/scan_session.php) pour ne jamais dupliquer la requête
 * ni le calcul de conditionnement.
 */
final class ProductLookupService
{
    /** @return array<string,mixed>|null null si aucun produit ne correspond pour cette entreprise. */
    public static function findByBarcode(PDO $pdo, string $barcode, int $entrepriseId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT Id_Produit, Nom_Produit, Description_Produit, Prix_Unitaire_Produit, Prix_B2B, Quantite_En_Stock,
                   Code_Barre_Unite, Code_Barre_Carton, Quantite_Par_Carton
            FROM Produit
            WHERE Id_Entreprise = ?
              AND (Code_Barre_Unite = ? OR Code_Barre_Carton = ?)
            LIMIT 1
        ");
        $stmt->execute([$entrepriseId, $barcode, $barcode]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produit) {
            return null;
        }

        // Le backend seul décide du conditionnement détecté et du coefficient — jamais le client.
        $conditionnement = PackagingConverter::detect($produit, $barcode);
        if ($conditionnement === null) {
            return null;
        }

        $prixUnitaire = (float) $produit['Prix_Unitaire_Produit'];

        return [
            'id_produit'           => (int) $produit['Id_Produit'],
            'nom_produit'          => $produit['Nom_Produit'],
            'description'          => $produit['Description_Produit'],
            'prix_unitaire'        => $prixUnitaire,
            'prix_b2b'             => $produit['Prix_B2B'] !== null ? (float) $produit['Prix_B2B'] : null,
            'stock_disponible'     => (int) $produit['Quantite_En_Stock'],
            'code_barre_unite'     => $produit['Code_Barre_Unite'],
            'code_barre_carton'    => $produit['Code_Barre_Carton'],
            'quantite_par_carton'  => PackagingConverter::coefficientCarton($produit),
            'type_conditionnement' => $conditionnement['type'],
            'coefficient'          => $conditionnement['coefficient'],
            'prix_conditionnement' => $prixUnitaire * $conditionnement['coefficient'],
        ];
    }
}
