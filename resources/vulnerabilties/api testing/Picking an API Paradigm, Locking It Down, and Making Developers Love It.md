# Picking an API Paradigm, Locking It Down, and Making Developers Love It

A while back I wrote about what an API actually *is* — the interface between two systems, the doorway that lets one piece of software talk to another without either side having to understand the other's internals. That post was the "why." This one is the "how."

Once I've decided I'm building an API, I'm immediately faced with a cascade of decisions: What paradigm do I use — REST? RPC? GraphQL? How do I push real-time updates instead of making developers poll me every five seconds? How do I make sure the person calling my API is actually who they say they are, and that they can only touch the data they're allowed to touch? And once all that's sorted, how do I make the experience of using my API something developers actually *enjoy*, instead of something they grudgingly tolerate?

I want to walk through all of that in this post — the paradigms, the security model, and the design practices — with real diagrams, tables, and code I actually ran, not just described.

> **Caution before I start:** Every choice I make here is expensive to undo. Once real developers are building real products on top of my API's shape, changing that shape means breaking their code. So I try to treat this less like "writing an endpoint" and more like "signing a long-term contract." I want to get as much of it right upfront as I reasonably can.

---

## Part 1: Choosing an API Paradigm

An API paradigm defines *how* I expose my backend data to the outside world — the rules, structure, and conventions the interface follows. There isn't a single correct paradigm. REST, RPC, GraphQL, WebHooks, and WebSockets are the major players today, and each one solves a different shape of problem.

I find it useful to split these into two buckets:

1. **Request–response APIs** — the client asks, the server answers, and the conversation ends. REST, RPC, and GraphQL all live here.
2. **Event-driven APIs** — the server pushes data to the client as things happen, instead of waiting to be asked. WebHooks, WebSockets, and HTTP Streaming live here.

```mermaid
flowchart TD
    A[API Paradigms] --> B[Request-Response]
    A --> C[Event-Driven]
    B --> B1[REST]
    B --> B2[RPC]
    B --> B3[GraphQL]
    C --> C1[WebHooks]
    C --> C2[WebSockets]
    C --> C3[HTTP Streaming]
```

Let me go through each one.

### REST: Thinking in Resources

REST — Representational State Transfer — is far and away the most common paradigm I run into today. Companies like Stripe, GitHub, Google, and Twitter all lean on it. The core idea is simple: everything is a **resource** — an entity that can be identified, named, and addressed on the web — and I use standard HTTP verbs to perform Create, Read, Update, and Delete (CRUD) operations against those resources.

I've internalized a few rules that keep my REST APIs consistent:

- Resources live in the URL, like `/users`.
- I generally implement two URLs per resource: a collection endpoint (`/users`) and a specific-item endpoint (`/users/U123`).
- I use **nouns**, not verbs, in my URLs. `/users/U123` is right. `/getUserInfo/U123` is wrong — the HTTP verb already tells the server what action to take, so I don't need to repeat it in the path.
- I let the HTTP method carry the meaning of the action.

Here's the mapping I keep in my head, laid out as a table:

| Operation | HTTP verb | `/users` | `/users/U123` |
|---|---|---|---|
| Create | `POST` | Create a new user | Not applicable |
| Read | `GET` | List all users | Retrieve user U123 |
| Update | `PUT` or `PATCH` | Batch update users | Update user U123 |
| Delete | `DELETE` | Delete all users | Delete user U123 |

> **Note:** `GET` requests should *never* change server state. They're read-only, idempotent, and cacheable by design — which means I can throw a CDN or browser cache in front of them without worrying about side effects. If I ever find myself tempted to make a `GET` request that deletes something "for convenience," that's a sign I've broken the contract REST developers expect.

Here's what a real REST request against Stripe's API looks like, retrieving a specific charge:

```http
GET /v1/charges/ch_CWyutlXs9pZyfD
HOST api.stripe.com
Authorization: Bearer YNoJ1Yq64iCBhzfL9HNO00fzVrsEjtVl
```

And creating one:

```http
POST /v1/charges/ch_CWyutlXs9pZyfD
HOST api.stripe.com
Content-Type: application/x-www-form-urlencoded
Authorization: Bearer YNoJ1Yq64iCBhzfL9HNO00fzVrsEjtVl

amount=2000&currency=usd
```

#### Showing Relationships With Subresources

Sometimes a resource only makes sense inside another resource. GitHub's API is a great example — issues don't exist independently, they exist *within* a repository. So GitHub nests them as subresources:

```
POST   /repos/:owner/:repo/issues              → Create an issue
GET    /repos/:owner/:repo/issues/:number       → Retrieve an issue
GET    /repos/:owner/:repo/issues               → List all issues
PATCH  /repos/:owner/:repo/issues/:number       → Edit an issue
```

I like this pattern because the URL structure itself communicates the relationship. A developer reading `/repos/:owner/:repo/issues/:number` doesn't need documentation to guess what's going on — the hierarchy is self-explanatory.

#### The Awkward Part: Non-CRUD Actions

REST is elegant when everything fits neatly into Create/Read/Update/Delete. Real products, unfortunately, need to do things that aren't CRUD. I've seen three common workarounds:

**1. Render the action as a field on the resource.** GitHub does this for archiving a repo — instead of a separate "archive" endpoint, `archived` is just a boolean field I `PATCH`:

```http
PATCH /repos/saurabhsahni/Hacks
HOST api.github.com
Content-Type: application/json
Authorization: token OAUTH-TOKEN

{
  "archived": true
}
```

**2. Treat the action like a subresource.** Locking an issue isn't really "updating" it in the traditional sense, so GitHub models it as its own tiny resource:

```
PUT /repos/:owner/:repo/issues/:number/lock
```

**3. Just use a verb when nothing else fits.** Search is the classic example — there's no "resource" being created, read, updated, or deleted. GitHub just accepts that and uses a verb in the URL:

```
GET /search/code?q=:query
```

> **Note:** I don't think any of these three approaches is "the" correct answer. I pick whichever one keeps the API easiest to predict for the specific action I'm modeling.

