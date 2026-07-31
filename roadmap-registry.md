# Holly Roadmap — engine namespace allocation registry

Cross-branch coordination for resources that collide **silently** (no textual
merge conflict — just corrupt behavior in the integrated build). **Rule: claim
here BEFORE implementing on any feature branch; verify claimed values against
current master at claim time** (upstream moves: new opcodes/flags land every cycle).

## VM opcodes (`Zend/zend_vm_opcodes.h`; verify max against master before claiming)

| Number | Name                  | Branch / feature        | Status |
|--------|-----------------------|-------------------------|--------|
| 212    | ZEND_BIND_EXTENSION   | extension-methods       | claimed, implemented |
| 213    | ZEND_RECV_RECEIVER    | extension-methods (scalars) | claimed, implemented |
| 214    | ZEND_REGISTER_MODULE  | modules                 | claimed, implemented (was 212 — collided with BIND_EXTENSION; renumbered `be5f28626e`, found by first roadmap rebuild 2026-07-16) |
| 215    | ZEND_FETCH_OBJ_RECEIVER (scoped-borrow receiver fetch) | structs | claimed 2026-07-18 (mutating methods; 212–214 left for extensions/modules) |
| 216+   | generics (instantiation fetch?) | generics       | unclaimed — next free |

## Class-entry flags — `ZEND_ACC_*` (mind the COLUMN: class / function / prop / const)

| Bit | Column(s)        | Name                     | Branch      | Status |
|-----|------------------|--------------------------|-------------|--------|
| 30  | func/prop/const  | ZEND_ACC_MODULE_INTERNAL | modules     | claimed, implemented (class column bit 30 = USE_GUARDS — different column, no clash, but NEVER extend MODULE_INTERNAL to the class column) |
| 31  | function         | ~~ZEND_ACC_MUTATING~~ RETIRED | structs | **bit 31 = upstream ZEND_ACC_STRICT_TYPES — collided** (strict_types methods misread as mutating; found by full-Zend-suite run on integrated roadmap 2026-07-20). Transport moved OFF fn_flags entirely to decl-attr ZEND_FN_IS_MUTATING (`a45a9a694b`); fn column has NO free bits (12–31 assigned upstream/branches, 0–11 parser modifier space) — **future transport markers must use decl attr or fn_flags2**. |
| TBD | class            | surfaces flags           | surfaces    | **audit surfaces branch and record here** |
| —   | class            | generics template flag   | generics    | moved to ce_flags2 bits 1–2 (see below) — no ce_flags class-column claim needed |

## ce_flags2 (`zend_class_entry.ce_flags2` — the second class-flags word)

| Bit | Name                   | Branch  | Status |
|-----|------------------------|---------|--------|
| 0   | ZEND_ACC2_VALUE_CLASS  | structs | claimed, implemented (supersedes the earlier "ce_flags bit 14" candidate) |
| 1   | ZEND_ACC2_GENERIC_TEMPLATE | generics | claimed 2026-07-19 (class column; CE is an uninstantiable template) |
| 2   | ZEND_ACC2_GENERIC_INSTANCE | generics | claimed 2026-07-19 (class column; CE is a stamped instantiation) |

## fn_flags2 (`zend_op_array.fn_flags2`)

| Bit | Name                       | Branch            | Status |
|-----|----------------------------|-------------------|--------|
| 1   | ZEND_ACC2_SCALAR_RECEIVER  | extension-methods | claimed, implemented |
| 2   | ZEND_ACC2_MUTATING         | structs           | claimed, implemented (was bit 1 — collided with SCALAR_RECEIVER; renumbered `c2b2897daf`, found by roadmap rebuild 2026-07-19) |
| 3   | ZEND_ACC2_GENERIC_SUBST_ARG_INFO | generics    | claimed 2026-07-19, implemented (`9b29be8a55`) — marks clone headers whose arg_info is arena-substituted; destroy_op_array restores the stored original before the final free |

## Tokens & contextual keywords (lexer lookahead family)

