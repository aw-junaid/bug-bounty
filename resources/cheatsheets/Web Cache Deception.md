# Web Cache Deception

These preconditions can be exploited for the Web Cache Deception attack in the following manner:

* **Step 1:** An attacker entices the victim to open a maliciously crafted link:

  `https://www.example.com/my_profile/test.jpg`

  The application ignores the 'test.jpg' part of the URL, the victim profile page is loaded. The caching mechanism identifies the resource as an image, caching it.
* **Step 2:** The attacker sends a GET request for the cached page:

  `https://www.example.com/my_profile/test.jpg`

  The cached resource, which is in fact the victim profile page is returned to the attacker (and to anyone else requesting it).

---

## Introduction and Core Concept

Web Cache Deception (WCD) is an attack that exploits a mismatch between the caching layer (e.g., CDN, proxy, or load balancer) and the web application logic . Attackers manipulate URLs to trick the cache into storing sensitive, user-specific dynamic content as if it were a public static resource—making it retrievable by anyone .

The vulnerability arises when a caching server classifies cacheable responses by file extension (e.g., `.jpg`, `.css`, `.js`) rather than by their actual content type or `Cache-Control` headers, while the origin server accepts arbitrary paths and serves dynamic content .

## Detailed Attack Mechanics

### Preconditions for Exploitation

The attack depends on a very specific set of circumstances :

1.  **Path Ignorance on Origin Server:** The application only reads the first part of the URL to determine the resource to return. If the victim requests `https://www.example.com/my_profile`, the application returns the profile page. If the application receives a request for `https://www.example.com/my_profile/test.jpg`, it still returns the profile page of the victim, disregarding the added path .

2.  **Extension-Based Caching:** The caching mechanism (CDN or reverse proxy) is configured to cache resources according to their file extensions (e.g., all `.jpg`, `.png`, `.css` files), often overriding upstream `Cache-Control` headers .

### Step-by-Step Exploitation Flow

**Step 1: Crafting the Malicious Link**
The attacker identifies a sensitive dynamic endpoint (e.g., `/my_profile`, `/account`, `/settings`) and appends a fake static file extension to the URL.

**Step 2: Victim Interaction**
The attacker sends the crafted link to the victim via phishing email, social media, or other social engineering techniques. When the authenticated victim clicks the link :
- The browser sends the request along with the victim's session cookies.
- The origin server ignores the appended path (`/test.jpg`) and serves the sensitive dynamic page (`/my_profile`).
- The response contains the victim's personal information, CSRF tokens, and other sensitive data.

**Step 3: Cache Poisoning**
The caching layer, seeing the `.jpg` extension, incorrectly treats the response as a static, cacheable image and stores it .

**Step 4: Attacker Retrieval**
The attacker requests the same URL (`https://www.example.com/my_profile/test.jpg`) without any authentication cookies. The cache serves the stored response containing the victim's sensitive data .

## Real-World Examples from Past Years

### Shopify Web Cache Deception (2021)

In July 2021, security researcher Matteo Golinelli discovered a WCD vulnerability on `shopify.com` and reported it through HackerOne .

**Vulnerable URL Pattern:**
`https://help.shopify.com/es/manual/your-account/copyright-and-trademark/<RANDOM_STRING>.css`

**Steps to Reproduce:**
1.  Create a random string (e.g., `abcdefg`)
2.  Compose the URL: `https://help.shopify.com/es/manual/your-account/copyright-and-trademark/abcdefg.css`
3.  An authenticated victim follows the URL
4.  The attacker accesses the same URL using `curl` without any cookies
5.  The response contains the victim's personal information in the source code

**Leaked Data:**
- User first and last name, username, and email
- User's profile picture
- User's valid CSRF token (enabling CSRF attacks)

**Additional Findings:** Other vulnerable subdomains included `https://hatchful.shopify.com/furniture-logo-maker%25%32%46random.css`, which leaked the authenticated user's API key .

### Bug Bounty Program Disclosure (2019)

A security researcher participating in a private bug bounty program discovered WCD vulnerabilities across multiple targets .

**Target 1 - `example.com`:** The researcher accessed `https://example.com/welcome.css` and received a 404 error page. However, viewing the page source revealed that the 404 page still contained user session information and a "Go to your Workspace" link tied to the active session. When accessed in anonymous mode, the page briefly displayed this information before redirecting to login, confirming that sensitive data was being cached .

**Target 2 - `manage.example.com`:** The researcher attempted `manage.example.com/hello.css`. While the normal response appeared unremarkable, accessing `view-source://manage.example.com/hello.css` leaked significant amounts of sensitive information from authenticated user sessions .

**Reward:** The researcher received $150 + $150 for this disclosure.

### Large-Scale Research Findings (2020-2022)

Researchers from Northeastern University, University of Trento, and Akamai conducted systematic investigations into WCD vulnerabilities .

**2020 Study:** Probed 340 websites and found 37 vulnerable to WCD (approximately 11%).

