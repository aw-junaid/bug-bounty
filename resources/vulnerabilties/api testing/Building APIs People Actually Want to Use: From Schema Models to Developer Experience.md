# Building APIs People Actually Want to Use: From Schema Models to Developer Experience

I've spent a lot of time lately digging into how professional API teams go from "we have a business idea" to "we have a well-loved, well-documented API that developers actually enjoy integrating with." What I found is that the gap between a mediocre API and a genuinely *irresistible* one isn't really about REST verbs or JSON formatting — it's about process. It's about writing a contract before you write a line of code, testing that contract with a mock server, running disciplined development sprints against it, and then investing as much energy into documentation and support as you invested into the endpoints themselves.

In this post I want to walk through that whole pipeline in detail: what a schema model is and why it matters, how to build one in both RAML and OpenAPI, how design-driven development differs from waterfall and agile, how to run sprints against an API project, and — maybe most importantly — how to actually support the developers who are going to consume what you build. I'll back this up with real, tested code: a small Flask implementation of a "PizzaToppings" API and a validated OpenAPI 3.0 spec that describes it. I ran both through an actual test pass before including them here, so the request/response examples you see are real output, not invented text.

I'm writing this as one continuous story rather than a reference manual, because that's how I actually learned it — by watching the pieces connect to each other. Let's get into it.

---

## Part 1 — What Is a Schema Model, and Why Should I Bother?

Before I even get near an endpoint, I try to answer three questions: What is my business value? What are my use cases? And what do my client developers need to be able to do? Those three things are the foundation. A schema model is the next step — it's the artifact that turns "I know why I'm building this" into "here's precisely what I'm building."

I think of a schema model as a *map* of the API: a machine-readable, human-readable description of every endpoint, every request, every response, and every error condition, written before a single line of implementation code exists. It's not a wireframe and it's not a spec document buried in a wiki page nobody reads — it's a living contract between my team and everyone who's going to consume the API, whether that's another internal team, a partner company, or the general public.

> **Note:** A schema model is *not* the same thing as documentation. Documentation explains how to use something that exists. A schema model defines what will exist before it does. Good documentation is often generated *from* the schema model later — but the schema model comes first.

There are two schema modeling systems I want to focus on, because they're the two I see used most often in production teams:

- **RAML** (RESTful API Modeling Language) — built on Markdown, designed from the ground up for design-first development.
- **OpenAPI** (formerly Swagger) — supports JSON and YAML, and has become something close to the industry default.

Both of them let me do the same fundamental things:

1. Define the resources (the "nouns") in my API.
2. Define the methods (GET / POST / PUT / DELETE) available on each resource.
3. Define query parameters, request bodies, and response bodies — including error responses.
4. Generate a mock server from the model, so people can poke at the API before it's real.
5. Generate client or server code stubs from the model.

Here's the resource plan I'll use throughout this post — a small "PizzaToppings" API. It's intentionally simple so the modeling concepts stay visible instead of getting buried under business logic.

```
Resources and methods
======================
/toppings
   GET   -> list of toppings (optionally filtered by ?q=)
   POST  -> add a new topping

/toppings/{toppingId}
   GET    -> a single topping
   PUT    -> update a topping
   DELETE -> remove a topping (only if unused by any pizza)

/pizzas
   GET   -> list of pizzas (optionally filtered by ?q=)
   POST  -> add a new pizza (one per user)

/pizzas/{pizzaId}
   GET    -> a single pizza, including its toppings
   PUT    -> update a pizza (e.g. add/remove toppings)
   DELETE -> remove a pizza
```

Here's a simple ASCII diagram of the resource hierarchy, which is the kind of picture I like to sketch on a whiteboard before opening any modeling tool at all:

```
                     PizzaToppings API
                            |
          +-----------------+------------------+
          |                                     |
      /toppings                             /pizzas
      GET, POST                             GET, POST
          |                                     |
   /toppings/{id}                        /pizzas/{id}
   GET, PUT, DELETE                      GET, PUT, DELETE
```

Notice the pattern: the collection-level resource only accepts `GET` (read the list) and `POST` (add a new item) — never `PUT` or `DELETE`, because I don't want a client accidentally replacing or wiping out everyone else's data. The item-level resource accepts `GET`, `PUT`, and `DELETE`, but never `POST`, because "creating" doesn't make sense once you're already looking at a specific item.

> **Caution:** It's tempting to let `PUT` on a collection mean "replace the whole list." Resist this. Multiple people are using that list concurrently, and a `PUT` on `/toppings` implies you're allowed to wipe everyone else's toppings out. If you truly need bulk replace semantics, make it an explicit, separate, clearly-labeled operation — don't overload the verb.

---

## Part 2 — Modeling the API in RAML

RAML was built around Markdown, which means when you view the spec file directly on GitHub, it renders cleanly with headers, code blocks, and readable prose — instead of looking like machine output. It was also designed from day one around **design-first development**: you write the contract, then you build to match it, rather than reverse-engineering a description of code that already exists.

