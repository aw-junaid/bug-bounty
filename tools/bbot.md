# BBOT Complete Guide: Passive and Active Recon, Module by Module


BBOT (Bighuge BLS OSINT Tool, v3.1.0) chains 100+ modules into one recursive scan: every module feeds its findings to the others, so one domain grows into subdomains, IPs, ports, web apps and leaks. This guide walks through every help section of the tool, every module family, and explains why each command is used.

## 1. What BBOT is and how it thinks

BBOT is a recursive reconnaissance framework from Black Lantern Security. You give it a target, and each module that finds something (a subdomain, an IP, an open port, a URL) emits it as an event that other modules pick up and expand. That is why a scan that starts with one domain can end with hundreds of assets.

Three ideas explain almost every option in the help screen you pasted:

- **Events, not tools.** Modules do not run in a fixed order. A module subscribes to event types (for example `DNS_NAME`, `IP_ADDRESS`, `OPEN_TCP_PORT`, `URL`) and fires when one appears.
- **Scope.** Targets define what BBOT is allowed to actively touch. Passive modules may query third parties about in-scope names. Active modules send traffic only to in-scope hosts.
- **Flags.** Every module carries flags such as `passive`, `active`, `safe`, `loud`, `slow` and `invasive`. Flags are how you choose how noisy a scan is without naming modules one by one.

### Passive vs active, in plain terms

|  | Passive | Active |
| --- | --- | --- |
| Who is contacted | Third-party data sources (certificate logs, DNS aggregators, search engines, archives) | The target's own servers, DNS and network |
| Visible to the target | No, the target never sees your traffic | Yes, it appears in logs, WAF, IDS |
| Typical findings | Subdomains, historical URLs, leaked emails, repos, ASN ranges | Live hosts, open ports, web technologies, misconfigurations, vulnerabilities |
| Why use it first | Zero risk, builds a target map cheaply | Confirms what is really alive and exposed |

The professional workflow is passive first (map the attack surface without touching it), then active on only what is confirmed in scope.

### Authorization comes first

Use BBOT only against assets you own or have written permission to test (a signed scope, a bug bounty program that allows it, or your own lab). Passive scans are low-risk, but active modules (port scanning, brute forcing, vulnerability checks) can trigger alerts, lock accounts or break fragile services. Scanning without permission can be illegal in many jurisdictions. Everything below assumes an authorized engagement or a lab you control.

## 2. Your first run: reading the output you pasted

Running `bbot` with no arguments printed the banner and help. Two lines matter:

```
[INFO] Creating BBOT config at /home/kali/.config/bbot/bbot.yml
[INFO] Creating BBOT secrets at /home/kali/.config/bbot/secrets.yml
```

- `bbot.yml` stores your default settings (threads, DNS options, module defaults). Edit it once and every scan inherits it.
- `secrets.yml` stores API keys for services such as Shodan, VirusTotal, SecurityTrails, Censys, Chaos and GitHub. Modules marked as needing a key stay silent until you add one, so adding keys is the biggest upgrade to passive coverage.

### Safe first commands

| Command | What it does | Why you run it |
| --- | --- | --- |
| `bbot --version` | Prints the installed version | Confirms the install and tells you which docs to match |
| `bbot -l` | Lists all modules with flags and the event types they consume and produce | Shows what exists and which are passive or active |
| `bbot -lf` | Lists flags | Lets you choose scan behavior by flag instead of module name |
| `bbot -lp` | Lists presets | Presets are ready-made scan recipes |
| `bbot -lo` | Lists output modules | Shows where results can be sent |
| `bbot -mh <module>` | Help for one module | Reads exactly what a module does and which options it has |
| `bbot -lmo` | Lists every module option | Finds settings you can tune with `-c` |
| `bbot -t example.com -p subdomain-enum --dry-run` | Builds the scan and aborts before running | Verifies targets, modules and config before sending any traffic |
| `bbot -t example.com -p subdomain-enum --current-preset` | Prints the resulting preset as YAML | Shows exactly what will run, and lets you save it as your own preset |

Use `--dry-run` and `-mh` habitually. They cost nothing and prevent surprises, especially before an active scan.

## 3. The Target section: defining scope

Scope is the most important concept in BBOT. Get it right and the scan stays inside your authorization.

| Option | Meaning | Why you use it |
| --- | --- | --- |
| `-t, --targets` | Domains, IPs, CIDR ranges, URLs, even company names or emails | Defines what is in scope and what active modules may touch. Example: `bbot -t example.com 203.0.113.0/24` |
| `-s, --seeds` | Starting points that feed passive modules but are not themselves in scope | Lets you start from a known name without authorizing active contact with it. Defaults to the same as targets |
| `-b, --blacklist` | Hosts and ranges BBOT must never touch | Protects fragile systems, third parties and out-of-scope assets. Example: `-b admin.example.com 10.0.0.5` |
| `--strict-scope` | Only exact matches count, subdomains are not automatically in scope | Stops a scan of `www.example.com` from spreading across the whole domain |

### How targets are used

- `bbot -t example.com` treats `example.com` and every subdomain as in scope.
- `bbot -t www.example.com --strict-scope` scans only that one hostname.
- Targets can be read from a file: `bbot -t targets.txt`, which keeps long engagements reproducible.
- An IP range such as `-t 203.0.113.0/24` makes every address in the range in scope, which is what you want for a client-owned network block.

### Why the blacklist matters

BBOT discovers new hosts recursively, so a CDN, a payment provider or a shared host can easily appear in results. Anything covered by your scope rules but off-limits in the contract (a legacy device, a production database host) must be blacklisted before you start. Treat `-b` as a safety rail, not an afterthought.

## 4. Presets and flags: choosing passive or active

Presets (`-p`) are saved scan recipes. Flags (`-f`, `-rf`, `-ef`) filter modules by behavior. Together they are how you control noise.

### Common presets

Run `bbot -lp` for the full list in your version. The ones you will use most:

| Preset | Purpose | Nature |
| --- | --- | --- |
| `subdomain-enum` | Finds subdomains from many sources plus DNS brute force | Mixed: passive sources and active brute force |
| `cloud-enum` | Finds cloud storage buckets and cloud assets tied to the target | Mixed |
| `code-enum` | Finds public repositories, code and secrets related to the target | Mostly passive |
| `email-enum` | Collects email addresses tied to the domain | Mostly passive |
| `spider` | Crawls a website following links | Active |
| `web-basic` / `web-thorough` | Light vs deep web checks (technologies, common misconfigurations) | Active |
| `web-screenshots` | Screenshots of discovered web pages | Active |
| `paramminer` | Hunts for hidden parameters, headers and cookies | Active, loud |
| `nuclei` | Runs template-based vulnerability checks | Active |
| `kitchen-sink` | Enables nearly everything | Very loud |

Use `bbot -lp` to confirm names in your install, since presets change between releases.

### Flags that control behavior

| Flag | Meaning | Why it matters |
| --- | --- | --- |
| `passive` | Module only queries third-party sources, never the target | Safe for early recon |
| `active` | Module sends traffic to the target | Needs authorization |
| `safe` | Unlikely to cause problems | Good default for production-adjacent scans |
| `loud` | Generates lots of requests or obvious traffic | Easily noticed, may trigger WAF or rate limits |
| `slow` | Takes a long time | Plan runtime |
| `invasive` | Could affect the target (brute force, fuzzing, exploit-style checks) | Only with explicit approval |
| `subdomain-enum`, `cloud-enum`, `code-enum`, `email-enum`, `web`, `portscan`, `baddns` and others | Topic groupings | Select a whole capability at once |

### The three flag options

- `-f` **enables** modules with a flag. `bbot -t example.com -f subdomain-enum` turns on every subdomain module.
- `-rf` **requires** a flag, so only modules that carry it run. `bbot -t example.com -p subdomain-enum -rf passive` keeps the preset but strips out anything that touches the target. This is the key passive-only command.
- `-ef` **excludes** a flag. `bbot -t example.com -p kitchen-sink -ef loud invasive` removes the noisy and risky modules from a broad scan.

### Why these three matter

The same preset behaves very differently with flag filters. A scan of `-p subdomain-enum -rf passive` never contacts the target. Dropping `-rf passive` adds DNS brute forcing and resolves every candidate, which is active. Adding `-ef loud` or `-ef invasive` lets you keep a wide scan inside a safe boundary.

### Overriding settings with -c

`-c` sets individual options in `key=value` form without editing files:

```
bbot -t example.com -p spider -c web.spider_distance=2 web.spider_depth=2
bbot -t example.com -m shodan_dns -c modules.shodan_dns.api_key=YOUR_KEY
```

The first limits how far a crawler wanders, which controls scan size and noise. The second supplies a key for one run. For permanent keys, use `secrets.yml`. Run `bbot -lmo` to see every option.

## 5. The Modules section: every family explained

The `-m` list in your help output holds 100+ modules. Rather than memorize them, learn the families. Descriptions below are from general knowledge of BBOT; confirm any module's exact behavior and options with `bbot -mh <module>`, since they change between versions. Modules marked **(key)** usually need an API key in `secrets.yml`.

### 5.1 Passive subdomain and DNS-intel sources

These query third-party datasets and never contact the target. Run them first.

| Module(s) | Data source or idea | Why it helps |
| --- | --- | --- |
| `crt`, `crt_db`, `certspotter` | Certificate Transparency logs | Every public TLS certificate names hostnames, which exposes subdomains even internal-looking ones |
| `anubisdb`, `hackertarget`, `rapiddns`, `subdomaincenter`, `subdomainradar`, `dnsdumpster`, `myssl`, `bufferoverrun`, `otx` | Public DNS aggregators and threat-intel feeds | Different datasets, so combined coverage is much wider than any one |
| `virustotal`, `securitytrails`, `shodan_dns`, `censys_dns`, `chaos`, `fullhunt`, `bevigil`, `c99`, `builtwith` **(key)** | Commercial and community databases | Deep passive DNS history and related domains |
| `wayback`, `urlscan`, `leakix`, `trickest` | Web archives, scan databases and curated datasets | Historical URLs, forgotten endpoints, leaked or exposed services |
| `viewdns`, `affiliates` | Reverse WHOIS and related-domain correlation | Finds sibling domains owned by the same organization |
| `asn` | BGP and network ownership data | Turns an IP into the company's whole netblock |
| `ip2location`, `ipstack` **(key)**, `shodan_idb` | IP geolocation and Shodan InternetDB (free, no key) | Passive view of open ports and known vulnerabilities per IP |

**Why use them:** you build a map of the organization without sending one packet to it. Command: `bbot -t example.com -p subdomain-enum -rf passive`.

### 5.2 Active DNS modules

| Module | What it does | Why / risk |
| --- | --- | --- |
| `dnsbrute` | Guesses subdomains from a wordlist and resolves them | Finds names no public dataset knows. Many DNS queries, so it is active and slower |
| `dnsbrute_mutations` | Mutates found names (`api` becomes `api-dev`, `api2`) | Discovers naming-pattern siblings |
| `dnscommonsrv` | Checks common SRV records (for example mail, SIP, LDAP) | Reveals services advertised in DNS |
| `dnscaa`, `dnstlsrpt`, `dnsbimi` | Reads CAA, TLS-RPT and BIMI DNS records | Shows certificate authorities, mail reporting addresses and mail-policy infrastructure |
| `ipneighbor` | Checks IPs adjacent to discovered ones | Finds related hosts in the same block |
| `baddns`, `baddns_direct`, `baddns_zone` | Tests for dangling DNS, takeover-prone CNAMEs and zone issues | Subdomain takeover is a common high-impact finding |

### 5.3 Network and service discovery

| Module | What it does | Why |
| --- | --- | --- |
| `portscan` | Fast port scanning of in-scope IPs (masscan-based) | Finds open TCP ports. By default only common web ports are kept on CDN or WAF IPs; override with `--allow-cdn-ports` |
| `fingerprintx` | Identifies the service running on an open port | Distinguishes SSH, SMB, databases, etc. from the port number alone |
| `sslcert` | Reads TLS certificates from open ports | Certificate names reveal new hostnames |
| `azure_tenant` | Finds Microsoft tenant info for a domain | Maps Microsoft 365 / Azure footprint |
| `ntlm` | Reads NTLM challenge info from web services | Leaks internal hostnames and domain names |

### 5.4 Web discovery and fingerprinting (active)

