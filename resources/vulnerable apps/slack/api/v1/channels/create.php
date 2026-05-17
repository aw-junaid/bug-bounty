<?php
/**
 * FILE: api/v1/channels/create.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] SQL INJECTION VIA channel name / description
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : The $name and $description values are forwarded to createChannel()
 *               which interpolates them into a raw INSERT statement without
 *               parameterization (see channel_handler.php VULN [1]).  A
 *               malicious channel name can break out of the SQL string context
 *               and execute arbitrary SQL, including reading the users table or
 *               dropping tables.
 *
 * [2] REFLECTED XSS IN RESPONSE
 *     Type    : Injection – Reflected XSS (OWASP A03)
 *     CWE     : CWE-79 – Improper Neutralization of Input During Web Page Generation
 *     Detail  : The raw $name value is included in the JSON response 'name'
 *               field without HTML-encoding.  A client that renders the returned
 *               name as HTML (e.g. document.getElementById('msg').innerHTML =
 *               data.name) will execute any script tags or event handlers in the
 *               channel name.
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/channel_handler.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input       = json_decode(file_get_contents('php://input'), true);
$name        = $input['name']        ?? $_POST['name']        ?? '';
$description = $input['description'] ?? $_POST['description'] ?? '';

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Channel name required']);
    exit;
}

$userId    = $_SESSION['user_id'];
// VULN: SQLi in createChannel() - name and description interpolated
$channelId = createChannel($name, $description, $userId);

echo json_encode([
    'success'    => true,
    'channel_id' => $channelId,
    'name'       => $name, // VULN: Reflected unsanitized - XSS
]);