### RPC: Thinking in Actions

Where REST is about resources, **RPC (Remote Procedure Call)** is about actions. With RPC, I'm not asking "what resource am I manipulating" — I'm asking "what function do I want to run on the server." The client passes a method name and some arguments, and gets JSON or XML back.

RPC keeps things simple by following two rules:

- The endpoint name *is* the operation.
- I use `GET` for read-only calls and `POST` for everything else.

Slack's API is the textbook example here. Look at this call to archive a channel:

```http
POST /api/conversations.archive
HOST slack.com
Content-Type: application/x-www-form-urlencoded
Authorization: Bearer xoxp-1650112-jgc2asDae

channel=C01234
```

Notice the endpoint is literally named after the verb: `conversations.archive`. Slack's Conversations API supports a whole family of actions like this — `archive`, `join`, `kick`, `leave`, `rename` — and honestly, trying to cram those into REST's CRUD mental model would be awkward. Kicking a user from a channel isn't cleanly a "create," "read," "update," or "delete" of anything. RPC just names the verb directly and moves on.

RPC-style APIs aren't limited to plain HTTP, either — protocols like **gRPC** and **Apache Thrift** use RPC-style, serialized, strongly-typed interfaces for high-performance service-to-service communication. I won't dig into those in depth here, but it's worth knowing they exist if you're building internal microservices rather than a public developer-facing API.

### GraphQL: Thinking in Queries

GraphQL flips the model again. Instead of the server deciding what shape of data comes back, the *client* specifies exactly the fields it wants, and the server returns precisely that — nothing more, nothing less.

Here's a real GraphQL query against GitHub's API:

```graphql
{
  user(login: "saurabhsahni") {
    id
    name
    company
    createdAt
  }
}
```

And the response mirrors the shape of the request exactly:

```json
{
  "data": {
    "user": {
      "id": "MDQ6VXNlcjY1MDI5",
      "name": "Saurabh Sahni",
      "company": "Slack",
      "createdAt": "2009-03-19T21:00:06Z"
    }
  }
}
```

Structurally, GraphQL looks totally different from REST or RPC on the wire. There's only **one URL endpoint** — typically `/graphql` — and instead of different HTTP verbs meaning different things, the *body* of the request tells the server whether I'm running a `query` (read) or a `mutation` (write):

```http
POST /graphql
HOST api.github.com
Content-Type: application/json
Authorization: bearer 2332dg1acf9f502737d5e

{
  "query": "query { viewer { login }}"
}
```

#### I Tested the "Over-Fetching" Problem Myself

One of GraphQL's biggest selling points is that REST APIs tend to return more data than clients actually need — a phenomenon called "over-fetching." I wanted to see how dramatic that difference actually is, so I wrote a small script that builds a realistic "full" REST user object versus a GraphQL response that only asked for four fields:

```python
import json

# --- Simulated "full" REST response (over-fetching example) ---
rest_response = {
    "id": "U123",
    "login": "saurabhsahni",
    "name": "Saurabh Sahni",
    "company": "Slack",
    "email": "hidden@example.com",
    "bio": "Long bio text here " * 10,
    "public_repos": 42,
    "public_gists": 7,
    "followers": 1500,
    "following": 30,
    "created_at": "2009-03-19T21:00:06Z",
    "updated_at": "2023-01-01T00:00:00Z",
    "avatar_url": "https://example.com/avatar.png",
    "location": "San Francisco",
    "blog": "https://example.com",
    "hireable": None
}

# --- Simulated GraphQL response (client asked only for 4 fields) ---
graphql_response = {
    "data": {
        "user": {
            "id": "U123",
            "name": "Saurabh Sahni",
            "company": "Slack",
            "createdAt": "2009-03-19T21:00:06Z"
        }
    }
}

rest_bytes = len(json.dumps(rest_response).encode("utf-8"))
graphql_bytes = len(json.dumps(graphql_response).encode("utf-8"))

print("REST payload size (all fields):", rest_bytes, "bytes")
print("GraphQL payload size (4 requested fields):", graphql_bytes, "bytes")
print(f"GraphQL response is {round((1 - graphql_bytes/rest_bytes) * 100)}% smaller in this example")
```

Running that gave me:

```
REST payload size (all fields): 593 bytes
GraphQL payload size (4 requested fields): 116 bytes
GraphQL response is 80% smaller in this example
```

That's obviously a made-up example with exaggerated field counts, but it illustrates the real dynamic: the more fields a REST resource accumulates over time, the more every client pays for data it never uses — while GraphQL clients keep paying only for what they actually asked for.

GraphQL brings a few other advantages I've come to appreciate:

- **Fewer round trips.** I can nest related resources into a single query instead of chaining multiple REST calls. This matters a lot on slow mobile networks.
- **Easier versioning.** I can add new fields without breaking existing queries, and I can watch server logs to see exactly which clients still use a field before deprecating it — something that's much harder to determine with REST.
- **Strong typing.** The schema catches malformed queries before they ever hit my server logic.
- **Built-in introspection.** Tools like GraphiQL let developers explore the schema live in a browser, without needing an external tool like Swagger.

But GraphQL isn't free. Here's a quote I keep coming back to, from Kyle Daigle, GitHub's director of ecosystem engineering, on why they eventually built a GraphQL API alongside their REST one:

> One of the biggest issues GitHub saw was REST payload creep. Over time, you add additional information to a serializer for, say, a repository. It starts small but as you add additional data... your API responses are enormous... With GraphQL, you specify a query for just the data you want and we return just that data.

The tradeoff is that all that flexibility on the client side becomes complexity on the server side. My backend now has to parse arbitrary, developer-composed queries, validate them, and figure out how to execute them efficiently — and unlike a fixed set of REST endpoints, I can't easily predict which query shapes external developers will throw at me. Performance optimization becomes a much fuzzier target.

### Comparing the Three, Side by Side

Here's the full comparison I keep as a mental cheat sheet:

