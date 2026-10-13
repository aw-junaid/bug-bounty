# Walking Through My API Hacking Checklist, Phase by Phase

I keep a checklist taped to the inside of my mental "how do I test this API" folder, and every time I run through it on an engagement, I learn something new about why each step exists. In this post I want to go phase by phase through that checklist — testing approach, recon, endpoint analysis, auth testing, fuzzing, authorization testing, mass assignment, injection, rate limiting, and evasive techniques — and explain *why* I do each step, not just *that* I do it. I'll show some of the small scripts and commands I actually ran while putting this together, with real output, plus diagrams for the parts that are easier to see than to read.

> **Caution:** Every technique below assumes you have **explicit, written authorization** to test the target — a bug bounty scope, a signed pentest agreement, or your own lab environment. Running any of this against an API you don't own or haven't been authorized to test is illegal in most places, full stop. All commands in this post run against local sample data or intentionally-fake values — nothing here touches a real third party.

---

## Table of Contents

1. [The Checklist at a Glance](#glance)
2. [Phase 0 — Choosing a Testing Approach](#phase0)
3. [Phase 1 — Passive Reconnaissance](#phase1)
4. [Phase 2 — Active Reconnaissance](#phase2)
5. [Phase 3 — Endpoint Analysis](#phase3)
6. [Phase 4 — Authentication Testing](#phase4)
7. [Phase 5 — Fuzzing](#phase5)
8. [Phase 6 — Authorization Testing](#phase6)
9. [Phase 7 — Mass Assignment Testing](#phase7)
10. [Phase 8 — Injection Testing](#phase8)
11. [Phase 9 — Rate Limit Testing](#phase9)
12. [Phase 10 — Evasive Techniques](#phase10)
13. [My Takeaways](#takeaways)

---

<a id="glance"></a>
## 1. The Checklist at a Glance

Here's the whole thing compressed into one table, so you can see how the phases build on each other before I go deep on each one.

| Phase | Goal | Typical Output |
|---|---|---|
| 0. Testing Approach | Decide how much access/knowledge you start with | Black box / gray box / white box decision |
| 1. Passive Recon | Map the attack surface without touching the target directly | List of domains, subdomains, leaked secrets |
| 2. Active Recon | Interact with the target to confirm what's really there | Open ports, live hosts, discovered API paths |
| 3. Endpoint Analysis | Understand what each endpoint does and expects | Endpoint inventory, request/response map |
| 4. Authentication Testing | Probe how identity is proven | Weak login logic, token handling flaws |
| 5. Fuzzing | Throw structured garbage at everything | Crashes, errors, hidden parameters |
| 6. Authorization Testing | Check whether permission is actually enforced | BOLA / BFLA findings |
| 7. Mass Assignment | Check whether extra fields get silently accepted | Privilege escalation via hidden fields |
| 8. Injection Testing | Check whether user input reaches a dangerous sink | XSS, SQLi, OS command injection |
| 9. Rate Limit Testing | Check whether abuse is throttled | Brute-force and scraping feasibility |
| 10. Evasive Techniques | Get past filters/WAFs blocking the above | Encoded, mutated versions of prior attacks |

```mermaid
flowchart TD
    P0([Phase 0<br/>Pick approach]):::phase0 --> P1[Phase 1<br/>Passive recon]:::phase1
    P1 --> P2[Phase 2<br/>Active recon]:::phase2
    P2 --> P3[Phase 3<br/>Endpoint analysis]:::phase3
    P3 --> P4[Phase 4<br/>Auth testing]:::phase4
    P4 --> P5[Phase 5<br/>Fuzzing]:::phase5
    P5 --> P6[Phase 6<br/>Authorization testing]:::phase6
    P6 --> P7[Phase 7<br/>Mass assignment]:::phase7
    P7 --> P8[Phase 8<br/>Injection testing]:::phase8
    P8 --> P9[Phase 9<br/>Rate limit testing]:::phase9
    P9 --> P10[Phase 10<br/>Evasive techniques]:::phase10
    P10 -.loop back over<br/>anything blocked.-> P4
    P10 -.loop back over<br/>anything blocked.-> P6
    P10 -.loop back over<br/>anything blocked.-> P8

    classDef phase0 fill:#1f6feb,stroke:#0d3b76,color:#ffffff,stroke-width:2px
    classDef phase1 fill:#8957e5,stroke:#4c2889,color:#ffffff,stroke-width:2px
    classDef phase2 fill:#6e40c9,stroke:#3c1f82,color:#ffffff,stroke-width:2px
    classDef phase3 fill:#1b7fd4,stroke:#0c4a80,color:#ffffff,stroke-width:2px
    classDef phase4 fill:#d29922,stroke:#7a5705,color:#1a1a1a,stroke-width:2px
    classDef phase5 fill:#bf7a1f,stroke:#6b4511,color:#ffffff,stroke-width:2px
    classDef phase6 fill:#da3633,stroke:#7a1613,color:#ffffff,stroke-width:2px
    classDef phase7 fill:#c9427a,stroke:#7a1f4c,color:#ffffff,stroke-width:2px
    classDef phase8 fill:#67060c,stroke:#da3633,color:#ffffff,stroke-width:2px
    classDef phase9 fill:#2ea043,stroke:#1a6b2b,color:#ffffff,stroke-width:2px
    classDef phase10 fill:#1a1a1a,stroke:#000000,color:#ffffff,stroke-width:2px
```

I like this diagram because of the dotted lines at the bottom. Evasive techniques aren't a standalone "phase 10" I do once at the end — they're something I loop back and apply to auth, authorization, and injection testing specifically, any time a WAF or filter got in my way earlier.

---

<a id="phase0"></a>
## 2. Phase 0 — Choosing a Testing Approach

Before touching anything, I decide how much the client is giving me up front:

| Approach | What I'm given | What it simulates | Pros | Cons |
|---|---|---|---|---|
| **Black box** | Just a URL (or app) | A random external attacker | Realistic | Slow, may miss deep logic flaws |
| **Gray box** | Some credentials, maybe a Postman collection | A malicious or compromised customer/partner | Good balance | Still some blind spots |
| **White box** | Full API docs, source access, admin accounts | An insider threat or a thorough internal audit | Most thorough | Least "realistic" to an outsider's experience |

I almost always push for **gray box** when I can get it. Black box testing on APIs in particular burns a huge amount of time just rediscovering things that a five-minute conversation with the dev team would hand me — and APIs, unlike websites, often have *no visible UI at all* to even tell you they exist.

> **Note:** If a client insists on black box only, I make sure the timeline reflects that. API discovery without any hints takes real time, and rushing it is how BOLA bugs get missed.

---

<a id="phase1"></a>
## 3. Phase 1 — Passive Reconnaissance

Passive recon means I'm gathering information *without* sending traffic the target would notice as unusual — public records, search engines, source code, certificate transparency logs.

### Attack surface discovery

I'm looking for anything that points toward API infrastructure: subdomains like `api.`, `api-staging.`, `dev-api.`, `graphql.`; mobile app binaries that might embed API hostnames; API marketplaces; and old blog posts or conference talks from the engineering team that casually mention architecture.

### Checking for exposed secrets

This is one of my favorite parts of recon because it so consistently pays off. Client-side JavaScript bundles frequently ship with leftover API keys, internal hostnames, or debug flags that were never meant to reach production. Here's a scan I ran against a small sample file I built to demonstrate the technique (this is **not** a real key — it's a fake test value I generated):

```bash
$ cat /tmp/app.min.js
var config={endpoint:"https://api.example-test.local/v1",
apiKey:"sk_test_51Hc8fKDEMOkeyNOTreal0000",
debug:false};
function loadUsers(){return fetch(config.endpoint+"/users",{headers:{Authorization:"Bearer "+config.apiKey}});}
```

```bash
$ grep -EoiI '(api[_-]?key|secret|token|auth)[a-z0-9_"'"'"': =]{0,40}' /tmp/app.min.js
```

**Output:**
```
apiKey:"sk_test_51Hc8fKDEMOkeyNOTreal0000"
Authorization:"Bearer "
apiKey
```

That one-line regex scan found the key, confirmed there's an `Authorization: Bearer` header pattern to watch for, and did it in under a second. On a real engagement I'd run something similar across every JS bundle, mobile app decompile, GitHub repo, and public Postman collection I can find.

| Where I look for leaked secrets | Why it works |
|---|---|
| Minified/bundled JS | Build tools don't strip hardcoded strings |
| Mobile app APKs/IPAs (decompiled) | Developers assume reverse engineering is "too hard" |
| Public GitHub repos / gists | `.env` files and config get committed by accident constantly |
| Public Postman/Swagger collections | Shared "for convenience" and forgotten about |
| Archived pages (Wayback Machine, cached search results) | Old versions sometimes had secrets later removed from live code |

---

<a id="phase2"></a>
## 4. Phase 2 — Active Reconnaissance

Active recon is where I start actually touching the target — carefully, and only within scope.

### Scanning for open ports and services

A basic port scan tells me what's actually listening, which matters because APIs frequently run on non-standard ports (8080, 8443, 3000, 5000) separate from the main website.

```bash
# Against an explicitly-permitted scan target (never a system without authorization)
$ nmap -sV -p 80,443,3000,5000,8080,8443 scanme.nmap.org
```

> **Note:** `scanme.nmap.org` is the Nmap project's own purpose-built, publicly-permitted scan target — it's one of the only hosts on the internet you're allowed to port-scan without separate authorization, which is why it's the example here instead of a live client system.

### Use the application as intended

Before trying to break anything, I click through the app normally — sign up, log in, perform the core workflows — while watching the network tab. This builds my mental map of "what should a normal request look like," which is the baseline every later test gets compared against.

### Inspect with DevTools

I keep the browser's Network tab open the entire time, filtering by `Fetch/XHR`, and I pay attention to:

- Request headers (custom headers like `X-Api-Key`, `X-Client-Version`)
- Response headers (`Server`, rate-limit headers, CORS headers)
- Anything in `Initiator` pointing to a JS file I haven't reviewed yet

### Search for API-related directories

A quick, scoped content-discovery pass against common API paths is usually worth running early:

```bash
$ cat wordlist.txt
api
api/v1
api/v2
graphql
swagger
swagger.json
openapi.json
docs
internal
admin/api
```

Each of those is a reasonable guess because they're extremely common conventions — frameworks and API gateways default to them constantly.

### Discover API endpoints

By this point I usually have a growing list of confirmed endpoints. I keep it as a simple table as I go:

| Method | Endpoint | Auth required? | Notes |
|---|---|---|---|
| GET | `/api/v1/users/:id` | Yes | Returns full profile |
| POST | `/api/v1/auth/login` | No | Returns JWT |
| GET | `/api/v1/orders?user_id=` | Yes | user_id looks swappable — flag for Phase 6 |
| GET | `/swagger.json` | No | Full schema exposed publicly |

---

<a id="phase3"></a>
## 5. Phase 3 — Endpoint Analysis

### Find and review API documentation

If `/swagger.json` or `/openapi.json` is reachable, I pull it down and read it closely — it's effectively a free map of every endpoint, parameter, and expected data type, written by the developers themselves.

### Reverse engineer the API

When no docs exist, I reconstruct the schema by hand: using the app, capturing every request/response pair, and inferring the data model from field names and types.

### Use the API as intended, then analyze responses

This is the step I rush the least, because it's where excessive data exposure and business logic flaws hide in plain sight. I ask of every single response:

- Does this include fields the UI never displays?
- Does this include fields for *other* users, not just me?
- Does a "success" response ever leak information that an "error" response shouldn't (or vice versa)?

```mermaid
sequenceDiagram
    participant Me as Tester
    participant UI as Web/App UI
    participant API as API Server

    Me->>UI: Perform a normal action (e.g. view my profile)
    UI->>API: GET /api/v1/users/42
    API-->>UI: 200 OK { id, name, email, role, internal_notes }
    UI-->>Me: Renders only name + email
    Note over Me,API: The UI hides "role" and "internal_notes"...<br/>but the raw response still contains them!
    rect rgb(255, 235, 205)
    Me->>Me: Flag: excessive data exposure candidate
    end
```

---

<a id="phase4"></a>
## 6. Phase 4 — Authentication Testing

### Basic authentication testing

I check the fundamentals first: Does the login endpoint rate-limit failed attempts? Does it return different error messages for "wrong password" versus "user doesn't exist" (a username-enumeration leak)? Is multi-factor actually enforced server-side, or just in the UI flow?

### Attacking and manipulating API tokens

JSON Web Tokens are everywhere in modern APIs, and they're worth understanding structurally because a JWT is just three base64url-encoded segments — it is **not encrypted** by default, only signed. Here's a decode of a sample token I built for this demo (fake subject/claims, not from a real system):

```bash
$ python3 - << 'EOF'
import base64, json
jwt = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0Iiwicm9sZSI6InVzZXIiLCJpYXQiOjE3MDAwMDAwMDB9.fake_signature_demo"
header_b64, payload_b64, sig = jwt.split(".")

def pad_decode(s):
    s += "=" * (-len(s) % 4)
    return base64.urlsafe_b64decode(s)

print("Header:", json.dumps(json.loads(pad_decode(header_b64)), indent=2))
print("Payload:", json.dumps(json.loads(pad_decode(payload_b64)), indent=2))
EOF
```

**Output:**
```
Header: {
  "alg": "HS256",
  "typ": "JWT"
}
Payload: {
  "sub": "1234",
  "role": "user",
  "iat": 1700000000
}
```

Seeing `"role": "user"` sitting there in plain, readable JSON is exactly why I always check: does the server re-validate the role server-side on every request, or does it trust whatever role was in the token the moment it was issued? And separately — is the signature actually being verified, or would the server accept `"alg": "none"`, or a token re-signed with a key I can guess (like a published public key being reused as an HMAC secret)?

| Token weakness to test for | What I'm checking |
|---|---|
| Algorithm confusion (`none`, RS256→HS256) | Does the server strictly enforce the expected signing algorithm? |
| Long/no expiration | Can a stolen token be used indefinitely? |
| No revocation on logout/password change | Does logging out actually invalidate the token server-side? |
| Predictable or reused secrets | Is the signing secret unique per environment? |
| Sensitive data in the payload | JWTs are readable, not confidential, by design |

---

<a id="phase5"></a>
## 7. Phase 5 — Fuzzing

"Fuzz all the things" is blunt advice, but I take it seriously: parameter names, header values, path segments, content-types, and body fields all get thrown structured and semi-random input to see what breaks or what reveals a hidden code path.

A simple way I think about fuzzing scope before I touch a real tool like `ffuf`, ZAP, or Burp Intruder:

```bash
$ python3 - << 'EOF'
surfaces = ["URL path segments", "query parameters", "JSON body fields",
            "headers", "cookie values", "content-type header"]
payload_classes = ["empty string", "null", "huge string (10k+ chars)",
                    "negative number", "wrong type (string where int expected)",
                    "unicode/emoji", "SQL meta-characters", "path traversal sequences"]

print(f"Fuzzing matrix size: {len(surfaces)} surfaces x {len(payload_classes)} payload classes "
      f"= {len(surfaces) * len(payload_classes)} test cases per endpoint")
EOF
```

**Output:**
```
Fuzzing matrix size: 6 surfaces x 8 payload classes = 48 test cases per endpoint
```

That number — 48 test cases for just *one* endpoint, with a fairly modest matrix — is why fuzzing has to be automated. I'll scope which fields matter most per endpoint (an `id` field gets different payload classes than a `comment` text field) rather than firing the full matrix blindly everywhere.

> **Note:** Every unexpected `500` error during fuzzing is worth a second look. A stack trace in the response body is a free gift — it often names the framework, ORM, or even file paths, all useful for the injection testing phase ahead.

---

<a id="phase6"></a>
## 8. Phase 6 — Authorization Testing

### Discover resource identification methods

First I catalog how objects are referenced: sequential integers (`/orders/1042`), UUIDs, usernames, emails, or opaque tokens. Sequential IDs are the easiest to abuse and the first thing I test.

### Test for BOLA (Broken Object Level Authorization)

The core test is always the same shape: log in as **User A**, grab an object ID that belongs to **User B** (from recon, a shared resource, or just incrementing/decrementing my own ID), and request it using **User A's** session or token.

```mermaid
flowchart LR
    A[Log in as User A]:::step --> B[Note User A's object ID, e.g. 1042]:::step
    B --> C[Guess/obtain User B's ID, e.g. 1043]:::step
    C --> D["Request /orders/1043<br/>using User A's token"]:::attack
    D --> E{Response?}
    E -- "403/404" --> F[✅ Authorization enforced]:::good
    E -- "200 with User B's data" --> G[❌ BOLA confirmed]:::critical

    classDef step fill:#1f6feb,stroke:#0d3b76,color:#ffffff,stroke-width:2px
    classDef attack fill:#d29922,stroke:#7a5705,color:#1a1a1a,stroke-width:2px
    classDef good fill:#2ea043,stroke:#1a6b2b,color:#ffffff,stroke-width:2px
    classDef critical fill:#da3633,stroke:#7a1613,color:#ffffff,stroke-width:2px
```

### Test for BFLA (Broken Function Level Authorization)

Same idea, different target: instead of swapping an object ID, I try calling an **admin or privileged function** with a low-privilege account's token — `DELETE /api/v1/users/9`, `POST /api/v1/admin/export`, and so on. A lot of APIs enforce role checks only in the frontend's navigation menu (hiding the "Delete User" button from non-admins) while the underlying endpoint accepts the request from anyone with a valid token.

| Test | BOLA | BFLA |
|---|---|---|
| What's being abused | An object's **ID** | A **function/action** |
| Typical payload | Swap `id=1042` → `id=1043` | Call an admin-only route as a regular user |
| Root cause | Missing per-object ownership check | Missing per-role function check |

---

<a id="phase7"></a>
## 9. Phase 7 — Mass Assignment Testing

### Discover standard parameters used in requests

I capture a normal, legitimate request body first — say, a profile update:

```json
{ "name": "Jordan Example", "email": "jordan@example-test.local" }
```

### Test for mass assignment

Then I try adding fields that **aren't** part of the documented request but plausibly exist as columns in the underlying data model — `role`, `isAdmin`, `accountBalance`, `verified`:

```json
{
  "name": "Jordan Example",
  "email": "jordan@example-test.local",
  "role": "admin",
  "isVerified": true
}
```

If the API blindly binds the whole request body to the database object (common with ORMs that auto-map JSON to models), those extra fields get silently accepted and saved — and I've just self-promoted to admin through a profile update form.

> **Caution:** This is one of the highest-impact, lowest-effort tests on the whole checklist. It costs nothing to try and the payoff when it works is almost always critical severity. Always test it, even — especially — on endpoints that look "boring."

---

<a id="phase8"></a>
## 10. Phase 8 — Injection Testing

### Discover requests that accept user input

Anywhere user-controlled data flows into a query, a command, a template, or back out into a browser is a candidate.

### Test for XSS / XAS

For reflected or stored cross-site scripting, I check whether API responses that later get rendered in a browser (e.g., a comment field, a display name) properly encode output. A classic, extremely well-known test string like `<script>alert(1)</script>` submitted as a display name tells me a lot depending on whether it comes back encoded (`&lt;script&gt;`) or raw.

### Perform database-specific attacks

For SQL injection, I look at how input affects query logic — a well-known, textbook test payload like `' OR '1'='1` in a login or filter field is the standard first probe, because the *encoding* often matters more than the raw string when a WAF is in the way:

```bash
$ python3 - << 'EOF'
import urllib.parse
payload = "' OR '1'='1"
print("Raw        :", payload)
print("URL-encoded:", urllib.parse.quote(payload))
EOF
```

**Output:**
```
Raw        : ' OR '1'='1
URL-encoded: %27%20OR%20%271%27%3D%271
```

### Perform operating system injection

For OS command injection, I'm looking at any endpoint that might shell out to the underlying system — file conversion, image processing, ping/diagnostic utilities — and testing whether command-separator characters (`;`, `|`, `&&`) in input get passed through to a shell unsanitized.

| Injection type | Typical sink | What I'm watching for |
|---|---|---|
| XSS/XAS | API response rendered in a browser | Unencoded `<`, `>`, `"` in output |
| SQL injection | Database query built from input | Query logic changes, error messages, timing differences |
| OS command injection | Shell command built from input | Command separators reaching a shell |

> **Caution:** Injection testing can be destructive if you're not careful — a successful SQLi or command injection proof-of-concept can modify or delete real data. Use the least invasive payload that proves the point (a time-delay or an error message is usually enough), never a payload that drops tables or deletes files, and only on systems you're authorized to test.

---

<a id="phase9"></a>
## 11. Phase 9 — Rate Limit Testing

### Test for the existence of rate limits

I send a quick burst of requests and watch for `429 Too Many Requests`, `Retry-After` headers, or `X-RateLimit-*` headers. Here's a simulated version of what that pattern typically looks like once a limit kicks in (a local mock, not a real API call):

```bash
$ python3 - << 'EOF'
limit = 5
def mock_api_call(n):
    return 200 if n <= limit else 429

for i in range(1, 9):
    print(f"Request #{i}: HTTP {mock_api_call(i)}")
EOF
```

**Output:**
```
Request #1: HTTP 200
Request #2: HTTP 200
Request #3: HTTP 200
Request #4: HTTP 200
Request #5: HTTP 200
Request #6: HTTP 429
Request #7: HTTP 429
Request #8: HTTP 429
```

If I send 50 requests in a few seconds and every single one comes back `200`, that's a finding in itself — no throttling means brute-force and scraping attacks become far more practical.

### Test for methods to avoid rate limits

Sometimes a limit exists but is scoped too narrowly — per-IP instead of per-account, or per-endpoint instead of globally. I check whether rotating a header like `X-Forwarded-For`, switching endpoints that hit the same underlying logic, or alternating between HTTP/1.1 and HTTP/2 resets the counter.

### Test for methods to bypass rate limits

I also check whether slightly varying the request — different casing in the path, a trailing slash, an alternate but equivalent endpoint — causes the rate limiter to treat it as a "new" unthrottled resource.

| Rate-limit scoping mistake | How I test it |
|---|---|
| Limited per-IP only | Rotate `X-Forwarded-For` / `X-Real-IP` headers |
| Limited per-endpoint only | Hit a functionally-equivalent alternate route |
| Limited per-session only | Request new sessions/tokens between bursts |
| Case-sensitive path matching | Try `/API/Login` vs `/api/login` |

---

<a id="phase10"></a>
## 12. Phase 10 — Evasive Techniques

This phase is less a standalone step and more a toolbox I reach for whenever a WAF, input filter, or rate limiter blocked something I tried in an earlier phase.

### Add string terminators to attacks

Null bytes (`%00`) or other terminator characters can sometimes truncate a string early in one layer of validation while the rest of the string still reaches a vulnerable function downstream.

### Add case switching to attacks

Some naive filters block exact keyword matches (`SELECT`, `UNION`, `<script>`) case-sensitively. Mixing case is a trivial bypass against that specific (weak) kind of filter:

```bash
$ python3 -c "print('UnIoN sElEcT')"
```
**Output:**
```
UnIoN sElEcT
```

### Encode payloads

Filters that check the raw string miss payloads hidden behind encoding layers the backend will later decode anyway:

```bash
$ python3 - << 'EOF'
import urllib.parse
payload = "' OR '1'='1"
print("Single-encoded:", urllib.parse.quote(payload))
print("Double-encoded:", urllib.parse.quote(urllib.parse.quote(payload)))
EOF
```

**Output:**
```
Single-encoded: %27%20OR%20%271%27%3D%271
Double-encoded: %2527%2520OR%2520%25271%2527%253D%25271
```

Double-encoding matters specifically against systems where a proxy or WAF decodes once, sees nothing malicious, and forwards the request — but the application behind it decodes *again*, revealing the payload only after the filter already approved it.

### Combine different evasion techniques

The most effective evasion is rarely one trick alone — it's case-switching *plus* encoding *plus* a string terminator, layered together.

### Rinse and repeat

This is the step I find myself skipping when I'm tired, and it's always the wrong call: going back over every earlier phase — auth testing, authorization testing, injection testing — and reapplying whatever evasive technique just worked, because a filter that blocked one payload shape often still misses the same underlying bug delivered differently.

```mermaid
flowchart TD
    Start([Payload blocked<br/>by filter/WAF]):::blocked --> T1[Try string terminator]:::try
    Start --> T2[Try case switching]:::try
    Start --> T3[Try single/double encoding]:::try
    T1 --> Combine{Combine techniques}:::combine
    T2 --> Combine
    T3 --> Combine
    Combine --> Retest[Re-test against:<br/>auth, authz, injection phases]:::retest
    Retest --> Result{Bypassed?}
    Result -- Yes --> Success([Document finding +<br/>exact bypass used]):::success
    Result -- No --> Start

    classDef blocked fill:#da3633,stroke:#7a1613,color:#ffffff,stroke-width:2px
    classDef try fill:#d29922,stroke:#7a5705,color:#1a1a1a,stroke-width:2px
    classDef combine fill:#8957e5,stroke:#4c2889,color:#ffffff,stroke-width:2px
    classDef retest fill:#1f6feb,stroke:#0d3b76,color:#ffffff,stroke-width:2px
    classDef success fill:#2ea043,stroke:#1a6b2b,color:#ffffff,stroke-width:3px
```

---

<a id="takeaways"></a>
## 13. My Takeaways

Running through this checklist end to end, a few things stand out to me:

1. **Recon quality determines everything downstream.** Every later phase is only as good as the endpoint inventory and parameter list built during Phases 1–3. Rushing recon means fuzzing and authorization testing have blind spots you won't even know exist.
2. **BOLA and mass assignment are disproportionately high-value.** They're conceptually simple, fast to test, and consistently turn up critical findings — they're the first things I test on any new endpoint, not an afterthought.
3. **Evasion isn't a final phase, it's a multiplier.** I used to treat "evasive techniques" as the last checkbox. Now I think of it as something I re-apply to every earlier phase whenever something gets blocked, which is why the checklist's own structure loops back on itself.
4. **Nothing here works without a baseline.** "Use the application as intended" shows up twice in this checklist (recon and endpoint analysis) for a reason — you can't spot an abnormal response if you never established what a normal one looks like.

I run this same sequence on basically every API engagement now, and the order matters: skipping ahead to injection testing before finishing authorization testing, for example, means you'll often miss that the injection bug you just found is *also* reachable by an unauthorized user — which changes its severity entirely.
