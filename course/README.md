# Bug Bounty & Web Penetration Testing Bootcamp
## Complete Course

---

## 📋 TABLE OF CONTENTS

1. [Module 1: Programming Fundamentals](#module-1)
2. [Module 2: Operating Systems Essentials](#module-2)
3. [Module 3: Networking Fundamentals](#module-3)
4. [Module 4: Web Technologies & Architecture](#module-4)
5. [Module 5: Introduction to Cybersecurity](#module-5)
6. [Module 6: Scripting for Security](#module-6)
7. [Module 7: Tools & Environment Setup](#module-7)
8. [Module 8: Lab Exercises & Practical Projects](#module-8)

---

# MODULE 1: PROGRAMMING FUNDAMENTALS {#module-1}

## 1.1 Introduction to Programming

### 1.1.1 What is Programming?
- [Definition and purpose](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Definition%20and%20purpose.md)
- [Why programming matters in cybersecurity](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Why%20programming%20matters%20in%20cybersecurity.md)
- [How code execution works](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/How%20code%20execution%20works.md)
- [Compiled vs Interpreted languages](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Compiled%20vs%20Interpreted%20languages.md)

### 1.1.2 Programming Languages Overview
- [Python](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python.md)
  - [Why Python for security](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python.md#part-1-why-python-for-security--the-foundational-argument)
  - [Ease of learning](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python.md#part-2-ease-of-learning--the-democratization-of-security-automation)
  - [Extensive libraries](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python.md#part-3-extensive-libraries--the-force-multiplier)
  - [Industry standard](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python.md#part-4-industry-standard--the-network-effect-and-institutional-adoption)
- [Bash/Shell Scripting](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Bash-Shell%20Scripting.md)
  - [System automation](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Bash-Shell%20Scripting.md#part-2-system-automation--orchestrating-security-at-scale)
  - [Linux interaction](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Bash-Shell%20Scripting.md#part-3-linux-interaction--the-deep-integration-advantage)
  - [Quick scripting](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Bash-Shell%20Scripting.md#part-4-quick-scripting--the-art-of-the-security-one-liner)
- [JavaScript](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/JavaScript%20for%20Security%20Professionals.md)
  - [Web security testing](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/JavaScript%20for%20Security%20Professionals.md#part-2-web-security-testing-with-javascript)
  - [DOM manipulation](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/JavaScript%20for%20Security%20Professionals.md#part-3-dom-manipulation-and-security-implications)
  - [Client-side vulnerabilities](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/JavaScript%20for%20Security%20Professionals.md#part-4-client-side-vulnerabilities-deep-dive)
- [C/Assembly](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/C-Assembly.md)
  - [Low-level understanding](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/C-Assembly.md#part-1-low-level-understanding--the-foundation-of-system-security)
  - [Buffer overflow concepts](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/C-Assembly.md#part-2-buffer-overflow-concepts--the-canonical-vulnerability)
  - [Reverse engineering basics](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/C-Assembly.md#part-3-reverse-engineering-basics)

---

## 1.2 Python Programming

### 1.2.1 Getting Started with Python

#### Installation and Setup
- [Installing Python 3](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python%20Programming.md#part-1-installing-python-3--the-foundation)
- [Setting up IDE/Text Editor](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python%20Programming.md#part-2-setting-up-your-ide-or-text-editor)
  - [VS Code](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python%20Programming.md#visual-studio-code-vs-code--the-modern-standard)
  - [PyCharm](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python%20Programming.md#pycharm--the-professional-python-ide)
  - [Vim/Nano](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python%20Programming.md#vimneovim--the-terminal-warriors-choice)
- Virtual environments
  - [Creating virtual environments](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python%20Programming.md#part-3-virtual-environments--the-professionals-best-friend)
  - [Managing dependencies](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Python%20Programming.md#managing-dependencies-with-pip)
  - pip package manager

#### Running Python Code
- [Interactive Python shell](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Running%20Python%20Code.md#part-1-the-interactive-python-shell--your-instant-feedback-laboratory)
- [Running scripts](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Running%20Python%20Code.md#part-2-running-python-scripts--from-source-to-execution)
- [Python version checking](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/Running%20Python%20Code.md#part-3-python-version-checking-and-management)

### 1.2.2 Python Basics

#### Variables and Data Types
- [Strings](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#part-2-strings--working-with-text)
  - [String creation](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#string-creation)
  - [String operations](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#string-operations)
  - [String methods (upper, lower, replace, split, etc.)](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#essential-string-methods-for-security)
- [Numbers](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#part-3-numbers--integers-floats-and-arithmetic)
  - [Integers](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#integers-int)
  - [Floats](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#floating-point-numbers-float)
  - [Arithmetic operations](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#arithmetic-operations-in-security-contexts)
- [Lists](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#part-4-lists--ordered-mutable-collections)
  - [Creating lists](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#creating-lists)
  - [Accessing elements](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#accessing-and-slicing-lists)
  - [List methods (append, remove, extend, etc.)](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#essential-list-methods)
- [Dictionaries](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#part-5-dictionaries--key-value-mappings)
  - [Key-value pairs](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#creating-dictionaries)
  - [Accessing values](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#accessing-dictionary-values)
  - [Dictionary methods](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#dictionary-methods-for-security-tasks)
- [Tuples](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#part-6-tuples--immutable-sequences)
  - [Immutable sequences](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#creating-tuples)
  - [When to use tuples](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#accessing-tuples)
  - [Unpacking tuples](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#tuple-unpacking)
- [Sets](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#part-7-sets--unique-unordered-collections)
  - Unique elements
  - [Set operations](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#creating-sets)
  - [Set methods](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#modifying-sets)
- [Booleans](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#part-8-booleans--true-and-false-logic)
  - [True/False values](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#boolean-values-and-operations)
  - [Boolean operations](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Variables%20and%20Data%20Types.md#boolean-values-and-operations)

#### Operators
- [Arithmetic operators](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Operators.md#part-1-arithmetic-operators--the-mathematical-foundation) (+, -, *, /, //, %, **)
- [Comparison operators](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Operators.md#part-2-comparison-operators--making-decisions) (==, !=, >, <, >=, <=)
- [Logical operators](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Operators.md#part-3-logical-operators--combining-conditions) (and, or, not)
- [Assignment operators](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Operators.md#part-4-assignment-operators--setting-values) (=, +=, -=, etc.)
- [Membership operators](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Operators.md#part-5-membership-operators--searching-collections) (in, not in)
- [Identity operators (is, is not)](https://github.com/aw-junaid/bug-bounty/blob/main/course/module-1/programming/python/Operators.md#part-7-operator-precedence-complete-reference)

### 1.2.3 Control Flow

#### Conditional Statements
- If statements
- Else statements
- Elif statements
- Nested conditionals
- Conditional expressions (ternary operator)

#### Loops
- For loops
  - Iterating through lists
  - Range function
  - Enumerate
  - Break and continue
- While loops
  - Conditional loops
  - Infinite loops
  - Break and continue
- Loop control
  - Break statement
  - Continue statement
  - Pass statement

### 1.2.4 Functions

#### Function Basics
- Defining functions
- Function parameters
- Return statements
- Default parameters
- Variable-length arguments (*args, **kwargs)

#### Variable Scope
- Local scope
- Global scope
- Nonlocal scope
- Scope resolution (LEGB rule)

#### Advanced Functions
- Lambda functions
- List comprehensions
- Dictionary comprehensions
- Generator functions
- Decorators (basics)

### 1.2.5 Working with Data Structures

#### Lists
- Creating and modifying
- Indexing and slicing
- List methods
- Iterating through lists
- List comprehensions

#### Dictionaries
- Creating dictionaries
- Accessing keys and values
- Adding and removing items
- Dictionary methods
- Iterating through dictionaries

#### Sets
- Creating sets
- Set operations (union, intersection, difference)
- Set methods
- Use cases for sets

#### Tuples
- Immutability
- Tuple unpacking
- Named tuples (collections.namedtuple)

### 1.2.6 Error Handling

#### Exception Handling
- Try-except blocks
- Multiple exception handling
- Except-else-finally
- Raising exceptions
- Custom exceptions

#### Common Exceptions
- ValueError
- TypeError
- KeyError
- IndexError
- FileNotFoundError
- ConnectionError

### 1.2.7 File Operations

#### Reading Files
- Opening files
- Reading content
- Reading line by line
- Closing files
- Context managers (with statement)

#### Writing Files
- Writing new files
- Appending to files
- Overwriting files

#### Working with Paths
- os.path module
- pathlib module
- Checking file existence
- Getting file information

#### Parsing Different Formats
- Text files
- CSV files
- JSON files
- XML files

### 1.2.8 Modules and Libraries

#### Importing Modules
- Import statements
- From imports
- Aliases
- Module path management

#### Built-in Modules
- os - Operating system interaction
- sys - System parameters
- json - JSON parsing
- re - Regular expressions
- time - Time operations
- datetime - Date and time handling
- random - Random number generation
- math - Mathematical operations
- hashlib - Cryptographic hashes
- base64 - Base64 encoding/decoding

#### Third-party Libraries for Security
- requests - HTTP library
- socket - Network sockets
- paramiko - SSH client
- beautifulsoup4 - HTML parsing
- urllib3 - HTTP client
- cryptography - Cryptographic functions
- pycryptodome - Cryptography library
- scapy - Packet manipulation

#### Package Management
- pip installation
- requirements.txt
- Virtual environments
- Version pinning

---

## 1.3 Bash/Shell Scripting

### 1.3.1 Introduction to Bash

#### What is Bash?
- Shell concept
- Command interpreter
- Scripting language
- Linux standard shell

#### Getting Started
- Accessing terminal/command line
- Bash vs other shells
- Shebang (#!)
- Executing scripts
- Script permissions (chmod)

### 1.3.2 Bash Basics

#### Variables
- Variable assignment
- Using variables ($VAR, ${VAR})
- Variable naming conventions
- Environment variables
- Special variables ($0, $1, $?, $!, $#, $@, $*)

#### Data Types
- Strings
  - Single quotes (literal)
  - Double quotes (with substitution)
  - Command substitution ($(), backticks)
  - String concatenation
- Numbers
  - Arithmetic expansion $(())
  - Integer operations
- Arrays
  - Indexed arrays
  - Associative arrays
  - Array operations

#### Operators
- Arithmetic operators
- Comparison operators
- Logical operators
- String operators
- File test operators

### 1.3.3 Control Structures

#### Conditional Statements
- If-then-else
- Elif statements
- Case statements
- Test conditionals [ ]
- [[ ]] extended test

#### Loops
- For loops
  - C-style for loops
  - For-in loops
- While loops
- Until loops
- Break and continue
- Loop control

#### Pattern Matching
- Globbing
- Regular expressions (basic)
- Case pattern matching

### 1.3.4 Functions

#### Creating Functions
- Function definition
- Function parameters ($1, $2, etc.)
- Return values
- Local variables
- Function scope

#### Advanced Functions
- Recursive functions
- Function libraries
- Sourcing functions

### 1.3.5 Text Processing

#### Grep
- Pattern searching
- Case-insensitive search (-i)
- Count matches (-c)
- Recursive search (-r)
- Inverse match (-v)
- Regular expressions (-E, -P)

#### Sed
- Stream editing
- Substitution (s///)
- Deletion (d)
- Insertion (i)
- Appending (a)
- Line ranges
- In-place editing (-i)

#### Awk
- Field processing
- Pattern-action structure
- Built-in variables
- String functions
- Numeric functions
- Arrays

#### Cut
- Field extraction
- Delimiter specification
- Column ranges

#### Sort
- Sorting data
- Reverse sort (-r)
- Numeric sort (-n)
- Key-based sorting
- Unique values (uniq)

### 1.3.6 Input/Output and Redirection

#### Redirection
- Standard input (stdin)
- Standard output (stdout)
- Standard error (stderr)
- Output redirection (>, >>)
- Input redirection (<)
- Combining stdout and stderr (2>&1, &>)

#### Piping
- Pipe operator (|)
- Command chaining
- Multiple pipes
- Process substitution

#### Here Documents
- Multi-line input
- Here strings
- Delimiter variations

#### File Descriptors
- Understanding file descriptors
- Custom file descriptors
- Closing file descriptors

### 1.3.7 Advanced Bash Features

#### Command Substitution
- $() syntax
- Backticks
- Nesting substitution
- Variable assignment

#### Arithmetic Expansion
- $(()) syntax
- Arithmetic operations
- Variable arithmetic
- Comparison in arithmetic

#### Brace Expansion
- Sequence generation
- Pattern expansion
- Variable expansion

#### Process Substitution
- <() and >() operators
- Use cases
- Parallel processing

#### Job Control
- Running processes in background (&)
- Foreground/background switching
- Job listing (jobs)
- Process management (fg, bg)

### 1.3.8 Error Handling and Debugging

#### Exit Codes
- Understanding exit codes
- $? variable
- Success and failure codes
- Conditional execution (&&, ||)

#### Error Handling
- Set options (set -e, set -x)
- Trap signals
- Error messages to stderr
- Graceful error handling

#### Debugging
- Debug mode (-x)
- Verbose mode (-v)
- Error output
- Test modes

---

## 1.4 JavaScript for Web Security

### 1.4.1 JavaScript Fundamentals

#### Getting Started
- JavaScript execution environments
- Browser console
- Node.js
- Script execution order

#### Variables and Declarations
- var keyword
- let keyword
- const keyword
- Scope differences
- Hoisting

### 1.4.2 Data Types and Values

#### Primitive Types
- String
  - String literals
  - Template literals
  - String methods
  - String concatenation
- Number
  - Integers and floats
  - Special values (Infinity, NaN)
  - Numeric methods
  - Type coercion
- Boolean
  - True and false
  - Truthy and falsy values
- Null and undefined
  - Differences
  - Type checking
- Symbol
  - Unique identifiers
  - Use cases

#### Objects
- Object literals
- Properties and methods
- Accessing properties (dot and bracket notation)
- Adding and deleting properties
- Object methods

#### Arrays
- Array literals
- Indexing and length
- Array methods
  - push, pop, shift, unshift
  - slice, splice
  - join, split
  - map, filter, reduce
  - forEach, find, some, every
  - sort, reverse

#### Functions
- Function declarations
- Function expressions
- Arrow functions
- Parameters and arguments
- Default parameters
- Rest parameters (...args)
- Return values

### 1.4.3 Control Flow

#### Conditional Statements
- If-else statements
- Switch statements
- Ternary operator

#### Loops
- For loops
- While loops
- Do-while loops
- For-in loops
- For-of loops
- Loop control (break, continue)

### 1.4.4 Objects and Prototypes

#### Object-Oriented Programming
- Constructor functions
- Classes
  - Class declaration
  - Constructor method
  - Methods
  - Properties
- Inheritance
  - Prototype chain
  - Extends keyword
  - Super keyword
- Encapsulation
  - Private fields
  - Getters and setters

#### This Keyword
- Context binding
- Arrow function binding
- Method calls
- Constructors
- Call, apply, bind methods

### 1.4.5 Asynchronous JavaScript

#### Callbacks
- Callback functions
- Callback hell/pyramid of doom
- Error handling with callbacks

#### Promises
- Promise states (pending, resolved, rejected)
- Creating promises
- Then method
- Catch method
- Finally method
- Promise.all, Promise.race

#### Async/Await
- Async functions
- Await keyword
- Error handling (try-catch)
- Sequential vs parallel execution

#### Fetch API
- Making HTTP requests
- Request options
- Response handling
- Error handling
- Headers and authentication

### 1.4.6 DOM Manipulation

#### Selecting Elements
- getElementById
- querySelector
- querySelectorAll
- getElementsByClassName
- getElementsByTagName

#### Modifying Elements
- textContent vs innerText
- innerHTML
- Creating elements (createElement)
- Appending and inserting
- Removing elements
- Cloning elements

#### Attributes and Properties
- Getting attributes (getAttribute)
- Setting attributes (setAttribute)
- Removing attributes
- Property access
- Data attributes (data-*)
- Class manipulation (classList)

#### Styling
- Inline styles
- Class-based styling
- Computed styles
- Style properties

#### Events
- Event listeners (addEventListener)
- Event removal (removeEventListener)
- Event object
- Event bubbling and capturing
- Event delegation
- Preventing defaults (preventDefault)
- Stopping propagation (stopPropagation)
- Common events
  - Click, hover, submit
  - Keyboard events
  - Input and change events
  - Load and unload events

### 1.4.7 Security Considerations

#### XSS Prevention
- Script injection risks
- DOM-based XSS
- Safe DOM manipulation
- Content Security Policy
- Input validation and sanitization

#### CSRF Protection
- Token-based protection
- SameSite cookies
- Double-submit cookies

#### Secure Coding Practices
- Input validation
- Output encoding
- Secure storage (avoiding localStorage for sensitive data)
- HTTPS enforcement
- Secure headers

#### Authentication Security
- Credential handling
- Session management
- Token storage
- Secure password practices

---

## 1.5 Programming Concepts Review

### 1.5.1 Core Programming Principles
- Variables and scope
- Functions and modularity
- Data structures
- Control flow
- Error handling
- File I/O
- Modules and libraries

### 1.5.2 Security-Focused Coding
- Input validation
- Output encoding
- Error handling security
- Secure logging
- Avoiding hardcoded secrets
- Following OWASP guidelines

### 1.5.3 Code Organization
- Code structure
- Comments and documentation
- Naming conventions
- DRY principle (Don't Repeat Yourself)
- Code reusability

### 1.5.4 Common Mistakes and How to Avoid Them
- Type errors
- Off-by-one errors
- Logic errors
- Infinite loops
- Memory leaks
- Unhandled exceptions

---

## 1.6 Practice Exercises and Projects

### 1.6.1 Python Projects
- Port scanner script
- Password strength checker
- File hash calculator
- Log file analyzer
- Network utilities

### 1.6.2 Bash Projects
- System monitoring script
- Log analyzer
- Backup automation
- User management script
- Network diagnostics

### 1.6.3 JavaScript Projects
- Form validator
- DOM security tester
- Request interceptor
- Cookie analyzer
- DOM vulnerability scanner

### 1.6.4 Multi-Language Integration
- Python backend with Bash automation
- JavaScript frontend with API calls
- Script integration

---

## 1.7 Module 1 Assessment

### 1.7.1 Knowledge Check
- Variable scope questions
- Function behavior
- Data structure operations
- Control flow logic
- Error handling concepts

### 1.7.2 Practical Exercises
- Writing functions
- Data manipulation
- File operations
- Loop constructs
- Error handling

### 1.7.3 Mini Project
- Combine multiple languages
- Solve real problem
- Apply security concepts
- Code documentation

---

---

# MODULE 2: OPERATING SYSTEMS ESSENTIALS {#module-2}

## 2.1 Introduction to Operating Systems

### 2.1.1 OS Fundamentals
- Definition of operating system
- Role and responsibilities
- Hardware abstraction
- Resource management
- User and kernel modes

### 2.1.2 Why OS Knowledge Matters
- Privilege escalation understanding
- File permission exploits
- Process management vulnerabilities
- System hardening
- Security tool usage
- Vulnerability patching

### 2.1.3 Common Operating Systems
- Linux distributions
  - Ubuntu
  - Debian
  - CentOS
  - Kali Linux
  - ParrotOS
- Windows
  - Windows Server
  - Windows 10/11
  - Windows security features
- macOS
  - Unix-based system
  - Security features
  - Development platform

### 2.1.4 OS Architecture
- Kernel
- Shell
- Filesystem
- Utilities and programs
- Libraries and APIs

---

## 2.2 Linux Fundamentals

### 2.2.1 Linux Overview

#### What is Linux?
- Open-source operating system
- Unix-like system
- Kernel and distributions
- Free and freely distributable
- Community-driven development

#### Why Linux for Security?
- Widespread in servers
- Transparency (open-source)
- Powerful command-line tools
- Security features
- Community support

### 2.2.2 Linux Architecture

#### Kernel
- Core of the OS
- Hardware management
- Process management
- Memory management
- File system management

#### Shell
- Command interpreter
- Bash
- Zsh
- Fish
- Script execution

#### Filesystem
- Hierarchical structure
- Files and directories
- Inodes
- File types

#### System Libraries
- Standard C library (libc)
- System call interfaces
- API abstractions

#### Utilities and Programs
- Command-line tools
- System utilities
- User applications
- Daemons and services

### 2.2.3 Linux Directory Structure

#### Root Directory (/)
```
/ - Root of filesystem
├── /bin - Essential binaries
├── /boot - Boot files and kernel
├── /dev - Device files
├── /etc - System configuration
├── /home - User home directories
├── /lib - System libraries
├── /media - Removable media
├── /mnt - Temporary mount points
├── /opt - Optional software
├── /proc - Process information
├── /root - Root user home
├── /run - Runtime data
├── /sbin - System binaries
├── /srv - Service data
├── /sys - System information
├── /tmp - Temporary files
├── /usr - User programs and data
├── /var - Variable data
└── /var/log - Log files
```

#### Important Directories
- /etc
  - Configuration files
  - System settings
  - Application configs
- /home
  - User home directories
  - Personal files
  - User configurations
- /var/log
  - System logs
  - Application logs
  - Security logs
- /tmp
  - Temporary storage
  - Session files
  - Cache files
- /opt
  - Optional software
  - Third-party applications
  - Custom software

### 2.2.4 Users and Groups

#### User Management
- User accounts
  - Root (UID 0)
  - System accounts
  - Regular users
- User identification
  - UID (User ID)
  - Username
  - Home directory
  - Shell
- User databases
  - /etc/passwd
  - /etc/shadow
  - /etc/group

#### User Commands
- whoami
- id
- groups
- passwd
- useradd
- userdel
- usermod
- su and sudo

#### Group Management
- Group accounts
- Group membership
- Adding/removing users from groups
- Group permissions

#### Privilege Escalation
- Understanding sudo
- Sudo configuration (/etc/sudoers)
- Password-less sudo
- Privilege levels
- Privilege escalation vulnerabilities

### 2.2.5 File Permissions and Ownership

#### Understanding Permissions
- Read (r) - 4
- Write (w) - 2
- Execute (x) - 1
- Owner permissions
- Group permissions
- Other permissions

#### Permission Representation
- Symbolic notation (rwxrwxrwx)
- Octal notation (777, 755, 644)
- Three-digit breakdown
- Special permissions (setuid, setgid, sticky bit)

#### Changing Permissions
- chmod command
  - Symbolic mode (u+x, g-w, o=r)
  - Octal mode (755, 644)
  - Recursive permissions
- chown command
  - Changing owner
  - Changing group
  - Recursive ownership change
- chgrp command
  - Changing group only
  - Recursive group change

#### Special Permissions
- SetUID (4xxx)
  - Run as owner
  - Security implications
- SetGID (2xxx)
  - Run as group
  - Directory inheritance
- Sticky bit (1xxx)
  - File deletion control
  - /tmp directory

#### Permission Vulnerabilities
- World-writable directories
- Weak file permissions
- Insecure configurations
- Permission inheritance issues

### 2.2.6 File Operations

#### File Commands
- ls and listing files
  - Long format (ls -l)
  - Hidden files (ls -a)
  - Recursive listing (ls -R)
  - Sorting options
- cd and directory navigation
- pwd - Print working directory
- mkdir - Create directories
- rmdir - Remove directories
- rm - Remove files
- cp - Copy files/directories
- mv - Move/rename files
- touch - Create/modify files

#### Viewing File Contents
- cat - Display file contents
- less and more - Pagination
- head - First lines
- tail - Last lines
- wc - Word count
- file - Determine file type

#### Finding Files
- find command
  - Finding by name
  - Finding by type
  - Finding by size
  - Finding by permissions
  - Finding by date
  - Finding by ownership
- locate command
- which and whereis
- grep for pattern searching

### 2.2.7 Process Management

#### Understanding Processes
- Process ID (PID)
- Parent Process ID (PPID)
- Process states
  - Running
  - Sleeping
  - Zombie
  - Stopped
- Process hierarchy
- Daemon processes

#### Process Commands
- ps command
  - PS output interpretation
  - Process listing (ps aux)
  - User processes (ps -u username)
  - Tree view (pstree)
- top command
  - Real-time monitoring
  - Resource usage
  - CPU and memory analysis
  - Interactive options
- htop command
  - Enhanced top
  - Better visualization
  - Interactive process management

#### Process Control
- Starting processes
  - Foreground execution
  - Background execution (&)
- Stopping processes
  - Ctrl+C (SIGINT)
  - kill command
  - SIGTERM vs SIGKILL
- Suspending processes
  - Ctrl+Z
  - fg and bg commands
  - nohup command

#### Process Signals
- SIGTERM (15) - Termination
- SIGKILL (9) - Kill (cannot be caught)
- SIGSTOP (19) - Stop
- SIGCONT (18) - Continue
- SIGHUP (1) - Hang up
- SIGUSR1 (10) - User signal 1
- SIGUSR2 (12) - User signal 2

### 2.2.8 File System Basics

#### Understanding File Types
- Regular files
- Directories
- Symbolic links
- Device files
- Sockets
- Named pipes (FIFOs)

#### Inodes
- Inode structure
- Inode number
- Hard links
- Soft links (symbolic links)
- Link limitations

#### Disk Usage
- du command
  - Directory size
  - Recursive analysis
  - Human-readable format
- df command
  - Disk free space
  - Filesystem usage
  - Mounted filesystems

#### File System Types
- ext4
- NTFS
- FAT32
- XFS
- Btrfs
- ZFS

#### Partitions and Mounting
- Understanding partitions
- Mount points
- Mounting filesystems
- /etc/fstab configuration
- umount command

### 2.2.9 Package Management

#### Package Managers
- apt (Debian/Ubuntu)
  - apt update
  - apt install
  - apt remove
  - apt search
  - apt list
- yum (CentOS/RHEL)
  - yum install
  - yum remove
  - yum search
  - yum update
- pacman (Arch)

#### Managing Packages
- Installing packages
- Upgrading packages
- Removing packages
- Searching for packages
- Viewing package information
- Dependency management

### 2.2.10 System Services and Daemons

#### Service Management
- systemctl command
  - Starting services
  - Stopping services
  - Restarting services
  - Enabling/disabling services
  - Service status
- init.d scripts
- Service configuration
  - Service files (/etc/systemd/system/)
  - Dependencies
  - Start/stop behavior

#### Common Services
- SSH daemon (sshd)
- Web servers (Apache, Nginx)
- Database services
- Firewall services
- Logging services
- DNS services

### 2.2.11 Network Configuration

#### Network Commands
- ifconfig and ip command
  - Viewing network interfaces
  - IP configuration
  - MAC addresses
- netstat and ss command
  - Open ports
  - Connection states
  - Protocol information
- ping - Testing connectivity
- nslookup and dig - DNS queries
- traceroute - Route tracing
- netcat - Network utility

#### Network Configuration Files
- /etc/hostname
- /etc/hosts
- /etc/network/interfaces
- /etc/resolv.conf
- /etc/sysconfig/network

#### Firewall Basics
- iptables and nftables
  - Rule structure
  - INPUT, OUTPUT, FORWARD chains
  - Rules and policies
- ufw - Uncomplicated Firewall
- firewalld

### 2.2.12 System Logs

#### Log Files Location
- /var/log/syslog
- /var/log/auth.log
- /var/log/apache2/
- /var/log/nginx/
- Application-specific logs

#### Log Analysis
- tail and follow logs (tail -f)
- Filtering logs (grep)
- Log rotation
- Log levels
  - CRITICAL
  - ERROR
  - WARNING
  - INFO
  - DEBUG

#### Journalctl
- systemd logging
- Querying logs
- Time-based filtering
- Unit-specific logs
- Boot logs

---

## 2.3 Windows Operating System

### 2.3.1 Windows Overview

#### Windows Basics
- Windows versions
  - Windows Server
  - Windows 10/11
  - Desktop vs Server edition
- Windows architecture
- Registry
- Group Policy

### 2.3.2 Windows File System

#### NTFS
- NTFS features
- Permissions system
- Alternate Data Streams (ADS)
- Encryption (EFS)
- Compression

#### File Permissions
- ACL (Access Control Lists)
- Permission types
- Inheritance
- Ownership
- Effective permissions

#### Windows Directories
- %SystemRoot%
- %ProgramFiles%
- %AppData%
- %LocalAppData%
- %Temp%
- %System32%
- %SysWOW64%

### 2.3.3 User and Group Management

#### User Accounts
- Local users
- Domain users
- System accounts
- Built-in accounts
- User profile

#### User Management Tools
- User and Groups (lusrmgr.msc)
- Active Directory Users and Computers (ADUC)
- Net user command
- PowerShell commands

#### Group Management
- Local groups
- Domain groups
- Group membership
- Special groups (Administrators, Users)

### 2.3.4 Process Management

#### Process Concepts
- Process hierarchy
- Parent/Child processes
- Process priority
- Multi-threaded processes

#### Process Management Tools
- Task Manager
- Process Explorer
- tasklist command
- taskkill command
- Get-Process (PowerShell)

### 2.3.5 Services and Applications

#### Windows Services
- Service states
- Service startup types
  - Automatic
  - Manual
  - Disabled
- Managing services
  - Services.msc
  - Net start/stop
  - Sc command

#### Windows Registry
- Registry structure
- HKEY roots
- Keys and values
- Registry editors
- Registry security
- Registry hives

### 2.3.6 Windows Security Features

#### Windows Defender
- Real-time protection
- Scanning
- Quarantine
- Updates

#### Windows Firewall
- Inbound/Outbound rules
- Profiles
- Windows Firewall with Advanced Security

#### UAC (User Account Control)
- Privilege elevation
- UAC levels
- Token elevation
- Bypass vulnerabilities

#### Encryption
- BitLocker
- EFS (Encrypting File System)
- DPAPI (Data Protection API)

### 2.3.7 Windows Command Line

#### CMD vs PowerShell
- Command Prompt (cmd.exe)
- Windows PowerShell
- PowerShell Core
- Basic commands
- File operations
- Process management
- Network commands

#### PowerShell Basics
- Cmdlets
- Objects and properties
- Pipelines
- Get-Help
- Aliases

### 2.3.8 System Administration

#### System Information
- systeminfo command
- OS version
- Hardware details
- Network configuration
- Installed updates

#### Task Scheduler
- Scheduled tasks
- Creating tasks
- Task triggers
- Task actions

#### Event Viewer
- Event logs
  - System
  - Security
  - Application
- Event ID
- Log filtering
- Log analysis

---

## 2.4 macOS Operating System

### 2.4.1 macOS Overview
- Unix-based system
- Mach kernel
- Security features
- Development platform

### 2.4.2 File System
- APFS (Apple File System)
- HFS+ (older systems)
- File organization
- Hidden files

### 2.4.3 Command Line
- Terminal application
- Unix commands
- Bash vs Zsh
- Homebrew package manager

### 2.4.4 Permissions and Security
- chmod and chown
- File permissions
- Gatekeeper
- System Integrity Protection (SIP)
- Code signing

### 2.4.5 Process and Service Management
- Activity Monitor
- Process monitoring
- Launch agents and daemons
- Service management

---

## 2.5 OS Security Hardening

### 2.5.1 Principle of Least Privilege
- Minimal user rights
- Service account limitations
- Group policy application
- Regular permission audits

### 2.5.2 Security Updates
- Patch management
- Update strategies
- Security bulletins
- Patching schedule

### 2.5.3 Firewall Configuration
- Inbound rules
- Outbound rules
- Rule ordering
- Default policies

### 2.5.4 Account Security
- Password policies
- Account lockout
- Session timeout
- Disable unnecessary accounts

### 2.5.5 Auditing and Logging
- Audit policies
- Log retention
- Log forwarding
- Log analysis
- Monitoring strategies

### 2.5.6 Disk Encryption
- Full disk encryption
- Partition encryption
- Key management
- Performance considerations

### 2.5.7 Security Best Practices
- Regular backups
- Antivirus/antimalware
- Safe browsing
- Email security
- USB and removable media control

---

## 2.6 Module 2 Assessment

### 2.6.1 Knowledge Check
- File permissions and ownership
- User and group management
- Process management
- Service management
- Network configuration
- Security hardening

### 2.6.2 Practical Exercises
- Managing files and directories
- User and group administration
- Process monitoring
- Permission configuration
- Log analysis

### 2.6.3 Mini Projects
- System hardening checklist
- User management script
- Log analysis tool
- Permission audit script

---

---

# MODULE 3: NETWORKING FUNDAMENTALS {#module-3}

## 3.1 Introduction to Networking

### 3.1.1 Networking Basics
- What is a network?
- Network types
  - LAN (Local Area Network)
  - WAN (Wide Area Network)
  - MAN (Metropolitan Area Network)
  - PAN (Personal Area Network)
- Network components
  - Nodes/Hosts
  - Links
  - Routers and switches
  - Gateways

### 3.1.2 Why Networking Knowledge Matters
- Understanding network attacks
- Protocol vulnerabilities
- Man-in-the-middle attacks
- Network reconnaissance
- Packet analysis
- Network hardening

### 3.1.3 Network Models
- OSI Model
  - Layer 1: Physical
  - Layer 2: Data Link
  - Layer 3: Network
  - Layer 4: Transport
  - Layer 5: Session
  - Layer 6: Presentation
  - Layer 7: Application
- TCP/IP Model
  - Link Layer
  - Internet Layer
  - Transport Layer
  - Application Layer

---

## 3.2 OSI Model Deep Dive

### 3.2.1 Layer 1: Physical Layer
- Physical transmission
- Cables and connectors
  - Ethernet cables
  - Fiber optic
  - Wireless
- Signals
  - Digital signals
  - Analog signals
  - Modulation
- Devices
  - Repeaters
  - Hubs
- Physical vulnerabilities
  - Cable tapping
  - Signal jamming
  - Physical damage

### 3.2.2 Layer 2: Data Link Layer
- Frames
- MAC addresses
- Switching
  - MAC table
  - Switching logic
  - VLAN
- ARP (Address Resolution Protocol)
  - ARP process
  - ARP spoofing
  - Gratuitous ARP
- Devices
  - Switches
  - Bridges
- Vulnerabilities
  - MAC spoofing
  - ARP poisoning
  - VLAN hopping
  - STP manipulation

### 3.2.3 Layer 3: Network Layer
- IP Addresses
  - IPv4 addressing
  - Subnetting
  - CIDR notation
  - IPv6 basics
- Routing
  - Router function
  - Routing protocols
  - Routing tables
  - Routing attacks
- ICMP
  - Echo request/reply (ping)
  - Unreachable messages
  - Redirect messages
  - ICMP attacks
- IP Fragmentation
  - Fragment offset
  - More fragments flag
  - Reassembly
  - Fragmentation attacks (Ping of Death)
- Devices
  - Routers
  - Layer 3 switches
- Vulnerabilities
  - IP spoofing
  - Routing attacks
  - ICMP attacks
  - Route hijacking

### 3.2.4 Layer 4: Transport Layer
- TCP (Transmission Control Protocol)
  - TCP header
  - Three-way handshake
  - Connection states
  - Flags (SYN, ACK, FIN, RST)
  - TCP windowing
  - TCP congestion control
  - TCP vulnerabilities
- UDP (User Datagram Protocol)
  - UDP header
  - Connectionless communication
  - Speed advantages
  - UDP vulnerabilities
- Ports and sockets
  - Port ranges
  - Well-known ports
  - Ephemeral ports
  - Socket pairs
- Connection states
- Flow control
- Error checking
- Vulnerabilities
  - SYN flood
  - UDP flooding
  - Port scanning
  - Connection hijacking
  - Sequence prediction

### 3.2.5 Layer 5: Session Layer
- Session establishment
- Session maintenance
- Session termination
- Dialogue control
  - Simplex
  - Half-duplex
  - Full-duplex
- Synchronization
- Token management
- Vulnerabilities
  - Session hijacking
  - Session fixation
  - Session timeout attacks

### 3.2.6 Layer 6: Presentation Layer
- Data translation
- Encryption and decryption
- Data compression
- Formatting
- Character set translation
- Vulnerabilities
  - Encryption weaknesses
  - Compression attacks (CRIME, BREACH)
  - Format string vulnerabilities

### 3.2.7 Layer 7: Application Layer
- Application protocols
- User services
- Data exchange
- Common protocols
  - HTTP/HTTPS
  - FTP/SFTP
  - SMTP/POP3/IMAP
  - DNS
  - SSH
  - Telnet
  - SNMP
- Vulnerabilities
  - Application-level attacks
  - Protocol exploits
  - Credential compromise

---

## 3.3 TCP/IP Protocols

### 3.3.1 IPv4 Addressing and Subnetting

#### IPv4 Basics
- 32-bit addresses
- Dotted decimal notation
- Address classes (historical)
- Public vs Private addresses
  - Private ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
  - Loopback (127.0.0.0/8)
  - Link-local (169.254.0.0/16)

#### Subnetting
- Subnet mask
- Network address
- Broadcast address
- Host range
- Subnetting calculations
- CIDR notation
- Subnet examples
  - /24, /25, /26, /30, /32

#### Special Addresses
- 0.0.0.0 (default route)
- 255.255.255.255 (broadcast)
- Multicast addresses (224.0.0.0/4)
- Reserved addresses

#### IPv6 Basics
- 128-bit addresses
- Hexadecimal notation
- Address types
  - Unicast
  - Multicast
  - Anycast
- IPv6 advantages
- Transition mechanisms

### 3.3.2 TCP (Transmission Control Protocol)

#### TCP Header
- Source port
- Destination port
- Sequence number
- Acknowledgment number
- Flags (SYN, ACK, FIN, RST, PSH, URG)
- Window size
- Checksum
- Urgent pointer
- Options

#### TCP Connection Establishment
- Three-way handshake
  - SYN
  - SYN-ACK
  - ACK
- Connection parameters
- ISN (Initial Sequence Number)
- Window size negotiation

#### TCP Connection Termination
- Four-way handshake
  - FIN
  - ACK
  - FIN
  - ACK
- Half-close
- Abrupt termination (RST)
- TIME_WAIT state

#### TCP Reliability
- Sequence numbers
- Acknowledgments
- Retransmission
- Checksum
- Ordering
- Flow control (window mechanism)
- Congestion control (slow start, congestion avoidance)

#### TCP Vulnerabilities
- SYN flood attack
- ACK flood attack
- Sequence prediction
- Reset injection
- Connection hijacking

### 3.3.3 UDP (User Datagram Protocol)

#### UDP Header
- Source port
- Destination port
- Length
- Checksum

#### UDP Characteristics
- Connectionless
- Unreliable delivery
- No flow control
- Low overhead
- Speed advantages

#### UDP Use Cases
- DNS queries
- DHCP
- NTP
- Video streaming
- Online gaming
- VoIP

#### UDP Vulnerabilities
- No delivery guarantee
- No congestion control
- UDP flooding attacks
- DNS amplification attacks
- No connection state tracking

### 3.3.4 ICMP (Internet Control Message Protocol)

#### ICMP Types and Codes
- Echo Request/Reply (ping)
- Destination Unreachable
- Time Exceeded
- Parameter Problem
- Redirect
- Timestamp Request/Reply

#### ICMP Usage
- Network diagnostics
- Traceroute
- Ping
- Path MTU discovery
- Redirect messages

#### ICMP Vulnerabilities
- Ping of Death (fragmentation)
- Smurf attack (echo amplification)
- ICMP redirect attacks
- Firewall evasion

### 3.3.5 ARP (Address Resolution Protocol)

#### ARP Process
- ARP request broadcast
- ARP reply unicast
- ARP cache
- TTL in ARP cache

#### ARP Packet Structure
- Hardware type
- Protocol type
- Hardware address length
- Protocol address length
- Operation (request/reply)
- Sender hardware address
- Sender protocol address
- Target hardware address
- Target protocol address

#### ARP Vulnerabilities
- ARP spoofing
- ARP poisoning
- Gratuitous ARP abuse
- ARP cache poisoning
- Man-in-the-middle attacks

#### ARP Mitigation
- Static ARP entries
- ARP inspection
- DHCP snooping
- DAI (Dynamic ARP Inspection)

---

## 3.4 Application Layer Protocols

### 3.4.1 DNS (Domain Name System)

#### DNS Basics
- Hostname resolution
- DNS hierarchy
  - Root nameservers
  - TLD nameservers
  - Authoritative nameservers
  - Recursive resolvers
- DNS records
  - A records
  - AAAA records
  - CNAME records
  - MX records
  - NS records
  - TXT records
  - SOA records

#### DNS Process
- DNS query
- Recursive resolution
- Iterative queries
- Caching
- TTL (Time To Live)

#### DNS Tools
- nslookup
- dig
- host
- whois
- DNS enumeration tools

#### DNS Vulnerabilities
- DNS spoofing
- Cache poisoning
- DNS hijacking
- Zone transfer attacks
- Subdomain enumeration
- DNS exfiltration
- DNS amplification attacks

#### DNSSEC
- Digital signatures
- Authentication
- Integrity
- Key management

### 3.4.2 HTTP/HTTPS

#### HTTP Basics
- Protocol versions
  - HTTP/1.0
  - HTTP/1.1
  - HTTP/2
  - HTTP/3
- Request methods
  - GET
  - POST
  - PUT
  - DELETE
  - HEAD
  - PATCH
  - OPTIONS
  - TRACE
  - CONNECT
- Status codes
  - 1xx (Informational)
  - 2xx (Success)
  - 3xx (Redirection)
  - 4xx (Client Error)
  - 5xx (Server Error)
- Headers
  - Request headers
  - Response headers
  - Content-Type
  - Content-Length
  - Authorization
  - Cookies
  - User-Agent

#### HTTPS and SSL/TLS
- Encryption
- Certificate validation
- Handshake process
- Cipher suites
- Protocol versions
  - SSL 3.0 (deprecated)
  - TLS 1.0, 1.1 (deprecated)
  - TLS 1.2 (current)
  - TLS 1.3 (modern)
- Certificate pinning
- HSTS

#### HTTP Vulnerabilities
- Man-in-the-middle attacks
- SSL/TLS downgrade attacks
- Certificate spoofing
- HTTP request smuggling
- Cache poisoning
- Session hijacking

### 3.4.3 FTP/SFTP

#### FTP (File Transfer Protocol)
- FTP basics
- Active vs Passive mode
- Commands
- Anonymous FTP
- Vulnerabilities
  - Plaintext credentials
  - Man-in-the-middle
  - FTP bounce attacks
  - Port prediction

#### SFTP (SSH File Transfer Protocol)
- Secure FTP
- SSH tunneling
- Encrypted communication
- Key authentication

### 3.4.4 SSH (Secure Shell)

#### SSH Basics
- Secure remote access
- Encryption
- Authentication methods
  - Password authentication
  - Public key authentication
  - Certificate authentication
- SSH keys
  - Key generation
  - Key distribution
  - Key storage
- Port forwarding
  - Local port forwarding
  - Remote port forwarding
  - Dynamic port forwarding

#### SSH Security
- SSH hardening
- Key management
- Authentication methods
- Session security
- Vulnerabilities
  - Brute force attacks
  - Key compromise
  - Host key spoofing
  - Timing attacks

### 3.4.5 SMTP/POP3/IMAP

#### Email Protocols
- SMTP (Simple Mail Transfer Protocol)
  - Message transmission
  - Relay servers
  - Authentication
  - TLS support
- POP3 (Post Office Protocol 3)
  - Message retrieval
  - Delete-on-download
  - Stateless
- IMAP (Internet Message Access Protocol)
  - Message management
  - Server-side storage
  - Folder support
  - Message flags

#### Email Security
- SPF (Sender Policy Framework)
- DKIM (DomainKeys Identified Mail)
- DMARC (Domain-based Message Authentication)
- Email encryption
- Phishing attacks
- Spoofing vulnerabilities

### 3.4.6 DNS and Domain Services

#### DHCP (Dynamic Host Configuration Protocol)
- DHCP process
  - Discover
  - Offer
  - Request
  - Acknowledge
- DHCP lease
- DHCP vulnerabilities
  - DHCP starvation
  - DHCP spoofing
  - Rogue DHCP server

#### LDAP (Lightweight Directory Access Protocol)
- Directory services
- LDAP query
- Authentication
- Directory structure
- LDAP injection attacks

### 3.4.7 Other Important Protocols
- NTP (Network Time Protocol)
- SNMP (Simple Network Management Protocol)
- Syslog
- RDP (Remote Desktop Protocol)
- VPN protocols (IPSec, OpenVPN, WireGuard)

---

## 3.5 Network Security Concepts

### 3.5.1 Firewalls
- Firewall types
  - Packet filtering
  - Stateful inspection
  - Application layer
  - Next-generation firewalls
- Firewall rules
  - Allow rules
  - Deny rules
  - Default policy
- Firewall implementation
  - Hardware firewalls
  - Software firewalls
  - Cloud firewalls

### 3.5.2 Network Segmentation
- VLAN (Virtual LAN)
  - VLAN tagging
  - VLAN trunking
  - VLAN hopping attacks
- Network zones
  - DMZ (Demilitarized Zone)
  - Internal networks
  - Management networks
- Network policies
- Traffic isolation

### 3.5.3 VPN (Virtual Private Network)
- VPN concepts
- Tunneling protocols
  - IPSec
  - OpenVPN
  - WireGuard
  - L2TP/IPSec
- VPN security
- VPN vulnerabilities

### 3.5.4 Network Monitoring
- Network taps
- Port mirroring (SPAN)
- Flow analysis
- SIEM (Security Information and Event Management)
- IDS/IPS (Intrusion Detection/Prevention System)

### 3.5.5 Zero Trust Network Architecture
- Principles
- Micro-segmentation
- Continuous verification
- Implementation challenges

---

## 3.6 Hands-On Networking

### 3.6.1 Network Tools
- ping
- traceroute/tracert
- netstat/ss
- nslookup/dig
- ipconfig/ifconfig
- arp
- netcat
- telnet
- curl/wget

### 3.6.2 Packet Analysis
- Packet structure
- Wireshark
  - Capturing packets
  - Filtering packets
  - Analyzing packets
  - Protocol dissection
- tcpdump
- Network flow analysis

### 3.6.3 Network Troubleshooting
- Connectivity issues
- DNS resolution
- Routing problems
- Performance issues
- Tools and techniques

---

## 3.7 Module 3 Assessment

### 3.7.1 Knowledge Check
- OSI model layers
- TCP/IP protocol suite
- IPv4 subnetting
- TCP and UDP
- Application protocols
- Network security concepts

### 3.7.2 Practical Exercises
- Subnetting calculations
- Packet analysis
- Protocol identification
- Network command usage
- Vulnerability identification

### 3.7.3 Mini Projects
- Network diagram creation
- Subnetting plan
- Packet analysis project
- Network security assessment

---

---

# MODULE 4: WEB TECHNOLOGIES & ARCHITECTURE {#module-4}

## 4.1 Introduction to Web Technologies

### 4.1.1 Web Basics
- What is the web?
- World Wide Web
- Internet vs Web
- Web clients and servers
- Request-response model
- Web standards and specifications

### 4.1.2 Why Web Technology Knowledge Matters
- Most attacks target web applications
- Understanding vulnerabilities
- API security
- Client-side security
- Server-side security
- Web application architecture
- Technology stack analysis

### 4.1.3 Web Technology Stack
- Frontend technologies
  - HTML
  - CSS
  - JavaScript
  - Frameworks
- Backend technologies
  - Server-side languages
  - Databases
  - APIs
- DevOps and infrastructure
- Security layers

---

## 4.2 HTTP/HTTPS and Web Protocols

### 4.2.1 HTTP Protocol Deep Dive

#### HTTP Request Structure
- Request line
  - Method
  - URI/URL
  - HTTP version
- Headers
  - Host
  - User-Agent
  - Accept
  - Accept-Encoding
  - Accept-Language
  - Content-Type
  - Content-Length
  - Authorization
  - Referer
  - Cookie
  - Custom headers
- Request body (for POST, PUT, PATCH)
- Query parameters

#### HTTP Response Structure
- Status line
  - HTTP version
  - Status code
  - Reason phrase
- Headers
  - Content-Type
  - Content-Length
  - Set-Cookie
  - Cache-Control
  - Expires
  - ETag
  - Server
  - Custom headers
- Response body

#### HTTP Methods
- GET
  - Safe and idempotent
  - No request body
  - Caching
  - Bookmarking
- HEAD
  - Like GET but no response body
  - Metadata retrieval
- POST
  - Not idempotent
  - Request body
  - Form submission
  - Data creation
- PUT
  - Idempotent
  - Full resource replacement
  - Request body
- PATCH
  - Partial update
  - Not always idempotent
- DELETE
  - Resource deletion
  - Idempotent
- OPTIONS
  - CORS preflight
  - Allowed methods
- CONNECT
  - HTTPS tunneling
  - Proxy tunneling
- TRACE
  - Diagnostic method
  - Security risks

#### Status Codes
- 1xx Information
  - 100 Continue
  - 101 Switching Protocols
- 2xx Success
  - 200 OK
  - 201 Created
  - 204 No Content
- 3xx Redirection
  - 301 Moved Permanently
  - 302 Found
  - 304 Not Modified
  - 307 Temporary Redirect
- 4xx Client Error
  - 400 Bad Request
  - 401 Unauthorized
  - 403 Forbidden
  - 404 Not Found
  - 429 Too Many Requests
- 5xx Server Error
  - 500 Internal Server Error
  - 502 Bad Gateway
  - 503 Service Unavailable

#### HTTP Caching
- Cache-Control header
  - Public/Private
  - Max-age
  - No-cache, no-store
  - Must-revalidate
- Etag (Entity Tag)
- Last-Modified
- Expires
- Conditional requests (If-Modified-Since, If-None-Match)

#### HTTP Security Headers
- Strict-Transport-Security (HSTS)
- Content-Security-Policy (CSP)
- X-Content-Type-Options
- X-Frame-Options
- X-XSS-Protection
- Referrer-Policy
- Permissions-Policy
- Access-Control-Allow-Origin (CORS)

### 4.2.2 HTTPS and SSL/TLS

#### SSL/TLS Encryption
- Symmetric encryption
- Asymmetric encryption
- Handshake process
- Certificate validation
- Perfect Forward Secrecy (PFS)

#### Certificates and PKI
- X.509 certificates
- Certificate fields
- Certificate chain
- Root CA, Intermediate CA
- Certificate validation
- Certificate pinning
- Certificate revocation (CRL, OCSP)

#### TLS Versions
- SSL 3.0 (deprecated)
- TLS 1.0 (deprecated)
- TLS 1.1 (deprecated)
- TLS 1.2 (current standard)
- TLS 1.3 (modern, improved)

#### Cipher Suites
- Key exchange algorithms
- Authentication algorithms
- Encryption algorithms
- Hash algorithms
- Selecting secure cipher suites
- Deprecated ciphers

#### HTTPS Best Practices
- HSTS
- Certificate management
- Cipher suite selection
- TLS version enforcement
- Secure key management

#### HTTPS Vulnerabilities
- Downgrade attacks
- POODLE (Padding Oracle On Downgraded Legacy Encryption)
- BEAST (Browser Exploit Against SSL/TLS)
- CRIME (Compression Ratio Info-leak Made Easy)
- Heartbleed
- Certificate spoofing

### 4.2.3 HTTP/2 and HTTP/3
- Protocol improvements
- Multiplexing
- Header compression
- Server push
- Binary framing
- Stream prioritization
- QUIC protocol (HTTP/3)
- Benefits and compatibility

---

## 4.3 Web Application Architecture

### 4.3.1 Client-Server Architecture

#### Client (Frontend)
- Browser environment
- DOM (Document Object Model)
- JavaScript execution
- Client-side validation
- Local storage
  - Cookies
  - LocalStorage
  - SessionStorage
  - IndexedDB
- Service Workers
- Web Workers

#### Server (Backend)
- Web server software
  - Apache
  - Nginx
  - IIS
  - Node.js
  - Tomcat
- Server-side rendering
- Request processing
- Business logic
- Database interaction
- Session management

#### Communication
- Request-response model
- RESTful APIs
- GraphQL
- WebSockets
- Server-Sent Events (SSE)

### 4.3.2 REST APIs

#### REST Principles
- Client-server architecture
- Statelessness
- Cacheability
- Uniform interface
- Layered system
- Code on demand (optional)

#### RESTful Design
- Resources
- HTTP methods for operations
- URL structure
- Status codes
- Content negotiation
- Versioning strategies
- Pagination
- Filtering and sorting
- Error handling

#### REST Security
- Authentication (API keys, OAuth, JWT)
- Authorization (role-based access control)
- Rate limiting
- Input validation
- Output encoding
- HTTPS enforcement
- CORS configuration
- API key management

#### REST Vulnerabilities
- Insecure API endpoints
- Weak authentication
- Missing authorization
- Improper input validation
- Excessive data exposure
- Lack of rate limiting
- API versioning issues

### 4.3.3 GraphQL

#### GraphQL Basics
- Query language
- Schema definition
- Queries and mutations
- Subscriptions
- Field resolution
- Type system
- Resolvers

#### GraphQL Security
- Query complexity analysis
- Depth limiting
- Rate limiting
- Introspection control
- Input validation
- Authentication and authorization
- Error handling

#### GraphQL Vulnerabilities
- N+1 queries
- Denial of service (complex queries)
- Information disclosure through introspection
- Improper authorization

### 4.3.4 WebSockets

#### WebSocket Protocol
- Full-duplex communication
- Persistent connection
- Lower latency
- Real-time updates
- WebSocket handshake
- Frame format

#### WebSocket Security
- Origin validation
- Authentication
- Authorization
- Input validation
- Rate limiting
- Connection limits

#### WebSocket Vulnerabilities
- Man-in-the-middle attacks
- Message injection
- Unauthorized access
- Session hijacking

---

## 4.4 Frontend Technologies

### 4.4.1 HTML

#### HTML Basics
- Document structure
- Elements and tags
- Attributes
- Semantic HTML
- Accessibility
- Meta tags
- Forms and inputs

#### HTML Security
- Input sanitization
- Output encoding
- Form validation
- CSRF tokens
- Content Security Policy headers

#### HTML Vulnerabilities
- XSS through HTML injection
- HTML5 features (local storage, Web Workers)
- Iframe sandboxing

### 4.4.2 CSS

#### CSS Basics
- Selectors
- Properties and values
- Cascading and specificity
- Box model
- Flexbox and Grid
- Media queries
- Responsive design
- Animations and transitions

#### CSS Security
- CSS-based attacks
- Information leakage
- Clickjacking prevention
- Property-based vulnerabilities

### 4.4.3 JavaScript (Client-Side)

#### JavaScript DOM API
- DOM manipulation
- Event handling
- AJAX/Fetch API
- Browser storage APIs
- Geolocation
- Canvas
- WebGL
- Crypto API

#### JavaScript Frameworks
- React
  - Components
  - Virtual DOM
  - State management
  - Hooks
  - JSX
- Vue.js
  - Components
  - Reactive data
  - Templates
  - Composition API
- Angular
  - TypeScript
  - Dependency injection
  - RxJS observables
  - Services and components

#### JavaScript Security
- XSS prevention
- Input validation
- Output encoding
- DOM-based XSS
- Content Security Policy
- Secure coding practices
- Package security

#### JavaScript Vulnerabilities
- XSS (Cross-Site Scripting)
- CSRF (Cross-Site Request Forgery)
- DOM-based vulnerabilities
- Prototype pollution
- Supply chain attacks
- Vulnerable dependencies

---

## 4.5 Backend Technologies

### 4.5.1 Backend Languages and Frameworks

#### Python
- Django
  - ORM
  - Authentication
  - Admin interface
  - Security features
- Flask
  - Lightweight
  - Modularity
  - Extensions
- FastAPI
  - Modern
  - Type hints
  - Automatic API documentation

#### PHP
- Laravel
  - MVC architecture
  - Eloquent ORM
  - Security features
- Symfony
  - Full-featured framework
  - Security component
- WordPress (CMS)

#### Node.js/JavaScript
- Express.js
  - Middleware
  - Routing
  - Request handling
- NestJS
  - TypeScript
  - Dependency injection
  - Modular structure
- Fastify
  - High-performance
  - Plugin system

#### Java
- Spring Framework
  - Spring Boot
  - Spring Security
  - Spring Data
- Jakarta EE

#### C#/.NET
- ASP.NET Core
  - MVC and API
  - Entity Framework
  - Identity and authentication
  - Built-in security

#### Go
- Gin
  - Web framework
  - HTTP handling
- Echo
  - Lightweight
  - Middleware support

### 4.5.2 Databases

#### Relational Databases
- MySQL
- PostgreSQL
- SQL Server
- Oracle
- SQL fundamentals
  - SELECT queries
  - JOIN operations
  - Aggregations
  - Subqueries
- ACID properties
- Normalization
- Indexing
- Query optimization
- Database security
  - User permissions
  - SQL injection prevention
  - Encryption

#### NoSQL Databases
- MongoDB (Document)
  - Collections
  - Documents
  - Queries
  - Indexing
- Redis (Key-Value)
  - Data structures
  - Caching
  - Sessions
- DynamoDB (AWS)
  - Tables
  - Items
  - Queries
- Elasticsearch (Search and Analytics)
- Cassandra (Time-series)

#### Database Security
- Access control
- Encryption at rest
- Encryption in transit
- Audit logging
- Backup and recovery
- Database hardening
- SQL injection prevention

### 4.5.3 Authentication and Authorization

#### Authentication
- User registration
- Login process
- Password hashing
  - Bcrypt
  - Argon2
  - PBKDF2
  - Scrypt
- Password reset
- MFA (Multi-Factor Authentication)
  - TOTP (Time-based One-Time Password)
  - U2F/WebAuthn
  - SMS-based
  - Email-based

#### Session Management
- Session creation
- Session tokens
- Session storage
- Session validation
- Session timeout
- Secure session cookies
- CSRF protection

#### OAuth 2.0
- Authorization framework
- Grant types
  - Authorization Code
  - Implicit
  - Resource Owner Password Credentials
  - Client Credentials
- Access tokens
- Refresh tokens
- Scope
- OpenID Connect

#### JWT (JSON Web Tokens)
- Token structure
  - Header
  - Payload
  - Signature
- Token creation
- Token validation
- Key management
- Algorithm selection
- Token expiration
- Refresh token rotation

#### Authorization
- Role-Based Access Control (RBAC)
- Attribute-Based Access Control (ABAC)
- Access control lists (ACL)
- Permission management
- Least privilege principle

### 4.5.4 Input Validation and Output Encoding

#### Input Validation
- Whitelist validation
- Type checking
- Length validation
- Format validation
- Range validation
- Canonicalization
- Normalization

#### Output Encoding
- HTML encoding
- URL encoding
- JavaScript encoding
- CSS encoding
- SQL encoding
- XML encoding
- Base64 encoding
- Context-specific encoding

#### Injection Attacks Prevention
- SQL injection prevention
- Command injection prevention
- LDAP injection prevention
- XML injection prevention
- XPath injection prevention

---

## 4.6 Web Server Security

### 4.6.1 Web Server Configuration

#### Apache
- Virtual hosts
- .htaccess files
- Modules
- Security modules (mod_security)
- SSL/TLS configuration
- Access control
- Directory protection

#### Nginx
- Server blocks
- Location blocks
- Upstream servers
- Reverse proxy configuration
- Load balancing
- SSL/TLS configuration
- Security headers

#### IIS
- Application pools
- Bindings
- SSL/TLS
- URL rewriting
- Request filtering
- Modules
- Authentication methods

### 4.6.2 Web Server Hardening
- Remove unnecessary modules
- Disable dangerous methods
- Hide server information
- Configure secure headers
- Access logging
- Error handling
- Timeout configuration
- Rate limiting
- IP-based access control

### 4.6.3 Reverse Proxy and Load Balancing
- Reverse proxy concepts
- Load balancing algorithms
- Session stickiness
- Health checks
- SSL/TLS termination
- Request routing
- Security benefits

---

## 4.7 Web Application Security

### 4.7.1 OWASP Top 10
- A01:2021 – Broken Access Control
- A02:2021 – Cryptographic Failures
- A03:2021 – Injection
- A04:2021 – Insecure Design
- A05:2021 – Security Misconfiguration
- A06:2021 – Vulnerable and Outdated Components
- A07:2021 – Identification and Authentication Failures
- A08:2021 – Software and Data Integrity Failures
- A09:2021 – Logging and Monitoring Failures
- A10:2021 – Server-Side Request Forgery (SSRF)

### 4.7.2 Common Web Vulnerabilities
- XSS (Cross-Site Scripting)
- CSRF (Cross-Site Request Forgery)
- SQL Injection
- IDOR (Insecure Direct Object References)
- Broken Authentication
- Sensitive Data Exposure
- Broken Access Control
- Using Components with Known Vulnerabilities
- Insufficient Logging and Monitoring
- Server-Side Template Injection
- Remote Code Execution

### 4.7.3 API Security
- API authentication
- API authorization
- Rate limiting
- Input validation
- Output validation
- API versioning security
- Error handling
- API key management
- OAuth 2.0 implementation
- GraphQL security
- API documentation security
- Endpoint discovery and enumeration

### 4.7.4 Secure Coding Practices
- Input validation
- Output encoding
- Proper error handling
- Logging and monitoring
- Secure defaults
- Fail-safe defaults
- Complete mediation
- Least privilege
- Separation of mechanism and policy
- Psychological acceptability

---

## 4.8 Module 4 Assessment

### 4.8.1 Knowledge Check
- HTTP protocol fundamentals
- HTTPS and SSL/TLS
- Web application architecture
- REST API design
- Authentication and authorization
- Common web vulnerabilities
- Security best practices

### 4.8.2 Practical Exercises
- HTTP request analysis
- Web server configuration
- API creation and security
- Form validation
- HTTPS implementation
- Authentication mechanisms

### 4.8.3 Mini Projects
- REST API development with security
- Web application setup
- Authentication system design
- Vulnerability identification

---

---

# MODULE 5: INTRODUCTION TO CYBERSECURITY {#module-5}

## 5.1 Cybersecurity Fundamentals

### 5.1.1 Introduction to Cybersecurity
- Definition of cybersecurity
- Threats, vulnerabilities, and risks
- Cybersecurity objectives (CIA Triad)
  - Confidentiality
  - Integrity
  - Availability
- Additional principles
  - Authenticity
  - Non-repudiation
  - Accountability
- Cybersecurity landscape
- Why cybersecurity matters

### 5.1.2 Types of Security Threats

#### Malware
- Viruses
- Worms
- Trojans
- Ransomware
- Spyware
- Adware
- Rootkits
- Botnets
- Logic bombs
- Backdoors

#### Unauthorized Access
- Brute force attacks
- Dictionary attacks
- Rainbow tables
- Credential stuffing
- Password spray attacks
- Privilege escalation
- Lateral movement

#### Injection Attacks
- SQL injection
- Command injection
- LDAP injection
- XML injection
- XPath injection
- Code injection

#### XSS (Cross-Site Scripting)
- Reflected XSS
- Stored XSS
- DOM-based XSS
- Consequences
- Attack vectors

#### CSRF (Cross-Site Request Forgery)
- How CSRF works
- Attack vectors
- Impact
- Prevention methods

#### Man-in-the-Middle (MITM)
- ARP spoofing
- DNS spoofing
- SSL/TLS downgrade
- Session hijacking
- SSL stripping

#### DoS/DDoS (Denial of Service)
- Volumetric attacks
- Protocol attacks
- Application layer attacks
- Botnet-based attacks
- Mitigation strategies

#### Data Breaches
- Data exfiltration
- Insider threats
- Misconfiguration
- Unpatched vulnerabilities
- Social engineering
- Impact and consequences

#### Social Engineering
- Phishing
- Spear phishing
- Whaling
- Pretexting
- Baiting
- Tailgating/Piggybacking
- Human vulnerabilities

#### Supply Chain Attacks
- Software supply chain
- Hardware supply chain
- Third-party risks
- Dependency attacks
- Counterfeit components

### 5.1.3 Vulnerability Types

#### By Severity
- Critical
- High
- Medium
- Low
- Informational

#### By Category
- Authentication vulnerabilities
- Authorization vulnerabilities
- Cryptographic vulnerabilities
- Injection vulnerabilities
- XSS vulnerabilities
- Configuration vulnerabilities
- Information disclosure
- Logic flaws
- Business logic flaws
- Race conditions

#### By Status
- Unpatched vulnerabilities
- Zero-day vulnerabilities
- Known vulnerabilities
- Vendor-acknowledged vulnerabilities
- Disputed vulnerabilities

---

## 5.2 Risk Management

### 5.2.1 Risk Concepts

#### Risk Definition
- Asset
- Threat
- Vulnerability
- Likelihood
- Impact
- Risk = Likelihood × Impact

#### Risk Assessment
- Identifying assets
- Identifying threats
- Identifying vulnerabilities
- Evaluating likelihood
- Evaluating impact
- Calculating risk
- Prioritization

#### Risk Analysis
- Qualitative risk analysis
- Quantitative risk analysis
- Single Loss Expectancy (SLE)
- Annual Rate of Occurrence (ARO)
- Annual Loss Expectancy (ALE)

### 5.2.2 Risk Response

#### Risk Acceptance
- Accepting residual risk
- Documentation
- Circumstances for acceptance

#### Risk Mitigation
- Reducing likelihood
- Reducing impact
- Implementation
- Monitoring

#### Risk Avoidance
- Eliminating risk
- Ceasing activities
- Changing processes

#### Risk Transfer
- Insurance
- Outsourcing
- Third-party services
- SLAs

### 5.2.3 Security Controls

#### Preventive Controls
- Access controls
- Authentication
- Encryption
- Firewalls
- Input validation
- Code review

#### Detective Controls
- Logs and monitoring
- IDS/IPS
- SIEM
- Audit trails
- Intrusion detection
- Vulnerability scans

#### Corrective Controls
- Incident response
- System patching
- Backup and recovery
- Disaster recovery
- Business continuity

#### Deterrent Controls
- Security policies
- Legal threats
- Warning banners
- Visible security measures

#### Compensating Controls
- Alternative controls
- Risk compensation
- When used

#### Directive Controls
- Policies and procedures
- Guidelines
- Standards
- Security awareness training

### 5.2.4 Security Governance

#### Security Policies
- Acceptable Use Policy (AUP)
- Clean Desk Policy
- Password Policy
- Data Classification Policy
- Incident Response Policy
- Disaster Recovery Policy
- Business Continuity Policy
- Remote Access Policy
- BYOD Policy

#### Compliance and Standards
- NIST frameworks
- ISO/IEC 27000 series
- CIS Controls
- COBIT
- SOC 2
- PCI DSS
- HIPAA
- GDPR
- CCPA
- Industry-specific standards

#### Security Awareness
- Training programs
- Phishing simulations
- Security culture
- User education
- Continuous learning

---

## 5.3 Cryptography

### 5.3.1 Cryptography Basics

#### Confidentiality
- Encryption
- Symmetric encryption
- Asymmetric encryption
- Key management
- Cryptographic protocols

#### Integrity
- Hashing
- Digital signatures
- HMAC
- Message authentication codes
- Non-repudiation

#### Authentication
- Cryptographic authentication
- Challenge-response
- Digital certificates
- Public Key Infrastructure (PKI)

#### Key Management
- Key generation
- Key distribution
- Key storage
- Key rotation
- Key revocation
- Key escrow

### 5.3.2 Symmetric Encryption
- DES (Data Encryption Standard)
  - History
  - Security (deprecated)
- 3DES (Triple DES)
  - How it works
  - Security level
  - Performance
- AES (Advanced Encryption Standard)
  - Algorithm
  - Block sizes and key sizes
  - Modes (ECB, CBC, CTR, GCM)
  - Current standard
- Stream ciphers
  - RC4
  - ChaCha20
- Block cipher modes
  - ECB (Electronic CodeBook)
  - CBC (Cipher Block Chaining)
  - CTR (Counter)
  - GCM (Galois/Counter Mode)
  - CCM (Counter with CBC-MAC)

### 5.3.3 Asymmetric Encryption
- RSA
  - Key generation
  - Encryption and decryption
  - Key size
  - Security strength
- ECC (Elliptic Curve Cryptography)
  - Advantages
  - Key size vs RSA
  - ECDSA
- Diffie-Hellman
  - Key exchange
  - ECDH
- Public Key Infrastructure (PKI)
  - Public and private keys
  - Key distribution
  - Certificate authorities
  - Trust models

### 5.3.4 Hashing
- MD5 (deprecated)
- SHA-1 (deprecated)
- SHA-2 family
  - SHA-256
  - SHA-384
  - SHA-512
- SHA-3
- BLAKE2
- Password hashing
  - Bcrypt
  - Argon2
  - PBKDF2
  - Scrypt
- Hash collisions
- Rainbow tables and salting

### 5.3.5 Digital Signatures
- How digital signatures work
- Signing process
- Verification process
- Algorithms
  - RSA signatures
  - ECDSA
  - DSA (Digital Signature Algorithm)
- Certificate-based signatures
- Timestamping

### 5.3.6 Cryptographic Protocols
- TLS/SSL
- IPSec
- PGP/GPG
- Signal Protocol
- Noise Protocol

### 5.3.7 Cryptographic Attacks

#### Attacks on Encryption
- Brute force
- Frequency analysis
- Known plaintext attack
- Chosen plaintext attack
- Ciphertext-only attack
- Related-key attack

#### Attacks on Hashing
- Preimage attack
- Second preimage attack
- Collision attack
- Rainbow tables

#### Attacks on Signatures
- Key forgery
- Message forgery
- Signature forgery

#### Timing and Side-Channel Attacks
- Timing attacks
- Power analysis
- Cache timing
- Electromagnetic attacks

---

## 5.4 Common Security Issues and Exploits

### 5.4.1 Buffer Overflow
- Buffer overflow concept
- Stack overflow
- Heap overflow
- Integer overflow
- Format string vulnerability
- Prevention methods
  - Input validation
  - Bounds checking
  - Stack canaries
  - Address space layout randomization (ASLR)
  - Data Execution Prevention (DEP)
  - Position Independent Code (PIC)

### 5.4.2 SQL Injection
- SQL injection concept
- Blind SQL injection
- Error-based SQL injection
- Time-based blind SQL injection
- Union-based SQL injection
- Second-order SQL injection
- Prevention methods
  - Parameterized queries
  - Input validation
  - Escape functions
  - ORM frameworks
  - Web Application Firewall (WAF)

### 5.4.3 Command Injection
- OS command injection
- Command concatenation risks
- Prevention methods
  - Avoid shell execution
  - Input validation
  - Use of libraries and APIs
  - Parameterization

### 5.4.4 Path Traversal
- Directory traversal
- File inclusion vulnerabilities
- Null byte injection
- Unicode encoding bypass
- Prevention methods
  - Input validation
  - Whitelist approved paths
  - Canonicalization

### 5.4.5 SSRF (Server-Side Request Forgery)
- SSRF concept
- Impact
- Prevention methods
  - Input validation
  - Whitelist URLs
  - Disable unnecessary protocols
  - Network segmentation
  - Disable cloud metadata access

### 5.4.6 XXE (XML External Entity)
- XXE concept
- XML parsing dangers
- DTD (Document Type Definition)
- Entity expansion attacks (Billion laughs)
- Prevention methods
  - Disable external entity processing
  - Use safer XML parsers
  - Input validation
  - XML schema validation

### 5.4.7 Deserialization Vulnerabilities
- Object deserialization dangers
- Untrusted data deserialization
- Gadget chains
- Prevention methods
  - Avoid deserializing untrusted data
  - Type checking
  - Use safer serialization formats (JSON)
  - Input validation
  - Keep libraries updated

### 5.4.8 Privilege Escalation
- Vertical privilege escalation
- Horizontal privilege escalation
- Unpatched vulnerabilities
- Misconfiguration
- Weak permissions
- Prevention methods
  - Principle of least privilege
  - Regular patching
  - Security hardening
  - Access control reviews

### 5.4.9 Race Conditions
- Time-of-check/Time-of-use (TOCTOU)
- Concurrent access issues
- Prevention methods
  - Proper synchronization
  - Atomic operations
  - Locking mechanisms
  - Careful timing consideration

---

## 5.5 Incident Response and Forensics

### 5.5.1 Incident Response Fundamentals

#### Incident Response Process
- Preparation
- Detection and analysis
- Containment
- Eradication
- Recovery
- Post-incident analysis

#### Incident Types
- Malware infection
- Unauthorized access
- Data breach
- DoS/DDoS
- System compromise
- Insider threat
- Data loss
- Availability issues

#### Incident Response Team
- Roles and responsibilities
- Team structure
- Communication plan
- Escalation procedures

#### Incident Response Plan
- Incident classification
- Notification procedures
- Communication plan
- Recovery procedures
- Documentation requirements

### 5.5.2 Digital Forensics

#### Forensic Process
- Identification
- Preservation
- Collection
- Examination
- Analysis
- Reporting
- Chain of custody

#### Evidence Collection
- Volatile data
- Hard drive imaging
- Memory capture
- Log collection
- Network traffic capture
- Mobile device forensics

#### Analysis
- File system analysis
- Registry analysis
- Memory analysis
- Network traffic analysis
- Application logs
- Timeline analysis
- Artifact analysis

#### Forensic Tools
- Forensic imaging
  - dd
  - Forensic Toolkit (FTK)
  - EnCase
- Analysis tools
  - Autopsy
  - IDA Pro
  - Wireshark
  - Volatility
  - WinHex

---

## 5.6 Security Mindset

### 5.6.1 Defense in Depth
- Multiple layers of security
- Redundancy
- Layered defenses
- Single point of failure elimination

### 5.6.2 Least Privilege Principle
- Minimal necessary permissions
- User account privilege
- Service account privilege
- Application privileges
- Regular privilege audits

### 5.6.3 Secure Defaults
- Default secure configuration
- Disable unnecessary features
- Fail-safe defaults
- Secure-by-default design

### 5.6.4 Security Awareness
- Human factor in security
- Phishing awareness
- Password hygiene
- Social engineering awareness
- Security culture
- Continuous training

### 5.6.5 Threat Modeling
- Understanding threats
- Asset identification
- Threat identification
- Vulnerability identification
- Risk assessment
- Mitigation planning
- STRIDE methodology
- DREAD methodology
- Attack trees

---

## 5.7 Module 5 Assessment

### 5.7.1 Knowledge Check
- Cybersecurity fundamentals
- Threat types and vulnerabilities
- Risk assessment and management
- Cryptography basics
- Common exploits
- Incident response
- Forensics basics

### 5.7.2 Practical Exercises
- Risk assessment analysis
- Threat modeling
- Cryptographic tool usage
- Vulnerability analysis
- Incident response scenario
- Forensic analysis basics

### 5.7.3 Mini Projects
- Threat model for application
- Risk assessment for organization
- Incident response plan
- Security policy creation

---

---

# MODULE 6: SCRIPTING FOR SECURITY {#module-6}

## 6.1 Advanced Python for Security

### 6.1.1 Network Scripting with Python

#### Socket Programming
- TCP client/server
- UDP client/server
- Port scanning
- Banner grabbing
- Raw sockets
- Connection handling
- Error handling

#### Working with Network Libraries
- requests library
  - HTTP requests
  - Request parameters
  - Response handling
  - Session management
  - SSL verification
  - Proxy support
- urllib library
- httpx library
- nmap library (python-nmap)
- paramiko (SSH)

#### DNS and Network Tools
- DNS resolution
- Reverse DNS lookup
- DNS enumeration
- MX record queries
- Whois queries
- Ping implementation
- Traceroute implementation

### 6.1.2 Web Scraping and Analysis

#### HTML Parsing
- BeautifulSoup
  - Parsing HTML
  - Finding elements
  - Extracting data
  - Tag navigation
- LXML
- Requests for page retrieval
- User-Agent headers
- Cookie handling
- Session management

#### Data Extraction
- CSS selectors
- XPath queries
- Regular expressions
- Data cleaning
- Parsing JSON responses

#### API Interaction
- REST API consumption
- JSON parsing
- Error handling
- Rate limiting
- Authentication

### 6.1.3 File and Data Processing

#### File Operations
- Reading different formats
  - Text files
  - Binary files
  - CSV files
  - JSON files
  - XML files
- Writing and modifying files
- File permissions
- Directory traversal
- Path manipulation

#### Data Analysis
- Pandas library
  - DataFrames
  - Data filtering
  - Data manipulation
  - Data aggregation
  - CSV handling
- NumPy library
  - Arrays
  - Mathematical operations
  - Data analysis

#### Regular Expressions
- Pattern matching
- Character classes
- Quantifiers
- Groups and captures
- Lookahead and lookbehind
- Substitution and splitting
- Compiled patterns
- Performance considerations

### 6.1.4 System Interaction

#### OS Module
- Command execution
- Environment variables
- File and directory operations
- Process management
- System information

#### Subprocess Module
- Running commands
- Capturing output
- Input/output redirection
- Process management
- Error handling
- Shell vs no shell

#### System Administration
- User management
- Group management
- File permissions
- System monitoring
- Service management

### 6.1.5 Cryptographic Operations

#### Hashlib
- MD5, SHA hashing
- HMAC
- Key derivation
- Password hashing

#### Cryptography Library
- Symmetric encryption (AES)
- Asymmetric encryption (RSA)
- Key generation
- Digital signatures
- Certificate handling

#### Secrets Module
- Secure random generation
- Token generation
- Password generation

### 6.1.6 Automation and Scheduling

#### Logging
- Logging module
- Log levels
- Log formatting
- File handlers
- Stream handlers
- Log rotation

#### Task Scheduling
- APScheduler
- Scheduled tasks
- Job scheduling
- Cron-like scheduling

#### Error Handling and Retries
- Exception handling
- Retry mechanisms
- Exponential backoff
- Circuit breaker pattern

### 6.1.7 Security-Specific Libraries

#### Scapy
- Packet creation
- Packet sending and receiving
- Packet analysis
- Protocol layering
- Custom protocols

#### Pwntools
- Binary exploitation
- Assembly and disassembly
- Process interaction
- Shellcode generation
- Debugging

#### Bandit
- Code security analysis
- Vulnerability detection
- Security issue identification
- Configuration security

---

## 6.2 Advanced Bash Scripting for Security

### 6.2.1 Advanced Text Processing

#### Advanced Grep Usage
- Multiple patterns
- Context lines
- Perl-compatible regex
- File processing
- Performance optimization

#### Advanced Sed Usage
- Multiple substitutions
- Address ranges
- Hold buffer and pattern space
- Advanced commands
- Script files

#### Advanced Awk Usage
- Pattern ranges
- Built-in functions
- String functions
- Mathematical functions
- User-defined functions
- Multi-dimensional arrays
- Complex data processing

#### Other Text Tools
- Tr (translate characters)
- Col (column filtering)
- Paste (combining files)
- Comm (comparing files)
- Diff and patch
- Cmp (byte comparison)

### 6.2.2 System Monitoring and Analysis

#### Process Monitoring
- Real-time process analysis
- Process filtering
- Memory and CPU analysis
- Process trees
- Command monitoring
- Open file monitoring (lsof)

#### Log Analysis
- Log file parsing
- Pattern extraction
- Statistical analysis
- Trend identification
- Anomaly detection
- Log aggregation

#### Network Monitoring
- Interface statistics
- Connection monitoring
- Traffic analysis
- Packet counting
- Bandwidth analysis
- Port monitoring

#### Disk and Storage Analysis
- Storage usage analysis
- File statistics
- Directory analysis
- Quota monitoring
- File integrity checking

### 6.2.3 Advanced System Administration

#### User and Permission Management
- Automated user management
- Batch operations
- Permission auditing
- Access control verification
- Privilege validation

#### Software Management
- Package installation automation
- Dependency tracking
- Update management
- Version control
- Rollback procedures

#### Service Management
- Service automation
- Health checks
- Restart mechanisms
- Service dependencies
- Service orchestration

### 6.2.4 Network Scripting in Bash

#### Network Utilities
- Netcat usage
- Socat
- Telnet scripting
- SSH automation
- SCP operations

#### DNS Operations
- DNS lookup scripting
- Reverse DNS queries
- DNS zone transfer attempts
- DNS record monitoring
- Subdomain enumeration

#### HTTP Operations
- curl advanced usage
- wget options
- HTTP header manipulation
- Cookie handling
- Authentication
- Proxy configuration

### 6.2.5 Advanced Bash Features

#### Arrays and Associative Arrays
- Array manipulation
- Loop constructs
- Array functions
- Associative arrays (bash 4+)
- Multi-dimensional arrays

#### String Manipulation
- Substring extraction
- String substitution
- Pattern matching
- String comparison
- String functions

#### Advanced Control Structures
- Case statements with ranges
- Multiple conditions
- Nested loops and conditionals
- Loop optimization

#### Function Best Practices
- Return codes
- Error handling
- Scope management
- Recursion
- Performance optimization

### 6.2.6 Security Automation

#### Configuration Management
- Configuration file parsing
- Configuration validation
- Configuration enforcement
- Audit trail maintenance

#### Automated Testing
- Test script creation
- Security test automation
- Vulnerability scanning
- Patch testing
- Compliance checking

#### Audit Logging
- Event logging
- Change logging
- Action tracking
- Timestamp recording
- Alert generation

---

## 6.3 JavaScript/Node.js for Security

### 6.3.1 Node.js Fundamentals for Security

#### Node.js Basics
- Module system (CommonJS, ES6)
- NPM package management
- npm scripts
- Dependency management
- Version pinning
- Security audits

#### Asynchronous Programming
- Callbacks
- Promises
- Async/await
- Error handling
- Promise chains
- Concurrent execution

#### File System Operations
- Reading and writing files
- Directory operations
- File permissions
- Stream handling
- Buffer operations

#### Process and System
- Child processes
- Process spawning
- Command execution
- Process communication
- System information
- Signal handling

### 6.3.2 HTTP and Network Programming

#### HTTP Server
- Creating HTTP servers
- Request handling
- Response generation
- Request parsing
- Header management
- Streaming responses

#### HTTP Client
- Making requests
- Request options
- Response handling
- Authentication
- Proxies
- SSL/TLS configuration

#### Advanced Networking
- TCP/UDP sockets
- WebSocket implementation
- DNS resolution
- TLS/SSL
- Custom protocols

### 6.3.3 Data Processing

#### JSON Processing
- JSON parsing
- JSON validation
- JSON formatting
- JSON schema validation
- Streaming JSON

#### Cryptography
- Node.js crypto module
- Hashing
- Encryption and decryption
- Digital signatures
- Certificate handling
- Key generation

#### Data Structures
- Built-in data structures
- Buffer handling
- Stream processing
- Data validation

### 6.3.4 Security-Specific Libraries

#### Express Security Middleware
- helmet.js
- CORS
- Rate limiting
- Input validation
- Output sanitization
- Authentication middleware

#### Authentication and Authorization
- passport.js
- JWT libraries
- OAuth implementations
- Session management
- Permission handling

#### Vulnerability Detection
- npm audit
- OWASP dependency check
- Snyk
- Retire.js
- Security linters

### 6.3.5 Automation and Scripting

#### Task Automation
- Scripting patterns
- Job scheduling
- Cron jobs
- Event handling
- Error handling

#### API Testing
- Supertest
- Test frameworks
- Assertion libraries
- Mock servers
- Load testing

#### Web Scraping and Crawling
- Puppeteer
- Cheerio
- Playwright
- Browser automation
- Data extraction

---

## 6.4 Integration and Advanced Scripting

### 6.4.1 Multi-Language Integration

#### Python-Bash Integration
- Calling bash from Python
- Processing bash output
- Error handling
- Data exchange
- Performance considerations

#### Python-JavaScript Integration
- Node.js interaction
- Data exchange
- Process communication
- JSON serialization

#### API Orchestration
- Chaining API calls
- Data transformation
- Error handling
- Conditional execution

### 6.4.2 Tool Development

#### Building CLI Tools
- Command-line argument parsing
- User input handling
- Output formatting
- Configuration files
- Help and documentation

#### Building Utilities
- Reusable modules
- Library design
- API design
- Documentation
- Testing

#### Packaging and Distribution
- Python packaging
- Node.js packages
- Version management
- Dependency specification
- Release management

### 6.4.3 Continuous Integration and Automation

#### Git Integration
- Git hooks
- Automated testing
- Pre-commit checks
- Version management
- Change logging

#### CI/CD Pipelines
- Pipeline design
- Test automation
- Build automation
- Deployment automation
- Monitoring and alerts

#### Infrastructure as Code
- Configuration management
- Automated provisioning
- Repeatability
- Version control
- Disaster recovery

---

## 6.5 Module 6 Assessment

### 6.5.1 Knowledge Check
- Network scripting concepts
- File and data processing
- System automation
- Cryptographic operations
- Scripting best practices
- Security-focused scripting

### 6.5.2 Practical Exercises
- Network scanning script
- Log analysis automation
- System monitoring script
- Data processing pipeline
- API interaction tool
- Security audit script

### 6.5.3 Mini Projects
- Security tool development
- Automation framework
- Data analysis pipeline
- Monitoring solution
- Integration tool

---

---

# MODULE 7: TOOLS & ENVIRONMENT SETUP {#module-7}

## 7.1 Setting Up Security Lab Environment

### 7.1.1 Lab Architecture

#### Physical vs Virtual
- Physical lab
- Virtual machines
- Cloud-based labs
- Hybrid approach
- Cost considerations
- Resource requirements

#### Lab Isolation
- Network segmentation
- VLAN separation
- Firewall rules
- Air-gapped systems
- Isolated subnets
- Lab-to-internet isolation

#### Lab Topology
- Single machine setup
- Multi-machine setup
- Networked lab
- DMZ simulation
- Complex architectures
- Documentation

### 7.1.2 Virtualization Platforms

#### VirtualBox
- Installation and setup
- VM creation
- Virtual machine configuration
- Network configuration
- Snapshots and cloning
- Resource allocation
- Performance optimization
- Troubleshooting

#### VMware
- VMware Player/Pro
- VMware Workstation
- VMware vSphere
- vCenter
- Resource management
- Clustering
- Backup and recovery

#### Hyper-V
- Hyper-V on Windows
- Virtual machine creation
- Network configuration
- Integration services
- Performance monitoring
- Nested virtualization

#### KVM/QEMU
- Linux KVM
- QEMU
- Virsh commands
- Network configuration
- Storage management
- Performance tuning

#### Proxmox VE
- Container and VM hosting
- Cluster management
- Resource allocation
- Backup solutions
- High availability
- Datacenter solutions

### 7.1.3 Linux Distributions for Security

#### Kali Linux
- Kali features
- Package selection
- Installation
- Customization
- Live mode
- Persistence
- Tool collections
- Update management

#### Parrot Security OS
- Parrot features
- Tool integration
- Lightweight variants
- Security tools
- Community support

#### Ubuntu (Security-Focused)
- LTS versions
- Server vs Desktop
- Hardening
- Security tools installation
- Package management
- Support lifecycle

#### Debian
- Stability
- Security updates
- Lightweight
- Server use
- Minimal installation

#### CentOS/Rocky Linux
- Enterprise Linux
- Security-focused
- Server deployment
- Long-term support
- Package management

### 7.1.4 Container-Based Environments

#### Docker
- Container basics
- Docker installation
- Docker images
- Container creation
- Dockerfile creation
- Volume management
- Network configuration
- Container orchestration
- Security considerations

#### Docker Compose
- Multi-container applications
- Service definition
- Environment configuration
- Volume and network setup
- Scaling containers
- Environment management

#### Kubernetes
- Container orchestration
- Cluster setup
- Pod deployment
- Service configuration
- Storage management
- Security policies
- Monitoring and logging

### 7.1.5 Cloud-Based Lab Environments

#### AWS
- EC2 instances
- VPC setup
- Security groups
- Key pairs
- AMI selection
- Cost management
- Spot instances
- Auto-scaling

#### Azure
- Virtual machines
- Resource groups
- Virtual networks
- Network security groups
- VM sizing
- Managed services
- Cost considerations

#### Google Cloud
- Compute Engine
- Virtual networks
- Firewall rules
- Instance templates
- Cloud storage
- Networking

#### DigitalOcean
- Droplet creation
- Droplet sizing
- Networking
- SSH key setup
- Backup and snapshots
- Simplicity and affordability

---

## 7.2 Penetration Testing Tools

### 7.2.1 Reconnaissance Tools

#### Network Reconnaissance
- Nmap
  - Basic scanning
  - Port scanning types
  - Service detection
  - OS detection
  - Script engine (NSE)
  - Output formats
  - Optimization
  - Scanning techniques
- Masscan
  - Fast scanning
  - Large networks
  - Output formats
- Zmap
  - Internet-scale scanning
  - Performance
  - Data collection

#### Subdomain Enumeration
- Sublist3r
- DNSMap
- DNS Zone Transfer attacks
- Dig for subdomain discovery
- Amass
- Knockpy
- Fierce
- Subfinder
- Gobuster
- Assetfinder

#### DNS Tools
- nslookup
- dig
- host
- whois
- DIG for advanced queries
- DNSdumpster
- DNS reverse lookup
- Mail server enumeration

#### OSINT Tools
- TheHarvester
- Shodan
- Google dorks
- LinkedIn reconnaissance
- GitHub dorks
- Pastebin searches
- Archive.org
- WHOIS databases
- Domain registration info
- Email harvesting

### 7.2.2 Vulnerability Scanning Tools

#### Network Vulnerability Scanners
- Nessus
  - Scan configuration
  - Plugin management
  - Report generation
  - Remediation guidance
  - Compliance scanning
  - Vulnerability prioritization
- OpenVAS
  - Installation
  - Scan setup
  - Report generation
  - Task creation
  - Credential verification
- Qualys
- Rapid7 InsightVM

#### Web Application Scanners
- Burp Suite
  - Community edition
  - Professional edition
  - Scanner module
  - Crawler
  - Intruder tool
  - Repeater
  - Decoder
  - Comparer
  - Extensions
  - API testing
  - Customization
- OWASP ZAP
  - Active scanning
  - Passive scanning
  - Fuzzing
  - Spider
  - Proxy
  - API testing
  - Alert management
  - Scripts and automation
- Acunetix
- Netsparker
- Veracode
- Checkmarx

#### Code Scanning Tools
- SonarQube
  - Installation
  - Code analysis
  - Report generation
  - Issue tracking
  - Quality gates
- Checkmarx
- Veracode
- Snyk
- WhiteSource
- Fortify

### 7.2.3 Exploitation Frameworks

#### Metasploit Framework
- Metasploit basics
- Modules (exploits, payloads, encoders, nops)
- Msfconsole
- Msfvenom
- Payload generation
- Post-exploitation
- Scripting
- Automation

#### Custom Exploits
- Exploit development
- Shellcode creation
- Payload encoding
- Anti-virus evasion
- Exploit verification

### 7.2.4 Password Testing Tools

#### Offline Password Attacks
- Hashcat
  - Hash cracking
  - Rule creation
  - GPU acceleration
  - Wordlist management
  - Mask attacks
  - Dictionary attacks
  - Brute force
- John the Ripper
  - Wordlist attacks
  - Rule creation
  - Format detection
  - GPU support
- RainbowCrack
- Dictionary generation
- Custom wordlists

#### Online Password Attacks
- Hydra
  - Service attack
  - Wordlist usage
  - Custom dictionaries
  - Parallel attacks
  - SSL/TLS support
- Medusa
- THC-Hydra
- Credential stuffing tools
- Brute force frameworks

#### Password Analysis
- Crunch (wordlist generation)
- Cewl (custom wordlists)
- Mentalist
- CommonRegexes
- Password strength analysis

### 7.2.5 Web Application Testing Tools

#### Proxy Tools
- Burp Suite
  - Proxy setup
  - Traffic interception
  - Request modification
  - Cookie editing
  - Session management
- OWASP ZAP
  - Proxy configuration
  - Traffic capture
  - Request editing
  - Automated scanning
- Fiddler
- Charles Proxy
- Mitmproxy

#### Fuzzing Tools
- Burp Intruder
- Fuzz testing frameworks
- AFL (American Fuzzy Lop)
- Radamsa
- Custom fuzzers

#### API Testing
- Postman
  - Request creation
  - Collection management
  - Test scripting
  - Automation
  - API documentation
  - Collaboration
- Insomnia
- REST Client extensions
- GraphQL testing
- SOAP testing

#### Template Injection Testing
- Tplmap
- Burp Scanner
- Manual testing techniques
- Template syntax understanding

### 7.2.6 Network Analysis Tools

#### Packet Capture and Analysis
- Wireshark
  - Packet capture
  - Filtering
  - Protocol analysis
  - Follow streams
  - Statistics
  - Display filters
  - Troubleshooting
  - Post-analysis
- tcpdump
  - Command-line packet capture
  - Filter syntax
  - Output options
  - Writing to files
  - Reading captures
- tshark (Wireshark CLI)

#### Network Mapping
- Graphviz
- Network diagram creation
- Topology visualization
- Asset management

### 7.2.7 Wireless Testing Tools

#### Wireless Reconnaissance
- Airmon-ng
- Airodump-ng
- Kismet
- InSSIDer
- NetStumbler

#### Wireless Attacks
- Aircrack-ng
  - WEP cracking
  - WPA cracking
  - Packet injection
  - Key recovery
- Cowpatty
- Pyrit
- Reaver
- Wifite

### 7.2.8 Social Engineering Tools

#### Phishing Tools
- Gophish
- King Phisher
- Email template design
- Credential harvesting
- Campaign management
- Statistics and reporting

#### Physical Security
- Physical test reports
- Lock picking tools
- Badge cloning
- Video documentation

### 7.2.9 Post-Exploitation Tools

#### Privilege Escalation
- Windows PE (Privilege Escalation)
  - Windows exploit suggester
  - Accesschk
  - Potato exploits
  - Bypass UAC
  - Kernel exploits
- Linux PE
  - LinEnum
  - Linux Privilege Checker
  - Unix Privesc checker
  - Kernel exploits
  - Sudo abuse
  - SUID binaries

#### Persistence
- Rootkit frameworks
- Backdoor installation
- Scheduled tasks
- Startup persistence
- Registry persistence
- Boot persistence
- Firmware persistence

#### Data Exfiltration
- Data transfer methods
- Encryption for exfiltration
- Covert channels
- Data staging
- Compression

### 7.2.10 Reporting Tools

#### Report Generation
- Burp Report generator
- Nessus reporting
- Custom report templates
- Executive summaries
- Detailed findings
- Evidence compilation
- Remediation guidance

#### Documentation
- Markdown
- LaTeX
- HTML report generation
- PDF export
- Version control

---

## 7.3 Development and Analysis Tools

### 7.3.1 Reverse Engineering Tools

#### Disassemblers and Decompilers
- IDA Pro
  - Binary analysis
  - Disassembly
  - Debugging
  - Plugin ecosystem
  - Scripting
- Ghidra
  - FOSS alternative
  - NSA tool
  - Disassembly
  - Decompilation
  - Scripting
  - Collaborative analysis
- Radare2
  - CLI tool
  - Community-driven
  - Scripting support
  - Plugin system
- Binary Ninja
- Rizin

#### Debuggers
- GDB (GNU Debugger)
  - Breakpoints
  - Watchpoints
  - Stepping
  - Memory examination
  - Register inspection
- WinDbg
- LLDB
- OllyDbg
- x64dbg
- Frida (dynamic instrumentation)

#### Binary Analysis
- Strings analysis
- Hex editors (HxD, Hex Fiend)
- Binary comparison
- Entropy analysis
- Signature analysis

### 7.3.2 Code Analysis Tools

#### Static Analysis
- SonarQube
- Checkmarx
- Fortify
- Veracode
- Snyk
- Bandit
- Pylint
- ESLint
- FindBugs

#### Dynamic Analysis
- Valgrind
- AddressSanitizer
- MemorySanitizer
- ThreadSanitizer
- Runtime analysis

#### Coverage Analysis
- Code coverage tools
- Test coverage
- Branch coverage
- Path coverage

### 7.3.3 Debugging Tools

#### Symbol and Source
- Debug symbols
- Source maps
- Symbol servers
- Source debugging
- Step-through debugging

#### Memory Analysis
- Valgrind
- LeakSanitizer
- Memory debugging
- Heap analysis
- Stack analysis
- Out-of-bounds detection

---

## 7.4 Monitoring and Logging Tools

### 7.4.1 SIEM Solutions

#### Splunk
- Data indexing
- Search and analysis
- Alerting
- Dashboards
- Report creation
- Automation
- Integration
- Scaling

#### ELK Stack (Elasticsearch, Logstash, Kibana)
- Elasticsearch
  - Indexing
  - Querying
  - Performance
- Logstash
  - Log parsing
  - Filtering
  - Enrichment
  - Output
- Kibana
  - Visualization
  - Dashboarding
  - Alerting
  - DevTools
- Beats (data shippers)

#### Splunk, ArcSight, IBM QRadar
- Log aggregation
- Threat detection
- Correlation
- Reporting

### 7.4.2 IDS/IPS Systems

#### Snort
- Installation and configuration
- Rules and signatures
- Modes (sniffer, packet logger, NIDS)
- Alert configuration
- Output formats
- Preprocessors
- Performance tuning

#### Suricata
- Open-source IDS/IPS
- Rule format
- Configuration
- Multi-threading
- Protocol analysis
- File extraction
- JavaScript processing

#### Zeek (formerly Bro)
- Network monitoring
- Protocol analysis
- Scripting
- File analysis
- Communication analysis

### 7.4.3 Logging Tools

#### Syslog
- Syslog protocol
- Syslog configuration
- Log levels
- Log sources
- Log forwarding
- Centralized logging

#### Rsyslog
- Advanced syslog
- Filtering
- Routing
- Encryption
- High-speed log processing

#### Fluentd
- Log collection
- Log processing
- Log forwarding
- Reliability
- Scalability

### 7.4.4 Alerting and Notification

#### Alert Configuration
- Threshold-based alerts
- Pattern-based alerts
- Anomaly detection
- Alert routing
- Escalation procedures
- On-call management

#### Notification Methods
- Email alerts
- Slack integration
- PagerDuty
- SMS alerts
- Webhook integration

---

## 7.5 Version Control and Collaboration

### 7.5.1 Git and GitHub

#### Git Basics
- Repository initialization
- Staging changes
- Committing
- Branching
- Merging
- Rebasing
- Conflict resolution
- History navigation

#### GitHub
- Repository creation
- Collaboration
- Pull requests
- Code review
- Issue tracking
- Project management
- Wiki
- GitHub Pages
- Security features
- GitHub Actions

#### Git Best Practices
- Commit messages
- Branch strategy
- Code review process
- Documentation
- Security considerations

### 7.5.2 GitLab and Gitea

#### GitLab
- Self-hosted option
- CI/CD pipelines
- Container registry
- Security scanning
- Vulnerability management
- SAST and DAST
- Dependency scanning

#### Gitea
- Lightweight Git service
- Self-hosted
- Easy installation
- Low resource requirements

### 7.5.3 Collaboration Tools

#### Documentation
- Wiki systems
- Markdown documentation
- README files
- API documentation
- Swagger/OpenAPI

#### Communication
- Team chat (Slack, Teams, Discord)
- Video conferencing
- Email systems
- Knowledge bases

---

## 7.6 Automation and Scheduling

### 7.6.1 CI/CD Pipelines

#### GitHub Actions
- Workflow configuration
- Actions and runners
- Secrets management
- Artifact storage
- Workflow triggers
- Matrix builds
- Caching
- Notifications

#### GitLab CI/CD
- .gitlab-ci.yml configuration
- Runners
- Stages and jobs
- Artifacts
- Dependencies
- Caching
- Security scanning

#### Jenkins
- Job configuration
- Pipeline creation
- Plugin ecosystem
- Distributed builds
- Integration
- Security
- Scalability

#### Travis CI, CircleCI
- Cloud-based CI
- Easy setup
- Configuration files
- Build matrix
- Deployment

### 7.6.2 Infrastructure as Code

#### Terraform
- Infrastructure definition
- Provider configuration
- Resource management
- State management
- Modules
- Variables and outputs
- Backends

#### Ansible
- Playbook creation
- Task definition
- Handlers
- Variables
- Templating
- Roles
- Dynamic inventory
- Idempotency

#### CloudFormation
- Stack creation
- Template design
- Parameters and outputs
- Change sets
- Integration with AWS services

#### Docker and Kubernetes
- Container orchestration
- Deployment automation
- Scaling
- Self-healing
- Rolling updates

---

## 7.7 Documentation and Knowledge Management

### 7.7.1 Documentation Tools

#### Technical Documentation
- Sphinx
- MkDocs
- Docusaurus
- Hugo
- Jekyll
- Confluence

#### Diagram Tools
- Lucidchart
- Draw.io
- Miro
- Visio
- PlantUML
- Graphviz

### 7.7.2 Knowledge Bases

#### Internal Documentation
- Wiki systems
- Knowledge bases
- FAQ systems
- Procedure documentation
- Lab documentation

---

## 7.8 Module 7 Assessment

### 7.8.1 Knowledge Check
- Lab environment setup
- Tool selection and usage
- Penetration testing tools
- Analysis tools
- Monitoring and logging
- Automation and CI/CD

### 7.8.2 Practical Exercises
- Lab environment setup
- Tool installation and configuration
- Basic scanning and enumeration
- Vulnerability scanning
- Report generation

### 7.8.3 Mini Projects
- Complete lab environment
- Tool integration
- Automated workflow
- Documentation and knowledge base

---

---

# MODULE 8: LAB EXERCISES & PRACTICAL PROJECTS {#module-8}

## 8.1 Fundamental Exercises

### 8.1.1 Programming Exercises

#### Python Exercises
- Python basics (variables, loops, functions)
- Data structure manipulation
- File I/O and processing
- Error handling
- Module usage
- Script writing

#### Bash Exercises
- Command-line fundamentals
- Text processing
- File operations
- System administration
- Scripting fundamentals
- Automation basics

#### JavaScript Exercises
- DOM manipulation
- Event handling
- Async operations
- Form validation
- API calls

### 8.1.2 Networking Exercises

#### IP and Subnetting
- Subnetting calculations
- CIDR notation
- Network design
- Addressing schemes
- Subnet planning

#### Network Tools Practice
- Ping and ICMP
- Traceroute analysis
- DNS queries
- Netstat usage
- ARP operations

#### Protocol Analysis
- Packet identification
- Protocol header analysis
- Wireshark filtering
- Traffic analysis
- Anomaly detection

### 8.1.3 Operating System Exercises

#### Linux Practice
- File and directory operations
- User and group management
- Permission management
- Process management
- Service management
- Log analysis
- System administration

#### Windows Practice
- File system navigation
- User management
- Registry access
- Service management
- Event viewer analysis
- Command-line operations

---

## 8.2 Web Application Security Labs

### 8.2.1 OWASP WebGoat

#### Injection Attacks
- SQL Injection
- OS Command Injection
- XPath Injection
- LDAP Injection
- Prevention techniques

#### Broken Authentication
- Session management
- Password management
- Credential storage
- Multi-factor authentication
- Authentication bypass

#### XSS Vulnerabilities
- Reflected XSS
- Stored XSS
- DOM-based XSS
- XSS prevention
- Content Security Policy

#### Broken Access Control
- Horizontal access control
- Vertical access control
- IDOR vulnerabilities
- Authorization flaws
- Privilege escalation

#### Security Misconfiguration
- Default credentials
- Unnecessary services
- Unpatched systems
- Security headers
- Error handling

#### Sensitive Data Exposure
- Encryption
- SSL/TLS
- Data classification
- Secure transmission
- Secure storage

### 8.2.2 DVWA (Damn Vulnerable Web Application)

#### Brute Force
- Login page attacks
- Dictionary attacks
- Credential stuffing
- Rate limiting

#### Command Injection
- OS command execution
- Command chaining
- Blind command injection
- Prevention

#### CSRF
- CSRF tokens
- SameSite cookies
- Referrer validation
- Form submission attacks

#### File Inclusion
- Local file inclusion
- Remote file inclusion
- Path traversal
- File upload vulnerabilities

#### File Upload
- Upload validation
- File type checking
- Executable upload
- Reverse shell upload

#### Insecure CAPTCHA
- CAPTCHA weaknesses
- Image recognition
- Token reuse
- CAPTCHA bypass

#### SQL Injection
- Union-based SQL Injection
- Error-based SQL Injection
- Blind SQL Injection
- Time-based blind SQLi
- Prevention

#### Weak Session IDs
- Session prediction
- Session fixation
- Cookie analysis
- Token replay

#### XSS (DOM, Reflected, Stored)
- Cookie stealing
- Session hijacking
- Malware injection
- Phishing

### 8.2.3 HackTheBox Challenges

#### Easy Challenges
- Reconnaissance and enumeration
- Web application testing
- Basic vulnerability exploitation
- Report generation

#### Medium Challenges
- Complex vulnerability chains
- Logic flaws
- Authentication bypass
- Authorization testing

#### Hard Challenges
- Advanced exploitation
- Zero-day-like vulnerabilities
- Complex systems
- Lateral movement

### 8.2.4 TryHackMe Rooms

#### Common Topics
- Network basics
- Linux fundamentals
- Windows fundamentals
- Web security
- Cryptography
- Forensics
- Malware analysis
- Incident response
- Red team tactics
- Blue team defense

---

## 8.3 Hands-On Penetration Testing Labs

### 8.3.1 Network Penetration Testing

#### Network Reconnaissance
- Passive reconnaissance
- OSINT gathering
- Nmap scanning
- Service enumeration
- OS identification
- Vulnerability identification

#### Network Exploitation
- Default credentials
- Known vulnerabilities
- Weak configurations
- Unpatched systems
- Privilege escalation
- Post-exploitation

#### Network Lab Scenarios
- Small network penetration test
- Multi-subnet network
- DMZ penetration test
- Internal network assessment
- Wireless network testing

### 8.3.2 Web Application Penetration Testing

#### Reconnaissance
- Target identification
- Subdomain enumeration
- Technology stack identification
- Directory enumeration
- API discovery
- Source code analysis

#### Manual Testing
- Parameter tampering
- Input validation testing
- Authentication testing
- Session management testing
- Authorization testing
- Business logic testing

#### Automated Testing
- Burp Scanner
- OWASP ZAP
- Nikto
- SQLMap
- Report generation

#### Exploitation
- XSS exploitation
- SQL injection exploitation
- Authentication bypass
- Authorization bypass
- CSRF attacks
- File inclusion
- File upload
- SSRF
- XXE

#### Post-Exploitation
- Data extraction
- Credential discovery
- Lateral movement
- Persistence
- Covering tracks

### 8.3.3 Wireless Penetration Testing

#### Reconnaissance
- SSID discovery
- Signal strength mapping
- Security type identification
- Encryption detection
- Client analysis

#### WEP Cracking
- Packet capture
- IV collection
- Key recovery
- Network access

#### WPA/WPA2 Cracking
- Handshake capture
- Dictionary attacks
- Custom wordlists
- GPU-accelerated cracking
- Key recovery

#### Post-Connection
- Network access
- Network traffic analysis
- DHCP assignment
- DNS interception
- Man-in-the-middle

### 8.3.4 Physical Security Testing

#### Physical Reconnaissance
- Location assessment
- Access points identification
- Security controls observation
- Visual survey
- Tailgating opportunities

#### Physical Exploitation
- Lock bypass
- Badge cloning
- Social engineering
- Credential harvesting
- Data collection

#### Report Generation
- Findings documentation
- Risk assessment
- Remediation recommendations
- Evidence collection
- Professional presentation

---

## 8.4 Malware and Forensics Labs

### 8.4.1 Malware Analysis

#### Static Analysis
- File type identification
- String extraction
- Import analysis
- Metadata examination
- Packer detection
- Signature analysis

#### Dynamic Analysis
- Sandbox execution
- Behavior monitoring
- API call logging
- Network traffic analysis
- Registry monitoring
- File system monitoring
- Process monitoring

#### Decompilation and Reverse Engineering
- Binary disassembly
- Decompilation
- Code analysis
- Logic understanding
- Function identification
- Algorithm analysis

#### Malware Samples
- EICAR test file
- Intentional benign malware
- Controlled environments
- Documentation of behavior
- Extraction of indicators

### 8.4.2 Digital Forensics

#### Memory Forensics
- Memory capture
- Memory analysis
- Process recovery
- String recovery
- Rootkit detection
- Malware extraction

#### Disk Forensics
- Disk imaging
- File recovery
- Slack space analysis
- Partition analysis
- File carving
- Timeline analysis

#### File System Forensics
- NTFS analysis
- Ext4 analysis
- FAT32 analysis
- Alternate data streams
- Journaling analysis
- Unallocated space analysis

#### Timeline Analysis
- Event reconstruction
- Timeline creation
- Suspicious activity identification
- User activity analysis
- Malware installation timeline

#### Network Forensics
- Packet capture analysis
- Network flow analysis
- Connection identification
- Malware communication
- Data exfiltration
- Attack reconstruction

#### Forensics Tools Practice
- Autopsy
- FTK Imager
- WinHex
- Volatility
- Wireshark
- tcpdump
- grep and awk

---

## 8.5 Security Assessment Projects

### 8.5.1 Small Business Assessment

#### Scope Definition
- Target selection
- Scope documentation
- Rules of engagement
- Testing timeline
- Approval process

#### Information Gathering
- Company research
- Technology identification
- Employee information
- Infrastructure mapping
- Vulnerability sources

#### Vulnerability Assessment
- Network scanning
- Web application scanning
- Configuration review
- Patch status
- Known vulnerabilities

#### Remediation and Reporting
- Finding compilation
- Risk rating
- Remediation guidance
- Executive summary
- Detailed findings
- Evidence documentation

### 8.5.2 Enterprise Security Assessment

#### Multi-Department Testing
- Network segmentation
- Department isolation
- Cross-department access
- Data flow analysis
- Compliance checking

#### Advanced Exploitation
- Vulnerability chaining
- Lateral movement
- Privilege escalation
- Persistence establishment
- Data exfiltration simulation

#### Reporting to Executives
- Executive summary
- Business impact
- Risk prioritization
- Remediation roadmap
- Metrics and KPIs

### 8.5.3 Secure Code Review Project

#### Code Review Process
- Code examination
- Vulnerability identification
- Pattern recognition
- Best practices verification
- Security checklist

#### Common Vulnerabilities in Code
- Input validation flaws
- Output encoding issues
- Authentication weaknesses
- Authorization flaws
- Cryptographic issues
- Session management
- Error handling

#### Remediation Suggestions
- Code fixes
- Architecture changes
- Configuration adjustments
- Library updates
- Best practices implementation

---

## 8.6 Incident Response Scenarios

### 8.6.1 Incident Response Simulations

#### Breach Scenario
- Initial detection
- Incident classification
- Team activation
- Evidence preservation
- Investigation
- Containment
- Eradication
- Recovery
- Post-incident analysis

#### Malware Incident
- Detection
- Isolation
- Analysis
- Indicators extraction
- Hunting
- Cleanup
- Prevention

#### Data Loss Scenario
- Discovery
- Scope determination
- Investigation
- Notification
- Recovery
- Remediation
- Prevention

#### Denial of Service
- Detection
- Traffic analysis
- Source identification
- Mitigation
- Recovery
- Prevention

### 8.6.2 Forensics Investigation

#### Case Analysis
- Case scenario
- Investigative process
- Evidence collection
- Analysis
- Timeline creation
- Report writing
- Expert testimony

---

## 8.7 CTF (Capture The Flag) Challenges

### 8.7.1 CTF Fundamentals

#### CTF Types
- Jeopardy-style CTFs
- Attack-defense CTFs
- King-of-the-hill CTFs
- Mixed-mode CTFs

#### Common Challenge Categories
- Web exploitation
- Reverse engineering
- Cryptography
- Forensics
- Steganography
- Pwning (binary exploitation)
- Miscellaneous challenges

### 8.7.2 CTF Resources

#### Public CTF Platforms
- PicoCTF
- CTFTIME
- HackTheBox CTF
- TryHackMe CTF
- RootMe
- OverTheWire
- CSAW CTF

#### CTF Competitions
- Regional competitions
- National competitions
- International competitions
- Online competitions
- Practice competitions

---

## 8.8 Real-World Scenarios

### 8.8.1 Bug Bounty Preparation

#### Platform Selection
- HackerOne
- Bugcrowd
- Intigriti
- Yeswehack
- YesWeHack

#### Target Selection
- Private programs
- Public programs
- Scope analysis
- Vulnerability research
- Report quality

#### Report Writing
- Clear descriptions
- Proof of concept
- Impact assessment
- Remediation suggestions
- Professional tone

### 8.8.2 Vulnerability Research

#### Zero-Day Research
- Vulnerability discovery
- Proof of concept development
- Responsible disclosure
- CVE assignment
- Public announcement

#### Known Vulnerability Analysis
- Historical vulnerabilities
- Root cause analysis
- Impact assessment
- Fix verification
- Pattern recognition

### 8.8.3 Tool Development Project

#### Security Tool Creation
- Problem identification
- Tool design
- Feature specification
- Implementation
- Testing
- Documentation
- Release
- Community support

---

## 8.9 Capstone Project

### 8.9.1 Comprehensive Security Assessment

#### Project Scope
- Target selection
- Scope definition
- Timeline planning
- Resource allocation
- Risk mitigation

#### Reconnaissance Phase
- OSINT
- Network mapping
- Service identification
- Technology stack analysis
- Vulnerability research

#### Testing Phase
- Vulnerability scanning
- Manual testing
- Exploitation
- Post-exploitation
- Evidence documentation

#### Analysis and Reporting
- Findings compilation
- Risk rating
- Prioritization
- Remediation guidance
- Executive summary
- Detailed report
- Evidence compilation

#### Presentation
- Findings presentation
- Risk explanation
- Business impact
- Remediation roadmap
- Q&A preparation

### 8.9.2 Tool or Framework Development

#### Development Phases
- Requirements gathering
- Architecture design
- Development
- Testing
- Documentation
- Deployment
- Maintenance

#### Project Documentation
- README
- Installation guide
- Usage documentation
- API documentation
- Contributing guidelines
- License selection

### 8.9.3 Specialized Security Research

#### Research Topic Selection
- Emerging threats
- Novel techniques
- Vulnerability analysis
- Tool development
- Best practices research

#### Research Deliverables
- Research paper
- Proof of concept
- Presentation
- Findings summary
- Implementation guide

---

## 8.10 Module 8 Assessment

### 8.10.1 Practical Evaluation
- Lab completion
- Exercise execution
- Findings documentation
- Report quality
- Communication skills

### 8.10.2 Project Evaluation
- Scope completion
- Technical execution
- Professional presentation
- Documentation quality
- Risk assessment accuracy

### 8.10.3 Knowledge Demonstration
- Concept understanding
- Skill application
- Tool proficiency
- Methodology mastery
- Real-world application

---

---

# COURSE COMPLETION AND NEXT STEPS

## Certification Paths
- CEH (Certified Ethical Hacker)
- OSCP (Offensive Security Certified Professional)
- CompTIA Security+
- GPEN (GIAC Penetration Tester)
- ECSA (EC-Council Certified Security Analyst)

## Continued Learning
- Advanced penetration testing
- Web application security specialization
- Malware analysis and reverse engineering
- Incident response and forensics
- Security architecture and design
- Cloud security
- DevSecOps
- AI/ML in cybersecurity

## Community Involvement
- CTF participation
- Bug bounty hunting
- Conference attendance
- Community contribution
- Blogging and documentation
- Mentoring others
- Open-source contribution

## Career Development
- Job opportunities
- Specialization paths
- Freelance security work
- Consulting
- Training and education
- Research and development

---

## APPENDIX

### A. Command Reference

#### Linux Commands
- File operations
- System administration
- Network commands
- Text processing
- Process management

#### Windows Commands
- Command Prompt commands
- PowerShell commands
- System administration
- Network utilities

#### Network Tools
- ping, traceroute, nslookup
- netstat, ss, netcat
- iptables, firewall commands
- SSH, SCP commands
- DNS tools

### B. Python Libraries Reference

#### Standard Library
- os, sys, re
- json, csv
- hashlib, hmac
- socket, urllib
- subprocess, logging

#### Third-Party Libraries
- requests
- BeautifulSoup
- paramiko
- cryptography
- scapy
- pwntools

### C. Web Security Checklist

- OWASP Top 10 items
- Security headers
- Authentication mechanisms
- Authorization controls
- Input validation
- Output encoding
- Encryption requirements
- Logging and monitoring
- Security updates
- Compliance requirements

### D. Network Hardening Checklist

- Firewall rules
- Access control lists
- Network segmentation
- VLANs
- VPN configuration
- Intrusion detection
- Log aggregation
- Network monitoring
- Patch management
- Change management

### E. System Hardening Checklist

- OS updates and patches
- Service minimization
- User account management
- Permission configuration
- Firewall configuration
- Audit logging
- Antivirus/antimalware
- Disk encryption
- SSH hardening
- Secure boot

### F. Useful Resources

#### Documentation
- RFC documents
- Official tool documentation
- OWASP resources
- NIST publications
- CIS Benchmarks

#### Training Platforms
- TryHackMe
- HackTheBox
- PicoCTF
- OverTheWire
- SANS Cyber Aces

#### Communities
- GitHub
- Stack Exchange
- Reddit (r/cybersecurity)
- Twitter security community
- Security conferences

#### Blogs and Publications
- Krebs on Security
- Dark Reading
- Ars Technica
- SecurityWeekly
- SANS Security Blog

---

## GLOSSARY

### Common Terms
- API - Application Programming Interface
- ARP - Address Resolution Protocol
- BIOS - Basic Input/Output System
- CIDR - Classless Inter-Domain Routing
- CLI - Command Line Interface
- CVE - Common Vulnerabilities and Exposures
- CVSS - Common Vulnerability Scoring System
- DDoS - Distributed Denial of Service
- DHCP - Dynamic Host Configuration Protocol
- DMZ - Demilitarized Zone
- DNS - Domain Name System
- DoS - Denial of Service
- DRAM - Dynamic Random Access Memory
- ECC - Elliptic Curve Cryptography
- FTP - File Transfer Protocol
- GID - Group ID
- GUI - Graphical User Interface
- HMAC - Hash-based Message Authentication Code
- HTML - HyperText Markup Language
- HTTP - HyperText Transfer Protocol
- HTTPS - HyperText Transfer Protocol Secure
- IANA - Internet Assigned Numbers Authority
- ICMP - Internet Control Message Protocol
- IDS - Intrusion Detection System
- IP - Internet Protocol
- IPS - Intrusion Prevention System
- LDAP - Lightweight Directory Access Protocol
- LLM - Large Language Model
- MAC - Media Access Control
- MD5 - Message Digest Algorithm 5
- MFA - Multi-Factor Authentication
- NAT - Network Address Translation
- NTP - Network Time Protocol
- OWASP - Open Web Application Security Project
- PGP - Pretty Good Privacy
- PKI - Public Key Infrastructure
- RAM - Random Access Memory
- RFC - Request for Comments
- RSA - Rivest–Shamir–Adleman
- SAST - Static Application Security Testing
- SHA - Secure Hash Algorithm
- SIEM - Security Information and Event Management
- SNMP - Simple Network Management Protocol
- SOC - Security Operations Center
- SSH - Secure Shell
- SSL - Secure Sockets Layer
- SSRF - Server-Side Request Forgery
- SSTI - Server-Side Template Injection
- TCP - Transmission Control Protocol
- TLS - Transport Layer Security
- TOTP - Time-Based One-Time Password
- UDP - User Datagram Protocol
- UID - User ID
- URI - Uniform Resource Identifier
- URL - Uniform Resource Locator
- USB - Universal Serial Bus
- VPN - Virtual Private Network
- WAN - Wide Area Network
- WAF - Web Application Firewall
- XSS - Cross-Site Scripting
- XXE - XML External Entity
- YAML - YAML Ain't Markup Language
- ZIP - Zero-Trust Information Protection