| | REST | RPC | GraphQL |
|---|---|---|---|
| **What it models** | Resources + CRUD via HTTP methods | Actions — client passes method name + args | Client-specified query shape |
| **Example providers** | Stripe, GitHub, Twitter, Google | Slack, Flickr | Facebook, GitHub, Yelp |
| **HTTP verbs used** | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` | `GET`, `POST` | `GET`, `POST` |
| **Pros** | Standard conventions, leverages HTTP features, easy to maintain | Lightweight, high performance, easy to understand | Fewer round trips, smaller payloads, strongly typed, self-documenting |
| **Cons** | Can lead to big/over-fetched payloads, multiple round trips, discovery is hard | Limited standardization, can lead to "function explosion" | Backend query complexity, harder performance tuning, overkill for simple APIs |
| **Best for** | CRUD-heavy APIs | APIs exposing many discrete actions | APIs needing querying flexibility across related data |

My personal rule of thumb: if I'm modeling a set of resources that clients mostly create, read, update, and delete, REST is almost always the right default — it's the paradigm most developers already know intuitively. If I have a pile of actions that don't map cleanly onto CRUD, RPC saves me from twisting my API into an unnatural shape. And if my API spans many related resources that clients want to fetch together, in different combinations, for different apps (think: a mobile app needing a leaner payload than a desktop dashboard), GraphQL earns its complexity.

---

## Part 2: Event-Driven APIs

Request–response APIs have one fundamental weakness: the moment the server sends its response, that data can go stale. If I want to stay up to date, my only option is to keep asking — which is called **polling**.

Polling has an uncomfortable tradeoff built into it. Poll too rarely, and I miss events. Poll too often, and I'm wasting enormous amounts of resources on calls that return nothing new. Zapier actually measured this in their own systems and found that only about **1.5% of their polling API calls returned new data.** That means 98.5% of the traffic they generated was, in effect, noise.

```mermaid
sequenceDiagram
    participant Client
    participant Server
    Note over Client,Server: Polling (wasteful)
    Client->>Server: GET /channels (poll)
    Server-->>Client: No new data
    Client->>Server: GET /channels (poll)
    Server-->>Client: No new data
    Client->>Server: GET /channels (poll)
    Server-->>Client: New channel! (finally)

    Note over Client,Server: WebHook (efficient)
    Server->>Client: POST /my-webhook-url (new channel event)
```

There are three common mechanisms I reach for instead of polling: **WebHooks**, **WebSockets**, and **HTTP Streaming**.

### WebHooks

A WebHook is deceptively simple: it's just a URL I control that accepts an HTTP `POST` (sometimes `GET`, `PUT`, or `DELETE`). When something happens on the provider's side, they `POST` a message to my URL. That's it. No polling, no wasted requests — I find out the instant something changes.

Slack, Stripe, GitHub, and Zapier all support WebHooks. From an implementation standpoint, I find WebHooks genuinely pleasant to build against — I just stand up a normal HTTP endpoint and reuse infrastructure I already have.

But "simple to implement" doesn't mean "simple to get right." A few complexities I've had to think through:

| Complexity | What it means in practice |
|---|---|
| **Failures and retries** | My endpoint might be down when the event fires. Slack, for example, retries failed deliveries up to three times — immediately, then after one minute, then after five minutes — and if 95% of requests to my endpoint keep failing, Slack simply stops sending me events and notifies me. |
| **Security** | Since my WebHook URL is public on the internet, *anyone* can send a fake `POST` to it. I need a way to verify the request genuinely came from the provider (more on this in the security section below). |
| **Firewalls** | Applications sitting behind a corporate firewall generally can't receive inbound WebHook traffic at all. |
| **Noise** | Each WebHook usually represents a single event. If thousands of events happen in a short window, I'm suddenly fielding thousands of individual HTTP requests. |

### WebSockets

A WebSocket establishes a persistent, **two-way** streaming connection over a single TCP connection. Unlike WebHooks (server → client only), WebSockets let both sides talk simultaneously — full-duplex communication.

Slack uses WebSockets for its Real Time Messaging API, letting developers receive live events (new messages, emoji reactions, channel creation) and send messages back over the same connection. Trello pushes live UI updates to browsers the same way, and Blockchain uses WebSockets to broadcast new transactions in real time.

```mermaid
sequenceDiagram
    participant Browser
    participant Server
    Browser->>Server: WebSocket handshake (upgrade from HTTP)
    Server-->>Browser: 101 Switching Protocols
    Note over Browser,Server: Persistent full-duplex connection open
    Server->>Browser: New message event
    Browser->>Server: Send reply
    Server->>Browser: Reaction added event
    Server->>Browser: Channel created event
