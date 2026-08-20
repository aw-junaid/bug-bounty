# Describing Data with JSON Schema in OpenAPI

## JSON Schema: An Independent Format Used By OpenAPI

**JSON Schema** is its own, separate specification — it exists independently of OpenAPI and is used broadly anywhere JSON data needs to be described and validated, not just in APIs. OpenAPI doesn't reinvent a way to describe data; it **adopts JSON Schema** as the format for its `schema` properties, wherever fine-grained data needs to be defined.

```mermaid
flowchart LR
    JSONSchema["JSON Schema<br/>(independent, general-purpose<br/>data description format)"] -->|adopted by| OpenAPI2["OpenAPI<br/>(uses JSON Schema inside<br/>its 'schema' properties)"]
```

This matters practically: knowledge of JSON Schema is portable — the same syntax and rules learned here apply outside of OpenAPI too, in any context that validates or describes JSON data.

---

## When to Add Data Descriptions: After HTTP Operations

Following the staged design flow already established, JSON Schemas are added to the OpenAPI document **after** the HTTP operations (paths, methods, parameters, responses) have been sketched out — not before.

```mermaid
flowchart LR
    A["1. Design resource paths"] --> B["2. Add HTTP operations<br/>(methods, parameters, responses)"] --> C["3. Describe data<br/>with JSON Schema"]
    style C fill:#4a90d9,color:#fff
```

This ordering keeps the same discipline seen throughout the whole design process: settle the *shape* of the interaction first (what operation, what method, what status codes), then fill in the *detailed data* afterward. Trying to write precise schemas before the surrounding HTTP structure is stable invites rework.

---

## Reusable Resource Model Schemas: `components.schemas`

Rather than writing out a resource's data model inline, repeatedly, everywhere it's used, resource models are defined **once** as **reusable schemas** under a dedicated top-level section: `components.schemas`.

```mermaid
flowchart TD
    Root2["OpenAPI document"] --> Components["components:"]
    Components --> Schemas["schemas:"]
    Schemas --> ProductComplete["Product"]
    Schemas --> ProductSummary["ProductSummary"]
    Schemas --> ProductCreation["ProductCreation"]
```

```yaml
components:
  schemas:
    Product:
      type: object
      properties:
        productReference:
          type: number
        name:
          type: string
        price:
          type: number
    ProductSummary:
      type: object
      properties:
        productReference:
          type: number
        name:
          type: string
```

Two concrete benefits of doing this:

```mermaid
flowchart LR
    Reuse["Reusable schemas in<br/>components.schemas"] --> B1["Better view of the<br/>API's subject matter<br/>(all business concepts<br/>visible in one place)"]
    Reuse --> B2["Reusability across<br/>operations<br/>(no duplicated definitions)"]
```

- **Better subject-matter view** — having every resource model collected in one section gives anyone reading the document a clear picture of the API's underlying business concepts, independent of which specific operations happen to use them.
- **Reusability** — the same schema definition can be referenced from many different operations (create, read, update, search) instead of being copy-pasted and redefined each time, which is both less work and less error-prone.

This directly echoes the earlier **data modeling** stage, where a single **complete model** was designed first and then several other models (summarized, minimal, creation, etc.) were **derived** from it — `components.schemas` is exactly where each of those derived models gets its own reusable definition.

---

## The Building Blocks of a JSON Schema

### Every schema starts with a `type`

```mermaid
flowchart TD
    Types["JSON Schema type"]
    Types --> Object2["object"]
    Types --> Array2["array"]
    Types --> String2["string"]
    Types --> Number2["number"]
    Types --> Integer["integer"]
    Types --> Boolean2["boolean"]
```

These map directly back to the **portable JSON data types** established during data modeling — this is where those earlier type decisions actually get written down formally.

### Objects: `properties` and `required`

An `object` schema describes something with named fields. Its structure has two key parts:

```mermaid
flowchart TD
    ObjSchema["Object Schema"] --> Props["properties:<br/>a MAP —<br/>key = property name,<br/>value = that property's own schema"]
    ObjSchema --> Req["required:<br/>a LIST of property names<br/>that must be present"]
```

```yaml
Product:
  type: object
  properties:
    productReference:
      type: number
    name:
      type: string
    price:
      type: number
  required:
    - productReference
    - name
```

Notice that **each individual property's value is itself a schema** — this is what makes JSON Schema composable: a property can be a simple type (`string`, `number`) or something more complex, like a nested `object` or an `array`, following the exact same rules recursively.

```mermaid
flowchart LR
    Product2["Product (object)"] --> PR["productReference: {type: number}"]
    Product2 --> Name3["name: {type: string}"]
    Product2 --> Nested["category: {type: object, properties: {...}}<br/>(a property can itself be a full nested schema)"]
```

The `required` list only names properties that are **mandatory** — any property not listed there is implicitly optional, mirroring the same optional-by-default logic used for parameters described earlier.

### Arrays: the `items` property

An `array` schema describes a list, and its `items` property holds the schema that describes **each element** of that list.

```mermaid
flowchart LR
    ArraySchema["Array Schema<br/>type: array"] --> Items["items:<br/>the schema describing<br/>EACH element in the array"]
```

```yaml
type: array
items:
  type: object
  properties:
    productReference:
      type: number
    name:
      type: string
```

This is an **inline array schema** — the element type is defined directly, right there, rather than pointing to something reusable elsewhere.

---

## Referencing Reusable Schemas with `$ref`

Instead of repeating a schema inline every time it's needed, a schema defined once under `components.schemas.SomeName` can be **referenced** from anywhere else in the document using the `$ref` property.

