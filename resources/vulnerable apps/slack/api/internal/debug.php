<?php
/**
 * FILE: api/internal/debug.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] NO AUTHENTICATION REQUIRED
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-306 – Missing Authentication for Critical Function
 *     Detail  : This endpoint is accessible to any unauthenticated HTTP client.
 *               There is no session check, API key validation, or IP allowlist.
 *               The endpoint sits under /api/internal/ but that path prefix
 *               provides no server-level access restriction.
 *
 * [2] FULL PHP CONFIGURATION DISCLOSURE (phpinfo)
 *     Type    : Security Misconfiguration / Information Disclosure (OWASP A05)
 *     CWE     : CWE-200 – Exposure of Sensitive Information to an Unauthorized Actor
 *     Detail  : phpinfo() outputs the full server configuration including PHP
 *               version (enabling version-specific exploits), loaded extension
 *               list, all php.ini directives, enabled modules, server software
 *               version, and the web-server document root — a complete
 *               fingerprint for attack planning.
 *
 * [3] APPLICATION SECRETS DUMPED VIA get_defined_constants()
 *     Type    : Information Disclosure (OWASP A02)
 *     CWE     : CWE-312 – Cleartext Storage of Sensitive Information
 *     Detail  : get_defined_constants(true)['user'] includes all constants
 *               defined by the application: JWT_SECRET, ENCRYPTION_KEY,
 *               ENCRYPTION_IV, INTERNAL_API_KEY, database DSN values, etc.
 *               An attacker who hits this endpoint obtains every secret needed
 *               to forge JWTs, decrypt ciphertexts, and authenticate to the DB.
 *
 * [4] ENVIRONMENT VARIABLE DISCLOSURE
 *     Type    : Information Disclosure (OWASP A02)
 *     CWE     : CWE-214 – Invocation of Process Using Visible Sensitive Information
 *     Detail  : $_ENV and getenv() are printed in full.  In cloud/container
 *               environments, environment variables frequently contain cloud
 *               provider credentials (AWS_ACCESS_KEY_ID, DATABASE_URL, API tokens),
 *               giving an attacker lateral movement capability to other services.
 *
 * [5] SERVER VARIABLES DISCLOSURE
 *     Type    : Information Disclosure (OWASP A05)
 *     CWE     : CWE-200
 *     Detail  : $_SERVER contains the HTTP_AUTHORIZATION header (JWT tokens),
 *               DOCUMENT_ROOT, SCRIPT_FILENAME, SERVER_ADDR, SERVER_SOFTWARE,
 *               and other values that aid further attack planning.
 * =============================================================================
 */
// VULN: No authentication required for this internal endpoint
// VULN: Exposes phpinfo() and full configuration
// VULN: Accessible from the internet

header('Content-Type: text/html');

// VULN: Exposes PHP version, loaded modules, config values
phpinfo();

echo '<hr>';
echo '<h2>Application Config</h2>';
echo '<pre>';

// VULN: Dumps all defined constants including secrets
$constants = get_defined_constants(true);
print_r($constants['user'] ?? []);

echo '</pre>';

echo '<h2>Environment Variables</h2>';
echo '<pre>';
// VULN: Exposes env vars (may include cloud credentials, API keys)
print_r($_ENV);
print_r(getenv());
echo '</pre>';

echo '<h2>Server Variables</h2>';
echo '<pre>';
print_r($_SERVER);
echo '</pre>';
