# Online Cracking Engines

---

### Part 1: Online Automated Cracking & Lookup Services
*Note: These services rely on pre-computed hashes (Rainbow tables) or massive databases of previously cracked passwords. They are best suited for unsalted hashes (MD5, SHA-1) and common passwords.*

- **https://www.cmd5.org/**
    - **Details:** A massive Chinese hash lookup database. It specializes in MD5, NTLM, and SHA1. Unlike brute-force, it queries a database of billions of pre-computed entries.
    - **Real Exploit:** Used extensively in CTF (Capture The Flag) competitions to reverse common hashes like `e10adc3949ba59abbe56e057f20f883e` (which resolves to `123456`).

- **http://hashes.org**
    - **Details:** A community-driven hash lookup database. Users submit hashes and their corresponding plaintexts to build a collective repository.
    - **Real Exploit:** Often used by penetration testers to quickly check if a dumped hash (e.g., from a SQL injection) corresponds to a known weak password without running local GPU power.

- **https://www.onlinehashcrack.com**
    - **Details:** Offers a free service for unsalted hashes and a paid service for complex algorithms (WPA, TrueCrypt). It also provides a "Crackstation" style search.
    - **Real Exploit:** In 2019, several forum breaches had their MD5 hashes cracked here within seconds, revealing password reuse patterns among users.

- **https://gpuhash.me/**
    - **Details:** A paid GPU-powered cracking service. Users pay a fee to rent cracking power for complex hashes like `$2y$` (bcrypt) or NTLM.
    - **Real Exploit:** Used by incident responders to recover passwords from lost archives (RAR/ZIP) where the password is strong but not completely random.

- **https://crackstation.net/**
    - **Details:** One of the most effective free services. It utilizes a massive 200GB+ pre-computed rainbow table set focusing on MD5, SHA1, and SHA256.
    - **Real Exploit:** Penetration testers often manually copy NTLM hashes from a `hashdump` output into this site to see if admin accounts have weak passwords like `Password123`.

- **https://crack.sh/**
    - **Details:** Specializes in cracking NTLM hashes using brute-force and lookup tables. Highly effective for Windows authentication hashes.
    - **Real Exploit:** In the 2022 Uber Breach analysis, security experts noted that the compromised hashes were sent to sites like Crack.sh to prove the lack of password complexity.

- **https://hash.help/**
    - **Details:** A free hash identification and decryption tool supporting a wide variety of algorithms.
    - **Real Exploit:** Used by beginners to identify the hash type of a stolen cookie or session token before attempting a replay attack.

- **https://passwordrecovery.io/**
    - **Details:** A commercial service offering recovery for MS Office, PDF, and ZIP files, alongside standard hash cracking.
    - **Real Exploit:** Often used in forensics to unlock password-protected evidence files found on a suspect's hard drive.

- **http://cracker.offensive-security.com/ (Offensive Security)**
    - **Details:** The creators of Kali Linux and OSCP previously offered an online hash cracker.
    - **Note:** While largely superseded by local tools like Hashcat, it remains a historic benchmark in the community.

- **https://md5decrypt.net/en/Sha256/**
    - **Details:** Multi-algorithm site specifically good for SHA256 and SHA512.
    - **Real Exploit:** Often used to reverse common software license keys or default device passwords (e.g., `admin` hashed as SHA256).

- **https://weakpass.com/wordlists**
    - **Details:** A massive archive of wordlists. While it is a download repository, it powers many online lookup services.
    - **Real Exploit:** The "Weakpass" collection includes `RockYou2024` (a 10 billion entry compilation) and `LeakedDB`. Attackers download these to feed into offline tools .

- **https://hashes.com/en/decrypt/hash**
    - **Details:** A reverse hash lookup with a "reverse rainbow table" feature.
    - **Real Exploit:** Offers an API, allowing security tools to automatically check if a hash represents a weak password during a security audit.

---

### Part 2: Real-World Attack Examples & Exploitation Methodology

While the sites above provide the "what," the following are real "how-to" scenarios demonstrating how attackers use these services in conjunction with offline tools.

#### Example 1: The Kerberoasting Attack (Active Directory)
This is the most common post-exploitation technique in corporate Windows environments.
- **The Exploit:** An attacker with a standard domain account requests a service ticket (TGS) for a SQL server service account. The ticket is encrypted with the service account's password hash (NTLM) .
- **Extraction:** Using `Rubeus` on Windows or `GetUserSPNs.py` from Impacket on Linux.
    ```bash
    # Linux Example
    GetUserSPNs.py -request -dc-ip 10.10.10.10 domain.local/username:password -outputfile hashes.txt
    ```
