<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/roles.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

/*
 * Base de test ISOLÉE. Les tests d'intégration ne touchent plus jamais la base de dev :
 * ils utilisent `<DB_NAME>_test` (ou DB_TEST_NAME), recréée vide à chaque lancement avec
 * le schéma exact de la base de dev (tables, clés étrangères, triggers), lu en lecture
 * seule. Chaque test crée ses propres données via Tests\Fixtures.
 */
$devName = (string) ($_ENV['DB_NAME'] ?? 'facturation');
$testName = (string) ($_ENV['DB_TEST_NAME'] ?? $devName . '_test');

if ($testName === $devName || !preg_match('/^[A-Za-z0-9_]+$/', $testName)) {
    fwrite(STDERR, "Base de test invalide ou identique à la base de dev ($testName) : tests interrompus.\n");
    exit(1);
}

$server = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_PORT'] ?? '3306'),
    (string) ($_ENV['DB_USER'] ?? 'root'),
    (string) ($_ENV['DB_PASS'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$server->exec("DROP DATABASE IF EXISTS `$testName`");
$server->exec("CREATE DATABASE `$testName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
$server->exec('SET FOREIGN_KEY_CHECKS = 0');

foreach ($server->query("SHOW FULL TABLES FROM `$devName` WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
    $create = $server->query("SHOW CREATE TABLE `$devName`.`$table`")->fetch(PDO::FETCH_NUM)[1];
    $create = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create);
    $server->exec("USE `$testName`");
    $server->exec($create);
}

foreach ($server->query("SHOW TRIGGERS FROM `$devName`")->fetchAll() as $trigger) {
    $create = $server->query("SHOW CREATE TRIGGER `$devName`.`{$trigger['Trigger']}`")->fetch()['SQL Original Statement'];
    $create = preg_replace('/^CREATE\s+DEFINER=\S+\s+TRIGGER/i', 'CREATE TRIGGER', $create);
    $server->exec("USE `$testName`");
    $server->exec($create);
}

$server->exec('SET FOREIGN_KEY_CHECKS = 1');
$server = null;

$_ENV['DB_NAME'] = $testName;
