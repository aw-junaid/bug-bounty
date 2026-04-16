# VHosts

Virtual Host (vhost) enumeration discovers additional websites hosted on the same IP address but responding to different hostnames. A single web server can be configured to run multiple websites simultaneously under different domain names, a configuration commonly found in shared hosting environments . For penetration testers, discovering all vhosts on a web server is critical because each website may contain vulnerabilities that affect the same server; if one website is compromised, there is a high chance the attacker can gain unauthorized access to other websites on the same server .

## Why VHost Enumeration Matters

* Web servers can host multiple sites on one IP using the `Host` header
* Subdomains may not have DNS records (internal/dev sites)
* Different vhosts may have different security postures
* Can reveal admin panels, staging environments, APIs
* Attack surface mapping: discover additional entry points that may be less secure than the main website 

## How VHost Discovery Works

The process involves sending HTTP requests with different `Host` header values and analyzing responses to identify valid hosts while filtering out false positives . Unlike subdomain enumeration which relies on DNS resolution, vhost discovery works by directly testing the HTTP `Host` header . This distinction is important because many virtual hosts may not have public DNS records at all.

## Enumeration Techniques

### Using ffuf (Recommended)

```bash
# Basic vhost fuzzing
ffuf -w /usr/share/seclists/Discovery/DNS/subdomains-top1million-5000.txt \
  -u http://10.10.10.10 -H "Host: FUZZ.target.com" -fs 0

# Filter by response size (adjust based on default response)
ffuf -w /usr/share/seclists/Discovery/DNS/subdomains-top1million-20000.txt \
  -u http://10.10.10.10 -H "Host: FUZZ.target.com" -fs 4242

# Filter by status code
ffuf -w wordlist.txt -u http://10.10.10.10 -H "Host: FUZZ.target.com" -fc 400,404

# With HTTPS
ffuf -w wordlist.txt -u https://10.10.10.10 -H "Host: FUZZ.target.com" -fs 0
```

**Practical Example from CTF (Whiterose/TryHackMe):** During the Whiterose challenge, FFUF was used to enumerate virtual hosts. The output showed multiple results, but two stood out based on response sizes: `www` returned 252 bytes, while `admin` returned only 28 bytes. Both were added to `/etc/hosts`, revealing an admin login page at `admin.cyprusbank.thm` .

**Practical Example from TakeOver CTF:** When no DNS services were running on the target machine, vhost fuzzing with ffuf was performed using the command:
```bash
ffuf -w /usr/share/wordlists/SecLists/Discovery/DNS/bitquark-subdomains-top100000.txt -H "Host: FUZZ.futurevera.thm" -u https://10.10.193.63
```
The initial scan returned many false positives with a consistent response size of 4605 bytes, indicating a default response for non-existent hosts. Filtering with `-fs 4605` revealed two valid subdomains: `support` (Size: 1522) and `blog` (Size: 3838) .

### Using gobuster

```bash
# Vhost mode
gobuster vhost -u http://target.com -w /usr/share/seclists/Discovery/DNS/subdomains-top1million-5000.txt

# With specific IP
gobuster vhost -u http://10.10.10.10 -w wordlist.txt --domain target.com --append-domain

# Filter unwanted status codes
gobuster vhost -u http://target.com -w wordlist.txt --exclude-length 0
```

**Practical Example from HTB Lab:** During a Hack The Box engagement targeting `inlanefreight.htb`, Gobuster was used with the command:
```bash
gobuster vhost -u http://inlanefreight.htb:56255 -w /usr/share/seclists/Discovery/DNS/subdomains-top1million-20000.txt --append-domain -t 10
```
This successfully identified multiple valid subdomains including `blog.inlanefreight.htb`, `admin.inlanefreight.htb`, `support.inlanefreight.htb`, `vm5.inlanefreight.htb`, and `web17611.inlanefreight.htb`. When a specific prefix ("su") wasn't found, a targeted custom wordlist was created for focused scanning .

### Using wfuzz

