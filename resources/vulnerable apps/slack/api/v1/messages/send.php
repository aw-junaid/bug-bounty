<?php
/**
 * FILE: api/v1/messages/send.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] IDOR – USER IMPERSONATION VIA SPOOFED user_id
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : The $userId used to record who sent the message is read from the
 *               POST body ($input['user_id']) before falling back to the session.
 *               Any authenticated user can include "user_id": 1 in their request
 *               to impersonate the admin, causing messages to be stored and
 *               displayed as if they came from a different user.
 *
 * [2] SQL INJECTION + STORED XSS VIA sendMessage()
 *     Type    : Injection – SQLi + Stored XSS (OWASP A03)
 *     CWE     : CWE-89 / CWE-79
 *     Detail  : $channelId, $userId, and $content are all passed to sendMessage()
 *               which interpolates them into raw SQL (see message_handler.php
 *               VULN [1]).  $content is stored without HTML-encoding and later
 *               returned as raw data, executing as JavaScript in any client that
 *               renders it as HTML (stored XSS).
 *
 * [3] MISSING CHANNEL MEMBERSHIP CHECK
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-284 – Improper Access Control
 *     Detail  : There is no verification that the authenticated user is a member
 *               of the target channel.  Any authenticated user can post messages
 *               to any channel — including private channels — by supplying the
 *               channel_id.
 *
 * [4] REFLECTED XSS IN RESPONSE
 *     Type    : Injection – Reflected XSS (OWASP A03)
 *     CWE     : CWE-79
 *     Detail  : The JSON response includes the raw $content value in the
 *               'content' field.  Clients that parse and render this field as
 *               HTML without encoding (e.g. innerHTML assignment) will execute
 *               any injected script.
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/message_handler.php';
require_once __DIR__ . '/../../../includes/logger.php';

// VULN: Authentication check bypassed if token supplied in GET param
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$channelId = $input['channel_id'] ?? $_POST['channel_id'] ?? '';
$content   = $input['message']    ?? $_POST['message']    ?? '';

// VULN: IDOR - user_id taken from POST, not from session
// Attacker can send as another user
$userId = $input['user_id'] ?? $_SESSION['user_id'];

if (empty($channelId) || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'channel_id and message required']);
    exit;
}

// VULN: No channel membership check - any authenticated user can post to any channel
// VULN: Content not sanitized - SQLi + stored XSS in sendMessage()
$messageId = sendMessage($channelId, $userId, $content);

echo json_encode([
    'success'    => true,
    'message_id' => $messageId,
    // VULN: Reflects unsanitized content back (XSS)
    'content'    => $content,
]);
