#!/bin/bash
# URL Collector - Gathers URLs from various sources

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

show_help() {
    cat << EOF
URL Collector Tool
Usage: $0 -d <domain> [OPTIONS]

OPTIONS:
    -d, --domain      Target domain (required)
    -o, --output      Output file (default: urls_\${domain}.txt)
    -g, --gau         Use gau for historical URLs
    -w, --wayback     Use waybackurls
    -a, --all         Use all sources
    -h, --help        Show this help message

Example:
    $0 -d example.com -a
EOF
}

# Default values
DOMAIN=""
OUTPUT=""
USE_GAU=false
USE_WAYBACK=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -d|--domain)
            DOMAIN="$2"
            shift 2
            ;;
        -o|--output)
            OUTPUT="$2"
            shift 2
            ;;
        -g|--gau)
            USE_GAU=true
            shift
            ;;
        -w|--wayback)
            USE_WAYBACK=true
            shift
            ;;
        -a|--all)
            USE_GAU=true
            USE_WAYBACK=true
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Validate domain
if [[ -z "$DOMAIN" ]]; then
    echo -e "${RED}[!] Domain is required${NC}"
    show_help
    exit 1
fi

# Set output file
if [[ -z "$OUTPUT" ]]; then
    OUTPUT="urls_${DOMAIN}.txt"
fi

echo -e "${GREEN}[+] Starting URL collection for $DOMAIN${NC}"
echo -e "${YELLOW}[*] Output will be saved to: $OUTPUT${NC}"

# Temporary file for collecting URLs
TEMP_FILE=$(mktemp)

# Wayback Machine
if [[ "$USE_WAYBACK" = true ]]; then
    echo -e "${YELLOW}[*] Fetching URLs from Wayback Machine...${NC}"
    if command -v waybackurls &> /dev/null; then
        echo "$DOMAIN" | waybackurls >> "$TEMP_FILE"
    else
        # Fallback to curl if waybackurls not installed
        curl -s "http://web.archive.org/cdx/search/cdx?url=*.${DOMAIN}/*&output=text&fl=original&collapse=urlkey" >> "$TEMP_FILE"
    fi
fi

# GAU (Get All URLs)
if [[ "$USE_GAU" = true ]]; then
    echo -e "${YELLOW}[*] Fetching URLs using GAU...${NC}"
    if command -v gau &> /dev/null; then
        echo "$DOMAIN" | gau >> "$TEMP_FILE"
    else
        echo -e "${RED}[!] GAU not installed. Install with: go install github.com/lc/gau/v2/cmd/gau@latest${NC}"
    fi
fi

# AlienVault OTX
echo -e "${YELLOW}[*] Fetching URLs from AlienVault OTX...${NC}"
curl -s "https://otx.alienvault.com/api/v1/indicators/domain/${DOMAIN}/url_list?limit=500" | \
    jq -r '.url_list[].url' 2>/dev/null >> "$TEMP_FILE" || echo -e "${RED}[!] Failed to fetch from AlienVault${NC}"

# URLScan.io
echo -e "${YELLOW}[*] Fetching URLs from URLScan.io...${NC}"
curl -s "https://urlscan.io/api/v1/search/?q=domain:${DOMAIN}" | \
    jq -r '.results[].page.url' 2>/dev/null >> "$TEMP_FILE" || echo -e "${RED}[!] Failed to fetch from URLScan${NC}"

# Filter, sort, and remove duplicates
echo -e "${YELLOW}[*] Processing and deduplicating URLs...${NC}"
cat "$TEMP_FILE" | \
    grep -E "https?://" | \
    grep -i "$DOMAIN" | \
    sort -u > "$OUTPUT"

# Count URLs
URL_COUNT=$(wc -l < "$OUTPUT")
echo -e "${GREEN}[+] Collected $URL_COUNT unique URLs${NC}"

# Cleanup
rm "$TEMP_FILE"

# Show sample
echo -e "\n${GREEN}[+] Sample URLs:${NC}"
head -n 5 "$OUTPUT"

echo -e "\n${GREEN}[✓] Done! Full results saved to: $OUTPUT${NC}"

# Usage: ./url-collector.sh -d example.com -a
