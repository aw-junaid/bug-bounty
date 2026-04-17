# Bash/Shell Scripting: The Foundation of Security Automation and Linux Mastery

## Introduction: The Command Line as a Force Multiplier

In the pantheon of security tools, few are as universally present yet frequently underestimated as the humble shell script. While Python garners attention for its elegance and extensive libraries, and compiled languages command respect for their performance, Bash and shell scripting remain the **indispensable connective tissue** of security operations. Every Linux system—from the smallest embedded device to the largest cloud server—includes a shell. Every security professional, regardless of specialization, eventually finds themselves at a terminal prompt, stringing together commands to investigate an incident, automate a repetitive task, or quickly prototype a solution.

Shell scripting is the art of bending the command line to your will. It transforms the interactive, one-off commands you type into a terminal into persistent, repeatable, and automatable programs. For the security practitioner, this capability is transformative. The forensic analyst who needs to extract artifacts from hundreds of compromised systems can write a script. The penetration tester who needs to enumerate a network after gaining initial access can write a script. The SOC analyst who needs to parse and enrich alerts can write a script. The system administrator who needs to harden a fresh Linux installation can write a script.

This comprehensive exploration will examine shell scripting through three essential lenses: **System Automation** (the ability to orchestrate complex workflows without human intervention), **Linux Interaction** (the deep integration with the operating system that makes shell scripting uniquely powerful), and **Quick Scripting** (the rapid, iterative development style that makes the shell ideal for security investigations and prototyping). By the end, you will understand not just the syntax of Bash, but the philosophy and power of the command-line environment as a security force multiplier.

---

## Part 1: The Nature of Shell Scripting

### What Is a Shell?

A **shell** is a command-line interpreter—a program that provides a text-based interface between the user and the operating system kernel. When you type a command like `ls -la` or `grep error /var/log/syslog`, the shell reads that input, parses it, locates the appropriate program, executes it with the specified arguments, and displays the output.

But a shell is far more than a simple command launcher. It is a **programming environment** with:
- Variables and data structures (arrays, associative arrays)
- Control flow constructs (if/then/else, for/while/until loops, case statements)
- Functions and modularity
- Input/output redirection and pipelines
- Job control and process management
- Signal handling and traps
- Extensive built-in commands

**Common Shells in Security Contexts:**

| Shell | Path | Context | Notes |
|:---|:---|:---|:---|
| **Bash** | `/bin/bash` | Default on most Linux distributions | Bourne-Again SHell; the de facto standard |
| **Zsh** | `/bin/zsh` | Default on macOS, popular interactive shell | Extended features, better completion |
| **Dash** | `/bin/dash` | Debian/Ubuntu system shell | Minimal, POSIX-compliant, fast startup |
| **PowerShell** | `pwsh` | Windows, cross-platform | Object-oriented, .NET integration |
| **Sh** | `/bin/sh` | POSIX standard, embedded systems | Most portable; often symlinked to Bash or Dash |

For security scripting, **Bash** is the predominant choice due to its ubiquity, extensive features, and backward compatibility with POSIX `sh`. Scripts written in Bash will run on virtually every Linux system encountered in the field.

### The Philosophy of Shell Scripting

Shell scripting embodies a philosophy fundamentally different from general-purpose programming languages:

**1. Composition Over Creation**
Shell scripts rarely implement algorithms from scratch. Instead, they **compose** existing tools—`grep`, `awk`, `sed`, `find`, `cut`, `sort`, `uniq`—into pipelines that achieve complex results. A shell script is less a "program" in the traditional sense and more an **orchestration** of specialized utilities.

**2. Text as Universal Interface**
In the Unix tradition, everything is a file, and most files are text. Shell scripts excel at processing text streams—logs, configuration files, command output—transforming them through successive filtering and manipulation.

**3. Rapid Iteration and Disposability**
Shell scripts are often written for a specific, immediate purpose and discarded. The investment in a shell script is measured in minutes, not hours. This disposability encourages experimentation and adaptation.

**4. Environment Awareness**
Shell scripts execute within the user's environment, inheriting variables, aliases, and permissions. This makes them powerful for system interaction but also requires careful attention to security implications.

**5. Brevity and Density**
Experienced shell scripters can express complex operations in remarkably few characters. A one-liner pipeline can replace dozens of lines in a general-purpose language.

---

## Part 2: System Automation – Orchestrating Security at Scale

System automation is perhaps the most compelling use case for shell scripting in security. The ability to encode complex, multi-step procedures into executable scripts transforms manual, error-prone processes into reliable, repeatable, and auditable operations.

### The Automation Imperative in Security

Security operations face a fundamental scaling problem: the number of systems, alerts, and potential threats grows faster than the security team. Automation is not a luxury—it is the only viable response. Shell scripting provides the most accessible entry point to automation:

- **No Dependencies**: Shell scripts run on any Unix-like system without installing interpreters or libraries
- **Direct System Access**: Shell commands interact directly with files, processes, and kernel interfaces
- **Universal Understanding**: Every Linux administrator can read and modify shell scripts
- **Immediate Feedback**: Scripts can be tested interactively, line by line

### Automated System Hardening

One of the most common security automation tasks is hardening a fresh system—applying security configurations, disabling unnecessary services, and enforcing policies. A shell script can transform a default installation into a security-hardened system in seconds:

```bash
#!/bin/bash
#
# security_hardening.sh - Apply baseline security configuration
# Usage: sudo ./security_hardening.sh
#

set -euo pipefail  # Exit on error, undefined variable, pipe failure

LOG_FILE="/var/log/hardening.log"
exec 2>&1  # Redirect stderr to stdout
exec > >(tee -a "$LOG_FILE")  # Log everything to file and console

echo "[+] Starting system hardening at $(date)"
echo "[+] System: $(uname -a)"

# ---------- SSH Hardening ----------
echo "[+] Hardening SSH configuration..."

SSH_CONFIG="/etc/ssh/sshd_config"

# Backup original config
cp "$SSH_CONFIG" "${SSH_CONFIG}.backup.$(date +%Y%m%d)"

# Apply secure settings
declare -A SSH_SETTINGS=(
    ["PermitRootLogin"]="no"
    ["PasswordAuthentication"]="no"
    ["PubkeyAuthentication"]="yes"
    ["X11Forwarding"]="no"
    ["MaxAuthTries"]="3"
    ["ClientAliveInterval"]="300"
    ["ClientAliveCountMax"]="2"
    ["Protocol"]="2"
    ["AllowTcpForwarding"]="no"
    ["PermitEmptyPasswords"]="no"
)

for key in "${!SSH_SETTINGS[@]}"; do
    value="${SSH_SETTINGS[$key]}"
    if grep -q "^${key}" "$SSH_CONFIG"; then
        sed -i "s/^${key}.*/${key} ${value}/" "$SSH_CONFIG"
    else
        echo "${key} ${value}" >> "$SSH_CONFIG"
    fi
    echo "    Set $key = $value"
done

# ---------- Firewall Configuration ----------
echo "[+] Configuring firewall (iptables)..."

# Flush existing rules
iptables -F
iptables -X
iptables -t nat -F
iptables -t nat -X
iptables -t mangle -F
iptables -t mangle -X

# Default policies
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT

# Allow loopback
iptables -A INPUT -i lo -j ACCEPT

# Allow established connections
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

# Allow SSH (port 22)
iptables -A INPUT -p tcp --dport 22 -m state --state NEW -j ACCEPT

# Save rules (distribution-specific)
if command -v iptables-save >/dev/null; then
    if [ -f /etc/debian_version ]; then
        iptables-save > /etc/iptables/rules.v4
    elif [ -f /etc/redhat-release ]; then
        iptables-save > /etc/sysconfig/iptables
    fi
fi

echo "    Firewall rules applied and saved"

# ---------- System Updates ----------
echo "[+] Updating system packages..."

if command -v apt-get >/dev/null; then
    apt-get update -qq
    apt-get upgrade -y -qq
    apt-get autoremove -y -qq
elif command -v yum >/dev/null; then
    yum update -y -q
    yum autoremove -y -q
fi

echo "    System updated"

# ---------- Audit Logging ----------
echo "[+] Configuring audit logging..."

# Ensure auditd is installed and running
if ! command -v auditd >/dev/null; then
    if command -v apt-get >/dev/null; then
        apt-get install -y auditd
    elif command -v yum >/dev/null; then
        yum install -y audit
    fi
fi

# Add critical audit rules
cat >> /etc/audit/rules.d/security.rules << 'EOF'
# Monitor changes to critical files
-w /etc/passwd -p wa -k identity
-w /etc/shadow -p wa -k identity
-w /etc/group -p wa -k identity
-w /etc/sudoers -p wa -k sudo
-w /etc/ssh/sshd_config -p wa -k ssh

# Monitor privilege escalation
-a always,exit -S execve -F euid=0 -k priv_esc

# Monitor system time changes
-a always,exit -S adjtimex -S settimeofday -k time_change
EOF

# Restart auditd
service auditd restart 2>/dev/null || systemctl restart auditd

echo "    Audit rules configured"

# ---------- User Account Hardening ----------
echo "[+] Hardening user accounts..."

# Lock system accounts
for user in $(awk -F: '($3 < 1000) {print $1}' /etc/passwd); do
    if [[ "$user" != "root" && "$user" != "sync" && "$user" != "shutdown" && "$user" != "halt" ]]; then
        passwd -l "$user" 2>/dev/null
    fi
done

# Set password policies
if [ -f /etc/login.defs ]; then
    sed -i 's/^PASS_MAX_DAYS.*/PASS_MAX_DAYS 90/' /etc/login.defs
    sed -i 's/^PASS_MIN_DAYS.*/PASS_MIN_DAYS 7/' /etc/login.defs
    sed -i 's/^PASS_WARN_AGE.*/PASS_WARN_AGE 14/' /etc/login.defs
fi

echo "    Password policies updated"

# ---------- Disable Unnecessary Services ----------
echo "[+] Disabling unnecessary services..."

SERVICES_TO_DISABLE=(
    "cups"      # Printing
    "avahi-daemon"  # mDNS
    "bluetooth"
    "cups-browsed"
)

for service in "${SERVICES_TO_DISABLE[@]}"; do
    if systemctl is-active --quiet "$service" 2>/dev/null; then
        systemctl stop "$service"
        systemctl disable "$service"
        echo "    Disabled $service"
    fi
done

# ---------- Kernel Hardening ----------
echo "[+] Applying kernel hardening via sysctl..."

cat >> /etc/sysctl.d/99-security.conf << 'EOF'
# IP spoofing protection
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1

# Ignore ICMP redirects
net.ipv4.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0

# Ignore send redirects
net.ipv4.conf.all.send_redirects = 0

# Disable source packet routing
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_source_route = 0

# SYN flood protection
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_syn_retries = 2
net.ipv4.tcp_synack_retries = 2

# Disable IP forwarding
net.ipv4.ip_forward = 0
net.ipv6.conf.all.forwarding = 0

# Kernel hardening
kernel.kptr_restrict = 2
kernel.dmesg_restrict = 1
kernel.perf_event_paranoid = 3
kernel.yama.ptrace_scope = 3
EOF

sysctl -p /etc/sysctl.d/99-security.conf >/dev/null 2>&1

echo "    Kernel parameters applied"

# ---------- File Permissions ----------
echo "[+] Hardening file permissions..."

# Secure critical files
chmod 640 /etc/shadow
chmod 644 /etc/passwd
chmod 640 /etc/group
chmod 440 /etc/sudoers
chmod 600 /etc/ssh/ssh_host_*_key
chmod 644 /etc/ssh/ssh_host_*_key.pub

# Remove SUID/SGID from unnecessary binaries
for binary in /usr/bin/chage /usr/bin/gpasswd /usr/bin/wall /usr/bin/chfn /usr/bin/chsh /usr/bin/newgrp /usr/bin/write /usr/bin/at /usr/bin/pkexec; do
    if [ -f "$binary" ]; then
        chmod -s "$binary" 2>/dev/null && echo "    Removed SUID/SGID from $binary"
    fi
done

echo "[+] Hardening complete at $(date)"
echo "[+] System requires reboot for all changes to take effect"
echo "[+] Log saved to $LOG_FILE"

# ---------- Restart SSH ----------
systemctl restart sshd 2>/dev/null || service ssh restart 2>/dev/null

exit 0
```

