#!/usr/bin/env python3
"""
rate_limit_profiler.py — Profiles an endpoint's rate limiting behavior.

Usage:
  python3 rate_limit_profiler.py --url https://target.com/api/v1/login \
    --method POST --body '{"user":"a","pass":"b"}' --max-requests 200 --out rl_profile
"""

import argparse
import json
import time
from pathlib import Path

import requests


def main():
    ap = argparse.ArgumentParser(description="Rate limit behavior profiler")
    ap.add_argument("--url", required=True)
    ap.add_argument("--method", default="GET")
    ap.add_argument("--body", default=None, help="JSON body string (for POST/PUT)")
    ap.add_argument("--headers", action="append", default=[], help="header:value, repeatable")
    ap.add_argument("--max-requests", type=int, default=200)
    ap.add_argument("--out", default="rate_limit_profile")
    args = ap.parse_args()

    headers = {}
    for h in args.headers:
        k, v = h.split(":", 1)
        headers[k.strip()] = v.strip()
    body = json.loads(args.body) if args.body else None

    results = []
    blocked_at = None
    start = time.time()

    print(f"[*] Firing up to {args.max_requests} requests at {args.url} ...")
    for i in range(1, args.max_requests + 1):
        t0 = time.time()
        try:
            r = requests.request(args.method, args.url, json=body, headers=headers, timeout=10)
            elapsed = time.time() - t0
            rl_headers = {k: v for k, v in r.headers.items() if "rate" in k.lower() or "retry" in k.lower()}
            results.append({
                "n": i, "status": r.status_code, "elapsed": round(elapsed, 3),
                "rate_limit_headers": rl_headers, "len": len(r.content),
            })
            if blocked_at is None and r.status_code in (429, 403):
                blocked_at = i
                print(f"[!] First blocking response at request #{i} (status {r.status_code})")
                print(f"    Headers: {rl_headers}")
        except requests.RequestException as e:
            results.append({"n": i, "status": -1, "error": str(e)})

        if i % 25 == 0:
            print(f"    ... {i}/{args.max_requests} sent ({i/(time.time()-start):.1f} req/s)")

    outdir = Path(args.out)
    outdir.mkdir(parents=True, exist_ok=True)
    with open(outdir / "profile.json", "w") as f:
        json.dump(results, f, indent=2)

    with open(outdir / "SUMMARY.md", "w") as f:
        f.write(f"# Rate Limit Profile — {args.url}\n\n")
        f.write(f"Total requests sent: {len(results)}  \n")
        f.write(f"Total time: {time.time()-start:.1f}s  \n")
        f.write(f"First block observed at request: **{blocked_at or 'Never (no rate limit detected!)'}**\n\n")
        if not blocked_at:
            f.write("⚠️ No rate limiting detected within tested range — flag as a finding "
                    "(API4:2023 Unrestricted Resource Consumption).\n")

    print(f"\n[+] Done. Report: {outdir}/SUMMARY.md")
    if not blocked_at:
        print("[!] NO RATE LIMITING DETECTED — this is itself a reportable finding.")


if __name__ == "__main__":
    main()
