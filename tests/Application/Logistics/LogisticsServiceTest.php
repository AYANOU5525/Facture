<?php

declare(strict_types=1);

namespace Tests\Application\Logistics;

use App\Application\Logistics\LogisticsService;
use App\Infrastructure\Persistence\LogisticsRepository;
use InvalidArgumentException;
use Tests\DatabaseTestCase;

/** Couvre LogisticsService::update() — mise à jour du suivi d'une livraison. */
final class LogisticsServiceTest extends DatabaseTestCase
{
    private function service(\PDO $pdo): LogisticsService
    {
        return new LogisticsService($pdo, new LogisticsRepository($pdo));
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'carrier' => null,
            'tracking' => null,
            'status' => 'traitement',
            'date_expedition' => null,
            'date_prevue' => null,
            'date_livraison' => null,
            'notes' => null,
            'address' => null,
            'latitude' => null,
            'longitude' => null,
            'command_id' => 0,
        ], $overrides);
    }

    public function testRejectsAnInvalidStatus(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $enterprise = $f->enterprise();
        $logistics = $f->logistics($enterprise);

        $this->expectException(InvalidArgumentException::class);
        $this->service($pdo)->update($logistics, $enterprise, $this->baseData(['status' => 'en_orbite']));
    }

    public function testUpdatesTrackingFieldsForAStandaloneDelivery(): void
    {
        $pdo = $this->getPdo();
        $f = $this->fixtures($pdo);
        $enterprise = $f->enterprise();
        $logistics = $f->logistics($enterprise);

        $event = $this->service($pdo)->update($logistics, $enterprise, $this->baseData([
            'carrier' => 'PHPUNIT-Transporteur',
            'tracking' => 'PHPUNIT-TRACK-123',
            'status' => 'expediee',
            'date_expedition' => date('Y-m-d H:i:s'),
        ]));

        // Pas de Commande_B2B liée : aucun évènement de notification à renvoyer.
        $this->assertNull($event);

        $row = $pdo->query("SELECT Transporteur, Numero_Suivi, Statut_Livraison FROM Logistique WHERE Id_Logistique = $logistics")->fetch();
        $this->assertSame('PHPUNIT-Transporteur', $row['Transporteur']);
        $this->assertSame('PHPUNIT-TRACK-123', $row['Numero_Suivi']);
        $this->assertSame('expediee', $row['Statut_Livraison']);
    }
}
