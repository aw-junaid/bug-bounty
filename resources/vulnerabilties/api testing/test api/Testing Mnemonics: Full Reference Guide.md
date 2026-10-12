# Testing Mnemonics: Full Reference Guide

> Source poster credits: mnemonic data compiled from Karen N. Johnson's card deck, Lynn McKee's QP webpage, and Moolya, collected by Del Dewar (findingdeefex.com). This guide expands each mnemonic from that poster with a full letter-by-letter breakdown and practical notes on when and how to use it.

## How to use this guide

Each entry gives you: the mnemonic, its category, what each letter stands for, and a short explanation of what to actually *do* with it. Mnemonics are memory aids for a checklist, not a rigid procedure — use them to make sure you haven't forgotten an angle, not as a script to follow mechanically.

---

## 1. CRUSSPIC STMPL — Quality Characteristics

A checklist of software quality attributes to consider when planning what "quality" means for a given product — useful at the start of a project, or any time a requirement or bug needs to be judged against "is this actually a problem, and for which quality attribute?"

| Letter | Characteristic | What it means in practice |
|---|---|---|
| **C** | Capability | Does it do what it's supposed to do — the functional feature set itself |
| **R** | Reliability | Does it keep working correctly over time, under real conditions, without crashing or corrupting data |
| **U** | Usability | Can people actually use it effectively — learnability, efficiency, satisfaction |
| **S** | Security | Is data and access protected against unauthorized use, disclosure, or damage |
| **S** | Scalability | Does it keep performing acceptably as load, data volume, or users grow |
| **P** | Performance | Speed, responsiveness, resource usage under expected and peak conditions |
| **I** | Installability | How easily can it be installed, upgraded, and uninstalled cleanly |
| **C** | Compatibility | Does it work correctly alongside other software, hardware, browsers, OS versions |
| **S** | Supportability | How easy is it to diagnose and fix problems after release (logging, diagnostics, documentation) |
| **T** | Testability | How easy is it to test the system — observability and controllability of its behavior |
| **M** | Maintainability | How easily can the codebase be modified, extended, or fixed without introducing new bugs |
| **P** | Portability | How easily does it move to a different environment, platform, or configuration |
| **L** | Localisability | How easily can it be adapted for different languages, regions, and cultures |

**When to use it:** at project kickoff to build a test strategy that covers more than just functional correctness, or when triaging a bug to identify exactly which quality attribute it violates (which helps with prioritization and routing to the right owner).

---

## 2. MCOASTER — Reporting / Test Session Planning

Used for structuring an exploratory test session's charter, and for reporting on it afterward.

| Letter | Term | What it means |
|---|---|---|
| **M** | Mission | What is this testing session trying to accomplish? |
| **C** | Coverage | What areas, features, or risks did the session actually cover? |
| **O** | Obstacles | What got in the way — environment issues, blocked features, unclear requirements? |
| **A** | Audience | Who is this report for, and what do they need to know? |
| **S** | Status | Where does the product/feature stand right now based on what was found? |
| **T** | Techniques | What testing techniques/heuristics were actually applied during the session? |
| **E** | Environment | What configuration, build, data, and environment was used? |
| **R** | Risk | What risks were identified, confirmed, or ruled out? |

**When to use it:** at the end of an exploratory testing session, to write a report that a manager or stakeholder can actually act on, rather than a vague "tested stuff, found some bugs."

---

## 3. SPIES — Internationalisation (i18n) Testing

A checklist for testing whether a product genuinely works across languages, regions, and cultures.

| Letter | Term | What to check |
|---|---|---|
| **S** | Special characters | Accented letters, non-Latin scripts, right-to-left text, emoji, diacritics rendering and sorting correctly |
| **P** | Pages & content | Is all visible content (including images with embedded text) actually translated, not just UI labels? |
| **I** | Integrations | Do third-party integrations (payment providers, maps, analytics) support the target locale? |
| **E** | Error messages | Are error messages translated, and do they still make grammatical sense once translated? |
| **S** | Special events | Local holidays, calendars (non-Gregorian), regional date/number/currency formats, daylight saving differences |

**When to use it:** before a release to a new market, or any time a product claims to "support" a new language — this goes beyond "is the text translated" into whether the whole experience actually works for that locale.

---

## 4. SEED NATALI — GUI Step / Test Automation

A checklist for what a robust automated GUI test step should actually verify before and after interacting with an element — far more than just "click the button."

