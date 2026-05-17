<?php
/**
 * FILE: api/v1/files/download.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] LOCAL FILE INCLUSION / PATH TRAVERSAL VIA file_path PARAMETER
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-22 – Improper Limitation of a Pathname to a Restricted Directory
 *     Detail  : When ?file_path= is supplied, the raw value is passed directly
 *               to serveFile() (in file_handler.php) which calls file_exists()
 *               and readfile() without any path sanitization or directory
 *               boundary check.  An attacker can read arbitrary files on the
 *               server:
 *               ?file_path=../../etc/passwd
 *               ?file_path=../../config/config.php (leaks all secrets)
 *               ?file_path=../../logs/websocket.log (leaks session tokens)
 *
 * [2] IDOR – ANY USER CAN DOWNLOAD ANY UPLOADED FILE VIA file_id
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : When ?file_id= is supplied, getFilePath() fetches the file
 *               record with no check that the requesting user owns the file or
 *               has been granted access.  By iterating file_id values an
 *               attacker can download all files uploaded by all users.
 *
 * [3] SQL INJECTION VIA file_id
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : $fileId is passed to getFilePath() which interpolates it into a
 *               raw SQL query without casting to int or using a prepared
 *               statement (see file_handler.php VULN [6]).  A value such as
 *               0 UNION SELECT 1,username,password_hash,4,5 FROM users LIMIT 1--
 *               exfiltrates user credentials through the 'file_path' column of
 *               the returned row.
 * =============================================================================
 */
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/file_handler.php';
require_once __DIR__ . '/../../../includes/logger.php';

requireLogin();

// VULN: Path traversal - file parameter not validated
$fileId   = $_GET['file_id']   ?? null;
$filePath = $_GET['file_path'] ?? null; // VULN: Direct path parameter - LFI

if ($fileId) {
    $file = getFilePath($fileId);
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    // VULN: No ownership check - IDOR: any user can download any file
    $path = $file['file_path'];
} elseif ($filePath) {
    // VULN: Direct path traversal - ../../etc/passwd
    $path = $filePath;
} else {
    http_response_code(400);
    echo 'file_id or file_path required';
    exit;
}

// VULN: serveFile() does not validate path is within allowed directory
if (!serveFile($path)) {
    http_response_code(404);
    echo 'File not found';
}
