# Complete IDOR Exploitation Methodology

## Table of Contents
1. [Understanding IDOR](#understanding-idor)
2. [Preparation and Setup](#preparation-and-setup)
3. [Step-by-Step Testing Methodology](#step-by-step-testing-methodology)
4. [Advanced Bypass Techniques](#advanced-bypass-techniques)
5. [Real-World Exploitation Examples](#real-world-exploitation-examples)
6. [Tools Deep Dive](#tools-deep-dive)
7. [Automated Scanning](#automated-scanning)
8. [Reporting and Remediation](#reporting-and-remediation)

---

## Understanding IDOR

### What is IDOR?

Insecure Direct Object Reference (IDOR) is a vulnerability that occurs when a web application exposes a direct reference to an internal implementation object—such as a database key, filename, or record number—without proper access control checks.

**Key Concept:** The application uses user-supplied input to access objects directly, and if no authorization verification occurs, an attacker can manipulate these references to access unauthorized data.

### Why IDOR is Dangerous

IDOR vulnerabilities are business logic flaws that traditional vulnerability scanners often miss. They can lead to:
- Unauthorized access to sensitive personal information
- Account takeover through password change functionality
- Privilege escalation allowing low-privilege users to perform admin functions
- Data manipulation and integrity violations
- Large-scale data breaches affecting millions of users

---

## Preparation and Setup

### Prerequisites

Before beginning IDOR testing, ensure you have:

1. **Multiple Test Accounts** - At minimum, two accounts with different privilege levels. If possible, create accounts at each privilege level (user, moderator, admin).

2. **Burp Suite Professional or Community Edition** - The Community Edition works for most IDOR testing, though Professional offers useful automation features.

3. **Target Understanding** - Identify what data in the application is considered sensitive. Work with stakeholders to understand which data they are most concerned about protecting.

### Initial Application Mapping

Before diving into IDOR testing, map out the application thoroughly:
- Identify all endpoints that return or modify sensitive data
- Document user roles and their permissions
- Note all locations where identifiers appear (URLs, POST bodies, headers, cookies)

---

## Step-by-Step Testing Methodology

### Step 1: Parameter Discovery

First, identify all parameters that might serve as object references. Common parameter names include:

```
id, user_id, userId, account, account_id, order, order_id, 
document, doc, file, attachment, message, thread, post, 
comment, profile, edit, delete, view, download, receipt, 
invoice, transaction, payment, customer, member, employee
```

**How to find these parameters:**
- Intercept all requests while navigating the application normally
- Look for numeric or alphanumeric values that seem to identify specific resources
- Check URL paths (`/user/12345/profile`), query strings (`?id=12345`), POST bodies, and headers

### Step 2: Baseline Request Capture

Using your primary test account, perform normal actions and capture the requests in Burp Suite:

1. Configure Burp Suite proxy (default: `127.0.0.1:8080`)
2. Install Burp's CA certificate in your browser
3. Navigate through the application while Burp records traffic
4. Identify requests that contain identifiers referencing your account

### Step 3: Send to Repeater for Initial Testing

From Burp's HTTP History:
1. Right-click on a request containing an identifier
2. Select "Send to Repeater" (Ctrl+R)
3. In Repeater, modify the identifier value
4. Click "Send" and analyze the response

**Basic test pattern:**
```
Original: GET /api/user/10001/profile
Modified: GET /api/user/10002/profile
```

### Step 4: Automated Testing with Intruder

For systematic testing, use Burp Intruder:

1. Send the target request to Intruder (Ctrl+I)
2. Select the "Sniper" attack type
3. Highlight the identifier value and click "Add §" to set payload position
4. In the Payloads tab, add your test values:
   - Sequential numbers (10001, 10002, 10003...)
   - Usernames (admin, administrator, test)
   - UUIDs if applicable
5. Click "Start Attack"

**Pro tip:** Numeric identifiers are not always sequential. Inject hundreds or thousands of numbers as payloads, as a large number of guesses may be necessary to successfully identify IDOR.

### Step 5: Response Analysis

Analyze each response for:
- **Status codes** - 200 OK vs 403 Forbidden vs 401 Unauthorized
- **Response body differences** - Compare with legitimate responses
- **Sensitive data indicators** - Look for emails, phone numbers, addresses, financial data

**What to watch for:**
- Responses that return data clearly belonging to another user
- Responses that are identical in structure but contain different data
- Error messages that disclose information about whether an ID exists

### Step 6: Cross-User Verification

To confirm an IDOR vulnerability:

1. Log into a second test account
2. Attempt to access the same modified URL or API endpoint
3. If you can access another user's data using the second account, the vulnerability is confirmed

**Important:** IDOR, by definition, can only be performed post-authentication. You must be logged in to test for IDOR.

---

## Advanced Bypass Techniques

When direct ID substitution returns 401 or 403 errors, try these advanced bypass techniques:

### Technique 1: Parameter Injection

Sometimes endpoints require additional parameters to bypass authorization checks.

**Example:**
```
GET /api_v1/messages → 401 Unauthorized
GET /api_v1/messages?user_id=victim_id → 200 OK
```

**Why it works:** The endpoint may have multiple ways to specify which data to retrieve. The authorization check might only apply to one method.

### Technique 2: HTTP Parameter Pollution (HPP)

Send duplicate parameters and observe which value the application uses.

```
GET /api_v1/messages?user_id=ATTACKER_ID&user_id=VICTIM_ID
```

**Why it works:** The authorization check might use the first parameter (your ID), while the data retrieval uses the second (victim's ID).

### Technique 3: Array Wrapping

Wrap numeric IDs in arrays within JSON requests.

```
{"id": 111} → 401 Unauthorized
{"id": [111]} → 200 OK
```

**Why it works:** Some authorization mechanisms expect a plain string for IDs. If they receive an array instead, they may skip authorization checks entirely. When the input reaches the data-fetching component, it might handle the array input by flattening it and grant access without proper validation.

### Technique 4: JSON Object Wrapping

Nest the ID inside another JSON object.

```
{"id": 111} → 401 Unauthorized
{"id": {"id": 111}} → 200 OK
```

**Why it works:** Similar to array wrapping, nested objects can bypass type checking in authorization logic.

### Technique 5: JSON Parameter Pollution

Send duplicate keys in JSON where the server uses different values for different purposes.

```json
POST /api/get_profile
Content-Type: application/json
{
    "user_id": "legitimate_user_id",
    "user_id": "victim_user_id"
}
```

### Technique 6: File Extension Modification

Add or change file extensions in the request.

```
/user_data/2341 → 401 Unauthorized
/user_data/2341.json → 200 OK
```

**Why it works:** Ruby on Rails and other frameworks sometimes handle JSON requests differently from HTML requests, with different authorization logic.

### Technique 7: API Version Downgrade

Try older API versions that may have weaker access controls.

```
/v3/users_data/1234 → 403 Forbidden
/v1/users_data/1234 → 200 OK
```

### Technique 8: Wildcard and Null Values

Use wildcards or null values to bypass ownership filters.

```
{"user_id": "123"} → 403 Forbidden
{"user_id": "*"} → 200 OK
{"user_id": null} → 200 OK
```

### Technique 9: Encoding Manipulation

Test various encoding formats:
- URL encoding: `%31%32%33%34%35`
- Double URL encoding: `%2531%2532%2533%2534%2535`
- Base64 encoding: `MTIzNDU=`
- Case changes: `ABC123` vs `abc123`

### Technique 10: HTTP Method Override

Change the HTTP method or add method override headers.

```
DELETE /api/user/12345 → 403 Forbidden
POST /api/user/12345
X-HTTP-Method-Override: DELETE → 200 OK
```

### Technique 11: Parameter Location Shifting

Move parameters between different locations:
- Query string to POST body
- POST body to headers
- Path parameter to query string

### Technique 12: GraphQL-Specific Testing

For GraphQL endpoints, test different query structures:
- Change query IDs to access other users' data
- Use fragments to request unauthorized fields
- Test batch queries where one operation might bypass checks

---

## Real-World Exploitation Examples

### Example 1: Indian GST Portal Data Leak (2025)

**The Vulnerability:** A researcher discovered an IDOR vulnerability in India's Goods and Services Tax portal. The web portal retrieved tax payment data through an API request that included a receipt ID. The API failed to verify that the current user was authorized to access a specific record.

**The Exploitation:** Any user logged into the system could access any other tax payment record by simply changing the receipt ID in the API request.

**The Impact:** This single API flaw risked exposing sensitive financial information of **11.8 million Indian taxpayers and companies**.

**How You Would Test This:** Intercept the API request containing a receipt ID parameter, modify the ID value to a different number, and observe if you receive another taxpayer's data.

### Example 2: Robot Management API BOLA Flaw (2025)

**The Vulnerability:** A Chinese robotics vendor's robot management APIs had broken object-level authorization. While the APIs required an access token, no object-level checks were enforced.

**The Exploitation:** By manipulating identifiers in API payloads, attackers could take remote control of robots they didn't own.

**The Impact:** Potential for physical harm, sabotage, or surveillance through compromised service robots used in restaurants, hospitals, and retail stores.

**How You Would Test This:** Intercept API calls to control robots, modify the robot identifier to another value, and check if you can control a different robot without authorization.

### Example 3: Rallly Poll Application IDOR (2025)

**The Vulnerability:** An IDOR vulnerability in Rallly's participant rename function allowed any authenticated user to change display names of other participants in polls without being an admin or poll owner.

**The Exploitation:** 
1. Intercept a legitimate rename request from your own account
2. Modify the `participantId` parameter to that of another user
3. Forward the modified request

**Vulnerable endpoint:** `/api/trpc/polls.participants.rename`

**The Impact:** Unauthorized users could modify other participants' names, leading to impersonation, confusion among poll participants, and tampering with poll data.

### Example 4: Password Change IDOR

**The Scenario (from real penetration tests):** A penetration tester discovered IDOR affecting password change functionality.

**The Exploitation:** The password change request contained a user identifier. By changing this identifier to another user's ID, the attacker could change any user's password.

**The Impact:** Complete account takeover of any user in the application.

**How You Would Test This:** Intercept the password change request, modify the user ID parameter to a different user's ID, and check if you can successfully change that user's password without knowing their current password.

---

## Tools Deep Dive

### Burp Suite Configuration for IDOR Testing

#### Setting Up Burp Suite

1. **Proxy Setup:**
   - Open Burp Suite → Proxy tab → Options
   - Ensure proxy listener is active on `127.0.0.1:8080`
   - Configure your browser to use this proxy

2. **Certificate Installation:**
   - Navigate to `http://burp` in your browser
   - Download and install CA certificate
   - This allows Burp to intercept HTTPS traffic

#### Using Burp Repeater for Manual Testing

Repeater allows you to manually modify and resend individual requests:

1. **Capture a request** in Proxy → HTTP History
2. **Right-click** → Send to Repeater
3. **Modify parameters** in the Request panel
4. **Click "Send"** to view the response
5. **Compare responses** between original and modified requests

**Repeater Strike Extension:** PortSwigger recently introduced an AI-powered extension that analyzes your Repeater traffic and automatically generates smart regular expressions to uncover related IDOR vulnerabilities across your proxy history.

#### Using Burp Intruder for Automated Testing

Intruder automates the process of testing multiple ID values:

1. **Send request to Intruder** (Ctrl+I)
2. **Set payload positions** by highlighting the ID value and clicking "Add §"
3. **Select attack type** - Sniper works for single parameter testing
4. **Configure payloads:**
   - Numbers: 1-1000 or larger ranges
   - Usernames from common lists
   - Custom wordlists
5. **Start attack** and analyze results

**Pro tip:** When testing in production, be careful with large payload ranges. Consider using a smaller range or testing during off-peak hours.

### Autorize Extension

The Autorize Burp extension helps automate access control checks, particularly when you have access to multiple user accounts.

**How to use Autorize:**
1. Install from BApp Store
2. Configure with two sets of cookies (low privilege and high privilege accounts)
3. Browse the application with one account
4. Autorize automatically replays requests with the other account's cookies
5. Review results for unauthorized access

### IDOR Forge Tool

IDOR Forge is a Python-based tool designed to detect IDOR vulnerabilities automatically.

**Key Features:**
- Dynamic payload generation (numeric values, random strings, special characters)
- Multi-parameter scanning
- Support for GET, POST, PUT, and DELETE methods
- Concurrent scanning with multi-threading
- Rate limiting detection and handling
- Sensitive data detection using keywords
- Proxy support for Burp Suite integration

**Basic Usage:**
```bash
python IDOR-Forge.py -u "https://example.com/api/resource?id=1"
```

**Advanced Usage:**
```bash
python IDOR-Forge.py -u "https://example.com/api/resource?id=1" -p -m GET --proxy "http://127.0.0.1:8080" -v -o results.csv
```

**GUI Mode:**
```bash
python IDOR-Forge.py --interactive
```

### OWASP ZAP

ZAP (Zed Attack Proxy) is a free alternative to Burp Suite with IDOR testing capabilities.

**ZAP IDOR Testing:**
1. Use the "Access Control Testing" add-on
2. Configure multiple user contexts
3. Run automated access control scans
4. Review findings for potential IDOR vulnerabilities

---

## Automated Scanning

### When to Use Automated Scanning

Automated scanning is useful for:
- Initial reconnaissance across many endpoints
- Testing large ranges of ID values
- Identifying low-hanging fruit quickly

**However, automation has limitations:** Vulnerability scanners may be great at detecting things like cross-site scripting, but they aren't going to pick up on nuanced business logic flaws such as "Customer A should have access to Customer B's data, but not Customer C's data".

### Setting Up Automated Scans

**Using IDOR Forge with Proxy:**

```bash
python IDOR-Forge.py -u "http://example.com/api/user?id=1" \
  --proxy "http://127.0.0.1:8080" \
  --headers '{"Authorization": "Bearer YOUR_TOKEN"}' \
  --test-values '[1,2,3,4,5,100,101,102]' \
  --sensitive-keywords '["email", "phone", "address", "ssn"]' \
  --num-range 1-1000 \
  -v \
  -o results.json
```

**Using Burp Intruder with Wordlists:**
1. Create a custom wordlist of potential ID values
2. Use Burp's built-in wordlists (Usernames, IDs, etc.)
3. Set up grep matching to highlight sensitive data in responses
4. Run the attack and sort by response length or status code

### Rate Limiting Considerations

Many applications implement rate limiting. When testing:
- Add delays between requests (`-d 2` for 2-second delays)
- Monitor for 429 status codes (Too Many Requests)
- If rate limiting is detected, increase delay times or reduce request volume

---

## Reporting and Remediation

### How to Document IDOR Findings

When you discover an IDOR vulnerability, document:

1. **The vulnerable endpoint** with full request details
2. **The parameter** that can be manipulated
3. **Step-by-step reproduction steps**:
   - Login with Account A
   - Intercept request X
   - Change parameter Y from value A to value B
   - Observe response containing Account B's data
4. **Impact assessment** - What data can be accessed? What actions can be performed?
5. **Screenshots** of requests and responses
6. **Proof of concept** code or Burp project file

### Sample Report Entry

```
Title: IDOR in User Profile Endpoint Allows Unauthorized Access to Any User's PII

Endpoint: GET /api/v2/users/{user_id}/profile

Description: 
The endpoint /api/v2/users/{user_id}/profile does not verify that the authenticated user
is authorized to access the requested user's profile. By changing the user_id parameter
in the URL, any authenticated user can view any other user's profile data.

Steps to Reproduce:
1. Authenticate as user with ID 10001
2. Navigate to /api/v2/users/10001/profile - returns own profile data
3. Change URL to /api/v2/users/10002/profile
4. Observe full profile data of user 10002 including email, phone, and address

Impact:
An attacker with a valid account can access sensitive personal information of all
application users, including email addresses, phone numbers, and physical addresses.

Affected Versions:
All versions prior to [date]

Suggested Remediation:
Implement object-level authorization checks on the endpoint to verify that the
authenticated user has permission to access the requested user's profile.
```

### Remediation Recommendations for Developers

When reporting to development teams, include these prevention strategies:

1. **Implement access control checks on every endpoint** that uses user-supplied identifiers
2. **Never rely on client-side access control** - all checks must be server-side
3. **Use unpredictable identifiers** like GUIDs instead of sequential integers (note: this makes IDOR harder to discover but does not replace proper access control)
4. **Test access controls with multiple user accounts** before each deployment
5. **Consider IDOR every time new code is deployed** - it often slips through quality checks
6. **Perform access control checks in database queries** using row-level security

---

## Quick Reference Checklist

### Basic Tests
- [ ] Change ID to adjacent values (+1, -1)
- [ ] Test large jumps (9999999)
- [ ] Try common defaults (0, 1, 999)
- [ ] Test as unauthenticated user
- [ ] Test with different authenticated users
- [ ] Test with privileged accounts

### Encoding Tampering
- [ ] URL encode the ID
- [ ] Double URL encode
- [ ] Base64 encode/decode
- [ ] Change case (if alphanumeric)
- [ ] Strip leading zeros
- [ ] Add padding characters

### Parameter Manipulation
- [ ] Send duplicate parameters
- [ ] Move parameter to different location (URL, body, header)
- [ ] Use both ID and resource_id with conflicting values
- [ ] Try different HTTP methods
- [ ] Change Content-Type headers

### Advanced Bypasses
- [ ] Wrap ID in array: `{"id":[111]}`
- [ ] Wrap ID in JSON object: `{"id":{"id":111}}`
- [ ] JSON parameter pollution: duplicate keys
- [ ] Add file extensions (.json, .xml)
- [ ] Try older API versions
- [ ] Use wildcard or null values
- [ ] Test GraphQL variations

---

## Final Notes

**Context is critical:** Authenticated users are often intended to access certain personally identifiable information in certain contexts. For example, an application accessible only to company employees may have a company directory with employees' contact information. Business addresses and business phone numbers may be appropriate to reveal, whereas home addresses may not be appropriate even to authenticated users.

**Be thorough:** Even if access controls are enforced correctly in 99 out of 100 endpoints, that one unenforced endpoint can expose a large amount of information.

**False positives are possible:** Always verify findings with multiple test accounts before reporting.