| Module | What it does |
| --- | --- |
| `http` | Visits discovered web hosts and records status, title, headers and technologies |
| `gowitness` | Screenshots pages so you can triage visually |
| `wafw00f` | Detects web application firewalls |
| `robots`, `securitytxt`, `favicon` | Reads robots.txt, security.txt and favicon hashes, which expose hidden paths, contacts and shared-product clues |
| `oauth` | Looks at OAuth and OpenID configurations |
| `social` | Finds links to social profiles and similar accounts |
| `newsletters`, `filedownload`, `kreuzberg` | Finds newsletter signups, downloads documents and extracts their text, which can leak metadata and secrets |
| `js_unpacker`, `retirejs` | Unpacks JavaScript and flags known-vulnerable libraries |

**Why use them:** after passive recon tells you what might exist, these tell you what is alive and what it runs.

### 5.5 Web content discovery and fuzzing (active, loud)

| Module | Purpose |
| --- | --- |
| `webbrute`, `webbrute_shortnames`, `iis_shortnames` | Guess directories and files, including IIS 8.3 short names |
| `paramminer_getparams`, `paramminer_headers`, `paramminer_cookies` | Look for hidden GET parameters, headers and cookies |
| `reflected_parameters`, `hunt` | Highlight parameters that reflect input or have risky names |
| `lightfuzz` | Lightweight fuzzing for common web issues |
| `virtualhost` | Finds virtual hosts on one IP by varying the Host header |
| `host_header`, `url_manipulation`, `generic_ssrf`, `bypass403` | Test host-header handling, URL parsing quirks, SSRF-style behaviour and access-control weaknesses |
| `graphql_introspection` | Checks whether a GraphQL endpoint exposes its schema |

These generate many requests. They belong on in-scope hosts with approval, and often with `-ef loud` removed for gentler runs.

### 5.6 Vulnerability and technology checks (active)

| Module | Purpose |
| --- | --- |
| `nuclei` | Runs community template checks for known issues and exposures |
| `badsecrets` | Detects known or default framework secrets (for example ASP.NET machine keys) |
| `telerik`, `ajaxpro`, `dotnetnuke`, `aspnet_bin_exposure` | Targeted checks for specific Microsoft-stack components and exposures |
| `trajan` | Looks for CI/CD pipeline misconfiguration |
| `waf_bypass`, `nowafpls` | Help assess whether a WAF can be sidestepped, for testing the protection itself |
| `legba`, `medusa` | Credential testing against services. Invasive and may lock accounts, so only with explicit permission |

### 5.7 Cloud storage and tenant discovery

| Module | Purpose |
| --- | --- |
| `bucket_amazon`, `bucket_google`, `bucket_microsoft`, `bucket_digitalocean`, `bucket_firebase`, `bucket_hetzner` | Look for storage buckets named after the target and check whether they are open |
| `bucket_file_enum` | Lists files inside open buckets |

**Why:** misconfigured buckets are a frequent source of data exposure. Run through `-p cloud-enum`.

### 5.8 Code, repository and secret discovery

| Module | Purpose |
| --- | --- |
| `github_org`, `github_usersearch`, `github_codesearch`, `github_workflows`, `gitlab_com`, `gitlab_onprem`, `code_repository` | Find public repos, users, code and CI workflows related to the target |
| `git`, `git_clone`, `gitdumper` | Detect exposed `.git` folders and retrieve their history |
| `trufflehog` | Scans code and artifacts for leaked secrets |
| `postman`, `postman_download` | Finds public Postman workspaces that often hold API keys and endpoints |
| `dockerhub`, `docker_pull` | Finds and pulls container images for review |
| `apkpure`, `google_playstore`, `jadx` | Finds mobile apps and decompiles them to read hardcoded endpoints and keys |

**Why:** developers leak secrets in public repositories more often than attackers find them through servers. Command: `bbot -t example.com -p code-enum`.

### 5.9 Email and people discovery

| Module | Purpose |
| --- | --- |
| `emailformat`, `hunterio` **(key)**, `skymem`, `pgp` | Find addresses and mail patterns for the domain |
| `dehashed` **(key)**, `credshed`, `otilabs` | Look up breach exposure. Handle results as sensitive data |

**Why:** email lists support phishing-awareness testing and breach-exposure checks. Command: `bbot -t example.com -p email-enum`.

## 6. Scan, dependency and misc options explained

### Scan section

| Option | What it does | Why you use it |
| --- | --- | --- |
| `-n, --name` | Names the scan | Output goes into a folder with that name, so engagements stay organized and a scan can be resumed or compared |
| `-v, --verbose` | More console output | See what is happening during long scans |
| `-d, --debug` | Full debug logging | Troubleshoot a module that is failing |
| `-S, --silent` | Minimal output | Cleaner logs in scripts and pipelines |
| `--force` | Runs despite failed module setups or warnings | Use sparingly, since failures often signal a real problem such as a missing dependency or key |
| `-y, --yes` | Skips confirmation prompts | Needed in automation. Also trusts custom module folders, so only use with code you reviewed |
| `--fast-mode` | Scans only the targets given, with no extra discovery | Quick check of a known host without recursion |
| `--dry-run` | Builds the scan and stops before running | Validates configuration safely |
| `--current-preset`, `--current-preset-full` | Prints the effective preset as YAML | Reproducible scans: save the output as your own preset file |
| `-mh, --module-help` | Help for one module | Read before enabling anything unfamiliar |

### Module dependency options

Many modules rely on external tools (masscan, nuclei, jadx and others). BBOT installs them on first use through Ansible.

| Option | Use |
| --- | --- |
| `--no-deps` | Do not install anything. Use on locked-down or offline machines |
| `--force-deps` | Reinstall dependencies. Fixes a corrupted install |
| `--retry-deps` | Try again after a failed install |
| `--ignore-failed-deps` | Run the modules that did install |
| `--install-all-deps` | Install every dependency ahead of time. Run once on a fresh Kali box before going offline or entering a client network |

### Misc options

| Option | Why |
| --- | --- |
| `--proxy`, `--no-proxy` | Send HTTP traffic through Burp or another proxy for inspection, and exclude internal hosts from it |
| `--custom-nameservers` | Use specific DNS resolvers such as `1.1.1.1`. Improves reliability and avoids local resolver issues |
| `-H, --custom-headers` | Add headers such as a bug-bounty tracking header so your traffic is identifiable to the program |
| `-C, --custom-cookies` | Scan as an authenticated session |
| `-ua`, `-uas` | Set or extend the user agent. A suffix with your contact info or ticket number is good practice for authorized tests |
| `--custom-yara-rules` | Add your own YARA rules to the content-extraction engine (`excavate`), so BBOT can flag custom patterns |
| `--reset-config`, `--reset-secrets` | Regenerate config files (the old file is backed up). Warning: reset-secrets overwrites your keys file with defaults, so keep the backup |

### Output options

| Option | Why |
| --- | --- |
| `-o, --output-dir` | Where results are written. Use a per-engagement folder |
| `-om` and `-eom` | Add or exclude output modules (next section) |
| `--json, -j` | Machine-readable JSON for scripts and SIEMs |
| `--brief, -br` | Only the data itself, ideal for piping into other tools |
| `--no-color` | Clean text for log files |
| `--event-types` | Show only chosen event types, for example `DNS_NAME` or `URL` |
| `--allow-cdn-ports` | Keep open ports on CDN and WAF IPs instead of only 80 and 443. Use when the CDN IP genuinely belongs to the target |

## 7. Output modules and reading results

BBOT always writes to the console and, by default, to a scan folder (`~/.bbot/scans/<name>` or the folder you set with `-o`). Output modules decide what else you get.

| Output module | What you get | Best for |
| --- | --- | --- |
| `txt` | Human-readable log | Quick reading and reports |
| `json` | Every event as JSON | Scripts, `jq`, SIEM import |
| `csv` | Spreadsheet-ready rows | Triage in Excel or Sheets |
| `subdomains` | Clean list of discovered subdomains | Feeding other tools |
| `emails` | List of found email addresses | Phishing-awareness scoping |
| `asset_inventory` | Consolidated table of hosts, IPs, ports and technologies | The best single deliverable for a client |
| `web_report` | HTML report of web findings | Stakeholder-friendly summary |
| `web_parameters` | Parameters discovered on web pages | Feeding web testing tools |
| `nmap_xml` | Nmap-format XML | Import into Nmap-aware tooling |
| `sqlite`, `postgres`, `mysql`, `mongo`, `elastic`, `splunk`, `neo4j` | Database and SIEM storage | Long engagements and graph analysis (neo4j shows relationships) |
| `slack`, `discord`, `teams`, `webhook`, `websocket`, `kafka`, `nats`, `rabbitmq`, `zeromq` | Live notifications and streams | Continuous monitoring and alerting |
| `stdout` | Console output control | Pipelines |

Example: `bbot -t example.com -p subdomain-enum -om asset_inventory csv json -o ./client-a -n recon1` writes an inventory, a CSV and JSON into `./client-a/recon1`.

### Reading events

Console lines look like `[DNS_NAME] api.example.com  (crt)`. The first token is the **event type**, then the value, then the **module that found it**. Common types:

- `DNS_NAME`: a hostname
- `IP_ADDRESS`: a resolved address
- `OPEN_TCP_PORT`: an open port
- `URL`: a live web page
- `TECHNOLOGY`: software detected on a host
- `FINDING`: something noteworthy (an exposure, a takeover candidate)
- `VULNERABILITY`: a confirmed issue with a severity
- `STORAGE_BUCKET`, `EMAIL_ADDRESS`, `CODE_REPOSITORY`: cloud, email and code discoveries

Filter the console with `--event-types`. Example: `bbot -t example.com -p subdomain-enum --event-types DNS_NAME`.

## 8. Real workflows: passive first, then active

Use `example.com` as a placeholder for a domain you are authorized to test.

### Stage 1: Passive reconnaissance (target never contacted)

```
bbot -t example.com -p subdomain-enum -rf passive -n passive1 -o ./engagement
```

- `-p subdomain-enum` selects all subdomain-finding modules.
- `-rf passive` keeps only passive ones, so DNS brute force and web probing are excluded.
- `-n` and `-o` keep results organized.

**Why:** this gives a full subdomain and IP map from public data. Nothing here appears in the target's logs.

### Stage 2: Passive code, cloud and email intelligence

```
bbot -t example.com -p code-enum cloud-enum email-enum -rf passive -n osint1 -o ./engagement
```

**Why:** finds public repos, exposed bucket names and mail patterns. Add API keys in `secrets.yml` first to widen coverage. Review `cloud-enum` carefully: some bucket modules probe cloud provider endpoints, so keep `-rf passive` when you want strictly third-party queries.

### Stage 3: Active discovery on confirmed in-scope hosts

```
bbot -t example.com -p subdomain-enum -m portscan fingerprintx http gowitness -n active1 -o ./engagement
```

- `subdomain-enum` without `-rf passive` adds DNS brute force.
- `portscan` finds open ports, `fingerprintx` names the services, `http` probes web servers, `gowitness` screenshots them.

**Why:** converts the candidate list into a verified, live attack surface you can triage visually. Expect more traffic and possible alerts.

### Stage 4: Web analysis of one application

```
bbot -t https://app.example.com --strict-scope -p web-basic -ef loud invasive
bbot -t www.example.com -p spider -c web.spider_distance=2 web.spider_depth=2
```

- `--strict-scope` stops the scan at that host.
- `-ef loud invasive` removes noisy and risky modules.
- The spider preset crawls links, and the `-c` limits keep it from running away.

**Why:** a focused, controlled look at one app is safer than a domain-wide web scan.

### Stage 5: Vulnerability and takeover checks

```
bbot -t example.com -p baddns-intense nuclei -n vuln1 -o ./engagement
```

**Why:** checks for dangling subdomains and known exposures once you know what is in scope. Run with approval, since template scans send many requests.

### Stage 6: Everything (labs and explicit full-scope tests only)

```
bbot -t example.com -p kitchen-sink
```

**Why:** maximum coverage, maximum noise. Use only against your own lab or with explicit permission, and prefer `-ef loud invasive` on anything production.

### Quick-reference: passive vs active command cheat sheet

