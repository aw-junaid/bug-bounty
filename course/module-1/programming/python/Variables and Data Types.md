# Python Basics: Variables, Data Types, and Fundamental Operations

## Introduction: The Building Blocks of Python

Every complex Python program—every security scanner, every data analysis pipeline, every web application—begins with the humble variable. These fundamental building blocks are the vocabulary and grammar of programming. Before you can write exploits, automate forensics, or analyze threat intelligence, you must master how Python stores, manipulates, and organizes data.

This comprehensive guide explores Python's core data types and operations. We will journey through **strings** (the text you process from logs and payloads), **numbers** (the calculations behind cryptography and statistics), **lists** (ordered collections for everything from port scans to file paths), **dictionaries** (key-value mappings perfect for configuration and API responses), **tuples** (immutable sequences for fixed data), **sets** (collections of unique items for deduplication), and **booleans** (the true/false logic that drives every decision in your code).

Each section includes practical examples drawn from security contexts—parsing logs, validating input, building payloads, and analyzing data. By the end, you will possess a complete understanding of Python's fundamental data structures, enabling you to write clean, efficient, and powerful code.

---

## Part 1: Variables – Naming and Storing Data

Variables are named containers that store data in memory. They are the fundamental way Python programs remember and manipulate information.

### Variable Assignment and Naming Rules

**Basic Assignment:**

```python
# Simple variable assignment
name = "Security Researcher"
age = 42
is_authenticated = False
vulnerability_count = 15

# Multiple assignment
x, y, z = 10, 20, 30
a = b = c = 0  # All three variables equal 0

# Swapping values elegantly
a, b = b, a
```

**Variable Naming Rules and Conventions:**

| Rule | Valid | Invalid |
|:---|:---|:---|
| Must start with letter or underscore | `_private`, `var1` | `1var`, `@name` |
| Can contain letters, numbers, underscores | `user_name_2` | `user-name`, `user name` |
| Case-sensitive | `Port`, `port`, `PORT` are different | N/A |
| Cannot be reserved keywords | `class`, `if`, `for` reserved | Cannot use `class = 5` |

**Python Keywords (Reserved Words):**

```python
False, None, True, and, as, assert, async, await, break,
class, continue, def, del, elif, else, except, finally,
for, from, global, if, import, in, is, lambda, nonlocal,
not, or, pass, raise, return, try, while, with, yield
```

**Naming Conventions (PEP 8):**

```python
# Use lowercase with underscores for variables and functions
user_name = "admin"
failed_login_count = 0
def calculate_hash(data):
    pass

# Use CamelCase for classes
class SecurityScanner:
    pass

# Use UPPERCASE for constants
MAX_RETRIES = 3
DEFAULT_TIMEOUT = 30
API_KEY = "sk-1234567890abcdef"

# Use leading underscore for "private" variables (convention only)
_internal_cache = {}
_hidden_function()

# Use trailing underscore to avoid name conflicts
class_ = "Python Class"  # 'class' is reserved
type_ = "string"        # 'type' is built-in function
```

### Variable Types and Dynamic Typing

Python is **dynamically typed**—variables can hold values of any type, and the type can change during execution:

```python
# Variable type changes dynamically
data = 42                # int
print(type(data))        # <class 'int'>

data = "forty-two"       # str
print(type(data))        # <class 'str'>

data = [4, 2]            # list
print(type(data))        # <class 'list'>

# Type checking
if isinstance(data, list):
    print(f"Data is a list with {len(data)} elements")

# Type conversion (casting)
number_str = "123"
number_int = int(number_str)      # String to int
number_float = float(number_str)  # String to float
back_to_str = str(number_int)     # Int to string
```

### Variable Scope

```python
# Global variable
global_config = {"debug": True, "timeout": 30}

def scan_target(target):
    # Local variable
    result = {"target": target, "ports": []}
    
    # Access global variable (reading is fine)
    if global_config["debug"]:
        print(f"Scanning {target}")
    
    return result

def modify_global():
    # Must declare global to modify
    global global_config
    global_config = {"debug": False}  # Modifies the global variable
```

### Memory and Identity

```python
# Object identity
a = [1, 2, 3]
b = [1, 2, 3]
c = a

print(a == b)    # True (same value)
print(a is b)    # False (different objects)
print(a is c)    # True (same object)

# Get memory address (implementation detail)
print(id(a))
print(id(b))     # Different from id(a)
print(id(c))     # Same as id(a)

# Small integers and strings are interned (cached)
x = 256
y = 256
print(x is y)    # True (small integers are cached)

x = 257
y = 257
print(x is y)    # False (larger integers not cached by default)
```

---

## Part 2: Strings – Working with Text

Strings are sequences of characters, essential for processing logs, parsing user input, building payloads, and handling text data in security applications.

### String Creation

**Different Ways to Create Strings:**

```python
# Single quotes
single = 'Simple string'

# Double quotes (preferred when string contains single quotes)
double = "It's a string with apostrophe"

# Triple quotes for multi-line strings
multi_line = """This is a
multi-line string that spans
multiple lines."""

multi_line_alt = '''Also works
with single quotes.'''

# Raw strings (backslashes are literal, useful for regex and Windows paths)
raw_path = r"C:\Users\Admin\Documents"
regex_pattern = r"\d{3}-\d{2}-\d{4}"

# f-Strings (formatted strings, Python 3.6+)
name = "Alice"
age = 30
f_string = f"Name: {name}, Age: {age}, Next year: {age + 1}"

# String from other types
from_number = str(42)
from_bytes = b"bytes".decode('utf-8')
from_list = "".join(['a', 'b', 'c'])  # "abc"

# Empty string
empty = ""
also_empty = str()
```

**String Literals with Escapes:**

