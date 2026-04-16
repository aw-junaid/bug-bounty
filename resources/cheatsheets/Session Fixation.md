# Session Fixation

Session fixation attacks force a user to use a session ID known to the attacker, enabling account takeover after the victim authenticates. This attack targets the vulnerability of web applications that accept session identifiers from untrusted sources and fail to regenerate them upon authentication.

## How It Works

Session fixation is a three-step process that exploits improper session management .

1. **Session Set-up**: Attacker obtains a valid session ID from the target site by establishing a connection or generating a session token
2. **Session Fixation**: Attacker tricks victim into using that session ID as their own
3. **Session Entrance**: Victim authenticates with the fixed session; attacker uses the same session ID to access victim's account

Unlike session hijacking, where the attacker steals an existing authenticated session, session fixation occurs *before* authentication, giving the attacker a wider window of opportunity .

## Testing Methodology

### Basic Test

```
1. Open target.com/login (Attacker browser)
2. Note the SESSION cookie value: abc123
3. Open target.com/login in incognito (Victim simulation)
4. Set cookie to attacker's value: abc123
5. Login as victim in incognito tab
6. Refresh attacker's browser
7. If logged in as victim → VULNERABLE
```

### Check if Session Changes on Login

```bash
# Get pre-auth session
curl -c cookies.txt https://target.com/login

# Check cookie value
cat cookies.txt

# Login and check if session changed
curl -b cookies.txt -c cookies2.txt -X POST \
  -d "user=test&pass=test" https://target.com/login

# Compare sessions
diff cookies.txt cookies2.txt
# If same → VULNERABLE
```

### Detailed Exploitation with cURL

The following step-by-step demonstration uses cURL commands to test for session fixation vulnerabilities .

**Step 1: Attacker obtains a valid session ID from the target application**

```bash
# Attacker visits the login page to receive a session cookie
curl -i -c attacker_cookies.txt https://target.com/login
```

The response contains a `Set-Cookie` header. Extract and note the session value:

```
Set-Cookie: sessionid=abc123; Path=/
```

**Step 2: Attacker fixes the victim's session ID**

The attacker crafts a malicious link or uses XSS to set the victim's cookie to `abc123`.

**Step 3: Victim logs in with the fixed session ID**

```bash
# Victim (simulated) logs in using the attacker's session ID
curl -i -c victim_cookies.txt -b "sessionid=abc123" -X POST \
  -d "username=victim&password=password123" \
  https://target.com/login
```

**Step 4: Attacker reuses the authenticated session**

```bash
# Attacker accesses protected resources using the same session ID
curl -i -b "sessionid=abc123" https://target.com/account
```

If the response shows the victim's account data, the application is vulnerable.

**Step 5: Verify session regeneration on login (secure behavior)**

```bash
# Login and capture the new session cookie
curl -i -c after_login_cookies.txt -X POST \
  -d "username=victim&password=password123" \
  https://target.com/login

# Compare with pre-auth cookie
diff attacker_cookies.txt after_login_cookies.txt
```

If the session ID differs, the application properly regenerates sessions.

## Attack Vectors

### Via URL Parameter

Some web applications accept session identifiers as URL parameters instead of or in addition to cookies. This creates multiple fixation opportunities.

```
https://target.com/login?PHPSESSID=attacker_session
https://target.com/login?JSESSIONID=attacker_session
https://target.com/login;jsessionid=attacker_session
```

The attacker sends this link to the victim via email, phishing message, or social media. When the victim clicks the link and logs in, the session ID is already fixed. The attacker can then use the same URL parameter or corresponding cookie value to access the authenticated session .

### Via Meta Tag Injection

If the target application has an HTML injection vulnerability, the attacker can use META tags to set cookies. This method is particularly effective because META tags cannot be disabled by browser settings like JavaScript can .

```html
<meta http-equiv="Set-Cookie" content="sessionid=attacker_session">
```

The injected META tag forces the victim's browser to store the attacker's session ID. This technique works even when the application has XSS filtering that blocks `<script>` tags .

### Via Subdomain Cookie

If an attacker controls a subdomain (e.g., `evil.target.com`), they can set a cookie for the parent domain `.target.com` .

```javascript
// On attacker-controlled subdomain
document.cookie = "sessionid=attacker_session; domain=.target.com; path=/"
```

Due to the way cookie domain scoping works, the browser will send this cookie to all subdomains of `target.com`, including the main login page. This technique is particularly dangerous in cloud environments where user-generated content is hosted on subdomains.

### Via Cross-Site Cooking

The attacker can force the victim's browser to set a cookie by embedding an image or making a request to the target domain.

```html
<img src="https://target.com/page?sessionid=attacker_session">
```

If the application accepts session IDs from URL parameters or sets cookies based on URL parameters, this method can fix the session.

### Via HTTP Response Header Injection

If an attacker can manipulate HTTP response headers (through HTTP Response Splitting or other injection vulnerabilities), they can inject a `Set-Cookie` header .

```
Set-Cookie: sessionid=attacker_session
```

This method allows the attacker to fix a session ID without requiring the victim to click a specially crafted link.

### Persistent Cookie Fixation

