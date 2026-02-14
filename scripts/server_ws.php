<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Chat;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
// On s'assure que la classe Chat est bien chargée. 
// Si App\Chat n'est pas trouvé via l'autoloader, on peut inclure le fichier directement.
if (!class_exists('App\Chat')) {
    require __DIR__ . '/src/Chat.php';
}

$port = 8080;

try {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new App\Chat($pdo)
            )
        ),
        $port
    );

    echo "Serveur WebSocket (FactuPro) lancé sur le port $port...\n";
    echo "Prêt à recevoir des messages en temps réel !\n";

    $server->run();
} catch (\Exception $e) {
    echo "Erreur lors du lancement du serveur : " . $e->getMessage() . "\n";
}
