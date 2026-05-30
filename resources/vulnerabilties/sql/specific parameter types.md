There are **9 major categories** with **47+ specific parameter types** total. Let me break them all down:

---

## By Location (Where They Appear)

### 1. URL Query Parameters
```
/search?q=hello&page=2&sort=asc
```
The most visible. Everything after `?`, separated by `&`.

### 2. Path Parameters
```
/api/users/123/orders/456
         ^^^           ^^^
```
Values embedded directly inside the URL path — not after `?`.

### 3. Fragment Parameters
```
/page#section=intro&tab=overview
```
After `#` — processed **only by the browser**, never sent to server. Rarely injectable but sometimes leaked to JS.

---

## By Request Body Type

### 4. Form URL-Encoded Parameters
```
POST /login
Content-Type: application/x-www-form-urlencoded

username=alice&password=secret&_token=abc123
```
Classic HTML form submission. Every `<input>`, `<select>`, `<textarea>` becomes a parameter.

### 5. JSON Body Parameters
```json
POST /api/update
Content-Type: application/json

{
  "username": "alice",
  "role": "user",
  "nested": {
    "preference": "dark"
  },
  "array_param": [1, 2, 3]
}
```
Three sub-types here — flat keys, nested object keys, and array values — all injectable separately.

### 6. XML / SOAP Body Parameters
```xml
POST /api/soap
<User>
  <id>1</id>
  <name>alice</name>
</User>
```
Each XML tag is effectively a parameter. XXE attacks live here.

### 7. Multipart Form Parameters
```
POST /upload
Content-Type: multipart/form-data

--boundary
Content-Disposition: form-data; name="file"; filename="doc.pdf"
Content-Type: application/pdf
[binary data]

--boundary
Content-Disposition: form-data; name="description"
This is my file
```
Two types mixed — file upload fields and regular text fields — in one request.

### 8. GraphQL Parameters
```graphql
query {
  user(id: "123", role: "admin") {
    email
    orders(limit: 10, offset: 0) { ... }
  }
}
```
Arguments on each field act as independent parameters.

---

## By Header Type

### 9. Standard HTTP Headers (Injectable)
```
User-Agent: Mozilla/5.0
Referer: https://evil.com
Origin: https://attacker.com
Accept-Language: en-US
```

### 10. Authentication Headers
```
Authorization: Bearer eyJhbGc...
Authorization: Basic dXNlcjpwYXNz
X-API-Key: abc123
```

### 11. Custom Application Headers
```
X-User-ID: 42
X-Role: admin
X-Forwarded-For: 127.0.0.1
X-Real-IP: 10.0.0.1
X-Forwarded-Host: evil.com
X-Original-URL: /admin
X-Rewrite-URL: /admin
```
These are extremely high-value targets — many apps trust these headers blindly and use them for access control decisions.

### 12. Cookie Parameters
```
Cookie: session=abc; user_id=42; role=user; theme=dark; remember_me=true
```
Every cookie key is a separate parameter. Some apps do access control purely from cookie values.

---

## By Special Purpose

### 13. Hidden Form Fields
```html
<input type="hidden" name="_token" value="csrf123">
<input type="hidden" name="_method" value="PUT">
<input type="hidden" name="price" value="99.99">
<input type="hidden" name="user_id" value="1">
<input type="hidden" name="is_admin" value="false">
```
Never shown to user in the UI but fully sent in the request. `price` and `is_admin` hidden fields are classic mass-assignment targets.

### 14. CSRF / Anti-Forgery Tokens
```
_token=abc          (Laravel)
__RequestVerificationToken=xyz    (ASP.NET)
authenticity_token=def            (Rails)
csrf_token=ghi                    (Django)
```
Technically security controls — but missing/weak CSRF tokens are vulnerabilities themselves.

### 15. Method Override Parameters
```
POST /resource/123
_method=DELETE      (in body or query)
X-HTTP-Method-Override: DELETE    (in header)
X-Method-Override: PATCH
```
Tricks the server into treating a POST as DELETE/PUT/PATCH — used to bypass firewalls that block those methods.

