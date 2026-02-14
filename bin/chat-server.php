<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Chat;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/db.php';
require dirname(__DIR__) . '/src/Chat.php';

$port = 8080;
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat($pdo)
        )
    ),
    $port
);

echo "Chat server started on port $port...\n";

$server->run();
