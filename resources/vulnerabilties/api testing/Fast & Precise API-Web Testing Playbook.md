# Fast & Precise API/Web Testing Playbook — Multi-Target Bug Bounty Session

Built for speed across many HackerOne programs in one sitting, without losing the small details that hide real bugs. Use this as a per-target checklist, not a one-time read.

## 0. Two-Minute Setup Per Target (do this every time, no exceptions)

- [ ] Read the program's policy page: in-scope assets, explicitly out-of-scope tests (often: DoS, automated scanners without rate-limit, social engineering, physical), and any "safe harbor" restrictions.
- [ ] Note the reward focus in the policy — programs often flag what they care about (e.g., "we especially want auth/IDOR reports," "low-severity XSS won't be rewarded") — spend your limited time where they're actually paying.
- [ ] Confirm your test accounts are allowed (most programs want you to self-register, not use real user data).
- [ ] Set a global rate cap in your tooling (Burp Intruder throttle, or a `time.sleep()` in scripts) matched to what the policy allows — getting IP-banned mid-test wastes the rest of your session.

## 1. Fast Recon (5–10 min per target)

Goal: max attack surface, min time.

```bash
# Subdomains (only if program scope includes *.domain.com)
subfinder -d target.com -silent | httpx -silent -status-code -title -tech-detect

# JS file mining for hidden endpoints, keys, and API paths
katana -u https://target.com -jc -d 3 -silent | grep -E "\.js$" > js_files.txt
cat js_files.txt | while read url; do
  curl -s "$url" | grep -oE "(\/api\/[a-zA-Z0-9_\-\/]+|\"[a-zA-Z0-9_]+_key\"|\"[a-zA-Z0-9_]+_token\")"
done

# Wayback/historical endpoints — catches deprecated/shadow API versions
waybackurls target.com | grep -E "/api/|/v[0-9]+/" | sort -u

# Swagger/OpenAPI/GraphQL auto-discovery
for path in /swagger.json /swagger-ui.html /api-docs /v2/api-docs /openapi.json /graphql /graphiql; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "https://target.com$path")
  [ "$code" != "404" ] && echo "$path -> $code"
done
```

- [ ] Proxy the mobile app too if in scope — often has endpoints the web app doesn't expose (see pinning bypass in the earlier doc). Mobile-only endpoints are frequently under-tested by other researchers, meaning less duplicate-report risk.
- [ ] Diff the JS bundle's API calls against what Burp's site map actually saw during manual browsing — anything in JS but not in your site map is a lead on unused/hidden functionality.

## 2. Priority Order (test in this order — highest hit-rate first)

Based on what actually pays out across bounty programs, in order:

1. **BOLA/IDOR** — still the single highest-yield class on almost every program.
2. **Auth/session flaws** — password reset poisoning, JWT issues, SSO/OAuth misconfig.
3. **Business logic abuse** — race conditions on payment/coupon/quantity fields, negative numbers, workflow-state skipping.
4. **SSRF** — especially via any "import from URL," webhook, or file-fetch feature.
5. **Injection** (SQLi, NoSQLi, command injection) — lower hit-rate on modern stacks but high severity when found.
6. **XSS/CSRF** — high volume of reports already exist here on most programs; only worth deep time if you spot something unusual (stored, in an unexpected context, or bypassing a specific WAF/filter).

Spend disproportionate time on #1–#3. They're where unique, high-severity findings still exist even on heavily-tested programs.

## 3. The "Don't Miss" Checklist — Small Details That Hide Real Bugs

This is the part that's easy to skip when moving fast. Run through these on **every** endpoint you touch, not just the interesting-looking ones.

