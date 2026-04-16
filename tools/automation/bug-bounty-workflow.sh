#!/bin/bash
# Bug Bounty Automation Workflow

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
THREADS=20
TIMEOUT=30

show_banner() {
    cat << "EOF"
    ╔═══════════════════════════════════════╗
    ║     Bug Bounty Automation Workflow    ║
    ║           Recon -> Scan -> Report      ║
    ╚═══════════════════════════════════════╝
EOF
}

usage() {
    cat << EOF
Usage: $0 -d <domain> [OPTIONS]

OPTIONS:
    -d, --domain      Target domain (required)
    -o, --output      Output directory (default: ./results)
    -s, --scope       Scope file with domains
    -p, --ports       Ports to scan (default: top 1000)
    -h, --help        Show this help

Example:
    $0 -d example.com -o ./example_results
EOF
}

# Parse arguments
DOMAIN=""
OUTPUT_DIR="./results"
SCOPE_FILE=""
PORTS="1000"

while [[ $# -gt 0 ]]; do
    case $1 in
        -d|--domain)
            DOMAIN="$2"
            shift 2
            ;;
        -o|--output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        -s|--scope)
            SCOPE_FILE="$2"
            shift 2
            ;;
        -p|--ports)
            PORTS="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Validate input
if [[ -z "$DOMAIN" && -z "$SCOPE_FILE" ]]; then
    echo -e "${RED}[!] Domain or scope file is required${NC}"
    usage
    exit 1
fi

# Create output directory structure
mkdir -p "$OUTPUT_DIR"/{subdomains,urls,scans,reports}

show_banner

echo -e "${BLUE}[*] Starting Bug Bounty Workflow${NC}"
echo -e "${YELLOW}[*] Target: ${DOMAIN:-"From scope file"}${NC}"
echo -e "${YELLOW}[*] Output: $OUTPUT_DIR${NC}"
echo "================================================"

# Phase 1: Subdomain Enumeration
echo -e "\n${GREEN}[Phase 1] Subdomain Enumeration${NC}"
echo "----------------------------------------"

SUBDOMAIN_FILE="$OUTPUT_DIR/subdomains/all_subdomains.txt"

if [[ -n "$DOMAIN" ]]; then
    echo -e "${YELLOW}[*] Enumerating subdomains for $DOMAIN${NC}"
    
    # Subfinder
    if command -v subfinder &> /dev/null; then
        echo "[*] Running subfinder..."
        subfinder -d "$DOMAIN" -silent -o "$OUTPUT_DIR/subdomains/subfinder.txt"
    fi
    
    # Assetfinder
    if command -v assetfinder &> /dev/null; then
        echo "[*] Running assetfinder..."
        assetfinder --subs-only "$DOMAIN" > "$OUTPUT_DIR/subdomains/assetfinder.txt"
    fi
    
    # Amass (passive)
    if command -v amass &> /dev/null; then
        echo "[*] Running amass (passive)..."
        amass enum -passive -d "$DOMAIN" -o "$OUTPUT_DIR/subdomains/amass.txt"
    fi
    
    # crt.sh
    echo "[*] Querying crt.sh..."
    curl -s "https://crt.sh/?q=%.${DOMAIN}&output=json" | jq -r '.[].name_value' 2>/dev/null | \
        sed 's/\*\.//g' | sort -u > "$OUTPUT_DIR/subdomains/crtsh.txt"
fi

# Combine and deduplicate
cat "$OUTPUT_DIR/subdomains/"*.txt 2>/dev/null | sort -u | grep -v "*" > "$SUBDOMAIN_FILE"

SUBDOMAIN_COUNT=$(wc -l < "$SUBDOMAIN_FILE" 2>/dev/null || echo 0)
echo -e "${GREEN}[+] Found $SUBDOMAIN_COUNT unique subdomains${NC}"

# Phase 2: Live Host Discovery
echo -e "\n${GREEN}[Phase 2] Live Host Discovery${NC}"
echo "----------------------------------------"

LIVE_HOSTS="$OUTPUT_DIR/subdomains/live_hosts.txt"

if command -v httpx &> /dev/null; then
    echo "[*] Probing for live hosts with httpx..."
    httpx -l "$SUBDOMAIN_FILE" -silent -threads "$THREADS" -timeout "$TIMEOUT" \
        -status-code -title -tech-detect -o "$OUTPUT_DIR/subdomains/httpx_full.txt"
    
    cat "$OUTPUT_DIR/subdomains/httpx_full.txt" | awk '{print $1}' > "$LIVE_HOSTS"
