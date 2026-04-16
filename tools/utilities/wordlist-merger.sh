#!/bin/bash
# Wordlist Merger - Combine, deduplicate, and optimize wordlists

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

show_help() {
    cat << EOF
Wordlist Merger Tool
Usage: $0 [OPTIONS] -i <input_files> -o <output_file>

OPTIONS:
    -i, --input      Input wordlist files (space-separated or wildcard)
    -o, --output     Output file (required)
    -s, --sort       Sort alphabetically (default: true)
    -m, --min-length Minimum word length (default: 1)
    -M, --max-length Maximum word length (default: 100)
    -f, --filter     Filter pattern (regex)
    -u, --unique     Remove duplicates (default: true)
    -l, --lowercase  Convert to lowercase (default: false)
    -c, --count      Show statistics only
    -h, --help       Show this help

Examples:
    $0 -i "wordlist1.txt wordlist2.txt" -o merged.txt
    $0 -i "*.txt" -o merged.txt -m 3 -M 20 -l
    $0 -i "/path/to/wordlists/*" -o combined.txt -f "^[a-z]"
EOF
}

# Default values
SORT=true
MIN_LENGTH=1
MAX_LENGTH=100
FILTER=""
UNIQUE=true
LOWERCASE=false
COUNT_ONLY=false
INPUT_FILES=""
OUTPUT_FILE=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -i|--input)
            INPUT_FILES="$2"
            shift 2
            ;;
        -o|--output)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        -s|--sort)
            SORT="$2"
            shift 2
            ;;
        -m|--min-length)
            MIN_LENGTH="$2"
            shift 2
            ;;
        -M|--max-length)
            MAX_LENGTH="$2"
            shift 2
            ;;
        -f|--filter)
            FILTER="$2"
            shift 2
            ;;
        -u|--unique)
            UNIQUE="$2"
            shift 2
            ;;
        -l|--lowercase)
            LOWERCASE=true
            shift
            ;;
        -c|--count)
            COUNT_ONLY=true
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

# Validate inputs
if [[ -z "$INPUT_FILES" ]]; then
    echo -e "${RED}[!] Input files required${NC}"
    show_help
    exit 1
fi

if [[ "$COUNT_ONLY" = false && -z "$OUTPUT_FILE" ]]; then
    echo -e "${RED}[!] Output file required${NC}"
    show_help
    exit 1
fi

echo -e "${BLUE}[*] Wordlist Merger Started${NC}"
echo "================================================"

# Temporary file
TEMP_FILE=$(mktemp)
TOTAL_WORDS=0

# Process each input file
echo -e "${YELLOW}[*] Processing input files...${NC}"
for file in $INPUT_FILES; do
    if [[ -f "$file" ]]; then
        file_count=$(wc -l < "$file")
        echo -e "  - ${file} (${file_count} words)"
        
        # Apply filters while reading
        if [[ -n "$FILTER" ]]; then
            grep -E "$FILTER" "$file" >> "$TEMP_FILE" 2>/dev/null || true
        else
            cat "$file" >> "$TEMP_FILE"
        fi
    else
        echo -e "${RED}  [!] File not found: $file${NC}"
    fi
done

# Count initial words
INITIAL_COUNT=$(wc -l < "$TEMP_FILE")
echo -e "\n${GREEN}[+] Total words before processing: $INITIAL_COUNT${NC}"

# Apply transformations
echo -e "${YELLOW}[*] Applying transformations...${NC}"

# Length filtering
if [[ $MIN_LENGTH -gt 1 ]] || [[ $MAX_LENGTH -lt 100 ]]; then
    echo "  - Filtering by length ($MIN_LENGTH - $MAX_LENGTH chars)"
    awk -v min="$MIN_LENGTH" -v max="$MAX_LENGTH" \
        'length >= min && length <= max' "$TEMP_FILE" > "${TEMP_FILE}.filtered"
    mv "${TEMP_FILE}.filtered" "$TEMP_FILE"
fi

# Convert to lowercase
if [[ "$LOWERCASE" = true ]]; then
    echo "  - Converting to lowercase"
    tr '[:upper:]' '[:lower:]' < "$TEMP_FILE" > "${TEMP_FILE}.lower"
    mv "${TEMP_FILE}.lower" "$TEMP_FILE"
fi

# Remove duplicates
if [[ "$UNIQUE" = true ]]; then
    echo "  - Removing duplicates"
    sort -u "$TEMP_FILE" -o "${TEMP_FILE}.unique"
    mv "${TEMP_FILE}.unique" "$TEMP_FILE"
fi

# Sort alphabetically
if [[ "$SORT" = true ]]; then
    echo "  - Sorting alphabetically"
    sort "$TEMP_FILE" -o "${TEMP_FILE}.sorted"
    mv "${TEMP_FILE}.sorted" "$TEMP_FILE"
fi

# Final count
FINAL_COUNT=$(wc -l < "$TEMP_FILE")
REMOVED=$((INITIAL_COUNT - FINAL_COUNT))

echo -e "\n${GREEN}[+] Processing Complete${NC}"
echo "================================================"
echo -e "Initial words:    ${INITIAL_COUNT}"
echo -e "Final words:      ${FINAL_COUNT}"
echo -e "Removed:          ${REMOVED} ($(awk "BEGIN {printf \"%.1f\", ($REMOVED/$INITIAL_COUNT)*100}")%)"
echo "================================================"

# Save or show sample
if [[ "$COUNT_ONLY" = false ]]; then
    mv "$TEMP_FILE" "$OUTPUT_FILE"
    echo -e "${GREEN}[+] Merged wordlist saved to: $OUTPUT_FILE${NC}"
    
    # Show sample
    echo -e "\n${YELLOW}[*] Sample (first 10 entries):${NC}"
    head -n 10 "$OUTPUT_FILE" | while read -r line; do
        echo "  $line"
    done
    
    # File size
    SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
    echo -e "\n${BLUE}[*] File size: $SIZE${NC}"
else
    rm "$TEMP_FILE"
fi

# Usage examples:
# ./wordlist-merger.sh -i "dir1/*.txt dir2/*.txt" -o merged.txt
# ./wordlist-merger.sh -i "*.txt" -m 3 -M 20 -l -o filtered.txt