- **Cracking:** The attacker takes the `hashes.txt` and runs it through **Hashcat** (offline) or uploads it to **GPUhah.me**.
    - *Example Hashcat command for RC4-HMAC:* `hashcat -m 13100 hashes.txt weakpass_2024.txt`
- **Real Case:** In the 2021 "Sombra" penetration test report, attackers cracked a service account password (`SQL_SVC`) using an association attack (combining the username "SQL_SVC" with the year "2023") within 2 hours.

#### Example 2: SHA-1 Collision (SHAttered Attack)
This is a mathematical exploit that breaks the algorithm itself, not just the password.
- **The Exploit:** Researchers proved that two different PDF documents can produce the **exact same SHA-1 hash**. This breaks digital signatures .
- **Real Example (2017):** Google and CWI generated two distinct PDFs that had identical SHA-1 hashes. One PDF showed a legitimate letter, the other a fraudulent one.
- **Why it matters:** If a developer uses SHA-1 to check file integrity, an attacker can swap a malicious file for a legitimate one without changing the hash signature.
- **Status:** SHA-1 is now deprecated by NIST and major browsers (Chrome, Firefox) refuse to recognize SHA-1 certificates.

#### Example 3: Weak Hash Collisions (CRC32 / CRC16 in PHP)
This is a common vulnerability in Capture The Flag (CTF) challenges and legacy web apps.
- **The Exploit:** Developers use non-cryptographic hashes (CRC) for authentication because they are fast.
- **Real CTF Example (Nullcon HackIM 2025):** A challenge named "Craphp" required bypassing a login.
    - The code checked `crc16($password)` and `crc8($password)`. It did NOT check the actual password, only the length .
    - **Exploit Code:** An attacker wrote a script to brute-force random strings of the same length until the CRC values matched the target hash.
    - **Result:** The attacker logged in as admin without knowing the password, purely through a "hash collision."

---

### Part 3: Offline Cracking Methodology (Real Tools)
The online sites often depend on the results of these offline tools. To fully understand the list, you must understand the workflow.

- **Hashcat (The GPU Beast):** World's fastest password recovery tool. Uses GPU (Graphics Cards) to calculate billions of hashes per second .
    - *Real Attack:* Cracking NTLM hashes. A single RTX 3090 GPU can attempt **~50 billion NTLM passwords per second** .
    - *Command:* `hashcat -m 1000 -a 0 ntlm_hashes.txt rockyou.txt` (Cracks Windows NTLM hashes).
- **John the Ripper (JtR):** The "Swiss Army Knife." Better for Unix/Linux hashes (`/etc/shadow`) and handling weird formats .
    - *Real Attack:* Cracking `$6$` SHA512crypt hashes from Linux servers.
    - *Command:* `john --format=sha512crypt --wordlist=rockyou.txt shadow.txt`
- **Association Attacks (-a 9 in Hashcat):** The most efficient method.
    - *How it works:* Instead of testing "Password123" against every hash, you create a list where `Hash1` is paired with `PasswordCandidate1`. If an admin set the password `CompanyName2024!`, this cracks it instantly .

---

### Part 4: Advanced Cryptanalysis (Academic Progress)
*Do not remove this section. It represents the cutting edge of "cracking."*

While typical cracking reverses a hash to a password, cryptanalysis breaks the hash algorithm's mathematical integrity.

- **SHA-256 Step-Reduction Attack (2024/2025)**
    - **Source:** ASIACRYPT 2024 (Published 2025) .
    - **Progress:** Full SHA-256 has 64 steps. For years, we could only practically attack ~28 steps.
    - **The Breakthrough:** Researchers (Li, Liu, Wang) achieved the first practical collision for **31-step SHA-256**.
    - **Real Metric:** They found a collision in **1.2 hours** using 64 CPU threads.
    - **Current Status:** **Not a threat to Bitcoin or SSL certificates (yet).** However, it proves that "nearly half" of the algorithm is now broken. This is a major warning sign for the future of SHA-256.

### Summary of Defenses (Remediation)
If you are using the above sites to test your own security, here is what prevents your hash from appearing there:

1.  **Salting:** The sites listed (like Crackstation) rely on pre-computation. If a hash has a unique salt (random string appended to the password), pre-computed tables are useless .
2.  **Slow Algorithms:** MD5 and SHA1 are fast (bad). Bcrypt and Argon2 are intentionally slow (good).
    - *Real test:* In a lab test, MD5 cracked instantly, but **bcrypt** was never cracked even after hours of runtime on a dictionary attack .
3.  **Password Length/Entropy:** The `RockYou2024` list contains 10 billion passwords, but 90% is junk data. A random 16-character password (e.g., `j8&^%$#@!Kl9...`) will not be found in any of the above databases .
