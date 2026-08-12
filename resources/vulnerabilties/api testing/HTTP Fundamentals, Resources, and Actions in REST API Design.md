# HTTP Fundamentals, Resources, and Actions in REST API Design

## HTTP: The Protocol REST Is Built On

HTTP is a **synchronous, request–response protocol**. A client sends a request; the server processes it and sends back a response before anything else happens — there's no "fire and forget," the client waits for an answer.

```mermaid
sequenceDiagram
    participant Client
    participant Server
    Client->>Server: Request (method + target)
    activate Server
    Server-->>Client: Response (status + content)
    deactivate Server
```

Interaction happens through **standardized methods** (`GET`, `POST`, `PUT`, `DELETE`, and others), each with a defined meaning. A **resource** — the thing being acted on — can be virtually anything: an HTML page, an image, a video, or structured data like JSON. HTTP doesn't care what the resource actually represents; it just needs a path to identify it and a method to describe the action being taken.

A **REST API** is a specific kind of web API: one that uses HTTP extensively and **respects its semantics** — meaning `GET` really only reads, `DELETE` really only deletes, status codes really reflect what happened, and so on, rather than using HTTP as a generic pipe that ignores its own conventions.

---

## The Three-Step Design Flow

Designing a REST interface from already-identified capabilities happens in three ordered steps:

```mermaid
flowchart LR
    A["1. Observe operations<br/>from the REST angle"] --> B["2. Represent elements<br/>with HTTP"]
    B --> C["3. Model the data"]

    subgraph Step1detail["Step 1 output"]
        A1["Resources"]
        A2["Actions"]
        A3["Inputs"]
        A4["Outputs"]
    end
    A -.-> Step1detail
```

**Step 1** stays entirely in plain language (English or whatever language the team uses) — no HTTP methods, no paths, no status codes yet. The goal is purely to identify:
- what **resources** (business concepts) are involved,
- what **action** is being performed,
- what **inputs** the action needs,
- what **outputs** it can produce (success or failure).

Only in step 2 do these plain-language elements get mapped onto actual HTTP methods and paths, and only in step 3 does the detailed data (field names, types, structure) get designed.

---

## The Five CRUD Operations

Most API operations fall into one of five classic categories — **CRUD**: **C**reate, **R**ead (including search/list), **U**pdate, **D**elete.

```mermaid
flowchart TD
    CRUD["CRUD Operations"]
    CRUD --> Create["Create<br/>e.g. Add a product"]
    CRUD --> Read["Read<br/>e.g. Get product details<br/>Search for products"]
    CRUD --> Update["Update<br/>e.g. Modify a product"]
    CRUD --> Delete["Delete<br/>e.g. Remove a product"]
```

---

## What Is a Resource?

A resource is a **standalone business concept** — something that exists and can be manipulated on its own, as distinct from a **property**, which is just a small piece of data that only makes sense attached to something else.

```mermaid
flowchart LR
    Product["Product<br/>(resource — stands alone)"]
    Name["name<br/>(property — belongs to Product)"]
    Category["category<br/>(property — belongs to Product)"]
    Product --> Name
    Product --> Category
```

**Key rule:** one operation manipulates exactly **one** resource, but a single resource can be the target of **many different** operations.

```mermaid
flowchart LR
    Search["Search"] --> Catalog
    GetDetails["Get details"] --> Product
    Modify["Modify"] --> Product
    Remove["Remove"] --> Product
    Add["Add"] --> Catalog
```

### How to find the resource: follow the main verb

The resource is whatever the **main verb** in the operation's description is acting on.

```mermaid
flowchart LR
    Desc["'Modify a product'"] --> Verb["Main verb: Modify"]
    Verb --> Res["Target of the verb → Resource = Product"]
```

### Container vs. individual element

Whether the resource is the **element itself** or its **container** depends on the type of operation:

```mermaid
flowchart TD
    subgraph Individual["Resource = the individual element"]
        Read2["Read one item"]
        Update2["Update one item"]
        Delete2["Delete one item"]
    end
    subgraph Container["Resource = the container"]
        Create2["Create / add an item"]
        List2["List / search items"]
    end
```