| Goal | Command | Type |
| --- | --- | --- |
| Subdomains, no contact | `bbot -t example.com -p subdomain-enum -rf passive` | Passive |
| Subdomains plus brute force | `bbot -t example.com -p subdomain-enum` | Passive + active |
| One host, nothing else | `bbot -t host.example.com --strict-scope --fast-mode` | Active |
| Test config only | `bbot -t example.com -p subdomain-enum --dry-run` | None |
| Save a custom recipe | `bbot -t example.com -p subdomain-enum -ef loud --current-preset > my.yml` | None |
| Safe web scan | `bbot -t example.com -p web-basic -ef loud invasive` | Active, limited |
| Add screenshots | `bbot -t example.com -p subdomain-enum -m portscan gowitness` | Active |

## 9. Tips, troubleshooting and defensive use

### Practical tips

- **Add API keys first.** Edit `~/.config/bbot/secrets.yml` and add keys for Shodan, VirusTotal, SecurityTrails, Censys, Chaos, GitHub and others. Free tiers help a lot, and passive coverage grows noticeably.
- **Save presets.** Run `--current-preset` and keep the YAML in your engagement repo. Repeatable scans make results comparable between runs.
- **Re-scan to detect change.** Run the same preset monthly and diff the `asset_inventory` or `subdomains` output to catch new exposure.
- **Control speed.** Heavy scans can hit rate limits. Lower module concurrency and web request settings through `-c` (check names with `bbot -lmo`) when targets or networks are fragile.
- **Use a stable resolver.** `--custom-nameservers 1.1.1.1 8.8.8.8` avoids flaky local DNS.
- **Identify yourself.** For authorized tests, set a user-agent suffix or a custom header with your name or ticket number so defenders can recognize your traffic.
- **Keep results private.** Output may contain emails, breach data and exposed secrets. Store and share it like sensitive client data.

### Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Module skipped at start | Missing API key or failed dependency | Add the key, or run `--retry-deps` / `--install-all-deps` |
| Setup fails with a dependency error | Package manager or network issue | `--force-deps`, check internet, run as a user with install rights |
| Few results | Passive sources are limited without keys | Add keys, drop `-rf passive` only if authorized |
| Too many results or scan won't finish | Scope too wide or recursion unbounded | Use `--strict-scope`, blacklist large providers, limit spider depth |
| Ports missing on a CDN-hosted site | Default CDN port filtering | `--allow-cdn-ports` if the IP belongs to the target |
| Confusing behavior after an upgrade | Config drift | `--reset-config` (backs up the old file) |

### Defensive use: scan yourself

BBOT is as useful to defenders as to testers. Point it at your own domains with `-p subdomain-enum -rf passive` to see what the internet already knows, add `baddns` to catch takeover risks, `cloud-enum` for exposed buckets and `code-enum` for leaked secrets. The goal is to find and fix exposure before someone else does.

### Learning path

1. Build a lab (a VM with a deliberately vulnerable app, or a domain you own).
2. Run `bbot -l`, `-lf` and `-lp` and read `-mh` for five modules.
3. Do a passive-only scan, then an active one, and compare output.
4. Add API keys and repeat, noting how much new data appears.
5. Save a preset and automate a monthly self-scan.

## 10. Part 2: The command playbook, start here

Sections 1 to 9 explained what BBOT is and what each option means. Sections 10 to 17 turn that into an ordered playbook: every phase, every preset, every flag combination, and where the results are stored. Run the commands in order. Replace `example.com` with a domain you own or are authorized to test.

### 10.1 Set up the output structure first

Decide where results live before the first scan. Without a plan, scans scatter into `~/.bbot/scans/` with random names (`-n` is optional, BBOT invents a name otherwise) and are hard to compare later.

```
mkdir -p ~/recon/example.com/{phase1-passive,phase2-active,phase3-web,phase4-vuln,reports}
cd ~/recon/example.com
```

| Folder | Holds | Why separate |
| --- | --- | --- |
| `phase1-passive` | Scans that never contact the target | Proves to a client that early recon was non-intrusive |
| `phase2-active` | DNS brute force, port scans, service fingerprints | Active traffic is logged on the target side, so you keep your own record with timestamps |
| `phase3-web` | Web probing, screenshots, spidering | Large output (screenshots), kept apart from lightweight data |
| `phase4-vuln` | Takeover and vulnerability checks | Sensitive findings you may need to restrict |
| `reports` | Exports you hand to others | Keeps raw data and deliverables separate |

### 10.2 The three flags that control storage

| Flag | What it does | Example |
| --- | --- | --- |
| `-o, --output-dir DIR` | The parent folder for scan results | `-o ~/recon/example.com/phase1-passive` |
| `-n, --name NAME` | The scan's own subfolder inside that directory | `-n passive-subs-2026-11-24` |
| `-om, --output-modules` | Extra output formats written alongside the defaults | `-om csv json subdomains asset_inventory` |

Result: `-o ~/recon/example.com/phase1-passive -n passive-subs-2026-11-24` writes into `~/recon/example.com/phase1-passive/passive-subs-2026-11-24/`. Inside that folder BBOT typically writes `output.txt`, `output.json`, `output.csv`, `scan.log` and the preset it used, plus one file per extra output module (for example `subdomains.txt`, `emails.txt`, `asset-inventory.csv`). Run `ls` on a finished scan to confirm names in your version.

### 10.3 A naming convention that scales

Use `<phase>-<what>-<date>` for every scan name, for example `passive-subs-2026-11-24` or `active-ports-2026-11-24`. The date makes diffing over time trivial, and the phase tells a reader what traffic was generated.

### 10.4 Save the preset with every scan

Before a real run, dump what will execute and keep it with the results:

```
bbot -t example.com -p subdomain-enum -rf passive --current-preset > phase1-passive/preset-passive-subs.yml
```

This records exactly which modules and settings were used, so the scan can be repeated or audited later. Run `--current-preset-full` instead when you want every default spelled out.

### 10.5 Always dry-run first

Add `--dry-run` to any command below to build the scan and stop before sending traffic. Remove it to run for real. It is the cheapest way to catch a wrong target, a missing key or an unintended module.

## 11. Phase 1: Passive commands, one by one

Passive means the target never receives a packet from you. BBOT asks third parties (certificate logs, DNS aggregators, archives, code hosts) about it. The common safety switch is `-rf passive`, which means "only run modules flagged passive." Every command below sends output to `~/recon/example.com/phase1-passive/`.

### 11.1 Passive subdomain enumeration (the starting point)

```
bbot -t example.com -p subdomain-enum -rf passive \
  -o ~/recon/example.com/phase1-passive -n passive-subs-2026-11-24 \
  -om subdomains csv json
```

| Part | Meaning | Why |
| --- | --- | --- |
| `-t example.com` | Target domain | Defines scope and what passive sources are queried about |
| `-p subdomain-enum` | Preset with every subdomain module | One flag instead of naming 20+ modules |
| `-rf passive` | Only passive modules | Removes DNS brute force so nothing touches the target |
| `-o` and `-n` | Output folder and scan name | Keeps results organized |
| `-om subdomains csv json` | Extra outputs | `subdomains.txt` for tools, CSV for spreadsheets, JSON for scripts |

Stored in: `phase1-passive/passive-subs-2026-11-24/subdomains.txt`, `output.csv`, `output.json`.

### 11.2 Passive email discovery

```
bbot -t example.com -p email-enum -rf passive \
  -o ~/recon/example.com/phase1-passive -n passive-emails-2026-11-24 \
  -om emails csv
```

Why: finds address formats and exposed addresses for awareness testing. `-om emails` writes `emails.txt`. Treat the file as sensitive personal data.

### 11.3 Passive code and repository discovery

```
bbot -t example.com -p code-enum -rf passive \
  -o ~/recon/example.com/phase1-passive -n passive-code-2026-11-24 \
  -om json txt
```

Why: surfaces public repositories, code-search hits and workflows tied to the organization. Add GitHub tokens in `secrets.yml` first or results are rate limited. Search `output.json` for `CODE_REPOSITORY` events.

### 11.4 Passive cloud discovery

```
bbot -t example.com -p cloud-enum -rf passive \
  -o ~/recon/example.com/phase1-passive -n passive-cloud-2026-11-24 \
  -om json csv
```

Why: lists cloud assets and storage names linked to the domain. Note that some bucket modules probe provider endpoints, so `-rf passive` keeps the scan to strictly passive ones and may return fewer buckets. Bucket probing belongs in Phase 2.

### 11.5 Passive-only, several presets in one scan

```
bbot -t example.com -p subdomain-enum code-enum email-enum cloud-enum -rf passive \
  -o ~/recon/example.com/phase1-passive -n passive-all-2026-11-24 \
  -om subdomains emails asset_inventory json csv
```

Why: `-p` accepts several presets, and BBOT merges them. One scan, one folder, one `asset_inventory` that combines everything. This is the single best Phase 1 command once you trust the pieces.

### 11.6 Several targets from a file

```
printf "example.com\nexample.org\n203.0.113.0/24\n" > targets.txt
bbot -t targets.txt -p subdomain-enum -rf passive \
  -o ~/recon/example.com/phase1-passive -n passive-multi-2026-11-24
```

Why: reproducible scope. Keep `targets.txt` in the engagement folder as proof of what was in scope.

### 11.7 Passive scan with seeds that are not targets

```
bbot -t example.com -s partner-known.example.net -p subdomain-enum -rf passive \
  -n passive-seeded-2026-11-24 -o ~/recon/example.com/phase1-passive
```

Why: `-s` feeds a name to passive modules without making it in scope for later active work. Useful when a client mentions a related domain you do not yet have permission to touch.

### 11.8 Blacklist while passive

```
bbot -t example.com -p subdomain-enum -rf passive -b dev-old.example.com 198.51.100.7 \
  -n passive-bl-2026-11-24 -o ~/recon/example.com/phase1-passive
```

Why: even passive scans create events for found hosts. The blacklist keeps later active phases from ever touching assets the client excluded, and the habit starts here.

### 11.9 Exact host only (no spreading across the domain)

```
bbot -t www.example.com --strict-scope -p subdomain-enum -rf passive \
  -n passive-strict-2026-11-24 -o ~/recon/example.com/phase1-passive
```

Why: `--strict-scope` means subdomains are not automatically in scope, so the scan stays on that one name.

### 11.10 Passive scan with API keys supplied for this run only

```
bbot -t example.com -p subdomain-enum -rf passive \
  -c modules.shodan_dns.api_key=YOUR_KEY modules.virustotal.api_key=YOUR_KEY \
  -n passive-keys-2026-11-24 -o ~/recon/example.com/phase1-passive
```

Why: tests a key before saving it. Keys passed with `-c` appear in your shell history, so prefer `secrets.yml` for anything permanent.

### 11.11 Quiet and scriptable passive scan

```
bbot -t example.com -p subdomain-enum -rf passive -S -y --no-color \
  -n passive-quiet-2026-11-24 -o ~/recon/example.com/phase1-passive
```

Why: `-S` is silent, `-y` skips prompts, `--no-color` keeps log files clean. Use this in cron jobs and CI.

### 11.12 Pipe only the data to another tool

```
bbot -t example.com -p subdomain-enum -rf passive --brief --event-types DNS_NAME -y | sort -u > phase1-passive/subs-clean.txt
```

Why: `--brief` prints only the value, `--event-types DNS_NAME` limits it to hostnames, and the pipeline gives a de-duplicated list you can hand to any other tool. This output is stored in the file you redirect to.

### 11.13 JSON on the console for live processing

```
bbot -t example.com -p subdomain-enum -rf passive --json -y | tee phase1-passive/live.ndjson | jq -r 'select(.type=="DNS_NAME") | .data'
```

Why: `--json` prints one JSON event per line. `tee` saves the raw stream while `jq` shows hostnames live. Useful for dashboards and SIEM feeds.

### Phase 1 checklist

- [ ] Keys added to `secrets.yml`
- [ ] `subdomains.txt` reviewed and de-duplicated
- [ ] Out-of-scope names removed or blacklisted
- [ ] Presets saved with `--current-preset`
- [ ] Findings noted before moving to Phase 2

## 12. Phase 2: Active commands, one by one

Active commands send traffic to the target: DNS queries to its servers, TCP connections to its ports, HTTP requests to its sites. Run them only on written-authorized scope, ideally inside a window agreed with the client, and keep the blacklist (`-b`) current. Each command writes to `~/recon/example.com/phase2-active/` unless noted.

### 12.1 Full subdomain enumeration (passive sources plus DNS brute force)

```
bbot -t example.com -p subdomain-enum \
  -o ~/recon/example.com/phase2-active -n active-subs-2026-11-24 \
  -om subdomains csv json
```

