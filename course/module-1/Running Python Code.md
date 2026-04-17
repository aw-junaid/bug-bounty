# Running Python Code: Interactive Shell, Script Execution, and Version Management

## Introduction: From Code to Execution

Writing Python code is only half the journey. The other half—the moment of truth—is **execution**: transforming those carefully crafted lines of text into living, breathing computation. Understanding the various ways to run Python code is fundamental to productivity, debugging, and effective development workflow.

Python offers multiple execution environments, each optimized for different tasks and stages of development. The **interactive shell** provides immediate feedback, perfect for exploration and rapid prototyping. **Script execution** enables automation and the creation of reusable tools. **Version checking** ensures compatibility and prevents the frustration of running code on the wrong Python interpreter.

This comprehensive guide explores every facet of running Python code: the interactive REPL (Read-Eval-Print Loop) that makes Python uniquely approachable, the various methods for executing scripts ranging from simple one-liners to complex module invocations, and the essential skill of verifying and managing Python versions. By the end, you will possess complete mastery over Python execution, enabling you to move fluidly between exploration, development, and deployment.

---

## Part 1: The Interactive Python Shell – Your Instant Feedback Laboratory

The interactive Python shell, commonly called the **REPL** (Read-Eval-Print Loop), is one of Python's most powerful features. It transforms programming from a write-compile-run cycle into a conversational dialogue with the computer. For security professionals, the REPL is invaluable for testing payloads, exploring APIs, validating assumptions, and debugging in real-time.

### Starting the Interactive Shell

**Basic Invocation:**

```bash
# Start Python 3 interactive shell
python3

# On Windows (if python3 not available)
python

# Using the Python launcher on Windows
py
```

Upon starting, you are greeted with a welcome message and the `>>>` prompt:

```
Python 3.12.0 (main, Oct  2 2023, 10:20:30) [GCC 12.2.0] on linux
Type "help", "copyright", "credits" or "license" for more information.
>>>
```

The `>>>` indicates that Python is ready to receive and execute commands immediately.

**Starting with Specific Options:**

```bash
# Start without reading startup files (clean environment)
python3 -S

# Start with optimization (removes assert statements and docstrings)
python3 -O

# Start in isolated mode (ignores environment variables and user site-packages)
python3 -I

# Start with unbuffered output (useful for logging)
python3 -u

# Start and execute a command, then exit
python3 -c "print('Hello, World!')"

# Start and execute a module as a script
python3 -m http.server 8000
```

### Basic REPL Operations

**Executing Python Statements:**

```python
>>> # Simple arithmetic
>>> 2 + 2
4

>>> # Variable assignment
>>> x = 42
>>> x * 2
84

>>> # String manipulation
>>> "Hello, " + "World!"
'Hello, World!'

>>> # Multi-line statements
>>> for i in range(3):
...     print(f"Iteration {i}")
...
Iteration 0
Iteration 1
Iteration 2
```

Notice how Python automatically provides the `...` continuation prompt for multi-line statements. Indentation is handled intelligently—a blank line completes the block.

**The Underscore Variable (`_`):**

The REPL automatically stores the result of the last expression in the special variable `_`:

```python
>>> 10 + 20
30
>>> _
30
>>> _ * 2
60
>>> ["a", "b", "c"]
['a', 'b', 'c']
>>> _[1]
'b'
```

This feature is enormously convenient for iterative calculations and data exploration.

**Getting Help in the REPL:**

```python
>>> # General help system
>>> help()
help> keywords
help> modules
help> quit

>>> # Help on specific object
>>> help(str)
>>> help(str.upper)

>>> # Quick documentation with ?
>>> # (Available in IPython, not standard REPL)

>>> # List attributes and methods
>>> dir(str)
['__add__', '__class__', '__contains__', ...]

>>> # Check type
>>> type(42)
<class 'int'>
>>> type([1, 2, 3])
<class 'list'>

>>> # View source if available (for pure Python functions)
>>> import os
>>> print(os.getcwd.__doc__)
Return a string representing the current working directory.
```

**Navigating Command History:**

