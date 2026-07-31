--TEST--
Generics packs: spread is not valid where type arguments must be concrete
--FILE--
<?php
class Vec<T> {}
function f(Vec<...Ts> $x) {}
?>
--EXPECTF--
Fatal error: Cannot use ... in a generic type argument (type arguments must be concrete in this version) in %s on line %d
