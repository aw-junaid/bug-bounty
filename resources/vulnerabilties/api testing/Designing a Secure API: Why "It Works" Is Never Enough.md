# Designing a Secure API: Why "It Works" Is Never Enough

I've spent enough time building and reviewing APIs to have learned this lesson the hard way: an API can be fast, elegant, beautifully documented, and a joy to integrate with — and still be a liability. Usability and security are not opposites, but they *do* pull in different directions if I'm not deliberate about it. A flexible query parameter that lets a client fetch exactly the fields it needs is great for developer experience. It's also exactly the kind of feature that, if I'm careless, lets a stranger fetch fields they were never supposed to see.

That tension is what this post is about. I'm not going to talk about firewalls, WAFs, or TLS certificate rotation — those are important, but they're infrastructure problems, not design problems. I want to talk about the decisions I make *at the drawing board*, before a single line of implementation code is written, that determine whether my API is secure by construction or secure by accident.

> **Note**
> Everything here assumes you already have transport security (HTTPS everywhere) and basic authentication in place. This post is about what happens *after* someone is authenticated — how I decide what they can see, what they can do, and what happens when something goes wrong.

## Why This Matters More Than Ever

APIs quietly became the backbone of nearly every digital product I use or build. Every mobile app, every single-page web app, every IoT device, every microservice-to-microservice call — it's all APIs talking to APIs. That explosion in surface area is exactly why attackers have followed the money. APIs have become one of the most attractive attack vectors precisely *because* they are so numerous, so automatable, and so often overlooked by security reviews that focus on the "front door" web UI instead of the machine-readable interface sitting right next to it.

The kinds of problems I keep running into (and keep seeing in breach reports) fall into a recognizable set of buckets:

| Problem | What it looks like in practice |
|---|---|
| Broken authentication | Weak tokens, missing expiry, predictable session IDs |
| Broken object-level authorization | User A can fetch User B's order by changing an ID in the URL |
| Excessive data exposure | The API returns the whole database row and lets the client "just ignore" the fields it doesn't need |
| Business logic abuse | A discount code API doesn't check whether the code was already used |
| Lack of resource/rate limiting | A single client can hammer an endpoint until the database falls over |
| Shadow or zombie APIs | An old `/v1/users/export` endpoint nobody remembered to retire is still live and unauthenticated |
| Mass assignment | A client sends `{"isAdmin": true}` in a profile update and the server blindly applies it |
| Improper error handling | A stack trace in a 500 response reveals the ORM, framework version, and internal file paths |

None of these are exotic. All of them are things I can meaningfully reduce — sometimes eliminate — through how I *design* the API, independent of how well the backend team implements it. That's the point I want to drive home: security is not purely an implementation concern that gets bolted on afterward. It's a design discipline.

## The Mental Model: Minimize, Contextualize, Verify, Partition, Fail Safely

I find it useful to organize secure API design around five design habits. I'll walk through each one in depth, but here's the map first:

```
 ┌───────────────────────────────────────────────────────────┐
 │                     SECURE API DESIGN                      │
 ├───────────────┬───────────────┬───────────────┬───────────┤
 │   MINIMIZE     │ CONTEXTUALIZE │    VERIFY      │ PARTITION │
 │ Expose only    │ Behavior must │ Data integrity │ Scopes &  │
 │ what's needed  │ match context │ end-to-end     │ least     │
 │ (data & ops)   │ (who/when/how)│ (in transit &  │ privilege │
 │                │               │  at rest)      │           │
 └───────────────┴───────────────┴───────────────┴───────────┘
                          │
                          ▼
                 ┌─────────────────┐
                 │  FAIL SAFELY     │
                 │ Errors reveal    │
                 │ nothing extra    │
                 └─────────────────┘
```

Let's go through each pillar one at a time, with real examples.

---

## 1. Expose Only What's Necessary

The single biggest lever I have as an API designer is deciding what goes into the contract in the first place. Every field I expose, every operation I add, is a piece of attack surface. If it's not in the OpenAPI document, it can't be abused (well — unless it's a shadow API, which I'll get to).

### The Shopping API Problem

Imagine I'm designing an API for an e-commerce platform. Internally, my `Product` database table looks like this:

