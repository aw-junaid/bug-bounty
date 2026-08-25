# Thinking Through API Security and User Management

Every time I've sat down to plan an API, I've noticed the technical design — resources, operations, data models — is only half the job. The other half is a set of questions about *who's actually calling this thing*, and I've learned the hard way that leaving those questions until "later" almost always means retrofitting authentication and user management onto something that wasn't built to support it. So I want to walk through the questions I ask myself here, in the order I actually think about them, with real, tested code where it helps.

## Do I Already Have User Management I Can Reuse?

The first thing I check before designing anything API-specific is whether user accounts already exist somewhere in my organization — a web app's login system, an internal identity service, a customer database. If they do, my job shifts from "build user management" to "expose that existing system to API consumers," which is a much smaller and safer task.

```mermaid
flowchart TD
    Start2c{"Do I already have<br/>user accounts somewhere?"} -->|Yes| Reuse2["Extend/reuse existing<br/>user management for the API"]
    Start2c -->|No| Build2["I need to design<br/>user management from scratch"]
```

> **Note:** I'd rather reuse an imperfect existing system than build a second, parallel one "just for the API." Two separate user stores for the same humans is a recipe for accounts drifting out of sync — password resets that only work in one place, permissions that disagree with each other, and a support team that has to check two systems to answer one ticket.

## Do I Want to Offer OAuth?

This is a genuinely separate decision from "do I have user accounts." OAuth is specifically about letting **third-party applications** act on a user's behalf without that user ever handing their password to the third party.

```mermaid
sequenceDiagram
    participant User
    participant ThirdPartyApp as Third-Party App
    participant MyAPI as My API / Auth Server

    User->>ThirdPartyApp: "I want to connect my account"
    ThirdPartyApp->>MyAPI: Redirect user to authorize
    MyAPI->>User: "Do you approve this app?"
    User->>MyAPI: Approve
    MyAPI->>ThirdPartyApp: Authorization code
    ThirdPartyApp->>MyAPI: Exchange code for access token
    MyAPI-->>ThirdPartyApp: Access token (scoped, time-limited)
    ThirdPartyApp->>MyAPI: API calls using the token
```

I decide whether I need this based on one question: will *other companies' applications* need to act on behalf of *my users*? If the only consumer of my API is my own first-party app, I generally don't need the full OAuth authorization-code dance — a simpler API key or a first-party login token is enough. If I'm opening the door to a real developer ecosystem — partners, integrations, plugins — OAuth becomes close to mandatory, because it's the mechanism that lets a user grant *limited, revocable* access without ever exposing their actual credentials to that third party.

Here's a JWT access token I generated and decoded to make sure the shape actually works the way I described it — carrying identity, tier, and scope claims together:

```python
import jwt
import datetime

secret = "demo-signing-secret"
payload = {
    "sub": "user_12345",
    "tier": "pro",
    "scope": "read:products write:orders",
    "iat": datetime.datetime.now(datetime.timezone.utc),
    "exp": datetime.datetime.now(datetime.timezone.utc) + datetime.timedelta(hours=1),
}
token = jwt.encode(payload, secret, algorithm="HS256")
print(token)
```

Running this produced a real, valid signed token:

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ1c2VyXzEyMzQ1IiwidGllciI6InBybyIsInNjb3BlIjoicmVhZDpwcm9kdWN0cyB3cml0ZTpvcmRlcnMiLCJpYXQiOjE3ODc2NjA0OTksImV4cCI6MTc4NzY2NDA5OX0.ebUo4mrJ_SDexVO4HzXTWgrxxRmRWvcj6gK35zZ3Zxo
```

Decoding it back confirms exactly what's inside — this is the kind of token an OAuth-issued access token would carry:

```json
{
  "sub": "user_12345",
  "tier": "pro",
  "scope": "read:products write:orders",
  "iat": 1787660499,
  "exp": 1787664099
}
```

> **Caution:** A JWT's payload is **not encrypted** — anyone holding the token can decode and read it (I just did, with no secret needed for reading, only for verifying the signature). Never put sensitive data like a raw password or a full credit card number inside a JWT payload. The signature proves the token wasn't tampered with; it doesn't hide the contents.

## Starting From Zero: Sign-Up

If I genuinely have no user management at all, the very first question is the most basic one: **what does someone actually have to do to sign up?**

```mermaid
flowchart LR
    Visit["Visit sign-up page /<br/>call sign-up endpoint"] --> Verify2["Verify identity<br/>(email confirmation, etc.)"]
    Verify2 --> Provision["Provision an account +<br/>issue credentials"]
    Provision --> FirstCall["Make first authenticated<br/>API call"]