| Keyword / token   | Branch            | Notes |
|-------------------|-------------------|-------|
| `extension` (T_EXTENSION) | extension-methods | contextual, lookahead technique |
| `surface` (T_SURFACE)     | surfaces          | contextual, incl. `surface[` form |
| module-pattern tokens (`:>` etc.) | modules  | **audit modules branch and record exact tokens here** |
| `struct` (T_STRUCT)       | structs           | implemented — contextual, identifier lookahead; also postfix `mutating` in the receiver-modifier signature slot (reserves nothing) |
| T_GENERIC_OPEN            | generics          | implemented (`16a534bc82`) — lone `<` runs a bounded structural scan (zend_scan_is_generic_open); SCNG(generic_depth) tracks open lists, within which `>>` splits into two `>` (no grammar hack). New scanner-global + lex_state field generic_depth. |

## Class-fetch kinds (`ZEND_FETCH_CLASS_*`, low nibble of the fetch operand)

| Value | Name | Branch | Status |
|-------|------|--------|--------|
| 7 | ZEND_FETCH_CLASS_TYPE_PARAM (+ param index at bit 16) | generics | claimed 2026-07-19, implemented (`caecb6ebf2`) — upstream uses 0–6; claim here before adding kinds on any branch |

## Executor globals / class-table conventions

| Resource | Branch | Notes |
|----------|--------|-------|
| EG(extension_autoload_attempted) | extension-methods-autoload | lazily allocated per-request table |
| EG(generics_stamping) | generics | lazily allocated per-request cycle-guard set (`ceae0add9a`) |
| EG(generics_variance_cache) | generics | lazily allocated per-request table; `%p:%p` keys cache variance edges, `%p:decl` keys cache deep positional-check passes (`ae7d1fcaf3`); freed at executor TAIL |
| Class-table IS_ALIAS_PTR entries for named extensions | extension-methods-autoload | destroy_zend_class skips |
| Mangled instantiation names `fqcn<args>` (canonical FQ, lowercased key) | generics | planned — delimiters unspellable in declarations, no collision |
| Post-construct refcount escape check; reflection-write rejection on ZEND_ACC_VALUE_CLASS | structs | planned |

## Grammar productions (zend_language_parser.y virgin territory claimed)

- `class NAME <` … `>` — generics declaration site (currently a parse error: safe)
- `struct NAME {` — structs (currently a parse error after contextual lexing: safe)
- `surface NAME ;` / `surface[...]` modifier — surfaces
- `extension TYPE $recv {` / `use extension` — extension-methods
- module-pattern productions — **audit modules branch and enumerate here**

## Open audits

1. Enumerate surfaces branch ACC bits + tokens into this file.
2. Enumerate modules branch tokens/productions/globals into this file.
3. Re-verify opcode max and free class-column bits against master at each rebase.

## Generic methods (branch: generic-methods, 2026-07-22)
- fn_flags2 bit 4: `ZEND_ACC2_GENERIC_METHOD_TEMPLATE` (declaring op_array of `function m<U>`; clones clear it). Next free fn_flags2 bit: 5.
- ZEND_FETCH_CLASS flag 0x2000: `ZEND_FETCH_CLASS_TYPE_PARAM_METHOD` (TYPE_PARAM index addresses the executing FUNCTION's method params). Next free fetch-class flag: 0x4000.
- Class-name marker prefix `"\0\x01"`: symbolic generic reference resolved against the executing binding (`zend_generics_resolve_type_symbol`). Composes with the modules `"\0"` provenance marker via second-byte dispatch in `zend_fetch_class_by_name` — any new `"\0"`-prefixed marker MUST claim a distinct second byte here.
- `zend_ast_decl` widened to `child[6]`; `child[5]` = method generic parameter list (classes keep theirs in child[4]). `zend_ast_create_decl_ex` added.
- `zend_op_array` gained `generic_params` / `generic_binding` tail fields (persisted: params only, xlat-shared; binding asserted never persisted).
- `zend_generic_binding` gained `owned_names` vector (substituted composite type names; released in destroy_zend_class; interned+memdup at persist).

## 2026-07-26 (trace-JIT session)
- fn_flags2 bit 5: ZEND_ACC2_GENERIC_CONTEXT (generics branch) — closures declared in generic templates; JIT identity exclusion.
- zend_func_info.h flags bit 18: ZEND_FUNC_GENERIC_TRACE (generics branch) — informational only; the authoritative marker is zend_jit_op_array_trace_extension.generic_trace (func_info.flags is trace-compiler scratch).
- New ZEND_API hook: zend_generics_jit_clone_hook (zend_generics.h) — tracing JIT attaches per-clone trace extensions.
- JIT stub list additions: jit_stub_hybrid_generic_{func,loop}_trace_dispatch (zend_jit_ir.c).
