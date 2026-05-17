<?php
/**
 * FILE: includes/auth.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] MD5 PASSWORD HASHING
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-916 – Use of Password Hash With Insufficient Computational Effort
 *     Detail  : hashPassword() uses md5() which is not a password-hashing
 *               function.  MD5 is extremely fast and precomputed rainbow tables
 *               exist for billions of common passwords, making offline cracking
 *               trivial.  Passwords should be hashed with bcrypt / argon2id.
 *
 * [2] TIMING ATTACK IN PASSWORD COMPARISON
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-208 – Observable Timing Discrepancy
 *     Detail  : verifyPassword() uses the === operator.  PHP short-circuits
 *               string comparison on the first differing byte, leaking timing
 *               information that lets an attacker iteratively determine the
 *               correct hash.  hash_equals() or password_verify() should be used.
 *
 * [3] WEAK JWT IMPLEMENTATION / ALG:NONE BYPASS
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-347 – Improper Verification of Cryptographic Signature
 *     Detail  : validateJWT() never reads the alg header field.  An attacker
 *               can craft a token with alg:none and an empty signature; the
 *               function will still verify the HMAC against the weak hardcoded
 *               secret "secret123" (see config.php VULN [1]).  Combined, tokens
 *               for any user can be forged.  No expiry (exp) claim is validated.
 *
 * [4] PREDICTABLE SESSION TOKEN
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-338 – Use of Cryptographically Weak PRNG
 *     Detail  : generateSessionToken() builds a token as md5(userId . time()).
 *               Both components are low-entropy and the result is predictable;
 *               an attacker who knows a user_id and the approximate login time
 *               can brute-force the token space in seconds.
 *
 * [5] SQL INJECTION IN loginUser()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : The username variable is interpolated directly into the SQL
 *               query string with no parameterization or escaping.  Input such
 *               as  ' OR '1'='1  bypasses authentication entirely.  The token
 *               UPDATE query at the end of the function is also injectable.
 *
 * [6] SQL INJECTION IN getUserById() / validateToken()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89
 *     Detail  : Both functions use string interpolation ($userId / $token) in
 *               raw SQL queries, allowing an attacker-controlled value to alter
 *               the query logic or exfiltrate data via UNION/error-based SQLi.
 *
 * [7] TOKEN EXPOSED IN GET PARAMETER
 *     Type    : Authentication Failure / Information Disclosure (OWASP A07)
 *     CWE     : CWE-598 – Information Exposure Through Query Strings in GET Request
 *     Detail  : isAuthenticated() accepts a token via $_GET['token'].  GET
 *               parameters are recorded in web-server access logs, browser
 *               history, and HTTP Referer headers, permanently exposing the
 *               session token to anyone with log access.
 *
 * [8] SESSION FIXATION
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-384 – Session Fixation
 *     Detail  : loginUser() does not call session_regenerate_id() after a
 *               successful authentication, leaving the pre-login session ID
 *               unchanged.  An attacker who sets a victim's PHPSESSID before
 *               login can then use that known ID to hijack the authenticated
 *               session.
 *
 * [9] WEAK PASSWORD POLICY
 *     Type    : Authentication Failure (OWASP A07)
 *     CWE     : CWE-521 – Weak Password Requirements
 *     Detail  : isValidPassword() only requires a minimum length of 4 characters
 *               with no complexity requirements, making accounts trivially
 *               brute-forceable.
 * =============================================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// VULN: MD5 password hashing - easily cracked with rainbow tables
function hashPassword($password) {
    return md5($password); // VULN: MD5 is not suitable for passwords
}

// VULN: No constant-time comparison - timing attack possible
function verifyPassword($password, $hash) {
    return md5($password) === $hash;
}

// VULN: Weak JWT implementation with symmetric secret
function generateJWT($payload) {
    $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode($payload));
    // VULN: Uses weak hardcoded secret
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
}

// VULN: No expiry validation, no algorithm check (alg:none attack possible)
function validateJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header, $payload, $signature] = $parts;
    // VULN: Does not check alg field - alg:none bypass possible
    $expected = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if ($signature !== $expected) return false;

    return json_decode(base64_decode($payload), true);
}

// VULN: Predictable token generation
function generateSessionToken($userId) {
    return md5($userId . time()); // VULN: Predictable, not cryptographically random
}

// VULN: Password policy only checks length >= 4
function isValidPassword($password) {
    return strlen($password) >= 4;
}

function registerUser($username, $email, $password) {
    $db = getDbConnection();

    // VULN: No email verification
    // VULN: Weak password policy
    if (!isValidPassword($password)) {
        return ['success' => false, 'message' => 'Password must be at least 4 characters'];
    }

    $passwordHash = hashPassword($password);
    $token = generateSessionToken($username);

    // VULN: No prepared statement in older code path
    $stmt = $db->prepare(
        "INSERT INTO users (username, email, password_hash, session_token) VALUES (?, ?, ?, ?)"
    );
    try {
        $stmt->execute([$username, $email, $passwordHash, $token]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $checkStmt = $db->prepare("SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
            $checkStmt->execute([$username, $email]);
            $existing = $checkStmt->fetch();
            if ($existing) {
                if ($existing['username'] === $username) {
                    return ['success' => false, 'message' => 'Username already exists'];
                }
                if ($existing['email'] === $email) {
                    return ['success' => false, 'message' => 'Email already exists'];
                }
            }
            return ['success' => false, 'message' => 'User already exists'];
        }
        error_log('registerUser failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed'];
    }

    return ['success' => true, 'user_id' => $db->lastInsertId()];
}

function loginUser($username, $password) {
    $db = getDbConnection();

    // VULN: Raw SQL string interpolation - SQL injection
    $query = "SELECT * FROM users WHERE username = '$username' AND is_active = 1";
    $result = $db->query($query);
    $user = $result->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    // VULN: MD5 comparison
    if (!verifyPassword($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    // VULN: Session not regenerated on login - session fixation
    $token = generateSessionToken($user['user_id']);
    $db->query("UPDATE users SET session_token = '$token', last_login = NOW() WHERE user_id = '{$user['user_id']}'");
    $user['session_token'] = $token;

    return [
        'success' => true,
        'user'    => $user,
        'token'   => $token,
        'jwt'     => generateJWT(['user_id' => $user['user_id'], 'role' => $user['role']]),
    ];
}

function getUserById($userId) {
    $db = getDbConnection();
    // VULN: String interpolation - SQL injection
    $result = $db->query("SELECT * FROM users WHERE user_id = '$userId'");
    return $result->fetch();
}

function isAuthenticated() {
    // VULN: Token passed in GET parameter (logged in web server access logs)
    if (isset($_GET['token'])) {
        return validateToken($_GET['token']);
    }
    if (isset($_COOKIE['session_token'])) {
        return validateToken($_COOKIE['session_token']);
    }
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        return validateJWT($token);
    }
    return false;
}

function validateToken($token) {
    $db = getDbConnection();
    // VULN: String interpolation
    $result = $db->query("SELECT * FROM users WHERE session_token = '$token'");
    return $result->fetch();
}
