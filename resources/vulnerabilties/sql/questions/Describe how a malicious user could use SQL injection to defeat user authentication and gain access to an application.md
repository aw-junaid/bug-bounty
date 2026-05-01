how a malicious user can defeat authentication with SQL injection, described conceptually without focusing on specific code.

---

### The Goal

Authentication systems typically work by taking a username and password, then asking the database: "Does a row exist where the username is X AND the password is Y?" If exactly one row comes back, you're logged in.

The attacker's goal is to trick the database into returning a valid row **without knowing a real password**—or even a real username.

---

### Method 1: Bypassing the Password Check (The Classic Tautology)

This is the simplest and most famous technique. The attacker turns the password check into a statement that is always true.

**What the application intends to ask:**
> "Does a user exist where the name is '[INPUT]' and the password is '[INPUT]'?"

**What the attacker types in the username field:**
`admin' OR '1'='1`

**What the database actually executes:**
> "Does a user exist where the name is 'admin' OR '1'='1' and the password is '[garbage]'?"

Because `'1'='1'` is always true, the `OR` makes the entire condition true. The database returns all rows from the users table, and the application—seeing at least one row returned—assumes authentication succeeded. The attacker logs in, often as the first user in the table (frequently an administrator).

Crucially, the attacker types random gibberish for the password, because the logic of that field has been rendered irrelevant.

---

### Method 2: Logging In As a Specific User Without Their Password

Sometimes an attacker knows a valid username like `alice` but not her password. They don't care about bypassing the check entirely; they want to impersonate Alice specifically.

**What the attacker types in the username field:**
`alice'--`

The `--` is a code comment in SQL. Everything after it is ignored.

**What the database actually executes:**
> "Does a user exist where the name is 'alice' [rest of the command is commented out and ignored]"

The password check is completely removed from the query. The database finds Alice's user row and returns it. The application, seeing a match, logs the attacker in as Alice. The attacker never had to guess, steal, or reset a single password.

---

### Method 3: Discovering Valid Usernames Through Differential Responses

If the attacker can't directly bypass the login, they often probe for information first. They need a valid username to target.

They inject a payload like `admin' AND '1'='1` and observe the result. If the application returns "Invalid password" instead of "User not found," the attacker has just confirmed that an account named `admin` exists. They can then focus their attack, perhaps using brute-force or one of the bypass methods above, knowing they have a real target. The subtle difference in error messages becomes the leak.

---

### Method 4: Full Table Extraction for Offline Cracking

In a more aggressive scenario, the attacker doesn't bother with the login logic at all. They use the injection point to extract the entire user database directly.

- If the search or login page is vulnerable to a `UNION`-based injection, the attacker appends a secondary query that fetches the contents of the user table.
- The application page, originally designed to show a welcome message or search result, now inadvertently displays all usernames and password hashes on the screen.
- Even if the passwords are hashed and salted, the attacker now has a complete copy of them on their local machine and can attempt to crack them offline without any rate limiting or lockout policies getting in their way.

---

### The Core Failure

In every one of these cases, the fundamental failure is identical: **the application cannot tell the difference between the code it wrote and the data the user supplied.** It hands the merged string to the database, which obediently executes it. The `WHERE` clause meant to act as a gatekeeper is silently rewritten by the attacker into a revolving door.
