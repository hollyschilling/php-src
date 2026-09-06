#!/usr/bin/env bash
#
# Fetch the two third-party projects the real-world workloads run against, and
# produce a native-generics conversion of one of them.
#
#   nikic/PHP-Parser        — a large, ordinary, generics-free application.
#                             Parsing its own source is the "does this branch
#                             slow down code that uses no generics" workload.
#   doctrine/collections    — a genuinely generic library. Checked out twice:
#                             once unmodified, once converted to native
#                             generics, so the same pipeline can be measured
#                             both ways.
#
# Both are plain PHP with no runtime dependencies for the code paths used here,
# so this needs `git` and nothing else — no Composer, no PECL, no network at
# measurement time.
#
# Run from the php-src root:  bench-harness/realworld/setup.sh
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORK="${REALWORLD_WORK:-${TMPDIR:-/tmp}/generics-bench-realworld}"

# Pinned so the numbers are reproducible.
PHP_PARSER_TAG="${PHP_PARSER_TAG:-v5.8.0}"
COLLECTIONS_TAG="${COLLECTIONS_TAG:-2.6.0}"

mkdir -p "$WORK"

clone_at() { # <url> <tag> <dest>
    [ -d "$3" ] && return 0
    echo "=== clone $(basename "$3") @ $2 ==="
    git clone --quiet --depth 1 --branch "$2" "$1" "$3"
}

clone_at https://github.com/nikic/PHP-Parser.git      "$PHP_PARSER_TAG"  "$WORK/php-parser"
clone_at https://github.com/doctrine/collections.git  "$COLLECTIONS_TAG" "$WORK/collections"

if [ ! -d "$WORK/collections-generic" ]; then
    echo "=== convert doctrine/collections to native generics ==="
    cp -r "$WORK/collections" "$WORK/collections-generic"
    "$HERE/convert-doctrine.sh" "$WORK/collections-generic/src"
fi

echo
echo "Real-world workloads ready in $WORK:"
echo "  php-parser          $PHP_PARSER_TAG   ($(find "$WORK/php-parser/lib" -name '*.php' | wc -l) source files)"
echo "  collections         $COLLECTIONS_TAG  (unmodified)"
echo "  collections-generic $COLLECTIONS_TAG  (converted)"
