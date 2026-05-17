<?php
/**
 * FILE: api/v1/channels/join.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] IDOR – ANY USER CAN JOIN ANY PRIVATE CHANNEL
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : joinChannel() (in channel_handler.php) inserts the user into
 *               the channel_members table without checking whether the channel
 *               is marked as private, whether the user has an invitation, or
 *               whether the channel owner has approved the join.  Any
 *               authenticated user who knows (or guesses) a private channel's
 *               channel_id can silently join it and read all its messages.
 *
 * [2] SQL INJECTION VIA channel_id
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : $channelId is read from the request body/query-string and
 *               passed to joinChannel() which interpolates it into two separate
 *               SQL queries without parameterization (see channel_handler.php
 *               VULN [3]).  An attacker can inject arbitrary SQL to bypass the
 *               duplicate-membership check or to exfiltrate data.
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/channel_handler.php';

requireLogin();

$input     = json_decode(file_get_contents('php://input'), true);
$channelId = $input['channel_id'] ?? $_POST['channel_id'] ?? $_GET['channel_id'] ?? '';

if (empty($channelId)) {
    http_response_code(400);
    echo json_encode(['error' => 'channel_id required']);
    exit;
}

// VULN: IDOR - joinChannel() does not check if channel is private
// VULN: SQLi via channelId string interpolation
$userId = $_SESSION['user_id'];
$result = joinChannel($channelId, $userId);

echo json_encode($result);
