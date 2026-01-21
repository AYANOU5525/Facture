<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            // Mode d'erreur : exceptions pour faciliter le débogage
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Mode de récupération par défaut : tableau associatif
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Désactiver l'émulation des requêtes préparées (sécurité)
            PDO::ATTR_EMULATE_PREPARES => false,

            // Forcer l'utilisation de vrais types de données
            PDO::ATTR_STRINGIFY_FETCHES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
