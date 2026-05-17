<?php
// VULN: Hardcoded credentials - never do this in production
// VULN: No SSL/TLS for database connection
// VULN: Credentials visible in version control

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'slack_clone');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');       // VULN: Weak, hardcoded password
define('DB_CHARSET', 'utf8');    // VULN: Should be utf8mb4

function getDbConnection() {
    // VULN: No SSL, no certificate validation
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $options = [
        // VULN: Emulated prepares allows multi-query injection
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // VULN: Error messages exposed to client
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // VULN: Exposes DB connection error details
        die(json_encode(['error' => $e->getMessage()]));
    }
}

// Legacy mysqli connection (used in some older endpoints)
// VULN: Unencrypted connection, hardcoded creds
function getLegacyConnection() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        // VULN: Exposes mysqli connect error
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}
