#!/usr/bin/env python3
"""
graphql_recon.py — GraphQL introspection dumper & attack-surface mapper.

Usage:
  python3 graphql_recon.py --url https://target.com/graphql --out graphql_recon
  python3 graphql_recon.py --url https://target.com/graphql --token "Bearer TOKEN" --out graphql_recon
"""

import argparse
import json
import sys
from pathlib import Path

try:
    import requests
except ImportError:
    sys.exit("Missing dependency: pip install requests")

INTROSPECTION_QUERY = """
query IntrospectionQuery {
  __schema {
    queryType { name }
    mutationType { name }
    subscriptionType { name }
    types {
      kind
      name
      description
      fields(includeDeprecated: true) {
        name
        description
        args { name type { name kind ofType { name kind } } }
        type { name kind ofType { name kind ofType { name kind } } }
      }
    }
  }
}
"""


def run_query(url, query, headers, variables=None):
    body = {"query": query}
    if variables:
        body["variables"] = variables
    r = requests.post(url, json=body, headers=headers, timeout=20)
    try:
        return r.status_code, r.json()
    except ValueError:
        return r.status_code, {"raw": r.text[:500]}


def check_field_suggestion(url, headers):
    """If introspection is off, probe with a deliberately misspelled field to trigger
    'Did you mean ...' suggestions that leak real field/type names."""
    status, data = run_query(url, "query{ qqqqnonexistentfield }", headers)
    errors = data.get("errors", []) if isinstance(data, dict) else []
    suggestions = []
    for e in errors:
        msg = e.get("message", "")
        if "did you mean" in msg.lower():
            suggestions.append(msg)
    return suggestions


def main():
    ap = argparse.ArgumentParser(description="GraphQL recon / schema extraction tool")
    ap.add_argument("--url", required=True)
    ap.add_argument("--token", default=None)
    ap.add_argument("--out", default="graphql_recon")
    args = ap.parse_args()

    headers = {"Content-Type": "application/json"}
    if args.token:
        headers["Authorization"] = args.token

    outdir = Path(args.out)
    outdir.mkdir(parents=True, exist_ok=True)

    print("[*] Attempting introspection...")
    status, data = run_query(args.url, INTROSPECTION_QUERY, headers)

    schema = data.get("data", {}).get("__schema") if isinstance(data, dict) else None

    if schema:
        print("[+] Introspection ENABLED — full schema recovered.")
        with open(outdir / "schema.json", "w") as f:
            json.dump(schema, f, indent=2)

        query_type = schema.get("queryType", {}).get("name")
        mutation_type = schema.get("mutationType", {}).get("name")
        types = {t["name"]: t for t in schema.get("types", []) if t.get("name")}

        with open(outdir / "ATTACK_SURFACE.md", "w") as f:
            f.write(f"# GraphQL Attack Surface — {args.url}\n\n")

            for label, type_name in [("Queries", query_type), ("Mutations", mutation_type)]:
                f.write(f"## {label}\n\n")
                t = types.get(type_name, {})
                for field in t.get("fields") or []:
                    args_str = ", ".join(
                        f"{a['name']}: {(a['type'].get('name') or a['type'].get('ofType', {}).get('name', '?'))}"
                        for a in field.get("args", [])
                    )
                    f.write(f"- **{field['name']}**({args_str})")
                    if field.get("description"):
                        f.write(f" — {field['description']}")
                    f.write("\n")
                f.write("\n")

            f.write("## All Types (look for Admin/Internal/Secret in names)\n\n")
            for name in sorted(types.keys()):
                if not name.startswith("__"):
                    flag = " ⚠️" if any(w in name.lower() for w in
                                        ["admin", "internal", "secret", "debug", "private"]) else ""
                    f.write(f"- {name}{flag}\n")

        print(f"[+] Attack surface map written to {outdir}/ATTACK_SURFACE.md")

    else:
        print("[-] Introspection appears DISABLED. Trying field-suggestion leak technique...")
        suggestions = check_field_suggestion(args.url, headers)
        with open(outdir / "field_suggestions.txt", "w") as f:
            if suggestions:
                f.write("\n".join(suggestions))
                print(f"[+] {len(suggestions)} field-name leaks found via error suggestions.")
            else:
                f.write("No suggestions leaked. Try clairvoyance for brute-force schema recovery:\n")
                f.write(f"  clairvoyance -o schema.json {args.url}\n")
                print("[-] No leaks found via suggestion technique. Recommend trying `clairvoyance`.")

    # Always check batching support (DoS-relevant, API4/API6:2023)
    print("[*] Testing batch-query support (DoS surface)...")
    batch_status, batch_data = run_query(
        args.url, None, headers, variables=None
    ) if False else (None, None)  # placeholder to keep single-query helper reusable

    batch_body = [{"query": "{__typename}"}] * 5
    r = requests.post(args.url, json=batch_body, headers=headers, timeout=20)
    if r.status_code == 200 and isinstance(r.json(), list):
        print("[!] Batching is ALLOWED — can be abused for brute force / rate-limit bypass / DoS.")
    else:
        print("[+] Batching appears blocked or unsupported.")


if __name__ == "__main__":
    main()