```python
# Common escape sequences
print("Line1\nLine2")           # Newline
print("Tab\tSeparated")         # Tab
print("Quote: \"quoted\"")      # Double quote
print('Single: \'quoted\'')     # Single quote
print("Backslash: \\")          # Backslash
print("Unicode: \u2764")        # Unicode character (heart)

# Raw strings ignore escapes (useful for regex)
print(r"C:\new\test")           # Prints: C:\new\test
# Without 'r', \n and \t would be interpreted as escapes
```

### String Operations

**Concatenation and Repetition:**

```python
# Concatenation with +
first = "Hello"
second = "World"
greeting = first + " " + second    # "Hello World"

# Repetition with *
separator = "-" * 40               # "----------------------------------------"
alert = "!" * 10                   # "!!!!!!!!!!"

# Augmented assignment
message = "Warning"
message += ": " + "System Error"   # "Warning: System Error"

# Efficient concatenation with join()
words = ["Security", "Scanner", "v1.0"]
title = " ".join(words)            # "Security Scanner v1.0"
path = "/".join(["usr", "local", "bin"])  # "usr/local/bin"
```

**Indexing and Slicing:**

```python
text = "Python Security"

# Indexing (0-based)
print(text[0])        # 'P'
print(text[7])        # 'S'
print(text[-1])       # 'y' (last character)
print(text[-3])       # 'i'

# Slicing [start:end:step]
print(text[0:6])      # "Python" (0 to 5)
print(text[7:])       # "Security" (7 to end)
print(text[:6])       # "Python" (start to 5)
print(text[:])        # "Python Security" (entire string)
print(text[::2])      # "Pto euiy" (every 2nd character)
print(text[::-1])     # "ytiruceS nohtyP" (reverse)

# Practical security examples
url = "https://example.com/login"
protocol = url[:5]                    # "https"
domain = url[8:-6]                    # "example.com"
endpoint = url.split('/')[-1]         # "login"
```

**String Membership and Length:**

```python
text = "Security Assessment Report"

# Check membership
print("Security" in text)      # True
print("vulnerability" in text) # False
print("report" in text.lower()) # True (case-insensitive check)

# Length
print(len(text))               # 26

# Iteration
for char in "ABC":
    print(char)                # Prints A, B, C on separate lines
```

### Essential String Methods for Security

**Case Manipulation:**

```python
text = "Python Security Scanner"

print(text.upper())          # "PYTHON SECURITY SCANNER"
print(text.lower())          # "python security scanner"
print(text.title())          # "Python Security Scanner"
print(text.capitalize())     # "Python security scanner"
print(text.swapcase())       # "pYTHON sECURITY sCANNER"

# Security use: Case-insensitive comparison
user_input = "ADMIN"
if user_input.lower() == "admin":
    print("Admin access requested")
```

**Searching and Replacing:**

```python
log_entry = "[ERROR] 2024-01-15: Failed login attempt from 192.168.1.100"

# Find position
print(log_entry.find("ERROR"))          # 1
print(log_entry.find("WARNING"))        # -1 (not found)
print(log_entry.rfind(":"))             # 32 (rightmost colon)

# Check start/end
print(log_entry.startswith("[ERROR]"))  # True
print(log_entry.endswith("100"))        # True

# Count occurrences
print(log_entry.count("."))             # 4 (dots in IP and date)

# Replace
sanitized = log_entry.replace("192.168.1.100", "[REDACTED]")
print(sanitized)  # "[ERROR] 2024-01-15: Failed login attempt from [REDACTED]"

# Replace with limit
text = "foo foo foo"
print(text.replace("foo", "bar", 2))    # "bar bar foo"
```

**Splitting and Joining:**

```python
# split() - break string into list
log_line = "192.168.1.100 - - [15/Jan/2024:10:30:00] \"GET /admin HTTP/1.1\" 403"
parts = log_line.split()
print(parts[0])   # "192.168.1.100"
print(parts[5])   # "\"GET"

# split with delimiter
csv_data = "admin,password123,192.168.1.1,active"
fields = csv_data.split(",")
username, password, ip, status = fields

# splitlines() - split by newlines
multi_line = "Line1\nLine2\nLine3"
lines = multi_line.splitlines()
print(lines)  # ['Line1', 'Line2', 'Line3']

# rsplit() - split from right
path = "/usr/local/bin/python3"
print(path.rsplit("/", 1))  # ['/usr/local/bin', 'python3']

# partition() - split into three parts
url = "https://example.com:443"
protocol, separator, rest = url.partition("://")
print(protocol)  # "https"
print(rest)      # "example.com:443"
```

**Stripping Whitespace and Characters:**

```python
user_input = "  admin  \n"

# Remove whitespace
print(repr(user_input.strip()))   # 'admin'
print(repr(user_input.lstrip()))  # 'admin  \n'
print(repr(user_input.rstrip()))  # '  admin'

# Remove specific characters
phone = "(555) 123-4567"
cleaned = phone.strip("() -")
print(cleaned)  # "5551234567"

# Remove prefix/suffix (Python 3.9+)
url = "https://example.com"
print(url.removeprefix("https://"))  # "example.com"
print(url.removesuffix(".com"))      # "https://example"
```

**Testing String Content:**

```python
# Content type checks
print("12345".isdigit())       # True
print("abc123".isalpha())      # False (has digits)
print("abc".isalpha())         # True
print("abc123".isalnum())      # True
print("   ".isspace())         # True
print("ABC".isupper())         # True
print("abc".islower())         # True
print("Hello World".istitle()) # True

# Security: Input validation
def is_valid_username(username):
    return username.isalnum() and 3 <= len(username) <= 20

def is_valid_ip_part(part):
    return part.isdigit() and 0 <= int(part) <= 255
```

**Formatting Strings:**

