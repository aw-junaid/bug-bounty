## The Reality: SQL Query Processing Pipeline

When you write:
```sql
SELECT username, email FROM users WHERE user_id = 5;
```

Here's what actually happens inside the database engine:

## 1. LEXER (Text → Tokens) in C

```c
// ============================================
// STEP 1: LEXER - Breaks SQL text into tokens
// Input:  "SELECT username, email FROM users WHERE user_id = 5;"
// Output: Token stream
// ============================================

typedef enum {
    TOKEN_SELECT,      // Keyword: SELECT
    TOKEN_FROM,        // Keyword: FROM
    TOKEN_WHERE,       // Keyword: WHERE
    TOKEN_IDENTIFIER,  // Column/table names: username, email, users
    TOKEN_COMMA,       // Punctuation: ,
    TOKEN_EQUALS,      // Operator: =
    TOKEN_NUMBER,      // Literal: 5
    TOKEN_SEMICOLON,   // Terminator: ;
    TOKEN_EOF          // End of input
} TokenType;

typedef struct {
    TokenType type;      // What kind of token
    char value[64];      // The actual text
    int line;            // Line number for errors
    int column;          // Column number for errors
} Token;

// The Lexer function (Tokenizer)
// Input: SQL string pointer in RDI register
// Output: Array of tokens, count in RAX
Token* lexer(const char* sql_input, int* token_count) {
    // RDI = pointer to "SELECT username, email FROM..."
    // RSI = pointer to token_count variable
    
    Token tokens[100];       // Fixed token array (stack)
    const char* cursor = sql_input;  // R8 = current position
    int count = 0;           // RCX = token counter
    
    while (*cursor != '\0') {  // Loop until end of string
        char c = *cursor;      // Load current character into AL
        
        // Skip whitespace
        if (c == ' ' || c == '\t' || c == '\n') {
            cursor++;
            continue;
        }
        
        // Check for keywords S-E-L-E-C-T
        if (strncmp(cursor, "SELECT", 6) == 0) {
            tokens[count].type = TOKEN_SELECT;
            strcpy(tokens[count].value, "SELECT");
            cursor += 6;
            count++;
        }
        // Check for F-R-O-M
        else if (strncmp(cursor, "FROM", 4) == 0) {
            tokens[count].type = TOKEN_FROM;
            strcpy(tokens[count].value, "FROM");
            cursor += 4;
            count++;
        }
        // Check for W-H-E-R-E
        else if (strncmp(cursor, "WHERE", 5) == 0) {
            tokens[count].type = TOKEN_WHERE;
            strcpy(tokens[count].value, "WHERE");
            cursor += 5;
            count++;
        }
        // Check for identifiers (column names)
        else if (isalpha(c)) {
            int i = 0;
            while (isalnum(*cursor) || *cursor == '_') {
                tokens[count].value[i++] = *cursor++;
            }
            tokens[count].value[i] = '\0';
            tokens[count].type = TOKEN_IDENTIFIER;
            count++;
        }
        // Check for numbers
        else if (isdigit(c)) {
            int i = 0;
            while (isdigit(*cursor)) {
                tokens[count].value[i++] = *cursor++;
            }
            tokens[count].value[i] = '\0';
            tokens[count].type = TOKEN_NUMBER;
            count++;
        }
        // Other characters
        else {
            switch(c) {
                case ',': tokens[count].type = TOKEN_COMMA; break;
                case '=': tokens[count].type = TOKEN_EQUALS; break;
                case ';': tokens[count].type = TOKEN_SEMICOLON; break;
            }
            tokens[count].value[0] = c;
            tokens[count].value[1] = '\0';
            cursor++;
            count++;
        }
    }
    
    tokens[count].type = TOKEN_EOF;
    *token_count = count;
    return tokens;  // Return token array
}

// After lexer runs, token array looks like:
// [SELECT] [username] [,] [email] [FROM] [users] [WHERE] [user_id] [=] [5] [;]
```

### Lexer in Assembly (Simplified)

