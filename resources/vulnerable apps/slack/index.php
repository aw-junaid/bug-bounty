<?php
/**
 * FILE: index.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] NO SECURITY RESPONSE HEADERS
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-16 – Configuration
 *     Detail  : The router never sets any protective HTTP headers
 *               (X-Frame-Options, Content-Security-Policy, X-Content-Type-Options,
 *               Strict-Transport-Security).  Without CSP, any XSS vulnerability
 *               in the application has no browser-enforced containment.
 *
 * [2] NO CSRF PROTECTION ON STATE-CHANGING ROUTES
 *     Type    : Cross-Site Request Forgery (OWASP A01)
 *     CWE     : CWE-352 – Cross-Site Request Forgery
 *     Detail  : No CSRF token is generated or validated anywhere in the router
 *               or in individual endpoints.  Because session cookies lack the
 *               SameSite=Strict/Lax attribute (see session.php), a malicious
 *               third-party page can forge authenticated POST requests on behalf
 *               of a logged-in user (e.g. sending messages, deleting accounts).
 *
 * [3] NO RATE LIMITING ON ANY ROUTE
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-307 – Improper Restriction of Excessive Authentication Attempts
 *     Detail  : All routes, including /login and /register, are processed without
 *               any per-IP or per-account throttling.  Attackers can perform
 *               credential stuffing and brute-force attacks at full network speed.
 *
 * [4] REFLECTED XSS IN 404 HANDLER
 *     Type    : Injection – Reflected XSS (OWASP A03)
 *     CWE     : CWE-79 – Improper Neutralization of Input During Web Page Generation
 *     Detail  : The default case echoes the request URI with htmlspecialchars()
 *               applied, which is partially safe for HTML text context.  However,
 *               if the output is ever moved into an HTML attribute without quotes
 *               or a JavaScript context, htmlspecialchars() alone is insufficient
 *               and XSS remains possible.  The comment "partial fix" acknowledges
 *               this is not a complete remediation.
 *
 * [5] DEBUG MODE EXPOSES ERRORS
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-215 – Insertion of Sensitive Information into Debugging Code
 *     Detail  : config.php enables display_errors = 1 globally (see config.php
 *               VULN [3]).  Any PHP error in any included file will print a full
 *               stack trace — including file paths, variable values, and database
 *               credentials — directly in the HTTP response body.
 * =============================================================================
 */
// When using PHP's built-in server (php -S), route real files directly
// so that API endpoints and static assets are served without going through
// the router switch below.
if (php_sapi_name() === 'cli-server') {
    $requestedFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($requestedFile)) {
        return false;
    }
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/logger.php';

// VULN: No security headers set
// VULN: Debug mode may expose errors directly on this page

initSession();

$requestUri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri  = trim($requestUri, '/');

// VULN: No CSRF protection on state-changing routes
// VULN: No rate limiting on any route

// Basic router
switch ($requestUri) {
    case '':
    case 'index':
        if (empty($_SESSION['logged_in'])) {
            header('Location: /login');
            exit;
        }
        include __DIR__ . '/views/chat.php';
        break;

    case 'login':
        include __DIR__ . '/views/login.php';
        break;

    case 'register':
        include __DIR__ . '/views/register.php';
        break;

    case 'logout':
        destroySession();
        header('Location: /login');
        exit;

    default:
        // VULN: 404 page reflects URI without sanitization - XSS
        http_response_code(404);
        echo "Page not found: " . htmlspecialchars($requestUri); // partial fix
        break;
}
