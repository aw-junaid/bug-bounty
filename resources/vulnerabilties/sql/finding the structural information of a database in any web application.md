## Phase 1 — Fingerprint the database engine

Before enumerating structure, you need to know *what* you're dealing with.

**Passive signals to look for:**
- HTTP response headers: `X-Powered-By`, `Server`, `Set-Cookie` (e.g. `PHPSESSID` → PHP/MySQL)
- Default error pages that mention the DB engine or version
- URL patterns: `.aspx` → MSSQL likely, `.php` → MySQL/MariaDB common, `/api/graphql` → GraphQL layer

**Tools:** Wappalyzer, BuiltWith, `curl -I <url>` for headers

---

## Phase 2 — Passive reconnaissance

**GraphQL introspection** is the most powerful passive method. If the app exposes a GraphQL endpoint, send:
```
{ __schema { types { name fields { name } } } }
```
This legally returns the *entire* schema — all types, fields, and relationships — with no exploitation needed.

**Swagger / OpenAPI docs** at paths like `/api-docs`, `/swagger.json`, or `/openapi.yaml` often expose model names that directly reflect DB table/column structure.

**Traffic interception** with Burp Suite or browser DevTools → Network tab. Watch XHR/fetch requests: parameter names like `user_id`, `order_table`, or `product_category` in API calls reveal table and column names directly.

**Google dorks** to find exposed admin panels or backup files:
```
site:example.com filetype:sql
site:example.com inurl:phpmyadmin
site:example.com "DB_PASSWORD"
```

---

## Phase 3 — Error-based enumeration

Send intentionally malformed input (a single quote `'`, type mismatches, out-of-range values) to fields and observe error messages. Verbose DB errors often leak:
- The database engine and version
- Table and column names referenced in the query
- The partial SQL query itself

Example: submitting `' OR 1=1--` in a login field. If unhandled, the error may show the underlying query structure.

---

## Phase 4 — Active enumeration (authorized only)

**SQL injection — `UNION SELECT` method:**
Once you confirm SQLi, use `UNION SELECT` to query `information_schema`:
```sql
' UNION SELECT table_name,2 FROM information_schema.tables--
' UNION SELECT column_name,2 FROM information_schema.columns WHERE table_name='users'--
```

**Blind SQLi** (when there are no visible errors): use boolean conditions (`AND 1=1` vs `AND 1=2`) or time delays (`SLEEP(5)`, `WAITFOR DELAY`) to extract data bit by bit.

**NoSQL injection:** MongoDB is vulnerable to operator injection:
```json
{"username": {"$gt": ""}, "password": {"$gt": ""}}
```
This can bypass auth and expose collection contents.

**GraphQL abuse:** Try deep query nesting to cause DoS or data leaks, disable introspection checks, or abuse batch queries.

---

## Phase 5 — Automated tools

| Tool | Best for |
|---|---|
| `sqlmap -u "url" --dbs` | Full auto SQLi — dumps schema, tables, data |
| Burp Suite Pro | Active scanning, parameter fuzzing, extension ecosystem |
| Nuclei | Template-based scanning for known CVEs and misconfigs |
| FFUF / DirBuster | Finding exposed admin panels, backup `.sql` files |
| InQL (Burp plugin) | GraphQL schema extraction and fuzzing |
| Shodan | Finding exposed DB ports (3306, 5432, 27017) on the internet |

---

## DB-specific system tables cheat sheet

| Engine | Schema query |
|---|---|
| MySQL/MariaDB | `SELECT * FROM information_schema.tables` |
| PostgreSQL | `SELECT * FROM pg_tables` / `\d tablename` in psql |
| MSSQL | `SELECT * FROM sys.tables` / `sys.columns` |
| Oracle | `SELECT * FROM all_tables` |
| SQLite | `SELECT name FROM sqlite_master WHERE type='table'` |
| MongoDB | `db.getCollectionNames()` / `show collections` |
| Cassandra | `DESCRIBE tables` in cqlsh |

---

## Key principle

The most reliable workflow is: **passive first** (GraphQL introspection, Swagger, traffic analysis) → **error-based** → **active injection** (only with authorization). Many modern apps inadvertently expose their full schema through developer tools before any exploitation is needed.