Why: without `-rf passive`, BBOT also runs `dnsbrute` (and mutation modules) against the target's DNS. It finds names that no public database knows. Expect many DNS queries.

### 12.2 Subdomains plus port scan plus service identification

```
bbot -t example.com -p subdomain-enum -m portscan fingerprintx sslcert \
  -o ~/recon/example.com/phase2-active -n active-ports-2026-11-24 \
  -om asset_inventory nmap_xml csv json
```

| Part | Meaning | Why |
| --- | --- | --- |
| `-m portscan` | Port scanner | Finds open TCP ports on discovered IPs |
| `-m fingerprintx` | Service identification | Names the service behind each port |
| `-m sslcert` | Reads TLS certificates | Reveals extra hostnames that feed back into the scan |
| `-om asset_inventory nmap_xml` | Inventory table and Nmap XML | One deliverable for the client, one for import into other tooling |

### 12.3 Same scan, but also keep ports on CDN and WAF IPs

```
bbot -t example.com -p subdomain-enum -m portscan fingerprintx --allow-cdn-ports \
  -n active-cdnports-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: by default only 80 and 443 are kept on CDN-fronted IPs, since other ports usually belong to the provider. Use this only when you know the IP is the client's own.

### 12.4 Network range scan (a netblock the client owns)

```
bbot -t 203.0.113.0/24 -m portscan fingerprintx sslcert -ef loud \
  -n active-range-2026-11-24 -o ~/recon/example.com/phase2-active -om nmap_xml csv
```

Why: gives a view of every IP in the range, not just names. `-ef loud` removes modules flagged loud, which keeps traffic lower on shared or fragile networks.

### 12.5 DNS-only active checks

```
bbot -t example.com -m dnscommonsrv dnscaa dnstlsrpt dnsbimi \
  -n active-dnsrecords-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: reads SRV, CAA, TLS-RPT and BIMI records. Low traffic and high value: shows certificate authorities allowed to issue for the domain, mail reporting addresses and advertised services.

### 12.6 Brute force with mutations

```
bbot -t example.com -m dnsbrute dnsbrute_mutations \
  -n active-dnsbrute-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: `dnsbrute` tries wordlist names, while `dnsbrute_mutations` combines found names with patterns (for example adding `-dev`, `-staging`, numbers). It finds assets built by the same naming habits. Run it after Phase 1 so mutations start from known names.

### 12.7 Subdomain takeover checks

```
bbot -t example.com -p subdomain-enum -m baddns baddns_direct baddns_zone \
  -n active-takeover-2026-11-24 -o ~/recon/example.com/phase2-active -om json
```

Why: checks CNAME and DNS records that point to services nobody owns anymore. Findings appear as `FINDING` events. Preset alternative: `-p baddns-intense` (section 13).

### 12.8 Cloud bucket checks

```
bbot -t example.com -p cloud-enum \
  -n active-cloud-2026-11-24 -o ~/recon/example.com/phase2-active -om json csv
```

Why: without `-rf passive`, the cloud modules also test whether storage buckets with names derived from the target exist and are open. Results appear as `STORAGE_BUCKET` events. Add `-m bucket_file_enum` to list the files in any bucket that turns out to be open.

### 12.9 Active exposed-repository check

```
bbot -t example.com -p subdomain-enum -m http git git_clone gitdumper \
  -n active-gitexposure-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: finds web servers that expose `.git` folders and retrieves what is exposed so it can be examined offline. Handle any retrieved code as sensitive client data.

### 12.10 Light active, strictly one host

```
bbot -t host.example.com --strict-scope --fast-mode -m portscan fingerprintx http \
  -n active-single-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: `--fast-mode` scans only what you gave it, no extra discovery. Good for retesting one host after a fix.

### 12.11 Safe active only

```
bbot -t example.com -p subdomain-enum -f safe -ef loud invasive slow \
  -n active-safe-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: `-f safe` selects modules unlikely to cause issues, while `-ef` removes anything noisy, risky or slow. This is the profile to use when the client says "be careful with production."

### 12.12 Proxy the traffic for evidence

```
bbot -t example.com -p subdomain-enum -m http --proxy http://127.0.0.1:8080 --no-proxy localhost \
  -n active-proxied-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: sends BBOT's HTTP traffic through Burp or another intercepting proxy so you can see and log every request. This also gives you a record to show the client exactly what was sent.

### 12.13 Identify your traffic

```
bbot -t example.com -p subdomain-enum -m http \
  -uas "pentest-ticket-4821 contact secops@yourfirm.example" -H "X-Test-Id=4821" \
  -n active-tagged-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: `-uas` appends text to the user agent and `-H` adds a header, so the client's defenders can tell your authorized traffic from a real attack and stop an incident response by mistake. Use the identifiers agreed in the rules of engagement.

### 12.14 Custom resolvers for reliability

```
bbot -t example.com -p subdomain-enum --custom-nameservers 1.1.1.1 8.8.8.8 \
  -n active-resolvers-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: DNS brute force is only as reliable as the resolver. Public resolvers avoid the flaky or rate-limited DNS server of a hotel or home router.

### Phase 2 checklist

- [ ] Written authorization and testing window confirmed
- [ ] Blacklist (`-b`) contains every excluded host
- [ ] Traffic identified (user-agent suffix or header)
- [ ] `asset_inventory` produced and reviewed
- [ ] Open ports and services recorded for Phase 3

## 13. Every preset paired with its commands

Presets are YAML recipes that turn on a group of modules and settings. First list what your install has, because names and contents change between releases:

```
bbot -lp
```

Then inspect any preset before running it:

```
bbot -t example.com -p <preset> --current-preset
bbot -t example.com -p <preset> --dry-run
```

The table lists the presets documented for BBOT 2.x and later. If one is missing in `bbot -lp`, your version does not have it.

| Preset | What it does | Phase | Passive-capable? |
| --- | --- | --- | --- |
| `subdomain-enum` | All subdomain sources plus DNS brute force | 1 and 2 | Yes, with `-rf passive` |
| `cloud-enum` | Cloud storage and tenant discovery | 1 and 2 | Partly |
| `code-enum` | Repositories, code search, secrets, containers, mobile apps | 1 | Mostly |
| `email-enum` | Email addresses and breach exposure | 1 | Yes |
| `fast` | Quick, shallow subdomain-focused scan | 1 and 2 | Mixed |
| `spider` | Crawls a site by following links | 3 | No |
| `web-basic` | Light web checks (technologies, common misconfigurations) | 3 | No |
| `web-thorough` | Wider web checks, slower | 3 | No |
| `web-screenshots` | Screenshots of discovered pages | 3 | No |
| `tech-detect` | Technology fingerprinting | 3 | No |
| `dirbust-light`, `dirbust-heavy` | Directory and file guessing, small or large list | 3 | No |
| `paramminer` | Hidden parameters, headers, cookies | 3 | No |
| `iis-shortnames` | IIS 8.3 short-name enumeration | 3 | No |
| `dotnet-audit` | Microsoft-stack checks (ASP.NET, Telerik, DotNetNuke and similar) | 3 and 4 | No |
| `lightfuzz-*` | Lightweight web fuzzing at several strengths | 4 | No |
| `baddns-intense` | Extended DNS takeover checks | 4 | No |
| `nuclei`, `nuclei-budget`, `nuclei-intense`, `nuclei-technology` | Template vulnerability scanning at different breadth | 4 | No |
| `kitchen-sink` | Almost everything | Lab only | No |

Every command below stores results under the phase folder shown, using the same pattern: `-o ~/recon/example.com/<phase-folder> -n <phase>-<what>-<date>`. Add `-om json csv asset_inventory` to any of them for machine-readable and spreadsheet output.

### 13.1 subdomain-enum

```
# Passive
bbot -t example.com -p subdomain-enum -rf passive -o ~/recon/example.com/phase1-passive -n passive-subs-2026-11-24 -om subdomains json
# Active
bbot -t example.com -p subdomain-enum -o ~/recon/example.com/phase2-active -n active-subs-2026-11-24 -om subdomains json
```

Use the passive version to map; use the active version to catch what the public data missed.

### 13.2 cloud-enum

```
bbot -t example.com -p cloud-enum -rf passive -o ~/recon/example.com/phase1-passive -n passive-cloud-2026-11-24 -om json
bbot -t example.com -p cloud-enum -o ~/recon/example.com/phase2-active -n active-cloud-2026-11-24 -om json csv
```

The active run tests candidate bucket names against cloud providers. Review `STORAGE_BUCKET` events in `output.json`.

### 13.3 code-enum

```
bbot -t example.com -p code-enum -rf passive -o ~/recon/example.com/phase1-passive -n passive-code-2026-11-24 -om json
bbot -t example.com -p code-enum -o ~/recon/example.com/phase2-active -n active-code-2026-11-24 -om json
```

The second command may also clone and scan found repositories and download related files, so it uses disk space and bandwidth. Store clones inside the scan folder and treat them as sensitive.

### 13.4 email-enum

```
bbot -t example.com -p email-enum -o ~/recon/example.com/phase1-passive -n passive-emails-2026-11-24 -om emails csv
```

Mostly passive by nature. `emails.txt` is the clean list.

### 13.5 fast

```
bbot -t example.com -p fast -o ~/recon/example.com/phase1-passive -n fast-2026-11-24 -om subdomains
```

A time-boxed first look when you only need a quick picture.

### 13.6 spider

```
bbot -t https://www.example.com --strict-scope -p spider -c web.spider_distance=2 web.spider_depth=2 -o ~/recon/example.com/phase3-web -n web-spider-2026-11-24 -om json web_parameters
```

Why `-c`: `spider_distance` limits how many hops away from the start the crawler goes, and `spider_depth` limits directory depth. Without limits a crawl can wander across a large site. `web_parameters` stores discovered parameters.

### 13.7 web-basic and web-thorough

```
bbot -t example.com -p subdomain-enum web-basic -ef loud invasive -o ~/recon/example.com/phase3-web -n web-basic-2026-11-24 -om asset_inventory web_report json
bbot -t example.com -p subdomain-enum web-thorough -o ~/recon/example.com/phase3-web -n web-thorough-2026-11-24 -om asset_inventory web_report json
```

Start with basic. Use thorough only when scope and time allow. `web_report` produces an HTML report for readers who will not open JSON.

### 13.8 web-screenshots

```
bbot -t example.com -p subdomain-enum web-screenshots -o ~/recon/example.com/phase3-web -n web-shots-2026-11-24
```

Screenshots are saved as image files inside the scan folder. Skim them to prioritize: login pages, admin panels, default installs and error pages stand out immediately.

### 13.9 tech-detect

```
bbot -t example.com -p subdomain-enum tech-detect -o ~/recon/example.com/phase3-web -n web-tech-2026-11-24 -om json
```

Filter `TECHNOLOGY` events in `output.json` to see what stack each host runs, which tells you which Phase 4 checks apply.

### 13.10 dirbust-light and dirbust-heavy

```
bbot -t https://app.example.com --strict-scope -p dirbust-light -o ~/recon/example.com/phase3-web -n web-dirlight-2026-11-24 -om json
bbot -t https://app.example.com --strict-scope -p dirbust-heavy -o ~/recon/example.com/phase3-web -n web-dirheavy-2026-11-24 -om json
```

These generate many requests per host. Aim them at one approved application, never a whole domain by accident (note `--strict-scope`).

### 13.11 paramminer

```
bbot -t https://app.example.com --strict-scope -p paramminer -o ~/recon/example.com/phase3-web -n web-params-2026-11-24 -om json web_parameters
```

Finds undocumented parameters, headers and cookies, a common source of hidden functionality. Loud, so use a single in-scope app.

### 13.12 iis-shortnames and dotnet-audit

```
bbot -t example.com -p subdomain-enum iis-shortnames -o ~/recon/example.com/phase3-web -n web-iis-2026-11-24 -om json
bbot -t example.com -p subdomain-enum dotnet-audit -o ~/recon/example.com/phase4-vuln -n vuln-dotnet-2026-11-24 -om json
```

Relevant only when Microsoft IIS or ASP.NET shows up in `tech-detect`.

### 13.13 lightfuzz-\* presets

```
bbot -mh lightfuzz
bbot -lp | grep -i lightfuzz
bbot -t https://app.example.com --strict-scope -p lightfuzz-light -o ~/recon/example.com/phase4-vuln -n vuln-fuzzlight-2026-11-24 -om json
```

The first two commands show what your version offers. Start with the lightest level and move up only with approval, since fuzzing sends modified inputs to the application.

### 13.14 baddns-intense

```
bbot -t example.com -p subdomain-enum baddns-intense -o ~/recon/example.com/phase4-vuln -n vuln-baddns-2026-11-24 -om json
```

Extended takeover and DNS-weakness checks. Findings appear as `FINDING` events.

### 13.15 nuclei family

```
bbot -t example.com -p subdomain-enum nuclei-budget -o ~/recon/example.com/phase4-vuln -n vuln-nuclei-budget-2026-11-24 -om json csv
bbot -t example.com -p subdomain-enum nuclei -o ~/recon/example.com/phase4-vuln -n vuln-nuclei-2026-11-24 -om json csv
bbot -t example.com -p subdomain-enum nuclei-technology -o ~/recon/example.com/phase4-vuln -n vuln-nuclei-tech-2026-11-24 -om json csv
bbot -t example.com -p subdomain-enum nuclei-intense -o ~/recon/example.com/phase4-vuln -n vuln-nuclei-intense-2026-11-24 -om json csv
```

`nuclei-budget` limits how many templates run, `nuclei` is the standard set, `nuclei-technology` picks templates matching detected technologies, and `nuclei-intense` is the widest. Findings arrive as `VULNERABILITY` events with severity. Always run the lowest level first.

### 13.16 kitchen-sink (labs and explicit full-scope only)

```
bbot -t example.com -p kitchen-sink -ef invasive -o ~/recon/example.com/phase4-vuln -n full-2026-11-24 -om asset_inventory web_report json csv
```

Maximum coverage and maximum noise. Keep `-ef invasive`, and add `loud` to the exclusion list on anything that is production.

### 13.17 Combining presets

`-p` accepts a list, and presets can be mixed with flags and extra modules:

```
bbot -t example.com -p subdomain-enum web-basic web-screenshots cloud-enum -ef loud invasive -m portscan fingerprintx -o ~/recon/example.com/phase3-web -n combo-2026-11-24 -om asset_inventory web_report json
```

BBOT merges everything into one scan. Use this when a client wants one consolidated result.

## 14. Every flag paired with commands

The flag list in your help output is: `active, affiliates, baddns, cloud-enum, code-enum, download, email-enum, iis-shortnames, invasive, loud, passive, portscan, safe, service-enum, slow, social-enum, subdomain-enum, subdomain-hijack, web, web-heavy, web-paramminer, web-screenshots`. Confirm what each flag selects on your install with:

```
bbot -lf
```

Three options use flags, with different jobs:

| Option | Rule | Think of it as |
| --- | --- | --- |
| `-f FLAG` | Enable every module that has this flag | "Add this capability" |
| `-rf FLAG` | Only keep modules that have this flag | "Restrict to this behavior" |
| `-ef FLAG` | Disable modules that have this flag | "Never run these" |

### 14.1 Behavior flags (how the scan acts)

| Flag | Command | Phase | Why |
| --- | --- | --- | --- |
| `passive` | `bbot -t example.com -f subdomain-enum -rf passive -n p-flag-2026-11-24 -o ~/recon/example.com/phase1-passive` | 1 | Strictly third-party queries |
| `active` | `bbot -t example.com -f subdomain-enum -rf active -n a-flag-2026-11-24 -o ~/recon/example.com/phase2-active` | 2 | Only the modules that touch the target, handy for a separate evidence record |
| `safe` | `bbot -t example.com -f subdomain-enum -rf safe -n safe-2026-11-24 -o ~/recon/example.com/phase2-active` | 2 | Modules unlikely to cause trouble |
| `loud` | `bbot -t example.com -p web-basic -ef loud -n quiet-web-2026-11-24 -o ~/recon/example.com/phase3-web` | 3 | Exclude the noisy ones. Use `-f loud` only in labs |
| `slow` | `bbot -t example.com -p subdomain-enum -ef slow -n nosl-2026-11-24 -o ~/recon/example.com/phase2-active` | 2 | Skip modules that take a long time, for a fast result |
| `invasive` | `bbot -t example.com -p kitchen-sink -ef invasive -n noninv-2026-11-24 -o ~/recon/example.com/phase4-vuln` | 4 | Never run modules that could change or disturb the target |

### 14.2 Capability flags (what the scan looks for)

| Flag | Command | Phase | Why |
| --- | --- | --- | --- |
| `subdomain-enum` | `bbot -t example.com -f subdomain-enum -n subs-2026-11-24 -o ~/recon/example.com/phase1-passive` | 1 and 2 | Subdomain discovery |
| `affiliates` | `bbot -t example.com -f affiliates -rf passive -n affil-2026-11-24 -o ~/recon/example.com/phase1-passive` | 1 | Related domains and organizations |
| `cloud-enum` | `bbot -t example.com -f cloud-enum -n cloud-2026-11-24 -o ~/recon/example.com/phase2-active` | 2 | Cloud storage and tenants |
| `code-enum` | `bbot -t example.com -f code-enum -rf passive -n code-2026-11-24 -o ~/recon/example.com/phase1-passive` | 1 | Repositories and secrets |
| `email-enum` | `bbot -t example.com -f email-enum -rf passive -n email-2026-11-24 -o ~/recon/example.com/phase1-passive` | 1 | Email addresses |
| `social-enum` | `bbot -t example.com -f social-enum -n social-2026-11-24 -o ~/recon/example.com/phase1-passive` | 1 | Social media and community profiles tied to the domain |
| `portscan` | `bbot -t example.com -f subdomain-enum portscan -n ports-2026-11-24 -o ~/recon/example.com/phase2-active` | 2 | Port scanning modules |
| `service-enum` | `bbot -t example.com -f subdomain-enum portscan service-enum -n svc-2026-11-24 -o ~/recon/example.com/phase2-active` | 2 | Service identification and related checks |
| `web` | `bbot -t example.com -f subdomain-enum web -ef loud -n web-2026-11-24 -o ~/recon/example.com/phase3-web` | 3 | Modules that work against web servers |
| `web-heavy` | `bbot -t https://app.example.com --strict-scope -f web-heavy -n webheavy-2026-11-24 -o ~/recon/example.com/phase3-web` | 3 | Resource-intensive web modules, aim at one approved host |
| `web-paramminer` | `bbot -t https://app.example.com --strict-scope -f web-paramminer -n pm-2026-11-24 -o ~/recon/example.com/phase3-web` | 3 | Hidden parameter discovery |
| `web-screenshots` | `bbot -t example.com -f subdomain-enum web-screenshots -n shots-2026-11-24 -o ~/recon/example.com/phase3-web` | 3 | Screenshots |
| `iis-shortnames` | `bbot -t example.com -f subdomain-enum iis-shortnames -n iis-2026-11-24 -o ~/recon/example.com/phase3-web` | 3 | IIS short-name leaks |
| `baddns` | `bbot -t example.com -f subdomain-enum baddns -n baddns-2026-11-24 -o ~/recon/example.com/phase4-vuln` | 4 | Dangling DNS checks |
| `subdomain-hijack` | `bbot -t example.com -f subdomain-enum subdomain-hijack -n hijack-2026-11-24 -o ~/recon/example.com/phase4-vuln` | 4 | Takeover-risk checks |
| `download` | `bbot -t example.com -f subdomain-enum download -n dl-2026-11-24 -o ~/recon/example.com/phase3-web` | 3 | Modules that download files (documents, images, repositories). Watch disk space and treat files as sensitive |

