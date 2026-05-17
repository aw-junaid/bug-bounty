<?php
/**
 * FILE: api/v1/files/upload.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] UNRESTRICTED FILE UPLOAD → REMOTE CODE EXECUTION
 *     Type    : Insecure Design (OWASP A04)
 *     CWE     : CWE-434 – Unrestricted Upload of File with Dangerous Type
 *     Detail  : handleFileUpload() blocks only ".php" extensions but allows
 *               .php5, .phtml, .phar, .shtml, and others that are executed as
 *               PHP by common web-server configurations.  Files are stored in
 *               /public/uploads/ which is directly web-accessible and has PHP
 *               execution enabled.  An attacker uploads a webshell renamed to
 *               shell.phtml and immediately executes OS commands:
 *               curl http://host/public/uploads/TIMESTAMP_shell.phtml?cmd=id
 *
 * [2] PATH TRAVERSAL IN FILENAME
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-22 – Improper Limitation of a Pathname to a Restricted Directory
 *     Detail  : The file is stored as time() . '_' . $file['name'].  Because
 *               $file['name'] is user-controlled, a filename of
 *               ../../config/config.php overwrites the application config file,
 *               effectively granting full application takeover.
 *
 * [3] CLIENT-SUPPLIED MIME TYPE TRUSTED
 *     Type    : Insecure Design (OWASP A04)
 *     CWE     : CWE-345 – Insufficient Verification of Data Authenticity
 *     Detail  : $_FILES['file']['type'] is set by the browser and can be forged.
 *               Submitting Content-Type: image/jpeg with a PHP payload bypasses
 *               any MIME-type check the application might add.  The actual file
 *               content (magic bytes) is never inspected.
 *
 * [4] MISSING CSRF TOKEN CHECK
 *     Type    : Cross-Site Request Forgery (OWASP A01)
 *     CWE     : CWE-352 – Cross-Site Request Forgery
 *     Detail  : No CSRF token is required for the multipart upload POST.  A
 *               malicious page can silently upload files on behalf of an
 *               authenticated victim using a hidden <form> submission.
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/file_handler.php';
require_once __DIR__ . '/../../../includes/message_handler.php';
require_once __DIR__ . '/../../../includes/logger.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file provided']);
    exit;
}

$userId    = $_SESSION['user_id'];
$file      = $_FILES['file'];
$channelId = $_POST['channel_id'] ?? '';

// VULN: No CSRF token check
// VULN: handleFileUpload() trusts extension, not magic bytes
// VULN: Uploads go to web-accessible directory with PHP execution enabled
// VULN: Filename from user-controlled input - path traversal
$result = handleFileUpload($file, $userId);

if ($result['success']) {
    logInfo("File uploaded: {$result['file_name']} by $userId");

    // If a channel was specified, send a message in that channel referencing the file
    if (!empty($channelId)) {
        $content   = "[file:" . $result['file_id'] . ":" . $result['file_path'] . ":" . $result['file_name'] . "]";
        $messageId = sendMessage($channelId, $userId, $content);
        $result['message_id'] = $messageId;
    }

    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode($result);
}
