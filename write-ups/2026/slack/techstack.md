# 🔐 Slack – Full Tech Stack, Domain Map & Bug Bounty Guide

> **Purpose:** Comprehensive technical reconnaissance of Slack's architecture, domains, APIs, databases, services, and bug bounty program. Compiled from official Slack engineering blogs, StackShare, Wappalyzer-equivalent sources, live traffic analysis, and HackerOne disclosures.

---

## 📌 Table of Contents

1. [Company Overview](#company-overview)
2. [Domain Map & Purpose of Each](#domain-map--purpose-of-each)
3. [Known Subdomains](#known-subdomains)
4. [Tech Stack – Frontend](#tech-stack--frontend)
5. [Tech Stack – Backend & Languages](#tech-stack--backend--languages)
6. [Databases & Storage](#databases--storage)
7. [Caching Layer](#caching-layer)
8. [Message Queue & Data Pipeline](#message-queue--data-pipeline)
9. [Networking, Load Balancing & Edge](#networking-load-balancing--edge)
10. [Cloud Infrastructure](#cloud-infrastructure)
11. [Service Mesh & Internal Services](#service-mesh--internal-services)
12. [Search Infrastructure](#search-infrastructure)
13. [DevOps & Observability](#devops--observability)
14. [Security & Identity](#security--identity)
15. [Third-Party APIs & Services](#third-party-apis--services)
16. [Mobile Stack](#mobile-stack)
17. [Desktop App](#desktop-app)
18. [API Surface for Security Testing](#api-surface-for-security-testing)
19. [Common Ports & Protocols](#common-ports--protocols)
20. [Bug Bounty Program – Full Details](#bug-bounty-program--full-details)
21. [Historical Disclosed Vulnerabilities](#historical-disclosed-vulnerabilities)
22. [High-Value Attack Surfaces](#high-value-attack-surfaces)
23. [Recon Tips & Tools](#recon-tips--tools)

---

## Company Overview

| Field | Details |
|-------|---------|
| **Legal entity** | Slack Technologies, LLC (subsidiary of Salesforce, Inc.) |
| **Acquired by** | Salesforce – $27.7B deal closed July 21, 2021 |
| **Founded** | 2009 as Tiny Speck (Vancouver, BC, Canada) |
| **HQ** | Salesforce Tower, San Francisco, CA |
| **Bug Bounty** | HackerOne – `hackerone.com/slack` |
| **Engineering Blog** | `slack.engineering` |
| **Started as** | LAMP stack (Linux, Apache, MySQL, PHP) |

---

## Domain Map & Purpose of Each

| Domain | Purpose / Function | Tech Observed |
|--------|--------------------|---------------|
| `slack.com` | Main marketing & authentication site | PHP/Hack, HHVM, Apache, CloudFront |
| `api.slack.com` | Core REST/Web API, Slack App management | PHP/Hack, HHVM, Apache |
| `app.slack.com` | Web application (Slack in browser), API traffic gateway | React, JS/TS, Envoy edge |
| `edgeapi.slack.com` | **Flannel** edge cache — user/channel/entity data (conditional timestamp fetching) | Golang, Envoy Proxy |
| `wss-primary.slack.com` | WebSocket primary endpoint (real-time messaging) | Envoy-WSS, NS1 DNS |
| `wss-backup.slack.com` | WebSocket failover endpoint | Envoy-WSS |
| `files.slack.com` | File upload (Supra service) and download (Miata service) | Go services |
| `slack-imgs.com` | **Imgproxy** — all external images rewritten through here; URL unfurling image proxy | Go / Imgproxy |
| `slack-redir.net` | URL redirect/safety proxy — wraps outbound links to check for malicious URLs | Internal redirect service |
| `slackb.com` | General logging for Slack Platform triggers, functions & workflows | Deno runtime + logging |
| `slackd.com` | Error, warning, and special condition reporting for Slack Platform apps | Logging service |
| `slackatwork.com` | Marketing / Slack branding domain | Static / CDN |
| `a.slack-edge.com` | **CloudFront CDN** — static asset delivery (JS, CSS, images) critical for client boot | AWS CloudFront |
| `www.quip.com` | Quip collaborative documents (Salesforce acquisition, $750M 2016) — powers **Slack Canvas** backend | Quip platform |
| `spaces.pm` | Originally acquired by Slack in 2014 (Spaces startup, 2 employees) — content-sharing tool that inspired Canvas | Legacy / inactive |

---

## Known Subdomains

These are confirmed active or historically active subdomains:

```
slack.com
api.slack.com
app.slack.com
edgeapi.slack.com
files.slack.com
status.slack.com
downloads.slack.com
a.slack-edge.com
wss-primary.slack.com
wss-backup.slack.com
slack-imgs.com
slack-redir.net
slackb.com
slackd.com
slackdns.com
slack-edge.com
slackatwork.com
slackb.com
get.slack.com
my.slack.com
your-workspace.slack.com  (wildcard: *.slack.com)
stun.screenhero.com       (historical – Screenhero VoIP, acquired 2015)
```

> **Note for Bug Hunters:** The wildcard `*.slack.com` is an interesting target — subdomain takeover on unclaimed subdomains has been found historically.

---

## Tech Stack – Frontend

| Technology | Details |
|------------|---------|
| **JavaScript** | ES6+ — core web app language |
| **TypeScript** | Used in Platform/Workflows SDK |
| **React** | Main UI framework for `app.slack.com` |
| **jQuery** | Legacy UI code (older parts) |
| **Handlebars.js** | Template rendering |
| **Lodash** | Utility library |
| **Moment.js** | Date/time formatting (legacy) |
| **Block Kit** | Slack's proprietary UI framework for app surfaces |
| **CSS** | Custom, no major public framework confirmed |

---

## Tech Stack – Backend & Languages

Slack's CTO Cal Henderson (ex-Flickr) publicly described the backend architecture. This is confirmed via engineering blog posts and live traffic headers (`x-server: slack-www-hhvm-api-iad-*`).

| Language / Runtime | Role |
|---------------------|------|
| **PHP → Hack (HHVM)** | Core application layer: `slack.com`, `api.slack.com` — the "webapp" tier. Migrated from PHP5 to Hack/HHVM in 2016. Still running as of 2025 (confirmed via `x-server` response headers) |
| **Java** | Real-time messaging services, SolrCloud ranking, multiple microservices |
| **Go (Golang)** | Job queue relay (JQRelay/Kafkagate), Flannel (edge cache), file services, infrastructure tooling |
| **Node.js** | Several auxiliary services |
| **Elixir** | Voice and video calling service (formerly Screenhero stack) |
| **Kotlin** | Android application |
| **Objective-C / Swift** | iOS application |
| **TypeScript + Deno** | Slack Platform (next-gen app runtime for developers building on Slack) |
| **Python** | Data pipelines, internal tooling |

---

## Databases & Storage

| Database | Role |
|----------|------|
| **MySQL + Vitess** | Primary datastore for all customer data (messages, channels, DMs). Vitess is the horizontal sharding layer. Peak: **2.3M QPS** (2M reads, 300K writes). Median query latency: **2ms**, p99: **11ms** |
| **Vitess clusters** | Multiple worldwide clusters for sharding and scaling MySQL |
| **Amazon S3** | File and object storage |
| **Hadoop** | Data warehouse / batch analytics |
| **Apache Spark** | Large-scale data processing |
| **Presto** | Ad-hoc analytical SQL queries over the data warehouse |
| **Apache Airflow** | Workflow orchestration for data pipelines |

---

## Caching Layer

| Technology | Role |
|------------|------|
| **Memcached + MCRouter** | Primary application cache. MCRouter is Facebook's Memcached proxy for routing and pooling |
| **Redis** | In-memory store for specific features (e.g., rate limiting, ephemeral state) |
| **Flannel** | Slack's **custom internal edge cache** service at `edgeapi.slack.com`. Uses conditional timestamp fetching — only returns entities that changed since the last-known timestamp. Backed by a distributed index called **Loom** |
| **AWS CloudFront** | CDN for static assets via `a.slack-edge.com` |
| **Fastly** | CDN (listed on StackShare; used for specific asset delivery paths) |

---

## Message Queue & Data Pipeline

| Technology | Role |
|------------|------|
| **Apache Kafka** | Job queue backbone. Used via: **Kafkagate** (Go HTTP→Kafka bridge) and **JQRelay** (Kafka→worker relay, Go) |
| **Apache Airflow** | Pipeline scheduling and DAG orchestration |
| **Apache Spark** | Stream and batch data processing |
| **Apache Hadoop** | Distributed storage for analytics |
| **Apache Thrift** | RPC protocol for some internal service communication |

---

## Networking, Load Balancing & Edge

This is one of the most detailed areas, confirmed from Slack's "Traffic 101" engineering post.

### DNS

| System | Role |
|--------|------|
| **Amazon Route53** | Authoritative DNS for most Slack domains. Uses `edns-client-subnet` (ECS) for geo-routing |
| **NS1** | Authoritative DNS specifically for WebSocket records (`wss-primary.slack.com`). Used for its Filter Chain & load-shedding capabilities |

### Load Balancers & Proxies

| System | Role |
|--------|------|
| **AWS Network Load Balancer (NLB)** | Layer 4, pass-through, public-facing — fronts both WS and non-WS stacks at each edge PoP |
| **Envoy Proxy (envoy-wss)** | WebSocket termination at every edge PoP. Replaced HAProxy for WS traffic |
| **Envoy Proxy (envoy-edge)** | All non-WebSocket (API, webhooks, bots) traffic routing and load balancing |
| **HAProxy** | Legacy ingress LB (now being replaced by Envoy). Still in use for some webapp tier routing as of 2023 |
| **Consul** | Service discovery and configuration. Used with `consul-template` to render HAProxy backend lists |
| **Envoy-WWW** | Internal load-balancing tier routing API traffic to webapp (HHVM) instances |

### Edge Architecture

```
User
  │
  ▼ DNS (Route53 / NS1)
  │
  ▼ NLB (Layer 4, nearest edge PoP)
  │
  ├─ WebSocket ──► envoy-wss ──► Gateway Server ──► AppLink ──► WebApp
  │
  └─ HTTP/API ───► envoy-edge ──► internal services:
                                   ├─ Flannel (edgeapi.slack.com — entity cache)
                                   ├─ Imgproxy (slack-imgs.com — image proxy)
                                   ├─ Supra (file upload)
                                   ├─ Miata (file download)
                                   └─ Envoy-WWW ──► HHVM webapp
```

### Edge PoP Drain

Slack has an automated **"Edge PoP Drain"** tool: if a region fails, DNS is automatically updated to route traffic to the next-closest PoP. The primary region is **us-east-1** (N. Virginia).

---

## Cloud Infrastructure

| Technology | Role |
|------------|------|
| **Amazon Web Services (AWS)** | Primary cloud provider. Primary region: `us-east-1`. Multi-AZ for resilience |
| **AWS CloudFront** | CDN for static assets |
| **AWS Route 53** | DNS |
| **AWS NLB** | Network load balancing |
| **AWS CloudTrail** | Audit logging for AWS API calls |
| **AWS Key Management Service (KMS)** | Encryption key management |
| **Nebula** | Slack's **open-source encrypted overlay network** for inter-PoP traffic (traverses AWS backbone encrypted) |
| **Kubernetes** | Container orchestration for internal services |
| **Ubuntu** | Primary server OS |
| **Terraform** | Infrastructure as Code |
| **Chef** | Configuration management (legacy, being phased out) |

---

## Service Mesh & Internal Services

| Service | Role |
|---------|------|
| **gRPC** | Primary inter-service RPC protocol |
| **Apache Thrift** | Legacy inter-service RPC (some services still use it) |
| **JSON-over-HTTP** | Used by some services alongside gRPC |
| **Consul** | Service discovery, K/V store, health checking, distributed locks (e.g., JQRelay Kafka partition locking) |
| **Flannel** | Edge cache microservice — entity (user/channel) data, runs behind `edgeapi.slack.com` |
| **Imgproxy** | Image proxy behind `slack-imgs.com` |
| **Supra** | File upload service |
| **Miata** | File download service |
| **AppLink** | Internal bridge between edge and core webapp |
| **Gateway Server** | Edge WebSocket gateway |
| **Snapshot Service** | App-aware microservice, reduces server load with ARC cache eviction. Handles ~1M QPS |

---

## Search Infrastructure

| Technology | Role |
|------------|------|
| **SolrCloud** | Primary search engine for Slack message search |
| **Apache Solr** | Underlying search platform |
| **Apache Lucene** | Core full-text search indexing engine (underneath Solr) |
| **Elasticsearch** | Used for internal log search / operational monitoring |
| **Java services** | Custom ranking/scoring services on top of SolrCloud |
| **ElastAlert** | Alerting on top of Elasticsearch |

---

## DevOps & Observability

| Technology | Role |
|------------|------|
| **Prometheus** | Metrics collection and alerting |
| **PagerDuty** | On-call alerting and incident management |
| **Chef** | Server configuration management |
| **Terraform** | Infrastructure provisioning |
| **Kubernetes** | Container orchestration |
| **Airflow** | Pipeline scheduling |
| **AWS CloudTrail** | AWS API audit trail |
| **ElastAlert** | Rule-based alerting on Elasticsearch data |
| **Opsmatic** | Infrastructure monitoring (legacy) |
| **RequireJS** | Legacy JS module bundler (build pipeline) |

---

## Security & Identity

| Technology | Role |
|------------|------|
| **SAML 2.0** | Enterprise SSO (custom SAML supported) |
| **OAuth 2.0** | App authorization framework |
| **HMAC-SHA256** | Request signature verification (`X-Slack-Signature` header) |
| **AWS KMS** | Encryption key management |
| **HackerOne** | Bug bounty platform |
| **Nebula** | Encrypted overlay network between regions |
| **TLS 1.2 / 1.3** | All traffic is HTTPS/WSS |
| **2FA / TOTP / Biometric** | User authentication options |

---

## Third-Party APIs & Services

| Service | Role |
|---------|------|
| **Twilio SendGrid** | Transactional email delivery |
| **Mailgun** | Email delivery (alternative/backup) |
| **Optimizely** | A/B testing and feature flags |
| **Google Analytics** | Web analytics |
| **Google Tag Manager** | Tag management |
| **Zendesk** | Customer support ticketing |
| **AdRoll** | Retargeting/marketing |
| **Delighted** | NPS / customer satisfaction surveys |
| **NS1** | Advanced DNS for WebSocket load management |
| **Deno** | TypeScript runtime for Slack Platform apps (`deno.land`, `jsr.io`) |
| **Fastly** | Secondary CDN |

---

## Mobile Stack

### iOS
| Technology | Role |
|------------|------|
| Objective-C | Legacy codebase |
| Swift | Modern iOS development |
| SlackTextViewController | Custom text input component |

### Android
| Technology | Role |
|------------|------|
| Java | Legacy Android code |
| Kotlin | Modern Android development |
| Android SDK | Core Android APIs |

---

## Desktop App

| Technology | Role |
|------------|------|
| **Electron** | Desktop wrapper (macOS, Windows, Linux) |
| **Chromium / Node.js** | Electron internals |
| **Hybrid loading** | Some assets bundled, most loaded remotely (hot-updated from CDN) |
| **MacGap** | macOS-specific native integration layer |

> **Security Note:** Slack's Electron app has historically been a high-value target. A critical RCE was found in 2020 via HTML injection → JavaScript code execution using Electron's `BrowserWindow` (`$1,500 bounty, CVSS 9-10`).

---

## API Surface for Security Testing

These are the primary API surfaces — confirmed from Slack's own bug bounty guidance:

### `api.slack.com` (Web API)
- RESTful HTTPS API
- All workspace management, messaging, users, channels
- Auth: OAuth 2.0 Bearer tokens
- What to look for: **Auth bypasses, permission escalations, information leakage**
- Runs on: PHP/Hack, HHVM, MySQL, Apache, CloudFront

### `app.slack.com` (Web App)
- What you interact with in the browser
- Rich React SPA
- What to look for: **XSS, CSRF, clickjacking, open redirects**

### `edgeapi.slack.com` (Edge / Flannel)
- POST `https://edgeapi.slack.com/cache/<enterprise_id>/<resource>/<action>`
- JSON request bodies (not form-data)
- Handles user/channel entity data with conditional timestamp fetching
- What to look for: **IDOR, unauthorized entity access, cache poisoning**

### WebSocket Endpoints
- `wss://wss-primary.slack.com` — primary
- `wss://wss-backup.slack.com` — failover
- What to look for: **Message injection, session fixation, WS hijacking**

### `files.slack.com`
- File upload and download
- What to look for: **XSS via file content, SSRF via file URLs, stored XSS**

### `slack-imgs.com`
- `https://slack-imgs.com/?c=1&o1=wi32.he32.si&url=<encoded_url>`
- All external images in link previews proxied here
- What to look for: **SSRF, URL validation bypass**

### `slack-redir.net`
- URL safety redirect proxy wrapping outbound links
- What to look for: **Open redirect, SSRF, bypass of URL validation**

### Slack Platform (Developer Apps)
- `slackb.com` — logging
- `slackd.com` — error reporting
- `deno.land`, `jsr.io` — package resolution for Deno runtime
- What to look for: **Supply chain issues in Deno packages, logging information leakage**

---

## Common Ports & Protocols

| Port | Protocol | Purpose |
|------|----------|---------|
| `443` | HTTPS / WSS | All primary traffic (API, web app, websockets) |
| `80` | HTTP | Redirect to HTTPS only |
| `3478`, `5349` | STUN/TURN (UDP/TCP) | Voice/video calls (WebRTC, formerly Screenhero infrastructure) |
| Internal gRPC | HTTP/2 | Inter-service communication |
| `9092` | Kafka | Internal message queue (not externally exposed) |
| `3306` | MySQL / Vitess | Database (internal only) |
| `11211` | Memcached | Cache (internal only) |

---

## Bug Bounty Program – Full Details

### Program Info

| Field | Details |
|-------|---------|
| **Platform** | HackerOne — `https://hackerone.com/slack` |
| **Program type** | Public bug bounty |
| **Launched** | February 2014 (one of the earliest BB programs) |
| **Owner** | Salesforce (post-2021 acquisition) |
| **Triage** | Third-party triage provider handles initial validation |
| **Total paid** | $210,000+ as of early program milestones; significantly more now |
| **Policy enforcement** | Governed by Salesforce; waives DMCA for good-faith testing |

### Severity Tiers & Reward Guidance

| Severity | Example Vulnerabilities |
|----------|------------------------|
| **Critical** | RCE, auth bypass to full account takeover, mass data exfiltration |
| **High** | Stored XSS (external-facing), CSRF on sensitive functions, SSRF with internal access, significant IDOR |
| **Medium** | Reflected XSS, limited IDOR, stack traces with sensitive info, weak crypto exploitable without nation-state resources |
| **Low** | Self-XSS, minor info disclosure, content spoofing, EoL software with no clear exploit chain |
| **Informational** | Best practice issues, missing headers, rate limiting absence |

> Reward value is at Salesforce's discretion. The **severity is capped** by the **Environmental Score** of the asset (e.g., a marketing static page cannot score "Critical").

### In-Scope Assets (Confirmed)

```
*.slack.com
api.slack.com
app.slack.com
files.slack.com
edgeapi.slack.com
slack-imgs.com
slack-redir.net
slackb.com
Slack desktop apps (Windows, macOS, Linux)
Slack mobile apps (iOS, Android)
Slack's Electron app
```

### Out-of-Scope (Common Exclusions)

```
- DoS/DDoS attacks
- Social engineering / phishing
- Physical attacks
- Spam
- Issues in third-party apps built on Slack's API (not Slack's own code)
- Content injection without security impact
- Clickjacking on non-sensitive pages
- Missing security headers without demonstrated impact
- Rate limiting issues without demonstrated impact
- Vulnerabilities in external/acquired products not explicitly in scope
```

### Rules of Engagement

- **Do not** access or modify other users' data
- **Do not** use automated scanners aggressively (risk of disrupting service)
- **Do not** hold yourself out as a Salesforce employee
- Test with your own accounts and test workspaces
- Report must be the **first** disclosure to qualify for reward
- Vulnerability must be **relevant, exploitable, and well-documented**
- Rewards are **paid upon fix**, not immediately upon report

---

## Historical Disclosed Vulnerabilities

These are real bugs found and disclosed via HackerOne — excellent for understanding what Slack's attack surface looks like:

| Vulnerability | Asset | Bounty | Notes |
|---------------|-------|--------|-------|
| **RCE via HTML injection → BrowserWindow** | Slack Desktop (Electron) | $1,500 | CVSS 9-10; attacker could exec arbitrary JS/code in desktop app. Used HTML injection + security bypass + RCE JS payload |
| **SSRF bypass in Event Subscriptions** | `api.slack.com/apps/*/event-subscriptions` | $500 | IPv6 `[::]` vector bypass of SSRF protections |
| **Internal SSRF via slash commands** | `api.slack.com` | — | SSRF through slash command URL parameters |
| **Android auth token disclosure via directory traversal** | Android app | $3,500 | Local directory traversal exposed stored auth tokens |
| **XSS on link + window.opener** | Web app | $1,000 | Cross-origin opener exploit |
| **Stored XSS on files.slack.com** | `files.slack.com` | — | Stored XSS via file content |
| **Facebook account takeover via 302 redirect** | `files.slack.com` | — | Access token leaked in redirect with 302 from files subdomain |
| **POODLE attack (SSLv3)** | `status.slack.com` | — | Deprecated SSL version exposure |
| **Reflected XSS via Custom Emoji Page** | Slack web | — | Reflected XSS parameter |
| **Workspace configuration metadata disclosure** | API | — | Leaked internal workspace config |
| **Slack-Corp Heroku app info disclosure** | Heroku-hosted internal app | — | Company member info disclosed |
| **Shared-channel integration persistence after unshare** | Slack Connect | $750 | Business logic flaw |
| **Cross-site leak for de-anonymization** | Web app | $250 | Cross-site timing attack |
| **STUN server info disclosure** | `stun.screenhero.com` | $700 | Internal network info via STUN |
| **Postmessage origin bypass via FTP** | Web app | — | FTP scheme bypass of postMessage validation |
| **HTML injection in promotional emails** | Email system | $100 | Email HTML injection |

> ~50% of Slack's bug bounty reports are "Not Applicable" — invalid issues, non-security bugs, or out-of-scope.

---

## High-Value Attack Surfaces

These areas are most likely to yield valid bug bounty findings based on Slack's architecture and historical disclosures:

### 🔴 Critical Priority

1. **Electron Desktop App** — Historically very juicy. Any HTML/JS injection in message rendering, file previews, or link handling that escapes to the Node.js layer = RCE. Look at `BrowserWindow` creation, `nodeIntegration` flags, and `contextIsolation`.

2. **OAuth Token Handling** — Slack's OAuth flows power thousands of third-party app integrations. Look for token leakage in redirects, referrer headers, postMessage misconfigurations, and open redirect chains that leak `access_token` parameters.

3. **SSRF via Webhooks / Event Subscriptions** — `api.slack.com/apps/*/event-subscriptions` accepts user-supplied URLs. Internal IP bypasses (IPv6, `0.0.0.0`, decimal notation, DNS rebinding) have historically worked.

4. **`edgeapi.slack.com` (Flannel)** — The `POST /cache/<enterprise_id>/<resource>/<action>` endpoint handles user/channel data. IDOR via enterprise_id or resource manipulation is a strong target. Conditional timestamp fetching logic may have bypass cases.

### 🟠 High Priority

5. **`slack-imgs.com` Image Proxy (SSRF)** — URL is user-controlled: `?url=<encoded>`. Classic SSRF target. Check cloud metadata endpoints (169.254.169.254), internal DNS, protocol confusion (file://, gopher://).

6. **`slack-redir.net` Redirect Proxy** — Open redirect chain that could be used for phishing or chained with OAuth to steal tokens.

7. **`files.slack.com`** — File upload/download. Stored XSS via SVG uploads, polyglots, content-type mismatches. The Supra/Miata internal services may have different validation than the frontend.

8. **WebSocket Message Injection** — Any way to inject arbitrary messages to channels or DMs the attacker doesn't have access to.

9. **Slack Connect (Cross-workspace channels)** — Business logic bugs around shared channel permissions, integration persistence, and cross-workspace data access.

### 🟡 Medium Priority

10. **`app.slack.com` — Stored/Reflected XSS** — Slack's Block Kit rendering, user-controlled fields (display names, custom statuses, channel names), link unfurling.

11. **Android / iOS Authentication** — Token storage, local directory traversal, IPC misuse (Android Intents), deep link handling.

12. **Subdomain Takeover** — Historical unclaimed subdomains on Heroku, S3, etc. (One Heroku app was previously found disclosing internal company member info). Run `subfinder` + `nuclei` against `*.slack.com`.

13. **Slack Platform (Deno apps)** — Apps deployed to Slack's managed Deno infrastructure. Supply chain or sandbox escape in the Deno runtime.

---

## Recon Tips & Tools

### Passive Recon

```bash
# Subdomain enumeration
subfinder -d slack.com -o slack-subs.txt
amass enum -passive -d slack.com
assetfinder slack.com

# Certificate transparency
curl "https://crt.sh/?q=%25.slack.com&output=json" | jq '.[].name_value' | sort -u

# Historical URLs
gau slack.com | tee slack-gau.txt
waybackurls slack.com | tee slack-wayback.txt

# Check for subdomain takeover
subjack -w slack-subs.txt -t 100 -timeout 30 -o takeovers.txt
```

### Active Recon (Only on In-Scope Assets)

```bash
# Header fingerprinting – look for x-server: slack-www-hhvm-*
curl -I https://api.slack.com/

# Check OAuth endpoints
# https://slack.com/oauth/v2/authorize
# https://slack.com/oauth/v2/token

# JS file analysis (find hidden endpoints)
katana -u https://app.slack.com -js-crawl -d 3 | grep "\.js$"
```

### Key HTTP Headers to Inspect

| Header | What It Reveals |
|--------|----------------|
| `x-server` | Reveals internal hostnames like `slack-www-hhvm-api-iad-*` |
| `x-oauth-scopes` | What OAuth scopes the current token has |
| `x-accepted-oauth-scopes` | What scopes an endpoint requires |
| `x-slack-backend` | Sometimes reveals internal routing info |
| `X-Slack-Signature` | HMAC-SHA256 signing (v0 scheme) |
| `X-Slack-Request-Timestamp` | Replay attack window (±5 min) |

### Useful Endpoints to Probe

```
GET  https://slack.com/api/auth.test          # Test token validity
GET  https://slack.com/api/users.list         # Enumerate users (requires scope)
GET  https://slack.com/api/channels.list      # Channel enumeration
POST https://slack.com/api/files.upload       # File upload endpoint
POST https://api.slack.com/api/users.profile.set  # Profile data update (XSS target)
GET  https://edgeapi.slack.com/cache/<eid>/<resource>/<action>  # Flannel cache
```

---

## References

- Slack Engineering Blog: https://slack.engineering
- StackShare (Slack's self-reported stack): https://stackshare.io/slack/slack
- Slack CTO description of backend (2019 IPO era): https://stackshare.io/posts/102366436446294299
- Traffic 101 (official): https://slack.engineering/traffic-101-packets-mostly-flow/
- Vitess at Slack: https://slack.engineering/scaling-datastores-at-slack-with-vitess/
- Hacklang at Slack: https://slack.engineering/hacklang-at-slack-a-better-php/
- Job Queue (Kafka/Go): https://slack.engineering/scaling-slacks-job-queue/
- Bug Bounty 3 Years Later: https://slack.engineering/slack-bug-bounty-three-years-later/
- HackerOne Slack Profile: https://hackerone.com/slack
- ThousandEyes Slack Monitoring Docs: https://docs.thousandeyes.com/product-documentation/best-practices/monitoring-slack
- Reverse-engineered system design (live traffic): https://gist.github.com/sshh12/4cca8d6698be3c80e9232b68586b7924
- Top Slack HackerOne Reports: https://github.com/reddelexc/hackerone-reports/blob/master/tops_by_program/TOPSLACK.md
- FireBounty Slack VDP: https://firebounty.com/643-slack/

---

> ⚠️ **Legal Disclaimer:** This document is compiled from publicly available sources (official engineering blogs, StackShare, HackerOne disclosures, official documentation) for educational and authorized security research purposes only. Always operate within Slack's bug bounty rules of engagement. Unauthorized access to systems is illegal.