else
    echo "[*] Using curl for basic probing..."
    while read -r sub; do
        if curl -s -o /dev/null -w "%{http_code}" "https://$sub" --connect-timeout 5 | grep -q "^[23]"; then
            echo "https://$sub" >> "$LIVE_HOSTS"
        elif curl -s -o /dev/null -w "%{http_code}" "http://$sub" --connect-timeout 5 | grep -q "^[23]"; then
            echo "http://$sub" >> "$LIVE_HOSTS"
        fi
    done < "$SUBDOMAIN_FILE"
fi

LIVE_COUNT=$(wc -l < "$LIVE_HOSTS" 2>/dev/null || echo 0)
echo -e "${GREEN}[+] Found $LIVE_COUNT live hosts${NC}"

# Phase 3: URL Discovery
echo -e "\n${GREEN}[Phase 3] URL Discovery${NC}"
echo "----------------------------------------"

URLS_FILE="$OUTPUT_DIR/urls/all_urls.txt"

while read -r host; do
    # Wayback URLs
    if command -v waybackurls &> /dev/null; then
        echo "$host" | waybackurls >> "$OUTPUT_DIR/urls/wayback.txt" 2>/dev/null
    fi
    
    # GAU
    if command -v gau &> /dev/null; then
        echo "$host" | gau >> "$OUTPUT_DIR/urls/gau.txt" 2>/dev/null
    fi
done < "$LIVE_HOSTS"

# Combine and deduplicate
cat "$OUTPUT_DIR/urls/"*.txt 2>/dev/null | sort -u > "$URLS_FILE"

URL_COUNT=$(wc -l < "$URLS_FILE" 2>/dev/null || echo 0)
echo -e "${GREEN}[+] Collected $URL_COUNT unique URLs${NC}"

# Phase 4: Quick Vulnerability Scanning
echo -e "\n${GREEN}[Phase 4] Quick Vulnerability Scanning${NC}"
echo "----------------------------------------"

# Nuclei scan
if command -v nuclei &> /dev/null; then
    echo "[*] Running nuclei with low-hanging-fruit templates..."
    nuclei -l "$LIVE_HOSTS" -t ~/nuclei-templates/http/exposures/ \
        -severity low,medium,high,critical -silent \
        -o "$OUTPUT_DIR/scans/nuclei_results.txt" 2>/dev/null || true
    
    if [[ -f "$OUTPUT_DIR/scans/nuclei_results.txt" ]]; then
        NUCLEI_COUNT=$(wc -l < "$OUTPUT_DIR/scans/nuclei_results.txt")
        echo -e "${GREEN}[+] Nuclei found $NUCLEI_COUNT potential issues${NC}"
    fi
fi

# Phase 5: Generate Report
echo -e "\n${GREEN}[Phase 5] Report Generation${NC}"
echo "----------------------------------------"

REPORT_FILE="$OUTPUT_DIR/reports/summary_$(date +%Y%m%d_%H%M%S).md"

cat > "$REPORT_FILE" << EOF
# Bug Bounty Reconnaissance Report

**Target:** ${DOMAIN:-"Multiple (from scope)"}  
**Date:** $(date)  
**Tool:** Bug Bounty Automation Workflow

## Summary Statistics
- **Subdomains Discovered:** $SUBDOMAIN_COUNT
- **Live Hosts:** $LIVE_COUNT
- **URLs Collected:** $URL_COUNT

## Live Hosts
\`\`\`
$(head -n 20 "$LIVE_HOSTS" 2>/dev/null || echo "No live hosts found")
\`\`\`

## Potential Vulnerabilities
\`\`\`
$(head -n 20 "$OUTPUT_DIR/scans/nuclei_results.txt" 2>/dev/null || echo "None found")
\`\`\`

## Next Steps
1. Review live hosts manually
2. Test interesting parameters from URL list
3. Run more comprehensive scans on promising targets
4. Check for business logic vulnerabilities

---
*Generated by Bug Bounty Automation Workflow*
EOF

echo -e "${GREEN}[+] Report generated: $REPORT_FILE${NC}"

# Final Summary
echo -e "\n${BLUE}╔═══════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         Workflow Complete!            ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════╝${NC}"
echo -e "\n${YELLOW}Results saved in: $OUTPUT_DIR${NC}"
echo -e "${YELLOW}  - Subdomains: $OUTPUT_DIR/subdomains/${NC}"
echo -e "${YELLOW}  - URLs: $OUTPUT_DIR/urls/${NC}"
echo -e "${YELLOW}  - Scans: $OUTPUT_DIR/scans/${NC}"
echo -e "${YELLOW}  - Report: $REPORT_FILE${NC}"

# Usage: ./bug-bounty-workflow.sh -d example.com -o ./results
