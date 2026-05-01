No, that's absolutely **not true**. While SQL injection is the most famous example, the underlying vulnerability—**injecting malicious code or commands into data that is then passed to an interpreter**—exists across many different types of databases and systems.

The problem isn't SQL; the problem is an application that carelessly mixes untrusted user input with executable code. Any database or back-end system that has a command language can be vulnerable.

Here are the most prominent non-SQL examples.

---

### 1. NoSQL Injection

NoSQL databases like MongoDB, Couchbase, and Cassandra use query languages and APIs that are entirely different from SQL, but they are equally susceptible when input is not sanitized.

- **Mechanism:** Many NoSQL databases use JavaScript-like syntax or JSON-based queries. If an application builds these queries by concatenating user input as raw objects or strings, an attacker can inject operators that alter the query's logic.
- **Example (MongoDB):** An application's login function builds a JSON query to find a user: `db.users.find({user: $username, password: $password})`. An attacker injects a MongoDB operator as the username: `{ "$ne": "" }`. The query becomes `db.users.find({user: {"$ne": ""}, password: "garbage"})`. This translates to "find a user whose name is not empty," which is always true, bypassing authentication entirely—no SQL required.

---

### 2. LDAP Injection

LDAP (Lightweight Directory Access Protocol) is used for directory services like Active Directory. Applications use LDAP queries to authenticate users, look up printers, or search corporate directories.

- **Mechanism:** If user input is pasted directly into an LDAP filter string, an attacker can inject logical operators `(&)` or `(|)` and wildcards `*` to alter the filter.
- **Example:** A normal query to verify a user might be `(&(username=alice)(password=secret))`. If the application inserts a raw value like `*)(|(username=*`, the query logic becomes `(&(username=*)(|(username=*)(password=whatever))`. The filter now matches any user, bypassing the password check.

---

### 3. XPath/XQuery Injection

XML databases and applications that query XML documents use XPath. The same concatenation flaws lead to injection.

- **Mechanism:** XPath provides a path-based language to navigate XML nodes. An attacker can inject expressions into a crafted input string.
- **Example:** An application authenticates by running: `//User[username='$input' and password='$input']`. Injecting `' or '1'='1` into the username field breaks out of the string and injects a tautology, similar to SQL, allowing access to the entire user base in the XML document.

---

### 4. OS Command Injection

This goes beyond databases and targets the operating system hosting the application. It's extremely dangerous.

- **Mechanism:** The application calls a shell command (e.g., `nslookup`, `ping`, `curl`) using a function like `system()` or `exec()`, embedding user input directly into the command string.
- **Example:** A feature lets you enter an IP address to ping it, calling `ping $userInput`. An attacker types `127.0.0.1; cat /etc/passwd`. The shell executes the ping command and then, due to the semicolon, executes the `cat` command to expose the server's password file.

---

### Summary Table

| Attack Type | Target Interpreter | Injected Language/Pattern |
| :--- | :--- | :--- |
| **SQL Injection** | SQL Database (MySQL, Oracle, etc.) | SQL operators (`' OR '1'='1`) |
| **NoSQL Injection** | NoSQL Database (MongoDB, etc.) | JSON query operators (`$ne`, `$gt`) |
| **LDAP Injection** | Directory Service (Active Directory) | LDAP filter syntax (`*`, `|`, `&`) |
| **XPath Injection** | XML Database / Parser | XPath predicates (`' or '1'='1`) |
| **OS Command Injection** | Operating System Shell | Shell metacharacters (`;`, `|`, `&&`) |

The core vulnerability is universal: **failing to separate code from data**. Parameterized queries (or their equivalents in non-SQL systems, such as prepared JSON objects, parameterized LDAP filters, or safe shell argument arrays) are the defense against all of them.
