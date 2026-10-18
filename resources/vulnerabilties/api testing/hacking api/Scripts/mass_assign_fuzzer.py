#!/usr/bin/env python3
"""
mass_assign_fuzzer.py — Mass assignment / hidden privileged-field scanner.

Usage:
  python3 mass_assign_fuzzer.py \
    --url https://target.com/api/v1/register \
    --method POST \
    --base-body '{"username":"fuzztest","email":"fuzz@test.com","password":"Password1!"}' \
    --token "Bearer TOKEN" \
    --out mass_assign_results
"""

import argparse
import json
import sys
from pathlib import Path

try:
    import requests
except ImportError:
    sys.exit("Missing dependency: pip install requests")


DEFAULT_FIELDS = [
    "admin", "isAdmin", "is_admin", "role", "roles", "user_role", "account_type",
    "accountType", "permission", "permissions", "privilege", "isPremium", "is_premium",
    "verified", "is_verified", "active", "enabled", "approved", "status",
    "balance", "credit", "credits", "discount", "price", "superuser", "is_staff",
    "group", "org", "organization_id", "tenant_id", "plan", "subscription_tier",
]


def send(url, method, body, headers, timeout=15):
    try:
        r = requests.request(method, url, json=body, headers=headers, timeout=timeout)
        return r.status_code, len(r.content), r.text[:300]
    except requests.RequestException as e:
        return -1, 0, str(e)


def main():
    ap = argparse.ArgumentParser(description="Mass assignment / hidden field scanner")
    ap.add_argument("--url", required=True)
    ap.add_argument("--method", default="POST")
    ap.add_argument("--base-body", required=True, help="JSON string of the normal/expected request body")
    ap.add_argument("--token", default=None, help="Authorization header value")
    ap.add_argument("--fields", default=None, help="Path to custom newline-separated field wordlist")
    ap.add_argument("--out", default="mass_assign_results")
    args = ap.parse_args()

    base_body = json.loads(args.base_body)
    headers = {"Content-Type": "application/json"}
    if args.token:
        headers["Authorization"] = args.token

    fields = DEFAULT_FIELDS
    if args.fields:
        fields = [l.strip() for l in open(args.fields) if l.strip()]

    print(f"[*] Baseline request...")
    base_status, base_len, _ = send(args.url, args.method, base_body, headers)
    print(f"    Baseline: status={base_status} len={base_len}")

    results = []
    for field in fields:
        for value in (True, 1, "admin"):
            body = dict(base_body)
            body[field] = value
            status, length, snippet = send(args.url, args.method, body, headers)
            diff = (status != base_status) or (abs(length - base_len) > 5)
            results.append({
                "field": field, "value": value, "status": status, "length": length,
                "baseline_status": base_status, "baseline_length": base_len,
                "anomalous": diff, "snippet": snippet if diff else "",
            })
            flag = " <-- ANOMALY" if diff else ""
            print(f"  {field:20} = {str(value):8} -> status={status} len={length}{flag}")

    outdir = Path(args.out)
    outdir.mkdir(parents=True, exist_ok=True)
    with open(outdir / "results.json", "w") as f:
        json.dump(results, f, indent=2)

    anomalies = [r for r in results if r["anomalous"]]
    with open(outdir / "report.md", "w") as f:
        f.write("# Mass Assignment Fuzz Report\n\n")
        f.write(f"Baseline: status={base_status}, length={base_len}\n\n")
        f.write(f"**{len(anomalies)} anomalous field/value combos found** (out of {len(results)} tested)\n\n")
        if anomalies:
            f.write("| Field | Value | Status | Length |\n|---|---|---|---|\n")
            for a in anomalies:
                f.write(f"| {a['field']} | {a['value']} | {a['status']} | {a['length']} |\n")

    print(f"\n[+] {len(anomalies)} anomalies found. Reports in {outdir}/")


if __name__ == "__main__":
    main()