This makes intuitive sense: you can't "add a product" without a catalog to add it *to* — the catalog is what's really being modified (its contents change). But you *can* modify or delete a single, already-identified product directly — no container needed to describe that action.

---

## What Is an Action?

An action is simply the **main verb** applied to the resource identified above. Since the same verb is used to find both the resource and the action, these two identifications effectively happen together, from the same phrase.

```mermaid
flowchart LR
    Desc["'Search for products'"] --> V["Verb → Action = Search"]
    Desc --> R["Container → Resource = Catalog"]
```

---

## Consolidating an Action's Inputs

A single action (say, "Modify") might be triggered from several different use-case steps throughout your capability analysis — each with its own listed inputs. Rather than keeping separate, possibly redundant input lists, you **merge** them into one consolidated list for the action.

```mermaid
flowchart TD
    Step1["Use case A, step 3:<br/>needs 'product ID', 'new name'"]
    Step2["Use case B, step 5:<br/>needs 'selected product', 'updated info'"]
    Step1 --> Merge["Merge into one input list<br/>for the 'Modify' action"]
    Step2 --> Merge
    Merge --> Final["Final inputs:<br/>Product, Modified Info"]
```

Two rules guide this merge:

1. **Use context-agnostic descriptions.** "Selected product" and "product ID" from two different use cases might really be the same input described differently — rewording them into one neutral, reusable description avoids ending up with duplicate entries that mean the same thing.

2. **Remove the operation's own resource from its input list** — *unless* the action genuinely needs more than one instance of that resource. For example, "Modify a product" doesn't need to list "Product" as an input separately, because the resource being modified *is* the product itself (it'll be identified by its path/location, not passed as a separate input). But an action like "Compare two products" would legitimately need multiple product instances listed as input, since more than one distinct instance of the same resource type is genuinely required.

```mermaid
flowchart LR
    A["Modify a product"] --> B["Inputs: Modified Info<br/>(NOT 'Product' — that's the resource itself)"]
    C["Compare two products"] --> D["Inputs: Product A, Product B<br/>(kept — multiple distinct instances needed)"]
```

---

## Consolidating an Action's Outputs

The same merging logic applies to outputs. An action's **success** and **failure** outcomes, gathered from every use-case step that uses it, get combined into one output list.

```mermaid
flowchart TD
    S1["Use case A: success = 'product updated'"]
    S2["Use case B: success = 'changes saved'"]
    F1["Use case A: failure = 'product not found'"]
    F2["Use case B: failure = 'invalid data'"]
    S1 --> Merge2["Merge into one output list"]
    S2 --> Merge2
    F1 --> Merge2
    F2 --> Merge2
    Merge2 --> Final2["Final outputs:<br/>1. Product updated (success)<br/>2. Product not found (error)<br/>3. Invalid data (error)"]
```

Each entry in the merged output list has three parts:

| Field | Meaning |
|---|---|
| **Description** | Plain-language explanation of the outcome |
| **Type** | `success` or `error` |
| **Data** *(optional)* | Any data returned alongside this outcome |

### Judge success/error from a neutral, context-agnostic perspective

This is a subtle but important point: what counts as "success" or "error" should be decided from the **action's own neutral perspective** — not from how any one particular consumer happens to interpret it.

```mermaid
flowchart LR
    Action["Action: Search for products"]
    Action --> O1["Outcome: Products found → success"]
    Action --> O2["Outcome: No products found → still success<br/>(the search itself worked correctly —<br/>an empty result is a valid, successful outcome)"]
```

Different consumers might *feel* differently about the same outcome — one application might treat "no results found" as basically an error state to show the user, while another might treat it as a perfectly normal, expected outcome. But the action's own output list should reflect what **actually happened from the API's point of view**, not any single consumer's downstream interpretation. This keeps the design consistent and reusable across every consumer, instead of being biased toward how the first consumer you thought about happens to use it.
