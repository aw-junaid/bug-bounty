# Software Testing Cheat Sheet — Expanded Edition

A practical, detailed reference for exploratory testers, QA engineers, and developers. It keeps everything from the original one-page sheet and adds concrete example inputs, what to look for, and modern areas (WCAG 2.2, GraphQL, OWASP, Core Web Vitals, AI/LLM features, accessibility, mobile OS behaviours).

> **How to use this:** Don't run it top-to-bottom. Pick the sections that match the thing under test, pick a few heuristics, set a timebox (e.g. 45 minutes), and take notes as you go. Always ask: *"What would I expect to happen, and what actually happened?"*

## Table of Contents

1. [Data Type Attacks](#1-data-type-attacks)
2. [Web Tests](#2-web-tests)
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

## Credits

Heuristics and mnemonics in this sheet are credited to their authors as in the original: Gwen Diagram & Ash Winter (BINMEN), Amber Race (POISED), Stuart Ashman (VADER), Daniel Knott (Mobile App Testing), Karen N. Johnson (RCRCRC), Ben Simo (FAILURE), Darren McMillan (WWWWWHKE), Callum Akehurst-Ryan and Ash Coleman (Diversity & Inclusion), Cassandra H. Leung (Seven Dwarfs), Gerard McCann (Specs/Designs Watchlist), Simon Tomes (TORCH), Michael Kelly (MCOASTER), Ady Stokes (Seen and Heard), Mark Winteringham (TuTTu/TaTTa), Richard Bradshaw (SACRED, TRIMS), James Lyndsay (Judgement, Observations), Gause & Weinberg (Requirements), and W. Edwards Deming (PDCA). Added practical examples and modern topics are supplementary suggestions.

*Standards and tool recommendations (WCAG 2.2, Core Web Vitals, OWASP lists) evolve — verify current versions before relying on them for compliance work.*