```mermaid
flowchart LR
    Definition["components:<br/>schemas:<br/>ProductSummary:<br/>type: object<br/>properties: {...}"] -->|referenced via| Ref["$ref:<br/>'#/components/schemas/ProductSummary'"]
```

The value of `$ref` is a **JSON Pointer** — a path-like string that navigates to the exact location of the reusable definition inside the document:

```
#/components/schemas/ProductSummary
 │           │        │
 │           │        └── the schema's name (the key under schemas)
 │           └── the schemas map
 └── starts from the root of the current document
```

### Example: an array of a referenced schema

Putting arrays and references together — this is a very common real-world pattern, where a search operation returns an array, and each element of that array is a reusable schema rather than an inline one:

```mermaid
flowchart TD
    ArrayInline["Array schema<br/>(defined inline, right here)"] --> ItemsRef["items:<br/>$ref: '#/components/schemas/ProductSummary'"]
    ItemsRef -.->|points to| TargetSchema["components.schemas.ProductSummary<br/>(the actual, reusable definition)"]
```

```yaml
responses:
  "200":
    description: Products matching filters
    content:
      application/json:
        schema:
          type: array
          items:
            $ref: '#/components/schemas/ProductSummary'
```

Here, the **array itself is inline** (defined directly in the response), but its **elements' schema is a reference** — pointing to the `ProductSummary` reusable schema defined once under `components.schemas`. This is exactly the kind of composition that keeps the document both concise and consistent: the array shape is specific to this one response, but the shape of *what's inside it* is shared and reused everywhere `ProductSummary` applies.

---

## Where Schemas Get Used: Operation Inputs and Outputs

Recall from the HTTP-operations stage that every `schema` property — inside `parameters`, inside `requestBody.content`, inside `responses...content` — was left as a placeholder for detailed data. This is where those placeholders actually get filled in, either with inline definitions or with `$ref` pointers to `components.schemas`.

```mermaid
flowchart TD
    HTTPStage["HTTP operation design stage<br/>(schema properties added as placeholders)"] --> DataStage["Data description stage<br/>(schema properties filled in)"]
    DataStage --> Inline2["Option A: inline schema<br/>(defined right there)"]
    DataStage --> RefOption["Option B: $ref to<br/>components.schemas"]
    DataStage --> Mixed["Option C: a MIX of both<br/>(e.g. inline array of referenced items)"]
```

---

## Why Prefer References Over Duplication

The strong recommendation is to **use `$ref` to reusable schemas** rather than writing the same data shape inline, repeatedly, across multiple operations.

```mermaid
flowchart TD
    Problem["Without reuse:<br/>same 'Product' shape<br/>written inline in 5 different places"] --> Risk1["Duplication —<br/>more to maintain"]
    Problem --> Risk2["Unwanted variations —<br/>copies drift apart over time<br/>(one gets updated, others don't)"]

    Solution["With reuse:<br/>ONE 'Product' schema<br/>under components.schemas,<br/>referenced everywhere"] --> Benefit1["Single source of truth"]
    Solution --> Benefit2["Consistent request/response<br/>bodies across the whole API"]
```

The risk being avoided here isn't just extra typing — it's **drift**. If the same conceptual data shape is defined inline in multiple places, there's nothing stopping those copies from silently diverging over time as the API evolves (someone updates one operation's inline schema but forgets the other four). Referencing a single shared definition eliminates that risk structurally, rather than relying on discipline to keep copies in sync.

---

## Describing Resource Model Derivations as Reusable Schemas

This ties directly back to the **seven typical data models** (complete, summarized, minimal, identifier, creation, replacement, modification) from the data-modeling stage — each derived model gets its **own reusable schema** entry under `components.schemas`, not just the base "complete" model.

```mermaid
flowchart TD
    Complete7["components.schemas.Product<br/>(complete model)"] --> Summary4["components.schemas.ProductSummary<br/>(summarized model)"]
    Complete7 --> Minimal3["components.schemas.ProductMinimal<br/>(minimal model)"]
    Complete7 --> Creation5["components.schemas.ProductCreation<br/>(creation model)"]
    Creation5 --> Replacement3["components.schemas.ProductReplacement<br/>(replacement model)"]
    Creation5 --> Modification3["components.schemas.ProductModification<br/>(modification model)"]
```

```yaml
components:
  schemas:
    Product:
      type: object
      properties:
        productReference: { type: number }
        name: { type: string }
        price: { type: number }
        category: { type: string }
      required: [productReference, name, price]

    ProductSummary:
      type: object
      properties:
        productReference: { type: number }
        name: { type: string }
      required: [productReference, name]

    ProductCreation:
      type: object
      properties:
        name: { type: string }
        price: { type: number }
        category: { type: string }
      required: [name, price]
```

Each operation then simply **references** the specific derived schema that fits its role — search responses reference `ProductSummary`, create request bodies reference `ProductCreation`, and so on — rather than each operation inventing its own version of "what a product looks like."

---

## Summary: The Three Ways a `schema` Property Can Be Filled

```mermaid
flowchart TD
    SchemaProp["A schema property<br/>(in parameters, requestBody, or responses)"]
    SchemaProp --> OptA["Inline schema<br/>type/properties written<br/>directly at this location"]
    SchemaProp --> OptB["Reference ($ref)<br/>pointing to<br/>components.schemas.SomeName"]
    SchemaProp --> OptC["A mix of both —<br/>e.g. an inline array<br/>whose items are a $ref"]
```

The general guidance running through all of this: use **inline schemas** for shapes that are genuinely one-off and specific to a single location, and use **references to `components.schemas`** for anything representing a reusable business concept or a derived resource model — which, in practice, covers most of the meaningful data in a well-designed API.