| Letter | Term | What to check |
|---|---|---|
| **S** | Synchronise | Wait properly for the element/page state to be ready — don't rely on fixed sleeps |
| **E** | Exists | Confirm the element actually exists in the DOM/UI before interacting with it |
| **E** | Enabled | Confirm the element is enabled/interactable, not just present and disabled |
| **D** | Displayed | Confirm the element is visible on screen, not hidden or off-canvas |
| **N** | Number of arguments | Confirm the step/function is called with the correct number of arguments |
| **A** | Types of arguments | Confirm the arguments passed are the correct data types |
| **T** | Log | Log what the step did, for debugging failed runs later |
| **A** | (continued, see below) | — |
| **L** | Investigate | When a step fails, investigate *why* before assuming it's a flaky test |
| **I** | (letters map loosely; use as a practical checklist rather than a strict 1:1 decomposition) | — |

**When to use it:** when writing or reviewing automated UI test steps/page-object methods — a reminder that a reliable automated check needs more scaffolding than the literal user action it simulates.

---

## 5. FAILURE — Error Handling

A checklist for evaluating how well a system handles failure conditions — not just whether it works on the happy path.

| Letter | Term | What to check |
|---|---|---|
| **F** | Functional | Does the error-handling path actually function correctly (not just the happy path)? |
| **A** | Appropriate | Is the response appropriate to the situation — not over- or under-reacting to the failure? |
| **I** | Impact | What's the consequence of the failure — to data, to the user, to the business? |
| **L** | Log | Is the failure logged usefully, with enough detail to diagnose it later, without leaking sensitive data? |
| **U** | UI | Is the failure communicated clearly to the user through the interface? |
| **R** | Recovery | Can the system (and the user) recover from the failure cleanly? |
| **E** | Emotions | How does the failure make the user feel — frustrated, confused, reassured, respected? |

**When to use it:** any time you're deliberately testing error paths — timeouts, invalid input, dependency failures, network loss — as a checklist for judging whether the error handling is actually good, not just present.

---

## 6. SACKED SCOWS — Learning (Testing Skill Development)

A checklist of ways to deliberately improve your own testing skill over time.

| Letter | Term | What it means |
|---|---|---|
| **S** | Scouting obsessively | Actively seek out new ideas, tools, and techniques rather than waiting for them to come to you |
| **C** | Authentic probs *(note: appears as second word)* | Work on real, authentic problems rather than only toy exercises |
| **K** | Cognitive savvy | Understand how your own thinking and biases affect your testing |
| **E** | Knowledge attracts knowledge | Learning in one area makes it easier to learn in adjacent areas — build broadly |
| **D** | Experiment | Deliberately try new approaches and techniques, not just the familiar ones |
| **S** | Disposable time | Set aside dedicated time for deliberate practice and learning, separate from project delivery |
| **C** | Stories | Learn from other testers' stories and experience reports |
| **O** | Contrasting ideas | Seek out viewpoints that disagree with your own to stress-test your thinking |
| **W** | Other minds | Pair and collaborate with other people to see how they think about problems |
| **S** | Words and pictures | Use both verbal and visual thinking/notation to understand and communicate testing ideas |
| — | Systems thinking | Understand the product and its context as an interacting system, not isolated features |

**When to use it:** for personal/team skill development planning — a checklist of habits that build testing expertise deliberately rather than by accident.

---

## 7. RIMGEA — Bug Advocacy

A checklist for how to make a bug report that actually gets fixed, rather than ignored or deprioritized.

| Letter | Term | What to do |
|---|---|---|
| **R** | Replicate it | Confirm you can reproduce the bug reliably (or document exactly how often/under what conditions it occurs) |
| **I** | Isolate it | Narrow down the exact conditions that trigger it — remove anything not essential to reproducing it |
| **M** | Maximise it | Push the bug to its most convincing/impactful form — find the worst-case manifestation |
| **G** | Generalise it | Check whether the same bug occurs in other, related areas of the product |
| **E** | Externalise it | Frame the report in terms of impact to the user/business, not just technical description |
| **A** | And say it clearly & dispassionately | Write the report factually and without blame or emotional language — let the evidence speak |

**When to use it:** before filing a bug report for anything non-trivial or anything you suspect might get pushed back on — a few minutes of advocacy work dramatically increases the odds a real bug actually gets prioritized and fixed.

---

## 8. FEW HICCUPPS — Consistency Oracles

A heuristic for recognizing when something is a bug by checking consistency against different reference points — "oracles" for deciding if behavior is actually wrong.

