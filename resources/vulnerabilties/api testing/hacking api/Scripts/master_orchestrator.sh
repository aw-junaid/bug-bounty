#!/usr/bin/env bash
#
# master_orchestrator.sh — Run the full API testing automation chain in one shot.
# Usage: ./master_orchestrator.sh target.com [token_a] [token_b]
#
set -euo pipefail

TARGET="${1:?Usage: $0 <target.com> [token_a] [token_b]}"
TOKEN_A="${2:-}"
TOKEN_B="${3:-}"
OUTDIR="./engagement_${TARGET}_$(date +%Y%m%d)"
mkdir -p "$OUTDIR"

echo "=== [1/4] Recon ==="
./api_recon.sh "$TARGET" "$OUTDIR/recon"

echo "=== [2/4] GraphQL check (if endpoint found) ==="
GQL_URL=$(grep -im1 'graphql' "$OUTDIR/recon/api_candidates.txt" || true)
if [ -n "$GQL_URL" ]; then
  python3 graphql_recon.py --url "$GQL_URL" ${TOKEN_B:+--token "$TOKEN_B"} --out "$OUTDIR/graphql"
else
  echo "No GraphQL endpoint found in recon results."
fi

echo "=== [3/4] Rate limit profile on first discovered API endpoint ==="
FIRST_API=$(head -n1 "$OUTDIR/recon/api_candidates.txt" || true)
if [ -n "$FIRST_API" ]; then
  python3 rate_limit_profiler.py --url "$FIRST_API" --max-requests 100 --out "$OUTDIR/rate_limit"
fi

echo "=== [4/4] BOLA sweep (only if both tokens supplied) ==="
if [ -n "$TOKEN_A" ] && [ -n "$TOKEN_B" ]; then
  python3 bola_hunter.py \
    --url "${FIRST_API%/*}/{id}" \
    --token-a "$TOKEN_A" --token-b "$TOKEN_B" \
    --ids 1-200 --out "$OUTDIR/bola"
else
  echo "Skipping BOLA sweep — pass TOKEN_A and TOKEN_B to enable."
fi

echo ""
echo "=========================================="
echo " All results saved under: $OUTDIR"
echo "=========================================="
