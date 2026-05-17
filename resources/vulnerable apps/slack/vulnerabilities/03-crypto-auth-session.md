# Cryptography / Auth / Session - Vulnerable Files and Tests

## Vulnerable files
- `includes/auth.php` (MD5 password hashing, weak comparisons)
- `config/config.php` and frontend JS files (hardcoded/unsafe token handling)
- `includes/session.php` (session fixation, insecure cookie settings)
- `api/v1/auth/logout.php` (token not revoked)

## How to test
1. Register/login and inspect DB password hashes (`md5` format).
2. Confirm tokens are stored in `localStorage` and exposed to JavaScript.
3. Set a custom `PHPSESSID` before login and verify session fixation behavior.
4. Logout, then retry authenticated calls using old token/JWT to verify reuse.
