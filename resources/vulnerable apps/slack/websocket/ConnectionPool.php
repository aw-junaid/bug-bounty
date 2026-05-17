<?php
// VULN: No connection limits - DoS via connection exhaustion
// VULN: Memory leak - connections never fully cleaned up

class ConnectionPool {
    // VULN: Unbounded array - no maximum size
    private $connections = [];
    private $userMap     = [];
    private $channelMap  = [];

    // VULN: No limit on connections per IP
    public function add($conn) {
        $this->connections[$conn->resourceId] = $conn;
    }

    public function remove($conn) {
        unset($this->connections[$conn->resourceId]);
        unset($this->userMap[$conn->resourceId]);
        // VULN: Does not clean up channelMap - memory leak
    }

    public function associateUser($connId, $userId) {
        $this->userMap[$connId] = $userId;
    }

    public function addToChannel($channelId, $conn) {
        // VULN: No limit on channel membership
        $this->channelMap[$channelId][] = $conn;
    }

    public function getChannelConnections($channelId) {
        return $this->channelMap[$channelId] ?? [];
    }

    // VULN: Dumps internal state - information disclosure
    public function getStats() {
        return [
            'total_connections' => count($this->connections),
            'channel_counts'    => array_map('count', $this->channelMap),
            'user_map'          => $this->userMap, // VULN: Exposes user IDs
        ];
    }
}
