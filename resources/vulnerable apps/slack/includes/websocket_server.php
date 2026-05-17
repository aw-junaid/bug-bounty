<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/logger.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

// VULN: No connection limits, no authentication enforcement
class WebSocketServer implements MessageComponentInterface {

    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
    }

    // VULN: No origin validation - WebSocket hijacking possible
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        // VULN: Logs connection details including all headers (may contain tokens)
        logWebSocket("New connection: {$conn->resourceId} from {$conn->remoteAddress}");
    }

    // VULN: Trusts user_id from payload - IDOR
    // VULN: No authentication check before broadcasting
    // VULN: No input sanitization - XSS in messages
    public function onMessage(ConnectionInterface $from, $msg) {
        logWebSocket("Message from {$from->resourceId}: $msg"); // VULN: Logs full message

        $data = json_decode($msg, true);

        // VULN: Accepts any action without authorization
        if (isset($data['action']) && $data['action'] === 'send') {
            $payload = json_encode([
                'action'     => 'message',
                'user_id'    => $data['user_id'],    // VULN: Trusts client-supplied user_id
                'channel_id' => $data['channel_id'],
                'content'    => $data['msg'],         // VULN: No sanitization
                'timestamp'  => time(),
            ]);

            // Broadcast to all clients in same channel (no channel membership check)
            foreach ($this->clients as $client) {
                // VULN: Broadcasts to ALL clients, no channel filtering
                $client->send($payload);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        logWebSocket("Connection closed: {$conn->resourceId}");
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        // VULN: Full exception details sent to client
        $conn->send(json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]));
        $conn->close();
    }
}
