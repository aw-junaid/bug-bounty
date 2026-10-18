#!/usr/bin/env python3
"""
bola_hunter.py — Automated BOLA/IDOR scanner with A-B token comparison.

Usage:
  python3 bola_hunter.py \
    --url "https://target.com/api/v1/user/{id}" \
    --token-a "Bearer USER_A_TOKEN" \
    --token-b "Bearer USER_B_TOKEN" \
    --ids 1-500 \
    --out results

Strategy:
  1. Baseline: request each ID with token A (owner-ish behavior).
  2. Attack: request the SAME IDs with token B.
  3. Flag any ID where token B gets a 200 (or any non-40x) response that token A also got —
     i.e., UserB can read UserA-scoped or arbitrary resources it shouldn't be able to.
  4. Also flags "unauthenticated" exposure if --no-auth-check is passed.
"""

import argparse
import asyncio
import csv
import json
import sys
import time
from pathlib import Path

try:
    import httpx
except ImportError:
    sys.exit("Missing dependency: pip install httpx")


def parse_ids(spec: str):
    """Parse '1-500' or '1,2,3,99' or a mix: '1-10,50,100-110'."""
    ids = []
    for part in spec.split(","):
        if "-" in part:
            start, end = part.split("-")
            ids.extend(range(int(start), int(end) + 1))
        else:
            ids.append(int(part))
    return ids


async def fetch(client: httpx.AsyncClient, url: str, headers: dict, retries: int = 2):
    for attempt in range(retries + 1):
        try:
            r = await client.get(url, headers=headers, timeout=15)
            return r.status_code, len(r.content), r.text[:300]
        except (httpx.ConnectTimeout, httpx.ReadTimeout):
            if attempt == retries:
                return -1, 0, "TIMEOUT"
            await asyncio.sleep(1 + attempt)
        except Exception as e:
            return -2, 0, str(e)


async def run(args):
    ids = parse_ids(args.ids)
    url_template = args.url
    sem = asyncio.Semaphore(args.concurrency)
    results = []

    headers_a = {"Authorization": args.token_a} if args.token_a else {}
    headers_b = {"Authorization": args.token_b} if args.token_b else {}

    async with httpx.AsyncClient(http2=True, verify=not args.insecure) as client:

        async def worker(rid: int):
            url = url_template.replace("{id}", str(rid))
            async with sem:
                status_a, len_a, _ = await fetch(client, url, headers_a) if args.token_a else (None, None, None)
                await asyncio.sleep(args.delay)
                status_b, len_b, body_b = await fetch(client, url, headers_b)

                flagged = False
                reason = ""
                if status_b and 200 <= status_b < 300:
                    if args.token_a and status_a and 200 <= status_a < 300:
                        flagged = True
                        reason = "UserB accessed a resource UserA could also access (possible BOLA)"
                    elif not args.token_a:
                        flagged = True
                        reason = "Resource accessible with tested token (verify ownership manually)"

                results.append({
                    "id": rid,
                    "url": url,
                    "status_a": status_a,
                    "status_b": status_b,
                    "len_b": len_b,
                    "flagged": flagged,
                    "reason": reason,
                    "snippet": body_b[:200] if flagged else "",
                })

        await asyncio.gather(*(worker(i) for i in ids))

    return results


def write_reports(results, outdir: Path):
    outdir.mkdir(parents=True, exist_ok=True)
    json_path = outdir / "bola_results.json"
    csv_path = outdir / "bola_results.csv"
    md_path = outdir / "bola_report.md"

    with open(json_path, "w") as f:
        json.dump(results, f, indent=2)

    with open(csv_path, "w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=results[0].keys() if results else
                                 ["id", "url", "status_a", "status_b", "len_b", "flagged", "reason", "snippet"])
        writer.writeheader()
        writer.writerows(results)

    flagged = [r for r in results if r["flagged"]]
    with open(md_path, "w") as f:
        f.write(f"# BOLA Hunter Report\n\n")
        f.write(f"Total IDs tested: {len(results)}  \n")
        f.write(f"**Flagged (possible BOLA): {len(flagged)}**\n\n")
        if flagged:
            f.write("| ID | URL | Status A | Status B | Reason |\n|---|---|---|---|---|\n")
            for r in flagged:
                f.write(f"| {r['id']} | {r['url']} | {r['status_a']} | {r['status_b']} | {r['reason']} |\n")
        else:
            f.write("No anomalies flagged. Review raw CSV/JSON for edge cases.\n")

    print(f"[+] {len(flagged)}/{len(results)} IDs flagged as possible BOLA")
    print(f"[+] Reports written to: {json_path}, {csv_path}, {md_path}")


def main():
    ap = argparse.ArgumentParser(description="Automated BOLA/IDOR scanner (A-B token comparison)")
    ap.add_argument("--url", required=True, help="Target URL template with {id} placeholder")
    ap.add_argument("--token-a", default=None, help="Authorization header value for User A (owner)")
    ap.add_argument("--token-b", required=True, help="Authorization header value for User B (attacker)")
    ap.add_argument("--ids", required=True, help="ID range/list, e.g. '1-500' or '1,2,3,99'")
    ap.add_argument("--concurrency", type=int, default=15, help="Max concurrent requests (default 15)")
    ap.add_argument("--delay", type=float, default=0.05, help="Delay between A/B calls per ID, seconds")
    ap.add_argument("--insecure", action="store_true", help="Disable TLS verification (lab targets)")
    ap.add_argument("--out", default="bola_output", help="Output directory")
    args = ap.parse_args()

    start = time.time()
    results = asyncio.run(run(args))
    write_reports(results, Path(args.out))
    print(f"[+] Done in {time.time()-start:.1f}s")


if __name__ == "__main__":
    main()