The standard REPL provides basic history navigation using the arrow keys:
- `↑` (Up Arrow): Previous command
- `↓` (Down Arrow): Next command
- `Ctrl+R`: Reverse search through history (in some builds)
- `Ctrl+P`: Previous command (alternative)
- `Ctrl+N`: Next command (alternative)

**Exiting the REPL:**

```python
>>> exit()
# or
>>> quit()
# or
>>> import sys; sys.exit()
# or keyboard interrupt
Ctrl+D (Linux/macOS) or Ctrl+Z then Enter (Windows)
```

### Enhanced REPL: IPython

While the standard Python REPL is functional, **IPython** provides a dramatically enhanced interactive experience that has become the de facto standard for data science, security research, and exploratory programming.

**Installation:**

```bash
pip install ipython
```

**Starting IPython:**

```bash
ipython
```

**IPython Features Security Professionals Love:**

**1. Tab Completion Everywhere:**

```python
In [1]: import requ<tab>
In [1]: import requests

In [2]: requests.<tab>
requests.api          requests.get          requests.packages
requests.codes        requests.head         requests.post
requests.delete       requests.models       requests.put
requests.exceptions   requests.options      requests.sessions
requests.patch        requests.status_codes

In [3]: response = requests.get("https://api.example.com")
In [4]: response.<tab>
response.apparent_encoding  response.iter_lines
response.close              response.json
response.content            response.links
response.cookies            response.ok
response.elapsed            response.raise_for_status
response.encoding           response.raw
response.headers            response.reason
response.history            response.request
response.is_permanent_redirect  response.status_code
response.is_redirect        response.text
response.iter_content       response.url
```

**2. Magic Commands:**

IPython extends Python with "magic" commands prefixed by `%` (line magic) or `%%` (cell magic):

| Magic Command | Purpose | Example |
|:---|:---|:---|
| `%run` | Execute Python script | `%run script.py` |
| `%time` | Time execution of statement | `%time sum(range(1000000))` |
| `%timeit` | Benchmark statement | `%timeit [x**2 for x in range(100)]` |
| `%paste` | Paste from clipboard (auto-cleans) | `%paste` |
| `%cpaste` | Paste multi-line code block | `%cpaste` |
| `%debug` | Enter debugger after exception | `%debug` |
| `%pdb` | Toggle automatic debugger on exception | `%pdb on` |
| `%history` | View command history | `%history -n 1-10` |
| `%save` | Save commands to file | `%save script.py 1-50` |
| `%load` | Load script into REPL | `%load script.py` |
| `%who` / `%whos` | List variables in namespace | `%whos` |
| `%reset` | Clear namespace | `%reset -f` |
| `%env` | Show/set environment variables | `%env PATH` |
| `%cd` | Change directory | `%cd /tmp` |
| `%ls` / `%pwd` | List files / current directory | `%ls -la` |
| `%sx` / `%sc` | Shell command execution | `%sx ls -la` |
| `%pycat` | Show syntax-highlighted file | `%pycat script.py` |
| `%prun` | Profile Python code | `%prun my_function()` |
| `%pdef` | Print function definition | `%pdef requests.get` |
| `%pdoc` | Print function documentation | `%pdoc requests.get` |
| `%psource` | Print function source | `%psource requests.get` |
| `%pfile` | Print file where object is defined | `%pfile requests` |

**3. Shell Integration:**

```python
# Execute shell commands directly
In [5]: !ls -la
total 48
drwxr-xr-x  6 user  staff   192 Jan 15 10:30 .
drwxr-xr-x 12 user  staff   384 Jan 15 09:45 ..

# Capture shell output
In [6]: files = !ls *.py
In [7]: files
Out[7]: ['scanner.py', 'exploit.py', 'recon.py']

# Pass Python variables to shell
In [8]: target = "example.com"
In [9]: !ping -c 1 {target}
PING example.com (93.184.216.34): 56 data bytes
...
```

**4. Rich Output and Visualization:**

```python
# Syntax-highlighted source viewing
In [10]: import requests
In [11]: requests.get??
# Displays full source code with syntax highlighting

# Automatic pretty-printing
In [12]: data = {"user": "admin", "permissions": ["read", "write", "execute"]}
In [13]: data
Out[13]: 
{
    'user': 'admin',
    'permissions': ['read', 'write', 'execute']
}

# Integration with matplotlib
In [14]: %matplotlib inline
In [15]: import matplotlib.pyplot as plt
In [16]: plt.plot([1, 2, 3, 4, 5], [1, 4, 9, 16, 25])
# Plot appears inline in terminal (with appropriate backend)
```

