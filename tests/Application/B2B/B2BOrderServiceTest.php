<?php

declare(strict_types=1);

namespace Tests\Application\B2B;

use App\Application\B2B\B2BOrderService;
use App\Infrastructure\Persistence\OrderRepository;
use RuntimeException;
use Tests\DatabaseTestCase;

/** Création d'une commande B2B : quantité minimale, urgence, numérotation. */
final class B2BOrderServiceTest extends DatabaseTestCase
{
    /** @return array{0:\PDO,1:int,2:int,3:int} [pdo, acheteur, vendeur, produit déstocké (min 5)] */
    private function setUpScenario(): array
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $seller = $f->enterprise('Vendeur');
        $product = $f->product($seller, ['En_Destockage_B2B' => 1, 'Prix_B2B' => 500, 'Quantite_Min_B2B' => 5, 'Quantite_En_Stock' => 100]);

        return [$pdo, $f->enterprise('Acheteur'), $seller, $product];
    }

    private function create(\PDO $pdo, int $buyer, array $input): array
    {
        return (new B2BOrderService($pdo, new OrderRepository($pdo)))->create($input, $buyer);
    }

    public function testQuantityBelowTheMinimumIsRejected(): void
    {
        [$pdo, $buyer, $seller, $product] = $this->setUpScenario();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/minimale/');

        $this->create($pdo, $buyer, ['seller_id' => $seller, 'items' => [$product => 4]]);
    }

    public function testUncheckedUrgencyStaysNotUrgent(): void
    {
        [$pdo, $buyer, $seller, $product] = $this->setUpScenario();

        // Le contrôleur transmet toujours la clé : isset(false) était vrai, d'où des commandes toutes urgentes.
        $order = $this->create($pdo, $buyer, ['seller_id' => $seller, 'items' => [$product => 5], 'urgent' => false]);
        $urgent = $this->create($pdo, $buyer, ['seller_id' => $seller, 'items' => [$product => 5], 'urgent' => true, 'deadline_minutes' => 60]);

        $row = $pdo->query("SELECT Est_Urgente, Date_Limite_Reponse, Montant_Total FROM Commande_B2B WHERE Id_Commande_B2B = {$order['id']}")->fetch();
        $this->assertSame(0, (int) $row['Est_Urgente']);
        $this->assertNull($row['Date_Limite_Reponse']);
        $this->assertEqualsWithDelta(2500.0, (float) $row['Montant_Total'], 0.001);
        $this->assertSame(1, (int) $pdo->query("SELECT Est_Urgente FROM Commande_B2B WHERE Id_Commande_B2B = {$urgent['id']}")->fetchColumn());
        $this->assertMatchesRegularExpression('/^CMD-\d{8}-\d{4,}$/', $order['number']);
        $this->assertSame((int) substr($order['number'], -4) + 1, (int) substr($urgent['number'], -4));
    }

    public function testBuyerMayOrderMoreThanTheSellerStock(): void
    {
        [$pdo, $buyer, $seller, $product] = $this->setUpScenario();

        // Stock vendeur = 100 : l'acheteur ne le voit pas et peut en demander davantage ;
        // c'est au vendeur de signaler le manque (proposition partielle).
        $order = $this->create($pdo, $buyer, ['seller_id' => $seller, 'items' => [$product => 150]]);

        $this->assertSame('en_attente', $this->fixtures($pdo)->orderStatus($order['id']));
        $this->assertSame(100, $this->fixtures($pdo)->stock($product), 'Rien n’est déduit à la commande.');
    }
}
