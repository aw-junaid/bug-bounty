<?php
/**
 * FILE: api/v1/auth/register.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] WEAK PASSWORD POLICY
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-521 – Weak Password Requirements
 *     Detail  : The only password constraint is a minimum length of 4 characters.
 *               There is no complexity requirement (uppercase, digit, symbol) and
 *               no check against common-password lists.  This allows passwords
 *               like "pass" or "1234", which are cracked in milliseconds.
 *
 * [2] NO EMAIL VALIDATION
 *     Type    : Insecure Design (OWASP A04)
 *     CWE     : CWE-20 – Improper Input Validation
 *     Detail  : The email field is stored as-is with no format validation and
 *               no email-confirmation flow.  Attackers can register with
 *               arbitrary strings or other users' email addresses, facilitating
 *               account enumeration and potential account-takeover via
 *               password-reset flows.
 *
 * [3] NO CAPTCHA / BOT REGISTRATION PROTECTION
 *     Type    : Security Misconfiguration (OWASP A05)
 *     CWE     : CWE-307 – Improper Restriction of Excessive Authentication Attempts
 *     Detail  : Combined with no rate limiting, automated bots can bulk-register
 *               thousands of accounts per minute, which can be used to flood
 *               channels with spam, escalate privileges via IDOR, or perform
 *               other attacks at scale.
 *
 * [4] PASSWORDS STORED WITH MD5 (via registerUser / hashPassword)
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-916 – Use of Password Hash With Insufficient Computational Effort
 *     Detail  : registerUser() calls hashPassword() which uses md5().  Every
 *               registered password is stored as an unsalted MD5 hash, trivially
 *               reversible with rainbow tables.  See includes/auth.php VULN [1].
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? $_POST['username'] ?? '';
$email    = $input['email']    ?? $_POST['email']    ?? '';
$password = $input['password'] ?? $_POST['password'] ?? '';

if (empty($username) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields required']);
    exit;
}

// VULN: Weak password policy (min 4 chars)
// VULN: No email validation
// VULN: No CAPTCHA - bot registration possible
if (strlen($password) < 4) {
    echo json_encode(['error' => 'Password must be at least 4 characters']);
    exit;
}

$result = registerUser($username, $email, $password);

if ($result['success']) {
    logInfo("New user registered: $username ($email)");
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'user_id' => $result['user_id'],
    ]);
} else {
    http_response_code(400);
    echo json_encode(['error' => $result['message']]);
}
