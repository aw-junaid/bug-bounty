<?php
/**
 * FILE: includes/message_handler.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] SQL INJECTION IN sendMessage()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : $channelId, $userId, and $content are concatenated directly into
 *               the INSERT query string using PHP string interpolation.  An
 *               attacker who controls any of these parameters can break out of
 *               the string literal and inject arbitrary SQL, including reading
 *               data from other tables (UNION), modifying data, or dropping
 *               tables (stacked queries if the driver allows).
 *
 * [2] STORED CROSS-SITE SCRIPTING (XSS) IN sendMessage() / getMessages()
 *     Type    : Injection – XSS (OWASP A03)
 *     CWE     : CWE-79 – Improper Neutralization of Input During Web Page Generation
 *     Detail  : $content is stored in the database without sanitization and
 *               returned raw by getMessages().  When a client renders the
 *               message_content field as HTML (e.g. innerHTML), any
 *               <script> tag or event handler stored by an attacker is executed
 *               in every other user's browser who views the channel — a classic
 *               stored/persistent XSS attack.
 *
 * [3] SQL INJECTION IN getMessages()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89
 *     Detail  : $channelId is interpolated into the SELECT query.  The $limit
 *               parameter is cast to int, which is safe, but the channel_id
 *               interpolation allows UNION-based data extraction from the full
 *               database.
 *
 * [4] IDOR – MISSING AUTHORIZATION CHECK IN deleteMessage()
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639 – Authorization Bypass Through User-Controlled Key
 *     Detail  : deleteMessage() only receives a message_id and performs no check
 *               that the calling user owns the message or has admin privileges.
 *               Any authenticated user can delete any message by supplying its
 *               message_id.  No CSRF token is required, compounding the risk.
 *
 * [5] SQL INJECTION + REFLECTED XSS IN searchMessages()
 *     Type    : Injection – SQLi + XSS (OWASP A03)
 *     CWE     : CWE-89 / CWE-79
 *     Detail  : The $query parameter is interpolated into a LIKE clause without
 *               escaping.  A single quote breaks the SQL context (SQLi).  If the
 *               search term is echoed back to the page without HTML-encoding
 *               (which the API endpoint does), it also constitutes reflected XSS.
 *
 * [6] SENSITIVE DATA WRITTEN TO SQL LOG
 *     Type    : Information Disclosure (OWASP A02/A09)
 *     CWE     : CWE-532 – Insertion of Sensitive Information into Log File
 *     Detail  : logSQL() is called with the raw query string, which includes the
 *               full message content.  Log files containing private messages are
 *               accessible without authentication via api/internal/logs.php.
 * =============================================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

// VULN: SQL Injection - message_content inserted without sanitization
// VULN: XSS - message content stored and reflected without escaping
function sendMessage($channelId, $userId, $content) {
    $db = getDbConnection();

    // VULN: Direct string interpolation - SQLi
    $query = "INSERT INTO messages (channel_id, user_id, message_content)
              VALUES ('$channelId', '$userId', '$content')";

    logSQL($query); // VULN: SQL queries with data logged
    $db->query($query);

    return $db->lastInsertId();
}

// VULN: SQLi in channelId parameter + returns unsanitized content (stored XSS)
function getMessages($channelId, $limit = 50) {
    $db = getDbConnection();

    // VULN: String interpolation SQLi
    $query = "SELECT m.*, u.username FROM messages m
              JOIN users u ON m.user_id = u.user_id
              WHERE m.channel_id = '$channelId' AND m.is_deleted = 0
              ORDER BY m.timestamp DESC LIMIT $limit";

    logSQL($query);
    $result = $db->query($query);

    // VULN: Returns raw unsanitized content - reflected to client as-is
    return $result->fetchAll();
}

// VULN: IDOR - no ownership check, any user can delete any message
// VULN: No CSRF token required
function deleteMessage($messageId) {
    $db = getDbConnection();
    // VULN: No authorization check
    $query = "UPDATE messages SET is_deleted = 1 WHERE message_id = $messageId";
    logSQL($query);
    $db->query($query);
    return true;
}

// VULN: XSS in search query reflected back
function searchMessages($query, $channelId) {
    $db = getDbConnection();
    // VULN: SQLi via LIKE with raw input
    $sql = "SELECT m.*, u.username FROM messages m
            JOIN users u ON m.user_id = u.user_id
            WHERE m.channel_id = '$channelId'
              AND m.message_content LIKE '%$query%'
              AND m.is_deleted = 0";

    logSQL($sql);
    $result = $db->query($sql);
    return $result->fetchAll();
}
