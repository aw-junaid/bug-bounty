<?php
require_once __DIR__ . '/../config/constants.php';

// VULN: Log injection - user-controlled input written directly to log files
// VULN: Logs stored in web-accessible directory
// VULN: No log rotation - unbounded growth

function writeLog($file, $level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $_SESSION['username'] ?? 'anonymous';

    // VULN: Log injection - newlines in $message can forge log entries
    // VULN: User-controlled $message written verbatim
    $entry = "[$timestamp] [$level] [IP:$ip] [User:$user] $message\n";

    $logDir = dirname($file);
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            error_log($entry);
            return;
        }
    }

    if (!is_writable($logDir)) {
        error_log($entry);
        return;
    }

    if (file_exists($file) && !is_writable($file)) {
        error_log($entry);
        return;
    }

    // VULN: append mode with no locking - race condition possible
    if (file_put_contents($file, $entry, FILE_APPEND) === false) {
        error_log($entry);
    }
}

function logInfo($message) {
    // VULN: Log path under web root - world-readable
    writeLog(APP_LOG, 'INFO', $message);
}

function logError($message) {
    // VULN: Stack traces and sensitive data written to log
    writeLog(ERROR_LOG, 'ERROR', $message);
}

// VULN: All SQL queries including data written to log (credentials, PII)
function logSQL($query) {
    writeLog(SQL_LOG, 'SQL', $query);
}

// VULN: WebSocket messages including auth tokens written to log
function logWebSocket($message) {
    writeLog(WEBSOCKET_LOG, 'WS', $message);
}

// VULN: Logs readable via direct HTTP request (world-readable directory)
function getLogContents($logFile) {
    if (file_exists($logFile)) {
        // VULN: Returns raw log content with no authentication
        return file_get_contents($logFile);
    }
    return '';
}
