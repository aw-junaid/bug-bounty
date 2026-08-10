This is a checklist/methodology for the **Define stage of API design** — essentially, "how do you go from vague user needs to a concrete list of API capabilities?" Let me walk through it section by section.

## Part 1: Analyzing user needs to find capabilities

**Start from needs, not endpoints.** The whole point of the Define stage is to figure out *what the API must let people do* (capabilities) — before anyone starts designing endpoints, schemas, or methods.

**Use natural language, not API language.** Say "Send a message" instead of `POST /messages`. This matters because:
- It keeps discussions accessible to non-technical stakeholders (product owners, business analysts)
- It prevents premature design decisions — jumping to `POST /status` locks you into an implementation shape before you've even confirmed the capability is real or scoped correctly
- It reduces bias — natural language descriptions are easier to double check against actual user needs

**Clarify vocabulary ambiguity.** Different stakeholders or teams may use different words for the same concept (or the same word for different concepts). Explicitly ask "Are A and B the same thing?" to avoid designing duplicate or conflicting capabilities based on a terminology mismatch.

**Confirm with stakeholders.** Don't just gather requirements passively — validate what you found against what the Define-stage stakeholders (the people who defined the original business/user needs) actually intended. Push back or ask for clarification when something seems off, rather than assuming you interpreted it correctly.

**List all users and use cases exhaustively.** Miss a user type, and you'll likely miss capabilities specific to them (e.g., "admin" vs "customer" vs "third-party integrator" may all need different things).

## Part 2: Two-pass use case analysis

This is a key efficiency technique — **don't try to capture everything at once**.

**Pass 1 — Nominal paths only.** Focus purely on the happy path: the most common, successful way a use case plays out. Ignore edge cases and failures for now. This keeps the first pass fast and focused.

**Decompose into steps.** Break each nominal use case into its individual steps. This granularity is what lets you spot every discrete capability the API needs to expose, rather than one big vague operation.

**Trace inputs and outputs.** For each step, ask: *where does this input come from?* and *what happens to this successful output afterward?* This traceability often reveals:
- Missing steps you hadn't considered
- Entire missing use cases (if an output is used somewhere you hadn't mapped)
- Missing user types (if you find a consumer of data you hadn't accounted for)

**Pass 2 — Alternative paths and failures.** Now go back and do the messier work:
- List things that can go wrong from the *user's* perspective (not the system's) — what fails, why, and how it'd be resolved
- Ask "what if?" about events before/after each step to uncover branching paths you missed

Splitting nominal vs. alternative paths into two passes prevents you from getting bogged down in edge cases before you even have the core capability map — it's a form of progressive elaboration.

## Part 3: Refining and deduplicating capabilities

**Merge duplicates.** Compare steps by their description, inputs, and success outcomes. If two "different" steps are functionally identical, describe them as **one unique, context-agnostic operation** rather than keeping near-duplicate capabilities that just came from different use cases or teams.

**Challenge irrelevant or unused elements.** If a capability seems only loosely related to the actual subject matter, or its output is never consumed by anything, question whether it actually belongs in the API at all. This keeps the capability list lean and purposeful.

## Part 4: Design discipline (what to avoid)

Three important anti-patterns called out:

1. **Don't mirror UI flows.** If your API capabilities map 1:1 to specific screens/buttons in one particular consumer's interface, the API becomes tightly coupled to that one use case and less reusable by other consumers.

2. **Don't bake in consumer-specific business logic.** This connects directly to the address example from before — if the API expects the *consumer* to know and execute a specific sequence of steps, that's fragile and consumer-dependent rather than a clean, self-contained capability.

3. **Don't leak internal architecture.** Exposing your data organization or system internals through the API design creates unnecessary complexity for consumers and creates coupling that can compromise your system if internals change or are exploited.

All three point to the same underlying principle: **the API should represent a clean, stable, reusable abstraction of a capability — not a mirror of your UI, your internal database schema, or one particular client's workflow.**

## Conway's Law — the aside

> *"Any organization's system design will mirror its communication structure."*

Originally about software architecture generally (Melvin Conway, 1968), the book applies it to APIs specifically: **how your teams communicate and are organized tends to shape how your APIs end up structured** — sometimes for the worse. For example, if Team A and Team B don't talk much, you might end up with two separate, redundant APIs doing similar things instead of one well-designed shared capability. 

Why it's mentioned here: it's a reminder that when you're deduplicating capabilities and challenging irrelevant elements (the steps above), some of that mess isn't a design mistake per se — it's an **organizational** symptom. Recognizing this can help API architects push for cross-team alignment rather than just patching the API design after the fact.

---

**Big picture:** this whole excerpt is describing a structured, disciplined process for turning fuzzy business/user requirements into a clean, minimal, reusable set of API capabilities — while explicitly avoiding the traps (UI-coupling, consumer-side logic, leaked architecture) that make APIs fragile, insecure, or hard to maintain, which ties back to the security themes from your earlier document (the address example was literally a case of "consumer-specific business logic" bleeding into the API design and causing security/integrity issues).
