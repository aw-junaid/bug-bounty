<?php
/**
 * FILE: config/config.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] HARDCODED JWT SECRET
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-321 – Use of Hard-coded Cryptographic Key
 *     Detail  : JWT_SECRET is set to the trivially guessable value "secret123".
 *               Any attacker who sees this file can forge valid JWT tokens for
 *               any user_id / role combination without needing credentials.
 *
 * [2] HARDCODED ENCRYPTION KEY & STATIC IV
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-321 / CWE-329
 *     Detail  : ENCRYPTION_KEY and ENCRYPTION_IV are both hardcoded constants.
 *               A static IV means every identical plaintext produces identical
 *               ciphertext, eliminating semantic security (ECB-mode effect
 *               despite using AES-CBC).
 *
 * [3] DEBUG MODE ENABLED IN PRODUCTION
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-215 – Insertion of Sensitive Information into Debugging Code
 *     Detail  : DEBUG_MODE = true turns on display_errors and E_ALL, exposing
 *               full PHP stack traces, file paths, and variable contents to any
 *               end user who triggers an error.
 *
 * [4] HARDCODED INTERNAL API KEY
 *     Type    : Cryptographic Failure / Security Misconfiguration (OWASP A02/A05)
 *     CWE     : CWE-798 – Use of Hard-coded Credentials
 *     Detail  : INTERNAL_API_KEY is a predictable static string visible to
 *               anyone with source access.  Internal endpoints that rely on this
 *               key offer no real protection.
 *
 * [5] CORS WILDCARD
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-942 – Overly Permissive Cross-domain Whitelist
 *     Detail  : CORS_ORIGIN = '*' allows any origin to make credentialed
 *               cross-site requests, enabling CSRF and cross-origin data theft.
 *
 * [6] UPLOADS STORED IN WEB ROOT WITH PHP EXECUTION ENABLED
 *     Type    : Insecure Design (OWASP A04)
 *     CWE     : CWE-434 – Unrestricted Upload of File with Dangerous Type
 *     Detail  : UPLOAD_DIR points to public/uploads/ which is web-accessible
 *               and PHP execution is not disabled, meaning uploaded .php5 /
 *               .phtml files can be directly executed as PHP (RCE).
 *
 * [7] NO TLS FOR WEBSOCKET
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-319 – Cleartext Transmission of Sensitive Information
 *     Detail  : WS_HOST/WS_PORT configure a plain ws:// WebSocket, transmitting
 *               auth tokens and messages in cleartext over the network.
 * =============================================================================
 */
// VULN: Debug mode enabled in production
// VULN: Weak application secret

// Application settings
define('APP_NAME', 'SlackClone');
define('APP_URL', 'http://localhost');
define('APP_ENV', 'production');

// VULN: Debug mode exposes stack traces to users
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// VULN: Weak, hardcoded JWT secret - easily brutable
define('JWT_SECRET', 'secret123');
define('JWT_EXPIRY', 86400); // 24 hours

// VULN: Short session lifetime not enforced
define('SESSION_LIFETIME', 86400);

// VULN: Hardcoded API keys visible in source
define('INTERNAL_API_KEY', 'internal-debug-key-1234');

// VULN: CORS wildcard
define('CORS_ORIGIN', '*');

// WebSocket settings
// VULN: No TLS for WebSocket
define('WS_HOST', '0.0.0.0');
define('WS_PORT', 8080);

// VULN: Weak encryption key
define('ENCRYPTION_KEY', 'weakkey123456789');
define('ENCRYPTION_IV',  '1234567890abcdef'); // VULN: Hardcoded static IV

// File upload settings
define('MAX_FILE_SIZE', 10485760); // 10MB
// VULN: Uploads stored in web root, PHP execution not disabled
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');
define('UPLOAD_URL', '/public/uploads/');
