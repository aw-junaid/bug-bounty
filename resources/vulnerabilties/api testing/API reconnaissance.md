> When it comes to recognizing an API in the first place, it helps to consider
its purpose. APIs are meant to be used either internally, by partners and
customers, or publicly. If an API is intended for public or partner use, it’s
likely to have developer-friendly documentation that describes the API
endpoints and instructions for using it. Use this documentation to recognize
the API.If the API is for select customers or internal use, you’ll have to rely on other
clues: naming conventions, HTTP response header information such as
Content-Type: application/json, HTTP responses containing
JSON/XML, and information about the JavaScript source files that power
the application.

This passage is about API reconnaissance — figuring out whether a website or application is backed by an API, especially when that isn't obviously documented. Here's the breakdown:

**The core idea: purpose determines visibility**

APIs generally fall into three categories based on who's meant to use them, and that intended audience determines how easy they are to spot:

1. **Public APIs** — meant for any developer to use (think Twitter/X API, Google Maps API, Stripe API)
2. **Partner APIs** — meant for specific business partners integrating with the service
3. **Internal/private APIs** — meant only for the company's own applications (e.g., the API that powers a company's own mobile app or website), not meant for outside use

**How you recognize each type**

- **Public/partner APIs**: These usually come with **developer documentation** — pages explaining endpoints, authentication, request/response formats, rate limits, etc. If you find that documentation, you've basically found the API already described for you. Easy mode.

- **Internal/private APIs**: These have no public documentation because they were never meant to be discovered or used externally. So you have to infer their existence through **indirect clues**, such as:
  - **Naming conventions** — subdomains or paths like `api.example.com`, `example.com/api/v1/`, `example.com/graphql`, etc.
  - **HTTP response headers** — things like `Content-Type: application/json` tell you the server is returning structured data, which is a strong hint it's an API response rather than an HTML page.
  - **Response bodies** — if you see raw JSON or XML coming back instead of rendered HTML, that's a direct sign you've hit an API endpoint.
  - **JavaScript source files** — modern web apps (especially single-page apps built with React, Angular, Vue, etc.) fetch data dynamically via JS. Reading through the site's JS files (or watching network requests in browser dev tools) often reveals the API endpoints the frontend is calling behind the scenes.

**Why this matters (likely context: security testing / bug bounty / pentesting)**

This is a common technique in **web application security testing**. Public-facing sites often have hidden internal APIs powering their AJAX calls, mobile apps, or dynamic content — and those internal APIs are frequently *less* hardened than the "front door" because developers assumed no one outside the company would ever look at them. Learning to spot these (via browser dev tools' Network tab, viewing page source, or tools like Burp Suite) is a standard step in API security assessments.
