<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Infrastructure\Database\PdoFactory;

$pdo = PdoFactory::fromEnvironment($_ENV);