```

### Can They Sign Up Through a Browser? Should They?

I think about this as two separate questions, not one. "Can" is a technical capability question — most APIs *can* offer a web sign-up form. "Should" is a product question: developers evaluating an API often want to explore it with as little friction as possible, and a browser-based, self-serve sign-up flow (get a key in under a minute) tends to drive far more adoption than a flow that requires emailing someone and waiting for manual approval. I generally default to self-serve unless there's a real reason — security sensitivity, business/legal requirements, high potential for abuse — that justifies friction.

### Can I Simplify Things with User Tiers?

Rather than handling every user as a fully custom case, I've found it's much easier to define a small number of **tiers** — free, pro, enterprise, whatever fits — and attach behavior (rate limits, feature access, support level) to the tier rather than to the individual account.

```python
tiers = {
    "free": {"rate_limit_per_min": 60, "max_keys": 1},
    "pro": {"rate_limit_per_min": 1000, "max_keys": 5},
    "enterprise": {"rate_limit_per_min": 10000, "max_keys": 50},
}

def check_rate_limit(tier, requests_this_minute):
    limit = tiers[tier]["rate_limit_per_min"]
    return {"tier": tier, "limit": limit, "allowed": requests_this_minute <= limit}

print(check_rate_limit("pro", 500))
print(check_rate_limit("pro", 1500))
```

I ran this and got exactly the branching behavior I'd expect:

```
{'tier': 'pro', 'limit': 1000, 'allowed': True}
{'tier': 'pro', 'limit': 1000, 'allowed': False}
```

| Tier | Rate limit / min | Max API keys | Typical use case |
|---|---|---|---|
| Free | 60 | 1 | Evaluation, hobby projects |
| Pro | 1,000 | 5 | Production apps, small businesses |
| Enterprise | 10,000 | 50 | Large-scale integrations, custom SLAs |

This table becomes the single source of truth my rate-limiting middleware, my billing system, and my documentation all reference — instead of every part of the system encoding its own separate notion of "what a pro user gets."

### What Information Do They Actually Need Access To?

This is a scoping question I ask before I even think about implementation: not every signed-up user needs the same slice of the API. This is exactly where OAuth **scopes** (like the `read:products write:orders` I put in the JWT example above) or simpler per-tier permission flags earn their keep — they let me grant access proportional to actual need, rather than an all-or-nothing key that can do everything.

## Maintaining and Managing Accounts

Once accounts exist, a whole second set of operational questions kicks in — this is the stuff that doesn't show up until real users start using the API for real.

```mermaid
flowchart TD
    AccountLifecycle["Account Lifecycle"] --> Create2c["Sign-up"]
    Create2c --> Active2["Active use"]
    Active2 --> Reset["Password/key reset"]
    Active2 --> Monitor2["Usage monitoring"]
    Active2 --> Revoke2["Revocation"]
    Active2 --> Screen["Approval / screening<br/>(if required)"]
```

### Can I Reset User Passwords?

This sounds obvious, but I've genuinely seen early-stage APIs skip building this because "we'll add it later" — and then support tickets pile up. I never store a raw password, only a salted hash, which means I can *verify* a password but I can never *recover* the original — a reset always has to generate a brand-new credential, not "look up" the old one.

```python
import bcrypt