```sql
CREATE TABLE products (
    id              UUID PRIMARY KEY,
    name            TEXT,
    description     TEXT,
    retail_price    NUMERIC,
    wholesale_price NUMERIC,   -- what WE pay suppliers
    supplier_id     UUID,
    margin_percent  NUMERIC,
    internal_notes  TEXT,
    stock_quantity  INT
);
```

If I'm lazy, my "get product" endpoint might just serialize the whole row:

```json
GET /products/8f14e45f

{
  "id": "8f14e45f",
  "name": "Wireless Mouse",
  "description": "Ergonomic wireless mouse, 2.4GHz",
  "retail_price": 24.99,
  "wholesale_price": 6.50,
  "supplier_id": "b2a91c7e",
  "margin_percent": 74,
  "internal_notes": "Supplier delays expected in Q3",
  "stock_quantity": 412
}
```

I've just handed any curious customer with browser dev tools my supplier relationships, my margins, and my inventory levels. This is called **excessive data exposure**, and it's one of the most common API vulnerabilities I encounter, because it usually isn't caused by a bug — it's caused by convenience. Someone reused an internal serializer for a public response and trusted the client to "just not look" at the extra fields. Clients always look.

> **Caution**
> Never rely on the client to filter out sensitive fields. "The frontend just doesn't display that field" is not a security boundary. If it's in the HTTP response body, it has been exposed, full stop.

The fix is to design an explicit, minimal response schema — a DTO (data transfer object), not a database row:

```json
GET /products/8f14e45f

{
  "id": "8f14e45f",
  "name": "Wireless Mouse",
  "description": "Ergonomic wireless mouse, 2.4GHz",
  "price": 24.99,
  "inStock": true
}
```

Notice I also collapsed `stock_quantity: 412` into a boolean `inStock`. That's intentional — even inventory counts can be competitively sensitive or enable scraping-based attacks (e.g., timing a purchase right as stock runs low). I ask myself, for every field: *does the consumer of this API actually need this value, or just a derived fact about it?*

### Doing This in OpenAPI

This is exactly the kind of decision I like to encode directly into the contract, so implementers can't "accidentally" widen it later:

```yaml
components:
  schemas:
    Product:
      type: object
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
        description:
          type: string
        price:
          type: number
          format: float
        inStock:
          type: boolean
      required: [id, name, price, inStock]
      additionalProperties: false   # <-- reject anything not explicitly listed
```

`additionalProperties: false` is doing real work here. It's a design-time guardrail that says: even if the implementation team's ORM serializer changes and starts including `wholesale_price` by accident, a contract-testing tool validating against this schema will flag it before it reaches production.

### Minimizing Operations, Not Just Data

The same logic applies to *operations*, not just fields. I've seen APIs expose a generic `PATCH /products/{id}` that accepts and updates any field on the resource — including ones that should never be client-writable, like `wholesale_price` or `id` itself. This is the door that lets **mass assignment** attacks in.

```
❌ Bad: one endpoint, implicit trust
PATCH /products/8f14e45f
{ "wholesale_price": 0.01 }   -- accepted because the endpoint is generic

✅ Good: narrow, explicit operations
PATCH /products/8f14e45f/pricing   (admin-scope only)
PATCH /products/8f14e45f/description  (catalog-editor scope)
```

I design my write operations around *use cases*, not around "give me a way to edit any field." If a mobile app only ever needs to update a user's display name and avatar, I don't give it a generic `PUT /users/{id}` that happens to also accept a `role` field. I give it `PATCH /users/{id}/profile` with an explicit, narrow schema.

| Anti-pattern | Design fix |
|---|---|
| Generic `PUT`/`PATCH` accepting the full resource | Narrow, purpose-built operations per use case |
| Returning full DB rows | Explicit response DTOs, `additionalProperties: false` |
| "We'll just filter it in the frontend" | Filter it in the API contract itself |
| Undocumented debug/legacy endpoints | Formal deprecation & retirement process (kills shadow APIs) |

---

## 2. Ensure Operations Behave According to Context

Minimizing surface area solves *what* is exposed. The next question is *whether the same operation should behave differently depending on who's calling it, when, and under what conditions*. This is where **business logic abuse** and **broken object-level authorization (BOLA)** live — and these are notoriously hard to catch with automated scanners because, technically, nothing is "broken." The endpoint works exactly as coded. It's just missing a context check.

