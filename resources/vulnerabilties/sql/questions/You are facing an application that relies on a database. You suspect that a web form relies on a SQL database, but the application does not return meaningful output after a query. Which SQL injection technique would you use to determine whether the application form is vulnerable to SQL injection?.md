Given that the application returns **no meaningful output** after a query, you cannot rely on visual differences in the page content. You must use a technique where the signal is something else entirely.

The correct technique in this scenario is **Time-Based Blind SQL Injection**.

---

### Why This Is the Right Choice

- **Boolean-based won't work:** You need a visible difference between a true and false condition (like a "Welcome back" message vs. a "User not found" error). The problem states the application returns *no meaningful output*, so this visual difference likely doesn't exist or isn't reliable.
- **Time-based is perfect:** The signal is the server's response time, not the content of the response. You don't need the application to tell you anything explicitly. You simply measure how long it takes to reply.

---

### How You Would Test for Vulnerability

You would inject a payload that instructs the database to pause for a noticeable duration (typically 5 to 10 seconds) if the database is indeed present and executing your injected code. You do this alongside a legitimate request that you know will execute normally.

**Step 1: Establish a Baseline**
First, you submit the form normally with a valid, benign input. Note how quickly the server responds (usually a fraction of a second).

**Step 2: Inject a Database-Specific Sleep Command**
You append a sleep instruction to your input. Since you don't yet know which database system is running, a common test is to try the syntax for the most popular ones, or use a conditional that works across them. The pattern generally looks like:

- **For MySQL:** `' OR SLEEP(10)--`
- **For PostgreSQL:** `' OR pg_sleep(10)--`
- **For SQL Server:** `' OR WAITFOR DELAY '00:00:10'--`

**Step 3: Observe the Response Time**
If, after submitting the tainted input, the application suddenly takes exactly 10 seconds to return a generic response, you have confirmed a vulnerability.

- **Why it proves vulnerability:** A normal application would treat your `SLEEP(10)` as meaningless search text and respond instantly. Only a database actively parsing your injected string as a command would actually pause. You have successfully executed arbitrary SQL code without seeing any output at all.

**Step 4: Refine (Optional)**
To be absolutely certain it's not a network hiccup, you could submit a false condition that forces the sleep to *not* execute, like:
`' OR 1=2 AND SLEEP(10)--`
Because `1=2` is false, the `AND` clause is false and the sleep should not happen. The server should respond instantly. The contrast between a 10-second delay (true) and instant response (false) confirms a blind time-based SQL injection vulnerability beyond any doubt.
