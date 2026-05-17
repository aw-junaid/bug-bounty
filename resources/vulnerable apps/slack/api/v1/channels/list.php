<?php
/**
 * FILE: api/v1/channels/list.php
 * =============================================================================
 * VULNERABILITY SUMMARY
 * =============================================================================
 *
 * [1] MISSING AUTHENTICATION – UNAUTHENTICATED ACCESS ALLOWED
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-306 – Missing Authentication for Critical Function
 *     Detail  : The requireLogin() call is commented out, meaning any anonymous
 *               HTTP client can list all channels.  This endpoint was presumably
 *               meant to be authenticated; the omission is an access-control
 *               failure.
 *
 * [2] INFORMATION DISCLOSURE – ALL CHANNELS INCLUDING PRIVATE ONES RETURNED
 *     Type    : Broken Access Control (OWASP A01)
 *     CWE     : CWE-200 – Exposure of Sensitive Information to an Unauthorized Actor
 *     Detail  : listAllChannels() returns every channel row with no filtering
 *               based on channel visibility or the requesting user's membership.
 *               Private channel names, descriptions, and creator user IDs are
 *               exposed to unauthenticated callers, leaking organizational
 *               structure and providing channel_id values for further IDOR
 *               attacks (join a private channel, read its messages, etc.).
 * =============================================================================
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/channel_handler.php';

// VULN: No authentication required to list channels
// requireLogin();  // VULN: Commented out - unauthenticated access

// VULN: Information disclosure - returns all channels including private ones
$channels = listAllChannels();

// VULN: Returns created_by user_id - may be used for further IDOR
echo json_encode([
    'success'  => true,
    'channels' => $channels,
    'count'    => count($channels),
]);
