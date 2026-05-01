# Injection Vulnerabilities in Non-Relational Databases: Understanding NoSQL Injection

## The Fundamental Problem: Trusting User Input

The vulnerability to injection attacks stems from a single, critical mistake: trusting user-provided input without proper validation. Any input that contains interpretable code—whether it's SQL commands, JavaScript objects, or formatted text—can become a vehicle for attack when an application naively passes it directly to an interpreter or database system.

### Why Non-Relational Databases Are Also Vulnerable

While NoSQL databases don't use traditional SQL query languages, they still process structured, formatted text provided by users. Document-based databases, in particular, rely heavily on text formats such as JSON (JavaScript Object Notation) for storing and querying data. When applications accept user input and embed it directly into database queries without sanitization, attackers can manipulate the query structure to produce unintended behavior—following the same fundamental principle as SQL injection.

---

## Practical Example: MongoDB Authentication Bypass

### The Vulnerable Scenario

Consider a fictitious website that uses MongoDB, a popular document-based NoSQL database, for user authentication. The application accepts login credentials through HTTP parameters and queries the database to verify them.

### The Attack Vector

An attacker can craft a malicious HTTP GET request that exploits the way MongoDB processes query operators:

```
https://targetsite.org/login?user=admin&password[%24ne]=
```

The `%24ne` is a URL-encoded representation of `$ne`, which is MongoDB's "not equal" operator.

### The Vulnerable Backend Code

The target website runs on a Node.js framework and uses the following naïve code to verify credentials:

```javascript
db.collection('users').find({
    "user": req.query.user,
    "password": req.query.password
});
```

This code directly inserts user-supplied values into the MongoDB query without any sanitization or type checking.

### How the Injection Works

When the malicious request reaches the server, the application interprets the query parameters and constructs the following MongoDB query:

```javascript
db.collection('users').find({
    "user": "admin",
    "password": {"$ne": ""}
});
```

The `$ne` operator tells MongoDB to match documents where the password field is **not equal** to an empty string. Since any valid password is not empty, this condition evaluates to true. The `find()` function successfully returns a result, and the application grants access to the attacker—effectively bypassing authentication without knowing the actual password.

This attack succeeds because the application treats the `password` parameter as an object containing a MongoDB operator, rather than a simple string value. The attacker exploits the fact that HTTP parameter parsing can create nested objects from query string syntax like `password[$ne]`.

---

## Alternative Attack Vectors: POST Requests

### URL-Encoded POST Request

The injection vulnerability isn't limited to GET requests. An attacker can achieve the same result using a POST request with URL-encoded form data:

```http
POST /login HTTP/1.1
Host: targetsite.org
Content-Type: application/x-www-form-urlencoded
Content-Length: 27

user=admin&password[%24ne]=
```

The query string format in the request body follows the same parameter parsing rules, allowing the attacker to inject the `$ne` operator.

### JSON POST Request

If the application accepts JSON-formatted requests, the attack becomes even more straightforward. The attacker can send a properly formatted JSON object containing the malicious operator:

```http
POST /login HTTP/1.1
Host: targetsite.org
Content-Type: application/json
Content-Length: 36

{'user': 'admin', 'password': {'$ne': ''}}
```

In this case, the JSON parser naturally creates the nested object structure with the `$ne` operator, making the injection trivially easy for the attacker.

---

## Scope and Limitations of NoSQL Injection

### What Makes NoSQL Injection Different

Unlike traditional SQL injection, NoSQL injection attacks are generally more limited in scope due to the absence of a powerful, standardized query language. Key limitations include:

- **No Database Dumping**: Attackers typically cannot enumerate all databases, tables, or collections as they might with SQL `UNION`-based attacks.
- **No Arbitrary Query Execution**: The ability to execute completely custom queries is severely restricted compared to SQL injection.
- **Information Gathering Constraints**: Extracting systematic information about the database structure is more difficult.

### What Remains Dangerous

Despite these limitations, NoSQL injection remains a serious threat:

- **Semantic Manipulation**: Attackers can insert objects and operators that alter the meaning of existing queries, producing unexpected and potentially harmful results.
- **Authentication Bypass**: As demonstrated, gaining unauthorized access to protected resources is straightforward.
- **Logic Subversion**: Any application logic dependent on database query results can be subverted by manipulating query semantics.
- **Server-Side Code Exploitation**: Knowledge of the application's server-side code and database structure enables attackers to craft precisely targeted exploits.

---

## The Universal Solution: Input Sanitization

### Core Principle

The solution to injection vulnerabilities—whether SQL or NoSQL—remains fundamentally the same: **properly sanitize all user input and never trust data received from clients.**

### Recommended Practices

1. **Type Validation**: Verify that input matches the expected data type. If a parameter should be a string, reject objects, arrays, or other complex types.

