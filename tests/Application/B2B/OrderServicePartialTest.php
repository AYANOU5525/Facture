<?php

declare(strict_types=1);

namespace Tests\Application\B2B;

use App\Application\B2B\OrderService;
use App\Infrastructure\Persistence\OrderRepository;
use InvalidArgumentException;
use RuntimeException;
use Tests\DatabaseTestCase;

/**
 * Livraison partielle : le vendeur propose les quantités qu'il a, l'acheteur accepte
 * (commande validée sur ces quantités) ou annule (stock réservé rendu, score intact).
 */
final class OrderServicePartialTest extends DatabaseTestCase
{
    /**
     * Commande de 10 riz (stock vendeur 6) + 2 huiles (stock 50).
     * @return array{pdo:\PDO, f:\Tests\Fixtures, buyer:int, seller:int, order:int, riz:int, huile:int, ligneRiz:int, ligneHuile:int}
     */
    private function scenario(): array
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $seller = $f->enterprise('Vendeur');
        $buyer = $f->enterprise('Acheteur');
        $riz = $f->product($seller, ['Quantite_En_Stock' => 6]);
        $huile = $f->product($seller, ['Quantite_En_Stock' => 50]);
        $order = $f->order($buyer, $seller);
        $ligneRiz = $f->orderLine($order, $riz, 10, 1000);
        $ligneHuile = $f->orderLine($order, $huile, 2, 500);

