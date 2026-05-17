<?php
/**
 * FILE: includes/session.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] SESSION FIXATION
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-384 – Session Fixation
 *     Detail  : initSession() reads PHPSESSID directly from $_GET and passes it
 *               to session_id() before calling session_start().  An attacker
 *               can pre-set a known session ID via a crafted link
 *               (e.g. /login?PHPSESSID=attacker123), wait for the victim to log
 *               in, and immediately reuse that known ID to hijack the
 *               authenticated session.  setUserSession() never calls
 *               session_regenerate_id(true) to issue a fresh, unguessable ID
 *               after authentication.
 *
 * [2] MISSING HttpOnly FLAG ON SESSION COOKIE
 *     Type    : XSS Escalation / Authentication Failure (OWASP A07)
 *     CWE     : CWE-1004 – Sensitive Cookie Without HttpOnly Flag
 *     Detail  : httponly is set to false in both session_set_cookie_params() and
 *               the setcookie() call inside setUserSession().  JavaScript running
 *               in the page (e.g. via stored XSS) can read document.cookie and
 *               steal the session token, fully compromising the victim's account.
 *
 * [3] MISSING Secure FLAG ON SESSION COOKIE
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-614 – Sensitive Cookie in HTTPS Session Without Secure Attribute
 *     Detail  : secure is set to false, meaning the browser will send the session
 *               cookie over plain HTTP connections.  A network attacker (e.g. on
 *               the same Wi-Fi) performing a downgrade or man-in-the-middle
 *               attack can intercept the token in cleartext.
 *
 * [4] WEAK SameSite POLICY
 *     Type    : Cross-Site Request Forgery (OWASP A01/A07)
 *     CWE     : CWE-352 – Cross-Site Request Forgery (CSRF)
 *     Detail  : samesite is set to 'None', which is the most permissive setting.
 *               Cookies are sent on all cross-site requests, enabling CSRF
 *               attacks from any malicious third-party page.
 *
 * [5] WEAK SESSION ID ENTROPY
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-331 – Insufficient Entropy
 *     Detail  : session.entropy_length is reduced to 16 bytes and
 *               session.hash_function is set to md5 (128-bit output), resulting
 *               in weaker, shorter session IDs that are easier to brute-force
 *               compared to PHP defaults.
 *
 * [6] INCOMPLETE SESSION DESTRUCTION
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-613 – Insufficient Session Expiration
 *     Detail  : destroySession() does not call session_regenerate_id(true) prior
 *               to destruction, leaving the old session ID potentially reusable
 *               (race condition).  The session_token cookie is set to expire in
 *               the past but without an explicit path/domain match, browsers may
 *               not delete it consistently.  The token is never revoked in the
 *               database, so replaying it after logout still authenticates.
 * =============================================================================
 */
require_once __DIR__ . '/../config/config.php';

// VULN: Session fixation - session ID not regenerated after login
// VULN: Weak session configuration
function initSession() {
    // VULN: Missing Secure flag (cookies sent over HTTP)
    // VULN: Missing SameSite attribute
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,   // VULN: Should be true in production
        'httponly' => false,   // VULN: JS can read session cookie - XSS escalation
        'samesite' => 'None',  // VULN: Weak SameSite - CSRF risk
    ]);

    // VULN: Weak session ID entropy
    ini_set('session.entropy_length', 16);     // VULN: Too short
    ini_set('session.hash_function', 'md5');   // VULN: MD5 for session IDs

    if (session_status() === PHP_SESSION_NONE) {
        // VULN: session_start() accepts session ID from GET/POST - session fixation
        if (isset($_GET['PHPSESSID'])) {
            session_id($_GET['PHPSESSID']);
        }
        session_start();
    }
}

// VULN: Does not call session_regenerate_id() after login
function setUserSession($user) {
    $sessionToken = (string)($user['session_token'] ?? '');

    // VULN: No session regeneration - session fixation
    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['token']      = $sessionToken;
    $_SESSION['logged_in']  = true;

    // VULN: Token also set in cookie without HttpOnly/Secure
    setcookie('session_token', $sessionToken, [
        'expires'  => time() + SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,  // VULN
        'httponly' => false,  // VULN
        'samesite' => 'None', // VULN
    ]);
}

// VULN: Session data not fully cleared, session ID not regenerated
function destroySession() {
    // VULN: Does not call session_regenerate_id(true) before destroy
    // VULN: Does not unset all session variables properly
    $_SESSION = [];
    // VULN: Cookie not properly invalidated (expiry in the past not set)
    setcookie('session_token', '', time() - 1);
    session_destroy();
}

function requireLogin() {
    initSession();
    if (empty($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? 'guest';
}
