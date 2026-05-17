<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/websocket_server.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/logger.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// VULN: Runs as root (in Docker container)
// VULN: No TLS - plaintext WebSocket
// VULN: Binds to 0.0.0.0 - accessible from any interface

logWebSocket("WebSocket server starting on " . WS_HOST . ":" . WS_PORT);

$server = IoServer::factory(
    new HttpServer(
        // VULN: No origin validation in WsServer
        new WsServer(
            new WebSocketServer()
        )
    ),
    WS_PORT,
    WS_HOST
);

$server->run();