```python
# f-Strings (Python 3.6+) - Recommended
name = "Scanner"
version = 2.1
print(f"Tool: {name} v{version}")
print(f"Result: {42 * 2}")
print(f"Binary: {255:b}, Hex: {255:x}")

# Format with alignment
print(f"{'Left':<10} {'Center':^10} {'Right':>10}")
# Output: Left       Center      Right

# Padding and precision
print(f"PI: {3.14159:.2f}")           # "PI: 3.14"
print(f"Percent: {0.856:.1%}")        # "Percent: 85.6%"

# str.format() method (older style)
print("{} v{}".format(name, version))
print("{name} v{version}".format(name="Scanner", version=2.1))

# % formatting (legacy, avoid in new code)
print("%s v%.1f" % (name, version))
```

### String Security Considerations

**Input Sanitization for Security:**

```python
import html
import urllib.parse

# HTML escaping (prevent XSS)
user_comment = '<script>alert("XSS")</script>'
safe_html = html.escape(user_comment)
print(safe_html)  # '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'

# URL encoding
malicious_url = "http://example.com?q=<script>"
safe_url = urllib.parse.quote(malicious_url)
print(safe_url)  # 'http%3A//example.com%3Fq%3D%3Cscript%3E'

# SQL injection prevention (use parameterized queries!)
user_input = "admin' OR '1'='1"
# BAD: query = f"SELECT * FROM users WHERE username = '{user_input}'"
# GOOD: cursor.execute("SELECT * FROM users WHERE username = ?", (user_input,))

# Shell injection prevention
import shlex
command = "ls -la /home/user"
safe_args = shlex.split(command)  # Properly split respecting quotes
```

**String Comparison Security:**

```python
import secrets
import hmac

# Constant-time comparison for secrets (prevents timing attacks)
def verify_password(stored_hash, provided_password):
    # Use hmac.compare_digest for constant-time comparison
    return hmac.compare_digest(stored_hash, provided_password)

# DON'T use == for secret comparison (vulnerable to timing attacks)
# if user_input == secret_key:  # VULNERABLE

# DO use constant-time comparison
if secrets.compare_digest(user_input, secret_key):  # SAFE
    grant_access()
```

---

## Part 3: Numbers – Integers, Floats, and Arithmetic

Numbers are fundamental to programming—counting iterations, calculating statistics, performing cryptographic operations, and measuring performance.

### Integers (int)

Python integers have **arbitrary precision**—they can grow as large as memory allows.

```python
# Integer literals
decimal = 42
binary = 0b101010        # 42 in binary
octal = 0o52             # 42 in octal
hexadecimal = 0x2A       # 42 in hex

# Large integers (no overflow!)
huge_number = 2 ** 1000  # 10715086071862673209484250490600018105614048117055336074437503883703510511249361224931983788156958581275946729175531468251871452856923140435984577574698574803934567774824230985421074605062371141877954182153046474983581941267398767559165543946077062914571196477686542167660429831652624386837205668069376

# Integer operations
print(10 + 5)    # 15
print(10 - 5)    # 5
print(10 * 5)    # 50
print(10 / 5)    # 2.0 (always returns float)
print(10 // 3)   # 3 (floor division)
print(10 % 3)    # 1 (modulo)
print(2 ** 10)   # 1024 (exponentiation)

# Type conversion
print(int("123"))        # 123
print(int(3.99))         # 3 (truncates)
print(int("1010", 2))    # 10 (binary string to int)
print(int("FF", 16))     # 255 (hex string to int)
```

### Floating-Point Numbers (float)

Floats represent real numbers using IEEE 754 double precision (64-bit).

```python
# Float literals
simple = 3.14
scientific = 1.23e-4     # 0.000123
also_float = 5.0

# Float operations
print(10.5 + 2.3)   # 12.8
print(3.5 * 2)      # 7.0
print(10 / 3)       # 3.3333333333333335

# Precision issues (inherent to floating-point)
print(0.1 + 0.2)           # 0.30000000000000004 (not exactly 0.3)
print(0.1 + 0.2 == 0.3)    # False!

# Use decimal for exact decimal arithmetic
from decimal import Decimal
print(Decimal('0.1') + Decimal('0.2'))  # 0.3
print(Decimal('0.1') + Decimal('0.2') == Decimal('0.3'))  # True
```

**Float Methods and Utilities:**

```python
import math

# Rounding
print(round(3.14159, 2))    # 3.14
print(math.floor(3.9))      # 3
print(math.ceil(3.1))       # 4
print(math.trunc(-3.9))     # -3 (truncates toward zero)

# Math functions
print(math.sqrt(16))        # 4.0
print(math.sin(math.pi/2))  # 1.0
print(math.log(100, 10))    # 2.0 (log base 10)
print(math.exp(1))          # 2.718281828459045 (e^1)

# Check special values
print(math.isnan(float('nan')))    # True (Not a Number)
print(math.isinf(float('inf')))    # True (Infinity)
```

### Arithmetic Operations in Security Contexts

**Hash Calculations and Encoding:**

```python
import hashlib
import base64

# Integer to bytes conversion
def int_to_bytes(n, length):
    return n.to_bytes(length, byteorder='big')

def bytes_to_int(b):
    return int.from_bytes(b, byteorder='big')

# Example: Convert integer key to bytes for crypto
key_int = 0x1234567890ABCDEF
key_bytes = key_int.to_bytes(8, 'big')
print(key_bytes.hex())  # "1234567890abcdef"

# Modular arithmetic (cryptography)
p = 23  # Prime modulus
g = 5   # Generator
private = 6
public = pow(g, private, p)  # g^private mod p
print(f"Public key: {public}")  # 5^6 mod 23 = 8

# Bitwise operations
flags = 0b1010
print(bin(flags | 0b0101))   # 0b1111 (OR)
print(bin(flags & 0b1100))   # 0b1000 (AND)
print(bin(flags ^ 0b1111))   # 0b0101 (XOR)
print(bin(~flags))           # -0b1011 (NOT, two's complement)
print(bin(flags << 2))       # 0b101000 (left shift)
print(bin(flags >> 1))       # 0b101 (right shift)
```

**Statistical Analysis of Security Data:**

