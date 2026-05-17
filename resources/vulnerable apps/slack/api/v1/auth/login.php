<?php
/**
 * FILE: api/v1/auth/login.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] SQL INJECTION VIA loginUser()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : The $username value from the JSON/POST body is passed to
 *               loginUser() which interpolates it directly into a raw SQL query
 *               (see includes/auth.php).  A payload of  ' OR '1'='1'--  bypasses
 *               authentication entirely and logs in as the first user in the
 *               database (typically admin).  sqlmap can dump the full database
 *               with --level=5 --risk=3.
 *
 * [2] NO RATE LIMITING / BRUTE FORCE PROTECTION
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-307 – Improper Restriction of Excessive Authentication Attempts
 *     Detail  : There is no per-IP or per-account attempt counter, CAPTCHA, or
 *               lockout mechanism.  An attacker can submit millions of
 *               username/password pairs per second (credential stuffing) without
 *               any throttling.
 *
 * [3] CORS WILDCARD WITH CREDENTIALS
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-942 – Overly Permissive Cross-domain Whitelist
 *     Detail  : Access-Control-Allow-Origin: * combined with
 *               Access-Control-Allow-Credentials: true violates the CORS
 *               specification (browsers block credentialed wildcard responses)
 *               but signals that the developer intended to allow all origins.
 *               Any same-origin misconfiguration could expose session tokens to
 *               attacker-controlled pages.
 *
 * [4] TOKEN AND JWT RETURNED IN RESPONSE BODY
 *     Type    : Information Disclosure (OWASP A02)
 *     CWE     : CWE-312 – Cleartext Storage of Sensitive Information
 *     Detail  : Both the raw session token and the JWT are returned in the JSON
 *               response body.  If the response is logged by a proxy, CDN, or
 *               application log, the tokens are permanently recorded.
 *
 * [5] USER ENUMERATION VIA VERBOSE ERROR MESSAGE
 *     Type    : Information Disclosure (OWASP A07)
 *     CWE     : CWE-204 – Observable Response Discrepancy
 *     Detail  : On failed login the error message echoes the message from
 *               loginUser() which returns 'Invalid credentials' for both
 *               wrong-password and non-existent-user cases.  However, the
 *               response timing differs because the password comparison step is
 *               skipped for unknown users (timing side channel per VULN in
 *               auth.php).  Combined with no rate limiting, users can be
 *               enumerated.
 * =============================================================================
 */
header('Content-Type: application/json');
// VULN: CORS wildcard with credentials allowed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/logger.php';

// VULN: No rate limiting - brute force / credential stuffing possible
// VULN: SQL injection via loginUser()

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? $_POST['username'] ?? '';
$password = $input['password'] ?? $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password required']);
    exit;
}

// VULN: loginUser() uses raw string interpolation (SQLi)
$result = loginUser($username, $password);

if ($result['success']) {
    initSession();
    setUserSession($result['user']);

    // VULN: Token returned in response body - may be logged
    logInfo("User logged in: $username");
    echo json_encode([
        'success' => true,
        'token'   => $result['token'],
        'jwt'     => $result['jwt'],  // VULN: JWT exposed
        'user'    => [
            'user_id'  => $result['user']['user_id'],
            'username' => $result['user']['username'],
            'role'     => $result['user']['role'],
        ],
    ]);
} else {
    // VULN: Verbose error - reveals whether username exists
    http_response_code(401);
    logInfo("Failed login attempt for: $username");
    echo json_encode(['error' => $result['message']]);
}
