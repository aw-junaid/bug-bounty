Here's a hands-on walkthrough you can try on pretty much any modern website (use one you're authorized to test, or your own project — or just a site you use normally, like a shopping or news site, purely for observation).

## Step 1: Open Dev Tools and go to the Network tab

- Right-click the page → **Inspect** (or `F12` / `Ctrl+Shift+I` on Windows/Linux, `Cmd+Option+I` on Mac)
- Click the **Network** tab
- Check the box to preserve log if you'll be navigating, and filter by **Fetch/XHR** — this hides images, CSS, fonts, etc., and shows only the requests the JavaScript is making dynamically

## Step 2: Trigger some activity on the page

Interact with the site in a way that would need fresh data:
- Scroll down (infinite scroll / "load more")
- Type in a search box
- Apply a filter or sort
- Click "like," add to cart, submit a form, load a comment section

Watch the Network panel — you'll see new requests pop in as you do this.

## Step 3: Inspect a request

Click on one of the requests that appeared. Look at:
- **URL** — does it look like `/api/v1/products?category=shoes` or `/graphql`? That's your naming-convention clue.
- **Headers tab** — check `Content-Type` in the response headers. `application/json` is a dead giveaway.
- **Response/Preview tab** — you'll often see raw JSON data here, structured and clean, rather than HTML.
- **Request Method** — GET, POST, PUT, DELETE tell you what kind of operation it's doing.

## Step 4: Map out the pattern

Once you find one endpoint, you can usually infer others nearby:
- `/api/v1/users/123` → maybe `/api/v1/users/123/orders` exists too
- Check if there's an `Authorization` header or a token/cookie being sent — that tells you how auth works
- Look at query parameters — pagination (`?page=2`), filters, sort options

## Step 5: Check the JS source directly (optional deeper dive)

- Go to the **Sources** tab in dev tools
- Look through the bundled `.js` files (they're often minified, but readable-ish)
- Search (Ctrl+F within Sources) for strings like `"/api/"`, `fetch(`, `axios`, or `baseURL` — frontend code often has the API base URL hardcoded or in a config object

A couple of notes:
- Always make sure you're authorized to probe an API this way — this is standard practice for your own apps or in an authorized pentest/bug bounty scope, but poking at internal APIs of sites without permission can cross into legally risky territory depending on what you do with what you find.
- If you want, I can also walk through what a **GraphQL** internal API looks like (single `/graphql` endpoint, POST-only, introspection queries) since it behaves pretty differently from REST-style internal APIs.
