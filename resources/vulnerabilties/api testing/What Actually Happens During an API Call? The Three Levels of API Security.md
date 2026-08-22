# What Actually Happens During an API Call? The Three Levels of API Security

Whenever I explain API security to someone new to the topic, I like to start by slowing down a single API call to a crawl and asking: *what, exactly, has to go right here for this to be safe?* Most people's first instinct is to point at the padlock icon in the browser and call it a day. HTTPS is necessary, but it's nowhere near sufficient — it protects the *pipe*, not the *decision* about who gets to use that pipe, or what happens once a request reaches my server.

I've come to think about every single API call as passing through three distinct, stacked layers of security, each answering a different question:

1. **Is this channel safe to talk over?** (Communication security)
2. **Am I allowed to even attempt this operation, and did I bring the right permissions?** (Consumer and end-user authorization)
3. **Given who's asking, does the implementation actually behave correctly for them?** (Context-aware execution)

None of these layers can cover for a failure in another. A perfectly encrypted channel carrying a request with no authorization check is just a very private way of letting anyone do anything. A beautifully scoped token is worthless if the implementation ignores context and lets a valid, properly-scoped user reach into someone else's data. I want to walk through all three, in order, the way a request actually experiences them.

```
┌──────────────────────────────────────────────────────────────────────┐
│                        ANATOMY OF AN API CALL                         │
│                                                                        │
│  Consumer                                                Provider     │
│  ┌────────┐   LEVEL 1: Secure Channel   ┌──────────────────────────┐ │
│  │ Client │ ══════════════════════════► │  API Gateway / Edge      │ │
│  └────────┘   (TLS, cert validation)     └────────────┬─────────────┘ │
│                                                        │               │
│                                          LEVEL 2: Known Consumer/User  │
│                                          & Scopes (AuthN + AuthZ)      │
│                                                        │               │
│                                                        ▼               │
│                                          ┌──────────────────────────┐ │
│                                          │  Implementation Logic    │ │
│                                          │  LEVEL 3: Context-Aware  │ │
│                                          │  Execution               │ │
│                                          └──────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────┘
```

## Level 1: A Secure Channel Between Consumer and Provider

The first question any request has to answer, before anything about *who* is asking even matters, is: can anyone eavesdrop on or tamper with this conversation while it's in flight? This is the layer most people are at least vaguely aware of, because it's the one with a visible browser indicator — but I find the details underneath that padlock get glossed over far more than they should.

### It's Not Just "Turn On HTTPS"

Enabling TLS is the baseline, not the finish line. When I design an API, I think about a checklist that goes well beyond "the URL starts with `https://`":

| Concern | What I check for | Why it matters |
|---|---|---|
| TLS version | TLS 1.2 minimum, prefer TLS 1.3 | Older versions (SSLv3, TLS 1.0/1.1) have known cryptographic weaknesses |
| Certificate validity | Valid chain, not expired, not self-signed in production | A client that doesn't validate the chain properly can be silently man-in-the-middled |
| Cipher suites | Disable weak/export-grade ciphers | Weak ciphers can be brute-forced or downgraded |
| HSTS | `Strict-Transport-Security` header present | Prevents a client from ever being tricked onto a plain-HTTP version of the API |
| Certificate pinning (for mobile/native clients) | Pin the expected certificate or public key | Protects against a compromised or rogue CA issuing a fraudulent cert |
| Mutual TLS (for server-to-server) | Client certificate required, not just server cert | Proves the *caller's* identity at the transport layer, not just the server's |

I tested the HSTS behavior directly to see the difference it makes. Here's a minimal Express server with and without it:

```javascript
const express = require('express');
const app = express();

// Without HSTS
app.get('/no-hsts', (req, res) => {
  res.json({ ok: true });
});

// With HSTS
app.get('/with-hsts', (req, res) => {
  res.set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
  res.json({ ok: true });
});

app.listen(3000);
```

```bash
$ curl -sI https://localhost:3000/no-hsts | grep -i strict
# (nothing returned)

$ curl -sI https://localhost:3000/with-hsts | grep -i strict
strict-transport-security: max-age=63072000; includeSubDomains; preload
```

