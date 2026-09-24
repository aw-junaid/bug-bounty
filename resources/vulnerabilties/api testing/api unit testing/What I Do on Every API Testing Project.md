# What I Do on Every API Testing Project

After enough projects, you notice that the bugs that hurt most are rarely exotic. They come from skipped basics: an untested edge case, a flaky test suite nobody trusts, or an auth check that quietly went missing. These seven practices are the habits I bring to every project, and the reasoning behind each.

---

## 1. Prioritize by Risk

**The principle:** Not all endpoints deserve equal testing effort. I spend my time in proportion to the *damage* a failure could cause.

A bug in `GET /api/version` is an annoyance. A bug in `POST /api/payments` can mean lost money, legal trouble, and a very bad week. Treating both with the same depth of testing wastes effort on one and under-protects the other.

**How I assess risk**

I score each endpoint on two axes:

| Factor | Question I ask |
|---|---|
| **Impact** | If this breaks or is abused, what is the worst outcome? (money, data leak, account takeover, downtime) |
| **Likelihood** | How complex is it? How often does it change? Does it touch third parties? |

**Typical tiering**

| Tier | Examples | Testing depth |
|---|---|---|
| **Critical** | Payments, login, password reset, token refresh, permissions | Happy path, all negative paths, boundary values, security tests, concurrency, idempotency |
| **High** | User profile updates, order creation, file uploads | Happy path, validation, auth, error handling |
| **Medium** | Search, listings, filters | Core behavior, pagination, basic validation |
| **Low** | Health check, version, static config | Smoke test only |

**In practice**, I tag tests so I can run the important ones first and most often:

```python
import pytest

@pytest.mark.critical
def test_payment_rejects_negative_amount(api, auth_headers):
    resp = api.post("/payments", json={"amount": -50, "currency": "USD"}, headers=auth_headers)
    assert resp.status_code == 400

@pytest.mark.low
def test_version_endpoint_returns_200(api):
    assert api.get("/version").status_code == 200
```

```bash
# Run only critical tests on every commit; the full suite nightly
pytest -m critical
```

Risk-based testing is also a communication tool. When time is short and someone asks "what can we skip?", you have a defensible answer.

---

## 2. Cover All HTTP Verbs

**The principle:** Each verb has different semantics, so each has different ways to fail. Testing only `GET` and `POST` leaves entire categories of bugs untouched.

| Verb | Expected semantics | Failure modes I look for |
|---|---|---|
| **GET** | Safe, read-only, cacheable | Leaking data the caller shouldn't see, broken pagination, missing filters, N+1 slowness, state changes on a "safe" call |
| **POST** | Creates a resource, not idempotent | Duplicate creation on retry, missing validation, wrong status code (`200` vs `201`), missing `Location` header |
| **PUT** | Full replacement, idempotent | Not actually idempotent, silently dropping omitted fields, creating instead of updating |
| **PATCH** | Partial update | Overwriting fields that weren't sent, unable to set a field to `null`, no validation on partial payloads |
| **DELETE** | Removes the resource, idempotent | Deleting things you don't own, orphaned child records, inconsistent responses on repeated deletes |

**Examples of verb-specific tests**

```python
def test_put_is_idempotent(api, user, auth_headers):
    payload = {"name": "Ada Lovelace", "email": "ada@example.com"}

    first = api.put(f"/users/{user['id']}", json=payload, headers=auth_headers)
    second = api.put(f"/users/{user['id']}", json=payload, headers=auth_headers)

    assert first.status_code == second.status_code == 200
    assert first.json() == second.json()


def test_patch_only_changes_supplied_fields(api, user, auth_headers):
    original = api.get(f"/users/{user['id']}", headers=auth_headers).json()

    api.patch(f"/users/{user['id']}", json={"name": "New Name"}, headers=auth_headers)
    updated = api.get(f"/users/{user['id']}", headers=auth_headers).json()

    assert updated["name"] == "New Name"
    assert updated["email"] == original["email"]  # untouched


def test_delete_twice_is_handled_consistently(api, user, auth_headers):
    first = api.delete(f"/users/{user['id']}", headers=auth_headers)
    second = api.delete(f"/users/{user['id']}", headers=auth_headers)

    assert first.status_code in (200, 204)
    assert second.status_code == 404  # or 204, but be consistent and document it
```

I also test the verbs that *shouldn't* work. Sending `DELETE` to a read-only collection should return `405 Method Not Allowed` with a proper `Allow` header, not a `500` or, worse, a successful deletion.

---

## 3. Keep Tests Independent

**The principle:** Every test must be able to run alone, in any order, in parallel, and produce the same result.

A test that relies on data left behind by another test creates a hidden dependency chain. It passes when run in sequence and fails randomly when the order changes, when one test is skipped, or when the suite runs in parallel. These are the flaky failures that erode a team's trust in the test suite, until people start ignoring red builds.

**Anti-pattern**

```python
# test_1 creates a user, test_2 assumes it exists. Fragile.
def test_1_create_user(api):
    api.post("/users", json={"email": "shared@example.com"})

def test_2_update_user(api):
    api.put("/users/shared@example.com", json={"name": "Updated"})  # breaks if test_1 didn't run
```