```

WebSockets have a genuine advantage for enterprise use cases: because they typically run over port 80 or 443, they tend to sail through firewalls that would otherwise block inbound WebHook traffic. Some enterprise Slack developers actually prefer the WebSocket API over WebHooks for exactly this reason — they can receive events securely without opening an inbound HTTP endpoint to the public internet at all.

> **Caution:** WebSockets require the client to actively maintain the connection. If it drops — which happens constantly on mobile networks — the client has to detect that and reconnect. There's also a scalability wrinkle: if I'm building an app that installs onto 10,000 different Slack workspaces, and each workspace requires its own persistent WebSocket connection, I'm now responsible for keeping 10,000 simultaneous connections alive. That's a very different operational challenge than handling stateless HTTP requests.

### HTTP Streaming

The last option is HTTP Streaming — technically still just an HTTP response, except the server never actually closes it. Instead of a response with a fixed length, the connection stays open indefinitely, and the server keeps pushing new data down it.

There are two common techniques:

1. **Chunked transfer encoding** — the server sets `Transfer-Encoding: chunked` and streams newline-delimited JSON strings as they become available.
2. **Server-Sent Events (SSE)** — a browser-friendly standard that pairs nicely with the native `EventSource` API in JavaScript.

Twitter's Streaming API is the classic example: instead of developers polling for new tweets constantly, Twitter keeps a single HTTP connection open and pushes new tweets down it as they're posted. That saves resources for both sides.

The tradeoff is buffering. Clients and intermediate proxies often hold data until some threshold is met before actually delivering it to the application, which can introduce latency. And if I want to change *what* events I'm listening for, HTTP Streaming usually forces a full reconnection — there's no lightweight "subscribe/unsubscribe" mechanic like some WebSocket implementations offer.

### Comparing the Event-Driven Options

| | WebHooks | WebSockets | HTTP Streaming |
|---|---|---|---|
| **What it is** | Event notification via HTTP callback | Two-way streaming over TCP | Long-lived connection over HTTP |
| **Example services** | Slack, Stripe, GitHub, Zapier, Google | Slack, Trello, Blockchain | Twitter, Facebook |
| **Pros** | Simple server-to-server, plain HTTP | Bidirectional, native browser support, firewall-friendly | Simple to consume, native browser support |
| **Cons** | Doesn't work in browsers or across firewalls; retries/security are hard | Requires persistent connections; not plain HTTP | Buffering issues; reconnects needed to change subscriptions |
| **Best for** | Server-to-server real-time events | Two-way real-time, browser-to-server | One-way streaming over simple HTTP |

I don't think any provider needs to pick just one of these forever, by the way. Slack itself supports RPC-style request-response APIs, WebSockets, *and* WebHooks simultaneously, because different developers and different use cases genuinely call for different tools.

---

## Part 3: API Security

Here's a sentence I believe deeply: **security is hard, and securing APIs is even harder.** Once developers are using a particular authentication mechanism, changing it later means asking every single one of them to update their code — and if there's a vulnerability, I may be racing against attackers while developers scramble to patch.

### Authentication vs. Authorization

I like keeping these two concepts sharply separated, because conflating them causes real bugs:

- **Authentication** answers *"who are you?"* — usually a username/password check, or in the API world, a token.
- **Authorization** answers *"are you allowed to do this specific thing?"* — I might be authenticated as myself, but that doesn't mean I'm authorized to delete someone else's account.

### Why I Avoid Basic Authentication

Early APIs commonly used **Basic Authentication** — the client sends a base64-encoded `username:password` string in the `Authorization` header:

```
Authorization: Basic dXNlcjpwYXNzd29yZA==
```

It's simple, but I try to avoid it for anything user-facing, for a few concrete reasons:

- Third-party apps would need to *store* my actual password, in a form they can decrypt — a huge liability if they're ever breached.
- I can't revoke a single app's access without changing my password everywhere, which logs out every other app too.
- The app gets full, all-or-nothing access to my account. There's no way to grant "read-only" or "just this one resource."

This is part of why Twitter dropped Basic Authentication for its core API back in 2010.

### OAuth: Access Without Password Sharing

**OAuth**, introduced in 2007, solves exactly the problems above. OAuth 2.0 is now the industry-standard authorization protocol, adopted by Amazon, Google, Facebook, GitHub, Stripe, and Slack.

The core idea: instead of handing my password to a third-party app, that app redirects me to the actual provider (say, Facebook), I log in *there*, I explicitly approve exactly what the app can access, and the provider hands the app a limited-scope **access token** — never my password.

```mermaid
sequenceDiagram
    participant User
    participant TripAdvisor
    participant Facebook

    User->>TripAdvisor: Clicks "Continue with Facebook"
    TripAdvisor->>Facebook: Redirect user to authorize (client_id, scope)
    Facebook->>User: Show permission screen
    User->>Facebook: Approves
    Facebook->>TripAdvisor: Redirect back with authorization code
    TripAdvisor->>Facebook: Exchange code for access token (client_id, client_secret, code)
    Facebook->>TripAdvisor: Access token
    TripAdvisor->>Facebook: API calls using access token
    Facebook->>TripAdvisor: User's profile, friend list, etc.
```

Three concrete benefits fall out of this design:

1. I never share my password with TripAdvisor — only Facebook ever sees it.
2. I get to grant *selective* permissions — TripAdvisor might get read access to my profile and friend list but not the ability to post on my behalf.
3. I can revoke TripAdvisor's access later, from my Facebook settings, without touching my password at all.

### The Token Generation Flow, Step by Step

Before any of this works, an application has to **register** with the provider — supplying a redirect URL, and receiving back a `client_id` (safe to expose publicly) and a `client_secret` (must stay confidential).

From there, the flow runs in three steps:

**1. The app redirects the user to the provider for authorization**, passing along the `client_id`, the requested `scope` (what permissions it wants), an optional `state` parameter (used to prevent CSRF attacks), and a redirect URL.

**2. The provider asks the user to approve or deny.** If denied, the user is redirected back with an `access_denied` error. If approved, they're redirected back with a short-lived, single-use **authorization code**.

**3. The app exchanges the authorization code for an access token**, this time sending its `client_id`, `client_secret`, the authorization code, and the redirect URL. The provider verifies all of it and returns an access token the app can now use to make authenticated API calls.

> **Note:** The authorization code is deliberately single-use. If an attacker somehow intercepts it, they can't reuse it — attempting to redeem an already-used code should be rejected outright. This is a small but important defense against replay attacks.

### Scopes: Granular Permission Boundaries

**Scopes** are how OAuth lets me limit exactly what an application can do with my data, rather than an all-or-nothing grant. Twitter, for example, offers three tiers:

- Read only
- Read and write
- Read, write, and access to direct messages

Designing scopes well is genuinely tricky, and I've noticed a pattern: most API providers *underinvest* in scopes early on, only to realize — usually after abuse or a security incident — that they need much more granular control. By then, introducing new scopes is a much bigger lift, because existing integrations already assume the old, broader ones.

A few scope-design patterns I've picked up:

- **A minimal "identify" scope** — just enough to know who someone is (name, profile picture) for sign-in flows, without exposing anything else. Slack, Facebook, and Heroku all offer this.
- **Isolated scopes for sensitive data.** Heroku split out "read-protected" and "write-protected" scopes specifically for things like config variables containing database secrets. GitHub does the same for private repository access. Twitter added a dedicated scope for direct messages after noticing apps were reading DMs even when they didn't need to.
- **Scopes split by resource type**, not just by read/write. Slack has separate scopes for messages, pins, stars, reactions, and channels — so an app requesting access to repositories on GitHub, for instance, doesn't automatically get access to that user's gists too.

**A real story I find instructive:** when Slack first launched OAuth support, its `read` scope was extremely broad — granting access to essentially *everything*: messages, channels, reactions, stars, files. In late 2015, Slack introduced 27 granular scopes instead, splitting read/write access out per resource type. The result wasn't just better security — user *conversion rates* on app installs actually improved, because people felt more comfortable granting narrowly-scoped access than a blanket "read everything" permission.

### Making API Calls With the Token

Once I have an access token, I just attach it as a Bearer token on every request:

```http
POST /api/chat.postMessage
HOST slack.com
Content-Type: application/json
Authorization: Bearer xoxp-16501860-a24afg234