```python
import statistics

# Response times (possible timing attack detection)
response_times = [0.12, 0.15, 0.11, 0.45, 0.13, 0.14, 0.12]

mean = statistics.mean(response_times)
median = statistics.median(response_times)
stdev = statistics.stdev(response_times)

print(f"Mean: {mean:.3f}s")
print(f"Median: {median:.3f}s")
print(f"Std Dev: {stdev:.3f}s")

# Detect outliers (potential timing anomalies)
threshold = mean + 3 * stdev
outliers = [t for t in response_times if t > threshold]
if outliers:
    print(f"Potential timing anomaly: {outliers}")
```

**Random Number Generation for Security:**

```python
import secrets
import random

# WARNING: random module is NOT cryptographically secure!
# Use for simulations and non-security purposes only
random_int = random.randint(1, 100)
random_float = random.random()  # 0.0 to 1.0

# SECURE: Use secrets module for cryptographic randomness
secure_token = secrets.token_hex(32)      # 64-character hex string
secure_url = secrets.token_urlsafe(32)    # URL-safe base64
secure_int = secrets.randbits(256)        # 256-bit random integer
secure_choice = secrets.choice(['A', 'B', 'C'])

# Generate cryptographically secure random bytes
key_material = secrets.token_bytes(32)
print(key_material.hex())
```

---

## Part 4: Lists – Ordered, Mutable Collections

Lists are ordered, mutable sequences—the workhorse data structure for storing collections of items that can change.

### Creating Lists

```python
# List literals
empty = []
numbers = [1, 2, 3, 4, 5]
mixed = [1, "two", 3.0, True, [5, 6]]
ips = ["192.168.1.1", "10.0.0.1", "172.16.0.1"]

# List constructor
from_range = list(range(10))           # [0, 1, 2, ..., 9]
from_string = list("Python")           # ['P', 'y', 't', 'h', 'o', 'n']
from_tuple = list((1, 2, 3))           # [1, 2, 3]

# List comprehension
squares = [x**2 for x in range(10)]    # [0, 1, 4, 9, 16, 25, 36, 49, 64, 81]
evens = [x for x in range(20) if x % 2 == 0]
```

### Accessing and Slicing Lists

```python
ports = [22, 80, 443, 8080, 8443, 3306, 5432]

# Indexing
print(ports[0])         # 22 (first)
print(ports[-1])        # 5432 (last)
print(ports[3])         # 8080

# Slicing (returns new list)
print(ports[1:4])       # [80, 443, 8080]
print(ports[:3])        # [22, 80, 443]
print(ports[3:])        # [8080, 8443, 3306, 5432]
print(ports[::2])       # [22, 443, 8443, 5432] (every 2nd)
print(ports[::-1])      # [5432, 3306, 8443, 8080, 443, 80, 22] (reverse)

# Nested lists
matrix = [[1, 2, 3], [4, 5, 6], [7, 8, 9]]
print(matrix[1][2])     # 6 (row 1, column 2)

# Length and membership
print(len(ports))              # 7
print(443 in ports)            # True
print(21 in ports)             # False
print(ports.index(443))        # 2 (first occurrence)
print(ports.count(80))         # 1 (count occurrences)
```

### Modifying Lists

```python
vulnerabilities = ["XSS", "SQLi", "CSRF"]

# Modify by index
vulnerabilities[0] = "Reflected XSS"
print(vulnerabilities)  # ['Reflected XSS', 'SQLi', 'CSRF']

# Modify slice
vulnerabilities[1:3] = ["Blind SQLi", "Stored XSS"]
print(vulnerabilities)  # ['Reflected XSS', 'Blind SQLi', 'Stored XSS']

# Delete elements
del vulnerabilities[1]
print(vulnerabilities)  # ['Reflected XSS', 'Stored XSS']

# Clear entire list
vulnerabilities.clear()
print(vulnerabilities)  # []
```

### Essential List Methods

**Adding Elements:**

```python
tools = ["nmap", "wireshark"]

# append() - add single element to end
tools.append("burpsuite")
print(tools)  # ['nmap', 'wireshark', 'burpsuite']

# extend() - add multiple elements from iterable
tools.extend(["metasploit", "hydra"])
print(tools)  # ['nmap', 'wireshark', 'burpsuite', 'metasploit', 'hydra']

# insert() - insert at specific position
tools.insert(1, "tcpdump")
print(tools)  # ['nmap', 'tcpdump', 'wireshark', ...]

# + operator creates new list
all_tools = tools + ["gobuster", "ffuf"]
```

**Removing Elements:**

```python
targets = ["192.168.1.1", "10.0.0.1", "172.16.0.1", "192.168.1.1"]

# remove() - remove first matching value
targets.remove("192.168.1.1")
print(targets)  # ['10.0.0.1', '172.16.0.1', '192.168.1.1']

# pop() - remove and return by index
last = targets.pop()        # Remove and return last
specific = targets.pop(0)   # Remove and return first
print(f"Removed: {last}, {specific}")

# clear() - remove all
targets.clear()
```

**Ordering and Searching:**

```python
ports = [8080, 22, 443, 80, 3306, 22]

# sort() - sort in-place
ports.sort()
print(ports)  # [22, 22, 80, 443, 3306, 8080]

ports.sort(reverse=True)
print(ports)  # [8080, 3306, 443, 80, 22, 22]

# sorted() - returns new sorted list
original = [3, 1, 4, 1, 5]
sorted_copy = sorted(original)
print(original)      # [3, 1, 4, 1, 5] (unchanged)
print(sorted_copy)   # [1, 1, 3, 4, 5]

# reverse() - reverse in-place
ports.reverse()
print(ports)

# count() and index()
print(ports.count(22))      # 2
print(ports.index(443))     # Position of 443
```

**Copying Lists (Important!):**

```python
original = [1, 2, [3, 4]]

# Shallow copy (new list, but nested objects shared)
shallow = original.copy()
shallow = original[:]
shallow = list(original)

# Demonstrate shallow copy issue
shallow[2][0] = 99
print(original[2])  # [99, 4] (changed!)

# Deep copy (completely independent)
import copy
deep = copy.deepcopy(original)
deep[2][0] = 100
print(original[2])  # [99, 4] (unchanged)
print(deep[2])      # [100, 4]
```

