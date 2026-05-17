<?php
/**
 * FILE: includes/channel_handler.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] SQL INJECTION IN createChannel()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89 – Improper Neutralization of Special Elements in SQL
 *     Detail  : $name, $description, and $userId are interpolated directly into
 *               the INSERT statement.  A malicious channel name such as
 *               ','',''); DROP TABLE channels;-- will break out of the query
 *               and can execute arbitrary SQL commands.
 *
 * [2] SQL INJECTION IN getChannel()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89
 *     Detail  : $channelId is user-supplied and interpolated into the SELECT
 *               query.  An attacker can inject a UNION SELECT to read data from
 *               other tables (e.g. the users table, including password hashes).
 *
 * [3] IDOR + SQL INJECTION IN joinChannel()
 *     Type    : Broken Access Control + Injection (OWASP A01/A03)
 *     CWE     : CWE-639 / CWE-89
 *     Detail  : joinChannel() does not verify whether a channel is marked as
 *               private before adding the requesting user.  Any authenticated
 *               user can join any private channel by simply sending its
 *               channel_id.  Both parameters are also unsafely interpolated into
 *               SQL queries, enabling injection.
 *
 * [4] INFORMATION DISCLOSURE IN listAllChannels()
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-200 – Exposure of Sensitive Information to an Unauthorized Actor
 *     Detail  : All channels, including private ones, are returned without
 *               filtering by the caller's membership or role.  This leaks the
 *               existence and names of private channels to any authenticated user.
 *
 * [5] IDOR – MISSING AUTHORIZATION IN deleteChannel()
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-639
 *     Detail  : deleteChannel() deletes the channel matching $channelId with no
 *               check that the caller is the channel owner or an administrator.
 *               Any authenticated user can destroy any channel, including system
 *               channels.
 *
 * [6] SQL INJECTION IN getUserChannels()
 *     Type    : Injection (OWASP A03)
 *     CWE     : CWE-89
 *     Detail  : $userId is interpolated into the WHERE clause of a JOIN query.
 *               An attacker can manipulate this to access channel membership
 *               data for other users or to perform UNION-based data extraction.
 * =============================================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

// VULN: SQL Injection in channel creation
// VULN: IDOR - no proper ownership verification
function createChannel($name, $description, $userId) {
    $db = getDbConnection();
    // VULN: Direct string interpolation - SQLi
    $query = "INSERT INTO channels (channel_name, description, created_by)
              VALUES ('$name', '$description', '$userId')";
    logSQL($query);
    $db->query($query);
    return $db->lastInsertId();
}

// VULN: IDOR - channelId not validated against user membership
// VULN: SQLi in channelId
function getChannel($channelId) {
    $db = getDbConnection();
    // VULN: String interpolation
    $query = "SELECT * FROM channels WHERE channel_id = '$channelId'";
    logSQL($query);
    $result = $db->query($query);
    return $result->fetch();
}

// VULN: IDOR - any user can join any channel without invitation
function joinChannel($channelId, $userId) {
    $db = getDbConnection();
    // VULN: No check if channel is private
    // VULN: SQLi
    $check = $db->query(
        "SELECT * FROM channel_members WHERE channel_id = '$channelId' AND user_id = '$userId'"
    );
    if ($check->rowCount() > 0) {
        return ['success' => false, 'message' => 'Already a member'];
    }
    $db->query("INSERT INTO channel_members (channel_id, user_id) VALUES ('$channelId', '$userId')");
    return ['success' => true];
}

// VULN: Information disclosure - returns all channels including private
function listAllChannels() {
    $db = getDbConnection();
    // VULN: No filtering for private channels
    $result = $db->query("SELECT channel_id, channel_name, description, created_by, created_at FROM channels");
    return $result->fetchAll();
}

// VULN: IDOR - no authorization check before deleting
function deleteChannel($channelId) {
    $db = getDbConnection();
    // VULN: No ownership/admin check
    $db->query("DELETE FROM channels WHERE channel_id = '$channelId'");
    return true;
}

// VULN: SQLi - userId parameter not sanitized
function getUserChannels($userId) {
    $db = getDbConnection();
    $query = "SELECT c.* FROM channels c
              JOIN channel_members cm ON c.channel_id = cm.channel_id
              WHERE cm.user_id = '$userId'";
    logSQL($query);
    $result = $db->query($query);
    return $result->fetchAll();
}
