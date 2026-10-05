<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use InvalidArgumentException;
use PDO;

/**
 * Numéros de documents séquentiels par jour : PREFIXE-AAAAMMJJ-0001, 0002...
 * Remplace les numéros aléatoires (random_int) qui pouvaient entrer en collision avec
 * la contrainte UNIQUE (seulement 900 valeurs/jour pour FAC-B2B) et laissaient des
 * trous dans la numérotation des factures.
 *
 * À appeler DANS la transaction qui insère le document : le SELECT ... FOR UPDATE pose
 * un verrou sur la plage du jour, deux ventes simultanées reçoivent donc des numéros
 * distincts au lieu de se disputer le même.
 */
final class DocumentNumberRepository
{
    /** Colonnes autorisées (le nom de table/colonne est injecté dans le SQL). */
    private const COLUMNS = [
        'Vente' => 'Numero_Vente',
        'Commande_B2B' => 'Numero_Commande',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function next(string $table, string $prefix): string
    {
        $column = self::COLUMNS[$table] ?? throw new InvalidArgumentException("Numérotation non prévue pour $table.");
        $base = $prefix . '-' . date('Ymd') . '-';

        $statement = $this->pdo->prepare(
            "SELECT $column FROM $table WHERE $column LIKE ? ORDER BY $column FOR UPDATE"
        );
        $statement->execute([$base . '%']);

        // Plus grand suffixe numérique existant (les anciens numéros aléatoires du jour sont
        // pris en compte, la séquence repart simplement au-dessus).
        $max = 0;
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $number) {
            $suffix = substr((string) $number, strlen($base));
            if (ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $base . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