password = "correct-horse-battery-staple"
hashed = bcrypt.hashpw(password.encode(), bcrypt.gensalt())

print(bcrypt.checkpw(password.encode(), hashed))       # True
print(bcrypt.checkpw(b"wrong-password", hashed))        # False
```

Both checks ran exactly as expected — `True` for the correct password, `False` for a wrong guess — confirming the hash never needs to be reversed, only compared against.

> **Caution:** The same logic applies to API keys, not just passwords. I generate a random key, hand the raw value to the developer exactly once, and store only its hash. If my database is ever breached, an attacker gets hashes they can't reverse into working credentials — not the actual keys.

```python
import secrets
import hashlib

def generate_api_key(prefix="sk_live"):
    return f"{prefix}_{secrets.token_urlsafe(32)}"

api_key = generate_api_key()
key_hash = hashlib.sha256(api_key.encode()).hexdigest()

# Verifying a presented key later — recompute and compare hashes
is_valid = hashlib.sha256(api_key.encode()).hexdigest() == key_hash
print(is_valid)  # True
```

This produced a real key (`sk_live_...`, 32 random URL-safe bytes) and its SHA-256 hash, and confirmed the verification comparison works correctly.

### What Interface Do I Want for User Management?

I think about this along two axes: **self-service vs. assisted**, and **web UI vs. programmatic** (an admin API, a CLI, direct database access for internal staff). Most modern developer-facing APIs offer a self-service **developer portal** — a website where a user manages their own profile, keys, and billing — because it removes my team from the loop for routine tasks.

### Can Users Manage Accounts Through a Website, or Some Other Way?

If a full web dashboard isn't realistic yet, I consider lighter-weight alternatives: a small set of authenticated account-management API endpoints a CLI tool wraps, or even a semi-manual process (support-ticket-driven) for very early-stage or highly regulated products. The web dashboard is the ideal, not a hard requirement from day one.

### Can I Monitor Usage? Can Users Monitor Their Own?

I always want visibility into request volume, error rates, and which endpoints are actually being used — both for my own capacity planning and abuse detection, and because **giving users their own usage dashboard is itself a retention tool** (more on that below). Practically, this usually means every request gets logged with the authenticated identity attached, aggregated per user/per key.

### Can I Revoke Accounts?

Revocation has to be genuinely immediate, not "eventually consistent within a few hours." If a key is compromised or a user violates terms of service, I need a switch that stops that credential from working on the *next* request, not the next cache refresh.

```mermaid
sequenceDiagram
    participant Admin
    participant AuthSystem as Auth System
    participant APIGateway as API Gateway

    Admin->>AuthSystem: Revoke key sk_live_abc123
    AuthSystem->>AuthSystem: Mark key as revoked
    Note over APIGateway: Next request with that key
    APIGateway->>AuthSystem: Validate key
    AuthSystem-->>APIGateway: Revoked
    APIGateway-->>APIGateway: Reject with 401 Unauthorized
```

### Do I Need Approval or Screening?

Not every API is comfortable with instant, anonymous self-serve sign-up. If the API touches sensitive data (financial transactions, health records, personal data at scale) or has real abuse potential (sending SMS/email at volume, financial transfers), I add a manual or automated screening step — identity verification, business registration checks, a human review queue — before granting real access, even if a "free trial" tier can still be instant with tighter limits.

### Do I Need Reporting and Analytics on Users?

Beyond raw usage monitoring, I think about *developer-lifecycle* metrics specifically:

| Metric | What it tells me |
|---|---|
| Active developers | How many accounts are making real calls, not just registered |
| Engagement | Depth of usage — how many endpoints, how consistently |
| Retention rate | Whether developers who start using the API keep using it |
| Time-to-first-call | How much friction exists between sign-up and actual use |

These numbers usually matter more to product/business stakeholders than to me as the API designer directly, but I try to make sure the underlying data (who signed up, when, what they called, how often) is actually being captured from day one — because retrofitting historical analytics onto logs that were never designed to answer these questions is much harder than starting with them in mind.

## Integrating API Users Into the Rest of the Business

The last set of questions is about making sure the API doesn't become an island, disconnected from everything else the business already tracks.

```mermaid
flowchart LR
    APIUsers["API user accounts"] --> Sales["Sales / CRM"]
    APIUsers --> Support2["Support systems"]
    APIUsers --> ERP["ERP / billing"]
    APIUsers --> SSO2["Existing SSO"]