```asm
; RDI = SQL string pointer "SELECT username..."
; RSI = token_count pointer
lexer:
    push rbp
    mov rbp, rsp
    
    ; R8 = cursor (current position in SQL string)
    mov r8, rdi              ; R8 = input string pointer
    
    ; RBX = token array base (stack allocated)
    sub rsp, 6400            ; Allocate 100 tokens * 64 bytes
    
    ; RCX = token counter
    xor rcx, rcx             ; RCX = 0 (count = 0)
    
lexer_loop:
    mov al, [r8]             ; AL = current character (*cursor)
    test al, al              ; Check for null terminator
    jz lexer_done            ; If null, done
    
    ; Check for space
    cmp al, ' '              ; Compare with space
    je skip_whitespace       ; Skip if space
    
    ; Compare with "SELECT" (check first 6 bytes)
    ; Load 6 bytes into RAX for comparison
    mov rax, [r8]            ; RAX = first 8 bytes at cursor
    ; Mask to compare only 6 bytes: 'SELECT'
    mov r9, 0x005443454C4553  ; R9 = "SELECT\0" in hex (little-endian)
    and rax, 0x0000FFFFFFFFFFFF ; Mask upper bytes
    cmp rax, r9
    je token_select_found
    
    ; Similar checks for FROM, WHERE, etc...
    
    jmp lexer_loop
    
skip_whitespace:
    inc r8                   ; cursor++
    jmp lexer_loop
    
token_select_found:
    ; Store SELECT token
    mov rdi, rcx             ; RDI = token index
    imul rdi, 64             ; RDI = offset (index * 64)
    add rdi, rbx             ; RDI = &tokens[count]
    mov dword [rdi], 0       ; type = TOKEN_SELECT (0)
    ; Copy "SELECT" string
    lea rsi, [r8]            ; Source = cursor
    add rdi, 8               ; Destination = value field
    ; ... copy string ...
    add r8, 6                ; cursor += 6
    inc rcx                  ; count++
    jmp lexer_loop
```

## 2. PARSER (Tokens → Abstract Syntax Tree) in C

```c
// ============================================
// STEP 2: PARSER - Build query tree from tokens
// Input:  Token array from lexer
// Output: AST (Abstract Syntax Tree)
// ============================================

// AST Node types
typedef enum {
    NODE_SELECT,     // SELECT statement root
    NODE_COLUMN,     // Column reference
    NODE_TABLE,      // Table reference
    NODE_EQUALS,     // = comparison
    NODE_LITERAL     // Constant value
} NodeType;

typedef struct ASTNode {
    NodeType type;
    char name[64];           // Table/column name
    int value;               // For literal numbers
    struct ASTNode* left;    // Left child (WHERE condition)
    struct ASTNode* right;   // Right child (column list)
    struct ASTNode* next;    // Next in list (multiple columns)
} ASTNode;

// The Parser function
// Input: Token array pointer in RDI
// Output: AST root node pointer in RAX
ASTNode* parser(Token* tokens) {
    // RDI = token array
    // We'll use R8 as current token index
    
    ASTNode* root = NULL;        // Root of query tree
    ASTNode* columns = NULL;     // List of SELECT columns
    int pos = 0;                 // Current position in tokens
    
    // Expect SELECT keyword first
    if (tokens[pos].type != TOKEN_SELECT) {
        printf("Error: Expected SELECT\n");
        return NULL;
    }
    pos++;  // Move past SELECT
    
    // Parse column list: username, email
    while (tokens[pos].type != TOKEN_FROM) {
        if (tokens[pos].type == TOKEN_IDENTIFIER) {
            // Create column node
            ASTNode* col = malloc(sizeof(ASTNode));
            col->type = NODE_COLUMN;
            strcpy(col->name, tokens[pos].value);
            col->next = columns;     // Add to column list
            columns = col;
            pos++;
            
            // Skip comma if present
            if (tokens[pos].type == TOKEN_COMMA) {
                pos++;
            }
        }
    }
    
    // Expect FROM keyword
    pos++;  // Move past FROM
    
    // Parse table name: users
    ASTNode* table = malloc(sizeof(ASTNode));
    table->type = NODE_TABLE;
    strcpy(table->name, tokens[pos].value);
    pos++;
    
    // Check for WHERE clause
    ASTNode* where = NULL;
    if (tokens[pos].type == TOKEN_WHERE) {
        pos++;  // Move past WHERE
        
        // Left side of WHERE: user_id
        ASTNode* left = malloc(sizeof(ASTNode));
        left->type = NODE_COLUMN;
        strcpy(left->name, tokens[pos].value);
        pos += 2;  // Skip identifier and =
        
        // Right side of WHERE: 5
        ASTNode* right = malloc(sizeof(ASTNode));
        right->type = NODE_LITERAL;
        right->value = atoi(tokens[pos].value);
        
        // Create comparison node
        where = malloc(sizeof(ASTNode));
        where->type = NODE_EQUALS;
        where->left = left;      // user_id
        where->right = right;    // 5
        pos++;
    }
    
    // Build root node
    root = malloc(sizeof(ASTNode));
    root->type = NODE_SELECT;
    root->left = where;      // WHERE clause (or NULL)
    root->right = columns;   // SELECT columns
    
    return root;  // Return AST
}

// After parser, tree structure:
//         [SELECT]
//         /      \
//    [WHERE]    [COLUMNS]
//     /    \     /      \
// [user_id] [5] [username] [email]
//   (col)  (lit)   (col)    (col)
```

