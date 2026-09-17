<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Database\PdoFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base pour les tests d'intégration qui frappent la vraie base MySQL de dev (pas de
 * base de test séparée : les triggers, verrous FOR UPDATE et colonnes générées sont
 * spécifiques à MySQL, l'intérêt est de tester contre le moteur réel).
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
}