**Better: each test owns its data**

```python
import uuid
import pytest

@pytest.fixture
def user(api, auth_headers):
    unique = uuid.uuid4().hex[:8]
    resp = api.post(
        "/users",
        json={"email": f"test-{unique}@example.com", "name": "Test User"},
        headers=auth_headers,
    )
    assert resp.status_code == 201
    return resp.json()

def test_update_user(api, user, auth_headers):
    resp = api.put(f"/users/{user['id']}", json={"name": "Updated"}, headers=auth_headers)
    assert resp.status_code == 200
```

**Habits that keep tests independent**

- **Generate unique data** (UUIDs, timestamps) so parallel tests never collide on unique constraints.
- **Never assume global state**, such as "the database has exactly 3 users."
- **Randomize test order** with `pytest-randomly` to surface hidden coupling early.
- **Run in parallel** with `pytest-xdist`. If the suite survives `pytest -n auto`, it's probably well isolated.

```bash
pip install pytest-randomly pytest-xdist
pytest -n auto
```

---

## 4. Clean Up After Myself

**The principle:** Whatever a test creates, the test removes, and it does so *even when the test fails*.

Leftover data causes slow, creeping damage: unique-constraint collisions on the next run, polluted staging environments, inflated list endpoints, and confusing results for other people using the same environment. Cleanup that only runs on success is barely cleanup at all, since failing tests are exactly the ones that leave a mess.

**Fixtures with teardown are the cleanest approach**

Everything after `yield` runs whether the test passed or failed:

```python
@pytest.fixture
def user(api, admin_headers):
    resp = api.post("/users", json=make_user_payload(), headers=admin_headers)
    assert resp.status_code == 201
    created = resp.json()

    yield created  # the test runs here

    # Teardown: runs even if the test raised an exception
    api.delete(f"/users/{created['id']}", headers=admin_headers)
```

**When you can't use a fixture, use `try/finally`**

```python
def test_order_lifecycle(api, headers):
    order_id = None
    try:
        resp = api.post("/orders", json=order_payload(), headers=headers)
        order_id = resp.json()["id"]
        assert resp.status_code == 201
        # ... more assertions ...
    finally:
        if order_id:
            api.delete(f"/orders/{order_id}", headers=headers)
```

**Extra safeguards**

- **Make cleanup tolerant.** Teardown shouldn't fail if the resource is already gone (a `404` on delete is fine there).
- **Tag test data** (for example, an email domain like `@test.invalid` or a name prefix like `autotest_`) so you can run a periodic sweeper job that removes stragglers from crashed runs.
- **Prefer disposable environments** (containers, ephemeral databases) when possible, and treat per-test cleanup as the second line of defense.

---

## 5. Use Realistic Test Data

**The principle:** If your test data looks nothing like production data, your tests only prove the code works for data that will never exist.

Test data like `"John"`, `"Doe"`, `"test@test.com"`, and `123` is tidy and dangerous. Real users have names with apostrophes, accents, emoji, and no spaces at all. Real payloads contain long strings, empty strings, and unexpected nulls. Bugs love these.

**What "realistic" means**

| Category | Examples worth testing |
|---|---|
| **Unicode** | `José`, `Zoë`, `Łukasz`, `李雷`, `محمد`, `🎉` |
| **Special characters** | `O'Brien`, `Smith-Jones`, `a+tag@example.com`, `"quoted"`, `<script>` |
| **Length extremes** | 1 character, exactly at the max limit, max + 1, 10,000 characters |
| **Whitespace** | Leading/trailing spaces, tabs, newlines, empty string, only spaces |
| **Numbers** | `0`, negatives, very large values, decimals with many places, floats vs. integers |
| **Dates & time** | Leap days, timezone boundaries, DST changes, far-future and far-past dates |
| **Nulls & absence** | Missing field vs. `null` vs. empty (three different situations) |

**Generate variety instead of hand-writing it**

```python
from faker import Faker
import pytest

fake = Faker(["en_US", "de_DE", "ja_JP", "ar_SA", "pl_PL"])

def make_user_payload():
    return {
        "name": fake.name(),
        "email": fake.unique.email(),
        "address": fake.address(),
        "bio": fake.text(max_nb_chars=500),
    }

@pytest.mark.parametrize("name", [
    "José García",
    "O'Brien",
    "李雷",
    "a" * 255,          # exactly at the limit
    "Robert'); DROP TABLE users;--",
    "  padded  ",
])
def test_create_user_accepts_real_world_names(api, admin_headers, name):
    payload = {**make_user_payload(), "name": name}
    resp = api.post("/users", json=payload, headers=admin_headers)
    assert resp.status_code in (201, 400)  # either accepted or cleanly rejected, never a 500
    assert resp.status_code != 500
```

If you can, use **anonymized samples of production data** to shape your generators. Nothing exposes bad assumptions faster than the actual distribution of what users send you.

---

## 6. Automate Security Checks

**The principle:** Security regressions are easy for humans to miss and trivial for machines to catch, so encode the checks once and run them on every build.

