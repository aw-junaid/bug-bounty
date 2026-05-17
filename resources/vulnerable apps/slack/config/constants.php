<?php
// VULN: Exposed internal paths - information disclosure

// VULN: Absolute filesystem paths visible in source and potentially in errors
define('ROOT_PATH',    dirname(__DIR__));
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOAD_PATH',  ROOT_PATH . '/public/uploads');
define('LOG_PATH',     ROOT_PATH . '/logs');
define('VENDOR_PATH',  ROOT_PATH . '/vendor');

// VULN: Internal network topology exposed
define('DB_INTERNAL_HOST', '10.0.0.5');
define('REDIS_HOST',       '10.0.0.6');
define('REDIS_PORT',       6379);

// VULN: Internal service ports exposed
define('WEBSOCKET_INTERNAL_PORT', 8080);
define('ADMIN_PORT',              8081);

// Log file paths
// VULN: Log paths served under web root
define('APP_LOG',       LOG_PATH . '/app.log');
define('ERROR_LOG',     LOG_PATH . '/errors.log');
define('SQL_LOG',       LOG_PATH . '/sql.log');
define('WEBSOCKET_LOG', LOG_PATH . '/websocket.log');

// VULN: Hardcoded default credentials constant
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin');
