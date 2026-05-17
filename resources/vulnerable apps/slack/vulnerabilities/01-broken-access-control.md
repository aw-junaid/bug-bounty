# Broken Access Control - Vulnerable Files and Tests

## Vulnerable files
- `api/v1/messages/send.php` (user impersonation via body `user_id`)
- `api/v1/messages/fetch.php` (read other channels)
- `api/v1/messages/delete.php` (delete any message)
- `api/v1/channels/join.php` (join private channels)
- `api/v1/channels/list.php` (unauthenticated channel listing)
- `api/v1/files/download.php` (download any file by `file_id`)
- `api/v1/users/profile.php` (read/update other user profile)
- `api/internal/debug.php` and `api/internal/logs.php` (no authentication)

## How to test
1. Login as normal user and save token/session.
2. Try cross-user actions:
   - Send message with another user's `user_id`.
   - Delete another user's `message_id`.
   - Fetch messages from channel IDs you are not a member of.
   - Read/modify another profile using `user_id` parameter.
3. Access `/api/internal/debug.php` and `/api/internal/logs.php` without auth.
