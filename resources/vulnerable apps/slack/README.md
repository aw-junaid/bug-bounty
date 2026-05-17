# Slack Clone - PHP Security Penetration Testing Testbed

A deliberately vulnerable Slack-like messaging application built with PHP, MySQL, and WebSockets (Ratchet).

> ⚠️ **Warning:** This project intentionally contains security vulnerabilities for legal lab testing and security education only.

## Project Structure

```
project-root/
├── api/                    # REST and internal endpoints
├── config/                 # DB/app constants and runtime settings
├── includes/               # Core handlers (auth, channels, messages, users, files)
├── public/                 # JS/CSS + uploaded files
├── sql/                    # Schema + seed data
├── views/                  # login/register/chat UI
├── websocket/              # Ratchet server and connection handling
├── vulnerabilities/        # Vulnerability-file map + testing guides
├── function.md             # Application functionality map
└── docker/                 # Docker compose and image setup
```

## Quick Start (Docker)

```bash
cd docker
docker-compose up --build
```

If this is your first run or DB init failed before, reset once:

```bash
cd docker
docker-compose down -v
docker-compose up --build
```

### Access
- Web UI: `http://localhost`
- WebSocket endpoint: `ws://localhost:8080`
- Admin endpoint: `http://localhost:8081/api/internal/admin.php`
- MySQL: `localhost:3306`

## Docker Debug Guide

From `docker/`:

```bash
# Show running containers and ports
docker-compose ps

# Follow all logs
docker-compose logs -f

# Follow only app or db logs
docker-compose logs -f app
docker-compose logs -f db

# Check nginx config inside app container
docker-compose exec app nginx -t

# Check PHP-FPM process
docker-compose exec app ps aux | grep php-fpm

# Check nginx process
docker-compose exec app ps aux | grep nginx

# Check app files are present
docker-compose exec app ls -la /var/www/html

# Check DB connectivity from app container
docker-compose exec app sh -lc 'php -r "new PDO(\"mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306};dbname=${DB_NAME:-slack_clone}\",\"${DB_USER:-root}\",\"${DB_PASS:-root}\"); echo \"db-ok\n\";"'

# Check tables in DB container
docker-compose exec db sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "USE slack_clone; SHOW TABLES;"'
```

### Common Docker Issues

- **Nginx 403**: usually wrong web root or missing nginx site config. This repo uses `/var/www/html` with `index.php` routing.
- **`GET /index.php 404`**: usually FastCGI `SCRIPT_FILENAME` mismatch. Verify with `docker-compose exec app nginx -t`.
- **DB not opening**: reset volumes with `docker-compose down -v` so init scripts run again, then confirm with `SHOW TABLES`.
- **Containers start but app still fails**: check `docker-compose logs -f app db` and verify `db` health is `healthy` in `docker-compose ps`. If it is `starting`, wait a bit; if it is `unhealthy`, run `docker-compose logs db` and recreate with `docker-compose down -v && docker-compose up --build`.

### Default credentials
- Admin UI/API basic auth: `admin / admin`
- MySQL root: `root / root`
- App DB user: `slackuser / slackpass`

## Quick Start (Local PHP)

From repository root:

```bash
composer install
mysql -u root -p < sql/schema.sql
php websocket/server.php
php -S localhost:8000 index.php
```

Open `http://localhost:8000`.

## Data Seeding

`sql/schema.sql` now includes:
- default admin and general channel
- additional users/channels/channel memberships
- more than 100 seeded message records across multiple channels
- sample file metadata records

This gives enough sample data for chat browsing, searching, and security testing.

## Documentation Added

- **Functionality map:** `function.md`
- **Vulnerability file guides:**
  - `vulnerabilities/01-broken-access-control.md`
  - `vulnerabilities/02-injection.md`
  - `vulnerabilities/03-crypto-auth-session.md`
  - `vulnerabilities/04-misconfiguration-and-files.md`

## Validation / Checks

Run PHP syntax checks from repo root:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

## Legal Notice

Use only in authorized environments where you have explicit permission to test.