### 14.3 Useful flag combinations

| Goal | Command |
| --- | --- |
| Passive subdomains only, nothing else | `bbot -t example.com -f subdomain-enum -rf passive` |
| Safe and quiet web discovery | `bbot -t example.com -f subdomain-enum web -rf safe -ef loud slow` |
| Active but never loud or invasive | `bbot -t example.com -f subdomain-enum -rf active -ef loud invasive` |
| Everything except dangerous | `bbot -t example.com -p kitchen-sink -ef invasive loud` |
| Cloud and code, no active contact | `bbot -t example.com -f cloud-enum code-enum -rf passive` |
| Ports and services only | `bbot -t example.com -f portscan service-enum -rf safe` |

When unsure what a combination will run, add `--dry-run -v` and read the module list before removing the dry run.

## 15. Single-module commands (-m) and config options (-c)

Presets are convenient, but sometimes you want exactly one module. `-m` enables modules by name, `-em` excludes them. Before using any module, read its help:

```
bbot -mh crt
bbot -lmo | less
```

The first shows one module's description and options, the second lists every option you can set with `-c`. All commands below use the output pattern `-o ~/recon/example.com/<phase> -n <name>`; it is shortened to `-o <phase> -n <name>` here for readability.

### 15.1 Passive single modules (Phase 1)

| Purpose | Command | Notes |
| --- | --- | --- |
| Certificate transparency only | `bbot -t example.com -m crt certspotter -o phase1-passive -n m-ct-2026-11-24` | Fast, no key needed, high yield |
| Several DNS aggregators | `bbot -t example.com -m hackertarget rapiddns anubisdb otx urlscan -o phase1-passive -n m-agg-2026-11-24` | Combine sources for wider coverage |
| Historical URLs | `bbot -t example.com -m wayback -o phase1-passive -n m-wayback-2026-11-24` | Old endpoints that may still respond |
| Keyed sources | `bbot -t example.com -m virustotal securitytrails shodan_dns censys_dns chaos -o phase1-passive -n m-keyed-2026-11-24` | Add keys in `secrets.yml` first |
| Free port and vulnerability intel | `bbot -t 203.0.113.0/24 -m shodan_idb -o phase1-passive -n m-idb-2026-11-24` | Passive view of exposed ports without scanning |
| Network ownership | `bbot -t example.com -m asn -o phase1-passive -n m-asn-2026-11-24` | Finds the IP ranges the organization owns |
| Related domains | `bbot -t example.com -m viewdns affiliates -o phase1-passive -n m-related-2026-11-24` | Sibling domains for scope discussion |
| Emails | `bbot -t example.com -m emailformat skymem pgp -o phase1-passive -n m-email-2026-11-24 -om emails` | Plus `hunterio` with a key |
| Public repositories | `bbot -t example.com -m github_org github_codesearch gitlab_com postman dockerhub -o phase1-passive -n m-code-2026-11-24` | Add a GitHub token for better limits |
| Mobile apps | `bbot -t example.com -m google_playstore apkpure -o phase1-passive -n m-mobile-2026-11-24` | Then `jadx` to decompile (active, local analysis) |

### 15.2 Active single modules (Phase 2 and 3)

| Purpose | Command | Notes |
| --- | --- | --- |
| Brute force subdomains | `bbot -t example.com -m dnsbrute -o phase2-active -n m-brute-2026-11-24` | Add `dnsbrute_mutations` to expand |
| Port scan | `bbot -t 203.0.113.0/24 -m portscan -o phase2-active -n m-ports-2026-11-24 -om nmap_xml` | Fast; identify services with `fingerprintx` |
| Identify services | `bbot -t 203.0.113.10 -m portscan fingerprintx -o phase2-active -n m-svc-2026-11-24` | Names SSH, SMB, databases and more |
| TLS certificates | `bbot -t example.com -m portscan sslcert -o phase2-active -n m-tls-2026-11-24` | New hostnames from certificates |
| Web probing | `bbot -t example.com -m http -o phase3-web -n m-http-2026-11-24` | Status, titles, headers, technologies |
| Screenshots | `bbot -t example.com -m http gowitness -o phase3-web -n m-shots-2026-11-24` | Visual triage |
| WAF detection | `bbot -t example.com -m http wafw00f -o phase3-web -n m-waf-2026-11-24` | Know what protects the site before testing |
| Site metadata | `bbot -t example.com -m http robots securitytxt favicon -o phase3-web -n m-meta-2026-11-24` | Hidden paths, contacts, shared-product hints |
| Authentication info | `bbot -t example.com -m http ntlm oauth -o phase3-web -n m-auth-2026-11-24` | Internal names and identity configuration |
| JavaScript review | `bbot -t example.com -m http js_unpacker retirejs -o phase3-web -n m-js-2026-11-24` | Vulnerable libraries and hidden endpoints |
| Virtual hosts | `bbot -t 203.0.113.10 -m virtualhost -o phase3-web -n m-vhost-2026-11-24` | Sites hidden behind one IP |
| Directory guessing | `bbot -t https://app.example.com --strict-scope -m http webbrute -o phase3-web -n m-dir-2026-11-24` | Many requests, one approved host |
| Cloud buckets | `bbot -t example.com -m bucket_amazon bucket_google bucket_microsoft bucket_digitalocean bucket_firebase bucket_hetzner -o phase2-active -n m-buckets-2026-11-24` | Add `bucket_file_enum` for contents |
| Cloud tenant | `bbot -t example.com -m azure_tenant -o phase2-active -n m-tenant-2026-11-24` | Microsoft 365 footprint |

