# Designing User-Friendly, Interoperable Operation Flows

## What Is an Operation Flow?

An **operation flow** is the **sequence of API calls** a consumer has to make, in order, to actually accomplish a use case. It's the "story" formed by chaining individual operations together — not any single operation in isolation.

```mermaid
flowchart LR
    Op1["Operation 1"] --> Op2["Operation 2"] --> Op3["Operation 3"] --> Goal["Use case achieved"]
```

**Example flow — "Buy a product":**

```mermaid
sequenceDiagram
    participant Consumer
    participant API

    Consumer->>API: GET /products?category=electronics
    API-->>Consumer: 200 OK — list of matching products
    Consumer->>API: GET /products/123
    API-->>Consumer: 200 OK — product details
    Consumer->>API: POST /cart/items { productId: 123, quantity: 1 }
    API-->>Consumer: 201 Created — cart updated
    Consumer->>API: POST /orders { cartId: ... }
    API-->>Consumer: 201 Created — order placed
```

---

## Why Flows Need Their Own Design Attention

Even if every individual operation in a flow is well-designed on its own — user-friendly data, guessable paths, appropriate methods — the **flow as a whole** can still be clunky, rigid, or inefficient. Flow design is a distinct concern layered on top of everything covered so far.

```mermaid
flowchart TD
    Ingredients["Individually good operations<br/>(user-friendly data + operations)"] --> Question{"Is the FLOW,<br/>as a whole,<br/>also good?"}
    Question -->|Not automatic| NeedsWork["Must be evaluated<br/>and designed SEPARATELY"]
```

A flow can be bad even when every step in it is individually well-designed — for example, requiring five sequential calls where two would do, or forcing a strict linear order when consumers legitimately need to jump in partway through.

---

## Principle 1: Design Concise Flows

The fewer steps required to accomplish a use case, the better. Every additional required step is additional friction, additional network round-trips, and additional chances for something to fail partway through.

```mermaid
flowchart LR
    subgraph Verbose["Verbose flow (avoid)"]
        V1["List addresses"] --> V2["Deactivate old address"] --> V3["Create new address"]
    end
    subgraph Concise["Concise flow (prefer)"]
        C1["Update address<br/>(single call)"]
    end
```

This directly echoes the earlier address-update example: three separate calls collapsed into one atomic operation is exactly this "conciseness" principle in action.

```
Verbose (3 calls):
GET    /customers/456/addresses
PATCH  /customers/456/addresses/1  { "status": "inactive" }
POST   /customers/456/addresses    { "street": "...", "status": "active" }

Concise (1 call):
PUT    /customers/456/address      { "street": "..." }
```

---

## Principle 2: Design Flexible Flows

### Support various contexts, not just one UI

```mermaid
flowchart TD
    Flow2["Operation flow"] --> Constraint{"Is it tightly coupled<br/>to ONE specific UI's<br/>screen-by-screen flow?"}
    Constraint -->|Yes — bad| Rigid["Rigid: only works for<br/>that one consumer/UI"]
    Constraint -->|No — good| Flexible2["Flexible: usable by<br/>many different consumers<br/>and contexts"]
```

This connects to the earlier caution against mapping API design to UI flows. If a flow is built assuming one specific screen sequence (step 1 must be screen A, step 2 must be screen B...), any other consumer — a different app, a batch import tool, a partner integration — is forced to awkwardly conform to that same rigid sequence even when it doesn't fit their actual needs.

### Allow entry at later steps, not just the beginning

```mermaid
flowchart LR
    Step1b["Step 1"] --> Step2b["Step 2"] --> Step3b["Step 3"] --> Step4b["Step 4"]
    Step2b -.->|"Ideally: consumer CAN start here directly"| Step2b
    Step3b -.->|"or here"| Step3b
```

Ideally, a consumer who already has the data or context that would normally come from earlier steps should be able to **skip straight to a later step**, rather than being forced to march through the entire sequence from the beginning every time — even when some of it is redundant for their situation.

**Example:** an order-checkout flow shouldn't force every consumer through "search products → view product → add to cart" if a partner integration already knows exactly which product and quantity it wants — that partner should be able to call the "add to cart" or "create order" step directly.

---

## Principle 3: Operations Must Serve the Flow's Context

Every operation used within a flow should genuinely meet the needs of that specific point in the flow — and should help the consumer move on to whatever the **next logical operation** in the flow is. This often means an operation's output should include exactly what's needed as input for the *next* step.

```mermaid
flowchart LR
    OpA["Operation A output"] -->|"contains what's needed as input for..."| OpB["Operation B input"]
```

