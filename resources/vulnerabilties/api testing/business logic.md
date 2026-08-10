> Modifying a customer’s address from the provider’s perspective takes three steps. Users “List customer’s addresses” to get the active one, “Update address status” to make this address inactive, and finally, “Add new address” with an active status. Going through these steps and data manipulation is complex, but more critical problems exist. These steps will be executed by an uncontrolled consumer (developed by a third
party, for example) or in an unsecured environment (a browser, for instance). Due to unexpected crashes, errors in code, or malicious intent, consumers may stop at the
second step or add a new address without deactivating the active one, leading to data integrity problems. When thought of from the consumer’s perspective, the use case has a single step, “Update customer’s address,” which ensures that the implementation we control manages the business logic securely and preserves data integrity.
WARNING If incorrect API steps or operation executions can compromise underlying data and systems integrity, we trust API consumers with business
logic. It’s solely the implementation’s responsibility to handle such logic.

This passage is about a **business logic / API design flaw** — specifically, the danger of pushing multi-step workflows onto API consumers instead of handling them atomically on the server side.

## The concrete example

To change a customer's address, the *provider* (the API/backend) requires the *consumer* (whoever is calling the API) to perform **three separate calls in sequence**:

1. **List customer's addresses** — find the current active one
2. **Update address status** — mark that address inactive
3. **Add new address** — create the new one, marked active

## Why this is a problem

This workflow assumes the consumer will always execute all three steps, in order, successfully, every time. But APIs are frequently consumed by:
- Third-party developers who don't fully understand your intended flow
- Client-side code running in a browser — an inherently unsecured, uncontrolled environment
- Automated scripts or mobile apps that can crash mid-process

If anything interrupts that sequence — a network failure, a bug in the consumer's code, an app crash, or even a malicious actor deliberately manipulating the calls — you can end up with:
- Two active addresses (if step 2 gets skipped and step 3 still runs)
- Zero active addresses (if the process stops after step 2)
- Inconsistent state in general — this is a **data integrity problem**

## The core design principle

> **The API provider, not the API consumer, should own and enforce business logic.**

The fix here is to collapse this into a **single atomic operation**: "Update customer's address." The client sends one request, and the *server* internally handles deactivating the old address and creating the new one — as one transaction it controls, rather than trusting an external, unreliable, and potentially untrustworthy caller to correctly choreograph multiple steps.

## Why this matters for API security testing (the WARNING box)

This is flagged as a genuine vulnerability class, not just sloppy design. If an API's data integrity depends on the *consumer* performing steps correctly and in order, that's a **broken/missing business logic control** — and it's exploitable. An attacker doesn't need to "hack" anything technical here; they can just:
- Call step 2 without step 3 (deactivate address, never provide new one — denial of service on that field)
- Skip step 2 and only call step 3 (create duplicate "active" addresses — potential fraud vector, e.g., redirecting shipments)
- Interrupt/replay steps out of order to create inconsistent states

This ties into a broader OWASP API Security category — **Broken Function Level Authorization / Business Logic vulnerabilities** — where the API works "correctly" per its documentation, but the *sequence* of otherwise-valid calls can be abused because the server never enforces atomicity or validates that the whole logical operation completed as a unit.

**Testing implication:** when doing security assessments, you should specifically look for multi-step workflows like this (address changes, password resets, payment processing, order fulfillment) and try calling the steps out of order, skipping steps, or replaying them — since these are prime spots for business logic flaws that automated scanners typically miss.