### Object/ID handling
- [ ] Does the same object respond differently to `GET` vs `PUT` vs `PATCH` vs `DELETE` on authorization? (Very common: `GET` is checked, `DELETE` isn't.)
- [ ] Try the ID in the **body** even when it's normally in the path (`{"id": "other_user_id"}` in a PATCH body while the path still has your own ID) — some frameworks trust body params over path params.
- [ ] Try wrapping the ID in an array: `{"id": ["1001","1002"]}` — some ORMs silently return the first match or all matches.
- [ ] Try a trailing slash, a leading zero, or URL-encoded characters in the ID (`1001/`, `01001`, `%31%30%30%31`) — some auth middleware matches routes before normalization happens.
- [ ] Test the ID with a different casing if it's non-numeric (`Order-1001` vs `order-1001`) — some databases are case-insensitive but the authz check isn't, or vice versa.

### Auth/session
- [ ] After logout, is the JWT/session token actually invalidated server-side, or does it stay valid until natural expiry? (Test by saving a token, logging out, then replaying it.)
- [ ] Does changing `Content-Type` from `application/json` to `application/xml` or `text/plain` on the same endpoint change how input validation/authorization is applied?
- [ ] Password reset: does the reset token get invalidated after first use? Does it expire? Can the `email` param in the reset request be swapped for a different account's email while keeping your own valid token?
- [ ] OAuth/SSO: is `redirect_uri` validated with exact match, or does it accept subdomains/wildcards/open redirects appended (`https://target.com.attacker.com`)?

### Business logic
- [ ] Negative or zero values in quantity/price/amount fields — does the backend re-validate, or trust the client?
- [ ] Race condition on anything "use once" — coupon codes, referral bonuses, withdrawal requests. Fire the same request 10–20 times in parallel (Burp's "Send group in parallel" in Repeater, or Turbo Intruder) and see if it processes more than once.
- [ ] Can you skip a step in a multi-step workflow by calling a later-stage endpoint directly (e.g., hitting the "confirm order" endpoint without ever hitting "add payment method")?
- [ ] File upload: does changing the file extension only, keeping the magic bytes of a disallowed type, bypass the filter? Does the server re-derive content-type from actual content, or trust the client's declared `Content-Type`/extension?

### API-wide
- [ ] Check every API version you found in recon (`/v1/`, `/v2/`, `/v3/`) against the same test — older versions are frequently missing a fix applied to the current one.
- [ ] Check for verb tampering on every "protected" endpoint: `HEAD`, `OPTIONS`, `TRACE` sometimes bypass auth middleware that only hooks `GET`/`POST`.
- [ ] Check response headers for information leakage even on 403/404 responses — internal hostnames, stack traces, server versions, or debug flags accidentally left in non-2xx paths (people harden the happy path, not the error path).
- [ ] GraphQL: alias the same field/query multiple times in one request to see if per-field rate limiting is actually enforced (`{ a: user(id:1){email} b: user(id:2){email} }` — cheap way to test both BOLA and rate-limit bypass in one shot).

### Small-but-decisive HTTP quirks
- [ ] Does the app treat `X-Original-URL` / `X-Rewrite-URL` headers as the actual route (common in some reverse-proxy setups) — can bypass path-based access rules.
- [ ] Unicode normalization: does `%E2%80%8B` (zero-width space) or full-width characters (`％61dmin`) get normalized differently by the WAF vs. the app, letting a blocked keyword through?
- [ ] HTTP/1.0 vs 1.1 request smuggling potential if there's a reverse proxy/load balancer in front — worth a quick check with `smuggler` if the program scope explicitly allows this kind of testing (many don't; check first).

## 4. Fast Testing Loop Per Endpoint (repeatable, ~2 min each)

For every endpoint you find, run this exact sequence — it's fast enough to apply broadly instead of picking and choosing:

1. Request as yourself (baseline) → note response shape/fields.
2. Same request with no auth token → expect 401. If not, stop and report immediately (auth bypass, high severity, quick win).
3. Same request with User B's ID / User A's token → expect 403/404. If 200, BOLA — report.
4. Swap HTTP method (GET→PUT→PATCH→DELETE) with your own token → expect consistent enforcement across methods.
5. Add one hidden param from the list in the earlier methodology doc (`admin=true`, `role=admin`, etc.) → expect no behavior change.
6. Check the full response body against the sensitive-field list (password hash, internal IDs, tokens, PII you shouldn't see) even if the status code was "expected."

Steps 1–3 alone, run against every single endpoint you find in recon (not just the ones that look interesting), catch the majority of reportable bugs in a time-boxed session. Depth beyond that is worth it only on endpoints tied to money, auth, or admin functions.

## 5. Reporting Speed

To avoid losing time writing reports after a long testing session, capture this as you go — not after:
- Endpoint + method
- Exact request (curl or Burp Repeater export)
- Exact response showing the issue
- One-line impact statement ("any authenticated user can read any other user's PII by changing the `id` path parameter")

A report with these four things, sent within an hour of finding the bug, beats a polished report sent two days later — someone else may find and report the same thing in the meantime.