### 15.3 Vulnerability single modules (Phase 4, approval needed)

| Purpose | Command |
| --- | --- |
| Takeover checks | `bbot -t example.com -m baddns baddns_direct baddns_zone -o phase4-vuln -n m-baddns-2026-11-24` |
| Template vulnerability checks | `bbot -t example.com -m http nuclei -o phase4-vuln -n m-nuclei-2026-11-24` |
| Default framework secrets | `bbot -t example.com -m http badsecrets -o phase4-vuln -n m-secrets-2026-11-24` |
| Microsoft-stack checks | `bbot -t example.com -m http telerik ajaxpro dotnetnuke aspnet_bin_exposure -o phase4-vuln -n m-ms-2026-11-24` |
| GraphQL exposure | `bbot -t https://api.example.com --strict-scope -m http graphql_introspection -o phase4-vuln -n m-gql-2026-11-24` |
| Access-control and parsing tests | `bbot -t https://app.example.com --strict-scope -m http bypass403 url_manipulation host_header -o phase4-vuln -n m-access-2026-11-24` |
| CI/CD misconfiguration | `bbot -t example.com -m trajan -o phase4-vuln -n m-cicd-2026-11-24` |
| Secret scanning in code | `bbot -t example.com -m github_org git_clone trufflehog -o phase4-vuln -n m-trufflehog-2026-11-24` |

Credential-testing modules (`legba`, `medusa`) are deliberately not listed. They can lock real accounts, so only use them when a client has specifically authorized credential testing and defined the accounts and limits in writing.

### 15.4 Config options with -c

`-c key=value` overrides a setting for one run. The exact keys come from `bbot -lmo`, so confirm names there. Typical examples:

| Goal | Example | Why |
| --- | --- | --- |
| Limit crawl distance and depth | `-c web.spider_distance=2 web.spider_depth=2` | Keeps spidering bounded |
| Supply an API key for one run | `-c modules.shodan_dns.api_key=KEY` | Test before saving |
| Restrict nuclei severity | `-c modules.nuclei.severity=critical,high` | Smaller, more actionable results |
| Limit port range | `-c modules.portscan.top_ports=100` | Shorter and quieter port scan |
| Slow the port scan rate | `-c modules.portscan.rate=200` | Gentler on fragile networks |
| Change brute-force depth | `-c modules.dnsbrute.max_depth=2` | Controls how deep wordlist guessing goes |

Persistent settings belong in `~/.config/bbot/bbot.yml`. Use `-c` for per-run overrides and keep the resulting `preset.yml` in the scan folder to record exactly what was used.

### 15.5 Excluding modules

```
bbot -t example.com -p subdomain-enum -em dnsbrute dnsbrute_mutations -o phase1-passive -n no-brute-2026-11-24
```

Why: removes brute force without relying on the `passive` flag. Useful when one specific module is noisy or failing.

## 16. Storing, organizing and processing outputs

BBOT stores results in several ways at once. Pick the right one for the job, and keep raw data separate from reports.

### 16.1 Where files land

| Setting | Where results go |
| --- | --- |
| No `-o` | The default scans folder, usually `~/.bbot/scans/<scan-name>/` |
| `-o DIR` | `DIR/<scan-name>/` |
| `-n NAME` | Sets `<scan-name>` (otherwise BBOT picks a random name) |

Inside a scan folder you will typically find the console log (`output.txt`), JSON events (`output.json`), CSV (`output.csv`), a scan log (`scan.log`), the preset used, and one file per extra output module (for example `subdomains.txt`, `emails.txt`, `asset-inventory.csv`, `nmap.xml`, a web report and a screenshots folder). Confirm names with `ls -la` on a finished scan, since they can change between versions.

```
ls -la ~/recon/example.com/phase1-passive/passive-subs-2026-11-24/
```

### 16.2 Which output module for which job

| Need | Use | Example flag |
| --- | --- | --- |
| Quick human reading | `txt` (on by default) | built in |
| Machine processing | `json` | `-om json` |
| Spreadsheet triage | `csv` | `-om csv` |
| Clean hostname list for other tools | `subdomains` | `-om subdomains` |
| Email list | `emails` | `-om emails` |
| Client deliverable table | `asset_inventory` | `-om asset_inventory` |
| HTML summary for non-technical readers | `web_report` | `-om web_report` |
| Import into network tooling | `nmap_xml` | `-om nmap_xml` |
| Discovered web parameters | `web_parameters` | `-om web_parameters` |
| Long-running database | `sqlite`, `postgres`, `mysql`, `mongo` | `-om sqlite` |
| Search and dashboards | `elastic`, `splunk` | `-om elastic` |
| Relationship graph | `neo4j` | `-om neo4j` |
| Live alerts | `slack`, `discord`, `teams`, `webhook` | `-om slack` |
| Streaming to other systems | `kafka`, `nats`, `rabbitmq`, `zeromq`, `websocket` | `-om kafka` |

Settings for each output module (database addresses, webhook URLs) are set with `-c`, for example `-c modules.slack.webhook_url=...`. Run `bbot -lmo` to see the exact option names, and prefer `secrets.yml` for anything containing a credential.

To skip a default output you do not want, use `-eom`:

```
bbot -t example.com -p subdomain-enum -eom txt -om json -n json-only-2026-11-24
```

### 16.3 Reading the JSON with jq

Each line of `output.json` is one event. Install `jq` if needed (`sudo apt install jq`). Replace `SCAN` with your scan folder.

```
SCAN=~/recon/example.com/phase1-passive/passive-subs-2026-11-24

# Unique hostnames
jq -r 'select(.type=="DNS_NAME") | .data' $SCAN/output.json | sort -u

# Which module found each host
jq -r 'select(.type=="DNS_NAME") | [.data, .module] | @tsv' $SCAN/output.json | sort -u

# Live web pages
jq -r 'select(.type=="URL") | .data' $SCAN/output.json | sort -u

# Open ports
jq -r 'select(.type=="OPEN_TCP_PORT") | .data' $SCAN/output.json | sort -u

# Technologies detected
jq -r 'select(.type=="TECHNOLOGY") | [.host, .data.technology] | @tsv' $SCAN/output.json | sort -u

# Findings and vulnerabilities with severity
jq -r 'select(.type=="FINDING" or .type=="VULNERABILITY") | [.type, .host, (.data.severity // "-"), (.data.description // .data)] | @tsv' $SCAN/output.json

# Storage buckets
jq -r 'select(.type=="STORAGE_BUCKET") | .data' $SCAN/output.json

# Email addresses
jq -r 'select(.type=="EMAIL_ADDRESS") | .data' $SCAN/output.json | sort -u
```

The exact JSON fields depend on the event type and BBOT version. Inspect one line first with `head -n 1 $SCAN/output.json | jq .` and adjust the filters.

### 16.4 Reading with grep and standard tools

```
grep -h "\[FINDING\]" $SCAN/output.txt
grep -h "\[VULNERABILITY\]" $SCAN/output.txt | sort -u
wc -l $SCAN/subdomains.txt
sort -u $SCAN/subdomains.txt > phase1-passive/subdomains-final.txt
```

Why: quick triage without any extra tooling. Use `wc -l` to track growth between scans.

### 16.5 Merging several scans

```
cat ~/recon/example.com/phase*/*/subdomains.txt | sort -u > ~/recon/example.com/reports/all-subdomains.txt
```

Why: Phase 1 and Phase 2 results combined into one master list, de-duplicated.

### 16.6 Comparing scans to find change

```
sort -u old/subdomains.txt > /tmp/old.txt
sort -u new/subdomains.txt > /tmp/new.txt
comm -13 /tmp/old.txt /tmp/new.txt > ~/recon/example.com/reports/new-subdomains.txt
```

`comm -13` prints lines present only in the new file. Run the same preset on a schedule and this becomes an attack-surface change detector: new hostnames can mean new projects, new vendors or forgotten test systems.

### 16.7 A database for long engagements

```
bbot -t example.com -p subdomain-enum -om sqlite json -n db-2026-11-24 -o ~/recon/example.com/phase2-active
```

Why: SQLite gives you a single file you can query with `sqlite3`. For teams, use `postgres` or `elastic` through `-c` settings for their addresses. Use `neo4j` when you want to see how assets connect.

### 16.8 Alerts while the scan runs

```
bbot -t example.com -p subdomain-enum nuclei-budget -om slack -c modules.slack.webhook_url=https://hooks.example/XXXX -n alert-2026-11-24 -o ~/recon/example.com/phase4-vuln
```

Why: the team is notified of findings as they happen. Limit which event types are sent by checking `bbot -mh slack` options, and keep webhook URLs out of shared notes.

### 16.9 Protect and archive results

Scan data can contain secrets, personal data and exploitable details.

```
chmod -R go-rwx ~/recon/example.com
cd ~/recon && tar czf example.com-2026-11-24.tar.gz example.com
gpg -c example.com-2026-11-24.tar.gz
sha256sum example.com-2026-11-24.tar.gz.gpg > example.com-2026-11-24.sha256
```

- `chmod` restricts access to your user.
- `tar` bundles the engagement.
- `gpg -c` encrypts the archive with a passphrase.
- `sha256sum` records an integrity hash to prove the file was not modified.

Agree retention with the client and delete or return data when the engagement ends.

### 16.10 Housekeeping

| Task | Command |
| --- | --- |
| Size of scan folders | `du -sh ~/recon/example.com/*` |
| Newest scans | `ls -lt ~/.bbot/scans \| head` |
| Remove old default scans | `rm -rf ~/.bbot/scans/<old-scan-name>` (only after archiving) |
| Re-create the config after a bad edit | `bbot --reset-config` (a backup is kept) |
| Re-create the keys file | `bbot --reset-secrets` (overwrites keys; keep the backup safe) |

## 17. Full engagement script and master checklist

### 17.1 Everything in order, as one script

Save as `recon.sh`, make it executable with `chmod +x recon.sh`, and run `./recon.sh example.com`. Set `PHASE` to run one phase at a time (`1`, `2`, `3`, `4`). It stops before active phases unless you opt in, so an accidental run stays passive.

```
#!/usr/bin/env bash
set -euo pipefail

DOMAIN="${1:?usage: ./recon.sh <domain> [phase]}"
PHASE="${2:-1}"
DATE=$(date +%F)
ROOT="$HOME/recon/$DOMAIN"
BLACKLIST_FILE="$ROOT/blacklist.txt"   # one host or range per line, may be empty
UAS="authorized-test contact secops@yourfirm.example"

mkdir -p "$ROOT"/{phase1-passive,phase2-active,phase3-web,phase4-vuln,reports}
touch "$BLACKLIST_FILE"
BL=()
while read -r line; do [ -n "$line" ] && BL+=("$line"); done < "$BLACKLIST_FILE"
BLARGS=()
[ ${#BL[@]} -gt 0 ] && BLARGS=(-b "${BL[@]}")

run() {  # run <phase-folder> <name> <extra bbot args...>
  local folder="$1" name="$2"; shift 2
  bbot -t "$DOMAIN" "${BLARGS[@]}" -y -uas "$UAS" \
    -o "$ROOT/$folder" -n "$name-$DATE" "$@"
}

case "$PHASE" in
1)  # PASSIVE: the target is never contacted
  run phase1-passive passive-all \
    -p subdomain-enum code-enum email-enum cloud-enum -rf passive \
    -om subdomains emails asset_inventory json csv
  ;;
2)  # ACTIVE: DNS brute force, ports, services
  run phase2-active active-infra \
    -p subdomain-enum -m portscan fingerprintx sslcert -ef loud invasive \
    -om subdomains asset_inventory nmap_xml json csv
  ;;
3)  # WEB: probing, technologies, screenshots
  run phase3-web web-basic \
    -p subdomain-enum web-basic web-screenshots tech-detect -ef loud invasive \
    -om asset_inventory web_report web_parameters json
  ;;
4)  # VULNERABILITY: takeover and templates, approval required
  run phase4-vuln vuln-budget \
    -p subdomain-enum baddns-intense nuclei-budget -ef invasive \
    -om json csv
  ;;
*) echo "phase must be 1, 2, 3 or 4"; exit 1 ;;
esac

echo "Done. Results under $ROOT"
```

