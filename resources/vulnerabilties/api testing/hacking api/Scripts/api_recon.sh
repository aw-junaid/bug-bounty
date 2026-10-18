#!/usr/bin/env bash
#
# api_recon.sh — End-to-end API recon chain
# Usage: ./api_recon.sh target.com
#
set -euo pipefail

if [ $# -lt 1 ]; then
  echo "Usage: $0 <domain> [output_dir]"
  exit 1
fi

TARGET="$1"
OUTDIR="${2:-./recon_$TARGET}"
TS=$(date +"%Y-%m-%d_%H-%M-%S")
LOG="$OUTDIR/recon.log"

mkdir -p "$OUTDIR"
exec > >(tee -a "$LOG") 2>&1

log() { echo -e "\n[$(date +%T)] >> $1"; }

check_tool() {
  if ! command -v "$1" &>/dev/null; then
    echo "  [!] $1 not found — skipping this step (install with: $2)"
    return 1
  fi
  return 0
}

log "Starting recon for $TARGET -> $OUTDIR"

# 1. Subdomain enumeration
log "Step 1/6: Subdomain enumeration"
if check_tool subfinder "go install github.com/projectdiscovery/subfinder/v2/cmd/subfinder@latest"; then
  subfinder -d "$TARGET" -silent -o "$OUTDIR/subdomains.txt"
  echo "  Found $(wc -l < "$OUTDIR/subdomains.txt") subdomains"
else
  echo "$TARGET" > "$OUTDIR/subdomains.txt"
fi

# 2. Live host probing
log "Step 2/6: Live host probing"
if check_tool httpx "go install github.com/projectdiscovery/httpx/cmd/httpx@latest"; then
  httpx -l "$OUTDIR/subdomains.txt" -silent -status-code -title -tech-detect -o "$OUTDIR/live_hosts.txt"
  echo "  Found $(wc -l < "$OUTDIR/live_hosts.txt") live hosts"
else
  cp "$OUTDIR/subdomains.txt" "$OUTDIR/live_hosts.txt"
fi

# 3. Crawl for URLs
log "Step 3/6: Crawling"
if check_tool katana "go install github.com/projectdiscovery/katana/cmd/katana@latest"; then
  awk '{print $1}' "$OUTDIR/live_hosts.txt" | katana -d 3 -jc -silent -o "$OUTDIR/crawled_urls.txt"
  echo "  Crawled $(wc -l < "$OUTDIR/crawled_urls.txt") URLs"
fi

# 4. Historical URLs (catches old/forgotten API versions)
log "Step 4/6: Historical URL mining (gau/waybackurls)"
> "$OUTDIR/historical_urls.txt"
if check_tool gau "go install github.com/lc/gau/v2/cmd/gau@latest"; then
  gau "$TARGET" --subs >> "$OUTDIR/historical_urls.txt" 2>/dev/null || true
fi

# 5. Filter for API-looking paths
log "Step 5/6: Filtering for API candidates"
cat "$OUTDIR/crawled_urls.txt" "$OUTDIR/historical_urls.txt" 2>/dev/null \
  | grep -Eio 'https?://[^ ]*(/api/|/v[0-9]+/|/graphql|/rest/|/rpc/)[^ ]*' \
  | sort -u > "$OUTDIR/api_candidates.txt"
echo "  Found $(wc -l < "$OUTDIR/api_candidates.txt") API candidate URLs"

# 6. Quick nuclei exposure/misconfig pass
log "Step 6/6: Nuclei scan"
if check_tool nuclei "go install github.com/projectdiscovery/nuclei/v3/cmd/nuclei@latest"; then
  nuclei -l "$OUTDIR/api_candidates.txt" -tags api,exposure,misconfig,token,jwt \
    -severity low,medium,high,critical -silent -o "$OUTDIR/nuclei_findings.txt" || true
  echo "  Nuclei findings: $(wc -l < "$OUTDIR/nuclei_findings.txt" 2>/dev/null || echo 0)"
fi

# Summary report
cat > "$OUTDIR/SUMMARY.md" << EOF
# Recon Summary — $TARGET
Generated: $TS

| Metric | Count |
|---|---|
| Subdomains | $(wc -l < "$OUTDIR/subdomains.txt" 2>/dev/null || echo 0) |
| Live hosts | $(wc -l < "$OUTDIR/live_hosts.txt" 2>/dev/null || echo 0) |
| Crawled URLs | $(wc -l < "$OUTDIR/crawled_urls.txt" 2>/dev/null || echo 0) |
| API candidates | $(wc -l < "$OUTDIR/api_candidates.txt" 2>/dev/null || echo 0) |
| Nuclei findings | $(wc -l < "$OUTDIR/nuclei_findings.txt" 2>/dev/null || echo 0) |

## Next steps
- Review \`api_candidates.txt\` manually for Swagger/GraphQL/admin paths
- Feed \`api_candidates.txt\` into \`bola_hunter.py\` once you have an auth token
- Review \`nuclei_findings.txt\` for anything tagged critical/high first
EOF

log "Done. Summary: $OUTDIR/SUMMARY.md"