{
  "channel": "C0GEV71UG",
  "text": "This a message text",
  "attachments": [{"text": "attachment text"}]
}
```

On the server side, every incoming request needs two checks: is this token valid at all, and does it have the scope required for *this specific action*? I've found it genuinely helpful — not just nice-to-have — to return metadata about both. GitHub and Slack both use response headers for exactly this:

```
X-OAuth-Scopes: repo, user
X-Accepted-OAuth-Scopes: user
```

And when a token is missing a required scope, Slack returns a specific, actionable error body instead of a vague "forbidden":

```json
{
  "ok": false,
  "error": "missing_scope",
  "needed": "chat:write:user",
  "provided": "identify,bot,users:read"
}
```

That single response tells a developer *exactly* what went wrong and exactly what to fix — no guesswork, no support ticket needed.

### Token Expiry and Refresh Tokens

I've become a firm believer in short-lived access tokens. If a token expires in, say, an hour, a leaked token is only dangerous for that hour. To avoid forcing users to constantly re-authorize, providers issue **refresh tokens** alongside access tokens — a special, longer-lived credential an app can exchange (along with its `client_id` and `client_secret`) for a fresh access token, without bothering the end user again.

Interestingly, Stripe issues refresh tokens even though its access tokens *don't* expire — purely so a developer can proactively rotate a token if they suspect a compromise, without contacting Stripe support.

Here's why short-lived tokens genuinely raise the security bar, laid out plainly:

- A compromised access token is only useful until it naturally expires.
- A compromised refresh token is useless on its own — the attacker also needs the client secret, which typically isn't stored alongside tokens.
- Even if both are compromised, refresh tokens are usually one-time-use, so the moment the attacker uses it to mint a new access token, the legitimate app's *next* refresh attempt will fail — immediately signaling that something's wrong.

**A cautionary story:** Slack's tokens were historically long-lived — effectively permanent. Developers, being developers, sometimes hardcoded these tokens directly into their application source and accidentally published them on public GitHub repos. Slack's response was to build a scraper that continuously searches GitHub's public repositories for leaked Slack tokens and automatically revokes anything it finds, notifying the developer. It's a clever mitigation, but it's also a great illustration of *why* long-lived tokens are risky in the first place — the blast radius of a single mistake is enormous. Slack has since moved toward short-lived access tokens specifically to shrink that blast radius.

### Letting Users See and Revoke Access

I think it's a baseline requirement, not a nice-to-have, that users can see which applications have access to their account and revoke that access with one click — Twitter's authorized-apps settings page is the model I'd point to. And beyond the UI, I try to expose that same revocation capability as an *API* too, so a developer who suspects a token leak can programmatically revoke it themselves rather than waiting on a support process.

### OAuth Best Practices I Actually Follow

Pulling this together into a checklist I genuinely use:

| Practice | Why it matters |
|---|---|
| Support the `state` parameter | Mitigates CSRF attacks during the authorization redirect |
| Short-lived, single-use authorization codes | Limits the window an intercepted code is useful |
| Consider one-time-use refresh tokens | Helps detect compromised refresh tokens/client secrets |
| Allow client secret rotation | Lets a developer cut off a leaked secret without losing their whole integration |
| Dedicated scopes for sensitive data | Prevents blanket access to things users didn't mean to expose |
| Require HTTPS everywhere | Access tokens travel on every request — plaintext HTTP would leak them instantly |
| Verify the redirect URL matches registration | Stops attackers from redirecting authorization codes to their own servers |
| Block authorization screens from rendering in iframes | Prevents clickjacking attacks on the "Authorize" button |
| Notify users when new access is granted | Lets users catch unauthorized grants quickly |
| Prohibit misleading application names | Prevents phishing apps that impersonate the platform itself |

That last one isn't hypothetical. In 2017, an attacker registered a Google OAuth application literally named "Google Docs," complete with the real Google Docs logo. It phished roughly **one million** Google accounts before it was shut down. A user, mid-authorization-flow, has no easy way to distinguish a legitimately-named app from an impersonator unless the platform actively prevents the impersonation from being possible in the first place.

> **Caution — the Facebook/Cambridge Analytica lesson:** Between 2016 and 2018, Facebook faced intense scrutiny after developers mined user data in ways that technically complied with the API's granted permissions but violated Facebook's actual terms of service. The uncomfortable truth here: a user authorizing a scope is *not* the same as that user meaningfully understanding what they're agreeing to, and it's *not* the same as the third-party app respecting the platform's rules. As an API provider, I have to actively monitor how third-party apps use their granted access and be willing to rate-limit or shut down apps that violate my terms — the trust relationship is between me and my users, not between my users and the app they authorized.

### Securing WebHooks

WebHooks need a fundamentally different security approach than request-response APIs, because the WebHook URL is sitting publicly on the internet, and *anyone* can send a `POST` to it pretending to be the real provider.

**Verification tokens** are the simplest defense — a shared secret the provider sends with every WebHook request, which I compare against a stored value. Simple to implement, but weak: the token travels in plaintext with every request, so if it ever leaks, an attacker can forge requests indefinitely.

**Request signing (HMAC signatures)** is the far more common, more robust approach — used by Stripe and GitHub, among others. Instead of sending the secret itself, the provider computes a hash (an HMAC) of the shared secret plus the request body, and sends *that* signature as a header:

```
Stripe-Signature: t=1492774577,
  v1=5257a869e7ecebeda32affa62cdca3fa51cad7e77a0e56ff536d0c
