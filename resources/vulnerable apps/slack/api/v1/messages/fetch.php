<?php
/**
 * FILE: api/v1/messages/fetch.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] IDOR – ANY USER CAN READ ANY CHANNEL'S MESSAGES
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : The endpoint passes the caller-supplied $channelId directly to
 *               getMessages() without checking whether the requesting user is
 *               a member of that channel.  By iterating over channel_id values
 *               (1, 2, 3...), an attacker can read the complete message history
 *               of every channel, including private/direct-message channels.
 *
 * [2] SQL INJECTION VIA channel_id
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : $channelId from $_GET is forwarded to getMessages() which
 *               interpolates it into raw SQL (see message_handler.php VULN [3]).
 *               A UNION injection can exfiltrate the entire database over this
 *               endpoint.  Example:
 *               ?channel_id=1 UNION SELECT username,password_hash,3,4,5 FROM users--
 *
 * [3] STORED XSS – UNSANITIZED MESSAGE CONTENT RETURNED
 *     Type    : Injection – Stored XSS (OWASP A03)
 *     CWE     : CWE-79 – Improper Neutralization of Input During Web Page Generation
 *     Detail  : getMessages() returns raw database rows including the
 *               message_content column, which was stored without sanitization.
 *               Any JavaScript payload stored by an attacker via send.php is
 *               returned here and will execute in every client that renders the
 *               message_content field as HTML.
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/message_handler.php';

requireLogin();

// VULN: SQLi - channelId passed directly to getMessages()
$channelId = $_GET['channel_id'] ?? '';
$limit     = (int)($_GET['limit'] ?? 50);

if (empty($channelId)) {
    http_response_code(400);
    echo json_encode(['error' => 'channel_id required']);
    exit;
}

// VULN: No membership check - IDOR: any user can fetch any channel's messages
$messages = getMessages($channelId, $limit);

// VULN: Returns raw unsanitized message_content - stored XSS when rendered
echo json_encode(['success' => true, 'messages' => $messages]);