**5. Debugging Integration:**

```python
# Automatic debugger on exception
In [17]: %pdb on
Automatic pdb calling has been turned ON

In [18]: 1 / 0
ZeroDivisionError: division by zero
> <ipython-input-18>(1)<module>()
----> 1 1 / 0

ipdb> p locals()
{}
ipdb> up
ipdb> q

# Post-mortem debugging
In [19]: def crash():
    ...:     return 1 / 0
    ...: 

In [20]: crash()
ZeroDivisionError: division by zero

In [21]: %debug
> <ipython-input-19>(2)crash()
      1 def crash():
----> 2     return 1 / 0

ipdb> 
```

### Security-Focused REPL Workflows

**Exploring Suspicious Data:**

```python
In [1]: import base64
In [2]: import json

# Decode suspected base64 payload
In [3]: encoded = "eyJ1c2VybmFtZSI6ICJhZG1pbiIsICJyb2xlIjogImFkbWluIn0="
In [4]: decoded = base64.b64decode(encoded)
In [5]: decoded
Out[5]: b'{"username": "admin", "role": "admin"}'

In [6]: json.loads(decoded)
Out[6]: {'username': 'admin', 'role': 'admin'}
```

**Testing XSS Payloads:**

```python
In [7]: payloads = [
   ...:     '<script>alert("XSS")</script>',
   ...:     '<img src=x onerror=alert("XSS")>',
   ...:     '<svg onload=alert("XSS")>'
   ...: ]

In [8]: import html
In [9]: for p in payloads:
   ...:     print(f"Original: {p}")
   ...:     print(f"Encoded:  {html.escape(p)}")
   ...:     print()
```

**Network Reconnaissance:**

```python
In [10]: import socket
In [11]: import ipaddress

In [12]: target = "scanme.nmap.org"
In [13]: ip = socket.gethostbyname(target)
In [14]: ip
Out[14]: '45.33.32.156'

In [15]: for port in [22, 80, 443, 8080]:
    ...:     sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    ...:     sock.settimeout(1)
    ...:     result = sock.connect_ex((target, port))
    ...:     if result == 0:
    ...:         print(f"Port {port}: OPEN")
    ...:     sock.close()
```

### Jupyter Notebooks: The Ultimate Interactive Environment

Jupyter Notebooks extend the REPL concept to a web-based, document-centric interactive environment. They are invaluable for security research, incident documentation, and training.

**Installation:**

```bash
pip install jupyterlab notebook
```

**Starting Jupyter:**

```bash
# Classic notebook interface
jupyter notebook

# Modern JupyterLab interface
jupyter lab
```

**Jupyter for Security Workflows:**

1. **Incident Response Playbooks**: Create executable incident response procedures with documentation, code, and visualizations
2. **Threat Intelligence Analysis**: Explore and visualize threat data interactively
3. **Exploit Development**: Test payload components in isolated cells
4. **Training Materials**: Create interactive security training with executable examples
5. **Report Generation**: Export notebooks as PDF/HTML reports with embedded code and results

**Example Security Notebook Cell:**

```python
# Cell 1: Import libraries and setup
import requests
import pandas as pd
import matplotlib.pyplot as plt
from datetime import datetime, timedelta

%matplotlib inline

# Cell 2: Fetch threat intelligence feed
url = "https://feodotracker.abuse.ch/downloads/ipblocklist.csv"
df = pd.read_csv(url, skiprows=8, names=["first_seen", "ip", "port", "type", "malware"])

# Cell 3: Analyze and visualize
df['first_seen'] = pd.to_datetime(df['first_seen'])
recent = df[df['first_seen'] > datetime.now() - timedelta(days=7)]

print(f"Total C2 servers: {len(df)}")
print(f"New in last 7 days: {len(recent)}")

# Cell 4: Plot by malware family
recent['malware'].value_counts().head(10).plot(kind='barh')
plt.title('Top 10 Malware Families (Last 7 Days)')
plt.xlabel('Count')
plt.tight_layout()
plt.show()
```