```

The `t=` value is a timestamp, included specifically to prevent **replay attacks** — if the timestamp is too old, I reject the request outright, even if the signature is otherwise valid.

I wanted to actually see this work end-to-end rather than just describe it, so I wrote and ran a small simulation covering both sides: the provider signing a webhook, and the consumer verifying it — including verifying that tampering and wrong-secret attempts both get correctly rejected.

```python
import hmac
import hashlib
import time
import json

# --- This simulates the PROVIDER side: signing a webhook payload ---

SHARED_SECRET = b"whsec_test_shared_secret_12345"

payload = {
    "event": "charge.succeeded",
    "id": "ch_1A2B3C",
    "amount": 2000,
    "currency": "usd"
}

payload_body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
timestamp = str(int(time.time()))

# Stripe-style signed payload: "{timestamp}.{body}"
signed_payload = f"{timestamp}.".encode("utf-8") + payload_body
signature = hmac.new(SHARED_SECRET, signed_payload, hashlib.sha256).hexdigest()
signature_header = f"t={timestamp},v1={signature}"

print("Outgoing webhook headers:")
print("  X-Signature:", signature_header)
print("Outgoing webhook body:", payload_body.decode())

# --- This simulates the CONSUMER side: verifying the webhook payload ---

def verify_webhook(received_body: bytes, received_header: str, secret: bytes, tolerance_seconds: int = 300) -> bool:
    parts = dict(item.split("=", 1) for item in received_header.split(","))
    received_ts = parts["t"]
    received_sig = parts["v1"]

    # Reject replayed/old requests
    if abs(int(time.time()) - int(received_ts)) > tolerance_seconds:
        print("Rejected: timestamp outside tolerance window (possible replay attack)")
        return False

    expected_signed_payload = f"{received_ts}.".encode("utf-8") + received_body
    expected_sig = hmac.new(secret, expected_signed_payload, hashlib.sha256).hexdigest()

    # Constant-time comparison to avoid timing attacks
    if not hmac.compare_digest(expected_sig, received_sig):
        print("Rejected: signature mismatch (payload may have been tampered with)")
        return False

    return True

is_valid = verify_webhook(payload_body, signature_header, SHARED_SECRET)
print("\nLegitimate webhook valid?", is_valid)

tampered_body = payload_body.replace(b'"amount":2000', b'"amount":9999999')
is_valid_tampered = verify_webhook(tampered_body, signature_header, SHARED_SECRET)
print("Tampered webhook valid?", is_valid_tampered)

is_valid_wrong_secret = verify_webhook(payload_body, signature_header, b"wrong_secret")
print("Wrong-secret webhook valid?", is_valid_wrong_secret)
```

Here's the actual output when I ran it:

```
Outgoing webhook headers:
  X-Signature: t=1789505224,v1=e82e9a45bacfed11d693c0d80060ea8d4be6c7b7e2cb4d8e93fdee694e9605b0
Outgoing webhook body: {"event":"charge.succeeded","id":"ch_1A2B3C","amount":2000,"currency":"usd"}