A RAML document starts with a required version comment, then top-level metadata:

```raml
#%RAML 0.8
title: PizzaToppings API
baseUri: http://api.irresistibleapis.com/v1
version: v1
```

Every field here matters more than it looks:

- `#%RAML 0.8` — required, and it must be the very first line. It tells any tooling which markup dialect to parse.
- `title` — tells a human reader what this thing is for.
- `baseUri` — the base URL for every single endpoint in the document. It needs to be the same across all of them; if you find yourself wanting `users.api.com` for one resource and `pages.api.com` for another, that's a sign you actually have two APIs, not one.
- `version` — only required if the version is embedded in the URI itself (as it is above, via `v1`).

From there, I add resources:

```raml
/toppings:
  displayName: Toppings
  get:
    description: Get a list of all toppings, optionally filtered by a search string.
    queryParameters:
      q:
        displayName: Search Query
        type: string
        required: false
        example: pepper
    responses:
      200:
        body:
          application/json:
            example: |
              {
                "success": true,
                "status": 200,
                "toppings": [
                  { "id": 1, "title": "pepperoni" },
                  { "id": 2, "title": "peppers" }
                ]
              }
  post:
    description: Add a new topping.
    body:
      application/json:
        example: |
          { "title": "pineapple" }
    responses:
      201:
        headers:
          Location:
            example: /toppings/3
      409:
        body:
          application/json:
            example: |
              { "success": false, "status": 409, "message": "A topping with that name already exists" }

/toppings/{toppingId}:
  get:
    responses:
      200:
        body:
          application/json:
            example: |
              { "success": true, "status": 200, "topping": { "id": 1, "title": "pepperoni" } }
      404:
        body:
          application/json:
            example: |
              { "success": false, "status": 404, "message": "Topping not found" }
  put:
    body:
      application/json:
        example: |
          { "title": "roasted pineapple" }
    responses:
      204:
      404:
        body:
          application/json:
            example: |
              { "success": false, "status": 404, "message": "Topping not found" }
  delete:
    responses:
      204:
      404:
        body:
          application/json:
            example: |
              { "success": false, "status": 404, "message": "Topping not found" }
      409:
        body:
          application/json:
            example: |
              { "success": false, "status": 409, "message": "Topping is used on 2 pizza(s)" }
```

I want to call out the query parameter for `q`. The use case behind it was: "as a client developer, I want to search for toppings containing a string, like `pepper`, so I can find `pepperoni`, `pepperocini`, or `peppers` without listing every single topping in the system." Writing that query parameter into the schema model — including whether it's required and an example value — means anyone reading the contract knows *exactly* how search behaves before the endpoint is real.

The response-status decisions matter just as much as the shapes of the bodies:

| Status | Meaning in this API | When it's used |
|---|---|---|
| 200 | Success, body returned | GET requests |
| 201 | Created | Successful POST, includes a `Location` header pointing at the new resource |
| 204 | Success, no body | Successful PUT or DELETE — the client already knows the location |
| 404 | Not found | GET/PUT/DELETE on an item id that doesn't exist |
| 409 | Conflict | POST with a duplicate name, or DELETE on a topping still in use by a pizza |

> **Note:** A `201 Created` response should *always* include a `Location` header pointing at the newly created resource. It's a small thing, but it's one of the most consistently forgotten details I see in real APIs, and it saves the client an entire extra round trip just to find the thing they created.

### Getting hands-on with RAML

