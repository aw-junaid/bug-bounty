**Blind SQL injection** is a type of SQL injection attack where the attacker cannot see the results of their injected queries directly on the web page. Unlike a normal injection where stolen data might appear in a product listing or an error message, here the application shows nothing useful. It's like interrogating a prisoner who refuses to speak but whose heartbeat you can monitor—you must deduce the truth indirectly.

The attacker must ask the database a series of **true/false questions** and observe a secondary signal to infer the answer. Extracting data becomes a slow, methodical process of guessing one character at a time.

Here are the two primary ways to perform it.

---

### Method 1: Boolean-Based Blind Injection

This technique relies on observing a **change in the web page's content or behavior** based on whether the injected condition is true or false. The attacker turns the database into a logic gate.

**How it works:**

1.  **Baseline Identification:** The attacker first establishes normal behavior. They inject a condition known to be true (e.g., `AND 1=1`). The page loads normally. Then they inject a false condition (e.g., `AND 1=2`). The page might return a "no results" message, a blank section, or a generic error. This difference is the **signal**.
2.  **Information Probing:** The attacker now asks a real question. For example, "Is the first letter of the table name 'Users' a letter greater than 'M'?" They embed this question in the URL or input.
3.  **Answer Interpretation:** If the page loads normally, the answer is True. If the broken/empty version appears, it's False. A script cycles through every possible character for every possible position, slowly reconstructing the hidden data.

**Analogy:** You're trying to guess a safe's combination by turning the dial and listening for a click. You can't see the tumblers inside, but a subtle change in resistance or sound tells you when you've hit the right number.

---

### Method 2: Time-Based Blind Injection

This technique is used when the page's visual response is completely identical regardless of what happens in the database. There is no "no results" message to observe. The attacker's signal is therefore **time**. They force the database to pause if a condition is true.

**How it works:**

1.  **Injection of a Delay:** The attacker crafts an injected condition that instructs the database to wait for a specified period if the condition is met. Each database system has its own pause command (e.g., `SLEEP()` in MySQL, `WAITFOR DELAY` in SQL Server, `pg_sleep()` in PostgreSQL).
2.  **Timing the Response:** The attacker sends the request and measures how long the server takes to respond.
3.  **Answer Interpretation:** If the condition is false, the database ignores the delay command and responds instantly. If the condition is true, the database pauses, and the response is delayed by exactly the expected time (e.g., 5 seconds).
4.  **Data Extraction:** An attacker can ask, "If the first character of the admin's password is 'a', wait for 5 seconds." By scripting hundreds of these timed requests, each testing one character, the attacker can slowly exfiltrate an entire password hash.

**Analogy:** You are interrogating someone who is completely stoic and silent. You ask questions but agree beforehand that they will tap their finger once every five seconds if the statement they hear is true. You observe the delay, not their expression, to get the answer.

In both cases, the extraction is painfully slow but fully automatable, allowing attackers to steal entire databases without ever seeing a single raw row of output on their screen.