---

## Part 2: Running Python Scripts – From Source to Execution

While the interactive shell excels at exploration, production code lives in **scripts**—files containing Python code that can be executed repeatedly, shared, and automated.

### Basic Script Execution

**The Simplest Script:**

Create a file named `hello.py`:

```python
#!/usr/bin/env python3
"""
hello.py - Simple Python script demonstrating execution.
"""

print("Hello, World!")
print(f"Python version: {__import__('sys').version}")

# Code that only runs when executed as script
if __name__ == "__main__":
    print("This script was executed directly.")
```

**Executing the Script:**

```bash
# Method 1: Invoke Python interpreter with script as argument
python3 hello.py

# Method 2: Make script executable and run directly (Unix-like systems)
chmod +x hello.py
./hello.py

# Method 3: Using Python launcher on Windows
py hello.py

# Method 4: Execute with explicit Python version
python3.11 hello.py
python3.12 hello.py
```

**Understanding `if __name__ == "__main__"`:**

This crucial idiom distinguishes between a script being run directly versus being imported as a module:

```python
# module_example.py
def useful_function():
    """This function can be imported and used elsewhere."""
    return "Function result"

def _internal_helper():
    """Internal function not meant for external use."""
    pass

if __name__ == "__main__":
    # This code runs ONLY when script is executed directly
    print("Running as standalone script")
    result = useful_function()
    print(result)
else:
    # This code runs when imported as module
    print("Being imported as module")
```

Demonstration:

```python
# When executed directly
$ python3 module_example.py
Running as standalone script
Function result

# When imported
$ python3
>>> import module_example
Being imported as module
>>> module_example.useful_function()
'Function result'
```

### Advanced Script Execution Methods

**1. Passing Command-Line Arguments:**

```python
# args_demo.py
import sys
import argparse

def main():
    # Raw access via sys.argv
    print(f"Script name: {sys.argv[0]}")
    print(f"Arguments: {sys.argv[1:]}")
    
    # Proper argument parsing with argparse
    parser = argparse.ArgumentParser(description="Argument parsing demo")
    parser.add_argument("--target", "-t", required=True, help="Target host")
    parser.add_argument("--port", "-p", type=int, default=80, help="Target port")
    parser.add_argument("--verbose", "-v", action="store_true", help="Verbose output")
    parser.add_argument("--output", "-o", type=argparse.FileType('w'), help="Output file")
    args = parser.parse_args()
    
    if args.verbose:
        print(f"Scanning {args.target}:{args.port}")
    
    # Use arguments
    if args.output:
        args.output.write(f"Scan results for {args.target}\n")

if __name__ == "__main__":
    main()
```

Execution:

```bash
python3 args_demo.py --target example.com --port 443 --verbose
python3 args_demo.py -t 192.168.1.1 -p 22 -o scan_results.txt
```

**2. Executing Python Code from Command Line (`-c` flag):**

```bash
# Execute single statement
python3 -c "print('Hello from command line')"

# Execute multiple statements separated by semicolons
python3 -c "import sys; print(sys.version); print(sys.platform)"

# Execute more complex code using heredoc (Unix)
python3 -c '
import socket
import sys

host = sys.argv[1] if len(sys.argv) > 1 else "localhost"
for port in [22, 80, 443]:
    sock = socket.socket()
    sock.settimeout(1)
    if sock.connect_ex((host, port)) == 0:
        print(f"Port {port}: OPEN")
    sock.close()
' scanme.nmap.org

# On Windows PowerShell (multi-line)
python -c @"
import socket
target = 'scanme.nmap.org'
for port in [22, 80, 443]:
    sock = socket.socket()
    sock.settimeout(1)
    if sock.connect_ex((target, port)) == 0:
        print(f'Port {port}: OPEN')
    sock.close()
"@
```

**3. Executing Modules as Scripts (`-m` flag):**

The `-m` flag runs a module as if it were a script, which is the preferred way to invoke many Python tools:

