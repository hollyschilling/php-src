--TEST--
Generics packs: declarations parse with the pack first, mid, or last, with and without bounds
--FILE--
<?php
class Zip<...Ts> {}
class Pair<...Tp, R> {}
class Tri<A, ...Ts, Z> {}
interface Merger<...Ts implements Countable> {}
class Bound<...Ts extends Exception> {}
echo "ok\n";
var_dump(class_exists('Zip', false), interface_exists('Merger', false));
?>
--EXPECT--
ok
bool(true)
bool(true)