### Parser in Assembly (Register Mapping)

```asm
; RDI = tokens array pointer
; Returns RAX = AST root pointer
parser:
    push rbp
    mov rbp, rsp
    
    ; R12 = current position (pos)
    xor r12, r12             ; pos = 0
    
    ; R13 = columns list head
    xor r13, r13             ; columns = NULL
    
    ; R14 = table node
    xor r14, r14             ; table = NULL
    
    ; R15 = where clause
    xor r15, r15             ; where = NULL
    
    ; Allocate root SELECT node
    mov rdi, 24             ; sizeof(ASTNode) = 24 bytes
    call malloc              ; RAX = allocated memory
    mov rbx, rax             ; RBX = root node
    
parse_columns:
    ; Calculate current token address
    mov rax, r12             ; RAX = pos
    imul rax, 64             ; RAX = pos * sizeof(Token)
    add rax, rdi             ; RAX = &tokens[pos]
    
    ; Check token type
    mov eax, [rax]           ; EAX = tokens[pos].type
    cmp eax, TOKEN_FROM
    je parse_from
    
    ; Create column node
    mov rdi, 24
    call malloc
    ; Setup column node...
    add r12, 1               ; pos++
    jmp parse_columns
    
parse_from:
    ; Parse table name
    add r12, 1               ; Skip FROM token
    ; Create table node...
    
parse_where:
    ; Check for WHERE
    ; Create comparison node...
    
    mov rax, rbx             ; Return root
    pop rbp
    ret
```

## 3. EXECUTOR (AST → Actual Data Operations) in C

```c
// ============================================
// STEP 3: EXECUTOR - Run query against actual data
// This is where the real work happens
// ============================================

// The actual data storage (simulated table)
typedef struct {
    int user_id;
    char username[32];
    char email[64];
    char password_hash[64];
} UserRow;

UserRow table_data[] = {
    {1, "alice",   "alice@test.com",   "hash111"},
    {2, "bob",     "bob@test.com",     "hash222"},
    {3, "charlie", "charlie@test.com", "hash333"},
    {4, "diana",   "diana@test.com",   "hash444"},
    {5, "eve",     "eve@test.com",     "hash555"},
};
int row_count = 5;

// Execute SELECT query
// Input: AST root in RDI
// Output: Result rows in RAX
void execute_select(ASTNode* ast) {
    // RDI = AST pointer
    
    // Step 3a: Get table reference
    ASTNode* from_clause = ast->right;  // Skip to FROM
    while (from_clause->type != NODE_TABLE) {
        from_clause = from_clause->next;
    }
    char* table_name = from_clause->name;  // "users"
    
    // Step 3b: Get column list from SELECT
    ASTNode* columns = ast->right;  // Column list
    // columns -> username -> email -> NULL
    
    // Step 3c: Get WHERE condition
    ASTNode* where = ast->left;  // WHERE tree
    // where->left = column "user_id"
    // where->right = literal "5"
    
    // Step 3d: SCAN TABLE (Full table scan)
    // This is like: for (int i = 0; i < row_count; i++)
    for (int i = 0; i < row_count; i++) {
        UserRow* current_row = &table_data[i];
        
        // Step 3e: CHECK WHERE CLAUSE
        int match = 1;  // Assume match if no WHERE
        
        if (where != NULL) {
            // Get column name from WHERE
            char* where_col = where->left->name;  // "user_id"
            
            // Get actual value from current row
            int row_value = 0;
            if (strcmp(where_col, "user_id") == 0) {
                row_value = current_row->user_id;
            }
            // ... other columns ...
            
            // Get target value from WHERE
            int target = where->right->value;  // 5
            
            // Compare values
            match = (row_value == target);
        }
        
        // Step 3f: IF MATCH, OUTPUT ROW
        if (match) {
            // Print each requested column
            ASTNode* col = columns;
            while (col != NULL) {
                if (strcmp(col->name, "username") == 0) {
                    printf("%s ", current_row->username);
                }
                else if (strcmp(col->name, "email") == 0) {
                    printf("%s ", current_row->email);
                }
                col = col->next;
            }
            printf("\n");
        }
    }
}
// Output: "eve eve@test.com"
```