### List Comprehensions for Security Tasks

```python
# Filter open ports from scan results
scan_results = [
    {"port": 22, "state": "open"},
    {"port": 80, "state": "open"},
    {"port": 443, "state": "closed"},
    {"port": 3306, "state": "open"}
]

open_ports = [r["port"] for r in scan_results if r["state"] == "open"]
print(open_ports)  # [22, 80, 3306]

# Extract IPs from log entries
log_entries = [
    "192.168.1.100 - - [15/Jan/2024:10:30:00] \"GET /admin HTTP/1.1\" 403",
    "10.0.0.50 - - [15/Jan/2024:10:31:00] \"POST /login HTTP/1.1\" 200",
]

ips = [entry.split()[0] for entry in log_entries]
print(ips)  # ['192.168.1.100', '10.0.0.50']

# Generate payload list for fuzzing
fuzz_payloads = [f"' OR '1'='{i}" for i in range(1, 6)]
print(fuzz_payloads)
# ["' OR '1'='1", "' OR '1'='2", "' OR '1'='3", "' OR '1'='4", "' OR '1'='5"]

# Nested comprehension for 2D operations
matrix = [[1, 2, 3], [4, 5, 6], [7, 8, 9]]
flattened = [num for row in matrix for num in row]
print(flattened)  # [1, 2, 3, 4, 5, 6, 7, 8, 9]
```

---

## Part 5: Dictionaries – Key-Value Mappings

Dictionaries are unordered (insertion-ordered in Python 3.7+) mappings of unique keys to values. They are perfect for configuration, API responses, caching, and structured data.

### Creating Dictionaries

```python
# Dictionary literals
empty = {}
config = {
    "debug": True,
    "timeout": 30,
    "max_threads": 100,
    "targets": ["example.com", "test.com"]
}

# dict constructor
from_kwargs = dict(debug=True, timeout=30, retries=3)
from_tuples = dict([("a", 1), ("b", 2), ("c", 3)])
from_zip = dict(zip(["name", "age"], ["Alice", 30]))

# Dictionary comprehension
squares = {x: x**2 for x in range(5)}
print(squares)  # {0: 0, 1: 1, 2: 4, 3: 9, 4: 16}

# From list of keys with default value
keys = ["ssh", "http", "https", "mysql"]
default_ports = dict.fromkeys(keys, 0)
print(default_ports)  # {'ssh': 0, 'http': 0, 'https': 0, 'mysql': 0}
```

### Accessing Dictionary Values

```python
config = {"host": "localhost", "port": 5432, "ssl": True}

# Direct access (raises KeyError if missing)
print(config["host"])      # "localhost"

# Safe access with get()
print(config.get("port"))      # 5432
print(config.get("timeout"))   # None (no error)
print(config.get("timeout", 30))  # 30 (default value)

# Check existence
print("ssl" in config)      # True
print("debug" not in config) # True

# Get keys, values, items
print(config.keys())        # dict_keys(['host', 'port', 'ssl'])
print(config.values())      # dict_values(['localhost', 5432, True])
print(config.items())       # dict_items([('host', 'localhost'), ...])
```

### Modifying Dictionaries

```python
scan_config = {"target": "example.com"}

# Add/update single item
scan_config["port"] = 443
scan_config["timeout"] = 30
print(scan_config)

# Update with another dictionary
scan_config.update({"verbose": True, "ssl_verify": False})
print(scan_config)

# Set default (returns existing value or sets default)
threads = scan_config.setdefault("threads", 10)
print(threads)        # 10
print(scan_config)    # Includes 'threads': 10

# Delete items
del scan_config["ssl_verify"]
timeout = scan_config.pop("timeout")  # Remove and return
item = scan_config.popitem()          # Remove and return last item
scan_config.clear()                   # Remove all
```

### Dictionary Methods for Security Tasks

```python
# API response parsing
api_response = {
    "status": "success",
    "data": {
        "user": "admin",
        "permissions": ["read", "write", "delete"],
        "last_login": "2024-01-15T10:30:00Z"
    },
    "meta": {"request_id": "abc123"}
}

# Safe nested access
def safe_get(data, *keys, default=None):
    """Safely access nested dictionary keys."""
    for key in keys:
        try:
            data = data[key]
        except (KeyError, TypeError, IndexError):
            return default
    return data

print(safe_get(api_response, "data", "user"))           # "admin"
print(safe_get(api_response, "data", "email"))          # None
print(safe_get(api_response, "data", "permissions", 1)) # "write"

# Merge configurations (deep update)
def deep_update(original, update):
    """Recursively update nested dictionaries."""
    for key, value in update.items():
        if isinstance(value, dict) and key in original:
            deep_update(original[key], value)
        else:
            original[key] = value
    return original

default_config = {
    "scanner": {
        "timeout": 30,
        "threads": 10
    },
    "output": {"format": "json", "verbose": False}
}

user_config = {
    "scanner": {"timeout": 60},
    "output": {"verbose": True}
}

final_config = deep_update(default_config.copy(), user_config)
```

**Dictionary Comprehensions for Data Transformation:**