This script demonstrates several key automation principles:

- **Idempotence**: The script can be run multiple times without causing errors
- **Logging**: All actions are logged for audit purposes
- **Distribution Awareness**: The script adapts to Debian and Red Hat families
- **Defense in Depth**: Multiple layers of security controls are applied
- **Backup Creation**: Original configurations are preserved

### Automated Incident Response

During a security incident, speed and accuracy are paramount. Shell scripts can automate initial response actions, ensuring consistent evidence collection and containment:

```bash
#!/bin/bash
#
# ir_triage.sh - Incident Response Triage Collection
# Collect volatile data and system state for forensic analysis
#

set -euo pipefail

CASE_ID="${1:-IR-$(date +%Y%m%d-%H%M%S)}"
OUTPUT_DIR="/tmp/ir_collection_${CASE_ID}"
mkdir -p "$OUTPUT_DIR"

echo "[+] Starting IR triage collection - Case: $CASE_ID"
echo "[+] Output directory: $OUTPUT_DIR"

# ---------- System Information ----------
echo "[+] Collecting system information..."

uname -a > "$OUTPUT_DIR/uname.txt"
uptime > "$OUTPUT_DIR/uptime.txt"
date > "$OUTPUT_DIR/collection_time.txt"
cat /etc/os-release > "$OUTPUT_DIR/os_release.txt"
hostname -f > "$OUTPUT_DIR/hostname.txt"

# ---------- Network Connections ----------
echo "[+] Collecting network connection data..."

ss -tulnp > "$OUTPUT_DIR/ss_listening.txt"
ss -tupn > "$OUTPUT_DIR/ss_connections.txt"
netstat -tulnp > "$OUTPUT_DIR/netstat_listening.txt" 2>/dev/null || true
netstat -anp > "$OUTPUT_DIR/netstat_all.txt" 2>/dev/null || true

# ARP cache
arp -a > "$OUTPUT_DIR/arp_cache.txt" 2>/dev/null || ip neigh show > "$OUTPUT_DIR/arp_cache.txt"

# Routing table
route -n > "$OUTPUT_DIR/routing.txt" 2>/dev/null || ip route show > "$OUTPUT_DIR/routing.txt"

# DNS configuration
cat /etc/resolv.conf > "$OUTPUT_DIR/resolv.conf"
cat /etc/hosts > "$OUTPUT_DIR/hosts.txt"

# ---------- Running Processes ----------
echo "[+] Collecting process information..."

ps auxwww > "$OUTPUT_DIR/ps_aux.txt"
ps auxwwwf > "$OUTPUT_DIR/ps_forest.txt"  # Process tree
pstree -p > "$OUTPUT_DIR/pstree.txt" 2>/dev/null || true
top -b -n 1 > "$OUTPUT_DIR/top.txt"
lsof > "$OUTPUT_DIR/lsof.txt" 2>/dev/null || true

# Process-to-network mapping
for pid in $(ps -eo pid --no-headers); do
    if [ -d "/proc/$pid" ]; then
        echo "=== PID: $pid ===" >> "$OUTPUT_DIR/proc_net.txt"
        ls -la "/proc/$pid/exe" 2>/dev/null >> "$OUTPUT_DIR/proc_net.txt"
        cat "/proc/$pid/cmdline" 2>/dev/null | tr '\0' ' ' >> "$OUTPUT_DIR/proc_net.txt"
        echo >> "$OUTPUT_DIR/proc_net.txt"
    fi
done

# ---------- User Activity ----------
echo "[+] Collecting user activity data..."

w > "$OUTPUT_DIR/w.txt"
who > "$OUTPUT_DIR/who.txt"
last > "$OUTPUT_DIR/last.txt"
lastlog > "$OUTPUT_DIR/lastlog.txt" 2>/dev/null || true

# Current user sessions
who -a > "$OUTPUT_DIR/who_all.txt"

# User accounts with login shells
grep -v '/nologin\|/false' /etc/passwd > "$OUTPUT_DIR/interactive_users.txt"

# Sudoers configuration
cat /etc/sudoers > "$OUTPUT_DIR/sudoers.txt" 2>/dev/null || true
ls -la /etc/sudoers.d/ > "$OUTPUT_DIR/sudoers_d.txt" 2>/dev/null || true

# Recent bash history for all users
for user_home in /home/* /root; do
    if [ -f "$user_home/.bash_history" ]; then
        username=$(basename "$user_home")
        cp "$user_home/.bash_history" "$OUTPUT_DIR/bash_history_${username}.txt"
    fi
done

# ---------- Persistence Mechanisms ----------
echo "[+] Checking persistence mechanisms..."

# Cron jobs
for cron_dir in /etc/cron* /var/spool/cron/crontabs; do
    if [ -d "$cron_dir" ]; then
        ls -la "$cron_dir" > "$OUTPUT_DIR/cron_$(basename $cron_dir).txt"
    fi
done

# Systemd timers
systemctl list-timers --all > "$OUTPUT_DIR/systemd_timers.txt" 2>/dev/null || true

# Startup scripts
ls -la /etc/init.d/ > "$OUTPUT_DIR/init_scripts.txt" 2>/dev/null || true
ls -la /etc/rc*.d/ > "$OUTPUT_DIR/rc_scripts.txt" 2>/dev/null || true

# ---------- Log Collection ----------
echo "[+] Collecting recent logs..."

LOG_DIR="$OUTPUT_DIR/logs"
mkdir -p "$LOG_DIR"

# Collect last 1000 lines of critical logs
for logfile in /var/log/syslog /var/log/messages /var/log/secure /var/log/auth.log /var/log/kern.log; do
    if [ -f "$logfile" ]; then
        tail -n 1000 "$logfile" > "$LOG_DIR/$(basename $logfile)_tail.txt"
    fi
done

# Journal logs (systemd)
if command -v journalctl >/dev/null; then
    journalctl -n 1000 > "$LOG_DIR/journalctl_tail.txt"
    journalctl -u ssh --since "24 hours ago" > "$LOG_DIR/journalctl_ssh.txt" 2>/dev/null || true
    journalctl _UID=0 --since "24 hours ago" > "$LOG_DIR/journalctl_root.txt" 2>/dev/null || true
fi

# ---------- Suspicious Files ----------
echo "[+] Identifying suspicious files..."

# Files modified in last 24 hours
find / -type f -mtime -1 2>/dev/null | head -n 500 > "$OUTPUT_DIR/recently_modified.txt"

# SUID/SGID files
find / -type f \( -perm -4000 -o -perm -2000 \) -ls 2>/dev/null > "$OUTPUT_DIR/suid_sgid_files.txt"

# Hidden files and directories (excluding standard ones)
find / -name ".*" -type d 2>/dev/null | grep -v '^/\.' | head -n 200 > "$OUTPUT_DIR/hidden_dirs.txt"

# Files in /tmp and /dev/shm
ls -la /tmp/ > "$OUTPUT_DIR/tmp_listing.txt"
ls -la /dev/shm/ > "$OUTPUT_DIR/dev_shm_listing.txt" 2>/dev/null || true

# ---------- Loaded Kernel Modules ----------
echo "[+] Collecting kernel module information..."

lsmod > "$OUTPUT_DIR/lsmod.txt"
cat /proc/modules > "$OUTPUT_DIR/proc_modules.txt"

# ---------- Memory Acquisition (if root and tools available) ----------
if [ "$EUID" -eq 0 ]; then
    if command -v avml >/dev/null; then
        echo "[+] Acquiring memory image with avml..."
        avml "$OUTPUT_DIR/memory.lime" 2>/dev/null || echo "    Memory acquisition failed"
    elif command -v fmem >/dev/null; then
        echo "[+] Attempting memory acquisition with fmem..."
        dd if=/dev/fmem of="$OUTPUT_DIR/memory.dd" bs=1M 2>/dev/null || echo "    Memory acquisition failed"
    else
        echo "[!] Memory acquisition tools not available"
    fi
fi

# ---------- Create Archive ----------
echo "[+] Creating archive..."

ARCHIVE_NAME="ir_triage_${CASE_ID}_$(date +%Y%m%d_%H%M%S).tar.gz"
tar -czf "/tmp/${ARCHIVE_NAME}" -C /tmp "ir_collection_${CASE_ID}" 2>/dev/null

# ---------- Calculate Hashes ----------
echo "[+] Calculating hashes..."

md5sum "/tmp/${ARCHIVE_NAME}" > "/tmp/${ARCHIVE_NAME}.md5"
sha256sum "/tmp/${ARCHIVE_NAME}" > "/tmp/${ARCHIVE_NAME}.sha256"

echo "[+] Collection complete!"
echo "[+] Archive: /tmp/${ARCHIVE_NAME}"
echo "[+] Size: $(du -h /tmp/${ARCHIVE_NAME} | cut -f1)"
echo "[+] MD5: $(cat /tmp/${ARCHIVE_NAME}.md5 | cut -d' ' -f1)"
echo "[+] SHA256: $(cat /tmp/${ARCHIVE_NAME}.sha256 | cut -d' ' -f1)"

# Cleanup
rm -rf "$OUTPUT_DIR"

exit 0
```

