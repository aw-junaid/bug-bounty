# Crawl / Fuzz – Extended Guide

## Crawlers

### dirhunt
```bash
dirhunt https://url.com/
```
- **Description**: Optimized crawler that checks for interesting directories and files.
- **Real example**: Used to discover `/admin/login.php` and `/backup/old.sql` on a forgotten subdomain.
- **Exploitation**: Found backup file contained database credentials → direct access to database.

### hakrawler
```bash
hakrawler -domain https://url.com/
```
- **Description**: Fast Go-based crawler for endpoints, JS files, and parameters.
- **Real example**: Discovered `/api/v1/users?debug=true` exposing user emails.
- **Exploitation**: Parameter tampering to list all users (`?debug=true&limit=1000`).

### sourcewolf.py
```bash
python3 sourcewolf.py -h
```
- **Description**: Extracts hidden endpoints from HTML/JS source code.

### gospider
```bash
gospider -s "https://example.com/" -o output -c 10 -d 1
gospider -S sites.txt -o output -c 10 -d 1
gospider -s "https://example.com/" -o output -c 10 -d 1 --other-source --include-subs
```
- **Flags**:
  - `-c 10` → concurrency
  - `-d 1` → depth
  - `--other-source` → use wayback, commoncrawl, etc.
  - `--include-subs` → include subdomains
- **Real example**: Found `https://staging.example.com/.git/config` exposing repo.
- **Exploitation**: Downloaded `.git` and extracted API keys.

---

## Fuzzers

### ffuf – Flexible Fast URL Fuzzer

#### Discover content
```bash
ffuf -recursion -mc all -ac -c -e .htm,.shtml,.php,.html,.js,.txt,.zip,.bak,.asp,.aspx,.xml -w six2dez/OneListForAll/onelistforall.txt -u https://url.com/FUZZ
```
- **Flags**:
  - `-recursion` → scan found directories again
  - `-mc all` → match all HTTP codes
  - `-ac` → auto-calibrate filters
  - `-e` → extensions
- **Real example**: Found `/backup.zip` on `https://redacted.com/backup.zip` with sensitive config.
- **Exploitation**: Extracted DB passwords → full compromise.

#### Headers discovery
```bash
ffuf -mc all -ac -u https://hackxor.net -w six2dez/OneListForAll/onelistforall.txt -c -H "FUZZ: Hellothereheadertesting123 asd"
```
- **Real example**: Discovered `X-Debug-Token: 12345` header on `/admin`.
- **Exploitation**: Used token to access Symfony profiler exposing env variables.

#### ffuf + Burp proxy
```bash
ffuf -replay-proxy http://127.0.0.1:8080
```
- **Use case**: Send successful fuzz results to Burp for manual testing.

#### Fuzzing extensions – categorized
**General**:
```
.htm,.shtml,.php,.html,.js,.txt,.zip,.bak,.asp,.aspx,.xml,.inc
```
**Backups**:
```
.bak,.bac,.old,.000,.~,.01,._bak,.001,.inc,.Xxx,.swp,.swo,.tmp,.backup
```
- **Real example**: Found `.env.bak` on `https://example.com/.env.bak` with AWS keys.
- **Exploitation**: Used keys to access S3 buckets.

---

### kiterunner (kr)
```bash
# Brute force with wordlist
kr brute https://whatever.com/ -w onelistforallmicro.txt -x 100 --fail-status-codes 404

# Scan with API route wordlist
kr scan https://whatever.com/ -w routes-small.kite -A=apiroutes-210228 -x 100 --ignore-length=34
```
- **Real example**: Discovered `/api/v2/internal/users/export` on a fintech app.
- **Exploitation**: Accessed endpoint without auth → full user data leak (GDPR breach).

---

### chameleon
```bash
./chameleon -u http://testphp.vulnweb.com -a -A
```
- **Description**: Detects CMS, frameworks, and generates wordlists.
- **Real example**: Detected WordPress 5.9 → used to fuzz plugins directory.

---

## Best Wordlists for Fuzzing

| Source | Recommended Lists |
|--------|-------------------|
| SecLists | `raft-large-directories-lowercase.txt`, `directory-list-2.3-medium.txt`, `RobotsDisallowed/top10000.txt` |
| assetnote/commonspeak2 | `wordswithext/*` |
| random-robbie/bruteforce-lists | All |
| Google fuzzing | Dictionaries for JSON, SQL, LDAP |
| six2dez/OneListForAll | All-in-one |
| foospidy/payloads | Everything |
| assetnote.io/wordlists | API routes, tech-specific |

