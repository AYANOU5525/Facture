<?php

declare(strict_types=1);

namespace Tests\Application\B2B;

use App\Application\B2B\OrderService;
use App\Infrastructure\Persistence\OrderRepository;
use RuntimeException;
use Tests\DatabaseTestCase;

/**
 * Machine à états des commandes B2B : transitions refusées hors statut attendu, refus,
 * expiration automatique des commandes urgentes et score de fiabilité du vendeur.
 */
final class OrderServiceTransitionTest extends DatabaseTestCase
{
    private function service(\PDO $pdo): OrderService
    {
        return new OrderService($pdo, new OrderRepository($pdo));
    }

    private function score(\PDO $pdo, int $enterprise): int
    {
        return (int) $pdo->query("SELECT Score_Fiabilite FROM Entreprise WHERE Id_Entreprise = $enterprise")->fetchColumn();
    }

    public function testTransitionFailsWhenCurrentStatusDoesNotMatchExpected(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $seller = $f->enterprise('Vendeur');
        $order = $f->order($f->enterprise('Acheteur'), $seller, 'livree');

        try {
            $this->service($pdo)->transitionForSeller($order, $seller, 'en_attente', 'validee');
            $this->fail('La transition aurait dû être refusée.');
        } catch (RuntimeException $e) {
            $this->assertMatchesRegularExpression('/introuvable ou statut incorrect/', $e->getMessage());
        }
        $this->assertSame('livree', $f->orderStatus($order));
    }

    public function testTransitionFailsForWrongEnterprise(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $order = $f->order($f->enterprise('Acheteur'), $f->enterprise('Vendeur'));

        $this->expectException(RuntimeException::class);
        $this->service($pdo)->transitionForSeller($order, $f->enterprise('Intrus'), 'en_attente', 'validee');
    }

    public function testRefuseRejectsAnEmptyReason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service($this->getPdo())->refuse(999999, 999999, '   ');
    }

    public function testRefusalLowersTheReliabilityScore(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $seller = $f->enterprise('Vendeur');
        $order = $f->order($f->enterprise('Acheteur'), $seller);

        $this->service($pdo)->refuse($order, $seller, 'Rupture');

        $this->assertSame('refusee', $f->orderStatus($order));
        // (0 livrée + 5) / (1 tranchée + 5) = 83 %
        $this->assertSame(83, $this->score($pdo, $seller));
    }

    public function testReliabilityScoreFollowsDeliveredOverDecidedOrders(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $seller = $f->enterprise('Vendeur');
        $buyer = $f->enterprise('Acheteur');
        foreach (['livree', 'livree', 'livree', 'refusee', 'en_attente', 'expediee'] as $status) {
            $f->order($buyer, $seller, $status);
        }

        (new OrderRepository($pdo))->recalculateSellerReliability($seller);

        // Seules livrées et refusées comptent : (3 + 5) / (4 + 5) = 89 %
        $this->assertSame(89, $this->score($pdo, $seller));
        $this->assertSame(3, (int) $pdo->query("SELECT Nombre_Commandes_Completees FROM Entreprise WHERE Id_Entreprise = $seller")->fetchColumn());
    }

    public function testOverdueUrgentOrdersAreRefusedAutomatically(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $seller = $f->enterprise('Vendeur');
        $buyer = $f->enterprise('Acheteur');
        $past = date('Y-m-d H:i:s', time() - 60);
        $future = date('Y-m-d H:i:s', time() + 3600);

        $expired = $f->order($buyer, $seller, 'en_attente', ['Est_Urgente' => 1, 'Date_Limite_Reponse' => $past]);
        $stillOpen = $f->order($buyer, $seller, 'en_attente', ['Est_Urgente' => 1, 'Date_Limite_Reponse' => $future]);
        $alreadyValidated = $f->order($buyer, $seller, 'validee', ['Est_Urgente' => 1, 'Date_Limite_Reponse' => $past]);
        $notUrgent = $f->order($buyer, $seller, 'en_attente');

        $result = $this->service($pdo)->expireOverdueUrgentOrders();

        $this->assertContains($expired, array_map('intval', array_column($result, 'Id_Commande_B2B')));
        $this->assertSame('refusee', $f->orderStatus($expired));
        $this->assertSame('en_attente', $f->orderStatus($stillOpen));
        $this->assertSame('validee', $f->orderStatus($alreadyValidated));
        $this->assertSame('en_attente', $f->orderStatus($notUrgent));
        $this->assertSame(83, $this->score($pdo, $seller), 'Un délai dépassé compte comme un refus.');

        $history = $pdo->query("SELECT Nouveau_Statut FROM Historique_Commande_B2B WHERE Id_Commande_B2B = $expired")->fetchColumn();
        $this->assertSame('refusee', $history);
    }
}
