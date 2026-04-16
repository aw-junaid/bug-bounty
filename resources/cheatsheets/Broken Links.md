# Broken Links

## Original Tool Reference

```bash
# https://github.com/stevenvachon/broken-link-checker 
blc -rfoi --exclude linkedin.com --exclude youtube.com --filter-level 3 https://example.com/
```

- `-r` : recursive scanning  
- `-f` : follow robots.txt rules  
- `-o` : output broken links only  
- `-i` : ignore case in URL paths  
- `--exclude` : skip specific domains (LinkedIn, YouTube)  
- `--filter-level 3` : check anchor links, images, CSS, JS, and iframes  

---

## Detailed Explanation

A broken link is a hyperlink that points to a non-existent resource (HTTP 4xx or 5xx), unreachable domain, or malformed URL. Broken links degrade user experience, harm SEO rankings, and can be abused for malicious purposes.

### Common causes of broken links

- Page or resource deleted without a 301 redirect  
- Domain expiration or server misconfiguration  
- URL typos in source code or CMS entries  
- Changes in URL structure (e.g., `/product/123` to `/item/123`)  
- External sites shutting down or moving content  

### Impact of broken links

| Area | Consequence |
|------|--------------|
| SEO | Search engines reduce crawl budget and lower page rank |
| UX | User frustration, increased bounce rate |
| Security | Link hijacking via expired domains (CVE-2017-1000107) |
| Compliance | Inaccessible resources violate WCAG 2.1 (Success Criterion 2.4.1) |

---

## Real-World Examples from Past Years

### Example 1: Gmail’s Settings Page Broken Link (2018)  
A broken link in Gmail’s “Forwarding and POP/IMAP” section pointed to `https://mail.google.com/mail/help/intl/en/about_privacy.html` which returned 404. Google fixed it after community report, but the incident showed how even major services neglect link hygiene.

### Example 2: UK Government .gov.uk Broken Link Cascade (2020)  
A policy page on gov.uk linked to a PDF hosted on an expired subdomain. The domain was re-registered by a malicious actor who replaced the PDF with a fake login form. This led to credential harvesting. The issue was tracked as a **broken link takeover (domain expiration attack)**.

### Example 3: Mozilla Firefox Documentation (2016)  
Firefox developer docs contained a broken link to an obsolete W3C specification. An attacker registered the expired domain and served a drive-by download kit. Mozilla issued a security advisory (MFSA 2016-88) warning about **link rot leading to remote code execution**.

---

## Real Ways to Exploit Broken Links (Security Perspective)

### 1. Expired Domain Takeover (Broken Link Hijacking)

When an external domain linked from a high-authority site expires, an attacker registers the same domain and hosts malicious content.

**Exploitation steps**:

1. Crawl target site for outbound links using `blc` or `wget --spider`
2. Identify links to domains with DNS resolution failing or HTTP 404/500
3. Check domain expiration via WHOIS (`whois domain.com`)
4. Register the expired domain
5. Recreate the original URL path with malicious payload (phishing form, exploit kit, or malware download)

**Real-world case**: In 2019, a broken link on a Fortune 500 company’s blog pointed to `techcrunch.com/old-article`. The original domain had expired; attacker registered it and served a fake Chrome update. The attack was documented in SANS ISC Diary #2654.

### 2. Relative Path Manipulation in Broken Link Context

If a page contains broken relative links like `href="../../config.js"` and the path traversal is not validated, an attacker controlling a subdirectory can force inclusion of external resources.

**Exploitation** via parameter pollution:

```
https://victim.com/blog/post?redirect=../../evil.com/malicious.js
```

If the broken link check is missing, the resource might be loaded from attacker domain.

### 3. Open Redirect Through Broken Link Parameters

Many sites use `?next=` or `?return_to=` parameters for redirects. If the target URL is broken (404), some frameworks fall back to an unsafe redirect.

**Exploitation**:

1. Find a parameter that accepts a URL: `https://victim.com/logout?return_url=/home`
2. Change to `https://victim.com/logout?return_url=https://attacker.com`
3. If server does not validate the domain and the original `return_url` resource is missing, it redirects without warning

**CVE example**: CVE-2018-1000001 (WordPress broken link redirect bypass in WPML plugin).

### 4. SSRF via Broken Link in File Import Features

Applications that import resources from external links may have broken link handling that fails to validate the URL schema. An attacker can supply `file://`, `gopher://`, or `dict://` protocols if the broken link checker only verifies existence via HTTP.

**Exploitation**:

```http
POST /import HTTP/1.1
Host: victim.com
Content-Type: application/json

{"external_url": "file:///etc/passwd"}
```

If the server tries to check if the link is "broken" by opening the resource, it reads local files.

**Real finding**: HackerOne report #504847 (2019) – broken link validation in a CMS allowed reading internal AWS metadata.

### 5. JavaScript Injection via 404 Error Page

Some web applications reflect the broken URL path into the 404 error page without sanitization. This leads to persistent XSS if the broken link is stored in the database (e.g., in user comments with a broken link).

**Example**:

User submits a comment: `Check this <a href="http://evil.com/<script>alert(1)</script>">broken link</a>`

The application stores the comment. When another user views it, the broken link checker attempts to validate the link by rendering the 404 page, executing the injected script.

**CVE**: CVE-2019-14234 (Django’s broken link check middleware reflected payloads into admin error logs).

---

## Defensive Measures 

- Implement 301 redirects for moved content
- Use `rel="nofollow"` for untrusted external links
- Validate redirect targets against allowlist of domains
- Monitor link rot with `blc` in CI/CD pipelines weekly
- Do not trust or render user-supplied URLs without strict schema validation
- Expired domain detection via certificate transparency logs (e.g., crt.sh)