This incident response script demonstrates:
- **Volatile Data Collection**: Captures information that would be lost on reboot
- **Structured Output**: Organizes artifacts for analysis
- **Non-Destructive**: Only reads data; doesn't modify system state
- **Comprehensive Coverage**: Network, processes, users, persistence, logs
- **Chain of Custody Support**: Hashes for integrity verification

### Automated Log Analysis and Alerting

Security teams face an overwhelming volume of log data. Shell scripts can pre-process logs, extract relevant indicators, and generate alerts:

```bash
#!/bin/bash
#
# log_monitor.sh - Monitor logs for security events
# Usage: ./log_monitor.sh [logfile]
#

set -euo pipefail

LOG_FILE="${1:-/var/log/auth.log}"
ALERT_LOG="/var/log/security_alerts.log"
THRESHOLD_FAILED_SSH=10
THRESHOLD_FAILED_SUDO=5

# Patterns to monitor (extensible)
declare -A PATTERNS=(
    ["Failed password"]="Failed SSH login attempt"
    ["Accepted password"]="Successful SSH login with password"
    ["authentication failure"]="Authentication failure"
    ["sudo.*COMMAND"]="Sudo command execution"
    ["session opened for user root"]="Root session opened"
    ["su:.*authentication failure"]="Failed su attempt"
    ["useradd\|userdel\|usermod"]="User account modification"
    ["POSSIBLE BREAK-IN ATTEMPT"]="Possible break-in attempt detected"
    ["CRON.*CMD"]="Cron job execution"
)

# Function to send alert (customize for your environment)
send_alert() {
    local severity="$1"
    local message="$2"
    local details="$3"
    
    # Log to alert file
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$severity] $message - $details" >> "$ALERT_LOG"
    
    # Console output
    echo -e "\033[0;31m[ALERT]\033[0m $message - $details"
    
    # Send to syslog
    logger -p auth.alert -t "SECURITY_MONITOR" "[$severity] $message - $details"
    
    # Optional: Send email
    # echo "$details" | mail -s "Security Alert: $message" security-team@example.com
    
    # Optional: Slack/Discord webhook
    # curl -X POST -H 'Content-type: application/json' \
    #      --data "{\"text\":\"[$severity] $message - $details\"}" \
    #      https://hooks.slack.com/services/YOUR/WEBHOOK/URL
}

# Function to analyze SSH failures by IP
analyze_ssh_failures() {
    echo "[*] Analyzing SSH failure patterns..."
    
    # Count failed attempts by IP
    grep "Failed password" "$LOG_FILE" 2>/dev/null | \
        awk '{print $(NF-3)}' | \
        sort | uniq -c | sort -rn | \
    while read count ip; do
        if [ "$count" -ge "$THRESHOLD_FAILED_SSH" ]; then
            send_alert "HIGH" \
                "SSH brute force detected" \
                "IP: $ip, Attempts: $count"
            
            # Optional: Auto-block IP
            # iptables -A INPUT -s "$ip" -j DROP
            # echo "Blocked IP: $ip"
        fi
    done
}

# Function to analyze sudo usage
analyze_sudo_usage() {
    echo "[*] Analyzing sudo usage..."
    
    grep "sudo.*COMMAND" "$LOG_FILE" 2>/dev/null | \
        awk -F'COMMAND=' '{print $2}' | \
        sort | uniq -c | sort -rn | \
    while read count command; do
        if [ "$count" -ge "$THRESHOLD_FAILED_SUDO" ]; then
            send_alert "MEDIUM" \
                "High volume of sudo commands" \
                "Command: $command, Count: $count"
        fi
    done
    
    # Check for sudo attempts by non-privileged users
    grep "sudo.*user NOT in sudoers" "$LOG_FILE" 2>/dev/null | \
        awk '{print $6}' | \
        sort | uniq -c | \
    while read count user; do
        send_alert "MEDIUM" \
            "Unauthorized sudo attempt" \
            "User: $user, Attempts: $count"
    done
}

# Function to monitor user creation/deletion
analyze_user_changes() {
    echo "[*] Analyzing user account changes..."
    
    grep -E "useradd|userdel|usermod|groupadd|groupdel" "$LOG_FILE" 2>/dev/null | \
    while read line; do
        send_alert "HIGH" \
            "User/group account modified" \
            "$line"
    done
}

# Function to detect unusual login times
analyze_login_times() {
    echo "[*] Analyzing login patterns..."
    
    # Check for logins between 00:00 and 05:00 (suspicious hours)
    grep "Accepted" "$LOG_FILE" 2>/dev/null | \
        awk '{print $1, $2, $3, $9, $11}' | \
    while read month day time user ip; do
        hour=$(echo "$time" | cut -d: -f1)
        if [ "$hour" -ge 0 ] && [ "$hour" -lt 5 ]; then
            send_alert "LOW" \
                "Login during off-hours" \
                "User: $user, IP: $ip, Time: $time"
        fi
    done
}

# Function to watch for privilege escalation
analyze_priv_esc() {
    echo "[*] Analyzing privilege escalation indicators..."
    
    # Check for SUID exploitation patterns
    grep -E "uid=0|euid=0|gid=0" "$LOG_FILE" 2>/dev/null | \
    while read line; do
        if echo "$line" | grep -qv "CRON"; then  # Exclude cron (noisy)
            send_alert "HIGH" \
                "Possible privilege escalation" \
                "$line"
        fi
    done
}

# Function to tail the log in real-time
watch_realtime() {
    echo "[*] Watching log in real-time. Press Ctrl+C to stop."
    
    tail -n 0 -F "$LOG_FILE" 2>/dev/null | \
    while read line; do
        for pattern in "${!PATTERNS[@]}"; do
            if echo "$line" | grep -q "$pattern"; then
                severity="INFO"
                
                # Adjust severity based on pattern
                case "$pattern" in
                    *"Failed password"*) severity="MEDIUM" ;;
                    *"POSSIBLE BREAK-IN"*) severity="CRITICAL" ;;
                    *"session opened for user root"*) severity="HIGH" ;;
                    *"useradd\|userdel"*) severity="HIGH" ;;
                esac
                
                send_alert "$severity" "${PATTERNS[$pattern]}" "$line"
            fi
        done
    done
}

# Main execution
echo "========================================="
echo "Security Log Monitor"
echo "Monitoring: $LOG_FILE"
echo "Alert Log: $ALERT_LOG"
echo "========================================="

# Check if log file exists
if [ ! -f "$LOG_FILE" ]; then
    echo "Error: Log file $LOG_FILE not found"
    exit 1
fi

# Run analyses
analyze_ssh_failures
analyze_sudo_usage
analyze_user_changes
analyze_login_times
analyze_priv_esc

# Offer real-time monitoring
echo ""
read -p "Start real-time monitoring? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    watch_realtime
fi

exit 0
```

