--TEST--
Generics M1: built-in type names cannot be used as type parameter names
--FILE--
<?php
class Box<int> {}
?>
--EXPECTF--
Fatal error: Cannot use built-in type "int" as a generic type parameter name in %s on line %d