```json
// Step 1 output: creating a cart
// POST /carts → 201 Created
{
  "cartId": "c789",
  "items": []
}
```
```
// Step 2 input directly uses cartId from step 1's output — no extra lookup needed
POST /carts/c789/items
{ "productId": 123, "quantity": 1 }
```

---

## Principle 4: Reuse Similar Patterns Across Similar Flows

If several flows in the API share a similar underlying shape (e.g., multiple "create X, then confirm X" flows across different resource types), they should follow the **same structural pattern** consistently, rather than each one inventing its own slightly different sequence.

```mermaid
flowchart TD
    subgraph FlowA["Flow A: Create Order"]
        A1["POST /orders (draft)"] --> A2["POST /orders/{id}/confirm"]
    end
    subgraph FlowB["Flow B: Create Subscription"]
        B1["POST /subscriptions (draft)"] --> B2["POST /subscriptions/{id}/confirm"]
    end
```

A consistent "create as draft, then confirm" pattern used identically across every resource type that needs it means a consumer who's learned the pattern once from any single flow can correctly guess how *every other* similar flow in the API works — making the whole API more **guessable and easy to use** as a system, not just operation by operation.

---

## When Flow Optimization Happens

Flow optimization is deliberately done **after** individual data and operation user-friendliness/interoperability work is already finished — same staged-refinement philosophy seen throughout this whole design process.

```mermaid
flowchart LR
    A3["Enhance DATA<br/>user-friendliness"] --> B3["Enhance OPERATION<br/>user-friendliness"] --> C3["THEN optimize<br/>the FLOW as a whole"]
```

---

## Finding Optimization Opportunities

Three questions guide the search for flow improvements:

```mermaid
flowchart TD
    Optimize["Finding Flow Optimizations"]
    Optimize --> Q1["Question the need<br/>for certain STEPS"]
    Optimize --> Q2["Check whether ERRORS and<br/>DATA PROCESSING can be<br/>prevented earlier"]
    Optimize --> Q3["Reconsider HOW DATA<br/>is SAVED during the flow"]
```

### 1. Question the need for certain steps

For every step, ask: is this step *actually necessary*, or is it just historically how it's been done? A step that only exists to fetch data the server could derive or already has access to is a candidate for removal.

### 2. Check whether errors/processing can be prevented

If a later step commonly fails because of something that could have been checked or handled earlier, moving that validation earlier (or building it into an earlier step) prevents wasted round-trips and frustrating late-stage failures.

### 3. Reconsider how data is saved

Sometimes the *sequencing* of when data gets persisted during a flow is itself the source of friction — this is expanded on below in the partial-saving section.

---

## Techniques for Optimizing a Flow

```mermaid
flowchart TD
    Techniques["Flow Optimization Techniques"]
    Techniques --> Tech1["Add use-case-specific<br/>features or data"]
    Techniques --> Tech2["Create SPECIFIC<br/>operations"]
    Techniques --> Tech3["AGGREGATE<br/>operations"]
```

### Add use-case-specific features or data

Sometimes a flow can be shortened by simply enriching an existing operation with something extra tailored to a common use case, rather than requiring a separate call.

```json
// Before: separate call needed to check stock before adding to cart
GET /products/123/stock  → { "available": 5 }
POST /cart/items { "productId": 123, "quantity": 1 }

// After: stock info added directly to the product read response
GET /products/123 → { "productReference": 123, "name": "...", "stockAvailable": 5 }
POST /cart/items { "productId": 123, "quantity": 1 }
```

### Create specific operations

Instead of forcing a consumer through several generic operations to achieve one common, specific goal, a dedicated operation can be created that directly represents that goal.

```
Generic (multi-step):
GET  /products/123
PATCH /products/123 { "status": "discontinued" }
POST /notifications { "type": "product-discontinued", "productId": 123 }

Specific (one step):
POST /products/123/discontinue
```

### Aggregate operations

Multiple related operations can be **combined into one** that performs several actions together as a single atomic call — similar in spirit to the earlier address-update collapsing.

```mermaid
flowchart LR
    subgraph BeforeAgg["Before: separate calls"]
        BA1["POST /orders"] --> BA2["POST /payments"] --> BA3["POST /shipments"]
    end
    subgraph AfterAgg["After: aggregated"]
        AA1["POST /checkout<br/>(creates order + payment + shipment together)"]
    end
```

---

## The Caution: Don't Sacrifice Clarity for Optimization

```mermaid
flowchart TD
    Aggregation2["Aggregating operations"] --> Tradeoff2{"Does it blur the CLARITY<br/>or SCOPE of the underlying<br/>data/operations?"}
    Tradeoff2 -->|Yes| Danger["DON'T do it —<br/>optimization isn't worth<br/>losing clear, well-scoped design"]
    Tradeoff2 -->|No| OK2["Safe to aggregate"]
```