### Executor in Assembly (The Core Loop)

```asm
; RDI = AST pointer
execute_select:
    push rbp
    mov rbp, rsp
    
    ; Save callee-saved registers
    push r12                 ; Row counter (i)
    push r13                 ; Current row pointer
    push r14                 ; Match flag
    push r15                 ; WHERE column value
    
    ; Initialize row counter
    xor r12, r12             ; R12 = 0 (i = 0)
    
table_scan_loop:
    ; Check if we've scanned all rows
    cmp r12, [row_count]     ; Compare i with row_count
    jge scan_done            ; If i >= row_count, done
    
    ; Calculate current row address
    mov rax, r12             ; RAX = i
    imul rax, 168            ; RAX = i * sizeof(UserRow) [168 bytes]
    lea r13, [table_data + rax] ; R13 = &table_data[i]
    
    ; ---- CHECK WHERE CLAUSE ----
    mov r14, 1               ; R14 = match = 1 (assume true)
    
    ; Load WHERE column name
    mov r8, [rdi + 8]        ; R8 = ast->left (WHERE node)
    test r8, r8              ; Check if WHERE exists
    jz skip_where            ; If NULL, skip WHERE check
    
    ; Get column value from current row
    ; R13 points to current row
    ; Check if column is "user_id"
    mov eax, [r13 + 0]       ; EAX = current_row->user_id (offset 0)
    mov r15, rax             ; R15 = row value
    
    ; Get target value from WHERE
    mov r9, [r8 + 16]        ; R9 = where->right (literal node)
    mov r10d, [r9 + 8]       ; R10 = where->right->value (5)
    
    ; Compare row value with target
    cmp r15d, r10d           ; Compare row_value (R15) with target (R10)
    sete r14b                ; R14 = (row_value == target) ? 1 : 0
    
skip_where:
    ; If no match, skip this row
    test r14, r14            ; Check match flag
    jz next_row              ; If 0, go to next row
    
    ; ---- OUTPUT MATCHED ROW ----
    ; Print username column
    ; username is at offset 4 in UserRow
    lea rdi, [r13 + 4]       ; RDI = current_row->username
    call printf              ; Print username
    
    ; Print space
    mov rdi, ' '
    call putchar
    
    ; Print email column
    ; email is at offset 36 in UserRow
    lea rdi, [r13 + 36]      ; RDI = current_row->email  
    call printf              ; Print email
    
    ; Print newline
    mov rdi, 10              ; '\n'
    call putchar
    
next_row:
    inc r12                  ; i++
    jmp table_scan_loop      ; Continue scan
    
scan_done:
    pop r15
    pop r14
    pop r13
    pop r12
    pop rbp
    ret
```

## 4. Complete Data Flow with Register States

```
SQL Query: "SELECT username, email FROM users WHERE user_id = 5;"

┌─────────────────────────────────────────────────────────┐
│ STEP 1: LEXER                                           │
│ RDI = points to "SELECT username..."                    │
│ RSI = points to output buffer                           │
│ ┌──────────────────────────────────────┐               │
│ │ AL = 'S' 'E' 'L' 'E' 'C' 'T' ...   │ character loop│
│ │ RBX = token buffer base              │               │
│ │ RCX = token counter (6 tokens)       │               │
│ └──────────────────────────────────────┘               │
│ Output: [SELECT][username][,][email][FROM][users]      │
│         [WHERE][user_id][=][5][;]                      │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 2: PARSER                                          │
│ RDI = token array pointer                               │
│ ┌──────────────────────────────────────┐               │
│ │ R12 = position in tokens (0..10)     │ parse loop   │
│ │ R13 = column list (username→email)   │               │
│ │ R14 = table node ("users")           │               │
│ │ R15 = WHERE clause (user_id=5)       │               │
│ │ RAX = malloc() return for each node  │               │
│ └──────────────────────────────────────┘               │
│                                                          │
│         [SELECT]                                        │
│         /      \                                        │
│    [WHERE]    [COL_LIST]                                │
│     /    \     /      \                                 │
│ [user_id] [5] [username]─→[email]                      │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 3: EXECUTOR                                        │
│ RDI = AST root pointer                                  │
│ ┌──────────────────────────────────────┐               │
│ │ R12 = row counter (0→1→2→3→4→5)     │ scan loop    │
│ │ R13 = current row pointer            │ (table_data) │
│ │ R14 = match flag (1/0)               │               │
│ │ R15 = row's user_id value            │               │
│ │ R10 = target value (5)               │               │
│ └──────────────────────────────────────┘               │
│                                                          │
│ Iteration 1: user_id=1, R15=1, R10=5 → R14=0 (skip)    │
│ Iteration 2: user_id=2, R15=2, R10=5 → R14=0 (skip)    │
│ Iteration 3: user_id=3, R15=3, R10=5 → R14=0 (skip)    │
│ Iteration 4: user_id=4, R15=4, R10=5 → R14=0 (skip)    │
│ Iteration 5: user_id=5, R15=5, R10=5 → R14=1 (MATCH!)  │
│   → Output: "eve eve@test.com"                          │
└─────────────────────────────────────────────────────────┘
```