**Pro tip**: Add `Host: localhost` header to bypass weak vhost filtering.

---

## Custom Generated Dictionary

### Extract all paths from URLs
```bash
gau example.com | unfurl -u paths
```
- **Real example**: GAU found `https://example.com/admin/ajax.php?action=debug` → path extracted.

### Get only file names
```bash
sed 's#/#\n#g' paths.txt | sort -u
```

### Extract URL parameters (keys)
```bash
gau example.com | unfurl -u keys
```
- **Real example**: Found `debug`, `test`, `backdoor` parameters.

### Filter successful responses
```bash
gau example.com | head -n 1000 | fff -s 200 -s 404
```

---

## Hardware Devices Admin Panel Hunter

```bash
# https://github.com/InfosecMatter/default-http-login-hunter
default-http-login-hunter.sh https://10.10.0.1:443/
```
- **Real example**: Found Cisco router admin panel at `https://10.10.0.1:443/` with default `cisco/cisco`.
- **Exploitation**: Changed DNS settings to redirect traffic.

---

## Dirsearch

```bash
dirsearch -r -f -u https://10.11.1.111 --extensions=htm,html,asp,aspx,txt -w six2dez/OneListForAll/onelistforall.txt --request-by-hostname -t 40
```
- **Flags**:
  - `-r` → recursive
  - `-f` → force extensions
  - `-t 40` → threads
- **Real example**: Found `/aspnet_client/system_web/` with machineKey exposed.

---

## Dirb

```bash
dirb http://10.11.1.111 -r -o dirb-10.11.1.111.txt
```
- **Real example**: Found `/phpmyadmin/` with default `root:root` credentials.

---

## Wfuzz

```bash
wfuzz -c -z file,six2dez/OneListForAll/onelistforall.txt --hc 404 http://10.11.1.11/FUZZ
```
- **Real example**: Found `/backup.old.tar.gz` with source code.
- **Exploitation**: Extracted hardcoded JWT secret.

---

## Gobuster

```bash
gobuster dir -u http://10.11.1.111 -w six2dez/OneListForAll/onelistforall.txt -s '200,204,301,302,307,403,500' -e
```
- **Flags**: `-e` → show full URL
- **Real example**: Found `/gitweb/` exposing repository.

---

## Cansina

```bash
# https://github.com/deibit/cansina
python3 cansina.py -u example.com -p PAYLOAD
```
- **Description**: Advanced content discovery with bypass techniques.

---

## Get Endpoints from JS Files

### LinkFinder
```bash
python linkfinder.py -i https://example.com -d
python linkfinder.py -i burpfile -b
```
- **Real example**: Extracted `/api/internal/keys` from `bundle.js`.
- **Exploitation**: Called endpoint → leaked encryption keys.

### JSFScan.sh
```bash
# https://github.com/KathanP19/JSFScan.sh
```
- **Description**: Automates JS enumeration, secret detection, and endpoint extraction.

---

## Bypass 429 Rate Limiting – Add Headers

If you get HTTP 429 Too Many Requests, try:

```bash
Client-Ip: IP
X-Client-Ip: IP
X-Forwarded-For: IP
X-Forwarded-For: 127.0.0.1
X-Real-IP: 127.0.0.1
X-Remote-IP: 127.0.0.1
X-Remote-Addr: 127.0.0.1
X-Originating-IP: 127.0.0.1
```
- **Real example**: Fuzzing a login endpoint → 429 bypassed using `X-Forwarded-For: 127.0.0.1`.

---

## Real-World Exploitation Chain Example

**Target**: `https://redacted.com`

1. **Crawl** with gospider → found `/js/app.js`
2. **LinkFinder** on app.js → discovered `/api/backup/download?file=`
3. **Fuzz** with ffuf → found `/api/backup/download?file=../../config/database.php`
4. **Response** returned DB credentials
5. **Access** DB → extracted user hashes
6. **Cracked** admin hash → full system compromise

---

## One-Liner Summary by Category

| Purpose | Command |
|---------|---------|
| Crawl JS endpoints | `gospider -s https://site.com --other-source` |
| Fuzz directories | `ffuf -w wordlist.txt -u https://site.com/FUZZ` |
| Fuzz extensions | `ffuf -e .php,.bak -w list.txt` |
| Extract JS links | `python linkfinder.py -i https://site.com` |
| Bypass 429 | `-H "X-Forwarded-For: 127.0.0.1"` |
| Custom wordlist | `gau site.com \| unfurl paths \| sort -u` |
| Scan API routes | `kr scan https://site.com -w routes.kite` |

---
