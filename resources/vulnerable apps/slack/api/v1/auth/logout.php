<?php
/**
 * FILE: api/v1/auth/logout.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] SESSION TOKEN NOT REVOKED IN DATABASE
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-613 – Insufficient Session Expiration
 *     Detail  : destroySession() clears the server-side PHP session and removes
 *               the browser cookie, but it does NOT update the session_token or
 *               api_token columns in the users table.  Any intercepted token
 *               (from a log file, proxy, or XSS theft) remains permanently
 *               valid and can be used to authenticate long after the user
 *               believes they have logged out.  The token should be reset to a
 *               new random value (or NULL) in the database on logout.
 *
 * [2] JWT NOT INVALIDATED
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-613
 *     Detail  : JWTs issued at login have a 24-hour validity (JWT_EXPIRY = 86400)
 *               and there is no token revocation list or blacklist.  After logout,
 *               the JWT is still cryptographically valid and will be accepted by
 *               validateJWT() for the remainder of its lifetime.
 * =============================================================================
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/logger.php';

// VULN: Session not properly invalidated server-side
// VULN: Token not revoked in database
initSession();

$userId = $_SESSION['user_id'] ?? 'unknown';
logInfo("User logged out: $userId");

// VULN: Only clears local session - token still valid in DB
destroySession();

// VULN: Does not revoke API token from database
echo json_encode(['success' => true, 'message' => 'Logged out']);
