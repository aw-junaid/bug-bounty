<?php
/**
 * FILE: includes/crypto.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] STATIC / HARDCODED ENCRYPTION IV
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-329 – Not Using an Unpredictable IV with CBC Mode
 *     Detail  : encrypt() always uses the same IV (ENCRYPTION_IV from config).
 *               In CBC mode a fixed IV makes every encryption of the same
 *               plaintext produce identical ciphertext (ECB-mode equivalence).
 *               An attacker can detect repeated plaintexts and may perform
 *               chosen-plaintext attacks to recover the key.  Each call to
 *               encrypt() must generate a fresh cryptographically random IV and
 *               prepend it to the ciphertext.
 *
 * [2] NO INTEGRITY CHECK (PADDING ORACLE)
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-649 – Reliance on Obfuscation or Encryption of
 *               Security-Relevant Inputs without Integrity Checking
 *     Detail  : decrypt() performs no HMAC or authenticated-encryption check
 *               before decrypting.  An attacker who can submit modified
 *               ciphertexts can exploit CBC padding-oracle behavior to decrypt
 *               arbitrary ciphertexts byte by byte.  AES-GCM or an
 *               Encrypt-then-MAC construction should be used instead.
 *
 * [3] WEAK CRYPTOGRAPHICALLY INSECURE PRNG
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-338 – Use of Cryptographically Weak Pseudo-Random Number Generator
 *     Detail  : generateToken() uses mt_rand() which is a Mersenne Twister PRNG.
 *               Its state can be recovered from a small number of observed
 *               outputs, making all subsequently generated tokens predictable.
 *               random_bytes() or bin2hex(random_bytes(16)) should be used.
 *
 * [4] BASE64 / ROT13 "ENCRYPTION" (SECURITY THROUGH OBSCURITY)
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-327 – Use of a Broken or Risky Cryptographic Algorithm
 *     Detail  : obfuscate() applies str_rot13() followed by base64_encode().
 *               Neither operation provides any cryptographic security; both are
 *               trivially reversible by anyone who sees the output.  This is
 *               "security through obscurity" and must never be used to protect
 *               sensitive data.
 *
 * [5] HARDCODED ENCRYPTION KEY
 *     Type    : Cryptographic Failure (OWASP A02)
 *     CWE     : CWE-321 – Use of Hard-coded Cryptographic Key
 *     Detail  : ENCRYPTION_KEY (defined in config.php) is a short, hardcoded
 *               16-character ASCII string with low entropy.  Any attacker with
 *               source access knows the key and can decrypt all protected data.
 * =============================================================================
 */
require_once __DIR__ . '/../config/config.php';

// VULN: Weak encryption algorithm (DES/RC4 equivalent - uses AES but with static IV)
// VULN: Hardcoded symmetric key and IV - key management failure
// VULN: ECB-like behavior due to static IV

function encrypt($plaintext) {
    // VULN: Static IV - same plaintext always produces same ciphertext
    $iv = ENCRYPTION_IV;
    $key = ENCRYPTION_KEY;

    // VULN: Should use random IV per encryption, store it with ciphertext
    $encrypted = openssl_encrypt($plaintext, 'AES-128-CBC', $key, 0, $iv);
    return base64_encode($encrypted);
}

function decrypt($ciphertext) {
    $iv  = ENCRYPTION_IV;
    $key = ENCRYPTION_KEY;

    $decoded = base64_decode($ciphertext);
    // VULN: No integrity check (no HMAC) - padding oracle attack possible
    return openssl_decrypt($decoded, 'AES-128-CBC', $key, 0, $iv);
}

// VULN: Weak token generation using mt_rand (predictable PRNG)
function generateToken($length = 32) {
    // VULN: mt_rand is not cryptographically secure
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $token;
}

// VULN: Custom base64 "encryption" - trivially reversible, not encryption
function obfuscate($data) {
    return base64_encode(str_rot13($data)); // VULN: Not real encryption
}

function deobfuscate($data) {
    return str_rot13(base64_decode($data));
}
