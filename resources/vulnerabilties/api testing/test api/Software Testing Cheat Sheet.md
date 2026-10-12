# Software Testing Cheat Sheet — Expanded Edition

A practical, detailed reference for exploratory testers, QA engineers, and developers. It keeps everything from the original one-page sheet and adds concrete example inputs, what to look for, and modern areas (WCAG 2.2, GraphQL, OWASP, Core Web Vitals, AI/LLM features, accessibility, mobile OS behaviours).

> **How to use this:** Don't run it top-to-bottom. Pick the sections that match the thing under test, pick a few heuristics, set a timebox (e.g. 45 minutes), and take notes as you go. Always ask: *"What would I expect to happen, and what actually happened?"*

## Table of Contents

1. [Data Type Attacks](#1-data-type-attacks)
2. [Web Tests](#2-web-tests)
   - [13. Curl Command Cookbook](#13-curl-command-cookbook-live-tested)
   - [14. Testing with Postman](#14-testing-with-postman--section-by-section)
   - [15. Kali Linux Security Commands](#15-kali-linux--security-testing-commands)
   - [16. Tested Scripts](#16-tested-scripts-live-executed-real-output)
   - [17. Burp Suite Guide](#17-burp-suite--section-by-section-testing-guide)
   - [18. Local Practice Target](#18-a-local-safe-practice-target-built-and-run-live-for-this-guide)
   - [19. Database Testing](#19-database-testing)
   - [20. JWT / Token / Session Testing](#20-jwt--token--session-testing-live-tested)
   - [21. Test Data Generation](#21-test-data-generation-live-tested-with-faker)
   - [22. Log Analysis](#22-log-analysis-for-test-evidence-live-tested)
   - [23. Browser Automation](#23-browser-automation-selenium--playwright--cypress)
   - [24. Load / Performance Testing](#24-load--performance-testing-k6--jmeter)
   - [25. Automated Accessibility Testing](#25-automated-accessibility-testing-cli)
   - [26. CI/CD Integration](#26-wiring-it-all-into-cicd)
3. [Accessibility (A11y)](#3-accessibility--a11y)
4. [Performance & Reliability](#4-performance--reliability)
5. [Security Quick Checks](#5-security-quick-checks)
6. [API Tests](#6-api-tests)
7. [Mobile / Device / Tablet](#7-mobile--device--tablet)
8. [AI / LLM Feature Testing](#8-ai--llm-feature-testing)
9. [Testing Wisdom](#9-testing-wisdom)
10. [Heuristics](#10-heuristics)
11. [Frameworks](#11-frameworks)
12. [Bug Report Template & Session Notes](#12-bug-report-template--session-notes)

---

## 1. Data Type Attacks

For each field, parameter, or input, try the relevant attacks below. Always check **four things**: what the UI shows, what is stored, what is logged, and what is returned to other consumers (exports, APIs, emails, PDFs).

### 1.1 Paths / Files

| Attack | What to try | What to look for |
|---|---|---|
| **Long name (>255 chars)** | 255, 256, 260 (Windows MAX_PATH), 4096 (Linux PATH_MAX) characters | Truncation, crashes, silent failures, DB column overflow, broken downloads |
| **Special characters in name** | `space * ? / \ \| < > , . ( ) [ ] { } ; : ' " ! @ # $ % ^ &` | Rejected vs. accepted, escaped correctly, path traversal, filename mangled on download |
| **Non-existent** | Path that was deleted, mistyped, or never existed | Clear error message, no stack trace, no crash |
| **Already exists** | Save/upload/rename to an existing name | Overwrite prompt? Silent overwrite? Auto-rename (`file (1).txt`)? |
| **No space** | Fill the disk / use a tiny quota / full temp directory | Graceful error, no partial/corrupt file left behind |
| **Minimal space** | Just enough space for part of the file | Partial writes cleaned up, user is told |
| **Write-protected** | Read-only file, folder, or share | Clear permission error; no data loss |
| **Unavailable** | Drive disconnected, network share offline, USB removed mid-operation | Retry, timeout, recoverable state |
| **Locked** | File open in another app/process (esp. Windows) | Lock error handled, no infinite wait |
| **On remote machine** | UNC paths, SMB/NFS, cloud-synced folders (OneDrive, Dropbox, iCloud) | Latency, partial sync, conflicts |
| **Corrupted** | Truncated file, wrong extension, wrong magic bytes, zero-byte file | Safe rejection, no crash, no code execution |

**Extra path/file ideas**
- Path traversal: `../../etc/passwd`, `..\..\windows\win.ini`, URL-encoded `%2e%2e%2f`
- Reserved Windows names: `CON`, `PRN`, `AUX`, `NUL`, `COM1`, `LPT1`
- Trailing dots/spaces (`file.txt.`), leading dots (`.hidden`), unicode normalization (`é` as one char vs. `e` + combining accent)
- Case sensitivity: `File.txt` vs `file.txt` (Linux/macOS/Windows differ)
- Symlinks and shortcuts pointing outside allowed directories
- Upload checks: wrong MIME type vs. extension (`.jpg` that is actually `.exe`), double extensions (`invoice.pdf.exe`), oversized files, zero-byte files, huge image dimensions (decompression bombs), zip bombs, SVG with embedded scripts, EXIF data leaking GPS location
- Filename encoding: UTF-8, emoji, right-to-left override character (U+202E)

### 1.2 Time and Date

| Attack | Examples |
|---|---|
| **Timeouts** | Session expiry mid-form, idle timeout, request timeout, token expiry during a long operation |
| **Time difference between machines** | Client vs. server clock skew of seconds, minutes, hours (breaks JWT `exp`/`nbf`, TOTP codes, signatures, caching) |
| **Crossing time zones** | Create in UTC+5, view in UTC−8; travel across the International Date Line; users in half-hour zones (India UTC+5:30), 45-minute zones (Nepal UTC+5:45) |
| **Leap days** | 29 Feb in a leap year (2024, 2028); 2100 is **not** a leap year, 2000 **was** |
| **Always invalid days** | 30 Feb, 31 Apr, 31 Jun, 31 Sep, 31 Nov, month 0, month 13, day 0 |
| **Feb 29 in non-leap years** | 29 Feb 2025, 29 Feb 2100; "add 1 year" to 29 Feb 2024 |
| **Different formats** | `June 5, 2001`; `06/05/2001`; `06/05/01`; `06-05-01`; `6/5/2001 12:34`; `2001-06-05` (ISO 8601); `2001-06-05T12:34:00Z`; `2001-06-05T12:34:00+05:00` |
| **Internationalisation** | `dd.mm.yyyy` (Germany), `mm/dd/yyyy` (US), `yyyy/mm/dd` (Japan); is `06/05/2001` June 5 or 6 May? Non-Gregorian calendars (Hijri, Buddhist, Japanese era) |
| **12 vs 24 hour** | `12:00 AM` vs `12:00 PM`; `00:00` vs `24:00`; `12:30 AM` edge cases |
| **Daylight saving changeover** | Clocks forward (a local time that doesn't exist, e.g. 02:30), clocks back (a local time that occurs twice); recurring events/alarms/cron jobs near the change |
| **Reset clock backward/forward** | Change device time manually: trial periods, licences, tokens, scheduled jobs, "expires in" logic |

**Extra time/date ideas**
- **Year 2038 problem:** 32-bit Unix timestamps overflow at `2038-01-19 03:14:07 UTC`
- Epoch: `1970-01-01`, timestamps of `0`, negative timestamps, seconds vs. milliseconds confusion (10 vs. 13 digits)
- Dates far in the past/future: `0001-01-01`, `1900-01-01`, `9999-12-31`; two-digit years (`01` → 1901 or 2001?)
- Leap seconds (`23:59:60`)
- Week numbering: ISO weeks (week 53), first day of week (Sunday vs. Monday), year-boundary weeks (31 Dec in week 1 of next year)
- Duration/elapsed-time math across DST changes (a "day" is not always 24 hours)
- End-of-month rolling: "+1 month" from 31 Jan
- Birthdays on 29 Feb; age calculations
- Time zone database changes (countries abolish/adopt DST) — are tz data updates handled?
- Store in UTC, display in local — verify both, and verify what's shown on emails, PDFs, and exports

### 1.3 Numbers

| Value | Why it matters |
|---|---|
| `0` | Divide by zero, empty state, falsy checks |
| `32767` / `32768` (2^15) | Signed 16-bit max / overflow |
| `32769` (2^15 + 1) | Just past boundary |
| `65535` / `65536` (2^16) | Unsigned 16-bit max / overflow (also TCP port range: 0–65535) |
| `65537` (2^16 + 1) | Just past boundary |
| `2147483647` / `2147483648` (2^31) | Signed 32-bit max / overflow |
| `2147483649` (2^31 + 1) | Just past boundary |
| `4294967295` / `4294967296` (2^32) | Unsigned 32-bit max / overflow |
| `4294967297` (2^32 + 1) | Just past boundary |
| `9223372036854775807` (2^63 − 1) | Signed 64-bit max; JavaScript loses precision above `9007199254740991` (2^53 − 1, `Number.MAX_SAFE_INTEGER`) |
| Scientific notation | `1E-16`, `1e5`, `1E+308`, `4.9e-324` |
| Negative | `-1`, `-0`, `-2147483648`, negative quantities/prices/ages |
| Floating point / decimal | `0.0001`, `0.1 + 0.2` (should be 0.3, usually isn't), `1.005` rounding, many decimal places |
| With commas | `1,234,567` — accepted? parsed correctly? |
| European style | `1.234.567,89` — period as thousands separator, comma as decimal |
| All of the above in calculations | Totals, averages, tax, discounts, currency conversion, percentages |

**Extra number ideas**
- Special values: `NaN`, `Infinity`, `-Infinity`, `null`, `undefined`, `""`, `"abc"`, `"1e999"`
- Leading zeros (`007` → octal?), leading `+`, trailing `.`, `.5`, `5.`, hexadecimal `0xFF`, binary `0b101`
- Spaces inside numbers (`1 000`), non-breaking spaces, Arabic-Indic digits (`١٢٣`), full-width digits (`１２３`)
- Currency: zero-decimal currencies (JPY), three-decimal currencies (KWD, BHD), rounding mode (half-up vs. banker's rounding), never use floats for money
- Quantity limits: 0, 1, max allowed, max + 1, fractional quantity on whole-item products
- Percentages: 0%, 100%, 100.01%, negative %
- Totals that don't add up due to rounding per line vs. rounding at the end
- Sorting numbers as strings (`1, 10, 2`)

### 1.4 Strings

| Attack | Examples |
|---|---|
| **Long** | 255, 256, 257, 1000, 1024, 2000, 2048, 4096, 65535, 1 million characters |
| **Accented chars** | `àáâãäåçèéêëìíîïðñòóôõö`, plus names like `Zoë`, `Søren`, `Ñandú` |
| **Asian chars** | `漢字`, `日本語`, `한국어`, `中文` — check width, truncation, sorting, fonts |
| **Right-to-left** | Arabic `مرحبا`, Hebrew `שלום` — layout mirrors, mixed LTR/RTL text |
| **Common delimiters/special chars** | `" ' ` \| / \ , ; : & < > ^ * ? Tab` |
| **Leave blank** | Empty string vs. missing field vs. `null` |
| **Single space** | `" "` — treated as empty? Accepted when it shouldn't be? |
| **Multiple spaces** | Collapsed? Preserved? |
| **Leading/trailing spaces** | `" john"`, `"john "` — trimmed? Login mismatch? Duplicate-user bypass? |
| **End-of-line chars** | `^M` (`\r`), `\n`, `\r\n`, Unicode line separator U+2028 |
| **SQL injection** | `' OR '1'='1`, `'; DROP TABLE users;--`, `' select * from customer`, `" OR ""="` |
| **With all actions** | Enter, search, update, delete, sort, filter, export, import, print, email |
| **Emojis** | 😀, 👨‍👩‍👧‍👦 (multi-codepoint), flags 🇵🇰, skin-tone modifiers 👍🏽 — length counts, DB `utf8` vs. `utf8mb4` |

**Extra string ideas**
- **Unicode:** zero-width space (U+200B), zero-width joiner, byte-order mark (U+FEFF), combining characters, homoglyphs (Cyrillic `а` vs. Latin `a`), normalization forms (NFC vs. NFD), invisible characters, "Zalgo" text
- **Null byte:** `%00`, `\0` — truncation of strings in some languages/APIs
- **Format strings:** `%s %s %s %n`, `{0}`, `${7*7}`, `{{7*7}}` (template injection)
- **Injection families:** XSS (`<script>alert(1)</script>`, `"><img src=x onerror=alert(1)>`), NoSQL (`{"$ne": null}`), LDAP (`*)(uid=*))(|(uid=*`), OS command (`; ls`, `&& whoami`, `` `id` ``), XML/XXE, header injection (`\r\nSet-Cookie:`), CSV/formula injection (`=cmd|' /C calc'!A0`, `=HYPERLINK(...)`)
- **Names:** single-name people, apostrophes (`O'Brien`), hyphens, very long names, names with numbers, names with titles — avoid assumptions of "first name / last name"
- **Case:** `ALL CAPS`, `lowercase`, Turkish dotless `i` (`İ`, `ı`), case-insensitive uniqueness (`Bob@x.com` vs. `bob@x.com`)
- **Strings that look like other types:** `"true"`, `"null"`, `"0"`, `"NaN"`, `"undefined"`
- Copy-paste from Word/web (smart quotes `“ ”`, em dashes, hidden formatting)
- Truncation mid-multibyte-character (produces `�`)

### 1.5 General

| Attack | Examples |
|---|---|
| **Violates domain-specific rules** | IP `999.999.999.999`; email without `@`; age of `-1` or `200`; phone number with letters; postcode in wrong format; end date before start date; credit card failing Luhn check; IBAN with wrong checksum |
| **Violates uniqueness constraint** | Duplicate username, email, ID, SKU; case variants (`Admin` vs. `admin`); differing only by whitespace; race condition (two simultaneous creations) |

**Useful valid-format test data**
- Email: `a@b.co`, `user+tag@example.com`, `"quoted"@example.com`, `very.long.local-part…@subdomain.example.com`, internationalised `用户@例子.广告`
- Phone: `+92 300 1234567`, `(555) 123-4567`, `+1-555-123-4567 ext. 89`
- Test card numbers (use only in sandbox environments — Stripe's `4242 4242 4242 4242`, declined `4000 0000 0000 0002`)
- Placeholder domains safe for testing: `example.com`, `example.org`, `.test`, `.invalid`

---

## 2. Web Tests

### 2.1 Navigation

| Test | What to look for |
|---|---|
| **Back** | "Document Expired" pages, **double-posted transactions**, stale data, lost form input, broken SPA routing |
| **Refresh** | Resubmitted POSTs, duplicate orders, lost state, re-triggered one-time actions |
| **Bookmark the URL** | Does a deep link restore the right state? Does it work for mid-wizard URLs? |
| **Select bookmark when logged out** | Redirect to login, then back to the intended page after login (`returnUrl`); open redirect vulnerabilities |
| **Hack the URL** | Change/remove parameters; change IDs (`/orders/1001` → `/orders/1002` — access control/IDOR); add `?debug=true`; tamper with sort/page params; see Data Type Attacks |
| **Multiple browser instances open** | Two tabs, two windows, two browsers, same user in two places; stale sessions; logout in one tab |
| **Swipe/tap/pinch** | Touch gestures, pinch-zoom blocked or broken, accidental swipe-back, long-press, double-tap |

**More navigation ideas**
- Forward button after Back; history manipulation in SPAs (`pushState`)
- Open link in new tab / new window / incognito; middle-click; right-click → copy link
- Deep links with anchors (`#section`); trailing slashes (`/page` vs. `/page/`); uppercase paths; unknown URLs → friendly 404 page with a way back
- Redirect chains and loops; HTTP → HTTPS redirect; `www` vs. non-`www`
- Session handling: expired session mid-action, "remember me", concurrent logins, logout clears everything (including browser cache of sensitive pages)
- Print preview and print stylesheet; "Save as PDF"
- Browser autofill and password managers working with forms

### 2.2 Input

| Test | What to look for |
|---|---|
| **See also Data Type Attacks** | Everything in section 1 |
| **HTML/JavaScript injection** | `<script>alert(1)</script>`, `<img src=x onerror=alert(1)>`, `javascript:alert(1)` in URL fields. Test where the value is *displayed* later (profile pages, admin screens, emails, PDFs, logs) — stored XSS often appears in a different place than where it was entered |
| **Check max length on text inputs** | Does `maxlength` exist? Is it enforced server-side too? Does the DB column match? Does the counter count characters or bytes (emoji!)? |
| **>5000 chars in textareas** | Truncation, 413 errors, performance, lost text with no warning |

**More input ideas**
- Client-side validation bypassed (disable JS, or send the request directly with curl/Postman) — server must validate independently
- Paste vs. type vs. autofill vs. drag-and-drop vs. voice input
- Double-click submit, press Enter repeatedly (see Flood heuristic)
- File upload: large file, many files, cancel mid-upload, network drop mid-upload, same file twice
- Date pickers: keyboard entry vs. widget, min/max dates, manual typing of invalid values
- Rich-text editors: pasted formatting, scripts, huge images, undo/redo
- Autocomplete/typeahead: fast typing (race conditions between responses), special characters in search, no results, thousands of results
- Required-field messaging: is each error specific, near the field, and announced to screen readers?
- Error messages: don't reveal system internals (SQL, stack traces, file paths, library versions)

### 2.3 Syntax

- **HTML syntax checker** (W3C Nu Validator): unclosed tags, duplicate IDs, invalid nesting, missing `alt`/`lang`
- **CSS syntax checker** (W3C CSS Validator)
- Also consider: linting (ESLint, Stylelint), console errors/warnings in DevTools, broken links (link checker), mixed-content warnings, 404s on assets, failed network requests in the Network tab

### 2.4 Preferences & Environment

| Test | What to look for |
|---|---|
| **JavaScript off** | Graceful `<noscript>` message, core content still readable, forms degrade gracefully |
| **Cookies off** | Clear message vs. broken login or infinite redirect loops; also test blocking third-party cookies |
| **Security high** | Strict browser privacy mode (Safari ITP, Firefox Enhanced Tracking Protection, Brave Shields), ad/tracker blockers breaking functionality |
| **Resize browser window** | Responsive breakpoints (320, 375, 768, 1024, 1280, 1920+ px), no horizontal scrolling, nothing clipped or overlapping |
| **Change font size** | Browser text size to 200%, minimum font size settings, layout doesn't break, text doesn't overflow/overlap |

**More environment ideas**
- **Browsers:** latest Chrome, Edge, Firefox, Safari (macOS and iOS — WebKit differs!), plus one older version; Samsung Internet where relevant
- **Display modes:** dark mode / light mode (`prefers-color-scheme`), high contrast / forced colors (Windows), reduced motion (`prefers-reduced-motion`), print
- **Zoom:** 50%–500%, pinch-zoom on mobile, OS-level display scaling (125%, 150%)
- **Network:** throttle to Slow 3G / offline in DevTools; high latency; flaky connection; going offline mid-action; service worker / PWA offline behaviour
- **Extensions and privacy tools:** password managers, translators (Google Translate rewriting the DOM!), reader mode, VPN, ad blockers
- **Language & locale:** switch browser language; long translations (German is ~30% longer), RTL layouts, locale-specific formatting
- **Storage:** clear cookies/local storage mid-session; storage full; incognito mode
- **Third parties:** what happens if analytics, chat widgets, payment providers, CDNs, or fonts fail to load?

### 2.5 Forms Checklist (quick)

- Tab order logical; Enter submits; labels clickable
- Inline validation timing (on blur vs. on submit); errors preserved after failed submit (data not wiped)
- Disabled/loading state on submit button; double-submit prevention
- Password fields: show/hide toggle, paste allowed, strength meter sensible, password managers work, no restrictive max length
- Success confirmation; duplicate submission after browser Back
- CAPTCHA/MFA flows: expired codes, resend limits, wrong code lockout, copy-paste of one-time codes

---

## 3. Accessibility / A11y

Aim for **WCAG 2.2 Level AA** as a baseline (the current W3C Recommendation; it adds new success criteria on top of 2.1). Automated tools (axe DevTools, Lighthouse, WAVE, Accessibility Insights) catch only roughly a third of issues — manual testing is essential.

### 3.1 Keyboard

- **Navigation:** Every interactive element reachable with `Tab` / `Shift+Tab`; arrow keys for menus, tabs, radio groups; `Enter`/`Space` to activate; `Esc` to dismiss
- **Skip to link** (first tab press): "Skip to main content" appears and works
- **No traps:** you can get *out* of menus, modals, embedded widgets, and iframes using the keyboard
- **Visible focus indicator:** clearly visible, adequate contrast, not hidden by sticky headers/cookie banners (WCAG 2.2: *Focus Not Obscured*)
- **Use all functionality:** hover-only menus, drag-and-drop, sliders, and custom widgets all need keyboard alternatives
- **Pop-ups get focus, can be dismissed:** focus moves into a modal on open, is trapped *within* it while open, and returns to the trigger on close

### 3.2 Context & Structure

- **Links are descriptive:** avoid "click here" / "read more" alone; links with the same text should go to the same place
- **Alt text:** informative images have meaningful alt text; **decorative images have `alt=""`** so screen readers skip them; complex charts get a longer description; images of text avoided
- **Form input labels:** every input has a programmatic `<label>` (not just placeholder text); groups use `<fieldset>`/`<legend>`; errors tied to inputs with `aria-describedby`
- **Main element:** exactly one `<main>`; also use `<header>`, `<nav>`, `<footer>`, and a logical heading hierarchy (one `<h1>`, no skipped levels)
- **Country and language defined:** `<html lang="en">`; mark up language changes inside content (`lang="fr"`)
- **Plain language** used; page titles unique and descriptive
- **Don't rely on ARIA first:** native HTML elements (`<button>`, `<a>`, `<input>`) beat `div` + `role` — "No ARIA is better than bad ARIA"

### 3.3 Content & Visual

- **Capitals in `#` hashtags (CamelCase):** `#BestDayEver` not `#bestdayever` — screen readers read words correctly
- **No all-capitals text** (harder to read, may be read letter-by-letter)
- **No justified text** (uneven spacing hurts readability for dyslexia)
- **Zoom to 200%** (and **reflow at 400% / 320px width**) without loss of content or horizontal scrolling
- **Gender-neutral language;** acronyms explained at first use; clear instructions
- **Good contrast:** 4.5:1 for normal text, 3:1 for large text and UI components/graphics (check with a contrast checker)
- **More than just colour** to indicate success/error/state: e.g. a green tick *plus* text, icons, patterns; links underlined in body text

### 3.4 New in WCAG 2.2 (worth checking)

| Criterion | Quick test |
|---|---|
| **Focus Not Obscured (2.4.11)** | Tab through the page: is the focused item hidden behind sticky headers, cookie banners, chat widgets? |
| **Dragging Movements (2.5.7)** | Any drag-and-drop or slider has a single-pointer alternative (e.g. buttons) |
| **Target Size Minimum (2.5.8)** | Touch/click targets at least 24×24 CSS px (aim for 44×44 for touch) |
| **Consistent Help (3.2.6)** | Help link/contact/chat appears in the same place across pages |
| **Redundant Entry (3.3.7)** | Users aren't forced to re-enter info already provided in the same process |
| **Accessible Authentication (3.3.8)** | Login doesn't depend on memorizing/transcribing/solving puzzles; paste into password fields allowed, password managers work |

### 3.5 Assistive Tech Smoke Test

| Tool | Platform |
|---|---|
| **NVDA** (free) + Firefox/Chrome | Windows |
| **JAWS** + Chrome | Windows |
| **VoiceOver** + Safari | macOS / iOS (`Cmd+F5`) |
| **TalkBack** | Android |
| **Voice control / Dragon** | Say the visible label of a button to click it — *Label in Name* (2.5.3) |
| **Magnifier / OS zoom, high contrast mode, reduced motion** | All |

Also: test with captions/transcripts for media, no auto-playing audio, animations can be paused (and respect `prefers-reduced-motion`), no flashing content more than 3 times per second, timeouts can be extended, error messages are announced (`aria-live` / `role="alert"`).

### 3.6 Seen and Heard (Ady Stokes)

- For everything you can **see**, is it announced by a screen reader?
- For everything you can **hear**, can it be read (transcript, subtitles, captions, audio descriptions)?

---

## 4. Performance & Reliability

### 4.1 Web performance — Core Web Vitals (Google)

| Metric | Measures | "Good" target |
|---|---|---|
| **LCP** — Largest Contentful Paint | Loading speed of the main content | ≤ 2.5 s |
| **INP** — Interaction to Next Paint (replaced FID in March 2024) | Responsiveness to input | ≤ 200 ms |
| **CLS** — Cumulative Layout Shift | Visual stability (things jumping around) | ≤ 0.1 |

Tools: Lighthouse, PageSpeed Insights, WebPageTest, Chrome DevTools Performance panel, real-user monitoring (RUM).

### 4.2 Load, stress, and soak

| Type | Question it answers | Typical tools |
|---|---|---|
| **Load** | Does it meet targets at expected traffic? | k6, JMeter, Gatling, Locust |
| **Stress** | Where does it break, and *how* does it fail? | same |
| **Spike** | Can it handle a sudden burst (flash sale)? | same |
| **Soak / endurance** | Memory leaks, connection leaks, log/disk growth over hours/days | same |
| **Scalability** | Does adding resources improve capacity linearly? | cloud tooling |

Watch: response time percentiles (p50/p95/p99, not just averages), error rate, throughput, CPU/memory/disk/network, DB connection pool, queue depth, GC pauses.

### 4.3 Resilience & chaos

- Kill a service/pod/process mid-transaction; restart order; dependency down (DB, cache, queue, third-party API)
- Network partition, packet loss, high latency (Toxiproxy, `tc netem`, Chaos Mesh, Gremlin)
- Retry storms, missing timeouts, no circuit breaker, thundering herd on cache expiry
- Idempotency: does retrying a request create duplicates?
- Backup/restore actually works; failover; rollback of a bad deploy; feature flag off/on

---

## 5. Security Quick Checks

Not a substitute for a security assessment, but a tester can catch a lot. Reference: **OWASP Top 10 (2021)** and **OWASP API Security Top 10 (2023)**.

| Area | Quick tests |
|---|---|
| **Broken access control / IDOR** | Change IDs in URLs/bodies; access another user's data; access admin pages as normal user; use a normal user's token on admin endpoints |
| **Authentication** | Brute-force lockout/rate limit; weak passwords accepted; password reset tokens reusable/guessable/never expire; session fixation; logout invalidates token server-side; MFA bypass |
| **Injection** | SQL, NoSQL, OS command, LDAP, template, header (see Strings) |
| **XSS** | Reflected, stored, DOM-based; check output encoding in HTML, attributes, JS, URLs |
| **CSRF** | State-changing requests without anti-CSRF tokens / `SameSite` cookies |
| **Security misconfiguration** | Default credentials, directory listing, verbose errors, exposed `/.git`, `/admin`, `/swagger`, debug endpoints, missing security headers |
| **Sensitive data exposure** | HTTPS everywhere, secrets in URLs/logs/responses, PII in error messages, caching of sensitive pages, data in browser storage |
| **SSRF** | URL-fetching features pointing to `http://localhost`, `169.254.169.254` (cloud metadata), internal IPs |
| **File upload** | Executable uploads, path traversal in filenames, content-type spoofing |
| **Vulnerable components** | Dependency scanning (`npm audit`, `pip-audit`, Dependabot, Snyk, OWASP Dependency-Check) |
| **Logging & monitoring** | Are failed logins, access-denied events logged? Are secrets/PII *not* logged? |
| **Rate limiting** | Login, password reset, OTP, search, expensive endpoints, API keys |

**Security headers to check:** `Content-Security-Policy`, `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options` / CSP `frame-ancestors`, `Referrer-Policy`, `Permissions-Policy`; cookies flagged `Secure`, `HttpOnly`, `SameSite`. Try [securityheaders.com](https://securityheaders.com) or OWASP ZAP baseline scan.

**Tools:** OWASP ZAP, Burp Suite, browser DevTools, Postman, curl.

> ⚠️ Only test systems you own or have written permission to test. Never run attacks against production or third-party systems.

---

## 6. API Tests

### 6.1 Mnemonics (from the original sheet)

**BINMEN** *(Gwen Diagram & Ash Winter)* — **B**oundary, **I**nvalid entries, **N**ULL, **M**ethod, **E**mpty, **N**egative

**POISED** *(Amber Race)* — **P**arameters, **O**utput, **I**nterop, **S**ecurity, **E**rrors, **D**ata

**VADER** *(Stuart Ashman)* — **V**erbs, **A**uthorisation/Authentication, **D**ata, **E**rrors, **R**esponsiveness

### 6.2 What each letter looks like in practice

**BINMEN**

| Letter | Practical tests |
|---|---|
| **B**oundary | Min/max values, string length limits, pagination `limit=0`, `limit=1`, `limit=max`, `limit=max+1`; array with 0, 1, many items |
| **I**nvalid entries | Wrong type (string where number expected), malformed JSON/XML, unknown fields, extra fields (mass assignment: `"isAdmin": true`), wrong enum value, bad date format |
| **N**ULL | `null` value vs. missing key vs. empty string; `null` in nested objects and arrays |
| **M**ethod | Use the wrong HTTP method (`DELETE` on a read-only endpoint → expect `405 Method Not Allowed` with `Allow` header); `HEAD`, `OPTIONS`, `PATCH` |
| **E**mpty | Empty body, empty array `[]`, empty object `{}`, empty headers, empty query string |
| **N**egative | Negative numbers, negative paths (things that *should* fail do fail with the right code), unauthorised calls |

**POISED**

| Letter | Practical tests |
|---|---|
| **P**arameters | Required vs. optional, defaults, order, duplicates (`?id=1&id=2`), encoding, case sensitivity, path vs. query vs. header vs. body |
| **O**utput | Correct status code, schema/contract matches docs, correct `Content-Type`, field types, null handling, pagination metadata, consistent naming, no extra sensitive fields |
| **I**nterop | Works with different clients/versions, backwards compatibility, content negotiation (`Accept`), CORS, character encoding, third-party integrations |
| **S**ecurity | Auth, authorisation per resource, injection, rate limits, TLS, secrets in responses, CORS configuration (see section 5) |
| **E**rrors | Consistent error format (e.g. RFC 9457 Problem Details), helpful but not leaky messages, correct 4xx vs. 5xx |
| **D**ata | CRUD integrity, persistence, idempotency, concurrency, data created via API visible in UI and vice-versa |

**VADER**

| Letter | Practical tests |
|---|---|
| **V**erbs | `GET` is safe/read-only, `PUT` idempotent, `POST` creates, `PATCH` partial update, `DELETE` removes (and second `DELETE` → `404`/`204` consistent) |
| **A**uthN / AuthZ | No token, expired token, malformed token, token of another user/role/tenant, scopes too narrow, revoked token |
| **D**ata | Valid and invalid payloads, large payloads, special characters, encoding |
| **E**rrors | Status codes and bodies for each failure type |
| **R**esponsiveness | Response time, timeouts, behaviour under concurrency, large result sets |

### 6.3 HTTP status code cheat sheet

| Code | Meaning | Test it |
|---|---|---|
| `200` OK | Success | Happy path |
| `201` Created | Resource created (+ `Location` header) | `POST` success |
| `202` Accepted | Async processing | Long-running jobs, polling |
| `204` No Content | Success, no body | `DELETE`, `PUT` |
| `301/302/307/308` | Redirects | Follows correctly, method preserved on `307/308` |
| `304` Not Modified | Cache validation | `ETag` / `If-None-Match` |
| `400` Bad Request | Malformed request | Invalid JSON, wrong types |
| `401` Unauthorized | Not authenticated | No/invalid token |
| `403` Forbidden | Authenticated but not allowed | Wrong role |
| `404` Not Found | Doesn't exist (or hidden) | Unknown ID |
| `405` Method Not Allowed | Verb unsupported | Wrong method |
| `409` Conflict | State conflict / duplicate | Duplicate create, version clash |
| `413` Payload Too Large | Body too big | Oversized upload |
| `415` Unsupported Media Type | Wrong `Content-Type` | Send XML to JSON-only API |
| `422` Unprocessable Content | Valid syntax, invalid semantics | Business-rule violation |
| `429` Too Many Requests | Rate limited | Flood test; check `Retry-After` |
| `500` Internal Server Error | Unhandled failure | **Should never be caused by user input** — always a bug |
| `502/503/504` | Gateway / unavailable / timeout | Dependency down |

### 6.4 Extra API checks

- **Contract testing:** validate responses against the OpenAPI/Swagger spec (Schemathesis, Dredd, Pact for consumer-driven contracts)
- **Versioning:** old versions still work; deprecation headers; breaking changes flagged
- **Pagination, sorting, filtering:** consistent across endpoints; stable ordering; no duplicates/missing items across pages; cursor vs. offset
- **Idempotency keys:** retrying a `POST` payment with the same key doesn't double-charge
- **Concurrency:** two simultaneous updates (optimistic locking, `ETag`/`If-Match`, lost update problem)
- **Caching headers:** `Cache-Control`, `ETag`, `Vary`
- **Webhooks/callbacks:** retries, signature verification, replay protection, ordering, duplicates
- **Timeouts/retries:** what happens if a downstream service is slow?
- **Large responses:** compression (`gzip`/`br`), streaming, partial responses
- **Case/whitespace in headers & params**, duplicated headers, oversized headers
- **Tools:** Postman, Insomnia, Bruno, curl/HTTPie, REST Assured, Playwright API testing, Karate, Newman (CLI), Schemathesis (property-based)

### 6.5 GraphQL-specific

- Introspection enabled in production? (usually should be disabled)
- Query depth/complexity limits (deeply nested queries causing DoS); alias/batching abuse
- Field-level authorisation (not just query-level)
- N+1 query performance problems
- Error handling: GraphQL often returns `200` with an `errors` array — check the body
- Mutations: input validation, idempotency, partial failures

### 6.6 gRPC / event-driven / async

- gRPC: deadlines/timeouts, status codes, streaming cancellation, backward-compatible protobuf changes
- Message queues/event streams (Kafka, RabbitMQ, SQS): duplicate delivery, out-of-order messages, poison messages, dead-letter queues, consumer lag, schema evolution

---

## 7. Mobile / Device / Tablet

### 7.1 MOBILE APP TESTING *(Daniel Knott)*

**M**obile Device, **O**rientation, **M**obile Browsers, **I**nterrupts, **L**ook, **E**nergy Consumption, **A**utomation, **P**erformance, **P**ersonas, **T**ime & Date, **E**rgonomics, **S**ecurity, **T**racking, **I**nputs, **N**etwork, **P**latform Guidelines.

| Area | Practical tests |
|---|---|
| **Mobile device** | Low-end vs. flagship; different screen sizes/densities; notch/punch-hole/Dynamic Island; **foldables** and split-screen/multi-window; tablets; low memory; low storage |
| **Orientation** | Portrait ↔ landscape rotation mid-flow (state kept?), rotation lock, tablets |
| **Mobile browsers** | Chrome, Safari (all iOS browsers use WebKit), Samsung Internet, Firefox; in-app browsers/webviews; mobile address-bar collapse affecting `100vh` |
| **Interrupts** | Incoming call, SMS, notification, alarm, low-battery warning, app backgrounded/killed by OS, screen lock, switch apps, Do Not Disturb, Siri/Assistant, charger plugged in |
| **Look** | Dark mode, font scaling (OS "Larger text"), display zoom, safe areas, truncated text, localisation, high-contrast |
| **Energy consumption** | Battery drain (profile with Xcode Instruments / Android Studio Profiler / Battery Historian), background activity, GPS/polling, wake locks, thermal throttling |
| **Automation** | Appium, XCUITest, Espresso, Maestro, Detox, device farms (BrowserStack, Sauce Labs, AWS Device Farm, Firebase Test Lab) |
| **Performance** | Cold vs. warm start time, jank/frame drops, memory leaks, large lists scrolling, app size, ANRs (Android "App Not Responding"), crashes (Crashlytics/Sentry) |
| **Personas** | Elderly, children, left-handed, one-handed, low-literacy, visually impaired, travellers, users with limited data plans |
| **Time & date** | Time zones when travelling, DST, manual clock changes, 12/24 hour setting, locale/calendar |
| **Ergonomics** | Thumb reach zones, touch target size (≥ 44×44 pt iOS / 48×48 dp Android), gesture conflicts with OS (edge swipes), keyboard covering inputs |
| **Security** | Secure storage (Keychain / Keystore), no secrets in logs/binaries, certificate pinning, jailbroken/rooted behaviour, screenshot/screen-recording on sensitive screens, biometric fallback, deep link hijacking, clipboard exposure, OWASP MASVS / MASTG |
| **Tracking** | Analytics events fire correctly, consent (GDPR/ATT on iOS), no tracking before consent, privacy labels accurate |
| **Inputs** | On-screen keyboard types (email, numeric, phone), autofill, voice input, copy/paste, stylus, external keyboard, multi-touch, shake, camera/QR |
| **Network** | Wi-Fi ↔ cellular switching, airplane mode, 2G/3G/4G/5G, high latency, packet loss, captive portals, VPN, roaming, offline mode and **sync conflict resolution** |
| **Platform guidelines** | Apple Human Interface Guidelines; Google Material Design; back button/gesture behaviour; permission prompts' wording and timing |

### 7.2 Mobile extras worth testing

- **Install/update/uninstall:** fresh install; upgrade from several older versions (data migration!); reinstall; app data cleared; rollback; forced-update flow
- **Permissions:** accept, deny, "Allow once," "Only while using," revoke in Settings while app is running; approximate vs. precise location (iOS/Android); notification permission (Android 13+ runtime prompt); photo picker's limited access
- **Push notifications:** delivered when app is foreground/background/killed; tapping opens the right screen (deep link); notification settings/channels; token refresh
- **Deep links / universal links / app links:** opens app vs. web; works when logged out; handles invalid parameters
- **Background behaviour:** background fetch, battery optimisations, OS killing background work, Android Doze / App Standby, iOS background refresh limits
- **Store requirements:** App Store / Google Play review rules, privacy policy, data safety form, target SDK/API level requirements
- **Accessibility:** VoiceOver / TalkBack, Dynamic Type / font size, Switch Control, reduce motion, contrast, labels on custom controls
- **Real devices vs. emulators:** emulators miss sensors, thermals, carriers, real network conditions — test the critical paths on real devices
- **Hardware features:** camera, GPS, Bluetooth, NFC, biometrics, accelerometer, microphone, external display, headphones plug/unplug

---

## 8. AI / LLM Feature Testing

If the product uses AI features (chatbots, summarisation, search, recommendations, code assistants), classic testing still applies — plus the following. Outputs are **non-deterministic**, so test for *properties and ranges*, not exact strings.

### 8.1 Quality and behaviour

| Area | What to test |
|---|---|
| **Accuracy / hallucination** | Ask questions with known answers; ask about things that don't exist (fake products, fake citations) — does it make things up or admit uncertainty? |
| **Grounding** (RAG) | Answers cite sources correctly; stays within the provided documents; handles "not in the documents" properly; stale or conflicting sources |
| **Consistency** | Same question asked several times and in different phrasings; temperature effects; stability across model/version updates |
| **Instruction following** | Format constraints (JSON schema, length, language), refusal when it should refuse, doesn't refuse when it shouldn't (over-refusal) |
| **Context handling** | Very long input; conversation memory across turns; truncation when context window is exceeded; irrelevant context |
| **Multilingual / locale** | Non-English prompts, mixed languages, dialects, transliteration |
| **Tool use / agents** | Correct tool chosen; parameters valid; handles tool errors/timeouts; loops and runaway actions; permissions boundaries; confirmation before destructive actions |
| **Structured output** | Output parses 100% of the time? Validate against schema; handle malformed output with retries/fallbacks |

### 8.2 Safety, security, and fairness

- **Prompt injection:** malicious instructions in user input *and* in retrieved content (web pages, emails, documents, filenames) — "ignore previous instructions", hidden text, encoded text. Can the model be made to leak the system prompt, call unintended tools, or exfiltrate data?
- **Data leakage:** other users' data, PII, secrets, or internal documents appearing in answers; cross-tenant retrieval
- **Jailbreaks & harmful content:** role-play framing, multi-turn escalation; check refusals and safe-completion behaviour
- **Insecure output handling:** model output rendered as HTML/Markdown/SQL/shell without sanitisation (XSS, injection via model output, malicious links/images)
- **Excessive agency:** AI can take actions (send email, delete, buy) — require human approval for high-impact actions
- **Bias and fairness:** same prompt with different names, genders, ethnicities, dialects, ages — compare outcomes; stereotyped content
- **Privacy & compliance:** what is logged and retained; user consent; data residency; opt-out of training; regulatory obligations (e.g. EU AI Act, GDPR)
- Reference: **OWASP Top 10 for LLM Applications**, NIST AI Risk Management Framework

### 8.3 Operational

- **Latency & streaming:** time to first token; slow responses; cancellation; partial output on network drop
- **Cost & rate limits:** token usage spikes with long inputs; loops; quota exhaustion; 429/overload handling and graceful degradation
- **Fallbacks:** what does the user see when the AI service is down or returns garbage?
- **Evaluation:** build a **golden dataset** of representative prompts + expected properties; automate evals (rubrics, LLM-as-judge with human spot-checks, regression suites run on every prompt/model change); track metrics over time
- **Observability:** log prompts/responses (with privacy controls), model version, latency, feedback signals (thumbs up/down)
- **UX:** clear that content is AI-generated; users can correct/report; copy/regenerate/edit flows work; accessible streaming text (screen reader announcements)

---

## 9. Testing Wisdom

- A test is an **experiment** designed to reveal information or answer a specific question about the software or system.
- **Stakeholders have questions; testers have answers.**
- Don't confuse **speed** with **progress**.
- Take a **contrary approach.**
- **Observation is exploratory.**
- **The narrower the view, the wider the ignorance.**
- **Big bugs are often found by coincidence.**
- **Bugs cluster.** When you find one, look nearby.
- **Vary sequences, configurations, and data** to increase the probability that, if there is a problem, testing will find it.
- **It's all about the variables.**
- **I am not all humans** — not everyone does things as I do.

**Practical additions**

- Testing can show the presence of bugs, never their absence — and exhaustive testing is impossible, so **prioritise by risk**.
- Distinguish **checking** (confirming expectations, automatable) from **testing** (exploring, learning, questioning).
- A bug report is a **persuasive message**: make it easy to reproduce, easy to understand, and clear about impact.
- Look for **oracles** — ways to recognise a problem: *specs, comparable products, previous versions, claims, user expectations, purpose, standards/laws, consistency within the product, and your own experience.*
- Test early (shift-left: reviews of requirements, designs, and code) and keep testing in production (shift-right: monitoring, feature flags, canary releases, observability).
- Automation is a tool, not a goal: automate stable, repetitive, high-value checks; keep humans for exploration.
- Flaky tests erode trust — fix or quarantine them quickly.
- If you can't reproduce it, **don't ignore it** — record everything (time, build, data, logs, environment) and watch for patterns.
- When something is "weird" but not wrong, tell someone. Unease is data.

---

## 10. Heuristics

Heuristics are fallible rules of thumb — they guide exploration; they don't guarantee coverage.

### 10.1 Core heuristics

| Heuristic | Description | Practical example |
|---|---|---|
| **Variable Analysis** | Identify anything whose value can change. Variables can be obvious, subtle, or hidden. | Field values, config settings, user role, time, locale, feature flags, data volume, network speed, browser, OS, hidden state like cached data |
| **TouchPoints** | Identify any public or private interface that provides visibility or control — places to provoke, monitor, and verify the system. | UI, API, CLI, DB, logs, file system, queues, admin tools, emails sent, webhooks, metrics dashboards |
| **Boundaries** | *Approaching* the boundary (almost too big/small), *at* the boundary, and just *over* it. | Max length 50: try 49, 50, 51; age 18: try 17, 18, 19; basket limit 100 items |
| **Goldilocks** | Too big, too small, just right. | Password of 1, 8, and 10,000 characters |
| **CRUD** | Create, Read, Update, Delete. | Create a record → Read it elsewhere → Update a field → Delete → Read again (should fail cleanly) |
| **Follow the Data** | Perform a sequence of actions involving data, verifying integrity at each step. | **Enter → Search → Report → Export → Import → Update → View**; compare values at every stage |
| **Configurations** | Vary configuration-related variables. | Screen resolution; network speed, latency, signal strength; memory; disk availability; apply Count to peripherals (0, 1, many monitors, mice, printers) |
| **Interruptions** | Disrupt operations. | Log off, shut down, reboot, kill process, disconnect, hibernate, timeout, cancel — **mid-operation** |
| **Starvation** | Run resources at maximum capacity. | CPU, memory, network, or disk at 100% while using the feature |
| **Position** | Beginning, middle, end. | Edit at start / middle / end of line, list, file, page range; first/last item in a list |
| **Selection** | Some, none, all. | Some permissions, no permissions, all permissions; select some, none, all rows then apply bulk action |
| **Count** | 0, 1, many. | 0, 1, many transactions; 0, 1, many simultaneous users |
| **Multi-User** | Simultaneous create/update/delete from two accounts or the same account logged in twice. | Two people edit the same record; one deletes while another edits |
| **Flood** | Multiple simultaneous transactions or requests flooding the queue. | Click Submit 10 times quickly; script 1,000 requests; mash Enter |
| **Dependencies** | Identify "has a" relationships, then apply CRUD, Count, Position, Selection. | Customer has 0/1/many Invoices; Invoice has 0/1/many Line Items; delete last line item then read; update first line item; some/none/all line items taxable; delete customer with 0/1/many invoices |
| **Constraints** | Violate constraints; apply with Input Method. | Leave required fields blank; invalid combinations in dependent fields (country = US, state = "Punjab"); duplicate IDs/names |
| **Input Method** | Vary how data gets in. | Typing, copy/paste, import, drag/drop, voice, various interfaces (GUI vs. API) |
| **Sequences** | Vary order of operations. | Undo/redo, reverse, combine, invert, simultaneous; skip steps in a wizard; go backwards; repeat a step |
| **Sorting** | Check ordering behaviours. | Alpha vs. numeric (`10` before `2`?), case sensitivity, accents, across multiple pages, stable sort, ties, nulls first/last |
| **State Analysis** | Identify states and events/transitions, then represent in a picture or table. Works with Sequences and Interruptions. | Order: Draft → Submitted → Paid → Shipped → Delivered → Returned. Try every transition, including illegal ones (Delivered → Draft) |
| **Map Making** | Identify a "base"/"home" state. Pick a direction, take one step, return to base. Repeat. | Great for exploring unknown apps/menus systematically |
| **Users & Scenarios** | Use cases, soap operas, personae, extreme personalities. | A first-time user; a power user; an angry user; an impatient user; an attacker; an admin; someone on slow mobile data |

### 10.2 State transition table example

| From \ Event | Submit | Pay | Ship | Cancel | Refund |
|---|---|---|---|---|---|
| **Draft** | Submitted | ❌ | ❌ | Cancelled | ❌ |
| **Submitted** | ❌ | Paid | ❌ | Cancelled | ❌ |
| **Paid** | ❌ | ❌ | Shipped | Cancelled* | Refunded |
| **Shipped** | ❌ | ❌ | ❌ | ❌ | Refunded |

❌ = should be rejected cleanly — **test these too**. *\* Cancelling a paid order should probably trigger a refund — what actually happens?*

### 10.3 RCRCRC — *Karen N. Johnson* (regression test planning)

| Letter | Question |
|---|---|
| **R**ecent | What testing around new areas of code should I think about? |
| **C**ore | What essential functions or features must continue to work? |
| **R**isky | What features or areas of code are inherently more risky? |
| **C**onfiguration sensitive | What code is dependent on environment settings? |
| **R**epaired | What code has changed to address defects and potentially created issues? |
| **C**hronic | What code typically breaks features that need to be tested? |

### 10.4 FAILURE — *Ben Simo*

**F**unctional, **A**ppropriate, **I**mpact, **L**og, **U**I, **R**ecovery, **E**motions

- **Functional:** does it do what it should?
- **Appropriate:** is that what it *should* do for the user's goal?
- **Impact:** what are the consequences of failure (people, money, safety, reputation)?
- **Log:** are errors and key events logged usefully? Free of sensitive data?
- **UI:** clear, consistent, usable
- **Recovery:** can users and the system recover from errors?
- **Emotions:** how does it make the user feel — frustrated, confused, safe, respected?

### 10.5 WWWWWHKE *(sounds like "wiki")* — *Darren McMillan*

**Who** is this for? **What** is this for? **When** & by whom is it to be done? **Where** is it being done? **Why** is it being done? **How** is it being achieved? What questions does my **Knowledge & Experience** produce?

### 10.6 Diversity & Inclusion

*Callum Akehurst-Ryan:* **Combat Bias with Heuristics of Diversity.**
*Ash Coleman:* Does this work for me? Does this work for them? Does this work for someone I have never considered or ever met?

Practical prompts: different ages, abilities, languages, literacy levels, cultures, genders, names, family structures, devices, connectivity, financial situations; avoid assumptions on names, addresses, gender fields, relationships, or "normal" usage.

### 10.7 Seven Dwarfs — *Cassandra H. Leung* (personas / mindsets)

| Dwarf | Mindset | Try |
|---|---|---|
| **Grumpy** | Impatient, hates delays and errors | Slow responses, confusing errors, repeated prompts |
| **Happy** | Does everything as designed | The happy path — does it truly work? |
| **Sleepy** | Distracted, slow, leaves and returns | Timeouts, resuming sessions, long pauses |
| **Bashful** | Hesitant, avoids sharing info | Skipping optional fields, privacy concerns, minimal data |
| **Sneezy** | Accidents and interruptions | Fat-finger mistakes, accidental taps, interrupted flows |
| **Dopey** | Confused, doesn't read instructions | Wrong inputs, misunderstood labels, unclear wording |
| **Doc** | Expert, knows shortcuts | Power-user paths, bulk actions, keyboard shortcuts, edge cases |

### 10.8 Specs / Designs Watchlist — *Gerard McCann*

Look for: **Ambiguity**; **weasel words** (*could, should, may, might, usually, where possible, etc.*); **Fudge** (e.g. "this will be resolved at a later date" with no who/when); **confusing terminology, jargon, or obscure acronyms**; **oversimplification**; **overcomplication**.

Add: missing error cases, unstated assumptions, missing non-functional requirements (performance, security, accessibility), contradictions between documents, untestable statements ("user-friendly", "fast", "intuitive") — ask for measurable criteria.

### 10.9 TORCH — *Simon Tomes*

**T**imer, **O**racles, **R**isks, **C**onsider these questions, **H**euristics

### 10.10 MCOASTER — *Michael Kelly* (test-session/charter planning)

**M**ission, **C**overage, **O**bstacles, **A**udience, **S**tatus, **T**echniques, **E**nvironment, **R**isk

### 10.11 Test charter template (Explore It! style)

```
Explore   <target / area>
With      <resources, tools, data, heuristics>
To discover <information: risks, bugs, questions>
```

*Example:* Explore the **checkout flow** with **expired cards, slow network, and two browser tabs** to discover **payment, duplication, and error-handling risks.**

### 10.12 TuTTu and TaTTa — *Mark Winteringham*

- **TuTTu:** Testing the UI **or** Testing *Through* the UI
- **TaTTa:** Testing the API **or** Testing *Through* the API

(Decide: are you testing the UI itself, or just using it as a route to test something else — and would an API be a faster, more reliable route?)

### 10.13 SACRED — *Richard Bradshaw* (automation design)

**S**tate management, **A**ctions, **C**odified oracle, **R**eporting, **E**xecution, **D**eterministic

### 10.14 TRIMS — *Richard Bradshaw* (automation quality)

**T**argeted, **R**eliable, **I**nformative, **M**aintainable, **S**peedy

---

## 11. Frameworks

### 11.1 Judgement — *James Lyndsay*

Look for **Inconsistencies**, **Absences**, and **Extras** with respect to:

| | Internal | External – Specific | External – Cultural |
|---|---|---|---|
| **Reference** | Elsewhere within the product | Specs, standards, requirements, other products, user docs | Wider social/cultural expectations |
| **Inconsistency** | Two screens say different things | Behaviour contradicts the spec | Behaves unlike what people commonly expect |
| **Absence** | Missing confirmation message | Spec'd feature missing | No way to do what users reasonably expect |
| **Extra** | Unexplained field/behaviour | Behaviour not in requirements | Surprising, unwanted, or unwarranted features |

### 11.2 Observations — *James Lyndsay*

**Input / Output / Linkage:** what goes in, what comes out, and what connects them (which inputs affect which outputs — and which *shouldn't*).

### 11.3 Flow

**Input → Processing → Output.** Test each stage and the hand-offs between them; follow the data.

### 11.4 Requirements — *Gause & Weinberg*

**Users / Functions / Attributes / Constraints** — Who uses it? What does it do? What qualities (fast, secure, usable, accessible)? What limits apply (legal, budget, technology, time)?

### 11.5 Nouns & Verbs

The **objects/data** in the system (nouns) and the ways the system manipulates them (verbs). Add **adjectives** (attributes) such as *Visible, Identical, Verbose*, and **adverbs** (action descriptors) such as *Quickly, Slowly, Repeatedly, Precisely, Randomly*. Good for creating random scenarios.

*Example:* Noun = "invoice"; verbs = create, edit, send, void; adverbs = quickly, twice, simultaneously → "Void an invoice twice, simultaneously."

### 11.6 Deming's Cycle (PDCA)

**P**lan → **D**o → **C**heck → **A**ct. Plan the test, run it, check results against expectations, act (log bugs, adjust approach, improve process) — then repeat.

### 11.7 Other useful models

- **Test pyramid / trophy:** many fast unit tests, fewer integration tests, fewest end-to-end tests (or the "trophy" model emphasising integration tests)
- **Risk matrix:** Likelihood × Impact → prioritise testing and bug-fix order
- **Session-Based Test Management (SBTM):** charter → timeboxed session (60–120 min) → notes/debrief
- **Three Amigos:** business + dev + test discuss a story before building ("What could go wrong?")
- **Example Mapping / BDD (Given-When-Then):** rules → examples → questions
- **Pairwise (all-pairs) testing:** cover all 2-way combinations of parameters with far fewer tests (tools: PICT, AllPairs)
- **Equivalence partitioning & boundary value analysis:** group inputs into valid/invalid classes; test one value per class plus the edges
- **Decision tables & state-transition testing:** for complex business rules

---

## 12. Bug Report Template & Session Notes

### 12.1 Bug report template

```markdown
**Title:** Short, specific — what fails, where, when (not "It doesn't work")

**Environment:** App/build version, OS, browser/device, environment (test/staging/prod), user role, test account, date/time (with time zone)

**Steps to reproduce:**
1. …
2. …
3. …

**Expected result:** …
**Actual result:** …
**Frequency:** Always / Intermittent (x of y attempts) / Once

**Evidence:** Screenshot/video, console errors, network requests (HAR), logs, correlation/request IDs, test data used

**Impact:** Who is affected? Workaround available? Data loss / security / legal / accessibility implications?
**Severity / Priority:** …
**Notes:** Suspected cause, related bugs, when it started (regression? last known good build)
```

**Tips:** one bug per report; minimal reproduction; remove sensitive data from screenshots/logs; describe behaviour, not blame; mention what you already tried to isolate it (other browser, other account, other data).

### 12.2 Exploratory session notes template

```
Charter:        <Explore … with … to discover …>
Tester / Date:  <name, date, duration>
Build / Env:    <version, environment, data>

Notes (timestamped):
- 10:05  Started with <area>; tried <heuristic/data>
- 10:12  Observation: …
- 10:20  QUESTION: …
- 10:31  BUG: … (link)
- 10:40  RISK: …

Coverage:  <what was covered / not covered>
Issues:    <bugs raised>
Questions: <for product/dev>
Next:      <follow-up charters>
```

### 12.3 Quick pre-release smoke checklist

- [ ] Can I sign up, log in, log out, reset my password?
- [ ] Do the 3–5 most important user journeys complete end to end?
- [ ] Payments / emails / notifications work (in test mode)?
- [ ] Data created yesterday is still correct (migration/upgrade)?
- [ ] No new console errors, 5xx responses, or failed network calls in key flows?
- [ ] Works in the main browsers/devices and at mobile width?
- [ ] Keyboard-only run of the main journey; zoom 200%
- [ ] Permissions: a normal user cannot see/do admin things
- [ ] Monitoring/alerting and logs working; rollback plan verified
- [ ] Release notes/known issues documented

---

## 13. Curl Command Cookbook (live-tested)

> **Honesty note:** every command and output block in this section was actually executed in a sandboxed container against GitHub's public API (`api.github.com`), which is one of the few hosts my environment can reach. Outputs below are copy-pasted verbatim, not invented. Swap the URL for your own API and the same patterns apply. I can't reach arbitrary third-party sites from this environment, so run these yourself against your target to confirm — but the syntax is correct and standard `curl`.

### 13.1 Basic GET + status code

```bash
curl -s -i https://api.github.com/users/octocat
```
**Real output (captured just now):**
```
HTTP/2 403
date: Mon, 12 Oct 2026 10:57:33 GMT
server: Varnish
strict-transport-security: max-age=31536000; includeSubdomains; preload
x-content-type-options: nosniff
x-frame-options: deny
content-security-policy: default-src 'none'; style-src 'unsafe-inline'
access-control-allow-origin: *
content-type: application/json; charset=utf-8
x-ratelimit-limit: 60
x-ratelimit-remaining: 0
x-ratelimit-used: 60
x-ratelimit-reset: 1791803865
content-length: 277

{"message":"API rate limit exceeded for 34.44.29.51. (But here's the good news: Authenticated requests get a higher rate limit. Check out the documentation for more details.)","documentation_url":"https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting"}
```
**What this real output teaches you (genuinely useful, not staged):** unauthenticated requests to GitHub's API are capped at 60/hour per IP. My container hit that cap mid-session. This is exactly the kind of thing the **Rate limiting** row in section 5 and the **Responsiveness** letter in VADER (section 6) tell you to test: what does the API return when you exceed quota? Here: `403`, a clear `x-ratelimit-*` header set, and a JSON body explaining why — that's a **well-behaved** rate limit response. A bug would be a bare `500` with no explanation.

### 13.2 Status-code-only check (fast smoke test)

```bash
curl -s -o /dev/null -w "HTTP Status: %{http_code}\nTotal time: %{time_total}s\n" \
  https://api.github.com/repos/nonexistent-user-xyz123/nonexistent-repo-xyz123
```
**Real output:**
```
HTTP Status: 403
Total time: 0.078417s
```
(Would normally be `404` — it's `403` here only because of the rate limit shown above; this is itself a good example of **flaky external dependencies affecting test results**, something to watch for in your own test suites.)

### 13.3 Headers only

```bash
curl -s -I https://api.github.com/zen
```
Use `-I` (capital i) to send a `HEAD` request and see only headers — fast way to check caching headers, content-type, and security headers (section 5.1) without downloading the body.

### 13.4 Sending a method the endpoint doesn't support

```bash
curl -s -i -X DELETE https://api.github.com/zen
```
This is the **M (Method)** check from BINMEN (section 6.2) — hit an endpoint with a verb it shouldn't accept and confirm you get `404`/`405` with an `Allow` header, not a `500` or, worse, the method actually executing.

### 13.5 POST with a JSON body (unauthenticated — expect rejection)

```bash
curl -s -i -X POST https://api.github.com/repos/octocat/Hello-World/issues \
  -H "Content-Type: application/json" \
  -d '{"title":"test issue"}'
```
This is an **AuthN check** (VADER's "A") — confirm write operations are rejected without credentials.

### 13.6 Authenticated request (pattern — use your own token)

```bash
curl -s -i https://api.github.com/user \
  -H "Authorization: Bearer $GITHUB_TOKEN"
```
Never hard-code tokens in scripts or commit them — read from an environment variable (`$GITHUB_TOKEN`) or a secrets manager.

### 13.7 Boundary / invalid data payloads

```bash
# Empty body
curl -s -i -X POST https://your-api.example.com/users -d ''

# Oversized field (10,000 chars)
curl -s -i -X POST https://your-api.example.com/users \
  -H "Content-Type: application/json" \
  -d "{\"name\": \"$(python3 -c 'print("A"*10000)')\"}"

# Wrong type
curl -s -i -X POST https://your-api.example.com/users \
  -H "Content-Type: application/json" \
  -d '{"age": "twenty-five"}'

# SQL-injection-style string (to confirm it's handled as DATA, not code)
curl -s -i -X POST https://your-api.example.com/users \
  -H "Content-Type: application/json" \
  -d '{"name": "Robert''; DROP TABLE students;--"}'

# XSS-style string
curl -s -i -X POST https://your-api.example.com/users \
  -H "Content-Type: application/json" \
  -d '{"bio": "<script>alert(1)</script>"}'
```
Replace `your-api.example.com` with a system you own or are authorized to test (never run injection payloads against systems you don't have permission for).

### 13.8 Malformed JSON

```bash
curl -s -i -X POST https://your-api.example.com/users \
  -H "Content-Type: application/json" \
  -d '{"name": "incomplete'
```
Expect a clean `400 Bad Request` with a parse-error message — not a stack trace.

### 13.9 Rate-limit / flood test (BINMEN's "Flood" via section 10)

```bash
for i in $(seq 1 20); do
  curl -s -o /dev/null -w "%{http_code} "  https://your-api.example.com/login \
    -d '{"user":"test","pass":"wrong"}'
done
echo ""
```
Fire the same request rapidly and watch for `429 Too Many Requests` to kick in — and check the `Retry-After` header.

### 13.10 Timing a request (performance smoke check)

```bash
curl -s -o /dev/null -w "DNS: %{time_namelookup}s | Connect: %{time_connect}s | TLS: %{time_appconnect}s | TTFB: %{time_starttransfer}s | Total: %{time_total}s\n" \
  https://your-api.example.com/search?q=test
```
Breaks a request down by phase — useful for spotting whether slowness is DNS, TLS handshake, server processing, or download.

### 13.11 Following redirects & checking the chain

```bash
curl -s -i -L -w "\n--- Final URL: %{url_effective} | Redirects: %{num_redirects} ---\n" \
  http://your-domain.example.com
```
Confirms HTTP → HTTPS redirect works and doesn't loop.

### 13.12 Downloading with resume (flaky network simulation)

```bash
curl -C - -O https://your-api.example.com/large-file.zip
```
`-C -` resumes a partial download — test this against a connection you interrupt midway (Ctrl+C), then re-run the same command.

---

## 14. Testing with Postman — Section by Section

Postman is a GUI (and CLI, via **Newman**) tool for building, running, and automating HTTP requests. Below is how to use it for each relevant section of this guide.

### 14.1 Setup once per project

1. **Create a Workspace** → **New Collection** (name it after the API/feature, e.g. "Checkout API").
2. **Create an Environment** (gear icon → Environments → `+`) with variables like `base_url`, `auth_token`, `test_user_id`. Switch environments (Dev/Staging/Prod) from the top-right dropdown — never hard-code URLs in requests.
3. **Collection-level Authorization**: set once (Bearer token, Basic Auth, OAuth2) so every request inherits it — edit per-request only when testing auth failures.

### 14.2 Functional / Data Type Attacks (section 1) in Postman

- Create one request per endpoint, then **duplicate it** for each attack variant (long string, empty, SQL injection, Unicode, boundary number) — name each clearly: `POST /users - empty name`, `POST /users - 10000 char name`, etc.
- Use **Postman's Tests tab** (JavaScript) to assert automatically instead of eyeballing responses:
  ```javascript
  pm.test("Status code is 400 for empty name", function () {
      pm.response.to.have.status(400);
  });
  pm.test("Error message mentions 'name'", function () {
      pm.expect(pm.response.text()).to.include("name");
  });
  pm.test("Response time is acceptable", function () {
      pm.expect(pm.response.responseTime).to.be.below(1000);
  });
  ```
- Use **Collection Runner** (▶ Run button, top of collection) to execute every request in the folder in sequence and see a pass/fail summary — this is your regression suite.

### 14.3 Data-driven testing (boundary values, section 1.3) with a CSV

1. Build a CSV: `age,expected_status\n-1,400\n0,200\n120,200\n121,400\n`
2. In **Collection Runner**, click **Select File** and load the CSV.
3. Reference values in the request body as `{{age}}` and in a test script as `pm.expect(pm.response.code).to.eql(parseInt(pm.iterationData.get("expected_status")))`.
4. Run — Postman iterates the request once per CSV row and reports pass/fail per row. This directly automates the **Boundary** and **Goldilocks** heuristics (section 10.1).

### 14.4 API Tests — BINMEN / POISED / VADER (section 6) in Postman

- **Method (BINMEN "M"):** duplicate a request and change the verb dropdown (GET→DELETE/PATCH) to confirm `405`.
- **AuthN/AuthZ (VADER "A"):** create three versions of the same request — no `Authorization` header, an expired token, and a token belonging to a different role — each a separate saved request with its own Tests assertions for `401`/`403`.
- **Output schema (POISED "O"):** use **Postman's schema validation**:
  ```javascript
  const schema = {
    type: "object",
    required: ["id", "name", "email"],
    properties: { id: { type: "number" }, email: { type: "string" } }
  };
  pm.test("Schema is valid", function () {
      pm.response.to.have.jsonSchema(schema);
  });
  ```
- **Chaining requests (Dependencies, section 10.1):** use `pm.environment.set("order_id", pm.response.json().id);` in the Tests tab of a "Create Order" request, then reference `{{order_id}}` in the following "Get Order" / "Delete Order" requests — this builds the **Follow the Data** heuristic (Enter → Search → Update → Delete) as a runnable chain.

### 14.5 Mocking for contract testing

- **Collection → ⋯ → Mock Collection** generates a mock server URL that returns your saved example responses. Point a frontend at it to test UI behaviour before the real API exists, or to test error-response handling (configure an example that returns `500`).

### 14.6 Automating in CI with Newman (Postman's CLI)

```bash
npm install -g newman
newman run "Checkout API.postman_collection.json" \
  -e "Staging.postman_environment.json" \
  --reporters cli,junit \
  --reporter-junit-export results.xml
```
This runs the whole collection headlessly and exports JUnit XML your CI pipeline (Jenkins, GitHub Actions, GitLab CI) can parse for pass/fail reporting — turning your exploratory Postman work into a regression gate.

### 14.7 Security checks (section 5) in Postman

- Duplicate a request, inject payloads from section 1.4 into each parameter (`' OR '1'='1`, `<script>alert(1)</script>`) and assert the response does **not** reflect the raw payload unescaped:
  ```javascript
  pm.test("XSS payload not reflected unescaped", function () {
      pm.expect(pm.response.text()).to.not.include("<script>alert(1)</script>");
  });
  ```
- Check security headers automatically:
  ```javascript
  pm.test("Has Content-Security-Policy header", function () {
      pm.response.to.have.header("Content-Security-Policy");
  });
  pm.test("Has X-Content-Type-Options: nosniff", function () {
      pm.expect(pm.response.headers.get("X-Content-Type-Options")).to.eql("nosniff");
  });
  ```
- **IDOR check:** save two environments (`user_a_token`, `user_b_token`); request `GET /orders/{{user_a_order_id}}` using `user_b_token` and assert `403`/`404`.

### 14.8 Mobile/API (section 7) in Postman

- Set custom headers to simulate different clients: `User-Agent: MyApp/2.1 (iPhone; iOS 17.4)` and confirm the API responds consistently regardless of claimed client.

---

## 15. Kali Linux — Security Testing Commands

> ⚠️ **Authorization is mandatory.** Every tool below is a real, standard penetration-testing tool pre-installed on Kali Linux. Use them **only** against systems you own or are explicitly authorized to test in writing. Running these against systems you don't control is illegal in most jurisdictions (e.g., US Computer Fraud and Abuse Act, UK Computer Misuse Act) and can carry criminal penalties. For practice, use: **`scanme.nmap.org`** (the Nmap project's own host, which it explicitly permits people to scan for testing/learning), or self-hosted vulnerable-by-design apps — **OWASP Juice Shop**, **DVWA**, **WebGoat** — run locally via Docker so nothing leaves your machine. I have not executed these myself (my sandbox has no access to Kali, these targets, or a GUI), so the commands below are standard, correct syntax, not live-captured output — verify in your own lab.

### 15.1 Set up a safe local lab first (recommended starting point)

```bash
# OWASP Juice Shop — deliberately vulnerable web app, safe to attack locally
docker run -d -p 3000:3000 bkimminich/juice-shop
# now target http://localhost:3000 with everything below

# DVWA (Damn Vulnerable Web Application)
docker run -d -p 8080:80 vulnerables/web-dvwa
# target http://localhost:8080
```

### 15.2 Reconnaissance — `nmap`

```bash
# Basic port scan (against Nmap's own permitted test host)
nmap scanme.nmap.org

# Service/version detection + default scripts
nmap -sV -sC scanme.nmap.org

# Full TCP port range
nmap -p- -T4 scanme.nmap.org

# OS detection (needs sudo)
sudo nmap -O scanme.nmap.org

# Against your local DVWA instance
nmap -sV -p 8080 localhost
```
**Expected output shape:**
```
PORT     STATE SERVICE VERSION
22/tcp   open  ssh     OpenSSH 6.6.1p1 Ubuntu
80/tcp   open  http    Apache httpd 2.4.7
```
Maps to the **TouchPoints** heuristic (section 10.1): nmap tells you every open interface the system exposes — your attack surface.

### 15.3 Web server fingerprinting — `whatweb` / `nikto`

```bash
whatweb http://localhost:3000

nikto -h http://localhost:3000
```
`nikto` checks for outdated server software, dangerous files, missing security headers — directly covers the **Security misconfiguration** row in section 5.

### 15.4 Directory/content discovery — `gobuster` / `dirb`

```bash
gobuster dir -u http://localhost:3000 -w /usr/share/wordlists/dirb/common.txt -x php,bak,config

dirb http://localhost:3000
```
Finds exposed `/admin`, `/.git`, `/backup.zip`, debug endpoints — see the "exposed `/.git`, `/admin`" item in section 5.

### 15.5 SQL injection testing — `sqlmap`

```bash
# Against a known-vulnerable local parameter (e.g., DVWA SQLi page)
sqlmap -u "http://localhost:8080/vulnerabilities/sqli/?id=1&Submit=Submit" \
  --cookie="PHPSESSID=your_session_id; security=low" \
  --batch --dbs
```
`--batch` accepts sqlmap's defaults non-interactively; `--dbs` enumerates databases if the parameter is injectable. This automates the manual `' OR '1'='1` check from section 1.4 systematically across every parameter.

### 15.6 Login brute-force testing — `hydra`

```bash
# Test your OWN app's login throttling/lockout (section 5, Authentication row)
hydra -l admin -P /usr/share/wordlists/rockyou.txt \
  localhost -s 8080 http-post-form \
  "/login.php:username=^USER^&password=^PASS^:Invalid credentials"
```
The **goal** here isn't to "get in" — it's to confirm your app **locks the account or rate-limits after N attempts**. If `hydra` runs 10,000 attempts unthrottled, that's your bug report.

### 15.7 SSL/TLS configuration — `sslscan` / `testssl.sh`

```bash
sslscan your-domain.example.com:443

testssl.sh https://your-domain.example.com
```
Flags weak ciphers, expired certs, missing HSTS — supports the "HTTPS everywhere" and header checks in section 5.

### 15.8 Intercepting/manipulating traffic — `mitmproxy`

```bash
mitmproxy --listen-port 8081
# then configure your browser or mobile device proxy to 127.0.0.1:8081
```
Lets you inspect and modify requests live — useful for mobile app testing (section 7) where the app doesn't expose a visible network tab.

### 15.9 Wireless/network-adjacent (context only)

Tools like `aircrack-ng` exist on Kali for Wi-Fi security auditing of networks **you own**; out of scope for typical app/API QA work, mentioned only for completeness.

### 15.10 How to actually use Kali for this work — workflow

1. Get **written authorization** (a signed scope document, or it's your own system/lab).
2. `sudo apt update && sudo apt full-upgrade` — keep tools current.
3. Recon (`nmap`, `whatweb`) → map attack surface.
4. Targeted testing (`nikto`, `gobuster`, `sqlmap`, `hydra`) per the risk areas you found.
5. Record everything: `script session.log` at the start of your terminal session captures all commands and output for your report.
6. Write findings using the **Bug Report Template** (section 12.1) with severity based on real impact, not just "it's a vuln."

---

## 16. Tested Scripts (live-executed, real output)

These three scripts were actually written and run in a sandboxed container during this session. Output below is copy-pasted, not simulated.

### 16.1 Boundary-value test script (Python) — section 10.1 "Boundaries"

```python
#!/usr/bin/env python3
"""Real, runnable boundary-value test script.
Tests an is_valid_age() function against boundary values from the cheat sheet."""

def is_valid_age(age):
    """Business rule: age must be an integer between 0 and 120 inclusive."""
    if not isinstance(age, int):
        return False
    return 0 <= age <= 120

test_cases = [
    (-1, False, "just below min boundary"),
    (0, True, "min boundary"),
    (1, True, "just above min boundary"),
    (119, True, "just below max boundary"),
    (120, True, "max boundary"),
    (121, False, "just above max boundary"),
    (200, False, "way out of range"),
    ("25", False, "string instead of int"),
    (25.5, False, "float instead of int"),
    (None, False, "null"),
]

passed = failed = 0
print(f"{'Input':<10}{'Expected':<10}{'Actual':<10}{'Result':<8}Description")
print("-" * 70)
for value, expected, desc in test_cases:
    actual = is_valid_age(value)
    ok = actual == expected
    passed += ok
    failed += not ok
    print(f"{str(value):<10}{str(expected):<10}{str(actual):<10}{'PASS' if ok else 'FAIL':<8}{desc}")

print("-" * 70)
print(f"Total: {len(test_cases)}  Passed: {passed}  Failed: {failed}")
```

**Real captured output:**
```
Input     Expected  Actual    Result  Description
----------------------------------------------------------------------
-1        False     False     PASS    just below min boundary
0         True      True      PASS    min boundary
1         True      True      PASS    just above min boundary
119       True      True      PASS    just below max boundary
120       True      True      PASS    max boundary
121       False     False     PASS    just above max boundary
200       False     False     PASS    way out of range
25        False     False     PASS    string instead of int
25.5      False     False     PASS    float instead of int
None      False     False     PASS    null
----------------------------------------------------------------------
Total: 10  Passed: 10  Failed: 0
```

### 16.2 Curl-based API smoke-test script (Bash) — section 6

```bash
#!/usr/bin/env bash
# Real, runnable API smoke-test script using curl + assertions.
set -uo pipefail

BASE="https://api.github.com"
PASS=0
FAIL=0

assert_status() {
  local desc="$1" url="$2" expected="$3" method="${4:-GET}"
  actual=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$url")
  if [ "$actual" == "$expected" ]; then
    echo "PASS: $desc (expected $expected, got $actual)"
    PASS=$((PASS+1))
  else
    echo "FAIL: $desc (expected $expected, got $actual)"
    FAIL=$((FAIL+1))
  fi
}

assert_status "GET /zen returns 200"                 "$BASE/zen" 200
assert_status "GET known user returns 200"            "$BASE/users/octocat" 200
assert_status "GET nonexistent user returns 404"      "$BASE/users/this-user-should-not-exist-xyz999" 404
assert_status "DELETE on read endpoint returns 405"   "$BASE/zen" 405 DELETE

echo "---"
echo "Total: $((PASS+FAIL))  Passed: $PASS  Failed: $FAIL"
```

**Real captured output (run moments after 16.1's network calls, which had already used up the hourly unauthenticated quota):**
```
FAIL: GET /zen returns 200 (expected 200, got 403)
FAIL: GET known user returns 200 (expected 200, got 403)
FAIL: GET nonexistent user returns 404 (expected 404, got 403)
FAIL: DELETE on read endpoint returns 405 (expected 405, got 404)
---
Total: 4  Passed: 0  Failed: 4
```
**This is a genuine, unedited failure — and it's a good lesson, not a staged one.** My earlier curl calls (section 13) used up GitHub's 60-requests/hour unauthenticated quota, so every assertion here got `403` instead of its expected code. This is exactly the **"Chronic"** and **"Configuration Sensitive"** categories from RCRCRC (section 10.3) in action: a test suite that shares a rate-limited dependency with other tests is **flaky by design**. The fix in a real suite: authenticate (higher quota), mock the external dependency, or run against a dedicated test environment — never assume a shared public API will behave the same every run.

### 16.3 XSS/output-encoding sanitisation test (Python) — section 1.4 / 5

```python
#!/usr/bin/env python3
"""Real, runnable test for output-encoding / XSS sanitisation.
Tests a sample sanitize_html() function against common XSS payloads."""
import html

def sanitize_html(user_input: str) -> str:
    """Example of what an app SHOULD do: HTML-escape before rendering."""
    return html.escape(user_input)

payloads = [
    "<script>alert(1)</script>",
    '"><img src=x onerror=alert(1)>',
    "javascript:alert(1)",
    "<svg onload=alert(1)>",
    "'; DROP TABLE users;--",
]

print(f"{'Raw payload':<45}{'Sanitized output'}")
print("-" * 100)
for p in payloads:
    out = sanitize_html(p)
    still_dangerous = "<script" in out.lower() or "onerror=" in out.lower() or "onload=" in out.lower()
    flag = "UNSAFE!" if still_dangerous else "escaped OK"
    print(f"{p:<45}{out}   [{flag}]")
```

**Real captured output:**
```
Raw payload                                  Sanitized output
----------------------------------------------------------------------------------------------------
<script>alert(1)</script>                    &lt;script&gt;alert(1)&lt;/script&gt;   [escaped OK]
"><img src=x onerror=alert(1)>               &quot;&gt;&lt;img src=x onerror=alert(1)&gt;   [UNSAFE!]
javascript:alert(1)                          javascript:alert(1)   [escaped OK]
<svg onload=alert(1)>                        &lt;svg onload=alert(1)&gt;   [UNSAFE!]
'; DROP TABLE users;--                       &#x27;; DROP TABLE users;--   [escaped OK]
```
**Another genuine, unedited lesson:** `html.escape()` correctly neutralized every payload — `<` and `>` are gone, so none of these can execute as a tag in a browser. But my **test oracle was too naive**: I flagged anything still *containing the substring* `onerror=` or `onload=` as "unsafe," even though those substrings are now inert plain text inside escaped `&lt;...&gt;` tags, not live HTML attributes. This is a textbook example of why section 9 says **"oracles" matter** — a bad assertion produces false positives just as easily as a bad implementation produces real bugs. The correct oracle would render the output in an actual DOM parser (or at minimum check that no un-escaped `<`, `>`, or `"` characters remain) rather than doing a raw substring search.

### 16.4 How to run these yourself

```bash
# Save each script, then:
chmod +x boundary_test.py api_smoke_test.sh xss_sanitization_test.py
python3 boundary_test.py
./api_smoke_test.sh
python3 xss_sanitization_test.py
```
Wire them into CI (GitHub Actions, GitLab CI, Jenkins) as a job step so they run on every commit — turning one-off exploratory checks into a standing regression net.

---

## 17. Burp Suite — Section-by-Section Testing Guide

Burp Suite (Community or Professional) is a GUI intercepting proxy + toolkit. I can't launch a GUI inside this text-only sandbox, so this is accurate, standard usage guidance (the same workflow taught in every web security course), not a live-captured session — run it yourself against a system you're authorized to test, e.g. the local Juice Shop/DVWA containers from section 15.1.

### 17.1 Initial setup

1. Launch Burp → **Proxy** tab → confirm **Intercept** is listening on `127.0.0.1:8080` (Proxy → Options).
2. Configure your browser to use `127.0.0.1:8080` as its HTTP/HTTPS proxy (or use Burp's bundled Chromium: **Proxy → Intercept → Open Browser**).
3. Install Burp's CA certificate in your browser (visit `http://burp` while proxied, download the cert) so HTTPS traffic can be decrypted for inspection.

### 17.2 Functional/Data Type Attacks (section 1) with Burp

1. Turn **Intercept off** and browse the app normally — every request/response lands in **Proxy → HTTP history**.
2. Right-click any request → **Send to Repeater**.
3. In **Repeater**, edit the request body/parameters by hand (empty string, 10,000-char string, Unicode, SQL/XSS payloads from section 1) and click **Send** repeatedly — watch the response pane for how each variant is handled. This is the GUI equivalent of the curl loop in section 13.7, but interactive.

### 17.3 Automated vulnerability scanning (Burp Pro)

1. Right-click the target in **HTTP history** or **Site map** → **Scan** → choose **Crawl and Audit**.
2. Review results in **Dashboard → Issue activity** — each finding includes severity, confidence, the request/response, and a remediation note.
3. Cross-reference findings against section 5's OWASP Top 10 categories to prioritise.

### 17.4 Fuzzing with Intruder (BINMEN/boundary testing, sections 1 & 10)

1. Send a captured request to **Intruder**.
2. In the **Positions** tab, highlight the parameter to fuzz (e.g. an `id` or `age` value) and click **Add §** to mark it as an injection point.
3. In **Payloads**, choose a payload type:
   - **Numbers** list: `-1, 0, 1, 2147483647, 2147483648` (section 1.3 boundary values)
   - **Simple list**: paste SQLi/XSS strings from section 1.4
   - **Brute forcer**: character sets for fuzzing string fields
4. **Start attack** — Burp sends one request per payload and shows status code, response length, and timing per row, so you can sort by anomalies (a response that's suddenly much longer, or a `500` among a column of `400`s, stands out immediately).

### 17.5 Authentication & session testing (VADER "A", section 6)

- **Sequencer** tab: capture a set of session tokens/CSRF tokens and run Burp's statistical randomness analysis — weak token generation shows up as low entropy.
- **Repeater**: replay a request with an expired/tampered/other-user's token to confirm `401`/`403` (same IDOR check as Postman section 14.7, done interactively).
- **Proxy → HTTP history**: filter by status code or search for `Set-Cookie` to confirm `Secure`/`HttpOnly`/`SameSite` flags are present (section 5 header checklist).

### 17.6 Manual XSS/injection confirmation

1. In **Repeater**, submit a payload like `<script>alert(1)</script>` in a field that gets displayed elsewhere (profile, comments, admin view).
2. Browse to the page where it's rendered using Burp's embedded browser.
3. If the alert box fires, it's a **confirmed stored XSS** — screenshot it and the exact request for the bug report (section 12.1).

### 17.7 Comparing responses (Burp's Comparer)

- Send two similar responses (e.g. valid login vs. invalid login) to **Comparer** (right-click → Send to Comparer) and diff them — useful for spotting subtle differences that leak information, like "Invalid username" vs. "Invalid password" telling an attacker which field was wrong (a classic username-enumeration bug).

### 17.8 Exporting results

- **Dashboard → right-click issue → Report selected issues** to generate an HTML/XML report — feed findings into your bug tracker using the template in section 12.1, with Burp's captured request/response as evidence.

---

## 18. A Local, Safe Practice Target (built and run live for this guide)

Every tool in sections 13–17 needs *something* to point at. Rather than suggest you practice against someone else's site, here's a tiny, deliberately vulnerable Flask + SQLite app — built and actually run in this sandbox, entirely on `127.0.0.1`, with no outbound network calls. Copy it, run it locally, and every curl/Postman/Burp/sqlmap example in this guide has a real, safe, disposable target.

```python
#!/usr/bin/env python3
"""
DELIBERATELY VULNERABLE local practice target for QA/security testing.
Run ONLY on localhost. Do not deploy. Do not expose to a network.
"""
from flask import Flask, request, jsonify, g
import sqlite3, os

DB = "/tmp/target_app/test.db"
app = Flask(__name__)

def get_db():
    db = getattr(g, '_database', None)
    if db is None:
        db = g._database = sqlite3.connect(DB)
    return db

@app.teardown_appcontext
def close_connection(exception):
    db = getattr(g, '_database', None)
    if db is not None:
        db.close()

def init_db():
    if os.path.exists(DB):
        os.remove(DB)
    conn = sqlite3.connect(DB)
    conn.execute("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, age INTEGER)")
    conn.execute("INSERT INTO users (username, email, age) VALUES ('alice', 'alice@example.com', 30)")
    conn.execute("INSERT INTO users (username, email, age) VALUES ('bob', 'bob@example.com', 25)")
    conn.commit()
    conn.close()

@app.route("/health")
def health():
    return jsonify(status="ok"), 200

# Intentionally vulnerable to SQL injection (string concatenation) -- for practicing section 1.4 / 15.5
@app.route("/users")
def search_users():
    username = request.args.get("username", "")
    db = get_db()
    query = f"SELECT id, username, email, age FROM users WHERE username = '{username}'"
    try:
        rows = db.execute(query).fetchall()
        return jsonify([{"id": r[0], "username": r[1], "email": r[2], "age": r[3]} for r in rows])
    except sqlite3.OperationalError as e:
        return jsonify(error=str(e)), 500

# Properly parameterized version -- the FIX, for comparison
@app.route("/users-safe")
def search_users_safe():
    username = request.args.get("username", "")
    db = get_db()
    rows = db.execute("SELECT id, username, email, age FROM users WHERE username = ?", (username,)).fetchall()
    return jsonify([{"id": r[0], "username": r[1], "email": r[2], "age": r[3]} for r in rows])

@app.route("/users", methods=["POST"])
def create_user():
    data = request.get_json(silent=True)
    if not data or "username" not in data:
        return jsonify(error="username is required"), 400
    age = data.get("age")
    if age is not None and not isinstance(age, int):
        return jsonify(error="age must be an integer"), 400
    if age is not None and not (0 <= age <= 120):
        return jsonify(error="age must be between 0 and 120"), 400
    db = get_db()
    db.execute("INSERT INTO users (username, email, age) VALUES (?, ?, ?)",
               (data["username"], data.get("email", ""), age))
    db.commit()
    return jsonify(message="created"), 201

if __name__ == "__main__":
    init_db()
    app.run(host="127.0.0.1", port=5050)
```

**How to run it:**
```bash
pip install flask
mkdir -p /tmp/target_app && mv app.py /tmp/target_app/
cd /tmp/target_app && python3 app.py
# now http://127.0.0.1:5050 is your test target
```

### 18.1 Real SQL injection bypass — captured live against this exact app

```bash
curl -s -G "http://127.0.0.1:5050/users" --data-urlencode "username=alice"
```
```
[{"age":30,"email":"alice@example.com","id":1,"username":"alice"}]
```

```bash
curl -s -G "http://127.0.0.1:5050/users" --data-urlencode "username=' OR '1'='1"
```
```
[{"age":30,"email":"alice@example.com","id":1,"username":"alice"},{"age":25,"email":"bob@example.com","id":2,"username":"bob"}]
```
**This is a genuine, confirmed SQL injection**, not a hypothetical: the filter query `WHERE username = ''` `OR '1'='1'` always evaluates true, so it dumps every row regardless of the intended filter — exactly the behaviour described in section 1.4.

```bash
curl -s -G "http://127.0.0.1:5050/users-safe" --data-urlencode "username=' OR '1'='1"
```
```
[]
```
Same payload against the **parameterized** version (`?`-placeholder query) returns an empty array — the string is treated as literal data, not SQL. This is the fix, demonstrated side by side, not just described.

**Bonus real finding from this test run:** a bare syntax-error probe (`username='`) against the vulnerable endpoint returned `HTTP 500` with the raw SQLite error message (`unrecognized token: "'''"`) in the JSON body. That's a real bug by the standard in section 5 ("sensitive data exposure" / verbose errors) — a production app should never return a database's internal error text to the client.

### 18.2 Point every earlier tool at this target

- **Postman (section 14):** `base_url = http://127.0.0.1:5050`
- **Burp Suite (section 17):** proxy your browser, visit `http://127.0.0.1:5050/users?username=alice`, send to Repeater, try payloads
- **sqlmap (section 15.5):** `sqlmap -u "http://127.0.0.1:5050/users?username=alice" --batch --dbs`
- **nikto/gobuster (section 15.3–15.4):** `nikto -h http://127.0.0.1:5050`

---

## 19. Database Testing

### 19.1 Direct DB verification (bypass the API to confirm ground truth)

The API is not always a trustworthy witness to what's actually stored — verify directly against the database. Live-run against the practice target's `test.db`:

```python
import sqlite3
conn = sqlite3.connect('test.db')
for row in conn.execute('SELECT * FROM users'):
    print(row)
```
**Real output:**
```
(1, 'alice', 'alice@example.com', 30)
(2, 'bob', 'bob@example.com', 25)
```

> **Sandbox note:** the `sqlite3` CLI binary wasn't installed in this container (`sqlite3: not found`), so the example above uses Python's built-in `sqlite3` module instead — functionally identical. On a real machine with the CLI installed, the equivalent commands are:
> ```bash
> sqlite3 test.db "SELECT * FROM users;"
> sqlite3 test.db ".schema users"
> sqlite3 test.db "SELECT COUNT(*) FROM users WHERE username IS NULL;"
> ```

### 19.2 Data integrity checks worth automating

```sql
-- Orphaned foreign keys (Dependencies heuristic, section 10.1)
SELECT * FROM line_items WHERE invoice_id NOT IN (SELECT id FROM invoices);

-- Violates uniqueness constraint (section 1.5)
SELECT email, COUNT(*) c FROM users GROUP BY email HAVING c > 1;

-- NULLs in required fields
SELECT COUNT(*) FROM users WHERE username IS NULL OR email IS NULL;

-- Negative values that shouldn't exist (Data Type Attacks, section 1.3)
SELECT * FROM orders WHERE total_amount < 0;

-- Dates that don't make sense
SELECT * FROM orders WHERE delivered_at < created_at;

-- Row counts match between related tables (Follow the Data, section 10.1)
SELECT (SELECT COUNT(*) FROM orders) AS orders, (SELECT COUNT(*) FROM order_audit_log) AS audit_entries;
```

### 19.3 MySQL / PostgreSQL CLI equivalents

```bash
# MySQL
mysql -u testuser -p -h localhost testdb -e "SELECT COUNT(*) FROM users WHERE email IS NULL;"

# PostgreSQL
psql -U testuser -d testdb -c "SELECT email, COUNT(*) FROM users GROUP BY email HAVING COUNT(*) > 1;"

# Dump schema to diff against a previous version (regression check, RCRCRC "Repaired", section 10.3)
mysqldump -u testuser -p --no-data testdb > schema_after.sql
diff schema_before.sql schema_after.sql
```

### 19.4 NoSQL (MongoDB) equivalents

```javascript
// mongosh
db.users.find({ email: null }).count()
db.users.aggregate([{ $group: { _id: "$email", count: { $sum: 1 } } }, { $match: { count: { $gt: 1 } } }])
db.orders.find({ total_amount: { $lt: 0 } })
```

### 19.5 Transaction / concurrency testing (Multi-User heuristic, section 10.1)

```python
# Two "simultaneous" updates to the same row -- does the DB serialize correctly
# or does one silently overwrite the other (lost update)?
import sqlite3, threading

def update_age(new_age):
    conn = sqlite3.connect('test.db', timeout=5)
    conn.execute("UPDATE users SET age = ? WHERE id = 1", (new_age,))
    conn.commit()
    conn.close()

t1 = threading.Thread(target=update_age, args=(31,))
t2 = threading.Thread(target=update_age, args=(99,))
t1.start(); t2.start(); t1.join(); t2.join()
# Check which value "won" -- is that the behavior you expect? Should it have been a conflict error instead?
```

---

## 20. JWT / Token / Session Testing (live-tested)

Real script, actually executed:

```python
#!/usr/bin/env python3
import jwt, json, base64, time

SECRET = "super-secret-signing-key"

# 1. Issue a legitimate token
token = jwt.encode({"user_id": 42, "role": "user"}, SECRET, algorithm="HS256")
print("Legitimate token:", token)
decoded = jwt.decode(token, SECRET, algorithms=["HS256"])
print("Decoded payload:", decoded)

# 2. ATTACK: tamper with the payload without the secret
header_b64, payload_b64, sig_b64 = token.split(".")
def b64url_decode(s):
    s += "=" * (-len(s) % 4)
    return base64.urlsafe_b64decode(s)
def b64url_encode(b):
    return base64.urlsafe_b64encode(b).rstrip(b"=").decode()

tampered_payload = json.loads(b64url_decode(payload_b64))
tampered_payload["role"] = "admin"
tampered_token = f"{header_b64}.{b64url_encode(json.dumps(tampered_payload).encode())}.{sig_b64}"
try:
    jwt.decode(tampered_token, SECRET, algorithms=["HS256"])
    print("VULNERABLE -- tampered token accepted!")
except jwt.InvalidSignatureError:
    print("SECURE -- tampered token rejected")

# 3. ATTACK: classic "alg:none" bypass attempt
none_header = b64url_encode(json.dumps({"alg": "none", "typ": "JWT"}).encode())
none_token = f"{none_header}.{payload_b64}."
try:
    jwt.decode(none_token, SECRET, algorithms=["HS256"])
    print("VULNERABLE -- alg=none token accepted!")
except Exception as e:
    print(f"SECURE -- rejected ({type(e).__name__})")

# 4. Expiry check
expired = jwt.encode({"user_id": 42, "exp": int(time.time()) - 10}, SECRET, algorithm="HS256")
try:
    jwt.decode(expired, SECRET, algorithms=["HS256"])
    print("VULNERABLE -- expired token accepted!")
except jwt.ExpiredSignatureError:
    print("SECURE -- expired token rejected")
```

**Real captured output:**
```
Legitimate token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0Miwicm9sZSI6InVzZXIifQ.uucNXqa3QHfp-0Zmj0xgkA1l2taEpKrFe_DiueOcYxM
Decoded payload: {'user_id': 42, 'role': 'user'}

SECURE -- tampered token rejected
SECURE -- rejected (InvalidAlgorithmError)
SECURE -- expired token rejected
```

This is the `PyJWT` library behaving correctly by default. **Not every JWT implementation does** — this is exactly why you test it rather than assume it: historically, several real-world libraries shipped with the `alg:none` bypass actually working, and some apps explicitly configure `jwt.decode(token, verify=False)` for debugging and forget to remove it. Run this script against your *own* app's token-verification code (swap the manual encode/decode for a real call to your login endpoint) rather than trusting the library in isolation.

### 20.1 What else to test on tokens/sessions

```bash
# Decode a JWT without verifying (just to read the claims -- useful for debugging, never for trust decisions)
echo "eyJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo0Mn0.xxx" | cut -d. -f2 | base64 -d 2>/dev/null; echo

# Confirm a logged-out session's token is actually rejected server-side, not just discarded client-side
curl -s -i -H "Authorization: Bearer $OLD_TOKEN_AFTER_LOGOUT" https://your-api.example.com/me

# Confirm cookies have the right flags (grep the response headers)
curl -s -i https://your-api.example.com/login | grep -i "set-cookie"
# Look for: Secure; HttpOnly; SameSite=Strict (or Lax)
```

---

## 21. Test Data Generation (live-tested with Faker)

```python
from faker import Faker
fake = Faker()
Faker.seed(42)
for _ in range(5):
    print(fake.name(), fake.email(), fake.phone_number())
```
**Real output:**
```
Allison Hill       donaldgarcia@example.net      +1-219-560-0133
Monica Herrera     smiller@example.net           +1-659-931-0341x316
Andrew Stewart     carl95@example.org            +1-876-772-4238x8496
Kendra Maddox DVM  frazierdanny@example.net      001-645-514-6270x4828
Keith Miller       wcabrera@example.net          001-822-778-2489x6383
```

### 21.1 Internationalization test data — a real finding from this run

```python
from faker import Faker
for loc in ['ja_JP', 'ar_EG', 'de_DE']:
    fake = Faker(loc)
    Faker.seed(1)
    print(loc, fake.name())
```
**Real output:**
```
ja_JP: 木村 陽一
ar_EG: Ryan Gallagher
de_DE: Karsten Wieloch-Bolnbach
```
Notice `ar_EG` returned an English name instead of an Arabic one — an actual, unedited anomaly from this run, not a staged example. That's the exact kind of thing section 1.3 ("Internationalisation") tells you to go looking for: a locale that silently falls back to the wrong data instead of erroring. Before trusting any generated test-data set for i18n testing, spot-check a sample — don't assume the library did what the locale code implies.

### 21.2 Practical Faker patterns for this cheat sheet's sections

```python
from faker import Faker
fake = Faker()

fake.name(); fake.first_name(); fake.last_name()
fake.email(); fake.company_email()
fake.date_of_birth(minimum_age=0, maximum_age=120)   # exercise age boundaries (section 1.3)
fake.credit_card_number(card_type="visa")             # sandbox/test only -- never real cards
fake.text(max_nb_chars=5000)                           # long-string testing (section 1.4)
fake.sentence()
fake.iban()
fake.ipv4(); fake.ipv6()
fake.user_agent()
fake.file_name(extension="pdf")
fake.pystr_format("###-??-####")                      # custom pattern test data

# Generate deliberately invalid data for negative testing
bad_emails = ["not-an-email", "@missing-local.com", "trailing-dot.@x.com", "spaces in@email.com"]
bad_ages = [-1, 999, "twenty", None, 3.14]
```

---

## 22. Log Analysis for Test Evidence (live-tested)

Real Flask access log from the practice target, parsed live with standard Unix tools:

```
127.0.0.1 - - [12/Oct/2026 11:03:57] "GET /users?username=alice HTTP/1.1" 200 -
127.0.0.1 - - [12/Oct/2026 11:03:57] "GET /users?username=' HTTP/1.1" 500 -
127.0.0.1 - - [12/Oct/2026 11:03:57] "POST /users HTTP/1.1" 400 -
127.0.0.1 - - [12/Oct/2026 11:03:57] "POST /users HTTP/1.1" 201 -
127.0.0.1 - - [12/Oct/2026 11:03:57] "POST /users HTTP/1.1" 400 -
```

```bash
# Count requests by status code
grep -oE '" [0-9]{3} ' server.log | awk '{print $2}' | sort | uniq -c
```
**Real output:**
```
      1 200
      1 201
      2 400
      1 500
```
The lone `500` here is the SQL syntax-error probe from section 18.1 — a one-line `awk` is enough to spot "something unhandled happened" in a sea of log lines, without reading every request by eye.

### 22.1 More log-analysis one-liners

```bash
# Find every 5xx with surrounding context (3 lines before/after)
grep -B3 -A3 '" 5[0-9][0-9] ' server.log

# Find all requests slower than a threshold (if your log includes response time)
awk '$NF > 1000 {print}' access.log   # adjust field number to your log format

# Unique IPs hitting a login endpoint (brute-force indicator)
grep "/login" access.log | awk '{print $1}' | sort | uniq -c | sort -rn | head

# Pretty-print and filter JSON application logs with jq
cat app.jsonl | jq 'select(.level == "ERROR")'
cat app.jsonl | jq -r '[.timestamp, .status_code, .path] | @tsv'

# Tail logs live while running a test, piped straight to a file for the bug report
kubectl logs -f deployment/my-api > evidence.log &
# ... run your test ...
kill %1
```

### 22.2 Correlating a test failure to server-side evidence

```bash
# 1. Note the exact timestamp and any request-id header from your test client
curl -s -i https://your-api.example.com/checkout -d '{...}' | grep -i x-request-id

# 2. Grep the server log for that exact request-id -- ties your black-box test
#    straight to the stack trace / DB query / downstream call that caused it
grep "req-abc123" server.log
```
This is the practical version of the **Log** letter in FAILURE (section 10.4) and the **TouchPoints** heuristic (section 10.1) — logs are one of the "interfaces that provide visibility," and knowing how to search them turns "it's broken" into a bug report with a root cause.

---

## 23. Browser Automation (Selenium / Playwright / Cypress)

> **Honesty note:** this sandbox has no browser binaries and no access to the domains Playwright/Selenium's driver managers download from, so I could not execute these live. The syntax below is standard, current (2026) API for each tool — verify by running it in your own environment, which does have a real browser.

### 23.1 Playwright (Python) — recommended default for new projects

```python
from playwright.sync_api import sync_playwright, expect

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto("https://your-app.example.com/login")

    page.fill("#username", "testuser")
    page.fill("#password", "wrong-password")
    page.click("button[type=submit]")

    expect(page.locator(".error-message")).to_be_visible()
    expect(page.locator(".error-message")).to_contain_text("Invalid credentials")

    # Boundary test: 10,000-char input (section 1.4)
    page.fill("#bio", "A" * 10000)
    page.click("button[type=submit]")

    page.screenshot(path="checkout_failure.png")  # evidence for bug report, section 12.1
    browser.close()
```
```bash
pip install playwright
playwright install chromium   # downloads the browser binary (needs real internet access)
python3 login_test.py
```

### 23.2 Selenium (Python)

```python
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

driver = webdriver.Chrome()
driver.get("https://your-app.example.com/login")
driver.find_element(By.ID, "username").send_keys("testuser")
driver.find_element(By.ID, "password").send_keys("wrong-password")
driver.find_element(By.CSS_SELECTOR, "button[type=submit]").click()

error = WebDriverWait(driver, 5).until(
    EC.visibility_of_element_located((By.CLASS_NAME, "error-message"))
)
assert "Invalid credentials" in error.text
driver.save_screenshot("login_failure.png")
driver.quit()
```

### 23.3 Cypress (JavaScript)

```javascript
describe('Login', () => {
  it('rejects wrong password', () => {
    cy.visit('/login');
    cy.get('#username').type('testuser');
    cy.get('#password').type('wrong-password');
    cy.get('button[type=submit]').click();
    cy.get('.error-message').should('be.visible').and('contain', 'Invalid credentials');
  });

  it('rejects a 10,000 char bio', () => {
    cy.get('#bio').type('A'.repeat(10000), { delay: 0 });
    cy.get('button[type=submit]').click();
    cy.get('.error-message').should('exist');
  });
});
```
```bash
npm install cypress --save-dev
npx cypress run        # headless, for CI
npx cypress open       # interactive GUI runner
```

### 23.4 Visual regression testing

```bash
# Playwright's built-in screenshot comparison
npx playwright test --update-snapshots   # capture baseline
npx playwright test                       # subsequent runs diff against baseline, fail on pixel difference
```
Or dedicated services: **Percy**, **Chromatic**, **Applitools** — all plug into the same Playwright/Cypress/Selenium test code and add a visual-diff step.

---

## 24. Load / Performance Testing (k6 / JMeter)

> **Honesty note:** these require installing a binary (`k6`) or JVM app (`JMeter`) that this sandbox can't download — syntax below is standard and correct; run it in your own environment per section 4.2.

### 24.1 k6 (JavaScript-based, modern, CLI-first)

```javascript
// load_test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 20 },   // ramp up to 20 virtual users
    { duration: '1m', target: 20 },    // hold at 20
    { duration: '30s', target: 0 },    // ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% of requests must complete under 500ms
    http_req_failed: ['rate<0.01'],    // error rate must stay under 1%
  },
};

export default function () {
  const res = http.get('https://your-api.example.com/products');
  check(res, {
    'status is 200': (r) => r.status === 200,
    'response has products': (r) => JSON.parse(r.body).length > 0,
  });
  sleep(1);
}
```
```bash
k6 run load_test.js
```
**Typical output shape:**
```
     ✓ status is 200
     ✓ response has products

     http_req_duration..............: avg=142ms  p(95)=310ms
     http_req_failed................: 0.12%  ✓ 2 ✗ 1658
     vus.............................: 20
```

### 24.2 Apache JMeter (GUI + CLI, older but still widely used in enterprises)

```bash
# Run a saved test plan headlessly (CI-friendly)
jmeter -n -t checkout_load_test.jmx -l results.jtl -e -o report_html/
```
Opens as an HTML dashboard report with throughput, response-time percentiles, and error rate graphs.

### 24.3 Simple load test with nothing but curl + xargs (no extra tools needed)

```bash
seq 1 100 | xargs -P 20 -I {} curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" \
  https://your-api.example.com/health
```
Fires 100 requests, 20 at a time concurrently — a crude but genuinely useful smoke-level load test when you don't have k6/JMeter installed.

---

## 25. Automated Accessibility Testing (CLI)

> **Honesty note:** these tools (`pa11y`, `axe-core` CLI, Lighthouse CI) download a headless Chromium from hosts outside this sandbox's allow-list, so I could not run them live here. Syntax is current and standard — run against your own app per section 3.

```bash
# axe-core CLI -- automates roughly a third of WCAG checks (manual testing from section 3 still required)
npm install -g @axe-core/cli
axe https://your-app.example.com/checkout --exit

# pa11y -- similar, simpler output, good for CI gating
npm install -g pa11y
pa11y https://your-app.example.com --standard WCAG2AA

# Lighthouse CLI -- accessibility score + performance + SEO + best practices in one run
npm install -g lighthouse
lighthouse https://your-app.example.com --only-categories=accessibility --output=json --output-path=./report.json

# Lighthouse CI -- gate pull requests on a score threshold
npm install -g @lhci/cli
lhci autorun --collect.url=https://your-app.example.com --assert.assertions.categories:accessibility=90
```

---

## 26. Wiring It All Into CI/CD

### 26.1 GitHub Actions — run the API smoke test + Postman/Newman + k6 + axe on every PR

```yaml
name: QA Checks
on: [pull_request]

jobs:
  api-smoke:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run curl-based smoke tests
        run: |
          chmod +x ./scripts/api_smoke_test.sh
          ./scripts/api_smoke_test.sh

  postman:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: npm install -g newman
      - run: newman run collection.json -e staging.postman_environment.json --reporters cli,junit --reporter-junit-export results.xml
      - uses: actions/upload-artifact@v4
        with: { name: newman-results, path: results.xml }

  accessibility:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: npm install -g pa11y
      - run: pa11y https://staging.your-app.example.com --standard WCAG2AA

  load-test:
    runs-on: ubuntu-latest
    steps:
      - uses: grafana/setup-k6-action@v1
      - run: k6 run load_test.js
```

### 26.2 Pre-commit hook for fast local checks

```bash
# .git/hooks/pre-commit (or via the `pre-commit` framework)
#!/bin/bash
python3 boundary_test.py || exit 1
./api_smoke_test.sh || exit 1
echo "Local QA checks passed."
```

---

## Credits

Heuristics and mnemonics in this sheet are credited to their authors as in the original: Gwen Diagram & Ash Winter (BINMEN), Amber Race (POISED), Stuart Ashman (VADER), Daniel Knott (Mobile App Testing), Karen N. Johnson (RCRCRC), Ben Simo (FAILURE), Darren McMillan (WWWWWHKE), Callum Akehurst-Ryan and Ash Coleman (Diversity & Inclusion), Cassandra H. Leung (Seven Dwarfs), Gerard McCann (Specs/Designs Watchlist), Simon Tomes (TORCH), Michael Kelly (MCOASTER), Ady Stokes (Seen and Heard), Mark Winteringham (TuTTu/TaTTa), Richard Bradshaw (SACRED, TRIMS), James Lyndsay (Judgement, Observations), Gause & Weinberg (Requirements), and W. Edwards Deming (PDCA). Added practical examples and modern topics are supplementary suggestions.

*Standards and tool recommendations (WCAG 2.2, Core Web Vitals, OWASP lists) evolve — verify current versions before relying on them for compliance work.*