## 5. The REAL SQL Database Code (SQLite Example)

SQLite is written in C. Here's how it actually processes SELECT:

```c
// From SQLite source code (simplified)
// This is REAL production database code

// The VDBE (Virtual Database Engine) executes bytecode
// SQL → Tokens → AST → Bytecode → Execution

// For "SELECT username FROM users WHERE user_id=5"
// SQLite generates this bytecode:

int sqlite3VdbeExec(Vdbe *p) {
    // Register usage in VDBE:
    // r[1] = cursor for table scan
    // r[2] = user_id column value
    // r[3] = target value (5)
    // r[4] = username column value
    // r[5] = result flag
    
    for(pc=0; !rc; pc++) {
        switch(pOp->opcode) {
            
            case OP_OpenRead:  // Open table for reading
                // Open cursor on "users" table
                pCur = allocateCursor();
                pCur->pgnoRoot = pOp->p2;  // Root page
                break;
                
            case OP_Rewind:    // Go to first row
                // Move cursor to beginning of table
                rc = sqlite3BtreeFirst(pCur, &res);
                break;
                
            case OP_Column:    // Read column value
                // Extract column from current row
                // pOp->p1 = cursor number
                // pOp->p2 = column index
                int col = pOp->p2;
                if (col == 0) {  // user_id column
                    r[pOp->p3] = pCur->aData[0];  // Store in register
                }
                break;
                
            case OP_Ne:        // Compare: Not Equal
                // If r[P1] != r[P3], jump to P2
                if (r[pOp->p1] != r[pOp->p3]) {
                    pc = pOp->p2 - 1;  // Jump to next row
                }
                break;
                
            case OP_ResultRow: // Output current row
                // Send row to application
                // r[4] contains username
                sqlite3_result_text(ctx, r[4], -1);
                break;
                
            case OP_Next:      // Move to next row
                rc = sqlite3BtreeNext(pCur, &res);
                if (res == 0) {
                    pc = pOp->p2 - 1;  // Loop back
                }
                break;
        }
    }
}

// The actual bytecode generated:
// 1: OpenRead 0, users       (open table, cursor 0)
// 2: Rewind 0, 10            (go to first row, exit at 10)
// 3: Column 0, 0, r1         (read user_id column to r1)
// 4: Integer 5, r2           (load constant 5 to r2)
// 5: Ne r1, r2, 9            (if r1!=r2, jump to 9)
// 6: Column 0, 1, r3         (read username column to r3)
// 7: ResultRow r3            (output r3)
// 8: Next 0, 3               (next row, jump to 3)
// 9: Close 0                 (close cursor)
// 10: Halt                   (end)
```

## Summary: What Happens When You Run SELECT

The register flow for `SELECT username FROM users WHERE user_id = 5`:

1. **RDI gets SQL string** → Lexer breaks "SELECT" into token using AL register checking characters
2. **RDI gets tokens** → Parser builds tree: RCX counts tokens, R12 tracks position, malloc() allocates nodes
3. **RDI gets AST** → Executor scans table: R12 loops rows, R13 points to current row, R15 holds row value, R10 holds target (5)
4. **Comparison**: `CMP R15, R10` → `SETE R14B` sets match flag
5. **Output**: If match, RDI points to username string for printf

The key insight: **SQL is just C code** that:
- Walks through text character by character (lexer)
- Builds a tree structure (parser)  
- Scans arrays comparing values (executor)
- All using loops, pointers, and comparisons in registers!
