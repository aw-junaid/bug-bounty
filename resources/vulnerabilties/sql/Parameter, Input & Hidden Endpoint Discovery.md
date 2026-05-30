# Parameter, Input & Hidden Endpoint Discovery
## Complete Workflow — Starting From Only a Domain (example.com)

> **Goal:** Given nothing but `example.com`, systematically uncover every URL, endpoint, parameter, hidden input, and injectable surface the application exposes.

---

## Table of Contents

1. [Mental Model — The Four Layers](#1-mental-model--the-four-layers)
2. [Phase 0 — Passive Reconnaissance (No Touch)](#2-phase-0--passive-reconnaissance-no-touch)
3. [Phase 1 — Active Crawling & Spidering](#3-phase-1--active-crawling--spidering)
4. [Phase 2 — JavaScript Analysis (Hidden Endpoints Live Here)](#4-phase-2--javascript-analysis-hidden-endpoints-live-here)
5. [Phase 3 — Parameter Discovery](#5-phase-3--parameter-discovery)
6. [Phase 4 — Hidden Endpoint Brute-forcing](#6-phase-4--hidden-endpoint-brute-forcing)
7. [Phase 5 — Authenticated Surface Discovery](#7-phase-5--authenticated-surface-discovery)
8. [Phase 6 — API-Specific Discovery](#8-phase-6--api-specific-discovery)
9. [Phase 7 — Passive Traffic Interception (Proxy)](#9-phase-7--passive-traffic-interception-proxy)
10. [Phase 8 — Historical & Third-Party Sources](#10-phase-8--historical--third-party-sources)
11. [Phase 9 — Parameter Value Fuzzing](#11-phase-9--parameter-value-fuzzing)
12. [Full Tool Chain Summary](#12-full-tool-chain-summary)
13. [Output Organization](#13-output-organization)

---

## 1. Mental Model — The Four Layers

Before touching any tool, understand *where* parameters and endpoints can live:

```
┌─────────────────────────────────────────────────────────────┐
│  LAYER 1: URL STRUCTURE                                      │
│  example.com/users/profile?id=1&tab=settings                │
│              ─────┬──────  ──┬──  ────┬────                 │
│                   │          │        └─ Query parameters    │
│                   │          └──────── Path parameters       │
│                   └─────────────────── Path / Endpoint       │
├─────────────────────────────────────────────────────────────┤
│  LAYER 2: REQUEST BODY                                       │
│  POST /login                                                 │
│  {"username":"x","password":"y","_token":"abc","2fa":"z"}    │
│         ────┬────  ─────┬─────  ────┬──────   ──┬───        │
│             │           │           │            └ hidden    │
│             └───────────┴───────────┴── form/JSON inputs    │
├─────────────────────────────────────────────────────────────┤
│  LAYER 3: HEADERS                                            │
│  X-User-ID: 42                                               │
│  X-Role: user                                                │
│  X-Forwarded-For: 127.0.0.1    ← all injectable surfaces    │
│  Authorization: Bearer <token>                               │
├─────────────────────────────────────────────────────────────┤
│  LAYER 4: HIDDEN / OUT-OF-BAND                               │
│  - Endpoints only called by JavaScript (not linked in HTML)  │
│  - Internal API routes exposed without UI                    │
│  - Admin/debug routes not in sitemap                         │
│  - GraphQL introspection, WebSocket messages                 │
│  - Environment-specific routes (/dev, /staging, /debug)      │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Phase 0 — Passive Reconnaissance (No Touch)

**Goal:** Learn the maximum about the target before sending a single request to their server.  
**Risk to target:** Zero — all data comes from public third-party sources.

### 2.1 DNS & Subdomain Discovery

Subdomains often host separate applications with different (weaker) security postures, different codebases, and unique endpoints.

```bash
# Passive subdomain enumeration — no direct DNS queries to target
subfinder -d example.com -silent -o subdomains.txt

# Certificate transparency logs — every SSL cert ever issued is public
curl "https://crt.sh/?q=%.example.com&output=json" | jq '.[].name_value' | sort -u

# DNSDumpster, Shodan, VirusTotal passive DNS
amass enum -passive -d example.com -o amass_passive.txt

# All in one
cat subdomains.txt amass_passive.txt | sort -u | anew all_subdomains.txt
```

**What you find:** `api.example.com`, `dev.example.com`, `admin.example.com`, `staging.example.com`, `internal.example.com` — each a separate attack surface.

### 2.2 Technology Stack Fingerprinting

Knowing the stack tells you which parameter patterns to expect.

```bash
# Wappalyzer CLI
wappalyzer https://example.com

# whatweb
whatweb -v https://example.com

# Check response headers manually
curl -I https://example.com
# Look for: X-Powered-By, Server, X-Generator, Set-Cookie names (PHPSESSID = PHP, JSESSIONID = Java, etc.)
```

**What you learn from stack:**
- PHP → expect `.php` extensions, `?id=`, `?page=` patterns
- ASP.NET → expect `__VIEWSTATE`, `__EVENTVALIDATION` hidden inputs, `.aspx` extensions
- Ruby on Rails → RESTful routes `/resource/:id`
- Django → CSRF tokens, `/admin/` at common path
- Spring Boot → `/actuator/` endpoints (often leaks internals)

### 2.3 Google Dorking

```
# Find indexed parameters
site:example.com inurl:?
site:example.com inurl:id=
site:example.com inurl:page=
site:example.com inurl:redirect=
site:example.com inurl:url=
site:example.com inurl:token=

# Find exposed files and directories
site:example.com ext:php
site:example.com ext:json
site:example.com ext:env         ← .env files exposed
site:example.com "index of /"

# Find admin panels
site:example.com inurl:admin
site:example.com inurl:dashboard
site:example.com inurl:panel
site:example.com inurl:login

# Find API documentation
site:example.com inurl:swagger
site:example.com inurl:api-docs
site:example.com inurl:openapi
site:example.com filetype:yaml "swagger"
```

### 2.4 Shodan & Censys

```bash
# Find all IPs and open ports for the domain
shodan search "hostname:example.com"

# Find interesting services
shodan search "hostname:example.com" --fields ip_str,port,transport,product

# Censys
censys search "example.com" --index-type hosts
```

**Why this matters for parameters:** Services on non-standard ports (`:8080`, `:8443`, `:9000`) often expose debug interfaces, admin panels, or internal APIs with parameters the main app doesn't show.

---

## 3. Phase 1 — Active Crawling & Spidering

**Goal:** Let automated tools discover all linked pages and extract every URL with parameters from HTML source.

### 3.1 Katana — Modern Fast Crawler

```bash
# Standard crawl
katana -u https://example.com -d 5 -o katana_output.txt

# With JavaScript rendering (finds AJAX endpoints)
katana -u https://example.com -d 5 -js-crawl -o katana_js.txt

# Crawl with custom headers (e.g., if behind auth)
katana -u https://example.com -H "Cookie: session=abc123" -d 5

# Crawl all discovered subdomains at once
cat all_subdomains.txt | katana -d 3 -o all_crawl.txt
```

### 3.2 Gospider

```bash
# Crawl with external link following
gospider -s https://example.com -d 3 -t 20 -o gospider_out/

# Include subdomains
gospider -s https://example.com -d 3 --include-subs

# Output all found URLs
cat gospider_out/* | grep -oP 'https?://[^ ]+' | sort -u
```

### 3.3 Hakrawler

```bash
# Fast, focused on links and parameters
echo "https://example.com" | hakrawler -depth 3 -plain | tee hakrawler.txt

# Get only URLs with parameters
cat hakrawler.txt | grep "?"
```

### 3.4 Extracting Parameters From Crawl Output

```bash
# Combine all crawler output
cat katana_output.txt gospider_out/* hakrawler.txt | sort -u > all_urls.txt

# Extract only URLs that have query parameters
grep "?" all_urls.txt | sort -u > urls_with_params.txt

# Extract just the parameter names
cat urls_with_params.txt | python3 -c "
import sys
from urllib.parse import urlparse, parse_qs
params = set()
for line in sys.stdin:
    qs = urlparse(line.strip()).query
    for key in parse_qs(qs).keys():
        params.add(key)
for p in sorted(params):
    print(p)
" > discovered_params.txt

# OR use unfurl
cat urls_with_params.txt | unfurl --unique keys
```

**Example output at this stage:**

```
id
page
tab
redirect
token
user_id
category
sort
order
format
lang
ref
source
```

---

## 4. Phase 2 — JavaScript Analysis (Hidden Endpoints Live Here)

**Why:** Modern applications are SPA (React/Vue/Angular). Most endpoints are **never linked in HTML** — they only exist in JavaScript files as strings, fetch() calls, or axios requests. Crawlers that don't parse JS miss 60–80% of the attack surface.

### 4.1 Extract All JavaScript Files

```bash
# From crawler output, pull all .js URLs
cat all_urls.txt | grep "\.js" | sort -u > js_files.txt

# Also find JS files directly
katana -u https://example.com -js-crawl | grep "\.js$" >> js_files.txt

# Download all JS files for offline analysis
mkdir js_downloaded
while read url; do
    filename=$(echo "$url" | md5sum | cut -d' ' -f1)
    curl -s "$url" -o "js_downloaded/$filename.js"
done < js_files.txt
```

### 4.2 Extract Endpoints From JavaScript — LinkFinder

```bash
# Scan a single JS file
python3 linkfinder.py -i https://example.com/static/app.js -o cli

# Scan all downloaded JS files
for f in js_downloaded/*.js; do
    python3 linkfinder.py -i "$f" -o cli >> linkfinder_results.txt
done

# Scan entire domain's JS automatically
python3 linkfinder.py -i https://example.com -d -o cli > linkfinder_all.txt
```

### 4.3 SecretFinder — API Keys & Auth Tokens in JS

```bash
# Often reveals internal API base URLs, tokens, hardcoded credentials
python3 SecretFinder.py -i https://example.com -e -o cli

# Results show things like:
# AWS_KEY: AKIAIOSFODNN7EXAMPLE
# API_BASE_URL: https://internal-api.example.com/v2
# JWT_SECRET: mysecretkey
```

### 4.4 Manual JS Analysis Patterns

Open browser DevTools → Sources → Search across all JS files:

```javascript
// Things to search for in JS source:

// API endpoints
fetch("
axios.get("
axios.post("
$.ajax({
XMLHttpRequest
baseURL
apiUrl
API_URL
BASE_URL
endpoint
/api/
/v1/
/v2/
/internal/
/admin/

// Hidden parameters
formData.append(
params: {
data: {
body: JSON.stringify({
headers: {
"X-"            // custom headers
"Authorization"

// Feature flags and debug modes
debug:
isAdmin:
enableFeature
__dev
_internal
```

### 4.5 Automated JS Endpoint Extraction with getJS + LinkFinder Pipeline

```bash
# Step 1: Get all JS file URLs from a domain
getJS --url https://example.com --output js_urls.txt

# Step 2: Process each with linkfinder
cat js_urls.txt | while read url; do
    python3 linkfinder.py -i "$url" -o cli 2>/dev/null
done | grep -E "^/" | sort -u > hidden_endpoints.txt

# Step 3: Prepend base URL
sed 's|^|https://example.com|' hidden_endpoints.txt > full_hidden_endpoints.txt
```

---

## 5. Phase 3 — Parameter Discovery

**Goal:** For each known endpoint, find ALL parameters it accepts — including ones not visible in any form or URL you've seen.

### 5.1 Arjun — HTTP Parameter Discovery

Arjun sends requests with wordlists of parameter names and detects which ones the server responds to differently (changed status code, response size, new content).

```bash
# GET parameter discovery on a single URL
arjun -u https://example.com/search

# POST parameter discovery
arjun -u https://example.com/api/user --post

# JSON body parameter discovery
arjun -u https://example.com/api/login --json

# Discover params on multiple URLs
arjun --urls urls_with_params.txt -o arjun_results.json

# Use a custom wordlist
arjun -u https://example.com/search -w /path/to/params.txt

# Increase threads for speed
arjun -u https://example.com/search -t 10

# Example output:
# [+] Parameters found: id, user, redirect, debug, format, callback
```

### 5.2 x8 — Fast Parameter Brute-Forcer

```bash
# GET
x8 -u "https://example.com/api/search" -w wordlists/params.txt

# POST JSON
x8 -u "https://example.com/api/login" -w wordlists/params.txt --data '{}' -H "Content-Type: application/json"

# Multiple URLs
x8 -u "https://example.com/api/v1/{endpoint}" -w endpoints.txt
```

### 5.3 ParamSpider — Mine Parameters From Web Archives

Queries the Wayback Machine and Common Crawl for historical URLs containing parameters.

```bash
# Mine from archives
paramspider -d example.com -o paramspider_out.txt

# Results include URLs like:
# https://example.com/user?id=FUZZ&role=FUZZ
# https://example.com/admin/search?q=FUZZ&filter=FUZZ&export=FUZZ

# Extract unique parameter names
cat paramspider_out.txt | unfurl --unique keys
```

### 5.4 GAU (Get All URLs) — Aggregates Multiple Sources

```bash
# Pulls from Wayback Machine, Common Crawl, URLScan, AlienVault OTX
gau example.com | tee gau_output.txt

# Filter only URLs with parameters
cat gau_output.txt | grep "?" | sort -u

# Get unique parameter names across all historical URLs
cat gau_output.txt | unfurl --unique keys | sort -u > historical_params.txt
```

### 5.5 Building Your Master Parameter List

```bash
# Combine all parameter discovery sources
cat \
    discovered_params.txt \
    historical_params.txt \
    arjun_results_params.txt \
    | sort -u > MASTER_PARAMS.txt

# How many unique params discovered?
wc -l MASTER_PARAMS.txt
```

---

## 6. Phase 4 — Hidden Endpoint Brute-forcing

**Goal:** Find paths and directories that exist on the server but are not linked anywhere — admin panels, debug interfaces, backup files, API versions.

### 6.1 ffuf — Fast Web Fuzzer

```bash
# Directory brute-force
ffuf -u https://example.com/FUZZ -w /usr/share/wordlists/dirb/common.txt -mc 200,301,302,403

# With extensions
ffuf -u https://example.com/FUZZ -w wordlist.txt -e .php,.asp,.aspx,.json,.xml,.bak,.old

# API versioning discovery
ffuf -u https://example.com/api/FUZZ -w versions.txt
# tries: /api/v1, /api/v2, /api/v3, /api/beta, /api/internal

# Find hidden parameters in a known endpoint (param fuzzing with ffuf)
ffuf -u "https://example.com/search?FUZZ=test" -w params_wordlist.txt -mc 200 -fs 1234
#   -fs 1234 filters out responses of size 1234 (the "no param" baseline)

# Subdomain fuzzing
ffuf -u https://FUZZ.example.com -w subdomains.txt -H "Host: FUZZ.example.com" -mc 200

# POST body fuzzing
ffuf -u https://example.com/api/login \
     -X POST \
     -d '{"username":"admin","password":"FUZZ"}' \
     -H "Content-Type: application/json" \
     -w passwords.txt \
     -mc 200
```

### 6.2 Feroxbuster — Recursive Discovery

```bash
# Recursive — finds directories then crawls inside them
feroxbuster -u https://example.com -w wordlist.txt -r

# With extensions and status filter
feroxbuster -u https://example.com -w wordlist.txt -x php,html,json -s 200 301 302 403

# Force scan depth
feroxbuster -u https://example.com -w wordlist.txt --depth 4
```

### 6.3 Gobuster

```bash
# Directory mode
gobuster dir -u https://example.com -w /usr/share/wordlists/dirbuster/directory-list-2.3-medium.txt -x php,html,js

# DNS subdomain mode
gobuster dns -d example.com -w subdomains.txt

# Virtual host mode (finds vhosts not in DNS)
gobuster vhost -u https://example.com -w vhosts.txt
```

### 6.4 Key Wordlists for Endpoint Discovery

```bash
# SecLists (most comprehensive collection)
# https://github.com/danielmiessler/SecLists

/SecLists/Discovery/Web-Content/common.txt          # 4,614 entries
/SecLists/Discovery/Web-Content/big.txt             # 20,469 entries
/SecLists/Discovery/Web-Content/raft-large-words.txt
/SecLists/Discovery/Web-Content/api/api-endpoints.txt

# API-specific
/SecLists/Discovery/Web-Content/api/api-seen-in-wild.txt
/SecLists/Discovery/Web-Content/swagger.txt

# Parameter names
/SecLists/Discovery/Web-Content/burp-parameter-names.txt   # 6,453 param names
```

### 6.5 What to Look For — Common Hidden Paths

```
/admin                  /administrator          /wp-admin
/api                    /api/v1                 /api/v2
/graphql                /graphiql               /__graphql
/swagger                /swagger-ui             /swagger.json
/api-docs               /openapi.json           /openapi.yaml
/actuator               /actuator/health        /actuator/env
/debug                  /.env                   /config.json
/backup                 /backup.zip             /dump.sql
/phpinfo.php            /info.php               /test.php
/.git                   /.git/config            /.svn
/robots.txt             /sitemap.xml            /crossdomain.xml
/server-status          /server-info
/console                /h2-console             /jolokia
/metrics                /health                 /status
/internal               /private                /secret
```

---

## 7. Phase 5 — Authenticated Surface Discovery

**Goal:** After logging in, the application exposes a much larger surface. Parameters and endpoints only accessible with a valid session must be discovered separately.

### 7.1 Capture Your Session

```bash
# Log in through the browser, copy the session cookie from DevTools → Application → Cookies
# Also note any Authorization: Bearer <token> header used

SESSION="session=eyJhbGciOiJIUzI1NiJ9..."
AUTH_HEADER="Authorization: Bearer eyJhbGciOiJIUzI1NiJ9..."
```

### 7.2 Authenticated Crawl

```bash
# Katana with auth
katana -u https://example.com/dashboard \
       -H "Cookie: $SESSION" \
       -d 5 -js-crawl \
       -o authenticated_urls.txt

# Gospider with auth
gospider -s https://example.com/dashboard \
         -H "Cookie: $SESSION" \
         -d 3 -o auth_crawl/
```

### 7.3 Burp Suite Active Crawler (GUI)

1. Set your browser to proxy through Burp (127.0.0.1:8080)
2. Log in to the application normally
3. Browse every page you can find — click every button, every tab, every dropdown
4. Burp's **Target → Site Map** builds a complete tree of everything visited
5. Right-click any directory → "Spider this host" for automated follow-up crawl

**Key benefit:** Burp captures every request — including XHR/fetch calls made by JavaScript reacting to your clicks — that automated tools miss.

### 7.4 Role-Based Discovery

If the application has multiple roles (user, moderator, admin), repeat the entire discovery process for **each role** separately. Admin roles expose entirely different endpoint sets.

```bash
# Diff the two authenticated crawls to find admin-only endpoints
diff <(sort user_urls.txt) <(sort admin_urls.txt) | grep "^>" > admin_only_endpoints.txt
```

---

## 8. Phase 6 — API-Specific Discovery

### 8.1 OpenAPI / Swagger Discovery

If the app exposes a Swagger/OpenAPI spec, it hands you the complete API documentation including every endpoint, every parameter, every data type — for free.

```bash
# Common swagger locations
curl https://example.com/swagger.json
curl https://example.com/swagger-ui.html
curl https://example.com/api-docs
curl https://example.com/openapi.json
curl https://example.com/openapi.yaml
curl https://example.com/v1/api-docs
curl https://example.com/v2/api-docs
```

Once you have the spec, parse it:

```python
import json, yaml

with open("openapi.json") as f:
    spec = json.load(f)

for path, methods in spec["paths"].items():
    for method, details in methods.items():
        print(f"\n[{method.upper()}] {path}")
        params = details.get("parameters", [])
        for p in params:
            print(f"  Param: {p['name']} ({p.get('in','?')}) required={p.get('required',False)}")
        body = details.get("requestBody", {})
        if body:
            schema = body.get("content",{}).get("application/json",{}).get("schema",{})
            print(f"  Body schema: {schema.get('properties', {}).keys()}")
```

### 8.2 GraphQL Introspection

GraphQL exposes a special `__schema` introspection query that reveals the entire API schema — every type, every query, every mutation, every field name.

```bash
# Check if GraphQL exists
curl https://example.com/graphql
curl https://example.com/api/graphql
curl https://example.com/graphiql

# Run introspection query
curl -X POST https://example.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { queryType { fields { name description args { name type { name kind } } } } } }"}'

# Full introspection with GraphQL Voyager / InQL
# InQL Burp extension — automatically builds entire schema map from introspection
inql -t https://example.com/graphql
```

**What you find:**

```graphql
# Example introspection reveals hidden queries:
query getAdminUsers { ... }       # Not exposed in the UI
mutation deleteUser(id: ID!) { }  # Destructive endpoint
query exportDatabase { ... }      # Dangerous export functionality
```

### 8.3 WSDL (SOAP APIs)

```bash
# SOAP services expose WSDL which describes all methods/parameters
curl https://example.com/service?wsdl
curl https://example.com/api/soap?WSDL

# Parse WSDL with wsdl2py or manual inspection
# Look for <wsdl:operation> tags — each is an endpoint
```

### 8.4 Postman Collections in the Wild

Developers sometimes accidentally publish internal API collections:

```
# Search
site:github.com "example.com" "postman_collection"
site:postman.com/collections "example.com"

# Also check GitHub for leaked collections
github.com search: "example.com" filename:postman_collection.json
```

---

## 9. Phase 7 — Passive Traffic Interception (Proxy)

**Goal:** Intercept every request made by the browser and the application to see the full request — including hidden parameters, headers, and body fields that never appear in HTML source.

### 9.1 Burp Suite Setup

```
Browser → Proxy (127.0.0.1:8080) → Burp Suite → Internet

In Burp:
- Proxy → Intercept → Browse the entire application
- Target → Site Map → Expands as you browse
- Logger → Captures every request/response
- Proxy → HTTP History → Full list of all requests made
```

**What the proxy reveals that crawlers miss:**

```http
POST /api/user/update HTTP/1.1
Host: example.com
Content-Type: application/json
Authorization: Bearer eyJ...
X-CSRF-Token: abc123def456          ← hidden CSRF token
X-Request-ID: uuid-here             ← request tracking header
X-Forwarded-User: alice@company.com ← header the app trusts

{
  "username": "alice",
  "email": "alice@example.com",
  "_method": "PUT",                  ← hidden method override
  "_token": "csrf_token_value",      ← CSRF in body
  "__RequestVerificationToken": "x", ← ASP.NET hidden field
  "role": "user",                    ← IDOR candidate
  "is_active": true                  ← privilege parameter
}
```

### 9.2 Extracting Parameters From Proxy History

Burp Suite:
- **Proxy → HTTP History** → right-click → Send to Intruder → Positions tab shows all injectable points
- **Engagement Tools → Find Comments** → finds HTML/JS comments with hints
- **Engagement Tools → Discover Content** → automated content discovery from proxy data
- Export full history: `Project → Save copy → Include all traffic`

OWASP ZAP:
```bash
# Run ZAP in headless mode, spider, then export
zap-cli start
zap-cli spider https://example.com
zap-cli ajax-spider https://example.com  # JavaScript-aware
zap-cli report -o report.html -f html
```

### 9.3 Mitmproxy — Scriptable Proxy

```bash
# Run mitmproxy and capture to file
mitmproxy -w traffic.mitm

# Extract all parameters from capture
mitmdump -r traffic.mitm -s extract_params.py
```

```python
# extract_params.py
from mitmproxy import http
from urllib.parse import parse_qs, urlparse
import json

def request(flow: http.HTTPFlow):
    url = flow.request.url
    parsed = urlparse(url)
    
    # URL parameters
    qs_params = parse_qs(parsed.query)
    if qs_params:
        print(f"[GET] {parsed.path}: {list(qs_params.keys())}")
    
    # POST body parameters
    if flow.request.method == "POST":
        ct = flow.request.headers.get("content-type", "")
        if "application/json" in ct:
            try:
                body = json.loads(flow.request.text)
                print(f"[POST JSON] {parsed.path}: {list(body.keys())}")
            except: pass
        elif "application/x-www-form-urlencoded" in ct:
            params = parse_qs(flow.request.text)
            print(f"[POST FORM] {parsed.path}: {list(params.keys())}")
```

---

## 10. Phase 8 — Historical & Third-Party Sources

**Goal:** The Wayback Machine and other archives have crawled the target for years. Old versions of the app may have had endpoints, parameters, and files that still exist on the server even if they're no longer linked.

### 10.1 Wayback Machine / Web Archives

```bash
# GAU — queries Wayback + Common Crawl + URLScan + AlienVault
gau example.com --threads 10 --o gau_all.txt

# waybackurls — focused on Wayback Machine
waybackurls example.com | tee wayback.txt

# Filter for interesting patterns
cat wayback.txt | grep -E "admin|backup|debug|config|test|dev|old|\.sql|\.env|\.bak|\.zip"

# Find all unique parameters ever seen
cat wayback.txt gau_all.txt | grep "?" | unfurl --unique keys | sort -u
```

### 10.2 Common Crawl

```bash
# CommonCrawl Index
curl "https://index.commoncrawl.org/CC-MAIN-2024-10-index?url=*.example.com&output=json" \
  | jq '.url' | sort -u > common_crawl_urls.txt
```

### 10.3 URLScan.io

```bash
# Find all pages scanned for the domain
curl "https://urlscan.io/api/v1/search/?q=domain:example.com&size=100" \
  | jq '.results[].task.url' | sort -u > urlscan_urls.txt
```

### 10.4 GitHub & Source Code Leakage

Developers accidentally commit environment files, API clients, and internal tools that reveal endpoint structures and parameters.

```bash
# Search GitHub
# github.com → search: "example.com" + language:JavaScript
# github.com → search: "api.example.com" + extension:env

# GitDorker
python3 gitdorker.py -tf github_tokens.txt -q example.com -d dorks/GDORK_GENERAL.txt

# TruffleHog — finds secrets in git history
trufflehog github --repo https://github.com/example-org/frontend --json
```

---

## 11. Phase 9 — Parameter Value Fuzzing

**Goal:** Now that you have a complete list of endpoints and parameters, test what each parameter does and what values it accepts — this reveals injection points, IDOR, business logic flaws.

### 11.1 Categorize Your Parameters

Before fuzzing, understand what each parameter likely does:

```
Type → Examples → What to test
─────────────────────────────────────────────────────
ID parameters     id=1, user_id=42, item=5      → IDOR (try other IDs), SQLi
Redirect params   redirect=/, next=/, url=       → Open redirect, SSRF
Search/query      q=hello, search=, keyword=     → SQLi, XSS, SSTI
File/path         file=doc.pdf, page=home        → Path traversal, LFI
Callback/format   callback=func, format=json     → JSONP injection, XXE
Token/key         token=abc, key=x, auth=        → Auth bypass, JWT attacks
Debug/flags       debug=0, verbose=false         → Debug mode exposure
Role/privilege    role=user, type=basic          → Privilege escalation
```

### 11.2 IDOR Testing — ID Parameters

```bash
# If you see: /api/user?id=1234
# Test: /api/user?id=1235, /api/user?id=1, /api/user?id=0, /api/user?id=-1

# Automated IDOR testing with ffuf
ffuf -u "https://example.com/api/user?id=FUZZ" \
     -w numbers_1_to_10000.txt \
     -mc 200 \
     -H "Cookie: $YOUR_SESSION" \
     -fr "access denied|unauthorized"  # filter out access denied responses
```

### 11.3 Parameter Pollution Testing

```bash
# HTTP Parameter Pollution — send same parameter twice
curl "https://example.com/search?q=normal&q=injected"
curl "https://example.com/api/transfer?to=victim&to=attacker&amount=100"

# JSON parameter pollution
{"user": "alice", "role": "user", "role": "admin"}  # which one wins?
```

### 11.4 Mass Parameter Assignment (Mass Assignment)

Modern frameworks may automatically map all HTTP parameters to object properties:

```bash
# If registration is: POST /api/users  {"username":"x","email":"y"}
# Test adding: "role":"admin", "is_verified":true, "plan":"enterprise"
curl -X POST https://example.com/api/users \
     -H "Content-Type: application/json" \
     -d '{"username":"x","email":"y@test.com","role":"admin","is_admin":true}'
```

### 11.5 Hidden Parameter Activation

Some parameters exist but do nothing unless set to specific trigger values:

```bash
# Common hidden parameter names that change behavior
debug=1          verbose=true       trace=on
test=true        dev=1              internal=true
format=xml       output=csv         export=true
callback=test    jsonp=test         _method=DELETE
X-HTTP-Method-Override: DELETE      (in headers)
```

---

## 12. Full Tool Chain Summary

### Install Everything

```bash
# Go tools
go install github.com/projectdiscovery/katana/cmd/katana@latest
go install github.com/projectdiscovery/subfinder/v2/cmd/subfinder@latest
go install github.com/lc/gau/v2/cmd/gau@latest
go install github.com/hakluke/hakrawler@latest
go install github.com/tomnomnom/waybackurls@latest
go install github.com/tomnomnom/unfurl@latest
go install github.com/tomnomnom/anew@latest
go install github.com/ffuf/ffuf/v2@latest
go install github.com/OJ/gobuster/v3@latest

# Python tools
pip install arjun paramspider

# Other
cargo install feroxbuster
```

### Complete Single-Domain Workflow Script

```bash
#!/bin/bash
TARGET="example.com"
mkdir -p recon/$TARGET && cd recon/$TARGET

echo "[1] Subdomain Discovery"
subfinder -d $TARGET -silent -o subdomains.txt
curl -s "https://crt.sh/?q=%.$TARGET&output=json" | jq '.[].name_value' \
  | sort -u | anew subdomains.txt

echo "[2] Crawl All Subdomains"
cat subdomains.txt | katana -d 4 -js-crawl -o all_crawl.txt

echo "[3] Historical URLs"
gau $TARGET --o gau.txt
waybackurls $TARGET >> gau.txt
sort -u gau.txt -o gau.txt

echo "[4] Combine all URLs"
cat all_crawl.txt gau.txt | grep "^http" | sort -u > all_urls.txt

echo "[5] Extract URLs with parameters"
grep "?" all_urls.txt | sort -u > urls_with_params.txt

echo "[6] Extract unique parameter names"
cat urls_with_params.txt | unfurl --unique keys | sort -u > param_names.txt

echo "[7] JavaScript endpoint extraction"
cat all_urls.txt | grep "\.js$" | sort -u > js_files.txt
cat js_files.txt | while read url; do
    python3 ~/tools/LinkFinder/linkfinder.py -i "$url" -o cli 2>/dev/null
done | grep "^/" | sort -u > hidden_endpoints.txt

echo "[8] Directory brute-force on main domain"
ffuf -u https://$TARGET/FUZZ \
     -w ~/SecLists/Discovery/Web-Content/common.txt \
     -mc 200,301,302,403 \
     -o ffuf_results.json -of json

echo "[9] Parameter discovery on top endpoints"
arjun --urls urls_with_params.txt -o arjun_results.json

echo "[DONE] Results in recon/$TARGET/"
echo "  all_urls.txt            - All discovered URLs"
echo "  urls_with_params.txt    - URLs containing parameters"
echo "  param_names.txt         - All unique parameter names"
echo "  hidden_endpoints.txt    - Endpoints from JS analysis"
echo "  arjun_results.json      - Parameter discovery results"
```

---

## 13. Output Organization

### Folder Structure

```
recon/
└── example.com/
    ├── 01_subdomains/
    │   ├── subdomains.txt          # All discovered subdomains
    │   └── live_subdomains.txt     # Only responding ones (httpx filtered)
    │
    ├── 02_urls/
    │   ├── all_urls.txt            # Every URL found by all methods
    │   ├── urls_with_params.txt    # Only URLs with query parameters
    │   └── interesting_urls.txt    # Admin/debug/api/backup patterns
    │
    ├── 03_parameters/
    │   ├── param_names.txt         # All unique parameter names
    │   ├── arjun_results.json      # Discovered params per endpoint
    │   └── params_by_type.txt      # Categorized (id, redirect, search, etc.)
    │
    ├── 04_endpoints/
    │   ├── from_html.txt           # Found in HTML crawl
    │   ├── from_javascript.txt     # Extracted from JS files
    │   ├── from_bruteforce.txt     # Found by ffuf/feroxbuster
    │   └── from_archives.txt      # Found in Wayback/GAU
    │
    ├── 05_api/
    │   ├── swagger.json            # If found
    │   ├── graphql_schema.json     # If introspection enabled
    │   └── api_endpoints.txt      # All API routes discovered
    │
    └── 06_master/
        ├── MASTER_URLS.txt         # Combined, deduped, all sources
        ├── MASTER_PARAMS.txt       # All parameter names
        └── ATTACK_SURFACE.md       # Written summary for reporting
```

### Master Summary Template

```markdown
# Attack Surface: example.com
## Discovery Date: YYYY-MM-DD

## Statistics
- Subdomains discovered: 47
- Total unique URLs: 1,842
- URLs with parameters: 389
- Unique parameter names: 127
- Hidden endpoints (from JS): 43
- API endpoints: 67

## High-Value Endpoints
- /api/v2/admin/users — requires admin role
- /internal/debug — no auth required (!)
- /api/export?format= — large data export

## Interesting Parameters
- `id` (appearing in 43 endpoints) — IDOR surface
- `redirect` (12 endpoints) — open redirect surface  
- `file` (3 endpoints) — path traversal surface
- `role` (2 endpoints) — privilege escalation surface
- `debug` (1 endpoint) — activates verbose mode

## Next Steps
[ ] Test all id= params for IDOR
[ ] Test redirect= params for open redirect
[ ] Fuzz hidden endpoints for auth bypass
[ ] Test GraphQL introspection mutations
```

---

## Quick Reference — What Finds What

| Source | Finds |
|---|---|
| Google Dorks | Indexed parameters, exposed files, admin panels |
| Subdomain enumeration | Separate apps with different endpoints |
| HTML crawler (katana) | Linked pages, form parameters, visible API calls |
| JS analysis (LinkFinder) | Unlinked API endpoints, internal routes |
| Archive (GAU/waybackurls) | Historical endpoints, old parameters, removed features |
| ffuf/gobuster | Unlisted directories, backup files, debug endpoints |
| Arjun/x8 | Hidden parameters that endpoints accept but don't advertise |
| Burp proxy | Full request including headers, hidden form fields, XHR calls |
| Swagger/OpenAPI | Complete API surface with all params and types |
| GraphQL introspection | Complete graph schema including dangerous mutations |
| GitHub search | Internal API docs, leaked collections, hardcoded endpoints |

---

*This document is for authorized security research and penetration testing only. Always obtain written permission before testing any system you don't own.*
