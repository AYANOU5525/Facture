<?php

declare(strict_types=1);

namespace App\Application\Billing;

/**
 * TVA appliquée aux factures. Les prix saisis dans FactuPro sont des prix TTC : le
 * montant HT et la TVA sont déduits du TTC (et non TTC = HT × 0,8 comme auparavant,
 * ce qui revenait à un taux de 25 %).
 */
final class Vat
{
    /** Taux de TVA du Togo (18 %). Seul endroit à modifier si le taux change. */
    public const RATE = 0.18;

    /** @return array{ht: float, tva: float} */
    public static function fromTtc(float $ttc): array
    {
        $ht = round($ttc / (1 + self::RATE), 2);

        return ['ht' => $ht, 'tva' => round($ttc - $ht, 2)];
    }

    /** Libellé affiché sur la facture, ex. « 18 % ». */
    public static function label(): string
    {
        return rtrim(rtrim(number_format(self::RATE * 100, 2, ',', ''), '0'), ',') . ' %';
    }
}
