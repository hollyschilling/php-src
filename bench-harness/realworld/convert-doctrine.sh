#!/usr/bin/env bash
#
# Convert doctrine/collections' generic surface from @template docblocks to
# native generics, in place.
#
# doctrine/collections is a good conversion target because it is genuinely
# generic (its whole API is documented with @template TKey/T) and because its
# method signatures return `self`/`static` rather than naming the collection
# types — so parameterising the library itself takes the six edits below.
#
# What it does NOT hide is the call-site cost: with no type-parameter defaults
# on this branch, `new ArrayCollection($items)` stops working the moment the
# class is parameterised, and every construction site in every consumer has to
# spell its type arguments. That is the migration burden this workload is meant
# to expose, and it is why there is a separate _generic driver rather than one
# driver that runs against both variants.
#
# Built-in interfaces (Countable, IteratorAggregate, ArrayAccess, Stringable)
# stay un-parameterised: native generics cannot parameterise them, and the
# branch accepts a generic class implementing a non-generic interface.
#
# Usage: convert-doctrine.sh <collections/src dir>
set -euo pipefail
SRC="${1:?usage: convert-doctrine.sh <collections/src>}"

# repl <file> <exact old text> <new text> — fails loudly if the source moved,
# rather than silently producing an unconverted "converted" library.
repl() {
    local file="$SRC/$1" old="$2" new="$3"
    grep -qF -- "$old" "$file" || {
        echo "ERROR: pattern not found in $1 (doctrine version changed?): $old" >&2
        exit 1
    }
    old="$old" new="$new" perl -i -pe 'BEGIN { $o = $ENV{old}; $n = $ENV{new} } s/\Q$o\E/$n/' "$file"
    grep -qF -- "$new" "$file" || { echo "ERROR: replacement did not apply in $1" >&2; exit 1; }
}

repl Selectable.php \
    'interface Selectable' \
    'interface Selectable<TKey, T>'

repl ReadableCollection.php \
    'interface ReadableCollection extends Countable, IteratorAggregate' \
    'interface ReadableCollection<TKey, T> extends Countable, IteratorAggregate'

repl Collection.php \
    'interface Collection extends ReadableCollection, ArrayAccess' \
    'interface Collection<TKey, T> extends ReadableCollection<TKey, T>, ArrayAccess'

repl ArrayCollection.php \
    'class ArrayCollection implements Collection, Selectable, Stringable' \
    'class ArrayCollection<TKey, T> implements Collection<TKey, T>, Selectable<TKey, T>, Stringable'

repl AbstractLazyCollection.php \
    'abstract class AbstractLazyCollection implements Collection, Selectable' \
    'abstract class AbstractLazyCollection<TKey, T> implements Collection<TKey, T>, Selectable<TKey, T>'

repl AbstractLazyCollection.php \
    'protected Collection|null $collection;' \
    'protected Collection<TKey, T>|null $collection;'

> "$SRC/../CONVERSION.txt" <<'EOF'
doctrine/collections converted to native generics: 6 edits, all of them class
and interface declaration headers. No method signature needed changing, because
the library returns `self`/`static` throughout rather than naming its own types.

Not converted: the two `instanceof Selectable` checks in AbstractLazyCollection,
which still test against the template. AbstractLazyCollection is not exercised
by the benchmark driver.
EOF

echo "converted doctrine/collections in $SRC (6 edits)"