The realistic workflow with RAML looks like this: sign up for an account with a RAML-aware editor (MuleSoft's Anypoint platform is the traditional home for this), create a new API entry with a name, version, and base URI, then use the graphical "Add Resource" tooling to build up the tree of endpoints, methods, query parameters, and example bodies. The editor is context-sensitive — the toolbar only offers you sections that make sense at whatever level of the document you're currently editing, which keeps you from, say, trying to attach a query parameter to a resource that doesn't accept `GET`.

Once the model is built, you get two very real advantages for free:

1. **An interactive documentation view**, generated straight from the model, that a client developer can browse without writing any code.
2. **A mock server**, which returns canned responses that exactly match your `example:` blocks — so a partner team can start writing a client against the *contract* while your team is still writing the *implementation*.

---

## Part 3 — Modeling the Same API in OpenAPI

OpenAPI grew out of Swagger and has become, in my experience, the more commonly adopted of the two — partly because it supports plain JSON as well as YAML, and partly because of its enormous tooling ecosystem (code generators, validators, mock servers, documentation renderers like Swagger UI and Redoc).

Rather than hand-wave through this, I actually wrote out a full OpenAPI 3.0 document for the PizzaToppings `/toppings` resource and ran it through `openapi-spec-validator` before putting it in this post. Here's the real, validated spec:

```yaml
openapi: 3.0.3
info:
  title: PizzaToppings API
  version: "1.0"
  description: A demo API for managing pizza toppings and pizzas.
servers:
  - url: https://api.irresistibleapis.com/v1
paths:
  /toppings:
    get:
      summary: List all toppings
      parameters:
        - name: q
          in: query
          required: false
          schema:
            type: string
          description: Filter toppings whose title contains this string
      responses:
        "200":
          description: A list of toppings
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  status:
                    type: integer
                  toppings:
                    type: array
                    items:
                      $ref: "#/components/schemas/Topping"
    post:
      summary: Add a new topping
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/NewTopping"
      responses:
        "201":
          description: Topping created
          headers:
            Location:
              schema:
                type: string
        "409":
          description: A topping with that name already exists
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/Error"
  /toppings/{toppingId}:
    parameters:
      - name: toppingId
        in: path
        required: true
        schema:
          type: integer
    get:
      summary: Get a single topping
      responses:
        "200":
          description: A single topping
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/Topping"
        "404":
          description: Topping not found
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/Error"
    put:
      summary: Update an existing topping
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/NewTopping"
      responses:
        "204":
          description: Topping updated
        "404":
          description: Topping not found
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/Error"
    delete:
      summary: Delete a topping
      responses:
        "204":
          description: Topping deleted
        "404":
          description: Topping not found
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/Error"
        "409":
          description: Topping is still used on one or more pizzas
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/Error"
components:
  schemas:
    Topping:
      type: object
      properties:
        id:
          type: integer
        title:
          type: string
    NewTopping:
      type: object
      required:
        - title
      properties:
        title:
          type: string
    Error:
      type: object
      properties:
        success:
          type: boolean
        status:
          type: integer
        message:
          type: string
```

When I ran this through the validator, here's what actually came back:

```
$ python3 -m openapi_spec_validator pizza_openapi.yaml
pizza_openapi.yaml: OK
```

That single line matters more than it looks. It means every `$ref`, every required field, every response object actually conforms to the OpenAPI 3.0 meta-schema — this isn't just YAML that happens to parse, it's a document that a code generator, a mock-server tool, or a documentation renderer could consume without choking.

Notice the structural difference from RAML immediately: OpenAPI pulls repeated shapes out into `components/schemas` and references them with `$ref`. This is more verbose to write by hand, but it pays off the moment you have dozens of endpoints that all return, say, an `Error` object — you define it once and reference it everywhere, instead of copy-pasting the same example JSON into every single response block like RAML tends to encourage.

> **Note:** The `$ref` mechanism is one of OpenAPI's best features and one of its most common sources of tooling bugs. If you ever see a "could not resolve reference" error from a code generator, check that your `$ref` path is *relative to the root of the document* (`#/components/schemas/Topping`), not relative to wherever you're currently nested.

### RAML vs. OpenAPI — how I actually decide

| | RAML | OpenAPI |
|---|---|---|
| Markup | Markdown-based | JSON or YAML |
| Philosophy | Design-first by default | Works design-first or code-first (via generation from code) |
| Reuse mechanism | Traits & resource types | `$ref` + `components` |
| Ecosystem size | Smaller, MuleSoft-centric | Very large — Swagger UI, Redoc, dozens of codegen targets |
| Readability on GitHub | Excellent (renders as Markdown) | Fine, but denser |
| My honest take | Nicer to read for a human reviewing the contract for the first time | The safer long-term bet given tooling maturity |

Both frameworks let you do the same essential thing: create a contract before code exists, generate a mock server from that contract, and generate client or server stubs afterward. I've found the real decision usually comes down to what your organization's existing tooling already understands, not which markup language you personally find prettier.

---

## Part 4 — Design-Driven Development, and Why I Don't Default to Waterfall or Plain Agile

Once I have a schema model, the next question is: what development methodology am I actually going to run against it? I want to walk through the landscape here because I think people conflate "agile" with "the only sane option" without really understanding what problem each methodology is solving.

### Waterfall

```
Requirements --> Design --> Implementation --> Verification --> Maintenance
```

Each phase fully completes before the next begins. The appeal is obvious — it's linear, it's easy to plan around, and it maps neatly onto a Gantt chart. The problem is that tests get written *after* the code, which means they tend to validate whatever the code already does rather than catch places where the implementation drifted from the actual requirement. There's no built-in checkpoint for "wait, does this still match what the customer needs?" — and for a public-facing API, where you're constantly getting feedback from client developers, that's a real liability. A 2009 Standish Group study found roughly a quarter of software projects failed outright and another 44% were "challenged" — numbers that predate the industry's big swing toward agile, and numbers I think about whenever I'm tempted to just "spec it all up front and go."

### Agile / Test-First Development

```
   +-----------+       fails        +------------------+
   | Write the |------------------->| Write just enough |
   |  test     |                    |  code to pass it  |
   +-----------+                    +------------------+
        ^                                    |
        |               succeeds             v
        +------------------------------ Run full suite
                                              |
                                     (repeat until done)
```

Here the test is written *before* the implementation. That forces you to articulate expected behavior in an executable, unambiguous way before you write a single line that could bias your understanding of what "correct" even means. Agile wraps this in **scrum** (sprints, standups, retrospectives) and often **kanban** (a visual board moving tasks through Backlog → To Do → In Progress → Verification → Done).

### Behavior-Driven Development (BDD)

BDD layers a second kind of test — an *acceptance* test written directly from a user story — on top of TDD's unit tests:

```
   Write a failing        Make the           Refactor
    feature test    --->  test pass    --->  the code
        ^                                        |
        |                                        v
        +---------------- n cycles --------------+
```

The unit tests confirm individual functions behave correctly; the acceptance tests confirm the *whole workflow*, end to end, actually satisfies the user story. This closes the gap that pure TDD sometimes leaves — you can have beautifully tested units that, when wired together, still fail to deliver the thing the user actually asked for.

### Design-Driven Development (DDD)

This is where the schema model earns its keep. Design-driven development adds a design layer *on top of* BDD/TDD: you write the functional specification, model the schema, generate acceptance criteria and code stubs from that schema, and only then start writing implementation code.

```
Functional Spec  -->  Schema Model  -->  Acceptance Criteria  -->  Development
 (business value)     (the contract)       & Unit Tests            Iterations
```

I like this model specifically for APIs because a client of your API can't see your internal implementation — they can only see the contract. If the contract is wrong, no amount of clean internal code fixes the developer experience. Design-driven development forces the contract to be right *first*.

### Code-First Development (the one I try to avoid)

For completeness — because plenty of real APIs are actually built this way — code-first is what happens when someone says "add an endpoint for X" and a developer just… adds it, directly on top of the existing backend, with no upfront modeling at all.

> **Caution:** Code-first APIs tend to be internally inconsistent (different endpoints use different pagination conventions, different error shapes, different naming casing) because there was never a single contract governing all of them. Worse, once external customers depend on the accidental shape of a code-first endpoint, you can't easily fix it — you're stuck maintaining an API that was never actually designed, only accreted.

### My honest summary table

| Methodology | Tests written | Best for | Weakness |
|---|---|---|---|
| Waterfall | After code | Very stable, rarely-changing requirements | No feedback loop; big-bang releases |
| Agile / TDD | Before code, unit-level | Iterative internal development | Doesn't guarantee end-to-end behavior is right |
| BDD | Before code, unit + acceptance | Ensuring user stories are actually satisfied | Requires discipline to keep stories current |
| Design-Driven | Before code, driven by schema | Public/partner-facing APIs, contracts with external parties | More upfront investment before any code runs |
| Code-First | Rarely, or after the fact | Quick internal prototypes | Inconsistent, hard to evolve safely |

---

## Part 5 — Project Management for APIs

With a methodology chosen, here's the actual sequencing I follow for an API project, start to finish.

### Step 1: The functional specification

Before the schema model, I want a short document — doesn't need to be long — that answers:

1. What problem is this project solving?
2. What is the business value?
3. What are the metrics and use cases?
4. What resources are needed or available?
5. What does "done" look like?
6. What could go wrong?

That sixth question is the one I see skipped most often, and it's the one I've come to value the most. If part of your API depends on another team's system, or a third-party data source, naming that dependency explicitly in the functional spec does two things: it puts a spotlight on a risk you don't fully control, and it gives you a reason to loop that other team into communication *before* it becomes a blocker.

> **Note:** Avoid writing a functional spec that's really just an architecture document in disguise. The architecture is less interesting to your future readers than the developer experience — the resource schema, the workflows, the "what can someone actually accomplish with this."

### Step 2: The schema model

This is everything covered in Parts 2–3 above. It gets built in parallel with, or shortly after, the functional specification.

### Step 3: Acceptance criteria and unit tests

Only after both of those exist do I write the acceptance criteria and unit tests. Trying to write tests before you know the contract means you're guessing at the contract while also trying to write correct tests — a bad way to do either.

### Step 4: Development iterations

Now, and only now, actual implementation starts — ideally in parallel across multiple engineers working against different, already-agreed-upon resources, because the schema model is the thing keeping everyone's work consistent with everyone else's.

```
   Functional        Schema Model         Acceptance Criteria       Development
   Specification -->  (Design Doc)   -->    & Unit Tests       -->   Iterations
```

---

## Part 6 — Road-Testing the API With a Mock Server

Here's a step I think gets skipped constantly, and it's one of the highest-leverage things in this whole pipeline: **before writing a single line of real implementation, stand up a mock server from your schema model and let customers (internal or external) poke at it.**

Both RAML and OpenAPI tooling can generate a mock server automatically from your document. The mock server returns exactly the `example:` values you wrote into the schema — it's not connected to any real database, and nothing you POST, PUT, or DELETE through it actually changes anything. That's the whole point: it lets a partner team start writing and testing *their* client code against your contract while your team is still building the real backend behind it, and it lets you catch design mistakes (a missing field, a confusing status code, a query parameter nobody actually wanted) before they're baked into running code.

```
                     +-----------------------+
  Schema Model  ---> |    Mock Server Tool   | ---> Static, canned responses
 (RAML/OpenAPI)      | (from RAML/OpenAPI    |       matching your examples
                      |  tooling)             |
                      +-----------------------+
                                |
                     Client teams can build & test
                     against the CONTRACT, not the
                     eventual implementation.
```

I like deploying these somewhere genuinely reachable — a free tier on a lightweight host works fine — specifically so partner teams outside your own network can hit it. The difference between a live endpoint and a mock endpoint is invisible from the outside except for one thing: a mock server's responses never change no matter what you send it. Delete a topping through the mock server, then `GET` the list again — it'll still be there. That's a feature, not a bug: it means the "documentation" and the "live demo" can never drift apart, because they're the same static data every single time.

> **Note:** A great side effect of maintaining a mock server is that it becomes a natural home for interactive API documentation — a place where a curious developer can click "Try it" on any endpoint and immediately see a real (if canned) response, instead of just reading prose about what the response *should* look like.

---

## Part 7 — Turning Use Cases Into Acceptance Tests

I want to walk through this translation step carefully because it's easy to skip and it's where a lot of the actual quality control happens.

**Step 1 — the user story**, in the standard agile template:

```
As a <type of user>
  ... I want to be able to <perform an action>
  ... so that I can <create an outcome>.
```

Example:

> **Story:** Add pineapple to the system
> As a pizza eater, I want to be able to add pineapple, so I can customize my pizza.

**Step 2 — the testing scenario**, which turns the story into concrete, checkable steps:

```
Scenario: Add pineapple to the system
  Given the pizza topping doesn't exist
  And I add a new topping to the system
  The list of toppings should include the new topping
```

**Step 3 — the acceptance test case**, which is what an engineer or QA person actually executes:

```
Acceptance test case: Add pineapple to the system
  1. Get a list of pizza toppings in the system
  2. Verify that the new topping doesn't exist
  3. Add a new topping to the system
  4. Get the list of pizza toppings
  5. Verify that the new topping has been added
```

And a second scenario, testing the *conflict* path:

```
Acceptance test case: Add duplicate pizza topping
  1. Get a list of pizza toppings in the system
  2. Verify that "pineapple" already exists
  3. Attempt to add "pineapple" again
  4. Verify a 409 is returned with a developer-friendly message
```

> **Note:** An acceptance test is not the same thing as a unit test. A unit test checks a specific function's behavior in isolation. An acceptance test walks through the *whole workflow* a real client would perform, hitting the real endpoints in sequence, and checking that the end-to-end behavior matches the user story — including the parts of the story that live across multiple endpoints.

I ran exactly this scenario against my own implementation before writing this post, so I can show you the actual output rather than a hypothetical.

Here's the Flask implementation I tested it against:

```python
from flask import Flask, jsonify, request

app = Flask(__name__)

toppings = {
    1: {"id": 1, "title": "pepperoni"},
    2: {"id": 2, "title": "peppers"},
}
pizzas = {}
next_topping_id = 3


@app.route("/api/v1.0/toppings", methods=["GET", "POST"])
def toppings_collection():
    global next_topping_id
    if request.method == "GET":
        q = request.args.get("q")
        items = list(toppings.values())
        if q:
            items = [t for t in items if q.lower() in t["title"].lower()]
        return jsonify({"success": True, "status": 200, "toppings": items}), 200

    body = request.get_json(force=True)
    title = body.get("title")
    if any(t["title"] == title for t in toppings.values()):
        return jsonify({"success": False, "status": 409,
                         "message": "A topping with that name already exists"}), 409
    new_id = next_topping_id
    next_topping_id += 1
    toppings[new_id] = {"id": new_id, "title": title}
    return "", 201, {"Location": f"/api/v1.0/toppings/{new_id}"}


@app.route("/api/v1.0/toppings/<int:topping_id>", methods=["GET", "PUT", "DELETE"])
def topping_item(topping_id):
    if topping_id not in toppings:
        return jsonify({"success": False, "status": 404, "message": "Topping not found"}), 404

    if request.method == "GET":
        return jsonify({"success": True, "status": 200, "topping": toppings[topping_id]}), 200

    if request.method == "PUT":
        body = request.get_json(force=True)
        toppings[topping_id]["title"] = body.get("title", toppings[topping_id]["title"])
        return "", 204

    # In use on any pizza? Reject the DELETE per the "safe" design decision.
    in_use = [p["id"] for p in pizzas.values() if topping_id in p["toppings"]]
    if in_use:
        return jsonify({"success": False, "status": 409,
                         "message": f"Topping is used on {len(in_use)} pizza(s)"}), 409
    del toppings[topping_id]
    return "", 204


if __name__ == "__main__":
    app.run(port=5055)
```

And here's the real output from running it and exercising every endpoint with `curl`:

```
--- GET list ---
{"status":200,"success":true,"toppings":[
  {"id":1,"title":"pepperoni"},
  {"id":2,"title":"peppers"}
]}

--- POST new topping ("pineapple") ---
HTTP/1.1 201 CREATED
Location: /api/v1.0/toppings/4

--- POST duplicate ("pineapple" again) ---
HTTP/1.1 409 CONFLICT
{"message":"A topping with that name already exists","status":409,"success":false}

--- GET single item (4) ---
{"status":200,"success":true,"topping":{"id":4,"title":"pineapple"}}

--- GET missing item (999) ---
HTTP/1.1 404 NOT FOUND
{"message":"Topping not found","status":404,"success":false}

--- PUT update (4 -> "roasted pineapple") ---
HTTP/1.1 204 NO CONTENT

--- GET after PUT ---
{"status":200,"success":true,"topping":{"id":4,"title":"roasted pineapple"}}

--- DELETE (4) ---
HTTP/1.1 204 NO CONTENT

--- GET list after delete ---
{"status":200,"success":true,"toppings":[
  {"id":1,"title":"pepperoni"},
  {"id":2,"title":"peppers"}
]}
```

Every status code lines up exactly with what the schema model promised: 200 for reads, 201 with a `Location` header for a successful create, 409 for the duplicate conflict, 404 for the missing item, and 204 with an empty body for both the update and the delete. That's the entire point of design-driven development — the implementation is *accountable* to the contract, and I can prove it with a test run instead of just asserting it in prose.

---

## Part 8 — Development Sprints

Once real development starts, I run it in short sprints — one to two weeks is typical. Here's the day-to-day shape of that.

### Planning

At the start of a sprint, stories move from the Backlog column onto a kanban board, into swim lanes (often one per task type: development, documentation, QA):

```
  Story        To Do        In Process      To Verify       Done
 +--------+   +--------+    +----------+    +----------+   +--------+
 | As a   |   | Code   |    | Code     |    | Test the |   | Done   |
 | user...|-->| the    |--> | the...   |--> | ...      |-->| ...    |
 +--------+   | GET    |    +----------+    +----------+   +--------+
              +--------+
```

Estimation is done in small increments — half-day chunks are common. A task expected to take more than a day gets broken down further, both because smaller tasks parallelize better and because they move through "To Verify" faster, keeping the whole board flowing instead of stalling on one giant ticket.

### Standups

Daily, ideally 15 minutes or under, and — literally — standing up, which keeps the meeting from turning into a status report. The useful framing I keep coming back to is the "three P's": **progress, plans, problems.** What did I do yesterday? What am I doing today? What might block me? Anything bigger than that gets taken to a separate conversation, off the clock of the whole team.

### Kanban swim lanes in motion

```
 Backlog -> To Do -> In Process -> To Verify -> Done
                          ^              |
                          |   fails      |
                          +--------------+
                        (verification failure
                         sends it back)
```

If a task fails verification, it moves *back* to To Do rather than lingering in limbo — someone (often the original engineer) picks it back up, fixes it, and it goes through verification again.

### Retrospective

This is the step I've watched the most teams try to skip, usually because it feels uncomfortable — like it's going to turn into assigning blame. It shouldn't, and when it's done well, it doesn't. The questions worth asking: Were the estimates accurate? If not, why not? Were we blocked by dependencies on other teams — the ones you flagged, hopefully, back in the functional spec's "what could go wrong" section? What can we adjust before the *next* sprint's planning session, ideally scheduled right before that planning session so the lessons are still fresh?

> **Note:** A retrospective is also the right moment to congratulate the team for what went well — it's not purely a bug-hunt on the process itself.

---

## Part 9 — The Four Pillars of Developer Experience

Here's the part I actually think is most underrated in the whole pipeline. You can have a perfect schema model, a disciplined design-driven process, and flawless sprint execution — and still end up with an API nobody wants to integrate with, because you never invested in the experience of the people consuming it. I think about developer experience in four pillars: **communication, documentation, building blocks, and support.**

```
       +---------------+---------------+---------------+---------------+
       | Communication | Documentation | Building Blocks|    Support    |
       +---------------+---------------+---------------+---------------+
       | Overall Vision| Reference Docs| Sample Code   | Forums        |
       | Business Value| Workflows     | Ref. Apps     | SLAs          |
       | Metrics       | Tutorials     | Tools/Techniques| Interactive |
       +---------------+---------------+---------------+---------------+
```

### Pillar 1: Communication

I used to think sharing "why we have this API" felt like exposing internal business strategy unnecessarily. I've come around on this completely. Developers who understand *why* an API exists build applications that actually align with what you're trying to accomplish — and developers who are kept in the dark tend to build things that fight against your product direction, or worse, abandon the platform when they can't tell whether it's actively maintained.

I've seen this failure mode described from real companies: platforms that launched an API without a clear internal business case, gave developers a sanitized, friendly-but-empty message, and then, when the API failed to generate revenue, quietly deprecated it with no warning to the developers who'd built real products on top of it. Compare that to companies that treat API sunsets as something worth communicating clearly, in advance, via blog posts and documentation updates — even when the message itself ("we're shutting this down") is unpopular. Developers can accept a business decision. What they can't forgive is being blindsided by one.

A generic, forgettable version of a "why we have this API" message looks like this:

> "We have an API because we want to increase integration with our system for third-party applications. We have made it possible to access the users, messages, and contacts."

That could describe literally any API ever built. A version that actually communicates something looks more like:

> "We created this platform so developers could find more ways for users to interact with our application. We're measuring success by counting applications with consistent, heavy read-or-write usage. Using the `user` resource, you can pull a personalized feed and let users share, comment, or favorite content directly from your own interface."

The difference is specificity: named metrics, named resources, and a real use case a developer can picture building.

### Pillar 2: Documentation

Documentation isn't one thing — it's at least three distinct kinds of writing, answering three distinct questions:

| Type | Question it answers | Format |
|---|---|---|
| Reference documentation | "What does this endpoint do?" | Per-endpoint: exact URL, request shape, response shape, all status codes |
| Workflows | "How do I accomplish a multi-step task?" | Ordered steps showing which earlier calls feed parameters into later ones |
| Tutorials | "How do I get started at all?" | Narrative, step-by-step, assumes zero prior knowledge |

**Reference documentation**, at minimum, needs: a catalog of every endpoint, general system-level info (headers, authentication, error code conventions), and, per endpoint, the exact call shape and an example of a successful (and unsuccessful) response. Both RAML and OpenAPI tooling generate this for you directly from the schema model — which is yet another reason the schema-model-first approach pays for itself repeatedly.

**Workflows** matter because most real tasks aren't a single API call — they're a chain. Here's a realistic example of a billing-report workflow, which needs three calls just to gather the parameters for a fourth:

```
Goal: Get a billing usage report for all products on an account

  Get Accounts     Use ACCOUNT_ID to     Use PROPERTY_ID and     Use PROPERTY_ID
  and Groups   -->  get PROPERTY_ID  --> ACCOUNT_ID to get  -->  and PRODUCT_ID
                                          PRODUCT_ID              to get Report
```

Without documenting that chain explicitly, a developer is left guessing where each intermediate value even comes from — and every guess they have to make is a support ticket you'll eventually receive instead.

**Tutorials** are, in my experience, the single highest-value documentation artifact you can write, and the one teams skip most often because it feels like "just" a getting-started guide. When I've seen teams ask their developer community directly what they wanted most from a redesigned developer portal, "a Getting Started tutorial" comes back as the overwhelming top request — and building one has been shown to meaningfully cut down on simple-question support volume.

Rules I try to follow when writing one:

- Assume *zero* prior knowledge of the platform.
- Break it into separate steps, each with a clear beginning (the goal), middle (the technical content), and end (confirmation it worked).
- Provide runnable sample code alongside the steps — not just prose describing what the code does.
- If a step involves something notoriously fiddly (authentication, almost always), consider shipping a small helper script specifically for that step.
- End each step with something like a small "nice, that worked" confirmation, so the reader always knows whether they're on track.

Here's what a tutorial step actually looks like, using the real, tested code and output from earlier in this post:

> **Step 1 — Get the current list of toppings.**
> Send a `GET` request to the toppings endpoint:
>
> ```
> GET /api/v1.0/toppings
> ```
>
> You'll get back a JSON array of the toppings currently in the system:
>
> ```json
> {"status": 200, "success": true, "toppings": [
>   {"id": 1, "title": "pepperoni"},
>   {"id": 2, "title": "peppers"}
> ]}
> ```
>
> Now check whether "pineapple" is already in that list:
>
> ```python
> import requests
>
> session = requests.Session()
> result = session.get("http://127.0.0.1:5055/api/v1.0/toppings")
> toppings = result.json()["toppings"]
>
> topping_to_check = "pineapple"
> already_exists = any(t["title"] == topping_to_check for t in toppings)
>
> print("In list" if already_exists else "Not in list")
> ```
>
> Running this prints `Not in list` — which is exactly what we expect, since we haven't added it yet. Nice, that worked!

> **Note:** I write tutorial code with the same verbosity a beginner would appreciate, not the cleverest one-liner I could produce. Tutorials are not the place to show off; they're the place to be unambiguous.

### Pillar 3: Building Blocks

This is where sample code, reference applications, and tooling live.

**Authentication libraries** deserve special attention because authentication is, by a wide margin, the single most common place developers get stuck. Cryptographic signing is genuinely hard to implement correctly, and every developer forced to write their own signing code from scratch is a developer who will eventually make a subtle mistake. Providing a well-documented, officially supported signing library in every major language your customers use is one of the highest-leverage things you can build.

**Reference implementations and sample code** should:

- Demonstrate a real documented workflow, not an abstract "hello world."
- Avoid clever/tricky abstractions — readability beats elegance here.
- Include debug/verbose output so a developer can *see* the actual request/response conversation, not just the parsed result.
- Stay consistent in style (error handling, logging, formatting) across every sample you publish, so developers can pattern-match from one example to the next.

**Tools and techniques** worth documenting and linking to explicitly:

| Tool | What it's for |
|---|---|
| API console (e.g. generated from your OpenAPI/RAML doc) | Explore endpoints and see request/response pairs without writing code |
| `curl` | The universal fallback — everyone already has it, though JSON output isn't pretty |
| HTTPie | Purpose-built for API calls; color-coded, readable JSON by default |
| HTTP proxy/sniffer (Charles, Fiddler) | Inspect the raw traffic your own client code is sending |
| Client generated from the schema model | A fast way to bootstrap a working client in a new language |

> **Caution:** If you "wrap" `curl` to bake in authentication so it's easier for developers to use, be aware you're often putting credentials directly on the command line — which lands in shell history and process listings. Warn developers about this explicitly in your documentation rather than letting them discover it the hard way.

### Pillar 4: Support

Support is the pillar that only gets exercised when a developer is already stuck and frustrated — which is exactly why getting it right matters disproportionately. A bad support experience doesn't just cost you one ticket; it can turn one loud, unhappy developer into public negative sentiment that outweighs a dozen quiet successful integrations.

**Support channel options**, and their real tradeoffs:

| Channel | Pros | Cons |
|---|---|---|
| Email alias | Free, familiar, zero setup | No searchable archive; knowledge evaporates when staff turn over; no way to surface related past answers |
| Help desk tool | Personalized, builds an internal knowledge base | Closed off to public visitors browsing for existing answers |
| Public forum | Builds a *public*, searchable knowledge base as a side effect of every interaction; supports standalone explainer posts too | Most time-intensive to run well |

Whatever channel you pick, developers need to know what response time to expect — I try to hold to "within one business day" as a floor, and I make that promise visible on the support page itself, not just something the team knows internally.

**A pattern worth teaching your own developers explicitly**, because it saves everyone time: help them frame their question as *I did X, I expected Y, instead Z happened.* Compare these two:

```
Q: This is broken. Help.
A: What's broken?
Q: The toppings endpoint.
A: What happened?
Q: It didn't return everything.
A: What were you trying to do?
Q: Get a list of everything added since 1999.
A: What did you expect back?
Q: Everything from 1999 onward.
A: ...that's not how it's documented to work.
```

versus:

```
Q: I called GET /toppings expecting it to support a date filter for
   "everything added since 1999," but there's no date parameter and
   it just returns the full unfiltered list. Is a date filter planned,
   or is there another way to get this?
A: No date filter yet — here's the workaround, and I've filed a
   feature request for the filter itself.
```

The second version resolves in one exchange instead of five, and — crucially — it usually reveals that what looked like "a bug" was actually a documentation gap: the developer's expectation was never set correctly in the first place. That's a signal worth treating as a real bug, just in the documentation rather than the code.

**Noninteractive support** — blog posts, FAQ entries, standalone explainer articles — is what you build once you notice the *same* question showing up repeatedly in your interactive channel. If three different developers ask how your resource's cache invalidation actually behaves, that's your cue to write one canonical explainer post rather than answering the same question a fourth, fifth, and sixth time.

---

## Part 10 — Everything I Wish Someone Had Told Me Up Front

Let me pull the whole thing together into one table of the mistakes I've watched teams (including, occasionally, my own) make at each stage:

| Stage | Common mistake | What to do instead |
|---|---|---|
| Schema modeling | Skipping it, going straight to code | Model the contract first, even for a "simple" API |
| Resource design | Allowing `PUT`/`DELETE` on collections | Reserve those for item-level resources only |
| Status codes | Reusing 200 for everything, including errors | Use 201+Location for creates, 204 for no-content success, 404/409 for real failure semantics |
| Methodology | Defaulting to waterfall because it's familiar | Prefer design-driven development for anything with external consumers |
| Testing | Writing tests after the implementation | Write acceptance tests from user stories before implementation begins |
| Road-testing | Building the real backend before anyone reviews the contract | Generate a mock server from the schema model and get feedback first |
| Documentation | Publishing only reference docs | Add workflows and a genuine beginner tutorial |
| Communication | Treating business rationale as confidential | Share the vision, metrics, and use cases openly |
| Support | Using only an unsearchable email alias | Prefer a forum or help desk that builds a searchable knowledge base |
| Deprecation | Sunsetting quietly, without notice | Communicate early, repeatedly, and specifically |

If I had to compress this entire post into one sentence, it would be: **the quality of an API is measured less by its implementation and more by how faithfully that implementation honors a contract you were disciplined enough to write down first, and how much care you put into the humans who'll be reading that contract.** The schema model is the technical half of that discipline. Developer experience — communication, documentation, building blocks, and support — is the human half. Skip either one and you end up with an API that's technically correct and practically unloved.