```

### Does Developer Activity Need to Map Into Sales, Support, and ERP?

If a developer signs up, integrates, and starts generating real production traffic, that's often a signal sales or customer success genuinely wants to know about — especially for usage-based billing or upsell opportunities. I think about whether developer/API events (sign-up, first call, hitting a usage tier, a spike in errors) should trigger events in a CRM or support system, rather than living only in API logs nobody outside engineering ever looks at.

### Does My API Key Structure Map Developers to Applications, Customers, and End Users?

This is a modeling question I take seriously early on, because retrofitting it is genuinely painful. A single flat "one key = one user" model breaks down fast once a *company* (my customer) has multiple *developers* building multiple *applications*, each potentially acting on behalf of many *end users*.

```mermaid
erDiagram
    CUSTOMER ||--o{ DEVELOPER : employs
    DEVELOPER ||--o{ APPLICATION : builds
    APPLICATION ||--o{ API_KEY : "issued for"
    APPLICATION ||--o{ END_USER : "serves"
```

Getting this hierarchy right up front means I can answer questions like "which customer does this API key ultimately belong to" or "which application is generating this traffic" without having to reconstruct that relationship after the fact from scattered records.

### Does User Data Need to Integrate with Existing Profiles? Can I Use Existing SSO?

Just like the very first question in this post, I check whether **identity** itself should be centralized. If my organization already runs an SSO system (SAML, OpenID Connect against an existing identity provider), I strongly prefer federating into that rather than maintaining yet another separate password store.

```mermaid
sequenceDiagram
    participant Developer
    participant MyAPIPortal as My API Developer Portal
    participant SSOProvider as Existing SSO Provider

    Developer->>MyAPIPortal: Click "Log in"
    MyAPIPortal->>SSOProvider: Redirect for authentication
    SSOProvider->>Developer: Prompt for credentials (if not already logged in)
    Developer->>SSOProvider: Authenticate
    SSOProvider-->>MyAPIPortal: Identity token
    MyAPIPortal->>MyAPIPortal: Create/link local developer profile
```

This keeps identity as a single source of truth and gives developers a familiar, trusted login experience instead of yet another password to manage — while my API portal still maintains whatever API-specific profile data (keys, tier, usage) it actually needs locally.

### Can I Create Usage Incentives Through Data Access?

Finally, I think about giving developers visibility into their **own** usage data as a genuine feature, not just an internal analytics artifact. A developer who can see their own call volume, error rates, and how close they are to a rate limit is more engaged, more likely to self-diagnose problems instead of filing a support ticket, and more likely to understand when it's time to upgrade tiers — which turns account monitoring from a purely defensive/operational feature into something that actually drives retention and expansion.

## Pulling It Together

| Category | Key questions I ask |
|---|---|
| Foundation | Reuse existing user management? Offer OAuth? |
| Sign-up | What's the process? Browser-based? Tiered? What data access is granted? |
| Maintenance | Password/key resets? Management UI? Usage monitoring? Revocation? Screening? Analytics? |
| Business integration | CRM/ERP mapping? Key structure hierarchy? SSO? Usage-based incentives? |

None of these questions have a universally correct answer — they depend heavily on the API's audience, sensitivity, and business model. But I've found that going through them deliberately, before writing a line of authentication code, saves me from the much more painful version of this exercise: redesigning user management after real developers are already depending on whatever I shipped first.