| Letter | Term | Consistent with... |
|---|---|---|
| **F** | Familiarity | Prior experience and conventions users are familiar with |
| **E** | Explainability | A sensible, explainable rationale — can you explain *why* it behaves this way? |
| **W** | World | The real world and how things generally work |
| **H** | History | *(Image)* — the product's own history and previous versions |
| **I** | Image | The company's desired brand image and reputation |
| **C** | Comparable products | Similar or competing products |
| **C** | Claims | What the product/documentation/marketing claims it does |
| **U** | Users' desires | What users actually want and need |
| **P** | Product | Internal consistency — does it behave consistently with the rest of itself? |
| **P** | Purpose | The product's stated purpose and goals |
| **S** | Statutes | Laws, regulations, and standards it must comply with |

**When to use it:** whenever you're not sure if something is "working as intended" or is actually a bug — checking it against each of these consistency oracles is often how you build the argument for why it's wrong.

---

## 9. DEED HELP GC — API Consumability

A checklist for judging how pleasant and safe an API is to actually use, from a consumer's perspective — separate from whether it's merely functionally correct.

| Letter | Term | What to check |
|---|---|---|
| **D** | Domain specific names | Are names in the API meaningful within the problem domain, not generic/cryptic? |
| **E** | Examples | Are there clear usage examples in documentation? |
| **E** | Easy to learn | Can a new consumer get productive with it quickly? |
| **D** | Documentation | Is it documented thoroughly and kept up to date? |
| **H** | Hard to misuse | Is the API designed so that incorrect usage is difficult or caught early? |
| **E** | Easy to use | Is the common/happy-path usage simple and intuitive? |
| **L** | Lead to readable code | Does using the API naturally produce readable consumer code? |
| **P** | Principle of least astonishment/surprise | Does the API behave the way a reasonable consumer would expect? |
| **G** | Guessability | Can a consumer reasonably guess how to use an unfamiliar part of the API from naming/patterns alone? |
| **C** | Consistency | Is the API internally consistent in naming, structure, and behavior across endpoints? |

**When to use it:** API design review, or when testing an API from the perspective of "would I want to build on this?" rather than just "does it return the right status code."

---

## 10. IVECTRAS — Performance Test Classification

A checklist for classifying what kind of performance test you're actually running (or should be running) — clarifying intent before designing the test.

| Letter | Term | What it means |
|---|---|---|
| **I** | Investigation or | Is this exploratory performance investigation (finding out how it behaves)... |
| **V** | Validation of | ...or validation against a known requirement/SLA? |
| **E** | End-to-end or | Is this measuring the full end-to-end user experience... |
| **C** | Component response times and/or | ...or isolated response times of individual components? |
| **T** | Resource consumption under | What resource consumption (CPU, memory, disk, network) is being measured... |
| **R** | Anticipated or | ...under anticipated/normal expected conditions... |
| **A** | Stressful conditions | ...or deliberately stressful/extreme conditions? |
| **S** | (rolled into above) | — |

**When to use it:** before designing a performance test, to make sure you and your stakeholders agree on what question the test is actually trying to answer — load test, stress test, soak test, and spike test are not interchangeable, and this mnemonic forces that distinction up front.

---

## 11. COP FLUNG GUN — Mobile Applications

A checklist of mobile-specific test dimensions, distinct from (but complementary to) the broader "I SLICED UP FUN" mobile mnemonic below.

| Letter | Term | What to check |
|---|---|---|
| **C** | Communication | Phone calls, SMS, push notifications, and other communication interrupting or interacting with the app |
| **O** | Orientation | Portrait/landscape rotation and how it's handled |
| **P** | Platform | Platform-specific behavior and guideline compliance (iOS vs. Android) |
| **F** | Function | Core functional correctness of the app's features |
| **L** | Location | GPS/location-based behavior, including permission states |
| **U** | User scenarios | Realistic end-to-end usage scenarios and personas |
| **N** | Network | Behavior across Wi-Fi, cellular, offline, and poor connectivity |
| **G** | Gesture | Touch gestures — tap, swipe, pinch, long-press, multi-touch |
| **G** | Guidelines | Compliance with platform design/store guidelines |
| **U** | Updates | App update behavior, including data migration across versions |
| **N** | Notifications | Push notification delivery, display, and tap-through behavior |