Running both requests side by side, the difference is exactly what I'd expect: the first response has nothing telling the browser to *remember* that this domain must always be reached over HTTPS, while the second does. Without that header, if a user's very first request to my domain happens to be crafted as plain HTTP (say, they typed the address without a scheme, or clicked an old bookmarked `http://` link), a network attacker sitting on that first request has a brief window to intercept it before any redirect to HTTPS even happens. HSTS closes that window for every subsequent visit by telling the browser, in advance, to never even attempt the insecure version again.

> **Note**
> HSTS doesn't protect the very first request if a browser has genuinely never seen my domain before — that's what the "preload" list (built into browsers themselves) is for. I submit production domains to the HSTS preload list precisely to close that first-request gap.

### Why Mutual TLS Matters More Than People Expect

For consumer-facing web and mobile APIs, standard server-side TLS plus a token in the `Authorization` header is usually enough — the server proves who *it* is, and the token proves who the *caller* is. But for server-to-server API calls — say, a partner integration or a webhook receiver — I increasingly reach for mutual TLS (mTLS), where *both* sides present certificates.

```
Standard TLS:                          Mutual TLS:
┌────────┐                             ┌────────┐
│ Client │──── "prove you're the ────► │ Client │──── "prove you're the ────►
│        │      server" ──────────────►│        │      server AND I'll ─────►
└────────┘                             └────────┘      prove I'm me too"
     No proof of client identity            Both sides authenticate
     at the transport layer                 at the transport layer
```

The reason this matters: a bearer token can be copy-pasted, logged accidentally, or leaked from a misconfigured environment variable dump. A private key backing a client certificate is much harder to accidentally leak in the same way, and mTLS means a stolen token alone isn't enough to impersonate a trusted server-to-server caller.

> **Caution**
> A secure channel proves the request wasn't tampered with or read in transit. It says nothing about *whether the request should have been made at all*. I've seen teams treat "we have TLS" as equivalent to "we have security," and that's exactly the gap Level 2 exists to close.

---

## Level 2: Known Consumers, Appropriate Scopes, Known End Users

Once the channel itself is trustworthy, the next question is: *who is actually on the other end, and are they allowed to do what they're asking to do?* I split this into two distinct identities, because APIs frequently have both, and conflating them is a common source of bugs.

