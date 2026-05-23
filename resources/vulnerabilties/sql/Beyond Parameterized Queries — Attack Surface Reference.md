# Beyond Parameterized Queries — Attack Surface Reference
### For Penetration Testers, Bug Bounty Hunters & Defenders

> **Legal notice:** This document is for authorized security testing and defense only.
> Unauthorized testing of systems is illegal worldwide. Always have written permission.
> CVEs listed are public, disclosed, and patched — referenced for education and defense.

---

## The Honest Truth About Parameterized Queries

Parameterized queries (prepared statements) are **genuinely very strong** protection
specifically against SQL injection. When correctly implemented, they are not bypassed
by payload tricks. Anyone claiming a payload can bypass correct parameterization
is either:

- Referring to a **misimplementation** (app uses params in some places, not others)
- Exploiting a **driver/ORM bug** (a real CVE in the database driver itself)
- Attacking a **different layer entirely** (not SQL injection anymore)
- Selling false promises

This document covers what the real remaining attack surface looks like.

---

## Table of Contents

1. [What Parameterized Queries Actually Protect](#1-what-they-protect)
2. [What They Do NOT Protect](#2-what-they-dont-protect)
3. [Misimplementation Patterns — Where It Still Breaks](#3-misimplementations)
4. [ORM-Layer Vulnerabilities — Bypasses via Framework Bugs](#4-orm-bugs)
5. [Known Public CVEs in Database Drivers & ORMs](#5-known-cves)
6. [Second-Order & Application Logic Attacks](#6-second-order)
7. [Adjacent Attack Surfaces — After SQL is Secured](#7-adjacent-surfaces)
8. [Database-Level Vulnerabilities (Not Injection)](#8-db-level)
9. [Defense Checklist — Full Stack](#9-defense-checklist)

---

## 1. What Parameterized Queries Actually Protect

When an app uses correct parameterization:

```python
# Python — correct
cursor.execute("SELECT * FROM users WHERE id = %s", (user_id,))

# Java — correct
PreparedStatement ps = conn.prepareStatement("SELECT * FROM users WHERE id = ?");
ps.setInt(1, userId);

# Node.js pg — correct
await pool.query("SELECT * FROM users WHERE id = $1", [userId]);
```

The database driver sends the **query structure** and **data values** separately.
The database parser sees the data as data — never as syntax.

**What this locks down:**
- Classic `' OR 1=1` tautology attacks
- UNION-based extraction via input fields
- Error-based extraction via input
- Time-based blind injection via input
- Stacked query injection via input
- Comment-strip attacks via input
- Encoding/WAF-bypass SQL injection via input

For these attack types, correct parameterization is essentially a hard wall.

---

## 2. What They Do NOT Protect

Parameterized queries only protect the **data values** passed to a fixed query.
They do not protect:

### 2.1 Dynamic Structural Elements

SQL structure elements cannot be parameterized in standard SQL:
- **Table names** — `SELECT * FROM ?` is not valid syntax
- **Column names** — `SELECT ? FROM users` treats column as string
- **ORDER BY direction** — `ORDER BY ? ASC` fails
- **Schema names** — cannot be a parameter

If an app dynamically builds these from user input even while using params for values,
those structural elements are injection points.

```python
# STILL VULNERABLE — table name is not parameterized
table = request.args.get('table')  # attacker controls this
cursor.execute(f"SELECT * FROM {table} WHERE id = %s", (id,))
# Payload: table = "users; DROP TABLE logs--"

# STILL VULNERABLE — ORDER BY is not parameterized
col = request.args.get('sort')
direction = request.args.get('dir')
cursor.execute(f"SELECT * FROM items ORDER BY {col} {direction}")
# Payload: col = "(SELECT SLEEP(5))"
```

**Correct defense for dynamic structure:**
```python
# Whitelist approach — only allow known safe values
ALLOWED_TABLES = {'products', 'categories', 'reviews'}
ALLOWED_COLUMNS = {'name', 'price', 'created_at'}
ALLOWED_DIRS = {'ASC', 'DESC'}

if table not in ALLOWED_TABLES:
    raise ValueError("Invalid table")
if col not in ALLOWED_COLUMNS:
    raise ValueError("Invalid column")
```

### 2.2 Second-Order Data Flow

Data stored safely in the DB via parameterized INSERT can later be read
and used *without parameterization* in another query path.

See Section 6 for full detail.

### 2.3 Stored Procedures With Dynamic SQL Inside

A stored procedure itself can contain dynamic SQL that concatenates its parameters:

```sql
-- MSSQL: Vulnerable stored procedure (param used inside dynamic SQL)
CREATE PROCEDURE SearchUsers @name NVARCHAR(100)
AS
  EXEC('SELECT * FROM users WHERE name = ''' + @name + '''')
  -- The EXEC() is building a string — injection point is inside the proc!
```

Calling this proc via parameterized call from the app doesn't help —
the injection happens inside the database itself.

**Secure version:**
```sql
CREATE PROCEDURE SearchUsers @name NVARCHAR(100)
AS
  SELECT * FROM users WHERE name = @name
  -- Direct parameterized use, no dynamic SQL
```

### 2.4 Full-Text Search & LIKE Wildcards

Even with parameterization, LIKE wildcards `%` and `_` may not be escaped:

```python
# Parameterized but LIKE wildcards still work
term = request.args.get('q')  # user enters: %
cursor.execute("SELECT * FROM docs WHERE title LIKE %s", (f"%{term}%",))
# term = "%" → matches ALL rows → information disclosure
# term = "a%" → matches all titles starting with 'a' → enumeration
# term = "___%" → matches titles 3+ chars → oracle on data length
```

**Defense:** Escape LIKE wildcards before parameterizing:
```python
safe_term = term.replace('\\','\\\\').replace('%','\\%').replace('_','\\_')
cursor.execute("SELECT * FROM docs WHERE title LIKE %s ESCAPE '\\\\'",
               (f"%{safe_term}%",))
```

---

## 3. Misimplementation Patterns — Where It Still Breaks

These are the most common real-world failures found in bug bounties:

### 3.1 Partial Parameterization

App uses params for most queries but falls back to concatenation
for specific features:

```javascript
// 99% of queries use parameterized — but search doesn't:
const results = await db.query(
  `SELECT * FROM products WHERE name LIKE '%${req.query.q}%'`
);  // ← direct injection here
```

**How to find:** Test every search field, autocomplete, filter, export,
and report-generation endpoint. These are often added quickly and
skipped in security reviews.

### 3.2 ORM .raw() / .execute() Escape Hatches

Developers use raw query methods when ORM query builders can't express
what they need:

```python
# Django — raw() with f-string (vulnerable)
User.objects.raw(f"SELECT * FROM auth_user WHERE username = '{name}'")

# SQLAlchemy — text() with concatenation (vulnerable)
db.execute(text(f"SELECT * FROM users WHERE name = '{name}'"))

# Sequelize — query() without replacements (vulnerable)
sequelize.query(`SELECT * FROM users WHERE id = ${req.params.id}`)
```

**How to find in code review:** Search codebase for:
- `.raw(`, `.execute(`, `.query(` combined with f-strings or + concatenation
- `f"SELECT`, `"SELECT " +`, `'SELECT ' +`
- `EXEC(`, `sp_executesql` with string building (in stored procs)

### 3.3 Logging, Audit Tables, and Debug Endpoints

User input often gets written to audit/log tables without proper
parameterization because "it's just logging":

```java
// Logs user actions — often written quickly, not reviewed
String sql = "INSERT INTO audit_log (action, user_input) " +
             "VALUES ('search', '" + userInput + "')";
stmt.execute(sql);  // ← injection into audit table
```

From audit table, data is later read by admin panel — second-order injection.

### 3.4 The N+1 Dynamic Query Loop

App builds per-row queries dynamically inside a loop:

```python
for item_id in user_provided_ids:  # user controls the list
    cursor.execute(f"SELECT * FROM items WHERE id = {item_id}")
    # Each iteration is a new concatenated query
```

---

## 4. ORM-Layer Vulnerabilities — Bypasses via Framework Bugs

### 4.1 Sequelize (Node.js) Operator Injection

**CVE:** Not a single CVE but a class of vulnerability in Sequelize < 4.x
and when `operatorsAliases` is enabled.

When the app does:
```javascript
User.findAll({ where: req.body })
// req.body = { "username": { "$like": "%" } }
```

The `$like` (or `[Op.like]`) becomes a SQL LIKE operator.
Attacker can inject Sequelize operators through JSON body.

**Payloads (JSON body):**
```json
{"username": {"$like": "%"}}      → dumps all users
{"id": {"$gt": 0}}                → all IDs > 0 = all records
{"$or": [{"admin": true}]}        → bypass role check
{"password": {"$ne": "x"}}        → not-equal = any password except "x"
```

**Defense:** Never pass raw request body to ORM `.find()` without
filtering allowed fields and operators. Use an explicit schema.

### 4.2 TypeORM Raw Query Interpolation

TypeORM's `query()` method does not parameterize by default:
```typescript
// Vulnerable
await getConnection().query(`SELECT * FROM user WHERE id = ${id}`);

// Safe
await getConnection().query("SELECT * FROM user WHERE id = $1", [id]);
```

### 4.3 Hibernate HQL Injection (Java)

HQL (Hibernate Query Language) is separate from SQL but has its own
injection class when string-concatenated:

```java
// Vulnerable HQL
String hql = "FROM User WHERE name = '" + name + "'";
Query q = session.createQuery(hql);

// Payload: ' OR '1'='1   (HQL tautology — same concept as SQL)
// HQL injection can expose entity data, bypass WHERE clauses
```

HQL injection is NOT SQL injection — it operates at the ORM layer —
but has similar impact.

**Safe:**
```java
Query q = session.createQuery("FROM User WHERE name = :name");
q.setParameter("name", name);
```

---

## 5. Known Public CVEs in Database Drivers & ORMs

These are all **disclosed, patched, and public**. Listed for:
- Understanding what historical driver bugs looked like
- Checking if legacy systems are unpatched
- Defensive awareness

| CVE | Component | Description | CVSS |
|-----|-----------|-------------|------|
| CVE-2022-21724 | PostgreSQL JDBC driver | SQL injection via connection property `sslmode` and `preferQueryMode=simple` — bypasses parameterization in specific mode | 9.8 |
| CVE-2021-42392 | H2 Database console | JNDI injection via JDBC URL — not SQL injection but RCE via database console | 9.8 |
| CVE-2020-13692 | PostgreSQL JDBC | XML External Entity (XXE) via JDBC4 ResultSet — data exfil via DB driver | 7.7 |
| CVE-2019-12415 | Apache POI (xlsx) | XXE when reading Excel files that interact with DB — adjacent vector |7.1 |
| CVE-2018-1058 | PostgreSQL | Search path injection — attacker-controlled schema shadows trusted functions | 8.8 |
| CVE-2017-7525 | Jackson (Java JSON) | Deserialization RCE often chained with DB access | 9.8 |
| CVE-2015-3456 | MySQL client library | Buffer overflow in MySQL client — not injection but driver-level | 8.8 |
| CVE-2012-5613 | MySQL 5.5 | Privilege escalation via symlink attack on data directory | 6.8 |

### CVE-2022-21724 Deep Dive (PostgreSQL JDBC)

This is the most relevant "bypasses parameterized queries" class CVE:

**What happened:** The PostgreSQL JDBC driver has a `preferQueryMode` option.
When set to `simple`, the driver does NOT use server-side prepared statements —
it falls back to client-side string substitution.

**Attack scenario:** If an attacker can influence the JDBC connection string
(e.g., via SSRF hitting an internal connection manager, or misconfigured
connection pool config loaded from user-controlled source), they can set:
```
jdbc:postgresql://host/db?preferQueryMode=simple
```
Now all "parameterized" queries in the app become string-concatenated
queries at the driver level — full SQL injection restored.

**Affected:** PostgreSQL JDBC < 42.3.2, 42.2.x < 42.2.25

**Fix:** Upgrade driver. Never load connection string from user-controlled input.

### CVE-2018-1058 — PostgreSQL Search Path Injection

**What happened:** PostgreSQL's `search_path` setting determines which schema
is searched first when an unqualified name (like `SELECT * FROM users`) is used.

If an attacker can CREATE objects in a non-privileged schema, and the app
runs queries without fully-qualified schema names, the attacker's schema
is searched first:

```sql
-- Attacker creates malicious function in their schema:
CREATE FUNCTION public.lower(text) RETURNS text AS
$$ BEGIN PERFORM pg_sleep(10); RETURN $1; END; $$ LANGUAGE plpgsql;

-- When app calls: SELECT lower(username) FROM users
-- PostgreSQL finds attacker's lower() first if search_path includes public
-- Result: time delay (or worse, data exfil via attacker-controlled function)
```

**This bypasses parameterized queries entirely** because the injection is
in the function resolution, not in the data values.

**Fix:** Always use fully-qualified schema names (`schema.tablename`).
Set `search_path` to a specific trusted schema per connection.
PostgreSQL 15+ changed defaults to restrict public schema creation.

---

## 6. Second-Order & Application Logic Attacks

### 6.1 Classic Second-Order

Data parameterized on write, concatenated on read:

```
Step 1: Register username: admin'--
        → INSERT safely stores: admin'--

Step 2: Password reset feature reads username from DB:
        → cursor.execute(f"UPDATE users SET password='{new}' WHERE username='{stored_name}'")
        → Stored name is read raw from DB and concatenated!
        → Executes: UPDATE users SET password='x' WHERE username='admin'--'
        → Changes ADMIN's password, not attacker's account
```

**Finding these:** Look for any two-step flows:
- Register then use account features
- Submit data then trigger processing/export/email
- Upload file then have it processed by another service

### 6.2 Template Injection Into Database-Driven Emails

```
User sets "display name" to: {{7*7}}
App stores safely via parameterized query.
Later, email template engine reads display name from DB:
  "Hello {{user.display_name}}, your order..."
Template renders: "Hello 49, your order..."
→ Server-Side Template Injection (SSTI) — not SQL but equivalent severity
```

### 6.3 Business Logic Bypasses

When SQL injection is closed, attackers pivot to logic:

- **Mass assignment:** API accepts `{"role":"admin"}` and ORM maps it
- **IDOR:** `/api/orders/12345` — change to `/api/orders/12346` (other user's data)
- **Race conditions:** Two concurrent requests exploit non-atomic DB operations
- **Integer overflow:** Negative balances, wrap-around in financial logic

---

## 7. Adjacent Attack Surfaces — After SQL Is Secured

When the SQL layer is hardened, skilled attackers move to these:

### 7.1 NoSQL Injection (MongoDB, Redis, Elasticsearch)

If the stack uses NoSQL alongside SQL:

```javascript
// MongoDB — operator injection
db.users.find({ username: req.body.username })
// Body: { "username": { "$gt": "" } }  → matches all users

// Redis — command injection via RESP protocol
// If app builds Redis commands by concatenation:
redis.send_command(`SET session:${userId} ${data}`)
// userId = "x\r\nDEL sessions:admin" → CRLF injection into Redis protocol
```

### 7.2 XML External Entity (XXE)

If app parses user-supplied XML (even indirectly via file upload):

```xml
<?xml version="1.0"?>
<!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<request><data>&xxe;</data></request>
```

Can read server files even when SQL is fully secured.

### 7.3 Server-Side Request Forgery (SSRF)

```
POST /api/fetch-preview
{"url": "http://169.254.169.254/latest/meta-data/"}
```

Hits internal services, cloud metadata endpoints, internal databases
not exposed to internet. Often leads to credential theft.

### 7.4 Deserialization Attacks

Java, .NET, PHP, Python apps that deserialize user-provided data:

- Java: `ObjectInputStream` with gadget chains → RCE
- PHP: `unserialize()` with magic methods → RCE
- Python: `pickle.loads()` → RCE
- .NET: `BinaryFormatter` → RCE

These bypass all SQL protections because they attack the runtime, not the DB.

### 7.5 GraphQL Introspection & Batching

```graphql
# Introspection — maps entire schema
{ __schema { types { name fields { name type { name } } } } }

# Batching DoS — one HTTP request, thousands of DB queries
[{"query":"{ user(id:1){name}}" },
 {"query":"{ user(id:2){name}}" },
 ...1000 more]
```

---

## 8. Database-Level Vulnerabilities (Not Injection)

### 8.1 Privilege Escalation Inside the DB

If attacker gets low-privilege DB access (via leaked credentials, not injection):

```sql
-- MySQL: UDF privilege escalation
-- If attacker can write to plugin dir, they can load a malicious shared library
CREATE FUNCTION sys_exec RETURNS INT SONAME 'lib_mysqludf_sys.so';
SELECT sys_exec('id');

-- PostgreSQL: SECURITY DEFINER function abuse
-- A function with elevated privileges that can be called by lower-privilege user
CREATE FUNCTION admin_op() RETURNS void SECURITY DEFINER AS ...
-- If the function body is exploitable, low-priv user escalates
```

### 8.2 Credential Stuffing the DB Port Directly

Many databases are exposed on default ports:
- MySQL: 3306
- PostgreSQL: 5432
- MSSQL: 1433
- MongoDB: 27017
- Redis: 6379 (often no auth)

Automated scanning + credential stuffing bypasses the application entirely.

**Defense:** Database should NEVER be reachable from the internet.
Bind to localhost or private network only.

### 8.3 Configuration Weaknesses

| Setting | Risk | Database |
|---------|------|----------|
| `secure_file_priv=''` | Allows LOAD_FILE / INTO OUTFILE anywhere | MySQL |
| `xp_cmdshell` enabled | OS command execution | MSSQL |
| `log_statement=all` | Sensitive data in logs | PostgreSQL |
| `trust` auth in pg_hba.conf | No password for local connections | PostgreSQL |
| Anonymous auth | No password for any connection | Redis < 6 default |
| Default `sa` password | Full MSSQL access | MSSQL |

---

## 9. Defense Checklist — Full Stack

### Code Level
- [ ] Parameterized queries for ALL inputs — no exceptions
- [ ] Whitelist validation for any structural SQL element (table, column, schema)
- [ ] Escape LIKE wildcards before parameterizing LIKE patterns
- [ ] Audit all ORM `.raw()`, `.execute()`, `.query()` calls in codebase
- [ ] Treat data from YOUR OWN DATABASE as untrusted when re-using in queries
- [ ] No dynamic SQL inside stored procedures
- [ ] Validate operator/field names in API filter parameters (Sequelize $op etc.)

### Application Level
- [ ] Input validation (type, length, format) before it reaches DB layer
- [ ] Output encoding appropriate to context (HTML, JSON, SQL are different)
- [ ] Rate limiting on all query-triggering endpoints
- [ ] Request body schema validation (block unknown fields/operators)
- [ ] Disable debug/stack traces in production responses

### Database Level
- [ ] App DB user has ONLY SELECT/INSERT/UPDATE/DELETE it needs
- [ ] No app user has FILE, SUPER, EXECUTE on xp_cmdshell
- [ ] Database not accessible from internet (firewall/security group)
- [ ] Encrypted connections (TLS between app and DB)
- [ ] Database audit logging enabled and monitored
- [ ] Fully-qualified schema names in all queries (prevents search_path abuse)
- [ ] Regular credential rotation
- [ ] `secure_file_priv` set to a specific safe path or NULL (MySQL)

### Infrastructure Level
- [ ] Database driver and ORM versions up to date (CVE patching)
- [ ] Web Application Firewall as supplementary layer
- [ ] Intrusion detection on unusual query patterns
- [ ] Secrets in environment variables, not in code or config files
- [ ] Network segmentation: app server → DB server only (not DB → internet)

### Testing
- [ ] Run sqlmap with `--level=5 --risk=3` on all endpoints (authorized)
- [ ] Test second-order flows: register with payloads, exhaust all features
- [ ] Test dynamic structural elements (sort, filter, table selection)
- [ ] Review all ORM raw query usage in code
- [ ] Check database driver versions against CVE databases

---

## Summary

| Attack Vector | Blocked by Params? | Requires |
|---------------|-------------------|----------|
| Classic SQL injection | ✓ Yes | Nothing — parameterization closes it |
| Dynamic table/column injection | ✗ No | Whitelist validation |
| ORM operator injection | ✗ No | Request schema validation |
| Second-order injection | ✗ No | Treat DB data as untrusted too |
| Stored proc dynamic SQL | ✗ No | Fix procedure, not just app |
| LIKE wildcard abuse | ✗ Partial | Escape wildcards before binding |
| DB driver CVE (e.g. CVE-2022-21724) | ✗ No | Patch driver |
| Search path injection (CVE-2018-1058) | ✗ No | Qualify schema names |
| Credential stuffing DB port | ✗ No | Network firewall |
| NoSQL injection (parallel stack) | ✗ No | NoSQL input validation |
| SSRF to internal DB | ✗ No | SSRF protection |

**Bottom line:** Parameterized queries solve classical SQL injection well.
Real-world vulnerabilities in hardened systems come from misimplementation,
adjacent attack surfaces, driver CVEs, and second-order flows — not from
magic payloads that break correct parameterization.

---

*CVE data sourced from nvd.nist.gov — all public and disclosed.*
*For current CVEs: https://cve.mitre.org and https://snyk.io/vuln*
*Authorized testing resources: HackTheBox, TryHackMe, PortSwigger Web Academy*