**When to use it:** mobile app test planning, as a complementary checklist to I SLICED UP FUN (#28) — between the two, most of the mobile-specific risk surface is covered.

---

## 12. DUFFSSCRA — Test Techniques

A checklist of distinct test-design techniques/approaches to draw on, to avoid testing the same way every time.

| Letter | Term | What it means |
|---|---|---|
| **D** | Domain | Testing based on partitioning the input domain into equivalence classes |
| **U** | User | Testing from the perspective of real user behavior and scenarios |
| **F** | Function | Testing each function/feature directly against its specification |
| **F** | Flow | Testing end-to-end flows and transitions across multiple features |
| **S** | Stress | Testing under extreme or resource-constrained conditions |
| **S** | Scenario | Testing realistic, narrative-based usage scenarios |
| **C** | Claims | Testing against documented claims (specs, marketing, help text) |
| **R** | Risk | Testing targeted at the highest-risk areas of the product |
| **A** | Automatic (model-based) | Using models or automated generation to derive test cases systematically |

**When to use it:** test design/strategy planning, as a reminder of the toolbox of distinct techniques available — useful for avoiding "test design tunnel vision" where every test ends up being the same shape.

---

## 13. ICEOVERMAD — API Testing

A broad checklist of what to cover when testing an API as a whole system, not just individual endpoints.

| Letter | Term | What to check |
|---|---|---|
| **I** | Integration | How the API integrates with the systems around it |
| **C** | Consumers | Who/what consumes the API, and whether their actual needs are met |
| **E** | Endpoints | Coverage across every exposed endpoint |
| **O** | Operations | Coverage across every operation/verb on each endpoint |
| **V** | Volume | Behavior under high data volume or high request volume |
| **E** | Error handling | Correct, consistent error responses across failure modes |
| **R** | Restful | Adherence to REST principles/conventions where applicable |
| **M** | Modularity | Whether the API is well-factored and modular, not a monolith of overloaded endpoints |
| **A** | Authentication | Authentication and authorization correctness |
| **D** | Definitions | Accuracy of the API's schema/contract definitions (OpenAPI/Swagger, etc.) against actual behavior |

**When to use it:** as a top-level API test strategy checklist, to make sure coverage spans beyond individual request/response pairs into integration, scale, and contract accuracy.

---

## 14. SLIME — Task Ordering

A checklist for prioritizing what to test first when time is limited.

| Letter | Term | What it means |
|---|---|---|
| **S** | Security | Security-sensitive areas often warrant early attention given the cost of getting them wrong |
| **L** | Languages | Localization/language-dependent areas, which are often tested too late |
| **I** | Requirements | Areas most directly tied to explicit requirements |
| **M** | Measurement | Areas that are measurable/quantifiable, where you can show clear progress |
| **E** | Existing | Existing, already-built functionality (vs. speculative or unbuilt areas) |

**When to use it:** when triaging what to test first under time pressure — a short list of dimensions that tend to deserve priority.

---

## 15. FCC CUTS VIDS — Touring

A set of "tours" for systematically exploring a product you're unfamiliar with, each tour focused on a different lens.

| Letter | Term | Tour focus |
|---|---|---|
| **F** | Feature | Walk through the features themselves, one by one |
| **C** | Complexity | Seek out the most complex parts of the product |
| **C** | Claims | Walk through what documentation/marketing claims the product does |
| **C** | Configuration | Explore configuration options and settings |
| **U** | User | Walk through as a realistic end user would |
| **T** | Testability | Explore which parts are easy vs. hard to test/observe |
| **S** | Scenario | Walk through realistic end-to-end scenarios |
| **V** | Variability | Explore areas where inputs/behavior can vary widely |
| **I** | Interoperability | Explore integration points with other systems |
| **D** | Data | Follow how data moves and is transformed through the system |
| **S** | Structure | Explore the underlying structure/architecture of the product |

**When to use it:** early in testing a new or unfamiliar product/feature, as a structured way to explore multiple facets quickly rather than wandering randomly.

---

## 16. RCRCRC — Regression Testing (Prioritization)

A checklist for deciding what to prioritize in regression testing when you can't test everything.

| Letter | Term | What it means |
|---|---|---|
| **R** | Recent | What's recently changed that needs fresh testing attention? |
| **C** | Core | What essential, must-work functionality needs to keep working? |
| **R** | Risky | What areas carry the highest inherent risk? |
| **C** | Configuration | What's sensitive to environment/configuration differences? |
| **R** | Repaired | What code was recently changed to fix a defect (and might have introduced a new one)? |
| **C** | Chronic | What areas have a history of recurring problems? |

**When to use it:** scoping a regression test pass under time constraints — this is the same mnemonic credited to Karen N. Johnson that appears elsewhere as "RCRCRC."

---

## 17. GRATEDD SCRIPTS — Test Strategy

A comprehensive checklist for the components of a written test strategy document.

| Letter | Term | What it covers |
|---|---|---|
| **G** | Goals | What is this testing effort trying to achieve? |
| **R** | Risks | What risks is the strategy designed to address? |
| **A** | Approach | What overall testing approach will be used? |
| **T** | Tradeoffs | What tradeoffs (time, coverage, depth) are being made deliberately? |
| **E** | Environments | What environments will testing happen in? |
| **D** | Dependencies | What does the testing effort depend on (teams, systems, data)? |
| **D** | Data | What test data strategy will be used? |
| **S** | Stakeholders | Who are the stakeholders and what do they need from this effort? |
| **C** | Coverage | What coverage model/target is being used? |
| **R** | Resources | What people, tools, and budget are available? |
| **I** | Information needs | What information do stakeholders need reported, and how often? |
| **P** | Prioritisation | How will testing be prioritized? |
| **T** | Tooling | What tools will be used? |
| **S** | Schedule | What's the timeline? |

**When to use it:** writing a formal test strategy document — this is a thorough checklist to make sure nothing structurally important is missing from the plan.

---

## 18. PAOLO — Device Orientation

A short, focused checklist specifically for testing device/screen orientation handling.

| Letter | Term | What to check |
|---|---|---|
| **P** | Portrait | Behavior and layout in portrait orientation |
| **A** | Audio | Whether audio playback/recording is affected by rotation |
| **O** | Objects | Whether on-screen objects/elements reposition and resize correctly |
| **L** | Landscape | Behavior and layout in landscape orientation |
| **O** | Overlay | Whether overlays (modals, dialogs, keyboards) behave correctly across rotation |

**When to use it:** a narrow, tactical checklist specifically for rotation-related mobile/tablet testing, as a companion to the broader mobile mnemonics (#11, #28).

---

## 19. MUTII — Testing (Core Dimensions)

A compact reminder of the core dimensions to understand about a product before/while testing it.

| Letter | Term | What it means |
|---|---|---|
| **M** | Market | What market/competitive context is this product operating in? |
| **U** | Users | Who are the actual users, and what are their needs? |
| **T** | Tasks | What tasks are users trying to accomplish? |
| **I** | Information | What information does the product need to provide or process? |
| **I** | Implementation | How is it actually built/implemented, and what does that imply for testing? |

**When to use it:** as a quick orientation checklist when starting to test an unfamiliar product, to ground testing in context rather than just mechanics.

---

## 20. SPIFFY — Microtest Qualities

A checklist for what makes a good unit/micro-test, as distinct from broader test types.

| Letter | Term | What it means |
|---|---|---|
| **S** | Small | Tests a small, focused unit of behavior |
| **P** | Precise | Has a precise, specific assertion — failures point clearly at the cause |
| **I** | Isolated | Doesn't depend on external state, other tests, or shared mutable fixtures |
| **F** | Fast | Runs quickly, enabling tight feedback loops |
| **F** | Frequently run | Run often — ideally on every change, not just before release |
| **Y** | *(rounds out the acronym)* | — |

**When to use it:** reviewing unit test quality/code review of test suites, to check whether tests actually have the properties that make them valuable (fast, reliable feedback) rather than just existing.

---

## 21. TERMS — Test Automation (Strategy)

A checklist of what to consider when planning a test automation effort.

| Letter | Term | What to consider |
|---|---|---|
| **T** | Tools & technology | What tools/frameworks fit the tech stack and team skills? |
| **E** | Execution | How and where will automated tests actually run (CI, schedule, on-demand)? |
| **R** | Requirements & risks | What requirements and risks is automation specifically meant to address? |
| **M** | Maintenance | What's the plan for keeping the suite maintained as the product changes? |
| **S** | Security | How is sensitive test data/credentials handled securely in the automation? |

**When to use it:** planning or reviewing a test automation strategy, especially the often-neglected "maintenance" and "security" angles.

---

## 22. RSTLLL — SMS/Text Message Applications

A checklist for testing SMS/text-messaging features specifically.

| Letter | Term | What to check |
|---|---|---|
| **R** | Reply | Does reply functionality work correctly, including threading? |
| **S** | Sender | Is sender information correct and handled properly (spoofing, display name)? |
| **T** | Timestamp | Are timestamps accurate, correctly time-zoned, and displayed sensibly? |
| **L** | List | Does message list/history display and update correctly? |
| **L** | Links | Are links within messages handled correctly (rendered, tappable, safe)? |
| **L** | Language & Length | Does the message handle different languages and respect length limits (including multi-part SMS segmentation)? |

**When to use it:** testing any feature that sends or receives SMS/text messages — a narrow but genuinely useful checklist for a feature area with a lot of easy-to-miss edge cases (concatenation, encoding, delivery receipts).

---

## 23. CRUMBS — Test Automation (Code Review Criteria)

A checklist for reviewing the quality of an automated test suite itself.

| Letter | Term | What to check |
|---|---|---|
| **C** | Confirmation | Does the suite confirm the right things — meaningful assertions, not just "did it run"? |
| **R** | Robustness & reliability | Is the suite robust against flakiness and environmental noise? |
| **U** | Usefulness & usability | Is the suite actually useful to the team, and usable (clear output, easy to run)? |
| **M** | Maintainability & manual effort | How much manual effort does the suite require to keep working, and how maintainable is it? |
| **B** | Basis & bias | What's the suite's coverage basis, and what biases exist in what it does/doesn't cover? |
| **S** | Span | How broad is the suite's span across the product/risk surface? |
| — | Coverage criteria & complexity | What coverage criteria were used, and is the suite's complexity proportionate? |
| — | Separation & security | Is test code properly separated from production code, and is sensitive data/credentials handled securely? |

**When to use it:** reviewing an existing automated test suite's overall health — useful as an audit checklist, not just for writing new tests.

---

## 24. SFDIPOT — Test Strategy (Product Elements)

A classic heuristic (from James Bach/Satisfice) for systematically considering the elements of a product that might need testing.

| Letter | Term | What it covers |
|---|---|---|
| **S** | Structure | Everything that makes up the physical/logical structure of the product (code, hardware, docs) |
| **F** | Function | What the product does — its features and operations |
| **D** | Data | What data it inputs, outputs, stores, and transforms |
| **I** | Interface | How users and other systems interact with it (UI, API, hardware interfaces) |
| **P** | Platform | What it depends on externally (OS, hardware, third-party software/services) |
| **O** | Operation | How it's actually used in the real world, by real users, in real environments |
| **T** | Time | How the product relates to time — performance, concurrency, scheduling, data that changes over time |

**When to use it:** building a comprehensive test strategy from first principles — a well-established, widely taught heuristic for making sure you've considered every major facet of "the product."

---

## 25. GO DARE=M — Test Plans

A checklist for the key components of a test plan document.

| Term | What it covers |
|---|---|
| **"Go for goal"** | A clear statement of the plan's overall goal |
| **Deliverables** | What tangible deliverables the testing effort will produce |
| **Activities** | What specific activities/tasks will be carried out |
| **Resources** | What people, tools, and environments are needed |
| **Estimates** | Time and effort estimates for the activities |
| **= represents balance** | A reminder that the plan must balance scope, time, and resources realistically |
| **Milestones** | Key checkpoints and dates the plan is organized around |

**When to use it:** writing a test plan (as distinct from the broader test *strategy* in GRATEDD SCRIPTS, #17) — more tactical, more focused on deliverables and scheduling.

---

## 26. CCD IS EARI — Performance Test Core Principles

A checklist for the lifecycle of running a disciplined performance test.

| Letter | Term | What it means |
|---|---|---|
| **C** | Context | Understand the context the performance test needs to represent |
| **C** | Criteria | Define clear pass/fail criteria before running the test |
| **D** | Design | Design the test scenario, including realistic load shapes and data |
| **I** | Install | Set up and configure the test environment and tooling |
| **S** | Script | Build the test scripts/scenarios |
| **E** | Execute | Run the test |
| **A** | Analyse | Analyze the results |
| **R** | Report | Report findings to stakeholders |
| **I** | Iterate | Iterate — refine the test and repeat based on what was learned |

**When to use it:** running a performance testing engagement end-to-end, as a reminder that performance testing is a disciplined process, not just "run a load tool and see what happens."

---

## 27. CIDTESTD — Project Environment

A checklist of the environmental/organizational factors around a testing effort that are easy to overlook.

| Letter | Term | What it covers |
|---|---|---|
| **C** | Customers | Who are the actual customers/stakeholders for this project? |
| **I** | Info | What information is available (or missing) about the project? |
| **D** | Dev relations | What's the working relationship with the development team? |
| **T** | Team | What does the test team itself look like — size, skills, availability? |
| **E** | Equipment & tools | What equipment and tooling is available? |
| **S** | Schedule | What's the overall project schedule and how does testing fit into it? |
| **T** | Test items | What are the actual items under test? |
| **D** | Deliverables | What deliverables is the test effort expected to produce? |

**When to use it:** at the start of a new project or engagement, as a checklist for understanding the organizational context testing will operate in — not product risk, but project/team risk.

---

## 28. MR Q COMP GRAB R&R — Exploration & Tactics

A detailed checklist of specific tactics to use during exploratory testing sessions, when you need a wider repertoire than just "click around."

| Letter | Term | Tactic |
|---|---|---|
| **M** | Modelling | Build a mental (or literal) model of how the system works |
| **R** | Resourcing | Identify what resources/data/tools you have available to test with |
| **Q** | Questioning | Ask questions of the product, the spec, or stakeholders as you go |
| **C** | Chartering | Define a clear, scoped charter for the session |
| **O** | Observing | Pay close attention to what's actually happening, not just what you expect |
| **M** | Manipulating | Directly manipulate inputs/state to see how the system reacts |
| **P** | Pairing | Test together with another person for a different perspective |
| **G** | Generating/elaborating | Generate new test ideas and elaborate on existing ones as you learn |
| **R** | Refocusing | Deliberately narrow or widen your focus as needed |
| **A** | Alternating | Alternate between different tactics/techniques to avoid tunnel vision |
| **B** | Branching/backtracking | Follow interesting threads (branch), then return to where you left off (backtrack) |
| **R&R** | Conjecturing | Form hypotheses about how the system behaves, and test them |
| — | Recording | Keep notes of what you did and found as you go |
| — | Reporting | Summarize and report your findings at the end |

**When to use it:** as a tactical toolbox during active exploratory testing sessions — when you feel stuck or repetitive, pick a tactic from this list you haven't used recently.

---

## 29. WWWWWHKE — Requirements Analysis

A checklist of questions to ask when analyzing requirements, specs, or a feature request — helps surface ambiguity and missing information early.

| Letter | Term | Question |
|---|---|---|
| **W** | Who | Who is this for? Who is involved? |
| **W** | What | What is this supposed to do? |
| **W** | When | When does this need to happen / by whom and when is it done? |
| **W** | Where | Where does this take place/apply? |
| **W** | Why | Why is this being done — what problem does it solve? |
| **H** | How | How will it be achieved/implemented? |
| **K** | Knowledge | What existing knowledge and experience is relevant here? |
| **E** | Experience | What questions does prior experience raise about this? |

**When to use it:** reviewing requirements, user stories, or specs before testing begins — a quick way to surface the ambiguities and gaps that otherwise turn into late-discovered bugs.

---

## 30. DVLA PC — API Maintainability

A short checklist for judging how maintainable an API is over its lifecycle, as distinct from its consumability (#9) or functionality (#32).

| Letter | Term | What to check |
|---|---|---|
| **D** | Diagnostic | Does the API expose enough diagnostic information to troubleshoot problems? |
| **V** | Versioning | Is there a clear, workable versioning strategy for evolving the API over time? |
| **L** | Logging | Is server-side logging adequate for maintaining and debugging the API in production? |
| **A** | Accessibility | Is the API's design and documentation accessible to the teams who need to maintain it? |
| **P** | Purpose | Does each part of the API still clearly serve a defined purpose, or has it accumulated cruft? |
| **C** | Consumer | Are consumer needs tracked and considered as the API evolves? |

**When to use it:** API design/architecture review focused on long-term health — distinct from functional testing, this is about whether the API will still be sane to work with a year from now.

---

## 31. FIBLOTS — Model Workloads / Performance Test Scenarios

A checklist for identifying which workloads actually deserve dedicated performance testing attention.

| Letter | Term | What it means |
|---|---|---|
| **F** | Frequent | Workloads that happen very often in real usage |
| **I** | Intensive | Workloads that are resource-intensive, even if infrequent |
| **B** | Business critical | Workloads tied directly to critical business operations |
| **L** | Legal | Workloads with legal/regulatory performance obligations |
| **O** | Obvious | Workloads that are the "obvious" candidates stakeholders will expect to be tested |
| **T** | Technically risky | Workloads that are technically risky from a performance perspective (new architecture, unproven at scale) |
| **S** | Stakeholder mandated | Workloads specific stakeholders have explicitly required to be tested |

**When to use it:** scoping which specific workloads/scenarios to include in a performance test plan, when you can't test every possible usage pattern.

---

## 32. PAPAS BE @ SFO — API Functionality

A checklist for functional testing of an API's actual behavior, distinct from the architectural/consumability mnemonics above.

| Letter | Term | What to check |
|---|---|---|
| **P** | Paging | Pagination behavior — page size limits, boundary pages, consistency across pages |
| **A** | Authentication | Authentication mechanisms work correctly across valid/invalid/expired credentials |
| **P** | Parameters/query strings | Correct handling of required, optional, and invalid parameters |
| **A** | Authorisations | Correct enforcement of authorization/permission rules per resource |
| **S** | Security | General security posture of the endpoint (see the broader security checklist) |
| **B** | Behave | Does the endpoint actually behave as documented/specified? |
| **E** | Error handling | Consistent, correct error responses |
| **S** | State | Correct handling of resource state and state transitions |
| **F** | Filter | Filtering functionality works correctly, including edge cases |
| **O** | Order | Sorting/ordering functionality works correctly, including ties and stability |

**When to use it:** functional API test design, as a focused checklist of the behaviors that are easy to under-test on a typical REST/GraphQL endpoint (pagination and filtering especially).

---

## 33. I SLICED UP FUN — Mobile Applications

The classic, widely-used mobile testing mnemonic (originally popularized by Jonathan Kohl), covering the full breadth of mobile-specific test dimensions.

| Letter | Term | What to check |
|---|---|---|
| **I** | Inputs | All the different ways input can be provided — touch, keyboard, voice, sensors |
| **S** | Store | App store requirements, listing, install/update/uninstall behavior |
| **L** | Location | Location-based behavior and permissions |
| **I** | Interactions/interruptions | Calls, SMS, notifications, alarms, and other interruptions mid-use |
| **C** | Communications | Network communication across connection types and quality |
| **E** | Ergonomics | Physical usability — one-handed use, reach, screen glare, thumb zones |
| **D** | Data | Data handling, storage, and synchronization |
| **U** | Usability | General usability of the mobile experience |
| **P** | Platform | Platform-specific guidelines and behavior (iOS vs. Android) |
| **F** | Function | Core functional correctness |
| **U** | User scenarios | Realistic end-to-end usage scenarios |
| **N** | Networks | Behavior across different network types and conditions |

**When to use it:** the primary, most comprehensive mobile test-planning mnemonic — use alongside the narrower COP FLUNG GUN (#11) and PAOLO (#18) for extra depth on communication/notifications and orientation specifically.

---

## 34. PROOF — Session-Based Test Debrief

A structure for debriefing an exploratory testing session with a manager or peer — the conversational counterpart to the MCOASTER written report (#2).

| Letter | Term | What to discuss |
|---|---|---|
| **P** | Past | What happened during the session — what was actually done |
| **R** | Results | What was found — bugs, questions, risks, information |
| **O** | Obstacles | What got in the way of testing effectively |
| **O** | Outlook | What's the outlook going forward — what's left to test, what's the risk picture |
| **F** | Feelings | How does the tester feel about the coverage and confidence achieved — is there a nagging doubt worth surfacing? |

**When to use it:** immediately after an exploratory testing session, as a short structured conversation (not a full written report) to communicate findings and calibrate confidence with a manager or the team.

---

## Quick Index

| # | Mnemonic | Category |
|---|---|---|
| 1 | CRUSSPIC STMPL | Quality Characteristics |
| 2 | MCOASTER | Reporting |
| 3 | SPIES | Internationalisation |
| 4 | SEED NATALI | GUI Step Automation |
| 5 | FAILURE | Error Handling |
| 6 | SACKED SCOWS | Learning |
| 7 | RIMGEA | Bug Advocacy |
| 8 | FEW HICCUPPS | Consistency |
| 9 | DEED HELP GC | API Consumability |
| 10 | IVECTRAS | Perf. Test Classification |
| 11 | COP FLUNG GUN | Mobile Applications |
| 12 | DUFFSSCRA | Test Techniques |
| 13 | ICEOVERMAD | API Testing |
| 14 | SLIME | Task Ordering |
| 15 | FCC CUTS VIDS | Touring |
| 16 | RCRCRC | Regression Testing |
| 17 | GRATEDD SCRIPTS | Test Strategy |
| 18 | PAOLO | Device Orientation |
| 19 | MUTII | Testing |
| 20 | SPIFFY | Microtest |
| 21 | TERMS | Test Automation |
| 22 | RSTLLL | SMS Text Applications |
| 23 | CRUMBS | Test Automation |
| 24 | SFDIPOT | Test Strategy |
| 25 | GO DARE=M | Test Plans |
| 26 | CCD IS EARI | Perf. Test Core Principles |
| 27 | CIDTESTD | Project Environment |
| 28 | MR Q COMP GRAB R&R | Exploration & Tactics |
| 29 | WWWWWHKE | Requirements Analysis |
| 30 | DVLA PC | API Maintainability |
| 31 | FIBLOTS | Model Workloads/Perf. Test |
| 32 | PAPAS BE @ SFO | API Functionality |
| 33 | I SLICED UP FUN | Mobile Applications |
| 34 | PROOF | Session-Based Test Debrief |

---

*Mnemonic data compiled from Karen N. Johnson's card deck, Lynn McKee's QP webpage, and Moolya — collected into the original poster by Del Dewar (findingdeefex.com). This guide expands each entry with practical explanations and usage guidance.*
