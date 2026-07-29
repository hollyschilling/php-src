--TEST--
Composite (DNF) arguments: duplicate members are a compile error
--FILE--
<?php
class Vec<T> {}
class A {}
$x = new Vec<A|a>();
?>
--EXPECTF--
Fatal error: Duplicate type %s is redundant in %s on line %d
