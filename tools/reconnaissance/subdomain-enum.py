#!/usr/bin/env python3
"""
Subdomain Enumeration Tool
Combines multiple sources for comprehensive subdomain discovery
"""

import requests
import sys
import json
import time
from concurrent.futures import ThreadPoolExecutor
import argparse

class SubdomainEnumerator:
    def __init__(self, domain, threads=10):
        self.domain = domain
        self.threads = threads
        self.subdomains = set()
        
    def crtsh_enum(self):
        """Fetch subdomains from crt.sh"""
        try:
            url = f"https://crt.sh/?q=%.{self.domain}&output=json"
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                for entry in data:
                    name = entry.get('name_value', '')
                    for sub in name.split('\n'):
                        if self.domain in sub:
                            self.subdomains.add(sub.strip().lower())
        except Exception as e:
            print(f"[-] crt.sh error: {e}")
    
    def alienvault_enum(self):
        """Fetch subdomains from AlienVault OTX"""
        try:
            url = f"https://otx.alienvault.com/api/v1/indicators/domain/{self.domain}/passive_dns"
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                for entry in data.get('passive_dns', []):
                    hostname = entry.get('hostname', '')
                    if self.domain in hostname:
                        self.subdomains.add(hostname.lower())
        except Exception as e:
            print(f"[-] AlienVault error: {e}")
    
    def wordlist_enum(self, wordlist):
        """Brute force using wordlist"""
        def check_subdomain(sub):
            url = f"https://{sub}.{self.domain}"
            try:
                response = requests.get(url, timeout=3, verify=False)
                if response.status_code < 500:
                    self.subdomains.add(f"{sub}.{self.domain}")
                    print(f"[+] Found: {sub}.{self.domain}")
            except:
                pass
        
        with open(wordlist, 'r') as f:
            subs = [line.strip() for line in f if line.strip()]
        
        with ThreadPoolExecutor(max_workers=self.threads) as executor:
            executor.map(check_subdomain, subs[:100])  # Limit to first 100 for demo
    
    def run(self, wordlist=None):
        """Run all enumeration methods"""
        print(f"[*] Starting subdomain enumeration for {self.domain}")
        
        print("[*] Querying crt.sh...")
        self.crtsh_enum()
        
        print("[*] Querying AlienVault...")
        self.alienvault_enum()
        
        if wordlist:
            print("[*] Starting wordlist brute force...")
            self.wordlist_enum(wordlist)
        
        print(f"\n[+] Found {len(self.subdomains)} unique subdomains")
        return sorted(list(self.subdomains))

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Subdomain Enumeration Tool')
    parser.add_argument('-d', '--domain', required=True, help='Target domain')
    parser.add_argument('-w', '--wordlist', help='Wordlist for brute force')
    parser.add_argument('-o', '--output', help='Output file')
    parser.add_argument('-t', '--threads', type=int, default=10, help='Thread count')
    
    args = parser.parse_args()
    
    enum = SubdomainEnumerator(args.domain, args.threads)
    results = enum.run(args.wordlist)
    
    if args.output:
        with open(args.output, 'w') as f:
            for sub in results:
                f.write(sub + '\n')
        print(f"[+] Results saved to {args.output}")
    
    for sub in results:
        print(sub)

# Usage: python3 subdomain-enum.py -d example.com -w wordlist.txt -o output.txt
