## The Core Problem — String Concatenation

The entire vulnerability comes down to **one bad habit** — building queries by gluing strings together:

```python
# Developer writes this innocent-looking code
username = input("Enter username: ")
query = "SELECT * FROM users WHERE username = '" + username + "'"
```

The developer **imagines** the user types: `alice`

So the query becomes:
```sql
SELECT * FROM users WHERE username = 'alice'   ✅ fine
```

But the app has **no idea** what the user actually types. It just pastes it in blindly.

---

## Exactly How the Attacker Inserts a Command

### Step 1 — The attacker understands your query structure

They see a login form. They know the app probably runs:
```sql
SELECT * FROM users WHERE username = '[INPUT]' AND password = '[INPUT]'
```

### Step 2 — They use a quote to **escape the string context**

The single quote `'` is the key. It **closes** the string the developer opened:

```
Developer opened a string here → '
                                  ↓
Query:  WHERE username = '  alice  '
                         ↑         ↑
                    dev opens   dev closes
```

Now the attacker types `alice'` — their quote **closes the string early**:

```
Query:  WHERE username = 'alice'   ← string closed by attacker!
                                ↑
                        attacker's quote closes the string here
                        everything after this is now RAW SQL CODE
```

### Step 3 — They inject a command in the leftover space

Attacker types: `alice' OR '1'='1`

```
Query:  WHERE username = 'alice'  OR  '1'='1'
                         [data]  [←  SQL CODE  →]
```

The database sees two separate things:
- `'alice'` → the string (data)
- `OR '1'='1'` → a real SQL command it executes

---The interactive stepper above walks through each attack character by character. Here's the deeper explanation for each mechanism:

---

## The 4 Weapons an Attacker Uses

### 1. The Single Quote `'` — the master key

This is the core weapon. Every SQL string is wrapped in quotes by the developer. The attacker simply **provides their own closing quote** to break out of the string context:

```
Developer's string:   '  alice  '
                       ↑         ↑
                    opens       closes

Attacker breaks it:   '  '  OR 1=1
                       ↑  ↑
                    dev   attacker's quote
                    opens  closes string early → rest becomes SQL code
```

### 2. The Double Dash `--` — the silencer

After injecting their command, the attacker needs to neutralize whatever SQL the developer wrote after the injection point. `--` comments out everything that follows:

```sql
-- Developer wrote this:
WHERE username = '[input]' AND password = '[input]'

-- After injection with admin'--
WHERE username = 'admin'-- AND password = '...'
--                          ↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
--                          all of this is now ignored
```

### 3. The Semicolon `;` — the command separator

Lets the attacker end the current query and start a completely new one:

```sql
-- Attacker types: '; DROP TABLE users;--
SELECT * FROM products WHERE name = ''; DROP TABLE users;--'
--                                      ↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
--                                      brand new query executes
```

### 4. `UNION SELECT` — the data thief

Attaches a second SELECT to the developer's SELECT, pulling data from any other table in the database and returning it as if it were the original query's results.

---

## Why the App Cannot Tell the Difference

The root cause is that the database engine receives a **finished string of text** — it has no idea which parts were written by the developer and which parts were injected by the user. It simply parses and executes the whole thing:

```python
# The app builds this string and sends it as one piece:
"SELECT * FROM users WHERE username = '' OR '1'='1'"

# The DB has no memory of what was 'developer code' vs 'user input'
# It just sees valid SQL and runs it
```

This is exactly why parameterized queries fix the problem — they send the SQL structure and the data **separately**, so the database always knows which is which, no matter what the user types.