```python
# Filter dictionary by value
scan_results = {
    "192.168.1.1": {"port_22": "open", "port_80": "closed"},
    "192.168.1.2": {"port_22": "closed", "port_80": "open"},
    "192.168.1.3": {"port_22": "open", "port_80": "open"}
}

# Find hosts with SSH (port 22) open
ssh_hosts = {ip: ports for ip, ports in scan_results.items() 
             if ports.get("port_22") == "open"}
print(ssh_hosts)  # {'192.168.1.1': {...}, '192.168.1.3': {...}}

# Transform values
port_counts = {ip: sum(1 for state in ports.values() if state == "open")
               for ip, ports in scan_results.items()}
print(port_counts)  # {'192.168.1.1': 1, '192.168.1.2': 1, '192.168.1.3': 2}

# Invert dictionary (values become keys, group duplicates)
from collections import defaultdict

vulnerabilities = {
    "XSS": "High",
    "SQLi": "Critical",
    "CSRF": "Medium",
    "RCE": "Critical"
}

by_severity = defaultdict(list)
for vuln, severity in vulnerabilities.items():
    by_severity[severity].append(vuln)

print(dict(by_severity))
# {'High': ['XSS'], 'Critical': ['SQLi', 'RCE'], 'Medium': ['CSRF']}
```

---

## Part 6: Tuples – Immutable Sequences

Tuples are ordered, immutable sequences. Once created, they cannot be modified—useful for constants, dictionary keys, and returning multiple values.

### Creating Tuples

```python
# Tuple literals
empty = ()
single = (1,)          # Comma required for single-element tuple
numbers = (1, 2, 3, 4, 5)
mixed = (1, "two", 3.0, True)

# Without parentheses (tuple packing)
coordinates = 10, 20
print(type(coordinates))  # <class 'tuple'>

# tuple constructor
from_list = tuple([1, 2, 3])
from_string = tuple("Python")  # ('P', 'y', 't', 'h', 'o', 'n')
from_range = tuple(range(5))   # (0, 1, 2, 3, 4)
```

### Accessing Tuples

```python
config = ("localhost", 5432, "postgres", "secret")

# Indexing and slicing (same as lists)
print(config[0])         # "localhost"
print(config[-1])        # "secret"
print(config[1:3])       # (5432, "postgres")

# Membership and length
print(5432 in config)    # True
print(len(config))       # 4

# Iteration
for item in config:
    print(item)
```

### Why Tuples Matter in Security

**1. Immutability for Security Constants:**

```python
# Constants that should never change
CRITICAL_PORTS = (22, 3389, 5900)  # SSH, RDP, VNC
SAFE_METHODS = ("GET", "HEAD", "OPTIONS")
HASH_ALGORITHMS = ("sha256", "sha512", "blake2b")

# Accidentally trying to modify raises error
# CRITICAL_PORTS.append(23)  # AttributeError!
```

**2. Dictionary Keys (Lists Cannot Be Keys):**

```python
# Tuple as dictionary key (useful for caching)
cache = {}
request_key = ("GET", "/api/users", "admin")
cache[request_key] = {"status": 200, "response_time": 0.123}

# Coordinates mapping
vulnerability_locations = {
    (40.7128, -74.0060): "New York SOC",
    (37.7749, -122.4194): "San Francisco SOC",
    (51.5074, -0.1278): "London SOC"
}
```

**3. Returning Multiple Values from Functions:**

```python
def scan_port(target, port):
    """Return scan result as tuple."""
    import socket
    try:
        sock = socket.socket()
        sock.settimeout(1)
        result = sock.connect_ex((target, port))
        sock.close()
        return (port, result == 0, None)  # (port, is_open, error)
    except Exception as e:
        return (port, False, str(e))

# Usage
port, is_open, error = scan_port("example.com", 80)
if is_open:
    print(f"Port {port} is open")
elif error:
    print(f"Error scanning port {port}: {error}")
```

### Tuple Unpacking

```python
# Basic unpacking
host, port, user, password = ("localhost", 5432, "postgres", "secret")
print(f"Connecting to {host}:{port} as {user}")

# Swapping variables
a, b = 10, 20
a, b = b, a
print(a, b)  # 20 10

# Extended unpacking (Python 3+)
first, *middle, last = (1, 2, 3, 4, 5)
print(first)   # 1
print(middle)  # [2, 3, 4]
print(last)    # 5

# Ignoring values with underscore
name, _, version = ("Scanner", "unused", "2.0")
print(f"{name} v{version}")

# Unpacking in loops
scan_results = [
    ("192.168.1.1", 22, "open"),
    ("192.168.1.1", 80, "closed"),
    ("192.168.1.2", 22, "open"),
]

for ip, port, state in scan_results:
    if state == "open":
        print(f"{ip}:{port} is {state}")
```

### Named Tuples for Readable Code

```python
from collections import namedtuple

# Define a named tuple type
ScanResult = namedtuple('ScanResult', ['host', 'port', 'state', 'service'])

# Create instances
result1 = ScanResult("example.com", 80, "open", "http")
result2 = ScanResult("example.com", 443, "open", "https")

# Access by name (more readable than index)
print(result1.host)      # "example.com"
print(result1.port)      # 80
print(result1.service)   # "http"

# Still works like a regular tuple
host, port, state, service = result1
print(f"{host}:{port} - {state}")

# Immutable (like regular tuples)
# result1.port = 8080  # AttributeError!
```

---

## Part 7: Sets – Unique, Unordered Collections

Sets store **unique**, **unordered** elements. They are optimized for membership testing and mathematical set operations.

### Creating Sets

```python
# Set literals (curly braces)
empty = set()  # Note: {} creates empty dict, not set!
vulnerabilities = {"XSS", "SQLi", "CSRF", "RCE"}

# set constructor
from_list = set([1, 2, 2, 3, 3, 3])  # {1, 2, 3} (duplicates removed)
from_string = set("hello")           # {'h', 'e', 'l', 'o'}
from_tuple = set((1, 2, 3))

# Set comprehension
squares = {x**2 for x in range(10)}  # {0, 1, 4, 9, 16, 25, 36, 49, 64, 81}
even_squares = {x**2 for x in range(10) if x % 2 == 0}
```

### Set Operations