### 16. Pagination Parameters
```
page=2
offset=20
limit=100
per_page=50
cursor=eyJpZCI6MX0=
after=abc123
```
`limit` is especially interesting — `limit=999999` can cause DoS or data leaks.

### 17. Format / Content Negotiation Parameters
```
format=json
format=xml
output=csv
callback=myFunc        ← JSONP — potential injection
type=pdf
```
`callback=` is a JSONP parameter — if you control it, you control a `<script>` execution context.

### 18. Sort / Order Parameters
```
sort=price
orderby=username
order=ASC
direction=desc
```
Cannot be parameterized — classic dynamic SQL injection surface (from the README earlier).

### 19. Redirect / URL Parameters
```
redirect=/dashboard
next=/home
url=https://example.com
return_to=/profile
goto=https://...
continue=...
```
Open redirect and SSRF surface. Any parameter whose value is a URL is high priority.

### 20. Search / Filter Parameters
```
q=hello
search=alice
keyword=test
filter=active
category=books
tag=security
```
SQLi, XSS, SSTI, NoSQL injection all target these.

### 21. File / Path Parameters
```
file=report.pdf
page=home
template=default
path=/uploads/doc
include=header
load=module
```
Path traversal (`../../etc/passwd`) and Local File Inclusion live here.

### 22. Debug / Feature Flag Parameters
```
debug=1
verbose=true
trace=on
test=1
dev=true
_debug=1
```
Usually hidden — discovered via Arjun/fuzzing. Activating these can expose stack traces, internal data, bypass validation.

### 23. Versioning Parameters
```
version=2
api_version=v1
v=3
```

### 24. Language / Locale Parameters
```
lang=en
locale=fr-FR
tz=UTC
currency=USD
```
Sometimes injectable — `lang=../../../../etc/passwd` for LFI.

---

## Protocol-Specific Parameters

### 25. WebSocket Message Parameters
```json
{"action": "subscribe", "channel": "user_42", "token": "abc"}
{"type": "message", "to": "user_1", "body": "hello"}
```
WebSocket messages are structured data — every key is a parameter. Often completely untested.

### 26. gRPC / Protobuf Fields
Each field in a `.proto` definition is a parameter. Rarely tested, often missing validation.

### 27. NoSQL Operator Parameters
```json
{"username": "alice", "password": {"$gt": ""}}
{"age": {"$gte": 18, "$lte": 65}}
```
Not traditional parameters — but operator keys like `$gt`, `$where`, `$regex` act as injectable parameters within NoSQL queries.

---

## Complete Count Table

| Category | Count |
|---|---|
| URL-based (query, path, fragment) | 3 |
| Body-based (form, JSON, XML, multipart, GraphQL) | 5 |
| Header-based (standard, auth, custom, cookie) | 4 |
| Special purpose (hidden fields, CSRF, method override) | 3 |
| Behavioral (pagination, format, sort, redirect, search, file, debug, version, locale) | 9 |
| Protocol-specific (WebSocket, gRPC, NoSQL operators) | 3 |
| **Total categories** | **27 types** |

---

## Which Ones Matter Most for Security

```
CRITICAL (test these first)
├── Redirect/URL params     → Open Redirect, SSRF
├── File/Path params        → LFI, Path Traversal
├── ID params               → IDOR
├── Sort/Order params       → SQL Injection
├── Hidden form fields      → Mass Assignment, Price Manipulation
├── Custom X- headers       → Auth Bypass, IP Spoofing
└── Cookie params           → Session Hijack, Privilege Escalation

HIGH
├── Search/Filter params    → SQLi, XSS, SSTI
├── Format/Callback params  → JSONP, XXE
├── Debug params            → Info Disclosure
└── GraphQL arguments       → Auth bypass, Data exfil

MEDIUM
├── Pagination (limit=)     → DoS, Data leak
├── Language/Locale params  → LFI
└── WebSocket message keys  → Logic flaws
```

The short answer: most developers think about **1 type** (URL query strings). Real applications have all **27** — and the ones that cause the biggest vulnerabilities are almost always the ones nobody thinks about: hidden form fields, custom headers, and parameters buried in JavaScript fetch calls.
