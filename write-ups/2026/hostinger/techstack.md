# 🔍 Hostinger — Full Tech Stack & Bug Bounty Reference

> **Purpose:** Deep-dive reference for security researchers, bug bounty hunters, and code reviewers mapping Hostinger's full infrastructure, technology stack per subdomain, known APIs, databases, and bug bounty program scope.
>
> **Last Updated:** June 2026
> **Sources:** StackShare, Himalayas, Hostinger Engineering Blog, Responsible Disclosure Policy, Wappalyzer-equivalent sources, official Hostinger documentation.

---

## 📑 Table of Contents

1. [Company Overview](#company-overview)
2. [Subdomain Map & Purpose](#subdomain-map--purpose)
3. [Full Technology Stack](#full-technology-stack)
   - [Languages & Frameworks](#languages--frameworks)
   - [Web Servers & Proxies](#web-servers--proxies)
   - [Databases & Data Stores](#databases--data-stores)
   - [Messaging & Queues](#messaging--queues)
   - [Infrastructure & DevOps](#infrastructure--devops)
   - [CDN, Security & Networking](#cdn-security--networking)
   - [Monitoring & Observability](#monitoring--observability)
   - [Analytics & Marketing](#analytics--marketing)
   - [Machine Learning & AI](#machine-learning--ai)
   - [Collaboration & Back Office](#collaboration--back-office)
   - [Payment Integrations](#payment-integrations)
4. [Per-Subdomain Stack Breakdown](#per-subdomain-stack-breakdown)
5. [API Surface & Endpoints](#api-surface--endpoints)
6. [H5G Next-Gen Infrastructure](#h5g-next-gen-infrastructure)
7. [Third-Party Services (Attack Surface)](#third-party-services-attack-surface)
8. [Bug Bounty Program](#bug-bounty-program)
   - [Program Details](#program-details)
   - [In-Scope Domains & Assets](#in-scope-domains--assets)
   - [Out-of-Scope](#out-of-scope)
   - [Eligible Vulnerability Classes](#eligible-vulnerability-classes)
   - [Ineligible / Excluded Issues](#ineligible--excluded-issues)
   - [Reward Tiers](#reward-tiers)
   - [Rules of Engagement](#rules-of-engagement)
   - [Reporting & Contact](#reporting--contact)
9. [Recon Tips for Bug Hunters](#recon-tips-for-bug-hunters)
10. [Known Historical CVEs / Incidents](#known-historical-cves--incidents)

---

## Company Overview

| Field | Details |
|---|---|
| **Company** | Hostinger International Ltd. / HOSTINGER operations, UAB |
| **HQ** | Švitrigailos str. 34, Vilnius 03230, Lithuania |
| **Founded** | 2004 |
| **Users** | 5+ million across 150+ countries |
| **Revenue (2025)** | ~€275.4M (58% CAGR since 2022) |
| **Certifications** | ISO/IEC 27001:2022 |
| **Bug Bounty Platform** | HackerOne (`hackerone.com/hostinger`) |
| **Security Email** | `security@hostinger.com` |
| **Data Centers** | USA (Dallas), UK (London), Netherlands (Amsterdam), Lithuania (Vilnius), India (Mumbai), Indonesia (Jakarta), Singapore, Brazil (São Paulo) — 10 DCs in 8 countries |

---

## Subdomain Map & Purpose

| Subdomain | Purpose | Tech Hints |
|---|---|---|
| `hostinger.com` | Main marketing site, blog, tutorials | Nuxt.js / Vue.js frontend, Cloudflare CDN, LiteSpeed |
| `hpanel.hostinger.com` | Proprietary hosting control panel (hPanel) | Vue.js SPA + Laravel REST API backend, PHP |
| `cpanel.hostinger.com` | Legacy cPanel interface (WHM/cPanel for legacy users) | cPanel/WHM stack (not rewarded in BB program for cPanel-native bugs) |
| `payments.hostinger.com` | Payment processing portal | Laravel backend, integrates Stripe, PayPal, Braintree, CoinGate, Credorax |
| `reach.hostinger.com` | Hostinger Reach — AI-powered email marketing platform | Vue.js/Nuxt.js frontend, Laravel API, Kafka/RabbitMQ for email pipelines, AI/LLM layer |
| `builder.hostinger.com` | AI Website Builder (Hostinger Website Builder / HWB) | Vue.js/Nuxt.js frontend, Node.js build tooling, REST API, integrates Google Fonts, Google Analytics, Facebook Pixel |
| `agents.hostinger.com` | Hostinger AI Agents / Horizons (no-code AI app builder, VPS AI agent) | Node.js / Python backend, LLM APIs (likely OpenAI/Anthropic), REST + WebSocket, Kubernetes-orchestrated |
| `auth.hostinger.com` | Authentication & SSO portal | OAuth2 / JWT-based auth, Laravel Passport or similar |
| `api.hostinger.com` | Internal/external REST API gateway | REST + JSON, Swagger/OpenAPI documented, Laravel, NGINX reverse proxy |
| `support.hostinger.com` | Help Center & Knowledge Base | WordPress or custom CMS |
| `roadmap.hostinger.com` | Public product roadmap | Third-party: likely Canny.io or similar |
| `status.hostinger.com` | Service status page | Likely Atlassian Statuspage or custom |
| `webmail.hostinger.com` | Webmail client for hosted email | Roundcube or Horde (standard cPanel/hPanel mail) |
| `ns1.dns-parking.com` / `ns2.dns-parking.com` | Authoritative DNS | PowerDNS backend |

---

## Full Technology Stack

### Languages & Frameworks

| Technology | Usage |
|---|---|
| **PHP 8.x** | Primary backend language; used in hPanel API, billing, domain management |
| **Laravel** | Main PHP framework; routing, ORM (Eloquent), REST API, queues |
| **Zend Framework** | Legacy services (being phased out) |
| **JavaScript / ES2022+** | Frontend and Node.js services |
| **Vue.js 3.x** | Primary frontend framework (hPanel, Reach, Builder) |
| **Nuxt.js** | SSR/SSG layer on top of Vue.js for main site and sub-apps |
| **jQuery** | Legacy frontend components |
| **Lodash** | JavaScript utility library |
| **Python 3.x** | ML/AI pipelines, internal tooling, data engineering |
| **Go (Golang)** | High-performance microservices (infrastructure tooling) |
| **Ruby** | Internal scripts / legacy tooling |
| **Java** | Some backend microservices |
| **Sass / CSS3** | Styling |
| **HTML5** | Markup |

---

### Web Servers & Proxies

| Technology | Usage |
|---|---|
| **LiteSpeed Enterprise** | Primary web server for all shared/cloud/WordPress hosting (mass Redis-controlled virtual hosting); event-driven, handles thousands of concurrent connections with HTTP/3 & QUIC support |
| **NGINX** | Reverse proxy, load balancing, API gateway; custom-patched version used in H5G next-gen stack |
| **OpenResty** | Legacy stack (replaced by LiteSpeed in production; was OpenResty + Apache combo before) |
| **Apache HTTP Server** | Legacy/cPanel-based hosting environments |
| **CloudLinux OS** | User isolation layer in shared hosting (resource limits via cgroups) |

---

### Databases & Data Stores

| Technology | Usage |
|---|---|
| **MySQL 8.x** | Primary relational database (user data, billing, domain records) |
| **MariaDB** | Drop-in MySQL alternative on some hosting tiers |
| **Percona Server** | MySQL-compatible high-performance DB variant |
| **Redis** | Object caching, session storage, LiteSpeed mass hosting virtual host control, real-time features |
| **Memcached** | Legacy caching layer |
| **Elasticsearch** | Full-text search, log indexing, analytics queries |
| **CephFS** | Distributed filesystem for H5G next-gen hosting containers (replaces NFS) |

---

### Messaging & Queues

| Technology | Usage |
|---|---|
| **RabbitMQ** | Task queuing, async jobs, internal microservice messaging |
| **Apache Kafka** | High-throughput event streaming (likely used in Reach email pipeline and analytics) |
| **Google Cloud Pub/Sub** | Cloud-native event delivery |

---

### Infrastructure & DevOps

| Technology | Usage |
|---|---|
| **Docker** | Container packaging for all services |
| **Docker Compose** | Local and staging orchestration |
| **Kubernetes (K8s)** | Production container orchestration (Google Kubernetes Engine + self-managed clusters) |
| **Google Kubernetes Engine (GKE)** | Managed Kubernetes for cloud-native workloads |
| **Helm** | Kubernetes package management |
| **Argo CD / Argo Workflows** | GitOps continuous delivery on Kubernetes |
| **Terraform** | Infrastructure-as-code (cloud resource provisioning) |
| **Ansible** | Configuration management across servers |
| **Chef** | Server configuration automation (legacy / coexists with Ansible) |
| **Vault (HashiCorp)** | Secrets management |
| **GitHub** | Source code hosting, CI triggers |
| **Jenkins** | CI/CD pipelines |
| **Webpack / Parcel / Gulp** | Frontend build tooling |
| **npm** | Node.js package management |
| **Amazon EC2** | VM-based workloads (AWS hybrid infrastructure) |
| **Google Cloud Platform** | GKE, Pub/Sub, Cloud Storage |
| **Amazon SES** | Transactional email delivery |
| **Amazon CloudFront** | CDN for static assets |
| **PowerDNS** | Authoritative DNS server software |
| **Dovecot** | IMAP/POP3 email server (hosting email services) |

---

### CDN, Security & Networking

| Technology | Usage |
|---|---|
| **Cloudflare** | Primary CDN, DDoS protection, WAF, DNS proxy for Hostinger's own domains and customer sites |
| **jsDelivr** | Open-source CDN for JS libraries |
| **Amazon CloudFront** | Static asset delivery |
| **LiteSpeed Cache (LSCache)** | Full-page cache, object cache (Redis-backed), CDN integration for WordPress hosting |
| **Brotli Compression** | Enabled in custom NGINX (H5G) for faster content delivery |
| **Custom Zlib** | Patched Zlib (22-29% faster than default), used in H5G NGINX |
| **ModSecurity (WAF)** | Web Application Firewall on LiteSpeed and Apache stacks |
| **2FA** | Enabled on all applicable internal systems |

---

### Monitoring & Observability

| Technology | Usage |
|---|---|
| **Prometheus** | Metrics collection |
| **Grafana** | Metrics visualization and dashboards |
| **Graylog** | Centralized log management |
| **Fluentd** | Log shipping/aggregation |
| **Kibana** | Elasticsearch log visualization |
| **Sentry** | Application error tracking (frontend + backend) |
| **New Relic** | APM and infrastructure monitoring |
| **Elasticsearch** | Log and metric storage backend |

---

### Analytics & Marketing

| Technology | Usage |
|---|---|
| **Google Analytics 4** | Web analytics |
| **Google Tag Manager** | Tag management |
| **Google Search Console** | SEO performance monitoring |
| **Hotjar** | Session recording, heatmaps |
| **Amplitude** | Product analytics and user behavior |
| **Facebook Pixel** | Conversion tracking |
| **Sift Science** | Fraud detection and risk scoring |
| **Clearbit** | B2B data enrichment |
| **Tableau** | Business intelligence and internal reporting |

---

### Machine Learning & AI

| Technology | Usage |
|---|---|
| **Python (scikit-learn)** | ML model training for fraud detection, user segmentation |
| **LightGBM** | Gradient boosting for predictions (churn, upsell scoring) |
| **PyTorch** | Deep learning (AI Website Builder features, Horizons agent) |
| **LLM APIs** | Powering Horizons (no-code AI app builder), Reach AI writer, AI domain name generator — likely integrating OpenAI GPT-4o or similar |
| **REST Assured** | API testing in CI pipelines |

---

### Collaboration & Back Office

| Technology | Usage |
|---|---|
| **Slack** | Internal communication |
| **Jira** | Issue tracking, sprint management |
| **Confluence** | Internal documentation |
| **Google Workspace** | Email, Docs, Drive |
| **Monday.com** | Project management |
| **DocuSign** | Contract signing |
| **Postman / SwaggerHub** | API development and documentation |
| **HackerOne** | Bug bounty program management |
| **Selenium / Cypress** | Automated testing (E2E) |

---

### Payment Integrations

| Provider | Usage |
|---|---|
| **Stripe** | Primary card payment processing globally |
| **PayPal / Braintree** | Alternative payment gateway |
| **Credorax** | Cross-border payment acquiring |
| **CoinGate** | Cryptocurrency payments (accepted since 2022) |
| **Razorpay** | Indian market payments (in HWB eCommerce) |
| **100+ payment methods** | Supported in Website Builder eCommerce (local payment methods globally) |

---

## Per-Subdomain Stack Breakdown

### `hostinger.com` — Main Website
- **Frontend:** Nuxt.js (SSR), Vue.js, Sass, Google Fonts
- **CDN/Security:** Cloudflare (WAF + CDN), Amazon CloudFront
- **Analytics:** Google Analytics 4, GTM, Hotjar, Facebook Pixel, Amplitude
- **CMS/Blog:** WordPress (tutorials section), custom CMS
- **SEO:** Google Search Console
- **Performance:** HTTP/3, Brotli, LiteSpeed

---

### `hpanel.hostinger.com` — Control Panel
- **Frontend:** Vue.js 3 SPA (single-page application)
- **Backend API:** Laravel (PHP 8), RESTful JSON API
- **Auth:** JWT tokens, 2FA (TOTP), OAuth2 social login
- **Database:** MySQL / MariaDB, Redis (sessions/cache)
- **Hosting:** Kubernetes (GKE) or VM-based
- **Error Tracking:** Sentry
- **Build:** Webpack / npm

---

### `cpanel.hostinger.com` — Legacy cPanel Interface
- **Stack:** WHM/cPanel (third-party proprietary software by cPanel LLC)
- **Mail:** Dovecot (IMAP/POP3), Exim (SMTP)
- **DNS:** PowerDNS
- **Note:** cPanel-native bugs are **NOT eligible** for Hostinger's bug bounty. Only vulnerabilities in Hostinger's own integration or systems adjacent to cPanel are in scope.
- **CVE Alert (2026):** CVE-2026-41940 — critical cPanel authentication bypass actively exploited in the wild (patched April 28, 2026 by cPanel). Hostinger users on cPanel should verify patch status.

---

### `payments.hostinger.com` — Payments Portal
- **Backend:** Laravel (PHP), REST API
- **Integrations:** Stripe, PayPal/Braintree, Credorax, CoinGate, Razorpay
- **Fraud Detection:** Sift Science
- **Security:** TLS 1.3, PCI-DSS compliant payment flows
- **Session/Auth:** JWT, 2FA enforced
- **High-Value BB Target:** Authentication bypass, IDOR on payment records, price manipulation, subscription tampering

---

### `reach.hostinger.com` — Email Marketing Platform
- **Frontend:** Vue.js / Nuxt.js
- **Backend:** Laravel API
- **Email Pipeline:** RabbitMQ or Kafka for campaign queuing, Amazon SES for delivery
- **AI Layer:** LLM-powered subject line suggestions, AI email content generator
- **Analytics:** Real-time open/click tracking, audience segmentation
- **Database:** MySQL + Elasticsearch (for subscriber search)
- **High-Value BB Target:** IDOR on campaign/subscriber data, stored XSS in email templates, SSRF in preview rendering, mass email abuse

---

### `builder.hostinger.com` — Website Builder
- **Frontend:** Vue.js / Nuxt.js (drag-and-drop editor)
- **Backend:** Laravel + Node.js microservices
- **AI Features:** AI content writer (LLM-based), AI logo maker, AI domain name generator
- **Integrations:** Google Ads, Google AdSense, Facebook Pixel, Razorpay, Stripe, 100+ payment gateways
- **CDN:** Cloudflare + Amazon CloudFront for published sites
- **High-Value BB Target:** XSS in builder canvas, arbitrary file upload to published sites, template injection, IDOR on site data, SSRF via integrations

---

### `agents.hostinger.com` — AI Agents / Horizons
- **Purpose:** No-code AI web app builder; VPS AI management agent
- **Frontend:** Vue.js / React (likely)
- **Backend:** Python or Node.js + LLM API calls (OpenAI / Anthropic / custom model)
- **Orchestration:** Kubernetes (auto-scaling AI workloads)
- **Protocols:** REST API + WebSocket (real-time streaming responses)
- **High-Value BB Target:** Prompt injection → SSRF/RCE via AI agent, unauthorized access to VPS management via agent API, stored XSS in app output, insecure deserialization

---

## API Surface & Endpoints

Hostinger uses both public-facing and internal REST APIs. Key observed patterns:

| API Pattern | Notes |
|---|---|
| `https://hpanel.hostinger.com/api/v1/*` | hPanel internal API (Laravel routes) |
| `https://www.hostinger.com/api/*` | Public marketing / pricing API |
| `https://payments.hostinger.com/api/*` | Payment processing endpoints |
| `https://reach.hostinger.com/api/*` | Email marketing campaign API |
| `https://builder.hostinger.com/api/*` | Website builder API (site publish, assets) |
| `https://agents.hostinger.com/api/*` | AI agent execution API |
| GraphQL | Not confirmed publicly; likely internal only |
| WebSocket `wss://` | Used in Horizons for real-time AI streaming |

**API Documentation:** Hostinger uses **SwaggerHub** and **Postman** internally. Some public API docs may be available via `roadmap.hostinger.com` or developer docs.

**Auth mechanism:** Bearer JWT tokens in `Authorization: Bearer <token>` header. OAuth2 for third-party integrations.

---

## H5G Next-Gen Infrastructure

Hostinger's internally named **H5G** architecture is the next-generation hosting platform (announced 2022, production rollout ongoing):

```
┌────────────────────────────────────────────────────────┐
│               H5G Architecture                         │
│                                                        │
│  ┌──────────────────┐    ┌───────────────────────┐    │
│  │  Management Node │    │   Compute Node Cluster │    │
│  │  Cluster         │    │                        │    │
│  │  - Laravel APIs  │    │  - Lightweight LXC     │    │
│  │  - Helper svc    │    │    Containers          │    │
│  │  - Kubernetes    │◄──►│  - Custom NGINX        │    │
│  │    orchestration │    │    (Brotli + Zlib)     │    │
│  └──────────────────┘    │  - PHP via LSAPI       │    │
│                          └───────────┬───────────┘    │
│  ┌──────────────────────────────────▼──────────┐      │
│  │         Database Node Cluster                │      │
│  │  MySQL/MariaDB + Redis + CephFS              │      │
│  └──────────────────────────────────────────────┘      │
└────────────────────────────────────────────────────────┘
```

**Key components:**
- **LXC Containers** — lightweight Linux containers for per-account isolation (not Docker)
- **CephFS** — distributed filesystem replacing NFS for high availability
- **Custom NGINX** — patched with optimized Zlib (22-29% faster), Brotli support, custom performance patches
- **Redis-controlled mass virtual hosting** — each domain resolved dynamically via Redis lookups (no config file per site)
- **Node cluster architecture** — separate clusters for management, compute, and database give fault isolation (a DB node failure doesn't take down compute)
- **PageSpeed Scores** — 99-100 without cache, consistent 100 with cache in internal tests

---

## Third-Party Services (Attack Surface)

These third-party integrations are part of Hostinger's ecosystem and can be relevant for security testing (within permitted scopes):

| Service | Integration Point | Relevance |
|---|---|---|
| **Cloudflare** | CDN, WAF, DNS for hostinger.com | Misconfigured Cloudflare rules, cache poisoning, header injection bypass |
| **HackerOne** | Bug bounty platform | Reports go here |
| **Amazon SES** | Transactional email (reach, auth) | Email header injection, SPF/DKIM/DMARC misconfigs |
| **Amazon CloudFront** | Static asset CDN | Cache poisoning, header smuggling |
| **Google Cloud Platform** | GKE, Pub/Sub | GCP misconfiguration, exposed GCP metadata endpoints |
| **Stripe / Braintree / PayPal** | Payment processing | Only Hostinger-side integration bugs; not provider bugs |
| **CoinGate** | Crypto payments | Webhook verification bypass |
| **Sift Science** | Fraud detection API | Not directly testable |
| **Clearbit** | Data enrichment | Not directly testable |
| **DocuSign** | Contract signing | SSRF via webhook, if misconfigured |
| **Atlassian (Jira/Confluence)** | Internal only | May appear in subdomain recon (`jira.hostinger.com`) |
| **Hotjar** | Analytics (session recording) | XSS via Hotjar tag if misconfigured |
| **Google Tag Manager** | Tag injection | Stored XSS risk if GTM account is compromised |
| **jsDelivr** | JS CDN | Subresource integrity (SRI) checks |
| **Canny.io** | Roadmap tool (`roadmap.hostinger.com`) | Possible account takeover via OAuth |

---

## Bug Bounty Program

### Program Details

| Field | Details |
|---|---|
| **Platform** | HackerOne — `https://hackerone.com/hostinger` |
| **Type** | Private invite + public |
| **Last Policy Update** | 2024-01-24 |
| **Security Contact** | `security@hostinger.com` |
| **Response SLA** | Not publicly stated; typical HackerOne SLA applies |
| **Legal Protection** | Hostinger agrees not to pursue civil or criminal action against researchers who comply with the policy |
| **Agency Plan Testing** | Email `security@hostinger.com` with your hPanel email to request a coupon for Agency plan (H5G infrastructure) testing |

---

### In-Scope Domains & Assets

Based on Hostinger's official Responsible Disclosure Policy:

| Asset | Type | Notes |
|---|---|---|
| `hostinger.com` | Web application | Main website |
| `hpanel.hostinger.com` | Web application | **Highest priority** — control panel |
| `payments.hostinger.com` | Web application | Payment flows |
| `reach.hostinger.com` | Web application | Email marketing |
| `builder.hostinger.com` | Web application | Website builder |
| `agents.hostinger.com` | Web application | AI agents / Horizons |
| `auth.hostinger.com` | Authentication | SSO / login |
| H5G Infrastructure | Hosting infrastructure | Requires test account (contact security@) |
| Agency Hosting plans | Hosting infrastructure | H5G-powered; request coupon via email |
| Hostinger Android/iOS apps | Mobile | If published on HackerOne scope |

> **Note:** Any domain **not explicitly listed** in the HackerOne policy scope is considered **out of scope**. Always verify current scope on `hackerone.com/hostinger` before testing.

---

### Out-of-Scope

- `cpanel.hostinger.com` — bugs in **cPanel platform itself** (not Hostinger's code); cPanel LLC is responsible
- All **hosted customer content** (websites/apps hosted by Hostinger customers)
- **Third-party plugins and programs** (e.g., WordPress plugins installed by customers)
- Any domain not listed in the current policy scope
- Infrastructure belonging to third-party providers (Cloudflare, AWS, GCP bugs)

---

### Eligible Vulnerability Classes

Hostinger explicitly considers the following vulnerability types in scope:

| Category | Examples |
|---|---|
| **Authentication & Authorization** | Auth bypass, broken access control, privilege escalation, insecure direct object references (IDOR) |
| **Injection** | SQL injection, command injection, LDAP injection, XXE |
| **Cross-Site Scripting** | Stored XSS, reflected XSS, DOM XSS — especially in hPanel, payments, Reach |
| **CSRF** | State-changing actions without CSRF tokens |
| **SSRF** | Server-Side Request Forgery (especially via AI agent endpoints, webhooks, image preview) |
| **Sensitive Data Exposure** | Exposed credentials, API keys, PII leakage in responses or logs |
| **Business Logic Flaws** | Price manipulation, subscription bypass, plan privilege escalation |
| **Subdomain Takeover** | Dangling DNS pointing to unclaimed cloud resources |
| **Open Redirect** | Redirects that enable phishing |
| **Security Misconfiguration** | Exposed admin panels, debug endpoints, directory traversal |
| **Exposed Credentials** | Hostinger-disclosed credentials (API keys, DB passwords in JS, Git repos) |
| **RCE / Code Execution** | Remote code execution in any in-scope asset |
| **Clickjacking** | On sensitive state-changing pages |
| **HTTP Request Smuggling** | Especially relevant against NGINX/LiteSpeed proxy setup |

---

### Ineligible / Excluded Issues

The following are **explicitly excluded** from rewards:

| Issue Type | Reason |
|---|---|
| cPanel platform bugs | Not Hostinger's code |
| Username/account enumeration on customer-facing systems | Low impact |
| Scanner output / automated reports | No manual analysis |
| Reports only affecting parent account via business relationship | Limited impact |
| Missing security headers (without demonstrated impact) | Low severity |
| SPF/DKIM/DMARC misconfigurations (without exploitation) | Informational |
| Rate limiting issues (without chained impact) | Low severity |
| Self-XSS | No victim interaction |
| SSL/TLS version issues on non-sensitive pages | Informational |
| Physical security issues | Out of scope |
| Social engineering attacks | Out of scope |
| DDoS / volumetric attacks | Prohibited |
| Vulnerabilities in customer-hosted content | Not Hostinger's code |

---

### Reward Tiers

Hostinger awards bounties based on CVSS severity and business impact. Typical HackerOne program tiers (verify on hackerone.com/hostinger for exact current amounts):

| Severity | CVSS Range | Typical Bounty Range |
|---|---|---|
| **Critical** | 9.0 – 10.0 | $500 – $3,000+ |
| **High** | 7.0 – 8.9 | $200 – $999 |
| **Medium** | 4.0 – 6.9 | $50 – $199 |
| **Low** | 0.1 – 3.9 | $0 – $49 / Swag |
| **Informational** | N/A | No reward |

> Always check `hackerone.com/hostinger` for current exact reward amounts; these change over time.

---

### Rules of Engagement

1. **No automated scanning** against production without explicit written permission
2. **No exploitation beyond PoC** — do not exfiltrate, modify, or delete customer data
3. **No social engineering** of Hostinger staff or customers
4. **No DDoS or stress testing**
5. **No physical security testing**
6. **Use test accounts** — create a free Hostinger account for testing; do not test on other users' accounts
7. **One account per researcher** unless testing multi-account interaction
8. **Report promptly** — do not disclose publicly before Hostinger has patched (standard responsible disclosure)
9. **Safe Harbor** — researchers acting in good faith and within these rules are protected from legal action
10. **Agency plan testing** — requires requesting a coupon from `security@hostinger.com`

---

### Reporting & Contact

- **HackerOne Program:** `https://hackerone.com/hostinger`
- **Direct Email:** `security@hostinger.com`
- **Policy Page:** `https://www.hostinger.com/legal/responsible-disclosure-policy`
- **Help Center:** `https://support.hostinger.com/en/articles/8001450-how-to-report-a-security-issue-at-hostinger`

**Report Format Best Practices:**
```
Title: [Asset] - [Vulnerability Type] - [Brief Impact]

Summary:
One-paragraph description of the vulnerability.

Steps to Reproduce:
1. Log in at https://hpanel.hostinger.com
2. Navigate to ...
3. Intercept request with Burp Suite
4. Modify parameter X to Y
5. Observe Z

Impact:
What an attacker can achieve (e.g., access all user accounts, exfiltrate PII, etc.)

Supporting Material:
- Screenshots / screen recordings
- Burp Suite HTTP request/response
- PoC script (if applicable)

CVSS Vector: CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H
```

---

## Recon Tips for Bug Hunters

### Subdomain Enumeration
```bash
# Passive recon
subfinder -d hostinger.com -o hostinger_subdomains.txt
amass enum -passive -d hostinger.com
assetfinder --subs-only hostinger.com

# Certificate Transparency
curl "https://crt.sh/?q=%.hostinger.com&output=json" | jq '.[].name_value' | sort -u

# DNS brute force
shuffledns -d hostinger.com -w /path/to/wordlist.txt -r resolvers.txt
```

### Technology Fingerprinting (Wappalyzer-equivalent)
```bash
# whatweb
whatweb https://hpanel.hostinger.com
whatweb https://payments.hostinger.com

# httpx with tech detect
httpx -l hostinger_subdomains.txt -tech-detect -status-code -title
```

### API Discovery
```bash
# JS file analysis for endpoints
katana -u https://hpanel.hostinger.com -js-crawl -d 5
gau hostinger.com | grep "api"
waybackurls hostinger.com | grep "api"

# Find swagger/openapi docs
ffuf -u https://hpanel.hostinger.com/FUZZ -w /path/to/api-wordlist.txt \
  -fc 404 -mc all -t 50
# Look for: /api/docs, /swagger, /openapi.json, /api/v1, /api-docs
```

### Interesting Files / Endpoints to Check
```
/.env                          # Environment variables leak
/.git/                         # Git repo exposure
/phpinfo.php                   # PHP config leak
/server-status                 # Apache mod_status
/actuator                      # Spring Boot actuator (Java services)
/api/v1/users                  # IDOR starting point
/api/v1/accounts               # Account enumeration
/admin                         # Admin panel
/debug                         # Debug endpoint
/.well-known/security.txt      # Security contact
```

### High-Value Test Cases for Hostinger
1. **IDOR on hPanel**: Test if accessing `/api/v1/hosting/{id}` with another user's hosting ID returns data
2. **Payment manipulation**: Test if plan prices can be altered client-side before checkout
3. **SSRF via Website Builder**: If the builder fetches external URLs (for templates, images, previews), test SSRF to `169.254.169.254` (AWS metadata) or internal ranges
4. **Stored XSS in Reach**: Craft a campaign with malicious HTML/JS in email subject or body that renders in the dashboard
5. **AI Prompt Injection in Horizons/agents**: Test if injecting instructions into user input causes the AI agent to perform unintended server-side actions (SSRF, data leakage)
6. **Subdomain Takeover**: Check all subdomains with unclaimed CNAME records (e.g., pointing to Azure, GitHub Pages, Heroku, etc.)
7. **OAuth misconfiguration**: Test `auth.hostinger.com` for open redirect in OAuth `redirect_uri`, token leakage
8. **JWT vulnerabilities**: Test for `alg:none`, weak secrets, missing expiry validation on API tokens

---

## Known Historical CVEs / Incidents

| Date | Incident | Details |
|---|---|---|
| **December 2025** | Unauthorized server access | Hostinger identified suspicious activity on a server hosting client websites; investigated and disclosed publicly |
| **2019** | Data breach | Historical breach affecting customer data; resolved and disclosed |
| **April 2026** | cPanel CVE-2026-41940 | Critical authentication bypass in cPanel/WHM (not Hostinger-specific); actively exploited in wild; patched April 28, 2026. Hostinger's cPanel users are potentially affected if not patched |

---

## Additional Notes for Security Researchers

- **ISO/IEC 27001:2022 certified** — Hostinger follows structured ISMS; expect mature patch processes
- **OWASP secure coding practices** are applied per their own security page
- **Static code analysis** runs in CI/CD pipelines (continuous)
- **Penetration testing** is performed regularly by internal and external teams
- **All OS systems** kept patched including security updates
- **Database encryption** with secure hashing algorithms for sensitive fields
- **2FA** enforced on all applicable internal systems

---

*This document is intended for legitimate security research and bug bounty purposes only. Always operate within the scope defined by Hostinger's official Responsible Disclosure Policy. Unauthorized testing is illegal and unethical.*

*Sources: StackShare (hostinger), Himalayas tech stack, Hostinger Engineering Blog, Hostinger Responsible Disclosure Policy, Wikipedia, AlternativeTo, various hosting review sites.*
