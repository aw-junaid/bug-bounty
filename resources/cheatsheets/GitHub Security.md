# GitHub Security & OSINT Tools

## Contents
- [Repository Dumping & .git Exposure](#repository-dumping--git-exposure)
- [Secret Detection & Extraction](#secret-detection--extraction)
- [Advanced GitHub OSINT](#advanced-github-osint)
- [Real-World Exploits & Case Studies](#real-world-exploits--case-studies)
- [Manual Git Forensics](#manual-git-forensics)
- [Defensive Measures](#defensive-measures)

---

## Repository Dumping & .git Exposure

### Githack
https://github.com/OwenChia/githack
Exposes .git folder contents through directory traversal vulnerabilities.

### Goop
https://github.com/deletescape/goop
Alternative dumper for exposed .git repositories.

### GitTools (GitDumper + Extractor)
https://github.com/internetwache/GitTools

If we have access to .git folder:
```bash
./gitdumper.sh http://example.com/.git/ /home/user/dump/
./extractor.sh /home/user/dump/ /home/user/dump_extracted
```

**Real-world scenario:** In 2023, a major e-commerce platform exposed their entire .git directory due to misconfigured web server routing. Attackers downloaded the repository and extracted database credentials from configuration files committed 4 years prior.

### GitJacker
https://github.com/liamg/gitjacker

```bash
curl -s "https://raw.githubusercontent.com/liamg/gitjacker/master/scripts/install.sh" | bash
gitjacker target.com
```

This tool automatically reconstructs entire Git repositories from exposed .git folders, including all commit history.

### Git-Scanner
https://github.com/HightechSec/git-scanner
Scans for publicly accessible .git directories across IP ranges and domains.

---

## Secret Detection & Extraction

### Gitleaks
https://github.com/zricethezav/gitleaks

Docker method:
```bash
sudo docker pull zricethezav/gitleaks
sudo docker run --rm --name=gitleaks zricethezav/gitleaks -v -r https://github.com/target/repo.git
```

Local repository scan:
```bash
gitleaks detect /tmp/test -v
```

**Real-world impact:** In 2024, a Fortune 500 company's internal scan using Gitleaks discovered over 300 valid API keys and credentials across their repositories, including production AWS keys that had been exposed for 18+ months.

### TruffleHog
https://github.com/trufflesecurity/trufflehog

Scan a repository:
```bash
trufflehog https://github.com/Plazmaz/leaky-repo
trufflehog --regex --entropy=False https://github.com/Plazmaz/leaky-repo
```

Filesystem scan:
```bash
trufflehog filesystem --directory=/tmp/test
```

**Critical feature:** TruffleHog verifies secrets by actually testing them against the service API, eliminating false positives.

```bash
# Only show verified (confirmed working) secrets
trufflehog --only-verified https://github.com/target/repo.git
```

### GitMiner - Advanced Query Examples

WordPress configuration files with passwords:
```bash
python3 gitminer-v2.0.py -q 'filename:wp-config extension:php FTP_HOST in:file' -m wordpress -c API_KEY -o results.txt
```

Government files containing passwords (Brazilian example):
```bash
python3 gitminer-v2.0.py --query 'extension:php "root" in:file AND "gov.br" in:file' -m senhas -c API_KEY
```

Shadow files in etc path:
```bash
python3 gitminer-v2.0.py --query 'filename:shadow path:etc' -m root -c API_KEY
```

Joomla configuration with passwords:
```bash
python3 gitminer-v2.0.py --query 'filename:configuration extension:php "public password" in:file' -m joomla -c API_KEY
```

### GitHub's Built-in Secret Scanning
Enable in repository: Settings → Code Security → Secret scanning

GitHub Actions integration example:
```yaml
name: Secret Scan
on: [pull_request]
jobs:
  scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: gitleaks/gitleaks-action@v2
```


### Manual Secret Discovery within Repository

After cloning or dumping a repository:
```bash
# View commit history
git log

# Checkout a specific commit containing sensitive files
git checkout f17a07721ab9acec96aef0b1794ee466e516e37a
ls -la
cat .env

# Search commit history for secrets
git log -S "password" --source --all
git grep "API_KEY" $(git rev-list --all)
```

---

## Advanced GitHub OSINT

### GitGot
https://github.com/BishopFox/GitGot

```bash
./gitgot.py --gist -q CompanyName
./gitgot.py -q '"example.com"'
./gitgot.py -q "org:github cats"
```
Semi-automated tool with feedback mechanisms for auditing Git repositories.

### GitRob
https://github.com/michenriksen/gitrob

```bash
gitrob target.com
```
Analyzes public organizations and repositories for sensitive files.

### GitHound
https://github.com/tillson/git-hound

```bash
echo "domain.com" | githound --dig --many-results --languages common-languages.txt --threads 100
```
High-risk OSINT tool for finding sensitive data in GitHub repositories.

### GitGraber
https://github.com/hisxo/gitGraber

```bash
python3 gitGraber.py -k wordlists/keywords.txt -tg
```
Requires API configuration. Searches for unprotected tokens.

### SSH Git Monitoring
https://shhgit.darkport.co.uk/
Real-time monitoring of GitHub for exposed secrets.

### GitHub Search
https://github.com/gwen001/github-search
Advanced GitHub search capabilities for security research.

### VersionShaker
https://github.com/Orange-Cyberdefense/versionshaker
Finds websites from GitHub repositories - identifies domains and subdomains referenced in code.

### GitHub Dorks - Finding Employee Code
Navigate to a company's GitHub profile to view all employees who have public contributions. Each employee's personal repositories may contain company code, credentials, or internal documentation unintentionally exposed.

---

## Real-World Exploits & Case Studies

### CVE-2025-48064: GitHub Desktop NTLM Hash Disclosure

**Vulnerability:** Prior to version 3.4.20-beta3, GitHub Desktop on Windows had a path traversal vulnerability. When viewing a malicious commit in the history view, Git would attempt to resolve UNC paths (network shares), triggering NTLM authentication which leaks:
- Computer name
- Currently signed-in Windows username
- NTLM hash (vulnerable to offline cracking)

**Exploitation:** Attacker creates a repository with a commit containing a file referencing `\\attacker.com\share\file`. When victim views the diff, Windows automatically sends NTLM authentication to attacker-controlled server.

**Fix:** Upgrade to GitHub Desktop 3.4.20 or later. Workaround: only view commits from trusted sources.

### Shai-Hulud Supply Chain Attack (2025)

**Overview:** Malicious npm packages compromised CrowdStrike and nearly 500 other packages in an ongoing campaign named after the Dune sandworms.

**Attack Mechanism:**
The malware installed TruffleHog (a legitimate secret scanner) and executed it to:
- Search host systems for tokens and cloud credentials
- Validate discovered developer and CI credentials
- Create unauthorized GitHub Actions workflows within repositories
- Exfiltrate sensitive data to a hardcoded webhook endpoint

**Code Analysis (Version 1 vs Version 2):**
```javascript
// Version 1 - Direct credential harvesting
modules:{github:{authenticated:F.isAuthenticated(),token:F.getCurrentToken()},
aws:{valid:await te.getCallerIdentity(),secrets:await te.getAllSecretValues()}}

// Version 2 - Improved error handling, targeting GCP instead of Azure
let ue=[];await te.isValid()&&(ue=await te.getAllSecretValues());
modules:{aws:{secrets:ue},gcp:{secrets:de}}
```

**7 Evolution Stages Identified:**
- V1-V2: Added GCP targeting, improved exception handling
- V2-V3: Removed logging, fixed race conditions, earlier GitHub token abuse
- V3-V4: Increased package iteration from 10 to 20 per maintainer
- V4-V5: Removed repo existence check (always creates "Shai-Hulud" repo)
- V5-V6: Removed filesystem scanning (loudest step), increased stealth
- V6-V7: Removed bare-repo filesystem manipulation, retained workflow injection

**Indicators of Compromise:**
- Workflow file named `shai-hulud.yaml` or `shai-hulud-workflow.yml`
- Nearly 700 public repositories titled "Shai-Hulud Migration"
- Unexpected TruffleHog execution in CI/CD pipelines

**Detection:** Sigma rule detects TruffleHog execution with command line arguments including 'Git', 'GitHub', 's3', 'gcs'.

### TruffleHog Malicious Detector Attack (2024)

**Vulnerability Discovery:** Security researchers identified that TruffleHog enables all 700+ detectors by default, and keywords can overlap, causing secrets to be sent to multiple verification endpoints.

**Attack Scenario - "Uare" Detector:**
The legitimate Square detector looks for keyword "EAAA" with regex pattern for 60-character tokens. An attacker registers a fictional "Uare" service. Since "uare" is a substring of "square", any chunk containing Square secrets triggers both detectors.

**Malicious Detector Code:**
```go
// Uare detector - appears legitimate but harvests Square tokens
var secretPat = regexp.MustCompile(detectors.PrefixRegex([]string{"uare"}) + `([a-zA-Z0-9\-\+\=]{60})`)

func (s Scanner) Keywords() []string {
    return []string{"uare"}  // Substring of "square"
}
```

**Impact:** If the malicious detector passes code review (appearing as a legitimate contribution), any organization running TruffleHog will send their Square API tokens to the attacker's verification endpoint.

**Mitigation:** Organizations should review and selectively enable detectors rather than using all 700+ by default.

---

## Manual Git Forensics

### Extracting Blob Content

```bash
# List all objects with their types
git cat-file --batch-check --batch-all-objects | grep blob

# Display content of specific blob
git cat-file -p HASH
```

### Finding Deleted Secrets

```bash
# Search through all commits including deleted files
git log --all --full-history --source -- "*password*"
git log -S "secret" --pickaxe-regex

# Recover deleted file
git rev-list -n 1 HEAD -- filename
git checkout HASH~1 -- filename
```

### Visualizing Commits Online
```
https://github.com/[account]/[repo]/commit/[commit-id]
```

Example:
https://github.com/zricethezav/gitleaks/commit/744ff2f876813fbd34731e6e0d600e1a26e858cf

### Working with Bare Repositories
When dumping a .git folder, you're working with a bare repository:
```bash
# Convert to working repository
git config --unset core.bare
git reset --hard
```

---

## Defensive Measures

### CI/CD Integration

**Dependabot for vulnerability scanning:**
```yaml
# Enable in GitHub settings or add dependabot.yml
version: 2
updates:
  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "daily"
```

**npm audit in CI:**
```yaml
- run: npm audit --audit-level=high
```


### Secret Management Best Practices

1. **Never hardcode secrets** - Use environment variables or secret managers
2. **Rotate secrets every 90 days** - Especially if exposed in any commit
3. **Use GitHub Actions Secrets** for CI/CD credentials
4. **Implement branch protection** requiring passing secret scans before merge
5. **Use AWS Secrets Manager, HashiCorp Vault, or Doppler** for automation

### Gitleaks in Docker (Local Defense)

```bash
docker run -v $(pwd):/tmp/out \
  zricethezav/gitleaks:latest detect \
  --source="/tmp/out" -v --no-color > results.txt
```


### TruffleHog Docker Scan

```bash
docker run --rm -it -v "$(pwd):/out" \
  trufflesecurity/trufflehog:latest git file:///out | \
  sed -r 's/\x1b_[^\x1b]*\x1b[\\]//g; s/\x1B\[[^m]*m//g' > results.txt
```


### False Positive Management
Many secrets match generic patterns (e.g., 32-character alphanumeric strings appear in 24+ detectors). Use `--only-verified` with TruffleHog to confirm secrets actually work before reporting, but be aware this sends the secret to the service's API for validation.
