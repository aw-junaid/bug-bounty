# Contributing to Bug Bounty

Thank you for your interest in contributing! This repository is a community-driven knowledge base for ethical security researchers, penetration testers, and bug bounty hunters. Every contribution — from fixing a typo to adding a full methodology — helps the community grow.

> ⚠️ **All contributions must be for authorized, educational, and ethical security research only.** Techniques that facilitate unauthorized access, malware distribution, or illegal activity will not be accepted.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [What You Can Contribute](#what-you-can-contribute)
- [Getting Started](#getting-started)
- [Repository Structure](#repository-structure)
- [Contribution Workflow](#contribution-workflow)
- [Writing Guidelines](#writing-guidelines)
- [Cheatsheet Format](#cheatsheet-format)
- [Methodology Format](#methodology-format)
- [Tool Contributions](#tool-contributions)
- [Write-up Submissions](#write-up-submissions)
- [Pull Request Guidelines](#pull-request-guidelines)
- [Reporting Issues](#reporting-issues)
- [Community & Support](#community--support)

---

## Code of Conduct

By participating in this project, you agree to uphold our [Code of Conduct](CODE_OF_CONDUCT.md). In short:

- Be respectful and inclusive
- Contribute only techniques for **authorized and ethical** testing
- Do not share zero-day exploits targeting specific production systems without responsible disclosure
- Provide proper attribution for all sources and references

---

## What You Can Contribute

| Type | Examples |
|:-----|:---------|
| **Methodologies** | Step-by-step exploitation guides for a vulnerability class |
| **Cheatsheets** | Quick-reference commands, payloads, and detection tips |
| **Tools** | Automation scripts, recon utilities, exploitation helpers |
| **Wordlists** | Curated subdomain, directory, or payload lists |
| **Write-ups** | Real-world bug bounty reports and vulnerability disclosures |
| **Report Templates** | Structured templates for submitting vulnerability reports |
| **Fixes** | Typos, broken links, outdated commands, improved clarity |
| **Translations** | Translations of existing content (open an issue first) |

---

## Getting Started

### Prerequisites

- A GitHub account
- Basic familiarity with Markdown
- Git installed locally (`git --version`)

### Fork & Clone

```bash
# 1. Fork the repo via GitHub UI, then clone your fork
git clone https://github.com/<your-username>/bug-bounty.git
cd bug-bounty

# 2. Add the upstream remote
git remote add upstream https://github.com/aw-junaid/bug-bounty.git

# 3. Create a feature branch
git checkout -b feat/add-csrf-cheatsheet
```

> Always branch off `main` and keep your branch focused on a single contribution.

---

## Repository Structure

```
bug-bounty/
├── methodologies/
│   ├── web penetration/       # Core web vuln exploitation guides
│   └── web technologies/      # Platform-specific testing guides
├── resources/
│   ├── cheatsheets/           # Quick-reference payload & command sheets
│   ├── templates/             # Bug report templates
│   └── wordlists/             # Subdomain, directory, and payload lists
├── tools/
│   ├── automation/            # Workflow automation scripts
│   ├── exploitation/          # Vulnerability exploitation tools
│   ├── reconnaissance/        # Asset discovery & enumeration tools
│   └── utilities/             # Helper scripts & payload generators
├── write-ups/
│   └── <year>/                # Real-world bug bounty write-ups by year
├── course/                    # Structured learning curriculum
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md            # This file
├── LICENSE
├── README.md
└── SECURITY.md
```

Place your files in the correct directory. If a fitting directory does not exist, propose a new one in your pull request description.

---

## Contribution Workflow

```
Fork repo → Create branch → Make changes → Test locally → Open PR → Review → Merge
```

1. **Sync your fork** before starting work:
   ```bash
   git fetch upstream
   git checkout main
   git merge upstream/main
   ```

2. **Create a focused branch**:
   ```bash
   git checkout -b feat/add-websocket-cheatsheet
   # or
   git checkout -b fix/broken-link-ssrf-guide
   ```

3. **Make your changes**, following the writing guidelines below.

4. **Commit with a clear message**:
   ```bash
   git add .
   git commit -m "feat: add WebSocket security cheatsheet"
   ```

   Use conventional commit prefixes:

   | Prefix | When to use |
   |:-------|:------------|
   | `feat:` | New methodology, cheatsheet, tool, or write-up |
   | `fix:` | Correcting errors, broken links, typos |
   | `docs:` | Improving existing documentation |
   | `refactor:` | Restructuring content without changing meaning |
   | `chore:` | Non-content changes (README badges, CI config) |

5. **Push and open a PR**:
   ```bash
   git push origin feat/add-websocket-cheatsheet
   ```
   Then open a pull request against `main` on GitHub.

---

## Writing Guidelines

These standards keep the repository consistent and useful for everyone.

### General

- Write in **clear, plain English**. Assume the reader is a competent security professional, not a beginner.
- Use **sentence case** for headings — not Title Case or ALL CAPS.
- Keep lines under **120 characters** where possible.
- Use **active voice**: "Run the following command" not "The following command should be run."
- Always include a **practical example** — commands, payloads, or code snippets.
- Link to **authoritative references** (CVEs, vendor advisories, research papers) at the end of each document.

### Ethical disclaimer

Every new methodology and cheatsheet file must include this warning block near the top:

```markdown
> ⚠️ **Authorized use only.** The techniques documented here must only be used on systems you own or have explicit written permission to test. Unauthorized access is illegal.
```

### Markdown style

- Use `##` for top-level sections within a file (the `#` title is the H1).
- Use fenced code blocks with a language identifier: ` ```bash `, ` ```python `, ` ```http `.
- Use tables for comparing options or listing payloads side-by-side.
- Avoid raw HTML unless absolutely necessary.
- Internal links should use relative paths: `[SSRF](../methodologies/web%20penetration/SSRF.md)`.

---

## Cheatsheet Format

Use this template when adding a new cheatsheet under `resources/cheatsheets/`:

```markdown
# <Vulnerability Name> Cheatsheet

> ⚠️ Authorized use only.

## Detection

<!-- How to identify if the target is vulnerable -->

## Basic payloads

<!-- Minimal working examples -->
\`\`\`
PAYLOAD_HERE
\`\`\`

## Advanced payloads

<!-- Filter bypasses, edge cases, out-of-band variants -->

## Tools

| Tool | Command |
|:-----|:--------|
| Tool Name | `command --flag target` |

## Remediation

<!-- One-line developer fix -->

## References

- [Source Title](https://link)
```

---

## Methodology Format

Use this template when adding a new guide under `methodologies/`:

```markdown
# <Vulnerability Class> — Penetration Testing Guide

> ⚠️ Authorized use only.

## Overview

<!-- 2–3 sentences: what is it, why does it matter, what is the impact -->

## Prerequisites

<!-- Tools, knowledge, or access required before testing -->

## Testing Methodology

### Step 1 — Reconnaissance
### Step 2 — Identification
### Step 3 — Exploitation
### Step 4 — Impact Assessment
### Step 5 — Reporting

## Common Bypasses

<!-- Known WAF, filter, and validation bypasses -->

## Real-World Examples

<!-- CVEs, public disclosures, or sanitized PoC -->

## Remediation

<!-- Developer-facing fix guidance -->

## References

- [Source Title](https://link)
```

---

## Tool Contributions

Scripts and utilities go under `tools/` in the appropriate subdirectory.

**Requirements for all tool contributions:**

- Include a **docstring or header comment** explaining what the tool does, its inputs, and outputs.
- Include **usage examples** in a comment block or a companion `README.md`.
- Tools must not include hardcoded credentials, live targets, or personal data.
- Python tools must be compatible with **Python 3.8+**.
- Bash scripts must pass `shellcheck` with no errors.
- Do not bundle third-party dependencies — list them in a `requirements.txt` or as comments.

**Example tool header (Python):**

```python
#!/usr/bin/env python3
"""
Tool Name: sqli-tester.py
Description: Automated SQL injection detection for GET/POST parameters.
Usage: python3 sqli-tester.py -u https://target.com/page?id=1
Author: Your Name
License: MIT
"""
```

---

## Write-up Submissions

Write-ups go under `write-ups/<year>/`. They must:

- Document a **real vulnerability** you discovered through an authorized bug bounty program or with explicit permission.
- **Not disclose** program names, targets, or details that the program has asked you to keep confidential.
- Follow responsible disclosure — do not submit write-ups for vulnerabilities that have not yet been patched or disclosed by the vendor.
- Use this filename format: `platform-vulnerability-type.md` (e.g., `slack-idor-profile-data.md`).

**Write-up template:**

```markdown
# <Platform> — <Vulnerability Type>

**Severity:** Critical / High / Medium / Low  
**Bounty:** $X (or N/A)  
**Year:** 2026  
**Status:** Resolved  

## Summary

## Steps to Reproduce

1.
2.
3.

## Impact

## Root Cause

## Remediation

## Timeline

| Date | Event |
|:-----|:------|
| YYYY-MM-DD | Reported |
| YYYY-MM-DD | Triaged |
| YYYY-MM-DD | Resolved |
| YYYY-MM-DD | Disclosed |

## References
```

---

## Pull Request Guidelines

Before submitting a PR, confirm:

- [ ] The content is for **authorized, educational use only**
- [ ] Files are placed in the **correct directory**
- [ ] Markdown renders correctly (preview in GitHub or a local renderer)
- [ ] Code blocks have language identifiers
- [ ] No broken links (check with a Markdown linter if possible)
- [ ] Commit messages use the conventional prefix format
- [ ] New files follow the relevant format template above
- [ ] No personally identifiable information, credentials, or live targets included

**PR title format:**

```
feat: add HTTP request smuggling cheatsheet
fix: correct SSRF payload for GCP metadata endpoint
docs: improve OAuth methodology step-by-step guide
```

Your PR will be reviewed within a few days. Expect feedback — don't take it personally. We aim to merge quality contributions quickly.

---

## Reporting Issues

Found an error, outdated command, or broken link? [Open an issue](https://github.com/aw-junaid/bug-bounty/issues) with:

- A clear title describing the problem
- The file path and line number (if applicable)
- What the content currently says vs. what it should say
- A reference link if you're correcting a factual error

For **security vulnerabilities in this repository itself**, please follow our [Security Policy](SECURITY.md) instead of opening a public issue.

---

## Community & Support

| Platform | Link |
|:---------|:-----|
| GitHub Discussions | [Ask questions, share ideas](https://github.com/aw-junaid/bug-bounty/discussions) |
| Discord | [Join the community](https://discord.gg/Neddn8gPqY) |
| YouTube | [Video walkthroughs](https://www.youtube.com/@awjunaid/featured) |
| Twitter / X | [@awjunaid_](https://twitter.com/awjunaid_) |
| Email | awjunaid@proton.me |

---

## Recognition

All contributors are acknowledged in the repository's contributor graph. Significant contributions may be highlighted in release notes or the wiki.

[![Contributors](https://img.shields.io/github/contributors/aw-junaid/bug-bounty?style=flat-square)](https://github.com/aw-junaid/bug-bounty/graphs/contributors)

---

*Thank you for helping make this resource better for the entire security community.*

© 2026 [aw-junaid](https://github.com/aw-junaid) · [MIT License](LICENSE)
