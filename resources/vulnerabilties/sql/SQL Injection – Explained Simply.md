## SQL Injection – Explained Simply

---

### The Core Problem

When a web application builds an SQL query using user input, it just **pastes** whatever the user typed directly into the SQL code — without checking *what* it actually says. The running program doesn't think "wait, is this safe?" — it just executes it.

---

### Normal Example (How it's supposed to work)

Imagine a login form. The app builds this query behind the scenes:

```sql
SELECT * FROM users WHERE username = 'alice' AND password = 'mypassword';
```

The app checks: *did this return a user? Yes → log them in.*

This works fine when users type normal values.

---

### Malicious Example (SQL Injection Attack)

Now a hacker types this into the **username field**:

```
' OR '1'='1
```

The app blindly pastes it in, producing:

```sql
SELECT * FROM users WHERE username = '' OR '1'='1' AND password = '';
```

Since `'1'='1'` is **always true**, this returns **all users** — and the attacker logs in **without knowing any password**.

---

### Even Worse — Dropping a Table

An attacker types this into a search box:

```
'; DROP TABLE users; --
```

The app produces:

```sql
SELECT * FROM products WHERE name = ''; DROP TABLE users; --';
```

This **deletes the entire users table**. The `--` at the end comments out the rest of the original query so it doesn't cause a syntax error.

---

### Why Does This Happen?

| What the app assumes | What actually happens |
|---|---|
| User input is just *data* | Input can contain SQL *commands* |
| The query is fixed | The query structure gets changed |
| The DB only does what's intended | The DB does what the attacker says |

---

### How to Prevent It

The fix is to use **Prepared Statements / Parameterized Queries**, which separate the *code* from the *data*:

```python
# ❌ Vulnerable
query = "SELECT * FROM users WHERE username = '" + username + "'"

# ✅ Safe – user input is treated as pure data, never as code
cursor.execute("SELECT * FROM users WHERE username = ?", (username,))
```

With prepared statements, even if a user types `' OR '1'='1`, it's treated as a **literal string**, not SQL logic — so the attack fails completely.