| Line | Purpose |
| --- | --- |
| `set -euo pipefail` | Stops the script on any error instead of continuing blindly |
| `BLACKLIST_FILE` | Keeps excluded hosts in one reviewable file that is passed to every phase with `-b` |
| `-y` | Skips prompts so the script can run unattended |
| `-uas` | Marks your traffic with a contact string for defenders |
| `run()` | One function so every scan uses the same naming and output pattern |
| `case` | Runs one phase at a time, so you can review results between phases |

Run a preview of any phase by adding `--dry-run` inside `run()` temporarily.

### 17.2 Master checklist

**Before you start**

- [ ] Written authorization, scope and testing window in hand
- [ ] `blacklist.txt` filled with every excluded host or range
- [ ] API keys added to `secrets.yml`
- [ ] Output folders created, access restricted with `chmod`
- [ ] Traffic identification (user-agent suffix, header) agreed with the client

**Phase 1: Passive**

- [ ] `bbot ... -p subdomain-enum -rf passive`
- [ ] Code, email and cloud passive scans
- [ ] Results merged and de-duplicated
- [ ] Scope list reviewed with the client

**Phase 2: Active infrastructure**

- [ ] DNS brute force with mutations
- [ ] Port scan, service fingerprinting, certificate names
- [ ] `asset_inventory` and `nmap_xml` saved

**Phase 3: Web**

- [ ] `web-basic`, screenshots and technology detection
- [ ] Screenshots reviewed for login pages, admin panels, default installs
- [ ] Approved single hosts only for directory, parameter and spider scans

**Phase 4: Vulnerability (approval required)**

- [ ] Takeover checks with `baddns-intense`
- [ ] `nuclei-budget` first, wider only with permission
- [ ] Findings validated manually before reporting

**After**

- [ ] Reports generated from `asset_inventory` and `web_report`
- [ ] Archive encrypted, hash recorded
- [ ] Retention and deletion agreed with the client
- [ ] Preset YAML files kept for repeatability

### 17.3 Quick passive vs active decision table

| If you want to... | Use | Contacts target? |
| --- | --- | --- |
| Map subdomains from public data | `-p subdomain-enum -rf passive` | No |
| Find repos and leaked code | `-p code-enum -rf passive` | No |
| Find emails | `-p email-enum` | No |
| Guess hidden subdomains | `-p subdomain-enum` (no `-rf passive`) | Yes, DNS |
| Find open ports | add `-m portscan fingerprintx` | Yes |
| Probe web servers | add `-m http` or `-p web-basic` | Yes |
| Capture screenshots | add `-p web-screenshots` | Yes |
| Test buckets | `-p cloud-enum` (no `-rf passive`) | Yes, cloud providers |
| Check takeovers | add `-m baddns` or `-p baddns-intense` | Yes, DNS and HTTP |
| Run vulnerability templates | add `-p nuclei-budget` | Yes, heavily |
| Stay safe on production | add `-ef loud invasive slow` | Yes, but gentler |

## 18. Part 3: The simple-to-advanced learning ladder

Sections 10 to 17 are a reference. This part teaches in order. Each level builds on the last, ends with an exercise, and says what you should be able to do before moving on. Use only your own lab or authorized targets.

| Level | Skill | You can... |
| --- | --- | --- |
| 1 | Beginner | Run a scan and read the output |
| 2 | Novice | Use presets, passive-only mode and output files |
| 3 | Intermediate | Control scope, use keys, flags and config |
| 4 | Advanced | Write custom presets, tune performance, scan authenticated apps, process JSON |
| 5 | Expert | Script BBOT in Python, automate, diff and chain tools |

### Level 1: Beginner (your first scans)

**Goal:** understand what BBOT prints and how to stay safe.

**Step 1. Check the tool works.**

```
bbot --version
bbot -h
```

`--version` confirms the install. `-h` is the help you pasted earlier. If either fails, reinstall (`pipx install bbot` is the usual method) before going on.

**Step 2. Look before you run.**

```
bbot -l
bbot -lp
bbot -lf
```

These list modules, presets and flags without scanning anything. Read the passive and active labels in `bbot -l`; this is where the passive-vs-active idea from section 1 becomes concrete.

**Step 3. Preview a scan without sending traffic.**

```
bbot -t example.com -p subdomain-enum --dry-run
```

`--dry-run` builds the scan and exits. Read the module list it prints. This habit prevents most beginner mistakes.

**Step 4. Run your first real scan, passive only.**

```
bbot -t example.com -p subdomain-enum -rf passive
```

The target is a domain you own or have permission to test. Because of `-rf passive`, BBOT only asks public data sources about it.

**Step 5. Read the output.**

You will see lines shaped like this (values are illustrative):

```
[SCAN]        Scan ready. Scope: example.com
[DNS_NAME]    api.example.com    crt
[DNS_NAME]    mail.example.com   certspotter
[IP_ADDRESS]  203.0.113.10       api.example.com
[SCAN]        Scan completed
```

Read each line as: `[event type] value  module-that-found-it`. At the end, BBOT prints where the files were saved. Open that folder and look at `output.txt`.

**Step 6. Name your scan and choose where it goes.**

```
bbot -t example.com -p subdomain-enum -rf passive -n my-first-scan -o ~/recon-lab
```

`-n` names the scan and `-o` chooses the parent folder, so the results are in `~/recon-lab/my-first-scan/`.

**Beginner exercise.** Run Steps 3, 4 and 6 against a domain you own. Then answer: how many hostnames did it find, and which module found most of them? (Hint: `grep -c DNS_NAME output.txt`.)

**You are ready for Level 2 when** you can explain what `-t`, `-p`, `-rf`, `-n` and `-o` do without looking.

### Level 2: Novice (presets, output files, passive vs active)

**Goal:** use ready-made recipes and understand the difference between looking and touching.

**Step 1. Compare passive and active on the same target.**

```
bbot -t example.com -p subdomain-enum -rf passive -n lvl2-passive -o ~/recon-lab -om subdomains
bbot -t example.com -p subdomain-enum -n lvl2-active -o ~/recon-lab -om subdomains
wc -l ~/recon-lab/lvl2-passive/subdomains.txt ~/recon-lab/lvl2-active/subdomains.txt
```

The second scan includes DNS brute force. `wc -l` counts lines so you can see how many extra names active guessing found. The difference is exactly what "active" buys you, and exactly what it costs in noise.

**Step 2. Try each recon preset.**

```
bbot -t example.com -p email-enum -n lvl2-email -o ~/recon-lab -om emails
bbot -t example.com -p code-enum -rf passive -n lvl2-code -o ~/recon-lab
bbot -t example.com -p cloud-enum -rf passive -n lvl2-cloud -o ~/recon-lab
```

Each preset is a different question: who works here, what code is public, what cloud assets exist.

**Step 3. Add one module to a preset.**

```
bbot -t example.com -p subdomain-enum -m portscan -n lvl2-ports -o ~/recon-lab
```

`-m` adds a single module on top of the preset. Here it adds port scanning to the subdomain results. Read `bbot -mh portscan` first.

**Step 4. Understand output files.**

```
ls ~/recon-lab/lvl2-active/
head ~/recon-lab/lvl2-active/output.txt
head -n 3 ~/recon-lab/lvl2-active/output.json
```

Three views of the same data: readable text, readable JSON. The JSON is the one you will process with tools later.

**Step 5. Save output formats you will need.**

```
bbot -t example.com -p subdomain-enum -rf passive -om subdomains csv json asset_inventory -n lvl2-formats -o ~/recon-lab
```

Open the CSV in a spreadsheet and the `asset-inventory.csv` to see the client-style table.

**Novice exercise.** Run passive and active scans of your lab domain, then use `comm -13` (section 16.6) to list names found only by the active scan.

**You are ready for Level 3 when** you can choose passive or active deliberately and know where every file is.

### Level 3: Intermediate (scope, keys, flags, config)

**Goal:** control exactly what is scanned and how much data you get.

**Step 1. Add API keys.**

```
nano ~/.config/bbot/secrets.yml
```

Add keys for services you have accounts with (typically under `modules: <module_name>: api_key:`). The file contains a template after the first run. Then verify a keyed module works:

```
bbot -t example.com -m virustotal -n lvl3-key-test -o ~/recon-lab
```

Without a key, keyed modules are skipped at startup, and you will see this in the setup messages.

**Step 2. Control scope tightly.**

```
bbot -t www.example.com --strict-scope -p subdomain-enum -rf passive -n lvl3-strict -o ~/recon-lab
bbot -t example.com -b staging.example.com 198.51.100.7 -p subdomain-enum -n lvl3-bl -o ~/recon-lab
```

The first stays on one hostname. The second scans the whole domain but never touches the blacklisted hosts.

**Step 3. Use a target file for many targets.**

```
printf "example.com\nexample.org\n203.0.113.0/24\n" > targets.txt
bbot -t targets.txt -p subdomain-enum -rf passive -n lvl3-multi -o ~/recon-lab
```

**Step 4. Use flags to shape behaviour.**

```
bbot -t example.com -p subdomain-enum web-basic -ef loud invasive -n lvl3-flags -o ~/recon-lab
bbot -t example.com -f subdomain-enum -rf passive -n lvl3-flagpassive -o ~/recon-lab
```

The first adds web checks but removes anything noisy or risky. The second builds a passive-only scan from a flag instead of a preset.

**Step 5. Override settings with -c.**

```
bbot -t www.example.com -p spider -c web.spider_distance=1 web.spider_depth=1 -n lvl3-spider -o ~/recon-lab
```

Find more options with `bbot -lmo | grep -i spider`.

**Step 6. Tag your traffic and use a proxy.**

```
bbot -t example.com -p subdomain-enum web-basic -uas "lab-test" -H "X-Test=lab" --proxy http://127.0.0.1:8080 -n lvl3-proxy -o ~/recon-lab
```

Watch the requests in Burp. This shows exactly what BBOT sends, and a good way to learn what "active" means in practice.

**Step 7. Add a screenshot pass.**

```
bbot -t example.com -p subdomain-enum web-screenshots -ef loud invasive -n lvl3-shots -o ~/recon-lab
```

**Intermediate exercise.** Build one command that scans your lab domain, excludes one host, tags traffic, keeps output in a dated folder, and produces `subdomains`, `asset_inventory` and `json`. Write it as one command you can reuse.

**You are ready for Level 4 when** you never run a scan without knowing scope, blacklist and flags.

### Level 4: Advanced (custom presets, tuning, authenticated scans, pipelines)

**Goal:** make BBOT behave exactly the way your team needs, repeatably.

**Step 1. Understand what a preset really is.**

```
bbot -t example.com -p subdomain-enum -rf passive --current-preset
bbot -t example.com -p subdomain-enum --current-preset-full | less
```

The first prints the short YAML for the current command. The second prints every effective setting, including defaults. Reading these teaches you the YAML keys.

**Step 2. Write your own preset.**

Save as `~/recon/presets/passive-client.yml`:

```
description: Passive-only client recon with safe web checks later
include:
  - subdomain-enum
  - code-enum
  - email-enum
require_flags:
  - passive
output_modules:
  - subdomains
  - emails
  - asset_inventory
  - json
  - csv
config:
  web:
    http_timeout: 15
```

Run it by path:

```
bbot -t example.com -p ~/recon/presets/passive-client.yml -n lvl4-custom -o ~/recon/example.com/phase1-passive
```

Why: a preset file is a reviewable, version-controlled record of exactly what ran. Common keys are `description`, `include` (other presets), `flags`, `require_flags`, `exclude_flags`, `modules`, `exclude_modules`, `output_modules`, and `config`. Validate your file with `--current-preset` before trusting it, since key names can change between versions.

A second preset for a gentle active pass, `active-safe.yml`:

```
description: Active but quiet
include:
  - subdomain-enum
  - web-basic
exclude_flags:
  - loud
  - invasive
  - slow
modules:
  - portscan
  - fingerprintx
  - sslcert
output_modules:
  - asset_inventory
  - web_report
  - json
```

**Step 3. Put targets, seeds and blacklist in the preset (or keep them separate).**

Keeping targets out of the preset lets one preset serve many engagements. Keep a separate `blacklist.txt` and pass it with `-b`. Presets can also name a blacklist, but a visible command-line `-b` is easier to audit.

**Step 4. Tune performance and politeness.**

