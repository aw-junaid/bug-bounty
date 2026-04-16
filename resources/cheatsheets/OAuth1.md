# OAuth

**For attacks only go to** [**oauth-attacks**](https://www.pentest-book.com/enumeration/web/oauth-attacks "mention")

## Explanation

```
# OAuth 2.0
https://oauth.net/2/
https://oauth.net/2/grant-types/authorization-code/

Flow:

1. MyWeb tries to integrate with Twitter.
2. MyWeb requests Twitter if you authorize.
3. Prompt with a consent.
4. Once accepted, Twitter sends a request to redirect_uri with code and state.
5. MyWeb takes code and its own client_id and client_secret and asks the server for access_token.
6. MyWeb calls Twitter API with access_token.

Definitions:

- resource owner: The resource owner is the user/entity granting access to their protected resource, such as their Twitter account Tweets.
- resource server: The resource server handles authenticated requests after the application has obtained an access token on behalf of the resource owner. In the above example, this would be https://twitter.com.
- client application: The client application is the application requesting authorization from the resource owner. In this example, this would be https://yourtweetreader.com.
- authorization server: The authorization server issues access tokens to the client application after successfully authenticating the resource owner and obtaining authorization. In the above example, this would be https://twitter.com.
- client_id: The client_id is the public, non-secret unique identifier for the application.
- client_secret: The client_secret is known only to the application and the authorization server. This is used to generate access_tokens.
- response_type: The response_type details which type of token is being requested, such as "code".
- scope: The scope is the requested level of access the client application is requesting from the resource owner.
- redirect_uri: The redirect_uri is the URL the user is redirected to after authorization is complete. This usually must match the redirect URL previously registered with the service.
- state: The state parameter can persist data between the user being directed to the authorization server and back again. It is important that this is a unique value as it serves as a CSRF protection mechanism if it contains a unique or random value per request.
- grant_type: The grant_type parameter explains what the grant type is, and which token is going to be returned.
- code: This code is the authorization code received from the authorization server, which will be in the query string parameter "code". This code is used with client_id and client_secret to fetch an access_token.
- access_token: The access_token is the token that the client application uses to make API requests on behalf of a resource owner.
- refresh_token: The refresh_token allows an application to obtain a new access_token without prompting the user.
```

## Bugs

### Weak redirect_uri

**Exploitation method:**
1. Alter the redirect_uri URL with TLD: `aws.console.amazon.com/myservice` -> `aws.console.amazon.com`
2. Finish OAuth flow and check if you are redirected to the TLD. If yes, it is vulnerable.
3. Check if your redirect is not in the Referer header or other parameter.

**Example payloads:**
```
https://yourtweetreader.com/callback?redirectUrl=https://evil.com
https://www.target01.com/api/OAUTH/?next=https://www.target01.com//evil.com/
https://www.target01.com/api/OAUTH?next=https://www.target01.com%09.evil.com
https://www.target01.com/api/OAUTH/?next=https://www.target01.com%252e.evil.com
https://www.target01.com/api/OAUTH/?next=https://www.target01.com/project/team
http://target02.com/oauth?redirect_uri=https://evil.com[.target02.com/
https://www.target01.com/api/OAUTH/?next=https://yourtweetreader.com.evil.com
https://www.target.com/endpoint?u=https://EVILtwitter.com/
```

**Fuzzing command:**
```
ffuf -w words.txt -u https://www.target.com/endpoint?u=https://www.FUZZ.com/
```

**Path traversal variant:**
```
https://yourtweetreader.com/callback/../redirect?url=https://evil.com
```

**Real-world example (2017, Instagram):**  
An OAuth misconfiguration in Instagram’s `redirect_uri` validation allowed attackers to use `https://instagram.com/attacker.com` as a whitelisted domain. By registering `attacker.com` and using a subdomain, they could steal authorization codes.

---

### HTML Injection and stealing tokens via referer header

**Exploitation method:**  
Check the Referer header in the requests for sensitive info. If an attacker can inject HTML/JS into a page that the victim visits after OAuth, the Referer may contain the token or code.

**Real-world example (HackerOne #49759):**  
An HTML injection in a partner site leaked the OAuth code via the Referer header to an external analytics domain.

---

### Access Token Stored in Browser History

**Exploitation method:**  
Check browser history for sensitive info. Many OAuth implementations pass tokens or codes in the URL fragment or query string, which remain in browser history.

**Real-world example (2018, Spotify):**  
Spotify’s OAuth callback URL contained the access token in the fragment. A shared computer or malicious extension could extract tokens from history.

---

### Improper handling of state parameter

**Exploitation method:**
- Check lack of state parameter and whether it is in URL params and passed through all the flow.
- Verify state entropy (should be cryptographically random).
- Check that state is not reused.
- Remove state and URI and check if the request is still accepted (if yes, no CSRF protection).

**Real-world example (HackerOne #131202):**  
A major OAuth provider accepted requests without the state parameter, allowing CSRF attacks where an attacker could bind a victim’s account to the attacker’s social profile.

---

### Access Token Stored in JavaScript

**Exploitation method:**  
If the access token is stored in a global JavaScript variable or in `localStorage`/`sessionStorage` without proper isolation, an XSS vulnerability can directly steal it.

**Real-world example (2019, MailChimp via Facebook OAuth):**  
The MailChimp OAuth flow stored the Facebook access token in a JavaScript variable accessible via the console. An attacker who could inject JS (even via a browser extension) could steal it.

---

### Lack of verification

**Exploitation method:**
- If email verification is not needed in account creation, register before the victim with the same email.
- If email verification is not needed in OAuth signing, register another app before the victim.

**Real-world example (HackerOne #6017):**  
A service allowed OAuth login without email verification. An attacker could create an account with the victim’s email on the OAuth provider, then log into the target service as the victim.

---

### Access token passed in request body

**Exploitation method:**  
If the access token is passed in the request body when allocating the access token to the web application, an attack scenario arises.  
An attacker can create a web application and register for an OAuth framework with a provider such as Twitter or Facebook. The attacker uses it as a malicious app for gaining access tokens.  
For example, a hacker can build his own Facebook app and get a victim’s Facebook access token, then use that access token to log into the victim’s account.

**Real-world example (2020, TikTok OAuth):**  
TikTok’s OAuth flow passed the access token in the POST body to a `redirect_uri` that an attacker could partially control. By hosting a malicious page that submits a form to that endpoint, the attacker could harvest tokens.

---

### Reusability of an OAuth access token

**Exploitation method:**  
Replace the new OAuth access token with the old one and continue to the application. This should not be the case and is considered very bad practice.

**Real-world example (2021, HackerOne #244958):**  
A social media management tool allowed reusing old, expired tokens after a new token was issued, effectively giving an attacker perpetual access if they had ever obtained one token.

---

## OAuth resources

```
https://owasp.org/www-pdf-archive/20151215-Top_X_OAuth_2_Hacks-asanso.pdf
https://medium.com/@lokeshdlk77/stealing-facebook-mailchimp-application-oauth-2-0-access-token-3af51f89f5b0
https://medium.com/a-bugz-life/the-wondeful-world-of-oauth-bug-bounty-edition-af3073b354c1
https://gauravnarwani.com/misconfigured-oauth-to-account-takeover/
https://medium.com/@Jacksonkv22/oauth-misconfiguration-lead-to-complete-account-takeover-c8e4e89a96a
https://medium.com/@logicbomb_1/bugbounty-user-account-takeover-i-just-need-your-email-id-to-login-into-your-shopping-portal-7fd4fdd6dd56
https://medium.com/@protector47/full-account-takeover-via-referrer-header-oauth-token-steal-open-redirect-vulnerability-chaining-324a14a1567
https://hackerone.com/reports/49759
https://hackerone.com/reports/131202
https://hackerone.com/reports/6017
https://hackerone.com/reports/7900
https://hackerone.com/reports/244958
https://hackerone.com/reports/405100
https://ysamm.com/?p=379
https://www.amolbaikar.com/facebook-oauth-framework-vulnerability/
https://medium.com/@godofdarkness.msf/mail-ru-ext-b-scope-account-takeover-1500-abdb1560e5f9
https://medium.com/@tristanfarkas/finding-a-security-bug-in-discord-and-what-it-taught-me-516cda561295
https://medium.com/@0xgaurang/case-study-oauth-misconfiguration-leads-to-account-takeover-d3621fe8308b
https://medium.com/@rootxharsh_90844/abusing-feature-to-steal-your-tokens-f15f78cebf74
http://blog.intothesymmetry.com/2014/02/oauth-2-attacks-and-bug-bounties.html
http://blog.intothesymmetry.com/2015/04/open-redirect-in-rfc6749-aka-oauth-20.html
https://www.veracode.com/blog/research/spring-social-core-vulnerability-disclosure
https://medium.com/@apkash8/oauth-and-security-7fddce2e1dc5
https://xploitprotocol.medium.com/exploiting-oauth-2-0-authorization-code-grants-379798888893
```