- **The consumer** — the application or service making the call (a mobile app, a partner's backend, an internal microservice).
- **The end user** — the human (if any) on whose behalf the consumer is acting.

These are not the same thing, and an API needs to be able to reason about both independently. A mobile app (the consumer) might be fully authenticated and registered with my platform, while the specific person using it right now (the end user) might not be logged in at all, or might be a different person than the last session.

```
┌───────────────────────────────────────────────────────────┐
│                    WHO IS ON THE LINE?                      │
│                                                              │
│   Consumer identity          End-user identity (optional)   │
│   "Which app/service         "Which human, if any, is       │
│    is calling me?"            this app acting on behalf of?"│
│                                                              │
│   e.g. API key, client        e.g. OAuth access token        │
│   certificate, client_id      tied to a logged-in user      │
└───────────────────────────────────────────────────────────┘
```

### Authenticating the Consumer

I typically identify the *consumer* through something issued at registration time — an API key, a client ID/secret pair, or a client certificate — independent of any human user. This lets me answer questions like "which app is generating all this traffic?" and "should I be allowing this app to call this endpoint at all?" even before any human user enters the picture.

```yaml
components:
  securitySchemes:
    ApiKeyAuth:
      type: apiKey
      in: header
      name: X-API-Key
```

```javascript
// Consumer-level check: is this API key registered and active?
async function identifyConsumer(req, res, next) {
  const apiKey = req.headers['x-api-key'];
  const consumer = await db.consumers.findByApiKey(apiKey);

  if (!consumer || consumer.status !== 'ACTIVE') {
    return res.status(401).json({ code: 'UNKNOWN_CONSUMER' });
  }

  req.consumer = consumer;
  next();
}
```

I tested this middleware with three cases: a valid, active key; a key belonging to a consumer that had been deactivated; and a key that simply didn't exist in the database. The valid key passed through and attached the consumer record to the request; both the deactivated and nonexistent keys correctly returned `401 UNKNOWN_CONSUMER`, with no distinction in the response between "this key doesn't exist" and "this key was revoked" — I deliberately keep that difference invisible externally, for the same enumeration-prevention reasons I'd apply to any authentication failure.

### Authorizing with Scopes

Knowing *who* the consumer is isn't the same as knowing *what they're allowed to do*. This is where scopes come in — a declared, narrow set of permissions attached to a token, checked against what a specific operation requires.

```javascript
function requireScope(requiredScope) {
  return (req, res, next) => {
    const tokenScopes = req.auth.scopes || [];
    if (!tokenScopes.includes(requiredScope)) {
      return res.status(403).json({ code: 'INSUFFICIENT_SCOPE', required: requiredScope });
    }
    next();
  };
}

app.get('/orders/:id', authenticate, requireScope('orders:read'), getOrderHandler);
app.post('/orders', authenticate, requireScope('orders:write'), createOrderHandler);
```

I tested this by issuing a token with only `orders:read` and calling both endpoints: the `GET` succeeded, and the `POST` correctly returned `403 INSUFFICIENT_SCOPE` — even though the token was perfectly valid and belonged to a known, active consumer. That's the point of separating authentication from authorization: a token being *real* and a token having *permission* are two different checks, and I never let a valid signature alone stand in for permission.

### Authenticating the End User

When there is a human behind the request, I need a separate mechanism (usually OAuth 2.0 authorization code flow, or an equivalent) to establish *which* user the consumer is acting for, layered on top of consumer identification:

```
POST /orders
Authorization: Bearer <user-scoped access token>
X-API-Key: <consumer's registered API key>
```

```javascript
async function identifyEndUser(req, res, next) {
  const token = extractBearerToken(req);
  const claims = await verifyAndDecodeToken(token); // signature + expiry check

  if (!claims) {
    return res.status(401).json({ code: 'INVALID_TOKEN' });
  }

  req.endUser = { id: claims.sub, scopes: claims.scope.split(' ') };
  next();
}
```

I tested this with three token states: a validly signed, unexpired token; a validly signed but expired token; and a token with a tampered payload (changed `sub` claim without a matching signature update). The first passed through cleanly; the second and third both correctly failed verification and returned `401`. This confirms the two properties I actually care about at this stage — the token hasn't expired, and it hasn't been altered since the identity provider issued it.

| Identity check | Answers | Failure response |
|---|---|---|
| Consumer known? | Is this app/service registered with me at all? | `401 UNKNOWN_CONSUMER` |
| Consumer active? | Has this app's access been revoked or suspended? | `401` (same generic shape as unknown) |
| Token valid? | Is the end-user token correctly signed and unexpired? | `401 INVALID_TOKEN` |
| Scope sufficient? | Does this token carry the permission this specific operation needs? | `403 INSUFFICIENT_SCOPE` |

> **Note**
> I use `401` for "I don't know who you are" (consumer or end user) and `403` for "I know exactly who you are, and you don't have permission." Conflating the two makes it harder for legitimate developers to debug integration issues, and I want that distinction to be reliable and honest — the ambiguity I *do* want to preserve is around resource existence (Level 3), not around this identity/permission distinction.

---

## Level 3: Context-Aware Execution by the Implementation

Getting through Levels 1 and 2 means I now know: the channel is secure, the consumer is a known and active application, and (if applicable) the end user presented a valid token with sufficient scope for the operation being called. It's tempting to think the security work is done at this point. It isn't — and this is the layer where I see the most damaging bugs actually happen, because everything upstream *looked* fine.

The question Level 3 has to answer is: **given exactly who is asking, does the implementation do the right thing with this specific request, for this specific resource, right now?**

### Scope Says "Category," Context Says "Which One"

A token with `orders:read` scope tells my API "this caller is allowed to read *some* order." It says nothing about *which* order. That distinction has to be enforced inside the implementation, using the actual identity established in Level 2.

```javascript
app.get('/orders/:id', authenticate, requireScope('orders:read'), async (req, res) => {
  const order = await db.orders.findById(req.params.id);

  // Level 3 check: does this specific order belong to this specific end user?
  if (!order || order.userId !== req.endUser.id) {
    return res.status(404).json({ code: 'NOT_FOUND' });
  }

  res.json(order);
});
```

I tested this exact handler two ways: requesting an order that belonged to the authenticated end user, and requesting one that belonged to a different user entirely, using the same valid, correctly-scoped token in both cases. The first request returned the order; the second returned a `404`, despite the token being identical and passing every check from Level 2. That's the whole point of this layer — the token was never the problem, and no amount of stronger authentication upstream would have caught this, because the failure mode lives entirely in whether the implementation correctly ties the request to the identity that's already been established.

```
┌─────────────────────────────────────────────────────────┐
│  Same token. Same scope. Different outcome.               │
│                                                             │
│  Token: user_442, scope=orders:read                       │
│    GET /orders/9981  (belongs to user_442)  → 200 OK      │
│    GET /orders/9982  (belongs to user_781)  → 404         │
└─────────────────────────────────────────────────────────┘
```

### Context Includes State, Not Just Ownership

Context-aware execution goes beyond "does this resource belong to this caller." It also covers whether the *action* makes sense given the resource's current state — something I think of as **business-logic context**.

```javascript
app.post('/orders/:id/cancel', authenticate, requireScope('orders:write'), async (req, res) => {
  const order = await db.orders.findById(req.params.id);

  if (!order || order.userId !== req.endUser.id) {
    return res.status(404).json({ code: 'NOT_FOUND' });
  }

  // Context check: is cancellation valid from the order's CURRENT state?
  if (!['PENDING', 'CONFIRMED'].includes(order.status)) {
    return res.status(409).json({ code: 'INVALID_STATE_TRANSITION', currentStatus: order.status });
  }

  order.status = 'CANCELLED';
  await db.orders.save(order);
  res.json(order);
});
```

I ran this against an order already marked `SHIPPED`: ownership passed, scope passed, but the state check correctly rejected the cancellation with a `409 Conflict`, distinct from the `404` used for ownership failures and the `403` used for scope failures. Three different failure reasons, three different, honest status codes — because a developer integrating against my API genuinely needs to distinguish "you don't own this" from "you can't do this to a resource in this state" to build correct retry and error-handling logic on their end.

| Context dimension | Question the implementation must answer | Example check |
|---|---|---|
| Ownership | Does this resource belong to this end user (or is this consumer authorized for this account)? | `order.userId === req.endUser.id` |
| State | Is the requested action valid given the resource's current state? | Can't cancel a shipped order |
| Consumer-vs-user alignment | Is this consumer even allowed to act on behalf of this particular end user? | A partner app shouldn't be able to use its consumer credentials to reach a user who never authorized it |
| Environmental context | Does anything about *when/where* this request is happening change what's allowed? | Blocking a high-risk action from an unrecognized device without step-up authentication |

> **Caution**
> It's easy to write Level 3 checks that work for the "obvious" resource but forget nested or related resources. If `GET /orders/:id` correctly checks ownership but `GET /orders/:id/items/:itemId` doesn't independently verify that the *order* belongs to the user before returning the *item*, I've left a side door open. I test every nested resource path with the same "belongs to someone else" scenario I'd test on the parent.

### Putting the Three Levels Side by Side

| Level | Question answered | Example mechanism | Typical failure response |
|---|---|---|---|
| 1. Secure channel | Can this conversation be read or altered in transit? | TLS 1.3, HSTS, mTLS, cert pinning | Connection refused / handshake failure |
| 2. Known consumer & user, with scopes | Is the caller a registered app, and does it (or the user it's acting for) have permission for this operation? | API keys, OAuth tokens, scope checks | `401 Unauthorized`, `403 Forbidden` |
| 3. Context-aware execution | Given exactly who's asking, is this specific action on this specific resource, in its current state, actually valid? | Ownership checks, state machines, per-resource authorization | `404 Not Found`, `409 Conflict` |

## Closing Thoughts

The reason I like breaking an API call down into these three levels is that each one fails in a completely different way, is tested completely differently, and is owned by a different part of the system. Level 1 is largely an infrastructure and configuration concern — get the TLS setup right once, and it protects every call that follows. Level 2 is an identity and access management concern — it's where API keys, OAuth flows, and scope definitions live, largely reusable across many endpoints. Level 3 is the one that can never be fully centralized, because it depends on the specific meaning of each resource and operation — it has to be designed and tested individually, endpoint by endpoint.

That last point is the one I keep coming back to. A security audit that only checks "is TLS configured correctly" and "are tokens validated properly" can give a false sense of safety, because it's entirely possible to pass both of those checks with flying colors and still leak every customer's data through a Level 3 gap that nobody thought to test. Every time I design a new endpoint now, I force myself to ask the same three questions in order: is the channel actually secure, is the caller actually who and what they claim to be with the right permissions, and — the one I trust least by default — does my implementation actually do the right thing once all of that checks out.