```bash
# Run built-in HTTP server
python3 -m http.server 8000

# Run pip (alternative to direct pip command)
python3 -m pip install requests

# Run module from current directory
python3 -m mypackage.mymodule

# Run with package resolution (ensures proper import context)
cd /path/to/project
python3 -m pytest tests/

# Profile a script
python3 -m cProfile -s cumulative my_script.py

# Check code style
python3 -m py_compile script.py
python3 -m compileall .

# Create virtual environment
python3 -m venv venv

# Run JSON tool to pretty-print
echo '{"name":"test","value":42}' | python3 -m json.tool

# Run timeit for benchmarking
python3 -m timeit -s "import requests" "requests.get('https://example.com')"

# Zip entire package
python3 -m zipfile -c archive.zip my_directory/
```

**4. Running with Environment Variables:**

```bash
# Set environment variables for script execution
PYTHONPATH=/custom/path python3 script.py
PYTHONUNBUFFERED=1 python3 script.py  # Disable output buffering
PYTHONVERBOSE=1 python3 script.py     # Verbose import logging
PYTHONOPTIMIZE=1 python3 script.py    # Optimize (remove asserts)
PYTHONHASHSEED=0 python3 script.py    # Disable hash randomization

# Using env command
env DEBUG=1 TARGET=example.com python3 scanner.py
```

**5. Piping Code to Python:**

```bash
# Pipe code directly to interpreter
echo "print('Hello from stdin')" | python3

# Pipe multi-line code using heredoc
python3 << 'EOF'
import sys
print(f"Python {sys.version}")
print(f"Platform: {sys.platform}")
EOF

# Pipe from curl (DANGEROUS - never do this with untrusted sources!)
# curl -s http://example.com/script.py | python3

# Generate and execute code dynamically
for port in 80 443 8080; do
    echo "print('Checking port $port')"
done | python3
```

**6. Running in the Background (Unix/Linux):**

```bash
# Run script in background
python3 long_running_scanner.py &

# Run with nohup (survives terminal close)
nohup python3 long_running_scanner.py > output.log 2>&1 &

# Run with output redirection
python3 scanner.py > results.txt 2> errors.log

# Run and disown
python3 scanner.py &
disown
```

### Script Execution in Different Environments

**Virtual Environment Execution:**

```bash
# Activate environment first
source venv/bin/activate
python script.py  # Uses venv's Python and packages

# Or use venv's Python directly (no activation needed)
venv/bin/python script.py

# On Windows
venv\Scripts\python.exe script.py
```

**Docker Container Execution:**

```bash
# Run Python script in container
docker run --rm -v $(pwd):/app -w /app python:3.12 python script.py

# Run with custom image
docker run --rm my-security-tool python /app/scanner.py --target example.com

# Interactive container
docker run -it --rm python:3.12 python
```

**Remote Execution via SSH:**

```bash
# Execute script on remote server
ssh user@server "python3 /path/to/script.py"

# Execute with arguments
ssh user@server "python3 scanner.py --target internal-host"

# Pipe local script to remote execution
cat local_script.py | ssh user@server python3

# Execute remote script with local output
ssh user@server "python3 /path/to/script.py" > local_results.txt
```

**Scheduled Execution (Cron):**

```bash
# Edit crontab
crontab -e

# Run script every hour
0 * * * * /usr/bin/python3 /home/user/scanner.py --target example.com >> /var/log/scanner.log 2>&1

# Run script with virtual environment
0 */6 * * * cd /home/user/project && ./venv/bin/python scanner.py
```

### Debugging Script Execution

**Common Execution Errors and Solutions:**

| Error | Cause | Solution |
|:---|:---|:---|
| `python: command not found` | Python not installed or not in PATH | Install Python or add to PATH |
| `ModuleNotFoundError` | Missing dependency | `pip install missing-module` |
| `SyntaxError` | Python 2 vs 3 syntax | Use `python3` explicitly |
| `Permission denied` | Script not executable | `chmod +x script.py` |
| `ImportError` | Wrong Python environment | Activate correct venv |
| `IndentationError` | Mixed tabs/spaces | Use consistent indentation |
| `FileNotFoundError` | Wrong working directory | Use absolute paths or `os.chdir()` |

**Debugging Techniques:**

