<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;

final class PdoFactory
{
    public static function fromEnvironment(array $environment): PDO
    {
        $host = (string) ($environment['DB_HOST'] ?? 'localhost');
        $name = (string) ($environment['DB_NAME'] ?? 'facturation');
        $user = (string) ($environment['DB_USER'] ?? 'root');
        $password = (string) ($environment['DB_PASS'] ?? '');

        try {
            return new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );
        } catch (PDOException $exception) {
            error_log('[FactuPro] Database connection failed: ' . $exception->getMessage());
            http_response_code(500);

            if (PHP_SAPI === 'cli') {
                die("Erreur de connexion à la base de données ({$host}/{$name}) : {$exception->getMessage()}" . PHP_EOL);
            }

            die('Erreur de connexion à la base de données.');
        }
    }
}
