
## Layer 1 — Parameterized Queries (most important)

```python
import mysql.connector

conn = mysql.connector.connect(host="localhost", user="root", password="pass", database="myapp")
cursor = conn.cursor()

# ❌ VULNERABLE — never do this
username = request.form['username']
query = "SELECT * FROM users WHERE username = '" + username + "'"

# ✅ SAFE — parameterized query
# The %s is a placeholder. MySQL treats user input as pure DATA, never as code.
cursor.execute("SELECT * FROM users WHERE username = %s AND password_hash = %s", (username, hashed_password))
user = cursor.fetchone()
```

---

## Layer 2 — Password Hashing with bcrypt

```python
import bcrypt

# When user REGISTERS — hash before saving
plain_password = "mypassword123"
hashed = bcrypt.hashpw(plain_password.encode('utf-8'), bcrypt.gensalt())
# Store `hashed` in DB — never the plain password

# When user LOGS IN — compare hash
def check_login(username, plain_password):
    cursor.execute("SELECT password_hash FROM users WHERE username = %s", (username,))
    row = cursor.fetchone()
    if row and bcrypt.checkpw(plain_password.encode('utf-8'), row[0]):
        return True   # ✅ Login success
    return False      # ❌ Wrong credentials
```

---

## Layer 3 — Input Sanitization & Validation

```python
import re

def sanitize_input(value):
    # Remove dangerous SQL characters
    value = value.strip()

    # Block SQL injection patterns
    sql_patterns = [r"('|\")", r"(--|#|/\*)", r"\b(OR|AND|UNION|SELECT|DROP|DELETE|INSERT)\b"]
    for pattern in sql_patterns:
        if re.search(pattern, value, re.IGNORECASE):
            raise ValueError("Malicious input detected")

    # Block XSS
    if re.search(r"<[^>]+>|javascript:|on\w+=", value, re.IGNORECASE):
        raise ValueError("Invalid characters in input")

    # Length limit
    if len(value) > 80:
        raise ValueError("Input too long")

    return value

# Usage in login route
try:
    username = sanitize_input(request.form['username'])
    password = sanitize_input(request.form['password'])
except ValueError as e:
    return "Invalid input", 400
```

---

## Layer 4 — Rate Limiting (blocks brute force)

```python
from flask_limiter import Limiter

limiter = Limiter(app, key_func=get_remote_address)

@app.route('/login', methods=['POST'])
@limiter.limit("5 per minute")   # Only 5 login tries per minute per IP
def login():
    username = sanitize_input(request.form['username'])
    password = request.form['password']

    if check_login(username, password):
        session['user'] = username
        return redirect('/dashboard')
    else:
        return "Invalid credentials", 401
```

---

## Full Summary — Defence in Depth

| Layer | What it does | Stops |
|---|---|---|
| Parameterized queries | Input = data, never code | SQL injection |
| Input sanitization | Strip/reject dangerous chars | SQL + XSS |
| Password hashing | bcrypt, never plain text | Credential theft |
| Length limits | Reject oversized inputs | Buffer overflow |
| Rate limiting | Max 5 tries/min per IP | Brute force |
| Least privilege DB user | DB account can only SELECT/INSERT, not DROP | Catastrophic damage |
