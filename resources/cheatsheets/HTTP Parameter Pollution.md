# HTTP Parameter Pollution (HPP)

HTTP Parameter Pollution is a web vulnerability that occurs when an attacker injects encoded query string delimiters into existing parameters or adds extra parameters to manipulate the application's behavior . This exploits the inconsistent way different web technologies handle multiple HTTP parameters with the same name.

## Core Concept

The HTTP protocol (RFC 2616) and URI syntax (RFC 3986) do not define standard behavior for requests containing the same variable name multiple times. Consequently, developers of web servers and application frameworks have implemented different approaches :

| Framework | Input | Output |
|-----------|-------|--------|
| ASP.NET / ASP | param=val1&param=val2 | param=val1,val2 |
| PHP | param=val1&param=val2 | param=val2 |
| JSP | param=val1&param=val2 | param=val1 |
| Python Zope | param=val1&param=val2 | param=['val1','val2'] |
| Golang net/http | param=val1&param=val2 | param=['val1','val2'] |
| Node.js | param=val1&param=val2 | param=val1,val2 |

An attacker can leverage these discrepancies to bypass security filters, modify application logic, or achieve otherwise impossible states .

---

## Attack Examples

### Basic Parameter Injection

Injecting existing extra parameters in GET requests:

```
https://www.bank.com/transfer?from=12345&to=67890&amount=5000&from=ABCDEF
```

In this example, the `from` parameter appears twice. Depending on the backend behavior:
- PHP/JSP may use only the first or last occurrence
- ASP.NET may concatenate both values
- The application might process the unintended account

### Social Sharing Parameter Pollution

```
https://www.site.com/sharer.php?u=https://site2.com/blog/introducing?&u=https://site3.com/test
```

The attacker injects a second `u` parameter to override or modify the destination URL, potentially leading to phishing or open redirect attacks.

---

## Real-World Exploits and Past Vulnerabilities

### 1. Digits (Twitter) OAuth Host Validation Bypass