### Scheduled Automation with Cron

Shell scripts reach their full automation potential when scheduled with **cron**, the Unix time-based job scheduler. Security teams use cron for:

- **Daily vulnerability scans**: Run `nmap` or custom scripts on a schedule
- **Log rotation and archival**: Compress and move logs before they fill disk space
- **Automated updates**: Apply security patches during maintenance windows
- **Health checks**: Verify critical services are running and responsive
- **Compliance checks**: Validate configuration against security baselines

**Example Crontab for Security Automation:**

```bash
# /etc/crontab or user crontab (crontab -e)

# Run system hardening check daily at 2 AM
0 2 * * * root /usr/local/bin/security_audit.sh > /var/log/security_audit.log 2>&1

# Check for rootkits weekly (Sunday at 3 AM)
0 3 * * 0 root /usr/local/bin/chkrootkit.sh && /usr/local/bin/rkhunter --check --cronjob

# Rotate and archive security logs daily at midnight
0 0 * * * root /usr/local/bin/log_rotation.sh

# Update ClamAV signatures daily at 4 AM
0 4 * * * root freshclam --quiet

# Monitor disk usage for potential log flooding (every hour)
0 * * * * root /usr/local/bin/check_disk_space.sh

# Verify integrity of critical files (every 30 minutes)
*/30 * * * * root /usr/local/bin/integrity_check.sh

# Sync threat intelligence feeds (every 6 hours)
0 */6 * * * root /usr/local/bin/sync_threat_feeds.sh

# Generate weekly security report (Monday at 7 AM)
0 7 * * 1 root /usr/local/bin/weekly_security_report.sh | mail -s "Weekly Security Report" security-team@example.com
```

---

## Part 3: Linux Interaction – The Deep Integration Advantage

Shell scripting's unique power in security comes from its **direct, unmediated access to the Linux operating system**. Unlike higher-level languages that abstract away system details, shell scripts operate at the same level as interactive terminal commands. This deep integration enables security tasks that would be cumbersome or impossible in other languages.

### File System Mastery

Security investigations revolve around the file system—finding suspicious files, analyzing permissions, tracking changes, and recovering deleted data. Shell scripts provide unparalleled file system manipulation capabilities:

**Finding World-Writable Files (Privilege Escalation Vector):**

```bash
#!/bin/bash
# find_world_writable.sh - Locate security-sensitive world-writable files

echo "[+] Scanning for world-writable files..."

# Find world-writable files, exclude pseudo-filesystems
find / -type f -perm -0002 \
    ! -path "/proc/*" \
    ! -path "/sys/*" \
    ! -path "/dev/*" \
    ! -path "/run/*" \
    -ls 2>/dev/null | \
    tee /tmp/world_writable_files.txt

# Find world-writable directories
echo "[+] Scanning for world-writable directories..."
find / -type d -perm -0002 \
    ! -path "/proc/*" \
    ! -path "/sys/*" \
    ! -path "/dev/*" \
    ! -path "/run/*" \
    ! -path "/tmp/*" \
    ! -path "/var/tmp/*" \
    -ls 2>/dev/null | \
    tee /tmp/world_writable_dirs.txt

# Check for writable files in system directories
echo "[+] Checking system binary directories..."
for dir in /bin /sbin /usr/bin /usr/sbin /usr/local/bin; do
    find "$dir" -type f -writable -ls 2>/dev/null
done
```

**Finding Files Modified During Incident Window:**

```bash
#!/bin/bash
# find_modified.sh - Find files modified within specified time window
# Usage: ./find_modified.sh [days] [start_directory]

DAYS="${1:-1}"
START_DIR="${2:-/}"

echo "[+] Finding files modified in the last $DAYS day(s)..."

# Files modified in last N days
find "$START_DIR" -type f -mtime "-$DAYS" \
    -printf "%T@ %Tc %p\n" 2>/dev/null | \
    sort -rn | \
    head -n 1000 | \
    tee "/tmp/recently_modified_${DAYS}days.txt"

# Files accessed in last N days (might indicate exfiltration)
echo "[+] Finding files accessed in the last $DAYS day(s)..."
find "$START_DIR" -type f -atime "-$DAYS" \
    -printf "%A@ %Ac %p\n" 2>/dev/null | \
    sort -rn | \
    head -n 500 | \
    tee "/tmp/recently_accessed_${DAYS}days.txt"

# Files with changed metadata (permissions, ownership)
echo "[+] Finding files with changed metadata..."
find "$START_DIR" -type f -ctime "-$DAYS" \
    -printf "%C@ %Cc %p\n" 2>/dev/null | \
    sort -rn | \
    head -n 500 | \
    tee "/tmp/recently_changed_${DAYS}days.txt"
```

**File Integrity Monitoring:**

```bash
#!/bin/bash
# integrity_check.sh - Monitor critical files for unauthorized changes

BASELINE_DIR="/var/lib/integrity_baseline"
mkdir -p "$BASELINE_DIR"

# Files to monitor
CRITICAL_FILES=(
    "/etc/passwd"
    "/etc/shadow"
    "/etc/group"
    "/etc/sudoers"
    "/etc/ssh/sshd_config"
    "/etc/crontab"
    "/etc/hosts"
    "/etc/resolv.conf"
    "/bin/ls"
    "/bin/ps"
    "/bin/netstat"
    "/usr/bin/ssh"
)

calculate_hash() {
    sha256sum "$1" 2>/dev/null | cut -d' ' -f1
}

# Initialize baseline if not exists
if [ ! -f "$BASELINE_DIR/manifest.txt" ]; then
    echo "[+] Initializing baseline..."
    for file in "${CRITICAL_FILES[@]}"; do
        if [ -f "$file" ]; then
            hash=$(calculate_hash "$file")
            echo "$file:$hash" >> "$BASELINE_DIR/manifest.txt"
        fi
    done
    echo "[+] Baseline created"
    exit 0
fi

# Check against baseline
echo "[+] Checking file integrity..."
CHANGES_DETECTED=0

while IFS=':' read -r file expected_hash; do
    if [ -f "$file" ]; then
        current_hash=$(calculate_hash "$file")
        if [ "$current_hash" != "$expected_hash" ]; then
            echo "  [WARNING] $file has been modified!"
            echo "    Expected: $expected_hash"
            echo "    Current:  $current_hash"
            
            # Log to syslog
            logger -p auth.alert -t "INTEGRITY_CHECK" "File modified: $file"
            CHANGES_DETECTED=1
        fi
    else
        echo "  [WARNING] $file is missing!"
        logger -p auth.alert -t "INTEGRITY_CHECK" "File missing: $file"
        CHANGES_DETECTED=1
    fi
done < "$BASELINE_DIR/manifest.txt"

if [ $CHANGES_DETECTED -eq 0 ]; then
    echo "[+] No integrity violations detected"
fi
```

### Process and Network Investigation

Shell scripts excel at gathering and correlating process and network information—essential for detecting malware, backdoors, and active intrusions:

**Process-to-Port Mapping:**

```bash
#!/bin/bash
# process_port_map.sh - Show which process is listening on which port

echo "PORT      PID    PROCESS"
echo "========  =====  ======================================="

# Method 1: Using ss (modern)
if command -v ss >/dev/null; then
    ss -tulnp 2>/dev/null | \
    while read line; do
        if echo "$line" | grep -q "LISTEN"; then
            port=$(echo "$line" | awk '{print $5}' | rev | cut -d: -f1 | rev)
            pid=$(echo "$line" | grep -o "pid=[0-9]*" | cut -d= -f2)
            if [ -n "$pid" ]; then
                process=$(ps -p "$pid" -o comm= 2>/dev/null)
                printf "%-8s  %-6s %s\n" "$port" "$pid" "$process"
            fi
        fi
    done
fi

# Method 2: Using lsof (fallback)
if command -v lsof >/dev/null; then
    lsof -i -P -n 2>/dev/null | grep LISTEN | \
    while read line; do
        process=$(echo "$line" | awk '{print $1}')
        pid=$(echo "$line" | awk '{print $2}')
        port=$(echo "$line" | awk '{print $9}' | rev | cut -d: -f1 | rev)
        printf "%-8s  %-6s %s\n" "$port" "$pid" "$process"
    done
fi
```

**Detecting Hidden Processes:**