**2022 Study ("Web Cache Deception Escalates!"):** Performed the largest WCD experiment to date, searching among 10,000 sites and finding 1,118 that were impacted .

**Key Insights from the Research:**

1.  **WCD Impacts Unauthenticated Pages:** Public pages still contain sensitive secrets such as CSRF tokens, OAuth state parameters, and CSP nonces. Researchers were able to hijack chat sessions on a travel reservation platform by reusing a session token leaked via WCD .

2.  **WCD Leads to Cache Poisoning:** Sites containing WCD vulnerabilities can be exploited for damaging cache poisoning attacks. For example, researchers repurposed a WCD vector to poison the cache with an XSS payload, escalating a reflected XSS vulnerability to a stored XSS attack .

3.  **WCD as a Supply Chain Vulnerability:** A customer support management service with a WCD vulnerability exposed 456 of its clients to attacks. Three other service providers were also found endangering their clients .

## Technical Exploitation Techniques

### Path Confusion Methods

Attackers use various techniques to confuse the cache while keeping the origin server functional :

| Technique | Example URL |
|-----------|-------------|
| Arbitrary file extension | `/my_profile/test.jpg` |
| Semi-colon delimiter | `/my_profile;test.css` |
| Question mark delimiter | `/my_profile?test.css` |
| Encoded dot-segments | `/my_profile/..%2ftest.css` |
| Path traversal patterns | `/my_profile/..;/test.css` |
| Null byte injection | `/my_profile%00.css` |

### PortSwigger Lab Example

A PortSwigger Web Security Academy lab demonstrates exploiting exact-match cache rules. The lab shows that using the semi-colon delimiter with encoded dot-segments can trick the cache into normalizing a path to a cacheable resource like `/robots.txt` while the origin server processes the request differently .

**Exploit Payload Example:**
`/my-account;%2f%2e%2e%2frobots.txt?wcd`

This payload causes the cache to store the response as if it were `/robots.txt` while the origin server serves the authenticated user's account page .

## Impact and Consequences

### Information Disclosure

- Personal Identifiable Information (PII): names, email addresses, profile pictures
- Session tokens and authentication cookies
- Valid CSRF tokens (enabling CSRF attacks)
- API keys and access tokens
- Shopping cart contents and order history
- Financial information 

### Cascading Attacks

1.  **CSRF Exploitation:** With a leaked CSRF token, attackers can forge requests on behalf of the victim, performing actions such as changing email addresses, approving accounts, or making purchases .

2.  **Account Takeover:** Session tokens leaked via WCD can be reused to hijack victim accounts .

3.  **Stored XSS via Cache Poisoning:** Attackers can escalate reflected XSS vulnerabilities to stored XSS by poisoning the cache with malicious payloads .

## Remediation and Mitigation

### Application-Level Fixes

1.  **Set Proper Cache Headers:** Ensure sensitive endpoints respond with `Cache-Control: no-store, private`. This prevents caching layers from storing the response .

2.  **Block Static Extensions on Dynamic Routes:** Configure the application server to reject requests with unexpected file extensions on dynamic endpoints .

### CDN and Proxy Configuration

1.  **Whitelist Cacheable Routes:** Only allow known, safe routes (e.g., `/static/*`, `/assets/*`, `/resources/*`) to be cached .

2.  **Configure Vary Headers:** Use `Vary: Cookie, Authorization` to ensure authenticated and unauthenticated responses are cached separately .

3.  **Classify by Content-Type, Not Extension:** Configure the cache to base caching decisions on the `Content-Type` header rather than URL file extensions .

4.  **Respect Upstream Headers:** Ensure the CDN or proxy respects the `Cache-Control` headers sent by the application rather than overriding them .

### Cookie Configuration

Update the `SameSite` attribute for session cookies to `Strict` or `Lax` to prevent cross-site request forgery. Setting `SameSite` to `None` (the default in some older configurations) leaves the application vulnerable .

## Testing Methodology for Security Researchers

1.  **Identify Dynamic, Sensitive Endpoints:** Look for authenticated or private pages such as `/dashboard`, `/account`, `/profile/settings` .

2.  **Append Fake Static File Extensions:** Modify URLs to mimic static resources: `/account` → `/account/profile.jpg` or `/account;.css` .

3.  **Log in and Request the Modified URL:** Use a valid account to authenticate and visit the modified URL with the fake extension. Observe the response and check for private content .

4.  **Check for Caching Behavior:** Log out or open a private/incognito session. Revisit the same modified URL. If you see previously displayed private data, the cache has stored and exposed it .

5.  **Inspect Response Headers:** Use tools like Burp Suite or browser DevTools to examine `Cache-Control: public, max-age=86400` and `X-Cache: HIT`, which indicate the content was cached .

6.  **Attempt Variants:** Use different extensions (`.js`, `.css`, `.png`, `.html`) and path obfuscation techniques .

7.  **Validate Cross-User Exposure:** Ask a second test user (or use another browser/device) to visit the same modified URL. If they see your data, the vulnerability is confirmed .

---