```python
# Add debug output
import sys
print(f"Python version: {sys.version}", file=sys.stderr)
print(f"Working directory: {os.getcwd()}", file=sys.stderr)
print(f"Sys.path: {sys.path}", file=sys.stderr)

# Run with verbose import logging
python3 -v script.py

# Run with debugger
python3 -m pdb script.py

# Run and drop into debugger on exception
python3 -m pdb -c continue script.py

# Profile execution
python3 -m cProfile -o profile.out script.py
python3 -m pstats profile.out
```

---

## Part 3: Python Version Checking and Management

Knowing which Python version you are running is critical. Code written for Python 3.12 may not work on Python 3.8. Libraries have version requirements. Security vulnerabilities affect specific versions. Mastering version identification and management prevents countless hours of debugging.

### Checking Python Version

**1. Command Line Version Check:**

```bash
# Primary method
python3 --version
python3 -V

# Check specific Python executable
/usr/bin/python3 --version
./venv/bin/python --version

# Check Python 2 (if installed)
python --version
python2 --version

# Windows Python launcher
py --version
py -3.11 --version
py -3.12 --version

# Get more detailed version information
python3 -c "import sys; print(sys.version)"
```

**Sample Output:**
```
Python 3.12.0
Python 3.12.0 (main, Oct  2 2023, 10:20:30) [GCC 12.2.0] on linux
```

**2. Programmatic Version Checking in Python:**

```python
import sys
import platform

# Basic version info
print(f"Python version: {sys.version}")
print(f"Version info tuple: {sys.version_info}")
print(f"Major: {sys.version_info.major}")
print(f"Minor: {sys.version_info.minor}")
print(f"Micro: {sys.version_info.micro}")

# Platform-specific information
print(f"Platform: {sys.platform}")
print(f"Implementation: {platform.python_implementation()}")
print(f"Compiler: {platform.python_compiler()}")
print(f"Build: {platform.python_build()}")

# Detailed system information
print(f"System: {platform.system()}")
print(f"Release: {platform.release()}")
print(f"Machine: {platform.machine()}")
print(f"Processor: {platform.processor()}")
```

**3. Version Checking for Compatibility:**

```python
import sys

# Check minimum version
MIN_VERSION = (3, 8)

if sys.version_info < MIN_VERSION:
    raise SystemExit(f"Python {'.'.join(map(str, MIN_VERSION))} or higher required")

# Feature detection (preferred over version checking)
try:
    # Try to use feature available in newer Python
    from importlib.metadata import version
except ImportError:
    # Fall back to older method
    import pkg_resources
    def version(pkg):
        return pkg_resources.get_distribution(pkg).version

# Conditional code based on version
if sys.version_info >= (3, 11):
    # Use Python 3.11+ features
    from typing import Self
else:
    # Use compatibility approach
    from typing_extensions import Self

if sys.version_info >= (3, 10):
    # Use match statement (Python 3.10+)
    match status_code:
        case 200:
            return "OK"
        case 404:
            return "Not Found"
        case _:
            return "Unknown"
else:
    # Use if-elif chain
    if status_code == 200:
        return "OK"
    elif status_code == 404:
        return "Not Found"
    else:
        return "Unknown"
```

**4. Checking Package Versions:**

```python
# Check individual package version
import requests
print(f"requests version: {requests.__version__}")

# Check all installed packages (from command line)
# pip list
# pip show requests

# Programmatic package version check
import importlib.metadata

try:
    version = importlib.metadata.version("requests")
    print(f"requests: {version}")
except importlib.metadata.PackageNotFoundError:
    print("requests not installed")

# Check all installed packages
for dist in importlib.metadata.distributions():
    print(f"{dist.metadata['Name']}: {dist.version}")
```

**5. Security-Focused Version Checking:**