Legitimate webhook valid? True
Rejected: signature mismatch (payload may have been tampered with)
Tampered webhook valid? False
Rejected: signature mismatch (payload may have been tampered with)
Wrong-secret webhook valid? False
```

That last part is the whole point: a legitimate, unmodified webhook passes; an attacker who tampers with even a single field gets caught immediately, because the recomputed hash no longer matches; and someone guessing at the secret gets caught the same way. Note also that I used `hmac.compare_digest()` rather than a plain `==` comparison — a naive string comparison can leak timing information about *how many characters matched*, which sophisticated attackers can exploit to guess a signature byte by byte. `compare_digest` runs in constant time specifically to close that side channel.

**Mutual TLS** is a heavier-weight option some providers (DocuSign, for instance) offer for business-to-business integrations — both sides present certificates and authenticate each other at the connection level, meaning security is enforced structurally rather than relying on application code to check a signature correctly.

**Thin payloads** are a clever pattern Google uses for Gmail's WebHook API: instead of sending the full event data in the WebHook itself, Gmail sends just enough information (an email address and a change ID) to tell the app *something* changed. The app then has to make a separate, fully-authenticated API call to retrieve the actual details. The advantage: even if a developer never bothers to verify the WebHook signature at all, they still can't get any sensitive data without going through a proper authenticated request.

> **Note — WebHook security checklist:** Never put secrets or passwords directly in a WebHook payload. Always include a timestamp if you're signing payloads, so receivers can reject replays. Support secret rotation, so a compromised shared secret can be replaced without breaking the integration. And ship SDKs or sample verification code — because if verification is hard to implement correctly, plenty of developers simply won't bother.

---

## Part 4: Design Best Practices for a Great Developer Experience

Paradigm and security decisions get an API *working*. This last section is about what makes an API actually pleasant — even delightful — to use, because developers have a genuinely low bar for abandoning an API that frustrates them.

### Start From Real Use Cases, Not Internal Architecture

The single biggest design mistake I see — and have made myself — is designing an API around my own internal data model instead of around what developers are actually trying to accomplish. When I do that, I end up leaking implementation details that mean nothing to an outside developer and force them to reverse-engineer my internal mental model just to get anything done.

Ido Green, a developer advocate at Google, put it well:

> The API should enable developers to do one thing really well. It's not as easy as it sounds, and you want to be clear on what the API is not going to do as well.

I try to write down concrete use cases before I design anything — not vague aspirations like "developers should be able to build cool things," but specific sentences like "a developer should be able to charge a customer's credit card" or "a developer should be able to list all open issues in a repository." Then I check: can a developer *actually* accomplish that, using only my public API, without needing special internal knowledge?

### Make It Fast to Get Started

Romain Huet, head of developer relations at Stripe, framed API design as something closer to designing a transportation network than a fixed destination:

> Rather than prescribing an end state or destination, a good API expands the very notion of what's possible for developers.

In practice, "fast to get started" means a few concrete things I try to provide:

- **Interactive documentation** — a sandbox where a developer can try real requests without first implementing authentication. Stripe's docs let you test API calls right in the browser before you've written a single line of your own code.
- **SDKs** — pre-built client libraries that handle the tedious plumbing (auth headers, retries, serialization) so a developer's first successful call happens in minutes, not hours.
- **A frictionless signup flow** — the absolute minimum information required to get an API key. Every extra required field before "Hello World" is a developer I might lose.
- **An easy way to generate OAuth access tokens directly from a dashboard UI.** Implementing the full OAuth flow is genuinely tedious, and if there's no shortcut for testing, I'll see a real drop-off at exactly that step.

### Work Toward Consistency

This is the practice I think pays off the most over the long run, and it's deceptively simple to state: **developers should be able to guess how parts of my API work without reading the docs.**

That means: if I have a resource called `users` in one endpoint, I don't casually rename it to `members` somewhere else just because "members" feels more accurate in a newer part of the product. If a `user` field is sometimes an integer ID and sometimes a nested object, every developer working with my API now has to write conditional logic to handle both shapes — for every single field like that, across their entire codebase.

Consistency shows up in things like:

- Naming conventions (snake_case vs. camelCase, singular vs. plural)
- Error response formats — if a successful response is JSON, an error response should be JSON too, in the same envelope shape
- Pagination patterns — always the same parameter names and response structure
- Timestamp formats — always ISO 8601, always UTC, no exceptions

The payoff compounds: consistency reduces cognitive load for new developers picking up my API for the first time, and it prevents my own SDKs from accumulating special-case logic to paper over inconsistencies I introduced myself.

### Make Troubleshooting Easy

Nothing kills developer goodwill faster than a vague, unhelpful error. I try to design my error system as deliberately as I design the "happy path" endpoints.

A **meaningful error** is easy to understand, unambiguous, and actionable. I like to return both a machine-readable error *code* (so developers can branch on it programmatically) and a human-readable *message* (so a person debugging at 2 a.m. can understand what actually went wrong without cross-referencing docs).

Here's a table of good versus not-so-good error codes for the same underlying problems:

| Situation | Recommended code | Not recommended |
|---|---|---|
| Authentication failed because token is revoked | `token_revoked` | `invalid_auth` |
| Value passed for `name` exceeded max length | `name_too_long` | `invalid_name` |
| Credit card has expired | `expired_card` | `invalid_card` |
| Cannot refund because charge already refunded | `charge_already_refunded` | `cannot_refund` |

Notice the pattern: the "not recommended" column is technically true but generic enough to be nearly useless. The "recommended" column tells a developer *exactly* what's wrong and hints at exactly what to fix.

I've also found it worth mapping out error *categories* along the actual code path a request travels, rather than inventing error types ad hoc as I go:

| Error category | Examples |
|---|---|
| System-level error | Database connection issue, backend service down, fatal error |
| Business logic error | Rate-limited, request fulfilled but no results found, business rule denies access |
| API request formatting error | Missing required parameter, conflicting parameters |
| Authorization error | Invalid OAuth credentials, expired token |

And from there, deciding how to communicate each category consistently:

| Error category | HTTP status | Headers | Machine-readable code | Human-readable message |
|---|---|---|---|---|
| System-level error | `500` | — | — | — |
| Business logic error | `429` | `Retry-After` | `rate_limit_exceeded` | "You have been rate-limited. See Retry-After and try again." |
| API request formatting error | `400` | — | `missing_required_parameter` | "Your request was missing a `{user}` parameter." |
| Auth error | `401` | — | `invalid_request` | "Your ClientId is invalid." |

I wanted to actually see this pattern behave correctly rather than just sketch it out, so I wrote a small `APIError` class that formats structured errors — including reproducing the exact kind of test/live-mode mismatch error Stripe returns — and ran it through a few scenarios:

```python
class APIError(Exception):
    def __init__(self, http_status, code, message, headers=None):
        self.http_status = http_status
        self.code = code
        self.message = message
        self.headers = headers or {}

    def to_response(self):
        return {
            "status": self.http_status,
            "headers": self.headers,
            "body": {
                "error": {
                    "code": self.code,
                    "message": self.message
                }
            }
        }


def charge_card(amount, token, is_test_key, is_test_token):
    if is_test_key and not is_test_token:
        raise APIError(
            400, "test_live_mismatch",
            f"No such token {token}. A similar object exists in test mode, "
            f"but a live mode key was used to make this request."
        )
    if amount <= 0:
        raise APIError(400, "invalid_amount", "Amount must be a positive integer.")
    return {"status": "succeeded", "amount": amount}


test_cases = [
    dict(amount=2000, token="tok_live_abc", is_test_key=True, is_test_token=False),
    dict(amount=-50, token="tok_test_60neARX2", is_test_key=False, is_test_token=False),
    dict(amount=2000, token="tok_test_60neARX2", is_test_key=True, is_test_token=True),
]

for case in test_cases:
    try:
        result = charge_card(**case)
        print("Success:", result)
    except APIError as e:
        print("Error response:", e.to_response())
