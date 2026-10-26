--TEST--
'null' is a union member, never a standalone type argument
--FILE--
<?php
class Vec<T> {}
$x = new Vec<null>();
?>
--EXPECTF--
Fatal error: Built-in type "null" cannot be used as a generic type argument in %s on line %d
