// VULN: Token exposed in WebSocket URL - logged by proxies and web servers
// VULN: No TLS (ws:// not wss://)
// VULN: No origin validation

const WS_HOST = 'localhost';
const WS_PORT = 8080;

// VULN: API token from localStorage passed in WS URL - visible in browser history/logs
const wsToken = localStorage.getItem('api_token');

// VULN: Token in URL - logged by network proxies, server access logs, browser history
const wsUrl = `ws://${WS_HOST}:${WS_PORT}?token=${wsToken}`;

let socket       = null;
let reconnectInt = null;

function connectWebSocket() {
    socket = new WebSocket(wsUrl);

    socket.onopen = () => {
        console.log('[WS] Connected');
        clearInterval(reconnectInt);

        // VULN: Sends user_id and token in plaintext on open
        socket.send(JSON.stringify({
            action:  'auth',
            token:   wsToken,    // VULN: Token sent again in message body
            user_id: localStorage.getItem('user_id'),
        }));

        // Re-join current channel
        if (typeof currentChannelId !== 'undefined' && currentChannelId) {
            socket.send(JSON.stringify({
                action:     'join',
                channel_id: currentChannelId,
            }));
        }
    };

    socket.onmessage = (event) => {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch (e) {
            return;
        }

        if (data.type === 'message') {
            // VULN: Server sends unsanitized content; rendered via innerHTML in app.js
            if (data.channel_id === currentChannelId) {
                renderMessage({
                    username:        data.username,
                    message_content: data.content,  // VULN: Unescaped - XSS via WebSocket
                    timestamp:       data.timestamp * 1000,
                });
            }
        }
    };

    socket.onerror = (err) => {
        // VULN: Full error logged to console - may expose internal details
        console.error('[WS] Error:', err);
    };

    socket.onclose = () => {
        console.log('[WS] Disconnected, reconnecting in 3s...');
        // VULN: Aggressive reconnect - DoS amplification
        reconnectInt = setTimeout(connectWebSocket, 3000);
    };
}

function sendWebSocketMessage(channelId, content) {
    if (!socket || socket.readyState !== WebSocket.OPEN) return;

    socket.send(JSON.stringify({
        action:     'send',
        channel_id: channelId,
        // VULN: user_id from localStorage - IDOR, any user can spoof another
        user_id:    localStorage.getItem('user_id'),
        username:   localStorage.getItem('username'),
        content:    content,  // VULN: Not sanitized before sending
    }));
}

connectWebSocket();