        return compact('pdo', 'f', 'buyer', 'seller', 'order', 'riz', 'huile', 'ligneRiz', 'ligneHuile');
    }

    private function service(\PDO $pdo): OrderService
    {
        return new OrderService($pdo, new OrderRepository($pdo));
    }

    private function total(\PDO $pdo, int $order): float
    {
        return (float) $pdo->query("SELECT Montant_Total FROM Commande_B2B WHERE Id_Commande_B2B = $order")->fetchColumn();
    }

    public function testProposalReservesStockWithoutChangingTheOrderYet(): void
    {
        $s = $this->scenario();
        $this->assertEqualsWithDelta(11000.0, $this->total($s['pdo'], $s['order']), 0.001);

        $result = $this->service($s['pdo'])->proposePartial($s['order'], $s['seller'], [$s['ligneRiz'] => 6, $s['ligneHuile'] => 2], 'Rupture partielle');

        $this->assertStringContainsString('10 → 6', $result['summary']);
        $this->assertSame('a_confirmer', $s['f']->orderStatus($s['order']));
        $this->assertSame(0, $s['f']->stock($s['riz']), 'Stock réservé dès la proposition.');
        $this->assertSame(48, $s['f']->stock($s['huile']));
        $this->assertEqualsWithDelta(11000.0, $this->total($s['pdo'], $s['order']), 0.001, 'Commande inchangée tant que l’acheteur n’a pas accepté.');
    }

    /** @return iterable<string, array{0: callable(array): array, 1: string}> */
    public static function invalidProposals(): iterable
    {
        yield 'au-delà du stock' => [fn (array $s) => [$s['ligneRiz'] => 7, $s['ligneHuile'] => 2], 'Stock insuffisant'];
        yield 'au-delà de la commande' => [fn (array $s) => [$s['ligneRiz'] => 6, $s['ligneHuile'] => 3], 'invalide'];
        yield 'tout à zéro' => [fn (array $s) => [$s['ligneRiz'] => 0, $s['ligneHuile'] => 0], 'Aucune quantité'];
        yield 'quantité négative' => [fn (array $s) => [$s['ligneRiz'] => -1, $s['ligneHuile'] => 2], 'invalide'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidProposals')]
    public function testInvalidProposalsAreRejectedAndChangeNothing(callable $quantities, string $message): void
    {
        $s = $this->scenario();

        try {
            $this->service($s['pdo'])->proposePartial($s['order'], $s['seller'], $quantities($s));
            $this->fail('La proposition aurait dû être refusée.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
        $this->assertSame('en_attente', $s['f']->orderStatus($s['order']));
        $this->assertSame(6, $s['f']->stock($s['riz']));
    }

    public function testIdenticalQuantitiesMustUseNormalValidation(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $seller = $f->enterprise('Vendeur');
        $order = $f->order($f->enterprise('Acheteur'), $seller);
        $line = $f->orderLine($order, $f->product($seller), 3, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/validez-la directement/');
        $this->service($pdo)->proposePartial($order, $seller, [$line => 3]);
    }

    public function testAcceptanceReplacesQuantitiesAndValidates(): void
    {
        $s = $this->scenario();
        $service = $this->service($s['pdo']);
        // Riz réduit à 6, huile retirée (0).
        $service->proposePartial($s['order'], $s['seller'], [$s['ligneRiz'] => 6, $s['ligneHuile'] => 0]);
        $this->assertSame(50, $s['f']->stock($s['huile']), 'Une ligne à 0 ne réserve rien.');

        $service->acceptProposal($s['order'], $s['buyer']);

        $this->assertSame('validee', $s['f']->orderStatus($s['order']));
        $this->assertEqualsWithDelta(6000.0, $this->total($s['pdo'], $s['order']), 0.001);
        $lines = $s['pdo']->query("SELECT Id_Produit, Quantite, Quantite_Proposee FROM Ligne_Commande_B2B WHERE Id_Commande_B2B = {$s['order']}")->fetchAll();
        $this->assertCount(1, $lines, 'La ligne retirée disparaît.');
        $this->assertSame(6, (int) $lines[0]['Quantite']);
        $this->assertNull($lines[0]['Quantite_Proposee']);
        $this->assertSame(0, $s['f']->stock($s['riz']), 'Pas de seconde déduction à l’acceptation.');
    }

    public function testOnlyTheBuyerCanAccept(): void
    {
        $s = $this->scenario();
        $service = $this->service($s['pdo']);
        $service->proposePartial($s['order'], $s['seller'], [$s['ligneRiz'] => 6, $s['ligneHuile'] => 2]);

        $this->expectException(RuntimeException::class);
        $service->acceptProposal($s['order'], $s['seller']);
    }

    public function testBuyerCancellationReturnsStockAndKeepsTheSellerScore(): void
    {
        $s = $this->scenario();
        $service = $this->service($s['pdo']);
        $service->proposePartial($s['order'], $s['seller'], [$s['ligneRiz'] => 6, $s['ligneHuile'] => 2]);

        $service->cancelByBuyer($s['order'], $s['buyer'], 'Quantité trop faible');

        $this->assertSame('annulee', $s['f']->orderStatus($s['order']));
        $this->assertSame(6, $s['f']->stock($s['riz']));
        $this->assertSame(50, $s['f']->stock($s['huile']));
        (new OrderRepository($s['pdo']))->recalculateSellerReliability($s['seller']);
        $this->assertSame(100, (int) $s['pdo']->query("SELECT Score_Fiabilite FROM Entreprise WHERE Id_Entreprise = {$s['seller']}")->fetchColumn());
    }

    public function testValidatedOrdersCannotBeCancelledByTheBuyer(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $buyer = $f->enterprise('Acheteur');
        $order = $f->order($buyer, $f->enterprise('Vendeur'), 'validee');

        $this->expectException(RuntimeException::class);
        $this->service($pdo)->cancelByBuyer($order, $buyer);
    }

    public function testAcceptingWithCompleteLaterCreatesALinkedBackorder(): void
    {
        $s = $this->scenario();
        $service = $this->service($s['pdo']);
        $service->proposePartial($s['order'], $s['seller'], [$s['ligneRiz'] => 6, $s['ligneHuile'] => 2]);

        $result = $service->acceptProposal($s['order'], $s['buyer'], true);

        $this->assertSame('validee', $s['f']->orderStatus($s['order']));
        $this->assertNotNull($result['backorder']);
        $backorder = $result['backorder']['id'];
        $this->assertSame('en_attente', $s['f']->orderStatus($backorder));
        $row = $s['pdo']->query("SELECT Id_Commande_Origine, Id_Entreprise_Acheteuse, Id_Entreprise_Vendeuse, Montant_Total FROM Commande_B2B WHERE Id_Commande_B2B = $backorder")->fetch();
        $this->assertSame($s['order'], (int) $row['Id_Commande_Origine']);
        $this->assertSame([$s['buyer'], $s['seller']], [(int) $row['Id_Entreprise_Acheteuse'], (int) $row['Id_Entreprise_Vendeuse']]);
        // Seul le riz manque : 4 × 1000 au prix de la commande d'origine.
        $this->assertEqualsWithDelta(4000.0, (float) $row['Montant_Total'], 0.001);
        $lines = $s['pdo']->query("SELECT Id_Produit, Quantite FROM Ligne_Commande_B2B WHERE Id_Commande_B2B = $backorder")->fetchAll();
        $this->assertSame([[ 'Id_Produit' => $s['riz'], 'Quantite' => 4 ]], array_map(fn ($l) => ['Id_Produit' => (int) $l['Id_Produit'], 'Quantite' => (int) $l['Quantite']], $lines));
        $this->assertSame(0, $s['f']->stock($s['riz']), 'Le reliquat ne réserve pas de stock : le vendeur le validera plus tard.');
    }

    public function testAcceptingWithoutCompleteLaterCreatesNoBackorder(): void
    {
        $s = $this->scenario();
        $service = $this->service($s['pdo']);
        $service->proposePartial($s['order'], $s['seller'], [$s['ligneRiz'] => 6, $s['ligneHuile'] => 2]);

        $this->assertNull($service->acceptProposal($s['order'], $s['buyer'])['backorder']);
        $this->assertSame(0, (int) $s['pdo']->query("SELECT COUNT(*) FROM Commande_B2B WHERE Id_Commande_Origine = {$s['order']}")->fetchColumn());
    }
}