A code reviewer reading a diff sees the code that was *added*. A missing `@require_auth` decorator is code that was *never written*, which makes it nearly invisible in review. A test, however, will fail every single time.

**The checks I automate on every project**

**a) Authentication: every protected endpoint rejects anonymous callers**

```python
PROTECTED_ENDPOINTS = [
    ("GET", "/users"),
    ("GET", "/users/1"),
    ("POST", "/orders"),
    ("DELETE", "/users/1"),
    ("PATCH", "/account/settings"),
]

@pytest.mark.parametrize("method,path", PROTECTED_ENDPOINTS)
def test_requires_authentication(api, method, path):
    resp = api.request(method, path)  # no auth header
    assert resp.status_code == 401
```

Better still, generate this list from your OpenAPI spec so a newly added endpoint is covered automatically.

**b) Authorization (BOLA/IDOR): user A can't touch user B's data**

This is the most common serious API vulnerability, and functional tests rarely catch it.

```python
def test_user_cannot_read_another_users_order(api, user_a_headers, user_b_order):
    resp = api.get(f"/orders/{user_b_order['id']}", headers=user_a_headers)
    assert resp.status_code in (403, 404)
```

**c) Token handling**

```python
def test_expired_token_is_rejected(api, expired_token):
    resp = api.get("/users/me", headers={"Authorization": f"Bearer {expired_token}"})
    assert resp.status_code == 401

def test_tampered_token_is_rejected(api, valid_token):
    tampered = valid_token[:-4] + "abcd"
    resp = api.get("/users/me", headers={"Authorization": f"Bearer {tampered}"})
    assert resp.status_code == 401
```

**d) Privilege escalation and mass assignment**

```python
def test_user_cannot_make_themselves_admin(api, user, user_headers):
    resp = api.patch(f"/users/{user['id']}", json={"role": "admin"}, headers=user_headers)
    me = api.get("/users/me", headers=user_headers).json()
    assert me["role"] != "admin"
```

**e) Information leakage**

- Error responses shouldn't include stack traces, SQL fragments, or internal hostnames.
- Responses shouldn't return sensitive fields (`password_hash`, internal IDs, tokens).
- Login errors should not reveal whether the username or the password was wrong.

**f) Add tooling on top of your own tests**

Run a scanner such as OWASP ZAP in CI against a test environment, and dependency scanning for known-vulnerable libraries. Your hand-written tests cover *your* business logic, and the tools cover the generic, well-known problems.

---

## 7. Version My Contracts

**The principle:** An API is a promise to its consumers. Breaking that promise should be a *decision*, not an accident.

Consumers (mobile apps, partner integrations, other teams' services) build against your API's shape. If you rename a field, change a type, or make an optional parameter required, you can break them, often without a single one of your own tests failing.

**What counts as a breaking change**

| Breaking | Usually safe |
|---|---|
| Removing or renaming a field | Adding a new optional response field |
| Changing a field's type (`int` → `string`) | Adding a new endpoint |
| Making an optional request field required | Adding a new optional request parameter |
| Changing status codes or error formats | Relaxing validation |
| Tightening validation | Adding new enum values (*if* clients are tolerant readers) |

**How I make change deliberate**

**a) Treat the OpenAPI spec as a first-class artifact**

Keep it in version control and review changes to it like code.

**b) Diff the spec in CI and fail on breaking changes**

```bash
# Compare the spec in the PR against the main branch
oasdiff breaking main-openapi.yaml pr-openapi.yaml --fail-on ERR
```

If the build goes red, the author must either revert the change or consciously acknowledge it as a breaking release.

**c) Validate responses against the schema in tests**

This catches the case where the code and the documentation have drifted apart:

```python
from jsonschema import validate

def test_get_user_matches_contract(api, user, auth_headers, openapi_schemas):
    resp = api.get(f"/users/{user['id']}", headers=auth_headers)
    validate(instance=resp.json(), schema=openapi_schemas["User"])
```

**d) Use consumer-driven contract testing where it fits**

With tools like **Pact**, each consumer publishes what it actually relies on, and the provider verifies it can still satisfy every consumer before releasing.

**e) Have a versioning and deprecation policy**

- Version the API (`/v1/`, `/v2/`, or header-based) when a breaking change is unavoidable.
- Announce deprecations with a timeline, and use `Deprecation` and `Sunset` headers.
- Keep the old version running through the migration window.

---

## Putting It All Together

If I had to compress everything into a checklist for a new project:

- [ ] Endpoints are tiered by risk, and critical tests run on every commit
- [ ] Every verb on every resource has at least one test, including verbs that should be rejected
- [ ] Tests generate their own data and pass in random order and in parallel
- [ ] Every created resource is cleaned up through fixture teardown or `try/finally`
- [ ] Test data includes unicode, boundaries, and awkward real-world shapes
- [ ] Auth, authorization, and token tests run automatically on every build
- [ ] The OpenAPI spec is versioned, diffed in CI, and validated against real responses

None of these practices is glamorous, and that's the point. Good API testing is mostly consistency: the boring habits, applied every time, are what stop the 2 a.m. incidents.