A critical vulnerability was discovered in Digits web authentication (Twitter's OAuth service) where HTTP Parameter Pollution allowed attackers to steal OAuth credentials. The login page contained two parameters: `consumer_key` and `host`. The `host` parameter was validated against the application's registered domain to prevent credential theft. However, by supplying multiple `host` parameters, the validation logic compared only the first value, while the transfer step used the last value. This allowed attackers to redirect OAuth credentials to attacker-controlled domains, affecting every application that had integrated Digits including Periscope .

**Attack URL:**
```
https://www.digits.com/login?consumer_key=9I4iINIyd0R01qEPEwT9IC6RE&host=https%3A%2F%2Fwww.periscope.tv&host=https%3A%2F%2Fattacker.com
```

### 2. PayPal Currency Manipulation Vulnerability

A past vulnerability in PayPal allowed attackers to modify transaction details by injecting multiple `currency` parameters. By supplying duplicate values, an attacker could manipulate exchange rates during financial transactions, potentially leading to fraudulent financial gains .

### 3. HackerOne / Greenhouse.io Career Page Form Injection

Security researchers discovered that HackerOne's career pages loaded application forms from Greenhouse.io via an iframe. The `gh_jid` parameter was incorporated into the iframe URL. By using a semicolon (an alternative query string delimiter supported in HTML), researchers could inject additional parameters and load arbitrary external Greenhouse forms. This created a phishing attack vector where malicious forms could be loaded on legitimate career pages .

**Exploit URL:**
```
https://www.hackerone.com/careers?gh_jid=795069;for=airbnb
```

This loaded an Airbnb application form on HackerOne's careers page instead of the intended HackerOne form.

### 4. CVE-2017-20160: Express-Param Library Vulnerability

A critical parameter pollution vulnerability (CWE-235) was found in the flitto express-param library up to version 0.x. The `lib/fetchParams.js` file incorrectly handled situations where the number of parameters with the same name exceeded expected amounts, leading to impacts on confidentiality, integrity, and availability. The vulnerability was patched in version 1.0.0 .

### 5. Google, Yahoo, and Ask.com Discoveries (2009)

During OWASP AppSec 2009, researchers presented HPP as a newly discovered input validation vulnerability. They found exploitable flaws in Google Search Appliance front-end scripts, Ask.com, Yahoo! Mail Classic, and many other products. These vulnerabilities allowed attackers to modify application behavior, access uncontrollable variables, and bypass input validation checkpoints .

### 6. Omise CDN Cache Pollution (2025)

A June 2025 disclosure revealed that the CDN serving www.omise.co cached pages based on full URLs including arbitrary query parameters without normalization. An attacker could send requests with varying GET parameters such as `?test=123`, `?abc=xyz`, etc., and cause each version to be cached separately even when page content was identical. This allowed attackers to:

- Flood the cache with redundant variants
- Evict legitimate cache entries (denial of service)
- Create inconsistent user experiences
- Establish groundwork for future web cache poisoning attacks

**Reproduction steps:**
```
GET /en/contact-sales?test=123 HTTP/2
Host: www.omise.co
```
Response shows `CF-Cache-Status: HIT` with increasing `Age` header, indicating independent caching of each variant .

---

## Web Application Firewall (WAF) Bypass Techniques

HPP is a powerful technique for bypassing WAF protections. Recent research tested 17 different WAF configurations from major cloud providers including AWS WAF, Google Cloud Armor, Azure WAF, and Cloudflare. The results demonstrated that simple injection payloads achieved only a 17.6% bypass rate, while sophisticated parameter pollution payloads bypassed 70.6% of tested configurations .

### ASP.NET XSS WAF Bypass

The most effective bypass technique exploits ASP.NET's comma-concatenation behavior combined with JavaScript's comma operator. When ASP.NET processes a query string with multiple identical parameters, it concatenates the values with commas using `HttpUtility.ParseQueryString()`. Microsoft's official documentation states that "multiple occurrences of the same query string parameter are listed as a single entry with a comma separating each value" .

**Example bypass payload:**
```
/?q=1'&q=alert(1)&q='2
```

ASP.NET concatenates this into:
```
1',alert(1),'2
```

When inserted into a vulnerable JavaScript string context, this becomes syntactically valid JavaScript that executes the alert function.

**Payload variants tested:**

| Payload Type | Example | Success Rate |
|--------------|---------|---------------|
| Simple Injection | `q=';alert(1),'` | 17.6% |
| Pollution + Semicolon | `q=1'+1;let+asd=window&q=def='al'+'ert'` | 52.9% |
| Pollution + Line Breaks | `q=1'%0aasd=window&q=def="al"+"ert"` | 70.6% |

### Azure WAF Bypass

Researchers discovered a surprisingly simple payload that defeated Azure WAF:
```
test\\';alert(1);//
```

This payload exploits parsing discrepancies in escaped character handling between the WAF's pattern matching engine and JavaScript interpretation .

### Why WAFs Struggle with HPP

Traditional WAFs face several challenges when detecting HPP-based attacks:

1. **Individual parameter analysis:** WAFs typically analyze parameters independently and miss relationships between multiple parameters that combine to form malicious code .

2. **Lack of framework-specific parsing:** Most WAFs lack deep understanding of how different web frameworks handle parameter parsing and do not simulate framework-specific behaviors like ASP.NET's comma concatenation .

3. **Signature-based limitations:** Rule-based WAFs rely on known attack patterns, but parameter pollution creates payloads that don't match traditional XSS signatures while remaining functionally equivalent .

4. **Context unawareness:** WAFs often fail to understand the execution context (JavaScript, SQL, HTML) where polluted parameters will be interpreted .

---

## Attack Categories

### Client-Side HPP

Occurs when multiple parameters are injected into a URL and the browser or frontend application processes them incorrectly. Attackers can craft malicious links that victims click, leading to security bypasses or forced unintended behavior .

**Example:**
```
https://example.com/login?user=admin&role=user&role=admin
```
If the application fails to properly validate parameters, it might grant admin privileges instead of user access.

### Server-Side HPP

Occurs when an attacker sends multiple identical parameters in a request, causing unexpected behavior on the server. This is particularly dangerous in API endpoints and financial transactions .

**Example:**
```
POST /transfer HTTP/1.1
Host: bank.com
Content-Type: application/x-www-form-urlencoded

amount=1000¤cy=USD¤cy=INR
```
The server may process the last value (INR) or merge values, resulting in unexpected financial transactions.

---

## Impact Assessment

| Context | Vulnerable Case | Exploitation Impact |
|---------|----------------|---------------------|
| Authentication | Multiple `role` parameters | Privilege escalation |
| Financial APIs | Multiple `amount` or `currency` parameters | Fraudulent transactions |
| Web Applications | Duplicate `order` or `action` parameters | Order manipulation |
| WAF Evasion | `q=<script>&q=alert(1)` | XSS or SQL injection |
| SSRF Exploitation | `url=internal.com&url=evil.com` | Server-side request manipulation |
| CDN/Caching | Multiple unique query parameters | Cache pollution, DoS |
| OAuth/SSO | Duplicate `redirect_uri` or `host` parameters | Credential theft |

---

## Detection Methodology

### Manual Testing Steps

1. **Identify parameters:** Map all input parameters accepted by the application (GET, POST, cookies, headers).

2. **Inject duplicate parameters:** Submit requests with multiple identical parameter names.

3. **Observe behavior:** Monitor how the application processes duplicates:
   - Which value takes precedence (first, last, or concatenation)?
   - Does the response change based on duplicate order?
   - Are error messages revealing processing logic?

4. **Test boundary conditions:** Inject parameters with different data types, encodings, and delimiters (`,`, `;`, `&`, `%26`).

5. **Chain with other vulnerabilities:** Combine HPP with XSS, SQLi, SSRF, or open redirects for increased impact.

### Automated Tools

- **Burp Suite:** Use Param Miner extension or Intruder with duplicate parameter payloads
- **OWASP ZAP:** Active scan rules include HPP detection
- **Custom scripts:** Test framework-specific behaviors using the parsing table above

---

## Mitigation Strategies

### 1. Parameter Normalization

Implement cache key normalization that strips irrelevant query parameters. For CDN and caching layers, ignore unknown or unimportant GET parameters when constructing cache keys .

### 2. Parameter Allowlisting

Maintain a whitelist of expected parameters and reject any unexpected ones. Only cache based on known functional parameters .

### 3. Consistent Parameter Handling

Ensure the entire application stack (WAF, load balancer, application server, framework) handles duplicate parameters uniformly. The validated parameter must be the same as the one used in processing .

### 4. Input Validation

- Validate all parameters against expected patterns
- Reject requests containing duplicate parameters where not expected
- Implement strict type checking for parameter values

### 5. Framework Updates

Keep frameworks and dependencies updated. For example, the express-param vulnerability (CVE-2017-20160) was fixed in version 1.0.0 . Slack resolved their Greenhouse.io HPP vulnerability by updating the JavaScript dependency to the latest version .

### 6. WAF Configuration

- Use machine learning-based WAFs which demonstrated superior detection (though not complete)
- Implement framework-specific parsing logic where possible
- Configure WAFs to simulate backend parameter handling behavior 

### 7. Output Encoding

Always encode output based on context. Even if parameters are polluted, proper encoding prevents injection attacks from executing.

### 8. Monitoring and Logging

- Track requests containing duplicate parameters
- Alert on anomalous parameter combinations
- Log both the raw request and parsed parameter values for forensic analysis

---

## References

1. Omise Cache Pollution Disclosure, HackerOne (June 2025) 
2. WAF Bypass with JS Injection and Parameter Pollution, HEAL Security (September 2025) 
3. HTTP Parameter Pollution Labs and Techniques, GitHub 
4. CVE-2017-20160: Express-Param Parameter Pollution, VulDB (December 2022) 
5. Bypassing WAFs with JS Injection and Parameter Pollution, Ethiack (August 2025) 
6. ASVS5 Application Security Verification Scenarios, including Digits and Slack/HackerOne cases 
7. WAF Protections Bypassed via JS Injection, GBHackers (August 2025) 
8. HTTP Incoherent Handling of Parameters, Vigil@nce / Global Security Mag 
9. OWASP AppSec 2009 HPP Presentation, Bugtraq (May 2009) 