```python
web_ports = {80, 443, 8080, 8443}
db_ports = {3306, 5432, 1433, 27017}
common_ports = {22, 80, 443, 8080}

# Union (all elements from both)
all_ports = web_ports | db_ports
all_ports = web_ports.union(db_ports)
print(all_ports)  # {80, 443, 8080, 8443, 3306, 5432, 1433, 27017}

# Intersection (elements in both)
overlap = web_ports & common_ports
overlap = web_ports.intersection(common_ports)
print(overlap)  # {80, 443, 8080}

# Difference (in first but not second)
web_only = web_ports - common_ports
web_only = web_ports.difference(common_ports)
print(web_only)  # {8443}

# Symmetric Difference (in one but not both)
unique = web_ports ^ db_ports
unique = web_ports.symmetric_difference(db_ports)
print(unique)  # Elements in web_ports OR db_ports but not both

# Subset and Superset
print({80, 443}.issubset(web_ports))      # True
print(web_ports.issuperset({80, 443}))    # True
print({80, 21}.isdisjoint(web_ports))     # False (80 is shared)
```

### Modifying Sets

```python
ports = {22, 80, 443}

# Add elements
ports.add(8080)
print(ports)  # {22, 80, 443, 8080}

# Add multiple elements
ports.update([8443, 9090])
print(ports)

# Remove elements (raises KeyError if missing)
ports.remove(80)

# Remove safely (no error if missing)
ports.discard(9999)

# Remove and return arbitrary element
port = ports.pop()
print(f"Removed: {port}")

# Clear all
ports.clear()
```

### Security Applications of Sets

**1. Deduplication:**

```python
# Remove duplicate IPs from multiple sources
firewall_logs = ["192.168.1.1", "10.0.0.1", "192.168.1.1", "172.16.0.1"]
ids_alerts = ["192.168.1.1", "192.168.1.100", "10.0.0.1"]

unique_ips = set(firewall_logs + ids_alerts)
print(unique_ips)  # {'192.168.1.1', '10.0.0.1', '172.16.0.1', '192.168.1.100'}

# Find common IPs across sources
common_ips = set(firewall_logs) & set(ids_alerts)
print(f"IPs in both sources: {common_ips}")

# Find IPs only in firewall logs
firewall_only = set(firewall_logs) - set(ids_alerts)
```

**2. Port Scanning Analysis:**

```python
# Standard ports for comparison
STANDARD_WEB_PORTS = {80, 443}
STANDARD_DB_PORTS = {3306, 5432, 1433, 27017}

# Scan results
open_ports = {22, 80, 443, 8080, 3306}

# Identify unusual services
unusual = open_ports - STANDARD_WEB_PORTS - STANDARD_DB_PORTS - {22}
if unusual:
    print(f"Unusual ports found: {unusual}")

# Check for missing expected services
if not (open_ports & STANDARD_WEB_PORTS):
    print("No standard web ports open")

# Identify potential misconfigurations
web_on_nonstandard = open_ports & {8000, 8080, 8888}
if web_on_nonstandard:
    print(f"Web services on non-standard ports: {web_on_nonstandard}")
```

**3. Efficient Membership Testing:**

```python
# Sets are O(1) for membership tests (lists are O(n))
WHITELISTED_IPS = {"192.168.1.100", "10.0.0.50", "172.16.0.25"}

def is_whitelisted(ip):
    return ip in WHITELISTED_IPS

# Much faster for large datasets
BLOCKED_USER_AGENTS = {
    "sqlmap", "nikto", "nessus", "nmap", "gobuster",
    "dirbuster", "wfuzz", "hydra", "metasploit"
}

def is_scanner(user_agent):
    user_agent_lower = user_agent.lower()
    return any(scanner in user_agent_lower for scanner in BLOCKED_USER_AGENTS)
```

**4. Finding Unique Elements in Security Data:**

```python
# Find unique CVEs from multiple scanner outputs
scanner1_cves = {"CVE-2021-44228", "CVE-2022-22965", "CVE-2023-12345"}
scanner2_cves = {"CVE-2021-44228", "CVE-2021-45046", "CVE-2023-12345"}

all_cves = scanner1_cves | scanner2_cves
print(f"Total unique CVEs: {len(all_cves)}")

# Find CVEs detected by both scanners (high confidence)
confirmed_cves = scanner1_cves & scanner2_cves
print(f"Confirmed CVEs: {confirmed_cves}")

# Find CVEs only detected by scanner1 (potential false negatives in scanner2)
scanner1_only = scanner1_cves - scanner2_cves
print(f"Scanner1 only: {scanner1_only}")
```

---

## Part 8: Booleans – True and False Logic

Booleans represent truth values and are fundamental to control flow, conditional execution, and logical operations.

### Boolean Values and Operations

```python
# Boolean literals
is_authenticated = True
is_admin = False

# Boolean from expressions
is_greater = 10 > 5          # True
is_equal = "admin" == "root" # False
is_member = 80 in [80, 443]  # True

# Boolean from other types (Truthiness)
print(bool(1))          # True
print(bool(0))          # False
print(bool("hello"))    # True
print(bool(""))         # False
print(bool([1, 2]))     # True
print(bool([]))         # False
print(bool(None))       # False
```

### Boolean Operations (and, or, not)

```python
# Logical AND (both must be True)
print(True and True)    # True
print(True and False)   # False

# Logical OR (at least one True)
print(True or False)    # True
print(False or False)   # False

# Logical NOT (invert)
print(not True)         # False
print(not False)        # True

# Short-circuit evaluation
def is_valid():
    print("Checking validity")
    return True

# Second condition not evaluated if first is False
result = False and is_valid()  # is_valid() not called

# Second condition not evaluated if first is True
result = True or is_valid()    # is_valid() not called
```

### Boolean Expressions in Security Logic

```python
# Authentication check
def authenticate(username, password, mfa_code=None, require_mfa=False):
    valid_credentials = check_credentials(username, password)
    valid_mfa = check_mfa(username, mfa_code) if require_mfa else True
    
    return valid_credentials and valid_mfa

# Input validation
def is_safe_input(user_input):
    return (len(user_input) <= 100 and 
            user_input.isalnum() and 
            not any(char in user_input for char in "<>\"';&|"))

# Access control
def can_access(user, resource):
    return (user.is_authenticated and 
            (user.is_admin or user.has_permission(resource)))

# Vulnerability assessment
def is_critical(vulnerability):
    return (vulnerability["cvss_score"] >= 9.0 or 
            vulnerability["exploit_available"] or 
            vulnerability["internet_exposed"])
```

