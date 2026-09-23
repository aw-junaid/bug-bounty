# API Testing Techniques: A Practical, Deep-Dive Guide

*A blog post I wrote after years of chasing down flaky endpoints, silent 500s, and "works on my machine" API bugs.*

## Why I'm Writing This

I've spent a lot of my career testing software, and if there's one lesson that keeps repeating itself, it's this: **the API layer is where most of the real damage happens when something breaks.** The UI can look gorgeous and still be sitting on top of an API that leaks data, times out under load, or silently returns the wrong record. I wanted to put together the guide I wish I'd had when I started taking API testing seriously — something that covers the theory, but also gives me runnable code, diagrams I can point to in a design review, and the war-story-level cautions that only show up after you've been burned once or twice.

This post is long on purpose. I'm covering functional testing, performance testing, security testing, GraphQL, CI/CD integration, and the best practices and pitfalls I've collected along the way. Grab a coffee.

---

## Table of Contents

1. [What API Testing Actually Is](#what-api-testing-actually-is)
2. [Why It Matters So Much Right Now](#why-it-matters-so-much-right-now)
3. [The Three Pillars: Functional, Performance, Security](#the-three-pillars-functional-performance-security)
4. [Designing Test Cases That Actually Catch Bugs](#designing-test-cases-that-actually-catch-bugs)
5. [Automating Tests with PyTest and Requests](#automating-tests-with-pytest-and-requests)
6. [Performance Testing with Locust](#performance-testing-with-locust)
7. [Security Testing APIs](#security-testing-apis)
8. [Testing GraphQL APIs](#testing-graphql-apis)
9. [Wiring Tests into CI/CD](#wiring-tests-into-cicd)
10. [Best Practices and Common Pitfalls](#best-practices-and-common-pitfalls)
11. [Wrapping Up](#wrapping-up)

---

## What API Testing Actually Is

When I explain this to people new to the field, I like to draw the distinction against UI testing first. UI testing checks what a human sees and clicks. API testing skips the human entirely and talks directly to the service layer — the thing that actually does the work.

Here's how I think about where API testing sits in the bigger picture:

```mermaid
graph TD
    A[Manual / Exploratory Testing] --> B[UI / End-to-End Tests]
    B --> C[API / Service Tests]
    C --> D[Unit Tests]
    style D fill:#2d6a4f,color:#fff
    style C fill:#40916c,color:#fff
    style B fill:#74c69d,color:#000
    style A fill:#b7e4c7,color:#000
```

I usually draw this as a pyramid rather than a plain stack: lots of fast unit tests at the bottom, a healthy middle layer of API tests, and a thin layer of UI tests on top. The reason I lean so hard on the middle layer is simple — API tests give me most of the confidence of an end-to-end test, at a fraction of the cost and flakiness.

> **Note:** I define "API testing" broadly here. It covers REST, GraphQL, and even gRPC in spirit, though most of my concrete examples use REST because that's still what I run into most often in production systems.

### The Core Goal

Every API test I write is trying to answer one of these questions:

- Does the API do what it's supposed to do, for valid input? (**functional**)
- Does the API keep doing that when a lot of people hit it at once? (**performance**)
- Can someone abuse it to get data or access they shouldn't have? (**security**)
- Does it fail *gracefully* when things go wrong? (**reliability**)

If I can answer "yes" to all four with a reasonable degree of confidence, I consider the API well-tested.

---

## Why It Matters So Much Right Now

I don't think I'm overstating it when I say APIs are the actual product in most of the systems I work on today. The UI is often just a thin client sitting on top of a constellation of services talking to each other over HTTP or gRPC.

### The Shift to Distributed Systems

Ten years ago, I was mostly testing monoliths — one big codebase, one database, internal function calls instead of network calls. Today almost everything I touch looks like this:

```mermaid
graph LR
    Mobile[Mobile App] -->|HTTPS| GW[API Gateway]
    Web[Web App] -->|HTTPS| GW
    GW --> Auth[Auth Service]
    GW --> Users[User Service]
    GW --> Orders[Order Service]
    GW --> Payments[Payment Service]
    Orders --> Inventory[Inventory Service]
    Payments --> Bank[Third-Party Payment API]
```

Every arrow in that diagram is a place where an API contract can be violated, a timeout can cascade, or a security boundary can be tested by an attacker. That's exactly why I test each of those boundaries independently, instead of hoping the UI catches everything.

### Why I Test at the API Layer Instead of Just the UI

A few reasons I keep coming back to:

| Reason | Why it matters to me |
|---|---|
| **Speed** | An API test against a real endpoint usually runs in milliseconds to a couple of seconds. A UI test involves a browser, page loads, and waiting for elements — often 10-50x slower. |
| **Stability** | UI tests break when a button moves three pixels to the left. API tests only break when the actual contract changes. |
| **Shift-left** | I can start testing an API as soon as the endpoint exists, often before there's any UI built on top of it at all. |
| **Isolation** | When an API test fails, I know exactly which service is broken. A failing UI test could mean the UI, the API, or three services downstream. |
| **CI/CD friendliness** | API tests are trivial to run in a pipeline. No headless browser infrastructure required. |

> **Caution:** I've seen teams over-correct and drop UI testing entirely because "the API tests cover it." They don't — the API tests won't catch a broken button binding or a CSS regression that hides the checkout button. I treat API tests as the *majority* of my coverage, not the *entirety* of it.

---

## The Three Pillars: Functional, Performance, Security

I organize almost all of my API testing effort into three buckets. They overlap a bit, but thinking in these categories helps me make sure I'm not accidentally skipping one of them.

```mermaid
graph TD
    API[API Under Test]
    API --> F[Functional Testing]
    API --> P[Performance Testing]
    API --> S[Security Testing]
    F --> F1[Correct responses]
    F --> F2[Correct status codes]
    F --> F3[Error handling]
    P --> P1[Load testing]
    P --> P2[Stress testing]
    P --> P3[Latency / throughput]
    S --> S1[AuthN / AuthZ]
    S --> S2[Injection resistance]
    S --> S3[Data encryption]
```

### Functional Testing

This is where I start, always. Functional testing answers: *for a given input, does the API return the output I expect, with the right status code and the right shape of data?*

I break my functional test cases into positive and negative paths:

- **Positive path** — valid input, expected success response.
- **Negative path** — invalid, missing, or malformed input, expected error response.

### Performance Testing

Once I trust that the API behaves correctly, I want to know if it *stays* correct under load. I usually split this into:

- **Load testing** — simulate realistic, expected traffic.
- **Stress testing** — push past expected traffic to find the breaking point.
- **Soak testing** — run moderate load for a long time to catch memory leaks or slow degradation.

### Security Testing

Last but never least. I check authentication, authorization, input sanitization, and how much the API reveals about itself when something goes wrong.

> **Note:** I don't do these three in strict sequence in real projects — I write a functional test and a matching negative/security test for the same endpoint in the same sitting, because the context is fresh in my head.

---

## Designing Test Cases That Actually Catch Bugs

I've watched a lot of test suites that have hundreds of tests and still miss obvious bugs, because the test cases were all testing the same happy path with slightly different data. Here's how I actually approach designing test cases.

### Step 1: Map the Inputs

For every field an endpoint accepts, I ask myself five questions:

1. What's a **valid** value?
2. What's an **invalid** value (wrong type, wrong format)?
3. What are the **boundary** values (min length, max length, zero, negative)?
4. What happens with a **null or empty** value?
5. What happens if I send something **malicious** (SQL injection string, script tag, oversized payload)?

I like to keep this as an actual table before I write a single line of test code — it forces me to be exhaustive instead of just writing whatever comes to mind.

**Example: `POST /users` — field `username` (rules: 3–15 characters, alphanumeric)**

| Test case | Input | Expected result |
|---|---|---|
| Valid, mid-range | `"johndoe"` | `201 Created` |
| Valid, lower boundary | `"abc"` (3 chars) | `201 Created` |
| Valid, upper boundary | `"abcdefghijklmno"` (15 chars) | `201 Created` |
| Invalid, below boundary | `"ab"` (2 chars) | `400 Bad Request` |
| Invalid, above boundary | 16+ char string | `400 Bad Request` |
| Empty string | `""` | `400 Bad Request` |
| Null | `null` | `400 Bad Request` |
| Missing field entirely | field omitted | `400 Bad Request` |
| Wrong type | `12345` (number) | `400 Bad Request` |
| Special characters | `"john<script>"` | `400 Bad Request`, sanitized, no reflected script |
| SQL-injection-shaped string | `"' OR '1'='1"` | `400 Bad Request` or safely treated as literal string |

I do this for *every* field that matters, not just the "important" ones. Boundary bugs love to hide in the field nobody thought was interesting.

### Step 2: Define the Output Contract

Once inputs are mapped, I write down exactly what a correct response looks like — structure, types, and status code — *before* I write the assertion code. This keeps me from writing lazy assertions like "just check it's a 200."

```json
{
  "id": 123,
  "username": "johndoe",
  "email": "john@example.com",
  "createdAt": "2026-09-20T10:15:00Z"
}
```

Things I always check on the response, not just the status code:

- **Structure** — are all expected keys present, and no unexpected ones leaking through (like an internal `passwordHash` field)?
- **Types** — is `id` actually a number, not a numeric string?
- **Values** — does `username` in the response match what I sent, not some transformed version I didn't expect?
- **Headers** — is `Content-Type` correct? Is there a `Location` header for a `201`?

> **Caution:** I've been burned more than once by a test that only checked `response.status_code == 200` and missed that the response body had silently stopped including a field the frontend depended on. Status-code-only assertions are close to useless on their own.

### Step 3: Edge Cases and Error Scenarios

This is the part people skip when they're in a hurry, and it's exactly the part that saves me in production. My standard edge-case checklist:

- Extremely large payloads (e.g., 10,000-item array in a request body)
- Concurrent requests modifying the same resource (race conditions)
- Timeouts from a downstream dependency
- Expired or malformed authentication tokens
- Requests with unexpected content types (`text/plain` instead of `application/json`)
- Unicode and emoji in text fields
- Pagination edge cases — page 0, negative page numbers, page far beyond the data

### HTTP Status Codes I Always Verify

I keep this table pinned somewhere close by, because getting status codes "close enough" is a common and avoidable mistake.

| Code | Meaning | When I expect it |
|---|---|---|
| `200 OK` | Success | `GET`, successful `PUT`/`PATCH` |
| `201 Created` | Resource created | Successful `POST` that creates something |
| `204 No Content` | Success, empty body | Successful `DELETE` |
| `400 Bad Request` | Client sent malformed/invalid data | Validation failures |
| `401 Unauthorized` | No or invalid credentials | Missing/expired/invalid token |
| `403 Forbidden` | Authenticated but not allowed | Role/permission failures |
| `404 Not Found` | Resource doesn't exist | Invalid ID lookups |
| `409 Conflict` | State conflict | Duplicate resource creation |
| `422 Unprocessable Entity` | Semantically invalid data | Business-rule validation failures |
| `429 Too Many Requests` | Rate limit hit | Throttling tests |
| `500 Internal Server Error` | Server-side bug | Should almost never show up in a passing test suite |
| `503 Service Unavailable` | Downstream dependency down | Tested with fault injection / timeouts |

---

## Automating Tests with PyTest and Requests

Here's where I stop talking in the abstract and show actual code. I'm using [httpbin.org](https://httpbin.org), a free public HTTP testing service, so every snippet below is something I actually ran before putting it in this post — you can copy-paste and run these yourself.

### Setting Up

```bash
python3 -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate

pip install pytest requests
```

### My First Real Test: A GET Request

```python
# test_get_request.py
import requests

def test_get_returns_200_and_correct_origin_structure():
    response = requests.get("https://httpbin.org/get", params={"user": "johndoe"})

    assert response.status_code == 200

    data = response.json()
    # httpbin echoes back what we sent under "args"
    assert data["args"]["user"] == "johndoe"
    assert "headers" in data
    assert response.headers["Content-Type"] == "application/json"
```

I ran this with:

```bash
pytest test_get_request.py -v
```

and got:

```
test_get_request.py::test_get_returns_200_and_correct_origin_structure PASSED
```

### Testing a POST Request with a JSON Body

```python
# test_post_request.py
import requests

def test_post_creates_expected_payload_echo():
    payload = {
        "username": "janedoe",
        "email": "jane@example.com"
    }

    response = requests.post("https://httpbin.org/post", json=payload)

    assert response.status_code == 200

    data = response.json()
    # httpbin's /post echoes the parsed JSON body back under "json"
    assert data["json"]["username"] == "janedoe"
    assert data["json"]["email"] == "jane@example.com"
    assert data["headers"]["Content-Type"] == "application/json"
```

### Testing Authentication Headers

```python
# test_auth.py
import requests

def test_bearer_token_is_forwarded_correctly():
    token = "test-token-123"
    headers = {"Authorization": f"Bearer {token}"}

    response = requests.get("https://httpbin.org/bearer", headers=headers)

    assert response.status_code == 200
    data = response.json()
    assert data["authenticated"] is True
    assert data["token"] == token


def test_missing_auth_header_is_rejected():
    response = requests.get("https://httpbin.org/bearer")
    assert response.status_code == 401
```

I like this pair of tests because it shows the pattern I use everywhere: one test proving the happy path works, one proving the API correctly rejects the absence of what it needs.

### Handling Status Code Scenarios Directly

httpbin has a neat endpoint that lets me request any status code on demand, which is great for exercising my assertion logic without needing a real broken endpoint:

```python
# test_status_codes.py
import pytest
import requests

@pytest.mark.parametrize("status_code", [200, 201, 400, 401, 403, 404, 500, 503])
def test_status_code_is_echoed(status_code):
    response = requests.get(f"https://httpbin.org/status/{status_code}")
    assert response.status_code == status_code
```

Running this:

```bash
pytest test_status_codes.py -v
```

gives me eight separate test results, one per parametrized status code — exactly the kind of table-driven coverage I want without writing eight nearly-identical functions.

### Parameterizing Real Test Data

```python
# test_user_lookup.py
import pytest
import requests

# In a real project this points at your own API. Using httpbin's /anything
# endpoint here just to keep the example runnable end-to-end.
@pytest.mark.parametrize("user_id, expected_status", [
    (123, 200),
    (0, 400),
    (-1, 400),
])
def test_user_lookup_status(user_id, expected_status):
    # /status/<code> is a stand-in — swap for your real endpoint,
    # e.g. requests.get(f"https://api.example.com/users/{user_id}")
    status_to_request = expected_status
    response = requests.get(f"https://httpbin.org/status/{status_to_request}")
    assert response.status_code == expected_status
```

> **Note:** In the snippet above I'm using httpbin's `/status/<code>` endpoint as a stand-in because I don't have a real, publicly reachable user API to point you at. The structure — `@pytest.mark.parametrize` feeding multiple input/expected-output pairs into one test function — is exactly what I use against real APIs.

### Timeouts and Retries

I always set explicit timeouts. A test suite that hangs indefinitely because a service never responds is worse than a test that fails fast.

```python
# test_timeout_handling.py
import requests
from requests.exceptions import Timeout

def test_slow_endpoint_times_out_as_expected():
    try:
        # /delay/5 waits 5 seconds before responding
        requests.get("https://httpbin.org/delay/5", timeout=1)
        assert False, "Expected a Timeout exception but the request succeeded"
    except Timeout:
        # This is the behavior we want to confirm
        assert True
```

I use a short timeout on purpose here so the test itself doesn't take five real seconds to fail. This is a pattern I apply everywhere: don't let your tests be as slow as the failure mode you're testing for.

### Fixtures for Shared Setup

Once I have more than two or three tests, I stop repeating boilerplate and move to fixtures:

```python
# conftest.py
import pytest

@pytest.fixture
def api_base_url():
    return "https://httpbin.org"

@pytest.fixture
def auth_headers():
    return {"Authorization": "Bearer test-token-123"}
```

```python
# test_with_fixtures.py
import requests

def test_authenticated_request(api_base_url, auth_headers):
    response = requests.get(f"{api_base_url}/bearer", headers=auth_headers)
    assert response.status_code == 200
    assert response.json()["authenticated"] is True
```

### Generating an HTML Report

```bash
pip install pytest-html
pytest --html=report.html --self-contained-html
```

That gives me a single-file HTML report I can attach to a build artifact or hand to a teammate without them needing to run anything themselves.

---

## Performance Testing with Locust

Functional correctness doesn't mean much if the API falls over the moment real traffic hits it. I reach for [Locust](https://locust.io/) for this — it's Python-based, so I get to write load scenarios in the same language as my functional tests.

### Installing Locust

```bash
pip install locust
```

### A Basic Load Test

```python
# locustfile.py
from locust import HttpUser, task, between

class ApiUser(HttpUser):
    # Each simulated user waits 1-3 seconds between actions,
    # roughly mimicking a real person, not a script hammering the API.
    wait_time = between(1, 3)

    @task(3)
    def get_resource(self):
        # weight of 3 — this happens 3x more often than create_resource
        self.client.get("/get")

    @task(1)
    def create_resource(self):
        self.client.post("/post", json={"username": "loadtestuser"})
```

I run this against httpbin as a stand-in target:

```bash
locust -f locustfile.py --host=https://httpbin.org
```

Then I open `http://localhost:8089`, set the number of simulated users and spawn rate, and watch the live dashboard.

```mermaid
sequenceDiagram
    participant L as Locust Master
    participant W as Simulated Users
    participant API as API Under Test
    L->>W: Spawn N users
    loop Every wait_time interval
        W->>API: GET /get
        API-->>W: 200 OK
        W->>API: POST /post
        API-->>W: 200 OK
    end
    W-->>L: Report response times, failures
    L-->>L: Aggregate metrics dashboard
```

### What I Actually Watch During a Load Test

| Metric | What it tells me | Red flag |
|---|---|---|
| Median (p50) response time | Typical user experience | Rising as load increases |
| p95 / p99 response time | Worst-case tail latency | Big gap between p50 and p99 |
| Requests per second (RPS) | Throughput ceiling | Plateaus well below expected traffic |
| Failure rate | Stability under load | Any non-zero rate for expected-success endpoints |
| CPU / memory on the server | Resource exhaustion | Climbing memory that never plateaus (leak) |

> **Caution:** I never run a serious load test against a production system without coordinating with whoever owns it. I've accidentally taken down a staging environment more than once by forgetting it shared a database connection pool with something else important.

### Simulating a Realistic User Journey

Real users don't just hit one endpoint in a loop — they log in, do something, then log out. I model that:

```python
# user_journey_locustfile.py
from locust import HttpUser, task, between

class UserJourney(HttpUser):
    wait_time = between(1, 3)

    def on_start(self):
        # Runs once when a simulated user starts — good place to "log in"
        response = self.client.get("/bearer", headers={"Authorization": "Bearer demo-token"})
        self.token = "demo-token" if response.status_code == 200 else None

    @task
    def view_profile(self):
        headers = {"Authorization": f"Bearer {self.token}"}
        self.client.get("/get", headers=headers, name="/profile")

    @task
    def update_profile(self):
        headers = {"Authorization": f"Bearer {self.token}"}
        self.client.post("/post", json={"bio": "updated bio"}, headers=headers, name="/profile [update]")
```

The `name=` parameter is something I use constantly — it groups requests with dynamic paths (like `/users/123`, `/users/456`) under one readable label in the Locust dashboard instead of splitting my stats across hundreds of unique URLs.

### Headless / CI-Friendly Runs

For CI, I don't want the web UI — I want a fixed run that exits with a clear pass/fail:

```bash
locust -f locustfile.py --host=https://httpbin.org \
  --headless -u 50 -r 5 -t 2m \
  --csv=results
```

This spins up 50 users at a spawn rate of 5/second, runs for 2 minutes, and writes CSV stats I can parse afterward or feed into a dashboard.

---

## Security Testing APIs

I treat security testing as a non-negotiable part of API testing, not a separate phase that happens "later, if there's time." Most of the incidents I've read post-mortems on trace back to an API that trusted its caller too much.

### Authentication vs. Authorization — I Test Them Separately

I keep these conceptually distinct because they fail in different ways:

- **Authentication**: *Who are you?* — is the token valid, not expired, correctly signed?
- **Authorization**: *What are you allowed to do?* — does this authenticated user have permission for this specific action?

```mermaid
flowchart LR
    Req[Incoming Request] --> AuthN{Authenticated?}
    AuthN -- No --> R401[401 Unauthorized]
    AuthN -- Yes --> AuthZ{Authorized for this action?}
    AuthZ -- No --> R403[403 Forbidden]
    AuthZ -- Yes --> Handle[Process Request]
```

### Testing Authentication Failures

```python
# test_auth_security.py
import requests

def test_invalid_token_is_rejected():
    headers = {"Authorization": "Bearer totally-invalid-token"}
    # httpbin's /bearer endpoint accepts any non-empty token, so in a real
    # system this call would go against your own token-validating endpoint.
    # I'm showing the pattern; swap the URL for your API.
    response = requests.get("https://httpbin.org/bearer", headers=headers)
    # Against a real API you'd assert 401 here for a genuinely invalid token.
    assert response.status_code in (200, 401)


def test_no_token_returns_401():
    response = requests.get("https://httpbin.org/bearer")
    assert response.status_code == 401
```

> **Note:** httpbin's `/bearer` endpoint accepts any bearer token as "valid," so it can't demonstrate real token-signature validation. I'm using it only to show test *structure*. Against your own API, this is exactly where you'd send an expired JWT, a token signed with the wrong key, and a token for a disabled account — three separate tests, three separate assertions.

### Testing for Injection Vulnerabilities

I never assume an ORM or framework has "handled" injection for me — I test it directly.

```python
# test_injection_resistance.py
import requests

SQLI_PAYLOADS = [
    "' OR '1'='1",
    "'; DROP TABLE users; --",
    "1; SELECT * FROM users",
]

XSS_PAYLOADS = [
    "<script>alert('xss')</script>",
    "<img src=x onerror=alert(1)>",
]

def test_search_endpoint_rejects_sql_injection_shapes():
    for payload in SQLI_PAYLOADS:
        # Swap for your real search endpoint
        response = requests.get("https://httpbin.org/get", params={"q": payload})
        assert response.status_code == 200
        # The important assertion against a *real* API: the payload comes back
        # untouched as data, never interpreted, and no 500 error is thrown.
        assert response.json()["args"]["q"] == payload

def test_input_field_does_not_reflect_raw_script_tags():
    for payload in XSS_PAYLOADS:
        response = requests.post("https://httpbin.org/post", json={"comment": payload})
        assert response.status_code == 200
        # Against a real API, you'd check the STORED/rendered value is escaped,
        # e.g. "&lt;script&gt;" instead of a live <script> tag.
```

> **Caution:** These payload lists are deliberately mild, textbook examples for illustration. Full security testing of production APIs should be done with proper authorization, ideally by a dedicated security team or a tool like OWASP ZAP, not just ad hoc payloads pasted into a pytest file. Don't run untested injection payloads against systems you don't own or have explicit permission to test.

### Rate Limiting

```python
# test_rate_limiting.py
import requests

def test_repeated_requests_eventually_get_throttled():
    statuses = []
    for _ in range(20):
        response = requests.get("https://httpbin.org/get")
        statuses.append(response.status_code)

    # Against a real, rate-limited API, I'd expect to see 429s appear
    # once I cross the configured threshold.
    # httpbin doesn't rate-limit by default, so this assertion is illustrative:
    assert all(s == 200 for s in statuses) or 429 in statuses
```

### Enforcing HTTPS

```python
# test_https_enforcement.py
import requests

def test_http_redirects_to_https():
    response = requests.get("http://httpbin.org/get", allow_redirects=False)
    # httpbin doesn't force this redirect, but for your own API you'd expect
    # a 301/308 pointing at the https:// version.
    assert response.status_code in (200, 301, 308)
```

### My Security Testing Checklist

| Area | What I test |
|---|---|
| Authentication | Valid token, invalid token, expired token, missing token |
| Authorization | Correct role succeeds, wrong role gets `403`, no privilege escalation via parameter tampering |
| Input validation | SQL injection, XSS, command injection payloads are neutralized |
| Transport security | HTTP redirects to HTTPS, no sensitive data in URLs |
| Error verbosity | Errors don't leak stack traces, internal IPs, or database details |
| Rate limiting | Excessive requests get throttled with `429` |
| Sensitive data exposure | Passwords, tokens, and PII never appear in logs or plain responses |

---

## Testing GraphQL APIs

GraphQL flips a lot of my REST assumptions upside down. There's usually one endpoint (`/graphql`), and the "shape" of the response is defined by the *query*, not the *route*. That means my testing strategy has to shift too.

```mermaid
graph LR
    Client -->|"query { user(id: 1) { name orders { id } } }"| Endpoint["/graphql"]
    Endpoint --> Resolver1[User Resolver]
    Endpoint --> Resolver2[Orders Resolver]
    Resolver1 --> DB[(Database)]
    Resolver2 --> DB
    Endpoint -->|JSON matching requested shape| Client
```

### Setting Up

```bash
pip install pytest gql
```

### A Basic GraphQL Query Test

```python
# test_graphql_query.py
import pytest
from gql import gql, Client
from gql.transport.requests import RequestsHTTPTransport

@pytest.fixture
def graphql_client():
    transport = RequestsHTTPTransport(
        url="https://countries.trevorblades.com/",  # public demo GraphQL API
        use_json=True,
    )
    return Client(transport=transport, fetch_schema_from_transport=True)


def test_country_query_returns_expected_fields(graphql_client):
    query = gql("""
        query {
            country(code: "US") {
                name
                capital
                currency
            }
        }
    """)

    result = graphql_client.execute(query)

    assert result["country"]["name"] == "United States"
    assert result["country"]["capital"] == "Washington"
```

I used a public GraphQL demo API here (`countries.trevorblades.com`) so this test is something you can actually run right now. Against your own service, the pattern stays identical — swap the URL, the query, and the assertions.

### Testing GraphQL Error Handling

GraphQL is unusual in that errors often come back with a `200 OK` status code, with the actual error described inside the JSON body under an `errors` key. I make sure I never forget this when writing assertions.

```python
# test_graphql_errors.py
from gql import gql, Client
from gql.transport.requests import RequestsHTTPTransport
from gql.transport.exceptions import TransportQueryError
import pytest

@pytest.fixture
def graphql_client():
    transport = RequestsHTTPTransport(
        url="https://countries.trevorblades.com/",
        use_json=True,
    )
    return Client(transport=transport, fetch_schema_from_transport=True)


def test_invalid_country_code_raises_graphql_error(graphql_client):
    query = gql("""
        query {
            country(code: "ZZ") {
                name
            }
        }
    """)

    result = graphql_client.execute(query)
    # This particular API returns null rather than an error for unknown codes —
    # a good reminder to always check the ACTUAL behavior of the schema
    # you're testing, rather than assuming REST-style error conventions apply.
    assert result["country"] is None
```

> **Note:** This is exactly the kind of surprise I want my test suite to catch *once*, in writing, so nobody on my team has to rediscover it the hard way six months later. Different GraphQL servers handle "not found" differently — some return `null`, some populate `errors`, some do both.

### What I Test Differently for GraphQL

| Concern | REST approach | GraphQL approach |
|---|---|---|
| Status codes | Primary signal of success/failure | Almost always `200`; check the `errors` field instead |
| Response shape | Fixed per endpoint | Varies per query — assert against exactly what was requested |
| Over/under-fetching | N/A | Test that requesting fewer fields returns fewer fields, not extras |
| Query complexity abuse | N/A | Test deeply nested queries don't cause runaway resource usage |
| Schema evolution | Versioned URLs (`/v1/`, `/v2/`) | Deprecated fields still resolve; new fields are additive |

---

## Wiring Tests into CI/CD

None of this matters much to me if the tests only run on my laptop. I want every pull request to trigger the full API test suite automatically.

```mermaid
flowchart LR
    Dev[Developer pushes code] --> PR[Pull Request opened]
    PR --> CI[CI Pipeline Triggered]
    CI --> Install[Install dependencies]
    Install --> Functional[Run functional tests]
    Functional --> Security[Run security tests]
    Security --> Perf[Run smoke-level performance tests]
    Perf --> Report[Publish test report]
    Report --> Gate{All passed?}
    Gate -- Yes --> Merge[Allow merge]
    Gate -- No --> Block[Block merge, notify team]
```

### A GitHub Actions Workflow I Actually Use

```yaml
# .github/workflows/api-tests.yml
name: API Test Suite

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  functional-tests:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Python
        uses: actions/setup-python@v5
        with:
          python-version: "3.11"

      - name: Install dependencies
        run: |
          python -m pip install --upgrade pip
          pip install -r requirements.txt

      - name: Run functional + security API tests
        run: pytest tests/api --junitxml=report.xml -v

      - name: Upload test report
        uses: actions/upload-artifact@v4
        if: always()
        with:
          name: api-test-report
          path: report.xml

  smoke-load-test:
    runs-on: ubuntu-latest
    needs: functional-tests
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Python
        uses: actions/setup-python@v5
        with:
          python-version: "3.11"

      - name: Install Locust
        run: pip install locust

      - name: Run short smoke load test
        run: |
          locust -f locustfile.py --host=${{ secrets.STAGING_API_URL }} \
            --headless -u 10 -r 2 -t 30s --csv=smoke
```

I split functional tests and load tests into two jobs on purpose. Functional tests should run on every single push and be fast. Load tests, even a short smoke version, take longer and I don't want them blocking a developer's quick iteration loop unnecessarily — I gate the merge on functional + security, and treat the smoke load test as an early warning signal.

### A Few CI/CD Habits I Insist On

- **Fail fast** — if functional tests fail, I don't bother running the load test job.
- **Parallelize** — I split large suites across multiple runners by test module.
- **Isolate environments** — I run against a dedicated staging environment, never against shared dev infrastructure that other people are actively poking at.
- **Publish reports** — every run leaves behind an artifact I can open without re-running anything.

---

## Best Practices and Common Pitfalls

I've distilled the habits that have actually saved me time and the mistakes that have actually cost me time into two tables.

### What I Do On Every Project

| Practice | Why I do it |
|---|---|
| Prioritize by risk | I test payment and auth endpoints harder than a "get app version" endpoint. |
| Cover all HTTP verbs | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` each have their own failure modes. |
| Keep tests independent | A test that depends on another test's leftover data is a test that will randomly fail. |
| Clean up after myself | If a test creates a user, it deletes that user, even on failure (`try`/`finally` or fixtures with teardown). |
| Use realistic test data | Fake data that doesn't resemble production data hides bugs that only show up with real-world shapes (long names, unicode, etc). |
| Automate security checks | A missing auth check is exactly the kind of thing a human reviewer misses and a test catches every time. |
| Version my contracts | I track breaking API changes deliberately, not by accident. |

### Mistakes I've Made (So You Don't Have To)

| Pitfall | What actually happened | What I do now |
|---|---|---|
| Only testing the happy path | An endpoint crashed in production on the very first empty-string input a real user sent. | Every endpoint gets at least one negative test before I call it "done." |
| Over-mocking | My mocked test suite passed while the real integration was completely broken. | I keep a smaller suite of tests that hit real (staging) dependencies, not just mocks. |
| Ignoring flaky tests | A "random" failure turned out to be a real race condition in production. | Flaky tests get investigated immediately, never just re-run until green. |
| Skipping error-message content | A test only checked for `400`, missing that the error message had stopped making sense. | I assert on the *content* of error responses, not just the status code. |
| Letting tests rot | Tests kept passing against an API that had actually changed shape, because nobody updated the assertions. | Tests get reviewed and updated in the same PR that changes the API contract. |
| No timeout set | A hung test tied up a CI runner for the full job timeout. | Every HTTP call in my test suite has an explicit timeout. |

> **Caution:** The single most expensive mistake I've personally made was trusting a test suite that was 100% green but only ever exercised mocked responses. The real API had a subtly different date format than the mock, and it shipped to production undetected for three weeks. I now insist on at least a thin layer of tests running against real staging services before every release.

---

## Wrapping Up

If I had to compress this entire post into a handful of sentences for someone in a hurry, it would be this: test the contract, not just the happy path. Treat performance and security as first-class citizens of your test suite, not afterthoughts bolted on before a big launch. Automate everything you possibly can, and wire it into CI so nobody has to remember to run it manually. And whatever you do, write assertions that actually check the content of a response — not just whether a status code happened to be in the 200s.

API testing isn't glamorous work, most days. But it's the layer of testing that has personally saved me from the worst production incidents I've ever been part of, and the one I trust the most when I need to sleep well the night before a release.

### Quick Reference: Tools I Reach For

| Task | Tool |
|---|---|
| Functional API tests | `pytest` + `requests` |
| GraphQL tests | `pytest` + `gql` |
| Load / stress testing | `Locust` |
| Manual exploration | `Postman` |
| Security scanning | `OWASP ZAP` |
| CI/CD | `GitHub Actions` (or Jenkins / CircleCI) |

---

*If you build on any of the code in this post, remember: swap the demo URLs (httpbin.org, the public countries GraphQL API) for your own service, and adjust the assertions to match your actual contract. The structure of the tests is the reusable part — the URLs are just there so you can run everything yourself, right now, and see it pass.*
