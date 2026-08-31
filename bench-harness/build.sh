#!/usr/bin/env bash
#
# Build the two pinned RELEASE binaries the benchmarks compare:
#
#   baseline : the branch's merge-base with php/php-src master (no generics)
#   generics : this branch's HEAD, plus any uncommitted working-tree changes
#
# Each is built in its own detached `git worktree` under $BUILDROOT, so your
# normal (probably --enable-debug) development build is left untouched.
#
# Both builds use IDENTICAL configure flags, so an instruction-count difference
# between them is attributable to the branch and not to the build.
#
# Usage, from the php-src root:
#     bench-harness/build.sh [baseline|generics|both]
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILDROOT="${BUILDROOT:-${TMPDIR:-/tmp}/generics-bench-builds}"
GENERICS_REF="${GENERICS_REF:-HEAD}"
# Merge-base against upstream master. Computed once and cached in the env so the
# two builds can never drift onto different baselines mid-run.
if [ -z "${BASELINE_REF:-}" ]; then
    BASELINE_REF="$(git -C "$SRC" merge-base "$GENERICS_REF" \
        "$(git -C "$SRC" rev-parse --verify --quiet upstream/master >/dev/null 2>&1 \
            && echo upstream/master || echo origin/master)")"
fi
JOBS="${JOBS:-$(nproc)}"

# Release flags:
#   --disable-debug      benchmark a production-shaped binary
#   --enable-opcache     required for the opcache/JIT cells
#   --enable-cgi         php-cgi -T drives the cold/warm split
#   --with-valgrind      REQUIRED: without it php-cgi's -T warmup phase cannot
#                        emit CALLGRIND_ZERO_STATS, callgrind then counts the
#                        warmup requests too, and "warm" silently reads HIGHER
#                        than "cold". This is the single easiest way to get
#                        nonsense numbers out of this harness.
#   --enable-mbstring    used by Zend/bench.php
#   --enable-tokenizer   used by the php-parser real-world workload
CONFIGURE_FLAGS="${CONFIGURE_FLAGS:---disable-debug --enable-opcache --enable-cgi --with-valgrind --enable-mbstring --enable-tokenizer}"

build_one() {
    local name="$1" ref="$2" carry_worktree_changes="$3"
    local dir="$BUILDROOT/${name}-src"

    echo "=== [$name] worktree @ $(git -C "$SRC" rev-parse --short "$ref") -> $dir ==="
    git -C "$SRC" worktree remove --force "$dir" 2>/dev/null || rm -rf "$dir"
    git -C "$SRC" worktree add --detach "$dir" "$ref" >/dev/null

    if [ "$carry_worktree_changes" = yes ]; then
        # Carry uncommitted engine changes into the benchmark build, so you can
        # measure a work-in-progress optimisation without committing it first.
        local patch
        patch="$(git -C "$SRC" diff --binary HEAD -- . ':(exclude)bench-harness')"
        if [ -n "$patch" ]; then
            echo "=== [$name] applying uncommitted working-tree patch ==="
            printf '%s\n' "$patch" | git -C "$dir" apply --index
        fi
    fi

    echo "=== [$name] buildconf + configure ==="
    ( cd "$dir" && ./buildconf --force >/dev/null 2>&1 \
        && ./configure $CONFIGURE_FLAGS >"/tmp/${name}-configure.log" 2>&1 ) \
        || { echo "configure failed; see /tmp/${name}-configure.log"; exit 1; }

    echo "=== [$name] make -j$JOBS ==="
    ( cd "$dir" && make -j"$JOBS" >"/tmp/${name}-build.log" 2>&1 ) \
        || { echo "build failed; see /tmp/${name}-build.log"; exit 1; }

    local ver; ver="$("$dir"/sapi/cli/php -v | head -1)"
    echo "=== [$name] built: $ver ==="
    case "$ver" in
        *DEBUG*) echo "ERROR: $name is a DEBUG build — benchmark numbers would be meaningless"; exit 1 ;;
    esac
    # A binary without valgrind support silently breaks the cold/warm split.
    # No `grep -q` here: under `set -o pipefail` it exits on the first match,
    # `php -i` dies writing to the closed pipe, and the check fails on every
    # build — including correct ones.
    "$dir"/sapi/cli/php -i | grep -- '--with-valgrind' >/dev/null \
        || echo "WARNING: $name was built without --with-valgrind; warm numbers will be wrong"
}

TARGET="${1:-both}"
mkdir -p "$BUILDROOT"
case "$TARGET" in
    baseline) build_one baseline "$BASELINE_REF" no ;;
    generics) build_one generics "$GENERICS_REF" yes ;;
    both)     build_one baseline "$BASELINE_REF" no
              build_one generics "$GENERICS_REF" yes ;;
    *) echo "usage: $0 [baseline|generics|both]"; exit 1 ;;
esac

echo
echo "Release binaries:"
echo "  baseline ($(git -C "$SRC" rev-parse --short "$BASELINE_REF")) : $BUILDROOT/baseline-src/sapi/cli/php"
echo "  generics ($(git -C "$SRC" rev-parse --short "$GENERICS_REF")) : $BUILDROOT/generics-src/sapi/cli/php"
