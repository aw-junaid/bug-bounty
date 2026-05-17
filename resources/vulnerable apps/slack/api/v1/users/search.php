<?php
/**
 * FILE: api/v1/users/search.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] SQL INJECTION VIA SEARCH TERM
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : The ?q= parameter is forwarded to searchUsers() which
 *               interpolates it into a LIKE clause with no escaping or
 *               parameterization (see user_handler.php VULN [3]).  An attacker
 *               can inject a UNION SELECT to dump any table.  Example:
 *               ?q=%' UNION SELECT user_id,password_hash,email,role FROM users--
 *
 * [2] REFLECTED XSS – SEARCH TERM IN JSON RESPONSE
 *     Type    : Injection – Reflected XSS (OWASP A03)
 *     CWE     : CWE-79 – Improper Neutralization of Input During Web Page Generation
 *     Detail  : The raw $q value is included in the 'query' field of the JSON
 *               response without HTML-encoding.  Clients that render this field
 *               as HTML (e.g. "Search results for: " + data.query with innerHTML)
 *               will execute injected JavaScript from the URL, constituting a
 *               reflected XSS attack.
 *
 * [3] INFORMATION DISCLOSURE – ROLE AND EMAIL RETURNED
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-200 – Exposure of Sensitive Information to an Unauthorized Actor
 *     Detail  : The response includes the role and email fields for every
 *               matching user.  This allows attackers to identify administrator
 *               accounts and collect email addresses for phishing or as input
 *               for further IDOR attacks.
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/user_handler.php';

requireLogin();

// VULN: SQLi via searchTerm parameter
// VULN: XSS - search term reflected in response unsanitized
$q = $_GET['q'] ?? '';

if (empty($q)) {
    http_response_code(400);
    echo json_encode(['error' => 'Search query required']);
    exit;
}

$users = searchUsers($q);

// VULN: Returns role and email fields - information disclosure
// VULN: XSS: $q reflected without escaping
echo json_encode([
    'success' => true,
    'query'   => $q,     // VULN: Reflected unsanitized
    'users'   => $users, // VULN: Includes sensitive fields
    'count'   => count($users),
]);