```python
import sys
import ssl
import hashlib

def check_python_security():
    """Check Python version for known security issues."""
    
    issues = []
    
    # Check for end-of-life versions
    if sys.version_info < (3, 8):
        issues.append("Python < 3.8 is end-of-life and no longer receives security updates")
    
    # Check for specific vulnerable versions
    vulnerable_versions = [
        ((3, 10, 0), (3, 10, 4)),   # Example range
        ((3, 9, 0), (3, 9, 12)),
    ]
    
    for min_ver, max_ver in vulnerable_versions:
        if min_ver <= sys.version_info < max_ver:
            issues.append(f"Python {'.'.join(map(str, sys.version_info[:3]))} has known vulnerabilities")
    
    # Check OpenSSL version
    openssl_version = ssl.OPENSSL_VERSION
    if "1.1.1" in openssl_version:
        # Check specific OpenSSL patch level
        pass
    
    # Check hash algorithm availability
    try:
        hashlib.sha3_256()
    except AttributeError:
        issues.append("SHA-3 not available (Python < 3.6)")
    
    return issues

issues = check_python_security()
if issues:
    print("⚠️ Security concerns detected:")
    for issue in issues:
        print(f"  - {issue}")
else:
    print("✓ No immediate security concerns detected")
```

### Managing Multiple Python Versions

Security professionals often need multiple Python versions for testing exploits, analyzing malware, or supporting legacy tools.

**1. pyenv – The Universal Version Manager:**

```bash
# Install pyenv
git clone https://github.com/pyenv/pyenv.git ~/.pyenv
echo 'export PYENV_ROOT="$HOME/.pyenv"' >> ~/.bashrc
echo 'export PATH="$PYENV_ROOT/bin:$PATH"' >> ~/.bashrc
echo 'eval "$(pyenv init -)"' >> ~/.bashrc
source ~/.bashrc

# List available Python versions
pyenv install --list | grep "^  3\."

# Install specific versions
pyenv install 3.8.18
pyenv install 3.9.18
pyenv install 3.10.13
pyenv install 3.11.8
pyenv install 3.12.2

# Set global default version
pyenv global 3.12.2

# Set version for current directory
cd ~/projects/legacy-tool
pyenv local 3.8.18

# Set version for current shell session
pyenv shell 3.11.8

# Check current version
pyenv version
python --version

# List installed versions
pyenv versions
```

**2. Using Docker for Version Isolation:**

```dockerfile
# Dockerfile for Python 3.8 environment
FROM python:3.8-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY . .
CMD ["python", "scanner.py"]
```

```bash
# Run with specific Python version
docker run --rm python:3.9 python --version
docker run --rm python:3.11 python --version

# Run script in specific version
docker run --rm -v $(pwd):/app -w /app python:3.8 python legacy_script.py
```

**3. conda for Scientific Python Versions:**

```bash
# Install Miniconda
wget https://repo.anaconda.com/miniconda/Miniconda3-latest-Linux-x86_64.sh
bash Miniconda3-latest-Linux-x86_64.sh

# Create environments with specific Python versions
conda create -n py38 python=3.8
conda create -n py311 python=3.11
conda create -n security python=3.12 requests cryptography

# Activate environment
conda activate py38
python --version

# List environments
conda env list

# Export environment
conda env export > environment.yml
```

**4. Windows Python Launcher (`py`):**

```cmd
# List installed Python versions
py --list
py -0

# Run with specific version
py -3.8 script.py
py -3.11 script.py
py -3.12 script.py

# Set default version (via environment variable)
set PY_PYTHON=3.12

# Run pip for specific version
py -3.11 -m pip install requests
```

### Version Compatibility Best Practices

**1. Specify Python Version Requirements:**

```python
# setup.py
from setuptools import setup

setup(
    name="my-security-tool",
    python_requires=">=3.8,<3.13",
)

# pyproject.toml
[project]
name = "my-security-tool"
requires-python = ">=3.8,<3.13"
```

**2. Use `sys.version_info` for Runtime Checks:**

```python
import sys

if sys.version_info < (3, 8):
    raise RuntimeError("Python 3.8 or higher required")

# Use features conditionally
if sys.version_info >= (3, 9):
    # Use dict union operator (Python 3.9+)
    merged = dict1 | dict2
else:
    # Fallback for older versions
    merged = {**dict1, **dict2}
```

**3. Test Across Multiple Versions with tox:**

```ini
# tox.ini
[tox]
envlist = py38, py39, py310, py311, py312

[testenv]
deps = pytest
commands = pytest tests/
```

```bash
pip install tox
tox  # Runs tests on all available Python versions
```