### Comparison Operators

```python
x = 10
y = 20

print(x == y)    # False (equal)
print(x != y)    # True (not equal)
print(x < y)     # True (less than)
print(x > y)     # False (greater than)
print(x <= y)    # True (less than or equal)
print(x >= y)    # False (greater than or equal)

# Chained comparisons
age = 25
print(18 <= age <= 65)  # True

# Identity vs Equality
a = [1, 2, 3]
b = [1, 2, 3]
c = a

print(a == b)    # True (same values)
print(a is b)    # False (different objects)
print(a is c)    # True (same object)

# None checking (use 'is', not '==')
result = None
if result is None:
    print("No result")
```

### Truth Table for Security Logic

```python
# Common security logic patterns
def access_granted(user, resource, time):
    """Demonstrate complex boolean logic."""
    is_work_hours = 9 <= time.hour <= 17
    is_authorized = user.role in ["admin", "security"]
    is_owner = user.id == resource.owner_id
    is_emergency = user.has_flag("emergency_override")
    
    # Access granted if:
    # - User is owner during work hours, OR
    # - User is authorized (admin/security) anytime, OR
    # - Emergency override is active
    return (is_owner and is_work_hours) or is_authorized or is_emergency

# Testing all combinations
test_cases = [
    # (is_owner, is_work_hours, is_authorized, is_emergency, expected)
    (True, True, False, False, True),   # Owner during work hours
    (True, False, False, False, False), # Owner outside work hours
    (False, False, True, False, True),  # Admin anytime
    (False, False, False, True, True),  # Emergency override
    (False, True, False, False, False), # No access
]

for owner, work, auth, emergency, expected in test_cases:
    result = (owner and work) or auth or emergency
    assert result == expected
```

---

## Part 9: Complete Reference and Best Practices

### Type Conversion Reference

```python
# To String
str(42)                # "42"
str([1, 2, 3])         # "[1, 2, 3]"
str({"a": 1})          # "{'a': 1}"

# To Integer
int("42")              # 42
int("1010", 2)         # 10 (binary)
int(3.99)              # 3

# To Float
float("3.14")          # 3.14
float(42)              # 42.0

# To List
list("abc")            # ['a', 'b', 'c']
list((1, 2, 3))        # [1, 2, 3]
list({1, 2, 3})        # [1, 2, 3]

# To Tuple
tuple([1, 2, 3])       # (1, 2, 3)

# To Set
set([1, 2, 2, 3])      # {1, 2, 3}

# To Boolean
bool(1)                # True
bool(0)                # False
bool([])               # False
```

### Mutability Summary

| Type | Mutable | Ordered | Duplicates | Use Case |
|:---|:---|:---|:---|:---|
| String | No | Yes | Yes | Text data |
| Integer | No | N/A | N/A | Counting, math |
| Float | No | N/A | N/A | Decimal calculations |
| List | Yes | Yes | Yes | Ordered collections |
| Tuple | No | Yes | Yes | Constants, keys |
| Dictionary | Yes | Yes (3.7+) | Keys: No, Values: Yes | Key-value mappings |
| Set | Yes | No | No | Unique elements |

### Choosing the Right Data Structure

```python
# Use LIST when:
# - Order matters
# - You need to modify the collection
# - You need indexed access
ports = [22, 80, 443, 8080]

# Use TUPLE when:
# - Data should not change
# - Using as dictionary key
# - Slight performance advantage
DEFAULT_PORTS = (22, 80, 443)

# Use SET when:
# - You need unique elements
# - You need fast membership testing
# - You need set operations (union, intersection)
unique_ips = {"192.168.1.1", "10.0.0.1"}

# Use DICTIONARY when:
# - You need key-value mapping
# - Fast lookup by key
# - Structured data representation
config = {"host": "localhost", "port": 5432}
```

---

## Conclusion: Building on Solid Foundations

You have now mastered Python's fundamental data types and operations. This knowledge forms the bedrock upon which all Python programming is built:

**Strings** give you the power to process text—parse logs, sanitize input, build payloads, and format output. Understanding string methods and security considerations enables safe handling of untrusted data.

**Numbers** provide the mathematical foundation for calculations, statistics, cryptography, and performance measurement. Knowing the nuances of integers and floating-point numbers prevents subtle bugs.

**Lists** offer flexible, ordered collections perfect for storing sequences of items that change over time—scan results, target lists, payload collections.

**Dictionaries** provide fast key-value lookups ideal for configuration, API responses, caches, and structured data representation.

**Tuples** deliver immutable sequences for constants, return values, and situations where data integrity matters.

**Sets** excel at deduplication, membership testing, and mathematical set operations—essential for comparing scan results and managing unique elements.

**Booleans** drive the logic that makes programs intelligent—conditional execution, access control, input validation, and decision-making.

These fundamental concepts appear in every Python program, from simple scripts to complex security frameworks. Master them, and you possess the vocabulary to express any programming idea. Practice them, and they become second nature. Build upon them, and there is no limit to what you can create.

---

## Further Resources

**Official Documentation:**
- [Python Built-in Types](https://docs.python.org/3/library/stdtypes.html)
- [Python Data Structures Tutorial](https://docs.python.org/3/tutorial/datastructures.html)
- [String Methods Reference](https://docs.python.org/3/library/stdtypes.html#string-methods)

**Practice Resources:**
- [Python Exercises on Exercism](https://exercism.org/tracks/python)
- [HackerRank Python Challenges](https://www.hackerrank.com/domains/python)
- [LeetCode Python Problems](https://leetcode.com/problemset/all/?difficulty=EASY&page=1&topicSlugs=python)
