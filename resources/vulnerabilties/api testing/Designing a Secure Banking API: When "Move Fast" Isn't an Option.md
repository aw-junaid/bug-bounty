# Designing a Secure Banking API: When "Move Fast" Isn't an Option

I've designed APIs for e-commerce platforms, internal tooling, and SaaS products, and in every one of those contexts I could tell myself "if we get this wrong, we'll patch it and apologize." The first time I sat down to design an API sitting in front of a core banking system, that comfort disappeared. A bug in a product catalog API embarrasses you. A bug in a banking API moves money that isn't yours to move, or shows one customer another customer's balance. There is no "we'll fix it in the next release" in a domain where every request can be a financial transaction with legal, regulatory, and irreversible consequences.

So this post is my attempt to take everything I know about secure API design — minimizing exposure, contextual authorization, data integrity, infrastructure leak prevention, scopes, and safe error handling — and rebuild it from the ground up for a banking system, where the stakes and the constraints are genuinely different. I'll use a hypothetical retail bank's API layer as my running example: account balances, transfers, card management, and open banking (third-party) access.

> **Note**
> I'm designing at the API contract level here, not the cryptographic core-banking-ledger level. I won't pretend to reinvent double-entry bookkeeping or HSM key management — those are specialist domains. What I *can* do is make sure the API surface sitting in front of that core doesn't undermine it.

## Why Banking Changes the Calculus

Every principle from general API security still applies to a bank, but three things make the stakes different:

1. **Irreversibility.** An e-commerce order can be refunded. A fraudulent wire transfer that's already cleared through a correspondent bank often can't be pulled back at all.
2. **Regulation, not just best practice.** PSD2, PCI DSS, SOC 2, GLBA, and local banking regulators don't treat "we followed OWASP guidance" as optional — many of these controls are legally mandated, with audits and fines attached.
3. **A much wider set of legitimate callers.** A modern bank's API isn't just serving its own mobile app anymore. Under open banking regimes, *other companies' apps* call into my bank on a customer's behalf, using tokens my bank never directly controls the lifecycle of. That third-party surface is enormous, and it's exactly where a lot of real-world banking API incidents have originated.

```
┌───────────────────────────────────────────────────────────────┐
│                     WHO CALLS A BANK'S API                     │
│                                                                 │
│   [Bank's own mobile app] ───┐                                 │
│   [Bank's web portal]     ───┼──► API Gateway ──► Core Banking │
│   [Branch/teller systems] ───┤        │                        │
│   [ATM network]           ───┤        │                        │
│   [Third-party fintech     ] ┘        ▼                        │
│    apps via Open Banking          Auth / Fraud / Audit layer   │
└───────────────────────────────────────────────────────────────┘
```

With that context in mind, let's rebuild the six pillars for this world.

---

## 1. Expose Only What's Necessary — Applied to Money and Identity

In my e-commerce example, the worst-case leak was a wholesale price. In banking, the equivalent careless design leaks account numbers, balances, counterparties, and enough personal data to enable identity theft or account takeover. The blast radius of "just return the whole database row" is categorically worse here.

### The Account Details Problem

A naive `GET /accounts/{id}` pulled straight from an ORM might look like this:

```json
{
  "accountId": "acc_88214",
  "customerId": "cust_44210",
  "customerSSN": "078-05-1120",
  "accountNumber": "1234567890",
  "routingNumber": "021000021",
  "balance": 18234.55,
  "availableBalance": 18234.55,
  "creditScore": 712,
  "riskFlag": "LOW",
  "internalRelationshipManagerNotes": "High net worth, upsell wealth mgmt",
  "openedDate": "2019-03-14",
  "branchId": "br_0042"
}
```

Every field here beyond the basics — SSN, credit score, internal sales notes, risk flags — has no business being in a response to the account holder's own banking app, let alone a third-party budgeting app connected via open banking. This isn't a hypothetical; "excessive data exposure" through banking and fintech APIs returning far more customer PII than the client needed has been a recurring theme in real breach disclosures over the past several years.

I redesign this as a **purpose-scoped response**, and — critically for banking — I design *different* response shapes for *different consumers* of the same underlying resource, rather than one generic representation:

```json
// GET /accounts/{id}  — customer-facing app, own account
{
  "accountId": "acc_88214",
  "displayName": "Everyday Checking",
  "maskedAccountNumber": "****7890",
  "availableBalance": 18234.55,
  "currency": "USD",
  "status": "ACTIVE"
}
```

```json
// GET /accounts/{id}/summary — open banking (third-party, read-only, consented)
{
  "accountId": "acc_88214",
  "accountType": "CHECKING",
  "currency": "USD",
  "availableBalance": 18234.55
}
```

Notice the open banking response is *even narrower* than the bank's own app — no display name, no masked account number. Third parties should get the absolute minimum required to deliver the feature the customer consented to (say, a budgeting app that only needs balances, not account numbers).

| Field | Bank's own app | Open banking (3rd party) | Internal ops/staff tools |
|---|---|---|---|
| Masked account number | Yes | No | Yes (masked) |
| Full account number | No (never, even to owner, via API) | No | Yes (audited access only) |
| SSN / national ID | No | No | Yes (separate, heavily audited endpoint) |
| Available balance | Yes | Yes (with consent scope) | Yes |
| Credit score / risk flags | No | No | Yes (specific risk-team scope) |
| Relationship manager notes | No | No | Yes (specific role only) |

> **Caution**
> I've deliberately said "never, even to owner, via API" for the *full* account number. Even though it's the customer's own data, the identifiers used to move money (full account + routing numbers) are exactly what account-takeover fraud needs. I mask them by default everywhere and require a distinct, heavily rate-limited, re-authenticated ("step-up auth") operation if a legitimate flow genuinely needs the unmasked number — e.g., setting up a wire.

### Minimizing Operations: The Transaction Endpoint

The same discipline applies to write operations, and here banking gives me a sharper example than e-commerce ever did. A generic `POST /accounts/{id}/transactions` that accepts an arbitrary transaction type and amount is a business-logic and fraud nightmare. I split transaction-initiating operations by *intent*, each with its own validation rules, limits, and required authentication strength:

```
POST /transfers/internal        (between the customer's own accounts)
POST /transfers/domestic        (to another customer/bank, ACH-style)
POST /transfers/international   (SWIFT/wire — requires step-up auth)
POST /cards/{id}/freeze
POST /cards/{id}/limits         (spending limit changes)
```

Collapsing all of these into one "generic transaction" endpoint would mean a single authorization bug compromises every kind of money movement at once. Splitting them means an international wire — the highest-risk, hardest-to-reverse operation — can carry its own stricter rules (lower rate limits, mandatory step-up authentication, mandatory manual review above a threshold) without those rules leaking into or being weakened by the low-risk internal transfer path.

---

## 2. Ensuring Operations Behave According to Context — Banking's Hardest Problem

