# Generic closure function-JIT regression benchmark

`workloads/jit_closure_churn.php` creates and invokes 10,000 closures from one
generic factory, changing the captured value each time. One invocation warms the
specialization before timing. Each sample runs in a fresh PHP process.

```sh
sapi/cli/php -n \
  -d opcache.enable_cli=1 \
  -d opcache.file_update_protection=0 \
  -d opcache.jit=function \
  -d opcache.jit_buffer_size=16M \
  bench-harness/workloads/jit_closure_churn.php 10000
```

Use `opcache.jit=disable` for the interpreter control. Compare release builds
with identical configure/compiler settings. The previous implementation is
commit `d8ee975efe14bbc41113eeed9ccbd852dbc935b4`.

Measured on Linux x86-64 on 2026-11-15; median of seven fresh processes per
configuration, with a 16 MiB JIT buffer:

| Configuration | Median milliseconds | JIT bytes consumed after warmup | Request memory growth, bytes |
| --- | ---: | ---: | ---: |
| Previous implementation, function JIT | 2057.24 | 4,800,000 | 3,081,344 |
| Request-local closure cache, function JIT | 11.44 | 0 | 853,120 |
| JIT disabled, same revised build | 9.10 | 0 | 853,120 |

The cache removes repeated IR compilation and entry-table allocation for this
binding. It does not make this tiny closure faster than interpretation: lookup
and dispatch still cost time. Wall-clock values vary on the shared host and are
illustrative, not a general application speedup claim. The code-buffer and memory
figures were stable across the seven samples.

The cache identity includes the template handler table, lexical scope, method
binding, function flags and concrete signature. Different captured values and
closure-local static variables still belong to each closure. `bindTo()` and
`Closure::call()` use the new scope; equal scopes with different substituted
signatures remain separate. First-class method callables are also covered.

Cache entries retain an owned function prototype and signature, not the closure
object or its captured/bound objects. Entries are discarded at request shutdown.
Consequently, reuse is within a request; this does not establish cross-request
native-code reuse for request-owned specializations. Distinct specializations
still consume code and metadata, and buffer exhaustion still falls back to the VM.

The dispatch offset now uses `sizeof(zend_op)` and handler-pointer size instead
of a fixed 64-bit shift. Execution was tested on Linux x86-64. x86-32 runtime
testing was unavailable because the host lacks 32-bit C startup objects/libraries;
AArch64 and Windows runtime validation also remain outstanding.

## Steady-state generic bodies and call-heavy control

`workloads/jit_generic_steady_state.php` warms the method and caller before timing.
The `loop` case executes three million integer-property additions inside one
method call. The `calls` case invokes a tiny method 200,000 times through the
same caller. Both have an identical hand-written, non-generic class control.
Every sample checks its returned value and arithmetic result.

```sh
sapi/cli/php -n \
  -d opcache.enable_cli=1 \
  -d opcache.file_update_protection=0 \
  -d opcache.jit=function \
  -d opcache.jit_buffer_size=16M \
  bench-harness/workloads/jit_generic_steady_state.php generic loop
```

Replace `generic` with `plain`, `loop` with `calls`, or `jit=function` with
`jit=disable` for the other cases. The old executable was built from Holly's
original branch, commit `5a287e592aa6779facc7b6fb1821f2a756d51131`, where generic
bodies remain interpreted even when function JIT is enabled. The revised
executable was built from `cc20fc915ba808bff7b29ba12eeeccfcf764211b`.

Linux x86-64 release builds, seven fresh processes per cell, median milliseconds:

| Configuration | Three-million-iteration method | 200,000 tiny-method calls |
| --- | ---: | ---: |
| Original branch, generic, function JIT enabled | 35.923 | 3.604 |
| Revised branch, generic, function JIT enabled | 17.520 | 1.811 |
| Original branch, plain, function JIT enabled | 18.759 | 1.886 |
| Revised branch, plain, function JIT enabled | 18.340 | 1.879 |
| Revised branch, generic, JIT disabled | 36.150 | 4.927 |
| Revised branch, plain, JIT disabled | 36.776 | 4.462 |

The hot generic method is about 2.05 times faster than the original interpreted
generic body in this workload. No case consumed additional JIT bytes after
warmup. The call-heavy plain control measures the full ordinary method-call path
against the specialization/dispatch path; it does not isolate the instruction
cost of the indirect jump alone. Short shared-host timings cannot establish that
the dispatch cost is zero or that the generic path is faster than the plain one.
With JIT disabled, the caller is interpreted as well, so that column is an
end-to-end control rather than a measurement of the callee alone.

## Cache lifetime and review notes

There is no explicit entry-count cap: each distinct cache identity adds a
prototype and extension until request shutdown. This is per distinct identity,
not per closure copy. Request-memory exhaustion and exhaustion of the fixed JIT
code buffer are separate limits.

Shutdown ordering is explicit: `shutdown_executor()` invokes extension
deactivators (`Zend/zend_execute_API.c`), OPcache's `accel_deactivate()` calls
`zend_jit_deactivate()`, and that destroys the cache. `main/main.c` destroys
`CG(arena)` later, after `zend_deactivate()` returns. The prototype destructor
therefore runs while its arena-owned header and signature are still alive.

The concrete signature cannot simply be removed from the key: after rebinding,
closures originating from `Factory<int>` and `Factory<string>` can have the same
template, new lexical scope and null method-binding pointer while retaining
different signatures. The regression test covers this collision. A cheaper key
would need to preserve that original specialization identity.

Shared-memory allocation failure in the function-JIT clone hook currently remains
fatal. Blindly retaining a template's extension, as suggested in review, is not a
safe substitute: its entry table may contain native checks for a different
signature. A graceful implementation needs an explicitly interpreter-only
fallback that cannot inherit incompatible compiled entries.