This is an explicit guardrail: reducing the number of calls is valuable, but **not at the cost of clarity**. An aggregated "do everything" operation that becomes vague about what it actually does, or that mixes unrelated concerns together just to save a round-trip, has traded a real problem (verbosity) for a worse one (an unclear, poorly-scoped operation). Every optimization should be checked against this tradeoff before being adopted.

---

## Partial Data-Saving in Multi-Step Flows

For flows where a consumer builds up data over multiple steps (e.g., a multi-page form, a draft order, a long registration process), the API should support **saving progress incrementally**, not force an all-or-nothing single submission.

```mermaid
flowchart LR
    Start2["Start flow —<br/>provide only the MINIMAL vital data"] --> Draft["Draft resource created"]
    Draft --> Step2c["Add more data<br/>(step 2)"]
    Step2c --> Step3c["Add more data<br/>(step 3)"]
    Step3c --> Finalize["Finalize / confirm"]
```

```
POST   /applications              { "applicantName": "Ana Diaz" }   → creates a draft, minimal data only
PATCH  /applications/a1           { "email": "ana@example.com" }    → adds more data later
PATCH  /applications/a1           { "documents": [...] }            → adds more data later still
POST   /applications/a1/submit                                       → finalizes
```

The key requirement: **initiating the flow should only require the minimal vital data**, so consumers aren't blocked from starting just because they don't yet have every piece of information — they can collect the rest incrementally as it becomes available.

### Split validation from completion

```mermaid
flowchart TD
    DataSaveFlow["Data-saving flow"] --> Validate["VALIDATE step<br/>(check what's missing/invalid,<br/>WITHOUT finalizing)"]
    DataSaveFlow --> Complete2["COMPLETE step<br/>(finalize, only once<br/>everything checks out)"]
```

```
GET  /applications/a1/validation
→ 200 OK
{
  "isComplete": false,
  "missingFields": ["documents"],
  "issues": []
}

POST /applications/a1/submit
→ 400 Bad Request (if still incomplete)
{
  "title": "Application incomplete",
  "detail": "Missing required field: documents"
}
```

Separating a **validation check** from the actual **completion/submission** action lets consumers (and end users, through their UI) verify what's missing or wrong *before* attempting to finalize — rather than only discovering problems as a rejected final submission, which is a much worse experience, especially in a long multi-step flow.

### Support both full (single-step) and partial (multi-step) saving

```mermaid
flowchart TD
    SaveChoice["How should data be saved?"]
    SaveChoice --> Full["FULL: single-step —<br/>submit everything at once<br/>(good for consumers who<br/>already have all the data)"]
    SaveChoice --> Partial2["PARTIAL: multi-step —<br/>save incrementally over time<br/>(good for consumers who<br/>gather data progressively)"]
```

```
Full (single call — e.g. a partner system with all data ready):
POST /applications
{
  "applicantName": "Ana Diaz",
  "email": "ana@example.com",
  "documents": [...]
}
→ 201 Created (already complete)

Partial (multiple calls — e.g. a human filling out a long form):
POST  /applications          { "applicantName": "Ana Diaz" }
PATCH /applications/a1       { "email": "ana@example.com" }
PATCH /applications/a1       { "documents": [...] }
POST  /applications/a1/submit
```

Offering both options lets each consumer choose whichever approach genuinely fits their situation — a fully-informed backend integration shouldn't be forced through an artificial multi-step dance, and a human filling out a form over several sessions shouldn't be forced to provide everything in one shot.

---

## Consolidated Checklist

| Concern | Guideline |
|---|---|
| Step count | Keep flows as concise as possible; collapse unnecessary multi-step sequences |
| Flexibility | Don't couple flows to one UI's screen order; allow entry at later steps |
| Operation fit | Each operation should serve its flow context and feed naturally into the next step |
| Consistency | Reuse the same flow patterns across similar use cases |
| Timing | Optimize flows only after individual data/operation user-friendliness is done |
| Finding optimizations | Question unnecessary steps; prevent errors/processing earlier; reconsider data-saving timing |
| Optimization techniques | Use-case-specific enrichment, dedicated operations, aggregation |
| Aggregation guardrail | Never sacrifice clarity or scope for fewer calls |
| Partial saving | Require only minimal vital data to start; allow incremental additions |
| Validation vs. completion | Separate "check what's missing" from "finalize" |
| Save modes | Support both full single-step and partial multi-step saving |
