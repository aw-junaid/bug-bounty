<?php
/**
 * FILE: api/internal/logs.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] NO AUTHENTICATION REQUIRED
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-306 – Missing Authentication for Critical Function
 *     Detail  : Any unauthenticated HTTP client can read the application's log
 *               files by requesting this endpoint.  The /api/internal/ path
 *               prefix has no server-level access restriction; there is no
 *               session check or API key validation.
 *
 * [2] PATH TRAVERSAL VIA 'type' PARAMETER
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-22 – Improper Limitation of a Pathname to a Restricted Directory
 *     Detail  : When $logType does not match a known key, the path is constructed
 *               as LOG_PATH . '/' . $logType . '.log' with no sanitization.  An
 *               attacker can supply type=../../etc/passwd (stripping the .log
 *               extension with a null-byte on some older PHP versions, or using
 *               the known extension by reading /etc/passwd.log if it exists) or
 *               target known-path files.  Even without null-byte tricks, partial
 *               path traversal can expose readable files inside the log tree.
 *
 * [3] SENSITIVE DATA INSIDE LOG FILES
 *     Type    : Information Disclosure (OWASP A09)
 *     CWE     : CWE-532 – Insertion of Sensitive Information into Log File
 *     Detail  : The SQL log (sql.log) contains full raw query strings with
 *               user-supplied values — including message content, usernames, and
 *               passwords entered at login.  The WebSocket log (websocket.log)
 *               contains session tokens logged on connection open, effectively
 *               giving an attacker a list of all currently valid session tokens.
 *
 * [4] UNBOUNDED FILE READ
 *     Type    : Denial of Service / Information Disclosure (OWASP A05)
 *     CWE     : CWE-770 – Allocation of Resources Without Limits or Throttling
 *     Detail  : file_get_contents($path) returns the entire file with no size
 *               limit.  A busy production system may generate multi-gigabyte log
 *               files.  Repeatedly requesting this endpoint can exhaust server
 *               memory, cause an out-of-memory crash, or at minimum produce a
 *               very large response that exhausts attacker-side resources during
 *               exfiltration.
 * =============================================================================
 */
// VULN: No authentication required
// VULN: Exposes sensitive log contents over HTTP

header('Content-Type: text/plain');

require_once __DIR__ . '/../../config/constants.php';

$logType = $_GET['type'] ?? 'app';

// VULN: Path traversal via type parameter
$logFiles = [
    'app'       => APP_LOG,
    'errors'    => ERROR_LOG,
    'sql'       => SQL_LOG,       // VULN: Contains SQL queries with user data
    'websocket' => WEBSOCKET_LOG, // VULN: Contains auth tokens and messages
];

if (isset($logFiles[$logType])) {
    $path = $logFiles[$logType];
} else {
    // VULN: Direct path traversal - ?type=../../etc/passwd
    $path = LOG_PATH . '/' . $logType . '.log';
}

if (file_exists($path)) {
    // VULN: No truncation - may return MBs of sensitive data
    echo file_get_contents($path);
} else {
    echo "Log file not found: $path";
}
