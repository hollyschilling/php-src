--TEST--
Generics M2: non-scalar built-in types are rejected as generic type arguments
--FILE--
<?php
class Vec<T> {}
function f(Vec<mixed> $x): void {}
?>
--EXPECTF--
Fatal error: Built-in type "mixed" cannot be used as a generic type argument in %s on line %d
