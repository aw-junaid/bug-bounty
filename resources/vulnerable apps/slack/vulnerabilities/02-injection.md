# Injection - Vulnerable Files and Tests

## Vulnerable files
- `api/v1/auth/login.php` + `includes/auth.php` (SQL injection)
- `api/v1/messages/send.php` + `includes/message_handler.php` (SQLi + stored XSS)
- `api/v1/messages/fetch.php` and `api/v1/messages/search.php` (SQLi)
- `api/v1/channels/create.php` + `includes/channel_handler.php` (SQLi)
- `api/v1/users/search.php` + `includes/user_handler.php` (SQLi + reflected XSS)
- `api/internal/admin.php` (command injection and arbitrary SQL)

## How to test
1. **SQL injection**: use crafted payloads or `sqlmap` against login/search/message routes.
2. **Stored XSS**: post message content with script payload and reload chat.
3. **Reflected XSS**: pass HTML/JS payloads in search query parameters.
4. **Command injection**: call admin endpoint `action=exec&cmd=id` with default basic auth.
