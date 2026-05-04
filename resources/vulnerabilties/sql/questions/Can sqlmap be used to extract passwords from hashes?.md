Yes, SQLMap can extract password hashes from a database, but it cannot reverse them into plaintext passwords on its own. It's designed to fetch the encrypted hashes, and then it provides a separate built-in tool to help you crack them.

### 🔑 How SQLMap Handles Passwords

The process has two distinct stages: **Extraction** and **Cracking**.

1.  **Extraction: Getting the Hashes**
    SQLMap specializes in exploiting SQL injection flaws to pull data directly from the database. To specifically target user credentials, you can use the `--passwords` switch. This command enumerates and dumps the password hashes for all database users.
    ```bash
    sqlmap -u "http://target-url/page.php?id=1" --passwords
    ```
    When you dump a database table with the `--dump` option, SQLMap automatically locates and stores any columns that contain hashes.

2.  **Cracking: Converting Hashes to Plaintext**
    Once the hashes are extracted, SQLMap stores them in a local file. It can then automatically analyze the hash format (e.g., MD5, SHA1, MySQL password hash) and launch a dictionary-based attack to try and find the matching plaintext password. This is not a guaranteed decryption but an automated brute-force attempt using a wordlist.

### 💡 What SQLMap Can't Do

It's important to understand the tool's limits. SQLMap is a SQL injection tool first and foremost. It cracks hashes as an auxiliary feature, offering a basic dictionary attack. It is not a replacement for dedicated, high-performance password cracking utilities like **Hashcat** or **John the Ripper**. For complex or strong passwords, you'd typically extract the data with SQLMap and then use those more powerful, GPU-accelerated tools.

I hope this clears up exactly what SQLMap can and cannot do with passwords. Let me know if anything is still unclear.
