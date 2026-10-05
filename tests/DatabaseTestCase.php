<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Database\PdoFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base pour les tests d'intégration MySQL (triggers, verrous FOR UPDATE et colonnes
 * générées sont spécifiques à MySQL : on teste contre le moteur réel).
 *
 * La connexion vise la base de test ISOLÉE recréée par tests/bootstrap.php — jamais la
 * base de dev. Les données se créent dans chaque test via fixtures().
 *
 * Chaque test récupère sa propre connexion via getPdo() — ne pas partager entre tests
 * pour éviter qu'une transaction laissée ouverte par un test fuite vers le suivant.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected function getPdo(): PDO
    {
        return PdoFactory::fromEnvironment($_ENV);
    }

    protected function fixtures(PDO $pdo): Fixtures
    {
        return new Fixtures($pdo);
    }
}