```
bbot -lmo | less
bbot -t example.com -p subdomain-enum -c dns.threads=100 -n lvl4-tune -o ~/recon-lab
```

The `-lmo` output lists the real option names for your version. Typical tuning goals: fewer concurrent DNS or web requests on fragile targets, longer timeouts on slow ones, lower port-scan rate on shared networks. Make one change at a time and compare scan duration and result counts.

**Step 5. Use custom resolvers and a proxy chain.**

```
bbot -t example.com -p subdomain-enum --custom-nameservers 1.1.1.1 9.9.9.9 --proxy http://127.0.0.1:8080 -n lvl4-net -o ~/recon-lab
```

Why: reliable DNS for brute force, and full request visibility through your proxy.

**Step 6. Scan an authenticated web app (with permission).**

```
bbot -t https://app.example.com --strict-scope -p spider web-basic \
  -C "session=PASTE_TEST_ACCOUNT_COOKIE" -H "Authorization=Bearer TEST_TOKEN" \
  -c web.spider_distance=2 web.spider_depth=3 \
  -n lvl4-auth -o ~/recon/example.com/phase3-web
```

`-C` adds cookies and `-H` adds headers, so BBOT sees pages behind login. Use a dedicated test account, never a real user's session, and confirm in writing that authenticated testing is in scope. Cookies and tokens end up in shell history: clear it afterwards or use a file-based approach.

**Step 7. Add your own detection rules.**

```
bbot -t https://app.example.com --strict-scope -p spider --custom-yara-rules ./rules.yar -n lvl4-yara -o ~/recon-lab
```

`--custom-yara-rules` adds your own patterns (for example an internal project code name, or an API-key format) to the content-extraction step, so BBOT can flag them in pages it fetches.

**Step 8. Filter console and files precisely.**

```
bbot -t example.com -p subdomain-enum --event-types DNS_NAME IP_ADDRESS OPEN_TCP_PORT -n lvl4-filter -o ~/recon-lab
bbot -t example.com -p subdomain-enum --brief -y | sort -u
```

Use `--event-types` to keep the console readable and `--brief` for pipelines.

**Step 9. Build jq pipelines.**

```
SCAN=~/recon/example.com/phase3-web/lvl4-auth

# Hosts with their detected technologies, grouped
jq -r 'select(.type=="TECHNOLOGY") | "\(.host)\t\(.data.technology)"' $SCAN/output.json | sort -u

# All FINDING descriptions, de-duplicated
jq -r 'select(.type=="FINDING") | .data.description' $SCAN/output.json | sort | uniq -c | sort -rn

# Count events by type
jq -r '.type' $SCAN/output.json | sort | uniq -c | sort -rn

# Events found by one module
jq -r 'select(.module=="crt") | .data' $SCAN/output.json | sort -u
```

The last two commands are the fastest way to understand a new scan: how many events of each kind, and which module contributed what.

**Step 10. Feed other tools.**

```
jq -r 'select(.type=="URL") | .data' $SCAN/output.json | sort -u > urls.txt
jq -r 'select(.type=="DNS_NAME") | .data' $SCAN/output.json | sort -u > hosts.txt
```

These lists plug into your other approved tools (a proxy sitemap, a screenshot tool, a vulnerability scanner).

**Advanced exercise.** Create `passive-client.yml` and `active-safe.yml`, run both against your lab, and use `jq` to produce a table of host, IP, open port and technology. Commit the YAML and results folder to a private git repository.

**You are ready for Level 5 when** every scan you run is repeatable from a file and you can answer "what exactly ran" from the scan folder alone.

### Level 5: Expert (Python API, automation, change detection, tool chaining)

**Goal:** run BBOT as part of a continuous attack-surface program rather than as a one-off tool.

**Step 1. Use BBOT as a Python library.**

BBOT can be imported and scripted. Save as `scan.py` (run with `python3 scan.py`):

```
from bbot.scanner import Scanner

# Same options as the command line: targets, presets, flags, config
scan = Scanner(
    "example.com",
    presets=["subdomain-enum"],
    require_flags=["passive"],
    output_dir="/home/kali/recon/example.com/phase1-passive",
    name="py-passive-2026-11-24",
)

hosts = set()
for event in scan.start():
    if event.type == "DNS_NAME":
        hosts.add(str(event.data))
    elif event.type in ("FINDING", "VULNERABILITY"):
        print(event.type, event.host, event.data)

print(f"{len(hosts)} unique hostnames")
open("hosts-py.txt", "w").write("\n".join(sorted(hosts)))
```

Why: lets you filter events live, post to an API, or decide the next scan from the previous results. Constructor argument names can differ between versions, so check the project's developer documentation for your release and test with a harmless target first. For a larger program, the async variant (`async for event in scan.async_start()`) fits inside other async code.

**Step 2. Chain scans automatically (passive result drives active scan).**

```
#!/usr/bin/env bash
set -euo pipefail
D=example.com
ROOT=~/recon/$D
bbot -t $D -p subdomain-enum -rf passive -om subdomains -y -n chain-p1 -o $ROOT/phase1-passive
# keep only names you are authorized to touch
grep -E "\.${D//./\\.}$|^${D//./\\.}$" $ROOT/phase1-passive/chain-p1/subdomains.txt | sort -u > $ROOT/scope-confirmed.txt
# active scan only against the confirmed list
bbot -t $ROOT/scope-confirmed.txt -m portscan fingerprintx http -ef loud invasive -y -n chain-p2 -o $ROOT/phase2-active
```

Why: a human review step (editing `scope-confirmed.txt`) sits between passive discovery and active contact, which is the discipline of a professional engagement. Remove names the client owns but excluded, or third-party hosts that appeared in results.

**Step 3. Schedule recurring scans.**

```
crontab -e
# every Monday at 02:00, passive scan with dated output
0 2 * * 1 /home/kali/recon.sh example.com 1 >> /home/kali/recon/example.com/cron.log 2>&1
```

Why: attack surface changes constantly. A weekly passive scan costs nothing and catches new hostnames, new certificates and newly exposed repositories.

**Step 4. Detect and report change.**

```
#!/usr/bin/env bash
D=example.com
BASE=~/recon/$D/phase1-passive
NEW=$(ls -1dt $BASE/*/ | sed -n 1p)
OLD=$(ls -1dt $BASE/*/ | sed -n 2p)
sort -u "$NEW/subdomains.txt" > /tmp/new.txt
sort -u "$OLD/subdomains.txt" > /tmp/old.txt
echo "New since last scan:"; comm -13 /tmp/old.txt /tmp/new.txt
echo "Gone since last scan:"; comm -23 /tmp/old.txt /tmp/new.txt
```

Why: `comm -13` shows only-new lines and `comm -23` shows only-old lines. Pipe the new list to a notification (Slack, email) so someone reviews fresh assets within hours instead of months.

**Step 5. Stream events into storage and alerts.**

```
bbot -t example.com -p subdomain-enum -rf passive -om json sqlite slack -c modules.slack.webhook_url=https://hooks.example/XXXX -y -n stream-2026-11-24 -o ~/recon/example.com/phase1-passive
```

Use `neo4j` when graph questions matter ("which hosts share this certificate and this IP?"), `elastic` or `splunk` when your SOC already searches there, and `kafka` or `nats` when other systems subscribe to events.

**Step 6. Chain with other approved tools.**

| Stage | Tool role | Example hand-off |
| --- | --- | --- |
| Discover | BBOT | `bbot ... -om subdomains` produces `subdomains.txt` |
| Resolve and probe | HTTP prober | Read `subdomains.txt` |
| Crawl | Crawler or proxy | Read `urls.txt` from `jq` |
| Test | Vulnerability scanner | Read only confirmed in-scope URLs |
| Track | Ticketing | Create tickets from `FINDING` events |

Why: BBOT is strongest at breadth. Depth testing is better done by tools that specialize in it, fed with BBOT's confirmed asset lists.

**Step 7. Write or modify modules (concept).**

BBOT supports custom modules placed in a folder and loaded through a preset (the help text mentions that `-y` also trusts custom module folders). A module declares which event types it watches, which it produces, its flags, and a handler function that runs when a matching event arrives. Start by reading an existing small module's source, copy it into your custom folder, change the logic and test on a lab domain. Review any third-party module code before loading, because modules run with your privileges.

**Step 8. Make results trustworthy.**

- Keep the preset YAML, `scan.log` and the command you ran with every result set.
- Re-run key findings manually and capture evidence (request and response, screenshot).
- Record the BBOT version (`bbot --version`) in reports, since module behaviour changes.
- Treat third-party data as leads, not proof: stale passive data can name hosts that no longer exist.

**Expert exercise.** Build a pipeline that runs a weekly passive scan, emails you only the new hostnames, and (after your manual approval step) runs a quiet active scan against the approved list, storing everything in dated folders with an encrypted weekly archive.

### Common mistakes at each level

| Level | Mistake | Fix |
| --- | --- | --- |
| 1 | Running a scan on a domain you do not have permission for | Use your own domain or a lab. Passive-only is still a decision you should be able to justify |
| 1 | Not reading what is about to run | Use `--dry-run` and `bbot -mh` first |
| 2 | Thinking `-p subdomain-enum` is passive | It includes active DNS brute force. Add `-rf passive` |
| 2 | Losing scan results in a random folder | Always use `-o` and `-n` |
| 3 | Scanning the whole domain when only one app is approved | Use `--strict-scope` and a blacklist |
| 3 | Skipping API keys then assuming BBOT is weak | Keys add large amounts of passive data |
| 3 | Putting secrets directly on the command line | They enter shell history. Use `secrets.yml` |
| 4 | Editing a preset without checking the result | Run `--current-preset` after every change |
| 4 | Using a real user's cookie for authenticated scans | Use a dedicated test account agreed in writing |
| 4 | Changing many tuning settings at once | Change one, compare, repeat |
| 5 | Fully automating active scans | Keep a human approval step between discovery and active contact |
| 5 | Trusting passive data as fact | Verify before reporting. Old records create false leads |
| 5 | Loading unreviewed custom modules | Read the code first. Modules run with your privileges |

### Practice labs (all legal and local)

1. **Own domain lab.** Register a cheap domain, add a few subdomains, a test web page and a storage bucket you control. Run every phase against it and check what BBOT finds versus what you know exists.
2. **Vulnerable VM lab.** Run a deliberately vulnerable web application in a VM on a host-only network. Scan the VM's IP with `portscan`, `fingerprintx`, `http`, `web-basic` and `nuclei-budget`. This is the safest place to see active modules work.
3. **Passive vs active diff lab.** Scan the same lab domain passively and actively. Use `comm` to list what only active scans found, and explain why public data missed it.
4. **Change-detection lab.** Run the same passive scan today and in a week. Add a subdomain in between, and confirm your diff script reports it.
5. **Detection lab.** Scan your lab with a proxy on and look at the traffic in logs. Learn what each phase looks like from the defender's side.

### Skills self-test

Answer without looking back.

1. What is the difference between `-t` and `-s`?
2. Which flag option keeps only passive modules, and which removes loud ones?
3. Why is `-p subdomain-enum` alone not strictly passive?
4. Which command previews a scan without running it?
5. Where do results go when you set `-o ~/recon/x -n scan1`?
6. Which output module gives a clean hostname list, and which gives a client-style inventory table?
7. What does `--strict-scope` change?
8. How do you record exactly which modules ran?
9. Why keep a human approval step between passive and active phases?
10. Name two ways to be identifiable to defenders during an authorized active test.

**Answers.** 1) `-t` defines scope, `-s` only seeds passive modules. 2) `-rf passive`, and `-ef loud`. 3) It also runs DNS brute force, which contacts the target's DNS. 4) `--dry-run`. 5) `~/recon/x/scan1/`. 6) `subdomains` and `asset_inventory`. 7) Subdomains of the target are no longer automatically in scope. 8) `--current-preset` and the saved preset in the scan folder. 9) To confirm assets are authorized and in scope before touching them. 10) A user-agent suffix (`-uas`) and a custom header (`-H`).

## Sources

- The BBOT v3.1.0 help output you pasted (options, module list, flags, output modules, examples).
- BBOT project: [Black Lantern Security BBOT](https://www.blacklanternsecurity.com/bbot), the address printed in the banner.
- Module and preset descriptions come from general knowledge of BBOT, not from pages opened in this session, so treat them as approximate. Confirm any detail with `bbot -mh <module>`, `bbot -lp` and `bbot -lf` on your install.
