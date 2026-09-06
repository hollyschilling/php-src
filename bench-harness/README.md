# bench-harness — performance benchmarks for the generics branch

Reproducible instruction-count benchmarks for this branch against its merge-base
with `php/php-src` master. It answers the three questions an RFC gets asked:

1. **What does this cost code that doesn't use it?** Identical generics-free code
   on both builds.
2. **What does using it cost?** Generic code against a hand-written non-generic
   twin that computes exactly the same thing.
3. **How does it scale with the number of instantiations?** Instructions and
   memory per stamped instantiation, with and without preloading.

Everything here is new files under `bench-harness/`. The engine is untouched: the
class table already exposes what the benchmarks need to count, since an
instantiation is an ordinary class whose name contains `<`, so
`get_declared_classes()` and `get_declared_interfaces()` enumerate them without
any instrumentation to add, compile in, or leave switched off in production.

## Running it

```sh
bench-harness/build.sh              # the two release binaries (~15 min)
bench-harness/realworld/setup.sh    # two third-party checkouts (git only)
bench-harness/run.sh                # the matrix -> RESULTS.md (~30 min)
```

`run.sh` is self-contained and non-interactive: it measures everything, checks
each generic workload against its non-generic twin, and writes `RESULTS.md`
(the tables) and `results.json` (the raw counts). Nothing else needs to be run
in between, and no step depends on anything but `git`, `valgrind` and a compiler.

The full matrix runs everything under Callgrind, which is a ~50x slowdown, so it
takes a couple of hours. For a sanity check, one configuration is enough and
takes a few minutes:

```sh
CONFIGS=jitoff-warm bench-harness/run.sh
```

Env knobs: `WARMUP` (warmup requests per warm cell, default 5), `CONFIGS`
(a subset of the config list), `BENCH_ITERS` (collection-pipeline iterations),
`BENCH_FILES` (files php-parser parses per request), `BUILDROOT`,
`REALWORLD_WORK`.

## Method

**The metric is Callgrind instruction count (Ir).** Wall-clock would need CPU
pinning, which is unavailable in most container and CI environments, and would
then need repetition and statistics on top. Ir is deterministic: one measured
request is exact, and a 1.7% difference is a real 1.7% difference rather than
something to argue about. It is also what `php-src/benchmark/benchmark.php`
already uses.

**Cold and warm are measured separately**, through `php-cgi`'s `-T` flag:

| phase | invocation | what the count covers |
|-------|------------|-----------------------|
| cold | `php-cgi -T1` | one request including compilation — a cache miss |
| warm | `php-cgi -T<W>,1` | `W` untimed warmup requests fill opcache and let the tracing JIT compile, the counters are zeroed, then **one** request is measured in steady state |

Warm is the production-shaped number; cold shows first-hit cost. Warm matters
especially on this branch: templates are persisted to SHM by opcache, but
instantiations stamped at runtime are built in the per-request compiler arena and
rebuilt on every request, so a warm opcache does **not** amortise them. That cost
is invisible in a cold measurement and is exactly what section 4 of the results
quantifies.

> The builds must be configured `--with-valgrind`, or `-T`'s warmup phase cannot
> emit `CALLGRIND_ZERO_STATS`, Callgrind counts the warmup requests too, and warm
> silently reads *higher* than cold. `build.sh` sets it and warns if it is missing.

**Six configurations** are measured, chosen to match what this branch actually
does rather than to fill a grid:

| config | why it is here |
|--------|----------------|
| opcache off | the interpreter floor |
| JIT off, cold / warm | isolates stamping cost with templates served from SHM |
| tracing JIT, cold / warm | the only JIT mode that compiles generic bodies, via the per-clone trace extensions |
| function JIT, warm | generic bodies are deliberately excluded from function-mode JIT; this cell measures what that exclusion costs |

## Workloads

Self-contained, no network at measurement time:

- **`workloads/lib_generic.php`** — a small generic collection library: a generic
  interface, a generic class implementing it, a generic subclass, `parent::`,
  and `new static()` propagating the instantiation through `map`/`filter`/`slice`.
  **`lib_plain.php`** is its hand-written non-generic twin, structurally
  identical, with the element type fixed to `int`. Keep them in sync.
- **`workloads/collections_{generic,plain}.php`** — the same pipeline over both
  libraries. They print an accumulator, and `run.sh` refuses to measure if the
  two ever disagree — a benchmark comparing two programs is only meaningful if
  they compute the same thing.
- **`workloads/stamp_scale.php`** — stamps N distinct instantiations of one
  template. Run at two counts and subtracted, it gives the marginal cost of an
  instantiation in both instructions and bytes. `preload_scale.php` names the
  same closed world for the preload cell; both it and `typeargs.php` are written
  by `generate.php`.

Third-party, fetched once by `realworld/setup.sh`:

- **nikic/PHP-Parser** parsing its own source tree — a large, ordinary,
  generics-free application. This is the honest "tax" number.
- **doctrine/collections**, checked out twice: unmodified, and converted to
  native generics by `realworld/convert-doctrine.sh`. Six edits convert it,
  because the library returns `self`/`static` rather than naming its own types.

Both are plain PHP with no runtime dependencies on the paths used here, so
Composer is not needed.

## What is not measured, and why

- **Generic functions and generic methods.** This branch parameterises classes,
  interfaces and traits only, so there is nothing to measure. A function-level
  workload would be the natural addition if that changes.
- **Conversion with unchanged call sites.** With no type-parameter defaults,
  parameterising a class is a breaking change for every construction site, so
  the converted doctrine workload has its own driver rather than sharing one
  with the unconverted library. The diff between the two drivers is a fair
  picture of what converting a library costs its consumers.
- **`opcache.file_cache`.** It serialises templates but not instantiation
  bindings, so for generics it behaves like a cold SHM cache rather than like
  preloading, and adds a column that says nothing new.