```bash
# Basic vhost fuzzing
wfuzz -c -w wordlist.txt -H "Host: FUZZ.target.com" --hc 400,404 http://10.10.10.10

# Hide responses by size
wfuzz -c -w wordlist.txt -H "Host: FUZZ.target.com" --hh 1234 http://10.10.10.10

# Hide responses by word count
wfuzz -c -w wordlist.txt -H "Host: FUZZ.target.com" --hw 50 http://10.10.10.10
```

### Specialized Tools

```bash
# Virtual Host Discovery
# https://github.com/jobertabma/virtual-host-discovery
ruby scan.rb --ip=192.168.1.101 --host=domain.tld

# VHosts Sieve - Find vhosts in non-resolvable domains
# https://github.com/dariusztytko/vhosts-sieve
python3 vhosts-sieve.py -d domains.txt -o vhosts.txt

# HostHunter - Discover hostnames from IP ranges
# https://github.com/SpiderLabs/HostHunter
python3 hosthunter.py targets.txt -o hosts.txt

# auto_fuzz - Python3 wrapper that runs ffuf and gobuster in streaming mode
# https://github.com/Rsa-001/auto_fuzz
python3 auto_fuzz.py
```
The `auto_fuzz` tool discovers directories, files, and virtual host subdomains on a target IP/hostname, printing results live as they are found. It is particularly useful for CTF-style machines like HackTheBox labs .

## Finding VHosts Without DNS

```bash
# Extract potential hostnames from SSL certificates
echo | openssl s_client -connect 10.10.10.10:443 2>/dev/null | openssl x509 -noout -text | grep -oP '(?<=DNS:)[^,]+'

# Check certificate SAN (Subject Alternative Names)
nmap --script ssl-cert -p 443 10.10.10.10

# Reverse DNS lookup
host 10.10.10.10

# Check for common internal hostnames
for host in admin dev staging test api internal portal; do
  curl -s -o /dev/null -w "%{http_code} - $host.target.com\n" \
    -H "Host: $host.target.com" http://10.10.10.10
done
```

**Real-World Example of Certificate Discovery:** In the TakeOver CTF challenge, after discovering two subdomains (`support` and `blog`), further enumeration stalled until the SSL certificates were analyzed. While the `blog` subdomain certificate appeared normal, the `support` subdomain certificate contained valuable information in its Subject Alternative Name (SAN) field, revealing `secrethelpdesk934752.support.futurevera.thm`. Adding this to `/etc/hosts` led to an error page with the flag in the URL .

## Adding Discovered VHosts

```bash
# Add to /etc/hosts for testing
echo "10.10.10.10 dev.target.com staging.target.com admin.target.com" | sudo tee -a /etc/hosts

# Or use curl with Host header directly
curl -H "Host: dev.target.com" http://10.10.10.10/
```

## Wordlists for VHost Fuzzing

```bash
# SecLists
/usr/share/seclists/Discovery/DNS/subdomains-top1million-5000.txt
/usr/share/seclists/Discovery/DNS/subdomains-top1million-20000.txt
/usr/share/seclists/Discovery/DNS/bitquark-subdomains-top100000.txt

# Common internal hostnames
/usr/share/seclists/Discovery/DNS/namelist.txt

# Additional Kali Linux wordlists
/usr/share/wordlists/SecLists/Discovery/DNS/virtual-hosts.txt
/usr/share/wordlists/SecLists/Discovery/Web-Content/common.txt
/usr/share/wordlists/dirbuster/directory-list-2.3-medium.txt
/usr/share/wordlists/SecLists/Discovery/DNS/raft-large-directories.txt
```
The choice of wordlist significantly impacts the success of vhost enumeration. SecLists provides a robust foundation for discovery, but targeted custom wordlists can fill gaps when initial scans are incomplete .

## Exploitation and Security Risks

### VHost Confusion Attacks

