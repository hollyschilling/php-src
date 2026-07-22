--TEST--
Generics packs: only the declaring template's pack can be spread
--FILE--
<?php
interface I<T> {}
class X<A, ...Ts> implements I<...A> {}
?>
--EXPECTF--
Fatal error: Only a type parameter pack can be expanded with ... (A is not the declaring class's pack) in %s on line %d
