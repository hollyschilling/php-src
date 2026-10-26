--TEST--
Generics M1: duplicate type parameter names are a compile error
--FILE--
<?php
class Box<T, t> {}
?>
--EXPECTF--
Fatal error: Duplicate generic type parameter t in %s on line %d
