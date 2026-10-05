<?php

declare(strict_types=1);

namespace Tests\Application\Billing;

use App\Application\Billing\Vat;
use PHPUnit\Framework\TestCase;

final class VatTest extends TestCase
{
    public function testSplitsATtcAmountAtEighteenPercent(): void
    {
        $this->assertSame(['ht' => 10000.0, 'tva' => 1800.0], Vat::fromTtc(11800));
    }

    public function testHtPlusVatAlwaysEqualsTtc(): void
    {
        foreach ([1, 999, 15000, 52500, 123456.78] as $ttc) {
            $vat = Vat::fromTtc($ttc);
            $this->assertEqualsWithDelta($ttc, $vat['ht'] + $vat['tva'], 0.001);
        }
    }

    public function testLabel(): void
    {
        $this->assertSame('18 %', Vat::label());
    }
}