**Apache HTTP Server Vulnerability (CVE-2025-XXXX):** In Apache HTTP Server versions 2.4.35 through 2.4.63, an access control bypass vulnerability exists in some mod_ssl configurations. When multiple virtual hosts are configured with different trusted client certificates and `SSLStrictSNIVHostCheck` is not enabled, a client trusted to access one virtual host may gain unauthorized access to another virtual host. This can lead to data exposure and security breaches. The fix involves updating to Apache version 2.4.64 or later and enabling `SSLStrictSNIVHostCheck on` in virtual host configurations .

**TLS Session Ticket Confusion (STEK Attack):** Research presented at USENIX Security 2025 demonstrated how TLS session resumption in virtual hosting can introduce session ticket confusion vulnerabilities, potentially enabling bypass of both server and client authentication. All four major implementations analyzed—Apache, nginx, (Open)LiteSpeed, and Caddy—were found vulnerable to client authentication bypasses. Large-scale scans identified six clusters of vulnerable providers, including Fastly, susceptible to server authentication bypasses. This highlights inconsistent isolation of virtual hosts following TLS session resumption .

**Twisted NameVirtualHost Host Header Injection (CVE-2022-39348):** In Twisted versions 0.9.4 through 22.10.0, when the host header does not match a configured host, `twisted.web.vhost.NameVirtualHost` returns a `NoResource` resource that renders the Host header unescaped into the 404 response, allowing HTML and script injection. Example vulnerable configuration:
```python
from twisted.web.server import Site
from twisted.web.vhost import NameVirtualHost
resource = NameVirtualHost()
site = Site(resource)
```
An attacker could send `curl -H"Host:<h1>HELLO THERE</h1>" http://localhost:8080/` and have HTML injected into the error page. The vulnerability was fixed in version 22.10.0rc1 .

**frp Authentication Bypass (GHSA-pq96-pwvg-vrr9):** In frp versions 0.43.0 through 0.68.0, an authentication bypass vulnerability exists in HTTP vhost routing when `routeByHTTPUser` is used for access control. The root cause is a discrepancy between credential sources: for routing, the username is extracted from the `Proxy-Authorization` header, while authentication checks only look at the standard `Authorization` header. An attacker could send a request with a known `routeByHTTPUser` value in the `Proxy-Authorization` header to be routed to a protected backend while bypassing authentication. The vulnerability was patched in version 0.68.1 .

### Post-Exploitation Chain Example

A complete attack chain from vhost enumeration to privilege escalation was demonstrated in the Whiterose CTF challenge:
1. Vhost enumeration revealed an `admin` subdomain
2. The admin portal was vulnerable to Insecure Direct Object Reference (IDOR)
3. IDOR exploitation led to Server-Side Template Injection (SSTI) in an EJS-powered page
4. SSTI provided Remote Code Execution (RCE)
5. A vulnerability in `sudoedit` (CVE-2023-22809) was exploited for privilege escalation to root 

## Filtering False Positives

When performing vhost enumeration, false positives are common. A default response page for non-existent hosts will have a consistent size (e.g., 4605 bytes). Filtering by response size using flags like `-fs` in ffuf or `--exclude-length` in gobuster removes these false positives and reveals valid vhosts . Additional filtering strategies include:
- Filter by status code (`-fc 400,404` in ffuf)
- Filter by response size (`--hh` in wfuzz)
- Filter by word count (`--hw` in wfuzz)

## Key Lessons from Real Engagements

1. **Effective wordlist selection** significantly impacts discovery success. Combine broad scans with targeted custom scans when initial output is incomplete .

2. **Persistence is critical**—when traditional enumeration methods fail, valuable information can be hidden in error pages or SSL certificates rather than main page content .

3. **Understand the difference** between subdomains (rely on DNS resolution) and vhosts (discovered via HTTP Host header testing) .

4. **Always verify results** by adding discovered hosts to `/etc/hosts` and testing responses with curl .

## Related Topics

* Subdomain Enumeration
* Crawl/Fuzz
* SSRF - VHosts can be internal SSRF targets
* TLS/SSL Certificate Analysis
* Web Application Firewall Testing
