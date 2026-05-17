# Security Misconfiguration / File Issues - Vulnerable Files and Tests

## Vulnerable files
- `.htaccess` (directory listing)
- `docker/Dockerfile`, `docker/docker-compose.yml`, `kubernetes/deployment.yaml` (unsafe runtime defaults)
- `api/v1/files/upload.php` + `includes/file_handler.php` (dangerous upload handling)
- `api/v1/files/download.php`, `api/internal/logs.php` (path traversal/LFI style access)

## How to test
1. Browse directories with indexes enabled and inspect exposed files.
2. Upload executable payloads with alternate PHP extensions (`.phtml`, `.php5`).
3. Request traversal payloads (for example `../../etc/passwd`) in download/log endpoints.
4. Review compose/deployment manifests for exposed ports, privileged/risky settings, and weak defaults.
