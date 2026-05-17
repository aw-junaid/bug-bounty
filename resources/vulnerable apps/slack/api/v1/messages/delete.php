<?php
/**
 * FILE: api/v1/messages/delete.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] IDOR – ANY USER CAN DELETE ANY MESSAGE
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : deleteMessage() (in message_handler.php) performs no ownership
 *               check — it soft-deletes the row matching $messageId with no
 *               verification that the requesting user wrote the message or has
 *               moderation privileges.  An attacker who can enumerate or guess
 *               message_id values can delete any message in any channel.
 *
 * [2] MISSING CSRF PROTECTION ON DESTRUCTIVE OPERATION
 *     Type    : Cross-Site Request Forgery (OWASP A01)
 *     CWE     : CWE-352 – Cross-Site Request Forgery
 *     Detail  : A DELETE/POST request to this endpoint requires no CSRF token
 *               and no SameSite cookie protection is in place (see session.php).
 *               A malicious page can silently trigger a delete request using a
 *               form submission or fetch() for any message whose ID can be
 *               guessed (auto-incrementing integer IDs are predictable).
 *
 * [3] message_id ACCEPTED FROM GET PARAMETER
 *     Type    : Insecure Design (OWASP A04)
 *     CWE     : CWE-20 – Improper Input Validation
 *     Detail  : message_id is read from both $_POST and $_GET.  Accepting a
 *               state-changing (destructive) parameter via GET makes CSRF
 *               exploitation trivial via a simple <img src="..."> tag that
 *               the victim's browser loads automatically.
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/message_handler.php';
require_once __DIR__ . '/../../../includes/logger.php';

requireLogin();

// VULN: No CSRF token required for state-changing DELETE operation
// VULN: IDOR - no ownership check in deleteMessage()
$messageId = $_POST['message_id'] ?? $_GET['message_id'] ?? '';

if (empty($messageId)) {
    http_response_code(400);
    echo json_encode(['error' => 'message_id required']);
    exit;
}

// VULN: Any authenticated user can delete any message
$result = deleteMessage($messageId);

logInfo("Message deleted: $messageId by user {$_SESSION['user_id']}");
echo json_encode(['success' => true, 'deleted' => $messageId]);
