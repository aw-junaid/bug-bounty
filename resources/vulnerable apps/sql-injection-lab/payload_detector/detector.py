import re
import json
from typing import Dict, List, Any

class SQLInjectionDetector:
    def __init__(self):
        self.payload_patterns = self.load_payloads()
        
    def load_payloads(self) -> Dict[str, List[str]]:
        """Load SQL injection payload patterns"""
        return {
            'authentication_bypass': [
                r"'\s*OR\s+'1'='1",
                r"'\s*OR\s+1=1",
                r"admin'\s*--",
                r"'\s*OR\s+'x'='x",
                r"'\s*OR\s+'\w+'='\w+",
                r"'\s*OR\s+\d+=\d+",
                r"'\)\s*OR\s*\(",
                r"'\s*OR\s+'1'='1'\s*--",
            ],
            'union_based': [
                r"UNION\s+SELECT",
                r"UNION\s+ALL\s+SELECT",
                r"\)\s*UNION\s+SELECT",
                r"'\s*UNION\s+SELECT",
            ],
            'boolean_based': [
                r"AND\s+\d+=\d+",
                r"OR\s+\d+=\d+",
                r"AND\s+'[^']*'='[^']*'",
                r"AND\s+SUBSTRING",
                r"AND\s+MID\(",
                r"AND\s+ASCII\(",
                r"AND\s+CHAR\(",
            ],
            'time_based': [
                r"WAITFOR\s+DELAY",
                r"SLEEP\s*\(",
                r"BENCHMARK\s*\(",
                r"pg_sleep\s*\(",
                r"AND\s+SLEEP\s*\(",
                r"';\s*WAITFOR\s+DELAY",
            ],
            'error_based': [
                r"EXTRACTVALUE\s*\(",
                r"UPDATEXML\s*\(",
                r"CONVERT\s*\(.*USING",
                r"AND\s+EXTRACTVALUE",
                r"AND\s+UPDATEXML",
                r"'\s*AND\s+EXTRACTVALUE",
            ],
            'stacked_queries': [
                r";\s*DROP\s+TABLE",
                r";\s*DELETE\s+FROM",
                r";\s*UPDATE\s+\w+\s+SET",
                r";\s*INSERT\s+INTO",
                r";\s*ALTER\s+TABLE",
                r";\s*CREATE\s+TABLE",
                r";\s*EXEC\s+",
            ],
            'information_schema': [
                r"information_schema",
                r"INFORMATION_SCHEMA",
                r"\.tables",
                r"\.columns",
                r"schema_name",
                r"table_name",
                r"column_name",
            ],
            'comments_obfuscation': [
                r"/\*.*\*/",
                r"--\s*\w*$",
                r"#\s*\w*$",
                r";\s*--",
                r"'\s*--\s",
                r"\s+--\s+",
            ],
            'function_injection': [
                r"CONCAT\s*\(",
                r"GROUP_CONCAT\s*\(",
                r"SELECT\s+\*\s+FROM",
                r"LOAD_FILE\s*\(",
                r"INTO\s+OUTFILE",
                r"INTO\s+DUMPFILE",
                r"@@version",
                r"@@datadir",
                r"@@hostname",
                r"DATABASE\s*\(",
                r"USER\s*\(",
                r"VERSION\s*\(",
                r"CURRENT_USER\s*\(",
            ],
            'encoding_evasion': [
                r"CHAR\s*\(.*\)",
                r"CHAR\s*\(",
                r"HEX\s*\(",
                r"UNHEX\s*\(",
                r"0x[0-9a-fA-F]+",
                r"%[0-9a-fA-F]{2}",
            ],
            'advanced_injection': [
                r"/\*!.*\*/",
                r"\|\|.*\|\|",
                r"&&.*&&",
                r"CASE\s+WHEN",
                r"IF\s*\(",
                r"ELT\s*\(",
                r"FIELD\s*\(",
                r"FIND_IN_SET\s*\(",
                r"ORDER\s+BY\s+\d+",
                r"LIMIT\s+\d+,\d+",
            ]
        }
    
    def detect_injection(self, input_text: str) -> Dict[str, Any]:
        """Detect SQL injection patterns in input text"""
        if not input_text:
            return {
                'has_injection': False,
                'injections_found': [],
                'risk_level': 'none',
                'details': []
            }
        
        injections_found = []
        details = []
        
        for category, patterns in self.payload_patterns.items():
            for pattern in patterns:
                match = re.search(pattern, input_text, re.IGNORECASE)
                if match:
                    injection_info = {
                        'category': category.replace('_', ' ').title(),
                        'pattern': pattern,
                        'matched_text': match.group(),
                        'position': match.span(),
                        'description': self.get_injection_description(category)
                    }
                    injections_found.append(injection_info)
                    details.append(injection_info)
        
        has_injection = len(injections_found) > 0
        risk_level = self.assess_risk_level(injections_found)
        
        return {
            'has_injection': has_injection,
            'injections_found': self.format_injections(injections_found),
            'risk_level': risk_level,
            'details': details,
            'total_patterns_matched': len(injections_found)
        }
    
    def get_injection_description(self, category: str) -> str:
        """Get description for injection category"""
        descriptions = {
            'authentication_bypass': 'Attempts to bypass authentication by manipulating SQL logic',
            'union_based': 'Uses UNION SELECT to extract data from other tables',
            'boolean_based': 'Uses boolean conditions to extract data bit by bit',
            'time_based': 'Uses time delays to extract data based on response time',
            'error_based': 'Forces database errors to extract information',
            'stacked_queries': 'Attempts to execute multiple SQL statements',
            'information_schema': 'Tries to access database metadata tables',
            'comments_obfuscation': 'Uses comments to modify query structure',
            'function_injection': 'Injects SQL functions to extract or manipulate data',
            'encoding_evasion': 'Uses encoding to bypass detection mechanisms',
            'advanced_injection': 'Advanced SQL injection techniques and obfuscation'
        }
        return descriptions.get(category, 'Unknown injection type')
    
    def format_injections(self, injections: List[Dict]) -> List[str]:
        """Format injection findings for display"""
        formatted = []
        seen_categories = set()
        
        for injection in injections:
            category = injection['category']
            if category not in seen_categories:
                formatted.append(f"{category}: {injection['description']}")
                seen_categories.add(category)
        
        return formatted
    
    def assess_risk_level(self, injections: List[Dict]) -> str:
        """Assess the risk level based on detected injections"""
        if not injections:
            return 'none'
        
        high_risk_categories = [
            'authentication_bypass',
            'stacked_queries',
            'union_based',
            'advanced_injection'
        ]
        
        medium_risk_categories = [
            'information_schema',
            'function_injection',
            'error_based',
            'time_based'
        ]
        
        has_high = any(inj['category'].replace(' ', '_').lower() in high_risk_categories 
                      for inj in injections)
        has_medium = any(inj['category'].replace(' ', '_').lower() in medium_risk_categories 
                        for inj in injections)
        
        if has_high:
            return 'critical'
        elif has_medium:
            return 'high'
        elif len(injections) > 3:
            return 'medium'
        else:
            return 'low'
