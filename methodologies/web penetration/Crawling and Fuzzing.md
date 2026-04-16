# Complete Methodology for Crawling and Fuzzing

## Table of Contents
1. [Understanding the Methodology](#understanding)
2. [Phase 1: Reconnaissance & Baseline Mapping](#phase1)
3. [Phase 2: JavaScript Analysis for Hidden Endpoints](#phase2)
4. [Phase 3: Forced Browsing & Directory Fuzzing](#phase3)
5. [Phase 4: Parameter Discovery & Fuzzing](#phase4)
6. [Phase 5: Header Fuzzing & Authentication Bypass](#phase5)
7. [Real-World Exploitation Case Studies](#casestudies)
8. [Complete Tool Workflows](#workflows)
9. [How to Test: Step-by-Step Lab Setup](#testing)
10. [Detection & Bypass Techniques](#bypass)

---

## Understanding the Methodology {#understanding}

Web crawling and fuzzing form the backbone of modern web application security testing. The methodology is simple: **discover everything the application has, then test everything you discover with unexpected inputs** .

Think of it this way:
- **Crawling** = Following all visible links and paths to understand the application structure
- **Fuzzing** = Sending unexpected data to every discovered endpoint to trigger errors or bypasses

The most successful bug bounty hunters and penetration testers follow this core principle: **crawl first with purpose, then fuzz with intelligence** .

---

## Phase 1: Reconnaissance & Baseline Mapping {#phase1}

### Step 1.1: Manual Crawl with Burp Suite

Before running automated tools, you must understand the application manually.

**Setup Process:**
1. Configure Burp Suite proxy on `127.0.0.1:8080`
2. Install Burp's CA certificate in your browser
3. Turn off "intercept" mode initially
4. Navigate through EVERY feature of the application
5. Log in as different user roles (admin, regular user, guest)

**Why This Matters:**
A real-world engagement revealed that manual crawling of a financial application uncovered an `/internal/healthcheck` endpoint that automated scanners missed entirely. This endpoint exposed database credentials in JSON format .

**What to Record:**
- All HTTP requests (GET, POST, PUT, DELETE, PATCH)
- Cookie names and session patterns
- Hidden form fields
- Comments in HTML source
- Custom HTTP headers

### Step 1.2: Automated Crawling with Gospider

After manual mapping, use gospider to expand your attack surface:

```bash
# Basic crawl with depth control
gospider -s "https://example.com" -o crawl_output -c 10 -d 3

# Advanced crawl including third-party sources
gospider -s "https://example.com" --other-source --include-subs -o full_crawl
```

**What `--other-source` Does:**
This flag queries archive services (Wayback Machine, Common Crawl) to find historical endpoints that may no longer be linked but remain accessible .

**Real-World Example:**
During a 2024 bug bounty engagement, a tester used gospider with `--other-source` on a major e-commerce site. The tool discovered `/api/v1/backup/users_2023_old.json` from Wayback Machine archives. This file contained 50,000 user records including password hashes. The company had deleted the file from their server but forgot it was archived .

### Step 1.3: Extract Hidden Paths from Robots.txt and Sitemap.xml

Always check these files first:

```bash
# Fetch and analyze robots.txt
curl https://example.com/robots.txt

# Look for disallowed paths (often developers hide admin panels here)
# Common patterns: /admin, /backup, /temp, /test, /dev
```

**Real Exploit:**
A penetration tester found `/Disallow: /api/internal/` in robots.txt. Accessing this path returned a 200 OK with full API documentation, including authentication bypass details .

---

## Phase 2: JavaScript Analysis for Hidden Endpoints {#phase2}

Modern web applications hide their most sensitive functionality in JavaScript files. This is where you find API endpoints, parameter names, and debug routes .

### Step 2.1: Extract All JavaScript Files

```bash
# Download all JS files from target
gospider -s https://example.com -d 2 | grep "\.js" | awk '{print $2}' | sort -u > js_files.txt

# Download each JS file
while read url; do
    wget "$url" -O $(basename "$url")
done < js_files.txt
```

### Step 2.2: Use LinkFinder to Extract Endpoints

```bash
# Scan a single JavaScript file
python3 linkfinder.py -i https://example.com/static/js/main.js -d

# Scan all JS files recursively
python3 linkfinder.py -i https://example.com -d -o cli
```

**What LinkFinder Finds:**
- API endpoints (`/api/users`, `/v2/auth/login`)
- Parameter names (`user_id`, `token`, `debug`)
- URL paths that aren't linked in HTML
- Hidden GET/POST endpoints

**Real-World Case (2023):**
A bug bounty hunter used LinkFinder on a popular social media platform's JavaScript bundle. The tool extracted `/api/admin/export_all_users` which wasn't documented anywhere. Accessing this endpoint with a regular user session returned a 403 Forbidden. However, by fuzzing the `?format=` parameter, they discovered `?format=jsonp` bypassed authentication and returned all user data. This was a $15,000 bounty .

### Step 2.3: Manual JS Analysis for Parameter Discovery

Sometimes automated tools miss context. Search JavaScript files for these patterns:

```bash
# Search for API endpoint patterns
grep -E '("|\/)(api|v1|v2|admin|internal|private)('"'"'|")' *.js

# Search for parameter names
grep -E '(\?|&)(id|user|token|debug|test|admin|key|secret)' *.js

# Search for feature flags
grep -iE '(debug|test|beta|internal|admin_only|feature_flag)' *.js
```

**Why This Works:**
Developers often leave debug parameters active in production. A 2024 assessment found `?debug=true` in JavaScript comments on a banking app. When added to requests, it revealed full stack traces including database credentials .

---

## Phase 3: Forced Browsing & Directory Fuzzing {#phase3}

Forced browsing means guessing paths that developers never intended to be public .

### Step 3.1: Directory Fuzzing with FFUF

```bash
# Basic directory discovery
ffuf -w /path/to/wordlist.txt -u https://example.com/FUZZ -mc 200,301,302,403

# Recursive fuzzing (auto-explore found directories)
ffuf -w wordlist.txt -u https://example.com/FUZZ -recursion -recursion-depth 3

# With common extensions
ffuf -w wordlist.txt -u https://example.com/FUZZ -e .php,.asp,.aspx,.txt,.bak,.old,.sql
```

**Understanding Status Codes:**
- `200 OK` - The resource exists (investigate immediately)
- `301/302` - Redirect (could lead to internal paths)
- `403 Forbidden` - The directory exists but you can't access it (try fuzzing inside it)
- `404 Not Found` - Doesn't exist (filter these out with `--fc 404`)

### Step 3.2: Backup File Discovery

Developers frequently create backup copies of sensitive files with predictable names .

**Common Backup Patterns to Fuzz:**
```bash
# Test these variations for every discovered file
config.php.bak
config.php.old
config.php~
config.old
config_backup.php
config_2024.php
config.txt
```

**Real-World Exploit (2024):**
A tester found `settings.php` on a university website. Using ffuf, they tested extensions and discovered `settings.php.bak`. The backup file contained database credentials with root access. The university had 10,000 student records exposed .

### Step 3.3: Use Dirb and Gobuster for Alternative Approaches

```bash
# Dirb with custom wordlist
dirb https://example.com /path/to/wordlist.txt -r -z 10

# Gobuster with status code filtering
gobuster dir -u https://example.com -w wordlist.txt -s "200,204,301,302,307,403" -t 50
```

**Pro Tip:** Always use multiple tools. FFUF is fastest, but Gobuster sometimes finds things FFUF misses due to different request handling.

---

## Phase 4: Parameter Discovery & Fuzzing {#phase4}

Parameters are where vulnerabilities hide. A hidden `?admin=true` parameter could grant privilege escalation .

### Step 4.1: Automated Parameter Discovery with Param Miner (Burp Extension)

Param Miner is an essential Burp Suite extension that automatically tests for hidden parameters .

**How to Use:**
1. Install Param Miner from BApp Store
2. Right-click any request → "Param Miner" → "Guess parameters"
3. Param Miner tests thousands of common parameter names
4. Review "Parameter found" messages in the output tab

**Real-World Discovery:**
During a 2024 engagement, Param Miner discovered a `?isAdmin=false` parameter on a user profile request. Changing it to `?isAdmin=true` granted full administrative access. The application checked the session token but never validated the parameter against it .

### Step 4.2: Parameter Fuzzing with FFUF

```bash
# Fuzz GET parameters
ffuf -w parameters.txt:PARAM -w values.txt:VALUE -u "https://example.com/page.php?PARAM=VALUE"

# Fuzz POST parameters
ffuf -w parameters.txt -X POST -d "PARAM=FUZZ" -u "https://example.com/api/endpoint"

# Cluster bomb mode (test all combinations)
ffuf -w params.txt:PARAM -w values.txt:VALUE -u "https://example.com/?PARAM=VALUE" -mode clusterbomb
```

### Step 4.3: Parameter Value Fuzzing for IDOR

Insecure Direct Object References (IDOR) occur when numeric IDs are exposed in URLs .

```bash
# Test sequential IDs
ffuf -w /path/to/ids.txt -u "https://example.com/user/profile?id=FUZZ"

# Test UUID patterns
ffuf -w uuids.txt -u "https://example.com/api/order/FUZZ"
```

**Real IDOR Exploit (2024):**
A tester found an endpoint `/api/invoice/12345` while crawling. Using ffuf with a wordlist of 1-10000, they found invoice numbers from other customers. Changing the ID returned full invoices including credit card numbers. The company fixed this within 24 hours .

---

## Phase 5: Header Fuzzing & Authentication Bypass {#phase5}

### Step 5.1: X-Forwarded-For Bypass Testing

Many applications trust the `X-Forwarded-For` header to determine client IP addresses. This is often exploited for authentication bypass .

**Real-World Vulnerability (Serv-U, 2017):**
SolarWinds Serv-U FTP server had a critical vulnerability where sending `X-Forwarded-For: 127.0.0.1` with any login request returned a valid admin session cookie. Over 15,000 servers were exposed .

**How to Test Today:**

```bash
# Test X-Forwarded-For bypass
ffuf -w ips.txt -u "https://example.com/admin" -H "X-Forwarded-For: FUZZ" -mc 200

# Common bypass IPs
127.0.0.1
localhost
0.0.0.0
10.0.0.1
172.16.0.1
192.168.1.1
```

**Burp Suite Configuration for Header Testing:**
1. Send request to Intruder
2. Add `X-Forwarded-For: §IP§` header
3. Payload type: Numbers (1-255 for each octet)
4. Look for different response status codes

### Step 5.2: Host Header Injection

The `Host` header tells the server which virtual host to serve. Misconfigured servers may accept arbitrary values .

```bash
# Test Host header injection
ffuf -w hosts.txt -u "https://example.com/reset-password" -H "Host: FUZZ" -mc 200

# Common test values
localhost
127.0.0.1
admin.example.com
internal-api.example.com
evil.com
```

**Real Exploit:**
A password reset endpoint used the Host header to generate reset links. Changing Host to `attacker.com` made the application send reset emails with links to `attacker.com/reset?token=...`, allowing token theft.

### Step 5.3: Debug Header Discovery

Many applications have hidden debug functionality activated by custom headers .

```bash
# Test common debug headers
ffuf -w debug_headers.txt -u "https://example.com/api/user" -H "FUZZ: true" -mc 200
```

**Debug Headers to Test:**
```
X-Debug-Token: true
X-Debug: 1
Debug: true
X-Developer-Mode: 1
X-Source: admin
X-Forwarded-Host: localhost
X-Original-URL: /admin
X-Rewrite-URL: /admin
```

---

## Real-World Exploitation Case Studies {#casestudies}

### Case Study 1: The 2017 Serv-U Privilege Escalation

**Background:** SolarWinds Serv-U FTP server had a public-facing web interface.

**Discovery Process:**
1. The tester crawled the application using Burp Suite's spider
2. Discovered `/?Command=Login` endpoint
3. Used Burp Scanner to fuzz various inputs
4. Noticed different response when sending `X-Forwarded-For: 127.0.0.1` header

**The Vulnerability:**
Submitting a POST to `/?Command=Login` with `X-Forwarded-For: 127.0.0.1` returned a valid Session cookie for the local administrator account—without any credentials .

**Exploitation:**
```http
POST /?Command=Login HTTP/1.1
Host: target.com
X-Forwarded-For: 127.0.0.1

(No body needed)
```

**Impact:** The attacker received a Session cookie for `(Local Admin)`, then uploaded a malicious DLL and executed it as SYSTEM. Over 15,000 public-facing servers were vulnerable .

**Lesson:** Always test how the application handles proxy headers. Authentication decisions based on client-supplied headers are fundamentally broken.

### Case Study 2: The SRC XSS via Image Upload (2023)

**Background:** A Chinese SRC (Security Response Center) platform had a content editor with image upload.

**Discovery Process:**
1. Tester found an image upload endpoint in the editor
2. Initially thought it was arbitrary file inclusion
3. Discovered the endpoint concatenated paths to fetch remote images
4. Added a `+` sign after the image extension

**The Vulnerability:**
The endpoint took a parameter like `?img=/images/photo.jpg`. Adding `+` at the end caused the server to parse the response as HTML instead of an image.

**Exploitation Path:**
1. Created a PNG file with embedded JavaScript code
2. Uploaded the malicious image
3. Used directory traversal `?img=/uploads/malicious.png%2F..%2F..%2F`
4. The WAF blocked `../`, but `%252F` bypassed it
5. The page rendered as HTML, executing the JavaScript

**Impact:** The tester stole personal information (name, phone, ID card, bank card) from victims who viewed the page .

**Lesson:** Fuzzing isn't just about finding files—it's about understanding how the server processes input. One unexpected character (`+`) changed the entire response type.

### Case Study 3: The Hidden Admin Panel (2024)

**Background:** Bug bounty program for a major e-commerce platform.

**Discovery Process:**
1. Manual crawling revealed nothing unusual
2. Ran gospider with `--other-source`
3. Wayback Machine showed `/v2/admin/dashboard` from 6 months ago
4. Current site returned 404 for this path
5. Fuzzed variations: `/v2/admin_new/dashboard`, `/v3/admin/dashboard`

**The Vulnerability:**
The `/v3/admin/dashboard` endpoint existed but wasn't linked anywhere. It had no authentication checks because developers assumed it was inaccessible.

**Exploitation:** Direct access gave full administrative control—user management, order manipulation, refund processing.

**Impact:** The tester could process refunds to their own account and export all customer data.

**Lesson:** Historical endpoints often remain accessible at different paths. Always check version variations (`/v1`, `/v2`, `/api/v1`, `/api/v2`).

---

## Complete Tool Workflows {#workflows}

### Workflow 1: Burp Suite Professional Approach

This is the most efficient workflow for professional testers .

**Step 1: Configure Proxy and Logger++**
- Keep Burp proxy running constantly
- Enable Logger++ extension to record all traffic
- Tag requests by user role (admin, user, guest)

**Step 2: Initial Crawl**
- Right-click target → Spider → "Spider this host"
- Let Burp crawl while you manually browse

**Step 3: Param Miner (First Pass)**
- Right-click any request → "Param Miner" → "Guess parameters"
- Param Miner will test thousands of hidden parameters
- Review "Parameter found" messages in Extender → Param Miner output

**Step 4: Autorize for Access Control Testing**
- Configure two sessions (low privilege user, high privilege user)
- Autorize replays requests with both sessions
- Look for endpoints where low-priv user gets same response as high-priv user

**Step 5: Targeted Fuzzing with Intruder**
- Send interesting requests to Intruder
- Highlight parameters with `§` symbols
- Load wordlists from SecLists
- Run attack and sort by status code or response length

**Step 6: Turbo Intruder for High-Speed Testing**
- Use when you need speed (rate limiting bypass)
- Python scripting allows precise control
- Pitchfork mode for testing multiple payload types simultaneously

**Step 7: JWT Editor for Token Testing**
- If JWT tokens present, test algorithm confusion
- Try `none` algorithm
- Test kid path traversal
- Modify claims (aud, iss, sub, role)

### Workflow 2: Command-Line Only (Linux)

For when you can't use Burp or want automation .

**Phase 1: Crawling**
```bash
# Initial crawl with gospider
gospider -s https://target.com -o crawl -c 20 -d 3 --other-source

# Extract all URLs
cat crawl/* | grep -E "https?://" | sort -u > all_urls.txt

# Extract parameters
cat all_urls.txt | grep -oP '\?.*' | tr '&' '\n' | cut -d'=' -f1 | sort -u > params.txt
```

**Phase 2: JavaScript Analysis**
```bash
# Download all JS files
cat all_urls.txt | grep "\.js$" | while read js; do
    wget "$js" -O js_files/$(basename "$js")
done

# Extract endpoints with LinkFinder
python3 linkfinder.py -i js_files/ -d -o cli > js_endpoints.txt
```

**Phase 3: Directory Fuzzing**
```bash
# Fast directory fuzzing
ffuf -w /usr/share/wordlists/seclists/Discovery/Web-Content/raft-large-directories.txt \
     -u https://target.com/FUZZ \
     -e .php,.asp,.aspx,.txt,.bak,.sql \
     -c -t 100 -fc 404 \
     -o dir_fuzz.json
```

**Phase 4: Parameter Fuzzing**
```bash
# Fuzz parameters on discovered endpoints
ffuf -w params.txt -u "https://target.com/api/endpoint?FUZZ=test" -fc 404
```

**Phase 5: Header Fuzzing**
```bash
# Test X-Forwarded-For bypass
ffuf -w ips.txt -u "https://target.com/admin" -H "X-Forwarded-For: FUZZ" -mc 200
```

### Workflow 3: BFFUF - Burp Bridge to FFUF

BFFUF integrates FFUF's speed with Burp's interface .

**Setup:**
1. Install BFFUF from BApp Store
2. Configure wordlist paths in BFFUF Config tab

**Usage:**
1. In Burp Repeater, mark parameters with `FUZZ1`, `FUZZ2`, etc.
2. Right-click request → `bfffuf`
3. Select mode: Cluster Bomb, Pitchfork, or Sniper
4. FFUF runs in terminal with Burp's configuration

**Example Request in Repeater:**
```http
GET /api/FUZZ1?id=FUZZ2 HTTP/1.1
Host: target.com
Authorization: Bearer FUZZ3
```

**Advantage:** You get Burp's request editing with FFUF's speed (thousands of requests per second).

---

## How to Test: Step-by-Step Lab Setup {#testing}

### Setting Up Your Testing Environment

**For Legal Testing Only:** Always test on applications you own or have written permission to test.

**Option 1: DVWA (Damn Vulnerable Web Application)**
```bash
# Install with Docker
docker pull vulnerables/web-dvwa
docker run -d -p 80:80 vulnerables/web-dvwa

# Access at http://localhost
# Login: admin / password
```

**Option 2: Hack The Box Machines**
- Sign up at hackthebox.com
- Start with "Easy" machines like "Meow" or "Fawn"
- Practice fuzzing techniques in their VPN environment

**Option 3: TryHackMe Rooms**
- "Web Fuzzing" room
- "Burp Suite" room
- "OWASP Top 10" room

### Step-by-Step Test: Finding a Hidden Parameter

**Target:** Your DVWA instance

**Step 1: Crawl the application**
```bash
gospider -s http://localhost -d 2
```

**Step 2: Identify a request with parameters**
Navigate to "SQL Injection" page. The URL will be: `http://localhost/vulnerabilities/sqli/?id=1&Submit=Submit`

**Step 3: Fuzz for additional parameters**
```bash
ffuf -w /usr/share/wordlists/seclists/Discovery/Web-Content/burp-parameter-names.txt \
     -u "http://localhost/vulnerabilities/sqli/?id=1&FUZZ=test&Submit=Submit" \
     -fc 404
```

**Step 4: Analyze results**
Look for parameters that change the response length or content.

**Step 5: Exploit found parameters**
If `debug` parameter is accepted, try `?id=1&debug=true` to see error messages.

### Step-by-Step Test: Finding Backup Files

**Target:** Any test web application

**Step 1: Discover a PHP file**
Crawl the site and note PHP files (index.php, config.php, etc.)

**Step 2: Fuzz for backup extensions**
```bash
ffuf -w extensions.txt -u "http://localhost/config.phpFUZZ" -mc 200
```

**Extensions to test:**
```
.bak
.old
~
.1
.backup
.save
.txt
.inc
.sql
```

**Step 3: Download and analyze any found backups**
```bash
wget http://localhost/config.php.bak
cat config.php.bak
```

### Measuring Success

Track these metrics during your testing:
- Number of unique endpoints discovered
- Number of parameters found
- Backup files discovered
- Debug endpoints found
- Authentication bypasses achieved

---

## Detection & Bypass Techniques {#bypass}

### How Applications Detect Fuzzing

**Rate Limiting:**
- After X requests, IP is blocked temporarily
- HTTP 429 status code indicates rate limiting

**WAF (Web Application Firewall):**
- Blocks requests with common attack patterns
- Checks User-Agent strings
- Analyzes request frequency

**Honeypot Endpoints:**
- Fake paths that trigger alerts when accessed
- `/admin`, `/backup`, `/config` often monitored

### Bypass Techniques

**Bypass Rate Limiting with Headers :**
```bash
# Rotate X-Forwarded-For headers
ffuf -w wordlist.txt -u https://target.com/FUZZ -H "X-Forwarded-For: 127.0.0.1"

# Use a wordlist of IPs
ffuf -w wordlist.txt -u https://target.com/FUZZ -H "X-Forwarded-For: FUZZ_IP"
```

**Bypass WAF with Encoding:**
```bash
# URL encode special characters
# Instead of ../ use %2e%2e%2f
# Instead of /etc/passwd use %2f%65%74%63%2f%70%61%73%73%77%64
```

**Bypass User-Agent Filtering:**
```bash
# Use common browser User-Agents
ffuf -w wordlist.txt -u https://target.com/FUZZ -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
```

**Slow Down Requests:**
```bash
# Add delay between requests
ffuf -w wordlist.txt -u https://target.com/FUZZ -t 1 -s
```

---

## Wordlist Reference 

| Purpose | Recommended Wordlist | Source |
|---------|---------------------|--------|
| General directories | raft-large-directories-lowercase.txt | SecLists |
| Common files | directory-list-2.3-medium.txt | SecLists |
| API endpoints | api-endpoints.txt | assetnote |
| Parameters | burp-parameter-names.txt | SecLists |
| Backup extensions | backup_extensions.txt | Custom |
| Subdomains | subdomains-top1million-5000.txt | SecLists |
| Technologies | raft-small-words-lowercase.txt | SecLists |

**Custom Wordlist Generation:**
```bash
# Extract words from target site
cewl https://target.com -w custom.txt

# Combine with standard wordlists
cat custom.txt /usr/share/wordlists/seclists/Discovery/Web-Content/common.txt > merged.txt

# Remove duplicates
sort -u merged.txt > final_wordlist.txt
```

---

## Quick Reference: Commands by Objective

| Objective | Command |
|-----------|---------|
| Crawl everything | `gospider -s https://target.com --other-source --include-subs` |
| Find JS endpoints | `python3 linkfinder.py -i https://target.com -d` |
| Fuzz directories | `ffuf -w wordlist.txt -u https://target.com/FUZZ -e .php,.bak` |
| Fuzz parameters | `ffuf -w params.txt -u "https://target.com/page?FUZZ=test"` |
| Fuzz headers | `ffuf -w headers.txt -u https://target.com/admin -H "FUZZ: 127.0.0.1"` |
| Bypass rate limit | `-H "X-Forwarded-For: 127.0.0.1"` |
| Burp workflow | Proxy → Param Miner → Autorize → Intruder → Turbo Intruder |

---

## Final Methodology Summary

**The Complete Testing Flow:**

1. **Manual Reconnaissance** (30 min)
   - Browse every feature
   - Record all requests in Burp
   - Note user roles and access levels

2. **Automated Crawling** (1-2 hours)
   - Run gospider with archive sources
   - Extract all JavaScript files
   - Run LinkFinder on JS files

3. **Directory Fuzzing** (2-4 hours)
   - Fuzz for hidden directories
   - Test common extensions
   - Check for backup files

4. **Parameter Discovery** (1-2 hours)
   - Run Param Miner on all endpoints
   - Fuzz parameter names
   - Fuzz parameter values

5. **Header Testing** (1 hour)
   - Test X-Forwarded-For bypass
   - Test Host header injection
   - Test debug headers

6. **Exploitation** (variable)
   - Attempt authentication bypass
   - Test for IDOR on discovered parameters
   - Check for admin functionality exposure

**Remember:** The best testers combine automated tools with manual analysis. Tools find the paths, but humans find the vulnerabilities.

---
