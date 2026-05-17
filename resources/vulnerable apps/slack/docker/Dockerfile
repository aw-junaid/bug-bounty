# VULN: Runs as root - all processes inside container run as UID 0
# VULN: Exposes multiple ports including debug port
# VULN: No non-root user created

FROM php:8.2-fpm

# VULN: Running as root (no USER directive)

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    unzip \
    curl \
    nginx \
    && docker-php-ext-install pdo_mysql zip gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY . /var/www/html/

# VULN: World-writable uploads directory
RUN chmod -R 777 /var/www/html/public/uploads
# Ensure app processes can write log files with restricted permissions
RUN chown -R www-data:www-data /var/www/html/logs && chmod -R 775 /var/www/html/logs

RUN composer install --no-interaction 2>/dev/null || true

# VULN: PHP error display enabled
RUN echo "display_errors = On"      >> /usr/local/etc/php/php.ini
RUN echo "display_startup_errors=On" >> /usr/local/etc/php/php.ini
# VULN: SQL logging enabled
RUN echo "log_errors = On"           >> /usr/local/etc/php/php.ini
RUN echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/php.ini

COPY docker/nginx.conf /etc/nginx/sites-available/default

# VULN: Multiple ports exposed including debug (8081)
EXPOSE 80 8080 8081

# Start nginx + websocket server (auto-restart loop) + php-fpm
CMD mkdir -p /var/www/html/logs /var/www/html/public/uploads && chown -R www-data:www-data /var/www/html/logs /var/www/html/public/uploads && chmod -R 775 /var/www/html/logs /var/www/html/public/uploads && service nginx start && (while true; do php websocket/server.php >> /var/www/html/logs/websocket.log 2>&1; sleep 1; done) & php-fpm