```bash
#!/bin/bash
# detect_hidden_procs.sh - Compare /proc with ps output to find hidden processes

echo "[+] Checking for hidden processes..."

# Get PIDs from ps
ps_pids=$(ps -eo pid --no-headers | tr -d ' ' | sort)

# Get PIDs from /proc
proc_pids=$(ls -d /proc/[0-9]* 2>/dev/null | sed 's/\/proc\///' | sort)

# Compare
hidden_in_ps=$(comm -13 <(echo "$ps_pids") <(echo "$proc_pids"))
hidden_in_proc=$(comm -23 <(echo "$ps_pids") <(echo "$proc_pids"))

if [ -n "$hidden_in_ps" ]; then
    echo "  [WARNING] PIDs in /proc but not in ps (possible rootkit):"
    for pid in $hidden_in_ps; do
        echo "    PID: $pid"
        cat "/proc/$pid/cmdline" 2>/dev/null | tr '\0' ' ' && echo
    done
fi

if [ -n "$hidden_in_proc" ]; then
    echo "  [INFO] PIDs in ps but not in /proc (normal for short-lived processes)"
fi
```

**Network Connection Profiling:**

```bash
#!/bin/bash
# network_profile.sh - Comprehensive network connection analysis

echo "========================================="
echo "Network Connection Profile"
echo "Generated: $(date)"
echo "========================================="

# Summary statistics
total_connections=$(ss -an | wc -l)
established=$(ss -an | grep -c ESTAB)
listen=$(ss -an | grep -c LISTEN)
time_wait=$(ss -an | grep -c TIME-WAIT)

echo "Total Connections: $total_connections"
echo "Established:       $established"
echo "Listening:         $listen"
echo "TIME-WAIT:         $time_wait"
echo ""

# Outbound connections by process
echo "Top Outbound Connections by Process:"
ss -tupn state established 2>/dev/null | \
    grep -v "127.0.0.1" | \
    awk '{print $NF}' | \
    grep -o 'pid=[0-9]*' | \
    cut -d= -f2 | \
    sort | uniq -c | sort -rn | head -n 10 | \
while read count pid; do
    process=$(ps -p "$pid" -o comm= 2>/dev/null)
    echo "  $count connections: $process (PID: $pid)"
done
echo ""

# Foreign IPs with most connections
echo "Top Remote IPs:"
ss -tun state established 2>/dev/null | \
    awk '{print $5}' | \
    cut -d: -f1 | \
    grep -v "127.0.0.1" | \
    sort | uniq -c | sort -rn | head -n 10 | \
while read count ip; do
    echo "  $count connections: $ip"
done
echo ""

# Listening services
echo "Listening Services:"
ss -tuln 2>/dev/null | grep LISTEN | \
    awk '{print $5}' | \
    sort -u | \
while read addr; do
    echo "  $addr"
done
echo ""

# Check for unusual outbound ports
echo "Checking for connections on unusual ports..."
SUSPICIOUS_PORTS="4444 5555 6666 6667 7777 8888 9999 31337 1337"
for port in $SUSPICIOUS_PORTS; do
    if ss -tun 2>/dev/null | grep -q ":$port "; then
        echo "  [WARNING] Connection on suspicious port $port detected!"
        ss -tunp 2>/dev/null | grep ":$port "
    fi
done
```

### User and Authentication Analysis

Shell scripts can parse authentication logs, track user activity, and identify compromised accounts:

**User Login Analysis:**

```bash
#!/bin/bash
# user_analysis.sh - Analyze user login patterns and anomalies

echo "========================================="
echo "User Activity Analysis"
echo "========================================="

# Users with active sessions
echo "[+] Currently logged in users:"
who -u | while read user tty date time pid host; do
    echo "  $user on $tty from ${host:-local} since $date $time"
done
echo ""

# Recent logins (last 24 hours)
echo "[+] Logins in last 24 hours:"
last -i | head -n 20 | while read user tty ip date time duration; do
    if [[ "$user" != "reboot" && "$user" != "wtmp" && -n "$user" ]]; then
        echo "  $user from ${ip:-local} at $date $time"
    fi
done
echo ""

# Failed login attempts by user
echo "[+] Failed login attempts by user:"
if [ -f /var/log/auth.log ]; then
    grep "Failed password" /var/log/auth.log 2>/dev/null | \
        awk '{print $9}' | sort | uniq -c | sort -rn | head -n 10 | \
    while read count user; do
        echo "  $count failed attempts: $user"
    done
elif [ -f /var/log/secure ]; then
    grep "Failed password" /var/log/secure 2>/dev/null | \
        awk '{print $9}' | sort | uniq -c | sort -rn | head -n 10 | \
    while read count user; do
        echo "  $count failed attempts: $user"
    done
fi
echo ""

# Root login attempts
echo "[+] Root authentication attempts:"
if [ -f /var/log/auth.log ]; then
    grep "ROOT" /var/log/auth.log 2>/dev/null | tail -n 10
elif [ -f /var/log/secure ]; then
    grep "root" /var/log/secure 2>/dev/null | tail -n 10
fi
echo ""

# New user accounts (last 30 days)
echo "[+] Recently created user accounts (last 30 days):"
find /etc -name passwd -newermt "30 days ago" -ls 2>/dev/null
awk -F: '$3 >= 1000 && $3 < 65534 {print $1}' /etc/passwd | \
while read user; do
    if [ -d "/home/$user" ]; then
        home_age=$(stat -c %Y "/home/$user" 2>/dev/null)
        now=$(date +%s)
        age_days=$(( (now - home_age) / 86400 ))
        if [ "$age_days" -lt 30 ]; then
            echo "  $user (home directory $age_days days old)"
        fi
    fi
done
```

**Password Policy Audit:**

```bash
#!/bin/bash
# password_audit.sh - Check password policy compliance

echo "========================================="
echo "Password Policy Audit"
echo "========================================="

# Check password aging settings
echo "[+] Password aging settings (/etc/login.defs):"
grep -E "^PASS_MAX_DAYS|^PASS_MIN_DAYS|^PASS_WARN_AGE" /etc/login.defs 2>/dev/null
echo ""

# Check PAM password quality settings
echo "[+] PAM password quality settings:"
if [ -f /etc/pam.d/common-password ]; then
    grep "pam_pwquality.so" /etc/pam.d/common-password 2>/dev/null
elif [ -f /etc/pam.d/system-auth ]; then
    grep "pam_pwquality.so" /etc/pam.d/system-auth 2>/dev/null
fi
echo ""

# Check for empty passwords
echo "[+] Accounts with empty passwords:"
awk -F: '($2 == "") {print $1}' /etc/shadow 2>/dev/null
echo ""

# Check for UID 0 accounts (root-equivalent)
echo "[+] Accounts with UID 0:"
awk -F: '($3 == 0) {print $1}' /etc/passwd
echo ""

# Check for accounts with no password expiration
echo "[+] Accounts with no password expiration:"
awk -F: '($2 != "*" && $2 != "!" && $5 == "") {print $1}' /etc/shadow 2>/dev/null | head -n 10
```

### System Hardening Verification

Shell scripts can verify that security controls are properly configured and alert on deviations:

```bash
#!/bin/bash
# security_audit.sh - Verify system security configuration

PASSED=0
FAILED=0
WARNING=0

check() {
    local description="$1"
    local command="$2"
    local expected="$3"
    
    result=$(eval "$command" 2>/dev/null)
    
    if [[ "$result" == "$expected" ]]; then
        echo "  [PASS] $description"
        ((PASSED++))
    else
        echo "  [FAIL] $description (Expected: $expected, Got: $result)"
        ((FAILED++))
    fi
}

check_warn() {
    local description="$1"
    local command="$2"
    local expected="$3"
    
    result=$(eval "$command" 2>/dev/null)
    
    if [[ "$result" == "$expected" ]]; then
        echo "  [PASS] $description"
        ((PASSED++))
    else
        echo "  [WARN] $description (Got: $result, Recommended: $expected)"
        ((WARNING++))
    fi
}

echo "========================================="
echo "Security Configuration Audit"
echo "========================================="

echo "[+] SSH Configuration:"
check "Root login disabled" \
    "grep '^PermitRootLogin' /etc/ssh/sshd_config | awk '{print \$2}'" \
    "no"

check "Password authentication disabled" \
    "grep '^PasswordAuthentication' /etc/ssh/sshd_config | awk '{print \$2}'" \
    "no"

check "Protocol 2 only" \
    "grep '^Protocol' /etc/ssh/sshd_config | awk '{print \$2}'" \
    "2"

echo ""
echo "[+] Kernel Security:"
check_warn "ASLR enabled" \
    "cat /proc/sys/kernel/randomize_va_space" \
    "2"

check_warn "ExecShield enabled" \
    "cat /proc/sys/kernel/exec-shield 2>/dev/null || echo 1" \
    "1"

check_warn "Kernel pointer restriction" \
    "cat /proc/sys/kernel/kptr_restrict" \
    "2"

check_warn "dmesg restriction" \
    "cat /proc/sys/kernel/dmesg_restrict" \
    "1"

echo ""
echo "[+] Network Security:"
check_warn "IP forwarding disabled" \
    "cat /proc/sys/net/ipv4/ip_forward" \
    "0"

check_warn "ICMP redirects disabled" \
    "cat /proc/sys/net/ipv4/conf/all/accept_redirects" \
    "0"

check_warn "Source routing disabled" \
    "cat /proc/sys/net/ipv4/conf/all/accept_source_route" \
    "0"

check_warn "SYN cookies enabled" \
    "cat /proc/sys/net/ipv4/tcp_syncookies" \
    "1"

echo ""
echo "[+] File Permissions:"
check "Shadow file permissions" \
    "stat -c '%a' /etc/shadow 2>/dev/null" \
    "640"

check "Passwd file permissions" \
    "stat -c '%a' /etc/passwd 2>/dev/null" \
    "644"

check "Sudoers permissions" \
    "stat -c '%a' /etc/sudoers 2>/dev/null" \
    "440"

echo ""
echo "[+] Services:"
for service in telnet rsh rlogin rexec finger; do
    if systemctl is-active --quiet "$service" 2>/dev/null; then
        echo "  [FAIL] $service is running"
        ((FAILED++))
    else
        echo "  [PASS] $service is not running"
        ((PASSED++))
    fi
done

echo ""
echo "========================================="
echo "Audit Summary:"
echo "  Passed:  $PASSED"
echo "  Failed:  $FAILED"
echo "  Warnings: $WARNING"
echo "========================================="
```