**4. Use `__future__` Imports for Forward Compatibility:**

```python
from __future__ import annotations  # Postponed evaluation of annotations
from __future__ import division     # True division
from __future__ import print_function  # print as function
from __future__ import unicode_literals  # String literals are Unicode
```

---

## Part 4: Complete Execution Reference

### Quick Reference: Ways to Run Python

| Method | Command | Use Case |
|:---|:---|:---|
| Interactive REPL | `python3` | Exploration, testing |
| Run script | `python3 script.py` | Execute saved code |
| Run module | `python3 -m module_name` | Run module as script |
| Execute string | `python3 -c "print('hi')"` | One-liners |
| Pipe to Python | `echo "code" \| python3` | Dynamic code |
| Make executable | `./script.py` (with shebang) | Command-line tools |
| IPython | `ipython` | Enhanced REPL |
| Jupyter | `jupyter lab` | Notebook environment |
| Background | `python3 script.py &` | Long-running tasks |
| Scheduled | Cron/systemd timer | Automation |

### Common Execution Patterns for Security Tools

**Pattern 1: Simple Scanner Script**

```bash
# Single-target scan
python3 scanner.py --target example.com

# Multi-target scan from file
python3 scanner.py --target-file targets.txt --output results.json

# Scan with custom ports and threads
python3 scanner.py -t 192.168.1.0/24 -p 1-1000 --threads 200
```

**Pattern 2: Interactive Analysis**

```bash
# Start IPython for data exploration
ipython

# Load and analyze scan results
In [1]: import json
In [2]: with open('results.json') as f:
   ...:     data = json.load(f)
In [3]: [host for host in data if host['open_ports']]
```

**Pattern 3: Scheduled Automation**

```bash
# Crontab entry for daily vulnerability scan
0 2 * * * cd /opt/security && ./venv/bin/python scanner.py --config daily.yaml >> /var/log/scanner.log 2>&1
```

**Pattern 4: Dockerized Tool Execution**

```bash
# Run security tool in isolated container
docker run --rm -v $(pwd)/output:/output security-scanner \
    python scanner.py --target example.com --output /output/results.json
```

**Pattern 5: Remote Execution Chain**

```bash
# SSH to jump host and execute scanner
ssh user@jump-host "python3 /opt/tools/internal-scanner.py --target internal-app"
```

---

## Conclusion: Mastering Python Execution

You have now mastered the art of running Python code in all its forms:

**The Interactive Shell** provides an immediate feedback loop that accelerates learning, debugging, and exploration. Whether using the standard REPL, the enhanced IPython environment, or Jupyter Notebooks, you can converse with Python in real-time, testing hypotheses and iterating rapidly.

**Script Execution** transforms your code into reusable, automatable tools. You understand the various invocation methods—direct execution, module execution, command-line strings—and know how to pass arguments, manage environments, and integrate with system automation.

**Version Management** ensures your code runs where and how you expect. You can check Python versions, manage multiple installations, and write version-aware code that gracefully handles differences across Python releases.

These skills form the practical foundation of Python proficiency. They enable you to move fluidly between exploration and production, between your local machine and remote servers, between development and deployment. With this knowledge, you are equipped to write, test, and run Python code in any context—from quick one-liners to complex security automation systems.

The Python interpreter awaits. Run something.

---

## Further Resources

**Official Documentation:**
- [Python Command Line Documentation](https://docs.python.org/3/using/cmdline.html)
- [Python Tutorial: Interpreter](https://docs.python.org/3/tutorial/interpreter.html)
- [sys — System-specific Parameters](https://docs.python.org/3/library/sys.html)

**IPython and Jupyter:**
- [IPython Documentation](https://ipython.readthedocs.io/)
- [Jupyter Documentation](https://jupyter.org/documentation)
- [IPython Magics Reference](https://ipython.readthedocs.io/en/stable/interactive/magics.html)

**Version Management:**
- [pyenv GitHub Repository](https://github.com/pyenv/pyenv)
- [Python Version Management Guide](https://realpython.com/intro-to-pyenv/)
- [PEP 440 – Version Identification and Dependency Specification](https://peps.python.org/pep-0440/)
