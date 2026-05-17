<?php
/**
 * FILE: includes/file_handler.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] UNRESTRICTED FILE UPLOAD → REMOTE CODE EXECUTION
 *     Type    : Insecure Design (OWASP A04)
 *     CWE     : CWE-434 – Unrestricted Upload of File with Dangerous Type
 *     Detail  : handleFileUpload() only checks the file extension extracted from
 *               the user-supplied filename and blocks only ".php".  Extensions
 *               such as .php5, .phtml, .phar, .shtml are not blocked and are
 *               executed as PHP by default Apache/Nginx configurations.  An
 *               attacker can rename a PHP webshell to shell.phtml and upload it,
 *               then fetch http://host/public/uploads/TIMESTAMP_shell.phtml?cmd=id
 *               to achieve full remote code execution on the server.
 *
 * [2] CLIENT-SUPPLIED MIME TYPE TRUSTED
 *     Type    : Insecure Design (OWASP A04)
 *     CWE     : CWE-345 – Insufficient Verification of Data Authenticity
 *     Detail  : The MIME type is taken from $_FILES['file']['type'], which is
 *               set by the browser/client and can be forged trivially with a
 *               tool like Burp Suite.  The server never inspects the actual file
 *               magic bytes (e.g. with finfo), so a PHP script submitted with
 *               Content-Type: image/jpeg bypasses any content-type check.
 *
 * [3] PATH TRAVERSAL IN FILE NAMING
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-22 – Improper Limitation of a Pathname to a Restricted Directory
 *     Detail  : The new filename is constructed as time() . '_' . $originalName,
 *               where $originalName is user-controlled.  A filename such as
 *               ../../config/config.php overwrites the application config file
 *               (or any other writable file outside the uploads directory),
 *               potentially escalating to RCE or full application takeover.
 *
 * [4] LOCAL FILE INCLUSION / PATH TRAVERSAL IN serveFile()
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-22 / CWE-98
 *     Detail  : serveFile() calls file_exists() and readfile() on the $filePath
 *               argument without validating that the path is inside the allowed
 *               uploads directory (e.g. using realpath() comparison).  An
 *               attacker can pass ?file_path=../../etc/passwd to read arbitrary
 *               server files.
 *
 * [5] IDOR IN getFilePath() – ANY USER CAN DOWNLOAD ANY FILE
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : getFilePath() fetches a file record by $fileId with no check
 *               that the requesting user uploaded the file or has permission to
 *               download it.  An attacker who increments file_id can download
 *               files belonging to other users.
 *
 * [6] SQL INJECTION IN getFilePath()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89
 *     Detail  : $fileId is interpolated directly into the SELECT query without
 *               being cast to an integer or bound as a prepared-statement
 *               parameter, enabling SQL injection.
 * =============================================================================
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

// VULN: Unrestricted file upload - no MIME type validation beyond extension
// VULN: PHP execution not disabled in upload directory
// VULN: Path traversal in file naming
function handleFileUpload($file, $userId) {
    // VULN: Only checks file extension, not actual content/magic bytes
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];

    $originalName = $file['name'];
    $tmpPath      = $file['tmp_name'];
    $fileSize     = $file['size'];
    $mimeType     = $file['type']; // VULN: Trusts client-supplied MIME type

    // VULN: Extension extracted from user-controlled filename
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // VULN: Blacklist bypass - .php5, .phtml, .phar not blocked
    if (!in_array($ext, $allowedExtensions)) {
        // VULN: Only blocks common PHP extension, not php5/phtml/phar
        if ($ext === 'php') {
            return ['success' => false, 'message' => 'PHP files not allowed'];
        }
    }

    // VULN: Predictable filename - original name partially preserved
    $newName = time() . '_' . $originalName;  // VULN: Path traversal via ../

    // VULN: No sanitization of filename - path traversal possible
    $uploadPath = UPLOAD_DIR . $newName;

    if (move_uploaded_file($tmpPath, $uploadPath)) {
        // VULN: Stores raw path including user-controlled filename
        $db = getDbConnection();
        $stmt = $db->prepare(
            "INSERT INTO files (user_id, file_path, original_name, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $uploadPath, $originalName, $mimeType, $fileSize]);

        return [
            'success'   => true,
            'file_id'   => $db->lastInsertId(),
            'file_path' => UPLOAD_URL . $newName,
            'file_name' => $originalName,
        ];
    }

    return ['success' => false, 'message' => 'Upload failed'];
}

// VULN: Path traversal - allows reading arbitrary files
function getFilePath($fileId) {
    $db = getDbConnection();
    // VULN: Integer not sanitized
    $result = $db->query("SELECT * FROM files WHERE file_id = $fileId");
    return $result->fetch();
}

// VULN: Local File Inclusion via path traversal
function serveFile($filePath) {
    // VULN: No validation that path is within allowed directory
    // VULN: Allows reading /etc/passwd via ../../etc/passwd
    if (file_exists($filePath)) {
        $mime = mime_content_type($filePath);
        header("Content-Type: $mime");
        readfile($filePath);
        return true;
    }
    return false;
}
