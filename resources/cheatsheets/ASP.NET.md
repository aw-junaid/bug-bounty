### Threat Analysis: ASP.NET Tracing Information Disclosure (trace.axd)

#### 1. Overview
The `trace.axd` endpoint is a built-in diagnostic feature of Microsoft ASP.NET designed to assist developers in monitoring and debugging application requests. When enabled, it provides a detailed log of the last 50 to 80 requests processed by the server. However, when left active in a production environment, this feature becomes a critical information disclosure vulnerability, allowing an unauthenticated attacker to view sensitive system and session data.

**Default Test Paths:**
- `example.com/trace.axd`
- `example.com/any.aspx/trace.axd`

#### 2. Technical Deep Dive & Exploitation
Exploitation of this endpoint is trivial. An attacker simply navigates to the `trace.axd` path using a web browser or a simple `curl` command. If the application returns the Trace Viewer page, the system is vulnerable.

**Real-World Exploitation Steps:**
1.  **Discovery:** An attacker uses directory brute-forcing (e.g., using tools like `gobuster` or `dirb`) or searches via Google Dorks (e.g., `inurl:trace.axd site:example.com`) to locate the exposed endpoint .
2.  **Accessing the Trace Log:** The attacker navigates to `https://example.com/trace.axd`. The page displays a list of recent HTTP requests.
3.  **Extracting Sensitive Data:** The attacker clicks the "View Details" link next to a specific request (such as a login POST request).
4.  **Data Harvesting:** The detailed view reveals:
    - **Session Identifiers:** `ASP.NET_SessionId` or `AuthId`.
    - **Authorization Headers:** Plaintext `Authorization: Basic` tokens or Bearer tokens.
    - **Credentials:** Username and password submitted via POST forms.
    - **Internal Paths:** Physical file paths on the server (e.g., `E:\webroots\application\web.config`).
    - **Query Strings & Server Variables:** Custom request headers and internal IP addresses.

**Real Case Example (Ericsson - 2023):**
In March 2023, the Checkmarx Security Research team discovered that a subdomain of Ericsson (`forecast.ericsson.net`) had tracing enabled. By accessing `https://forecast.ericsson.net/Trace.axd`, researchers were able to view the internal file structure (`E:\webroots\SupplyExtranet`) and, crucially, the plaintext credentials (username/password) submitted to the `/Login/Login.aspx` endpoint. Ericsson fixed the issue in April 2023 .

**Real Case Example (U.S. Department of Defense - 2025):**
In January 2025, a bug bounty hunter identified an active `Trace.axd` endpoint on a U.S. Department of Defense (DoD) application. The vulnerability was classified as **High Severity** by the DoD due to the risk of session hijacking and sensitive information exposure. The endpoint allowed the hunter to view session ID values and physical paths without any authentication .

**Real Case Example (Hospital Manager Backend - 2025):**
A significant vulnerability identified as **CVE-2025-54459** affected the Vertikal Systems Hospital Manager Backend Services prior to September 2025. The `/trace.axd` endpoint was exposed without authentication, allowing remote attackers to obtain live request traces. This exposure risked violations of healthcare regulations like HIPAA due to the leakage of session identifiers and internal file paths .

#### 3. Advanced Exploitation Vectors
- **Log Tampering (CWE-778):** In some configurations, the `trace.axd` viewer includes a "Clear Current Trace" button. If accessible, an attacker can clear the logs to cover their tracks or disrupt forensic investigations .
- **Port Misconfiguration:** Researchers have noted that sometimes `trace.axd` returns a `403 Forbidden` on HTTP (Port 80) but is fully accessible on HTTPS (Port 443) due to server misconfiguration .
- **Alternative Paths:** The endpoint is sometimes accessible via rewritten paths, such as `example.com/en-us/home/trace.axd`, depending on routing rules.

#### 4. Remediation & Mitigation
To prevent this vulnerability, developers and system administrators must ensure tracing is disabled in production environments.

**Fix: Disable Tracing in `web.config`**
The primary mitigation is to set the `enabled` attribute to `false` in the application's `Web.config` file.

```xml
<configuration>
  <system.web>
    <trace enabled="false" pageOutput="false" requestLimit="10" localOnly="true" />
  </system.web>
</configuration>
```

**Best Practices:**
- **Remove Handler Mapping:** If tracing is not used, remove the `TraceHandler` entirely.
- **Access Restrictions:** If debugging is required in a staging environment, restrict access to the `/trace.axd` path using IP restrictions (`<ipSecurity>`) or Authentication (`<authorization>`).
- **Code Review:** Scan legacy applications for commented-out or forgotten debug flags before deploying to production.

#### 5. Detection (For Defenders & Pentesters)
- **Nessus Plugin ID:** 10993 and 112352 detect this vulnerability by requesting `/trace.axd` and analyzing the response .
- **Manual Curl Command:**
    ```bash
    curl -k -s https://example.com/trace.axd | grep "ASP.NET Session State"
    ```
- **Metasploit:** The auxiliary module `auxiliary/scanner/http/trace_axd` can be used to enumerate the information.