### The Classic BOLA Example

```
GET /orders/48213
```

If my API checks "is there a valid token?" but not "does this token's user actually own order 48213?", I have a BOLA vulnerability. An attacker just increments the ID and walks through every order in my system.

```javascript
// ❌ Vulnerable: authenticates, but doesn't authorize against context
app.get('/orders/:id', authenticate, async (req, res) => {
  const order = await db.orders.findById(req.params.id);
  res.json(order);
});

// ✅ Context-aware: authorization tied to the requester
app.get('/orders/:id', authenticate, async (req, res) => {
  const order = await db.orders.findById(req.params.id);
  if (!order || order.userId !== req.user.id) {
    return res.status(404).json({ error: 'Order not found' });
  }
  res.json(order);
});
```

I tested this pattern against a small Express service locally, and the difference in behavior is stark: without the ownership check, any authenticated user (even a brand-new, empty-cart account) can enumerate every order in the system just by changing an integer in the URL. With the check in place, cross-user requests come back as a generic 404 — which brings up a design point I care about: **I return 404, not 403, for objects the user doesn't own.** A 403 confirms the resource exists; a 404 doesn't confirm anything. That small choice reduces information leakage during ID-enumeration attacks.

> **Note**
> This is why BOLA (also called IDOR — Insecure Direct Object Reference) is consistently near the top of API vulnerability rankings. It's invisible to a functional test suite because the "happy path" always works. It only surfaces when someone tests with *someone else's* ID.

### The Discount Code Example (Business Logic Abuse)

Context-awareness isn't only about *who* is calling — it's also about *state* and *sequence*. Consider a `POST /cart/apply-discount` endpoint:

```json
POST /cart/apply-discount
{ "code": "WELCOME10" }
```

If my API only checks "does this code exist and is it not expired?" — but not "has this specific user already redeemed it?" or "is this a first-purchase-only code being applied to a tenth order?" — I've built an endpoint that is functionally correct and business-logic broken. A client can call it in a loop and stack the discount, or share a single-use code across thousands of accounts scripted in a botnet.

I design against this by explicitly modeling the *rules of the operation* into the API's behavior, not just its data shape:

| Context dimension | Question I ask at design time |
|---|---|
| Identity | Who is allowed to invoke this, and does the result depend on who they are? |
| Ownership | Does the target resource belong to the caller? |
| State | Is the resource in a state where this operation is valid? (e.g., can't ship a cancelled order) |
| Sequence | Has this operation already happened for this user/resource? (idempotency, one-time codes) |
| Rate | How often can this reasonably happen? (login attempts, discount applications) |

I usually document these rules directly as part of the operation description in the OpenAPI spec, and back them with explicit response codes (`409 Conflict` for state violations, `429 Too Many Requests` for rate violations) — not just a blanket `400 Bad Request` that hides *why* it failed.

---

## 3. Ensuring Data Integrity

Minimizing exposure and enforcing context stop unauthorized *reads* and abusive *writes*. Data integrity is about a different threat: **tampering** — making sure that data hasn't been altered in transit or that a request truly originated from where it claims to.

### In-Transit Tampering

HTTPS gives me confidentiality and prevents casual man-in-the-middle tampering, but it doesn't stop a legitimate, authenticated client from tampering with *its own* request in ways I don't want — like replaying an old request, or a malicious proxy sitting between a partner's server and mine modifying a webhook payload.

This is where I reach for request signing, similar to how many payment and webhook providers do it:

```
POST /webhooks/payment-confirmed
X-Signature: sha256=5d41402abc4b2a76b9719d911017c592
X-Timestamp: 1734691200

{ "orderId": "48213", "status": "paid", "amount": 24.99 }
```

The receiving server recomputes the HMAC over the raw body using a shared secret and compares it to `X-Signature`. I tested this pattern with a small Node script to make the mechanics concrete:

```javascript
const crypto = require('crypto');

function sign(payload, secret) {
  return crypto.createHmac('sha256', secret).update(payload).digest('hex');
}

function verify(payload, signatureHeader, secret) {
  const expected = sign(payload, secret);
  // timing-safe comparison — regular === is vulnerable to timing attacks
  return crypto.timingSafeEqual(
    Buffer.from(expected),
    Buffer.from(signatureHeader)
  );
}

// --- test ---
const secret = 'shared-webhook-secret';
const body = JSON.stringify({ orderId: '48213', status: 'paid', amount: 24.99 });

const signature = sign(body, secret);
console.log('Generated signature:', signature);
console.log('Valid signature accepted:', verify(body, signature, secret));      // true
console.log('Tampered payload rejected:', verify(
  JSON.stringify({ orderId: '48213', status: 'paid', amount: 0.01 }),
  signature,
  secret
));  // false
```

Running this locally produces exactly what I'd expect: the untampered payload verifies as `true`, and the moment I change `amount` from `24.99` to `0.01`, the signature no longer matches and `verify` correctly returns `false`. That's the whole point — the signature is a function of the *exact bytes* of the payload, so any modification, even a single character, invalidates it.

> **Caution**
> Notice I used `crypto.timingSafeEqual` instead of `===` to compare signatures. A naive string comparison returns early on the first mismatched character, which means comparison time correlates with how many leading characters are correct. That timing difference is measurable and has been used in real timing attacks to guess secrets byte by byte. Always use a constant-time comparison for anything security-sensitive.

I also include a timestamp header and reject requests where the timestamp is more than a few minutes old — this defeats **replay attacks**, where an attacker captures a valid, correctly-signed request and simply resends it later.

### Designing Integrity Into the Contract

At the design level, I document these expectations explicitly in the OpenAPI spec using custom headers and security scheme extensions, so implementers know signing isn't optional:

```yaml
paths:
  /webhooks/payment-confirmed:
    post:
      summary: Payment provider confirms a completed payment
      parameters:
        - name: X-Signature
          in: header
          required: true
          schema:
            type: string
          description: HMAC-SHA256 of the raw request body, hex-encoded
        - name: X-Timestamp
          in: header
          required: true
          schema:
            type: integer
          description: Unix timestamp; requests older than 300s are rejected
```

I also think about integrity for data *at rest*, not just in transit — checksums on uploaded files, version fields (`ETag` / optimistic concurrency tokens) to prevent lost-update race conditions where two clients overwrite each other's changes without knowing it:

```
GET /documents/9f21   → 200 OK, ETag: "v7"
PUT /documents/9f21
  If-Match: "v7"
  { ...updated content... }
→ 200 OK, ETag: "v8"

-- if someone else updated it in between --
PUT /documents/9f21
  If-Match: "v7"     -- stale!
→ 409 Conflict
```

This is a design pattern (`ETag` + `If-Match`), not an implementation detail — I bake it into the API contract so every write operation on a mutable resource supports optimistic concurrency by default.

---

## 4. Preventing Protocol and Infrastructure-Based Data Leaks

This is the category I find most people forget about, because it isn't about the JSON body at all — it's about everything *around* the response.

```
┌─────────────────────────────────────────────────────┐
│  HTTP RESPONSE                                       │
│  ┌─────────────────────────────────────────────┐    │
│  │ Status line, headers  ← leaks live here too! │    │
│  │  Server: Express/4.17.1                      │    │
│  │  X-Powered-By: PHP/7.4.3                     │    │
│  │  Cache-Control: (missing!)                   │    │
│  │  Set-Cookie: session=... (missing HttpOnly?) │    │
│  ├───────────────────────────────────────────────┤   │
│  │ Body (JSON)  ← what we usually focus on       │   │
│  └───────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────┘
```

A few concrete ways this bites me if I'm not careful:

| Leak vector | Example | Design mitigation |
|---|---|---|
| Verbose server headers | `Server: Apache/2.4.41 (Ubuntu)` | Strip/override framework and version headers |
| Response timing | Login endpoint takes measurably longer for valid usernames than invalid ones | Constant-time responses for auth-adjacent checks |
| Caching sensitive responses | A CDN caches an authenticated `/account/balance` response and serves it to the next visitor | Explicit `Cache-Control: no-store` on private endpoints |
| URL-based sensitive data | `GET /reset-password?token=abc123` | Tokens logged in browser history, proxy logs, referrer headers — put sensitive tokens in the body of a POST instead |
| Overly descriptive HTTP status/messages | `401 - Invalid password for user 'jsmith'` (confirms username exists) | Generic `401 - Invalid credentials` regardless of which part was wrong |
| CORS misconfiguration | `Access-Control-Allow-Origin: *` on an endpoint that returns personal data | Explicit origin allow-lists per endpoint sensitivity |

The **timing side-channel** on login endpoints is a subtle one I've actually tested. If my login logic looks like this:

```javascript
// ❌ Timing leak: fails fast for unknown users
async function login(username, password) {
  const user = await db.users.findByUsername(username);
  if (!user) {
    return { error: 'Invalid credentials' };   // returns almost instantly
  }
  const valid = await bcrypt.compare(password, user.passwordHash); // ~100ms
  return valid ? { token: issueToken(user) } : { error: 'Invalid credentials' };
}
```

...an attacker can measure response times: unknown usernames return in a few milliseconds (no DB user found, no bcrypt call), while valid usernames take ~100ms longer (because bcrypt ran). That timing gap, measured over enough requests, reveals which usernames exist in my system even though the error message is identical. The fix is to always do equivalent work on both paths:

```javascript
// ✅ Constant-time-ish: always run the expensive comparison
async function login(username, password) {
  const user = await db.users.findByUsername(username);
  const hashToCompare = user ? user.passwordHash : DUMMY_HASH; // pre-computed dummy
  const valid = await bcrypt.compare(password, hashToCompare);
  return (user && valid) ? { token: issueToken(user) } : { error: 'Invalid credentials' };
}
```

I actually benchmarked both versions with a simple loop against fake "known" and "unknown" usernames, and the difference was obvious in the first version (unknown-user responses returned almost immediately while known-user responses consistently took the full bcrypt round-trip) and disappeared in the second version once both code paths performed the same bcrypt comparison regardless of whether the user existed.

> **Note**
> None of this requires exotic tooling to design for — it requires *thinking about the response as a whole*, not just the JSON schema. When I write an OpenAPI spec, I now explicitly document response headers like `Cache-Control` for sensitive endpoints, right alongside the body schema.

---

## 5. Limiting Access with Security Scopes

Authentication answers "who are you?" Authorization answers "what are you allowed to do?" — and the design tool I use for the second question is **scopes**.

A flat model where every authenticated user or service can call every endpoint is a liability. I much prefer a model where tokens carry an explicit, narrow list of permissions, and every operation in my API declares which scope(s) it requires.

```yaml
components:
  securitySchemes:
    OAuth2:
      type: oauth2
      flows:
        authorizationCode:
          authorizationUrl: https://auth.example.com/authorize
          tokenUrl: https://auth.example.com/token
          scopes:
            orders:read: Read order history
            orders:write: Create or modify orders
            products:read: Read product catalog
            products:admin: Modify pricing and inventory (internal only)

paths:
  /products/{id}/pricing:
    patch:
      security:
        - OAuth2: [products:admin]
      summary: Update wholesale or retail pricing (internal staff only)

  /orders/{id}:
    get:
      security:
        - OAuth2: [orders:read]
      summary: Retrieve a single order
```

This design has a nice property: it lets me issue *different tokens for different clients* without touching a line of endpoint code. A public mobile app's token might only ever include `orders:read` and `products:read`. An internal admin dashboard's token includes `products:admin`. A partner's server-to-server integration might get `orders:write` but never `products:admin`. If the mobile app's token leaks (a very real, very common scenario — tokens end up in decompiled APKs, log files, or browser storage more often than anyone wants), the blast radius is limited to what that scope allows.

| Principle | Why it matters |
|---|---|
| Least privilege | Every token gets the minimum scopes needed for its purpose |
| Scope granularity | `orders:read` and `orders:write` as separate scopes, not one `orders` scope |
| Scope-to-endpoint mapping documented in the contract | Implementers can't "forget" to check a scope if it's declared in the spec |
| Short-lived tokens + refresh flow | Limits the damage window of a leaked token |

> **Caution**
> Scopes describe *categories* of access, not individual resource ownership. `orders:read` tells me a token is allowed to read *some* orders — it does not tell me *whose* orders. I still need the object-level context check from Section 2 on top of the scope check. Scopes and context-awareness are complementary, not substitutes for each other.

---

## 6. Erroring Securely

The last pillar is the one I see neglected most often, probably because errors feel like an afterthought compared to the "happy path." But an error response is still a response — it still leaves my system and lands in front of whoever sent the request, friendly or hostile.

### What Goes Wrong

```json
HTTP/1.1 500 Internal Server Error
{
  "error": "Unhandled exception",
  "message": "Cannot read property 'wholesalePrice' of undefined",
  "stack": "at ProductService.getPricing (/app/src/services/product.js:84:19)\n at ...",
  "sql": "SELECT wholesale_price FROM products WHERE id = $1"
}
```

This is a gift to an attacker. It reveals my file structure, my framework, my ORM, my table and column names, and confirms exactly where a bug lives that they might be able to trigger deliberately. I've genuinely seen production APIs do this, usually because a generic exception handler was left in "development mode" defaults.

### Designing Errors Deliberately

I treat error responses as first-class parts of the API contract, with the same rigor as success responses:

```yaml
components:
  schemas:
    Error:
      type: object
      properties:
        code:
          type: string
          example: "RESOURCE_NOT_FOUND"
        message:
          type: string
          example: "The requested resource could not be found."
        requestId:
          type: string
          description: Correlation ID for support/debugging — safe to expose, useless to an attacker
      required: [code, message, requestId]
      additionalProperties: false
```

```json
HTTP/1.1 404 Not Found
{
  "code": "RESOURCE_NOT_FOUND",
  "message": "The requested resource could not be found.",
  "requestId": "a1b2c3d4"
}
```

Everything an attacker needs to have *is not here*: no stack trace, no SQL, no internal paths, no framework fingerprint. What's left is everything a legitimate developer needs: a machine-readable `code` for programmatic handling, a human `message`, and a `requestId` that support teams can grep in internal logs — a detail that's safe precisely because it's meaningless outside my own systems.

I also standardize on a small, deliberate set of error codes across the whole API rather than letting every endpoint invent its own vocabulary, and I make sure error *specificity* never leaks state that should be private:

```
❌ "Invalid password"                → confirms username is valid
❌ "This coupon was already used by another account" → confirms coupon usage details
❌ "User with that email already exists" (on signup) → confirms account existence

✅ "Invalid credentials"
✅ "This coupon code is not valid"
✅ "If an account exists for that email, we've sent a confirmation" (on signup)
```

This last "account enumeration" pattern is one I apply consistently: anywhere an error message could confirm or deny the existence of an account, a coupon, or any other private fact, I phrase the response so it's true regardless of the underlying reality.

> **Note**
> Rate limit responses are part of this too. A well-designed `429 Too Many Requests` includes a `Retry-After` header so legitimate clients back off gracefully, without needing to expose *why* the limit was configured the way it was (e.g., don't reveal "you've made 4 of your 5 allowed login attempts" — that tells an attacker exactly how much runway they have left).

---

## Bringing It Together in the OpenAPI Document

None of these five pillars live only in a design document I write once and forget. The OpenAPI spec is where they become enforceable — implementers, contract-testing tools, and API gateways can all validate against it. A well-designed operation definition touches every pillar at once:

```yaml
paths:
  /orders/{orderId}:
    get:
      summary: Retrieve an order (owner only)
      security:
        - OAuth2: [orders:read]          # (5) scope
      parameters:
        - name: orderId
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Order found and owned by the caller       # (2) context implied
          headers:
            Cache-Control:
              schema: { type: string, example: "no-store" }       # (4) infra leak prevention
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Order'                 # (1) minimal DTO
        '404':
          description: Not found, or not owned by caller           # (2) generic on purpose
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'                  # (6) safe error shape
```

## Closing Thoughts

If there's one habit I'd want any API designer to take from this, it's to stop treating security as something that happens in a separate review, after the "real" design work is done. Every schema I write is a decision about exposure. Every endpoint is a decision about context. Every error message is a decision about what I'm willing to reveal to someone I've never met and can't verify the intentions of.

None of the five pillars I've walked through here — minimizing surface area, enforcing context, guaranteeing integrity, closing infrastructure leaks, partitioning with scopes, and failing safely — require exotic tools. They require asking a slightly different question at design time: not just "does this work?" but "what does this reveal, to whom, and under what conditions could that go wrong?" That single habit, applied consistently across every endpoint I design, does more for security than almost any tool I could bolt on afterward.
