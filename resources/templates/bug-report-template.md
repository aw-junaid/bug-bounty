# Bug Bounty Report: [VULNERABILITY TYPE]

## 📋 Executive Summary

| Field | Value |
|-------|-------|
| **Report ID** | [BB-YYYY-MM-XXX] |
| **Program** | [Program Name] |
| **Vulnerability Type** | [XSS/SQLi/IDOR/etc.] |
| **Severity** | 🔴 Critical / 🟠 High / 🟡 Medium / 🟢 Low / ⚪ Informational |
| **CVSS Score** | [0.0 - 10.0] |
| **CWE ID** | [CWE-XX] |
| **Date Reported** | [YYYY-MM-DD] |
| **Status** | Pending / Triaged / Fixed / Duplicate / N/A |

## 🎯 Vulnerability Location

- **URL:** `[Full URL with vulnerable parameter]`
- **Endpoint:** `[API endpoint or route]`
- **Parameter:** `[Vulnerable parameter name]`
- **Method:** `[GET/POST/PUT/DELETE]`

## 📝 Description

[Provide a clear and concise description of the vulnerability. Explain what the issue is, where it occurs, and why it's a security concern.]

**Example:**
A Stored Cross-Site Scripting (XSS) vulnerability exists in the comment section of the blog posts. The application fails to properly sanitize user input in the comment field, allowing an attacker to inject arbitrary JavaScript code that gets stored in the database and executed in the browsers of other users viewing the comment.

## 🔍 Steps to Reproduce

1. Navigate to: `[URL]`
2. Perform the following actions:
