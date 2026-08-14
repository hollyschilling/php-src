#!/usr/bin/env bash
#
# Run the whole benchmark matrix and write bench-harness/RESULTS.md.
#
# One command, no interaction:
#
#     bench-harness/build.sh                # once: the two release binaries
#     bench-harness/realworld/setup.sh      # once: the third-party checkouts
#     bench-harness/run.sh                  # -> RESULTS.md + results.json
#
# Metric is Callgrind instruction count (Ir): deterministic, so a single
# measured request is exact and there is nothing to average or de-noise. Wall
# clock would need CPU pinning a container cannot give us.
#
# Cold vs warm comes from php-cgi's -T flag: `-T1` measures one request
# including compilation (a cache miss); `-T<W>,1` runs W untimed warmup requests
# that fill opcache and let the tracing JIT compile, zeroes the counters, and
# then measures one request in steady state. Warm is the production-shaped
# number; cold shows first-hit cost.
#
# Env knobs: WARMUP (default 5), CONFIGS (subset of the config list),
#            BENCH_ITERS, BENCH_FILES, BUILDROOT, REALWORLD_WORK, OUT.
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HARNESS="$SRC/bench-harness"
BUILDROOT="${BUILDROOT:-${TMPDIR:-/tmp}/generics-bench-builds}"
REALWORLD_WORK="${REALWORLD_WORK:-${TMPDIR:-/tmp}/generics-bench-realworld}"
WARMUP="${WARMUP:-5}"
OUT="${OUT:-$HARNESS}"

IR_DATA="$(mktemp)"
GROWTH_DATA="$(mktemp)"
trap 'rm -f "$IR_DATA" "$GROWTH_DATA" /tmp/cg.'"$$"'.*' EXIT

export REALWORLD_WORK
export BENCH_ITERS="${BENCH_ITERS:-2000}"
export BENCH_FILES="${BENCH_FILES:-120}"

CONFIGS="${CONFIGS:-off jitoff-cold jitoff-warm tracing-cold tracing-warm function-warm}"

# --- preconditions ----------------------------------------------------------

for build in baseline generics; do
    cgi="$BUILDROOT/$build-src/sapi/cgi/php-cgi"
    [ -x "$cgi" ] || { echo "Missing $cgi — run bench-harness/build.sh first" >&2; exit 1; }
    if "$cgi" -v | head -1 | grep -q DEBUG; then
        echo "ERROR: $build is a DEBUG build; its numbers would be meaningless" >&2; exit 1
    fi
done
[ -d "$REALWORLD_WORK/php-parser" ] \
    || { echo "Missing $REALWORLD_WORK — run bench-harness/realworld/setup.sh first" >&2; exit 1; }
command -v valgrind >/dev/null || { echo "valgrind is not installed" >&2; exit 1; }

# --- measurement ------------------------------------------------------------

# cfg_ini <config> — sets CFG_INI (array of -d flags) and CFG_T (the -T value).
cfg_ini() {
    local common=(-d opcache.enable=1 -d opcache.validate_timestamps=0)
    local jit=(-d opcache.jit_buffer_size=64M)
    case "$1" in
        off)           CFG_INI=(-d opcache.enable=0);                                CFG_T=1 ;;
        jitoff-cold)   CFG_INI=("${common[@]}" -d opcache.jit=disable);               CFG_T=1 ;;
        jitoff-warm)   CFG_INI=("${common[@]}" -d opcache.jit=disable);               CFG_T="$WARMUP,1" ;;
        tracing-cold)  CFG_INI=("${common[@]}" -d opcache.jit=tracing "${jit[@]}");   CFG_T=1 ;;
        tracing-warm)  CFG_INI=("${common[@]}" -d opcache.jit=tracing "${jit[@]}");   CFG_T="$WARMUP,1" ;;
        function-warm) CFG_INI=("${common[@]}" -d opcache.jit=function "${jit[@]}");  CFG_T="$WARMUP,1" ;;
        *) echo "unknown config: $1" >&2; exit 1 ;;
    esac
}

# ir <build> <config> <script> [extra -d flags...] — Callgrind Ir of one request
ir() {
    local build="$1" config="$2" script="$3"; shift 3
    cfg_ini "$config"
    # `|| true`: a cell that crashes the interpreter must not abort the run —
    # it is reported as a missing measurement and everything else still runs.
    valgrind --tool=callgrind --callgrind-out-file="/tmp/cg.$$.out" -- \
        "$BUILDROOT/$build-src/sapi/cgi/php-cgi" -T"$CFG_T" \
        -d max_execution_time=0 "${CFG_INI[@]}" "$@" "$script" \
        >/dev/null 2>"/tmp/cg.$$.err" || true
    grep -oE 'Collected : [0-9]+' "/tmp/cg.$$.err" | grep -oE '[0-9]+' | head -1 || true
}

# measure <workload> <build> <config> <script> [extra -d flags...]
measure() {
    local workload="$1" build="$2" config="$3"; shift 3
    local value; value="$(ir "$build" "$config" "$@" || true)"
    if [ -z "$value" ]; then
        echo "  !! no Ir collected for $workload/$build/$config — cell skipped" >&2
        grep -iE 'segmentation|fatal|error' "/tmp/cg.$$.err" | head -3 >&2 || true
        return 0
    fi
    printf '%s|%s|%s|%s\n' "$workload" "$build" "$config" "$value" >>"$IR_DATA"
    printf '  %-22s %-9s %-14s Ir=%s\n' "$workload" "$build" "$config" "$value"
}

W="$HARNESS/workloads"
R="$HARNESS/realworld/workloads"
CLI="$BUILDROOT/generics-src/sapi/cli/php"

# A benchmark that compares two programs is only meaningful if they compute the
# same thing. Each generic workload and its non-generic twin print an
# accumulator; if those ever diverge the numbers below are comparing different
# work, so refuse to measure.
echo "=== cross-check: each generic workload agrees with its non-generic twin ==="
check_pair() { # <label> <plain output> <generic output>
    if [ "$2" = "$3" ]; then
        printf '  %-14s %s\n' "$1" "$2"
    else
        echo "MISMATCH in $1: plain gave '$2', generic gave '$3'" >&2; exit 1
    fi
}
check_pair collections \
    "$("$CLI" "$W/collections_plain.php")" \
    "$("$CLI" "$W/collections_generic.php")"
check_pair doctrine \
    "$(COLLECTIONS_SRC="$REALWORLD_WORK/collections/src"         "$CLI" "$R/doctrine_pipeline.php")" \
    "$(COLLECTIONS_SRC="$REALWORLD_WORK/collections-generic/src" "$CLI" "$R/doctrine_pipeline_generic.php")"

echo "=== workloads that use no generics (the tax) ==="
export COLLECTIONS_SRC="$REALWORLD_WORK/collections/src"
for config in $CONFIGS; do
    for build in baseline generics; do
        measure canary            "$build" "$config" "$SRC/Zend/bench.php"
        measure phpparser         "$build" "$config" "$R/phpparser_parse.php"
        measure collections_plain "$build" "$config" "$W/collections_plain.php"
        measure doctrine_plain    "$build" "$config" "$R/doctrine_pipeline.php"
    done
done

echo "=== the same workloads written with generics (the cost of using them) ==="
export COLLECTIONS_SRC="$REALWORLD_WORK/collections-generic/src"
for config in $CONFIGS; do
    measure collections_generic "generics" "$config" "$W/collections_generic.php"
    measure doctrine_generic    "generics" "$config" "$R/doctrine_pipeline_generic.php"
done

echo "=== the generic workload preloaded (instantiations stamped once, into SHM) ==="
for config in $CONFIGS; do
    [ "$config" = off ] && continue          # preloading requires opcache
    measure collections_preloaded "generics" "$config" "$W/collections_generic.php" \
        -d "opcache.preload=$W/preload_generic.php" -d "opcache.preload_user=$(id -un)"
done

# growth <mode> <count> [extra -d flags...] — Ir and memory for one stamp count.
# Measured warm: preloading does its stamping once, at startup, and a cold
# measurement would count that one-time cost as if the request paid it.
growth() {
    local mode="$1" count="$2"; shift 2
    local value json
    value="$(STAMP_COUNT=$count ir generics jitoff-warm "$W/stamp_scale.php" "$@" || true)"
    json="$(STAMP_COUNT=$count "$CLI" -d opcache.enable_cli=1 "$@" "$W/stamp_scale.php" || true)"
    printf '%s|%s|%s|%s\n' "$mode" "$count" "${value:-0}" "$json" >>"$GROWTH_DATA"
    printf '  %-10s stamped=%-4s Ir=%-12s %s\n' "$mode" "$count" "${value:-crashed}" "$json"
}

echo "=== marginal cost of one more instantiation ==="
for count in 0 50 100 200; do
    growth runtime "$count"
done

echo "=== the same closed world, preloaded into SHM instead of stamped per request ==="
for count in 0 200; do
    growth preload "$count" \
        -d "opcache.preload=$W/preload_scale.php" -d "opcache.preload_user=$(id -un)"
done

# --- report -----------------------------------------------------------------

BASELINE_REF="$(git -C "$SRC" rev-parse --short "$(git -C "$SRC" merge-base HEAD origin/master)")"
GENERICS_REF="$(git -C "$SRC" rev-parse --short HEAD)"
GENERICS_BRANCH="$(git -C "$SRC" rev-parse --abbrev-ref HEAD)"

IR_DATA="$IR_DATA" GROWTH_DATA="$GROWTH_DATA" OUT="$OUT" WARMUP="$WARMUP" CONFIGS="$CONFIGS" \
BASELINE_REF="$BASELINE_REF" GENERICS_REF="$GENERICS_REF" GENERICS_BRANCH="$GENERICS_BRANCH" \
BENCH_ITERS="$BENCH_ITERS" BENCH_FILES="$BENCH_FILES" \
    "$BUILDROOT/generics-src/sapi/cli/php" "$HARNESS/report.php"

echo
echo "Wrote $OUT/RESULTS.md and $OUT/results.json"