For long-term access, the attacker can set a persistent cookie with an extended expiration date .

```javascript
document.cookie = "sessionid=attacker_session; Expires=Fri, 1 Jan 2030 00:00:00 GMT"
```

If the application does not invalidate sessions properly, this technique can keep the session fixed even after the victim restarts their browser or computer.

## Real-World Examples

### GLPI Session Fixation (CVE-2026-23624)

A session fixation vulnerability was discovered in GLPI, a widely-used IT asset management software. When remote authentication via SSO (Single Sign-On) was enabled, an attacker could hijack sessions of other users on shared systems. The vulnerability affected GLPI versions from 0.71 up to 10.0.22 and 11.0.0 through 11.0.4.

The exploitation scenario required physical access to a shared machine where GLPI was accessed:
- A legitimate user authenticated to GLPI via SSO on a shared workstation
- The user's session artifacts persisted after they finished working
- An attacker with physical access to the same machine authenticated and accessed the previous user's session data
- The attacker gained the permissions and access level of the victim user

This vulnerability was patched in versions 10.0.23 and 11.0.5. The root cause was improper session management in GLPI's SSO authentication flow, where the application failed to properly isolate or regenerate session tokens .

### Honeywell XL Web II Controller (CVE-2017-5141)

Honeywell XL Web II controller firmware prior to XL1000C500 XLWebExe-2-01-00 contained a session fixation vulnerability. An attacker could establish a new user session without invalidating existing session identifiers, enabling authenticated session theft. This vulnerability received a CVSS v3 base score of 6.0 (MEDIUM) and was classified under CWE-384 (Session Fixation) .

### IBM Initiate Master Data Service (CVE-2014-4789)

IBM Initiate Master Data Service versions 9.5, 9.7, 10.0, and 10.1 contained a session fixation vulnerability that allowed remote attackers to hijack web sessions. The vulnerability enabled attackers to bypass authentication by fixing a session ID before victim authentication .

### OliveTin Session Invalidation Failure (CVE-2026-30224)

OliveTin, a web interface for executing predefined shell commands, had a session management flaw in versions prior to 3000.11.1. Although the browser cookie was cleared upon logout, the corresponding session remained valid in server storage until expiry (default approximately one year). This created a post-logout authentication bypass where an attacker with a previously captured session cookie could continue authenticating after the victim logged out. This vulnerability combined elements of session fixation with improper session invalidation .

### ScriptCase Critical Vulnerability Chain (CVE-2025-47227)

ScriptCase, a web development tool, contained a vulnerability chain where CVE-2025-47227 enabled unauthenticated password reset via session fixation combined with CAPTCHA bypass. This attack chain required only basic HTTP requests (using cURL) and was accessible to low-skilled attackers. The session fixation component allowed attackers to take over accounts through the password reset flow, demonstrating how session fixation can be combined with other vulnerabilities for complete account compromise .

### Schneider Electric EcoStruxure Power Monitoring Expert

A session fixation vulnerability in Schneider Electric's EcoStruxure Power Monitoring Expert (PME) allowed attackers to set a session ID in advance via the login URL. An attacker could send a crafted link containing a predefined session ID to a victim. When the victim logged in using that link, the attacker could use the same session ID to access the authenticated session. This example is particularly significant because it demonstrates session fixation in industrial control system (ICS) and enterprise environments, not just traditional web applications .

## Indicators of Vulnerability

| Indicator | Status |
| --- | --- |
| Session ID unchanged after login | VULNERABLE |
| Session accepted via URL parameter | VULNERABLE |
| No HttpOnly flag on session cookie | Risk factor |
| Session cookie domain too broad | Risk factor |
| Long session timeout | Risk factor |
| Session ID predictable or sequential | Risk factor |

## Verification Commands

```bash
# Check cookie attributes and session handling
curl -v -c - https://target.com/login 2>&1 | grep -i "set-cookie"

# Check if session ID is accepted via URL parameter
curl -v -b "sessionid=test123" "https://target.com/login?sessionid=test123"

# Verify session regeneration on login
curl -c preauth.txt https://target.com/login
curl -b preauth.txt -c postauth.txt -X POST -d "user=test&pass=test" https://target.com/login
diff preauth.txt postauth.txt

# Test with custom session ID
curl -c cookies.txt -b "sessionid=attacker_controlled" -X POST \
  -d "username=victim&password=password" https://target.com/login
```

Look for:
- HttpOnly flag (mitigates XSS-based fixation but does not prevent URL-based fixation)
- Secure flag
- SameSite attribute
- Domain scope
- Whether session ID changes after login

## Secure Implementation (What to Look For)

- Generate new session ID after authentication
- Invalidate old session on login
- Use HttpOnly and Secure flags
- Implement SameSite=Strict or Lax
- Reject session IDs from URL parameters
- Short session timeouts
- Regenerate session ID on privilege level changes
- Implement server-side session invalidation on logout

## Related Topics

- XSS - Can be used to set cookies and fix sessions
- CSRF - Related session attacks
- Authentication Bypass - Often combined with session fixation
- Session Hijacking - Related attack with different timing and methodology
