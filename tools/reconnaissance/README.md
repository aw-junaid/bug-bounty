# Reconnaissance Tools

## subdomain-enum.py
Multi-source subdomain enumeration tool.
- crt.sh certificate transparency logs
- AlienVault OTX passive DNS
- Wordlist brute forcing

**Usage:** `python3 subdomain-enum.py -d example.com -w subdomains.txt`

## url-collector.sh
URL harvesting from multiple sources.
- Wayback Machine
- GAU (Get All URLs)
- AlienVault OTX
- URLScan.io

**Usage:** `./url-collector.sh -d example.com -a`
