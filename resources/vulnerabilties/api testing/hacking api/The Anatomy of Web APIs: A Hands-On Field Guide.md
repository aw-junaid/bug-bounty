# The Anatomy of Web APIs: A Hands-On Field Guide

*REST, GraphQL, SOAP, data formats, and every major authentication method, explained with diagrams and code I actually ran.*

---

## Table of Contents

1. [Why I wrote this](#why-i-wrote-this)
2. [How web APIs work](#how-web-apis-work)
3. [Let me build a tiny REST API](#let-me-build-a-tiny-rest-api)
4. [RESTful APIs and their six constraints](#restful-apis-and-their-six-constraints)
5. [Headers I look for first](#headers-i-look-for-first)
6. [GraphQL: the query-centric API](#graphql-the-query-centric-api)
7. [SOAP: the old guard](#soap-the-old-guard)
8. [API specifications: OAS and RAML](#api-specifications-oas-and-raml)
9. [Data interchange formats: JSON, XML, YAML](#data-interchange-formats-json-xml-yaml)
10. [Encoding is not encryption](#encoding-is-not-encryption)
11. [API authentication, method by method](#api-authentication-method-by-method)
12. [Putting it together: what happens when you search on Twitter](#putting-it-together-what-happens-when-you-search-on-twitter)
13. [My cheat sheet](#my-cheat-sheet)
14. [Closing thoughts](#closing-thoughts)

---

## Why I wrote this

Most people know a web application only through what they can see and click. The buttons, images, feeds, and search bars make up the graphical user interface (GUI) in the browser. But the GUI is a thin skin. Underneath it, **APIs do most of the work**: they let one piece of software use the data and functionality of another over HTTP, and they feed the GUI with the text, images, and videos you finally see.

When I started digging into APIs, I kept running into the same problem. The vocabulary arrives all at once: *endpoint, resource, gateway, microservice, bearer token, HMAC, mutation, schema*. Each term makes sense on its own, but the picture only clicks when you connect them.

So in this post I walk through the whole landscape in the order I wish someone had shown me. I cover the terminology, the main API types, the data formats, and the authentication methods. Then I tie everything together with a real example: what happens when you search for "hacking" on Twitter.

I also don't want this to be theory only. Every code sample and terminal session below was **executed in a real environment** (Python 3.12, Flask 3.1, `requests`, `graphql-core`, PyYAML, `curl`), and the outputs you see are the outputs I got.

> **📝 Note**
> Everything in this post is meant for learning and for building or testing APIs you own or are explicitly authorized to test. The weaknesses I mention are there so you can defend against them.

---

## How web APIs work

Like web applications, web APIs rely on HTTP to create a client/server relationship. Two roles matter:

- The **provider** hosts the API.
- The **consumer** is the person or system making requests to it.

A consumer asks for things from an **endpoint**, which is simply a URL that exposes part of the API. Each of these is a different endpoint:

```text
https://example.com/api/v3/users/
https://example.com/api/v3/customers/
https://example.com/api/updated_on/
https://example.com/api/state/1/
```

### Resources: singletons, collections, and subcollections

The data being requested is called a **resource**. I find it useful to sort resources into three shapes:

| Shape | What it is | Example |
|---|---|---|
| **Singleton** | One unique object | `/api/user/{user_id}` |
| **Collection** | A group of resources | `/api/profiles/users` |
| **Subcollection** | A collection that lives inside one resource | `/api/user/{user_id}/settings` |

The last row reads as: "the settings belonging to one specific user."

### The journey of a request

A request doesn't go straight from your device to the code that answers it. It passes through an **API gateway**, which is the front door of the application. Whether you're on a phone, a laptop, or an IoT device, you all come through the same door.

```mermaid
flowchart LR
    subgraph Clients["Consumers"]
        A["📱 Mobile app"]
        B["💻 Browser"]
        C["🔌 IoT device"]
    end

    G{{"🚪 API Gateway<br/>auth · rate limiting<br/>SSL · load balancing"}}

    subgraph Services["Microservices"]
        S1["💳 Billing"]
        S2["👤 Accounts"]
        S3["✉️ Email receipts"]
    end

    A --> G
    B --> G
    C --> G
    G --> S1
    G --> S2
    G --> S3

    classDef client fill:#dbeafe,stroke:#1d4ed8,stroke-width:2px,color:#1e3a8a
    classDef gate fill:#fde68a,stroke:#b45309,stroke-width:3px,color:#78350f
    classDef svc fill:#dcfce7,stroke:#15803d,stroke-width:2px,color:#14532d
    class A,B,C client
    class G gate
    class S1,S2,S3 svc
```

The gateway is a busy component. It:

- filters bad requests,
- monitors incoming traffic,
- routes each request to the right service,
- and often enforces **authentication, authorization, encryption in transit (SSL/TLS), rate limiting, and load balancing**.

### Monolith vs. microservices

Behind the gateway, the backend can be built in two broad ways:

| | **Monolithic** | **Microservices** |
|---|---|---|
| Structure | All services live inside one application | Each service is its own standalone application |
| Example | Billing, accounts, and receipts in one codebase | Three separate apps talking through APIs |
| Deployment | Deploy everything together | Deploy each piece independently |
| Failure blast radius | One bug can take down everything | Failures are more isolated |

A **microservice** is a modular piece of a web app that handles one specific function. Think of a checkout page: one feature bills the card, another records the customer account, a third emails the receipt. Microservices use APIs to pass data and trigger actions between each other.

The important point is that **the consumer never sees this backend design**. All they see are the endpoints they can call and the resources they can reach.

### The API contract: documentation

Those endpoints and resources are described in the **API contract**: human-readable documentation explaining how to use the API and how it behaves. It usually covers authentication requirements, permission levels, endpoints, and required parameters, and often includes examples.

> **💡 Tip**
> From a security tester's point of view, documentation is gold. It can reveal which endpoints return customer data, which keys you'd need for an administrator role, and even business logic flaws. From a developer's point of view, that's exactly why you should review what your own docs reveal.

Here's the structure of a well-documented endpoint, modeled on GitHub's "revoke a grant for an application" page:

```text
DELETE /applications/{client_id}/grants/{access_token}

Name          Type    In      Description
accept        string  header  application/vnd.github.v3+json is recommended
client_id     string  path    The client ID of your GitHub app
access_token  string  body    Required. The OAuth token used to authenticate
```

Three things make this good documentation:

1. It states the **purpose** of the request.
2. It gives the **HTTP method** and the **endpoint**.
3. It lists each **parameter's name, type, location, and description**.

By convention, **curly brackets** mean "replace this with a real value." Some docs use a colon (`/api/v2/:customers/`) or square brackets for the same thing.

### CRUD: the four verbs behind everything

Most API behavior boils down to four actions, known as **CRUD**:

| CRUD | Meaning | Typical HTTP method(s) |
|---|---|---|
| **C**reate | Make a new record | `POST` |
| **R**ead | Retrieve data | `GET` |
| **U**pdate | Modify an existing record | `PUT`, `POST` (and often `PATCH`) |
| **D**elete | Erase a record | `DELETE`, sometimes `POST` |

> **⚠️ Caution**
> CRUD is a *best practice*, not a law. Developers are free to implement APIs differently. I've seen `POST /deleteUser` and `GET /api/logout`. Never assume the method name tells you what an endpoint really does.

---

## Let me build a tiny REST API

Reading definitions only goes so far, so I built a small inventory API for a pretend pillow store. It uses Flask and demonstrates a bearer token, `GET`/`POST`/`DELETE`, error codes, and a couple of response headers.

**`rest_shop.py`**

```python
"""A tiny REST API (Flask) that demonstrates the ideas from the chapter."""
from flask import Flask, jsonify, request

app = Flask(__name__)

INVENTORY = {
    "pillow": {"id": "00101", "name": "pillow", "count": 25,
               "price": {"currency": "USD", "value": "19.99"}},
}
VALID_TOKEN = "Ab4dtok3n"


def authorized():
    header = request.headers.get("Authorization", "")
    return header == f"Bearer {VALID_TOKEN}"


@app.get("/api/v3/inventory/item/<name>")
def get_item(name):
    if not authorized():
        return jsonify(error="missing or invalid token"), 401
    item = INVENTORY.get(name)
    if item is None:
        return jsonify(error="item not found"), 404
    resp = jsonify(item=item)
    resp.headers["Cache-Control"] = "no-store"
    resp.headers["X-Response-Time"] = "3"
    return resp


@app.post("/api/v3/inventory/item")
def create_item():
    if not authorized():
        return jsonify(error="missing or invalid token"), 401
    body = request.get_json(silent=True) or {}
    if "name" not in body:
        return jsonify(error="name is required"), 400
    INVENTORY[body["name"]] = {"id": "00102", **body}
    return jsonify(item=INVENTORY[body["name"]]), 201


@app.delete("/api/v3/inventory/item/<name>")
def delete_item(name):
    if not authorized():
        return jsonify(error="missing or invalid token"), 401
    if INVENTORY.pop(name, None) is None:
        return jsonify(error="item not found"), 404
    return "", 204


if __name__ == "__main__":
    app.run(port=5055)
```

> **⚠️ Caution**
> This server is a learning toy. It has a hardcoded token, no TLS, and no rate limiting. Run it only on `localhost`, and never deploy something like it.

### Calling it with curl

First, a request with **no token**:

```console
$ curl -i http://localhost:5055/api/v3/inventory/item/pillow
HTTP/1.1 401 UNAUTHORIZED
Content-Type: application/json
Content-Length: 37
Connection: close

{"error":"missing or invalid token"}
```

Now with the bearer token and an `Accept` header:

```console
$ curl -i -H "Authorization: Bearer Ab4dtok3n" \
          -H "Accept: application/json" \
          http://localhost:5055/api/v3/inventory/item/pillow
HTTP/1.1 200 OK
Content-Type: application/json
Content-Length: 94
Cache-Control: no-store
X-Response-Time: 3
Connection: close

{"item":{"count":25,"id":"00101","name":"pillow","price":{"currency":"USD","value":"19.99"}}}
```

A resource that doesn't exist:

```console
$ curl -i -H "Authorization: Bearer Ab4dtok3n" \
       http://localhost:5055/api/v3/inventory/item/sofa
HTTP/1.1 404 NOT FOUND
Content-Type: application/json

{"error":"item not found"}
```

Creating a record (`POST`), then a malformed create, then deleting:

```console
$ curl -i -X POST -H "Authorization: Bearer Ab4dtok3n" \
       -H "Content-Type: application/json" \
       -d '{"name":"blanket","count":10}' \
       http://localhost:5055/api/v3/inventory/item
HTTP/1.1 201 CREATED
Content-Type: application/json

{"item":{"count":10,"id":"00102","name":"blanket"}}

$ curl -i -X POST -H "Authorization: Bearer Ab4dtok3n" \
       -H "Content-Type: application/json" \
       -d '{"count":10}' \
       http://localhost:5055/api/v3/inventory/item
HTTP/1.1 400 BAD REQUEST
Content-Type: application/json

{"error":"name is required"}

$ curl -i -X DELETE -H "Authorization: Bearer Ab4dtok3n" \
       http://localhost:5055/api/v3/inventory/item/blanket
HTTP/1.1 204 NO CONTENT
```

Here is a summary of the status codes I just triggered:

| Code | Name | Why I got it |
|---|---|---|
| `200` | OK | Valid token, existing item |
| `201` | Created | `POST` created a new record |
| `204` | No Content | `DELETE` succeeded, nothing to return |
| `400` | Bad Request | I forgot the required `name` field |
| `401` | Unauthorized | No token supplied |
| `404` | Not Found | `sofa` isn't in the inventory |

Here's the same conversation as a sequence diagram:

```mermaid
sequenceDiagram
    autonumber
    participant C as Consumer (curl)
    participant P as Provider (Flask)

    rect rgb(254, 226, 226)
    C->>P: GET /item/pillow (no token)
    P-->>C: 401 Unauthorized
    end

    rect rgb(220, 252, 231)
    C->>P: GET /item/pillow + Bearer token
    P-->>C: 200 OK + JSON body
    end

    rect rgb(254, 249, 195)
    C->>P: GET /item/sofa + Bearer token
    P-->>C: 404 Not Found
    end

    rect rgb(219, 234, 254)
    C->>P: POST /item {"name": "blanket"}
    P-->>C: 201 Created
    C->>P: DELETE /item/blanket
    P-->>C: 204 No Content
    end
```

### The same thing from Python

Here is a small client using the `requests` library. I included client-side filtering on purpose, because it shows something important about REST that I'll come back to.

**`client.py`**

```python
import requests

BASE = "http://localhost:5055/api/v3/inventory/item"
HEADERS = {"Authorization": "Bearer Ab4dtok3n", "Accept": "application/json"}

def get_item(name):
    r = requests.get(f"{BASE}/{name}", headers=HEADERS, timeout=5)
    if r.status_code == 200:
        return r.json()["item"]
    raise RuntimeError(f"{r.status_code}: {r.json()['error']}")

item = get_item("pillow")
print("Full object   :", item)
print("Only what I need:", {"name": item["name"], "price": item["price"]["value"]})

try:
    get_item("sofa")
except RuntimeError as e:
    print("Error handled :", e)

# Statelessness: no token, no access - the server does not remember me
r = requests.get(f"{BASE}/pillow", timeout=5)
print("No token      :", r.status_code, r.json())
```

Output:

```console
$ python3 client.py
Full object   : {'count': 25, 'id': '00101', 'name': 'pillow', 'price': {'currency': 'USD', 'value': '19.99'}}
Only what I need: {'name': 'pillow', 'price': '19.99'}
Error handled : 404: item not found
No token      : 401 {'error': 'missing or invalid token'}
```

Notice two things. The server returned **everything it knew** about the pillow, and my client had to filter out what it didn't need. And the last call proves the API is **stateless**: the server did not remember that I was authorized a moment ago.

---

## RESTful APIs and their six constraints

**REST** (Representational State Transfer) is a set of architectural constraints for applications that talk over HTTP. An API that follows them is called RESTful. REST was designed to improve on older, heavier approaches such as SOAP, and because it relies entirely on plain HTTP, it's very approachable.

The constraints are really "shoulds" rather than "musts," which is why REST is better described as a **style** than a protocol.

```mermaid
mindmap
  root((REST))
    Uniform interface
      Any device, same access
    Client/server
      Consumers and providers separated
    Stateless
      Every request is self-contained
    Cacheable
      Responses say if they can be cached
    Layered system
      Client doesn't see the architecture
    Code on demand
      Optional
```

| Constraint | What it means in plain words | Why I care |
|---|---|---|
| **Uniform interface** | A phone, an IoT sensor, and a laptop all access the server the same way | Predictable behavior |
| **Client/server** | Consumers request, providers supply | Each side can evolve independently |
| **Stateless** | Each request is treated like the first one | Server doesn't need to remember me, so it scales well |
| **Cacheable** | Responses declare whether they can be stored | Faster responses, less server load |
| **Layered system** | I can't tell if I'm talking to the real server or an intermediary | Flexibility for providers |
| **Code on demand** *(optional)* | The server can send executable code to the client | Rarely used |

### Statelessness and tokens

Because REST doesn't keep state, the consumer must send **everything the provider needs** with each request. That's why tokens exist: they create a state-like experience without the server having to remember me from one call to the next.

### Caching, briefly

Caching speeds things up by storing commonly requested data closer to the consumer. A request travels down a chain, and the further down it goes, the more expensive it gets:

```mermaid
flowchart TD
    R["Request"] --> CC{"Client cache<br/>has it?"}
    CC -- "Yes ✅" --> D1["Return instantly<br/>no server cost"]
    CC -- "No" --> SC{"Server cache<br/>has it?"}
    SC -- "Yes ✅" --> D2["Return quickly<br/>low cost"]
    SC -- "No" --> DB[("Database / other servers")]
    DB --> D3["Return slowly<br/>highest cost"]

    style R fill:#e0e7ff,stroke:#4338ca,stroke-width:2px
    style CC fill:#fef3c7,stroke:#d97706,stroke-width:2px
    style SC fill:#fef3c7,stroke:#d97706,stroke-width:2px
    style DB fill:#fee2e2,stroke:#b91c1c,stroke-width:2px
    style D1 fill:#bbf7d0,stroke:#15803d,stroke-width:2px
    style D2 fill:#d9f99d,stroke:#4d7c0f,stroke-width:2px
    style D3 fill:#fecaca,stroke:#b91c1c,stroke-width:2px
```

APIs typically manage this with headers that say when cached data expires. In my toy server I set `Cache-Control: no-store`, which tells everyone **not** to cache the response.

> **📝 Note**
> "RESTful" is often a marketing label. An organization can call its API RESTful without following all the constraints, so I never assume a given API meets every one of them. It might also use methods beyond CRUD, subdomains instead of paths, or its own rate-limit rules.

---

## Headers I look for first

Headers are the quickest way for me to tell what kind of API I'm looking at. Headers, naming conventions, and the data format together are the best indicators of an API's type. These three families show up constantly in REST.

### Authorization

The format is `Authorization: <type> <token/credentials>`.

```http
Authorization: Bearer Ab4dtok3n
```

| Type | What it carries |
|---|---|
| `Basic` | Base64-encoded `username:password` |
| `Bearer` | An API token |
| `AWS-HMAC-SHA256` | AWS signature built from an access key and secret key |

### Content-Type vs. Accept

These two get confused all the time:

- **`Content-Type`** describes the media I am **sending**.
- **`Accept`** says which media I want to **receive**.

| Content-Type value | Meaning |
|---|---|
| `application/json` | JSON, by far the most common for REST |
| `application/xml` | XML |
| `application/x-www-form-urlencoded` | Key/value pairs joined by `=` and separated by `&` |

### Middleware (`X-`) headers

Any header beginning with `X-` is a middleware header, and they can serve almost any purpose:

| Header | Typical use |
|---|---|
| `X-Response-Time` | How long the response took to process |
| `X-API-Key` | Carrying an API key |
| `X-Powered-By` | Info about backend technology |
| `X-Rate-Limit` | Requests allowed in a time window |
| `X-RateLimit-Remaining` | Requests left before throttling |

> **⚠️ Caution**
> `X-Powered-By` and similar headers leak information about your stack. If you operate an API, consider removing headers that reveal framework names and versions.

---

## GraphQL: the query-centric API

**GraphQL** (Graph Query Language) is a specification that lets clients define the **structure of the data they want back**. It follows the REST constraints (the chapter I'm drawing on calls it RESTful), but it works more like a database query language, similar in spirit to SQL. Resources are stored in a graph, and the consumer usually talks to a **single entry point** with `POST`, putting the query in the body.

### The problem GraphQL solves

With REST, I often hit two annoyances:

- **Over-fetching**: the server sends fields I don't need, as in my pillow example.
- **Under-fetching**: I need several endpoints to assemble all the data.

GraphQL lets me ask for exactly the fields I want in one request.

```mermaid
flowchart LR
    subgraph REST["REST: many endpoints"]
        direction TB
        R1["GET /items/101"] --> RR["Returns EVERYTHING<br/>id, name, price, qty, brand..."]
        R2["GET /items/101/stock"] --> RR2["Returns another full object"]
    end
    subgraph GQL["GraphQL: one endpoint"]
        direction TB
        G1["POST /graphql<br/>{ inventory { name price } }"] --> GR["Returns ONLY<br/>name and price"]
    end

    style REST fill:#fee2e2,stroke:#b91c1c,stroke-width:2px,color:#7f1d1d
    style GQL fill:#dcfce7,stroke:#15803d,stroke-width:2px,color:#14532d
    style R1 fill:#fecaca,stroke:#b91c1c
    style R2 fill:#fecaca,stroke:#b91c1c
    style RR fill:#fecaca,stroke:#b91c1c
    style RR2 fill:#fecaca,stroke:#b91c1c
    style G1 fill:#bbf7d0,stroke:#15803d
    style GR fill:#bbf7d0,stroke:#15803d
```

### Three operations

GraphQL still does CRUD, but through three operation types inside a `POST` body:

| Operation | Purpose | CRUD equivalent |
|---|---|---|
| **query** | Retrieve data | Read |
| **mutation** | Submit and write data | Create, Update, Delete |
| **subscription** | Push data to me when an event happens | Read (live updates) |

### A working example

I wrote a GraphQL schema with `graphql-core` that mirrors the graphics-card inventory example. A **schema** is the collection of data that can be queried, and having it is a bit like having a REST API collection in hand.

**`graphql_shop.py`**

```python
from graphql import (GraphQLSchema, GraphQLObjectType, GraphQLField, GraphQLString,
                     GraphQLInt, GraphQLFloat, GraphQLList, GraphQLArgument, graphql_sync)

Field = GraphQLObjectType("Field", {"price": GraphQLField(GraphQLFloat),
                                    "quantity": GraphQLField(GraphQLInt)})
Inventory = GraphQLObjectType("Inventory", {
    "name": GraphQLField(GraphQLString),
    "id": GraphQLField(GraphQLString),
    "brand": GraphQLField(GraphQLString),
    "fields": GraphQLField(GraphQLList(Field)),
})
DATA = {"name": "Graphics Card", "id": "00101", "brand": "Acme",
        "fields": [{"price": 999.99, "quantity": 25}]}
Query = GraphQLObjectType("Query", {"inventory": GraphQLField(
    Inventory,
    args={"item": GraphQLArgument(GraphQLString), "id": GraphQLArgument(GraphQLString)},
    resolve=lambda root, info, item=None, id=None: DATA)})
schema = GraphQLSchema(query=Query)

q = '{ inventory(item: "Graphics Card", id: "00101") { name fields { price quantity } } }'
r = graphql_sync(schema, q)
import json
print(json.dumps(r.data, indent=2))

print("--- unknown field ---")
r = graphql_sync(schema, '{ inventory(item:"x") { colour } }')
print(r.errors[0].message)

print("--- introspection ---")
r = graphql_sync(schema, '{ __schema { queryType { name } types { name } } }')
print([t["name"] for t in r.data["__schema"]["types"] if not t["name"].startswith("__")])
```

Output:

```console
$ python3 graphql_shop.py
{
  "inventory": {
    "name": "Graphics Card",
    "fields": [
      {
        "price": 999.99,
        "quantity": 25
      }
    ]
  }
}
--- unknown field ---
Cannot query field 'colour' on type 'Inventory'.
--- introspection ---
['Query', 'Inventory', 'String', 'Field', 'Float', 'Int', 'Boolean']
```

Look at the first result. The `Inventory` type also has `id` and `brand`, but because I didn't ask for them, they never came back. That's the entire pitch of GraphQL in one screen.

The introspection query at the end is the part I find most interesting. A GraphQL server can often **describe its own schema** on request. That's wonderful for developers and tooling, and it's also why it's worth deciding deliberately whether introspection should be on in production.

> **📝 Note**
> In the raw HTTP form, the request looks like this (the `id` here is quoted because I declared it a string in my schema; some examples in the wild use bare numbers when the schema says `Int`):
>
> ```http
> POST /graphql HTTP/1.1
> Host: graphql-shop.com
> Authorization: Bearer ab4dt0k3n
> Content-Type: application/json
>
> {"query": "{ inventory(item: \"Graphics Card\", id: \"00101\") { name fields { price quantity } } }"}
> ```

### How to explore GraphQL

You can use a browser-based IDE such as **GraphiQL** when one is exposed. Otherwise you need a client such as **Postman**, Apollo Client, GraphQL-Request, GraphQL-CLI, or GraphQL-Compose.

---

## SOAP: the old guard

**SOAP** (Simple Object Access Protocol) is an older, action-oriented API format that relies on **XML**. It can run over HTTP, SMTP, TCP, and UDP, but it was designed mainly for HTTP, and when used that way **every request is a `POST`**.

A SOAP message has four parts:

| Part | Required? | Role |
|---|---|---|
| **Envelope** | Yes | The XML tag that says "this is a SOAP message" |
| **Header** | Yes (in the chapter's framing) | Information for processing the message |
| **Body** | Optional | The main XML payload |
| **Fault** | Optional | Error information in a response |

A trimmed request looks like this:

```xml
POST /Inventory HTTP/1.1
Host: www.soap-shop.com
Content-Type: application/soap+xml; charset=utf-8

<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope/">
  <soap:Body xmlns:m="http://www.soap-shop.com/inventory">
    <m:GetInventoryPrice>
      <m:InventoryName>ThebestSOAP</m:InventoryName>
    </m:GetInventoryPrice>
  </soap:Body>
</soap:Envelope>
```

Headers in machine-to-machine communication are essentially an **agreement**: they make sure consumer and provider are "speaking the same language." SOAP's strictness is both its strength and the reason newer projects usually pick REST or GraphQL.

---

## API specifications: OAS and RAML

Because REST is a style, every REST API ends up a little different. **API specifications** (description languages) fix this by giving organizations a standard way to design APIs and generate consistent documentation.

I like the electrical-socket analogy: if every appliance used a different plug, life would be miserable. A shared socket standard lets you buy a toaster and plug it in anywhere. Specifications play that role for APIs, because a consumer can write tooling that ingests the specification and then talks to *any* API that uses it.

| Specification | Format | Notes |
|---|---|---|
| **OpenAPI Specification 3.0 (OAS)**, formerly Swagger | JSON or YAML | One of the leading specifications for REST. Describes endpoints, resources, operations, and auth requirements |
| **RAML** (RESTful API Modeling Language) | YAML only | Open specification for documenting, designing, building, and testing REST APIs |

> **💡 Tip**
> If an API publishes an OpenAPI file, import it straight into Postman. You instantly get every documented endpoint as a ready-to-run request. It's the fastest way I know to learn a new API.

---

## Data interchange formats: JSON, XML, YAML

APIs exchange data in a handful of standard formats, and specifications use them too. Recognizing each format helps me work out what kind of API I'm facing.

### JSON

**JSON** (JavaScript Object Notation) is the dominant format. It's easy for humans to read and easy for programs to parse. Data is stored as key/value pairs inside curly brackets:

```json
{
  "firstName": "James",
  "lastName": "Lovell",
  "tripsToTheMoon": 2,
  "isAstronaut": true,
  "walkedOnMoon": false,
  "comment": "This is a comment",
  "spacecrafts": ["Gemini 7", "Gemini 12", "Apollo 8", "Apollo 13"],
  "book": [
    { "title": "Lost Moon", "genre": "Non-fiction" }
  ]
}
```

JSON supports six value types:

| Type | Example | Notes |
|---|---|---|
| String | `"Hack the planet"` | Double quotes only |
| Number | `101`, `-102`, `1.03`, `1.0E+4` | Integers, decimals, negatives, exponents |
| Boolean | `true`, `false` | Lowercase |
| Null | `null` | No value |
| Array | `["1","2","3"]` | Ordered, in square brackets |
| Object | `{"admin": false}` | Unordered key/value pairs in curly brackets |

> **📝 Note**
> **JSON has no inline comments.** That's why you see tricks like `"comment": "This is a comment"` as a normal key/value pair.

### XML

**XML** wraps data in descriptive tags. REST can use it, but it's most associated with SOAP, which can *only* use XML. Everything starts with a **prolog** (version and encoding), then a single **root element** that contains child elements. Comments look like `<!-- this -->`.

### YAML

**YAML** ("YAML Ain't Markup Language," a recursive acronym) was designed to be more human-readable still. It uses indentation instead of brackets, makes quotes optional for most strings, and allows `#` comments.

### Same data, three formats, measured

I converted one tweet-like object into all three formats and compared sizes, so the claim "XML is wordy" has an actual number behind it.

**`formats_demo.py`**

```python
import json, yaml
import xml.etree.ElementTree as ET

tweet = {
    "id": 1278533978970976256,
    "id_str": "1278533978970976256",
    "full_text": "1984: William Gibson published his debut novel, Neuromancer.",
    "truncated": False,
}

as_json = json.dumps(tweet, separators=(",", ":"))
root = ET.Element("root")
for k, v in tweet.items():
    ET.SubElement(root, k).text = str(v).lower() if isinstance(v, bool) else str(v)
as_xml = '<?xml version="1.0" encoding="UTF-8" ?>' + ET.tostring(root, encoding="unicode")
as_yaml = yaml.safe_dump(tweet, sort_keys=False, width=200)

for name, text in (("JSON", as_json), ("XML", as_xml), ("YAML", as_yaml)):
    print(f"{name:<5}{len(text.encode()):>4} bytes")
print("\n--- YAML ---"); print(as_yaml)
print("round-trip equal:", yaml.safe_load(as_yaml) == tweet == json.loads(as_json))
```

```console
$ python3 formats_demo.py
JSON  150 bytes
XML   227 bytes
YAML  145 bytes

--- YAML ---
id: 1278533978970976256
id_str: '1278533978970976256'
full_text: '1984: William Gibson published his debut novel, Neuromancer.'
truncated: false

round-trip equal: True
```

| Format | Bytes (my test) | Readability | Comments | Typical home |
|---|---|---|---|---|
| JSON (minified) | 150 | Good | No | REST, GraphQL |
| XML | 227 | Verbose | Yes | SOAP |
| YAML | 145 | Excellent | Yes (`#`) | API specs, config |

XML came out about **50% larger** than JSON for the same data. The exact numbers depend on the payload (my sample text is shorter than a real tweet), but the pattern is consistent: the closing tags cost bytes.

> **⚠️ Caution**
> Always use `yaml.safe_load`, never `yaml.load`, on data you didn't write. The unsafe loader can construct arbitrary Python objects from untrusted input.

---

## Encoding is not encryption

APIs and HTTP use **encoding** to make sure messages travel intact. Certain "bad characters" can confuse the technologies on the server, so schemes like Unicode, HTML encoding, URL encoding, and base64 reformat the message.

Here's the string `hAPI hacker` in three encodings, produced by a script I ran:

```python
import base64
s = "hAPI hacker"
print("UTF-8 hex :", "".join(f"\\x{b:02X}" for b in s.encode("utf-8")))
print("UTF-16 cp :", "".join(f"\\u{{{ord(c):x}}}" for c in s))
print("base64    :", base64.b64encode(s.encode()).decode())
```

```console
UTF-8 hex : \x68\x41\x50\x49\x20\x68\x61\x63\x6B\x65\x72
UTF-16 cp : \u{68}\u{41}\u{50}\u{49}\u{20}\u{68}\u{61}\u{63}\u{6b}\u{65}\u{72}
base64    : aEFQSSBoYWNrZXI=
```

> **📝 Note**
> The "UTF-16" line above is really a list of Unicode code points written in `\u{…}` notation, which is how the source material presents it. Actual UTF-16 on the wire is two-byte units. The point of the exercise is recognizing the shapes: `\x..` pairs, `\u{..}` groups, and base64's `=` padding.

> **🔥 Danger**
> **Base64 is not a secret.** Anyone can decode it in one command. Never treat a base64 string as protected data.

---

## API authentication, method by method

An API can be fully public, but as soon as it touches proprietary or sensitive data it needs two things:

- **Authentication**: proving *who you are*.
- **Authorization**: deciding *what you may access*.

On a traditional website, you send credentials, the server compares them with what it stored, creates a **session**, and issues a **cookie**. REST and GraphQL are stateless, so there's no session. **Every request has to prove identity again.**

All methods share one principle: the consumer sends *something* to the provider, and the provider links it to a user before allowing or denying access.

Here's how I choose between them when I'm trying to understand a system:

```mermaid
flowchart TD
    Start(["Does the API expose<br/>sensitive or private data?"]) -->|No| NoAuth["No authentication<br/>public data only"]
    Start -->|Yes| Q2{"Is a third party acting<br/>on the user's behalf?"}
    Q2 -->|Yes| OAuth["OAuth 2.0<br/>delegated, time-limited tokens"]
    Q2 -->|No| Q3{"Need to verify message<br/>integrity too?"}
    Q3 -->|Yes| HMAC["HMAC<br/>signed requests, as AWS does"]
    Q3 -->|No| Q4{"Want self-contained<br/>stateless tokens?"}
    Q4 -->|Yes| JWT["JWT<br/>header.payload.signature"]
    Q4 -->|No| Q5{"Simple service-to-service<br/>or developer access?"}
    Q5 -->|Yes| Key["API key"]
    Q5 -->|No| Basic["Basic auth<br/>only over TLS, ideally once"]

    style Start fill:#e0e7ff,stroke:#4338ca,stroke-width:2px
    style NoAuth fill:#e5e7eb,stroke:#4b5563
    style OAuth fill:#bbf7d0,stroke:#15803d,stroke-width:2px
    style HMAC fill:#bfdbfe,stroke:#1d4ed8,stroke-width:2px
    style JWT fill:#ddd6fe,stroke:#6d28d9,stroke-width:2px
    style Key fill:#fde68a,stroke:#b45309,stroke-width:2px
    style Basic fill:#fecaca,stroke:#b91c1c,stroke-width:2px
```

### Basic authentication

The simplest method: the consumer puts a username and password in a header or body, either in plaintext (`username:password`) or base64-encoded to save space. Let me prove how weak the encoding is, using the terminal:

```console
$ echo "username:password" | base64
dXNlcm5hbWU6cGFzc3dvcmQK
$ echo "dXNlcm5hbWU6cGFzc3dvcmQK" | base64 -d
username:password
```

> **📝 Note**
> That trailing `K` appears because `echo` adds a newline character, which gets encoded too. HTTP Basic auth is normally computed without a newline. Using `echo -n` gives the cleaner value:
>
> ```console
> $ echo -n "username:password" | base64
> dXNlcm5hbWU6cGFzc3dvcmQ=
> ```
>
> Both decode back to the credentials, which is the real lesson.

Basic authentication has **no inherent security**. It depends entirely on other controls such as TLS. An attacker who captures traffic, performs a man-in-the-middle attack, socially engineers the user, or brute-forces credentials can get in. Because APIs are stateless, using Basic on *every* request multiplies the exposure, so it's common to use Basic once and then hand out a token or key for the rest.

> **⚠️ Caution**
> Never send Basic credentials over plain HTTP. Always TLS.

### API keys

An **API key** is a unique string the provider generates and gives to approved consumers. It can travel in the query string, a header, the body, or a cookie:

```text
/api/v1/users?apikey=ju574n3x4mpl34p1k3y         (query string)
"API-Secret": "17813fg8-46a7-5006-e235-45be7e9f2345"   (header)
Cookie: API-Key=4n07h3r4p1k3y                    (cookie)
```

I tested the query string and header placements against a small Flask app:

```python
from flask import Flask, request, jsonify
app = Flask(__name__)
KEYS = {"ju574n3x4mpl34p1k3y": "alice"}

@app.get("/api/v1/users")
def users():
    key = request.args.get("apikey") or request.headers.get("X-API-Key")
    if key not in KEYS:
        return jsonify(error="invalid api key"), 403
    return jsonify(user=KEYS[key])

c = app.test_client()
print(c.get("/api/v1/users?apikey=ju574n3x4mpl34p1k3y").get_json())
print(c.get("/api/v1/users", headers={"X-API-Key": "ju574n3x4mpl34p1k3y"}).get_json())
print(c.get("/api/v1/users?apikey=nope").status_code)
```

```console
{'user': 'alice'}
{'user': 'alice'}
403
```

Getting a key depends on the provider. NASA's API, for example, asks you to register with a name and email and then gives you a long random key that you pass as `?api_key=...` on each call.

**Why keys beat Basic auth:** when they're long, random, and complex they're hard to guess, and providers can expire them.

**Where they go wrong:**

- Keys generated from **predictable user data** can be guessed or forged.
- Keys get **committed to public repositories** or left in code comments.
- Keys in query strings end up in **logs and browser history**.
- Keys sent over unencrypted connections can be **intercepted**, or stolen through phishing.

> **⚠️ Caution**
> Prefer headers over query strings for secrets. URLs get logged by proxies, servers, and browsers.

### JSON Web Tokens (JWT)

A **JWT** is a token used for stateless, token-based authentication. The flow is:

```mermaid
sequenceDiagram
    autonumber
    participant U as Consumer
    participant A as API Provider

    rect rgb(237, 233, 254)
    U->>A: Authenticate with username + password
    A->>A: Build header + payload, sign with secret
    A-->>U: JWT
    end

    rect rgb(220, 252, 231)
    U->>A: GET /resource (Authorization: Bearer JWT)
    A->>A: Recompute signature and compare
    A-->>U: 200 OK
    end

    rect rgb(254, 226, 226)
    U->>A: GET /resource (tampered JWT)
    A->>A: Signature mismatch
    A-->>U: 401 Unauthorized
    end
```

A JWT has **three base64url-encoded parts separated by periods**:

| Part | Contains | Example (decoded) |
|---|---|---|
| **Header** | The signing algorithm and token type | `{"alg":"HS512","typ":"JWT"}` |
| **Payload** | The claims: username, timestamp, issuer, and so on | `{"sub":"1234567890","name":"hAPI Hacker","iat":1516239022}` |
| **Signature** | HMAC of header + payload using a secret | (binary, base64url-encoded) |

I implemented HS512 signing and verification using only Python's standard library, so there's no black box:

**`jwt_demo.py`**

```python
import base64, hashlib, hmac, json

def b64url(b: bytes) -> str:
    return base64.urlsafe_b64encode(b).rstrip(b"=").decode()

def b64url_decode(s: str) -> bytes:
    return base64.urlsafe_b64decode(s + "=" * (-len(s) % 4))

def sign(header, payload, secret):
    h = b64url(json.dumps(header, separators=(",", ":")).encode())
    p = b64url(json.dumps(payload, separators=(",", ":")).encode())
    sig = hmac.new(secret.encode(), f"{h}.{p}".encode(), hashlib.sha512).digest()
    return f"{h}.{p}.{b64url(sig)}"

def verify(token, secret):
    h, p, s = token.split(".")
    expected = hmac.new(secret.encode(), f"{h}.{p}".encode(), hashlib.sha512).digest()
    return hmac.compare_digest(expected, b64url_decode(s))

token = sign({"alg": "HS512", "typ": "JWT"},
             {"sub": "1234567890", "name": "hAPI Hacker", "iat": 1516239022},
             "SuperSecretPassword")
print("TOKEN:", token)
h, p, s = token.split(".")
print("HEADER :", b64url_decode(h).decode())
print("PAYLOAD:", b64url_decode(p).decode())
print("valid with right secret:", verify(token, "SuperSecretPassword"))
print("valid with wrong secret:", verify(token, "guess"))

# tamper with payload (signature left unchanged)
evil = json.loads(b64url_decode(p)); evil["name"] = "admin"
forged = f"{h}.{b64url(json.dumps(evil, separators=(',', ':')).encode())}.{s}"
print("tampered token accepted:", verify(forged, "SuperSecretPassword"))
```

```console
$ python3 jwt_demo.py
TOKEN: eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6ImhBUEkgSGFja2VyIiwiaWF0IjoxNTE2MjM5MDIyfQ.zsUjGDbBjqI-bJbaUmvUdKaGSEvROKfNjy9K6TckK55sd97AMdPDLxUZwsneff4O1ZWQikhgPm7HHlXYn4jm0Q
HEADER : {"alg":"HS512","typ":"JWT"}
PAYLOAD: {"sub":"1234567890","name":"hAPI Hacker","iat":1516239022}
valid with right secret: True
valid with wrong secret: False
tampered token accepted: False
```

The header and payload of my token match the example token printed in the source chapter. The signature differs by a single character because it uses the URL-safe alphabet (`-`) rather than `+`.

The last line is the whole point of the signature: when I altered the payload and kept the old signature, a correct verifier **rejected** it.

> **🔥 Danger**
> **A JWT payload is encoded, not encrypted.** Anyone holding the token can read it, as my script did with `b64url_decode`. Never put passwords, card numbers, or other secrets in the payload.

JWTs are generally secure but easy to implement badly. Common failure modes are:

- Accepting tokens that aren't properly signed or verified, which leaves you one base64 decode away from exposing and altering the contents.
- A weak, guessable, or leaked **secret key** that can be brute-forced.
- Never **expiring** tokens.

A safe verifier should **pin the algorithm it expects** (never trust the `alg` field the token itself claims), check the signature, and check expiry.

### HMAC

**HMAC** (hash-based message authentication code) is the primary method used by AWS. The provider creates a secret key and shares it with the consumer. For each request the consumer hashes the request data with that key and sends the resulting digest along. The provider repeats the computation and compares. A match proves two things at once: the sender knows the key, and the message wasn't modified in transit.

```mermaid
flowchart LR
    subgraph Consumer
        M1["Request data"] --> H1["HMAC function<br/>+ secret key"]
        H1 --> D1["Digest"]
    end
    D1 -->|"sent with request"| CMP
    subgraph Provider
        M2["Received request data"] --> H2["HMAC function<br/>+ same secret key"]
        H2 --> D2["Recomputed digest"]
        D2 --> CMP{"Digests<br/>equal?"}
    end
    CMP -->|"Yes"| OK["✅ Authorized"]
    CMP -->|"No"| NO["❌ Wrong key or<br/>tampered message"]

    style M1 fill:#dbeafe,stroke:#1d4ed8
    style H1 fill:#dbeafe,stroke:#1d4ed8
    style D1 fill:#bfdbfe,stroke:#1d4ed8,stroke-width:2px
    style M2 fill:#ede9fe,stroke:#6d28d9
    style H2 fill:#ede9fe,stroke:#6d28d9
    style D2 fill:#ddd6fe,stroke:#6d28d9,stroke-width:2px
    style CMP fill:#fef3c7,stroke:#d97706,stroke-width:2px
    style OK fill:#bbf7d0,stroke:#15803d,stroke-width:2px
    style NO fill:#fecaca,stroke:#b91c1c,stroke-width:2px
```

Here's a live comparison of the four common algorithms on the same message and key:

**`hmac_demo.py`**

```python
import hashlib, hmac
key, msg = b"s3cr3t-key", b"GET /api/v3/inventory/item/pillow"
for name in ("md5", "sha1", "sha256", "sha512"):
    d = hmac.new(key, msg, name).hexdigest()
    print(f"HMAC-{name.upper():<7} {len(d)//2*8:>3} bits  {d[:32]}...")
sig = hmac.new(key, msg, hashlib.sha256).hexdigest()
print("server check (same msg)  :", hmac.compare_digest(sig, hmac.new(key, msg, hashlib.sha256).hexdigest()))
print("server check (tampered)  :", hmac.compare_digest(sig, hmac.new(key, msg + b"?admin=1", hashlib.sha256).hexdigest()))
```

```console
$ python3 hmac_demo.py
HMAC-MD5     128 bits  f3bc9cc003ff6d00a558397fc73ebd6d...
HMAC-SHA1    160 bits  0416feb30a24e5606fe0fa847b95bdb2...
HMAC-SHA256  256 bits  5ee9e59cd786af03acd462c956594f68...
HMAC-SHA512  512 bits  0f1e735c111b799a8a4883f0df53024d...
server check (same msg)  : True
server check (tampered)  : False
```

| Algorithm | Output size | My take |
|---|---|---|
| HMAC-MD5 | 128 bits | Fast, weakest of the four |
| HMAC-SHA1 | 160 bits | Fast, no known practical HMAC attacks, but dated |
| HMAC-SHA256 | 256 bits | The sensible default |
| HMAC-SHA512 | 512 bits | Strongest, a bit slower |

Stronger hash functions produce longer digests, and they're also slower. Choosing one is a trade-off between performance and security. As with every method here, **the whole scheme collapses if the secret key leaks**: an attacker holding it can impersonate you.

> **💡 Tip**
> Use `hmac.compare_digest` (as I did) instead of `==` when comparing digests. It runs in constant time and avoids timing side channels.

### OAuth 2.0

**OAuth 2.0** is an *authorization* standard that lets services access each other's data without sharing passwords. The classic example: you want LinkedIn to share your tweets.

- You are the **resource owner**.
- LinkedIn is the **client** (the application).
- Twitter is the **authorization server** and **resource server**.

```mermaid
sequenceDiagram
    autonumber
    actor O as Resource owner (you)
    participant C as Client (LinkedIn)
    participant A as Authorization server (Twitter)
    participant R as Resource server (Twitter API)

    O->>C: "Share my tweets here"
    C->>O: Redirect to Twitter's authorization page
    rect rgb(254, 249, 195)
    O->>A: Log in and approve access
    A-->>C: Limited, time-based access token
    end
    rect rgb(220, 252, 231)
    C->>R: Request + access token
    R-->>C: Your tweets
    end
    Note over O,C: LinkedIn never saw your Twitter password
```

You never hand LinkedIn your Twitter credentials. Instead, Twitter issues a **limited, time-based token** that LinkedIn presents to read or post on your behalf.

OAuth is among the most trusted ways to handle authorization, but it also **widens the attack surface**. Problems usually come from how a provider *implements* it, not from OAuth itself. Poor implementations are exposed to token injection, authorization-code reuse, cross-site request forgery, invalid redirection, and phishing.

> **⚠️ Caution**
> Always validate the `redirect_uri` against an exact allow-list, and use the `state` parameter to defend against CSRF.

### No authentication

It's perfectly valid for an API to have none. If it serves only public information (a public weather feed, for example), requiring credentials adds friction without benefit.

### Authentication methods compared

| Method | Credential | Stateless friendly | Main strength | Main weakness |
|---|---|---|---|---|
| **None** | Nothing | ✅ | Zero friction | No identity or control |
| **Basic** | Username + password (base64) | ✅ but sends the password every time | Dead simple | No built-in protection; relies on TLS |
| **API key** | Random string | ✅ | Easy, revocable, expirable | Leaks via repos, logs, URLs; guessable if predictable |
| **JWT** | Signed token | ✅ | Self-contained claims | Bad implementations, weak secrets, readable payload |
| **HMAC** | Signed digest of the request | ✅ | Proves integrity *and* possession of key | Key compromise = impersonation |
| **OAuth 2.0** | Delegated access token | ✅ | No password sharing, limited scope and lifetime | Complex; implementation flaws |

---

## Putting it together: what happens when you search on Twitter

Now I can walk through a real-world interaction using every concept above. (This walkthrough describes the Twitter API as presented in the source chapter; the platform has changed a lot since then, so treat it as an illustration of the *patterns* rather than current behavior.)

**Step 1: loading the page.** Typing the site address makes the browser send a plain HTTP request:

```http
GET / HTTP/1.1
Host: twitter.com
User-Agent: Mozilla/5.0
Accept: text/html
Cookie: [...]
```

The server replies `200 OK` with headers such as `cache-control`, `content-security-policy`, `x-powered-by: Express`, and `x-response-time: 56`, followed by the HTML the browser needs to draw the page. That's ordinary web traffic, not the API yet.

**Step 2: searching for "hacking".** The search kicks off a request to a *different host*, the API:

```http
POST /1.1/jot/client_event.json?q=hacking HTTP/1.1
Host: api.twitter.com
User-Agent: Mozilla/5.0
Authorization: Bearer AAAAAAAAAAAAAAAAA...
```

**Step 3: JSON comes back.** The response is JSON describing tweets, with fields like these:

```json
{
  "created_at": "[...]",
  "id": 1278533978970976256,
  "id_str": "1278533978970976256",
  "full_text": "1984: William Gibson published his debut novel...",
  "truncated": false
}
```

**Step 4: the browser renders it.** The browser turns that JSON into the search results you see, complete with images, likes, and retweet counts.

```mermaid
sequenceDiagram
    autonumber
    actor U as User
    participant B as Browser (GUI)
    participant W as twitter.com (web server)
    participant API as api.twitter.com (API)

    rect rgb(219, 234, 254)
    U->>B: Type the site URL
    B->>W: GET / (Accept: text/html)
    W-->>B: 200 OK + HTML page
    end

    rect rgb(237, 233, 254)
    U->>B: Search for "hacking"
    B->>API: POST /1.1/jot/client_event.json?q=hacking<br/>Authorization: Bearer ...
    API-->>B: 200 OK + JSON (tweets, hashtags, mentions)
    end

    rect rgb(220, 252, 231)
    B->>U: Render results in the GUI
    end
```

How can I tell this API is RESTful? By its **fingerprints**:

| Clue | What I observed |
|---|---|
| Resource-style, versioned paths | `/1.1/...` |
| Token-based authorization | `Authorization: Bearer ...` |
| Form-style encoding | `application/x-www-form-urlencoded` |
| JSON data interchange | Tweets returned as JSON |
| HTTP methods and status codes | CRUD-style use of HTTP |

From the user's seat the whole thing feels seamless: click, type, results. But there were at least two distinct conversations underneath, and the second one is an API talking JSON to a browser.

To observe this yourself, route your browser through an intercepting proxy such as **Burp Suite**, which lets you watch every request and response in real time.

> **⚠️ Caution**
> Only intercept traffic from your own browser and from services whose terms allow it. Intercepting other people's traffic is illegal in most places.

---

## My cheat sheet

### Quick identification guide

When I land on an unknown API, I run through this table:

| If I see... | It's probably... |
|---|---|
| Many URLs like `/api/v1/users/{id}` and verbs `GET/POST/PUT/DELETE` | REST |
| One URL (`/graphql`) with `POST` and a body starting with `query` or `mutation` | GraphQL |
| XML with `soap:Envelope`, `Content-Type: application/soap+xml` | SOAP |
| `/swagger.json`, `/openapi.json`, `/api-docs` | An OpenAPI spec is published |
| Three dot-separated base64 chunks starting `eyJ` | A JWT |
| `Authorization: Basic ...` | Base64 credentials; decode to confirm |
| `X-API-Key`, `?apikey=` | API key auth |
| `Authorization: AWS4-HMAC-SHA256 ...` | AWS-style HMAC signing |

### Defensive checklist for API builders

- [ ] Put a **gateway** in front for authentication, rate limiting, and TLS.
- [ ] Use **HTTPS everywhere**; never send credentials over plain HTTP.
- [ ] Return only the fields consumers need; don't over-share.
- [ ] Remove **version-leaking headers** like `X-Powered-By`.
- [ ] Use **long random API keys**, rotate them, and let them **expire**.
- [ ] Keep secrets out of **repos, URLs, and logs**.
- [ ] **Pin and verify** the JWT algorithm; set short expirations; never put secrets in the payload.
- [ ] Use **constant-time comparison** for digests and tokens.
- [ ] Treat OAuth redirect URIs as an **exact-match allow-list**.
- [ ] Review your **public documentation** for information you didn't mean to publish.
- [ ] Decide deliberately whether GraphQL **introspection** should be available in production.
- [ ] Use `yaml.safe_load` for untrusted YAML.

### Glossary

| Term | Definition |
|---|---|
| **API** | An interface that lets applications use each other's data and functionality |
| **Endpoint** | A URL for interacting with part of an API |
| **Resource** | The data being requested |
| **API gateway** | Entry-point component handling routing, security, and traffic |
| **Microservice** | A small, modular piece of an app handling one function |
| **API contract** | Documentation of how the API behaves |
| **CRUD** | Create, Read, Update, Delete |
| **REST** | Architectural style built on HTTP and six constraints |
| **GraphQL** | Query-centric API specification where clients choose the fields |
| **SOAP** | Older XML-based, action-oriented API format |
| **OAS / RAML** | Specifications for describing REST APIs |
| **JWT** | Signed token with header, payload, and signature |
| **HMAC** | Keyed hash used to authenticate and integrity-check a message |
| **OAuth 2.0** | Authorization framework for delegated access |
| **Bearer token** | A token whose possession alone grants access |

---

## Closing thoughts

If I had to compress this whole post into a few sentences, they would be these.

A web application is a GUI sitting on top of APIs. APIs are reached through **endpoints**, guarded by a **gateway**, and powered by **microservices**, and their behavior is described in an **API contract**. The two types worth knowing best are **REST**, a flexible style built on HTTP, and **GraphQL**, a query-centric alternative that lets clients pick their fields. They trade data in **JSON, XML, or YAML**, and they prove identity with **Basic auth, API keys, JWTs, HMAC, or OAuth**, each with its own trade-offs.

The deepest lesson I took away is that **almost every weakness lives in implementation, not in the standard**. Base64 isn't encryption, a signed token still has a readable payload, and OAuth is only as safe as its redirect handling. Once you can see those building blocks clearly, reading any API's traffic becomes a lot less mysterious.

My suggestion: spin up the little Flask server from this post, break it on purpose, and watch the status codes change. Then point an intercepting proxy at an API you're allowed to test and see how many of the patterns you can name on sight.

Happy building, and happy (authorized) testing.

---

### Appendix: how I tested

| Item | Version / detail |
|---|---|
| Python | 3.12.3 |
| Flask | 3.1.3 |
| requests | 2.33.1 |
| PyYAML | 6.0.3 |
| graphql-core | installed via `pip` |
| HTTP testing | `curl` against `localhost:5055` |

To reproduce:

```console
$ pip install flask requests pyyaml graphql-core
$ python3 rest_shop.py &            # starts the toy REST API on port 5055
$ python3 client.py                 # requests-based client
$ python3 graphql_shop.py           # GraphQL demo
$ python3 jwt_demo.py               # JWT sign/verify demo
$ python3 hmac_demo.py              # HMAC comparison
$ python3 formats_demo.py           # JSON vs XML vs YAML
```