If BOLA (one user reading another user's data by changing an ID) was a serious problem for a Shopping API, it's an existential one for a bank. And banking introduces a second, subtler context problem that e-commerce rarely has to the same degree: **transaction-state and velocity abuse** — essentially, business logic abuse where the "product" is money itself.

### BOLA on Steroids: Account Enumeration

```
GET /accounts/acc_88214/transactions
```

If my authorization check is "is this a valid token?" instead of "does this token's customer actually own `acc_88214`?", I've built an API that lets anyone with *any* valid account read *anyone's* transaction history by walking through account IDs. I tested this exact scenario against a small mock service:

```javascript
// Vulnerable version
app.get('/accounts/:id/transactions', authenticate, async (req, res) => {
  const txns = await db.transactions.findByAccountId(req.params.id);
  res.json(txns);
});

// Context-aware version, with an audit trail on top
app.get('/accounts/:id/transactions', authenticate, async (req, res) => {
  const account = await db.accounts.findById(req.params.id);

  if (!account || account.customerId !== req.user.customerId) {
    await auditLog.record({
      event: 'UNAUTHORIZED_ACCOUNT_ACCESS_ATTEMPT',
      actorId: req.user.customerId,
      targetAccountId: req.params.id,
      timestamp: Date.now()
    });
    return res.status(404).json({ code: 'NOT_FOUND' });
  }

  const txns = await db.transactions.findByAccountId(req.params.id);
  res.json(txns);
});
```

I tested both versions with a second test-user account and a hardcoded "victim" account ID: the first version happily returned the victim's full transaction list; the second correctly returned a 404 *and* wrote an audit event. That audit line is the banking-specific addition I wouldn't necessarily bother with on a shopping API — in a regulated environment, I don't just want to *block* an unauthorized access attempt, I want a permanent, queryable record that it was attempted, because a pattern of these attempts across many accounts from the same source is itself a fraud signal my security team needs to see.

### Velocity and State: The Real Business Logic Abuse

The context problems that are unique to banking mostly aren't about *who* is calling — they're about *sequence, timing, and state*. A few real patterns I design against explicitly:

| Abuse pattern | What it looks like | Design mitigation |
|---|---|---|
| Transaction replay | Same transfer request sent twice (double-click, retry after timeout, or malicious replay) | Mandatory idempotency keys on every money-movement endpoint |
| Velocity abuse | 200 small transfers in 60 seconds to drain an account just under a fraud threshold | Per-account and per-customer rate limits *and* cumulative amount limits over rolling windows |
| Race condition on balance | Two simultaneous withdrawal requests both read balance = $100 before either is committed, both succeed, account goes to -$50 | Atomic, serialized balance checks at the ledger level; API layer must not "check-then-act" across two calls |
| Stale-state transitions | Trying to reverse a transfer that's already settled | Explicit state machine per transaction; operations only valid from specific states |

Idempotency deserves its own spotlight, because it's the single most important pattern I didn't need nearly as urgently in the e-commerce world but is non-negotiable in banking:

```
POST /transfers/domestic
Idempotency-Key: 6f8a1c2e-9b3d-4e11-8a77-2d5f9c001abc

{
  "fromAccountId": "acc_88214",
  "toAccountId": "acc_99031",
  "amount": 500.00,
  "currency": "USD"
}
```

```javascript
async function handleTransfer(req, res) {
  const key = req.headers['idempotency-key'];
  if (!key) {
    return res.status(400).json({ code: 'IDEMPOTENCY_KEY_REQUIRED' });
  }

  const existing = await db.idempotencyRecords.find(key);
  if (existing) {
    // Same key seen before: return the ORIGINAL result, don't re-execute
    return res.status(existing.statusCode).json(existing.responseBody);
  }

  const result = await ledger.executeTransfer(req.body); // atomic, single execution
  await db.idempotencyRecords.save(key, result);
  return res.status(result.statusCode).json(result.responseBody);
}
```

I tested this by firing the exact same `POST` twice in a row (simulating a mobile client that retried after a slow network response) with the same `Idempotency-Key`: the first call executed the transfer and returned `201 Created`; the second call, despite hitting the handler again, returned the *stored* result without moving any additional money. Removing the idempotency check and running the same test moved $500 twice. That single header, and the storage behind it, is the difference between a flaky network connection costing a customer $500 and it costing them nothing.

> **Note**
> I document idempotency as a *required*, not optional, header on every state-changing financial endpoint in the OpenAPI spec, and I make the contract testing suite reject any money-movement operation definition that doesn't declare it.

---

## 3. Data Integrity — Ledgers Don't Forgive Tampering

I talked about HMAC-signed webhooks in the general API context. In banking, integrity concerns show up everywhere, but two are specific to this domain: **ledger immutability** and **inter-bank message integrity**.

### Append-Only, Not Update-in-Place

A core design decision I insist on: the transaction ledger is **append-only**. My API never exposes an operation that edits or deletes a historical transaction record. A reversal is a *new* transaction that references the original, not a mutation of it.

```
Never expose:  PATCH /transactions/tx_5521  { "amount": 0 }
Instead:       POST  /transactions/tx_5521/reversals
```

```json
// Original transaction — untouched, forever
{ "id": "tx_5521", "amount": -500.00, "status": "SETTLED" }

// Reversal — a new record referencing the original
{ "id": "tx_6034", "reversalOf": "tx_5521", "amount": 500.00, "status": "SETTLED" }
```

This design choice alone eliminates an entire category of tampering and fraud-covering: nobody, not even someone with a compromised admin credential, can silently rewrite history through the API, because the operation to do so doesn't exist in the contract at all.

### Inter-Bank Message Integrity

When my bank talks to another bank or a payment network, message integrity is usually handled by the messaging standard itself (ISO 20022 messages are typically signed, and SWIFT, ACH, and card networks all layer in their own authentication). But at *my* API boundary — say, a webhook telling my system "this incoming wire has settled" — I apply the same signed-webhook pattern from general API design, with one addition specific to money: **dual control on high-value confirmations**.

```javascript
function verifyAndProcessSettlement(payload, signature, secret, amount) {
  const valid = verifyHmac(payload, signature, secret);
  if (!valid) {
    throw new Error('SIGNATURE_INVALID');
  }
  if (amount > HIGH_VALUE_THRESHOLD) {
    // Even a validly-signed high-value settlement gets queued
    // for a second, independent automated check before the ledger updates
    return queueForDualControlReview(payload);
  }
  return ledger.applySettlement(payload);
}
```

I tested this with a mock settlement of $2,000,000 against a $500,000 threshold: a validly signed payload above the threshold correctly routed to the review queue instead of applying directly, while an identical payload at $50,000 applied immediately. The signature check alone tells me the message wasn't tampered with in transit — it doesn't tell me the *sender's own system* wasn't compromised or didn't make an error. For sufficiently large amounts, I don't let a single automated integrity check be the last line of defense.

### Optimistic Concurrency at the API Layer

The `ETag` / `If-Match` pattern I use for documents in general API design maps directly onto things like updating a standing order or a card's spending limit — anywhere two concurrent requests could otherwise silently clobber each other:

```
GET /cards/card_331/limits        → 200 OK, ETag: "v3"
PATCH /cards/card_331/limits
  If-Match: "v3"
  { "dailyLimit": 2000.00 }
→ 200 OK, ETag: "v4"
```

I never allow this pattern to apply to the *balance* itself, though — balance changes only ever happen through the atomic ledger operations described above, never through a generic `PATCH`. Some resources are too sensitive for even a "safe-looking" generic update pattern.

---

## 4. Preventing Protocol and Infrastructure Leaks — Higher Stakes, Same Playbook

Everything from general API design applies directly here, plus banking-specific reinforcements:

| Leak vector | Banking-specific concern | Mitigation |
|---|---|---|
| Response timing on login/PIN checks | Reveals whether an account number or card PIN is "close" to correct | Constant-time comparisons, uniform response timing regardless of match closeness |
| Caching | A shared proxy or CDN caching an authenticated balance response | `Cache-Control: no-store, no-cache` on every authenticated banking endpoint, enforced at the gateway, not just per-endpoint code |
| CORS | A misconfigured wildcard origin on an account API lets any website's JavaScript read logged-in users' balances | Explicit origin allow-lists; banking APIs typically shouldn't be callable directly from arbitrary browser JS at all — route through backend-for-frontend patterns |
| mTLS for server-to-server | Interbank and open-banking connections without mutual TLS | Certificate-based mutual authentication in addition to OAuth tokens for machine-to-machine banking traffic |
| Verbose infra headers | `Server: nginx/1.18.0` on a public banking API | Strip at the gateway layer for every environment, not just production (dev habits leak into prod) |

The **mTLS** point is worth dwelling on because it's the clearest way banking infrastructure design diverges from a typical consumer API. Under most open banking regulatory frameworks, a third-party app doesn't just present an OAuth bearer token to my API — the *connection itself* is established using a certificate issued to that specific registered third-party provider, verified by my gateway before the request is even routed to application code. This means a stolen bearer token alone, without the corresponding private key and certificate, isn't enough to impersonate that third party.

```
┌───────────────┐        mTLS handshake         ┌───────────────┐
│  Third-Party   │ ── (client cert required) ──► │  Bank's API    │
│  Fintech App   │ ◄───────────────────────────  │  Gateway       │
└───────────────┘        + OAuth Bearer          └───────────────┘
                          token in header
```

I design this as two independent layers on purpose: the network/transport layer proves "this connection is genuinely from a registered third-party provider," and the application layer's OAuth token proves "and this specific customer consented to this specific scope of access." Neither one alone is sufficient.

---

## 5. Limiting Access with Security Scopes — Open Banking Makes This Non-Negotiable

Scopes went from "good practice" in my e-commerce example to "the entire regulatory basis for third-party access" in banking. Open banking frameworks are essentially built around the idea that a customer can grant a third-party app a narrow, explicit, revocable, time-boxed scope of access to their financial data — nothing more.

```yaml
components:
  securitySchemes:
    OpenBankingOAuth2:
      type: oauth2
      flows:
        authorizationCode:
          authorizationUrl: https://auth.mybank.example/authorize
          tokenUrl: https://auth.mybank.example/token
          scopes:
            accounts:balances:read: Read account balances only
            accounts:transactions:read: Read transaction history
            payments:initiate: Initiate a single payment (with explicit customer confirmation)
            cards:read: Read card metadata (no PAN, no CVV)

paths:
  /open-banking/accounts/{id}/balances:
    get:
      security:
        - OpenBankingOAuth2: [accounts:balances:read]

  /open-banking/payments:
    post:
      security:
        - OpenBankingOAuth2: [payments:initiate]
      description: >
        Requires an explicit, separate customer confirmation step
        (redirect back to the bank's own authentication flow) —
        the scope alone does not authorize execution of the payment.
```

Notice that `payments:initiate` explicitly does *not* mean "this third party can move money whenever it wants." Under most open banking models, payment initiation always requires the customer to be redirected back to the bank's own authenticated interface to confirm the specific payment — the scope authorizes the third party to *start* that flow, not to complete a transfer unilaterally. I document that distinction directly in the API description because it's exactly the kind of nuance that gets lost if an implementer just sees "scope: payments:initiate" and assumes scope equals full authority.

| Token holder | Typical scopes | Never gets |
|---|---|---|
| Bank's own mobile app | Full account read/write, all transfer types | N/A — but still uses step-up auth for high-risk ops |
| Budgeting app (open banking) | `accounts:balances:read`, `accounts:transactions:read` | Any write scope, card details, SSN |
| Payment initiation fintech | `payments:initiate` (with mandatory confirmation redirect) | Balance/transaction read access unless separately granted |
| Internal fraud analyst tooling | `fraud:review:read`, `fraud:review:write` | Direct transfer-initiation scopes |
| ATM network service | `accounts:balances:read`, `cash:withdrawal:execute` (its own narrow scope) | Card management, profile changes |

> **Caution**
> Scope creep is a real, slow-moving risk in banking APIs. A scope like `accounts:read` that started narrow can quietly grow new fields over years of "just add this one more field the partner needs" requests until it's effectively `accounts:read-everything`. I treat every new field added to an existing scoped response as requiring the same scrutiny as creating a brand-new scope — because in practice, that's what it is.

---

## 6. Erroring Securely — Where Fraud Prevention and UX Collide

Secure error design in banking has an extra wrinkle I didn't face in e-commerce: fraud prevention systems often want an error to be deliberately vague to the customer while being extremely detailed internally. Designing for both audiences from the same underlying event is the real skill here.

### Card Decline: A Worked Example

When a card payment is declined, there are dozens of possible internal reasons: insufficient funds, suspected fraud, card reported lost, merchant category blocked, velocity limit hit, expired card. If my API returns the *real* reason to the calling merchant or app, I hand a fraudster a diagnostic tool for probing stolen cards ("insufficient funds" vs. "card blocked" tells them very different things about whether to keep trying).

```json
// Leaks fraud-detection internals to whoever holds the card/token
{
  "code": "DECLINE_SUSPECTED_FRAUD",
  "message": "Transaction blocked: velocity rule VR-014 triggered, risk score 87/100",
  "riskScore": 87,
  "triggeredRule": "VR-014"
}
```

```json
// Generic external response...
{
  "code": "PAYMENT_DECLINED",
  "message": "This payment could not be completed. Please contact your bank.",
  "requestId": "9f3a7c21"
}
```

```javascript
// ...paired with a rich, internal-only audit record keyed by the same requestId
{
  requestId: "9f3a7c21",
  cardId: "card_331",
  declineReasonInternal: "SUSPECTED_FRAUD",
  riskScore: 87,
  triggeredRule: "VR-014",
  timestamp: 1734691200
}
```

I tested this split by simulating three different real decline reasons (insufficient funds, expired card, suspected fraud) through the same endpoint: externally, all three came back with the identical generic `PAYMENT_DECLINED` shape and only a different `requestId`; internally, the fraud team's log view showed the full differentiated reason for each. A merchant or fraudster probing the card gets no differentiating signal at all — every decline looks identical from the outside — while my own fraud analysts, searching by `requestId`, get everything they need.

### Account Lockouts and Enumeration

The account-enumeration principle from general API design applies with extra force here, because in banking the accounts being enumerated are tied directly to real money:

```
Avoid:  "No account found with that number"           → confirms account doesn't exist
Avoid:  "Incorrect PIN, 2 attempts remaining"          → confirms account exists AND reveals attempt budget
Prefer: "We couldn't verify those details. If you continue to have trouble, contact us."
```

I still track attempts internally and still lock the account after a threshold — I just don't narrate the countdown to whoever's trying. And when I do lock an account, the response is intentionally identical to a "just a temporary system issue" message from the outside, while triggering an internal alert and a customer notification through a separate, verified channel (SMS/email to the address on file — never through the same channel the failed attempts came through).

| Error scenario | External message | Internal signal |
|---|---|---|
| Wrong password/PIN | Generic "verification failed" | Failed-attempt counter increments; audit log entry |
| Account locked | Same generic "verification failed" (no distinction!) | Alert to fraud team; out-of-band customer notification |
| Suspected fraud on transaction | Generic "payment declined" | Full risk score, rule ID, case opened |
| Rate limit hit | `429` with `Retry-After`, no detail on threshold | Velocity metrics logged per account/IP |

---

## Tying It Together in the Contract

Just as before, none of this is useful if it only lives in my head — it has to be enforceable in the OpenAPI document that implementers, gateways, and contract tests all read from:

```yaml
paths:
  /transfers/domestic:
    post:
      summary: Initiate a domestic transfer (idempotent, audited, rate-limited)
      security:
        - OAuth2: [payments:initiate]
      parameters:
        - name: Idempotency-Key
          in: header
          required: true
          schema: { type: string, format: uuid }
      requestBody:
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/DomesticTransferRequest'
      responses:
        '201':
          description: Transfer accepted for processing
          headers:
            Cache-Control:
              schema: { type: string, example: "no-store" }
        '409':
          description: Idempotency key reused with a different payload
        '429':
          description: Velocity limit exceeded
          headers:
            Retry-After:
              schema: { type: integer }
        '422':
          description: Generic validation/business-rule failure
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
```

Every field of this operation definition maps back to one of the six pillars: the scope enforces least privilege, the required idempotency header prevents double-spends, the explicit `no-store` header closes an infrastructure leak, the `429` with `Retry-After` handles velocity abuse without revealing thresholds, and the generic `422` error shape refuses to leak the business-logic reason behind a decline.

## Closing Thoughts

Designing APIs for a bank taught me that the general principles of secure API design don't change — minimize exposure, enforce context, guarantee integrity, close infrastructure leaks, partition with scopes, fail safely — but the *cost of getting any one of them wrong* changes by orders of magnitude, and a few of them (idempotency on money movement, append-only ledgers, mTLS for third-party access, dual control on high-value operations) go from "nice to have" to "the entire reason the design exists" the moment real money is on the other side of the wire.

The habit I've taken away from this is a simple discipline: for every endpoint I design in a financial system, I ask not just "who can call this and what do they get back," but "what happens if this exact request arrives twice, what happens if it arrives from someone who shouldn't have gotten this far, and what does the answer we give away — even in a decline message — teach an attacker about how close they got." Answering those three questions honestly, for every operation, is most of what it takes to design a banking API that's boring in exactly the way a bank should be.
