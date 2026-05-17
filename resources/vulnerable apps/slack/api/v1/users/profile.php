<?php
/**
 * FILE: api/v1/users/profile.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] IDOR – ANY USER CAN VIEW ANY PROFILE (GET)
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : The GET handler reads $userId from $_GET['user_id'] with no
 *               check that it matches $_SESSION['user_id'].  By iterating
 *               user_id values (1, 2, 3...) an attacker can enumerate the
 *               email address, role, and login history of every user account.
 *
 * [2] IDOR + PRIVILEGE ESCALATION – ANY USER CAN UPDATE ANY PROFILE (POST)
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 / CWE-269 – Privilege Management
 *     Detail  : The POST handler reads $userId from the request body with no
 *               session ownership check.  Any authenticated user can modify any
 *               other user's account.  Because updateUserProfile() allows the
 *               'role' field (see user_handler.php VULN [2]), an attacker can
 *               set role=admin on their own account or reset another user's
 *               email address to lock them out.
 *
 * [3] SQL INJECTION VIA getUserProfile() / updateUserProfile()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89
 *     Detail  : Both the GET and POST code paths call functions that interpolate
 *               the unsanitized $userId and field values into raw SQL queries
 *               (see user_handler.php).
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/user_handler.php';
require_once __DIR__ . '/../../../includes/logger.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // VULN: IDOR - any user can view any profile by supplying user_id
    $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
    $profile = getUserProfile($userId);

    if (!$profile) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    echo json_encode(['success' => true, 'profile' => $profile]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    // VULN: IDOR - user_id from POST, not from session
    $userId = $input['user_id'] ?? $_SESSION['user_id'];

    // VULN: Mass assignment - 'role' field allows privilege escalation
    unset($input['user_id']);
    $result = updateUserProfile($userId, $input);

    echo json_encode(['success' => $result]);
}
