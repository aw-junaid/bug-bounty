<?php
/**
 * FILE: api/v1/messages/search.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] SQL INJECTION VIA SEARCH TERM
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : The search query ?q= is forwarded to searchMessages() which
 *               embeds it inside a SQL LIKE clause via string interpolation
 *               (see message_handler.php VULN [5]).  A value such as
 *               %' UNION SELECT username,password_hash,3,4 FROM users--
 *               exfiltrates credentials from the users table through the normal
 *               search results response.
 *
 * [2] SQL INJECTION VIA channel_id
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89
 *     Detail  : The channel_id parameter is also interpolated unsafely into the
 *               same SQL query, providing a second injection point.
 *
 * [3] REFLECTED XSS VIA UNSANITIZED SEARCH RESULTS
 *     Type    : Injection – Reflected XSS (OWASP A03)
 *     CWE     : CWE-79 – Improper Neutralization of Input During Web Page Generation
 *     Detail  : The JSON response includes raw message_content rows from the
 *               database which may contain previously injected XSS payloads
 *               (stored XSS delivery path).  Additionally, if the search term
 *               itself is rendered anywhere in the UI without encoding, it
 *               constitutes reflected XSS.
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/message_handler.php';

requireLogin();

$query     = $_GET['q']          ?? '';
$channelId = $_GET['channel_id'] ?? '';

if (empty($query) || empty($channelId)) {
    http_response_code(400);
    echo json_encode(['error' => 'q and channel_id are required']);
    exit;
}

$messages = searchMessages($query, $channelId);

echo json_encode(['success' => true, 'messages' => $messages]);
