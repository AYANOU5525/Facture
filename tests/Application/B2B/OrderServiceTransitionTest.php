<?php

declare(strict_types=1);

namespace Tests\Application\B2B;

use App\Application\B2B\OrderService;
use App\Infrastructure\Persistence\OrderRepository;
use RuntimeException;
use Tests\DatabaseTestCase;

/**
 * Vérifie que la machine à états des commandes B2B refuse une transition qui ne part
 * pas du statut attendu (ex. : tenter de valider une commande déjà livrée). Comme
 * InvoiceService, transitionForSeller() gère sa propre transaction en interne, donc
 * pas d'enveloppe rollback possible depuis le test. On s'appuie sur des lignes
 * fixture réelles et déjà dans un état "terminal" (livrée / expédiée) : la requête de
 * garde (WHERE ... AND Statut = <attendu>) ne matche jamais, donc aucune écriture n'a
 * lieu — zéro résidu, et on vérifie explicitement que le statut n'a pas bougé.
 */
final class OrderServiceTransitionTest extends DatabaseTestCase
{
    public function testTransitionFailsWhenCurrentStatusDoesNotMatchExpected(): void
    {
        $pdo = $this->getPdo();
        $service = new OrderService($pdo, new OrderRepository($pdo));

        [$orderId, $enterpriseId, $statusBefore] = $this->fetchAnyOrderNotInStatus($pdo, 'en_attente');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/introuvable ou statut incorrect/');

        try {
            // On prétend (à tort) que la commande est "en_attente" pour la faire passer
            // à "validee" — doit être rejeté puisque son vrai statut est différent.
            $service->transitionForSeller($orderId, $enterpriseId, 'en_attente', 'validee');
        } finally {
            $statusAfter = $pdo
                ->query('SELECT Statut FROM Commande_B2B WHERE Id_Commande_B2B = ' . (int) $orderId)
                ->fetchColumn();
            $this->assertSame($statusBefore, $statusAfter, 'Le statut ne doit pas bouger quand la transition est refusée.');
        }
    }

    public function testTransitionFailsForWrongEnterprise(): void
    {
        $pdo = $this->getPdo();
        $service = new OrderService($pdo, new OrderRepository($pdo));

        $row = $pdo->query('SELECT Id_Commande_B2B, Statut, Id_Entreprise_Vendeuse FROM Commande_B2B LIMIT 1')
            ->fetch();
        $this->assertNotFalse($row, 'Précondition : au moins une commande B2B doit exister en fixture.');

        $wrongEnterpriseId = (int) $row['Id_Entreprise_Vendeuse'] + 1_000_000;

        $this->expectException(RuntimeException::class);

        $service->transitionForSeller(
            (int) $row['Id_Commande_B2B'],
            $wrongEnterpriseId,
            (string) $row['Statut'],
            'validee'
        );
    }

    public function testRefuseRejectsAnEmptyReason(): void
    {
        $pdo = $this->getPdo();
        $service = new OrderService($pdo, new OrderRepository($pdo));

        $this->expectException(\InvalidArgumentException::class);

        // Le motif vide doit être rejeté avant toute requête ; l'ID/entreprise n'ont
        // même pas besoin d'exister pour ce cas.
        $service->refuse(999999, 999999, '   ');
    }

    /** @return array{0:int,1:int,2:string} [orderId, enterpriseId, currentStatus] */
    private function fetchAnyOrderNotInStatus(\PDO $pdo, string $excludedStatus): array
    {
        $stmt = $pdo->prepare('SELECT Id_Commande_B2B, Id_Entreprise_Vendeuse, Statut FROM Commande_B2B WHERE Statut != ? LIMIT 1');
        $stmt->execute([$excludedStatus]);
        $row = $stmt->fetch();

        $this->assertNotFalse(
            $row,
            "Précondition : il faut au moins une commande B2B fixture avec un statut différent de '{$excludedStatus}'."
        );

        return [(int) $row['Id_Commande_B2B'], (int) $row['Id_Entreprise_Vendeuse'], (string) $row['Statut']];
    }
}