2. **Input Casting**: Explicitly cast values to their expected types. For the MongoDB example, casting `req.query.password` to a string would prevent the object injection:

   ```javascript
   db.collection('users').find({
       "user": String(req.query.user),
       "password": String(req.query.password)
   });
   ```

3. **Allowlisting**: Define and enforce strict rules about which characters, patterns, and structures are permitted in input fields.

4. **Parameterized Queries**: Use database drivers and ORMs that support parameterized queries, which separate query structure from user-provided data.

5. **Expect Compromise Attempts**: Design applications with the assumption that users will attempt to submit malicious input, and build defenses accordingly.

---

## Comprehensive Summary: Injection Vulnerabilities Across Database Paradigms

### SQL Injection: A Quick Recap

SQL injection enables attackers to achieve various malicious objectives:

**Information Gathering:**
- Database exploration to discover schema structure
- Inference techniques to extract data without direct output
- Extracting sensitive information such as password hashes

**Privilege Escalation:**
- Authentication bypass using tautologies (always-true expressions like `1=1`)
- Gaining unauthorized access to application functionality
- Accessing data belonging to other users or administrative accounts

**Destructive Actions:**
- Executing `DROP` statements to delete database objects
- Modifying or deleting critical information such as login credentials

### The UNION Operator in SQL Injection

The `UNION` operator is a powerful tool for SQL injection attacks:

- It appends the results of an additional query to the original result set
- The second query must have the same number of columns as the first
- Attackers can add arbitrary static values to match column counts
- This enables extraction of data from entirely different tables within the same response

### Information Gathering Techniques

**Database Schema Exploration:**
- Query system catalogs to enumerate databases, tables, and columns
- Use discovered schema information to craft targeted data extraction queries

**UNION-Based Data Extraction:**
- Particularly powerful in MySQL and MSSQL environments
- Multiple databases can be queried, especially in shared hosting environments
- Many applications sharing a database system can all be compromised

### Database System Differences

Different SQL database systems have important variations that affect injection techniques:

| Database System | Key Characteristics | Exploitation Notes |
|----------------|---------------------|-------------------|
| **MySQL** | Multiple databases accessible per connection; `information_schema` database | Rich information_schema content; easy cross-database queries |
| **MSSQL** | Uses `..` syntax to access tables in default databases | Default databases contain useful metadata; supports multi-database queries |
| **Oracle** | Single database per connection; limited to one database at a time | Attacker must extract information from one database at a time |

### Blind SQL Injection Techniques

When direct database output is not visible, attackers employ blind techniques:

**Time-Based Blind SQL Injection:**
- Insert time delays (e.g., `SLEEP()` or `WAITFOR DELAY`) into queries
- Measure response times to determine if the injection is successful
- Combine with conditional logic to extract data one bit at a time

**Boolean-Based Blind SQL Injection:**
- Craft queries that evaluate to either true or false
- Observe application behavior differences between true and false conditions
- Reconstruct hidden data through systematic binary inference

**Combined Time and Boolean Techniques:**
- Use `IF` conditions with `UNION` statements
- Cause measurable time delays based on boolean conditions
- Infer database content by analyzing response timing patterns

**Splitting and Balancing:**
- Abuse query equivalence to inject arbitrary sub-queries
- Use parentheses to ensure correct syntax in complex injections
- Enable injection in scenarios where simpler methods fail

### NoSQL Injection: A Different Beast

Despite being called "injection," NoSQL injection has distinct characteristics:

**Similarities to SQL Injection:**
- Both exploit unsanitized user input
- Both manipulate query semantics
- Both can lead to unauthorized access and data exposure

**Key Differences:**
- NoSQL databases lack the powerful, standardized query language of SQL
- Database dumping and arbitrary queries are generally not possible
- Attack surface is narrower but still significant

**Why NoSQL Injection Should Not Be Underestimated:**
- Authentication bypass attacks remain straightforward
- Application-specific logic can be subverted
- Any unsanitized input to a database system creates risk
- The fundamental vulnerability is identical: trusting unvalidated user input

---

## Conclusion

Injection vulnerabilities transcend the traditional boundaries of SQL databases. Whether the backend is a relational database using structured query language or a NoSQL system processing JSON documents, the core vulnerability remains the same: **applications that blindly trust and process user input as executable code or query parameters create exploitable attack surfaces.**

The MongoDB authentication bypass example demonstrates that NoSQL injection can achieve concrete, damaging results with minimal complexity. While NoSQL injection may not offer the same breadth of exploitation as SQL injection, it remains a serious security concern that demands the same rigorous approach to input validation and sanitization.

The universal defense is consistent across all database technologies: **never trust user input, always validate and sanitize, and design applications with the expectation that every input field is a potential attack vector.** Understanding these principles and applying them systematically is essential for building secure applications in any database paradigm.