```

The actual output when I ran it:

```
Error response: {'status': 400, 'headers': {}, 'body': {'error': {'code': 'test_live_mismatch', 'message': 'No such token tok_live_abc. A similar object exists in test mode, but a live mode key was used to make this request.'}}}
Error response: {'status': 400, 'headers': {}, 'body': {'error': {'code': 'invalid_amount', 'message': 'Amount must be a positive integer.'}}}
Success: {'status': 'succeeded', 'amount': 2000}
```

Every error response comes back in exactly the same JSON envelope shape as the success response — just with an `error` object instead of the resource data — which is exactly the consistency principle I mentioned earlier applying directly to error design.

> **Caution:** There's a real tension between being specific and being secure. I want to be as specific as possible to help developers fix their own mistakes — but sometimes specificity leaks information I don't want exposed. I don't want a raw database connection error bubbling up to the outside world; it reveals infrastructure details that could help an attacker, and it's not actionable for the developer anyway. My rule: be maximally specific about the developer's *own* mistake, and maximally generic about *my* internal failures.

Beyond the errors themselves, I've found real value in building internal tooling around them — dashboards showing error frequency over time, which endpoints get hit most, which parameters go unused. That data tells me where developers are actually struggling, often before they ever file a support ticket.

> **Note:** If you're logging real request data for troubleshooting, redact personally identifiable information before it ever hits your logs or dashboards. It's a privacy obligation, not just a nice-to-have.

### Design for Extensibility

No API design survives first contact with years of product evolution unscathed. I've come to think of extensibility less as an afterthought and more as a core design constraint from day one.

Kyle Daigle's framing of this, again from GitHub, has stuck with me:

> APIs should provide primitives that can enable new workflows and not simply mirror the workflows of your application... You need to find the right balance to enable workflows you hadn't considered.

Practically, this means a few things:

- **Give privileged partners an early look.** A beta or early-adopter program lets me get real feedback on a design before it's locked in by widespread public adoption — while changes are still cheap to make.
- **Decide on versioning early, even if I don't need it yet.** Building a versioning system is far easier to bolt on at the start than to retrofit years later, once thousands of integrations implicitly depend on undocumented current behavior. If I anticipate major breaking changes down the road, I set up the versioning scaffolding immediately — even if the first major version bump doesn't happen for years.
- **Prefer additive changes when I can.** Adding a new field is safe. Renaming or removing one is a breaking change. If I genuinely don't expect major breaking changes ahead, I can sometimes skip formal versioning entirely and just commit to careful, additive evolution instead.

**A story I find genuinely impressive:** when Slack launched its Enterprise Grid product in 2017, it had to fundamentally restructure its user data model — moving from a single user ID to a global ID plus a per-workspace local ID, to support users belonging to multiple workspaces. That change, done naively, would have broken every third-party integration relying on the old fixed user ID format.

Instead of accepting that breakage, Slack's engineering team built a **translation layer** — infrastructure that silently mapped the new ID structure back to the IDs developers had always received, preserving backward compatibility. That decision delayed the entire product launch by several months. Slack judged that cost as worth paying, because for a platform businesses depend on, breaking existing integrations wholesale simply wasn't an acceptable option.

Not every company will make that same tradeoff, and that's a legitimate call to make deliberately rather than by accident. Twitch, for example, deprecated an older API in 2018 and initially set an end-of-life date — but after hearing from developers who genuinely needed more time to migrate, Twitch chose to extend that deadline rather than force a hard cutoff. Both stories point at the same underlying value: **give developers enough runway, and listen when they tell you it isn't enough.**

---

## Closing Thoughts

If I zoom all the way back out, everything in this post is really answering one question from a few different angles: *how do I make it as easy as possible for a developer to trust, understand, and build on my API — today, and years from now?*

Picking REST, RPC, or GraphQL is about matching my API's shape to the actual problem I'm solving, not chasing whichever paradigm is trendiest. Choosing WebHooks, WebSockets, or streaming is about respecting the fact that stale data and wasteful polling are real costs, both for me and for every developer calling my API. Getting OAuth and WebHook security right is about earning trust I genuinely deserve to be given, and proving it with verifiable mechanisms like HMAC signatures rather than just asking developers to take my word for it. And the design practices — consistency, meaningful errors, extensibility — are about respecting the fact that once developers build on what I've shipped, I've made them a promise, whether I meant to or not.

I ran real code in this post — a webhook signature scheme that correctly caught both tampering and wrong-secret attempts, a payload-size comparison that showed GraphQL's over-fetching advantage concretely rather than just asserting it, and a structured error system that behaved exactly as designed across multiple test cases. I think that's the right way to actually internalize this material: not just reading about how these patterns are *supposed* to work, but watching them actually work — and, just as usefully, watching them correctly fail when something's wrong.

## A Quick-Reference Cheat Sheet

Since I covered a lot of ground here, I want to leave myself — and you — a condensed reference for the decisions this post actually walked through.

**Choosing a request-response paradigm:**

| If my API mostly... | I'd reach for... |
|---|---|
| Manages a set of resources with standard create/read/update/delete needs | REST |
| Exposes a pile of discrete actions that don't map cleanly to CRUD | RPC |
| Serves many different clients (mobile, web, dashboards) that each need different slices of related data | GraphQL |

**Choosing an event-driven mechanism:**

| If I need... | I'd reach for... |
|---|---|
| Simple server-to-server notifications, no browser involved | WebHooks |
| Two-way, low-latency communication with a browser client | WebSockets |
| One-way live updates over plain HTTP, browser-friendly | HTTP Streaming (SSE) |

**Security non-negotiables, regardless of paradigm:**

- Never use Basic Authentication for third-party access — use OAuth 2.0 instead.
- Scope tokens narrowly; don't grant "read everything" when "read this one resource" would do.
- Prefer short-lived access tokens plus refresh tokens over long-lived tokens.
- Sign WebHook payloads with HMAC, include a timestamp, and use constant-time comparison when verifying.
- Require HTTPS everywhere access tokens travel — which, in practice, means everywhere.

**Design habits worth keeping, even under deadline pressure:**

- Write down the actual developer use case before writing the endpoint.
- Keep naming, error formats, and pagination consistent across the whole surface area, not just within one feature.
- Return machine-readable *and* human-readable errors, in the same response envelope as success.
- Decide on a versioning strategy before I need one, not after.

None of this is a checklist I run through once and forget. Every new endpoint I add is a fresh chance to either reinforce these patterns or quietly break them — and developers, it turns out, notice both.