---

## Part 4: Quick Scripting – The Art of the Security One-Liner

Perhaps the most distinctive characteristic of shell scripting is its support for **rapid, disposable scripting**. Security professionals often work in time-constrained, high-pressure situations where writing a full program is impractical. The shell enables complex operations to be expressed as dense one-liners that can be typed directly into a terminal or saved as minimal scripts.

### The Power of Pipelines

The Unix pipeline (`|`) is the fundamental composition operator of shell scripting. It allows the output of one command to become the input of another, creating powerful data processing chains:

**Finding the Top 10 IPs Attacking SSH:**

```bash
grep "Failed password" /var/log/auth.log | \
    awk '{print $(NF-3)}' | \
    sort | uniq -c | sort -rn | head -10
```

This single line:
1. Extracts all failed SSH password lines from the auth log
2. Isolates the IP address field (varies by log format)
3. Sorts the IPs to group identical addresses
4. Counts unique occurrences (`uniq -c`)
5. Sorts by count in descending numeric order
6. Displays the top 10

**Extracting Unique User-Agents from Web Logs:**

```bash
awk -F'"' '{print $6}' /var/log/apache2/access.log | \
    sort -u | \
    grep -i "bot\|crawler\|spider"
```

**Finding Large Files Created Recently:**

```bash
find / -type f -size +100M -mtime -7 -exec ls -lh {} \; 2>/dev/null | \
    awk '{print $5, $9}' | \
    sort -rh
```

**Live Network Traffic Analysis for Suspicious Ports:**

```bash
tcpdump -i eth0 -nn -l 2>/dev/null | \
    grep -E ":(4444|5555|6666|31337)" | \
    while read line; do echo "[ALERT] Suspicious traffic: $line"; done
```

### Command Substitution Magic

Command substitution (`$(...)` or backticks) allows the output of a command to be used as arguments or variable values:

**Quick Port Scan Using Built-in Tools:**

```bash
for port in $(seq 1 1024); do
    timeout 1 bash -c "echo >/dev/tcp/localhost/$port" 2>/dev/null && \
        echo "Port $port is open"
done
```

**Find and Kill Processes Older Than 1 Hour:**

```bash
kill -9 $(ps -eo pid,etimes,comm | \
    awk '$2 > 3600 && $3 ~ /suspicious_process/ {print $1}')
```

**Backup Files Modified in Last 24 Hours:**

```bash
tar -czf backup_$(date +%Y%m%d).tar.gz \
    $(find . -type f -mtime -1)
```

### The Security Professional's One-Liner Toolkit

These one-liners represent common security tasks compressed into single command lines:

**Network Reconnaissance:**

```bash
# Quick ping sweep
for i in {1..254}; do ping -c 1 -W 1 192.168.1.$i | grep "64 bytes" & done

# DNS enumeration with dig
for sub in $(cat subdomains.txt); do dig +short $sub.example.com; done | grep -v "^$"

# Find all live hosts with ARP scan
arp-scan --localnet 2>/dev/null | grep -E '([0-9a-f]{2}:){5}[0-9a-f]{2}'

# Quick service enumeration with netcat
for port in 22 80 443 8080 8443; do nc -zv target.com $port 2>&1 | grep succeeded; done
```

**Log Analysis:**

```bash
# Count HTTP response codes
awk '{print $9}' /var/log/apache2/access.log | sort | uniq -c | sort -rn

# Find 404s from a specific IP
grep "192.168.1.100" /var/log/apache2/access.log | grep " 404 "

# Extract all unique IPs that hit a specific URL
grep "/admin" /var/log/apache2/access.log | awk '{print $1}' | sort -u

# Find requests with suspicious User-Agents
grep -E "(sqlmap|nikto|nessus|nmap)" /var/log/apache2/access.log
```

**System Forensics:**

```bash
# Find all files owned by a specific user
find / -user username -ls 2>/dev/null

# Find files with SUID bit set
find / -perm -4000 -ls 2>/dev/null

# List all running services
systemctl list-units --type=service --state=running

# Show process tree with command lines
ps auxf | grep -v "\["

# Find recently modified binaries
find /usr/bin /bin /sbin -type f -mtime -7 -ls 2>/dev/null
```

**Malware Detection Quick Checks:**

```bash
# Check for processes connecting to unusual ports
netstat -tupn 2>/dev/null | grep -v "127.0.0.1" | grep ESTABLISHED

# Look for hidden directories in /tmp
ls -la /tmp/ | grep "^d.*\..*" | grep -v "^d.*\.\.\?$"

# Check crontab for all users
for user in $(cut -f1 -d: /etc/passwd); do echo "=== $user ==="; crontab -u $user -l 2>/dev/null; done

# Find all shell scripts in web directories
find /var/www -type f -name "*.sh" -o -name "*.pl" -o -name "*.py" 2>/dev/null
```

### Scripting on the Fly: Heredocs and Here-Strings

When you need to write a slightly longer script but don't want to create a file, **heredocs** allow multi-line scripts to be passed to an interpreter:

```bash
# Run a Python script without creating a file
python3 << 'EOF'
import socket
import sys

target = sys.argv[1] if len(sys.argv) > 1 else "localhost"
for port in [22, 80, 443, 3306, 5432]:
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(1)
    result = sock.connect_ex((target, port))
    if result == 0:
        print(f"Port {port}: OPEN")
    sock.close()
EOF
```

**Here-strings** provide a concise way to pass strings as input:

```bash
# Process a list without a temporary file
while read ip; do
    ping -c 1 "$ip" >/dev/null && echo "$ip is up"
done <<< "192.168.1.1
192.168.1.10
192.168.1.20
192.168.1.100"
```

### Aliases and Functions: Extending the Shell

Security professionals often define custom aliases and functions in their `.bashrc` to streamline common tasks:

```bash
# ~/.bashrc security aliases

# Quick network recon
alias myip='curl -s ifconfig.me'
alias listening='ss -tulnp 2>/dev/null | grep LISTEN'
alias connections='ss -tunp 2>/dev/null | grep ESTAB'

# Log analysis shortcuts
alias auth_fails='grep "Failed password" /var/log/auth.log | tail -20'
alias sudo_usage='grep "sudo.*COMMAND" /var/log/auth.log | tail -20'

# Process investigation
alias proctree='ps auxf'
alias procs_by_cpu='ps aux --sort=-%cpu | head -20'
alias procs_by_mem='ps aux --sort=-%mem | head -20'

# File system security
alias find_world_writable='find / -type f -perm -0002 -ls 2>/dev/null'
alias find_suid='find / -perm -4000 -ls 2>/dev/null'

# Quick port scan function
portscan() {
    local target=$1
    local ports=${2:-"1-1024"}
    nc -zv "$target" $ports 2>&1 | grep succeeded
}

# Extract IPs from log file
extract_ips() {
    grep -oE '\b([0-9]{1,3}\.){3}[0-9]{1,3}\b' "$1" | sort -u
}

# Quick hash calculation
hashfile() {
    sha256sum "$1" | cut -d' ' -f1
}

# Monitor log in real-time with highlighting
watchlog() {
    tail -f "$1" | grep --color=always -E "ERROR|FAIL|WARN|ALERT|CRITICAL"
}
```

---

## Part 5: Advanced Shell Scripting Techniques for Security

Beyond basic automation and one-liners, shell scripting offers sophisticated capabilities that security professionals can leverage.

### Signal Trapping for Cleanup

When writing security tools that create temporary files or modify system state, it's essential to clean up even if the script is interrupted:

```bash
#!/bin/bash
# secure_temp.sh - Demonstrate secure temporary file handling

# Create secure temporary directory
TEMP_DIR=$(mktemp -d)
TEMP_FILES=()

# Cleanup function
cleanup() {
    echo "[+] Cleaning up temporary files..."
    
    # Remove individual temp files
    for file in "${TEMP_FILES[@]}"; do
        rm -f "$file" 2>/dev/null
    done
    
    # Remove temp directory
    rm -rf "$TEMP_DIR" 2>/dev/null
    
    # Restore terminal settings if modified
    stty sane 2>/dev/null
    
    echo "[+] Cleanup complete"
    exit 0
}

# Trap signals
trap cleanup EXIT INT TERM HUP

# Create temporary files securely
create_temp_file() {
    local temp_file=$(mktemp -p "$TEMP_DIR" tmp.XXXXXX)
    TEMP_FILES+=("$temp_file")
    echo "$temp_file"
}

# Usage
echo "[+] Using temp dir: $TEMP_DIR"

data_file=$(create_temp_file)
echo "Sensitive data" > "$data_file"

log_file=$(create_temp_file)
echo "Processing log..." > "$log_file"

# Script continues...
echo "[+] Press Ctrl+C to test cleanup..."
sleep 60

# Normal exit triggers cleanup via trap
```

