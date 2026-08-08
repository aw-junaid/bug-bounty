## What passive recon is

Passive recon means gathering information about a target **without directly touching or interacting with their systems** — so the target has no idea you're looking. This contrasts with *active* recon (which we were basically doing in the Network tab / dev tools walkthrough — that involves poking the live application).

The goal is to map the **attack surface**: all the systems, endpoints, and exposed data points that could eventually be probed or exploited.

## What you're hunting for

- **API endpoints** — targets for later active testing
- **Credentials** — to test as an authenticated (or better, admin) user
- **Version info** — hints at known vulnerabilities for that specific version
- **API documentation** — literally tells you how the API works
- **Business purpose** — helps you spot logic flaws (e.g., an API for a lending platform might have different abuse cases than one for a social app)

And sometimes you get lucky and stumble on **critical exposures directly** — API keys, JWTs, leaked PII, credit card data. These get flagged and reported immediately since they're already a serious finding.

## The three-phase process

1. **Cast a wide net** — broad searches (Google, Shodan, ProgrammableWeb) plus infrastructure mapping tools (DNS Dumpster, OWASP Amass) to see the target's overall footprint and related hosts.
2. **Adapt and focus** — narrow your searches based on what phase one revealed. Check GitHub repos tied to the target, use tools like Pastehunter to find leaked secrets in paste sites.
3. **Document everything** — screenshots, notes, a running task list you'll revisit during active exploitation.

## Google Hacking (the meatiest part of this excerpt)

This is using **advanced Google search operators** to surface things Google indexed that probably shouldn't be public:

| Operator | Does |
|---|---|
| `intitle:` | search page titles |
| `inurl:` | search URLs |
| `filetype:` | search by file extension |
| `site:` | limit to one domain |

Example progression: `inurl:/api/` alone is too broad (2M+ results) → adding `intitle:"targetname api key"` narrows it to something usable.

The **GHDB (Google Hacking Database)** is a pre-built library of these queries maintained by Offensive Security, with known-effective ones like:
- `intitle:"index.of" intext:"api.txt"` → finds exposed API key files
- `intitle:"index of" api_key OR "api key" OR apiKey -pool` → finds exposed keys sitting in open directory listings

Essentially: sites sometimes accidentally leave sensitive files browsable (directory listing enabled, no auth), and Google indexes them. These queries are shortcuts to finding that low-hanging fruit before someone with worse intentions does.
