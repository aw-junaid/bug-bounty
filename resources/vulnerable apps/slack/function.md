# Application Functionality Map

## 1. Core User Flow
- **Register account**: `api/v1/auth/register.php`
- **Login and session issuance**: `api/v1/auth/login.php`
- **Logout**: `api/v1/auth/logout.php`
- **Session/router gating**: `index.php`, `includes/session.php`, `includes/auth.php`

## 2. UI Pages
- **Login page**: `views/login.php`
- **Register page**: `views/register.php`
- **Main chat page**: `views/chat.php`
- **Single entry router**: `index.php`

## 3. Channel Features
- **List channels**: `api/v1/channels/list.php`
- **Create channel**: `api/v1/channels/create.php`
- **Join channel**: `api/v1/channels/join.php`
- **Channel data logic**: `includes/channel_handler.php`

## 4. Messaging Features
- **Send message**: `api/v1/messages/send.php`
- **Fetch messages**: `api/v1/messages/fetch.php`
- **Delete message**: `api/v1/messages/delete.php`
- **Search messages**: `api/v1/messages/search.php`
- **Message data logic**: `includes/message_handler.php`

## 5. File Features
- **Upload file**: `api/v1/files/upload.php`
- **Download file**: `api/v1/files/download.php`
- **File handling/storage logic**: `includes/file_handler.php`

## 6. User/Profile Features
- **Get/update profile**: `api/v1/users/profile.php`
- **Search users**: `api/v1/users/search.php`
- **User data logic**: `includes/user_handler.php`

## 7. Realtime/WebSocket Features
- **WebSocket server bootstrap**: `websocket/server.php`
- **Chat event handling**: `websocket/Chat.php`
- **Connection tracking**: `websocket/ConnectionPool.php`
- **Legacy websocket implementation**: `includes/websocket_server.php`

## 8. Frontend JS Features
- **Main app interactions** (channels, messages, search): `public/js/app.js`
- **WebSocket client handling**: `public/js/websocket.js`
- **File upload interactions**: `public/js/upload.js`

## 9. Configuration and Platform
- **App config/constants**: `config/config.php`, `config/constants.php`
- **Database connection**: `config/database.php`
- **Docker runtime**: `docker/Dockerfile`, `docker/docker-compose.yml`, `docker/mysql/init.sql`
- **Kubernetes manifests**: `kubernetes/*.yaml`

## 10. Logging and Diagnostics
- **Application/SQL/WebSocket logging**: `includes/logger.php`, `logs/*.log`
- **Internal diagnostic endpoints**: `api/internal/debug.php`, `api/internal/logs.php`, `api/internal/admin.php`

## 11. Database Entities Used by the Application
- `users`
- `channels`
- `channel_members`
- `messages`
- `files`

See `sql/schema.sql` for full structure and seeded sample records.