### Parallel Execution for Speed

Security tools often need to perform many independent operations—scanning ports, checking URLs, processing files. Parallel execution dramatically reduces runtime:

```bash
#!/bin/bash
# parallel_scan.sh - Concurrent port scanning

TARGET="$1"
MAX_PROCS=50
TIMEOUT=2

if [ -z "$TARGET" ]; then
    echo "Usage: $0 <target> [start_port] [end_port]"
    exit 1
fi

START_PORT="${2:-1}"
END_PORT="${3:-1024}"

echo "[+] Scanning $TARGET ports $START_PORT-$END_PORT"
echo "[+] Using up to $MAX_PROCS parallel processes"
echo ""

# Function to scan a single port
scan_port() {
    local port=$1
    timeout $TIMEOUT bash -c "echo >/dev/tcp/$TARGET/$port" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "Port $port: OPEN"
    fi
}

# Export function and variables for parallel execution
export -f scan_port
export TARGET TIMEOUT

# Use xargs for parallel execution
seq "$START_PORT" "$END_PORT" | \
    xargs -P "$MAX_PROCS" -I {} bash -c 'scan_port "$@"' _ {}

echo ""
echo "[+] Scan complete"
```

**Parallel Web Checker:**

```bash
#!/bin/bash
# parallel_web_check.sh - Check multiple URLs concurrently

URLS_FILE="${1:-urls.txt}"
MAX_CONCURRENT=20

check_url() {
    local url="$1"
    local response=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 --max-time 10 "$url" 2>/dev/null)
    echo "$url : $response"
}

export -f check_url

cat "$URLS_FILE" | \
    xargs -P "$MAX_CONCURRENT" -I {} bash -c 'check_url "$@"' _ {}
```

### Associative Arrays for Data Correlation

Bash 4+ supports associative arrays (hash maps), enabling complex data correlation in pure shell:

```bash
#!/bin/bash
# correlate_data.sh - Correlate network connections with process information

declare -A pid_to_process
declare -A pid_to_connections
declare -A ip_to_count

# Build process lookup table
while read pid process; do
    pid_to_process["$pid"]="$process"
done < <(ps -eo pid,comm --no-headers)

# Build connection data
while read proto recv send local remote state pid; do
    if [ -n "$pid" ] && [ "$pid" != "0" ]; then
        # Extract IP from remote address
        remote_ip=$(echo "$remote" | cut -d: -f1)
        
        # Count connections by IP
        ((ip_to_count["$remote_ip"]++))
        
        # Track connections per PID
        if [ -z "${pid_to_connections[$pid]}" ]; then
            pid_to_connections["$pid"]="$remote_ip"
        else
            pid_to_connections["$pid"]="${pid_to_connections[$pid]},$remote_ip"
        fi
    fi
done < <(ss -tunp 2>/dev/null | tail -n +2)

# Report findings
echo "========================================="
echo "Process Network Activity Report"
echo "========================================="

for pid in "${!pid_to_connections[@]}"; do
    process="${pid_to_process[$pid]:-UNKNOWN}"
    echo ""
    echo "PID: $pid ($process)"
    echo "Connections to:"
    
    # Split comma-separated IPs
    IFS=',' read -ra ips <<< "${pid_to_connections[$pid]}"
    for ip in "${ips[@]}"; do
        count="${ip_to_count[$ip]}"
        echo "  - $ip ($count total connection(s))"
    done
done
```

### Co-Processes for Interactive Control

Bash coprocesses enable bidirectional communication with subprocesses, useful for controlling interactive security tools:

```bash
#!/bin/bash
# interact_with_tool.sh - Control an interactive tool programmatically

# Start netcat as a coprocess
coproc NC { nc -v target.com 80; }

# Send HTTP request
echo -e "GET / HTTP/1.0\r\nHost: target.com\r\n\r\n" >&${NC[1]}

# Read response
while read -t 2 -u ${NC[0]} line; do
    echo "Received: $line"
done

# Close the coprocess
kill $NC_PID 2>/dev/null
```

### Secure Scripting Practices

Security scripts must themselves be secure. Key practices include:

**1. Use `set -euo pipefail`:**

```bash
#!/bin/bash
set -euo pipefail
# -e: Exit on error
# -u: Exit on undefined variable
# -o pipefail: Pipeline fails if any command fails
```

**2. Quote All Variable Expansions:**

```bash
# BAD (word splitting, globbing)
if [ $filename = "test" ]; then

# GOOD
if [ "$filename" = "test" ]; then
```

**3. Use `[[` Instead of `[` for Bash Tests:**

```bash
# [ is POSIX but has limitations
if [ "$a" = "$b" -a "$c" = "$d" ]; then

# [[ is Bash-specific but safer and more featureful
if [[ "$a" == "$b" && "$c" == "$d" ]]; then
```

**4. Avoid `eval` with User Input:**

```bash
# DANGEROUS
eval "echo $user_input"

# SAFE ALTERNATIVE
echo "$user_input"
```

**5. Use Temporary Files Securely:**

```bash
# BAD (predictable name, race condition)
TEMP="/tmp/my_temp_file"

# GOOD (unpredictable name, secure creation)
TEMP=$(mktemp) || exit 1
trap 'rm -f "$TEMP"' EXIT
```

**6. Validate Input:**

```bash
#!/bin/bash
# Secure input validation example

validate_ip() {
    local ip="$1"
    local ip_regex='^([0-9]{1,3}\.){3}[0-9]{1,3}$'
    
    if [[ ! "$ip" =~ $ip_regex ]]; then
        echo "Error: Invalid IP address format: $ip" >&2
        return 1
    fi
    
    IFS='.' read -ra octets <<< "$ip"
    for octet in "${octets[@]}"; do
        if ((octet < 0 || octet > 255)); then
            echo "Error: IP octet out of range: $octet" >&2
            return 1
        fi
    done
    
    return 0
}

# Usage
if validate_ip "$1"; then
    echo "Valid IP: $1"
else
    exit 1
fi
```

---

## Part 6: Bash in Modern Security Workflows

Despite the availability of more sophisticated languages, Bash remains integral to modern security workflows.

### CI/CD Security Integration

Shell scripts are the glue that integrates security tools into CI/CD pipelines:

```yaml
# .gitlab-ci.yml security stage
security_scan:
  stage: test
  script:
    - ./scripts/dependency_check.sh
    - ./scripts/secrets_scan.sh
    - ./scripts/container_scan.sh
    - ./scripts/sast_scan.sh
  artifacts:
    reports:
      sast: gl-sast-report.json
```

**Example SAST Runner Script:**

```bash
#!/bin/bash
# run_sast.sh - Execute multiple SAST tools and aggregate results

set -euo pipefail

RESULTS_DIR="sast_results"
mkdir -p "$RESULTS_DIR"

echo "[+] Running Bandit (Python)..."
if [ -f "requirements.txt" ] || [ -f "setup.py" ]; then
    bandit -r . -f json -o "$RESULTS_DIR/bandit.json" 2>/dev/null || true
fi

echo "[+] Running Semgrep..."
semgrep --config=auto --json --output "$RESULTS_DIR/semgrep.json" . 2>/dev/null || true

echo "[+] Running Gitleaks (secrets)..."
gitleaks detect --source . --report-path "$RESULTS_DIR/gitleaks.json" --exit-code 0 2>/dev/null || true

echo "[+] Running Trivy (vulnerabilities)..."
trivy fs --format json --output "$RESULTS_DIR/trivy.json" . 2>/dev/null || true

# Generate summary
echo "========================================="
echo "SAST Scan Summary"
echo "========================================="

for tool in bandit semgrep gitleaks trivy; do
    if [ -f "$RESULTS_DIR/${tool}.json" ]; then
        count=$(jq '.results | length' "$RESULTS_DIR/${tool}.json" 2>/dev/null || echo "0")
        echo "$tool: $count findings"
    fi
done

echo "Results saved to: $RESULTS_DIR"
```

### Docker and Container Security

Shell scripts are essential for container security operations:

```bash
#!/bin/bash
# container_scan.sh - Security scan for Docker containers

IMAGE="$1"

if [ -z "$IMAGE" ]; then
    echo "Usage: $0 <image:tag>"
    exit 1
fi

echo "[+] Scanning image: $IMAGE"

# Trivy vulnerability scan
echo "[+] Running Trivy vulnerability scan..."
trivy image --severity HIGH,CRITICAL --format json --output trivy_scan.json "$IMAGE"

# Docker Scout
echo "[+] Running Docker Scout..."
docker scout cves "$IMAGE" --format json > scout_scan.json 2>/dev/null || true

# Check for secrets in layers
echo "[+] Checking for secrets in layers..."
docker history --no-trunc "$IMAGE" | \
    grep -E "(password|secret|key|token)" && \
    echo "  [WARNING] Potential secrets found in image history"

# Check for running as root
echo "[+] Checking user context..."
if docker inspect "$IMAGE" | jq -r '.[].Config.User' | grep -q "^$\|^root$\|^0"; then
    echo "  [WARNING] Container runs as root"
fi

# Check for exposed ports
echo "[+] Checking exposed ports..."
docker inspect "$IMAGE" | jq -r '.[].Config.ExposedPorts | keys[]' 2>/dev/null

echo "[+] Scan complete"
```

### Kubernetes Security

Shell scripts manage Kubernetes security contexts and automate cluster hardening:

```bash
#!/bin/bash
# k8s_security_audit.sh - Kubernetes cluster security audit

NAMESPACE="${1:-default}"

echo "========================================="
echo "Kubernetes Security Audit"
echo "Namespace: $NAMESPACE"
echo "========================================="

# Check for pods running as root
echo "[+] Pods running as root:"
kubectl get pods -n "$NAMESPACE" -o json | \
    jq -r '.items[] | select(.spec.containers[].securityContext.runAsNonRoot != true) | .metadata.name' 2>/dev/null

# Check for privileged containers
echo "[+] Privileged containers:"
kubectl get pods -n "$NAMESPACE" -o json | \
    jq -r '.items[] | select(.spec.containers[].securityContext.privileged == true) | .metadata.name' 2>/dev/null

# Check for host network usage
echo "[+] Pods using host network:"
kubectl get pods -n "$NAMESPACE" -o json | \
    jq -r '.items[] | select(.spec.hostNetwork == true) | .metadata.name' 2>/dev/null

# Check for host PID/IPC usage
echo "[+] Pods sharing host PID namespace:"
kubectl get pods -n "$NAMESPACE" -o json | \
    jq -r '.items[] | select(.spec.hostPID == true) | .metadata.name' 2>/dev/null

# Check for capabilities
echo "[+] Containers with dangerous capabilities:"
kubectl get pods -n "$NAMESPACE" -o json | \
    jq -r '.items[].spec.containers[] | select(.securityContext.capabilities.add[] | contains("SYS_ADMIN", "NET_ADMIN", "ALL")) | .name' 2>/dev/null

# Check secrets
echo "[+] Secrets in namespace:"
kubectl get secrets -n "$NAMESPACE" -o name

# Check RBAC
echo "[+] ClusterRoleBindings:"
kubectl get clusterrolebindings -o wide | grep -E "$NAMESPACE|system:"

echo "[+] Audit complete"
```

### Cloud Security Automation

Shell scripts interact with cloud provider CLIs for security automation:

```bash
#!/bin/bash
# aws_security_audit.sh - AWS security baseline check

echo "========================================="
echo "AWS Security Audit"
echo "========================================="

# Check S3 bucket public access
echo "[+] Checking S3 bucket public access..."
aws s3 ls | while read date time bucket; do
    if aws s3api get-public-access-block --bucket "$bucket" >/dev/null 2>&1; then
        echo "  [PASS] $bucket: Public access blocked"
    else
        echo "  [WARN] $bucket: No public access block configured"
    fi
done

# Check security groups for open SSH (0.0.0.0/0)
echo "[+] Checking for open SSH (0.0.0.0/0)..."
aws ec2 describe-security-groups \
    --filters Name=ip-permission.from-port,Values=22 Name=ip-permission.cidr,Values='0.0.0.0/0' \
    --query 'SecurityGroups[*].[GroupName,GroupId]' --output text | \
while read name id; do
    echo "  [WARN] $name ($id) allows SSH from anywhere"
done

# Check for unused access keys
echo "[+] Checking for unused IAM access keys..."
aws iam list-users --query 'Users[*].UserName' --output text | \
tr '\t' '\n' | while read user; do
    aws iam list-access-keys --user-name "$user" --query 'AccessKeyMetadata[*].AccessKeyId' --output text | \
    tr '\t' '\n' | while read key; do
        last_used=$(aws iam get-access-key-last-used --access-key-id "$key" --query 'AccessKeyLastUsed.LastUsedDate' --output text)
        if [ "$last_used" = "None" ]; then
            echo "  [WARN] $user has unused access key: $key"
        fi
    done
done

# Check CloudTrail enabled
echo "[+] Checking CloudTrail..."
trails=$(aws cloudtrail describe-trails --query 'trailList[].Name' --output text)
if [ -z "$trails" ]; then
    echo "  [FAIL] No CloudTrail trails configured"
else
    echo "  [PASS] CloudTrail configured"
fi

# Check MFA for root account
echo "[+] Checking root MFA..."
root_mfa=$(aws iam get-account-summary --query 'SummaryMap.AccountMFAEnabled' --output text)
if [ "$root_mfa" = "1" ]; then
    echo "  [PASS] Root MFA enabled"
else
    echo "  [CRITICAL] Root MFA not enabled!"
fi

echo "[+] Audit complete"
```

---

## Part 7: Limitations and When to Move Beyond Bash

While Bash is indispensable, recognizing its limitations is crucial for knowing when to use other tools.

### When Bash Excels

- **File system operations**: Moving, copying, finding files
- **Process management**: Starting, stopping, monitoring processes
- **Text processing pipelines**: Combining grep, awk, sed, cut
- **Glue code**: Orchestrating other tools and scripts
- **Quick prototypes**: Testing ideas before writing robust code
- **System administration**: User management, service control
- **CI/CD pipelines**: Build and deployment automation

### When to Use Python Instead

- **Complex data structures**: Nested dictionaries, objects
- **Long-running applications**: Memory management, error handling
- **API interactions**: REST APIs with authentication, pagination
- **Cross-platform compatibility**: Windows/macOS/Linux without modification
- **Unit testing**: Comprehensive test frameworks
- **Maintainable codebase**: Multi-developer projects
- **Performance-critical parsing**: Large XML/JSON processing

### When to Use Go/Rust Instead

- **Performance requirements**: High-throughput network tools
- **Single-binary distribution**: No dependencies on target system
- **Concurrency**: Complex parallel operations with shared state
- **Embedded systems**: Resource-constrained environments
- **Long-term maintainability**: Large codebases with many contributors

### The Polyglot Reality

The most effective security professionals are polyglot—they use the right tool for the job:

- **Bash** for orchestration and quick tasks
- **Python** for data analysis and API integration
- **Go** for high-performance network tools
- **PowerShell** for Windows environments
- **C** for low-level exploit development

Bash scripts often call Python scripts for complex processing, and Python scripts often shell out for system operations. Understanding the strengths of each language enables effective composition.

---

## Conclusion: Mastering the Command Line

Bash and shell scripting occupy a unique and irreplaceable position in the security professional's toolkit. They provide:

**System Automation**: The ability to encode complex procedures into repeatable, auditable scripts that run on every Unix-like system without dependencies. From hardening a fresh server to collecting forensic evidence during an incident, shell scripts transform manual processes into automated workflows.

**Linux Interaction**: Unparalleled, direct access to the operating system. Shell scripts operate at the same level as interactive commands, enabling deep system inspection, manipulation, and monitoring that higher-level languages abstract away.

**Quick Scripting**: The capacity to express powerful operations in dense one-liners and disposable scripts. In time-critical security situations, the ability to type a pipeline that extracts actionable intelligence from logs or identifies an active threat is invaluable.

The command line is not merely a tool—it is an environment, a philosophy, and a force multiplier. The security professional who masters the shell gains the ability to think and act at the speed of the terminal, to compose solutions from existing utilities, and to automate the mundane while focusing on the complex.

As you continue your security journey, invest time in deepening your shell expertise. Learn the flags to your favorite commands. Study the man pages. Practice writing scripts that solve real problems you encounter. Build your library of aliases, functions, and reusable scripts. The command line rewards investment with compound interest—every new technique multiplies your effectiveness.

In a field where minutes matter and automation is survival, the shell is your most reliable ally.

---

## Further Reading and Resources

**Books:**
- *The Linux Command Line* by William Shotts (Free at linuxcommand.org)
- *Bash Cookbook* by Carl Albing and JP Vossen
- *Shell Scripting: Expert Recipes for Linux, Bash, and More* by Steve Parker
- *Unix and Linux System Administration Handbook* by Evi Nemeth et al.

**Online Resources:**
- **Bash Guide**: mywiki.wooledge.org/BashGuide
- **ShellCheck**: shellcheck.net (Linter for shell scripts)
- **Explainshell**: explainshell.com (Explains command-line arguments)
- **Command Line Fu**: commandlinefu.com (One-liner repository)
- **The Art of Command Line**: github.com/jlevy/the-art-of-command-line

**Essential Man Pages:**
```bash
man bash           # Bash manual
man builtins       # Shell built-in commands
man proc           # /proc filesystem
man signal         # Signal handling
man regex          # Regular expressions
```

**Practice Environments:**
- **OverTheWire Bandit**: overthewire.org/wargames/bandit
- **CMD Challenge**: cmdchallenge.com
- **Terminus**: web.mit.edu/mprat/Public/web/Terminus/Web/main.html
