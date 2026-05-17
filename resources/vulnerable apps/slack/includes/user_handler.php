<?php
/**
 * FILE: includes/user_handler.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] IDOR + SQL INJECTION IN getUserProfile()
 *     Type    : Broken Access Control + Injection (OWASP A01/A03)
 *     CWE     : CWE-639 / CWE-89
 *     Detail  : getUserProfile() accepts a caller-supplied $userId and executes
 *               it in a raw SQL string.  No check is performed to verify the
 *               requesting user owns or is authorized to view that profile.
 *               Any authenticated user can enumerate and read all user records,
 *               including email addresses and last-login timestamps.
 *
 * [2] IDOR + PRIVILEGE ESCALATION + SQL INJECTION IN updateUserProfile()
 *     Type    : Broken Access Control + Injection (OWASP A01/A03)
 *     CWE     : CWE-639 / CWE-269 / CWE-89
 *     Detail  : The function accepts any $userId without checking it matches
 *               the session user, so any authenticated user can overwrite any
 *               other account's details.  The 'role' field is included in
 *               $allowedFields, enabling privilege escalation: a regular user
 *               can promote themselves to 'admin' by passing role=admin in the
 *               request body.  All field values are interpolated into SQL,
 *               enabling second-order SQL injection via the UPDATE statement.
 *
 * [3] SQL INJECTION + REFLECTED XSS IN searchUsers()
 *     Type    : Injection – SQLi + XSS (OWASP A03)
 *     CWE     : CWE-89 / CWE-79
 *     Detail  : $searchTerm is interpolated into a LIKE pattern inside the SQL
 *               query without escaping.  A value of  %' UNION SELECT 1,version()
 *               ,3,4-- extracts the DB version.  The search term is also
 *               reflected in the JSON response (query field) without
 *               HTML-encoding, causing reflected XSS in any client that renders
 *               the response as HTML.
 *
 * [4] UNAUTHORIZED PRIVILEGE ESCALATION IN promoteUser()
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-269 – Improper Privilege Management
 *     Detail  : promoteUser() accepts any $userId and $newRole and performs the
 *               UPDATE with no check that the caller holds an admin role.  Any
 *               authenticated user can call this function (e.g. via a crafted
 *               HTTP request) to grant themselves or anyone else administrator
 *               privileges.
 *
 * [5] IDOR – UNAUTHORIZED ACCOUNT DELETION IN deleteUser()
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639
 *     Detail  : deleteUser() deletes the account matching $userId with no
 *               ownership or role check, allowing any authenticated user to
 *               delete any account, including administrator accounts.
 * =============================================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

// VULN: IDOR - getUserById exposes any user's data without authorization check
function getUserProfile($userId) {
    $db = getDbConnection();
    // VULN: String interpolation - SQLi
    $query = "SELECT user_id, username, email, role, created_at, last_login FROM users WHERE user_id = '$userId'";
    logSQL($query);
    $result = $db->query($query);
    return $result->fetch();
}

// VULN: IDOR - any user can update any user's profile
// VULN: Privilege escalation - role parameter not validated
function updateUserProfile($userId, $data) {
    $db = getDbConnection();

    // VULN: Mass assignment - role can be escalated to 'admin' by passing role=admin
    $allowedFields = ['username', 'email', 'role']; // VULN: 'role' should not be user-controlled
    $updates = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowedFields)) {
            // VULN: String interpolation - SQLi
            $updates[] = "$key = '$value'";
        }
    }

    if (empty($updates)) return false;

    // VULN: No ownership check - any authenticated user can update any user_id
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = '$userId'";
    logSQL($sql);
    $db->query($sql);
    return true;
}

// VULN: SQLi + XSS - search term reflected unsanitized
function searchUsers($searchTerm) {
    $db = getDbConnection();
    // VULN: Direct interpolation - SQLi
    $query = "SELECT user_id, username, email, role FROM users
              WHERE username LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%'";
    logSQL($query);
    $result = $db->query($query);
    return $result->fetchAll();
}

// VULN: Privilege escalation - no admin check to promote users
function promoteUser($userId, $newRole) {
    $db = getDbConnection();
    // VULN: No check that the caller is admin
    $db->query("UPDATE users SET role = '$newRole' WHERE user_id = '$userId'");
    return true;
}

// VULN: IDOR - delete any user without admin check
function deleteUser($userId) {
    $db = getDbConnection();
    // VULN: No authorization check
    $db->query("DELETE FROM users WHERE user_id = '$userId'");
    return true;
}
